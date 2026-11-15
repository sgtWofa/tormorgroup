<?php

/**
 * Cumulative customer withdrawal controller.
 *
 * Allows agents + managers/admins to withdraw against a customer's aggregate
 * balance (across all their receive records), not per-tracking-id. The
 * withdrawal amount is FIFO-allocated across the customer's oldest unpaid
 * receives, and a separate `pay` row is created per receive slice.
 *
 * Source is tagged as `customer_withdrawal` so the ledger can distinguish
 * these from legacy per-receive payouts and from customer-to-customer transfers.
 */

require_once plugin_path('helpers/customer-ledger.php');
require_once plugin_path('helpers/transfer-allocation.php');
require_once plugin_path('helpers/customer-assignment.php');

if(!(user_can('view_cashpickup') || user_can('add_cashpickup') || user_can('is_manager') || user_can('is_admin'))){
	message_fail('Access denied');
	redirect($admin_route.'/'.$plugin_route.'/customers');
}

$session = get_session();
$actorId = (int)($session->id ?? 0);
$customerId = (int)(URL(3) ?? ($req->post('customer_id') ?? 0));
$amount = (float)($req->post('amount') ?? 0);
$pickupCharge = (float)($req->post('pickup_charge') ?? 0);
$paymentMethodRaw = strtolower(trim((string)($req->post('payment_method') ?? '')));
$payoutChannel = strtolower(trim((string)($req->post('payout_channel') ?? '')));
$accountPaidTo = trim((string)($req->post('account_paid_to') ?? ''));
$confirmedBy = trim((string)($req->post('confirmed_by') ?? ''));
$denosJson = trim((string)($req->post('denos_json') ?? '[]'));
$cfaDenosJson = trim((string)($req->post('cfa_denos_json') ?? '[]'));
$cfaAmountInput = (float)($req->post('cfa_amount') ?? 0);
$notes = trim((string)($req->post('notes') ?? ''));
$idcardPhoto = trim((string)($req->post('idcard_photo') ?? ''));
$customerPhoto = trim((string)($req->post('customer_photo') ?? ''));

// Normalize payment method to the 4 canonical values (with backward-compat for
// the old cash/transfer values).
$paymentMethod = $paymentMethodRaw;
if ($paymentMethod === 'cash') {
	$paymentMethod = 'cash_ghs';
} elseif ($paymentMethod === 'transfer') {
	$paymentMethod = 'transfer_telcos';
}

if($customerId <= 0){
	message_fail('Customer is required.');
	redirect($admin_route.'/'.$plugin_route.'/customers');
}
if($amount <= 0){
	message_fail('Enter a valid withdrawal amount.');
	redirect($admin_route.'/'.$plugin_route.'/customer/'.$customerId);
}

// Payment method required: one of the four supported methods.
$allowedMethods = ['cash_ghs', 'cash_cfa', 'transfer_telcos', 'transfer_bank'];
if(!in_array($paymentMethod, $allowedMethods, true)){
	message_fail('Select a valid payment method (Cash GHS, Cash CFA, Transfer Telcos, or Transfer Bank).');
	redirect($admin_route.'/'.$plugin_route.'/customer/'.$customerId);
}

if($paymentMethod === 'transfer_telcos' && !in_array($payoutChannel, ['mtn','telecel','at'], true)){
	message_fail('Select a valid mobile money channel (MTN, Telecel, AT) for the transfer.');
	redirect($admin_route.'/'.$plugin_route.'/customer/'.$customerId);
}
if($paymentMethod === 'transfer_bank' && $accountPaidTo === ''){
	message_fail('Select a bank for the bank transfer.');
	redirect($admin_route.'/'.$plugin_route.'/customer/'.$customerId);
}

if($confirmedBy === ''){
	message_fail('Customer confirmation name/ID is required.');
	redirect($admin_route.'/'.$plugin_route.'/customer/'.$customerId);
}

if($pickupCharge < 0){
	$pickupCharge = 0;
}

$amountPaidOut = max(0.0, $amount - $pickupCharge);
if($amountPaidOut <= 0 && $amount > 0){
	$amountPaidOut = $amount;
	$pickupCharge = 0;
}

$csrf = csrf_verify($req->post());
if(!$csrf){
	message_fail('Form expired.');
	redirect($admin_route.'/'.$plugin_route.'/customer/'.$customerId);
}

$transactionStarted = false;
$insertedIds = [];
try{
	$db = new \Core\Database;
	\CashpickupLedger\ensure_cashpickup_customer_schema($db);
	\CashpickupLedger\ensure_cashpickup_columns($db);
	\CashpickupLedger\ensure_cashpickup_credit_schema($db);
	\CashpickupCustomerAssignment\ensure_schema($db);

	// Lookup customer
	$customerRows = $db->query("SELECT * FROM cashpickup_customers WHERE deleted = 0 AND id = :id LIMIT 1", ['id' => $customerId]) ?: [];
	if(empty($customerRows)){
		message_fail('Customer not found.');
		redirect($admin_route.'/'.$plugin_route.'/customer/'.$customerId);
	}
	$customer = $customerRows[0];

	if(!\CashpickupLedger\customer_is_approved($customer)){
		message_fail('Customer registration is pending approval.');
		redirect($admin_route.'/'.$plugin_route.'/customer/'.$customerId);
	}
	\CashpickupCustomerAssignment\migrate_legacy_customer_assignments($db, $customerId, $actorId);

	// Check cumulative balance
	$balance = \CashpickupLedger\fetch_customer_balance_metrics($db, $customerId);
	$customerBalance = max(0.0, (float)($balance['balance'] ?? 0));
	$isAssignmentPrivileged = user_can('is_manager') || user_can('is_admin');
	$assignedBalance = \CashpickupCustomerAssignment\get_remaining_amount($db, $customerId, $actorId);
	$withdrawalLimit = $isAssignmentPrivileged ? $customerBalance : min($customerBalance, $assignedBalance);
	if(($amount - $withdrawalLimit) > 0.00001){
		$limitMessage = $isAssignmentPrivileged
			? 'Withdrawal amount exceeds this customer balance (' . number_format($withdrawalLimit, 2) . ').'
			: 'Withdrawal amount exceeds your assigned payout balance (' . number_format($withdrawalLimit, 2) . ').';
		message_fail($limitMessage);
		redirect($admin_route.'/'.$plugin_route.'/customer/'.$customerId);
	}

	// FIFO allocation across unpaid receives
	$allocation = \CashpickupLedger\compute_cumulative_withdrawal_allocation($db, $customerId, $amount);
	if(empty($allocation['slices']) || ($allocation['shortfall'] ?? 0) > 0.00001){
		message_fail('Unable to allocate withdrawal to customer receives. Balance may have changed.');
		redirect($admin_route.'/'.$plugin_route.'/customer/'.$customerId);
	}

	// Approval workflow: same as add-controller.php
	$isPrivileged = user_can('is_admin');
	$isGeneralManager = false;
	try {
		$gm = $db->query(
			"SELECT 1
			 FROM user_roles_map urm
			 JOIN user_roles ur ON ur.id = urm.role_id
			 WHERE urm.disabled = 0
			 AND urm.user_id = :uid
			 AND LOWER(ur.role) IN ('general manager','general_manager','generalmanager','gm','is_general_manager')
			 LIMIT 1",
			['uid' => $actorId]
		);
		$isGeneralManager = !empty($gm);
	} catch (\Throwable $e) {}
	$approvalStatus = ($isPrivileged || $isGeneralManager) ? 'approved' : 'pending';

	// CFA amount (only set for cash_cfa; zero otherwise). Hoisted here so it is
	// always defined when the insert loop references it.
	$cfaAmount = 0.0;

	// ===== STRICT CASHDRAWER / BALANCE VALIDATION (HARD BLOCK) =====
	// Runs for ALL users regardless of approval status: a withdrawal can never
	// be created if the agent's cashdrawer denominations or e-cash/bank balance
	// are insufficient to pay the amount involved. No row is created on failure.
	$bridgePath = realpath(__DIR__ . '/../../backoffice/helpers/cashdrawer-bridge.php');
	$availabilityPath = realpath(__DIR__ . '/../../cfa/helpers/drawer-availability.php');
	$agencyHelper = realpath(__DIR__ . '/../../agencybanking/helpers/cashdrawer-sync.php');
	if ($agencyHelper && file_exists($agencyHelper)) {
		require_once $agencyHelper;
	}
	if ($bridgePath && file_exists($bridgePath)) {
		require_once $bridgePath;
		\BackOfficeCashdrawer\init();
	}
	if ($availabilityPath && file_exists($availabilityPath)) {
		require_once $availabilityPath;
	}
	$drawerCtx = null;
	if ($bridgePath && function_exists('BackOfficeCashdrawer\\getActiveDrawer')) {
		try { $drawerCtx = \BackOfficeCashdrawer\getActiveDrawer($actorId); } catch (\Throwable $e) { $drawerCtx = null; }
	}
	if (empty($drawerCtx)) {
		message_fail('No active cashdrawer found for this user. Please open a cash drawer first.');
		redirect($admin_route.'/'.$plugin_route.'/customer/'.$customerId);
	}
	$drawerId = (int)($drawerCtx['drawer_id'] ?? 0);
	$drawerUserId = (int)($drawerCtx['drawer_user_id'] ?? $actorId);

	if ($paymentMethod === 'cash_ghs') {
		// GHS note denominations must cover the cash paid out.
		$decodedGhs = json_decode($denosJson, true);
		if (!is_array($decodedGhs) || empty($decodedGhs)) {
			message_fail('Please enter the GHS cash denominations for the payout.');
			redirect($admin_route.'/'.$plugin_route.'/customer/'.$customerId);
		}
		if (function_exists('BackOfficeCashdrawer\\normalizeDenominations')) {
			$denos = \BackOfficeCashdrawer\normalizeDenominations($denosJson, $amountPaidOut, 'GHS');
		} else {
			$denos = [];
		}
		$ghsPayload = [];
		$ghsVolume = 0.0;
		foreach ($denos as $row) {
			if (strtoupper((string)($row['currency'] ?? 'GHS')) !== 'GHS') continue;
			$ghsPayload[] = [
				'denomination' => (float)($row['denomination'] ?? 0),
				'count' => (int)($row['count'] ?? 0),
				'volume' => (float)($row['volume'] ?? 0),
			];
			$ghsVolume += (float)($row['volume'] ?? 0);
		}
		if (abs($ghsVolume - $amountPaidOut) > 0.009) {
			message_fail('GHS denomination volume (GH₵ ' . number_format($ghsVolume, 2) . ') must equal the amount paid out (GH₵ ' . number_format($amountPaidOut, 2) . ').');
			redirect($admin_route.'/'.$plugin_route.'/customer/'.$customerId);
		}
		if (function_exists('Cfas\\cfa_fetch_ghs_denominations') && function_exists('Cfas\\cfa_validate_ghs_supply')) {
			$availableDenos = \Cfas\cfa_fetch_ghs_denominations($drawerId, $drawerUserId);
			$errs = \Cfas\cfa_validate_ghs_supply($ghsPayload, $availableDenos);
			if (!empty($errs)) {
				message_fail('Insufficient GHS cash. ' . implode(' ', $errs));
				redirect($admin_route.'/'.$plugin_route.'/customer/'.$customerId);
			}
		}
	} elseif ($paymentMethod === 'cash_cfa') {
		// CFA: customer is PAID in CFA notes. Convert GHS payable -> CFA using the
		// SELLING rate (shop sells CFA to the customer), then check CFA notes.
		$rates = \CashpickupLedger\cashpickup_settings_cfa_rates($db);
		$sellingRate = (float)($rates['selling_rate'] ?? 0);
		if ($sellingRate <= 0) {
			message_fail('No CFA selling rate is configured. Ask an admin to set the selling rate in CFA settings.');
			redirect($admin_route.'/'.$plugin_route.'/customer/'.$customerId);
		}
		// CFA = GHS * 1000 / selling_rate (selling_rate is per CFA 1,000).
		$cfaAmount = $amountPaidOut * 1000.0 / $sellingRate;
		// CFA notes are whole; round to nearest 5 (smallest practical multiple).
		$cfaAmount = round($cfaAmount / 5) * 5;
		if ($cfaAmountInput > 0) {
			$cfaAmount = $cfaAmountInput; // trust the calculator-driven value if provided
		}
		if ($cfaAmount <= 0) {
			message_fail('The CFA conversion produced an invalid amount. Check the selling rate.');
			redirect($admin_route.'/'.$plugin_route.'/customer/'.$customerId);
		}
		$decodedCfa = json_decode($cfaDenosJson, true);
		if (!is_array($decodedCfa) || empty($decodedCfa)) {
			message_fail('Please enter the CFA cash denominations for the payout.');
			redirect($admin_route.'/'.$plugin_route.'/customer/'.$customerId);
		}
		$cfaPayload = [];
		$cfaVolume = 0.0;
		foreach ($decodedCfa as $row) {
			$denom = (float)($row['denomination'] ?? 0);
			$count = (int)($row['count'] ?? 0);
			if ($denom <= 0 || $count <= 0) continue;
			$cfaPayload[] = ['denomination' => $denom, 'count' => $count, 'volume' => $denom * $count];
			$cfaVolume += $denom * $count;
		}
		if (abs($cfaVolume - $cfaAmount) > 5) {
			message_fail('CFA denomination volume (' . number_format($cfaVolume, 0) . ' CFA) must equal the CFA amount (' . number_format($cfaAmount, 0) . ' CFA).');
			redirect($admin_route.'/'.$plugin_route.'/customer/'.$customerId);
		}
		if (function_exists('Cfas\\cfa_fetch_fx_denominations') && function_exists('Cfas\\cfa_validate_fx_supply')) {
			$availableCfa = \Cfas\cfa_fetch_fx_denominations($drawerId, $drawerUserId, 'CFA');
			$errs = \Cfas\cfa_validate_fx_supply($cfaPayload, $availableCfa, 'CFA');
			if (!empty($errs)) {
				message_fail('Insufficient CFA cash. ' . implode(' ', $errs));
				redirect($admin_route.'/'.$plugin_route.'/customer/'.$customerId);
			}
		}
	} elseif ($paymentMethod === 'transfer_telcos') {
		// e-cash (momo) balance must cover the paid-out amount.
		$channel = strtoupper($payoutChannel);
		$available = 0.0;
		$dbBal = new \Core\Database;
		if ($dbBal->table_exists(['drawer_balances'])) {
			$balRow = $dbBal->query(
				"SELECT closing_balance FROM drawer_balances
				 WHERE drawer_id = :drawer_id AND user_id = :user_id
				 AND channel_type = 'ecash'
				 AND (channel_key = :channel_key OR channel_key = :ecash_channel_key)
				 ORDER BY id DESC LIMIT 1",
				['drawer_id' => $drawerId, 'user_id' => $drawerUserId, 'channel_key' => $channel, 'ecash_channel_key' => 'ECASH_' . $channel]
			);
			$available = !empty($balRow) ? (float)($balRow[0]->closing_balance ?? 0) : 0.0;
		}
		if ($available <= 0.0 && function_exists('Agencybankings\\agency_get_shared_ecash_balance')) {
			$sharedAvail = (float)\Agencybankings\agency_get_shared_ecash_balance($channel, $channel);
			if ($sharedAvail > 0) $available = $sharedAvail;
		}
		if (($amountPaidOut - $available) > 0.009) {
			message_fail('Insufficient ' . $channel . ' balance. Available: GH₵ ' . number_format($available, 2) . ' | Required: GH₵ ' . number_format($amountPaidOut, 2));
			redirect($admin_route.'/'.$plugin_route.'/customer/'.$customerId);
		}
	} elseif ($paymentMethod === 'transfer_bank') {
		// Bank e-cash balance must cover the paid-out amount.
		$bankName = $accountPaidTo;
		if (function_exists('Agencybankings\\agency_canonicalize_drawer_bank_name')) {
			$bankName = \Agencybankings\agency_canonicalize_drawer_bank_name($bankName);
		}
		$network = strtoupper(preg_replace('/[^A-Z0-9]+/i', '_', $bankName));
		$network = trim($network, '_');
		$channelKey = $network !== '' ? ('ECASH_' . $network) : '';
		$available = 0.0;
		$dbBal = new \Core\Database;
		if ($dbBal->table_exists(['drawer_balances']) && $channelKey !== '') {
			$balRow = $dbBal->query(
				"SELECT closing_balance FROM drawer_balances
				 WHERE drawer_id = :drawer_id AND user_id = :user_id
				 AND channel_type = 'ecash'
				 AND (channel_key = :ck1 OR channel_key = :ck2)
				 ORDER BY id DESC LIMIT 1",
				['drawer_id' => $drawerId, 'user_id' => $drawerUserId, 'ck1' => $channelKey, 'ck2' => $network]
			);
			$available = !empty($balRow) ? (float)($balRow[0]->closing_balance ?? 0) : 0.0;
		}
		if ($available <= 0.0 && $channelKey !== '' && function_exists('Agencybankings\\agency_get_shared_ecash_balance')) {
			$sharedAvail = (float)\Agencybankings\agency_get_shared_ecash_balance($channelKey, $bankName);
			if ($sharedAvail > 0) $available = $sharedAvail;
		}
		if (($amountPaidOut - $available) > 0.009) {
			message_fail('Insufficient ' . $bankName . ' balance. Available: GH₵ ' . number_format($available, 2) . ' | Required: GH₵ ' . number_format($amountPaidOut, 2));
			redirect($admin_route.'/'.$plugin_route.'/customer/'.$customerId);
		}
	}

	if (!$db->beginTransaction()) {
		throw new \RuntimeException('Unable to secure the customer balance for withdrawal.');
	}
	$transactionStarted = true;
	$lockedCustomer = $db->query(
		"SELECT id FROM cashpickup_customers WHERE id = :id AND deleted = 0 FOR UPDATE",
		['id' => $customerId]
	) ?: [];
	if (empty($lockedCustomer)) {
		throw new \RuntimeException('Customer not found.');
	}
	$latestBalance = \CashpickupLedger\fetch_customer_balance_metrics($db, $customerId);
	$latestCustomerBalance = max(0.0, (float)($latestBalance['balance'] ?? 0));
	$latestAssignedBalance = \CashpickupCustomerAssignment\get_remaining_amount($db, $customerId, $actorId);
	$latestWithdrawalLimit = $isAssignmentPrivileged ? $latestCustomerBalance : min($latestCustomerBalance, $latestAssignedBalance);
	if ($amount > $latestWithdrawalLimit + 0.00001) {
		throw new \RuntimeException('The available customer payout balance changed. Available: GH₵ ' . number_format($latestWithdrawalLimit, 2) . '.');
	}
	$allocation = \CashpickupLedger\compute_cumulative_withdrawal_allocation($db, $customerId, $amount);
	if (empty($allocation['slices']) || ($allocation['shortfall'] ?? 0) > 0.00001) {
		throw new \RuntimeException('Unable to allocate withdrawal to customer receives. Balance may have changed.');
	}

	$payoutSlices = [];
	if ($isAssignmentPrivileged) {
		foreach ($allocation['slices'] as $slice) {
			$payoutSlices[] = [
				'receive_id' => (int)$slice['receive_id'],
				'amount' => round((float)$slice['amount'], 2),
				'customer_assignment_id' => null,
			];
		}
	} else {
		$assignmentPlan = \CashpickupCustomerAssignment\plan_allocation($db, $customerId, $actorId, $amount);
		if (($assignmentPlan['shortfall'] ?? 0) > 0.00001) {
			throw new \RuntimeException('Your assigned payout balance changed. Refresh and try again.');
		}
		$assignmentIndex = 0;
		$assignmentRemaining = (float)($assignmentPlan['slices'][0]['amount'] ?? 0);
		foreach ($allocation['slices'] as $receiveSlice) {
			$receiveRemaining = round((float)$receiveSlice['amount'], 2);
			while ($receiveRemaining > 0.00001) {
				$currentAssignment = $assignmentPlan['slices'][$assignmentIndex] ?? null;
				if (!$currentAssignment) {
					throw new \RuntimeException('Unable to link withdrawal to its payout assignment.');
				}
				if ($assignmentRemaining <= 0.00001) {
					$assignmentIndex++;
					$assignmentRemaining = (float)($assignmentPlan['slices'][$assignmentIndex]['amount'] ?? 0);
					continue;
				}
				$take = round(min($receiveRemaining, $assignmentRemaining), 2);
				if ($take <= 0.00001) {
					throw new \RuntimeException('Unable to prepare the assigned payout.');
				}
				$payoutSlices[] = [
					'receive_id' => (int)$receiveSlice['receive_id'],
					'amount' => $take,
					'customer_assignment_id' => (int)$currentAssignment['assignment_id'],
				];
				$receiveRemaining = round(max(0.0, $receiveRemaining - $take), 2);
				$assignmentRemaining = round(max(0.0, $assignmentRemaining - $take), 2);
			}
		}
	}
	if ($pickupCharge > 0 && !empty($payoutSlices)) {
		usort($payoutSlices, static function(array $left, array $right): int {
			return (float)$right['amount'] <=> (float)$left['amount'];
		});
		if ($pickupCharge >= (float)$payoutSlices[0]['amount'] - 0.00001) {
			throw new \RuntimeException('Pickup charge must be less than the largest payout allocation. Reduce the charge and try again.');
		}
	}

	// --- Insert pay rows per FIFO slice and assignment slice ---
	$now = date('Y-m-d H:i:s');
	foreach ($payoutSlices as $sliceIndex => $slice) {
		$sliceAmount = (float)$slice['amount'];
		$receiveId = (int)$slice['receive_id'];

		// Pickup charge goes on the first slice only; remaining slices have no charge.
		$isFirstSlice = $sliceIndex === 0;
		$sliceCharge = $isFirstSlice ? $pickupCharge : 0.0;
		$slicePaidOut = max(0.0, $sliceAmount - $sliceCharge);

		$rowId = (int)floor(microtime(true) * 1000000);
		$insertData = [
			'id' => $rowId,
			'transaction_side' => 'pay',
			'date' => date('Y-m-d'),
			'source' => 'customer_withdrawal',
			'account_paid_to' => $paymentMethod === 'transfer_bank' ? $accountPaidTo : '',
			'sender_details' => (string)($customer->full_name ?? ''),
			'phone_number' => (string)($customer->phone_number ?? ''),
			'tracking_id' => 'WD-' . $rowId,
			'idcard_photo' => $idcardPhoto,
			'customer_photo' => $customerPhoto,
			'pickup_person_idcard' => $confirmedBy,
			'pickup_person_signature' => '',
			'pickup_person_phone' => (string)($customer->phone_number ?? ''),
			'amount_involved' => $sliceAmount,
			'payment_method' => $paymentMethod,
			'pickup_charge' => $sliceCharge,
			'amount_paid_out' => $slicePaidOut,
			'amount_transferred' => $sliceAmount,
			'receive_id' => $receiveId,
			'pay_type' => 'part',
			'amount_requested' => $sliceAmount,
			'customer_assignment_id' => $slice['customer_assignment_id'],
			'denos_json' => ($isFirstSlice && $paymentMethod === 'cash_ghs') ? $denosJson : '',
			'cfa_amount' => ($isFirstSlice && $paymentMethod === 'cash_cfa') ? $cfaAmount : 0,
			'cfa_denos_json' => ($isFirstSlice && $paymentMethod === 'cash_cfa') ? $cfaDenosJson : '',
			'customer_id' => $customerId,
			'customer_id_type' => (string)($customer->id_type ?? ''),
			'customer_id_number' => (string)($customer->id_number ?? ''),
			'customer_location' => (string)($customer->location ?? ''),
			'payout_channel' => $paymentMethod === 'transfer_telcos' ? $payoutChannel : ($paymentMethod === 'transfer_bank' ? $accountPaidTo : ''),
			'assigned_user_id' => $actorId,
			'approved_by' => $actorId,
			'approved_at' => $now,
			'confirmed_by' => $confirmedBy,
			'notes' => $notes,
			'approval_status' => $approvalStatus,
			'transaction_status' => 'Approved',
			'created_by' => $actorId,
			'updated_by' => null,
			'disabled' => 0,
			'deleted' => 0,
			'date_created' => $now,
			'date_updated' => null,
			'date_deleted' => null,
		];

		$cols = array_keys($insertData);
		$sql = "INSERT INTO cashpickups (".implode(',', $cols).") VALUES (:".implode(',:', $cols).")";

		// Duplicate PK retry (mirrors add-controller pattern)
		$insertOk = false;
		for ($attempt = 0; $attempt < 3; $attempt++) {
			try {
				$insertData['id'] = (int)floor(microtime(true) * 1000000);
				$insertData['tracking_id'] = 'WD-' . $insertData['id'];
				$db->query($sql, $insertData);
				if ($db->has_error) {
					throw new \RuntimeException('Unable to save a payout allocation: ' . $db->error);
				}
				$insertedIds[] = $insertData['id'];
				$insertOk = true;
				break;
			} catch (\Throwable $insertErr) {
				if ($attempt >= 2) {
					throw $insertErr;
				}
				usleep(80000);
			}
		}
	}
	if (!$db->commit()) {
		throw new \RuntimeException('Unable to complete the customer withdrawal.');
	}
	$transactionStarted = false;

	// Commission row if there is a charge (first slice only)
	if ($pickupCharge > 0 && !empty($insertedIds)) {
		try {
			$commissionDb = new \Core\Database;
			$commissionDescription = 'CASHPICKUP CHARGE | PAY | customer_withdrawal | ID ' . $insertedIds[0];
			$commissionInsert = [
				'date_received' => $now,
				'colletion_period' => 'INSTANT',
				'network' => $payoutChannel !== '' ? $payoutChannel : ($paymentMethod !== '' ? $paymentMethod : null),
				'commission_type' => 'CASHPICKUP CHARGE',
				'description' => $commissionDescription,
				'pay_to' => $actorId,
				'receiving_wallet' => null,
				'amount' => $pickupCharge,
				'transaction_status' => $approvalStatus === 'approved' ? 'Approved' : $approvalStatus,
				'created_by' => $actorId,
				'updated_by' => null,
				'disabled' => 0,
				'deleted' => 0,
				'date_created' => $now,
				'date_updated' => null,
				'date_deleted' => null,
			];
			$commissionSql = "INSERT INTO commissions (".implode(',', array_keys($commissionInsert)).") VALUES (:".implode(',:', array_keys($commissionInsert)).")";
			$commissionDb->query($commissionSql, $commissionInsert);
		} catch (\Throwable $e) {
			error_log('CASHPICKUP CHARGE COMMISSION INSERT FAILED (WITHDRAW): ' . $e->getMessage());
		}
	}

	// ===== POST-INSERT CASHDRAWER SYNC (only when approved) =====
	if ($approvalStatus === 'approved' && !empty($insertedIds) && $drawerId > 0) {
		$firstInsertedId = (int)$insertedIds[0];

		// Cash GHS out: decrement GHS denominations
		if ($paymentMethod === 'cash_ghs' && function_exists('BackOfficeCashdrawer\\syncCashMovement')) {
			try {
				$denos = \BackOfficeCashdrawer\normalizeDenominations($denosJson, $amountPaidOut, 'GHS');
				\BackOfficeCashdrawer\syncCashMovement($actorId, $denos, $amountPaidOut, 'Approved', $firstInsertedId, 'out');
			} catch (\Throwable $e) {
				error_log('CASHDRAWER SYNC FAILED (WITHDRAW GHS OUT): ' . $e->getMessage());
			}
		}

		// Cash CFA out: decrement CFA FX denominations
		if ($paymentMethod === 'cash_cfa' && function_exists('Cfas\\cfa_process_fx_denominations')) {
			try {
				$cfaDecoded = json_decode($cfaDenosJson, true);
				$cfaDenos = is_array($cfaDecoded) ? $cfaDecoded : [];
				\Cfas\cfa_process_fx_denominations($drawerId, $drawerUserId, $cfaDenos, -1, 'Approved', $firstInsertedId, $now, $cfaAmount);
			} catch (\Throwable $e) {
				error_log('CASHDRAWER SYNC FAILED (WITHDRAW CFA OUT): ' . $e->getMessage());
			}
		}

		// e-cash / bank out: decrement channel balance
		if (($paymentMethod === 'transfer_telcos' || $paymentMethod === 'transfer_bank')) {
			$channelName = $paymentMethod === 'transfer_telcos' ? $payoutChannel : $accountPaidTo;
			if ($paymentMethod === 'transfer_bank' && function_exists('Agencybankings\\agency_canonicalize_drawer_bank_name')) {
				$channelName = \Agencybankings\agency_canonicalize_drawer_bank_name($channelName);
			}
			$net = strtoupper(preg_replace('/[^A-Z0-9]+/i', '_', $channelName));
			$net = trim($net, '_');
			$channelKey = $paymentMethod === 'transfer_telcos' ? strtoupper($channelName) : ($net !== '' ? ('ECASH_' . $net) : '');
			try {
				$dbSync = new \Core\Database;
				if ($dbSync->table_exists(['drawer_balances']) && $channelKey !== '') {
					$balRow = $dbSync->query(
						"SELECT id, closing_balance FROM drawer_balances
						 WHERE drawer_id = :drawer_id AND user_id = :user_id
						 AND channel_type = 'ecash'
						 AND (channel_key = :ck1 OR channel_key = :ck2)
						 ORDER BY id DESC LIMIT 1",
						['drawer_id' => $drawerId, 'user_id' => $drawerUserId, 'ck1' => $channelKey, 'ck2' => $paymentMethod === 'transfer_telcos' ? ('ECASH_' . $channelKey) : $net]
					);
					if (!empty($balRow)) {
						$newBal = max(0.0, (float)($balRow[0]->closing_balance ?? 0) - $amountPaidOut);
						$dbSync->query(
							"UPDATE drawer_balances SET closing_balance = :new_bal, date_updated = :now WHERE id = :id",
							['new_bal' => $newBal, 'now' => $now, 'id' => (int)$balRow[0]->id]
						);
					}
				}
				if (function_exists('Agencybankings\\agency_refresh_ecash_channel') && $channelKey !== '') {
					try { \Agencybankings\agency_refresh_ecash_channel($drawerId, $drawerUserId, $channelKey, $channelName); } catch (\Throwable $e) {}
				}
			} catch (\Throwable $e) {
				error_log('CASHDRAWER SYNC FAILED (WITHDRAW ECASH/BANK OUT): ' . $e->getMessage());
			}
		}
	}

	$sliceCount = count($allocation['slices']);
	message_success('Cumulative withdrawal of GH₵ ' . number_format($amount, 2) . ' recorded across ' . $sliceCount . ' receive record' . ($sliceCount > 1 ? 's' : '') . '.');
	redirect($admin_route.'/'.$plugin_route.'/customer/'.$customerId);

}catch(\Throwable $e){
	if ($transactionStarted && isset($db) && $db instanceof \Core\Database) {
		$db->rollBack();
		$transactionStarted = false;
	}
	// Roll back any inserted rows on failure.
	if (!empty($insertedIds)) {
		try {
			$rollbackDb = new \Core\Database;
			$placeholders = [];
			$rollbackParams = [];
			foreach ($insertedIds as $idx => $rid) {
				$key = 'rbid' . $idx;
				$placeholders[] = ':' . $key;
				$rollbackParams[$key] = $rid;
			}
			$rollbackDb->query(
				"UPDATE cashpickups SET deleted = 1, date_deleted = :now WHERE id IN (" . implode(',', $placeholders) . ")",
				array_merge($rollbackParams, ['now' => date('Y-m-d H:i:s')])
			);
		} catch (\Throwable $e2) {}
	}
	message_fail('Unable to process withdrawal. ' . $e->getMessage());
	redirect($admin_route.'/'.$plugin_route.'/customer/'.$customerId);
}

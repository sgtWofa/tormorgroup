<?php

require_once plugin_path('helpers/customer-ledger.php');
$transferHelperPath = realpath(__DIR__ . '/../helpers/transfer-allocation.php');
if ($transferHelperPath && file_exists($transferHelperPath)) {
	require_once $transferHelperPath;
}
$agencyHelperPath = realpath(__DIR__ . '/../../agencybanking/helpers/cashdrawer-sync.php');
if ($agencyHelperPath && file_exists($agencyHelperPath)) {
	require_once $agencyHelperPath;
}

// Set timezone to Accra (Africa/Accra)
date_default_timezone_set('Africa/Accra');

$cashpickup = new \Cashpickups\Cashpickup;

$postdata = $req->post();

$postdata['idcard_photo'] = $postdata['idcard_photo'] ?? '';
$postdata['customer_photo'] = $postdata['customer_photo'] ?? '';
$postdata['receive_proof_photo'] = $postdata['receive_proof_photo'] ?? '';

try {
	$dbSchema = new \Core\Database;
	\CashpickupLedger\ensure_cashpickup_customer_schema($dbSchema);
	\CashpickupLedger\ensure_cashpickup_columns($dbSchema);
	\CashpickupLedger\ensure_cashpickup_credit_schema($dbSchema);
} catch (\Throwable $e) {
	$cashpickup->errors['idcard_photo'] = 'Unable to validate cash pickup photo storage.';
	set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
	return;
}

try {
	$ledgerDb = new \Core\Database;
	\CashpickupLedger\ensure_cashpickup_customer_schema($ledgerDb);
	\CashpickupLedger\ensure_cashpickup_columns($ledgerDb);
	\CashpickupLedger\ensure_cashpickup_credit_schema($ledgerDb);
	$sessionLedger = get_session();
	$actorId = (int)($sessionLedger->id ?? 0);
} catch (\Throwable $e) {
	// ledger is optional; ignore failures
}

$req->upload_folder = 'uploads/cashpickup';

// Validate image size (max 1MB) before upload
$maxFileSize = 1 * 1024 * 1024;
$imageFields = ['idcard_photo', 'customer_photo', 'receive_proof_photo'];
foreach ($imageFields as $field) {
	if (!empty($_FILES[$field]['tmp_name'])) {
		$fileSize = (int)($_FILES[$field]['size'] ?? 0);
		if ($fileSize > $maxFileSize) {
			$maxFileSizeMB = round($maxFileSize / (1024 * 1024), 0);
			$cashpickup->errors[$field] = "Image size exceeds {$maxFileSizeMB}MB limit. Please upload a smaller image.";
			set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
			return;
		}
	}
}

$uploadedIdCard = $req->upload_files('idcard_photo');
if (!empty($uploadedIdCard)) {
	$postdata['idcard_photo'] = $uploadedIdCard;
}
$uploadedCustomer = $req->upload_files('customer_photo');
if (!empty($uploadedCustomer)) {
	$postdata['customer_photo'] = $uploadedCustomer;
}
$uploadedProof = $req->upload_files('receive_proof_photo');
if (!empty($uploadedProof)) {
	$postdata['receive_proof_photo'] = $uploadedProof;
}

$postdata['denos_json'] = $postdata['denos_json'] ?? ($postdata['denos'] ?? '');

// normalize numeric fields
$postdata['amount_involved'] = (float)($postdata['amount_involved'] ?? 0);
$postdata['pickup_charge'] = (float)($postdata['pickup_charge'] ?? 0);
$postdata['amount_paid_out'] = (float)($postdata['amount_paid_out'] ?? 0);
$postdata['amount_transferred'] = (float)($postdata['amount_transferred'] ?? 0);
$postdata['amount_requested'] = (float)($postdata['amount_requested'] ?? 0);

// normalize customer bio fields
$postdata['customer_id_type'] = trim((string)($postdata['customer_id_type'] ?? ''));
$postdata['customer_id_number'] = trim((string)($postdata['customer_id_number'] ?? ''));
$postdata['customer_location'] = trim((string)($postdata['customer_location'] ?? ''));

// normalize optional integer fields (avoid strict mode errors for empty strings)
if (array_key_exists('assigned_user_id', $postdata)) {
	$v = trim((string)($postdata['assigned_user_id'] ?? ''));
	$postdata['assigned_user_id'] = ($v === '') ? null : (int)$v;
}

$side = strtolower(trim((string)($postdata['transaction_side'] ?? '')));
$customerType = strtolower(trim((string)($postdata['customer_type'] ?? 'existing')));
if (!in_array($customerType, ['existing', 'new'], true)) {
	$customerType = 'existing';
}

// status is system-controlled, set later based on approval_status
if ($side === 'receive') {
	// tracking id is system-generated for receive
	if (trim((string)($postdata['tracking_id'] ?? '')) === '') {
		try {
			$dbTrack = new \Core\Database;
			$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
			$makeId = function() use ($alphabet): string {
				$out = '';
				$max = strlen($alphabet) - 1;
				for ($i = 0; $i < 10; $i++) {
					$out .= $alphabet[rand(0, $max)];
				}
				return $out;
			};
			$tries = 0;
			do {
				$candidate = $makeId();
				$exists = $dbTrack->query(
					"SELECT id FROM cashpickups WHERE tracking_id = :tid LIMIT 1",
					['tid' => $candidate]
				);
				$tries++;
			} while (!empty($exists) && $tries < 25);
			$postdata['tracking_id'] = $candidate;
		} catch (\Throwable $e) {
			$postdata['tracking_id'] = strtoupper(substr(md5((string)microtime(true) . (string)rand(0, 999999)), 0, 10));
		}
	}

	$src = strtolower(trim((string)($postdata['source'] ?? '')));
	$isManagerOrAdmin = (user_can('is_manager') || user_can('is_admin'));
	if ($customerType !== 'new') {
		if($isManagerOrAdmin){
			$allowed = ['cash','mtn','at','telecel','bank'];
			if($src === '' || !in_array($src, $allowed, true)){
				$cashpickup->errors['source'] = 'Please select source';
				set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
				return;
			}
			$postdata['source'] = $src;
			if($src === 'bank'){
				$bank = trim((string)($postdata['account_paid_to'] ?? ''));
				if($bank === ''){
					$cashpickup->errors['account_paid_to'] = 'Please select source bank';
					set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
					return;
				}
				$postdata['account_paid_to'] = $bank;
			}else{
				$postdata['account_paid_to'] = '';
			}

			// manager/admin: cash uses denominations, other sources allow manual amount
			if($src === 'cash'){
				$denosRaw = (string)($postdata['denos_json'] ?? '');
				$decoded = json_decode($denosRaw, true);
				if (!is_array($decoded) || empty($decoded)) {
					$cashpickup->errors['denos_json'] = 'Please select denominations';
					set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
					return;
				}
				$sum = 0.0;
				foreach ($decoded as $d) {
					$sum += (float)($d['volume'] ?? 0);
				}
				$postdata['amount_involved'] = (float)number_format($sum, 2, '.', '');
			}else{
				$amt = (float)($postdata['amount_involved'] ?? 0);
				if($amt <= 0){
					$cashpickup->errors['amount_involved'] = 'Amount involved is required';
					set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
					return;
				}
				$postdata['amount_involved'] = (float)number_format($amt, 2, '.', '');
			}
			if(trim((string)($postdata['receive_proof_photo'] ?? '')) === ''){
				$cashpickup->errors['receive_proof_photo'] = 'Cash received proof photo is required';
				set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
				return;
			}
		}else{
			if($src === ''){
				$postdata['source'] = 'Cash';
			}
			$postdata['account_paid_to'] = $postdata['account_paid_to'] ?? '';

			$denosRaw = (string)($postdata['denos_json'] ?? '');
			$decoded = json_decode($denosRaw, true);
			if (!is_array($decoded) || empty($decoded)) {
				$cashpickup->errors['denos_json'] = 'Please select denominations';
				set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
				return;
			}
			$sum = 0.0;
			foreach ($decoded as $d) {
				$sum += (float)($d['volume'] ?? 0);
			}
			$postdata['amount_involved'] = (float)number_format($sum, 2, '.', '');
		}
	}
}

if ($side === 'pay') {
	$receiveId = (int)($postdata['receive_id'] ?? 0);
	if ($receiveId <= 0) {
		$cashpickup->errors['transaction_side'] = 'Pay to customer can only be done from a received pickup payout.';
		set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
		return;
	}

	$payMethodRaw = strtolower(trim((string)($postdata['payment_method'] ?? '')));
	// Normalize to the 4 canonical payout methods (backward-compat for cash/transfer).
	$payMethod = $payMethodRaw;
	if ($payMethod === 'cash') {
		$payMethod = 'cash_ghs';
	} elseif ($payMethod === 'transfer') {
		$payMethod = 'transfer_telcos';
	}
	$postdata['payment_method'] = $payMethod;

	$allowedPayMethods = ['cash_ghs', 'cash_cfa', 'transfer_telcos', 'transfer_bank'];
	if ($side === 'pay' && !in_array($payMethod, $allowedPayMethods, true)) {
		$cashpickup->errors['payment_method'] = 'Please select a valid payment method (Cash GHS, Cash CFA, Transfer Telcos, Transfer Bank)';
		set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
		return;
	}

	if ($payMethod === 'transfer_telcos') {
		$postdata['customer_photo'] = '';
		$chan = strtolower(trim((string)($postdata['payout_channel'] ?? '')));
		if ($chan === '' || !in_array($chan, ['mtn','telecel','at'], true)) {
			$cashpickup->errors['payout_channel'] = 'Please select transfer as (MTN/Telecel/AT)';
			set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
			return;
		}
		$postdata['payout_channel'] = $chan;
	} elseif ($payMethod === 'transfer_bank') {
		$postdata['customer_photo'] = '';
		$bank = trim((string)($postdata['account_paid_to'] ?? ''));
		if ($bank === '') {
			$cashpickup->errors['account_paid_to'] = 'Please select a bank for the bank transfer';
			set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
			return;
		}
		$postdata['payout_channel'] = $bank;
	} else {
		$postdata['payout_channel'] = '';
	}
	if ($payMethod === 'cash_ghs') {
		$denosRaw = (string)($postdata['denos_json'] ?? '');
		$decoded = json_decode($denosRaw, true);
		if (!is_array($decoded) || empty($decoded)) {
			$cashpickup->errors['denos_json'] = 'Please select GHS denominations for the cash payout';
			set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
			return;
		}
	}
	if ($payMethod === 'cash_cfa') {
		$cfaDenosRaw = (string)($postdata['cfa_denos_json'] ?? '');
		$decodedCfa = json_decode($cfaDenosRaw, true);
		if (!is_array($decodedCfa) || empty($decodedCfa)) {
			$cashpickup->errors['cfa_denos_json'] = 'Please select CFA denominations for the cash CFA payout';
			set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
			return;
		}
		// Compute the CFA amount from the GHS payable + selling rate if not supplied.
		$payableGhs = max(0.0, (float)($postdata['amount_paid_out'] ?? 0));
		$rates = \CashpickupLedger\cashpickup_settings_cfa_rates($db);
		$sellingRate = (float)($rates['selling_rate'] ?? 0);
		if ($sellingRate <= 0) {
			$cashpickup->errors['cfa_denos_json'] = 'No CFA selling rate is configured. Ask an admin to set the selling rate.';
			set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
			return;
		}
		$cfaCalc = round(($payableGhs * 1000.0 / $sellingRate) / 5) * 5;
		$postdata['cfa_amount'] = (float)($postdata['cfa_amount'] ?? 0) > 0 ? (float)$postdata['cfa_amount'] : $cfaCalc;
		$postdata['customer_photo'] = '';
	}

	$toFloat = function($v): float {
		if (is_string($v)) {
			$v = trim($v);
			$v = str_replace([',', ' '], '', $v);
			$v = preg_replace('/[^0-9.\-]/', '', $v);
		}
		return (float)$v;
	};

	// Auto-populate amount + customer details from receive record
	try {
		$dbLookup = new \Core\Database;
		$receive = $dbLookup->query(
			"SELECT id, phone_number, tracking_id, sender_details, amount_involved, source,
			        customer_id, customer_id_type, customer_id_number, customer_location,
			        pickup_charge, credit_mode, assigned_user_id, approval_status, transaction_status
			 FROM cashpickups
			 WHERE (deleted IS NULL OR deleted = 0)
			 AND LOWER(transaction_side) = 'receive'
			 AND (
			 	(approval_status IS NULL OR approval_status = '' OR LOWER(approval_status) = 'approved')
			 	OR (
                	LOWER(TRIM(COALESCE(credit_mode,''))) = 'credit'
			 		AND assigned_user_id = :actor_id
			 		AND (
			 			approval_status IS NULL
			 			OR approval_status = ''
			 			OR LOWER(approval_status) IN ('pending','approved')
			 		)
			 		AND LOWER(COALESCE(transaction_status,'')) IN ('pending payout','approved')
			 	)
			 )
			 AND id = :id
			 ORDER BY date_created DESC, id DESC LIMIT 1",
			['id' => $receiveId, 'actor_id' => (int)$actorId]
		);
		if (empty($receive)) {
			$cashpickup->errors['receive_id'] = 'No matching received pickup found for this payout.';
			set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
			return;
		}
		$receiveRow = $receive[0];
		$postdata['sender_details'] = (string)($receiveRow->sender_details ?? '');
		$postdata['phone_number'] = (string)($receiveRow->phone_number ?? '');
		$postdata['tracking_id'] = (string)($receiveRow->tracking_id ?? '');
		$postdata['source'] = (string)($receiveRow->source ?? '');
		$postdata['customer_id'] = (int)($receiveRow->customer_id ?? 0);
		$postdata['customer_id_type'] = (string)($receiveRow->customer_id_type ?? $postdata['customer_id_type'] ?? '');
		$postdata['customer_id_number'] = (string)($receiveRow->customer_id_number ?? $postdata['customer_id_number'] ?? '');
		$postdata['customer_location'] = (string)($receiveRow->customer_location ?? $postdata['customer_location'] ?? '');
		$postdata['credit_mode'] = (string)($receiveRow->credit_mode ?? '');
		if (trim((string)($postdata['pickup_charge'] ?? '')) === '' && strtolower(trim((string)($receiveRow->credit_mode ?? ''))) === 'credit') {
			$postdata['pickup_charge'] = (float)($receiveRow->pickup_charge ?? 0);
		}
		$receiveTotal = $toFloat($receiveRow->amount_involved ?? 0);

		$payoutSum = $dbLookup->query(
			"SELECT COALESCE(SUM(COALESCE(amount_paid_out,0) + COALESCE(pickup_charge,0)),0) AS paid
			 FROM cashpickups
			 WHERE (deleted IS NULL OR deleted = 0)
			 AND LOWER(transaction_side) = 'pay'
			 AND (
				approval_status IS NULL
				OR approval_status = ''
				OR LOWER(approval_status) IN ('approved','pending')
			 )
			 AND transaction_status = 'Approved'
			 AND (confirmed_by IS NOT NULL AND confirmed_by != '')
			 AND COALESCE(amount_paid_out,0) > 0
			 AND receive_id = :rid",
			['rid' => $receiveId]
		);
		$alreadyPaid = $toFloat($payoutSum[0]->paid ?? 0);
		$receiveTotal = (float)number_format($receiveTotal, 2, '.', '');
		$alreadyPaid = (float)number_format($alreadyPaid, 2, '.', '');
		$remaining = max(0, (float)number_format(($receiveTotal - $alreadyPaid), 2, '.', ''));
		$assignedGross = 0.0;
		$assignedCharge = 0.0;
		$assignedPayable = 0.0;
		$assignedRemaining = 0.0;
		$hasAssignedAllocation = false;
		try {
			$allocDb = new \Core\Database;
			if (function_exists('CashpickupTransferAllocation\\get_transfer_allocation')) {
				$allocation = \CashpickupTransferAllocation\get_transfer_allocation($allocDb, $receiveId, (int)$actorId);
				$assignedGross = (float)($allocation['gross'] ?? 0);
				$assignedCharge = (float)($allocation['charge'] ?? 0);
				$assignedPayable = (float)($allocation['payable'] ?? 0);
				if ($assignedGross > 0.00001) {
					$hasAssignedAllocation = true;
					$assignedRemaining = $assignedGross;
				}
			}
		} catch (\Throwable $e) {
			$assignedGross = 0.0;
			$assignedCharge = 0.0;
			$assignedPayable = 0.0;
			$assignedRemaining = 0.0;
			$hasAssignedAllocation = false;
		}
		if ($hasAssignedAllocation && $assignedRemaining <= 0.00001) {
			$cashpickup->errors['amount_requested'] = 'This assigned payout has already been fully used.';
			set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
			return;
		}

		$payType = strtolower(trim((string)($postdata['pay_type'] ?? '')));
		if ($hasAssignedAllocation) {
			$payType = 'all';
			$postdata['pay_type'] = 'all';
		}
		if ($payType === 'all') {
			$postdata['amount_requested'] = $hasAssignedAllocation ? min($remaining, $assignedRemaining) : $remaining;
		}
		$postdata['amount_requested'] = (float)number_format($toFloat($postdata['amount_requested'] ?? 0), 2, '.', '');
		if ($payType === 'part' && $postdata['amount_requested'] <= 0) {
			$cashpickup->errors['amount_requested'] = 'Amount requested is required for part payout.';
			set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
			return;
		}
		if ($hasAssignedAllocation && $postdata['amount_requested'] > $assignedRemaining + 0.00001) {
			$cashpickup->errors['amount_requested'] = 'Assigned payout amount cannot exceed the assigned balance. Assigned: '.number_format($assignedRemaining, 2).' Requested: '.number_format($postdata['amount_requested'], 2).'.';
			set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
			return;
		}
		if (($postdata['amount_requested'] - $remaining) > 0.00001) {
			$payoutRows = $dbLookup->query(
				"SELECT id, amount_requested, transaction_status, approval_status, date_created
				 FROM cashpickups
				 WHERE (deleted IS NULL OR deleted = 0)
				 AND LOWER(transaction_side) = 'pay'
				 AND receive_id = :rid
				 ORDER BY id DESC",
				['rid' => $receiveId]
			) ?? [];
			$payoutSummary = '';
			if(!empty($payoutRows)){
				$items = [];
				foreach($payoutRows as $pr){
					$items[] = '#'.($pr->id ?? '').' ('.number_format($toFloat($pr->amount_requested ?? 0),2).') ['.($pr->transaction_status ?? '').'] ('.(trim((string)($pr->approval_status ?? '')) === '' ? 'approved' : (string)($pr->approval_status ?? '')).') '.($pr->date_created ?? '');
				}
				$payoutSummary = ' Linked payouts: '.implode(' | ', $items).'.';
			}
			error_log('cashpickup payout validation failed: receive_id=' . $receiveId . ' receiveTotal=' . $receiveTotal . ' alreadyPaid=' . $alreadyPaid . ' remaining=' . $remaining . ' requested=' . $postdata['amount_requested']);
			$cashpickup->errors['amount_requested'] = 'Amount requested exceeds the remaining balance. Remaining: '.number_format($remaining,2).', Requested: '.number_format((float)$postdata['amount_requested'],2).', Receive total: '.number_format($receiveTotal,2).', Already paid: '.number_format($alreadyPaid,2).'.'.$payoutSummary;
			set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
			return;
		}
		$postdata['pickup_charge'] = $hasAssignedAllocation ? (float)number_format($assignedCharge, 2, '.', '') : $toFloat($postdata['pickup_charge'] ?? 0);
		if ($hasAssignedAllocation && $postdata['pickup_charge'] > $postdata['amount_requested']) {
			$postdata['pickup_charge'] = $postdata['amount_requested'];
		}
		$postdata['amount_paid_out'] = max(0, $postdata['amount_requested'] - (float)($postdata['pickup_charge'] ?? 0));
		$postdata['amount_involved'] = (float)($postdata['amount_requested'] ?? 0);
		$postdata['amount_transferred'] = (float)($postdata['amount_requested'] ?? 0);

		if ($payMethod === 'cash') {
			$denosRaw = (string)($postdata['denos_json'] ?? '');
			$decoded = json_decode($denosRaw, true);
			$sum = 0.0;
			if (is_array($decoded)) {
				foreach ($decoded as $d) {
					$sum += (float)($d['volume'] ?? 0);
				}
			}
			$sum = (float)number_format($sum, 2, '.', '');
			$expected = (float)number_format((float)($postdata['amount_paid_out'] ?? 0), 2, '.', '');
			if (abs($sum - $expected) > 0.009) {
				$cashpickup->errors['denos_json'] = 'Denominations total must equal cash paid out (GH₵ '.number_format($expected, 2).')';
				set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
				return;
			}
		}
	} catch (\Throwable $e) {
		$cashpickup->errors['tracking_id'] = 'Unable to lookup received pickup.';
		set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
		return;
	}
}

if ($side === 'receive') {
	// Pay-only fields
	$postdata['payment_method'] = '';
	$postdata['pickup_charge'] = 0;
	$postdata['amount_paid_out'] = 0;
	$postdata['amount_transferred'] = 0;
	$postdata['receive_id'] = 0;
	$postdata['pay_type'] = '';
	$postdata['amount_requested'] = 0;
	$postdata['confirmed_by'] = '';
}

if ($side === 'pay') {
	$postdata['amount_paid_out'] = max(0, (float)($postdata['amount_paid_out'] ?? 0));
}

if ($side === 'receive' && $customerType === 'new') {
	$fullName = trim((string)($postdata['sender_details'] ?? ''));
	$phone = trim((string)($postdata['phone_number'] ?? ''));
	$idType = trim((string)($postdata['customer_id_type'] ?? ''));
	$idNumber = trim((string)($postdata['customer_id_number'] ?? ''));
	$location = trim((string)($postdata['customer_location'] ?? ''));
	if ($fullName === '') {
		$cashpickup->errors['sender_details'] = 'Customer full name is required.';
	}
	if ($phone === '' && $idNumber === '') {
		$cashpickup->errors['phone_number'] = 'Phone number or ID number is required.';
	}
	if (!empty($cashpickup->errors)) {
		set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
		return;
	}

	try {
		$customerDb = new \Core\Database;
		\CashpickupLedger\ensure_cashpickup_customer_schema($customerDb);
		\CashpickupLedger\ensure_cashpickup_customer_approval_columns($customerDb);
		$isApprover = (user_can('is_admin') || user_can('is_manager'));
		$sessionCustomer = get_session();
		$customerActorId = (int)($sessionCustomer->id ?? 0);
		$customer = \CashpickupLedger\upsert_customer([
			'full_name' => $fullName,
			'phone_number' => $phone,
			'id_type' => $idType,
			'id_number' => $idNumber,
			'location' => $location,
		], $customerActorId, $customerDb, [
			'approval_status' => $isApprover ? 'approved' : 'pending',
			'approved_by' => $isApprover ? $customerActorId : null,
		]);
		if (empty($customer['id'])) {
			throw new \RuntimeException('Unable to save customer registration.');
		}
		if ($isApprover) {
			message_success('Customer registered and approved. Continue with the cashpickup for this customer.');
			redirect($admin_route . '/' . $plugin_route . '/add?customer_id=' . (int)$customer['id']);
		}
		message_success('Customer registration submitted for approval. A manager or admin must approve before cashpickup can be recorded.');
		redirect($admin_route . '/' . $plugin_route . '/customers');
	} catch (\Throwable $e) {
		$cashpickup->errors['phone_number'] = 'Unable to submit customer registration.';
		set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
	}
	return;
}

// Ensure customer is registered in ledger before recording cashpickup/payout
try {
	$ledgerDb = new \Core\Database;
	\CashpickupLedger\ensure_cashpickup_customer_schema($ledgerDb);
	\CashpickupLedger\ensure_cashpickup_columns($ledgerDb);
	\CashpickupLedger\ensure_cashpickup_customer_approval_columns($ledgerDb);

	$customerId = (int)($postdata['customer_id'] ?? 0);
	$customerPhone = trim((string)($postdata['phone_number'] ?? ''));
	$customerIdNumber = trim((string)($postdata['customer_id_number'] ?? ''));

	$customerRow = null;
	if ($customerId > 0) {
		$rows = $ledgerDb->query(
			"SELECT * FROM cashpickup_customers
			 WHERE deleted = 0
			 AND id = :id
			 AND (approval_status IS NULL OR approval_status = '' OR LOWER(approval_status) = 'approved')
			 LIMIT 1",
			['id' => $customerId]
		);
		if (!empty($rows)) {
			$customerRow = $rows[0];
		}
	}
	if (!$customerRow) {
		$customerRow = \CashpickupLedger\find_customer($ledgerDb, $customerIdNumber, $customerPhone);
	}
	if (!$customerRow) {
		$pendingCustomer = \CashpickupLedger\find_customer_any_status($ledgerDb, $customerIdNumber, $customerPhone);
		if (!empty($pendingCustomer) && !\CashpickupLedger\customer_is_approved($pendingCustomer)) {
			$cashpickup->errors['phone_number'] = 'Customer registration is pending approval. A manager or admin must approve it first.';
		} else {
			$cashpickup->errors['phone_number'] = 'Customer not registered. Please register the customer in the ledger first.';
		}
		set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
		return;
	}

	$postdata['customer_id'] = (int)($customerRow->id ?? 0);
	if (trim((string)($postdata['sender_details'] ?? '')) === '' && !empty($customerRow->full_name)) {
		$postdata['sender_details'] = (string)$customerRow->full_name;
	}
	if (trim((string)($postdata['phone_number'] ?? '')) === '' && !empty($customerRow->phone_number)) {
		$postdata['phone_number'] = (string)$customerRow->phone_number;
	}
	if (trim((string)($postdata['customer_id_type'] ?? '')) === '' && !empty($customerRow->id_type)) {
		$postdata['customer_id_type'] = (string)$customerRow->id_type;
	}
	if (trim((string)($postdata['customer_id_number'] ?? '')) === '' && !empty($customerRow->id_number)) {
		$postdata['customer_id_number'] = (string)$customerRow->id_number;
	}
	if (trim((string)($postdata['customer_location'] ?? '')) === '' && !empty($customerRow->location)) {
		$postdata['customer_location'] = (string)$customerRow->location;
	}

	$updates = [];
	$updateParams = ['id' => (int)($customerRow->id ?? 0)];
	if (trim((string)($postdata['sender_details'] ?? '')) !== '' && trim((string)($customerRow->full_name ?? '')) === '') {
		$updates[] = 'full_name = :full_name';
		$updateParams['full_name'] = (string)$postdata['sender_details'];
	}
	if (trim((string)($postdata['phone_number'] ?? '')) !== '' && trim((string)($customerRow->phone_number ?? '')) === '') {
		$updates[] = 'phone_number = :phone_number';
		$updateParams['phone_number'] = (string)$postdata['phone_number'];
	}
	if (trim((string)($postdata['customer_id_type'] ?? '')) !== '' && trim((string)($customerRow->id_type ?? '')) === '') {
		$updates[] = 'id_type = :id_type';
		$updateParams['id_type'] = (string)$postdata['customer_id_type'];
	}
	if (trim((string)($postdata['customer_id_number'] ?? '')) !== '' && trim((string)($customerRow->id_number ?? '')) === '') {
		$updates[] = 'id_number = :id_number';
		$updateParams['id_number'] = (string)$postdata['customer_id_number'];
	}
	if (trim((string)($postdata['customer_location'] ?? '')) !== '' && trim((string)($customerRow->location ?? '')) === '') {
		$updates[] = 'location = :location';
		$updateParams['location'] = (string)$postdata['customer_location'];
	}
	if (!empty($updates)) {
		$updates[] = 'updated_by = :updated_by';
		$updates[] = 'date_updated = :date_updated';
		$updateParams['updated_by'] = (string)$actorId;
		$updateParams['date_updated'] = date('Y-m-d H:i:s');
		$ledgerDb->query(
			"UPDATE cashpickup_customers SET " . implode(', ', $updates) . " WHERE id = :id",
			$updateParams
		);
	}

	\CashpickupLedger\link_customer_transactions($ledgerDb, (int)($customerRow->id ?? 0), [
		'phone_number' => (string)($postdata['phone_number'] ?? ''),
		'id_type' => (string)($postdata['customer_id_type'] ?? ''),
		'id_number' => (string)($postdata['customer_id_number'] ?? ''),
		'location' => (string)($postdata['customer_location'] ?? ''),
	]);
} catch (\Throwable $e) {
	$cashpickup->errors['phone_number'] = 'Unable to verify customer ledger.';
	set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
	return;
}

// Prevent any spoofed date values; date is system-generated
unset($postdata['date']);

$csrf = csrf_verify($postdata);
if ($csrf && $cashpickup->validate_insert($postdata))
{
	if (user_can('add_cashpickup'))
	{
		$postdata['date_created'] = date("Y-m-d H:i:s");
		$postdata['date'] = date('Y-m-d H:i:s');
		$postdata['id'] = time();

		$session = get_session();
		if (!empty($session->id)) {
			$username = $session->first_name . ' ' . $session->last_name;
			$user_id = $session->id;
		} else {
			$username = 'System';
			$user_id = 0;
		}

		$postdata['created_by'] = $user_id;
		if ($side === 'pay' && ((int)($postdata['assigned_user_id'] ?? 0)) <= 0) {
			$postdata['assigned_user_id'] = (int)$user_id;
		}

		// Approval workflow: pending for non-admin/general manager
		$isPrivileged = (user_can('is_admin'));
		$isGeneralManager = false;
		try {
			$dbRole = new \Core\Database;
			$gm = $dbRole->query(
				"SELECT 1
				 FROM user_roles_map urm
				 JOIN user_roles ur ON ur.id = urm.role_id
				 WHERE urm.disabled = 0
				 AND urm.user_id = :uid
				 AND LOWER(ur.role) IN ('general manager','general_manager','generalmanager','gm','is_general_manager')
				 LIMIT 1",
				['uid' => (int)$user_id]
			);
			$isGeneralManager = !empty($gm);
		} catch (\Throwable $e) {}
		$postdata['approval_status'] = ($isPrivileged || $isGeneralManager) ? 'approved' : 'pending';
		
		// Only set approved_by and approved_at if actually approved by admin/manager
		if ($postdata['approval_status'] === 'approved') {
			$postdata['approved_by'] = $user_id;
			$postdata['approved_at'] = date('Y-m-d H:i:s');
		} else {
			$postdata['approved_by'] = null;
			$postdata['approved_at'] = null;
		}

		// Set transaction_status based on approval_status
		if (!isset($postdata['transaction_status']) || $postdata['transaction_status'] === '') {
			if ($postdata['approval_status'] === 'approved') {
				$postdata['transaction_status'] = 'Approved';
			} else {
				$postdata['transaction_status'] = $side === 'receive' ? 'Pending receive' : 'Pending payout';
			}
		}

		// Validate cash payout denominations exist in user's cashdrawer
		// Only validate/mutate cashdrawer once payout is approved
		if ($side === 'pay' && ($postdata['approval_status'] ?? 'pending') === 'approved' && in_array(strtolower(trim((string)($postdata['payment_method'] ?? ''))), ['cash', 'cash_ghs'], true)) {
			try {
				$bridgePath = realpath(__DIR__ . '/../../backoffice/helpers/cashdrawer-bridge.php');
				$availabilityPath = realpath(__DIR__ . '/../../cfa/helpers/drawer-availability.php');
				if ($bridgePath && file_exists($bridgePath)) {
					require_once $bridgePath;
					\BackOfficeCashdrawer\init();
					$drawerCtx = \BackOfficeCashdrawer\getActiveDrawer((int)$user_id);
					if (empty($drawerCtx)) {
						$cashpickup->errors['denos_json'] = 'No active cashdrawer found for this user.';
						set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
						return;
					}
					if ($availabilityPath && file_exists($availabilityPath)) {
						require_once $availabilityPath;
					}
					$denos = \BackOfficeCashdrawer\normalizeDenominations($postdata['denos_json'] ?? '[]', (float)($postdata['amount_paid_out'] ?? 0), 'GHS');
					$payload = [];
					foreach ($denos as $row) {
						if (strtoupper((string)($row['currency'] ?? 'GHS')) !== 'GHS') continue;
						$payload[] = [
							'denomination' => (float)($row['denomination'] ?? 0),
							'count' => (int)($row['count'] ?? 0),
							'volume' => (float)($row['volume'] ?? 0),
						];
					}
					if (function_exists('Cfas\\cfa_fetch_ghs_denominations') && function_exists('Cfas\\cfa_validate_ghs_supply')) {
						$available = \Cfas\cfa_fetch_ghs_denominations((int)($drawerCtx['drawer_id'] ?? 0), (int)($drawerCtx['drawer_user_id'] ?? $user_id));
						$errs = \Cfas\cfa_validate_ghs_supply($payload, $available);
						if (!empty($errs)) {
							$cashpickup->errors['denos_json'] = implode(' ', $errs);
							set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
							return;
						}
					}
					if (function_exists('BackOfficeCashdrawer\\checkCashDenominationSufficiencyCurrency')) {
						if (function_exists('BackOfficeCashdrawer\\checkCashDenominationCountSufficiencyCurrency')) {
							$countCheck = \BackOfficeCashdrawer\checkCashDenominationCountSufficiencyCurrency(
								(int)($drawerCtx['drawer_user_id'] ?? $user_id),
								$denos,
								(float)($postdata['amount_paid_out'] ?? 0),
								'GHS'
							);
							if (empty($countCheck['ok'])) {
								$shortageParts = [];
								foreach ((array)($countCheck['shortages'] ?? []) as $shortage) {
									$denom = (float)($shortage['denomination'] ?? 0);
									$missing = (int)($shortage['missing_count'] ?? 0);
									if ($denom <= 0 || $missing <= 0) {
										continue;
									}
									$shortageParts[] = 'GHS '.number_format($denom, 2).' x'.$missing;
								}
								$shortageText = !empty($shortageParts) ? implode(', ', $shortageParts) : 'requested mix is unavailable';
								$cashpickup->errors['denos_json'] = 'Cash denominations are insufficient for the assignable amount. Shortages: '.$shortageText;
								message_fail($cashpickup->errors['denos_json']);
								set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
								return;
							}
						}
						$balanceCheck = \BackOfficeCashdrawer\checkCashDenominationSufficiencyCurrency(
							(int)($drawerCtx['drawer_user_id'] ?? $user_id),
							$denos,
							(float)($postdata['amount_paid_out'] ?? 0),
							'GHS'
						);
						if (empty($balanceCheck['ok'])) {
							$availableBalance = (float)($balanceCheck['available'] ?? 0);
							$requiredBalance = (float)($balanceCheck['required'] ?? 0);
							$shortfallBalance = (float)($balanceCheck['shortfall'] ?? max(0, $requiredBalance - $availableBalance));
							$cashpickup->errors['denos_json'] = 'Insufficient cashdrawer balance for this payout. Shortfall: GHâ‚µ '.number_format($shortfallBalance, 2).' (Available: GHâ‚µ '.number_format($availableBalance, 2).' | Required: GHâ‚µ '.number_format($requiredBalance, 2).')';
							message_fail($cashpickup->errors['denos_json']);
							set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
							return;
						}
					}
				}
			} catch (\Throwable $e) {
				$cashpickup->errors['denos_json'] = 'Unable to validate cashdrawer denominations.';
				set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
				return;
			}
		}

		// Validate e-cash balance for transfer payouts
		// Validate sufficiency even for pending payouts (prevents creating an overdraft request)
		// but only mutate cashdrawer once payout is approved.
		if ($side === 'pay' && in_array(strtolower(trim((string)($postdata['payment_method'] ?? ''))), ['transfer', 'transfer_telcos', 'transfer_bank'], true)) {
			try {
				$bridgePath = realpath(__DIR__ . '/../../backoffice/helpers/cashdrawer-bridge.php');
				if ($bridgePath && file_exists($bridgePath)) {
					require_once $bridgePath;
					\BackOfficeCashdrawer\init();
					$drawerCtx = \BackOfficeCashdrawer\getActiveDrawer((int)$user_id);
					if (empty($drawerCtx)) {
						$cashpickup->errors['payout_channel'] = 'No active cashdrawer found for this user.';
						set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
						return;
					}
					$drawerId = (int)($drawerCtx['drawer_id'] ?? 0);
					$drawerUserId = (int)($drawerCtx['drawer_user_id'] ?? $user_id);
					$channel = strtoupper(trim((string)($postdata['payout_channel'] ?? '')));
					$amt = (float)($postdata['amount_paid_out'] ?? 0);
					if ($drawerId <= 0 || $channel === '' || $amt <= 0) {
						$cashpickup->errors['payout_channel'] = 'Invalid transfer payout configuration.';
						message_fail($cashpickup->errors['payout_channel']);
						set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
						return;
					}
					$dbBal = new \Core\Database;
					if (!$dbBal->table_exists(['drawer_balances'])) {
						$cashpickup->errors['payout_channel'] = 'Cashdrawer balances table missing.';
						set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
						return;
					}
					$balRow = $dbBal->query(
						"SELECT closing_balance
						 FROM drawer_balances
						 WHERE drawer_id = :drawer_id AND user_id = :user_id
						 AND channel_type = 'ecash'
						 AND (channel_key = :channel_key OR channel_key = :ecash_channel_key)
						 ORDER BY id DESC LIMIT 1",
						[
							'drawer_id' => $drawerId,
							'user_id' => $drawerUserId,
							'channel_key' => $channel,
							'ecash_channel_key' => 'ECASH_' . $channel,
						]
					);
					$available = !empty($balRow) ? (float)($balRow[0]->closing_balance ?? 0) : 0.0;
					if ($available <= 0.0 && function_exists('Agencybankings\\agency_get_shared_ecash_balance')) {
						$sharedAvailable = (float)\Agencybankings\agency_get_shared_ecash_balance($channel, $channel);
						if ($sharedAvailable > 0) {
							$available = $sharedAvailable;
						}
					}
					if (($amt - $available) > 0.009) {
						$cashpickup->errors['payout_channel'] = 'Insufficient balance in your cashdrawer for '.$channel.'. Available: GH₵ '.number_format($available,2).' Required: GH₵ '.number_format($amt,2);
						message_fail($cashpickup->errors['payout_channel']);
						set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
						return;
					}
				}
			} catch (\Throwable $e) {
				$cashpickup->errors['payout_channel'] = 'Unable to validate cashdrawer balance for transfer payout.';
				message_fail($cashpickup->errors['payout_channel']);
				set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
				return;
			}
		}

		// ===== STRICT BALANCE HARD-BLOCK (pay side, all methods, all users) =====
		// No payout row may be created if the agent's cashdrawer denominations or
		// e-cash/bank balance are insufficient to pay the amount involved.
		if ($side === 'pay') {
			$bridgePathHb = realpath(__DIR__ . '/../../backoffice/helpers/cashdrawer-bridge.php');
			$availabilityPathHb = realpath(__DIR__ . '/../../cfa/helpers/drawer-availability.php');
			$agencyHelperHb = realpath(__DIR__ . '/../../agencybanking/helpers/cashdrawer-sync.php');
			if ($agencyHelperHb && file_exists($agencyHelperHb)) { require_once $agencyHelperHb; }
			if ($bridgePathHb && file_exists($bridgePathHb)) { require_once $bridgePathHb; @\BackOfficeCashdrawer\init(); }
			if ($availabilityPathHb && file_exists($availabilityPathHb)) { require_once $availabilityPathHb; }
			$drawerCtxHb = null;
			if (function_exists('BackOfficeCashdrawer\\getActiveDrawer')) {
				try { $drawerCtxHb = \BackOfficeCashdrawer\getActiveDrawer((int)$user_id); } catch (\Throwable $e) {}
			}
			if (empty($drawerCtxHb)) {
				$cashpickup->errors['payment_method'] = 'No active cashdrawer found for this user. Please open a cash drawer first.';
				message_fail($cashpickup->errors['payment_method']);
				set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
				return;
			}
			$hbDrawerId = (int)($drawerCtxHb['drawer_id'] ?? 0);
			$hbDrawerUserId = (int)($drawerCtxHb['drawer_user_id'] ?? $user_id);
			$hbPaidOut = (float)($postdata['amount_paid_out'] ?? 0);

			if ($payMethod === 'cash_ghs') {
				if (function_exists('BackOfficeCashdrawer\\normalizeDenominations')) {
					$hbDenos = \BackOfficeCashdrawer\normalizeDenominations((string)($postdata['denos_json'] ?? '[]'), $hbPaidOut, 'GHS');
				} else { $hbDenos = []; }
				$hbPayload = []; $hbVol = 0.0;
				foreach ($hbDenos as $hbRow) {
					if (strtoupper((string)($hbRow['currency'] ?? 'GHS')) !== 'GHS') continue;
					$hbPayload[] = ['denomination' => (float)$hbRow['denomination'], 'count' => (int)$hbRow['count'], 'volume' => (float)$hbRow['volume']];
					$hbVol += (float)$hbRow['volume'];
				}
				if (abs($hbVol - $hbPaidOut) > 0.009) {
					$cashpickup->errors['denos_json'] = 'GHS denomination volume (GH₵ ' . number_format($hbVol, 2) . ') must equal amount paid out (GH₵ ' . number_format($hbPaidOut, 2) . ').';
					message_fail($cashpickup->errors['denos_json']);
					set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
					return;
				}
				if (function_exists('Cfas\\cfa_fetch_ghs_denominations') && function_exists('Cfas\\cfa_validate_ghs_supply')) {
					$hbAvail = \Cfas\cfa_fetch_ghs_denominations($hbDrawerId, $hbDrawerUserId);
					$hbErrs = \Cfas\cfa_validate_ghs_supply($hbPayload, $hbAvail);
					if (!empty($hbErrs)) {
						$cashpickup->errors['denos_json'] = 'Insufficient GHS cash. ' . implode(' ', $hbErrs);
						message_fail($cashpickup->errors['denos_json']);
						set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
						return;
					}
				}
			} elseif ($payMethod === 'cash_cfa') {
				$hbCfaAmt = (float)($postdata['cfa_amount'] ?? 0);
				$hbCfaDecoded = json_decode((string)($postdata['cfa_denos_json'] ?? '[]'), true);
				$hbCfaPayload = []; $hbCfaVol = 0.0;
				foreach ((is_array($hbCfaDecoded) ? $hbCfaDecoded : []) as $hbCfaRow) {
					$d = (float)($hbCfaRow['denomination'] ?? 0); $c = (int)($hbCfaRow['count'] ?? 0);
					if ($d <= 0 || $c <= 0) continue;
					$hbCfaPayload[] = ['denomination' => $d, 'count' => $c, 'volume' => $d * $c];
					$hbCfaVol += $d * $c;
				}
				if (abs($hbCfaVol - $hbCfaAmt) > 5) {
					$cashpickup->errors['cfa_denos_json'] = 'CFA denomination volume (' . number_format($hbCfaVol, 0) . ') must equal the CFA amount (' . number_format($hbCfaAmt, 0) . ').';
					message_fail($cashpickup->errors['cfa_denos_json']);
					set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
					return;
				}
				if (function_exists('Cfas\\cfa_fetch_fx_denominations') && function_exists('Cfas\\cfa_validate_fx_supply')) {
					$hbCfaAvail = \Cfas\cfa_fetch_fx_denominations($hbDrawerId, $hbDrawerUserId, 'CFA');
					$hbCfaErrs = \Cfas\cfa_validate_fx_supply($hbCfaPayload, $hbCfaAvail, 'CFA');
					if (!empty($hbCfaErrs)) {
						$cashpickup->errors['cfa_denos_json'] = 'Insufficient CFA cash. ' . implode(' ', $hbCfaErrs);
						message_fail($cashpickup->errors['cfa_denos_json']);
						set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
						return;
					}
				}
			} elseif ($payMethod === 'transfer_telcos') {
				$hbChan = strtoupper(trim((string)($postdata['payout_channel'] ?? '')));
				$hbAvail = 0.0;
				$dbHb = new \Core\Database;
				if ($dbHb->table_exists(['drawer_balances']) && $hbChan !== '') {
					$hbBalRow = $dbHb->query(
						"SELECT closing_balance FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash' AND (channel_key = :ck1 OR channel_key = :ck2) ORDER BY id DESC LIMIT 1",
						['drawer_id' => $hbDrawerId, 'user_id' => $hbDrawerUserId, 'ck1' => $hbChan, 'ck2' => 'ECASH_' . $hbChan]
					);
					$hbAvail = !empty($hbBalRow) ? (float)($hbBalRow[0]->closing_balance ?? 0) : 0.0;
				}
				if ($hbAvail <= 0.0 && function_exists('Agencybankings\\agency_get_shared_ecash_balance')) {
					$hbShared = (float)\Agencybankings\agency_get_shared_ecash_balance($hbChan, $hbChan);
					if ($hbShared > 0) $hbAvail = $hbShared;
				}
				if (($hbPaidOut - $hbAvail) > 0.009) {
					$cashpickup->errors['payout_channel'] = 'Insufficient ' . $hbChan . ' balance. Available: GH₵ ' . number_format($hbAvail, 2) . ' | Required: GH₵ ' . number_format($hbPaidOut, 2);
					message_fail($cashpickup->errors['payout_channel']);
					set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
					return;
				}
			} elseif ($payMethod === 'transfer_bank') {
				$hbBank = trim((string)($postdata['account_paid_to'] ?? ''));
				if (function_exists('Agencybankings\\agency_canonicalize_drawer_bank_name')) {
					$hbBank = \Agencybankings\agency_canonicalize_drawer_bank_name($hbBank);
				}
				$hbNet = strtoupper(preg_replace('/[^A-Z0-9]+/i', '_', $hbBank)); $hbNet = trim($hbNet, '_');
				$hbChanKey = $hbNet !== '' ? ('ECASH_' . $hbNet) : '';
				$hbAvail = 0.0;
				$dbHb = new \Core\Database;
				if ($dbHb->table_exists(['drawer_balances']) && $hbChanKey !== '') {
					$hbBalRow = $dbHb->query(
						"SELECT closing_balance FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash' AND (channel_key = :ck1 OR channel_key = :ck2) ORDER BY id DESC LIMIT 1",
						['drawer_id' => $hbDrawerId, 'user_id' => $hbDrawerUserId, 'ck1' => $hbChanKey, 'ck2' => $hbNet]
					);
					$hbAvail = !empty($hbBalRow) ? (float)($hbBalRow[0]->closing_balance ?? 0) : 0.0;
				}
				if ($hbAvail <= 0.0 && $hbChanKey !== '' && function_exists('Agencybankings\\agency_get_shared_ecash_balance')) {
					$hbShared = (float)\Agencybankings\agency_get_shared_ecash_balance($hbChanKey, $hbBank);
					if ($hbShared > 0) $hbAvail = $hbShared;
				}
				if (($hbPaidOut - $hbAvail) > 0.009) {
					$cashpickup->errors['account_paid_to'] = 'Insufficient ' . $hbBank . ' balance. Available: GH₵ ' . number_format($hbAvail, 2) . ' | Required: GH₵ ' . number_format($hbPaidOut, 2);
					message_fail($cashpickup->errors['account_paid_to']);
					set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
					return;
				}
			}
		}

		$cashpickup->insert($postdata);
		if (!empty($cashpickup->has_error)) {
			$err = (string)($cashpickup->error ?? 'Insert failed');
			$dup = (stripos($err, 'duplicate entry') !== false) || (stripos($err, 'duplicate') !== false && stripos($err, 'primary') !== false);
			if ($dup) {
				try {
					$postdata['id'] = (int)floor(microtime(true) * 1000000);
					$cashpickup->insert($postdata);
				} catch (\Throwable $e) {
					// ignore
				}
			}
			if (!empty($cashpickup->has_error)) {
				$err2 = (string)($cashpickup->error ?? $err);
				error_log('cashpickup insert failed: ' . $err2);
				$cashpickup->errors['transaction_side'] = 'Unable to save cashpickup. ' . $err2;
				message_fail('Unable to save cashpickup.');
				set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
				return;
			}
		}

		$chargeValue = (float)($postdata['pickup_charge'] ?? 0);
		if ($chargeValue > 0) {
			try {
				$commissionDb = new \Core\Database;
				$sideLabel = strtoupper((string)$side);
				$methodLabel = $side === 'pay'
					? trim((string)($postdata['payment_method'] ?? ''))
					: trim((string)($postdata['source'] ?? ''));
				$chargeNetwork = $side === 'pay'
					? trim((string)($postdata['payout_channel'] ?? ($postdata['payment_method'] ?? '')))
					: trim((string)($postdata['source'] ?? ''));
				$commissionDescription = 'CASHPICKUP CHARGE | ' . $sideLabel . ' | ' . $methodLabel . ' | ID ' . ($postdata['id'] ?? '');
				$commissionDb->query(
					"DELETE FROM commissions WHERE commission_type = :commission_type AND description LIKE :needle",
					[
						'commission_type' => 'CASHPICKUP CHARGE',
						'needle' => '%| ID ' . ($postdata['id'] ?? ''),
					]
				);
				$commissionInsert = [
					'date_received' => $postdata['date_created'] ?? date('Y-m-d H:i:s'),
					'colletion_period' => 'INSTANT',
					'network' => $chargeNetwork !== '' ? $chargeNetwork : null,
					'commission_type' => 'CASHPICKUP CHARGE',
					'description' => $commissionDescription,
					'pay_to' => $user_id,
					'receiving_wallet' => null,
					'amount' => $chargeValue,
					'transaction_status' => $postdata['transaction_status'] ?? ($postdata['approval_status'] === 'approved' ? 'Approved' : ($side === 'receive' ? 'Pending receive' : 'Pending payout')),
					'created_by' => $user_id,
					'updated_by' => null,
					'disabled' => 0,
					'deleted' => 0,
					'date_created' => $postdata['date_created'] ?? date('Y-m-d H:i:s'),
					'date_updated' => null,
					'date_deleted' => null,
				];
				$commissionSql = "INSERT INTO commissions (".implode(',', array_keys($commissionInsert)).") VALUES (:".implode(',:', array_keys($commissionInsert)).")";
				$commissionDb->query($commissionSql, $commissionInsert);
			} catch (\Throwable $e) {
				error_log('CASHPICKUP CHARGE COMMISSION INSERT FAILED: ' . $e->getMessage());
			}
		}

		// Sync cash IN for receive-side transactions
		// Only mutate cashdrawer once receive is approved
		if ($side === 'receive' && ($postdata['approval_status'] ?? 'pending') === 'approved') {
			try {
				$bridgePath = realpath(__DIR__ . '/../../backoffice/helpers/cashdrawer-bridge.php');
				if ($bridgePath && file_exists($bridgePath)) {
					require_once $bridgePath;
					\BackOfficeCashdrawer\init();
					$src = strtolower(trim((string)($postdata['source'] ?? '')));
					if ($src === 'cash' || $src === '') {
						$denos = \BackOfficeCashdrawer\normalizeDenominations($postdata['denos_json'] ?? '[]', (float)($postdata['amount_involved'] ?? 0), 'GHS');
						\BackOfficeCashdrawer\syncCashMovement((int)$user_id, $denos, (float)($postdata['amount_involved'] ?? 0), (string)($postdata['transaction_status'] ?? 'Approved'), (int)($postdata['id'] ?? 0), 'in');
					}
				}
			} catch (\Throwable $e) {
				// fail silently
			}
		}

		// Sync e-cash/bank IN for receive-side transactions (do not depend on bridge)
		// Only mutate cashdrawer once receive is approved
		if ($side === 'receive' && ($postdata['approval_status'] ?? 'pending') === 'approved') {
			try {
				$src = strtolower(trim((string)($postdata['source'] ?? '')));
				if (in_array($src, ['mtn','telecel','at','bank'], true)) {
					$amt = (float)($postdata['amount_involved'] ?? 0);
					if ($amt > 0) {
						if ($src === 'bank') {
							$bankName = trim((string)($postdata['account_paid_to'] ?? ''));
							if ($bankName !== '') {
								$agencyHelper = realpath(__DIR__ . '/../../agencybanking/helpers/cashdrawer-sync.php');
								if ($agencyHelper && file_exists($agencyHelper)) {
									require_once $agencyHelper;
								}

								$canSharedSync = function_exists('Agencybankings\\agency_refresh_ecash_channel');
								if (function_exists('Agencybankings\\agency_canonicalize_drawer_bank_name')) {
									$bankName = \Agencybankings\agency_canonicalize_drawer_bank_name($bankName);
								}
								$network = strtoupper(preg_replace('/[^A-Z0-9]+/i', '_', $bankName));
								$network = trim($network, '_');
								$baseChannelKey = $network !== '' ? ('ECASH_' . $network) : '';

								if ($baseChannelKey !== '' && $canSharedSync) {
									$transId = (int)($postdata['id'] ?? 0);
									$transStatus = (string)($postdata['transaction_status'] ?? 'Approved');
									$sharedOpening = 0.0;
									if (function_exists('Agencybankings\\agency_get_shared_ecash_balance')) {
										$sharedOpening = (float)\Agencybankings\agency_get_shared_ecash_balance($baseChannelKey, $bankName);
									}

									$appliedTargets = [];
									$markApplied = function (int $dId, int $uId) use (&$appliedTargets): void {
										$appliedTargets[$dId . ':' . $uId] = true;
									};
									$hasApplied = function (int $dId, int $uId) use (&$appliedTargets): bool {
										return !empty($appliedTargets[$dId . ':' . $uId]);
									};
									$applyToDrawer = function (int $drawerId, int $drawerUserId) use ($baseChannelKey, $bankName, $amt, $transId, $transStatus, $sharedOpening): void {
										if ($drawerId <= 0 || $drawerUserId <= 0) {
											return;
										}
										$writeKey = $baseChannelKey;
										if (function_exists('Agencybankings\\agency_find_existing_ecash_channel_key')) {
											$existingKey = \Agencybankings\agency_find_existing_ecash_channel_key($drawerId, $drawerUserId, $writeKey);
											if (!empty($existingKey)) {
												$writeKey = (string)$existingKey;
											}
										}
										if ($writeKey === $baseChannelKey && function_exists('Agencybankings\\agency_resolve_ecash_channel_key')) {
											$resolved = \Agencybankings\agency_resolve_ecash_channel_key($drawerId, $drawerUserId, $bankName);
											if (!empty($resolved)) {
												$writeKey = (string)$resolved;
											}
										}
										$seedOpening = null;
										if (function_exists('Agencybankings\\agency_drawer_has_ecash_channel')) {
											$hasChannel = \Agencybankings\agency_drawer_has_ecash_channel($drawerId, $drawerUserId, $writeKey);
											if (!$hasChannel && $sharedOpening > 0) {
												$seedOpening = $sharedOpening;
											}
										}
										\Agencybankings\agency_refresh_ecash_channel($drawerId, $drawerUserId, $writeKey, 'in', $amt, $transId, $transStatus, $seedOpening);
									};

									$drawerId = 0;
									$drawerUserId = 0;
									if (function_exists('Agencybankings\\agency_find_active_drawer')) {
										$ctx = \Agencybankings\agency_find_active_drawer((int)$user_id);
										if (!empty($ctx)) {
											$drawerId = (int)($ctx['drawer_id'] ?? 0);
											$drawerUserId = (int)($ctx['drawer_user_id'] ?? 0);
										}
									}
									if ($drawerId <= 0 || $drawerUserId <= 0) {
										try {
											$dbOwner = new \Core\Database;
											$today = date('Y-m-d');
											$row = $dbOwner->query("SELECT id, user_id FROM cashdrawers WHERE user_id = :user_id AND DATE(date_created) = :today ORDER BY id DESC LIMIT 1", ['user_id' => $user_id, 'today' => $today]);
											if (empty($row)) {
												$row = $dbOwner->query("SELECT id, user_id FROM cashdrawers WHERE user_id = :user_id ORDER BY date_created DESC, id DESC LIMIT 1", ['user_id' => $user_id]);
											}
											if (!empty($row)) {
												$drawerId = (int)($row[0]->id ?? 0);
												$drawerUserId = (int)($row[0]->user_id ?? 0);
											}
										} catch (\Throwable $e) {}
									}
									if ($drawerId > 0 && $drawerUserId > 0) {
										$applyToDrawer($drawerId, $drawerUserId);
										$markApplied($drawerId, $drawerUserId);
									}

									if (function_exists('Agencybankings\\agency_fetch_admin_manager_drawers_today')) {
										$targets = \Agencybankings\agency_fetch_admin_manager_drawers_today();
										if (!empty($targets)) {
											foreach ($targets as $ctx) {
												$targetDrawerId = (int)($ctx['drawer_id'] ?? 0);
												$targetDrawerUserId = (int)($ctx['drawer_user_id'] ?? 0);
												if ($targetDrawerId <= 0 || $targetDrawerUserId <= 0) {
													continue;
												}
												if ($hasApplied($targetDrawerId, $targetDrawerUserId)) {
													continue;
												}
												$applyToDrawer($targetDrawerId, $targetDrawerUserId);
												$markApplied($targetDrawerId, $targetDrawerUserId);
											}
										}
									}
								} else {
									$src = 'bank';
								}
							}
						}

						if ($src !== 'bank') {
							$chanKey = strtoupper($src);
							if ($chanKey !== '') {
								$dbOwner = new \Core\Database;
								$today = date('Y-m-d');
								$row = $dbOwner->query("SELECT id, user_id FROM cashdrawers WHERE user_id = :user_id AND DATE(date_created) = :today ORDER BY id DESC LIMIT 1", ['user_id' => $user_id, 'today' => $today]);
								if (empty($row)) {
									$row = $dbOwner->query("SELECT id, user_id FROM cashdrawers WHERE user_id = :user_id ORDER BY date_created DESC, id DESC LIMIT 1", ['user_id' => $user_id]);
								}
								$drawerId = !empty($row) ? (int)($row[0]->id ?? 0) : 0;
								$drawerUserId = !empty($row) ? (int)($row[0]->user_id ?? 0) : 0;
								if ($drawerId > 0 && $drawerUserId > 0) {
									$dbBal = new \Core\Database;
									if ($dbBal->table_exists(['drawer_balances'])) {
										$now = date('Y-m-d H:i:s');
										$transId = (int)($postdata['id'] ?? 0);
										$lookupKey = (string)$chanKey;
										$altLookupKey = $lookupKey;
										if (stripos($lookupKey, 'ECASH_') === 0) {
											$altLookupKey = substr($lookupKey, 6);
										} else {
											$altLookupKey = 'ECASH_' . $lookupKey;
										}
										$rowsEcash = $dbBal->query(
											"SELECT * FROM drawer_balances
											 WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash'
											 AND (channel_key = :channel_key OR channel_key = :ecash_channel_key)
											 ORDER BY id DESC LIMIT 1",
											[
												'drawer_id' => $drawerId,
												'user_id' => $drawerUserId,
												'channel_key' => $lookupKey,
												'ecash_channel_key' => $altLookupKey,
											]
										);
										if (empty($rowsEcash)) {
											$opening = 0.0;
											$closing = $opening + $amt;
											$dbBal->query(
												"INSERT INTO drawer_balances (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status)
												 VALUES (:drawer_id, :user_id, 'ecash', :channel_key, :opening, :credit, :debit, :closing, :date_created, :trans_id, :trans_status)",
												[
													'drawer_id' => $drawerId,
													'user_id' => $drawerUserId,
													'channel_key' => $chanKey,
													'opening' => $opening,
													'credit' => $amt,
													'debit' => 0.0,
													'closing' => $closing,
													'date_created' => $now,
													'trans_id' => $transId,
													'trans_status' => (string)($postdata['transaction_status'] ?? ($postdata['approval_status'] === 'approved' ? 'Approved' : 'Pending payout')),
												]
											);
										} else {
											$rowEcash = $rowsEcash[0];
											$opening = (float)($rowEcash->opening_balance ?? 0);
											$newCredit = (float)($rowEcash->credit ?? 0) + $amt;
											$newDebit = (float)($rowEcash->debit ?? 0);
											$closing = $opening + $newCredit - $newDebit;
											$dbBal->query(
												"UPDATE drawer_balances
												 SET channel_key = :channel_key, credit = :credit, debit = :debit, closing_balance = :closing
												 WHERE id = :id",
												[
													'channel_key' => $chanKey,
													'credit' => $newCredit,
													'debit' => $newDebit,
													'closing' => $closing,
													'id' => (int)($rowEcash->id ?? 0),
												]
											);
										}
									}
								}
							}
						}
					}
				}
			} catch (\Throwable $e) {
				// fail silently
			}
		}

		// Sync e-cash OUT for pay-side transfer payouts (telcos + bank)
		$payMethodForSync = strtolower(trim((string)($postdata['payment_method'] ?? '')));
		if ($side === 'pay' && ($postdata['approval_status'] ?? 'pending') === 'approved' && in_array($payMethodForSync, ['transfer', 'transfer_telcos', 'transfer_bank'], true)) {
			try {
				$bridgePath = realpath(__DIR__ . '/../../backoffice/helpers/cashdrawer-bridge.php');
				if ($bridgePath && file_exists($bridgePath)) {
					require_once $bridgePath;
					\BackOfficeCashdrawer\init();
					$drawerId = 0;
					$drawerUserId = 0;
					try {
						$dbOwner = new \Core\Database;
						$today = date('Y-m-d');
						$row = $dbOwner->query("SELECT id, user_id FROM cashdrawers WHERE user_id = :user_id AND DATE(date_created) = :today ORDER BY id DESC LIMIT 1", ['user_id' => $user_id, 'today' => $today]);
						if (empty($row)) {
							$row = $dbOwner->query("SELECT id, user_id FROM cashdrawers WHERE user_id = :user_id ORDER BY date_created DESC, id DESC LIMIT 1", ['user_id' => $user_id]);
						}
						if (!empty($row)) {
							$drawerId = (int)($row[0]->id ?? 0);
							$drawerUserId = (int)($row[0]->user_id ?? 0);
						}
					} catch (\Throwable $e) {}
					if ($drawerId > 0 && $drawerUserId > 0) {
						$dbBal = new \Core\Database;
						if ($drawerId > 0 && $dbBal->table_exists(['drawer_balances'])) {
							$now = date('Y-m-d H:i:s');
							// Resolve the channel name: telco (mtn/telecel/at) or bank name.
							$channelRaw = trim((string)($postdata['payout_channel'] ?? ''));
							if ($channelRaw === '' && $payMethodForSync === 'transfer_bank') {
								$channelRaw = trim((string)($postdata['account_paid_to'] ?? ''));
							}
							if ($payMethodForSync === 'transfer_bank' && function_exists('Agencybankings\\agency_canonicalize_drawer_bank_name')) {
								$channelRaw = \Agencybankings\agency_canonicalize_drawer_bank_name($channelRaw);
							}
							$channel = strtoupper(preg_replace('/[^A-Z0-9]+/i', '_', $channelRaw));
							$channel = trim($channel, '_');
							if ($payMethodForSync === 'transfer_bank' && $channel !== '') {
								$channel = 'ECASH_' . $channel;
							}
							$amt = (float)($postdata['amount_paid_out'] ?? 0);
							$transId = (int)($postdata['id'] ?? 0);
							if ($channel !== '' && $amt > 0) {
								$sharedOpening = 0.0;
								if (function_exists('Agencybankings\\agency_get_shared_ecash_balance')) {
									$sharedOpening = (float)\Agencybankings\agency_get_shared_ecash_balance($channel, $channelRaw);
								}
								$writeKey = $channel;
								if (function_exists('Agencybankings\\agency_find_existing_ecash_channel_key')) {
									$existingKey = \Agencybankings\agency_find_existing_ecash_channel_key($drawerId, $drawerUserId, $writeKey);
									if (!empty($existingKey)) {
										$writeKey = (string)$existingKey;
									}
								}
								if ($writeKey === $channel && function_exists('Agencybankings\\agency_resolve_ecash_channel_key')) {
									$resolved = \Agencybankings\agency_resolve_ecash_channel_key($drawerId, $drawerUserId, $channelRaw);
									if (!empty($resolved)) {
										$writeKey = (string)$resolved;
									}
								}
								$seedOpening = null;
								if (function_exists('Agencybankings\\agency_drawer_has_ecash_channel')) {
									$hasChannel = \Agencybankings\agency_drawer_has_ecash_channel($drawerId, $drawerUserId, $writeKey);
									if (!$hasChannel && $sharedOpening > 0) {
										$seedOpening = $sharedOpening;
									}
								}
								if (function_exists('Agencybankings\\agency_refresh_ecash_channel')) {
									\Agencybankings\agency_refresh_ecash_channel($drawerId, $drawerUserId, $writeKey, 'out', $amt, $transId, (string)($postdata['transaction_status'] ?? 'Approved'), $seedOpening);
								}
							}
						}
					}
				}
			} catch (\Throwable $e) {
				// fail silently
			}
		}

		// Sync cash OUT for pay-side cash payouts (GHS + CFA FX)
		if ($side === 'pay' && ($postdata['approval_status'] ?? 'pending') === 'approved') {
			$payMethodSyncCash = strtolower(trim((string)($postdata['payment_method'] ?? '')));
			// GHS cash out
			if (in_array($payMethodSyncCash, ['cash', 'cash_ghs'], true)) {
				try {
					$bridgePath = realpath(__DIR__ . '/../../backoffice/helpers/cashdrawer-bridge.php');
					if ($bridgePath && file_exists($bridgePath)) {
						require_once $bridgePath;
						\BackOfficeCashdrawer\init();
						$denos = \BackOfficeCashdrawer\normalizeDenominations($postdata['denos_json'] ?? '[]', (float)($postdata['amount_paid_out'] ?? 0), 'GHS');
						\BackOfficeCashdrawer\syncCashMovement((int)$user_id, $denos, (float)($postdata['amount_paid_out'] ?? 0), (string)($postdata['transaction_status'] ?? 'Approved'), (int)($postdata['id'] ?? 0), 'out');
					}
				} catch (\Throwable $e) {
					// fail silently
				}
			}
			// CFA cash out: decrement CFA FX denominations
			if ($payMethodSyncCash === 'cash_cfa' && function_exists('Cfas\\cfa_process_fx_denominations')) {
				try {
					$bridgePath = realpath(__DIR__ . '/../../backoffice/helpers/cashdrawer-bridge.php');
					if ($bridgePath && file_exists($bridgePath)) {
						require_once $bridgePath;
						\BackOfficeCashdrawer\init();
					}
					$drawerIdSync = 0; $drawerUserIdSync = 0;
					try {
						$dbOwner = new \Core\Database;
						$today = date('Y-m-d');
						$drow = $dbOwner->query("SELECT id, user_id FROM cashdrawers WHERE user_id = :user_id AND DATE(date_created) = :today ORDER BY id DESC LIMIT 1", ['user_id' => $user_id, 'today' => $today]);
						if (empty($drow)) {
							$drow = $dbOwner->query("SELECT id, user_id FROM cashdrawers WHERE user_id = :user_id ORDER BY date_created DESC, id DESC LIMIT 1", ['user_id' => $user_id]);
						}
						if (!empty($drow)) {
							$drawerIdSync = (int)($drow[0]->id ?? 0);
							$drawerUserIdSync = (int)($drow[0]->user_id ?? 0);
						}
					} catch (\Throwable $e) {}
					if ($drawerIdSync > 0 && $drawerUserIdSync > 0) {
						$cfaDecoded = json_decode((string)($postdata['cfa_denos_json'] ?? '[]'), true);
						$cfaDenos = is_array($cfaDecoded) ? $cfaDecoded : [];
						\Cfas\cfa_process_fx_denominations($drawerIdSync, $drawerUserIdSync, $cfaDenos, -1, (string)($postdata['transaction_status'] ?? 'Approved'), (int)($postdata['id'] ?? 0), date('Y-m-d H:i:s'), (float)($postdata['cfa_amount'] ?? 0));
					}
				} catch (\Throwable $e) {
					error_log('CASHDRAWER SYNC FAILED (PAY CFA OUT): ' . $e->getMessage());
				}
			}
		}

		if (
			$side === 'receive'
			&& ($postdata['approval_status'] ?? 'pending') === 'approved'
			&& strtolower(trim((string)($postdata['credit_mode'] ?? ''))) !== 'credit'
		) {
			try {
				$creditDb = new \Core\Database;
				\CashpickupLedger\apply_receive_credit_repayments($creditDb, (int)($postdata['id'] ?? 0), (int)$user_id);
			} catch (\Throwable $e) {
				// Credit recovery tracking should never block a successful receive.
			}
		}

		message_success("Cash pickup recorded successfully!");
		redirect($admin_route.'/'.$plugin_route);
	}
}

if(!$csrf)
	$cashpickup->errors['transaction_side'] = "Form expired!";

set_value('errors',$cashpickup->errors);

<?php

$session = get_session();
$actorId = (int)($session->id ?? 0);
$transferHelperPath = realpath(__DIR__ . '/../helpers/transfer-allocation.php');
if ($transferHelperPath && file_exists($transferHelperPath)) {
	require_once $transferHelperPath;
}

if(!(user_can('is_manager') || user_can('is_admin'))){
	message_fail('Access denied');
	redirect($admin_route.'/'.$plugin_route);
}

$id = (int)(URL(3) ?? 0);
if($id <= 0){
	message_fail('Invalid record');
	redirect($admin_route.'/'.$plugin_route);
}

$row = $cashpickup->first(['id'=>$id]);
if(empty($row)){
	message_fail('Record not found');
	redirect($admin_route.'/'.$plugin_route);
}

// Only managers/admins can transfer or reassign
$isPrivileged = (user_can('is_manager') || user_can('is_admin'));
if (!$isPrivileged) {
	message_fail('Access denied');
	redirect($admin_route.'/'.$plugin_route.'/view/'.$id);
}

$side = strtolower(trim((string)($row->transaction_side ?? '')));
$isCreditPayout = $side === 'receive' && strtolower(trim((string)($row->credit_mode ?? ''))) === 'credit';
$approvalStatus = strtolower(trim((string)($row->approval_status ?? ($isCreditPayout ? 'pending' : 'approved'))));
$canTransferPendingCreditPayout = $isCreditPayout && in_array($approvalStatus, ['', 'pending', 'approved'], true);
if (!$canTransferPendingCreditPayout && $approvalStatus !== '' && $approvalStatus !== 'approved') {
	message_fail('This cashpickup is pending approval and cannot be transferred');
	redirect($admin_route.'/'.$plugin_route.'/view/'.$id);
}

if($side !== 'receive'){
	message_fail('Only receive cashpickups can be transferred');
	redirect($admin_route.'/'.$plugin_route.'/view/'.$id);
}

$targetUserId = (int)($req->post('assigned_user_id') ?? 0);
if($targetUserId <= 0){
	message_fail('Please select a user');
	redirect($admin_route.'/'.$plugin_route.'/view/'.$id);
}
$transferAmount = (float)($req->post('transfer_amount') ?? 0);

try{
	$db = new \Core\Database;

	// Ensure transfer ledger table exists
	try {
		$dbSchema = new \Core\Database;
		if (!$dbSchema->table_exists(['cashpickup_transfers'])) {
			$dbSchema->query(
					"CREATE TABLE IF NOT EXISTS cashpickup_transfers (
						id BIGINT unsigned NOT NULL AUTO_INCREMENT,
						receive_id BIGINT unsigned NULL,
						from_user_id INT unsigned NULL,
						source_user_id INT unsigned NULL,
						to_user_id INT unsigned NULL,
						amount FLOAT(10,2) DEFAULT 0.00,
						transfer_charge FLOAT(10,2) DEFAULT 0.00,
					approval_status varchar(20) null,
					sender_mode varchar(20) null,
					sender_channel_key varchar(150) null,
					receiver_mode varchar(20) null,
					receiver_channel_key varchar(150) null,
					transfer_mode varchar(20) null,
					transfer_channel varchar(100) null,
					transfer_bank varchar(150) null,
					denos_json JSON null,
					created_by INT unsigned null,
					date_created datetime default null,
					PRIMARY KEY (id),
					KEY receive_id (receive_id),
					KEY from_user_id (from_user_id),
					KEY to_user_id (to_user_id),
					KEY date_created (date_created)
				)"
			);
		}
	} catch (\Throwable $e) {}

	try {
	if (function_exists('CashpickupTransferAllocation\\ensure_cashpickup_transfer_schema')) {
			$dbEnsure = new \Core\Database;
			\CashpickupTransferAllocation\ensure_cashpickup_transfer_schema($dbEnsure);
		}
	} catch (\Throwable $e) {}

	// Ensure transfer ledger approval_status column exists
	try {
		$dbSchema = new \Core\Database;
		$hasCol = $dbSchema->query(
			"SELECT COLUMN_NAME
			 FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'cashpickup_transfers' AND COLUMN_NAME = 'approval_status'
			 LIMIT 1",
			['db' => DB_NAME]
		);
		if (empty($hasCol)) {
			$dbSchema->query("ALTER TABLE cashpickup_transfers ADD COLUMN approval_status varchar(20) null");
		}
	} catch (\Throwable $e) {}

	// Ensure transfer audit columns exist
	try {
		$dbSchema = new \Core\Database;
		$neededCols = ['transfer_mode', 'transfer_channel', 'transfer_bank'];
		$missingCols = [];
		foreach ($neededCols as $col) {
			$hasCol = $dbSchema->query(
				"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'cashpickups' AND COLUMN_NAME = :col LIMIT 1",
				['db' => DB_NAME, 'col' => $col]
			);
			if (empty($hasCol)) {
				$missingCols[] = $col;
			}
		}
		if (!empty($missingCols)) {
			$typeMap = [
				'transfer_mode' => 'varchar(20) null',
				'transfer_channel' => 'varchar(100) null',
				'transfer_bank' => 'varchar(150) null',
			];
			foreach ($missingCols as $col) {
				try {
					$colType = $typeMap[$col] ?? 'VARCHAR(255) NULL';
					$dbSchema->query("ALTER TABLE cashpickups ADD COLUMN {$col} {$colType}");
				} catch (\Throwable $e) {}
			}
		}
	} catch (\Throwable $e) {}

	// Ensure transfer source tracking column exists
	try {
		$dbSchema = new \Core\Database;
		$hasCol = $dbSchema->query(
			"SELECT COLUMN_NAME
			 FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = :db
			 AND TABLE_NAME = 'cashpickup_transfers'
			 AND COLUMN_NAME = 'source_user_id'
			 LIMIT 1",
			['db' => DB_NAME]
		);
		if (empty($hasCol)) {
			$dbSchema->query("ALTER TABLE cashpickup_transfers ADD COLUMN source_user_id INT unsigned NULL AFTER from_user_id");
		}
	} catch (\Throwable $e) {}

	// Ensure target exists, is not manager/admin, and is marked Present/Late today
	$target = $db->query(
		"SELECT u.id
		 FROM users u
		 WHERE u.deleted = 0
		 AND u.id = :uid
		 AND EXISTS (
			SELECT 1
			FROM attendance a
			WHERE a.deleted = 0
			AND a.user_id = u.id
			AND a.date = CURDATE()
			AND UPPER(a.remarks) IN ('PRESENT','LATE')
		 )
		 AND NOT EXISTS (
			SELECT 1
			FROM user_roles_map urm
			JOIN user_roles ur ON ur.id = urm.role_id
			WHERE urm.disabled = 0
			AND urm.user_id = u.id
			AND LOWER(ur.role) IN ('manager','admin','is_manager','is_admin')
		 )
		 LIMIT 1",
		['uid' => $targetUserId]
	);
	if(empty($target)){
		message_fail('Selected user is not eligible for assignment');
		redirect($admin_route.'/'.$plugin_route.'/view/'.$id);
	}

	$amountInvolved = (float)($row->amount_involved ?? 0);
	$normalizeEcashKey = function(string $key): string {
		$key = trim($key);
		if ($key === '') return '';
		$upper = strtoupper($key);
		if (in_array($upper, ['MTN','TELECEL','AT'], true)) return $upper;
		return $key;
	};
	$resolveDrawerBankKey = function(int $drawerId, int $drawerUserId, string $bankNameRaw) {
		$bankNameRaw = trim((string)$bankNameRaw);
		if ($drawerId <= 0 || $drawerUserId <= 0 || $bankNameRaw === '') return '';
		$dbBal = new \Core\Database;
		if (!$dbBal->table_exists(['drawer_balances'])) return '';
		$normalizeSlug = function(string $v): string {
			$v = strtoupper(trim($v));
			$v = preg_replace('/[^A-Z0-9]+/i', '_', $v);
			$v = trim($v, '_');
			return $v;
		};
		$needSlug = $normalizeSlug($bankNameRaw);
		$needCompact = str_replace('_', '', $needSlug);
		$resolvedExisting = '';
		try {
			$rows = $dbBal->query(
				"SELECT channel_key
				 FROM drawer_balances
				 WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash'
				 ORDER BY id DESC",
				['drawer_id' => $drawerId, 'user_id' => $drawerUserId]
			);
			if (!empty($rows)) {
				foreach ($rows as $r) {
					$kRaw = trim((string)($r->channel_key ?? ''));
					if ($kRaw === '') continue;
					$k = strtoupper($kRaw);
					$candidate = $k;
					if (strpos($candidate, 'ECASH_') === 0) {
						$candidate = substr($candidate, 6);
					}
					$candidate = $normalizeSlug($candidate);
					$compact = str_replace('_', '', $candidate);
					if ($compact !== '' && $compact === $needCompact) {
						$resolvedExisting = $kRaw;
						break;
					}
				}
			}
		} catch (\Throwable $e) {}
		if ($resolvedExisting !== '') return $resolvedExisting;
		return 'ECASH_' . $needSlug;
	};

	$src = strtolower(trim((string)($row->source ?? '')));
	$senderMode = ($src === 'cash') ? 'cash' : 'ecash';
	$senderKey = '';
	if ($senderMode === 'cash') {
		$senderKey = 'CASH_DRAWER';
	} else {
		if ($src === 'bank') {
			$senderKey = trim((string)($row->account_paid_to ?? ''));
		} else {
			$senderKey = $normalizeEcashKey($src);
		}
	}

	// Assign always follows original receive medium (cash stays cash; bank/ecash stays that channel)
	$transferMode = $senderMode;
	$transferChannel = ($senderMode === 'cash') ? 'cash' : ($src === 'bank' ? 'bank' : $src);
	$transferBank = ($src === 'bank') ? trim((string)($row->account_paid_to ?? '')) : '';
	$transferDenosRaw = ($senderMode === 'cash') ? (string)($row->denos_json ?? '') : '';
	if (trim($senderKey) === '' || ($senderMode !== 'cash' && $senderKey === '0')) {
		message_fail('Invalid cashpickup source for transfer');
		redirect($admin_route.'/'.$plugin_route.'/view/'.$id.'#transfer');
	}

	// remaining = amount involved - paid out plus pickup charge - active transferred amount
	$paidRequested = 0.0;
	try {
		$sumPaid = $db->query(
			"SELECT COALESCE(SUM(COALESCE(amount_paid_out,0) + COALESCE(pickup_charge,0)),0) AS paid_requested
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
			['rid' => (int)$id]
		);
		$paidRequested = !empty($sumPaid) ? (float)($sumPaid[0]->paid_requested ?? 0) : 0.0;
	} catch (\Throwable $e) {}
	$activeTransferred = 0.0;
	try {
		$activeTransferred = function_exists('CashpickupTransferAllocation\\get_total_active_transfer_remaining')
			? \CashpickupTransferAllocation\get_total_active_transfer_remaining($db, (int)$id)
			: 0.0;
	} catch (\Throwable $e) {}

	$remainingBalance = max(0.0, (float)number_format(($amountInvolved - $paidRequested), 2, '.', ''));
	$remainingTransferable = max(0.0, (float)number_format(($remainingBalance - $activeTransferred), 2, '.', ''));
	$isPrivileged = (user_can('is_manager') || user_can('is_admin'));
	$assignmentType = strtolower(trim((string)($req->post('assignment_type') ?? 'full')));
	if (!in_array($assignmentType, ['full', 'partial'], true)) {
		$assignmentType = 'full';
	}
	$sourceUserId = (int)($req->post('source_user_id') ?? 0);
	if ($sourceUserId <= 0 && $remainingTransferable <= 0.00001) {
		message_fail('No unassigned balance remains. Select a source allocation to reassign existing balance.');
		redirect($admin_route.'/'.$plugin_route.'/view/'.$id.'#transfer');
	}
	$sourceTransfer = null;
	$sourceGross = 0.0;
	$sourcePaid = 0.0;
	$sourceAvailable = 0.0;
	if ($sourceUserId > 0) {
		try {
			if (function_exists('CashpickupTransferAllocation\\get_transfer_allocation')) {
				$sourceAllocation = \CashpickupTransferAllocation\get_transfer_allocation($db, (int)$id, (int)$sourceUserId);
				$sourceGross = (float)($sourceAllocation['gross'] ?? 0);
				$sourceAvailable = $sourceGross;
			}
			$sourceTransferRows = $db->query(
				"SELECT id
				 FROM cashpickup_transfers
				 WHERE receive_id = :rid
				 AND to_user_id = :uid
				 AND (
					approval_status IS NULL
					OR approval_status = ''
					OR LOWER(approval_status) IN ('approved','pending')
				 )
				 ORDER BY id DESC
				 LIMIT 1",
				['rid' => (int)$id, 'uid' => $sourceUserId]
			) ?? [];
			if (!empty($sourceTransferRows)) {
				$sourceTransfer = $sourceTransferRows[0];
			}
		} catch (\Throwable $e) {}
		if (!$sourceTransfer) {
			message_fail('Selected source allocation could not be found');
			redirect($admin_route.'/'.$plugin_route.'/view/'.$id.'#transfer');
		}
		if ($sourceAvailable <= 0.00001) {
			message_fail('Selected source allocation has already been exhausted');
			redirect($admin_route.'/'.$plugin_route.'/view/'.$id.'#transfer');
		}
	}
	$transferCharge = (float)number_format((float)($req->post('transfer_charge') ?? 0), 2, '.', '');
	$transferAmountInput = null;
	if ($assignmentType === 'partial') {
		$transferAmountInput = (float)($req->post('transfer_amount') ?? 0);
		if ($transferAmountInput <= 0) {
			message_fail('Please enter the assignment amount');
			redirect($admin_route.'/'.$plugin_route.'/view/'.$id.'#transfer');
		}
		if ($sourceUserId > 0) {
			if ($transferAmountInput > $sourceAvailable + 0.00001) {
				message_fail('Partial reassignment cannot exceed the selected allocation balance');
				redirect($admin_route.'/'.$plugin_route.'/view/'.$id.'#transfer');
			}
		} elseif ($transferAmountInput > $remainingTransferable + 0.00001) {
			message_fail('Partial assignment cannot exceed the remaining transferable balance');
			redirect($admin_route.'/'.$plugin_route.'/view/'.$id.'#transfer');
		}
	}
	if ($sourceUserId > 0 && $sourceUserId === $targetUserId) {
		message_fail('Source and target users must be different');
		redirect($admin_route.'/'.$plugin_route.'/view/'.$id.'#transfer');
	}
	$transferAmount = 0.0;
	if ($sourceUserId > 0) {
		$transferAmount = ($assignmentType === 'partial' && $transferAmountInput !== null)
			? (float)number_format($transferAmountInput, 2, '.', '')
			: $sourceAvailable;
	} else {
		$transferAmount = ($assignmentType === 'partial' && $transferAmountInput !== null)
			? (float)number_format($transferAmountInput, 2, '.', '')
			: $remainingTransferable;
	}
	if ($transferAmount <= 0) {
		message_fail('Invalid assign amount');
		redirect($admin_route.'/'.$plugin_route.'/view/'.$id.'#transfer');
	}
	if ($transferCharge < 0) {
		message_fail('Invalid assignment charge');
		redirect($admin_route.'/'.$plugin_route.'/view/'.$id.'#transfer');
	}
	if ($transferCharge > $transferAmount + 0.00001) {
		message_fail('Assignment charge cannot exceed the assigned amount');
		redirect($admin_route.'/'.$plugin_route.'/view/'.$id.'#transfer');
	}

	$receiverMode = $senderMode;
	$receiverKey = $senderMode === 'cash' ? 'CASH_DRAWER' : $senderKey;

	$senderCashDenos = [];
	$receiverCashDenos = [];
	$decodedTransferDenos = [];
	$needsCashDenos = ($senderMode === 'cash' || $receiverMode === 'cash');
	if ($needsCashDenos) {
		$decoded = json_decode($transferDenosRaw, true);
		if (!is_array($decoded) || empty($decoded)) {
			message_fail('Cash denominations missing for this cashpickup');
			redirect($admin_route.'/'.$plugin_route.'/view/'.$id.'#transfer');
		}
		$sum = 0.0;
		foreach ($decoded as $d) {
			$sum += (float)($d['volume'] ?? 0);
		}
		$sum = (float)number_format($sum, 2, '.', '');
		$expected = (float)number_format($transferAmount, 2, '.', '');
		if ($expected <= 0 || $sum + 0.009 < $expected) {
			message_fail('Cash denominations are insufficient for the assignable amount');
			redirect($admin_route.'/'.$plugin_route.'/view/'.$id.'#transfer');
		}
		$decodedTransferDenos = $decoded;
		$senderCashDenos = $decodedTransferDenos;
		$receiverCashDenos = $decodedTransferDenos;
	}

	$transStatus = (string)($row->transaction_status ?? 'Approved');
	$transId = (int)($row->id ?? $id);

	// Use per-party cash denos (conversion may differ)
	$senderCashDenos = is_array($senderCashDenos) ? $senderCashDenos : [];
	$receiverCashDenos = is_array($receiverCashDenos) ? $receiverCashDenos : [];

	// persist audit fields (latest assignment) and running total
	$persistTransferMode = $transferMode;
	$persistTransferChannel = $transferMode === 'cash' ? 'cash' : $transferChannel;
	$persistTransferBank = $transferMode === 'cash' ? '' : ($transferChannel === 'bank' ? $transferBank : '');
	if (!$db->beginTransaction()) {
		throw new \RuntimeException('Unable to start the reassignment transaction.');
	}
	if ($sourceUserId > 0) {
		$sourceRows = $db->query(
			"SELECT id, amount, transfer_charge
			 FROM cashpickup_transfers
			 WHERE receive_id = :rid
			 AND to_user_id = :uid
			 AND (approval_status IS NULL OR approval_status = '' OR LOWER(approval_status) IN ('approved','pending'))
			 ORDER BY date_created ASC, id ASC
			 FOR UPDATE",
			['rid' => (int)$id, 'uid' => $sourceUserId]
		) ?: [];
		if (empty($sourceRows) || $db->has_error) {
			throw new \RuntimeException('Selected source allocation could not be locked. Refresh and try again.');
		}

		$payoutPaid = function_exists('CashpickupTransferAllocation\\get_actor_payout_total')
			? \CashpickupTransferAllocation\get_actor_payout_total($db, (int)$id, $sourceUserId)
			: 0.0;
		$sourceState = [];
		$calculatedSourceAvailable = 0.0;
		foreach ($sourceRows as $sourceRow) {
			$rowAmount = round(max(0.0, (float)($sourceRow->amount ?? 0)), 2);
			$consumed = min($payoutPaid, $rowAmount);
			$payoutPaid = round(max(0.0, $payoutPaid - $consumed), 2);
			$available = round(max(0.0, $rowAmount - $consumed), 2);
			$calculatedSourceAvailable += $available;
			$sourceState[] = [
				'id' => (int)($sourceRow->id ?? 0),
				'amount' => $rowAmount,
				'charge' => round(max(0.0, (float)($sourceRow->transfer_charge ?? 0)), 2),
				'available' => $available,
			];
		}
		$calculatedSourceAvailable = round($calculatedSourceAvailable, 2);
		if ($transferAmount > $calculatedSourceAvailable + 0.01) {
			throw new \RuntimeException('The source allocation changed before reassignment. Refresh and try again.');
		}

		$amountToReassign = $transferAmount;
		for ($index = count($sourceState) - 1; $index >= 0 && $amountToReassign > 0.00001; $index--) {
			$sourceEntry = $sourceState[$index];
			$take = min((float)$sourceEntry['available'], $amountToReassign);
			if ($take <= 0.00001) {
				continue;
			}
			$oldAmount = (float)$sourceEntry['amount'];
			$newAmount = round(max(0.0, $oldAmount - $take), 2);
			$newCharge = $oldAmount > 0 ? round((float)$sourceEntry['charge'] * ($newAmount / $oldAmount), 2) : 0.0;
			$db->query(
				"UPDATE cashpickup_transfers SET amount = :amount, transfer_charge = :transfer_charge WHERE id = :id",
				['amount' => $newAmount, 'transfer_charge' => $newCharge, 'id' => (int)$sourceEntry['id']]
			);
			if ($db->has_error || $db->affected_rows !== 1) {
				throw new \RuntimeException('Unable to reduce the selected source allocation.');
			}
			$amountToReassign = round(max(0.0, $amountToReassign - $take), 2);
		}
		if ($amountToReassign > 0.01) {
			throw new \RuntimeException('Unable to apply the full reassignment amount.');
		}
	}

	try {
		$hasSourceUserCol = false;
		try {
			$sourceCol = $db->query(
				"SELECT COLUMN_NAME
				 FROM INFORMATION_SCHEMA.COLUMNS
				 WHERE TABLE_SCHEMA = :db
				 AND TABLE_NAME = 'cashpickup_transfers'
				 AND COLUMN_NAME = 'source_user_id'
				 LIMIT 1",
				['db' => DB_NAME]
			);
			$hasSourceUserCol = !empty($sourceCol);
		} catch (\Throwable $e) {}
		$hasTransferChargeCol = false;
		try {
			$chargeCol = $db->query(
				"SELECT COLUMN_NAME
				 FROM INFORMATION_SCHEMA.COLUMNS
				 WHERE TABLE_SCHEMA = :db
				 AND TABLE_NAME = 'cashpickup_transfers'
				 AND COLUMN_NAME = 'transfer_charge'
				 LIMIT 1",
				['db' => DB_NAME]
			);
			$hasTransferChargeCol = !empty($chargeCol);
		} catch (\Throwable $e) {}
		$sourceUserParam = ($sourceUserId > 0) ? $sourceUserId : null;
		if ($hasTransferChargeCol && $hasSourceUserCol) {
			$db->query(
				"INSERT INTO cashpickup_transfers (receive_id, from_user_id, source_user_id, to_user_id, amount, transfer_charge, approval_status, sender_mode, sender_channel_key, receiver_mode, receiver_channel_key, transfer_mode, transfer_channel, transfer_bank, denos_json, created_by, date_created)
				 VALUES (:receive_id, :from_user_id, :source_user_id, :to_user_id, :amount, :transfer_charge, :approval_status, :sender_mode, :sender_channel_key, :receiver_mode, :receiver_channel_key, :transfer_mode, :transfer_channel, :transfer_bank, :denos_json, :created_by, :date_created)",
				[
					'receive_id' => (int)$id,
					'from_user_id' => (int)$actorId,
					'source_user_id' => $sourceUserParam,
					'to_user_id' => (int)$targetUserId,
					'amount' => $transferAmount,
					'transfer_charge' => $transferCharge,
					'approval_status' => 'approved',
					'sender_mode' => $senderMode,
					'sender_channel_key' => $senderKey,
					'receiver_mode' => $receiverMode,
					'receiver_channel_key' => $receiverKey,
					'transfer_mode' => $persistTransferMode,
					'transfer_channel' => $persistTransferChannel,
					'transfer_bank' => $persistTransferBank,
					'denos_json' => $needsCashDenos ? $transferDenosRaw : null,
					'created_by' => (int)$actorId,
					'date_created' => date('Y-m-d H:i:s'),
				]
			);
		} elseif ($hasTransferChargeCol) {
			$db->query(
				"INSERT INTO cashpickup_transfers (receive_id, from_user_id, to_user_id, amount, transfer_charge, approval_status, sender_mode, sender_channel_key, receiver_mode, receiver_channel_key, transfer_mode, transfer_channel, transfer_bank, denos_json, created_by, date_created)
				 VALUES (:receive_id, :from_user_id, :to_user_id, :amount, :transfer_charge, :approval_status, :sender_mode, :sender_channel_key, :receiver_mode, :receiver_channel_key, :transfer_mode, :transfer_channel, :transfer_bank, :denos_json, :created_by, :date_created)",
				[
					'receive_id' => (int)$id,
					'from_user_id' => (int)$actorId,
					'to_user_id' => (int)$targetUserId,
					'amount' => $transferAmount,
					'transfer_charge' => $transferCharge,
					'approval_status' => 'approved',
					'sender_mode' => $senderMode,
					'sender_channel_key' => $senderKey,
					'receiver_mode' => $receiverMode,
					'receiver_channel_key' => $receiverKey,
					'transfer_mode' => $persistTransferMode,
					'transfer_channel' => $persistTransferChannel,
					'transfer_bank' => $persistTransferBank,
					'denos_json' => $needsCashDenos ? $transferDenosRaw : null,
					'created_by' => (int)$actorId,
					'date_created' => date('Y-m-d H:i:s'),
				]
			);
		} elseif ($hasSourceUserCol) {
			$db->query(
				"INSERT INTO cashpickup_transfers (receive_id, from_user_id, source_user_id, to_user_id, amount, approval_status, sender_mode, sender_channel_key, receiver_mode, receiver_channel_key, transfer_mode, transfer_channel, transfer_bank, denos_json, created_by, date_created)
				 VALUES (:receive_id, :from_user_id, :source_user_id, :to_user_id, :amount, :approval_status, :sender_mode, :sender_channel_key, :receiver_mode, :receiver_channel_key, :transfer_mode, :transfer_channel, :transfer_bank, :denos_json, :created_by, :date_created)",
				[
					'receive_id' => (int)$id,
					'from_user_id' => (int)$actorId,
					'source_user_id' => $sourceUserParam,
					'to_user_id' => (int)$targetUserId,
					'amount' => $transferAmount,
					'approval_status' => 'approved',
					'sender_mode' => $senderMode,
					'sender_channel_key' => $senderKey,
					'receiver_mode' => $receiverMode,
					'receiver_channel_key' => $receiverKey,
					'transfer_mode' => $persistTransferMode,
					'transfer_channel' => $persistTransferChannel,
					'transfer_bank' => $persistTransferBank,
					'denos_json' => $needsCashDenos ? $transferDenosRaw : null,
					'created_by' => (int)$actorId,
					'date_created' => date('Y-m-d H:i:s'),
				]
			);
		} else {
			$db->query(
				"INSERT INTO cashpickup_transfers (receive_id, from_user_id, to_user_id, amount, approval_status, sender_mode, sender_channel_key, receiver_mode, receiver_channel_key, transfer_mode, transfer_channel, transfer_bank, denos_json, created_by, date_created)
				 VALUES (:receive_id, :from_user_id, :to_user_id, :amount, :approval_status, :sender_mode, :sender_channel_key, :receiver_mode, :receiver_channel_key, :transfer_mode, :transfer_channel, :transfer_bank, :denos_json, :created_by, :date_created)",
				[
					'receive_id' => (int)$id,
					'from_user_id' => (int)$actorId,
					'to_user_id' => (int)$targetUserId,
					'amount' => $transferAmount,
					'approval_status' => 'approved',
					'sender_mode' => $senderMode,
					'sender_channel_key' => $senderKey,
					'receiver_mode' => $receiverMode,
					'receiver_channel_key' => $receiverKey,
					'transfer_mode' => $persistTransferMode,
					'transfer_channel' => $persistTransferChannel,
					'transfer_bank' => $persistTransferBank,
					'denos_json' => $needsCashDenos ? $transferDenosRaw : null,
					'created_by' => (int)$actorId,
					'date_created' => date('Y-m-d H:i:s'),
				]
			);
		}
	} catch (\Throwable $e) {
		throw $e;
	}
		if ($db->has_error) {
			throw new \RuntimeException('Unable to save the reassignment: ' . $db->error);
		}

		// Keep the active allocation aligned with the current assignee.
		// Replacements overwrite the prior active allocation; regular assignments accumulate on the active balance.
		$newTransferred = ($sourceUserId > 0)
			? (float)number_format($activeTransferred, 2, '.', '')
			: (float)number_format(($activeTransferred + $transferAmount), 2, '.', '');
		$db->query(
			"UPDATE cashpickups
			 SET assigned_user_id = :assigned_user_id,
			     amount_transferred = :amount_transferred,
			     transfer_mode = :transfer_mode,
			     transfer_channel = :transfer_channel,
			     transfer_bank = :transfer_bank,
			     updated_by = :updated_by,
			     date_updated = :date_updated
			 WHERE id = :id",
			[
				'assigned_user_id' => $targetUserId,
				'amount_transferred' => $newTransferred,
				'transfer_mode' => $persistTransferMode,
				'transfer_channel' => $persistTransferChannel,
				'transfer_bank' => $persistTransferBank,
				'updated_by' => $actorId,
				'date_updated' => date('Y-m-d H:i:s'),
				'id' => $id,
			]
		);
		if ($db->has_error || $db->affected_rows !== 1) {
			throw new \RuntimeException('Unable to update the cashpickup assignee.');
		}
		if (!$db->commit()) {
			throw new \RuntimeException('Unable to complete the reassignment.');
		}

		message_success('Cashpickup assigned');
		redirect($admin_route.'/'.$plugin_route.'/view/'.$id);

	}catch(\Throwable $e){
		if (isset($db) && $db instanceof \Core\Database) {
			$db->rollBack();
		}
		message_fail('Transfer failed: ' . $e->getMessage());
		redirect($admin_route.'/'.$plugin_route.'/view/'.$id);
	}

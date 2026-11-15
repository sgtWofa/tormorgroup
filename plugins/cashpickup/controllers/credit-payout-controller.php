<?php

require_once plugin_path('helpers/customer-ledger.php');
$transferHelperPath = realpath(__DIR__ . '/../helpers/transfer-allocation.php');
if ($transferHelperPath && file_exists($transferHelperPath)) {
	require_once $transferHelperPath;
}

$cashpickup = new \Cashpickups\Cashpickup;
$postdata = $req->post();
$customerId = (int)($postdata['customer_id'] ?? URL(3) ?? 0);

if (!(user_can('is_admin') || user_can('is_manager'))) {
	$cashpickup->errors['credit_amount'] = 'Access denied.';
	set_value('errors', $cashpickup->errors);
	return;
}

try {
	$db = new \Core\Database;
	\CashpickupLedger\ensure_cashpickup_customer_schema($db);
	\CashpickupLedger\ensure_cashpickup_columns($db);
	\CashpickupLedger\ensure_cashpickup_credit_schema($db);
	if (function_exists('CashpickupTransferAllocation\\ensure_cashpickup_transfer_schema')) {
		\CashpickupTransferAllocation\ensure_cashpickup_transfer_schema($db);
	}
} catch (\Throwable $e) {
	$cashpickup->errors['credit_amount'] = 'Unable to prepare credit payout records.';
	set_value('errors', $cashpickup->errors);
	return;
}

if ($customerId <= 0) {
	$cashpickup->errors['credit_amount'] = 'Customer not found.';
	set_value('errors', $cashpickup->errors);
	return;
}

$customerRows = $db->query(
	"SELECT * FROM cashpickup_customers WHERE deleted = 0 AND id = :id LIMIT 1",
	['id' => $customerId]
) ?: [];
if (empty($customerRows)) {
	$cashpickup->errors['credit_amount'] = 'Customer not found.';
	set_value('errors', $cashpickup->errors);
	return;
}
$customer = $customerRows[0];

$csrf = csrf_verify($postdata);
if (!$csrf) {
	$cashpickup->errors['credit_amount'] = 'Form expired!';
	set_value('errors', $cashpickup->errors);
	return;
}

$session = get_session();
$userId = (int)($session->id ?? 0);
$assignToUserId = (int)($postdata['assign_to_user_id'] ?? 0);
$creditAmount = (float)($postdata['credit_amount'] ?? 0);
$pickupCharge = (float)($postdata['pickup_charge'] ?? 0);
$notes = trim((string)($postdata['notes'] ?? ''));

if ($creditAmount <= 0) {
	$cashpickup->errors['credit_amount'] = 'Credit amount is required.';
}
if ($pickupCharge < 0) {
	$cashpickup->errors['pickup_charge'] = 'Charge cannot be negative.';
}
if ($assignToUserId <= 0) {
	$cashpickup->errors['assign_to_user_id'] = 'Please select a user to assign.';
}

if (!empty($cashpickup->errors)) {
	set_value('errors', $cashpickup->errors);
	return;
}

$eligibleAssignee = $db->query(
	"SELECT u.id
	 FROM users u
	 JOIN (
		SELECT DISTINCT a.user_id
		FROM attendance a
		WHERE a.deleted = 0
		AND a.date = CURDATE()
		AND UPPER(a.remarks) IN ('PRESENT','LATE')
	 ) present_today ON present_today.user_id = u.id
	 LEFT JOIN (
		SELECT DISTINCT urm.user_id
		FROM user_roles_map urm
		JOIN user_roles ur ON ur.id = urm.role_id
		WHERE urm.disabled = 0
		AND LOWER(ur.role) IN (
			'manager','admin','is_manager','is_admin',
			'general manager','general_manager','generalmanager','gm','is_general_manager'
		)
	 ) blocked_roles ON blocked_roles.user_id = u.id
	 WHERE u.deleted = 0
	 AND u.id = :user_id
	 AND blocked_roles.user_id IS NULL
	 LIMIT 1",
	['user_id' => $assignToUserId]
) ?: [];
if (empty($eligibleAssignee)) {
	$cashpickup->errors['assign_to_user_id'] = 'Selected user is not available for credit payout assignment today.';
	set_value('errors', $cashpickup->errors);
	return;
}

$grossAmount = round($creditAmount + max(0, $pickupCharge), 2);
if ($grossAmount <= 0.00001) {
	$cashpickup->errors['credit_amount'] = 'Credit amount must be greater than zero.';
	set_value('errors', $cashpickup->errors);
	return;
}

$generateTrackingId = static function(\Core\Database $db): string {
	$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
	$makeId = static function() use ($alphabet): string {
		$out = 'CR';
		$max = strlen($alphabet) - 1;
		for ($i = 0; $i < 8; $i++) {
			$out .= $alphabet[rand(0, $max)];
		}
		return $out;
	};

	$tries = 0;
	do {
		$candidate = $makeId();
		$exists = $db->query(
			"SELECT id FROM cashpickups WHERE tracking_id = :tracking_id LIMIT 1",
			['tracking_id' => $candidate]
		) ?: [];
		$tries++;
	} while (!empty($exists) && $tries < 25);

	return $candidate;
};

$now = date('Y-m-d H:i:s');
$creditReceiveId = (int)floor(microtime(true) * 1000000);
$trackingId = $generateTrackingId($db);
$record = [
	'id' => $creditReceiveId,
	'transaction_side' => 'receive',
	'date' => $now,
	'source' => 'credit',
	'account_paid_to' => '',
	'sender_details' => (string)($customer->full_name ?? ''),
	'phone_number' => (string)($customer->phone_number ?? ''),
	'tracking_id' => $trackingId,
	'idcard_photo' => '',
	'customer_photo' => '',
	'receive_proof_photo' => '',
	'pickup_person_idcard' => '',
	'pickup_person_signature' => '',
	'pickup_person_phone' => '',
	'amount_involved' => $grossAmount,
	'payment_method' => '',
	'pickup_charge' => max(0, $pickupCharge),
	'amount_paid_out' => 0,
	'amount_transferred' => 0,
	'receive_id' => null,
	'pay_type' => null,
	'amount_requested' => 0,
	'denos_json' => null,
	'customer_id' => $customerId,
	'customer_id_type' => (string)($customer->id_type ?? ''),
	'customer_id_number' => (string)($customer->id_number ?? ''),
	'customer_location' => (string)($customer->location ?? ''),
	'credit_mode' => 'credit',
	'payout_channel' => null,
	'assigned_user_id' => $assignToUserId,
	'transfer_mode' => null,
	'transfer_channel' => null,
	'transfer_bank' => null,
	'approved_by' => null,
	'approved_at' => null,
	'confirmed_by' => '',
	'notes' => $notes,
	'approval_status' => 'pending',
	'denial_reason' => '',
	'transaction_status' => 'Pending payout',
	'created_by' => $userId,
	'updated_by' => null,
	'disabled' => 0,
	'deleted' => 0,
	'date_created' => $now,
	'date_updated' => null,
	'date_deleted' => null,
];

if (!$cashpickup->validate_insert($record)) {
	set_value('errors', $cashpickup->errors);
	return;
}

$cashpickup->insert($record);
if (!empty($cashpickup->has_error)) {
	$errorText = (string)($cashpickup->error ?? 'Unable to save credit payout.');
	$cashpickup->errors['credit_amount'] = $errorText;
	set_value('errors', $cashpickup->errors);
	return;
}

try {
	$db->query(
		"INSERT INTO cashpickup_transfers
		 (receive_id, from_user_id, source_user_id, to_user_id, amount, transfer_charge, approval_status, transfer_mode, transfer_channel, created_by, date_created)
		 VALUES
		 (:receive_id, :from_user_id, :source_user_id, :to_user_id, :amount, :transfer_charge, :approval_status, :transfer_mode, :transfer_channel, :created_by, :date_created)",
		[
			'receive_id' => $creditReceiveId,
			'from_user_id' => $userId > 0 ? $userId : null,
			'source_user_id' => null,
			'to_user_id' => $assignToUserId,
			'amount' => $grossAmount,
			'transfer_charge' => max(0, $pickupCharge),
			'approval_status' => 'approved',
			'transfer_mode' => 'credit',
			'transfer_channel' => 'credit',
			'created_by' => $userId > 0 ? $userId : null,
			'date_created' => $now,
		]
	);
} catch (\Throwable $e) {
	try {
		$db->query(
			"UPDATE cashpickups
			 SET deleted = 1, date_deleted = :date_deleted, updated_by = :updated_by, date_updated = :date_updated
			 WHERE id = :id",
			[
				'id' => $creditReceiveId,
				'date_deleted' => $now,
				'updated_by' => $userId > 0 ? $userId : null,
				'date_updated' => $now,
			]
		);
	} catch (\Throwable $rollbackError) {
		// ignore rollback failure
	}
	$cashpickup->errors['assign_to_user_id'] = 'Credit payout could not be assigned to the selected user.';
	set_value('errors', $cashpickup->errors);
	return;
}

message_success('Credit payout assigned successfully!');
redirect($admin_route.'/'.$plugin_route.'/customer/'.$customerId);

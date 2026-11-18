<?php

require_once plugin_path('helpers/customer-ledger.php');
require_once plugin_path('helpers/customer-assignment.php');
require_once plugin_path('helpers/permissions.php');

$session = get_session();
$actorId = (int)($session->id ?? 0);
try {
	$db = new \Core\Database;
	$canAssignPayout = \CashpickupPermissions\can_assign_customer_payout($db, $actorId);
} catch (\Throwable $e) {
	error_log('[cashpickup] Customer assignment access check failed: ' . $e->getMessage());
	message_fail('Unable to verify access. Please try again.');
	redirect($admin_route . '/' . $plugin_route . '/customers');
}
if (!$canAssignPayout) {
	message_fail('Access denied.');
	redirect($admin_route . '/' . $plugin_route . '/customers');
}

$customerId = (int)(URL(3) ?? ($req->post('customer_id') ?? 0));
$targetUserId = (int)($req->post('assigned_user_id') ?? 0);
$amount = round((float)($req->post('amount') ?? 0), 2);
$pickupCharge = round((float)($req->post('pickup_charge') ?? 0), 2);

if ($customerId <= 0 || $targetUserId <= 0 || $amount <= 0 || $pickupCharge < 0 || $pickupCharge >= $amount) {
	message_fail('Select a user, enter a valid assignment amount, and keep the payout charge below that amount.');
	redirect($admin_route . '/' . $plugin_route . '/customer/' . $customerId . '#withdraw-section');
}
if (!csrf_verify($req->post())) {
	message_fail('Form expired.');
	redirect($admin_route . '/' . $plugin_route . '/customer/' . $customerId . '#withdraw-section');
}

try {
	\CashpickupLedger\ensure_cashpickup_customer_schema($db);
	\CashpickupLedger\ensure_cashpickup_columns($db);
	\CashpickupLedger\ensure_cashpickup_credit_schema($db);
	\CashpickupCustomerAssignment\ensure_schema($db);
	\CashpickupCustomerAssignment\migrate_legacy_customer_assignments($db, $customerId, $actorId);

	$targetRows = $db->query(
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
			AND LOWER(TRIM(ur.role)) IN (
				'manager','admin','is_manager','is_admin',
				'general manager','general_manager','generalmanager','gm','is_general_manager',
				'operations manager','operations_manager','operationsmanager','ops manager','ops_manager','opsmanager','is_operations_manager'
			)
		 )
		 LIMIT 1",
		['uid' => $targetUserId]
	) ?: [];
	if (empty($targetRows)) {
		message_fail('Selected user is not eligible for assignment. They must be present today and not a manager or admin.');
		redirect($admin_route . '/' . $plugin_route . '/customer/' . $customerId . '#withdraw-section');
	}

	if (!$db->beginTransaction()) {
		throw new \RuntimeException('Unable to start the assignment.');
	}
	$lockedCustomer = $db->query(
		"SELECT id FROM cashpickup_customers WHERE id = :id AND deleted = 0 FOR UPDATE",
		['id' => $customerId]
	) ?: [];
	if (empty($lockedCustomer)) {
		throw new \RuntimeException('Customer not found.');
	}
	$customerRows = $db->query(
		"SELECT * FROM cashpickup_customers WHERE id = :id AND deleted = 0 LIMIT 1",
		['id' => $customerId]
	) ?: [];
	$customer = $customerRows[0] ?? null;
	if (!\CashpickupLedger\customer_is_approved($customer)) {
		throw new \RuntimeException('Customer registration is pending approval.');
	}

	$unassigned = \CashpickupCustomerAssignment\get_unassigned_balance($db, $customerId);
	if ($amount > $unassigned + 0.00001) {
		throw new \RuntimeException('Assignment exceeds the customer’s unassigned balance of GH₵ ' . number_format($unassigned, 2) . '.');
	}
	$db->query(
		"INSERT INTO cashpickup_customer_assignments
		 (customer_id, assigned_user_id, amount, pickup_charge, status, assigned_by, date_created)
		 VALUES (:customer_id, :assigned_user_id, :amount, :pickup_charge, 'active', :assigned_by, :date_created)",
		[
			'customer_id' => $customerId,
			'assigned_user_id' => $targetUserId,
			'amount' => $amount,
			'pickup_charge' => $pickupCharge,
			'assigned_by' => $actorId > 0 ? $actorId : null,
			'date_created' => date('Y-m-d H:i:s'),
		]
	);
	if ($db->has_error || $db->affected_rows !== 1) {
		throw new \RuntimeException('Unable to save the customer payout assignment.');
	}
	if (!$db->commit()) {
		throw new \RuntimeException('Unable to complete the customer payout assignment.');
	}

	message_success('Customer balance assigned for payout.');
	redirect($admin_route . '/' . $plugin_route . '/customer/' . $customerId . '#withdraw-section');
} catch (\Throwable $e) {
	if (isset($db) && $db instanceof \Core\Database) {
		$db->rollBack();
	}
	message_fail('Assignment failed: ' . $e->getMessage());
	redirect($admin_route . '/' . $plugin_route . '/customer/' . $customerId . '#withdraw-section');
}

<?php

require_once plugin_path('helpers/customer-ledger.php');
require_once plugin_path('helpers/permissions.php');

$session = get_session();
$userId = (int)($session->id ?? 0);
try {
	$db = new \Core\Database;
	$canReviewCustomers = \CashpickupPermissions\is_top_authority($db, $userId);
} catch (\Throwable $e) {
	error_log('[cashpickup] Customer approval access check failed: ' . $e->getMessage());
	message_fail('Unable to verify access. Please try again.');
	redirect($admin_route . '/' . $plugin_route . '/customers?tab=new');
}
if (!$canReviewCustomers) {
	message_fail('Access denied');
	redirect($admin_route . '/' . $plugin_route . '/customers?tab=new');
}

$postdata = $req->post();
if (!csrf_verify($postdata)) {
	message_fail('Form expired');
	redirect($admin_route . '/' . $plugin_route . '/customers?tab=new');
}

$customerId = (int)(URL(3) ?? 0);
if ($customerId <= 0) {
	$customerId = (int)($postdata['customer_id'] ?? 0);
}
if ($customerId <= 0) {
	message_fail('Customer not found.');
	redirect($admin_route . '/' . $plugin_route . '/customers?tab=new');
}

try {
	\CashpickupLedger\ensure_cashpickup_customer_schema($db);
	\CashpickupLedger\ensure_cashpickup_customer_approval_columns($db);
	$db->query(
		"UPDATE cashpickup_customers
		 SET approval_status = 'approved',
		     approved_by = :approved_by,
		     approved_at = :approved_at,
		     denial_reason = NULL,
		     updated_by = :updated_by,
		     date_updated = :date_updated
		 WHERE deleted = 0 AND id = :id",
		[
			'id' => $customerId,
			'approved_by' => $userId > 0 ? $userId : null,
			'approved_at' => date('Y-m-d H:i:s'),
			'updated_by' => (string)$userId,
			'date_updated' => date('Y-m-d H:i:s'),
		]
	);
	if ($db->has_error) {
		error_log('[cashpickup] Customer approval update failed for customer ' . $customerId . ': ' . $db->error);
		message_fail('Unable to approve customer registration. Please try again.');
		redirect($admin_route . '/' . $plugin_route . '/customers?tab=new');
	}
	$approvedRows = $db->query(
		"SELECT approval_status
		 FROM cashpickup_customers
		 WHERE deleted = 0 AND id = :id
		 LIMIT 1",
		['id' => $customerId]
	);
	if (empty($approvedRows) || strtolower(trim((string)($approvedRows[0]->approval_status ?? ''))) !== 'approved') {
		message_fail('Customer approval was not saved. Refresh and try again.');
		redirect($admin_route . '/' . $plugin_route . '/customers?tab=new');
	}
	message_success('Customer registration approved.');
} catch (\Throwable $e) {
	error_log('[cashpickup] Customer approval failed for customer ' . $customerId . ': ' . $e->getMessage());
	message_fail('Unable to approve customer registration.');
}

redirect($admin_route . '/' . $plugin_route . '/customers?tab=new');

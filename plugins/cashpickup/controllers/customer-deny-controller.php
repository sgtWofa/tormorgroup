<?php

require_once plugin_path('helpers/customer-ledger.php');
require_once plugin_path('helpers/permissions.php');

$session = get_session();
$userId = (int)($session->id ?? 0);
try {
	$db = new \Core\Database;
	$canReviewCustomers = \CashpickupPermissions\is_top_authority($db, $userId);
} catch (\Throwable $e) {
	error_log('[cashpickup] Customer denial access check failed: ' . $e->getMessage());
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

$reason = trim((string)($postdata['denial_reason'] ?? 'Registration denied'));
if ($reason === '') {
	$reason = 'Registration denied';
}

try {
	\CashpickupLedger\ensure_cashpickup_customer_schema($db);
	\CashpickupLedger\ensure_cashpickup_customer_approval_columns($db);
	$db->query(
		"UPDATE cashpickup_customers
		 SET approval_status = 'denied',
		     approved_by = NULL,
		     approved_at = NULL,
		     denial_reason = :denial_reason,
		     updated_by = :updated_by,
		     date_updated = :date_updated
		 WHERE deleted = 0 AND id = :id",
		[
			'id' => $customerId,
			'denial_reason' => $reason,
			'updated_by' => (string)$userId,
			'date_updated' => date('Y-m-d H:i:s'),
		]
	);
	if ($db->has_error) {
		error_log('[cashpickup] Customer denial update failed for customer ' . $customerId . ': ' . $db->error);
		message_fail('Unable to deny customer registration. Please try again.');
		redirect($admin_route . '/' . $plugin_route . '/customers?tab=new');
	}
	$deniedRows = $db->query(
		"SELECT approval_status
		 FROM cashpickup_customers
		 WHERE deleted = 0 AND id = :id
		 LIMIT 1",
		['id' => $customerId]
	);
	if (empty($deniedRows) || strtolower(trim((string)($deniedRows[0]->approval_status ?? ''))) !== 'denied') {
		message_fail('Customer denial was not saved. Refresh and try again.');
		redirect($admin_route . '/' . $plugin_route . '/customers?tab=new');
	}
	message_success('Customer registration denied.');
} catch (\Throwable $e) {
	error_log('[cashpickup] Customer denial failed for customer ' . $customerId . ': ' . $e->getMessage());
	message_fail('Unable to deny customer registration.');
}

redirect($admin_route . '/' . $plugin_route . '/customers?tab=new');

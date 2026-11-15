<?php

require_once plugin_path('helpers/customer-assignment.php');

if (!user_can('is_admin')) {
	message_fail('Only an admin can cancel customer payout assignments.');
	redirect($admin_route . '/' . $plugin_route . '/customers');
}

$assignmentId = (int)(URL(3) ?? 0);
$customerId = (int)($req->post('customer_id') ?? 0);
$session = get_session();
$actorId = (int)($session->id ?? 0);

if ($assignmentId <= 0 || $customerId <= 0) {
	message_fail('Invalid customer assignment.');
	redirect($admin_route . '/' . $plugin_route . '/customers');
}
if (!csrf_verify($req->post())) {
	message_fail('Form expired.');
	redirect($admin_route . '/' . $plugin_route . '/customer/' . $customerId . '#withdraw-section');
}

try {
	$db = new \Core\Database;
	\CashpickupCustomerAssignment\ensure_schema($db);
	if (!$db->beginTransaction()) {
		throw new \RuntimeException('Unable to start assignment cancellation.');
	}
	$db->query(
		"SELECT id FROM cashpickup_customers WHERE id = :id AND deleted = 0 FOR UPDATE",
		['id' => $customerId]
	);
	$rows = $db->query(
		"SELECT id, customer_id, status
		 FROM cashpickup_customer_assignments
		 WHERE id = :id AND customer_id = :customer_id
		 FOR UPDATE",
		['id' => $assignmentId, 'customer_id' => $customerId]
	) ?: [];
	if (empty($rows)) {
		throw new \RuntimeException('Assignment not found.');
	}
	if (strtolower(trim((string)($rows[0]->status ?? ''))) !== 'active') {
		throw new \RuntimeException('This assignment is already inactive.');
	}
	$remaining = 0.0;
	foreach (\CashpickupCustomerAssignment\get_customer_assignments($db, $customerId) as $assignment) {
		if ((int)($assignment->id ?? 0) === $assignmentId) {
			$remaining = max(0.0, (float)($assignment->remaining_amount ?? 0));
			break;
		}
	}
	if ($remaining <= 0.00001) {
		throw new \RuntimeException('This assignment has already been fully used and cannot be cancelled.');
	}
	$db->query(
		"UPDATE cashpickup_customer_assignments
		 SET status = 'cancelled', cancelled_by = :cancelled_by, date_cancelled = :date_cancelled
		 WHERE id = :id AND status = 'active'",
		[
			'cancelled_by' => $actorId > 0 ? $actorId : null,
			'date_cancelled' => date('Y-m-d H:i:s'),
			'id' => $assignmentId,
		]
	);
	if ($db->has_error || $db->affected_rows !== 1) {
		throw new \RuntimeException('Unable to cancel the customer assignment.');
	}
	if (!$db->commit()) {
		throw new \RuntimeException('Unable to complete assignment cancellation.');
	}
	message_success('Customer payout assignment cancelled.');
	redirect($admin_route . '/' . $plugin_route . '/customer/' . $customerId . '#withdraw-section');
} catch (\Throwable $e) {
	if (isset($db) && $db instanceof \Core\Database) {
		$db->rollBack();
	}
	message_fail('Assignment cancellation failed: ' . $e->getMessage());
	redirect($admin_route . '/' . $plugin_route . '/customer/' . $customerId . '#withdraw-section');
}

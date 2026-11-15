<?php

date_default_timezone_set('Africa/Accra');

$postdata = $req->post();
$csrf = csrf_verify($postdata);

if ($csrf && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$session = get_session();
	$userId = (int)($session->id ?? 0);
	$username = trim((string)(($session->first_name ?? '') . ' ' . ($session->last_name ?? '')));
	$id = URL(3) ?? ($postdata['id'] ?? null);

	if (!backoffice_user_can_approve_reconciliations($session)) {
		message_fail('Only Admin or Operations Manager can approve a submitted reconciliation.');
		redirect($admin_route . '/' . $plugin_route);
		exit;
	}

	if (empty($id)) {
		message_fail('Reconciliation not found.');
		redirect($admin_route . '/' . $plugin_route);
		exit;
	}

	backoffice_ensure_reconciliation_access_schema();

	$db = new \Core\Database;
	$rows = $db->query(
		"SELECT * FROM reconciliations_summary WHERE id = :id LIMIT 1",
		['id' => $id]
	) ?: [];
	if (empty($rows)) {
		$rows = $db->query(
			"SELECT * FROM reconciliations_summary WHERE session_id = :session_id ORDER BY id DESC LIMIT 1",
			['session_id' => (string)$id]
		) ?: [];
	}

	if (empty($rows)) {
		message_fail('Reconciliation not found.');
		redirect($admin_route . '/' . $plugin_route);
		exit;
	}

	$summary = $rows[0];
	$statusLower = strtolower(trim((string)($summary->transaction_status ?? 'Submitted')));
	if (in_array($statusLower, ['rejected', 'declined'], true)) {
		message_fail('Rejected reconciliation cannot be approved.');
		redirect($admin_route . '/' . $plugin_route . '/view_reconciliation/' . ($summary->id ?? $id));
		exit;
	}

	if ($statusLower === 'approved') {
		message_success('Reconciliation is already approved.');
		redirect($admin_route . '/' . $plugin_route . '/view_reconciliation/' . ($summary->id ?? $id));
		exit;
	}

	$now = date('Y-m-d H:i:s');
	$db->query(
		"UPDATE reconciliations_summary
		 SET transaction_status = 'Approved',
		     approved_by = :approved_by,
		     date_approved = :date_approved,
		     updated_by = :updated_by,
		     date_updated = :date_updated
		 WHERE id = :id
		 LIMIT 1",
		[
			'approved_by' => (string)$userId,
			'date_approved' => $now,
			'updated_by' => (string)$userId,
			'date_updated' => $now,
			'id' => $summary->id,
		]
	);

	if (!empty($db->has_error)) {
		message_fail('Failed to approve reconciliation: ' . ($db->error ?? 'Database error'));
		redirect($admin_route . '/' . $plugin_route . '/view_reconciliation/' . ($summary->id ?? $id));
		exit;
	}

	$log = new \BackOffices\Log;
	$log->insert([
		'table_name' => 'reconciliations_summary',
		'action' => 'approved',
		'user_id' => $userId,
		'user_name' => $username,
		'description' => $username . ' approved reconciliation #' . ($summary->id ?? $id) . '.',
		'date_created' => $now,
	]);

	message_success('Reconciliation approved successfully. The user access is now frozen until this reconciliation is rejected for that day.');
	redirect($admin_route . '/' . $plugin_route . '/view_reconciliation/' . ($summary->id ?? $id));
	exit;
}

message_fail('Form expired. Please try again.');
redirect($admin_route . '/' . $plugin_route);

<?php

date_default_timezone_set('Africa/Accra');

$postdata = $req->post();
$csrf = csrf_verify($postdata);

if ($csrf && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$session = get_session();
	$userId = (int)($session->id ?? 0);
	$username = trim((string)(($session->first_name ?? '') . ' ' . ($session->last_name ?? '')));
	$id = URL(3) ?? ($postdata['id'] ?? null);
	$reason = trim((string)($postdata['rejection_reason'] ?? ''));

	if (!backoffice_user_can_reject_reconciliations($session)) {
		message_fail('Only Admin or Operations Manager can reject a reconciliation and allow redo.');
		redirect($admin_route . '/' . $plugin_route);
		exit;
	}

	if (empty($id)) {
		message_fail('Reconciliation not found.');
		redirect($admin_route . '/' . $plugin_route);
		exit;
	}

	if ($reason === '') {
		message_fail('Rejection reason is required before allowing a redo.');
		redirect($admin_route . '/' . $plugin_route . '/view_reconciliation/' . $id);
		exit;
	}

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
		message_fail('This reconciliation has already been rejected.');
		redirect($admin_route . '/' . $plugin_route . '/view_reconciliation/' . ($summary->id ?? $id));
		exit;
	}

	$now = date('Y-m-d H:i:s');
	$db->query(
		"UPDATE reconciliations_summary
		 SET transaction_status = 'Rejected',
		     rejection_reason = :reason,
		     rejected_by = :rejected_by,
		     date_rejected = :date_rejected,
		     updated_by = :updated_by,
		     date_updated = :date_updated
		 WHERE id = :id
		 LIMIT 1",
		[
			'reason' => $reason,
			'rejected_by' => (string)$userId,
			'date_rejected' => $now,
			'updated_by' => (string)$userId,
			'date_updated' => $now,
			'id' => $summary->id,
		]
	);

	if (!empty($db->has_error)) {
		message_fail('Failed to reject reconciliation: ' . ($db->error ?? 'Database error'));
		redirect($admin_route . '/' . $plugin_route . '/view_reconciliation/' . ($summary->id ?? $id));
		exit;
	}

	$log = new \BackOffices\Log;
	$log->insert([
		'table_name' => 'reconciliations_summary',
		'action' => 'rejected',
		'user_id' => $userId,
		'user_name' => $username,
		'description' => $username . ' rejected reconciliation #' . ($summary->id ?? $id) . ' to allow redo. Reason: ' . $reason,
		'date_created' => $now,
	]);

	message_success('Reconciliation rejected successfully. The user can now redo reconciliation for that day.');
	redirect($admin_route . '/' . $plugin_route . '/view_reconciliation/' . ($summary->id ?? $id));
	exit;
}

message_fail('Form expired. Please try again.');
redirect($admin_route . '/' . $plugin_route);

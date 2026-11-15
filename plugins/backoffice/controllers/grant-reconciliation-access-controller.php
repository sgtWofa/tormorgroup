<?php

date_default_timezone_set('Africa/Accra');

$session = get_session();
$adminId = (int)($session->id ?? 0);
$postdata = $req->post();
$csrf = csrf_verify($postdata);

if (!$csrf) {
	message_fail('Form expired. Please try again.');
	redirect($admin_route . '/' . $plugin_route);
}

if (!backoffice_user_is_admin($session)) {
	message_fail('Only admin can grant late reconciliation access.');
	redirect($admin_route . '/' . $plugin_route);
}

$cashdrawerId = (int)($postdata['cashdrawer_id'] ?? 0);
$userId = (int)($postdata['user_id'] ?? 0);
$notes = trim((string)($postdata['notes'] ?? ''));

try {
	backoffice_ensure_late_reconciliation_access_schema();
	$db = new \Core\Database;
	$cashdrawerColumns = backoffice_table_columns($db, 'cashdrawers');
	$summaryColumns = backoffice_table_columns($db, 'reconciliations_summary');
	$cashdrawerDeletedSql = !empty($cashdrawerColumns['deleted']) ? " AND (c.deleted IS NULL OR c.deleted = 0)" : "";
	$summaryDeletedSql = !empty($summaryColumns['deleted']) ? " AND (deleted IS NULL OR deleted = 0)" : "";
	$drawerRows = $db->query(
		"SELECT c.id, c.user_id, c.date_created, u.first_name, u.last_name
		 FROM cashdrawers c
		 LEFT JOIN users u ON u.id = c.user_id
		 WHERE c.id = :cashdrawer_id
		 AND c.user_id = :user_id
		 {$cashdrawerDeletedSql}
		 LIMIT 1",
		['cashdrawer_id' => $cashdrawerId, 'user_id' => $userId]
	) ?: [];
	if (empty($drawerRows)) {
		message_fail('Cashdrawer was not found for the selected user.');
		redirect($admin_route . '/' . $plugin_route);
	}

	$drawer = $drawerRows[0];
	$drawerDate = date('Y-m-d', strtotime((string)($drawer->date_created ?? '')));
	if ($drawerDate === date('Y-m-d')) {
		message_fail('Late reconciliation access is only for past cashdrawers.');
		redirect($admin_route . '/' . $plugin_route);
	}

	$existingRecon = $db->query(
		"SELECT id FROM reconciliations_summary
		 WHERE created_by = :user_id
		 {$summaryDeletedSql}
		 AND DATE(date_created) = :drawer_date
		 AND LOWER(COALESCE(NULLIF(TRIM(transaction_status),''),'submitted')) NOT IN ('rejected','declined')
		 LIMIT 1",
		['user_id' => $userId, 'drawer_date' => $drawerDate]
	) ?: [];
	if (!empty($existingRecon)) {
		message_fail('This cashdrawer already has an active or approved reconciliation.');
		redirect($admin_route . '/' . $plugin_route);
	}

	$now = date('Y-m-d H:i:s');
	$db->query(
		"UPDATE reconciliation_late_access
		 SET status = 'revoked', date_updated = :date_updated
		 WHERE user_id = :user_id
		 AND cashdrawer_id = :cashdrawer_id
		 AND LOWER(COALESCE(status,'')) = 'active'",
		[
			'user_id' => $userId,
			'cashdrawer_id' => $cashdrawerId,
			'date_updated' => $now,
		]
	);
	$db->query(
		"INSERT INTO reconciliation_late_access
		 (user_id, cashdrawer_id, drawer_date, status, notes, granted_by, granted_at, date_created)
		 VALUES (:user_id, :cashdrawer_id, :drawer_date, 'active', :notes, :granted_by, :granted_at, :date_created)",
		[
			'user_id' => $userId,
			'cashdrawer_id' => $cashdrawerId,
			'drawer_date' => $drawerDate,
			'notes' => $notes,
			'granted_by' => $adminId,
			'granted_at' => $now,
			'date_created' => $now,
		]
	);

	$name = trim((string)($drawer->first_name ?? '') . ' ' . (string)($drawer->last_name ?? ''));
	message_success('Late reconciliation access granted to ' . ($name !== '' ? $name : 'the selected user') . ' for ' . $drawerDate . '.');
} catch (\Throwable $e) {
	message_fail('Unable to grant reconciliation access. ' . $e->getMessage());
}

redirect($admin_route . '/' . $plugin_route);

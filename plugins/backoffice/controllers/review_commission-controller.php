<?php

if (empty($row)) {
	message_fail('Record not found');
	return;
}

date_default_timezone_set('Africa/Accra');

$postdata = $req->post();
$postdata['id'] = $row->id;
$csrf = csrf_verify($postdata);

if (!$csrf) {
	set_value('errors', ['review_action' => 'Form expired!']);
	return;
}

$session = get_session();
$user_id = (int)($session->id ?? 0);
$username = trim((string)(($session->first_name ?? '') . ' ' . ($session->last_name ?? '')));
$assignedId = backoffice_commission_assignee_id($row);
$createdById = (int)($row->created_by ?? 0);
$canViewAll = backoffice_commission_can_view_all($session);
$canReviewAnyCommission = backoffice_user_is_admin($session) || backoffice_user_is_operations_manager($session);

if ($user_id <= 0 || (!$canViewAll && $user_id !== $createdById && $user_id !== $assignedId)) {
	message_fail('Access denied.');
	redirect($admin_route . '/' . $plugin_route . '/list');
	exit;
}

$statusLower = strtolower(trim((string)($row->transaction_status ?? '')));
if (!in_array($statusLower, ['pending', 'draft'], true)) {
	message_fail('Only pending commissions can be reviewed.');
	redirect($admin_route . '/' . $plugin_route . '/view_commission/' . (int)$row->id);
	exit;
}

if ($assignedId !== $user_id && !$canReviewAnyCommission) {
	message_fail('Only the assigned reviewer, admin, or operations manager can approve or reject this commission.');
	redirect($admin_route . '/' . $plugin_route . '/view_commission/' . (int)$row->id);
	exit;
}

$reviewAction = strtolower(trim((string)($postdata['review_action'] ?? '')));
if (!in_array($reviewAction, ['approve', 'reject'], true)) {
	set_value('errors', ['review_action' => 'Select a valid review action.']);
	return;
}

$commReviewDb = new \Core\Database;
$commissionColumns = function_exists('backoffice_table_columns')
	? backoffice_table_columns($commReviewDb, 'commissions')
	: [];
$children = function_exists('backoffice_commission_breakdown_rows')
	? backoffice_commission_breakdown_rows($commission, $row, $commissionColumns)
	: [];
if (empty($children)) {
	$children = [$row];
}

$now = date('Y-m-d H:i:s');

if ($reviewAction === 'reject') {
	$reason = trim((string)($postdata['reject_reason'] ?? ''));
	if ($reason === '') {
		set_value('errors', ['reject_reason' => 'Reject reason is required.']);
		set_value('old_post', $postdata);
		return;
	}

	$update = [
		'transaction_status' => 'Rejected',
		'reject_reason' => $reason,
		'updated_by' => $user_id,
		'date_updated' => $now,
	];
	$commission->update((int)$row->id, $update);
	foreach ($children as $child) {
		$childId = (int)($child->id ?? 0);
		if ($childId > 0) {
			$commission->update($childId, $update);
		}
	}
	if (function_exists('momoremit_finalize_pending_commission_review') && !momoremit_finalize_pending_commission_review($row, $user_id, false)) {
		set_value('errors', ['review_action' => 'Commission rejected, but the Momoremit staging record could not be released.']);
		return;
	}

	$log = new \BackOffices\Log;
	$log->insert([
		'table_name' => 'commissions',
		'action' => 'rejected',
		'user_id' => $user_id,
		'user_name' => $username,
		'description' => "$username rejected commission {$row->id}",
		'date_created' => $now,
	]);

	backoffice_whatsapp_notify_creator_on_assignee_update([
		'type' => 'Commission',
		'id' => (int)$row->id,
		'status' => 'Rejected',
		'creator_id' => $createdById,
		'assigned_id' => $assignedId,
		'details' => ['Reason' => $reason],
		'view_url' => ROOT . '/admin/back_office/view_commission/' . (int)$row->id,
	], $user_id);

	message_success('Commission rejected successfully.');
	redirect($admin_route . '/' . $plugin_route . '/view_commission/' . (int)$row->id);
	exit;
}

$creatorDrawer = \BackOfficeCashdrawer\getActiveDrawer($createdById);
if (empty($creatorDrawer)) {
	set_value('errors', ['review_action' => 'The creator must open a cashdrawer before this commission can be approved.']);
	return;
}

foreach ($children as $child) {
	$amountValue = (float)($child->amount ?? 0);
	if ($amountValue <= 0) {
		continue;
	}

	$networkKey = backoffice_commission_credit_channel_key($child);
	if ($networkKey === '') {
		set_value('errors', ['review_action' => 'A commission row is missing its cashdrawer channel mapping.']);
		return;
	}

	try {
		$result = \BackOfficeCashdrawer\syncEcashMovement($createdById, $networkKey, $amountValue, 'Approved', (int)($child->id ?? 0), 'in');
		if ($result === null) {
			set_value('errors', ['review_action' => 'Failed to add one or more commission rows into the creator cashdrawer.']);
			return;
		}
	} catch (\Throwable $e) {
		set_value('errors', ['review_action' => 'Failed to add commission to the creator cashdrawer: ' . $e->getMessage()]);
		return;
	}
}

$approvedUpdate = [
	'transaction_status' => 'Approved',
	'reject_reason' => '',
	'approved_by' => $user_id,
	'approved_at' => $now,
	'updated_by' => $user_id,
	'date_updated' => $now,
];
$commission->update((int)$row->id, $approvedUpdate);
foreach ($children as $child) {
	$childId = (int)($child->id ?? 0);
	if ($childId > 0) {
		$commission->update($childId, $approvedUpdate);
	}
}
	if (function_exists('momoremit_finalize_pending_commission_review') && !momoremit_finalize_pending_commission_review($row, $user_id, true)) {
		set_value('errors', ['review_action' => 'Commission was approved, but the Momoremit staging record could not be finalized.']);
		return;
	}

$log = new \BackOffices\Log;
$log->insert([
	'table_name' => 'commissions',
	'action' => 'approved',
	'user_id' => $user_id,
	'user_name' => $username,
	'description' => "$username approved commission {$row->id}",
	'date_created' => $now,
]);

backoffice_whatsapp_notify_creator_on_assignee_update([
	'type' => 'Commission',
	'id' => (int)$row->id,
	'status' => 'Approved',
	'creator_id' => $createdById,
	'assigned_id' => $assignedId,
	'details' => ['Amount' => 'GHS ' . number_format((float)($row->amount ?? 0), 2)],
	'view_url' => ROOT . '/admin/back_office/view_commission/' . (int)$row->id,
], $user_id);

message_success('Commission approved successfully.');
redirect($admin_route . '/' . $plugin_route . '/view_commission/' . (int)$row->id);
exit;

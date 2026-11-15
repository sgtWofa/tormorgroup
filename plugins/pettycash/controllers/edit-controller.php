<?php

$id = (int)(URL(3) ?? 0);
$row = pettycash_find_row($id);
$postdata = $req->post();
$csrf = csrf_verify($postdata);
$pettycash = new \PettyCashes\PettyCash;

if (!$row) {
	message_fail('Petty cash transaction not found.');
	redirect($admin_route . '/' . $plugin_route);
	exit;
}

if (!$csrf) {
	$pettycash->errors['csrf'] = 'Form expired. Please try again.';
	set_value('errors', $pettycash->errors);
	return;
}

if (!pettycash_user_is_privileged(get_session()) || !user_can('edit_pettycash')) {
	message_fail('Access denied.');
	redirect($admin_route . '/' . $plugin_route);
	exit;
}

if (strtolower((string)($row->status ?? 'pending_ack')) !== 'pending_ack') {
	message_fail('Only pending petty cash transactions can be edited.');
	redirect($admin_route . '/' . $plugin_route . '/view/' . $row->id);
	exit;
}

$postdata['currency'] = strtoupper(trim((string)($postdata['currency'] ?? $row->currency ?? 'GHS')));
$postdata['category'] = pettycash_normalize_category($postdata['category'] ?? ($row->category ?? ''));
$items = pettycash_normalize_items($postdata['items_json'] ?? ($row->items_json ?? '[]'), $postdata['currency']);
$postdata['items_json'] = json_encode($items, JSON_UNESCAPED_SLASHES);
$postdata['budget_amount'] = pettycash_calculate_items_total($items, $postdata['currency']);
$postdata['assigned_user_id'] = (int)($postdata['assigned_user_id'] ?? $row->assigned_user_id ?? 0);

$session = get_session();
$editorId = (int)($session->id ?? 0);
if ($postdata['assigned_user_id'] > 0 && $postdata['assigned_user_id'] === $editorId) {
	$pettycash->errors['assigned_user_id'] = 'Assign the transaction to another user.';
}

if ($pettycash->validate_update($postdata) && empty($pettycash->errors)) {
	$update = [
		'title' => trim((string)($postdata['title'] ?? '')),
		'description' => trim((string)($postdata['description'] ?? '')),
		'currency' => $postdata['currency'],
		'category' => $postdata['category'],
		'budget_amount' => $postdata['budget_amount'],
		'items_json' => $postdata['items_json'],
		'assigned_user_id' => $postdata['assigned_user_id'],
		'updated_by' => $editorId > 0 ? $editorId : null,
		'date_updated' => date('Y-m-d H:i:s'),
	];

	$pettycash->update($row->id, $update);
	if (empty($pettycash->has_error) && empty($pettycash->error)) {
		message_success('Petty cash transaction updated successfully.');
		redirect($admin_route . '/' . $plugin_route . '/view/' . $row->id);
		exit;
	}

	$pettycash->errors['general'] = 'Failed to update petty cash transaction.';
}

set_value('errors', $pettycash->errors);

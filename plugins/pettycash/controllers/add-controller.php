<?php

$postdata = $req->post();
$csrf = csrf_verify($postdata);
$pettycash = new \PettyCashes\PettyCash;

if (!$csrf) {
	$pettycash->errors['csrf'] = 'Form expired. Please try again.';
	set_value('errors', $pettycash->errors);
	return;
}

if (!pettycash_user_is_privileged(get_session()) || !user_can('add_pettycash')) {
	message_fail('Access denied.');
	redirect($admin_route . '/' . $plugin_route);
	exit;
}

$postdata['currency'] = strtoupper(trim((string)($postdata['currency'] ?? 'GHS')));
$postdata['category'] = pettycash_normalize_category($postdata['category'] ?? '');
$items = pettycash_normalize_items($postdata['items_json'] ?? '[]', $postdata['currency']);
$postdata['items_json'] = json_encode($items, JSON_UNESCAPED_SLASHES);
$postdata['budget_amount'] = pettycash_calculate_items_total($items, $postdata['currency']);
$postdata['assigned_user_id'] = (int)($postdata['assigned_user_id'] ?? 0);

$session = get_session();
$creatorId = (int)($session->id ?? 0);
if ($postdata['assigned_user_id'] > 0 && $postdata['assigned_user_id'] === $creatorId) {
	$pettycash->errors['assigned_user_id'] = 'Assign the transaction to another user.';
}

if ($pettycash->validate_insert($postdata) && empty($pettycash->errors)) {
	$postdata['reference'] = pettycash_make_reference();
	$postdata['status'] = 'pending_ack';
	$postdata['paid_denos_json'] = '[]';
	$postdata['change_denos_json'] = '[]';
	$postdata['created_by'] = $creatorId > 0 ? $creatorId : null;
	$postdata['date_created'] = date('Y-m-d H:i:s');

	$pettycash->insert($postdata);
	if (empty($pettycash->has_error) && empty($pettycash->error)) {
		message_success('Petty cash transaction created successfully.');
		redirect($admin_route . '/' . $plugin_route);
		exit;
	}

	$pettycash->errors['general'] = 'Failed to create petty cash transaction.';
}

set_value('errors', $pettycash->errors);

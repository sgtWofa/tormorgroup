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

if (!pettycash_user_is_privileged(get_session()) || !user_can('delete_pettycash')) {
	message_fail('Access denied.');
	redirect($admin_route . '/' . $plugin_route);
	exit;
}

if (!$csrf) {
	message_fail('Form expired. Please try again.');
	redirect($admin_route . '/' . $plugin_route . '/delete/' . $row->id);
	exit;
}

if (strtolower((string)($row->status ?? 'pending_ack')) !== 'pending_ack') {
	message_fail('Only pending petty cash transactions can be deleted.');
	redirect($admin_route . '/' . $plugin_route . '/view/' . $row->id);
	exit;
}

$session = get_session();
$pettycash->update($row->id, [
	'deleted' => 1,
	'updated_by' => (int)($session->id ?? 0),
	'date_updated' => date('Y-m-d H:i:s'),
	'date_deleted' => date('Y-m-d H:i:s'),
]);

message_success('Petty cash transaction deleted successfully.');
redirect($admin_route . '/' . $plugin_route);
exit;

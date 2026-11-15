<?php

date_default_timezone_set('Africa/Accra');

$cashdrawer = new \MyWallets\Cashdrawer;
$row = !empty($row) && is_object($row) ? $row : operations_account_get_cashdrawer_row((int)$id);

if (empty($row))
{
	message_fail('Cashdrawer not found');
	redirect($admin_route.'/'.$plugin_route);
}

if (!user_can('delete_wallet'))
{
	message_fail('Access denied');
	redirect($admin_route.'/'.$plugin_route.'/view_cashdrawer/'.$row->id);
}

$postdata = $req->post();
$csrf = csrf_verify($postdata);
if (!$csrf) {
	return;
}

try {
	$selectedLinked = $postdata['delete_linked'] ?? [];
	if (!is_array($selectedLinked)) {
		$selectedLinked = [];
	}
	$selectedLinked = array_values(array_filter(array_map('strval', $selectedLinked), 'strlen'));

	$result = operations_account_delete_cashdrawer_and_links($row, $selectedLinked);

	$deletedLinked = (int)($result['deleted_linked'] ?? 0);
	$detachedLinked = (int)($result['detached_linked'] ?? 0);
	$deletedRows = (int)($result['deleted_drawer_rows'] ?? 0);

	$message = 'Cashdrawer deleted successfully.';
	if ($deletedLinked > 0 || $detachedLinked > 0) {
		$message .= ' Linked records deleted: ' . $deletedLinked . '. Detached: ' . $detachedLinked . '.';
	}
	if ($deletedRows > 0) {
		$message .= ' Drawer rows removed: ' . $deletedRows . '.';
	}

	message_success($message);
	redirect($admin_route.'/'.$plugin_route);
} catch (\Throwable $e) {
	message_fail($e->getMessage());
}

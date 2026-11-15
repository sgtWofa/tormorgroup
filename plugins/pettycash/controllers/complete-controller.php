<?php

$id = (int)(URL(3) ?? 0);
$row = pettycash_find_row($id);
$postdata = $req->post();
$csrf = csrf_verify($postdata);

if (!$row) {
	message_fail('Petty cash transaction not found.');
	redirect($admin_route . '/' . $plugin_route);
	exit;
}

$session = get_session();
$userId = (int)($session->id ?? 0);

if (!$csrf) {
	message_fail('Form expired. Please try again.');
	redirect($admin_route . '/' . $plugin_route . '/view/' . $row->id);
	exit;
}

if (!pettycash_user_can_access_row($row, $userId, $session) || pettycash_user_is_privileged($session) || !user_can('complete_pettycash')) {
	message_fail('Access denied.');
	redirect($admin_route . '/' . $plugin_route . '/view/' . $row->id);
	exit;
}

if (strtolower((string)($row->status ?? 'pending_ack')) !== 'acknowledged') {
	message_fail('Only acknowledged petty cash transactions can be completed.');
	redirect($admin_route . '/' . $plugin_route . '/view/' . $row->id);
	exit;
}

$currency = strtoupper(trim((string)($row->currency ?? 'GHS')));
if (!in_array($currency, ['GHS', 'CFA'], true)) {
	$currency = 'GHS';
}

$budgetAmount = (float)($row->budget_amount ?? 0);
$spentAmount = (float)($postdata['spent_amount'] ?? 0);
$action = strtolower(trim((string)($postdata['completion_action'] ?? 'approve')));
$note = trim((string)($postdata['completion_note'] ?? ''));

if ($spentAmount <= 0) {
	message_fail('Enter the amount spent before completing this petty cash transaction.');
	redirect($admin_route . '/' . $plugin_route . '/view/' . $row->id);
	exit;
}

$uploadReq = new \Core\Request;
$uploadReq->upload_folder = 'uploads/pettycash';
$uploadReq->upload_max_size = 1;

if (empty($_FILES['voucher_photo']['name'])) {
	message_fail('Upload the payment voucher before completing this petty cash transaction.');
	redirect($admin_route . '/' . $plugin_route . '/view/' . $row->id);
	exit;
}

$voucherPhoto = $uploadReq->upload_files('voucher_photo');
if (empty($voucherPhoto)) {
	message_fail('Failed to upload the payment voucher.');
	redirect($admin_route . '/' . $plugin_route . '/view/' . $row->id);
	exit;
}

$pettycash = new \PettyCashes\PettyCash;
$now = date('Y-m-d H:i:s');

if ($spentAmount > $budgetAmount || $action === 'reject') {
	if ($spentAmount <= $budgetAmount) {
		message_fail('Reject this transaction only when the amount spent exceeds the petty cash budget.');
		redirect($admin_route . '/' . $plugin_route . '/view/' . $row->id);
		exit;
	}

	if ($note === '') {
		$note = 'Amount spent exceeded the petty cash budget.';
	}

	$pettycash->update($row->id, [
		'status' => 'rejected',
		'spent_amount' => $spentAmount,
		'change_amount' => 0,
		'change_denos_json' => '[]',
		'voucher_photo' => $voucherPhoto,
		'completion_note' => $note,
		'completed_by' => $userId,
		'completed_at' => $now,
		'updated_by' => $userId,
		'date_updated' => $now,
	]);

	message_success('Petty cash transaction rejected and closed.');
	redirect($admin_route . '/' . $plugin_route . '/view/' . $row->id);
	exit;
}

$changeAmount = max(0, round($budgetAmount - $spentAmount, 2));
$changeDenosPayload = [];
$changeDrawer = null;
if ($changeAmount > 0) {
	$rawChangeDenos = $postdata['change_denos_json'] ?? '[]';
	$changeDenosPayload = \BackOfficeCashdrawer\normalizeDenominations($rawChangeDenos, $changeAmount, $currency);
	$changeTotal = \BackOfficeCashdrawer\calculateTotal($changeDenosPayload);
	if (empty($changeDenosPayload)) {
		message_fail('Enter the returned change denominations before completing this petty cash transaction.');
		redirect($admin_route . '/' . $plugin_route . '/view/' . $row->id);
		exit;
	}

	if (abs($changeTotal - $changeAmount) > 0.01) {
		message_fail('Returned change denominations must exactly match the remaining petty cash amount.');
		redirect($admin_route . '/' . $plugin_route . '/view/' . $row->id);
		exit;
	}

	$changeDrawer = \BackOfficeCashdrawer\syncCashMovementCurrency($userId, $changeDenosPayload, $changeAmount, 'Completed', (int)$row->id, 'in', $currency);
	if ($changeDrawer === null) {
		message_fail('Unable to return the petty cash change back into your cashdrawer.');
		redirect($admin_route . '/' . $plugin_route . '/view/' . $row->id);
		exit;
	}
}

$pettycash->update($row->id, [
	'status' => 'completed',
	'spent_amount' => $spentAmount,
	'change_amount' => $changeAmount,
	'change_denos_json' => json_encode($changeDenosPayload, JSON_UNESCAPED_SLASHES),
	'voucher_photo' => $voucherPhoto,
	'completion_note' => $note,
	'change_cashdrawer_id' => $changeDrawer['drawer_id'] ?? null,
	'change_drawer_user_id' => $changeDrawer['drawer_user_id'] ?? null,
	'completed_by' => $userId,
	'completed_at' => $now,
	'updated_by' => $userId,
	'date_updated' => $now,
]);

message_success('Petty cash transaction completed successfully.');
redirect($admin_route . '/' . $plugin_route . '/view/' . $row->id);
exit;

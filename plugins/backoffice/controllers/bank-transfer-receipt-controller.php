<?php

date_default_timezone_set('Africa/Accra');

$postdata = $req->post();
$csrf = csrf_verify($postdata);
$bankTransfer = new \BackOffices\BankTransfer;
$row = $bankTransfer->first(['id' => $id]);

if (empty($row)) {
	message_fail('Bank transfer not found.');
	redirect($admin_route . '/' . $plugin_route);
	exit;
}

$session = get_session();
$userId = (int)($session->id ?? 0);

if (!$csrf) {
	set_value('old_post', $postdata);
	set_value('errors', ['creator_bank_receipt_photo' => 'Form expired!']);
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

if ((int)($row->created_by ?? 0) !== $userId) {
	message_fail('Only the creator can upload the bank receipt.');
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

if (strtolower(trim((string)($row->transaction_status ?? ''))) !== 'pending creator receipt') {
	message_fail('This transaction is not waiting for creator receipt upload.');
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

$thirdPartyId = (int)($postdata['third_party_user_id'] ?? 0);
$validThirdPartyIds = array_map(function($user){
	return (int)($user->id ?? 0);
}, backoffice_bank_transfer_third_party_candidates());
$errors = [];
if ($thirdPartyId <= 0) {
	$errors['third_party_user_id'] = 'Assign the transaction to a 3rd party.';
} elseif (!in_array($thirdPartyId, $validThirdPartyIds, true)) {
	$errors['third_party_user_id'] = 'Selected 3rd party must be an admin or general manager with today\'s cashdrawer.';
}

$req->upload_folder = 'uploads/backoffice/bank-transfers';
$maxFileSize = 1 * 1024 * 1024;
if (empty($_FILES['creator_bank_receipt_photo']['name'])) {
	$errors['creator_bank_receipt_photo'] = 'Bank receipt photo is required.';
} elseif (!empty($_FILES['creator_bank_receipt_photo']['size']) && (int)$_FILES['creator_bank_receipt_photo']['size'] > $maxFileSize) {
	$errors['creator_bank_receipt_photo'] = 'Bank receipt photo must not exceed 1MB.';
}

if (!empty($errors)) {
	set_value('old_post', $postdata);
	set_value('errors', $errors);
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

$uploaded = $req->upload_files('creator_bank_receipt_photo');
if (empty($uploaded)) {
	set_value('old_post', $postdata);
	set_value('errors', ['creator_bank_receipt_photo' => 'Failed to upload bank receipt photo.']);
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

$snapshot = backoffice_bank_transfer_assignment_snapshot(
	$thirdPartyId,
	(string)($row->transfer_category ?? ''),
	(string)($row->bank_name ?? ''),
	(string)($row->network_name ?? '')
);

$bankTransfer->update($id, [
	'third_party_user_id' => $thirdPartyId,
	'creator_bank_receipt_photo' => $uploaded,
	'creator_bank_receipt_uploaded_at' => date('Y-m-d H:i:s'),
	'creator_receipt_status' => 'Submitted',
	'assignment_snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_SLASHES),
	'transaction_status' => 'Pending 3rd Party',
	'updated_by' => $userId,
	'date_updated' => date('Y-m-d H:i:s'),
]);

set_value('errors', []);
message_success('Bank receipt uploaded and sent to the 3rd party for review.');
redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
exit;

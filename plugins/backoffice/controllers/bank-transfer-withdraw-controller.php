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
	set_value('errors', ['creator_bank_receipt_photo' => 'Form expired. Please try again.']);
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

if (!backoffice_bank_transfer_is_from_bank_flow((string)($row->transaction_type ?? ''))) {
	message_fail('This action only applies to From Bank or To Vault transactions.');
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

if ((int)($row->withdrawing_user_id ?? 0) !== $userId) {
	message_fail('Only the assigned withdrawing user can confirm this withdrawal.');
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

if (strtolower(trim((string)($row->transaction_status ?? ''))) !== 'pending withdrawal') {
	message_fail('This transaction is no longer waiting for withdrawal confirmation.');
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

$req->upload_folder = 'uploads/backoffice/bank-transfers';
$maxFileSize = 1 * 1024 * 1024;
if (empty($_FILES['creator_bank_receipt_photo']['name'])) {
	set_value('old_post', $postdata);
	set_value('errors', ['creator_bank_receipt_photo' => 'Bank receipt photo is required.']);
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}
if (!empty($_FILES['creator_bank_receipt_photo']['size']) && (int)$_FILES['creator_bank_receipt_photo']['size'] > $maxFileSize) {
	set_value('old_post', $postdata);
	set_value('errors', ['creator_bank_receipt_photo' => 'Bank receipt photo must not exceed 1MB.']);
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

$uploadedReceipt = $req->upload_files('creator_bank_receipt_photo');
if (empty($uploadedReceipt)) {
	set_value('old_post', $postdata);
	set_value('errors', ['creator_bank_receipt_photo' => 'Failed to upload bank receipt photo.']);
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

$bankTransfer->update($id, [
	'withdrawal_status' => 'Withdrawn',
	'withdrawn_by' => $userId,
	'withdrawn_at' => date('Y-m-d H:i:s'),
	'creator_bank_receipt_photo' => $uploadedReceipt,
	'creator_bank_receipt_uploaded_at' => date('Y-m-d H:i:s'),
	'creator_receipt_status' => 'Submitted',
	'second_party_status' => 'Pending',
	'transaction_status' => 'Pending 2nd Party',
	'updated_by' => $userId,
	'date_updated' => date('Y-m-d H:i:s'),
]);

message_success('Bank receipt uploaded. It is now waiting for 2nd-party final review.');
redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
exit;

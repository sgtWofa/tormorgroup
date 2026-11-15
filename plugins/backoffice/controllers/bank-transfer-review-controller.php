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
	set_value('errors', ['reject_reason' => 'Form expired!']);
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

if ((int)($row->third_party_user_id ?? 0) !== $userId || !backoffice_user_can_bank_transfer_third_party($session)) {
	message_fail('Only the assigned admin or general manager can review this transaction.');
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

$statusLower = strtolower(trim((string)($row->transaction_status ?? '')));
if (!in_array($statusLower, ['pending 3rd party', 'pending creator receipt'], true)) {
	message_fail('This transaction is not waiting for final 3rd-party review.');
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

$reviewAction = strtolower(trim((string)($postdata['review_action'] ?? ($_POST['review_action'] ?? ''))));
if (!in_array($reviewAction, ['approve', 'reject'], true)) {
	message_fail('Invalid review action.');
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

$errors = [];
$existingReceiptPhoto = trim((string)($row->creator_bank_receipt_photo ?? ''));
$hasExistingReceiptPhoto = $existingReceiptPhoto !== '';
$req->upload_folder = 'uploads/backoffice/bank-transfers';
$maxFileSize = 1 * 1024 * 1024;
$hasNewReceiptPhoto = !empty($_FILES['creator_bank_receipt_photo']['name']);

if (!$hasExistingReceiptPhoto && !$hasNewReceiptPhoto) {
	$errors['creator_bank_receipt_photo'] = 'Receipt photo is required before final review.';
} elseif ($hasNewReceiptPhoto && !empty($_FILES['creator_bank_receipt_photo']['size']) && (int)$_FILES['creator_bank_receipt_photo']['size'] > $maxFileSize) {
	$errors['creator_bank_receipt_photo'] = 'Receipt photo must not exceed 1MB.';
}

if ($reviewAction === 'reject') {
	$reason = trim((string)($postdata['reject_reason'] ?? ''));
	if ($reason === '') {
		$errors['reject_reason'] = 'Reject reason is required.';
	}
}

if (!empty($errors)) {
	set_value('old_post', $postdata);
	set_value('errors', $errors);
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

$receiptUpdatePayload = [
	'creator_receipt_status' => 'Submitted',
];
if ($hasNewReceiptPhoto) {
	$uploadedReceiptPhoto = $req->upload_files('creator_bank_receipt_photo');
	if (empty($uploadedReceiptPhoto)) {
		set_value('old_post', $postdata);
		set_value('errors', ['creator_bank_receipt_photo' => 'Failed to upload receipt photo.']);
		redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
		exit;
	}
	$receiptUpdatePayload['creator_bank_receipt_photo'] = $uploadedReceiptPhoto;
	$receiptUpdatePayload['creator_bank_receipt_uploaded_at'] = date('Y-m-d H:i:s');
}

if ($reviewAction === 'reject') {
	$reason = trim((string)($postdata['reject_reason'] ?? ''));
	$bankTransfer->update($id, [
		'creator_receipt_status' => $receiptUpdatePayload['creator_receipt_status'],
		'creator_bank_receipt_photo' => $receiptUpdatePayload['creator_bank_receipt_photo'] ?? ($row->creator_bank_receipt_photo ?? ''),
		'creator_bank_receipt_uploaded_at' => $receiptUpdatePayload['creator_bank_receipt_uploaded_at'] ?? ($row->creator_bank_receipt_uploaded_at ?? null),
		'third_party_status' => 'Rejected',
		'third_party_reviewed_by' => $userId,
		'third_party_reviewed_at' => date('Y-m-d H:i:s'),
		'third_party_reject_reason' => $reason,
		'transaction_status' => 'Rejected',
		'updated_by' => $userId,
		'date_updated' => date('Y-m-d H:i:s'),
	]);
	backoffice_whatsapp_notify_creator_on_assignee_update([
		'type' => 'Bank Transfer',
		'id' => (int)$id,
		'status' => 'Rejected',
		'creator_id' => (int)($row->created_by ?? 0),
		'assigned_id' => $userId,
		'details' => [
			'Transaction type' => $row->transaction_type ?? '',
			'Amount' => 'GHS ' . number_format((float)($row->amount ?? 0), 2),
			'Reason' => $reason,
		],
		'view_url' => ROOT . '/admin/back_office/view_bank_transfer/' . (int)$id,
	], $userId);

	set_value('errors', []);
	message_success('Bank transfer rejected.');
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

$bankTransfer->update($id, [
	'creator_receipt_status' => $receiptUpdatePayload['creator_receipt_status'],
	'creator_bank_receipt_photo' => $receiptUpdatePayload['creator_bank_receipt_photo'] ?? ($row->creator_bank_receipt_photo ?? ''),
	'creator_bank_receipt_uploaded_at' => $receiptUpdatePayload['creator_bank_receipt_uploaded_at'] ?? ($row->creator_bank_receipt_uploaded_at ?? null),
	'third_party_status' => 'Approved',
	'third_party_reviewed_by' => $userId,
	'third_party_reviewed_at' => date('Y-m-d H:i:s'),
	'approved_by' => $userId,
	'approved_at' => date('Y-m-d H:i:s'),
	'transaction_status' => 'Approved',
	'updated_by' => $userId,
	'date_updated' => date('Y-m-d H:i:s'),
]);

backoffice_whatsapp_notify_creator_on_assignee_update([
	'type' => 'Bank Transfer',
	'id' => (int)$id,
	'status' => 'Approved',
	'creator_id' => (int)($row->created_by ?? 0),
	'assigned_id' => $userId,
	'details' => [
		'Transaction type' => $row->transaction_type ?? '',
		'Amount' => 'GHS ' . number_format((float)($row->amount ?? 0), 2),
	],
	'view_url' => ROOT . '/admin/back_office/view_bank_transfer/' . (int)$id,
], $userId);

$transactionType = strtolower(trim((string)($row->transaction_type ?? '')));
$isToBankFlow = backoffice_bank_transfer_is_to_bank_flow($transactionType);
$transferCategory = strtolower(trim((string)($row->transfer_category ?? '')));
$amountValue = (float)($row->amount ?? 0);
$thirdPartyId = (int)($row->third_party_user_id ?? 0);
if ($isToBankFlow && $thirdPartyId > 0 && $amountValue > 0) {
	try {
		$channelKey = '';
		if ($transferCategory === 'e-cash') {
			$networkName = strtoupper(trim((string)($row->network_name ?? '')));
			if ($networkName === 'GMONEY') {
				$networkName = 'G-MONEY';
			}
			$channelKey = $networkName;
		} elseif ($transferCategory === 'bank float') {
			$channelKey = strtoupper(trim((string)($row->bank_name ?? '')));
		}

		if ($channelKey !== '') {
			\BackOfficeCashdrawer\syncEcashMovement($thirdPartyId, $channelKey, $amountValue, 'Approved', (int)$id, 'in');
		}
	} catch (\Throwable $e) {
		error_log('[backoffice bank transfer] balance sync failed for #' . $id . ': ' . $e->getMessage());
	}
}

set_value('errors', []);
message_success('Bank transfer approved.');
redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
exit;

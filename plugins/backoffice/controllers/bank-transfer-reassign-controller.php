<?php

date_default_timezone_set('Africa/Accra');

$routeConfig = get_value();
$admin_route = (string)($routeConfig['admin_route'] ?? 'admin');
$plugin_route = (string)($routeConfig['plugin_route'] ?? 'back_office');

$postdata = $req->post();
$csrf = csrf_verify($postdata);
$bankTransfer = new \BackOffices\BankTransfer;
$row = $bankTransfer->first(['id' => $id]);

if (empty($row)) {
	set_value('errors', ['reassign' => 'Bank transfer not found.']);
	message_fail('Bank transfer not found.');
	redirect($admin_route . '/' . $plugin_route);
	exit;
}

$session = get_session();
$userId = (int)($session->id ?? 0);
$transactionType = strtolower(trim((string)($row->transaction_type ?? '')));
$statusLower = strtolower(trim((string)($row->transaction_status ?? '')));
$thirdPartyStatusLower = strtolower(trim((string)($row->third_party_status ?? '')));
$isCreator = (int)($row->created_by ?? 0) === $userId;
$canReassign = (backoffice_user_is_admin($session) || $isCreator)
	&& $statusLower !== 'approved'
	&& $thirdPartyStatusLower !== 'approved';

if (!$csrf) {
	set_value('old_post', $postdata);
	set_value('errors', ['reassign' => 'Form expired. Please try again.']);
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

if (!$canReassign) {
	set_value('old_post', $postdata);
	set_value('errors', ['reassign' => 'Only an admin or the transaction creator can reassign parties before final approval.']);
	message_fail('Only an admin or the transaction creator can reassign parties before final approval.');
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

$secondPartyId = (int)($postdata['second_party_user_id'] ?? 0);
$thirdPartyId = (int)($postdata['third_party_user_id'] ?? 0);
$withdrawingUserId = (int)($postdata['withdrawing_user_id'] ?? 0);
$errors = [];

if ($secondPartyId <= 0) {
	$errors['second_party_user_id'] = 'Assign the transaction to a 2nd party.';
} elseif ($secondPartyId === (int)($row->created_by ?? 0)) {
	$errors['second_party_user_id'] = '2nd party must be different from the creator.';
} elseif ($secondPartyId !== (int)($row->second_party_user_id ?? 0) && !\BackOfficeCashdrawer\userHasCashdrawerToday($secondPartyId)) {
	$errors['second_party_user_id'] = 'New 2nd party must have created a cashdrawer today.';
}

$vaultBranchId = 0;
if (in_array($transactionType, ['from vault', 'to vault'], true)) {
	$vaultId = $transactionType === 'from vault'
		? (int)($row->source_vault_id ?? 0)
		: (int)($row->destination_vault_id ?? 0);
	$vault = function_exists('Vaults\\getVault') ? \Vaults\getVault($vaultId, $userId) : null;
	$vaultBranchId = (int)($vault->branch_id ?? 0);
	if ($vaultBranchId <= 0) {
		$errors['reassign'] = 'The transaction vault is unavailable, so its branch assignments cannot be verified.';
	} else {
		$branchPartyIds = $transactionType === 'from vault'
			? ['second_party_user_id' => $secondPartyId, 'third_party_user_id' => $thirdPartyId]
			: ['second_party_user_id' => $secondPartyId, 'withdrawing_user_id' => $withdrawingUserId];
		$dbParties = new \Core\Database;
		foreach ($branchPartyIds as $field => $partyId) {
			if ($partyId <= 0) {
				continue;
			}
			$partyRows = $dbParties->query(
				'SELECT branch_id FROM users WHERE id = :id AND (disabled IS NULL OR disabled = 0) LIMIT 1',
				['id' => $partyId]
			) ?: [];
			if ($dbParties->has_error || empty($partyRows) || (int)($partyRows[0]->branch_id ?? 0) !== $vaultBranchId) {
				$errors[$field] = 'All assigned parties must belong to the selected vault branch.';
			}
		}
	}
}

if (backoffice_bank_transfer_is_from_bank_flow($transactionType)) {
	$currentWithdrawalStatus = strtolower(trim((string)($row->withdrawal_status ?? '')));
	if ($currentWithdrawalStatus === 'withdrawn') {
		$withdrawingUserId = (int)($row->withdrawing_user_id ?? 0);
	} elseif ($withdrawingUserId <= 0) {
		$errors['withdrawing_user_id'] = 'Assign a user to withdraw from the bank.';
	} elseif ($withdrawingUserId === (int)($row->created_by ?? 0)) {
		$errors['withdrawing_user_id'] = 'The withdrawing user must be different from the creator.';
	} elseif ($withdrawingUserId !== (int)($row->withdrawing_user_id ?? 0) && !\BackOfficeCashdrawer\userHasCashdrawerToday($withdrawingUserId)) {
		$errors['withdrawing_user_id'] = 'New withdrawing user must have created a cashdrawer today.';
	}

	if ($withdrawingUserId > 0 && $secondPartyId > 0 && $secondPartyId === $withdrawingUserId) {
		$errors['second_party_user_id'] = '2nd party must be different from the withdrawing user.';
	}
} else {
	$validThirdPartyIds = array_map(function($user){
		return (int)($user->id ?? 0);
	}, backoffice_bank_transfer_third_party_candidates());

	if ($thirdPartyId <= 0) {
		$errors['third_party_user_id'] = 'Assign the transaction to a 3rd party.';
	} elseif (!in_array($thirdPartyId, $validThirdPartyIds, true)) {
		if ($thirdPartyId === (int)($row->third_party_user_id ?? 0)) {
			$validThirdPartyIds[] = $thirdPartyId;
		} else {
			$errors['third_party_user_id'] = 'New 3rd party must be an admin or general manager with today\'s cashdrawer.';
		}
	}
}

if (!empty($errors)) {
	set_value('old_post', $postdata);
	set_value('errors', $errors);
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

$now = date('Y-m-d H:i:s');
$updatePayload = [
	'second_party_user_id' => $secondPartyId,
	'updated_by' => $userId,
	'date_updated' => $now,
];

$secondPartyChanged = $secondPartyId !== (int)($row->second_party_user_id ?? 0);
if ($secondPartyChanged) {
	$updatePayload['treasury_slip_photo'] = null;
	$updatePayload['second_party_verified_by'] = null;
	$updatePayload['second_party_verified_at'] = null;
	if (backoffice_bank_transfer_is_from_bank_flow($transactionType)) {
		$updatePayload['second_party_status'] = strtolower(trim((string)($row->withdrawal_status ?? ''))) === 'withdrawn' ? 'Pending' : 'Waiting Withdrawal';
		if ($statusLower === 'pending 2nd party') {
			$updatePayload['transaction_status'] = 'Pending 2nd Party';
		}
	} else {
		$updatePayload['second_party_status'] = 'Pending';
		$updatePayload['third_party_status'] = 'Pending';
		$updatePayload['transaction_status'] = 'Pending 2nd Party';
	}
}

if (backoffice_bank_transfer_is_from_bank_flow($transactionType)) {
	if ($withdrawingUserId !== (int)($row->withdrawing_user_id ?? 0)) {
		$updatePayload['withdrawing_user_id'] = $withdrawingUserId;
		$updatePayload['withdrawal_status'] = 'Pending';
		$updatePayload['withdrawn_by'] = null;
		$updatePayload['withdrawn_at'] = null;
		$updatePayload['creator_bank_receipt_photo'] = null;
		$updatePayload['creator_bank_receipt_uploaded_at'] = null;
		$updatePayload['creator_receipt_status'] = 'Pending';
		$updatePayload['second_party_status'] = 'Waiting Withdrawal';
		$updatePayload['transaction_status'] = 'Pending Withdrawal';
	}
} else {
	if ($thirdPartyId !== (int)($row->third_party_user_id ?? 0)) {
		$updatePayload['third_party_user_id'] = $thirdPartyId;
		$updatePayload['third_party_status'] = 'Pending';
		$updatePayload['third_party_reviewed_by'] = null;
		$updatePayload['third_party_reviewed_at'] = null;
		$updatePayload['third_party_reject_reason'] = null;
		$updatePayload['assignment_snapshot_json'] = json_encode(backoffice_bank_transfer_assignment_snapshot(
			$thirdPartyId,
			(string)($row->transfer_category ?? ''),
			(string)($row->bank_name ?? ''),
			(string)($row->network_name ?? '')
		), JSON_UNESCAPED_SLASHES);
	}
}

$updatePayload['approved_by'] = null;
$updatePayload['approved_at'] = null;
$bankTransfer->update($id, $updatePayload);
if (!empty($bankTransfer->has_error)) {
	$errorMessage = 'Could not update assigned parties. ' . trim((string)($bankTransfer->error ?? ''));
	set_value('old_post', $postdata);
	set_value('errors', ['reassign' => trim($errorMessage)]);
	message_fail(trim($errorMessage));
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

set_value('old_post', []);
set_value('errors', []);
message_success('Assigned parties updated successfully.');
redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
exit;

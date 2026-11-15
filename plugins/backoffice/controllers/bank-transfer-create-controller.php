<?php

use function BackOfficeCashdrawer\calculateTotal;
use function BackOfficeCashdrawer\checkCashDenominationCountSufficiencyCurrency;
use function BackOfficeCashdrawer\checkCashDenominationSufficiencyCurrency;
use function BackOfficeCashdrawer\getActiveDrawer;
use function BackOfficeCashdrawer\normalizeDenominations;
use function BackOfficeCashdrawer\userHasCashdrawerToday;

date_default_timezone_set('Africa/Accra');

$routeConfig = get_value();
$admin_route = (string)($routeConfig['admin_route'] ?? 'admin');
$plugin_route = (string)($routeConfig['plugin_route'] ?? 'back_office');

$postdata = $req->post();
$bankTransfer = new \BackOffices\BankTransfer;
$csrf = csrf_verify($postdata);

$req->upload_folder = 'uploads/backoffice/bank-transfers';
$maxFileSize = 1 * 1024 * 1024;

$session = get_session();
$userId = (int)($session->id ?? 0);
$username = trim((string)(($session->first_name ?? '') . ' ' . ($session->last_name ?? '')));
$transactionType = trim((string)($postdata['transaction_type'] ?? ''));
$isToBankFlow = backoffice_bank_transfer_is_to_bank_flow($transactionType);

$amountValue = (float)($postdata['amount'] ?? 0);
$rawDenos = $postdata['denos_json'] ?? '[]';
$denosPayload = normalizeDenominations($rawDenos, $amountValue, 'GHS');
$denosTotal = calculateTotal($denosPayload);

if ($isToBankFlow) {
	if ($denosTotal <= 0) {
		$bankTransfer->errors['denos_json'] = 'Use the calculator to capture the amount breakdown.';
	} elseif (abs($denosTotal - $amountValue) > 0.01) {
		$bankTransfer->errors['denos_json'] = 'Calculator total must match the amount / load.';
	}
}

if($csrf && $bankTransfer->validate_insert($postdata))
{
	$transferCategory = trim((string)($postdata['transfer_category'] ?? ''));
	$bankName = trim((string)($postdata['bank_name'] ?? ''));
	$networkName = trim((string)($postdata['network_name'] ?? ''));
	$withdrawingUserId = (int)($postdata['withdrawing_user_id'] ?? 0);
	$secondPartyId = (int)($postdata['second_party_user_id'] ?? 0);
	$thirdPartyId = (int)($postdata['third_party_user_id'] ?? 0);
	$isFromBankFlow = backoffice_bank_transfer_is_from_bank_flow($transactionType);
	$sourceVaultId = $transactionType === 'From Vault' ? (int)($postdata['source_vault_id'] ?? 0) : 0;
	$destinationVaultId = $transactionType === 'To Vault' ? (int)($postdata['destination_vault_id'] ?? 0) : 0;
	$chequeNumber = trim((string)($postdata['cheque_number'] ?? ''));
	$chequeDate = trim((string)($postdata['cheque_date'] ?? ''));

	if ($transactionType === 'From Vault' && $sourceVaultId <= 0) {
		$bankTransfer->errors['source_vault_id'] = 'Select the vault that will supply the cash.';
	}
	if ($transactionType === 'To Vault' && $destinationVaultId <= 0) {
		$bankTransfer->errors['destination_vault_id'] = 'Select the vault that will receive the bank withdrawal.';
	}
	if ($sourceVaultId > 0 && (!function_exists('Vaults\\getVault') || !\Vaults\getVault($sourceVaultId, $userId))) {
		$bankTransfer->errors['source_vault_id'] = 'Select an active vault assigned to your branch.';
	}
	if ($destinationVaultId > 0 && (!function_exists('Vaults\\getVault') || !\Vaults\getVault($destinationVaultId, $userId))) {
		$bankTransfer->errors['destination_vault_id'] = 'Select an active vault assigned to your branch.';
	}
	$branchVaultId = $sourceVaultId > 0 ? $sourceVaultId : $destinationVaultId;
	$branchVault = null;
	if ($branchVaultId > 0 && empty($bankTransfer->errors['source_vault_id']) && empty($bankTransfer->errors['destination_vault_id'])) {
		$branchVault = \Vaults\getVault($branchVaultId, $userId);
	}
	if ($branchVault) {
		$branchParties = $transactionType === 'From Vault'
			? [
				'second_party_user_id' => $secondPartyId,
				'third_party_user_id' => $thirdPartyId,
			]
			: [
				'second_party_user_id' => $secondPartyId,
				'withdrawing_user_id' => $withdrawingUserId,
			];
		$dbParties = new \Core\Database;
		foreach ($branchParties as $field => $partyId) {
			if ($partyId <= 0) {
				continue;
			}
			$partyRows = $dbParties->query(
				'SELECT branch_id FROM users WHERE id = :id AND (disabled IS NULL OR disabled = 0) LIMIT 1',
				['id' => $partyId]
			) ?: [];
			if ($dbParties->has_error || empty($partyRows) || (int)($partyRows[0]->branch_id ?? 0) !== (int)$branchVault->branch_id) {
				$bankTransfer->errors[$field] = 'All assigned parties must belong to the selected vault branch.';
			}
		}
	}

	if ($isToBankFlow && $transferCategory === '') {
		$bankTransfer->errors['transfer_category'] = 'Category is required for To Bank entries.';
	}
	if ($isToBankFlow && $transferCategory === 'E-Cash' && $networkName === '') {
		$bankTransfer->errors['network_name'] = 'Select the network for E-Cash entries.';
	}
	if ($isFromBankFlow && !backoffice_user_can_create_from_bank_transfer($session)) {
		$bankTransfer->errors['transaction_type'] = 'Only admins or managers can create From Bank / To Vault entries.';
	}
	if ($isFromBankFlow && $withdrawingUserId <= 0) {
		$bankTransfer->errors['withdrawing_user_id'] = 'Assign a user to withdraw from the bank.';
	}
	if ($isFromBankFlow && $withdrawingUserId === $userId) {
		$bankTransfer->errors['withdrawing_user_id'] = 'The withdrawing user must be different from the creator.';
	}
	if ($secondPartyId <= 0) {
		$bankTransfer->errors['second_party_user_id'] = 'Assign the transaction to a 2nd party.';
	}
	if ($secondPartyId === $userId) {
		$bankTransfer->errors['second_party_user_id'] = '2nd party must be another user with today\'s cashdrawer.';
	}
	if ($isToBankFlow && $thirdPartyId <= 0) {
		$bankTransfer->errors['third_party_user_id'] = 'Assign the transaction to a 3rd party.';
	}
	if ($isFromBankFlow && $secondPartyId === $withdrawingUserId && $withdrawingUserId > 0) {
		$bankTransfer->errors['second_party_user_id'] = '2nd party must be different from the withdrawing user.';
	}
	if ($isFromBankFlow && $chequeNumber === '') {
		$bankTransfer->errors['cheque_number'] = 'Cheque number is required.';
	}
	if ($isFromBankFlow && $chequeDate === '') {
		$bankTransfer->errors['cheque_date'] = 'Date on cheque is required.';
	}

	$secondPartyExcludeIds = [$userId];
	if ($isFromBankFlow && $withdrawingUserId > 0) {
		$secondPartyExcludeIds[] = $withdrawingUserId;
	}
	$validSecondPartyIds = array_map(function($row){
		return (int)($row->id ?? 0);
	}, backoffice_bank_transfer_candidates_excluding($secondPartyExcludeIds));
	$secondPartyHasDrawerToday = $secondPartyId > 0 && \BackOfficeCashdrawer\userHasCashdrawerToday($secondPartyId);
	if (
		$secondPartyId > 0
		&& (
			(!$secondPartyHasDrawerToday)
			|| in_array($secondPartyId, $secondPartyExcludeIds, true)
			|| (!empty($validSecondPartyIds) && !in_array($secondPartyId, $validSecondPartyIds, true) && $isFromBankFlow)
		)
	) {
		$bankTransfer->errors['second_party_user_id'] = 'Selected 2nd party must have created a cashdrawer today.';
	}

	$validWithdrawerIds = array_map(function($row){
		return (int)($row->id ?? 0);
	}, backoffice_bank_transfer_candidates_excluding([$userId]));
	$withdrawingUserHasDrawerToday = $withdrawingUserId > 0 && \BackOfficeCashdrawer\userHasCashdrawerToday($withdrawingUserId);
	if (
		$isFromBankFlow
		&& $withdrawingUserId > 0
		&& (!$withdrawingUserHasDrawerToday || (!empty($validWithdrawerIds) && !in_array($withdrawingUserId, $validWithdrawerIds, true)))
	) {
		$bankTransfer->errors['withdrawing_user_id'] = 'Selected withdrawing user must have created a cashdrawer today.';
	}

	if ($isToBankFlow) {
		if ($denosTotal <= 0 || abs($denosTotal - $amountValue) > 0.01) {
			$bankTransfer->errors['denos_json'] = 'Calculator total must match the amount / load.';
		}
		$validThirdPartyIds = array_map(function($row){
			return (int)($row->id ?? 0);
		}, backoffice_bank_transfer_third_party_candidates());
		if ($thirdPartyId > 0 && !in_array($thirdPartyId, $validThirdPartyIds, true)) {
			$bankTransfer->errors['third_party_user_id'] = 'Selected 3rd party must be an admin or general manager with today\'s cashdrawer.';
		}

		if ($sourceVaultId > 0) {
			if (empty($bankTransfer->errors['source_vault_id']) && !\Vaults\hasSufficientStock($sourceVaultId, 'GHS', $denosPayload)) {
				$bankTransfer->errors['denos_json'] = 'The selected vault does not contain the entered GHS note mix.';
			}
		} elseif (!userHasCashdrawerToday($userId)) {
			$bankTransfer->errors['denos_json'] = 'Open your cashdrawer for today before submitting a To Bank transaction.';
		} else {
			$creatorDrawer = getActiveDrawer($userId);
			$drawerUserId = (int)($creatorDrawer['drawer_user_id'] ?? $userId);

			$balanceCheck = checkCashDenominationSufficiencyCurrency($drawerUserId, $denosPayload, $amountValue, 'GHS');
			if (empty($balanceCheck['ok'])) {
				$required = number_format((float)($balanceCheck['required'] ?? $amountValue), 2);
				$available = number_format((float)($balanceCheck['available'] ?? 0), 2);
				$bankTransfer->errors['denos_json'] = "Insufficient GHS cashdrawer balance. Required: GHS {$required}, available: GHS {$available}.";
			}

			$countsCheck = checkCashDenominationCountSufficiencyCurrency($drawerUserId, $denosPayload, $amountValue, 'GHS');
			if (empty($countsCheck['ok'])) {
				$shortages = $countsCheck['shortages'] ?? [];
				$msg = 'Insufficient GHS denominations in cashdrawer.';
				if (!empty($shortages) && is_array($shortages)) {
					$parts = [];
					foreach ($shortages as $s) {
						$den = (string)($s['denomination'] ?? '');
						$need = (int)($s['required_count'] ?? 0);
						$have = (int)($s['available_count'] ?? 0);
						if ($den === '' || $need <= 0) {
							continue;
						}
						$parts[] = "{$den} x{$need} (available {$have})";
					}
					if (!empty($parts)) {
						$msg .= ' Shortages: ' . implode(', ', $parts) . '.';
					}
				}
				$bankTransfer->errors['denos_json'] = $msg;
			}
		}
	}

	if ($isFromBankFlow) {
		if (empty($_FILES['cheque_photo']['name'])) {
			$bankTransfer->errors['cheque_photo'] = 'Upload a picture of the cheque.';
		} elseif (!empty($_FILES['cheque_photo']['size']) && (int)$_FILES['cheque_photo']['size'] > $maxFileSize) {
			$bankTransfer->errors['cheque_photo'] = 'Cheque photo must not exceed 1MB.';
		}
	}

	if (!empty($bankTransfer->errors)) {
		set_value('old_post', $postdata);
		set_value('errors', $bankTransfer->errors);
		return;
	}

	$postdata['id'] = empty($postdata['id']) ? (int)floor(microtime(true) * 1000000) : (int)$postdata['id'];
	$postdata['created_by'] = $userId;
	$postdata['date_created'] = date('Y-m-d H:i:s');
	$postdata['transaction_status'] = $isFromBankFlow ? 'Pending Withdrawal' : 'Pending 2nd Party';
	$postdata['withdrawal_status'] = 'Pending';
	$postdata['second_party_status'] = $isFromBankFlow ? 'Waiting Withdrawal' : 'Pending';
	$postdata['creator_receipt_status'] = 'Pending';
	$postdata['third_party_status'] = 'Pending';
	$postdata['denos_json'] = $isToBankFlow
		? json_encode($denosPayload, JSON_UNESCAPED_SLASHES)
		: '[]';
	$postdata['source_vault_id'] = $sourceVaultId > 0 ? $sourceVaultId : null;
	$postdata['destination_vault_id'] = $destinationVaultId > 0 ? $destinationVaultId : null;
	$postdata['assignment_snapshot_json'] = $isToBankFlow
		? json_encode(backoffice_bank_transfer_assignment_snapshot($thirdPartyId, $transferCategory, $bankName, $networkName), JSON_UNESCAPED_SLASHES)
		: json_encode(['missing' => false, 'message' => '', 'lines' => []], JSON_UNESCAPED_SLASHES);
	if ($isFromBankFlow) {
		$postdata['transfer_category'] = null;
		$postdata['network_name'] = null;
		$postdata['third_party_user_id'] = null;
		$postdata['cheque_number'] = $chequeNumber;
		$postdata['cheque_date'] = $chequeDate;
		$postdata['cheque_photo'] = $req->upload_files('cheque_photo');
		if (empty($postdata['cheque_photo'])) {
			set_value('old_post', $postdata);
			set_value('errors', ['cheque_photo' => 'Failed to upload cheque photo.']);
			return;
		}
	} else {
		$postdata['withdrawing_user_id'] = null;
		$postdata['cheque_number'] = null;
		$postdata['cheque_date'] = null;
		$postdata['cheque_photo'] = null;
	}

	$bankTransfer->insert($postdata);
	if (!empty($bankTransfer->has_error)) {
		error_log('Backoffice bank transfer insert failed: ' . $bankTransfer->error);
		set_value('old_post', $postdata);
		// Surface DB error in the form errors for debugging by developers.
		// Keep the user-facing message generic while exposing a debug field.
		set_value('errors', [
			'transaction_type' => 'Unable to create the bank transaction. Please try again.',
			'db_error' => $bankTransfer->error,
		]);
		return;
	}
	backoffice_whatsapp_notify_transaction([
		'type' => 'Bank Transfer',
		'id' => (int)($postdata['id'] ?? 0),
		'status' => $postdata['transaction_status'] ?? '',
		'creator_id' => $userId,
		'assigned_id' => $isToBankFlow ? $thirdPartyId : $withdrawingUserId,
		'details' => [
			'Transaction type' => $transactionType,
			'Bank' => $bankName,
			'Amount' => 'GHS ' . number_format((float)($postdata['amount'] ?? 0), 2),
		],
		'view_url' => ROOT . '/admin/back_office/view_bank_transfer/' . (int)($postdata['id'] ?? 0),
	]);

	$log = new \BackOffices\Log;
	$log->insert([
		'table_name' => 'bank_transfers_backoffice',
		'action' => 'added',
		'user_id' => $userId,
		'user_name' => $username,
		'description' => $username . ' added a new TO & FROM transaction with id ' . $postdata['id'],
		'date_created' => date('Y-m-d H:i:s'),
	]);

	set_value('old_post', []);
	set_value('errors', []);
	message_success('TO & FROM transaction created successfully.');
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $postdata['id']);
	exit;
}

if(!$csrf) {
	$bankTransfer->errors['transaction_type'] = 'Form expired!';
}

if (!empty($bankTransfer->errors)) {
	set_value('old_post', $postdata);
	set_value('errors', $bankTransfer->errors);
}

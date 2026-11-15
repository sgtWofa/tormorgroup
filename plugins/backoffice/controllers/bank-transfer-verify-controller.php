<?php

use function BackOfficeCashdrawer\calculateTotal;
use function BackOfficeCashdrawer\syncBankWithdrawalToCashdrawer;
use function BackOfficeCashdrawer\checkCashDenominationCountSufficiencyCurrency;
use function BackOfficeCashdrawer\checkCashDenominationSufficiencyCurrency;
use function BackOfficeCashdrawer\normalizeDenominations;
use function BackOfficeCashdrawer\syncCashMovementCurrency;

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
$transactionType = strtolower(trim((string)($row->transaction_type ?? '')));
$isFromBankFlow = backoffice_bank_transfer_is_from_bank_flow($transactionType);
$verificationAction = strtolower(trim((string)($postdata['verification_action'] ?? ($_POST['verification_action'] ?? 'verify'))));
if (!in_array($verificationAction, ['verify', 'reject'], true)) {
	$verificationAction = 'verify';
}

if (!$csrf) {
	set_value('old_post', $postdata);
	set_value('errors', ['treasury_slip_photo' => 'Form expired!']);
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

if ((int)($row->second_party_user_id ?? 0) !== $userId) {
	message_fail('Only the assigned 2nd party can verify this transaction.');
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

if (strtolower(trim((string)($row->transaction_status ?? ''))) !== 'pending 2nd party') {
	message_fail('This transaction is no longer waiting for 2nd-party verification.');
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

$req->upload_folder = 'uploads/backoffice/bank-transfers';
$maxFileSize = 1 * 1024 * 1024;
if ($isFromBankFlow) {
	$amountValue = (float)($row->amount ?? 0);
	$denosPayload = normalizeDenominations($postdata['denos_json'] ?? '[]', $amountValue, 'GHS');
	$denosTotal = calculateTotal($denosPayload);
	if (empty($denosPayload)) {
		set_value('old_post', $postdata);
		set_value('errors', ['denos_json' => 'Enter the denominations that will be added to the cashdrawer.']);
		redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
		exit;
	}

	$denoMismatch = abs($denosTotal - $amountValue) > 0.01;
	if ($verificationAction === 'reject') {
		if (!$denoMismatch) {
			set_value('old_post', $postdata);
			set_value('errors', ['denos_json' => 'Reject is only needed when the denomination total does not match the cheque amount.']);
			redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
			exit;
		}
		$reason = trim((string)($postdata['reject_reason'] ?? ''));
		if ($reason === '') {
			set_value('old_post', $postdata);
			set_value('errors', ['reject_reason' => 'Enter a clear reason for rejecting this withdrawal mismatch.']);
			redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
			exit;
		}
	}

	if ($denoMismatch) {
		set_value('old_post', $postdata);
		set_value('errors', [
			'denos_json' => 'The denomination total does not match the amount on cheque. Enter a clear reject reason and submit the rejection.',
		]);
		redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
		exit;
	}
} elseif ($verificationAction === 'reject') {
	$reason = trim((string)($postdata['reject_reason'] ?? ''));
	if ($reason === '') {
		set_value('old_post', $postdata);
		set_value('errors', ['reject_reason' => 'Enter a clear reason for rejecting this treasury slip mismatch.']);
		redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
		exit;
	}
}

if (empty($_FILES['treasury_slip_photo']['name'])) {
	set_value('old_post', $postdata);
	set_value('errors', ['treasury_slip_photo' => $verificationAction === 'reject'
		? 'Upload the signed treasury slip photo before rejecting this transaction.'
		: 'Signed treasury slip photo is required.'
	]);
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}
if (!empty($_FILES['treasury_slip_photo']['size']) && (int)$_FILES['treasury_slip_photo']['size'] > $maxFileSize) {
	set_value('old_post', $postdata);
	set_value('errors', ['treasury_slip_photo' => 'Treasury slip photo must not exceed 1MB.']);
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

$uploaded = $req->upload_files('treasury_slip_photo');
if (empty($uploaded)) {
	set_value('old_post', $postdata);
	set_value('errors', ['treasury_slip_photo' => 'Failed to upload treasury slip photo.']);
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

$updatePayload = [
	'treasury_slip_photo' => $uploaded,
	'second_party_status' => 'Verified',
	'second_party_verified_by' => $userId,
	'second_party_verified_at' => date('Y-m-d H:i:s'),
	'updated_by' => $userId,
	'date_updated' => date('Y-m-d H:i:s'),
];
$startedTransaction = false;

if ($isFromBankFlow) {
	$amountValue = (float)($row->amount ?? 0);
	$denosPayload = normalizeDenominations($postdata['denos_json'] ?? '[]', $amountValue, 'GHS');
	$denosTotal = calculateTotal($denosPayload);
	$reason = trim((string)($postdata['reject_reason'] ?? ''));
	if ($verificationAction === 'reject') {
		$updatePayload['denos_json'] = json_encode($denosPayload, JSON_UNESCAPED_SLASHES);
		$updatePayload['second_party_status'] = 'Rejected';
		$updatePayload['third_party_reject_reason'] = $reason;
		$updatePayload['transaction_status'] = 'Rejected';
		$bankTransfer->update($id, $updatePayload);

		set_value('old_post', []);
		set_value('errors', []);
		message_success('Mismatch recorded, treasury slip uploaded, and transaction rejected with the 2nd-party reason.');
		redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
		exit;
	}

	if (abs($denosTotal - $amountValue) > 0.01) {
		set_value('old_post', $postdata);
		set_value('errors', ['denos_json' => 'The denomination total does not match the amount on cheque. Enter a clear reject reason and submit the rejection.']);
		redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
		exit;
	}
	$secondPartyId = (int)($row->second_party_user_id ?? 0);
	if ($secondPartyId <= 0) {
		set_value('old_post', $postdata);
		set_value('errors', ['denos_json' => 'A valid 2nd party is required before final approval.']);
		redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
		exit;
	}
	if ((int)($row->destination_vault_id ?? 0) > 0) {
		$startedTransaction = $bankTransfer->beginTransaction();
		if (!$startedTransaction) {
			set_value('old_post', $postdata);
			set_value('errors', ['denos_json' => 'Unable to prepare the vault deposit. Please try again.']);
			redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
			exit;
		}
		try {
			\Vaults\applyBankTransfer((int)$row->destination_vault_id, 'in', 'GHS', $denosPayload, (int)$id, $userId);
		} catch (\Throwable $e) {
			$bankTransfer->rollBack();
			error_log('[backoffice bank transfer] vault deposit failed for #' . $id . ': ' . $e->getMessage());
			set_value('old_post', $postdata);
			set_value('errors', ['denos_json' => 'Unable to post the bank withdrawal to the destination vault: ' . $e->getMessage()]);
			redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
			exit;
		}
	} elseif (strtolower(trim((string)($row->transaction_type ?? ''))) === 'from bank') {
		$withdrawingUserId = (int)($row->withdrawing_user_id ?? 0);
		$startedTransaction = $bankTransfer->beginTransaction();
		if (!$startedTransaction) {
			set_value('old_post', $postdata);
			set_value('errors', ['denos_json' => 'Unable to prepare the bank and cashdrawer balance update. Please try again.']);
			redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
			exit;
		}
		$movement = syncBankWithdrawalToCashdrawer(
			$withdrawingUserId,
			$secondPartyId,
			(string)($row->bank_name ?? ''),
			$denosPayload,
			$amountValue,
			'Approved',
			(int)$id
		);
		if (empty($movement['ok'])) {
			$bankTransfer->rollBack();
			$balance = $movement['balance'] ?? [];
			$error = ($movement['reason'] ?? '') === 'insufficient_bank_balance'
				? 'Insufficient ' . (string)($row->bank_name ?? 'bank') . ' balance in the withdrawing user cashdrawer. Required: GHS ' . number_format((float)($balance['required'] ?? $amountValue), 2) . ', available: GHS ' . number_format((float)($balance['available'] ?? 0), 2) . '.'
				: 'Unable to update the selected bank balance and 2nd party cashdrawer. Confirm both users have active cashdrawers.';
			set_value('old_post', $postdata);
			set_value('errors', ['denos_json' => $error]);
			redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
			exit;
		}
	} else {
		$drawerContext = syncCashMovementCurrency($secondPartyId, $denosPayload, $amountValue, 'Approved', (int)$id, 'in', 'GHS');
		if (empty($drawerContext)) {
			set_value('old_post', $postdata);
			set_value('errors', ['denos_json' => 'Unable to update the 2nd party cashdrawer. Confirm that the user has an active cashdrawer for today.']);
			redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
			exit;
		}
	}
	$updatePayload['denos_json'] = json_encode($denosPayload, JSON_UNESCAPED_SLASHES);
	$updatePayload['second_party_status'] = 'Approved';
	$updatePayload['approved_by'] = $userId;
	$updatePayload['approved_at'] = date('Y-m-d H:i:s');
	$updatePayload['transaction_status'] = 'Approved';
} else {
	if ($verificationAction === 'reject') {
		$reason = trim((string)($postdata['reject_reason'] ?? ''));
		$updatePayload['second_party_status'] = 'Rejected';
		$updatePayload['third_party_reject_reason'] = $reason;
		$updatePayload['transaction_status'] = 'Rejected';
		$bankTransfer->update($id, $updatePayload);

		set_value('old_post', []);
		set_value('errors', []);
		message_success('Treasury slip mismatch recorded and transaction rejected by the 2nd party.');
		redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
		exit;
	}

	$amountValue = (float)($row->amount ?? 0);
	$creatorId = (int)($row->created_by ?? 0);
	$denosPayload = normalizeDenominations((string)($row->denos_json ?? '[]'), $amountValue, 'GHS');
	$denosTotal = calculateTotal($denosPayload);

	if ($creatorId <= 0) {
		set_value('old_post', $postdata);
		set_value('errors', ['denos_json' => 'A valid creator is required before 2nd-party verification can deduct the cashdrawer.']);
		redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
		exit;
	}

	if (empty($denosPayload) || abs($denosTotal - $amountValue) > 0.01) {
		set_value('old_post', $postdata);
		set_value('errors', ['denos_json' => 'The creator denominations must equal the bank transfer amount before verification.']);
		redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
		exit;
	}

	$sourceVaultId = (int)($row->source_vault_id ?? 0);
	if ($sourceVaultId > 0) {
		if (!function_exists('Vaults\\applyBankTransfer') || !\Vaults\hasSufficientStock($sourceVaultId, 'GHS', $denosPayload)) {
			set_value('old_post', $postdata);
			set_value('errors', ['denos_json' => 'The selected vault no longer contains the submitted GHS note mix.']);
			redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
			exit;
		}
		$startedTransaction = $bankTransfer->beginTransaction();
		if (!$startedTransaction) {
			set_value('old_post', $postdata);
			set_value('errors', ['treasury_slip_photo' => 'Unable to prepare verification. Please try again.']);
			redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
			exit;
		}
		try {
			\Vaults\applyBankTransfer($sourceVaultId, 'out', 'GHS', $denosPayload, (int)$id, $userId);
		} catch (\Throwable $e) {
			$bankTransfer->rollBack();
			error_log('[backoffice bank transfer] vault withdrawal failed for #' . $id . ': ' . $e->getMessage());
			set_value('old_post', $postdata);
			set_value('errors', ['denos_json' => 'Unable to deduct the selected vault: ' . $e->getMessage()]);
			redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
			exit;
		}
	} else {
		$balanceCheck = checkCashDenominationSufficiencyCurrency($creatorId, $denosPayload, $amountValue, 'GHS');
		if (empty($balanceCheck['ok'])) {
			$required = number_format((float)($balanceCheck['required'] ?? $amountValue), 2);
			$available = number_format((float)($balanceCheck['available'] ?? 0), 2);
			set_value('old_post', $postdata);
			set_value('errors', ['denos_json' => "Insufficient GHS cashdrawer balance for the creator. Required: GHS {$required}, available: GHS {$available}."]);
			redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
			exit;
		}

		$countsCheck = checkCashDenominationCountSufficiencyCurrency($creatorId, $denosPayload, $amountValue, 'GHS');
		if (empty($countsCheck['ok'])) {
			$shortages = $countsCheck['shortages'] ?? [];
			$msg = 'Insufficient GHS denominations in the creator cashdrawer.';
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
			set_value('old_post', $postdata);
			set_value('errors', ['denos_json' => $msg]);
			redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
			exit;
		}

		$startedTransaction = $bankTransfer->beginTransaction();
		if (!$startedTransaction) {
			set_value('old_post', $postdata);
			set_value('errors', ['treasury_slip_photo' => 'Unable to prepare verification. Please try again.']);
			redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
			exit;
		}

		$drawerContext = syncCashMovementCurrency($creatorId, $denosPayload, $amountValue, 'Verified To Bank', (int)$id, 'out', 'GHS');
		if (empty($drawerContext)) {
			$bankTransfer->rollBack();
			set_value('old_post', $postdata);
			set_value('errors', ['denos_json' => 'Unable to deduct the creator cashdrawer. Confirm today\'s drawer has the submitted currency notes mix.']);
			redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
			exit;
		}
	}

	$updatePayload['transaction_status'] = 'Pending 3rd Party';
}

$bankTransfer->update($id, $updatePayload);
if (!empty($bankTransfer->has_error)) {
	if ($startedTransaction) {
		$bankTransfer->rollBack();
	}
	set_value('old_post', $postdata);
	set_value('errors', ['treasury_slip_photo' => 'Unable to save verification. Please try again.']);
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}
if ($startedTransaction) {
	$bankTransfer->commit();
}

set_value('old_post', []);
set_value('errors', []);
message_success($isFromBankFlow
	? ((int)($row->destination_vault_id ?? 0) > 0
		? 'Treasury slip uploaded, denominations approved, and the destination vault has been updated.'
		: 'Treasury slip uploaded, denominations approved, and the 2nd party cashdrawer has been updated.')
	: 'Treasury slip uploaded, creator cashdrawer deducted, and sent to the 3rd party for final receipt upload and review.'
);
redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
exit;

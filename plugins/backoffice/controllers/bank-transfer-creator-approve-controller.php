<?php

use function BackOfficeCashdrawer\calculateTotal;
use function BackOfficeCashdrawer\normalizeDenominations;
use function BackOfficeCashdrawer\syncBankWithdrawalToCashdrawer;
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

if (!$csrf) {
	set_value('errors', ['reject_reason' => 'Form expired!']);
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

if (!backoffice_bank_transfer_is_from_bank_flow((string)($row->transaction_type ?? ''))) {
	message_fail('This action only applies to From Bank or To Vault transactions.');
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

if ((int)($row->second_party_user_id ?? 0) !== $userId) {
	message_fail('Only the assigned 2nd party can complete this bank withdrawal transaction.');
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

if (strtolower(trim((string)($row->transaction_status ?? ''))) !== 'pending creator approval') {
	message_fail('This transaction is not waiting for final 2nd-party approval.');
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

$action = strtolower(trim((string)($postdata['review_action'] ?? '')));
if (!in_array($action, ['approve', 'reject'], true)) {
	message_fail('Invalid approval action.');
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

if ($action === 'reject') {
	$reason = trim((string)($postdata['reject_reason'] ?? ''));
	if ($reason === '') {
		set_value('errors', ['reject_reason' => 'Reject reason is required.']);
		redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
		exit;
	}

	$bankTransfer->update($id, [
		'second_party_status' => 'Rejected',
		'third_party_reject_reason' => $reason,
		'transaction_status' => 'Rejected',
		'updated_by' => $userId,
		'date_updated' => date('Y-m-d H:i:s'),
	]);

	message_success('Bank withdrawal transaction rejected by the 2nd party.');
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}

$amountValue = (float)($row->amount ?? 0);
$secondPartyId = (int)($row->second_party_user_id ?? 0);
$denosPayload = normalizeDenominations((string)($row->denos_json ?? '[]'), $amountValue, 'GHS');
$denosTotal = calculateTotal($denosPayload);
if ($secondPartyId <= 0) {
	message_fail('A valid 2nd party is required before approval.');
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}
if (empty($denosPayload) || abs($denosTotal - $amountValue) > 0.01) {
	message_fail('The 2nd-party denominations must equal the cheque amount before approval.');
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}


$startedTransaction = false;
$isFromBankCashFlow = strtolower(trim((string)($row->transaction_type ?? ''))) === 'from bank'
	&& (int)($row->destination_vault_id ?? 0) <= 0;
if ($isFromBankCashFlow) {
	$withdrawingUserId = (int)($row->withdrawing_user_id ?? 0);
	$startedTransaction = $bankTransfer->beginTransaction();
	if (!$startedTransaction) {
		message_fail('Unable to prepare the bank and cashdrawer balance update. Please try again.');
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
		$message = ($movement['reason'] ?? '') === 'insufficient_bank_balance'
			? 'Insufficient ' . (string)($row->bank_name ?? 'bank') . ' balance in the withdrawing user cashdrawer. Required: GHS ' . number_format((float)($balance['required'] ?? $amountValue), 2) . ', available: GHS ' . number_format((float)($balance['available'] ?? 0), 2) . '.'
			: 'Unable to update the selected bank balance and 2nd party cashdrawer.';
		message_fail($message);
		redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
		exit;
	}
} else {
	$drawerContext = syncCashMovementCurrency($secondPartyId, $denosPayload, $amountValue, 'Approved', (int)$id, 'in', 'GHS');
	if (empty($drawerContext)) {
		message_fail('Unable to update the 2nd party cashdrawer. Confirm that the user has an active cashdrawer for today.');
		redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
		exit;
	}
}

$bankTransfer->update($id, [
	'second_party_status' => 'Approved',
	'approved_by' => $userId,
	'approved_at' => date('Y-m-d H:i:s'),
	'transaction_status' => 'Approved',
	'updated_by' => $userId,
	'date_updated' => date('Y-m-d H:i:s'),
]);
if (!empty($bankTransfer->has_error)) {
	if ($startedTransaction) {
		$bankTransfer->rollBack();
	}
	message_fail('Unable to complete the bank withdrawal balance update. Please try again.');
	redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
	exit;
}
if ($startedTransaction) {
	$bankTransfer->commit();
}

message_success('Bank withdrawal transaction approved and the 2nd party cashdrawer has been updated.');
redirect($admin_route . '/' . $plugin_route . '/view_bank_transfer/' . $id);
exit;

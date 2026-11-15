<?php

if (!user_can('is_admin')) {
	message_fail('Only an admin can cancel cashpickup transactions.');
	redirect($admin_route . '/' . $plugin_route);
	exit;
}

$postdata = $req->post();
if (!csrf_verify($postdata)) {
	message_fail('Form expired.');
	redirect($admin_route . '/' . $plugin_route);
	exit;
}

$cancellationReason = trim((string)($postdata['cancellation_reason'] ?? ''));
if ($cancellationReason === '' || strlen($cancellationReason) > 500) {
	message_fail('Enter a cancellation reason (up to 500 characters).');
	redirect($admin_route . '/' . $plugin_route);
	exit;
}

$id = (int)(URL(3) ?? 0);
$db = new \Core\Database;
$customerLedgerPath = realpath(__DIR__ . '/../helpers/customer-ledger.php');
if ($customerLedgerPath && file_exists($customerLedgerPath)) {
	require_once $customerLedgerPath;
	if (function_exists('CashpickupLedger\\ensure_cashpickup_transfer_columns')) {
		\CashpickupLedger\ensure_cashpickup_transfer_columns($db);
	}
}
if ($id <= 0 || !$db->beginTransaction()) {
	message_fail('Unable to prepare transaction cancellation.');
	redirect($admin_route . '/' . $plugin_route);
	exit;
}

try {
	$rows = $db->query(
		"SELECT * FROM cashpickups WHERE id = :id AND (deleted IS NULL OR deleted = 0) LIMIT 1 FOR UPDATE",
		['id' => $id]
	) ?: [];
	$row = $rows[0] ?? null;
	if (!$row) {
		throw new \RuntimeException('Cashpickup transaction not found.');
	}

	$approvalStatus = strtolower(trim((string)($row->approval_status ?? 'pending')));
	$transactionStatus = strtolower(trim((string)($row->transaction_status ?? '')));
	$currentStatuses = $approvalStatus . ' ' . $transactionStatus;
	if (preg_match('/reject|declin|deni/i', $currentStatuses)) {
		throw new \RuntimeException('Rejected, declined, or denied cashpickup transactions cannot be cancelled.');
	}
	if (preg_match('/cancelled|canceled/i', $currentStatuses)) {
		if (!$db->commit()) {
			throw new \RuntimeException('Unable to complete cashpickup cancellation.');
		}
		message_success('Cashpickup transaction is already cancelled.');
		redirect($admin_route . '/' . $plugin_route . '/view/' . $id);
		exit;
	}

	$cashdrawerReversalError = '';
	$side = strtolower(trim((string)($row->transaction_side ?? '')));
	$source = strtolower(trim((string)($row->source ?? '')));
	$paymentMethod = strtolower(trim((string)($row->payment_method ?? '')));
	$isCustomerTransfer = $source === 'customer_transfer' || $paymentMethod === 'customer_transfer';
	$cancelledTransactionIds = [$id];
	if ($isCustomerTransfer && (int)($row->ledger_transfer_pair_id ?? 0) > 0) {
		$pairRows = $db->query(
			"SELECT id, approval_status, transaction_status
			 FROM cashpickups
			 WHERE ledger_transfer_pair_id = :pair_id
			   AND (deleted IS NULL OR deleted = 0)
			 FOR UPDATE",
			['pair_id' => (int)$row->ledger_transfer_pair_id]
		) ?: [];
		foreach ($pairRows as $pairRow) {
			$pairStatus = strtolower(trim((string)($pairRow->approval_status ?? 'pending'))) . ' ' . strtolower(trim((string)($pairRow->transaction_status ?? '')));
			if (preg_match('/reject|declin|deni/i', $pairStatus)) {
				throw new \RuntimeException('A customer transfer cannot be cancelled because its paired transaction was rejected or denied.');
			}
			$pairId = (int)($pairRow->id ?? 0);
			if ($pairId > 0 && !preg_match('/cancelled|canceled/i', $pairStatus) && !in_array($pairId, $cancelledTransactionIds, true)) {
				$cancelledTransactionIds[] = $pairId;
			}
		}
	}
	$hasCashdrawerMovement = !($side === 'receive' && in_array($source, ['credit', 'customer_transfer'], true))
		&& !($side === 'pay' && $paymentMethod === 'customer_transfer');
	if ($approvalStatus === 'approved' && $hasCashdrawerMovement) {
		try {
			$drawerUserId = (int)($row->created_by ?? 0);
			$bridgePath = realpath(__DIR__ . '/../../backoffice/helpers/cashdrawer-bridge.php');
			if ($drawerUserId <= 0 || !$bridgePath || !file_exists($bridgePath)) {
				throw new \RuntimeException('Unable to reverse this approved transaction in the cashdrawer.');
			}
			require_once $bridgePath;

			$movement = null;
			if ($side === 'receive') {
				$amount = (float)($row->amount_involved ?? 0);
				if ($source === '' || $source === 'cash') {
					$denominations = \BackOfficeCashdrawer\normalizeDenominations($row->denos_json ?? '[]', $amount, 'GHS');
					$movement = \BackOfficeCashdrawer\syncCashMovement($drawerUserId, $denominations, $amount, 'Approved', $id, 'out');
				} elseif (in_array($source, ['mtn', 'telecel', 'at', 'bank'], true)) {
					$channel = $source === 'bank'
						? trim((string)($row->account_paid_to ?? ''))
						: strtoupper($source);
					$balance = \BackOfficeCashdrawer\checkEcashChannelSufficiency($drawerUserId, $channel, $amount);
					if (empty($balance['ok'])) {
						throw new \RuntimeException('Unable to reverse this transaction: the cashdrawer channel has insufficient balance.');
					}
					$movement = \BackOfficeCashdrawer\syncEcashMovement($drawerUserId, $channel, $amount, 'Approved', $id, 'out');
				} else {
					throw new \RuntimeException('Unable to determine the cashdrawer source for this approved transaction.');
				}
			} elseif ($side === 'pay') {
				$amount = (float)($row->amount_paid_out ?? 0);
				if (in_array($paymentMethod, ['cash', 'cash_ghs'], true)) {
					$denominations = \BackOfficeCashdrawer\normalizeDenominations($row->denos_json ?? '[]', $amount, 'GHS');
					$movement = \BackOfficeCashdrawer\syncCashMovement($drawerUserId, $denominations, $amount, 'Approved', $id, 'in');
				} elseif ($paymentMethod === 'cash_cfa') {
					$cfaAmount = (float)($row->cfa_amount ?? 0);
					$denominations = \BackOfficeCashdrawer\normalizeDenominations($row->cfa_denos_json ?? '[]', $cfaAmount, 'CFA');
					$movement = \BackOfficeCashdrawer\syncCashMovementCurrency($drawerUserId, $denominations, $cfaAmount, 'Approved', $id, 'in', 'CFA');
				} elseif (in_array($paymentMethod, ['transfer', 'transfer_telcos', 'transfer_bank'], true)) {
					$channel = trim((string)($row->payout_channel ?? ''));
					if ($channel === '') {
						throw new \RuntimeException('Unable to determine the cashdrawer channel for this approved payout.');
					}
					$movement = \BackOfficeCashdrawer\syncEcashMovement($drawerUserId, $channel, $amount, 'Approved', $id, 'in');
				} else {
					throw new \RuntimeException('Unable to determine the cashdrawer method for this approved payout.');
				}
			} else {
				throw new \RuntimeException('Unable to determine the cashdrawer direction for this approved transaction.');
			}

			if ($movement === null) {
				throw new \RuntimeException('Unable to reverse this approved transaction in the cashdrawer.');
			}
		} catch (\Throwable $drawerError) {
			$cashdrawerReversalError = $drawerError->getMessage();
			error_log('[cashpickup cancellation] transaction_id=' . $id . ' side=' . $side . ' source=' . $source . ' payment_method=' . $paymentMethod . ' error=' . $cashdrawerReversalError);
		}
	}

	foreach ($cancelledTransactionIds as $cancelledId) {
		$db->query(
			"UPDATE cashpickups SET approval_status = 'cancelled', transaction_status = 'Cancelled', denial_reason = :denial_reason, updated_by = :updated_by, date_updated = :date_updated WHERE id = :id",
			[
				'denial_reason' => $cancellationReason,
				'updated_by' => (int)(get_session()->id ?? 0),
				'date_updated' => date('Y-m-d H:i:s'),
				'id' => $cancelledId,
			]
		);
		if ($db->has_error) {
			throw new \RuntimeException('Unable to cancel cashpickup transaction #' . $cancelledId . '.');
		}
	}

	if ($db->table_exists(['commissions'])) {
		foreach ($cancelledTransactionIds as $cancelledId) {
			$needle = '%| ID ' . $cancelledId;
			$db->query(
				"UPDATE commissions SET deleted = 1, date_deleted = :now WHERE commission_type = :ctype AND description LIKE :needle",
				['now' => date('Y-m-d H:i:s'), 'ctype' => 'CASHPICKUP CHARGE', 'needle' => $needle]
			);
			if ($db->has_error) {
				throw new \RuntimeException('Unable to remove the cashpickup charge commission for transaction #' . $cancelledId . '.');
			}
		}
	}

	if (!$db->commit()) {
		throw new \RuntimeException('Unable to commit cashpickup cancellation.');
	}
	if ($cashdrawerReversalError !== '') {
		message_fail('Cashpickup transaction cancelled, but the cashdrawer reversal was not completed. Manual reconciliation is required.');
	} else {
		message_success($isCustomerTransfer && count($cancelledTransactionIds) > 1
			? 'Customer transfer and its paired cashpickup transaction cancelled.'
			: 'Cashpickup transaction cancelled.');
	}
	redirect($admin_route . '/' . $plugin_route . '/view/' . $id);
	exit;
} catch (\Throwable $e) {
	$db->rollBack();
	message_fail($e->getMessage());
	redirect($admin_route . '/' . $plugin_route . '/view/' . $id);
	exit;
}
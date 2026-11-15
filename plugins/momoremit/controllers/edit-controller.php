<?php

$rejectOnlyRequest = !empty($_POST['reject_only']);
$isRejectAjaxRequest = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
$respondToRejectRequest = function (bool $success, string $message, int $failureStatus = 422) use ($rejectOnlyRequest, $isRejectAjaxRequest): void {
    if (!$rejectOnlyRequest || !$isRejectAjaxRequest) {
        return;
    }

    $payload = ['success' => $success, 'message' => $message];
    if (function_exists('momoremit_send_json')) {
        momoremit_send_json($payload, $success ? 200 : $failureStatus);
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($success ? 200 : $failureStatus);
    }
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
};

if (empty($row)) {
    $respondToRejectRequest(false, 'Transaction record not found.');
    message_fail("Record not found");
    return;
}

$log = new \Momoremits\Log;
date_default_timezone_set('Africa/Accra');

$postdata = $req->post();
$postdata['id'] = $row->id;

$defaults = [
    'network' => $row->network ?? '',
    'bank_name' => $row->bank_name ?? '',
    'transaction_type' => $row->transaction_type ?? '',
    'transaction_status' => momoremits_normalize_status($row->transaction_status ?? null, 'Approved'),
    'category' => $row->category ?? '',
    'sending_category' => $row->sending_category ?? '',
    'phone' => $row->phone ?? '',
    'sender' => $row->sender ?? '',
    'sim_type' => $row->sim_type ?? '',
    'charging_type' => $row->charging_type ?? '',
    'amount_load' => $row->amount_load ?? '0.00',
    'amount_payable' => $row->amount_payable ?? '0.00',
    'amount_sendable' => $row->amount_sendable ?? '0.00',
    'cash_received_amount' => $row->cash_received_amount ?? '0.00',
    'change_required' => $row->change_required ?? '0.00',
    'change_denominations' => $row->change_denominations ?? '[]',
    'amount' => $row->amount ?? '0.00',
    'balance_after' => $row->balance_after ?? '',
    'transaction_image' => $row->transaction_image ?? '',
    'rejection_reason' => $row->rejection_reason ?? '',
    'receiver' => $row->receiver ?? '',
    'token' => $row->token ?? '',
    'id_type' => $row->id_type ?? '',
    'id_number' => $row->id_number ?? '',
    'secret_code' => $row->secret_code ?? '',
    'charge' => $row->charge ?? '0.00',
    'wallet_in_id' => $row->wallet_in_id ?? '',
    'wallet_out_id' => $row->wallet_out_id ?? '',
    'balance_after_display' => '',
];

foreach ($defaults as $key => $value) {
    if (!array_key_exists($key, $postdata)) {
        $postdata[$key] = $value;
    }
}

$normalizeMoneyField = function ($value): string {
    $raw = trim((string)$value);
    if ($raw === '') {
        return '';
    }

    $raw = str_replace(['GHS', 'GHâ‚µ', 'GHÂ¢', 'â‚µ', 'Â¢', ','], '', $raw);
    if (preg_match('/-?\d+(?:\.\d+)?/', $raw, $matches)) {
        return number_format((float)$matches[0], 2, '.', '');
    }

    return $raw;
};

$normalizePhoneNumberField = function ($value): string {
    $raw = trim((string)$value);
    if ($raw === '') {
        return '';
    }
    return preg_replace('/\s+/', '', $raw);
};

$postdata['phone'] = $normalizePhoneNumberField($postdata['phone'] ?? '');
$postdata['sender'] = $normalizePhoneNumberField($postdata['sender'] ?? '');

$previousStatus = momoremits_normalize_status($row->transaction_status ?? null, 'Approved');
$rejectOnly = !empty($postdata['reject_only']);
if ($rejectOnly) {
    $postdata['transaction_status'] = 'Rejected';
}
$postdata['transaction_status'] = momoremits_normalize_status($postdata['transaction_status'] ?? $previousStatus, $previousStatus ?: 'Approved');
$postdata['rejection_reason'] = trim((string)($postdata['rejection_reason'] ?? ''));
$isRejectionRequest = $rejectOnly || (
    in_array($postdata['transaction_status'], ['Declined', 'Rejected'], true)
    && !in_array($previousStatus, ['Declined', 'Rejected'], true)
);

if ($isRejectionRequest && !user_can('is_admin')) {
    $accessDeniedMessage = 'Access denied. Only administrators can reject MoMoRemit transactions.';
    $momoremit->errors['transaction_status'] = $accessDeniedMessage;
    $respondToRejectRequest(false, $accessDeniedMessage, 403);
    set_value('errors', array_merge($momoremit->errors, $req->upload_errors));
    return;
}

$wasApproved = momoremits_status_is_approved($previousStatus);
$willBeApproved = momoremits_status_is_approved($postdata['transaction_status']);
$statusChangedToApproved = !$wasApproved && $willBeApproved;
$statusChangedFromApproved = $wasApproved && !$willBeApproved;

if ($wasApproved) {
    $lockedColumns = [
        'network',
        'bank_name',
        'transaction_type',
        'category',
        'sending_category',
        'phone',
        'sender',
        'sim_type',
        'charging_type',
        'amount_load',
        'amount_payable',
        'amount_sendable',
        'cash_received_amount',
        'change_required',
        'change_denominations',
        'amount',
        'balance_after',
        'transaction_image',
        'receiver',
        'token',
        'id_type',
        'id_number',
        'secret_code',
        'charge',
        'wallet_in_id',
        'wallet_out_id',
    ];

    foreach ($lockedColumns as $column) {
        $postdata[$column] = $row->{$column} ?? ($defaults[$column] ?? '');
    }
}

if (strcasecmp(trim((string)($postdata['transaction_type'] ?? '')), 'Withdrawal') === 0) {
    $withdrawalTargetAmount = (float)($postdata['amount_to_withdraw'] ?? $postdata['amount'] ?? ($row->amount ?? 0));
    if ($withdrawalTargetAmount > 0) {
        $formattedWithdrawalAmount = number_format($withdrawalTargetAmount, 2, '.', '');
        $postdata['amount'] = $formattedWithdrawalAmount;
        $postdata['amount_to_withdraw'] = $formattedWithdrawalAmount;
    }

    $postdata['balance_after'] = $normalizeMoneyField($postdata['balance_after'] ?? ($row->balance_after ?? ''));
    $postdata['charge'] = $normalizeMoneyField($postdata['charge'] ?? ($row->charge ?? ''));
    $postdata['amount'] = $normalizeMoneyField($postdata['amount'] ?? ($row->amount ?? ''));
    $postdata['amount_to_withdraw'] = $normalizeMoneyField($postdata['amount_to_withdraw'] ?? $postdata['amount'] ?? ($row->amount ?? ''));
}

$txTypeNorm = trim((string)($postdata['transaction_type'] ?? ($row->transaction_type ?? '')));
$simTypeNorm = strtolower(trim((string)($postdata['sim_type'] ?? ($row->sim_type ?? ''))));
$categoryNorm = strtolower(trim((string)($postdata['category'] ?? ($row->category ?? ''))));
$categoryNorm = str_replace([' ', '/', '-', '_'], '', $categoryNorm);

if (!$wasApproved && strcasecmp($txTypeNorm, 'Cash in') === 0) {
    foreach (['amount_load','amount_sendable','amount_payable','cash_received_amount','change_required','amount','balance_after','charge'] as $moneyField) {
        $postdata[$moneyField] = $normalizeMoneyField($postdata[$moneyField] ?? ($row->{$moneyField} ?? ''));
    }
    if (function_exists('momoremits_apply_merchant_cashin_sending_amounts')) {
        $postdata = momoremits_apply_merchant_cashin_sending_amounts($postdata);
    }
}

if ($txTypeNorm === 'Cash in' && in_array($simTypeNorm, ['subscriber', 'agent'], true) && $categoryNorm === 'inout') {
    $amountPayable = (float)($postdata['amount_payable'] ?? ($row->amount_payable ?? 0));
    $amountSendable = (float)($postdata['amount_sendable'] ?? ($row->amount_sendable ?? 0));
    $savedCharge = round(max(0, $amountPayable - $amountSendable), 2);
    if ($savedCharge > 0) {
        $postdata['charge'] = number_format($savedCharge, 2, '.', '');
    }
}

if ($txTypeNorm === 'CIT') {
    $citAmount = (float)($postdata['amount_to_withdraw'] ?? $postdata['amount'] ?? ($row->amount_to_withdraw ?? $row->amount ?? 0));
    if ($citAmount > 0) {
        $formattedCitAmount = number_format($citAmount, 2, '.', '');
        $postdata['amount_to_withdraw'] = $formattedCitAmount;
        $postdata['amount_to_receive'] = $formattedCitAmount;
        $postdata['amount_payable'] = $formattedCitAmount;
    }
    $postdata['charge'] = '0.00';
    $postdata['amount_sendable'] = '0.00';
}

$csrf = csrf_verify($postdata);
if (!$csrf) {
    $momoremit->errors['network'] = "Form expired!";
    $respondToRejectRequest(false, $momoremit->errors['network']);
    set_value('errors', array_merge($momoremit->errors, $req->upload_errors));
    return;
}

if ($rejectOnly) {
    if ($postdata['rejection_reason'] === '') {
        $momoremit->errors['rejection_reason'] = 'A rejection reason is required.';
    }
} else {
    $momoremit->validate_update($postdata);
}

if (!$rejectOnly && in_array($postdata['transaction_status'], ['Declined', 'Rejected'], true) && $postdata['rejection_reason'] === '') {
    $momoremit->errors['rejection_reason'] = 'A rejection reason is required when declining or rejecting a transaction.';
}

if ($willBeApproved) {
    $postdata['rejection_reason'] = '';
}

if (!empty($momoremit->errors)) {
    $respondToRejectRequest(false, implode(' ', array_values($momoremit->errors)));
    set_value('errors', array_merge($momoremit->errors, $req->upload_errors));
    return;
}

if (!user_can('edit_momoremit')) {
    $momoremit->errors['network'] = 'Access denied. You do not have permission for this action.';
    $respondToRejectRequest(false, $momoremit->errors['network']);
    set_value('errors', array_merge($momoremit->errors, $req->upload_errors));
    return;
}

unset($postdata['balance_after_display']);
$postdata['date_updated'] = date("Y-m-d H:i:s");

$session = get_session();
$username = !empty($session->id)
    ? trim(($session->first_name ?? '') . ' ' . ($session->last_name ?? ''))
    : trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
$user_id = (int)($session->id ?? ($row->id ?? 0));
$postdata['updated_by'] = $user_id;

$cashdrawerClosingBalance = null;
$ecashClosingBalance = null;
$dbMutation = new \Core\Database;

try {
    if (!$dbMutation->beginTransaction()) {
        throw new \RuntimeException(
            'Unable to start an atomic MoMoRemit update.' .
            (!empty($dbMutation->error) ? ' ' . $dbMutation->error : '')
        );
    }

    $linkedTableUpdate = [];
    if ($statusChangedToApproved) {
        $drawerOwnerId = (int)($row->created_by ?? $user_id ?? 0);
        if ($drawerOwnerId <= 0) {
            $drawerOwnerId = (int)($session->id ?? 0);
        }

        $drawer_id = 0;
        $drawerUserId = $drawerOwnerId;
        if ($drawerOwnerId > 0) {
            $drawerContext = momoremits_find_drawer_context($drawerOwnerId, 0);
            if (!empty($drawerContext)) {
                $drawer_id = (int)($drawerContext['drawer_id'] ?? 0);
                $drawerUserId = (int)($drawerContext['drawer_user_id'] ?? $drawerOwnerId);
            }
        }

        $txType = trim((string)($postdata['transaction_type'] ?? ($row->transaction_type ?? '')));
        if ($txType === 'Cash in' && $drawer_id <= 0) {
            throw new \RuntimeException('Cashdrawer not found for this user, so e-cash balance cannot be validated.');
        }

        try {
            $network = trim((string)($postdata['network'] ?? ($row->network ?? '')));
            $amountSendableApproval = (float)($postdata['amount_sendable'] ?? ($row->amount_sendable ?? 0));
            if ($txType === 'Cash in' && $drawer_id > 0 && $network !== '') {
                if ($amountSendableApproval <= 0) {
                    throw new \RuntimeException('Amount to send is required for Cash in.');
                }
                $ecashDebitRequired = function_exists('momoremits_cashin_ecash_debit_amount')
                    ? momoremits_cashin_ecash_debit_amount($postdata)
                    : $amountSendableApproval;

                $resolvedKey = momoremits_resolve_ecash_channel_key(
                    $drawer_id,
                    $drawerUserId,
                    $network,
                    (string)($postdata['bank_name'] ?? ($row->bank_name ?? '')),
                    (string)($postdata['sim_type'] ?? ($row->sim_type ?? ''))
                );
                if ($resolvedKey === null) {
                    throw new \RuntimeException('Current balance for this e-cash/bank channel could not be found.');
                }

                $dbBalGuard = new \Core\Database;
                $rows = $dbBalGuard->query(
                    "SELECT closing_balance
                     FROM drawer_balances
                     WHERE drawer_id = :drawer_id
                       AND user_id = :user_id
                       AND channel_type = 'ecash'
                       AND channel_key = :channel_key
                     ORDER BY id DESC
                     LIMIT 1",
                    [
                        'drawer_id' => $drawer_id,
                        'user_id' => $drawerUserId,
                        'channel_key' => $resolvedKey,
                    ]
                );

                if (empty($rows)) {
                    throw new \RuntimeException('Current balance for channel ' . $resolvedKey . ' could not be found.');
                }

                $currentBalance = (float)($rows[0]->closing_balance ?? 0);
                $projected = $currentBalance - $ecashDebitRequired;
                $postdata['balance_after'] = number_format($projected, 2, '.', '');
                $postdata['amount_sendable'] = number_format($amountSendableApproval, 2, '.', '');

                if ($projected < -0.00001) {
                    throw new \RuntimeException(
                        'Insufficient e-cash/bank balance in channel ' . $resolvedKey .
                        ' (available ' . number_format($currentBalance, 2) .
                        ', required ' . number_format($ecashDebitRequired, 2) . ')'
                    );
                }
            }
        } catch (\Throwable $e) {
            $momoremit->errors['amount'] = $e->getMessage();
            throw $e;
        }

        if ($drawer_id > 0) {
            try {
                $dbBal = new \Core\Database;
                if ($dbBal->table_exists(['drawer_balances'])) {
                    $transactionAmount = (float)($postdata['amount'] ?? $row->amount ?? 0);
                    if ($transactionAmount > 0) {
                        $rowsBal = $dbBal->query(
                            "SELECT *
                             FROM drawer_balances
                             WHERE drawer_id = :drawer_id
                               AND user_id = :user_id
                               AND channel_type = 'cash'
                               AND channel_key = 'CASH_DRAWER'
                             LIMIT 1",
                            [
                                'drawer_id' => $drawer_id,
                                'user_id' => $drawerUserId,
                            ]
                        );

                        $now = date('Y-m-d H:i:s');
                        if (empty($rowsBal)) {
                            $hdr = $dbBal->query("SELECT amount FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawer_id]);
                            $opening = !empty($hdr) ? (float)($hdr[0]->amount ?? 0) : 0.0;
                            $credit = 0.0;
                            $debit = $transactionAmount;
                            $closing = $opening - $debit;
                            $dbBal->query(
                                "INSERT INTO drawer_balances
                                 (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status)
                                 VALUES
                                 (:drawer_id, :user_id, 'cash', 'CASH_DRAWER', :opening, :credit, :debit, :closing, :date_created, :trans_id, :trans_status)",
                                [
                                    'drawer_id' => $drawer_id,
                                    'user_id' => $drawerUserId,
                                    'opening' => $opening,
                                    'credit' => $credit,
                                    'debit' => $debit,
                                    'closing' => $closing,
                                    'date_created' => $now,
                                    'trans_id' => $row->id,
                                    'trans_status' => $postdata['transaction_status'],
                                ]
                            );
                        } else {
                            $rowBal = $rowsBal[0];
                            $opening = (float)($rowBal->opening_balance ?? 0);
                            $credit = (float)($rowBal->credit ?? 0);
                            $debit = (float)($rowBal->debit ?? 0) + $transactionAmount;
                            $closing = $opening + $credit - $debit;
                            $paramsCashUpdate = [
                                'debit' => $debit,
                                'closing' => $closing,
                                'trans_id' => $row->id,
                                'trans_status' => $postdata['transaction_status'],
                                'id' => $rowBal->id,
                            ];
                            $cashUpdateSql = "UPDATE drawer_balances
                                 SET debit = :debit,
                                     closing_balance = :closing,
                                     trans_id = :trans_id,
                                     trans_status = :trans_status";
                            if (function_exists('momoremits_drawer_balances_has_date_updated') && momoremits_drawer_balances_has_date_updated()) {
                                $cashUpdateSql .= ",
                                     date_updated = :updated";
                                $paramsCashUpdate['updated'] = $now;
                            }
                            $cashUpdateSql .= "
                                 WHERE id = :id";
                            $dbBal->query($cashUpdateSql, $paramsCashUpdate);
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Keep legacy behavior: do not block if aggregate cash row update fails.
            }

            try {
                $dbBal = new \Core\Database;
                if ($dbBal->table_exists(['drawer_balances'])) {
                    $network = $postdata['network'] ?? $row->network ?? '';
                    $ecashAmount = function_exists('momoremits_cashin_ecash_debit_amount')
                        ? momoremits_cashin_ecash_debit_amount($postdata)
                        : (float)($postdata['amount_sendable'] ?? ($row->amount_sendable ?? 0));
                    if ($ecashAmount > 0 && !empty($network)) {
                        $channelKey = momoremits_resolve_ecash_channel_key(
                            $drawer_id,
                            $drawerUserId,
                            (string)$network,
                            (string)($postdata['bank_name'] ?? ($row->bank_name ?? '')),
                            (string)($postdata['sim_type'] ?? ($row->sim_type ?? ''))
                        );
                        if ($channelKey !== null) {
                            $rowsBal = $dbBal->query(
                                "SELECT *
                                 FROM drawer_balances
                                 WHERE drawer_id = :drawer_id
                                   AND user_id = :user_id
                                   AND channel_type = 'ecash'
                                   AND channel_key = :channel_key
                                 LIMIT 1",
                                [
                                    'drawer_id' => $drawer_id,
                                    'user_id' => $drawerUserId,
                                    'channel_key' => $channelKey,
                                ]
                            );

                            $now = date('Y-m-d H:i:s');
                            if (empty($rowsBal)) {
                                $opening = 0.0;
                                $credit = 0.0;
                                $debit = $ecashAmount;
                                $closing = $opening - $debit;
                                $ecashClosingBalance = $closing;
                                $dbBal->query(
                                    "INSERT INTO drawer_balances
                                     (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status)
                                     VALUES
                                     (:drawer_id, :user_id, 'ecash', :channel_key, :opening, :credit, :debit, :closing, :date_created, :trans_id, :trans_status)",
                                    [
                                        'drawer_id' => $drawer_id,
                                        'user_id' => $drawerUserId,
                                        'channel_key' => $channelKey,
                                        'opening' => $opening,
                                        'credit' => $credit,
                                        'debit' => $debit,
                                        'closing' => $closing,
                                        'date_created' => $now,
                                        'trans_id' => $row->id,
                                        'trans_status' => $postdata['transaction_status'],
                                    ]
                                );
                            } else {
                                $rowBal = $rowsBal[0];
                                $opening = (float)($rowBal->opening_balance ?? 0);
                                $credit = (float)($rowBal->credit ?? 0);
                                $debit = (float)($rowBal->debit ?? 0) + $ecashAmount;
                                $closing = $opening + $credit - $debit;
                                $ecashClosingBalance = $closing;
                                $paramsEcashUpdate = [
                                    'debit' => $debit,
                                    'closing' => $closing,
                                    'trans_id' => $row->id,
                                    'trans_status' => $postdata['transaction_status'],
                                    'id' => $rowBal->id,
                                ];
                                $ecashUpdateSql = "UPDATE drawer_balances
                                     SET debit = :debit,
                                         closing_balance = :closing,
                                         trans_id = :trans_id,
                                         trans_status = :trans_status";
                                if (function_exists('momoremits_drawer_balances_has_date_updated') && momoremits_drawer_balances_has_date_updated()) {
                                    $ecashUpdateSql .= ",
                                         date_updated = :updated";
                                    $paramsEcashUpdate['updated'] = $now;
                                }
                                $ecashUpdateSql .= "
                                     WHERE id = :id";
                                $dbBal->query($ecashUpdateSql, $paramsEcashUpdate);
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Keep legacy behavior: do not block if aggregate e-cash row update fails.
            }
        }
    }

    if ($statusChangedFromApproved) {
        $reversal = momoremits_reverse_approval_effects((object)array_merge((array)$row, $postdata), $postdata['transaction_status']);
        if (empty($reversal['ok'])) {
            $momoremit->errors['transaction_status'] = $reversal['message'] ?: 'Unable to reverse the approved transaction impacts.';
            throw new \RuntimeException($momoremit->errors['transaction_status']);
        }

        $cashdrawerClosingBalance = $reversal['cashdrawer_closing_balance'] ?? null;
        $ecashClosingBalance = $reversal['ecash_closing_balance'] ?? null;
    }

    if (!$willBeApproved) {
        $postdata['balance_after'] = $row->balance_after ?? '';
    }

    if ($ecashClosingBalance !== null && $willBeApproved) {
        $postdata['balance_after'] = number_format($ecashClosingBalance, 2, '.', '');
    }

    unset($postdata['id'], $postdata['reject_only']);
    $momoremit->update($row->id, $postdata);
    if ($momoremit->has_error || !empty($momoremit->error)) {
        throw new \RuntimeException(
            'Unable to update this MoMoRemit transaction on the server.' .
            (!empty($momoremit->error) ? ' ' . $momoremit->error : '')
        );
    }

    if ($previousStatus !== $postdata['transaction_status']) {
        $isRejected = in_array($postdata['transaction_status'], ['Declined', 'Rejected'], true);
        $linkedTableUpdate = [
            'deleted' => $isRejected ? 1 : 0,
            'date_deleted' => $isRejected ? $postdata['date_updated'] : null,
            'updated_by' => $user_id,
            'date_updated' => $postdata['date_updated'],
        ];

        if ($isRejected && function_exists('momoremits_table_exists') && momoremits_table_exists('cashout_commissions')) {
            $cashoutCommissionDb = new \Core\Database;
            $cashoutCommissionDb->query(
                "UPDATE cashout_commissions
                 SET status = 'CANCELLED', date_updated = :date_updated
                 WHERE transaction_id = :transaction_id
                   AND status = 'PENDING'
                   AND deleted = 0",
                [
                    'date_updated' => $postdata['date_updated'],
                    'transaction_id' => $row->id,
                ]
            );
            if ($cashoutCommissionDb->has_error) {
                throw new \RuntimeException(
                    'Unable to cancel the linked MoMoRemit cashout commission.' .
                    (!empty($cashoutCommissionDb->error) ? ' ' . $cashoutCommissionDb->error : '')
                );
            }
        }
    }

    $chargeValue = (float)($postdata['charge'] ?? ($row->charge ?? 0));
    $networkForCommission = trim((string)($postdata['network'] ?? ($row->network ?? '')));
    $transactionTypeForCommission = trim((string)($postdata['transaction_type'] ?? ($row->transaction_type ?? '')));
    $sendingCategoryForCommission = trim((string)($postdata['sending_category'] ?? ($row->sending_category ?? '')));
    $statusForCommission = trim((string)($postdata['transaction_status'] ?? ($row->transaction_status ?? '')));
    $networkUpper = strtoupper($networkForCommission);
    $commissionAmount = function_exists('momoremits_calc_charge_commission_amount')
        ? momoremits_calc_charge_commission_amount((object)array_merge((array)$row, $postdata))
        : $chargeValue;

    $commissionDb = new \Core\Database;
    $commissionDb->query(
        "DELETE FROM commissions
         WHERE colletion_period = :period
           AND (commission_type = :commission_type OR commission_type = :commission_type_legacy)
           AND description LIKE :needle",
        [
            'period' => 'INSTANT',
            'commission_type' => 'MOMOREMIT CHARGE',
            'commission_type_legacy' => 'MOMOREMIT CASHIN',
            'needle' => '%| ID ' . ($row->id ?? ''),
        ]
    );
    if ($commissionDb->has_error) {
        throw new \RuntimeException(
            'Unable to reverse the linked MoMoRemit charge commission.' .
            (!empty($commissionDb->error) ? ' ' . $commissionDb->error : '')
        );
    }

    if ($chargeValue > 0 && $commissionAmount > 0 && momoremits_status_is_approved($statusForCommission)) {
        $commissionDescription = 'MOMOREMIT CHARGE | ' .
            ($networkUpper ?: ($postdata['network'] ?? ($row->network ?? ''))) .
            ' | ' . strtoupper((string)$transactionTypeForCommission) .
            ' | ' . strtoupper((string)$sendingCategoryForCommission) .
            ' | ID ' . ($row->id ?? '');

        $commissionInsert = [
            'date_received' => $postdata['date_updated'] ?? date('Y-m-d H:i:s'),
            'colletion_period' => 'INSTANT',
            'network' => $postdata['network'] ?? ($row->network ?? null),
            'commission_type' => 'MOMOREMIT CHARGE',
            'description' => $commissionDescription,
            'pay_to' => $user_id,
            'receiving_wallet' => null,
            'amount' => $commissionAmount,
            'transaction_status' => 'Approved',
            'created_by' => $user_id,
            'updated_by' => null,
            'disabled' => 0,
            'deleted' => 0,
            'date_created' => $postdata['date_updated'] ?? date('Y-m-d H:i:s'),
            'date_updated' => null,
            'date_deleted' => null,
        ];

        $commissionSql = "INSERT INTO commissions (" . implode(',', array_keys($commissionInsert)) . ") VALUES (:" . implode(',:', array_keys($commissionInsert)) . ")";
        $commissionDb->query($commissionSql, $commissionInsert);
        if ($commissionDb->has_error) {
            throw new \RuntimeException(
                'Unable to save the linked MoMoRemit charge commission.' .
                (!empty($commissionDb->error) ? ' ' . $commissionDb->error : '')
            );
        }
    }

    $linkedRecord = null;
    switch ($row->transaction_type) {
        case 'Cash in':
        case 'Sending':
            $linkedRecord = $sending;
            break;

        case 'Withdrawal':
            $linkedRecord = $withdrawal;
            break;

        case 'Deposit':
            $linkedRecord = $deposit;
            break;

        case 'CIT':
            $linkedRecord = $cit;
            break;
    }

    if ($linkedRecord !== null) {
        $linkedUpdateData = $rejectOnly
            ? $linkedTableUpdate
            : array_merge($postdata, $linkedTableUpdate);
        $linkedRecord->update($row->id, $linkedUpdateData);
        if ($linkedRecord->has_error || !empty($linkedRecord->error)) {
            throw new \RuntimeException(
                'Unable to update the linked MoMoRemit transaction record.' .
                (!empty($linkedRecord->error) ? ' ' . $linkedRecord->error : '')
            );
        }
    }

    $description = "$username edited momoremit transaction " . $row->network . ' ' . $row->transaction_type . " with id $row->id";
    $log->insert([
        'table_name' => 'momoremits',
        'action' => 'edited',
        'user_id' => $user_id,
        'user_name' => $username,
        'description' => $description,
        'date_created' => date("Y-m-d H:i:s"),
    ]);

    if (!$dbMutation->commit()) {
        throw new \RuntimeException(
            'Unable to commit the MoMoRemit transaction update.' .
            (!empty($dbMutation->error) ? ' ' . $dbMutation->error : '')
        );
    }

    $respondToRejectRequest(true, 'Transaction rejected successfully.');
    message_success("Transaction with id ($row->id) edited successfully!");
    redirect($admin_route . '/' . $plugin_route);
} catch (\Throwable $e) {
    try {
        if (!$dbMutation->rollBack()) {
            error_log('MOMOREMIT UPDATE ROLLBACK FAILED: ' . $dbMutation->error);
        }
    } catch (\Throwable $rollbackError) {
        error_log('MOMOREMIT UPDATE ROLLBACK FAILED: ' . $rollbackError->getMessage());
    }

    if (empty($momoremit->errors)) {
        $momoremit->errors['amount'] = $e->getMessage() ?: 'Unable to update this MoMoRemit transaction on the server.';
    }

    error_log(
        'MOMOREMIT UPDATE FAILED [' . ($postdata['transaction_type'] ?? ($row->transaction_type ?? 'unknown')) . ']: ' .
        $e->getMessage()
    );

    $respondToRejectRequest(false, $e->getMessage() ?: 'Unable to reject this transaction.');
    set_value('errors', array_merge($momoremit->errors, $req->upload_errors));
}
?>

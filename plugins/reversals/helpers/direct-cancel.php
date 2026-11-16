<?php

if (!function_exists('direct_cancel_send_json')) {
    function direct_cancel_send_json(array $payload, int $status = 200): void
    {
        foreach ([
            'agencybanking_json_response_sent',
            'cfa_json_response_sent',
            'ezwich_json_response_sent',
            'airtimebundle_json_response_sent',
            'payment_services_json_response_sent',
        ] as $responseFlag) {
            $GLOBALS[$responseFlag] = true;
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}

if (!function_exists('direct_cancel_configuration')) {
    function direct_cancel_configuration(string $module): array
    {
        $roleScoped = static function ($row, int $actorId, $session): bool {
            $role = strtolower((string)($session->roles[0] ?? ''));
            return !in_array($role, ['momo agent', 'solo agent'], true)
                || (int)($row->created_by ?? 0) === $actorId;
        };

        $configs = [
            'agencybanking' => [
                'table' => 'agencybankings',
                'view_permission' => 'view_agencybanking',
                'can_cancel' => $roleScoped,
                'reverse' => static function ($row): array {
                    if (!in_array(strtolower(trim((string)($row->transaction_status ?? ''))), ['approved', 'sent'], true)) {
                        return ['ok' => true];
                    }
                    return \Agencybankings\agency_reverse_approved_effects($row, 'Cancelled');
                },
                'log' => static function ($db, $row, int $actorId, string $actorName, string $now): void {
                    direct_cancel_write_log($db, 'agencybankings', $row, $actorId, $actorName, $now, 'agency banking');
                },
            ],
            'cfa' => [
                'table' => 'cfas',
                'view_permission' => 'view_cfa',
                'can_cancel' => static function ($row, int $actorId, $session): bool {
                    if (cfa_user_can_see_all_transactions($session) || (int)($row->created_by ?? 0) === $actorId) {
                        return true;
                    }
                    $db = new \Core\Database;
                    $walletRows = $db->query(
                        'SELECT id FROM wallets WHERE assigned = :user_id AND id IN (:wallet_in_id, :wallet_out_id) LIMIT 1',
                        [
                            'user_id' => $actorId,
                            'wallet_in_id' => (int)($row->wallet_in_id ?? 0),
                            'wallet_out_id' => (int)($row->wallet_out_id ?? 0),
                        ]
                    );
                    if ($db->has_error) {
                        throw new \RuntimeException('Unable to verify CFA transaction visibility: ' . $db->error);
                    }
                    return !empty($walletRows);
                },
                'reverse' => static function ($row, int $actorId, string $now): array {
                    if (strtolower(trim((string)($row->transaction_status ?? ''))) !== 'complete') {
                        return ['ok' => true];
                    }
                    $result = \Cfas\cfa_reverse_transaction_entries((int)$row->id, $actorId, $now);
                    if (empty($result['ok'])) {
                        return $result;
                    }
                    $db = new \Core\Database;
                    $db->query(
                        "DELETE FROM commissions
                         WHERE network = :network
                           AND colletion_period = :period
                           AND commission_type IN ('CFA INSTANT', 'CFA ROUNDING')
                           AND description LIKE :needle",
                        [
                            'network' => 'CFA',
                            'period' => 'INSTANT',
                            'needle' => '%| ID ' . (int)$row->id,
                        ]
                    );
                    return $db->has_error
                        ? ['ok' => false, 'message' => 'Unable to reverse the CFA commission: ' . $db->error]
                        : ['ok' => true];
                },
                'log' => static function ($db, $row, int $actorId, string $actorName, string $now): void {
                    direct_cancel_write_log($db, 'cfas', $row, $actorId, $actorName, $now, 'CFA');
                },
            ],
            'ezwich' => [
                'table' => 'ezwichs',
                'view_permission' => 'view_ezwich',
                'can_cancel' => $roleScoped,
                'reverse' => static function ($row): array {
                    if (strcasecmp(trim((string)($row->transaction_status ?? '')), 'Approved') !== 0) {
                        return ['ok' => true];
                    }
                    return ezwich_reverse_approved_effects($row, 'Cancelled');
                },
                'log' => static function ($db, $row, int $actorId, string $actorName, string $now): void {
                    direct_cancel_write_log($db, 'ezwichs', $row, $actorId, $actorName, $now, 'ezwich');
                },
            ],
            'airtimebundle' => [
                'table' => 'airtimebundles',
                'view_permission' => 'view_momoremit',
                'can_cancel' => $roleScoped,
                'reverse' => static function ($row): array {
                    if (strcasecmp(trim((string)($row->transaction_status ?? '')), 'Sent') !== 0) {
                        return ['ok' => true];
                    }
                    return airtimebundle_reverse_sent_effects($row, 'Cancelled');
                },
                'log' => static function ($db, $row, int $actorId, string $actorName, string $now): void {
                    direct_cancel_write_log($db, 'airtimebundles', $row, $actorId, $actorName, $now, 'airtimebundle');
                },
            ],
            'momoremit' => [
                'table' => 'momoremits',
                'view_permission' => 'view_momoremit',
                'can_cancel' => $roleScoped,
                'reverse' => static function ($row): array {
                    if (strcasecmp(trim((string)($row->transaction_status ?? '')), 'Approved') !== 0) {
                        return ['ok' => true];
                    }
                    return momoremits_reverse_approval_effects($row, 'Cancelled');
                },
                'after_cancel' => static function ($row, int $actorId, string $now): void {
                    $linkedTables = [
                        'Cash in' => 'sendings',
                        'Sending' => 'sendings',
                        'Withdrawal' => 'withdrawals',
                        'Deposit' => 'deposits',
                        'CIT' => 'cits',
                    ];
                    $linkedTable = $linkedTables[(string)($row->transaction_type ?? '')] ?? null;
                    $db = new \Core\Database;
                    if ($linkedTable !== null) {
                        $db->query(
                            "UPDATE {$linkedTable}
                             SET deleted = 1, date_deleted = :now, updated_by = :actor_id, date_updated = :now
                             WHERE id = :id",
                            ['now' => $now, 'actor_id' => $actorId, 'id' => (int)$row->id]
                        );
                        if ($db->has_error) {
                            throw new \RuntimeException('Unable to cancel the linked MoMoRemit record: ' . $db->error);
                        }
                    }
                    if (function_exists('momoremits_table_exists') && momoremits_table_exists('cashout_commissions')) {
                        $cashoutRows = $db->query(
                            "SELECT id, transferred_to_commission_id
                             FROM cashout_commissions
                             WHERE transaction_id = :transaction_id AND deleted = 0
                             FOR UPDATE",
                            ['transaction_id' => (int)$row->id]
                        ) ?: [];
                        if ($db->has_error) {
                            throw new \RuntimeException('Unable to check the linked MoMoRemit cashout commission: ' . $db->error);
                        }
                        foreach ($cashoutRows as $cashoutRow) {
                            $commissionId = (int)($cashoutRow->transferred_to_commission_id ?? 0);
                            if ($commissionId <= 0) {
                                continue;
                            }
                            $commissionRows = $db->query(
                                'SELECT transaction_status FROM commissions WHERE id = :id LIMIT 1 FOR UPDATE',
                                ['id' => $commissionId]
                            ) ?: [];
                            if ($db->has_error) {
                                throw new \RuntimeException('Unable to check the linked MoMoRemit commission status: ' . $db->error);
                            }
                            if (strtolower(trim((string)($commissionRows[0]->transaction_status ?? ''))) === 'approved') {
                                throw new \DomainException('An associated cashout commission is already approved. Reverse that commission before cancelling this transaction.', 409);
                            }
                            if (!empty($commissionRows)) {
                                $db->query(
                                    "UPDATE commissions
                                     SET transaction_status = 'Rejected', reject_reason = :reason, updated_by = :actor_id, date_updated = :now
                                     WHERE id = :id",
                                    [
                                        'reason' => 'Source MoMoRemit transaction cancelled',
                                        'actor_id' => $actorId,
                                        'now' => $now,
                                        'id' => $commissionId,
                                    ]
                                );
                                if ($db->has_error) {
                                    throw new \RuntimeException('Unable to cancel the linked MoMoRemit commission: ' . $db->error);
                                }
                            }
                        }
                        $db->query(
                            "UPDATE cashout_commissions
                             SET status = 'CANCELLED', notes = :notes, date_updated = :now
                             WHERE transaction_id = :transaction_id AND deleted = 0",
                            [
                                'notes' => 'Source transaction cancelled by user #' . $actorId,
                                'now' => $now,
                                'transaction_id' => (int)$row->id,
                            ]
                        );
                        if ($db->has_error) {
                            throw new \RuntimeException('Unable to cancel the linked MoMoRemit cashout commission: ' . $db->error);
                        }
                    }
                    $db->query(
                        "DELETE FROM commissions
                         WHERE colletion_period = :period
                           AND commission_type IN (:commission_type, :legacy_type)
                           AND description LIKE :needle",
                        [
                            'period' => 'INSTANT',
                            'commission_type' => 'MOMOREMIT CHARGE',
                            'legacy_type' => 'MOMOREMIT CASHIN',
                            'needle' => '%| ID ' . (int)$row->id,
                        ]
                    );
                    if ($db->has_error) {
                        throw new \RuntimeException('Unable to cancel the linked MoMoRemit charge commission: ' . $db->error);
                    }
                },
                'log' => static function ($db, $row, int $actorId, string $actorName, string $now): void {
                    direct_cancel_write_log($db, 'momoremits', $row, $actorId, $actorName, $now, 'momoremit');
                },
            ],
            'payment_services' => [
                'table' => 'payment_services',
                'view_permission' => 'view_payment_service',
                'can_cancel' => static function ($row, int $actorId): bool {
                    return true;
                },
                'reverse' => static function ($row): array {
                    if (strcasecmp(trim((string)($row->transaction_status ?? '')), 'Approved') !== 0) {
                        return ['ok' => true];
                    }
                    return payment_service_reverse_approved_effects($row, 'Cancelled');
                },
                'log' => static function ($db, $row, int $actorId, string $actorName, string $now): void {
                    direct_cancel_write_log($db, 'payment_services', $row, $actorId, $actorName, $now, 'payment service');
                },
            ],
        ];

        if (!isset($configs[$module])) {
            throw new \InvalidArgumentException('Unsupported transaction cancellation module.');
        }
        return $configs[$module];
    }
}

if (!function_exists('direct_cancel_write_log')) {
    function direct_cancel_write_log(\Core\Database $db, string $table, $row, int $actorId, string $actorName, string $now, string $label): void
    {
        $details = trim((string)($row->network ?? '') . ' ' . (string)($row->transaction_type ?? ''));
        if ($label === 'CFA') {
            $details = (string)($row->transaction_type ?? 'exchange');
        } elseif ($label === 'payment service') {
            $details = trim((string)($row->category ?? '') . ' ' . (string)($row->institution ?? ''));
        } elseif ($label === 'ezwich') {
            $details = trim((string)($row->usn ?? '') . ' ' . (string)($row->transaction_type ?? ''));
        }

        $db->query(
            'INSERT INTO logs (table_name, action, user_id, user_name, description, date_created)
             VALUES (:table_name, :action, :user_id, :user_name, :description, :date_created)',
            [
                'table_name' => $table,
                'action' => 'cancelled',
                'user_id' => $actorId,
                'user_name' => $actorName,
                'description' => $actorName . ' cancelled ' . $label . ' transaction ' . $details . ' with id ' . $row->id,
                'date_created' => $now,
            ]
        );
        if ($db->has_error) {
            throw new \RuntimeException('Unable to save the transaction cancellation audit log: ' . $db->error);
        }
    }
}

if (!function_exists('direct_cancel_handle')) {
    function direct_cancel_handle(array $config): void
    {
        $session = get_session();
        $actorId = (int)($session->id ?? 0);
        if ($actorId <= 0) {
            direct_cancel_send_json(['success' => false, 'message' => 'Login is required to cancel a transaction.'], 401);
        }
        if (!user_can($config['view_permission'])) {
            direct_cancel_send_json(['success' => false, 'message' => 'Access denied.'], 403);
        }
        if (!csrf_verify($_POST)) {
            direct_cancel_send_json(['success' => false, 'message' => 'Security token expired. Refresh the page and try again.'], 403);
        }

        $transactionId = (int)($_POST['id'] ?? 0);
        if ($transactionId <= 0) {
            direct_cancel_send_json(['success' => false, 'message' => 'A valid transaction ID is required.'], 422);
        }

        date_default_timezone_set('Africa/Accra');
        $now = date('Y-m-d H:i:s');
        $db = new \Core\Database;
        try {
            if (!$db->beginTransaction()) {
                throw new \RuntimeException('Unable to start an atomic transaction cancellation.');
            }
            $rows = $db->query(
                'SELECT * FROM ' . $config['table'] . ' WHERE id = :id LIMIT 1 FOR UPDATE',
                ['id' => $transactionId]
            );
            if ($db->has_error) {
                throw new \RuntimeException('Unable to load the transaction: ' . $db->error);
            }
            if (empty($rows)) {
                throw new \DomainException('Transaction not found.', 404);
            }

            $row = $rows[0];
            if ((int)($row->deleted ?? 0) !== 0) {
                throw new \DomainException('Deleted transactions cannot be cancelled.', 422);
            }
            if (substr(trim((string)($row->date_created ?? '')), 0, 10) !== substr($now, 0, 10)) {
                throw new \DomainException('Only transactions created today can be cancelled.', 422);
            }
            if (empty($config['can_cancel']($row, $actorId, $session))) {
                throw new \DomainException('You can only cancel transactions visible to your account.', 403);
            }
            $previousStatus = strtolower(trim((string)($row->transaction_status ?? '')));
            if (in_array($previousStatus, ['cancelled', 'canceled', 'declined', 'rejected', 'reversed'], true)) {
                throw new \DomainException('This transaction has already been cancelled or reversed.', 409);
            }

            $userRows = $db->query(
                'SELECT first_name, last_name FROM users WHERE id = :id LIMIT 1',
                ['id' => $actorId]
            );
            if ($db->has_error) {
                throw new \RuntimeException('Unable to load the cancelling user: ' . $db->error);
            }
            $actorName = trim(($userRows[0]->first_name ?? $session->first_name ?? '') . ' ' . ($userRows[0]->last_name ?? $session->last_name ?? ''));
            if ($actorName === '') {
                $actorName = 'User #' . $actorId;
            }

            $row->updated_by = $actorId;
            $effectResult = $config['reverse']($row, $actorId, $now);
            if (is_array($effectResult) && empty($effectResult['ok'])) {
                throw new \RuntimeException($effectResult['message'] ?: 'Unable to reverse the transaction effects.');
            }
            $db->query(
                'UPDATE ' . $config['table'] . "
                 SET transaction_status = 'Cancelled', updated_by = :actor_id, date_updated = :date_updated
                 WHERE id = :id",
                ['actor_id' => $actorId, 'date_updated' => $now, 'id' => $transactionId]
            );
            if ($db->has_error) {
                throw new \RuntimeException('Unable to save the cancelled transaction status: ' . $db->error);
            }

            if (isset($config['after_cancel'])) {
                $config['after_cancel']($row, $actorId, $now);
            }
            $config['log']($db, $row, $actorId, $actorName, $now);
            if (!$db->commit()) {
                throw new \RuntimeException('Unable to commit the transaction cancellation.');
            }
            direct_cancel_send_json([
                'success' => true,
                'message' => 'Transaction cancelled and its recorded financial effects reversed.',
            ]);
        } catch (\Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $status = $error instanceof \DomainException && $error->getCode() >= 400 && $error->getCode() < 600
                ? (int)$error->getCode()
                : 500;
            if ($status === 500) {
                error_log('DIRECT TRANSACTION CANCELLATION FAILED (' . $config['table'] . ' #' . $transactionId . '): ' . $error->getMessage());
            }
            direct_cancel_send_json(['success' => false, 'message' => $error->getMessage()], $status);
        }
    }
}

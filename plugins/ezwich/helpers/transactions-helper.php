<?php

use Core\Database;

if (!function_exists('ezwich_normalize_status')) {
    function ezwich_normalize_status(?string $status, string $fallback = 'Draft'): string
    {
        $status = trim((string)$status);
        if ($status === '') {
            $status = trim($fallback);
        }

        return match (strtolower($status)) {
            'approved' => 'Approved',
            'pending' => 'Pending',
            'draft' => 'Draft',
            'rejected' => 'Rejected',
            'declined' => 'Declined',
            default => $status !== '' ? ucfirst($status) : 'Draft',
        };
    }
}

if (!function_exists('ezwich_status_is_approved')) {
    function ezwich_status_is_approved(?string $status): bool
    {
        return strcasecmp(ezwich_normalize_status($status), 'Approved') === 0;
    }
}

if (!function_exists('ezwich_status_is_declined')) {
    function ezwich_status_is_declined(?string $status): bool
    {
        return strcasecmp(ezwich_normalize_status($status), 'Declined') === 0;
    }
}

if (!function_exists('ezwich_table_exists')) {
    function ezwich_table_exists(string $tableName): bool
    {
        static $cache = [];

        $tableName = trim($tableName);
        if ($tableName === '') {
            return false;
        }

        if (array_key_exists($tableName, $cache)) {
            return $cache[$tableName];
        }

        $exists = false;
        try {
            $db = new Database();
            $rows = $db->query(
                "SELECT TABLE_NAME
                 FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = :schema_name
                   AND TABLE_NAME = :table_name
                 LIMIT 1",
                [
                    'schema_name' => DB_NAME,
                    'table_name' => $tableName,
                ]
            ) ?: [];
            $exists = !empty($rows);
        } catch (\Throwable $e) {
            $exists = false;
        }

        $cache[$tableName] = $exists;
        return $exists;
    }
}

if (!function_exists('ezwich_db_error_message')) {
    function ezwich_db_error_message(Database $db, string $fallback): string
    {
        $message = trim((string)($db->error ?? ''));
        return $message !== '' ? $message : $fallback;
    }
}

if (!function_exists('ezwich_find_transaction_drawer_context')) {
    function ezwich_find_transaction_drawer_context(object $transaction): ?array
    {
        $transId = (int)($transaction->id ?? 0);
        $creatorId = (int)($transaction->created_by ?? 0);
        $db = new Database();

        if ($transId > 0 && ezwich_table_exists('drawer_denos')) {
            try {
                $rows = $db->query(
                    "SELECT drawer_id, user_id
                     FROM drawer_denos
                     WHERE trans_id = :trans_id
                     ORDER BY id DESC
                     LIMIT 1",
                    ['trans_id' => $transId]
                ) ?: [];
                if (!empty($rows)) {
                    $drawerId = (int)($rows[0]->drawer_id ?? 0);
                    $drawerUserId = (int)($rows[0]->user_id ?? 0);
                    if ($drawerId > 0 && $drawerUserId > 0) {
                        return [
                            'drawer_id' => $drawerId,
                            'drawer_user_id' => $drawerUserId,
                        ];
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        if ($transId > 0 && ezwich_table_exists('drawer_balances')) {
            try {
                $rows = $db->query(
                    "SELECT drawer_id, user_id
                     FROM drawer_balances
                     WHERE trans_id = :trans_id
                     ORDER BY id DESC
                     LIMIT 1",
                    ['trans_id' => $transId]
                ) ?: [];
                if (!empty($rows)) {
                    $drawerId = (int)($rows[0]->drawer_id ?? 0);
                    $drawerUserId = (int)($rows[0]->user_id ?? 0);
                    if ($drawerId > 0 && $drawerUserId > 0) {
                        return [
                            'drawer_id' => $drawerId,
                            'drawer_user_id' => $drawerUserId,
                        ];
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        if ($creatorId <= 0 || !ezwich_table_exists('cashdrawers')) {
            return null;
        }

        $transactionDate = trim((string)($transaction->date_created ?? ''));
        if ($transactionDate !== '') {
            try {
                $rows = $db->query(
                    "SELECT id, user_id
                     FROM cashdrawers
                     WHERE user_id = :user_id
                       AND DATE(date_created) = DATE(:transaction_date)
                     ORDER BY id DESC
                     LIMIT 1",
                    [
                        'user_id' => $creatorId,
                        'transaction_date' => $transactionDate,
                    ]
                ) ?: [];
                if (!empty($rows)) {
                    return [
                        'drawer_id' => (int)($rows[0]->id ?? 0),
                        'drawer_user_id' => (int)($rows[0]->user_id ?? $creatorId),
                    ];
                }
            } catch (\Throwable $e) {
            }
        }

        return null;
    }
}

if (!function_exists('ezwich_resolve_ecash_channel_key')) {
    function ezwich_resolve_ecash_channel_key(int $drawerId, int $drawerUserId, string $networkRaw = 'MTN MOMOPAY'): ?string
    {
        if ($drawerId <= 0 || $drawerUserId <= 0 || !ezwich_table_exists('drawer_balances')) {
            return null;
        }

        $networkUpper = strtoupper(trim($networkRaw));
        if ($networkUpper === '') {
            $networkUpper = 'MTN MOMOPAY';
        }

        $db = new Database();
        $normalizedUnderscore = preg_replace('/[^A-Z0-9]+/i', '_', $networkUpper);
        $normalizedUnderscore = trim((string)$normalizedUnderscore, '_');
        $normalizedHyphen = preg_replace('/[^A-Z0-9]+/i', '-', $networkUpper);
        $normalizedHyphen = trim((string)$normalizedHyphen, '-');

        $keysToTry = array_values(array_unique(array_filter([
            'ECASH_' . $normalizedUnderscore,
            $normalizedUnderscore,
            $normalizedHyphen,
            'ECASH_' . $normalizedHyphen,
            $networkUpper,
            'ECASH_' . str_replace(' ', '_', $networkUpper),
        ])));

        foreach ($keysToTry as $key) {
            $rows = $db->query(
                "SELECT channel_key
                 FROM drawer_balances
                 WHERE drawer_id = :drawer_id
                   AND user_id = :user_id
                   AND channel_type = 'ecash'
                   AND channel_key = :channel_key
                 ORDER BY id DESC
                 LIMIT 1",
                [
                    'drawer_id' => $drawerId,
                    'user_id' => $drawerUserId,
                    'channel_key' => $key,
                ]
            ) ?: [];

            if (!empty($rows) && !empty($rows[0]->channel_key)) {
                return (string)$rows[0]->channel_key;
            }
        }

        return 'ECASH_' . ($normalizedUnderscore !== '' ? $normalizedUnderscore : 'MTN_MOMOPAY');
    }
}

if (!function_exists('ezwich_apply_cashdrawer_delta')) {
    function ezwich_apply_cashdrawer_delta(int $drawerId, int $drawerUserId, float $delta, int $transId, string $transStatus): array
    {
        if ($drawerId <= 0 || $drawerUserId <= 0 || abs($delta) < 0.00001) {
            return [
                'ok' => true,
                'closing_balance' => null,
                'message' => '',
            ];
        }

        if (!ezwich_table_exists('drawer_balances')) {
            return [
                'ok' => false,
                'closing_balance' => null,
                'message' => 'Cashdrawer balance table is not available.',
            ];
        }

        $db = new Database();
        $rows = $db->query(
            "SELECT *
             FROM drawer_balances
             WHERE drawer_id = :drawer_id
               AND user_id = :user_id
               AND channel_type = 'cash'
               AND channel_key = 'CASH_DRAWER'
             LIMIT 1",
            [
                'drawer_id' => $drawerId,
                'user_id' => $drawerUserId,
            ]
        ) ?: [];

        $opening = 0.0;
        $credit = 0.0;
        $debit = 0.0;
        $rowId = 0;
        if (!empty($rows)) {
            $row = $rows[0];
            $rowId = (int)($row->id ?? 0);
            $opening = (float)($row->opening_balance ?? 0);
            $credit = (float)($row->credit ?? 0);
            $debit = (float)($row->debit ?? 0);
        } else {
            $hdr = $db->query(
                "SELECT amount FROM cashdrawers WHERE id = :id LIMIT 1",
                ['id' => $drawerId]
            ) ?: [];
            $opening = !empty($hdr) ? (float)($hdr[0]->amount ?? 0) : 0.0;
        }

        $currentClosing = $opening + $credit - $debit;
        $projectedClosing = $currentClosing + $delta;
        if ($projectedClosing < -0.00001) {
            return [
                'ok' => false,
                'closing_balance' => null,
                'message' => 'Current cashdrawer balance is not enough to reverse this approved transaction.',
            ];
        }

        if ($delta >= 0) {
            $credit += $delta;
        } else {
            $debit += abs($delta);
        }

        $closing = $opening + $credit - $debit;
        $now = date('Y-m-d H:i:s');
        if ($rowId <= 0) {
            $db->query(
                "INSERT INTO drawer_balances
                 (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status)
                 VALUES
                 (:drawer_id, :user_id, 'cash', 'CASH_DRAWER', :opening, :credit, :debit, :closing, :date_created, :trans_id, :trans_status)",
                [
                    'drawer_id' => $drawerId,
                    'user_id' => $drawerUserId,
                    'opening' => $opening,
                    'credit' => $credit,
                    'debit' => $debit,
                    'closing' => $closing,
                    'date_created' => $now,
                    'trans_id' => $transId,
                    'trans_status' => $transStatus,
                ]
            );
        } else {
            $db->query(
                "UPDATE drawer_balances
                 SET credit = :credit,
                     debit = :debit,
                     closing_balance = :closing,
                     trans_id = :trans_id,
                     trans_status = :trans_status
                 WHERE id = :id",
                [
                    'credit' => $credit,
                    'debit' => $debit,
                    'closing' => $closing,
                    'trans_id' => $transId,
                    'trans_status' => $transStatus,
                    'id' => $rowId,
                ]
            );
        }

        if ($db->has_error) {
            return [
                'ok' => false,
                'closing_balance' => null,
                'message' => ezwich_db_error_message($db, 'Cashdrawer reversal could not be saved.'),
            ];
        }

        return [
            'ok' => true,
            'closing_balance' => $closing,
            'message' => '',
        ];
    }
}

if (!function_exists('ezwich_refresh_cashdrawer_balance')) {
    function ezwich_refresh_cashdrawer_balance(int $drawerId, int $drawerUserId, int $transId, string $transStatus): ?float
    {
        if ($drawerId <= 0 || $drawerUserId <= 0 || !ezwich_table_exists('drawer_balances') || !ezwich_table_exists('drawer_denos')) {
            return null;
        }

        $db = new Database();
        $sumRows = $db->query(
            "SELECT COALESCE(SUM(volume), 0) AS total_volume
             FROM drawer_denos
             WHERE drawer_id = :drawer_id
               AND user_id = :user_id",
            [
                'drawer_id' => $drawerId,
                'user_id' => $drawerUserId,
            ]
        ) ?: [];

        $currentClosing = !empty($sumRows) ? (float)($sumRows[0]->total_volume ?? 0) : 0.0;
        $rowsBal = $db->query(
            "SELECT *
             FROM drawer_balances
             WHERE drawer_id = :drawer_id
               AND user_id = :user_id
               AND channel_type = 'cash'
               AND channel_key = 'CASH_DRAWER'
             LIMIT 1",
            [
                'drawer_id' => $drawerId,
                'user_id' => $drawerUserId,
            ]
        ) ?: [];

        $now = date('Y-m-d H:i:s');
        if (empty($rowsBal)) {
            $hdr = $db->query(
                "SELECT amount FROM cashdrawers WHERE id = :id LIMIT 1",
                ['id' => $drawerId]
            ) ?: [];
            $opening = !empty($hdr) ? (float)($hdr[0]->amount ?? 0) : 0.0;
            $delta = $currentClosing - $opening;
            $credit = $delta > 0 ? $delta : 0.0;
            $debit = $delta < 0 ? abs($delta) : 0.0;

            $db->query(
                "INSERT INTO drawer_balances
                 (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status)
                 VALUES
                 (:drawer_id, :user_id, 'cash', 'CASH_DRAWER', :opening, :credit, :debit, :closing, :date_created, :trans_id, :trans_status)",
                [
                    'drawer_id' => $drawerId,
                    'user_id' => $drawerUserId,
                    'opening' => $opening,
                    'credit' => $credit,
                    'debit' => $debit,
                    'closing' => $currentClosing,
                    'date_created' => $now,
                    'trans_id' => $transId,
                    'trans_status' => $transStatus,
                ]
            );

            return $db->has_error ? null : $currentClosing;
        }

        $rowBal = $rowsBal[0];
        $opening = (float)($rowBal->opening_balance ?? 0);
        $delta = $currentClosing - $opening;
        $credit = $delta > 0 ? $delta : 0.0;
        $debit = $delta < 0 ? abs($delta) : 0.0;

        $db->query(
            "UPDATE drawer_balances
             SET credit = :credit,
                 debit = :debit,
                 closing_balance = :closing,
                 trans_id = :trans_id,
                 trans_status = :trans_status
             WHERE id = :id",
            [
                'credit' => $credit,
                'debit' => $debit,
                'closing' => $currentClosing,
                'trans_id' => $transId,
                'trans_status' => $transStatus,
                'id' => $rowBal->id,
            ]
        );

        return $db->has_error ? null : $currentClosing;
    }
}

if (!function_exists('ezwich_reverse_drawer_denos')) {
    function ezwich_reverse_drawer_denos(int $drawerId, int $drawerUserId, int $transId, string $transStatus, int $actorUserId = 0): array
    {
        $result = [
            'ok' => true,
            'had_rows' => false,
            'closing_balance' => null,
            'message' => '',
        ];

        if ($drawerId <= 0 || $drawerUserId <= 0 || $transId <= 0 || !ezwich_table_exists('drawer_denos')) {
            return $result;
        }

        $db = new Database();
        $rows = $db->query(
            "SELECT denomination, `count`, volume
             FROM drawer_denos
             WHERE drawer_id = :drawer_id
               AND user_id = :user_id
               AND trans_id = :trans_id
             ORDER BY id ASC",
            [
                'drawer_id' => $drawerId,
                'user_id' => $drawerUserId,
                'trans_id' => $transId,
            ]
        ) ?: [];

        if (empty($rows)) {
            return $result;
        }

        $result['had_rows'] = true;
        $requiredDenos = [];
        foreach ($rows as $row) {
            $count = (int)($row->count ?? 0);
            if ($count <= 0) {
                continue;
            }
            $denomination = (float)($row->denomination ?? 0);
            if ($denomination <= 0) {
                continue;
            }
            $denoKey = number_format($denomination, 2, '.', '');
            $requiredDenos[$denoKey] = ($requiredDenos[$denoKey] ?? 0) + $count;
        }

        if (!empty($requiredDenos)) {
            $availableRows = $db->query(
                "SELECT denomination, SUM(`count`) AS total_count
                 FROM drawer_denos
                 WHERE drawer_id = :drawer_id
                   AND user_id = :user_id
                 GROUP BY denomination",
                [
                    'drawer_id' => $drawerId,
                    'user_id' => $drawerUserId,
                ]
            ) ?: [];

            $availableDenos = [];
            foreach ($availableRows as $availableRow) {
                $denoKey = number_format((float)($availableRow->denomination ?? 0), 2, '.', '');
                $availableDenos[$denoKey] = (int)($availableRow->total_count ?? 0);
            }

            $shortages = [];
            foreach ($requiredDenos as $denoKey => $requiredCount) {
                $availableCount = (int)($availableDenos[$denoKey] ?? 0);
                if ($availableCount < $requiredCount) {
                    $shortages[] = 'GHS ' . number_format((float)$denoKey, 2) . ' notes: required ' . $requiredCount . ', available ' . $availableCount;
                }
            }

            if (!empty($shortages)) {
                return [
                    'ok' => false,
                    'had_rows' => true,
                    'closing_balance' => null,
                    'message' => 'Cashdrawer denomination balance is not enough to decline or reject this ezwich transaction. ' . implode(' | ', $shortages),
                ];
            }
        }

        $createdBy = $actorUserId > 0 ? $actorUserId : $drawerUserId;
        $now = date('Y-m-d H:i:s');

        foreach ($rows as $row) {
            $count = (int)($row->count ?? 0);
            $volume = (float)($row->volume ?? 0);
            if ($count === 0 && abs($volume) < 0.00001) {
                continue;
            }

            $db->query(
                "INSERT INTO drawer_denos
                 (drawer_id, user_id, denomination, `count`, volume, date_created, created_by, trans_id, trans_status)
                 VALUES
                 (:drawer_id, :user_id, :denomination, :count, :volume, :date_created, :created_by, :trans_id, :trans_status)",
                [
                    'drawer_id' => $drawerId,
                    'user_id' => $drawerUserId,
                    'denomination' => $row->denomination ?? 0,
                    'count' => -1 * $count,
                    'volume' => -1 * $volume,
                    'date_created' => $now,
                    'created_by' => $createdBy,
                    'trans_id' => $transId,
                    'trans_status' => $transStatus,
                ]
            );

            if ($db->has_error) {
                return [
                    'ok' => false,
                    'had_rows' => true,
                    'closing_balance' => null,
                    'message' => ezwich_db_error_message($db, 'Cashdrawer denomination reversal could not be saved.'),
                ];
            }
        }

        $result['closing_balance'] = ezwich_refresh_cashdrawer_balance($drawerId, $drawerUserId, $transId, $transStatus);
        return $result;
    }
}

if (!function_exists('ezwich_apply_resolved_ecash_delta')) {
    function ezwich_apply_resolved_ecash_delta(int $drawerId, int $drawerUserId, string $channelKey, float $delta, int $transId, string $transStatus): array
    {
        if ($drawerId <= 0 || $drawerUserId <= 0 || abs($delta) < 0.00001) {
            return [
                'ok' => true,
                'closing_balance' => null,
                'message' => '',
            ];
        }

        $channelKey = strtoupper(trim($channelKey));
        if ($channelKey === '') {
            return [
                'ok' => false,
                'closing_balance' => null,
                'message' => 'The original e-cash channel for this transaction could not be resolved.',
            ];
        }

        if (!ezwich_table_exists('drawer_balances')) {
            return [
                'ok' => false,
                'closing_balance' => null,
                'message' => 'Drawer balances table is not available for the e-cash reversal.',
            ];
        }

        $db = new Database();
        $rows = $db->query(
            "SELECT *
             FROM drawer_balances
             WHERE drawer_id = :drawer_id
               AND user_id = :user_id
               AND channel_type = 'ecash'
               AND channel_key = :channel_key
             ORDER BY id DESC
             LIMIT 1",
            [
                'drawer_id' => $drawerId,
                'user_id' => $drawerUserId,
                'channel_key' => $channelKey,
            ]
        ) ?: [];

        $opening = 0.0;
        $credit = 0.0;
        $debit = 0.0;
        $rowId = 0;
        if (!empty($rows)) {
            $row = $rows[0];
            $rowId = (int)($row->id ?? 0);
            $opening = (float)($row->opening_balance ?? 0);
            $credit = (float)($row->credit ?? 0);
            $debit = (float)($row->debit ?? 0);
        } elseif ($delta < 0) {
            return [
                'ok' => false,
                'closing_balance' => null,
                'message' => 'There is no balance for channel ' . $channelKey . ' on the original drawer to reverse this approved transaction.',
            ];
        }

        $currentClosing = $opening + $credit - $debit;
        $projectedClosing = $currentClosing + $delta;
        if ($projectedClosing < -0.00001) {
            return [
                'ok' => false,
                'closing_balance' => null,
                'message' => 'Current balance in channel ' . $channelKey . ' is not enough to reverse this approved transaction.',
            ];
        }

        if ($delta >= 0) {
            $credit += $delta;
        } else {
            $debit += abs($delta);
        }

        $closing = $opening + $credit - $debit;
        $now = date('Y-m-d H:i:s');
        if ($rowId <= 0) {
            $db->query(
                "INSERT INTO drawer_balances
                 (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status)
                 VALUES
                 (:drawer_id, :user_id, 'ecash', :channel_key, :opening, :credit, :debit, :closing, :date_created, :trans_id, :trans_status)",
                [
                    'drawer_id' => $drawerId,
                    'user_id' => $drawerUserId,
                    'channel_key' => $channelKey,
                    'opening' => $opening,
                    'credit' => $credit,
                    'debit' => $debit,
                    'closing' => $closing,
                    'date_created' => $now,
                    'trans_id' => $transId,
                    'trans_status' => $transStatus,
                ]
            );
        } else {
            $db->query(
                "UPDATE drawer_balances
                 SET credit = :credit,
                     debit = :debit,
                     closing_balance = :closing,
                     trans_id = :trans_id,
                     trans_status = :trans_status
                 WHERE id = :id",
                [
                    'credit' => $credit,
                    'debit' => $debit,
                    'closing' => $closing,
                    'trans_id' => $transId,
                    'trans_status' => $transStatus,
                    'id' => $rowId,
                ]
            );
        }

        if ($db->has_error) {
            return [
                'ok' => false,
                'closing_balance' => null,
                'message' => ezwich_db_error_message($db, 'E-cash reversal could not be saved.'),
            ];
        }

        return [
            'ok' => true,
            'closing_balance' => $closing,
            'message' => '',
        ];
    }
}

if (!function_exists('ezwich_apply_ecash_delta')) {
    function ezwich_apply_ecash_delta(int $drawerId, int $drawerUserId, float $delta, int $transId, string $transStatus): array
    {
        if (ezwich_table_exists('drawer_balances')) {
            $db = new Database();
            $normalizedKeySql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(channel_key)), 'ECASH_', ''), 'ECASH-', ''), '_', ''), '-', ''), ' ', '')";
            $rows = $db->query(
                "SELECT drawer_id, user_id, channel_key
                 FROM drawer_balances
                 WHERE trans_id = :trans_id
                   AND channel_type = 'ecash'
                   AND " . $normalizedKeySql . " IN ('MTNMOMOPAY', 'MTNMOMOPAYOUT')
                 ORDER BY id ASC",
                ['trans_id' => $transId]
            );
            if (!empty($db->has_error)) {
                return [
                    'ok' => false,
                    'closing_balance' => null,
                    'message' => 'Unable to resolve the shared MTN MoMoPay balance for this eZwich transaction.',
                ];
            }

            if (!empty($rows)) {
                $contexts = [];
                foreach ($rows as $row) {
                    $contextKey = (int)($row->drawer_id ?? 0) . ':' .
                        (int)($row->user_id ?? 0);
                    $contexts[$contextKey] = [
                        'drawer_id' => (int)($row->drawer_id ?? 0),
                        'drawer_user_id' => (int)($row->user_id ?? 0),
                        'channel_key' => (string)($row->channel_key ?? ''),
                    ];
                }

                $lastResult = null;
                foreach ($contexts as $context) {
                    $result = ezwich_apply_resolved_ecash_delta(
                        $context['drawer_id'],
                        $context['drawer_user_id'],
                        $context['channel_key'],
                        $delta,
                        $transId,
                        $transStatus
                    );
                    if (empty($result['ok'])) {
                        return $result;
                    }
                    $lastResult = $result;
                }

                if ($lastResult !== null) {
                    return $lastResult;
                }
            }

            if (function_exists('Agencybankings\\agency_find_shared_momopay_context')) {
                $dateRows = $db->query(
                    "SELECT DATE(date_created) AS drawer_date FROM cashdrawers WHERE id = :drawer_id LIMIT 1",
                    ['drawer_id' => $drawerId]
                );
                $drawerDate = !empty($dateRows[0]->drawer_date) ? (string)$dateRows[0]->drawer_date : date('Y-m-d');
                $sharedContext = \Agencybankings\agency_find_shared_momopay_context(null, $drawerDate);
                if (!empty($sharedContext)) {
                    $drawerId = (int)($sharedContext['drawer_id'] ?? $drawerId);
                    $drawerUserId = (int)($sharedContext['drawer_user_id'] ?? $drawerUserId);
                }
            }
        }

        $channelKey = ezwich_resolve_ecash_channel_key($drawerId, $drawerUserId, 'MTN MOMOPAY');
        if ($channelKey === null) {
            return [
                'ok' => false,
                'closing_balance' => null,
                'message' => 'The original e-cash channel for this transaction could not be resolved.',
            ];
        }

        return ezwich_apply_resolved_ecash_delta($drawerId, $drawerUserId, $channelKey, $delta, $transId, $transStatus);
    }
}

if (!function_exists('ezwich_delete_charge_commission')) {
    function ezwich_delete_charge_commission(int $transId): bool
    {
        if ($transId <= 0 || !ezwich_table_exists('commissions')) {
            return true;
        }

        $db = new Database();
        $db->query(
            "DELETE FROM commissions
             WHERE commission_type = :commission_type
               AND description LIKE :needle",
            [
                'commission_type' => 'EZWICH CHARGE',
                'needle' => '%| ID ' . $transId,
            ]
        );

        return !$db->has_error;
    }
}

if (!function_exists('ezwich_reverse_approved_effects')) {
    function ezwich_reverse_approved_effects(object $transaction, string $transStatus): array
    {
        $result = [
            'ok' => true,
            'cashdrawer_closing_balance' => null,
            'ecash_closing_balance' => null,
            'message' => '',
        ];

        $transId = (int)($transaction->id ?? 0);
        if ($transId <= 0) {
            return $result;
        }

        $context = ezwich_find_transaction_drawer_context($transaction);
        if (empty($context)) {
            $transactionDateLabel = 'the transaction date';
            $transactionDateRaw = trim((string)($transaction->date_created ?? ''));
            if ($transactionDateRaw !== '') {
                $timestamp = strtotime($transactionDateRaw);
                if ($timestamp !== false) {
                    $transactionDateLabel = date('F j, Y', $timestamp);
                }
            }

            return [
                'ok' => false,
                'cashdrawer_closing_balance' => null,
                'ecash_closing_balance' => null,
                'message' => 'No historical cashdrawer could be resolved for ' . $transactionDateLabel . ' for the user who created this transaction. To keep the records consistent, decline it against that original day\'s cashdrawer.',
            ];
        }

        $drawerId = (int)($context['drawer_id'] ?? 0);
        $drawerUserId = (int)($context['drawer_user_id'] ?? 0);
        $actorUserId = (int)($transaction->updated_by ?? 0);

        $cashAmount = (float)($transaction->amount ?? 0);
        $cashDelta = $cashAmount;
        if (abs($cashDelta) > 0.00001) {
            $cashResult = ezwich_reverse_drawer_denos($drawerId, $drawerUserId, $transId, $transStatus, $actorUserId);
            if (empty($cashResult['ok'])) {
                return [
                    'ok' => false,
                    'cashdrawer_closing_balance' => null,
                    'ecash_closing_balance' => null,
                    'message' => $cashResult['message'] ?: 'Cashdrawer reversal failed.',
                ];
            }

            if (!empty($cashResult['had_rows'])) {
                $result['cashdrawer_closing_balance'] = $cashResult['closing_balance'] ?? null;
            } else {
                $cashFallback = ezwich_apply_cashdrawer_delta($drawerId, $drawerUserId, $cashDelta, $transId, $transStatus);
                if (empty($cashFallback['ok'])) {
                    return [
                        'ok' => false,
                        'cashdrawer_closing_balance' => null,
                        'ecash_closing_balance' => null,
                        'message' => $cashFallback['message'] ?: 'Cashdrawer reversal failed.',
                    ];
                }

                $result['cashdrawer_closing_balance'] = $cashFallback['closing_balance'] ?? null;
            }
        }

        $ecashDelta = -1 * $cashAmount;
        if (abs($ecashDelta) > 0.00001) {
            $ecashResult = ezwich_apply_ecash_delta($drawerId, $drawerUserId, $ecashDelta, $transId, $transStatus);
            if (empty($ecashResult['ok'])) {
                return [
                    'ok' => false,
                    'cashdrawer_closing_balance' => $result['cashdrawer_closing_balance'],
                    'ecash_closing_balance' => null,
                    'message' => $ecashResult['message'] ?: 'E-cash reversal failed.',
                ];
            }

            $result['ecash_closing_balance'] = $ecashResult['closing_balance'] ?? null;
        }

        // Ezwich charges are not commissions; this only cleans up legacy rows if they exist.
        ezwich_delete_charge_commission($transId);

        return $result;
    }
}

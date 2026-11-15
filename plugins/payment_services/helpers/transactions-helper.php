<?php

use Core\Database;

if (!function_exists('payment_service_ensure_runtime_columns')) {
    function payment_service_ensure_runtime_columns(): void
    {
        try {
            $dbSchema = new Database();
            $neededCols = [
                'amount_paid' => 'FLOAT(10,2) DEFAULT 0.00',
                'assigned_to' => 'INT(11) DEFAULT 0',
                'transaction_image' => 'VARCHAR(255) NULL',
                'change_required' => 'FLOAT(10,2) DEFAULT 0.00',
                'change_denominations' => 'LONGTEXT NULL',
                'cash_received_amount' => 'FLOAT(10,2) DEFAULT 0.00',
                'rejection_reason' => 'TEXT NULL',
            ];

            foreach ($neededCols as $col => $definition) {
                $exists = $dbSchema->query(
                    "SELECT COLUMN_NAME
                     FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA = :db
                       AND TABLE_NAME = 'payment_services'
                       AND COLUMN_NAME = :col
                     LIMIT 1",
                    ['db' => DB_NAME, 'col' => $col]
                );

                if (empty($exists)) {
                    try {
                        $dbSchema->query("ALTER TABLE payment_services ADD COLUMN {$col} {$definition}");
                    } catch (\Throwable $e) {
                    }
                }
            }
        } catch (\Throwable $e) {
        }
    }
}

if (!function_exists('payment_service_normalize_status')) {
    function payment_service_normalize_status(?string $status, string $fallback = 'Pending'): string
    {
        $status = trim((string)$status);
        if ($status === '') {
            $status = trim($fallback);
        }

        $normalized = strtolower($status);
        return match ($normalized) {
            'approved' => 'Approved',
            'pending' => 'Pending',
            'draft' => 'Draft',
            'rejected' => 'Rejected',
            'declined' => 'Declined',
            'reversed' => 'Reversed',
            default => $status !== '' ? ucfirst($status) : 'Pending',
        };
    }
}

if (!function_exists('payment_service_status_is_approved')) {
    function payment_service_status_is_approved(?string $status): bool
    {
        $status = trim((string)$status);
        if ($status === '') {
            return false;
        }

        return strcasecmp(payment_service_normalize_status($status, 'Pending'), 'Approved') === 0;
    }
}

if (!function_exists('payment_service_status_is_declined')) {
    function payment_service_status_is_declined(?string $status): bool
    {
        $status = trim((string)$status);
        if ($status === '') {
            return false;
        }

        return in_array(strtolower(payment_service_normalize_status($status, 'Pending')), ['declined', 'reversed'], true);
    }
}

if (!function_exists('payment_service_table_exists')) {
    function payment_service_table_exists(string $tableName): bool
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

if (!function_exists('payment_service_db_error_message')) {
    function payment_service_db_error_message(Database $db, string $fallback): string
    {
        $message = trim((string)($db->error ?? ''));
        return $message !== '' ? $message : $fallback;
    }
}

if (!function_exists('payment_service_find_transaction_drawer_context')) {
    function payment_service_find_transaction_drawer_context(object $transaction): ?array
    {
        $transId = (int)($transaction->id ?? 0);
        $creatorId = (int)($transaction->created_by ?? 0);
        $db = new Database();

        if ($transId > 0 && payment_service_table_exists('drawer_denos')) {
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

        if ($transId > 0 && payment_service_table_exists('drawer_balances')) {
            try {
                $rows = $db->query(
                    "SELECT drawer_id, user_id
                     FROM drawer_balances
                     WHERE trans_id = :trans_id
                       AND channel_type = 'cash'
                       AND channel_key = 'CASH_DRAWER'
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

        if ($creatorId <= 0 || !payment_service_table_exists('cashdrawers')) {
            return null;
        }

        $transactionDate = trim((string)($transaction->date_created ?? ''));
        if ($transactionDate === '') {
            return null;
        }

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

        return null;
    }
}

if (!function_exists('payment_service_resolve_channel_key')) {
    function payment_service_resolve_channel_key(int $drawerId, int $drawerUserId, string $channelLabelRaw, string $paymentMediumRaw = ''): ?string
    {
        if ($drawerId <= 0 || $drawerUserId <= 0) {
            return null;
        }

        $channelRaw = trim((string)$channelLabelRaw);
        if ($channelRaw === '') {
            return null;
        }

        $mediumUpper = strtoupper(trim($paymentMediumRaw));
        $suffix = $mediumUpper === 'BANK' ? '_BANK' : '';
        $channelBase = trim((string)$channelRaw);
        $channelUpper = strtoupper($channelBase);
        $ecashVariants = [];
        if (in_array($channelUpper, ['MTN', 'TELECEL', 'AT'], true)) {
            $ecashVariants = [
                'ECASH_' . $channelUpper,
                'ECASH_' . $channelBase,
                $channelUpper,
                $channelBase,
                $channelUpper . $suffix,
                $channelBase . $suffix,
            ];
        } else {
            $ecashVariants = [
                $channelBase,
                $channelBase . $suffix,
                $channelUpper,
                $channelUpper . $suffix,
                'ECASH_' . $channelBase,
                'ECASH_' . $channelUpper,
                'ECASH_' . $channelBase . $suffix,
                'ECASH_' . $channelUpper . $suffix,
            ];
        }
        $candidates = array_values(array_unique(array_filter($ecashVariants, static function ($value): bool {
            return trim((string)$value) !== '';
        })));

        if (!payment_service_table_exists('drawer_balances')) {
            return $candidates[0] ?? $channelRaw;
        }

        $db = new Database();
        $rows = $db->query(
            "SELECT channel_key
             FROM drawer_balances
             WHERE drawer_id = :drawer_id
               AND user_id = :user_id
             ORDER BY id DESC",
            [
                'drawer_id' => $drawerId,
                'user_id' => $drawerUserId,
            ]
        ) ?: [];

        foreach ($candidates as $candidate) {
            foreach ($rows as $row) {
                $stored = trim((string)($row->channel_key ?? ''));
                if ($stored === '') {
                    continue;
                }

                if (strcasecmp($stored, $candidate) === 0) {
                    return $stored;
                }
            }
        }

        return null;
    }
}

if (!function_exists('payment_service_find_transaction_channel_context')) {
    function payment_service_find_transaction_channel_context(object $transaction): ?array
    {
        $transId = (int)($transaction->id ?? 0);
        $db = new Database();

        if ($transId > 0 && payment_service_table_exists('drawer_balances')) {
            try {
                $rows = $db->query(
                    "SELECT drawer_id, user_id, channel_key
                     FROM drawer_balances
                     WHERE trans_id = :trans_id
                       AND channel_type = 'ecash'
                     ORDER BY id DESC
                     LIMIT 1",
                    ['trans_id' => $transId]
                ) ?: [];
                if (!empty($rows)) {
                    $drawerId = (int)($rows[0]->drawer_id ?? 0);
                    $drawerUserId = (int)($rows[0]->user_id ?? 0);
                    $channelKey = trim((string)($rows[0]->channel_key ?? ''));
                    if ($drawerId > 0 && $drawerUserId > 0 && $channelKey !== '') {
                        return [
                            'drawer_id' => $drawerId,
                            'drawer_user_id' => $drawerUserId,
                            'channel_key' => $channelKey,
                        ];
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        $medium = strtoupper(trim((string)($transaction->payment_medium ?? '')));
        $ownerId = ($medium === 'BANK')
            ? (int)($transaction->assigned_to ?? 0)
            : (int)($transaction->created_by ?? 0);
        if ($ownerId <= 0 || !payment_service_table_exists('cashdrawers')) {
            return null;
        }

        $transactionDate = trim((string)($transaction->date_created ?? ''));
        if ($transactionDate === '') {
            return null;
        }

        try {
            $rows = $db->query(
                "SELECT id, user_id
                 FROM cashdrawers
                 WHERE user_id = :user_id
                   AND DATE(date_created) = DATE(:transaction_date)
                 ORDER BY id DESC
                 LIMIT 1",
                [
                    'user_id' => $ownerId,
                    'transaction_date' => $transactionDate,
                ]
            ) ?: [];
            if (empty($rows)) {
                return null;
            }

            $drawerId = (int)($rows[0]->id ?? 0);
            $drawerUserId = (int)($rows[0]->user_id ?? $ownerId);
            $channelLabel = ($medium === 'BANK')
                ? trim((string)($transaction->bank ?? ''))
                : trim((string)($transaction->network ?? ''));
            $channelKey = payment_service_resolve_channel_key($drawerId, $drawerUserId, $channelLabel, $medium);
            if ($drawerId <= 0 || $drawerUserId <= 0 || $channelKey === null) {
                return null;
            }

            return [
                'drawer_id' => $drawerId,
                'drawer_user_id' => $drawerUserId,
                'channel_key' => $channelKey,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('payment_service_apply_cashdrawer_delta')) {
    function payment_service_apply_cashdrawer_delta(int $drawerId, int $drawerUserId, float $delta, int $transId, string $transStatus): array
    {
        if ($drawerId <= 0 || $drawerUserId <= 0 || abs($delta) < 0.00001) {
            return [
                'ok' => true,
                'closing_balance' => null,
                'message' => '',
            ];
        }

        $db = new Database();
        if (!payment_service_table_exists('drawer_balances')) {
            return [
                'ok' => false,
                'closing_balance' => null,
                'message' => 'Cashdrawer balance table is not available.',
            ];
        }

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
            $hdr = $db->query("SELECT amount FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawerId]) ?: [];
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
                'message' => payment_service_db_error_message($db, 'Cashdrawer reversal could not be saved.'),
            ];
        }

        return [
            'ok' => true,
            'closing_balance' => $closing,
            'message' => '',
        ];
    }
}

if (!function_exists('payment_service_refresh_cashdrawer_balance')) {
    function payment_service_refresh_cashdrawer_balance(int $drawerId, int $drawerUserId, int $transId, string $transStatus): ?float
    {
        if ($drawerId <= 0 || $drawerUserId <= 0) {
            return null;
        }

        $db = new Database();
        if (!payment_service_table_exists('drawer_balances') || !payment_service_table_exists('drawer_denos')) {
            return null;
        }

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
            $hdr = $db->query("SELECT amount FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawerId]) ?: [];
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

if (!function_exists('payment_service_reverse_drawer_denos')) {
    function payment_service_reverse_drawer_denos(int $drawerId, int $drawerUserId, int $transId, string $transStatus, int $actorUserId = 0): array
    {
        $result = [
            'ok' => true,
            'had_rows' => false,
            'closing_balance' => null,
            'message' => '',
        ];

        if ($drawerId <= 0 || $drawerUserId <= 0 || $transId <= 0) {
            return $result;
        }

        $db = new Database();
        if (!payment_service_table_exists('drawer_denos')) {
            return $result;
        }

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
                    'message' => 'Cashdrawer denomination balance is not enough to decline this payment service transaction. ' . implode(' | ', $shortages),
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
                    'message' => payment_service_db_error_message($db, 'Cashdrawer denomination reversal could not be saved.'),
                ];
            }
        }

        $result['closing_balance'] = payment_service_refresh_cashdrawer_balance($drawerId, $drawerUserId, $transId, $transStatus);
        return $result;
    }
}

if (!function_exists('payment_service_apply_resolved_channel_delta')) {
    function payment_service_apply_resolved_channel_delta(int $drawerId, int $drawerUserId, string $channelKey, float $delta, int $transId, string $transStatus): array
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
                'message' => 'The original payment channel for this transaction could not be resolved.',
            ];
        }

        $db = new Database();
        if (!payment_service_table_exists('drawer_balances')) {
            return [
                'ok' => false,
                'closing_balance' => null,
                'message' => 'Drawer balances table is not available for the channel reversal.',
            ];
        }

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
                'message' => payment_service_db_error_message($db, 'Channel reversal could not be saved.'),
            ];
        }

        return [
            'ok' => true,
            'closing_balance' => $closing,
            'message' => '',
        ];
    }
}

if (!function_exists('payment_service_delete_instant_commission')) {
    function payment_service_delete_instant_commission(int $transId): bool
    {
        if ($transId <= 0 || !payment_service_table_exists('commissions')) {
            return true;
        }

        $db = new Database();
        $db->query(
            "DELETE FROM commissions
             WHERE commission_type = :commission_type
               AND description LIKE :needle",
            [
                'commission_type' => 'PAYMENT SERVICES CHARGE',
                'needle' => '%| ID ' . $transId,
            ]
        );

        return !$db->has_error;
    }
}

if (!function_exists('payment_service_reverse_approved_effects')) {
    function payment_service_reverse_approved_effects(object $transaction, string $transStatus): array
    {
        $result = [
            'ok' => true,
            'cashdrawer_closing_balance' => null,
            'channel_closing_balance' => null,
            'message' => '',
        ];

        $transId = (int)($transaction->id ?? 0);
        if ($transId <= 0) {
            return $result;
        }

        $cashContext = payment_service_find_transaction_drawer_context($transaction);
        if (empty($cashContext)) {
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
                'channel_closing_balance' => null,
                'message' => 'No historical cashdrawer could be resolved for ' . $transactionDateLabel . ' for the user who created this transaction. To keep the records consistent, decline it against that original day\'s cashdrawer.',
            ];
        }

        $cashDrawerId = (int)($cashContext['drawer_id'] ?? 0);
        $cashDrawerUserId = (int)($cashContext['drawer_user_id'] ?? 0);
        $actorUserId = (int)($transaction->updated_by ?? 0);
        $cashDelta = -1 * (float)($transaction->amount ?? 0);

        if (abs($cashDelta) > 0.00001) {
            $cashResult = payment_service_reverse_drawer_denos($cashDrawerId, $cashDrawerUserId, $transId, $transStatus, $actorUserId);
            if (empty($cashResult['ok'])) {
                return [
                    'ok' => false,
                    'cashdrawer_closing_balance' => null,
                    'channel_closing_balance' => null,
                    'message' => $cashResult['message'] ?: 'Cashdrawer reversal failed.',
                ];
            }

            if (!empty($cashResult['had_rows'])) {
                $result['cashdrawer_closing_balance'] = $cashResult['closing_balance'] ?? null;
            } else {
                $cashFallback = payment_service_apply_cashdrawer_delta($cashDrawerId, $cashDrawerUserId, $cashDelta, $transId, $transStatus);
                if (empty($cashFallback['ok'])) {
                    return [
                        'ok' => false,
                        'cashdrawer_closing_balance' => null,
                        'channel_closing_balance' => null,
                        'message' => $cashFallback['message'] ?: 'Cashdrawer reversal failed.',
                    ];
                }

                $result['cashdrawer_closing_balance'] = $cashFallback['closing_balance'] ?? null;
            }
        }

        $channelDelta = (float)($transaction->amount ?? 0);
        if (abs($channelDelta) > 0.00001) {
            $channelContext = payment_service_find_transaction_channel_context($transaction);
            if (empty($channelContext)) {
                return [
                    'ok' => false,
                    'cashdrawer_closing_balance' => $result['cashdrawer_closing_balance'],
                    'channel_closing_balance' => null,
                    'message' => 'The original payment channel for this transaction could not be resolved.',
                ];
            }

            $channelResult = payment_service_apply_resolved_channel_delta(
                (int)($channelContext['drawer_id'] ?? 0),
                (int)($channelContext['drawer_user_id'] ?? 0),
                (string)($channelContext['channel_key'] ?? ''),
                $channelDelta,
                $transId,
                $transStatus
            );
            if (empty($channelResult['ok'])) {
                return [
                    'ok' => false,
                    'cashdrawer_closing_balance' => $result['cashdrawer_closing_balance'],
                    'channel_closing_balance' => null,
                    'message' => $channelResult['message'] ?: 'Payment channel reversal failed.',
                ];
            }

            $result['channel_closing_balance'] = $channelResult['closing_balance'] ?? null;
        }

        if (!payment_service_delete_instant_commission($transId)) {
            return [
                'ok' => false,
                'cashdrawer_closing_balance' => $result['cashdrawer_closing_balance'],
                'channel_closing_balance' => $result['channel_closing_balance'],
                'message' => 'Commission reversal failed for this transaction.',
            ];
        }

        return $result;
    }
}

<?php

use Core\Database;

if (!function_exists('momoremits_ensure_limit_table')) {
    function momoremits_ensure_limit_table(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        try {
            $db = new Database;
            if (!$db->table_exists(['momoremit_limits'])) {
                $db->query("CREATE TABLE momoremit_limits (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    transaction_type VARCHAR(50) NOT NULL,
                    sim_type VARCHAR(50) NOT NULL,
                    network VARCHAR(100) NOT NULL,
                    max_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                    active TINYINT(1) NOT NULL DEFAULT 1,
                    created_by INT UNSIGNED NULL,
                    date_created DATETIME NULL,
                    date_updated DATETIME NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY momoremit_limit_key (transaction_type, sim_type, network),
                    KEY momoremit_limit_active (active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            }
        } catch (\Throwable $e) {
            // Validation remains permissive if the optional limits table cannot be created.
        }

        $ensured = true;
    }
}

if (!function_exists('momoremits_limit_key')) {
    function momoremits_limit_key($value): string
    {
        return strtolower(trim((string)$value));
    }
}

if (!function_exists('momoremits_normalize_limit_type')) {
    function momoremits_normalize_limit_type($value): string
    {
        $value = momoremits_limit_key($value);
        return match ($value) {
            'cashin', 'cash in', 'deposit' => 'cashin',
            'withdrawal', 'withdraw' => 'withdrawal',
            'cit' => 'cit',
            default => $value,
        };
    }
}

if (!function_exists('momoremits_find_transaction_limit')) {
    function momoremits_find_transaction_limit($transactionType, $simType, $network): ?float
    {
        momoremits_ensure_limit_table();
        try {
            $db = new Database;
            if (!$db->table_exists(['momoremit_limits'])) {
                return null;
            }
                $normalizedType = momoremits_normalize_limit_type($transactionType);
                $params = [
                    'transaction_type' => $normalizedType,
                    'network' => momoremits_limit_key($network),
                ];
                $simSql = '';
                if (!in_array($normalizedType, ['withdrawal', 'cit'], true)) {
                    $simSql = ' AND sim_type = :sim_type';
                    $params['sim_type'] = momoremits_limit_key($simType);
                }
                $rows = $db->query(
                    "SELECT max_amount FROM momoremit_limits
                     WHERE active = 1 AND transaction_type = :transaction_type
                       AND network = :network{$simSql}
                     ORDER BY id DESC LIMIT 1",
                    $params
                ) ?: [];
            if (empty($rows)) {
                return null;
            }
            return max(0.0, (float)($rows[0]->max_amount ?? 0));
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('momoremits_ensure_runtime_columns')) {
    function momoremits_ensure_runtime_columns(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $ensured = true;

        try {
            $db = new Database();
            $rows = $db->query("SHOW COLUMNS FROM momoremits") ?: [];
            if (empty($rows)) {
                return;
            }

            $existing = [];
            $existingTypes = [];
            foreach ($rows as $row) {
                if (!is_object($row) || empty($row->Field)) {
                    continue;
                }
                $existing[strtolower((string)$row->Field)] = true;
                $existingTypes[strtolower((string)$row->Field)] = strtolower((string)($row->Type ?? ''));
            }

            $tables = [
                'momoremits' => [
                    'sender' => 'VARCHAR(50) NULL',
                    'bank_name' => 'VARCHAR(120) NULL',
                    'sim_type' => 'VARCHAR(50) NULL',
                    'charging_type' => 'VARCHAR(50) NULL',
                    'amount_load' => 'FLOAT(10,2) DEFAULT 0.00',
                    'amount_sendable' => 'FLOAT(10,2) DEFAULT 0.00',
                    'amount_payable' => 'FLOAT(10,2) DEFAULT 0.00',
                    'cash_received_amount' => 'FLOAT(10,2) DEFAULT 0.00',
                    'change_required' => 'FLOAT(10,2) DEFAULT 0.00',
                    'change_denominations' => 'LONGTEXT NULL',
                    'transaction_image' => 'VARCHAR(512) NULL',
                    'transaction_status' => 'VARCHAR(50) NULL',
                    'rejection_reason' => 'TEXT NULL',
                ],
                'sendings' => [
                    'sender' => 'VARCHAR(50) NULL',
                ],
            ];

            foreach ($tables as $table => $required) {
                $rows = $db->query("SHOW COLUMNS FROM {$table}") ?: [];
                if (empty($rows)) {
                    continue;
                }

                $existing = [];
                $existingTypes = [];
                foreach ($rows as $row) {
                    if (!is_object($row) || empty($row->Field)) {
                        continue;
                    }
                    $existing[strtolower((string)$row->Field)] = true;
                    $existingTypes[strtolower((string)$row->Field)] = strtolower((string)($row->Type ?? ''));
                }

                $missing = [];
                foreach ($required as $column => $definition) {
                    if (empty($existing[$column])) {
                        $missing[$column] = $definition;
                    }
                }

                $alterParts = [];
                foreach ($missing as $column => $definition) {
                    $alterParts[] = "ADD COLUMN {$column} {$definition}";
                }

                if (!empty($existing['transaction_image']) && $table === 'momoremits') {
                    $type = $existingTypes['transaction_image'] ?? '';
                    if (preg_match('/varchar\((\d+)\)/i', $type, $matches) && (int)$matches[1] < 512) {
                        $alterParts[] = "MODIFY COLUMN transaction_image VARCHAR(512) NULL";
                    }
                }

                if (!empty($existing['change_denominations']) && $table === 'momoremits') {
                    $type = $existingTypes['change_denominations'] ?? '';
                    if ($type !== '' && !str_contains($type, 'text')) {
                        $alterParts[] = "MODIFY COLUMN change_denominations LONGTEXT NULL";
                    }
                }

                if (!empty($alterParts)) {
                    $db->query("ALTER TABLE {$table} " . implode(', ', $alterParts));
                }
            }
        } catch (\Throwable $e) {
            // Keep the runtime lean: if schema repair fails, the save path will
            // still surface the real database error or retry without the column.
        }
    }
}

if (!function_exists('momoremits_normalize_status')) {
    function momoremits_normalize_status(?string $status, string $fallback = 'Approved'): string
    {
        $status = trim((string)$status);
        if ($status === '') {
            $status = trim($fallback);
        }

        $normalized = strtolower($status);
        return match ($normalized) {
            'approved' => 'Approved',
            'declined' => 'Declined',
            'rejected' => 'Rejected',
            'reversed' => 'Reversed',
            'pending' => 'Pending',
            default => $status !== '' ? ucfirst($status) : 'Approved',
        };
    }
}

if (!function_exists('momoremits_round_special_fee')) {
    function momoremits_round_special_fee(float $value): float
    {
        $value = (float)$value;
        if (!is_finite($value) || $value <= 0) {
            return 0.0;
        }

        $whole = floor($value + 1e-9);
        $frac = $value - $whole;
        if ($frac < 0) {
            $frac = 0;
        }
        if ($frac > 0.999999) {
            $frac = 0.999999;
        }
        if ($frac > 0.000001 && $frac < 0.5 - 0.000001) {
            return $whole + 0.5;
        }
        if ($frac > 0.5 + 0.000001) {
            return $whole + 1;
        }
        if (abs($frac - 0.5) <= 0.000001) {
            return $whole + 0.5;
        }

        return $whole;
    }
}

if (!function_exists('momoremits_is_mtn_merchant_cashin')) {
    function momoremits_is_mtn_merchant_cashin($network, $simType, $transactionType, $sendingCategory): bool
    {
        return strtoupper(trim((string)$network)) === 'MTN'
            && strtolower(trim((string)$simType)) === 'merchant'
            && strcasecmp(trim((string)$transactionType), 'Cash in') === 0
            && in_array(strtolower(trim((string)$sendingCategory)), ['deposit', 'sending'], true);
    }
}

if (!function_exists('momoremits_normalize_fee_option')) {
    function momoremits_normalize_fee_option($value): string
    {
        return preg_replace('/[^a-z0-9+\-]+/', '', strtolower(trim((string)$value)));
    }
}

if (!function_exists('momoremits_is_merchant_cashin_sending')) {
    function momoremits_is_merchant_cashin_sending($simType, $transactionType, $sendingCategory): bool
    {
        return strtolower(trim((string)$simType)) === 'merchant'
            && strcasecmp(trim((string)$transactionType), 'Cash in') === 0
            && strcasecmp(trim((string)$sendingCategory), 'Sending') === 0;
    }
}

if (!function_exists('momoremits_calc_merchant_cashin_sending')) {
    function momoremits_calc_merchant_cashin_sending($transaction): array
    {
        $row = is_array($transaction) ? (object)$transaction : (is_object($transaction) ? $transaction : (object)[]);
        $amount = round(max(0, (float)($row->amount_load ?? 0)), 2);
        $category = momoremits_normalize_fee_option($row->category ?? '');
        $chargingType = momoremits_normalize_fee_option($row->charging_type ?? '');

        $inFee = momoremits_round_special_fee($amount * 0.01);
        $outFee = $category === 'inout' ? momoremits_round_special_fee(min($amount * 0.005, 20)) : 0.0;

        $sendBeforeMerchantCharge = $amount;
        $amountPayable = $amount;

        if ($category === 'subtractin') {
            $sendBeforeMerchantCharge = max(0, round($amount - $inFee, 2));
        } elseif ($category === 'onlyin') {
            $amountPayable = round($amount + $inFee, 2);
        } elseif ($category === 'inout') {
            $sendBeforeMerchantCharge = round($amount + $outFee, 2);
            $amountPayable = round($amount + $inFee + $outFee, 2);
        }

        $rawMerchantCharge = round(min($sendBeforeMerchantCharge * 0.005, 25), 2);
        $merchantCharge = momoremits_round_special_fee($rawMerchantCharge);
        if ($chargingType === '-fee') {
            $amountSendable = max(0, round($sendBeforeMerchantCharge - $rawMerchantCharge, 2));
            $ecashDebit = $sendBeforeMerchantCharge;
        } else {
            $amountSendable = $sendBeforeMerchantCharge;
            $amountPayable = round($amountPayable + $merchantCharge, 2);
            $ecashDebit = round($sendBeforeMerchantCharge + $rawMerchantCharge, 2);
        }

        $totalCharge = round($inFee + $outFee + $merchantCharge, 2);

        return [
            'amount_load' => $amount,
            'amount_payable' => round($amountPayable, 2),
            'amount_sendable' => round($amountSendable, 2),
            'charge' => $totalCharge,
            'in_fee' => $inFee,
            'out_fee' => $outFee,
            'merchant_charge' => $merchantCharge,
            'raw_merchant_charge' => $rawMerchantCharge,
            'ecash_debit' => round($ecashDebit, 2),
        ];
    }
}

if (!function_exists('momoremits_apply_merchant_cashin_sending_amounts')) {
    function momoremits_apply_merchant_cashin_sending_amounts(array $data): array
    {
        if (!momoremits_is_merchant_cashin_sending(
            $data['sim_type'] ?? '',
            $data['transaction_type'] ?? '',
            $data['sending_category'] ?? ''
        )) {
            return $data;
        }

        $calc = momoremits_calc_merchant_cashin_sending($data);
        foreach (['amount_load', 'amount_payable', 'amount_sendable', 'charge'] as $field) {
            $data[$field] = number_format((float)$calc[$field], 2, '.', '');
        }

        return $data;
    }
}

if (!function_exists('momoremits_cashin_ecash_debit_amount')) {
    function momoremits_cashin_ecash_debit_amount($transaction): float
    {
        $row = is_array($transaction) ? (object)$transaction : (is_object($transaction) ? $transaction : (object)[]);
        $amountSendable = (float)($row->amount_sendable ?? 0);

        if (momoremits_is_merchant_cashin_sending(
            $row->sim_type ?? '',
            $row->transaction_type ?? '',
            $row->sending_category ?? ''
        )) {
            $calc = momoremits_calc_merchant_cashin_sending($row);
            return round(max(0, (float)$calc['ecash_debit']), 2);
        }

        if (!momoremits_is_mtn_merchant_cashin(
            $row->network ?? '',
            $row->sim_type ?? '',
            $row->transaction_type ?? '',
            $row->sending_category ?? ''
        )) {
            return round(max(0, $amountSendable), 2);
        }

        $amountLoad = (float)($row->amount_load ?? 0);
        $charge = (float)($row->charge ?? 0);
        if (strcasecmp(trim((string)($row->sending_category ?? '')), 'Sending') === 0) {
            $providerFee = momoremits_round_special_fee(min($amountLoad * 0.005, 25));
            $providerFee = (float)number_format($providerFee, 2, '.', '');
            return round(max(0, $amountLoad + min($providerFee, $charge)), 2);
        }

        return round(max(0, $amountLoad + $charge), 2);
    }
}

if (!function_exists('momoremits_calc_charge_commission_amount')) {
    function momoremits_calc_charge_commission_amount($transaction): float
    {
        $row = is_array($transaction) ? (object)$transaction : (is_object($transaction) ? $transaction : (object)[]);
        $chargeValue = (float)($row->charge ?? 0);
        if ($chargeValue <= 0) {
            return 0.0;
        }

        $transactionType = trim((string)($row->transaction_type ?? ''));
        $sendingCategory = trim((string)($row->sending_category ?? ''));
        $isMtnMerchantCashin = momoremits_is_mtn_merchant_cashin(
            $row->network ?? '',
            $row->sim_type ?? '',
            $transactionType,
            $sendingCategory
        );

        if ($isMtnMerchantCashin && strcasecmp($sendingCategory, 'Deposit') === 0) {
            return 0.0;
        }

        if (momoremits_is_merchant_cashin_sending(
            $row->sim_type ?? '',
            $transactionType,
            $sendingCategory
        )) {
            $calc = momoremits_calc_merchant_cashin_sending($row);
            return round(max(0, (float)$calc['in_fee']), 2);
        }

        if (strcasecmp($transactionType, 'Cash in') !== 0 || strcasecmp($sendingCategory, 'Sending') !== 0) {
            return $chargeValue;
        }

        $chargingType = strtolower(trim((string)($row->charging_type ?? '')));
        $category = strtolower(trim((string)($row->category ?? '')));
        $category = str_replace([' ', '/', '-', '_'], '', $category);
        if ($chargingType === '+fee' && $category === 'inout') {
            $amountLoad = (float)($row->amount_load ?? 0);
            if ($amountLoad > 0) {
                $inFee = momoremits_round_special_fee($amountLoad * 0.01);
                $inFee = (float)number_format($inFee, 2, '.', '');
                if ($inFee > 0) {
                    return min($inFee, $chargeValue);
                }
            }
        }

        return $chargeValue;
    }
}

if (!function_exists('momoremits_status_is_approved')) {
    function momoremits_status_is_approved(?string $status): bool
    {
        return strcasecmp(momoremits_normalize_status($status), 'Approved') === 0;
    }
}

if (!function_exists('momoremits_drawer_balances_has_date_updated')) {
    function momoremits_drawer_balances_has_date_updated(): bool
    {
        static $hasColumn = null;
        if ($hasColumn !== null) {
            return $hasColumn;
        }

        $hasColumn = false;
        try {
            $db = new Database();
            $rows = $db->query("SHOW COLUMNS FROM drawer_balances LIKE 'date_updated'") ?: [];
            $hasColumn = !empty($rows);
        } catch (\Throwable $e) {
            $hasColumn = false;
        }

        return $hasColumn;
    }
}

if (!function_exists('momoremits_table_exists')) {
    function momoremits_table_exists(string $tableName): bool
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

if (!function_exists('momoremits_db_error_message')) {
    function momoremits_db_error_message(Database $db, string $fallback): string
    {
        $message = trim((string)($db->error ?? ''));
        return $message !== '' ? $message : $fallback;
    }
}

if (!function_exists('momoremits_find_drawer_context')) {
    function momoremits_find_drawer_context(int $fallbackUserId, int $transId = 0): ?array
    {
        $db = new Database();

        if ($transId > 0 && momoremits_table_exists('drawer_denos')) {
            try {
                $rows = $db->query(
                    "SELECT drawer_id, user_id FROM drawer_denos WHERE trans_id = :trans_id ORDER BY id DESC LIMIT 1",
                    ['trans_id' => $transId]
                );
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
                // Fall through to active/latest drawer lookup.
            }
        }

        if ($fallbackUserId <= 0) {
            return null;
        }

        $today = date('Y-m-d');
        $rows = $db->query(
            "SELECT id, user_id
             FROM cashdrawers
             WHERE user_id = :user_id AND DATE(date_created) = :today
             ORDER BY id DESC
             LIMIT 1",
            [
                'user_id' => $fallbackUserId,
                'today' => $today,
            ]
        );

        if (empty($rows)) {
            $rows = $db->query(
                "SELECT id, user_id
                 FROM cashdrawers
                 WHERE user_id = :user_id
                 ORDER BY date_created DESC, id DESC
                 LIMIT 1",
                ['user_id' => $fallbackUserId]
            );
        }

        if (empty($rows)) {
            return null;
        }

        return [
            'drawer_id' => (int)($rows[0]->id ?? 0),
            'drawer_user_id' => (int)($rows[0]->user_id ?? $fallbackUserId),
        ];
    }
}

if (!function_exists('momoremits_find_today_drawer_context')) {
    function momoremits_find_today_drawer_context(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $db = new Database();
        $today = date('Y-m-d');
        $rows = $db->query(
            "SELECT id, user_id
             FROM cashdrawers
             WHERE user_id = :user_id AND DATE(date_created) = :today
             ORDER BY id DESC
             LIMIT 1",
            [
                'user_id' => $userId,
                'today' => $today,
            ]
        );

        if (empty($rows)) {
            return null;
        }

        return [
            'drawer_id' => (int)($rows[0]->id ?? 0),
            'drawer_user_id' => (int)($rows[0]->user_id ?? $userId),
        ];
    }
}

if (!function_exists('momoremits_find_transaction_drawer_context')) {
    function momoremits_find_transaction_drawer_context(object $transaction): ?array
    {
        $transId = (int)($transaction->id ?? 0);
        $creatorId = (int)($transaction->created_by ?? 0);
        $db = new Database();

        if ($transId > 0 && momoremits_table_exists('drawer_denos')) {
            try {
                $rows = $db->query(
                    "SELECT drawer_id, user_id
                     FROM drawer_denos
                     WHERE trans_id = :trans_id
                     ORDER BY id DESC
                     LIMIT 1",
                    ['trans_id' => $transId]
                );
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
                // Fall through to other historical drawer lookups.
            }
        }

        if ($transId > 0 && momoremits_table_exists('drawer_balances')) {
            try {
                $rows = $db->query(
                    "SELECT drawer_id, user_id
                     FROM drawer_balances
                     WHERE trans_id = :trans_id
                     ORDER BY id DESC
                     LIMIT 1",
                    ['trans_id' => $transId]
                );
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
                // Fall through to transaction-date drawer lookup.
            }
        }

        if ($creatorId <= 0) {
            return null;
        }

        $transactionDate = '';
        $transactionDateRaw = trim((string)($transaction->date_created ?? ''));
        if ($transactionDateRaw !== '') {
            $timestamp = strtotime($transactionDateRaw);
            if ($timestamp !== false) {
                $transactionDate = date('Y-m-d', $timestamp);
            }
        }

        if ($transactionDate === '') {
            return null;
        }

        $rows = $db->query(
            "SELECT id, user_id
             FROM cashdrawers
             WHERE user_id = :user_id
               AND DATE(date_created) = :transaction_date
             ORDER BY id DESC
             LIMIT 1",
            [
                'user_id' => $creatorId,
                'transaction_date' => $transactionDate,
            ]
        );

        if (empty($rows)) {
            return null;
        }

        return [
            'drawer_id' => (int)($rows[0]->id ?? 0),
            'drawer_user_id' => (int)($rows[0]->user_id ?? $creatorId),
        ];
    }
}

if (!function_exists('momoremits_ecash_channel_candidates')) {
    function momoremits_ecash_channel_candidates(string $networkRaw, string $bankName = '', string $simType = ''): array
    {
        $networkRaw = trim($networkRaw);
        if ($networkRaw === '') {
            return [];
        }

        $networkUpper = strtoupper($networkRaw);
        $isMomoPay = in_array($networkUpper, ['MOMOPAY', 'MTN_MOMOPAY', 'MTN-MOMOPAY'], true);
        $networkNormalized = strtoupper(trim((string)preg_replace('/[^A-Z0-9]+/i', '_', $networkUpper), '_'));
        $baseChannelKey = in_array($networkUpper, ['MTN', 'TELECEL', 'AT'], true) ? $networkUpper : $networkNormalized;

        $bankNormalized = strtoupper(trim((string)preg_replace('/[^A-Z0-9]+/i', '_', trim($bankName)), '_'));
        $bankCandidates = [];
        if ($networkUpper === 'GHANAPAY' && strcasecmp(trim($simType), 'Bank') === 0 && $bankNormalized !== '') {
            $bankCandidates = [
                'ECASH_' . $bankNormalized,
                'BANK_' . $bankNormalized,
                $bankNormalized,
            ];
        }

        if ($isMomoPay) {
            $keysToTry = array_values(array_unique([
                'ECASH_MTN_MOMOPAY',
                'MTN_MOMOPAY',
                'MTN-MOMOPAY',
                'ECASH_MTN-MOMOPAY',
                'ECASH_MTN_MOMOPAY_OUT',
                'ECASH_MTN-MOMOPAY-OUT',
            ]));
        } else {
            $keysToTry = array_merge($bankCandidates, [
                $networkUpper,
                $networkNormalized,
                strtoupper(str_replace('_', ' ', $networkNormalized)),
                strtoupper(str_replace('_', '-', $networkNormalized)),
                $baseChannelKey,
                'ECASH_' . $networkUpper,
                'ECASH_' . $networkNormalized,
                'ECASH_' . $baseChannelKey,
                'BANK_' . $networkUpper,
                'BANK_' . $networkNormalized,
                'BANK_' . $baseChannelKey,
            ]);
        }

        return array_values(array_unique($keysToTry));
    }
}

if (!function_exists('momoremits_is_momopay_channel_key')) {
    function momoremits_is_momopay_channel_key(string $channelKey): bool
    {
        $normalized = strtoupper(trim($channelKey));
        $normalized = preg_replace('/^ECASH[_-]*/', '', $normalized);
        return in_array(preg_replace('/[^A-Z0-9]/', '', (string)$normalized), ['MTNMOMOPAY', 'MTNMOMOPAYOUT'], true);
    }
}

if (!function_exists('momoremits_find_shared_momopay_context')) {
    function momoremits_find_shared_momopay_context(int $drawerId, int $drawerUserId, ?int $transId = null): ?array
    {
        $drawerDate = date('Y-m-d');
        if ($drawerId > 0 && momoremits_table_exists('cashdrawers')) {
            $dateRows = (new Database())->query(
                "SELECT DATE(date_created) AS drawer_date FROM cashdrawers WHERE id = :drawer_id LIMIT 1",
                ['drawer_id' => $drawerId]
            );
            if (!empty($dateRows[0]->drawer_date)) {
                $drawerDate = (string)$dateRows[0]->drawer_date;
            }
        }

        if (function_exists('Agencybankings\\agency_find_shared_momopay_context')) {
            return \Agencybankings\agency_find_shared_momopay_context(
                ($transId ?? 0) > 0 ? $transId : null,
                $drawerDate
            );
        }

        if (!momoremits_table_exists('drawer_balances') || !momoremits_table_exists('cashdrawers')) {
            return null;
        }

        $normalizedKeySql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(db.channel_key)), 'ECASH_', ''), 'ECASH-', ''), '_', ''), '-', ''), ' ', '')";
        $params = [];
        $where = [
            "db.channel_type = 'ecash'",
            $normalizedKeySql . " IN ('MTNMOMOPAY', 'MTNMOMOPAYOUT')",
        ];
        if (($transId ?? 0) > 0) {
            $where[] = 'db.trans_id = :trans_id';
            $params['trans_id'] = $transId;
        } else {
            $where[] = 'DATE(cd.date_created) = :drawer_date';
            $params['drawer_date'] = $drawerDate;
        }

        $db = new Database();
        $rows = $db->query(
            "SELECT db.drawer_id, db.user_id AS drawer_user_id, db.channel_key,
                    db.opening_balance, db.credit, db.debit, db.closing_balance, db.trans_id
             FROM drawer_balances AS db
             INNER JOIN cashdrawers AS cd ON cd.id = db.drawer_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY CASE WHEN LOWER(COALESCE(db.trans_status, '')) = 'opening snapshot' THEN 1 ELSE 0 END,
                      db.id DESC
             LIMIT 1",
            $params
        );
        if (!empty($db->has_error)) {
            throw new \RuntimeException('Unable to resolve the shared MTN MoMoPay cashdrawer account.');
        }
        if (empty($rows)) {
            return null;
        }

        $row = $rows[0];
        return [
            'drawer_id' => (int)($row->drawer_id ?? 0),
            'drawer_user_id' => (int)($row->drawer_user_id ?? 0),
            'channel_key' => (string)($row->channel_key ?? ''),
            'closing_balance' => (float)($row->closing_balance ?? (
                (float)($row->opening_balance ?? 0) +
                (float)($row->credit ?? 0) -
                (float)($row->debit ?? 0)
            )),
            'trans_id' => (int)($row->trans_id ?? 0),
        ];
    }
}

if (!function_exists('momoremits_resolve_ecash_channel_key')) {
    function momoremits_resolve_ecash_channel_key(int $drawerId, int $drawerUserId, string $networkRaw, string $bankName = '', string $simType = ''): ?string
    {
        if ($drawerId <= 0 || $drawerUserId <= 0 || !momoremits_table_exists('drawer_balances')) {
            return null;
        }

        $keysToTry = momoremits_ecash_channel_candidates($networkRaw, $bankName, $simType);
        if (empty($keysToTry)) {
            return null;
        }

        if (momoremits_is_momopay_channel_key($keysToTry[0])) {
            $sharedContext = momoremits_find_shared_momopay_context($drawerId, $drawerUserId);
            if (!empty($sharedContext['channel_key'])) {
                return (string)$sharedContext['channel_key'];
            }
        }

        $db = new Database();
        $rows = $db->query(
            "SELECT channel_key
             FROM drawer_balances
             WHERE drawer_id = :drawer_id
               AND user_id = :user_id
               AND channel_type = 'ecash'
             ORDER BY id DESC",
            [
                'drawer_id' => $drawerId,
                'user_id' => $drawerUserId,
            ]
        );
        $availableKeys = [];
        foreach ($rows ?? [] as $row) {
            $channelKey = trim((string)($row->channel_key ?? ''));
            if ($channelKey !== '') {
                $availableKeys[strtoupper($channelKey)] = $channelKey;
            }
        }

        foreach ($keysToTry as $key) {
            $match = $availableKeys[strtoupper(trim($key))] ?? null;
            if ($match !== null) {
                return $match;
            }
        }

        return null;
    }
}

if (!function_exists('momoremits_apply_cashdrawer_delta')) {
    function momoremits_apply_cashdrawer_delta(int $drawerId, int $drawerUserId, float $delta, int $transId, string $transStatus): array
    {
        if ($drawerId <= 0 || $drawerUserId <= 0 || abs($delta) < 0.00001) {
            return [
                'ok' => true,
                'closing_balance' => null,
                'message' => '',
            ];
        }

        $db = new Database();
        if (!momoremits_table_exists('drawer_balances')) {
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
        );

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
            $hdr = $db->query("SELECT amount FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawerId]);
            $opening = !empty($hdr) ? (float)($hdr[0]->amount ?? 0) : 0.0;
        }

        $currentClosing = $opening + $credit - $debit;
        $projectedClosing = $currentClosing + $delta;
        if ($projectedClosing < -0.00001) {
            $required = abs($delta);
            $shortfall = abs($projectedClosing);
            return [
                'ok' => false,
                'closing_balance' => null,
                'message' => 'Decline/reject blocked: the cashdrawer does not have enough cash to reverse this approved transaction. Available: GHS ' . number_format($currentClosing, 2) . '; required reversal: GHS ' . number_format($required, 2) . '; shortfall: GHS ' . number_format($shortfall, 2) . '.',
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
            $params = [
                'credit' => $credit,
                'debit' => $debit,
                'closing' => $closing,
                'trans_id' => $transId,
                'trans_status' => $transStatus,
                'id' => $rowId,
            ];
            $sql = "UPDATE drawer_balances
                SET credit = :credit,
                    debit = :debit,
                    closing_balance = :closing,
                    trans_id = :trans_id,
                    trans_status = :trans_status";
            if (momoremits_drawer_balances_has_date_updated()) {
                $sql .= ",
                    date_updated = :updated";
                $params['updated'] = $now;
            }
            $sql .= "
                WHERE id = :id";
            $db->query($sql, $params);
        }

        if ($db->has_error) {
            return [
                'ok' => false,
                'closing_balance' => null,
                'message' => momoremits_db_error_message($db, 'Cashdrawer reversal could not be saved.'),
            ];
        }

        return [
            'ok' => true,
            'closing_balance' => $closing,
            'message' => '',
        ];
    }
}

if (!function_exists('momoremits_reverse_drawer_denos')) {
    function momoremits_reverse_drawer_denos(int $drawerId, int $drawerUserId, int $transId, string $transStatus, int $actorUserId = 0): array
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
        if (!momoremits_table_exists('drawer_denos')) {
            return $result;
        }

        $rows = $db->query(
            "SELECT denomination, count, volume
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
        );

        if (empty($rows)) {
            return $result;
        }

        $result['had_rows'] = true;
        $originalCashEffect = 0.0;
        $requiredDenominations = [];
        foreach ($rows as $row) {
            $count = (int)($row->count ?? 0);
            $volume = (float)($row->volume ?? 0);
            $originalCashEffect += $volume;

            if ($count > 0) {
                $denomination = (float)($row->denomination ?? 0);
                if ($denomination > 0) {
                    $denominationKey = rtrim(rtrim(number_format($denomination, 2, '.', ''), '0'), '.');
                    $requiredDenominations[$denominationKey] = ($requiredDenominations[$denominationKey] ?? 0) + $count;
                }
            }
        }

        $reversalCashEffect = -1 * $originalCashEffect;
        if ($reversalCashEffect < -0.00001) {
            if (!empty($requiredDenominations)) {
                $availableRows = $db->query(
                    "SELECT denomination, COALESCE(SUM(count), 0) AS available_count
                     FROM drawer_denos
                     WHERE drawer_id = :drawer_id AND user_id = :user_id
                     GROUP BY denomination",
                    [
                        'drawer_id' => $drawerId,
                        'user_id' => $drawerUserId,
                    ]
                );

                $availableDenominations = [];
                foreach ($availableRows ?? [] as $availableRow) {
                    $denomination = (float)($availableRow->denomination ?? 0);
                    if ($denomination <= 0) {
                        continue;
                    }
                    $denominationKey = rtrim(rtrim(number_format($denomination, 2, '.', ''), '0'), '.');
                    $availableDenominations[$denominationKey] = (int)($availableRow->available_count ?? 0);
                }

                $missingDenominations = [];
                foreach ($requiredDenominations as $denominationKey => $requiredCount) {
                    $availableCount = $availableDenominations[$denominationKey] ?? 0;
                    if ($availableCount < $requiredCount) {
                        $missingDenominations[] = 'GHS ' . number_format((float)$denominationKey, 2) . ' notes: available ' . $availableCount . ', required ' . $requiredCount;
                    }
                }

                if (!empty($missingDenominations)) {
                    return [
                        'ok' => false,
                        'had_rows' => true,
                        'closing_balance' => null,
                        'message' => 'Decline/reject blocked: the cashdrawer does not have the exact denominations needed to reverse this approved transaction. ' . implode('; ', $missingDenominations) . '.',
                    ];
                }
            }

            $sumRows = $db->query(
                "SELECT COALESCE(SUM(volume), 0) AS total_volume
                 FROM drawer_denos
                 WHERE drawer_id = :drawer_id AND user_id = :user_id",
                [
                    'drawer_id' => $drawerId,
                    'user_id' => $drawerUserId,
                ]
            );

            $currentClosing = !empty($sumRows) ? (float)($sumRows[0]->total_volume ?? 0) : 0.0;
            $projectedClosing = $currentClosing + $reversalCashEffect;
            if ($projectedClosing < -0.00001) {
                return [
                    'ok' => false,
                    'had_rows' => true,
                    'closing_balance' => null,
                    'message' => 'Decline/reject blocked: the cashdrawer does not have enough cash to reverse this approved transaction. Available: GHS ' . number_format($currentClosing, 2) . '; required reversal: GHS ' . number_format(abs($reversalCashEffect), 2) . '; shortfall: GHS ' . number_format(abs($projectedClosing), 2) . '.',
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
                 (drawer_id, user_id, denomination, count, volume, date_created, created_by, trans_id, trans_status)
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
                    'message' => momoremits_db_error_message($db, 'Cashdrawer denomination reversal could not be saved.'),
                ];
            }
        }

        $result['closing_balance'] = momoremits_refresh_cashdrawer_balance($drawerId, $drawerUserId, $transId, $transStatus);
        if ($result['closing_balance'] === null) {
            return [
                'ok' => false,
                'had_rows' => true,
                'closing_balance' => null,
                'message' => 'Cashdrawer denomination reversal could not refresh the drawer balance.',
            ];
        }

        return $result;
    }
}

if (!function_exists('momoremits_apply_resolved_ecash_delta')) {
    function momoremits_apply_resolved_ecash_delta(int $drawerId, int $drawerUserId, string $channelKey, float $delta, int $transId, string $transStatus): array
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
                'message' => 'The original e-cash/bank channel for this transaction could not be resolved.',
            ];
        }

        $db = new Database();
        if (!momoremits_table_exists('drawer_balances')) {
            return [
                'ok' => false,
                'closing_balance' => null,
                'message' => 'Drawer balances table is not available for the e-cash reversal.',
            ];
        }

        if (momoremits_is_momopay_channel_key($channelKey)) {
            $sharedContext = momoremits_find_shared_momopay_context($drawerId, $drawerUserId, $transId);
            if (!empty($sharedContext)) {
                $drawerId = (int)($sharedContext['drawer_id'] ?? $drawerId);
                $drawerUserId = (int)($sharedContext['drawer_user_id'] ?? $drawerUserId);
                $channelKey = strtoupper(trim((string)($sharedContext['channel_key'] ?? $channelKey)));
            }
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
        );

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
                'message' => 'Insufficient balance in ' . $channelKey .
                    ': available GHS 0.00; expected debit GHS ' . number_format(abs($delta), 2) .
                    '; shortfall GHS ' . number_format(abs($delta), 2) . '.',
            ];
        }

        $currentClosing = $opening + $credit - $debit;
        $projectedClosing = $currentClosing + $delta;
        if ($projectedClosing < -0.00001) {
            return [
                'ok' => false,
                'closing_balance' => null,
                'message' => 'Insufficient balance in ' . $channelKey .
                    ': available GHS ' . number_format($currentClosing, 2) .
                    '; expected debit GHS ' . number_format(abs($delta), 2) .
                    '; shortfall GHS ' . number_format(abs($projectedClosing), 2) . '.',
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
            $params = [
                'credit' => $credit,
                'debit' => $debit,
                'closing' => $closing,
                'trans_id' => $transId,
                'trans_status' => $transStatus,
                'id' => $rowId,
            ];
            $sql = "UPDATE drawer_balances
                SET credit = :credit,
                    debit = :debit,
                    closing_balance = :closing,
                    trans_id = :trans_id,
                    trans_status = :trans_status";
            if (momoremits_drawer_balances_has_date_updated()) {
                $sql .= ",
                    date_updated = :updated";
                $params['updated'] = $now;
            }
            $sql .= "
                WHERE id = :id";
            $db->query($sql, $params);
        }

        if ($db->has_error) {
            return [
                'ok' => false,
                'closing_balance' => null,
                'message' => momoremits_db_error_message($db, 'E-cash reversal could not be saved.'),
            ];
        }

        return [
            'ok' => true,
            'closing_balance' => $closing,
            'message' => '',
        ];
    }
}

if (!function_exists('momoremits_apply_ecash_delta')) {
    function momoremits_apply_ecash_delta(int $drawerId, int $drawerUserId, string $networkRaw, float $delta, int $transId, string $transStatus): array
    {
        $channelKey = momoremits_resolve_ecash_channel_key($drawerId, $drawerUserId, $networkRaw);
        if ($channelKey === null) {
            return [
                'ok' => false,
                'closing_balance' => null,
                'message' => 'The original e-cash/bank channel for this transaction could not be resolved.',
            ];
        }

        return momoremits_apply_resolved_ecash_delta($drawerId, $drawerUserId, $channelKey, $delta, $transId, $transStatus);
    }
}

if (!function_exists('momoremits_refresh_cashdrawer_balance')) {
    function momoremits_refresh_cashdrawer_balance(int $drawerId, int $drawerUserId, int $transId, string $transStatus): ?float
    {
        if ($drawerId <= 0 || $drawerUserId <= 0) {
            return null;
        }

        $db = new Database();
        if (!momoremits_table_exists('drawer_balances') || !momoremits_table_exists('drawer_denos')) {
            return null;
        }

        $sumRows = $db->query(
            "SELECT COALESCE(SUM(volume), 0) AS total_volume
             FROM drawer_denos
             WHERE drawer_id = :drawer_id AND user_id = :user_id",
            [
                'drawer_id' => $drawerId,
                'user_id' => $drawerUserId,
            ]
        );

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
        );

        $now = date('Y-m-d H:i:s');
        if (empty($rowsBal)) {
            $hdr = $db->query("SELECT amount FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawerId]);
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

            if ($db->has_error) {
                return null;
            }

            return $currentClosing;
        }

        $rowBal = $rowsBal[0];
        $opening = (float)($rowBal->opening_balance ?? 0);
        $delta = $currentClosing - $opening;
        $credit = $delta > 0 ? $delta : 0.0;
        $debit = $delta < 0 ? abs($delta) : 0.0;

        $params = [
            'credit' => $credit,
            'debit' => $debit,
            'closing' => $currentClosing,
            'trans_id' => $transId,
            'trans_status' => $transStatus,
            'id' => $rowBal->id,
        ];
        $sql = "UPDATE drawer_balances
            SET credit = :credit,
                debit = :debit,
                closing_balance = :closing,
                trans_id = :trans_id,
                trans_status = :trans_status";
        if (momoremits_drawer_balances_has_date_updated()) {
            $sql .= ",
                date_updated = :updated";
            $params['updated'] = $now;
        }
        $sql .= "
            WHERE id = :id";
        $db->query($sql, $params);

        if ($db->has_error) {
            return null;
        }

        return $currentClosing;
    }
}

if (!function_exists('momoremits_adjust_ecash_balance')) {
    function momoremits_adjust_ecash_balance(int $drawerId, int $drawerUserId, string $channelKey, float $creditDelta, float $debitDelta, int $transId, string $transStatus): ?float
    {
        if ($drawerId <= 0 || $drawerUserId <= 0) {
            return null;
        }

        $channelKey = strtoupper(trim($channelKey));
        if ($channelKey === '') {
            return null;
        }

        $db = new Database();
        if (!momoremits_table_exists('drawer_balances')) {
            return null;
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
        );

        if (empty($rows)) {
            return null;
        }

        $row = $rows[0];
        $opening = (float)($row->opening_balance ?? 0);
        $credit = max(0.0, (float)($row->credit ?? 0) + $creditDelta);
        $debit = max(0.0, (float)($row->debit ?? 0) + $debitDelta);
        $closing = $opening + $credit - $debit;

        $params = [
            'credit' => $credit,
            'debit' => $debit,
            'closing' => $closing,
            'trans_id' => $transId,
            'trans_status' => $transStatus,
            'id' => $row->id,
        ];
        $sql = "UPDATE drawer_balances
            SET credit = :credit,
                debit = :debit,
                closing_balance = :closing,
                trans_id = :trans_id,
                trans_status = :trans_status";
        if (momoremits_drawer_balances_has_date_updated()) {
            $sql .= ",
                date_updated = :updated";
            $params['updated'] = date('Y-m-d H:i:s');
        }
        $sql .= "
            WHERE id = :id";
        $db->query($sql, $params);

        if ($db->has_error) {
            return null;
        }

        return $closing;
    }
}

if (!function_exists('momoremits_reverse_approval_effects')) {
    function momoremits_reverse_approval_effects(object $transaction, string $transStatus): array
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

        $context = momoremits_find_transaction_drawer_context($transaction);
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
                'message' => 'No historical cashdrawer could be resolved for ' . $transactionDateLabel . ' for the user who created this transaction. To keep the records consistent, decline or reject it against that original day\'s cashdrawer.',
            ];
        }

        $drawerId = (int)($context['drawer_id'] ?? 0);
        $drawerUserId = (int)($context['drawer_user_id'] ?? 0);

        $transactionType = trim((string)($transaction->transaction_type ?? ''));
        $network = trim((string)($transaction->network ?? ''));
        $amount = (float)($transaction->amount ?? 0);
        $amountSendable = (float)($transaction->amount_sendable ?? 0);
        $actorUserId = (int)($transaction->updated_by ?? 0);

        $cashDelta = 0.0;
        $ecashDelta = 0.0;
        if (strcasecmp($transactionType, 'Cash in') === 0) {
            $cashDelta = -1 * abs($amount);
            $ecashDelta = function_exists('momoremits_cashin_ecash_debit_amount')
                ? abs(momoremits_cashin_ecash_debit_amount($transaction))
                : abs($amountSendable);
        } elseif (in_array($transactionType, ['Withdrawal', 'CIT'], true)) {
            $cashDelta = abs($amount);
            $ecashDelta = -1 * abs($amount);
        }

        if (abs($cashDelta) > 0.00001) {
            $cashResult = momoremits_reverse_drawer_denos($drawerId, $drawerUserId, $transId, $transStatus, $actorUserId);
            if (!empty($cashResult['had_rows'])) {
                $result['cashdrawer_closing_balance'] = $cashResult['closing_balance'] ?? null;
            } else {
                $cashResult = momoremits_apply_cashdrawer_delta($drawerId, $drawerUserId, $cashDelta, $transId, $transStatus);
            }
            if (empty($cashResult['ok'])) {
                $result['ok'] = false;
                $result['message'] = $cashResult['message'] ?: 'Cashdrawer reversal failed.';
                return $result;
            }
            if ($result['cashdrawer_closing_balance'] === null) {
                $result['cashdrawer_closing_balance'] = $cashResult['closing_balance'] ?? null;
            }
        }

        if (abs($ecashDelta) > 0.00001 && $network !== '') {
            $resolvedEcashKey = null;
            if (momoremits_table_exists('drawer_balances')) {
                $channelRows = (new Database())->query(
                    "SELECT channel_key
                     FROM drawer_balances
                     WHERE drawer_id = :drawer_id
                       AND user_id = :user_id
                       AND channel_type = 'ecash'
                       AND trans_id = :trans_id
                     ORDER BY id DESC
                     LIMIT 1",
                    [
                        'drawer_id' => $drawerId,
                        'user_id' => $drawerUserId,
                        'trans_id' => $transId,
                    ]
                );
                if (!empty($channelRows)) {
                    $resolvedEcashKey = (string)($channelRows[0]->channel_key ?? '');
                }
            }
            if ($resolvedEcashKey === '') {
                $resolvedEcashKey = momoremits_resolve_ecash_channel_key(
                    $drawerId,
                    $drawerUserId,
                    $network,
                    (string)($transaction->bank_name ?? ''),
                    (string)($transaction->sim_type ?? '')
                );
            }
            if ($resolvedEcashKey === null) {
                $candidates = momoremits_ecash_channel_candidates(
                    $network,
                    (string)($transaction->bank_name ?? ''),
                    (string)($transaction->sim_type ?? '')
                );
                $availableRows = (new Database())->query(
                    "SELECT channel_key, closing_balance
                     FROM drawer_balances
                     WHERE drawer_id = :drawer_id
                       AND user_id = :user_id
                       AND channel_type = 'ecash'
                     ORDER BY channel_key ASC",
                    [
                        'drawer_id' => $drawerId,
                        'user_id' => $drawerUserId,
                    ]
                );
                $candidateKeys = array_fill_keys(array_map('strtoupper', $candidates), true);
                foreach ($availableRows ?? [] as $availableRow) {
                    $availableKey = trim((string)($availableRow->channel_key ?? ''));
                    if (isset($candidateKeys[strtoupper($availableKey)])) {
                        $resolvedEcashKey = $availableKey;
                        break;
                    }
                }
            }
            if ($resolvedEcashKey === null) {
                $candidates = momoremits_ecash_channel_candidates(
                    $network,
                    (string)($transaction->bank_name ?? ''),
                    (string)($transaction->sim_type ?? '')
                );
                $expectedChannel = !empty($candidates) ? $candidates[0] : $network;
                $availableBalance = 0.0;
                if (momoremits_table_exists('drawer_balances')) {
                    $expectedRows = (new Database())->query(
                        "SELECT closing_balance
                         FROM drawer_balances
                         WHERE drawer_id = :drawer_id
                           AND user_id = :user_id
                           AND channel_type = 'ecash'
                           AND UPPER(channel_key) = :channel_key
                         ORDER BY id DESC
                         LIMIT 1",
                        [
                            'drawer_id' => $drawerId,
                            'user_id' => $drawerUserId,
                            'channel_key' => strtoupper($expectedChannel),
                        ]
                    );
                    if (!empty($expectedRows)) {
                        $availableBalance = (float)($expectedRows[0]->closing_balance ?? 0);
                    }
                }
                $result['ok'] = false;
                $result['message'] = 'Unable to resolve the transaction channel ' . $expectedChannel .
                    ': available GHS ' . number_format($availableBalance, 2) .
                    '; expected debit GHS ' . number_format(abs($ecashDelta), 2) . '.';
                return $result;
            }

            $ecashResult = momoremits_apply_resolved_ecash_delta($drawerId, $drawerUserId, $resolvedEcashKey, $ecashDelta, $transId, $transStatus);
            if (empty($ecashResult['ok'])) {
                $result['ok'] = false;
                $result['message'] = $ecashResult['message'] ?: 'E-cash reversal failed.';
                return $result;
            }
            $result['ecash_closing_balance'] = $ecashResult['closing_balance'] ?? null;
        }

        return $result;
    }
}

if (!function_exists('momoremits_fetch_transactions')) {
    function momoremits_fetch_transactions(array $filters = [], array $context = []): array
    {
        $db = new Database();

        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = (int)($filters['per_page'] ?? 10);
        $perPage = min(50, max(5, $perPage));
        $offset = ($page - 1) * $perPage;

        $search = trim($filters['search'] ?? '');
        $transactionType = strtolower(trim($filters['transaction_type'] ?? 'all'));
        $createdBy = (int)($filters['created_by'] ?? 0);
        $startDate = trim($filters['start_date'] ?? '');
        $endDate = trim($filters['end_date'] ?? '');

        $restrictToUser = !empty($context['restrict_to_user']);
        $contextUserId = (int)($context['user_id'] ?? 0);

        $where = ['(m.deleted IS NULL OR m.deleted = 0)'];
        $params = [];

        if ($restrictToUser && $contextUserId) {
            $where[] = 'm.created_by = :ctx_user';
            $params['ctx_user'] = $contextUserId;
        }

        if ($transactionType && $transactionType !== 'all') {
            $where[] = 'LOWER(m.transaction_type) = :transaction_type';
            $params['transaction_type'] = $transactionType;
        }

        if ($createdBy > 0) {
            $where[] = 'm.created_by = :created_by';
            $params['created_by'] = $createdBy;
        }

        if ($startDate) {
            $where[] = 'DATE(m.date_created) >= :start_date';
            $params['start_date'] = date('Y-m-d', strtotime($startDate));
        }

        if ($endDate) {
            $where[] = 'DATE(m.date_created) <= :end_date';
            $params['end_date'] = date('Y-m-d', strtotime($endDate));
        }

        if ($search !== '') {
            $where[] = '(
                m.network LIKE :search OR
                m.id LIKE :search OR
                m.transaction_type LIKE :search OR
                m.sending_category LIKE :search OR
                m.category LIKE :search OR
                m.phone LIKE :search OR
                m.transaction_status LIKE :search OR
                u.first_name LIKE :search OR
                u.last_name LIKE :search
            )';
            $params['search'] = "%$search%";
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $fromClause = 'FROM momoremits AS m LEFT JOIN users AS u ON m.created_by = u.id';

        $columns = "
            m.id,
            m.network,
            m.transaction_type,
            m.sending_category,
            m.category,
            m.phone,
            m.amount,
            m.charge,
            m.balance_after,
            m.transaction_status,
            m.date_created,
            m.created_by,
            u.first_name AS user_first_name,
            u.last_name AS user_last_name,
            u.image AS user_image
        ";

        $sql = "SELECT $columns $fromClause $whereSql ORDER BY m.id DESC LIMIT $perPage OFFSET $offset";
        $rows = $db->query($sql, $params) ?: [];

        $aggregateSql = "SELECT COUNT(*) AS total_rows, COALESCE(SUM(m.amount),0) AS total_amount, COALESCE(SUM(m.charge),0) AS total_charge $fromClause $whereSql";
        $aggregate = $db->query($aggregateSql, $params) ?: [];
        $aggregateRow = $aggregate[0] ?? (object)['total_rows' => 0, 'total_amount' => 0, 'total_charge' => 0];

        $formattedRows = array_map(function ($row) {
            $creatorName = trim(($row->user_first_name ?? '') . ' ' . ($row->user_last_name ?? ''));
            return [
                'id' => (int)$row->id,
                'network' => $row->network,
                'transaction_type' => $row->transaction_type,
                'categories' => array_filter([
                    $row->sending_category ?? null,
                    $row->category ?? null,
                ]),
                'amount' => (float)$row->amount,
                'charge' => (float)$row->charge,
                'balance_after' => (float)$row->balance_after,
                'transaction_status' => $row->transaction_status,
                'date_created' => $row->date_created,
                'creator' => [
                    'name' => $creatorName ?: '—',
                    'image' => get_image($row->user_image ?? ''),
                ],
            ];
        }, $rows);

        $totalRows = (int)($aggregateRow->total_rows ?? 0);
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $totalAmount = (float)$aggregateRow->total_amount;
        $totalCharge = (float)$aggregateRow->total_charge;
        $avgTicket = $totalRows ? ($totalAmount / $totalRows) : 0;

        return [
            'rows' => $formattedRows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total_rows' => $totalRows,
                'total_pages' => $totalPages,
            ],
            'totals' => [
                'amount' => $totalAmount,
                'charge' => $totalCharge,
                'avg_ticket' => $avgTicket,
                'count' => $totalRows,
            ],
        ];
    }
}

if (!function_exists('momoremits_filter_metadata')) {
    function momoremits_filter_metadata(array $context = []): array
    {
        $db = new Database();

        $restrictToUser = !empty($context['restrict_to_user']);
        $contextUserId = (int)($context['user_id'] ?? 0);

        $where = ['(m.deleted IS NULL OR m.deleted = 0)'];
        $params = [];

        if ($restrictToUser && $contextUserId) {
            $where[] = 'm.created_by = :ctx_user';
            $params['ctx_user'] = $contextUserId;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $typesSql = "SELECT DISTINCT m.transaction_type FROM momoremits AS m $whereSql AND m.transaction_type IS NOT NULL ORDER BY m.transaction_type";
        $types = $db->query($typesSql, $params) ?: [];
        $transactionTypes = array_values(array_filter(array_map(fn($row) => $row->transaction_type ?? null, $types)));

        $creatorsSql = "
            SELECT DISTINCT u.id, u.first_name, u.last_name
            FROM momoremits AS m
            LEFT JOIN users AS u ON m.created_by = u.id
            $whereSql AND u.id IS NOT NULL
            ORDER BY u.first_name, u.last_name
        ";
        $creatorRows = $db->query($creatorsSql, $params) ?: [];
        $creators = array_map(function ($row) {
            return [
                'id' => (int)$row->id,
                'name' => trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')),
            ];
        }, $creatorRows);

        return [
            'transaction_types' => $transactionTypes,
            'creators' => $creators,
        ];
    }
}

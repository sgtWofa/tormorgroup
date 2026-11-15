<?php

if (!function_exists('agencybanking_limit_key')) {
    function agencybanking_limit_key($value): string
    {
        $value = strtolower(trim((string)$value));
        $compact = preg_replace('/[^a-z0-9]+/', '', $value);
        $aliases = [
            'ghqr' => 'ghqr',
            'gcb' => 'gcb',
            'fidelity' => 'fidelity bank',
            'fidelitybank' => 'fidelity bank',
            'absa' => 'absa',
            'absabank' => 'absa',
            'gtbank' => 'gt bank',
            'gt' => 'gt bank',
            'apexnafanabank' => 'nafana rural bank',
            'nafanaruralbank' => 'nafana rural bank',
            'nafanabank' => 'nafana rural bank',
            'apexsumabank' => 'suma rural bank',
            'sumaruralbank' => 'suma rural bank',
            'sumabank' => 'suma rural bank',
        ];
        return $aliases[$compact] ?? $value;
    }
}

if (!function_exists('agencybanking_normalize_limit_type')) {
    function agencybanking_normalize_limit_type($value): string
    {
        $value = agencybanking_limit_key($value);
        if (strpos($value, 'deposit') !== false) {
            return 'deposit';
        }
        if (strpos($value, 'withdrawal') !== false) {
            return 'withdrawal';
        }
        if (strpos($value, 'direct transfer') !== false || strpos($value, 'funds transfer') !== false) {
            return 'direct_transfer';
        }
        if (strpos($value, 'interbank') !== false) {
            return 'interbank';
        }
        if (strpos($value, 'account opening') !== false || strpos($value, 'account creation') !== false) {
            return 'account_creation';
        }
        return $value;
    }
}

if (!function_exists('agencybanking_normalize_withdrawal_type')) {
    function agencybanking_normalize_withdrawal_type($transactionType, $withdrawalType = ''): string
    {
        $value = agencybanking_limit_key($withdrawalType !== '' ? $withdrawalType : $transactionType);
        if (strpos($value, 'ait') !== false) {
            return 'ait';
        }
        if (strpos($value, 'cit') !== false) {
            return 'cit';
        }
        return '';
    }
}

if (!function_exists('agencybanking_transaction_withdrawal_type')) {
    function agencybanking_transaction_withdrawal_type($transactionType, $explicitType = ''): string
    {
        return agencybanking_normalize_withdrawal_type($transactionType, $explicitType);
    }
}

if (!function_exists('agencybanking_ensure_limit_table')) {
    function agencybanking_ensure_limit_table(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $marker = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.runtime' . DIRECTORY_SEPARATOR . 'agencybanking_limits_schema_20260821.done';
        if (is_file($marker)) {
            $db = new \Core\Database;
            if ($db->table_exists(['agencybanking_limits'])) {
                $ensured = true;
                return;
            }
        }
        try {
            $db = new \Core\Database;
            if (!$db->table_exists(['agencybanking_limits'])) {
                $db->query("CREATE TABLE agencybanking_limits (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    transaction_type VARCHAR(50) NOT NULL,
                    withdrawal_type VARCHAR(20) NOT NULL DEFAULT '',
                    network VARCHAR(100) NOT NULL,
                    max_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                    active TINYINT(1) NOT NULL DEFAULT 1,
                    created_by INT UNSIGNED NULL,
                    date_created DATETIME NULL,
                    date_updated DATETIME NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY agencybanking_limit_key (transaction_type, withdrawal_type, network),
                    KEY agencybanking_limit_active (active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            }
            $columns = $db->query("SHOW COLUMNS FROM agencybanking_limits") ?: [];
            $hasWithdrawalType = false;
            foreach ($columns as $column) {
                if (strtolower((string)($column->Field ?? '')) === 'withdrawal_type') {
                    $hasWithdrawalType = true;
                    break;
                }
            }
            if (!$hasWithdrawalType) {
                $db->query("ALTER TABLE agencybanking_limits ADD COLUMN withdrawal_type VARCHAR(20) NOT NULL DEFAULT '' AFTER transaction_type");
            }
            $indexes = $db->query("SHOW INDEX FROM agencybanking_limits") ?: [];
            $hasOldUniqueKey = false;
            $hasNewUniqueKey = false;
            foreach ($indexes as $index) {
                if (($index->Key_name ?? '') !== 'agencybanking_limit_key') {
                    continue;
                }
                if ((int)($index->Seq_in_index ?? 0) === 3 && ($index->Column_name ?? '') === 'network') {
                    $hasNewUniqueKey = true;
                }
                if ((int)($index->Seq_in_index ?? 0) === 2 && ($index->Column_name ?? '') === 'network') {
                    $hasOldUniqueKey = true;
                }
            }
            if ($hasOldUniqueKey && !$hasNewUniqueKey) {
                $db->query('ALTER TABLE agencybanking_limits DROP INDEX agencybanking_limit_key, ADD UNIQUE KEY agencybanking_limit_key (transaction_type, withdrawal_type, network)');
            }
            if (!is_dir(dirname($marker))) {
                @mkdir(dirname($marker), 0777, true);
            }
            @file_put_contents($marker, '1');
        } catch (\Throwable $e) {
            // The transaction flow remains available if optional limit storage is unavailable.
        }
        $ensured = true;
    }
}

if (!function_exists('agencybanking_find_transaction_limit')) {
    function agencybanking_find_transaction_limit($transactionType, $network, $withdrawalType = ''): ?float
    {
        agencybanking_ensure_limit_table();
        try {
            $db = new \Core\Database;
            $params = [
                'transaction_type' => agencybanking_normalize_limit_type($transactionType),
                'withdrawal_type' => agencybanking_normalize_withdrawal_type($transactionType, $withdrawalType),
                'network' => agencybanking_limit_key($network),
            ];
            $rows = $db->query(
                'SELECT max_amount FROM agencybanking_limits WHERE active = 1 AND transaction_type = :transaction_type AND withdrawal_type = :withdrawal_type AND network = :network LIMIT 1',
                $params
            ) ?: [];
            if (empty($rows) && $params['transaction_type'] === 'withdrawal' && $params['withdrawal_type'] !== '') {
                $rows = $db->query(
                    "SELECT max_amount FROM agencybanking_limits WHERE active = 1 AND transaction_type = 'withdrawal' AND withdrawal_type = '' AND network = :network LIMIT 1",
                    ['network' => $params['network']]
                ) ?: [];
            }
            return empty($rows) ? null : max(0.0, (float)$rows[0]->max_amount);
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('agencybanking_limit_amount')) {
    function agencybanking_limit_amount(array $data): float
    {
        $type = agencybanking_normalize_limit_type($data['transaction_type'] ?? '');
        $fields = match ($type) {
            'withdrawal' => ['amount_withdrawn', 'amount_payable', 'amount'],
            'direct_transfer' => ['amount', 'balance_before', 'amount_to_pay'],
            default => ['amount'],
        };
        foreach ($fields as $field) {
            $amount = (float)str_replace(',', '', (string)($data[$field] ?? 0));
            if (is_finite($amount) && $amount > 0) {
                return $amount;
            }
        }
        return 0.0;
    }
}

if (!function_exists('agencybanking_validate_transaction_limit')) {
    function agencybanking_validate_transaction_limit(array $data): ?string
    {
        $transactionType = $data['transaction_type'] ?? '';
        $withdrawalType = agencybanking_transaction_withdrawal_type($transactionType, $data['withdrawal_type'] ?? '');
        $limit = agencybanking_find_transaction_limit($transactionType, $data['network'] ?? '', $withdrawalType);
        if ($limit === null) {
            return null;
        }
        $amount = agencybanking_limit_amount($data);
        if ($amount > $limit + 0.009) {
            $label = ucfirst(agencybanking_normalize_limit_type($data['transaction_type'] ?? 'transaction'));
            if ($label === 'Withdrawal') {
                $label .= ' (' . strtoupper($withdrawalType) . ')';
            }
            return 'Maximum allowed amount for ' . $label . ' at ' . strtoupper(trim((string)($data['network'] ?? ''))) . ' is GHS ' . number_format($limit, 2) . '.';
        }
        return null;
    }
}

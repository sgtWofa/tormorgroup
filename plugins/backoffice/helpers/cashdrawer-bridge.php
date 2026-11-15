<?php

namespace BackOfficeCashdrawer;

use Core\Database;

function init(): void
{
    static $bootstrapped = false;
    if ($bootstrapped) {
        return;
    }
    loadCfaHelpers();
    ensurePaymentColumns();
    ensureRequestColumns();
    ensureCommissionColumns();
    ensureReconciliationColumns();
    ensureAgentServicesColumns();
    $bootstrapped = true;
}

function loadCfaHelpers(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $helperPath = realpath(__DIR__ . '/../../cfa/helpers/cashdrawer-sync.php');
    if ($helperPath && file_exists($helperPath)) {
        require_once $helperPath;
    }
    $loaded = true;
}

function ensurePaymentColumns(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $db = new Database;
    $schema = $db->query("SHOW COLUMNS FROM payments_backoffice");
    if (!$schema || !is_array($schema)) {
        $ensured = true;
        return;
    }
    $existing = [];
    foreach ($schema as $column) {
        $existing[strtolower($column->Field ?? '')] = true;
    }
    $alters = [];
    if (empty($existing['cashdrawer_id'])) {
        $alters[] = "ADD COLUMN cashdrawer_id INT UNSIGNED NULL AFTER receiving_wallet";
    }
    if (empty($existing['drawer_user_id'])) {
        $alters[] = "ADD COLUMN drawer_user_id INT UNSIGNED NULL AFTER cashdrawer_id";
    }
    if (empty($existing['denos_json'])) {
        $alters[] = "ADD COLUMN denos_json JSON NULL AFTER drawer_user_id";
    }
    if (empty($existing['verified_denos_json'])) {
        $alters[] = "ADD COLUMN verified_denos_json LONGTEXT NULL AFTER denos_json";
    }
    if (empty($existing['payment_breakdown_json'])) {
        $alters[] = "ADD COLUMN payment_breakdown_json LONGTEXT NULL AFTER verified_denos_json";
    }
    if (empty($existing['approval_receipt_photo'])) {
        $alters[] = "ADD COLUMN approval_receipt_photo VARCHAR(255) NULL AFTER payment_breakdown_json";
    }
    if (empty($existing['approval_receipt_uploaded_at'])) {
        $alters[] = "ADD COLUMN approval_receipt_uploaded_at DATETIME NULL AFTER approval_receipt_photo";
    }
    if (empty($existing['approved_by'])) {
        $alters[] = "ADD COLUMN approved_by INT UNSIGNED NULL AFTER updated_by";
    }
    if (empty($existing['approved_at'])) {
        $alters[] = "ADD COLUMN approved_at DATETIME NULL AFTER approved_by";
    }
    if (!empty($alters)) {
        $db->query("ALTER TABLE payments_backoffice " . implode(',', $alters));
    }
    $ensured = true;
}

function ensureReconciliationColumns(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $db = new Database;
    $schema = $db->query("SHOW COLUMNS FROM reconciliations");
    if (!$schema || !is_array($schema)) {
        $ensured = true;
        return;
    }
    $existing = [];
    foreach ($schema as $column) {
        $existing[strtolower($column->Field ?? '')] = true;
    }
    $alters = [];
    if (empty($existing['proof_image'])) {
        $alters[] = "ADD COLUMN proof_image VARCHAR(255) NULL AFTER remarks";
    }
	if (empty($existing['denos_json'])) {
		$alters[] = "ADD COLUMN denos_json JSON NULL AFTER proof_image";
	}
    if (!empty($alters)) {
        $db->query("ALTER TABLE reconciliations " . implode(',', $alters));
    }
    $ensured = true;
}

function ensureAgentServicesColumns(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $db = new Database;
    if (!$db->table_exists(['agent_services'])) {
        $sql = "CREATE TABLE `agent_services` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `customer_type` varchar(50) DEFAULT NULL,
            `service_type` varchar(50) DEFAULT NULL,
            `creator_id` int(10) unsigned DEFAULT NULL,
            `assigned_to` int(10) unsigned DEFAULT NULL,
            `processor_id` int(10) unsigned DEFAULT NULL,
            `approver_id` int(10) unsigned DEFAULT NULL,
            `status` varchar(50) DEFAULT 'Pending',
            `stage` int(10) unsigned DEFAULT 1,
            `amount` float(10,2) DEFAULT 0.00,
            `cashdrawer_id` int(10) unsigned DEFAULT NULL,
            `drawer_user_id` int(10) unsigned DEFAULT NULL,
            `cash_received` float(10,2) DEFAULT 0.00,
            `change_amount` float(10,2) DEFAULT 0.00,
            `change_denominations_json` json DEFAULT NULL,
            `commission_amount` float(10,2) DEFAULT 0.00,
            `commission_type` varchar(20) DEFAULT NULL,
            `cancel_reason` text DEFAULT NULL,
            `cancelled_by` int(10) unsigned DEFAULT NULL,
            `date_cancelled` datetime DEFAULT NULL,
            `provider` varchar(100) DEFAULT NULL,
            `paying_network` varchar(100) DEFAULT NULL,
            `receiving_network` varchar(100) DEFAULT NULL,
            `agent_name` varchar(100) DEFAULT NULL,
            `agent_contact` varchar(100) DEFAULT NULL,
            `business_name` varchar(100) DEFAULT NULL,
            `agent_number` varchar(100) DEFAULT NULL,
            `proof_image` varchar(255) DEFAULT NULL,
            `metadata` longtext DEFAULT NULL,
            `denominations_json` json DEFAULT NULL,
            `remarks` text DEFAULT NULL,
            `reject_reason` text DEFAULT NULL,
            `date_completed` datetime DEFAULT NULL,
            `created_by` int(10) unsigned DEFAULT NULL,
            `updated_by` int(10) unsigned DEFAULT NULL,
            `deleted` tinyint(1) unsigned DEFAULT 0,
            `date_created` datetime DEFAULT NULL,
            `date_updated` datetime DEFAULT NULL,
            `date_deleted` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `customer_type` (`customer_type`),
            KEY `service_type` (`service_type`),
            KEY `creator_id` (`creator_id`),
            KEY `assigned_to` (`assigned_to`),
            KEY `status` (`status`),
            KEY `deleted` (`deleted`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        try {
            $db->query($sql);
        } catch (\Throwable $e) {}
    } else {
        $schema = $db->query("SHOW COLUMNS FROM agent_services");
        $existing = [];
        if ($schema && is_array($schema)) {
            foreach ($schema as $column) {
                $existing[strtolower($column->Field ?? '')] = true;
            }
        }
        $alters = [];
        if (empty($existing['cashdrawer_id'])) {
            $alters[] = "ADD COLUMN cashdrawer_id INT UNSIGNED NULL AFTER amount";
        }
        if (empty($existing['drawer_user_id'])) {
            $alters[] = "ADD COLUMN drawer_user_id INT UNSIGNED NULL AFTER cashdrawer_id";
        }
        if (empty($existing['cash_received'])) {
            $alters[] = "ADD COLUMN cash_received FLOAT(10,2) DEFAULT 0.00 AFTER amount";
        }
        if (empty($existing['change_amount'])) {
            $alters[] = "ADD COLUMN change_amount FLOAT(10,2) DEFAULT 0.00 AFTER cash_received";
        }
        if (empty($existing['change_denominations_json'])) {
            $alters[] = "ADD COLUMN change_denominations_json JSON NULL AFTER change_amount";
        }
        if (empty($existing['denominations_json'])) {
            $alters[] = "ADD COLUMN denominations_json JSON NULL";
        }
        if (empty($existing['cancel_reason'])) {
            $alters[] = "ADD COLUMN cancel_reason TEXT NULL";
        }
        if (empty($existing['cancelled_by'])) {
            $alters[] = "ADD COLUMN cancelled_by INT UNSIGNED NULL";
        }
        if (empty($existing['date_cancelled'])) {
            $alters[] = "ADD COLUMN date_cancelled DATETIME NULL";
        }
        if (!empty($alters)) {
            $db->query("ALTER TABLE agent_services " . implode(',', $alters));
        }
    }
    $ensured = true;
}

function ensureCommissionColumns(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $db = new Database;
    $schema = $db->query("SHOW COLUMNS FROM commissions");
    if (!$schema || !is_array($schema)) {
        $ensured = true;
        return;
    }
    $existing = [];
    foreach ($schema as $column) {
        $existing[strtolower($column->Field ?? '')] = true;
    }
    $alters = [];
    if (empty($existing['approved_by'])) {
        $alters[] = "ADD COLUMN approved_by INT UNSIGNED NULL AFTER updated_by";
    }
    if (empty($existing['approved_at'])) {
        $alters[] = "ADD COLUMN approved_at DATETIME NULL AFTER approved_by";
    }
    if (!empty($alters)) {
        $db->query("ALTER TABLE commissions " . implode(',', $alters));
    }
    $ensured = true;
}

function ensureRequestColumns(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $db = new Database;
    $schema = $db->query("SHOW COLUMNS FROM requests");
    if (!$schema || !is_array($schema)) {
        $ensured = true;
        return;
    }
    $existing = [];
    foreach ($schema as $column) {
        $existing[strtolower($column->Field ?? '')] = true;
    }
    $alters = [];
    if (empty($existing['cashdrawer_id'])) {
        $alters[] = "ADD COLUMN cashdrawer_id INT UNSIGNED NULL AFTER assigned";
    }
    if (empty($existing['drawer_user_id'])) {
        $alters[] = "ADD COLUMN drawer_user_id INT UNSIGNED NULL AFTER cashdrawer_id";
    }
    if (empty($existing['denos_json'])) {
        $alters[] = "ADD COLUMN denos_json JSON NULL AFTER drawer_user_id";
    }
    if (empty($existing['approved_by'])) {
        $alters[] = "ADD COLUMN approved_by INT UNSIGNED NULL AFTER updated_by";
    }
    if (empty($existing['approved_at'])) {
        $alters[] = "ADD COLUMN approved_at DATETIME NULL AFTER approved_by";
    }
    if (!empty($alters)) {
        $db->query("ALTER TABLE requests " . implode(',', $alters));
    }
    $ensured = true;
}

function getActiveDrawer(int $userId): ?array
{
    loadCfaHelpers();
    if (!function_exists('Cfas\\cfa_find_active_drawer')) {
        return null;
    }
    return \Cfas\cfa_find_active_drawer($userId);
}

function getAssignableUsersToday(int $excludeUserId = 0): array
{
    $db = new Database;
    $today = (new \DateTimeImmutable('now', new \DateTimeZone('Africa/Accra')))->format('Y-m-d');

    try {
        if (!$db->table_exists(['users', 'cashdrawers'])) {
            return [];
        }

        $params = ['today' => $today];
        $excludeSql = '';
        if ($excludeUserId > 0) {
            $excludeSql = ' AND u.id <> :exclude_user_id';
            $params['exclude_user_id'] = $excludeUserId;
        }

        $rows = $db->query(
            "SELECT DISTINCT u.*
             FROM users u
             INNER JOIN cashdrawers c ON c.user_id = u.id AND DATE(c.date_created) = :today
             WHERE u.deleted = 0{$excludeSql}
             ORDER BY u.first_name ASC, u.last_name ASC, u.id ASC",
            $params
        ) ?: [];

        if (empty($rows)) {
            return [];
        }

        foreach ($rows as $row) {
            $row->roles = [''];
        }

        if ($db->table_exists(['user_roles', 'user_roles_map'])) {
            $ids = array_values(array_unique(array_filter(array_map(function ($row) {
                return (int)($row->id ?? 0);
            }, $rows))));

            if (!empty($ids)) {
                $in = implode(',', array_map('intval', $ids));
                $roleRows = $db->query(
                    "SELECT m.user_id, ur.role
                     FROM user_roles_map m
                     JOIN user_roles ur ON ur.id = m.role_id
                     WHERE m.disabled = 0 AND ur.disabled = 0 AND m.user_id IN ($in)
                     ORDER BY m.id ASC"
                ) ?: [];

                $roleMap = [];
                foreach ($roleRows as $roleRow) {
                    $userId = (int)($roleRow->user_id ?? 0);
                    if ($userId <= 0 || isset($roleMap[$userId])) {
                        continue;
                    }
                    $roleMap[$userId] = trim((string)($roleRow->role ?? ''));
                }

                foreach ($rows as $row) {
                    $rowId = (int)($row->id ?? 0);
                    $row->roles = [$roleMap[$rowId] ?? ''];
                }
            }
        }

        return $rows;
    } catch (\Throwable $e) {
        return [];
    }
}

function userHasCashdrawerToday(int $userId): bool
{
    $userId = (int)$userId;
    if ($userId <= 0) {
        return false;
    }

    $db = new Database;
    $today = (new \DateTimeImmutable('now', new \DateTimeZone('Africa/Accra')))->format('Y-m-d');

    try {
        if (!$db->table_exists(['cashdrawers'])) {
            return false;
        }

        $rows = $db->query(
            "SELECT id
             FROM cashdrawers
             WHERE user_id = :user_id
             AND DATE(date_created) = :today
             ORDER BY id DESC
             LIMIT 1",
            [
                'user_id' => $userId,
                'today' => $today,
            ]
        ) ?: [];

        return !empty($rows);
    } catch (\Throwable $e) {
        return false;
    }
}

function isStandardDenomination(float $amount, string $currency = 'GHS'): bool
{
    $currency = strtoupper(trim($currency));
    $amount = (float)$amount;
    if ($amount <= 0) {
        return false;
    }

    $denoms = [];
    if ($currency === 'CFA') {
        $denoms = [10000, 5000, 2000, 1000, 500, 200, 100, 50, 20, 10];
    } else {
        $denoms = [200, 100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1];
    }

    foreach ($denoms as $d) {
        if (abs($amount - (float)$d) < 0.0001) {
            return true;
        }
    }
    return false;
}

function normalizeDenominations($rawPayload, float $fallbackAmount = 0.0, string $defaultCurrency = 'GHS'): array
{
    if (is_string($rawPayload)) {
        $decoded = json_decode($rawPayload, true);
        $rawPayload = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($rawPayload)) {
        $rawPayload = [];
    }
    $defaultCurrency = strtoupper(trim($defaultCurrency ?: 'GHS'));
    if (!in_array($defaultCurrency, ['GHS', 'CFA'], true)) {
        $defaultCurrency = 'GHS';
    }
    $normalized = [];
    foreach ($rawPayload as $row) {
        $volume = (float)($row['volume'] ?? 0);
        $denomination = (float)($row['denomination'] ?? 0);
        $count = (int)($row['count'] ?? 0);
        if ($volume <= 0 && $denomination <= 0) {
            continue;
        }
        if ($count === 0 && $denomination > 0) {
            $count = max(1, (int)round($volume / $denomination));
        }
        $normalized[] = [
            'currency' => strtoupper($row['currency'] ?? $defaultCurrency),
            'denomination' => $denomination,
            'count' => $count,
            'volume' => $volume,
        ];
    }
    if (empty($normalized) && $fallbackAmount > 0 && isStandardDenomination($fallbackAmount, $defaultCurrency)) {
        $normalized[] = [
            'currency' => $defaultCurrency,
            'denomination' => $fallbackAmount,
            'count' => 1,
            'volume' => $fallbackAmount,
        ];
    }
    return $normalized;
}

function calculateTotal(array $denos): float
{
    $total = 0;
    foreach ($denos as $row) {
        $total += (float)($row['volume'] ?? 0);
    }
    return round($total, 2);
}

function getDrawerDenominationCounts(int $userId, string $currency = 'GHS'): array
{
    loadCfaHelpers();
    $currency = strtoupper(trim($currency ?: 'GHS'));
    if (!in_array($currency, ['GHS', 'CFA'], true)) {
        $currency = 'GHS';
    }

    $ctx = getActiveDrawer($userId);
    if (empty($ctx)) {
        return [];
    }

    $drawerId = (int)($ctx['drawer_id'] ?? 0);
    $drawerUserId = (int)($ctx['drawer_user_id'] ?? $userId);
    if ($drawerId <= 0 || $drawerUserId <= 0) {
        return [];
    }

    $db = new Database;

    $formatKey = function (float $denom) use ($currency): string {
        if ($currency === 'CFA') {
            return (string)((int)round($denom));
        }
        return number_format((float)$denom, 2, '.', '');
    };

    $counts = [];
    if ($currency === 'CFA') {
        if (!function_exists('Cfas\\cfa_ensure_fx_table')) {
            return [];
        }
        \Cfas\cfa_ensure_fx_table();
        if (!$db->table_exists(['drawer_fx_denos'])) {
            return [];
        }
        $rows = $db->query(
            "SELECT denomination, COALESCE(SUM(count),0) AS cnt FROM drawer_fx_denos WHERE drawer_id = :drawer_id AND user_id = :user_id AND currency = 'CFA' GROUP BY denomination",
            [
                'drawer_id' => $drawerId,
                'user_id' => $drawerUserId,
            ]
        );
    } else {
        if (!$db->table_exists(['drawer_denos'])) {
            return [];
        }
        $rows = $db->query(
            "SELECT denomination, COALESCE(SUM(count),0) AS cnt FROM drawer_denos WHERE drawer_id = :drawer_id AND user_id = :user_id GROUP BY denomination",
            [
                'drawer_id' => $drawerId,
                'user_id' => $drawerUserId,
            ]
        );
    }

    if (!empty($rows) && is_array($rows)) {
        foreach ($rows as $r) {
            $denom = (float)($r->denomination ?? 0);
            $k = $formatKey($denom);
            if ($k === '' || $k === '0' || $k === '0.00') {
                continue;
            }
            $counts[$k] = (int)($r->cnt ?? 0);
        }
    }

    return $counts;
}

function checkCashDenominationCountSufficiencyCurrency(int $userId, array $denos, float $fallbackAmount = 0.0, string $currency = 'GHS'): array
{
    $currency = strtoupper(trim($currency ?: 'GHS'));
    if (!in_array($currency, ['GHS', 'CFA'], true)) {
        $currency = 'GHS';
    }

    $drawerCounts = getDrawerDenominationCounts($userId, $currency);
    if (empty($drawerCounts)) {
        return [
            'ok' => false,
            'reason' => 'no_active_drawer',
            'currency' => $currency,
            'shortages' => [],
        ];
    }

    $formatKey = function (float $denom) use ($currency): string {
        if ($currency === 'CFA') {
            return (string)((int)round($denom));
        }
        return number_format((float)$denom, 2, '.', '');
    };

    $required = [];
    foreach ($denos as $row) {
        $rowCur = strtoupper($row['currency'] ?? $currency);
        if ($rowCur !== $currency) {
            continue;
        }
        $denomination = (float)($row['denomination'] ?? 0);
        $count = (int)($row['count'] ?? 0);
        if ($denomination <= 0 || $count <= 0) {
            continue;
        }
        $k = $formatKey($denomination);
        if (!isset($required[$k])) {
            $required[$k] = 0;
        }
        $required[$k] += $count;
    }

    if (empty($required) && $fallbackAmount > 0 && isStandardDenomination($fallbackAmount, $currency)) {
        $k = $formatKey((float)$fallbackAmount);
        $required[$k] = 1;
    }

    $shortages = [];
    foreach ($required as $denomKey => $needCount) {
        $availableCount = (int)($drawerCounts[$denomKey] ?? 0);
        if ($availableCount < $needCount) {
            $shortages[] = [
                'denomination' => $denomKey,
                'required_count' => (int)$needCount,
                'available_count' => (int)$availableCount,
                'missing_count' => (int)max(0, $needCount - $availableCount),
            ];
        }
    }

    return [
        'ok' => empty($shortages),
        'reason' => empty($shortages) ? 'sufficient' : 'insufficient_denominations',
        'currency' => $currency,
        'shortages' => $shortages,
    ];
}

function checkGhsDenominationSufficiency(int $userId, array $denos, float $fallbackAmount = 0.0): array
{
    loadCfaHelpers();
    if (!function_exists('Cfas\\cfa_get_cashdrawer_snapshot')) {
        return [
            'ok' => false,
            'reason' => 'snapshot_unavailable',
            'required' => 0.0,
            'available' => 0.0,
            'shortfall' => 0.0,
        ];
    }

    $snapshot = \Cfas\cfa_get_cashdrawer_snapshot($userId);
    if (empty($snapshot)) {
        return [
            'ok' => false,
            'reason' => 'no_active_drawer',
            'required' => 0.0,
            'available' => 0.0,
            'shortfall' => 0.0,
        ];
    }

    $required = 0.0;
    foreach ($denos as $row) {
        $currency = strtoupper($row['currency'] ?? 'GHS');
        if ($currency !== 'GHS') {
            continue;
        }
        $required += (float)($row['volume'] ?? 0.0);
    }

    if ($required <= 0.0 && $fallbackAmount > 0.0) {
        $required = $fallbackAmount;
    }

    $available = (float)($snapshot['ghs_balance'] ?? 0.0);
    $shortfall = max(0.0, $required - $available);

    return [
        'ok' => $shortfall <= 0.01,
        'reason' => $shortfall <= 0.01 ? 'sufficient' : 'insufficient',
        'required' => round($required, 2),
        'available' => round($available, 2),
        'shortfall' => round(max(0.0, $shortfall), 2),
    ];
}

function checkCashDenominationSufficiencyCurrency(int $userId, array $denos, float $fallbackAmount = 0.0, string $currency = 'GHS'): array
{
    loadCfaHelpers();
    $currency = strtoupper(trim($currency ?: 'GHS'));
    if (!in_array($currency, ['GHS', 'CFA'], true)) {
        $currency = 'GHS';
    }

    if (!function_exists('Cfas\\cfa_get_cashdrawer_snapshot')) {
        return [
            'ok' => false,
            'reason' => 'snapshot_unavailable',
            'required' => 0.0,
            'available' => 0.0,
            'shortfall' => 0.0,
            'currency' => $currency,
        ];
    }

    $snapshot = \Cfas\cfa_get_cashdrawer_snapshot($userId);
    if (empty($snapshot)) {
        return [
            'ok' => false,
            'reason' => 'no_active_drawer',
            'required' => 0.0,
            'available' => 0.0,
            'shortfall' => 0.0,
            'currency' => $currency,
        ];
    }

    $required = 0.0;
    foreach ($denos as $row) {
        $rowCur = strtoupper($row['currency'] ?? $currency);
        if ($rowCur !== $currency) {
            continue;
        }
        $required += (float)($row['volume'] ?? 0.0);
    }
    if ($required <= 0.0 && $fallbackAmount > 0.0) {
        $required = $fallbackAmount;
    }

    $available = (float)($currency === 'CFA' ? ($snapshot['cfa_balance'] ?? 0.0) : ($snapshot['ghs_balance'] ?? 0.0));
    $shortfall = max(0.0, $required - $available);

    return [
        'ok' => $shortfall <= 0.01,
        'reason' => $shortfall <= 0.01 ? 'sufficient' : 'insufficient',
        'required' => round($required, 2),
        'available' => round($available, 2),
        'shortfall' => round(max(0.0, $shortfall), 2),
        'currency' => $currency,
    ];
}

function checkEcashChannelSufficiency(int $userId, string $channelKey, float $amount): array
{
    $drawerContext = getActiveDrawer($userId);
    if (empty($drawerContext)) {
        return [
            'ok' => false,
            'reason' => 'no_active_drawer',
            'required' => round((float)$amount, 2),
            'available' => 0.0,
            'shortfall' => round(max(0.0, (float)$amount), 2),
            'channel_key' => strtoupper(trim($channelKey)),
        ];
    }

    $drawerId = (int)($drawerContext['drawer_id'] ?? 0);
    $drawerUserId = (int)($drawerContext['drawer_user_id'] ?? $userId);
    if ($drawerId <= 0) {
        return [
            'ok' => false,
            'reason' => 'no_active_drawer',
            'required' => round((float)$amount, 2),
            'available' => 0.0,
            'shortfall' => round(max(0.0, (float)$amount), 2),
            'channel_key' => strtoupper(trim($channelKey)),
        ];
    }

    $channelKey = strtoupper(trim($channelKey));
    if ($channelKey === '') {
        return [
            'ok' => false,
            'reason' => 'missing_channel',
            'required' => round((float)$amount, 2),
            'available' => 0.0,
            'shortfall' => round(max(0.0, (float)$amount), 2),
            'channel_key' => $channelKey,
        ];
    }

    $dbBal = new Database;
    if (!$dbBal->table_exists(['drawer_balances'])) {
        return [
            'ok' => false,
            'reason' => 'balances_unavailable',
            'required' => round((float)$amount, 2),
            'available' => 0.0,
            'shortfall' => round(max(0.0, (float)$amount), 2),
            'channel_key' => $channelKey,
        ];
    }

    $normalizeKey = function (string $k): string {
        $k = strtoupper(trim($k));
        return preg_replace('/[^A-Z0-9]+/', '', $k) ?: '';
    };

    $targetNormalized = $normalizeKey($channelKey);
    $targetNormalizedAlt = $normalizeKey('ECASH_' . $channelKey);

    $available = 0.0;
    $rows = $dbBal->query(
        "SELECT channel_key, closing_balance FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash'",
        [
            'drawer_id' => $drawerId,
            'user_id' => $drawerUserId,
        ]
    );
    if (!empty($rows) && is_array($rows)) {
        foreach ($rows as $r) {
            $rowKey = (string)($r->channel_key ?? '');
            $rowNormalized = $normalizeKey($rowKey);
            if ($rowNormalized === '') {
                continue;
            }
            if ($rowNormalized === $targetNormalized || $rowNormalized === $targetNormalizedAlt) {
                $available = (float)($r->closing_balance ?? 0.0);
                break;
            }
        }
    }

    $required = (float)$amount;
    $shortfall = max(0.0, $required - $available);

    return [
        'ok' => $shortfall <= 0.01,
        'reason' => $shortfall <= 0.01 ? 'sufficient' : 'insufficient',
        'required' => round($required, 2),
        'available' => round($available, 2),
        'shortfall' => round($shortfall, 2),
        'channel_key' => $channelKey,
    ];
}

function resolveExistingEcashChannelKey(int $drawerId, int $drawerUserId, string $channelKey): string
{
    $channelKey = strtoupper(trim($channelKey));
    if ($drawerId <= 0 || $drawerUserId <= 0 || $channelKey === '') {
        return $channelKey;
    }

    $db = new Database;
    if (!$db->table_exists(['drawer_balances'])) {
        return $channelKey;
    }

    $normalizeKey = function (string $k): string {
        $k = strtoupper(trim($k));
        return preg_replace('/[^A-Z0-9]+/', '', $k) ?: '';
    };

    $targetNormalized = $normalizeKey($channelKey);
    $targetNormalizedAlt = $normalizeKey('ECASH_' . $channelKey);
    if ($targetNormalized === '') {
        return $channelKey;
    }

    $rows = $db->query(
        "SELECT channel_key FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash'",
        [
            'drawer_id' => $drawerId,
            'user_id' => $drawerUserId,
        ]
    );
    if (empty($rows) || !is_array($rows)) {
        return $channelKey;
    }

    foreach ($rows as $row) {
        $stored = strtoupper(trim((string)($row->channel_key ?? '')));
        if ($stored === '') {
            continue;
        }
        $storedNormalized = $normalizeKey($stored);
        if ($storedNormalized !== '' && ($storedNormalized === $targetNormalized || ($targetNormalizedAlt !== '' && $storedNormalized === $targetNormalizedAlt))) {
            return $stored;
        }
    }

    return $channelKey;
}

function syncCashMovement(int $actorUserId, array $denos, float $fallbackAmount, string $status, int $paymentId, string $direction = 'out', string $transSource = ''): ?array
{
    loadCfaHelpers();
    $drawerContext = getActiveDrawer($actorUserId);
    if (empty($drawerContext) || !function_exists('Cfas\\cfa_process_ghs_denominations')) {
        return null;
    }
    $sign = strtolower($direction) === 'in' ? 1 : -1;
    $payload = [];
    foreach ($denos as $row) {
        if (strtoupper($row['currency'] ?? 'GHS') !== 'GHS') {
            continue;
        }
        $payload[] = [
            'denomination' => (float)($row['denomination'] ?? 0),
            'count' => (int)($row['count'] ?? 0),
            'volume' => (float)($row['volume'] ?? 0),
        ];
    }
    if (empty($payload) && $fallbackAmount > 0 && isStandardDenomination($fallbackAmount, 'GHS')) {
        $payload[] = [
            'denomination' => $fallbackAmount,
            'count' => 1,
            'volume' => $fallbackAmount,
        ];
    }
    if (empty($payload)) {
        return null;
    }
    if ($sign < 0 && function_exists(__NAMESPACE__ . '\\checkCashDenominationSufficiencyCurrency')) {
        $check = checkCashDenominationSufficiencyCurrency($actorUserId, $payload, $fallbackAmount, 'GHS');
        if (empty($check['ok'])) {
            return null;
        }
    }
    \Cfas\cfa_process_ghs_denominations(
        $drawerContext['drawer_id'],
        $drawerContext['drawer_user_id'],
        $payload,
        $sign,
        $status,
        $paymentId,
        date('Y-m-d H:i:s'),
        $fallbackAmount,
        $transSource
    );
    \Cfas\cfa_refresh_cash_channel(
        $drawerContext['drawer_id'],
        $drawerContext['drawer_user_id'],
        'CASH_DRAWER',
        $paymentId,
        $status,
        false,
        'GHS'
    );
    return $drawerContext;
}

function syncCashMovementCurrency(int $actorUserId, array $denos, float $fallbackAmount, string $status, int $paymentId, string $direction = 'out', string $currency = 'GHS', string $transSource = ''): ?array
{
    loadCfaHelpers();
    $drawerContext = getActiveDrawer($actorUserId);
    if (empty($drawerContext)) {
        return null;
    }

    $currency = strtoupper(trim($currency));
    $sign = strtolower($direction) === 'in' ? 1 : -1;

    if ($currency === 'CFA') {
        if (!function_exists('Cfas\\cfa_process_fx_denominations')) {
            return null;
        }

        $payload = [];
        foreach ($denos as $row) {
            if (strtoupper($row['currency'] ?? $currency) !== 'CFA') {
                continue;
            }
            $payload[] = [
                'currency' => 'CFA',
                'denomination' => (float)($row['denomination'] ?? 0),
                'count' => (int)($row['count'] ?? 0),
                'volume' => (float)($row['volume'] ?? 0),
            ];
        }
        if (empty($payload) && $fallbackAmount > 0 && isStandardDenomination($fallbackAmount, 'CFA')) {
            $payload[] = [
                'currency' => 'CFA',
                'denomination' => $fallbackAmount,
                'count' => 1,
                'volume' => $fallbackAmount,
            ];
        }
        if (empty($payload)) {
            return null;
        }
        if ($sign < 0 && function_exists(__NAMESPACE__ . '\\checkCashDenominationSufficiencyCurrency')) {
            $check = checkCashDenominationSufficiencyCurrency($actorUserId, $payload, $fallbackAmount, 'CFA');
            if (empty($check['ok'])) {
                return null;
            }
        }

        \Cfas\cfa_process_fx_denominations(
            $drawerContext['drawer_id'],
            $drawerContext['drawer_user_id'],
            $payload,
            $sign,
            'CFA',
            $status,
            $paymentId,
            date('Y-m-d H:i:s'),
            $fallbackAmount,
            $transSource
        );
        \Cfas\cfa_refresh_cash_channel(
            $drawerContext['drawer_id'],
            $drawerContext['drawer_user_id'],
            'CASH_CFA',
            $paymentId,
            $status,
            true,
            'CFA'
        );
        return $drawerContext;
    }

    return syncCashMovement($actorUserId, $denos, $fallbackAmount, $status, $paymentId, $direction, $transSource);
}

function syncEcashMovement(int $actorUserId, string $channelKey, float $amount, string $status, int $transId, string $direction = 'in'): ?array
{
    $drawerContext = getActiveDrawer($actorUserId);
    if (empty($drawerContext)) {
        return null;
    }

    $drawerId = (int)($drawerContext['drawer_id'] ?? 0);
    $drawerUserId = (int)($drawerContext['drawer_user_id'] ?? $actorUserId);
    if ($drawerId <= 0) {
        return null;
    }

    $channelKey = strtoupper(trim($channelKey));
    if ($channelKey === '') {
        return null;
    }

    $channelKey = resolveExistingEcashChannelKey($drawerId, $drawerUserId, $channelKey);

    $dbBal = new Database;
    if (!$dbBal->table_exists(['drawer_balances'])) {
        return $drawerContext;
    }

    $amountValue = (float)$amount;
    $rowsEcash = $dbBal->query(
        "SELECT * FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash' AND (channel_key = :channel_key OR channel_key = :ecash_channel_key) LIMIT 1",
        [
            'drawer_id' => $drawerId,
            'user_id' => $drawerUserId,
            'channel_key' => $channelKey,
            'ecash_channel_key' => 'ECASH_' . $channelKey,
        ]
    );

    $now = date('Y-m-d H:i:s');
    $creditDelta = (strtolower($direction) === 'in') ? $amountValue : 0.0;
    $debitDelta = (strtolower($direction) === 'out') ? $amountValue : 0.0;

    if (empty($rowsEcash)) {
        $opening = 0.0;
        $closing = $opening + $creditDelta - $debitDelta;
        $dbBal->query(
            "INSERT INTO drawer_balances (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status) VALUES (:drawer_id, :user_id, 'ecash', :channel_key, :opening, :credit, :debit, :closing, :date_created, :trans_id, :trans_status)",
            [
                'drawer_id' => $drawerId,
                'user_id' => $drawerUserId,
                'channel_key' => $channelKey,
                'opening' => $opening,
                'credit' => $creditDelta,
                'debit' => $debitDelta,
                'closing' => $closing,
                'date_created' => $now,
                'trans_id' => $transId,
                'trans_status' => $status,
            ]
        );
    } else {
        $rowEcash = $rowsEcash[0];
        $opening = (float)($rowEcash->opening_balance ?? 0);
        $credit = (float)($rowEcash->credit ?? 0) + $creditDelta;
        $debit = (float)($rowEcash->debit ?? 0) + $debitDelta;
        $closing = $opening + $credit - $debit;
        $dbBal->query(
            "UPDATE drawer_balances SET channel_key = :channel_key, credit = :credit, debit = :debit, closing_balance = :closing, trans_id = :trans_id, trans_status = :trans_status WHERE id = :id",
            [
                'channel_key' => $channelKey,
                'credit' => $credit,
                'debit' => $debit,
                'closing' => $closing,
                'trans_id' => $transId,
                'trans_status' => $status,
                'id' => $rowEcash->id,
            ]
        );
    }

    return $drawerContext;
}

function syncBankWithdrawalToCashdrawer(int $bankAccountUserId, int $cashRecipientUserId, string $bankName, array $denos, float $amount, string $status, int $transId): array
{
    $balance = checkEcashChannelSufficiency($bankAccountUserId, $bankName, $amount);
    if (empty($balance['ok'])) {
        return ['ok' => false, 'reason' => 'insufficient_bank_balance', 'balance' => $balance];
    }

    if (empty(syncEcashMovement($bankAccountUserId, $bankName, $amount, $status, $transId, 'out'))) {
        return ['ok' => false, 'reason' => 'bank_balance_update_failed'];
    }

    $drawerContext = syncCashMovementCurrency($cashRecipientUserId, $denos, $amount, $status, $transId, 'in', 'GHS');
    if (empty($drawerContext)) {
        return ['ok' => false, 'reason' => 'cashdrawer_update_failed'];
    }

    return ['ok' => true, 'drawer_context' => $drawerContext];
}

/**
 * Agent Services Helper: Fetch paginated & filtered commissions
 */
if (!function_exists('backoffice_commission_fetch_paginated')) {
    function backoffice_commission_fetch_paginated($commissionModel, array $params, $session = null): array
    {
        $db = new \Core\Database;
        $session = $session ?: get_session();
        $currentUserId = (int)($session->id ?? 0);
        $canViewAll = backoffice_commission_can_view_all($session);

        // Sanitize Pagination inputs
        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = (int)($params['per_page'] ?? 10);
        $allowedPerPage = [10, 25, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }
        $offset = ($page - 1) * $perPage;

        // Build base filters
        $where = ["(m.deleted = 0 OR m.deleted IS NULL)"];
        $queryParams = [];

        // Role/Scope constraints
        if (!$canViewAll) {
            $where[] = "(m.created_by = :curr_user OR m.pay_to = :curr_user OR m.assigned = :curr_user)";
            $queryParams['curr_user'] = $currentUserId;
        }

        // Restrict bank SIM visibility if unprivileged
        $commissionColumns = backoffice_table_columns($db, 'commissions');
        if (!backoffice_commission_can_access_bank_sims($session)) {
            $where[] = backoffice_commission_non_bank_sql_clause('m', $commissionColumns);
        }

        // Status Filter
        $status = trim((string)($params['status'] ?? ''));
        if ($status !== '') {
            $where[] = "LOWER(COALESCE(m.transaction_status, '')) = :status";
            $queryParams['status'] = strtolower($status);
        }

        // Network Filter
        $network = trim((string)($params['network'] ?? ''));
        if ($network !== '') {
            $where[] = "UPPER(COALESCE(m.network, '')) = :network";
            $queryParams['network'] = strtoupper($network);
        }

        // Search Term Filter
        $search = trim((string)($params['search'] ?? ''));
        if ($search !== '') {
            $where[] = "(m.id LIKE :search OR m.amount LIKE :search OR m.notes LIKE :search OR m.network LIKE :search)";
            $queryParams['search'] = '%' . $search . '%';
        }

        $whereSql = implode(' AND ', $where);

        // Count total matching records
        $countSql = "SELECT COUNT(*) AS total FROM commissions m WHERE {$whereSql}";
        $countResult = $db->query($countSql, $queryParams) ?: [];
        $totalItems = (int)($countResult[0]->total ?? 0);
        $totalPages = max(1, ceil($totalItems / $perPage));

        // Retrieve items for page
        $sql = "SELECT m.* FROM commissions m WHERE {$whereSql} ORDER BY m.id DESC LIMIT {$perPage} OFFSET {$offset}";
        $rows = $db->query($sql, $queryParams) ?: [];

        return [
            'data' => $rows,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
            ]
        ];
    }
}
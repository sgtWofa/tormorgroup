<?php

namespace Vaults;

use Core\Database;

function ensureSchema(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $db = new Database;
    $schemaStatements = ["CREATE TABLE IF NOT EXISTS vaults (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(120) NOT NULL,
        branch_id INT UNSIGNED NOT NULL,
        custodian_user_id BIGINT UNSIGNED NULL,
        limit_ghs DECIMAL(14,2) NOT NULL DEFAULT 0,
        limit_cfa DECIMAL(14,2) NOT NULL DEFAULT 0,
        limit_total_ghs DECIMAL(14,2) NOT NULL DEFAULT 0,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_by BIGINT UNSIGNED NOT NULL,
        updated_by BIGINT UNSIGNED NULL,
        date_created DATETIME NOT NULL,
        date_updated DATETIME NULL,
        PRIMARY KEY (id),
        KEY idx_vaults_branch_active (branch_id, active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "CREATE TABLE IF NOT EXISTS vault_stock (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        vault_id BIGINT UNSIGNED NOT NULL,
        currency CHAR(3) NOT NULL,
        denomination DECIMAL(12,2) NOT NULL,
        purchase_rate DECIMAL(12,6) NOT NULL DEFAULT 1,
        note_count BIGINT NOT NULL DEFAULT 0,
        date_updated DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_vault_stock (vault_id, currency, denomination, purchase_rate),
        KEY idx_vault_stock_vault (vault_id, currency)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "CREATE TABLE IF NOT EXISTS vault_transactions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        transaction_type VARCHAR(40) NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'Pending',
        source_vault_id BIGINT UNSIGNED NULL,
        destination_vault_id BIGINT UNSIGNED NULL,
        source_branch_id INT UNSIGNED NULL,
        destination_branch_id INT UNSIGNED NULL,
        cashdrawer_id BIGINT UNSIGNED NULL,
        drawer_user_id BIGINT UNSIGNED NULL,
        agent_user_id BIGINT UNSIGNED NULL,
        currency CHAR(3) NOT NULL,
        amount DECIMAL(14,2) NOT NULL,
        cfa_rate DECIMAL(12,6) NULL,
        denos_json LONGTEXT NULL,
        proof_image VARCHAR(255) NULL,
        requested_by BIGINT UNSIGNED NOT NULL,
        assigned_first_party_id BIGINT UNSIGNED NULL,
        assigned_approver_id BIGINT UNSIGNED NULL,
        reviewed_by BIGINT UNSIGNED NULL,
        reviewed_at DATETIME NULL,
        reject_reason TEXT NULL,
        notes TEXT NULL,
        date_created DATETIME NOT NULL,
        date_updated DATETIME NULL,
        PRIMARY KEY (id),
        KEY idx_vault_tx_status (status),
        KEY idx_vault_tx_source (source_vault_id),
        KEY idx_vault_tx_destination (destination_vault_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "CREATE TABLE IF NOT EXISTS vault_movements (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        vault_id BIGINT UNSIGNED NOT NULL,
        transaction_id BIGINT UNSIGNED NULL,
        reference_type VARCHAR(40) NOT NULL,
        reference_id BIGINT UNSIGNED NOT NULL,
        direction VARCHAR(3) NOT NULL,
        currency CHAR(3) NOT NULL,
        denomination DECIMAL(12,2) NOT NULL,
        purchase_rate DECIMAL(12,6) NOT NULL DEFAULT 1,
        note_count BIGINT NOT NULL,
        amount DECIMAL(14,2) NOT NULL,
        balance_before DECIMAL(14,2) NOT NULL DEFAULT 0,
        balance_after DECIMAL(14,2) NOT NULL DEFAULT 0,
        cfa_rate DECIMAL(12,6) NULL,
        created_by BIGINT UNSIGNED NOT NULL,
        date_created DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_vault_movement_ref (vault_id, reference_type, reference_id, direction, currency, denomination, purchase_rate),
        KEY idx_vault_movement_statement (vault_id, date_created),
        KEY idx_vault_movement_transaction (transaction_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "CREATE TABLE IF NOT EXISTS vault_reconciliations (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        vault_id BIGINT UNSIGNED NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'Pending',
        counted_stock_json LONGTEXT NOT NULL,
        proof_image VARCHAR(255) NULL,
        notes TEXT NULL,
        created_by BIGINT UNSIGNED NOT NULL,
        assigned_approver_id BIGINT UNSIGNED NOT NULL,
        reviewed_by BIGINT UNSIGNED NULL,
        reviewed_at DATETIME NULL,
        system_stock_json LONGTEXT NULL,
        reviewed_stock_json LONGTEXT NULL,
        reject_reason TEXT NULL,
        date_created DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_vault_recon_vault_status (vault_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"];
    foreach ($schemaStatements as $statement) {
        $db->query($statement);
        if ($db->has_error) {
            throw new \RuntimeException('Unable to prepare Vaults storage: ' . $db->error);
        }
    }
    foreach (['vaults', 'vault_stock', 'vault_transactions', 'vault_movements', 'vault_reconciliations'] as $table) {
        $columns = $db->query('SHOW COLUMNS FROM ' . $table) ?: [];
        if ($db->has_error) {
            throw new \RuntimeException('Unable to inspect Vaults storage: ' . $db->error);
        }
        $idColumn = null;
        foreach ($columns as $column) {
            if (strtolower((string)($column->Field ?? '')) === 'id') {
                $idColumn = $column;
                break;
            }
        }
        if (!$idColumn) {
            throw new \RuntimeException('Unable to prepare Vaults storage: ' . $table . '.id is missing.');
        }
        $primary = $db->query("SHOW INDEX FROM {$table} WHERE Key_name = 'PRIMARY'") ?: [];
        if ($db->has_error) {
            throw new \RuntimeException('Unable to inspect Vaults indexes: ' . $db->error);
        }
        $primaryColumn = strtolower((string)($primary[0]->Column_name ?? ''));
        if ($primaryColumn !== '' && $primaryColumn !== 'id') {
            throw new \RuntimeException('Unable to prepare Vaults storage: ' . $table . ' has an unexpected primary key.');
        }
        $hasAutoIncrement = str_contains(strtolower((string)($idColumn->Extra ?? '')), 'auto_increment');
        $alter = [];
        if (!$hasAutoIncrement) {
            $idType = preg_match('/^bigint/i', (string)($idColumn->Type ?? '')) ? 'BIGINT UNSIGNED' : 'INT UNSIGNED';
            $alter[] = 'MODIFY id ' . $idType . ' NOT NULL AUTO_INCREMENT';
        }
        if ($primaryColumn === '') {
            $alter[] = 'ADD PRIMARY KEY (id)';
        }
        if ($alter) {
            $db->query('ALTER TABLE ' . $table . ' ' . implode(', ', $alter));
            if ($db->has_error) {
                throw new \RuntimeException('Unable to enable the Vaults primary key and auto-increment for ' . $table . ': ' . $db->error);
            }
        }
    }
    $stockIndexes = $db->query("SHOW INDEX FROM vault_stock WHERE Key_name = 'uq_vault_stock'") ?: [];
    if (!$stockIndexes) {
        $stockRows = $db->query(
            'SELECT vault_id, currency, denomination, purchase_rate
             FROM vault_stock
             GROUP BY vault_id, currency, denomination, purchase_rate
             HAVING COUNT(*) > 1
             LIMIT 1'
        ) ?: [];
        if ($db->has_error) {
            throw new \RuntimeException('Unable to check Vault stock consistency: ' . $db->error);
        }
        if ($stockRows) {
            throw new \RuntimeException('Vault stock contains duplicate denomination lots and requires manual review before it can be safely upgraded.');
        }
        $db->query('ALTER TABLE vault_stock ADD UNIQUE KEY uq_vault_stock (vault_id, currency, denomination, purchase_rate)');
        if ($db->has_error) {
            throw new \RuntimeException('Unable to protect Vault denomination lots from duplicates: ' . $db->error);
        }
    }
    foreach ([
        'vault_transactions' => [
            'cashdrawer_id' => 'ALTER TABLE vault_transactions ADD COLUMN cashdrawer_id BIGINT UNSIGNED NULL AFTER destination_branch_id',
            'drawer_user_id' => 'ALTER TABLE vault_transactions ADD COLUMN drawer_user_id BIGINT UNSIGNED NULL AFTER cashdrawer_id',
        ],
        'vault_movements' => [
            'balance_before' => 'ALTER TABLE vault_movements ADD COLUMN balance_before DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER amount',
            'balance_after' => 'ALTER TABLE vault_movements ADD COLUMN balance_after DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER balance_before',
        ],
        'vault_reconciliations' => [
            'assigned_approver_id' => 'ALTER TABLE vault_reconciliations ADD COLUMN assigned_approver_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER created_by',
            'system_stock_json' => 'ALTER TABLE vault_reconciliations ADD COLUMN system_stock_json LONGTEXT NULL AFTER counted_stock_json',
            'reviewed_stock_json' => 'ALTER TABLE vault_reconciliations ADD COLUMN reviewed_stock_json LONGTEXT NULL AFTER system_stock_json',
        ],
    ] as $table => $columns) {
        $rows = $db->query('SHOW COLUMNS FROM ' . $table) ?: [];
        if ($db->has_error) {
            throw new \RuntimeException('Unable to inspect Vaults storage: ' . $db->error);
        }
        $existing = [];
        foreach ($rows as $row) {
            $existing[strtolower((string)($row->Field ?? ''))] = true;
        }
        foreach ($columns as $column => $statement) {
            if (!isset($existing[$column])) {
                $db->query($statement);
                if ($db->has_error) {
                    throw new \RuntimeException('Unable to upgrade Vaults storage: ' . $db->error);
                }
            }
        }
    }

    $ready = true;
}

function roleNames($session = null): array
{
    $session = $session ?? \get_session();
    $roles = $session->roles ?? [];
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    return array_values(array_filter(array_map(static function ($role): string {
        return strtolower(trim((string)$role));
    }, $roles)));
}

function canManage(): bool
{
    if (\user_can('is_admin')) {
        return true;
    }
    foreach (roleNames() as $role) {
        if (in_array($role, ['manager', 'operations manager', 'operations_manager', 'op manager', 'op_manager'], true)) {
            return true;
        }
    }
    return false;
}

function canReconcileVault($vault, int $userId): bool
{
    return \user_can('is_admin')
        || ($userId > 0 && (int)($vault->custodian_user_id ?? 0) === $userId);
}

function currentBranchId(int $userId): int
{
    $db = new Database;
    $rows = $db->query('SELECT branch_id FROM users WHERE id = :id LIMIT 1', ['id' => $userId]) ?: [];
    return (int)($rows[0]->branch_id ?? 0);
}

function eligibleStaffToday(): array
{
    $db = new Database;
    if (!$db->table_exists(['users', 'cashdrawers', 'attendance'])) {
        throw new \RuntimeException('Attendance and cashdrawer records are required to assign Vault transaction parties.');
    }
    $today = (new \DateTimeImmutable('now', new \DateTimeZone('Africa/Accra')))->format('Y-m-d');
    $tomorrow = (new \DateTimeImmutable($today, new \DateTimeZone('Africa/Accra')))
        ->modify('+1 day')
        ->format('Y-m-d');
    $rows = $db->query(
        "SELECT DISTINCT u.id, u.first_name, u.last_name, u.branch_id
         FROM users u
         INNER JOIN cashdrawers c ON c.user_id = u.id
             AND c.date_created >= :cashdrawer_start AND c.date_created < :cashdrawer_end
         INNER JOIN attendance a ON a.user_id = u.id AND a.deleted = 0 AND a.date = :attendance_date
             AND UPPER(COALESCE(a.remarks, '')) IN ('PRESENT', 'LATE')
         WHERE u.deleted = 0 AND (u.disabled IS NULL OR u.disabled = 0)
         ORDER BY u.first_name, u.last_name",
        [
            'cashdrawer_start' => $today . ' 00:00:00',
            'cashdrawer_end' => $tomorrow . ' 00:00:00',
            'attendance_date' => $today,
        ]
    );
    if ($db->has_error) {
        throw new \RuntimeException('Unable to verify today’s attendance and cashdrawer eligibility: ' . $db->error);
    }
    return $rows ?: [];
}

function userIsEligibleStaffToday(int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }
    foreach (eligibleStaffToday() as $staff) {
        if ((int)$staff->id === $userId) {
            return true;
        }
    }
    return false;
}

function attentionCount(int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }

    ensureSchema();
    $db = new Database;
    $rows = $db->query(
        "SELECT COUNT(DISTINCT id) AS c
         FROM vault_transactions
         WHERE (assigned_first_party_id = :first_party_id AND status = 'Pending First Party')
            OR (assigned_approver_id = :approver_id AND status = 'Pending Approval')",
        ['first_party_id' => $userId, 'approver_id' => $userId]
    ) ?: [];
    if ($db->has_error) {
        throw new \RuntimeException('Unable to load Vault transaction activity requiring attention: ' . $db->error);
    }

    return (int)($rows[0]->c ?? 0) + reconciliationAttentionCount($userId);
}

function attentionCountForVault(int $userId, int $vaultId): int
{
    if ($userId <= 0 || $vaultId <= 0) {
        return 0;
    }

    return transactionAttentionCountForVault($userId, $vaultId)
        + reconciliationAttentionCountForVault($userId, $vaultId);
}

function attentionCountsForVaults(int $userId, array $vaultIds): array
{
    $counts = ['transactions' => [], 'reconciliations' => []];
    $vaultIds = array_values(array_unique(array_filter(array_map('intval', $vaultIds), static function (int $id): bool {
        return $id > 0;
    })));
    if ($userId <= 0 || !$vaultIds) {
        return $counts;
    }

    ensureSchema();
    $sourcePlaceholders = [];
    $destinationPlaceholders = [];
    $vaultPlaceholders = [];
    $params = [
        'first_party_id' => $userId,
        'approver_id' => $userId,
    ];
    foreach ($vaultIds as $index => $vaultId) {
        $sourceKey = 'source_vault_id_' . $index;
        $destinationKey = 'destination_vault_id_' . $index;
        $vaultKey = 'vault_id_' . $index;
        $sourcePlaceholders[] = ':' . $sourceKey;
        $destinationPlaceholders[] = ':' . $destinationKey;
        $vaultPlaceholders[] = ':' . $vaultKey;
        $params[$sourceKey] = $vaultId;
        $params[$destinationKey] = $vaultId;
    }
    $vaultInClause = implode(',', $vaultPlaceholders);
    $db = new Database;
    $transactionRows = $db->query(
        "SELECT id, source_vault_id, destination_vault_id
         FROM vault_transactions
         WHERE (
             (assigned_first_party_id = :first_party_id AND status = 'Pending First Party')
             OR (assigned_approver_id = :approver_id AND status = 'Pending Approval')
         )
           AND (source_vault_id IN (" . implode(',', $sourcePlaceholders) . ")
                OR destination_vault_id IN (" . implode(',', $destinationPlaceholders) . "))",
        $params
    ) ?: [];
    if ($db->has_error) {
        throw new \RuntimeException('Unable to load Vault attention counts: ' . $db->error);
    }
    foreach ($transactionRows as $row) {
        foreach (['source_vault_id', 'destination_vault_id'] as $field) {
            $vaultId = (int)($row->{$field} ?? 0);
            if ($vaultId > 0 && in_array($vaultId, $vaultIds, true)) {
                $counts['transactions'][$vaultId][(int)$row->id] = true;
            }
        }
    }

    $reconciliationParams = ['approver_id' => $userId, 'creator_id' => $userId];
    foreach ($vaultIds as $index => $vaultId) {
        $reconciliationParams['vault_id_' . $index] = $vaultId;
    }
    $reconciliationRows = $db->query(
        "SELECT r.id, r.vault_id
         FROM vault_reconciliations r
         WHERE r.vault_id IN ({$vaultInClause})
           AND (
               (r.assigned_approver_id = :approver_id AND r.status = 'Pending')
               OR (
                   r.created_by = :creator_id
                   AND r.status = 'Rejected'
                   AND r.id = (
                       SELECT MAX(latest.id)
                       FROM vault_reconciliations latest
                       WHERE latest.vault_id = r.vault_id
                   )
               )
           )",
        $reconciliationParams
    ) ?: [];
    if ($db->has_error) {
        throw new \RuntimeException('Unable to load Vault reconciliation attention counts: ' . $db->error);
    }
    foreach ($reconciliationRows as $row) {
        $vaultId = (int)($row->vault_id ?? 0);
        if ($vaultId > 0) {
            $counts['reconciliations'][$vaultId][(int)$row->id] = true;
        }
    }

    foreach (['transactions', 'reconciliations'] as $type) {
        foreach ($counts[$type] as $vaultId => $ids) {
            $counts[$type][$vaultId] = count($ids);
        }
    }
    return $counts;
}

function reconciliationAttentionCount(int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }

    ensureSchema();
    $db = new Database;
    $pendingRows = $db->query(
        "SELECT COUNT(DISTINCT id) AS c
         FROM vault_reconciliations
         WHERE assigned_approver_id = :approver_id AND status = 'Pending'",
        ['approver_id' => $userId]
    ) ?: [];
    if ($db->has_error) {
        throw new \RuntimeException('Unable to load Vault reconciliations awaiting this user’s review: ' . $db->error);
    }
    $rejectedRows = $db->query(
        "SELECT COUNT(DISTINCT r.id) AS c
         FROM vault_reconciliations r
         WHERE r.created_by = :creator_id
           AND r.status = 'Rejected'
           AND r.id = (
               SELECT MAX(latest.id)
               FROM vault_reconciliations latest
               WHERE latest.vault_id = r.vault_id
           )",
        ['creator_id' => $userId]
    ) ?: [];
    if ($db->has_error) {
        throw new \RuntimeException('Unable to load Vault reconciliations requiring resubmission: ' . $db->error);
    }

    return (int)($pendingRows[0]->c ?? 0) + (int)($rejectedRows[0]->c ?? 0);
}

function reconciliationAttentionCountForVault(int $userId, int $vaultId): int
{
    if ($userId <= 0 || $vaultId <= 0) {
        return 0;
    }

    ensureSchema();
    $db = new Database;
    $rows = $db->query(
        "SELECT COUNT(DISTINCT r.id) AS c
         FROM vault_reconciliations r
         WHERE r.vault_id = :vault_id
           AND (
               (r.assigned_approver_id = :approver_id AND r.status = 'Pending')
               OR (
                   r.created_by = :creator_id
                   AND r.status = 'Rejected'
                   AND r.id = (
                       SELECT MAX(latest.id)
                       FROM vault_reconciliations latest
                       WHERE latest.vault_id = r.vault_id
                   )
               )
           )",
        [
            'vault_id' => $vaultId,
            'approver_id' => $userId,
            'creator_id' => $userId,
        ]
    ) ?: [];
    if ($db->has_error) {
        throw new \RuntimeException('Unable to load reconciliation attention for this vault: ' . $db->error);
    }

    return (int)($rows[0]->c ?? 0);
}

function transactionAttentionCountForVault(int $userId, int $vaultId): int
{
    if ($userId <= 0 || $vaultId <= 0) {
        return 0;
    }

    ensureSchema();
    $db = new Database;
    $rows = $db->query(
        "SELECT COUNT(DISTINCT id) AS c
         FROM vault_transactions
         WHERE (source_vault_id = :source_vault_id OR destination_vault_id = :destination_vault_id)
           AND (
               (assigned_first_party_id = :first_party_id AND status = 'Pending First Party')
               OR (assigned_approver_id = :approver_id AND status = 'Pending Approval')
           )",
        [
            'source_vault_id' => $vaultId,
            'destination_vault_id' => $vaultId,
            'first_party_id' => $userId,
            'approver_id' => $userId,
        ]
    ) ?: [];
    if ($db->has_error) {
        throw new \RuntimeException('Unable to load Vault workflow items requiring attention: ' . $db->error);
    }

    return (int)($rows[0]->c ?? 0);
}

function visibleVaults(int $userId): array
{
    $db = new Database;
    $sql = "SELECT v.*, b.name AS branch_name, u.first_name, u.last_name
        FROM vaults v
        LEFT JOIN branches b ON b.id = v.branch_id
        LEFT JOIN users u ON u.id = v.custodian_user_id
        WHERE 1 = 1";
    $params = [];
    if (!\user_can('is_admin')) {
        $sql .= ' AND v.active = 1';
    }
    if (!canManage()) {
        $branchId = currentBranchId($userId);
        if ($branchId <= 0) {
            return [];
        }
        $sql .= ' AND v.branch_id = :branch_id';
        $params['branch_id'] = $branchId;
    }
    $sql .= ' ORDER BY b.name, v.name';
    return $db->query($sql, $params) ?: [];
}

function activeVaultsForTransfers(): array
{
    $db = new Database;
    return $db->query(
        "SELECT v.*, b.name AS branch_name
         FROM vaults v
         LEFT JOIN branches b ON b.id = v.branch_id
         WHERE v.active = 1
         ORDER BY b.name, v.name"
    ) ?: [];
}

function getVault(int $vaultId, int $userId, bool $lock = false, bool $includeInactive = false)
{
    if ($vaultId <= 0) {
        return null;
    }
    $db = new Database;
    $rows = $db->query(
        'SELECT v.* FROM vaults v WHERE v.id = :id' . ($includeInactive ? '' : ' AND v.active = 1') . ' LIMIT 1' . ($lock ? ' FOR UPDATE' : ''),
        ['id' => $vaultId]
    ) ?: [];
    $vault = $rows[0] ?? null;
    if (!$vault) {
        return null;
    }
    if (!canManage() && (int)$vault->branch_id !== currentBranchId($userId)) {
        return null;
    }
    return $vault;
}

function getActiveVaultForTransfer(int $vaultId, bool $lock = false)
{
    if ($vaultId <= 0) {
        return null;
    }
    $db = new Database;
    $rows = $db->query(
        'SELECT * FROM vaults WHERE id = :id AND active = 1 LIMIT 1' . ($lock ? ' FOR UPDATE' : ''),
        ['id' => $vaultId]
    ) ?: [];
    return $rows[0] ?? null;
}

function configuredCfaRate(): ?float
{
    $db = new Database;
    $rows = $db->query('SELECT * FROM settings WHERE id = 1 LIMIT 1') ?: [];
    if ($db->has_error) {
        throw new \RuntimeException('Unable to read the configured CFA exchange rate: ' . $db->error);
    }
    $settings = $rows[0] ?? null;
    foreach (['cfa_rate', 'buying_rate', 'selling_rate'] as $field) {
        $rate = (float)($settings->{$field} ?? 0);
        if ($rate > 0) {
            return $rate;
        }
    }
    return null;
}

function currentCfaRate(): float
{
    $rate = configuredCfaRate();
    if ($rate === null || $rate <= 0) {
        throw new \RuntimeException('The current CFA rate is missing or invalid in Settings.');
    }
    return $rate;
}

function stockRows(int $vaultId): array
{
    $db = new Database;
    return $db->query(
        'SELECT currency, denomination, purchase_rate, note_count FROM vault_stock WHERE vault_id = :vault_id AND note_count > 0 ORDER BY currency, purchase_rate, denomination DESC',
        ['vault_id' => $vaultId]
    ) ?: [];
}

function currencyBalance(int $vaultId, string $currency): float
{
    $db = new Database;
    $rows = $db->query(
        'SELECT COALESCE(SUM(denomination * note_count), 0) AS balance FROM vault_stock WHERE vault_id = :vault_id AND currency = :currency',
        ['vault_id' => $vaultId, 'currency' => $currency]
    ) ?: [];
    return (float)($rows[0]->balance ?? 0);
}

function hasSufficientStock(int $vaultId, string $currency, array $denominations): bool
{
    $db = new Database;
    foreach ($denominations as $line) {
        $rows = $db->query(
            'SELECT COALESCE(SUM(note_count), 0) AS available FROM vault_stock
             WHERE vault_id = :vault_id AND currency = :currency AND denomination = :denomination',
            [
                'vault_id' => $vaultId,
                'currency' => $currency,
                'denomination' => (float)$line['denomination'],
            ]
        ) ?: [];
        if ((int)($rows[0]->available ?? 0) < (int)$line['count']) {
            return false;
        }
    }
    return true;
}

function denominationAvailability(int $vaultId, string $currency, array $denominations, ?float $purchaseRate = null): array
{
    $db = new Database;
    $stockRows = $db->query(
        'SELECT denomination, purchase_rate, note_count FROM vault_stock
         WHERE vault_id = :vault_id AND currency = :currency AND note_count > 0',
        ['vault_id' => $vaultId, 'currency' => $currency]
    ) ?: [];
    if ($db->has_error) {
        throw new \RuntimeException('Unable to check source vault denomination availability: ' . $db->error);
    }

    $availableByDenomination = [];
    foreach ($stockRows as $stockRow) {
        if ($purchaseRate !== null && abs((float)$stockRow->purchase_rate - $purchaseRate) > 0.000001) {
            continue;
        }
        $key = number_format((float)$stockRow->denomination, 2, '.', '');
        $availableByDenomination[$key] = ($availableByDenomination[$key] ?? 0) + (int)$stockRow->note_count;
    }

    $availability = [];
    foreach ($denominations as $line) {
        $denomination = (float)$line['denomination'];
        $requested = (int)$line['count'];
        $available = (int)($availableByDenomination[number_format($denomination, 2, '.', '')] ?? 0);
        $availability[] = [
            'denomination' => $denomination,
            'requested' => $requested,
            'available' => $available,
            'shortage' => max(0, $requested - $available),
        ];
    }
    return $availability;
}

function insufficientDenominationMessage(string $currency, array $availability): string
{
    $lines = [];
    foreach ($availability as $line) {
        $denomination = (float)$line['denomination'];
        $unit = $currency === 'GHS' && $denomination < 1 ? 'coins' : 'notes';
        $label = $currency === 'GHS'
            ? 'GH₵ ' . number_format($denomination, 2)
            : number_format($denomination, 0) . ' CFA';
        $state = (int)$line['shortage'] > 0 ? 'INSUFFICIENT' : 'OK';
        $lines[] = $label . ' (' . $unit . '): requested ' . number_format((int)$line['requested'])
            . ', available ' . number_format((int)$line['available'])
            . ', short ' . number_format((int)$line['shortage']) . ' — ' . $state;
    }
    return 'Source vault denomination check (' . $currency . '): ' . implode('; ', $lines);
}

function validateDenominations(string $currency, $json): array
{
    if (is_string($json)) {
        $rows = json_decode($json, true);
    } else {
        $rows = $json;
    }
    if (!is_array($rows)) {
        throw new \InvalidArgumentException('Provide a valid denomination breakdown.');
    }
    $allowed = $currency === 'GHS'
        ? [200, 100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1]
        : [10000, 5000, 2000, 1000, 500, 200, 100, 50, 25, 10, 5];
    $clean = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $denomination = (float)($row['denomination'] ?? 0);
        $count = filter_var($row['count'] ?? null, FILTER_VALIDATE_INT);
        if ($count === false || $count < 0 || $denomination <= 0 || !in_array($denomination, array_map('floatval', $allowed), true)) {
            throw new \InvalidArgumentException('The denomination breakdown contains an invalid note or count.');
        }
        if ($count > 0) {
            $clean[] = ['denomination' => $denomination, 'count' => $count];
        }
    }
    if (!$clean) {
        throw new \InvalidArgumentException('Enter at least one denomination and count.');
    }
    return $clean;
}

function denominationTotal(array $rows): float
{
    return array_reduce($rows, static function (float $sum, array $row): float {
        return $sum + ((float)$row['denomination'] * (int)$row['count']);
    }, 0.0);
}

function setStock(int $vaultId, string $currency, float $denomination, float $rate, int $count): void
{
    $db = new Database;
    if ($count < 0) {
        throw new \RuntimeException('Vault stock cannot be negative.');
    }
    if ($count === 0) {
        $db->query(
            'DELETE FROM vault_stock WHERE vault_id = :vault_id AND currency = :currency AND denomination = :denomination AND purchase_rate = :rate',
            ['vault_id' => $vaultId, 'currency' => $currency, 'denomination' => $denomination, 'rate' => $rate]
        );
        if ($db->has_error) {
            throw new \RuntimeException('Unable to remove zero-count vault stock: ' . $db->error);
        }
        return;
    }
    $db->query(
        'INSERT INTO vault_stock (vault_id, currency, denomination, purchase_rate, note_count, date_updated)
         VALUES (:vault_id, :currency, :denomination, :rate, :count, :now)
         ON DUPLICATE KEY UPDATE note_count = VALUES(note_count), date_updated = VALUES(date_updated)',
        [
            'vault_id' => $vaultId,
            'currency' => $currency,
            'denomination' => $denomination,
            'rate' => $rate,
            'count' => $count,
            'now' => date('Y-m-d H:i:s'),
        ]
    );
    if ($db->has_error) {
        throw new \RuntimeException('Unable to update vault stock: ' . $db->error);
    }
}

function recordMovement(
    int $vaultId,
    string $direction,
    string $currency,
    array $denominations,
    float $rate,
    string $referenceType,
    int $referenceId,
    int $createdBy,
    ?int $transactionId = null,
    ?float $cfaRateSnapshot = null
): void {
    $db = new Database;
    $vault = getVault($vaultId, $createdBy, true);
    if (!$vault) {
        throw new \RuntimeException('Vault not found or not accessible.');
    }
    foreach ($denominations as $row) {
        $denomination = (float)$row['denomination'];
        $count = (int)$row['count'];
        $balanceBefore = currencyBalance($vaultId, $currency);
        $existing = $db->query(
            'SELECT id, note_count FROM vault_stock WHERE vault_id = :vault_id AND currency = :currency AND denomination = :denomination AND purchase_rate = :rate LIMIT 1 FOR UPDATE',
            ['vault_id' => $vaultId, 'currency' => $currency, 'denomination' => $denomination, 'rate' => $rate]
        ) ?: [];
        if ($db->has_error) {
            throw new \RuntimeException('Unable to read vault stock: ' . $db->error);
        }
        $oldCount = (int)($existing[0]->note_count ?? 0);
        $newCount = $direction === 'in' ? $oldCount + $count : $oldCount - $count;
        if ($newCount < 0) {
            throw new \RuntimeException('Insufficient ' . $currency . ' notes for denomination ' . number_format($denomination, 2) . '.');
        }
        setStock($vaultId, $currency, $denomination, $rate, $newCount);
        $balanceAfter = currencyBalance($vaultId, $currency);
        $db->query(
            'INSERT INTO vault_movements (vault_id, transaction_id, reference_type, reference_id, direction, currency, denomination, purchase_rate, note_count, amount, balance_before, balance_after, cfa_rate, created_by, date_created)
             VALUES (:vault_id, :transaction_id, :reference_type, :reference_id, :direction, :currency, :denomination, :rate, :count, :amount, :balance_before, :balance_after, :cfa_rate, :created_by, :date_created)',
            [
                'vault_id' => $vaultId,
                'transaction_id' => $transactionId,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'direction' => $direction,
                'currency' => $currency,
                'denomination' => $denomination,
                'rate' => $rate,
                'count' => $count,
                'amount' => $denomination * $count,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'cfa_rate' => $currency === 'CFA' ? $cfaRateSnapshot : null,
                'created_by' => $createdBy,
                'date_created' => date('Y-m-d H:i:s'),
            ]
        );
        if ($db->has_error) {
            throw new \RuntimeException('Unable to save vault movement: ' . $db->error);
        }
    }
}

function applyBankTransfer(int $vaultId, string $direction, string $currency, array $denominations, int $bankTransferId, int $userId): void
{
    if (!in_array($direction, ['in', 'out'], true)) {
        throw new \InvalidArgumentException('Invalid bank movement direction.');
    }
    if ($currency !== 'GHS') {
        throw new \InvalidArgumentException('Bank transfers into or out of a vault support GHS only.');
    }
    $db = new Database;
    $started = !$db->inTransaction();
    if ($started && !$db->beginTransaction()) {
        throw new \RuntimeException('Unable to start the vault bank movement.');
    }
    try {
        recordMovement($vaultId, $direction, $currency, $denominations, 1, 'bank_transfer', $bankTransferId, $userId);
        if ($direction === 'in') {
            $vault = getVault($vaultId, $userId);
            $balance = balanceSummary($vaultId);
            if ($vault && (((float)$vault->limit_ghs > 0 && $balance['GHS'] > (float)$vault->limit_ghs + 0.01)
                || ((float)$vault->limit_total_ghs > 0 && $balance['GHS'] + $balance['CFA_GHS_VALUE'] > (float)$vault->limit_total_ghs + 0.01))) {
                throw new \RuntimeException('This deposit would exceed the destination vault limit.');
            }
        }
        if ($started && !$db->commit()) {
            throw new \RuntimeException('Unable to commit the vault bank movement.');
        }
    } catch (\Throwable $e) {
        if ($started) {
            $db->rollBack();
        }
        throw $e;
    }
}

function balanceSummary(int $vaultId): array
{
    $result = ['GHS' => 0.0, 'CFA' => 0.0, 'CFA_GHS_VALUE' => 0.0];
    foreach (stockRows($vaultId) as $row) {
        $amount = (float)$row->denomination * (int)$row->note_count;
        $currency = (string)$row->currency;
        if (array_key_exists($currency, $result)) {
            $result[$currency] += $amount;
        }
        if ($currency === 'CFA') {
            $result['CFA_GHS_VALUE'] += $amount * (float)$row->purchase_rate / 1000;
        }
    }
    return $result;
}

function userHasHigherRole(int $userId): bool
{
    $db = new Database;
    $roles = ['admin', 'manager', 'operations manager', 'operations_manager', 'op manager', 'op_manager'];
    $placeholders = [];
    $params = ['user_id' => $userId];
    foreach ($roles as $index => $role) {
        $key = 'role' . $index;
        $placeholders[] = ':' . $key;
        $params[$key] = $role;
    }
    $rows = $db->query(
        'SELECT 1 FROM user_roles_map m
         INNER JOIN user_roles r ON r.id = m.role_id
         WHERE m.user_id = :user_id
           AND LOWER(REPLACE(TRIM(r.role), "_", " ")) IN (' . implode(',', $placeholders) . ')
           AND (m.disabled = 0 OR m.disabled IS NULL)
           AND (r.disabled = 0 OR r.disabled IS NULL)
         LIMIT 1',
        $params
    ) ?: [];
    return !empty($rows);
}

function applyVaultTransaction($transaction, int $reviewerId): void
{
    $status = strtolower(trim((string)($transaction->status ?? '')));
    if (in_array($status, ['rejected', 'cancelled', 'canceled', 'declined'], true)) {
        throw new \RuntimeException('Rejected or cancelled Vault transactions cannot update stock.');
    }

    $db = new Database;
    $transactionId = (int)$transaction->id;
    $currency = (string)$transaction->currency;
    $isExchange = (string)$transaction->transaction_type === 'denos_exchange';
    if ($isExchange) {
        $exchange = json_decode((string)$transaction->denos_json, true);
        if (!is_array($exchange) || !isset($exchange['out'], $exchange['in'])) {
            throw new \RuntimeException('The denomination exchange breakdown is invalid.');
        }
        $outDenominations = validateDenominations($currency, $exchange['out']);
        $inDenominations = validateDenominations($currency, $exchange['in']);
        if (abs(denominationTotal($outDenominations) - denominationTotal($inDenominations)) > 0.01
            || abs(denominationTotal($outDenominations) - (float)$transaction->amount) > 0.01) {
            throw new \RuntimeException('Given and received denominations must have equal totals.');
        }
    } else {
        $outDenominations = validateDenominations($currency, (string)$transaction->denos_json);
        $inDenominations = $outDenominations;
        if (abs(denominationTotal($outDenominations) - (float)$transaction->amount) > 0.01) {
            throw new \RuntimeException('The denomination total no longer matches the transaction amount.');
        }
    }

    $sourceId = (int)($transaction->source_vault_id ?? 0);
    $destinationId = (int)($transaction->destination_vault_id ?? 0);
    if ($sourceId > 0 && $destinationId > 0 && $sourceId === $destinationId && !$isExchange) {
        throw new \RuntimeException('Source and destination vaults must be different.');
    }
    if ($isExchange && ($sourceId <= 0 || $sourceId !== $destinationId)) {
        throw new \RuntimeException('A denomination exchange must use the same source and destination vault.');
    }
    $rate = $currency === 'CFA' ? (float)($transaction->cfa_rate ?? 0) : 1.0;
    if ($currency === 'CFA' && $rate <= 0) {
        throw new \RuntimeException('The CFA purchase rate for this transaction is invalid.');
    }

    $sourceLots = [];
    if ($sourceId > 0) {
        $source = getVault($sourceId, $reviewerId, true);
        if (!$source) {
            throw new \RuntimeException('The source vault is not available.');
        }
        $shortages = [];
        foreach ($outDenominations as $requested) {
            $needed = (int)$requested['count'];
            $rateFilter = $isExchange && $currency === 'CFA' ? ' AND purchase_rate = :purchase_rate' : '';
            $lotParams = [
                'vault_id' => $sourceId,
                'currency' => $currency,
                'denomination' => $requested['denomination'],
            ];
            if ($rateFilter !== '') {
                $lotParams['purchase_rate'] = $rate;
            }
            $lots = $db->query(
                'SELECT id, denomination, purchase_rate, note_count FROM vault_stock
                 WHERE vault_id = :vault_id AND currency = :currency AND denomination = :denomination
                   AND note_count > 0' . $rateFilter . '
                 ORDER BY id ASC FOR UPDATE',
                $lotParams
            ) ?: [];
            foreach ($lots as $lot) {
                if ($needed <= 0) {
                    break;
                }
                $take = min($needed, (int)$lot->note_count);
                if ($take > 0) {
                    $sourceLots[] = [
                        'denomination' => (float)$lot->denomination,
                        'purchase_rate' => (float)$lot->purchase_rate,
                        'count' => $take,
                    ];
                    $needed -= $take;
                }
            }
            $available = (int)$requested['count'] - $needed;
            $shortages[] = [
                'denomination' => (float)$requested['denomination'],
                'requested' => (int)$requested['count'],
                'available' => $available,
                'shortage' => max(0, $needed),
            ];
            if ($needed > 0) {
                continue;
            }
        }
        if (array_filter($shortages, static function (array $line): bool { return $line['shortage'] > 0; })) {
            throw new \RuntimeException(insufficientDenominationMessage($currency, $shortages));
        }
        foreach ($sourceLots as $lot) {
            recordMovement($sourceId, 'out', $currency, [$lot], (float)$lot['purchase_rate'], 'vault_transaction', $transactionId, $reviewerId, $transactionId, $rate ?: null);
        }
    }

    if ($destinationId > 0) {
        $destination = getVault($destinationId, $reviewerId, true);
        if (!$destination) {
            throw new \RuntimeException('The destination vault is not available.');
        }
        $incomingLots = !$isExchange && $sourceId > 0
            ? $sourceLots
            : array_map(static function (array $row) use ($rate): array {
                return [
                    'denomination' => (float)$row['denomination'],
                    'purchase_rate' => $rate,
                    'count' => (int)$row['count'],
                ];
            }, $inDenominations);
        foreach ($incomingLots as $lot) {
            recordMovement($destinationId, 'in', $currency, [$lot], (float)$lot['purchase_rate'], 'vault_transaction', $transactionId, $reviewerId, $transactionId, $rate ?: null);
        }

        $current = balanceSummary($destinationId);
        $incomingValueGhs = $currency === 'GHS'
            ? (float)$transaction->amount
            : (float)$transaction->amount * $rate / 1000;
        $totalAfter = $current['GHS'] + $current['CFA_GHS_VALUE'];
        $totalLimit = (float)$destination->limit_total_ghs;
        if ($totalLimit > 0 && $totalAfter > $totalLimit + 0.01) {
            throw new \RuntimeException('This movement would exceed the vault total cash limit.');
        }
        if ($currency === 'GHS' && (float)$destination->limit_ghs > 0 && $current['GHS'] > (float)$destination->limit_ghs + 0.01) {
            throw new \RuntimeException('This movement would exceed the vault GHS limit.');
        }
        if ($currency === 'CFA' && (float)$destination->limit_cfa > 0 && $current['CFA'] > (float)$destination->limit_cfa + 0.01) {
            throw new \RuntimeException('This movement would exceed the vault CFA limit.');
        }
        if ($incomingValueGhs < 0) {
            throw new \RuntimeException('Invalid incoming amount.');
        }
    }

    if (in_array((string)$transaction->transaction_type, ['to_agent', 'from_agent'], true)) {
        $agentUserId = (int)($transaction->requested_by ?? $transaction->agent_user_id ?? 0);
        if ($agentUserId <= 0 || !function_exists('BackOfficeCashdrawer\\syncCashMovementCurrency')) {
            throw new \RuntimeException('The requesting agent cashdrawer service is unavailable.');
        }
        $expectedBranchId = (string)$transaction->transaction_type === 'to_agent'
            ? (int)($transaction->source_branch_id ?? 0)
            : (int)($transaction->destination_branch_id ?? 0);
        $agentRows = $db->query(
            'SELECT branch_id FROM users WHERE id = :id AND (disabled IS NULL OR disabled = 0) LIMIT 1',
            ['id' => $agentUserId]
        ) ?: [];
        if (!$agentRows || $expectedBranchId <= 0 || (int)$agentRows[0]->branch_id !== $expectedBranchId) {
            throw new \RuntimeException('The requesting agent is no longer assigned to the Vault branch for this movement.');
        }
        $drawerDenominations = array_map(static function (array $row) use ($currency): array {
            return [
                'currency' => $currency,
                'denomination' => (float)$row['denomination'],
                'count' => (int)$row['count'],
                'volume' => (float)$row['denomination'] * (int)$row['count'],
            ];
        }, $outDenominations);
        $direction = (string)$transaction->transaction_type === 'to_agent' ? 'in' : 'out';
        $drawerContext = \BackOfficeCashdrawer\syncCashMovementCurrency(
            $agentUserId,
            $drawerDenominations,
            (float)$transaction->amount,
            'Approved',
            $transactionId,
            $direction,
            $currency,
            'Vault'
        );
        if (empty($drawerContext)) {
            throw new \RuntimeException($direction === 'in'
                ? 'Unable to credit the requesting agent. Confirm they have an active cashdrawer today.'
                : 'The requesting agent cashdrawer does not have the required denomination counts.');
        }
    }
}

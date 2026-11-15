<?php

date_default_timezone_set('Africa/Accra');

$session = get_session();
$userId = (int)($session->id ?? 0);
$req = new \Core\Request;
$postdata = $req->post();
$adminRoute = 'admin';
$pluginRoute = 'vaults';
$isBackofficeEmbed = ($postdata['vault_return_to'] ?? '') === 'backoffice';
$returnUrl = $isBackofficeEmbed
    ? $adminRoute . '/back_office/list'
    : $adminRoute . '/' . $pluginRoute;
$vaultReturnUrl = static function (int $vaultId, bool $operations = false) use ($isBackofficeEmbed, $returnUrl): string {
    if ($isBackofficeEmbed) {
        return $returnUrl . '?vault_id=' . $vaultId . '#vaults';
    }
    return $returnUrl . ($operations ? '/operations' : '') . '?vault_id=' . $vaultId;
};
$db = new \Core\Database;
$notifyVaultRecipient = static function (
    string $type,
    int $transactionId,
    int $vaultId,
    int $recipientId,
    string $status,
    string $notificationLabel,
    array $details
): void {
    if ($recipientId <= 0) {
        return;
    }
    backoffice_whatsapp_notify_transaction([
        'type' => $type,
        'id' => $transactionId,
        'status' => $status,
        'recipient_id' => $recipientId,
        'recipient_label' => 'assigned staff member',
        'notification_label' => $notificationLabel,
        'details' => $details,
        'view_url' => ROOT . '/admin/back_office/list?vault_id=' . $vaultId . '#vaults',
    ]);
};

if (!csrf_verify($postdata)) {
    message_fail('Form expired. Refresh the page and try again.');
    redirect($returnUrl);
    exit;
}

$action = trim((string)($postdata['action'] ?? ''));
$now = date('Y-m-d H:i:s');

try {
    if ($action === 'create_vault') {
        if (!\Vaults\canManage()) {
            throw new \RuntimeException('Only managers, operations managers, and admins can create or assign vaults.');
        }
        $name = trim((string)($postdata['name'] ?? ''));
        $branchId = (int)($postdata['branch_id'] ?? 0);
        $custodianId = (int)($postdata['custodian_user_id'] ?? 0);
        $limitGhs = (float)($postdata['limit_ghs'] ?? 0);
        $limitCfa = (float)($postdata['limit_cfa'] ?? 0);
        if ($name === '' || $branchId <= 0 || $custodianId <= 0 || $limitGhs < 0 || $limitCfa < 0) {
            throw new \InvalidArgumentException('Enter a vault name, branch, custodian, and valid non-negative limits.');
        }
        $branches = $db->query('SELECT id FROM branches WHERE id = :id AND active = 1 AND deleted = 0 LIMIT 1', ['id' => $branchId]) ?: [];
        $custodians = $db->query('SELECT id FROM users WHERE id = :id AND branch_id = :branch_id AND (disabled IS NULL OR disabled = 0) LIMIT 1', [
            'id' => $custodianId,
            'branch_id' => $branchId,
        ]) ?: [];
        if (!$branches || !$custodians) {
            throw new \InvalidArgumentException('Select an active branch and an active staff custodian assigned to that branch.');
        }

        $ghsOpeningText = trim((string)($postdata['ghs_opening_json'] ?? '[]'));
        $ghsOpening = $ghsOpeningText === '' || $ghsOpeningText === '[]'
            ? []
            : \Vaults\validateDenominations('GHS', $ghsOpeningText);
        $cfaOpeningText = trim((string)($postdata['cfa_opening_json'] ?? '[]'));
        $cfaOpening = $cfaOpeningText === '' || $cfaOpeningText === '[]'
            ? []
            : \Vaults\validateDenominations('CFA', $cfaOpeningText);
        $cfaRate = ($limitCfa > 0 || !empty($cfaOpening)) ? \Vaults\currentCfaRate() : 0.0;
        $limitTotal = round($limitGhs + ($limitCfa * $cfaRate / 1000), 2);
        $ghsAmount = \Vaults\denominationTotal($ghsOpening);
        $cfaAmount = \Vaults\denominationTotal($cfaOpening);
        if ($limitGhs > 0 && $ghsAmount > $limitGhs + 0.01) {
            throw new \InvalidArgumentException(sprintf(
                'Opening GHS cash is GH₵ %s, above the GHS balance limit of GH₵ %s. Increase the GHS limit or reduce the GHS note counts.',
                number_format($ghsAmount, 2),
                number_format($limitGhs, 2)
            ));
        }
        if ($limitCfa > 0 && $cfaAmount > $limitCfa + 0.01) {
            throw new \InvalidArgumentException(sprintf(
                'Opening CFA cash is %s CFA, above the CFA balance limit of %s CFA. Increase the CFA limit or reduce the CFA note counts.',
                number_format($cfaAmount, 0),
                number_format($limitCfa, 0)
            ));
        }
        $openingTotalGhs = $ghsAmount + ($cfaAmount * $cfaRate / 1000);
        if ($limitTotal > 0 && $openingTotalGhs > $limitTotal + 0.01) {
            throw new \InvalidArgumentException(sprintf(
                'Combined opening cash is worth GH₵ %s at the current CFA rate, above the cumulative limit of GH₵ %s. Increase the GHS/CFA limits or reduce the opening note counts.',
                number_format($openingTotalGhs, 2),
                number_format($limitTotal, 2)
            ));
        }

        if (!$db->beginTransaction()) {
            throw new \RuntimeException('Unable to start vault creation.');
        }
        $db->query(
            'INSERT INTO vaults (name, branch_id, custodian_user_id, limit_ghs, limit_cfa, limit_total_ghs, active, created_by, date_created)
             VALUES (:name, :branch_id, :custodian_user_id, :limit_ghs, :limit_cfa, :limit_total_ghs, 1, :created_by, :date_created)',
            [
                'name' => $name,
                'branch_id' => $branchId,
                'custodian_user_id' => $custodianId,
                'limit_ghs' => $limitGhs,
                'limit_cfa' => $limitCfa,
                'limit_total_ghs' => $limitTotal,
                'created_by' => $userId,
                'date_created' => $now,
            ]
        );
        if ($db->has_error || $db->insert_id <= 0) {
            throw new \RuntimeException('Unable to save the vault record: ' . ((string)$db->error ?: 'the database did not return a generated vault ID.'));
        }
        $vaultId = (int)$db->insert_id;
        foreach ($ghsOpening as $line) {
            \Vaults\recordMovement($vaultId, 'in', 'GHS', [$line], 1, 'opening_stock', $vaultId, $userId);
        }
        foreach ($cfaOpening as $line) {
            \Vaults\recordMovement($vaultId, 'in', 'CFA', [$line], $cfaRate, 'opening_stock', $vaultId, $userId, null, $cfaRate);
        }
        if (!$db->commit()) {
            throw new \RuntimeException('Unable to commit the vault record.');
        }
        message_success('Vault created and assigned successfully.');
        redirect($vaultReturnUrl($vaultId, true));
        exit;
    }

    if ($action === 'update_limits') {
        if (!\Vaults\canManage()) {
            throw new \RuntimeException('You are not allowed to change vault limits.');
        }
        $vaultId = (int)($postdata['vault_id'] ?? 0);
        $limitGhs = (float)($postdata['limit_ghs'] ?? -1);
        $limitCfa = (float)($postdata['limit_cfa'] ?? -1);
        if ($vaultId <= 0 || $limitGhs < 0 || $limitCfa < 0) {
            throw new \InvalidArgumentException('Enter valid, non-negative limits.');
        }
        $cfaRate = $limitCfa > 0 ? \Vaults\currentCfaRate() : 0.0;
        $limitTotal = round($limitGhs + ($limitCfa * $cfaRate / 1000), 2);
        $vault = \Vaults\getVault($vaultId, $userId, true);
        if (!$vault) {
            throw new \RuntimeException('Vault not found.');
        }
        $balance = \Vaults\balanceSummary($vaultId);
        if (($limitGhs > 0 && $balance['GHS'] > $limitGhs + 0.01)
            || ($limitCfa > 0 && $balance['CFA'] > $limitCfa + 0.01)
            || ($limitTotal > 0 && $balance['GHS'] + $balance['CFA_GHS_VALUE'] > $limitTotal + 0.01)) {
            throw new \InvalidArgumentException('New limits cannot be lower than the vault’s current balances.');
        }
        $db->query(
            'UPDATE vaults SET limit_ghs = :limit_ghs, limit_cfa = :limit_cfa, limit_total_ghs = :limit_total_ghs, updated_by = :updated_by, date_updated = :date_updated WHERE id = :id',
            [
                'limit_ghs' => $limitGhs,
                'limit_cfa' => $limitCfa,
                'limit_total_ghs' => $limitTotal,
                'updated_by' => $userId,
                'date_updated' => $now,
                'id' => $vaultId,
            ]
        );
        if ($db->has_error) {
            throw new \RuntimeException('Unable to update vault limits.');
        }
        message_success('Vault limits updated.');
        redirect($vaultReturnUrl($vaultId, true));
        exit;
    }

    if ($action === 'edit_vault') {
        if (!\user_can('is_admin')) {
            throw new \RuntimeException('Only an admin can edit Vault details or change its status.');
        }
        $vaultId = (int)($postdata['vault_id'] ?? 0);
        $name = trim((string)($postdata['name'] ?? ''));
        $branchId = (int)($postdata['branch_id'] ?? 0);
        $custodianId = (int)($postdata['custodian_user_id'] ?? 0);
        $limitGhs = filter_var($postdata['limit_ghs'] ?? null, FILTER_VALIDATE_FLOAT);
        $limitCfa = filter_var($postdata['limit_cfa'] ?? null, FILTER_VALIDATE_FLOAT);
        $active = (int)($postdata['active'] ?? 0) === 1 ? 1 : 0;
        if ($vaultId <= 0 || $name === '' || mb_strlen($name) > 120
            || $branchId <= 0 || $custodianId <= 0
            || $limitGhs === false || $limitCfa === false || $limitGhs < 0 || $limitCfa < 0) {
            throw new \InvalidArgumentException('Enter a vault name, branch, custodian, and valid non-negative limits.');
        }
        $branchRows = $db->query(
            'SELECT id FROM branches WHERE id = :id AND active = 1 AND deleted = 0 LIMIT 1',
            ['id' => $branchId]
        ) ?: [];
        $custodianRows = $db->query(
            'SELECT id FROM users WHERE id = :id AND branch_id = :branch_id AND (disabled IS NULL OR disabled = 0) LIMIT 1',
            ['id' => $custodianId, 'branch_id' => $branchId]
        ) ?: [];
        if ($db->has_error) {
            throw new \RuntimeException('Unable to verify the selected branch and custodian: ' . $db->error);
        }
        if (!$branchRows || !$custodianRows) {
            throw new \InvalidArgumentException('Select an active branch and a custodian assigned to that branch.');
        }
        $cfaRate = $limitCfa > 0 ? \Vaults\currentCfaRate() : 0.0;
        $limitTotal = round($limitGhs + ($limitCfa * $cfaRate / 1000), 2);
        if (!$db->beginTransaction()) {
            throw new \RuntimeException('Unable to start the Vault update.');
        }
        $vault = \Vaults\getVault($vaultId, $userId, true, true);
        if (!$vault) {
            throw new \RuntimeException('Vault not found.');
        }
        if ((int)$vault->branch_id !== $branchId) {
            $movementRows = $db->query(
                'SELECT id FROM vault_movements WHERE vault_id = :vault_id LIMIT 1',
                ['vault_id' => $vaultId]
            ) ?: [];
            if ($db->has_error) {
                throw new \RuntimeException('Unable to verify Vault branch history: ' . $db->error);
            }
            if ($movementRows) {
                throw new \InvalidArgumentException('A Vault with recorded movements cannot be reassigned to another branch.');
            }
            $transactionRows = $db->query(
                'SELECT id FROM vault_transactions
                 WHERE source_vault_id = :source_vault_id OR destination_vault_id = :destination_vault_id
                 LIMIT 1',
                ['source_vault_id' => $vaultId, 'destination_vault_id' => $vaultId]
            ) ?: [];
            if ($db->has_error) {
                throw new \RuntimeException('Unable to verify Vault transaction history: ' . $db->error);
            }
            if ($transactionRows) {
                throw new \InvalidArgumentException('A Vault with recorded transactions cannot be reassigned to another branch.');
            }
            $reconciliationRows = $db->query(
                'SELECT id FROM vault_reconciliations WHERE vault_id = :vault_id LIMIT 1',
                ['vault_id' => $vaultId]
            ) ?: [];
            if ($db->has_error) {
                throw new \RuntimeException('Unable to verify Vault reconciliation history: ' . $db->error);
            }
            if ($reconciliationRows) {
                throw new \InvalidArgumentException('A Vault with reconciliation history cannot be reassigned to another branch.');
            }
        }
        $balance = \Vaults\balanceSummary($vaultId);
        if (($limitGhs > 0 && $balance['GHS'] > $limitGhs + 0.01)
            || ($limitCfa > 0 && $balance['CFA'] > $limitCfa + 0.01)
            || ($limitTotal > 0 && $balance['GHS'] + $balance['CFA_GHS_VALUE'] > $limitTotal + 0.01)) {
            throw new \InvalidArgumentException('New limits cannot be lower than the Vault’s current balances.');
        }
        $db->query(
            'UPDATE vaults
             SET name = :name, branch_id = :branch_id, custodian_user_id = :custodian_user_id,
                 limit_ghs = :limit_ghs, limit_cfa = :limit_cfa, limit_total_ghs = :limit_total_ghs,
                 active = :active, updated_by = :updated_by, date_updated = :date_updated
             WHERE id = :id',
            [
                'name' => $name,
                'branch_id' => $branchId,
                'custodian_user_id' => $custodianId,
                'limit_ghs' => $limitGhs,
                'limit_cfa' => $limitCfa,
                'limit_total_ghs' => $limitTotal,
                'active' => $active,
                'updated_by' => $userId,
                'date_updated' => $now,
                'id' => $vaultId,
            ]
        );
        if ($db->has_error) {
            throw new \RuntimeException('Unable to update Vault details: ' . $db->error);
        }
        if (!$db->commit()) {
            throw new \RuntimeException('Unable to commit the Vault update.');
        }
        message_success('Vault details and status updated.');
        redirect($vaultReturnUrl($vaultId, true));
        exit;
    }

    if ($action === 'admin_cash_in') {
        if (!\user_can('is_admin')) {
            throw new \RuntimeException('Only an admin can transfer cash from a drawer into a vault they created.');
        }
        $vaultId = (int)($postdata['vault_id'] ?? 0);
        $drawerUserId = (int)($postdata['drawer_user_id'] ?? 0);
        $currency = strtoupper(trim((string)($postdata['currency'] ?? '')));
        $notes = trim((string)($postdata['notes'] ?? ''));
        if ($vaultId <= 0 || $drawerUserId <= 0 || !in_array($currency, ['GHS', 'CFA'], true) || $notes === '') {
            throw new \InvalidArgumentException('Select a source cashdrawer and currency, then provide a cash-in note.');
        }
        if (empty($_FILES['proof_image']['name']) || (int)($_FILES['proof_image']['size'] ?? 0) > 2 * 1024 * 1024) {
            throw new \InvalidArgumentException('Upload the signed cash-in proof as an image under 2MB.');
        }
        $vaultRows = $db->query(
            'SELECT * FROM vaults WHERE id = :id AND active = 1 AND created_by = :created_by LIMIT 1',
            ['id' => $vaultId, 'created_by' => $userId]
        ) ?: [];
        $vault = $vaultRows[0] ?? null;
        if (!$vault) {
            throw new \RuntimeException('You can post cash only to an active vault that you created.');
        }
        $drawerOwnerRows = $db->query(
            'SELECT id FROM users
             WHERE id = :id AND branch_id = :branch_id AND deleted = 0
               AND (disabled IS NULL OR disabled = 0)
             LIMIT 1',
            ['id' => $drawerUserId, 'branch_id' => (int)$vault->branch_id]
        ) ?: [];
        if ($db->has_error) {
            throw new \RuntimeException('Unable to verify the selected cashdrawer user: ' . $db->error);
        }
        if (!$drawerOwnerRows) {
            throw new \InvalidArgumentException('Choose an active cashdrawer user assigned to the Vault branch.');
        }
        $countsByCurrency = $postdata['counts'] ?? [];
        if (!is_array($countsByCurrency) || !is_array($countsByCurrency[$currency] ?? null)) {
            throw new \InvalidArgumentException('Enter valid denomination counts.');
        }
        $denominationValues = $currency === 'GHS'
            ? [200, 100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1]
            : [10000, 5000, 2000, 1000, 500, 200, 100, 50, 25, 10, 5];
        $denominationRows = [];
        foreach ($denominationValues as $denomination) {
            $rawCount = $countsByCurrency[$currency][(string)$denomination] ?? '0';
            $count = filter_var($rawCount, FILTER_VALIDATE_INT);
            if ($count === false || $count < 0) {
                throw new \InvalidArgumentException('Denomination counts must be whole numbers and cannot be negative.');
            }
            $denominationRows[] = ['denomination' => $denomination, 'count' => $count];
        }
        $denominations = \Vaults\validateDenominations($currency, $denominationRows);
        $amount = \Vaults\denominationTotal($denominations);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Enter at least one denomination count greater than zero.');
        }
        $rate = $currency === 'CFA' ? \Vaults\currentCfaRate() : 1.0;
        \BackOfficeCashdrawer\loadCfaHelpers();
        if ($currency === 'CFA') {
            if (!function_exists('Cfas\\cfa_ensure_fx_table')) {
                throw new \RuntimeException('The CFA cashdrawer denomination service is unavailable.');
            }
            \Cfas\cfa_ensure_fx_table();
        }
        $drawerTable = $currency === 'CFA' ? 'drawer_fx_denos' : 'drawer_denos';
        $db->query('SELECT id FROM cashdrawers LIMIT 0');
        if ($db->has_error) {
            throw new \RuntimeException('The cashdrawer table is unavailable: ' . $db->error);
        }
        $db->query('SELECT id FROM ' . $drawerTable . ' LIMIT 0');
        if ($db->has_error) {
            throw new \RuntimeException('The cashdrawer denomination ledger is unavailable: ' . $db->error);
        }
        $today = date('Y-m-d');
        $drawerRows = $db->query(
            'SELECT id, user_id FROM cashdrawers
             WHERE user_id = :user_id AND DATE(date_created) = :today
             ORDER BY id DESC LIMIT 1',
            ['user_id' => $drawerUserId, 'today' => $today]
        ) ?: [];
        if ($db->has_error) {
            throw new \RuntimeException('Unable to locate the selected user’s cashdrawer: ' . $db->error);
        }
        $sourceDrawer = $drawerRows[0] ?? null;
        if (!$sourceDrawer) {
            throw new \RuntimeException('The selected user has no cashdrawer open today.');
        }
        $drawerId = (int)$sourceDrawer->id;
        $loadDrawerCounts = static function (bool $lockRows = false) use ($db, $drawerTable, $drawerId, $drawerUserId, $currency): array {
            $sql = "SELECT denomination, count FROM {$drawerTable}
                    WHERE drawer_id = :drawer_id AND user_id = :user_id";
            $params = ['drawer_id' => $drawerId, 'user_id' => $drawerUserId];
            if ($currency === 'CFA') {
                $sql .= " AND currency = 'CFA'";
            }
            if ($lockRows) {
                $sql .= ' FOR UPDATE';
            }
            $rows = $db->query($sql, $params) ?: [];
            if ($db->has_error) {
                throw new \RuntimeException('Unable to verify source cashdrawer denominations: ' . $db->error);
            }
            $available = [];
            foreach ($rows as $row) {
                $key = $currency === 'CFA'
                    ? (string)(int)round((float)$row->denomination)
                    : number_format((float)$row->denomination, 2, '.', '');
                $available[$key] = ($available[$key] ?? 0) + (int)$row->count;
            }
            return $available;
        };
        $assertDrawerCounts = static function (array $available) use ($denominations, $currency): void {
            $shortages = [];
            foreach ($denominations as $line) {
                $denomination = (float)$line['denomination'];
                $key = $currency === 'CFA'
                    ? (string)(int)round($denomination)
                    : number_format($denomination, 2, '.', '');
                $have = max(0, (int)($available[$key] ?? 0));
                $need = (int)$line['count'];
                if ($need > $have) {
                    $shortages[] = number_format($denomination, $currency === 'CFA' ? 0 : 2)
                        . ' (' . $need . ' requested, ' . $have . ' available)';
                }
            }
            if ($shortages) {
                throw new \InvalidArgumentException(
                    'The selected cashdrawer has insufficient ' . $currency . ' denominations: '
                    . implode('; ', $shortages) . '.'
                );
            }
        };
        $assertDrawerCounts($loadDrawerCounts());
        $req->upload_folder = 'uploads/vaults';
        $proof = $req->upload_files('proof_image');
        if (empty($proof)) {
            throw new \RuntimeException('Cash-in proof upload failed.');
        }
        if (!$db->beginTransaction()) {
            throw new \RuntimeException('Unable to start the vault cash-in.');
        }
        $lockedVaultRows = $db->query(
            'SELECT * FROM vaults WHERE id = :id AND active = 1 AND created_by = :created_by LIMIT 1 FOR UPDATE',
            ['id' => $vaultId, 'created_by' => $userId]
        ) ?: [];
        if ($db->has_error) {
            throw new \RuntimeException('Unable to lock the selected Vault: ' . $db->error);
        }
        $vault = $lockedVaultRows[0] ?? null;
        if (!$vault) {
            throw new \RuntimeException('This vault is no longer active or is not one you created.');
        }
        $lockedDrawerRows = $db->query(
            'SELECT id, user_id FROM cashdrawers
             WHERE user_id = :user_id AND DATE(date_created) = :today
             ORDER BY id DESC LIMIT 1 FOR UPDATE',
            ['user_id' => $drawerUserId, 'today' => $today]
        ) ?: [];
        if ($db->has_error) {
            throw new \RuntimeException('Unable to lock the source cashdrawer: ' . $db->error);
        }
        if (!$lockedDrawerRows || (int)$lockedDrawerRows[0]->id !== $drawerId) {
            throw new \RuntimeException('The source cashdrawer changed while this cash-in was being prepared. Please try again.');
        }
        $assertDrawerCounts($loadDrawerCounts(true));
        $db->query(
            "INSERT INTO vault_transactions
             (transaction_type, status, destination_vault_id, destination_branch_id, cashdrawer_id, drawer_user_id,
              agent_user_id, currency, amount, cfa_rate, denos_json, proof_image, requested_by, reviewed_by,
              reviewed_at, notes, date_created, date_updated)
             VALUES ('admin_cash_in', 'Approved', :vault_id, :branch_id, :cashdrawer_id, :drawer_user_id,
              :agent_user_id, :currency, :amount, :cfa_rate, :denos_json, :proof, :requested_by, :reviewed_by,
              :reviewed_at, :notes, :date_created, :date_updated)",
            [
                'vault_id' => $vaultId,
                'branch_id' => (int)$vault->branch_id,
                'cashdrawer_id' => $drawerId,
                'drawer_user_id' => $drawerUserId,
                'agent_user_id' => $userId,
                'requested_by' => $userId,
                'reviewed_by' => $userId,
                'currency' => $currency,
                'amount' => $amount,
                'cfa_rate' => $currency === 'CFA' ? $rate : null,
                'denos_json' => json_encode($denominations, JSON_UNESCAPED_SLASHES),
                'proof' => $proof,
                'reviewed_at' => $now,
                'notes' => $notes,
                'date_created' => $now,
                'date_updated' => $now,
            ]
        );
        if ($db->has_error || (int)$db->insert_id <= 0) {
            throw new \RuntimeException('Unable to save the audited vault cash-in transaction: ' . (string)$db->error);
        }
        $transactionId = (int)$db->insert_id;
        foreach ($denominations as $line) {
            \Vaults\recordMovement(
                $vaultId,
                'in',
                $currency,
                [$line],
                $rate,
                'vault_transaction',
                $transactionId,
                $userId,
                $transactionId,
                $currency === 'CFA' ? $rate : null
            );
        }
        $transStatus = 'Vault: Approved';
        foreach ($denominations as $line) {
            $denomination = (float)$line['denomination'];
            $count = (int)$line['count'];
            $volume = $denomination * $count;
            if ($currency === 'CFA') {
                $db->query(
                    'INSERT INTO drawer_fx_denos
                     (drawer_id, user_id, currency, denomination, count, volume, date_created, created_by, trans_id, trans_status)
                     VALUES (:drawer_id, :user_id, :currency, :denomination, :count, :volume, :date_created, :created_by, :trans_id, :trans_status)',
                    [
                        'drawer_id' => $drawerId,
                        'user_id' => $drawerUserId,
                        'currency' => $currency,
                        'denomination' => $denomination,
                        'count' => -$count,
                        'volume' => -$volume,
                        'date_created' => $now,
                        'created_by' => $userId,
                        'trans_id' => $transactionId,
                        'trans_status' => $transStatus,
                    ]
                );
            } else {
                $db->query(
                    'INSERT INTO drawer_denos
                     (drawer_id, user_id, denomination, count, volume, date_created, created_by, trans_id, trans_status)
                     VALUES (:drawer_id, :user_id, :denomination, :count, :volume, :date_created, :created_by, :trans_id, :trans_status)',
                    [
                        'drawer_id' => $drawerId,
                        'user_id' => $drawerUserId,
                        'denomination' => $denomination,
                        'count' => -$count,
                        'volume' => -$volume,
                        'date_created' => $now,
                        'created_by' => $userId,
                        'trans_id' => $transactionId,
                        'trans_status' => $transStatus,
                    ]
                );
            }
            if ($db->has_error) {
                throw new \RuntimeException('Unable to record the cashdrawer debit: ' . $db->error);
            }
        }
        if (!function_exists('Cfas\\cfa_refresh_cash_channel')) {
            throw new \RuntimeException('The cashdrawer balance refresh service is unavailable.');
        }
        \Cfas\cfa_refresh_cash_channel(
            $drawerId,
            $drawerUserId,
            $currency === 'CFA' ? 'CASH_CFA' : 'CASH_DRAWER',
            $transactionId,
            $transStatus,
            $currency === 'CFA',
            $currency
        );
        $drawerChannel = $currency === 'CFA' ? 'CASH_CFA' : 'CASH_DRAWER';
        $drawerBalanceRows = $db->query(
            "SELECT id FROM drawer_balances
             WHERE drawer_id = :drawer_id AND user_id = :user_id
               AND channel_type = 'cash' AND channel_key = :channel_key AND trans_id = :transaction_id
             ORDER BY id DESC LIMIT 1",
            [
                'drawer_id' => $drawerId,
                'user_id' => $drawerUserId,
                'channel_key' => $drawerChannel,
                'transaction_id' => $transactionId,
            ]
        ) ?: [];
        if ($db->has_error || !$drawerBalanceRows) {
            throw new \RuntimeException('The cashdrawer balance could not be refreshed for this Vault transfer.');
        }
        $balance = \Vaults\balanceSummary($vaultId);
        if (((float)$vault->limit_ghs > 0 && $balance['GHS'] > (float)$vault->limit_ghs + 0.01)
            || ((float)$vault->limit_cfa > 0 && $balance['CFA'] > (float)$vault->limit_cfa + 0.01)
            || ((float)$vault->limit_total_ghs > 0
                && $balance['GHS'] + $balance['CFA_GHS_VALUE'] > (float)$vault->limit_total_ghs + 0.01)) {
            throw new \InvalidArgumentException('This cash-in would exceed the vault balance limit.');
        }
        if (!$db->commit()) {
            throw new \RuntimeException('Unable to commit the vault cash-in.');
        }
        message_success('Cash transferred from the selected cashdrawer into the vault and recorded in both histories.');
        redirect($vaultReturnUrl($vaultId, true));
        exit;
    }

    if ($action === 'submit_transaction') {
        $type = trim((string)($postdata['transaction_type'] ?? ''));
        $currency = strtoupper(trim((string)($postdata['currency'] ?? '')));
        if (!in_array($type, ['to_agent', 'from_agent', 'interbranch', 'denos_exchange'], true) || !in_array($currency, ['GHS', 'CFA'], true)) {
            throw new \InvalidArgumentException('Select a valid Vault transaction type and currency.');
        }
        $sourceId = (int)($postdata['source_vault_id'] ?? 0);
        $destinationId = (int)($postdata['destination_vault_id'] ?? 0);
        if ($type === 'to_agent') {
            $destinationId = 0;
        } elseif ($type === 'from_agent') {
            $sourceId = 0;
        } elseif ($type === 'denos_exchange') {
            $destinationId = $sourceId;
        }
        if ($type === 'interbranch' && ($sourceId <= 0 || $destinationId <= 0 || $sourceId === $destinationId)) {
            throw new \InvalidArgumentException('Select different source and destination vaults for an interbranch transfer.');
        }
        $currentBranchId = \Vaults\currentBranchId($userId);
        $isInterbranch = $type === 'interbranch';
        $sourceVault = $sourceId > 0
            ? ($isInterbranch ? \Vaults\getActiveVaultForTransfer($sourceId) : \Vaults\getVault($sourceId, $userId))
            : null;
        $destinationVault = $destinationId > 0
            ? ($isInterbranch ? \Vaults\getActiveVaultForTransfer($destinationId) : \Vaults\getVault($destinationId, $userId))
            : null;
        if (($sourceId > 0 && !$sourceVault) || ($destinationId > 0 && !$destinationVault)) {
            throw new \RuntimeException('Select an active vault available for this movement.');
        }
        if ($isInterbranch && !\Vaults\canManage() && (!$sourceVault || (int)$sourceVault->branch_id !== $currentBranchId)) {
            throw new \RuntimeException('Interbranch transfers must start from a vault assigned to your branch.');
        }
        if ($type === 'interbranch' && (!$sourceVault || !$destinationVault)) {
            throw new \RuntimeException('Select both active source and destination vaults.');
        }
        if ($type === 'interbranch' && $sourceVault && $destinationVault
            && (int)$sourceVault->branch_id === (int)$destinationVault->branch_id) {
            throw new \InvalidArgumentException('Choose vaults assigned to different branches for an interbranch transfer.');
        }
        if (($type === 'to_agent' && !$sourceVault) || ($type === 'from_agent' && !$destinationVault)) {
            throw new \InvalidArgumentException('Select the vault holding or receiving the cash for this movement.');
        }
        if (in_array($type, ['to_agent', 'from_agent'], true)) {
            $agentVault = $type === 'to_agent' ? $sourceVault : $destinationVault;
            if ($currentBranchId <= 0 || (int)$agentVault->branch_id !== $currentBranchId) {
                throw new \InvalidArgumentException('Agent cashdrawer movements must use a vault in the requesting agent’s branch.');
            }
            if (!function_exists('BackOfficeCashdrawer\\userHasCashdrawerToday')
                || !\BackOfficeCashdrawer\userHasCashdrawerToday($userId)) {
                throw new \RuntimeException('The requesting agent must have an active cashdrawer today to use a Vault transfer.');
            }
        }
        if ($type === 'denos_exchange' && !$sourceVault) {
            throw new \InvalidArgumentException('Select the vault whose denominations will be exchanged.');
        }
        $assignedFirstPartyId = (int)($postdata['assigned_first_party_id'] ?? 0);
        $assignedApproverId = (int)($postdata['assigned_approver_id'] ?? 0);
        $firstParty = $db->query(
            'SELECT id, branch_id FROM users WHERE id = :id AND (disabled IS NULL OR disabled = 0) LIMIT 1',
            ['id' => $assignedFirstPartyId]
        ) ?: [];
        if (!$firstParty || \Vaults\userHasHigherRole($assignedFirstPartyId)) {
            throw new \InvalidArgumentException('Assign a non-manager staff member as the first party.');
        }
        if (!\Vaults\userIsEligibleStaffToday($assignedFirstPartyId)) {
            throw new \InvalidArgumentException('The first party must have created a cashdrawer and been marked present or late today.');
        }
        $receivingBranch = $destinationVault ? (int)$destinationVault->branch_id : (int)($sourceVault->branch_id ?? 0);
        if ((int)$firstParty[0]->branch_id !== $receivingBranch) {
            throw new \InvalidArgumentException('The first party must be assigned to the receiving branch.');
        }
        if ($assignedFirstPartyId === $userId) {
            throw new \InvalidArgumentException('The first party must be a different staff member.');
        }
        if ($assignedApproverId <= 0 || !\Vaults\userHasHigherRole($assignedApproverId)) {
            throw new \InvalidArgumentException('Assign a manager, operations manager, or admin as the approver.');
        }
        if (!\Vaults\userIsEligibleStaffToday($assignedApproverId)) {
            throw new \InvalidArgumentException('The approver must have created a cashdrawer and been marked present or late today.');
        }
        if (($assignedApproverId === $userId && !user_can('is_admin')) || $assignedApproverId === $assignedFirstPartyId) {
            throw new \InvalidArgumentException('The approver must be different from the initiator unless the initiator is an admin, and must always be different from the first party.');
        }
        $outgoingDenominations = \Vaults\validateDenominations($currency, $postdata['denos_json'] ?? '[]');
        $amount = \Vaults\denominationTotal($outgoingDenominations);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Enter a transaction amount greater than zero.');
        }
        $rate = $currency === 'CFA' ? \Vaults\currentCfaRate() : null;
        $denominationsPayload = $outgoingDenominations;
        if ($type === 'denos_exchange') {
            $incomingDenominations = \Vaults\validateDenominations($currency, $postdata['exchange_received_json'] ?? '[]');
            if (abs(\Vaults\denominationTotal($incomingDenominations) - $amount) > 0.01) {
                throw new \InvalidArgumentException('The given and received denomination totals must match exactly.');
            }
            if ($currency === 'CFA') {
                $rate = (float)($postdata['exchange_rate'] ?? 0);
                $stockRate = $db->query(
                    'SELECT id FROM vault_stock WHERE vault_id = :vault_id AND currency = \'CFA\' AND purchase_rate = :rate AND note_count > 0 LIMIT 1',
                    ['vault_id' => $sourceId, 'rate' => $rate]
                ) ?: [];
                if ($rate <= 0 || !$stockRate) {
                    throw new \InvalidArgumentException('Choose an available CFA purchase-rate lot for the exchange.');
                }
            }
            $denominationsPayload = ['out' => $outgoingDenominations, 'in' => $incomingDenominations];
        }
        if ($sourceId > 0) {
            $availability = \Vaults\denominationAvailability(
                $sourceId,
                $currency,
                $outgoingDenominations,
                $type === 'denos_exchange' && $currency === 'CFA' ? (float)$rate : null
            );
            $hasShortage = (bool)array_filter(
                $availability,
                static function (array $line): bool { return (int)$line['shortage'] > 0; }
            );
            if ($hasShortage) {
                throw new \InvalidArgumentException(\Vaults\insufficientDenominationMessage($currency, $availability));
            }
        }
        if ($type === 'from_agent') {
            if (!function_exists('BackOfficeCashdrawer\\getDrawerDenominationCounts')) {
                throw new \RuntimeException('The cashdrawer denomination service is unavailable.');
            }
            $drawerCounts = \BackOfficeCashdrawer\getDrawerDenominationCounts($userId, $currency);
            $shortages = [];
            foreach ($outgoingDenominations as $requested) {
                $denomination = (float)$requested['denomination'];
                $key = $currency === 'CFA'
                    ? (string)(int)round($denomination)
                    : number_format($denomination, 2, '.', '');
                $available = max(0, (int)($drawerCounts[$key] ?? 0));
                $needed = (int)$requested['count'];
                if ($available < $needed) {
                    $shortages[] = [
                        'denomination' => $denomination,
                        'requested' => $needed,
                        'available' => $available,
                        'shortage' => $needed - $available,
                    ];
                }
            }
            if ($shortages) {
                $details = [];
                foreach ($shortages as $shortage) {
                    $denomination = (float)$shortage['denomination'];
                    $label = $currency === 'GHS'
                        ? 'GH₵ ' . number_format($denomination, 2)
                        : number_format($denomination, 0) . ' CFA';
                    $unit = $currency === 'GHS' && $denomination < 1 ? 'coins' : 'notes';
                    $details[] = $label . ' (' . $unit . '): requested ' . number_format((int)$shortage['requested'])
                        . ', available ' . number_format((int)$shortage['available'])
                        . ', short ' . number_format((int)$shortage['shortage']);
                }
                throw new \InvalidArgumentException('Insufficient cashdrawer denominations (' . $currency . '): ' . implode('; ', $details) . '.');
            }
        }
        $req->upload_folder = 'uploads/vaults';
        $proof = null;
        if (empty($_FILES['proof_image']['name']) || (int)($_FILES['proof_image']['size'] ?? 0) > 2 * 1024 * 1024) {
            throw new \InvalidArgumentException('Upload the signed treasury sheet as an image under 2MB.');
        }
        $proof = $req->upload_files('proof_image');
        if (empty($proof)) {
            throw new \RuntimeException('Treasury proof upload failed.');
        }
        $sourceBranchId = (int)($sourceVault->branch_id ?? 0);
        $destinationBranchId = (int)($destinationVault->branch_id ?? $sourceBranchId);
        $db->query(
            "INSERT INTO vault_transactions
             (transaction_type, status, source_vault_id, destination_vault_id, source_branch_id, destination_branch_id,
              agent_user_id, currency, amount, cfa_rate, denos_json, proof_image, requested_by,
              assigned_first_party_id, assigned_approver_id, notes, date_created)
             VALUES (:type, 'Pending First Party', :source_vault_id, :destination_vault_id, :source_branch_id,
              :destination_branch_id, :agent_user_id, :currency, :amount, :cfa_rate, :denos_json, :proof_image,
              :requested_by, :first_party, :approver, :notes, :date_created)",
            [
                'type' => $type,
                'source_vault_id' => $sourceId ?: null,
                'destination_vault_id' => $destinationId ?: null,
                'source_branch_id' => $sourceBranchId ?: null,
                'destination_branch_id' => $destinationBranchId ?: null,
                'agent_user_id' => $userId,
                'currency' => $currency,
                'amount' => $amount,
                'cfa_rate' => $rate,
                'denos_json' => json_encode($denominationsPayload, JSON_UNESCAPED_SLASHES),
                'proof_image' => $proof,
                'requested_by' => $userId,
                'first_party' => $assignedFirstPartyId,
                'approver' => $assignedApproverId,
                'notes' => trim((string)($postdata['notes'] ?? '')),
                'date_created' => $now,
            ]
        );
        if ($db->has_error) {
            throw new \RuntimeException('Unable to save the Vault transaction.');
        }
        $notifyVaultRecipient(
            'Vault transaction',
            (int)$db->insert_id,
            (int)($sourceId ?: $destinationId),
            $assignedFirstPartyId,
            'Pending First Party',
            'ACTION REQUIRED',
            [
                'Movement' => ucwords(str_replace('_', ' ', $type)),
                'Amount' => $currency . ' ' . number_format($amount, 2),
                'Initiated by' => trim((string)($session->first_name ?? '') . ' ' . (string)($session->last_name ?? '')),
            ]
        );
        message_success('Vault transaction submitted to the assigned first party.');
        redirect($vaultReturnUrl((int)($sourceId ?: $destinationId)));
        exit;
    }

    if ($action === 'first_party_action') {
        $transactionId = (int)($postdata['transaction_id'] ?? 0);
        $choice = trim((string)($postdata['choice'] ?? ''));
        $reason = trim((string)($postdata['reason'] ?? ''));
        $rows = $db->query('SELECT * FROM vault_transactions WHERE id = :id LIMIT 1', ['id' => $transactionId]) ?: [];
        $transaction = $rows[0] ?? null;
        if (!$transaction || (int)$transaction->assigned_first_party_id !== $userId || $transaction->status !== 'Pending First Party') {
            throw new \RuntimeException('This transaction is not awaiting your first-party review.');
        }
        if (!in_array($choice, ['confirm', 'reject'], true) || ($choice === 'reject' && $reason === '')) {
            throw new \InvalidArgumentException('Choose confirm or provide a rejection reason.');
        }
        $status = $choice === 'confirm' ? 'Pending Approval' : 'Rejected';
        $db->query(
            'UPDATE vault_transactions SET status = :status, reject_reason = :reason, date_updated = :date_updated WHERE id = :id AND status = \'Pending First Party\'',
            ['status' => $status, 'reason' => $choice === 'reject' ? $reason : null, 'date_updated' => $now, 'id' => $transactionId]
        );
        if ($db->has_error || $db->affected_rows !== 1) {
            throw new \RuntimeException('This transaction changed while it was being reviewed. Refresh and try again.');
        }
        $notifyVaultRecipient(
            'Vault transaction',
            $transactionId,
            (int)($transaction->source_vault_id ?: $transaction->destination_vault_id),
            $choice === 'confirm' ? (int)$transaction->assigned_approver_id : (int)$transaction->requested_by,
            $status,
            $choice === 'confirm' ? 'ACTION REQUIRED' : 'UPDATE',
            [
                'Movement' => ucwords(str_replace('_', ' ', (string)$transaction->transaction_type)),
                'Amount' => (string)$transaction->currency . ' ' . number_format((float)$transaction->amount, 2),
                'First-party decision' => $choice === 'confirm' ? 'Confirmed; approval required' : 'Declined',
                'Reason' => $choice === 'reject' ? $reason : '',
            ]
        );
        message_success($choice === 'confirm' ? 'First-party check confirmed; transaction sent for approval.' : 'Vault transaction rejected.');
        redirect($vaultReturnUrl((int)($transaction->source_vault_id ?: $transaction->destination_vault_id)));
        exit;
    }

    if ($action === 'edit_pending_transaction') {
        $transactionId = (int)($postdata['transaction_id'] ?? 0);
        $rows = $db->query('SELECT * FROM vault_transactions WHERE id = :id LIMIT 1', ['id' => $transactionId]) ?: [];
        $transaction = $rows[0] ?? null;
        if (!$transaction
            || !in_array($userId, [(int)$transaction->requested_by, (int)$transaction->assigned_first_party_id], true)
            || $transaction->status !== 'Pending First Party') {
            throw new \RuntimeException('Only the initiator or assigned first party can edit a transaction before first-party confirmation.');
        }
        $assignedFirstPartyId = (int)($postdata['assigned_first_party_id'] ?? 0);
        $assignedApproverId = (int)($postdata['assigned_approver_id'] ?? 0);
        $partyRows = $db->query(
            'SELECT id, branch_id FROM users WHERE id = :id AND (disabled IS NULL OR disabled = 0) LIMIT 1',
            ['id' => $assignedFirstPartyId]
        ) ?: [];
        if (!$partyRows || \Vaults\userHasHigherRole($assignedFirstPartyId)) {
            throw new \InvalidArgumentException('Assign an active non-manager staff member as the second party.');
        }
        $receivingBranchId = (int)($transaction->destination_branch_id ?: $transaction->source_branch_id);
        if ($receivingBranchId <= 0 || (int)$partyRows[0]->branch_id !== $receivingBranchId) {
            throw new \InvalidArgumentException('The second party must be assigned to the transaction’s receiving branch.');
        }
        if (!\Vaults\userIsEligibleStaffToday($assignedFirstPartyId)) {
            throw new \InvalidArgumentException('The second party must have created a cashdrawer and been marked present or late today.');
        }
        if ($assignedApproverId <= 0 || !\Vaults\userHasHigherRole($assignedApproverId)) {
            throw new \InvalidArgumentException('Assign an admin, manager, or operations manager as the approver.');
        }
        if (!\Vaults\userIsEligibleStaffToday($assignedApproverId)) {
            throw new \InvalidArgumentException('The approver must have created a cashdrawer and been marked present or late today.');
        }
        if (($assignedApproverId === $userId && !user_can('is_admin')) || $assignedApproverId === $assignedFirstPartyId) {
            throw new \InvalidArgumentException('The approver must be different from the initiator unless the initiator is an admin, and must always be different from the second party.');
        }
        $currency = (string)$transaction->currency;
        if ((string)$transaction->transaction_type === 'denos_exchange') {
            $exchange = json_decode((string)($postdata['denos_json'] ?? ''), true);
            if (!is_array($exchange) || !isset($exchange['out'], $exchange['in'])) {
                throw new \InvalidArgumentException('Enter valid denominations given and received.');
            }
            $outgoing = \Vaults\validateDenominations($currency, $exchange['out']);
            $incoming = \Vaults\validateDenominations($currency, $exchange['in']);
            $amount = \Vaults\denominationTotal($outgoing);
            if (abs($amount - \Vaults\denominationTotal($incoming)) > 0.01) {
                throw new \InvalidArgumentException('Given and received denominations must total the same amount.');
            }
            $payload = ['out' => $outgoing, 'in' => $incoming];
        } else {
            $outgoing = \Vaults\validateDenominations($currency, $postdata['denos_json'] ?? '[]');
            $amount = \Vaults\denominationTotal($outgoing);
            $payload = $outgoing;
        }
        $proof = (string)($transaction->proof_image ?? '');
        if (!empty($_FILES['proof_image']['name'])) {
            if ((int)($_FILES['proof_image']['size'] ?? 0) > 2 * 1024 * 1024) {
                throw new \InvalidArgumentException('Treasury proof must not exceed 2MB.');
            }
            $req->upload_folder = 'uploads/vaults';
            $proof = $req->upload_files('proof_image');
            if (empty($proof)) {
                throw new \RuntimeException('Treasury proof upload failed.');
            }
        }
        $notes = trim((string)($postdata['notes'] ?? ''));
        $db->query(
            'UPDATE vault_transactions SET amount = :amount, denos_json = :denos_json, proof_image = :proof_image,
             assigned_first_party_id = :first_party, assigned_approver_id = :approver, notes = :notes, date_updated = :date_updated
             WHERE id = :id AND status = \'Pending First Party\'',
            [
                'amount' => $amount,
                'denos_json' => json_encode($payload, JSON_UNESCAPED_SLASHES),
                'proof_image' => $proof,
                'first_party' => $assignedFirstPartyId,
                'approver' => $assignedApproverId,
                'notes' => $notes,
                'date_updated' => $now,
                'id' => $transactionId,
            ]
        );
        if ($db->has_error || $db->affected_rows !== 1) {
            throw new \RuntimeException('Unable to update this pending transaction. It may have already been confirmed.');
        }
        if ($assignedFirstPartyId !== (int)$transaction->assigned_first_party_id && $assignedFirstPartyId !== $userId) {
            $notifyVaultRecipient(
                'Vault transaction',
                $transactionId,
                (int)($transaction->source_vault_id ?: $transaction->destination_vault_id),
                $assignedFirstPartyId,
                'Pending First Party',
                'ACTION REQUIRED',
                [
                    'Movement' => ucwords(str_replace('_', ' ', (string)$transaction->transaction_type)),
                    'Amount' => $currency . ' ' . number_format($amount, 2),
                    'Assignment' => 'You have been assigned as the first party.',
                ]
            );
        }
        message_success('Pending Vault transaction updated.');
        redirect($vaultReturnUrl((int)($transaction->source_vault_id ?: $transaction->destination_vault_id)));
        exit;
    }

    if ($action === 'review_transaction') {
        $transactionId = (int)($postdata['transaction_id'] ?? 0);
        $choice = trim((string)($postdata['choice'] ?? ''));
        $reason = trim((string)($postdata['reason'] ?? ''));
        if (!\Vaults\canManage() || !in_array($choice, ['approve', 'reject'], true) || ($choice === 'reject' && $reason === '')) {
            throw new \RuntimeException('Only a manager, operations manager, or admin can approve or reject a Vault transaction.');
        }
        if (!$db->beginTransaction()) {
            throw new \RuntimeException('Unable to start transaction approval.');
        }
        $rows = $db->query('SELECT * FROM vault_transactions WHERE id = :id LIMIT 1 FOR UPDATE', ['id' => $transactionId]) ?: [];
        $transaction = $rows[0] ?? null;
        if (!$transaction || (int)$transaction->assigned_approver_id !== $userId || $transaction->status !== 'Pending Approval') {
            throw new \RuntimeException('This transaction is not assigned to you for approval.');
        }
        if ($choice === 'approve') {
            \Vaults\applyVaultTransaction($transaction, $userId);
        }
        $db->query(
            'UPDATE vault_transactions SET status = :status, reviewed_by = :reviewed_by, reviewed_at = :reviewed_at, reject_reason = :reason, date_updated = :date_updated WHERE id = :id',
            [
                'status' => $choice === 'approve' ? 'Approved' : 'Rejected',
                'reviewed_by' => $userId,
                'reviewed_at' => $now,
                'reason' => $choice === 'reject' ? $reason : null,
                'date_updated' => $now,
                'id' => $transactionId,
            ]
        );
        if ($db->has_error) {
            throw new \RuntimeException('Unable to save Vault approval.');
        }
        if (!$db->commit()) {
            throw new \RuntimeException('Unable to commit Vault approval.');
        }
        if ((int)$transaction->requested_by !== $userId) {
            $notifyVaultRecipient(
                'Vault transaction',
                $transactionId,
                (int)($transaction->source_vault_id ?: $transaction->destination_vault_id),
                (int)$transaction->requested_by,
                $choice === 'approve' ? 'Approved' : 'Rejected',
                'UPDATE',
                [
                    'Movement' => ucwords(str_replace('_', ' ', (string)$transaction->transaction_type)),
                    'Amount' => (string)$transaction->currency . ' ' . number_format((float)$transaction->amount, 2),
                    'Decision' => $choice === 'approve' ? 'Approved; balances updated' : 'Rejected',
                    'Reason' => $choice === 'reject' ? $reason : '',
                ]
            );
        }
        message_success($choice === 'approve' ? 'Vault transaction approved and balances updated.' : 'Vault transaction rejected.');
        redirect($vaultReturnUrl((int)($transaction->source_vault_id ?: $transaction->destination_vault_id), true));
        exit;
    }

    if ($action === 'submit_reconciliation') {
        $vaultId = (int)($postdata['vault_id'] ?? 0);
        if (!$db->beginTransaction()) {
            throw new \RuntimeException('Unable to start vault reconciliation submission.');
        }
        $vault = \Vaults\getVault($vaultId, $userId, true);
        if (!$vault) {
            throw new \RuntimeException('Vault not found or not accessible.');
        }
        if (!\Vaults\canReconcileVault($vault, $userId)) {
            throw new \RuntimeException('Only an administrator or the assigned Vault custodian can submit a reconciliation.');
        }
        $pendingRows = $db->query(
            "SELECT id FROM vault_reconciliations WHERE vault_id = :vault_id AND status = 'Pending' LIMIT 1 FOR UPDATE",
            ['vault_id' => $vaultId]
        ) ?: [];
        if ($pendingRows) {
            throw new \RuntimeException('This vault already has a reconciliation awaiting review. You can resubmit after the reviewer rejects it.');
        }
        $latestReconciliationRows = $db->query(
            'SELECT created_by, status FROM vault_reconciliations WHERE vault_id = :vault_id ORDER BY id DESC LIMIT 1 FOR UPDATE',
            ['vault_id' => $vaultId]
        ) ?: [];
        $latestReconciliation = $latestReconciliationRows[0] ?? null;
        if ($latestReconciliation
            && strtolower((string)$latestReconciliation->status) === 'rejected'
            && (int)$latestReconciliation->created_by !== $userId) {
            throw new \RuntimeException('The staff member who submitted the rejected reconciliation must resubmit it.');
        }
        $approverId = (int)($postdata['assigned_approver_id'] ?? 0);
        if ($approverId <= 0 || !\Vaults\userHasHigherRole($approverId)) {
            throw new \InvalidArgumentException('Assign a manager, operations manager, or admin to review reconciliation.');
        }
        $counts = json_decode((string)($postdata['counted_stock_json'] ?? ''), true);
        if (!is_array($counts) || !$counts) {
            throw new \InvalidArgumentException('Enter the counted vault denominations.');
        }
        $allowedDenominations = [
            'GHS' => [200, 100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1],
            'CFA' => [10000, 5000, 2000, 1000, 500, 200, 100, 50, 25, 10, 5],
        ];
        $knownCfaRates = [];
        foreach (\Vaults\stockRows($vaultId) as $stockLine) {
            if ((string)$stockLine->currency === 'CFA') {
                $knownCfaRates[number_format((float)$stockLine->purchase_rate, 6, '.', '')] = (float)$stockLine->purchase_rate;
            }
        }
        $historicalRateRows = $db->query(
            "SELECT DISTINCT purchase_rate FROM vault_movements
             WHERE vault_id = :vault_id AND currency = 'CFA' AND purchase_rate > 0",
            ['vault_id' => $vaultId]
        ) ?: [];
        if ($db->has_error) {
            throw new \RuntimeException('Unable to read the vault’s CFA purchase-rate history: ' . $db->error);
        }
        foreach ($historicalRateRows as $rateRow) {
            $knownCfaRates[number_format((float)$rateRow->purchase_rate, 6, '.', '')] = (float)$rateRow->purchase_rate;
        }
        $currentCfaRate = \Vaults\configuredCfaRate();
        if ($currentCfaRate !== null && $currentCfaRate > 0) {
            $knownCfaRates[number_format($currentCfaRate, 6, '.', '')] = $currentCfaRate;
        }
        $seen = [];
        $normalizedCounts = [];
        foreach ($counts as $line) {
            if (!is_array($line)) {
                throw new \InvalidArgumentException('The counted stock contains an invalid denomination row.');
            }
            $currency = strtoupper(trim((string)($line['currency'] ?? '')));
            $denomination = (float)($line['denomination'] ?? 0);
            $countValue = $line['count'] ?? null;
            $count = is_numeric($countValue) ? (float)$countValue : -1;
            $rate = $currency === 'CFA' ? (float)($line['purchase_rate'] ?? 0) : 1.0;
            $rateKey = number_format($rate, 6, '.', '');
            $key = $currency . '|' . number_format($denomination, 2, '.', '') . '|' . $rateKey;
            if (!isset($allowedDenominations[$currency])
                || !in_array($denomination, array_map('floatval', $allowedDenominations[$currency]), true)
                || $count < 0 || floor($count) !== $count
                || ($currency === 'CFA' && ($rate <= 0 || !isset($knownCfaRates[$rateKey])))
                || isset($seen[$key])) {
                throw new \InvalidArgumentException('The counted stock contains an invalid currency, denomination, purchase rate, or count.');
            }
            $seen[$key] = true;
            $normalizedCounts[] = [
                'currency' => $currency,
                'denomination' => $denomination,
                'purchase_rate' => $rate,
                'count' => (int)$count,
            ];
        }
        $requiredRates = ['GHS|1.000000' => ['currency' => 'GHS', 'rate' => 1.0]];
        foreach ($knownCfaRates as $rateKey => $rate) {
            $requiredRates['CFA|' . $rateKey] = ['currency' => 'CFA', 'rate' => $rate];
        }
        foreach ($requiredRates as $rateGroup) {
            foreach ($allowedDenominations[$rateGroup['currency']] as $denomination) {
                $key = $rateGroup['currency'] . '|' . number_format((float)$denomination, 2, '.', '') . '|' . number_format((float)$rateGroup['rate'], 6, '.', '');
                if (!isset($seen[$key])) {
                    throw new \InvalidArgumentException('Open the denomination calculator and enter counts for every denomination in each currency and CFA purchase-rate lot.');
                }
            }
        }
        $systemStockSnapshot = array_map(static function ($line): array {
            return [
                'currency' => (string)$line->currency,
                'denomination' => (float)$line->denomination,
                'purchase_rate' => (float)$line->purchase_rate,
                'count' => (int)$line->note_count,
            ];
        }, \Vaults\stockRows($vaultId));
        $proof = null;
        if (!empty($_FILES['proof_image']['name'])) {
            if ((int)($_FILES['proof_image']['size'] ?? 0) > 2 * 1024 * 1024) {
                throw new \InvalidArgumentException('The optional reconciliation proof image must be under 2MB.');
            }
            $req->upload_folder = 'uploads/vaults/reconciliations';
            $proof = $req->upload_files('proof_image');
            if (empty($proof)) {
                throw new \RuntimeException('Reconciliation proof upload failed.');
            }
        }
        $db->query(
            "INSERT INTO vault_reconciliations
             (vault_id, status, counted_stock_json, system_stock_json, proof_image, notes, created_by, assigned_approver_id, date_created)
             VALUES (:vault_id, 'Pending', :counts, :system_stock, :proof, :notes, :created_by, :approver, :date_created)",
            [
                'vault_id' => $vaultId,
                'counts' => json_encode($normalizedCounts, JSON_UNESCAPED_SLASHES),
                'system_stock' => json_encode($systemStockSnapshot, JSON_UNESCAPED_SLASHES),
                'proof' => $proof,
                'notes' => trim((string)($postdata['notes'] ?? '')),
                'created_by' => $userId,
                'approver' => $approverId,
                'date_created' => $now,
            ]
        );
        if ($db->has_error) {
            throw new \RuntimeException('Unable to submit Vault reconciliation.');
        }
        if (!$db->commit()) {
            throw new \RuntimeException('Unable to commit the Vault reconciliation.');
        }
        $notifyVaultRecipient(
            'Vault reconciliation',
            (int)$db->insert_id,
            $vaultId,
            $approverId,
            'Pending',
            'ACTION REQUIRED',
            [
                'Vault' => (string)$vault->name,
                'Submitted by' => 'A vault reconciliation is ready for review.',
            ]
        );
        message_success('Vault reconciliation submitted for higher-role review.');
        redirect($vaultReturnUrl($vaultId));
        exit;
    }

    if ($action === 'review_reconciliation') {
        $reconciliationId = (int)($postdata['reconciliation_id'] ?? 0);
        $choice = trim((string)($postdata['choice'] ?? ''));
        $reason = trim((string)($postdata['reason'] ?? ''));
        if (!\Vaults\canManage()) {
            throw new \RuntimeException('Only a manager, operations manager, or admin can review reconciliation.');
        }
        if (!in_array($choice, ['approve', 'reject'], true)) {
            throw new \InvalidArgumentException('Choose whether to approve or reject this reconciliation.');
        }
        if ($choice === 'reject' && $reason === '') {
            throw new \InvalidArgumentException('Enter a reason before rejecting this reconciliation.');
        }
        if (!$db->beginTransaction()) {
            throw new \RuntimeException('Unable to start reconciliation review.');
        }
        $lookupRows = $db->query(
            'SELECT vault_id FROM vault_reconciliations WHERE id = :id LIMIT 1',
            ['id' => $reconciliationId]
        ) ?: [];
        $lookup = $lookupRows[0] ?? null;
        if (!$lookup) {
            throw new \RuntimeException('Reconciliation not found.');
        }
        $vaultId = (int)$lookup->vault_id;
        $vault = \Vaults\getVault($vaultId, $userId, true);
        if (!$vault) {
            throw new \RuntimeException('Vault not found or not accessible.');
        }
        $rows = $db->query('SELECT * FROM vault_reconciliations WHERE id = :id LIMIT 1 FOR UPDATE', ['id' => $reconciliationId]) ?: [];
        $reconciliation = $rows[0] ?? null;
        if (!$reconciliation || (int)$reconciliation->assigned_approver_id !== $userId || $reconciliation->status !== 'Pending') {
            throw new \RuntimeException('This reconciliation is not assigned to you for review.');
        }
        $reviewedStockSnapshot = array_map(static function ($line): array {
            return [
                'currency' => (string)$line->currency,
                'denomination' => (float)$line->denomination,
                'purchase_rate' => (float)$line->purchase_rate,
                'count' => (int)$line->note_count,
            ];
        }, \Vaults\stockRows($vaultId));
        if ($choice === 'approve') {
            $counted = json_decode((string)$reconciliation->counted_stock_json, true);
            if (!is_array($counted) || !$counted) {
                throw new \RuntimeException('The submitted denomination count is missing or invalid.');
            }
            $knownCfaRates = [];
            foreach (\Vaults\stockRows($vaultId) as $line) {
                if ((string)$line->currency === 'CFA') {
                    $knownCfaRates[number_format((float)$line->purchase_rate, 6, '.', '')] = true;
                }
            }
            $historicalRateRows = $db->query(
                "SELECT DISTINCT purchase_rate FROM vault_movements
                 WHERE vault_id = :vault_id AND currency = 'CFA' AND purchase_rate > 0",
                ['vault_id' => $vaultId]
            ) ?: [];
            if ($db->has_error) {
                throw new \RuntimeException('Unable to read the vault’s CFA purchase-rate history: ' . $db->error);
            }
            foreach ($historicalRateRows as $rateRow) {
                $knownCfaRates[number_format((float)$rateRow->purchase_rate, 6, '.', '')] = true;
            }
            $latestCfaRate = \Vaults\configuredCfaRate();
            if ($latestCfaRate !== null && $latestCfaRate > 0) {
                $knownCfaRates[number_format($latestCfaRate, 6, '.', '')] = true;
            }
            $actual = [];
            foreach ($counted as $line) {
                if (!is_array($line) || !isset($line['currency'], $line['denomination'], $line['count'])) {
                    throw new \RuntimeException('The submitted denomination count contains an invalid row.');
                }
                $currency = (string)$line['currency'];
                $denomination = (float)$line['denomination'];
                $purchaseRate = $currency === 'CFA' ? (float)($line['purchase_rate'] ?? 0) : 1.0;
                $allowedDenominations = $currency === 'GHS'
                    ? [200, 100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1]
                    : [10000, 5000, 2000, 1000, 500, 200, 100, 50, 25, 10, 5];
                $countValue = $line['count'];
                if (!in_array($currency, ['GHS', 'CFA'], true)
                    || !is_numeric($countValue)
                    || (float)$countValue < 0
                    || floor((float)$countValue) !== (float)$countValue
                    || $purchaseRate <= 0
                    || !in_array($denomination, array_map('floatval', $allowedDenominations), true)
                    || ($currency === 'CFA' && !isset($knownCfaRates[number_format($purchaseRate, 6, '.', '')]))) {
                    throw new \InvalidArgumentException('A submitted stock line has an invalid denomination or CFA purchase rate.');
                }
            }
        }
        $db->query(
            'UPDATE vault_reconciliations SET status = :status, reviewed_by = :reviewed_by, reviewed_at = :reviewed_at, reviewed_stock_json = :reviewed_stock, reject_reason = :reason WHERE id = :id AND status = \'Pending\'',
            [
                'status' => $choice === 'approve' ? 'Approved' : 'Rejected',
                'reviewed_by' => $userId,
                'reviewed_at' => $now,
                'reviewed_stock' => json_encode($reviewedStockSnapshot, JSON_UNESCAPED_SLASHES),
                'reason' => $choice === 'reject' ? $reason : null,
                'id' => $reconciliationId,
            ]
        );
        if ($db->has_error) {
            throw new \RuntimeException('Unable to save reconciliation review: ' . $db->error);
        }
        if ($db->affected_rows !== 1) {
            throw new \RuntimeException('This reconciliation was already reviewed. Refresh the page and check its latest status.');
        }
        if (!$db->commit()) {
            throw new \RuntimeException('Unable to commit reconciliation.');
        }
        if ((int)$reconciliation->created_by !== $userId) {
            $notifyVaultRecipient(
                'Vault reconciliation',
                $reconciliationId,
                $vaultId,
                (int)$reconciliation->created_by,
                $choice === 'approve' ? 'Approved' : 'Rejected',
                'UPDATE',
                [
                    'Decision' => $choice === 'approve' ? 'Approved' : 'Rejected; resubmission required',
                    'Reason' => $choice === 'reject' ? $reason : '',
                ]
            );
        }
        message_success($choice === 'approve' ? 'Reconciliation approved. Vault balances were not changed.' : 'Reconciliation rejected.');
        redirect($vaultReturnUrl((int)$reconciliation->vault_id, true));
        exit;
    }

    throw new \InvalidArgumentException('Unknown Vault action.');
} catch (\Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('[vaults] action ' . $action . ' failed: ' . $e->getMessage());
    set_value('errors', ['action' => $e->getMessage()]);
    if ($action === 'create_vault') {
        set_value('old_post', $postdata);
        (new \Core\Session)->set('vaults_create_old_post', $postdata);
    }
    message_fail($e->getMessage());
    redirect(isset($postdata['vault_id'])
        ? $vaultReturnUrl((int)$postdata['vault_id'], URL(2) === 'operations')
        : ($isBackofficeEmbed ? $returnUrl . '#vaults' : $returnUrl . ($action === 'create_vault' ? '/operations' : '')));
    exit;
}

<?php

set_value([
    'admin_route' => 'admin',
    'plugin_route' => 'vaults',
]);

require_once __DIR__ . '/helpers/vaults.php';
require_once __DIR__ . '/../backoffice/helpers/cashdrawer-bridge.php';
require_once __DIR__ . '/../backoffice/helpers/whatsapp-notifications.php';
\Vaults\ensureSchema();

add_filter('permissions', static function (array $permissions): array {
    $permissions[] = 'view_vaults';
    $permissions[] = 'manage_vaults';
    return $permissions;
});

add_filter('basic-admin_before_admin_links', static function (array $links): array {
    $url = ROOT . '/admin/vaults';
    $userId = (int)(\get_session()->id ?? 0);
    $attentionCount = \Vaults\attentionCount($userId);
    if (\Vaults\canManage()) {
        foreach ($links as $item) {
            if (!is_object($item) || ($item->id ?? '') !== 'view_operations') {
                continue;
            }
            if (!isset($item->sublinks) || !is_array($item->sublinks)) {
                $item->sublinks = [];
            }
            $item->sublinks[] = (object)[
                'title' => 'Vaults',
                'link' => $url . '/operations',
                'icon' => 'fas fa-vault',
                'parent' => 'view_operations',
                'id' => 'view_operations',
                'count' => $attentionCount > 0 ? (string)$attentionCount : '',
            ];
        }
    }

    return $links;
});

add_action('controller', static function (): void {
    $request = new \Core\Request;
    $postdata = $request->posted() ? $request->post() : [];
    $backofficeVaultAction = URL(1) === 'back_office'
        && in_array((string)($postdata['action'] ?? ''), [
            'create_vault', 'admin_cash_in', 'update_limits', 'edit_vault', 'submit_transaction', 'first_party_action',
            'edit_pending_transaction', 'review_transaction', 'submit_reconciliation', 'review_reconciliation',
        ], true);
    if (URL(1) !== 'vaults' && !$backofficeVaultAction) {
        return;
    }

    if (URL(1) === 'vaults' && URL(2) === 'statement') {
        vaults_export_statement();
        return;
    }

    if ($request->posted()) {
        require __DIR__ . '/controllers/action-controller.php';
    }
});

function vaults_render_page(bool $isOperationsView = false, bool $isEmbeddedView = false): void
{
    $admin_route = 'admin';
    $plugin_route = 'vaults';
    $session = \get_session();
    $userId = (int)($session->id ?? 0);
    if ($userId <= 0) {
        \render_app_error_screen(401, 'Sign in to access Vaults', 'Your session may have expired. Sign in again to continue.', ROOT . '/login', 'Go to login');
        return;
    }

    if ($isOperationsView && !\Vaults\canManage()) {
        \render_app_error_screen(403, 'Operations access required', 'Your role does not include access to Vaults Operations. If this access is needed for your work, contact an administrator.', ROOT . '/admin/back_office/list', 'Return to Backoffice');
        return;
    }

    try {
        $vaults = \Vaults\visibleVaults($userId);
        $transferVaults = \Vaults\activeVaultsForTransfers();
        $transferVaultStock = [];
        $visibleVaultIds = array_values(array_map(
            static function ($vault): int { return (int)$vault->id; },
            $vaults
        ));
        $vaultBalances = [];
        $vaultAttentionCounts = [];
        $vaultReconciliationAttentionCounts = [];
        if ($visibleVaultIds) {
            $stockParams = [];
            $stockPlaceholders = [];
            foreach ($visibleVaultIds as $index => $vaultId) {
                $key = 'stock_vault_' . $index;
                $stockPlaceholders[] = ':' . $key;
                $stockParams[$key] = $vaultId;
            }
            $stockDb = new \Core\Database;
            $transferStockRows = $stockDb->query(
                'SELECT vault_id, currency, denomination, purchase_rate, note_count
                 FROM vault_stock
                 WHERE note_count > 0 AND vault_id IN (' . implode(',', $stockPlaceholders) . ')
                 ORDER BY vault_id, currency, purchase_rate, denomination DESC',
                $stockParams
            ) ?: [];
            if ($stockDb->has_error) {
                throw new \RuntimeException('Unable to load Vault denomination availability: ' . $stockDb->error);
            }
            foreach ($transferStockRows as $stockLine) {
                $transferVaultStock[] = [
                    'vault_id' => (int)$stockLine->vault_id,
                    'currency' => (string)$stockLine->currency,
                    'denomination' => (float)$stockLine->denomination,
                    'purchase_rate' => (float)$stockLine->purchase_rate,
                    'count' => (int)$stockLine->note_count,
                ];
            }
            $balanceParams = [];
            $balancePlaceholders = [];
            foreach ($visibleVaultIds as $index => $vaultId) {
                $key = 'balance_vault_' . $index;
                $balancePlaceholders[] = ':' . $key;
                $balanceParams[$key] = $vaultId;
            }
            $balanceRows = $stockDb->query(
                'SELECT vault_id,
                        SUM(CASE WHEN currency = \'GHS\' THEN denomination * note_count ELSE 0 END) AS ghs_balance,
                        SUM(CASE WHEN currency = \'CFA\' THEN denomination * note_count ELSE 0 END) AS cfa_balance,
                        SUM(CASE WHEN currency = \'CFA\' THEN denomination * note_count * purchase_rate / 1000 ELSE 0 END) AS cfa_ghs_value
                 FROM vault_stock
                 WHERE vault_id IN (' . implode(',', $balancePlaceholders) . ')
                 GROUP BY vault_id',
                $balanceParams
            ) ?: [];
            if ($stockDb->has_error) {
                throw new \RuntimeException('Unable to load Vault balances: ' . $stockDb->error);
            }
            foreach ($balanceRows as $balanceRow) {
                $vaultBalances[(int)$balanceRow->vault_id] = [
                    'GHS' => (float)$balanceRow->ghs_balance,
                    'CFA' => (float)$balanceRow->cfa_balance,
                    'CFA_GHS_VALUE' => (float)$balanceRow->cfa_ghs_value,
                ];
            }
            $attentionCounts = \Vaults\attentionCountsForVaults($userId, $visibleVaultIds);
            foreach ($visibleVaultIds as $vaultId) {
                $transactionCount = (int)($attentionCounts['transactions'][$vaultId] ?? 0);
                $reconciliationCount = (int)($attentionCounts['reconciliations'][$vaultId] ?? 0);
                $vaultAttentionCounts[$vaultId] = $transactionCount + $reconciliationCount;
                $vaultReconciliationAttentionCounts[$vaultId] = $reconciliationCount;
            }
        }
        $staffCashdrawerDenominationCounts = [
            'GHS' => \BackOfficeCashdrawer\getDrawerDenominationCounts($userId, 'GHS'),
            'CFA' => \BackOfficeCashdrawer\getDrawerDenominationCounts($userId, 'CFA'),
        ];
        $currentCfaRate = \Vaults\configuredCfaRate() ?? 0.0;
        $allVaultBalances = ['GHS' => 0.0, 'CFA' => 0.0, 'CFA_GHS_VALUE' => 0.0];
        foreach ($vaults as $vault) {
            $vaultBalance = $vaultBalances[(int)$vault->id] ?? ['GHS' => 0.0, 'CFA' => 0.0, 'CFA_GHS_VALUE' => 0.0];
            foreach ($allVaultBalances as $currency => $amount) {
                $allVaultBalances[$currency] += $vaultBalance[$currency];
            }
        }
        $allBranches = [];
        $users = [];
        $transactions = [];
        $movements = [];
        $statementMovements = [];
        $reconciliations = [];
        $pendingReconciliation = null;
        $latestReconciliation = null;
        $selectedVaultId = max(0, (int)($_GET['vault_id'] ?? URL(3) ?? 0));
        $selectedVault = $selectedVaultId > 0
            ? \Vaults\getVault($selectedVaultId, $userId, false, \user_can('is_admin'))
            : null;
        if ($selectedVault) {
            foreach ($vaults as $visibleVault) {
                if ((int)$visibleVault->id === $selectedVaultId) {
                    $selectedVault = $visibleVault;
                    break;
                }
            }
        }
        $cashInDrawerUsers = [];
        $drawerLookupDb = new \Core\Database;
        if (\user_can('is_admin')
            && $selectedVault
            && (int)$selectedVault->created_by === $userId) {
            $today = new \DateTimeImmutable('today', new \DateTimeZone('Africa/Accra'));
            $cashInDrawerUsers = $drawerLookupDb->query(
                "SELECT u.id, u.first_name, u.last_name, MAX(c.id) AS cashdrawer_id
                 FROM users u
                 INNER JOIN cashdrawers c ON c.user_id = u.id
                   AND c.date_created >= :day_start AND c.date_created < :day_end
                 WHERE u.branch_id = :branch_id
                   AND u.deleted = 0
                   AND (u.disabled IS NULL OR u.disabled = 0)
                 GROUP BY u.id, u.first_name, u.last_name
                 ORDER BY u.first_name, u.last_name, u.id",
                [
                     'day_start' => $today->format('Y-m-d H:i:s'),
                     'day_end' => $today->modify('+1 day')->format('Y-m-d H:i:s'),
                     'branch_id' => (int)$selectedVault->branch_id,
                 ]
            ) ?: [];
            if ($drawerLookupDb->has_error) {
                throw new \RuntimeException('Unable to load cashdrawers available for a Vault cash-in: ' . $drawerLookupDb->error);
            }
            $drawerPlaceholders = [];
            $drawerParams = [];
            foreach ($cashInDrawerUsers as $index => $drawerUser) {
                $drawerId = (int)($drawerUser->cashdrawer_id ?? 0);
                if ($drawerId <= 0) {
                    continue;
                }
                $key = 'drawer_id_' . $index;
                $drawerPlaceholders[] = ':' . $key;
                $drawerParams[$key] = $drawerId;
            }
            $drawerCounts = ['GHS' => [], 'CFA' => []];
            if ($drawerPlaceholders) {
                $drawerInClause = implode(',', $drawerPlaceholders);
                if ($drawerLookupDb->table_exists(['drawer_denos'])) {
                    $ghsRows = $drawerLookupDb->query(
                        'SELECT drawer_id, user_id, denomination, COALESCE(SUM(count), 0) AS note_count
                         FROM drawer_denos
                         WHERE drawer_id IN (' . $drawerInClause . ')
                         GROUP BY drawer_id, user_id, denomination',
                        $drawerParams
                    ) ?: [];
                    if ($drawerLookupDb->has_error) {
                        throw new \RuntimeException('Unable to load GHS cashdrawer denominations for Vault cash-in: ' . $drawerLookupDb->error);
                    }
                    foreach ($ghsRows as $row) {
                        $denominationKey = number_format((float)$row->denomination, 2, '.', '');
                        if ($denominationKey !== '0.00') {
                            $drawerCounts['GHS'][(int)$row->drawer_id][$denominationKey] = (int)$row->note_count;
                        }
                    }
                }
                if (function_exists('\\Cfas\\cfa_ensure_fx_table')) {
                    \Cfas\cfa_ensure_fx_table();
                }
                if ($drawerLookupDb->table_exists(['drawer_fx_denos'])) {
                    $cfaRows = $drawerLookupDb->query(
                        "SELECT drawer_id, user_id, denomination, COALESCE(SUM(count), 0) AS note_count
                         FROM drawer_fx_denos
                         WHERE drawer_id IN (" . $drawerInClause . ") AND currency = 'CFA'
                         GROUP BY drawer_id, user_id, denomination",
                        $drawerParams
                    ) ?: [];
                    if ($drawerLookupDb->has_error) {
                        throw new \RuntimeException('Unable to load CFA cashdrawer denominations for Vault cash-in: ' . $drawerLookupDb->error);
                    }
                    foreach ($cfaRows as $row) {
                        $denominationKey = (string)(int)round((float)$row->denomination);
                        if ($denominationKey !== '0') {
                            $drawerCounts['CFA'][(int)$row->drawer_id][$denominationKey] = (int)$row->note_count;
                        }
                    }
                }
            }
            foreach ($cashInDrawerUsers as $drawerUser) {
                $drawerId = (int)($drawerUser->cashdrawer_id ?? 0);
                $drawerUser->ghs_counts = $drawerCounts['GHS'][$drawerId] ?? [];
                $drawerUser->cfa_counts = $drawerCounts['CFA'][$drawerId] ?? [];
            }
        }
        if ($selectedVault) {
            $selectedVaultAttentionCount = (int)($attentionCounts['transactions'][$selectedVaultId] ?? 0);
            $selectedVaultReconciliationAttentionCount = (int)($vaultReconciliationAttentionCounts[$selectedVaultId] ?? 0);
            $balances = $vaultBalances[$selectedVaultId] ?? ['GHS' => 0.0, 'CFA' => 0.0, 'CFA_GHS_VALUE' => 0.0];
            $stock = \Vaults\stockRows($selectedVaultId);
            $reconciliationStock = $stock;
            $cfaRate = $currentCfaRate;
            $reconciliationRates = ['GHS' => [1.0], 'CFA' => []];
            $rateDb = new \Core\Database;
            $historicalCfaRateRows = $rateDb->query(
                "SELECT DISTINCT purchase_rate FROM vault_movements
                 WHERE vault_id = :vault_id AND currency = 'CFA' AND purchase_rate > 0",
                ['vault_id' => $selectedVaultId]
            ) ?: [];
            if ($rateDb->has_error) {
                throw new \RuntimeException('Unable to read this vault’s CFA purchase-rate history: ' . $rateDb->error);
            }
            foreach ($historicalCfaRateRows as $rateRow) {
                $reconciliationRates['CFA'][] = (float)$rateRow->purchase_rate;
            }
            foreach ($stock as $line) {
                if ((string)$line->currency === 'CFA') {
                    $reconciliationRates['CFA'][] = (float)$line->purchase_rate;
                }
            }
            if ($cfaRate > 0) {
                $reconciliationRates['CFA'][] = $cfaRate;
            }
            $reconciliationRates['CFA'] = array_values(array_unique(array_map(
                static function (float $rate): string { return number_format($rate, 6, '.', ''); },
                $reconciliationRates['CFA']
            )));
            $reconciliationRates['CFA'] = array_map('floatval', $reconciliationRates['CFA']);
            $reconciliationStock = [];
            foreach ([
                'GHS' => [200, 100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1],
                'CFA' => [10000, 5000, 2000, 1000, 500, 200, 100, 50, 25, 10, 5],
            ] as $currency => $denominations) {
                $rates = $currency === 'CFA' ? $reconciliationRates['CFA'] : $reconciliationRates['GHS'];
                foreach ($rates as $rate) {
                    foreach ($denominations as $denomination) {
                        $count = 0;
                        foreach ($stock as $line) {
                            if ((string)$line->currency === $currency
                                && abs((float)$line->denomination - (float)$denomination) < 0.001
                                && abs((float)$line->purchase_rate - $rate) < 0.000001) {
                                $count = (int)$line->note_count;
                                break;
                            }
                        }
                        $reconciliationStock[] = (object)[
                            'currency' => $currency,
                            'denomination' => $denomination,
                            'purchase_rate' => $rate,
                            'note_count' => $count,
                        ];
                    }
                }
            }
            $db = new \Core\Database;
            $transactions = $db->query(
                "SELECT t.*, u.first_name, u.last_name, a.first_name AS first_party_first_name,
                        a.last_name AS first_party_last_name, ap.first_name AS approver_first_name,
                        ap.last_name AS approver_last_name, r.first_name AS reviewer_first_name,
                        r.last_name AS reviewer_last_name, ag.first_name AS agent_first_name,
                        ag.last_name AS agent_last_name, sv.name AS source_vault_name,
                        dv.name AS destination_vault_name,
                        TRIM(CONCAT(COALESCE(du.first_name, ''), ' ', COALESCE(du.last_name, ''))) AS drawer_user_name,
                        COALESCE(t.cashdrawer_id, (
                            SELECT cd.id
                            FROM cashdrawers cd
                            WHERE cd.user_id = t.agent_user_id
                              AND cd.date_created <= t.date_created
                            ORDER BY cd.date_created DESC, cd.id DESC
                            LIMIT 1
                        )) AS agent_cashdrawer_id
                 FROM vault_transactions t
                 LEFT JOIN users u ON u.id = t.requested_by
                 LEFT JOIN users a ON a.id = t.assigned_first_party_id
                 LEFT JOIN users ap ON ap.id = t.assigned_approver_id
                 LEFT JOIN users r ON r.id = t.reviewed_by
                 LEFT JOIN users ag ON ag.id = t.agent_user_id
                 LEFT JOIN users du ON du.id = t.drawer_user_id
                 LEFT JOIN vaults sv ON sv.id = t.source_vault_id
                 LEFT JOIN vaults dv ON dv.id = t.destination_vault_id
                 WHERE t.source_vault_id = :vault_id OR t.destination_vault_id = :vault_id
                 ORDER BY t.id DESC",
                ['vault_id' => $selectedVaultId]
            ) ?: [];
            $movements = $db->query(
                'SELECT m.*, u.first_name, u.last_name, t.transaction_type,
                        t.cashdrawer_id, t.drawer_user_id,
                        TRIM(CONCAT(COALESCE(du.first_name, \'\'), \' \', COALESCE(du.last_name, \'\'))) AS drawer_user_name,
                        t.status AS transaction_status, t.notes AS transaction_notes, t.proof_image AS transaction_proof_image,
                        COALESCE(t.agent_user_id, bt.second_party_user_id, bt.withdrawing_user_id) AS agent_user_id,
                        ia.first_name AS agent_first_name, ia.last_name AS agent_last_name
                 FROM vault_movements m
                 LEFT JOIN users u ON u.id = m.created_by
                 LEFT JOIN vault_transactions t ON t.id = m.transaction_id
                 LEFT JOIN users du ON du.id = t.drawer_user_id
                 LEFT JOIN bank_transfers_backoffice bt ON m.reference_type = \'bank_transfer\' AND bt.id = m.reference_id
                 LEFT JOIN users ia ON ia.id = COALESCE(t.agent_user_id, bt.second_party_user_id, bt.withdrawing_user_id)
                 WHERE m.vault_id = :vault_id ORDER BY m.id DESC',
                ['vault_id' => $selectedVaultId]
            ) ?: [];
            $statementMovements = vaults_group_statement_movements($movements);
            $reconciliations = $db->query(
                'SELECT r.*, u.first_name, u.last_name, a.first_name AS approver_first_name,
                        a.last_name AS approver_last_name, rv.first_name AS reviewer_first_name,
                        rv.last_name AS reviewer_last_name
                 FROM vault_reconciliations r
                 LEFT JOIN users u ON u.id = r.created_by
                 LEFT JOIN users a ON a.id = r.assigned_approver_id
                 LEFT JOIN users rv ON rv.id = r.reviewed_by
                 WHERE r.vault_id = :vault_id ORDER BY r.id DESC',
                ['vault_id' => $selectedVaultId]
            ) ?: [];
            $legacyReconciliations = [];
            foreach ($reconciliations as $reconciliation) {
                $hasStoredSnapshot = trim((string)($reconciliation->reviewed_stock_json ?? '')) !== ''
                    || trim((string)($reconciliation->system_stock_json ?? '')) !== '';
                if ($hasStoredSnapshot) {
                    $reconciliation->snapshot_source = 'Recorded vault balance snapshot';
                    continue;
                }
                $legacyReconciliations[] = $reconciliation;
            }
            if ($legacyReconciliations) {
                $movementHistory = $movements;
                usort($movementHistory, static function ($left, $right): int {
                    $dateCompare = strcmp((string)$left->date_created, (string)$right->date_created);
                    return $dateCompare !== 0
                        ? $dateCompare
                        : ((int)$left->id <=> (int)$right->id);
                });
                usort($legacyReconciliations, static function ($left, $right): int {
                    $dateCompare = strcmp((string)$left->date_created, (string)$right->date_created);
                    return $dateCompare !== 0
                        ? $dateCompare
                        : ((int)$left->id <=> (int)$right->id);
                });

                $stockCounts = [];
                $movementIndex = 0;
                foreach ($legacyReconciliations as $reconciliation) {
                    $reconciliationDate = (string)$reconciliation->date_created;
                    while (
                        isset($movementHistory[$movementIndex])
                        && (string)$movementHistory[$movementIndex]->date_created <= $reconciliationDate
                    ) {
                        $movement = $movementHistory[$movementIndex++];
                        $currency = strtoupper((string)$movement->currency);
                        $denomination = (float)$movement->denomination;
                        $purchaseRate = (float)$movement->purchase_rate;
                        $stockKey = implode('|', [
                            $currency,
                            number_format($denomination, 2, '.', ''),
                            number_format($purchaseRate, 6, '.', ''),
                        ]);
                        $delta = (int)$movement->note_count;
                        $stockCounts[$stockKey] = ($stockCounts[$stockKey] ?? 0)
                            + (strtolower((string)$movement->direction) === 'in' ? $delta : -$delta);
                    }

                    $legacySnapshot = [];
                    foreach ($stockCounts as $stockKey => $count) {
                        if ($count <= 0) {
                            continue;
                        }
                        [$currency, $denomination, $purchaseRate] = explode('|', $stockKey);
                        $legacySnapshot[] = [
                            'currency' => $currency,
                            'denomination' => (float)$denomination,
                            'purchase_rate' => (float)$purchaseRate,
                            'count' => $count,
                        ];
                    }
                    $reconciliation->legacy_system_stock_json = json_encode($legacySnapshot, JSON_UNESCAPED_SLASHES);
                    $reconciliation->snapshot_source = $legacySnapshot
                        ? 'Reconstructed from vault movement history at submission time'
                        : 'Historical vault balance unavailable';
                }
            }
            $latestReconciliation = $reconciliations[0] ?? null;
            $pendingReconciliationRows = $db->query(
                "SELECT id, assigned_approver_id FROM vault_reconciliations
                 WHERE vault_id = :vault_id AND status = 'Pending' ORDER BY id DESC LIMIT 1",
                ['vault_id' => $selectedVaultId]
            ) ?: [];
            $pendingReconciliation = $pendingReconciliationRows[0] ?? null;
            $pendingApprovals = $db->query(
                "SELECT id FROM vault_transactions WHERE assigned_approver_id = :uid AND status = 'Pending Approval'",
                ['uid' => $userId]
            ) ?: [];
            $pendingFirstParty = $db->query(
                "SELECT id FROM vault_transactions WHERE assigned_first_party_id = :uid AND status = 'Pending First Party'",
                ['uid' => $userId]
            ) ?: [];
        } else {
            $selectedVaultAttentionCount = 0;
            $selectedVaultReconciliationAttentionCount = 0;
            $balances = [];
            $stock = [];
            $reconciliationStock = [];
            $pendingReconciliation = null;
            $latestReconciliation = null;
            $movements = [];
            $statementMovements = [];
            $pendingApprovals = [];
            $pendingFirstParty = [];
        }

        $db = new \Core\Database;
        $allBranches = \Vaults\canManage()
            ? ($db->query('SELECT id, name FROM branches WHERE active = 1 AND deleted = 0 ORDER BY name') ?: [])
            : [];
        $users = $db->query(
            "SELECT u.id, u.first_name, u.last_name, u.branch_id
             FROM users u WHERE (u.disabled IS NULL OR u.disabled = 0)
             ORDER BY u.first_name, u.last_name"
        ) ?: [];
        $eligibleTransactionUsers = \Vaults\eligibleStaffToday();

        $errors = \get_value('errors') ?? [];
        $notice = \get_value('notice') ?? '';
        require __DIR__ . '/views/vaults.php';
    } catch (\Throwable $e) {
        error_log('[vaults] page load failed: ' . $e->getMessage());
        echo '<div class="alert alert-danger">Vaults could not be loaded. Please contact an administrator.</div>';
    }
}

function vaults_render_backoffice_tab(): void
{
    vaults_render_page(false, true);
}

function vaults_group_statement_movements(array $movements): array
{
    $groups = [];
    foreach ($movements as $movement) {
        $transactionId = (int)($movement->transaction_id ?? 0);
        $referenceType = (string)($movement->reference_type ?? '');
        $referenceId = (int)($movement->reference_id ?? 0);
        $sourceKey = $transactionId > 0
            ? 'transaction:' . $transactionId
            : 'reference:' . $referenceType . ':' . $referenceId;
        $key = implode('|', [
            $sourceKey,
            strtolower((string)($movement->direction ?? '')),
            strtoupper((string)($movement->currency ?? '')),
        ]);

        if (!isset($groups[$key])) {
            $group = clone $movement;
            $group->amount = 0.0;
            $group->note_count = 0;
            $group->denominations = [];
            $group->first_movement_id = (int)$movement->id;
            $group->last_movement_id = (int)$movement->id;
            $group->balance_before = (float)$movement->balance_before;
            $group->balance_after = (float)$movement->balance_after;
            $groups[$key] = $group;
        }

        $group = $groups[$key];
        $group->amount += (float)$movement->amount;
        $group->note_count += (int)$movement->note_count;
        $group->denominations[] = [
            'denomination' => (float)$movement->denomination,
            'purchase_rate' => (float)$movement->purchase_rate,
            'note_count' => (int)$movement->note_count,
            'amount' => (float)$movement->amount,
            'currency' => strtoupper((string)$movement->currency),
        ];
        $movementId = (int)$movement->id;
        if ($movementId < $group->first_movement_id) {
            $group->first_movement_id = $movementId;
            $group->balance_before = (float)$movement->balance_before;
        }
        if ($movementId >= $group->last_movement_id) {
            $group->last_movement_id = $movementId;
            $group->balance_after = (float)$movement->balance_after;
            $group->date_created = $movement->date_created;
        }
    }

    return array_values($groups);
}

function vaults_build_analytics(array $statementMovements, ?string $requestedStart = null, ?string $requestedEnd = null): array
{
    $timezone = new \DateTimeZone('Africa/Accra');
    $today = new \DateTimeImmutable('today', $timezone);
    $thirtyDayStart = $today->modify('-29 days')->format('Y-m-d');
    $defaultStart = $today->modify('-13 days');
    $parseDate = static function (?string $date) use ($timezone): ?\DateTimeImmutable {
        if ($date === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$parsed || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $parsed->format('Y-m-d') !== $date) {
            return null;
        }
        return $parsed;
    };
    $startDate = $parseDate($requestedStart) ?? $defaultStart;
    $endDate = $parseDate($requestedEnd) ?? $today;
    if ($startDate > $endDate) {
        [$startDate, $endDate] = [$endDate, $startDate];
    }
    $rangeLimited = false;
    if ($startDate->diff($endDate)->days > 365) {
        $startDate = $endDate->modify('-365 days');
        $rangeLimited = true;
    }
    $chartStart = $startDate->format('Y-m-d');
    $chartEnd = $endDate->format('Y-m-d');
    $dailyNet = [];
    $inflow = 0.0;
    $outflow = 0.0;
    $activityIds = [];

    for ($date = $chartStart; $date <= $chartEnd; $date = (new \DateTimeImmutable($date, $timezone))->modify('+1 day')->format('Y-m-d')) {
        $dailyNet[$date] = 0.0;
    }

    foreach ($statementMovements as $movement) {
        $date = substr((string)($movement->date_created ?? ''), 0, 10);
        if (strlen($date) !== 10) {
            continue;
        }

        $directionIn = strtolower((string)($movement->direction ?? '')) === 'in';
        $currency = strtoupper((string)($movement->currency ?? ''));
        $amount = (float)($movement->amount ?? 0);
        $ghsEquivalent = $amount;
        if ($currency === 'CFA') {
            $ghsEquivalent = 0.0;
            foreach (($movement->denominations ?? []) as $denomination) {
                $ghsEquivalent += (float)($denomination['amount'] ?? 0)
                    * (float)($denomination['purchase_rate'] ?? 0) / 1000;
            }
        }

        if ($date >= $thirtyDayStart && $date <= $today->format('Y-m-d')) {
            if ($directionIn) {
                $inflow += $ghsEquivalent;
            } else {
                $outflow += $ghsEquivalent;
            }
            $transactionId = (int)($movement->transaction_id ?? 0);
            $activityIds[$transactionId > 0
                ? 'transaction:' . $transactionId
                : 'reference:' . (string)($movement->reference_type ?? '') . ':' . (int)($movement->reference_id ?? 0)] = true;
        }

        if (isset($dailyNet[$date])) {
            $dailyNet[$date] += $directionIn ? $ghsEquivalent : -$ghsEquivalent;
        }
    }

    $maxDailyNet = max(0.0, ...array_map('abs', array_values($dailyNet)));
    $trend = [];
    foreach ($dailyNet as $date => $net) {
        $trend[] = [
            'date' => $date,
            'label' => (new \DateTimeImmutable($date, $timezone))->format('M j'),
            'net' => round($net, 2),
            'height' => $maxDailyNet > 0 ? min(48, abs($net) / $maxDailyNet * 48) : 0,
        ];
    }

    return [
        'inflow_30d' => round($inflow, 2),
        'outflow_30d' => round($outflow, 2),
        'net_30d' => round($inflow - $outflow, 2),
        'activity_count_30d' => count($activityIds),
        'trend' => $trend,
        'trend_start' => $chartStart,
        'trend_end' => $chartEnd,
        'trend_range_limited' => $rangeLimited,
    ];
}

add_action('basic-admin_main_content', static function (): void {
    if (URL(1) === 'vaults') {
        vaults_render_page(URL(2) === 'operations');
    }
});

function vaults_export_statement(): void
{
    $session = \get_session();
    $userId = (int)($session->id ?? 0);
    $vaultId = (int)($_GET['vault_id'] ?? 0);
    $format = strtolower(trim((string)($_GET['format'] ?? 'xlsx')));
    $vault = \Vaults\getVault($vaultId, $userId);
    if (!$vault || !in_array($format, ['csv', 'xlsx', 'pdf'], true)) {
        http_response_code($vault ? 400 : 403);
        echo 'Invalid statement request.';
        return;
    }

    $db = new \Core\Database;
    $filters = [
        'search' => trim((string)($_GET['search'] ?? '')),
        'direction' => strtolower(trim((string)($_GET['direction'] ?? ''))),
        'currency' => strtoupper(trim((string)($_GET['currency'] ?? ''))),
        'date_from' => trim((string)($_GET['date_from'] ?? '')),
        'date_to' => trim((string)($_GET['date_to'] ?? '')),
    ];
    foreach (['date_from', 'date_to'] as $dateFilter) {
        if ($filters[$dateFilter] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters[$dateFilter])) {
            $filters[$dateFilter] = '';
        }
    }
    if (!in_array($filters['direction'], ['', 'in', 'out'], true)) {
        $filters['direction'] = '';
    }
    if (!in_array($filters['currency'], ['', 'GHS', 'CFA'], true)) {
        $filters['currency'] = '';
    }

    $rows = $db->query(
        'SELECT m.*, u.first_name, u.last_name, t.transaction_type, t.status AS transaction_status,
                t.notes AS transaction_notes, t.proof_image AS transaction_proof_image,
                t.cashdrawer_id, t.drawer_user_id,
                TRIM(CONCAT(COALESCE(du.first_name, \'\'), \' \', COALESCE(du.last_name, \'\'))) AS drawer_user_name
         FROM vault_movements m
         LEFT JOIN users u ON u.id = m.created_by
         LEFT JOIN vault_transactions t ON t.id = m.transaction_id
         LEFT JOIN users du ON du.id = t.drawer_user_id
         LEFT JOIN bank_transfers_backoffice bt ON m.reference_type = \'bank_transfer\' AND bt.id = m.reference_id
         LEFT JOIN users ia ON ia.id = COALESCE(t.agent_user_id, bt.second_party_user_id, bt.withdrawing_user_id)
         WHERE m.vault_id = :vault_id
         ORDER BY m.id DESC',
        ['vault_id' => $vaultId]
    ) ?: [];

    $activityLabel = static function ($row): string {
        $type = (string)($row->transaction_type ?: $row->reference_type);
        $labels = [
            'to_agent' => 'Cash issued to agent',
            'from_agent' => 'Cash received from agent',
            'interbranch' => 'Interbranch vault transfer',
            'denos_exchange' => 'Denomination exchange',
            'admin_cash_in' => 'Admin cash-in',
            'cashdrawer_opening' => 'Daily cashdrawer issued',
            'cashdrawer_return' => 'Daily cashdrawer return',
            'opening_stock' => 'Opening balance',
            'bank_transfer' => strtolower((string)$row->direction) === 'in' ? 'Bank deposit' : 'Bank withdrawal',
            'vault_transaction' => 'Vault transaction',
            'vault_reconciliation' => 'Vault reconciliation',
        ];
        $activity = $labels[$type] ?? ucwords(str_replace('_', ' ', $type));
        return $activity . ' - ' . (strtolower((string)$row->direction) === 'in' ? 'IN' : 'OUT') . ' ' . strtoupper((string)$row->currency);
    };
    $movementDateLabel = static function ($date): string {
        $timestamp = strtotime((string)$date);
        return $timestamp === false ? '' : date('j M Y H:i', $timestamp);
    };
    $rows = vaults_group_statement_movements($rows);
    if ($filters['direction'] !== '' || $filters['currency'] !== '' || $filters['date_from'] !== '' || $filters['date_to'] !== '' || $filters['search'] !== '') {
        $searchTerm = strtolower($filters['search']);
        $rows = array_values(array_filter($rows, static function ($row) use ($filters, $searchTerm, $activityLabel, $movementDateLabel): bool {
            $direction = strtolower((string)$row->direction);
            $currency = strtoupper((string)$row->currency);
            $date = substr((string)$row->date_created, 0, 10);
            if ($filters['direction'] !== '' && $direction !== $filters['direction']) return false;
            if ($filters['currency'] !== '' && $currency !== $filters['currency']) return false;
            if ($filters['date_from'] !== '' && $date < $filters['date_from']) return false;
            if ($filters['date_to'] !== '' && $date > $filters['date_to']) return false;
            if ($searchTerm === '') return true;

            $searchable = implode(' ', [
                (string)($row->transaction_id ?: $row->reference_id),
                $activityLabel($row),
                $movementDateLabel($row->date_created),
                $direction,
                $currency,
                (string)$row->amount,
                (string)$row->note_count,
                (string)$row->balance_before,
                (string)$row->balance_after,
                (string)($row->transaction_status ?? ''),
                (string)($row->transaction_notes ?? ''),
                (string)($row->cashdrawer_id ?? ''),
                (string)($row->drawer_user_name ?? ''),
                trim((string)$row->first_name . ' ' . (string)$row->last_name),
                trim((string)($row->agent_first_name ?? '') . ' ' . (string)($row->agent_last_name ?? '')),
                implode(' ', array_map(static function (array $line): string {
                    return implode(' ', [$line['denomination'], $line['purchase_rate'], $line['note_count'], $line['amount']]);
                }, $row->denominations)),
            ]);
            return str_contains(strtolower($searchable), $searchTerm);
        }));
    }

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="vault-' . $vaultId . '-statement.csv"');
        $output = fopen('php://output', 'w');
        if (!$output) {
            throw new \RuntimeException('Unable to create statement export.');
        }
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['Transaction ID', 'Activity', 'Date', 'Direction', 'Currency', 'Total amount', 'Total notes / coins', 'Balance before', 'Balance after', 'Denomination details', 'Status', 'Notes / reference', 'Proof file', 'Agent / staff', 'Cashdrawer ID', 'Drawer user']);
        foreach ($rows as $row) {
            $denominationDetails = implode('; ', array_map(static function (array $line): string {
                return number_format($line['denomination'], $line['currency'] === 'CFA' ? 0 : 2, '.', '')
                    . ' x ' . $line['note_count']
                    . ($line['currency'] === 'CFA' ? ' @ CFA 1,000 = GHS ' . number_format($line['purchase_rate'], 2, '.', '') : '');
            }, $row->denominations));
            fputcsv($output, [
                $row->transaction_id ?: $row->reference_id,
                $activityLabel($row),
                $movementDateLabel($row->date_created),
                strtoupper((string)$row->direction),
                strtoupper((string)$row->currency),
                number_format((float)$row->amount, 2, '.', ''),
                (int)$row->note_count,
                number_format((float)$row->balance_before, 2, '.', ''),
                number_format((float)$row->balance_after, 2, '.', ''),
                $denominationDetails,
                (string)($row->transaction_status ?? ''),
                (string)($row->transaction_notes ?? ''),
                (string)($row->transaction_proof_image ?? ''),
                trim((string)($row->agent_first_name ?? '') . ' ' . (string)($row->agent_last_name ?? '')) ?: trim((string)$row->first_name . ' ' . (string)$row->last_name),
                (string)($row->cashdrawer_id ?? ''),
                (string)($row->drawer_user_name ?? ''),
            ]);
        }
        fclose($output);
        exit;
    }

    if ($format === 'xlsx') {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class) && is_file(ROOTPATH . 'assets/vendor/autoload.php')) {
            require_once ROOTPATH . 'assets/vendor/autoload.php';
        }
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            http_response_code(500);
            echo 'Excel export is unavailable because the spreadsheet library is not installed.';
            return;
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Vault Statement');
        $appName = defined('APP_NAME') && trim((string)APP_NAME) !== '' ? trim((string)APP_NAME) : 'Vaults';
        $org = [];
        try {
            $settings = $db->query('SELECT * FROM settings ORDER BY id DESC LIMIT 1');
            $org = is_array($settings) && isset($settings[0]) ? (array)$settings[0] : (array)($settings ?? []);
        } catch (\Throwable $e) {
            error_log('[vaults] statement branding lookup failed: ' . $e->getMessage());
        }
        $companyName = trim((string)($org['company_name'] ?? '')) ?: $appName;
        $branchName = '';
        if (isset($vault->branch_id)) {
            $branchRows = $db->query('SELECT name FROM branches WHERE id = :id LIMIT 1', ['id' => (int)$vault->branch_id]) ?: [];
            $branchName = (string)($branchRows[0]->name ?? '');
        }
        $headers = ['Transaction ID', 'Activity', 'Date', 'Direction', 'Currency', 'Total amount', 'Total notes / coins', 'Balance before', 'Balance after', 'Denomination details', 'Status', 'Notes / reference', 'Proof file', 'Agent / staff', 'Cashdrawer ID', 'Drawer user'];
        $sheet->mergeCells('A1:P1');
        $sheet->setCellValue('A1', $companyName);
        $sheet->mergeCells('A2:P2');
        $sheet->setCellValue('A2', $appName . ' | Vault statement');
        $sheet->mergeCells('A3:P3');
        $sheet->setCellValue('A3', 'Vault: ' . (string)$vault->name . ($branchName !== '' ? ' | Branch: ' . $branchName : ''));
        $sheet->mergeCells('A4:P4');
        $sheet->setCellValue('A4', 'Generated: ' . date('d M Y H:i:s'));
        $sheet->fromArray($headers, null, 'A6');

        $line = 7;
        foreach ($rows as $row) {
            $sheet->fromArray([
                $row->transaction_id ?: $row->reference_id,
                $activityLabel($row),
                $movementDateLabel($row->date_created),
                strtoupper((string)$row->direction),
                strtoupper((string)$row->currency),
                (float)$row->amount,
                (int)$row->note_count,
                (float)$row->balance_before,
                (float)$row->balance_after,
                implode('; ', array_map(static function (array $line): string {
                    return number_format($line['denomination'], $line['currency'] === 'CFA' ? 0 : 2)
                        . ' x ' . $line['note_count']
                        . ($line['currency'] === 'CFA' ? ' @ CFA 1,000 = GHS ' . number_format($line['purchase_rate'], 2) : '');
                }, $row->denominations)),
                (string)($row->transaction_status ?? ''),
                (string)($row->transaction_notes ?? ''),
                (string)($row->transaction_proof_image ?? ''),
                trim((string)($row->agent_first_name ?? '') . ' ' . (string)($row->agent_last_name ?? '')) ?: trim((string)$row->first_name . ' ' . (string)$row->last_name),
                (string)($row->cashdrawer_id ?? ''),
                (string)($row->drawer_user_name ?? ''),
            ], null, 'A' . $line);
            $line++;
        }
        $lastRow = max(6, $line - 1);
        $sheet->getStyle('A1:P1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 17, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '17365D']],
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getStyle('A2:P2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('17365D');
        $sheet->getStyle('A3:P4')->getFont()->getColor()->setRGB('526477');
        $sheet->getStyle('A6:P6')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '2574A9']],
            'alignment' => ['wrapText' => true, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(6)->setRowHeight(34);
        if ($lastRow > 6) {
            $sheet->getStyle('A7:P' . $lastRow)->applyFromArray([
                'borders' => ['bottom' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_HAIR, 'color' => ['rgb' => 'D9E2F3']]],
                'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
            ]);
            for ($rowNumber = 7; $rowNumber <= $lastRow; $rowNumber++) {
                if ($rowNumber % 2 === 0) {
                    $sheet->getStyle('A' . $rowNumber . ':P' . $rowNumber)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F3F7FB');
                }
                $direction = strtoupper((string)$sheet->getCell('D' . $rowNumber)->getValue());
                $sheet->getStyle('D' . $rowNumber)->getFont()->getColor()->setRGB($direction === 'IN' ? '137547' : 'B42318');
            }
            $sheet->getStyle('F7:F' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('H7:I' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->setAutoFilter('A6:P' . $lastRow);
        }
        $widths = [16, 31, 20, 12, 10, 16, 17, 18, 18, 52, 16, 48, 36, 24, 16, 24];
        foreach ($widths as $index => $width) {
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $sheet->freezePane('A7');
        $sheet->getHeaderFooter()->setOddFooter('&L' . $companyName . ' | Confidential&CPage &P of &N&RGenerated &D');
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);

        $detailsSheet = $spreadsheet->createSheet();
        $detailsSheet->setTitle('Denomination details');
        $detailsHeaders = ['Transaction ID', 'Activity', 'Date', 'Direction', 'Currency', 'Denomination', 'Purchased rate (GHS / 1,000 CFA)', 'Count', 'Face value'];
        $detailsSheet->mergeCells('A1:I1');
        $detailsSheet->setCellValue('A1', $companyName . ' | Vault denomination details');
        $detailsSheet->fromArray($detailsHeaders, null, 'A3');
        $detailRow = 4;
        foreach ($rows as $row) {
            foreach ($row->denominations as $denomination) {
                $detailsSheet->fromArray([
                    $row->transaction_id ?: $row->reference_id,
                    $activityLabel($row),
                    $movementDateLabel($row->date_created),
                    strtoupper((string)$row->direction),
                    strtoupper((string)$row->currency),
                    (float)$denomination['denomination'],
                    $denomination['currency'] === 'CFA' ? (float)$denomination['purchase_rate'] : null,
                    (int)$denomination['note_count'],
                    (float)$denomination['amount'],
                ], null, 'A' . $detailRow);
                $detailRow++;
            }
        }
        $detailLastRow = max(3, $detailRow - 1);
        $detailsSheet->getStyle('A1:I1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 15, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '17365D']],
        ]);
        $detailsSheet->getStyle('A3:I3')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '2574A9']],
            'alignment' => ['wrapText' => true],
        ]);
        if ($detailLastRow > 3) {
            $detailsSheet->setAutoFilter('A3:I' . $detailLastRow);
            $detailsSheet->getStyle('F4:G' . $detailLastRow)->getNumberFormat()->setFormatCode('#,##0.00');
            $detailsSheet->getStyle('I4:I' . $detailLastRow)->getNumberFormat()->setFormatCode('#,##0.00');
        }
        foreach (['A' => 16, 'B' => 30, 'C' => 20, 'D' => 12, 'E' => 10, 'F' => 16, 'G' => 29, 'H' => 12, 'I' => 16] as $column => $width) {
            $detailsSheet->getColumnDimension($column)->setWidth($width);
        }
        $detailsSheet->freezePane('A4');
        $detailsSheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);

        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="vault-' . $vaultId . '-statement-' . date('Ymd-His') . '.xlsx"');
        header('Cache-Control: max-age=0');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
        $spreadsheet->disconnectWorksheets();
        exit;
    }

    if (!class_exists(\Dompdf\Dompdf::class) && is_file(ROOTPATH . 'assets/vendor/autoload.php')) {
        require_once ROOTPATH . 'assets/vendor/autoload.php';
    }
    if (!class_exists(\Dompdf\Dompdf::class) && is_file(ROOTPATH . 'vendor/autoload.php')) {
        require_once ROOTPATH . 'vendor/autoload.php';
    }
    if (!class_exists(\Dompdf\Dompdf::class)) {
        http_response_code(500);
        echo 'PDF export is unavailable because the PDF library is not installed.';
        return;
    }
    $org = [];
    try {
        $settings = $db->query('SELECT * FROM settings ORDER BY id DESC LIMIT 1');
        $org = is_array($settings) && isset($settings[0]) ? (array)$settings[0] : (array)($settings ?? []);
    } catch (\Throwable $e) {
        error_log('[vaults] statement branding lookup failed: ' . $e->getMessage());
    }
    $appName = defined('APP_NAME') && trim((string)APP_NAME) !== '' ? trim((string)APP_NAME) : 'Vaults';
    $companyName = trim((string)($org['company_name'] ?? '')) ?: $appName;
    $companyTitle = trim((string)($org['title'] ?? ''));
    $contactParts = array_filter([
        trim((string)($org['admin_contact'] ?? '')),
        trim((string)($org['ceo_contact'] ?? '')),
        trim((string)($org['company_sim'] ?? '')),
    ]);
    $branchName = '';
    if (isset($vault->branch_id)) {
        $branchRows = $db->query('SELECT name FROM branches WHERE id = :id LIMIT 1', ['id' => (int)$vault->branch_id]) ?: [];
        $branchName = (string)($branchRows[0]->name ?? '');
    }
    $h = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    $logo = trim((string)($org['image'] ?? ''));
    if ($logo !== '' && function_exists('get_image')) {
        $logo = (string)get_image($logo);
    } elseif ($logo === '') {
        $logo = defined('APP_LOGO2') ? (string)APP_LOGO2 : (defined('APP_LOGO') ? (string)APP_LOGO : '');
    }
    $logoHtml = $logo !== '' ? '<img class="brand-logo" src="' . $h($logo) . '" alt="Logo">' : '';
    $brandMeta = implode(' | ', $contactParts);
    $movementTotal = count($rows);
    $generatedAt = date('d M Y H:i:s');
    $html = '<!doctype html><html><head><meta charset="utf-8"><style>
        @page { margin: 26mm 12mm 20mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #243447; font-size: 8px; }
        .brand { width:100%; border-bottom:3px solid #2474a6; padding:0 0 10px; margin-bottom:12px; }
        .brand-logo { width:52px; height:52px; object-fit:contain; vertical-align:middle; margin-right:12px; }
        .brand-name { color:#17365d; font-size:17px; font-weight:bold; }
        .brand-sub { color:#64748b; font-size:8px; margin-top:3px; }
        .report-title { font-size:15px; color:#17365d; margin:4px 0; }
        .meta { width:100%; border-collapse:collapse; margin:10px 0 13px; }
        .meta td { border:1px solid #d9e2ec; padding:6px 8px; background:#f5f8fb; }
        .meta strong { color:#17365d; }
        .summary { background:#eaf3f8; border-left:4px solid #2474a6; padding:7px 9px; margin:0 0 12px; }
        table.statement { width:100%; border-collapse:collapse; table-layout:fixed; }
        .statement thead { display:table-header-group; }
        .statement th { background:#17365d; color:#fff; padding:6px 4px; text-align:left; font-size:7px; }
        .statement td { border-bottom:1px solid #dce4ec; padding:5px 4px; vertical-align:top; word-wrap:break-word; }
        .statement tr:nth-child(even) td { background:#f4f7fa; }
        .direction-in { color:#137547; font-weight:bold; }
        .direction-out { color:#b42318; font-weight:bold; }
        .empty { text-align:center; padding:18px !important; color:#64748b; }
        .footnote { color:#64748b; font-size:7px; margin-top:10px; }
    </style></head><body>';
    $html .= '<div class="brand">' . $logoHtml . '<span class="brand-name">' . $h($companyName) . '</span>';
    if ($companyTitle !== '') {
        $html .= '<div class="brand-sub">' . $h($companyTitle) . '</div>';
    }
    if ($brandMeta !== '') {
        $html .= '<div class="brand-sub">' . $h($brandMeta) . '</div>';
    }
    $html .= '</div><div class="report-title">Vault transaction statement</div>';
    $html .= '<table class="meta"><tr><td><strong>Vault</strong><br>' . $h($vault->name) . '</td><td><strong>Branch</strong><br>' . $h($branchName ?: '—') . '</td><td><strong>Generated</strong><br>' . $h($generatedAt) . '</td></tr></table>';
    $html .= '<div class="summary"><strong>' . $movementTotal . '</strong> transaction ' . ($movementTotal === 1 ? 'row' : 'rows') . ' | Amounts and balances are shown in their original currency.</div>';
    $html .= '<table class="statement"><thead><tr><th style="width:8%">ID</th><th style="width:17%">Activity</th><th style="width:13%">Date</th><th style="width:8%">Direction</th><th style="width:9%">Currency</th><th style="width:12%">Total amount</th><th style="width:9%">Notes / coins</th><th style="width:12%">Balance before / after</th><th style="width:12%">Agent / staff / drawer</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $direction = strtolower((string)$row->direction) === 'in' ? 'IN' : 'OUT';
        $currency = strtoupper((string)$row->currency);
        $cells = [
            $row->transaction_id ?: $row->reference_id,
            $activityLabel($row),
            $movementDateLabel($row->date_created),
            $direction,
            $currency,
            number_format((float)$row->amount, 2),
            number_format((int)$row->note_count),
            number_format((float)$row->balance_before, 2) . ' / ' . number_format((float)$row->balance_after, 2),
            (trim((string)($row->agent_first_name ?? '') . ' ' . (string)($row->agent_last_name ?? '')) ?: trim((string)$row->first_name . ' ' . (string)$row->last_name))
                . ((int)($row->cashdrawer_id ?? 0) > 0
                    ? ' / Cashdrawer #' . (int)$row->cashdrawer_id . ' - ' . trim((string)($row->drawer_user_name ?? ''))
                    : ''),
        ];
        $html .= '<tr>';
        foreach ($cells as $cell) {
            $class = $cell === $direction ? ($direction === 'IN' ? 'direction-in' : 'direction-out') : '';
            $html .= '<td class="' . $class . '">' . $h($cell) . '</td>';
        }
        $html .= '</tr>';
    }
    if (!$rows) {
        $html .= '<tr><td colspan="9" class="empty">No vault movements are available for this statement.</td></tr>';
    }
    $html .= '</tbody></table><div class="footnote">Generated by ' . $h($appName) . '. This report contains confidential operational information.</div></body></html>';
    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $pdf = new \Dompdf\Dompdf($options);
    $pdf->loadHtml($html);
    $pdf->setPaper('A4', 'landscape');
    $pdf->render();
    $canvas = $pdf->getCanvas();
    $footerFont = $pdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
    $canvas->page_text(730, 570, 'Page {PAGE_NUM} of {PAGE_COUNT}', $footerFont, 8, [0.28, 0.35, 0.43]);
    $pdf->stream('vault-' . $vaultId . '-statement.pdf', ['Attachment' => true]);
    exit;
}

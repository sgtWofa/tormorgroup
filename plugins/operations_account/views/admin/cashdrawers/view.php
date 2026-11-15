<?php
$viewRouteVars = get_value();
$viewRouteVars = is_array($viewRouteVars) ? $viewRouteVars : [];
$admin_route = (string)($viewRouteVars['admin_route'] ?? 'admin');
$plugin_route = (string)($viewRouteVars['plugin_route'] ?? 'operations_accounts');
if(user_can('view_wallet_details')):
?>
<style type="text/css">
    tbody tr{
        white-space: nowrap;
    }
    tbody tr.wrap{
        white-space: normal;
    }

    td.wrap{
        white-space: normal;
    }
</style>

<!-- MODERN CASHDRAWER VIEW -->
<style>
    /* Theme-aware styling */
    :root {
        --bg-primary: #ffffff;
        --bg-secondary: #f9f9f9;
        --text-primary: #333333;
        --text-secondary: #666666;
        --border-color: #f5f5f5;
    }

    @media (prefers-color-scheme: dark) {
        :root {
            --bg-primary: #1e1e1e;
            --bg-secondary: #2d2d2d;
            --text-primary: #ffffff;
            --text-secondary: #cccccc;
            --border-color: #404040;
        }
    }

    /* Modern Cashdrawer Styling */
    .cashdrawer-container {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        padding: 18px;
        color: white;
        box-shadow: 0 8px 22px rgba(0,0,0,0.16);
        margin-bottom: 18px;
    }

    .cashdrawer-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 14px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .cashdrawer-title {
        font-size: 24px;
        font-weight: 700;
        letter-spacing: 0.5px;
    }

    .balance-display {
        background: rgba(255,255,255,0.15);
        padding: 10px 16px;
        border-radius: 8px;
        backdrop-filter: blur(10px);
        font-size: 16px;
        font-weight: 600;
    }

    .action-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .btn-modern {
        padding: 8px 14px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-draft-pending {
        background: rgba(255,193,7,0.9);
        color: #333;
    }

    .btn-draft-pending:hover {
        background: #ffc107;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(255,193,7,0.3);
    }

    .btn-export {
        background: rgba(244,67,54,0.9);
        color: white;
    }

    .btn-export:hover {
        background: #f44336;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(244,67,54,0.3);
    }

    .channels-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr));
        gap: 14px;
        margin-top: 16px;
    }

    .channel-card {
        background: var(--bg-primary);
        color: var(--text-primary);
        border-radius: 12px;
        padding: 16px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.07);
        transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        border: 1px solid var(--border-color);
        min-width: 0;
    }

    .channel-card--ecash {
        border-top: 3px solid #3b82f6;
        background: linear-gradient(145deg, var(--bg-primary), rgba(59,130,246,.045));
    }

    .channel-card--shared {
        border: 2px solid #d6a313;
        border-top-width: 5px;
        background: linear-gradient(145deg, var(--bg-primary), rgba(246,195,67,.17));
        box-shadow: 0 5px 16px rgba(214,163,19,.16);
    }

    .channel-card--cash-ghs {
        border-top: 4px solid #159a63;
        background: linear-gradient(145deg, var(--bg-primary), rgba(21,154,99,.09));
    }

    .channel-card--cash-cfa {
        border-top: 4px solid #7854c8;
        background: linear-gradient(145deg, var(--bg-primary), rgba(120,84,200,.10));
    }

    .channel-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 18px rgba(0,0,0,0.12);
    }

    .channel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 10px;
        gap: 8px;
    }

    .recon-proof-thumb-view {
        width: 46px;
        height: 38px;
        border-radius: 10px;
        overflow: hidden;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(0,0,0,0.06);
        border: 1px solid rgba(0,0,0,0.12);
    }
    .recon-proof-thumb-view img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .channel-name {
        font-size: 16px;
        font-weight: 700;
        color: var(--text-primary);
        min-width: 0;
        overflow-wrap: anywhere;
    }

    .channel-type-badge {
        display: inline-block;
        padding: 4px 9px;
        border-radius: 20px;
        font-size: 10px;
        font-weight: 600;
        white-space: nowrap;
    }

    .badge-cash {
        background: #e8f5e9;
        color: #2e7d32;
    }

    .badge-cash-ghs {
        background: #d7f4e5;
        color: #08633c;
    }

    .badge-cash-cfa {
        background: #ede6ff;
        color: #56339f;
    }

    .badge-ecash {
        background: #e3f2fd;
        color: #1565c0;
    }

    .badge-shared-account {
        background: #fff3cd;
        color: #805b00;
    }

    .channel-type-badge.badge-shared-account {
        display: inline-flex;
        width: 1.8rem;
        height: 1.8rem;
        align-items: center;
        justify-content: center;
        padding: 0;
        font-size: 0.9rem;
    }

    .channel-stats {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }

    .stat-item {
        background: var(--bg-secondary);
        padding: 10px;
        border-radius: 8px;
        text-align: center;
    }

    .stat-label {
        font-size: 12px;
        color: var(--text-secondary);
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 5px;
    }

    .stat-value {
        font-size: 16px;
        font-weight: 700;
        color: var(--text-primary);
    }

    .stat-value.positive {
        color: #4caf50;
    }

    .stat-value.negative {
        color: #f44336;
    }

    /* Sidebar Panel */
    .draft-pending-sidebar {
        position: fixed;
        right: -400px;
        top: 0;
        width: 400px;
        height: 100vh;
        background: var(--bg-primary);
        color: var(--text-primary);
        box-shadow: -5px 0 20px rgba(0,0,0,0.15);
        z-index: 1000;
        transition: right 0.3s ease;
        overflow-y: auto;
    }

    .draft-pending-sidebar.open {
        right: 0;
    }

    .sidebar-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0;
    }

    .sidebar-title {
        font-size: 18px;
        font-weight: 700;
    }

    .close-sidebar {
        background: rgba(255,255,255,0.2);
        border: none;
        color: white;
        font-size: 24px;
        cursor: pointer;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }

    .close-sidebar:hover {
        background: rgba(255,255,255,0.3);
    }

    .sidebar-content {
        padding: 20px;
    }

    .transaction-item {
        border-left: 4px solid #ffc107;
        padding: 15px;
        margin-bottom: 12px;
        border-radius: 6px;
        transition: all 0.3s ease;
    }

    .transaction-status {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .status-draft {
        background: #f3e5f5;
        color: #7b1fa2;
    }

    .status-pending {
        background: #fff3e0;
        color: #e65100;
    }

    .status-opening {
        background: #e8f5e9;
        color: #2e7d32;
    }

    .transaction-details {
        font-size: 13px;
        color: var(--text-secondary);
        margin-bottom: 8px;
    }

    .cash-balance {
        color: var(--bs-modal-color, var(--bs-body-color, #212529)) !important;
    }

    .transaction-amount {
        font-size: 16px;
        font-weight: 700;
        color: var(--text-primary);
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #999;
    }

    .empty-state-icon {
        font-size: 48px;
        margin-bottom: 15px;
        opacity: 0.5;
    }


    @media (max-width: 768px) {
        .draft-pending-sidebar {
            width: 100%;
            right: -100%;
        }

        .channels-grid {
            grid-template-columns: 1fr;
            gap: 10px;
            margin-top: 12px;
        }

        .cashdrawer-container {
            padding: 12px;
            border-radius: 10px;
        }

        .cashdrawer-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }

        .cashdrawer-title {
            font-size: 20px;
        }

        .balance-display {
            width: 100%;
            font-size: 15px;
            padding: 9px 12px;
        }

        .action-buttons {
            width: 100%;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 7px;
        }

        .btn-modern {
            width: 100%;
            justify-content: center;
            min-width: 0;
            padding: 8px 7px;
            font-size: 12px;
        }

        .channel-card {
            padding: 13px;
        }

        .channel-stats {
            gap: 7px;
        }

        .stat-item {
            padding: 8px 6px;
        }

        .stat-value {
            font-size: 14px;
        }
    }
</style>

<!-- GLOBAL FUNCTIONS FOR THIS VIEW -->
<script>
// Draft/Pending Sidebar Toggle
window.toggleDraftPendingPanel = function() {
    const sidebar = document.getElementById('draftPendingSidebar');
    if (sidebar) {
        sidebar.classList.toggle('open');
    }
};

// Denomination History Modal Toggle
window.toggleDenoHistoryModal = function() {
    const modal = document.getElementById('denoHistoryModal');
    if (modal) {
        modal.classList.toggle('show');
    }
};
</script>

<?php if(!empty($row)): ?>

<div class="cashdrawer-container" data-cashdrawer-id="<?= (int)$row->id ?>">
    <div class="cashdrawer-header">
        <div>
            <div class="cashdrawer-title">
                <?= htmlspecialchars(strtoupper($row->user_first_name . "'s Cash Drawer")) ?>
            </div>

<!-- Denomination History Modal -->
<div class="modal fade" id="denoHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-muted">🧾 Denomination History</h5>
                <div style="display:flex; gap:8px;">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="exportDenoHistoryPdfBtn" title="Export to PDF">
                        <i class="fa fa-file-pdf"></i> Export PDF
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body" id="denoHistoryModalBody">
                <?php
                    $prettyChannelKey = function($key){
                        $k = strtoupper(trim((string)$key));
                        if (stripos($k, 'ECASH_') === 0) {
                            $k = substr($k, 6);
                        }
                        return trim(str_replace('_', ' ', $k));
                    };
                    $parseExchangeDescription = function($desc) use ($prettyChannelKey){
                        $desc = (string)$desc;
                        $out = ['from' => '', 'to' => '', 'amount' => 0.0];
                        if (preg_match('/\bfrom=([^\s]+)\s+to=([^\s]+)\s+amount=([0-9.]+)/i', $desc, $m)) {
                            $out['from'] = $prettyChannelKey($m[1] ?? '');
                            $out['to'] = $prettyChannelKey($m[2] ?? '');
                            $out['amount'] = (float)($m[3] ?? 0);
                            return $out;
                        }
                        if (preg_match('/\bamount=([0-9.]+)/i', $desc, $m2)) {
                            $out['amount'] = (float)($m2[1] ?? 0);
                        }
                        return $out;
                    };
                    $renderTransactionDetail = function($tx): string {
                        $detail = is_array($tx ?? null) ? ($tx['transaction_detail'] ?? null) : null;
                        $sourceTables = is_array($tx ?? null) ? ($tx['source_tables'] ?? []) : [];
                        $html = '';
                        if (!empty($sourceTables)) {
                            $labels = array_map(function($sourceTable){
                                return operations_account_pretty_table_label((string)$sourceTable);
                            }, $sourceTables);
                            $html .= '<div class="small mt-1">Deno source: ' . esc(implode(', ', array_unique($labels))) . '</div>';
                        }
                        if (!is_array($detail) || empty($detail)) {
                            return $html;
                        }
                        $bits = [];
                        if (!empty($detail['type'])) $bits[] = 'Type: ' . (string)$detail['type'];
                        if (!empty($detail['network'])) $bits[] = 'Network: ' . (string)$detail['network'];
                        if (!empty($detail['phone'])) $bits[] = 'Phone: ' . (string)$detail['phone'];
                        if (!empty($detail['reference'])) $bits[] = 'Ref: ' . (string)$detail['reference'];
                        if (array_key_exists('amount', $detail) && $detail['amount'] !== null) $bits[] = 'Amount: ' . (string)($detail['currency'] ?? 'GHS') . ' ' . number_format((float)$detail['amount'], 2);
                        if (!empty($detail['status'])) $bits[] = 'Status: ' . (string)$detail['status'];
                        $html .= '<div class="mt-2 p-2" style="border:1px solid var(--bs-border-color); border-radius:8px;">';
                        $detailId = (string)($detail['id'] ?? '');
                        $lookupId = (string)($detail['lookup_id'] ?? '');
                        $idLabel = $detailId !== '' ? (' #' . $detailId) : '';
                        if ($lookupId !== '' && $lookupId !== $detailId) {
                            $idLabel .= ' (TX ' . $lookupId . ')';
                        }
                        $html .= '<div class="fw-semibold small">' . esc((string)($detail['label'] ?? 'Transaction Detail')) . esc($idLabel) . '</div>';
                        if (!empty($bits)) {
                            $html .= '<div class="small mt-1">' . esc(implode(' | ', $bits)) . '</div>';
                        }
                        if (!empty($detail['summary']) && is_array($detail['summary'])) {
                            $summary = array_values(array_filter($detail['summary'], static function ($item): bool {
                                return trim((string)$item) !== '' && !preg_match('/:\s*$/', (string)$item);
                            }));
                            $html .= '<div class="small text-muted mt-1">' . esc(implode(' | ', array_slice($summary, 0, 6))) . '</div>';
                        }
                        $html .= '</div>';
                        return $html;
                    };
                ?>

                <div class="mb-3">
                    <h6 class="mb-2" style="font-weight:600;">🔁 Channel Exchange History</h6>
                    <?php if (!empty($channel_exchange_history)): ?>
                        <?php foreach (($channel_exchange_history ?? []) as $hx): ?>
                            <?php
                                $rawDate = (string)($hx->date_created ?? '');
                                $desc = (string)($hx->description ?? '');
                                $parsed = $parseExchangeDescription($desc);
                                $fromLabel = trim((string)($parsed['from'] ?? ''));
                                $toLabel = trim((string)($parsed['to'] ?? ''));
                                $amt = (float)($parsed['amount'] ?? 0);
                                $line1 = ($fromLabel !== '' && $toLabel !== '') ? ($fromLabel . ' -> ' . $toLabel) : '';
                                $line2 = $amt > 0 ? ('GHS ' . number_format($amt, 2)) : '';
                            ?>
                            <div class="transaction-item mb-2" style="padding:10px; border:1px solid var(--bs-border-color); border-radius:8px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; gap:10px;">
                                    <span class="transaction-status status-exchange">Exchange</span>
                                    <span style="font-size:12px; color:var(--text-secondary);">
                                        <?= esc($rawDate) ?>
                                    </span>
                                </div>
                                <?php if ($line1 !== '' || $line2 !== ''): ?>
                                    <div style="margin-top:6px; font-size:13px; font-weight:600;">
                                        <?= esc(trim($line1)) ?>
                                    </div>
                                    <?php if ($line2 !== ''): ?>
                                        <div style="margin-top:2px; font-size:12px; color:var(--bs-secondary-color);">
                                            <?= esc($line2) ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div style="margin-top:6px; font-size:13px;">
                                        <?= esc($desc) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">🔁</div>
                            <p>No channel exchanges recorded for this drawer yet.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <?php $cashdrawerEditHistory = $cashdrawer_edit_history ?? []; ?>
                <div class="mb-3">
                    <h6 class="mb-2" style="font-weight:600;">Cashdrawer Edit History</h6>
                    <?php if (!empty($cashdrawerEditHistory)): ?>
                        <?php foreach ($cashdrawerEditHistory as $editLog): ?>
                            <?= operations_account_render_cashdrawer_edit_history_entry($editLog) ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">i</div>
                            <p>No cashdrawer edits recorded for this drawer yet.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <?php $openingSnapshotHistory = $opening_snapshot_history ?? []; ?>
                <?php if (!empty($openingSnapshotHistory)): ?>
                    <div class="mb-3">
                        <h6 class="mb-2" style="font-weight:600;">🏦 Opening Cashdrawer Snapshots</h6>
                        <?php foreach ($openingSnapshotHistory as $tx): ?>
                            <?php
                                $txId = $tx['trans_id'] ?? null;
                                $dateCreated = $tx['date_created'] ?? '';
                                $status = $tx['trans_status'] ?? 'Opening Snapshot';
                                $lines = $tx['lines'] ?? [];
                                $meta  = $tx['meta'] ?? ['has_ghs' => false, 'has_cfa' => false, 'net_ghs' => 0.0, 'net_cfa' => 0.0];
                                $totalGhs = 0; $totalCfa = 0;
                                foreach ($lines as $ln) {
                                    if (($ln['currency'] ?? 'GHS') === 'CFA') {
                                        $totalCfa += (float)($ln['volume'] ?? 0);
                                    } else {
                                        $totalGhs += (float)($ln['volume'] ?? 0);
                                    }
                                }
                            ?>
                            <div class="transaction-item mb-3" data-history-index="opening-<?= esc($txId ? $txId : 'snapshot') ?>">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                    <span class="transaction-status status-opening"><?= esc($status) ?></span>
                                    <span style="font-size:12px; color:var(--text-secondary);">
                                        <?= esc($dateCreated) ?>
                                        <br><strong>Opening Snapshot</strong>
                                    </span>
                                </div>
                                <div class="transaction-details">
                                    <strong>TX #<?= $txId ? esc($txId) : 'N/A' ?></strong><br>
                                    GHS: <strong><?= number_format($totalGhs, 2) ?></strong>
                                    &nbsp; | &nbsp;
                                    CFA: <strong><?= number_format($totalCfa, 2) ?></strong>
                                    <?= $renderTransactionDetail($tx) ?>
                                </div>
                                <div style="max-height:180px; overflow:auto; margin-top:6px;">
                                    <table class="table table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th>Curr</th>
                                                <th>Denom</th>
                                                <th>Count</th>
                                                <th>Volume</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($lines as $ln): ?>
                                                <tr>
                                                    <td><?= esc($ln['currency'] ?? 'GHS') ?></td>
                                                    <td>
                                                        <?php
                                                            $curr = $ln['currency'] ?? 'GHS';
                                                            $den = (float)($ln['denomination'] ?? 0);
                                                            echo $curr === 'CFA'
                                                                ? number_format($den, 0)
                                                                : number_format($den, 2);
                                                        ?>
                                                    </td>
                                                    <td><?= (int)($ln['count'] ?? 0) ?></td>
                                                    <td><?= number_format((float)($ln['volume'] ?? 0), 2) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($deno_history)): ?>
                    <div id="denoHistoryMovementList">
                    <?php $denoIndex = 0; foreach ($deno_history as $tx): $denoIndex++; ?>
                        <?php 
                            $txId = $tx['trans_id'] ?? null;
                            $dateCreated = $tx['date_created'] ?? '';
                            $status = $tx['trans_status'] ?? 'Unknown';
                            $statusLabel = preg_replace('/^vault:\s*/i', '', (string)$status);
                            $lines = $tx['lines'] ?? [];
                            $meta  = $tx['meta'] ?? ['has_ghs' => false, 'has_cfa' => false, 'net_ghs' => 0.0, 'net_cfa' => 0.0];
                            $typeLabel = 'Movement';
                        $netGhs = (float)($meta['net_ghs'] ?? 0);
                        $netCfa = (float)($meta['net_cfa'] ?? 0);
                        if (operations_account_is_opening_snapshot_status($status ?? null)) {
                            $typeLabel = 'Opening Snapshot';
                        } elseif (stripos((string)$status, 'vault:') === 0) {
                            $typeLabel = 'Vault Transaction';
                        // Treat zero-net movements without trans_id as denomination exchanges
                        } elseif (empty($txId) && abs($netGhs) < 0.01 && abs($netCfa) < 0.01) {
                            $typeLabel = 'Exchange';
                        } elseif (empty($txId)) {
                            $typeLabel = 'Opening Snapshot';
                        } elseif (!empty($meta['has_ghs']) && !empty($meta['has_cfa'])) {
                            $typeLabel = 'FX Exchange';
                        } else {
                            $net    = $netGhs !== 0.0 ? $netGhs : $netCfa;
                            if ($net > 0) {
                                $typeLabel = 'Cash In';
                            } elseif ($net < 0) {
                                $typeLabel = 'Cash Out';
                            }
                        }
                        $totalGhs = 0; $totalCfa = 0;
                            foreach ($lines as $ln) {
                                if (($ln['currency'] ?? 'GHS') === 'CFA') {
                                    $totalCfa += (float)($ln['volume'] ?? 0);
                                } else {
                                    $totalGhs += (float)($ln['volume'] ?? 0);
                                }
                            }
                        ?>
                        <div class="transaction-item mb-3" data-history-index="<?=$denoIndex?>">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                <?php
                                    if (strcasecmp($status, 'Exchange') === 0) {
                                        $badgeClass = 'status-exchange';
                                    } elseif (operations_account_is_opening_snapshot_status($status ?? null)) {
                                        $badgeClass = 'status-opening';
                                    } else {
                                        $badgeClass = ($statusLabel === 'Approved' ? 'status-pending' : 'status-draft');
                                    }
                                ?>
                                <span class="transaction-status <?= $badgeClass ?>"><?= esc($statusLabel) ?></span>
                                <span style="font-size:12px; color:var(--text-secondary);">
                                    <?= esc($dateCreated) ?>
                                    <br><strong><?= esc($typeLabel) ?></strong>
                                </span>
                            </div>
                            <div class="transaction-details">
                                <strong>TX #<?= $txId ? esc($txId) : 'N/A' ?></strong><br>
                                GHS: <strong><?= number_format($totalGhs, 2) ?></strong>
                                &nbsp; | &nbsp;
                                CFA: <strong><?= number_format($totalCfa, 2) ?></strong>
                                <?= $renderTransactionDetail($tx) ?>
                            </div>
                            <div class="cash-balance text-muted" style="margin-top:8px; padding:8px; border-left:3px solid #0066cc; font-size:11px;">
                                <strong>Cash Balance</strong><br>
                                <div style="margin-top:4px; display:flex; justify-content:space-between; gap:15px;">
                                    <span><strong>Before:</strong> GHS <?= number_format((float)($tx['balance_before']['ghs'] ?? 0), 2) ?> | CFA <?= number_format((float)($tx['balance_before']['cfa'] ?? 0), 0) ?></span>
                                    <span><strong>After:</strong> GHS <?= number_format((float)($tx['balance_after']['ghs'] ?? 0), 2) ?> | CFA <?= number_format((float)($tx['balance_after']['cfa'] ?? 0), 0) ?></span>
                                </div>
                            </div>
                            <div style="max-height:180px; overflow:auto; margin-top:6px;">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Curr</th>
                                            <th>Denom</th>
                                            <th>Count</th>
                                            <th>Volume</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lines as $ln): ?>
                                            <tr>
                                                <td><?= esc($ln['currency'] ?? 'GHS') ?></td>
                                                <td>
                                                    <?php
                                                        $curr = $ln['currency'] ?? 'GHS';
                                                        $den = (float)($ln['denomination'] ?? 0);
                                                        echo $curr === 'CFA'
                                                            ? number_format($den, 0)
                                                            : number_format($den, 2);
                                                    ?>
                                                </td>
                                                <td><?= (int)($ln['count'] ?? 0) ?></td>
                                                <td><?= number_format((float)($ln['volume'] ?? 0), 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                    <div class="text-center mt-2">
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-primary <?= empty($deno_history_has_more) ? 'd-none' : '' ?>"
                            id="denoHistoryLoadMoreBtn"
                            data-drawer-id="<?= esc($row->id ?? '') ?>"
                            data-page="1"
                            data-limit="20"
                            data-url="<?= ROOT ?>/<?= $admin_route ?>/<?= $plugin_route ?>/ajax_deno_history/<?= esc($row->id ?? '') ?>"
                        >Load more</button>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">🧾</div>
                        <p>No denomination movements recorded for this drawer yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

            <div class="balance-display">
                💰 Balance: GHS <?= number_format($total_volume ?? 0, 2) ?>
                <?php if (isset($combinedBuy) && $combinedBuy !== null): ?>
                    <br><small>Total Available (Buy): <strong>GHS <?= number_format($combinedBuy, 2) ?></strong></small>
                <?php endif; ?>
                <?php if (isset($combinedSell) && $combinedSell !== null): ?>
                    <br><small>Total Available (Sell): <strong>GHS <?= number_format($combinedSell, 2) ?></strong></small>
                <?php endif; ?>
            </div>
        </div>
        <div class="action-buttons">
            <button class="btn-modern btn-draft-pending" onclick="toggleDraftPendingPanel()">
                <i class="fa fa-clock"></i>
                <span id="draft-pending-count">0</span> Pending/Draft
            </button>
            <button class="btn-modern" style="background: rgba(33,150,243,0.9); color:white;" data-bs-toggle="modal" data-bs-target="#denoHistoryModal">
                <i class="fa fa-history"></i> History
            </button>
            <button class="btn-modern btn-exchange d-none" data-bs-toggle="modal" data-bs-target="#exchangeDenoModal">
                <i class="fa fa-exchange-alt"></i> Exchange Denos
            </button>
            <button class="btn-modern btn-export" data-export-drawer="<?= htmlspecialchars($row->id) ?>">
                <i class="fa fa-file-pdf"></i> Export PDF
            </button>
            <?php if(user_can('edit_wallet')): ?>
                <a class="btn-modern" style="background: rgba(255,193,7,0.92); color:#1f2937; text-decoration:none;" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit_cashdrawer/<?=$row->id?>">
                    <i class="fa fa-pen"></i> Edit
                </a>
            <?php endif; ?>
            <?php if(user_can('delete_wallet')): ?>
                <a class="btn-modern" style="background: rgba(220,38,38,0.92); color:#fff; text-decoration:none;" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/delete_cashdrawer/<?=$row->id?>">
                    <i class="fa fa-trash"></i> Delete
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if(!empty($cashdrawer_issue_report['issue_count']) && user_can('edit_wallet')): ?>
        <div class="alert alert-warning mt-3 mb-0" style="border-radius:14px; border:none;">
            <strong><?=number_format((int)$cashdrawer_issue_report['issue_count'])?> inconsistency<?=((int)$cashdrawer_issue_report['issue_count']) === 1 ? '' : 'ies'?> detected.</strong>
            Admins can edit this drawer to repair the opening balances or delete and rebuild it if the ledger is beyond repair.
        </div>
    <?php endif; ?>

    <!-- Denominations Section -->
    <div class="denominations-section">
        <div class="section-title">
            <i class="fa fa-coins"></i> Denominations Breakdown
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Denomination</th>
                        <th>Count</th>
                        <th>Volume</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($denominations)): $id = 1;?>
                        <?php foreach ($denominations as $rRow): $id++; ?>
                            <tr>
                                <td>
                                    <span class="badge bg-warning text-black rounded-circle"><?= $id ?></span>
                                </td>
                                <td>GHS <?= number_format($rRow->denomination, 2) ?></td>
                                <td><?= esc($rRow->count ?? '') ?></td>
                                <td>GHS <?= number_format($rRow->volume ?? 0, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center text-muted">No denominations recorded</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="2" class="text-end">TOTAL</th>
                        <?php
                            $computedDisplayCount = 0;
                            $computedDisplayVolume = 0.0;
                            if (!empty($denominations) && is_array($denominations)) {
                                foreach ($denominations as $denoTotalRow) {
                                    $computedDisplayCount += (int)($denoTotalRow->count ?? 0);
                                    $computedDisplayVolume += (float)($denoTotalRow->volume ?? 0);
                                }
                            }
                            $displayTotalCount = $denomination_total_count ?? $total_count ?? 0;
                            $displayTotalVolume = $denomination_total_volume ?? $total_volume ?? 0;
                            if (((float)$displayTotalCount <= 0 && (float)$displayTotalVolume <= 0) && ($computedDisplayCount > 0 || abs($computedDisplayVolume) > 0.009)) {
                                $displayTotalCount = $computedDisplayCount;
                                $displayTotalVolume = $computedDisplayVolume;
                            }
                        ?>
                        <th><?= number_format((float)$displayTotalCount) ?></th>
                        <th>GHS <?= number_format((float)$displayTotalVolume, 2) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php if (!empty($cfa_denominations)): ?>
            <div class="mt-4">
                <div class="section-title">
                    <i class="fa fa-coins"></i> CFA Denominations Breakdown
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Denomination (CFA)</th>
                                <th>Count</th>
                                <th>Volume (CFA)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $cid = 1; foreach ($cfa_denominations as $cRow): $cid++; ?>
                                <tr>
                                    <td><span class="badge bg-info text-black rounded-circle"><?= $cid ?></span></td>
                                    <td><?= number_format($cRow->denomination ?? 0, 0) ?> CFA</td>
                                    <td><?= esc($cRow->count ?? '') ?></td>
                                    <td><?= number_format($cRow->volume ?? 0, 2) ?> CFA</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="2" class="text-end">TOTAL CFA</th>
                                <?php
                                    $displayCfaTotalCount = $cfa_denomination_total_count ?? $cfa_total_count ?? 0;
                                    $displayCfaTotalVolume = $cfa_denomination_total_volume ?? $cfa_total_volume ?? 0;
                                ?>
                                <th><?= number_format((float)$displayCfaTotalCount) ?></th>
                                <th><?= number_format((float)$displayCfaTotalVolume, 2) ?> CFA</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Channels Grid -->
    <div class="channels-grid">
        <?php 
        if (!empty($cash_channels)):
            foreach ($cash_channels as $channel):
                $isCfaCash = strtoupper($channel->channel_key ?? '') === 'CASH_CFA';
                $shouldShow = $isCfaCash || in_array($channel->trans_status ?? 'Sent', ['Approved', 'Sent']);
                if ($shouldShow):
                    $currencyLabel = $isCfaCash ? 'CFA' : 'GHS';
                    $cashCardClass = $isCfaCash ? 'channel-card--cash-cfa' : 'channel-card--cash-ghs';
                    $cashBadgeClass = $isCfaCash ? 'badge-cash-cfa' : 'badge-cash-ghs';
        ?>
        <div class="channel-card channel-card--cash <?= $cashCardClass ?>">
            <div class="channel-header">
                <div class="channel-name">
                    <i class="fa fa-money-bill-wave me-1" aria-hidden="true"></i>
                    <?= htmlspecialchars($currencyLabel) ?>
                </div>
                <span class="channel-type-badge <?= $cashBadgeClass ?>" title="<?= htmlspecialchars($currencyLabel) ?> cash" aria-label="<?= htmlspecialchars($currencyLabel) ?> cash">
                    <i class="fa fa-money-bill-wave" aria-hidden="true"></i>
                </span>
            </div>
            <div class="channel-stats">
                <div class="stat-item">
                    <div class="stat-label">Opening</div>
                    <div class="stat-value"><?= $currencyLabel ?> <?= number_format($channel->opening_balance ?? 0, 2) ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Credit</div>
                    <div class="stat-value positive">+<?= $currencyLabel ?> <?= number_format($channel->credit ?? 0, 2) ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Debit</div>
                    <div class="stat-value negative">-<?= $currencyLabel ?> <?= number_format($channel->debit ?? 0, 2) ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Closing</div>
                    <div class="stat-value"><?= $currencyLabel ?> <?= number_format($channel->closing_balance ?? 0, 2) ?></div>
                </div>
            </div>
        </div>
        <?php 
                endif;
            endforeach;
        endif;
        ?>
        
        <?php 
        if (!empty($ecash_channels)):
            foreach ($ecash_channels as $channel):
                if (in_array($channel->trans_status ?? 'Sent', ['Approved', 'Sent', 'Pending', 'Draft', 'Opening Snapshot'])):
                    $ob = (float)($channel->opening_balance ?? 0);
                    $cr = (float)($channel->credit ?? 0);
                    $dbt = (float)($channel->debit ?? 0);
                    $cb = (float)($channel->closing_balance ?? 0);
                    $hasAnyValue = (abs($ob) > 0.000001) || (abs($cr) > 0.000001) || (abs($dbt) > 0.000001) || (abs($cb) > 0.000001);
                    if(!$hasAnyValue) continue;
                    $isSharedAccount = !empty($channel->is_shared_account);
                    $channelKey = (string)($channel->channel_key ?? 'ECASH');
                    $channelLabel = preg_replace('/^ECASH[_-]*/i', '', $channelKey);
                    $channelLabel = str_replace(['_', '-'], ' ', $channelLabel);
        ?>
        <div class="channel-card channel-card--ecash <?= $isSharedAccount ? 'channel-card--shared' : '' ?>">
            <div class="channel-header">
                <div class="channel-name">
                    <i class="fa <?= $isSharedAccount ? 'fa-building-columns' : 'fa-mobile-screen-button' ?> me-1" aria-hidden="true"></i>
                    <?= htmlspecialchars($channelLabel) ?>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <?php
                        $proofImage = (string)($channel->proof_image ?? '');
                        $proofSrc = '';
                        if ($proofImage !== '') {
                            $proofSrc = rtrim(ROOT, '/') . '/' . ltrim($proofImage, '/');
                        }
                    ?>
                    <?php if ($proofSrc !== ''): ?>
                        <a href="<?=esc($proofSrc)?>" target="_blank" style="text-decoration:none;">
                            <span class="recon-proof-thumb-view" title="View photo proof">
                                <img src="<?=esc($proofSrc)?>" alt="Proof" loading="lazy" />
                            </span>
                        </a>
                    <?php endif; ?>
                    <span class="channel-type-badge <?= $isSharedAccount ? 'badge-shared-account' : 'badge-ecash' ?>" title="<?= $isSharedAccount ? 'Shared account' : 'E-cash' ?>" aria-label="<?= $isSharedAccount ? 'Shared account' : 'E-cash' ?>">
                        <?php if ($isSharedAccount): ?>
                            <i class="fa fa-building-columns" aria-hidden="true"></i>
                        <?php else: ?>
                            <i class="fa fa-wallet" aria-hidden="true"></i>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            <div class="channel-stats">
                <div class="stat-item">
                    <div class="stat-label">Opening</div>
                    <div class="stat-value">GHS <?= number_format($channel->opening_balance ?? 0, 2) ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Credit</div>
                    <div class="stat-value positive">+GHS <?= number_format($channel->credit ?? 0, 2) ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Debit</div>
                    <div class="stat-value negative">-GHS <?= number_format($channel->debit ?? 0, 2) ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Closing</div>
                    <div class="stat-value">GHS <?= number_format($channel->closing_balance ?? 0, 2) ?></div>
                </div>
            </div>
        </div>
        <?php 
                endif;
            endforeach;
        endif;
        ?>
    </div>
</div>

<!-- Draft/Pending Sidebar Panel -->
<div class="draft-pending-sidebar" id="draftPendingSidebar">
    <div class="sidebar-header">
        <div class="sidebar-title">📋 Pending & Draft</div>
        <button class="close-sidebar" onclick="toggleDraftPendingPanel()">×</button>
    </div>
    <div class="sidebar-content" id="sidebarContent">
        <!-- Populated by JavaScript -->
    </div>
</div>

<!-- Back Button -->
<div class="mt-4 mb-4">
    <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
        <button class="btn btn-outline-primary">
            <i class="fa fa-chevron-left"></i> Back to Cashdrawers
        </button>
    </a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const draftPendingTransactions = <?= json_encode(array_merge(
        array_filter($cash_channels ?? [], function($c) { return in_array($c->trans_status ?? '', ['Draft', 'Pending']); }),
        array_filter($ecash_channels ?? [], function($c) { return in_array($c->trans_status ?? '', ['Draft', 'Pending']); })
    )) ?>;

    const count = draftPendingTransactions.length;
    const countElement = document.getElementById('draft-pending-count');
    if (countElement) {
        countElement.textContent = count;
    }

    const sidebarContent = document.getElementById('sidebarContent');
    if (!sidebarContent) return;
    
    if (count === 0) {
        sidebarContent.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">✓</div>
                <p>No pending or draft transactions</p>
            </div>
        `;
    } else {
        let html = '';
        draftPendingTransactions.forEach(trans => {
            const statusClass = (trans.trans_status === 'Draft') ? 'status-draft' : 'status-pending';
            const statusText = trans.trans_status || 'Unknown';
            const rawDate = trans.meta_date_created || trans.date_created || trans.date_updated || '';
            const dateCreated = rawDate ? new Date(rawDate).toLocaleDateString('en-GB', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'N/A';
            const createdBy = trans.meta_created_by || 'Unknown user';
            const channelLabel = trans.channel_key ? trans.channel_key.replace(/_/g, ' ') : 'Transaction';
            const amount = parseFloat(trans.closing_balance || 0).toFixed(2);
            
            html += `
                <div class="transaction-item" style="border-left: 4px solid ${statusClass === 'status-draft' ? '#9c27b0' : '#ff9800'}; padding: 12px; margin-bottom: 10px; border-radius: 4px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span class="transaction-status ${statusClass}" style="padding: 4px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">${statusText}</span>
                        <span style="font-size: 12px; color: var(--text-muted, #6c757d);">${dateCreated}</span>
                    </div>
                    <div style="margin-bottom: 8px;">
                        <strong style="color: var(--text-primary, #333);">${channelLabel}</strong>
                        <div style="font-size: 12px; color: var(--text-muted, #6c757d); margin-top: 4px;">
                            Type: ${trans.channel_type || 'N/A'}<br>
                            Opening: GHS ${parseFloat(trans.opening_balance || 0).toFixed(2)}<br>
                            Credit: <span style="color: #28a745;">+GHS ${parseFloat(trans.credit || 0).toFixed(2)}</span><br>
                            Debit: <span style="color: #dc3545;">-GHS ${parseFloat(trans.debit || 0).toFixed(2)}</span><br>
                            Created by: ${createdBy}
                        </div>
                    </div>
                    <div style="padding-top: 8px; border-top: 1px solid rgba(0,0,0,0.1);">
                        <strong style="font-size: 14px; color: var(--text-primary, #333);">Closing: GHS ${amount}</strong>
                    </div>
                </div>
            `;
        });
        sidebarContent.innerHTML = html;
    }

    // Denomination movement history
    const denoHistoryData = <?= json_encode(array_values($deno_history ?? [])) ?>;
    const denoHistoryContent = document.getElementById('denoHistoryContent');
    if (denoHistoryContent) {
        if (!denoHistoryData || denoHistoryData.length === 0) {
            denoHistoryContent.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">🧾</div>
                    <p>No denomination movements recorded for this drawer yet.</p>
                </div>
            `;
        } else {
            let html = '';
            denoHistoryData.forEach((tx) => {
                const rawDate = tx.date_created || '';
                const dateLabel = rawDate ? new Date(rawDate).toLocaleString('en-GB', { year:'numeric', month:'short', day:'2-digit', hour:'2-digit', minute:'2-digit' }) : 'N/A';
                const status = tx.trans_status || 'Unknown';
                const displayStatus = status.replace(/^vault:\s*/i, '');
                let totalGhs = 0, totalCfa = 0;
                (tx.lines || []).forEach(line => {
                    if (line.currency === 'CFA') {
                        totalCfa += parseFloat(line.volume || 0);
                    } else {
                        totalGhs += parseFloat(line.volume || 0);
                    }
                });

                html += `
                    <div class="transaction-item">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <span class="transaction-status ${displayStatus === 'Approved' ? 'status-pending' : 'status-draft'}">${displayStatus}</span>
                            <span style="font-size:12px; color:var(--text-secondary);">${dateLabel}</span>
                        </div>
                        <div class="transaction-details">
                            <strong>TX #${tx.trans_id}</strong><br>
                            GHS: <strong>${totalGhs.toFixed(2)}</strong> &nbsp; | &nbsp; CFA: <strong>${totalCfa.toFixed(2)}</strong>
                        </div>
                        <div style="margin-top:8px; padding:8px; border-left:3px solid #0066cc; font-size:11px;">
                            <strong>Cash Balance</strong><br>
                            <div style="margin-top:4px; display:flex; justify-content:space-between; gap:15px;">
                                <span><strong>Before:</strong> GHS ${Number(tx.balance_before?.ghs || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} | CFA ${Number(tx.balance_before?.cfa || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}</span>
                                <span><strong>After:</strong> GHS ${Number(tx.balance_after?.ghs || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} | CFA ${Number(tx.balance_after?.cfa || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}</span>
                            </div>
                        </div>
                        <div style="max-height:150px; overflow:auto; margin-top:6px;">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Curr</th>
                                        <th>Denom</th>
                                        <th>Count</th>
                                        <th>Volume</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${(tx.lines || []).map(line => `
                                        <tr>
                                            <td>${line.currency}</td>
                                            <td>${Number(line.denomination || 0).toLocaleString('en-US', { minimumFractionDigits: line.currency === 'CFA' ? 0 : 2, maximumFractionDigits: 2 })}</td>
                                            <td>${line.count}</td>
                                            <td>${Number(line.volume || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
            });
            denoHistoryContent.innerHTML = html;
        }
    }

    const denoLoadMoreBtn = document.getElementById('denoHistoryLoadMoreBtn');
    const denoMovementList = document.getElementById('denoHistoryMovementList');
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[ch]));
    const money = (value, currency = 'GHS') => {
        const number = Number(value || 0);
        return `${currency} ${number.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    };
    const tableLabel = (table) => String(table || '')
        .replace(/^drawer_denos$/i, 'Drawer Denominations')
        .replace(/^drawer_fx_denos$/i, 'FX Denominations')
        .replace(/^momoremits$/i, 'MoMo Remit Transaction')
        .replace(/^agencybankings$/i, 'Agency Banking Transaction')
        .replace(/^airtimebundles$/i, 'Airtime/Bundle Transaction')
        .replace(/^cfas$/i, 'CFA Transaction')
        .replace(/^ezwichs$/i, 'Ezwich Transaction')
        .replace(/^payment_services$/i, 'Payment Service Transaction')
        .replace(/^cashpickups$/i, 'Cash Pickup')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, char => char.toUpperCase());
    const renderDenoTransactionDetail = (tx) => {
        let html = '';
        const sourceTables = Array.isArray(tx.source_tables) ? tx.source_tables : [];
        if (sourceTables.length) {
            html += `<div class="small text-muted mt-1">Deno source: ${escapeHtml([...new Set(sourceTables.map(tableLabel))].join(', '))}</div>`;
        }
        const detail = tx.transaction_detail || null;
        if (!detail) return html;
        const bits = [];
        if (detail.type) bits.push(`Type: ${detail.type}`);
        if (detail.network) bits.push(`Network: ${detail.network}`);
        if (detail.phone) bits.push(`Phone: ${detail.phone}`);
        if (detail.reference) bits.push(`Ref: ${detail.reference}`);
        if (detail.amount !== null && detail.amount !== undefined) bits.push(`Amount: ${money(detail.amount)}`);
        if (detail.status) bits.push(`Status: ${detail.status}`);
        let idLabel = detail.id ? ` #${detail.id}` : '';
        if (detail.lookup_id && String(detail.lookup_id) !== String(detail.id || '')) {
            idLabel += ` (TX ${detail.lookup_id})`;
        }
        html += `
            <div class="mt-2 p-2" style="border:1px solid var(--bs-border-color); border-radius:8px;">
                <div class="fw-semibold small">${escapeHtml(detail.label || 'Transaction Detail')}${escapeHtml(idLabel)}</div>
                ${bits.length ? `<div class="small mt-1">${escapeHtml(bits.join(' | '))}</div>` : ''}
                ${Array.isArray(detail.summary) && detail.summary.length ? `<div class="small text-muted mt-1">${escapeHtml(detail.summary.slice(0, 3).join(' | '))}</div>` : ''}
            </div>
        `;
        return html;
    };
    const renderDenoHistoryCard = (tx, index) => {
        const status = tx.trans_status || 'Unknown';
        const displayStatus = status.replace(/^vault:\s*/i, '');
        const lines = Array.isArray(tx.lines) ? tx.lines : [];
        const meta = tx.meta || {};
        const netGhs = Number(meta.net_ghs || 0);
        const netCfa = Number(meta.net_cfa || 0);
        const balanceBefore = tx.balance_before || {};
        const balanceAfter = tx.balance_after || {};
        
        let typeLabel = 'Movement';
        if (status.toLowerCase().startsWith('vault:')) {
            typeLabel = 'Vault Transaction';
        } else if (displayStatus.toLowerCase() === 'exchange') {
            typeLabel = 'Exchange';
        } else if (meta.has_ghs && meta.has_cfa) {
            typeLabel = 'FX Exchange';
        } else {
            const net = Math.abs(netGhs) > 0.0001 ? netGhs : netCfa;
            if (net > 0) typeLabel = 'Cash In';
            if (net < 0) typeLabel = 'Cash Out';
        }
        let totalGhs = 0;
        let totalCfa = 0;
        lines.forEach(line => {
            if (String(line.currency || 'GHS').toUpperCase() === 'CFA') {
                totalCfa += Number(line.volume || 0);
            } else {
                totalGhs += Number(line.volume || 0);
            }
        });
        const badgeClass = displayStatus.toLowerCase() === 'exchange'
            ? 'status-exchange'
            : (displayStatus === 'Approved' ? 'status-pending' : 'status-draft');
        
        // Format balance numbers
        const balBeforeGhs = Number(balanceBefore.ghs || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const balAfterGhs = Number(balanceAfter.ghs || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const balBeforeCfa = Number(balanceBefore.cfa || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        const balAfterCfa = Number(balanceAfter.cfa || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        
        return `
            <div class="transaction-item mb-3" data-history-index="loaded-${index}">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <span class="transaction-status ${badgeClass}">${escapeHtml(displayStatus)}</span>
                    <span style="font-size:12px; color:var(--text-secondary);">
                        ${escapeHtml(tx.date_created || '')}
                        <br><strong>${escapeHtml(typeLabel)}</strong>
                    </span>
                </div>
                <div class="transaction-details">
                    <strong>TX #${tx.trans_id ? escapeHtml(tx.trans_id) : 'N/A'}</strong><br>
                    GHS: <strong>${totalGhs.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong>
                    &nbsp; | &nbsp;
                    CFA: <strong>${totalCfa.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong>
                    ${renderDenoTransactionDetail(tx)}
                </div>
                
                <!-- Balance Section -->
                <div class="cash-balance text-muted" style="margin-top:8px; padding:8px; border-left:3px solid #0066cc; font-size:11px;">
                    <strong>💰 Cash Balance</strong><br>
                    <div style="margin-top:4px; display:flex; justify-content:space-between; gap:15px;">
                        <span><strong>Before:</strong> GHS ${balBeforeGhs} | CFA ${balBeforeCfa}</span>
                        <span><strong>After:</strong> GHS ${balAfterGhs} | CFA ${balAfterCfa}</span>
                    </div>
                </div>
                
                <div style="max-height:180px; overflow:auto; margin-top:6px;">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Curr</th><th>Denom</th><th>Count</th><th>Volume</th></tr></thead>
                        <tbody>
                            ${lines.map(line => {
                                const curr = String(line.currency || 'GHS').toUpperCase();
                                const denomination = Number(line.denomination || 0).toLocaleString('en-US', {
                                    minimumFractionDigits: curr === 'CFA' ? 0 : 2,
                                    maximumFractionDigits: 2
                                });
                                const volume = Number(line.volume || 0).toLocaleString('en-US', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                                return `<tr><td>${escapeHtml(curr)}</td><td>${denomination}</td><td>${escapeHtml(line.count || 0)}</td><td>${volume}</td></tr>`;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    };
    if (denoLoadMoreBtn && denoMovementList) {
        denoLoadMoreBtn.addEventListener('click', async function() {
            const nextPage = Number(denoLoadMoreBtn.dataset.page || '1') + 1;
            const limit = Number(denoLoadMoreBtn.dataset.limit || '20');
            const url = new URL(denoLoadMoreBtn.dataset.url || '', window.location.origin);
            url.searchParams.set('page', String(nextPage));
            url.searchParams.set('limit', String(limit));
            denoLoadMoreBtn.disabled = true;
            const originalLabel = denoLoadMoreBtn.textContent;
            denoLoadMoreBtn.textContent = 'Loading...';
            try {
                const response = await fetch(url.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    cache: 'no-store'
                });
                
                console.log('Deno history fetch response:', { 
                    ok: response.ok, 
                    status: response.status, 
                    contentType: response.headers.get('content-type'),
                    contentEncoding: response.headers.get('content-encoding')
                });
                
                let payload = { success: false, message: 'Unable to load denomination history' };
                
                // Always try to parse as JSON since content-type says application/json
                try {
                    payload = await response.json();
                } catch (parseErr) {
                    console.error('JSON parse error:', parseErr);
                    // If JSON parse fails, try text fallback
                    try {
                        const text = await response.text();
                        console.log('Response text:', text.substring(0, 500));
                        if (text.trim().startsWith('{')) {
                            payload = JSON.parse(text);
                        } else {
                            payload = { success: false, message: 'Server response is not valid JSON' };
                        }
                    } catch (textErr) {
                        console.error('Text parse error:', textErr);
                        payload = { success: false, message: 'Invalid server response format' };
                    }
                }
                
                console.log('Deno history payload:', payload);
                
                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || 'Failed to load denomination history');
                }
                
                const items = Array.isArray(payload.items) ? payload.items : [];
                if (items.length) {
                    denoMovementList.insertAdjacentHTML('beforeend', items.map((item, idx) => renderDenoHistoryCard(item, `${nextPage}-${idx}`)).join(''));
                }
                denoLoadMoreBtn.dataset.page = String(nextPage);
                if (!payload.has_more || items.length === 0) {
                    denoLoadMoreBtn.classList.add('d-none');
                }
            } catch (err) {
                console.error('Deno history error:', err);
                denoLoadMoreBtn.classList.add('d-none');
                if (denoMovementList) {
                    const fallback = denoMovementList.querySelector('[data-fallback-history]');
                    if (!fallback) {
                        denoMovementList.insertAdjacentHTML('beforeend', `
                            <div class="empty-state" data-fallback-history>
                                <div class="empty-state-icon">🧾</div>
                                <p>History is temporarily unavailable.</p>
                            </div>
                        `);
                    }
                }
            } finally {
                denoLoadMoreBtn.disabled = false;
                denoLoadMoreBtn.textContent = originalLabel;
            }
        });
    }
    
    // PDF Export Handler
    const exportDenoHistoryPdfBtn = document.getElementById('exportDenoHistoryPdfBtn');
    if (exportDenoHistoryPdfBtn) {
        exportDenoHistoryPdfBtn.addEventListener('click', async function() {
            const drawerId = document.querySelector('[data-cashdrawer-id]')?.dataset.cashdrawerId;
            if (!drawerId) {
                alert('Error: Drawer ID not found');
                return;
            }
            
            const btnText = exportDenoHistoryPdfBtn.textContent;
            exportDenoHistoryPdfBtn.disabled = true;
            exportDenoHistoryPdfBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating...';
            
            try {
                const response = await fetch(`${window.location.origin}/admin/operations_accounts/ajax_export_deno_history_pdf/${drawerId}`, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.message || 'Failed to export PDF');
                }
                
                // Get the HTML content from the response
                const htmlContent = await response.text();
                
                // Open in new window for PDF printing
                const printWindow = window.open('', '_blank');
                if (printWindow) {
                    printWindow.document.write(htmlContent);
                    printWindow.document.close();
                    
                    // Auto-trigger print dialog after a short delay
                    setTimeout(() => {
                        printWindow.print();
                    }, 500);
                } else {
                    alert('Please enable pop-ups to export PDF');
                }
            } catch (error) {
                console.error('Export error:', error);
                alert('Error exporting PDF: ' + error.message);
            } finally {
                exportDenoHistoryPdfBtn.disabled = false;
                exportDenoHistoryPdfBtn.innerHTML = btnText;
            }
        });
    }
});

document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('draftPendingSidebar');
    if (sidebar && !sidebar.contains(event.target) && !event.target.closest('.btn-draft-pending')) {
        sidebar.classList.remove('open');
    }
    const denoSidebar = document.getElementById('denoHistorySidebar');
    if (denoSidebar && !denoSidebar.contains(event.target) && !event.target.closest('.btn-modern')) {
        denoSidebar.classList.remove('open');
    }
});
</script>

    <?php else:?>
        <div class="alert alert-danger text-center">
            That record was not found!
        </div>
        
        <?php if (user_can('add_wallet')): ?>
            <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
                <button class="btn btn-md btn-outline-primary" style="border:none;">
                    <i class="fa-solid fa-chevron-left"></i> Back
                </button>
            </a>
        <?php else:?>
            <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/assigned">
                <button class="btn btn-md btn-outline-primary" style="border:none;">
                    <i class="fa-solid fa-chevron-left"></i> Back
                </button>
            </a>
        <?php endif ?>
    
    <?php endif?>

<?php else:?>
    <div class="alert alert-danger text-center">
        Access denied. You don't have permission for this action.
    </div>
<?php endif?>

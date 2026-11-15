<?php if(user_can('view_wallet_details')): ?>
<style>
    .cashdrawer-container {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        padding: 24px;
        color: white;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        margin-bottom: 24px;
    }

    .cashdrawer-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .cashdrawer-title {
        font-size: 28px;
        font-weight: 700;
        letter-spacing: 0.5px;
    }

    .balance-display {
        background: rgba(255,255,255,0.15);
        padding: 12px 20px;
        border-radius: 8px;
        backdrop-filter: blur(10px);
        font-size: 18px;
        font-weight: 600;
    }

    .action-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .btn-modern {
        padding: 10px 18px;
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

    .btn-exchange {
        background: rgba(76,175,80,0.9);
        color: white;
    }

    .btn-exchange:hover {
        background: #4caf50;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(76,175,80,0.3);
    }

    .channels-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .channel-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
    }

    .channel-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    }

    .channel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        border-bottom: 2px solid #f5f5f5;
        padding-bottom: 12px;
    }

    .channel-name {
        font-size: 16px;
        font-weight: 700;
        color: #333;
    }

    .channel-type-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    .badge-cash {
        background: #e8f5e9;
        color: #2e7d32;
    }

    .badge-ecash {
        background: #e3f2fd;
        color: #1565c0;
    }

    .channel-stats {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .stat-item {
        background: #f9f9f9;
        padding: 12px;
        border-radius: 8px;
        text-align: center;
    }

    .stat-label {
        font-size: 12px;
        color: #999;
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 5px;
    }

    .stat-value {
        font-size: 18px;
        font-weight: 700;
        color: #333;
    }

    .stat-value.positive {
        color: #4caf50;
    }

    .stat-value.negative {
        color: #f44336;
    }

    .draft-pending-sidebar {
        position: fixed;
        right: -400px;
        top: 0;
        width: 400px;
        height: 100vh;
        background: white;
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
        background: #f9f9f9;
        border-left: 4px solid #ffc107;
        padding: 15px;
        margin-bottom: 12px;
        border-radius: 6px;
        transition: all 0.3s ease;
    }

    .transaction-item:hover {
        background: #f0f0f0;
        transform: translateX(5px);
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

    .transaction-details {
        font-size: 13px;
        color: #666;
        margin-bottom: 8px;
    }

    .transaction-amount {
        font-size: 16px;
        font-weight: 700;
        color: #333;
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

    .denominations-section {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 24px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    }

    .section-title {
        font-size: 18px;
        font-weight: 700;
        color: #333;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    @media (max-width: 768px) {
        .draft-pending-sidebar {
            width: 100%;
            right: -100%;
        }

        .channels-grid {
            grid-template-columns: 1fr;
        }

        .cashdrawer-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .action-buttons {
            width: 100%;
            flex-direction: column;
        }

        .btn-modern {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<?php if(!empty($row)): ?>

<div class="cashdrawer-container">
    <div class="cashdrawer-header">
        <div>
            <div class="cashdrawer-title">
                <?= htmlspecialchars(strtoupper($row->user_first_name . "'s Cash Drawer")) ?>
            </div>
            <div class="balance-display">
                💰 Balance: GHS <?= number_format($headerBalance ?? 0, 2) ?>
            </div>
        </div>
        <div class="action-buttons">
            <button class="btn-modern btn-draft-pending" onclick="toggleDraftPendingPanel()">
                <i class="fa fa-clock"></i>
                <span id="draft-pending-count">0</span> Pending/Draft
            </button>
            <button class="btn-modern btn-exchange" data-bs-toggle="modal" data-bs-target="#exchangeDenoModal">
                <i class="fa fa-exchange-alt"></i> Exchange Denos
            </button>
            <button class="btn-modern btn-export" onclick="exportCashdrawerPDF(<?= htmlspecialchars($row->id) ?>)">
                <i class="fa fa-file-pdf"></i> Export PDF
            </button>
        </div>
    </div>

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
                        <th><?= number_format($total_count ?? 0) ?></th>
                        <th>GHS <?= number_format($total_volume ?? 0, 2) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Channels Grid -->
    <div class="channels-grid">
        <?php 
        if (!empty($cash_channels)):
            foreach ($cash_channels as $channel):
                if (in_array($channel->trans_status ?? 'Sent', ['Approved', 'Sent'])):
                    $isCfaCash = strtoupper(trim((string)($channel->channel_key ?? ''))) === 'CASH_CFA';
                    $currencyLabel = $isCfaCash ? 'CFA' : 'GHS';
                    $cashLabel = $currencyLabel . ' CASH';
        ?>
        <div class="channel-card">
            <div class="channel-header">
                <div class="channel-name">
                    <?= htmlspecialchars($cashLabel) ?>
                </div>
                <span class="channel-type-badge badge-cash"><?= htmlspecialchars($cashLabel) ?></span>
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
                if (in_array($channel->trans_status ?? 'Sent', ['Approved', 'Sent'])):
        ?>
        <div class="channel-card">
            <div class="channel-header">
                <div class="channel-name">
                    <?= htmlspecialchars($channel->channel_key ?? 'ECASH') ?>
                </div>
                <span class="channel-type-badge badge-ecash">E-CASH</span>
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

<!-- Include Exchange Denominations Modal -->
<?php require plugin_path('views/admin/cashdrawers/exchange_denominations.php'); ?>

<script>
function toggleDraftPendingPanel() {
    const sidebar = document.getElementById('draftPendingSidebar');
    sidebar.classList.toggle('open');
}

document.addEventListener('DOMContentLoaded', function() {
    const draftPendingTransactions = <?= json_encode(array_merge(
        array_filter($cash_channels ?? [], function($c) { return in_array($c->trans_status ?? '', ['Draft', 'Pending']); }),
        array_filter($ecash_channels ?? [], function($c) { return in_array($c->trans_status ?? '', ['Draft', 'Pending']); })
    )) ?>;

    const count = draftPendingTransactions.length;
    document.getElementById('draft-pending-count').textContent = count;

    const sidebarContent = document.getElementById('sidebarContent');
    
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
            html += `
                <div class="transaction-item">
                    <span class="transaction-status ${statusClass}">${statusText}</span>
                    <div class="transaction-details">
                        <strong>${trans.channel_key || 'Transaction'}</strong><br>
                        Type: ${trans.channel_type || 'N/A'}
                    </div>
                    <div class="transaction-amount">
                        GHS ${parseFloat(trans.closing_balance || 0).toFixed(2)}
                    </div>
                </div>
            `;
        });
        sidebarContent.innerHTML = html;
    }
});

document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('draftPendingSidebar');
    if (!sidebar.contains(event.target) && !event.target.closest('.btn-draft-pending')) {
        sidebar.classList.remove('open');
    }
});
</script>

<?php else: ?>
<div class="alert alert-warning">
    <i class="fa fa-exclamation-triangle"></i> No cashdrawer data available
</div>
<?php endif; ?>

<?php endif; ?>

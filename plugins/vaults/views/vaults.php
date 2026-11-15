<?php

/**
 * @var bool $isOperationsView
 * @var bool $isEmbeddedView
 * @var string $admin_route
 * @var string $plugin_route
 * @var object|null $selectedVault
 * @var array $allVaultBalances
 * @var array $vaults
 * @var int $selectedVaultReconciliationAttentionCount
 * @var int $selectedVaultAttentionCount
 * @var array $balances
 * @var float $currentCfaRate
 * @var array $stock
 * @var array $transferVaultStock
 * @var array $transferVaults
 * @var array $eligibleTransactionUsers
 * @var array $reconciliationStock
 * @var array $users
 * @var array $transactions
 * @var array $statementMovements
 * @var array $vaultAnalytics
 * @var array $reconciliations
 * @var array $allBranches
 * @var int $userId
 * @var string $csrfField
 * @var string $baseUrl
 * @var string $vaultPageUrl
 * @var callable $formatVaultDate
 * @var callable $vaultStatusClass
 * @var callable $vaultImageUrl
 */
$vaultAnalytics = \vaults_build_analytics(
    $statementMovements,
    is_string($_GET['chart_from'] ?? null) ? $_GET['chart_from'] : null,
    is_string($_GET['chart_to'] ?? null) ? $_GET['chart_to'] : null
);
$pageTitle = $isOperationsView ? 'Operations · Vaults' : 'Backoffice · Vaults';
$activeVaultId = (int)($selectedVault->id ?? 0);
$latestReconciliationRejectedByOther = !empty($latestReconciliation)
    && strtolower((string)$latestReconciliation->status) === 'rejected'
    && (int)$latestReconciliation->created_by !== $userId;
$canReconcileSelectedVault = $selectedVault !== null
    && \Vaults\canReconcileVault($selectedVault, $userId);
$canSubmitReconciliation = $canReconcileSelectedVault
    && empty($pendingReconciliation)
    && !$latestReconciliationRejectedByOther;
$csrfField = csrf() . ($isEmbeddedView ? '<input type="hidden" name="vault_return_to" value="backoffice">' : '');
$oldPost = (new \Core\Session)->pop('vaults_create_old_post');
$oldPost = is_array($oldPost) ? $oldPost : \get_value('old_post');
$oldPost = is_array($oldPost) ? $oldPost : [];
$openingCounts = ['GHS' => [], 'CFA' => []];
foreach (['GHS' => 'ghs_opening_json', 'CFA' => 'cfa_opening_json'] as $currency => $field) {
    $openingLines = json_decode((string)($oldPost[$field] ?? '[]'), true);
    if (!is_array($openingLines)) {
        continue;
    }
    foreach ($openingLines as $line) {
        if (!is_array($line) || !isset($line['denomination'], $line['count'])) {
            continue;
        }
        $openingCounts[$currency][(string)(float)$line['denomination']] = max(0, (int)$line['count']);
    }
}
$operationsUrl = ROOT . '/' . $admin_route . '/' . $plugin_route . '/operations';
$baseUrl = ROOT . '/' . $admin_route . '/' . $plugin_route;
$vaultPageUrl = $isEmbeddedView ? ROOT . '/admin/back_office/list' : ($isOperationsView ? $operationsUrl : $baseUrl);
$formatVaultDate = static function ($date): string {
    $timestamp = strtotime((string)$date);
    return $timestamp === false ? '—' : date('jS F, Y H:i', $timestamp);
};
$vaultStatusClass = static function (string $status): string {
    $status = strtolower(trim($status));
    if (in_array($status, ['approved', 'completed', 'confirmed'], true)) {
        return 'text-bg-success';
    }
    if (in_array($status, ['rejected', 'declined', 'failed'], true)) {
        return 'text-bg-danger';
    }
    if (in_array($status, ['pending approval', 'pending first party', 'pending'], true)) {
        return 'text-bg-warning';
    }
    return 'text-bg-secondary';
};
$vaultGridControls = static function (string $grid, bool $withStatus = false, bool $withCurrency = false, bool $withDirection = false): string {
    $html = '<div class="vault-grid-controls row g-2 align-items-end p-3 border-bottom" data-vault-grid-controls="' . esc($grid) . '">';
    $html .= '<div class="col-12 col-md"><label class="form-label small mb-1">Search</label><input class="form-control form-control-sm" type="search" placeholder="Search this list" aria-label="Search ' . esc($grid) . '" data-grid-filter="search"></div>';
    if ($withStatus) {
        $html .= '<div class="col-6 col-md-2"><label class="form-label small mb-1">Status</label><select class="form-select form-select-sm" aria-label="Filter ' . esc($grid) . ' by status" data-grid-filter="status"><option value="">All statuses</option></select></div>';
    }
    if ($withCurrency) {
        $html .= '<div class="col-6 col-md-2"><label class="form-label small mb-1">Currency</label><select class="form-select form-select-sm" aria-label="Filter ' . esc($grid) . ' by currency" data-grid-filter="currency"><option value="">All currencies</option><option>GHS</option><option>CFA</option></select></div>';
    }
    if ($withDirection) {
        $html .= '<div class="col-6 col-md-2"><label class="form-label small mb-1">Direction</label><select class="form-select form-select-sm" aria-label="Filter ' . esc($grid) . ' by direction" data-grid-filter="direction"><option value="">All directions</option><option value="in">In</option><option value="out">Out</option></select></div>';
    }
    $html .= '<div class="col-6 col-md-2"><label class="form-label small mb-1">From</label><input class="form-control form-control-sm" type="date" aria-label="Filter ' . esc($grid) . ' from date" data-grid-filter="date-from"></div>';
    $html .= '<div class="col-6 col-md-2"><label class="form-label small mb-1">To</label><input class="form-control form-control-sm" type="date" aria-label="Filter ' . esc($grid) . ' to date" data-grid-filter="date-to"></div>';
    $html .= '</div>';
    return $html;
};
$vaultImageUrl = static function (string $path): string {
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if (filter_var($path, FILTER_VALIDATE_URL)) {
        return $path;
    }
    return ROOT . '/' . ltrim(str_replace('\\', '/', $path), '/');
};
$renderEditDenominationTable = static function (array $lines, string $set, string $currency): void {
    $denominations = $currency === 'GHS'
        ? [200, 100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1]
        : [10000, 5000, 2000, 1000, 500, 200, 100, 50, 25, 10, 5];
    $counts = [];
    foreach ($lines as $line) {
        if (!is_array($line)) {
            continue;
        }
        $counts[number_format((float)($line['denomination'] ?? 0), 2, '.', '')] =
            max(0, (int)($line['count'] ?? 0));
    }
?>
<div class="table-responsive">
    <table class="table table-sm table-hover align-middle vault-edit-denomination-table mb-2">
        <thead>
            <tr>
                <th>Denomination</th>
                <th>Type</th>
                <th class="text-end">Count</th>
                <th class="text-end">Value</th>
            </tr>
        </thead>
        <tbody data-edit-denomination-rows="<?= esc($set) ?>">
            <?php foreach ($denominations as $denomination):
                    $key = number_format((float)$denomination, 2, '.', '');
                    $count = $counts[$key] ?? 0;
                    $isCoin = $currency === 'GHS' && (float)$denomination < 1;
                    $value = (float)$denomination * $count;
                ?>
            <tr data-edit-denomination-row data-denomination="<?= esc((string)$denomination) ?>">
                <td class="fw-semibold"><?= number_format((float)$denomination, $currency === 'CFA' ? 0 : 2) ?>
                    <?= esc($currency) ?></td>
                <td><span
                        class="badge <?= $isCoin ? 'text-bg-info' : 'text-bg-secondary' ?>"><?= $isCoin ? 'Coin' : 'Note' ?></span>
                </td>
                <td><input class="form-control form-control-sm ms-auto" type="number" min="0" step="1"
                        value="<?= $count ?>" data-edit-count
                        aria-label="<?= esc(number_format((float)$denomination, $currency === 'CFA' ? 0 : 2) . ' ' . $currency) ?> count">
                </td>
                <td class="text-end text-nowrap" data-edit-line-total><?= number_format($value, 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="fw-semibold">
                <td colspan="3" class="text-end">Total</td>
                <td class="text-end" data-edit-set-total>0.00</td>
            </tr>
        </tfoot>
    </table>
</div>
<?php
};
?>
<style>
.vault-metric-card {
    border: 1px solid var(--bs-border-color);
    border-radius: .85rem;
    overflow: hidden;
    transition: transform .18s ease, box-shadow .18s ease;
}

.vault-metric-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 .6rem 1.4rem rgba(0, 0, 0, .12);
}

.vault-metric-icon {
    display: grid;
    width: 2.5rem;
    height: 2.5rem;
    place-items: center;
    border-radius: .75rem;
    font-size: 1rem;
}

.vault-metric-icon--vault {
    background: rgba(13, 110, 253, .12);
    color: #0d6efd;
}

.vault-metric-icon--ghs {
    background: rgba(25, 135, 84, .12);
    color: #198754;
}

.vault-metric-icon--cfa {
    background: rgba(111, 66, 193, .12);
    color: #6f42c1;
}

.vault-metric-icon--total {
    background: rgba(13, 202, 240, .14);
    color: #087990;
}

.vault-metric-card--vault {
    border-top: 3px solid #0d6efd;
}

.vault-metric-card--ghs,
.vault-overview-metric--ghs {
    border-top: 3px solid #198754;
}

.vault-metric-card--cfa,
.vault-overview-metric--cfa {
    border-top: 3px solid #6f42c1;
}

.vault-metric-card--total {
    border-top: 3px solid #0dcaf0;
}

.vault-overview-metric--custodian {
    border-top: 3px solid #0d6efd;
}

.vault-overview-icon {
    display: inline-grid;
    width: 2.25rem;
    height: 2.25rem;
    place-items: center;
    margin-bottom: .65rem;
    border-radius: .7rem;
    background: var(--vault-icon-bg);
    color: var(--vault-icon-color);
}

.vault-overview-metric--ghs {
    --vault-icon-bg: rgba(25, 135, 84, .12);
    --vault-icon-color: #198754;
}

.vault-overview-metric--cfa {
    --vault-icon-bg: rgba(111, 66, 193, .12);
    --vault-icon-color: #6f42c1;
}

.vault-overview-metric--custodian {
    --vault-icon-bg: rgba(13, 110, 253, .12);
    --vault-icon-color: #0d6efd;
}

.vault-overview-metric {
    border-radius: .85rem;
}

.vault-analytics-card {
    border-radius: .85rem;
    overflow: hidden;
}

.vault-analytics-card--inflow {
    border-top: 3px solid #198754;
}

.vault-analytics-card--outflow {
    border-top: 3px solid #dc3545;
}

.vault-analytics-card--net {
    border-top: 3px solid #0d6efd;
}

.vault-analytics-card--activity {
    border-top: 3px solid #6f42c1;
}

.vault-analytics-value-icon {
    display: inline-grid;
    width: 2rem;
    height: 2rem;
    place-items: center;
    margin-bottom: .5rem;
    border-radius: .65rem;
    background: var(--vault-icon-bg);
    color: var(--vault-icon-color);
}

.vault-analytics-card--inflow {
    --vault-icon-bg: rgba(25, 135, 84, .12);
    --vault-icon-color: #198754;
}

.vault-analytics-card--outflow {
    --vault-icon-bg: rgba(220, 53, 69, .12);
    --vault-icon-color: #dc3545;
}

.vault-analytics-card--net {
    --vault-icon-bg: rgba(13, 110, 253, .12);
    --vault-icon-color: #0d6efd;
}

.vault-analytics-card--activity {
    --vault-icon-bg: rgba(111, 66, 193, .12);
    --vault-icon-color: #6f42c1;
}

.vault-analytics-chart {
    position: relative;
    min-width: 620px;
    padding: .75rem .75rem .25rem;
}

.vault-analytics-chart-grid {
    position: relative;
    z-index: 1;
    display: grid;
    gap: .25rem;
}

.vault-analytics-chart-zero {
    position: absolute;
    top: 56px;
    right: .75rem;
    left: .75rem;
    border-top: 1px dashed var(--bs-border-color);
}

.vault-analytics-day {
    min-width: 0;
    text-align: center;
}

.vault-analytics-plot {
    position: relative;
    height: 112px;
}

.vault-analytics-bar {
    position: absolute;
    right: 22%;
    left: 22%;
    min-height: 2px;
    border-radius: 3px;
}

.vault-analytics-bar--positive {
    bottom: 50%;
    background: linear-gradient(0deg, #198754, #48b983);
}

.vault-analytics-bar--negative {
    top: 50%;
    background: linear-gradient(180deg, #dc3545, #f07b87);
}

.vault-analytics-date {
    display: block;
    overflow: hidden;
    color: var(--bs-secondary-color);
    font-size: .68rem;
    text-overflow: clip;
    white-space: nowrap;
}

.vault-tab-icon {
    margin-right: .4rem;
    opacity: .82;
}

.vault-opening-summary {
    border: 1px solid transparent !important;
    border-radius: .8rem !important;
    box-shadow: inset 3px 0 currentColor;
}

.vault-opening-summary--ghs {
    color: #198754;
    background: rgba(25, 135, 84, .09);
    border-color: rgba(25, 135, 84, .2) !important;
}

.vault-opening-summary--cfa {
    color: #6f42c1;
    background: rgba(111, 66, 193, .09);
    border-color: rgba(111, 66, 193, .2) !important;
}

.vault-opening-summary--total {
    color: #087990;
    background: rgba(13, 202, 240, .1);
    border-color: rgba(13, 202, 240, .22) !important;
}

.vault-opening-summary .small,
.vault-opening-summary .fw-semibold {
    color: inherit !important;
}

.vault-list-filters {
    display: flex;
    width: 100%;
    flex-wrap: nowrap;
    align-items: center;
    gap: .5rem;
}

.vault-list-filters [data-vault-search] {
    width: auto;
    flex: 1 1 18rem;
}

.vault-list-filters select {
    width: auto;
    min-width: 9rem;
}

.vault-attention-row>td {
    background: var(--bs-warning-bg-subtle) !important;
}

.vault-attention-row>td:first-child {
    box-shadow: inset 4px 0 var(--bs-warning);
}

.vault-attention-pill {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
    margin-left: .4rem;
    padding: .18rem .48rem;
    border-radius: 999px;
    background: var(--bs-danger);
    color: #fff;
    font-size: .68rem;
    font-weight: 700;
    white-space: nowrap;
}

.vault-tab-attention-pill {
    display: inline-flex;
    min-width: 1.25rem;
    height: 1.25rem;
    align-items: center;
    justify-content: center;
    margin-left: .35rem;
    padding: 0 .35rem;
    border-radius: 999px;
    background: var(--bs-danger);
    color: #fff;
    font-size: .7rem;
    font-weight: 700;
    vertical-align: middle;
}

.vault-status-badge {
    display: inline-flex;
    padding: .3rem .55rem;
    border-radius: 999px;
    font-size: .72rem;
    font-weight: 700;
    white-space: nowrap;
}

.vault-actions {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
}

.vault-action-modal {
    position: fixed;
    inset: 0;
    z-index: 1080;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    overflow-y: auto;
    background: rgba(15, 23, 42, .62);
    backdrop-filter: blur(4px);
}

.vault-action-modal.is-open {
    display: flex;
}

.vault-action-modal>.row {
    width: min(820px, 100%);
    max-height: calc(100vh - 2rem);
    margin: 0;
    overflow-y: auto;
}

.vault-action-modal .card {
    border: 0;
    border-radius: 1rem;
    box-shadow: 0 1.5rem 4rem rgba(0, 0, 0, .28);
}

.vault-action-modal .card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--bs-border-color);
}

.vault-action-modal[data-vault-modal="request"] .card-header {
    border-top: 4px solid var(--bs-primary);
}

.vault-action-modal[data-vault-modal="reconciliation"] .card-header {
    border-top: 4px solid var(--bs-warning);
}

.vault-action-modal[data-vault-modal="adjustment"] .card-header {
    border-top: 4px solid var(--bs-info);
}

.vault-action-modal[data-vault-modal="adjustment"]> .card {
    width: min(560px, 100%);
    max-height: calc(100vh - 2rem);
    overflow-y: auto;
}

.vault-action-modal[data-vault-modal="adjustment"] .card-body {
    padding: 1.5rem;
}

.vault-limit-form {
    display: grid;
    gap: .85rem;
}

.vault-limit-form .form-control {
    width: 100%;
}

.vault-action-modal .vault-action-close {
    width: 2rem;
    height: 2rem;
    display: grid;
    place-items: center;
    border: 0;
    border-radius: 50%;
    background: var(--bs-secondary-bg);
    color: var(--bs-body-color);
}

.vault-edit-modal {
    position: fixed;
    inset: 0;
    z-index: 1090;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    overflow-y: auto;
    background: rgba(15, 23, 42, .72);
    backdrop-filter: blur(6px);
}

.vault-edit-modal.is-open {
    display: flex;
}

.vault-edit-modal .modal-dialog {
    width: 780px;
    max-width: calc(100vw - 2rem);
    height: min(88vh, 900px);
    max-height: calc(100vh - 2rem);
    margin: auto;
}

.vault-edit-modal .modal-content {
    display: flex;
    flex-direction: column;
    height: 100%;
    max-height: 100%;
    min-height: 0;
    overflow: hidden;
    border: 1px solid var(--bs-border-color);
    border-top: 4px solid var(--bs-primary);
    border-radius: .85rem;
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
    box-shadow: 0 1.5rem 4rem rgba(0, 0, 0, .35);
}

.vault-edit-modal .modal-header,
.vault-edit-modal .modal-footer {
    flex: 0 0 auto;
    background: var(--bs-body-bg);
}

.vault-edit-modal form {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
}

.vault-edit-modal .modal-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow-x: hidden;
    overflow-y: auto;
    overscroll-behavior: contain;
}

.vault-edit-modal .modal-title {
    font-size: 1.1rem;
    font-weight: 700;
}

.vault-edit-modal .vault-edit-context {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .5rem;
}

.vault-edit-modal .vault-edit-context-item {
    min-width: 0;
    padding: .55rem .7rem;
    border: 1px solid var(--bs-border-color);
    border-radius: .55rem;
    background: var(--bs-tertiary-bg);
}

.vault-edit-context-item small {
    display: block;
    color: var(--bs-secondary-color);
    font-size: .68rem;
    text-transform: uppercase;
    letter-spacing: .04em;
}

.vault-edit-context-item span {
    display: block;
    overflow-wrap: anywhere;
    font-size: .83rem;
    font-weight: 600;
    line-height: 1.35;
    white-space: normal;
}

.vault-edit-denomination-table {
    font-size: .79rem;
}

.vault-edit-denomination-table> :not(caption)>*>* {
    padding: .23rem .5rem;
}

.vault-edit-denomination-table th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: var(--bs-tertiary-bg);
    color: var(--bs-emphasis-color);
    white-space: nowrap;
}

.vault-edit-denomination-table .form-control {
    width: 5.25rem;
    min-height: 1.7rem;
    padding: .1rem .35rem;
    text-align: right;
}

.vault-edit-denomination-table .badge {
    min-width: 2.5rem;
    padding: .24rem .35rem;
    font-size: .65rem;
}

.vault-edit-total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: .55rem .8rem;
    border: 1px solid var(--bs-primary-border-subtle);
    border-radius: .65rem;
    background: var(--bs-primary-bg-subtle);
    font-weight: 700;
}

.vault-transaction-view-modal {
    position: fixed;
    inset: 0;
    z-index: 1090;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    overflow-y: auto;
    background: rgba(15, 23, 42, .72);
    backdrop-filter: blur(6px);
}

.vault-transaction-view-modal.is-open {
    display: flex;
}

.vault-transaction-view-modal .modal-dialog {
    width: 780px;
    max-width: calc(100vw - 2rem);
    height: min(84vh, 860px);
    max-height: calc(100vh - 2rem);
    margin: auto;
}

.vault-transaction-view-modal .modal-content {
    display: flex;
    flex-direction: column;
    height: 100%;
    max-height: 100%;
    min-height: 0;
    overflow: hidden;
    border: 1px solid var(--bs-border-color);
    border-top: 4px solid var(--bs-primary);
    border-radius: .85rem;
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
    box-shadow: 0 1.5rem 4rem rgba(0, 0, 0, .35);
}

.vault-transaction-view-modal .modal-header,
.vault-transaction-view-modal .modal-footer {
    flex: 0 0 auto;
    background: var(--bs-body-bg);
}

.vault-transaction-view-modal .modal-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow-x: hidden;
    overflow-y: auto;
    overscroll-behavior: contain;
}

.vault-transaction-view-modal .vault-action-close {
    width: 2rem;
    height: 2rem;
    display: grid;
    place-items: center;
    border: 0;
    border-radius: 50%;
    background: var(--bs-secondary-bg);
    color: var(--bs-body-color);
}

.vault-row-actions {
    position: relative;
    display: inline-block;
}

.vault-row-actions>summary {
    list-style: none;
    cursor: pointer;
}

.vault-row-actions>summary::-webkit-details-marker {
    display: none;
}

.vault-row-actions__menu {
    position: fixed;
    z-index: 1150;
    min-width: 12rem;
    padding: .35rem;
    border: 1px solid var(--bs-border-color);
    border-radius: .65rem;
    background: var(--bs-body-bg);
    box-shadow: 0 .75rem 2rem rgba(15, 23, 42, .18);
}

.vault-row-actions__menu .dropdown-item {
    display: flex;
    width: 100%;
    align-items: center;
    gap: .5rem;
    padding: .45rem .6rem;
    border: 0;
    border-radius: .4rem;
    background: transparent;
    color: var(--bs-body-color);
    font-size: .84rem;
    text-align: left;
    text-decoration: none;
}

.vault-row-actions__menu .dropdown-item:hover,
.vault-row-actions__menu .dropdown-item:focus {
    background: var(--bs-tertiary-bg);
    color: var(--bs-emphasis-color);
}

.vault-row-actions__menu form {
    margin: 0;
}

.vault-row-actions__menu .dropdown-divider {
    margin: .3rem 0;
}

.vault-transaction-summary {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .55rem;
}

.vault-transaction-summary__item {
    min-width: 0;
    padding: .6rem .75rem;
    border: 1px solid var(--bs-border-color);
    border-radius: .6rem;
    background: var(--bs-tertiary-bg);
}

.vault-transaction-summary__item small {
    display: block;
    color: var(--bs-secondary-color);
    font-size: .68rem;
    letter-spacing: .04em;
    text-transform: uppercase;
}

.vault-transaction-summary__item span {
    display: block;
    overflow-wrap: anywhere;
    font-size: .85rem;
    font-weight: 600;
    line-height: 1.35;
}

.vault-transaction-view-table {
    font-size: .8rem;
}

.vault-transaction-view-table th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: var(--bs-tertiary-bg);
    white-space: nowrap;
}

.vault-image-preview {
    display: grid;
    grid-template-columns: 96px 1fr;
    align-items: center;
    gap: .75rem;
    margin-top: .6rem;
    padding: .6rem;
    border: 1px dashed var(--bs-border-color);
    border-radius: .65rem;
    background: var(--bs-tertiary-bg);
}

.vault-image-preview__frame {
    display: grid;
    width: 96px;
    height: 72px;
    place-items: center;
    overflow: hidden;
    border: 1px solid var(--bs-border-color);
    border-radius: .5rem;
    background: var(--bs-body-bg);
    color: var(--bs-secondary-color);
}

.vault-image-preview__frame img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.vault-image-preview__frame img:not(.d-none) {
    cursor: zoom-in;
}

.vault-image-preview__meta {
    color: var(--bs-secondary-color);
    font-size: .78rem;
    overflow-wrap: anywhere;
}

.vault-image-viewer {
    position: fixed;
    inset: 0;
    z-index: 1200;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 3.5rem 1.5rem 1.5rem;
    background: rgba(0, 0, 0, .86);
}

.vault-image-viewer.is-open {
    display: flex;
}

.vault-image-viewer__image {
    display: block;
    max-width: min(100%, 1600px);
    max-height: calc(100vh - 5rem);
    object-fit: contain;
    border-radius: .35rem;
    box-shadow: 0 1rem 3rem rgba(0, 0, 0, .45);
}

.vault-image-viewer__close {
    position: fixed;
    top: 1rem;
    right: 1rem;
    z-index: 1;
    width: 2.75rem;
    height: 2.75rem;
    border: 1px solid rgba(255, 255, 255, .45);
    border-radius: 50%;
    background: rgba(20, 20, 20, .7);
    color: #fff;
    font-size: 1.4rem;
}

.vault-image-viewer__caption {
    position: fixed;
    right: 4.5rem;
    top: 1.35rem;
    left: 1.5rem;
    color: #fff;
    font-size: .9rem;
    text-align: center;
    overflow-wrap: anywhere;
}

.vault-statement-table th {
    white-space: nowrap;
}

.vault-statement-table td {
    vertical-align: middle;
}

.vault-statement-table thead th {
    background: var(--bs-tertiary-bg);
    border-bottom: 2px solid var(--bs-border-color);
    color: var(--bs-emphasis-color);
    font-size: .76rem;
    letter-spacing: .035em;
    text-transform: uppercase;
}

.vault-statement-table tbody tr {
    transition: background-color .15s ease, box-shadow .15s ease;
}

.vault-statement-table tbody tr:hover {
    background: var(--bs-primary-bg-subtle);
    box-shadow: inset 3px 0 var(--bs-primary);
}

.vault-stock-row--ghs>* {
    background-color: var(--bs-success-bg-subtle) !important;
    color: var(--bs-success-text-emphasis);
}

.vault-stock-row--cfa>* {
    background-color: var(--bs-info-bg-subtle) !important;
    color: var(--bs-info-text-emphasis);
}

.vault-denomination-detail-table th {
    white-space: nowrap;
}

.vault-activity-direction {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    margin-top: .25rem;
    padding: .2rem .5rem;
    border-radius: 999px;
    background: var(--bs-secondary-bg);
    font-size: .75rem;
    font-weight: 600;
}

.vault-purchase-rate {
    display: table;
    width: max-content;
    max-width: 100%;
    margin-top: .22rem;
    padding: .12rem .38rem;
    border: 1px solid rgba(111, 66, 193, .2);
    border-radius: 999px;
    background: rgba(111, 66, 193, .08);
    color: var(--bs-emphasis-color);
    font-size: .65rem;
    line-height: 1.35;
    white-space: nowrap;
}

.vault-denomination-summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    padding: .7rem .85rem;
    border: 1px solid var(--bs-border-color);
    border-radius: .75rem;
    background: var(--bs-tertiary-bg);
}

.vault-denomination-summary small {
    display: block;
    color: var(--bs-secondary-color);
}

.vault-denomination-calculator {
    position: fixed;
    inset: 0;
    z-index: 1100;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: rgba(15, 23, 42, .72);
    backdrop-filter: blur(5px);
}

.vault-denomination-calculator.is-open {
    display: flex;
}

.vault-denomination-calculator .modal-dialog {
    width: 560px;
    max-width: calc(100vw - 2rem);
    margin: 0;
}

.vault-denomination-calculator .modal-content {
    border: 2px solid var(--bs-primary);
    border-radius: .9rem;
    max-height: calc(100vh - 2rem);
    overflow: hidden;
    box-shadow: 0 1rem 3rem rgba(0, 0, 0, .35);
}

.vault-denomination-calculator .modal-header {
    background: var(--bs-primary-bg-subtle);
    border-bottom: 2px solid var(--bs-primary);
}

.vault-denomination-calculator .modal-body {
    overflow-y: auto;
}

.vault-denomination-calculator .denomination-calculator-table input {
    min-width: 5rem;
}

[data-recon-calculator-open] {
    min-width: 9rem;
}

@media(max-width:767px) {
    .vault-list-filters {
        flex-wrap: wrap;
    }

    .vault-list-filters [data-vault-search] {
        flex-basis: 100%;
    }

    .vault-list-filters select {
        flex: 1 1 8rem;
    }

    .vault-action-modal[data-vault-modal="adjustment"] .card-body {
        padding: 1rem;
    }

    .vault-edit-modal {
        padding: .5rem;
    }

    .vault-edit-modal .modal-dialog {
        height: calc(100vh - 1rem);
        max-height: calc(100vh - 1rem);
    }

    .vault-edit-modal .modal-content {
        max-height: calc(100vh - 1rem);
    }

    .vault-edit-modal .vault-edit-context {
        grid-template-columns: 1fr;
    }

    .vault-transaction-view-modal {
        padding: .5rem;
    }

    .vault-transaction-view-modal .modal-dialog {
        height: calc(100vh - 1rem);
        max-height: calc(100vh - 1rem);
    }

    .vault-transaction-summary {
        grid-template-columns: 1fr;
    }

    .vault-image-preview {
        grid-template-columns: 76px 1fr;
    }

    .vault-image-preview__frame {
        width: 76px;
        height: 60px;
    }
}

</style>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h2 class="h4 mb-1"><?= esc($pageTitle) ?></h2>
            <div class="text-muted small">Branch cash custody, approved movements, reconciliations, and statements.
            </div>
        </div>
        <div class="d-flex flex-wrap justify-content-end gap-2">
            <?php if (\Vaults\canManage()): ?>
            <?php if ($isEmbeddedView): ?>
            <?php elseif ($isOperationsView): ?>
            <a class="btn btn-outline-primary btn-sm" href="<?= esc($baseUrl) ?>">Backoffice view</a>
            <?php if (!$selectedVault): ?>
            <button class="btn btn-primary btn-sm" type="button" data-create-vault-open>
                <i class="fa fa-plus me-1" aria-hidden="true"></i> Create vault
            </button>
            <?php endif; ?>
            <?php else: ?>
            <a class="btn btn-outline-primary btn-sm" href="<?= esc($operationsUrl) ?>">Operations view</a>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($errors['action'])): ?>
    <div class="alert alert-danger"><?= esc((string)$errors['action']) ?></div>
    <?php endif; ?>
    <?php if (!$selectedVault): ?>
    <?php if (!empty($vaults)): ?>
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <div class="card h-100 vault-metric-card vault-metric-card--vault">
                <div class="card-body d-flex align-items-center gap-3"><span
                        class="vault-metric-icon vault-metric-icon--vault"><i class="fa fa-building"></i></span>
                    <div>
                        <div class="small text-muted">Accessible vaults</div>
                        <div class="fs-4 fw-semibold"><?= count($vaults) ?></div>
                        <div class="small text-muted">Active branches</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card h-100 vault-metric-card vault-metric-card--ghs">
                <div class="card-body d-flex align-items-center gap-3"><span
                        class="vault-metric-icon vault-metric-icon--ghs"><i class="fa fa-money-bill-wave"></i></span>
                    <div>
                        <div class="small text-muted">GHS held</div>
                        <div class="fs-5 fw-semibold">GH₵ <?= number_format((float)$allVaultBalances['GHS'], 2) ?></div>
                        <div class="small text-muted">Across accessible vaults</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card h-100 vault-metric-card vault-metric-card--cfa">
                <div class="card-body d-flex align-items-center gap-3"><span
                        class="vault-metric-icon vault-metric-icon--cfa"><i class="fa fa-coins"></i></span>
                    <div>
                        <div class="small text-muted">CFA held</div>
                        <div class="fs-5 fw-semibold"><?= number_format((float)$allVaultBalances['CFA'], 0) ?> CFA</div>
                        <div class="small text-muted">At purchased rates</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card h-100 vault-metric-card vault-metric-card--total">
                <div class="card-body d-flex align-items-center gap-3"><span
                        class="vault-metric-icon vault-metric-icon--total"><i class="fa fa-chart-pie"></i></span>
                    <div>
                        <div class="small text-muted">Combined value</div>
                        <div class="fs-5 fw-semibold">GH₵
                            <?= number_format((float)($allVaultBalances['GHS'] + $allVaultBalances['CFA_GHS_VALUE']), 2) ?>
                        </div>
                        <div class="small text-muted">CFA at purchase rates</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="fw-semibold"><?= $isOperationsView ? 'Branch vaults' : 'Vaults available to your branch' ?>
                </div>
                <div class="small text-muted">Search, filter, then open a vault to view its details and actions.</div>
            </div>
            <div class="vault-list-filters">
                <input class="form-control form-control-sm" type="search" placeholder="Search vaults"
                    aria-label="Search vaults" data-vault-search>
                <select class="form-select form-select-sm" aria-label="Filter by branch" data-vault-branch-filter>
                    <option value="">All branches</option>
                    <?php foreach (
                                array_unique(array_map(static function ($v) {
                                    return (string)($v->branch_name ?? '');
                                }, $vaults)) as $branchName
                            ): if ($branchName === '') continue; ?>
                    <option value="<?= esc(strtolower($branchName)) ?>"><?= esc($branchName) ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="form-select form-select-sm" aria-label="Filter by currency balance"
                    data-vault-currency-filter>
                    <option value="">All currencies</option>
                    <option value="ghs">GHS balance</option>
                    <option value="cfa">CFA balance</option>
                </select>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" data-vault-list>
                <thead>
                    <tr>
                        <th>Vault</th>
                        <th>Branch</th>
                        <th>Custodian</th>
                        <th>GHS</th>
                        <th>CFA</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vaults as $vault): $vaultBalance = \Vaults\balanceSummary((int)$vault->id);
                                $rowAttentionCount = (int)($vaultAttentionCounts[(int)$vault->id] ?? 0); ?>
                    <tr class="<?= ($rowAttentionCount > 0 ? 'vault-attention-row ' : '') . (((int)$vault->id === $activeVaultId) ? 'table-active' : '') ?>"
                        data-vault-row
                        data-search="<?= esc(strtolower((string)$vault->name . ' ' . (string)($vault->branch_name ?? '') . ' ' . trim((string)($vault->first_name ?? '') . ' ' . (string)($vault->last_name ?? '')))) ?>"
                        data-branch="<?= esc(strtolower((string)($vault->branch_name ?? ''))) ?>"
                        data-has-ghs="<?= ((float)$vaultBalance['GHS'] > 0) ? '1' : '0' ?>"
                        data-has-cfa="<?= ((float)$vaultBalance['CFA'] > 0) ? '1' : '0' ?>">
                        <td class="fw-semibold">
                            <?= esc((string)$vault->name) ?>
                            <?php if (!(int)$vault->active): ?><span class="badge text-bg-secondary ms-1">Disabled</span><?php endif; ?>
                            <?php if ($rowAttentionCount > 0): ?><span
                                class="vault-attention-pill"><i class="fa fa-bell"></i><?= $rowAttentionCount ?>
                                attention</span><?php endif; ?></td>
                        <td><?= esc((string)($vault->branch_name ?? '')) ?></td>
                        <td><?= esc(trim((string)($vault->first_name ?? '') . ' ' . (string)($vault->last_name ?? '')) ?: 'Not assigned') ?>
                        </td>
                        <td>GH₵ <?= number_format((float)$vaultBalance['GHS'], 2) ?></td>
                        <td><?= number_format((float)$vaultBalance['CFA'], 0) ?> CFA</td>
                        <td class="text-end"><a
                                class="btn btn-sm <?= ((int)$vault->id === $activeVaultId) ? 'btn-primary' : 'btn-outline-primary' ?>"
                                href="<?= esc($vaultPageUrl) ?>?vault_id=<?= (int)$vault->id ?><?= $isEmbeddedView ? '#vaults' : '' ?>">View
                                details</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="small text-muted" data-vault-page-summary></div>
            <div class="d-flex align-items-center gap-2">
                <label class="small text-muted" for="vaultPageSize">Rows</label>
                <select id="vaultPageSize" class="form-select form-select-sm" style="width:auto"
                    aria-label="Vaults per page" data-vault-page-size>
                    <option>10</option>
                    <option>25</option>
                    <option>50</option>
                </select>
                <button class="btn btn-outline-secondary btn-sm" type="button" data-vault-prev>Previous</button>
                <span class="small text-muted text-nowrap" data-vault-page-number></span>
                <button class="btn btn-outline-secondary btn-sm" type="button" data-vault-next>Next</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$vaults): ?>
    <div class="alert alert-info">
        <?= \Vaults\canManage() && !$isOperationsView
                    ? 'No active vaults yet. Create and assign a vault from the Operations view.'
                    : (\Vaults\canManage()
                        ? 'No active vaults yet. Use Create and assign branch vault to set one up.'
                        : 'There is no active vault assigned to your branch. Contact Operations.') ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($activeVaultId > 0 && $selectedVault): ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <a class="small text-decoration-none"
                href="<?= esc($vaultPageUrl) ?><?= $isEmbeddedView ? '#vaults' : '' ?>"><i
                    class="fa fa-arrow-left me-1"></i> Back to vaults</a>
            <div class="h5 mb-1"><?= esc((string)$selectedVault->name) ?></div>
            <div class="small text-muted"><?= esc((string)($selectedVault->branch_name ?? '')) ?> ·
                <?= esc(trim((string)($selectedVault->first_name ?? '') . ' ' . (string)($selectedVault->last_name ?? '')) ?: 'No custodian assigned') ?>
                <?php if (!(int)$selectedVault->active): ?> · <span class="text-danger fw-semibold">Disabled</span><?php endif; ?>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if (\user_can('is_admin')): ?>
            <button class="btn btn-outline-primary btn-sm" type="button" data-vault-metadata-edit-open>
                <i class="fa fa-pen-to-square me-1" aria-hidden="true"></i>Edit vault
            </button>
            <?php endif; ?>
            <?php if ((int)$selectedVault->active): ?><button class="btn btn-primary btn-sm" type="button" data-vault-modal-open="request"><i
                    class="fa fa-paper-plane me-1"></i> Request</button>
            <?php if ($canReconcileSelectedVault): ?>
            <button class="btn btn-outline-primary btn-sm" type="button" data-vault-modal-open="reconciliation"
                <?= !$canSubmitReconciliation ? 'disabled title="' . (!empty($pendingReconciliation) ? 'A reconciliation is awaiting review.' : 'The staff member who submitted the rejected reconciliation must resubmit it.') . '"' : '' ?>><i
                    class="fa fa-rotate me-1"></i>
                <?= !empty($pendingReconciliation) ? 'Awaiting review' : 'Reconciliation' ?><?php if ($selectedVaultReconciliationAttentionCount > 0): ?><span
                    class="vault-tab-attention-pill"
                    aria-label="<?= $selectedVaultReconciliationAttentionCount ?> reconciliation items require your attention"><?= $selectedVaultReconciliationAttentionCount ?></span><?php endif; ?></button>
            <?php endif; ?>
            <?php if (\Vaults\canManage()): ?><button class="btn btn-outline-primary btn-sm" type="button"
                data-vault-modal-open="adjustment"><i class="fa fa-sliders me-1"></i> Adjustment</button><?php endif; ?>
            <?php endif; ?>
            <a class="btn btn-success btn-sm"
                href="<?= esc($baseUrl) ?>/statement?vault_id=<?= $activeVaultId ?>&amp;format=xlsx" data-app-export
                title="Export Excel statement"><i class="fa fa-file-excel me-1"></i> Excel</a>
            <a class="btn btn-danger btn-sm"
                href="<?= esc($baseUrl) ?>/statement?vault_id=<?= $activeVaultId ?>&amp;format=pdf" data-app-export
                title="Export PDF statement"><i class="fa fa-file-pdf me-1"></i> PDF</a>
        </div>
    </div>
    <?php if (\user_can('is_admin')): ?>
    <div class="vault-edit-modal" data-vault-metadata-edit-modal aria-hidden="true" role="dialog" aria-modal="true"
        aria-labelledby="vaultMetadataEditTitle">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <span class="badge text-bg-primary mb-1">Administrator settings</span>
                        <h5 class="modal-title" id="vaultMetadataEditTitle">Edit vault details</h5>
                        <div class="small text-muted">Update vault assignment, limits, or availability.</div>
                    </div>
                    <button type="button" class="vault-action-close" data-vault-metadata-edit-close
                        aria-label="Close vault editor"><i class="fa fa-times"></i></button>
                </div>
                <form method="post">
                    <?= $csrfField ?>
                    <input type="hidden" name="action" value="edit_vault">
                    <input type="hidden" name="vault_id" value="<?= $activeVaultId ?>">
                    <div class="modal-body">
                        <div class="alert alert-info py-2">
                            Vaults with posted movements cannot be reassigned to another branch. Limits cannot be set
                            below the existing balances.
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="vaultMetadataName">Vault name</label>
                            <input class="form-control" id="vaultMetadataName" name="name" maxlength="120" required
                                value="<?= esc((string)$selectedVault->name) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="vaultMetadataBranch">Branch</label>
                            <select class="form-select" id="vaultMetadataBranch" name="branch_id"
                                data-vault-metadata-branch required>
                                <?php foreach ($allBranches as $branch): ?>
                                <option value="<?= (int)$branch->id ?>"
                                    <?= (int)$selectedVault->branch_id === (int)$branch->id ? 'selected' : '' ?>>
                                    <?= esc((string)$branch->name) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="vaultMetadataCustodian">Custodian</label>
                            <select class="form-select" id="vaultMetadataCustodian" name="custodian_user_id"
                                data-vault-metadata-custodian required>
                                <?php foreach ($users as $person): ?>
                                <option value="<?= (int)$person->id ?>" data-branch="<?= (int)($person->branch_id ?? 0) ?>"
                                    <?= (int)$selectedVault->custodian_user_id === (int)$person->id ? 'selected' : '' ?>>
                                    <?= esc(trim((string)$person->first_name . ' ' . (string)$person->last_name)) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="vaultMetadataGhsLimit">GHS limit</label>
                                <div class="input-group"><span class="input-group-text">GH₵</span>
                                    <input class="form-control" id="vaultMetadataGhsLimit" type="number" min="0"
                                        step="0.01" name="limit_ghs" value="<?= esc((string)$selectedVault->limit_ghs) ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="vaultMetadataCfaLimit">CFA limit</label>
                                <div class="input-group">
                                    <input class="form-control" id="vaultMetadataCfaLimit" type="number" min="0"
                                        step="0.01" name="limit_cfa" value="<?= esc((string)$selectedVault->limit_cfa) ?>"
                                        <?= $currentCfaRate <= 0 ? 'disabled' : '' ?>>
                                    <span class="input-group-text">CFA</span>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="vaultMetadataTotalLimit">Cumulative GHS-equivalent limit</label>
                                <div class="input-group"><span class="input-group-text">GH₵</span>
                                    <input class="form-control" id="vaultMetadataTotalLimit" type="text" readonly
                                        value="<?= number_format((float)$selectedVault->limit_total_ghs, 2) ?>">
                                </div>
                                <div class="form-text">Automatically calculated using the current CFA rate.
                                    <?= $currentCfaRate > 0 ? 'CFA 1,000 = GH₵ ' . number_format($currentCfaRate, 2) : 'CFA rate is unavailable.' ?>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch border rounded p-3 ps-5">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                        id="vaultMetadataActive" name="active" value="1"
                                        <?= (int)$selectedVault->active === 1 ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="vaultMetadataActive">Vault is active</label>
                                    <div class="small text-muted">Disabled vaults are removed from normal use and cannot receive transfers.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary"
                            data-vault-metadata-edit-close>Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fa fa-save me-1"></i>Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <ul class="nav nav-tabs mb-3" role="tablist" aria-label="Vault detail sections"
        data-vault-tab-list="<?= (int)$activeVaultId ?>">
        <li class="nav-item" role="presentation"><button class="nav-link active" type="button" role="tab"
                aria-selected="true" data-vault-tab="overview"><i class="fa fa-chart-pie vault-tab-icon"
                    aria-hidden="true"></i>Overview</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" type="button" role="tab" aria-selected="false"
                data-vault-tab="reconciliation"><i class="fa fa-clipboard-check vault-tab-icon"
                    aria-hidden="true"></i>Reconciliation<?php if ($selectedVaultReconciliationAttentionCount > 0): ?><span
                    class="vault-tab-attention-pill"
                    aria-label="<?= $selectedVaultReconciliationAttentionCount ?> reconciliations require your attention"><?= $selectedVaultReconciliationAttentionCount ?></span><?php endif; ?></button>
        </li>
        <li class="nav-item" role="presentation"><button class="nav-link" type="button" role="tab" aria-selected="false"
                data-vault-tab="workflow"><i class="fa fa-list-check vault-tab-icon"
                    aria-hidden="true"></i>Approval workflow<?php if ($selectedVaultAttentionCount > 0): ?><span
                    class="vault-tab-attention-pill"
                    aria-label="<?= $selectedVaultAttentionCount ?> activities require your attention"><?= $selectedVaultAttentionCount ?></span><?php endif; ?></button>
        </li>
        <li class="nav-item" role="presentation"><button class="nav-link" type="button" role="tab" aria-selected="false"
                data-vault-tab="statement"><i class="fa fa-file-lines vault-tab-icon"
                    aria-hidden="true"></i>Statement</button></li>
    </ul>
    <section class="vault-detail-pane" data-vault-pane="overview">
        <div class="row g-3 mb-3">
            <div class="col-12 col-md-4">
                <div class="card h-100 vault-overview-metric vault-overview-metric--ghs">
                    <div class="card-body">
                        <span class="vault-overview-icon"><i class="fa fa-money-bill-wave"
                                aria-hidden="true"></i></span>
                        <div class="small text-muted">GHS cash</div>
                        <div class="fs-4 fw-semibold">GH₵ <?= number_format((float)$balances['GHS'], 2) ?></div>
                        <div class="small text-muted">Limit:
                            <?= ((float)$selectedVault->limit_ghs > 0) ? 'GH₵ ' . number_format((float)$selectedVault->limit_ghs, 2) : 'Not set' ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card h-100 vault-overview-metric vault-overview-metric--cfa">
                    <div class="card-body">
                        <span class="vault-overview-icon"><i class="fa fa-coins"
                                aria-hidden="true"></i></span>
                        <div class="small text-muted">CFA cash · purchased lots</div>
                        <div class="fs-4 fw-semibold"><?= number_format((float)$balances['CFA'], 0) ?> CFA</div>
                        <div class="small text-muted">GHS value at purchase rates: GH₵
                            <?= number_format((float)$balances['CFA_GHS_VALUE'], 2) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card h-100 vault-overview-metric vault-overview-metric--custodian">
                    <div class="card-body">
                        <span class="vault-overview-icon"><i class="fa fa-user-shield"
                                aria-hidden="true"></i></span>
                        <div class="small text-muted">Assigned custodian</div>
                        <div class="fs-5 fw-semibold">
                            <?= esc(trim((string)($selectedVault->first_name ?? '') . ' ' . (string)($selectedVault->last_name ?? '')) ?: 'Not assigned') ?>
                        </div>
                        <div class="small text-muted">Current CFA rate: CFA 1,000 = GH₵
                            <?= ($currentCfaRate > 0) ? number_format($currentCfaRate, 2) : 'not configured' ?></div>
                    </div>
                </div>
            </div>
        </div>

        <section class="mb-3" aria-labelledby="vaultAnalyticsTitle">
            <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-2">
                <div>
                    <h3 class="h6 mb-1" id="vaultAnalyticsTitle"><i class="fa fa-chart-line text-primary me-1"
                            aria-hidden="true"></i>Vault analytics</h3>
                    <div class="small text-muted">Posted movements over the last 30 days, with CFA converted at each
                        movement’s purchased rate.</div>
                </div>
                <span class="badge text-bg-light border">Daily net movement · GHS equivalent</span>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-6 col-xl-3">
                    <div class="card h-100 vault-analytics-card vault-analytics-card--inflow">
                        <div class="card-body py-3">
                            <span class="vault-analytics-value-icon"><i class="fa fa-arrow-down"
                                    aria-hidden="true"></i></span>
                            <div class="small text-muted">Inflow · 30 days</div>
                            <div class="fs-5 fw-semibold text-success">GH₵
                                <?= number_format((float)$vaultAnalytics['inflow_30d'], 2) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card h-100 vault-analytics-card vault-analytics-card--outflow">
                        <div class="card-body py-3">
                            <span class="vault-analytics-value-icon"><i class="fa fa-arrow-up"
                                    aria-hidden="true"></i></span>
                            <div class="small text-muted">Outflow · 30 days</div>
                            <div class="fs-5 fw-semibold text-danger">GH₵
                                <?= number_format((float)$vaultAnalytics['outflow_30d'], 2) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card h-100 vault-analytics-card vault-analytics-card--net">
                        <div class="card-body py-3">
                            <span class="vault-analytics-value-icon"><i class="fa fa-scale-balanced"
                                    aria-hidden="true"></i></span>
                            <div class="small text-muted">Net movement · 30 days</div>
                            <div
                                class="fs-5 fw-semibold <?= (float)$vaultAnalytics['net_30d'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= (float)$vaultAnalytics['net_30d'] < 0 ? '−' : '' ?>GH₵
                                <?= number_format(abs((float)$vaultAnalytics['net_30d']), 2) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card h-100 vault-analytics-card vault-analytics-card--activity">
                        <div class="card-body py-3">
                            <span class="vault-analytics-value-icon"><i class="fa fa-right-left"
                                    aria-hidden="true"></i></span>
                            <div class="small text-muted">Activities · 30 days</div>
                            <div class="fs-5 fw-semibold"><?= (int)$vaultAnalytics['activity_count_30d'] ?></div>
                            <div class="small text-muted">Posted movement references</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card vault-analytics-card">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <span class="fw-semibold">Daily net movement · <?= esc($vaultAnalytics['trend_start']) ?> to
                        <?= esc($vaultAnalytics['trend_end']) ?></span>
                    <form method="get" class="d-flex flex-wrap align-items-end gap-2">
                        <input type="hidden" name="vault_id" value="<?= $activeVaultId ?>">
                        <div>
                            <label class="form-label small mb-1" for="vaultChartFrom">From</label>
                            <input class="form-control form-control-sm" type="date" id="vaultChartFrom"
                                name="chart_from" value="<?= esc($vaultAnalytics['trend_start']) ?>" required>
                        </div>
                        <div>
                            <label class="form-label small mb-1" for="vaultChartTo">To</label>
                            <input class="form-control form-control-sm" type="date" id="vaultChartTo"
                                name="chart_to" value="<?= esc($vaultAnalytics['trend_end']) ?>" required>
                        </div>
                        <button class="btn btn-primary btn-sm" type="submit"><i
                                class="fa fa-magnifying-glass me-1" aria-hidden="true"></i>Search</button>
                    </form>
                </div>
                <?php if (!empty($vaultAnalytics['trend_range_limited'])): ?>
                <div class="px-3 pt-2 small text-warning-emphasis">Daily chart ranges are limited to 366 days.
                    Showing the most recent 366 days ending on the selected end date.</div>
                <?php endif; ?>
                <div class="px-3 pt-2 small text-muted">Positive bars add value; negative bars remove value.</div>
                <div class="table-responsive">
                    <div class="vault-analytics-chart" role="img"
                        aria-label="Daily net movement in GHS equivalent from <?= esc($vaultAnalytics['trend_start']) ?> to <?= esc($vaultAnalytics['trend_end']) ?>">
                        <div class="vault-analytics-chart-zero" aria-hidden="true"></div>
                        <div class="vault-analytics-chart-grid"
                            style="grid-template-columns: repeat(<?= count($vaultAnalytics['trend']) ?>, minmax(2rem, 1fr))">
                            <?php foreach ($vaultAnalytics['trend'] as $day): ?>
                            <?php
                                    $netValue = (float)$day['net'];
                                    $barClass = $netValue >= 0 ? 'positive' : 'negative';
                                    $barHeight = number_format((float)$day['height'], 2, '.', '');
                                    $netLabel = ($netValue >= 0 ? '+' : '−') . ' GH₵ ' . number_format(abs($netValue), 2);
                                ?>
                            <div class="vault-analytics-day" aria-label="<?= esc($day['label'] . ': ' . $netLabel) ?>"
                                title="<?= esc($day['label'] . ': ' . $netLabel) ?>">
                                <div class="vault-analytics-plot">
                                    <?php if ($netValue != 0.0): ?>
                                    <span class="vault-analytics-bar vault-analytics-bar--<?= $barClass ?>"
                                        style="height: <?= $barHeight ?>%"></span>
                                    <?php endif; ?>
                                </div>
                                <span class="vault-analytics-date"><?= esc($day['label']) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php if (\user_can('is_admin') && (int)$selectedVault->active === 1
                && (int)($selectedVault->created_by ?? 0) === $userId): ?>
            <div class="card mb-3 border-primary vault-admin-cash-in-card">
                <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <div class="fw-semibold"><i class="fa fa-arrow-right-arrow-left text-primary me-2"
                                aria-hidden="true"></i>Admin cash-in to your vault</div>
                        <div class="small text-muted mt-1">Transfer cash from a same-branch drawer with a denomination
                            breakdown, signed proof, and a complete audit trail.</div>
                    </div>
                    <button class="btn btn-primary" type="button" data-vault-admin-cash-in-open
                        <?= !$cashInDrawerUsers ? 'disabled' : '' ?>>
                        <i class="fa fa-plus me-1" aria-hidden="true"></i> Transfer cash from drawer
                    </button>
                    <?php if (!$cashInDrawerUsers): ?>
                    <div class="small text-danger w-100">No same-branch cashdrawers are open today.</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal fade" id="vaultAdminCashInModal" tabindex="-1" aria-hidden="true"
                aria-labelledby="vaultAdminCashInTitle">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div>
                                <div class="badge text-bg-primary mb-1">Audited drawer transfer</div>
                                <h5 class="modal-title" id="vaultAdminCashInTitle">Cash-in to <?= esc((string)$selectedVault->name) ?></h5>
                                <div class="small text-muted">Cash is deducted from the selected drawer and posted to this vault.</div>
                            </div>
                            <button type="button" class="btn-close" data-vault-admin-cash-in-close
                                aria-label="Close cash-in"></button>
                        </div>
                        <form method="post" enctype="multipart/form-data" data-vault-admin-cash-in-form>
                            <?= $csrfField ?>
                            <input type="hidden" name="action" value="admin_cash_in">
                            <input type="hidden" name="vault_id" value="<?= $activeVaultId ?>">
                            <?php foreach ([
                                'GHS' => [200, 100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1],
                                'CFA' => [10000, 5000, 2000, 1000, 500, 200, 100, 50, 25, 10, 5],
                            ] as $cashCurrency => $cashDenominations): ?>
                            <?php foreach ($cashDenominations as $cashDenomination): ?>
                            <input type="hidden" name="counts[<?= esc($cashCurrency) ?>][<?= esc((string)$cashDenomination) ?>]"
                                value="0" data-vault-cash-in-hidden-count data-currency="<?= esc($cashCurrency) ?>"
                                data-denomination="<?= esc((string)$cashDenomination) ?>">
                            <?php endforeach; ?>
                            <?php endforeach; ?>
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-md-5">
                                        <label class="form-label" for="vaultAdminCashInCurrency">Currency</label>
                                        <select class="form-select" id="vaultAdminCashInCurrency" name="currency"
                                            data-vault-cash-in-currency required>
                                            <option value="GHS">GHS</option>
                                            <option value="CFA" <?= $currentCfaRate <= 0 ? 'disabled' : '' ?>>CFA</option>
                                        </select>
                                    </div>
                                    <div class="col-md-7">
                                        <label class="form-label" for="vaultAdminCashInDrawer">Source cashdrawer</label>
                                        <select class="form-select" id="vaultAdminCashInDrawer" name="drawer_user_id"
                                            required>
                                            <option value="">Select a cashdrawer</option>
                                            <?php foreach ($cashInDrawerUsers as $drawerUser): ?>
                                            <option value="<?= (int)$drawerUser->id ?>"
                                                data-cashdrawer-id="<?= (int)$drawerUser->cashdrawer_id ?>"
                                                data-ghs-counts="<?= esc(json_encode($drawerUser->ghs_counts, JSON_UNESCAPED_SLASHES)) ?>"
                                                data-cfa-counts="<?= esc(json_encode($drawerUser->cfa_counts, JSON_UNESCAPED_SLASHES)) ?>"
                                                <?= (int)$drawerUser->id === $userId ? 'selected' : '' ?>>
                                                <?= esc(trim((string)$drawerUser->first_name . ' ' . (string)$drawerUser->last_name)) ?>
                                                (drawer #<?= (int)$drawerUser->cashdrawer_id ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="vaultAdminCashInNotes">Reason / reference</label>
                                        <input class="form-control" id="vaultAdminCashInNotes" name="notes"
                                            maxlength="2000" required
                                            placeholder="Record the source or reason for this cash-in">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="vaultAdminCashInProof">Signed cash-in proof</label>
                                        <input class="form-control" id="vaultAdminCashInProof" type="file"
                                            name="proof_image" accept="image/*" required
                                            data-vault-image-input data-preview-image="vaultAdminCashInProofPreview"
                                            data-preview-empty="vaultAdminCashInProofEmpty"
                                            data-preview-meta="vaultAdminCashInProofMeta">
                                        <div class="form-text">Choose the signed cash-in sheet as an image, up to 2MB.</div>
                                        <div class="vault-image-preview mt-2">
                                            <div class="vault-image-preview__frame">
                                                <span id="vaultAdminCashInProofEmpty"><i class="fa fa-image fa-lg"
                                                        aria-hidden="true"></i></span>
                                                <img id="vaultAdminCashInProofPreview" class="d-none"
                                                    alt="Vault cash-in proof preview" tabindex="0" role="button"
                                                    aria-label="View full-size cash-in proof" data-vault-image-open>
                                            </div>
                                            <div class="vault-image-preview__meta" id="vaultAdminCashInProofMeta">
                                                Choose a signed cash-in sheet to preview it.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="vault-admin-cash-in-summary">
                                            <div>
                                                <div class="small text-muted">Denominations counted</div>
                                                <div class="fw-semibold" data-vault-cash-in-count-total>0</div>
                                            </div>
                                            <div>
                                                <div class="small text-muted">Transfer total</div>
                                                <div class="fw-semibold text-primary" data-vault-cash-in-volume-total>GH₵ 0.00</div>
                                            </div>
                                            <button type="button" class="btn btn-outline-primary"
                                                data-vault-cash-in-calculator-open aria-expanded="false">
                                                <i class="fa fa-calculator me-1" aria-hidden="true"></i>Open denomination calculator
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="alert alert-danger py-2 d-none mb-0" role="alert"
                                            data-vault-cash-in-feedback></div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary"
                                    data-vault-admin-cash-in-close>Cancel</button>
                                <button class="btn btn-primary" type="submit"
                                    <?= !$cashInDrawerUsers ? 'disabled' : '' ?>>
                                    <i class="fa fa-check me-1" aria-hidden="true"></i>Record cash-in
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal fade" id="vaultAdminCashInCalculator" tabindex="-1" aria-hidden="true"
                aria-labelledby="vaultAdminCashInCalculatorTitle">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div>
                                <div class="badge text-bg-primary mb-1">Drawer denomination breakdown</div>
                                <h5 class="modal-title" id="vaultAdminCashInCalculatorTitle">Count the cash-in</h5>
                                <div class="small text-muted" data-vault-cash-in-calculator-subtitle>
                                    Enter the denomination counts being transferred from the drawer.
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-vault-cash-in-calculator-close
                                aria-label="Close denomination calculator"></button>
                        </div>
                        <div class="modal-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-sm align-middle mb-0 vault-admin-cash-in-table">
                                    <thead>
                                        <tr>
                                            <th>Denomination</th>
                                            <th class="text-end" style="width:100px">Available</th>
                                            <th style="width:120px">Transfer</th>
                                            <th class="text-end" style="width:150px">Volume</th>
                                        </tr>
                                    </thead>
                                    <?php foreach ([
                                        'GHS' => [200, 100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1],
                                        'CFA' => [10000, 5000, 2000, 1000, 500, 200, 100, 50, 25, 10, 5],
                                    ] as $cashCurrency => $cashDenominations): ?>
                                    <tbody data-vault-cash-in-currency-rows="<?= esc($cashCurrency) ?>">
                                        <tr class="table-active">
                                            <th colspan="4"><?= esc($cashCurrency) ?>
                                                <?= $cashCurrency === 'GHS' ? 'notes and coins' : 'notes' ?></th>
                                        </tr>
                                        <?php foreach ($cashDenominations as $cashDenomination): ?>
                                        <tr data-vault-cash-in-row data-currency="<?= esc($cashCurrency) ?>"
                                            data-denomination="<?= esc((string)$cashDenomination) ?>">
                                            <td><?= $cashCurrency === 'GHS'
                                                ? 'GH₵ ' . number_format((float)$cashDenomination, 2)
                                                : number_format((float)$cashDenomination, 0) . ' CFA' ?></td>
                                            <td class="text-end" data-vault-cash-in-available>0</td>
                                            <td><input class="form-control form-control-sm" type="number" min="0"
                                                    step="1" inputmode="numeric" placeholder="0" value=""
                                                    data-vault-cash-in-count aria-label="<?= esc($cashCurrency . ' ' . $cashDenomination . ' count') ?>"></td>
                                            <td><input class="form-control form-control-sm text-end" type="text"
                                                    value="0.00" readonly data-vault-cash-in-volume
                                                    aria-label="<?= esc($cashCurrency . ' ' . $cashDenomination . ' volume') ?>"></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <?php endforeach; ?>
                                    <tfoot>
                                        <tr>
                                            <th>Total notes / coins</th>
                                            <th><input class="form-control form-control-sm" type="text" readonly
                                                    data-vault-cash-in-calculator-count-total></th>
                                            <th><input class="form-control form-control-sm text-end" type="text"
                                                    readonly data-vault-cash-in-calculator-volume-total></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer justify-content-between">
                            <button type="button" class="btn btn-outline-secondary"
                                data-vault-cash-in-calculator-reset>Reset counts</button>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-secondary"
                                    data-vault-cash-in-calculator-close>Cancel</button>
                                <button type="button" class="btn btn-primary"
                                    data-vault-cash-in-calculator-apply disabled>Apply denomination counts</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </section>

        <div class="card mb-3">
            <div class="card-header fw-semibold">Denomination inventory by purchased rate</div>
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Currency</th>
                            <th>Denomination</th>
                            <th>Count</th>
                            <th>Face value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$stock): ?><tr>
                            <td colspan="4" class="text-center text-muted py-3">No cash has been posted to this vault.
                            </td>
                        </tr><?php endif; ?>
                        <?php foreach ($stock as $line): ?>
                        <tr class="vault-stock-row--<?= strtolower((string)$line->currency) ?>">
                            <td><?= esc((string)$line->currency) ?></td>
                            <td>
                                <div>
                                    <?= number_format((float)$line->denomination, (string)$line->currency === 'CFA' ? 0 : 2) ?>
                                </div>
                                <?php if ((string)$line->currency === 'CFA'): ?>
                                <span class="vault-purchase-rate">CFA 1,000 <i class="fa fa-arrow-right"></i> GH₵
                                    <?= number_format((float)$line->purchase_rate, 2) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= (int)$line->note_count ?></td>
                            <td><?= number_format((float)$line->denomination * (int)$line->note_count, 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </section>
    <section class="vault-action-modal" data-vault-modal="request" role="dialog" aria-modal="true" aria-hidden="true"
        aria-labelledby="vaultRequestTitle">
        <div class="row g-3 mb-3">
            <div class="col-12">
                <div class="card h-100">
                    <div class="card-header"><span class="fw-semibold" id="vaultRequestTitle"><i
                                class="fa fa-paper-plane text-primary me-2"></i>Request a vault movement</span><button
                            type="button" class="vault-action-close" data-vault-modal-close
                            aria-label="Close request"><i class="fa fa-times"></i></button></div>
                    <div class="card-body">
                        <form method="post" enctype="multipart/form-data" class="row g-2 vault-denomination-form"
                            data-denomination-form
                            data-vault-stock="<?= esc(json_encode($transferVaultStock, JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>"
                            data-user-drawer-counts="<?= esc(json_encode($staffCashdrawerDenominationCounts ?? ['GHS' => [], 'CFA' => []], JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>"
                            data-user-branch="<?= (int)\Vaults\currentBranchId($userId) ?>"
                            data-can-manage="<?= \Vaults\canManage() ? '1' : '0' ?>">
                            <?= $csrfField ?>
                            <input type="hidden" name="action" value="submit_transaction">
                            <div class="col-md-6">
                                <label class="form-label">Movement</label>
                                <select class="form-select" name="transaction_type" data-transaction-type required>
                                    <option value="to_agent">From vault to agent</option>
                                    <option value="from_agent">Agent to vault</option>
                                    <option value="interbranch">Interbranch vault transfer</option>
                                    <option value="denos_exchange">Denomination exchange</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Currency</label>
                                <select class="form-select" name="currency" data-currency required>
                                    <option value="GHS">GHS</option>
                                    <option value="CFA">CFA</option>
                                </select>
                            </div>
                            <div class="col-md-6" data-source-vault-wrap>
                                <label class="form-label">Source vault</label>
                                <select class="form-select" name="source_vault_id" data-source-vault>
                                    <option value="">— Not applicable —</option>
                                    <?php foreach ($transferVaults as $vault): ?><option value="<?= (int)$vault->id ?>"
                                        data-branch="<?= (int)$vault->branch_id ?>"
                                        <?= ((int)$vault->id === $activeVaultId) ? 'selected' : '' ?>>
                                        <?= esc((string)$vault->name) ?> · <?= esc((string)$vault->branch_name) ?>
                                    </option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6" data-destination-vault-wrap>
                                <label class="form-label">Destination vault</label>
                                <select class="form-select" name="destination_vault_id" data-destination-vault>
                                    <option value="">— Not applicable —</option>
                                    <?php foreach ($transferVaults as $vault): ?><option value="<?= (int)$vault->id ?>"
                                        data-branch="<?= (int)$vault->branch_id ?>"
                                        <?= ((int)$vault->id === $activeVaultId) ? 'selected' : '' ?>>
                                        <?= esc((string)$vault->name) ?> · <?= esc((string)$vault->branch_name) ?>
                                    </option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 small text-muted" data-agent-destination-note>
                                For agent movements, the requesting staff member’s own cashdrawer is the agent
                                destination or source. It changes only after final approval.
                            </div>
                            <div class="col-12" data-normal-denominations>
                                <label class="form-label">Denomination counts</label>
                                <div class="vault-denomination-summary">
                                    <div>
                                        <div class="fw-semibold" data-request-summary="out">No denominations entered
                                        </div><small>Source vault counts are shown in the calculator before you enter a
                                            request.</small>
                                    </div>
                                    <button class="btn btn-outline-primary btn-sm" type="button"
                                        data-request-deno-open="out"><i class="fa fa-calculator me-1"></i>Open
                                        calculator</button>
                                </div>
                                <div class="alert alert-danger py-2 mt-2 mb-0 d-none" role="alert"
                                    data-denomination-shortage></div>
                            </div>
                            <div class="col-12 d-none" data-exchange-section>
                                <label class="form-label">Notes given</label>
                                <div class="vault-denomination-summary">
                                    <div>
                                        <div class="fw-semibold" data-request-summary="out">No denominations entered
                                        </div><small>Cash leaving the vault.</small>
                                    </div>
                                    <button class="btn btn-outline-primary btn-sm" type="button"
                                        data-request-deno-open="out"><i class="fa fa-calculator me-1"></i>Enter notes
                                        given</button>
                                </div>
                                <div class="alert alert-danger py-2 mt-2 mb-0 d-none" role="alert"
                                    data-denomination-shortage></div>
                                <label class="form-label mt-3">Notes received</label>
                                <div class="vault-denomination-summary">
                                    <div>
                                        <div class="fw-semibold" data-request-summary="in">No denominations entered
                                        </div><small>Cash entering the vault; totals must match notes given.</small>
                                    </div>
                                    <button class="btn btn-outline-success btn-sm" type="button"
                                        data-request-deno-open="in"><i class="fa fa-calculator me-1"></i>Enter notes
                                        received</button>
                                </div>
                                <div class="col-md-6 mt-2 d-none" data-exchange-rate-wrap>
                                    <label class="form-label">CFA purchased-rate lot</label>
                                    <select name="exchange_rate" class="form-select" data-exchange-rate>
                                        <option value="">Select an available purchase-rate lot</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-6"><label class="form-label">Total amount</label><input class="form-control"
                                    data-total readonly></div>
                            <div class="col-6 d-none" data-exchange-received-total-wrap><label
                                    class="form-label">Received total</label><input class="form-control"
                                    data-exchange-total readonly></div>
                            <div class="col-6"><label class="form-label">Assigned first party</label>
                                <select class="form-select" name="assigned_first_party_id" data-first-party-select
                                    required>
                                    <option value="">Select staff…</option>
                                    <?php foreach ($eligibleTransactionUsers as $person): if (\Vaults\userHasHigherRole((int)$person->id)) continue; ?>
                                    <option value="<?= (int)$person->id ?>"
                                        data-branch="<?= (int)$person->branch_id ?>">
                                        <?= esc(trim((string)$person->first_name . ' ' . (string)$person->last_name)) ?>
                                    </option><?php endforeach; ?>
                                </select>
                                <small class="form-text">Only staff marked Present/Late and with a cashdrawer opened
                                    today are listed.</small>
                            </div>
                            <input type="hidden" name="denos_json" data-denos>
                            <input type="hidden" name="exchange_received_json" data-exchange-received value="[]">
                            <div class="col-md-6"><label class="form-label">Approving manager</label>
                                <select class="form-select" name="assigned_approver_id" required>
                                    <option value="">Select approver…</option>
                                    <?php foreach ($eligibleTransactionUsers as $person): if (!\Vaults\userHasHigherRole((int)$person->id)) continue; ?>
                                    <option value="<?= (int)$person->id ?>">
                                        <?= esc(trim((string)$person->first_name . ' ' . (string)$person->last_name)) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text">Approvers must also be marked Present/Late and have a
                                    cashdrawer opened today. An eligible admin may approve their own request.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Signed treasury sheet</label>
                                <input class="form-control" type="file" name="proof_image" accept="image/*" required
                                    data-vault-image-input data-preview-image="vaultRequestProofPreview"
                                    data-preview-empty="vaultRequestProofEmpty"
                                    data-preview-meta="vaultRequestProofMeta">
                                <div class="vault-image-preview">
                                    <div class="vault-image-preview__frame"><span id="vaultRequestProofEmpty"><i
                                                class="fa fa-image fa-lg"></i></span><img id="vaultRequestProofPreview"
                                            class="d-none" alt="Treasury sheet preview" tabindex="0" role="button"
                                            aria-label="View full-size treasury sheet" data-vault-image-open></div>
                                    <div class="vault-image-preview__meta" id="vaultRequestProofMeta">Choose an image to
                                        preview the signed treasury sheet.</div>
                                </div>
                            </div>
                            <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control"
                                    name="notes" rows="2"></textarea></div>
                            <div class="col-12"><button class="btn btn-primary" type="submit">Submit for first-party
                                    check</button></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php if ($canReconcileSelectedVault): ?>
    <section class="vault-action-modal" data-vault-modal="reconciliation" role="dialog" aria-modal="true"
        aria-hidden="true" aria-labelledby="vaultReconciliationTitle">
        <div class="row g-3 mb-3">
            <div class="col-12">
                <div class="card h-100">
                    <div class="card-header"><span class="fw-semibold" id="vaultReconciliationTitle"><i
                                class="fa fa-rotate text-warning me-2"></i>Reconcile this vault</span><button
                            type="button" class="vault-action-close" data-vault-modal-close
                            aria-label="Close reconciliation"><i class="fa fa-times"></i></button></div>
                    <div class="card-body">
                        <form method="post" enctype="multipart/form-data" class="row g-2" data-reconciliation-form>
                            <?= $csrfField ?>
                            <input type="hidden" name="action" value="submit_reconciliation">
                            <input type="hidden" name="vault_id" value="<?= $activeVaultId ?>">
                            <?php if (!empty($latestReconciliation) && strtolower((string)$latestReconciliation->status) === 'rejected' && (int)$latestReconciliation->created_by === $userId): ?>
                            <div class="col-12">
                                <div class="alert alert-danger py-2 mb-1">
                                    <div class="fw-semibold">Resubmission required</div>
                                    <div class="small">The previous count was rejected. Correct the count and submit
                                        again. Reason: <?= esc((string)$latestReconciliation->reject_reason) ?></div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="col-12">
                                <p class="small text-muted mb-1">Count the physical cash in the vault without referring
                                    to the recorded balance. Enter each CFA denomination under its purchased-rate lot.
                                    The assigned reviewer will compare your submitted count with the vault records.</p>
                            </div>
                            <div class="col-12">
                                <div class="table-responsive">
                                    <?php
                                        $reconciliationGroups = [];
                                        foreach ($reconciliationStock as $line) {
                                            $currency = (string)$line->currency;
                                            $purchaseRate = (float)$line->purchase_rate;
                                            $groupKey = $currency . '|' . number_format($purchaseRate, 6, '.', '');
                                            $reconciliationGroups[$groupKey]['currency'] = $currency;
                                            $reconciliationGroups[$groupKey]['rate'] = $purchaseRate;
                                            $reconciliationGroups[$groupKey]['lines'][] = [
                                                'currency' => $currency,
                                                'denomination' => (float)$line->denomination,
                                                'purchase_rate' => $purchaseRate,
                                                'count' => 0,
                                            ];
                                        }
                                        ?>
                                    <table class="table table-sm table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>Currency</th>
                                                <th>Count</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reconciliationGroups as $groupKey => $group): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold"><?= esc($group['currency']) ?></div>
                                                    <?php if ($group['currency'] === 'CFA'): ?><span
                                                        class="vault-purchase-rate">CFA 1,000 <i
                                                            class="fa fa-arrow-right"></i> GH₵
                                                        <?= number_format($group['rate'], 2) ?></span><?php else: ?><span
                                                        class="small text-muted">Ghana cedi</span><?php endif; ?>
                                                </td>
                                                <td>
                                                    <button type="button"
                                                        class="btn btn-outline-primary btn-sm w-100 text-start"
                                                        data-recon-calculator-open
                                                        data-currency="<?= esc($group['currency']) ?>"
                                                        data-rate="<?= esc((string)$group['rate']) ?>"
                                                        data-stock="<?= esc(json_encode($group['lines'], JSON_UNESCAPED_SLASHES)) ?>">
                                                        <i class="fa fa-calculator me-1"></i><span
                                                            data-recon-total>Enter denomination counts</span>
                                                    </button>
                                                    <input type="hidden" data-recon-group-json value="[]">
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php if (!$reconciliationGroups): ?><tr>
                                                <td colspan="2" class="text-muted">No currency denomination groups are
                                                    configured for counting.</td>
                                            </tr><?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <input type="hidden" name="counted_stock_json" data-counted-stock>
                            </div>
                            <div class="col-md-6"><label class="form-label">Reconciliation reviewer</label>
                                <select class="form-select" name="assigned_approver_id" required>
                                    <option value="">Select approver…</option>
                                    <?php foreach ($users as $person): if (!\Vaults\userHasHigherRole((int)$person->id)) continue; ?>
                                    <option value="<?= (int)$person->id ?>">
                                        <?= esc(trim((string)$person->first_name . ' ' . (string)$person->last_name)) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Count sheet photo (optional)</label>
                                <input class="form-control" type="file" name="proof_image" accept="image/*"
                                    data-vault-image-input data-preview-image="vaultReconProofPreview"
                                    data-preview-empty="vaultReconProofEmpty" data-preview-meta="vaultReconProofMeta">
                                <div class="vault-image-preview">
                                    <div class="vault-image-preview__frame"><span id="vaultReconProofEmpty"><i
                                                class="fa fa-image fa-lg"></i></span><img id="vaultReconProofPreview"
                                            class="d-none" alt="Reconciliation count sheet preview" tabindex="0"
                                            role="button" aria-label="View full-size reconciliation count sheet"
                                            data-vault-image-open></div>
                                    <div class="vault-image-preview__meta" id="vaultReconProofMeta">Choose an image to
                                        preview the count sheet.</div>
                                </div>
                                <div class="form-text">Attach a signed count sheet if required by your branch.</div>
                            </div>
                            <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control"
                                    name="notes" rows="2"></textarea></div>
                            <div class="col-12"><button class="btn btn-outline-primary" type="submit">Submit
                                    reconciliation</button></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </section>
    <?php endif; ?>
    <div class="vault-denomination-calculator" data-vault-denomination-calculator aria-hidden="true" role="dialog"
        aria-modal="true" aria-labelledby="vaultDenoCalculatorTitle">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="vaultDenoCalculatorTitle">Currency denomination calculator</h5>
                        <div class="small text-muted" data-calculator-subtitle>Enter the physical count for each
                            denomination.</div>
                    </div>
                    <button type="button" class="btn-close" data-calculator-close
                        aria-label="Close calculator"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle denomination-calculator-table mb-0">
                            <thead>
                                <tr>
                                    <th>Denomination</th>
                                    <th class="text-end" style="width:85px">Available</th>
                                    <th style="width:100px">Requested</th>
                                    <th style="width:130px">Volume</th>
                                </tr>
                            </thead>
                            <tbody data-calculator-rows></tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="2">Total requested</th>
                                    <th><input class="form-control form-control-sm" type="text" readonly
                                            data-calculator-count-total></th>
                                    <th><input class="form-control form-control-sm" type="text" readonly
                                            data-calculator-volume-total></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="alert alert-danger py-2 mx-3 mb-0 d-none" role="alert" data-calculator-shortage></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-calculator-reset>Reset</button>
                    <button type="button" class="btn btn-outline-secondary" data-calculator-close>Cancel</button>
                    <button type="button" class="btn btn-primary" data-calculator-apply>Apply count</button>
                </div>
            </div>
        </div>
    </div>
    <?php if (\Vaults\canManage()): ?>
    <section class="vault-action-modal" data-vault-modal="adjustment" role="dialog" aria-modal="true" aria-hidden="true"
        aria-labelledby="vaultAdjustmentTitle">
        <div class="card mb-3">
            <div class="card-header"><span class="fw-semibold" id="vaultAdjustmentTitle"><i
                        class="fa fa-sliders text-info me-2"></i>Adjust vault limits</span><button type="button"
                    class="vault-action-close" data-vault-modal-close aria-label="Close adjustments"><i
                        class="fa fa-times"></i></button></div>
            <div class="card-body">
                <form method="post" class="vault-limit-form" data-vault-limit-form
                    data-cfa-rate="<?= $currentCfaRate ?>">
                    <?= $csrfField ?><input type="hidden" name="action" value="update_limits"><input type="hidden"
                        name="vault_id" value="<?= $activeVaultId ?>">
                    <div><label class="form-label">GHS limit</label><input class="form-control" type="number" min="0"
                            step="0.01" name="limit_ghs" value="<?= esc((string)$selectedVault->limit_ghs) ?>"
                            data-limit-ghs></div>
                    <div><label class="form-label">CFA limit</label><input class="form-control" type="number" min="0"
                            step="0.01" name="limit_cfa" value="<?= esc((string)$selectedVault->limit_cfa) ?>"
                            data-limit-cfa></div>
                    <div><label class="form-label">Cumulative GHS-equivalent limit</label><input class="form-control"
                            type="number" min="0" step="0.01" name="limit_total_ghs"
                            value="<?= esc((string)$selectedVault->limit_total_ghs) ?>" data-limit-total readonly></div>
                    <div><button class="btn btn-outline-primary"><i class="fa fa-floppy-disk me-1"></i>Save
                            limits</button></div>
                </form>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="vault-detail-pane d-none" data-vault-pane="workflow">
        <div class="card mb-3">
            <div class="card-header fw-semibold">Recent approval workflow</div>
            <?= $vaultGridControls('workflow', true, true) ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0" data-vault-grid="workflow">
                    <thead>
                        <tr>
                            <th>ID / Date</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Currency</th>
                            <th>Initiator</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$transactions): ?><tr>
                            <td colspan="6" class="text-center text-muted py-3">No vault transactions yet.</td>
                        </tr><?php endif; ?>
                        <?php foreach ($transactions as $transaction): ?>
                        <tr data-grid-status="<?= esc((string)$transaction->status) ?>"
                            data-grid-currency="<?= esc(strtoupper((string)$transaction->currency)) ?>"
                            data-grid-date="<?= esc(substr((string)$transaction->date_created, 0, 10)) ?>">
                            <td class="text-nowrap">
                                <div class="fw-semibold">#<?= (int)$transaction->id ?></div>
                                <div class="small text-muted"><?= esc($formatVaultDate($transaction->date_created)) ?></div>
                            </td>
                            <td><?= esc(ucwords(str_replace('_', ' ', (string)$transaction->transaction_type))) ?></td>
                            <td><span
                                    class="vault-status-badge <?= $vaultStatusClass((string)$transaction->status) ?>"><?= esc((string)$transaction->status) ?></span>
                            </td>
                            <td><?= esc((string)$transaction->currency) ?>
                                <?= number_format((float)$transaction->amount, 2) ?><?php if ((string)$transaction->currency === 'CFA'): ?>
                                <div class="small text-muted">CFA 1,000 <i class="fa fa-arrow-right mx-1"></i> GH₵
                                    <?= number_format((float)$transaction->cfa_rate, 2) ?></div><?php endif; ?>
                            </td>
                            <td><?= esc(trim((string)$transaction->first_name . ' ' . (string)$transaction->last_name)) ?>
                            </td>
                            <td class="text-nowrap">
                                <details class="vault-row-actions">
                                    <summary class="btn btn-outline-secondary btn-sm"><i
                                            class="fa fa-ellipsis me-1"></i>Actions</summary>
                                    <div class="vault-row-actions__menu">
                                        <button class="dropdown-item" type="button"
                                            data-vault-transaction-view="<?= (int)$transaction->id ?>">
                                            <i class="fa fa-eye text-primary"></i>View transaction
                                        </button>
                                        <?php if ($transaction->status === 'Pending First Party' && in_array($userId, [(int)$transaction->requested_by, (int)$transaction->assigned_first_party_id], true)): ?>
                                        <button class="dropdown-item" type="button"
                                            data-vault-edit-open="<?= (int)$transaction->id ?>">
                                            <i class="fa fa-pen-to-square"></i>Edit before confirmation
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($transaction->status === 'Pending First Party' && (int)$transaction->assigned_first_party_id === $userId): ?>
                                        <div class="dropdown-divider"></div>
                                        <form method="post">
                                            <?= $csrfField ?><input type="hidden" name="action"
                                                value="first_party_action"><input type="hidden" name="transaction_id"
                                                value="<?= (int)$transaction->id ?>">
                                            <input type="hidden" name="choice" value="confirm"><button
                                                class="dropdown-item text-success"><i
                                                    class="fa fa-check"></i>Confirm</button>
                                        </form>
                                        <button class="dropdown-item text-danger" type="button"
                                            data-vault-modal-open="transaction-reject"
                                            data-reject-action="first_party_action"
                                            data-reject-transaction="<?= (int)$transaction->id ?>"
                                            data-reject-title="Decline transaction">
                                            <i class="fa fa-ban"></i>Decline
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($transaction->status === 'Pending Approval' && (int)$transaction->assigned_approver_id === $userId && \Vaults\canManage()): ?>
                                        <div class="dropdown-divider"></div>
                                        <form method="post">
                                            <?= $csrfField ?><input type="hidden" name="action"
                                                value="review_transaction"><input type="hidden" name="transaction_id"
                                                value="<?= (int)$transaction->id ?>">
                                            <input type="hidden" name="choice" value="approve"><button
                                                class="dropdown-item text-success"><i
                                                    class="fa fa-check"></i>Approve</button>
                                        </form>
                                        <button class="dropdown-item text-danger" type="button"
                                            data-vault-modal-open="transaction-reject"
                                            data-reject-action="review_transaction"
                                            data-reject-transaction="<?= (int)$transaction->id ?>"
                                            data-reject-title="Reject transaction">
                                            <i class="fa fa-ban"></i>Reject
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </details>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="vault-grid-footer d-flex flex-wrap align-items-center justify-content-between gap-2 p-3 border-top"
                data-vault-grid-footer="workflow">
                <span class="small text-body-secondary" data-grid-summary></span>
                <div class="d-flex align-items-center gap-2"><label class="small text-body-secondary"
                        for="vaultWorkflowPageSize">Rows</label><select class="form-select form-select-sm"
                        id="vaultWorkflowPageSize" data-grid-page-size>
                        <option>10</option>
                        <option selected>25</option>
                        <option>50</option>
                        <option>100</option>
                    </select><button class="btn btn-outline-secondary btn-sm" type="button"
                        data-grid-prev>Previous</button><span class="small text-nowrap" data-grid-page></span><button
                        class="btn btn-outline-secondary btn-sm" type="button" data-grid-next>Next</button></div>
            </div>
        </div>
        <div class="vault-action-modal" data-vault-modal="transaction-reject" role="dialog" aria-modal="true"
            aria-hidden="true" aria-labelledby="vaultTransactionRejectTitle">
            <div class="row g-3">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <span class="fw-semibold" id="vaultTransactionRejectTitle"><i
                                    class="fa fa-triangle-exclamation text-danger me-2"></i><span
                                    data-transaction-reject-title>Reject transaction</span></span>
                            <button type="button" class="vault-action-close" data-vault-modal-close
                                aria-label="Close rejection dialog"><i class="fa fa-times"></i></button>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted mb-3">Provide a reason so the initiator knows what needs
                                attention.</p>
                            <form method="post" data-vault-transaction-reject-form>
                                <?= $csrfField ?>
                                <input type="hidden" name="action" value="">
                                <input type="hidden" name="transaction_id" value="">
                                <input type="hidden" name="choice" value="reject">
                                <div class="mb-3">
                                    <label class="form-label" for="vaultTransactionRejectReason">Reason <span
                                            class="text-danger">*</span></label>
                                    <textarea class="form-control" id="vaultTransactionRejectReason" name="reason"
                                        rows="4" maxlength="2000" required
                                        placeholder="Explain why this transaction is being rejected"></textarea>
                                </div>
                                <div class="d-flex justify-content-end gap-2">
                                    <button class="btn btn-outline-secondary" type="button"
                                        data-vault-modal-close>Cancel</button>
                                    <button class="btn btn-danger" type="submit"><i class="fa fa-ban me-1"></i>Confirm
                                        rejection</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php foreach ($transactions as $transaction):
                $viewDenoData = json_decode((string)($transaction->denos_json ?? ''), true);
                $viewDenoData = is_array($viewDenoData) ? $viewDenoData : [];
                $isViewExchange = (string)$transaction->transaction_type === 'denos_exchange';
                $viewOutLines = $isViewExchange && is_array($viewDenoData['out'] ?? null)
                    ? $viewDenoData['out']
                    : $viewDenoData;
                $viewInLines = $isViewExchange && is_array($viewDenoData['in'] ?? null)
                    ? $viewDenoData['in']
                    : [];
                $viewSource = trim((string)($transaction->source_vault_name ?? ''));
                $viewDestination = trim((string)($transaction->destination_vault_name ?? ''));
                if ($viewSource === '') {
                    $viewSource = !empty($transaction->source_vault_id) ? 'Vault #' . (int)$transaction->source_vault_id : 'Agent cashdrawer';
                }
                if ($viewDestination === '') {
                    $viewDestination = !empty($transaction->destination_vault_id) ? 'Vault #' . (int)$transaction->destination_vault_id : 'Agent cashdrawer';
                }
                $viewAgentName = trim((string)($transaction->agent_first_name ?? '') . ' ' . (string)($transaction->agent_last_name ?? ''));
                $viewInitiatorName = trim((string)$transaction->first_name . ' ' . (string)$transaction->last_name);
                $viewDrawerName = trim((string)($transaction->drawer_user_name ?? '')) ?: $viewAgentName;
                $viewDrawerId = (int)($transaction->agent_cashdrawer_id ?? 0);
                $viewAgentCashdrawer = 'Agent cashdrawer'
                    . ($viewDrawerName !== '' ? ' — ' . $viewDrawerName : '')
                    . ($viewDrawerId > 0 ? ' (drawer #' . $viewDrawerId . ')' : '');
                $viewRecordedCashdrawer = 'Cashdrawer'
                    . ($viewDrawerName !== '' ? ' — ' . $viewDrawerName : '')
                    . ($viewDrawerId > 0 ? ' (drawer #' . $viewDrawerId . ')' : '');
                if ((string)$transaction->transaction_type === 'from_agent' && empty($transaction->source_vault_id)) {
                    $viewSource = $viewAgentCashdrawer;
                }
                if ((string)$transaction->transaction_type === 'to_agent' && empty($transaction->destination_vault_id)) {
                    $viewDestination = $viewAgentCashdrawer;
                }
                if ((string)$transaction->transaction_type === 'admin_cash_in' && empty($transaction->source_vault_id)) {
                    $viewSource = $viewRecordedCashdrawer;
                }
                if ((string)$transaction->transaction_type === 'cashdrawer_opening') {
                    $viewDestination = $viewRecordedCashdrawer;
                }
                if ((string)$transaction->transaction_type === 'cashdrawer_return') {
                    $viewSource = $viewRecordedCashdrawer;
                }
            ?>
        <div class="vault-transaction-view-modal" data-vault-transaction-modal="<?= (int)$transaction->id ?>"
            role="dialog" aria-modal="true" aria-hidden="true"
            aria-labelledby="vaultTransactionTitle<?= (int)$transaction->id ?>">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header d-flex justify-content-between align-items-center p-3 border-bottom">
                        <div>
                            <div class="h5 mb-1" id="vaultTransactionTitle<?= (int)$transaction->id ?>"><i
                                    class="fa fa-receipt text-primary me-2"></i>Vault transaction
                                #<?= (int)$transaction->id ?></div>
                            <div class="small text-muted">
                                <?= esc(ucwords(str_replace('_', ' ', (string)$transaction->transaction_type))) ?> ·
                                <?= esc($formatVaultDate($transaction->date_created)) ?></div>
                        </div>
                        <button class="vault-action-close" type="button" data-vault-transaction-close
                            aria-label="Close transaction details"><i class="fa fa-times"></i></button>
                    </div>
                    <div class="modal-body p-3">
                        <div class="vault-transaction-summary mb-3">
                            <div class="vault-transaction-summary__item"><small>Status</small><span><span
                                        class="vault-status-badge <?= $vaultStatusClass((string)$transaction->status) ?>"><?= esc((string)$transaction->status) ?></span></span>
                            </div>
                            <div class="vault-transaction-summary__item">
                                <small>Amount</small><span><?= esc((string)$transaction->currency) ?>
                                    <?= number_format((float)$transaction->amount, 2) ?><?php if ((string)$transaction->currency === 'CFA'): ?><span
                                        class="vault-purchase-rate">CFA 1,000 <i class="fa fa-arrow-right"></i> GH₵
                                        <?= number_format((float)$transaction->cfa_rate, 2) ?></span><?php endif; ?></span>
                            </div>
                            <div class="vault-transaction-summary__item"><small>Initiated
                                    by</small><span><?= esc($viewInitiatorName ?: 'Unknown staff') ?></span></div>
                            <div class="vault-transaction-summary__item"><small>Agent / assigned
                                    staff</small><span><?= esc($viewAgentName ?: '—') ?></span></div>
                            <div class="vault-transaction-summary__item">
                                <small>Source</small><span><?= esc($viewSource) ?></span>
                            </div>
                            <div class="vault-transaction-summary__item">
                                <small>Destination</small><span><?= esc($viewDestination) ?></span>
                            </div>
                            <div class="vault-transaction-summary__item"><small>First
                                    party</small><span><?= esc(trim((string)($transaction->first_party_first_name ?? '') . ' ' . (string)($transaction->first_party_last_name ?? '')) ?: '—') ?></span>
                            </div>
                            <div class="vault-transaction-summary__item">
                                <small>Approver</small><span><?= esc(trim((string)($transaction->approver_first_name ?? '') . ' ' . (string)($transaction->approver_last_name ?? '')) ?: '—') ?></span>
                            </div>
                            <?php if (!empty($transaction->reviewer_first_name) || !empty($transaction->reviewer_last_name)): ?>
                            <div class="vault-transaction-summary__item"><small>Reviewed
                                    by</small><span><?= esc(trim((string)$transaction->reviewer_first_name . ' ' . (string)$transaction->reviewer_last_name)) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if (trim((string)($transaction->notes ?? '')) !== ''): ?>
                        <div class="alert alert-secondary py-2">
                            <div class="small fw-semibold mb-1">Transaction notes</div>
                            <?= nl2br(esc((string)$transaction->notes)) ?>
                        </div>
                        <?php endif; ?>
                        <?php if (trim((string)($transaction->reject_reason ?? '')) !== ''): ?>
                        <div class="alert alert-danger py-2">
                            <div class="small fw-semibold mb-1">Rejection reason</div>
                            <?= nl2br(esc((string)$transaction->reject_reason)) ?>
                        </div>
                        <?php endif; ?>
                        <?php foreach (['out' => ['Notes / coins given', $viewOutLines], 'in' => ['Notes / coins received', $viewInLines]] as $setKey => [$setLabel, $denominationLines]):
                                    if ($setKey === 'in' && !$isViewExchange) continue;
                                ?>
                        <h6 class="fw-semibold mt-3"><?= esc($setLabel) ?></h6>
                        <?php if (!$denominationLines): ?>
                        <div class="small text-muted mb-3">No denomination breakdown recorded.</div>
                        <?php else: ?>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-hover align-middle vault-transaction-view-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Denomination</th>
                                        <th>Type</th>
                                        <th class="text-end">Count</th>
                                        <th class="text-end">Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $denominationTotal = 0.0;
                                                    foreach ($denominationLines as $line):
                                                        if (!is_array($line)) continue;
                                                        $denomination = (float)($line['denomination'] ?? 0);
                                                        $count = max(0, (int)($line['count'] ?? 0));
                                                        $lineValue = $denomination * $count;
                                                        $denominationTotal += $lineValue;
                                                        $viewCurrency = strtoupper((string)$transaction->currency);
                                                        $viewCoin = $viewCurrency === 'GHS' && $denomination < 1;
                                                    ?>
                                    <tr>
                                        <td class="fw-semibold text-nowrap">
                                            <?= number_format($denomination, $viewCurrency === 'CFA' ? 0 : 2) ?>
                                            <?= esc($viewCurrency) ?></td>
                                        <td><span
                                                class="badge <?= $viewCoin ? 'text-bg-info' : 'text-bg-secondary' ?>"><?= $viewCoin ? 'Coin' : 'Note' ?></span>
                                        </td>
                                        <td class="text-end"><?= number_format($count) ?></td>
                                        <td class="text-end"><?= number_format($lineValue, 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="fw-semibold">
                                        <td colspan="3" class="text-end">Total</td>
                                        <td class="text-end"><?= number_format($denominationTotal, 2) ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                        <div class="mb-1 fw-semibold">Signed treasury sheet</div>
                        <?php if (!empty($transaction->proof_image)): ?>
                        <div class="vault-image-preview">
                            <div class="vault-image-preview__frame">
                                <img src="<?= esc($vaultImageUrl((string)$transaction->proof_image)) ?>"
                                    alt="Signed treasury sheet for transaction #<?= (int)$transaction->id ?>"
                                    tabindex="0" role="button" aria-label="View full-size signed treasury sheet"
                                    data-vault-image-open>
                            </div>
                            <div class="vault-image-preview__meta">Select the image to view it at full size.</div>
                        </div>
                        <?php else: ?>
                        <div class="small text-muted">No image attached.</div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer p-3 border-top"><button class="btn btn-outline-secondary btn-sm"
                            type="button" data-vault-transaction-close>Close</button></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php foreach ($transactions as $transaction):
                if (
                    $transaction->status !== 'Pending First Party'
                    || !in_array($userId, [(int)$transaction->requested_by, (int)$transaction->assigned_first_party_id], true)
                ) {
                    continue;
                }
                $transactionDenoData = json_decode((string)$transaction->denos_json, true);
                $isExchangeEdit = (string)$transaction->transaction_type === 'denos_exchange';
                $outEditLines = $isExchangeEdit
                    ? (is_array($transactionDenoData['out'] ?? null) ? $transactionDenoData['out'] : [])
                    : (is_array($transactionDenoData) ? $transactionDenoData : []);
                $inEditLines = $isExchangeEdit && is_array($transactionDenoData['in'] ?? null)
                    ? $transactionDenoData['in']
                    : [];
                $editCurrency = strtoupper((string)$transaction->currency);
            ?>
        <div class="vault-edit-modal" data-vault-edit-modal="<?= (int)$transaction->id ?>" role="dialog"
            aria-modal="true" aria-hidden="true" aria-labelledby="vaultEditTitle<?= (int)$transaction->id ?>">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header d-flex justify-content-between align-items-center p-3 border-bottom">
                        <div>
                            <div class="h5 mb-1" id="vaultEditTitle<?= (int)$transaction->id ?>"><i
                                    class="fa fa-pen-to-square text-primary me-2"></i>Edit pending vault movement</div>
                            <div class="small text-muted">Transaction #<?= (int)$transaction->id ?> ·
                                <?= esc($editCurrency) ?> denomination counts</div>
                        </div>
                        <button class="vault-action-close" type="button" data-vault-edit-close
                            aria-label="Close editor"><i class="fa fa-times"></i></button>
                    </div>
                    <form method="post" enctype="multipart/form-data" data-vault-edit-form
                        data-exchange="<?= $isExchangeEdit ? '1' : '0' ?>">
                        <?= $csrfField ?>
                        <input type="hidden" name="action" value="edit_pending_transaction">
                        <input type="hidden" name="transaction_id" value="<?= (int)$transaction->id ?>">
                        <input type="hidden" name="denos_json" value="" data-vault-edit-json>
                        <div class="modal-body p-3">
                            <div class="vault-edit-context mb-3">
                                <div class="vault-edit-context-item">
                                    <small>Movement</small><span><?= esc(ucwords(str_replace('_', ' ', (string)$transaction->transaction_type))) ?></span>
                                </div>
                                <div class="vault-edit-context-item">
                                    <small>Currency</small><span><?= esc($editCurrency) ?><?= ($editCurrency === 'CFA') ? ' · CFA 1,000 → GH₵ ' . number_format((float)$transaction->cfa_rate, 2) : '' ?></span>
                                </div>
                                <div class="vault-edit-context-item"><small>Source vault</small><span
                                        title="<?= esc((string)($transaction->source_vault_name ?? 'Agent cashdrawer / not applicable')) ?>"><?= esc((string)($transaction->source_vault_name ?? 'Agent cashdrawer / not applicable')) ?></span>
                                </div>
                                <div class="vault-edit-context-item"><small>Destination vault</small><span
                                        title="<?= esc((string)($transaction->destination_vault_name ?? 'Agent cashdrawer / not applicable')) ?>"><?= esc((string)($transaction->destination_vault_name ?? 'Agent cashdrawer / not applicable')) ?></span>
                                </div>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-sm-6">
                                    <label class="form-label"
                                        for="vaultEditFirstParty<?= (int)$transaction->id ?>">Assigned second
                                        party</label>
                                    <select class="form-select form-select-sm"
                                        id="vaultEditFirstParty<?= (int)$transaction->id ?>"
                                        name="assigned_first_party_id" required>
                                        <option value="">Select staff member</option>
                                        <?php $editFirstPartyListed = false;
                                                foreach ($eligibleTransactionUsers as $person):
                                                    if (\Vaults\userHasHigherRole((int)$person->id)) continue;
                                                    if ((int)$person->id === (int)$transaction->assigned_first_party_id) $editFirstPartyListed = true;
                                                ?>
                                        <option value="<?= (int)$person->id ?>"
                                            <?= ((int)$person->id === (int)$transaction->assigned_first_party_id) ? 'selected' : '' ?>>
                                            <?= esc(trim((string)$person->first_name . ' ' . (string)$person->last_name)) ?>
                                        </option>
                                        <?php endforeach; ?>
                                        <?php if (!$editFirstPartyListed && (int)$transaction->assigned_first_party_id > 0): ?>
                                        <option value="<?= (int)$transaction->assigned_first_party_id ?>" selected>
                                            <?= esc(trim((string)$transaction->first_party_first_name . ' ' . (string)$transaction->first_party_last_name)) ?>
                                            · no longer eligible today</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label"
                                        for="vaultEditApprover<?= (int)$transaction->id ?>">Approving manager</label>
                                    <select class="form-select form-select-sm"
                                        id="vaultEditApprover<?= (int)$transaction->id ?>" name="assigned_approver_id"
                                        required>
                                        <option value="">Select approver</option>
                                        <?php $editApproverListed = false;
                                                foreach ($eligibleTransactionUsers as $person):
                                                    if (!\Vaults\userHasHigherRole((int)$person->id)) continue;
                                                    if ((int)$person->id === (int)$transaction->assigned_approver_id) $editApproverListed = true;
                                                ?>
                                        <option value="<?= (int)$person->id ?>"
                                            <?= ((int)$person->id === (int)$transaction->assigned_approver_id) ? 'selected' : '' ?>>
                                            <?= esc(trim((string)$person->first_name . ' ' . (string)$person->last_name)) ?>
                                        </option>
                                        <?php endforeach; ?>
                                        <?php if (!$editApproverListed && (int)$transaction->assigned_approver_id > 0): ?>
                                        <option value="<?= (int)$transaction->assigned_approver_id ?>" selected>
                                            <?= esc(trim((string)$transaction->approver_first_name . ' ' . $transaction->approver_last_name)) ?>
                                            · no longer eligible today</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            </div>
                            <?php if ($isExchangeEdit): ?>
                            <div class="alert alert-info small"><i class="fa fa-circle-info me-1"></i>Adjust both sides
                                of the exchange. Notes given and received must balance exactly.</div>
                            <?php else: ?>
                            <div class="alert alert-secondary small"><i class="fa fa-calculator me-1"></i>Enter the
                                physical count for each denomination. Zero-count denominations may be left at zero.
                            </div>
                            <?php endif; ?>
                            <h6 class="fw-semibold"><?= $isExchangeEdit ? 'Notes given' : 'Movement denominations' ?>
                            </h6>
                            <?php $renderEditDenominationTable($outEditLines, 'out', $editCurrency); ?>
                            <?php if ($isExchangeEdit): ?>
                            <h6 class="fw-semibold mt-4">Notes received</h6>
                            <?php $renderEditDenominationTable($inEditLines, 'in', $editCurrency); ?>
                            <div class="alert alert-warning py-2 mb-3 d-none" data-vault-edit-mismatch>Given and
                                received totals must match before saving.</div>
                            <?php endif; ?>
                            <div class="vault-edit-total mb-3">
                                <span><?= $isExchangeEdit ? 'Given total' : 'Transaction total' ?></span><span
                                    data-vault-edit-total="out">0.00</span>
                            </div>
                            <?php if ($isExchangeEdit): ?><div class="vault-edit-total mb-3"><span>Received
                                    total</span><span data-vault-edit-total="in">0.00</span></div><?php endif; ?>
                            <label class="form-label" for="vaultEditNotes<?= (int)$transaction->id ?>">Transaction
                                notes</label>
                            <textarea class="form-control form-control-sm mb-3"
                                id="vaultEditNotes<?= (int)$transaction->id ?>" name="notes"
                                rows="2"><?= esc((string)($transaction->notes ?? '')) ?></textarea>
                            <label class="form-label" for="vaultEditProof<?= (int)$transaction->id ?>">Replace signed
                                treasury sheet <span class="text-muted">(optional)</span></label>
                            <input class="form-control" id="vaultEditProof<?= (int)$transaction->id ?>" type="file"
                                name="proof_image" accept="image/*" data-vault-image-input
                                data-preview-image="vaultEditProofPreview<?= (int)$transaction->id ?>"
                                data-preview-empty="vaultEditProofEmpty<?= (int)$transaction->id ?>"
                                data-preview-meta="vaultEditProofMeta<?= (int)$transaction->id ?>">
                            <div class="vault-image-preview">
                                <div class="vault-image-preview__frame">
                                    <span id="vaultEditProofEmpty<?= (int)$transaction->id ?>"
                                        class="<?= $transaction->proof_image ? 'd-none' : '' ?>"><i
                                            class="fa fa-image fa-lg"></i></span>
                                    <img id="vaultEditProofPreview<?= (int)$transaction->id ?>"
                                        class="<?= $transaction->proof_image ? '' : 'd-none' ?>"
                                        src="<?= esc($vaultImageUrl((string)($transaction->proof_image ?? ''))) ?>"
                                        alt="Current signed treasury sheet" tabindex="0" role="button"
                                        aria-label="View full-size signed treasury sheet" data-vault-image-open>
                                </div>
                                <div class="vault-image-preview__meta"
                                    id="vaultEditProofMeta<?= (int)$transaction->id ?>">
                                    <?= !empty($transaction->proof_image) ? 'Current signed treasury sheet. Choose a replacement to preview it.' : 'No signed treasury sheet is currently attached.' ?>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer d-flex justify-content-end gap-2 p-3 border-top">
                            <button class="btn btn-outline-secondary" type="button"
                                data-vault-edit-close>Cancel</button>
                            <button class="btn btn-primary" type="submit"><i class="fa fa-floppy-disk me-1"></i>Save
                                changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

    </section>
    <section class="vault-detail-pane d-none" data-vault-pane="statement">
        <div class="card mb-3">
            <div class="card-header fw-semibold">Vault transaction statement · balances and denomination detail</div>
            <?= $vaultGridControls('statement', false, true, true) ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 vault-statement-table" data-vault-grid="statement">
                    <thead>
                        <tr>
                            <th>ID / Date</th>
                            <th>Activity</th>
                            <th>Currency</th>
                            <th>Total amount</th>
                            <th>Balance</th>
                            <th>staff</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$statementMovements): ?><tr>
                            <td colspan="7" class="text-center text-muted py-3">No posted vault movements yet.</td>
                        </tr><?php endif; ?>
                        <?php foreach ($statementMovements as $movement): ?>
                        <?php
                                $activityKey = (string)($movement->transaction_type ?: $movement->reference_type);
                                $activityLabels = [
                                    'to_agent' => 'Cash issued to agent',
                                    'from_agent' => 'Cash received from agent',
                                    'interbranch' => 'Interbranch vault transfer',
                                    'denos_exchange' => 'Denomination exchange',
                                    'admin_cash_in' => 'Admin cash-in',
                                    'cashdrawer_opening' => 'Daily cashdrawer issued',
                                    'cashdrawer_return' => 'Daily cashdrawer returned',
                                    'opening_stock' => 'Opening balance',
                                    'bank_transfer' => strtolower((string)$movement->direction) === 'in' ? 'Bank deposit' : 'Bank withdrawal',
                                    'vault_transaction' => 'Vault transaction',
                                    'vault_reconciliation' => 'Vault reconciliation',
                                ];
                                $activityLabel = $activityLabels[$activityKey] ?? ucwords(str_replace('_', ' ', $activityKey));
                                $directionIn = strtolower((string)$movement->direction) === 'in';
                                $movementCurrency = strtoupper((string)$movement->currency);
                                $movementCount = (int)$movement->note_count;
                                $countUnit = $movementCurrency === 'GHS' ? 'notes / coins' : 'notes';
                                $detailSearch = implode(' ', array_map(static function (array $line): string {
                                    return implode(' ', [$line['denomination'], $line['purchase_rate'], $line['note_count'], $line['amount']]);
                                }, $movement->denominations)) . ' ' . (string)($movement->transaction_notes ?? '') . ' ' . (string)($movement->transaction_status ?? '');
                                ?>
                        <tr data-grid-search="<?= esc($detailSearch) ?>"
                            data-grid-currency="<?= esc($movementCurrency) ?>"
                            data-grid-direction="<?= $directionIn ? 'in' : 'out' ?>"
                            data-grid-date="<?= esc(substr((string)$movement->date_created, 0, 10)) ?>">
                            <td class="text-nowrap">
                                <div class="fw-semibold">#<?= esc((string)($movement->transaction_id ?: $movement->reference_id)) ?></div>
                                <div class="small text-muted"><?= esc($formatVaultDate($movement->date_created)) ?></div>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= esc($activityLabel) ?></div>
                                <?php if (!empty($movement->transaction_status)): ?>
                                <div class="small text-muted"><?= esc((string)$movement->transaction_status) ?></div>
                                <?php endif; ?>
                                <?php if (trim((string)($movement->transaction_notes ?? '')) !== ''): ?>
                                <div class="small text-muted"><?= esc((string)$movement->transaction_notes) ?></div>
                                <?php endif; ?>
                                <?php if ((int)($movement->cashdrawer_id ?? 0) > 0): ?>
                                <div class="small text-muted">
                                    Source cashdrawer #<?= (int)$movement->cashdrawer_id ?>
                                    <?= trim((string)($movement->drawer_user_name ?? '')) !== ''
                                        ? ' · ' . esc((string)$movement->drawer_user_name)
                                        : '' ?>
                                </div>
                                <?php endif; ?>
                                <span
                                    class="vault-activity-direction"><i
                                        class="fa fa-arrow-<?= $directionIn ? 'down' : 'up' ?> text-<?= $directionIn ? 'success' : 'warning' ?>"></i><?= esc($movementCurrency) ?>
                                    · <?= $directionIn ? 'In' : 'Out' ?></span>
                            </td>
                            <td><span
                                    class="badge <?= $movementCurrency === 'CFA' ? 'text-bg-info' : 'text-bg-success' ?>"><?= esc($movementCurrency) ?></span>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= number_format((float)$movement->amount, 2) ?></div>
                                <div class="small text-muted"><?= $movementCount ?> <?= $countUnit ?></div>
                            </td>
                            <td>
                                <div class="small text-muted">Before</div>
                                <div class="fw-semibold"><?= number_format((float)$movement->balance_before, 2) ?></div>
                                <div class="small text-muted mt-1">After <span
                                        class="fw-semibold text-body"><?= number_format((float)$movement->balance_after, 2) ?></span>
                                </div>
                            </td>
                            <td><?= esc(trim((string)($movement->agent_first_name ?? '') . ' ' . (string)($movement->agent_last_name ?? '')) ?: trim((string)$movement->first_name . ' ' . (string)$movement->last_name)) ?>
                            </td>
                            <td class="text-nowrap">
                                <?php if (!empty($movement->transaction_proof_image)): ?>
                                <a class="btn btn-outline-secondary btn-sm" href="<?= esc($vaultImageUrl((string)$movement->transaction_proof_image)) ?>"
                                    target="_blank" rel="noopener">Proof</a>
                                <?php endif; ?>
                                <button class="btn btn-outline-primary btn-sm" type="button"
                                    data-vault-modal-open="statement-denominations"
                                    data-statement-id="<?= esc((string)($movement->transaction_id ?: $movement->reference_id)) ?>"
                                    data-statement-currency="<?= esc($movementCurrency) ?>"
                                    data-statement-denominations="<?= esc(base64_encode(json_encode($movement->denominations, JSON_UNESCAPED_SLASHES))) ?>"
                                    aria-label="View denomination details for transaction #<?= esc((string)($movement->transaction_id ?: $movement->reference_id)) ?>"><i
                                        class="fa fa-list-ul me-1" aria-hidden="true"></i>Details</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="vault-grid-footer d-flex flex-wrap align-items-center justify-content-between gap-2 p-3 border-top"
                data-vault-grid-footer="statement">
                <span class="small text-body-secondary" data-grid-summary></span>
                <div class="d-flex align-items-center gap-2"><label class="small text-body-secondary"
                        for="vaultStatementPageSize">Rows</label><select class="form-select form-select-sm"
                        id="vaultStatementPageSize" data-grid-page-size>
                        <option>10</option>
                        <option selected>25</option>
                        <option>50</option>
                        <option>100</option>
                    </select><button class="btn btn-outline-secondary btn-sm" type="button"
                        data-grid-prev>Previous</button><span class="small text-nowrap" data-grid-page></span><button
                        class="btn btn-outline-secondary btn-sm" type="button" data-grid-next>Next</button></div>
            </div>
        </div>
    </section>
    <section class="vault-detail-pane d-none" data-vault-pane="reconciliation">
        <div class="card mb-3">
            <div class="card-header fw-semibold">Reconciliation history</div>
            <?= $vaultGridControls('reconciliation', true) ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0" data-vault-grid="reconciliation">
                    <thead>
                        <tr>
                            <th>ID / Date</th>
                            <th>Status</th>
                            <th>Staff</th>
                            <th>Proof / notes</th>
                            <th>Review</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$reconciliations): ?><tr>
                            <td colspan="5" class="text-center text-muted py-3">No reconciliation records.</td>
                        </tr><?php endif; ?>
                        <?php foreach ($reconciliations as $reconciliation): ?>
                        <?php $reconciliationCounts = json_decode((string)$reconciliation->counted_stock_json, true); ?>
                        <tr data-grid-status="<?= esc((string)$reconciliation->status) ?>"
                            data-grid-date="<?= esc(substr((string)$reconciliation->date_created, 0, 10)) ?>">
                            <td class="text-nowrap">
                                <div class="fw-semibold">#<?= (int)$reconciliation->id ?></div>
                                <div class="small text-muted"><?= esc($formatVaultDate($reconciliation->date_created)) ?></div>
                            </td>
                            <td><span
                                    class="badge <?= strtolower((string)$reconciliation->status) === 'approved' ? 'text-bg-success' : (strtolower((string)$reconciliation->status) === 'rejected' ? 'text-bg-danger' : 'text-bg-warning') ?>"><?= esc((string)$reconciliation->status) ?></span>
                                <?php if ((string)$reconciliation->status === 'Rejected' && !empty($reconciliation->reject_reason)): ?>
                                <div class="small text-danger mt-1"><?= esc((string)$reconciliation->reject_reason) ?>
                                </div><?php endif; ?>
                            </td>
                            <td><?= esc(trim((string)$reconciliation->first_name . ' ' . (string)$reconciliation->last_name)) ?>
                            </td>
                            <td><?php if (!empty($reconciliation->proof_image)): ?><a
                                    href="<?= esc($vaultImageUrl((string)$reconciliation->proof_image)) ?>"
                                    data-vault-image-open aria-label="View full-size reconciliation photo">View
                                    photo</a><?php else: ?><span class="text-muted">No attachment</span><?php endif; ?>
                                <?php if (trim((string)$reconciliation->notes) !== ''): ?><div class="small text-muted">
                                    <?= esc((string)$reconciliation->notes) ?></div><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($reconciliation->status === 'Pending' && (int)$reconciliation->assigned_approver_id === $userId && \Vaults\canManage()): ?>
                                <button class="btn btn-primary btn-sm" type="button"
                                    data-vault-modal-open="reconciliation-review"
                                    data-reconciliation-id="<?= (int)$reconciliation->id ?>"
                                    data-reconciliation-staff="<?= esc(trim((string)$reconciliation->first_name . ' ' . (string)$reconciliation->last_name)) ?>"
                                    data-reconciliation-date="<?= esc($formatVaultDate($reconciliation->date_created)) ?>"
                                    data-reconciliation-notes="<?= esc((string)$reconciliation->notes) ?>"
                                    data-reconciliation-status="Pending" data-reconciliation-readonly="false"
                                    data-reconciliation-counts="<?= esc(base64_encode(json_encode(is_array($reconciliationCounts) ? $reconciliationCounts : [], JSON_UNESCAPED_SLASHES))) ?>"
                                    data-reconciliation-system="<?= esc(base64_encode(json_encode(array_map(static function ($line): array {
                                                                                return [
                                                                                    'currency' => (string)$line->currency,
                                                                                    'denomination' => (float)$line->denomination,
                                                                                    'purchase_rate' => (float)$line->purchase_rate,
                                                                                    'count' => (int)$line->note_count,
                                                                                ];
                                                                            }, $stock), JSON_UNESCAPED_SLASHES))) ?>">
                                    <i class="fa fa-magnifying-glass-chart me-1"></i>Review &amp; approve
                                </button>
                                <?php elseif ($reconciliation->status === 'Pending'): ?>
                                <span class="small text-muted">Awaiting
                                    <?= esc(trim((string)$reconciliation->approver_first_name . ' ' . (string)$reconciliation->approver_last_name) ?: 'assigned reviewer') ?></span>
                                <?php else: ?>
                                <?php
                                            $historicalSystemStock = [];
                                            $hasHistoricalSnapshot = false;
                                            $snapshotSource = (string)($reconciliation->snapshot_source ?? 'Recorded vault balance snapshot');
                                            foreach (
                                                [
                                                    ['value' => (string)($reconciliation->reviewed_stock_json ?? ''), 'source' => 'Vault balance at review'],
                                                    ['value' => (string)($reconciliation->system_stock_json ?? ''), 'source' => 'Vault balance at submission'],
                                                    ['value' => (string)($reconciliation->legacy_system_stock_json ?? ''), 'source' => $snapshotSource],
                                                ] as $snapshotCandidate
                                            ) {
                                                if (trim($snapshotCandidate['value']) === '') {
                                                    continue;
                                                }
                                                $decodedSnapshot = json_decode($snapshotCandidate['value'], true);
                                                if (is_array($decodedSnapshot)) {
                                                    $historicalSystemStock = $decodedSnapshot;
                                                    $hasHistoricalSnapshot = true;
                                                    $snapshotSource = $snapshotCandidate['source'];
                                                    break;
                                                }
                                            }
                                            if (!is_array($historicalSystemStock) || !$historicalSystemStock) {
                                                $historicalSystemStock = json_decode((string)($reconciliation->legacy_system_stock_json ?? ''), true);
                                            }
                                            ?>
                                <button class="btn btn-outline-secondary btn-sm" type="button"
                                    data-vault-modal-open="reconciliation-review"
                                    data-reconciliation-id="<?= (int)$reconciliation->id ?>"
                                    data-reconciliation-staff="<?= esc(trim((string)$reconciliation->first_name . ' ' . (string)$reconciliation->last_name)) ?>"
                                    data-reconciliation-date="<?= esc($formatVaultDate($reconciliation->date_created)) ?>"
                                    data-reconciliation-notes="<?= esc((string)$reconciliation->notes) ?>"
                                    data-reconciliation-status="<?= esc((string)$reconciliation->status) ?>"
                                    data-reconciliation-readonly="true"
                                    data-reconciliation-source="<?= esc($snapshotSource) ?>"
                                    data-reconciliation-has-snapshot="<?= $hasHistoricalSnapshot ? 'true' : 'false' ?>"
                                    data-reconciliation-counts="<?= esc(base64_encode(json_encode(is_array($reconciliationCounts) ? $reconciliationCounts : [], JSON_UNESCAPED_SLASHES))) ?>"
                                    data-reconciliation-system="<?= esc(base64_encode(json_encode(is_array($historicalSystemStock) ? $historicalSystemStock : [], JSON_UNESCAPED_SLASHES))) ?>"
                                    data-reconciliation-reason="<?= esc((string)$reconciliation->reject_reason) ?>"
                                    data-reconciliation-reviewer="<?= esc(trim((string)$reconciliation->reviewer_first_name . ' ' . (string)$reconciliation->reviewer_last_name)) ?>"
                                    data-reconciliation-reviewed-date="<?= esc($formatVaultDate($reconciliation->reviewed_at ?? '')) ?>">
                                    <i class="fa fa-eye me-1"></i>View
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="vault-grid-footer d-flex flex-wrap align-items-center justify-content-between gap-2 p-3 border-top"
                data-vault-grid-footer="reconciliation">
                <span class="small text-body-secondary" data-grid-summary></span>
                <div class="d-flex align-items-center gap-2"><label class="small text-body-secondary"
                        for="vaultReconciliationPageSize">Rows</label><select class="form-select form-select-sm"
                        id="vaultReconciliationPageSize" data-grid-page-size>
                        <option>10</option>
                        <option selected>25</option>
                        <option>50</option>
                        <option>100</option>
                    </select><button class="btn btn-outline-secondary btn-sm" type="button"
                        data-grid-prev>Previous</button><span class="small text-nowrap" data-grid-page></span><button
                        class="btn btn-outline-secondary btn-sm" type="button" data-grid-next>Next</button></div>
            </div>
        </div>
        <div class="vault-action-modal" data-vault-modal="reconciliation-review" role="dialog" aria-modal="true"
            aria-hidden="true" aria-labelledby="vaultReconciliationReviewTitle">
            <div class="row g-3">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <span class="fw-semibold" id="vaultReconciliationReviewTitle"><i
                                    class="fa fa-scale-balanced text-primary me-2"></i><span data-review-title>Review
                                    vault reconciliation</span></span>
                            <button type="button" class="vault-action-close" data-vault-modal-close
                                aria-label="Close review"><i class="fa fa-times"></i></button>
                        </div>
                        <div class="card-body">
                            <div class="small text-muted mb-3"><span data-review-staff></span> · <span
                                    data-review-date></span>
                                <div data-review-decision class="mt-1"></div>
                                <div data-review-notes class="mt-1"></div>
                                <div class="text-danger mt-1" data-review-reason></div>
                            </div>
                            <div data-review-comparison></div>
                            <form method="post" class="border-top pt-3 mt-3" data-reconciliation-review-form>
                                <?= $csrfField ?>
                                <input type="hidden" name="action" value="review_reconciliation">
                                <input type="hidden" name="reconciliation_id" value="">
                                <input type="hidden" name="vault_id" value="<?= $activeVaultId ?>">
                                <input type="hidden" name="choice" value="" data-reconciliation-choice>
                                <div class="mb-3">
                                    <label class="form-label" for="vaultReconciliationRejectReason">Reason (required to
                                        reject)</label>
                                    <textarea class="form-control" id="vaultReconciliationRejectReason" name="reason"
                                        rows="2"
                                        placeholder="Explain what needs correction before the user resubmits"></textarea>
                                </div>
                                <div class="d-flex justify-content-end gap-2">
                                    <button class="btn btn-outline-secondary" type="button"
                                        data-vault-modal-close>Cancel</button>
                                    <button class="btn btn-outline-danger" type="submit"
                                        data-reconciliation-decision="reject"><i class="fa fa-ban me-1"></i>Reject and
                                        request resubmission</button>
                                    <button class="btn btn-success" type="submit"
                                        data-reconciliation-decision="approve"><i class="fa fa-check me-1"></i>Approve
                                        reconciliation</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if (\Vaults\canManage() && $isOperationsView): ?>
    <?php $creationCfaRate = $currentCfaRate; ?>
    <?php if ($creationCfaRate <= 0): ?>
    <div class="alert alert-warning">The CFA exchange rate is unavailable. GHS vault setup is available, but CFA limits
        and opening CFA cash require a valid exchange rate.</div>
    <?php endif; ?>
    <style>
    #createVaultModal .modal-dialog {
        width: 560px;
        max-width: calc(100vw - 2rem);
        margin: 1rem auto;
    }

    #createVaultModal .modal-content {
        display: flex;
        max-height: calc(100vh - 2rem);
    }

    #createVaultModal .modal-header,
    #createVaultModal .modal-footer {
        flex: 0 0 auto;
    }

    #createVaultModal form {
        display: flex;
        min-height: 0;
        flex: 1 1 auto;
        flex-direction: column;
    }

    #createVaultModal .modal-body {
        min-height: 0;
        overflow-y: auto;
    }

    #createVaultModal {
        z-index: 1055;
    }

    #vaultOpeningCalculator {
        z-index: 1065;
    }

    .vault-opening-calculator-backdrop {
        position: fixed;
        inset: 0;
        z-index: 1060;
        background: rgba(0, 0, 0, .55);
    }

    #vaultOpeningCalculator .modal-dialog {
        width: 560px;
        max-width: calc(100vw - 2rem);
        margin: 1rem auto;
    }

    #vaultOpeningCalculator .modal-content {
        border: 2px solid var(--bs-primary);
        border-radius: .85rem;
        max-height: calc(100vh - 2rem);
        overflow: hidden;
        box-shadow: 0 1rem 3rem rgba(0, 0, 0, .35);
    }

    #vaultOpeningCalculator .modal-header {
        background: var(--bs-primary-bg-subtle);
        border-bottom: 2px solid var(--bs-primary);
    }

    #vaultOpeningCalculator .modal-body {
        overflow-y: auto;
    }

    #vaultAdminCashInModal {
        z-index: 1055;
    }

    #vaultAdminCashInModal .modal-dialog {
        width: 760px;
        max-width: calc(100vw - 2rem);
        margin: 1rem auto;
    }

    #vaultAdminCashInModal .modal-content {
        display: flex;
        max-height: calc(100vh - 2rem);
    }

    #vaultAdminCashInModal .modal-header,
    #vaultAdminCashInModal .modal-footer,
    #vaultAdminCashInCalculator .modal-header,
    #vaultAdminCashInCalculator .modal-footer {
        flex: 0 0 auto;
    }

    #vaultAdminCashInModal form {
        display: flex;
        min-height: 0;
        flex: 1 1 auto;
        flex-direction: column;
    }

    #vaultAdminCashInModal .modal-body {
        min-height: 0;
        overflow-y: auto;
    }

    .vault-admin-cash-in-backdrop {
        position: fixed;
        inset: 0;
        z-index: 1060;
        background: rgba(0, 0, 0, .55);
    }

    #vaultAdminCashInCalculator {
        z-index: 1065;
    }

    #vaultAdminCashInCalculator .modal-dialog {
        width: 720px;
        max-width: calc(100vw - 2rem);
        margin: 1rem auto;
    }

    #vaultAdminCashInCalculator .modal-content {
        border: 2px solid var(--bs-primary);
        border-radius: .85rem;
        max-height: calc(100vh - 2rem);
        overflow: hidden;
        box-shadow: 0 1rem 3rem rgba(0, 0, 0, .35);
    }

    #vaultAdminCashInCalculator .modal-header {
        background: var(--bs-primary-bg-subtle);
        border-bottom: 2px solid var(--bs-primary);
    }

    #vaultAdminCashInCalculator .modal-body {
        overflow-y: auto;
    }

    .vault-admin-cash-in-summary {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem;
        border: 1px solid var(--bs-border-color);
        border-radius: .75rem;
        background: var(--bs-tertiary-bg);
    }

    .vault-admin-cash-in-summary [data-vault-cash-in-volume-total] {
        font-size: 1.15rem;
    }

    .vault-admin-cash-in-table input {
        min-width: 5rem;
    }

    @media(max-width:575px) {
        #vaultAdminCashInModal .modal-dialog,
        #vaultAdminCashInCalculator .modal-dialog {
            width: auto;
            max-width: calc(100vw - 1rem);
            margin: .5rem;
        }

        .vault-admin-cash-in-summary {
            align-items: stretch;
        }

        .vault-admin-cash-in-summary button {
            width: 100%;
        }
    }
    </style>
    <div class="modal fade" id="createVaultModal" tabindex="-1" aria-labelledby="createVaultModalLabel"
        aria-hidden="true" data-open-on-load="<?= !empty($oldPost) ? 'true' : 'false' ?>">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="createVaultModalLabel">Create and assign branch vault</h5>
                        <div class="small text-muted">Set the branch, custodian, limits, and optional opening cash.
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-create-vault-close aria-label="Close"></button>
                </div>
                <form method="post" class="vault-denomination-form" data-opening-form data-vault-limit-form
                    data-cfa-rate="<?= $creationCfaRate ?>">
                    <?= $csrfField ?><input type="hidden" name="action" value="create_vault">
                    <div class="modal-body">
                        <div class="mb-3"><label class="form-label">Vault name</label><input class="form-control"
                                name="name" required maxlength="120" placeholder="e.g. Main Branch Vault"
                                value="<?= esc((string)($oldPost['name'] ?? '')) ?>"></div>
                        <div class="mb-3"><label class="form-label">Branch</label>
                            <select class="form-select" name="branch_id" data-branch-select required>
                                <option value="">Select branch…</option>
                                <?php foreach ($allBranches as $branch): ?><option value="<?= (int)$branch->id ?>"
                                    <?= ((string)($oldPost['branch_id'] ?? '') === (string)$branch->id) ? 'selected' : '' ?>>
                                    <?= esc((string)$branch->name) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3"><label class="form-label">Custodian</label>
                            <select class="form-select" name="custodian_user_id" data-custodian-select required>
                                <option value="">Select staff…</option>
                                <?php foreach ($users as $person): ?><option value="<?= (int)$person->id ?>"
                                    data-branch="<?= (int)($person->branch_id ?? 0) ?>"
                                    <?= ((string)($oldPost['custodian_user_id'] ?? '') === (string)$person->id) ? 'selected' : '' ?>>
                                    <?= esc(trim((string)$person->first_name . ' ' . (string)$person->last_name)) ?>
                                </option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="alert alert-info mb-3">
                            <div class="fw-semibold">How vault limits work</div>
                            <div class="small">GHS and CFA limits are the maximum face-value balances for each currency.
                                The cumulative limit is calculated automatically as GHS limit + CFA limit converted at
                                the current rate. Opening cash must fit within both its currency limit and the
                                cumulative limit. Leave a currency limit blank or enter 0 for no separate cap in that
                                currency; a non-zero cumulative limit still applies.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="createVaultGhsLimit">GHS balance limit</label>
                            <div class="input-group"><span class="input-group-text">GH₵</span><input
                                    id="createVaultGhsLimit" class="form-control" type="number" min="0" step="0.01"
                                    name="limit_ghs" placeholder="No separate limit"
                                    value="<?= esc((string)($oldPost['limit_ghs'] ?? '')) ?>" data-limit-ghs></div>
                            <div class="form-text">Maximum GHS cash allowed in this vault. Blank or 0 means no separate
                                GHS cap.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="createVaultCfaLimit">CFA balance limit</label>
                            <div class="input-group"><input id="createVaultCfaLimit" class="form-control" type="number"
                                    min="0" step="0.01" name="limit_cfa" placeholder="No separate limit"
                                    value="<?= esc((string)($oldPost['limit_cfa'] ?? '')) ?>" data-limit-cfa
                                    <?= ($creationCfaRate <= 0) ? 'disabled' : '' ?>><span
                                    class="input-group-text">CFA</span></div>
                            <div class="form-text">Maximum CFA face value allowed in this vault. Blank or 0 means no
                                separate CFA cap.</div>
                        </div>
                        <div class="mb-3"><label class="form-label">Cumulative GHS-equivalent limit</label>
                            <div class="input-group"><span class="input-group-text">GH₵</span><input
                                    class="form-control" type="number" min="0" step="0.01" name="limit_total_ghs"
                                    placeholder="Calculated automatically"
                                    value="<?= esc((string)($oldPost['limit_total_ghs'] ?? '')) ?>" data-limit-total
                                    readonly></div>
                            <div class="form-text">Combined maximum value of GHS and CFA cash. Recalculated as you enter
                                the two currency limits, using CFA 1,000 = GH₵
                                <?= number_format($creationCfaRate, 2) ?>. A result of 0 means no combined cap.</div>
                        </div>
                        <div class="alert alert-danger py-2 d-none" role="alert" data-opening-limit-feedback></div>
                        <div class="mb-3">
                            <label class="form-label">Opening cash (optional)</label>
                            <div class="input-group">
                                <input type="text" class="form-control" data-opening-display value="GH₵ 0.00 · CFA 0"
                                    readonly>
                                <button type="button" class="btn btn-outline-secondary" data-opening-calculator-open
                                    aria-expanded="false"><i class="fa fa-calculator"></i> Currency notes</button>
                            </div>
                            <input type="hidden" name="ghs_opening_json" data-ghs-opening value="[]">
                            <input type="hidden" name="cfa_opening_json" data-cfa-opening value="[]">
                        </div>
                        <div class="vstack gap-2">
                            <div class="border rounded p-3 vault-opening-summary vault-opening-summary--ghs">
                                <div class="small"><i class="fa fa-money-bill-wave me-1" aria-hidden="true"></i>Opening
                                    GHS</div>
                                <div class="fw-semibold" data-opening-ghs-total>GH₵ 0.00</div>
                                <div class="small text-muted"><span data-opening-ghs-count>0</span> notes and coins
                                </div>
                            </div>
                            <div class="border rounded p-3 vault-opening-summary vault-opening-summary--cfa">
                                <div class="small"><i class="fa fa-coins me-1" aria-hidden="true"></i>Opening CFA</div>
                                <div class="fw-semibold" data-opening-cfa-total>0 CFA</div>
                                <div class="small text-muted"><span data-opening-cfa-count>0</span> notes</div>
                            </div>
                            <div class="border rounded p-3 vault-opening-summary vault-opening-summary--total">
                                <div class="small"><i class="fa fa-chart-line me-1" aria-hidden="true"></i>Total opening
                                    vault value at current rate</div>
                                <div class="fw-semibold" data-opening-total-ghs>GH₵ 0.00</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-create-vault-close>Cancel</button>
                        <button class="btn btn-primary">Create vault</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="vaultOpeningCalculator" tabindex="-1" aria-labelledby="vaultOpeningCalculatorLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <div class="badge text-bg-primary mb-1">Opening balance calculator</div>
                        <h5 class="modal-title" id="vaultOpeningCalculatorLabel"><i class="fa fa-calculator me-1"></i>
                            Currency Notes</h5>
                    </div>
                    <button type="button" class="btn-close" data-opening-calculator-close
                        aria-label="Close calculator"></button>
                </div>
                <div class="modal-body table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Notes</th>
                                <th style="width: 140px">Count</th>
                                <th style="width: 150px">Volume</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="table-active">
                                <th colspan="3">GHS notes</th>
                            </tr>
                            <?php foreach ([200, 100, 50, 20, 10, 5, 2, 1] as $denomination): ?>
                            <tr data-opening-row data-currency="GHS" data-denomination="<?= $denomination ?>">
                                <td>GH₵<?= rtrim(rtrim(number_format($denomination, 2), '0'), '.') ?></td>
                                <td><input type="number" min="0" step="1" inputmode="numeric"
                                        class="form-control form-control-sm" placeholder="Enter count"
                                        value="<?= esc((string)($openingCounts['GHS'][(string)(float)$denomination] ?? '')) ?>"
                                        data-opening-count></td>
                                <td><input type="text" class="form-control form-control-sm" placeholder="Volume"
                                        data-opening-volume readonly></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-secondary">
                                <th colspan="3">GHS coins</th>
                            </tr>
                            <?php foreach ([[0.5, '50p'], [0.2, '20p'], [0.1, '10p']] as [$denomination, $label]): ?>
                            <tr data-opening-row data-currency="GHS" data-denomination="<?= $denomination ?>">
                                <td><?= $label ?></td>
                                <td><input type="number" min="0" step="1" inputmode="numeric"
                                        class="form-control form-control-sm" placeholder="Enter count"
                                        value="<?= esc((string)($openingCounts['GHS'][(string)(float)$denomination] ?? '')) ?>"
                                        data-opening-count></td>
                                <td><input type="text" class="form-control form-control-sm" placeholder="Volume"
                                        data-opening-volume readonly></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-active">
                                <th colspan="3">CFA notes</th>
                            </tr>
                            <?php foreach ([10000, 5000, 2000, 1000, 500, 200, 100, 50, 25, 10, 5] as $denomination): ?>
                            <tr data-opening-row data-currency="CFA" data-denomination="<?= $denomination ?>">
                                <td><?= number_format($denomination) ?> CFA</td>
                                <td><input type="number" min="0" step="1" inputmode="numeric"
                                        class="form-control form-control-sm" placeholder="Enter count"
                                        value="<?= esc((string)($openingCounts['CFA'][(string)(float)$denomination] ?? '')) ?>"
                                        data-opening-count <?= ($creationCfaRate <= 0) ? 'disabled' : '' ?>></td>
                                <td><input type="text" class="form-control form-control-sm" placeholder="Volume"
                                        data-opening-volume readonly></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>GHS total</th>
                                <td><input type="text" class="form-control form-control-sm" data-calculator-ghs-count
                                        readonly></td>
                                <td><input type="text" class="form-control form-control-sm" data-calculator-ghs-total
                                        readonly></td>
                            </tr>
                            <tr>
                                <th>CFA total</th>
                                <td><input type="text" class="form-control form-control-sm" data-calculator-cfa-count
                                        readonly></td>
                                <td><input type="text" class="form-control form-control-sm" data-calculator-cfa-total
                                        readonly></td>
                            </tr>
                            <tr>
                                <th colspan="2">Total vault value in GHS</th>
                                <td><input type="text" class="form-control form-control-sm" data-calculator-total-ghs
                                        readonly></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" data-opening-reset>Reset</button>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary" data-opening-calculator-close>Close</button>
                        <button type="button" class="btn btn-primary" data-opening-apply>Apply denominations</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>
</div>
<div class="vault-action-modal" data-vault-modal="statement-denominations" role="dialog" aria-modal="true"
    aria-hidden="true" aria-labelledby="vaultStatementDenominationsTitle">
    <div class="row g-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <span class="fw-semibold" id="vaultStatementDenominationsTitle"><i
                            class="fa fa-list-ul text-primary me-2"></i>Transaction denomination details · <span
                            data-statement-detail-id></span></span>
                    <button type="button" class="vault-action-close" data-vault-modal-close
                        aria-label="Close denomination details"><i class="fa fa-times"></i></button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 vault-denomination-detail-table">
                            <thead>
                                <tr>
                                    <th>Currency</th>
                                    <th>Denomination</th>
                                    <th>Purchased rate</th>
                                    <th>Count</th>
                                    <th>Face value</th>
                                </tr>
                            </thead>
                            <tbody data-statement-detail-rows></tbody>
                            <tfoot>
                                <tr class="fw-semibold">
                                    <td colspan="3">Transaction total</td>
                                    <td data-statement-detail-count></td>
                                    <td data-statement-detail-total></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="vault-image-viewer" data-vault-image-viewer role="dialog" aria-modal="true" aria-hidden="true"
    aria-label="Image preview">
    <button class="vault-image-viewer__close" type="button" data-vault-image-close aria-label="Close image preview"><i
            class="fa fa-times"></i></button>
    <div class="vault-image-viewer__caption" data-vault-image-caption></div>
    <img class="vault-image-viewer__image" data-vault-image-full alt="Full-size attachment preview">
</div>

<script>
document.querySelectorAll('[data-vault-limit-form]').forEach(function(form) {
    const rate = Number(form.dataset.cfaRate || 0);
    const ghs = form.querySelector('[data-limit-ghs]');
    const cfa = form.querySelector('[data-limit-cfa]');
    const total = form.querySelector('[data-limit-total]');

    function refresh() {
        total.value = (Math.max(0, Number(ghs.value || 0)) + Math.max(0, Number(cfa.value || 0)) * rate / 1000)
            .toFixed(2);
    }
    ghs.addEventListener('input', refresh);
    cfa.addEventListener('input', refresh);
    form.addEventListener('submit', refresh);
    refresh();
});

const vaultList = document.querySelector('[data-vault-list]');
if (vaultList) {
    const vaultRows = Array.from(vaultList.querySelectorAll('[data-vault-row]'));
    const search = document.querySelector('[data-vault-search]');
    const branchFilter = document.querySelector('[data-vault-branch-filter]');
    const currencyFilter = document.querySelector('[data-vault-currency-filter]');
    const pageSize = document.querySelector('[data-vault-page-size]');
    const summary = document.querySelector('[data-vault-page-summary]');
    const pageNumber = document.querySelector('[data-vault-page-number]');
    const previous = document.querySelector('[data-vault-prev]');
    const next = document.querySelector('[data-vault-next]');
    let currentPage = 1;

    function renderVaultPage() {
        const term = search.value.trim().toLowerCase();
        const branch = branchFilter.value;
        const currency = currencyFilter.value;
        const matching = vaultRows.filter(function(row) {
            const matchesText = !term || row.dataset.search.includes(term);
            const matchesBranch = !branch || row.dataset.branch === branch;
            const matchesCurrency = !currency || row.getAttribute('data-has-' + currency) === '1';
            return matchesText && matchesBranch && matchesCurrency;
        });
        const perPage = Number(pageSize.value) || 10;
        const pageCount = Math.max(1, Math.ceil(matching.length / perPage));
        currentPage = Math.min(currentPage, pageCount);
        const startIndex = (currentPage - 1) * perPage;
        vaultRows.forEach(function(row) {
            row.classList.add('d-none');
        });
        matching.slice(startIndex, startIndex + perPage).forEach(function(row) {
            row.classList.remove('d-none');
        });
        let emptyRow = vaultList.querySelector('[data-vault-filter-empty]');
        if (!matching.length && !emptyRow) {
            emptyRow = document.createElement('tr');
            emptyRow.dataset.vaultFilterEmpty = 'true';
            emptyRow.innerHTML =
                '<td colspan="6" class="text-center text-muted py-4">No vaults match these filters.</td>';
            vaultList.querySelector('tbody').appendChild(emptyRow);
        } else if (matching.length && emptyRow) {
            emptyRow.remove();
        }
        summary.textContent = matching.length ?
            'Showing ' + (startIndex + 1) + '–' + Math.min(startIndex + perPage, matching.length) + ' of ' + matching
            .length + ' vaults' :
            'No matching vaults';
        pageNumber.textContent = 'Page ' + currentPage + ' of ' + pageCount;
        previous.disabled = currentPage <= 1;
        next.disabled = currentPage >= pageCount;
    }
    [search, branchFilter, currencyFilter].forEach(function(control) {
        control.addEventListener('input', function() {
            currentPage = 1;
            renderVaultPage();
        });
        control.addEventListener('change', function() {
            currentPage = 1;
            renderVaultPage();
        });
    });
    pageSize.addEventListener('change', function() {
        currentPage = 1;
        renderVaultPage();
    });
    previous.addEventListener('click', function() {
        currentPage -= 1;
        renderVaultPage();
    });
    next.addEventListener('click', function() {
        currentPage += 1;
        renderVaultPage();
    });
    renderVaultPage();
}

document.querySelectorAll('[data-vault-grid]').forEach(function(table) {
    const grid = table.dataset.vaultGrid;
    const tbody = table.tBodies[0];
    const controls = document.querySelector('[data-vault-grid-controls="' + grid + '"]');
    const footer = document.querySelector('[data-vault-grid-footer="' + grid + '"]');
    if (!tbody || !controls || !footer) return;

    const rows = Array.from(tbody.rows).filter(function(row) {
        return !row.querySelector('[colspan]');
    });
    const filters = {};
    controls.querySelectorAll('[data-grid-filter]').forEach(function(input) {
        filters[input.dataset.gridFilter] = input;
    });
    if (filters.status) {
        const statuses = Array.from(new Set(rows.map(function(row) {
            return row.dataset.gridStatus || '';
        }).filter(Boolean))).sort(function(left, right) {
            return left.localeCompare(right);
        });
        statuses.forEach(function(status) {
            const option = document.createElement('option');
            option.value = status;
            option.textContent = status;
            filters.status.appendChild(option);
        });
    }
    const pageSize = footer.querySelector('[data-grid-page-size]');
    const summary = footer.querySelector('[data-grid-summary]');
    const pageLabel = footer.querySelector('[data-grid-page]');
    const previous = footer.querySelector('[data-grid-prev]');
    const next = footer.querySelector('[data-grid-next]');
    const exportLinks = grid === 'statement' ? Array.from(document.querySelectorAll('[data-app-export]')) : [];
    let currentPage = 1;
    let filteredEmptyRow = null;

    function selectedValue(key) {
        return filters[key] ? filters[key].value.trim() : '';
    }

    function updateExportLinks() {
        if (grid !== 'statement') return;
        exportLinks.forEach(function(link) {
            const url = new URL(link.href, window.location.href);
            ['search', 'direction', 'currency', 'date-from', 'date-to'].forEach(function(key) {
                const param = key === 'date-from' ? 'date_from' : (key === 'date-to' ?
                    'date_to' : key);
                const value = selectedValue(key);
                if (value) url.searchParams.set(param, value);
                else url.searchParams.delete(param);
            });
            link.href = url.toString();
        });
    }

    function renderGrid() {
        const term = selectedValue('search').toLocaleLowerCase();
        const status = selectedValue('status').toLocaleLowerCase();
        const currency = selectedValue('currency').toLocaleUpperCase();
        const direction = selectedValue('direction').toLocaleLowerCase();
        const dateFrom = selectedValue('date-from');
        const dateTo = selectedValue('date-to');
        const matching = rows.filter(function(row) {
            const rowDate = row.dataset.gridDate || '';
            const rowText = (row.textContent + ' ' + (row.dataset.gridSearch || ''))
                .toLocaleLowerCase();
            return (!term || rowText.includes(term)) &&
                (!status || (row.dataset.gridStatus || '').toLocaleLowerCase() === status) &&
                (!currency || (row.dataset.gridCurrency || '').toLocaleUpperCase() === currency) &&
                (!direction || (row.dataset.gridDirection || '').toLocaleLowerCase() === direction) &&
                (!dateFrom || !rowDate || rowDate >= dateFrom) &&
                (!dateTo || !rowDate || rowDate <= dateTo);
        });
        const perPage = Math.max(1, Number(pageSize.value) || 25);
        const pageCount = Math.max(1, Math.ceil(matching.length / perPage));
        currentPage = Math.min(currentPage, pageCount);
        const startIndex = matching.length ? (currentPage - 1) * perPage : 0;

        rows.forEach(function(row) {
            row.classList.add('d-none');
        });
        matching.slice(startIndex, startIndex + perPage).forEach(function(row) {
            row.classList.remove('d-none');
        });

        if (!matching.length && rows.length) {
            if (!filteredEmptyRow) {
                filteredEmptyRow = document.createElement('tr');
                filteredEmptyRow.innerHTML = '<td colspan="' + table.tHead.rows[0].cells.length +
                    '" class="text-center text-body-secondary py-4">No records match these filters.</td>';
                tbody.appendChild(filteredEmptyRow);
            }
        } else if (filteredEmptyRow) {
            filteredEmptyRow.remove();
            filteredEmptyRow = null;
        }

        summary.textContent = matching.length ?
            'Showing ' + (startIndex + 1) + '–' + Math.min(startIndex + perPage, matching.length) + ' of ' +
            matching.length + ' records' :
            (rows.length ? 'No matching records' : 'No records');
        pageLabel.textContent = 'Page ' + currentPage + ' of ' + pageCount;
        previous.disabled = currentPage <= 1;
        next.disabled = currentPage >= pageCount;
        updateExportLinks();
    }

    Object.keys(filters).forEach(function(key) {
        filters[key].addEventListener('input', function() {
            currentPage = 1;
            renderGrid();
        });
        filters[key].addEventListener('change', function() {
            currentPage = 1;
            renderGrid();
        });
    });
    pageSize.addEventListener('change', function() {
        currentPage = 1;
        renderGrid();
    });
    previous.addEventListener('click', function() {
        currentPage--;
        renderGrid();
    });
    next.addEventListener('click', function() {
        currentPage++;
        renderGrid();
    });
    renderGrid();
});

const vaultTabs = Array.from(document.querySelectorAll('[data-vault-tab]'));
const vaultPanes = Array.from(document.querySelectorAll('[data-vault-pane]'));
const vaultTabList = document.querySelector('[data-vault-tab-list]');
const vaultTabStorageKey = vaultTabList ?
    'vault-active-tab:' + window.location.pathname + ':' + vaultTabList.dataset.vaultTabList :
    '';

function activateVaultTab(tabName) {
    if (!vaultTabs.some(function(tab) {
            return tab.dataset.vaultTab === tabName;
        })) return;
    vaultTabs.forEach(function(tab) {
        const active = tab.dataset.vaultTab === tabName;
        tab.classList.toggle('active', active);
        tab.setAttribute('aria-selected', String(active));
    });
    vaultPanes.forEach(function(pane) {
        pane.classList.toggle('d-none', pane.dataset.vaultPane !== tabName);
    });
    if (vaultTabStorageKey) {
        try {
            window.localStorage.setItem(vaultTabStorageKey, tabName);
        } catch (error) {
            /* Tab selection still works when storage is unavailable. */
        }
    }
}
vaultTabs.forEach(function(tab) {
    tab.addEventListener('click', function() {
        activateVaultTab(tab.dataset.vaultTab);
    });
});
if (vaultTabStorageKey) {
    try {
        const savedTab = window.localStorage.getItem(vaultTabStorageKey);
        if (savedTab && vaultTabs.some(function(tab) {
                return tab.dataset.vaultTab === savedTab;
            })) {
            activateVaultTab(savedTab);
        }
    } catch (error) {
        /* Keep the default tab when storage is unavailable. */
    }
}
document.querySelectorAll('[data-vault-tab-action]').forEach(function(button) {
    button.addEventListener('click', function() {
        activateVaultTab(button.dataset.vaultTabAction);
        const target = document.querySelector('[data-vault-pane="' + button.dataset.vaultTabAction +
            '"]');
        if (target) target.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    });
});

let activeVaultModal = null;
let activeVaultModalTrigger = null;
let closeDenominationCalculator = function() {};

function closeVaultModal() {
    if (!activeVaultModal) return;
    if (denominationCalculator && denominationCalculator.classList.contains('is-open')) closeDenominationCalculator();
    activeVaultModal.classList.remove('is-open');
    activeVaultModal.setAttribute('aria-hidden', 'true');
    activeVaultModal = null;
    document.body.classList.remove('modal-open');
    if (activeVaultModalTrigger) activeVaultModalTrigger.focus();
}

function populateStatementDenominations(trigger, modal) {
    const rows = modal.querySelector('[data-statement-detail-rows]');
    rows.replaceChildren();
    let totalCount = 0;
    let totalAmount = 0;
    let denominations = [];
    try {
        denominations = JSON.parse(atob(trigger.dataset.statementDenominations || '')) || [];
    } catch (error) {
        denominations = [];
    }
    const format = function(value, decimals) {
        return Number(value || 0).toLocaleString(undefined, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    };
    denominations.forEach(function(line) {
        const currency = String(line.currency || trigger.dataset.statementCurrency || '').toUpperCase();
        const denomination = Number(line.denomination || 0);
        const count = Number(line.note_count || 0);
        const amount = Number(line.amount || denomination * count);
        const row = document.createElement('tr');
        const values = [
            currency,
            format(denomination, currency === 'CFA' ? 0 : 2),
            currency === 'CFA' ? 'CFA 1,000 = GHS ' + format(line.purchase_rate, 2) : '—',
            count.toLocaleString() + ' ' + (currency === 'GHS' && denomination < 1 ? 'coins' : 'notes'),
            format(amount, 2)
        ];
        values.forEach(function(value, index) {
            const cell = document.createElement('td');
            if (index === 0) {
                const badge = document.createElement('span');
                badge.className = 'badge ' + (currency === 'CFA' ? 'text-bg-info' : 'text-bg-success');
                badge.textContent = currency;
                cell.appendChild(badge);
            } else {
                cell.textContent = value;
            }
            row.appendChild(cell);
        });
        rows.appendChild(row);
        totalCount += count;
        totalAmount += amount;
    });
    modal.querySelector('[data-statement-detail-id]').textContent = '#' + (trigger.dataset.statementId || '');
    modal.querySelector('[data-statement-detail-count]').textContent = totalCount.toLocaleString();
    modal.querySelector('[data-statement-detail-total]').textContent = format(totalAmount, 2) + ' ' + (trigger.dataset
        .statementCurrency || '');
}
document.querySelectorAll('[data-vault-modal-open]').forEach(function(button) {
    button.addEventListener('click', function() {
        const modal = document.querySelector('[data-vault-modal="' + button.dataset.vaultModalOpen +
            '"]');
        if (!modal) return;
        if (button.dataset.vaultModalOpen === 'reconciliation-review') {
            populateReconciliationReview(button, modal);
        }
        if (button.dataset.vaultModalOpen === 'transaction-reject') {
            const rejectForm = modal.querySelector('[data-vault-transaction-reject-form]');
            rejectForm.querySelector('[name="action"]').value = button.dataset.rejectAction || '';
            rejectForm.querySelector('[name="transaction_id"]').value = button.dataset
                .rejectTransaction || '';
            rejectForm.querySelector('[name="reason"]').value = '';
            modal.querySelector('[data-transaction-reject-title]').textContent =
                button.dataset.rejectTitle || 'Reject transaction';
        }
        if (button.dataset.vaultModalOpen === 'statement-denominations') {
            populateStatementDenominations(button, modal);
        }
        activeVaultModalTrigger = button;
        activeVaultModal = modal;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        const focusable = modal.querySelector(
            'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled])'
        );
        if (focusable) focusable.focus();
    });
});

function populateReconciliationReview(trigger, modal) {
    const decodeRows = function(value) {
        try {
            const decoded = atob(value || '');
            const parsed = JSON.parse(decodeURIComponent(Array.from(decoded, function(char) {
                return '%' + char.charCodeAt(0).toString(16).padStart(2, '0');
            }).join('')));
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    };
    const escapeHtml = function(value) {
        return String(value ?? '').replace(/[&<>"']/g, function(char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            } [char];
        });
    };
    const formatMoney = function(value) {
        return Number(value || 0).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    };
    const systemRows = decodeRows(trigger.dataset.reconciliationSystem);
    const countedRows = decodeRows(trigger.dataset.reconciliationCounts);
    const status = trigger.dataset.reconciliationStatus || 'Pending';
    const readOnly = trigger.dataset.reconciliationReadonly === 'true';
    const snapshotSource = trigger.dataset.reconciliationSource || '';
    const mapRows = function(rows) {
        const map = new Map();
        rows.forEach(function(row) {
            const currency = String(row.currency || 'GHS').toUpperCase();
            const denomination = Number(row.denomination || 0);
            const rate = currency === 'CFA' ? Number(row.purchase_rate || 0) : 1;
            const key = currency + '|' + denomination.toFixed(2) + '|' + rate.toFixed(6);
            const current = map.get(key) || {
                currency: currency,
                denomination: denomination,
                purchase_rate: rate,
                count: 0
            };
            current.count += Number(row.count || 0);
            map.set(key, current);
        });
        return map;
    };
    const systemMap = mapRows(systemRows);
    const countedMap = mapRows(countedRows);
    const keys = Array.from(new Set(Array.from(systemMap.keys()).concat(Array.from(countedMap.keys()))));
    keys.sort(function(a, b) {
        const left = a.split('|');
        const right = b.split('|');
        if (left[0] !== right[0]) return left[0] === 'GHS' ? -1 : 1;
        if (Number(left[2]) !== Number(right[2])) return Number(left[2]) - Number(right[2]);
        return Number(right[1]) - Number(left[1]);
    });
    const totals = {
        GHS: {
            system: 0,
            counted: 0
        },
        CFA: {
            system: 0,
            counted: 0
        }
    };
    const rowsHtml = keys.map(function(key) {
        const system = systemMap.get(key) || countedMap.get(key);
        const counted = countedMap.get(key) || systemMap.get(key);
        const systemCount = systemMap.has(key) ? systemMap.get(key).count : 0;
        const countedCount = countedMap.has(key) ? countedMap.get(key).count : 0;
        const volumeSystem = Number(system.denomination) * systemCount;
        const volumeCounted = Number(system.denomination) * countedCount;
        const countDiff = countedCount - systemCount;
        const volumeDiff = volumeCounted - volumeSystem;
        const currency = system.currency;
        totals[currency].system += volumeSystem;
        totals[currency].counted += volumeCounted;
        const isDifferent = countDiff !== 0;
        const unit = currency === 'GHS' && Number(system.denomination) < 1 ? 'coin' : 'note';
        const denomLabel = currency === 'CFA' ?
            Number(system.denomination).toLocaleString() + ' CFA' :
            'GH₵ ' + Number(system.denomination).toFixed(2);
        const rateLabel = currency === 'CFA' ?
            '<div class="small text-muted">CFA 1,000 <i class="fa fa-arrow-right mx-1"></i> GH₵ ' + formatMoney(
                system.purchase_rate) + '</div>' :
            '';
        const diffClass = !isDifferent ? 'text-success' : (countDiff < 0 ? 'text-danger' :
            'text-warning-emphasis');
        const diffText = countDiff > 0 ? '+' + countDiff : String(countDiff);
        const badge = !isDifferent ?
            '<span class="badge text-bg-success">Match</span>' :
            '<span class="badge ' + (countDiff < 0 ? 'text-bg-danger' : 'text-bg-warning') + '">' + (countDiff <
                0 ? 'Shortage' : 'Overage') + '</span>';
        return '<tr class="' + (isDifferent ? 'table-warning' : '') + '">' +
            '<td><div class="fw-semibold">' + escapeHtml(denomLabel) + '</div>' + rateLabel + '</td>' +
            '<td class="text-end">' + escapeHtml(systemCount + ' ' + unit + (systemCount === 1 ? '' : 's')) +
            '<div class="small text-muted">' + formatMoney(volumeSystem) + ' ' + currency + '</div></td>' +
            '<td class="text-end">' + escapeHtml(countedCount + ' ' + unit + (countedCount === 1 ? '' : 's')) +
            '<div class="small text-muted">' + formatMoney(volumeCounted) + ' ' + currency + '</div></td>' +
            '<td class="text-end ' + diffClass + '">' + escapeHtml(diffText) + '<div class="small">' + (
                volumeDiff > 0 ? '+' : '') + formatMoney(volumeDiff) + ' ' + currency + '</div></td>' +
            '<td class="text-end">' + badge + '</td></tr>';
    }).join('');
    const summary = ['GHS', 'CFA'].map(function(currency) {
        const variance = totals[currency].counted - totals[currency].system;
        const status = Math.abs(variance) < 0.01 ? 'Balanced' : (variance < 0 ? 'Shortage' : 'Overage');
        const statusClass = status === 'Balanced' ? 'text-bg-success' : (variance < 0 ? 'text-bg-danger' :
            'text-bg-warning');
        return '<div class="col-6"><div class="border rounded p-2 h-100"><div class="small text-muted">' +
            currency + ' cash</div>' +
            '<div class="fw-semibold">System ' + formatMoney(totals[currency].system) + ' · Counted ' +
            formatMoney(totals[currency].counted) + '</div>' +
            '<div class="d-flex justify-content-between align-items-center mt-1"><span class="small">Variance: ' +
            (variance > 0 ? '+' : '') + formatMoney(variance) + ' ' + currency + '</span><span class="badge ' +
            statusClass + '">' + status + '</span></div></div></div>';
    }).join('');
    const comparison = modal.querySelector('[data-review-comparison]');
    const hasSystemSnapshot = !readOnly || trigger.dataset.reconciliationHasSnapshot === 'true';
    const comparisonTable = hasSystemSnapshot ?
        '<div class="row g-2 mb-3">' + summary + '</div>' +
        (readOnly && snapshotSource && snapshotSource !== 'Recorded vault balance snapshot' ?
            '<div class="small text-muted mb-2">' + escapeHtml(snapshotSource) + '.</div>' :
            '') +
        '<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">' +
        '<thead><tr><th>Denomination</th><th class="text-end">' + (readOnly ? 'Balance at review' :
            'Current vault') +
        '</th><th class="text-end">Submitted count</th><th class="text-end">Variance</th><th class="text-end">Result</th></tr></thead>' +
        '<tbody>' + (rowsHtml ||
            '<tr><td colspan="5" class="text-muted text-center">No denomination counts were submitted.</td></tr>') +
        '</tbody></table></div>' +
        '<div class="small text-muted mt-2">A positive variance is an overage; a negative variance is a shortage.' + (
            readOnly ? '' : ' Approving applies the submitted denomination counts to the live vault stock.') +
        '</div>' :
        '<div class="alert alert-warning">The system balance for this reconciliation is unavailable. Submitted denomination counts are shown below without calculating a variance.</div>' +
        '<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Submitted denomination</th><th class="text-end">Count</th><th class="text-end">Volume</th></tr></thead><tbody>' +
        countedRows.map(function(row) {
            const currency = String(row.currency || 'GHS').toUpperCase();
            const denomination = Number(row.denomination || 0);
            const count = Number(row.count || 0);
            const name = currency === 'CFA' ? denomination.toLocaleString() + ' CFA' : 'GH₵ ' + denomination
                .toFixed(2);
            return '<tr><td>' + escapeHtml(name) + '</td><td class="text-end">' + count +
                '</td><td class="text-end">' + formatMoney(denomination * count) + ' ' + currency + '</td></tr>';
        }).join('') + '</tbody></table></div>';
    comparison.innerHTML = comparisonTable;
    modal.querySelector('[data-review-staff]').textContent = trigger.dataset.reconciliationStaff || 'Staff member';
    modal.querySelector('[data-review-date]').textContent = trigger.dataset.reconciliationDate || '';
    modal.querySelector('[data-review-notes]').textContent = trigger.dataset.reconciliationNotes || '';
    modal.querySelector('[data-review-title]').textContent = readOnly ? 'Vault reconciliation · ' + status :
        'Review vault reconciliation';
    const decision = modal.querySelector('[data-review-decision]');
    decision.textContent = readOnly ?
        status + (trigger.dataset.reconciliationReviewer ? ' by ' + trigger.dataset.reconciliationReviewer : '') +
        (trigger.dataset.reconciliationReviewedDate ? ' · ' + trigger.dataset.reconciliationReviewedDate : '') :
        'Awaiting reviewer decision';
    modal.querySelector('[data-review-reason]').textContent = trigger.dataset.reconciliationReason ?
        'Rejection reason: ' + trigger.dataset.reconciliationReason :
        '';
    modal.querySelector('[name="reconciliation_id"]').value = trigger.dataset.reconciliationId || '';
    modal.querySelector('[name="reason"]').value = '';
    modal.querySelector('[data-reconciliation-review-form]').classList.toggle('d-none', readOnly);
    modal.querySelector('[data-review-notes]').classList.toggle('d-none', !(trigger.dataset.reconciliationNotes ||
        trigger.dataset.reconciliationReason));
}
document.querySelectorAll('[data-vault-modal-close]').forEach(function(button) {
    button.addEventListener('click', closeVaultModal);
});
document.querySelectorAll('[data-vault-modal]').forEach(function(modal) {
    modal.addEventListener('click', function(event) {
        if (event.target === modal) closeVaultModal();
    });
});
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && denominationCalculator && denominationCalculator.classList.contains(
            'is-open')) {
        closeDenominationCalculator();
    } else if (event.key === 'Escape' && activeVaultModal) {
        closeVaultModal();
    }
});

const denominationCalculator = document.querySelector('[data-vault-denomination-calculator]');
if (denominationCalculator) {
    const calculatorRows = denominationCalculator.querySelector('[data-calculator-rows]');
    const calculatorCountTotal = denominationCalculator.querySelector('[data-calculator-count-total]');
    const calculatorVolumeTotal = denominationCalculator.querySelector('[data-calculator-volume-total]');
    const calculatorSubtitle = denominationCalculator.querySelector('[data-calculator-subtitle]');
    const ghsDenominations = [200, 100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1];
    const cfaDenominations = [10000, 5000, 2000, 1000, 500, 200, 100, 50, 25, 10, 5];
    let calculatorMode = '';
    let calculatorForm = null;
    let calculatorSet = 'out';
    let calculatorTrigger = null;
    let calculatorStock = [];

    function formatCalculatorMoney(value) {
        return Number(value || 0).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function getRequestAvailability(form) {
        const type = form.querySelector('[data-transaction-type]').value;
        const sourceId = Number(form.querySelector('[data-source-vault]').value || 0);
        const currency = form.querySelector('[data-currency]').value;
        if (type === 'from_agent') {
            let rawDrawerCounts = {};
            try {
                const allCounts = JSON.parse(form.dataset.userDrawerCounts || '{}');
                rawDrawerCounts = allCounts[currency] || {};
            } catch (error) {
                rawDrawerCounts = {};
            }
            const denominations = currency === 'CFA' ? cfaDenominations : ghsDenominations;
            const drawerCounts = {};
            Object.keys(rawDrawerCounts).forEach(function(key) {
                drawerCounts[Number(key).toFixed(2)] = Math.max(0, Number(rawDrawerCounts[key] || 0));
            });
            denominations.forEach(function(denomination) {
                const key = denomination.toFixed(2);
                if (!Object.prototype.hasOwnProperty.call(drawerCounts, key)) drawerCounts[key] = 0;
            });
            return {
                source: 'cashdrawer',
                counts: drawerCounts,
                rows: denominations.map(function(denomination) {
                    return {
                        denomination: denomination,
                        purchase_rate: 1,
                        count: Number(drawerCounts[denomination.toFixed(2)] || 0)
                    };
                })
            };
        }
        if (!sourceId || !['to_agent', 'interbranch', 'denos_exchange'].includes(type)) return null;
        const rateSelect = form.querySelector('[data-exchange-rate]');
        const selectedRate = type === 'denos_exchange' && currency === 'CFA' && rateSelect ?
            Number(rateSelect.value || 0) :
            null;
        let stock = [];
        try {
            stock = JSON.parse(form.dataset.vaultStock || '[]');
        } catch (error) {
            stock = [];
        }
        const counts = {};
        stock.forEach(function(line) {
            if (Number(line.vault_id) !== sourceId || line.currency !== currency) return;
            if (selectedRate !== null && (selectedRate <= 0 || Math.abs(Number(line.purchase_rate) -
                    selectedRate) > 0.000001)) return;
            const key = Number(line.denomination).toFixed(2);
            counts[key] = (counts[key] || 0) + Number(line.count || 0);
        });
        const denominations = currency === 'CFA' ? cfaDenominations : ghsDenominations;
        return {
            source: 'vault',
            counts: counts,
            rows: denominations.map(function(denomination) {
                return {
                    denomination: denomination,
                    purchase_rate: selectedRate || 1,
                    count: counts[denomination.toFixed(2)] || 0
                };
            })
        };
    }

    function requestShortageMessage(currency, shortages, source) {
        const details = shortages.map(function(line) {
            const label = currency === 'GHS' ?
                'GH₵ ' + Number(line.denomination).toFixed(2) :
                Number(line.denomination).toLocaleString() + ' CFA';
            const unit = currency === 'GHS' && Number(line.denomination) < 1 ? 'coins' : 'notes';
            return label + ' ' + unit + ': requested ' + Number(line.requested).toLocaleString() +
                ', available ' + Number(line.available).toLocaleString() +
                ', short ' + Number(line.shortage).toLocaleString();
        });
        const sourceName = source === 'cashdrawer' ? 'your cashdrawer' : 'source vault';
        return 'Insufficient ' + sourceName + ' denominations. ' + details.join('; ') + '.';
    }
    closeDenominationCalculator = function() {
        denominationCalculator.classList.remove('is-open');
        denominationCalculator.setAttribute('aria-hidden', 'true');
        if (calculatorTrigger) calculatorTrigger.focus();
        calculatorTrigger = null;
    };

    function calculatorLineData() {
        const lines = [];
        const shortages = [];
        let countTotal = 0;
        let noteCount = 0;
        let coinCount = 0;
        let volumeTotal = 0;
        calculatorRows.querySelectorAll('[data-calculator-row]').forEach(function(row) {
            const denomination = Number(row.dataset.denomination);
            const countInput = row.querySelector('[data-calculator-count]');
            const count = Math.max(0, Math.floor(Number(countInput.value || 0)));
            countInput.value = countInput.value === '' ? '' : String(count);
            const volume = denomination * count;
            row.querySelector('[data-calculator-volume]').value = count > 0 ? formatCalculatorMoney(volume) :
                '';
            const available = row.dataset.availableCount === '' ? null : Number(row.dataset.availableCount);
            const isShort = calculatorMode === 'request' && available !== null && count > available;
            row.classList.toggle('table-danger', isShort);
            const rowAvailability = row.querySelector('[data-calculator-availability]');
            if (rowAvailability) rowAvailability.classList.toggle('text-danger', isShort);
            if (isShort) {
                shortages.push({
                    denomination: denomination,
                    requested: count,
                    available: available,
                    shortage: count - available
                });
            }
            countTotal += count;
            if (row.dataset.currency === 'GHS' && denomination < 1) coinCount += count;
            else noteCount += count;
            volumeTotal += volume;
            if (calculatorMode === 'request' && count > 0) {
                lines.push({
                    denomination: denomination,
                    count: count
                });
            } else if (calculatorMode === 'reconciliation') {
                const original = calculatorStock.find(function(line) {
                    return Math.abs(Number(line.denomination) - denomination) < 0.001;
                });
                lines.push({
                    currency: row.dataset.currency,
                    denomination: denomination,
                    purchase_rate: Number(original ? original.purchase_rate : row.dataset.rate),
                    count: count
                });
            }
        });
        calculatorCountTotal.value = String(countTotal);
        calculatorVolumeTotal.value = formatCalculatorMoney(volumeTotal);
        const shortageNotice = denominationCalculator.querySelector('[data-calculator-shortage]');
        if (shortageNotice) {
            shortageNotice.textContent = shortages.length ?
                requestShortageMessage(
                    calculatorRows.dataset.currency || 'GHS',
                    shortages,
                    calculatorRows.dataset.availabilitySource || ''
                ) :
                '';
            shortageNotice.classList.toggle('d-none', shortages.length === 0);
        }
        return {
            lines: lines,
            count: countTotal,
            notes: noteCount,
            coins: coinCount,
            volume: volumeTotal,
            shortages: shortages
        };
    }

    function buildCalculatorRows(currency, presetCounts, stockRows, availableCounts) {
        calculatorRows.replaceChildren();
        const lines = stockRows || (currency === 'CFA' ?
            cfaDenominations.map(function(value) {
                return {
                    currency: 'CFA',
                    denomination: value,
                    purchase_rate: 1,
                    count: 0
                };
            }) :
            ghsDenominations.map(function(value) {
                return {
                    currency: 'GHS',
                    denomination: value,
                    purchase_rate: 1,
                    count: 0
                };
            }));
        lines.forEach(function(line) {
            const denomination = Number(line.denomination);
            const tr = document.createElement('tr');
            tr.dataset.calculatorRow = 'true';
            tr.dataset.denomination = String(denomination);
            tr.dataset.currency = currency;
            tr.dataset.rate = String(Number(line.purchase_rate || 1));
            const denomLabel = currency === 'CFA' ?
                Number(denomination).toLocaleString() + ' CFA notes' :
                (denomination < 1 ? 'GH₵ ' + denomination + ' coins' : 'GH₵ ' + denomination + ' notes');
            const savedCount = presetCounts && Object.prototype.hasOwnProperty.call(presetCounts, String(
                    denomination)) ?
                presetCounts[String(denomination)] :
                Number(line.count || 0);
            const available = availableCounts && Object.prototype.hasOwnProperty.call(availableCounts,
                    denomination.toFixed(2)) ?
                Number(availableCounts[denomination.toFixed(2)]) :
                null;
            tr.dataset.availableCount = available === null ? '' : String(available);
            tr.innerHTML =
                '<td></td><td class="text-end" data-calculator-availability></td><td><input class="form-control form-control-sm" type="number" min="0" step="1" data-calculator-count></td><td><input class="form-control form-control-sm" type="text" readonly data-calculator-volume></td>';
            tr.cells[0].textContent = denomLabel;
            tr.querySelector('[data-calculator-availability]').textContent = available === null ? '—' :
                available.toLocaleString();
            tr.querySelector('[data-calculator-count]').value = Number(savedCount) > 0 ? String(savedCount) :
                '';
            calculatorRows.appendChild(tr);
        });
        calculatorLineData();
    }

    function openCalculator(trigger) {
        calculatorTrigger = trigger;
        const requestForm = trigger.closest('[data-denomination-form]');
        const requestCurrency = requestForm ? requestForm.querySelector('[data-currency]').value : '';
        const currency = trigger.dataset.currency || requestCurrency || 'GHS';
        calculatorRows.dataset.currency = currency;
        if (trigger.hasAttribute('data-request-deno-open')) {
            calculatorMode = 'request';
            calculatorForm = trigger.closest('[data-denomination-form]');
            calculatorSet = trigger.dataset.requestDenoOpen;
            const isExchange = calculatorForm.querySelector('[data-transaction-type]').value === 'denos_exchange';
            const hidden = calculatorSet === 'in' ?
                calculatorForm.querySelector('[data-exchange-received]') :
                calculatorForm.querySelector('[data-denos]');
            let saved = [];
            try {
                saved = JSON.parse(hidden.value || '[]');
            } catch (error) {
                saved = [];
            }
            const presetCounts = {};
            saved.forEach(function(line) {
                presetCounts[String(Number(line.denomination))] = Number(line.count || 0);
            });
            const availability = calculatorSet === 'out' ?
                getRequestAvailability(calculatorForm) :
                null;
            calculatorRows.dataset.availabilitySource = availability ? availability.source : '';
            calculatorStock = availability ? availability.rows : [];
            calculatorSubtitle.textContent = isExchange ?
                (calculatorSet === 'in' ? 'Count denominations entering the vault.' :
                    'Count denominations leaving the vault.') :
                (availability && availability.source === 'cashdrawer' ?
                    'Available counts are from your active cashdrawer. Red rows exceed available stock.' :
                    (availability ?
                        'Available counts are for the selected source vault. Red rows exceed available stock.' :
                        'No source vault stock applies to this movement.'));
            buildCalculatorRows(currency, presetCounts, null, availability ? availability.counts : null);
        } else {
            calculatorMode = 'reconciliation';
            calculatorForm = trigger.closest('[data-reconciliation-form]');
            calculatorSet = '';
            try {
                calculatorStock = JSON.parse(trigger.dataset.stock || '[]');
            } catch (error) {
                calculatorStock = [];
            }
            const presetCounts = {};
            calculatorStock.forEach(function(line) {
                presetCounts[String(Number(line.denomination))] = Number(line.count || 0);
            });
            calculatorSubtitle.textContent = currency + (currency === 'CFA' ? ' purchased-rate lot' : ' cash') +
                ' · enter the physical count.';
            buildCalculatorRows(currency, presetCounts, calculatorStock);
        }
        denominationCalculator.classList.add('is-open');
        denominationCalculator.setAttribute('aria-hidden', 'false');
        const firstCount = calculatorRows.querySelector('[data-calculator-count]');
        if (firstCount) firstCount.focus();
    }
    document.querySelectorAll('[data-request-deno-open], [data-recon-calculator-open]').forEach(function(trigger) {
        trigger.addEventListener('click', function() {
            openCalculator(trigger);
        });
    });
    denominationCalculator.addEventListener('click', function(event) {
        if (event.target === denominationCalculator) closeDenominationCalculator();
    });
    calculatorRows.addEventListener('input', function(event) {
        if (event.target.matches('[data-calculator-count]')) calculatorLineData();
    });
    denominationCalculator.querySelectorAll('[data-calculator-close]').forEach(function(button) {
        button.addEventListener('click', closeDenominationCalculator);
    });
    denominationCalculator.querySelector('[data-calculator-reset]').addEventListener('click', function() {
        calculatorRows.querySelectorAll('[data-calculator-count]').forEach(function(input) {
            input.value = '';
        });
        calculatorLineData();
    });
    denominationCalculator.querySelector('[data-calculator-apply]').addEventListener('click', function() {
        const result = calculatorLineData();
        const currency = calculatorRows.dataset.currency || 'GHS';
        if (calculatorMode === 'request' && calculatorForm) {
            const field = calculatorSet === 'in' ?
                calculatorForm.querySelector('[data-exchange-received]') :
                calculatorForm.querySelector('[data-denos]');
            field.value = JSON.stringify(result.lines);
            const summary = calculatorForm.querySelector('[data-request-summary="' + calculatorSet + '"]');
            if (summary) {
                const countLabel = currency === 'CFA' ?
                    result.notes + ' ' + (result.notes === 1 ? 'note' : 'notes') :
                    result.notes + ' ' + (result.notes === 1 ? 'note' : 'notes') + ' · ' + result.coins + ' ' +
                    (result.coins === 1 ? 'coin' : 'coins');
                summary.textContent = result.count ?
                    countLabel + ' · ' + formatCalculatorMoney(result.volume) + ' ' + currency :
                    'No denominations entered';
            }
            calculatorForm.dispatchEvent(new Event('input', {
                bubbles: true
            }));
        } else if (calculatorMode === 'reconciliation' && calculatorForm) {
            const stockField = calculatorForm.querySelector('[data-counted-stock]');
            const groupField = calculatorTrigger.parentElement.querySelector('[data-recon-group-json]');
            const totalField = calculatorTrigger.querySelector('[data-recon-total]');
            if (groupField) groupField.value = JSON.stringify(result.lines);
            const countLabel = currency === 'CFA' ?
                result.notes + ' ' + (result.notes === 1 ? 'note' : 'notes') :
                result.notes + ' ' + (result.notes === 1 ? 'note' : 'notes') + ' · ' + result.coins + ' ' + (
                    result.coins === 1 ? 'coin' : 'coins');
            if (totalField) totalField.textContent = formatCalculatorMoney(result.volume) + ' ' + currency +
                ' · ' + countLabel;
            const combined = [];
            calculatorForm.querySelectorAll('[data-recon-group-json]').forEach(function(input) {
                try {
                    combined.push.apply(combined, JSON.parse(input.value || '[]'));
                } catch (error) {
                    /* Keep the remaining valid lots. */
                }
            });
            if (stockField) stockField.value = JSON.stringify(combined);
        }
        closeDenominationCalculator();
    });
}
document.querySelectorAll('[data-reconciliation-review-form]').forEach(function(form) {
    const choice = form.querySelector('[data-reconciliation-choice]');
    form.querySelectorAll('[data-reconciliation-decision]').forEach(function(button) {
        button.addEventListener('click', function() {
            choice.value = button.dataset.reconciliationDecision;
        });
    });
    form.addEventListener('submit', function(event) {
        if (event.submitter && event.submitter.dataset.reconciliationDecision) {
            choice.value = event.submitter.dataset.reconciliationDecision;
        }
        if (choice.value === 'reject' && !form.querySelector('[name="reason"]').value.trim()) {
            event.preventDefault();
            const reason = form.querySelector('[name="reason"]');
            reason.focus();
            reason.setCustomValidity('Enter a rejection reason before returning this reconciliation.');
            reason.reportValidity();
            reason.setCustomValidity('');
        } else {
            form.querySelector('[name="reason"]').setCustomValidity('');
        }
    });
    form.querySelector('[name="reason"]').addEventListener('input', function() {
        this.setCustomValidity('');
    });
});

function refreshVaultEditForm(form) {
    const exchange = form.dataset.exchange === '1';
    const totals = {
        out: 0,
        in: 0
    };
    const payload = {};
    ['out', 'in'].forEach(function(set) {
        const rowsContainer = form.querySelector('[data-edit-denomination-rows="' + set + '"]');
        if (!rowsContainer) return;
        payload[set] = [];
        rowsContainer.querySelectorAll('[data-edit-denomination-row]').forEach(function(row) {
            const denomination = Number(row.dataset.denomination || 0);
            const countInput = row.querySelector('[data-edit-count]');
            const count = Math.max(0, Math.floor(Number(countInput.value || 0)));
            const lineTotal = denomination * count;
            totals[set] += lineTotal;
            row.querySelector('[data-edit-line-total]').textContent = lineTotal.toLocaleString(
                undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            if (count > 0) payload[set].push({
                denomination: denomination,
                count: count
            });
        });
        const setTotal = form.querySelector('[data-vault-edit-total="' + set + '"]');
        if (setTotal) setTotal.textContent = totals[set].toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    });
    const validOut = payload.out && payload.out.length > 0;
    const balanced = !exchange || Math.abs(totals.out - totals.in) < 0.01;
    const mismatch = form.querySelector('[data-vault-edit-mismatch]');
    if (mismatch) mismatch.classList.toggle('d-none', balanced);
    const submit = form.querySelector('[type="submit"]');
    submit.disabled = !validOut || !balanced || (exchange && (!payload.in || payload.in.length === 0));
    form.querySelector('[data-vault-edit-json]').value = JSON.stringify(
        exchange ? {
            out: payload.out || [],
            in: payload.in || []
        } : (payload.out || [])
    );
}

document.querySelectorAll('[data-vault-edit-form]').forEach(function(form) {
    form.addEventListener('input', function() {
        refreshVaultEditForm(form);
    });
    form.addEventListener('submit', function(event) {
        refreshVaultEditForm(form);
        if (form.querySelector('[type="submit"]').disabled) {
            event.preventDefault();
            const firstCount = form.querySelector('[data-edit-count]');
            if (firstCount) firstCount.focus();
        }
    });
    refreshVaultEditForm(form);
});

let activeVaultEditModal = null;
let activeVaultEditTrigger = null;

function closeVaultEditModal() {
    if (!activeVaultEditModal) return;
    activeVaultEditModal.classList.remove('is-open');
    activeVaultEditModal.setAttribute('aria-hidden', 'true');
    activeVaultEditModal = null;
    if ((!activeVaultModal || !activeVaultModal.classList.contains('is-open')) &&
        (!activeVaultTransactionModal || !activeVaultTransactionModal.classList.contains('is-open'))) {
        document.body.classList.remove('modal-open');
    }
    if (activeVaultEditTrigger) activeVaultEditTrigger.focus();
}
document.querySelectorAll('[data-vault-edit-open]').forEach(function(button) {
    button.addEventListener('click', function() {
        const modal = document.querySelector('[data-vault-edit-modal="' + button.dataset.vaultEditOpen +
            '"]');
        if (!modal) return;
        activeVaultEditTrigger = button;
        activeVaultEditModal = modal;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        const firstInput = modal.querySelector('[data-edit-count]');
        if (firstInput) firstInput.focus();
    });
});
document.querySelectorAll('[data-vault-edit-modal]').forEach(function(modal) {
    document.body.appendChild(modal);
});
document.querySelectorAll('[data-vault-edit-close]').forEach(function(button) {
    button.addEventListener('click', closeVaultEditModal);
});
document.querySelectorAll('[data-vault-edit-modal]').forEach(function(modal) {
    modal.addEventListener('click', function(event) {
        if (event.target === modal) closeVaultEditModal();
    });
});
let activeVaultTransactionModal = null;
let activeVaultTransactionTrigger = null;

function closeVaultTransactionModal() {
    if (!activeVaultTransactionModal) return;
    activeVaultTransactionModal.classList.remove('is-open');
    activeVaultTransactionModal.setAttribute('aria-hidden', 'true');
    activeVaultTransactionModal = null;
    if ((!activeVaultEditModal || !activeVaultEditModal.classList.contains('is-open')) &&
        (!activeVaultModal || !activeVaultModal.classList.contains('is-open')) &&
        !document.querySelector('[data-vault-image-viewer].is-open')) {
        document.body.classList.remove('modal-open');
    }
    if (activeVaultTransactionTrigger) activeVaultTransactionTrigger.focus();
}
document.querySelectorAll('[data-vault-transaction-view]').forEach(function(button) {
    button.addEventListener('click', function() {
        const modal = document.querySelector('[data-vault-transaction-modal="' + button.dataset
            .vaultTransactionView + '"]');
        if (!modal) return;
        activeVaultTransactionTrigger = button;
        activeVaultTransactionModal = modal;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        modal.querySelector('[data-vault-transaction-close]').focus();
    });
});
document.querySelectorAll('[data-vault-transaction-modal]').forEach(function(modal) {
    document.body.appendChild(modal);
    modal.addEventListener('click', function(event) {
        if (event.target === modal) closeVaultTransactionModal();
    });
});
document.querySelectorAll('[data-vault-transaction-close]').forEach(function(button) {
    button.addEventListener('click', closeVaultTransactionModal);
});
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && activeVaultTransactionModal &&
        !document.querySelector('[data-vault-image-viewer].is-open')) {
        closeVaultTransactionModal();
    }
});
const vaultActionMenus = Array.from(document.querySelectorAll('.vault-row-actions')).map(function(details) {
    const menu = details.querySelector('.vault-row-actions__menu');
    const summary = details.querySelector('summary');
    if (!menu || !summary) return null;

    function restoreMenu() {
        if (menu.parentElement === document.body) {
            details.insertBefore(menu, summary.nextSibling);
        }
    }
    details.addEventListener('toggle', function() {
        if (!details.open) {
            restoreMenu();
            return;
        }
        document.body.appendChild(menu);
        const anchor = summary.getBoundingClientRect();
        const menuHeight = menu.offsetHeight;
        const menuWidth = menu.offsetWidth;
        const left = Math.max(8, Math.min(anchor.right - menuWidth, window.innerWidth - menuWidth - 8));
        const top = anchor.bottom + menuHeight <= window.innerHeight - 8 ?
            anchor.bottom + 4 :
            Math.max(8, anchor.top - menuHeight - 4);
        menu.style.left = left + 'px';
        menu.style.top = top + 'px';
    });
    menu.addEventListener('click', function(event) {
        if (event.target.closest('[data-vault-transaction-view], [data-vault-edit-open]')) {
            details.open = false;
        }
    });
    return {
        details: details,
        menu: menu
    };
}).filter(Boolean);
document.addEventListener('click', function(event) {
    vaultActionMenus.forEach(function(actionMenu) {
        if (actionMenu.details.open &&
            !actionMenu.details.contains(event.target) &&
            !actionMenu.menu.contains(event.target)) {
            actionMenu.details.open = false;
        }
    });
});

function closeOpenVaultActionMenus() {
    vaultActionMenus.forEach(function(actionMenu) {
        if (actionMenu.details.open) actionMenu.details.open = false;
    });
}
window.addEventListener('resize', closeOpenVaultActionMenus);
window.addEventListener('scroll', closeOpenVaultActionMenus, true);
document.querySelectorAll('[data-vault-image-input]').forEach(function(input) {
    input.addEventListener('change', function() {
        const file = input.files && input.files[0];
        const image = document.getElementById(input.dataset.previewImage || '');
        const empty = document.getElementById(input.dataset.previewEmpty || '');
        const meta = document.getElementById(input.dataset.previewMeta || '');
        if (!image || !empty || !meta) return;
        if (!file) return;
        if (!file.type || !file.type.startsWith('image/')) {
            input.value = '';
            meta.textContent = 'Please choose a valid image file.';
            return;
        }
        if (image.dataset.objectUrl) URL.revokeObjectURL(image.dataset.objectUrl);
        const objectUrl = URL.createObjectURL(file);
        image.dataset.objectUrl = objectUrl;
        image.src = objectUrl;
        image.classList.remove('d-none');
        empty.classList.add('d-none');
        const size = file.size < 1024 * 1024 ?
            Math.max(1, Math.round(file.size / 1024)) + ' KB' :
            (file.size / (1024 * 1024)).toFixed(2) + ' MB';
        meta.textContent = file.name + ' · ' + size + ' · preview ready';
    });
});
const vaultImageViewer = document.querySelector('[data-vault-image-viewer]');
const vaultImageFull = vaultImageViewer.querySelector('[data-vault-image-full]');
const vaultImageCaption = vaultImageViewer.querySelector('[data-vault-image-caption]');
let vaultImageOpener = null;

function openVaultImageViewer(trigger) {
    const image = trigger.matches('img') ? trigger : null;
    const src = image ? image.currentSrc || image.src : trigger.href;
    if (!src || (image && image.classList.contains('d-none'))) return;
    vaultImageOpener = trigger;
    vaultImageFull.src = src;
    vaultImageFull.alt = image ? image.alt : trigger.textContent.trim();
    const preview = image ? image.closest('.vault-image-preview') : null;
    const metadata = preview ? preview.querySelector('.vault-image-preview__meta') : null;
    vaultImageCaption.textContent = image ?
        (metadata ? metadata.textContent.trim() : image.alt) :
        trigger.textContent.trim();
    vaultImageViewer.classList.add('is-open');
    vaultImageViewer.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
    vaultImageViewer.querySelector('[data-vault-image-close]').focus();
}

function closeVaultImageViewer() {
    if (!vaultImageViewer.classList.contains('is-open')) return;
    vaultImageViewer.classList.remove('is-open');
    vaultImageViewer.setAttribute('aria-hidden', 'true');
    vaultImageFull.removeAttribute('src');
    if ((!activeVaultEditModal || !activeVaultEditModal.classList.contains('is-open')) &&
        (!activeVaultModal || !activeVaultModal.classList.contains('is-open')) &&
        (!activeVaultTransactionModal || !activeVaultTransactionModal.classList.contains('is-open'))) {
        document.body.classList.remove('modal-open');
    }
    if (vaultImageOpener) vaultImageOpener.focus();
}
document.body.appendChild(vaultImageViewer);
document.addEventListener('click', function(event) {
    const trigger = event.target.closest('[data-vault-image-open]');
    if (!trigger) return;
    event.preventDefault();
    openVaultImageViewer(trigger);
});
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && vaultImageViewer.classList.contains('is-open')) {
        closeVaultImageViewer();
        event.stopImmediatePropagation();
        return;
    }
    if ((event.key === 'Enter' || event.key === ' ') && event.target.matches('img[data-vault-image-open]')) {
        event.preventDefault();
        openVaultImageViewer(event.target);
    }
});
vaultImageViewer.addEventListener('click', function(event) {
    if (event.target === vaultImageViewer || event.target.closest('[data-vault-image-close]')) {
        closeVaultImageViewer();
    }
});
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && activeVaultEditModal) closeVaultEditModal();
});

document.querySelectorAll('[data-denomination-form]').forEach(function(form) {
    const currency = form.querySelector('[data-currency]');
    const transactionType = form.querySelector('[data-transaction-type]');
    const sourceSelect = form.querySelector('[data-source-vault]');
    const destinationSelect = form.querySelector('[data-destination-vault]');
    const sourceWrap = form.querySelector('[data-source-vault-wrap]');
    const destinationWrap = form.querySelector('[data-destination-vault-wrap]');
    const agentDestinationNote = form.querySelector('[data-agent-destination-note]');
    const firstPartySelect = form.querySelector('[data-first-party-select]');
    const total = form.querySelector('[data-total]');
    const hidden = form.querySelector('[data-denos]');
    const receivedHidden = form.querySelector('[data-exchange-received]');
    const exchangeRateSelect = form.querySelector('[data-exchange-rate]');
    const submitButton = form.querySelector('[type="submit"]');
    const normalSection = form.querySelector('[data-normal-denominations]');
    const exchangeSection = form.querySelector('[data-exchange-section]');
    const exchangeRateWrap = form.querySelector('[data-exchange-rate-wrap]');
    const receivedTotal = form.querySelector('[data-exchange-total]');
    const receivedTotalWrap = form.querySelector('[data-exchange-received-total-wrap]');

    function readTotal(field) {
        let lines = [];
        try {
            lines = JSON.parse(field.value || '[]');
        } catch (error) {
            lines = [];
        }
        return lines.reduce(function(sum, line) {
            return sum + Number(line.denomination || 0) * Number(line.count || 0);
        }, 0);
    }

    function chooseVaultForBranch(select, branchId, excludedBranchId) {
        const options = Array.from(select.options).filter(function(option) {
            if (!option.value) return false;
            const optionBranch = Number(option.dataset.branch || 0);
            return (!branchId || optionBranch === branchId) && (!excludedBranchId || optionBranch !==
                excludedBranchId);
        });
        const selectedIsValid = options.some(function(option) {
            return option.value === select.value;
        });
        if (!selectedIsValid && options.length) select.value = options[0].value;
        if (!options.length) select.value = '';
        const selectedOption = select.selectedOptions[0];
        return selectedOption ? Number(selectedOption.dataset.branch || 0) : 0;
    }

    function refreshVaultChoices() {
        const type = transactionType.value;
        const currentBranchId = Number(form.dataset.userBranch || 0);
        const interbranch = type === 'interbranch';
        const sourceNeeded = ['to_agent', 'interbranch', 'denos_exchange'].includes(type);
        const destinationNeeded = ['from_agent', 'interbranch'].includes(type);
        agentDestinationNote.classList.toggle('d-none', !['to_agent', 'from_agent'].includes(type));
        sourceWrap.classList.toggle('d-none', !sourceNeeded);
        destinationWrap.classList.toggle('d-none', !destinationNeeded);
        sourceSelect.required = sourceNeeded;
        destinationSelect.required = destinationNeeded;

        Array.from(sourceSelect.options).forEach(function(option) {
            if (!option.value) return;
            option.hidden = !interbranch && Number(option.dataset.branch || 0) !== currentBranchId;
            option.disabled = option.hidden;
        });
        Array.from(destinationSelect.options).forEach(function(option) {
            if (!option.value) return;
            option.hidden = !interbranch && Number(option.dataset.branch || 0) !== currentBranchId;
            option.disabled = option.hidden;
        });

        if (type === 'to_agent') {
            chooseVaultForBranch(sourceSelect, currentBranchId, 0);
            destinationSelect.value = '';
        } else if (type === 'from_agent') {
            sourceSelect.value = '';
            chooseVaultForBranch(destinationSelect, currentBranchId, 0);
        } else if (type === 'interbranch') {
            if (form.dataset.canManage !== '1') {
                Array.from(sourceSelect.options).forEach(function(option) {
                    if (!option.value) return;
                    option.hidden = Number(option.dataset.branch || 0) !== currentBranchId;
                    option.disabled = option.hidden;
                });
            }
            const sourceBranch = chooseVaultForBranch(
                sourceSelect,
                form.dataset.canManage === '1' ? 0 : currentBranchId,
                0
            );
            chooseVaultForBranch(destinationSelect, 0, sourceBranch);
        } else if (type === 'denos_exchange') {
            chooseVaultForBranch(sourceSelect, currentBranchId, 0);
            destinationSelect.value = '';
        } else {
            sourceSelect.value = '';
            destinationSelect.value = '';
        }
        const sourceOption = sourceSelect.selectedOptions[0];
        const destinationOption = destinationSelect.selectedOptions[0];
        const receivingBranch = type === 'interbranch' ?
            Number(destinationOption ? destinationOption.dataset.branch || 0 : 0) :
            (type === 'from_agent' ? currentBranchId : Number(sourceOption ? sourceOption.dataset.branch || 0 :
                0));
        Array.from(firstPartySelect.options).forEach(function(option) {
            if (!option.value) return;
            option.hidden = !receivingBranch || Number(option.dataset.branch || 0) !== receivingBranch;
            option.disabled = option.hidden;
        });
        if (firstPartySelect.selectedOptions.length &&
            firstPartySelect.selectedOptions[0].value &&
            firstPartySelect.selectedOptions[0].disabled) {
            firstPartySelect.value = '';
        }
    }

    function refreshExchangeRates() {
        if (!exchangeRateSelect) return;
        const previousRate = exchangeRateSelect.value;
        const sourceId = Number(sourceSelect.value || 0);
        let stock = [];
        try {
            stock = JSON.parse(form.dataset.vaultStock || '[]');
        } catch (error) {
            stock = [];
        }
        const rates = Array.from(new Set(stock.filter(function(line) {
            return Number(line.vault_id) === sourceId && line.currency === 'CFA' && Number(line
                .count) > 0;
        }).map(function(line) {
            return Number(line.purchase_rate).toFixed(6);
        }))).sort(function(a, b) {
            return Number(a) - Number(b);
        });
        exchangeRateSelect.replaceChildren();
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = rates.length ? 'Select an available purchase-rate lot' :
            'No CFA stock available in this source vault';
        exchangeRateSelect.appendChild(placeholder);
        rates.forEach(function(rate) {
            const option = document.createElement('option');
            option.value = rate;
            option.textContent = 'CFA 1,000 = GH₵ ' + Number(rate).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            exchangeRateSelect.appendChild(option);
        });
        if (rates.includes(Number(previousRate).toFixed(6))) {
            exchangeRateSelect.value = Number(previousRate).toFixed(6);
        } else if (rates.length) {
            exchangeRateSelect.value = rates[0];
        }
        exchangeRateSelect.required = transactionType.value === 'denos_exchange' && currency.value === 'CFA';
    }

    function getShortages() {
        const type = transactionType.value;
        const sourceId = Number(sourceSelect.value || 0);
        let requestedLines = [];
        try {
            requestedLines = JSON.parse(hidden.value || '[]');
        } catch (error) {
            requestedLines = [];
        }
        const availableByDenomination = {};
        if (type === 'from_agent') {
            let allCounts = {};
            try {
                allCounts = JSON.parse(form.dataset.userDrawerCounts || '{}');
            } catch (error) {
                allCounts = {};
            }
            Object.keys(allCounts[currency.value] || {}).forEach(function(key) {
                availableByDenomination[Number(key).toFixed(2)] = Math.max(0, Number(allCounts[currency
                    .value][key] || 0));
            });
        } else {
            if (!sourceId || !['to_agent', 'interbranch', 'denos_exchange'].includes(type)) return [];
            let stock = [];
            try {
                stock = JSON.parse(form.dataset.vaultStock || '[]');
            } catch (error) {
                stock = [];
            }
            const selectedRate = type === 'denos_exchange' && currency.value === 'CFA' ?
                Number(exchangeRateSelect ? exchangeRateSelect.value : 0) :
                null;
            stock.forEach(function(line) {
                if (Number(line.vault_id) !== sourceId || line.currency !== currency.value) return;
                if (selectedRate !== null && (selectedRate <= 0 || Math.abs(Number(line.purchase_rate) -
                        selectedRate) > 0.000001)) return;
                const key = Number(line.denomination).toFixed(2);
                availableByDenomination[key] = (availableByDenomination[key] || 0) + Number(line
                    .count || 0);
            });
        }
        return requestedLines.reduce(function(shortages, line) {
            const denomination = Number(line.denomination);
            const requested = Number(line.count || 0);
            const available = availableByDenomination[denomination.toFixed(2)] || 0;
            if (requested > available) shortages.push({
                denomination: denomination,
                requested: requested,
                available: available,
                shortage: requested - available
            });
            return shortages;
        }, []);
    }

    function shortageMessage(shortages) {
        const sourceLabel = transactionType.value === 'from_agent' ? 'your cashdrawer' : 'source vault';
        return 'Insufficient ' + sourceLabel + ' denominations. ' + shortages.map(function(line) {
            const label = currency.value === 'GHS' ?
                'GH₵ ' + line.denomination.toFixed(2) :
                line.denomination.toLocaleString() + ' CFA';
            const unit = currency.value === 'GHS' && line.denomination < 1 ? 'coins' : 'notes';
            return label + ' ' + unit + ': requested ' + line.requested.toLocaleString() +
                ', available ' + line.available.toLocaleString() +
                ', short ' + line.shortage.toLocaleString();
        }).join('; ') + '. Correct the denomination counts before submitting.';
    }

    function refresh() {
        refreshVaultChoices();
        refreshExchangeRates();
        const isExchange = transactionType.value === 'denos_exchange';
        normalSection.classList.toggle('d-none', isExchange);
        exchangeSection.classList.toggle('d-none', !isExchange);
        exchangeRateWrap.classList.toggle('d-none', !(isExchange && currency.value === 'CFA'));
        const outgoing = readTotal(hidden);
        const incoming = readTotal(receivedHidden);
        total.value = outgoing.toFixed(2);
        if (receivedTotal) receivedTotal.value = incoming.toFixed(2);
        if (receivedTotalWrap) receivedTotalWrap.classList.toggle('d-none', !isExchange);
        form.dataset.exchangeTotalsMatch = String(Math.abs(outgoing - incoming) < 0.01);
        const shortages = getShortages();
        form.dataset.sourceStockSufficient = String(shortages.length === 0);
        form.querySelectorAll('[data-denomination-shortage]').forEach(function(alert) {
            alert.textContent = shortages.length ? shortageMessage(shortages) : '';
            alert.classList.toggle('d-none', shortages.length === 0);
        });
        if (submitButton) {
            const hasOutgoing = outgoing > 0;
            const exchangeValid = !isExchange || form.dataset.exchangeTotalsMatch === 'true';
            const rateValid = !exchangeRateSelect || !exchangeRateSelect.required || Number(exchangeRateSelect
                .value) > 0;
            submitButton.disabled = !hasOutgoing || !exchangeValid || !rateValid || shortages.length > 0;
        }
    }
    currency.addEventListener('change', function() {
        hidden.value = '[]';
        receivedHidden.value = '[]';
        form.querySelectorAll('[data-request-summary]').forEach(function(summary) {
            summary.textContent = 'No denominations entered';
        });
        refresh();
    });
    transactionType.addEventListener('change', refresh);
    sourceSelect.addEventListener('change', refresh);
    destinationSelect.addEventListener('change', refresh);
    if (exchangeRateSelect) exchangeRateSelect.addEventListener('change', refresh);
    form.addEventListener('input', refresh);
    form.addEventListener('submit', function(event) {
        refresh();
        const shortages = getShortages();
        if (!hidden.value || hidden.value === '[]' ||
            (transactionType.value === 'denos_exchange' && form.dataset.exchangeTotalsMatch !==
                'true') ||
            (exchangeRateSelect && exchangeRateSelect.required && Number(exchangeRateSelect.value) <=
                0) ||
            shortages.length > 0) {
            event.preventDefault();
            if (shortages.length) alert(shortageMessage(shortages));
            else if (exchangeRateSelect && exchangeRateSelect.required && Number(exchangeRateSelect
                    .value) <= 0) {
                alert('Select an available CFA purchase-rate lot from the source vault.');
            } else {
                alert(transactionType.value === 'denos_exchange' ?
                    'Enter notes given and received with exactly matching totals.' :
                    'Enter at least one note count.');
            }
        }
    });
    refresh();
});

document.querySelectorAll('[data-reconciliation-form]').forEach(function(form) {
    const field = form.querySelector('[data-counted-stock]');

    function refresh() {
        const rows = [];
        form.querySelectorAll('[data-recon-group-json]').forEach(function(input) {
            try {
                rows.push.apply(rows, JSON.parse(input.value || '[]'));
            } catch (error) {
                /* Preserve the remaining valid lots. */
            }
        });
        field.value = JSON.stringify(rows);
    }
    form.addEventListener('input', refresh);
    form.addEventListener('submit', function(event) {
        refresh();
        const groups = Array.from(form.querySelectorAll('[data-recon-group-json]'));
        const hasUncountedGroup = groups.some(function(input) {
            try {
                return JSON.parse(input.value || '[]').length === 0;
            } catch (error) {
                return true;
            }
        });
        if (hasUncountedGroup) {
            event.preventDefault();
            alert(
                'Open the denomination calculator and enter the physical count for every currency and CFA purchase-rate group.'
            );
        }
    });
    refresh();
});

const openingForm = document.querySelector('[data-opening-form]');
if (openingForm) {
    const branch = openingForm.querySelector('[data-branch-select]');
    const custodian = openingForm.querySelector('[data-custodian-select]');
    const rate = Number(openingForm.dataset.cfaRate || 0);
    const calculator = document.querySelector('#vaultOpeningCalculator');
    const createVaultModal = document.querySelector('#createVaultModal');
    const calculatorToggle = openingForm.querySelector('[data-opening-calculator-open]');
    const openingRows = Array.from(calculator.querySelectorAll('[data-opening-row]'));
    let modalBackdrop = null;
    let calculatorBackdrop = null;

    function showCreateModal() {
        modalBackdrop = document.createElement('div');
        modalBackdrop.className = 'modal-backdrop fade show';
        modalBackdrop.addEventListener('click', hideCreateModal);
        document.body.appendChild(modalBackdrop);
        document.body.classList.add('modal-open');
        createVaultModal.style.display = 'block';
        createVaultModal.removeAttribute('aria-hidden');
        createVaultModal.setAttribute('aria-modal', 'true');
        createVaultModal.setAttribute('role', 'dialog');
        createVaultModal.classList.add('show');
        const firstInput = createVaultModal.querySelector(
            'input:not([type="hidden"]):not([disabled]), select:not([disabled])');
        if (firstInput) firstInput.focus();
    }

    function hideCreateModal() {
        if (calculator.classList.contains('show')) hideOpeningCalculator();
        createVaultModal.classList.remove('show');
        createVaultModal.style.display = 'none';
        createVaultModal.setAttribute('aria-hidden', 'true');
        createVaultModal.removeAttribute('aria-modal');
        createVaultModal.removeAttribute('role');
        document.body.classList.remove('modal-open');
        if (modalBackdrop) {
            modalBackdrop.remove();
            modalBackdrop = null;
        }
    }

    function showOpeningCalculator() {
        calculatorBackdrop = document.createElement('div');
        calculatorBackdrop.className = 'vault-opening-calculator-backdrop';
        calculatorBackdrop.addEventListener('click', hideOpeningCalculator);
        document.body.appendChild(calculatorBackdrop);
        calculator.style.display = 'block';
        calculator.removeAttribute('aria-hidden');
        calculator.setAttribute('aria-modal', 'true');
        calculator.setAttribute('role', 'dialog');
        calculator.classList.add('show');
        calculatorToggle.setAttribute('aria-expanded', 'true');
        const firstCount = calculator.querySelector('[data-opening-count]:not([disabled])');
        if (firstCount) firstCount.focus();
    }

    function hideOpeningCalculator() {
        calculator.classList.remove('show');
        calculator.style.display = 'none';
        calculator.setAttribute('aria-hidden', 'true');
        calculator.removeAttribute('aria-modal');
        calculator.removeAttribute('role');
        if (calculatorBackdrop) {
            calculatorBackdrop.remove();
            calculatorBackdrop = null;
        }
        calculatorToggle.setAttribute('aria-expanded', 'false');
        calculatorToggle.focus();
    }
    const formatMoney = function(value) {
        return Number(value || 0).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    };

    function filterCustodians(resetSelection) {
        Array.from(custodian.options).forEach(function(option) {
            if (option.value) option.hidden = option.dataset.branch !== branch.value;
        });
        if (resetSelection) custodian.value = '';
    }
    branch.addEventListener('change', function() {
        filterCustodians(true);
    });
    filterCustodians(false);

    function refreshOpeningCalculator() {
        const lines = {
            GHS: [],
            CFA: []
        };
        const totals = {
            GHS: 0,
            CFA: 0
        };
        const counts = {
            GHS: 0,
            CFA: 0
        };
        openingRows.forEach(function(row) {
            const currency = row.dataset.currency;
            const denomination = Number(row.dataset.denomination);
            const countInput = row.querySelector('[data-opening-count]');
            const count = Math.max(0, Math.floor(Number(countInput.value || 0)));
            countInput.value = countInput.value === '' ? '' : String(count);
            const volume = denomination * count;
            row.querySelector('[data-opening-volume]').value = count > 0 ? formatMoney(volume) : '';
            if (count > 0) {
                lines[currency].push({
                    denomination: denomination,
                    count: count
                });
                totals[currency] += volume;
                counts[currency] += count;
            }
        });
        openingForm.querySelector('[data-ghs-opening]').value = JSON.stringify(lines.GHS);
        openingForm.querySelector('[data-cfa-opening]').value = JSON.stringify(lines.CFA);
        calculator.querySelector('[data-calculator-ghs-count]').value = counts.GHS;
        calculator.querySelector('[data-calculator-ghs-total]').value = formatMoney(totals.GHS);
        calculator.querySelector('[data-calculator-cfa-count]').value = counts.CFA;
        calculator.querySelector('[data-calculator-cfa-total]').value = formatMoney(totals.CFA);
        calculator.querySelector('[data-calculator-total-ghs]').value = formatMoney(totals.GHS + totals.CFA * rate /
            1000);
        openingForm.querySelector('[data-opening-ghs-total]').textContent = 'GH₵ ' + formatMoney(totals.GHS);
        openingForm.querySelector('[data-opening-ghs-count]').textContent = counts.GHS.toLocaleString();
        openingForm.querySelector('[data-opening-cfa-total]').textContent = totals.CFA.toLocaleString(undefined, {
            maximumFractionDigits: 0
        }) + ' CFA';
        openingForm.querySelector('[data-opening-cfa-count]').textContent = counts.CFA.toLocaleString();
        openingForm.querySelector('[data-opening-total-ghs]').textContent = 'GH₵ ' + formatMoney(totals.GHS + totals
            .CFA * rate / 1000);
        openingForm.querySelector('[data-opening-display]').value = 'GH₵ ' + formatMoney(totals.GHS) + ' · ' + totals
            .CFA.toLocaleString(undefined, {
                maximumFractionDigits: 0
            }) + ' CFA';
        const limitGhs = Number(openingForm.querySelector('[data-limit-ghs]').value || 0);
        const limitCfa = Number(openingForm.querySelector('[data-limit-cfa]').value || 0);
        const limitTotal = Number(openingForm.querySelector('[data-limit-total]').value || 0);
        const openingTotalGhs = totals.GHS + totals.CFA * rate / 1000;
        const issues = [];
        if (limitGhs > 0 && totals.GHS > limitGhs + 0.01) {
            issues.push('Opening GHS cash (GH₵ ' + formatMoney(totals.GHS) + ') exceeds the GHS limit (GH₵ ' +
                formatMoney(limitGhs) + '). Increase the GHS limit or reduce the GHS notes.');
        }
        if (limitCfa > 0 && totals.CFA > limitCfa + 0.01) {
            issues.push('Opening CFA cash (' + totals.CFA.toLocaleString() + ' CFA) exceeds the CFA limit (' + limitCfa
                .toLocaleString() + ' CFA). Increase the CFA limit or reduce the CFA notes.');
        }
        if (limitTotal > 0 && openingTotalGhs > limitTotal + 0.01) {
            issues.push('Combined opening cash (GH₵ ' + formatMoney(openingTotalGhs) +
                ') exceeds the cumulative limit (GH₵ ' + formatMoney(limitTotal) +
                '). Increase the currency limits or reduce the opening notes.');
        }
        const limitFeedback = openingForm.querySelector('[data-opening-limit-feedback]');
        limitFeedback.textContent = issues.join(' ');
        limitFeedback.classList.toggle('d-none', issues.length === 0);
        openingForm.dataset.openingLimitsValid = String(issues.length === 0);
    }
    calculator.addEventListener('input', refreshOpeningCalculator);
    openingForm.querySelectorAll('[data-limit-ghs], [data-limit-cfa]').forEach(function(input) {
        input.addEventListener('input', refreshOpeningCalculator);
    });
    openingRows.forEach(function(row) {
        row.querySelector('[data-opening-count]').addEventListener('input', refreshOpeningCalculator);
    });
    calculator.querySelector('[data-opening-reset]').addEventListener('click', function() {
        openingRows.forEach(function(row) {
            row.querySelector('[data-opening-count]').value = '';
        });
        refreshOpeningCalculator();
    });
    calculator.querySelector('[data-opening-apply]').addEventListener('click', function() {
        refreshOpeningCalculator();
        hideOpeningCalculator();
    });
    calculatorToggle.addEventListener('click', function() {
        if (calculator.classList.contains('show')) {
            hideOpeningCalculator();
        } else {
            showOpeningCalculator();
        }
    });
    calculator.querySelectorAll('[data-opening-calculator-close]').forEach(function(button) {
        button.addEventListener('click', hideOpeningCalculator);
    });
    document.querySelectorAll('[data-create-vault-open]').forEach(function(button) {
        button.addEventListener('click', showCreateModal);
    });
    createVaultModal.querySelectorAll('[data-create-vault-close]').forEach(function(button) {
        button.addEventListener('click', hideCreateModal);
    });
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            if (calculator.classList.contains('show')) {
                hideOpeningCalculator();
            } else if (createVaultModal.classList.contains('show')) {
                hideCreateModal();
            }
        }
    });
    openingForm.addEventListener('submit', function(event) {
        refreshOpeningCalculator();
        if (openingForm.dataset.openingLimitsValid !== 'true') {
            event.preventDefault();
            const limitFeedback = openingForm.querySelector('[data-opening-limit-feedback]');
            limitFeedback.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }
    });
    refreshOpeningCalculator();
    if (createVaultModal.dataset.openOnLoad === 'true') {
        showCreateModal();
    }
}

const vaultAdminCashInForm = document.querySelector('[data-vault-admin-cash-in-form]');
if (vaultAdminCashInForm) {
    const cashInModal = document.querySelector('#vaultAdminCashInModal');
    const cashInCalculator = document.querySelector('#vaultAdminCashInCalculator');
    const currencySelect = vaultAdminCashInForm.querySelector('[data-vault-cash-in-currency]');
    const drawerSelect = vaultAdminCashInForm.querySelector('[name="drawer_user_id"]');
    const calculatorOpenButton = vaultAdminCashInForm.querySelector('[data-vault-cash-in-calculator-open]');
    const calculatorRows = Array.from(cashInCalculator.querySelectorAll('[data-vault-cash-in-row]'));
    const hiddenCounts = Array.from(vaultAdminCashInForm.querySelectorAll('[data-vault-cash-in-hidden-count]'));
    const feedback = vaultAdminCashInForm.querySelector('[data-vault-cash-in-feedback]');
    let cashInBackdrop = null;
    let cashInCalculatorBackdrop = null;

    function formatCashInMoney(value, currency) {
        return Number(value || 0).toLocaleString(undefined, {
            minimumFractionDigits: currency === 'CFA' ? 0 : 2,
            maximumFractionDigits: currency === 'CFA' ? 0 : 2
        });
    }

    function refreshCashInCalculator() {
        const totals = {
            GHS: {
                count: 0,
                volume: 0
            },
            CFA: {
                count: 0,
                volume: 0
            }
        };
        calculatorRows.forEach(function(row) {
            const currency = row.dataset.currency;
            const denomination = Number(row.dataset.denomination || 0);
            const input = row.querySelector('[data-vault-cash-in-count]');
            const count = Math.max(0, Math.floor(Number(input.value || 0)));
            input.value = input.value === '' ? '' : String(count);
            const volume = denomination * count;
            row.querySelector('[data-vault-cash-in-volume]').value = formatCashInMoney(volume, currency);
            totals[currency].count += count;
            totals[currency].volume += volume;
        });

        const currency = currencySelect.value || 'GHS';
        const total = totals[currency];
        const selectedDrawer = drawerSelect.options[drawerSelect.selectedIndex];
        const applyButton = cashInCalculator.querySelector('[data-vault-cash-in-calculator-apply]');
        let availableCounts = {};
        if (selectedDrawer && selectedDrawer.value) {
            try {
                availableCounts = JSON.parse(currency === 'CFA' ?
                    selectedDrawer.dataset.cfaCounts || '{}' :
                    selectedDrawer.dataset.ghsCounts || '{}');
            } catch (error) {
                availableCounts = {};
            }
        }
        const shortages = [];
        calculatorRows.forEach(function(row) {
            if (row.dataset.currency !== currency) return;
            const denomination = Number(row.dataset.denomination || 0);
            const key = currency === 'CFA' ? String(Math.round(denomination)) : denomination.toFixed(2);
            const available = Math.max(0, Number(availableCounts[key] || 0));
            const count = Math.max(0, Math.floor(Number(row.querySelector('[data-vault-cash-in-count]').value || 0)));
            row.querySelector('[data-vault-cash-in-available]').textContent = available.toLocaleString();
            row.classList.toggle('table-danger', count > available);
            if (count > available) {
                shortages.push((currency === 'GHS' ? 'GH₵ ' + denomination.toFixed(2) : denomination.toLocaleString() +
                    ' CFA') + ': ' + count.toLocaleString() + ' requested, ' + available.toLocaleString() +
                    ' available');
            }
        });
        cashInCalculator.querySelector('[data-vault-cash-in-calculator-count-total]').value =
            total.count.toLocaleString();
        cashInCalculator.querySelector('[data-vault-cash-in-calculator-volume-total]').value =
            formatCashInMoney(total.volume, currency);
        vaultAdminCashInForm.querySelector('[data-vault-cash-in-count-total]').textContent =
            total.count.toLocaleString() + (currency === 'GHS' ? ' notes / coins' : ' notes');
        vaultAdminCashInForm.querySelector('[data-vault-cash-in-volume-total]').textContent =
            (currency === 'GHS' ? 'GH₵ ' : '') + formatCashInMoney(total.volume, currency) +
            (currency === 'CFA' ? ' CFA' : '');

        cashInCalculator.querySelectorAll('[data-vault-cash-in-currency-rows]').forEach(function(group) {
            group.hidden = group.dataset.vaultCashInCurrencyRows !== currency;
        });
        cashInCalculator.querySelector('[data-vault-cash-in-calculator-subtitle]').textContent =
            selectedDrawer && selectedDrawer.value ?
                'Available counts in drawer #' + (selectedDrawer.dataset.cashdrawerId || '') + '. Enter the counts to transfer.' :
                'Select a source cashdrawer to see available ' + currency + ' counts.';
        if (shortages.length) {
            feedback.textContent = 'The selected cashdrawer does not have enough ' + currency +
                ' denominations: ' + shortages.join('; ') + '.';
            feedback.dataset.cashInShortage = 'true';
            feedback.classList.remove('d-none');
        } else if (feedback.dataset.cashInShortage === 'true') {
            feedback.textContent = '';
            delete feedback.dataset.cashInShortage;
            feedback.classList.add('d-none');
        }
        applyButton.disabled = shortages.length > 0;
        return {
            totals: totals,
            shortages: shortages
        };
    }

    function loadCashInCalculator() {
        calculatorRows.forEach(function(row) {
            const currency = row.dataset.currency;
            const denomination = row.dataset.denomination;
            const hidden = hiddenCounts.find(function(input) {
                return input.dataset.currency === currency && input.dataset.denomination === denomination;
            });
            const savedCount = hidden ? Math.max(0, Math.floor(Number(hidden.value || 0))) : 0;
            row.querySelector('[data-vault-cash-in-count]').value = savedCount > 0 ? String(savedCount) : '';
        });
        refreshCashInCalculator();
    }

    function applyCashInCounts() {
        calculatorRows.forEach(function(row) {
            const currency = row.dataset.currency;
            const denomination = row.dataset.denomination;
            const hidden = hiddenCounts.find(function(input) {
                return input.dataset.currency === currency && input.dataset.denomination === denomination;
            });
            if (hidden) hidden.value = row.querySelector('[data-vault-cash-in-count]').value || '0';
        });
        refreshCashInCalculator();
    }

    function showCashInModal() {
        cashInBackdrop = document.createElement('div');
        cashInBackdrop.className = 'modal-backdrop fade show';
        cashInBackdrop.addEventListener('click', hideCashInModal);
        document.body.appendChild(cashInBackdrop);
        document.body.classList.add('modal-open');
        cashInModal.style.display = 'block';
        cashInModal.classList.add('show');
        cashInModal.removeAttribute('aria-hidden');
        cashInModal.setAttribute('aria-modal', 'true');
        cashInModal.setAttribute('role', 'dialog');
        const firstInput = cashInModal.querySelector('select:not([disabled]), input:not([type="hidden"]):not([disabled])');
        if (firstInput) firstInput.focus();
    }

    function hideCashInModal() {
        if (cashInCalculator.classList.contains('show')) hideCashInCalculator();
        cashInModal.classList.remove('show');
        cashInModal.style.display = 'none';
        cashInModal.setAttribute('aria-hidden', 'true');
        cashInModal.removeAttribute('aria-modal');
        cashInModal.removeAttribute('role');
        document.body.classList.remove('modal-open');
        if (cashInBackdrop) {
            cashInBackdrop.remove();
            cashInBackdrop = null;
        }
    }

    function showCashInCalculator() {
        loadCashInCalculator();
        cashInCalculatorBackdrop = document.createElement('div');
        cashInCalculatorBackdrop.className = 'vault-admin-cash-in-backdrop';
        cashInCalculatorBackdrop.addEventListener('click', hideCashInCalculator);
        document.body.appendChild(cashInCalculatorBackdrop);
        cashInCalculator.style.display = 'block';
        cashInCalculator.classList.add('show');
        cashInCalculator.removeAttribute('aria-hidden');
        cashInCalculator.setAttribute('aria-modal', 'true');
        cashInCalculator.setAttribute('role', 'dialog');
        calculatorOpenButton.setAttribute('aria-expanded', 'true');
        const firstCount = cashInCalculator.querySelector(
            '[data-vault-cash-in-row][data-currency="' + currencySelect.value + '"] [data-vault-cash-in-count]');
        if (firstCount) firstCount.focus();
    }

    function hideCashInCalculator() {
        cashInCalculator.classList.remove('show');
        cashInCalculator.style.display = 'none';
        cashInCalculator.setAttribute('aria-hidden', 'true');
        cashInCalculator.removeAttribute('aria-modal');
        cashInCalculator.removeAttribute('role');
        if (cashInCalculatorBackdrop) {
            cashInCalculatorBackdrop.remove();
            cashInCalculatorBackdrop = null;
        }
        calculatorOpenButton.setAttribute('aria-expanded', 'false');
        calculatorOpenButton.focus();
    }

    document.querySelectorAll('[data-vault-admin-cash-in-open]').forEach(function(button) {
        button.addEventListener('click', showCashInModal);
    });
    cashInModal.querySelectorAll('[data-vault-admin-cash-in-close]').forEach(function(button) {
        button.addEventListener('click', hideCashInModal);
    });
    cashInModal.addEventListener('click', function(event) {
        if (event.target === cashInModal) hideCashInModal();
    });
    calculatorOpenButton.addEventListener('click', showCashInCalculator);
    cashInCalculator.querySelectorAll('[data-vault-cash-in-calculator-close]').forEach(function(button) {
        button.addEventListener('click', hideCashInCalculator);
    });
    cashInCalculator.addEventListener('click', function(event) {
        if (event.target === cashInCalculator) hideCashInCalculator();
    });
    cashInCalculator.querySelector('[data-vault-cash-in-calculator-reset]').addEventListener('click', function() {
        calculatorRows.forEach(function(row) {
            if (row.dataset.currency === currencySelect.value) {
                row.querySelector('[data-vault-cash-in-count]').value = '';
            }
        });
        refreshCashInCalculator();
    });
    cashInCalculator.querySelector('[data-vault-cash-in-calculator-apply]').addEventListener('click', function() {
        applyCashInCounts();
        hideCashInCalculator();
    });
    cashInCalculator.addEventListener('input', refreshCashInCalculator);
    currencySelect.addEventListener('change', function() {
        refreshCashInCalculator();
    });
    drawerSelect.addEventListener('change', function() {
        refreshCashInCalculator();
    });
    vaultAdminCashInForm.addEventListener('submit', function(event) {
        const result = refreshCashInCalculator();
        applyCashInCounts();
        if (result.totals[currencySelect.value].volume <= 0) {
            event.preventDefault();
            feedback.textContent = 'Open the denomination calculator and enter at least one count for the selected currency.';
            feedback.classList.remove('d-none');
            calculatorOpenButton.focus();
            return;
        }
        if (result.shortages.length) {
            event.preventDefault();
            calculatorOpenButton.focus();
            return;
        }
        feedback.classList.add('d-none');
    });
    document.addEventListener('keydown', function(event) {
        if (event.key !== 'Escape') return;
        if (cashInCalculator.classList.contains('show')) {
            hideCashInCalculator();
        } else if (cashInModal.classList.contains('show')) {
            hideCashInModal();
        }
    });
    refreshCashInCalculator();
}

const vaultMetadataEditModal = document.querySelector('[data-vault-metadata-edit-modal]');
if (vaultMetadataEditModal) {
    let metadataEditBackdrop = null;
    const branchSelect = vaultMetadataEditModal.querySelector('[data-vault-metadata-branch]');
    const custodianSelect = vaultMetadataEditModal.querySelector('[data-vault-metadata-custodian]');
    const ghsLimitInput = vaultMetadataEditModal.querySelector('[name="limit_ghs"]');
    const cfaLimitInput = vaultMetadataEditModal.querySelector('[name="limit_cfa"]');
    const totalLimitInput = vaultMetadataEditModal.querySelector('#vaultMetadataTotalLimit');
    const cfaRate = Number(<?= json_encode((float)$currentCfaRate) ?>);

    function refreshMetadataCustodians() {
        Array.from(custodianSelect.options).forEach(function(option) {
            option.hidden = option.dataset.branch !== branchSelect.value;
        });
        const selected = custodianSelect.options[custodianSelect.selectedIndex];
        if (!selected || selected.dataset.branch !== branchSelect.value) {
            const firstMatching = Array.from(custodianSelect.options).find(function(option) {
                return option.dataset.branch === branchSelect.value;
            });
            custodianSelect.value = firstMatching ? firstMatching.value : '';
        }
    }

    function refreshMetadataLimit() {
        const ghs = Math.max(0, Number(ghsLimitInput.value || 0));
        const cfa = Math.max(0, Number(cfaLimitInput.value || 0));
        totalLimitInput.value = (ghs + cfa * cfaRate / 1000).toFixed(2);
    }

    function openMetadataEditModal() {
        metadataEditBackdrop = document.createElement('div');
        metadataEditBackdrop.className = 'modal-backdrop fade show';
        metadataEditBackdrop.addEventListener('click', closeMetadataEditModal);
        document.body.appendChild(metadataEditBackdrop);
        document.body.classList.add('modal-open');
        vaultMetadataEditModal.classList.add('is-open');
        vaultMetadataEditModal.setAttribute('aria-hidden', 'false');
        const firstInput = vaultMetadataEditModal.querySelector('input:not([type="hidden"]), select');
        if (firstInput) firstInput.focus();
    }

    function closeMetadataEditModal() {
        vaultMetadataEditModal.classList.remove('is-open');
        vaultMetadataEditModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        if (metadataEditBackdrop) {
            metadataEditBackdrop.remove();
            metadataEditBackdrop = null;
        }
    }

    document.querySelectorAll('[data-vault-metadata-edit-open]').forEach(function(button) {
        button.addEventListener('click', openMetadataEditModal);
    });
    vaultMetadataEditModal.querySelectorAll('[data-vault-metadata-edit-close]').forEach(function(button) {
        button.addEventListener('click', closeMetadataEditModal);
    });
    vaultMetadataEditModal.addEventListener('click', function(event) {
        if (event.target === vaultMetadataEditModal) closeMetadataEditModal();
    });
    branchSelect.addEventListener('change', refreshMetadataCustodians);
    ghsLimitInput.addEventListener('input', refreshMetadataLimit);
    cfaLimitInput.addEventListener('input', refreshMetadataLimit);
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && vaultMetadataEditModal.classList.contains('is-open')) {
            closeMetadataEditModal();
        }
    });
    refreshMetadataCustodians();
    refreshMetadataLimit();
}
</script>
<?php
$adminRoute  = $admin_route  ?? 'admin';
$pluginRoute = $plugin_route ?? 'payment_services';
$canViewDetails = user_can('view_payment_service_details');
$canEdit = user_can('edit_payment_service');

$exportQuery = $_GET ?? [];
unset($exportQuery['url']);
?>

<style>
    .ps-page { background: var(--bs-body-bg); color: var(--bs-body-color); }
    .ps-hero {
        border-radius: 1.25rem;
        padding: 2rem;
        border: 1px solid var(--bs-border-color);
        box-shadow: 0 25px 45px rgba(15,23,42,.08);
        background:
            radial-gradient(circle at top left, rgba(56,189,248,.35), transparent 55%),
            radial-gradient(circle at bottom right, rgba(250,204,21,.4), transparent 55%),
            linear-gradient(135deg, rgba(15,23,42,.96), rgba(15,23,42,.9));
        color: var(--bs-body-bg);
    }
    body[data-bs-theme="light"] .ps-hero {
        background:
            radial-gradient(circle at top left, rgba(56,189,248,.25), transparent 55%),
            radial-gradient(circle at bottom right, rgba(250,204,21,.32), transparent 55%),
            linear-gradient(135deg, #80a2f0ff, #dcf86fff);
    }
    .ps-hero h1 { font-weight: 600; }
    .ps-hero-metric {
        border-radius: 1.25rem;
        padding: 1.5rem;
        border: 1px solid rgba(148,163,184,.35);
        box-shadow: 0 18px 40px rgba(15,23,42,.6);
    }
    body[data-bs-theme="light"] .ps-hero-metric {
        background:
            linear-gradient(135deg, rgba(15,23,42,.96), rgba(15,23,42,.9));
        border-color: rgba(148,163,184,.45);
    }
    .ps-hero-label {
        text-transform: uppercase;
        letter-spacing: .08em;
        font-size: .7rem;
        font-weight: 600;
    }
    .ps-hero-value { font-size: 1.6rem; font-weight: 700; }
    .ps-hero-meta { font-size: .85rem; color: yellow; }
    .ps-hero-icon {
        width:40px;height:40px;border-radius:999px;
        display:inline-flex;align-items:center;justify-content:center;
        background:rgba(34,211,238,.18);color:var(--bs-primary);
    }
    .ps-pulse {
        display:inline-flex;align-items:center;gap:.35rem;
        font-size:.85rem;color:var(--bs-success);
    }
    .ps-pulse::before {
        content:"";width:8px;height:8px;border-radius:999px;
        background:currentColor;animation:psPulse 1.6s infinite;
    }
    @keyframes psPulse {
        0%{opacity:.2;transform:scale(.8);}
        50%{opacity:1;transform:scale(1.1);}
        100%{opacity:.2;transform:scale(.8);}
    }
    .ps-actions .btn { border-radius:999px;font-weight:500; }
    .ps-filters label {
        font-size:.78rem;text-transform:uppercase;
        letter-spacing:.08em;font-weight:600;
    }
    .ps-selected-filters {
        background:rgba(15,23,42,.02);
        border:1px dashed rgba(15,23,42,.15);
        border-radius:1rem;
        padding:1rem;
    }
    .ps-filter-empty { color:var(--bs-secondary-color);font-size:.9rem; }
    .ps-tab-card {
        border-radius:1.25rem;
        border:1px solid var(--bs-border-color);
        box-shadow:0 25px 45px rgba(15,23,42,.08);
    }
    .active,
    #psTabs .nav-link.active,
    #psTabs .nav-link.active:focus,
    #psTabs .nav-link.active:hover,
    #psTabs .nav-item.show .nav-link,
    #psTabs .nav-item.show .nav-link:focus,
    #psTabs .nav-item.show .nav-link:hover {
        background-image: linear-gradient(135deg, rgba(var(--bs-primary-rgb, 13,110,253),0.12), rgba(var(--bs-warning-rgb, 255,193,7),0.12)) !important;
        background-color: transparent !important;
        color: var(--bs-body-color) !important;
        border-color: rgba(var(--bs-primary-rgb,13,110,253), 0.25) !important;
    }
    .ps-table thead th {
        text-transform:uppercase;
        font-size:.72rem;
        letter-spacing:.08em;
        border-top:0;
        border-bottom:1px solid var(--bs-border-color);
        background:rgba(var(--bs-secondary-rgb,148,163,184),.12);
        color:var(--bs-secondary-color);
        padding-top:.65rem;
        padding-bottom:.55rem;
    }
    .ps-table tbody tr { transition:background-color .15s ease; }
    .ps-table tbody tr:hover {
        background:rgba(var(--bs-primary-rgb,13,110,253),.05);
    }
    .ps-table tbody td {
        vertical-align:middle;
        border-color:rgba(148,163,184,.18);
        padding-top:.55rem;padding-bottom:.55rem;
        font-size:.86rem;
    }
    .ps-table tfoot th {
        border-top:1px solid var(--bs-border-color);
        font-weight:600;
    }
    .ps-mobile-details{min-width:260px;}
    .ps-mobile-amounts{min-width:140px;}
    .ps-amount-stack{display:flex;flex-direction:column;gap:2px;align-items:flex-end;}
    .ps-loader {
        position:absolute;inset:0;
        display:flex;align-items:center;justify-content:center;
        background:rgba(255,255,255,.85);
        backdrop-filter:blur(3px);
        z-index:3;
        transition:opacity .2s ease;
    }
    .ps-loader.hidden { opacity:0;pointer-events:none; }
    .ps-empty,.ps-error{ padding:3rem 1rem;text-align:center; }
    .ps-error{ color:var(--bs-danger); }
    .ps-analysis-card{
        border-radius:1rem;
        border:1px solid var(--bs-border-color);
        padding:1.25rem;
        height:100%;
        background:linear-gradient(135deg,rgba(15,23,42,.02),rgba(15,23,42,.08));
        backdrop-filter:blur(2px);
    }
    body[data-bs-theme="dark"] .ps-analysis-card{
        background:radial-gradient(circle at top left,rgba(30,64,175,.34),rgba(15,23,42,1));
    }
    .ps-insights-pane{
        background:var(--bs-card-bg, var(--bs-body-bg));
        border-radius:1.25rem;
        padding:1.5rem;
        border:1px solid var(--bs-border-color);
        box-shadow:0 18px 40px rgba(15,23,42,.16);
    }
    .ps-list-group{ list-style:none;padding-left:0;margin-bottom:0; }
    .ps-list-group li+li{ border-top:1px solid rgba(148,163,184,.2); }
    .ps-avatar{
        width:32px;height:32px;border-radius:999px;
        background:rgba(var(--bs-warning-rgb,255,193,7),.2);
        display:inline-flex;align-items:center;justify-content:center;
        font-weight:600;color:var(--bs-warning);
    }
    .ps-filter-options{
        border:1px solid var(--bs-border-color);
        border-radius:.5rem;
        padding:.25rem .5rem;
        max-height:200px;
        overflow-y:auto;
        background:var(--bs-body-bg);
    }
    @media(max-width:992px){ .ps-hero{padding:1.5rem;} }
    @media (min-width:768px) and (max-width:991.98px){
        .ps-hero-value{font-size:1.35rem;}
        .ps-hero-metric{padding:1.25rem;}
        .ps-hero-icon{width:36px;height:36px;}
    }
</style>

<div class="ps-page">
    <div class="ps-hero mb-4">
        <div class="row align-items-center g-3">
            <div class="col-lg-8">
                <p class="text-uppercase text-warning mb-1 small fw-semibold">Payment services overview</p>
                <h1 class="mb-2 text-white">Payments cockpit</h1>
                <p class="mb-0 text-secondary">
                    Track utility, TV, tertiary and insurance payments with live cashdrawer-aware analytics.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <div class="ps-actions d-flex flex-wrap justify-content-lg-end gap-2">
                    <a href="<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>/export/excel?<?=http_build_query($exportQuery)?>" class="btn btn-outline-success">
                        <i class="fa fa-file-excel me-1"></i> Excel
                    </a>
                    <a href="<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>/export/pdf?<?=http_build_query($exportQuery)?>" class="btn btn-outline-danger">
                        <i class="fa fa-file-pdf me-1"></i> PDF
                    </a>
                    <a href="<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>/add" class="btn btn-warning text-dark">
                        <i class="fa fa-plus me-1"></i> New payment
                    </a>
                </div>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="ps-hero-metric h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="ps-hero-label text-white">Total volume</span>
                        <div class="ps-hero-icon"><i class="fa fa-receipt"></i></div>
                    </div>
                    <div class="ps-hero-value text-primary" data-ps-total-amount>GHS 0.00</div>
                    <div class="ps-hero-meta" data-ps-count>0 transactions</div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="ps-hero-metric h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="ps-hero-label">Total charges</span>
                        <div class="ps-hero-icon"><i class="fa fa-coins"></i></div>
                    </div>
                    <div class="ps-hero-value text-warning" data-ps-total-charge>GHS 0.00</div>
                    <div class="ps-hero-meta">Fees earned on payment services.</div>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="ps-hero-metric h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="ps-hero-label text-white">Latest activity</span>
                        <div class="ps-hero-icon"><i class="fa fa-broadcast-tower"></i></div>
                    </div>
                    <div class="ps-hero-value text-white" data-ps-latest-activity>Awaiting sync</div>
                    <span class="ps-pulse">Live</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-3 ps-filters">
        <div class="card-body">
            <form id="psFilterForm" class="row g-3 align-items-end">
                <div class="col-12 col-md-8 col-lg-4">
                    <label class="form-label">Search</label>
                    <div class="input-group rounded-pill overflow-hidden border">
                        <span class="input-group-text bg-transparent border-0 ps-3">
                            <i class="fa fa-search text-muted"></i>
                        </span>
                        <input type="text" class="form-control border-0" id="psSearch"
                               placeholder="Client ID, phone, institution, description">
                    </div>
                </div>
                <div class="col-6 col-md-2 col-lg-2">
                    <label class="form-label">Per page</label>
                    <select class="form-select w-auto" id="psLimit">
                        <option value="10">10</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div class="col-6 col-md-2 col-lg-2 d-flex gap-2">
                    <button type="button" id="psReset" class="btn btn-outline-secondary flex-fill">Reset</button>
                    <button type="submit" class="btn btn-outline-warning flex-fill d-none d-lg-inline-block">Apply</button>
                </div>
                <div class="col-12 col-lg-4 d-none d-lg-block">
                    <label class="form-label">Advanced filters</label>
                    <div class="dropdown w-100" data-bs-auto-close="outside">
                        <button class="btn btn-outline-secondary w-100 d-flex justify-content-between align-items-center"
                                type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span><i class="fa fa-sliders me-2"></i>Configure filters</span>
                            <i class="fa fa-chevron-down text-muted"></i>
                        </button>
                        <div class="dropdown-menu w-100 p-3" id="psFilterMenu">
                            <p class="text-muted small mb-2">
                                Toggle dimensions to filter by, then pick values below.
                            </p>
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="form-check mb-1">
                                        <input class="form-check-input" type="checkbox" value="date"
                                               id="psOptDate" data-filter-toggle="date">
                                        <label class="form-check-label" for="psOptDate">Date range</label>
                                    </div>
                                    <div class="form-check mb-1">
                                        <input class="form-check-input" type="checkbox" value="category"
                                               id="psOptCategory" data-filter-toggle="category">
                                        <label class="form-check-label" for="psOptCategory">Category</label>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-check mb-1">
                                        <input class="form-check-input" type="checkbox" value="payment_medium"
                                               id="psOptMedium" data-filter-toggle="payment_medium">
                                        <label class="form-check-label" for="psOptMedium">Payment medium</label>
                                    </div>
                                    <div class="form-check mb-1">
                                        <input class="form-check-input" type="checkbox" value="network"
                                               id="psOptNetwork" data-filter-toggle="network">
                                        <label class="form-check-label" for="psOptNetwork">Network</label>
                                    </div>
                                    <div class="form-check mb-1">
                                        <input class="form-check-input" type="checkbox" value="transaction_status"
                                               id="psOptStatus" data-filter-toggle="transaction_status">
                                        <label class="form-check-label" for="psOptStatus">Status</label>
                                    </div>
                                </div>
                            </div>
                            <hr class="my-2" />
                            <div class="small text-muted">
                                Active filter values appear as chips below.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 mt-1 d-none d-lg-block">
                    <div class="ps-selected-filters d-flex flex-wrap gap-2" id="psFilterChips">
                        <span class="ps-filter-empty">
                            <i class="fa fa-circle-xmark me-1"></i>No advanced filters applied.
                        </span>
                    </div>
                </div>
                <div class="col-12 d-none d-lg-block">
                    <div class="row g-2" id="psFilterFields"></div>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm ps-tab-card mt-3">
        <div class="card-header border-0 pb-0 bg-transparent">
            <ul class="nav nav-tabs card-header-tabs" id="psTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="ps-tab-transactions" data-bs-toggle="tab"
                            data-bs-target="#psPaneTransactions" type="button" role="tab"
                            aria-controls="psPaneTransactions" aria-selected="true">
                        <i class="fa fa-table me-1"></i> Transactions
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="ps-tab-insights" data-bs-toggle="tab"
                            data-bs-target="#psPaneInsights" type="button" role="tab"
                            aria-controls="psPaneInsights" aria-selected="false">
                        <i class="fa fa-chart-line me-1"></i> Insights & performance
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body tab-content">
            <div class="tab-pane fade show active" id="psPaneTransactions" role="tabpanel"
                 aria-labelledby="ps-tab-transactions">
                <div class="position-relative">
                    <div class="ps-loader hidden" id="psLoader">
                        <div class="text-center">
                            <div class="spinner-border text-primary mb-2" role="status"></div>
                            <p class="text-muted mb-0">Fetching latest payments…</p>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle ps-table mb-0">
                            <thead>
                                <tr>
                                    <th class="d-none d-lg-table-cell"># / Ref</th>
                                    <th class="d-none d-lg-table-cell">Date</th>
                                    <th class="d-none d-lg-table-cell">Category / Institution</th>
                                    <th class="d-none d-lg-table-cell">Medium</th>
                                    <th class="d-none d-lg-table-cell">Status</th>
                                    <th class="text-end d-none d-lg-table-cell">Amount / Charge</th>
                                    <th class="d-none d-lg-table-cell">Staff</th>
                                    <th class="text-end d-none d-lg-table-cell">Actions</th>
                                    <th class="d-lg-none">Details</th>
                                    <th class="text-end d-lg-none">Amounts</th>
                                    <th class="text-end d-lg-none">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="psTableBody"></tbody>
                            <tfoot id="psTableFooter"></tfoot>
                        </table>
                    </div>
                    <div class="ps-empty text-muted d-none" id="psEmpty">
                        <i class="fa fa-box-open fa-2x mb-2"></i>
                        <p class="mb-0">No payment services match the current filters.</p>
                    </div>
                    <div class="ps-error d-none" id="psError"></div>
                </div>
                <div class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <small class="text-muted">
                        Showing <span data-ps-result-count>0</span> of
                        <span data-ps-total-count>0</span> payments
                    </small>
                    <nav aria-label="Payment Services pagination">
                        <ul class="pagination justify-content-center mb-0" id="psPagination"></ul>
                    </nav>
                </div>
            </div>

            <div class="tab-pane fade" id="psPaneInsights" role="tabpanel" aria-labelledby="ps-tab-insights">
                <div class="ps-insights-pane mt-2">
                    <div class="row g-3">
                        <div class="col-lg-4">
                            <div class="ps-analysis-card h-100">
                                <p class="text-uppercase text-muted small mb-1">Avg ticket</p>
                                <h4 data-ps-avg-ticket>GHS 0.00</h4>
                                <p class="text-muted mb-0">Average payment amount.</p>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="ps-analysis-card h-100">
                                <p class="text-uppercase text-muted small mb-1">Charge rate</p>
                                <h4 data-ps-charge-rate>0%</h4>
                                <p class="text-muted mb-0">Charges relative to total volume.</p>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="ps-analysis-card h-100">
                                <p class="text-uppercase text-muted small mb-1">Latest sync</p>
                                <h4 data-ps-last-sync>—</h4>
                                <p class="text-muted mb-0">Timestamp from the data endpoint.</p>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-xl-6">
                            <div class="ps-analysis-card h-100">
                                <h5 class="mb-2">Top staff</h5>
                                <ul class="ps-list-group" id="psTopAgents"></ul>
                            </div>
                        </div>
                        <div class="col-xl-6">
                            <div class="ps-analysis-card h-100">
                                <h5 class="mb-2">Status breakdown</h5>
                                <ul class="ps-list-group" id="psStatusBreakdown"></ul>
                            </div>
                        </div>
                    </div>

                    <div class="ps-analysis-card mt-3">
                        <h5 class="mb-2">7-day trend</h5>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Volume</th>
                                        <th>Transactions</th>
                                    </tr>
                                </thead>
                                <tbody id="psTrend"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div> <!-- card -->
</div> <!-- ps-page -->

<script>
(function(){
    const DATA_ENDPOINT = '<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>/data';
    const VIEW_BASE    = '<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>';
    const canView      = <?= $canViewDetails ? 'true' : 'false' ?>;
    const canEdit      = <?= $canEdit ? 'true' : 'false' ?>;

    const refs = {
        body: document.getElementById('psTableBody'),
        footer: document.getElementById('psTableFooter'),
        pagination: document.getElementById('psPagination'),
        loader: document.getElementById('psLoader'),
        empty: document.getElementById('psEmpty'),
        error: document.getElementById('psError'),
        search: document.getElementById('psSearch'),
        limit: document.getElementById('psLimit'),
        filterForm: document.getElementById('psFilterForm'),
        filterMenu: document.getElementById('psFilterMenu'),
        filterChips: document.getElementById('psFilterChips'),
        filterFields: document.getElementById('psFilterFields'),
        reset: document.getElementById('psReset'),
        statAmount: document.querySelector('[data-ps-total-amount]'),
        statCharge: document.querySelector('[data-ps-total-charge]'),
        statCount: document.querySelector('[data-ps-count]'),
        latestActivity: document.querySelector('[data-ps-latest-activity]'),
        resultCount: document.querySelector('[data-ps-result-count]'),
        totalCount: document.querySelector('[data-ps-total-count]'),
        avgTicket: document.querySelector('[data-ps-avg-ticket]'),
        chargeRate: document.querySelector('[data-ps-charge-rate]'),
        lastSync: document.querySelector('[data-ps-last-sync]'),
        topAgents: document.getElementById('psTopAgents'),
        statusBreakdown: document.getElementById('psStatusBreakdown'),
        trend: document.getElementById('psTrend'),
    };

    const state = {
        page: 1,
        limit: 25,
        find: '',
        filters: {
            date_from: '',
            date_to: '',
            category: [],
            payment_medium: [],
            network: [],
            transaction_status: []
        }
    };

    const FILTER_LABELS = {
        date_from: 'From',
        date_to: 'To',
        category: 'Category',
        payment_medium: 'Medium',
        network: 'Network',
        transaction_status: 'Status',
    };

    function ensureFilterSections(){
        if(!refs.filterFields || !refs.filterMenu) return;
        const toggles = refs.filterMenu.querySelectorAll('[data-filter-toggle]');
        toggles.forEach(toggle => {
            const key = toggle.getAttribute('data-filter-toggle');
            if(!key) return;
            const existing = refs.filterFields.querySelector(`[data-filter-section="${key}"]`);
            if(toggle.checked && !existing){
                const col = document.createElement('div');
                col.className = 'col-12 col-md-6 col-xl-3';
                col.setAttribute('data-filter-section', key);

                if(key === 'date'){
                    col.innerHTML = `
                        <label class="form-label small text-uppercase text-muted mb-1">Date range</label>
                        <div class="d-flex gap-2">
                            <input type="date" class="form-control form-control-sm" id="psDateFrom">
                            <input type="date" class="form-control form-control-sm" id="psDateTo">
                        </div>
                    `;
                }else if(key === 'category'){
                    col.innerHTML = `
                        <label class="form-label small text-uppercase text-muted mb-1">Category</label>
                        <input type="text" class="form-control form-control-sm mb-1" placeholder="Search category..." id="psCategorySearch">
                        <div id="psCategoryOptions" class="small ps-filter-options">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="UTILITY" id="psCatUTILITY">
                                <label class="form-check-label" for="psCatUTILITY">UTILITY</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="TV & ENTERTAINMENT" id="psCatTV">
                                <label class="form-check-label" for="psCatTV">TV &amp; ENTERTAINMENT</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="TERTIARY" id="psCatTERTIARY">
                                <label class="form-check-label" for="psCatTERTIARY">TERTIARY</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="INSURANCE" id="psCatINS">
                                <label class="form-check-label" for="psCatINS">INSURANCE</label>
                            </div>
                        </div>
                    `;
                }else if(key === 'payment_medium'){
                    col.innerHTML = `
                        <label class="form-label small text-uppercase text-muted mb-1">Payment medium</label>
                        <input type="text" class="form-control form-control-sm mb-1" placeholder="Search medium..." id="psMediumSearch">
                        <div id="psMediumOptions" class="small ps-filter-options">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="Telecos" id="psMedTel">
                                <label class="form-check-label" for="psMedTel">Telecos</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="Bank" id="psMedBank">
                                <label class="form-check-label" for="psMedBank">Bank</label>
                            </div>
                        </div>
                    `;
                }else if(key === 'network'){
                    col.innerHTML = `
                        <label class="form-label small text-uppercase text-muted mb-1">Network</label>
                        <input type="text" class="form-control form-control-sm mb-1" placeholder="Search network..." id="psNetworkSearch">
                        <div id="psNetworkOptions" class="small ps-filter-options">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="MTN" id="psNetMTN">
                                <label class="form-check-label" for="psNetMTN">MTN</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="AT" id="psNetAT">
                                <label class="form-check-label" for="psNetAT">AT</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="Telecel" id="psNetTelecel">
                                <label class="form-check-label" for="psNetTelecel">Telecel</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="G-Money" id="psNetGM">
                                <label class="form-check-label" for="psNetGM">G-Money</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="GhanaPay" id="psNetGP">
                                <label class="form-check-label" for="psNetGP">GhanaPay</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="GHQR" id="psNetGHQR">
                                <label class="form-check-label" for="psNetGHQR">GHQR</label>
                            </div>
                        </div>
                    `;
                }else if(key === 'transaction_status'){
                    col.innerHTML = `
                        <label class="form-label small text-uppercase text-muted mb-1">Status</label>
                        <input type="text" class="form-control form-control-sm mb-1" placeholder="Search status..." id="psStatusSearch">
                        <div id="psStatusOptions" class="small ps-filter-options">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="pending" id="psStatusPending">
                                <label class="form-check-label" for="psStatusPending">Pending</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="approved" id="psStatusApproved">
                                <label class="form-check-label" for="psStatusApproved">Approved</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="rejected" id="psStatusRejected">
                                <label class="form-check-label" for="psStatusRejected">Rejected</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="declined" id="psStatusDeclined">
                                <label class="form-check-label" for="psStatusDeclined">Declined</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="reversed" id="psStatusReversed">
                                <label class="form-check-label" for="psStatusReversed">Reversed</label>
                            </div>
                        </div>
                    `;
                }
                refs.filterFields.appendChild(col);
            }else if(!toggle.checked && existing){
                existing.remove();
                if(key === 'date'){
                    state.filters.date_from = '';
                    state.filters.date_to = '';
                }else if(key === 'category'){
                    state.filters.category = [];
                }else if(key === 'payment_medium'){
                    state.filters.payment_medium = [];
                }else if(key === 'network'){
                    state.filters.network = [];
                }else if(key === 'transaction_status'){
                    state.filters.transaction_status = [];
                }
            }
        });
        wireFilterSectionInputs();
        syncChips();
    }

    function attachSearchableCheckboxList(searchInput, container){
        if(!searchInput || !container) return;
        searchInput.addEventListener('input', function(){
            const term = searchInput.value.toLowerCase();
            container.querySelectorAll('.form-check').forEach(row => {
                const label = row.querySelector('label');
                const text  = (label?.textContent || '').toLowerCase();
                row.style.display = term && !text.includes(term) ? 'none' : '';
            });
        });
    }

    function wireFilterSectionInputs(){
        const dateFrom = document.getElementById('psDateFrom');
        const dateTo   = document.getElementById('psDateTo');
        if(dateFrom){
            dateFrom.addEventListener('change', ()=>{
                state.filters.date_from = dateFrom.value || '';
                syncChips();
            });
        }
        if(dateTo){
            dateTo.addEventListener('change', ()=>{
                state.filters.date_to = dateTo.value || '';
                syncChips();
            });
        }

        // CATEGORY
        const catContainer = document.getElementById('psCategoryOptions');
        const catSearch    = document.getElementById('psCategorySearch');
        if(catContainer){
            attachSearchableCheckboxList(catSearch, catContainer);
            catContainer.querySelectorAll('input[type="checkbox"]').forEach(cb=>{
                cb.addEventListener('change', ()=>{
                    const selected = Array.from(
                        catContainer.querySelectorAll('input[type="checkbox"]:checked')
                    ).map(x=>x.value);
                    state.filters.category = selected;
                    syncChips();
                });
            });
        }

        // PAYMENT MEDIUM
        const medContainer = document.getElementById('psMediumOptions');
        const medSearch    = document.getElementById('psMediumSearch');
        if(medContainer){
            attachSearchableCheckboxList(medSearch, medContainer);
            medContainer.querySelectorAll('input[type="checkbox"]').forEach(cb=>{
                cb.addEventListener('change', ()=>{
                    const selected = Array.from(
                        medContainer.querySelectorAll('input[type="checkbox"]:checked')
                    ).map(x=>x.value);
                    state.filters.payment_medium = selected;
                    syncChips();
                });
            });
        }

        // NETWORK
        const netContainer = document.getElementById('psNetworkOptions');
        const netSearch    = document.getElementById('psNetworkSearch');
        if(netContainer){
            attachSearchableCheckboxList(netSearch, netContainer);
            netContainer.querySelectorAll('input[type="checkbox"]').forEach(cb=>{
                cb.addEventListener('change', ()=>{
                    const selected = Array.from(
                        netContainer.querySelectorAll('input[type="checkbox"]:checked')
                    ).map(x=>x.value);
                    state.filters.network = selected;
                    syncChips();
                });
            });
        }

        // STATUS
        const statusContainer = document.getElementById('psStatusOptions');
        const statusSearch    = document.getElementById('psStatusSearch');
        if(statusContainer){
            attachSearchableCheckboxList(statusSearch, statusContainer);
            statusContainer.querySelectorAll('input[type="checkbox"]').forEach(cb=>{
                cb.addEventListener('change', ()=>{
                    const selected = Array.from(
                        statusContainer.querySelectorAll('input[type="checkbox"]:checked')
                    ).map(x=>x.value);
                    state.filters.transaction_status = selected;
                    syncChips();
                });
            });
        }
    }

    function syncChips(){
        if(!refs.filterChips) return;
        refs.filterChips.innerHTML = '';

        const active = [];
        if(state.filters.date_from || state.filters.date_to){
            active.push({key:'date_range', label:'Date', value:`${state.filters.date_from || '…'} → ${state.filters.date_to || '…'}`});
        }
        (state.filters.category || []).forEach(v=>{
            active.push({key:'category', label:'Category', value:v});
        });
        (state.filters.payment_medium || []).forEach(v=>{
            active.push({key:'payment_medium', label:'Medium', value:v});
        });
        (state.filters.network || []).forEach(v=>{
            active.push({key:'network', label:'Network', value:v});
        });
        (state.filters.transaction_status || []).forEach(v=>{
            active.push({key:'transaction_status', label:'Status', value:v});
        });

        if(!active.length){
            const span = document.createElement('span');
            span.className = 'ps-filter-empty';
            span.innerHTML = '<i class="fa fa-circle-xmark me-1"></i>No advanced filters applied.';
            refs.filterChips.appendChild(span);
            return;
        }

        active.forEach((item, index)=>{
            const chip = document.createElement('span');
            chip.className = 'badge rounded-pill bg-light text-dark border d-flex align-items-center gap-1';
            chip.style.cursor = 'pointer';
            chip.dataset.key = item.key;
            chip.dataset.index = String(index);
            chip.innerHTML = `<span class="text-muted small">${item.label}:</span> <span>${item.value}</span> <i class="fa fa-times small"></i>`;
            chip.addEventListener('click', ()=>{
                removeChip(item);
            });
            refs.filterChips.appendChild(chip);
        });
    }

    function removeChip(item){
        if(item.key === 'date_range'){
            state.filters.date_from = '';
            state.filters.date_to = '';
            const dateFrom = document.getElementById('psDateFrom');
            const dateTo   = document.getElementById('psDateTo');
            if(dateFrom) dateFrom.value = '';
            if(dateTo)   dateTo.value   = '';
        }else if(item.key === 'category'){
            state.filters.category = (state.filters.category || []).filter(v=>v !== item.value);
            const container = document.getElementById('psCategoryOptions');
            if(container){
                container.querySelectorAll('input[type="checkbox"]').forEach(cb=>{
                    if(cb.value === item.value) cb.checked = false;
                });
            }
        }else if(item.key === 'payment_medium'){
            state.filters.payment_medium = (state.filters.payment_medium || []).filter(v=>v !== item.value);
            const container = document.getElementById('psMediumOptions');
            if(container){
                container.querySelectorAll('input[type="checkbox"]').forEach(cb=>{
                    if(cb.value === item.value) cb.checked = false;
                });
            }
        }else if(item.key === 'network'){
            state.filters.network = (state.filters.network || []).filter(v=>v !== item.value);
            const container = document.getElementById('psNetworkOptions');
            if(container){
                container.querySelectorAll('input[type="checkbox"]').forEach(cb=>{
                    if(cb.value === item.value) cb.checked = false;
                });
            }
        }else if(item.key === 'transaction_status'){
            state.filters.transaction_status = (state.filters.transaction_status || []).filter(v=>v !== item.value);
            const container = document.getElementById('psStatusOptions');
            if(container){
                container.querySelectorAll('input[type="checkbox"]').forEach(cb=>{
                    if(cb.value === item.value) cb.checked = false;
                });
            }
        }
        syncChips();
    }

    function formatCurrency(v){
        return new Intl.NumberFormat('en-GH',{
            style:'currency',currency:'GHS'
        }).format(Number(v||0));
    }
    function formatDate(v){
        if(!v) return '—';
        return new Date(v).toLocaleString('en-GB',{
            dateStyle:'medium',timeStyle:'short'
        });
    }
    function escapeHtml(value){
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
    function reasonHtml(reason){
        const text = String(reason || '').trim();
        if(!text) return '';
        return `<div class="small text-muted mt-1">Reason: ${escapeHtml(text)}</div>`;
    }
    function buildQuery(){
        const p=new URLSearchParams({page:state.page,limit:state.limit});
        if(state.find) p.append('find',state.find);
        if(state.filters.date_from) p.append('date_from',state.filters.date_from);
        if(state.filters.date_to)   p.append('date_to',state.filters.date_to);
        (state.filters.category||[]).forEach(v=>v&&p.append('category',v));
        (state.filters.payment_medium||[]).forEach(v=>v&&p.append('payment_medium',v));
        (state.filters.network||[]).forEach(v=>v&&p.append('network',v));
        (state.filters.transaction_status||[]).forEach(v=>v&&p.append('transaction_status',v));
        return p.toString();
    }
    function setLoading(show){
        if(!refs.loader) return;
        refs.loader.classList.toggle('hidden',!show);
    }

    async function fetchData(){
        setLoading(true);
        refs.error.classList.add('d-none');
        try{
            const res = await fetch(`${DATA_ENDPOINT}?${buildQuery()}`,{
                headers:{'Accept':'application/json'}
            });
            if(!res.ok){
                refs.error.textContent='Failed to load data';
                refs.error.classList.remove('d-none');
                return;
            }
            const payload = await res.json();
            if(!payload.success){
                refs.error.textContent = payload.message || 'Failed to load data';
                refs.error.classList.remove('d-none');
                return;
            }
            applyPayload(payload);
        }catch(e){
            refs.error.textContent='Unexpected error loading data';
            refs.error.classList.remove('d-none');
        }finally{
            setLoading(false);
        }
    }

    function applyPayload(payload){
        const {rows,totals,pagination,latest_activity,analysis} = payload;
        state.page  = pagination?.page  || 1;
        state.limit = pagination?.limit || state.limit;

        if(refs.statAmount) refs.statAmount.textContent = formatCurrency(totals?.total_amount||0);
        if(refs.statCharge) refs.statCharge.textContent = formatCurrency(totals?.total_charge||0);
        if(refs.statCount)  refs.statCount.textContent  = (totals?.count||0)+' transactions';
        if(refs.latestActivity)
            refs.latestActivity.textContent = latest_activity?formatDate(latest_activity):'—';
        if(refs.resultCount) refs.resultCount.textContent = (rows||[]).length;
        if(refs.totalCount)  refs.totalCount.textContent  = totals?.count||0;
        if(refs.avgTicket)   refs.avgTicket.textContent   = formatCurrency(analysis?.avg_ticket||0);
        if(refs.chargeRate)
            refs.chargeRate.textContent = `${((analysis?.charge_rate||0)*100).toFixed(1)}%`;
        if(refs.lastSync)
            refs.lastSync.textContent = latest_activity?formatDate(latest_activity):'—';

        renderRows(rows||[]);
        renderFooter(totals||{});
        renderPagination(pagination||{page:1,total_pages:1});
        renderStatusBreakdown(analysis?.status_breakdown||[]);
        renderTopAgents(analysis?.top_agents||[]);
        renderTrend(analysis?.trend||[]);
    }

    function renderRows(rows){
        if(!refs.body) return;
        refs.body.innerHTML='';
        if(!rows.length){
            refs.empty.classList.remove('d-none');

            return;
        }
        refs.empty.classList.add('d-none');
        rows.forEach(row=>{
            const tr=document.createElement('tr');
            const status=(row.transaction_status||'').toLowerCase();
            let badge='badge bg-secondary';
            if(status==='pending')  badge='badge bg-warning text-dark';
            else if(status==='approved') badge='badge bg-success';
            else if(status==='rejected' || status==='declined' || status==='reversed' || status==='cancelled' || status==='canceled') badge='badge bg-danger';

            const actions=[];
            if(canView){
                actions.push(
                    `<a href=\"${VIEW_BASE}/view/${row.id}\" class=\"btn btn-sm btn-outline-primary\"><i class=\"fa fa-eye\"></i></a>`
                );
            }
            if(canEdit && !['cancelled', 'canceled', 'declined', 'rejected', 'reversed'].includes(status)){
                actions.push(
                    `<a href=\"${VIEW_BASE}/edit/${row.id}\" class=\"btn btn-sm btn-outline-warning\"><i class=\"fa fa-pen\"></i></a>`
                );
            }
            if(!['cancelled', 'canceled', 'declined', 'rejected', 'reversed'].includes(status)){
                actions.push(
                    `<button type=\"button\" class=\"btn btn-sm btn-outline-danger js-direct-cancel\" data-id=\"${row.id}\" data-cancel-date=\"${String(row.date_created || '').slice(0, 10)}\" data-cancel-endpoint=\"${VIEW_BASE}/cancel\" data-cancel-label=\"Transaction #${row.id}\" aria-label=\"Cancel transaction #${row.id}\" title=\"Cancel transaction\"><i class=\"fa-solid fa-arrow-rotate-left\" aria-hidden=\"true\"></i></button>`
                );
            }

            const desktopActions = actions.join('');
            const mobileActions = actions.join('');

            tr.innerHTML=`
                <td class="d-none d-lg-table-cell">
                    <div class="d-flex flex-column">
                        <strong>#${row.id}</strong>
                        <small class="text-muted">${row.reference}</small>
                    </div>
                </td>
                <td class="d-none d-lg-table-cell">${formatDate(row.date_created)}</td>
                <td class="d-none d-lg-table-cell">
                    <div class="d-flex flex-column">
                        <strong>${row.category || '—'}</strong>
                        <small class="text-muted">${row.institution || ''}</small>
                    </div>
                </td>
                <td class="d-none d-lg-table-cell">
                    <div class="d-flex flex-column">
                        <span>${row.payment_medium || '—'}</span>
                        ${row.payment_medium && row.payment_medium.toLowerCase() === 'bank'
                            ? row.bank ? `<small class="text-muted">${row.bank}</small>` : ''
                            : row.network ? `<small class="text-muted">${row.network}</small>` : ''}
                    </div>
                </td>
                <td class="d-none d-lg-table-cell"><span class="${badge}">${row.transaction_status || 'Unknown'}</span>${reasonHtml(row.rejection_reason)}</td>
                <td class="text-end d-none d-lg-table-cell">
                    <strong>${formatCurrency(row.amount)}</strong>
                    ${row.charge ? `<div><small class="text-muted">Charge: ${formatCurrency(row.charge)}</small></div>` : ''}
                </td>
                <td class="d-none d-lg-table-cell">
                    <div class="d-flex align-items-center gap-2">
                        <span class="ps-avatar">${(row.agent?.name || '??').slice(0,2).toUpperCase()}</span>
                        <div>
                            <strong>${row.agent?.name || '—'}</strong>
                            <small class="text-muted">ID: ${row.agent?.id || '—'}</small>
                        </div>
                    </div>
                </td>
                <td class="text-end d-none d-lg-table-cell">
                    <div class="d-inline-flex justify-content-end gap-2 flex-wrap">${desktopActions}</div>
                </td>

                <td class="d-lg-none ps-mobile-details">
                    <div class="fw-semibold">#${row.id} · ${formatDate(row.date_created)}</div>
                    <div class="text-muted small">${row.reference || ''}</div>
                    <div class="mt-1">
                        <div class="fw-semibold">${row.category || '—'}</div>
                        <div class="text-muted small">${row.institution || ''}</div>
                    </div>
                    <div class="text-muted small mt-1">
                        ${(row.payment_medium || '—')}${row.payment_medium && row.payment_medium.toLowerCase() === 'bank'
                            ? row.bank ? ` · ${row.bank}` : ''
                            : row.network ? ` · ${row.network}` : ''}
                    </div>
                    <div class="mt-1"><span class="${badge}">${row.transaction_status || 'Unknown'}</span>${reasonHtml(row.rejection_reason)}</div>
                    <div class="text-muted small mt-1">Staff: ${row.agent?.name || '—'}</div>
                </td>
                <td class="text-end d-lg-none ps-mobile-amounts">
                    <div class="ps-amount-stack">
                        <div>
                            <small class="text-muted d-block">Amount</small>
                            <span class="fw-semibold">${formatCurrency(row.amount)}</span>
                        </div>
                        <div>
                            <small class="text-muted d-block">Charge</small>
                            <span class="fw-semibold">${formatCurrency(row.charge || 0)}</span>
                        </div>
                    </div>
                </td>
                <td class="text-end d-lg-none">
                    <div class="d-inline-flex justify-content-end gap-2 flex-wrap">${mobileActions}</div>
                </td>
            `;
            refs.body.appendChild(tr);
        });
    }

    function renderFooter(totals){
        if(!refs.footer) return;
        refs.footer.innerHTML=`
            <tr>
                <th class="d-none d-lg-table-cell"></th>
                <th class="d-none d-lg-table-cell"></th>
                <th class="d-none d-lg-table-cell">TOTAL :</th>
                <th class="d-none d-lg-table-cell"></th>
                <th class="d-none d-lg-table-cell"></th>
                <th class="text-end d-none d-lg-table-cell">${Number(totals.total_amount||0).toFixed(2)}</th>
                <th class="d-none d-lg-table-cell"></th>
                <th class="d-none d-lg-table-cell"></th>
                <th class="d-lg-none text-end" colspan="3">TOTAL: ${Number(totals.total_amount||0).toFixed(2)} · Charge: ${Number(totals.total_charge||0).toFixed(2)}</th>
            </tr>`;
    }

    function renderPagination(p){
        if(!refs.pagination) return;
        const total = p.total_pages || 1;
        const cur   = p.page || 1;
        const items=[];
        const make=(page,label=page,disabled=false,active=false)=>`
            <li class="page-item ${disabled?'disabled':''} ${active?'active':''}">
                <button class="page-link" data-page="${page}" ${disabled?'disabled':''}>${label}</button>
            </li>`;
        items.push(make(cur-1,'&laquo;',cur===1));
        for(let i=1;i<=total;i++){
            if(i===1||i===total||Math.abs(i-cur)<=1){
                items.push(make(i,i,false,i===cur));
            }else if(Math.abs(i-cur)===2){
                items.push('<li class="page-item disabled"><span class="page-link">…</span></li>');
            }
        }
        items.push(make(cur+1,'&raquo;',cur===total));
        refs.pagination.innerHTML=items.join('');
    }

    function renderTopAgents(list){
        if(!refs.topAgents) return;
        if(!list.length){
            refs.topAgents.innerHTML='<li class="text-muted small">No staff data.</li>';
            return;
        }
        refs.topAgents.innerHTML=list.map(a=>`
            <li class="py-2 d-flex justify-content-between">
                <div>
                    <strong>${a.name || '—'}</strong>
                    <div class="text-muted small">${a.count || 0} txns</div>
                </div>
                <div class="text-end">${formatCurrency(a.total || 0)}</div>
            </li>`).join('');
    }

    function renderStatusBreakdown(items){
        if(!refs.statusBreakdown) return;
        if(!items.length){
            refs.statusBreakdown.innerHTML='<li class="text-muted small">No status data.</li>';
            return;
        }
        refs.statusBreakdown.innerHTML=items.map(it=>`
            <li class="py-2 d-flex justify-content-between align-items-center">
                <div>
                    <strong>${it.label || 'Unknown'}</strong>
                    <div class="text-muted small">${it.count || 0} txns</div>
                </div>
                <div class="text-end">${formatCurrency(it.amount || 0)}</div>
            </li>`).join('');
    }

    function renderTrend(trend){
        if(!refs.trend) return;
        if(!trend.length){
            refs.trend.innerHTML='<tr><td colspan="3" class="text-muted small">No trend data.</td></tr>';
            return;
        }
        refs.trend.innerHTML=trend.map(t=>`
            <tr>
                <td>${t.label}</td>
                <td>${formatCurrency(t.amount || 0)}</td>
                <td>${t.count || 0}</td>
            </tr>`).join('');
    }

    document.addEventListener('DOMContentLoaded',()=>{
        if(refs.search){
            refs.search.addEventListener('input',()=>{
                state.find = refs.search.value.trim();
                state.page = 1;
                fetchData();
            });
        }
        if(refs.limit){
            refs.limit.addEventListener('change',()=>{
                state.limit = parseInt(refs.limit.value||'25',10) || 25;
                state.page  = 1;
                fetchData();
            });
        }
        if(refs.filterForm){
            refs.filterForm.addEventListener('submit',e=>{
                e.preventDefault();
                state.page = 1;
                fetchData();
            });
        }
        if(refs.filterMenu){
            refs.filterMenu.addEventListener('change',e=>{
                const target = e.target;
                if(!target.matches('[data-filter-toggle]')) return;
                ensureFilterSections();
            });
        }
        if(refs.pagination){
            refs.pagination.addEventListener('click',e=>{
                const btn=e.target.closest('[data-page]');
                if(!btn) return;
                const page=parseInt(btn.getAttribute('data-page'),10)||1;

                state.page=page;
                fetchData();
            });
        }
        if(refs.reset){
            refs.reset.addEventListener('click',()=>{
                state.page=1;
                state.limit=25;
                state.find='';
                if(refs.search) refs.search.value='';
                if(refs.limit)  refs.limit.value='25';
                state.filters.date_from = '';
                state.filters.date_to = '';
                state.filters.category = [];
                state.filters.payment_medium = [];
                state.filters.network = [];
                state.filters.transaction_status = [];

                if(refs.filterMenu){
                    refs.filterMenu.querySelectorAll('[data-filter-toggle]').forEach(cb=>{
                        cb.checked = false;
                    });
                }
                if(refs.filterFields){
                    refs.filterFields.innerHTML = '';
                }
                syncChips();
                fetchData();
            });
        }
        ensureFilterSections();
        syncChips();
        fetchData();
    });
})();
</script>
<div class="d-none" data-direct-cancel-csrf><?=csrf()?></div>
<script src="<?=ROOT?>/plugins/reversals/assets/direct-cancel.js?v=20261116-1"></script>

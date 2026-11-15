<style>
  /* Dashboard shell */
  .ops-dashboard-shell {
    margin-top: 1.5rem;
    margin-bottom: 2.5rem;
    padding: 1.75rem 1.5rem 2rem;
    border-radius: 1.25rem;
    box-shadow:
      0 16px 45px rgba(15,23,42,0.75),
      0 0 0 1px rgba(108,117,125,0.5);
    position: relative;
    overflow: hidden;
  }

  /* Slide-over drill panel */
  .ops-drill-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,0.75);
    display: none;
    align-items: stretch;
    justify-content: flex-end;
    z-index: 1050;
  }

  .ops-drill-overlay.active {
    display: flex;
  }

  .ops-drill-panel {
    width: min(480px, 100%);
    max-width: 100%;
    background: radial-gradient(circle at top left, rgba(30,64,175,0.28) 0, rgba(15,23,42,0.98) 55%);
    border-left: 1px solid rgba(148,163,184,0.5);
    box-shadow: -18px 0 40px rgba(15,23,42,0.9);
    padding: 1.25rem 1.25rem 1rem;
    color: #e5e7eb;
    overflow-y: auto;
  }

  .ops-drill-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    margin-bottom: .75rem;
  }

  .ops-drill-title {
    font-size: .9rem;
    letter-spacing: .12em;
    text-transform: uppercase;
  }

  .ops-drill-close {
    border: none;
    background: rgba(15,23,42,0.9);
    border-radius: 999px;
    color: #e5e7eb;
    width: 28px;
    height: 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
  }

  .ops-drill-metrics {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    margin-bottom: .75rem;
  }

  .ops-drill-pill {
    border-radius: .75rem;
    padding: .35rem .7rem;
    background: rgba(15,23,42,0.9);
    border: 1px solid rgba(148,163,184,0.6);
    font-size: .72rem;
    min-width: 0;
  }

  .ops-drill-pill small {
    display: block;
    color: #9ca3af;
    font-size: .7rem;
  }

  .ops-drill-pill span {
    font-weight: 600;
    color: #e5e7eb;
  }

  .ops-drill-section-title {
    font-size: .75rem;
    text-transform: uppercase;
    letter-spacing: .12em;
    color: #9ca3af;
    margin-bottom: .25rem;
  }

  .ops-drill-list {
    list-style: none;
    padding-left: 0;
    margin: 0;
    font-size: .78rem;
  }

  .ops-drill-list li {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    padding: .35rem 0;
    border-bottom: 1px solid rgba(31,41,55,0.8);
  }

  .ops-drill-list li:last-child {
    border-bottom: none;
  }

  .ops-dashboard-shell::before {
    content: "";
    position: absolute;
    inset: -40%;
    background:
      radial-gradient(circle at 0% 0%, rgba(129,140,248,0.28) 0, transparent 55%),
      radial-gradient(circle at 100% 100%, rgba(248,250,252,0.06) 0, transparent 55%);
    opacity: 0.6;
    filter: blur(4px);
    pointer-events: none;
    z-index: 0;
  }

  .ops-dashboard-shell > * {
    position: relative;
    z-index: 1;
  }

  /* Tabs styling */
  .ops-dashboard-tabs .nav-link {
    border: none;
    color: var(--bs-secondary-color, #ced4da) !important;
    font-weight: 600;
    letter-spacing: .04em;
    text-transform: uppercase;
    font-size: .8rem;
    padding: .75rem 1.25rem;
    position: relative;
    background-color: transparent !important;
  }

  .ops-dashboard-tabs .nav-link::after {
    content: "";
    position: absolute;
    left: 1.5rem;
    right: 1.5rem;
    bottom: .25rem;
    height: 2px;
    border-radius: 999px;
    background: linear-gradient(90deg,#60a5fa,#a855f7,#f97316);
    opacity: 0;
    transform: scaleX(.4);
    transform-origin: center;
    transition: all .22s ease-out;
  }



  .active,
  .ops-dashboard-tabs .nav-link.active,
  .ops-dashboard-tabs .nav-link.active:focus,
  .ops-dashboard-tabs .nav-link.active:hover,
  .ops-dashboard-tabs .show > .nav-link,
  .ops-dashboard-tabs .show > .nav-link:focus,
  .ops-dashboard-tabs .show > .nav-link:hover {
    background-image: linear-gradient(135deg, rgba(var(--bs-primary-rgb, 13,110,253),0.12), rgba(var(--bs-warning-rgb, 255,193,7),0.12)) !important;
    background-color: transparent !important;
    color: var(--bs-body-color) !important;
    border-color: rgba(var(--bs-primary-rgb,13,110,253), 0.25) !important;
  }

  .ops-dashboard-tabs .nav-link.active {
    color: #ffc107 !important;
  }

  .ops-dashboard-tabs .nav-link.active::after {
    opacity: 1;
    transform: scaleX(1);
  }

  .ops-tab-content {
    padding: 1.75rem 0 .25rem;
  }

  .ops-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4,minmax(0,1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
  }

  .ops-kpi-card {
    border-radius: 1rem;
    padding: 1rem 1.1rem;
    box-shadow: 0 14px 26px rgba(15,23,42,0.7);
    border: 1px solid rgba(108,117,125,0.5);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    position: relative;
    overflow: hidden;
    transition: transform .18s ease-out, box-shadow .18s ease-out, border-color .18s ease-out;
  }

  .ops-kpi-card:hover {
    transform: translateY(-2px) scale(1.01);
    box-shadow: 0 18px 34px rgba(15,23,42,0.8);
  }

  .ops-kpi-card > * {
    position: relative;
    z-index: 1;
  }

  .ops-kpi-icon {
    position: absolute;
    top: .9rem;
    right: 1rem;
    font-size: 1.2rem;
    opacity: .55;
  }

  .ops-kpi-label {
    font-size: .78rem;
    letter-spacing: .12em;
    text-transform: uppercase;
    margin-bottom: .1rem;
  }

  .ops-kpi-value {
    font-size: 1.4rem;
    font-weight: 700;
  }

  .ops-kpi-sub {
    font-size: .78rem;
  }

  .ops-kpi-trend {
    font-size: .78rem;
    font-weight: 500;
  }

  .ops-section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    margin-bottom: .85rem;
  }

  .ops-section-title {
    font-size: .95rem;
    text-transform: uppercase;
    letter-spacing: .16em;
    color: #adb5bd;
  }

  .ops-section-title span {
    color: #f8f9fa;
  }

  .ops-chip-row {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    margin-bottom: 1rem;
  }

  .ops-chip {
    border-radius: 999px;
    padding: .25rem .7rem;
    font-size: .75rem;
    border: 1px solid rgba(108,117,125,0.65);
    color: #f8f9fa;
    background: radial-gradient(circle at 0 0,rgba(73,80,87,0.5) 0,rgba(15,23,42,0.95) 55%);
  }

  .ops-chip span {
    color: #ffc107;
    font-weight: 600;
  }

  .ops-bar-track {
    height: .35rem;
    border-radius: 999px;
    background: rgba(15,23,42,0.96);
    overflow: hidden;
    margin-top: .25rem;
  }

  .ops-bar-fill {
    height: 100%;
    border-radius: inherit;
    background-image: linear-gradient(90deg,#22c55e,#a3e635,#facc15);
    transform-origin: left;
    transform: scaleX(0);
    animation: ops-bar-grow .7s ease-out forwards;
  }

  @keyframes ops-bar-grow {
    0% { transform: scaleX(0); }
    100% { transform: scaleX(1); }
  }

  /* Table wrapping helpers */
  tbody tr {
    white-space: nowrap;
  }

  .wrap,
  tbody tr.wrap {
    white-space: normal;
  }

  @media (max-width: 1200px) {
    .ops-kpi-grid {
      grid-template-columns: repeat(3,minmax(0,1fr));
    }
  }

  @media (max-width: 992px) {
    .ops-dashboard-shell {
      padding: 1.25rem 1rem 1.75rem;
    }

    .ops-kpi-grid {
      grid-template-columns: repeat(2,minmax(0,1fr));
    }
  }

  @media (max-width: 576px) {
    .ops-kpi-grid {
      grid-template-columns: minmax(0,1fr);
    }

    .ops-dashboard-tabs .nav-link {
      font-size: .75rem;
      padding-inline: .75rem;
    }
  }
</style>



<div class="col-lg-11 col-md-12 mx-auto ops-dashboard-shell">       
      <!-- Tabs Navigation -->
      <ul class="nav nav-tabs ops-dashboard-tabs" id="profileTabs" role="tablist">
        <!-- <li class="nav-item" role="presentation">
          <button class="nav-link active" id="account-tab" data-bs-toggle="tab" data-bs-target="#account" type="button" role="tab">ACCOUNTS </button>
        </li> -->
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="sims-tab" data-bs-toggle="tab" data-bs-target="#sims" type="button" role="tab">SIMS </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="cashdrawer-tab" data-bs-toggle="tab" data-bs-target="#cashdrawer" type="button" role="tab">CASH DRAWER </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="performance-tab" data-bs-toggle="tab" data-bs-target="#performance" type="button" role="tab">PERFORMANCE </button>
        </li>     
      </ul>

      <!-- Tabs Content -->
      <div class="tab-content ops-tab-content" id="profileTabsContent">
        <!-- Accounts Tab -->
        <div class="tab-pane fade show active" id="account" role="tabpanel" aria-labelledby="account-tab">
          <div class="ops-section-heading">
            <div>
              <div class="ops-section-title">ACCOUNTS <span>OVERVIEW</span></div>
              <small class="text-muted">Structure, categories and control of operational accounts.</small>
            </div>
          </div>

			     <?php require plugin_path('views/admin/accounts/account.php'); ?>     
        </div>
        
        <!-- Sims Tab -->
        <div class="tab-pane fade" id="sims" role="tabpanel" aria-labelledby="sims-tab">
          <div class="ops-section-heading">
            <div>
              <div class="ops-section-title">SIMS <span>INVENTORY</span></div>
              <small class="text-muted">Track inventory, assignment and sell-through by network.</small>
            </div>
          </div>

          <?php
            // Build lightweight SIM KPIs using existing $networks shape if available
            $opsTotalSims   = 0;
            $opsTotalSold   = 0;
            $opsPerNetwork  = [];

            if (!empty($networks) && is_array($networks)) {
                foreach ($networks as $netName => $data) {
                    $total = (int)($data['total'] ?? 0);
                    $sold  = (int)($data['sold'] ?? 0);
                    $opsTotalSims += $total;
                    $opsTotalSold += $sold;
                    $opsPerNetwork[$netName] = [
                        'total' => $total,
                        'sold'  => $sold,
                    ];
                }
            }
            $opsSellThrough = $opsTotalSims > 0 ? round(($opsTotalSold / $opsTotalSims) * 100, 1) : 0;
          ?>

          <?php if ($opsTotalSims > 0): ?>
            <div class="ops-kpi-grid mb-3">
              <div class="ops-kpi-card">
                <i class="fa-solid fa-sim-card ops-kpi-icon" aria-hidden="true"></i>
                <div class="ops-kpi-label">TOTAL SIMS</div>
                <div class="ops-kpi-value" id="opsSimsTotalValue"><?=number_format($opsTotalSims)?></div>
                <div class="ops-kpi-sub">Across all active networks</div>
              </div>
              <div class="ops-kpi-card">
                <i class="fa-solid fa-cart-shopping ops-kpi-icon" aria-hidden="true"></i>
                <div class="ops-kpi-label">SOLD</div>
                <div class="ops-kpi-value" id="opsSimsSoldValue"><?=number_format($opsTotalSold)?></div>
                <div class="ops-kpi-sub">Retail + wholesale</div>
              </div>
              <div class="ops-kpi-card">
                <i class="fa-solid fa-chart-line ops-kpi-icon" aria-hidden="true"></i>
                <div class="ops-kpi-label">SELL-THROUGH</div>
                <div class="ops-kpi-value" id="opsSimsSellValue"><?=$opsSellThrough?>%</div>
                <div class="ops-kpi-trend <?=($opsSellThrough >= 50 ? 'positive' : 'negative')?>" id="opsSimsSellTrend">
                  <?=($opsSellThrough >= 50 ? 'Healthy rotation' : 'Slow moving inventory')?>
                </div>
              </div>
              <div class="ops-kpi-card">
                <i class="fa-solid fa-tower-cell ops-kpi-icon" aria-hidden="true"></i>
                <div class="ops-kpi-label">ACTIVE NETWORKS</div>
                <div class="ops-kpi-value" id="opsSimsNetworksValue"><?=count($opsPerNetwork)?></div>
                <div class="ops-kpi-sub">With inventory assigned or sold</div>
              </div>
            </div>

            <div class="ops-section-heading">
              <div class="ops-section-title">NETWORK <span>BREAKDOWN</span></div>
            </div>
            <div class="ops-chip-row">
              <?php foreach ($opsPerNetwork as $name => $nData): 
                $nTotal = max(0,(int)($nData['total'] ?? 0));
                $nSold  = max(0,(int)($nData['sold'] ?? 0));
                $pct    = $nTotal > 0 ? round(($nSold / $nTotal) * 100, 1) : 0;
                $ratio  = $nTotal > 0 ? min(100, max(0, ($nSold / $nTotal) * 100)) : 0;
              ?>
                <div class="flex-fill" style="min-width: 180px; max-width: 260px;">
                  <div class="ops-chip d-flex justify-content-between align-items-center">
                    <span class="me-1"><?=strtoupper($name)?></span>
                    <small><?=$nSold?> / <?=$nTotal?> sold</small>
                  </div>
                  <div class="ops-bar-track">
                    <div class="ops-bar-fill" style="width: <?=$ratio?>%"></div>
                  </div>
                  <small class="text-muted d-block mt-1">Sell-through: <?=$pct?>%</small>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

		   <?php require plugin_path('views/admin/sims/list.php'); ?>
        </div>
        
        <!-- Cashdrawer Tab -->
        <div class="tab-pane fade" id="cashdrawer" role="tabpanel" aria-labelledby="cashdrawer-tab">
          <div class="ops-section-heading">
            <div>
              <div class="ops-section-title">CASHDRAWER <span>CONTROL</span></div>
              <small class="text-muted">Monitor total float, active drawers and export snapshots.</small>
            </div>
          </div>

		   <?php require plugin_path('views/admin/cashdrawers/list.php'); ?>
        </div>
        
        <!-- Performance Tab -->
        <div class="tab-pane fade" id="performance" role="tabpanel" aria-labelledby="performance-tab" id="opsPerformanceRoot" data-metrics-url="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/ajax_performance_metrics">
          <?php
            // Guard against undefined variables when coming from other routes
            $opsCashTotalPerf  = isset($cashdrawers_total) ? (float)$cashdrawers_total : 0.0;

            $opsTotalSimsPerf = 0;
            $opsTotalSoldPerf = 0;
            if (!empty($networks) && is_array($networks)) {
                foreach ($networks as $netName => $data) {
                    $opsTotalSimsPerf += (int)($data['total'] ?? 0);
                    $opsTotalSoldPerf += (int)($data['sold'] ?? 0);
                }
            }
            $opsSellThroughPerf = $opsTotalSimsPerf > 0 ? round(($opsTotalSoldPerf / $opsTotalSimsPerf) * 100, 1) : 0;
          ?>

          <div class="ops-section-heading mb-3">
            <div>
              <div class="ops-section-title">OPERATIONS <span>PERFORMANCE</span></div>
              <small class="text-muted ops-period-caption">Performance metrics fully driven by cashdrawers, SIMs and transactions (current view).</small>
            </div>
            <div class="btn-group btn-group-sm" role="group" aria-label="Performance period filter">
              <button type="button" class="btn btn-outline-secondary ops-period-btn active" data-period="today">Today</button>
              <button type="button" class="btn btn-outline-secondary ops-period-btn" data-period="7d">Last 7 days</button>
              <button type="button" class="btn btn-outline-secondary ops-period-btn" data-period="30d">Last 30 days</button>
              <button type="button" class="btn btn-outline-secondary ops-period-btn" data-period="all">All time</button>
            </div>
          </div>

          <div class="ops-kpi-grid mb-4" id="opsPerformanceKpis">
            <div class="ops-kpi-card">
              <i class="fa-solid fa-cash-register ops-kpi-icon" aria-hidden="true"></i>
              <div class="ops-kpi-label">CASHDRAWER FLOAT</div>
              <div class="ops-kpi-value" id="opsCashFloatValue"><?=number_format($opsCashTotalPerf, 2)?></div>
              <div class="ops-kpi-sub">Opening amounts across drawers in scope</div>
            </div>
            <div class="ops-kpi-card">
              <i class="fa-solid fa-briefcase ops-kpi-icon" aria-hidden="true"></i>
              <div class="ops-kpi-label">ACTIVE DRAWERS TODAY</div>
              <div class="ops-kpi-value" id="opsActiveDrawersTodayValue">0</div>
              <div class="ops-kpi-sub">Drawers opened with todays date</div>
            </div>
            <div class="ops-kpi-card">
              <i class="fa-solid fa-calendar-days ops-kpi-icon" aria-hidden="true"></i>
              <div class="ops-kpi-label">DRAWERS IN PERIOD</div>
              <div class="ops-kpi-value" id="opsDrawerCountValue">0</div>
              <div class="ops-kpi-sub">Drawers opened in selected period</div>
            </div>
            <div class="ops-kpi-card">
              <i class="fa-solid fa-arrow-trend-up ops-kpi-icon" aria-hidden="true"></i>
              <div class="ops-kpi-label">SIMS SELL-THROUGH</div>
              <div class="ops-kpi-value" id="opsSellThroughValue"><?=$opsSellThroughPerf?>%</div>
              <div class="ops-kpi-trend <?=($opsSellThroughPerf >= 50 ? 'positive' : 'negative')?>" id="opsSellThroughTrend">
                <?=($opsSellThroughPerf >= 50 ? 'Inventory velocity is healthy' : 'Consider campaigns to move stock')?>
              </div>
              <div class="mt-1"><span id="opsSellBadge" class="badge rounded-pill bg-secondary" style="font-size:.7rem;">Calibrating  </span></div>
            </div>
          </div>

          <div class="ops-kpi-grid mb-3">
            <div class="ops-kpi-card">
              <i class="fa-solid fa-receipt ops-kpi-icon" aria-hidden="true"></i>
              <div class="ops-kpi-label">TOTAL TRANSACTIONS</div>
              <div class="ops-kpi-value" id="opsTxCountValue">0</div>
              <div class="ops-kpi-sub">All transactions in selected period</div>
            </div>
            <div class="ops-kpi-card">
              <i class="fa-solid fa-money-bill-transfer ops-kpi-icon" aria-hidden="true"></i>
              <div class="ops-kpi-label">TX VOLUME</div>
              <div class="ops-kpi-value" id="opsTxVolumeValue">0.00</div>
              <div class="ops-kpi-sub">Sum of transaction amounts</div>
            </div>
            <div class="ops-kpi-card">
              <i class="fa-solid fa-scale-balanced ops-kpi-icon" aria-hidden="true"></i>
              <div class="ops-kpi-label">AVG TICKET SIZE</div>
              <div class="ops-kpi-value" id="opsTxAvgValue">0.00</div>
              <div class="ops-kpi-sub">TX volume divided by count</div>
            </div>
            <div class="ops-kpi-card">
              <i class="fa-solid fa-gauge-high ops-kpi-icon" aria-hidden="true"></i>
              <div class="ops-kpi-label">CASH UTILISATION INDEX</div>
              <div class="ops-kpi-value" id="opsUtilIndexValue">0.0%</div>
              <div class="ops-kpi-sub">TX volume as a % of cashdrawer float</div>
              <div class="mt-1"><span id="opsUtilBadge" class="badge rounded-pill bg-secondary" style="font-size:.7rem;">Awaiting signal  </span></div>
            </div>
          </div>

          <div class="row g-3">
            <div class="col-lg-6">
              <div class="ops-kpi-card" style="min-height: 180px;">
                <i class="fa-solid fa-diagram-project ops-kpi-icon" aria-hidden="true"></i>
                <div class="ops-kpi-label">SIM PIPELINE</div>
                <div class="ops-kpi-sub mb-2">From unassigned stock to sold SIMs.</div>
                <div class="ops-bar-track mb-2">
                  <?php
                    $unassigned = max(0, $opsTotalSimsPerf - $opsTotalSoldPerf);
                    $totalPipe = max(1, $opsTotalSimsPerf);
                    $soldPct = round(($opsTotalSoldPerf / $totalPipe) * 100, 1);
                    $unassignedPct = 100 - $soldPct;
                  ?>
                  <div class="ops-bar-fill" id="opsPipelineBar" style="width: <?=$soldPct?>%; background-image: linear-gradient(90deg,#22c55e,#a3e635,#facc15);"></div>
                </div>
                <small class="d-block text-muted mb-1" id="opsPipelineSold">Sold: <?=number_format($opsTotalSoldPerf)?> (<?=$soldPct?>%)</small>
                <small class="d-block text-muted" id="opsPipelineUnassigned">Unassigned: <?=number_format($unassigned)?> (<?=$unassignedPct?>%)</small>
              </div>
            </div>

            <div class="col-lg-6">
              <div class="ops-kpi-card" style="min-height: 180px;">
                <i class="fa-solid fa-magnifying-glass-chart ops-kpi-icon" aria-hidden="true"></i>
                <div class="ops-kpi-label">CASHDRAWER LENS</div>
                <div class="ops-kpi-sub mb-2">Use this panel as a narrative guide when investigating drawers.</div>
                <ul class="mb-2" style="font-size:.8rem; padding-left: 1rem;">
                  <li>Start on <strong>Cashdrawer</strong> tab to inspect float per agent, history and PDF snapshots.</li>
                  <li>Drill into <strong>SIMS</strong> tab to confirm if cash movements align with SIM sales.</li>
                  <li>Reconcile against supporting <strong>transactions</strong> to confirm end-of-day positions.</li>
                </ul>
                <small class="text-muted">Future iterations can plug in channel-level margins, chargebacks and FX spreads.</small>
              </div>
            </div>
          </div>

          <div class="row g-3 mt-3">
            <div class="col-lg-5">
              <div class="ops-kpi-card" style="min-height: 220px;">
                <i class="fa-solid fa-ranking-star ops-kpi-icon" aria-hidden="true"></i>
                <div class="ops-kpi-label">USER PERFORMANCE RANKING</div>
                <div class="ops-kpi-sub mb-2">Top users by transaction volume in the selected period (ops-dashboard).</div>
                <div class="table-responsive" style="max-height: 160px; overflow-y: auto;">
                  <table class="table table-sm table-dark mb-0 align-middle">
                    <thead style="font-size:.75rem; text-transform:uppercase; letter-spacing:.08em;">
                      <tr>
                        <th scope="col">#</th>
                        <th scope="col">User</th>
                        <th scope="col" class="text-end">Tx Count</th>
                        <th scope="col" class="text-end">Volume</th>
                      </tr>
                    </thead>
                    <tbody id="opsUserRankingBody" data-drill-url="<?= ROOT ?>/admin/operations_accounts/ajax_user_performance" style="font-size:.8rem;"></tbody>
                  </table>
                </div>
              </div>
            </div>

            <div class="col-lg-7">
              <div class="ops-kpi-card" style="min-height: 220px;">
                <i class="fa-solid fa-chart-pie ops-kpi-icon" aria-hidden="true"></i>
                <div class="ops-kpi-label">SERVICE MIX PERFORMANCE</div>
                <div class="ops-kpi-sub mb-2">Progress bars compare volumes across key channels in the selected period.</div>
                <div id="opsServiceProgress" style="font-size:.78rem;"></div>
                <div id="opsPerfSummary" class="mt-2" style="font-size:.75rem;"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
  </div>

  <!-- Slide-over drill panel -->
  <div class="ops-drill-overlay" id="opsDrillOverlay">
    <div class="ops-drill-panel">
      <div class="ops-drill-header">
        <div>
          <div class="ops-drill-title" id="opsDrillTitle">OPS USER PERFORMANCE DRILL</div>
          <small class="text-muted" id="opsDrillSubtitle" style="font-size:.75rem;">Filtered view from current performance period.</small>
        </div>
        <button type="button" class="ops-drill-close" id="opsDrillClose" aria-label="Close">
          &times;
        </button>
      </div>
      <div id="opsDrillBody" style="font-size:.8rem;"></div>
    </div>
  </div>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const tabs = document.querySelectorAll("#profileTabs .nav-link");
  const periodButtons = document.querySelectorAll('.ops-period-btn');
  const periodCaption = document.querySelector('.ops-period-caption');
  const perfRoot = document.querySelector('#performance[data-metrics-url]');
  const seriesChips = document.querySelectorAll('.ops-series-chip');

  // Retrieve the stored active tab from localStorage
  const storedActiveTabId = localStorage.getItem("activeTab");

  // Set the active tab based on stored value or default to the first tab
  if (storedActiveTabId && document.getElementById(storedActiveTabId)) {
    activateTab(storedActiveTabId);
  } else {
    // Default to the first tab if no valid stored tab exists
    if (tabs.length > 0) {
      activateTab(tabs[0].id);
    }
  }

  // Add click event listeners to all tabs
  tabs.forEach(tab => {
    tab.addEventListener("click", function () {
      // Activate the clicked tab and save its state
      activateTab(this.id);
      localStorage.setItem("activeTab", this.id);
    });
  });

  // Performance period filter (front-end only)
  if (periodButtons.length && periodCaption) {
    periodButtons.forEach(btn => {
      btn.addEventListener('click', function () {
        const period = this.getAttribute('data-period') || 'today';

        periodButtons.forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        let label = 'current view';
        if (period === 'today') {
          label = 'today\'s performance';
        } else if (period === '7d') {
          label = 'the last 7 days';
        } else if (period === '30d') {
          label = 'the last 30 days';
        } else if (period === 'all') {
          label = 'all time';
        }

        periodCaption.textContent = 'A cross-cutting view of liquidity, inventory and utilisation (' + label + ').';

        if (perfRoot) {
          opsLoadPerformanceMetrics(perfRoot.getAttribute('data-metrics-url'), period);
        }
      });
    });
  }

  // Time-series chips were previously used for line chart; keep no-op wiring
  if (seriesChips.length) {
    seriesChips.forEach(chip => {
      chip.addEventListener('click', function () {
        // Intentionally left minimal – service mix view is now driven by totals
      });
    });
  }

  initOpsCashdrawersRegion();

  // Drill overlay close handlers
  const drillOverlay = document.getElementById('opsDrillOverlay');
  const drillClose = document.getElementById('opsDrillClose');
  if (drillOverlay && drillClose) {
    drillClose.addEventListener('click', function () {
      opsCloseDrill();
    });
    drillOverlay.addEventListener('click', function (e) {
      if (e.target === drillOverlay) {
        opsCloseDrill();
      }
    });
  }
});

// Function to activate a specific tab by ID and deactivate others
function activateTab(tabId) {
  const activeTab = document.getElementById(tabId);

  // Validate the tabId and its corresponding element
  if (!activeTab) {
    console.error(`Tab with ID "${tabId}" not found.`);
    return;
  }

  // Deactivate all tabs and panes
  document.querySelectorAll("#profileTabs .nav-link").forEach(tab => {
    tab.classList.remove("active");
  });
  document.querySelectorAll("#profileTabsContent .tab-pane").forEach(pane => {
    pane.classList.remove("show", "active");
  });

  // Activate the specified tab and its associated pane
  const activePane = document.querySelector(activeTab.getAttribute("data-bs-target"));
  if (activePane) {
    activeTab.classList.add("active");
    activePane.classList.add("show", "active");
    if (activePane.id === 'performance') {
      const perfRoot = activePane.matches('[data-metrics-url]')
        ? activePane
        : activePane.querySelector('[data-metrics-url]');
      const activePeriodBtn = activePane.querySelector('.ops-period-btn.active');
      const period = activePeriodBtn ? activePeriodBtn.getAttribute('data-period') || 'today' : 'today';
      if (perfRoot && typeof opsLoadPerformanceMetrics === 'function') {
        opsLoadPerformanceMetrics(perfRoot.getAttribute('data-metrics-url'), period);
      }
    }
  } else {
    console.error(`Pane for tab "${tabId}" not found.`);
  }
}

function opsFormatMoney(value) {
  const n = Number(value) || 0;
  return new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
}

function opsFormatInt(value) {
  const n = Number(value) || 0;
  return new Intl.NumberFormat().format(Math.round(n));
}

async function opsOpenDrill(url, title) {
  const overlay = document.getElementById('opsDrillOverlay');
  const body = document.getElementById('opsDrillBody');
  const titleEl = document.getElementById('opsDrillTitle');
  if (!overlay || !body) return;

  if (titleEl && title) titleEl.textContent = title;

  overlay.classList.add('active');
  body.innerHTML = '<div class="text-muted">Loading details...</div>';

  try {
    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    if (!res.ok) {
      body.innerHTML = '<div class="text-danger">Failed to load details.</div>';
      return;
    }
    const html = await res.text();
    body.innerHTML = html;
  } catch (e) {
    body.innerHTML = '<div class="text-danger">Unable to load details at the moment.</div>';
  }
}

function opsCloseDrill() {
  const overlay = document.getElementById('opsDrillOverlay');
  if (overlay) overlay.classList.remove('active');
}

async function opsLoadPerformanceMetrics(url, period) {
  if (!url || !period) return;
  try {
    const params = new URLSearchParams({ period: period });
    const res = await fetch(url + '?' + params.toString(), {
      headers: {
        'Accept': 'application/json'
      }
    });
    if (!res.ok) return;
    const data = await res.json();
    if (!data || !data.success) return;

    const cashEl = document.getElementById('opsCashFloatValue');
    const sellEl = document.getElementById('opsSellThroughValue');
    const covEl = document.getElementById('opsCoverageValue');
    const trendEl = document.getElementById('opsSellThroughTrend');
    const activeTodayEl = document.getElementById('opsActiveDrawersTodayValue');
    const drawerCountEl = document.getElementById('opsDrawerCountValue');
    const txCountEl = document.getElementById('opsTxCountValue');
    const txVolumeEl = document.getElementById('opsTxVolumeValue');
    const txAvgEl = document.getElementById('opsTxAvgValue');
    const utilIndexEl = document.getElementById('opsUtilIndexValue');
    const rankingBody = document.getElementById('opsUserRankingBody');
    const pipeBar = document.getElementById('opsPipelineBar');
    const pipeSold = document.getElementById('opsPipelineSold');
    const pipeUn = document.getElementById('opsPipelineUnassigned');

    if (cashEl) cashEl.textContent = opsFormatMoney(data.cashdrawer_float || 0);

    const sellThrough = Number(data.sell_through || 0);
    if (sellEl) sellEl.textContent = sellThrough.toFixed(1) + '%';
    if (covEl) covEl.textContent = '';

    if (trendEl) {
      trendEl.classList.remove('positive', 'negative');
      const cls = sellThrough >= 50 ? 'positive' : 'negative';
      trendEl.classList.add(cls);
      trendEl.textContent = sellThrough >= 50 ? 'Inventory velocity is healthy' : 'Consider campaigns to move stock';
    }

    const totalSims = Number(data.total_sims || 0);
    const totalSold = Number(data.total_sold || 0);
    const unassigned = Math.max(0, totalSims - totalSold);
    const totalPipe = Math.max(1, totalSims);
    const soldPct = Math.round((totalSold / totalPipe) * 1000) / 10;
    const unPct = Math.max(0, 100 - soldPct);

    if (pipeBar) pipeBar.style.width = soldPct + '%';
    if (pipeSold) pipeSold.textContent = 'Sold: ' + opsFormatInt(totalSold) + ' (' + soldPct.toFixed(1) + '%)';
    if (pipeUn) pipeUn.textContent = 'Unassigned: ' + opsFormatInt(unassigned) + ' (' + unPct.toFixed(1) + '%)';

    const drawerCount = Number(data.drawer_count || 0);
    if (drawerCountEl) drawerCountEl.textContent = opsFormatInt(drawerCount);

    const activeToday = Number(data.active_drawers_today || 0);
    if (activeTodayEl) activeTodayEl.textContent = opsFormatInt(activeToday);

    const txCount = Number(data.tx_count || 0);
    const txVolume = Number(data.tx_volume || 0);
    if (txCountEl) txCountEl.textContent = opsFormatInt(txCount);
    if (txVolumeEl) txVolumeEl.textContent = opsFormatMoney(txVolume);

    const txAvg = txCount > 0 ? txVolume / txCount : 0;
    if (txAvgEl) txAvgEl.textContent = opsFormatMoney(txAvg);

    const utilIndex = Number(data.util_index || 0);
    if (utilIndexEl) utilIndexEl.textContent = utilIndex.toFixed(1) + '%';

    // Health badges
    const sellBadge = document.getElementById('opsSellBadge');
    if (sellBadge) {
      sellBadge.className = 'badge rounded-pill';
      if (sellThrough >= 80) {
        sellBadge.classList.add('bg-success');
        sellBadge.textContent = 'High rotation';
      } else if (sellThrough >= 50) {
        sellBadge.classList.add('bg-info');
        sellBadge.textContent = 'Balanced';
      } else if (sellThrough > 0) {
        sellBadge.classList.add('bg-warning');
        sellBadge.textContent = 'Slow moving';
      } else {
        sellBadge.classList.add('bg-secondary');
        sellBadge.textContent = 'No movement yet';
      }
    }

    const utilBadge = document.getElementById('opsUtilBadge');
    if (utilBadge) {
      utilBadge.className = 'badge rounded-pill';
      if (utilIndex >= 120) {
        utilBadge.classList.add('bg-danger');
        utilBadge.textContent = 'Overheated';
      } else if (utilIndex >= 80) {
        utilBadge.classList.add('bg-success');
        utilBadge.textContent = 'Healthy utilisation';
      } else if (utilIndex > 0) {
        utilBadge.classList.add('bg-warning');
        utilBadge.textContent = 'Under-utilised';
      } else {
        utilBadge.classList.add('bg-secondary');
        utilBadge.textContent = 'No utilisation yet';
      }
    }

    // User ranking table (with relative-volume bar + drill-through)
    if (rankingBody) {
      const ranking = Array.isArray(data.user_ranking) ? data.user_ranking : [];
      rankingBody.innerHTML = '';
      if (!ranking.length) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 4;
        td.className = 'text-muted text-center';
        td.textContent = 'No activity in this period.';
        tr.appendChild(td);
        rankingBody.appendChild(tr);
      } else {
        const drillBase = rankingBody.getAttribute('data-drill-url') || '';
        let maxVol = 0;
        ranking.forEach(r => {
          const v = Number(r.tx_volume || 0);
          if (v > maxVol) maxVol = v;
        });
        const safeMax = maxVol > 0 ? maxVol : 1;

        ranking.forEach((row, idx) => {
          const tr = document.createElement('tr');
          const rankTd = document.createElement('td');
          rankTd.textContent = String(idx + 1);

          const nameTd = document.createElement('td');
          nameTd.textContent = row.name || 'User ' + (row.id || '');

          const cntTd = document.createElement('td');
          cntTd.className = 'text-end';
          cntTd.textContent = opsFormatInt(row.tx_count || 0);

          const volTd = document.createElement('td');
          volTd.className = 'text-end position-relative';

          const volWrap = document.createElement('div');
          volWrap.style.position = 'relative';
          volWrap.style.zIndex = '1';

          const volText = document.createElement('span');
          volText.textContent = opsFormatMoney(row.tx_volume || 0);
          volWrap.appendChild(volText);

          const volBg = document.createElement('div');
          volBg.style.position = 'absolute';
          volBg.style.inset = '0 0 0 0';
          volBg.style.opacity = '0.12';
          volBg.style.borderRadius = '999px';
          const widthPct = Math.max(8, Math.min(100, (Number(row.tx_volume || 0) / safeMax) * 100));
          volBg.style.width = widthPct + '%';
          volBg.style.left = '0';
          volBg.style.backgroundImage = 'linear-gradient(90deg,#22c55e,#eab308,#f97316)';

          volTd.appendChild(volBg);
          volTd.appendChild(volWrap);

          tr.appendChild(rankTd);
          tr.appendChild(nameTd);
          tr.appendChild(cntTd);
          tr.appendChild(volTd);

          // Drill-through: click row to open performance view in slide-over via AJAX
          if (drillBase && row.id) {
            tr.style.cursor = 'pointer';
            tr.addEventListener('click', function (e) {
              // Avoid conflicts if we later add links/buttons inside the row
              if (e.target && e.target.tagName === 'A') return;
              const activeBtn = document.querySelector('.ops-period-btn.active');
              const period = activeBtn ? (activeBtn.getAttribute('data-period') || 'today') : 'today';
              const url = drillBase + '?user_id=' + encodeURIComponent(row.id) + '&period=' + encodeURIComponent(period);
              const label = (row.name || ('User ' + (row.id || ''))).toString();
              opsOpenDrill(url, 'User ' + label + ' performance');
            });
          }

          rankingBody.appendChild(tr);
        });
      }
    }

    const svcSeries = data.series || {};
    opsRenderServiceProgress(svcSeries);
  } catch (e) {
  }
}

function opsRenderServiceProgress(series) {
  const container = document.getElementById('opsServiceProgress');
  const summaryEl = document.getElementById('opsPerfSummary');
  if (!container) return;

  const svcConfig = [
    { key: 'cfas', label: 'CFAs', color: 'linear-gradient(90deg,#60a5fa,#38bdf8)' },
    { key: 'momoremits', label: 'Momo', color: 'linear-gradient(90deg,#22c55e,#4ade80)' },
    { key: 'airtimebundles', label: 'Airtime', color: 'linear-gradient(90deg,#facc15,#fb923c)' },
    { key: 'ezwichs', label: 'Ezwich', color: 'linear-gradient(90deg,#fb7185,#f97316)' },
    { key: 'agencybankings', label: 'Agency banking', color: 'linear-gradient(90deg,#a855f7,#6366f1)' },
    { key: 'payment_services', label: 'Payment services', color: 'linear-gradient(90deg,#0ea5e9,#22c55e)' },
    { key: 'sims', label: 'SIMs', color: 'linear-gradient(90deg,#f97316,#facc15)' }
  ];

  const rows = [];
  let maxVal = 0;
  svcConfig.forEach(cfg => {
    const arr = Array.isArray(series[cfg.key]) ? series[cfg.key] : [];
    const total = arr.reduce((sum, p) => sum + Number(p && p.total ? p.total : 0), 0);
    if (total > 0) {
      rows.push({ ...cfg, total });
      if (total > maxVal) maxVal = total;
    }
  });

  container.innerHTML = '';

  if (!rows.length || !maxVal) {
    const empty = document.createElement('div');
    empty.className = 'text-muted';
    empty.textContent = 'No channel activity recorded for this period yet.';
    container.appendChild(empty);
    if (summaryEl) {
      summaryEl.textContent = '';
    }
    return;
  }

  rows.sort((a, b) => b.total - a.total);

  const summaryParts = [];
  rows.forEach(row => {
    const ratio = Math.max(4, Math.min(100, (row.total / maxVal) * 100));
    const wrap = document.createElement('div');
    wrap.className = 'mb-1';

    const head = document.createElement('div');
    head.className = 'd-flex justify-content-between align-items-center';

    const labelEl = document.createElement('span');
    labelEl.textContent = row.label;
    const valueEl = document.createElement('span');
    valueEl.textContent = opsFormatMoney(row.total);

    head.appendChild(labelEl);
    head.appendChild(valueEl);

    const track = document.createElement('div');
    track.className = 'ops-bar-track';

    const fill = document.createElement('div');
    fill.className = 'ops-bar-fill';
    fill.style.width = ratio + '%';
    fill.style.backgroundImage = row.color;

    track.appendChild(fill);

    wrap.appendChild(head);
    wrap.appendChild(track);
    container.appendChild(wrap);

    summaryParts.push(row.label + ' ' + opsFormatMoney(row.total));
  });

  if (summaryEl) {
    summaryEl.textContent = 'Channel mix totals → ' + summaryParts.join(' · ');
  }
}

function initOpsCashdrawersRegion() {
  const region = document.getElementById('opsCashdrawersRegion');
  if (!region) return;
  const ajaxUrl = region.getAttribute('data-ajax-url');
  if (!ajaxUrl) return;

  const dateFrom = region.querySelector('[data-role="ops-date-from"]');
  const dateTo = region.querySelector('[data-role="ops-date-to"]');
  const agent = region.querySelector('[data-role="ops-agent"]');

  async function load(page) {
    const params = new URLSearchParams();
    if (dateFrom && dateFrom.value) params.append('date_from', dateFrom.value);
    if (dateTo && dateTo.value) params.append('date_to', dateTo.value);
    if (agent && agent.value) params.append('agent', agent.value);
    if (page && page > 1) params.append('page', String(page));

    region.style.opacity = '0.6';
    try {
      const res = await fetch(ajaxUrl + '?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const html = await res.text();
      const tmp = document.createElement('div');
      tmp.innerHTML = html;
      const fresh = tmp.querySelector('#opsCashdrawersRegion');
      if (fresh) {
        region.innerHTML = fresh.innerHTML;
      } else {
        region.innerHTML = html;
      }
    } catch (e) {
    } finally {
      region.style.opacity = '';
      initOpsCashdrawersRegion();
    }
  }

  if (dateFrom) {
    dateFrom.addEventListener('change', function () { load(1); });
  }
  if (dateTo) {
    dateTo.addEventListener('change', function () { load(1); });
  }

  if (agent) {
    let typingTimer;
    const delay = 400;
    agent.addEventListener('keyup', function (e) {
      if (e.key === 'Enter') {
        load(1);
        return;
      }
      clearTimeout(typingTimer);
      typingTimer = setTimeout(function () { load(1); }, delay);
    });
  }

  region.querySelectorAll('a[href*="page="]').forEach(function (link) {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      try {
        const u = new URL(link.href, window.location.origin);
        const p = parseInt(u.searchParams.get('page') || '1', 10);
        load(p || 1);
      } catch (_) {
        load(1);
      }
    });
  });
}
</script>

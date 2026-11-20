<?php if(user_can('view_sim')):?>
	<?php
		//session data
		$session = get_session();
		//dd($session);
		if(!empty($session->id)){
			$username = $session->first_name . ' '. $session->last_name;
			$user_id = $session->id;
		}

		$notificationPillColorConfig = function_exists('admin_settings_notification_pill_color_config')
			? admin_settings_notification_pill_color_config()
			: ['serious' => '#dc3545', 'mild' => '#ffc107', 'regular' => '#0d6efd'];
		$simPillColor = $notificationPillColorConfig['mild'] ?? $notificationPillColorConfig['regular'] ?? '#f59e0b';
		$simHighlightColor = $simPillColor;
		if (preg_match('/^#?([a-f0-9]{3}|[a-f0-9]{6})$/i', (string) $simPillColor, $match)) {
			$hex = ltrim($match[1], '#');
			if (strlen($hex) === 3) {
				$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
			}
			$simHighlightColor = sprintf(
				'rgba(%d, %d, %d, 0.24)',
				hexdec(substr($hex, 0, 2)),
				hexdec(substr($hex, 2, 2)),
				hexdec(substr($hex, 4, 2))
			);
		}
	?>
<div id="simListCsrf" class="d-none"><?=csrf()?></div>
<style type="text/css">
	tr{
		white-space: nowrap;
	}
	.sim-analytics{
		color: var(--sim-text, #e2e8f0);
		--sim-text:#e2e8f0;
		--sim-muted:#94a3b8;
		--sim-card-border:rgba(148,163,184,0.25);
		--sim-card-bg:linear-gradient(135deg, rgba(15,23,42,0.95), rgba(15,23,42,0.75));
		--sim-card-shadow:0 25px 55px rgba(2,6,23,0.55);
		--sim-glass-border:rgba(148,163,184,0.2);
		--sim-table-border:rgba(148,163,184,0.15);
		--sim-summary-bg:linear-gradient(135deg, rgba(59,130,246,0.18), rgba(217,70,239,0.18));
	}
	html[data-bs-theme='light'] .sim-analytics,
	body[data-bs-theme='light'] .sim-analytics {
		--sim-text:#0f172a;
		--sim-muted:#475569;
		--sim-card-border:rgba(15,23,42,0.08);
		--sim-card-bg:linear-gradient(135deg, rgba(255,255,255,0.98), rgba(241,245,249,0.92));
		--sim-card-shadow:0 25px 55px rgba(15,23,42,0.08);
		--sim-glass-border:rgba(15,23,42,0.08);
		--sim-table-border:rgba(15,23,42,0.08);
		--sim-summary-bg:linear-gradient(135deg, rgba(59,130,246,0.12), rgba(236,72,153,0.12));
	}
	.sim-analytics .analytics-card {
		border-radius: 1rem;
		border: 1px solid var(--sim-card-border);
		background: var(--sim-card-bg);
		padding: 1.5rem;
		height: 100%;
		box-shadow: var(--sim-card-shadow);
		backdrop-filter: blur(18px);
	}
	.sim-analytics .analytics-card h2 {
		font-weight: 700;
		font-size: 1rem;
		letter-spacing: .08em;
		text-transform: uppercase;
		color: #fb923c;
	}
	.sim-analytics .analytics-card .value {
		font-size: clamp(1.8rem, 2vw, 2.6rem);
		font-weight: 700;
		color: var(--sim-text);
	}
	.sim-analytics .analytics-card .meta {
		color: var(--sim-muted);
		font-size: .85rem;
	}

	.sim-analytics .sims-summary {
		margin-bottom: 1.75rem;
	}
	.sim-analytics .sims-summary table {
		width: 100%;
		background: transparent;
	}
	.sim-analytics .sims-summary th {
		border: none;
		font-weight: 600;
		color: var(--sim-text);
		padding: 1rem 1.25rem;
		border-radius: 1rem;
		backdrop-filter: blur(14px);
		background: var(--sim-summary-bg);
		box-shadow: 0 20px 45px rgba(2,6,23,0.2);
	}
	.sim-analytics .sims-summary th span.text-muted {
		color: var(--sim-muted) !important;
	}

	.sim-analytics .sims-summary th + th {
		margin-left: .75rem;
	}
	.sim-analytics .dashboard-grid {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
		gap: 1.25rem;
	}
	.sim-analytics .section-title {
		font-weight: 700;
		font-size: 1.05rem;
		letter-spacing: .08em;
	}
	.sim-analytics .leaderboard-card {
		border-radius: 1rem;
		background: rgba(10,15,30,0.9);
		border: 1px solid var(--sim-glass-border);
		box-shadow: 0 20px 45px rgba(2,6,23,0.25);
		overflow: hidden;
		height: 100%;
		backdrop-filter: blur(16px);
	}
html[data-bs-theme='light'] .sim-analytics .leaderboard-card,
body[data-bs-theme='light'] .sim-analytics .leaderboard-card {
	background: rgba(255,255,255,0.95);
	box-shadow: 0 20px 45px rgba(15,23,42,0.08);
}

	.sim-analytics .leaderboard-card header {
		padding: 1rem 1.25rem;
		border-bottom: 1px solid rgba(148,163,184,0.15);
		display: flex;
		justify-content: space-between;
		align-items: center;
		background: linear-gradient(90deg, rgba(249,115,22,0.18), rgba(59,130,246,0.18));
	}
	.sim-analytics .leaderboard-card ul {
		list-style: none;
		margin: 0;
		padding: 1rem 1.25rem;
	}
	.sim-analytics .leaderboard-card li + li {
		margin-top: .85rem;
	}
	.sim-analytics .trend-pill {
		border-radius: 999px;
		padding: .25rem .75rem;
		font-size: .75rem;
		font-weight: 600;
		color:var(--sim-text);
	}
	.sim-analytics .pill-success {background: rgba(34,197,94,.2); color:#4ade80;}
	.sim-analytics .pill-warning {background: rgba(249,115,22,.18); color:#fb923c;}
	.sim-analytics .pill-info {background: rgba(59,130,246,.18); color:#93c5fd;}
	.sim-analytics .table-matrix th {white-space: nowrap;}
	.sim-analytics .leaderboard-card .table {
		background: transparent;
		color: var(--sim-text);
	}
	.sim-analytics .leaderboard-card .table thead th {
		color: var(--sim-muted);
		border-color: var(--sim-card-border);
	}
	.sim-analytics .leaderboard-card .table tbody td {
		border-color: var(--sim-table-border);
	}
	.sim-analytics .leaderboard-card .table tbody tr:hover {
		background: rgba(59, 130, 246, 0.08);
	}

	.highlight-assigned-sim {
		background-color: <?= htmlspecialchars($simHighlightColor, ENT_QUOTES, 'UTF-8') ?> !important;
		border-left: 4px solid <?= htmlspecialchars($simPillColor ?: '#f59e0b', ENT_QUOTES, 'UTF-8') ?>;
		font-weight: 700;
	}

	.table-striped tbody tr.highlight-assigned-sim,
	.highlight-assigned-sim {
		background-color: <?= htmlspecialchars($simHighlightColor, ENT_QUOTES, 'UTF-8') ?> !important;
	}

	.table-striped tbody tr.highlight-assigned-sim td,
	.highlight-assigned-sim td {
		background-color: <?= htmlspecialchars($simHighlightColor, ENT_QUOTES, 'UTF-8') ?> !important;
	}

	.highlight-assigned-sim:hover {
		background-color: rgba(250, 204, 21, 0.4) !important;
	}

	.sim-analytics .progress {
		background-color: rgba(226,232,240,0.12);
	}
	.sim-analytics .progress-bar {
		border-radius: 999px;
	}

	/* Updated SIM Transactions Table Font Sizing */
	.sim-transactions-card .table {
		margin-bottom: 0;
		color: var(--sim-text);
		vertical-align: middle;
	}
	.sim-transactions-card .table thead th {
		background: rgba(15, 23, 42, 0.4);
		color: var(--sim-muted);
		font-size: 0.875rem; /* Increased from 0.75rem */
		text-transform: uppercase;
		letter-spacing: 0.05em;
		font-weight: 700;
		padding: 1rem;
		border-bottom: 1px solid var(--sim-card-border);
	}
	html[data-bs-theme='light'] .sim-transactions-card .table thead th,
	body[data-bs-theme='light'] .sim-transactions-card .table thead th {
		background: rgba(241, 245, 249, 0.8);
	}
	.sim-transactions-card .table tbody td {
		padding: 0.95rem 1rem;
		border-bottom: 1px solid var(--sim-table-border);
		font-size: 0.975rem; /* Increased base font size from 0.875rem */
	}
	.sim-transactions-card .table tbody tr {
		transition: background-color 0.2s ease-in-out;
	}
	.sim-transactions-card .table tbody tr:hover {
		background-color: rgba(59, 130, 246, 0.05) !important;
	}
    .sim-transactions-card {
        position: relative;
    }
    .sim-table-preloader {
        position: absolute;
        inset: 0;
        z-index: 20;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(15, 23, 42, .46);
        backdrop-filter: blur(2px);
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: opacity .16s ease, visibility .16s ease;
    }
    .sim-table-preloader.is-visible {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
    }
    .sim-table-preloader-content {
        display: inline-flex;
        align-items: center;
        gap: .65rem;
        padding: .7rem 1rem;
        border: 1px solid rgba(148, 163, 184, .3);
        border-radius: .65rem;
        background: rgba(15, 23, 42, .9);
        color: #e2e8f0;
        font-size: .85rem;
        font-weight: 600;
        box-shadow: 0 10px 28px rgba(2, 6, 23, .25);
    }
    .sim-table-preloader .spinner-border {
        width: 1.15rem;
        height: 1.15rem;
        border-width: .16em;
    }
    html[data-bs-theme='light'] .sim-table-preloader,
    body[data-bs-theme='light'] .sim-table-preloader {
        background: rgba(248, 250, 252, .58);
    }
    html[data-bs-theme='light'] .sim-table-preloader-content,
    body[data-bs-theme='light'] .sim-table-preloader-content {
        background: rgba(255, 255, 255, .94);
        color: #334155;
    }
	.sim-avatar {
		width: 36px;
		height: 36px;
		object-fit: cover;
		border-radius: 50%;
		border: 1px solid var(--sim-glass-border);
	}
	.sim-avatar-sm {
		width: 28px;
		height: 28px;
		object-fit: cover;
		border-radius: 50%;
		border: 1px solid var(--sim-glass-border);
	}
	.table-text-muted {
		font-size: 0.875rem;
		color: var(--sim-muted);
	}
    #simPagerWrapper {
        display: flex;
        justify-content: center;
        padding: 1rem 1.25rem !important;
        overflow-x: auto;
    }
    #simPagerWrapper .sim-pagination {
        width: max-content;
        max-width: 100%;
    }
    #simPagerWrapper .sim-pagination-list {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-wrap: nowrap;
        gap: .35rem;
        padding: .35rem;
        border: 1px solid var(--sim-card-border);
        border-radius: .75rem;
        background: rgba(15, 23, 42, .42);
        box-shadow: 0 8px 24px rgba(2, 6, 23, .16);
    }
    #simPagerWrapper .sim-page-control {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        min-width: 2.35rem;
        height: 2.35rem;
        box-sizing: border-box;
        padding: 0 .7rem;
        border: 0;
        border-radius: .5rem;
        background: transparent;
        color: var(--sim-text);
        font-size: .8rem;
        font-weight: 600;
        line-height: 1;
        white-space: nowrap;
        text-decoration: none;
        vertical-align: middle;
    }
    #simPagerWrapper a.sim-page-control:hover {
        background: rgba(59, 130, 246, .18);
        color: #93c5fd;
    }
    #simPagerWrapper .sim-page-control.is-active {
        background: #2563eb;
        color: #fff;
        box-shadow: 0 4px 12px rgba(37, 99, 235, .3);
    }
    #simPagerWrapper .sim-page-control.is-disabled {
        background: transparent;
        color: var(--sim-muted);
        opacity: .55;
        cursor: default;
    }
    #simPagerWrapper .sim-pagination-ellipsis {
        min-width: auto;
        padding-inline: .45rem;
    }
    html[data-bs-theme='light'] #simPagerWrapper .sim-pagination-list,
    body[data-bs-theme='light'] #simPagerWrapper .sim-pagination-list {
        background: rgba(241, 245, 249, .9);
        box-shadow: 0 8px 24px rgba(15, 23, 42, .08);
    }
</style>

<div class="sim-analytics">
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <div class="text-uppercase fw-semibold text-muted small">SIMS HQ</div>
        <h1 class="fw-bold text-dark">Sales Intelligence</h1>
        <p class="text-muted mb-0">Realtime view of SIM performance, customers, and field agents.</p>
    </div>
	<div class="d-flex gap-2">
		<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/exportList_pdf<?= !empty($_GET['find']) ? ('?find=' . urlencode($_GET['find'])) : '' ?>" class="btn btn-outline-danger btn-sm" target="_blank">
			<i class="fa-solid fa-file-pdf"></i> Export PDF
		</a>
		<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/exportList_csv<?= !empty($_GET['find']) ? ('?find=' . urlencode($_GET['find'])) : '' ?>" class="btn btn-outline-success btn-sm">
			<i class="fa-solid fa-file-csv"></i> Export CSV
		</a>
		<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/sell">
			<button class="btn btn-warning btn-sm" title="Sell Sim">
			<i class="fa-solid fa-credit-card"></i>  SELL SIM
			</button>
		</a>
	</div>
</div>
<div class="row sims-summary">
	<table class="table table-borderless">
	    <tbody>
	        <tr>
	        <?php if (!empty($networks)): ?>
	            <?php foreach ($networks as $network => $data): ?>
	                <?php $soldCount = isset($data['sold']) ? $data['sold'] : 0; ?>
	                <?php $totalCount = isset($data['total']) ? $data['total'] : 0; ?>
	                <th class="fs-4">
	                	<span class="text-warning"><?= htmlspecialchars(strtoupper($network)); ?>: </span>
	                	<span class="text-muted"><?= htmlspecialchars($soldCount . '/' . $totalCount); ?></span>
	                </th>
	            <?php endforeach; ?>
        	<?php else: ?>
                <td colspan="2" class="text-center">No data found.</td>
        	<?php endif; ?>
	        </tr>
	    </tbody>
	</table>
</div>
<div class="dashboard-grid mb-4">
    <div class="analytics-card">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h2>Total SIMs Sold</h2>
                <div class="value"><?=number_format($soldStats->sold_count_total ?? 0)?></div>
                <div class="meta"><?=number_format($soldStats->sold_count_week ?? 0)?> sold in the last 7 days</div>
            </div>
            <div class="trend-pill pill-info">Today: <?=number_format($soldStats->sold_count_today ?? 0)?></div>
        </div>
        <div class="mt-4 d-flex justify-content-between">
            <div>
                <div class="text-muted small">Retail</div>
                <div class="fw-bold text-success"><?=number_format($soldStats->retail_count_total ?? 0)?></div>
            </div>
            <div>
                <div class="text-muted small">Wholesale</div>
                <div class="fw-bold text-primary"><?=number_format($soldStats->wholesale_count_total ?? 0)?></div>
            </div>
        </div>
    </div>

    <div class="analytics-card">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h2>Revenue Generated</h2>
                <div class="value">GHS <?=number_format($soldStats->sold_amount_total ?? 0, 2)?></div>
                <div class="meta">GHS <?=number_format($soldStats->sold_amount_week ?? 0,2)?> in last 7 days</div>
            </div>
            <div class="trend-pill pill-success">Today: GHS <?=number_format($soldStats->sold_amount_today ?? 0,2)?></div>
        </div>
        <div class="mt-4">
            <div class="text-muted small">Commission cumulative</div>
            <div class="fw-bold">GHS <?=number_format($soldStats->sold_commission_total ?? 0,2)?> <span class="text-success small ms-2">+<?=number_format($soldStats->sold_commission_week ?? 0,2)?> this week</span></div>
        </div>
    </div>

    <div class="analytics-card">
        <h2>Network Leaderboard</h2>
        <?php if (!empty($networkPerformance)): ?>
            <?php foreach (array_slice($networkPerformance, 0, 3) as $rank => $net): ?>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        <div class="fw-semibold text-dark text-uppercase">
                            <?=$rank+1?>. <?=esc($net->network ?? 'Unknown')?>
                        </div>
                        <div class="meta">Sold: <?=number_format($net->total_sold ?? 0)?> · GHS <?=number_format($net->total_amount ?? 0,2)?></div>
                    </div>
                    <div class="trend-pill pill-warning">7d: <?=number_format($net->sold_last_7 ?? 0)?></div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted mt-3">No network performance data yet.</p>
        <?php endif; ?>
    </div>

    <div class="analytics-card">
        <h2>Inventory Snapshot</h2>
        <div class="mt-3">
            <div class="d-flex justify-content-between">
                <span class="text-muted">Unassigned</span>
                <span class="fw-semibold">GHS <?=number_format($totalUnassigned_amount ?? 0,2)?></span>
            </div>
            <div class="progress" style="height:6px;">
                <div class="progress-bar bg-secondary" style="width: <?=($total_amount ?? 0) > 0 ? min(100, ($totalUnassigned_amount ?? 0)/max(1,$total_amount)*100) : 0?>%"></div>
            </div>
        </div>
        <div class="mt-3">
            <div class="d-flex justify-content-between">
                <span class="text-muted">Assigned</span>
                <span class="fw-semibold">GHS <?=number_format($totalAssigned_amount ?? 0,2)?></span>
            </div>
            <div class="progress" style="height:6px;">
                <div class="progress-bar bg-info" style="width: <?=($total_amount ?? 0) > 0 ? min(100, ($totalAssigned_amount ?? 0)/max(1,$total_amount)*100) : 0?>%"></div>
            </div>
        </div>
        <div class="mt-3">
            <div class="d-flex justify-content-between">
                <span class="text-muted">Sold</span>
                <span class="fw-semibold">GHS <?=number_format(($totalSoldRetail_amount ?? 0)+($totalSoldWholesale_amount ?? 0),2)?></span>
            </div>
            <div class="progress" style="height:6px;">
                <div class="progress-bar bg-success" style="width: <?=($total_amount ?? 0) > 0 ? min(100, (($totalSoldRetail_amount ?? 0)+($totalSoldWholesale_amount ?? 0))/max(1,$total_amount)*100) : 0?>%"></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="leaderboard-card">
            <header>
                <div>
                    <div class="section-title">Recent Sales</div>
                    <small class="text-muted">Latest 10 transactions</small>
                </div>
                <span class="badge bg-dark">Live</span>
            </header>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr class="text-muted small text-uppercase">
                            <th>Network/Serial</th>
                            <th>Buyer/Amount</th>
                            <th>Agent/Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentSales)): ?>
                            <?php foreach ($recentSales as $sale): ?>
                                <tr>
                                    <td>
		                            	<?php
			                                $textClass = 'text-muted';
			                                switch (strtolower($sale->network ?? 'mtn')) {
			                                    case 'telecel':
			                                        $textClass = 'text-danger';
			                                        break;
			                                    case 'starlink':
			                                        $textClass = 'text-success';
			                                        break;
			                                    case 'mtn':
			                                        $textClass = 'text-warning';
			                                        break;
			                                    case 'at':
			                                        $textClass = 'text-primary';
			                                        break;
			                                }
			                            ?>
                                    	<div class="fw-semibold <?=$textClass?> text-uppercase"> <i class="fa fa-signal me-1 <?=$textClass?>"></i> <?=esc($sale->network ?? '—')?></div>
                                    	<small class="text-muted"> S/N: <?=esc($sale->serial_number ?? '—')?></small>
                                    </td>
                                    <td>
                                    	<div class="fw-bold"><i class="fa-solid fa-phone"></i> <?=esc($sale->buyer_phone ?? '—')?></div>
	                                    <div>AMT: GHS <?=number_format($sale->amount ?? 0,2)?></div>
	                                </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2 fw-bold">
                                            <i class="fa-solid fa-user"></i>
                                            <?=esc(trim(($sale->assigned_first_name ?? '').' '.($sale->assigned_last_name ?? ''))) ?: 'Unassigned'?>
                                        </div>
                                    	<small class="text-muted"><?=get_date($sale->date_updated ?? $sale->date_created ?? '')?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No sales yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="leaderboard-card h-100">
            <header>
                <div>
                    <div class="section-title">Top Customers</div>
                    <small class="text-muted">By SIMs purchased</small>
                </div>
                <i class="fa fa-users text-dark"></i>
            </header>
            <ul>
                <?php if (!empty($topCustomers)): ?>
                    <?php foreach ($topCustomers as $customer): ?>
                        <li class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="customer-phone fw-semibold">
                                    <span class="phone-badge">Buyer</span>
                                    <?=esc($customer->buyer_phone)?>
                                </div>
                                <div class="text-muted small">SIMs <?=number_format($customer->total_sims ?? 0)?> · GHS <?=number_format($customer->total_amount ?? 0,2)?></div>
                            </div>
                            <span class="trend-pill pill-success">Avg <?=number_format($customer->avg_ticket ?? 0,2)?></span>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="text-muted">No customer performance data yet.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="leaderboard-card h-100">
            <header>
                <div>
                    <div class="section-title">Top Agents</div>
                    <small class="text-muted">Conversion champions</small>
                </div>
                <i class="fa fa-award text-warning"></i>
            </header>
            <ul>
                <?php if (!empty($topAgents)): ?>
                    <?php foreach ($topAgents as $agent): ?>
                        <li class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <img src="<?=get_image($agent->image ?? '')?>" class="rounded-circle" style="width:32px;height:32px;object-fit:cover;" alt="agent">
                                <div>
                                    <div class="fw-semibold"><?=esc(trim(($agent->first_name ?? '').' '.($agent->last_name ?? '')))?></div>
                                    <div class="text-warning small">SIMs <?=number_format($agent->total_sims ?? 0)?></div>
                                </div>
                            </div>
                            <span class="trend-pill pill-warning">GHS <?=number_format($agent->total_amount ?? 0,0)?></span>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="text-muted">No agent rankings yet.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="leaderboard-card">
            <header>
                <div>
                    <div class="section-title">Network Performance Matrix</div>
                    <small class="text-muted">Sold vs total inventory per network</small>
                </div>
                <span class="badge bg-secondary">All Networks</span>
            </header>
            <div class="table-responsive">
                <table class="table table-matrix mb-0">
                    <thead class="text-muted small text-uppercase">
                        <tr>
                            <th>Network</th>
                            <th>Sold / Total</th>
                            <th>Retail</th>
                            <th>Wholesale</th>
                            <th>7d Trend</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($networks)): ?>
                            <?php foreach ($networks as $network => $data): ?>
                                <?php
                                    $np = (is_array($networkPerformance ?? null) ? $networkPerformance : []);
                                    $idx = array_search($network, array_column($np, 'network'));
                                    $performance = ($idx !== false && isset($np[$idx])) ? $np[$idx] : null;
                                ?>
                                <tr>
                                    <td class="fw-semibold text-uppercase" style="color:var(--sim-text);">
                                        <?=$network?>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?=number_format($data['sold'] ?? 0)?> / <?=number_format($data['total'] ?? 0)?></div>
                                        <div class="progress" style="height:6px;">
                                            <div class="progress-bar bg-success" style="width: <?=($data['total'] ?? 0) > 0 ? min(100, ($data['sold']/$data['total'])*100) : 0?>%"></div>
                                        </div>
                                    </td>
                                    <td><?=number_format($performance->retail_count ?? 0)?></td>
                                    <td><?=number_format($performance->wholesale_count ?? 0)?></td>
                                    <td><span class="trend-pill pill-info"><?=number_format($performance->sold_last_7 ?? 0)?> in 7d</span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">No network data to display.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if(user_can('view_sim')):?>
<div class="leaderboard-card sim-transactions-card mb-4">
    <div id="simTablePreloader" class="sim-table-preloader" role="status" aria-live="polite" aria-hidden="true">
        <div class="sim-table-preloader-content">
            <span class="spinner-border text-warning" aria-hidden="true"></span>
            <span>Loading transactions...</span>
        </div>
    </div>
    <header class="d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <i class="fa-solid fa-list-check text-warning fs-5"></i>
            <div>
                <h2 class="section-title text-uppercase m-0">SIM Transactions</h2>
                <small class="text-muted">Filter, manage, and inspect all SIM inventory records</small>
            </div>
        </div>
        <span class="badge bg-warning text-dark font-monospace fs-6">
            <?= count($rows ?? []) ?> Records
        </span>
    </header>
    <div class="p-4 border-bottom border-secondary border-opacity-10">
        <form id="simFiltersForm" class="w-100" data-bo-allow-multi-submit="1">
            <!-- Basic Filters Row -->
            <div class="row g-3 align-items-end mb-3">
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-semibold text-uppercase mb-1">Keyword</label>
                    <input type="text" class="form-control" name="find" id="simFilterFind" placeholder="Serial / buyer / status / assigned" value="<?= esc($_GET['find'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-semibold text-uppercase mb-1">Status</label>
                    <select class="form-select" name="status" id="simFilterStatus">
                        <option value="">All statuses</option>
                        <?php foreach ([
                            'Unassigned',
                            'Assigned',
                            'Expired',
                            'Sold - retail',
                            'Sold - wholesale',
                            'Declined',
                            'Rejected'
                        ] as $statusOption): ?>
                            <option value="<?= esc($statusOption) ?>" <?= esc($_GET['status'] ?? '') === $statusOption ? 'selected' : '' ?>><?= esc($statusOption) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-semibold text-uppercase mb-1" for="simFilterLimit">Items per page</label>
                    <?php $itemsPerPage = (int)($_GET['limit'] ?? 10); ?>
                    <select class="form-select" name="limit" id="simFilterLimit">
                        <?php foreach ([5, 10, 25, 50, 100] as $limitOption): ?>
                            <option value="<?=$limitOption?>" <?=$itemsPerPage === $limitOption ? 'selected' : ''?>><?=$limitOption?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-warning flex-grow-1">
                            <i class="fa-solid fa-filter"></i> Filter
                        </button>
                        <button type="button" id="simFilterReset" class="btn btn-outline-secondary">
                            <i class="fa-solid fa-redo"></i> Reset
                        </button>
                        <button type="button" id="simFilterToggle" class="btn btn-outline-info">
                            <i class="fa-solid fa-sliders-h"></i> More Filters
                        </button>
                    </div>
                </div>
            </div>

            <!-- Advanced Filters (Hidden by default) -->
            <div id="advancedFilters" class="row g-3" style="display: none;">
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-semibold text-uppercase mb-1">Buyer Phone</label>
                    <input type="text" class="form-control" name="buyer_phone" id="simFilterBuyerPhone" placeholder="Customer phone" value="<?= esc($_GET['buyer_phone'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-semibold text-uppercase mb-1">Network</label>
                    <select class="form-select" name="network" id="simFilterNetwork">
                        <option value="">Any network</option>
                        <?php foreach ($distinctNetworks as $networkOption): ?>
                            <option value="<?= esc($networkOption->network) ?>" <?= esc($_GET['network'] ?? '') === ($networkOption->network ?? '') ? 'selected' : '' ?>><?= esc($networkOption->network) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-semibold text-uppercase mb-1">Type</label>
                    <select class="form-select" name="type" id="simFilterType">
                        <option value="">Any type</option>
                        <?php foreach ($distinctTypes as $typeOption): ?>
                            <option value="<?= esc($typeOption->type) ?>" <?= esc($_GET['type'] ?? '') === ($typeOption->type ?? '') ? 'selected' : '' ?>><?= esc($typeOption->type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-semibold text-uppercase mb-1">Created by</label>
                    <select class="form-select" name="created_by" id="simFilterCreatedBy">
                        <option value="">Any creator</option>
                        <?php foreach ($users as $userOption): ?>
                            <option value="<?= esc($userOption->id) ?>" <?= (string)($userOption->id ?? '') === (string)($_GET['created_by'] ?? '') ? 'selected' : '' ?>><?= esc(trim(($userOption->first_name ?? '') . ' ' . ($userOption->last_name ?? ''))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-semibold text-uppercase mb-1">Start Date</label>
                    <input class="form-control" type="date" name="date_start" id="simFilterDateStart" value="<?= esc($_GET['date_start'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-semibold text-uppercase mb-1">End Date</label>
                    <input class="form-control" type="date" name="date_end" id="simFilterDateEnd" value="<?= esc($_GET['date_end'] ?? '') ?>">
                </div>
            </div>
        </form>
    </div>

    <!-- Enhanced Font Transactions Table -->
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th class="text-center" style="width: 50px;">#</th>
                    <th>Date Created</th>
                    <th>SIM & Network</th>
                    <th>Status</th>
                    <th>PUK & Serial</th>
                    <th class="text-end">Pricing</th>
                    <th>Agent & Creator</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <?php if(!empty($rows)): $id = 0;?>
                <?php
                    if(!empty($session->id)){
                        $username = $session->first_name . ' '. $session->last_name;
                        $user_id = $session->id;
                    }
                ?>
                <tbody id="simListBody">
                <?php foreach($rows as $row): $id ++;?>
                    <?php
                        $isAssignedToCurrentUser = !empty($row->assigned)
                            && !empty($user_id)
                            && ((int)$row->assigned === (int)$user_id)
                            && !in_array(strtolower((string)($row->transaction_status ?? '')), ['sold - retail','sold - wholesale'], true);
                    ?>
                    <tr class="<?= $isAssignedToCurrentUser ? 'highlight-assigned-sim' : '' ?>" <?= $isAssignedToCurrentUser ? 'style="background-color: ' . htmlspecialchars($simHighlightColor, ENT_QUOTES, 'UTF-8') . ' !important;"' : '' ?>>
                        <td class="text-center">
                            <span class="badge rounded-pill bg-warning text-dark fs-6 px-2"><?= $id ?></span>
                        </td>
                        <td>
                            <div class="fw-semibold text-body fs-6"><?= get_date($row->date_created ?? '') ?></div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                            	<?php
	                                $textClass = 'text-muted';
	                                switch (strtolower($row->network ?? 'mtn')) {
	                                    case 'telecel':
	                                        $textClass = 'text-danger';
	                                        break;
	                                    case 'starlink':
	                                        $textClass = 'text-success';
	                                        break;
	                                    case 'mtn':
	                                        $textClass = 'text-warning';
	                                        break;
	                                    case 'at':
	                                        $textClass = 'text-primary';
	                                        break;
	                                }
	                            ?>
                                <div>
                                	<i class="fa-solid fa-signal me-1 <?=$textClass?>" data-app-image-viewer title="Click to view image"></i>
                                    <a class="fw-bold text-decoration-none <?=$textClass?> fs-6" href="<?= ROOT ?>/<?= $admin_route ?>/<?= $plugin_route ?>/view/<?= $row->id ?>">
                                        <?= strtoupper($row->network) ?>
                                    </a>
                                    <div class="table-text-muted font-monospace"><?= $row->number_assigned ?? 'Unassigned' ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php
                                $badgeClass = 'bg-secondary';
                                switch (strtolower($row->transaction_status ?? 'unassigned')) {
                                    case 'expired':
                                    case 'declined':
                                    case 'rejected':
                                        $badgeClass = 'bg-danger';
                                        break;
                                    case 'sold - retail':
                                    case 'sold - wholesale':
                                        $badgeClass = 'bg-success';
                                        break;
                                    case 'unassigned':
                                        $badgeClass = 'bg-warning text-dark';
                                        break;
                                    case 'assigned':
                                        $badgeClass = 'bg-primary';
                                        break;
                                }
                            ?>
                            <span class="badge <?= $badgeClass ?> text-uppercase px-2 py-1"><?= esc($row->transaction_status) ?></span>
                            <?php if(in_array(strtolower((string)($row->transaction_status ?? '')), ['declined','rejected'], true) && !empty($row->rejection_reason)):?>
                                <div class="table-text-muted text-danger mt-1" style="white-space:normal;max-width:220px;">
                                    <i class="fa-solid fa-circle-exclamation me-1"></i><?= esc($row->rejection_reason) ?>
                                </div>
                            <?php endif;?>
                        </td>
                        <td>
                            <div class="font-monospace fs-6">
                                <span class="table-text-muted">PUK:</span> <?= esc($row->puk ?? 'N/A') ?>
                            </div>
                            <div class="font-monospace fs-6">
                                <span class="table-text-muted">S/N:</span> <?= esc($row->serial_number ?? 'N/A') ?>
                            </div>
                        </td>
                        <!-- Combined Pricing Column -->
                        <td class="text-end font-monospace">
                            <div class="table-text-muted" title="Cost Price">
                                Cost: GHS <?= number_format($row->cost, 2) ?>
                            </div>
                            <div class="text-primary fw-semibold fs-6" title="Wholesale Price">
                                WS: GHS <?= number_format($row->wholesale_price, 2) ?>
                            </div>
                            <div class="fw-bold text-success fs-6" title="Retail Price">
                                Ret: GHS <?= number_format($row->retail_price, 2) ?>
                            </div>
                        </td>
                        <!-- Combined User Roles Column -->
                        <td>
                            <div class="d-flex align-items-center gap-2 mb-1" title="In Charge / Assigned Agent">
                                <img src="<?= get_image($row->assigned_image) ?>" class="sim-avatar-sm" alt="Assigned">
                                <span class="fw-semibold fs-6"><?= esc(trim(($row->assigned_first_name ?? '') . ' ' . ($row->assigned_last_name ?? ''))) ?: 'Unassigned' ?></span>
                            </div>
                            <div class="d-flex align-items-center gap-2" title="Created By">
                                <img src="<?= get_image($row->user_image) ?>" class="sim-avatar-sm" alt="Creator">
                                <span class="table-text-muted"><?= esc(trim(($row->user_first_name ?? '') . ' ' . ($row->user_last_name ?? ''))) ?></span>
                            </div>
                        </td>
                        <td class="text-end">
                            <div class="btn-group" role="group">
                                <?php if(user_can('view_sim')):?>
                                <a href="<?= ROOT ?>/<?= $admin_route ?>/<?= $plugin_route ?>/view/<?= $row->id ?>" class="btn btn-outline-primary btn-sm px-2" title="View Details">
                                    <i class="fa-solid fa-eye fs-6"></i>
                                </a>
                                <?php endif?>

                                <?php if(user_can('edit_sim') && !in_array(strtolower((string)($row->transaction_status ?? '')), ['declined','rejected','sold - retail','sold - wholesale'], true)):?>
                                <a href="<?= ROOT ?>/<?= $admin_route ?>/<?= $plugin_route ?>/edit/<?= $row->id ?>" class="btn btn-outline-warning btn-sm px-2" title="Edit SIM">
                                    <i class="fa-solid fa-pen-to-square fs-6"></i>
                                </a>
                                <?php endif?>

                                <?php if(user_can('delete_sim') && !in_array(strtolower((string)($row->transaction_status ?? '')), ['sold - retail','sold - wholesale'], true)):?>
                                <button type="button" class="btn btn-outline-danger btn-sm px-2 sim-delete-button" data-sim-id="<?= $row->id ?>" title="Delete Record">
                                    <i class="fa-solid fa-trash fs-6"></i>
                                </button>
                                <?php endif?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach?>
                <?php else:?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted fs-5">
                            <i class="fa-solid fa-inbox fs-2 d-block mb-2"></i> No records found!
                        </td>
                    </tr>
                </tbody>
            <?php endif?>
        </table>
    </div>
    
    <div class="p-3 border-top border-secondary border-opacity-10" id="simPagerWrapper">
        <?= sims_render_pager($pager) ?>
    </div>
</div>

<?=get_app_image_viewer_inline_source()?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const csrfContainer = document.getElementById('simListCsrf');
    const csrfInput = csrfContainer ? csrfContainer.querySelector('input[name="csrf"]') : null;
    const csrfToken = csrfInput ? csrfInput.value : null;
    const filterForm = document.getElementById('simFiltersForm');
    const pagerWrapper = document.getElementById('simPagerWrapper');
    const tablePreloader = document.getElementById('simTablePreloader');
    const advancedFilters = document.getElementById('advancedFilters');
    const filterToggleBtn = document.getElementById('simFilterToggle');

    function setTableLoading(isLoading) {
        if (!tablePreloader) return;
        tablePreloader.classList.toggle('is-visible', isLoading);
        tablePreloader.setAttribute('aria-hidden', isLoading ? 'false' : 'true');
    }

    // Handle advanced filters toggle
    if (filterToggleBtn && advancedFilters) {
        filterToggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const isHidden = advancedFilters.style.display === 'none';
            advancedFilters.style.display = isHidden ? 'flex' : 'none';
            filterToggleBtn.classList.toggle('active', isHidden);
        });

        // Show advanced filters if any are already set
        const hasAdvancedFilters = 
            (document.getElementById('simFilterBuyerPhone')?.value) ||
            (document.getElementById('simFilterNetwork')?.value) ||
            (document.getElementById('simFilterType')?.value) ||
            (document.getElementById('simFilterCreatedBy')?.value) ||
            (document.getElementById('simFilterDateStart')?.value) ||
            (document.getElementById('simFilterDateEnd')?.value);
        
        if (hasAdvancedFilters) {
            advancedFilters.style.display = 'flex';
            filterToggleBtn.classList.add('active');
        }
    }

    function attachDeleteButtons() {
        document.querySelectorAll('.sim-delete-button:not([data-delete-attached])').forEach(button => {
            button.dataset.deleteAttached = '1';
            button.addEventListener('click', async () => {
                const simId = button.dataset.simId;
                if (!simId) return;

                if (window.Swal && typeof window.Swal.fire === 'function') {
                    const result = await window.Swal.fire({
                        title: 'Delete SIM record?',
                        text: 'This action cannot be undone.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, delete it',
                        cancelButtonText: 'Cancel',
                        reverseButtons: true,
                    });

                    if (!result.isConfirmed) return;
                } else {
                    if (!window.confirm('Delete this SIM record?')) {
                        return;
                    }
                }

                const deleteUrl = '<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/delete/' + encodeURIComponent(simId);
                const formData = new FormData();
                formData.append('__ajax', '1');
                if (csrfToken) {
                    formData.append('csrf', csrfToken);
                }

                try {
                    const response = await fetch(deleteUrl, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const data = await response.json();
                    if (data && data.success) {
                        if (window.Swal && typeof window.Swal.fire === 'function') {
                            await window.Swal.fire({
                                title: 'Deleted',
                                text: data.message || 'SIM deleted successfully.',
                                icon: 'success',
                                confirmButtonText: 'OK'
                            });
                        }
                        window.location.reload();
                    } else {
                        const message = (data && data.message) ? data.message : 'Unable to delete SIM.';
                        if (window.Swal && typeof window.Swal.fire === 'function') {
                            window.Swal.fire({
                                title: 'Error',
                                text: message,
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        } else {
                            window.alert(message);
                        }
                    }
                } catch (error) {
                    const message = error.message || 'Unable to delete SIM.';
                    if (window.Swal && typeof window.Swal.fire === 'function') {
                        window.Swal.fire({
                            title: 'Error',
                            text: message,
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        window.alert(message);
                    }
                }
            });
        });
    }

    function buildFilterUrl(page) {
        const url = new URL(window.location.href);
        url.searchParams.delete('ajax');
        url.searchParams.delete('__ajax');
        if (filterForm) {
            for (const [key, value] of new FormData(filterForm).entries()) {
                if (value === '') {
                    url.searchParams.delete(key);
                } else {
                    url.searchParams.set(key, value);
                }
            }
        }
        if (page > 0) {
            url.searchParams.set('page', String(page));
        } else {
            url.searchParams.delete('page');
        }
        url.searchParams.set('ajax', '1');
        return url;
    }

    function attachPagerLinks() {
        if (!pagerWrapper || pagerWrapper.dataset.ajaxPagerAttached) return;
        pagerWrapper.dataset.ajaxPagerAttached = '1';
        pagerWrapper.addEventListener('click', async (event) => {
            const target = event.target instanceof Element ? event.target : null;
            const link = target ? target.closest('a.sim-page-control') : null;
            if (!link || !pagerWrapper.contains(link) || link.classList.contains('is-disabled')) return;
            event.preventDefault();
            const linkUrl = new URL(link.href, window.location.origin);
            const page = parseInt(linkUrl.searchParams.get('page') || '1', 10);
            await fetchFilteredRows(buildFilterUrl(Number.isNaN(page) ? 1 : page).toString());
        });
    }

    async function fetchFilteredRows(url) {
		setTableLoading(true);
        try {
            const response = await fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            if (!response.ok) {
                throw new Error('Filter request failed');
            }
            const contentType = response.headers.get('content-type') || '';
            const responseText = await response.text();
            const trimmedText = responseText.trim();
            const fallbackUrl = new URL(url, window.location.origin);
            fallbackUrl.searchParams.delete('ajax');
            fallbackUrl.searchParams.delete('__ajax');
            const looksLikeHtml = /<\s*html|<\s*form|login|access denied|<!DOCTYPE/i.test(trimmedText);
            if (!contentType.includes('application/json') && looksLikeHtml) {
                window.location.href = fallbackUrl.toString();
                return;
            }
            let data;
            try {
                data = JSON.parse(trimmedText);
            } catch (parseError) {
                if (looksLikeHtml) {
                    window.location.href = fallbackUrl.toString();
                    return;
                }
                console.error('Invalid JSON response:', trimmedText);
                throw new Error('Invalid JSON response');
            }
            if (data.rows) {
                const existingBody = document.getElementById('simListBody');
                if (existingBody) {
                    existingBody.outerHTML = data.rows;
                } else {
                    const table = document.querySelector('.sim-transactions-card .table');
                    if (table) {
                        table.insertAdjacentHTML('beforeend', data.rows);
                    }
                }
            }
            if (data.pager && pagerWrapper) {
                pagerWrapper.innerHTML = data.pager;
            }
            if (data.networks) {
                updateNetworkSummary(data.networks);
            }
            if (url) {
                const cleanUrl = new URL(url, window.location.origin);
                cleanUrl.searchParams.delete('ajax');
                cleanUrl.searchParams.delete('__ajax');
                history.replaceState({}, '', cleanUrl.toString());
            }
            attachDeleteButtons();
            attachPagerLinks();
        } catch (error) {
            console.error(error);
        } finally {
			setTableLoading(false);
        }
    }

    function updateNetworkSummary(networks) {
        const summaryRow = document.querySelector('.sims-summary tbody tr');
        if (!summaryRow) return;
        
        summaryRow.innerHTML = '';
        
        if (Object.keys(networks).length === 0) {
            summaryRow.innerHTML = '<td colspan="10" class="text-center">No data found.</td>';
            return;
        }
        
        for (const [network, data] of Object.entries(networks)) {
            const soldCount = data.sold ?? 0;
            const totalCount = data.total ?? 0;
            const th = document.createElement('th');
            th.className = 'fs-4';
            th.innerHTML = `
                <span class="text-warning">${network.toUpperCase()}: </span>
                <span class="text-muted">${soldCount}/${totalCount}</span>
            `;
            summaryRow.appendChild(th);
        }
    }

    if (filterForm) {
        filterForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            await fetchFilteredRows(buildFilterUrl(0).toString());
        });

        const pageSizeSelect = document.getElementById('simFilterLimit');
        if (pageSizeSelect) {
            pageSizeSelect.addEventListener('change', () => {
                filterForm.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
            });
        }

        const resetButton = document.getElementById('simFilterReset');
        if (resetButton) {
            resetButton.addEventListener('click', async (event) => {
                event.preventDefault();
                filterForm.reset();
                if (pageSizeSelect) {
                    pageSizeSelect.value = '10';
                }
                const url = new URL(window.location.href);
                url.searchParams.delete('find');
                url.searchParams.delete('status');
                url.searchParams.delete('created_by');
                url.searchParams.delete('buyer_phone');
                url.searchParams.delete('network');
                url.searchParams.delete('type');
                url.searchParams.delete('date_start');
                url.searchParams.delete('date_end');
                url.searchParams.delete('page');
                url.searchParams.delete('limit');
                url.searchParams.set('ajax', '1');
                await fetchFilteredRows(url.toString());
            });
        }
    }

    attachDeleteButtons();
    attachPagerLinks();
});
</script>
<?php endif?>
<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>
<style>
	.bo-com2-header{
		display:flex;
		flex-wrap:wrap;
		gap:.5rem;
		align-items:center;
		justify-content:space-between;
		margin-bottom:.75rem;
		min-width:0;
	}
	.bo-com2-header > div:first-child{
		min-width:0;
	}
	.bo-com2-title{
		font-weight:700;
		letter-spacing:.06em;
		text-transform:uppercase;
		font-size:.95rem;
	}
	.bo-com2-sub{
		color: var(--bs-secondary-color);
		font-size:.82rem;
	}
	.bo-com2-actions{
		display:flex;
		flex-wrap:wrap;
		gap:.5rem;
		align-items:center;
		justify-content:flex-end;
		max-width:100%;
		min-width:0;
	}
	.bo-com2-actions .btn,
	.bo-com2-actions .bo-com2-pill{
		flex:0 1 auto;
		white-space:normal;
	}
	.bo-com2-actions .bo-com2-pill{
		overflow-wrap:anywhere;
	}
	.bo-com2-card{
		border:1px solid var(--bs-border-color);
		border-radius:1rem;
		background:var(--bs-body-bg);
		box-shadow: 0 18px 40px rgba(15,23,42,.08);
	}
	.bo-com2-kpi{
		border:1px solid rgba(var(--bs-secondary-rgb,108,117,125), .25);
		border-radius: .9rem;
		padding: .9rem 1rem;
		background: rgba(var(--bs-secondary-rgb,108,117,125), .06);
	}
	.bo-com2-kpi .label{
		font-size:.68rem;
		letter-spacing:.08em;
		text-transform:uppercase;
		color:var(--bs-secondary-color);
	}
	.bo-com2-kpi .value{
		font-weight:700;
		font-size:1.05rem;
	}
	.bo-com2-kpi .meta{
		font-size:.72rem;
		color:var(--bs-secondary-color);
	}
	.bo-com2-pill{
		border-radius:999px;
		border:1px solid rgba(var(--bs-secondary-rgb,108,117,125), .25);
		padding:.12rem .55rem;
		font-size:.72rem;
		color:var(--bs-secondary-color);
		background: rgba(var(--bs-secondary-rgb,108,117,125), .08);
		white-space:nowrap;
	}
	.bo-com2-spark{
		width:100%;
		height:54px;
	}
	.bo-com2-table thead th{
		text-transform:uppercase;
		font-size:.74rem;
		letter-spacing:.06em;
		white-space:nowrap;
	}
	.bo-com2-table td,
	.bo-com2-table th,
	.bo-com2-table .descriptionDiv,
	.bo-com2-table .wrap {
		white-space: normal !important;
		overflow-wrap: anywhere;
		word-break: break-word;
	}
	.bo-com2-row{
		cursor:pointer;
	}
	.bo-com2-user{
		display:flex;
		gap:.6rem;
		align-items:center;
	}
	.bo-com2-user img{
		width:30px;
		height:30px;
		border-radius:50%;
		object-fit:cover;
		border:1px solid rgba(0,0,0,.12);
	}

	.descriptionDiv{
		padding: 2px;
		border-radius: 5px;
		font-size: 10px !important;
		
	}
	@media (max-width: 768px){
		.bo-com2-header{
			align-items:flex-start;
		}
		.bo-com2-actions{
			width:100%;
			justify-content:flex-start;
		}
		.bo-com2-actions .btn{
			flex:1 1 calc(50% - .5rem);
			min-width:8.5rem;
		}
		.bo-com2-actions .bo-com2-pill{
			flex:1 1 100%;
			text-align:center;
		}
		.bo-com2-kpi{
			padding:.75rem .8rem;
		}
	}
	@media (max-width: 420px){
		.bo-com2-actions .btn{
			flex-basis:100%;
		}
	}
</style>

<div class="bo-com2-header">
	<div>
		<div class="bo-com2-title">Commissions Analytics</div>
		<div class="bo-com2-sub">Track commissions by period, status, network, type, and staff (paid-to & submitted-by).</div>
	</div>
	<div class="bo-com2-actions">
		<div class="bo-com2-pill" id="boCom2Range">—</div>
		<?php if (user_can('is_admin')): ?>
			<button type="button" id="bo-com-backfill" class="btn btn-outline-primary btn-sm">Re-run Backfill</button>
		<?php endif; ?>
		<button type="button" id="bo-com-export-pdf" class="btn btn-outline-secondary btn-sm">Export PDF</button>
		<button type="button" id="bo-com-export-analytics-pdf" class="btn btn-outline-secondary btn-sm">Export Analytics PDF</button>
		<button type="button" id="bo-com-export-xlsx" class="btn btn-outline-success btn-sm">Export Excel</button>
		<!-- <a style="text-decoration:none;" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/pay_dailycommission"><button class="btn btn-success btn-sm"><i class="fa-solid fa-plus"></i> Daily</button></a> -->
		<a class="btn btn-warning btn-sm" style="text-decoration:none;" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/pay_commission"><i class="fa-solid fa-plus"></i> Pay Commissions</a>
		<?php if (backoffice_user_is_admin(get_session()) || backoffice_user_is_general_manager(get_session()) || backoffice_user_is_operations_manager(get_session())): ?>
			<a class="btn btn-primary btn-sm" style="text-decoration:none;" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/commission_bank_transfers"><i class="fa-solid fa-building-columns me-1"></i> To Bank
				<?php if (!empty($commissionBankAttentionCount)): ?><span class="badge rounded-pill text-bg-danger ms-1" aria-label="<?=esc((string)$commissionBankAttentionCount)?> commission bank transfers require attention"><?=esc((string)$commissionBankAttentionCount)?></span><?php endif; ?>
			</a>
		<?php endif; ?>
	</div>
</div>

<div class="bo-com2-card p-3 mb-3">
	<div class="row g-2 align-items-end">
		<div class="col-lg-4">
			<label class="form-label small mb-1">Search</label>
			<div class="input-group input-group-sm">
				<span class="input-group-text bg-body-secondary"><i class="fa fa-search"></i></span>
				<input type="text" class="form-control" id="boCom2Find" placeholder="id, description, network, type, status, staff">
			</div>
		</div>
		<?php $boComCanViewAll = backoffice_commission_can_view_all(get_session()); ?>
		<div class="col-lg-2 col-6">
			<label class="form-label small mb-1">Scope</label>
			<select class="form-select form-select-sm" id="boCom2Scope">
				<option value="mine" <?= $boComCanViewAll ? '' : 'selected' ?>>My commissions (Created & Assigned)</option>
				<?php if ($boComCanViewAll): ?>
					<option value="all" selected>All commissions</option>
				<?php endif; ?>
				<option value="created">Created by me</option>
				<option value="pay_to">Assigned to me</option>
			</select>
		</div>
		<?php if ($boComCanViewAll): ?>
			<div class="col-lg-3 col-12">
				<label class="form-label small mb-1">Assigned user</label>
				<div class="input-group input-group-sm">
					<input type="text" class="form-control" id="boCom2PayToFind" placeholder="Search user…">
					<button class="btn btn-outline-secondary" type="button" id="boCom2PayToClear">Clear</button>
				</div>
				<input type="hidden" id="boCom2PayToId" value="">
				<div class="list-group position-absolute w-100 d-none" style="z-index:1055; max-height:220px; overflow:auto;" id="boCom2PayToSug"></div>
			</div>
			<div class="col-lg-3 col-12">
				<label class="form-label small mb-1">Submitted by user</label>
				<div class="input-group input-group-sm">
					<input type="text" class="form-control" id="boCom2CreatedByFind" placeholder="Search user…">
					<button class="btn btn-outline-secondary" type="button" id="boCom2CreatedByClear">Clear</button>
				</div>
				<input type="hidden" id="boCom2CreatedById" value="">
				<div class="list-group position-absolute w-100 d-none" style="z-index:1055; max-height:220px; overflow:auto;" id="boCom2CreatedBySug"></div>
			</div>
		<?php endif; ?>
		<div class="col-lg-2 col-6">
			<label class="form-label small mb-1">Status</label>
			<select class="form-select form-select-sm" id="boCom2Status">
				<option value="">All</option>
				<option value="approved">Approved</option>
				<option value="pending">Pending</option>
				<option value="rejected">Rejected</option>
				<option value="draft">Draft</option>
			</select>
		</div>
		<div class="col-lg-2 col-6">
			<label class="form-label small mb-1">Period</label>
			<select class="form-select form-select-sm" id="boCom2Period">
				<option value="today">Today</option>
				<option value="7d">Last 7 days</option>
				<option value="30d" selected>Last 30 days</option>
				<option value="quarter">Quarter</option>
				<option value="ytd">Year to date</option>
				<option value="year">Last 365 days</option>
				<option value="custom">Custom range</option>
			</select>
		</div>
		<div class="col-lg-2 col-6">
			<label class="form-label small mb-1">Network</label>
			<select class="form-select form-select-sm" id="boCom2Network">
				<option value="">All</option>
			</select>
		</div>
		<div class="col-lg-2 col-6">
			<label class="form-label small mb-1">Commission type</label>
			<select class="form-select form-select-sm" id="boCom2Type">
				<option value="">All</option>
			</select>
		</div>
		<div class="col-lg-3 col-6 d-none" id="boCom2CustomStartWrap">
			<label class="form-label small mb-1">Start date</label>
			<input type="date" class="form-control form-control-sm" id="boCom2Start">
		</div>
		<div class="col-lg-3 col-6 d-none" id="boCom2CustomEndWrap">
			<label class="form-label small mb-1">End date</label>
			<input type="date" class="form-control form-control-sm" id="boCom2End">
		</div>
		<div class="col-lg-2 col-12 text-lg-end">
			<button class="btn btn-outline-secondary btn-sm" id="boCom2Reset">Reset</button>
			<button class="btn btn-primary btn-sm" id="boCom2Apply">Apply</button>
		</div>
	</div>
</div>

<div class="row g-2 mb-3">
	<div class="col-12 col-lg-3">
		<div class="bo-com2-kpi h-100">
			<div class="label">Total amount</div>
			<div class="value" id="boCom2KpiAmount">—</div>
			<div class="meta" id="boCom2KpiSplit">—</div>
		</div>
	</div>
	<div class="col-6 col-lg-2">
		<div class="bo-com2-kpi h-100">
			<div class="label">Transactions</div>
			<div class="value" id="boCom2KpiCount">—</div>
			<div class="meta">Total rows</div>
		</div>
	</div>
	<div class="col-6 col-lg-2">
		<div class="bo-com2-kpi h-100">
			<div class="label">Approved</div>
			<div class="value text-success" id="boCom2KpiApproved">—</div>
			<div class="meta" id="boCom2KpiApprovedAmt">—</div>
		</div>
	</div>
	<div class="col-6 col-lg-2">
		<div class="bo-com2-kpi h-100">
			<div class="label">Pending</div>
			<div class="value text-warning" id="boCom2KpiPending">—</div>
			<div class="meta" id="boCom2KpiPendingAmt">—</div>
		</div>
	</div>
	<div class="col-6 col-lg-3">
		<div class="bo-com2-kpi h-100">
			<div class="d-flex justify-content-between align-items-center">
				<div>
					<div class="label">Trend</div>
					<div class="meta">Daily totals</div>
				</div>
				<div class="bo-com2-pill" id="boCom2TrendMeta">—</div>
			</div>
			<canvas class="bo-com2-spark" id="boCom2Spark" height="54"></canvas>
		</div>
	</div>
</div>

<ul class="nav nav-tabs mb-3" id="boCom2Tabs" role="tablist">
	<li class="nav-item" role="presentation">
		<button class="nav-link active" id="boCom2-records-tab" data-bs-toggle="tab" data-bs-target="#boCom2-records" type="button" role="tab" aria-controls="boCom2-records" aria-selected="true">Records</button>
	</li>
	<li class="nav-item" role="presentation">
		<button class="nav-link" id="boCom2-analytics-tab" data-bs-toggle="tab" data-bs-target="#boCom2-analytics" type="button" role="tab" aria-controls="boCom2-analytics" aria-selected="false">Analytics</button>
	</li>
	<li class="nav-item" role="presentation">
		<button class="nav-link" id="boCom2-charts-tab" data-bs-toggle="tab" data-bs-target="#boCom2-charts" type="button" role="tab" aria-controls="boCom2-charts" aria-selected="false">Charts</button>
	</li>
</ul>

<div class="tab-content" id="boCom2TabContent">
	<div class="tab-pane fade" id="boCom2-analytics" role="tabpanel" aria-labelledby="boCom2-analytics-tab">

<div class="row g-2 mb-3">
	<div class="col-12">
		<div class="bo-com2-card p-3 h-100">
			<div class="d-flex justify-content-between align-items-center mb-2">
				<div>
					<div class="fw-semibold">Commission Source Revenue</div>
					<div class="bo-com2-sub">Detailed source split such as MTN CASHIN, MTN AIRTIME, MTN AGENT, MTN EVD, TELECEL EVD.</div>
				</div>
				<div class="bo-com2-pill" id="boCom2SourceCount">—</div>
			</div>
			<div class="table-responsive">
				<table class="table table-sm table-hover bo-com2-table mb-0">
					<thead><tr><th>Source</th><th class="text-end">Total</th><th class="text-end">Count</th></tr></thead>
					<tbody id="boCom2BySourceRevenue"></tbody>
				</table>
			</div>
		</div>
	</div>
	<div class="col-12">
		<div class="bo-com2-card p-3 h-100">
			<div class="d-flex justify-content-between align-items-center mb-2">
				<div class="fw-semibold">Breakdown by Commission Type</div>
				<div class="bo-com2-pill" id="boCom2TypeCount">—</div>
			</div>
			<div class="table-responsive">
				<table class="table table-sm table-hover bo-com2-table mb-0">
					<thead><tr><th>Type</th><th class="text-end">Total</th><th class="text-end">Count</th></tr></thead>
					<tbody id="boCom2ByType"></tbody>
				</table>
			</div>
		</div>
	</div>
</div>

<div class="row g-2 mb-3">
	<div class="col-12">
		<div class="bo-com2-card p-3 h-100">
			<div class="d-flex justify-content-between align-items-center mb-2">
				<div class="fw-semibold">Breakdown by Network</div>
				<div class="bo-com2-pill" id="boCom2NetCount">â€”</div>
			</div>
			<div class="table-responsive">
				<table class="table table-sm table-hover bo-com2-table mb-0">
					<thead><tr><th>Network</th><th class="text-end">Total</th><th class="text-end">Count</th></tr></thead>
					<tbody id="boCom2ByNetwork"></tbody>
				</table>
			</div>
		</div>
	</div>
</div>

<div class="row g-2 mb-3">
	<div class="col-12">
		<div class="bo-com2-card p-3 h-100">
			<div class="d-flex justify-content-between align-items-center mb-2">
				<div class="fw-semibold">Submitted-By Users Ranking</div>
				<div class="bo-com2-pill" id="boCom2CreatedCount">All</div>
			</div>
			<div class="table-responsive">
				<table class="table table-sm table-hover bo-com2-table mb-0">
					<thead><tr><th class="text-muted">#</th><th>User</th><th class="text-end">Total</th><th class="text-end">%</th><th class="text-end">Count</th></tr></thead>
					<tbody id="boCom2TopCreated"></tbody>
				</table>
			</div>
		</div>
	</div>
</div>

	</div>

	<div class="tab-pane fade" id="boCom2-charts" role="tabpanel" aria-labelledby="boCom2-charts-tab">
		<div class="row g-2">
			<div class="col-12">
				<div class="bo-com2-card p-3">
					<div class="d-flex justify-content-between align-items-center mb-2">
						<div class="fw-semibold">Charts</div>
						<div class="bo-com2-pill" id="boCom2ChartMeta">—</div>
					</div>
					<canvas id="boCom2ChartTrend" height="260" style="width:100%;height:260px;"></canvas>
				</div>
			</div>
			<div class="col-lg-6">
				<div class="bo-com2-card p-3 h-100">
					<div class="d-flex justify-content-between align-items-center mb-2">
						<div class="fw-semibold">Commission Source Revenue</div>
						<div class="bo-com2-pill" id="boCom2ChartSourceMeta">—</div>
					</div>
					<canvas id="boCom2ChartSource" height="300" style="width:100%;height:300px;"></canvas>
				</div>
			</div>
			<div class="col-lg-6">
				<div class="bo-com2-card p-3 h-100">
					<div class="d-flex justify-content-between align-items-center mb-2">
						<div class="fw-semibold">Submitted-By Ranking (Top)</div>
						<div class="bo-com2-pill" id="boCom2ChartCreatedMeta">—</div>
					</div>
					<canvas id="boCom2ChartCreated" height="300" style="width:100%;height:300px;"></canvas>
				</div>
			</div>
			<div class="col-lg-6">
				<div class="bo-com2-card p-3 h-100">
					<div class="d-flex justify-content-between align-items-center mb-2">
						<div class="fw-semibold">By Network (Total)</div>
						<div class="bo-com2-pill" id="boCom2ChartNetworkMeta">—</div>
					</div>
					<canvas id="boCom2ChartNetwork" height="260" style="width:100%;height:260px;"></canvas>
				</div>
			</div>
			<div class="col-lg-6">
				<div class="bo-com2-card p-3 h-100">
					<div class="d-flex justify-content-between align-items-center mb-2">
						<div class="fw-semibold">By Commission Type (Total)</div>
						<div class="bo-com2-pill" id="boCom2ChartTypeMeta">—</div>
					</div>
					<canvas id="boCom2ChartType" height="260" style="width:100%;height:260px;"></canvas>
				</div>
			</div>
			<div class="col-lg-6">
				<div class="bo-com2-card p-3 h-100">
					<div class="d-flex justify-content-between align-items-center mb-2">
						<div class="fw-semibold">Status Distribution (Count)</div>
						<div class="bo-com2-pill" id="boCom2ChartStatusMeta">—</div>
					</div>
					<canvas id="boCom2ChartStatus" height="260" style="width:100%;height:260px;"></canvas>
				</div>
			</div>
			<div class="col-lg-6">
				<div class="bo-com2-card p-3 h-100">
					<div class="d-flex justify-content-between align-items-center mb-2">
						<div class="fw-semibold">Currency Split (Total)</div>
						<div class="bo-com2-pill" id="boCom2ChartCurrencyMeta">—</div>
					</div>
					<canvas id="boCom2ChartCurrency" height="260" style="width:100%;height:260px;"></canvas>
				</div>
			</div>
		</div>
	</div>

	<div class="tab-pane fade show active" id="boCom2-records" role="tabpanel" aria-labelledby="boCom2-records-tab">

<div class="bo-com2-card p-3">
	<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-2">
		<div>
			<div class="fw-semibold">Commission Records</div>
		</div>
	</div>
	<div class="table-responsive">
		<table class="table table-striped table-hover bo-com2-table">
			<thead class="d-none d-lg-table-header-group">
				<tr>
					<th>#</th>
					<th>Date</th>
					<th>Description</th>
					<th>Network/Type</th>
					<th class="text-end">Amount</th>
					<th>Agent</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody id="boCom2Rows">
				<tr><td colspan="7" class="text-center">Loading…</td></tr>
			</tbody>
		</table>
	</div>
	<div class="d-flex gap-2 align-items-center">
		<div class="bo-com2-pill" id="boCom2ListMeta">—</div>
		<div class="btn-group btn-group-sm" role="group" aria-label="Pagination">
			<button class="btn btn-sm btn-outline-secondary rounded-pill" type="button" id="boCom2Prev">Prev</button>
			<button class="btn btn-sm btn-outline-secondary rounded-pill" type="button" id="boCom2Next">Next</button>
		</div>
	</div>
</div>

	</div>
</div>

<script>

document.addEventListener('DOMContentLoaded', function(){
	const refs = {
		range: document.getElementById('boCom2Range'),
		backfill: document.getElementById('bo-com-backfill'),
		find: document.getElementById('boCom2Find'),
		exportPdf: document.getElementById('bo-com-export-pdf'),
		exportAnalyticsPdf: document.getElementById('bo-com-export-analytics-pdf'),
		exportXlsx: document.getElementById('bo-com-export-xlsx'),
		scope: document.getElementById('boCom2Scope'),
		status: document.getElementById('boCom2Status'),
		period: document.getElementById('boCom2Period'),
		network: document.getElementById('boCom2Network'),
		type: document.getElementById('boCom2Type'),
		startWrap: document.getElementById('boCom2CustomStartWrap'),
		endWrap: document.getElementById('boCom2CustomEndWrap'),
		start: document.getElementById('boCom2Start'),
		end: document.getElementById('boCom2End'),
		apply: document.getElementById('boCom2Apply'),
		reset: document.getElementById('boCom2Reset'),
		kpiAmount: document.getElementById('boCom2KpiAmount'),
		kpiSplit: document.getElementById('boCom2KpiSplit'),
		kpiCount: document.getElementById('boCom2KpiCount'),
		kpiApproved: document.getElementById('boCom2KpiApproved'),
		kpiPending: document.getElementById('boCom2KpiPending'),
		kpiApprovedAmt: document.getElementById('boCom2KpiApprovedAmt'),
		kpiPendingAmt: document.getElementById('boCom2KpiPendingAmt'),
		spark: document.getElementById('boCom2Spark'),
		trendMeta: document.getElementById('boCom2TrendMeta'),
		byNetwork: document.getElementById('boCom2ByNetwork'),
		byType: document.getElementById('boCom2ByType'),
		bySourceRevenue: document.getElementById('boCom2BySourceRevenue'),
		netCount: document.getElementById('boCom2NetCount'),
		typeCount: document.getElementById('boCom2TypeCount'),
		sourceCount: document.getElementById('boCom2SourceCount'),
		createdCount: document.getElementById('boCom2CreatedCount'),
		topCreated: document.getElementById('boCom2TopCreated'),
		rows: document.getElementById('boCom2Rows'),
		listMeta: document.getElementById('boCom2ListMeta'),
		prev: document.getElementById('boCom2Prev'),
		next: document.getElementById('boCom2Next'),
		chartMeta: document.getElementById('boCom2ChartMeta'),
		chartTrend: document.getElementById('boCom2ChartTrend'),
		chartNetworkMeta: document.getElementById('boCom2ChartNetworkMeta'),
		chartNetwork: document.getElementById('boCom2ChartNetwork'),
		chartTypeMeta: document.getElementById('boCom2ChartTypeMeta'),
		chartType: document.getElementById('boCom2ChartType'),
		chartSourceMeta: document.getElementById('boCom2ChartSourceMeta'),
		chartSource: document.getElementById('boCom2ChartSource'),
		chartCreatedMeta: document.getElementById('boCom2ChartCreatedMeta'),
		chartCreated: document.getElementById('boCom2ChartCreated'),
		chartStatusMeta: document.getElementById('boCom2ChartStatusMeta'),
		chartStatus: document.getElementById('boCom2ChartStatus'),
		chartCurrencyMeta: document.getElementById('boCom2ChartCurrencyMeta'),
		chartCurrency: document.getElementById('boCom2ChartCurrency'),
		payToFind: document.getElementById('boCom2PayToFind'),
		payToId: document.getElementById('boCom2PayToId'),
		payToSug: document.getElementById('boCom2PayToSug'),
		payToClear: document.getElementById('boCom2PayToClear'),

		createdByFind: document.getElementById('boCom2CreatedByFind'),
		createdById: document.getElementById('boCom2CreatedById'),
		createdBySug: document.getElementById('boCom2CreatedBySug'),
		createdByClear: document.getElementById('boCom2CreatedByClear'),
	};

	if(!refs.find || !refs.rows) return;

	const urlDashboard = '<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/commissions_dashboard_v2';
	const urlList = '<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/commissions_list_v2';
	const urlUsers = '<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/commissions_users_v2';
	const exportBaseUrl = '<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export';
	const viewBase = '<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_commission/';
	const editBase = '<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit_commission/';
	const deleteBase = '<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/delete_commission/';
	const defaultScope = <?= $boComCanViewAll ? "'all'" : "'mine'" ?>;

	const state = {
		page: 1,
		limit: 10,
		lastTotalPages: 1,
	};
	let forceBackfillOnce = false;
	let lastDashboard = null;
	const chartStore = new WeakMap();

	if(refs.scope && defaultScope === 'all' && (refs.scope.value === '' || refs.scope.value === 'mine')){
		refs.scope.value = 'all';
	}

	function escapeHtml(str){
		return String(str ?? '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}
	function formatMoney(v){
		const n = Number(v || 0);
		return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}
	function sumAmount(rows){
		return (rows || []).reduce((total, row) => total + Number(row?.total_amount || 0), 0);
	}
	function breakdownMeta(rows, label){
		return String((rows || []).length) + ' ' + label + ' | GHS ' + formatMoney(sumAmount(rows));
	}
	function badge(status){
		const s = String(status || '').toLowerCase();
		let cls = 'badge bg-secondary';
		if(s === 'approved') cls = 'badge bg-success';
		else if(s === 'pending' || s === 'draft') cls = 'badge bg-warning text-dark';
		else if(s === 'rejected') cls = 'badge bg-danger';
		return '<span class="' + cls + '">' + escapeHtml(status || '') + '</span>';
	}

	function chartsVisible(){
		if(!refs.chartTrend) return false;
		return refs.chartTrend.offsetParent !== null;
	}
	function renderCharts(data, force){
		if(!data) return;
		if(!force && !chartsVisible()) return;
		if(refs.chartMeta) refs.chartMeta.textContent = String((data.series || []).length) + ' days';
		drawLineChart(refs.chartTrend, data.series || []);
		drawBarChart(refs.chartNetwork, data.by_network || []);
		drawBarChart(refs.chartType, data.by_commission_type || []);
		drawHBarChart(refs.chartSource, data.by_source_revenue || [], 15);
		drawHBarChart(refs.chartCreated, data.top_created_by || [], 15);
		drawDonutChart(refs.chartStatus, buildStatusSeries(data));
		drawDonutChart(refs.chartCurrency, buildCurrencySeries(data));
		attachCanvasTooltip(refs.chartTrend);
		attachCanvasTooltip(refs.chartNetwork);
		attachCanvasTooltip(refs.chartType);
		attachCanvasTooltip(refs.chartSource);
		attachCanvasTooltip(refs.chartCreated);
		attachCanvasTooltip(refs.chartStatus);
		attachCanvasTooltip(refs.chartCurrency);
		if(refs.chartNetworkMeta) refs.chartNetworkMeta.textContent = String((data.by_network || []).length) + ' networks';
		if(refs.chartTypeMeta) refs.chartTypeMeta.textContent = String((data.by_commission_type || []).length) + ' types';
		if(refs.chartSourceMeta) refs.chartSourceMeta.textContent = String((data.by_source_revenue || []).length) + ' sources';
		if(refs.chartCreatedMeta) refs.chartCreatedMeta.textContent = 'Top 15';
		if(refs.chartStatusMeta) refs.chartStatusMeta.textContent = 'Counts';
		if(refs.chartCurrencyMeta) refs.chartCurrencyMeta.textContent = 'Totals';
	}

	function qs(){
		const p = new URLSearchParams();
		const find = refs.find.value.trim();
		if(find) p.set('find', find);
		const scope = refs.scope.value.trim();
		if(scope) p.set('scope', scope);
		const payToId = (refs.payToId?.value || '').trim();
		if(payToId) p.set('pay_to', payToId);
		const createdById = (refs.createdById?.value || '').trim();
		if(createdById) p.set('created_by', createdById);
		const status = refs.status.value.trim();
		if(status) p.set('status', status);

		const period = refs.period.value.trim();
		if(period && period !== 'custom') p.set('period', period);
		if(period === 'custom'){
			const s = refs.start.value;
			const e = refs.end.value;
			if(s && e){
				p.set('start', s);
				p.set('end', e);
			}
		}
		const nw = refs.network.value.trim();
		if(nw) p.set('network', nw);
		const tp = refs.type.value.trim();
		if(tp) p.set('commission_type', tp);
		if(forceBackfillOnce) p.set('force_backfill', '1');
		return p;
	}

	function qsExport(){
		const params = qs();
		params.delete('page');
		params.delete('limit');
		params.set('entity', 'commissions');
		params.set('resource', 'list');
		return params;
	}

	if (refs.backfill) {
		refs.backfill.addEventListener('click', function(){
			forceBackfillOnce = true;
			refs.backfill.disabled = true;
			refs.backfill.textContent = 'Re-running...';
			refreshAll();
		});
	}
	if (refs.exportPdf) {
		refs.exportPdf.addEventListener('click', function(){
			const params = qsExport();
			params.set('format', 'pdf');
			window.location.href = exportBaseUrl + '?' + params.toString();
		});
	}
	if (refs.exportAnalyticsPdf) {
		refs.exportAnalyticsPdf.addEventListener('click', function(){
			const params = qsExport();
			params.set('resource', 'analytics');
			params.set('format', 'pdf');
			window.location.href = exportBaseUrl + '?' + params.toString();
		});
	}
	if (refs.exportXlsx) {
		refs.exportXlsx.addEventListener('click', function(){
			const params = qsExport();
			params.set('format', 'xlsx');
			window.location.href = exportBaseUrl + '?' + params.toString();
		});
	}

	function toggleCustom(){
		const isCustom = refs.period.value === 'custom';
		refs.startWrap.classList.toggle('d-none', !isCustom);
		refs.endWrap.classList.toggle('d-none', !isCustom);
	}

	function drawSpark(series){
		if(!refs.spark) return;
		const ctx = refs.spark.getContext('2d');
		const w = refs.spark.width;
		const h = refs.spark.height;
		ctx.clearRect(0,0,w,h);
		const vals = (series || []).map(x => Number(x.total_amount || 0));
		if(!vals.length){
			ctx.fillStyle = 'rgba(108,117,125,.6)';
			ctx.font = '12px system-ui';
			ctx.fillText('No data', 6, 18);
			return;
		}
		const max = Math.max(...vals, 1);
		const min = Math.min(...vals, 0);
		const pad = 6;
		const xStep = (w - pad*2) / Math.max(vals.length - 1, 1);
		ctx.lineWidth = 2;
		ctx.strokeStyle = 'rgba(var(--bs-primary-rgb, 13,110,253), .9)';
		ctx.beginPath();
		vals.forEach((v, i) => {
			const x = pad + i * xStep;
			const y = pad + (h - pad*2) * (1 - ((v - min) / (max - min || 1)));
			if(i === 0) ctx.moveTo(x,y); else ctx.lineTo(x,y);
		});
		ctx.stroke();
	}

	function getThemeRgb(varName, fallback){
		const raw = getComputedStyle(document.body).getPropertyValue(varName).trim();
		if(!raw) return fallback;
		const parts = raw.split(',').map(s => parseFloat(s.trim())).filter(n => !isNaN(n));
		if(parts.length < 3) return fallback;
		return [parts[0], parts[1], parts[2]];
	}

	function prepCanvas(canvas){
		if(!canvas) return null;
		const dpr = window.devicePixelRatio || 1;
		const rect = canvas.getBoundingClientRect();
		const w = Math.max(1, Math.floor(rect.width * dpr));
		const h = Math.max(1, Math.floor(rect.height * dpr));
		if(canvas.width !== w) canvas.width = w;
		if(canvas.height !== h) canvas.height = h;
		const ctx = canvas.getContext('2d');
		ctx.setTransform(1, 0, 0, 1, 0, 0);
		return { ctx, w, h, dpr };
	}

	function trimLabel(ctx, text, maxWidth){
		const raw = String(text || '');
		if(raw === '') return '';
		if(ctx.measureText(raw).width <= maxWidth) return raw;
		let out = raw;
		for(let i = raw.length - 1; i > 0; i--){
			const candidate = raw.slice(0, i).trim() + '…';
			if(ctx.measureText(candidate).width <= maxWidth){
				out = candidate;
				break;
			}
		}
		return out;
	}

	function parseColor(color){
		if(!color) return null;
		const c = color.trim().toLowerCase();
		if(c.startsWith('#')){
			let hex = c.slice(1);
			if(hex.length === 3) hex = hex.split('').map(ch => ch + ch).join('');
			if(hex.length !== 6) return null;
			const num = parseInt(hex, 16);
			if(Number.isNaN(num)) return null;
			return { rgb: [(num >> 16) & 255, (num >> 8) & 255, num & 255], a: 1 };
		}
		const m = c.match(/rgba?\(([^)]+)\)/);
		if(!m) return null;
		const parts = m[1].split(',').map(x => parseFloat(x));
		if(parts.length < 3 || parts.some(n => Number.isNaN(n))) return null;
		const rgb = parts.slice(0,3).map(n => Math.max(0, Math.min(255, n)));
		const a = parts.length >= 4 && !Number.isNaN(parts[3]) ? Math.max(0, Math.min(1, parts[3])) : 1;
		return { rgb, a };
	}

	function getLuma(rgb){
		if(!rgb) return 1;
		const [r,g,b] = rgb.map(v => v / 255).map(v => v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4));
		return 0.2126 * r + 0.7152 * g + 0.0722 * b;
	}

	function getEffectiveBg(el){
		let node = el;
		while(node){
			const bgRaw = getComputedStyle(node).backgroundColor;
			const parsed = parseColor(bgRaw);
			if(parsed && parsed.a > 0.05) return parsed.rgb;
			node = node.parentElement;
		}
		const bodyParsed = parseColor(getComputedStyle(document.body).backgroundColor);
		return bodyParsed ? bodyParsed.rgb : [255,255,255];
	}

	function contrastRatio(rgb1, rgb2){
		const l1 = getLuma(rgb1);
		const l2 = getLuma(rgb2);
		return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
	}

	function pickBestTextColor(bgRgb, candidates){
		let best = null;
		let bestRgb = null;
		let bestRatio = -1;
		(candidates || []).forEach(c => {
			const parsed = parseColor(c);
			if(!parsed) return;
			const ratio = contrastRatio(bgRgb, parsed.rgb);
			if(ratio > bestRatio){
				bestRatio = ratio;
				best = c;
				bestRgb = parsed.rgb;
			}
		});
		return { color: best, rgb: bestRgb, ratio: bestRatio };
	}

	function getThemeTextColors(el){
		const theme = (document.documentElement.getAttribute('data-bs-theme') || '').toLowerCase();
		if(theme === 'dark'){
			return { label: '#ffffff', muted: '#ffffff', isDark: true };
		}
		const rootStyle = getComputedStyle(document.documentElement);
		let label = (rootStyle.getPropertyValue('--text-color') || '').trim();
		if(!label){
			label = (rootStyle.getPropertyValue('--color-text') || '').trim();
		}
		if(!label){
			label = theme === 'dark' ? '#ffffff' : '#000000';
		}
		const parsed = parseColor(label);
		const muted = parsed
			? `rgba(${parsed.rgb[0]},${parsed.rgb[1]},${parsed.rgb[2]},0.65)`
			: label;
		return { label, muted, isDark: theme === 'dark' };
	}

	function registerChart(canvas, meta){
		if(!canvas) return;
		chartStore.set(canvas, meta);
	}

	function attachCanvasTooltip(canvas){
		if(!canvas) return;
		if(canvas.__boCom2TipBound) return;
		canvas.__boCom2TipBound = true;
		const parent = canvas.parentElement;
		if(parent && getComputedStyle(parent).position === 'static'){
			parent.style.position = 'relative';
		}
		const tip = document.createElement('div');
		tip.style.position = 'absolute';
		tip.style.display = 'none';
		tip.style.zIndex = '10';
		tip.style.pointerEvents = 'none';
		tip.style.background = 'rgba(0,0,0,.78)';
		tip.style.color = '#fff';
		tip.style.padding = '6px 8px';
		tip.style.borderRadius = '8px';
		tip.style.fontSize = '12px';
		tip.style.maxWidth = '280px';
		parent.appendChild(tip);
		let locked = false;
		function showAt(html, cx, cy){
			tip.innerHTML = html;
			tip.style.left = (cx + 12) + 'px';
			tip.style.top = (cy + 12) + 'px';
			tip.style.display = 'block';
		}
		function hide(){
			if(locked) return;
			tip.style.display = 'none';
		}
		function buildHtml(hit){
			let html = '<div class="fw-semibold">' + escapeHtml(hit.label || '') + '</div>';
			if(hit.value != null){
				if(hit.fmt === 'count') html += '<div>Value: ' + String(Math.round(Number(hit.value || 0))) + '</div>';
				else html += '<div>Value: ' + formatMoney(hit.value || 0) + '</div>';
			}
			if(hit.percent != null) html += '<div>Share: ' + Number(hit.percent || 0).toFixed(1) + '%</div>';
			return html;
		}

		function findHit(px, py){
			const meta = chartStore.get(canvas);
			if(!meta) return null;
			if(meta.type === 'bars'){
				return (meta.items || []).find(it => px >= it.x && px <= (it.x + it.w) && py >= it.y && py <= (it.y + it.h)) || null;
			}
			if(meta.type === 'donut'){
				const dx = px - meta.cx;
				const dy = py - meta.cy;
				const r = Math.sqrt(dx*dx + dy*dy);
				if(r < meta.r0 || r > meta.r1) return null;
				let ang = Math.atan2(dy, dx);
				if(ang < 0) ang += Math.PI*2;
				return (meta.items || []).find(it => {
					const a0 = it.a0;
					const a1 = it.a1;
					if(a1 >= a0) return ang >= a0 && ang <= a1;
					return ang >= a0 || ang <= a1;
				}) || null;
			}
			if(meta.type === 'line'){
				let best = null;
				let bestDx = Infinity;
				(meta.points || []).forEach(p => {
					const dx = Math.abs(px - p.x);
					if(dx < bestDx){ bestDx = dx; best = p; }
				});
				if(best && bestDx < 24) return best;
				return null;
			}
			return null;
		}
		function onMove(e){
			if(locked) return;
			const rect = canvas.getBoundingClientRect();
			const sx = canvas.width / rect.width;
			const sy = canvas.height / rect.height;
			const px = (e.clientX - rect.left) * sx;
			const py = (e.clientY - rect.top) * sy;
			const hit = findHit(px, py);
			if(!hit){
				hide();
				return;
			}
			showAt(buildHtml(hit), e.clientX - rect.left, e.clientY - rect.top);
		}
		function onClick(e){
			const rect = canvas.getBoundingClientRect();
			const sx = canvas.width / rect.width;
			const sy = canvas.height / rect.height;
			const px = (e.clientX - rect.left) * sx;
			const py = (e.clientY - rect.top) * sy;
			const hit = findHit(px, py);
			if(!hit){
				locked = false;
				tip.style.display = 'none';
				return;
			}
			locked = true;
			showAt(buildHtml(hit), e.clientX - rect.left, e.clientY - rect.top);
		}
		canvas.addEventListener('mousemove', onMove);
		canvas.addEventListener('mouseleave', function(){
			locked = false;
			tip.style.display = 'none';
		});
		canvas.addEventListener('click', onClick);
	}

	function setSelectOptions(selectEl, values){
		if(!selectEl) return;
		const current = selectEl.value;
		selectEl.innerHTML = '<option value="">All</option>';
		(values || []).forEach(v => {
			const opt = document.createElement('option');
			opt.value = v;
			opt.textContent = v;
			selectEl.appendChild(opt);
		});
		selectEl.value = current;
	}

	function renderBreakdown(tbody, rows){
		if(!tbody) return;
		tbody.innerHTML = '';
		if(!rows || !rows.length){
			tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No data</td></tr>';
			return;
		}
		rows.forEach(r => {
			const tr = document.createElement('tr');
			tr.innerHTML = '<td class="fw-semibold">' + escapeHtml(r.label || '') + '</td>'
				+ '<td class="text-end fw-semibold">' + formatMoney(r.total_amount || 0) + '</td>'
				+ '<td class="text-end text-muted">' + String(r.total_rows || 0) + '</td>';
			tbody.appendChild(tr);
		});
	}

	function bindUserLinks(rootEl){
		if(!rootEl) return;
		rootEl.querySelectorAll('.boCom2UserLink').forEach(el => {
			el.addEventListener('click', function(e){
				e.stopPropagation();
				const kind = (el.getAttribute('data-kind') || '');
				const userId = el.getAttribute('data-user-id') || '';
				const userName = el.getAttribute('data-user-name') || '';
				if(!userId) return;
				applyUserFilter(kind === 'pay_to' ? 'pay_to' : 'created_by', userId, userName);
			});
		});
	}

	function drawLineChart(canvas, series){
		const prep = prepCanvas(canvas);
		if(!prep) return;
		const { ctx, w, h } = prep;
		ctx.clearRect(0, 0, w, h);
		const vals = (series || []).map(x => Number(x.total_amount || 0));
		if(!vals.length){
			ctx.fillStyle = 'rgba(108,117,125,.8)';
			ctx.font = '12px system-ui';
			ctx.fillText('No data', 10, 18);
			return;
		}
		const padL = 44;
		const padR = 12;
		const padT = 12;
		const padB = 28;
		const max = Math.max(...vals, 1);
		const min = Math.min(...vals, 0);
		const xStep = (w - padL - padR) / Math.max(vals.length - 1, 1);
		const toY = (v) => padT + (h - padT - padB) * (1 - ((v - min) / (max - min || 1)));
		const primaryRgb = getThemeRgb('--bs-primary-rgb', [13,110,253]);
		ctx.lineWidth = 2;
		ctx.strokeStyle = `rgba(${primaryRgb[0]},${primaryRgb[1]},${primaryRgb[2]},.9)`;
		ctx.beginPath();
		const points = [];
		vals.forEach((v, i) => {
			const x = padL + i * xStep;
			const y = toY(v);
			if(i === 0) ctx.moveTo(x,y); else ctx.lineTo(x,y);
			points.push({ x, y, label: String((series[i] && (series[i].day || series[i].label)) || ''), value: v });
		});
		ctx.stroke();
		registerChart(canvas, { type: 'line', points });
	}

	function drawBarChart(canvas, rows){
		const prep = prepCanvas(canvas);
		if(!prep) return;
		const { ctx, w, h } = prep;
		ctx.clearRect(0, 0, w, h);
		const data = (rows || []).slice().map(r => ({
			label: String(r.label || ''),
			total: Number(r.total_amount || 0),
		})).filter(r => r.total > 0);
		data.sort((a,b) => b.total - a.total);
		const top = data.slice(0, 10);
		if(!top.length){
			ctx.fillStyle = 'rgba(108,117,125,.8)';
			ctx.font = '12px system-ui';
			ctx.fillText('No data', 10, 18);
			return;
		}
		const padL = 12;
		const padR = 12;
		const padT = 12;
		const padB = 42;
		const plotH = h - padT - padB;
		const max = Math.max(...top.map(x => x.total), 1);
		const barGap = 10;
		const barW = (w - padL - padR - barGap * (top.length - 1)) / top.length;
		const accentRgb = getThemeRgb('--bs-warning-rgb', [255,193,7]);
		const { label: labelColor, muted: mutedColor } = getThemeTextColors(canvas);
		const items = [];
		ctx.font = '11px system-ui';
		top.forEach((item, i) => {
			const x = padL + i * (barW + barGap);
			const bh = (item.total / max) * plotH;
			const y = padT + (plotH - bh);
			ctx.fillStyle = `rgba(${accentRgb[0]},${accentRgb[1]},${accentRgb[2]},.75)`;
			ctx.fillRect(x, y, barW, bh);
			const valueText = formatMoney(item.total);
			const valueWidth = ctx.measureText(valueText).width;
			const canFitInside = bh >= 18 && (valueWidth + 8) <= barW;
			ctx.textAlign = 'center';
			ctx.textBaseline = 'middle';
			if(canFitInside){
				ctx.fillStyle = 'rgba(255,255,255,.95)';
				ctx.fillText(valueText, x + barW/2, y + (bh / 2));
			} else {
				ctx.fillStyle = labelColor;
				const aboveY = Math.max(padT + 6, y - 8);
				ctx.fillText(valueText, x + barW/2, aboveY);
			}
			ctx.textBaseline = 'top';
			ctx.fillStyle = mutedColor;
			const trimmed = trimLabel(ctx, item.label, Math.max(8, barW - 4));
			ctx.fillText(trimmed, x + barW/2, padT + plotH + 6);
			items.push({ x, y, w: barW, h: bh, label: item.label, value: item.total });
		});
		registerChart(canvas, { type: 'bars', items });
	}

	function drawHBarChart(canvas, rows, topN){
		const prep = prepCanvas(canvas);
		if(!prep) return;
		const { ctx, w, h } = prep;
		ctx.clearRect(0, 0, w, h);
		const base = Number(lastDashboard?.kpis?.total_amount || 0);
		const data = (rows || []).slice().map(r => {
			const total = Number(r.total_amount || 0);
			return { label: String(r.name || r.label || ''), total, percent: base > 0 ? (total / base) * 100 : null };
		}).filter(r => r.total > 0);
		data.sort((a,b) => b.total - a.total);
		const top = data.slice(0, Math.max(1, Number(topN || 15)));
		if(!top.length){
			ctx.fillStyle = 'rgba(108,117,125,.8)';
			ctx.font = '12px system-ui';
			ctx.fillText('No data', 10, 18);
			return;
		}
		const padL = 150;
		const padR = 14;
		const padT = 14;
		const padB = 14;
		const rowH = (h - padT - padB) / top.length;
		const max = Math.max(...top.map(x => x.total), 1);
		const primaryRgb = getThemeRgb('--bs-primary-rgb', [13,110,253]);
		const { label: labelColor } = getThemeTextColors(canvas);
		ctx.font = '11px system-ui';
		ctx.textBaseline = 'middle';
		const items = [];
		top.forEach((it, idx) => {
			const y0 = padT + idx * rowH;
			const barH = Math.max(8, rowH * 0.6);
			const cy = y0 + rowH/2;
			const bw = (w - padL - padR) * (it.total / max);
			ctx.fillStyle = `rgba(${primaryRgb[0]},${primaryRgb[1]},${primaryRgb[2]},.75)`;
			ctx.fillRect(padL, cy - barH/2, bw, barH);
			ctx.fillStyle = labelColor;
			ctx.textAlign = 'left';
			const label = trimLabel(ctx, it.label, Math.max(12, padL - 12));
			ctx.fillText(label, 8, cy);
			const valueText = formatMoney(it.total);
			const valueWidth = ctx.measureText(valueText).width;
			const canShowOutside = (padL + bw + valueWidth + 8) < (w - padR);
			if(canShowOutside){
				ctx.textAlign = 'left';
				ctx.fillStyle = labelColor;
				ctx.fillText(valueText, padL + bw + 6, cy);
			} else {
				ctx.textAlign = 'right';
				ctx.fillStyle = 'rgba(255,255,255,.95)';
				ctx.fillText(valueText, Math.max(padL + 6, padL + bw - 4), cy);
			}
			items.push({ x: padL, y: cy - barH/2, w: bw, h: barH, label: it.label, value: it.total, percent: it.percent });
		});
		registerChart(canvas, { type: 'bars', items });
	}

	function drawDonutChart(canvas, items){
		const prep = prepCanvas(canvas);
		if(!prep) return;
		const { ctx, w, h } = prep;
		ctx.clearRect(0, 0, w, h);
		const rows = (items || []).filter(x => Number(x.value || 0) > 0);
		const total = rows.reduce((s, r) => s + Number(r.value || 0), 0);
		if(!rows.length || total <= 0){
			ctx.fillStyle = 'rgba(108,117,125,.8)';
			ctx.font = '12px system-ui';
			ctx.fillText('No data', 10, 18);
			return;
		}
		const cx = w / 2;
		const cy = h / 2;
		const r1 = Math.min(w, h) * 0.38;
		const r0 = r1 * 0.62;
		let a = -Math.PI / 2;
		const metaItems = [];
		rows.forEach(r => {
			const v = Number(r.value || 0);
			const da = (v / total) * Math.PI * 2;
			ctx.beginPath();
			ctx.moveTo(cx, cy);
			ctx.fillStyle = r.color || 'rgba(13,110,253,.8)';
			ctx.arc(cx, cy, r1, a, a + da);
			ctx.closePath();
			ctx.fill();
			let a0 = a;
			let a1 = a + da;
			while(a0 < 0) a0 += Math.PI * 2;
			while(a1 < 0) a1 += Math.PI * 2;
			while(a0 >= Math.PI * 2) a0 -= Math.PI * 2;
			while(a1 >= Math.PI * 2) a1 -= Math.PI * 2;
			metaItems.push({ label: r.label, value: v, percent: (v / total) * 100, a0, a1, fmt: r.fmt || null });
			a += da;
		});
		ctx.globalCompositeOperation = 'destination-out';
		ctx.beginPath();
		ctx.arc(cx, cy, r0, 0, Math.PI * 2);
		ctx.fill();
		ctx.globalCompositeOperation = 'source-over';
		ctx.fillStyle = 'rgba(108,117,125,.75)';
		ctx.font = '12px system-ui';
		ctx.textAlign = 'center';
		ctx.fillText('Total', cx, cy - 6);
		ctx.fillStyle = 'rgba(33,37,41,.9)';
		const isCount = rows.length && rows[0].fmt === 'count';
		ctx.fillText(isCount ? String(Math.round(total)) : formatMoney(total), cx, cy + 12);
		registerChart(canvas, { type: 'donut', items: metaItems, cx, cy, r0, r1 });
	}

	function buildStatusSeries(data){
		const k = data?.kpis || {};
		const approved = Number(k.approved_count || 0);
		const pending = Number(k.pending_count || 0);
		const rejected = Number(k.rejected_count || 0);
		const total = Number(k.total_rows || 0);
		const other = Math.max(0, total - approved - pending - rejected);
		const success = getThemeRgb('--bs-success-rgb', [25,135,84]);
		const warn = getThemeRgb('--bs-warning-rgb', [255,193,7]);
		const danger = getThemeRgb('--bs-danger-rgb', [220,53,69]);
		const secondary = getThemeRgb('--bs-secondary-rgb', [108,117,125]);
		return [
			{ label: 'Approved', value: approved, color: `rgba(${success[0]},${success[1]},${success[2]},.8)`, fmt: 'count' },
			{ label: 'Pending', value: pending, color: `rgba(${warn[0]},${warn[1]},${warn[2]},.8)`, fmt: 'count' },
			{ label: 'Rejected', value: rejected, color: `rgba(${danger[0]},${danger[1]},${danger[2]},.8)`, fmt: 'count' },
			{ label: 'Other', value: other, color: `rgba(${secondary[0]},${secondary[1]},${secondary[2]},.6)`, fmt: 'count' },
		];
	}
	function buildCurrencySeries(data){
		const k = data?.kpis || {};
		const ghs = Number(k.total_amount_ghs || 0);
		const cfa = Number(k.total_amount_cfa || 0);
		const primary = getThemeRgb('--bs-primary-rgb', [13,110,253]);
		const info = getThemeRgb('--bs-info-rgb', [13,202,240]);
		return [
			{ label: 'GHS', value: ghs, color: `rgba(${primary[0]},${primary[1]},${primary[2]},.8)`, fmt: 'money' },
			{ label: 'CFA', value: cfa, color: `rgba(${info[0]},${info[1]},${info[2]},.75)`, fmt: 'money' },
		];
	}

	function renderTopUsers(tbody, rows, kind){
		if(!tbody) return;
		tbody.innerHTML = '';
		if(!rows || !rows.length){
			tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No data</td></tr>';
			return;
		}
		const base = Number(lastDashboard?.kpis?.total_amount || 0);
		rows.forEach((r, idx) => {
			const tr = document.createElement('tr');
			const userId = r.user_id || r.id || '';
			const name = escapeHtml(r.name || 'Unknown');
			const img = escapeHtml(r.image || '');
			const total = Number(r.total_amount || 0);
			const pct = base > 0 ? (total / base) * 100 : 0;
			tr.innerHTML = '<td class="text-muted">' + String(idx + 1) + '</td>'
				+ '<td><div class="bo-com2-user boCom2UserLink" data-kind="' + escapeHtml(kind || '') + '" data-user-id="' + escapeHtml(String(userId)) + '" data-user-name="' + name + '"><img src="' + img + '" alt=""> <div class="fw-semibold">' + name + '</div></div></td>'
				+ '<td class="text-end fw-semibold">' + formatMoney(total) + '</td>'
				+ '<td class="text-end text-muted">' + pct.toFixed(1) + '%</td>'
				+ '<td class="text-end text-muted">' + String(r.total_rows || 0) + '</td>';
			tbody.appendChild(tr);
		});
		bindUserLinks(tbody);
	}

	function normalizeCommissionDescription(value){
		const text = String(value ?? '')
			.replace(/\s*\[\s*momoremit_cashout_commission_id\s*:\s*\d+\s*\]\s*/gi, ' ')
			.replace(/\s+/g, ' ')
			.trim();
		return text || 'Commission';
	}

	function renderRows(rows){
		if(!refs.rows) return;
		refs.rows.innerHTML = '';
		if(!rows || !rows.length){
			refs.rows.innerHTML = '<tr><td colspan="9" class="text-center">No records to show!</td></tr>';
			return;
		}
		rows.forEach((r, idx) => {
			const tr = document.createElement('tr');
			tr.className = 'bo-com2-row';
			tr.addEventListener('click', function(){
				if(r && r.id) window.location.href = viewBase + String(r.id);
			});
			const payToId = r.assigned_to?.id || r.pay_to?.id || '';
			const createdById = r.created_by?.id || '';
			const statusLower = String(r.status || '').toLowerCase();
			const canApprove = !!r.can_act;
			const canShowDelete = !!r.can_delete;
			const descriptionText = normalizeCommissionDescription(r.description);
			const actionsHtml = `
				<div class="btn-group btn-group-sm" role="group">
					<a class="btn btn-outline-primary" href="${viewBase}${escapeHtml(String(r.id))}">View</a>
					${canApprove ? `<a class="btn btn-warning" href="${editBase}${escapeHtml(String(r.id))}">Review</a>` : ''}
					${canShowDelete ? `<a class="btn btn-outline-danger" href="${deleteBase}${escapeHtml(String(r.id))}">Delete</a>` : ''}
				</div>
			`;
			tr.innerHTML = `
				<td class="d-none d-lg-table-cell">${(state.limit * (state.page - 1)) + idx + 1}</td>
				<td class="d-none d-lg-table-cell wrap">${escapeHtml(String(r.created_at || '').slice(0,19).replace('T',' '))}</td>
				<td class="d-none d-lg-table-cell wrap">${escapeHtml(descriptionText)}</td>
				<td class="d-none d-lg-table-cell">
					<small>${escapeHtml(r.network || '')}</small>
					<div class="descriptionDiv">${escapeHtml(r.commission_type || '')}${r.is_bulk ? ' <span class="text-muted">(bulk)</span>' : ''}</div>
					<div class="descriptionDiv text-muted">Assigned: ${escapeHtml(r.assigned_to?.name || r.pay_to?.name || '')}</div>
					<div class="descriptionDiv">${badge(r.status || '')}</div>
				</td>
				<td class="d-none d-lg-table-cell text-end fw-semibold">${formatMoney(r.amount || 0)}</td>
				<td class="d-none d-lg-table-cell"><div class="bo-com2-user"><img src="${escapeHtml(r.created_by?.image || '')}" alt=""> <div>${escapeHtml(r.created_by?.name || '')}</div></div></td>
				<td class="d-none d-lg-table-cell">${actionsHtml}</td>
			`;
			tr.querySelectorAll('a').forEach(a => {
				a.addEventListener('click', function(e){
					e.stopPropagation();
				});
			});
			refs.rows.appendChild(tr);

			const mobile = document.createElement('tr');
			mobile.className = 'd-lg-none';
			mobile.addEventListener('click', function(){
				if(r && r.id) window.location.href = viewBase + String(r.id);
			});
			mobile.innerHTML = `
				<td colspan="10">
					<div class="d-flex justify-content-between gap-2">
						<div>
							<div class="fw-semibold descriptionDiv wrap">${escapeHtml(normalizeCommissionDescription(r.description))}</div>
							<div class="text-muted small">${escapeHtml(String(r.created_at || '').slice(0,19).replace('T',' '))}</div>
							<div class="text-muted small">Assigned: ${escapeHtml(r.assigned_to?.name || r.pay_to?.name || '')}</div>
							<div class="mt-1">${badge(r.status || '')}</div>
						</div>
						<div class="text-end">
							<div class="fw-semibold">${formatMoney(r.amount || 0)}</div>
							<div class="text-muted small">${escapeHtml(r.network || '')}</div>
							<div class="mt-2">${actionsHtml}</div>
						</div>
					</div>
				</td>
			`;
			mobile.querySelectorAll('a').forEach(a => {
				a.addEventListener('click', function(e){
					e.stopPropagation();
				});
			});
			refs.rows.appendChild(mobile);
		});
		bindUserLinks(refs.rows);
	}

	function fetchDashboard(){
		const params = qs();
		return fetch(urlDashboard + '?' + params.toString(), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
			.then(window.backofficeParseJson)
			.then(data => {
				if(!data || !data.success) throw new Error(data?.message || data?.error || 'Failed to load commission dashboard.');
				lastDashboard = data;
				if(refs.range && data.range && data.range.start){
					refs.range.textContent = data.range.start + ' → ' + data.range.end;
				}

				setSelectOptions(refs.network, data.filters?.networks || []);
				setSelectOptions(refs.type, data.filters?.commission_types || []);
				const k = data.kpis || {};

				refs.kpiAmount.textContent = 'GHS ' + formatMoney(k.total_amount_ghs ?? k.total_amount ?? 0);
				refs.kpiSplit.textContent = 'CFA ' + (Number(k.total_amount_cfa || 0)).toLocaleString(undefined, { maximumFractionDigits: 0 }) + ' | Total ' + formatMoney(k.total_amount ?? 0);
				refs.kpiCount.textContent = String(k.total_rows ?? 0);
				refs.kpiApproved.textContent = String(k.approved_count ?? 0);
				refs.kpiPending.textContent = String(k.pending_count ?? 0);

				refs.kpiApprovedAmt.textContent = 'GHS ' + formatMoney(k.approved_amount ?? 0);
				refs.kpiPendingAmt.textContent = 'GHS ' + formatMoney(k.pending_amount ?? 0);
				refs.trendMeta.textContent = String((data.series || []).length) + ' days';
				drawSpark(data.series || []);
				renderBreakdown(refs.bySourceRevenue, data.by_source_revenue || []);
				renderBreakdown(refs.byNetwork, data.by_network || []);
				renderBreakdown(refs.byType, data.by_commission_type || []);
				refs.sourceCount.textContent = breakdownMeta(data.by_source_revenue || [], 'sources');
				refs.netCount.textContent = breakdownMeta(data.by_network || [], 'networks');
				refs.typeCount.textContent = breakdownMeta(data.by_commission_type || [], 'types');
				if(refs.createdCount) refs.createdCount.textContent = breakdownMeta(data.top_created_by || [], 'users');
				renderTopUsers(refs.topCreated, data.top_created_by || [], 'created_by');
				renderCharts(data, false);
				const tabBadge = document.getElementById('bo-tab-commissions-badge');
				if(tabBadge){
					const pending = Number(k.pending_count ?? 0);
					const overdue = Number(tabBadge.dataset.overdueCount || 0);
					const totalBadge = pending + overdue;
					tabBadge.textContent = totalBadge ? String(totalBadge) : '';
					tabBadge.style.display = totalBadge ? '' : 'none';
				}
			});
	}

	function fetchList(){
		const params = qs();
		params.set('page', String(state.page));
		params.set('limit', String(state.limit));
		refs.rows.innerHTML = '<tr><td colspan="10" class="text-center">Loading…</td></tr>';
		return fetch(urlList + '?' + params.toString(), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
			.then(window.backofficeParseJson)
			.then(data => {
				if(!data || !data.success) throw new Error(data?.message || data?.error || 'Failed to load commissions.');
				const pag = data.pagination || {};
				state.lastTotalPages = Number(pag.total_pages || 1);
				renderRows(data.rows || []);
				refs.listMeta.textContent = 'Page ' + String(pag.page || state.page) + ' / ' + String(state.lastTotalPages) + ' · ' + String(pag.total_rows || 0) + ' rows';
				refs.prev.disabled = state.page <= 1;
				refs.next.disabled = state.page >= state.lastTotalPages;
				if(forceBackfillOnce){
					forceBackfillOnce = false;
					if(refs.backfill){
						refs.backfill.disabled = false;
						refs.backfill.textContent = 'Re-run Backfill';
					}
				}
			})
			.catch(err => {
				if(forceBackfillOnce){
					forceBackfillOnce = false;
					if(refs.backfill){
						refs.backfill.disabled = false;
						refs.backfill.textContent = 'Re-run Backfill';
					}
				}
				throw err;
			});
	}

	function refreshAll(){
		state.page = 1;
		Promise.all([fetchDashboard(), fetchList()]).catch(() => {
			refs.rows.innerHTML = '<tr><td colspan="10" class="text-center">Failed to load commissions.</td></tr>';
		});
	}

	function hideSuggestions(listEl){
		if(!listEl) return;
		listEl.classList.add('d-none');
		listEl.innerHTML = '';
	}
	function showSuggestions(listEl){
		if(!listEl) return;
		listEl.classList.remove('d-none');
	}
	function renderUserSuggestions(listEl, users, onPick){
		if(!listEl) return;
		listEl.innerHTML = '';
		if(!users || !users.length){
			listEl.innerHTML = '<div class="list-group-item text-muted">No users</div>';
			return;
		}
		users.forEach(u => {
			const a = document.createElement('button');
			a.type = 'button';
			a.className = 'list-group-item list-group-item-action d-flex align-items-center gap-2';
			a.innerHTML = '<img src="' + escapeHtml(u.image || '') + '" style="width:26px;height:26px;border-radius:50%;object-fit:cover;border:1px solid rgba(0,0,0,.12)">' + '<div class="fw-semibold">' + escapeHtml(u.name || '') + '</div>';
			a.addEventListener('click', function(){
				onPick(u);
			});
			listEl.appendChild(a);
		});
	}
	function fetchUsers(find){
		const p = new URLSearchParams();
		p.set('find', find);
		return fetch(urlUsers + '?' + p.toString(), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
			.then(window.backofficeParseJson)
			.then(data => {
				if(!data || !data.success) return [];
				return data.users || [];
			});
	}
	function attachUserSearch(inputEl, hiddenIdEl, listEl, clearBtnEl){
		if(!inputEl || !hiddenIdEl || !listEl) return;
		let t2 = null;
		inputEl.addEventListener('input', function(){
			const v = inputEl.value.trim();
			hiddenIdEl.value = '';
			if(t2) clearTimeout(t2);
			if(!v){
				hideSuggestions(listEl);
				return;
			}
			t2 = setTimeout(() => {
				fetchUsers(v).then(users => {
					renderUserSuggestions(listEl, users, function(u){
						hiddenIdEl.value = String(u.id || '');
						inputEl.value = String(u.name || '');
						hideSuggestions(listEl);
						refreshAll();
					});
					showSuggestions(listEl);
				});
			}, 250);
		});
		inputEl.addEventListener('focus', function(){
			if(listEl.innerHTML.trim() !== '') showSuggestions(listEl);
		});
		document.addEventListener('click', function(e){
			if(e.target === inputEl || listEl.contains(e.target)) return;
			hideSuggestions(listEl);
		});
		if(clearBtnEl){
			clearBtnEl.addEventListener('click', function(){
				hiddenIdEl.value = '';
				inputEl.value = '';
				hideSuggestions(listEl);
				refreshAll();
			});
		}
	}

	function applyUserFilter(kind, userId, userName){
		if(kind === 'pay_to'){
			if(refs.payToId) refs.payToId.value = String(userId || '');
			if(refs.payToFind) refs.payToFind.value = String(userName || '');
			if(refs.createdById) refs.createdById.value = '';
			if(refs.createdByFind) refs.createdByFind.value = '';
		} else {
			if(refs.createdById) refs.createdById.value = String(userId || '');
			if(refs.createdByFind) refs.createdByFind.value = String(userName || '');
			if(refs.payToId) refs.payToId.value = '';
			if(refs.payToFind) refs.payToFind.value = '';
		}
		refreshAll();
	}

	refs.period.addEventListener('change', function(){
		toggleCustom();
	});
	refs.apply.addEventListener('click', function(){
		refreshAll();
	});
	refs.reset.addEventListener('click', function(){
		refs.find.value = '';
		refs.scope.value = defaultScope;
		refs.status.value = '';
		refs.period.value = '30d';
		refs.network.value = '';
		refs.type.value = '';
		if(refs.payToId) refs.payToId.value = '';
		if(refs.payToFind) refs.payToFind.value = '';
		if(refs.createdById) refs.createdById.value = '';
		if(refs.createdByFind) refs.createdByFind.value = '';
		refs.start.value = '';
		refs.end.value = '';
		toggleCustom();
		refreshAll();
	});
	refs.prev.addEventListener('click', function(){
		if(state.page <= 1) return;
		state.page -= 1;
		fetchList();
	});
	refs.next.addEventListener('click', function(){
		if(state.page >= state.lastTotalPages) return;
		state.page += 1;
		fetchList();
	});

	let t = null;
	refs.find.addEventListener('input', function(){
		if(t) clearTimeout(t);
		t = setTimeout(() => refreshAll(), 400);
	});

	attachUserSearch(refs.payToFind, refs.payToId, refs.payToSug, refs.payToClear);
	attachUserSearch(refs.createdByFind, refs.createdById, refs.createdBySug, refs.createdByClear);

	const tabKey = 'boCom2ActiveTab';
	function activateMiniTab(target){
		const tabsRoot = document.getElementById('boCom2Tabs');
		const panesRoot = document.getElementById('boCom2TabContent');
		if(!tabsRoot || !panesRoot) return;
		const desired = target || '#boCom2-records';
		const btn = tabsRoot.querySelector('button[data-bs-target="' + desired + '"]')
			|| tabsRoot.querySelector('button[data-bs-target="#boCom2-records"]');
		if(!btn) return;

		tabsRoot.querySelectorAll('button.nav-link').forEach(b => {
			b.classList.remove('active');
			b.setAttribute('aria-selected', 'false');
		});
		panesRoot.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('show', 'active'));

		btn.classList.add('active');
		btn.setAttribute('aria-selected', 'true');
		const pane = document.querySelector(desired);
		if(pane) pane.classList.add('show', 'active');

		if(window.bootstrap && window.bootstrap.Tab){
			window.bootstrap.Tab.getOrCreateInstance(btn).show();
		}
		if(desired === '#boCom2-charts'){
			renderCharts(lastDashboard, true);
		}
	}
	function ensureMiniTabActive(){
		const tabsRoot = document.getElementById('boCom2Tabs');
		const panesRoot = document.getElementById('boCom2TabContent');
		if(!tabsRoot || !panesRoot) return;
		const hasActiveBtn = !!tabsRoot.querySelector('button.nav-link.active');
		const hasActivePane = !!panesRoot.querySelector('.tab-pane.show.active');
		if(hasActiveBtn && hasActivePane) return;
		activateMiniTab(localStorage.getItem(tabKey) || '#boCom2-records');
	}

	document.querySelectorAll('#boCom2Tabs button[data-bs-toggle="tab"]').forEach(btn => {
		btn.addEventListener('shown.bs.tab', function(){
			const target = btn.getAttribute('data-bs-target');
			if(target) localStorage.setItem(tabKey, target);
		});
	});

	const themeObserver = new MutationObserver(mutations => {
		for(const m of mutations){
			if(m.type === 'attributes' && m.attributeName === 'data-bs-theme'){
				renderCharts(lastDashboard, true);
				break;
			}
		}
	});
	themeObserver.observe(document.documentElement, { attributes: true });

	activateMiniTab(localStorage.getItem(tabKey) || '#boCom2-records');
	ensureMiniTabActive();
	setTimeout(ensureMiniTabActive, 0);
	setTimeout(ensureMiniTabActive, 250);
	document.addEventListener('shown.bs.tab', function(event){
		const source = event.target;
		if (!source || (source.id !== 'commissions-tab' && !source.closest('#boCom2Tabs'))) {
			return;
		}
		setTimeout(ensureMiniTabActive, 0);
	});

	toggleCustom();
	refreshAll();

	document.getElementById('boCom2-charts-tab')?.addEventListener('shown.bs.tab', function(){
		renderCharts(lastDashboard, true);
	});
});
</script>

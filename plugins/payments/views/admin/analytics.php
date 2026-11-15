<?php if(user_can('view_payment')):?>
<?php
	$db = new \Core\Database;
	$from = trim((string)($_GET['from'] ?? ''));
	$to = trim((string)($_GET['to'] ?? ''));
	$cat = trim((string)($_GET['category'] ?? ''));

	$where = "WHERE deleted = 0";
	$params = [];
	if($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)){
		$where .= " AND DATE(date_created) >= :from";
		$params['from'] = $from;
	}
	if($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)){
		$where .= " AND DATE(date_created) <= :to";
		$params['to'] = $to;
	}
	if($cat !== ''){
		$where .= " AND category = :category";
		$params['category'] = strtoupper($cat);
	}

	$summary = (object)[
		'total_amount' => 0,
		'total_count' => 0,
		'avg_amount' => 0,
		'median_amount' => 0,
		'approved_amount' => 0,
		'pending_amount' => 0,
		'draft_amount' => 0,
		'rejected_amount' => 0,
		'approved_count' => 0,
		'pending_count' => 0,
		'draft_count' => 0,
		'rejected_count' => 0,
	];

	try{
		$rowsSum = $db->query(
			"SELECT COUNT(*) AS cnt, SUM(amount) AS total, AVG(amount) AS avg_amt FROM payments {$where}",
			$params
		);
		if(!empty($rowsSum)){
			$summary->total_count = (int)($rowsSum[0]->cnt ?? 0);
			$summary->total_amount = (float)($rowsSum[0]->total ?? 0);
			$summary->avg_amount = (float)($rowsSum[0]->avg_amt ?? 0);
		}

		$rowsStatus = $db->query(
			"SELECT
				SUM(CASE WHEN transaction_status='Approved' THEN 1 ELSE 0 END) AS approved_count,
				SUM(CASE WHEN transaction_status='Pending' THEN 1 ELSE 0 END) AS pending_count,
				SUM(CASE WHEN transaction_status='Draft' THEN 1 ELSE 0 END) AS draft_count,
				SUM(CASE WHEN transaction_status='Rejected' THEN 1 ELSE 0 END) AS rejected_count,
				SUM(CASE WHEN transaction_status='Approved' THEN amount ELSE 0 END) AS approved_amount,
				SUM(CASE WHEN transaction_status='Pending' THEN amount ELSE 0 END) AS pending_amount,
				SUM(CASE WHEN transaction_status='Draft' THEN amount ELSE 0 END) AS draft_amount,
				SUM(CASE WHEN transaction_status='Rejected' THEN amount ELSE 0 END) AS rejected_amount
			 FROM payments {$where}",
			$params
		);
		if(!empty($rowsStatus)){
			$r = $rowsStatus[0];
			$summary->approved_count = (int)($r->approved_count ?? 0);
			$summary->pending_count = (int)($r->pending_count ?? 0);
			$summary->draft_count = (int)($r->draft_count ?? 0);
			$summary->rejected_count = (int)($r->rejected_count ?? 0);
			$summary->approved_amount = (float)($r->approved_amount ?? 0);
			$summary->pending_amount = (float)($r->pending_amount ?? 0);
			$summary->draft_amount = (float)($r->draft_amount ?? 0);
			$summary->rejected_amount = (float)($r->rejected_amount ?? 0);
		}

		$amountRows = $db->query(
			"SELECT amount FROM payments {$where} ORDER BY amount ASC",
			$params
		) ?: [];
		$n = count($amountRows);
		if($n > 0){
			$mid = (int)floor(($n-1)/2);
			if($n % 2 === 1){
				$summary->median_amount = (float)($amountRows[$mid]->amount ?? 0);
			}else{
				$a = (float)($amountRows[$mid]->amount ?? 0);
				$b = (float)($amountRows[$mid+1]->amount ?? 0);
				$summary->median_amount = ($a + $b) / 2;
			}
		}
	}catch(\Throwable $e){
	}

	$byCategory = [];
	$trend = [];
	try{
		$byCategory = $db->query(
			"SELECT category, COUNT(*) AS cnt, SUM(amount) AS total
			 FROM payments {$where}
			 GROUP BY category
			 ORDER BY total DESC",
			$params
		) ?: [];
		$trend = $db->query(
			"SELECT DATE_FORMAT(date_created, '%Y-%m') AS ym, COUNT(*) AS cnt, SUM(amount) AS total
			 FROM payments {$where}
			 GROUP BY ym
			 ORDER BY ym ASC",
			$params
		) ?: [];
	}catch(\Throwable $e){
		$byCategory = [];
		$trend = [];
	}

	$catLabels = [];
	$catTotals = [];
	foreach($byCategory as $r){
		$catLabels[] = (string)($r->category ?? '');
		$catTotals[] = (float)($r->total ?? 0);
	}
	$trendLabels = [];
	$trendTotals = [];
	foreach($trend as $r){
		$trendLabels[] = (string)($r->ym ?? '');
		$trendTotals[] = (float)($r->total ?? 0);
	}

	$bestCat = !empty($byCategory) ? (string)($byCategory[0]->category ?? '') : '';
	$bestCatAmt = !empty($byCategory) ? (float)($byCategory[0]->total ?? 0) : 0;
	$riskRejectedRate = ($summary->total_count > 0) ? ((float)$summary->rejected_count / (float)$summary->total_count) : 0;
	$approvalRate = ($summary->total_count > 0) ? ((float)$summary->approved_count / (float)$summary->total_count) : 0;
	$pendingRate = ($summary->total_count > 0) ? ((float)$summary->pending_count / (float)$summary->total_count) : 0;
?>

<div class="row g-3 col-12 col-lg-11 mx-auto shadow p-3 rounded">
	<div class="d-flex justify-content-between align-items-start">
		<div>
			<h4 class="mb-1">
				<i class="fa-solid fa-chart-line"></i>
				Payments Analytics
			</h4>
			<div class="text-muted">High-level KPIs, trends, and category intelligence</div>
		</div>
		<div class="text-end">
			<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>" class="btn btn-sm btn-outline-primary">
				<i class="fa-solid fa-chevron-left"></i> Back
			</a>
		</div>
	</div>

	<form method="get" class="row g-2 mt-2">
		<div class="col-6 col-md-3">
			<label class="form-label">From</label>
			<input type="date" name="from" class="form-control" value="<?=esc($from)?>" />
		</div>
		<div class="col-6 col-md-3">
			<label class="form-label">To</label>
			<input type="date" name="to" class="form-control" value="<?=esc($to)?>" />
		</div>
		<div class="col-12 col-md-4">
			<label class="form-label">Category</label>
			<select class="form-select" name="category">
				<option value="">All</option>
				<?php
					$cats = ['GENERAL','SALARY','UTILITIES','MAINTENANCE','OPERATIONAL','LOGISTICS','RENT','MARKETING'];
					foreach($cats as $c){
						$sel = (strtoupper($cat) === $c) ? 'selected' : '';
						echo '<option value="' . esc($c) . '" ' . $sel . '>' . esc($c) . '</option>';
					}
				?>
			</select>
		</div>
		<div class="col-12 col-md-2 d-flex align-items-end">
			<button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
		</div>
	</form>

	<div class="row g-2 mt-3">
		<div class="col-12 col-md-3">
			<div class="p-3 border rounded">
				<div class="text-muted">Total Spend</div>
				<div class="h4 mb-0">GH₵ <?=number_format((float)$summary->total_amount, 2)?></div>
				<div class="text-muted small"><?=number_format((int)$summary->total_count)?> payment(s)</div>
			</div>
		</div>
		<div class="col-12 col-md-3">
			<div class="p-3 border rounded">
				<div class="text-muted">Average / Median</div>
				<div class="h5 mb-0">Avg: GH₵ <?=number_format((float)$summary->avg_amount, 2)?></div>
				<div class="text-muted">Median: GH₵ <?=number_format((float)$summary->median_amount, 2)?></div>
			</div>
		</div>
		<div class="col-12 col-md-3">
			<div class="p-3 border rounded">
				<div class="text-muted">Approval Rate</div>
				<div class="h4 mb-0"><?=number_format($approvalRate * 100, 1)?>%</div>
				<div class="text-muted small">Pending: <?=number_format($pendingRate * 100, 1)?>%</div>
			</div>
		</div>
		<div class="col-12 col-md-3">
			<div class="p-3 border rounded">
				<div class="text-muted">Top Category</div>
				<div class="h5 mb-0"><?=esc($bestCat ?: '-')?></div>
				<div class="text-muted">GH₵ <?=number_format((float)$bestCatAmt, 2)?></div>
			</div>
		</div>
	</div>

	<div class="row g-3 mt-1">
		<div class="col-12 col-lg-6">
			<div class="p-3 border rounded">
				<div class="d-flex justify-content-between align-items-center">
					<div class="fw-bold">Spend by Category</div>
					<div class="text-muted small">Decision hint: focus controls on high-spend categories</div>
				</div>
				<canvas id="catChart" height="140"></canvas>
			</div>
		</div>
		<div class="col-12 col-lg-6">
			<div class="p-3 border rounded">
				<div class="d-flex justify-content-between align-items-center">
					<div class="fw-bold">Spend Trend (Monthly)</div>
					<div class="text-muted small">Decision hint: detect growing cost centers early</div>
				</div>
				<canvas id="trendChart" height="140"></canvas>
			</div>
		</div>
	</div>

	<div class="row g-3 mt-1">
		<div class="col-12 col-lg-6">
			<div class="p-3 border rounded">
				<div class="fw-bold">Status Mix (Count)</div>
				<canvas id="statusChart" height="140"></canvas>
			</div>
		</div>
		<div class="col-12 col-lg-6">
			<div class="p-3 border rounded">
				<div class="fw-bold">Intelligent Insights</div>
				<div class="mt-2">
					<div class="text-muted">Risk indicator</div>
					<div class="fw-bold">Rejected rate: <?=number_format($riskRejectedRate * 100, 1)?>%</div>
					<div class="text-muted mt-2">Actionable suggestion</div>
					<div class="fw-bold">
						<?php if($riskRejectedRate >= 0.2): ?>
							Improve approval workflow (high rejection rate)
						<?php elseif($pendingRate >= 0.3): ?>
							Reduce pending backlog (many payments waiting)
						<?php elseif($bestCat !== ''): ?>
							Review controls for <?=esc($bestCat)?> spending (highest category)
						<?php else: ?>
							No specific risk detected
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function(){
	var catLabels = <?=json_encode($catLabels)?>;
	var catTotals = <?=json_encode($catTotals)?>;
	var trendLabels = <?=json_encode($trendLabels)?>;
	var trendTotals = <?=json_encode($trendTotals)?>;

	var statusLabels = ['Approved','Pending','Draft','Rejected'];
	var statusCounts = [
		<?= (int)$summary->approved_count ?>,
		<?= (int)$summary->pending_count ?>,
		<?= (int)$summary->draft_count ?>,
		<?= (int)$summary->rejected_count ?>
	];

	function moneyTick(value){
		try{ return 'GH₵ ' + Number(value||0).toLocaleString(undefined,{minimumFractionDigits:0, maximumFractionDigits:0}); }catch(e){ return value; }
	}

	var elCat = document.getElementById('catChart');
	if(elCat){
		new Chart(elCat, {
			type: 'bar',
			data: {
				labels: catLabels,
				datasets: [{
					label: 'Total',
					data: catTotals,
					backgroundColor: 'rgba(13,110,253,0.35)',
					borderColor: 'rgba(13,110,253,0.9)',
					borderWidth: 1
				}]
			},
			options: {
				responsive: true,
				plugins: { legend: { display: false } },
				scales: {
					y: { ticks: { callback: moneyTick } }
				}
			}
		});
	}

	var elTrend = document.getElementById('trendChart');
	if(elTrend){
		new Chart(elTrend, {
			type: 'line',
			data: {
				labels: trendLabels,
				datasets: [{
					label: 'Monthly Total',
					data: trendTotals,
					fill: true,
					backgroundColor: 'rgba(25,135,84,0.12)',
					borderColor: 'rgba(25,135,84,0.9)',
					tension: 0.25,
					pointRadius: 3
				}]
			},
			options: {
				responsive: true,
				plugins: { legend: { display: false } },
				scales: {
					y: { ticks: { callback: moneyTick } }
				}
			}
		});
	}

	var elStatus = document.getElementById('statusChart');
	if(elStatus){
		new Chart(elStatus, {
			type: 'doughnut',
			data: {
				labels: statusLabels,
				datasets: [{
					data: statusCounts,
					backgroundColor: [
						'rgba(25,135,84,0.85)',
						'rgba(255,193,7,0.85)',
						'rgba(108,117,125,0.85)',
						'rgba(220,53,69,0.85)'
					]
				}]
			},
			options: {
				responsive: true,
				plugins: { legend: { position: 'bottom' } }
			}
		});
	}
})();
</script>

<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>

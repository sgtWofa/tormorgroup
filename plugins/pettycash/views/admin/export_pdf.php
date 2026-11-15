<?php
require_once ROOTPATH . 'assets/vendor/autoload.php';

$rows = $exportRows ?? [];
$summary = $exportSummary ?? ['total_count' => 0, 'total_amount' => 0];
$filters = $filters ?? [];

$renderFilterValue = static function ($value): string {
	$value = trim((string)$value);
	return $value !== '' ? $value : 'All';
};

$html = (function () use ($rows, $summary, $filters, $renderFilterValue) {
	ob_start();
	?>
	<!DOCTYPE html>
	<html>
	<head>
		<meta charset="utf-8">
		<title>Petty Cash Report</title>
		<style>
			body { font-family: Tahoma, sans-serif; font-size: 12px; color: #111827; }
			h1, h2, h3, p { margin: 0; }
			.header { text-align: center; margin-bottom: 18px; }
			.header h2 { margin-bottom: 4px; }
			.meta { width: 100%; margin-bottom: 14px; border-collapse: collapse; }
			.meta td { padding: 6px 8px; border: 1px solid #d1d5db; }
			.summary { margin-bottom: 14px; }
			.summary td { padding: 8px; border: 1px solid #d1d5db; }
			table.report { width: 100%; border-collapse: collapse; table-layout: fixed; }
			table.report th, table.report td { border: 1px solid #d1d5db; padding: 8px 6px; vertical-align: top; }
			table.report th { background: #facc15; }
			.small { color: #6b7280; font-size: 10px; }
			.badge { display: inline-block; padding: 4px 8px; border-radius: 999px; font-size: 10px; font-weight: bold; }
			.pending { background: #facc15; color: #111827; }
			.acknowledged { background: #38bdf8; color: #111827; }
			.completed { background: #22c55e; color: #ffffff; }
			.rejected { background: #ef4444; color: #ffffff; }
			.text-end { text-align: right; }
			.text-center { text-align: center; }
		</style>
	</head>
	<body>
		<div class="header">
			<h2><?=esc(APP_NAME)?></h2>
			<h3>Petty Cash Transactions Report</h3>
			<p class="small">Generated on <?=date('Y-m-d H:i:s')?></p>
		</div>

		<table class="meta">
			<tr>
				<td><strong>Search</strong><br><?=esc($renderFilterValue($filters['q'] ?? ''))?></td>
				<td><strong>Status</strong><br><?=esc($renderFilterValue(pettycash_status_label($filters['status'] ?? '') === '' ? '' : pettycash_status_label($filters['status'] ?? '')))?></td>
				<td><strong>Category</strong><br><?=esc($renderFilterValue($filters['category'] ?? ''))?></td>
				<td><strong>Date Range</strong><br><?=esc($renderFilterValue(($filters['date_from'] ?? '') . (($filters['date_from'] ?? '') !== '' || ($filters['date_to'] ?? '') !== '' ? ' to ' : '') . ($filters['date_to'] ?? '')))?></td>
			</tr>
		</table>

		<table class="meta summary">
			<tr>
				<td><strong>Total Transactions</strong><br><?=number_format((int)($summary['total_count'] ?? 0))?></td>
				<td><strong>Total Budget</strong><br><?=number_format((float)($summary['total_amount'] ?? 0), 2)?></td>
			</tr>
		</table>

		<table class="report">
			<thead>
				<tr>
					<th style="width: 4%;">#</th>
					<th style="width: 12%;">Reference</th>
					<th style="width: 10%;">Date</th>
					<th style="width: 10%;">Category</th>
					<th style="width: 22%;">Title / Description</th>
					<th style="width: 12%;">Budget</th>
					<th style="width: 10%;">Status</th>
					<th style="width: 10%;">Manager</th>
					<th style="width: 10%;">Assigned</th>
				</tr>
			</thead>
			<tbody>
				<?php if (!empty($rows)): ?>
					<?php foreach ($rows as $index => $reportRow): ?>
						<?php
							$statusKey = strtolower((string)($reportRow->status ?? 'pending_ack'));
							$statusClass = in_array($statusKey, ['pending_ack', 'acknowledged', 'completed', 'rejected'], true) ? $statusKey : '';
							$manager = trim((string)($reportRow->creator_first_name ?? '') . ' ' . (string)($reportRow->creator_last_name ?? ''));
							$assigned = trim((string)($reportRow->assignee_first_name ?? '') . ' ' . (string)($reportRow->assignee_last_name ?? ''));
						?>
						<tr>
							<td class="text-center"><?=($index + 1)?></td>
							<td><?=esc($reportRow->reference ?? '')?></td>
							<td><?=esc(get_date_time($reportRow->date_created ?? ''))?></td>
							<td><?=esc($reportRow->category ?? '')?></td>
							<td>
								<div><strong><?=esc($reportRow->title ?? '')?></strong></div>
								<div class="small"><?=esc($reportRow->description ?? '')?></div>
							</td>
							<td><?=esc(pettycash_format_amount((float)($reportRow->budget_amount ?? 0), (string)($reportRow->currency ?? 'GHS')))?></td>
							<td><span class="badge <?=$statusClass?>"><?=esc(pettycash_status_label($reportRow->status ?? ''))?></span></td>
							<td><?=esc($manager !== '' ? $manager : 'N/A')?></td>
							<td><?=esc($assigned !== '' ? $assigned : 'N/A')?></td>
						</tr>
					<?php endforeach; ?>
				<?php else: ?>
					<tr>
						<td colspan="9" class="text-center">No petty cash transactions found for the selected filters.</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</body>
	</html>
	<?php
	return ob_get_clean();
})();

try {
	if (ob_get_length()) {
		@ob_clean();
	}

	$mpdf = new \Mpdf\Mpdf([
		'mode' => 'utf-8',
		'format' => 'A4-L',
		'margin_top' => 10,
		'margin_bottom' => 12,
		'margin_header' => 8,
		'margin_footer' => 8,
		'tempDir' => ROOTPATH . 'assets/vendor/mpdf/mpdf/tmp',
		'simpleTables' => true,
	]);

	$mpdf->SetHTMLFooter('<div style="text-align:center;font-family:Tahoma;font-size:10px;">Page {PAGENO} of {nbpg}</div>');
	header('Content-Type: application/pdf');
	header('Content-Disposition: inline; filename="PettyCash_Report_' . date('Ymd_His') . '.pdf"');
	$mpdf->WriteHTML($html);
	$mpdf->Output('PettyCash_Report_' . date('Ymd_His') . '.pdf', \Mpdf\Output\Destination::INLINE);
} catch (\Throwable $e) {
	echo '<div class="alert alert-danger text-center">Unable to generate petty cash PDF: ' . esc($e->getMessage()) . '</div>';
}

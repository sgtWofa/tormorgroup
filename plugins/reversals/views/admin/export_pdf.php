<?php
require_once ROOTPATH . 'assets/vendor/autoload.php';

$rows = $exportRows ?? [];
$summary = $exportSummary ?? ['total_count' => 0, 'total_amount' => 0];
$filters = $exportFilterInput ?? [];

$renderFilterValue = static function ($value): string {
	$value = trim((string)$value);
	return $value !== '' ? $value : 'All';
};

$typeOptions = function_exists('reversals_transaction_type_options') ? reversals_transaction_type_options() : [];

$html = (function () use ($rows, $summary, $filters, $renderFilterValue, $typeOptions) {
	ob_start();
	?>
	<!DOCTYPE html>
	<html>
	<head>
		<meta charset="utf-8">
		<title>Reversals Report</title>
		<style>
			body { font-family: Tahoma, sans-serif; font-size: 12px; color: #111827; }
			h1, h2, h3, p { margin: 0; }
			.header { text-align: center; margin-bottom: 18px; }
			.header h2 { margin-bottom: 4px; }
			.meta { width: 100%; margin-bottom: 14px; border-collapse: collapse; }
			.meta td { padding: 6px 8px; border: 1px solid #d1d5db; }
			.summary td { padding: 8px; border: 1px solid #d1d5db; }
			table.report { width: 100%; border-collapse: collapse; table-layout: fixed; }
			table.report th, table.report td { border: 1px solid #d1d5db; padding: 8px 6px; vertical-align: top; }
			table.report th { background: #facc15; }
			.small { color: #6b7280; font-size: 10px; }
			.badge { display: inline-block; padding: 4px 8px; border-radius: 999px; font-size: 10px; font-weight: bold; }
			.text-end { text-align: right; }
			.text-center { text-align: center; }
		</style>
	</head>
	<body>
		<div class="header">
			<h2><?=esc(APP_NAME)?></h2>
			<h3>Reversals Report</h3>
			<p class="small">Generated on <?=date('Y-m-d H:i:s')?></p>
		</div>

		<table class="meta">
			<tr>
				<td><strong>Search</strong><br><?=esc($renderFilterValue($filters['find'] ?? ''))?></td>
				<td><strong>Status</strong><br><?=esc($renderFilterValue($filters['status'] ?? ''))?></td>
				<td><strong>Provider</strong><br><?=esc($renderFilterValue($filters['network'] ?? ''))?></td>
				<td><strong>Date Range</strong><br><?=esc($renderFilterValue(($filters['date_from'] ?? '') . ((($filters['date_from'] ?? '') !== '' || ($filters['date_to'] ?? '') !== '') ? ' to ' : '') . ($filters['date_to'] ?? '')))?></td>
			</tr>
		</table>

		<table class="meta summary">
			<tr>
				<td><strong>Total Reversals</strong><br><?=number_format((int)($summary['total_count'] ?? 0))?></td>
				<td><strong>Total Amount</strong><br><?=number_format((float)($summary['total_amount'] ?? 0), 2)?></td>
			</tr>
		</table>

		<table class="report">
			<thead>
				<tr>
					<th style="width: 4%;">#</th>
					<th style="width: 13%;">Source</th>
					<th style="width: 10%;">Provider</th>
					<th style="width: 12%;">Service</th>
					<th style="width: 13%;">Sender</th>
					<th style="width: 13%;">Receiver</th>
					<th style="width: 10%;">Amount</th>
					<th style="width: 11%;">Status</th>
					<th style="width: 14%;">Created / Assigned</th>
				</tr>
			</thead>
			<tbody>
				<?php if (!empty($rows)): ?>
					<?php foreach ($rows as $index => $reportRow): ?>
						<?php
							$sourceType = (string)($reportRow->source_transaction_type ?? '');
							$sourceLabel = $typeOptions[$sourceType] ?? $sourceType;
							$createdBy = trim((string)($reportRow->user_first_name ?? '') . ' ' . (string)($reportRow->user_last_name ?? ''));
							$assignedTo = trim((string)($reportRow->assigned_first_name ?? '') . ' ' . (string)($reportRow->assigned_last_name ?? ''));
						?>
						<tr>
							<td class="text-center"><?=($index + 1)?></td>
							<td>
								<div><strong><?=esc($sourceLabel !== '' ? $sourceLabel : 'N/A')?></strong></div>
								<div class="small">ID: <?=esc($reportRow->source_transaction_id ?? $reportRow->id ?? '')?></div>
							</td>
							<td><?=esc($reportRow->network ?? '')?></td>
							<td><?=esc($reportRow->service_type ?? '')?></td>
							<td><?=esc($reportRow->sender_details ?? '')?></td>
							<td><?=esc($reportRow->receiver_details ?? '')?></td>
							<td class="text-end"><?=number_format((float)($reportRow->amount ?? 0), 2)?></td>
							<td><?=esc($reportRow->transaction_status ?? '')?></td>
							<td>
								<div><strong>Created:</strong> <?=esc($createdBy !== '' ? $createdBy : 'N/A')?></div>
								<div class="small"><strong>Assigned:</strong> <?=esc($assignedTo !== '' ? $assignedTo : 'N/A')?></div>
								<div class="small"><?=esc(get_date_time($reportRow->date_created ?? ''))?></div>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else: ?>
					<tr>
						<td colspan="9" class="text-center">No reversals found for the selected filters.</td>
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
	header('Content-Disposition: inline; filename="Reversals_Report_' . date('Ymd_His') . '.pdf"');
	$mpdf->WriteHTML($html);
	$mpdf->Output('Reversals_Report_' . date('Ymd_His') . '.pdf', \Mpdf\Output\Destination::INLINE);
} catch (\Throwable $e) {
	echo '<div class="alert alert-danger text-center">Unable to generate reversals PDF: ' . esc($e->getMessage()) . '</div>';
}

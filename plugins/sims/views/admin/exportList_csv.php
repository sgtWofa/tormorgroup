<?php

// Stream CSV export (no admin layout)

try {
	while (ob_get_level()) {
		ob_end_clean();
	}
} catch (Throwable $e) {}

$find = trim($_GET['find'] ?? '');
$rows = $rows ?? [];
if (!is_array($rows)) {
	$rows = [];
}

$total_amount = (float)($total_amount ?? 0);

$filename = 'sims_report_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

fputcsv($output, ['SIMS Report']);
if ($find !== '') {
	fputcsv($output, ['Filter', $find]);
}
fputcsv($output, ['Generated', date('Y-m-d H:i:s')]);
fputcsv($output, []);

fputcsv($output, [
	'#',
	'ID',
	'Network',
	'Status',
	'Serial',
	'PUK',
	'Amount',
	'Commission',
	'Assigned (Name)',
	'Buyer Phone',
	'Date Created',
	'Date Updated',
]);

$i = 0;
foreach ($rows as $row) {
	$i++;
	$assignedName = trim((string)($row->assigned_first_name ?? '') . ' ' . (string)($row->assigned_last_name ?? ''));
	fputcsv($output, [
		$i,
		$row->id ?? '',
		$row->network ?? '',
		$row->transaction_status ?? '',
		$row->serial_number ?? '',
		$row->puk ?? '',
		number_format((float)($row->amount ?? 0), 2, '.', ''),
		number_format((float)($row->commission ?? 0), 2, '.', ''),
		$assignedName,
		$row->buyer_phone ?? '',
		$row->date_created ?? '',
		$row->date_updated ?? '',
	]);
}

fputcsv($output, []);
fputcsv($output, ['TOTAL AMOUNT', number_format($total_amount, 2, '.', '')]);

fclose($output);
exit;

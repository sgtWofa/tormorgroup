<?php

$vars = get_value();
$admin_route = $vars['admin_route'] ?? 'admin';
$plugin_route = $vars['plugin_route'] ?? 'cashpickup';

if (!user_can('view_cashpickup')) {
	http_response_code(403);
	echo 'Access denied';
	exit;
}

require_once plugin_path('helpers/customer-ledger.php');
require_once plugin_path('helpers/crm-helper.php');
try {
	\CashpickupLedger\ensure_cashpickup_customer_schema($db);
	\CashpickupLedger\ensure_cashpickup_columns($db);
} catch (\Throwable $e) {
	// ignore
}

$autoload = __DIR__ . '/../../../assets/vendor/autoload.php';
if (file_exists($autoload)) {
	require_once $autoload;
}

$resource = strtolower(trim((string)($_GET['resource'] ?? 'list')));
$format = strtolower(trim((string)($_GET['format'] ?? 'xlsx')));

$db = new \Core\Database;
\CashpickupLedger\ensure_cashpickup_credit_schema($db);

$escape = function($v): string {
	return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
};

$getOrg = function() use ($db): array {
	$org = [
		'name' => defined('APP_NAME') ? (string)APP_NAME : 'Organization',
		'title' => defined('APP_DESCRIPTION') ? (string)APP_DESCRIPTION : '',
		'phone' => '',
		'alt_phone' => '',
		'sim' => '',
		'logo' => defined('APP_LOGO2') ? (string)APP_LOGO2 : (defined('APP_LOGO') ? (string)APP_LOGO : ''),
	];
	try {
		$row = $db->query(
			"SELECT company_name, title, admin_contact, ceo_contact, company_sim, image
			 FROM settings
			 ORDER BY id DESC
			 LIMIT 1"
		);
		if (!empty($row)) {
			$s = $row[0];
			if (trim((string)($s->company_name ?? '')) !== '') $org['name'] = (string)$s->company_name;
			if (trim((string)($s->title ?? '')) !== '') $org['title'] = (string)$s->title;
			$org['phone'] = trim((string)($s->admin_contact ?? ''));
			$org['alt_phone'] = trim((string)($s->ceo_contact ?? ''));
			$org['sim'] = trim((string)($s->company_sim ?? ''));
			$img = trim((string)($s->image ?? ''));
			if ($img !== '') {
				if (function_exists('get_image')) {
					$org['logo'] = (string)get_image($img);
				} else {
					$org['logo'] = $img;
				}
			}
		}
	} catch (\Throwable $e) {
		// ignore
	}
	return $org;
};

$orgHeaderHtml = function(array $org) use ($escape): string {
	$lines = [];
	if (trim((string)($org['title'] ?? '')) !== '') $lines[] = $escape($org['title']);
	$contacts = [];
	if (trim((string)($org['phone'] ?? '')) !== '') $contacts[] = 'Office: ' . $escape($org['phone']);
	if (trim((string)($org['alt_phone'] ?? '')) !== '') $contacts[] = 'CEO: ' . $escape($org['alt_phone']);
	if (!empty($contacts)) $lines[] = implode(' | ', $contacts);
	if (trim((string)($org['sim'] ?? '')) !== '') $lines[] = 'Sim: ' . $escape($org['sim']);

	$logo = trim((string)($org['logo'] ?? ''));
	$logoHtml = $logo !== '' ? '<img src="' . $escape($logo) . '" style="height:48px;max-width:96px;object-fit:contain;">' : '';

	$out = '<table width="100%" cellspacing="0" cellpadding="0" style="border-bottom:1px solid #ddd;padding-bottom:8px;margin-bottom:10px">';
	$out .= '<tr>';
	$out .= '<td style="width:110px;vertical-align:middle">' . $logoHtml . '</td>';
	$out .= '<td style="vertical-align:middle">';
	$out .= '<div style="font-size:16px;font-weight:bold">' . $escape((string)($org['name'] ?? '')) . '</div>';
	foreach ($lines as $ln) {
		$out .= '<div style="font-size:11px;color:#444">' . $ln . '</div>';
	}
	$out .= '</td>';
	$out .= '</tr>';
	$out .= '</table>';
	return $out;
};

$applyOrgToSheet = function(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $org, int $startRow = 1): int {
	$row = $startRow;
	$sheet->setCellValue('A' . $row, (string)($org['name'] ?? ''));
	$sheet->mergeCells('A' . $row . ':E' . $row);
	$sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
	$row++;
	if (trim((string)($org['title'] ?? '')) !== '') {
		$sheet->setCellValue('A' . $row, (string)$org['title']);
		$sheet->mergeCells('A' . $row . ':E' . $row);
		$row++;
	}
	$contacts = [];
	if (trim((string)($org['phone'] ?? '')) !== '') $contacts[] = 'Office: ' . (string)$org['phone'];
	if (trim((string)($org['alt_phone'] ?? '')) !== '') $contacts[] = 'CEO: ' . (string)$org['alt_phone'];
	if (!empty($contacts)) {
		$sheet->setCellValue('A' . $row, implode(' | ', $contacts));
		$sheet->mergeCells('A' . $row . ':E' . $row);
		$row++;
	}
	if (trim((string)($org['sim'] ?? '')) !== '') {
		$sheet->setCellValue('A' . $row, 'Sim: ' . (string)$org['sim']);
		$sheet->mergeCells('A' . $row . ':E' . $row);
		$row++;
	}
	$row++;
	return $row;
};

$sendCsv = function(string $filename, array $headers, array $rows) {
	while (ob_get_level()) {
		ob_end_clean();
	}
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	$out = fopen('php://output', 'w');
	fputcsv($out, $headers);
	foreach ($rows as $r) {
		fputcsv($out, $r);
	}
	fclose($out);
	exit;
};

$sendXlsx = function(string $filename, string $sheetName, array $headers, array $rows) {
	if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
		throw new \Exception('PhpSpreadsheet library is missing.');
	}
	while (ob_get_level()) {
		ob_end_clean();
	}
	$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
	$sheet = $spreadsheet->getActiveSheet();
	$sheet->setTitle($sheetName);
	$sheet->fromArray($headers, null, 'A1');
	if (!empty($rows)) {
		$sheet->fromArray($rows, null, 'A2');
	}
	$lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
	$sheet->getStyle('A1:' . $lastCol . '1')->getFont()->setBold(true);
	$sheet->freezePane('A2');
	for ($i = 1; $i <= count($headers); $i++) {
		$col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
		$sheet->getColumnDimension($col)->setAutoSize(true);
	}

	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	header('Cache-Control: max-age=0');
	$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
	$writer->save('php://output');
	exit;
};

$sendPdf = function(string $filename, string $title, string $html) {
	if (!class_exists('Mpdf\\Mpdf')) {
		throw new \Exception('mPDF library is missing.');
	}
	while (ob_get_level()) {
		ob_end_clean();
	}
	$mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir()]);
	$mpdf->SetTitle($title);
	$mpdf->WriteHTML($html);
	header('Content-Type: application/pdf');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	$mpdf->Output($filename, 'D');
	exit;
};

$listFetch = function() use ($db): array {
	$session = get_session();
	$user_id = (int)($session->id ?? 0);
	$isManagerOrAdmin = (user_can('is_manager') || user_can('is_admin'));
	$canViewAll = $isManagerOrAdmin;

	$params = [];
	$conditions = ['m.deleted = 0', "LOWER(m.transaction_side) = 'receive'"];
	$paidGrossExpr = "(COALESCE(amount_paid_out,0) + COALESCE(pickup_charge,0))";

	$find = trim((string)($_GET['find'] ?? ''));
	$dateFrom = trim((string)($_GET['date_from'] ?? ''));
	$dateTo = trim((string)($_GET['date_to'] ?? ''));
	$assignedFilter = trim((string)($_GET['assigned_user_id'] ?? ''));
	$paidFilter = strtolower(trim((string)($_GET['paid'] ?? '')));

	if (!$canViewAll && $user_id > 0) {
		$conditions[] = '(m.assigned_user_id = :viewer_user_id OR m.created_by = :viewer_user_id)';
		$params['viewer_user_id'] = $user_id;
	}

	if ($isManagerOrAdmin) {
		$conditions[] = "LOWER(COALESCE(m.source,'')) IN ('cash','mtn','telecel','at','bank')";
	}
	if ($dateFrom !== '') {
		$conditions[] = 'DATE(m.date_created) >= :date_from';
		$params['date_from'] = $dateFrom;
	}
	if ($dateTo !== '') {
		$conditions[] = 'DATE(m.date_created) <= :date_to';
		$params['date_to'] = $dateTo;
	}
	if ($assignedFilter !== '') {
		$conditions[] = 'm.assigned_user_id = :assigned_user_id';
		$params['assigned_user_id'] = (int)$assignedFilter;
	}
	if ($find !== '') {
		$conditions[] = '(m.tracking_id LIKE :find OR m.phone_number LIKE :find OR m.sender_details LIKE :find)';
		$params['find'] = '%' . $find . '%';
	}

	$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
	$paidWhere = '';
	if (in_array($paidFilter, ['paid', 'partial', 'unpaid'], true)) {
		if ($paidFilter === 'paid') {
			$paidWhere = " AND (COALESCE(paid.paid_requested,0) >= (COALESCE(m.amount_involved,0) - 0.00001))";
		} elseif ($paidFilter === 'partial') {
			$paidWhere = " AND (COALESCE(paid.paid_requested,0) > 0 AND COALESCE(paid.paid_requested,0) < (COALESCE(m.amount_involved,0) - 0.00001))";
		} else {
			$paidWhere = " AND (COALESCE(paid.paid_requested,0) <= 0)";
		}
	}

	$hasTransferLedger = false;
	try {
		$hasTransferLedger = $db->table_exists(['cashpickup_transfers']);
	} catch (\Throwable $e) {
		$hasTransferLedger = false;
	}
	$trSelect = $hasTransferLedger ? "COALESCE(tr.transferred,0)" : "0";
	$trJoin = $hasTransferLedger ? "\n\t\tLEFT JOIN (\n\t\t\tSELECT receive_id, COALESCE(SUM(amount),0) AS transferred\n\t\t\tFROM cashpickup_transfers\n\t\t\tGROUP BY receive_id\n\t\t) AS tr ON tr.receive_id = m.id" : "";

	$sql = "SELECT m.*, au.first_name AS assigned_first_name, au.last_name AS assigned_last_name,
		COALESCE(paid.paid_requested,0) AS paid_requested,
		COALESCE(paid.paid_out,0) AS paid_out,
		COALESCE(paid.parts,0) AS payout_parts,
		{$trSelect} AS transferred_to_agents
		FROM cashpickups AS m
		LEFT JOIN users AS au ON m.assigned_user_id = au.id
		{$trJoin}
		LEFT JOIN (
			SELECT receive_id,
				COALESCE(SUM({$paidGrossExpr}),0) AS paid_requested,
				COALESCE(SUM(amount_paid_out),0) AS paid_out,
				COUNT(*) AS parts
			FROM cashpickups
			WHERE (deleted IS NULL OR deleted = 0)
			AND LOWER(transaction_side) = 'pay'
			AND (
				approval_status IS NULL
				OR approval_status = ''
				OR LOWER(approval_status) IN ('approved','pending')
			)
			AND transaction_status = 'Approved'
			AND (confirmed_by IS NOT NULL AND confirmed_by != '')
			AND COALESCE(amount_paid_out,0) > 0
			GROUP BY receive_id
		) AS paid ON paid.receive_id = m.id
		{$where}{$paidWhere}
		ORDER BY m.id DESC
		LIMIT 5000";

	$rows = $db->query($sql, $params) ?? [];
	return $rows;
};

$singleReceiveFetch = function(int $id) use ($db): array {
	$row = $db->query(
		"SELECT m.*, au.first_name AS assigned_first_name, au.last_name AS assigned_last_name
		 FROM cashpickups m
		 LEFT JOIN users au ON au.id = m.assigned_user_id
		 WHERE (m.deleted IS NULL OR m.deleted = 0)
		 AND m.id = :id
		 LIMIT 1",
		['id' => $id]
	);
	$receive = !empty($row) ? $row[0] : null;
	$payouts = [];
	if ($receive) {
		$payouts = $db->query(
			"SELECT id, date_created, amount_requested, pickup_charge, amount_paid_out, payment_method, payout_channel, confirmed_by, approval_status
			 FROM cashpickups
			 WHERE (deleted IS NULL OR deleted = 0)
			 AND LOWER(transaction_side) = 'pay'
			 AND receive_id = :rid
			 ORDER BY date_created DESC, id DESC",
			['rid' => $id]
		) ?? [];
	}
	return [$receive, $payouts];
};

$customersFetch = function() use ($db): array {
	$params = [];
	$conditions = ['c.deleted = 0'];
	$find = trim((string)($_GET['find'] ?? ''));
	if ($find !== '') {
		$conditions[] = '(c.full_name LIKE :find OR c.phone_number LIKE :find OR c.id_number LIKE :find)';
		$params['find'] = '%' . $find . '%';
	}
	$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

	$activePaidOutExpr = "(CASE
		WHEN LOWER(transaction_side) = 'pay'
			AND LOWER(TRIM(COALESCE(credit_mode,''))) <> 'credit'
			AND LOWER(COALESCE(transaction_status,'')) = 'approved'
			AND (confirmed_by IS NOT NULL AND confirmed_by != '')
			AND COALESCE(amount_paid_out,0) > 0
			AND (
				approval_status IS NULL
				OR approval_status = ''
				OR LOWER(approval_status) IN ('approved','pending')
			)
		THEN (COALESCE(amount_paid_out,0) + COALESCE(pickup_charge,0))
		ELSE 0
	END)";

	$sql = "SELECT c.*,
		        COALESCE(stats.tx_count,0) AS tx_count,
		        COALESCE(stats.total_received,0) AS total_received,
		        COALESCE(stats.total_paid_out,0) AS total_paid_out,
	        COALESCE(stats.credit_balance_debit,0) AS credit_balance_debit,
		        stats.last_tx
		FROM cashpickup_customers c
		LEFT JOIN (
			SELECT customer_id,
				COUNT(*) AS tx_count,
			       COALESCE(SUM(CASE
			       	WHEN LOWER(transaction_side) = 'receive'
			       		AND LOWER(TRIM(COALESCE(credit_mode,''))) <> 'credit'
			       	THEN amount_involved
			       	ELSE 0
			       END),0) AS total_received,
			       COALESCE(SUM({$activePaidOutExpr}),0) AS total_paid_out,
			       COALESCE(SUM(CASE
			       	WHEN LOWER(transaction_side) = 'receive'
			       		AND LOWER(TRIM(COALESCE(credit_mode,''))) = 'credit'
			       		AND LOWER(TRIM(COALESCE(transaction_status,''))) IN ('approved','pending payout')
			       		AND (approval_status IS NULL OR approval_status = '' OR LOWER(TRIM(approval_status)) IN ('approved','pending'))
			       	THEN amount_involved
			       	ELSE 0
			       END),0) AS credit_balance_debit,
				MAX(date_created) AS last_tx
			FROM cashpickups
			WHERE deleted = 0
			GROUP BY customer_id
		) stats ON stats.customer_id = c.id
		{$where}
		ORDER BY (COALESCE(c.full_name,'') = '') ASC, c.full_name ASC, c.id DESC
		LIMIT 5000";
	return $db->query($sql, $params) ?? [];
};

$customerStatementFetch = function(int $customerId, array $filters = []) use ($db): array {
	return \CashpickupLedger\fetch_customer_workstation_activity($db, $customerId, $filters, 0, 0);
};

$generalLedgerFetch = function() use ($db): array {
	return \CashpickupLedger\fetch_general_ledger_page(
		$db,
		[
			'find' => trim((string)($_GET['find'] ?? '')),
			'order' => 'desc',
		],
		0,
		0
	);
};

$generalLedgerMetricsFetch = function() use ($db): array {
	return \CashpickupLedger\fetch_general_ledger_metrics(
		$db,
		[
			'find' => trim((string)($_GET['find'] ?? '')),
		]
	);
};

try {
	$stamp = date('Y-m-d_H-i-s');

	if ($resource === 'list') {
		$rows = $listFetch();
		$headers = ['ID','Date','Source','Tracking','Customer','Phone','Assigned To','Approval','Amount Involved','Paid Requested','Paid Out','Remaining'];
		$outRows = [];
		foreach ($rows as $r) {
			$paidRequested = (float)($r->paid_requested ?? 0);
			$amount = (float)($r->amount_involved ?? 0);
			$remaining = max(0, $amount - $paidRequested);
			$assignedName = trim(((string)($r->assigned_first_name ?? '')).' '.((string)($r->assigned_last_name ?? '')));
			$outRows[] = [
				(string)($r->id ?? ''),
				(string)($r->date_created ?? $r->date ?? ''),
				(string)($r->source ?? ''),
				(string)($r->tracking_id ?? ''),
				(string)($r->sender_details ?? ''),
				(string)($r->phone_number ?? ''),
				$assignedName,
				(string)($r->approval_status ?? ''),
				number_format($amount, 2, '.', ''),
				number_format($paidRequested, 2, '.', ''),
				number_format((float)($r->paid_out ?? 0), 2, '.', ''),
				number_format((float)$remaining, 2, '.', ''),
			];
		}

		if ($format === 'csv') {
			$sendCsv('cashpickup_list_' . $stamp . '.csv', $headers, $outRows);
		} elseif ($format === 'pdf') {
			$org = $getOrg();
			$rowsHtml = '';
			foreach ($outRows as $rr) {
				$rowsHtml .= '<tr>';
				foreach ($rr as $c) {
					$rowsHtml .= '<td>' . $escape($c) . '</td>';
				}
				$rowsHtml .= '</tr>';
			}
			$html = $orgHeaderHtml($org);
			$html .= '<h2 style="margin:0">Cashpickup Export (List)</h2>';
			$html .= '<div style="font-size:11px;color:#666">Generated: ' . $escape(date('Y-m-d H:i:s')) . '</div>';
			$html .= '<table width="100%" border="1" cellspacing="0" cellpadding="4" style="border-collapse:collapse;margin-top:10px;font-size:10px">';
			$html .= '<thead><tr>';
			foreach ($headers as $h) { $html .= '<th style="background:#f2f2f2">' . $escape($h) . '</th>'; }
			$html .= '</tr></thead><tbody>' . $rowsHtml . '</tbody></table>';
			$sendPdf('cashpickup_list_' . $stamp . '.pdf', 'Cashpickup List Export', $html);
		} else {
			if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
				throw new \Exception('PhpSpreadsheet library is missing.');
			}
			while (ob_get_level()) {
				ob_end_clean();
			}
			$org = $getOrg();
			$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
			$sheet = $spreadsheet->getActiveSheet();
			$sheet->setTitle('Cashpickup');
			$startRow = $applyOrgToSheet($sheet, $org, 1);
			$sheet->fromArray($headers, null, 'A' . $startRow);
			if (!empty($outRows)) {
				$sheet->fromArray($outRows, null, 'A' . ($startRow + 1));
			}
			$lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
			$sheet->getStyle('A' . $startRow . ':' . $lastCol . $startRow)->getFont()->setBold(true);
			$sheet->freezePane('A' . ($startRow + 1));
			for ($i = 1; $i <= count($headers); $i++) {
				$col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
				$sheet->getColumnDimension($col)->setAutoSize(true);
			}
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment; filename="cashpickup_list_' . $stamp . '.xlsx"');
			header('Cache-Control: max-age=0');
			$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
			$writer->save('php://output');
			exit;
		}
	}

	if ($resource === 'crm') {
		$session = get_session();
		$sessionUserId = (int)($session->id ?? 0);
		$isNonPrivileged = !(user_can('is_admin') || user_can('is_manager'));
		$filters = [
			'find' => trim((string)($_GET['find'] ?? '')),
			'date_from' => trim((string)($_GET['date_from'] ?? '')),
			'date_to' => trim((string)($_GET['date_to'] ?? '')),
			'service' => trim((string)($_GET['service'] ?? '')),
			'agent_id' => trim((string)($_GET['agent_id'] ?? '')),
		];
		$rows = \CashpickupCRM\fetchTransactions($db, $filters, 2000, $sessionUserId, $isNonPrivileged);
		$analytics = \CashpickupCRM\getReturningAnalytics($db, $filters, $sessionUserId, $isNonPrivileged);
		$headers = ['Date','Service','Agent','Phone','Amount','Status'];
		$outRows = [];
		$totalTransactions = 0;
		$totalAmount = 0.0;
		$uniquePhones = [];
		foreach ($rows as $r) {
			$totalTransactions++;
			$totalAmount += (float)($r['amount'] ?? 0);
			$phone = trim((string)($r['phone'] ?? ''));
			if ($phone !== '') {
				$uniquePhones[$phone] = true;
			}
			$outRows[] = [
				(string)($r['date'] ?? ''),
				(string)($r['service_label'] ?? ''),
				(string)($r['agent_name'] ?? 'Unassigned'),
				(string)($r['phone'] ?? ''),
				number_format((float)($r['amount'] ?? 0), 2, '.', ''),
				(string)($r['status'] ?? ''),
			];
		}
		$summaryRows = [
			['Metric', 'Value'],
		];
		$readableFiltersArr = [];
		if (trim((string)($filters['find'] ?? '')) !== '') $readableFiltersArr[] = 'Search: ' . trim((string)$filters['find']);
		$dateFromF = trim((string)($filters['date_from'] ?? ''));
		$dateToF = trim((string)($filters['date_to'] ?? ''));
		if ($dateFromF !== '' && $dateToF !== '') {
			$readableFiltersArr[] = 'Date: ' . $dateFromF . ' → ' . $dateToF;
		} elseif ($dateFromF !== '') {
			$readableFiltersArr[] = 'From: ' . $dateFromF;
		} elseif ($dateToF !== '') {
			$readableFiltersArr[] = 'To: ' . $dateToF;
		}
		$svcList = array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/', (string)($filters['service'] ?? ''))), static fn($v) => $v !== ''));
		if ($svcList !== []) {
			$defs = \CashpickupCRM\crmServiceDefinitions();
			$labels = array_map(static fn($key) => $defs[$key]['label'] ?? $key, $svcList);
			$readableFiltersArr[] = 'Service: ' . implode(', ', $labels);
		}
		$agentFilter = trim((string)($filters['agent_id'] ?? ''));
		if ($agentFilter !== '') {
			$agentIds = array_values(array_filter(array_map('intval', preg_split('/\s*,\s*/', $agentFilter)), static fn($v) => $v > 0));
			if ($agentIds !== []) {
				$agentMap = \CashpickupCRM\agentNameMap($db, $agentIds);
				$labels = array_map(static fn($id) => $agentMap[$id] ?? ('Agent #' . $id), $agentIds);
				$readableFiltersArr[] = 'Agent: ' . implode(', ', $labels);
			}
		}
		$readableFilters = !empty($readableFiltersArr) ? implode(' | ', $readableFiltersArr) : 'None';
		$summaryRows[] = ['Filters applied', $readableFilters];
		$summaryRows[] = ['Total transactions', (string)$totalTransactions];
		$summaryRows[] = ['Total amount', number_format($totalAmount, 2, '.', '')];
		$summaryRows[] = ['Unique phones', (string)count($uniquePhones)];
		$analyticsRows = [
			['CRM Measure', 'Value'],
			['Total services tracked', (string)($analytics['total_services'] ?? 0)],
			['Total returning customers', (string)($analytics['total_returning_customers'] ?? 0)],
			['Total high-performing agents', (string)($analytics['total_high_performing_agents'] ?? 0)],
		];

		if ($format === 'csv') {
			$csvRows = $outRows;
			$csvRows[] = [];
			foreach ($summaryRows as $summaryRow) {
				$csvRows[] = $summaryRow;
			}
			$csvRows[] = [];
			foreach ($analyticsRows as $analyticsRow) {
				$csvRows[] = $analyticsRow;
			}
			$sendCsv('crm_export_' . $stamp . '.csv', $headers, $csvRows);
		} elseif ($format === 'pdf') {
			$org = $getOrg();
			$html = $orgHeaderHtml($org);
			$html .= '<h2 style="margin:0">CRM Export</h2>';
			$html .= '<div style="font-size:11px;color:#666">Generated: ' . $escape(date('Y-m-d H:i:s')) . '</div>';
			$html .= '<table width="100%" border="1" cellspacing="0" cellpadding="4" style="border-collapse:collapse;margin-top:10px;font-size:10px">';
			$html .= '<thead><tr>';
			foreach ($headers as $h) { $html .= '<th style="background:#f2f2f2">' . $escape($h) . '</th>'; }
			$html .= '</tr></thead><tbody>';
			foreach ($outRows as $rr) {
				$html .= '<tr>';
				foreach ($rr as $c) {
					$html .= '<td>' . $escape($c) . '</td>';
				}
				$html .= '</tr>';
			}
			$html .= '</tbody></table>';
			$html .= '<h3 style="margin-top:24px;">CRM Summary Metrics</h3>';
			$html .= '<table width="100%" border="1" cellspacing="0" cellpadding="4" style="border-collapse:collapse;margin-top:10px;font-size:10px">';
			foreach ($summaryRows as $row) {
				$html .= '<tr><td style="background:#f8f8f8;font-weight:bold">' . $escape($row[0]) . '</td><td>' . $escape($row[1]) . '</td></tr>';
			}
			$html .= '</table>';
			$html .= '<h3 style="margin-top:24px;">CRM Analytics</h3>';
			$html .= '<table width="100%" border="1" cellspacing="0" cellpadding="4" style="border-collapse:collapse;margin-top:10px;font-size:10px">';
			$html .= '<tr><th style="background:#f8f8f8">Measure</th><th style="background:#f8f8f8">Value</th></tr>';
			$html .= '<tr><td>Total services tracked</td><td>' . $escape((string)($analytics['total_services'] ?? 0)) . '</td></tr>';
			$html .= '<tr><td>Total returning customers</td><td>' . $escape((string)($analytics['total_returning_customers'] ?? 0)) . '</td></tr>';
			$html .= '<tr><td>Total high-performing agents</td><td>' . $escape((string)($analytics['total_high_performing_agents'] ?? 0)) . '</td></tr>';
			$html .= '</table>';
			$sendPdf('crm_export_' . $stamp . '.pdf', 'CRM Export', $html);
		} else {
			if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
				throw new \Exception('PhpSpreadsheet library is missing.');
			}
			while (ob_get_level()) { ob_end_clean(); }
			$org = $getOrg();
			$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
			$sheet = $spreadsheet->getActiveSheet();
			$sheet->setTitle('Transactions');
			$startRow = $applyOrgToSheet($sheet, $org, 1);
			$sheet->fromArray($headers, null, 'A' . $startRow);
			if (!empty($outRows)) {
				$sheet->fromArray($outRows, null, 'A' . ($startRow + 1));
			}
			$lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
			$sheet->getStyle('A' . $startRow . ':' . $lastCol . $startRow)->getFont()->setBold(true);
			$sheet->freezePane('A' . ($startRow + 1));
			for ($i = 1; $i <= count($headers); $i++) {
				$col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
				$sheet->getColumnDimension($col)->setAutoSize(true);
			}

			// Summary sheet
			$s2 = $spreadsheet->createSheet();
			$s2->setTitle('Summary');
			$s2->fromArray(['Field','Value'], null, 'A1');
			if (!empty($summaryRows)) {
				$s2->fromArray(array_values(array_map(function($r){ return $r; }, $summaryRows)), null, 'A2');
			}
			$s2->getStyle('A1:B1')->getFont()->setBold(true);
			$s2->getColumnDimension('A')->setAutoSize(true);
			$s2->getColumnDimension('B')->setAutoSize(true);

			// Analytics sheet
			$s3 = $spreadsheet->createSheet();
			$s3->setTitle('Analytics');
			// Header
			$s3->fromArray(['Section','Value','Notes'], null, 'A1');
			$row = 2;
			$s3->setCellValue('A' . $row, 'Total services tracked');
			$s3->setCellValue('B' . $row, (string)($analytics['total_services'] ?? ''));
			$row++;
			$s3->setCellValue('A' . $row, 'Total returning customers');
			$s3->setCellValue('B' . $row, (string)($analytics['total_returning_customers'] ?? ''));
			$row++;
			$s3->setCellValue('A' . $row, 'Total high-performing agents');
			$s3->setCellValue('B' . $row, (string)($analytics['total_high_performing_agents'] ?? ''));
			$row += 2;
			// Per-service breakdown
			$s3->setCellValue('A' . $row, 'Service'); $s3->setCellValue('B' . $row, 'Returning Rate (%)'); $s3->setCellValue('C' . $row, 'Meta');
			$s3->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
			$row++;
			foreach (($analytics['services'] ?? []) as $svc) {
				$s3->setCellValue('A' . $row, (string)($svc['title'] ?? ''));
				$s3->setCellValue('B' . $row, (string)($svc['rate'] ?? ''));
				$s3->setCellValue('C' . $row, (string)($svc['meta'] ?? ''));
				$row++;
			}
			$row += 1;
			// Top agents
			$s3->setCellValue('A' . $row, 'Top agents (by returning rate)'); $row++;
			$s3->setCellValue('A' . $row, 'Agent'); $s3->setCellValue('B' . $row, 'Rate'); $s3->setCellValue('C' . $row, 'Meta');
			$s3->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
			$row++;
			foreach (($analytics['agents'] ?? []) as $a) {
				$s3->setCellValue('A' . $row, (string)($a['title'] ?? $a['agent_name'] ?? ''));
				$s3->setCellValue('B' . $row, (string)($a['rate'] ?? ''));
				$s3->setCellValue('C' . $row, (string)($a['meta'] ?? ''));
				$row++;
			}
			$row += 1;
			// Top customers
			$s3->setCellValue('A' . $row, 'Top customers (by returning rate)'); $row++;
			$s3->setCellValue('A' . $row, 'Customer'); $s3->setCellValue('B' . $row, 'Rate'); $s3->setCellValue('C' . $row, 'Meta');
			$s3->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
			$row++;
			foreach (($analytics['customers'] ?? []) as $cst) {
				$s3->setCellValue('A' . $row, (string)($cst['title'] ?? ''));
				$s3->setCellValue('B' . $row, (string)($cst['rate'] ?? ''));
				$s3->setCellValue('C' . $row, (string)($cst['meta'] ?? ''));
				$row++;
			}
			// Separate sheets for top agents and top customers for easier consumption
			$s4 = $spreadsheet->createSheet();
			$s4->setTitle('Top Agents');
			$s4->fromArray(['Agent','Rate','Meta'], null, 'A1');
			$r = 2;
			foreach (($analytics['agents'] ?? []) as $agentRow) {
				$s4->setCellValue('A' . $r, (string)($agentRow['title'] ?? $agentRow['agent_name'] ?? ''));
				$s4->setCellValue('B' . $r, (string)($agentRow['rate'] ?? ''));
				$s4->setCellValue('C' . $r, (string)($agentRow['meta'] ?? ''));
				$r++;
			}
			foreach (['A','B','C'] as $col) { $s4->getColumnDimension($col)->setAutoSize(true); }
			$s5 = $spreadsheet->createSheet();
			$s5->setTitle('Top Customers');
			$s5->fromArray(['Customer','Rate','Meta'], null, 'A1');
			$r = 2;
			foreach (($analytics['customers'] ?? []) as $custRow) {
				$s5->setCellValue('A' . $r, (string)($custRow['title'] ?? ''));
				$s5->setCellValue('B' . $r, (string)($custRow['rate'] ?? ''));
				$s5->setCellValue('C' . $r, (string)($custRow['meta'] ?? ''));
				$r++;
			}
			foreach (['A','B','C'] as $col) { $s5->getColumnDimension($col)->setAutoSize(true); }
			// autosize
			foreach (range('A','C') as $col) { $s3->getColumnDimension($col)->setAutoSize(true); }

			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment; filename="crm_export_' . $stamp . '.xlsx"');
			header('Cache-Control: max-age=0');
			$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
			$writer->save('php://output');
			exit;
		}
	}

	if ($resource === 'general_ledger') {
		$headers = ['ID','Full Name','Phone','ID Type','ID Number','Location','Transactions','Balance','Last Transaction'];
		$outRows = [];
		foreach ($rows as $r) {
			$balance = (float)($r->total_received ?? 0)
				- (float)($r->total_paid_out ?? 0)
				- (float)($r->credit_balance_debit ?? 0);
			$outRows[] = [
				(string)($r->id ?? ''),
				(string)($r->full_name ?? ''),
				(string)($r->phone_number ?? ''),
				(string)($r->id_type ?? ''),
				(string)($r->id_number ?? ''),
				(string)($r->location ?? ''),
				(string)($r->tx_count ?? 0),
				number_format((float)$balance, 2, '.', ''),
				(string)($r->last_tx ?? ''),
			];
		}

		if ($format === 'csv') {
			$sendCsv('cashpickup_customers_' . $stamp . '.csv', $headers, $outRows);
		} elseif ($format === 'pdf') {
			$org = $getOrg();
			$rowsHtml = '';
			foreach ($outRows as $rr) {
				$rowsHtml .= '<tr>';
				foreach ($rr as $c) {
					$rowsHtml .= '<td>' . $escape($c) . '</td>';
				}
				$rowsHtml .= '</tr>';
			}
			$html = $orgHeaderHtml($org);
			$html .= '<h2 style="margin:0">Cashpickup Customers Ledger</h2>';
			$html .= '<div style="font-size:11px;color:#666">Generated: ' . $escape(date('Y-m-d H:i:s')) . '</div>';
			$html .= '<h3 style="margin-top:12px">Metrics</h3>';
			$html .= '<table width="100%" border="1" cellspacing="0" cellpadding="4" style="border-collapse:collapse;font-size:10px">';
			$html .= '<tr><th style="background:#f2f2f2;width:40%">Total Transactions</th><td>' . $escape((string)$metricTotals['total_transactions']) . '</td></tr>';
			$html .= '<tr><th style="background:#f2f2f2">Total Received</th><td>GHS ' . $escape(number_format((float)$metricTotals['total_received'], 2, '.', '')) . '</td></tr>';
			$html .= '<tr><th style="background:#f2f2f2">Total Paid Out</th><td>GHS ' . $escape(number_format((float)$metricTotals['total_paid_out'], 2, '.', '')) . '</td></tr>';
			$html .= '<tr><th style="background:#f2f2f2">Balance</th><td>GHS ' . $escape(number_format((float)$metricTotals['balance'], 2, '.', '')) . '</td></tr>';
			$html .= '</table>';
			$html .= '<table width="100%" border="1" cellspacing="0" cellpadding="4" style="border-collapse:collapse;margin-top:10px;font-size:10px">';
			$html .= '<thead><tr>';
			foreach ($headers as $h) { $html .= '<th style="background:#f2f2f2">' . $escape($h) . '</th>'; }
			$html .= '</tr></thead><tbody>' . $rowsHtml . '</tbody></table>';
			$sendPdf('cashpickup_customers_' . $stamp . '.pdf', 'Cashpickup Customers Ledger', $html);
		} else {
			$sendXlsx('cashpickup_customers_' . $stamp . '.xlsx', 'Customers', $headers, $outRows);
		}
	}

	if ($resource === 'general_ledger') {
		$ledger = $generalLedgerFetch();
		$ledgerMetrics = $generalLedgerMetricsFetch();
		$rows = is_array($ledger['rows'] ?? null) ? $ledger['rows'] : [];
		$headers = ['Transaction Date','Transaction ID','Description','Received','Payout','Balance'];
		$outRows = [];
		foreach ($rows as $row) {
			$description = trim((string)($row->sender_details ?? ''));
			$phone = trim((string)($row->phone_number ?? ''));
			$descText = $description;
			if ($phone !== '') {
				$descText .= ($descText !== '' ? ' / ' : '') . $phone;
			}
			$outRows[] = [
				(string)($row->date_created ?? ''),
				(string)($row->id ?? ''),
				$descText,
				number_format((float)($row->received_amount ?? 0), 2, '.', ''),
				number_format((float)($row->payout_amount ?? 0), 2, '.', ''),
				number_format((float)($row->balance_amount ?? 0), 2, '.', ''),
			];
		}

		if ($format === 'csv') {
			$sendCsv('cashpickup_general_ledger_' . $stamp . '.csv', $headers, $outRows);
		} elseif ($format === 'pdf') {
			$org = $getOrg();
			$rowsHtml = '';
			foreach ($outRows as $rr) {
				$rowsHtml .= '<tr>';
				foreach ($rr as $c) {
					$rowsHtml .= '<td>' . $escape($c) . '</td>';
				}
				$rowsHtml .= '</tr>';
			}
			$html = $orgHeaderHtml($org);
			$html .= '<h2 style="margin:0">Cashpickup General Ledger</h2>';
			$html .= '<div style="font-size:11px;color:#666">Generated: ' . $escape(date('Y-m-d H:i:s')) . '</div>';
			$html .= '<h3 style="margin-top:12px">Metrics</h3>';
			$html .= '<table width="100%" border="1" cellspacing="0" cellpadding="4" style="border-collapse:collapse;font-size:10px">';
			$html .= '<tr><th style="background:#f2f2f2;width:30%">Total Received</th><td>GHS ' . $escape(number_format((float)($ledgerMetrics['total_received'] ?? 0), 2, '.', '')) . '</td></tr>';
			$html .= '<tr><th style="background:#f2f2f2">Total Paid Out</th><td>GHS ' . $escape(number_format((float)($ledgerMetrics['total_paid_out'] ?? 0), 2, '.', '')) . '</td></tr>';
			$html .= '<tr><th style="background:#f2f2f2">Balance</th><td>GHS ' . $escape(number_format((float)($ledgerMetrics['balance'] ?? 0), 2, '.', '')) . '</td></tr>';
			$html .= '</table>';
			$html .= '<table width="100%" border="1" cellspacing="0" cellpadding="4" style="border-collapse:collapse;margin-top:10px;font-size:10px">';
			$html .= '<thead><tr>';
			foreach ($headers as $h) { $html .= '<th style="background:#f2f2f2">' . $escape($h) . '</th>'; }
			$html .= '</tr></thead><tbody>' . $rowsHtml . '</tbody></table>';
			$sendPdf('cashpickup_general_ledger_' . $stamp . '.pdf', 'Cashpickup General Ledger', $html);
		} else {
			$sendXlsx('cashpickup_general_ledger_' . $stamp . '.xlsx', 'General Ledger', $headers, $outRows);
		}
	}

	if ($resource === 'customer') {
		$id = (int)($_GET['id'] ?? 0);
		if ($id <= 0) {
			throw new \Exception('Missing customer id');
		}
		$statementFilters = [
			'find' => trim((string)($_GET['find'] ?? '')),
			'date_from' => trim((string)($_GET['date_from'] ?? '')),
			'date_to' => trim((string)($_GET['date_to'] ?? '')),
			'order' => 'desc',
		];
		$statementResult = $customerStatementFetch($id, $statementFilters);
		$customer = $statementResult['customer'] ?? null;
		$txRows = is_array($statementResult['rows'] ?? null) ? $statementResult['rows'] : [];
		if (empty($customer)) {
			throw new \Exception('Customer not found');
		}

		$totalTransactions = is_array($txRows) ? count($txRows) : 0;
		$totalReceived = 0.0;
		$totalPaidOut = 0.0;
		$totalCredit = 0.0;
		$totalRepayments = 0.0;
		foreach ($txRows as $t) {
			$totalReceived += (float)($t->received_amount ?? 0);
			$totalPaidOut += (float)($t->payout_amount ?? 0);
			$totalCredit += (float)($t->credit_amount ?? 0);
			$totalRepayments += (float)($t->credit_repayment_amount ?? 0);
		}
		$balance = !empty($txRows) ? (float)($txRows[0]->balance_amount ?? 0) : 0.0;

		$summaryHeaders = ['Field','Value'];
		$summaryRows = [
			['ID', (string)($customer->id ?? '')],
			['Full Name', (string)($customer->full_name ?? '')],
			['Phone', (string)($customer->phone_number ?? '')],
			['ID Type', (string)($customer->id_type ?? '')],
			['ID Number', (string)($customer->id_number ?? '')],
			['Location', (string)($customer->location ?? '')],
		];

		$metricRows = [
			['Total Transactions', (string)$totalTransactions],
			['Total Received', number_format((float)$totalReceived, 2, '.', '')],
			['Total Paid Out', number_format((float)$totalPaidOut, 2, '.', '')],
			['Credit Issued / Paid Out', number_format((float)$totalCredit, 2, '.', '')],
			['Credit Repaid', number_format((float)$totalRepayments, 2, '.', '')],
			['Balance', number_format((float)$balance, 2, '.', '')],
		];

		$txHeaders = ['Transaction Date','Transaction ID','Activity','Description','Received','Payout','Credit','Repayment','Accumulated Balance'];
		$txRowsOut = [];
		foreach ($txRows as $t) {
			$description = trim((string)($t->sender_details ?? ''));
			$phone = trim((string)($t->phone_number ?? ''));
			$source = trim((string)($t->source ?? ''));
			$method = trim((string)($t->payment_method ?? ''));
			$tracking = trim((string)($t->tracking_id ?? ''));
			$type = (string)($t->entry_type ?? '');
			$typeLabel = ucwords(str_replace('_', ' ', $type));
			$descText = $description;
			if ($phone !== '') {
				$descText .= ($descText !== '' ? ' / ' : '') . $phone;
			}
			if ($source !== '') {
				$descText .= ($descText !== '' ? ' / ' : '') . 'Source: ' . $source;
			}
			if ($method !== '') {
				$descText .= ($descText !== '' ? ' / ' : '') . 'Method: ' . $method;
			}
			if ($tracking !== '') {
				$descText .= ($descText !== '' ? ' / ' : '') . 'Tracking: ' . $tracking;
			}
			$txRowsOut[] = [
				(string)($t->date_created ?? ''),
				(string)($t->id ?? ''),
				$typeLabel,
				$descText,
				number_format((float)($t->received_amount ?? 0), 2, '.', ''),
				number_format((float)($t->payout_amount ?? 0), 2, '.', ''),
				number_format((float)($t->credit_amount ?? 0), 2, '.', ''),
				number_format((float)($t->credit_repayment_amount ?? 0), 2, '.', ''),
				number_format((float)($t->balance_amount ?? 0), 2, '.', ''),
			];
		}

		if ($format === 'pdf') {
			$org = $getOrg();
			$html = $orgHeaderHtml($org);
			$html .= '<h2 style="margin:0">Cashpickup Customer Ledger Statement</h2>';
			$html .= '<div style="font-size:11px;color:#666">Generated: ' . $escape(date('Y-m-d H:i:s')) . '</div>';
			$html .= '<h3 style="margin-top:12px">Customer Profile</h3>';
			$html .= '<table width="100%" border="1" cellspacing="0" cellpadding="4" style="border-collapse:collapse;font-size:10px">';
			foreach ($summaryRows as $sr) {
				$html .= '<tr><th style="background:#f2f2f2;width:30%">' . $escape($sr[0]) . '</th><td>' . $escape($sr[1]) . '</td></tr>';
			}
			$html .= '</table>';
			$html .= '<h3 style="margin-top:12px">Metrics</h3>';
			$html .= '<table width="100%" border="1" cellspacing="0" cellpadding="4" style="border-collapse:collapse;font-size:10px">';
			foreach ($metricRows as $mr) {
				$val = in_array($mr[0], ['Total Received','Total Paid Out','Credit Issued / Paid Out','Credit Repaid','Balance'], true) ? ('GHS ' . $mr[1]) : $mr[1];
				$html .= '<tr><th style="background:#f2f2f2;width:30%">' . $escape($mr[0]) . '</th><td>' . $escape($val) . '</td></tr>';
			}
			$html .= '</table>';
			$html .= '<h3 style="margin-top:12px">Ledger Statement</h3>';
			$html .= '<div style="font-size:10px;color:#666;margin:4px 0 8px">Ordered by transaction id descending. Accumulated balance shows the balance immediately after each transaction.</div>';
			$html .= '<table width="100%" border="1" cellspacing="0" cellpadding="4" style="border-collapse:collapse;font-size:10px">';
			$html .= '<thead><tr>';
			foreach ($txHeaders as $h) { $html .= '<th style="background:#f2f2f2">' . $escape($h) . '</th>'; }
			$html .= '</tr></thead><tbody>';
			foreach ($txRows as $tx) {
				$description = trim((string)($tx->sender_details ?? ''));
				$phone = trim((string)($tx->phone_number ?? ''));
				$source = trim((string)($tx->source ?? ''));
				$method = trim((string)($tx->payment_method ?? ''));
				$tracking = trim((string)($tx->tracking_id ?? ''));
				$typeLabel = ucwords(str_replace('_', ' ', (string)($tx->entry_type ?? '')));
				$html .= '<tr>';
				$html .= '<td>' . $escape((string)($tx->date_created ?? '')) . '</td>';
				$html .= '<td><div style="font-weight:bold">#' . $escape((string)($tx->id ?? '')) . '</div></td>';
				$html .= '<td>' . $escape($typeLabel) . '</td>';
				$html .= '<td><div style="font-weight:bold">' . $escape($description !== '' ? $description : '---') . '</div>';
				if ($phone !== '') {
					$html .= '<div style="color:#666">Phone: ' . $escape($phone) . '</div>';
				}
				if ($source !== '') {
					$html .= '<div style="color:#666">Source: ' . $escape($source) . '</div>';
				}
				if ($method !== '') {
					$html .= '<div style="color:#666">Method: ' . $escape($method) . '</div>';
				}
				if ($tracking !== '') {
					$html .= '<div style="color:#666">Tracking: ' . $escape($tracking) . '</div>';
				}
				$html .= '</td>';
				$html .= '<td style="text-align:right">GHS ' . $escape(number_format((float)($tx->received_amount ?? 0), 2, '.', '')) . '</td>';
				$html .= '<td style="text-align:right">GHS ' . $escape(number_format((float)($tx->payout_amount ?? 0), 2, '.', '')) . '</td>';
				$html .= '<td style="text-align:right">GHS ' . $escape(number_format((float)($tx->credit_amount ?? 0), 2, '.', '')) . '</td>';
				$html .= '<td style="text-align:right">GHS ' . $escape(number_format((float)($tx->credit_repayment_amount ?? 0), 2, '.', '')) . '</td>';
				$balanceStyle = (float)($tx->balance_amount ?? 0) < 0 ? 'color:#dc3545;font-weight:bold;' : '';
				$html .= '<td style="text-align:right;' . $balanceStyle . '">GHS ' . $escape(number_format((float)($tx->balance_amount ?? 0), 2, '.', '')) . '</td>';
				$html .= '</tr>';
			}
			$html .= '</tbody></table>';
			$sendPdf('cashpickup_customer_statement_' . ($customer->id ?? 'record') . '_' . $stamp . '.pdf', 'Cashpickup Customer Ledger Statement', $html);
		}

		if ($format === 'csv') {
			$sendCsv('cashpickup_customer_statement_' . ($customer->id ?? 'record') . '_' . $stamp . '.csv', $txHeaders, $txRowsOut);
		}

		$sendXlsx('cashpickup_customer_statement_' . ($customer->id ?? 'record') . '_' . $stamp . '.xlsx', 'Statement', $txHeaders, $txRowsOut);
	}

	if ($resource === 'view' || $resource === 'payouts') {
		$id = (int)($_GET['id'] ?? 0);
		if ($id <= 0) {
			throw new \Exception('Missing id');
		}
		list($receive, $payouts) = $singleReceiveFetch($id);
		if (empty($receive)) {
			throw new \Exception('Record not found');
		}

		$assignedName = trim(((string)($receive->assigned_first_name ?? '')).' '.((string)($receive->assigned_last_name ?? '')));
		$summaryHeaders = ['Field','Value'];
		$summaryRows = [
			['ID', (string)($receive->id ?? '')],
			['Date', (string)($receive->date_created ?? $receive->date ?? '')],
			['Source', (string)($receive->source ?? '')],
			['Tracking', (string)($receive->tracking_id ?? '')],
			['Customer', (string)($receive->sender_details ?? '')],
			['Phone', (string)($receive->phone_number ?? '')],
			['ID Type', (string)($receive->customer_id_type ?? '')],
			['ID Number', (string)($receive->customer_id_number ?? '')],
			['Location', (string)($receive->customer_location ?? '')],
			['Assigned To', $assignedName],
			['Amount Involved', number_format((float)($receive->amount_involved ?? 0), 2, '.', '')],
		];

		$pHeaders = ['Payout ID','Date','Requested','Charge','Paid Out','Method','Channel','Confirmed By','Status'];
		$pRows = [];
		$payouts = is_array($payouts) ? $payouts : [];
		foreach ($payouts as $p) {
			$pRows[] = [
				(string)($p->id ?? ''),
				(string)($p->date_created ?? ''),
				number_format((float)($p->amount_requested ?? 0), 2, '.', ''),
				number_format((float)($p->pickup_charge ?? 0), 2, '.', ''),
				number_format((float)($p->amount_paid_out ?? 0), 2, '.', ''),
				(string)($p->payment_method ?? ''),
				(string)($p->payout_channel ?? ''),
				(string)($p->confirmed_by ?? ''),
				(string)($p->approval_status ?? ''),
			];
		}

		if ($format === 'pdf') {
			$org = $getOrg();
			$html = $orgHeaderHtml($org);
			$html .= '<h2 style="margin:0">Cashpickup Export</h2>';
			$html .= '<div style="font-size:11px;color:#666">Record #' . $escape($receive->id ?? '') . ' • Generated: ' . $escape(date('Y-m-d H:i:s')) . '</div>';
			$html .= '<h3 style="margin-top:12px">Summary</h3>';
			$html .= '<table width="100%" border="1" cellspacing="0" cellpadding="4" style="border-collapse:collapse;font-size:10px">';
			foreach ($summaryRows as $sr) {
				$html .= '<tr><th style="background:#f2f2f2;width:30%">' . $escape($sr[0]) . '</th><td>' . $escape($sr[1]) . '</td></tr>';
			}
			$html .= '</table>';
			$html .= '<h3 style="margin-top:12px">Payouts</h3>';
			$html .= '<table width="100%" border="1" cellspacing="0" cellpadding="4" style="border-collapse:collapse;font-size:10px">';
			$html .= '<thead><tr>';
			foreach ($pHeaders as $h) { $html .= '<th style="background:#f2f2f2">' . $escape($h) . '</th>'; }
			$html .= '</tr></thead><tbody>';
			foreach ($pRows as $pr) {
				$html .= '<tr>';
				foreach ($pr as $c) { $html .= '<td>' . $escape($c) . '</td>'; }
				$html .= '</tr>';
			}
			$html .= '</tbody></table>';
			$sendPdf('cashpickup_' . ($receive->id ?? 'record') . '_' . $stamp . '.pdf', 'Cashpickup Export', $html);
		}

		if ($format === 'csv') {
			$sendCsv('cashpickup_' . ($receive->id ?? 'record') . '_' . $stamp . '.csv', $pHeaders, $pRows);
		}

		if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
			throw new \Exception('PhpSpreadsheet library is missing.');
		}
		while (ob_get_level()) {
			ob_end_clean();
		}
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$s1 = $spreadsheet->getActiveSheet();
		$s1->setTitle('Summary');
		$org = $getOrg();
		$startRow = $applyOrgToSheet($s1, $org, 1);
		$s1->fromArray($summaryHeaders, null, 'A' . $startRow);
		$s1->fromArray($summaryRows, null, 'A' . ($startRow + 1));
		$s1->getStyle('A' . $startRow . ':B' . $startRow)->getFont()->setBold(true);
		$s1->getColumnDimension('A')->setAutoSize(true);
		$s1->getColumnDimension('B')->setAutoSize(true);

		$s2 = $spreadsheet->createSheet();
		$s2->setTitle('Payouts');
		$startRow2 = $applyOrgToSheet($s2, $org, 1);
		$s2->fromArray($pHeaders, null, 'A' . $startRow2);
		if (!empty($pRows)) {
			$s2->fromArray($pRows, null, 'A' . ($startRow2 + 1));
		}
		$lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($pHeaders));
		$s2->getStyle('A' . $startRow2 . ':' . $lastCol . $startRow2)->getFont()->setBold(true);
		$s2->freezePane('A' . ($startRow2 + 1));
		for ($i = 1; $i <= count($pHeaders); $i++) {
			$col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
			$s2->getColumnDimension($col)->setAutoSize(true);
		}

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment; filename="cashpickup_' . ($receive->id ?? 'record') . '_' . $stamp . '.xlsx"');
		header('Cache-Control: max-age=0');
		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
		$writer->save('php://output');
		exit;
	}

	throw new \Exception('Unknown resource');
} catch (\Throwable $e) {
	message_fail($e->getMessage());
	redirect($admin_route . '/' . $plugin_route);
}

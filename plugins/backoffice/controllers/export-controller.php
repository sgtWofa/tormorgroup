<?php

$vars = get_value();
$admin_route = $vars['admin_route'] ?? 'admin';
$plugin_route = $vars['plugin_route'] ?? 'back_office';

$session = get_session();
$userId = (int)($session->id ?? 0);
if ($userId <= 0) {
  http_response_code(401);
  echo 'Not authenticated';
  exit;
}

$autoload = __DIR__ . '/../../../assets/vendor/autoload.php';
if (file_exists($autoload)) {
  require_once $autoload;
}

$entity = strtolower(trim((string)($_GET['entity'] ?? 'requests')));
$resource = strtolower(trim((string)($_GET['resource'] ?? 'list')));
$format = strtolower(trim((string)($_GET['format'] ?? 'xlsx')));

$db = new \Core\Database;

$escape = function($v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
};

$formatDateHuman = function($value): string {
  $raw = trim((string)($value ?? ''));
  if ($raw === '') return '';
  try {
    if (function_exists('get_date_time')) {
      return (string)get_date_time($raw);
    }
    $ts = strtotime($raw);
    if ($ts === false) return $raw;
    return date('d M Y, h:i A', $ts);
  } catch (\Throwable $e) {
    return $raw;
  }
};

$statusBadgeHtml = function($status) use ($escape): string {
  $statusText = trim((string)($status ?? ''));
  $statusKey = strtolower($statusText);
  $style = 'display:inline-block;padding:3px 10px;border-radius:12px;font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;border:1px solid #cbd5e1;color:#334155;background:#f1f5f9;';

  if (in_array($statusKey, ['approved', 'complete', 'completed', 'balanced'], true)) {
    $style = 'display:inline-block;padding:3px 10px;border-radius:12px;font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;border:1px solid #bbf7d0;color:#15803d;background:#f0fdf4;';
  } elseif (in_array($statusKey, ['rejected', 'failed', 'shortage', 'not-balanced', 'mismatch'], true)) {
    $style = 'display:inline-block;padding:3px 10px;border-radius:12px;font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;border:1px solid #fecaca;color:#b91c1c;background:#fef2f2;';
  } elseif (strpos($statusKey, 'pending') !== false || strpos($statusKey, 'waiting') !== false || $statusKey === 'overage') {
    $style = 'display:inline-block;padding:3px 10px;border-radius:12px;font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;border:1px solid #fde68a;color:#b45309;background:#fffbeb;';
  }

  return '<span style="' . $style . '">' . $escape($statusText) . '</span>';
};

$renderJsonValue = function(mixed $decoded, string $key) use ($escape): array {
  $lowerKey = strtolower($key);
  if (is_array($decoded)) {
    $isList = array_keys($decoded) === range(0, count($decoded) - 1);
    if ($isList) {
      if (empty($decoded)) {
        return ['', ''];
      }
      $listItems = [];
      $plainItems = [];
      foreach ($decoded as $item) {
        if (is_array($item)) {
          $itemText = [];
          foreach ($item as $field => $value) {
            $itemText[] = ucfirst(str_replace('_', ' ', $field)) . ': ' . (is_scalar($value) || $value === null ? (string)$value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
          }
          $plainItems[] = implode(', ', $itemText);
          if (is_array($item)) {
            $listItems[] = '<tr><td style="padding:8px;border:1px solid #d1d5db;vertical-align:top;">' . $escape(implode('</td><td style="padding:8px;border:1px solid #d1d5db;vertical-align:top;">', array_map(fn($v) => (string)$v, $item))) . '</td></tr>';
          }
        } else {
          $plainItems[] = (string)$item;
          $listItems[] = '<li>' . $escape((string)$item) . '</li>';
        }
      }
      if ($lowerKey === 'denominations_json' || $lowerKey === 'change_denominations_json') {
        $headerRow = '';
        if (count($decoded) > 0 && is_array($decoded[0])) {
          $headers = array_keys($decoded[0]);
          $headerCells = implode('', array_map(fn($h) => '<th style="padding:8px;border:1px solid #94a3b8;background:#f1f5f9;text-align:left;font-weight:700;color:#0f172a;">' . $escape(ucwords(str_replace('_', ' ', $h))) . '</th>', $headers));
          $headerRow = '<tr>' . $headerCells . '</tr>';
          $bodyRows = '';
          foreach ($decoded as $item) {
            $cells = [];
            foreach ($headers as $header) {
              $cells[] = '<td style="padding:8px;border:1px solid #d1d5db;vertical-align:top;">' . $escape(is_scalar($item[$header] ?? null) ? (string)($item[$header] ?? '') : json_encode($item[$header], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</td>';
            }
            $bodyRows .= '<tr>' . implode('', $cells) . '</tr>';
          }
          return ['<table style="width:100%;border-collapse:collapse;margin:0 0 12px 0;">' . $headerRow . $bodyRows . '</table>', implode("\n", $plainItems)];
        }
      }
      return ['<ul style="margin:0 0 12px 18px;padding:0;color:#334155;">' . implode('', $listItems) . '</ul>', implode("\n", $plainItems)];
    }
    $rows = [];
    $plainRows = [];
    foreach ($decoded as $field => $item) {
      $rows[] = '<tr><td style="padding:8px;border:1px solid #d1d5db;font-weight:700;background:#f8fafc;width:30%;">' . $escape(ucwords(str_replace('_', ' ', $field))) . '</td><td style="padding:8px;border:1px solid #d1d5db;">' . $escape(is_scalar($item) || $item === null ? (string)$item : json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</td></tr>';
      $plainRows[] = ucfirst(str_replace('_', ' ', $field)) . ': ' . (is_scalar($item) || $item === null ? (string)$item : json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    return ['<table style="width:100%;border-collapse:collapse;margin:0 0 12px 0;">' . implode('', $rows) . '</table>', implode("\n", $plainRows)];
  }
  return ['<pre style="margin:0;font-size:8.5pt;line-height:1.3;white-space:pre-wrap;word-break:break-word;color:#334155;">' . $escape(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '</pre>', json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)];
};

$renderDetailValue = function(string $key, string $value) use ($escape, $statusBadgeHtml, $formatDateHuman, $renderJsonValue): array {
  $raw = trim($value);
  if ($raw === '') {
    return ['', ''];
  }
  $lowerKey = strtolower($key);
  if ($lowerKey === 'status' || str_ends_with($lowerKey, 'status')) {
    return [$statusBadgeHtml($raw), $raw];
  }
  if (strpos($lowerKey, 'image') !== false || strpos($lowerKey, 'proof') !== false) {
    return ['', ''];
  }
  if (strpos($lowerKey, 'json') !== false || ((str_starts_with($raw, '{') && str_ends_with($raw, '}')) || (str_starts_with($raw, '[') && str_ends_with($raw, ']')))) {
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) {
      if (empty($decoded)) {
        return ['', ''];
      }
      return $renderJsonValue($decoded, $lowerKey);
    }
  }
  if (strpos($lowerKey, 'date') !== false || str_ends_with($lowerKey, '_at')) {
    $formatted = $formatDateHuman($raw);
    return [$escape($formatted), $formatted];
  }
  return [$escape($raw), $raw];
};

$renderDetailLabel = function(string $key): string {
  $map = [
    'denominations_json' => 'Denominations',
    'change_denominations_json' => 'Change Denominations',
    'proof_image' => 'Proof Image',
  ];
  $lowerKey = strtolower($key);
  return $map[$lowerKey] ?? ucwords(str_replace('_', ' ', $key));
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
      "SELECT company_name, title, admin_contact, ceo_contact, company_sim, image FROM settings ORDER BY id DESC LIMIT 1"
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
        $org['logo'] = function_exists('get_image') ? (string)get_image($img) : $img;
      }
    }
  } catch (\Throwable $e) {
    // ignore
  }
  return $org;
};

$orgHeaderHtml = function(array $org) use ($escape): string {
  $lines = [];
  $rawTitle = trim((string)($org['title'] ?? ''));
  if ($rawTitle !== '' && strtoupper($rawTitle) !== 'CEO') {
    $lines[] = $escape($rawTitle);
  }
  $contacts = [];
  if (trim((string)($org['phone'] ?? '')) !== '') $contacts[] = 'Office: ' . $escape($org['phone']);
  if (!empty($contacts)) $lines[] = implode(' | ', $contacts);
  if (trim((string)($org['sim'] ?? '')) !== '') $lines[] = 'Sim: ' . $escape($org['sim']);

  $logo = trim((string)($org['logo'] ?? ''));
  $logoHtml = $logo !== '' ? '<img src="' . $escape($logo) . '" style="height:42px;max-width:120px;object-fit:contain;">' : '';

  $out = '<table width="100%" cellspacing="0" cellpadding="0" style="border-bottom:2px solid #0f172a;padding-bottom:12px;margin-bottom:16px;font-family:Segoe UI,Helvetica,Arial,sans-serif;">';
  $out .= '<tr>';
  $out .= '<td style="vertical-align:middle;">';
  $out .= '<div style="font-size:20px;font-weight:800;color:#0f172a;letter-spacing:-0.03em;">' . $escape((string)($org['name'] ?? '')) . '</div>';
  foreach ($lines as $ln) {
    $out .= '<div style="font-size:10px;color:#64748b;margin-top:2px;">' . $ln . '</div>';
  }
  $out .= '</td>';
  if ($logoHtml !== '') {
    $out .= '<td style="width:130px;vertical-align:middle;text-align:right;">' . $logoHtml . '</td>';
  }
  $out .= '</tr>';
  $out .= '</table>';
  return $out;
};

$getGlobalPdfCss = function(): string {
  return '
    body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; color: #1e293b; line-height: 1.4; font-size: 9pt; }
    h1 { font-size: 18pt; font-weight: 800; color: #0f172a; margin: 0 0 4px 0; letter-spacing: -0.02em; }
    h2 { font-size: 14pt; font-weight: 700; color: #0f172a; margin: 0 0 6px 0; }
    h3 { font-size: 11pt; font-weight: 700; color: #334155; margin: 16px 0 8px 0; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
    .meta-bar { font-size: 9pt; color: #64748b; margin-bottom: 16px; }
    .table-modern { width: 100%; border-collapse: collapse; font-size: 8.5pt; margin-top: 8px; }
    .table-modern th { background-color: #0f172a; color: #ffffff; font-weight: 700; text-transform: uppercase; font-size: 7.5pt; letter-spacing: 0.05em; padding: 8px 10px; text-align: left; border: none; }
    .table-modern td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; color: #334155; }
    .table-modern tr:nth-child(even) td { background-color: #f8fafc; }
    .card-grid { width: 100%; border-collapse: separate; border-spacing: 8px; margin-top: 10px; }
    .card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; }
    .card-title { font-size: 7.5pt; text-transform: uppercase; font-weight: 700; color: #64748b; letter-spacing: 0.05em; margin-bottom: 4px; }
    .card-value { font-size: 12pt; font-weight: 800; color: #0f172a; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .font-bold { font-weight: 700; }
  ';
};

$applyOrgToSheet = function(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $org, int $startRow = 1): int {
  $row = $startRow;
  $sheet->setCellValue('A' . $row, (string)($org['name'] ?? ''));
  $sheet->mergeCells('A' . $row . ':I' . $row);
  $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(16)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('0F172A'));
  $row++;

  $rawTitle = trim((string)($org['title'] ?? ''));
  if ($rawTitle !== '' && strtoupper($rawTitle) !== 'CEO') {
    $sheet->setCellValue('A' . $row, (string)$rawTitle);
    $sheet->mergeCells('A' . $row . ':I' . $row);
    $sheet->getStyle('A' . $row)->getFont()->setSize(10)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'));
    $row++;
  }

  $contacts = [];
  if (trim((string)($org['phone'] ?? '')) !== '') $contacts[] = 'Office: ' . (string)$org['phone'];
  if (trim((string)($org['sim'] ?? '')) !== '') $contacts[] = 'Sim: ' . (string)$org['sim'];
  if (!empty($contacts)) {
    $sheet->setCellValue('A' . $row, implode(' | ', $contacts));
    $sheet->mergeCells('A' . $row . ':I' . $row);
    $sheet->getStyle('A' . $row)->getFont()->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'));
    $row++;
  }
  
  $row++; // Spacer
  return $row;
};

$sendXlsx = function(string $filename, string $sheetName, array $headers, array $rows, ?callable $orgApplier = null, ?array $org = null) {
  if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
    throw new \Exception('PhpSpreadsheet library is missing.');
  }
  while (ob_get_level()) {
    ob_end_clean();
  }
  $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
  $sheet = $spreadsheet->getActiveSheet();
  $sheet->setTitle($sheetName);
  $sheet->setShowGridlines(true);

  $startRow = 1;
  if ($orgApplier && $org) {
    $startRow = $orgApplier($sheet, $org, 1);
  }

  // Write Headers
  $sheet->fromArray($headers, null, 'A' . $startRow);
  $lastColIndex = count($headers);
  $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastColIndex);
  
  // Format Header Styles
  $headerRange = 'A' . $startRow . ':' . $lastCol . $startRow;
  $sheet->getStyle($headerRange)->applyFromArray([
    'font' => [
      'bold' => true,
      'color' => ['rgb' => 'FFFFFF'],
      'size' => 10,
      'name' => 'Segoe UI',
    ],
    'fill' => [
      'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
      'startColor' => ['rgb' => '0F172A'],
    ],
    'alignment' => [
      'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
    ],
  ]);
  $sheet->getRowDimension($startRow)->setRowHeight(26);

  // Populate Rows
  if (!empty($rows)) {
    $dataStartRow = $startRow + 1;
    $sheet->fromArray($rows, null, 'A' . $dataStartRow);
    $dataEndRow = $dataStartRow + count($rows) - 1;

    // Zebra Striping & Padding Styles
    for ($r = $dataStartRow; $r <= $dataEndRow; $r++) {
      $sheet->getRowDimension($r)->setRowHeight(20);
      if ($r % 2 === 0) {
        $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->getFill()
          ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
          ->getStartColor()->setRGB('F8FAFC');
      }
    }

    // Apply Auto Filter
    $sheet->setAutoFilter('A' . $startRow . ':' . $lastCol . $dataEndRow);
  }

  // Auto-fit Column Widths with Extra Padding
  for ($i = 1; $i <= $lastColIndex; $i++) {
    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
    $sheet->getColumnDimension($col)->setAutoSize(true);
  }

  $sheet->freezePane('A' . ($startRow + 1));

  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  header('Cache-Control: max-age=0');
  $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
  $writer->save('php://output');
  exit;
};

$sendPdf = function(string $filename, string $title, string $html) use ($getGlobalPdfCss) {
  if (!class_exists('Mpdf\\Mpdf')) {
    throw new \Exception('mPDF library is missing.');
  }
  while (ob_get_level()) {
    ob_end_clean();
  }
  $mpdf = new \Mpdf\Mpdf([
    'tempDir' => sys_get_temp_dir(),
    'margin_left' => 12,
    'margin_right' => 12,
    'margin_top' => 14,
    'margin_bottom' => 16,
    'margin_header' => 6,
    'margin_footer' => 6,
    'orientation' => 'P',
  ]);

  $mpdf->SetTitle($title);
  $mpdf->SetHTMLFooter('
    <table width="100%" style="font-family: sans-serif; font-size: 8pt; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 6px;">
      <tr>
        <td width="50%">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</td>
        <td width="50%" align="right">Page {PAGENO} of {nbpg}</td>
      </tr>
    </table>
  ');

  $fullHtml = '<html><head><style>' . $getGlobalPdfCss() . '</style></head><body>' . $html . '</body></html>';
  $mpdf->WriteHTML($fullHtml);

  header('Content-Type: application/pdf');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  $mpdf->Output($filename, 'D');
  exit;
};

$listFilters = function(): array {
  return [
    'find' => trim((string)($_GET['find'] ?? '')),
    'status' => strtolower(trim((string)($_GET['status'] ?? ''))),
    'scope' => strtolower(trim((string)($_GET['scope'] ?? 'all'))),
    'pay_to' => trim((string)($_GET['pay_to'] ?? '')),
    'assigned' => trim((string)($_GET['assigned'] ?? '')),
    'created_by' => trim((string)($_GET['created_by'] ?? '')),
    'period' => strtolower(trim((string)($_GET['period'] ?? '30d'))),
    'start' => trim((string)($_GET['start'] ?? '')),
    'end' => trim((string)($_GET['end'] ?? '')),
    'network' => trim((string)($_GET['network'] ?? '')),
    'commission_type' => trim((string)($_GET['commission_type'] ?? '')),
    'date_from' => trim((string)($_GET['date_from'] ?? '')),
    'date_to' => trim((string)($_GET['date_to'] ?? '')),
    'remarks' => strtolower(trim((string)($_GET['remarks'] ?? ''))),
    'transaction_type' => strtolower(trim((string)($_GET['transaction_type'] ?? ''))),
    'bank_name' => strtolower(trim((string)($_GET['bank_name'] ?? ''))),
  ];
};

$userNameMap = function(array $ids) use ($db): array {
  $ids = array_values(array_unique(array_filter(array_map(function($v){
    return (int)$v;
  }, $ids), function($v){
    return $v > 0;
  })));
  if (empty($ids)) {
    return [];
  }
  $in = implode(',', array_map('intval', $ids));
  try {
    $rows = $db->query("SELECT id, first_name, last_name FROM users WHERE deleted = 0 AND id IN ($in)") ?: [];
    $map = [];
    foreach ($rows as $r) {
      $uid = (int)($r->id ?? 0);
      if ($uid <= 0) continue;
      $name = trim((string)($r->first_name ?? '') . ' ' . (string)($r->last_name ?? ''));
      $map[$uid] = $name !== '' ? $name : (string)$uid;
    }
    return $map;
  } catch (\Throwable $e) {
    return [];
  }
};

$agentServicesListFilters = function(): array {
  return [
    'search' => trim((string)($_GET['search'] ?? '')),
    'status' => strtolower(trim((string)($_GET['status'] ?? ''))),
    'provider' => strtolower(trim((string)($_GET['provider'] ?? ''))),
    'user' => (int)($_GET['user'] ?? 0),
    'start_date' => trim((string)($_GET['start_date'] ?? '')),
    'end_date' => trim((string)($_GET['end_date'] ?? '')),
  ];
};

$fetchAgentServicesList = function(array $filters) use ($db, $userId): array {
  $params = [];
  $conditions = ['(m.deleted IS NULL OR m.deleted = 0)'];
  $canViewAll = user_can('is_admin');
  if (!$canViewAll) {
    $conditions[] = '(m.creator_id = :user_id OR m.assigned_to = :user_id OR m.updated_by = :user_id)';
    $params['user_id'] = $userId;
  }
  if ($filters['search'] !== '') {
    $conditions[] = "(
      CAST(m.id AS CHAR) LIKE :search OR
      LOWER(COALESCE(m.customer_type, '')) LIKE :search OR
      LOWER(COALESCE(m.service_type, '')) LIKE :search OR
      LOWER(COALESCE(m.provider, '')) LIKE :search OR
      LOWER(COALESCE(m.paying_network, '')) LIKE :search OR
      LOWER(COALESCE(m.receiving_network, '')) LIKE :search OR
      LOWER(COALESCE(m.agent_name, '')) LIKE :search OR
      LOWER(COALESCE(m.agent_contact, '')) LIKE :search OR
      LOWER(COALESCE(m.business_name, '')) LIKE :search OR
      LOWER(COALESCE(m.agent_number, '')) LIKE :search OR
      LOWER(COALESCE(m.status, '')) LIKE :search
    )";
    $params['search'] = '%' . strtolower($filters['search']) . '%';
  }
  if ($filters['status'] !== '') {
    $conditions[] = "LOWER(COALESCE(m.status, '')) = :status";
    $params['status'] = $filters['status'];
  }
  if ($filters['provider'] !== '') {
    $conditions[] = "(
      LOWER(COALESCE(m.provider, '')) LIKE :provider OR
      LOWER(COALESCE(m.paying_network, '')) LIKE :provider OR
      LOWER(COALESCE(m.receiving_network, '')) LIKE :provider
    )";
    $params['provider'] = '%' . $filters['provider'] . '%';
  }
  if ($filters['user'] > 0) {
    $conditions[] = "(
      m.creator_id = :filter_user OR
      m.assigned_to = :filter_user OR
      m.updated_by = :filter_user
    )";
    $params['filter_user'] = $filters['user'];
  }
  if ($filters['start_date'] !== '') {
    $conditions[] = 'DATE(m.date_created) >= :start_date';
    $params['start_date'] = $filters['start_date'];
  }
  if ($filters['end_date'] !== '') {
    $conditions[] = 'DATE(m.date_created) <= :end_date';
    $params['end_date'] = $filters['end_date'];
  }
  $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

  $sql = "SELECT m.*, 
         c.first_name AS creator_first_name, c.last_name AS creator_last_name,
         a.first_name AS assigned_first_name, a.last_name AS assigned_last_name,
         u.first_name AS updated_by_first_name, u.last_name AS updated_by_last_name
      FROM agent_services AS m
      LEFT JOIN users AS c ON m.creator_id = c.id
      LEFT JOIN users AS a ON m.assigned_to = a.id
      LEFT JOIN users AS u ON m.updated_by = u.id
      {$where}
      ORDER BY m.id DESC
      LIMIT 5000";

  return $db->query($sql, $params) ?: [];
};

$fetchRequestsList = function(array $filters) use ($db, $userId): array {
  $params = [];
  $conditions = [];
  $scope = $filters['scope'] ?? 'all';
  $canViewAll = user_can('is_admin');
  if ($scope === 'created') {
    $conditions[] = 'm.created_by = :user_id';
    $params['user_id'] = $userId;
  } elseif ($scope === 'assigned') {
    $conditions[] = 'm.assigned = :user_id';
    $params['user_id'] = $userId;
  } elseif (!$canViewAll) {
    $conditions[] = '(m.created_by = :user_id OR m.assigned = :user_id)';
    $params['user_id'] = $userId;
  }
  $createdByFilter = (int)($filters['created_by'] ?? 0);
  $assignedFilter = (int)($filters['assigned'] ?? ($filters['pay_to'] ?? 0));
  if ($createdByFilter > 0) {
    $conditions[] = 'm.created_by = :created_by';
    $params['created_by'] = $createdByFilter;
  }
  if ($assignedFilter > 0) {
    $conditions[] = 'm.assigned = :assigned';
    $params['assigned'] = $assignedFilter;
  }
  if (!empty($filters['find'])) {
    $conditions[] = '(
      m.id LIKE :find OR
      m.wallet_number LIKE :find OR
      m.till_number LIKE :find OR
      m.request_type LIKE :find OR
      m.network LIKE :find OR
      m.transaction_status LIKE :find OR
      m.created_by LIKE :find OR
      m.assigned LIKE :find OR
      u.first_name LIKE :find OR
      u.last_name LIKE :find OR
      CONCAT(COALESCE(u.first_name, \'\'), \' \', COALESCE(u.last_name, \'\')) LIKE :find OR
      u_assigned.first_name LIKE :find OR
      u_assigned.last_name LIKE :find OR
      CONCAT(COALESCE(u_assigned.first_name, \'\'), \' \', COALESCE(u_assigned.last_name, \'\')) LIKE :find
    )';
    $params['find'] = '%' . $filters['find'] . '%';
  }
  if (!empty($filters['status']) && $filters['status'] !== 'all') {
    $conditions[] = 'LOWER(m.transaction_status) = :status';
    $params['status'] = $filters['status'];
  }
  $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
  $sql = "SELECT m.*
    FROM requests m
    LEFT JOIN users AS u ON m.created_by = u.id
    LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id
    {$where}
    ORDER BY m.id DESC
    LIMIT 5000";
  return $db->query($sql, $params) ?: [];
};

$fetchPaymentsList = function(array $filters) use ($db, $userId): array {
  $params = ['user_id' => $userId];
  $conditions = [];
  $scope = $filters['scope'] ?? 'all';
  $canViewAll = user_can('is_admin') || user_can('view_payment');
  if (!$canViewAll) {
    if ($scope === 'created') {
      $conditions[] = 'm.created_by = :user_id';
    } elseif ($scope === 'pay_to') {
      $conditions[] = 'm.pay_to = :user_id';
    } else {
      $conditions[] = '(m.created_by = :user_id OR m.pay_to = :user_id)';
    }
  } else {
    if ($scope === 'created') {
      $conditions[] = 'm.created_by = :user_id';
    } elseif ($scope === 'pay_to') {
      $conditions[] = 'm.pay_to = :user_id';
    } elseif ($scope === 'all') {
      // export all
    } else {
      $conditions[] = '(m.created_by = :user_id OR m.pay_to = :user_id)';
    }
  }
  if (!empty($filters['find'])) {
    $conditions[] = '(m.id LIKE :find OR m.description LIKE :find OR m.network LIKE :find OR m.payment_type LIKE :find OR m.transaction_status LIKE :find)';
    $params['find'] = '%' . $filters['find'] . '%';
  }
  if (!empty($filters['status']) && $filters['status'] !== 'all') {
    $conditions[] = 'LOWER(m.transaction_status) = :status';
    $params['status'] = $filters['status'];
  }
  $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
  $sql = "SELECT m.* FROM payments_backoffice m {$where} ORDER BY m.id DESC LIMIT 5000";
  $paramsUsed = $params;
  if (strpos($sql, ':user_id') === false) {
    unset($paramsUsed['user_id']);
  }
  if (strpos($sql, ':find') === false) {
    unset($paramsUsed['find']);
  }
  if (strpos($sql, ':status') === false) {
    unset($paramsUsed['status']);
  }
  $res = $db->query($sql, $paramsUsed) ?: [];
  if (!empty($db->has_error)) {
    throw new \Exception('Payments export query failed: ' . (string)($db->error ?? 'Unknown DB error'));
  }
  return $res;
};

$fetchCommissionsList = function(array $filters) use ($db, $userId, $session): array {
  $commissionColumns = function_exists('backoffice_table_columns') ? backoffice_table_columns($db, 'commissions') : [];
  $hasBulkParentId = !empty($commissionColumns['bulk_parent_id']);
  $hasAssigned = !empty($commissionColumns['assigned']);
  $assignedExpr = $hasAssigned ? 'COALESCE(m.assigned, m.pay_to)' : 'm.pay_to';
  $params = [];
  $conditions = ['(m.disabled = 0 OR m.disabled IS NULL)', '(m.deleted = 0 OR m.deleted IS NULL)'];
  if ($hasBulkParentId) {
    $conditions[] = 'm.bulk_parent_id IS NULL';
  }

  $period = strtolower(trim((string)($filters['period'] ?? '30d')));
  $start = trim((string)($filters['start'] ?? ''));
  $end = trim((string)($filters['end'] ?? ''));
  if ($start !== '' && $end !== '') {
    $params['d_start'] = date('Y-m-d 00:00:00', strtotime($start));
    $params['d_end'] = date('Y-m-d 00:00:00', strtotime($end . ' +1 day'));
  } else {
    $today0 = date('Y-m-d 00:00:00');
    $tomorrow0 = date('Y-m-d 00:00:00', strtotime('+1 day'));
    if ($period === 'today') {
      $params['d_start'] = $today0;
    } elseif ($period === '7d' || $period === 'week') {
      $params['d_start'] = date('Y-m-d 00:00:00', strtotime('-6 day'));
    } elseif ($period === '90d' || $period === 'quarter' || $period === 'quarterly') {
      $params['d_start'] = date('Y-m-d 00:00:00', strtotime('-89 day'));
    } elseif ($period === 'ytd') {
      $params['d_start'] = date('Y-01-01 00:00:00');
    } elseif ($period === '365d' || $period === 'annual' || $period === 'year') {
      $params['d_start'] = date('Y-m-d 00:00:00', strtotime('-364 day'));
    } else {
      $params['d_start'] = date('Y-m-d 00:00:00', strtotime('-29 day'));
    }
    $params['d_end'] = $tomorrow0;
  }
  $conditions[] = 'm.date_created >= :d_start AND m.date_created < :d_end';

  $scope = $filters['scope'] ?? 'mine';
  $canViewAll = function_exists('backoffice_commission_can_view_all')
    ? backoffice_commission_can_view_all($session)
    : user_can('is_admin');
  if ($scope === 'created') {
    $conditions[] = 'm.created_by = :user_id';
    $params['user_id'] = $userId;
  } elseif ($scope === 'pay_to') {
    $conditions[] = $assignedExpr . ' = :user_id';
    $params['user_id'] = $userId;
  } elseif ($canViewAll) {
    // export all for admins/viewers (default mine/all)
  } else {
    $conditions[] = '(m.created_by = :user_id OR ' . $assignedExpr . ' = :user_id)';
    $params['user_id'] = $userId;
  }
  if (!empty($filters['find'])) {
    $conditions[] = '(
      m.id LIKE :find OR
      m.description LIKE :find OR
      m.network LIKE :find OR
      m.commission_type LIKE :find OR
      m.transaction_status LIKE :find OR
      u.first_name LIKE :find OR
      u.last_name LIKE :find OR
      payto.first_name LIKE :find OR
      payto.last_name LIKE :find OR
      assigned_user.first_name LIKE :find OR
      assigned_user.last_name LIKE :find
    )';
    $params['find'] = '%' . $filters['find'] . '%';
  }
  if (!empty($filters['status']) && $filters['status'] !== 'all') {
    if ($filters['status'] === 'approved') {
      $conditions[] = "LOWER(m.transaction_status) IN ('approved','complete','completed')";
    } else {
      $conditions[] = 'LOWER(m.transaction_status) = :status';
      $params['status'] = $filters['status'];
    }
  }
  if (!empty($filters['network']) && $filters['network'] !== 'all') {
    $conditions[] = 'm.network = :network';
    $params['network'] = $filters['network'];
  }
  if (!empty($filters['commission_type']) && $filters['commission_type'] !== 'all') {
    $conditions[] = 'm.commission_type = :commission_type';
    $params['commission_type'] = $filters['commission_type'];
  }
  if (!empty($filters['pay_to']) && $canViewAll) {
    $conditions[] = $assignedExpr . ' = :pay_to';
    $params['pay_to'] = (int)$filters['pay_to'];
  }
  if (!empty($filters['created_by']) && $canViewAll) {
    $conditions[] = 'm.created_by = :created_by';
    $params['created_by'] = (int)$filters['created_by'];
  }
  if (function_exists('backoffice_commission_can_access_bank_sims') && !backoffice_commission_can_access_bank_sims($session) && function_exists('backoffice_commission_non_bank_sql_clause')) {
    $conditions[] = backoffice_commission_non_bank_sql_clause('m', $commissionColumns);
  }
  $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
  $assignedSelect = $hasAssigned ? 'm.assigned' : 'NULL AS assigned';
  $sql = "
    SELECT
      m.*,
      {$assignedSelect},
      u.first_name AS user_first_name,
      u.last_name AS user_last_name,
      payto.first_name AS payto_first_name,
      payto.last_name AS payto_last_name,
      assigned_user.first_name AS assigned_first_name,
      assigned_user.last_name AS assigned_last_name
    FROM commissions m
    LEFT JOIN users AS u ON m.created_by = u.id
    LEFT JOIN users AS payto ON m.pay_to = payto.id
    LEFT JOIN users AS assigned_user ON {$assignedExpr} = assigned_user.id
    {$where}
    ORDER BY m.id DESC
    LIMIT 5000
  ";
  $rows = $db->query($sql, $params) ?: [];
  if (!empty($db->has_error)) {
    throw new \Exception('Commissions export query failed: ' . (string)($db->error ?? 'Unknown DB error'));
  }
  return $rows;
};

$commissionAnalyticsFromRows = function(array $rows): array {
  $kpi = [
    'total_rows' => 0,
    'total_amount' => 0.0,
    'total_amount_ghs' => 0.0,
    'total_amount_cfa' => 0.0,
    'approved_count' => 0,
    'pending_count' => 0,
    'rejected_count' => 0,
    'approved_amount' => 0.0,
    'pending_amount' => 0.0,
    'rejected_amount' => 0.0,
  ];
  $byNetwork = [];
  $byType = [];
  $bySource = [];
  $byPayTo = [];
  $byCreated = [];

  $addBucket = static function(array &$buckets, string $key, float $amount): void {
    $key = trim($key) !== '' ? trim($key) : 'Unknown';
    if (!isset($buckets[$key])) {
      $buckets[$key] = ['label' => $key, 'total_rows' => 0, 'total_amount' => 0.0];
    }
    $buckets[$key]['total_rows']++;
    $buckets[$key]['total_amount'] += $amount;
  };

  foreach ($rows as $row) {
    $amount = (float)($row->amount ?? 0);
    $status = strtolower(trim((string)($row->transaction_status ?? '')));
    $network = trim((string)($row->network ?? ''));
    $type = trim((string)($row->commission_type ?? ''));

    $kpi['total_rows']++;
    if ($status === 'rejected') {
      $kpi['rejected_count']++;
      $kpi['rejected_amount'] += $amount;
      continue;
    }

    $kpi['total_amount'] += $amount;
    if (stripos($network, 'CFA') !== false) {
      $kpi['total_amount_cfa'] += $amount;
    } else {
      $kpi['total_amount_ghs'] += $amount;
    }
    if (in_array($status, ['approved', 'complete', 'completed'], true)) {
      $kpi['approved_count']++;
      $kpi['approved_amount'] += $amount;
    } elseif ($status === 'pending') {
      $kpi['pending_count']++;
      $kpi['pending_amount'] += $amount;
    }

    $addBucket($byType, $type !== '' ? $type : 'Unknown', $amount);
    if (function_exists('backoffice_commission_source_label_from_parts')) {
      $sourceLabel = backoffice_commission_source_label_from_parts($network, $type, (string)($row->commission_field ?? ''), []);
    } else {
      $sourceLabel = trim(($network !== '' ? $network . ' ' : '') . strtoupper($type !== '' ? $type : 'Commission'));
    }
    $addBucket($bySource, $sourceLabel, $amount);

    $networkSplitAdded = false;
    $rawSnapshot = trim((string)($row->assignment_snapshot_json ?? ''));
    $decoded = $rawSnapshot !== '' ? json_decode($rawSnapshot, true) : null;
    if ((strtoupper($type) === 'OTHER SIM COMMISSION BULK' || strtoupper($network) === 'MULTI') && is_array($decoded)) {
      $items = (array_key_exists('network', $decoded) || array_key_exists('sim_type', $decoded)) ? [$decoded] : $decoded;
      foreach ($items as $item) {
        if (!is_array($item)) {
          continue;
        }
        $snapshot = [];
        if (!empty($item['assignment']) && is_array($item['assignment'])) {
          $snapshot = $item['assignment'];
        } elseif (array_key_exists('network', $item) || array_key_exists('sim_type', $item)) {
          $snapshot = $item;
        }
        $networkLabel = trim((string)($snapshot['network'] ?? ''));
        if ($networkLabel === '') {
          continue;
        }
        $itemAmount = isset($item['amount']) ? (float)$item['amount'] : $amount;
        if ($itemAmount <= 0 && $amount > 0 && count($items) === 1) {
          $itemAmount = $amount;
        }
        $addBucket($byNetwork, $networkLabel, $itemAmount);
        $networkSplitAdded = true;
      }
    }
    if (!$networkSplitAdded) {
      $addBucket($byNetwork, $network !== '' ? $network : 'Unknown', $amount);
    }

    $createdName = trim((string)(($row->user_first_name ?? '') . ' ' . ($row->user_last_name ?? '')));
    if ($createdName === '') {
      $createdName = 'User #' . (int)($row->created_by ?? 0);
    }
    $payToName = trim((string)(($row->assigned_first_name ?? '') . ' ' . ($row->assigned_last_name ?? '')));
    if ($payToName === '') {
      $payToName = trim((string)(($row->payto_first_name ?? '') . ' ' . ($row->payto_last_name ?? '')));
    }
    if ($payToName === '') {
      $payToName = 'User #' . (int)($row->assigned ?? ($row->pay_to ?? 0));
    }
    $addBucket($byCreated, $createdName, $amount);
    $addBucket($byPayTo, $payToName, $amount);
  }

  $sortAmountDesc = static function(array &$items): void {
    usort($items, static function(array $a, array $b): int {
      $amountCompare = (float)$b['total_amount'] <=> (float)$a['total_amount'];
      if ($amountCompare !== 0) return $amountCompare;
      return strcasecmp((string)$a['label'], (string)$b['label']);
    });
  };
  $byNetwork = array_values($byNetwork);
  $byType = array_values($byType);
  $bySource = array_values($bySource);
  $byPayTo = array_values($byPayTo);
  $byCreated = array_values($byCreated);
  $sortAmountDesc($byNetwork);
  $sortAmountDesc($byType);
  usort($bySource, static function(array $a, array $b): int {
    return strcasecmp((string)$a['label'], (string)$b['label']);
  });
  $sortAmountDesc($byPayTo);
  $sortAmountDesc($byCreated);

  return [
    'kpi' => $kpi,
    'by_network' => array_slice($byNetwork, 0, 30),
    'by_commission_type' => array_slice($byType, 0, 30),
    'by_source_revenue' => array_slice($bySource, 0, 50),
    'top_pay_to' => array_slice($byPayTo, 0, 30),
    'top_created_by' => array_slice($byCreated, 0, 30),
  ];
};

$renderCommissionAnalyticsTable = function(string $title, array $rows, string $firstHeader = 'Label', float $grandTotal = 0.0) use ($escape): string {
  $html = '<h3>' . $escape($title) . '</h3>';
  $html .= '<table class="table-modern">';
  $html .= '<thead><tr><th>' . $escape($firstHeader) . '</th><th class="text-right">Total</th>';
  if ($grandTotal > 0) {
    $html .= '<th class="text-right">%</th>';
  }
  $html .= '<th class="text-right">Count</th></tr></thead><tbody>';
  if (empty($rows)) {
    $colspan = $grandTotal > 0 ? 4 : 3;
    $html .= '<tr><td colspan="' . $colspan . '" class="text-center" style="color:#94a3b8">No data available</td></tr>';
  } else {
    foreach ($rows as $row) {
      $total = (float)($row['total_amount'] ?? 0);
      $html .= '<tr>';
      $html .= '<td class="font-bold">' . $escape((string)($row['label'] ?? '')) . '</td>';
      $html .= '<td class="text-right">' . $escape(number_format($total, 2)) . '</td>';
      if ($grandTotal > 0) {
        $html .= '<td class="text-right">' . $escape(number_format(($total / $grandTotal) * 100, 1)) . '%</td>';
      }
      $html .= '<td class="text-right">' . $escape((string)(int)($row['total_rows'] ?? 0)) . '</td>';
      $html .= '</tr>';
    }
  }
  $html .= '</tbody></table>';
  return $html;
};

$fetchBankTransfersList = function(array $filters) use ($db, $userId, $session): array {
  $params = [];
  $conditions = ['(m.deleted IS NULL OR m.deleted = 0)'];
  $canViewAll = function_exists('backoffice_user_can_bank_transfer_third_party')
    ? backoffice_user_can_bank_transfer_third_party($session)
    : user_can('is_admin');

  if (!$canViewAll) {
    $conditions[] = '(m.created_by = :user_id OR m.withdrawing_user_id = :user_id OR m.second_party_user_id = :user_id OR m.third_party_user_id = :user_id)';
    $params['user_id'] = $userId;
  }

  if (!empty($filters['find'])) {
    $conditions[] = "(
      CAST(m.id AS CHAR) LIKE :find
      OR CAST(m.amount AS CHAR) LIKE :find
      OR CAST(m.date_created AS CHAR) LIKE :find
      OR m.transaction_type LIKE :find
      OR m.transfer_category LIKE :find
      OR m.bank_name LIKE :find
      OR m.network_name LIKE :find
      OR m.transaction_status LIKE :find
      OR m.third_party_reject_reason LIKE :find
      OR CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) LIKE :find
      OR CONCAT(COALESCE(uw.first_name,''),' ',COALESCE(uw.last_name,'')) LIKE :find
      OR CONCAT(COALESCE(u2.first_name,''),' ',COALESCE(u2.last_name,'')) LIKE :find
      OR CONCAT(COALESCE(u3.first_name,''),' ',COALESCE(u3.last_name,'')) LIKE :find
    )";
    $params['find'] = '%' . $filters['find'] . '%';
  }
  if (!empty($filters['status']) && $filters['status'] !== 'all') {
    $conditions[] = 'LOWER(COALESCE(m.transaction_status, \'\')) = :status';
    $params['status'] = $filters['status'];
  }
  if (!empty($filters['transaction_type']) && $filters['transaction_type'] !== 'all') {
    $conditions[] = 'LOWER(COALESCE(m.transaction_type, \'\')) = :transaction_type';
    $params['transaction_type'] = $filters['transaction_type'];
  }
  if (!empty($filters['bank_name']) && $filters['bank_name'] !== 'all') {
    $conditions[] = 'LOWER(TRIM(COALESCE(m.bank_name, \'\'))) = :bank_name';
    $params['bank_name'] = $filters['bank_name'];
  }

  $where = 'WHERE ' . implode(' AND ', $conditions);
  $sql = "SELECT m.*,
      u.first_name AS creator_first_name,
      u.last_name AS creator_last_name,
      uw.first_name AS withdrawer_first_name,
      uw.last_name AS withdrawer_last_name,
      u2.first_name AS second_party_first_name,
      u2.last_name AS second_party_last_name,
      u3.first_name AS third_party_first_name,
      u3.last_name AS third_party_last_name
    FROM bank_transfers_backoffice m
    LEFT JOIN users u ON m.created_by = u.id
    LEFT JOIN users uw ON m.withdrawing_user_id = uw.id
    LEFT JOIN users u2 ON m.second_party_user_id = u2.id
    LEFT JOIN users u3 ON m.third_party_user_id = u3.id
    {$where}
    ORDER BY m.id DESC
    LIMIT 5000";

  return $db->query($sql, $params) ?: [];
};

$reconciliationRemarkInfo = function($row): array {
  $miscBalance = (float)($row->misc_balance ?? 0);
  if (abs($miscBalance) >= 0.01) {
    return [
      'key' => $miscBalance > 0 ? 'shortage' : 'overage',
      'label' => ($miscBalance > 0 ? 'Shortage' : 'Overage') . ' (' . number_format(abs($miscBalance), 2) . ')',
    ];
  }
  $remarks = trim((string)($row->remarks ?? ''));
  if (strcasecmp($remarks, 'Balanced') === 0) {
    return ['key' => 'balanced', 'label' => 'Balanced'];
  }
  return ['key' => 'not-balanced', 'label' => $remarks !== '' ? $remarks : 'Not Balanced'];
};

$fetchReconciliationsList = function(array $filters) use ($db, $userId, $session, $reconciliationRemarkInfo): array {
  $params = [];
  $conditions = [];
  $canSeeAll = function_exists('backoffice_user_can_oversee_reconciliations')
    ? backoffice_user_can_oversee_reconciliations($session)
    : user_can('is_admin');
  if (!$canSeeAll) {
    $conditions[] = 'm.created_by = :user_id';
    $params['user_id'] = (string)$userId;
  }

  $miscSelect = '0 AS misc_balance';
  $miscJoin = '';
  $lateSelect = 'NULL AS late_access_id_resolved, NULL AS late_cashdrawer_date, NULL AS late_submitted_at';
  $lateJoin = '';
  if ($db->table_exists(['reconciliations'])) {
    $miscSelect = 'COALESCE(misc.balance, 0) AS misc_balance';
    $miscJoin = "LEFT JOIN reconciliations AS misc ON misc.session_id = m.session_id AND misc.wallet_id = 'misc'";
  }
  if ($db->table_exists(['reconciliation_late_access'])) {
    $summaryColumns = function_exists('backoffice_table_columns') ? backoffice_table_columns($db, 'reconciliations_summary') : [];
    $exactLateJoin = !empty($summaryColumns['late_access_id'])
      ? "LEFT JOIN reconciliation_late_access AS la_exact ON la_exact.id = m.late_access_id"
      : "";
    $lateSelect = "COALESCE(la_exact.id, la_fallback.id) AS late_access_id_resolved, COALESCE(la_exact.drawer_date, la_fallback.drawer_date) AS late_cashdrawer_date, COALESCE(la_exact.used_at, la_fallback.used_at) AS late_submitted_at";
    if (empty($summaryColumns['late_access_id'])) {
      $lateSelect = "la_fallback.id AS late_access_id_resolved, la_fallback.drawer_date AS late_cashdrawer_date, la_fallback.used_at AS late_submitted_at";
    }
    $lateJoin = "{$exactLateJoin}
      LEFT JOIN (
        SELECT la1.*
        FROM reconciliation_late_access AS la1
        INNER JOIN (
          SELECT user_id, drawer_date, MAX(id) AS max_id
          FROM reconciliation_late_access
          WHERE LOWER(COALESCE(status,'')) = 'used'
          GROUP BY user_id, drawer_date
        ) la2 ON la2.max_id = la1.id
      ) AS la_fallback ON la_fallback.user_id = m.created_by AND DATE(la_fallback.drawer_date) = DATE(m.date_created)";
  }

  $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
  $rows = $db->query(
    "SELECT m.*,
            u.first_name AS user_first_name,
            u.last_name AS user_last_name,
            u.image AS user_image,
            {$miscSelect},
            {$lateSelect}
     FROM reconciliations_summary AS m
     LEFT JOIN users AS u ON m.created_by = u.id
     {$miscJoin}
     {$lateJoin}
     {$where}
     ORDER BY m.id DESC
     LIMIT 5000",
    $params
  ) ?: [];

  $term = strtolower(trim((string)($filters['find'] ?? '')));
  $status = strtolower(trim((string)($filters['status'] ?? '')));
  $remarks = strtolower(trim((string)($filters['remarks'] ?? '')));
  $dateFrom = trim((string)($filters['date_from'] ?? ''));
  $dateTo = trim((string)($filters['date_to'] ?? ''));

  return array_values(array_filter($rows, function($row) use ($term, $status, $remarks, $dateFrom, $dateTo, $reconciliationRemarkInfo): bool {
    $statusLabel = trim((string)($row->transaction_status ?? 'Submitted'));
    if ($statusLabel === '') $statusLabel = 'Submitted';
    $statusKey = strtolower($statusLabel);
    $info = $reconciliationRemarkInfo($row);
    $isLate = !empty($row->late_access_id_resolved) || !empty($row->late_access_id) || !empty($row->late_submitted_at);
    $cashdrawerDateRaw = $isLate && !empty($row->late_cashdrawer_date)
      ? (string)$row->late_cashdrawer_date
      : (string)($row->date_created ?? '');
    $submittedDateRaw = $isLate && !empty($row->late_submitted_at)
      ? (string)$row->late_submitted_at
      : (string)($row->date_created ?? '');
    $rowDate = $cashdrawerDateRaw !== '' ? date('Y-m-d', strtotime($cashdrawerDateRaw)) : '';
    $agent = trim((string)($row->user_first_name ?? '') . ' ' . (string)($row->user_last_name ?? ''));
    $blob = strtolower(trim($agent . ' ' . $statusLabel . ' ' . $cashdrawerDateRaw . ' ' . $submittedDateRaw . ' ' . ($isLate ? 'Late access' : 'Normal') . ' ' . $info['key'] . ' ' . $info['label'] . ' ' . (string)($row->remarks ?? '') . ' ' . (string)($row->rejection_reason ?? '')));

    if ($term !== '' && strpos($blob, $term) === false) return false;
    if ($status !== '' && $status !== 'all' && $statusKey !== $status) return false;
    if ($remarks !== '' && $remarks !== 'all' && $info['key'] !== $remarks) return false;
    if ($dateFrom !== '' && $rowDate !== '' && $rowDate < $dateFrom) return false;
    if ($dateTo !== '' && $rowDate !== '' && $rowDate > $dateTo) return false;
    return true;
  }));
};

$fetchReconciliationView = function(int $id) use ($db, $userId, $session): array {
  if ($id <= 0) return [];
  $summaryRows = $db->query(
    "SELECT m.*, u.first_name AS user_first_name, u.last_name AS user_last_name, u.image AS user_image
     FROM reconciliations_summary AS m
     LEFT JOIN users AS u ON m.created_by = u.id
     WHERE m.id = :id
     LIMIT 1",
    ['id' => $id]
  ) ?: [];
  if (empty($summaryRows)) {
    $summaryRows = $db->query(
      "SELECT m.*, u.first_name AS user_first_name, u.last_name AS user_last_name, u.image AS user_image
       FROM reconciliations_summary AS m
       LEFT JOIN users AS u ON m.created_by = u.id
       WHERE m.session_id = :session_id
       ORDER BY m.id DESC
       LIMIT 1",
      ['session_id' => (string)$id]
    ) ?: [];
  }
  $summary = $summaryRows[0] ?? null;
  if (!$summary) return [];
  $canSeeAll = function_exists('backoffice_user_can_oversee_reconciliations')
    ? backoffice_user_can_oversee_reconciliations($session)
    : user_can('is_admin');
  if (!$canSeeAll && (int)($summary->created_by ?? 0) !== $userId) {
    return [];
  }
  $sessionId = (string)($summary->session_id ?? $summary->id ?? $id);
  $details = [];
  if ($db->table_exists(['reconciliations'])) {
    $details = $db->query(
      "SELECT m.*, u.first_name AS user_first_name, u.last_name AS user_last_name, u.image AS user_image
       FROM reconciliations AS m
       LEFT JOIN users AS u ON m.created_by = u.id
       WHERE m.session_id = :id
       ORDER BY m.id ASC",
      ['id' => $sessionId]
    ) ?: [];
  }
  return ['summary' => $summary, 'details' => $details];
};

$reconciliationChannelLabel = function(string $walletId): array {
  $key = strtolower(trim($walletId));
  if ($key === 'misc') return ['group' => 'Miscellaneous', 'label' => 'Miscellaneous'];
  if (strpos($key, 'cash:') === 0) return ['group' => 'Cash', 'label' => 'Cash (' . strtoupper(substr($walletId, 5)) . ')'];
  if (strpos($key, 'ecash:') === 0) return ['group' => 'E-Cash', 'label' => 'E-Cash (' . strtoupper(substr($walletId, 6)) . ')'];
  if (strpos($key, 'commission:') === 0) return ['group' => 'Commissions', 'label' => 'Commissions'];
  return ['group' => 'Other', 'label' => $walletId];
};

$fetchSingle = function(string $entity, int $id) use ($db, $userId) {
  if ($id <= 0) return null;
  if ($entity === 'requests') {
    $rows = $db->query('SELECT * FROM requests WHERE id = :id LIMIT 1', ['id' => $id]) ?: [];
    $row = $rows[0] ?? null;
    if (!$row) return null;
    if (!user_can('is_admin')) {
      $createdBy = (int)($row->created_by ?? 0);
      $assigned = (int)($row->assigned ?? 0);
      if ($createdBy !== $userId && $assigned !== $userId) {
        return null;
      }
    }
    return $row;
  }
  if ($entity === 'payments') {
    $rows = $db->query('SELECT * FROM payments_backoffice WHERE id = :id LIMIT 1', ['id' => $id]) ?: [];
    $row = $rows[0] ?? null;
    if (!$row) return null;
    $canViewAll = user_can('view_payment');
    if (!$canViewAll) {
      $createdBy = (int)($row->created_by ?? 0);
      $payTo = (int)($row->pay_to ?? 0);
      if ($createdBy !== $userId && $payTo !== $userId) {
        return null;
      }
    }
    return $row;
  }
  if ($entity === 'commissions') {
    $rows = $db->query('SELECT * FROM commissions WHERE id = :id LIMIT 1', ['id' => $id]) ?: [];
    $row = $rows[0] ?? null;
    if (!$row) return null;
    $canViewAll = user_can('is_admin');
    if (!$canViewAll) {
      $createdBy = (int)($row->created_by ?? 0);
      $payTo = (int)($row->pay_to ?? 0);
      if ($createdBy !== $userId && $payTo !== $userId) {
        return null;
      }
    }
    return $row;
  }
  if ($entity === 'bank_transfers') {
    $rows = $db->query('SELECT * FROM bank_transfers_backoffice WHERE id = :id LIMIT 1', ['id' => $id]) ?: [];
    $row = $rows[0] ?? null;
    if (!$row) return null;
    $canViewAll = function_exists('backoffice_user_can_bank_transfer_third_party')
      ? backoffice_user_can_bank_transfer_third_party(get_session())
      : user_can('is_admin');
    if (!$canViewAll) {
      $createdBy = (int)($row->created_by ?? 0);
      $withdrawer = (int)($row->withdrawing_user_id ?? 0);
      $secondParty = (int)($row->second_party_user_id ?? 0);
      $thirdParty = (int)($row->third_party_user_id ?? 0);
      if ($createdBy !== $userId && $withdrawer !== $userId && $secondParty !== $userId && $thirdParty !== $userId) {
        return null;
      }
    }
    return $row;
  }
  if ($entity === 'agent_services') {
    $rows = $db->query(
      "SELECT m.*, 
        c.first_name AS creator_first_name, c.last_name AS creator_last_name,
        a.first_name AS assigned_first_name, a.last_name AS assigned_last_name,
        u.first_name AS updated_by_first_name, u.last_name AS updated_by_last_name
       FROM agent_services AS m
       LEFT JOIN users AS c ON m.creator_id = c.id
       LEFT JOIN users AS a ON m.assigned_to = a.id
       LEFT JOIN users AS u ON m.updated_by = u.id
       WHERE m.id = :id
       LIMIT 1",
      ['id' => $id]
    ) ?: [];
    $row = $rows[0] ?? null;
    if (!$row) return null;
    if (!user_can('is_admin')) {
      $createdBy = (int)($row->creator_id ?? 0);
      $assignedTo = (int)($row->assigned_to ?? 0);
      $updatedBy = (int)($row->updated_by ?? 0);
      if ($createdBy !== $userId && $assignedTo !== $userId && $updatedBy !== $userId) {
        return null;
      }
    }
    return $row;
  }
  return null;
};

try {
  $stamp = date('Y-m-d_H-i-s');
  $org = $getOrg();

  if (!in_array($entity, ['requests', 'payments', 'commissions', 'bank_transfers', 'reconciliations', 'agent_services'], true)) {
    throw new \Exception('Unknown entity');
  }
  if (!in_array($resource, ['list', 'view', 'analytics'], true)) {
    throw new \Exception('Unknown resource');
  }
  if (!in_array($format, ['pdf', 'xlsx'], true)) {
    throw new \Exception('Unknown format');
  }
  if ($resource === 'analytics') {
    if ($entity !== 'commissions') {
      throw new \Exception('Analytics export is only available for commissions');
    }
    if ($format !== 'pdf') {
      throw new \Exception('Commission analytics export is only available as PDF');
    }
    $filters = $listFilters();
    $rows = $fetchCommissionsList($filters);
    $analytics = $commissionAnalyticsFromRows($rows);
    $kpi = $analytics['kpi'];
    $total = (float)($kpi['total_amount'] ?? 0);
    $filterBits = [];
    if (!empty($filters['period'])) $filterBits[] = 'Period: ' . $filters['period'];
    if (!empty($filters['start']) && !empty($filters['end'])) $filterBits[] = 'Range: ' . $filters['start'] . ' to ' . $filters['end'];
    if (!empty($filters['status'])) $filterBits[] = 'Status: ' . $filters['status'];
    if (!empty($filters['network'])) $filterBits[] = 'Network: ' . $filters['network'];
    if (!empty($filters['commission_type'])) $filterBits[] = 'Type: ' . $filters['commission_type'];
    if (!empty($filters['find'])) $filterBits[] = 'Search: ' . $filters['find'];

    $html = $orgHeaderHtml($org);
    $html .= '<h1>Commissions Analytics Report</h1>';
    $html .= '<div class="meta-bar">Generated: ' . $escape($formatDateHuman(date('Y-m-d H:i:s'))) . ' | Total Records: ' . (int)($kpi['total_rows'] ?? 0) . '</div>';
    if (!empty($filterBits)) {
      $html .= '<div style="font-size:8pt;color:#64748b;margin-bottom:12px;">Active Filters: ' . $escape(implode(' • ', $filterBits)) . '</div>';
    }
    
    $html .= '<table class="card-grid"><tr>';
    $html .= '<td width="25%"><div class="card"><div class="card-title">Total Revenue</div><div class="card-value">' . $escape(number_format($total, 2)) . '</div></div></td>';
    $html .= '<td width="25%"><div class="card"><div class="card-title">GHS Volume</div><div class="card-value">' . $escape(number_format((float)($kpi['total_amount_ghs'] ?? 0), 2)) . '</div></div></td>';
    $html .= '<td width="25%"><div class="card"><div class="card-title">CFA Volume</div><div class="card-value">' . $escape(number_format((float)($kpi['total_amount_cfa'] ?? 0), 2)) . '</div></div></td>';
    $html .= '<td width="25%"><div class="card"><div class="card-title">Approved Count</div><div class="card-value">' . $escape((string)(int)($kpi['approved_count'] ?? 0)) . '</div></div></td>';
    $html .= '</tr></table>';

    $html .= $renderCommissionAnalyticsTable('Commission Source Revenue', $analytics['by_source_revenue'], 'Source');
    
    $html .= '<table width="100%" cellspacing="0" cellpadding="0"><tr><td width="49%" style="vertical-align:top">';
    $html .= $renderCommissionAnalyticsTable('By Commission Type', $analytics['by_commission_type'], 'Type');
    $html .= '</td><td width="2%"></td><td width="49%" style="vertical-align:top">';
    $html .= $renderCommissionAnalyticsTable('By Network', $analytics['by_network'], 'Network');
    $html .= '</td></tr></table>';

    $html .= $renderCommissionAnalyticsTable('User Contribution Ranking', $analytics['top_created_by'], 'User', $total);

    $sendPdf('backoffice_commissions_analytics_' . $stamp . '.pdf', 'Commissions Analytics', $html);
  }

  if ($resource === 'list') {
    $filters = $entity === 'agent_services' ? $agentServicesListFilters() : $listFilters();
    
    $renderStandardPdfList = function(string $title, array $headers, array $outRows, array $statusColumnIndexes = []) use ($orgHeaderHtml, $org, $formatDateHuman, $escape, $statusBadgeHtml, $sendPdf, $stamp) {
      $html = $orgHeaderHtml($org);
      $html .= '<h1>' . $escape($title) . '</h1>';
      $html .= '<div class="meta-bar">Generated: ' . $escape($formatDateHuman(date('Y-m-d H:i:s'))) . ' | Total Records: ' . count($outRows) . '</div>';
      $html .= '<table class="table-modern">';
      $html .= '<thead><tr>';
      foreach ($headers as $h) {
        $html .= '<th>' . $escape($h) . '</th>';
      }
      $html .= '</tr></thead><tbody>';
      foreach ($outRows as $rr) {
        $html .= '<tr>';
        foreach ($rr as $idx => $c) {
          $cellHtml = in_array($idx, $statusColumnIndexes, true) ? $statusBadgeHtml($c) : $escape($c);
          $html .= '<td>' . $cellHtml . '</td>';
        }
        $html .= '</tr>';
      }
      $html .= '</tbody></table>';
      $sendPdf(strtolower(str_replace(' ', '_', $title)) . '_' . $stamp . '.pdf', $title, $html);
    };

    if ($entity === 'reconciliations') {
      $rows = $fetchReconciliationsList($filters);
      $headers = ['ID','Cashdrawer Date','Submitted','Agent','Type','Status','Remarks','Balance'];
      $outRows = [];
      foreach ($rows as $r) {
        $info = $reconciliationRemarkInfo($r);
        $isLate = !empty($r->late_access_id_resolved) || !empty($r->late_access_id) || !empty($r->late_submitted_at);
        $cashdrawerDateRaw = $isLate && !empty($r->late_cashdrawer_date) ? (string)$r->late_cashdrawer_date : (string)($r->date_created ?? '');
        $submittedDateRaw = $isLate && !empty($r->late_submitted_at) ? (string)$r->late_submitted_at : (string)($r->date_created ?? '');
        $outRows[] = [
          (string)($r->id ?? ''),
          $formatDateHuman($cashdrawerDateRaw),
          $formatDateHuman($submittedDateRaw),
          trim((string)($r->user_first_name ?? '') . ' ' . (string)($r->user_last_name ?? '')),
          $isLate ? 'Late access' : 'Normal',
          trim((string)($r->transaction_status ?? 'Submitted')) ?: 'Submitted',
          $info['label'],
          number_format((float)($r->balance ?? 0), 2, '.', ''),
        ];
      }
      if ($format === 'pdf') {
        $renderStandardPdfList('Reconciliations List', $headers, $outRows, [5]);
      }
      $sendXlsx('backoffice_reconciliations_list_' . $stamp . '.xlsx', 'Reconciliations', $headers, $outRows, $applyOrgToSheet, $org);
    }

    if ($entity === 'agent_services') {
      $rows = $fetchAgentServicesList($filters);
      $headers = ['ID','Date','Type','Service','Network','Amount','Status','Creator','Assigned'];
      $outRows = [];
      foreach ($rows as $r) {
        $providerText = trim((string)($r->provider ?? ''));
        if ($providerText === '') $providerText = trim((string)($r->paying_network ?? ''));
        if ($providerText === '') $providerText = trim((string)($r->receiving_network ?? ''));
        
        $outRows[] = [
          (string)($r->id ?? ''),
          $formatDateHuman($r->date_created ?? ''),
          (string)($r->customer_type ?? ''),
          (string)($r->service_type ?? ''),
          $providerText,
          number_format((float)($r->amount ?? 0), 2, '.', ''),
          (string)($r->status ?? ''),
          trim((string)($r->creator_first_name ?? '') . ' ' . (string)($r->creator_last_name ?? '')),
          trim((string)($r->assigned_first_name ?? '') . ' ' . (string)($r->assigned_last_name ?? '')),
        ];
      }
      if ($format === 'pdf') {
        $renderStandardPdfList('Agent Services List', $headers, $outRows, [6]);
      }
      $sendXlsx('backoffice_agent_services_list_' . $stamp . '.xlsx', 'Agent Services', $headers, $outRows, $applyOrgToSheet, $org);
    }

    if ($entity === 'requests') {
      $rows = $fetchRequestsList($filters);
      $ids = [];
      foreach ($rows as $r) {
        $ids[] = (int)($r->created_by ?? 0);
        $ids[] = (int)($r->assigned ?? 0);
      }
      $nameMap = $userNameMap($ids);
      $headers = ['ID','Date','Network','Type','Amount','Status','Created By','Assigned To','Description'];
      $outRows = [];
      foreach ($rows as $r) {
        $createdId = (int)($r->created_by ?? 0);
        $assignedId = (int)($r->assigned ?? 0);
        $outRows[] = [
          (string)($r->id ?? ''),
          $formatDateHuman($r->date_created ?? ''),
          (string)($r->network ?? ''),
          (string)($r->request_type ?? ''),
          number_format((float)($r->amount ?? 0), 2, '.', ''),
          (string)($r->transaction_status ?? ''),
          (string)($nameMap[$createdId] ?? (string)($r->created_by ?? '')),
          (string)($nameMap[$assignedId] ?? (string)($r->assigned ?? '')),
          (string)($r->description ?? ''),
        ];
      }
      if ($format === 'pdf') {
        $renderStandardPdfList('Requests List', $headers, $outRows, [5]);
      }
      $sendXlsx('backoffice_requests_list_' . $stamp . '.xlsx', 'Requests', $headers, $outRows, $applyOrgToSheet, $org);
    }

    if ($entity === 'payments') {
      $rows = $fetchPaymentsList($filters);
      $ids = [];
      foreach ($rows as $r) {
        $ids[] = (int)($r->created_by ?? 0);
        $ids[] = (int)($r->pay_to ?? 0);
      }
      $nameMap = $userNameMap($ids);
      $headers = ['ID','Date','Network','Payment Type','Amount','Status','Created By','Pay To','Description'];
      $outRows = [];
      foreach ($rows as $r) {
        $createdId = (int)($r->created_by ?? 0);
        $payToId = (int)($r->pay_to ?? 0);
        $outRows[] = [
          (string)($r->id ?? ''),
          $formatDateHuman($r->date_created ?? ''),
          (string)($r->network ?? ''),
          (string)($r->payment_type ?? ''),
          number_format((float)($r->amount ?? 0), 2, '.', ''),
          (string)($r->transaction_status ?? ''),
          (string)($nameMap[$createdId] ?? (string)($r->created_by ?? '')),
          (string)($nameMap[$payToId] ?? (string)($r->pay_to ?? '')),
          (string)($r->description ?? ''),
        ];
      }
      if ($format === 'pdf') {
        $renderStandardPdfList('Payments List', $headers, $outRows, [5]);
      }
      $sendXlsx('backoffice_payments_list_' . $stamp . '.xlsx', 'Payments', $headers, $outRows, $applyOrgToSheet, $org);
    }

    if ($entity === 'commissions') {
      $rows = $fetchCommissionsList($filters);
      $ids = [];
      foreach ($rows as $r) {
        $ids[] = (int)($r->created_by ?? 0);
        $ids[] = (int)($r->pay_to ?? 0);
      }
      $nameMap = $userNameMap($ids);
      $headers = ['ID','Date','Network','Commission Type','Amount','Status','Created By','Pay To','Description'];
      $outRows = [];
      foreach ($rows as $r) {
        $createdId = (int)($r->created_by ?? 0);
        $payToId = (int)($r->assigned ?? ($r->pay_to ?? 0));
        $createdName = trim((string)(($r->user_first_name ?? '') . ' ' . ($r->user_last_name ?? '')));
        $payToName = trim((string)(($r->assigned_first_name ?? '') . ' ' . ($r->assigned_last_name ?? '')));
        if ($payToName === '') {
          $payToName = trim((string)(($r->payto_first_name ?? '') . ' ' . ($r->payto_last_name ?? '')));
        }
        $outRows[] = [
          (string)($r->id ?? ''),
          $formatDateHuman($r->date_created ?? ''),
          (string)($r->network ?? ''),
          (string)($r->commission_type ?? ''),
          number_format((float)($r->amount ?? 0), 2, '.', ''),
          (string)($r->transaction_status ?? ''),
          $createdName !== '' ? $createdName : (string)($nameMap[$createdId] ?? (string)($r->created_by ?? '')),
          $payToName !== '' ? $payToName : (string)($nameMap[$payToId] ?? (string)($r->pay_to ?? '')),
          (string)($r->description ?? ''),
        ];
      }
      if ($format === 'pdf') {
        $renderStandardPdfList('Commissions List', $headers, $outRows, [5]);
      }
      $sendXlsx('backoffice_commissions_list_' . $stamp . '.xlsx', 'Commissions', $headers, $outRows, $applyOrgToSheet, $org);
    }

    if ($entity === 'bank_transfers') {
      $rows = $fetchBankTransfersList($filters);
      $headers = ['ID','Date','Type','Category / Detail','Bank','Network','Amount','2nd Party','3rd Party / Withdrawer','Status'];
      $outRows = [];
      foreach ($rows as $r) {
        $type = trim((string)($r->transaction_type ?? ''));
        $detail = trim((string)($r->transfer_category ?? ''));
        if ($detail === '') $detail = trim((string)($r->bank_name ?? ''));
        $secondPartyName = trim((string)(($r->second_party_first_name ?? '') . ' ' . ($r->second_party_last_name ?? '')));
        $withdrawerName = trim((string)(($r->withdrawer_first_name ?? '') . ' ' . ($r->withdrawer_last_name ?? '')));
        $thirdPartyName = trim((string)(($r->third_party_first_name ?? '') . ' ' . ($r->third_party_last_name ?? '')));
        $roleName = backoffice_bank_transfer_is_from_bank_flow($type) ? $withdrawerName : $thirdPartyName;
        $outRows[] = [
          (string)($r->id ?? ''),
          $formatDateHuman($r->date_created ?? ''),
          $type,
          $detail,
          (string)($r->bank_name ?? ''),
          (string)($r->network_name ?? ''),
          number_format((float)($r->amount ?? 0), 2, '.', ''),
          $secondPartyName,
          $roleName,
          (string)($r->transaction_status ?? ''),
        ];
      }
      if ($format === 'pdf') {
        $renderStandardPdfList('Bank Transfers List', $headers, $outRows, [9]);
      }
      $sendXlsx('backoffice_bank_transfers_list_' . $stamp . '.xlsx', 'Bank Transfers', $headers, $outRows, $applyOrgToSheet, $org);
    }
  }

  if ($resource === 'view') {
    $id = (int)($_GET['id'] ?? 0);
    if ($entity === 'reconciliations') {
      $payload = $fetchReconciliationView($id);
      if (empty($payload['summary'])) {
        throw new \Exception('Record not found or access denied');
      }
      $summary = $payload['summary'];
      $details = $payload['details'] ?? [];
      $bankChannelNeedles = ['BANK','ADB','ABSA','FIDELITY','APEX','ACCESS','CAL','ECOBANK','GCB','GT','STANBIC','FNB','FIRST'];
      $isBankChannel = static function (string $walletId) use ($bankChannelNeedles): bool {
        $walletId = strtoupper(trim($walletId));
        if (strpos($walletId, 'ECASH:') !== 0) return false;
        $labelKey = strtoupper(trim(substr($walletId, 6)));
        foreach ($bankChannelNeedles as $needle) {
          if ($needle !== '' && strpos($labelKey, $needle) !== false) return true;
        }
        return false;
      };
      
      $systemTotal = 0.0;
      $enteredTotal = 0.0;
      $cashBreakdown = [
        'GHS' => ['system_total' => 0.0, 'entered_total' => 0.0, 'variance_total' => 0.0],
        'CFA' => ['system_total' => 0.0, 'entered_total' => 0.0, 'variance_total' => 0.0],
      ];
      $varianceBreakdown = ['ghs_cash' => 0.0, 'cfa_cash' => 0.0, 'ecash' => 0.0, 'bank' => 0.0, 'commission' => 0.0, 'other' => 0.0];
      $miscAdjustment = 0.0;
      $hasMisc = false;
      $groupedRows = [];
      $detailRows = [];

      foreach ($details as $detail) {
        $walletId = (string)($detail->wallet_id ?? '');
        $labelInfo = $reconciliationChannelLabel($walletId);
        $expected = (float)($detail->expected_balance ?? 0);
        $entered = (float)($detail->balance ?? 0);
        $variance = (float)($detail->variance ?? ($expected - $entered));
        if (strtolower(trim($walletId)) === 'misc') {
          $miscAdjustment = $entered;
          $hasMisc = abs($miscAdjustment) >= 0.01;
          continue;
        }
        $systemTotal += $expected;
        $enteredTotal += $entered;
        $walletKey = strtolower(trim($walletId));
        $currency = '';
        if (strpos($walletKey, 'cash:') === 0) {
          $currency = strtoupper(substr($walletId, 5));
          if (!in_array($currency, ['GHS', 'CFA'], true)) $currency = '';
        }
        if ($currency !== '') {
          $cashBreakdown[$currency]['system_total'] += $expected;
          $cashBreakdown[$currency]['entered_total'] += $entered;
          $cashBreakdown[$currency]['variance_total'] += $variance;
          if ($currency === 'GHS') {
            $varianceBreakdown['ghs_cash'] += $variance;
          } else {
            $varianceBreakdown['cfa_cash'] += $variance;
          }
        } elseif ($labelInfo['group'] === 'E-Cash') {
          if ($isBankChannel($walletId)) {
            $varianceBreakdown['bank'] += $variance;
            $labelInfo['group'] = 'Shared Accounts Channels';
            $labelInfo['label'] = 'Bank (' . strtoupper(substr($walletId, 6)) . ')';
          } else {
            $varianceBreakdown['ecash'] += $variance;
          }
        } elseif ($labelInfo['group'] === 'Commissions') {
          $varianceBreakdown['commission'] += $variance;
        } else {
          $varianceBreakdown['other'] += $variance;
        }
        $varianceLabel = 'OK';
        if (abs($variance) >= 0.01) {
          $varianceLabel = $currency !== '' ? ($variance > 0 ? 'Shortage' : 'Overage') : ($hasMisc ? 'Captured' : 'Mismatch');
        }
        $rowOut = [
          $labelInfo['group'],
          $labelInfo['label'],
          number_format($expected, 2, '.', ''),
          number_format($entered, 2, '.', ''),
          $varianceLabel . ' ' . number_format(abs($variance), 2, '.', ''),
          $varianceLabel,
        ];
        $groupedRows[$labelInfo['group']][] = $rowOut;
        $detailRows[] = [
          $labelInfo['group'],
          $labelInfo['label'],
          number_format($expected, 2, '.', ''),
          number_format($entered, 2, '.', ''),
          $varianceLabel . ' ' . number_format(abs($variance), 2, '.', ''),
        ];
      }

      $summaryStatus = trim((string)($summary->transaction_status ?? 'Submitted')) ?: 'Submitted';
      $agent = trim((string)($summary->user_first_name ?? '') . ' ' . (string)($summary->user_last_name ?? ''));
      
      if ($format === 'pdf') {
        $html = $orgHeaderHtml($org);
        $html .= '<h1>Reconciliation Statement</h1>';
        $html .= '<div class="meta-bar">Session #' . $escape((string)($summary->id ?? $id)) . ' | Generated: ' . $escape($formatDateHuman(date('Y-m-d H:i:s'))) . '</div>';
        
        $html .= '<table class="table-modern" style="margin-bottom:16px">';
        $html .= '<tr><td width="15%" class="font-bold">Agent</td><td width="35%">' . $escape($agent) . '</td><td width="15%" class="font-bold">Status</td><td width="35%">' . $statusBadgeHtml($summaryStatus) . '</td></tr>';
        $html .= '<tr><td class="font-bold">Date</td><td>' . $escape($formatDateHuman($summary->date_created ?? '')) . '</td><td class="font-bold">Remarks</td><td>' . $escape((string)($summary->remarks ?? '')) . '</td></tr>';
        if (trim((string)($summary->rejection_reason ?? '')) !== '') {
          $html .= '<tr><td class="font-bold" style="color:#b91c1c">Reason</td><td colspan="3" style="color:#b91c1c">' . $escape((string)$summary->rejection_reason) . '</td></tr>';
        }
        $html .= '</table>';

        $html .= '<h3>Channel Details</h3>';
        $html .= '<table class="table-modern">';
        $html .= '<thead><tr><th>Description</th><th class="text-right">System Balance</th><th class="text-right">Entered Balance</th><th class="text-right">Variance</th></tr></thead><tbody>';
        foreach ($groupedRows as $group => $rowsInGroup) {
          $html .= '<tr><td colspan="4" style="background:#f1f5f9;font-weight:700;color:#0f172a;">' . $escape($group) . '</td></tr>';
          foreach ($rowsInGroup as $rr) {
            $html .= '<tr>';
            $html .= '<td>' . $escape($rr[1]) . '</td>';
            $html .= '<td class="text-right">' . $escape($rr[2]) . '</td>';
            $html .= '<td class="text-right">' . $escape($rr[3]) . '</td>';
            $html .= '<td class="text-right">' . $statusBadgeHtml($rr[5]) . ' <span class="font-bold">' . $escape(preg_replace('/^' . preg_quote((string)($rr[5] ?? ''), '/') . '\s*/i', '', (string)$rr[4])) . '</span></td>';
            $html .= '</tr>';
          }
        }
        $html .= '</tbody></table>';
        $sendPdf('backoffice_reconciliation_' . ($summary->id ?? $id) . '_' . $stamp . '.pdf', 'Reconciliation Statement', $html);
      }

      $headers = ['Group','Description','System','Entered','Variance'];
      $sendXlsx('backoffice_reconciliation_' . ($summary->id ?? $id) . '_' . $stamp . '.xlsx', 'Reconciliation', $headers, $detailRows, $applyOrgToSheet, $org);
    }

    $row = $fetchSingle($entity, $id);
    if (empty($row)) {
      throw new \Exception('Record not found or access denied');
    }

    // Field mapping for dynamic detailed view exports
    $headers = ['Field', 'Value'];
    $rows = [];
    $creatorName = ['first' => '', 'last' => ''];
    $assignedName = ['first' => '', 'last' => ''];
    foreach ((array)$row as $k => $v) {
      if (is_object($v) || is_array($v)) continue;
      $vv = (string)$v;
      if ($entity === 'agent_services') {
        if ($k === 'creator_first_name') {
          $creatorName['first'] = trim($vv);
          continue;
        }
        if ($k === 'creator_last_name') {
          $creatorName['last'] = trim($vv);
          continue;
        }
        if ($k === 'assigned_first_name') {
          $assignedName['first'] = trim($vv);
          continue;
        }
        if ($k === 'assigned_last_name') {
          $assignedName['last'] = trim($vv);
          continue;
        }
        if (in_array($k, ['creator_id', 'assigned_to', 'processor_id', 'stage', 'deleted'], true)) {
          continue;
        }
      }
      [$renderedHtml, $renderedPlain] = $renderDetailValue((string)$k, $vv);
      if ($entity === 'agent_services' && trim(strip_tags($renderedHtml)) === '') {
        continue;
      }
      $rows[] = [$renderDetailLabel((string)$k), $renderedHtml, $renderedPlain];
    }

    if ($entity === 'agent_services') {
      $creatorFull = trim($creatorName['first'] . ' ' . $creatorName['last']);
      if ($creatorFull !== '') {
        $rows[] = ['Creator Full Name', $escape($creatorFull), $creatorFull];
      }
      $assignedFull = trim($assignedName['first'] . ' ' . $assignedName['last']);
      if ($assignedFull !== '') {
        $rows[] = ['Assigned Full Name', $escape($assignedFull), $assignedFull];
      }
    }

    $titleLabel = str_replace('_', ' ', ucfirst($entity));
    if ($format === 'pdf') {
      $html = $orgHeaderHtml($org);
      $html .= '<h1>' . $escape($titleLabel) . ' Details</h1>';
      $html .= '<div class="meta-bar">Record ID: #' . $escape((string)($row->id ?? $id)) . ' • Generated: ' . $escape($formatDateHuman(date('Y-m-d H:i:s'))) . '</div>';
      $html .= '<table class="table-modern">';
      foreach ($rows as $rr) {
        $valueHtml = (string)$rr[1];
        $html .= '<tr><td class="font-bold" width="30%">' . $escape($rr[0]) . '</td><td>' . $valueHtml . '</td></tr>';
      }
      $html .= '</table>';
      $sendPdf('backoffice_' . $entity . '_' . ($row->id ?? $id) . '_' . $stamp . '.pdf', $titleLabel . ' Record Details', $html);
    }

    // Convert rows for XLSX: use plain text for the second column
    $xlsxRows = array_map(function(array $row) {
      return [$row[0], $row[2] ?? $row[1]];
    }, $rows);

    $sendXlsx('backoffice_' . $entity . '_' . ($row->id ?? $id) . '_' . $stamp . '.xlsx', $titleLabel, $headers, $xlsxRows, $applyOrgToSheet, $org);
  }

  throw new \Exception('Unknown resource');
} catch (\Throwable $e) {
  message_fail($e->getMessage());
  redirect($admin_route . '/' . $plugin_route);
}
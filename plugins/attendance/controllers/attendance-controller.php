<?php
require_once __DIR__ . '/../../../vendor/autoload.php'; // Composer autoload

// Set timezone to Accra
date_default_timezone_set('Africa/Accra');
header('Content-Type: application/json; charset=utf-8');
ob_start();

if(!function_exists('attendance_json_response')){
    function attendance_json_response(array $payload, int $status = 200): void
    {
        while(ob_get_level() > 0){
            ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}

// All actions will use POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    attendance_json_response(['success' => false, 'error' => 'Invalid request method'], 405);
}

$rawData = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    attendance_json_response(['success' => false, 'error' => 'Invalid JSON input: ' . json_last_error_msg()], 400);
}
if (!isset($rawData['action'])) {
    attendance_json_response(['success' => false, 'error' => 'Missing action parameter'], 400);
}
$action = $rawData['action'];

$attendanceJsonAction = !in_array($action, ['export_attendance', 'export_register_xlsx'], true);
if($attendanceJsonAction){
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    register_shutdown_function(static function(): void {
        $error = error_get_last();
        if(empty($error) || !in_array((int)($error['type'] ?? 0), [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)){
            return;
        }
        attendance_json_response(['success' => false, 'error' => 'Attendance request failed. Please try again.'], 500);
    });
    set_exception_handler(static function(\Throwable $exception): void {
        attendance_json_response(['success' => false, 'error' => 'Attendance request failed. Please try again.'], 500);
    });
}

try {
    $info = ['success' => false, 'results' => []];

	if ($action === 'export_register_xlsx') {
		$mode = $rawData['mode'] ?? 'range';
		$date = (string)($rawData['date'] ?? '');
		$startDate = (string)($rawData['startDate'] ?? '');
		$endDate = (string)($rawData['endDate'] ?? '');
		$staffIds = $rawData['staffIds'] ?? [];
		if (!is_array($staffIds)) $staffIds = [];
		$staffIds = array_values(array_filter(array_map('strval', $staffIds), function($v){ return $v !== ''; }));

		if ($mode === 'specific') {
			if (empty($date)) throw new Exception('Date parameter is required');
			$startDate = $date;
			$endDate = $date;
		} else {
			if (empty($startDate) || empty($endDate)) throw new Exception('Start and end dates are required');
		}

		$grouped = [];
		if (!empty($staffIds)) {
			$grouped = fetchAttendanceByStudentsOptimized($startDate, $endDate, $staffIds);
		} else {
			$grouped = fetchAttendanceByRangeOptimized($startDate, $endDate);
		}

		$dates = array_keys($grouped);
		sort($dates);
		$usersById = [];
		$statusByUserDate = [];
		foreach ($dates as $d) {
			$g = $grouped[$d] ?? ['marked' => [], 'unmarked' => []];
			$marked = $g['marked'] ?? [];
			foreach ($marked as $row) {
				$uid = (string)($row['user_id'] ?? $row['id'] ?? '');
				if ($uid === '') continue;
				$usersById[$uid] = $row;
				if (!isset($statusByUserDate[$uid])) $statusByUserDate[$uid] = [];
				$statusByUserDate[$uid][$d] = [
					'remark' => (string)($row['remarks'] ?? ''),
					'time' => (string)($row['time'] ?? ''),
				];
			}
			$unmarked = $g['unmarked'] ?? [];
			foreach ($unmarked as $row) {
				$uid = (string)($row['id'] ?? $row['user_id'] ?? '');
				if ($uid === '') continue;
				if (!isset($usersById[$uid])) $usersById[$uid] = $row;
			}
		}

		$users = [];
		foreach ($usersById as $uid => $u) {
			$fn = (string)($u['first_name'] ?? '');
			$ln = (string)($u['last_name'] ?? '');
			$name = trim($fn . ' ' . $ln);
			if ($name === '') $name = '#' . $uid;
			$users[] = ['id' => (string)$uid, 'name' => $name];
		}
		usort($users, function($a, $b){ return strcmp($a['name'], $b['name']); });

		$monthGroups = [];
		$currentKey = '';
		$current = null;
		foreach ($dates as $d) {
			$mk = date('Y-m', strtotime($d));
			if ($mk !== $currentKey) {
				$currentKey = $mk;
				$current = ['key' => $mk, 'label' => date('F Y', strtotime($d)), 'dates' => []];
				$monthGroups[] = $current;
			}
			$current['dates'][] = $d;
		}

		$session = get_session();
		$generatedBy = '';
		if (!empty($session->id)) {
			$generatedBy = trim(((string)($session->first_name ?? '')) . ' ' . ((string)($session->last_name ?? '')));
		}
		$appName = 'Attendance Register Sheet';
		$rangeLabel = ($dates ? (reset($dates) . ' to ' . end($dates)) : '');
		$stamp = date('Y-m-d H:i:s');

		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Register');

		$colors = [
			'green' => '198754',
			'lightGray' => 'F2F4F7',
			'gray' => 'E9ECEF',
			'present' => 'D1E7DD',
			'late' => 'FFF3CD',
			'absent' => 'F8D7DA',
			'nm' => 'F1F3F5',
			'rate' => 'E7F1FF',
			'textDark' => '141414',
		];
		$mkFill = function(string $hex){
			return ['fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => $hex]]];
		};
		$mkFont = function(bool $bold, string $rgb){
			return ['font' => ['bold' => $bold, 'color' => ['rgb' => $rgb]]];
		};
		$mkAlign = function(string $h){
			return ['alignment' => ['horizontal' => $h, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER, 'wrapText' => false]];
		};
		$mkBorder = function(string $style = 'thin'){
			$map = [
				'thin' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
				'medium' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM,
			];
			$bs = $map[$style] ?? \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN;
			return ['borders' => ['allBorders' => ['borderStyle' => $bs, 'color' => ['rgb' => 'CFD4DA']]]];
		};

		$rowNum = 1;
		$totalCols = 1 + count($dates) + 5;
		$lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalCols);
		$sheet->setCellValue('A' . $rowNum, $appName);
		$sheet->mergeCells('A' . $rowNum . ':' . $lastCol . $rowNum);
		$rowNum++;
		if ($rangeLabel) {
			$sheet->setCellValue('A' . $rowNum, 'Range: ' . $rangeLabel);
			$sheet->mergeCells('A' . $rowNum . ':' . $lastCol . $rowNum);
			$rowNum++;
		}
		if ($generatedBy) {
			$sheet->setCellValue('A' . $rowNum, 'Generated by: ' . $generatedBy);
			$sheet->mergeCells('A' . $rowNum . ':' . $lastCol . $rowNum);
			$rowNum++;
		}
		$sheet->setCellValue('A' . $rowNum, 'Generated: ' . $stamp);
		$sheet->mergeCells('A' . $rowNum . ':' . $lastCol . $rowNum);
		$rowNum += 2;

		$sheet->getStyle('A1:' . $lastCol . ($rowNum - 1))->applyFromArray($mkFont(true, $colors['textDark']));
		$sheet->getStyle('A1:' . $lastCol . ($rowNum - 1))->applyFromArray($mkAlign(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT));
		$sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray(['font' => ['size' => 14, 'bold' => true]]);
		$sheet->getStyle('A2:' . $lastCol . '2')->applyFromArray(['font' => ['size' => 11, 'bold' => true]]);

		$monthHeaderRow = $rowNum;
		$dayHeaderRow = $rowNum + 1;
		$sheet->setCellValue('A' . $monthHeaderRow, 'Staff');
		$sheet->mergeCells('A' . $monthHeaderRow . ':A' . $dayHeaderRow);
		$colIndex = 2;
		foreach ($monthGroups as $mg) {
			$span = count($mg['dates']);
			$startCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
			$endCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + $span - 1);
			$sheet->setCellValue($startCol . $monthHeaderRow, $mg['label']);
			$sheet->mergeCells($startCol . $monthHeaderRow . ':' . $endCol . $monthHeaderRow);
			$colIndex += $span;
		}
		$sumStartCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(2 + count($dates));
		$sumEndCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalCols);
		$sheet->setCellValue($sumStartCol . $monthHeaderRow, 'Summary');
		$sheet->mergeCells($sumStartCol . $monthHeaderRow . ':' . $sumEndCol . $monthHeaderRow);

		$sheet->setCellValue($sumStartCol . $dayHeaderRow, 'P');
		$sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + count($dates)) . $dayHeaderRow, 'L');
		$sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(4 + count($dates)) . $dayHeaderRow, 'A');
		$sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5 + count($dates)) . $dayHeaderRow, 'NM');
		$sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(6 + count($dates)) . $dayHeaderRow, '%');

		for ($i = 0; $i < count($dates); $i++) {
			$d = $dates[$i];
			$ts = strtotime($d);
			$dayNum = (int)date('j', $ts);
			$dow2 = substr(date('D', $ts), 0, 2);
			$cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(2 + $i) . $dayHeaderRow;
			$sheet->setCellValue($cell, $dayNum . ' ' . $dow2);
		}

		$monthHeaderRange = 'A' . $monthHeaderRow . ':' . $lastCol . $monthHeaderRow;
		$dayHeaderRange = 'A' . $dayHeaderRow . ':' . $lastCol . $dayHeaderRow;
		$sheet->getStyle($monthHeaderRange)->applyFromArray($mkFill($colors['green']) + $mkFont(true, 'FFFFFF') + $mkAlign(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER) + $mkBorder('thin'));
		$sheet->getStyle($dayHeaderRange)->applyFromArray($mkFill($colors['lightGray']) + $mkFont(true, $colors['textDark']) + $mkAlign(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER) + $mkBorder('thin'));
		$sheet->getStyle('A' . $monthHeaderRow . ':A' . $dayHeaderRow)->applyFromArray($mkFill($colors['green']) + $mkFont(true, 'FFFFFF') + $mkAlign(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT) + $mkBorder('thin'));

		$monthBoundaryCols = [];
		$prevMk = '';
		for ($i = 0; $i < count($dates); $i++) {
			$mk = date('Y-m', strtotime($dates[$i]));
			if ($prevMk && $mk !== $prevMk) {
				$monthBoundaryCols[] = 2 + $i;
			}
			$prevMk = $mk;
		}
		$summaryStartIndex = 2 + count($dates);

		$rowNum = $dayHeaderRow + 1;
		$totalPresent = 0; $totalLate = 0; $totalAbsent = 0; $totalNm = 0;
		$rateList = [];
		foreach ($users as $u) {
			$uid = $u['id'];
			$sheet->setCellValue('A' . $rowNum, $u['name']);
			$present = 0; $late = 0; $absent = 0; $nm = 0;
			for ($i = 0; $i < count($dates); $i++) {
				$d = $dates[$i];
				$cell = $statusByUserDate[$uid][$d] ?? null;
				$remark = $cell ? strtoupper((string)($cell['remark'] ?? '')) : '';
				$val = '—';
				if ($remark === 'PRESENT') { $present++; $val = '✔'; }
				else if ($remark === 'LATE') { $late++; $val = '✔'; }
				else if ($remark === 'ABSENT') { $absent++; $val = '✖'; }
				else { $nm++; $val = '—'; }
				$colIdx = 2 + $i;
				$addr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx) . $rowNum;
				$sheet->setCellValue($addr, $val);
				$fill = $colors['nm'];
				if ($remark === 'PRESENT') $fill = $colors['present'];
				else if ($remark === 'LATE') $fill = $colors['late'];
				else if ($remark === 'ABSENT') $fill = $colors['absent'];
				$sheet->getStyle($addr)->applyFromArray($mkFill($fill) + $mkAlign(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER) + $mkBorder('thin'));
			}
			$total = count($dates);
			$rate = $total ? (int)round((($present + $late) / $total) * 100) : 0;
			$totalPresent += $present;
			$totalLate += $late;
			$totalAbsent += $absent;
			$totalNm += $nm;
			$rateList[] = ['name' => $u['name'], 'rate' => $rate];
			$sheet->setCellValue($sumStartCol . $rowNum, $present);
			$sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + count($dates)) . $rowNum, $late);
			$sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(4 + count($dates)) . $rowNum, $absent);
			$sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5 + count($dates)) . $rowNum, $nm);
			$sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(6 + count($dates)) . $rowNum, $rate);

			$sumCells = [
				$sumStartCol . $rowNum => $colors['present'],
				\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + count($dates)) . $rowNum => $colors['late'],
				\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(4 + count($dates)) . $rowNum => $colors['absent'],
				\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5 + count($dates)) . $rowNum => $colors['nm'],
				\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(6 + count($dates)) . $rowNum => $colors['rate'],
			];
			foreach ($sumCells as $addr => $hex) {
				$sheet->getStyle($addr)->applyFromArray($mkFill($hex) + $mkAlign(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER) + $mkBorder('thin') + $mkFont(true, $colors['textDark']));
			}
			$rowNum++;
		}

		$endDataRow = $rowNum - 1;
		if ($endDataRow >= $dayHeaderRow) {
			$sheet->getStyle('A' . ($dayHeaderRow + 1) . ':' . $lastCol . $endDataRow)->applyFromArray($mkBorder('thin'));
			$sheet->getStyle('A' . ($dayHeaderRow + 1) . ':A' . $endDataRow)->applyFromArray($mkAlign(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT));
		}

		// month separators + summary separator
		foreach ($monthBoundaryCols as $colIdx) {
			$colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
			$sheet->getStyle($colLetter . $monthHeaderRow . ':' . $colLetter . $endDataRow)->applyFromArray([
				'borders' => ['left' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM, 'color' => ['rgb' => '333333']]]
			]);
		}
		$sumLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($summaryStartIndex);
		$sheet->getStyle($sumLetter . $monthHeaderRow . ':' . $sumLetter . $endDataRow)->applyFromArray([
			'borders' => ['left' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM, 'color' => ['rgb' => '333333']]]
		]);

		// style summary day headers
		$sheet->getStyle($sumStartCol . $dayHeaderRow . ':' . $sumEndCol . $dayHeaderRow)->applyFromArray($mkFill($colors['gray']) + $mkFont(true, $colors['textDark']) + $mkAlign(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER) + $mkBorder('thin'));

		// Key + Metrics
		$rowNum = $endDataRow + 2;
		$sheet->setCellValue('A' . $rowNum, 'Key');
		$sheet->mergeCells('A' . $rowNum . ':' . $lastCol . $rowNum);
		$sheet->getStyle('A' . $rowNum . ':' . $lastCol . $rowNum)->applyFromArray($mkFill($colors['gray']) + $mkFont(true, $colors['textDark']) + $mkBorder('thin'));
		$rowNum++;
		$legend = [
			['✔', 'Present', $colors['present']],
			['✔', 'Late', $colors['late']],
			['✖', 'Absent', $colors['absent']],
			['—', 'Not marked', $colors['nm']],
		];
		foreach ($legend as $lg) {
			$sheet->setCellValue('A' . $rowNum, $lg[0]);
			$sheet->setCellValue('B' . $rowNum, $lg[1]);
			$sheet->mergeCells('B' . $rowNum . ':' . $lastCol . $rowNum);
			$sheet->getStyle('A' . $rowNum)->applyFromArray($mkFill($lg[2]) + $mkAlign(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER) + $mkBorder('thin') + $mkFont(true, $colors['textDark']));
			$sheet->getStyle('B' . $rowNum . ':' . $lastCol . $rowNum)->applyFromArray($mkBorder('thin') + $mkAlign(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT));
			$sheet->getRowDimension($rowNum)->setRowHeight(18);
			$rowNum++;
		}

		$rowNum++;
		$sheet->setCellValue('A' . $rowNum, 'Metrics & Analytics');
		$sheet->mergeCells('A' . $rowNum . ':' . $lastCol . $rowNum);
		$sheet->getStyle('A' . $rowNum . ':' . $lastCol . $rowNum)->applyFromArray($mkFill($colors['gray']) + $mkFont(true, $colors['textDark']) + $mkBorder('thin'));
		$rowNum++;
		$staffCount = count($users);
		$daysCount = count($dates);
		$totalMarks = $staffCount * $daysCount;
		$overallRate = $totalMarks ? (int)round((($totalPresent + $totalLate) / $totalMarks) * 100) : 0;
		$sumRates = 0;
		foreach ($rateList as $rr) { $sumRates += (int)$rr['rate']; }
		$avgStaffRate = $staffCount ? (int)round($sumRates / $staffCount) : 0;

		$metricsRows = [
			['Staff', $staffCount, 'Days', $daysCount, 'Total Marks', $totalMarks],
			['Present', $totalPresent, 'Late', $totalLate, 'Absent', $totalAbsent],
			['Not marked', $totalNm, 'Overall Rate', $overallRate . '%', 'Avg Staff Rate', $avgStaffRate . '%'],
		];
		$coord = '\\PhpOffice\\PhpSpreadsheet\\Cell\\Coordinate';
		$colL = function(int $i) use ($coord){
			return $coord::stringFromColumnIndex($i);
		};
		foreach ($metricsRows as $mr) {
			// Build a 3 KPI-pair row spanning the sheet width so columns remain readable
			if ($totalCols >= 15) {
				$defs = [
					['lbl' => [1,3],  'val' => [4,5]],
					['lbl' => [6,8],  'val' => [9,10]],
					['lbl' => [11,13],'val' => [14,15]],
				];
				for ($k = 0; $k < 3; $k++) {
					$lbl = (string)($mr[$k*2] ?? '');
					$val = (string)($mr[$k*2 + 1] ?? '');
					$l0 = $colL($defs[$k]['lbl'][0]);
					$l1 = $colL($defs[$k]['lbl'][1]);
					$v0 = $colL($defs[$k]['val'][0]);
					$v1 = $colL($defs[$k]['val'][1]);
					$sheet->setCellValue($l0 . $rowNum, $lbl);
					$sheet->mergeCells($l0 . $rowNum . ':' . $l1 . $rowNum);
					$sheet->setCellValue($v0 . $rowNum, $val);
					$sheet->mergeCells($v0 . $rowNum . ':' . $v1 . $rowNum);
					$sheet->getStyle($l0 . $rowNum . ':' . $l1 . $rowNum)->applyFromArray($mkFill($colors['gray']) + $mkFont(true, $colors['textDark']) + $mkBorder('thin') + $mkAlign(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER));
					$sheet->getStyle($v0 . $rowNum . ':' . $v1 . $rowNum)->applyFromArray($mkFont(true, $colors['textDark']) + $mkBorder('thin') + $mkAlign(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER));
				}
				// Fill remaining columns with bordered blanks
				$end = $colL($totalCols);
				$sheet->getStyle('P' . $rowNum . ':' . $end . $rowNum)->applyFromArray($mkBorder('thin'));
			} else {
				$sheet->fromArray($mr, null, 'A' . $rowNum);
				$sheet->getStyle('A' . $rowNum . ':F' . $rowNum)->applyFromArray($mkBorder('thin'));
				$sheet->getStyle('A' . $rowNum . ':F' . $rowNum)->applyFromArray($mkAlign(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER));
				$sheet->getStyle('A' . $rowNum . ':F' . $rowNum)->applyFromArray($mkFont(true, $colors['textDark']));
				$sheet->getStyle('A' . $rowNum)->applyFromArray($mkFill($colors['gray']));
				$sheet->getStyle('C' . $rowNum)->applyFromArray($mkFill($colors['gray']));
				$sheet->getStyle('E' . $rowNum)->applyFromArray($mkFill($colors['gray']));
			}
			$sheet->getRowDimension($rowNum)->setRowHeight(18);
			$rowNum++;
		}

		usort($rateList, function($a, $b){
			if ($a['rate'] === $b['rate']) return strcmp($a['name'], $b['name']);
			return ($a['rate'] < $b['rate']) ? 1 : -1;
		});
		$top = array_slice($rateList, 0, 5);
		$bottom = array_slice(array_reverse($rateList), 0, 5);
		$rowNum++;
		// Keep Top/Bottom compact (content-fit) but wide enough for names
		// Top: A:F (Name A:E, % F) | Bottom: G:L (Name G:K, % L)
		$sheet->setCellValue('A' . $rowNum, 'Top 5 Staff (by %)');
		$sheet->mergeCells('A' . $rowNum . ':F' . $rowNum);
		$sheet->setCellValue('G' . $rowNum, 'Bottom 5 Staff (by %)');
		$sheet->mergeCells('G' . $rowNum . ':L' . $rowNum);
		$sheet->getStyle('A' . $rowNum . ':L' . $rowNum)->applyFromArray($mkFill($colors['gray']) + $mkFont(true, $colors['textDark']) + $mkBorder('thin') + $mkAlign(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER));
		$sheet->getRowDimension($rowNum)->setRowHeight(18);
		$rowNum++;
		// Column headers
		$sheet->setCellValue('A' . $rowNum, 'Name');
		$sheet->mergeCells('A' . $rowNum . ':E' . $rowNum);
		$sheet->setCellValue('F' . $rowNum, '%');
		$sheet->setCellValue('G' . $rowNum, 'Name');
		$sheet->mergeCells('G' . $rowNum . ':K' . $rowNum);
		$sheet->setCellValue('L' . $rowNum, '%');
		$sheet->getStyle('A' . $rowNum . ':L' . $rowNum)->applyFromArray($mkFill($colors['green']) + $mkFont(true, 'FFFFFF') + $mkBorder('thin') + $mkAlign(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER));
		$sheet->getRowDimension($rowNum)->setRowHeight(18);
		$rowNum++;
		for ($i = 0; $i < 5; $i++) {
			$t = $top[$i] ?? ['name' => '', 'rate' => ''];
			$b = $bottom[$i] ?? ['name' => '', 'rate' => ''];
			$sheet->setCellValue('A' . $rowNum, (string)$t['name']);
			$sheet->mergeCells('A' . $rowNum . ':E' . $rowNum);
			$sheet->setCellValue('F' . $rowNum, (string)$t['rate'] . '%');
			$sheet->setCellValue('G' . $rowNum, (string)$b['name']);
			$sheet->mergeCells('G' . $rowNum . ':K' . $rowNum);
			$sheet->setCellValue('L' . $rowNum, (string)$b['rate'] . '%');
			$sheet->getStyle('A' . $rowNum . ':L' . $rowNum)->applyFromArray($mkBorder('thin') + $mkAlign(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT));
			$sheet->getStyle('F' . $rowNum)->applyFromArray($mkAlign(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER) + $mkFont(true, $colors['textDark']));
			$sheet->getStyle('L' . $rowNum)->applyFromArray($mkAlign(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER) + $mkFont(true, $colors['textDark']));
			$sheet->getRowDimension($rowNum)->setRowHeight(18);
			$rowNum++;
		}

		$sheet->getColumnDimension('A')->setWidth(28);
		for ($i = 0; $i < count($dates); $i++) {
			$sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(2 + $i))->setWidth(4);
		}
		for ($i = 0; $i < 5; $i++) {
			$sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(2 + count($dates) + $i))->setWidth(5);
		}
		$sheet->freezePane('B' . ($dayHeaderRow + 1));

		while (ob_get_level()) { ob_end_clean(); }
		if (function_exists('header_remove')) { header_remove('Content-Type'); }
		$fileStamp = date('Y-m-d_H-i-s');
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment; filename="attendance_register_' . $fileStamp . '.xlsx"');
		header('Cache-Control: max-age=0');
		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
		$writer->save('php://output');
		exit;
	}

    // ------------------ Mark Attendance Action ------------------
    if ($action === 'mark_attendance') {
        $mode = $rawData['mode'] ?? 'specific';
        $data = $rawData['data'] ?? [];
        $date = $rawData['date'] ?? null;
        if (empty($data)) {
            throw new Exception('No attendance data provided');
        }
        if (!$date) {
            throw new Exception('Date parameter is missing for marking attendance');
        }
        foreach ($data as $row) {
            if (empty($row['userId'])) {
                $info['results'][] = [
                    'user_id' => $row['userId'] ?? null,
                    'status'   => 'failed',
                    'message'  => 'Invalid data for user',
                ];
                continue;
            }
            $rawRemarks = $row['remarks'] ?? '';
            $rawTime = $row['time'] ?? '';
            $effectiveRemarks = computeAttendanceRemarks($rawRemarks, $rawTime, $date);
            $postdata = [
                'date_created' => date("Y-m-d H:i:s"),
                'user_id'      => $row['userId'],
                'time'         => $rawTime,
                'remarks'      => $effectiveRemarks,
                'date'         => $date,
                'created_by'   => get_session()->id ?? null,
            ];
            $attendance = new \Performances\Attendance();
            if ($attendance->first(['user_id' => $row['userId'], 'date' => $date])) {
                $info['results'][] = [
                    'user_id' => $row['userId'],
                    'status'   => 'failed',
                    'message'  => 'Attendance already marked',
                ];
                continue;
            }
            $attendance->insert($postdata);
            logActivity('Attendance marked', $postdata);
            $info['results'][] = [
                'user_id' => $row['userId'],
                'status'   => 'success',
                'message'  => 'Attendance marked successfully',
            ];
        }
        if ($mode === 'specific') {
            $unmarked = fetchUnmarkedUsersForDate($date);
            $marked   = fetchMarkedUsersForDate($date);
            $info['success']  = true;
            $info['unmarked'] = $unmarked;
            $info['marked']   = $marked;
        } else {
            if (empty($rawData['startDate']) || empty($rawData['endDate'])) {
                throw new Exception('Start and end dates are required for range mode');
            }
            $startDate = $rawData['startDate'];
            $endDate   = $rawData['endDate'];
            $grouped   = fetchAttendanceByRange($startDate, $endDate);
            $info['success'] = true;
            $info['data'] = $grouped;
        }
        // Clear out any warnings or other output
        ob_clean();
        attendance_json_response($info);
    }

    // ------------------ Fetch Attendance Action ------------------
    if ($action === 'fetch_attendance') {
        $mode = $rawData['mode'] ?? 'specific';
        if ($mode === 'specific') {
            if (empty($rawData['date'])) {
                throw new Exception('Date parameter is required');
            }
            $date = $rawData['date'];
            $unmarked = fetchUnmarkedUsersForDate($date);
            $marked   = fetchMarkedUsersForDate($date);
            // Clear out any warnings or other output
            ob_clean();
            attendance_json_response([
                'success'  => true,
                'unmarked' => $unmarked,
                'marked'   => $marked,
            ]);
        } else {
            if (empty($rawData['startDate']) || empty($rawData['endDate'])) {
                throw new Exception('Start and end dates are required');
            }
            $startDate = $rawData['startDate'];
            $endDate   = $rawData['endDate'];
            $grouped = fetchAttendanceByRangeOptimized($startDate, $endDate);
            // Clear out any warnings or other output
            ob_clean();
            attendance_json_response([
                'success' => true,
                'data'    => $grouped,
            ]);
        }
    }

    // ------------------ Fetch By Student Action ------------------
    if ($action === 'fetch_attendance_by_students') {
        $studentIds = $rawData['studentIds'] ?? [];
        if (empty($studentIds) || !is_array($studentIds)) {
            throw new Exception('studentIds must be an array');
        }

       // range: startDate & endDate
        if (empty($rawData['startDate']) || empty($rawData['endDate'])) {
            throw new Exception('startDate & endDate are required for student mode');
        }
        $startDate = $rawData['startDate'];
        $endDate   = $rawData['endDate'];

        $grouped = fetchAttendanceByStudentsOptimized($startDate, $endDate, $studentIds);

        // Clear out any warnings or other output
        ob_clean();

        attendance_json_response([
            'success' => true,
            'data'    => $grouped,
        ]);
    }

    // ------------------ Fetch By View Attendance Action ------------------
    if ($action === 'view_attendance_by_students') {
        $studentIds = $rawData['studentIds'] ?? [];

       // range: startDate & endDate
        if (empty($rawData['startDate']) || empty($rawData['endDate'])) {
            throw new Exception('startDate & endDate are required for student mode');
        }
        $startDate = $rawData['startDate'];
        $endDate   = $rawData['endDate'];

        $grouped = fetchAttendanceByStudentsOptimized($startDate, $endDate, $studentIds, true);

        // Clear out any warnings or other output
        ob_clean();

        attendance_json_response([
            'success' => true,
            'data'    => $grouped,
        ]);
    }

    // ------------------ Export Attendance Action ------------------
    if ($action === 'export_attendance') {
        if (!class_exists('Mpdf\Mpdf')) {
            throw new Exception('mPDF library is missing. Run "composer require mpdf/mpdf".');
        }
        $exportType = $rawData['exportType'] ?? 'csv';
        $mode = $rawData['mode'] ?? 'specific';
        if ($mode === 'specific') {
            if (empty($rawData['date'])) {
                throw new Exception('Date parameter is required for export');
            }
            $date = $rawData['date'];
            $unmarked = fetchUnmarkedUsersForDate($date);
            $marked = fetchMarkedUsersForDate($date);
            $allData = array_merge($marked, $unmarked);
        } else {
            if (empty($rawData['startDate']) || empty($rawData['endDate'])) {
                throw new Exception('Start and end dates are required for export');
            }
            $startDate = $rawData['startDate'];
            $endDate = $rawData['endDate'];
            $grouped = fetchAttendanceByRange($startDate, $endDate);
            $allData = [];
            foreach ($grouped as $date => $group) {
                $allData = array_merge($allData, $group['marked'], $group['unmarked']);
            }
        }
        $breakdown = calculateGenderBreakdown($allData);
        if ($exportType === 'csv') {
            exportCSV($allData, $breakdown);
        } else {
            exportPDF($allData, $breakdown);
        }
        exit;
    }

    // ------------------ Attendance Analytics Action ------------------
    if ($action === 'fetch_attendance_analytics') {
        if (empty($rawData['startDate']) || empty($rawData['endDate'])) {
            throw new Exception('Start and end dates are required');
        }

        $startDate = (string)$rawData['startDate'];
        $endDate = (string)$rawData['endDate'];
		$staffIds = $rawData['staffIds'] ?? [];
		if (!is_array($staffIds)) $staffIds = [];
		$staffIds = array_values(array_filter(array_map('strval', $staffIds), fn($v) => $v !== ''));

        $startTs = strtotime($startDate);
        $endTs = strtotime($endDate);
        if (!$startTs || !$endTs) {
            throw new Exception('Invalid date range');
        }
        if ($endTs < $startTs) {
            throw new Exception('End date cannot be before start date');
        }

        $totalDays = (int)floor((($endTs - $startTs) / 86400)) + 1;
        if ($totalDays < 1) $totalDays = 1;

        $attendance = new \Performances\Attendance();

        $staffWhere = ' WHERE u.deleted = 0';

		$params = [
			'startDate' => $startDate,
			'endDate' => $endDate,
		];

		if (!empty($staffIds)) {
			$ph = [];
			foreach ($staffIds as $i => $sid) {
				$key = 'staff' . $i;
				$ph[] = ':' . $key;
				$params[$key] = $sid;
			}
			$staffWhere = ' WHERE u.deleted = 0 AND u.id IN (' . implode(',', $ph) . ')';
		}

		$params['lateThreshold'] = getLateAttendanceThreshold(getWorkOpenTime() ?: '08:00:00');

		$sql = "SELECT 
				u.id,
				u.first_name,
				u.last_name,
				u.image,
				u.gender,
				u.disabled,
				SUM(CASE WHEN (UPPER(a.remarks) = 'PRESENT' AND TIME_TO_SEC(a.time) < TIME_TO_SEC(:lateThreshold)) THEN 1 ELSE 0 END) AS present_days,
				SUM(CASE WHEN (UPPER(a.remarks) = 'LATE' OR (UPPER(a.remarks) = 'PRESENT' AND TIME_TO_SEC(a.time) >= TIME_TO_SEC(:lateThreshold))) THEN 1 ELSE 0 END) AS late_days,
				SUM(CASE WHEN UPPER(a.remarks) = 'ABSENT' THEN 1 ELSE 0 END) AS absent_days,
				SUM(CASE WHEN a.id IS NOT NULL THEN 1 ELSE 0 END) AS marked_days
			FROM users u
			LEFT JOIN attendance a
				ON a.user_id = u.id
				AND a.deleted = 0
				AND a.date >= :startDate
				AND a.date <= :endDate
			$staffWhere
			GROUP BY u.id, u.first_name, u.last_name, u.image, u.gender, u.disabled
			ORDER BY u.first_name ASC, u.last_name ASC";

		$rows = $attendance->query($sql, $params) ?: [];

		$results = [];
		$presentDaysTotal = 0;
		$absentDaysTotal = 0;
		$markedDaysTotal = 0;
		$lateDaysTotal = 0;

		foreach ($rows as $row) {
			$r = (array)$row;
			$presentDays = (int)($r['present_days'] ?? 0);
			$lateDays = (int)($r['late_days'] ?? 0);
			$absentDays = (int)($r['absent_days'] ?? 0);
			$markedDays = (int)($r['marked_days'] ?? 0);
			$unmarkedDays = max(0, $totalDays - $markedDays);

			$presentDaysTotal += $presentDays;
			$absentDaysTotal += $absentDays;
			$markedDaysTotal += $markedDays;
			$lateDaysTotal += $lateDays;

			$rate = $totalDays > 0 ? round((($presentDays + $lateDays) / $totalDays) * 100, 2) : 0;
			$presentRate = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 2) : 0;
			$lateRate = $totalDays > 0 ? round(($lateDays / $totalDays) * 100, 2) : 0;

			$results[] = [
				'id' => $r['id'] ?? null,
				'first_name' => $r['first_name'] ?? '',
				'last_name' => $r['last_name'] ?? '',
				'gender' => $r['gender'] ?? '',
				'disabled' => (int)($r['disabled'] ?? 0),
				'image' => get_image($r['image'] ?? ''),
				'present_days' => $presentDays,
				'late_days' => $lateDays,
				'absent_days' => $absentDays,
				'unmarked_days' => $unmarkedDays,
				'total_days' => $totalDays,
				'attendance_rate' => $rate,
				'present_rate' => $presentRate,
				'late_rate' => $lateRate,
			];
		}

		$meta = [
			'users' => count($results),
			'totalDays' => $totalDays,
			'startDate' => $startDate,
			'endDate' => $endDate,
			'presentDaysTotal' => $presentDaysTotal,
			'lateDaysTotal' => $lateDaysTotal,
			'absentDaysTotal' => $absentDaysTotal,
			'markedDaysTotal' => $markedDaysTotal,
		];

		ob_clean();
        attendance_json_response([
			'success' => true,
			'meta' => $meta,
			'data' => $results,
		]);
    }

    throw new Exception('Unknown action');
} catch (Exception $e) {
    ob_clean();
    attendance_json_response(['success' => false, 'error' => $e->getMessage()], 500);
} finally {
    ob_end_clean();
}

// =================== Supporting Functions ===================

function logActivity($action, $data) {
    $log = new \Performances\Log();
    $session = get_session();
    if (!empty($session->id)) {
        $username = $session->first_name . ' ' . $session->last_name;
    }
    $date = get_date($data['date']);
    $log->insert([
        'table_name'   => 'attendance',
        'action'       => $action,
        'user_id'      => $data['created_by'],
        'description'  => "$action by $username for $date",
        'date_created' => date("Y-m-d H:i:s"),
    ]);
}

function exportCSV($data, $breakdown) {
    // Clear output buffers completely.
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename="attendance_export.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['User ID', 'First Name', 'Last Name', 'Gender', 'Time', 'Remarks', 'Date']);
    foreach ($data as $row) {
         fputcsv($output, [
             $row['id'] ?? '',
             $row['first_name'] ?? '',
             $row['last_name'] ?? '',
             $row['gender'] ?? '',
             $row['time'] ?? '',
             $row['remarks'] ?? '',
             $row['date'] ?? ''
         ]);
    }
    fputcsv($output, []);
    fputcsv($output, ['Attendance Summary']);
    fputcsv($output, ['Male Present', $breakdown['malePresent']]);
    fputcsv($output, ['Male Absent', $breakdown['maleAbsent']]);
    fputcsv($output, ['Female Present', $breakdown['femalePresent']]);
    fputcsv($output, ['Female Absent', $breakdown['femaleAbsent']]);
    fclose($output);
    exit;
}

function exportPDF($data, $breakdown) {
    try {
        // Clean all buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        $mpdf = new \Mpdf\Mpdf();
        $html = '<h1>Attendance Export</h1>';
        $html .= '<table border="1" cellspacing="0" cellpadding="5">';
        $html .= '<tr>
                    <th>User ID</th><th>First Name</th><th>Last Name</th><th>Gender</th>
                    <th>Time</th><th>Remarks</th><th>Date</th>
                  </tr>';
        foreach ($data as $row) {
            $html .= '<tr>
                        <td>' . htmlspecialchars($row['id'] ?? '') . '</td>
                        <td>' . htmlspecialchars($row['first_name'] ?? '') . '</td>
                        <td>' . htmlspecialchars($row['last_name'] ?? '') . '</td>
                        <td>' . htmlspecialchars($row['gender'] ?? '') . '</td>
                        <td>' . htmlspecialchars($row['time'] ?? '') . '</td>
                        <td>' . htmlspecialchars($row['remarks'] ?? '') . '</td>
                        <td>' . htmlspecialchars($row['date'] ?? '') . '</td>
                      </tr>';
        }
        $html .= '</table>';
        $html .= '<h2>Attendance Summary</h2>';
        $html .= '<p>Male Present: ' . $breakdown['malePresent'] . '</p>';
        $html .= '<p>Male Absent: ' . $breakdown['maleAbsent'] . '</p>';
        $html .= '<p>Female Present: ' . $breakdown['femalePresent'] . '</p>';
        $html .= '<p>Female Absent: ' . $breakdown['femaleAbsent'] . '</p>';
        $mpdf->WriteHTML($html);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="attendance_export.pdf"');
        $mpdf->Output('attendance_export.pdf', 'I');
        exit;
    } catch (Exception $e) {
        error_log('PDF Export Error: ' . $e->getMessage());
        echo 'Error generating PDF';
    }
}

function fetchUnmarkedUsersForDate($date) {
    $user = new \Performances\User();
    $query = "SELECT id, first_name, last_name, image, gender, disabled FROM users 
              WHERE deleted = 0
              AND id NOT IN (SELECT user_id FROM attendance WHERE deleted = 0 AND date = :date)";
    $results = $user->query($query, ['date' => $date]);
    if (!empty($results)) {
        foreach ($results as &$result) {
            $result = (array)$result;
            $result['image'] = get_image($result['image']);
            $result['disabled'] = (int)($result['disabled'] ?? 0);
        }
    }
    return $results ?: [];
}

function fetchMarkedUsersForDate($date) {
    $attendance = new \Performances\Attendance();
    $query = "SELECT u.id, u.first_name, u.last_name, u.image, u.gender, u.disabled, a.time, a.remarks, a.date
              FROM attendance a JOIN users u ON a.user_id = u.id
              WHERE a.deleted = 0 AND u.deleted = 0 AND a.date = :date ORDER BY a.time ASC";
    $results = $attendance->query($query, ['date' => $date]);
    if (!empty($results)) {
        $workOpen = getWorkOpenTime() ?: '08:00:00';
        foreach ($results as &$result) {
            $result = (array)$result;
            $result['remarks'] = computeAttendanceRemarks($result['remarks'] ?? '', $result['time'] ?? '', $date, $workOpen);
            $result['image'] = get_image($result['image']);
            $result['disabled'] = (int)($result['disabled'] ?? 0);
        }
    }
    return $results ?: [];
}

function getDatesInRange($startDate, $endDate) {
    $dates = [];
    $current = strtotime($startDate);
    $end = strtotime($endDate);
    while ($current <= $end) {
        $dates[] = date('Y-m-d', $current);
        $current = strtotime('+1 day', $current);
    }
    return $dates;
}

function fetchAttendanceByRange($startDate, $endDate) {
    $grouped = [];
    $dates = getDatesInRange($startDate, $endDate);
    foreach ($dates as $date) {
        $grouped[$date] = [
            'marked'   => fetchMarkedUsersForDate($date),
            'unmarked' => fetchUnmarkedUsersForDate($date),
        ];
    }
    return $grouped;
}

function fetchAttendanceByRangeOptimized($startDate, $endDate) {
    $dates = getDatesInRange($startDate, $endDate);
    $grouped = [];
    foreach ($dates as $d) {
        $grouped[$d] = ['marked' => [], 'unmarked' => []];
    }

    $usersRepo = new \Performances\User();
    $allUsers = $usersRepo->query("SELECT id, first_name, last_name, image, gender, disabled FROM users WHERE deleted = 0 ORDER BY first_name ASC, last_name ASC") ?: [];
    $usersById = [];
    foreach ($allUsers as $u) {
        $u = (array)$u;
        $u['image'] = get_image($u['image'] ?? '');
        $u['disabled'] = (int)($u['disabled'] ?? 0);
        $usersById[(string)$u['id']] = $u;
    }

    $att = new \Performances\Attendance();
    $params = ['startDate' => $startDate, 'endDate' => $endDate];
    $whereIn = '';
    $workOpen = getWorkOpenTime() ?: '08:00:00';

    $sql = "SELECT a.user_id, a.date, u.first_name, u.last_name, u.image, u.gender, u.disabled, a.time, a.remarks
            FROM attendance a
            JOIN users u ON u.id = a.user_id
            WHERE a.deleted = 0 AND u.deleted = 0 AND a.date >= :startDate AND a.date <= :endDate
            ORDER BY a.date ASC, a.time ASC";
    $rows = $att->query($sql, $params) ?: [];

    $markedIdsByDate = [];
    foreach ($rows as $row) {
        $r = (array)$row;
        $date = (string)($r['date'] ?? '');
        if (!isset($grouped[$date])) continue;
        $r['remarks'] = computeAttendanceRemarks($r['remarks'] ?? '', $r['time'] ?? '', $date, $workOpen);
        $r['image'] = get_image($r['image'] ?? '');
        $r['disabled'] = (int)($r['disabled'] ?? 0);
        $grouped[$date]['marked'][] = $r;
        $uid = (string)($r['user_id'] ?? '');
        if (!isset($markedIdsByDate[$date])) $markedIdsByDate[$date] = [];
        $markedIdsByDate[$date][$uid] = true;
    }

    foreach ($dates as $date) {
        $markedMap = $markedIdsByDate[$date] ?? [];
        $unmarked = [];
        foreach ($usersById as $uid => $user) {
            if (!isset($markedMap[$uid])) {
                $unmarked[] = $user;
            }
        }
        $grouped[$date]['unmarked'] = $unmarked;
    }

    return $grouped;
}

function fetchAttendanceByStudentsOptimized($startDate, $endDate, $studentIds, $viewMode = false) {
    $dates = getDatesInRange($startDate, $endDate);
    $grouped = [];
    foreach ($dates as $d) {
        $grouped[$d] = ['marked' => [], 'unmarked' => []];
    }

    $studentIds = array_values(array_filter(array_map('strval', $studentIds), fn($v) => $v !== ''));

    $usersById = [];
    if (!empty($studentIds)) {
        $usersRepo = new \Performances\User();
        $ph = implode(',', array_fill(0, count($studentIds), '?'));
        $userRows = $usersRepo->query("SELECT id, first_name, last_name, image, gender, disabled FROM users WHERE deleted = 0 AND id IN ($ph)", $studentIds) ?: [];
        foreach ($userRows as $u) {
            $u = (array)$u;
            $u['image'] = get_image($u['image'] ?? '');
            $u['disabled'] = (int)($u['disabled'] ?? 0);
            $usersById[(string)$u['id']] = $u;
        }
    } else {
        $usersRepo = new \Performances\User();
        $all = $usersRepo->query("SELECT id, first_name, last_name, image, gender, disabled FROM users WHERE deleted = 0 ORDER BY first_name ASC, last_name ASC") ?: [];
        foreach ($all as $u) {
            $u = (array)$u;
            $u['image'] = get_image($u['image'] ?? '');
            $u['disabled'] = (int)($u['disabled'] ?? 0);
            $usersById[(string)$u['id']] = $u;
        }
        $studentIds = array_keys($usersById);
    }

    $att = new \Performances\Attendance();
    $params = ['startDate' => $startDate, 'endDate' => $endDate];
    $whereIn = '';
    if (!empty($studentIds)) {
        $ph = implode(',', array_fill(0, count($studentIds), '?'));
        $whereIn = " AND a.user_id IN ($ph)";
    }

    $sql = "SELECT a.user_id, a.date, u.first_name, u.last_name, u.image, u.gender, u.disabled, a.time, a.remarks
            FROM attendance a
            JOIN users u ON u.id = a.user_id
            WHERE a.deleted = 0 AND u.deleted = 0 AND a.date >= ? AND a.date <= ? $whereIn
            ORDER BY a.date ASC, a.time ASC";
    $execParams = [$startDate, $endDate];
    if (!empty($studentIds)) $execParams = array_merge($execParams, $studentIds);
    $rows = $att->query($sql, $execParams) ?: [];

    $markedIdsByDate = [];
    $num = 0;
    $workOpen = getWorkOpenTime() ?: '08:00:00';
    foreach ($rows as $row) {
        $r = (array)$row;
        $date = (string)($r['date'] ?? '');
        if (!isset($grouped[$date])) continue;
        $r['remarks'] = computeAttendanceRemarks($r['remarks'] ?? '', $r['time'] ?? '', $date, $workOpen);
        $r['image'] = get_image($r['image'] ?? '');
        $r['disabled'] = (int)($r['disabled'] ?? 0);
        if ($viewMode) {
            $num++;
            $r['date'] = get_date($r['date'] ?? '');
            $r['num'] = $num;
        }
        $grouped[$date]['marked'][] = $r;
        $uid = (string)($r['user_id'] ?? '');
        if (!isset($markedIdsByDate[$date])) $markedIdsByDate[$date] = [];
        $markedIdsByDate[$date][$uid] = true;
    }

    foreach ($dates as $date) {
        $markedMap = $markedIdsByDate[$date] ?? [];
        $unmarked = [];
        foreach ($studentIds as $sid) {
            if (!isset($markedMap[(string)$sid]) && isset($usersById[(string)$sid])) {
                $unmarked[] = $usersById[(string)$sid];
            }
        }
        $grouped[$date]['unmarked'] = $unmarked;
    }

    return $grouped;
}

function calculateGenderBreakdown($data) {
    $malePresent = 0; 
    $maleAbsent = 0;
    $femalePresent = 0; 
    $femaleAbsent = 0;
    foreach ($data as $row) {
        if (isset($row['gender'])) {
            $gender = strtolower($row['gender']);
            if (isset($row['remarks'])) {
                $rem = strtoupper((string)$row['remarks']);
                if ($rem === 'PRESENT' || $rem === 'LATE') {
                    if ($gender === 'male') $malePresent++;
                    else if ($gender === 'female') $femalePresent++;
                } else if ($rem === 'ABSENT') {
                    if ($gender === 'male') $maleAbsent++;
                    else if ($gender === 'female') $femaleAbsent++;
                }
            }
        }
    }
    return [
       'malePresent' => $malePresent,
       'maleAbsent' => $maleAbsent,
       'femalePresent' => $femalePresent,
       'femaleAbsent' => $femaleAbsent,
    ];
}

function getWorkOpenTime() {
    static $cached = null;
    static $loaded = false;
    if ($loaded) {
        return $cached;
    }
    $loaded = true;
    try {
        $db = new \Core\Database;
        if (!$db->table_exists(['settings'])) return null;
        $row = ($db->query("SELECT work_open FROM settings ORDER BY id DESC LIMIT 1") ?: [])[0] ?? null;
        $w = $row ? trim((string)($row->work_open ?? '')) : '';
        $cached = $w !== '' ? $w : null;
        return $cached;
    } catch (\Throwable $e) {
        return null;
    }
}

function getLateAttendanceTime() {
    static $cached = null;
    static $loaded = false;
    if ($loaded) {
        return $cached;
    }
    $loaded = true;
    try {
        $db = new \Core\Database;
        if (!$db->table_exists(['settings'])) return null;
        $row = ($db->query("SELECT late_attendance_time FROM settings ORDER BY id DESC LIMIT 1") ?: [])[0] ?? null;
        $value = $row ? trim((string)($row->late_attendance_time ?? '')) : '';
        if ($value === '') {
            $cached = null;
            return $cached;
        }
        if (strlen($value) === 5) $value .= ':00';
        $cached = $value;
        return $cached;
    } catch (\Throwable $e) {
        return null;
    }
}

function computeLateThreshold($workOpen) {
    $wo = trim((string)$workOpen);
    if ($wo === '') return '08:30:00';
    if (strlen($wo) === 5) $wo .= ':00';
    $ts = strtotime('1970-01-01 ' . $wo);
    if ($ts === false) return '08:30:00';
    return date('H:i:s', $ts + (30 * 60));
}

function getLateAttendanceThreshold($workOpen = null) {
    $configured = getLateAttendanceTime();
    if ($configured !== null) {
        return $configured;
    }

    $wo = $workOpen;
    if ($wo === null || trim((string)$wo) === '') {
        $wo = getWorkOpenTime() ?: '08:00:00';
    }

    return computeLateThreshold($wo);
}

function computeAttendanceRemarks($remarks, $time, $date, $workOpen = null) {
    $rem = strtoupper(trim((string)$remarks));
    if ($rem === '') return $remarks;
    if ($rem !== 'PRESENT' && $rem !== 'LATE') return $remarks;

    $t = trim((string)$time);
    if ($t === '') return $remarks;
    if (strlen($t) === 5) $t .= ':00';

    $threshold = getLateAttendanceThreshold($workOpen);

    $timeSec = strtotime('1970-01-01 ' . $t);
    $thrSec = strtotime('1970-01-01 ' . $threshold);
    if ($timeSec === false || $thrSec === false) return $remarks;

    return ($timeSec >= $thrSec) ? 'LATE' : 'PRESENT';
}
?>

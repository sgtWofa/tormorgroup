<?php

/**
 * Plugin name: Paymentservice
 * Description: A way for admin to manage payment_service
 * 
 * 
 **/

set_value([

	'admin_route'	=>'admin',
	'plugin_route'	=>'payment_services',
	'tables'		=>[
		'payment_service_table' 				=> 'payment_services',
	],

	'optional_tables'		=>[
	],
	

]);

if(!function_exists('payment_services_json_response')){
	function payment_services_json_response(array $payload, int $status = 200): void
	{
		$GLOBALS['payment_services_json_response_sent'] = true;
		while(ob_get_level() > 0){
			ob_end_clean();
		}
		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		exit;
	}
}

$paymentServicesJsonRoute = URL(1) === 'payment_services' && (string)(URL(2) ?? '') === 'data';
if($paymentServicesJsonRoute){
	ini_set('display_errors', '0');
	ini_set('display_startup_errors', '0');
	ob_start();
	$GLOBALS['payment_services_json_response_sent'] = false;
	register_shutdown_function(static function(): void {
		if(!empty($GLOBALS['payment_services_json_response_sent'])){
			return;
		}
		while(ob_get_level() > 0){
			ob_end_clean();
		}
		http_response_code(500);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(['success' => false, 'message' => 'Payment Services request failed. Please try again.'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
	});
	set_exception_handler(static function(\Throwable $exception): void {
		payment_services_json_response(['success' => false, 'message' => 'Payment Services request failed. Please try again.'], 500);
	});
}


/** check if all tables exist **/
$db = new \Core\Database;
$tables = get_value()['tables'];

if(!$db->table_exists($tables)){
	if($paymentServicesJsonRoute){
		payment_services_json_response(['success' => false, 'message' => 'Payment Services records are not available.'], 503);
	}
	dd("Missing database tables in ".plugin_id() ." plugin: ". implode(",", $db->missing_tables));
	die;
}

require_once plugin_path('helpers/transactions-helper.php');
payment_service_ensure_runtime_columns();


/** set payment_service permissions for this plugin **/
add_filter('permissions',function($permissions){

	$permissions[] = 'view_payment_service';
	$permissions[] = 'view_payment_service_details';
	$permissions[] = 'add_payment_service';
	$permissions[] = 'edit_payment_service';
	$permissions[] = 'delete_payment_service';

	return $permissions;
});


/** add to amin links **/
add_filter('basic-admin_before_admin_links', function ($links) {

    /*if (user_can('view_payment_service')) {
        $vars = get_value();

        // Main "Paymentservice" link object
        $obj = (object)[];
        $obj->title = 'Paymentservice';
        $obj->id = 'view_payment_service';
        $obj->link = ROOT . '/' . $vars['admin_route'] . '/' . $vars['plugin_route'];
        $obj->icon = 'fa-solid fa-credit-card'; // Icon for payment or card services
        $obj->parent = 0;

        $obj->sublinks = []; // Initialize sublinks array within the main link object

        // Add "Withdrawal" sublink
        $sub = (object)[];
        $sub->title = 'Withdrawal';
        $sub->link = ROOT . '/' . $vars['admin_route'] . '/' . $vars['plugin_route'] . '/withdrawal';
        $sub->icon = 'fa-solid fa-money-bill-wave'; // Icon for withdrawal
        $sub->parent = $obj->id;
        $sub->id = 'withdrawal';
        $obj->sublinks[] = $sub;

        // Add "Transfer" sublink
        $sub = (object)[];
        $sub->title = 'Transfer';
        $sub->link = ROOT . '/' . $vars['admin_route'] . '/' . $vars['plugin_route'] . '/transfer';
        $sub->icon = 'fa-solid fa-exchange-alt'; // Icon for transfer
        $sub->parent = $obj->id;
        $sub->id = 'transfer';
        $obj->sublinks[] = $sub;

        // Add "Withdrawal" sublink
        $sub = (object)[];
        $sub->title = 'View Transactions';
        $sub->link = ROOT . '/' . $vars['admin_route'] . '/' . $vars['plugin_route'];
        $sub->icon = 'fa-solid fa-eye'; // Icon for withdrawal
        $sub->parent = $obj->id;
        $sub->id = 'payment_service';
        $obj->sublinks[] = $sub;

        // Add the main "Paymentservice" link with sublinks to $links array
        $links[] = $obj;
    }*/

    return $links;
});



/** run this after a form submit **/
add_action('controller',function(){

	$req = new \Core\Request;
	$vars = get_value();
	
	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];

	$buildPaymentServicesFilters = function($session){
		$user_id = $session->id ?? 0;

		$find = trim($_GET['find'] ?? '');
		$dateFrom = $_GET['date_from'] ?? null;
		$dateTo = $_GET['date_to'] ?? null;
		$status = trim($_GET['transaction_status'] ?? '');
		$category = trim($_GET['category'] ?? '');
		$medium = trim($_GET['payment_medium'] ?? '');
		$networkParam = $_GET['network'] ?? null;

		$conditions = ['(m.deleted IS NULL OR m.deleted = 0)'];
		$params = [];

		if($find !== ''){
			$conditions[] = '(
				m.category LIKE :find OR
				m.institution LIKE :find OR
				m.customer_phone LIKE :find OR
				m.customer_id LIKE :find OR
				m.description LIKE :find OR
				m.id LIKE :find OR
				u.first_name LIKE :find OR
				u.last_name LIKE :find
			)';
			$params['find'] = "%$find%";
		}

		$normalizeDate = function($value){
			if(empty($value)) return null;
			$ts = strtotime($value);
			return $ts ? date('Y-m-d', $ts) : null;
		};

		if($dateFrom = $normalizeDate($dateFrom)){
			$conditions[] = 'DATE(m.date_created) >= :date_from';
			$params['date_from'] = $dateFrom;
		}
		if($dateTo = $normalizeDate($dateTo)){
			$conditions[] = 'DATE(m.date_created) <= :date_to';
			$params['date_to'] = $dateTo;
		}

		if($status !== ''){
			$conditions[] = 'LOWER(m.transaction_status) = :tx_status';
			$params['tx_status'] = strtolower($status);
		}
		if($category !== ''){
			$conditions[] = 'LOWER(m.category) = :category';
			$params['category'] = strtolower($category);
		}
		if($medium !== ''){
			$conditions[] = 'LOWER(m.payment_medium) = :medium';
			$params['medium'] = strtolower($medium);
		}
		if($networkParam !== null && $networkParam !== ''){
			if(is_array($networkParam)){
				$placeholders = [];
				foreach($networkParam as $idx => $net){
					$key = 'network_'.$idx;
					$placeholders[] = ':'.$key;
					$params[$key] = strtolower(trim($net));
				}
				if($placeholders){
					$conditions[] = 'LOWER(m.network) IN ('.implode(',', $placeholders).')';
				}
			}else{
				$conditions[] = 'LOWER(m.network) = :network';
				$params['network'] = strtolower(trim($networkParam));
			}
		}

		

		$whereSql = $conditions ? 'WHERE '.implode(' AND ', $conditions) : '';
		return [$whereSql, $params];
	};

	$csvEscape = function($value){
		$val = (string)$value;
		$val = str_replace("\r", ' ', $val);
		$val = str_replace("\n", ' ', $val);
		if(str_contains($val, ',') || str_contains($val, '"') || str_contains($val, "\n")){
			$val = '"'.str_replace('"', '""', $val).'"';
		}
		return $val;
	};

	$autoload = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
	if(file_exists($autoload)){
		require_once $autoload;
	}

	$clearOutputBuffers = function(){
		while(ob_get_level()){
			ob_end_clean();
		}
	};

	$sendXlsx = function(string $filename, string $sheetName, array $headers, array $rows) use ($clearOutputBuffers){
		if(!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')){
			throw new \Exception('PhpSpreadsheet library is missing.');
		}
		$clearOutputBuffers();
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle($sheetName);
		$startRow = 1;
		if(isset($GLOBALS['__payment_services_org_applier']) && is_callable($GLOBALS['__payment_services_org_applier'])){
			$startRow = (int)($GLOBALS['__payment_services_org_applier']($sheet, $startRow) ?? 1);
			if($startRow < 1) $startRow = 1;
		}
		$sheet->fromArray($headers, null, 'A' . $startRow);
		if(!empty($rows)){
			$sheet->fromArray($rows, null, 'A' . ($startRow + 1));
		}
		$lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
		$sheet->getStyle('A' . $startRow . ':' . $lastCol . $startRow)->getFont()->setBold(true);
		$sheet->freezePane('A' . ($startRow + 1));
		for($i=1;$i<=count($headers);$i++){
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

	$sendPdf = function(string $filename, string $title, string $html) use ($clearOutputBuffers){
		if(!class_exists('Mpdf\\Mpdf')){
			throw new \Exception('mPDF library is missing.');
		}
		$clearOutputBuffers();
		$mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir()]);
		$mpdf->SetTitle($title);
		$mpdf->WriteHTML($html);
		header('Content-Type: application/pdf');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		$mpdf->Output($filename, 'D');
		exit;
	};

	$formatDateHuman = function($value): string {
		$raw = trim((string)($value ?? ''));
		if($raw === '') return '';
		try{
			if(function_exists('get_date_time')){
				return (string)get_date_time($raw);
			}
			$ts = strtotime($raw);
			if($ts === false) return $raw;
			return date('d M Y, h:i A', $ts);
		} catch (\Throwable $e) {
			return $raw;
		}
	};

	$getOrg = function(){
		$org = [
			'name' => defined('APP_NAME') ? (string)APP_NAME : 'Organization',
			'title' => defined('APP_DESCRIPTION') ? (string)APP_DESCRIPTION : '',
			'phone' => '',
			'sim' => '',
			'logo' => defined('APP_LOGO2') ? (string)APP_LOGO2 : (defined('APP_LOGO') ? (string)APP_LOGO : ''),
		];
		try{
			$dbOrg = new \Core\Database;
			$row = $dbOrg->query("SELECT company_name, title, admin_contact, company_sim, image FROM settings ORDER BY id DESC LIMIT 1");
			if(!empty($row)){
				$s = $row[0];
				if(trim((string)($s->company_name ?? '')) !== '') $org['name'] = (string)$s->company_name;
				if(trim((string)($s->title ?? '')) !== '') $org['title'] = (string)$s->title;
				$org['phone'] = trim((string)($s->admin_contact ?? ''));
				$org['sim'] = trim((string)($s->company_sim ?? ''));
				$img = trim((string)($s->image ?? ''));
				if($img !== ''){
					if(function_exists('get_image')){
						$org['logo'] = (string)get_image($img);
					}else{
						$org['logo'] = $img;
					}
				}
			}
		} catch (\Throwable $e) {
		}
		return $org;
	};

	$escapeHtml = function($v){
		return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
	};

	$orgHeaderHtml = function(array $org) use ($escapeHtml){
		$lines = [];
		$rawTitle = trim((string)($org['title'] ?? ''));
		if($rawTitle !== '' && strtoupper($rawTitle) !== 'CEO'){
			$lines[] = $escapeHtml($rawTitle);
		}
		$contacts = [];
		if(trim((string)($org['phone'] ?? '')) !== '') $contacts[] = 'Office: ' . $escapeHtml($org['phone']);
		if(!empty($contacts)) $lines[] = implode(' | ', $contacts);
		if(trim((string)($org['sim'] ?? '')) !== '') $lines[] = 'Sim: ' . $escapeHtml($org['sim']);

		$logo = trim((string)($org['logo'] ?? ''));
		$logoHtml = $logo !== '' ? '<img src="' . $escapeHtml($logo) . '" style="height:48px;max-width:96px;object-fit:contain;">' : '';

		$out = '<table width="100%" cellspacing="0" cellpadding="0" style="border-bottom:1px solid #ddd;padding-bottom:8px;margin-bottom:10px">';
		$out .= '<tr>';
		$out .= '<td style="width:110px;vertical-align:middle">' . $logoHtml . '</td>';
		$out .= '<td style="vertical-align:middle">';
		$out .= '<div style="font-size:16px;font-weight:bold">' . $escapeHtml((string)($org['name'] ?? '')) . '</div>';
		foreach($lines as $ln){
			$out .= '<div style="font-size:11px;color:#444">' . $ln . '</div>';
		}
		$out .= '</td>';
		$out .= '</tr>';
		$out .= '</table>';
		return $out;
	};

	$applyOrgToSheet = function(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $startRow) use ($getOrg){
		$org = $getOrg();
		$row = $startRow;
		$sheet->setCellValue('A' . $row, (string)($org['name'] ?? ''));
		$sheet->mergeCells('A' . $row . ':F' . $row);
		$sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
		$row++;
		$rawTitle = trim((string)($org['title'] ?? ''));
		if($rawTitle !== '' && strtoupper($rawTitle) !== 'CEO'){
			$sheet->setCellValue('A' . $row, (string)$rawTitle);
			$sheet->mergeCells('A' . $row . ':F' . $row);
			$row++;
		}
		$contacts = [];
		if(trim((string)($org['phone'] ?? '')) !== '') $contacts[] = 'Office: ' . (string)$org['phone'];
		if(!empty($contacts)){
			$sheet->setCellValue('A' . $row, implode(' | ', $contacts));
			$sheet->mergeCells('A' . $row . ':F' . $row);
			$row++;
		}
		if(trim((string)($org['sim'] ?? '')) !== ''){
			$sheet->setCellValue('A' . $row, 'Sim: ' . (string)$org['sim']);
			$sheet->mergeCells('A' . $row . ':F' . $row);
			$row++;
		}
		$row++;
		return $row;
	};

	$GLOBALS['__payment_services_org_applier'] = function($sheet, $startRow) use ($applyOrgToSheet){
		return $applyOrgToSheet($sheet, (int)$startRow);
	};

	if(URL(1) == $plugin_route && URL(2) == 'export'){
		if(!user_can('view_payment_service')){
			http_response_code(403);
			echo 'Access denied';
			exit;
		}
		$type = strtolower((string)(URL(3) ?? ''));
		$session = get_session();
		$db = new \Core\Database;
		list($whereSql, $params) = $buildPaymentServicesFilters($session);
		$rowsSql = "SELECT 
			m.id,
			m.category,
			m.institution,
			m.payment_medium,
			m.bank,
			m.network,
			m.customer_phone,
			m.customer_id,
			m.description,
			m.amount,
			m.amount_paid,
			m.charge,
			m.transaction_status,
			m.date_created,
			m.created_by,
			u.first_name AS user_first_name,
			u.last_name AS user_last_name
		FROM payment_services AS m
		LEFT JOIN users AS u ON m.created_by = u.id
		$whereSql
		ORDER BY m.date_created DESC";
		$rows = $db->query($rowsSql, $params) ?: [];

		if($type === 'excel'){
			$filename = 'payment_services_'.date('Ymd_His').'.xlsx';
			$headers = ['ID','Reference','Date','Category','Institution','Medium','Bank','Network','Customer Phone','Customer ID','Description','Amount','Amount Paid','Charge','Status','Staff'];
			$outRows = [];
			foreach($rows as $r){
				$staff = trim(($r->user_first_name ?? '').' '.($r->user_last_name ?? ''));
				$outRows[] = [
					(string)($r->id ?? ''),
					(string)('PS'.($r->id ?? '')),
					(string)$formatDateHuman($r->date_created ?? ''),
					(string)($r->category ?? ''),
					(string)($r->institution ?? ''),
					(string)($r->payment_medium ?? ''),
					(string)($r->bank ?? ''),
					(string)($r->network ?? ''),
					(string)($r->customer_phone ?? ''),
					(string)($r->customer_id ?? ''),
					(string)($r->description ?? ''),
					(string)($r->amount ?? ''),
					(string)($r->amount_paid ?? ''),
					(string)($r->charge ?? ''),
					(string)($r->transaction_status ?? ''),
					(string)$staff,
				];
			}
			$sendXlsx($filename, 'Payment Services', $headers, $outRows);
		}

		if($type === 'pdf'){
			$filename = 'payment_services_'.date('Ymd_His').'.pdf';
			$html = '<html><head><meta charset="utf-8"><style>'
				.'body{font-family:DejaVu Sans, sans-serif;font-size:11px;}'
				.'h2{margin:0 0 10px 0;}'
				.'table{width:100%;border-collapse:collapse;}'
				.'th,td{border:1px solid #ddd;padding:6px;vertical-align:top;}'
				.'th{background:#f3f4f6;text-align:left;}'
				.'</style></head><body>';
			$html .= $orgHeaderHtml($getOrg());
			$html .= '<h2>Payment Services</h2>';
			$html .= '<table><thead><tr>'
				.'<th>ID</th><th>Date</th><th>Category</th><th>Institution</th><th>Medium</th><th>Bank</th><th>Network</th><th>Phone</th><th>Amount</th><th>Charge</th><th>Status</th><th>Staff</th>'
				.'</tr></thead><tbody>';
			foreach($rows as $r){
				$staff = trim(($r->user_first_name ?? '').' '.($r->user_last_name ?? ''));
				$html .= '<tr>'
					.'<td>'.htmlspecialchars((string)($r->id ?? '')).'</td>'
					.'<td>'.htmlspecialchars((string)$formatDateHuman($r->date_created ?? '')).'</td>'
					.'<td>'.htmlspecialchars((string)($r->category ?? '')).'</td>'
					.'<td>'.htmlspecialchars((string)($r->institution ?? '')).'</td>'
					.'<td>'.htmlspecialchars((string)($r->payment_medium ?? '')).'</td>'
					.'<td>'.htmlspecialchars((string)($r->bank ?? '')).'</td>'
					.'<td>'.htmlspecialchars((string)($r->network ?? '')).'</td>'
					.'<td>'.htmlspecialchars((string)($r->customer_phone ?? '')).'</td>'
					.'<td style="text-align:right;">'.htmlspecialchars((string)($r->amount ?? '')).'</td>'
					.'<td style="text-align:right;">'.htmlspecialchars((string)($r->charge ?? '')).'</td>'
					.'<td>'.htmlspecialchars((string)($r->transaction_status ?? '')).'</td>'
					.'<td>'.htmlspecialchars((string)($staff ?: '—')).'</td>'
					.'</tr>';
			}
			$html .= '</tbody></table>';
			$html .= '</body></html>';

			$sendPdf($filename, 'Payment Services List Export', $html);
		}
	}

	if(URL(1) == $plugin_route && URL(2) == 'export_view'){
		if(!user_can('view_payment_service_details')){
			http_response_code(403);
			echo 'Access denied';
			exit;
		}
		$id = URL(3) ?? null;
		if(!$id){
			http_response_code(400);
			echo 'Missing ID';
			exit;
		}

		$type = strtolower((string)(URL(4) ?? ''));
		$db = new \Core\Database;
		$rowSql = "SELECT 
			m.*,
			u.first_name AS user_first_name,
			u.last_name AS user_last_name,
			u.image AS user_image,
			u2.first_name AS updated_by_first_name,
			u2.last_name AS updated_by_last_name,
			u2.image AS updated_by_image
		FROM payment_services AS m
		LEFT JOIN users AS u ON m.created_by = u.id
		LEFT JOIN users AS u2 ON m.updated_by = u2.id
		WHERE m.id = :id
		LIMIT 1";
		$row = ($db->query($rowSql, ['id' => $id]) ?: [])[0] ?? null;
		if(empty($row)){
			http_response_code(404);
			echo 'Record not found';
			exit;
		}

		$denos = [];
		$receivedDenos = [];
		$changeDenos = [];
		$receivedTotalCount = 0;
		$receivedTotalVolume = 0.0;
		$changeTotalCount = 0;
		$changeTotalVolume = 0.0;
		$hasChangeGiven = false;
		try{
			$denos = $db->query("SELECT denomination, count, volume FROM denos WHERE trans_id = :id ORDER BY denomination DESC", ['id' => $id]) ?: [];

			$receivedDenosMap = [];
			$changeDenosMap = [];
			foreach($denos as $denoRow){
				$denomination = (string)($denoRow->denomination ?? '');
				$count = (float)($denoRow->count ?? 0);
				$volume = (float)($denoRow->volume ?? 0);
				$isChangeRow = ($count < 0 || $volume < 0);
				$displayCount = abs($count);
				$displayVolume = abs($volume);
				$targetMap = $isChangeRow ? $changeDenosMap : $receivedDenosMap;

				if(!isset($targetMap[$denomination])){
					$item = new \stdClass();
					$item->denomination = $denomination;
					$item->count = 0;
					$item->volume = 0;
					$targetMap[$denomination] = $item;
				}
				$targetMap[$denomination]->count += $displayCount;
				$targetMap[$denomination]->volume += $displayVolume;

				if($isChangeRow){
					$changeTotalCount += $displayCount;
					$changeTotalVolume += $displayVolume;
					$changeDenosMap = $targetMap;
				}else{
					$receivedTotalCount += $displayCount;
					$receivedTotalVolume += $displayVolume;
					$receivedDenosMap = $targetMap;
				}
			}

			$receivedDenos = array_values($receivedDenosMap);
			$changeDenos = array_values($changeDenosMap);
			$sortDenos = function($a, $b){
				$aVal = (float)($a->denomination ?? 0);
				$bVal = (float)($b->denomination ?? 0);
				if($aVal == $bVal) return 0;
				return ($aVal > $bVal) ? -1 : 1;
			};
			usort($receivedDenos, $sortDenos);
			usort($changeDenos, $sortDenos);
			$hasChangeGiven = !empty($changeDenos) && $changeTotalVolume > 0;
		} catch (\Throwable $e) {
			$denos = [];
		}

		if($type === 'excel'){
			$filename = 'payment_service_'.$id.'_'.date('Ymd_His').'.xlsx';
			$headers = ['Field','Value'];
			$outRows = [];
			$fields = [
				'ID' => $row->id ?? '',
				'Date' => $formatDateHuman($row->date_created ?? ''),
				'Category' => $row->category ?? '',
				'Institution' => $row->institution ?? '',
				'Customer Phone' => $row->customer_phone ?? '',
				'Customer ID' => $row->customer_id ?? '',
				'Description' => $row->description ?? '',
				'Payment Medium' => $row->payment_medium ?? '',
				'Network' => $row->network ?? '',
				'Wallet Out ID' => $row->wallet_out_id ?? '',
				'Amount' => $row->amount ?? '',
				'Amount Paid' => $row->amount_paid ?? '',
				'Charge' => $row->charge ?? '',
				'Status' => $row->transaction_status ?? '',
				'Created By' => trim(($row->user_first_name ?? '').' '.($row->user_last_name ?? '')),
				'Last Updated' => $formatDateHuman($row->date_updated ?? ''),
				'Updated By' => trim(($row->updated_by_first_name ?? '').' '.($row->updated_by_last_name ?? '')),
			];
			if($hasChangeGiven){
				$fields['Change Given'] = number_format((float)$changeTotalVolume, 2, '.', '');
			}
			foreach($fields as $k => $v){
				$outRows[] = [(string)$k, (string)$v];
			}
			if(!empty($receivedDenos) || !empty($changeDenos)){
				$outRows[] = ['', ''];
				if($hasChangeGiven){
					$outRows[] = ['Received Denominations', ''];
				}
				$outRows[] = ['Denomination','Count / Volume'];
				foreach($receivedDenos as $d){
					$outRows[] = [
						(string)($d->denomination ?? ''),
						(string)(($d->count ?? 0).' / '.($d->volume ?? 0)),
					];
				}
				$outRows[] = ['SUMMARY', (string)($receivedTotalCount.' / '.$receivedTotalVolume)];

				if($hasChangeGiven){
					$outRows[] = ['', ''];
					$outRows[] = ['Change Given', ''];
					$outRows[] = ['Denomination','Count / Volume'];
					foreach($changeDenos as $d){
						$outRows[] = [
							(string)($d->denomination ?? ''),
							(string)(($d->count ?? 0).' / '.($d->volume ?? 0)),
						];
					}
					$outRows[] = ['CHANGE TOTAL', (string)($changeTotalCount.' / '.$changeTotalVolume)];
				}
			}
			$sendXlsx($filename, 'Payment Service', $headers, $outRows);
		}

		if($type === 'pdf'){
			$filename = 'payment_service_'.$id.'_'.date('Ymd_His').'.pdf';
			$html = '<html><head><meta charset="utf-8"><style>'
				.'body{font-family:DejaVu Sans, sans-serif;font-size:11px;}'
				.'h2{margin:0 0 8px 0;}'
				.'table{width:100%;border-collapse:collapse;}'
				.'th,td{border:1px solid #ddd;padding:6px;vertical-align:top;}'
				.'th{background:#f3f4f6;text-align:left;}'
				.'</style></head><body>';
			$html .= $orgHeaderHtml($getOrg());
			$html .= '<h2>Payment Service - '.htmlspecialchars((string)($row->id ?? '')).'</h2>';
			$html .= '<table>';
			$renderRow = function($k, $v) use (&$html){
				$html .= '<tr><th style="width:28%;">'.htmlspecialchars((string)$k).'</th><td>'.htmlspecialchars((string)$v).'</td></tr>';
			};
			$renderRow('Date', $formatDateHuman($row->date_created ?? ''));
			$renderRow('Category', $row->category ?? '');
			$renderRow('Institution', $row->institution ?? '');
			$renderRow('Customer Phone', $row->customer_phone ?? '');
			$renderRow('Customer ID', $row->customer_id ?? '');
			$renderRow('Description', $row->description ?? '');
			$renderRow('Payment Medium', $row->payment_medium ?? '');
			$renderRow('Network', $row->network ?? '');
			$renderRow('Wallet Out ID', $row->wallet_out_id ?? '');
			$renderRow('Amount', $row->amount ?? '');
			$renderRow('Amount Paid', $row->amount_paid ?? '');
			if($hasChangeGiven){
				$renderRow('Change Given', number_format((float)$changeTotalVolume, 2));
			}
			$renderRow('Charge', $row->charge ?? '');
			$renderRow('Status', $row->transaction_status ?? '');
			$renderRow('Created By', trim(($row->user_first_name ?? '').' '.($row->user_last_name ?? '')));
			if(!empty($row->date_updated)){
				$renderRow('Last Updated', $formatDateHuman($row->date_updated ?? ''));
				$renderRow('Updated By', trim(($row->updated_by_first_name ?? '').' '.($row->updated_by_last_name ?? '')));
			}
			$html .= '</table>';

			if(!empty($receivedDenos) || !empty($changeDenos)){
				$html .= '<h2 style="margin-top:14px;">Denominations Breakdown</h2>';
				$html .= '<table><thead><tr><th>Denomination</th><th>Count</th><th>Volume</th></tr></thead><tbody>';
				foreach($receivedDenos as $d){
					$html .= '<tr>'
						.'<td>'.htmlspecialchars((string)($d->denomination ?? '')).'</td>'
						.'<td>'.htmlspecialchars((string)($d->count ?? 0)).'</td>'
						.'<td>'.htmlspecialchars((string)($d->volume ?? 0)).'</td>'
						.'</tr>';
				}
				$html .= '<tr>'
					.'<th>SUMMARY</th>'
					.'<th>'.htmlspecialchars((string)$receivedTotalCount).'</th>'
					.'<th>'.htmlspecialchars((string)$receivedTotalVolume).'</th>'
					.'</tr>';
				$html .= '</tbody></table>';

				if($hasChangeGiven){
					$html .= '<h2 style="margin-top:14px;">Change Given</h2>';
					$html .= '<table><thead><tr><th>Denomination</th><th>Count</th><th>Volume</th></tr></thead><tbody>';
					foreach($changeDenos as $d){
						$html .= '<tr>'
							.'<td>'.htmlspecialchars((string)($d->denomination ?? '')).'</td>'
							.'<td>'.htmlspecialchars((string)($d->count ?? 0)).'</td>'
							.'<td>'.htmlspecialchars((string)($d->volume ?? 0)).'</td>'
							.'</tr>';
					}
					$html .= '<tr>'
						.'<th>CHANGE TOTAL</th>'
						.'<th>'.htmlspecialchars((string)$changeTotalCount).'</th>'
						.'<th>'.htmlspecialchars((string)$changeTotalVolume).'</th>'
						.'</tr>';
					$html .= '</tbody></table>';
				}
			}

			$html .= '</body></html>';
			$sendPdf($filename, 'Payment Service Export', $html);
		}
	}

	// JSON data endpoint for AJAX dashboard
	if(URL(1) == $plugin_route && URL(2) == 'data'){
		try{
			if(!user_can('view_payment_service')){
				payment_services_json_response(['success' => false, 'message' => 'Access denied'], 403);
			}
			$db = new \Core\Database;
			$session = get_session();
			$user_id = $session->id ?? 0;
			

			$find = trim($_GET['find'] ?? '');
			$dateFrom = $_GET['date_from'] ?? null;
			$dateTo = $_GET['date_to'] ?? null;
			$status = trim($_GET['transaction_status'] ?? '');
			$category = trim($_GET['category'] ?? '');
			$medium = trim($_GET['payment_medium'] ?? '');
			$networkParam = $_GET['network'] ?? null;
			$page = max(1, (int)($_GET['page'] ?? 1));
			$limit = max(5, min(100, (int)($_GET['limit'] ?? 10)));
			$offset = ($page - 1) * $limit;

			$conditions = ['(m.deleted IS NULL OR m.deleted = 0)'];
			$params = [];

			if($find !== ''){
				$conditions[] = '(
					m.category LIKE :find OR
					m.institution LIKE :find OR
					m.customer_phone LIKE :find OR
					m.customer_id LIKE :find OR
					m.description LIKE :find OR
					m.id LIKE :find OR
					u.first_name LIKE :find OR
					u.last_name LIKE :find
				)';
				$params['find'] = "%$find%";
			}

			$normalizeDate = function($value){
				if(empty($value)) return null;
				$ts = strtotime($value);
				return $ts ? date('Y-m-d', $ts) : null;
			};

			if($dateFrom = $normalizeDate($dateFrom)){
				$conditions[] = 'DATE(m.date_created) >= :date_from';
				$params['date_from'] = $dateFrom;
			}
			if($dateTo = $normalizeDate($dateTo)){
				$conditions[] = 'DATE(m.date_created) <= :date_to';
				$params['date_to'] = $dateTo;
			}

			if($status !== ''){
				$conditions[] = 'LOWER(m.transaction_status) = :tx_status';
				$params['tx_status'] = strtolower($status);
			}
			if($category !== ''){
				$conditions[] = 'LOWER(m.category) = :category';
				$params['category'] = strtolower($category);
			}
			if($medium !== ''){
				$conditions[] = 'LOWER(m.payment_medium) = :medium';
				$params['medium'] = strtolower($medium);
			}
			if($networkParam !== null && $networkParam !== ''){
				if(is_array($networkParam)){
					$placeholders = [];
					foreach($networkParam as $idx => $net){
						$key = 'network_'.$idx;
						$placeholders[] = ':'.$key;
						$params[$key] = strtolower(trim($net));
					}
					if($placeholders){
						$conditions[] = 'LOWER(m.network) IN ('.implode(',', $placeholders).')';
					}
				}else{
					$conditions[] = 'LOWER(m.network) = :network';
					$params['network'] = strtolower(trim($networkParam));
				}
			}

			

			$whereSql = $conditions ? 'WHERE '.implode(' AND ', $conditions) : '';
			$limitClause = " LIMIT $limit OFFSET $offset";

			$rowsSql = "SELECT 
				m.id,
				m.category,
				m.institution,
				m.payment_medium,
				m.bank,
				m.network,
				m.customer_phone,
				m.amount,
				m.amount_paid,
				m.charge,
				m.transaction_status,
				m.rejection_reason,
				m.date_created,
				m.created_by,
				u.first_name AS user_first_name,
				u.last_name AS user_last_name,
				u.image AS user_image
			FROM payment_services AS m
			LEFT JOIN users AS u ON m.created_by = u.id
			$whereSql
			ORDER BY m.date_created DESC $limitClause";

			$rows = $db->query($rowsSql, $params) ?: [];

			$totalsSql = "SELECT 
				COUNT(*) AS total_rows,
				COALESCE(SUM(m.amount),0) AS total_amount,
				COALESCE(SUM(m.charge),0) AS total_charge
			FROM payment_services AS m
			$whereSql";
			$totalsRow = ($db->query($totalsSql, $params) ?: [])[0] ?? (object)['total_rows'=>0,'total_amount'=>0,'total_charge'=>0];
			$totalRows = (int)$totalsRow->total_rows;
			$totalPages = $totalRows ? (int)ceil($totalRows / $limit) : 1;
			$totalAmount = (float)$totalsRow->total_amount;
			$totalCharge = (float)$totalsRow->total_charge;

			$latestSql = "SELECT m.date_created FROM payment_services AS m $whereSql ORDER BY m.date_created DESC LIMIT 1";
			$latestActivity = ($db->query($latestSql, $params) ?: [])[0]->date_created ?? null;

			$rowsPayload = array_map(function($row){
				$agentName = trim(($row->user_first_name ?? '').' '.($row->user_last_name ?? ''));
				return [
					'id' => (int)$row->id,
					'reference' => 'PS'.(int)$row->id,
					'category' => $row->category ?? '',
					'institution' => $row->institution ?? '',
					'payment_medium' => $row->payment_medium ?? '',
					'bank' => $row->bank ?? '',
					'network' => $row->network ?? '',
					'phone' => $row->customer_phone ?? '',
					'amount' => (float)$row->amount,
					'amount_paid' => isset($row->amount_paid) ? (float)$row->amount_paid : null,
					'charge' => (float)$row->charge,
					'transaction_status' => $row->transaction_status ?? '',
					'rejection_reason' => trim((string)($row->rejection_reason ?? '')),
					'date_created' => $row->date_created,
					'agent' => [
						'id' => (int)$row->created_by,
						'name' => $agentName ?: '—',
						'image' => get_image($row->user_image ?? ''),
					],
				];
			}, $rows);

			// Top agents by volume
			$topAgentsSql = "SELECT 
				m.created_by,
				COUNT(*) AS tx_count,
				COALESCE(SUM(m.amount),0) AS total_amount,
				u.first_name,
				u.last_name
			FROM payment_services AS m
			LEFT JOIN users AS u ON m.created_by = u.id
			$whereSql
			GROUP BY m.created_by, u.first_name, u.last_name
			ORDER BY total_amount DESC
			LIMIT 5";
			$topAgentsRows = $db->query($topAgentsSql, $params) ?: [];
			$topAgents = array_map(function($row){
				return [
					'id' => (int)$row->created_by,
					'name' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')) ?: '—',
					'count' => (int)$row->tx_count,
					'total' => (float)$row->total_amount,
				];
			}, $topAgentsRows);

			// Status breakdown
			$statusSql = "SELECT m.transaction_status AS label, COUNT(*) AS tx_count, COALESCE(SUM(m.amount),0) AS total_amount FROM payment_services AS m $whereSql GROUP BY m.transaction_status";
			$statusRows = $db->query($statusSql, $params) ?: [];
			$statusBreakdown = array_map(function($row) use ($totalAmount){
				$amount = (float)$row->total_amount;
				return [
					'label' => $row->label ?: 'Unspecified',
					'amount' => $amount,
					'count' => (int)$row->tx_count,
					'percent' => $totalAmount > 0 ? ($amount / $totalAmount) : 0,
				];
			}, $statusRows);

			// 7-day volume trend
			$trendParams = $params;
			$trendStart = date('Y-m-d', strtotime('-6 days'));
			$trendWhere = $whereSql ? $whereSql.' AND DATE(m.date_created) >= :trend_start' : 'WHERE DATE(m.date_created) >= :trend_start';
			$trendParams['trend_start'] = $trendStart;
			$trendSql = "SELECT DATE(m.date_created) AS label, COUNT(*) AS tx_count, COALESCE(SUM(m.amount),0) AS total_amount FROM payment_services AS m $trendWhere GROUP BY DATE(m.date_created) ORDER BY DATE(m.date_created) ASC";
			$trendRows = $db->query($trendSql, $trendParams) ?: [];
			$trend = array_map(function($row){
				$labelDate = $row->label ?? '';
				return [
					'label' => $labelDate ? date('M j', strtotime($labelDate)) : '—',
					'count' => (int)$row->tx_count,
					'amount' => (float)$row->total_amount,
				];
			}, $trendRows);

			$avgTicket = $totalRows ? ($totalAmount / $totalRows) : 0;
			$chargeRate = $totalAmount > 0 ? ($totalCharge / $totalAmount) : 0;

			payment_services_json_response([
				'success' => true,
				'rows' => $rowsPayload,
				'pagination' => [
					'page' => $page,
					'limit' => $limit,
					'total_rows' => $totalRows,
					'total_pages' => $totalPages,
				],
				'totals' => [
					'total_amount' => $totalAmount,
					'total_charge' => $totalCharge,
					'count' => $totalRows,
				],
				'latest_activity' => $latestActivity,
				'analysis' => [
					'avg_ticket' => $avgTicket,
					'charge_rate' => $chargeRate,
					'top_agents' => $topAgents,
					'status_breakdown' => $statusBreakdown,
					'trend' => $trend,
				],
			]);
		} catch (\Throwable $th) {
			payment_services_json_response(['success' => false, 'message' => 'Failed to fetch data', 'error' => $th->getMessage()], 500);
		}
	}

	if(URL(1) == $vars['plugin_route'] && $req->posted())
	{
		$ses = new \Core\Session;
		$payment_service = new \PaymentService\Paymentservice;
		$transaction = new \PaymentService\Transaction;
		$wallet = new \PaymentService\Wallet;
		$user_roles_map = new \PaymentService\User_roles_map;

		$id = URL(3) ?? null;
		if($id)
			$row = $payment_service->first(['id'=>$id]);


		if(URL(2) == 'add'){
			require plugin_path('controllers/add-controller.php');
		}else
		if(URL(2) == 'edit'){			
			require plugin_path('controllers/edit-controller.php');
		}else
		if(URL(2) == 'delete'){
			require plugin_path('controllers/delete-controller.php');
		}

	}
});


/** displays the view file **/
add_action('basic-admin_main_content',function(){

	$ses = new \Core\Session;
	$vars = get_value();

	

	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];
	
	$errors = $vars['errors'] ?? [];

	$payment_service = new \PaymentService\Paymentservice;
	$transaction = new \PaymentService\Transaction;
	$wallet = new \PaymentService\Wallet;
	$deno = new \PaymentService\Deno;
	$db = new \Core\Database;

	$ensurePaymentServiceColumns = function() {
		try {
			$dbSchema = new \Core\Database;
			$neededCols = [
				'amount_paid',
				'assigned_to',
				'transaction_image',
				'change_required',
				'change_denominations',
				'cash_received_amount',
				'rejection_reason',
			];
			$missing = [];
			foreach ($neededCols as $col) {
				$hasCol = $dbSchema->query(
					"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'payment_services' AND COLUMN_NAME = :col LIMIT 1",
					['db' => DB_NAME, 'col' => $col]
				);
				if (empty($hasCol)) {
					$missing[] = $col;
				}
			}
			if (!empty($missing)) {
				$typeMap = [
					'amount_paid' => 'FLOAT(10,2) DEFAULT 0.00',
					'assigned_to' => 'INT(11) DEFAULT 0',
					'transaction_image' => 'VARCHAR(255) NULL',
				'change_required' => 'FLOAT(10,2) DEFAULT 0.00',
				'change_denominations' => 'LONGTEXT NULL',
				'cash_received_amount' => 'FLOAT(10,2) DEFAULT 0.00',
				'rejection_reason' => 'TEXT NULL',
			];
				foreach ($missing as $col) {
					$colType = $typeMap[$col] ?? 'VARCHAR(255) NULL';
					try {
						$dbSchema->query("ALTER TABLE payment_services ADD COLUMN {$col} {$colType}");
					} catch (\Throwable $e) {
						// ignore schema errors in production
					}
				}
			}
		} catch (\Throwable $e) {
			// ignore schema errors
		}
	};

	if(URL(1) == $vars['plugin_route']){

		$ensurePaymentServiceColumns();

		$id = URL(3) ?? null;
		$wallets = $wallet->where(['deleted'=>0, 'transaction_status'=>'Approved']);

		if($id){
			$payment_service::$query_id = 'get-users';
			
			$row = $payment_service->first(['id'=>$id]);
		}

		$session = get_session();
		if ($session) {
			$username = $session->first_name . ' '. $session->last_name;
			$user_id = $session->id;
		}

		$eligible_assignees = [];
		try {
			$dbEligible = new \Core\Database;
			$todayEligible = date('Y-m-d');
			if ($dbEligible->table_exists(['users', 'cashdrawers', 'attendance', 'user_roles', 'user_roles_map'])) {
				$eligible_assignees = $dbEligible->query(
					"SELECT DISTINCT u.id, u.first_name, u.last_name
					 FROM users u
					 JOIN user_roles_map urm ON urm.user_id = u.id AND urm.disabled = 0
					 JOIN user_roles ur ON ur.id = urm.role_id AND ur.disabled = 0
					 LEFT JOIN cashdrawers c ON c.user_id = u.id AND DATE(c.date_created) = :d
					 LEFT JOIN attendance a ON a.user_id = u.id AND a.deleted = 0 AND a.date = :d AND UPPER(COALESCE(a.remarks,'')) IN ('PRESENT','LATE')
					 WHERE u.deleted = 0
					 AND (c.id IS NOT NULL OR a.id IS NOT NULL)
					 AND UPPER(REPLACE(ur.role,'_',' ')) IN ('ADMIN','GENERAL MANAGER')
					 ORDER BY u.first_name ASC, u.last_name ASC, u.id ASC",
					['d' => $todayEligible]
				) ?: [];
			}
		} catch (\Throwable $e) {
			$eligible_assignees = [];
		}

		if(URL(2) == 'add'){
			$payment_service_role = new \PaymentService\User_role;
			$cashdrawerDenos = [];
			$cashdrawerBalances = [];
			try {
				$drawerDb = new \Core\Database;
				$today = date('Y-m-d');
				$drawerRow = $drawerDb->query("SELECT id FROM cashdrawers WHERE user_id = :user_id AND DATE(date_created) = :today ORDER BY id DESC LIMIT 1", ['user_id' => $session->id, 'today' => $today]);
				if (empty($drawerRow)) {
					$drawerRow = $drawerDb->query("SELECT id FROM cashdrawers WHERE user_id = :user_id ORDER BY date_created DESC, id DESC LIMIT 1", ['user_id' => $session->id]);
				}
				if (!empty($drawerRow)) {
					$drawer_id = (int)$drawerRow[0]->id;
					$denoRows = $drawerDb->query("SELECT denomination, SUM(count) as count FROM drawer_denos WHERE drawer_id = :drawer_id GROUP BY denomination", ['drawer_id' => $drawer_id]);
					if (!empty($denoRows)) {
						foreach ($denoRows as $row) {
							$cashdrawerDenos[] = [
								'denomination' => $row->denomination,
								'count' => $row->count
							];
						}
					}
					$balRows = $drawerDb->query("SELECT channel_key, closing_balance FROM drawer_balances WHERE drawer_id = :drawer_id", ['drawer_id' => $drawer_id]);
					if (!empty($balRows)) {
						foreach ($balRows as $row) {
							$cashdrawerBalances[] = [
								'channel_key' => $row->channel_key,
								'closing_balance' => $row->closing_balance
							];
						}
					}
				}
			} catch (\Throwable $e) {
				// fail silently
			}
			require plugin_path('views/admin/add.php');
		}else
		if(URL(2) == 'edit'){			
			$payment_service_role = new \PaymentService\User_role;
			require plugin_path('views/admin/edit.php');
		}else
		if(URL(2) == 'delete'){

			require plugin_path('views/admin/delete.php');
		}else
		if(URL(2) == 'view'){

			$query = "
			    SELECT 
			        m.*, 
			        u.first_name AS user_first_name, 
			        u.last_name AS user_last_name, 
			        u.image AS user_image,
			        w.name AS wallet_out_name,
			        w.network AS wallet_out_network,
			        u.image AS user_image,
			        u_updated.first_name AS updated_by_first_name, 
			        u_updated.last_name AS updated_by_last_name, 
			        u_updated.image AS updated_by_image 
			    FROM payment_services AS m 
			    LEFT JOIN mywallets AS w ON m.wallet_out_id = w.id 
			    LEFT JOIN users AS u ON m.created_by = u.id 
			    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			    WHERE m.id = :id 
			    LIMIT 1";

				$row = $payment_service->query($query, ['id'=>$id])[0];
				$query = "SELECT * FROM denos WHERE trans_id =:trans_id ORDER BY denomination DESC";
				$denos = $deno->query($query, ['trans_id'=>$id]);
				$totalCount = 0;
				$totalVolume = 0;

			require plugin_path('views/admin/view.php');
		}else
		{
			$payment_service = new \PaymentService\Paymentservice;
			$user_roles_map = new \PaymentService\User_roles_map;
			$limit = 30;
			$pager = new \Core\Pager($limit);
			$offset = $pager->offset;

			$payment_service->limit = $limit;
			$payment_service->offset = $offset;
			$payment_service::$query_id = 'get-users';
			
			if (!empty($_GET['find'])) {
			    $find = '%' . trim($_GET['find']) . '%';
			    $query = "
			        SELECT
			            m.*, 
			            u.first_name AS user_first_name, 
			            u.last_name AS user_last_name, 
			            u.image AS user_image,
			            u_updated.first_name AS updated_by_first_name, 
			            u_updated.last_name AS updated_by_last_name, 
			            u_updated.image AS updated_by_image  
			        FROM payment_services AS m 
			        LEFT JOIN users AS u ON m.created_by = u.id 
			        LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			        WHERE (
			        	m.network LIKE :find 
			            OR m.id LIKE :find 
			            OR m.transaction_type LIKE :find 
			            OR u.first_name LIKE :find 
			            OR u.last_name LIKE :find 
			            OR m.phone LIKE :find
			            OR m.transaction_status LIKE :find
			            OR m.date_created LIKE :find)
			        LIMIT $limit OFFSET $offset";

			    $rows = $payment_service->query($query, ['find' => $find]);
			  }
			else{
				$query = "
				    SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name, 
				        u_updated.last_name AS updated_by_last_name, 
				        u_updated.image AS updated_by_image 
				    FROM payment_services AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
				    WHERE m.created_by = :user_id
				    ORDER BY m.id DESC
				";

				$rows = $payment_service->query($query, ['user_id'=>$user_id]);
			}

			$query = "SELECT SUM(amount) AS total_amount, SUM(charge) AS total_charge, COUNT(id) AS total_qty FROM payment_services WHERE deleted = 0 AND transaction_status = :transaction_status AND created_by = $user_id";
			$total_approved = $payment_service->query($query, ['transaction_status'=>'Approved'])[0];
			$totalApproved_amount = $total_approved->total_amount ?? 0;
			$totalApproved_charge = $total_approved->total_charge ?? 0;
			$totalApproved_qty = $total_approved->total_qty ?? 0;

			$total_pending = $payment_service->query($query, ['transaction_status'=>'Pending'])[0];
			$totalPending_amount = $total_pending->total_amount ?? 0;
			$totalPending_charge = $total_pending->total_charge ?? 0;
			$totalPending_qty = $total_pending->total_qty ?? 0;

			$total_draft = $payment_service->query($query, ['transaction_status'=>'Draft'])[0];
			$totalDraft_amount = $total_draft->total_amount ?? 0;
			$totalDraft_charge = $total_draft->total_charge ?? 0;
			$totalDraft_qty = $total_draft->total_qty ?? 0;

			$total_rejected = $payment_service->query($query, ['transaction_status'=>'Rejected'])[0];
			$totalRejected_amount = $total_rejected->total_amount ?? 0;
			$totalRejected_charge = $total_rejected->total_charge ?? 0;
			$totalRejected_qty = $total_rejected->total_qty ?? 0;
			
			$query = "SELECT SUM(amount) AS total_amount, SUM(charge) AS total_charge, COUNT(id) AS total_qty FROM payment_services WHERE deleted = 0 AND created_by = $user_id";
			$total = $payment_service->query($query)[0];
			$total_amount = $total->total_amount ?? 0;
			$total_charge = $total->total_charge ?? 0;
			$total_qty = $total->total_qty ?? 0;
			
			require plugin_path('views/admin/list.php');
		}

	}
});

/** for manipulating data after a query operation **/
add_filter('after_query',function($data){

	
	if(empty($data['result']))
		return $data;

	if($data['query_id'] == 'get-users')
	{
		$role_map = new \PaymentService\User_roles_map;
		foreach ($data['result'] as $key => $row) {
			
			$query = "select * from user_roles where disabled = 0 && id in (select role_id from user_roles_map where disabled = 0 && user_id = :user_id)";
			$roles = $role_map->query($query,['user_id'=>$row->id]);
			if($roles)
				$data['result'][$key]->roles = array_column($roles, 'role');
			
			/** get payment_service's roles **/
			$user_roles_map = new \PaymentService\User_roles_map;
				
			$role_ids = $user_roles_map->where(['user_id'=>$row->id,'disabled'=>0]);
			if($role_ids)
				$data['result'][$key]->role_ids = array_column($role_ids, 'role_id');

		}
	}

	return $data;
});


<?php

/**
 * Plugin name: Agencybanking
 * Description: A way for admin to manage agencybanking
 * 
 * 
 **/

set_value([

	'admin_route'	=>'admin',
	'plugin_route'	=>'agencybanking',
	'tables'		=>[
		'agencybanking_table' => 'agencybankings',
	],

	'optional_tables'		=>[
	],
	

]);

if(!function_exists('agencybanking_json_response')){
	function agencybanking_json_response(array $payload, int $status = 200): void
	{
		$GLOBALS['agencybanking_json_response_sent'] = true;
		while(ob_get_level() > 0){
			ob_end_clean();
		}
		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		exit;
	}
}

$agencybankingJsonRoute = URL(1) === 'agencybanking' && in_array((string)(URL(2) ?? ''), ['limit-check', 'limits-api', 'data', 'cancel'], true);
if($agencybankingJsonRoute){
	ini_set('display_errors', '0');
	ini_set('display_startup_errors', '0');
	ob_start();
	$GLOBALS['agencybanking_json_response_sent'] = false;
	register_shutdown_function(static function(): void {
		if(!empty($GLOBALS['agencybanking_json_response_sent'])){
			return;
		}
		while(ob_get_level() > 0){
			ob_end_clean();
		}
		http_response_code(500);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(['success' => false, 'message' => 'Agency Banking request failed. Please try again.'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
	});
	set_exception_handler(static function(\Throwable $exception): void {
		agencybanking_json_response(['success' => false, 'message' => 'Agency Banking request failed. Please try again.'], 500);
	});
}


/** check if all tables exist **/
$db = new \Core\Database;
$tables = get_value()['tables'];

if(!$db->table_exists($tables)){
	if($agencybankingJsonRoute){
		agencybanking_json_response(['success' => false, 'message' => 'Agency Banking records are not available.'], 503);
	}
	dd("Missing database tables in ".plugin_id() ." plugin: ". implode(",", $db->missing_tables));
	die;
}

if(!function_exists('agencybanking_runtime_marker_path')){
	function agencybanking_runtime_marker_path(string $name): string
	{
		$dir = __DIR__ . DIRECTORY_SEPARATOR . '.runtime';
		if(!is_dir($dir)){
			@mkdir($dir, 0777, true);
		}
		return $dir . DIRECTORY_SEPARATOR . $name;
	}
}

 if(!function_exists('agencybanking_ensure_schema')){
 	function agencybanking_ensure_schema(): void
 	{
 		static $ensured = false;
 		if ($ensured) {
 			return;
 		}
 		$ensured = true;
		$marker = agencybanking_runtime_marker_path('agencybanking_schema_20260706.done');
		if(file_exists($marker)){
			return;
		}
 		try {
 			$db = new \Core\Database;
 			$schema = $db->query("SHOW COLUMNS FROM agencybankings");
 			if (!$schema || !is_array($schema)) {
 				return;
 			}
 			$existing = [];
 			foreach ($schema as $column) {
 				$existing[strtolower($column->Field ?? '')] = true;
 			}
 			$alters = [];
 			if (empty($existing['charging_type'])) {
 				$alters[] = "ADD COLUMN charging_type VARCHAR(20) NULL";
 			}
 			if (empty($existing['transaction_image'])) {
 				$alters[] = "ADD COLUMN transaction_image VARCHAR(255) NULL";
 			}
 			if (empty($existing['rejection_reason'])) {
 				$alters[] = "ADD COLUMN rejection_reason TEXT NULL";
 			}
 			if (empty($existing['cash_received_amount'])) {
 				$alters[] = "ADD COLUMN cash_received_amount FLOAT(10,2) DEFAULT 0.00";
 			}
 			if (empty($existing['change_required'])) {
 				$alters[] = "ADD COLUMN change_required FLOAT(10,2) DEFAULT 0.00";
 			}
 			if (empty($existing['change_denominations'])) {
 				$alters[] = "ADD COLUMN change_denominations LONGTEXT NULL";
 			}
 			if (empty($existing['full_name'])) {
				$alters[] = "ADD COLUMN full_name VARCHAR(50) NULL";
			}
			if (empty($existing['id_type'])) {
				$alters[] = "ADD COLUMN id_type VARCHAR(50) NULL";
			}
			if (empty($existing['id_number'])) {
				$alters[] = "ADD COLUMN id_number VARCHAR(50) NULL";
			}
			if (empty($existing['nok_phone'])) {
				$alters[] = "ADD COLUMN nok_phone VARCHAR(15) NULL";
			}
			if (!empty($alters)) {
				$db->query("ALTER TABLE agencybankings " . implode(',', $alters));
			}
			@file_put_contents($marker, '1');
 		} catch (\Throwable $e) {
 			return;
 		}
 	}
 }

 agencybanking_ensure_schema();

 require_once plugin_path('helpers/transaction-limits.php');
 agencybanking_ensure_limit_table();


 if(!function_exists('agencybanking_cleanup_interbank_charge_commissions')){
 	function agencybanking_cleanup_interbank_charge_commissions(): void
 	{
 		static $cleaned = false;
 		if ($cleaned) {
 			return;
 		}
 		$cleaned = true;
		$marker = agencybanking_runtime_marker_path('agencybanking_interbank_cleanup_20260427.done');
		if(file_exists($marker)){
			return;
		}
 		try {
 			$db = new \Core\Database;
 			if (!$db->table_exists(['commissions'])) {
 				@file_put_contents($marker, '1');
 				return;
 			}
 			$db->query(
 				"DELETE c
 				 FROM commissions c
 				 INNER JOIN agencybankings a ON c.description LIKE CONCAT('%| ID ', a.id)
 				 WHERE c.commission_type = :commission_type
 				   AND (LOWER(a.transaction_type) LIKE :interbank OR LOWER(a.transaction_type) LIKE :gip)",
 				[
 					'commission_type' => 'AGENCYBANKING CHARGE',
 					'interbank' => '%interbank%',
 					'gip' => '%gip%',
 				]
 			);
 			@file_put_contents($marker, '1');
 		} catch (\Throwable $e) {
 			error_log('AGENCYBANKING INTERBANK CHARGE COMMISSION CLEANUP FAILED: ' . $e->getMessage());
 		}
 	}
 }

 agencybanking_cleanup_interbank_charge_commissions();

if(!function_exists('agencybanking_build_filters')){
	function agencybanking_build_filters(array $filters, bool $canViewAll, int $currentUserId, array &$params): string
	{
		$clauses = ["(m.deleted = 0 OR m.deleted IS NULL)"];
		if(!$canViewAll){
			if($currentUserId > 0){
				$params['current_user_id'] = $currentUserId;
				$clauses[] = 'm.created_by = :current_user_id';
			}else{
				$clauses[] = '1 = 0';
			}
		}
		if(!empty($filters['find'])){
			$params['find'] = '%' . $filters['find'] . '%';
			$clauses[] = "(m.network LIKE :find OR m.transaction_type LIKE :find OR m.phone LIKE :find OR m.transaction_status LIKE :find OR m.rejection_reason LIKE :find OR CAST(m.id AS CHAR) LIKE :find OR EXISTS (SELECT 1 FROM users u WHERE u.id = m.created_by AND (u.first_name LIKE :find OR u.last_name LIKE :find OR CONCAT(u.first_name,' ',u.last_name) LIKE :find)))";
		}
		if(!empty($filters['date_from'])){
			$params['date_from'] = $filters['date_from'];
			$clauses[] = 'DATE(m.date_created) >= :date_from';
		}
		if(!empty($filters['date_to'])){
			$params['date_to'] = $filters['date_to'];
			$clauses[] = 'DATE(m.date_created) <= :date_to';
		}
		if(!empty($filters['created_by'])){
			$params['created_by'] = $filters['created_by'];
			$clauses[] = 'm.created_by = :created_by';
		}
		if(!empty($filters['transaction_type'])){
			$params['transaction_type'] = $filters['transaction_type'];
			$clauses[] = 'm.transaction_type = :transaction_type';
		}
		if(!empty($filters['transaction_status'])){
			$params['transaction_status'] = $filters['transaction_status'];
			$clauses[] = 'm.transaction_status = :transaction_status';
		}
		if(!empty($filters['network'])){
			$params['network'] = $filters['network'];
			$clauses[] = 'm.network = :network';
		}
		return 'WHERE ' . implode(' AND ', $clauses);
	}
}

if(!function_exists('agencybanking_format_currency')){
	function agencybanking_format_currency($value): float
	{
		return (float)number_format((float)$value, 2, '.', '');
	}
}

if(!function_exists('agencybanking_fetch_dashboard_payload')){
	function agencybanking_fetch_dashboard_payload(array $filters, int $limit, int $page, bool $canViewAll, int $currentUserId): array
	{
		$db = new \Core\Database;
		$params = [];
		$where = agencybanking_build_filters($filters, $canViewAll, $currentUserId, $params);
		$offset = max(0, ($page - 1) * $limit);

		$totalsResult = $db->query(
			"SELECT COUNT(*) AS total_rows, COALESCE(SUM(m.amount),0) AS total_amount, COALESCE(SUM(m.charge),0) AS total_charge
			FROM agencybankings AS m $where",
			$params
		);
		if(!is_array($totalsResult)){
			$totalsResult = [];
		}
		$totalsRow = $totalsResult[0] ?? null;
		$totalRows = (int)($totalsRow->total_rows ?? 0);
		$totalAmount = (float)($totalsRow->total_amount ?? 0);
		$totalCharge = (float)($totalsRow->total_charge ?? 0);

		$latestActivityResult = $db->query(
			"SELECT m.date_created FROM agencybankings AS m $where ORDER BY m.date_created DESC LIMIT 1",
			$params
		);
		if(!is_array($latestActivityResult)){
			$latestActivityResult = [];
		}
		$latestActivityRow = $latestActivityResult[0] ?? null;
		$latestActivity = $latestActivityRow->date_created ?? null;

		$rowsQuery = "SELECT m.*, u.first_name, u.last_name, u.image AS user_image
			FROM agencybankings AS m
			LEFT JOIN users AS u ON m.created_by = u.id
			$where
			ORDER BY m.id DESC
			LIMIT $limit OFFSET $offset";
		$rows = $db->query($rowsQuery, $params);
		if(!is_array($rows)){
			$rows = [];
		}
		$rawRowsForDebug = (defined('DEBUG') && DEBUG) ? array_map(function($row){ return (array)$row; }, $rows) : null;
		$normalizedRows = [];
		foreach($rows as $row){
			$statusRaw = strtolower(trim((string)($row->transaction_status ?? '')));
			$canModify = ($statusRaw !== '') && (strpos($statusRaw, 'pending') === 0 || strpos($statusRaw, 'draft') === 0);
			$isOwner = ((int)($row->created_by ?? 0)) === (int)$currentUserId;
			$normalizedRows[] = [
				'id' => (int)$row->id,
				'reference' => 'AG' . str_pad((string)$row->id, 5, '0', STR_PAD_LEFT),
				'date_created' => $row->date_created,
				'network' => $row->network,
				'transaction_type' => $row->transaction_type,
				'transaction_status' => $row->transaction_status,
				'rejection_reason' => $row->rejection_reason ?? '',
				'amount' => (float)$row->amount,
				'charge' => (float)($row->charge ?? 0),
				'phone' => $row->phone,
				'transaction_image' => $row->transaction_image ?? null,
				'full_name' => $row->full_name ?? null,
				'dob' => $row->dob ?? null,
				'house_number' => $row->house_number ?? null,
				'id_type' => $row->id_type ?? null,
				'id_number' => $row->id_number ?? null,
				'next_of_king' => $row->next_of_king ?? null,
				'nok_phone' => $row->nok_phone ?? null,
				'nok_relation' => $row->nok_relation ?? null,
				'other_phone' => $row->other_phone ?? null,
				'amount_withdrawn' => (float)($row->amount_withdrawn ?? 0),
				'amount_payable' => (float)($row->amount_payable ?? 0),
				'cash_received_amount' => (float)($row->cash_received_amount ?? 0),
				'change_required' => (float)($row->change_required ?? 0),
				'change_denominations' => $row->change_denominations ?? null,
				'charging_type' => $row->charging_type ?? null,
				'is_owner' => $isOwner,
				'can_modify' => ($isOwner && $canModify),
				'agent' => [
					'id' => (int)($row->created_by ?? 0),
					'name' => trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')),
					'image' => get_image($row->user_image ?? null),
				],
			];
		}

		$analysis = [
			'avg_ticket' => $totalRows > 0 ? $totalAmount / $totalRows : 0,
			'charge_rate' => $totalAmount > 0 ? $totalCharge / $totalAmount : 0,
			'top_agents' => [],
			'network_breakdown' => [],
			'status_breakdown' => [],
			'trend' => [],
		];

		$topAgents = $db->query(
			"SELECT m.created_by, u.first_name, u.last_name, SUM(m.amount) AS total_amount, COUNT(*) AS tx_count
			FROM agencybankings AS m
			LEFT JOIN users AS u ON m.created_by = u.id
			$where
			GROUP BY m.created_by, u.first_name, u.last_name
			ORDER BY total_amount DESC
			LIMIT 5",
			$params
		);
		if(!is_array($topAgents)){
			$topAgents = [];
		}
		foreach($topAgents as $agent){
			$analysis['top_agents'][] = [
				'id' => (int)($agent->created_by ?? 0),
				'name' => trim(($agent->first_name ?? '') . ' ' . ($agent->last_name ?? '')),
				'total' => (float)$agent->total_amount,
				'count' => (int)$agent->tx_count,
			];
		}

		$networkRows = $db->query(
			"SELECT m.network, SUM(m.amount) AS total_amount, COUNT(*) AS tx_count
			FROM agencybankings AS m
			$where
			GROUP BY m.network
			ORDER BY total_amount DESC",
			$params
		);
		if(!is_array($networkRows)){
			$networkRows = [];
		}
		foreach($networkRows as $net){
			$percent = $totalAmount > 0 ? ((float)$net->total_amount / $totalAmount) : 0;
			$analysis['network_breakdown'][] = [
				'label' => $net->network ?: 'Unspecified',
				'amount' => (float)$net->total_amount,
				'percent' => $percent,
				'count' => (int)$net->tx_count,
			];
		}

		$statusRows = $db->query(
			"SELECT m.transaction_status, SUM(m.amount) AS total_amount, COUNT(*) AS tx_count
			FROM agencybankings AS m
			$where
			GROUP BY m.transaction_status",
			$params
		);
		if(!is_array($statusRows)){
			$statusRows = [];
		}
		foreach($statusRows as $status){
			$analysis['status_breakdown'][] = [
				'label' => $status->transaction_status ?: 'Unknown',
				'amount' => (float)$status->total_amount,
				'count' => (int)$status->tx_count,
			];
		}

		$trendParams = $params;
		$trendRows = $db->query(
			"SELECT DATE(m.date_created) AS trend_day, SUM(m.amount) AS total_amount, COUNT(*) AS tx_count
			FROM agencybankings AS m
			$where
			GROUP BY DATE(m.date_created)
			ORDER BY trend_day DESC
			LIMIT 7",
			$trendParams
		);
		if(!is_array($trendRows)){
			$trendRows = [];
		}
		$trendRows = array_reverse($trendRows);
		foreach($trendRows as $trend){
			$analysis['trend'][] = [
				'label' => $trend->trend_day,
				'amount' => (float)$trend->total_amount,
				'count' => (int)$trend->tx_count,
			];
		}

		return [
			'success' => true,
			'rows' => $normalizedRows,
			'debug_raw_rows' => $rawRowsForDebug,
			'totals' => [
				'total_amount' => $totalAmount,
				'total_charge' => $totalCharge,
				'count' => $totalRows,
			],
			'latest_activity' => $latestActivity,
			'analysis' => $analysis,
			'pagination' => [
				'page' => $page,
				'limit' => $limit,
				'total_rows' => $totalRows,
				'total_pages' => $limit > 0 ? max(1, (int)ceil($totalRows / $limit)) : 1,
			],
		];
	}
}


/** set agencybanking permissions for this plugin **/
add_filter('permissions',function($permissions){

	$permissions[] = 'view_agencybanking';
	$permissions[] = 'view_agencybanking_details';
	$permissions[] = 'add_agencybanking';
	$permissions[] = 'edit_agencybanking';
	$permissions[] = 'delete_agencybanking';

	return $permissions;
});

/** run this after a form submit **/
add_action('controller',function(){

	$req = new \Core\Request;
	$vars = get_value();
	
	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];

	if(URL(1) == $plugin_route && URL(2) == 'limit-check'){
		if(!user_can('add_agencybanking')){
			agencybanking_json_response(['success' => false, 'message' => 'Access denied'], 403);
		}
		$limit = agencybanking_find_transaction_limit($_GET['transaction_type'] ?? '', $_GET['network'] ?? '', $_GET['withdrawal_type'] ?? '');
		$amount = (float)str_replace(',', '', (string)($_GET['amount'] ?? 0));
		agencybanking_json_response(['success' => true, 'configured' => $limit !== null, 'limit' => $limit, 'exceeded' => $limit !== null && $amount > $limit + 0.009]);
	}

	if(URL(1) == $plugin_route && URL(2) == 'limits-api'){
		if(!user_can('is_admin')){
			agencybanking_json_response(['success' => false, 'message' => 'Access denied'], 403);
		}
		$apiUserId = (int)(get_session()->id ?? 0);
		if($_SERVER['REQUEST_METHOD'] !== 'GET' && !csrf_verify($_POST)){
			agencybanking_json_response(['success' => false, 'message' => 'Invalid security token. Refresh the page and try again.'], 419);
		}
		if(session_status() === PHP_SESSION_ACTIVE){
			session_write_close();
		}
		try {
			$db = new \Core\Database;
			$action = trim((string)($_REQUEST['action'] ?? 'list'));
			if($_SERVER['REQUEST_METHOD'] === 'GET' || $action === 'list'){
				$rows = $db->query('SELECT id, transaction_type, withdrawal_type, network, max_amount, active FROM agencybanking_limits ORDER BY transaction_type, withdrawal_type, network') ?: [];
				agencybanking_json_response(['success' => true, 'rows' => $rows]);
			}
			if($action === 'delete'){
				$db->query('DELETE FROM agencybanking_limits WHERE id = :id LIMIT 1', ['id' => (int)($_POST['id'] ?? 0)]);
				if($db->has_error){ throw new \RuntimeException($db->error); }
				agencybanking_json_response(['success' => true, 'message' => 'Agency Banking limit deleted.']);
			}
			$id = (int)($_POST['id'] ?? 0);
			$type = agencybanking_normalize_limit_type($_POST['transaction_type'] ?? '');
			$withdrawalType = agencybanking_normalize_withdrawal_type($type, $_POST['withdrawal_type'] ?? '');
			$network = agencybanking_limit_key($_POST['network'] ?? '');
			$amount = (float)($_POST['max_amount'] ?? 0);
			if(!in_array($type, ['deposit', 'withdrawal', 'direct_transfer', 'interbank', 'account_creation'], true) || ($type === 'withdrawal' && $withdrawalType === '') || ($type !== 'withdrawal' && $withdrawalType !== '') || $network === '' || $amount <= 0){
				http_response_code(422);
				agencybanking_json_response(['success' => false, 'message' => 'Transaction type, bank network, and a positive maximum amount are required.'], 422);
			}
			$duplicateParams = ['transaction_type' => $type, 'withdrawal_type' => $withdrawalType, 'network' => $network];
			$duplicateSql = 'SELECT id FROM agencybanking_limits WHERE transaction_type = :transaction_type AND withdrawal_type = :withdrawal_type AND network = :network';
			if($id > 0){ $duplicateSql .= ' AND id <> :id'; $duplicateParams['id'] = $id; }
			if(!empty($db->query($duplicateSql . ' LIMIT 1', $duplicateParams))){
				http_response_code(409);
				agencybanking_json_response(['success' => false, 'message' => 'A limit already exists for this transaction type and bank network. Edit the existing limit instead.'], 409);
			}
			$params = ['transaction_type' => $type, 'withdrawal_type' => $withdrawalType, 'network' => $network, 'max_amount' => $amount, 'active' => !empty($_POST['active']) ? 1 : 0, 'date_updated' => date('Y-m-d H:i:s')];
			if($id > 0){
				$params['id'] = $id;
				$db->query('UPDATE agencybanking_limits SET transaction_type = :transaction_type, withdrawal_type = :withdrawal_type, network = :network, max_amount = :max_amount, active = :active, date_updated = :date_updated WHERE id = :id LIMIT 1', $params);
			}else{
				$params['created_by'] = $apiUserId;
				$params['date_created'] = date('Y-m-d H:i:s');
				$db->query('INSERT INTO agencybanking_limits (transaction_type, withdrawal_type, network, max_amount, active, created_by, date_created, date_updated) VALUES (:transaction_type, :withdrawal_type, :network, :max_amount, :active, :created_by, :date_created, :date_updated)', $params);
			}
			if($db->has_error){ throw new \RuntimeException($db->error); }
			$savedId = $id > 0 ? $id : (int)$db->insert_id;
			$savedRow = [
				'id' => $savedId,
				'transaction_type' => $type,
				'withdrawal_type' => $withdrawalType,
				'network' => $network,
				'max_amount' => number_format($amount, 2, '.', ''),
				'active' => !empty($_POST['active']) ? 1 : 0,
			];
			agencybanking_json_response(['success' => true, 'message' => $id > 0 ? 'Agency Banking limit updated.' : 'Agency Banking limit saved.', 'row' => $savedRow]);
		} catch(\Throwable $e) {
			agencybanking_json_response(['success' => false, 'message' => $e->getMessage() ?: 'Unable to save Agency Banking limit.'], 500);
		}
	}

	if(URL(1) == $vars['plugin_route'] && $req->posted() && URL(2) !== 'cancel')
	{
		$ses = new \Core\Session;
		$agencybanking = new \Agencybankings\Agencybanking;
		$wallet = new \Agencybankings\Wallet;
		$deno = new \Agencybankings\Deno;
		$transaction = new \Agencybankings\Transaction;
		$agencybanking_roles_map = new \Agencybankings\User_roles_map;

		$id = URL(3) ?? null;
		if($id)
			$row = $agencybanking->first(['id'=>$id]);


		if(
			URL(2) == 'add'
			|| URL(2) == 'withdrawal'
			|| URL(2) == 'direct_transfer'
			|| URL(2) == 'deposit'
			|| URL(2) == 'account_opening'
			|| URL(2) == 'balance_enquiry'
			|| URL(2) == 'mini_statement'
			|| URL(2) == 'account_opening'
			|| URL(2) == 'funds_transfer'
			|| URL(2) == 'interbank'
			|| URL(2) == 'international_transfer'
		){
			require plugin_path('controllers/add-controller.php');
		}else
		if(URL(2) == 'edit'){			
			require plugin_path('controllers/edit-controller.php');
		}else
		if(URL(2) == 'delete'){
			require plugin_path('controllers/delete-controller.php');
		}
	}elseif(URL(1) == $vars['plugin_route'] && URL(2) == 'cancel' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'){
		require_once plugin_path('helpers/cashdrawer-sync.php');
		require_once __DIR__ . '/../reversals/helpers/direct-cancel.php';
		direct_cancel_handle(direct_cancel_configuration('agencybanking'));
	}elseif(URL(1) == $vars['plugin_route'] && URL(2) == 'data'){
		$session = get_session();
		if(!user_can('view_agencybanking')){
			agencybanking_json_response([
				'success' => false,
				'message' => 'Access denied',
			], 403);
		}

		$userId = (int)($session->id ?? 0);
		$role = strtolower($session->roles[0] ?? '');
		$canViewAll = !in_array($role, ['momo agent','solo agent']);
		$page = max(1, (int)($_GET['page'] ?? 1));
		$limit = min(100, max(10, (int)($_GET['limit'] ?? 10)));
		$filters = [
			'find' => trim($_GET['find'] ?? ''),
			'date_from' => trim($_GET['date_from'] ?? ''),
			'date_to' => trim($_GET['date_to'] ?? ''),
			'created_by' => trim($_GET['created_by'] ?? ''),
			'transaction_type' => trim($_GET['transaction_type'] ?? ''),
			'transaction_status' => trim($_GET['transaction_status'] ?? ''),
			'network' => trim($_GET['network'] ?? ''),
		];

		try {
			$payload = agencybanking_fetch_dashboard_payload($filters, $limit, $page, $canViewAll, $userId);
			agencybanking_json_response($payload);
		}catch(\Throwable $th){
			error_log('AgencyBanking /data exception: ' . $th->getMessage());
			agencybanking_json_response([
				'success' => false,
				'message' => 'Unable to load agency banking data.',
				'error' => DEBUG ? $th->getMessage() : null,
			], 500);
		}
	}
});


/** displays the view file **/
add_action('basic-admin_main_content',function(){
	$ses = new \Core\Session;
	$vars = get_value();
	$db = new \Core\Database;

	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];
	
	$errors = $vars['errors'] ?? [];

	$agencybanking = new \Agencybankings\Agencybanking;
	$wallet = new \Agencybankings\Wallet;
	$deno = new \Agencybankings\Deno;
	$user = new \Agencybankings\User;
	$transaction = new \Agencybankings\Transaction;

	if(URL(1) == $vars['plugin_route']){

		$id = URL(3) ?? null;

		//session data
		$user::$query_id = 'get-users';
		$session = get_session();
		if(!empty($session->id)){
			$username = $session->first_name . ' '. $session->last_name;
			$user_id = $session->id;
			$role = $session->roles[0];
		}

		$users = $user->where(['deleted'=>0]);
		$wallets = $wallet->where(['deleted'=>0, 'transaction_status'=>'Approved']);
		$mywallets = $wallet->where(['deleted'=>0, 'assigned'=>$user_id, 'transaction_status'=>'Approved']);
		$denoRows = [];

		if($id){
			$user::$query_id = 'get-user';			
			$row = $agencybanking->first(['id'=>$id]);
			try{
				$denoRows = $deno->where(['trans_id'=>$id]) ?? [];
			}catch(\Throwable $th){
				$denoRows = [];
			}
		}

		$query = "SELECT * FROM settings ORDER BY id desc LIMIT 1";
		$settings = $db->query($query)[0] ?? [];	

		$agencybanking_role = new \Agencybankings\User_role;
		if(URL(2) == 'add'){
			$agencybanking_role = new \Agencybankings\User_role;
			require plugin_path('views/admin/add.php');
		}else
		if(URL(2) == 'limits'){
			if(!user_can('is_admin')){
				message_fail('Only an administrator can manage Agency Banking limits.');
				redirect($admin_route . '/' . $plugin_route);
				exit;
			}
			$agencybankingLimits = $db->query('SELECT * FROM agencybanking_limits ORDER BY transaction_type, network') ?: [];
			require plugin_path('views/admin/limits.php');
		}else
		if(URL(2) == 'edit'){			
			$agencybanking_role = new \Agencybankings\User_role;
			require plugin_path('views/admin/edit.php');
		}else
		if(URL(2) == 'delete'){
			require plugin_path('views/admin/delete.php');
		}else
		if(URL(2) == 'export_pdf'){

			$query = "
			    SELECT 
			        m.*, 
			        u.first_name AS user_first_name, 
			        u.last_name AS user_last_name, 
			        u.image AS user_image,
			        u_updated.first_name AS updated_by_first_name, 
			        u_updated.last_name AS updated_by_last_name, 
			        u_updated.image AS updated_by_image 
			    FROM agencybankings AS m 
			    LEFT JOIN users AS u ON m.created_by = u.id 
			    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			    WHERE m.id = :id 
			    LIMIT 1";

				$row = $agencybanking->query($query, ['id'=>$id])[0];

				$query = "SELECT * FROM denos WHERE trans_id = :trans_id GROUP BY denomination ORDER BY denomination DESC";
				$denos = $deno->query($query,['trans_id'=>$id]);

				//dd($denos);

				$query = "SELECT SUM(count) AS total_count, SUM(volume) AS total_volume FROM denos WHERE trans_id =:trans_id";
				$denosTotal = $deno->query($query, ['trans_id'=>$id]);
				if (!empty($denosTotal)) {
					$denosTotal = $denosTotal[0];
					$totalCount = $denosTotal->total_count ?? 0;
					$totalVolume = $denosTotal->total_volume ?? 0;
				}

			require plugin_path('views/admin/export_pdf.php');
		}else
		if(URL(2) == 'view'){

			$query = "
			    SELECT 
			        m.*, 
			        u.first_name AS user_first_name, 
			        u.last_name AS user_last_name, 
			        u.image AS user_image,
			        u_updated.first_name AS updated_by_first_name, 
			        u_updated.last_name AS updated_by_last_name, 
			        u_updated.image AS updated_by_image 
			    FROM agencybankings AS m 
			    LEFT JOIN users AS u ON m.created_by = u.id 
			    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			    WHERE m.id = :id 
			    LIMIT 1";

				$row = $agencybanking->query($query, ['id'=>$id])[0];

				$query = "SELECT * FROM denos WHERE trans_id = :trans_id GROUP BY denomination ORDER BY denomination DESC";
				$denos = $deno->query($query,['trans_id'=>$id]);

				//dd($denos);

				$query = "SELECT SUM(count) AS total_count, SUM(volume) AS total_volume FROM denos WHERE trans_id =:trans_id";
				$denosTotal = $deno->query($query, ['trans_id'=>$id]);
				if (!empty($denosTotal)) {
					$denosTotal = $denosTotal[0];
					$totalCount = $denosTotal->total_count ?? 0;
					$totalVolume = $denosTotal->total_volume ?? 0;
				}

			require plugin_path('views/admin/view.php');
		}else{
			// Helper function to fetch cashdrawer denominations and balances
			$getCashdrawerData = function() use ($session) {
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
				return ['denos' => $cashdrawerDenos, 'balances' => $cashdrawerBalances];
			};

			if(URL(2) == 'withdrawal'){
				$cashdrawerData = $getCashdrawerData();
				$cashdrawerDenos = $cashdrawerData['denos'];
				$cashdrawerBalances = $cashdrawerData['balances'];
				require plugin_path('views/admin/entries/withdrawal.php');
			}else
			if(URL(2) == 'direct_transfer'){

				require plugin_path('views/admin/entries/direct_transfer.php');
			}else
			if(URL(2) == 'deposit'){
				$cashdrawerData = $getCashdrawerData();
				$cashdrawerDenos = $cashdrawerData['denos'];
				$cashdrawerBalances = $cashdrawerData['balances'];
				require plugin_path('views/admin/entries/deposit.php');
			}else
			if(URL(2) == 'account_opening'){

				require plugin_path('views/admin/entries/account_opening.php');
			}else
			if(URL(2) == 'mini_statement'){

				require plugin_path('views/admin/entries/mini_statement.php');
			}else
			if(URL(2) == 'balance_enquiry'){

				require plugin_path('views/admin/entries/balance_enquiry.php');
			}else
			if(URL(2) == 'funds_transfer'){

				require plugin_path('views/admin/entries/funds_transfer.php');
			}else
			if(URL(2) == 'interbank'){

				require plugin_path('views/admin/entries/interbank.php');
			}else
			if(URL(2) == 'international_transfer'){

				require plugin_path('views/admin/entries/international_transfer.php');
			}else
			if(URL(2) == 'exportList_pdf'){

				// Debugging: Check if we reach this point
				error_log("PDF Export Endpoint Reached");

				$user::$query_id = 'get-user';

					$query = "
						SELECT 
							m.*, 
							u.first_name AS user_first_name, 
							u.last_name AS user_last_name, 
							u.image AS user_image
						FROM agencybankings AS m 
						LEFT JOIN users AS u ON m.created_by = u.id 
						ORDER BY m.id DESC
					";

					$query2 = $query. "WHERE created_by = $user_id";

					// Debug: Output the generated queries
					error_log("Query for non-agents: " . $query);
					error_log("Query for agents: " . $query2);				

					if (strtolower($role) !== 'momo agent' && strtolower($role) !== 'solo agent') {
						$rows = $agencybanking->query($query);
					} else {
						$rows = $agencybanking->query($query2);
					}

					// Debug: Check retrieved data
					error_log("Number of rows retrieved: " . count((array)$rows));
					error_log("First row data: " . print_r($rows[0] ?? 'No rows', true));

					// Debug: Check total calculation
					$total_query = "SELECT SUM(amount) AS total_amount FROM agencybankings WHERE deleted = 0";
					if (in_array(strtolower($role), ['momo agent', 'solo agent'])) {
						$total_query .= " AND created_by = $user_id";
					}
					
					error_log("Total query: " . $total_query);
					$total = $agencybanking->query($total_query)[0] ?? (object)['total_amount' => 0];
					$total_amount = $total->total_amount ?? 0;
					error_log("Total amount: " . ($total_amount ?? 0));

					// Debug: Verify view file existence
					$view_file = plugin_path('views/admin/exportList_pdf.php');
					error_log("View file path: " . $view_file);
					error_log("View file exists: " . (file_exists($view_file) ? 'Yes' : 'No'));

				require $view_file;
				exit();
			}else
			{
				$limit = 10;
				$pager = new \Core\Pager($limit);
				$offset = $pager->offset;

				$agencybanking->limit = $limit;
				$agencybanking->offset = $offset;
				$user::$query_id = 'get-user';
				
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
						FROM agencybankings AS m 
						LEFT JOIN users AS u ON m.created_by = u.id 
						LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
						WHERE (
							m.network LIKE :find 
							OR m.transaction_type LIKE :find 
							OR u.first_name LIKE :find 
							OR u.last_name LIKE :find 
							OR m.phone LIKE :find
							OR m.transaction_status LIKE :find
							OR m.date_created LIKE :find
							OR m.created_by LIKE :find
						 )
						LIMIT $limit OFFSET $offset";

					$rows = $agencybanking->query($query, ['find' => $find]);
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
						FROM agencybankings AS m
						LEFT JOIN users AS u 
							ON m.created_by = u.id
						LEFT JOIN users AS u_updated 
							ON m.updated_by = u_updated.id
						WHERE m.created_by = {$user_id}
						ORDER BY m.id DESC
						LIMIT {$limit} OFFSET {$offset}
						";

					$query2 = "WHERE m.created_by = $user_id";
						$rows = $agencybanking->query($query);

						$query = "SELECT SUM(amount) AS total_amount, SUM(charge) AS total_charge FROM agencybankings WHERE deleted = 0 AND transaction_status = :transaction_status AND created_by = {$user_id}";
						$total_approved = $agencybanking->query($query, ['transaction_status'=>'Approved'])[0];
						$totalApproved_amount = $total_approved->total_amount ?? 0;
						$totalApproved_charge = $total_approved->total_charge ?? 0;

						$total_pending = $agencybanking->query($query, ['transaction_status'=>'Pending'])[0];
						$totalPending_amount = $total_pending->total_amount ?? 0;
						$totalPending_charge = $total_pending->total_charge ?? 0;

						$total_draft = $agencybanking->query($query, ['transaction_status'=>'Draft'])[0];
						$totalDraft_amount = $total_draft->total_amount ?? 0;
						$totalDraft_charge = $total_draft->total_charge ?? 0;

						$total_rejected = $agencybanking->query($query, ['transaction_status'=>'Rejected'])[0];
						$totalRejected_amount = $total_rejected->total_amount ?? 0;
						$totalRejected_charge = $total_rejected->total_charge ?? 0;
						
						$query = "SELECT SUM(amount) AS total_amount, SUM(charge) AS total_charge FROM agencybankings WHERE deleted = 0 AND created_by = {$user_id}";
						$total = $agencybanking->query($query)[0];
						$total_amount = $total->total_amount ?? 0;
						$total_charge = $total->total_charge ?? 0;
					
				}
				
				require plugin_path('views/admin/list.php');
			}
		}

	}
});

/** for manipulating data after a query operation **/
add_filter('after_query',function($data){
	
	if(empty($data['result']))
		return $data;

	if($data['query_id'] == 'get-user')
	{
		$role_map = new \Agencybankings\User_roles_map;
		foreach ($data['result'] as $key => $row) {
			if (!is_object($row) || empty($row->id)) {
				continue;
			}
			
			$query = "select * from users_roles where disabled = 0 && id in (select role_id from users_roles_map where disabled = 0 && user_id = :user_id)";
			$roles = $role_map->query($query,['user_id'=>$row->id]);
			if($roles)
				$data['result'][$key]->roles = array_column($roles, 'role');
			
			/** get user's roles **/
			$user_roles_map = new \Agencybankings\User_roles_map;
				
			$role_ids = $user_roles_map->where(['user_id'=>$row->id,'disabled'=>0]);
			if($role_ids)
				$data['result'][$key]->role_ids = array_column($role_ids, 'role_id');

		}
	}

	return $data;
});

add_action('basic-admin_main_content', function(){
	if(URL(1) !== 'agencybanking' || !in_array(URL(2), ['add', 'deposit', 'withdrawal', 'direct_transfer', 'funds_transfer', 'interbank', 'international_transfer', 'account_opening'], true)){
		return;
	}
	?>
	<script>
	(function(){
		const form = [...document.querySelectorAll('form[method="post"]')].find(candidate => candidate.querySelector('#transaction_type') && candidate.querySelector('#network'));
		if(!form || !document.getElementById('transaction_type') || !document.getElementById('network')) return;
		const endpoint = '<?=ROOT?>/<?=get_value()['admin_route']?>/agencybanking/limit-check';
		const typeField = document.getElementById('transaction_type');
		const networkField = document.getElementById('network');
		const withdrawalTypeFromTransaction = value => {
			const text = String(value || '').toLowerCase();
			return text.includes('ait') ? 'ait' : (text.includes('cit') ? 'cit' : '');
		};
		const amountForCurrentEntry = () => {
			const fields = ['amount_withdrawn', 'amount_payable', 'amount', 'balance_before', 'amount_to_pay'].map(name => form.querySelector('[name="' + name + '"]')).filter(Boolean);
			return Math.max(...fields.map(field => parseFloat(String(field.value || '0').replace(/,/g, '')) || 0));
		};
		const submitButtons = [...form.querySelectorAll('button[type="submit"], input[type="submit"], button.save, input.save, #save')];
		const status = document.createElement('div');
		status.className = 'agency-limit-ui-status text-danger small mt-2';
		const amountField = () => {
			const type = String(typeField.value || '').toLowerCase();
			const names = type.includes('withdrawal')
				? ['amount_withdrawn', 'amount_payable', 'amount']
				: ['amount', 'amount_load', 'amount_sendable', 'balance_before', 'amount_to_pay'];
			for(const name of names){
				const field = form.querySelector('[name="' + name + '"]');
				if(field && !field.closest('.d-none')) return field;
			}
			return form.querySelector('[name="' + names[0] + '"]');
		};
		const placeStatus = () => {
			const field = amountField();
			if(field && field.parentNode) field.parentNode.insertBefore(status, field.nextSibling);
		};
		placeStatus();
		let requestNumber = 0;
		let currentState = '';
		let currentBlocked = false;
		let checking = false;
		const setLimitButtons = disabled => submitButtons.forEach(button => {
			if(disabled){
				if(!button.dataset.limitPreviousDisabled) button.dataset.limitPreviousDisabled = button.disabled ? '1' : '0';
				button.disabled = true;
			}else if(button.dataset.limitPreviousDisabled !== undefined){
				button.disabled = button.dataset.limitPreviousDisabled === '1';
				delete button.dataset.limitPreviousDisabled;
			}
		});
		const checkLimit = async () => {
			placeStatus();
			const transactionType = String(typeField.value || '').trim();
			const network = String(networkField.value || '').trim();
			const amount = amountForCurrentEntry();
			const withdrawalType = withdrawalTypeFromTransaction(transactionType);
			const state = [transactionType, network.toLowerCase(), withdrawalType, amount].join('|');
			if(state === currentState && !checking) return !currentBlocked;
			currentState = state;
			const requestId = ++requestNumber;
			if(!transactionType || !network || amount <= 0){
				currentBlocked = false;
				status.textContent = '';
				setLimitButtons(false);
				return true;
			}
			checking = true;
			currentBlocked = true;
			status.textContent = 'Checking configured transaction limit...';
			setLimitButtons(true);
			try{
				const query = new URLSearchParams({transaction_type: transactionType, network, amount: String(amount), withdrawal_type: withdrawalType});
				const response = await fetch(endpoint + '?' + query.toString(), {credentials: 'include', cache: 'no-store', headers: {Accept: 'application/json'}});
				const payload = await response.json();
				if(requestId !== requestNumber) return false;
				if(!response.ok || !payload.success) throw new Error(payload.message || 'Unable to check transaction limit.');
				currentBlocked = Boolean(payload.exceeded);
				status.textContent = currentBlocked ? 'Limit exceeded. Maximum allowed: GHS ' + Number(payload.limit || 0).toLocaleString(undefined, {minimumFractionDigits: 2}) : (payload.configured ? 'Maximum allowed: GHS ' + Number(payload.limit || 0).toLocaleString(undefined, {minimumFractionDigits: 2}) : '');
				setLimitButtons(currentBlocked);
				return !currentBlocked;
			}catch(error){
				currentBlocked = false;
				status.textContent = 'Limit could not be checked. The server will validate this transaction.';
				return true;
			}finally{
				checking = false;
				setLimitButtons(currentBlocked || checking);
			}
		};
		[typeField, networkField, ...['amount_withdrawn','amount_payable','amount','balance_before','amount_to_pay'].map(name => form.querySelector('[name="' + name + '"]')).filter(Boolean)].forEach(field => {
			field.addEventListener('input', () => { currentState = ''; checkLimit(); });
			field.addEventListener('change', () => { currentState = ''; checkLimit(); });
		});
		checkLimit();
	})();
	</script>
	<?php
});

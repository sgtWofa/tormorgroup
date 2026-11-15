<?php

/**
 * Plugin name: Momoremit
 * Description: A way for admin to manage momoremit
 * 
 * 
 **/

set_value([

	'admin_route'	=>'admin',
	'plugin_route'	=>'momoremit',
	'tables'		=>[
		'deposit_table' 		=> 'deposits',
		'withdrawal_table' 	=> 'withdrawals',
		'sending_table' 		=> 'sendings',
		'cit_table' 				=> 'cits',
		'wallet_table' 				=> 'wallets',
	],

	'optional_tables'		=>[
	],
	

]);


/** check if all tables exist **/
$db = new \Core\Database;
$tables = get_value()['tables'];

if(!$db->table_exists($tables)){
	dd("Missing database tables in ".plugin_id() ." plugin: ". implode(",", $db->missing_tables));
	die;
}

require_once plugin_path('helpers/transactions-helper.php');
require_once plugin_path('helpers/commission-helper.php');
$whatsappHelperPath = realpath(__DIR__ . '/../backoffice/helpers/whatsapp-notifications.php');
if ($whatsappHelperPath) {
	require_once $whatsappHelperPath;
}
$cashdrawerBridgePath = realpath(__DIR__ . '/../backoffice/helpers/cashdrawer-bridge.php');
if ($cashdrawerBridgePath) {
	require_once $cashdrawerBridgePath;
	if (function_exists('BackOfficeCashdrawer\\init')) {
		\BackOfficeCashdrawer\init();
	}
}
momoremits_ensure_runtime_columns();
momoremits_ensure_limit_table();

if (!function_exists('momoremit_send_json')) {
	function momoremit_send_json(array $payload, int $statusCode = 200): void
	{
		while (ob_get_level() > 0) {
			ob_end_clean();
		}
		header('Content-Type: application/json; charset=utf-8');
		http_response_code($statusCode);
		echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		exit;
	}
}


/** set momoremit permissions for this plugin **/
add_filter('permissions',function($permissions){
	$permissions[] = 'view_momoremit_details';
	$permissions[] = 'add_momoremit';
	$permissions[] = 'edit_momoremit';
	$permissions[] = 'delete_momoremit';
	$permissions[] = 'view_momoremit_commissions';
	$permissions[] = 'transfer_momoremit_commissions';

	return $permissions;
});

/** run this after a form submit **/
add_action('controller',function(){

	$req = new \Core\Request;
	$vars = get_value();
	
	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];

	// Load commission API controller if requested
	if (URL(1) === $plugin_route && URL(2) === 'commission-api') {
		require_once plugin_path('controllers/commission-api-controller.php');
		exit;
	}

	$session = get_session();
	$role = $session->roles[0] ?? '';
	$user_id = $session->id ?? 0;

	if(URL(1) == $plugin_route && URL(2) == 'limit-check'){
		if(!user_can('add_momoremit')){
			momoremit_send_json(['success' => false, 'message' => 'Access denied'], 403);
		}
		try {
			$type = trim((string)($_GET['transaction_type'] ?? ''));
			$simType = trim((string)($_GET['sim_type'] ?? ''));
			$network = trim((string)($_GET['network'] ?? ''));
			$amount = (float)str_replace(',', '', (string)($_GET['amount'] ?? 0));
			$limit = momoremits_find_transaction_limit($type, $simType, $network);
			momoremit_send_json([
				'success' => true,
				'configured' => $limit !== null,
				'limit' => $limit,
				'exceeded' => $limit !== null && $amount > $limit + 0.009,
				'message' => $limit === null ? 'No limit configured for this transaction combination.' : 'Maximum allowed: GHS ' . number_format($limit, 2),
			]);
		} catch (\Throwable $error) {
			momoremit_send_json(['success' => false, 'message' => 'Unable to verify the configured transaction limit.'], 500);
		}
	}

	if(URL(1) == $plugin_route && URL(2) == 'limits-api'){
		header('Content-Type: application/json');
		if(!user_can('is_admin')){
			http_response_code(403);
			echo json_encode(['success' => false, 'message' => 'Access denied']);
			exit;
		}

		try {
			$limitDb = new \Core\Database;
			$action = trim((string)($_REQUEST['action'] ?? 'list'));
			if($_SERVER['REQUEST_METHOD'] === 'GET' || $action === 'list'){
				$find = trim((string)($_GET['find'] ?? ''));
				$transactionType = momoremits_normalize_limit_type($_GET['transaction_type'] ?? '');
				$simType = momoremits_limit_key($_GET['sim_type'] ?? '');
				$network = momoremits_limit_key($_GET['network'] ?? '');
				$status = trim((string)($_GET['status'] ?? ''));
				$conditions = [];
				$params = [];
				if($find !== ''){
					$conditions[] = '(transaction_type LIKE :find OR sim_type LIKE :find OR network LIKE :find)';
					$params['find'] = '%' . $find . '%';
				}
				if(in_array($transactionType, ['withdrawal', 'cashin', 'cit'], true)){
					$conditions[] = 'transaction_type = :transaction_type';
					$params['transaction_type'] = $transactionType;
				}
				if(in_array($simType, ['subscriber', 'merchant', 'agent'], true)){
					$conditions[] = 'sim_type = :sim_type';
					$params['sim_type'] = $simType;
				}
				if($network !== ''){
					$conditions[] = 'network = :network';
					$params['network'] = $network;
				}
				if($status === 'active' || $status === 'inactive'){
					$conditions[] = 'active = :active';
					$params['active'] = $status === 'active' ? 1 : 0;
				}
				$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
				$rows = $limitDb->query('SELECT id, transaction_type, sim_type, network, max_amount, active FROM momoremit_limits' . $where . ' ORDER BY transaction_type, sim_type, network', $params) ?: [];
				echo json_encode(['success' => true, 'rows' => $rows]);
				exit;
			}

			$post = $_POST;
			if(!csrf_verify($post)){
				http_response_code(419);
				echo json_encode(['success' => false, 'message' => 'Invalid security token. Refresh the page and try again.']);
				exit;
			}
			if($action === 'delete'){
				$limitDb->query('DELETE FROM momoremit_limits WHERE id = :id LIMIT 1', ['id' => (int)($post['id'] ?? 0)]);
				if($limitDb->has_error){
					throw new \RuntimeException($limitDb->error);
				}
				echo json_encode(['success' => true, 'message' => 'Momoremit limit deleted.']);
				exit;
			}

			$limitId = (int)($post['id'] ?? 0);
			$transactionType = momoremits_normalize_limit_type($post['transaction_type'] ?? '');
			$simType = momoremits_limit_key($post['sim_type'] ?? '');
			$network = momoremits_limit_key($post['network'] ?? '');
			$maxAmount = (float)($post['max_amount'] ?? 0);
			$validSimType = in_array($transactionType, ['withdrawal', 'cit'], true) ? $simType === '' : in_array($simType, ['subscriber', 'merchant', 'agent'], true);
			if(!in_array($transactionType, ['withdrawal', 'cashin', 'cit'], true) || !$validSimType || $network === '' || $maxAmount <= 0){
				http_response_code(422);
				echo json_encode(['success' => false, 'message' => 'Transaction type, SIM type, network, and a positive maximum amount are required.']);
				exit;
			}
			$duplicateParams = [
				'transaction_type' => $transactionType,
				'sim_type' => $simType,
				'network' => $network,
			];
			$duplicateSql = 'SELECT id FROM momoremit_limits WHERE transaction_type = :transaction_type AND sim_type = :sim_type AND network = :network';
			if($limitId > 0){
				$duplicateSql .= ' AND id <> :id';
				$duplicateParams['id'] = $limitId;
			}
			$duplicateSql .= ' LIMIT 1';
			if(!empty($limitDb->query($duplicateSql, $duplicateParams))){
				http_response_code(409);
				echo json_encode(['success' => false, 'message' => 'A limit already exists for this transaction type, SIM type, and network. Edit the existing limit instead.']);
				exit;
			}
			$now = date('Y-m-d H:i:s');
			if($limitId > 0){
				$limitDb->query(
					'UPDATE momoremit_limits SET transaction_type = :transaction_type, sim_type = :sim_type, network = :network, max_amount = :max_amount, active = :active, date_updated = :date_updated WHERE id = :id LIMIT 1',
					[
						'transaction_type' => $transactionType,
						'sim_type' => $simType,
						'network' => $network,
						'max_amount' => $maxAmount,
						'active' => !empty($post['active']) ? 1 : 0,
						'date_updated' => $now,
						'id' => $limitId,
					]
				);
			}else{
				$limitDb->query(
					'INSERT INTO momoremit_limits (transaction_type, sim_type, network, max_amount, active, created_by, date_created, date_updated) VALUES (:transaction_type, :sim_type, :network, :max_amount, :active, :created_by, :date_created, :date_updated) ON DUPLICATE KEY UPDATE max_amount = VALUES(max_amount), active = VALUES(active), date_updated = VALUES(date_updated)',
					[
						'transaction_type' => $transactionType,
						'sim_type' => $simType,
						'network' => $network,
						'max_amount' => $maxAmount,
						'active' => !empty($post['active']) ? 1 : 0,
						'created_by' => (int)(get_session()->id ?? 0),
						'date_created' => $now,
						'date_updated' => $now,
					]
				);
			}
			if($limitDb->has_error){
				throw new \RuntimeException($limitDb->error);
			}
			echo json_encode(['success' => true, 'message' => $limitId > 0 ? 'Momoremit limit updated.' : 'Momoremit limit saved.']);
		} catch(\Throwable $error) {
			http_response_code(500);
			echo json_encode(['success' => false, 'message' => $error->getMessage() ?: 'Unable to process Momoremit limit.']);
		}
		exit;
	}

	if(URL(1) == $plugin_route && URL(2) == 'data'){
		if(!user_can('view_momoremit')){
			momoremit_send_json(['success' => false, 'message' => 'Access denied'], 403);
		}
		try {
			$db = new \Core\Database;
			$find = trim($_GET['find'] ?? '');
			$dateFrom = $_GET['date_from'] ?? null;
			$dateTo = $_GET['date_to'] ?? null;
			$createdByFilter = !empty($_GET['created_by']) ? (int)$_GET['created_by'] : null;
			$agentSearch = trim((string)($_GET['agent_search'] ?? ''));
			$networkFilter = trim((string)($_GET['network'] ?? ''));
			$transactionTypeFilter = trim($_GET['transaction_type'] ?? '');
			$page = max(1, (int)($_GET['page'] ?? 1));
			$limit = max(5, min(100, (int)($_GET['limit'] ?? 10)));
			$offset = ($page - 1) * $limit;

			$conditions = ['(m.deleted IS NULL OR m.deleted = 0)'];
			$params = [];

			if($find !== ''){
				$conditions[] = '(
					m.network LIKE :find OR
					m.id LIKE :find OR
					m.transaction_type LIKE :find OR
					m.sending_category LIKE :find OR
					m.category LIKE :find OR
					m.phone LIKE :find OR
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

			if($createdByFilter){
				$conditions[] = 'm.created_by = :created_by_filter';
				$params['created_by_filter'] = $createdByFilter;
			}

			if($agentSearch !== ''){
				$conditions[] = "(CONCAT_WS(' ', u.first_name, u.last_name) LIKE :agent_search OR u.id = :agent_search_id)";
				$params['agent_search'] = "%{$agentSearch}%";
				$params['agent_search_id'] = ctype_digit($agentSearch) ? (int)$agentSearch : -1;
			}

			if($networkFilter !== ''){
				$conditions[] = 'LOWER(m.network) = :network_filter';
				$params['network_filter'] = strtolower($networkFilter);
			}

			if($transactionTypeFilter !== ''){
				$conditions[] = 'LOWER(m.transaction_type) = :transaction_type_filter';
				$params['transaction_type_filter'] = strtolower($transactionTypeFilter);
			}

			if(in_array(strtolower($role), ['momo agent','solo agent'])){
				$conditions[] = 'm.created_by = :session_user';
				$params['session_user'] = $user_id;
			}

			$whereSql = $conditions ? 'WHERE '.implode(' AND ', $conditions) : '';
			$limitClause = " LIMIT $limit OFFSET $offset";

			$rowsSql = "SELECT 
				m.id,
				m.network,
				m.transaction_type,
				m.sending_category,
				m.category,
				m.phone,
				m.amount,
				m.amount_payable,
				m.amount_sendable,
				m.charge,
				m.balance_after,
				m.transaction_status,
				m.rejection_reason,
				m.date_created,
				m.created_by,
				u.first_name AS user_first_name,
				u.last_name AS user_last_name,
				u.image AS user_image
			FROM momoremits AS m
			LEFT JOIN users AS u ON m.created_by = u.id
			$whereSql
			ORDER BY m.date_created DESC $limitClause";

			$rows = $db->query($rowsSql, $params) ?: [];

			$totalsSql = "SELECT 
				COUNT(*) AS total_rows,
				COALESCE(SUM(m.amount),0) AS total_amount,
				COALESCE(SUM(m.charge),0) AS total_charge
			FROM momoremits AS m
			LEFT JOIN users AS u ON m.created_by = u.id
			$whereSql";
			$totalsRow = ($db->query($totalsSql, $params) ?: [])[0] ?? (object)['total_rows'=>0,'total_amount'=>0,'total_charge'=>0];
			$totalRows = (int)$totalsRow->total_rows;
			$totalPages = $totalRows ? (int)ceil($totalRows / $limit) : 1;
			$totalAmount = (float)$totalsRow->total_amount;
			$totalCharge = (float)$totalsRow->total_charge;

			$latestSql = "SELECT m.date_created FROM momoremits AS m LEFT JOIN users AS u ON m.created_by = u.id $whereSql ORDER BY m.date_created DESC LIMIT 1";
			$latestActivity = ($db->query($latestSql, $params) ?: [])[0]->date_created ?? null;

			$rowsPayload = array_map(function($row){
				$agentName = trim(($row->user_first_name ?? '').' '.($row->user_last_name ?? ''));
				return [
					'id' => (int)$row->id,
					'reference' => 'TX'.(int)$row->id,
					'network' => $row->network ?? '',
					'transaction_type' => $row->transaction_type ?? '',
					'sending_category' => $row->sending_category ?? '',
					'category' => $row->category ?? '',
					'phone' => $row->phone ?? '',
					'amount' => (float)$row->amount,
					'amount_payable' => isset($row->amount_payable) ? (float)$row->amount_payable : null,
					'amount_sendable' => isset($row->amount_sendable) ? (float)$row->amount_sendable : null,
					'charge' => (float)$row->charge,
					'balance_after' => ($row->balance_after !== null && $row->balance_after !== '') ? (float)$row->balance_after : null,
					'transaction_status' => trim((string)($row->transaction_status ?? 'Approved')) ?: 'Approved',
					'rejection_reason' => trim((string)($row->rejection_reason ?? '')),
					'date_created' => $row->date_created,
					'agent' => [
						'id' => (int)$row->created_by,
						'name' => $agentName ?: '—',
						'image' => get_image($row->user_image ?? ''),
					],
				];
			}, $rows);

			$topAgentsSql = "SELECT 
				m.created_by,
				COUNT(*) AS tx_count,
				COALESCE(SUM(m.amount),0) AS total_amount,
				u.first_name,
				u.last_name
			FROM momoremits AS m
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

			$networkSql = "SELECT m.network, COUNT(*) AS tx_count, COALESCE(SUM(m.amount),0) AS total_amount FROM momoremits AS m LEFT JOIN users AS u ON m.created_by = u.id $whereSql GROUP BY m.network";
			$networkRows = $db->query($networkSql, $params) ?: [];
			$networkBreakdown = array_map(function($row) use ($totalAmount){
				$amount = (float)$row->total_amount;
				return [
					'label' => $row->network ?: 'Unspecified',
					'amount' => $amount,
					'count' => (int)$row->tx_count,
					'percent' => $totalAmount > 0 ? ($amount / $totalAmount) : 0,
				];
			}, $networkRows);

			$trendParams = $params;
			$trendStart = date('Y-m-d', strtotime('-6 days'));
			$trendWhere = $whereSql ? $whereSql.' AND DATE(m.date_created) >= :trend_start' : 'WHERE DATE(m.date_created) >= :trend_start';
			$trendParams['trend_start'] = $trendStart;
			$trendSql = "SELECT DATE(m.date_created) AS label, COUNT(*) AS tx_count, COALESCE(SUM(m.amount),0) AS total_amount FROM momoremits AS m LEFT JOIN users AS u ON m.created_by = u.id $trendWhere GROUP BY DATE(m.date_created) ORDER BY DATE(m.date_created) ASC";
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

			momoremit_send_json([
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
					'avg_ticket' => $avgTicket,
					'count' => $totalRows,
				],
				'latest_activity' => $latestActivity,
				'analysis' => [
					'avg_ticket' => $avgTicket,
					'charge_rate' => $chargeRate,
					'top_agents' => $topAgents,
					'network_breakdown' => $networkBreakdown,
					'trend' => $trend,
				],
			]);
		} catch (\Throwable $th) {
			momoremit_send_json(['success' => false, 'message' => 'Failed to fetch data', 'error' => $th->getMessage()], 500);
		}
		return;
	}

	if(URL(1) == $vars['plugin_route'] && $req->posted())
	{
		if(URL(2) == 'limits'){
			$ses = new \Core\Session;
			if(!user_can('is_admin')){
				message_fail('Only an administrator can manage Momoremit limits.');
				redirect($admin_route . '/' . $plugin_route . '/limits');
				exit;
			}
			$limitDb = new \Core\Database;
			$limitPost = $req->post();
			if(!csrf_verify($limitPost)){
				message_fail('Invalid security token.');
				redirect($admin_route . '/' . $plugin_route . '/limits');
				exit;
			}
			if(($limitPost['limit_action'] ?? '') === 'delete'){
				$limitDb->query('DELETE FROM momoremit_limits WHERE id = :id LIMIT 1', ['id' => (int)($limitPost['id'] ?? 0)]);
				message_success('Momoremit limit deleted.');
				redirect($admin_route . '/' . $plugin_route . '/limits');
				exit;
			}
			$limitId = (int)($limitPost['id'] ?? 0);
			$transactionType = momoremits_normalize_limit_type($limitPost['transaction_type'] ?? '');
			$simType = momoremits_limit_key($limitPost['sim_type'] ?? '');
			$network = momoremits_limit_key($limitPost['network'] ?? '');
			$maxAmount = (float)($limitPost['max_amount'] ?? 0);
			$validSimType = in_array($transactionType, ['withdrawal', 'cit'], true) ? $simType === '' : in_array($simType, ['subscriber', 'merchant', 'agent'], true);
			if(!in_array($transactionType, ['withdrawal', 'cashin', 'cit'], true) || !$validSimType || $network === '' || $maxAmount <= 0){
				message_fail('Transaction type, SIM type, network, and a positive maximum amount are required.');
				redirect($admin_route . '/' . $plugin_route . '/limits');
				exit;
			}
			$limitParams = [
					'transaction_type' => $transactionType,
					'sim_type' => $simType,
					'network' => $network,
					'max_amount' => $maxAmount,
					'active' => !empty($limitPost['active']) ? 1 : 0,
					'created_by' => (int)($ses->id ?? 0),
					'date_created' => date('Y-m-d H:i:s'),
					'date_updated' => date('Y-m-d H:i:s'),
			];
			if($limitId > 0){
				$updateParams = [
					'transaction_type' => $transactionType,
					'sim_type' => $simType,
					'network' => $network,
					'max_amount' => $maxAmount,
					'active' => !empty($limitPost['active']) ? 1 : 0,
					'date_updated' => $limitParams['date_updated'],
					'id' => $limitId,
				];
				$limitDb->query(
					"UPDATE momoremit_limits SET transaction_type = :transaction_type, sim_type = :sim_type, network = :network, max_amount = :max_amount, active = :active, date_updated = :date_updated WHERE id = :id LIMIT 1",
					$updateParams
				);
				if($limitDb->has_error){
					message_fail('Momoremit limit could not be updated. The same transaction, SIM type, and network combination may already exist. Database error: ' . $limitDb->error);
				}else{
					message_success($limitDb->affected_rows === 0 ? 'No changes were made to the Momoremit limit.' : 'Momoremit limit updated.');
				}
			}else{
				$limitDb->query(
					"INSERT INTO momoremit_limits (transaction_type, sim_type, network, max_amount, active, created_by, date_created, date_updated)
					 VALUES (:transaction_type, :sim_type, :network, :max_amount, :active, :created_by, :date_created, :date_updated)
					 ON DUPLICATE KEY UPDATE max_amount = VALUES(max_amount), active = VALUES(active), date_updated = VALUES(date_updated)",
					$limitParams
				);
				if($limitDb->has_error){
					message_fail('Momoremit limit could not be saved. Database error: ' . $limitDb->error);
				}else{
					message_success('Momoremit limit saved.');
				}
			}
			redirect($admin_route . '/' . $plugin_route . '/limits');
			exit;
		}
		$ses = new \Core\Session;
		$sending = new \Momoremits\Sending;
		$deposit = new \Momoremits\Deposit;
		$withdrawal = new \Momoremits\Withdrawal;
		$cit = new \Momoremits\Cit;
		$momoremit = new \Momoremits\Momoremit;
		$wallet = new \Momoremits\Wallet;
		$transaction = new \Momoremits\Transaction;
		$momoremit_roles_map = new \Momoremits\User_roles_map;

		$id = URL(3) ?? null;
		if($id)
			$row = $momoremit->first(['id'=>$id]);

		if(URL(2) == 'add' || URL(2) == 'cit' || URL(2) == 'deposit' || URL(2) == 'withdrawal' || URL(2) == 'cashin'){
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

	$momoremit = new \Momoremits\Momoremit;
	$wallet = new \Momoremits\Wallet;
	$user = new \Momoremits\User;
	$transaction = new \Momoremits\Transaction;
	$deno = new \Momoremits\Deno;

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
		
		$wallets = $wallet->where(['deleted'=>0, 'transaction_status'=>'Approved']);

		if($id){
			$user::$query_id = 'get-users';			
			$row = $momoremit->first(['id'=>$id]);
		}
		
		$mywallets = $wallet->where(['deleted'=>0, 'transaction_status'=>'Approved', 'assigned'=>$user_id]);

		if(URL(2) == 'data'){
			if(!user_can('view_momoremit')){
				momoremit_send_json(['success' => false, 'message' => 'Access denied'], 403);
			}
			try {
				$db = new \Core\Database;
				$find = trim($_GET['find'] ?? '');
				$dateFrom = $_GET['date_from'] ?? null;
				$dateTo = $_GET['date_to'] ?? null;
				$createdByFilter = !empty($_GET['created_by']) ? (int)$_GET['created_by'] : null;
				$transactionTypeFilter = trim($_GET['transaction_type'] ?? '');
				$page = max(1, (int)($_GET['page'] ?? 1));
				$limit = max(5, min(100, (int)($_GET['limit'] ?? 10)));
				$offset = ($page - 1) * $limit;

				$conditions = ['(m.deleted IS NULL OR m.deleted = 0)'];
				$params = [];

				if($find !== ''){
					$conditions[] = '(
						m.network LIKE :find OR
						m.id LIKE :find OR
						m.transaction_type LIKE :find OR
						m.sending_category LIKE :find OR
						m.category LIKE :find OR
						m.phone LIKE :find OR
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

				if($createdByFilter){
					$conditions[] = 'm.created_by = :created_by_filter';
					$params['created_by_filter'] = $createdByFilter;
				}

				if($transactionTypeFilter !== ''){
					$conditions[] = 'LOWER(m.transaction_type) = :transaction_type_filter';
					$params['transaction_type_filter'] = strtolower($transactionTypeFilter);
				}

				if(in_array(strtolower($role), ['momo agent','solo agent'])){
					$conditions[] = 'm.created_by = :session_user';
					$params['session_user'] = $user_id;
				}

				$whereSql = $conditions ? 'WHERE '.implode(' AND ', $conditions) : '';
				$limitClause = " LIMIT $limit OFFSET $offset";

				$rowsSql = "SELECT 
					m.id,
					m.network,
					m.transaction_type,
					m.sending_category,
					m.category,
					m.phone,
					m.amount,
					m.amount_payable,
					m.amount_sendable,
					m.charge,
					m.balance_after,
					m.transaction_status,
					m.rejection_reason,
					m.date_created,
					m.created_by,
					u.first_name AS user_first_name,
					u.last_name AS user_last_name,
					u.image AS user_image
				FROM momoremits AS m
				LEFT JOIN users AS u ON m.created_by = u.id
				$whereSql
				ORDER BY m.date_created DESC $limitClause";

				$rows = $db->query($rowsSql, $params) ?: [];

				$totalsSql = "SELECT 
					COUNT(*) AS total_rows,
					COALESCE(SUM(m.amount),0) AS total_amount,
					COALESCE(SUM(m.charge),0) AS total_charge
				FROM momoremits AS m
				LEFT JOIN users AS u ON m.created_by = u.id
				$whereSql";
				$totalsRow = ($db->query($totalsSql, $params) ?: [])[0] ?? (object)['total_rows'=>0,'total_amount'=>0,'total_charge'=>0];
				$totalRows = (int)$totalsRow->total_rows;
				$totalPages = $totalRows ? (int)ceil($totalRows / $limit) : 1;
				$totalAmount = (float)$totalsRow->total_amount;
				$totalCharge = (float)$totalsRow->total_charge;

				$latestSql = "SELECT m.date_created FROM momoremits AS m LEFT JOIN users AS u ON m.created_by = u.id $whereSql ORDER BY m.date_created DESC LIMIT 1";
				$latestActivity = ($db->query($latestSql, $params) ?: [])[0]->date_created ?? null;

				$rowsPayload = array_map(function($row){
					$agentName = trim(($row->user_first_name ?? '').' '.($row->user_last_name ?? ''));
					return [
						'id' => (int)$row->id,
						'reference' => 'TX'.(int)$row->id,
						'network' => $row->network ?? '',
						'transaction_type' => $row->transaction_type ?? '',
						'sending_category' => $row->sending_category ?? '',
						'category' => $row->category ?? '',
						'phone' => $row->phone ?? '',
						'amount' => (float)$row->amount,
						'amount_payable' => isset($row->amount_payable) ? (float)$row->amount_payable : null,
						'amount_sendable' => isset($row->amount_sendable) ? (float)$row->amount_sendable : null,
						'charge' => (float)$row->charge,
						'balance_after' => ($row->balance_after !== null && $row->balance_after !== '') ? (float)$row->balance_after : null,
						'transaction_status' => trim((string)($row->transaction_status ?? 'Approved')) ?: 'Approved',
						'rejection_reason' => trim((string)($row->rejection_reason ?? '')),
						'date_created' => $row->date_created,
						'agent' => [
							'id' => (int)$row->created_by,
							'name' => $agentName ?: '—',
							'image' => get_image($row->user_image ?? ''),
						],
					];
				}, $rows);

				$topAgentsSql = "SELECT 
					m.created_by,
					COUNT(*) AS tx_count,
					COALESCE(SUM(m.amount),0) AS total_amount,
					u.first_name,
					u.last_name
				FROM momoremits AS m
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

				$networkSql = "SELECT m.network, COUNT(*) AS tx_count, COALESCE(SUM(m.amount),0) AS total_amount FROM momoremits AS m LEFT JOIN users AS u ON m.created_by = u.id $whereSql GROUP BY m.network";
				$networkRows = $db->query($networkSql, $params) ?: [];
				$networkBreakdown = array_map(function($row) use ($totalAmount){
					$amount = (float)$row->total_amount;
					return [
						'label' => $row->network ?: 'Unspecified',
						'amount' => $amount,
						'count' => (int)$row->tx_count,
						'percent' => $totalAmount > 0 ? ($amount / $totalAmount) : 0,
					];
				}, $networkRows);

				$trendParams = $params;
				$trendStart = date('Y-m-d', strtotime('-6 days'));
				$trendWhere = $whereSql ? $whereSql.' AND DATE(m.date_created) >= :trend_start' : 'WHERE DATE(m.date_created) >= :trend_start';
				$trendParams['trend_start'] = $trendStart;
				$trendSql = "SELECT DATE(m.date_created) AS label, COUNT(*) AS tx_count, COALESCE(SUM(m.amount),0) AS total_amount FROM momoremits AS m LEFT JOIN users AS u ON m.created_by = u.id $trendWhere GROUP BY DATE(m.date_created) ORDER BY DATE(m.date_created) ASC";
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

				momoremit_send_json([
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
						'avg_ticket' => $avgTicket,
						'count' => $totalRows,
					],
					'latest_activity' => $latestActivity,
					'analysis' => [
						'avg_ticket' => $avgTicket,
						'charge_rate' => $chargeRate,
						'top_agents' => $topAgents,
						'network_breakdown' => $networkBreakdown,
						'trend' => $trend,
					],
				]);
			} catch (\Throwable $th) {
				momoremit_send_json(['success' => false, 'message' => 'Failed to fetch data', 'error' => $th->getMessage()], 500);
			}
		}

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
					$sharedMomoContext = momoremits_find_shared_momopay_context(
						$drawer_id,
						(int)($session->id ?? 0)
					);
					if (!empty($sharedMomoContext)) {
						$cashdrawerBalances = array_values(array_filter(
							$cashdrawerBalances,
							static function (array $balance): bool {
								return !momoremits_is_momopay_channel_key((string)($balance['channel_key'] ?? ''));
							}
						));
						$cashdrawerBalances[] = [
							'channel_key' => (string)($sharedMomoContext['channel_key'] ?? 'MTN_MOMOPAY'),
							'closing_balance' => (float)($sharedMomoContext['closing_balance'] ?? 0),
						];
					}
				}
			} catch (\Throwable $e) {
				error_log('MOMOREMIT CASHDRAWER DATA LOAD FAILED: ' . $e->getMessage());
			}
			return ['denos' => $cashdrawerDenos, 'balances' => $cashdrawerBalances];
		};

		if(URL(2) == 'add'){
			$momoremit_role = new \Momoremits\User_role;
			require plugin_path('views/admin/add.php');
		}else
		if(URL(2) == 'limits'){
			if(!user_can('is_admin')){
				message_fail('Only an administrator can manage Momoremit limits.');
				redirect($admin_route . '/' . $plugin_route);
				exit;
			}
			$limitsDb = new \Core\Database;
			$limitFind = trim((string)($_GET['find'] ?? ''));
			$limitTransactionType = momoremits_normalize_limit_type($_GET['transaction_type'] ?? '');
			$limitSimType = momoremits_limit_key($_GET['sim_type'] ?? '');
			$limitNetwork = momoremits_limit_key($_GET['network'] ?? '');
			$limitStatus = trim((string)($_GET['status'] ?? ''));
			$limitConditions = [];
			$limitParams = [];
			if($limitFind !== ''){
				$limitConditions[] = '(transaction_type LIKE :limit_find OR sim_type LIKE :limit_find OR network LIKE :limit_find)';
				$limitParams['limit_find'] = '%' . $limitFind . '%';
			}
			if(in_array($limitTransactionType, ['withdrawal', 'cashin', 'cit'], true)){
				$limitConditions[] = 'transaction_type = :limit_transaction_type';
				$limitParams['limit_transaction_type'] = $limitTransactionType;
			}
			if(in_array($limitSimType, ['subscriber', 'merchant', 'agent'], true)){
				$limitConditions[] = 'sim_type = :limit_sim_type';
				$limitParams['limit_sim_type'] = $limitSimType;
			}
			if($limitNetwork !== ''){
				$limitConditions[] = 'network = :limit_network';
				$limitParams['limit_network'] = $limitNetwork;
			}
			if($limitStatus === 'active' || $limitStatus === 'inactive'){
				$limitConditions[] = 'active = :limit_active';
				$limitParams['limit_active'] = $limitStatus === 'active' ? 1 : 0;
			}
			$limitWhere = $limitConditions ? ' WHERE ' . implode(' AND ', $limitConditions) : '';
			$momoremitLimits = $limitsDb->query('SELECT * FROM momoremit_limits' . $limitWhere . ' ORDER BY transaction_type, sim_type, network', $limitParams) ?: [];
			$editLimit = null;
			$editLimitId = (int)($_GET['edit'] ?? 0);
			if($editLimitId > 0){
				$editLimit = ($limitsDb->query('SELECT * FROM momoremit_limits WHERE id = :id LIMIT 1', ['id' => $editLimitId]) ?: [])[0] ?? null;
			}
			require plugin_path('views/admin/limits.php');
		}else
		if(URL(2) == 'deposit'){
			$momoremit_role = new \Momoremits\User_role;
			$cashdrawerData = $getCashdrawerData();
			$cashdrawerDenos = $cashdrawerData['denos'];
			$cashdrawerBalances = $cashdrawerData['balances'];
			require plugin_path('views/admin/entries/deposit.php');
		}else
		if(URL(2) == 'cit'){
			$momoremit_role = new \Momoremits\User_role;
			$cashdrawerData = $getCashdrawerData();
			$cashdrawerDenos = $cashdrawerData['denos'];
			$cashdrawerBalances = $cashdrawerData['balances'];
			require plugin_path('views/admin/entries/cit.php');
		}else
		if(URL(2) == 'cashin'){
			$momoremit_role = new \Momoremits\User_role;
			$cashdrawerData = $getCashdrawerData();
			$cashdrawerDenos = $cashdrawerData['denos'];
			$cashdrawerBalances = $cashdrawerData['balances'];
			require plugin_path('views/admin/entries/cashin.php');
		}else
		if(URL(2) == 'withdrawal'){
			$momoremit_role = new \Momoremits\User_role;
			$cashdrawerData = $getCashdrawerData();
			$cashdrawerDenos = $cashdrawerData['denos'];
			$cashdrawerBalances = $cashdrawerData['balances'];
			require plugin_path('views/admin/entries/withdrawal.php');
		}else
		if(URL(2) == 'edit'){			
			$momoremit_role = new \Momoremits\User_role;
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
			        u_updated.first_name AS updated_by_first_name, 
			        u_updated.last_name AS updated_by_last_name, 
			        u_updated.image AS updated_by_image,
		            in_wallet.name AS in_wallet_name, 
		            in_wallet.network AS in_wallet_network,
		            out_wallet.name AS out_wallet_name, 
		            out_wallet.network AS out_wallet_network
			    FROM momoremits AS m 
			    LEFT JOIN users AS u ON m.created_by = u.id 
			    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id			        
			    LEFT JOIN mywallets AS in_wallet ON m.wallet_in_id = in_wallet.id 			        
			    LEFT JOIN mywallets AS out_wallet ON m.wallet_out_id = out_wallet.id 
			    WHERE m.id = :id 
			    LIMIT 1";

				$row = $momoremit->query($query, ['id'=>$id])[0];
				$denos = $deno->where(['trans_id'=>$id]);

				$query = "SELECT SUM(count) AS total_count, SUM(volume) AS total_volume FROM denos WHERE trans_id =:trans_id";
				$denosTotal = $deno->query($query, ['trans_id'=>$id]);
				if (!empty($denosTotal)) {
					$denosTotal = $denosTotal[0];
					$totalCount = $denosTotal->total_count ?? 0;
					$totalVolume = $denosTotal->total_volume ?? 0;
				}

			require plugin_path('views/admin/view.php');
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
			        u_updated.image AS updated_by_image,
		            in_wallet.name AS in_wallet_name, 
		            in_wallet.network AS in_wallet_network,
		            out_wallet.name AS out_wallet_name, 
		            out_wallet.network AS out_wallet_network
			    FROM momoremits AS m 
			    LEFT JOIN users AS u ON m.created_by = u.id 
			    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id			        
			    LEFT JOIN mywallets AS in_wallet ON m.wallet_in_id = in_wallet.id 			        
			    LEFT JOIN mywallets AS out_wallet ON m.wallet_out_id = out_wallet.id 
			    WHERE m.id = :id 
			    LIMIT 1";

				$row = $momoremit->query($query, ['id'=>$id])[0];
				$denos = $deno->where(['trans_id'=>$id]);

				$query = "SELECT SUM(count) AS total_count, SUM(volume) AS total_volume FROM denos WHERE trans_id =:trans_id";
				$denosTotal = $deno->query($query, ['trans_id'=>$id]);
				if (!empty($denosTotal)) {
					$denosTotal = $denosTotal[0];
					$totalCount = $denosTotal->total_count ?? 0;
					$totalVolume = $denosTotal->total_volume ?? 0;
				}

			require plugin_path('views/admin/export_pdf.php');
			exit;
		}else
		if(URL(2) == 'deposits'){
			$sending = new \Momoremits\Sending;
			$deposit = new \Momoremits\Deposit;
			$withdrawal = new \Momoremits\Withdrawal;
			$cit = new \Momoremits\Cit;
			$momoremit = new \Momoremits\Momoremit;

			$limit = 10;
			$pager = new \Core\Pager($limit);
			$offset = $pager->offset;

			$deposit->limit = $limit;
			$deposit->offset = $offset;
			$user::$query_id = 'get-users';			

		    if (isset($deposit)) {
		        $deposit->limit = $limit;
		        $deposit->offset = $offset;
		        $user::$query_id = 'get-users';
		    }
			
			if(!empty($_GET['find']))
			{
				$find = '%' . trim($_GET['find']) . '%';
				$query = "
			        SELECT
			            m.*, 
			            u.first_name AS user_first_name, 
			            u.last_name AS user_last_name, 
			            u.image AS user_image,
			            u_updated.first_name AS updated_by_first_name, 
			            u_updated.last_name AS updated_by_last_name, 
			            u_updated.image AS updated_by_image,
			            in_wallet.name AS in_wallet_name, 
			            in_wallet.network AS in_wallet_network,
			            out_wallet.name AS out_wallet_name, 
			            out_wallet.network AS out_wallet_network  
			        FROM deposits AS m 
			        LEFT JOIN users AS u ON m.created_by = u.id 
			        LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id			        
			        LEFT JOIN mywallets AS in_wallet ON m.wallet_in_id = in_wallet.id 			        
			        LEFT JOIN mywallets AS out_wallet ON m.wallet_out_id = out_wallet.id  
			        WHERE (
			        	m.network LIKE :find 
			            OR m.id LIKE :find 
			            OR m.transaction_type LIKE :find 
			            OR u.first_name LIKE :find 
			            OR u.last_name LIKE :find 
			            OR m.phone LIKE :find
			            OR m.transaction_status LIKE :find
			            OR in_wallet.network LIKE :find
			            OR in_wallet.name LIKE :find
			            OR out_wallet.network LIKE :find
			            OR out_wallet.name LIKE :find
			            OR m.date_created LIKE :find)
			       ";
				$rows = $deposit->query($query,['find'=>$find]);

				//dd($rows);die;

			}else
			{			
				$query = "
				    SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name, 
				        u_updated.last_name AS updated_by_last_name, 
				        u_updated.image AS updated_by_image,
			            in_wallet.name AS in_wallet_name, 
			            in_wallet.network AS in_wallet_network,
			            out_wallet.name AS out_wallet_name, 
			            out_wallet.network AS out_wallet_network 
				    FROM deposits AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id			        
				    LEFT JOIN mywallets AS in_wallet ON m.wallet_in_id = in_wallet.id 			        
				    LEFT JOIN mywallets AS out_wallet ON m.wallet_out_id = out_wallet.id  
				    ORDER BY m.id DESC";

					$rows = $deposit->query($query);
				}

			$query = "SELECT SUM(amount) AS total_amount, SUM(charge) AS total_charge FROM deposits WHERE deleted = 0";
			$total = $deposit->query($query)[0];
			$total_amount = $total->total_amount ?? 0;
			$total_charge = $total->total_charge ?? 0;

				require plugin_path('views/admin/deposits.php');

		}else
		if(URL(2) == 'withdrawals'){

			$sending = new \Momoremits\Sending;
			$deposit = new \Momoremits\Deposit;
			$withdrawal = new \Momoremits\Withdrawal;
			$cit = new \Momoremits\Cit;
			$momoremit = new \Momoremits\Momoremit;

			$limit = 10;
			$pager = new \Core\Pager($limit);
			$offset = $pager->offset;

			$momoremit->limit = $limit;
			$momoremit->offset = $offset;
			$user::$query_id = 'get-users';
			
			if(!empty($_GET['find']))
			{
				$find = '%' . trim($_GET['find']) . '%';
				$query = "
			        SELECT
			            m.*, 
			            u.first_name AS user_first_name, 
			            u.last_name AS user_last_name, 
			            u.image AS user_image,
			            u_updated.first_name AS updated_by_first_name, 
			            u_updated.last_name AS updated_by_last_name, 
			            u_updated.image AS updated_by_image, 
			            in_wallet.name AS in_wallet_name, 
			            in_wallet.network AS in_wallet_network,
			            out_wallet.name AS out_wallet_name, 
			            out_wallet.network AS out_wallet_network  
			        FROM momoremits AS m 
			        LEFT JOIN users AS u ON m.created_by = u.id 
			        LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			        LEFT JOIN mywallets AS in_wallet ON m.wallet_in_id = in_wallet.id 
			        LEFT JOIN mywallets AS out_wallet ON m.wallet_out_id = out_wallet.id 
			        WHERE (
			        	m.transaction_type LIKE 'Withdrawal' AND
			            OR m.id LIKE :find 
			            OR m.transaction_type LIKE :find 
			            OR u.first_name LIKE :find 
			            OR u.last_name LIKE :find 
			            OR m.phone LIKE :find
			            OR m.transaction_status LIKE :find
			            OR in_wallet.network LIKE :find
			            OR in_wallet.name LIKE :find
			            OR out_wallet.network LIKE :find
			            OR out_wallet.name LIKE :find
			            OR m.date_created LIKE :find)
			       ";
				$rows = $momoremit->query($query,['find'=>$find]);

				//dd($rows);

			}else
			{			
				$query = "
				    SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name, 
				        u_updated.last_name AS updated_by_last_name, 
				        u_updated.image AS updated_by_image, 
			            in_wallet.name AS in_wallet_name, 
			            in_wallet.network AS in_wallet_network,
			            out_wallet.name AS out_wallet_name, 
			            out_wallet.network AS out_wallet_network 
				    FROM momoremits AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id
			        LEFT JOIN mywallets AS in_wallet ON m.wallet_in_id = in_wallet.id 
			        LEFT JOIN mywallets AS out_wallet ON m.wallet_out_id = out_wallet.id  
			        WHERE transaction_type = 'Withdrawal'
				    ORDER BY m.id DESC";

				$query2 = "
				    SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name, 
				        u_updated.last_name AS updated_by_last_name, 
				        u_updated.image AS updated_by_image, 
			            in_wallet.name AS in_wallet_name, 
			            in_wallet.network AS in_wallet_network,
			            out_wallet.name AS out_wallet_name, 
			            out_wallet.network AS out_wallet_network 
				    FROM momoremits AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id
			        LEFT JOIN mywallets AS in_wallet ON m.wallet_in_id = in_wallet.id 
			        LEFT JOIN mywallets AS out_wallet ON m.wallet_out_id = out_wallet.id  
			        WHERE transaction_type = 'Withdrawal' AND created_by = $user_id
				    ORDER BY m.id DESC";

					if (strtolower($role) !== 'momo agent' && strtolower($role) !== 'solo agent') {
						
						$rows = $momoremit->query($query);

						$query = "SELECT SUM(amount) AS total_amount, SUM(charge) AS total_charge FROM momoremits WHERE deleted = 0 AND transaction_type = 'Withdrawal' ";
						$total = $momoremit->query($query)[0];
						$total_amount = $total->total_amount ?? 0;
						$total_charge = $total->total_charge ?? 0;
					
					}else{
						
						$rows = $momoremit->query($query2);

						$query = "SELECT SUM(amount) AS total_amount, SUM(charge) AS total_charge FROM momoremits WHERE deleted = 0 AND transaction_type = 'Withdrawal' AND created_by = $user_id ";
						$total = $momoremit->query($query)[0];
						$total_amount = $total->total_amount ?? 0;
						$total_charge = $total->total_charge ?? 0;

					}

				}

			require plugin_path('views/admin/withdrawals.php');
			
		}else
		if(URL(2) == 'cits'){

			$sending = new \Momoremits\Sending;
			$deposit = new \Momoremits\Deposit;
			$withdrawal = new \Momoremits\Withdrawal;
			$cit = new \Momoremits\Cit;
			$momoremit = new \Momoremits\Momoremit;

			$limit = 10;
			$pager = new \Core\Pager($limit);
			$offset = $pager->offset;

			$momoremit->limit = $limit;
			$momoremit->offset = $offset;
			$user::$query_id = 'get-users';
			
			if(!empty($_GET['find']))
			{
				$find = '%' . trim($_GET['find']) . '%';
				$query = "
			        SELECT
			            m.*, 
			            u.first_name AS user_first_name, 
			            u.last_name AS user_last_name, 
			            u.image AS user_image,
			            u_updated.first_name AS updated_by_first_name, 
			            u_updated.last_name AS updated_by_last_name, 
			            u_updated.image AS updated_by_image,
			            in_wallet.name AS in_wallet_name, 
			            in_wallet.network AS in_wallet_network,
			            out_wallet.name AS out_wallet_name, 
			            out_wallet.network AS out_wallet_network  
			        FROM momoremits AS m 
			        LEFT JOIN users AS u ON m.created_by = u.id 
			        LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id
			        LEFT JOIN mywallets AS in_wallet ON m.wallet_in_id = in_wallet.id 
			        LEFT JOIN mywallets AS out_wallet ON m.wallet_out_id = out_wallet.id  
			        WHERE (
			        	m.transaction_type LIKE 'CIT' AND 
			            OR m.id LIKE :find 
			            OR m.transaction_type LIKE :find 
			            OR u.first_name LIKE :find 
			            OR u.last_name LIKE :find 
			            OR m.phone LIKE :find
			            OR m.transaction_status LIKE :find
			            OR in_wallet.network LIKE :find
			            OR in_wallet.name LIKE :find
			            OR out_wallet.network LIKE :find
			            OR out_wallet.name LIKE :find
			            OR m.date_created LIKE :find)
			       ";
				$rows = $momoremit->query($query,['find'=>$find]);

				//dd($rows);

			}else
			{			
				$query = "
				    SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name, 
				        u_updated.last_name AS updated_by_last_name, 
				        u_updated.image AS updated_by_image,
			            in_wallet.name AS in_wallet_name, 
			            in_wallet.network AS in_wallet_network,
			            out_wallet.name AS out_wallet_name, 
			            out_wallet.network AS out_wallet_network 
				    FROM momoremits AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id
			        LEFT JOIN mywallets AS in_wallet ON m.wallet_in_id = in_wallet.id 
			        LEFT JOIN mywallets AS out_wallet ON m.wallet_out_id = out_wallet.id  
			        WHERE transaction_type ='CIT'
				    ORDER BY m.id DESC";

				$query2 = "
				    SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name, 
				        u_updated.last_name AS updated_by_last_name, 
				        u_updated.image AS updated_by_image,
			            in_wallet.name AS in_wallet_name, 
			            in_wallet.network AS in_wallet_network,
			            out_wallet.name AS out_wallet_name, 
			            out_wallet.network AS out_wallet_network 
				    FROM momoremits AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id
			        LEFT JOIN mywallets AS in_wallet ON m.wallet_in_id = in_wallet.id 
			        LEFT JOIN mywallets AS out_wallet ON m.wallet_out_id = out_wallet.id  
			        WHERE transaction_type ='CIT' AND created_by =$user_id
				    ORDER BY m.id DESC";

					if (strtolower($role) !== 'momo agent' && strtolower($role) !== 'solo agent') {

						$rows = $momoremit->query($query);

						$query = "SELECT SUM(amount) AS total_amount, SUM(charge) AS total_charge FROM momoremits WHERE deleted = 0 AND transaction_type = 'CIT' ";
						$total = $momoremit->query($query)[0];
						$total_amount = $total->total_amount ?? 0;
						$total_charge = $total->total_charge ?? 0;

					}else{

						$rows = $momoremit->query($query2);

						$query = "SELECT SUM(amount) AS total_amount, SUM(charge) AS total_charge FROM momoremits WHERE deleted = 0 AND transaction_type = 'CIT' AND created_by = $user_id";
						$total = $momoremit->query($query)[0];
						$total_amount = $total->total_amount ?? 0;
						$total_charge = $total->total_charge ?? 0;

					}
				}
			
			require plugin_path('views/admin/cits.php');
			
		}else
		if(URL(2) == 'sendings'){

			$sending = new \Momoremits\Sending;
			$deposit = new \Momoremits\Deposit;
			$withdrawal = new \Momoremits\Withdrawal;
			$cit = new \Momoremits\Cit;
			$momoremit = new \Momoremits\Momoremit;

			$limit = 10;
			$pager = new \Core\Pager($limit);
			$offset = $pager->offset;

			$momoremit->limit = $limit;
			$momoremit->offset = $offset;
			$user::$query_id = 'get-users';
			
			if(!empty($_GET['find']))
			{
				$find = '%' . trim($_GET['find']) . '%';
				$query = "
			        SELECT
			            m.*, 
			            u.first_name AS user_first_name, 
			            u.last_name AS user_last_name, 
			            u.image AS user_image,
			            u_updated.first_name AS updated_by_first_name, 
			            u_updated.last_name AS updated_by_last_name, 
			            u_updated.image AS updated_by_image,
			            in_wallet.name AS in_wallet_name, 
			            in_wallet.network AS in_wallet_network,
			            out_wallet.name AS out_wallet_name, 
			            out_wallet.network AS out_wallet_network  
			        FROM momoremits AS m 
			        LEFT JOIN users AS u ON m.created_by = u.id 
			        LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id
			        LEFT JOIN mywallets AS in_wallet ON m.wallet_in_id = in_wallet.id 
			        LEFT JOIN mywallets AS out_wallet ON m.wallet_out_id = out_wallet.id  
			        WHERE (
			        	m.transaction_type = 'Sending' 
			            OR m.id LIKE :find 
			            OR m.transaction_type LIKE :find 
			            OR u.first_name LIKE :find 
			            OR u.last_name LIKE :find 
			            OR m.phone LIKE :find
			            OR m.transaction_status LIKE :find
			            OR in_wallet.network LIKE :find
			            OR in_wallet.name LIKE :find
			            OR out_wallet.network LIKE :find
			            OR out_wallet.name LIKE :find
			            OR m.date_created LIKE :find)
			       ";
				$rows = $momoremit->query($query,['find'=>$find]);

			}else
			{			
				$query = "
				    SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name, 
				        u_updated.last_name AS updated_by_last_name, 
				        u_updated.image AS updated_by_image,
			            in_wallet.name AS in_wallet_name, 
			            in_wallet.network AS in_wallet_network,
			            out_wallet.name AS out_wallet_name, 
			            out_wallet.network AS out_wallet_network 
				    FROM momoremits AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id
			        LEFT JOIN mywallets AS in_wallet ON m.wallet_in_id = in_wallet.id 
			        LEFT JOIN mywallets AS out_wallet ON m.wallet_out_id = out_wallet.id  
			        WHERE transaction_type = 'Sending'
				    ORDER BY m.id DESC";

				$query2 = "
				    SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name, 
				        u_updated.last_name AS updated_by_last_name, 
				        u_updated.image AS updated_by_image,
			            in_wallet.name AS in_wallet_name, 
			            in_wallet.network AS in_wallet_network,
			            out_wallet.name AS out_wallet_name, 
			            out_wallet.network AS out_wallet_network 
				    FROM momoremits AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id
			        LEFT JOIN mywallets AS in_wallet ON m.wallet_in_id = in_wallet.id 
			        LEFT JOIN mywallets AS out_wallet ON m.wallet_out_id = out_wallet.id  
			        WHERE transaction_type = 'Sending' AND created_by = $user_id
				    ORDER BY m.id DESC";

					if (strtolower($role) !== 'momo agent' && strtolower($role) !== 'solo agent') {

						$rows = $momoremit->query($query);

						$query = "SELECT SUM(amount) AS total_amount, SUM(charge) AS total_charge FROM momoremits WHERE deleted = 0 AND transaction_type = 'Sending' ";
						$total = $momoremit->query($query)[0];
						$total_amount = $total->total_amount ?? 0;
						$total_charge = $total->total_charge ?? 0;

					}else{

						$rows = $momoremit->query($query2);

						$query = "SELECT SUM(amount) AS total_amount, SUM(charge) AS total_charge FROM momoremits WHERE deleted = 0 AND transaction_type = 'Sending' AND created_by = $user_id";
						$total = $momoremit->query($query)[0];
						$total_amount = $total->total_amount ?? 0;
						$total_charge = $total->total_charge ?? 0;

					}
				}
			
			require plugin_path('views/admin/sendings.php');
			
		}else
		if(URL(2) == 'cashout-commissions' || URL(2) == 'commissions'){
			// Cashout Commissions Management View
			if (!user_can('view_momoremit_details') && !user_can('view_momoremit') && !user_can('view_momoremit_commissions') && !user_can('is_operations_manager')) {
				echo '<div class="alert alert-danger text-center">Access denied. You do not have permission to view this page.</div>';
				return;
			}
			
			$admin_route = $vars['admin_route'];
			$plugin_route = $vars['plugin_route'];
			
			require plugin_path('views/admin/cashout-commissions.php');
			
		}else
		if(URL(2) == 'exportList_pdf'){
			$user::$query_id = 'get-users';
			$momoremit = new \Momoremits\Momoremit;

			$query = "
			    SELECT 
			        m.*, 
			        u.first_name AS user_first_name, 
			        u.last_name AS user_last_name, 
			        u.image AS user_image,
			        u_updated.first_name AS updated_by_first_name, 
			        u_updated.last_name AS updated_by_last_name, 
			        u_updated.image AS updated_by_image,
            	    in_wallet.name AS in_wallet_name, 
            	    in_wallet.network AS in_wallet_network,
            	    out_wallet.name AS out_wallet_name, 
            	    out_wallet.network AS out_wallet_network 
			    FROM momoremits AS m 
			    LEFT JOIN users AS u ON m.created_by = u.id 
			    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id
        	    LEFT JOIN mywallets AS in_wallet ON m.wallet_in_id = in_wallet.id 
        	    LEFT JOIN mywallets AS out_wallet ON m.wallet_out_id = out_wallet.id  
			    WHERE m.deleted = 0
			    ORDER BY m.id DESC
			";

			$query2 = "
			    SELECT 
			        m.*, 
			        u.first_name AS user_first_name, 
			        u.last_name AS user_last_name, 
			        u.image AS user_image,
			        u_updated.first_name AS updated_by_first_name, 
			        u_updated.last_name AS updated_by_last_name, 
			        u_updated.image AS updated_by_image,
            	    in_wallet.name AS in_wallet_name, 
            	    in_wallet.network AS in_wallet_network,
            	    out_wallet.name AS out_wallet_name, 
            	    out_wallet.network AS out_wallet_network 
			    FROM momoremits AS m 
			    LEFT JOIN users AS u ON m.created_by = u.id 
			    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id
        	    LEFT JOIN mywallets AS in_wallet ON m.wallet_in_id = in_wallet.id 
        	    LEFT JOIN mywallets AS out_wallet ON m.wallet_out_id = out_wallet.id  
			    WHERE m.deleted = 0 AND m.created_by = :user_id
			    ORDER BY m.id DESC
			";

			if (in_array(strtolower($role), ['momo agent', 'solo agent'])) {
				$rows = $momoremit->query($query2, [':user_id' => $user_id]);
				$total = $momoremit->query("SELECT SUM(amount) AS total_amount, SUM(charge) AS total_charge FROM momoremits WHERE deleted = 0 AND created_by = :user_id", [':user_id' => $user_id])[0] ?? null;
			} else {
				$rows = $momoremit->query($query);
				$total = $momoremit->query("SELECT SUM(amount) AS total_amount, SUM(charge) AS total_charge FROM momoremits WHERE deleted = 0")[0] ?? null;
			}

			$total_amount = $total->total_amount ?? 0;
			$total_charge = $total->total_charge ?? 0;

			require plugin_path('views/admin/exportList_pdf.php');
            exit;
		} else {
			$user::$query_id = 'get-users';
		
			if(!empty($_GET['find']))
			{
				$find = '%' . trim($_GET['find']) . '%';
				$query = "
				SELECT
				    m.*, 
				    u.first_name AS user_first_name, 
				    u.last_name AS user_last_name, 
				    u.image AS user_image,
				    u_updated.first_name AS updated_by_first_name, 
				    u_updated.last_name AS updated_by_last_name, 
				    u_updated.image AS updated_by_image,
				    in_wallet.name AS in_wallet_name, 
				    in_wallet.network AS in_wallet_network,
				    out_wallet.name AS out_wallet_name, 
				    out_wallet.network AS out_wallet_network  
				FROM momoremits AS m 
				LEFT JOIN users AS u ON m.created_by = u.id 
				LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id
				LEFT JOIN mywallets AS in_wallet ON m.wallet_in_id = in_wallet.id 
				LEFT JOIN mywallets AS out_wallet ON m.wallet_out_id = out_wallet.id  
				WHERE (
				    m.network LIKE :find 
				    OR m.id LIKE :find 
				    OR m.transaction_type LIKE :find 
				    OR u.first_name LIKE :find 
				    OR u.last_name LIKE :find 
				    OR m.phone LIKE :find
				    OR in_wallet.network LIKE :find
				    OR in_wallet.name LIKE :find
				    OR out_wallet.network LIKE :find
				    OR out_wallet.name LIKE :find
				    OR m.date_created LIKE :find)
			   ";
				$rows = $momoremit->query($query,['find'=>$find]);

			}else{

				$query = "
				    SELECT 
				        m.*, 
				        u.first_name AS user_first_name,
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name, 
				        u_updated.last_name AS updated_by_last_name, 
				        u_updated.image AS updated_by_image,
				        in_wallet.name AS in_wallet_name, 
				        in_wallet.network AS in_wallet_network,
				        out_wallet.name AS out_wallet_name, 
				        out_wallet.network AS out_wallet_network 
				    FROM momoremits AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id
				    LEFT JOIN mywallets AS in_wallet ON m.wallet_in_id = in_wallet.id 
				    LEFT JOIN mywallets AS out_wallet ON m.wallet_out_id = out_wallet.id  
				    ORDER BY m.id DESC
				";

				$query2 = "
				    SELECT 
				        m.*, 
				        u.first_name               AS user_first_name, 
				        u.last_name                AS user_last_name, 
				        u.image                    AS user_image,
				        u_updated.first_name       AS updated_by_first_name, 
				        u_updated.last_name        AS updated_by_last_name, 
				        u_updated.image            AS updated_by_image,
				        in_wallet.name             AS in_wallet_name, 
				        in_wallet.network          AS in_wallet_network,
				        out_wallet.name            AS out_wallet_name, 
				        out_wallet.network         AS out_wallet_network 
				    FROM momoremits AS m 
				    LEFT JOIN users AS u           ON m.created_by   = u.id 
				    LEFT JOIN users AS u_updated   ON m.updated_by   = u_updated.id
				    LEFT JOIN mywallets AS in_wallet  ON m.wallet_in_id  = in_wallet.id 
				    LEFT JOIN mywallets AS out_wallet ON m.wallet_out_id = out_wallet.id  
				    WHERE m.deleted = 0
				      AND m.created_by = :user
				    ORDER BY m.id DESC
				";
			
				$rows = $momoremit->query($query2, [':user' => $user_id]);
				$query = "SELECT SUM(amount) AS total_amount, SUM(charge) AS total_charge FROM momoremits WHERE deleted = 0 AND created_by = $user_id";

				// and your total query similarly:
				$totalQuery = "
				    SELECT 
				      SUM(amount) AS total_amount, 
				      SUM(charge)  AS total_charge 
				    FROM momoremits 
				    WHERE deleted = 0 
				      AND created_by = :user
				";
				$totalResult = $momoremit->query($totalQuery, [':user' => $user_id]);
				$totalRow = $totalResult[0] ?? null;
				$total_amount = $totalRow->total_amount ?? 0;
				$total_charge = $totalRow->total_charge ?? 0;
			}

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
		$role_map = new \Momoremits\User_roles_map;
		foreach ($data['result'] as $key => $row) {
			
			$query = "select * from user_roles where disabled = 0 && id in (select role_id from user_roles_map where disabled = 0 && user_id = :user_id)";
			$roles = $role_map->query($query,['user_id'=>$row->id]);
			if($roles)
				$data['result'][$key]->roles = array_column($roles, 'role');
			
			/** get momoremit's roles **/
			$user_roles_map = new \Momoremits\User_roles_map;
				
			$role_ids = $user_roles_map->where(['user_id'=>$row->id,'disabled'=>0]);
			if($role_ids)
				$data['result'][$key]->role_ids = array_column($role_ids, 'role_id');

		}
	}

	return $data;
});

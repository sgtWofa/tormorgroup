<?php

/**
 * Plugin name: Logs
 * Description: A way for admin to manage logs
 * 
 * 
 **/

set_value([

	'admin_route'	=>'admin',
	'plugin_route'	=>'logs',
	'tables'		=>[
		'logs_table' 		=> 'logs',
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

// Runtime-safe schema ensure: production servers may not run CLI migrations
try{
	$cols = $db->query("SHOW COLUMNS FROM logs") ?: [];
	$existing = [];
	foreach($cols as $c){
		if(!is_object($c) || empty($c->Field)) continue;
		$existing[strtolower((string)$c->Field)] = true;
	}

	if(empty($existing['ip_address'])){
		$db->query("ALTER TABLE logs ADD COLUMN ip_address varchar(45) null");
		$db->query("ALTER TABLE logs ADD KEY (ip_address)");
	}
	if(empty($existing['user_agent'])){
		$db->query("ALTER TABLE logs ADD COLUMN user_agent text null");
	}
	if(empty($existing['device_details'])){
		$db->query("ALTER TABLE logs ADD COLUMN device_details text null");
	}
}catch(\Throwable $e){
	// Ignore: schema changes may be restricted; logging will gracefully omit these fields.
}


/** set log permissions for this plugin **/
add_filter('permissions',function($permissions){

	$permissions[] = 'view_logs';

	return $permissions;
});


add_action('controller', function(){
	$vars = get_value();
	$seg1 = URL(1) ?? '';
	$seg2 = URL(2) ?? '';
	$seg3 = URL(3) ?? '';

	if($seg1 !== ($vars['plugin_route'] ?? 'logs') && $seg2 !== ($vars['plugin_route'] ?? 'logs')){
		return;
	}

	$action = null;
	if($seg2 === 'ajax_list' || $seg3 === 'ajax_list') $action = 'ajax_list';
	if($seg2 === 'ajax_meta' || $seg3 === 'ajax_meta') $action = 'ajax_meta';
	if(!$action) return;

	ini_set('display_errors', '0');
	error_reporting(0);
	$jsonResponse = function(array $payload, int $status = 200): void {
		while(ob_get_level() > 0){
			ob_end_clean();
		}
		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		exit;
	};

	if(!user_can('view_logs')){
		$jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
	}

	$db = new \Core\Database;
	if(!$db->table_exists(['logs'])){
		$jsonResponse(['success' => false, 'message' => 'Logs not available'], 503);
	}

	try{
		$logColumns = [];
		foreach(($db->query('SHOW COLUMNS FROM logs') ?: []) as $column){
			$name = strtolower(trim((string)($column->Field ?? '')));
			if($name !== ''){
				$logColumns[$name] = true;
			}
		}
		$optionalLogColumns = ['ip_address', 'user_agent', 'device_details'];
		if($action === 'ajax_meta'){
			$actions = $db->query("SELECT DISTINCT action FROM logs WHERE action IS NOT NULL AND action != '' ORDER BY action ASC") ?: [];
			$tables = $db->query("SELECT DISTINCT table_name FROM logs WHERE table_name IS NOT NULL AND table_name != '' ORDER BY table_name ASC") ?: [];
			$users = [];
			if($db->table_exists(['users'])){
				$users = $db->query(
					"SELECT DISTINCT u.id AS user_id, u.first_name, u.last_name, u.email
					FROM logs l
					LEFT JOIN users u ON u.id = l.user_id
					WHERE l.user_id IS NOT NULL AND l.user_id > 0 AND (u.deleted = 0 OR u.deleted IS NULL)
					ORDER BY u.first_name ASC, u.last_name ASC",
					[]
				) ?: [];
			} else {
				$users = $db->query("SELECT DISTINCT user_id FROM logs WHERE user_id IS NOT NULL AND user_id > 0 ORDER BY user_id ASC") ?: [];
			}
			$usersPayload = [];
			foreach ($users as $r) {
				if(!is_object($r)) {
					continue;
				}
				$uid = (int)($r->user_id ?? ($r->id ?? 0));
				if($uid <= 0) {
					continue;
				}
				$un = trim((string)($r->user_name ?? ''));
				if($un === ''){
					$full = trim((string)($r->first_name ?? '') . ' ' . (string)($r->last_name ?? ''));
					$un = $full !== '' ? $full : trim((string)($r->email ?? ''));
				}
				if($un === ''){
					$un = '#' . $uid;
				}
				if(!isset($usersPayload[$uid])){
					$usersPayload[$uid] = ['user_id' => $uid, 'user_name' => $un];
				}
			}

			$jsonResponse([
				'success' => true,
				'actions' => array_values(array_filter(array_map(function($r){ return is_object($r) ? ($r->action ?? null) : null; }, $actions))),
				'tables' => array_values(array_filter(array_map(function($r){ return is_object($r) ? ($r->table_name ?? null) : null; }, $tables))),
				'users' => array_values($usersPayload),
			]);
		}

		$page = max(1, (int)($_GET['page'] ?? 1));
		$limit = (int)($_GET['limit'] ?? 10);
		if($limit < 5) $limit = 5;
		if($limit > 100) $limit = 100;
		$offset = ($page - 1) * $limit;

		$q = trim((string)($_GET['q'] ?? ''));
		$filterAction = trim((string)($_GET['action'] ?? ''));
		$filterTable = trim((string)($_GET['table'] ?? ''));
		$filterUserId = (int)($_GET['user_id'] ?? 0);
		$dateFrom = trim((string)($_GET['date_from'] ?? ''));
		$dateTo = trim((string)($_GET['date_to'] ?? ''));

		$where = [];
		$params = [];

		if($filterAction !== ''){
			$where[] = 'l.action = :action';
			$params['action'] = $filterAction;
		}
		if($filterTable !== ''){
			$where[] = 'l.table_name = :table_name';
			$params['table_name'] = $filterTable;
		}
		if($filterUserId > 0){
			$where[] = 'l.user_id = :user_id';
			$params['user_id'] = $filterUserId;
		}
		if($dateFrom !== ''){
			$where[] = 'DATE(l.date_created) >= :date_from';
			$params['date_from'] = $dateFrom;
		}
		if($dateTo !== ''){
			$where[] = 'DATE(l.date_created) <= :date_to';
			$params['date_to'] = $dateTo;
		}
		if($q !== ''){
			$searchColumns = ['l.description', 'l.table_name', 'l.action', 'l.user_name', 'CAST(l.user_id AS CHAR)'];
			foreach($optionalLogColumns as $optionalColumn){
				if(!empty($logColumns[$optionalColumn])){
					$searchColumns[] = 'l.' . $optionalColumn;
				}
			}
			$where[] = '(' . implode(' OR ', array_map(static function($column){ return $column . ' LIKE :q'; }, $searchColumns)) . ')';
			$params['q'] = '%' . $q . '%';
		}

		$whereSql = '';
		if(!empty($where)){
			$whereSql = 'WHERE ' . implode(' AND ', $where);
		}

		$totalRow = ($db->query("SELECT COUNT(*) AS c FROM logs l {$whereSql}", $params) ?: [])[0] ?? (object)['c'=>0];
		$totalRows = (int)($totalRow->c ?? 0);
		$totalPages = $totalRows ? (int)ceil($totalRows / $limit) : 1;

		$optionalSelect = [];
		foreach($optionalLogColumns as $optionalColumn){
			$optionalSelect[] = !empty($logColumns[$optionalColumn])
				? 'l.' . $optionalColumn
				: 'NULL AS ' . $optionalColumn;
		}
		$rows = $db->query(
			"SELECT l.*, " . implode(', ', $optionalSelect) . ", u.first_name, u.last_name, u.email
			FROM logs l
			LEFT JOIN users u ON u.id = l.user_id
			{$whereSql}
			ORDER BY l.date_created DESC
			LIMIT {$limit} OFFSET {$offset}",
			$params
		) ?: [];

		$payload = array_map(function($r){
			$platform = null;
			$language = null;
			try{
				$raw = $r->device_details ?? null;
				if(is_string($raw) && $raw !== ''){
					$decoded = json_decode($raw, true);
					if(is_array($decoded)){
						$platform = $decoded['platform'] ?? null;
						$language = $decoded['language'] ?? null;
					}
				}
			}catch(\Throwable $e){
				$platform = null;
				$language = null;
			}

			return [
				'id' => (int)($r->id ?? 0),
				'date_created' => $r->date_created ?? null,
				'table_name' => $r->table_name ?? null,
				'action' => $r->action ?? null,
				'user_id' => (int)($r->user_id ?? 0),
				'user_name' => (function($row){
					$full = trim((string)($row->first_name ?? '') . ' ' . (string)($row->last_name ?? ''));
					if($full !== '') return $full;
					$em = trim((string)($row->email ?? ''));
					if($em !== '') return $em;
					$un = trim((string)($row->user_name ?? ''));
					if($un !== '') return $un;
					$uid = (int)($row->user_id ?? 0);
					return $uid > 0 ? ('#' . $uid) : null;
				})($r),
				'description' => $r->description ?? null,
				'ip_address' => $r->ip_address ?? null,
				'user_agent' => $r->user_agent ?? null,
				'device_platform' => $platform,
				'device_language' => $language,
			];
		}, $rows);

		$jsonResponse([
			'success' => true,
			'rows' => $payload,
			'pagination' => [
				'page' => $page,
				'limit' => $limit,
				'total_rows' => $totalRows,
				'total_pages' => $totalPages,
			],
		]);
	}catch(\Throwable $e){
		$jsonResponse(['success' => false, 'message' => 'Failed to load logs'], 500);
	}
});


/** add to amin links **/
add_filter('basic-admin_before_admin_links',function($links){

	if(user_can('view_logs'))
	{
		$vars = get_value();

		$obj = (object)[];
		$obj->title = 'Logs';
		$obj->link = ROOT . '/'.$vars['admin_route'].'/'.$vars['plugin_route'];
		$obj->icon = 'fas fa-list-alt';
		$obj->parent = 0;
		$links[] = $obj;
	}

	return $links;
});


/** displays the view file **/
add_action('basic-admin_main_content',function(){

	$ses = new \Core\Session;
	$vars = get_value();

	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];
	
	$errors = $vars['errors'] ?? [];

	$log = new \Logs\Log;

	if(URL(1) == $vars['plugin_route']){
	
		$limit = 30;
		$pager = new \Core\Pager($limit);
		$offset = $pager->offset;

		$log->limit = $limit;
		$log->offset = $offset;
		$log::$query_id = 'get-logs';
				
		$rows = $log->getAll();

		require plugin_path('views/logs.php');
	

	}
});




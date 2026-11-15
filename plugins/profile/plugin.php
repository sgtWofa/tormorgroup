<?php

/**
 * Plugin name: Users Manager
 * Description: A way for admin to manage users
 * 
 * 
 **/

set_value([

	'admin_route'	=>'admin',
	'plugin_route'	=>'profile',
	'tables'		=>[
		'users_table' 		=> 'users',
	],

	'optional_tables'		=>[
		'roles_table' 		=> 'user_roles',
		'permissions_table' => 'role_permissions',
		'roles_map_table' 	=> 'user_roles_map',
	],
	

]);


/** check if all tables exist **/
$db = new \Core\Database;
$tables = get_value()['tables'];

if(!$db->table_exists($tables)){
	dd("Missing database tables in ".plugin_id() ." plugin: ". implode(",", $db->missing_tables));
	die;
}


/** set user permissions for this plugin **/
add_filter('permissions',function($permissions){

	$permissions[] = 'all';
	$permissions[] = 'view_profile';
	$permissions[] = 'view_profile_details';
	$permissions[] = 'edit_profile';
	$permissions[] = 'delete_profile';

	return $permissions;
});



/** run this after a form submit **/
add_action('controller',function(){

	$req = new \Core\Request;
	$vars = get_value();
	
	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];

	if(URL(1) == $vars['plugin_route'] && $req->posted())
	{
		$ses = new \Core\Session;
		$user = new \UsersManager\User;
		$user_roles_map = new \UsersManager\User_roles_map;

		$id = URL(3) ?? null;
		if($id)
			$row = $user->first(['id'=>$id]);

		if(URL(2) == 'edit'){
			
			require plugin_path('controllers/edit-controller.php');
		}else
		if(URL(2) == 'delete'){

			require plugin_path('controllers/delete-controller.php');
		}else
		if(URL(2) == 'resetpassword'){

			require plugin_path('controllers/resetpassword-controller.php');
		}

	}
});


/** Lightweight AJAX endpoints for paginated profile tabs **/
add_action('controller', function(){
	$vars = get_value();
	$req = new \Core\Request;
	$seg1 = URL(1) ?? '';
	$seg2 = URL(2) ?? '';
	$seg3 = URL(3) ?? '';

	// Only respond to ajax_* routes that belong to this plugin.
	// Valid shapes:
	// - /profile/ajax_xxx
	// - /admin/profile/ajax_xxx
	if($seg1 !== ($vars['plugin_route'] ?? 'profile') && $seg2 !== ($vars['plugin_route'] ?? 'profile')){
		return;
	}

	$action = null;
	if($seg2 === 'ajax_attendance' || $seg3 === 'ajax_attendance') $action = 'ajax_attendance';
	if($seg2 === 'ajax_cashdrawers' || $seg3 === 'ajax_cashdrawers') $action = 'ajax_cashdrawers';
	if($seg2 === 'ajax_activity' || $seg3 === 'ajax_activity') $action = 'ajax_activity';
	if($seg2 === 'ajax_deno_history' || $seg3 === 'ajax_deno_history') $action = 'ajax_deno_history';
	if(!$action) return;

	while (ob_get_level() > 0) {
		ob_end_clean();
	}
	ini_set('display_errors', '0');
	error_reporting(E_ERROR | E_PARSE);
	header('Content-Type: application/json; charset=utf-8');

	$session = get_session();
	$currentUserId = (int)($session->id ?? 0);
	$profileUserId = (int)($_GET['user_id'] ?? 0);
	if($profileUserId <= 0){
			echo json_encode(['success' => false, 'message' => 'Missing user_id'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		exit;
	}

	if(!(user_can('view_profile_details') || $currentUserId === $profileUserId)){
		http_response_code(403);
		echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		exit;
	}

	$page = max(1, (int)($_GET['page'] ?? 1));
	$limit = (int)($_GET['limit'] ?? 10);
	if($limit < 5) $limit = 5;
	if($limit > 100) $limit = 100;
	$offset = ($page - 1) * $limit;

	$db = new \Core\Database;

	try{
		if($action === 'ajax_deno_history'){
			$drawerId = (int)($_GET['drawer_id'] ?? 0);
			if($drawerId <= 0){
				echo json_encode(['success' => false, 'message' => 'Missing drawer_id']);
				exit;
			}
			if(!$db->table_exists(['cashdrawers'])){
				echo json_encode(['success' => false, 'message' => 'Cashdrawers not available']);
				exit;
			}
			$drawerRow = ($db->query(
				"SELECT id, user_id FROM cashdrawers WHERE id = :id LIMIT 1",
				['id' => $drawerId]
			) ?: [])[0] ?? null;
			if(empty($drawerRow) || (int)($drawerRow->user_id ?? 0) !== $profileUserId){
				http_response_code(404);
				echo json_encode(['success' => false, 'message' => 'Cashdrawer not found for this user']);
				exit;
			}
			if(!function_exists('operations_account_collect_deno_history')){
				echo json_encode(['success' => false, 'message' => 'Denomination history is not available']);
				exit;
			}

			$payload = operations_account_collect_deno_history($drawerId, $profileUserId, $offset, $limit);
			echo json_encode([
				'success' => true,
				'page' => $page,
				'limit' => $limit,
				'has_more' => !empty($payload['has_more']),
				'items' => array_values($payload['movements'] ?? []),
				'opening' => $page === 1 ? array_values($payload['opening'] ?? []) : [],
			]);
			exit;
		}

		if($action === 'ajax_attendance'){
			if(!$db->table_exists(['attendance'])){
				echo json_encode(['success' => false, 'message' => 'Attendance not available']);
				exit;
			}
			$workOpen = null;
			$lateAttendanceTime = null;
			try{
				if($db->table_exists(['settings'])){
					$srow = ($db->query("SELECT work_open, late_attendance_time FROM settings ORDER BY id DESC LIMIT 1") ?: [])[0] ?? null;
					$workOpen = $srow ? trim((string)($srow->work_open ?? '')) : null;
					$lateAttendanceTime = $srow ? trim((string)($srow->late_attendance_time ?? '')) : null;
					if($workOpen === '') $workOpen = null;
					if($lateAttendanceTime === '') $lateAttendanceTime = null;
				}
			}catch(\Throwable $e){
				$workOpen = null;
				$lateAttendanceTime = null;
			}
			$computeLateThreshold = function($wo){
				$wo = trim((string)$wo);
				if($wo === '') return '08:30:00';
				if(strlen($wo) === 5) $wo .= ':00';
				$ts = strtotime('1970-01-01 ' . $wo);
				if($ts === false) return '08:30:00';
				return date('H:i:s', $ts + (30 * 60));
			};
			$lateThreshold = $lateAttendanceTime ?: $computeLateThreshold($workOpen ?: '08:00:00');
			if($lateThreshold && strlen($lateThreshold) === 5) $lateThreshold .= ':00';
			$computeRemarks = function($remarks, $time) use ($lateThreshold){
				$rem = strtoupper(trim((string)$remarks));
				if($rem !== 'PRESENT' && $rem !== 'LATE') return $remarks;
				$t = trim((string)$time);
				if($t === '') return $remarks;
				if(strlen($t) === 5) $t .= ':00';
				$timeSec = strtotime('1970-01-01 ' . $t);
				$thrSec = strtotime('1970-01-01 ' . $lateThreshold);
				if($timeSec === false || $thrSec === false) return $remarks;
				return ($timeSec >= $thrSec) ? 'LATE' : 'PRESENT';
			};
			$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
			$dateTo = $_GET['date_to'] ?? date('Y-m-d');
			$params = ['uid' => (string)$profileUserId, 'dfrom' => $dateFrom, 'dto' => $dateTo];

			$totalRow = ($db->query(
				"SELECT COUNT(*) AS c FROM attendance a WHERE a.deleted = 0 AND a.user_id = :uid AND a.date >= :dfrom AND a.date <= :dto",
				$params
			) ?: [])[0] ?? (object)['c'=>0];
			$totalRows = (int)($totalRow->c ?? 0);
			$totalPages = $totalRows ? (int)ceil($totalRows / $limit) : 1;

			$rows = $db->query(
				"SELECT a.*, u.first_name AS created_by_first_name, u.last_name AS created_by_last_name, u.deleted AS created_by_deleted
				FROM attendance a
				LEFT JOIN users u ON a.created_by = u.id
				WHERE a.deleted = 0 AND a.user_id = :uid AND a.date >= :dfrom AND a.date <= :dto
				ORDER BY a.date DESC, a.time DESC
				LIMIT {$limit} OFFSET {$offset}",
				$params
			) ?: [];

			$payload = array_map(function($r) use ($computeRemarks){
				$createdByName = trim(($r->created_by_first_name ?? '').' '.($r->created_by_last_name ?? ''));
				if($createdByName !== '' && (int)($r->created_by_deleted ?? 0) === 1){
					$createdByName .= ' (Deleted user)';
				}
				$effRemarks = $computeRemarks($r->remarks ?? '', $r->time ?? '');
				return [
					'date' => $r->date ?? '',
					'time' => $r->time ?? '',
					'remarks' => $effRemarks,
					'created_by' => $createdByName !== '' ? $createdByName : 'System marked',
					'date_created' => $r->date_created ?? null,
				];
			}, $rows);

			echo json_encode([
				'success' => true,
				'rows' => $payload,
				'pagination' => [
					'page' => $page,
					'limit' => $limit,
					'total_rows' => $totalRows,
					'total_pages' => $totalPages,
				],
			]);
			exit;
		}

		if($action === 'ajax_cashdrawers'){
			if(!$db->table_exists(['cashdrawers'])){
				echo json_encode(['success' => false, 'message' => 'Cashdrawers not available']);
				exit;
			}
			$dateFrom = $_GET['date_from'] ?? date('Y-m-d 00:00:00', strtotime('-30 days'));
			$dateTo = $_GET['date_to'] ?? date('Y-m-d 23:59:59');
			$params = ['uid' => (string)$profileUserId, 'dfrom' => $dateFrom, 'dto' => $dateTo];

			$totalRow = ($db->query(
				"SELECT COUNT(*) AS c FROM cashdrawers c WHERE c.user_id = :uid AND c.date_created >= :dfrom AND c.date_created <= :dto",
				$params
			) ?: [])[0] ?? (object)['c'=>0];
			$totalRows = (int)($totalRow->c ?? 0);
			$totalPages = $totalRows ? (int)ceil($totalRows / $limit) : 1;

			$rows = $db->query(
				"SELECT c.*, u.first_name AS created_by_first_name, u.last_name AS created_by_last_name, u.deleted AS created_by_deleted
				FROM cashdrawers c
				LEFT JOIN users u ON c.created_by = u.id
				WHERE c.user_id = :uid AND c.date_created >= :dfrom AND c.date_created <= :dto
				ORDER BY c.date_created DESC
				LIMIT {$limit} OFFSET {$offset}",
				$params
			) ?: [];

			// Denomination breakdowns (GHS + CFA) for drawers on this page
			$drawerIds = [];
			foreach($rows as $r){
				$did = (int)($r->id ?? 0);
				if($did > 0) $drawerIds[] = $did;
			}
			$denosGhsByDrawer = [];
			$denosCfaByDrawer = [];
			if(!empty($drawerIds)){
				$placeholders = [];
				$idParams = [];
				foreach(array_values(array_unique($drawerIds)) as $i => $did){
					$key = 'd' . $i;
					$placeholders[] = ':' . $key;
					$idParams[$key] = (int)$did;
				}
				$inSql = implode(',', $placeholders);

				try{
					if($db->table_exists(['drawer_denos'])){
						$q = "SELECT drawer_id, denomination, SUM(`count`) AS cnt, SUM(volume) AS vol
							FROM drawer_denos
							WHERE drawer_id IN ({$inSql}) AND user_id = :uid
							GROUP BY drawer_id, denomination
							HAVING ABS(COALESCE(SUM(volume),0)) > 0.009 OR ABS(COALESCE(SUM(`count`),0)) > 0
							ORDER BY denomination DESC";
						$rowsG = $db->query($q, array_merge($idParams, ['uid' => (int)$profileUserId])) ?: [];
						foreach($rowsG as $dr){
							$did = (int)($dr->drawer_id ?? 0);
							if($did <= 0) continue;
							$cnt = (int)($dr->cnt ?? 0);
							$vol = (float)($dr->vol ?? 0);
							if($cnt === 0 && abs($vol) <= 0.009) continue;
							if(!isset($denosGhsByDrawer[$did])) $denosGhsByDrawer[$did] = [];
							$denosGhsByDrawer[$did][] = [
								'denomination' => (float)($dr->denomination ?? 0),
								'count' => $cnt,
								'volume' => $vol,
							];
						}
					}
				}catch(\Throwable $e){
					$denosGhsByDrawer = [];
				}

				try{
					if($db->table_exists(['drawer_fx_denos'])){
						$q = "SELECT drawer_id, denomination, SUM(`count`) AS cnt, SUM(volume) AS vol
							FROM drawer_fx_denos
							WHERE drawer_id IN ({$inSql}) AND user_id = :uid
							GROUP BY drawer_id, denomination
							HAVING ABS(COALESCE(SUM(volume),0)) > 0.009 OR ABS(COALESCE(SUM(`count`),0)) > 0
							ORDER BY denomination DESC";
						$rowsC = $db->query($q, array_merge($idParams, ['uid' => (int)$profileUserId])) ?: [];
						foreach($rowsC as $dr){
							$did = (int)($dr->drawer_id ?? 0);
							if($did <= 0) continue;
							$cnt = (int)($dr->cnt ?? 0);
							$vol = (float)($dr->vol ?? 0);
							if($cnt === 0 && abs($vol) <= 0.009) continue;
							if(!isset($denosCfaByDrawer[$did])) $denosCfaByDrawer[$did] = [];
							$denosCfaByDrawer[$did][] = [
								'denomination' => (float)($dr->denomination ?? 0),
								'count' => $cnt,
								'volume' => $vol,
							];
						}
					}
				}catch(\Throwable $e){
					$denosCfaByDrawer = [];
				}
			}

			$payload = array_map(function($r) use ($denosGhsByDrawer, $denosCfaByDrawer){
				$did = (int)($r->id ?? 0);
				$ghsDenos = $denosGhsByDrawer[$did] ?? [];
				$cfaDenos = $denosCfaByDrawer[$did] ?? [];
				$createdByName = trim(($r->created_by_first_name ?? '').' '.($r->created_by_last_name ?? ''));
				if($createdByName !== '' && (int)($r->created_by_deleted ?? 0) === 1){
					$createdByName .= ' (Deleted user)';
				}
				return [
					'id' => (int)($r->id ?? 0),
					'currency' => strtoupper((string)($r->currency ?? '')),
					'amount' => (float)($r->amount ?? 0),
					'date_created' => $r->date_created ?? null,
					'created_by' => $createdByName !== '' ? $createdByName : '-',
					'ghs_denos' => is_array($ghsDenos) ? $ghsDenos : [],
					'cfa_denos' => is_array($cfaDenos) ? $cfaDenos : [],
				];
			}, $rows);

			echo json_encode([
				'success' => true,
				'rows' => $payload,
				'pagination' => [
					'page' => $page,
					'limit' => $limit,
					'total_rows' => $totalRows,
					'total_pages' => $totalPages,
				],
			]);
			exit;
		}

		if($action === 'ajax_activity'){
			if(!$db->table_exists(['logs'])){
				echo json_encode(['success' => false, 'message' => 'Logs not available']);
				exit;
			}
			$params = ['uid' => (string)$profileUserId];
			$totalRow = ($db->query(
				"SELECT COUNT(*) AS c FROM logs l WHERE l.user_id = :uid",
				$params
			) ?: [])[0] ?? (object)['c'=>0];
			$totalRows = (int)($totalRow->c ?? 0);
			$totalPages = $totalRows ? (int)ceil($totalRows / $limit) : 1;

			$rows = $db->query(
				"SELECT l.*
				FROM logs l
				WHERE l.user_id = :uid
				ORDER BY l.date_created DESC
				LIMIT {$limit} OFFSET {$offset}",
				$params
			) ?: [];

			$payload = array_map(function($r){
				$details = null;
				$platform = null;
				$language = null;
				try{
					$raw = $r->device_details ?? null;
					if(is_string($raw) && $raw !== ''){
						$decoded = json_decode($raw, true);
						if(is_array($decoded)){
							$details = $decoded;
							$platform = $decoded['platform'] ?? null;
							$language = $decoded['language'] ?? null;
						}
					}
				}catch(\Throwable $e){
					$details = null;
				}

				return [
					'date_created' => $r->date_created ?? null,
					'table_name' => $r->table_name ?? '—',
					'action' => $r->action ?? '—',
					'description' => $r->description ?? '',
					'ip_address' => $r->ip_address ?? null,
					'user_agent' => $r->user_agent ?? null,
					'device_platform' => $platform,
					'device_language' => $language,
				];
			}, $rows);

			echo json_encode([
				'success' => true,
				'rows' => $payload,
				'pagination' => [
					'page' => $page,
					'limit' => $limit,
					'total_rows' => $totalRows,
					'total_pages' => $totalPages,
				],
			]);
			exit;
		}
	}catch(\Throwable $e){
		http_response_code(500);
		echo json_encode(['success' => false, 'message' => 'Failed to load data']);
		exit;
	}
});


/** displays the view file **/
add_action('basic-admin_main_content',function(){

	$ses = new \Core\Session;
	$vars = get_value();

	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];
	
	$errors = $vars['errors'] ?? [];

	$user = new \UsersManager\User;
	$student = new \UsersManager\Student;

	if(URL(1) == $vars['plugin_route']){

		$id = URL(3) ?? null;
		if((URL(2) ?? '') == 'view' && empty($id)){
			$id = $ses->user('id') ?? null;
		}

		if($id){
			$user::$query_id = 'get-users';
			$row = $user->first(['id'=>$id]);
			$studentRow = $student->first(['id'=>$id]);
		}

		if(URL(2) == 'edit'){
			
			$user_role = new \UsersManager\User_role;
			require plugin_path('views/edit.php');
		}else
		if(URL(2) == 'delete'){

			require plugin_path('views/delete.php');
		}else
		if(URL(2) == 'view'){

			$user::$query_id = 'get-users';

				$query = "
				    SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name, 
				        u_updated.last_name AS updated_by_last_name, 
				        u_updated.image AS updated_by_image 
				    FROM users AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
				    WHERE m.id = :id 
				    LIMIT 1";

				$row = $user->query($query, ['id' => $id]);

				// Check if the query was successful and if a result was returned
				if ($row && is_array($row) && isset($row[0])) {
				    $row = $row[0]; // Access the first row in the result
				} else {
				    // Handle the error or empty result
				    echo "Error: No results found or query failed.";
				    // Optionally log the error message if you have logging enabled
				}

			require plugin_path('views/view.php');
		}else
		if(URL(2) == 'resetpassword'){

			require plugin_path('views/resetpassword.php');
		}

	}
});


/** for manipulating data after a query operation **/
add_filter('after_query',function($data){

	
	if(empty($data['result']))
		return $data;

	if($data['query_id'] == 'get-users')
	{
		$role_map = new \UsersManager\User_roles_map;
		foreach ($data['result'] as $key => $row) {
			
			$query = "select * from user_roles where disabled = 0 && id in (select role_id from user_roles_map where disabled = 0 && user_id = :user_id)";
			$roles = $role_map->query($query,['user_id'=>$row->id]);
			if($roles)
				$data['result'][$key]->roles = array_column($roles, 'role');
			
			/** get user's roles **/
			$user_roles_map = new \UsersManager\User_roles_map;
				
			$role_ids = $user_roles_map->where(['user_id'=>$row->id,'disabled'=>0]);
			if($role_ids)
				$data['result'][$key]->role_ids = array_column($role_ids, 'role_id');

		}
	}

	if($data['query_id'] == 'get-students')
	{
		$role_map = new \UsersManager\User_roles_map;
		$category_map = new \UsersManager\Student_categories_map;
		foreach ($data['result'] as $key => $row) {
			
			$query = "select * from user_roles where disabled = 0 && id in (select role_id from user_roles_map where disabled = 0 && user_id = :user_id)";
			$roles = $role_map->query($query,['user_id'=>$row->id]);
			if($roles)
				$data['result'][$key]->roles = array_column($roles, 'role');
			
			/** get user's roles **/
			$user_roles_map = new \UsersManager\User_roles_map;
				
			$role_ids = $user_roles_map->where(['user_id'=>$row->id,'disabled'=>0]);
			if($role_ids)
				$data['result'][$key]->role_ids = array_column($role_ids, 'role_id');


			
			$query = "select * from categories where disabled = 0 && id in (select category_id from students_categories_map where disabled = 0 && student_id = :student_id)";
			$categories = $category_map->query($query,['student_id'=>$row->id]);
			if($categories)
				$data['result'][$key]->categories = array_column($categories, 'category');

			
			/** get student's categories **/
			$student_categories_map = new \UsersManager\Student_categories_map;
				
			$category_ids = $student_categories_map->where(['student_id'=>$row->id,'disabled'=>0]);
			if($category_ids)
				$data['result'][$key]->category_ids = array_column($category_ids, 'category_id');
			//dd($data);

		}
	}


	return $data;
});



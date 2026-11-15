<?php

/**
 * Plugin name: Reversal
 * Description: A way for admin to manage reversal
 * 
 * 
 **/

require_once __DIR__ . '/helpers/workflow.php';
if (file_exists(__DIR__ . '/../backoffice/helpers/cashdrawer-bridge.php')) {
	require_once __DIR__ . '/../backoffice/helpers/cashdrawer-bridge.php';
	if (function_exists('BackOfficeCashdrawer\\init')) {
		\BackOfficeCashdrawer\init();
	}
}
reversals_ensure_workflow_schema();

set_value([

	'admin_route'	=>'admin',
	'plugin_route'	=>'reversals',
	'tables'		=>[
		'reversals_table' 				=> 'reversals',
	],

	'optional_tables'		=>[
	],
	

]);

if(!function_exists('reversals_json_response')){
	function reversals_json_response(array $payload, int $status = 200): void
	{
		$GLOBALS['reversals_json_response_sent'] = true;
		while(ob_get_level() > 0){
			ob_end_clean();
		}
		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		exit;
	}
}

$reversalsJsonRoute = URL(1) === 'reversals' && in_array((string)(URL(2) ?? ''), ['ajax_analytics', 'lookup_transaction'], true);
if($reversalsJsonRoute){
	ini_set('display_errors', '0');
	ob_start();
	$GLOBALS['reversals_json_response_sent'] = false;
	register_shutdown_function(static function(): void {
		if(!empty($GLOBALS['reversals_json_response_sent'])){
			return;
		}
		$error = error_get_last();
		while(ob_get_level() > 0){
			ob_end_clean();
		}
		http_response_code(500);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(['success' => false, 'message' => 'Reversals request failed. Please try again.'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
	});
	set_exception_handler(static function(\Throwable $exception): void {
		reversals_json_response(['success' => false, 'message' => 'Reversals request failed. Please try again.'], 500);
	});
}


/** check if all tables exist **/
$db = new \Core\Database;
$tables = get_value()['tables'];

if(!$db->table_exists($tables)){
	dd("Missing database tables in ".plugin_id() ." plugin: ". implode(",", $db->missing_tables));
	die;
}

if(!function_exists('reversals_user_can_view_all')){
	function reversals_user_can_view_all($session = null): bool
	{
		return user_can('approve_reversal');
	}
}

if(!function_exists('reversals_build_listing_filters')){
	function reversals_build_listing_filters(array $input, bool $canViewAll, int $userId): array
	{
		$params = [];
		$where = [];

		if (!$canViewAll) {
			$where[] = "(m.created_by = :user_id OR m.agent_involved = :user_id)";
			$params['user_id'] = $userId;
		}

		$find = trim((string)($input['find'] ?? $input['q'] ?? ''));
		if ($find !== '') {
			$params['find'] = '%' . $find . '%';
			$where[] = "(m.network LIKE :find
				OR m.id LIKE :find
				OR m.source_transaction_id LIKE :find
				OR m.transaction_status LIKE :find
				OR m.service_type LIKE :find
				OR m.sender_details LIKE :find
				OR m.receiver_details LIKE :find
				OR u.first_name LIKE :find
				OR u.last_name LIKE :find
				OR u_agent_involved.first_name LIKE :find
				OR u_agent_involved.last_name LIKE :find
				OR u_assigned.first_name LIKE :find
				OR u_assigned.last_name LIKE :find
				OR m.date_created LIKE :find)";
		}

		$statusFilter = strtolower(trim((string)($input['status'] ?? '')));
		if ($statusFilter !== '' && $statusFilter !== 'all') {
			$where[] = "LOWER(COALESCE(m.transaction_status,'')) = :status_filter";
			$params['status_filter'] = $statusFilter;
		}

		$networkFilter = trim((string)($input['network'] ?? ''));
		if (strtolower($networkFilter) === 'all') {
			$networkFilter = '';
		}
		if ($networkFilter !== '') {
			$where[] = "m.network LIKE :network_filter";
			$params['network_filter'] = '%' . $networkFilter . '%';
		}

		$dateFrom = trim((string)($input['date_from'] ?? ''));
		if ($dateFrom !== '') {
			$where[] = "DATE(m.date_created) >= :date_from";
			$params['date_from'] = $dateFrom;
		}

		$dateTo = trim((string)($input['date_to'] ?? ''));
		if ($dateTo !== '') {
			$where[] = "DATE(m.date_created) <= :date_to";
			$params['date_to'] = $dateTo;
		}

		return [
			'where' => !empty($where) ? (' WHERE ' . implode(' AND ', $where)) : '',
			'params' => $params,
			'input' => [
				'find' => $find,
				'status' => $statusFilter,
				'network' => $networkFilter,
				'date_from' => $dateFrom,
				'date_to' => $dateTo,
			],
		];
	}
}


/** set reversal permissions for this plugin **/
add_filter('permissions',function($permissions){

	$permissions[] = 'view_operations';
	$permissions[] = 'view_reversal';
	$permissions[] = 'view_reversal_details';
	$permissions[] = 'add_reversal';
	$permissions[] = 'edit_reversal';
	$permissions[] = 'approve_reversal';
	$permissions[] = 'delete_reversal';

	return $permissions;
});


/** Add to admin links **/
add_filter('basic-admin_before_admin_links', function($links) {
    $vars = get_value();
    $reversal = new \Reversals\Reversal;
    $session = get_session();
    $user_id = $session->id;

    // Define the queries
    $query = "SELECT COUNT(id) AS pending_reversal FROM reversals WHERE LOWER(COALESCE(transaction_status,'')) IN ('pending manager acknowledgement','refund pending review','claim pending review','follow up required') LIMIT 1";
    $queryLower = "SELECT COUNT(id) AS pending_reversal FROM reversals WHERE LOWER(COALESCE(transaction_status,'')) IN ('pending manager acknowledgement','refund pending review','claim pending review','follow up required') AND (created_by = :user_id OR agent_involved = :user_id) LIMIT 1";

    // Execute queries
    $rows = $reversal->query($query);
    $rowsL = $reversal->query($queryLower, ['user_id' => $user_id]);

    // Determine total pending based on permissions
    $total_pending = 0;
    if (user_can('approve_reversal')) {
        $total_pending = $rows[0]->pending_reversal ?? 0;
    } elseif (!empty($rowsL)) {
        $total_pending = $rowsL[0]->pending_reversal ?? 0;
    }

    // Format the badge
    $countBadge = $total_pending > 0 ? (string)$total_pending : '';

    // Add the "Reversals" menu item if the user can view reversals
    if (user_can('view_reversal')) {
        $obj = (object)[];
        $obj->title = 'Reversals';
        $obj->id = 'view_reversal';
        $obj->link = ROOT . '/' . $vars['admin_route'] . '/reversals';
        $obj->icon = 'fas fa-undo'; // Updated main icon for "Reversal"
        $obj->parent = 0;
        $obj->count = $countBadge;

        $links[] = $obj;
    }

    return $links;
});


/** run this after a form submit **/
add_action('controller',function(){

	$req = new \Core\Request;
	$vars = get_value();
	
	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];

	if(URL(1) == $vars['plugin_route'] && URL(2) == 'ajax_analytics')
	{
		if (!user_can('view_reversal')) {
			reversals_json_response(['success' => false, 'message' => 'Unauthorized'], 403);
		}

		try {
			$session = get_session();
			$userId = (int)($session->id ?? 0);
			$canViewAll = reversals_user_can_view_all($session);
			$filters = reversals_build_listing_filters($_GET, $canViewAll, $userId);
			$where = (string)($filters['where'] ?? '');
			$params = (array)($filters['params'] ?? []);
			$dbAjax = new \Core\Database;
			$joinSql = "
				FROM reversals AS m
				LEFT JOIN users AS u ON m.created_by = u.id
				LEFT JOIN users AS u_agent_involved ON m.agent_involved = u_agent_involved.id
				LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id
			";

			$sumRow = $dbAjax->query(
				"SELECT COUNT(*) AS cnt, COALESCE(SUM(m.amount),0) AS total, COALESCE(AVG(m.amount),0) AS avg_amt {$joinSql} {$where}",
				$params
			) ?: [];
			$summary = (object)[
				'total_count' => !empty($sumRow) ? (int)($sumRow[0]->cnt ?? 0) : 0,
				'total_amount' => !empty($sumRow) ? (float)($sumRow[0]->total ?? 0) : 0.0,
				'avg_amount' => !empty($sumRow) ? (float)($sumRow[0]->avg_amt ?? 0) : 0.0,
				'median_amount' => 0.0,
			];

			$amountRows = $dbAjax->query(
				"SELECT m.amount AS amount {$joinSql} {$where} ORDER BY m.amount ASC",
				$params
			) ?: [];
			$n = count($amountRows);
			if ($n > 0) {
				$mid = (int)floor(($n - 1) / 2);
				if ($n % 2 === 1) {
					$summary->median_amount = (float)($amountRows[$mid]->amount ?? 0);
				} else {
					$a = (float)($amountRows[$mid]->amount ?? 0);
					$b = (float)($amountRows[$mid + 1]->amount ?? 0);
					$summary->median_amount = ($a + $b) / 2;
				}
			}

			$statusRows = $dbAjax->query(
				"SELECT LOWER(COALESCE(m.transaction_status,'')) AS status_key, COUNT(*) AS cnt, COALESCE(SUM(m.amount),0) AS total {$joinSql} {$where} GROUP BY LOWER(COALESCE(m.transaction_status,''))",
				$params
			) ?: [];
			$status = [];
			$statusCounts = [];
			foreach ($statusRows as $srow) {
				$key = (string)($srow->status_key ?? '');
				$count = (int)($srow->cnt ?? 0);
				$status[$key] = [
					'count' => $count,
					'total' => (float)($srow->total ?? 0),
				];
				$statusCounts[$key] = $count;
			}
			$statusSummary = reversals_summarize_status_counts($statusCounts);

			$byProvider = $dbAjax->query(
				"SELECT COALESCE(NULLIF(m.network,''), 'UNSPECIFIED') AS provider, COUNT(*) AS cnt, COALESCE(SUM(m.amount),0) AS total {$joinSql} {$where} GROUP BY m.network ORDER BY total DESC",
				$params
			) ?: [];
			$trend = $dbAjax->query(
				"SELECT DATE_FORMAT(m.date_created, '%Y-%m') AS ym, COUNT(*) AS cnt, COALESCE(SUM(m.amount),0) AS total {$joinSql} {$where} GROUP BY ym ORDER BY ym ASC",
				$params
			) ?: [];

			reversals_json_response([
				'success' => true,
				'summary' => $summary,
				'status' => $status,
				'status_summary' => $statusSummary,
				'by_provider' => $byProvider,
				'trend' => $trend,
			]);
		} catch (\Throwable $e) {
			reversals_json_response(['success' => false, 'message' => 'Failed to load reversals analytics'], 500);
		}
	}

	if(URL(1) == $vars['plugin_route'] && URL(2) == 'export_pdf')
	{
		if (!user_can('view_reversal')) {
			echo '<div class="alert alert-danger text-center">Access denied.</div>';
			die;
		}

		try {
			$session = get_session();
			$userId = (int)($session->id ?? 0);
			$canViewAll = reversals_user_can_view_all($session);
			$filters = reversals_build_listing_filters($_GET, $canViewAll, $userId);
			$where = (string)($filters['where'] ?? '');
			$params = (array)($filters['params'] ?? []);
			$exportFilterInput = (array)($filters['input'] ?? []);
			$dbPdf = new \Core\Database;
			$joinSql = "
				FROM reversals AS m
				LEFT JOIN users AS u ON m.created_by = u.id
				LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id
				LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id
				LEFT JOIN users AS u_agent_involved ON m.agent_involved = u_agent_involved.id
			";

			$exportRows = $dbPdf->query(
				"SELECT
					m.*,
					u.first_name AS user_first_name,
					u.last_name AS user_last_name,
					u_assigned.first_name AS assigned_first_name,
					u_assigned.last_name AS assigned_last_name,
					u_agent_involved.first_name AS agent_involved_first_name,
					u_agent_involved.last_name AS agent_involved_last_name
				{$joinSql}
				{$where}
				ORDER BY m.date_created DESC, m.id DESC",
				$params
			) ?: [];

			$exportSummary = [
				'total_count' => count($exportRows),
				'total_amount' => 0.0,
			];
			foreach ($exportRows as $exportRow) {
				$exportSummary['total_amount'] += (float)($exportRow->amount ?? 0);
			}

			require plugin_path('views/admin/export_pdf.php');
		} catch (\Throwable $e) {
			echo '<div class="alert alert-danger text-center">Unable to generate reversals PDF: ' . esc($e->getMessage()) . '</div>';
		}
		die;
	}

	if(URL(1) == $vars['plugin_route'] && URL(2) == 'lookup_transaction')
	{
		$response = ['success' => false, 'message' => 'Unable to look up the transaction.'];
		try {
			if (!user_can('add_reversal') && !user_can('edit_reversal')) {
				$response = ['success' => false, 'message' => 'Access denied'];
			} else {
				$type = (string)($_GET['transaction_type'] ?? '');
				$id = (string)($_GET['transaction_id'] ?? '');
				$response = reversals_lookup_source_transaction($type, $id);
				if (!is_array($response)) {
					$response = ['success' => false, 'message' => 'Invalid transaction lookup response.'];
				}
			}
		} catch (\Throwable $e) {
			error_log('REVERSALS TRANSACTION LOOKUP FAILED: ' . $e->getMessage());
			$response = ['success' => false, 'message' => 'Transaction lookup failed.'];
		}
		reversals_json_response($response, ($response['message'] ?? '') === 'Access denied' ? 403 : (!empty($response['success']) ? 200 : 500));
	}

	if(URL(1) == $vars['plugin_route'] && $req->posted())
	{
		$ses = new \Core\Session;
		$reversal = new \Reversals\Reversal;
		$reversal_roles_map = new \Reversals\User_roles_map;

		$id = URL(3) ?? null;
		if($id)
			$row = $reversal->first(['id'=>$id]);

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

	$reversal = new \Reversals\Reversal;
	$user = new \Reversals\User;	
	$users = $user->where(['deleted'=>0]);

	if(URL(1) == $vars['plugin_route']){

		$id = URL(3) ?? null;
	    $session = get_session();
	    $user_id = $session->id;    

		if($id){
			$reversal::$query_id = 'get-reversal';
			
			$row = reversals_hydrate_row($reversal->first(['id'=>$id]));
		}
		$assignedReviewer = !empty($row) ? reversals_fetch_user_summary((int)($row->assigned ?? 0)) : null;

		if(URL(2) == 'add'){
			$transactionTypeOptions = reversals_transaction_type_options();
			$reversalTypeOptions = reversals_reversal_type_options();
			$reversalAssignableUsers = reversals_assignable_reviewers();
			require plugin_path('views/admin/add.php');
		}else
		if(URL(2) == 'edit'){			
			$resolutionStatusOptions = reversals_resolution_status_options();
			$reviewDecisionOptions = reversals_review_decision_options();
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
				        u_assigned.first_name AS assigned_first_name,
				        u_assigned.last_name AS assigned_last_name,
				        u_assigned.image AS assigned_image,
				        u_agent_involved.first_name AS agent_involved_first_name, 
				        u_agent_involved.last_name AS agent_involved_last_name, 
				        u_agent_involved.image AS agent_involved_image 
				    FROM reversals AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
				    LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id
				    LEFT JOIN users AS u_agent_involved ON m.agent_involved = u_agent_involved.id 
			    WHERE m.id = :id 
			    LIMIT 1";

				$row = $reversal->query($query, ['id'=>$id])[0] ?? null;
				$row = reversals_hydrate_row($row);
			require plugin_path('views/admin/view.php');
		}else
		{
			$reversal = new \Reversals\Reversal;
			$reversal_roles_map = new \Reversals\User_roles_map;
			$limit = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
			if ($limit < 1) $limit = 10;
			if ($limit > 100) $limit = 100;
			$pager = new \Core\Pager($limit);
			$offset = $pager->offset;

			$reversal->limit = $limit;
			$reversal->offset = $offset;
			$reversal::$query_id = 'get-reversal';
			
			$metrics = [
				'total_count' => 0,
				'total_amount' => 0,
				'status_counts' => [],
				'status_amounts' => [],
			];

			$metricsParams = [];
			$metricsWhere = [];
			$metricsJoin = " LEFT JOIN users AS u ON m.created_by = u.id LEFT JOIN users AS u_agent_involved ON m.agent_involved = u_agent_involved.id ";
			$statusFilter = !empty($_GET['status']) ? strtolower(trim((string)$_GET['status'])) : '';
			$networkFilter = !empty($_GET['network']) ? trim((string)$_GET['network']) : '';
			if (strtolower($networkFilter) === 'all') {
				$networkFilter = '';
			}
			$dateFrom = !empty($_GET['date_from']) ? trim((string)$_GET['date_from']) : '';
			$dateTo = !empty($_GET['date_to']) ? trim((string)$_GET['date_to']) : '';

			if (!user_can('approve_reversal')) {
				$metricsWhere[] = "(m.created_by = :user_id OR m.agent_involved = :user_id)";
				$metricsParams['user_id'] = $user_id;
			}

			if (!empty($_GET['find'])) {
				$find = '%' . trim($_GET['find']) . '%';
				$metricsWhere[] = "(m.network LIKE :find OR m.id LIKE :find OR m.transaction_status LIKE :find OR u.first_name LIKE :find OR u.last_name LIKE :find OR u_agent_involved.first_name LIKE :find OR u_agent_involved.last_name LIKE :find OR m.date_created LIKE :find)";
				$metricsParams['find'] = $find;
			}
			if (!empty($statusFilter) && $statusFilter !== 'all') {
				$metricsWhere[] = "LOWER(COALESCE(m.transaction_status,'')) = :status_filter";
				$metricsParams['status_filter'] = $statusFilter;
			}
			if (!empty($networkFilter)) {
				$metricsWhere[] = "m.network LIKE :network_filter";
				$metricsParams['network_filter'] = '%' . $networkFilter . '%';
			}
			if (!empty($dateFrom)) {
				$metricsWhere[] = "DATE(m.date_created) >= :date_from";
				$metricsParams['date_from'] = $dateFrom;
			}
			if (!empty($dateTo)) {
				$metricsWhere[] = "DATE(m.date_created) <= :date_to";
				$metricsParams['date_to'] = $dateTo;
			}

			$metricsWhereSql = '';
			if (!empty($metricsWhere)) {
				$metricsWhereSql = ' WHERE ' . implode(' AND ', $metricsWhere);
			}

			$metricsQuery = "
				SELECT
					LOWER(COALESCE(m.transaction_status, '')) AS status_key,
					COUNT(m.id) AS cnt,
					COALESCE(SUM(m.amount), 0) AS total_amount
				FROM reversals AS m
				$metricsJoin
				$metricsWhereSql
				GROUP BY LOWER(COALESCE(m.transaction_status, ''))
			";

			try {
				$reversal::$query_id = 'reversal-metrics';
				$metricsRows = $reversal->query($metricsQuery, $metricsParams) ?: [];
				$reversal::$query_id = 'get-reversal';
				foreach ($metricsRows as $mrow) {
					$key = (string)($mrow->status_key ?? '');
					$cnt = (int)($mrow->cnt ?? 0);
					$amt = (float)($mrow->total_amount ?? 0);
					$metrics['status_counts'][$key] = $cnt;
					$metrics['status_amounts'][$key] = $amt;
					$metrics['total_count'] += $cnt;
					$metrics['total_amount'] += $amt;
				}
			} catch (\Throwable $e) {
				$reversal::$query_id = 'get-reversal';
				$metrics = $metrics;
			}
			$statusCounts = $metrics['status_counts'] ?? [];
			$metricSummary = reversals_summarize_status_counts($statusCounts);

			if (!empty($_GET['find'])) {
			    $find = '%' . trim($_GET['find']) . '%';
				$params = ['find' => $find];
				$permissionWhere = '';
				if (!user_can('approve_reversal')) {
					$permissionWhere = ' AND (m.created_by = :user_id OR m.agent_involved = :user_id) ';
					$params['user_id'] = $user_id;
				}
				$extraWhere = '';
				if (!empty($statusFilter) && $statusFilter !== 'all') {
					$extraWhere .= ' AND LOWER(COALESCE(m.transaction_status,\'\')) = :status_filter ';
					$params['status_filter'] = $statusFilter;
				}
				if (!empty($networkFilter)) {
					$extraWhere .= ' AND m.network LIKE :network_filter ';
					$params['network_filter'] = '%' . $networkFilter . '%';
				}
				if (!empty($dateFrom)) {
					$extraWhere .= ' AND DATE(m.date_created) >= :date_from ';
					$params['date_from'] = $dateFrom;
				}
				if (!empty($dateTo)) {
					$extraWhere .= ' AND DATE(m.date_created) <= :date_to ';
					$params['date_to'] = $dateTo;
				}
			    $query = "
			        SELECT
			            m.*, 
			            u.first_name AS user_first_name, 
			            u.last_name AS user_last_name, 
			        		u.image AS user_image,
			        		u_updated.first_name AS updated_by_first_name, 
			        		u_updated.last_name AS updated_by_last_name, 
			        		u_updated.image AS updated_by_image,
			        		u_assigned.first_name AS assigned_first_name,
			        		u_assigned.last_name AS assigned_last_name,
			        		u_assigned.image AS assigned_image,
			        		u_agent_involved.first_name AS agent_involved_first_name, 
			        		u_agent_involved.last_name AS agent_involved_last_name, 
			        		u_agent_involved.image AS agent_involved_image 
			        FROM reversals AS m 
			        LEFT JOIN users AS u ON m.created_by = u.id 
			        LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			    	    LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id
			    	    LEFT JOIN users AS u_agent_involved ON m.agent_involved = u_agent_involved.id 
			        WHERE (
			        		m.network LIKE :find 
			            OR m.id LIKE :find 
			            OR m.transaction_status LIKE :find
			            OR m.service_type LIKE :find
			            OR m.sender_details LIKE :find
			            OR m.receiver_details LIKE :find
			            OR u.first_name LIKE :find 
			            OR u.last_name LIKE :find 
			            OR u_assigned.first_name LIKE :find
			            OR u_assigned.last_name LIKE :find
			            OR u_agent_involved.first_name LIKE :find 
			            OR u_agent_involved.last_name LIKE :find 
			            OR m.date_created LIKE :find)
					$permissionWhere
					$extraWhere
			        LIMIT $limit OFFSET $offset";

				$rows = $reversal->query($query, $params);
			  }
			else{
				$params = [];
				$permissionWhere = '';
				if (!user_can('approve_reversal')) {
					$permissionWhere = ' WHERE (m.created_by = :user_id OR m.agent_involved = :user_id) ';
					$params['user_id'] = $user_id;
				}
				$extraWhere = '';
				$extraPrefix = empty($permissionWhere) ? ' WHERE ' : ' AND ';
				if (!empty($statusFilter) && $statusFilter !== 'all') {
					$extraWhere .= $extraPrefix . ' LOWER(COALESCE(m.transaction_status,\'\')) = :status_filter ';
					$extraPrefix = ' AND ';
					$params['status_filter'] = $statusFilter;
				}
				if (!empty($networkFilter)) {
					$extraWhere .= $extraPrefix . ' m.network LIKE :network_filter ';
					$extraPrefix = ' AND ';
					$params['network_filter'] = '%' . $networkFilter . '%';
				}
				if (!empty($dateFrom)) {
					$extraWhere .= $extraPrefix . ' DATE(m.date_created) >= :date_from ';
					$extraPrefix = ' AND ';
					$params['date_from'] = $dateFrom;
				}
				if (!empty($dateTo)) {
					$extraWhere .= $extraPrefix . ' DATE(m.date_created) <= :date_to ';
					$params['date_to'] = $dateTo;
				}
				$query = "
				    SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name, 
				        u_updated.last_name AS updated_by_last_name, 
				        u_updated.image AS updated_by_image,
				        u_assigned.first_name AS assigned_first_name,
				        u_assigned.last_name AS assigned_last_name,
				        u_assigned.image AS assigned_image,
				        u_agent_involved.first_name AS agent_involved_first_name, 
				        u_agent_involved.last_name AS agent_involved_last_name, 
				        u_agent_involved.image AS agent_involved_image 
				    FROM reversals AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
				    LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id
				    LEFT JOIN users AS u_agent_involved ON m.agent_involved = u_agent_involved.id 
					$permissionWhere
					$extraWhere
				    ORDER BY m.id DESC LIMIT $limit OFFSET $offset
				";

				$rows = $reversal->query($query, $params);
				//dd($rows);
			}

			if (!empty($rows) && is_array($rows)) {
				foreach ($rows as $key => $listRow) {
					$rows[$key] = reversals_hydrate_row($listRow);
				}
			}

			$statusOptions = reversals_status_filter_options();
			$providerOptions = [];
			$providerQuery = "SELECT DISTINCT TRIM(COALESCE(network,'')) AS network_name FROM reversals";
			$providerParams = [];
			if (!user_can('approve_reversal')) {
				$providerQuery .= " WHERE (created_by = :user_id OR agent_involved = :user_id)";
				$providerParams['user_id'] = $user_id;
			}
			$providerQuery .= " ORDER BY network_name ASC";
			$providerRows = $reversal->query($providerQuery, $providerParams) ?: [];
			foreach ($providerRows as $providerRow) {
				$providerName = trim((string)($providerRow->network_name ?? ''));
				if ($providerName === '') {
					continue;
				}
				$providerOptions[$providerName] = $providerName;
			}

			//session data
			$session = get_session();
			//dd($session);
			if(!empty($session->id)){
				$username = $session->first_name . ' '. $session->last_name;
				$user_id = $session->id;
			}
			
			require plugin_path('views/admin/list.php');
		}

	}
});

/** for manipulating data after a query operation **/
add_filter('after_query',function($data){

	
	if(empty($data['result']))
		return $data;

	if($data['query_id'] == 'get-reversal')
	{
		$role_map = new \Reversals\User_roles_map;
		foreach ($data['result'] as $key => $row) {
			if (!is_object($row) || empty($row->id)) {
				continue;
			}
			
			$query = "select * from reversal_roles where disabled = 0 && id in (select role_id from reversal_roles_map where disabled = 0 && reversal_id = :reversal_id)";
			$roles = $role_map->query($query,['reversal_id'=>$row->id]);
			if($roles)
				$data['result'][$key]->roles = array_column($roles, 'role');
			
			/** get reversal's roles **/
			$reversal_roles_map = new \Reversals\User_roles_map;
				
			$role_ids = $reversal_roles_map->where(['reversal_id'=>$row->id,'disabled'=>0]);
			if($role_ids)
				$data['result'][$key]->role_ids = array_column($role_ids, 'role_id');

		}
	}

	return $data;
});


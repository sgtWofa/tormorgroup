<?php

/**
 * Plugin name: Airtimebundle
 * Description: A way for admin to manage airtimebundle
 * 
 * 
 **/

set_value([

	'admin_route'	=>'admin',
	'plugin_route'	=>'airtimebundle',
	'tables'		=>[
		'airtimebundle_table' 		=> 'airtimebundles',
	],

	'optional_tables'		=>[
	],
	

]);

if(!function_exists('airtimebundle_json_response')){
	function airtimebundle_json_response(array $payload, int $status = 200): void
	{
		$GLOBALS['airtimebundle_json_response_sent'] = true;
		while(ob_get_level() > 0){
			ob_end_clean();
		}
		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		exit;
	}
}

$airtimebundleJsonRoute = URL(1) === 'airtimebundle'
	&& (in_array((string)(URL(2) ?? ''), ['data', 'ajax'], true)
		|| ((string)(URL(2) ?? '') === '' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'));
if($airtimebundleJsonRoute){
	ini_set('display_errors', '0');
	ini_set('display_startup_errors', '0');
	$GLOBALS['airtimebundle_json_response_sent'] = false;
	register_shutdown_function(static function(): void {
		if(!empty($GLOBALS['airtimebundle_json_response_sent'])){
			return;
		}
		while(ob_get_level() > 0){
			ob_end_clean();
		}
		http_response_code(500);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode([
			'success' => false,
			'message' => 'AirtimeBundle request failed. Please try again.',
		], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
	});
	set_exception_handler(static function(\Throwable $exception): void {
		airtimebundle_json_response([
			'success' => false,
			'message' => 'AirtimeBundle request failed. Please try again.',
		], 500);
	});
}


/** check if all tables exist **/
$db = new \Core\Database;
$tables = get_value()['tables'];

if(!$db->table_exists($tables)){
	dd("Missing database tables in ".plugin_id() ." plugin: ". implode(",", $db->missing_tables));
	die;
}

require_once plugin_path('helpers/denos_audit.php');
require_once plugin_path('helpers/transactions-helper.php');

if(!function_exists('airtimebundle_normalize_multi_value')){
	function airtimebundle_normalize_multi_value($value): array
	{
		if(is_array($value)){
			return array_values(array_filter(array_map('trim', $value), fn($val) => $val !== ''));
		}
		$value = trim((string)$value);
		return $value === '' ? [] : [$value];
	}
}

if(!function_exists('airtimebundle_apply_multi_filter')){
	function airtimebundle_apply_multi_filter(string $column, $values, array &$clauses, array &$params): void
	{
		$normalized = airtimebundle_normalize_multi_value($values);
		if(empty($normalized)){
			return;
		}
		$placeholders = [];
		foreach($normalized as $index => $value){
			$paramKey = $column . '_' . $index;
			$placeholders[] = ':' . $paramKey;
			$params[$paramKey] = $value;
		}
		$clauses[] = 'm.' . $column . ' IN (' . implode(',', $placeholders) . ')';
	}
}

if(!function_exists('airtimebundle_build_filters')){
	function airtimebundle_build_filters(array $filters, bool $canViewAll, int $currentUserId, array &$params): string
	{
		$clauses = ['m.deleted = 0'];
		if(!$canViewAll && $currentUserId > 0){
			$clauses[] = '(m.created_by = :current_user_id OR m.assigned = :current_user_id)';
			$params['current_user_id'] = $currentUserId;
		}
		if(!empty($filters['find'])){
			$params['find'] = '%'.$filters['find'].'%';
			$clauses[] = "(m.id LIKE :find OR m.phone LIKE :find OR m.network LIKE :find OR m.transaction_type LIKE :find OR m.transaction_status LIKE :find)";
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
			if(is_array($filters['created_by'])){
				airtimebundle_apply_multi_filter('created_by', $filters['created_by'], $clauses, $params);
			}else{
				$params['created_by'] = $filters['created_by'];
				$clauses[] = 'm.created_by = :created_by';
			}
		}
		if(!empty($filters['transaction_type'])){
			if(is_array($filters['transaction_type'])){
				airtimebundle_apply_multi_filter('transaction_type', $filters['transaction_type'], $clauses, $params);
			}else{
				$params['transaction_type'] = $filters['transaction_type'];
				$clauses[] = 'm.transaction_type = :transaction_type';
			}
		}
		if(!empty($filters['transaction_status'])){
			if(is_array($filters['transaction_status'])){
				airtimebundle_apply_multi_filter('transaction_status', $filters['transaction_status'], $clauses, $params);
			}else{
				$params['transaction_status'] = $filters['transaction_status'];
				$clauses[] = 'm.transaction_status = :transaction_status';
			}
		}
		if(!empty($filters['network'])){
			if(is_array($filters['network'])){
				airtimebundle_apply_multi_filter('network', $filters['network'], $clauses, $params);
			}else{
				$params['network'] = $filters['network'];
				$clauses[] = 'm.network = :network';
			}
		}
		if(!empty($filters['phone'])){
			if(is_array($filters['phone'])){
				airtimebundle_apply_multi_filter('phone', $filters['phone'], $clauses, $params);
			}else{
				$params['phone'] = $filters['phone'];
				$clauses[] = 'm.phone = :phone';
			}
		}
		return 'WHERE ' . implode(' AND ', $clauses);
	}
}

if(!function_exists('airtimebundle_fetch_dashboard_payload')){
	function airtimebundle_fetch_dashboard_payload(array $filters, int $limit, int $page, bool $canViewAll, int $currentUserId, bool $includeMeta = true): array
	{
		$db = new \Core\Database;
		$params = [];
		$where = airtimebundle_build_filters($filters, $canViewAll, $currentUserId, $params);
		$offset = max(0, ($page - 1) * $limit);

		$totalsRow = $db->query(
			"SELECT COUNT(*) AS total_rows,
				COALESCE(SUM(m.amount),0) AS total_amount,
				COALESCE(SUM(m.commission),0) AS total_commission,
				COALESCE(SUM(m.balance_after),0) AS total_balance_after,
				MAX(m.date_created) AS latest_activity
			FROM airtimebundles AS m $where",
			$params
		)[0] ?? null;
		$totalRows = (int)($totalsRow->total_rows ?? 0);
		$totalAmount = (float)($totalsRow->total_amount ?? 0);
		$totalCommission = (float)($totalsRow->total_commission ?? 0);
		$totalBalanceAfter = (float)($totalsRow->total_balance_after ?? 0);
		$latestActivity = $totalsRow->latest_activity ?? null;

		$rowsQuery = "SELECT m.*, u.first_name, u.last_name, u.image AS user_image
			FROM airtimebundles AS m
			LEFT JOIN users AS u ON m.created_by = u.id
			$where
			ORDER BY m.id DESC
			LIMIT $limit OFFSET $offset";
		$rowsRaw = $db->query($rowsQuery, $params);
		$rows = is_array($rowsRaw) ? $rowsRaw : [];
		$normalizedRows = [];
		$resolveEffectiveStatus = static function($row) use ($db): string {
			$status = airtimebundle_normalize_status($row->transaction_status ?? '', 'Draft');
			if (strcasecmp($status, 'Sent') !== 0) {
				return $status;
			}
			if (trim((string)($row->rejection_reason ?? '')) !== '') {
				return 'Rejected';
			}
			$transId = (int)($row->id ?? 0);
			if ($transId <= 0) {
				return $status;
			}
			try {
				$statuses = [];
				if ($db->table_exists(['drawer_denos'])) {
					$denoRows = $db->query(
						"SELECT DISTINCT trans_status FROM drawer_denos WHERE trans_id = :trans_id AND trans_status IS NOT NULL AND trans_status <> ''",
						['trans_id' => $transId]
					) ?: [];
					foreach ($denoRows as $denoRow) {
						$statuses[] = strtolower(trim((string)($denoRow->trans_status ?? '')));
					}
				}
				if ($db->table_exists(['drawer_balances'])) {
					$balanceRows = $db->query(
						"SELECT DISTINCT trans_status FROM drawer_balances WHERE trans_id = :trans_id AND trans_status IS NOT NULL AND trans_status <> ''",
						['trans_id' => $transId]
					) ?: [];
					foreach ($balanceRows as $balanceRow) {
						$statuses[] = strtolower(trim((string)($balanceRow->trans_status ?? '')));
					}
				}
				if (array_intersect($statuses, ['rejected', 'reversed'])) {
					return 'Rejected';
				}
			} catch (\Throwable $e) {
				return $status;
			}
			return $status;
		};
		foreach($rows as $row){
			$effectiveStatus = $resolveEffectiveStatus($row);
			$normalizedRows[] = [
				'id' => (int)$row->id,
				'reference' => 'AB' . str_pad((string)$row->id, 5, '0', STR_PAD_LEFT),
				'date_created' => $row->date_created,
				'network' => $row->network,
				'category' => $row->category ?? '',
				'transaction_type' => $row->transaction_type,
				'transaction_status' => $effectiveStatus,
				'rejection_reason' => trim((string)($row->rejection_reason ?? '')),
				'amount' => (float)($row->amount ?? 0),
				'commission' => (float)($row->commission ?? 0),
				'balance_after' => (float)($row->balance_after ?? 0),
				'phone' => $row->phone,
				'agent' => [
					'id' => (int)($row->created_by ?? 0),
					'name' => trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')),
					'image' => get_image($row->user_image ?? null),
				],
			];
		}

		$analysis = [
			'avg_ticket' => $totalRows > 0 ? ($totalAmount / $totalRows) : 0,
			'commission_rate' => $totalAmount > 0 ? ($totalCommission / max(1, $totalAmount)) : 0,
			'top_agents' => [],
			'top_clients' => [],
			'network_breakdown' => [],
			'type_breakdown' => [],
			'status_breakdown' => [],
			'trend' => [],
			'status_summary' => [
				'approved' => ['amount' => 0, 'count' => 0],
				'pending' => ['amount' => 0, 'count' => 0],
				'draft' => ['amount' => 0, 'count' => 0],
				'rejected' => ['amount' => 0, 'count' => 0],
				'sent' => ['amount' => 0, 'count' => 0],
			],
		];

		$filterOptions = [
			'networks' => [],
			'transaction_types' => [],
			'transaction_statuses' => [],
			'agents' => [],
			'phones' => [],
		];

		if ($includeMeta) {
			$topAgentsRaw = $db->query(
				"SELECT m.created_by, u.first_name, u.last_name, SUM(m.amount) AS total_amount, COUNT(*) AS tx_count
				FROM airtimebundles AS m
				LEFT JOIN users AS u ON m.created_by = u.id
				$where
				GROUP BY m.created_by, u.first_name, u.last_name
				ORDER BY total_amount DESC
				LIMIT 5",
				$params
			);
			$topAgents = is_array($topAgentsRaw) ? $topAgentsRaw : [];
			foreach($topAgents as $agent){
				$analysis['top_agents'][] = [
					'id' => (int)($agent->created_by ?? 0),
					'name' => trim(($agent->first_name ?? '') . ' ' . ($agent->last_name ?? '')),
					'total' => (float)($agent->total_amount ?? 0),
					'count' => (int)($agent->tx_count ?? 0),
				];
			}

			$topClientsRaw = $db->query(
				"SELECT m.phone, SUM(m.amount) AS total_amount, COUNT(*) AS tx_count
				FROM airtimebundles AS m
				$where AND m.phone IS NOT NULL AND m.phone <> ''
				GROUP BY m.phone
				ORDER BY total_amount DESC
				LIMIT 5",
				$params
			);
			$topClients = is_array($topClientsRaw) ? $topClientsRaw : [];
			foreach($topClients as $client){
				$analysis['top_clients'][] = [
					'phone' => $client->phone ?? 'Unknown',
					'total' => (float)($client->total_amount ?? 0),
					'count' => (int)($client->tx_count ?? 0),
				];
			}

			$networkRowsRaw = $db->query(
				"SELECT m.network, SUM(m.amount) AS total_amount, COUNT(*) AS tx_count
				FROM airtimebundles AS m
				$where
				GROUP BY m.network
				ORDER BY total_amount DESC",
				$params
			);
			$networkRows = is_array($networkRowsRaw) ? $networkRowsRaw : [];
			foreach($networkRows as $net){
				$analysis['network_breakdown'][] = [
					'label' => $net->network ?: 'Unspecified',
					'amount' => (float)($net->total_amount ?? 0),
					'count' => (int)($net->tx_count ?? 0),
					'percent' => $totalAmount > 0 ? ((float)($net->total_amount ?? 0) / $totalAmount) : 0,
				];
			}

			$typeRowsRaw = $db->query(
				"SELECT m.transaction_type, SUM(m.amount) AS total_amount, COUNT(*) AS tx_count
				FROM airtimebundles AS m
				$where
				GROUP BY m.transaction_type
				ORDER BY total_amount DESC",
				$params
			);
			$typeRows = is_array($typeRowsRaw) ? $typeRowsRaw : [];
			foreach($typeRows as $type){
				$analysis['type_breakdown'][] = [
					'label' => $type->transaction_type ?: 'Unspecified',
					'amount' => (float)($type->total_amount ?? 0),
					'count' => (int)($type->tx_count ?? 0),
					'percent' => $totalAmount > 0 ? ((float)($type->total_amount ?? 0) / $totalAmount) : 0,
				];
			}

			$statusRowsRaw = $db->query(
				"SELECT m.transaction_status, SUM(m.amount) AS total_amount, COUNT(*) AS tx_count
				FROM airtimebundles AS m
				$where
				GROUP BY m.transaction_status",
				$params
			);
			$statusRows = is_array($statusRowsRaw) ? $statusRowsRaw : [];
			foreach($statusRows as $status){
				$label = $status->transaction_status ?: 'Unknown';
				$entry = [
					'label' => $label,
					'amount' => (float)($status->total_amount ?? 0),
					'count' => (int)($status->tx_count ?? 0),
				];
				$analysis['status_breakdown'][] = $entry;
				$slug = strtolower($label);
				if(isset($analysis['status_summary'][$slug])){
					$analysis['status_summary'][$slug] = [
						'amount' => (float)($status->total_amount ?? 0),
						'count' => (int)($status->tx_count ?? 0),
					];
				}
			}

			$trendRowsRaw = $db->query(
				"SELECT DATE(m.date_created) AS trend_day, SUM(m.amount) AS total_amount, COUNT(*) AS tx_count
				FROM airtimebundles AS m
				$where
				GROUP BY DATE(m.date_created)
				ORDER BY trend_day DESC
				LIMIT 7",
				$params
			);
			$trendRows = is_array($trendRowsRaw) ? $trendRowsRaw : [];
			$trendRows = array_reverse($trendRows);
			foreach($trendRows as $trend){
				$analysis['trend'][] = [
					'label' => $trend->trend_day,
					'amount' => (float)($trend->total_amount ?? 0),
					'count' => (int)($trend->tx_count ?? 0),
				];
			}

			$networkOptionsRaw = $db->query(
				"SELECT DISTINCT m.network AS value FROM airtimebundles AS m $where ORDER BY value ASC",
				$params
			);
			$networkOptions = is_array($networkOptionsRaw) ? $networkOptionsRaw : [];
			foreach($networkOptions as $option){
				$value = trim($option->value ?? '');
				if($value !== ''){
					$filterOptions['networks'][] = $value;
				}
			}

			$typeOptionsRaw = $db->query(
				"SELECT DISTINCT m.transaction_type AS value FROM airtimebundles AS m $where ORDER BY value ASC",
				$params
			);
			$typeOptions = is_array($typeOptionsRaw) ? $typeOptionsRaw : [];
			foreach($typeOptions as $option){
				$value = trim($option->value ?? '');
				if($value !== ''){
					$filterOptions['transaction_types'][] = $value;
				}
			}

			$statusOptionsRaw = $db->query(
				"SELECT DISTINCT m.transaction_status AS value FROM airtimebundles AS m $where ORDER BY value ASC",
				$params
			);
			$statusOptions = is_array($statusOptionsRaw) ? $statusOptionsRaw : [];
			foreach($statusOptions as $option){
				$value = trim($option->value ?? '');
				if($value !== ''){
					$filterOptions['transaction_statuses'][] = $value;
				}
			}

			$agentOptionsRaw = $db->query(
				"SELECT DISTINCT m.created_by AS user_id, u.first_name, u.last_name
				FROM airtimebundles AS m
				LEFT JOIN users AS u ON m.created_by = u.id
				$where
				ORDER BY u.first_name ASC, u.last_name ASC",
				$params
			);
			$agentOptions = is_array($agentOptionsRaw) ? $agentOptionsRaw : [];
			foreach($agentOptions as $option){
				$userId = (int)($option->user_id ?? 0);
				if($userId > 0){
					$filterOptions['agents'][] = [
						'id' => $userId,
						'name' => trim(($option->first_name ?? '') . ' ' . ($option->last_name ?? '')) ?: 'Unknown User',
					];
				}
			}

			$phoneOptionsRaw = $db->query(
				"SELECT DISTINCT m.phone AS phone FROM airtimebundles AS m $where AND m.phone IS NOT NULL AND m.phone <> '' ORDER BY m.phone ASC LIMIT 50",
				$params
			);
			$phoneOptions = is_array($phoneOptionsRaw) ? $phoneOptionsRaw : [];
			foreach($phoneOptions as $option){
				$value = trim($option->phone ?? '');
				if($value !== ''){
					$filterOptions['phones'][] = $value;
				}
			}
		}

		$rankings = [
			'agents' => [],
			'clients' => [],
		];
		if ($includeMeta) {
			foreach($analysis['top_agents'] as $index => $agent){
				$rankings['agents'][] = [
					'rank' => $index + 1,
					'id' => $agent['id'],
					'name' => $agent['name'],
					'total' => $agent['total'],
					'count' => $agent['count'],
				];
			}
			foreach($analysis['top_clients'] as $index => $client){
				$rankings['clients'][] = [
					'rank' => $index + 1,
					'phone' => $client['phone'],
					'total' => $client['total'],
					'count' => $client['count'],
				];
			}
		}

		$approvedCount = (int)($analysis['status_summary']['approved']['count'] ?? 0);
		$pendingCount = (int)($analysis['status_summary']['pending']['count'] ?? 0);
		$rejectedCount = (int)($analysis['status_summary']['rejected']['count'] ?? 0);
		$statistics = [
			'avg_ticket' => $analysis['avg_ticket'],
			'commission_rate' => $analysis['commission_rate'],
			'approval_rate' => $totalRows > 0 ? ($approvedCount / $totalRows) : 0,
			'pending_rate' => $totalRows > 0 ? ($pendingCount / $totalRows) : 0,
			'rejection_rate' => $totalRows > 0 ? ($rejectedCount / $totalRows) : 0,
			'latest_activity' => $latestActivity,
		];

		return [
			'success' => true,
			'meta_included' => $includeMeta,
			'rows' => $normalizedRows,
			'totals' => [
				'total_amount' => $totalAmount,
				'total_commission' => $totalCommission,
				'total_balance_after' => $totalBalanceAfter,
				'count' => $totalRows,
			],
			'latest_activity' => $latestActivity,
			'analysis' => $analysis,
			'filter_options' => $filterOptions,
			'rankings' => $rankings,
			'statistics' => $statistics,
			'pagination' => [
				'page' => $page,
				'limit' => $limit,
				'total_rows' => $totalRows,
				'total_pages' => $limit > 0 ? max(1, (int)ceil($totalRows / $limit)) : 1,
			],
		];
	}
}


/** set momoremit permissions for this plugin **/
add_filter('permissions',function($permissions){

	/*$permissions[] = 'view_momoremit';
	$permissions[] = 'view_momoremit_details';
	$permissions[] = 'add_momoremit';
	$permissions[] = 'edit_momoremit';
	$permissions[] = 'delete_momoremit';*/

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
		$momoremit = new \Airtimebundles\Momoremit;
		$airtimebundle = new \Airtimebundles\Airtimebundle;

		$id = URL(3) ?? null;
		if($id)
			$row = $airtimebundle->first(['id'=>$id]);


		if(URL(2) == ''){
			require plugin_path('controllers/list-controller.php');
		}else
		if(URL(2) == 'add' || URL(2) == 'airtime' || URL(2) == 'bundle'){
			require plugin_path('controllers/add-controller.php');
		}else
		if(URL(2) == 'edit' || URL(2) == 'airtime_edit' || URL(2) == 'bundle_edit'){
			
			require plugin_path('controllers/edit-controller.php');
		}else
		if(URL(2) == 'delete'){

			require plugin_path('controllers/delete-controller.php');
		}

	}elseif(URL(1) == $vars['plugin_route'] && URL(2) == 'data'){
		$session = get_session();
		if(!user_can('view_momoremit')){
			airtimebundle_json_response([
				'success' => false,
				'message' => 'Access denied',
			], 403);
		}

		$userId = (int)($session->id ?? 0);
		$role = strtolower($session->roles[0] ?? '');
		$restrictedRoles = ['momo agent','solo agent'];
		$canViewAll = !in_array($role, $restrictedRoles, true);
		$page = max(1, (int)($_GET['page'] ?? 1));
		$limit = min(100, max(10, (int)($_GET['limit'] ?? 10)));
		$includeMeta = (string)($_GET['include_meta'] ?? '1') !== '0';
		$filters = [
			'find' => trim($_GET['find'] ?? ''),
			'date_from' => trim($_GET['date_from'] ?? ''),
			'date_to' => trim($_GET['date_to'] ?? ''),
			'created_by' => airtimebundle_normalize_multi_value($_GET['created_by'] ?? []),
			'transaction_type' => airtimebundle_normalize_multi_value($_GET['transaction_type'] ?? []),
			'transaction_status' => airtimebundle_normalize_multi_value($_GET['transaction_status'] ?? []),
			'network' => airtimebundle_normalize_multi_value($_GET['network'] ?? []),
			'phone' => airtimebundle_normalize_multi_value($_GET['phone'] ?? []),
		];

		ini_set('display_errors', '0');
		ini_set('display_startup_errors', '0');
		try {
			$payload = airtimebundle_fetch_dashboard_payload($filters, $limit, $page, $canViewAll, $userId, $includeMeta);
			airtimebundle_json_response($payload);
		}catch(\Throwable $th){
			error_log('Airtimebundle /data exception: ' . $th->getMessage());
			airtimebundle_json_response([
				'success' => false,
				'message' => 'Unable to load airtime data.',
				'error' => DEBUG ? $th->getMessage() : null,
			], 500);
		}
		exit;
	}elseif(URL(1) == $vars['plugin_route'] && URL(2) == 'denos_audit_export'){
        if(!user_can('view_momoremit')){
            redirect($admin_route . '/' . $plugin_route);
        }

        $report = airtimebundle_generate_denos_audit_report(true);
        $exportType = URL(3) ?? 'batches';
        $timestamp = gmdate('Ymd_His');

        if($exportType == 'summary'){
            header('Content-Type: text/markdown; charset=utf-8');
            header("Content-Disposition: attachment; filename=airtimebundle-denos-audit-summary-{$timestamp}.md");
            echo $report['summary_markdown'] ?? '';
        }elseif($exportType == 'transactions'){
            header('Content-Type: text/csv; charset=utf-8');
            header("Content-Disposition: attachment; filename=airtimebundle-denos-audit-transactions-{$timestamp}.csv");
            echo airtimebundle_csv_from_rows($report['transaction_rows'] ?? []);
        }else{
            header('Content-Type: text/csv; charset=utf-8');
            header("Content-Disposition: attachment; filename=airtimebundle-denos-audit-batches-{$timestamp}.csv");
            echo airtimebundle_csv_from_rows($report['batch_rows'] ?? []);
        }
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

	$momoremit = new \Airtimebundles\Momoremit;
	$airtimebundle = new \Airtimebundles\Airtimebundle;
	$wallet = new \Airtimebundles\Wallet;
	$user = new \Airtimebundles\User;
	$transaction = new \Airtimebundles\Transaction;
	$deno = new \Airtimebundles\Deno;
	$db = new \Core\Database;

	$ensureAirtimebundleColumns = function() {
		$marker = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . 'airtimebundle_schema_20260421.done';
		if (file_exists($marker)) {
			return;
		}

		$ok = true;
		try {
			$dbSchema = new \Core\Database;
			$neededCols = [
				'cash_received_amount',
				'change_required',
				'change_denominations',
				'transaction_image',
			];
			$missing = [];
			foreach ($neededCols as $col) {
				$hasCol = $dbSchema->query(
					"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'airtimebundles' AND COLUMN_NAME = :col LIMIT 1",
					['db' => DB_NAME, 'col' => $col]
				);
				if (empty($hasCol)) {
					$missing[] = $col;
				}
			}
			if (!empty($missing)) {
				$typeMap = [
					'cash_received_amount' => 'FLOAT(10,2) DEFAULT 0.00',
					'change_required' => 'FLOAT(10,2) DEFAULT 0.00',
					'change_denominations' => 'LONGTEXT NULL',
					'transaction_image' => 'VARCHAR(255) NULL',
				];
				foreach ($missing as $col) {
					$colType = $typeMap[$col] ?? 'VARCHAR(255) NULL';
					try {
						$dbSchema->query("ALTER TABLE airtimebundles ADD COLUMN {$col} {$colType}");
					} catch (\Throwable $e) {
						$ok = false;
						// ignore schema errors in production
					}
				}
			}
			foreach ([
				"CREATE INDEX idx_airtimebundles_deleted_date ON airtimebundles (deleted, date_created)",
				"CREATE INDEX idx_airtimebundles_status ON airtimebundles (transaction_status)",
				"CREATE INDEX idx_airtimebundles_phone ON airtimebundles (phone)",
			] as $indexSql) {
				try {
					$dbSchema->query($indexSql);
				} catch (\Throwable $e) {
					// index may already exist; ignore
				}
			}
		} catch (\Throwable $e) {
			$ok = false;
			// ignore schema errors
		}
		if ($ok) {
			$dir = dirname($marker);
			if (!is_dir($dir)) {
				@mkdir($dir, 0777, true);
			}
			@file_put_contents($marker, 'done ' . date('c'));
		}
	};

	if(URL(1) == $vars['plugin_route']){
		$ensureAirtimebundleColumns();

		$pageAction = (string)(URL(2) ?? '');
		$id = URL(3) ?? null;
		$needsAssignableUsers = in_array($pageAction, ['add', 'airtime', 'bundle', 'edit', 'airtime_edit', 'bundle_edit'], true);
		$needsWalletCatalog = $needsAssignableUsers;
		$needsOwnWalletCatalog = $needsAssignableUsers;
		$needsTransactionRow = $id && in_array($pageAction, ['view', 'export_pdf', 'edit', 'airtime_edit', 'bundle_edit', 'delete'], true);

		$session = get_session();
		$username = '';
		$user_id = 0;
		$role = '';
		if(!empty($session->id)){
			$username = trim(($session->first_name ?? '') . ' ' . ($session->last_name ?? ''));
			$user_id = (int)$session->id;
			$role = (string)($session->roles[0] ?? '');
		}

		$users = [];
		if($needsAssignableUsers){
			$user::$query_id = 'get-users';
			try {
				$today = date('Y-m-d');
				$sqlUsers = "SELECT DISTINCT u.*
					FROM users u
					INNER JOIN attendance a
						ON a.user_id = u.id
						AND a.date = :today
						AND (a.deleted = 0 OR a.deleted IS NULL)
						AND UPPER(COALESCE(a.remarks,'')) IN ('PRESENT','LATE')
					INNER JOIN cashdrawers c
						ON c.user_id = u.id
						AND DATE(c.date_created) = :today
					WHERE (u.deleted = 0 OR u.deleted IS NULL)
					ORDER BY u.first_name ASC, u.last_name ASC";
				$users = $user->query($sqlUsers, ['today' => $today]) ?: [];
			} catch (\Throwable $e) {
				$users = [];
			}
		}
		
		$wallets = [];
		if($needsWalletCatalog){
			$wallets = $wallet->where(['deleted'=>0, 'transaction_status'=>'Approved']);
		}

		if($needsTransactionRow){
			$user::$query_id = 'get-users';
			
			$row = $airtimebundle->first(['id'=>$id]);
		}
		
		$mywallets = [];
		if($needsOwnWalletCatalog && $user_id > 0){
			$mywallets = $wallet->where(['deleted'=>0, 'transaction_status'=>'Approved', 'assigned'=>$user_id]);
		}
		$fetchTransactionDenos = function($transactionRow) use ($deno) {
			$transId = (int)($transactionRow->id ?? 0);
			if ($transId <= 0) {
				return [];
			}

			$rows = $deno->query("SELECT * FROM denos WHERE trans_id = :trans_id ORDER BY denomination DESC, id ASC", ['trans_id' => $transId]);
			if (is_iterable($rows) && !empty($rows)) {
				return $rows;
			}

			try {
				$db = new \Core\Database;
				if ($db->table_exists(['drawer_denos'])) {
					$rows = $db->query("SELECT denomination, `count`, volume, date_created, created_by, trans_id, trans_status FROM drawer_denos WHERE trans_id = :trans_id ORDER BY denomination DESC, id ASC", ['trans_id' => $transId]);
					if (is_iterable($rows) && !empty($rows)) {
						return $rows;
					}
				}
			} catch (\Throwable $e) {
			}

			return [];
		};

		if(URL(2) == 'add'){

			require plugin_path('views/admin/add.php');
		}else
		if(URL(2) == 'airtime'){
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
			
			$cashdrawerData = $getCashdrawerData();

			require plugin_path('views/admin/entries/airtime.php');
		}else
		if(URL(2) == 'bundle'){
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
			
			$cashdrawerData = $getCashdrawerData();

			require plugin_path('views/admin/entries/bundle.php');
		}else
		if(URL(2) == 'airtime_edit'){

			require plugin_path('views/admin/entries/airtime_edit.php');
		}else
		if(URL(2) == 'bundle_edit'){

			require plugin_path('views/admin/entries/bundle_edit.php');
		}else
		if(URL(2) == 'edit'){
			
			require plugin_path('views/admin/edit.php');
		}else
		if(URL(2) == 'delete'){

			require plugin_path('views/admin/delete.php');
		}else
        if(URL(2) == 'denos_audit'){

            $denosAudit = airtimebundle_generate_denos_audit_report(true);
            require plugin_path('views/admin/denos_audit.php');
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
			        u_assigned.first_name AS assigned_first_name, 
			        u_assigned.last_name AS assigned_last_name, 
			        u_assigned.image AS assigned_image,
		            in_wallet.name AS in_wallet_name, 
		            in_wallet.network AS in_wallet_network,
		            out_wallet.name AS out_wallet_name, 
		            out_wallet.network AS out_wallet_network
			    FROM airtimebundles AS m 
			    LEFT JOIN users AS u ON m.created_by = u.id 
			    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id			        
			    LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id			        
			    LEFT JOIN mywallets AS in_wallet ON m.wallet_in_id = in_wallet.id 			        
			    LEFT JOIN mywallets AS out_wallet ON m.wallet_out_id = out_wallet.id 
			    WHERE m.id = :id 
			    LIMIT 1";

				$row = $airtimebundle->query($query, ['id'=>$id])[0];
				$denos = $fetchTransactionDenos($row);
				$totalCount = 0;
				$totalVolume = 0;
				if (is_iterable($denos)) {
					foreach ($denos as $denoRow) {
						$totalCount += abs((int)($denoRow->count ?? 0));
						$totalVolume += abs((float)($denoRow->volume ?? 0));
					}
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
			        u_updated.image AS updated_by_image,
			        u_assigned.first_name AS assigned_first_name, 
			        u_assigned.last_name AS assigned_last_name, 
			        u_assigned.image AS assigned_image,
		            in_wallet.name AS in_wallet_name, 
		            in_wallet.network AS in_wallet_network,
		            out_wallet.name AS out_wallet_name, 
		            out_wallet.network AS out_wallet_network
			    FROM airtimebundles AS m 
			    LEFT JOIN users AS u ON m.created_by = u.id 
			    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id			        
			    LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id			        
			    LEFT JOIN mywallets AS in_wallet ON m.wallet_in_id = in_wallet.id 			        
			    LEFT JOIN mywallets AS out_wallet ON m.wallet_out_id = out_wallet.id 
			    WHERE m.id = :id 
			    LIMIT 1";

				$row = $airtimebundle->query($query, ['id'=>$id])[0];
				$denos = $fetchTransactionDenos($row);
				$totalCount = 0;
				$totalVolume = 0;
				if (is_iterable($denos)) {
					foreach ($denos as $denoRow) {
						$totalCount += abs((int)($denoRow->count ?? 0));
						$totalVolume += abs((float)($denoRow->volume ?? 0));
					}
				}

			require plugin_path('views/admin/view.php');
		}else
		if(URL(2) == 'exportList_pdf'){
			$user::$query_id = 'get-users';
					
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
			    FROM airtimebundles AS m 
			    LEFT JOIN users AS u ON m.created_by = u.id 
			    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id
		        LEFT JOIN mywallets AS in_wallet ON m.wallet_in_id = in_wallet.id 
		        LEFT JOIN mywallets AS out_wallet ON m.wallet_out_id = out_wallet.id  
			    ORDER BY m.id DESC
			";

			$query2 = $query. "WHERE created_by = $user_id";

		if (strtolower($role) !== 'momo agent' && strtolower($role) !== 'solo agent') {

				$rows = $airtimebundle->query($query);

				$query = "SELECT SUM(amount) AS total_amount, SUM(balance_after) AS total_balance_after FROM airtimebundles WHERE deleted = 0";
				$total = $airtimebundle->query($query)[0] ?? [];
				$total_amount = $total->total_amount ?? 0;
				$total_balance_after = $total->total_balance_after ?? 0;
			}else{

				$rows = $airtimebundle->query($query2);

				$query = "SELECT SUM(amount) AS total_amount, SUM(balance_after) AS total_balance_after FROM airtimebundles WHERE deleted = 0 AND created_by = $user_id";
				$total = $airtimebundle->query($query)[0] ?? [];
				$total_amount = $total->total_amount ?? 0;
				$total_balance_after = $total->total_balance_after ?? 0;
			}

			require plugin_path('views/admin/exportList_pdf.php');
		}else{
			$rows = [];
			$total_amount = 0;
			$total_balance_after = 0;
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
		$role_map = new \Airtimebundles\User_roles_map;
		foreach ($data['result'] as $key => $row) {
			
			$query = "select * from user_roles where disabled = 0 && id in (select role_id from user_roles_map where disabled = 0 && user_id = :user_id)";
			$roles = $role_map->query($query,['user_id'=>$row->id]);
			if($roles)
				$data['result'][$key]->roles = array_column($roles, 'role');
			
			/** get momoremit's roles **/
			$user_roles_map = new \Airtimebundles\User_roles_map;
				
			$role_ids = $user_roles_map->where(['user_id'=>$row->id,'disabled'=>0]);
			if($role_ids)
				$data['result'][$key]->role_ids = array_column($role_ids, 'role_id');

		}
	}

	return $data;
});



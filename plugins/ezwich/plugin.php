<?php

/**
 * Plugin name: Ezwich
 * Description: A way for admin to manage ezwich
 * 
 * 
 **/

set_value([

	'admin_route'	=>'admin',
	'plugin_route'	=>'ezwich',
	'tables'		=>[
		'ezwich_table' 				=> 'ezwichs',
	],

	'optional_tables'		=>[
	],
	

]);

if(!function_exists('ezwich_json_response')){
	function ezwich_json_response(array $payload, int $status = 200): void
	{
		$GLOBALS['ezwich_json_response_sent'] = true;
		while(ob_get_level() > 0){
			ob_end_clean();
		}
		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		exit;
	}
}

$ezwichJsonRoute = URL(1) === 'ezwich' && (
	(string)(URL(2) ?? '') === 'data'
	|| ((string)(URL(2) ?? '') === 'cancel' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')
	|| ((string)(URL(2) ?? '') === '' && $_SERVER['REQUEST_METHOD'] === 'POST')
);
if($ezwichJsonRoute){
	ini_set('display_errors', '0');
	ini_set('display_startup_errors', '0');
	ob_start();
	$GLOBALS['ezwich_json_response_sent'] = false;
	register_shutdown_function(static function(): void {
		if(!empty($GLOBALS['ezwich_json_response_sent'])){
			return;
		}
		while(ob_get_level() > 0){
			ob_end_clean();
		}
		http_response_code(500);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(['success' => false, 'message' => 'Ezwich request failed. Please try again.'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
	});
	set_exception_handler(static function(\Throwable $exception): void {
		ezwich_json_response(['success' => false, 'message' => 'Ezwich request failed. Please try again.'], 500);
	});
}


/** check if all tables exist **/
$db = new \Core\Database;
$tables = get_value()['tables'];

if(!$db->table_exists($tables)){
	if($ezwichJsonRoute){
		ezwich_json_response(['success' => false, 'message' => 'Ezwich records are not available.'], 503);
	}
	dd("Missing database tables in ".plugin_id() ." plugin: ". implode(",", $db->missing_tables));
	die;
}

if(!function_exists('ezwich_cleanup_charge_commissions')){
	function ezwich_cleanup_charge_commissions(): void
	{
		static $cleaned = false;
		if ($cleaned) {
			return;
		}
		$cleaned = true;
		try {
			$db = new \Core\Database;
			if (!$db->table_exists(['commissions'])) {
				return;
			}
			$db->query(
				"DELETE FROM commissions WHERE commission_type = :commission_type",
				['commission_type' => 'EZWICH CHARGE']
			);
		} catch (\Throwable $e) {
			error_log('EZWICH CHARGE COMMISSION CLEANUP FAILED: ' . $e->getMessage());
		}
	}
}

ezwich_cleanup_charge_commissions();

require_once plugin_path('helpers/transactions-helper.php');

if(!function_exists('ezwich_build_filters')){
	function ezwich_build_filters(array $filters, bool $canViewAll, int $currentUserId, array &$params): string
	{
		$clauses = ["m.deleted = 0"];
		if(!$canViewAll && $currentUserId > 0){
			$clauses[] = 'm.created_by = :current_user_id';
			$params['current_user_id'] = $currentUserId;
		}
		if(!empty($filters['find'])){
			$params['find'] = '%' . $filters['find'] . '%';
			$clauses[] = "(m.transaction_id LIKE :find OR m.phone LIKE :find OR m.network LIKE :find OR m.transaction_type LIKE :find)";
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

if(!function_exists('ezwich_fetch_dashboard_payload')){
	function ezwich_fetch_dashboard_payload(array $filters, int $limit, int $page, bool $canViewAll, int $currentUserId): array
	{
		$db = new \Core\Database;
		$params = [];
		$where = ezwich_build_filters($filters, $canViewAll, $currentUserId, $params);
		$offset = max(0, ($page - 1) * $limit);

		$totalsRow = $db->query(
			"SELECT COUNT(*) AS total_rows, COALESCE(SUM(m.amount),0) AS total_amount, COALESCE(SUM(m.charge),0) AS total_charge
			FROM ezwichs AS m $where",
			$params
		)[0] ?? null;
		$totalRows = (int)($totalsRow->total_rows ?? 0);
		$totalAmount = (float)($totalsRow->total_amount ?? 0);
		$totalCharge = (float)($totalsRow->total_charge ?? 0);

		$latestActivityRow = $db->query(
			"SELECT m.date_created FROM ezwichs AS m $where ORDER BY m.date_created DESC LIMIT 1",
			$params
		)[0] ?? null;
		$latestActivity = $latestActivityRow->date_created ?? null;

		$rowsQuery = "SELECT m.*, u.first_name, u.last_name, u.image AS user_image
			FROM ezwichs AS m
			LEFT JOIN users AS u ON m.created_by = u.id
			$where
			ORDER BY m.id DESC
			LIMIT $limit OFFSET $offset";
		$rows = $db->query($rowsQuery, $params) ?? [];
		$rawRowsForDebug = DEBUG ? array_map(function($row){ return (array)$row; }, $rows) : null;
		$normalizedRows = [];
		foreach($rows as $row){
			$transactionStatus = trim((string)($row->transaction_status ?? ''));
			$normalizedRows[] = [
				'id' => (int)$row->id,
				'reference' => 'EZ' . str_pad((string)$row->id, 5, '0', STR_PAD_LEFT),
				'date_created' => $row->date_created,
				'network' => $row->network,
				'transaction_type' => $row->transaction_type,
				'transaction_status' => $transactionStatus,
				'rejection_reason' => trim((string)($row->rejection_reason ?? '')),
				'amount' => (float)$row->amount,
				'charge' => (float)($row->charge ?? 0),
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
			'charge_rate' => $totalAmount > 0 ? ($totalCharge / max(1, $totalAmount)) : 0,
			'top_agents' => [],
			'top_clients' => [],
			'network_breakdown' => [],
			'status_breakdown' => [],
			'status_overview' => [],
			'trend' => [],
			'status_summary' => [
				'approved' => ['amount' => 0, 'count' => 0],
				'pending' => ['amount' => 0, 'count' => 0],
				'draft' => ['amount' => 0, 'count' => 0],
				'rejected' => ['amount' => 0, 'count' => 0],
				'declined' => ['amount' => 0, 'count' => 0],
			],
		];

		$topAgents = $db->query(
			"SELECT m.created_by, u.first_name, u.last_name, SUM(m.amount) AS total_amount, COUNT(*) AS tx_count
			FROM ezwichs AS m
			LEFT JOIN users AS u ON m.created_by = u.id
			$where
			GROUP BY m.created_by, u.first_name, u.last_name
			ORDER BY total_amount DESC
			LIMIT 5",
			$params
		) ?? [];
		foreach($topAgents as $agent){
			$analysis['top_agents'][] = [
				'id' => (int)($agent->created_by ?? 0),
				'name' => trim(($agent->first_name ?? '') . ' ' . ($agent->last_name ?? '')),
				'total' => (float)$agent->total_amount,
				'count' => (int)$agent->tx_count,
			];
		}

		$topClients = $db->query(
			"SELECT m.phone, SUM(m.amount) AS total_amount, COUNT(*) AS tx_count
			FROM ezwichs AS m
			$where AND m.phone IS NOT NULL AND m.phone <> ''
			GROUP BY m.phone
			ORDER BY total_amount DESC
			LIMIT 5",
			$params
		) ?? [];
		foreach($topClients as $client){
			$analysis['top_clients'][] = [
				'phone' => $client->phone ?? 'Unknown',
				'total' => (float)$client->total_amount,
				'count' => (int)$client->tx_count,
			];
		}

		$networkRows = $db->query(
			"SELECT m.network, SUM(m.amount) AS total_amount, COUNT(*) AS tx_count
			FROM ezwichs AS m
			$where
			GROUP BY m.network
			ORDER BY total_amount DESC",
			$params
		) ?? [];
		foreach($networkRows as $net){
			$analysis['network_breakdown'][] = [
				'label' => $net->network ?: 'Unspecified',
				'amount' => (float)$net->total_amount,
				'count' => (int)$net->tx_count,
				'percent' => $totalAmount > 0 ? ((float)$net->total_amount / $totalAmount) : 0,
			];
		}

		$statusRows = $db->query(
			"SELECT m.transaction_status, SUM(m.amount) AS total_amount, COUNT(*) AS tx_count
			FROM ezwichs AS m
			$where
			GROUP BY m.transaction_status",
			$params
		) ?? [];
		foreach($statusRows as $status){
			$label = $status->transaction_status ?: 'Unknown';
			$entry = [
				'label' => $label,
				'amount' => (float)$status->total_amount,
				'count' => (int)$status->tx_count,
			];
			$analysis['status_breakdown'][] = $entry;
			$analysis['status_overview'][] = $entry;
			$slug = strtolower($label);
			if(isset($analysis['status_summary'][$slug])){
				$analysis['status_summary'][$slug] = [
					'amount' => (float)$status->total_amount,
					'count' => (int)$status->tx_count,
				];
			}
		}

		$trendRows = $db->query(
			"SELECT DATE(m.date_created) AS trend_day, SUM(m.amount) AS total_amount, COUNT(*) AS tx_count
			FROM ezwichs AS m
			$where
			GROUP BY DATE(m.date_created)
			ORDER BY trend_day DESC
			LIMIT 7",
			$params
		) ?? [];
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


/** set ezwich permissions for this plugin **/
add_filter('permissions',function($permissions){

	$permissions[] = 'view_ezwich';
	$permissions[] = 'view_ezwich_details';
	$permissions[] = 'add_ezwich';
	$permissions[] = 'edit_ezwich';
	$permissions[] = 'delete_ezwich';

	return $permissions;
});


/** add to amin links **/
add_filter('basic-admin_before_admin_links', function ($links) {

    return $links;
});



/** run this after a form submit **/
add_action('controller',function(){

	$req = new \Core\Request;
	$vars = get_value();
	
	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];

	if(URL(1) == $vars['plugin_route'] && $req->posted() && URL(2) !== 'cancel')
	{
		$ses = new \Core\Session;
		$ezwich = new \Ezwichs\Ezwich;
		$transaction = new \Ezwichs\Transaction;
		$wallet = new \Ezwichs\Wallet;
		$setting = new \Ezwichs\Setting;		$id = URL(3) ?? null;
		if($id)
			$row = $ezwich->first(['id'=>$id]);


		if(URL(2) == 'add' || URL(2) == 'withdrawal' || URL(2) == 'transfer'){
			require plugin_path('controllers/add-controller.php');
		}else
		if(URL(2) == 'edit'){
			
			require plugin_path('controllers/edit-controller.php');
		}else
		if(URL(2) == 'delete'){

			require plugin_path('controllers/delete-controller.php');
		}else
		if(URL(2) == ''){

			require plugin_path('controllers/list-controller.php');
		}

	}elseif(URL(1) == $vars['plugin_route'] && URL(2) == 'cancel' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'){
		require_once __DIR__ . '/../agencybanking/helpers/cashdrawer-sync.php';
		require_once __DIR__ . '/../reversals/helpers/direct-cancel.php';
		direct_cancel_handle(direct_cancel_configuration('ezwich'));
	}elseif(URL(1) == $vars['plugin_route'] && URL(2) == 'data'){
		$session = get_session();
		if(!user_can('view_ezwich')){
			ezwich_json_response([
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
			$payload = ezwich_fetch_dashboard_payload($filters, $limit, $page, $canViewAll, $userId);
			ezwich_json_response($payload);
		}catch(\Throwable $th){
			error_log('Ezwich /data exception: ' . $th->getMessage());
			ezwich_json_response([
				'success' => false,
				'message' => 'Unable to load ezwich data.',
				'error' => DEBUG ? $th->getMessage() : null,
			], 500);
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

	$ezwich = new \Ezwichs\Ezwich;
	$transaction = new \Ezwichs\Transaction;
	$wallet = new \Ezwichs\Wallet;
	$setting = new \Ezwichs\Setting;
	$user = new \Ezwichs\User;
	$deno = new \Ezwichs\Deno;

	if(URL(1) == $vars['plugin_route']){


		$user::$query_id = 'get-users';
		$users = $user->where(['deleted'=>0]);

		$query = "SELECT * FROM settings ORDER BY id desc LIMIT 1";
		$settings = $setting->query($query)[0] ?? [];	


		$id = URL(3) ?? null;
		if($id){
			
			$row = $ezwich->first(['id'=>$id]);
		}

        //session data
        $user::$query_id = 'get-users';
        $session = get_session();
        if(!empty($session->id)){
            $username = $session->first_name . ' '. $session->last_name;
            $user_id = $session->id;
            $role = $session->roles[0];
        }
        //dd($role);
		$wallets = $wallet->where(['deleted'=>0, 'transaction_status'=>'Approved']);
		$mywallets = $wallet->where(['deleted'=>0, 'transaction_status'=>'Approved', 'assigned'=>$user_id]);
		//dd($mywallets);

		if(URL(2) == 'add'){

			$ezwich_role = new \Ezwichs\User_role;
			require plugin_path('views/admin/add.php');
		}else
		if(URL(2) == 'transfer'){

			$ezwich_role = new \Ezwichs\User_role;
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
			
			require plugin_path('views/admin/entries/transfer.php');
		}else
		if(URL(2) == 'withdrawal'){

			$ezwich_role = new \Ezwichs\User_role;
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
			
			require plugin_path('views/admin/entries/withdrawal.php');
		}else
		if(URL(2) == 'edit'){
			
			$ezwich_role = new \Ezwichs\User_role;
			// Load existing denominations for this transaction so the edit form can prefill them
			$denoRows = [];
			if (!empty($id)) {
				try {
					$denoRows = $deno->where(['trans_id' => $id]) ?? [];
				} catch (\Throwable $e) {
					$denoRows = [];
				}
			}
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
			        u_assigned.first_name AS assigned_first_name, 
			        u_assigned.last_name AS assigned_last_name, 
			        u_assigned.image AS assigned_image,
			        u_updated.first_name AS updated_by_first_name, 
			        u_updated.last_name AS updated_by_last_name, 
			        u_updated.image AS updated_by_image 
			    FROM ezwichs AS m 
			    LEFT JOIN users AS u ON m.created_by = u.id 
			    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			    LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id 
			    WHERE m.id = :id 
			    LIMIT 1";

				$row = $ezwich->query($query, ['id'=>$id])[0];

				$query = "SELECT * FROM denos WHERE trans_id = :trans_id AND count <> 0 GROUP BY denomination ORDER BY denomination DESC";
                $denos = $deno->query($query, ['trans_id'=>$row->id]);

               // dd(['denominations'=>$denos]);

                //$denos = $deno->where(['trans_id'=>$id]);

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
			        u_assigned.first_name AS assigned_first_name, 
			        u_assigned.last_name AS assigned_last_name, 
			        u_assigned.image AS assigned_image,
			        u_updated.first_name AS updated_by_first_name, 
			        u_updated.last_name AS updated_by_last_name, 
			        u_updated.image AS updated_by_image 
			    FROM ezwichs AS m 
			    LEFT JOIN users AS u ON m.created_by = u.id 
			    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			    LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id 
			    WHERE m.id = :id 
			    LIMIT 1";

				$row = $ezwich->query($query, ['id'=>$id])[0];

                $denos = $deno->where(['trans_id'=>$id]);

                $query = "SELECT SUM(count) AS total_count, SUM(volume) AS total_volume FROM denos WHERE trans_id =:trans_id";
                $denosTotal = $deno->query($query, ['trans_id'=>$id]);
                if (!empty($denosTotal)) {
                    $denosTotal = $denosTotal[0];
                    $totalCount = $denosTotal->total_count ?? 0;
                    $totalVolume = $denosTotal->total_volume ?? 0;
                }

			require plugin_path('views/admin/export_pdf.php');
		}else
		if(URL(2) == 'exportList_pdf'){
			$ezwich = new \Ezwichs\Ezwich;
			$ezwich_roles_map = new \Ezwichs\User_roles_map;
			$ezwich->offset = $offset;
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
				    FROM ezwichs AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id
				    ORDER BY m.id DESC 
				";

				$query2 = $query . " WHERE m.created_by = $user_id";

                  if (strtolower($role) !== 'momo agent' && strtolower($role) !== 'solo agent') {

						$rows = $ezwich->query($query);
						
						$query = "SELECT SUM(amount) AS total_amount, SUM(charge) AS total_charge, COUNT(id) AS total_qty FROM ezwichs WHERE deleted = 0";
						$total = $ezwich->query($query)[0];
						$total_amount = $total->total_amount ?? 0;
						$total_charge = $total->total_charge ?? 0;
						$total_qty = $total->total_qty ?? 0;
                  }else{

						$rows = $ezwich->query($query2);
						
						$query = "SELECT SUM(amount) AS total_amount, SUM(charge) AS total_charge, COUNT(id) AS total_qty FROM ezwichs WHERE created_by = $user_id AND deleted = 0";
						$total = $ezwich->query($query)[0];
						$total_amount = $total->total_amount ?? 0;
						$total_charge = $total->total_charge ?? 0;
						$total_qty = $total->total_qty ?? 0;

                  }
			
			require plugin_path('views/admin/exportList_pdf.php');
		}else
		{
			$ezwich = new \Ezwichs\Ezwich;
			$ezwich_roles_map = new \Ezwichs\User_roles_map;
			$limit = 10;

			$pager = new \Core\Pager($limit);
			$offset = $pager->offset;

			$ezwich->limit = $limit;
			$ezwich->offset = $offset;
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
				    FROM ezwichs AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id
				    ORDER BY m.id DESC  LIMIT $limit OFFSET $offset
				";

				$query2 = "
				    SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name, 
				        u_updated.last_name AS updated_by_last_name, 
				        u_updated.image AS updated_by_image 
				    FROM ezwichs AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
				    WHERE m.created_by = $user_id
				    ORDER BY m.id DESC 
				    LIMIT $limit OFFSET $offset
				";

                  if (strtolower($role) !== 'momo agent' && strtolower($role) !== 'solo agent') {

						$rows = $ezwich->query($query);

						$query = "SELECT SUM(amount) AS total_amount, SUM(charge) AS total_charge, COUNT(id) AS total_qty FROM ezwichs WHERE deleted = 0 AND transaction_status = :transaction_status";
						$total_approved = $ezwich->query($query, ['transaction_status'=>'Approved'])[0];
						$totalApproved_amount = $total_approved->total_amount ?? 0;
						$totalApproved_charge = $total_approved->total_charge ?? 0;
						$totalApproved_qty = $total_approved->total_qty ?? 0;

						$total_pending = $ezwich->query($query, ['transaction_status'=>'Pending'])[0];
						$totalPending_amount = $total_pending->total_amount ?? 0;
						$totalPending_charge = $total_pending->total_charge ?? 0;
						$totalPending_qty = $total_pending->total_qty ?? 0;

						$total_draft = $ezwich->query($query, ['transaction_status'=>'Draft'])[0];
						$totalDraft_amount = $total_draft->total_amount ?? 0;
						$totalDraft_charge = $total_draft->total_charge ?? 0;
						$totalDraft_qty = $total_draft->total_qty ?? 0;

						$total_rejected = $ezwich->query($query, ['transaction_status'=>'Rejected'])[0];
						$totalRejected_amount = $total_rejected->total_amount ?? 0;
						$totalRejected_charge = $total_rejected->total_charge ?? 0;
						$totalRejected_qty = $total_rejected->total_qty ?? 0;
						
						$query = "SELECT SUM(amount) AS total_amount, SUM(charge) AS total_charge, COUNT(id) AS total_qty FROM ezwichs WHERE deleted = 0";
						$total = $ezwich->query($query)[0];
						$total_amount = $total->total_amount ?? 0;
						$total_charge = $total->total_charge ?? 0;
						$total_qty = $total->total_qty ?? 0;
                  }else{

						$rows = $ezwich->query($query2);

						$query = "SELECT SUM(amount) AS total_amount, SUM(charge) AS total_charge, COUNT(id) AS total_qty FROM ezwichs WHERE created_by = $user_id AND deleted = 0 AND transaction_status = :transaction_status";
						$total_approved = $ezwich->query($query, ['transaction_status'=>'Approved'])[0];
						$totalApproved_amount = $total_approved->total_amount ?? 0;
						$totalApproved_charge = $total_approved->total_charge ?? 0;
						$totalApproved_qty = $total_approved->total_qty ?? 0;

						$total_pending = $ezwich->query($query, ['transaction_status'=>'Pending'])[0];
						$totalPending_amount = $total_pending->total_amount ?? 0;
						$totalPending_charge = $total_pending->total_charge ?? 0;
						$totalPending_qty = $total_pending->total_qty ?? 0;

						$total_draft = $ezwich->query($query, ['transaction_status'=>'Draft'])[0];
						$totalDraft_amount = $total_draft->total_amount ?? 0;
						$totalDraft_charge = $total_draft->total_charge ?? 0;
						$totalDraft_qty = $total_draft->total_qty ?? 0;

						$total_rejected = $ezwich->query($query, ['transaction_status'=>'Rejected'])[0];
						$totalRejected_amount = $total_rejected->total_amount ?? 0;
						$totalRejected_charge = $total_rejected->total_charge ?? 0;
						$totalRejected_qty = $total_rejected->total_qty ?? 0;
						
						$query = "SELECT SUM(amount) AS total_amount, SUM(charge) AS total_charge, COUNT(id) AS total_qty FROM ezwichs WHERE created_by = $user_id AND deleted = 0";
						$total = $ezwich->query($query)[0];
						$total_amount = $total->total_amount ?? 0;
						$total_charge = $total->total_charge ?? 0;
						$total_qty = $total->total_qty ?? 0;

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
		$role_map = new \Ezwichs\User_roles_map;
		foreach ($data['result'] as $key => $row) {
			
			$query = "select * from user_roles where disabled = 0 && id in (select role_id from user_roles_map where disabled = 0 && user_id = :user_id)";
			$roles = $role_map->query($query,['user_id'=>$row->id]);
			if($roles)
				$data['result'][$key]->roles = array_column($roles, 'role');
			
			/** get ezwich's roles **/
			$user_roles_map = new \Ezwichs\User_roles_map;
				
			$role_ids = $user_roles_map->where(['user_id'=>$row->id,'disabled'=>0]);
			if($role_ids)
				$data['result'][$key]->role_ids = array_column($role_ids, 'role_id');

		}
	}

	return $data;
});


<?php

/**
 * Plugin name: Momoremit
 * Description: A way for admin to manage performance
 * 
 * 
 **/

set_value([

	'admin_route'	=>'admin',
	'plugin_route'	=>'performance',
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

if(!function_exists('performance_json_response')){
	function performance_json_response($payload, int $status = 200): void
	{
		$GLOBALS['performance_json_response_sent'] = true;
		while(ob_get_level() > 0){
			ob_end_clean();
		}
		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		exit;
	}
}

$performanceJsonRoute = URL(1) === 'performance'
	&& (((string)(URL(2) ?? '') === '' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')
		|| in_array((string)(URL(2) ?? ''), ['data', 'analysis', 'ajax'], true));
if($performanceJsonRoute){
	ini_set('display_errors', '0');
	ini_set('display_startup_errors', '0');
	ini_set('html_errors', '0');
	$GLOBALS['performance_json_response_sent'] = false;
	register_shutdown_function(static function(): void {
		if(!empty($GLOBALS['performance_json_response_sent'])){
			return;
		}
		while(ob_get_level() > 0){
			ob_end_clean();
		}
		http_response_code(500);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode([
			'success' => false,
			'message' => 'Performance request failed. Please try again.',
		], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
	});
	set_exception_handler(static function(\Throwable $exception): void {
		performance_json_response([
			'success' => false,
			'message' => 'Performance request failed. Please try again.',
		], 500);
	});
}


/** check if all tables exist **/
$db = new \Core\Database;
$tables = get_value()['tables'];

if(!$db->table_exists($tables)){
	if($performanceJsonRoute){
		performance_json_response(['success' => false, 'message' => 'Performance records are not available.'], 503);
	}
	dd("Missing database tables in ".plugin_id() ." plugin: ". implode(",", $db->missing_tables));
	die;
}


/** set performance permissions for this plugin **/
add_filter('permissions',function($permissions){

	$permissions[] = 'view_performance';
	$permissions[] = 'view_performance_details';
	$permissions[] = 'add_performance';
	$permissions[] = 'edit_performance';
	$permissions[] = 'delete_performance';

	return $permissions;
});

add_action('controller',function(){
	$vars = get_value();
	if(URL(1) !== $vars['plugin_route'] || !in_array((string)(URL(2) ?? ''), ['data', 'analysis', 'ajax'], true)){
		return;
	}

	if(URL(2) === 'ajax'){
		require plugin_path('controllers/ajax-controller.php');
		return;
	}

	$_POST = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
	require plugin_path('controllers/performance-controller.php');
});

/** run this after a form submit **/
add_action('controller',function(){

	$req = new \Core\Request;
	$vars = get_value();
	
	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];

	$isJsonPagePost = URL(2) === '' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
	if(URL(1) == $vars['plugin_route'] && ($req->posted() || $isJsonPagePost))
	{
		$ses = new \Core\Session;
		$user = new \Performances\User;
		$sending = new \Performances\Sending;
		$deposit = new \Performances\Deposit;
		$withdrawal = new \Performances\Withdrawal;
		$cit = new \Performances\Cit;
		$momoremit = new \Performances\Momoremit;
		$wallet = new \Performances\Wallet;
		$transaction = new \Performances\Transaction;
		$momoremit_roles_map = new \Performances\User_roles_map;

		$users = $user->where(['disabled'=>0]);

		$id = URL(3) ?? null;
		if($id)
			$row = $user->first(['id'=>$id]);

		if(URL(2) == 'add' || URL(2) == 'cit' || URL(2) == 'deposit' || URL(2) == 'withdrawal' || URL(2) == 'sending'){
			require plugin_path('controllers/add-controller.php');
		}else
		if(URL(2) == 'edit'){			
			require plugin_path('controllers/edit-controller.php');
		}else
		if(URL(2) == 'delete'){
			require plugin_path('controllers/delete-controller.php');
		}else
		if(URL(2) == 'report'){
			require plugin_path('controllers/report-controller.php');
		}else{
			require plugin_path('controllers/analysis-controller.php');
		}
	}
});

add_action('controller',function(){

	$req = new \Core\Request;
	$vars = get_value();
	
	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];

	if(URL(1) == $vars['plugin_route'] && URL(2) == 'report')
	{
		require plugin_path('controllers/report-controller.php');
	}
});


/** displays the view file **/
add_action('basic-admin_main_content',function(){

	$ses = new \Core\Session;
	$vars = get_value();

	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];
	
	$errors = $vars['errors'] ?? [];

	$momoremit = new \Performances\Momoremit;
	$wallet = new \Performances\Wallet;
	$user = new \Performances\User;
	$transaction = new \Performances\Transaction;
	$deno = new \Performances\Deno;

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
			$row = $performance->first(['id'=>$id]);
		}
		
		$mywallets = $wallet->where(['deleted'=>0, 'transaction_status'=>'Approved', 'assigned'=>$user_id]);

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
			    FROM performances AS m 
			    LEFT JOIN users AS u ON m.created_by = u.id 
			    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id			        
			    LEFT JOIN mywallets AS in_wallet ON m.wallet_in_id = in_wallet.id 			        
			    LEFT JOIN mywallets AS out_wallet ON m.wallet_out_id = out_wallet.id 
			    WHERE m.id = :id 
			    LIMIT 1";

				$row = $performance->query($query, ['id'=>$id])[0];
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
			    FROM performances AS m 
			    LEFT JOIN users AS u ON m.created_by = u.id 
			    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id			        
			    LEFT JOIN mywallets AS in_wallet ON m.wallet_in_id = in_wallet.id 			        
			    LEFT JOIN mywallets AS out_wallet ON m.wallet_out_id = out_wallet.id 
			    WHERE m.id = :id 
			    LIMIT 1";

				$row = $performance->query($query, ['id'=>$id])[0];
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
		{
			$user::$query_id = 'get-users';
			
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
		$role_map = new \Performances\User_roles_map;
		foreach ($data['result'] as $key => $row) {
			
			$query = "select * from user_roles where disabled = 0 && id in (select role_id from user_roles_map where disabled = 0 && user_id = :user_id)";
			$roles = $role_map->query($query,['user_id'=>$row->id]);
			if($roles)
				$data['result'][$key]->roles = array_column($roles, 'role');
			
				/** get performance's roles **/
				$user_roles_map = new \Performances\User_roles_map;				
				$role_ids = $user_roles_map->where(['user_id'=>$row->id,'disabled'=>0]);
			if($role_ids)
				$data['result'][$key]->role_ids = array_column($role_ids, 'role_id');

		}
	}

	return $data;
});


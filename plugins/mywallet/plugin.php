<?php

/**
 * Plugin name: MyWallet
 * Description: A way for admin to manage account
 * 
 * 
 **/

set_value([

	'admin_route'	=>'admin',
	'plugin_route'	=>'my_wallet',
	'tables'		=>[
		'account_table' 				=> 'mywallets',
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


/** set mywallet permissions for this plugin **/
/*add_filter('permissions',function($permissions){

	$permissions[] = 'view_wallet';
	$permissions[] = 'view_transactions';
	$permissions[] = 'view_account_statements';
	$permissions[] = 'view_wallet_details';
	$permissions[] = 'add_wallet';
	$permissions[] = 'edit_wallet';
	$permissions[] = 'approve_transactions';
	$permissions[] = 'transfer_funds';
	$permissions[] = 'delete_wallet';

	return $permissions;
});*/


/** add to amin links 
add_filter('basic-admin_before_admin_links',function($links){

	if(!user_can('is_manager') || user_can('is_admin'))
	{
		$vars = get_value();		

	    $obj = (object)[];
	    $obj->title = 'My Wallet';
	    $obj->id = 'view_wallet';
	    $obj->link = ROOT . '/' . $vars['admin_route'] . '/' . $vars['plugin_route'];
	    $obj->icon = 'fas fa-wallet'; // Wallet for personal finances
	    $obj->parent = 0;
	    $links[] = $obj;

      }

	return $links;

});**/


/** run this after a form submit **/
add_action('controller',function(){

	$req = new \Core\Request;
	$vars = get_value();
	
	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];

	if(URL(1) == $vars['plugin_route'] && $req->posted())
	{
		$ses = new \Core\Session;
		$mywallet = new \MyWallets\Mywallet;
		$wallet = new \MyWallets\Wallet;
		$transaction = new \MyWallets\Transaction;
		$category = new \MyWallets\Category;
		$mywallet_roles_map = new \MyWallets\User_roles_map;

		$id = URL(3) ?? null;
		if($id)
			$row = $mywallet->first(['id'=>$id]);
			$categories = $category->where(['disabled'=>0]);


		if(URL(2) == 'add'){
			require plugin_path('controllers/add-controller.php');
		}else
		if(URL(2) == 'edit'){
			
			require plugin_path('controllers/edit-controller.php');
		}else
		if(URL(2) == 'delete'){

			require plugin_path('controllers/delete-controller.php');
		}else
		if(URL(2) == 'transfer'){

			require plugin_path('controllers/transfer-controller.php');
		}

	}
});


/** displays the view file **/
add_action('basic-admin_main_content',function(){

	$ses = new \Core\Session;
	$mywallet = new \MyWallets\Mywallet;
	$wallet = new \MyWallets\Wallet;
	$transaction = new \MyWallets\Transaction;
	$category = new \MyWallets\Category;
	$sim = new \MyWallets\Sim;
	$user = new \MyWallets\User;
	$vars = get_value();

	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];
	
	$errors = $vars['errors'] ?? [];

	$user::$query_id = 'get-users';
	$users = $user->where(['deleted'=>0]);


	if(URL(1) == $vars['plugin_route']){

		$id = URL(3) ?? null;

	    $session = get_session();
	    $user_id = $session->id;
	    //dd($session);

		if($id){
			$user::$query_id = 'get-users';
			
			$row = $mywallet->first(['id'=>$id]);
		}

		$mywallets = $mywallet->where(
			['deleted'=>0, 'transaction_status'=>'Approved'],
			['id'=>$id]
		);
		
		$categories = $category->where(['disabled'=>0]);

		if(URL(2) == 'add'){			
			$mywallet_role = new \MyWallets\User_role;
			require plugin_path('views/admin/add.php');
		}else
		if(URL(2) == 'edit'){
			
			$mywallet_role = new \MyWallets\User_role;
			require plugin_path('views/admin/edit.php');
		}else
		if(URL(2) == 'delete'){

			require plugin_path('views/admin/delete.php');
		}else
		if(URL(2) == 'transfer'){

			require plugin_path('views/admin/transfer.php');
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
			        u_assigned.image AS assigned_image
			    FROM mywallets AS m 
			    LEFT JOIN users AS u ON m.created_by = u.id 
			    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			    LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id 
			    WHERE m.id = :id
			    LIMIT 1";

				$row = $mywallet->query($query, ['id'=>$id])[0];

				$limit = 10;
				$pager = new \Core\Pager($limit);
				$offset = $pager->offset;
				$user::$query_id = 'get-users';

				$pager = new \Core\Pager($limit);
				$mywallet->limit = $limit;
				$mywallet->offset = $offset;
				$transaction->limit = $limit;
				$transaction->offset = $offset;
				$transaction::$query_id = 'get-users-transactions';

				  $query = "SELECT t.*, 
				    u.last_name,
				    u.first_name,
				    u.image
				    FROM transactions AS t 
				    LEFT JOIN users AS u ON t.created_by = u.id
				    WHERE t.wallet_id = :id
				    ORDER BY t.id DESC
				    LIMIT $limit OFFSET $offset";

				// Execute the query with parameters
				$rows = $transaction->query($query, ['id' => $id]);

				//dd($rows);

				$mywallets = $mywallet->where(
					['deleted'=>0, 'transaction_status'=>'Approved'],
					['id'=>$id]
				);

				$query2 = "SELECT sum(balance) AS total_balance, count(id) as total_num from mywallets";
				$total_query = $mywallet->query($query2)[0];

				$total_balance = $total_query->total_balance ?? 0;
				$total_num = $total_query->total_num ?? 0;
				//dd($rows);

			require plugin_path('views/admin/view.php');
		}else
		{
			$mywallet = new \MyWallets\Mywallet;
			$wallet = new \MyWallets\Wallet;
			$sim = new \MyWallets\Sim;
			$user = new \MyWallets\User;
			$mywallet_roles_map = new \MyWallets\User_roles_map;
			$limit = 10;
			$pager = new \Core\Pager($limit);
			$offset = $pager->offset;

			$mywallet->limit = $limit;
			$mywallet->offset = $offset;
			$user::$query_id = 'get-users';
			
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
				        u_updated.image AS updated_by_image,
			            u_assigned.first_name AS assigned_first_name, 
			            u_assigned.last_name AS assigned_last_name, 
			            u_assigned.image AS assigned_image, 
			            c.category AS category_name
				    FROM mywallets AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			        LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id
			        LEFT JOIN categories AS c ON m.category = c.id
			        WHERE (
			        	(m.assigned = $user_id  AND m.transaction_status = 'Approved')
			        	AND network LIKE :find 
			            OR id LIKE :find 
			            OR transaction_type LIKE :find 
			            OR u.first_name LIKE :find 
			            OR u.last_name LIKE :find 
			            OR phone LIKE :find
			            OR transaction_status LIKE :find
			            OR date_created LIKE :find)
			        LIMIT $limit OFFSET $offset";

			    $rows = $mywallet->query($query, ['find' => $find]);

				// Define the search term
					$find = '%' . trim($_GET['find'] ?? '') . '%';

					$query2 = "SELECT SUM(balance) AS total_balance FROM mywallets WHERE assigned = :assigned";

					// Run the query
					$total_query = $mywallet->query($query2, ['assigned'=>$user_id]);

					// Check if the query was successful and not empty
					if ($total_query && isset($total_query[0])) {
					    $total_balance = $total_query[0]->total_balance ?? 0;
					} else {
					    $total_balance = 0; // Default to 0 if query failed or returned no rows
					}
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
				        u_updated.image AS updated_by_image,
			            u_assigned.first_name AS assigned_first_name, 
			            u_assigned.last_name AS assigned_last_name, 
			            u_assigned.image AS assigned_image,
			            c.category AS category_name 
				    FROM mywallets AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			        LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id
			        LEFT JOIN categories AS c ON m.category = c.id
			        WHERE m.assigned = $user_id  AND m.transaction_status = 'Approved'
				    ORDER BY m.id DESC
				";

				$rows = $mywallet->query($query);
				//dd($rows);
				$query2 = "SELECT sum(balance) AS total_balance from mywallets WHERE assigned = $user_id";
				$total_query = $mywallet->query($query2);

				$total_balance = $total_query[0]->total_balance ?? 0;
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
		$role_map = new \MyWallets\User_roles_map;
		foreach ($data['result'] as $key => $row) {
			
			$query = "select * from user_roles where disabled = 0 && id in (select role_id from user_roles_map where disabled = 0 && user_id = :user_id)";
			$roles = $role_map->query($query,['user_id'=>$row->id]);
			if($roles)
				$data['result'][$key]->roles = array_column($roles, 'role');
			
			/** get mywallet's roles **/
			$mywallet_roles_map = new \MyWallets\User_roles_map;
				
			$role_ids = $mywallet_roles_map->where(['user_id'=>$row->id,'disabled'=>0]);
			if($role_ids)
				$data['result'][$key]->role_ids = array_column($role_ids, 'role_id');

		}
	}

	return $data;
});


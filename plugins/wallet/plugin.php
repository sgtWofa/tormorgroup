<?php

/**
 * Plugin name: Wallet
 * Description: A way for admin to manage account
 * 
 * 
 **/

set_value([

	'admin_route'	=>'admin',
	'plugin_route'	=>'wallet',
	'tables'		=>[
		'account_table' 				=> 'wallets',
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


/** set wallet permissions for this plugin **/
add_filter('permissions',function($permissions){

	$permissions[] = 'is_manager';
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
});


/** add to amin links **/
add_filter('basic-admin_before_admin_links',function($links){

	if(user_can('is_manager') || user_can('is_admin'))
	{
		$vars = get_value();

		$obj = (object)[];
		$obj->title = 'Manager';
		$obj->id = 'is_manager';
		$obj->link = ROOT . '/'.$vars['admin_route'].'/'.$vars['plugin_route'];
		$obj->icon = 'fa-solid fa-wallet';
		$obj->parent = 0;

        $obj->sublinks = []; // Initialize sublinks array within the main link object

        // Add "Cashin" sublink
        /*$sub = (object)[];
        $sub->title = 'Accounts';
        $sub->link = ROOT . '/' . $vars['admin_route'] . '/categories';
        $sub->icon = 'fa-solid fa-bank'; // Icon for sending money
        $sub->parent = $obj->id;
        $sub->id = 'view_categories';
        $obj->sublinks[] = $sub;*/

        // Add "Cashout" sublink
        /*$sub = (object)[];
        $sub->title = 'Wallets';
        $sub->link = ROOT . '/' . $vars['admin_route'] . '/' . $vars['plugin_route'];
        $sub->icon = 'fa-solid fa-wallet'; // Icon for cash out
        $sub->parent = $obj->id;
        $sub->id = 'view_wallet';
        $obj->sublinks[] = $sub;*/

        // Add "Cashout" sublink
        $sub = (object)[];
        $sub->title = 'Payments';
        $sub->link = ROOT . '/' . $vars['admin_route'] . '/payments';
        $sub->icon = 'fa-solid fa-piggy-bank'; // Icon for cash out
        $sub->parent = $obj->id;
        $sub->id = 'view_payments';
        $obj->sublinks[] = $sub;

        // Add "Cashout" sublink
        $sub = (object)[];
        $sub->title = 'Users';
        $sub->link = ROOT . '/' . $vars['admin_route'] . '/users';
        $sub->icon = 'fa-solid fa-users'; // Icon for cash out
        $sub->parent = $obj->id;
        $sub->id = 'users';
        $obj->sublinks[] = $sub;

        /*$sub = (object)[];
        $sub->title = 'CashPickups';
        $sub->link = ROOT . '/' . $vars['admin_route'] . '/cashpickup';
        $sub->icon = 'fa-solid fa-hand-holding-dollar';
        $sub->parent = $obj->id;
        $sub->id = 'view_cashpickup';
        $obj->sublinks[] = $sub;*/

        // Add the main "MoMo & Remitances" link with sublinks to $links array
        $links[] = $obj;

	}

	return $links;

	dd($links);
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
		$wallet = new \Wallets\Wallet;
		$transaction = new \Wallets\Transaction;
		$category = new \Wallets\Category;
		$user_roles_map = new \Wallets\User_roles_map;

		$id = URL(3) ?? null;
		if($id)
			$row = $wallet->first(['id'=>$id]);
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
		}else
		if(URL(2) == 'deposit'){

			require plugin_path('controllers/deposit-controller.php');
		}else
		if(URL(2) == 'withdraw'){

			require plugin_path('controllers/withdraw-controller.php');
		}

	}
});


/** displays the view file **/
add_action('basic-admin_main_content',function(){

	$ses = new \Core\Session;
	$wallet = new \Wallets\Wallet;
	$transaction = new \Wallets\Transaction;
	$category = new \Wallets\Category;
	$sim = new \Wallets\Sim;
	$user = new \Wallets\User;
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
	    
		if($id){
			$user::$query_id = 'get-users';
			
			$row = $wallet->first(['id'=>$id]);
		}

		$wallets = $wallet->where(
			['deleted'=>0, 'transaction_status'=>'Approved'],
			['id'=>$id]
		);
			$categories = $category->where(['disabled'=>0]);

		if(URL(2) == 'add'){			
			$wallet_role = new \Wallets\User_role;
			require plugin_path('views/admin/add.php');
		}else
		if(URL(2) == 'edit'){
			
			$wallet_role = new \Wallets\User_role;
			require plugin_path('views/admin/edit.php');
		}else
		if(URL(2) == 'delete'){

			require plugin_path('views/admin/delete.php');
		}else
		if(URL(2) == 'deposit'){

			require plugin_path('views/admin/deposit.php');
		}else
		if(URL(2) == 'withdraw'){

			require plugin_path('views/admin/withdraw.php');
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
			    FROM wallets AS m 
			    LEFT JOIN users AS u ON m.created_by = u.id 
			    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			    LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id 
			    WHERE m.id = :id 
			    LIMIT 1";

				$row = $wallet->query($query, ['id'=>$id])[0];

				$limit = 10;
				$pager = new \Core\Pager($limit);
				$offset = $pager->offset;
				$user::$query_id = 'get-users';

				$pager = new \Core\Pager($limit);
				$wallet->limit = $limit;
				$wallet->offset = $offset;
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

				$wallets = $wallet->where(
					['deleted'=>0, 'transaction_status'=>'Approved'],
					['id'=>$id]
				);

				$query2 = "SELECT sum(balance) AS total_balance, count(id) as total_num from wallets";
				$total_query = $wallet->query($query2)[0];

				$total_balance = $total_query->total_balance ?? 0;
				$total_num = $total_query->total_num ?? 0;
				//dd($rows);

			require plugin_path('views/admin/view.php');
		}else
		{
			$wallet = new \Wallets\Wallet;
			$sim = new \Wallets\Sim;
			$user = new \Wallets\User;
			$user_roles_map = new \Wallets\User_roles_map;
			$limit = 30;
			$pager = new \Core\Pager($limit);
			$offset = $pager->offset;

			$wallet->limit = $limit;
			$wallet->offset = $offset;
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
				    FROM wallets AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			        LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id
			        LEFT JOIN categories AS c ON m.category = c.id
			        WHERE (
			        	network LIKE :find 
			            OR id LIKE :find 
			            OR transaction_type LIKE :find 
			            OR u.first_name LIKE :find 
			            OR u.last_name LIKE :find 
			            OR phone LIKE :find
			            OR transaction_status LIKE :find
			            OR date_created LIKE :find)
			        LIMIT $limit OFFSET $offset";

			    $rows = $wallet->query($query, ['find' => $find]);

				// Define the search term
					$find = '%' . trim($_GET['find'] ?? '') . '%';

					$query2 = "SELECT SUM(balance) AS total_balance FROM wallets";

					// Run the query
					$total_query = $wallet->query($query2);

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
				    FROM wallets AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			        LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id
			        LEFT JOIN categories AS c ON m.category = c.id
				    ORDER BY m.id DESC
				";

				$rows = $wallet->query($query);
				//dd($rows);
				$query2 = "SELECT sum(balance) AS total_balance from wallets ";
				$total_query = $wallet->query($query2)[0];

				$total_balance = $total_query->total_balance ?? 0;
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
		$role_map = new \Wallets\User_roles_map;
		foreach ($data['result'] as $key => $row) {
			
			$query = "select * from user_roles where disabled = 0 && id in (select role_id from user_roles_map where disabled = 0 && user_id = :user_id)";
			$roles = $role_map->query($query,['user_id'=>$row->id]);
			if($roles)
				$data['result'][$key]->roles = array_column($roles, 'role');
			
			/** get wallet's roles **/
			$user_roles_map = new \Wallets\User_roles_map;
				
			$role_ids = $user_roles_map->where(['user_id'=>$row->id,'disabled'=>0]);
			if($role_ids)
				$data['result'][$key]->role_ids = array_column($role_ids, 'role_id');

		}
	}

	return $data;
});


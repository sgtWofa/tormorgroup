<?php

/**
 * Plugin name: Request
 * Description: A way for admin to manage account
 * 
 * 
 **/

set_value([

	'admin_route'	=>'admin',
	'plugin_route'	=>'requests',
	'tables'		=>[
		'account_table' 				=> 'requests',
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


/** set request permissions for this plugin **/
add_filter('permissions',function($permissions){

	$permissions[] = 'view_request';
	$permissions[] = 'view_request_details';
	$permissions[] = 'add_request';
	$permissions[] = 'edit_request';
	$permissions[] = 'delete_request';

	return $permissions;
});


/** Add to admin links **/
add_filter('basic-admin_before_admin_links', function ($links) {

    $vars = get_value();
    $reversal = new \Requests\RequestClass;
    $session = get_session();
    $user_id = $session->id;

    // Define the queries
    $queryLower = "SELECT COUNT(id) AS pending_reversal FROM requests WHERE (transaction_status = :transaction_status OR transaction_status = :transaction_status2) AND (created_by = :user_id OR assigned = :user_id) LIMIT 1";
    $rowsL = $reversal->query($queryLower, ['transaction_status' => strtolower('pending'),'transaction_status2' => strtolower('draft'), 'user_id' => $user_id]);

    // Determine total pending/draft
    $total_pending = 0;
    if (!empty($rowsL)) {
        $total_pending = $rowsL[0]->pending_reversal ?? 0;
    }

    // Format the badge

    $countBadge = $total_pending > 0 ? (string)$total_pending : '';

        /*$obj = (object)[];
        $obj->title = 'Requests';
        $obj->id = 'view_request';
        $obj->link = ROOT . '/' . $vars['admin_route'] . '/requests';
        $obj->icon = 'fas fa-inbox'; // Updated icon for "Requests"
        $obj->parent = 0;
        $obj->count = $countBadge;

        $links[] = $obj;*/
    return $links;
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
		$request = new \Requests\RequestClass;
		$transaction = new \Requests\Transaction;
		$request_roles_map = new \Requests\User_roles_map;

		$id = URL(3) ?? null;
		if($id)
			$row = $request->first(['id'=>$id]);

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
	$request = new \Requests\RequestClass;
	$transaction = new \Requests\Transaction;
	$user = new \Requests\User;
	$mywallet = new \Requests\Mywallet;
	$vars = get_value();

	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];

    $session = get_session();
    $user_id = $session->id;
	
	$errors = $vars['errors'] ?? [];

	if(URL(1) == $vars['plugin_route']){

		$id = URL(3) ?? null;

		$user::$query_id = 'get-users';
		$users = $user->where(['deleted'=>0], ['id'=>$user_id]);

		$mywallets = $mywallet->where(
			['deleted'=>0, 'transaction_status'=>'Approved', 'assigned'=>$user_id]
		);

		/*dd($users);*/

		if($id){
			
			$row = $request->first(['id'=>$id]);
		}

		$requests = $request->where(
			['deleted'=>0, 'transaction_status'=>'Approved'],
			['id'=>$id]
		);

		if(URL(2) == 'add'){			
			$request_role = new \Requests\User_role;
			require plugin_path('views/admin/add.php');
		}else
		if(URL(2) == 'edit'){			
			$request_role = new \Requests\User_role;
			
			$user::$query_id = 'get-users';
			$users = $user->where(['deleted'=>0]);
			
			$mywallets = $mywallet->where(
				['deleted'=>0, 'transaction_status'=>'Approved']
			);
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
			    FROM requests AS m 
			    LEFT JOIN users AS u ON m.created_by = u.id 
			    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			    LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id 
			    WHERE m.id = :id 
			    LIMIT 1";

			$row = $request->query($query, ['id'=>$id])[0];

			require plugin_path('views/admin/view.php');
		}else
		{
			$request = new \Requests\RequestClass;
			$user = new \Requests\User;
			$user_roles_map = new \Requests\User_roles_map;
			$limit = 30;
			$pager = new \Core\Pager($limit);
			$offset = $pager->offset;

			$request->limit = $limit;
			$request->offset = $offset;
			$request::$query_id = 'get-users';
			
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
			            u_assigned.image AS assigned_image
				    FROM requests AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			        LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id
			        LEFT JOIN categories AS c ON m.category = c.id
			        WHERE (
			        	(m.created_by = $user_id
			        	OR m.assigned = $user_id)
			        	AND network LIKE :find 
			            OR id LIKE :find 
			            OR transaction_type LIKE :find 
			            OR u.first_name LIKE :find 
			            OR u.last_name LIKE :find 
			            OR phone LIKE :find
			            OR transaction_status LIKE :find
			            OR date_created LIKE :find)
			        LIMIT $limit OFFSET $offset";

			    $rows = $request->query($query, ['find' => $find]);
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
			            u_assigned.image AS assigned_image
				    FROM requests AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			        LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id
			        WHERE m.created_by = $user_id
			        	OR m.assigned = $user_id
				    ORDER BY m.id DESC
				";

				$rows = $request->query($query);
				//dd($rows);
				$query2 = "SELECT sum(balance) AS total_balance from requests ";
				$total_query = $request->query($query2)[0] ?? [];

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
		$role_map = new \Requests\User_roles_map;
		foreach ($data['result'] as $key => $row) {
			
			$query = "select * from user_roles where disabled = 0 && id in (select role_id from user_roles_map where disabled = 0 && user_id = :user_id)";
			$roles = $role_map->query($query,['user_id'=>$row->id]);
			if($roles)
				$data['result'][$key]->roles = array_column($roles, 'role');
			
			/** get request's roles **/
			$user_roles_map = new \Requests\User_roles_map;
				
			$role_ids = $user_roles_map->where(['user_id'=>$row->id,'disabled'=>0]);
			if($role_ids)
				$data['result'][$key]->role_ids = array_column($role_ids, 'role_id');

		}
	}

	return $data;
});


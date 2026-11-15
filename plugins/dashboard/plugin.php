<?php

/**
 * Plugin name: Students Manager
 * Description: A way for admin to manage transactions
 * 
 * 
 **/

set_value([

	'admin_route'	=>'admin',
	'plugin_route'	=>'dashboard',
	'plugin_route'	=>'dashboard',
	'tables'		=>[
		'transactions_table' 		=> 'transactions',
		'users_table' 		=> 'users',
		'ezwichs_table' 		=> 'ezwichs',
		'momoremits_table' 		=> 'momoremits',
		'momoremits_table' 		=> 'momoremits',
		'wallets_table' 		=> 'wallets',
		'agencybankings_table' 		=> 'agencybankings',
	],

	'optional_tables'		=>[
		'roles_table' 		=> 'user_roles',
		'users_table' 		=> 'users',
		'permissions_table' => 'role_permissions',
		'roles_map_table' 	=> 'user_roles_map',
		'categories_table' 	=> 'categories',
		'categories_map_table' 	=> 'transactions_categories_map',
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
	$permissions[] = 'view_dashboard';

	return $permissions;
});


/** add to amin links **/
/*add_filter('basic-admin_before_admin_links',function($links){

	if(user_can('view_transactions'))
	{
		$vars = get_value();

		$obj = (object)[];
		$obj->title = 'Dashboard';
		$obj->link = ROOT . '/'.$vars['admin_route'].'/'.$vars['plugin_route'];
		$obj->icon = 'fas fa-tachometer-alt';
		$obj->parent = 0;
		$links[] = $obj;
	}

	return $links;
});*/


/** run this after a form submit **/
add_action('controller',function(){

	$req = new \Core\Request;
	$vars = get_value();
	
	$admin_route  = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];

	// Only handle POSTs for the dashboard routes themselves, not bare /admin
	if((URL(1) == $vars['plugin_route'] ||  URL(1) == $vars['plugin_route']) && $req->posted())
	{
		require plugin_path('controllers/dashboard-controller.php');
	}

	// Lightweight JSON chat API under /admin/dashboard-chat
	if(URL(1) === 'dashboard-chat'){
		$session = get_session();
		if(!$session || empty($session->id)){
			header('Content-Type: application/json', true, 401);
			echo json_encode(['error' => 'Unauthenticated']);
			exit;
		}

		$db = new \Core\Database;
		// Ensure chat_messages table exists (simple bootstrap)
		$db->query("CREATE TABLE IF NOT EXISTS chat_messages (
			id INT AUTO_INCREMENT PRIMARY KEY,
			room VARCHAR(64) NOT NULL DEFAULT 'global',
			user_id INT NOT NULL,
			message TEXT NOT NULL,
			image_path VARCHAR(255) NULL,
			image_paths TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			INDEX(room, created_at),
			INDEX(user_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		// In case the table already existed without the new columns, try to add them.
		// MySQL 8+ supports IF NOT EXISTS; older versions will ignore duplicate-column errors via try/catch.
		try{
			$db->query("ALTER TABLE chat_messages ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) NULL AFTER message");
		}catch(\Throwable $e){
			// ignore if column already exists or ALTER not supported
		}
		try{
			$db->query("ALTER TABLE chat_messages ADD COLUMN IF NOT EXISTS image_paths TEXT NULL AFTER image_path");
		}catch(\Throwable $e){
			// ignore if column already exists or ALTER not supported
		}

		$room = $_GET['room'] ?? 'global';
		$room = trim($room) ?: 'global';
		$baseRooms = ['global','transactions','commissions','attendance','activities'];
		$roomPrefixes = ['role:','branch:'];
		$valid = in_array($room, $baseRooms);
		if(!$valid){
			foreach($roomPrefixes as $pre){
				if(str_starts_with($room, $pre)){
					$valid = true;
					break;
				}
			}
		}
		if(!$valid){
			$room = 'global';
		}

		if(URL(2) === 'messages'){
			$since_id = (int)($_GET['since_id'] ?? 0);
			$params = ['room' => $room];
			if($since_id > 0){
				$query = "SELECT m.id,m.room,m.user_id,m.message,m.image_path,m.image_paths,m.created_at,u.first_name,u.last_name
						FROM chat_messages m
						LEFT JOIN users u ON u.id = m.user_id
						WHERE m.room = :room AND m.id > :since_id
						ORDER BY m.id ASC";
				$params['since_id'] = $since_id;
			}else{
				$query = "SELECT m.id,m.room,m.user_id,m.message,m.image_path,m.image_paths,m.created_at,u.first_name,u.last_name
						FROM chat_messages m
						LEFT JOIN users u ON u.id = m.user_id
						WHERE m.room = :room
						ORDER BY m.id DESC
						LIMIT 100";
			}
			$rows = $db->query($query,$params) ?: [];
			if($since_id === 0){
				$rows = array_reverse($rows);
			}
			header('Content-Type: application/json');
			echo json_encode($rows);
			exit;
		}

		if(URL(2) === 'send' && $req->posted()){
			// Support both JSON (existing behaviour) and multipart/form-data (for image upload)
			$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
			$contentType = strtolower($contentType);
			$message     = '';
			$roomIn      = '';
			$imagePath   = null;      // first image (for backward compatibility)
			$imagePaths  = [];        // all images (up to 5)

			if(str_starts_with($contentType, 'application/json')){
				$input   = json_decode(file_get_contents('php://input'), true) ?: [];
				$message = trim($input['message'] ?? '');
				$roomIn  = trim($input['room'] ?? '');
			}else{
				// Standard form submission (used by chat UI when sending images)
				$message = trim($_POST['message'] ?? '');
				$roomIn  = trim($_POST['room'] ?? '');
			}

			if($roomIn){
				$room = $roomIn;
			}

			$uploadDir = 'uploads/chat';
			$allowed   = ['jpg','jpeg','png','gif','webp'];

			// Handle multiple image upload (field name: chat_images[])
			if(!empty($_FILES['chat_images']) && is_array($_FILES['chat_images']['name'])){
				$names     = $_FILES['chat_images']['name'];
				$tmpNames  = $_FILES['chat_images']['tmp_name'];
				$errors    = $_FILES['chat_images']['error'];
				$total     = count($names);
				$maxImages = 5;

				if(!file_exists($uploadDir)){
					@mkdir($uploadDir, 0777, true);
				}

				for($i = 0; $i < $total && count($imagePaths) < $maxImages; $i++){
					if($errors[$i] !== UPLOAD_ERR_OK){
						continue;
					}
					if(empty($names[$i]) || !is_uploaded_file($tmpNames[$i])){
						continue;
					}
					$ext = pathinfo($names[$i], PATHINFO_EXTENSION);
					$ext = strtolower($ext);
					if(!in_array($ext, $allowed)){
						continue;
					}
					$base   = 'chat_'.date('Ymd_His').'_'.$session->id.'_'.rand(1000,9999).'.'.$ext;
					$target = rtrim($uploadDir,'/').'/'.$base;
					if(move_uploaded_file($tmpNames[$i], $target)){
						$imagePaths[] = $target; // store as relative path
					}
				}
			}

			// Fallback: single file field chat_image (older clients)
			if(empty($imagePaths) && !empty($_FILES['chat_image']['name']) && is_uploaded_file($_FILES['chat_image']['tmp_name'])){
				if(!file_exists($uploadDir)){
					@mkdir($uploadDir, 0777, true);
				}
				$ext = pathinfo($_FILES['chat_image']['name'], PATHINFO_EXTENSION);
				$ext = strtolower($ext);
				if(in_array($ext, $allowed)){
					$base   = 'chat_'.date('Ymd_His').'_'.$session->id.'_'.rand(1000,9999).'.'.$ext;
					$target = rtrim($uploadDir,'/').'/'.$base;
					if(move_uploaded_file($_FILES['chat_image']['tmp_name'], $target)){
						$imagePaths[] = $target;
					}
				}
			}

			if(!empty($imagePaths)){
				$imagePath = $imagePaths[0];
			}

			if($message === '' && empty($imagePaths)){
				header('Content-Type: application/json', true, 400);
				echo json_encode(['error' => 'Empty message']);
				exit;
			}
			$db->query("INSERT INTO chat_messages (room,user_id,message,image_path,image_paths) VALUES (:room,:user_id,:message,:image_path,:image_paths)",[
				'room'        => $room,
				'user_id'     => $session->id,
				'message'     => $message,
				'image_path'  => $imagePath,
				'image_paths' => !empty($imagePaths) ? json_encode($imagePaths) : null,
			]);
			$idRow = $db->query("SELECT LAST_INSERT_ID() AS id");
			$lastId = $idRow[0]->id ?? 0;
			header('Content-Type: application/json');
			echo json_encode([
				'ok'         => true,
				'id'         => (int)$lastId,
				'room'       => $room,
				'imagePath'  => $imagePath,
				'imagePaths' => $imagePaths,
			]);
			exit;
		}

		if(URL(2) === 'summary'){
			$query = "SELECT room, MAX(id) AS last_id FROM chat_messages GROUP BY room";
			$rows  = $db->query($query) ?: [];
			header('Content-Type: application/json');
			echo json_encode($rows);
			exit;
		}
	}
});


/** displays the view file **/
add_action('basic-admin_main_content',function(){

	$ses = new \Core\Session;
	$vars = get_value();
	$db = new \Core\Database;

	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];
	
	$errors = $vars['errors'] ?? [];

	// Retrieve session details
	$session   = get_session();
	$username  = $session->first_name . ' ' . $session->last_name ?? '';
	$user_id   = $session->id ?? 0;
	$roles     = $session->roles ?? [];
	$branchKey = $session->branch_name ?? ($session->branch ?? '');

	// Get the list of online users (ensure it's always an array)
	$onlineUsers = get_online_users() ?: [];

	if(URL(1) == "" OR URL(1) == $vars['plugin_route']){

		$getFirstRow = static function($rows): ?object {
			if (is_array($rows) && isset($rows[0]) && is_object($rows[0])) {
				return $rows[0];
			}
			return null;
		};
		$getTotal = static function($rows, string $field = 'total') use ($getFirstRow): float {
			$row = $getFirstRow($rows);
			return $row && isset($row->$field) ? (float)$row->$field : 0.0;
		};

		$query = "SELECT COUNT(*) AS total FROM transactions";
        
        $transactions = $db->query($query);		
		$total_transactions = $getTotal($transactions);

		$query = "SELECT COUNT(*) AS total FROM sims WHERE transaction_status = :transaction_status AND (created_by = :user_id OR assigned = :user_id)";
        
        $sims_unassigned = $db->query($query, ['transaction_status'=> 'Unassigned', 'user_id'=>$user_id]);		
		$sims_unassigned = $getTotal($sims_unassigned);
        
        $sims_assigned = $db->query($query, ['transaction_status'=> 'Assigned', 'user_id'=>$user_id]);		
		$sims_assigned = $getTotal($sims_assigned);
        
        $sims_sold_wholesale = $db->query($query, ['transaction_status'=> 'Sold - wholesale', 'user_id'=>$user_id]);		
		$sims_sold_wholesale = $getTotal($sims_sold_wholesale);
        
        $sims_sold_retail = $db->query($query, ['transaction_status'=> 'Sold - retail', 'user_id'=>$user_id]);		
		$sims_sold_retail = $getTotal($sims_sold_retail);
        
        $sims_expired = $db->query($query, ['transaction_status'=> 'Expired', 'user_id'=>$user_id]);		
		$sims_expired = $getTotal($sims_expired);
        
        $query = "SELECT COUNT(*) AS total, SUM(amount) AS total_amount FROM ezwichs WHERE transaction_status = :transaction_status AND created_by = :created_by";

        $pending_ezwichs = $db->query($query, ['transaction_status'=> 'Pending', 'created_by'=>$user_id]);		
		$pending_ezwichs = $getFirstRow($pending_ezwichs) ?? (object)['total' => 0, 'total_amount' => 0];
        
        $approved_ezwichs = $db->query($query, ['transaction_status'=> 'Approved', 'created_by'=>$user_id]);		
		$approved_ezwichs = $getFirstRow($approved_ezwichs) ?? (object)['total' => 0, 'total_amount' => 0];

		$query = "SELECT COUNT(*) AS total, SUM(amount) AS total_amount FROM payments WHERE transaction_status = :transaction_status";
        
        $payments = $db->query($query, ['transaction_status'=>'Approved']);		
		$payments = $payments[0] ?? [];

		$query = "SELECT COUNT(*) AS total, SUM(balance) AS total_balance FROM wallets WHERE deleted = :deleted";
        
        $wallets = $db->query($query, ['deleted'=>0]);		
		$wallets = $wallets[0] ?? [];

		$query = "SELECT COUNT(*) AS total, SUM(amount) AS total_payment FROM payments WHERE transaction_status = :transaction_status";
        
        $pending_transactions = $db->query($query, ['transaction_status'=> 'Pending']);		
        $approved_transactions = $db->query($query, ['transaction_status'=> 'Approved']);		
        $rejected_transactions = $db->query($query, ['transaction_status'=> 'Rejected']);		

		$pending_transactions = $pending_transactions[0] ?? [];
		$approved_transactions = $approved_transactions[0] ?? [];
		$rejected_transactions = $rejected_transactions[0] ?? [];

		$query = "SELECT COUNT(*) AS total FROM users WHERE deleted = :deleted";
        
        $deleted_users = $db->query($query,[':deleted'=>1]);
        $not_deleted_users = $db->query($query,[':deleted'=>0]);
		
		$total_deleted_users = $deleted_users[0]->total ?? 0;
		$total_active_users = $not_deleted_users[0]->total ?? 0;	        

		$limit = 40;
		$pager = new \Core\Pager($limit);
		$offset = (int)$pager->offset;

		// Fetch recent activities with explicit integer LIMIT/OFFSET to avoid binding issues
		$limit  = (int)$limit;
		$offset = (int)$offset;
		$query  = "SELECT * FROM logs ORDER BY id DESC LIMIT $limit OFFSET $offset";
		$recentActivities = $db->query($query);

        // Fetch users server-side
		  $query = "SELECT * FROM users WHERE deleted = 0";
		  $users = $db->query($query);
		  // Define all activity tables
		  $activityTypes = [
		    'agencybankings','airtimebundles','attendance','cfas','commissions',
		    'deposits','ezwichs','momoremits','myaccounts','payments',
		    'payment_services','requests','reversals','sendings','sims',
		    'tickets','transactions','withdrawals'
		  ];			
		require plugin_path('views/dashboard.php');		

	}
});

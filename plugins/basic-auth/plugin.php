<?php

/**
 * Plugin name: Basic Authentication
 * Description: Lets users login and signup
 * 
 * 
 **/

set_value([

	'login_page'	=>'login',
	'signup_page'	=>'signup',
	'maintenance_page'	=>'maintenance',
	'forgot_page'	=>'forgot',
	'logout_page'	=>'logout',
	'admin_plugin_route'	=>'admin',
	'tables'		=> [
		'users_table' => 'users'
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
	dd("Missing database tables in ".plugin_id() ." plugin: ". implode(",", $tables));
	die;
}

if(!function_exists('basic_auth_runtime_marker_path'))
{
	function basic_auth_runtime_marker_path(string $name): string
	{
		$dir = __DIR__ . DIRECTORY_SEPARATOR . '.runtime';
		if(!is_dir($dir)){
			@mkdir($dir, 0777, true);
		}
		return $dir . DIRECTORY_SEPARATOR . $name;
	}
}

if(!function_exists('basic_auth_ensure_user_disabled_column'))
{
	function basic_auth_ensure_user_disabled_column(): void
	{
		$marker = basic_auth_runtime_marker_path('users_disabled_column_20260427.done');
		if(file_exists($marker)){
			return;
		}
		try {
			$db = new \Core\Database;
			$disabledColumn = $db->query("SHOW COLUMNS FROM users LIKE 'disabled'");
			if(empty($disabledColumn)){
				$db->query("ALTER TABLE users ADD COLUMN disabled TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER deleted");
			}
			@file_put_contents($marker, '1');
		} catch (\Throwable $e) {
			error_log('[basic-auth] Disabled user column check failed: ' . $e->getMessage());
		}
	}
}

//basic_auth_ensure_user_disabled_column();

if(!function_exists('basic_auth_ensure_force_password_change_column'))
{
	function basic_auth_ensure_force_password_change_column(): bool
	{
		static $resolved = null;
		if ($resolved !== null) {
			return $resolved;
		}

		try {
			$db = new \Core\Database;
			$columns = $db->query("SHOW COLUMNS FROM users LIKE 'force_password_change'");
			if ($db->has_error) {
				throw new \RuntimeException($db->error);
			}
			if (empty($columns)) {
				$db->query("ALTER TABLE users ADD COLUMN force_password_change TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER password");
				if ($db->has_error) {
					$alterError = $db->error;
					$columns = $db->query("SHOW COLUMNS FROM users LIKE 'force_password_change'");
					if ($db->has_error || empty($columns)) {
						throw new \RuntimeException($alterError);
					}
				}
			}
			$resolved = true;
		} catch (\Throwable $e) {
			error_log('[basic-auth] Forced password change column check failed: ' . $e->getMessage());
			$resolved = false;
		}

		return $resolved;
	}
}

basic_auth_ensure_force_password_change_column();

add_action('before_controller', function(){
	$session = get_session();
	$userId = (int)($session->id ?? 0);
	if ($userId <= 0 || (int)($session->force_password_change ?? 0) !== 1) {
		return;
	}

	if (strtolower((string)page()) === 'logout') {
		return;
	}

	$isPasswordChangeRoute = strtolower((string)page()) === 'admin'
		&& strtolower((string)(URL(1) ?? '')) === 'profile'
		&& strtolower((string)(URL(2) ?? '')) === 'resetpassword'
		&& (int)(URL(3) ?? 0) === $userId;
	if ($isPasswordChangeRoute) {
		return;
	}

	redirect('admin/profile/resetpassword/' . $userId);
});

/** run this after a form submit **/
add_action('controller',function(){

	$vars = get_value();
	$req = new \Core\Request;
	$ses = new \Core\Session;
	$user = new \BasicAuth\User;

    //session data
    $user::$query_id = 'get-users';
    $session = get_session();
    if(!empty($session->id) && !empty($session->first_name) && !empty($session->last_name)){
        $username = $session->first_name ?? '' . ' '. $session->last_name ?? '';
        $user_id = $session->id;
        $role = $session->roles[0] ?? '';
    }

	if($req->posted() && page() == $vars['login_page'])
		require plugin_path('controllers/login-controller.php');
	
	else
	if($req->posted() && page() == $vars['signup_page'])
		require plugin_path('controllers/signup-controller.php');

	else
	if(page() == $vars['logout_page'])
		require plugin_path('controllers/logout-controller.php');

});


add_filter('before_check_admin', function($data){
	try {
		$session = get_session();
		$roles = [];
		if (is_object($session) && isset($session->roles) && is_array($session->roles)) {
			$roles = $session->roles;
		}
		$roles = array_map(function($r){ return strtolower(trim((string)$r)); }, $roles);
		if (in_array('admin', $roles, true)) {
			$data['is_admin'] = true;
		}
	} catch (\Throwable $e) {
	}

	return $data;
});


/** set permissions for current user **/
add_filter('user_permissions',function($permissions){

	$ses = new \Core\Session;

	if($ses->is_logged_in())
	{

		$vars = get_value();
		$db = new \Core\Database;

		$query = "select * from " . $vars['optional_tables']['roles_table'];
		$roles = $db->query($query);

		if(is_array($roles))
		{
			$user_id = $ses->user('id');
			$query = "select permission from " .$vars['optional_tables']['permissions_table']. " 
			 where disabled = 0 && role_id in 
			(select role_id from " .$vars['optional_tables']['roles_map_table']. "
			 where disabled = 0 && user_id = :user_id)";
			 
			$perms = $db->query($query,['user_id'=>$user_id]);
			if($perms)
			 	$permissions = array_column($perms, 'permission');

		}else{
			$permissions[] = 'all';
		}

	}

	return $permissions;
});


add_filter('header-footer_before_menu_links',function($links){

	$ses = new \Core\Session;
	$vars = get_value();

	$link        = (object)[];
    $link->id    = 0;
    $link->title = 'Login';
    $link->slug  = 'login';
    $link->icon  = 'fas fa-sign-in-alt';
    $link->permission  = 'not_logged_in';
    $links[] = $link;

    /*$link        = (object)[];
    $link->id    = 0;
    $link->title = 'Signup';
    $link->slug  = 'signup';
    $link->icon  = '';
    $link->permission  = 'not_logged_in';
    $links[] = $link;*/

    $link        = (object)[];
    $link->id    = 0;
    $link->title = 'Hi, ' . $ses->user('first_name');
    $link->slug  = '#';
    $link->image  = $ses->user('image');
    $link->icon  = '';
    $link->show_image  = 1;
    $link->list_order  = 20;
    $link->permission  = 'logged_in';
    $link->children  = [];

	    $link2        = (object)[];
	    $link2->id    = 0;
	    $link2->title = 'Profile';
	    $link2->slug  = 'admin/profile/view/'. $ses->user('id');
	    $link2->icon  = 'fas fa-user';
	    $link2->permission  = 'logged_in';
	    $link->children[] = $link2;
		
		$link2        = (object)[];
	    $link2->id    = 0;
	    $link2->title = 'Dashboard';
	    $link2->slug  = $vars['admin_plugin_route'];
	    $link2->icon  = 'fas fa-tachometer-alt';
	    $link2->permission  = 'logged_in';
	    $link->children[] = $link2;

	    $link2        = (object)[];
	    $link2->id    = 0;
	    $link2->title = 'Logout';
	    $link2->slug  = 'logout';
	    $link2->icon  = 'fas fa-sign-out-alt';
	    $link2->permission  = 'logged_in';
	    $link->children[] = $link2;

    $links[] = $link;

    
	return $links;
});


/** displays the view file **/
add_action('view',function(){

	$vars = get_value();
	$user = new \BasicAuth\User;

    //session data
    $user::$query_id = 'get-users';
    $session = get_session();
    if(!empty($session->id) && !empty($session->first_name) && !empty($session->last_name)){
        $username = $session->first_name . ' '. $session->last_name;
        $user_id = $session->id;
		$role = '';
		if (isset($session->roles) && is_array($session->roles) && isset($session->roles[0])) {
			$role = (string)$session->roles[0];
		}
    }

	if(page() == $vars['login_page']){
		
		require plugin_path('views/login.php');
	}else
	if(page() == $vars['signup_page'])
	{
		$errors = $vars['errors'] ?? [];
		require plugin_path('views/signup.php');
	}else
	if(page() == $vars['maintenance_page'])
	{
		$errors = $vars['errors'] ?? [];
		require plugin_path('views/maintenance.php');
	}

});

/** for manipulating data after a query operation **/
add_filter('after_query',function($data){

	
	if(empty($data['result']))
		return $data;

	if($data['query_id'] == 'get-users')
	{
		$db = new \Core\Database;
		foreach ($data['result'] as $key => $row) {
			
			$query = "select * from user_roles where disabled = 0 && id in (select role_id from user_roles_map where disabled = 0 && user_id = :user_id)";
			$roles = $db->query($query,['user_id'=>$row->id]);
			if($roles)
				$data['result'][$key]->roles = array_column($roles, 'role');
			
			/** get ezwich's roles **/
				
			$query = "select * from user_roles_map where user_id = :user_id && disabled = :disabled";
			$role_ids = $db->query($query, ['user_id'=>$row->id,'disabled'=>0]);
			if($role_ids)
				$data['result'][$key]->role_ids = array_column($role_ids, 'role_id');

		}
	}

	return $data;
});



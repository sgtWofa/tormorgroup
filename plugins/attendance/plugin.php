<?php

/**
 * Plugin name: Performance
 * Description: A way for admin to manage account
 * 
 * 
 **/

set_value([
	'admin_route'	=>'admin',
	'plugin_route'	=>'attendance',
	'tables'		=>[
		'performance_table' 				=> 'performance',
		'attendance_table' 				=> 'attendance',
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

$attendanceWorkCloseHelper = plugin_path('helpers/work-close-attendance.php');
if (file_exists($attendanceWorkCloseHelper)) {
	require_once $attendanceWorkCloseHelper;
}


/** set performance permissions for this plugin **/
add_filter('permissions',function($permissions){

	$permissions[] = 'view_attendance';
	$permissions[] = 'add_attendance';

	return $permissions;
});


/** Add to admin links **/
add_filter('basic-admin_before_admin_links', function ($links) {
    $vars = get_value();

    /*$obj = (object)[];
    $obj->title = 'Performance';
    $obj->id = 'view_performance';
    $obj->link = ROOT . '/' . $vars['admin_route'] . '/' . $vars['plugin_route'] . '/list';
    $obj->icon = 'fa-solid fa-chart-line'; // Performance icon
    $obj->parent = 0;

    $links[] = $obj;*/

    return $links;
});

add_action('before_controller', function () {
	static $checked = false;
	if ($checked) {
		return;
	}
	$checked = true;

	try {
		if (!function_exists('attendance_mark_absentees_after_work_close')) {
			return;
		}

		$ses = new \Core\Session;
		if (!$ses->is_logged_in()) {
			return;
		}

		if (strtolower(trim((string)URL(0))) !== 'admin') {
			return;
		}

		$vars = get_value();
		$pluginRoute = strtolower(trim((string)($vars['plugin_route'] ?? 'attendance')));
		if (strtolower(trim((string)URL(1))) !== $pluginRoute) {
			return;
		}

		attendance_mark_absentees_after_work_close();
	} catch (\Throwable $e) {
		error_log('[attendance] close-of-work trigger failed: ' . $e->getMessage());
	}
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
		$performance = new \Performances\Performance;
		$attendance = new \Performances\Attendance;

		require plugin_path('controllers/attendance-controller.php');

	}
});


/** displays the view file **/
add_action('basic-admin_main_content',function(){

	$ses = new \Core\Session;
	$performance = new \Performances\Performance;
	$attendance = new \Performances\Attendance;
	$user = new \Performances\User;
	$vars = get_value();

	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];
	
	$errors = $vars['errors'] ?? [];

	$users = $user->query(
		"SELECT id, first_name, last_name, disabled
		 FROM users
		 WHERE deleted = 0 AND COALESCE(disabled, 0) = 0
		 ORDER BY first_name ASC, last_name ASC"
	) ?: [];


	if(URL(1) == $vars['plugin_route']){

	    $session = get_session();
	    $user_id = $session->id;

			// If not an AJAX request, render the view
			require plugin_path('views/admin/attendance.php');

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
			$performance_roles_map = new \Performances\User_roles_map;
				
			$role_ids = $performance_roles_map->where(['user_id'=>$row->id,'disabled'=>0]);
			if($role_ids)
				$data['result'][$key]->role_ids = array_column($role_ids, 'role_id');

		}
	}

	return $data;
});


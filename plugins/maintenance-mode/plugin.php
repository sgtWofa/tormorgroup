<?php

/**
 * Plugin name: Maintenance Mode
 * Description: Allows admin to enable maintenance mode and allow selected users to bypass it.
 * 
 * 
 **/

set_value([

	'admin_route' => 'admin',
	'plugin_route' => 'maintenance-mode',
	'storage_file' => ROOTPATH . 'maintenance-mode.json',

]);

/** set user permissions for this plugin **/
add_filter('permissions', function($permissions){

	$permissions[] = 'manage_maintenance_mode';

	return $permissions;
});

function maintenance_mode_get_settings(): array
{
	$vars = get_value();
	$file = (string)($vars['storage_file'] ?? (ROOTPATH . 'maintenance-mode.json'));

	$data = null;
	if($file !== '' && file_exists($file)){
		$raw = (string)@file_get_contents($file);
		$tmp = json_decode($raw, true);
		if(is_array($tmp)) $data = $tmp;
	}
	if(!is_array($data)){
		$data = [
			'enabled' => 0,
			'allowed_users' => [],
			'duration_minutes' => 120,
			'started_at' => '',
			'ends_at' => '',
		];
	}

	$enabled = !empty($data['enabled']) ? 1 : 0;
	$allowed = $data['allowed_users'] ?? [];
	if(!is_array($allowed)) $allowed = [];
	$allowed = array_values(array_unique(array_map(function($v){ return (int)$v; }, $allowed)));
	$allowed = array_values(array_filter($allowed, function($v){ return $v > 0; }));

	$durationMinutes = (int)($data['duration_minutes'] ?? 0);
	if($durationMinutes <= 0) $durationMinutes = 120;

	$startedAt = (string)($data['started_at'] ?? '');
	$endsAt = (string)($data['ends_at'] ?? '');

	return [
		'enabled' => $enabled ? 1 : 0,
		'allowed_users' => $allowed,
		'duration_minutes' => $durationMinutes,
		'started_at' => $startedAt,
		'ends_at' => $endsAt,
		'raw' => $data,
		'file' => $file,
	];
}

function maintenance_mode_save_settings(int $enabled, array $allowedUsers, int $durationMinutes = 120, string $startedAt = '', string $endsAt = ''): bool
{
	$vars = get_value();
	$file = (string)($vars['storage_file'] ?? (ROOTPATH . 'maintenance-mode.json'));
	if($file === '') return false;

	$allowedUsers = array_values(array_unique(array_map(function($v){ return (int)$v; }, $allowedUsers)));
	$allowedUsers = array_values(array_filter($allowedUsers, function($v){ return $v > 0; }));

	if($durationMinutes <= 0) $durationMinutes = 120;

	$payload = [
		'enabled' => $enabled ? 1 : 0,
		'allowed_users' => $allowedUsers,
		'duration_minutes' => (int)$durationMinutes,
		'started_at' => (string)$startedAt,
		'ends_at' => (string)$endsAt,
		'updated_at' => date('Y-m-d H:i:s'),
	];

	$dir = dirname($file);
	if($dir && !is_dir($dir)){
		@mkdir($dir, 0777, true);
	}

	return @file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT)) !== false;
}

function maintenance_mode_user_allowed(int $userId, array $settings): bool
{
	if($userId <= 0) return false;
	$allowed = $settings['allowed_users'] ?? [];
	return in_array($userId, $allowed, true);
}

/** global gate **/
add_action('before_controller', function(){
	try {
		$settings = maintenance_mode_get_settings();
		if(empty($settings['enabled'])){
			return;
		}

		$endsAt = (string)($settings['ends_at'] ?? '');
		if($endsAt !== ''){
			$endTs = strtotime($endsAt);
			if($endTs !== false && time() > $endTs){
				maintenance_mode_save_settings(0, $settings['allowed_users'] ?? [], (int)($settings['duration_minutes'] ?? 120), '', '');
				return;
			}
		}

		$vars = get_value();
		$adminRoute = (string)($vars['admin_route'] ?? 'admin');
		$pluginRoute = (string)($vars['plugin_route'] ?? 'maintenance-mode');

		$publicAllowPages = ['home', 'login', 'logout', 'forgot', 'signup'];
		if(in_array((string)page(), $publicAllowPages, true)){
			return;
		}

		// Always allow the maintenance-mode admin page to prevent lockout
		if((string)page() === $adminRoute && (string)URL(1) === $pluginRoute){
			return;
		}

		$ses = new \Core\Session;
		if(!$ses->is_logged_in()){
			require ROOTPATH . 'maintenance.php';
			exit;
		}

		if($ses->is_admin()){
			return;
		}

		$user = get_session();
		$userId = (int)($user->id ?? 0);
		if(maintenance_mode_user_allowed($userId, $settings)){
			return;
		}

		require ROOTPATH . 'maintenance.php';
		exit;

	} catch (\Throwable $e) {
		// fail open
		return;
	}
});

/** run this after a form submit **/
add_action('controller', function(){

	$req = new \Core\Request;
	$vars = get_value();

	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];

	if(page() == $admin_route && URL(1) == $plugin_route && $req->posted())
	{
		require plugin_path('controllers/save-controller.php');
	}
});

/** displays the view file **/
add_action('basic-admin_main_content', function(){

	$vars = get_value();
	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];

	if(page() == $admin_route && URL(1) == $plugin_route){
		if(!user_can('is_admin') && !user_can('manage_maintenance_mode')){
			echo '<div class="alert alert-danger text-center">Access denied</div>';
			return;
		}

		$settings = maintenance_mode_get_settings();
		$errors = $vars['errors'] ?? [];
		$form_action = ROOT . '/'.$admin_route.'/'.$plugin_route;

		$db = new \Core\Database;
		$users = $db->query("select id, first_name, last_name, email from users where deleted = 0 order by first_name asc, last_name asc");
		$users = $users ?: [];

		require plugin_path('views/admin/edit.php');
	}
});

/** add to admin links **/
add_filter('basic-admin_before_admin_links', function($links){

	/*if(user_can('is_admin') || user_can('manage_maintenance_mode'))
	{
		$vars = get_value();

		$obj = (object)[];
		$obj->title = 'Maintenance Mode';
		$obj->id = 'manage_maintenance_mode';
		$obj->link = ROOT . '/'.$vars['admin_route'].'/'.$vars['plugin_route'];
		$obj->icon = 'fa-solid fa-screwdriver-wrench';
		$obj->parent = 0;

		$links[] = $obj;
	}*/

	return $links;
});


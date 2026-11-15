<?php

/**
 * Plugin name: Basic Settings
 * Description: Creates settings for your website
 * 
 * 
 **/

set_value([

	'admin_route'	=>'admin',
	'plugin_route'	=>'settings',
	'tables'		=> [
		'settings_table' 			=> 'settings'
	],

]);

/** check if all tables exist **/
$db = new \Core\Database;
$tables = get_value()['tables'];

if(!$db->table_exists($tables)){
	dd("Missing database tables in ".plugin_id() ." plugin: ". implode(",", $tables));
	die;
}

if(!function_exists('admin_settings_notification_pill_plugin_map_config'))
{
	function admin_settings_notification_pill_plugin_map_config(): array
	{
		$defaults = [
			'serious' => [],
			'mild' => [],
			'regular' => [],
		];

		$selected = $defaults;

		try {
			// reuse existing helper if present
			if(function_exists('get_latest_settings_row')){
				$s = get_latest_settings_row();
				if(!empty($s->notification_pill_plugin_map)){
					$raw = trim((string)$s->notification_pill_plugin_map);
					$decoded = json_decode($raw, true);
					if(is_array($decoded)){
						foreach(['serious','mild','regular'] as $k){
							if(!empty($decoded[$k]) && is_array($decoded[$k])){
								$selected[$k] = array_values(array_map('strval', $decoded[$k]));
							}
						}
					}
				}
			} else {
				$db = new \Core\Database;
				$rows = $db->query("SELECT notification_pill_plugin_map FROM settings ORDER BY id DESC LIMIT 1");
				if(!empty($rows[0])){
					$row = $rows[0];
					$raw = trim((string)($row->notification_pill_plugin_map ?? ''));
					if($raw !== ''){
						$decoded = json_decode($raw, true);
						if(is_array($decoded)){
							foreach(['serious','mild','regular'] as $k){
								if(!empty($decoded[$k]) && is_array($decoded[$k])){
									$selected[$k] = array_values(array_map('strval', $decoded[$k]));
								}
							}
						}
					}
				}
			}
		} catch (\Throwable $e) {
			// swallow errors
		}

		return $selected;
	}
}

if(!function_exists('admin_settings_runtime_marker_path'))
{
	function admin_settings_runtime_marker_path(string $name): string
	{
		$dir = __DIR__ . DIRECTORY_SEPARATOR . '.runtime';
		if(!is_dir($dir)){
			@mkdir($dir, 0777, true);
		}
		return $dir . DIRECTORY_SEPARATOR . $name;
	}
}

if(!function_exists('admin_settings_notification_sound_options'))
{
	function admin_settings_notification_sound_allowed_extensions(): array
	{
		return ['mp3', 'wav', 'ogg', 'm4a', 'aac'];
	}

	function admin_settings_notification_sound_allowed_mimes(): array
	{
		return [
			'',
			'audio/mpeg',
			'audio/mp3',
			'audio/wav',
			'audio/x-wav',
			'audio/ogg',
			'audio/m4a',
			'audio/aac',
			'audio/mp4',
			'application/ogg',
			'application/octet-stream',
		];
	}

	function admin_settings_notification_sound_max_size_mb(): int
	{
		return 2;
	}

	function admin_settings_notification_sound_options(): array
	{
		$soundsDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'sounds';
		$options = [];
		$allowedExtensions = admin_settings_notification_sound_allowed_extensions();

		if(!is_dir($soundsDir)){
			return $options;
		}

		$files = scandir($soundsDir);
		if(empty($files) || !is_array($files)){
			return $options;
		}

		foreach ($files as $file) {
			if($file === '.' || $file === '..'){
				continue;
			}

			$path = $soundsDir . DIRECTORY_SEPARATOR . $file;
			if(!is_file($path)){
				continue;
			}

			$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
			if(!in_array($extension, $allowedExtensions, true)){
				continue;
			}

			$relativePath = 'assets/sounds/' . $file;
			$label = preg_replace('/[-_]+/', ' ', pathinfo($file, PATHINFO_FILENAME));
			$label = trim(ucwords((string)$label));

			$options[$relativePath] = [
				'label' => $label !== '' ? $label : $file,
				'url' => ROOT . '/' . $relativePath,
				'file' => $file,
			];
		}

		ksort($options);
		return $options;
	}
}

if(!function_exists('admin_settings_upload_notification_sound'))
{
	function admin_settings_upload_notification_sound(\Core\Request $req): string
	{
		$file = $req->files('notification_sound_upload');
		if(empty($file) || empty($file['name'])){
			return '';
		}

		if(!empty($file['error'])){
			$req->upload_errors['notification_sound_upload'] = 'An error occured with notification sound: ' . $file['name'];
			return '';
		}

		$extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
		$allowedExtensions = admin_settings_notification_sound_allowed_extensions();
		$allowedMimes = admin_settings_notification_sound_allowed_mimes();
		if(!in_array($extension, $allowedExtensions, true) || !in_array((string)$file['type'], $allowedMimes, true)){
			$req->upload_errors['notification_sound_upload'] = 'Invalid notification sound type. Allowed: ' . implode(', ', $allowedExtensions);
			return '';
		}

		$maxBytes = admin_settings_notification_sound_max_size_mb() * 1024 * 1024;
		if((int)$file['size'] > $maxBytes){
			$req->upload_errors['notification_sound_upload'] = 'Notification sound is too large. Max ' . admin_settings_notification_sound_max_size_mb() . 'MB allowed.';
			return '';
		}

		$soundsDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'sounds';
		if(!is_dir($soundsDir)){
			@mkdir($soundsDir, 0777, true);
		}

		$baseName = strtolower(pathinfo((string)$file['name'], PATHINFO_FILENAME));
		$baseName = preg_replace('/[^a-z0-9_-]+/', '-', $baseName);
		$baseName = trim((string)$baseName, '-_');
		if($baseName === ''){
			$baseName = 'notification-sound';
		}

		$filename = $baseName . '.' . $extension;
		$destination = $soundsDir . DIRECTORY_SEPARATOR . $filename;
		$count = 1;
		while(file_exists($destination)){
			$filename = $baseName . '-' . $count . '.' . $extension;
			$destination = $soundsDir . DIRECTORY_SEPARATOR . $filename;
			$count++;
		}

		if(!move_uploaded_file((string)$file['tmp_name'], $destination)){
			$req->upload_errors['notification_sound_upload'] = 'Could not upload notification sound.';
			return '';
		}

		return 'assets/sounds/' . $filename;
	}
}

if(!function_exists('admin_settings_notification_sound_config'))
{
	function admin_settings_notification_sound_config(): array
	{
		$options = admin_settings_notification_sound_options();
		$selected = '';
		$volume = 90;
		$enabled = true;

		try {
			$db = new \Core\Database;
			$rows = $db->query("SELECT notification_sound, notification_sound_volume, notification_sound_enabled FROM settings ORDER BY id DESC LIMIT 1");
			if(!empty($rows[0])){
				$row = $rows[0];
				$selected = trim((string)($row->notification_sound ?? ''));
				$volume = (int)($row->notification_sound_volume ?? 90);
				$enabled = ((int)($row->notification_sound_enabled ?? 1)) === 1;
			}
		} catch (\Throwable $e) {
			error_log('[admin-settings] Notification sound config failed: ' . $e->getMessage());
		}

		$volume = max(0, min(100, $volume));
		$sounds = [];

		if($selected !== '' && isset($options[$selected])){
			$sounds[] = $options[$selected]['url'];
		}else{
			foreach ($options as $option) {
				$sounds[] = $option['url'];
			}
		}

		return [
			'enabled' => $enabled,
			'volume' => $volume,
			'sounds' => $sounds,
			'selected' => $selected,
			'options' => $options,
		];
	}
}

if(!function_exists('admin_settings_notification_pill_color_config'))
{
	function admin_settings_notification_pill_color_config(): array
	{
		$defaults = [
			'serious' => '#dc3545',
			'mild' => '#ffc107',
			'regular' => '#0d6efd',
		];

		$selected = $defaults;

		try {
			$db = new \Core\Database;
			$rows = $db->query(
				"SELECT notification_pill_color_serious, notification_pill_color_mild, notification_pill_color_regular FROM settings ORDER BY id DESC LIMIT 1"
			);
			if(!empty($rows[0])){
				$row = $rows[0];
				$selected['serious'] = trim((string)($row->notification_pill_color_serious ?? '')) ?: $defaults['serious'];
				$selected['mild'] = trim((string)($row->notification_pill_color_mild ?? '')) ?: $defaults['mild'];
				$selected['regular'] = trim((string)($row->notification_pill_color_regular ?? '')) ?: $defaults['regular'];
			}
		} catch (\Throwable $e) {
			error_log('[admin-settings] Notification pill color config failed: ' . $e->getMessage());
		}

		return $selected;
	}
}


if(!function_exists('admin_settings_log_change'))
{
	function admin_settings_log_change(string $action, string $description): void
	{
		try {
			$session = get_session();
			$userId = (int)($session->id ?? 0);
			$userName = trim((string)(($session->first_name ?? '') . ' ' . ($session->last_name ?? '')));
			if($userName === ''){
				$userName = $userId > 0 ? 'User #' . $userId : 'System';
			}

			$db = new \Core\Database;
			if(!$db->table_exists(['logs'])){
				return;
			}

			$db->query(
				"INSERT INTO logs (table_name, action, user_id, user_name, description, date_created)
				 VALUES (:table_name, :action, :user_id, :user_name, :description, :date_created)",
				[
					'table_name' => 'settings',
					'action' => $action,
					'user_id' => $userId,
					'user_name' => $userName,
					'description' => $description,
					'date_created' => date('Y-m-d H:i:s'),
				]
			);
		} catch (\Throwable $e) {
			error_log('[admin-settings] Settings log failed: ' . $e->getMessage());
		}
	}
}

if(!function_exists('admin_settings_ensure_rate_history_schema'))
{
	function admin_settings_ensure_rate_history_schema(): void
	{
		try {
			$db = new \Core\Database;
			$db->query("
				CREATE TABLE IF NOT EXISTS cfa_rate_history (
					id INT(11) NOT NULL AUTO_INCREMENT,
					cfa_id INT(11) NULL DEFAULT NULL,
					settings_id INT(11) NULL DEFAULT NULL,
					buying_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
					selling_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
					changed_by INT(11) NULL DEFAULT NULL,
					note VARCHAR(120) NULL DEFAULT NULL,
					date_created DATETIME NOT NULL,
					PRIMARY KEY (id),
					UNIQUE KEY cfa_id (cfa_id),
					KEY settings_id (settings_id),
					KEY date_created (date_created),
					KEY buying_rate (buying_rate),
					KEY selling_rate (selling_rate)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
			");
			$columns = $db->query("SHOW COLUMNS FROM cfa_rate_history LIKE 'cfa_id'");
			if(empty($columns)){
				$db->query("ALTER TABLE cfa_rate_history ADD COLUMN cfa_id INT(11) NULL DEFAULT NULL AFTER id");
				$db->query("ALTER TABLE cfa_rate_history ADD UNIQUE KEY cfa_id (cfa_id)");
			}
		} catch (\Throwable $e) {
			error_log('[admin-settings] CFA rate history schema failed: ' . $e->getMessage());
		}
	}
}

if(!function_exists('admin_settings_record_cfa_rate_history'))
{
	function admin_settings_record_cfa_rate_history($settingsId, $buyingRate, $sellingRate, string $note = 'settings-update'): void
	{
		admin_settings_ensure_rate_history_schema();

		$buyingRate = (float)$buyingRate;
		$sellingRate = (float)$sellingRate;
		if($buyingRate <= 0 && $sellingRate <= 0){
			return;
		}

		try {
			$db = new \Core\Database;
			$latest = $db->query("SELECT buying_rate, selling_rate FROM cfa_rate_history ORDER BY id DESC LIMIT 1");
			$latest = is_array($latest) ? ($latest[0] ?? null) : null;
			if($latest && (float)$latest->buying_rate === $buyingRate && (float)$latest->selling_rate === $sellingRate){
				return;
			}

			$session = get_session();
			$db->query(
				"INSERT INTO cfa_rate_history (cfa_id, settings_id, buying_rate, selling_rate, changed_by, note, date_created)
				VALUES (:cfa_id, :settings_id, :buying_rate, :selling_rate, :changed_by, :note, :date_created)",
				[
					'cfa_id' => null,
					'settings_id' => (int)$settingsId,
					'buying_rate' => $buyingRate,
					'selling_rate' => $sellingRate,
					'changed_by' => (int)($session->id ?? 0),
					'note' => $note,
					'date_created' => date('Y-m-d H:i:s'),
				]
			);
		} catch (\Throwable $e) {
			error_log('[admin-settings] CFA rate history insert failed: ' . $e->getMessage());
		}
	}
}

if(!function_exists('admin_settings_ensure_runtime_schema'))
{
	function admin_settings_ensure_runtime_schema(): void
	{
		$marker = admin_settings_runtime_marker_path('admin_settings_schema_20260508_notifications.done');

		try {
			$db = new \Core\Database;
			$columnFixes = [
				'work_open' => "ALTER TABLE settings ADD COLUMN work_open TIME DEFAULT NULL",
				'work_close' => "ALTER TABLE settings ADD COLUMN work_close TIME DEFAULT NULL AFTER work_open",
				'late_attendance_time' => "ALTER TABLE settings ADD COLUMN late_attendance_time TIME DEFAULT NULL AFTER work_close",
				'inactivity_limit_minutes' => "ALTER TABLE settings ADD COLUMN inactivity_limit_minutes INT(11) NULL DEFAULT 5 AFTER late_attendance_time",
				'notification_sound' => "ALTER TABLE settings ADD COLUMN notification_sound VARCHAR(255) NULL DEFAULT NULL AFTER inactivity_limit_minutes",
				'notification_sound_volume' => "ALTER TABLE settings ADD COLUMN notification_sound_volume TINYINT UNSIGNED NULL DEFAULT 90 AFTER notification_sound",
				'notification_sound_enabled' => "ALTER TABLE settings ADD COLUMN notification_sound_enabled TINYINT(1) NULL DEFAULT 1 AFTER notification_sound_volume",
			];

			$missingColumns = false;
			foreach ($columnFixes as $column => $query) {
				$exists = $db->query("SHOW COLUMNS FROM settings LIKE '{$column}'");
				if(empty($exists)){
					$missingColumns = true;
					$db->query($query);
				}
			}

			$db->query("CREATE TABLE IF NOT EXISTS branches (
				id INT UNSIGNED NOT NULL AUTO_INCREMENT,
				name VARCHAR(255) NOT NULL,
				code VARCHAR(100) DEFAULT NULL,
				active TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
				deleted TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
				created_by VARCHAR(100) DEFAULT NULL,
				updated_by VARCHAR(100) DEFAULT NULL,
				date_created DATETIME DEFAULT NULL,
				date_updated DATETIME DEFAULT NULL,
				date_deleted DATETIME DEFAULT NULL,
				PRIMARY KEY (id),
				KEY active (active),
				KEY deleted (deleted)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

			if($missingColumns || !file_exists($marker)){
				@file_put_contents($marker, '1');
			}
		} catch (\Throwable $e) {
			error_log('[admin-settings] Runtime schema check failed: ' . $e->getMessage());
		}
	}
}

if(!function_exists('admin_settings_fetch_branches'))
{
	function admin_settings_fetch_branches(array $filters = []): array
	{
		try {
			$db = new \Core\Database;
			$whereParts = [];
			$params = [];

			if(empty($filters['include_deleted'])){
				$whereParts[] = 'deleted = 0';
			}

			if(!empty($filters['active_only'])){
				$whereParts[] = 'active = 1';
			}

			if(isset($filters['branch_id'])){
				$whereParts[] = 'id = :branch_id';
				$params['branch_id'] = (int)$filters['branch_id'];
			}

			$where = '';
			if(!empty($whereParts)){
				$where = 'WHERE ' . implode(' AND ', $whereParts);
			}

			$rows = $db->query("SELECT * FROM branches {$where} ORDER BY active DESC, name ASC", $params) ?: [];
			return is_array($rows) ? $rows : [];
		} catch (\Throwable $e) {
			return [];
		}
	}
}

if(!function_exists('admin_settings_branch_options'))
{
	function admin_settings_branch_options(): array
	{
		$branches = admin_settings_fetch_branches(['active_only' => true, 'include_deleted' => false]);
		$options = [];
		foreach ($branches as $branch) {
			$options[(int)($branch->id ?? 0)] = trim((string)($branch->name ?? ''));
		}
		return $options;
	}
}

if(!function_exists('admin_settings_fetch_branch'))
{
	function admin_settings_fetch_branch(int $branchId): ?object
	{
		$branches = admin_settings_fetch_branches(['branch_id' => $branchId, 'include_deleted' => false]);
		return !empty($branches[0]) ? $branches[0] : null;
	}
}

admin_settings_ensure_runtime_schema();
admin_settings_ensure_rate_history_schema();

/** set user permissions for this plugin **/
add_filter('permissions',function($permissions){

	$permissions[] = 'view_settings';
	$permissions[] = 'is_admin';
	$permissions[] = 'add_settings';
	$permissions[] = 'edit_settings';

	return $permissions;
});


/** run this after a form submit **/
add_action('controller',function(){

	$req = new \Core\Request;
	$vars = get_value();
	
	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];

	if(page() == $vars['admin_route'] && URL(1) == $vars['plugin_route'] && $req->posted())
	{
		$ses = new \Core\Session;
		$setting = new \Settings\Setting;

		$id = URL(3) ?? null;
		if($id)
			$row = $setting->first(['id'=>$id]);

		if(URL(2) == 'branches'){
			require plugin_path('controllers/branches-controller.php');
		}else
		if(URL(2) == 'commission-rates'){
			require plugin_path('controllers/commission-rates-controller.php');
		}else
		if(URL(2) == 'add'){
			require plugin_path('controllers/add-controller.php');
		}else
		if(URL(2) == 'edit'){
			require plugin_path('controllers/edit-controller.php');
		}else
		if(URL(2) == 'delete'){

			require plugin_path('controllers/delete-controller.php');
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

	$setting = new \Settings\Setting;
	$notificationSoundOptions = admin_settings_notification_sound_options();

	if(page() == $vars['admin_route'] && URL(1) == $vars['plugin_route']){

		$id = URL(3) ?? null;
		if($id){
			$setting::$query_id = 'get-settings';
			$row = $setting->first(['id'=>$id]);

		}

		if(URL(2) == 'add'){
			$form_action = ROOT . '/'.$admin_route.'/'.$plugin_route.'/add';
			require plugin_path('views/admin/add.php');
		}else
		if(URL(2) == 'edit'){
			if(!empty($row) && !empty($row->id))
				$form_action = ROOT . '/'.$admin_route.'/'.$plugin_route.'/edit/'.$row->id;
			require plugin_path('views/admin/edit.php');
		}else
		if(URL(2) == 'delete'){

			require plugin_path('views/admin/delete.php');
		}else
		if(URL(2) == 'branches'){

			require plugin_path('views/admin/branches.php');
		}else
		if(URL(2) == 'commission-rates'){

			require plugin_path('views/admin/commission_rates.php');
		}else
		if(URL(2) == 'view'){

			require plugin_path('views/admin/view.php');
		}else
		{
			$setting->limit = 1;
			$setting->offset = 0;
			$setting::$query_id = 'get-settings';
			$rows = $setting->getAll();
			$row = (!empty($rows) && is_array($rows)) ? $rows[0] : false;

			if($row)
			{
				if(user_can('edit_settings'))
				{
					$form_action = ROOT . '/'.$admin_route.'/'.$plugin_route.'/edit/'.$row->id;
					require plugin_path('views/admin/edit.php');
				}else
				{
					require plugin_path('views/admin/view.php');
				}
			}else
			{
				if(user_can('add_settings'))
				{
					$form_action = ROOT . '/'.$admin_route.'/'.$plugin_route.'/add';
					require plugin_path('views/admin/add.php');
				}else
				{
					require plugin_path('views/admin/view.php');
				}
			}
		}

	}
});


/** add to amin links **/
add_filter('basic-admin_before_admin_links',function($links){

	if(user_can('is_admin'))
	{
		$vars = get_value();

		$obj = (object)[];
		$obj->title = 'Admin Settings';
		$obj->id = 'is_admin';
		$obj->link = ROOT . '/'.$vars['admin_route'].'/'.$vars['plugin_route'];
		$obj->icon = 'fa-solid fa-cog';
		$obj->parent = 0;

        $obj->sublinks = []; // Initialize sublinks array within the main link object

		// Add "Buy" sublink
		$sub = (object)[];
		$sub->title = 'Admin Settings';
		$sub->link = ROOT . '/' . $vars['admin_route'] . '/' . $vars['plugin_route'];
		$sub->icon = 'fa-solid fa-cog'; // Icon for buying/incoming funds
		$sub->parent = $obj->id;
		$sub->id = 'view_settings';
		$obj->sublinks[] = $sub;

		// Add "Buy" sublink
		$sub = (object)[];
		$sub->title = 'Permissions';
		$sub->link = ROOT . '/' . $vars['admin_route'] . '/user-roles';
		$sub->icon = 'fa-solid fa-user-shield'; // Icon for buying/incoming funds
		$sub->parent = $obj->id;
		$sub->id = 'view_roles';
		$obj->sublinks[] = $sub;

		// Add "Buy" sublink
		$sub = (object)[];
		$sub->title = 'Maintenance Mode';
		$sub->link = ROOT . '/' . $vars['admin_route'] . '/maintenance-mode';
		$sub->icon = 'fa-solid fa-screwdriver-wrench';
		$sub->parent = $obj->id;
		$sub->id = 'manage_maintenance_mode';
		$obj->sublinks[] = $sub;

		// Add Commission Rates sublink
		$sub = (object)[];
		$sub->title = 'Commission Rates';
		$sub->link = ROOT . '/' . $vars['admin_route'] . '/' . $vars['plugin_route'] . '/commission-rates';
		$sub->icon = 'fa-solid fa-percent';
		$sub->parent = $obj->id;
		$sub->id = 'manage_commission_rates';
		$obj->sublinks[] = $sub;

		// Add "Sell" sublink
		/*$sub = (object)[];
		$sub->title = 'Menu Lists';
		$sub->link = ROOT . '/' . $vars['admin_route'] . '/main-menu';
		$sub->icon = 'fa-solid fa-bars'; // Icon for selling/outgoing funds
		$sub->parent = $obj->id;
		$sub->id = 'view_menu';
		$obj->sublinks[] = $sub;

		// Add "Sell" sublink
		$sub = (object)[];
		$sub->title = 'Slider Images';
		$sub->link = ROOT . '/' . $vars['admin_route'] . '/slider';
		$sub->icon = 'fa-solid fa-image'; // Icon for slider
		$sub->parent = $obj->id;
		$sub->id = 'view_slider';
		$obj->sublinks[] = $sub;*/

		$links[] = $obj;
	}

	return $links;
});


/** for manipulating data after a query operation **/
add_filter('after_query',function($data){
	
	if(empty($data['result']))
		return $data;

	if($data['query_id'] == 'get-settings')
	{
		$db = new \Core\Database;
		foreach ($data['result'] as $key => $row) {
			
			$user_row = $db->get_row("select * from users where id = :id limit 1",['id'=>$row->user_id]);
			if($user_row)
				$data['result'][$key]->user_row = $user_row;
			
		}
	}

	return $data;
});

/**
 * Helper functions for Commission Rates
 */

if(!function_exists('get_commission_rate')) {
	function get_commission_rate($commission_type) {
		$commissionRate = new \Settings\CommissionRate;
		return $commissionRate->getByType($commission_type);
	}
}

if(!function_exists('get_all_commission_rates')) {
	function get_all_commission_rates($active_only = true) {
		$commissionRate = new \Settings\CommissionRate;
		
		if ($active_only) {
			return $commissionRate->getActive() ?: [];
		}
		
		// Ensure where() query executes safely without chaining orderBy directly on a potential false/array return
		$rates = $commissionRate->where(['deleted' => 0]);
		
		if (is_array($rates)) {
			// Sort array manually if where() directly returned an array of records
			usort($rates, function($a, $b) {
				return strcmp($a->commission_type ?? '', $b->commission_type ?? '');
			});
			return $rates;
		}
		
		if (is_object($rates) && method_exists($rates, 'orderBy')) {
			$results = $rates->orderBy('commission_type', 'ASC')->get();
			return is_array($results) ? $results : [];
		}
		
		// Fallback if where() returned false or null
		$all = $commissionRate->getAll();
		return is_array($all) ? array_filter($all, fn($r) => empty($r->deleted)) : [];
	}
}

if(!function_exists('calculate_commission')) {
	/**
	 * Calculate commission based on commission type and amount
	 * 
	 * @param string $commission_type
	 * @param float $amount
	 * @return array ['commission' => float, 'expected_value' => float, 'rate' => object]
	 */
	function calculate_commission($commission_type, $amount) {
		$rate = get_commission_rate($commission_type);
		
		if (!$rate) {
			return [
				'commission' => 0,
				'expected_value' => 0,
				'rate' => null,
				'error' => 'Commission type not found'
			];
		}
		
		$commission = $rate->calculateCommission($amount);
		$expectedValue = $rate->calculateExpectedValue($commission);
		
		return [
			'commission' => $commission,
			'expected_value' => $expectedValue,
			'rate' => $rate
		];
	}
}

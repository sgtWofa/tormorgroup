<?php

/**
 * Plugin name: Sim
 * Description: A way for admin to manage sim
 * 
 * 
 **/

set_value([

	'admin_route'	=>'admin',
	'plugin_route'	=>'sims',
	'tables'		=>[
		'sims_table' 				=> 'sims',
	],

	'optional_tables'		=>[
	],
	

]);

if(!function_exists('sims_json_response')){
	function sims_json_response(array $payload, int $status = 200): void
	{
		$GLOBALS['sims_json_response_sent'] = true;
		while(ob_get_level() > 0){
			ob_end_clean();
		}
		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		exit;
	}
}

if(!function_exists('sims_render_pager')){
	function sims_render_pager(\Core\Pager $pager): string
	{
		$totalPages = max(1, (int)$pager->total_pages);
		$currentPage = min($totalPages, max(1, (int)$pager->page_number));
		$currentUrl = (string)($pager->links['current'] ?? '');
		$pageUrl = static function(int $page) use ($currentUrl): string {
			$url = preg_replace('/page=[0-9]+/', 'page=' . max(1, $page), $currentUrl);
			return is_string($url) ? $url : $currentUrl;
		};
		$visiblePages = [];
		if($totalPages <= 7){
			$visiblePages = range(1, $totalPages);
		}else{
			$visiblePages = [1, 2, $currentPage - 1, $currentPage, $currentPage + 1, $totalPages - 1, $totalPages];
			$visiblePages = array_values(array_unique(array_filter($visiblePages, static function(int $page) use ($totalPages): bool {
				return $page >= 1 && $page <= $totalPages;
			})));
			sort($visiblePages);
		}
		$renderLink = static function(string $label, int $page, bool $disabled = false, bool $active = false) use ($pageUrl): string {
			$classes = 'sim-page-control' . ($disabled ? ' is-disabled' : '') . ($active ? ' is-active' : '');
			if($disabled){
				return '<span class="' . $classes . '" aria-disabled="true">' . $label . '</span>';
			}
			$href = htmlspecialchars($pageUrl($page), ENT_QUOTES, 'UTF-8');
			return '<a class="' . $classes . '" href="' . $href . '">' . $label . '</a>';
		};

		$html = '<nav class="sim-pagination" aria-label="SIM transactions pages"><div class="sim-pagination-list">';
		$html .= $renderLink('First', 1, $currentPage === 1);
		$html .= $renderLink('Prev', max(1, $currentPage - 1), $currentPage === 1);
		$previous = 0;
		foreach($visiblePages as $page){
			if($previous > 0 && $page > $previous + 1){
				$html .= '<span class="sim-page-control sim-pagination-ellipsis" aria-hidden="true">...</span>';
			}
			$html .= $renderLink((string)$page, $page, false, $page === $currentPage);
			$previous = $page;
		}
		$html .= $renderLink('Next', min($totalPages, $currentPage + 1), $currentPage === $totalPages);
		$html .= $renderLink('Last', $totalPages, $currentPage === $totalPages);
		return $html . '</div></nav>';
	}
}

$simsRoute = URL(1) ?? '';
$simsAjaxRequest = $simsRoute === 'sims' && (
	!empty($_GET['ajax'])
	|| !empty($_GET['__ajax'])
	|| !empty($_POST['__ajax'])
	|| strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
	|| strpos(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json') !== false
);
if($simsAjaxRequest){
	ini_set('display_errors', '0');
	ob_start();
	$GLOBALS['sims_json_response_sent'] = false;
	register_shutdown_function(static function(): void {
		$error = error_get_last();
		$fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
		if(!empty($GLOBALS['sims_json_response_sent'])){
			return;
		}
		while(ob_get_level() > 0){
			ob_end_clean();
		}
		http_response_code(500);
		header('Content-Type: application/json; charset=utf-8');
		$message = !empty($error) && in_array((int)($error['type'] ?? 0), $fatalTypes, true)
			? 'SIM request failed. Please try again.'
			: 'SIM request ended without a JSON response.';
		echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
	});
	set_exception_handler(static function(\Throwable $exception): void {
		if(function_exists('sims_json_response')){
			sims_json_response(['success' => false, 'message' => 'SIM request failed. Please try again.'], 500);
		}
	});
}


/** check if all tables exist **/
$db = new \Core\Database;
$tables = get_value()['tables'];

if(!$db->table_exists($tables)){
	if($simsAjaxRequest){
		sims_json_response(['success' => false, 'message' => 'SIM records are not available.'], 503);
	}
	dd("Missing database tables in ".plugin_id() ." plugin: ". implode(",", $db->missing_tables));
	die;
}

if(!function_exists('sims_runtime_marker_path')){
	function sims_runtime_marker_path(string $name): string
	{
		$dir = __DIR__ . DIRECTORY_SEPARATOR . '.runtime';
		if(!is_dir($dir)){
			@mkdir($dir, 0777, true);
		}
		return $dir . DIRECTORY_SEPARATOR . $name;
	}
}

if(!function_exists('sims_ensure_schema')){
	function sims_ensure_schema(): void
	{
		static $ensured = false;
		if($ensured){
			return;
		}
		$ensured = true;
		$marker = sims_runtime_marker_path('sims_schema_20260427.done');
		if(file_exists($marker)){
			// Schema was checked previously; still verify required columns in case the table was altered or the marker was created prematurely.
		}
		try{
			$db = new \Core\Database;
			$schema = $db->query("SHOW COLUMNS FROM sims");
			if(!$schema || !is_array($schema)){
				return;
			}
			$existing = [];
			foreach($schema as $column){
				$existing[strtolower($column->Field ?? '')] = true;
			}
			$alters = [];
			if(empty($existing['rejection_reason'])){
				$alters[] = "ADD COLUMN rejection_reason TEXT NULL AFTER transaction_status";
			}
			if(empty($existing['image'])){
				$alters[] = "ADD COLUMN image VARCHAR(1024) NULL AFTER buyer_phone";
			}
			if(empty($existing['denos'])){
				$alters[] = "ADD COLUMN denos LONGTEXT NULL AFTER commission";
			}
			if(!empty($alters)){
				$db->query("ALTER TABLE sims " . implode(',', $alters));
			}
			@file_put_contents($marker, '1');
		}catch(\Throwable $e){
			return;
		}
	}
}

sims_ensure_schema();

if(!function_exists('sims_user_is_general_manager')){
	function sims_user_is_general_manager($session = null): bool
	{
		$session = $session ?: get_session();
		$roles = [];
		if (!empty($session->roles) && is_array($session->roles)) {
			$roles = $session->roles;
		}
		$normalized = [];
		foreach ($roles as $role) {
			$normalized[] = strtolower(trim((string)$role));
		}
		return (bool)array_intersect($normalized, ['general manager', 'general_manager', 'generalmanager', 'gm', 'is_general_manager']);
	}
}

if(!function_exists('sims_user_can_view_all')){
	function sims_user_can_view_all($session = null): bool
	{
		return user_can('is_admin') || sims_user_is_general_manager($session);
	}
}


/** set sim permissions for this plugin **/
add_filter('permissions',function($permissions){

	$permissions[] = 'view_operations';
	$permissions[] = 'view_sim';
	$permissions[] = 'view_sim_details';
	$permissions[] = 'add_sim';
	$permissions[] = 'sell_sim';
	$permissions[] = 'edit_sim';
	$permissions[] = 'delete_sim';
	// Trainings module permissions (Operations > Trainings)
	$permissions[] = 'view_trainings';
	$permissions[] = 'manage_trainings';

	return $permissions;
});

add_filter('user_permissions', function($permissions){
    try {
        $session = get_session();
        $user_id = (int)($session->id ?? 0);
        if ($user_id > 0) {
            $db = new \Core\Database;
            if ($db->table_exists(['sims'])) {
                $rows = $db->query(
                    "SELECT COUNT(id) AS c FROM sims WHERE (deleted IS NULL OR deleted = 0) AND LOWER(TRIM(COALESCE(transaction_status,''))) NOT IN ('sold - retail','sold - wholesale','sold') AND (created_by = :uid OR assigned = :uid)",
                    ['uid' => $user_id]
                ) ?: [];
                if (!empty($rows[0]->c) && (int)($rows[0]->c) > 0) {
                    $permissions[] = 'view_sim';
                    $permissions[] = 'view_sim_details';
                }
            }
        }
    } catch (\Throwable $e) {
        // ignore permission fallback failure
    }

    return array_values(array_unique($permissions));
});


/** trainings controller: handle create/update via /admin/trainings POST
 *
 * Important: this must NOT intercept attendance/delete/report posts, which
 * have their own handlers in the main content routing. We therefore only
 * run this controller for the base /trainings endpoint and the explicit
 * add/edit routes.
 */
add_action('controller', function(){
	$req = new \Core\Request;
	if (URL(1) !== 'trainings' || !$req->posted()) {
		return;
	}

	$segment2 = URL(2) ?? '';
	// Only handle base, add, and edit posts here
	if (!in_array($segment2, ['', 'add', 'edit'], true)) {
		return;
	}

	$vars = get_value();
	$admin_route = $vars['admin_route'] ?? 'admin';

	// Allow Operations viewers/managers to manage trainings
	if (!user_can('manage_trainings') && !user_can('view_operations')) {
		message_fail('You are not allowed to manage trainings');
		redirect("{$admin_route}/trainings");
	}

	if (!csrf_verify($_POST)) {
		message_fail('Form expired. Please refresh and try again');
		redirect("{$admin_route}/trainings");
	}

	$db = new \Core\Database;

	$id = (int)($_POST['id'] ?? 0);

	$title        = trim($_POST['title'] ?? '');
	$type         = trim($_POST['type'] ?? '');
	$status       = trim($_POST['status'] ?? 'planned');
	$date_start   = trim($_POST['date_start'] ?? '');
	$date_end     = trim($_POST['date_end'] ?? '');
	$duration     = (int)($_POST['duration_minutes'] ?? 0);
	$trainer_name = trim($_POST['trainer_name'] ?? '');
	$mode         = trim($_POST['mode'] ?? 'in_person');
	$location     = trim($_POST['location'] ?? '');
	$target_team  = trim($_POST['target_team'] ?? '');
	$max_capacity = (int)($_POST['max_capacity'] ?? 0);
	$attendees    = (int)($_POST['attendees_count'] ?? 0);
	$cost         = (float)($_POST['cost'] ?? 0);
	$score        = (float)($_POST['score_rating'] ?? 0);
	$attachments  = trim($_POST['attachments'] ?? '');
	$notes        = trim($_POST['notes'] ?? '');

	$user = get_session();
	$now  = date('Y-m-d H:i:s');

	$columns = [
		'title'            => $title,
		'type'             => $type,
		'status'           => $status,
		'date_start'       => $date_start ?: null,
		'date_end'         => $date_end ?: null,
		'duration_minutes' => $duration ?: null,
		'trainer_name'     => $trainer_name,
		'mode'             => $mode,
		'location'         => $location,
		'target_team'      => $target_team,
		'max_capacity'     => $max_capacity ?: null,
		'attendees_count'  => $attendees ?: 0,
		'cost'             => $cost ?: 0,
		'score_rating'     => $score ?: 0,
		'attachments'      => $attachments,
		'notes'            => $notes,
	];

	if ($id > 0) {
		// Update existing training
		$columns['updated_by']   = $user->id ?? null;
		$columns['date_updated'] = $now;
		$columns['id']           = $id;

		$sql = "UPDATE trainings SET
			title = :title,
			type = :type,
			status = :status,
			date_start = :date_start,
			date_end = :date_end,
			duration_minutes = :duration_minutes,
			trainer_name = :trainer_name,
			mode = :mode,
			location = :location,
			target_team = :target_team,
			max_capacity = :max_capacity,
			attendees_count = :attendees_count,
			cost = :cost,
			score_rating = :score_rating,
			attachments = :attachments,
			notes = :notes,
			updated_by = :updated_by,
			date_updated = :date_updated
			WHERE id = :id";

		$db->query($sql, $columns);
		message_success('Training updated successfully');
	} else {
		// Insert new training
		$columns['created_by']   = $user->id ?? null;
		$columns['date_created'] = $now;

		$sql = "INSERT INTO trainings (
			title,
			type,
			status,
			date_start,
			date_end,
			duration_minutes,
			trainer_name,
			mode,
			location,
			target_team,
			max_capacity,
			attendees_count,
			cost,
			score_rating,
			attachments,
			notes,
			created_by,
			date_created
		) VALUES (
			:title,
			:type,
			:status,
			:date_start,
			:date_end,
			:duration_minutes,
			:trainer_name,
			:mode,
			:location,
			:target_team,
			:max_capacity,
			:attendees_count,
			:cost,
			:score_rating,
			:attachments,
			:notes,
			:created_by,
			:date_created
		)";

		$db->query($sql, $columns);
		message_success('Training created successfully');
	}

	redirect("{$admin_route}/trainings");
});


/** trainings main content: render /admin/trainings and /admin/trainings/add **/
add_action('basic-admin_main_content', function(){
	if (URL(1) !== 'trainings') {
		return;
	}

	// Permissions: reuse Operations visibility
	if (!user_can('view_trainings') && !user_can('view_operations')) {
		echo "<div class='alert alert-danger'>You are not allowed to view trainings.</div>";
		return;
	}

	$vars = get_value();
	$admin_route = $vars['admin_route'] ?? 'admin';
	$trainings_add_url = ROOT . "/{$admin_route}/trainings/add";
	$trainings_base = ROOT . "/{$admin_route}/trainings";

	$db = new \Core\Database;

	// Route: /admin/trainings/add
	if (URL(2) === 'add') {
		require ROOTPATH . 'plugins/trainings/views/admin/add.php';
		return;
	}

	// Route: /admin/trainings/edit/{id}
	if (URL(2) === 'edit') {
		$id = (int)(URL(3) ?? 0);
		if ($id <= 0) {
			message_fail('Invalid training ID');
			redirect("{$admin_route}/trainings");
		}
		$training = $db->get_row("SELECT * FROM trainings WHERE id = :id AND deleted = 0 LIMIT 1", ['id' => $id]);
		if (!$training) {
			message_fail('Training not found');
			redirect("{$admin_route}/trainings");
		}
		require ROOTPATH . 'plugins/trainings/views/admin/edit.php';
		return;
	}

	// Route: /admin/trainings/view/{id}
	if (URL(2) === 'view') {
		$id = (int)(URL(3) ?? 0);
		if ($id <= 0) {
			message_fail('Invalid training ID');
			redirect("{$admin_route}/trainings");
		}
		$training = $db->get_row("SELECT * FROM trainings WHERE id = :id AND deleted = 0 LIMIT 1", ['id' => $id]);
		if (!$training) {
			message_fail('Training not found');
			redirect("{$admin_route}/trainings");
		}
		require ROOTPATH . 'plugins/trainings/views/admin/view.php';
		return;
	}

	// Route: /admin/trainings/delete/{id}
	if (URL(2) === 'delete') {
		$id = (int)(URL(3) ?? 0);
		if ($id <= 0) {
			message_fail('Invalid training ID');
			redirect("{$admin_route}/trainings");
		}
		$training = $db->get_row("SELECT * FROM trainings WHERE id = :id AND deleted = 0 LIMIT 1", ['id' => $id]);
		if (!$training) {
			message_fail('Training not found');
			redirect("{$admin_route}/trainings");
		}
		// Handle POST delete
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'delete' && csrf_verify($_POST)) {
			$db->query("UPDATE trainings SET deleted = 1 WHERE id = :id", ['id' => $id]);
			message_success('Training deleted');
			redirect("{$admin_route}/trainings");
		}
		require ROOTPATH . 'plugins/trainings/views/admin/delete.php';
		return;
	}

	// Route: /admin/trainings/attendance/{id}
	if (URL(2) === 'attendance') {
		$id = (int)(URL(3) ?? 0);
		if ($id <= 0) {
			message_fail('Invalid training ID');
			redirect("{$admin_route}/trainings");
		}
		$training = $db->get_row("SELECT * FROM trainings WHERE id = :id AND deleted = 0 LIMIT 1", ['id' => $id]);
		if (!$training) {
			message_fail('Training not found');
			redirect("{$admin_route}/trainings");
		}

		// Handle mark/clear attendance (supports normal and AJAX requests)
		$isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			// Use dedicated CSRF key for attendance forms to avoid layout conflicts
			if (!csrf_verify($_POST, 'attendance_csrf')) {
				if ($isAjax) {
					http_response_code(400);
					exit;
				}
				message_fail('Form expired. Please refresh and try again');
				redirect("{$admin_route}/trainings/attendance/{$id}");
			}

			$action = $_POST['_action'] ?? '';
			if ($action === 'mark') {
				$userId = (int)($_POST['user_id'] ?? 0);
				$status = trim($_POST['status'] ?? 'present');
				$notes = trim($_POST['notes'] ?? '');
				if ($userId > 0 && in_array($status, ['present','absent','invited'], true)) {
					// Check if row exists
					$existing = $db->get_row("SELECT * FROM training_attendees WHERE training_id = :tid AND user_id = :uid AND deleted = 0 LIMIT 1", [
						'tid' => $id,
						'uid' => $userId,
					]);
					if ($existing) {
						$db->query("UPDATE training_attendees SET status = :status, notes = :notes, date_updated = NOW() WHERE id = :id", [
							'id' => $existing->id,
							'status' => $status,
							'notes' => $notes,
						]);
					} else {
						$db->query("INSERT INTO training_attendees (training_id, user_id, status, notes, date_created, deleted) VALUES (:tid, :uid, :status, :notes, NOW(), 0)", [
							'tid' => $id,
							'uid' => $userId,
							'status' => $status,
							'notes' => $notes,
						]);
					}
					if (!$isAjax) {
						message_success('Attendance updated');
					}
				}
			} elseif ($action === 'clear') {
				$userId = (int)($_POST['user_id'] ?? 0);
				if ($userId > 0) {
					$db->query("UPDATE training_attendees SET deleted = 1, date_updated = NOW() WHERE training_id = :tid AND user_id = :uid AND deleted = 0", [
						'tid' => $id,
						'uid' => $userId,
					]);
					if (!$isAjax) {
						message_success('Attendance cleared');
					}
				}
			}

			// After any mark/clear, update cached attendees_count on trainings table
			$presentCountRow = $db->get_row("SELECT COUNT(*) AS c FROM training_attendees WHERE training_id = :tid AND deleted = 0 AND status = 'present'", [
				'tid' => $id,
			]);
			$presentCount = (int)($presentCountRow->c ?? 0);
			$db->query("UPDATE trainings SET attendees_count = :c WHERE id = :id", [
				'c'  => $presentCount,
				'id' => $id,
			]);
			if ($isAjax) {
				http_response_code(204); // No Content; frontend will just reload inner section
				exit;
			}
			redirect("{$admin_route}/trainings/attendance/{$id}");
		}

		// Load all users and existing attendance; make sure we always have arrays
		$users = $db->query("SELECT id, first_name, last_name, email FROM users WHERE deleted = 0 ORDER BY first_name, last_name", []) ?: [];
		$attendees = $db->query("SELECT * FROM training_attendees WHERE training_id = :id AND deleted = 0", ['id' => $id]) ?: [];
		require ROOTPATH . 'plugins/trainings/views/admin/attendance.php';
		return;
	}

	// Route: /admin/trainings/report/{id}
	if (URL(2) === 'report') {
		$id = (int)(URL(3) ?? 0);
		if ($id <= 0) {
			message_fail('Invalid training ID');
			redirect("{$admin_route}/trainings");
		}
		$training = $db->get_row("SELECT * FROM trainings WHERE id = :id AND deleted = 0 LIMIT 1", ['id' => $id]);
		if (!$training) {
			message_fail('Training not found');
			redirect("{$admin_route}/trainings");
		}

		// Handle saving meeting report / key points
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'save_report') {
			// Use default CSRF key so it stays in sync with the main admin layout
			if (!csrf_verify($_POST)) {
				message_fail('Form expired. Please refresh and try again');
				redirect("{$admin_route}/trainings/report/{$id}");
			}

			$reportNotes = trim($_POST['report_notes'] ?? '');
			$db->query("UPDATE trainings SET notes = :notes, date_updated = NOW() WHERE id = :id", [
				'notes' => $reportNotes,
				'id'    => $id,
			]);
			message_success('Training report saved');
			redirect("{$admin_route}/trainings/report/{$id}");
		}

		// Normalize attendees result to an array so views can safely call count()/foreach
		$attendees = $db->query("SELECT ta.*, u.first_name, u.last_name, u.email FROM training_attendees ta LEFT JOIN users u ON u.id = ta.user_id WHERE ta.training_id = :id AND ta.deleted = 0 ORDER BY ta.id DESC", ['id' => $id]) ?: [];
		require ROOTPATH . 'plugins/trainings/views/admin/report.php';
		return;
	}

	$status  = trim($_GET['status'] ?? '');
	$type    = trim($_GET['type'] ?? '');
	$mode    = trim($_GET['mode'] ?? '');
	$period  = trim($_GET['period'] ?? 'this_month');
	$q       = trim($_GET['q'] ?? '');

	$where  = "WHERE deleted = 0";
	$params = [];

	if ($status !== '') {
		$where .= " AND status = :status";
		$params['status'] = $status;
	}
	if ($type !== '') {
		$where .= " AND type = :type";
		$params['type'] = $type;
	}
	if ($mode !== '') {
		$where .= " AND mode = :mode";
		$params['mode'] = $mode;
	}
	if ($q !== '') {
		$where .= " AND (title LIKE :q OR trainer_name LIKE :q OR target_team LIKE :q)";
		$params['q'] = '%' . $q . '%';
	}

	$today = date('Y-m-d');
	if ($period === 'this_month') {
		$where .= " AND DATE(date_start) >= :start_month";
		$params['start_month'] = date('Y-m-01');
	} elseif ($period === 'last_3_months') {
		$where .= " AND DATE(date_start) >= :start_3";
		$params['start_3'] = date('Y-m-d', strtotime('-3 months'));
	} elseif ($period === 'this_year') {
		$where .= " AND YEAR(date_start) = :year";
		$params['year'] = date('Y');
	}

	$listSql = "SELECT * FROM trainings {$where} ORDER BY date_start DESC, id DESC LIMIT 100";
	$trainings = $db->query($listSql, $params) ?? [];

	$kpiSql = "SELECT 
					COUNT(*) AS total_trainings,
					SUM(CASE WHEN date_start >= :today THEN 1 ELSE 0 END) AS upcoming,
					SUM(attendees_count) AS total_attendees,
					AVG(NULLIF(score_rating,0)) AS avg_score
				FROM trainings {$where}";

	$kpiParams = array_merge($params, ['today' => $today]);
	$kpiRows = $db->query($kpiSql, $kpiParams) ?? [];
	$kpi = (object) [
		'total_trainings' => 0,
		'upcoming'        => 0,
		'total_attendees' => 0,
		'avg_score'       => 0,
	];
	if (!empty($kpiRows)) {
		$row = $kpiRows[0];
		$kpi->total_trainings = (int)($row->total_trainings ?? 0);
		$kpi->upcoming        = (int)($row->upcoming ?? 0);
		$kpi->total_attendees = (int)($row->total_attendees ?? 0);
		$kpi->avg_score       = (float)($row->avg_score ?? 0);
	}

	// Trainings list view
	require ROOTPATH . 'plugins/trainings/views/admin/list.php';
});


/** lightweight AJAX validation endpoint for SIM identifiers **/
add_action('controller',function(){

	$req = new \Core\Request;

	if(!$req->posted()){
		return;
	}

	$inputData = json_decode(file_get_contents("php://input"), true);
	if(empty($inputData) || !is_array($inputData)){
		return;
	}

	$action = $inputData['action'] ?? '';
	if($action !== 'validate-sim-identifiers'){
		return;
	}

	if(!csrf_verify($inputData ?? [])){
		sims_json_response(['success' => false, 'message' => 'Form expired. Refresh and try again.'], 400);
	}

	if(!user_can('add_sim')){
		sims_json_response(['success' => false, 'message' => 'Unauthorized'], 403);
	}

	$network = strtolower(trim($inputData['network'] ?? ''));
	$entries = $inputData['entries'] ?? [];
	if(empty($network) || empty($entries) || !is_array($entries)){
		sims_json_response(['success' => false, 'message' => 'Missing identifiers for validation.'], 400);
	}

	$db = new \Core\Database;
	$results = [];

	foreach($entries as $entry){
		$key = $entry['key'] ?? uniqid('entry_', true);
		$serial = trim($entry['serial'] ?? '');
		$puk = trim($entry['puk'] ?? '');
		$serialExists = false;
		$pukExists = false;

		if($serial !== ''){
			$serialExists = !empty($db->query(
				"SELECT id FROM sims WHERE network = :network AND serial_number = :serial LIMIT 1",
				['network' => $network, 'serial' => $serial]
			));
		}

		if($puk !== ''){
			$pukExists = !empty($db->query(
				"SELECT id FROM sims WHERE network = :network AND puk = :puk LIMIT 1",
				['network' => $network, 'puk' => $puk]
			));
		}

		$results[] = [
			'key' => $key,
			'serialExists' => (bool)$serialExists,
			'pukExists' => (bool)$pukExists,
		];
	}

sims_json_response(['success' => true, 'results' => $results]);
});


/** add to amin links **/
/*add_filter('basic-admin_before_admin_links',function($links){

$db = new \Core\Database;
	
		$vars = get_value();

	if (user_can('view_operations')) {
	    $obj = (object)[];
	    $obj->title = 'Operations';
	    $obj->id = 'view_operations';
	    $obj->link = '#';
	    $obj->icon = 'fas fa-cogs'; // Updated main icon for "Operations"
	    $obj->parent = 0;

	    $obj->sublinks = []; // Initialize sublinks array within the main link object

	    // Add sublinks in ascending order by title
	    $sub = (object)[];
	    $sub->title = 'Accounts';
	    $sub->link = ROOT . '/' . $vars['admin_route'] . '/operations_accounts';
	    $sub->icon = 'fas fa-briefcase'; // Briefcase for administrative tasks
	    $sub->parent = $obj->id;
	    $sub->id = 'accounts';
	    $obj->sublinks[] = $sub;

	    // Add sublinks in ascending order by title
	    $sub = (object)[];
	    $sub->title = 'Attendance';
	    $sub->link = ROOT . '/' . $vars['admin_route'] . '/attendance';
	    $sub->icon = 'fa fa-clipboard-check'; // Briefcase for administrative tasks
	    $sub->parent = $obj->id;
	    $sub->id = 'accounts';
	    $obj->sublinks[] = $sub;

	    $sub = (object)[];
	    $sub->title = 'Error Reports';
	    $sub->link = ROOT . '/' . $vars['admin_route'] . '/error_reports';
	    $sub->icon = 'fas fa-exclamation-circle'; // Warning icon for errors
	    $sub->parent = $obj->id;
	    $sub->id = 'error_reports';
	    $obj->sublinks[] = $sub;

	    $sub = (object)[];
	    $sub->title = 'Performance';
	    $sub->link = ROOT . '/' . $vars['admin_route'] . '/performance';
	    $sub->icon = 'fas fa-chart-line'; // Line chart for performance
	    $sub->parent = $obj->id;
	    $sub->id = 'performance';
	    $obj->sublinks[] = $sub;

	    $sub = (object)[];
	    $sub->title = 'Trainings';
	    $sub->link = ROOT . '/' . $vars['admin_route'] . '/trainings';
	    $sub->icon = 'fas fa-chalkboard-teacher'; // Training/education icon
	    $sub->parent = $obj->id;
	    $sub->id = 'trainings';
	    $obj->sublinks[] = $sub;

	    // $sub = (object)[];
	    // $sub->title = 'Wallets';
	    // $sub->link = ROOT . '/' . $vars['admin_route'] . '/operations_wallet';
	    // $sub->icon = 'fas fa-wallet'; // Wallet for finances
	    // $sub->parent = $obj->id;
	    // $sub->id = 'view_wallet';
	    // $obj->sublinks[] = $sub;

	    $links[] = $obj;
	}


	return $links;

});*/


/** run this after a form submit **/
add_action('controller',function(){

	$req = new \Core\Request;
	$vars = get_value();
	
	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];

	if(URL(1) == $vars['plugin_route'] && $req->posted())
	{
		$ses = new \Core\Session;
		$sim = new \Sims\Sim;
		$transaction = new \Sims\Transaction;
		$wallet = new \Sims\Wallet;
		$sim_roles_map = new \Sims\User_roles_map;

		$id = URL(3) ?? null;
		if($id)
			$row = $sim->first(['id'=>$id]);

		if(URL(2) == 'add'){
			require plugin_path('controllers/add-controller.php');
		}else
		if(URL(2) == 'sell'){
			require plugin_path('controllers/sell-controller.php');
		}else
		if(URL(2) == 'bulk-sell'){
			require plugin_path('controllers/bulk_sell-controller.php');
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

	$sim = new \Sims\Sim;
	$transaction = new \Sims\Transaction;
	$wallet = new \Sims\Wallet;	
	$mywallet = new \Sims\Mywallet;	
	$user = new \Sims\User;	
	$users = $user->where(['deleted'=>0]);

	if(URL(1) == $vars['plugin_route']){

		$id = URL(3) ?? null;
		//session data
		$session = get_session();
		//dd($session);
		if(!empty($session->id)){
			$username = $session->first_name . ' '. $session->last_name;
			$user_id = $session->id;
		}
		$canViewAllSims = sims_user_can_view_all($session);

		$wallets = $wallet->where(['deleted'=>0, 'transaction_status'=>'Approved']);
		$mywallets = $mywallet->where(['deleted'=>0, 'transaction_status'=>'Approved', 'assigned'=>$user_id]);

		if($id){
			$sim::$query_id = 'get-sim';
			if($canViewAllSims){
				$row = $sim->first(['id'=>$id]);
			}else{
				$scopeRows = $sim->query(
					"SELECT * FROM sims WHERE id = :id AND (assigned = :user_id OR created_by = :user_id) LIMIT 1",
					['id' => $id, 'user_id' => $user_id]
				);
				$row = !empty($scopeRows) ? $scopeRows[0] : null;
			}
		}

		if(URL(2) == 'add'){
			$sim_role = new \Sims\User_role;
			$identifier_errors = $vars['identifier_errors'] ?? [];
			require plugin_path('views/admin/add.php');
		}else
		if(URL(2) == 'sell'){
			$sim_role = new \Sims\User_role;
			$query = "SELECT * FROM sims 
          WHERE (assigned = :user_id OR created_by = :user_id) 
            AND (transaction_status <> :transaction_status1 AND transaction_status <> :transaction_status2)";

			$params = [
			    'user_id' => $user_id,
			    'transaction_status1' => 'Sold - retail',
			    'transaction_status2' => 'Sold - wholesale',
			];

			$sims = $sim->query($query, $params);
			//dd($sims);

			require plugin_path('views/admin/sell.php');
		}else
		if(URL(2) == 'edit'){			
			$sim_role = new \Sims\User_role;
			require plugin_path('views/admin/edit.php');
		}else
		if(URL(2) == 'delete'){
			require plugin_path('views/admin/delete.php');
		}else
		if(URL(2) == 'view'){
			if(empty($row)){
				message_fail('Sim not found or access denied.');
				redirect("{$vars['admin_route']}/{$vars['plugin_route']}");
			}

			$viewScopeSql = $canViewAllSims ? '' : ' AND (m.assigned = :scope_user_id OR m.created_by = :scope_user_id)';
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
			        out_wallet.name AS out_wallet_name ,
			        out_wallet.network AS out_wallet_network, 
			        out_wallet.wallet_number AS out_wallet_number,
			       in_wallet.name AS in_wallet_name ,
			       in_wallet.network AS in_wallet_network, 
			       in_wallet.wallet_number AS in_wallet_number 
			    FROM sims AS m 
			    LEFT JOIN users AS u ON m.created_by = u.id 
			    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			    LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id 
			    LEFT JOIN wallets AS out_wallet ON m.wallet_out_id = out_wallet.id 
			    LEFT JOIN wallets AS in_wallet ON m.wallet_in_id = in_wallet.id 
			    WHERE m.id = :id {$viewScopeSql}
			    LIMIT 1";

				$viewParams = ['id'=>$id];
				if(!$canViewAllSims){
					$viewParams['scope_user_id'] = $user_id;
				}
				$viewRows = $sim->query($query, $viewParams);
				$row = !empty($viewRows) ? $viewRows[0] : null;
				if(empty($row)){
					message_fail('Sim not found or access denied.');
					redirect("{$vars['admin_route']}/{$vars['plugin_route']}");
				}
			require plugin_path('views/admin/view.php');
		}else
		if(URL(2) == 'exportList_csv'){
			$sim = new \Sims\Sim;
			$sim::$query_id = 'get-sim';
			$limit = 10000;
			$offset = 0;
			$rows = [];
			$total_amount = 0;
			$scopeSql = $canViewAllSims ? '' : ' AND (m.assigned = :scope_user_id OR m.created_by = :scope_user_id)';
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
					FROM sims AS m 
					LEFT JOIN users AS u ON m.created_by = u.id 
					LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
					LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id 
					WHERE (
						m.network LIKE :find 
						OR m.id LIKE :find 
						OR m.puk LIKE :find 
						OR m.serial_number LIKE :find 
						OR m.transaction_status LIKE :find
						OR m.rejection_reason LIKE :find
						OR u.first_name LIKE :find 
						OR u.last_name LIKE :find 
						OR u_assigned.first_name LIKE :find 
						OR u_assigned.last_name LIKE :find 
						OR m.date_created LIKE :find){$scopeSql}
					ORDER BY m.id DESC LIMIT $limit OFFSET $offset";
				$params = ['find' => $find];
				if(!$canViewAllSims){
					$params['scope_user_id'] = $user_id;
				}
				$rows = $sim->query($query, $params) ?: [];
			} else {
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
					FROM sims AS m 
					LEFT JOIN users AS u ON m.created_by = u.id 
					LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
					LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id 
					" . (!$canViewAllSims ? "WHERE (m.assigned = :scope_user_id OR m.created_by = :scope_user_id) " : "") . "
					ORDER BY m.id DESC LIMIT $limit OFFSET $offset";
				$params = !$canViewAllSims ? ['scope_user_id' => $user_id] : [];
				$rows = $sim->query($query, $params) ?: [];
			}
			if (!empty($rows)) {
				foreach ($rows as $r) {
					$total_amount += (float)($r->amount ?? 0);
				}
			}
			require plugin_path('views/admin/exportList_csv.php');
		}else
		if(URL(2) == 'exportList_pdf'){
			$sim = new \Sims\Sim;
			$sim::$query_id = 'get-sim';
			$limit = 1000;
			$offset = 0;
			$rows = [];
			$total_amount = 0;
			$scopeSql = $canViewAllSims ? '' : ' AND (m.assigned = :scope_user_id OR m.created_by = :scope_user_id)';
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
					FROM sims AS m 
					LEFT JOIN users AS u ON m.created_by = u.id 
					LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
					LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id 
					WHERE (
						m.network LIKE :find 
						OR m.id LIKE :find 
						OR m.puk LIKE :find 
						OR m.serial_number LIKE :find 
						OR m.transaction_status LIKE :find
						OR m.rejection_reason LIKE :find
						OR u.first_name LIKE :find 
						OR u.last_name LIKE :find 
						OR u_assigned.first_name LIKE :find 
						OR u_assigned.last_name LIKE :find 
						OR m.date_created LIKE :find){$scopeSql}
					ORDER BY m.id DESC LIMIT $limit OFFSET $offset";
				$params = ['find' => $find];
				if(!$canViewAllSims){
					$params['scope_user_id'] = $user_id;
				}
				$rows = $sim->query($query, $params) ?: [];
			} else {
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
					FROM sims AS m 
					LEFT JOIN users AS u ON m.created_by = u.id 
					LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
					LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id 
					" . (!$canViewAllSims ? "WHERE (m.assigned = :scope_user_id OR m.created_by = :scope_user_id) " : "") . "
					ORDER BY m.id DESC LIMIT $limit OFFSET $offset";
				$params = !$canViewAllSims ? ['scope_user_id' => $user_id] : [];
				$rows = $sim->query($query, $params) ?: [];
			}
			if (!empty($rows)) {
				foreach ($rows as $r) {
					$total_amount += (float)($r->amount ?? 0);
				}
			}
			require plugin_path('views/admin/exportList_pdf.php');
		}else
		{
			$sim = new \Sims\Sim;
			$sim_roles_map = new \Sims\User_roles_map;
			$limit = (int)($_GET['limit'] ?? 10);
			if (!in_array($limit, [5, 10, 25, 50, 100], true)) {
				$limit = 10;
			}
			$pager = new \Core\Pager($limit);
			$offset = $pager->offset;

			$sim->limit = $limit;
			$sim->offset = $offset;
			$sim::$query_id = 'get-sim';
			$simScopeSql = $canViewAllSims ? '' : ' AND (m.assigned = :scope_user_id OR m.created_by = :scope_user_id)';
			$simScopeParams = $canViewAllSims ? [] : ['scope_user_id' => $user_id];
			$simScopeSqlPlain = $canViewAllSims ? '' : ' AND (assigned = :scope_user_id OR created_by = :scope_user_id)';
			$simScopeSqlSales = $canViewAllSims ? '' : ' AND (s.assigned = :scope_user_id OR s.created_by = :scope_user_id)';

				$distinctNetworks = $sim->query("SELECT DISTINCT network FROM sims WHERE deleted = 0 ORDER BY network ASC") ?: [];
				$distinctTypes = $sim->query("SELECT DISTINCT type FROM sims WHERE deleted = 0 ORDER BY type ASC") ?: [];

				$find = trim($_GET['find'] ?? '');
				$status = trim($_GET['status'] ?? '');
				$createdBy = trim($_GET['created_by'] ?? '');
				$buyerPhone = trim($_GET['buyer_phone'] ?? '');
				$networkFilter = trim($_GET['network'] ?? '');
				$typeFilter = trim($_GET['type'] ?? '');
				$dateStart = trim($_GET['date_start'] ?? '');
				$dateEnd = trim($_GET['date_end'] ?? '');

				$filterSql = '';
				$filterParams = $simScopeParams;

				if ($find !== '') {
					$filterSql .= " AND (
						m.network LIKE :find 
						OR m.id LIKE :find 
						OR m.puk LIKE :find 
						OR m.serial_number LIKE :find 
						OR m.transaction_status LIKE :find
						OR m.rejection_reason LIKE :find
						OR u.first_name LIKE :find 
						OR u.last_name LIKE :find 
						OR u_assigned.first_name LIKE :find 
						OR u_assigned.last_name LIKE :find 
						OR m.date_created LIKE :find
					)";
					$filterParams['find'] = '%' . $find . '%';
				}
				if ($status !== '') {
					$filterSql .= ' AND m.transaction_status = :transaction_status';
					$filterParams['transaction_status'] = $status;
				}
				if ($createdBy !== '') {
					$filterSql .= ' AND m.created_by = :created_by';
					$filterParams['created_by'] = $createdBy;
				}
				if ($buyerPhone !== '') {
					$filterSql .= ' AND m.buyer_phone LIKE :buyer_phone';
					$filterParams['buyer_phone'] = '%' . $buyerPhone . '%';
				}
				if ($networkFilter !== '') {
					$filterSql .= ' AND m.network = :network';
					$filterParams['network'] = $networkFilter;
				}
				if ($typeFilter !== '') {
					$filterSql .= ' AND m.type = :type';
					$filterParams['type'] = $typeFilter;
				}
				if ($dateStart !== '' && strtotime($dateStart) !== false) {
					$filterSql .= ' AND m.date_created >= :date_start';
					$filterParams['date_start'] = date('Y-m-d 00:00:00', strtotime($dateStart));
				}
				if ($dateEnd !== '' && strtotime($dateEnd) !== false) {
					$filterSql .= ' AND m.date_created <= :date_end';
					$filterParams['date_end'] = date('Y-m-d 23:59:59', strtotime($dateEnd));
				}

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
				    FROM sims AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
				    LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id 
				    WHERE 1=1 {$simScopeSql} {$filterSql}
				    ORDER BY m.id DESC LIMIT $limit OFFSET $offset
				";
				$countQuery = "
					SELECT COUNT(m.id) AS total_rows
					FROM sims AS m
					LEFT JOIN users AS u ON m.created_by = u.id
					LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id
					LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id
					WHERE 1=1 {$simScopeSql} {$filterSql}
				";
				$countRows = $sim->query($countQuery, $filterParams) ?: [];
				$totalRows = (int)($countRows[0]->total_rows ?? 0);
				$pager->total_pages = max(1, (int)ceil($totalRows / $limit));
				if($pager->page_number > $pager->total_pages){
					$pager->page_number = $pager->total_pages;
					$pager->offset = ($pager->page_number - 1) * $limit;
					$offset = $pager->offset;
					$query = preg_replace('/LIMIT\s+\d+\s+OFFSET\s+\d+/', 'LIMIT ' . $limit . ' OFFSET ' . $offset, $query);
				}

				$rows = $sim->query($query, $filterParams) ?: [];
				$notificationPillColorConfig = function_exists('admin_settings_notification_pill_color_config')
				? admin_settings_notification_pill_color_config()
				: ['serious' => '#dc3545', 'mild' => '#ffc107', 'regular' => '#0d6efd'];
			$simPillColor = $notificationPillColorConfig['mild'] ?? $notificationPillColorConfig['regular'] ?? '#f59e0b';
			$simHighlightColor = $simPillColor;
			if (preg_match('/^#?([a-f0-9]{3}|[a-f0-9]{6})$/i', (string) $simPillColor, $match)) {
				$hex = ltrim($match[1], '#');
				if (strlen($hex) === 3) {
					$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
				}
				$simHighlightColor = sprintf(
					'rgba(%d, %d, %d, 0.24)',
					hexdec(substr($hex, 0, 2)),
					hexdec(substr($hex, 2, 2)),
					hexdec(substr($hex, 4, 2))
				);
			}
		// Calculate networks data with applied filters (before AJAX check for dynamic updates)
		// Build filter conditions for simple queries without table aliases
		// For networks display, we want: Total count vs Sold count
		// Total should include all statuses, Sold should only include sold statuses
		
		$networkFilterSql = '';
		$networkFilterSqlWithStatus = '';
		$networkFilterParams = $simScopeParams;
		
		// For find filter: exclude transaction_status from total count (only apply to sold)
		if ($find !== '') {
			$networkFilterSql .= " AND (network LIKE :find OR id LIKE :find OR puk LIKE :find OR serial_number LIKE :find OR rejection_reason LIKE :find)";
			// For sold count, also search in transaction_status
			$networkFilterSqlWithStatus .= " AND (network LIKE :find OR id LIKE :find OR puk LIKE :find OR serial_number LIKE :find OR transaction_status LIKE :find OR rejection_reason LIKE :find)";
			$networkFilterParams['find'] = '%' . $find . '%';
		}
		if ($buyerPhone !== '') {
			$networkFilterSql .= ' AND buyer_phone LIKE :buyer_phone';
			$networkFilterSqlWithStatus .= ' AND buyer_phone LIKE :buyer_phone';
			$networkFilterParams['buyer_phone'] = '%' . $buyerPhone . '%';
		}
		if ($networkFilter !== '') {
			$networkFilterSql .= ' AND network = :network';
			$networkFilterSqlWithStatus .= ' AND network = :network';
			$networkFilterParams['network'] = $networkFilter;
		}
		if ($typeFilter !== '') {
			$networkFilterSql .= ' AND type = :type';
			$networkFilterSqlWithStatus .= ' AND type = :type';
			$networkFilterParams['type'] = $typeFilter;
		}
		if ($dateStart !== '' && strtotime($dateStart) !== false) {
			$networkFilterSql .= ' AND date_created >= :date_start';
			$networkFilterSqlWithStatus .= ' AND date_created >= :date_start';
			$networkFilterParams['date_start'] = date('Y-m-d 00:00:00', strtotime($dateStart));
		}
		if ($dateEnd !== '' && strtotime($dateEnd) !== false) {
			$networkFilterSql .= ' AND date_created <= :date_end';
			$networkFilterSqlWithStatus .= ' AND date_created <= :date_end';
			$networkFilterParams['date_end'] = date('Y-m-d 23:59:59', strtotime($dateEnd));
		}
		
		// Query for total SIMs by network (includes all statuses for meaningful total count)
		$scopeCondition = $canViewAllSims ? '' : ' AND (assigned = :scope_user_id OR created_by = :scope_user_id)';
		$totalQuery = "SELECT network, COUNT(id) AS total_count FROM sims WHERE deleted = 0{$scopeCondition}{$networkFilterSql} GROUP BY network";
		$total_countSim = $sim->query($totalQuery, $networkFilterParams);

		// Query for sold SIMs by network (only Sold statuses)
		$scopeCondition = $canViewAllSims ? '' : ' AND (assigned = :scope_user_id OR created_by = :scope_user_id)';
		$soldQuery = "SELECT network, COUNT(id) AS total_count FROM sims WHERE deleted = 0{$scopeCondition} AND (transaction_status = :sold_status1 OR transaction_status = :sold_status2){$networkFilterSqlWithStatus} GROUP BY network";
		$totalCountSoldParams = array_merge(
		    [
		        'sold_status1' => 'Sold - retail',
		        'sold_status2' => 'Sold - wholesale'
		    ],
		    $networkFilterParams
		);
		$total_countSold = $sim->query($soldQuery, $totalCountSoldParams);

		// Merge results for display - initialize both total and sold for all networks
		$networks = [];
		if (!empty($total_countSim)) {
		    foreach ($total_countSim as $row) {
		        $networks[$row->network] = [
		            'total' => $row->total_count,
		            'sold' => 0
		        ];
		    }
		}

		if (!empty($total_countSold)) {
		    foreach ($total_countSold as $row) {
		        if (!isset($networks[$row->network])) {
		            $networks[$row->network] = ['total' => 0, 'sold' => 0];
		        }
		        $networks[$row->network]['sold'] = $row->total_count;
		    }
		}

		if (!empty($_GET['__ajax']) || !empty($_GET['ajax'])) {
				ob_start();
				require plugin_path('views/admin/list-table-body.php');
				$rowsHtml = ob_get_clean();
				ob_start();
				echo sims_render_pager($pager);
				$pagerHtml = ob_get_clean();
				sims_json_response([
					'rows' => $rowsHtml, 
					'pager' => $pagerHtml,
					'networks' => $networks
				]);
			}




			//for superior ranks
			$query = "SELECT SUM(amount) AS total_amount, SUM(commission) AS total_commission FROM sims WHERE deleted = 0 AND transaction_status = :transaction_status{$simScopeSqlPlain}";
			$total_unassigned = $sim->query($query, array_merge(['transaction_status'=>'Unassigned'], $simScopeParams));
			if (!empty($total_unassigned)) {			
				$totalUnassigned_amount = $total_unassigned[0]->total_amount ?? 0;
				$totalUnassigned_commission = $total_unassigned[0]->total_commission ?? 0;
			}

			$total_assigned = $sim->query($query, array_merge(['transaction_status'=>'Assigned'], $simScopeParams));
			if (!empty($total_assigned)) {
				$totalAssigned_amount = $total_assigned[0]->total_amount ?? 0;
				$totalAssigned_commission = $total_assigned[0]->total_commission ?? 0;
			}

			$total_soldWholesale = $sim->query($query, array_merge(['transaction_status'=>'Sold - wholesale'], $simScopeParams));
			if (!empty($total_soldWholesale)) {				
				$totalSoldWholesale_amount = $total_soldWholesale[0]->total_amount ?? 0;
				$totalSoldWholesale_commission = $total_soldWholesale[0]->total_commission ?? 0;
			}

			$total_soldRetail = $sim->query($query, array_merge(['transaction_status'=>'Sold - retail'], $simScopeParams));
			if (!empty($total_soldRetail)) {
				$totalSoldRetail_amount = $total_soldRetail[0]->total_amount ?? 0;
				$totalSoldRetail_commission = $total_soldRetail[0]->total_commission ?? 0;
			}

			$total_expired = $sim->query($query, array_merge(['transaction_status'=>'Expired'], $simScopeParams));
			if (!empty($total_expired)) {
				$totalExpired_amount = $total_expired[0]->total_amount ?? 0;
				$totalExpired_commission = $total_expired[0]->total_commission ?? 0;
			}
			
			$query = "SELECT SUM(amount) AS total_amount, SUM(commission) AS total_commission FROM sims WHERE deleted = 0{$simScopeSqlPlain}";
			$total = $sim->query($query, $simScopeParams);
			if (!empty($total)) {
				$total_amount = $total[0]->total_amount ?? 0;
				$total_commission = $total[0]->total_commission ?? 0;
			}

			// advanced analytics datasets for landing dashboard
			$analyticsStatuses = "('Sold - retail','Sold - wholesale')";
			$today = date('Y-m-d');
			$weekStart = date('Y-m-d', strtotime('-6 days'));
			$soldStats = (object)[
				'sold_count_total' => 0,
				'sold_count_today' => 0,
				'sold_count_week' => 0,
				'sold_amount_total' => 0,
				'sold_amount_today' => 0,
				'sold_amount_week' => 0,
				'sold_commission_total' => 0,
				'sold_commission_week' => 0,
				'retail_count_total' => 0,
				'wholesale_count_total' => 0,
			];

			$soldStatsQuery = "
				SELECT
					COUNT(*) AS sold_count_total,
					SUM(amount) AS sold_amount_total,
					SUM(CASE WHEN DATE(COALESCE(date_updated, date_created)) = :today THEN 1 ELSE 0 END) AS sold_count_today,
					SUM(CASE WHEN DATE(COALESCE(date_updated, date_created)) >= :week_start THEN 1 ELSE 0 END) AS sold_count_week,
					SUM(CASE WHEN DATE(COALESCE(date_updated, date_created)) = :today THEN amount ELSE 0 END) AS sold_amount_today,
					SUM(CASE WHEN DATE(COALESCE(date_updated, date_created)) >= :week_start THEN amount ELSE 0 END) AS sold_amount_week,
					SUM(commission) AS sold_commission_total,
					SUM(CASE WHEN DATE(COALESCE(date_updated, date_created)) >= :week_start THEN commission ELSE 0 END) AS sold_commission_week,
					SUM(CASE WHEN transaction_status = 'Sold - retail' THEN 1 ELSE 0 END) AS retail_count_total,
					SUM(CASE WHEN transaction_status = 'Sold - wholesale' THEN 1 ELSE 0 END) AS wholesale_count_total
				FROM sims
				WHERE deleted = 0 AND transaction_status IN $analyticsStatuses{$simScopeSqlPlain}
			";

			$soldStatsRows = $sim->query($soldStatsQuery, [
				'today' => $today,
				'week_start' => $weekStart,
			] + $simScopeParams);
			if (!empty($soldStatsRows)) {
				foreach ($soldStats as $key => $placeholder) {
					$soldStats->$key = (float)($soldStatsRows[0]->$key ?? 0);
				}
			}

			$recentSalesQuery = "
				SELECT
					s.*,
					u_assigned.first_name AS assigned_first_name,
					u_assigned.last_name AS assigned_last_name,
					u_assigned.image AS assigned_image,
					u_created.first_name AS created_first_name,
					u_created.last_name AS created_last_name
				FROM sims AS s
				LEFT JOIN users AS u_assigned ON s.assigned = u_assigned.id
				LEFT JOIN users AS u_created ON s.created_by = u_created.id
				WHERE s.deleted = 0 AND s.transaction_status IN $analyticsStatuses{$simScopeSqlSales}
				ORDER BY COALESCE(s.date_updated, s.date_created) DESC
				LIMIT 10
			";
			$recentSales = $sim->query($recentSalesQuery, $simScopeParams) ?? [];

			$topCustomersQuery = "
				SELECT
					buyer_phone,
					COUNT(*) AS total_sims,
					SUM(amount) AS total_amount,
					SUM(commission) AS total_commission,
					AVG(amount) AS avg_ticket,
					MAX(COALESCE(date_updated, date_created)) AS last_purchase
				FROM sims
				WHERE deleted = 0 AND buyer_phone IS NOT NULL AND buyer_phone <> ''
					AND transaction_status IN $analyticsStatuses{$simScopeSqlPlain}
				GROUP BY buyer_phone
				ORDER BY total_sims DESC, total_amount DESC
				LIMIT 7
			";
			$topCustomers = $sim->query($topCustomersQuery, $simScopeParams) ?? [];

			$networkPerformanceQuery = "
				SELECT
					network,
					COUNT(*) AS total_sold,
					SUM(amount) AS total_amount,
					SUM(CASE WHEN transaction_status = 'Sold - retail' THEN 1 ELSE 0 END) AS retail_count,
					SUM(CASE WHEN transaction_status = 'Sold - wholesale' THEN 1 ELSE 0 END) AS wholesale_count,
					SUM(CASE WHEN DATE(COALESCE(date_updated, date_created)) >= :week_start THEN 1 ELSE 0 END) AS sold_last_7
				FROM sims
				WHERE deleted = 0 AND transaction_status IN $analyticsStatuses{$simScopeSqlPlain}
				GROUP BY network
				ORDER BY total_sold DESC
			";
			$networkPerformance = $sim->query($networkPerformanceQuery, ['week_start' => $weekStart] + $simScopeParams) ?? [];

			$topAgentsQuery = "
				SELECT
					s.assigned AS user_id,
					u.first_name,
					u.last_name,
					u.image,
					COUNT(*) AS total_sims,
					SUM(s.amount) AS total_amount,
					SUM(s.commission) AS total_commission,
					MAX(COALESCE(s.date_updated, s.date_created)) AS last_sale
				FROM sims AS s
				LEFT JOIN users AS u ON s.assigned = u.id
				WHERE s.deleted = 0 AND s.assigned IS NOT NULL AND s.transaction_status IN $analyticsStatuses{$simScopeSqlSales}
				GROUP BY s.assigned, u.first_name, u.last_name, u.image
				ORDER BY total_sims DESC, total_amount DESC
				LIMIT 6
			";
			$topAgents = $sim->query($topAgentsQuery, $simScopeParams) ?? [];
			
			//for lower ranks
			$query = "SELECT SUM(amount) AS total_amount, SUM(commission) AS total_commission FROM sims WHERE deleted = 0 AND transaction_status = :transaction_status AND assigned = $user_id";
			$total_unassigned = $sim->query($query, ['transaction_status'=>'Unassigned']);
			if (!empty($total_unassigned)) {			
				$totalUnassigned_amount_sub = $total_unassigned[0]->total_amount ?? 0;
				$totalUnassigned_commission_sub = $total_unassigned[0]->total_commission ?? 0;
			}

			$total_assigned = $sim->query($query, ['transaction_status'=>'Assigned']);
			if (!empty($total_assigned)) {
				$totalAssigned_amount_sub = $total_assigned[0]->total_amount ?? 0;
				$totalAssigned_commission_sub = $total_assigned[0]->total_commission ?? 0;
			}

			$total_soldWholesale = $sim->query($query, ['transaction_status'=>'Sold - wholesale']);
			if (!empty($total_soldWholesale)) {				
				$totalSoldWholesale_amount_sub = $total_soldWholesale[0]->total_amount ?? 0;
				$totalSoldWholesale_commission_sub = $total_soldWholesale[0]->total_commission ?? 0;
			}

			$total_soldRetail = $sim->query($query, ['transaction_status'=>'Sold - retail']);
			if (!empty($total_soldRetail)) {
				$totalSoldRetail_amount_sub = $total_soldRetail[0]->total_amount ?? 0;
				$totalSoldRetail_commission_sub = $total_soldRetail[0]->total_commission ?? 0;
			}

			$total_expired = $sim->query($query, ['transaction_status'=>'Expired']);
			if (!empty($total_expired)) {
				$totalExpired_amount_sub = $total_expired[0]->total_amount ?? 0;
				$totalExpired_commission_sub = $total_expired[0]->total_commission ?? 0;
			}
			
			$query = "SELECT SUM(amount) AS total_amount, SUM(commission) AS total_commission FROM sims WHERE deleted = 0 AND assigned = $user_id";
			$total = $sim->query($query);
			if (!empty($total)) {
				$total_amount_sub = $total[0]->total_amount ?? 0;
				$total_commission_sub = $total[0]->total_commission ?? 0;
			}

			
			require plugin_path('views/admin/list.php');
		}

	}
});

/** for manipulating data after a query operation **/
add_filter('after_query',function($data){

	
	if(empty($data['result']))
		return $data;

	if($data['query_id'] == 'get-sim')
	{
		$role_map = new \Sims\User_roles_map;
		foreach ($data['result'] as $key => $row) {
			$simId = null;
			if (is_object($row)) {
				$simId = $row->id ?? null;
			} elseif (is_array($row)) {
				$simId = $row['id'] ?? null;
			}
			if ($simId === null) {
				continue;
			}
			$query = "select * from sim_roles where disabled = 0 && id in (select role_id from sim_roles_map where disabled = 0 && sim_id = :sim_id)";
			$roles = $role_map->query($query,['sim_id'=>$simId]);
			if($roles)
				$data['result'][$key]->roles = array_column($roles, 'role');
			
			/** get sim's roles **/
			$sim_roles_map = new \Sims\User_roles_map;
				
			$role_ids = $sim_roles_map->where(['sim_id'=>$simId,'disabled'=>0]);
			if($role_ids)
				$data['result'][$key]->role_ids = array_column($role_ids, 'role_id');

		}
	}

	return $data;
});


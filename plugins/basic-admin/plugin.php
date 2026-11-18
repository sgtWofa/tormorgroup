<?php



/**

 * Plugin name: Eathorne

 * Description: Provides a basic admin area

 * 

 **/



set_value([

	'plugin_route'		=>'admin',

	'dashboard_route'	=>'dashboard',

	'profile_route'		=>'profile',

	'logout_page'		=>'logout',

]);

require_once ROOTPATH . 'plugins/attendance/helpers/work-close-attendance.php';

if(!function_exists('basic_admin_live_ticket_notification_data'))
{
	function basic_admin_live_ticket_notification_data(int $userId): array
	{
		$data = ['count' => 0, 'latest_ticket_id' => 0, 'latest_response_id' => 0];
		if($userId <= 0){
			return $data;
		}

		try{
			$db = new \Core\Database;
			if(!$db->table_exists(['tickets', 'ticket_responses'])){
				return $data;
			}

			$rows = $db->query(
				"SELECT COUNT(r.id) AS cnt, COALESCE(MAX(r.id), 0) AS latest_response_id
				 FROM ticket_responses AS r
				 INNER JOIN tickets AS t ON t.id = r.ticket_id
				 WHERE r.seen = 0
				 AND r.created_by != :uid
				 AND (t.created_by = :uid OR t.assigned = :uid)",
				['uid' => $userId]
			) ?: [];
			$data['count'] = (int)($rows[0]->cnt ?? 0);
			$data['latest_response_id'] = (int)($rows[0]->latest_response_id ?? 0);

			$rows = $db->query(
				"SELECT COUNT(t.id) AS cnt
				 FROM tickets AS t
				 WHERE t.assigned = :uid
				 AND t.created_by != :uid
				 AND LOWER(COALESCE(t.status,'')) != 'closed'
				 AND NOT EXISTS (
					SELECT 1
					FROM ticket_responses AS r_any
					WHERE r_any.ticket_id = t.id
				 )",
				['uid' => $userId]
			) ?: [];
			$data['count'] += (int)($rows[0]->cnt ?? 0);

			$rows = $db->query(
				"SELECT COALESCE(MAX(id), 0) AS latest_ticket_id
				 FROM tickets
				 WHERE assigned = :uid
				 AND created_by != :uid",
				['uid' => $userId]
			) ?: [];
			$data['latest_ticket_id'] = (int)($rows[0]->latest_ticket_id ?? 0);
		}catch(\Throwable $e){
			error_log('[basic-admin] ticket live notification data failed: ' . $e->getMessage());
		}

		return $data;
	}
}

if(!function_exists('basic_admin_live_backoffice_notification_data'))
{
	function basic_admin_live_backoffice_notification_data($session): array
	{
		$data = ['count' => 0, 'latest_id' => 0];
		$userId = (int)($session->id ?? 0);
		if($userId <= 0){
			return $data;
		}

		try{
			$db = new \Core\Database;
			$isAdmin = user_can('is_admin');
			$params = [];
			$scopeWherePayments = '';
			$scopeWhereRequests = '';
			$scopeWhereCommissions = '';
			$scopeWhereBankTransfers = '';
			$bankCommissionVisibilitySql = '';

			if(function_exists('backoffice_commission_can_access_bank_sims') && function_exists('backoffice_commission_non_bank_sql_clause')){
				$bankCommissionVisibilitySql = backoffice_commission_can_access_bank_sims($session)
					? ''
					: ' AND ' . backoffice_commission_non_bank_sql_clause('commissions');
			}

			if(!$isAdmin){
				$params['uid'] = $userId;
				$scopeWherePayments = ' AND (created_by = :uid OR pay_to = :uid)';
				$scopeWhereRequests = ' AND (created_by = :uid OR assigned = :uid)';
				$scopeWhereCommissions = ' AND (created_by = :uid OR COALESCE(assigned, pay_to) = :uid)' . $bankCommissionVisibilitySql;
				if(function_exists('backoffice_user_can_bank_transfer_third_party') && backoffice_user_can_bank_transfer_third_party($session)){
					$scopeWhereBankTransfers = '';
				}else{
					$scopeWhereBankTransfers = ' AND (created_by = :uid OR withdrawing_user_id = :uid OR second_party_user_id = :uid OR third_party_user_id = :uid)';
				}
			}else{
				$scopeWhereCommissions = $bankCommissionVisibilitySql;
			}

			$queries = [];
			if($db->table_exists(['payments_backoffice'])){
				$queries[] = "SELECT COUNT(id) AS c, COALESCE(MAX(id), 0) AS latest_id
					FROM payments_backoffice
					WHERE (deleted IS NULL OR deleted = 0)
					AND (disabled IS NULL OR disabled = 0)
					AND LOWER(COALESCE(transaction_status,'')) = 'pending'{$scopeWherePayments}";
			}
			if($db->table_exists(['requests'])){
				$queries[] = "SELECT COUNT(id) AS c, COALESCE(MAX(id), 0) AS latest_id
					FROM requests
					WHERE (deleted IS NULL OR deleted = 0)
					AND (disabled IS NULL OR disabled = 0)
					AND LOWER(COALESCE(transaction_status,'')) = 'pending'{$scopeWhereRequests}";
			}
			if($db->table_exists(['commissions'])){
				$queries[] = "SELECT COUNT(id) AS c, COALESCE(MAX(id), 0) AS latest_id
					FROM commissions
					WHERE (deleted IS NULL OR deleted = 0)
					AND (disabled IS NULL OR disabled = 0)
					AND bulk_parent_id IS NULL
					AND LOWER(COALESCE(transaction_status,'')) = 'pending'{$scopeWhereCommissions}";
			}
			if($db->table_exists(['bank_transfers_backoffice'])){
				$queries[] = "SELECT COUNT(id) AS c, COALESCE(MAX(id), 0) AS latest_id
					FROM bank_transfers_backoffice
					WHERE (deleted IS NULL OR deleted = 0)
					AND LOWER(COALESCE(transaction_status,'')) NOT IN ('approved','rejected'){$scopeWhereBankTransfers}";
			}

			foreach($queries as $query){
				$rows = $db->query($query, $params) ?: [];
				$data['count'] += (int)($rows[0]->c ?? 0);
				$data['latest_id'] = max($data['latest_id'], (int)($rows[0]->latest_id ?? 0));
			}
		}catch(\Throwable $e){
			error_log('[basic-admin] backoffice live notification data failed: ' . $e->getMessage());
		}

		return $data;
	}
}

if(!function_exists('basic_admin_collect_sidebar_badges'))
{
	function basic_admin_collect_sidebar_badges(): array
	{
		$vars = get_value();
		$badges = [];

		$links = [];
		$obj = (object)[];
		$obj->title = 'Dashboard';
		$obj->link = ROOT . '/' . ($vars['plugin_route'] ?? 'admin') . '/' . ($vars['dashboard_route'] ?? 'dashboard');
		$obj->icon = 'fa-solid fa-gauge';
		$obj->parent = 0;
		$obj->id = 'view_dashboard';
		$links[] = $obj;

		try{
			$links = do_filter('basic-admin_before_admin_links', $links);
		}catch(\Throwable $e){
			error_log('[basic-admin] live sidebar filter failed: ' . $e->getMessage());
		}

		$collect = function($items) use (&$collect, &$badges): void {
			if(empty($items) || !is_array($items)){
				return;
			}

			foreach($items as $item){
				if(!is_object($item)){
					continue;
				}

				$id = trim((string)($item->id ?? ''));
				if($id !== '' && user_can($id)){
					$count = (int)($item->count ?? 0);
					$badges[$id] = max($count, (int)($badges[$id] ?? 0));
				}

				if(!empty($item->sublinks) && is_array($item->sublinks)){
					$collect($item->sublinks);
				}

				if(!empty($item->minorlinks) && is_array($item->minorlinks)){
					$collect($item->minorlinks);
				}
			}
		};

		$collect($links);

		return $badges;
	}
}



/** set user permissions for this plugin **/

add_filter('permissions',function($permissions){

	$permissions[] = 'view_admin_page';

	return $permissions;

});





/** run this after a form submit **/

add_action('before_controller',function(){

	$vars = get_value();

	$ses = new \Core\Session;
	if(page() == $vars['plugin_route'] && !$ses->is_logged_in())

	{
		$requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
		$requestPath = (string)(parse_url($requestUri, PHP_URL_PATH) ?? '');
		$rootPath = (string)(parse_url(ROOT, PHP_URL_PATH) ?? '');
		$rootPath = rtrim($rootPath, '/');
		if($requestPath !== '' && ($rootPath === '' || strpos($requestPath, $rootPath . '/') === 0)){
			$relativePath = ltrim(substr($requestPath, strlen($rootPath)), '/');
			$query = (string)(parse_url($requestUri, PHP_URL_QUERY) ?? '');
			if($relativePath !== '' && $relativePath !== 'login' && strpos($relativePath, 'login/') !== 0){
				$_SESSION['post_login_redirect'] = $relativePath . ($query !== '' ? '?' . $query : '');
			}
		}

		try {
			$hasUser = isset($_SESSION['USER']) ? 1 : 0;
			$sk = is_array($_SESSION ?? null) ? implode(',', array_keys($_SESSION)) : '';
			error_log('[ADMIN] denied; session_status=' . (string)session_status() . '; session_id=' . session_id() . '; has_USER=' . (string)$hasUser . '; keys=' . $sk);
		} catch (\Throwable $e) {
		}

		message_fail("Access to admin page denied! please try a different login");

		redirect('login');

	}

});



/** run this after a form submit **/

add_action('controller', function () {

    $req = new \Core\Request;

    $vars = get_value();

    $plugin_route = $vars['plugin_route'];

    if (URL(0) == $plugin_route && URL(1) == 'live-notifications') {
        $session = get_session();
        $userId = (int)($session->id ?? 0);

        header('Content-Type: application/json');
        if($userId <= 0){
            echo json_encode(['success' => false, 'data' => []]);
            exit;
        }

        $tickets = basic_admin_live_ticket_notification_data($userId);
        $backoffice = basic_admin_live_backoffice_notification_data($session);

        $badges = basic_admin_collect_sidebar_badges();
        $badges['view_tickets'] = max((int)($badges['view_tickets'] ?? 0), (int)($tickets['count'] ?? 0));
        $soundConfig = function_exists('admin_settings_notification_sound_config') ? admin_settings_notification_sound_config() : null;

        echo json_encode([
            'success' => true,
            'data' => [
                'badges' => $badges,
                'sound' => is_array($soundConfig) ? [
                    'enabled' => (bool)($soundConfig['enabled'] ?? true),
                    'volume' => (int)($soundConfig['volume'] ?? 90),
                    'sounds' => array_values($soundConfig['sounds'] ?? []),
                ] : null,
                'events' => [
                    'tickets' => [
                        'count' => (int)($tickets['count'] ?? 0),
                        'latest_ticket_id' => (int)($tickets['latest_ticket_id'] ?? 0),
                        'latest_response_id' => (int)($tickets['latest_response_id'] ?? 0),
                    ],
                    'backoffice' => [
                        'count' => (int)($backoffice['count'] ?? 0),
                        'latest_id' => (int)($backoffice['latest_id'] ?? 0),
                    ],
                ],
                'server_time' => time(),
            ],
        ]);
        exit;
    }

    // Ensure it's the admin route and a POST request

    if (URL(0) == $plugin_route && $req->posted()) {

        $ses = new \Core\Session;

        $db  = new \Core\Database;



        // Decode JSON input once here

        $inputData = json_decode(file_get_contents("php://input"), true);

        $action    = is_array($inputData) ? ($inputData['action'] ?? '') : '';



        // Only load the cashdrawer controller for supported cashdrawer AJAX actions

        if (in_array($action, ['view-drawer', 'view-drawer-by-id', 'exchange-ecash', 'normalize-ecash-channels'], true)) {

            // Pass decoded JSON to the controller so it does not re-read php://input

            $GLOBALS['cashdrawer_rawData'] = $inputData;

            require plugin_path('controllers/cashdrawer-controller.php');

            exit; // stop other controllers for this AJAX call

        }

    }



    do_action(plugin_id() . '_controller');

});









/** displays the view file **/

add_action('view',function(){


	$vars = get_value();


	$section = ucfirst(str_replace(["-", "_"], " ", (URL(0) ?? '')));

	$section_title = ucfirst(str_replace(["-", "_"], " ", (URL(1) ?? '')));

	$section_page = ucfirst(str_replace(["-", "_"], " ", (URL(2) ?? '')));

	$section_id = ucfirst(str_replace(["-", "_"], " ", (URL(3) ?? '')));
	

	if(empty($section_title))

		$section_title = "Dashboard";



	$section_title = do_filter(plugin_id().'_before_section_title',$section_title);



	$links = [];

	$obj = (object)[];

	$obj->title = 'Dashboard';

	$obj->link = ROOT . '/'.$vars['plugin_route']. '/'.$vars['dashboard_route'];

	$obj->icon = 'fa-solid fa-gauge';

	$obj->parent = 0;

	$obj->id = 'view_dashboard';

	$links[] = $obj;



	$links = do_filter(plugin_id().'_before_admin_links',$links);

	$bottom_links = [];



	/*$session = get_session();

	$id = $session->id ?? 0;

	$name = $session->last_name ?? 'Profile';*/



	/*$obj = (object)[];

	$obj->title = "Cash Drawer";

	$obj->link = '#';

	$obj->icon = 'fa-solid fa-book-open text-warning';

	$obj->parent = 0;

	$bottom_links[] = $obj;*/



	$obj = (object)[];

	$obj->title = 'Home';

	$obj->link = ROOT . "/home";

	$obj->icon = 'fa-solid fa-earth-africa';

	$obj->parent = 0;

	$obj->id = 'view_home';

	$bottom_links[] = $obj;



	$obj = (object)[];

	$obj->title = 'Logout';

	$obj->link = ROOT . '/' .$vars['logout_page'];

	$obj->icon = 'fa-solid fa-right-from-bracket';

	$obj->parent = 1;

	$obj->id = 'logout';

	$bottom_links[] = $obj;





$session = get_session();

$user_id = $session->id;

$db = new \Core\Database;



// Fetch user roles based on the session user ID

$query = "

    SELECT r_map.*, u_role.role 

    FROM user_roles_map AS r_map

    LEFT JOIN user_roles AS u_role ON r_map.role_id = u_role.id

    WHERE r_map.disabled = :disabled 

    AND r_map.user_id = :user_id

    ORDER BY u_role.role ASC

";



// Execute the query

$role_ids = $db->query($query, [

    'disabled' => 0, 

    'user_id' => $user_id

]);



// Add roles to the session object if results exist

if ($role_ids) {

    $session->roles = array_column($role_ids, 'role'); // Extract roles as an array

    $session->role_ids = array_column($role_ids, 'role_id'); // Extract role IDs as an array

}

// Require a cashdrawer for today before accessing admin pages (except opening one),
// while honoring work open/close times and reconciliation flow.
$isForcedPasswordChangePage = (int)($session->force_password_change ?? 0) === 1
    && strtolower((string)page()) === 'admin'
    && strtolower((string)(URL(1) ?? '')) === 'profile'
    && strtolower((string)(URL(2) ?? '')) === 'resetpassword'
    && (int)(URL(3) ?? 0) === (int)($session->id ?? 0);
$isForcedCashdrawerCreationPage = (int)($session->force_password_change ?? 0) === 1
    && strtolower((string)page()) === 'admin'
    && strtolower((string)(URL(1) ?? '')) === 'operations_accounts'
    && strtolower((string)(URL(2) ?? '')) === 'add_cashdrawer';
$hasCashdrawerToday = empty($user_id);
$isBeforeOpen = false;
$isAfterClose = false;
$workOpenText = '';
$workCloseText = '';
$nowGate = null;
if (!empty($user_id) && $db->table_exists(['settings'])) {
    $settingsRow = ($db->query("SELECT work_open, work_close FROM settings ORDER BY id DESC LIMIT 1") ?: [])[0] ?? null;
    $workOpenText = $settingsRow ? trim((string)($settingsRow->work_open ?? '')) : '';
    $workCloseText = $settingsRow ? trim((string)($settingsRow->work_close ?? '')) : '';
    $tzGate = new \DateTimeZone('Africa/Accra');
    $nowGate = new \DateTime('now', $tzGate);
    $todayGate = $nowGate->format('Y-m-d');

    if ($workOpenText !== '') {
        if (strlen($workOpenText) === 5) $workOpenText .= ':00';
        $openTodayGate = new \DateTime($todayGate . ' ' . $workOpenText, $tzGate);
        if ($nowGate < $openTodayGate) {
            $isBeforeOpen = true;
        }
    }

    if ($workCloseText !== '') {
        if (strlen($workCloseText) === 5) $workCloseText .= ':00';
        $closeTodayGate = new \DateTime($todayGate . ' ' . $workCloseText, $tzGate);
        if ($nowGate >= $closeTodayGate) {
            $isAfterClose = true;
        }
    }
}

if (!empty($user_id)) {
    $today = $nowGate ? $nowGate->format('Y-m-d') : date('Y-m-d');
    if (function_exists('operations_account_has_opened_or_owned_cashdrawer_today')) {
        $hasCashdrawerToday = operations_account_has_opened_or_owned_cashdrawer_today((int)$user_id, $today);
    } elseif (function_exists('operations_account_has_cashdrawer_today')) {
        $hasCashdrawerToday = operations_account_has_cashdrawer_today((int)$user_id, $today);
    } elseif ($db->table_exists(['cashdrawers'])) {
        $drawerRow = $db->query(
            "SELECT id FROM cashdrawers WHERE (user_id = :user_id OR created_by = :user_id) AND DATE(date_created) = :today LIMIT 1",
            ['user_id' => $user_id, 'today' => $today]
        );
        $hasCashdrawerToday = !empty($drawerRow);
    }
}

$isAddCashdrawerPage = ((URL(1) ?? '') === 'operations_accounts' && (URL(2) ?? '') === 'add_cashdrawer');
$isOperationsAccountsHome = ((URL(1) ?? '') === 'operations_accounts' && (string)(URL(2) ?? '') === '');
$isReconPage = ((URL(1) ?? '') === 'back_office' && (URL(2) ?? '') === 'reconciliation');
$isBackOfficePage = ((URL(1) ?? '') === 'back_office');
$hasLateReconAccess = false;
$lateReconAccessId = 0;
if (!$hasCashdrawerToday && $isBackOfficePage && !empty($user_id) && $db->table_exists(['reconciliation_late_access'])) {
    $lateRows = $db->query(
        "SELECT id FROM reconciliation_late_access WHERE user_id = :uid AND LOWER(COALESCE(status,'')) = 'active' LIMIT 1",
        ['uid' => (string)$user_id]
    ) ?: [];
    $hasLateReconAccess = !empty($lateRows);
    $lateReconAccessId = $hasLateReconAccess ? (int)($lateRows[0]->id ?? 0) : 0;
}

if ($isForcedPasswordChangePage && !$hasCashdrawerToday) {
    redirect('admin/operations_accounts/add_cashdrawer');
}

if (!$hasCashdrawerToday && ($isAddCashdrawerPage || $isOperationsAccountsHome)) {
    $currentFailMessage = ltrim((string)message_fail());
    $pendingReconMessages = [
        'Transaction denied.',
        'Transaction denied',
        'Please approve or reject your pending transactions first:',
        'Process all pending transactions before reconciliation.',
        'Unable to validate pending transactions.',
    ];
    foreach ($pendingReconMessages as $pendingReconMessage) {
        if (stripos($currentFailMessage, $pendingReconMessage) === 0 || stripos($currentFailMessage, $pendingReconMessage) !== false) {
            message_fail('', true);
            break;
        }
    }
}

if (!$isForcedPasswordChangePage && !$isForcedCashdrawerCreationPage && $isBeforeOpen) {
    $msg = 'Work has not started yet. Please login after ' . ($workOpenText !== '' ? $workOpenText : 'work opens') . '.';
    message_fail($msg);
    redirect('login');
}

if (!$isForcedPasswordChangePage && !$isForcedCashdrawerCreationPage && $isAfterClose) {
    if (function_exists('attendance_mark_absentees_after_work_close')) {
        attendance_mark_absentees_after_work_close($db, $nowGate);
    }
    if (!$hasCashdrawerToday && !$hasLateReconAccess) {
        message_fail('Work has closed for today. You did not open a cashdrawer.');
        redirect('login');
    } else {
        // If cashdrawer exists but reconciliation is missing, force reconciliation.
        if (!$isReconPage && $db->table_exists(['reconciliations_summary'])) {
            $start = ($nowGate ? $nowGate->format('Y-m-d') : date('Y-m-d')) . ' 00:00:00';
            $end = ($nowGate ? (clone $nowGate)->modify('+1 day')->format('Y-m-d') : date('Y-m-d', strtotime('+1 day'))) . ' 00:00:00';
            $reconRows = $db->query(
                "SELECT id FROM reconciliations_summary WHERE deleted = 0 AND created_by = :uid AND date_created >= :start AND date_created < :end ORDER BY date_created DESC, id DESC LIMIT 1",
                ['uid' => (string)$user_id, 'start' => $start, 'end' => $end]
            ) ?: [];
            if (empty($reconRows)) {
                if ($hasLateReconAccess && $lateReconAccessId > 0) {
                    redirect('admin/back_office/reconciliation?late_access=' . $lateReconAccessId);
                }
                redirect('admin/back_office/reconciliation');
            }
        }
    }
}

if (!$isForcedPasswordChangePage && !$hasCashdrawerToday && !$hasLateReconAccess && !$isAddCashdrawerPage && !$isOperationsAccountsHome && !$isBeforeOpen && !$isAfterClose) {
    message_fail('Please open a cashdrawer for today to continue.');
    redirect('admin/operations_accounts/add_cashdrawer');
}


	$__discardedOutput = '';
	while (ob_get_level() > 0) {
		$__discardedOutput .= (string)ob_get_clean();
	}
	if (trim($__discardedOutput) !== '') {
		error_log('[basic-admin] Discarded pre-view output before admin shell: ' . substr(trim($__discardedOutput), 0, 500));
	}
	ob_start();
	require plugin_path('views/view.php');
	echo (string)ob_get_clean();

});


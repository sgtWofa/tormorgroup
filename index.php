<?php

session_start();
ob_start();

$minPHPVersion = '8.0';
if(phpversion() < $minPHPVersion)
    die("You need a minimum of PHP version $minPHPVersion to run this app");


define('DS', DIRECTORY_SEPARATOR);
define('ROOTPATH', __DIR__.DS);

require 'config.php';
require 'app'.DS.'core'.DS.'init.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
register_app_error_handlers();

$ACTIONS            = [];
$FILTERS            = [];
$APP['URL']         = split_url($_GET['url'] ?? 'home');
$APP['permissions'] = [];
$USER_DATA          = [];
$CURRENT_PATH       = trim((string)($_GET['url'] ?? 'home'));

/** load plugins **/
$PLUGINS = get_plugin_folders();
if(!load_plugins($PLUGINS))
    die("<center><h1 style='font-family:tahoma'>No plugins were found! Please put at least one plugin in the plugins folder</h1></center>");

require_once ROOTPATH . 'app' . DS . 'core' . DS . 'drawer_balances_schema.php';
add_action('before_controller', 'ensure_drawer_balances_schema', -1000);

$APP['permissions'] = do_filter('permissions',$APP['permissions']);

add_action('before_controller', function () use ($CURRENT_PATH) {
    try {
        $ses = new \Core\Session;
        $isAccountStatusRequest = (URL(0) === 'account-status');
        $isAjaxRoute = preg_match('/(^|\/)ajax_/', $CURRENT_PATH) === 1;
        $wantsJson = $isAccountStatusRequest
            || $isAjaxRoute
            || stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false
            || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

        if (!$ses->is_logged_in()) {
            if ($isAccountStatusRequest || $wantsJson) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'logged_in' => false,
                    'deleted' => false,
                    'message' => 'Your session has expired. Please log in again.',
                    'redirect' => ROOT . '/login',
                ]);
                die;
            }
            return;
        }

        $sessionUser = get_session();
        $userId = (int)($sessionUser->id ?? 0);
        if ($userId <= 0) {
            return;
        }

        $db = new \Core\Database;
        if (!$db->table_exists(['users'])) {
            return;
        }

        $rows = $db->query(
            "SELECT id, first_name, last_name, deleted, disabled FROM users WHERE id = :id LIMIT 1",
            ['id' => $userId]
        ) ?: [];
        $currentUser = $rows[0] ?? null;
        $isDeleted = empty($currentUser) || (int)($currentUser->deleted ?? 0) === 1;

        if (!$isDeleted) {
            if ($isAccountStatusRequest) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'logged_in' => true, 'deleted' => false]);
                die;
            }
            return;
        }

        $ses->logout();
        $message = 'Your account has been removed by an administrator. Access to the app has been blocked. Please contact an administrator if you believe this is a mistake.';

        if ($wantsJson) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'logged_in' => false,
                'deleted' => true,
                'message' => $message,
                'redirect' => ROOT . '/login',
            ]);
            die;
        }

        http_response_code(403);
        $loginUrl = ROOT . '/login';
        render_app_error_screen(403, 'Account access blocked', $message, $loginUrl, 'Return to login', true);
        die;
    } catch (\Throwable $e) {
        return;
    }
}, 0);

add_action('before_controller', function () {
    try {
        $ses = new \Core\Session;
        if (!$ses->is_logged_in()) {
            return;
        }

        $user = get_session();
        $userId = (int)($user->id ?? 0);
        if ($userId <= 0) {
            return;
        }

        $isAdminPath = (URL(0) === 'admin');
        $module = strtolower(trim((string)($isAdminPath ? URL(1) : URL(0))));
        $action = strtolower(trim((string)($isAdminPath ? URL(2) : URL(1))));
        if ($action === '') {
            return;
        }

        $startActionsByModule = [
            'back_office' => [
                'make_request' => true,
                'make_payment' => true,
                'pay_commission' => true,
                'pay_dailycommission' => true,
                'self_stocking' => true,
            ],
            'cashpickup' => [
                'add' => true,
            ],
            'reversals' => [
                'add' => true,
            ],
            'payment_services' => [
                'add' => true,
            ],
            'agencybanking' => [
                'add' => true,
                'withdrawal' => true,
                'direct_transfer' => true,
                'deposit' => true,
                'account_opening' => true,
                'balance_enquiry' => true,
                'mini_statement' => true,
                'funds_transfer' => true,
                'interbank' => true,
                'international_transfer' => true,
            ],
            // momoremit plugin_route is singular (/admin/momoremit/...), so we must map it here
            'momoremit' => [
                'add' => true,
                'cit' => true,
                'deposit' => true,
                'withdrawal' => true,
                'cashin' => true,
            ],
            'momoremits' => [
                'add' => true,
                'cit' => true,
                'deposit' => true,
                'withdrawal' => true,
                'cashin' => true,
            ],
            'operations_accounts' => [
                'add_account' => true,
                'add_wallet' => true,
                'add_cashdrawer' => true,
                'add_sim' => true,
                'bulk_assign_sims' => true,
                'transfer' => true,
                'exchange_denominations' => true,
            ],
            'cfa' => [
                'add' => true,
                'buy' => true,
                'sell' => true,
            ],
            'airtimebundle' => [
                'add' => true,
                'airtime' => true,
                'bundle' => true,
            ],
            'ezwich' => [
                'add' => true,
                'withdrawal' => true,
                'transfer' => true,
            ],
            'sims' => [
                'add' => true,
                'sell' => true,
                'bulk-sell' => true,
            ],
        ];

        $genericStartActions = [
            'add' => true,
            'create' => true,
            'new' => true,
        ];

        $moduleStart = $startActionsByModule[$module] ?? [];
        $isStart = isset($genericStartActions[$action]) || isset($moduleStart[$action]) || str_starts_with($action, 'make_');
        if (!$isStart) {
            return;
        }

        $db = new \Core\Database;
        if ($module === 'operations_accounts' && $action === 'add_cashdrawer') {
            $today = date('Y-m-d');
            $hasDrawerToday = false;
            if (function_exists('operations_account_has_cashdrawer_today')) {
                $hasDrawerToday = operations_account_has_cashdrawer_today($userId, $today);
            } elseif ($db->table_exists(['cashdrawers'])) {
                $drawerRows = $db->query(
                    "SELECT id FROM cashdrawers WHERE user_id = :uid AND DATE(date_created) = :today LIMIT 1",
                    ['uid' => $userId, 'today' => $today]
                ) ?: [];
                $hasDrawerToday = !empty($drawerRows);
            }
            if (!$hasDrawerToday) {
                return;
            }
        }

        $blocking = [];

        $checks = [
            ['label' => 'Backoffice Requests', 'table' => 'requests', 'sql' => "SELECT COUNT(id) AS c FROM requests WHERE (deleted IS NULL OR deleted = 0) AND LOWER(TRIM(COALESCE(transaction_status,''))) = 'pending' AND (created_by = :uid OR assigned = :uid)"],
            ['label' => 'Backoffice Payments', 'table' => 'payments_backoffice', 'sql' => "SELECT COUNT(id) AS c FROM payments_backoffice WHERE (deleted IS NULL OR deleted = 0) AND LOWER(TRIM(COALESCE(transaction_status,''))) = 'pending' AND (created_by = :uid OR pay_to = :uid)"],
            ['label' => 'Backoffice Commissions', 'table' => 'commissions', 'sql' => "SELECT COUNT(id) AS c FROM commissions WHERE (deleted IS NULL OR deleted = 0) AND bulk_parent_id IS NULL AND LOWER(TRIM(COALESCE(transaction_status,''))) = 'pending' AND (created_by = :uid OR pay_to = :uid)"],
            ['label' => 'Cash Pickup', 'table' => 'cashpickups', 'sql' => "SELECT COUNT(id) AS c
                FROM cashpickups
                WHERE (deleted IS NULL OR deleted = 0)
                AND LOWER(TRIM(COALESCE(approval_status,'pending'))) = 'pending'
                AND (
                    (
                        LOWER(TRIM(COALESCE(credit_mode,''))) = 'credit'
                        AND LOWER(TRIM(COALESCE(transaction_status,''))) = 'pending payout'
                        AND (
                            assigned_user_id = :uid
                            OR (COALESCE(assigned_user_id,0) = 0 AND created_by = :uid)
                        )
                    )
                    OR (
                        NOT (
                            LOWER(TRIM(COALESCE(credit_mode,''))) = 'credit'
                            AND LOWER(TRIM(COALESCE(transaction_status,''))) = 'pending payout'
                        )
                        AND (created_by = :uid OR assigned_user_id = :uid)
                    )
                )"],
            ['label' => 'Reversals', 'table' => 'reversals', 'sql' => "SELECT COUNT(id) AS c FROM reversals WHERE LOWER(TRIM(COALESCE(transaction_status,''))) = 'pending' AND (created_by = :uid OR agent_involved = :uid)"],
            ['label' => 'Payment Services', 'table' => 'payment_services', 'sql' => "SELECT COUNT(id) AS c FROM payment_services WHERE (deleted IS NULL OR deleted = 0) AND LOWER(TRIM(COALESCE(transaction_status,''))) = 'pending' AND created_by = :uid"],
            ['label' => 'Payments', 'table' => 'payments', 'sql' => "SELECT COUNT(id) AS c FROM payments WHERE (deleted = 0 OR deleted IS NULL) AND LOWER(TRIM(COALESCE(transaction_status,''))) = 'pending' AND created_by = :uid"],
            ['label' => 'Agency Banking', 'table' => 'agencybankings', 'sql' => "SELECT COUNT(id) AS c FROM agencybankings WHERE (deleted IS NULL OR deleted = 0) AND LOWER(TRIM(COALESCE(transaction_status,''))) = 'pending' AND created_by = :uid"],
            ['label' => 'MoMo & Remittances', 'table' => 'momoremits', 'sql' => "SELECT COUNT(id) AS c FROM momoremits WHERE (deleted IS NULL OR deleted = 0) AND LOWER(TRIM(COALESCE(transaction_status,''))) = 'pending' AND created_by = :uid"],
            ['label' => 'CFA Exchange', 'table' => 'cfas', 'sql' => "SELECT COUNT(id) AS c FROM cfas WHERE (deleted IS NULL OR deleted = 0) AND LOWER(TRIM(COALESCE(transaction_status,''))) = 'pending' AND created_by = :uid"],
            ['label' => 'Airtime & Bundles', 'table' => 'airtimebundles', 'sql' => "SELECT COUNT(id) AS c FROM airtimebundles WHERE (deleted IS NULL OR deleted = 0) AND LOWER(TRIM(COALESCE(transaction_status,''))) = 'pending' AND created_by = :uid"],
            ['label' => 'Ezwich', 'table' => 'ezwichs', 'sql' => "SELECT COUNT(id) AS c FROM ezwichs WHERE (deleted IS NULL OR deleted = 0) AND LOWER(TRIM(COALESCE(transaction_status,''))) = 'pending' AND created_by = :uid"],
            ['label' => 'SIMs', 'table' => 'sims', 'sql' => "SELECT COUNT(id) AS c FROM sims WHERE (deleted IS NULL OR deleted = 0) AND LOWER(TRIM(COALESCE(transaction_status,''))) = 'pending' AND (created_by = :uid OR assigned = :uid)"],
        ];

        foreach ($checks as $check) {
            if (!$db->table_exists([$check['table']])) {
                continue;
            }
            $rows = $db->query($check['sql'], ['uid' => $userId]) ?: [];
            $c = (int)(($rows[0]->c ?? 0));
            if ($c > 0) {
                $blocking[$check['label']] = $c;
            }
        }

        if (empty($blocking)) {
            return;
        }

        $lines = [];
        foreach ($blocking as $label => $count) {
            $lines[] = $label . ' (' . $count . ')';
        }
        message_fail('Transaction denied. Please approve or reject your pending transactions first: ' . implode(', ', $lines) . '.');
		if ($isAdminPath) {
			$redirectTo = 'admin' . (!empty($module) ? ('/' . $module) : '');
			redirect($redirectTo);
		}
		$referer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
		if ($referer !== '') {
			redirect($referer);
		}
		redirect('home');
    } catch (\Throwable $e) {
        return;
    }
});

/*load the app*/
$app = new \Core\App();
$app->index();

/** 
 * Global shutdown handler: Close all database connections after every request
 * This prevents "Too many connections" errors on shared hosting with low MySQL limits
 */
register_shutdown_function(function() {
    \Core\Database::close();
});

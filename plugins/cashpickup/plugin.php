<?php

/**
 * Plugin name: Cashpickup
 * Description: Cash pickup transactions (pay / receive)
 * 
 * 
 **/

set_value([

	'admin_route' 	=> 'admin',
	'plugin_route' 	=> 'cashpickup',
	'tables' 		=> [
		'cashpickup_table' => 'cashpickups',
	],

	'optional_tables' => [
	],
	

]);

if(!function_exists('cashpickup_json_response')){
	function cashpickup_json_response(array $payload, int $status = 200): void
	{
		$GLOBALS['cashpickup_json_response_sent'] = true;
		while(ob_get_level() > 0){
			ob_end_clean();
		}
		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		exit;
	}
}

$cashpickupJsonRoute = URL(1) === 'cashpickup' && in_array((string)(URL(2) ?? ''), ['lookup', 'customer_lookup', 'customer_search'], true);
if($cashpickupJsonRoute){
	ini_set('display_errors', '0');
	ini_set('display_startup_errors', '0');
	ob_start();
	$GLOBALS['cashpickup_json_response_sent'] = false;
	register_shutdown_function(static function(): void {
		if(!empty($GLOBALS['cashpickup_json_response_sent'])){
			return;
		}
		while(ob_get_level() > 0){
			ob_end_clean();
		}
		http_response_code(500);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(['success' => false, 'message' => 'Cash Pickup request failed. Please try again.'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
	});
	set_exception_handler(static function(\Throwable $exception): void {
		cashpickup_json_response(['success' => false, 'message' => 'Cash Pickup request failed. Please try again.'], 500);
	});
}

/** check if all tables exist **/
$db = new \Core\Database;
$tables = get_value()['tables'];

if(!$db->table_exists($tables)){
	if($cashpickupJsonRoute){
		cashpickup_json_response(['success' => false, 'message' => 'Cash Pickup records are not available.'], 503);
	}
	dd("Missing database tables in ".plugin_id() ." plugin: ". implode(",", $db->missing_tables));
	die;
}

/** load shared helpers **/
$cashpickupTransferHelper = plugin_path('helpers/transfer-allocation.php');
if (file_exists($cashpickupTransferHelper)) {
	require_once $cashpickupTransferHelper;
	if (function_exists('CashpickupTransferAllocation\\backfill_cashpickup_payout_assignees_once')) {
		try {
			\CashpickupTransferAllocation\backfill_cashpickup_payout_assignees_once($db);
		} catch (\Throwable $e) {
			// Ignore backfill failures; runtime calculation still handles new rows.
		}
	}
}

/** set permissions for this plugin **/ 
add_filter('permissions',function($permissions){

	$permissions[] = 'view_cashpickup';
	$permissions[] = 'add_cashpickup';
	$permissions[] = 'edit_cashpickup';
	$permissions[] = 'delete_cashpickup';
	$permissions[] = 'view_cashpickup_details';
	$permissions[] = 'view_customers_ledger';

	return $permissions;
});

add_filter('user_permissions', function($permissions){
	try{
		$session = get_session();
		$role = strtolower((string)($session->roles[0] ?? ''));
		$isPrivileged = in_array($role, ['manager','admin','is_manager','is_admin'], true);
		if(!$isPrivileged){
			$permissions[] = 'view_cashpickup';
			$permissions[] = 'add_cashpickup';
			$permissions[] = 'delete_cashpickup';
			$permissions[] = 'view_cashpickup_details';
		}
	}catch(\Throwable $e){
		// ignore
	}
	return $permissions;
});

/** add to admin links **/
add_filter('basic-admin_before_admin_links',function($links){
	$vars = get_value();
	$session = get_session();
	$user_id = (int)($session->id ?? 0);
	$db = new \Core\Database;

	$total_pending = 0;
	$isManagerOrAdmin = (user_can('is_manager') || user_can('is_admin'));

	try{
		$badgeCacheKey = 'cashpickup_nav_badge_v3_' . (int)$user_id . '_' . ($isManagerOrAdmin ? 'mgr' : 'usr');
		$badgeSession = new \Core\Session;
		$badgeCached = $badgeSession->get($badgeCacheKey);
		if (is_array($badgeCached) && (int)($badgeCached['expires'] ?? 0) >= time()) {
			$total_pending = (int)($badgeCached['count'] ?? 0);
			goto cashpickup_badge_done;
		}

		$params = [];
		$scopeWhere = '';
		if(!$isManagerOrAdmin && $user_id > 0){
			$hasTransferScope = false;
			try {
				$hasTransferScope = $db->table_exists(['cashpickup_transfers']);
			} catch (\Throwable $e) {
				$hasTransferScope = false;
			}
			$scopeParts = [
				'created_by = :uid_created',
				'assigned_user_id = :uid_assigned',
			];
			if ($hasTransferScope) {
				$scopeParts[] = "EXISTS (
					SELECT 1
					FROM cashpickup_transfers nav_t
					WHERE nav_t.receive_id = cashpickups.id
					AND nav_t.to_user_id = :uid_transfer_visible
				)";
				$params['uid_transfer_visible'] = $user_id;
			}
			$params['uid_created'] = $user_id;
			$params['uid_assigned'] = $user_id;
			$scopeWhere = ' AND (' . implode(' OR ', $scopeParts) . ')';
		}

		$pendingRows = $db->query(
			"SELECT COUNT(id) AS c
			 FROM cashpickups
			 WHERE (deleted IS NULL OR deleted = 0)
			 AND (
				(
					LOWER(transaction_side) = 'receive'
					AND LOWER(COALESCE(approval_status,'pending')) IN ('pending','draft')
				)
				OR (
					LOWER(transaction_side) = 'pay'
					AND LOWER(COALESCE(approval_status,'approved')) IN ('pending','draft')
				)
			 ){$scopeWhere}",
			$params
		);
		$total_pending = (int)($pendingRows[0]->c ?? 0);
		if ($isManagerOrAdmin) {
			try {
				if ($db->table_exists(['cashpickup_customers'])) {
					$pendingCustomers = $db->query(
						"SELECT COUNT(id) AS c
						 FROM cashpickup_customers
						 WHERE deleted = 0
						 AND LOWER(COALESCE(approval_status,'approved')) IN ('pending','draft')"
					);
					$total_pending += (int)($pendingCustomers[0]->c ?? 0);
				}
			} catch (\Throwable $e) {
				// Keep the transaction count if the customer approval badge is unavailable.
			}
		}
		if (!$isManagerOrAdmin && $user_id > 0) {
			try {
				$assignedReadyRows = $db->query(
					"SELECT t.receive_id, COALESCE(SUM(t.amount),0) AS assigned_gross
					 FROM cashpickup_transfers t
					 JOIN cashpickups r ON r.id = t.receive_id
					 WHERE t.receive_id IS NOT NULL
					 AND t.to_user_id = :transfer_uid
					 AND (r.deleted IS NULL OR r.deleted = 0)
					 AND LOWER(r.transaction_side) = 'receive'
					 AND LOWER(COALESCE(r.approval_status,'pending')) = 'approved'
						AND (
							t.approval_status IS NULL
							OR t.approval_status = ''
							OR LOWER(t.approval_status) IN ('approved','pending')
						)
					 GROUP BY t.receive_id",
					[
						'transfer_uid' => $user_id,
					]
				) ?: [];

				$paidAssignedRows = $db->query(
					"SELECT receive_id, COALESCE(SUM(COALESCE(amount_paid_out,0) + COALESCE(pickup_charge,0)),0) AS paid_gross
					 FROM cashpickups
					 WHERE (deleted IS NULL OR deleted = 0)
					 AND receive_id IS NOT NULL
					 AND LOWER(transaction_side) = 'pay'
					 AND LOWER(COALESCE(transaction_status,'')) = 'approved'
					 AND (confirmed_by IS NOT NULL AND confirmed_by != '')
					 AND COALESCE(amount_paid_out,0) > 0
					 AND (
						approval_status IS NULL
						OR approval_status = ''
						OR LOWER(approval_status) IN ('approved','pending')
					 )
					 AND (created_by = :paid_uid OR assigned_user_id = :paid_uid)
					 GROUP BY receive_id",
					[
						'paid_uid' => $user_id,
					]
				) ?: [];
				$paidAssignedMap = [];
				foreach ($paidAssignedRows as $paidAssignedRow) {
					$paidReceiveId = (int)($paidAssignedRow->receive_id ?? 0);
					if ($paidReceiveId > 0) {
						$paidAssignedMap[$paidReceiveId] = (float)($paidAssignedRow->paid_gross ?? 0);
					}
				}

				$pendingAssignedPays = $db->query(
					"SELECT DISTINCT receive_id
					 FROM cashpickups
					 WHERE (deleted IS NULL OR deleted = 0)
					 AND receive_id IS NOT NULL
					 AND LOWER(transaction_side) = 'pay'
					 AND LOWER(COALESCE(approval_status,'pending')) IN ('pending','draft')
					 AND (created_by = :pending_uid OR assigned_user_id = :pending_uid)",
					[
						'pending_uid' => $user_id,
					]
				) ?: [];

				$pendingAssignedPayMap = [];
				foreach ($pendingAssignedPays as $pendingAssignedPay) {
					$pendingReceiveId = (int)($pendingAssignedPay->receive_id ?? 0);
					if ($pendingReceiveId > 0) {
						$pendingAssignedPayMap[$pendingReceiveId] = true;
					}
				}

				$assignedReadyCount = 0;
				foreach ($assignedReadyRows as $assignedReadyRow) {
					$receiveId = (int)($assignedReadyRow->receive_id ?? 0);
					if ($receiveId <= 0 || isset($pendingAssignedPayMap[$receiveId])) {
						continue;
					}
					$assignedGross = (float)($assignedReadyRow->assigned_gross ?? 0);
					$paidGross = (float)($paidAssignedMap[$receiveId] ?? 0);
					if (($assignedGross - $paidGross) <= 0.00001) {
						continue;
					}

					$assignedReadyCount++;
				}

				$total_pending += $assignedReadyCount;
			} catch (\Throwable $e) {
				// ignore assignment badge failures
			}
		}
		$badgeSession->set($badgeCacheKey, ['count' => $total_pending, 'expires' => time() + 10]);
		cashpickup_badge_done:
	}catch(\Throwable $e){
		$total_pending = 0;
	}

	if(user_can('view_cashpickup')){
		$obj = (object)[];
		$obj->title = 'Cash Pickup';
		$obj->id = 'view_cashpickup';
		$obj->link = ROOT . '/' . $vars['admin_route'] . '/' . $vars['plugin_route'];
		$obj->icon = 'fa-solid fa-hand-holding-dollar';
		$obj->parent = 0;
		$obj->count = $total_pending > 0 ? (string)$total_pending : '';
		$links[] = $obj;
	}

	return $links;
});

/** run this after a form submit **/
add_action('controller',function(){

	$req = new \Core\Request;
	$vars = get_value();
	
	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];

	if(URL(1) == $vars['plugin_route'] && URL(2) == 'lookup')
	{
		if(!user_can('view_cashpickup')){
			cashpickup_json_response(['success' => false, 'message' => 'Access denied'], 403);
		}
		try{
			$db = new \Core\Database;
			$customer = trim((string)($_GET['customer_number'] ?? ''));
			$tracking = trim((string)($_GET['tracking_id'] ?? ''));
			if($customer === '' || $tracking === ''){
				cashpickup_json_response(['success' => false, 'message' => 'Missing customer number or tracking id'], 400);
			}
			$row = $db->query(
				"SELECT id, phone_number, tracking_id, sender_details, amount_involved, source, date_created, transaction_status,
				        customer_id, customer_id_type, customer_id_number, customer_location
				 FROM cashpickups
				 WHERE (deleted IS NULL OR deleted = 0)
				 AND LOWER(transaction_side) = 'receive'
				 AND phone_number = :phone
				 AND tracking_id = :tracking
				 ORDER BY date_created DESC, id DESC
				 LIMIT 1",
				['phone' => $customer, 'tracking' => $tracking]
			);
			if(empty($row)){
				cashpickup_json_response(['success' => false, 'message' => 'No matching received pickup found'], 404);
			}
			$r = $row[0];
			cashpickup_json_response([
				'success' => true,
				'data' => [
					'id' => (int)($r->id ?? 0),
					'customer_number' => (string)($r->phone_number ?? ''),
					'tracking_id' => (string)($r->tracking_id ?? ''),
					'customer_details' => (string)($r->sender_details ?? ''),
					'amount_involved' => (float)($r->amount_involved ?? 0),
					'source' => (string)($r->source ?? ''),
					'date_created' => (string)($r->date_created ?? ''),
					'transaction_status' => (string)($r->transaction_status ?? ''),
					'customer_id' => (int)($r->customer_id ?? 0),
					'customer_id_type' => (string)($r->customer_id_type ?? ''),
					'customer_id_number' => (string)($r->customer_id_number ?? ''),
					'customer_location' => (string)($r->customer_location ?? ''),
				]
			]);
		}catch(\Throwable $e){
			cashpickup_json_response(['success' => false, 'message' => 'Lookup failed'], 500);
		}
	}

	if(URL(1) == $vars['plugin_route'] && URL(2) == 'customer_lookup')
	{
		if(!user_can('view_cashpickup')){
			cashpickup_json_response(['success' => false, 'message' => 'Access denied'], 403);
		}
		try{
			require_once plugin_path('helpers/customer-ledger.php');
			$db = new \Core\Database;
			\CashpickupLedger\ensure_cashpickup_customer_schema($db);
			\CashpickupLedger\ensure_cashpickup_columns($db);
			\CashpickupLedger\ensure_cashpickup_customer_approval_columns($db);
			$phone = trim((string)($_GET['phone'] ?? ''));
			$idNumber = trim((string)($_GET['id_number'] ?? ''));
			if($phone === '' && $idNumber === ''){
				cashpickup_json_response(['success' => false, 'message' => 'Missing phone or ID number'], 400);
			}
			$row = \CashpickupLedger\find_customer($db, $idNumber, $phone);
			if(empty($row)){
				$pendingRow = \CashpickupLedger\find_customer_any_status($db, $idNumber, $phone);
				if(!empty($pendingRow) && !\CashpickupLedger\customer_is_approved($pendingRow)){
					cashpickup_json_response([
						'success' => false,
						'message' => 'Customer registration is pending approval.',
						'status' => strtolower(trim((string)($pendingRow->approval_status ?? 'pending'))),
					]);
				}
				// Fallback: try to infer from existing cashpickup transactions
				$tx = $db->query(
					"SELECT sender_details, phone_number, customer_id_type, customer_id_number, customer_location, created_by
					 FROM cashpickups
					 WHERE (deleted IS NULL OR deleted = 0)
					   AND (
					    (phone_number != '' AND phone_number = :phone)
					    OR (customer_id_number != '' AND customer_id_number = :id_number)
					   )
					 ORDER BY date_created DESC, id DESC
					 LIMIT 1",
					['phone' => $phone, 'id_number' => $idNumber]
				) ?? [];
				if (!empty($tx)) {
					$txRow = $tx[0];
					$payload = [
						'full_name' => (string)($txRow->sender_details ?? ''),
						'phone_number' => (string)($txRow->phone_number ?? ''),
						'id_type' => (string)($txRow->customer_id_type ?? ''),
						'id_number' => (string)($txRow->customer_id_number ?? ''),
						'location' => (string)($txRow->customer_location ?? ''),
					];
					$actorId = (int)($txRow->created_by ?? 0);
					$ledgerRow = \CashpickupLedger\upsert_customer($payload, $actorId, $db);
					if (!empty($ledgerRow['id'])) {
						$row = (object)[
							'id' => (int)$ledgerRow['id'],
							'full_name' => (string)($ledgerRow['full_name'] ?? ''),
							'phone_number' => (string)($ledgerRow['phone_number'] ?? ''),
							'id_type' => (string)($ledgerRow['id_type'] ?? ''),
							'id_number' => (string)($ledgerRow['id_number'] ?? ''),
							'location' => (string)($ledgerRow['location'] ?? ''),
						];
					}
				}
				if(empty($row)){
					cashpickup_json_response(['success' => false, 'message' => 'Customer not found'], 404);
				}
			}
			cashpickup_json_response([
				'success' => true,
				'data' => [
					'id' => (int)($row->id ?? 0),
					'full_name' => (string)($row->full_name ?? ''),
					'phone_number' => (string)($row->phone_number ?? ''),
					'id_type' => (string)($row->id_type ?? ''),
					'id_number' => (string)($row->id_number ?? ''),
					'location' => (string)($row->location ?? ''),
				]
			]);
		}catch(\Throwable $e){
			cashpickup_json_response(['success' => false, 'message' => 'Lookup failed'], 500);
		}
	}

	if(URL(1) == $vars['plugin_route'] && URL(2) == 'customer_search')
	{
		if(!user_can('view_cashpickup')){
			cashpickup_json_response(['success' => false, 'message' => 'Access denied'], 403);
		}
		try{
			require_once plugin_path('helpers/customer-ledger.php');
			$db = new \Core\Database;
			\CashpickupLedger\ensure_cashpickup_customer_schema($db);
			\CashpickupLedger\ensure_cashpickup_customer_approval_columns($db);
			$term = trim((string)($_GET['term'] ?? ''));
			if($term === ''){
				cashpickup_json_response(['success' => true, 'data' => []]);
			}
			$rows = \CashpickupLedger\search_customers($db, $term, 12, true);
			$data = [];
			foreach($rows as $row){
				$data[] = [
					'id' => (int)($row->id ?? 0),
					'full_name' => (string)($row->full_name ?? ''),
					'phone_number' => (string)($row->phone_number ?? ''),
					'id_type' => (string)($row->id_type ?? ''),
					'id_number' => (string)($row->id_number ?? ''),
					'location' => (string)($row->location ?? ''),
					'approval_status' => (string)($row->approval_status ?? 'approved'),
				];
			}
			cashpickup_json_response(['success' => true, 'data' => $data]);
		}catch(\Throwable $e){
			cashpickup_json_response(['success' => false, 'message' => 'Search failed'], 500);
		}
	}

	// AJAX list fragment via POST (filters/pagination)
	if(URL(1) == $vars['plugin_route'] && URL(2) == 'ajax_list' && $req->posted())
	{
		if(!user_can('view_cashpickup')){
			http_response_code(403);
			echo 'Access denied';
			exit;
		}
		try{
			$_GET = ['ajax' => '1'];
			foreach($_POST as $k => $v){
				if(!is_string($k)) continue;
				$_GET[$k] = $v;
			}
			ob_start();
			require plugin_path('views/admin/list.php');
			$html = ob_get_clean();
			header('Content-Type: text/html; charset=utf-8');
			echo $html;
			exit;
		}catch(\Throwable $e){
			http_response_code(500);
			echo 'Unable to load list';
			exit;
		}
	}

	// AJAX customers list fragment via POST (filters/pagination)
	if(URL(1) == $vars['plugin_route'] && URL(2) == 'customers_ajax' && $req->posted())
	{
		if(!user_can('view_cashpickup')){
			http_response_code(403);
			echo 'Access denied';
			exit;
		}
		try{
			$_GET = ['ajax' => '1'];
			foreach($_POST as $k => $v){
				if(!is_string($k)) continue;
				$_GET[$k] = $v;
			}
			ob_start();
			require plugin_path('views/admin/customers.php');
			$html = ob_get_clean();
			header('Content-Type: text/html; charset=utf-8');
			echo $html;
			exit;
		}catch(\Throwable $e){
			http_response_code(500);
			echo 'Unable to load customers';
			exit;
		}
	}

	// AJAX customer transactions fragment via POST (filters/pagination)
	if(URL(1) == $vars['plugin_route'] && URL(2) == 'customer_tx_ajax' && $req->posted())
	{
		if(!user_can('view_cashpickup')){
			http_response_code(403);
			echo 'Access denied';
			exit;
		}
		try{
			$_GET = ['ajax' => '1'];
			foreach($_POST as $k => $v){
				if(!is_string($k)) continue;
				$_GET[$k] = $v;
			}
			ob_start();
			require plugin_path('views/admin/customer_view.php');
			$html = ob_get_clean();
			header('Content-Type: text/html; charset=utf-8');
			echo $html;
			exit;
		}catch(\Throwable $e){
			http_response_code(500);
			echo 'Unable to load transactions';
			exit;
		}
	}

	// AJAX list fragment (filters/pagination) - return partial HTML without admin layout
	if(URL(1) == $vars['plugin_route'] && !$req->posted() && (string)($_GET['ajax'] ?? '') === '1')
	{
		if(!user_can('view_cashpickup')){
			http_response_code(403);
			echo 'Access denied';
			exit;
		}
		try{
			ob_start();
			require plugin_path('views/admin/list.php');
			$html = ob_get_clean();
			header('Content-Type: text/html; charset=utf-8');
			echo $html;
			exit;
		}catch(\Throwable $e){
			http_response_code(500);
			echo 'Unable to load list';
			exit;
		}
	}

	// Modal view fragment - return partial HTML without admin layout
	if(URL(1) == $vars['plugin_route'] && URL(2) == 'view' && !$req->posted() && (string)($_GET['modal'] ?? '') === '1')
	{
		if(!user_can('view_cashpickup')){
			http_response_code(403);
			echo 'Access denied';
			exit;
		}
		try{
			$cashpickup = new \Cashpickups\Cashpickup;
			$id = URL(3) ?? null;
			$row = null;
			if($id){
				$row = $cashpickup->first(['id'=>$id]);
			}
			ob_start();
			require plugin_path('views/admin/view.php');
			$html = ob_get_clean();
			header('Content-Type: text/html; charset=utf-8');
			echo $html;
			exit;
		}catch(\Throwable $e){
			http_response_code(500);
			echo 'Unable to load view';
			exit;
		}
	}

	if(URL(1) == $vars['plugin_route'] && URL(2) == 'export' && !$req->posted())
	{
		require plugin_path('controllers/export-controller.php');
		exit;
	}

	if(URL(1) == $vars['plugin_route'] && $req->posted())
	{
		$ses = new \Core\Session;
		$cashpickup = new \Cashpickups\Cashpickup;

		// Persist last submitted payload so inputs can be restored after validation errors
		try {
			$ses->set('cashpickup_old_post', $req->post());
		} catch (\Throwable $e) {
			// ignore
		}

		$id = URL(3) ?? null;
		if($id)
			$row = $cashpickup->first(['id'=>$id]);

		if(URL(2) == 'add'){
			require plugin_path('controllers/add-controller.php');
		}else
		if(URL(2) == 'credit-payout'){
			require plugin_path('controllers/credit-payout-controller.php');
		}else
		if(URL(2) == 'payout'){
			$payoutReceive = $cashpickup->first(['id' => (int)(URL(3) ?? 0)]);
			if (!empty($payoutReceive)
				&& strtolower(trim((string)($payoutReceive->transaction_side ?? ''))) === 'receive'
				&& strtolower(trim((string)($payoutReceive->credit_mode ?? ''))) !== 'credit') {
				require_once plugin_path('helpers/customer-ledger.php');
				$customerDb = new \Core\Database;
				$customerProfileId = \CashpickupLedger\find_customer_id_for_cashpickup($customerDb, $payoutReceive);
				if ($customerProfileId > 0) {
					redirect($admin_route . '/' . $plugin_route . '/customer/' . $customerProfileId . '#withdraw-section');
					exit;
				}
			}
			require plugin_path('controllers/add-controller.php');
		}else
		if(URL(2) == 'transfer'){
			require plugin_path('controllers/transfer-controller.php');
		}else
		if(URL(2) == 'cancel-assignment'){
			require plugin_path('controllers/cancel-assignment-controller.php');
		}else
		if(URL(2) == 'customer-assignment'){
			require plugin_path('controllers/customer-assignment-controller.php');
		}else
		if(URL(2) == 'customer-assignment-cancel'){
			require plugin_path('controllers/customer-assignment-cancel-controller.php');
		}else
		if(URL(2) == 'approve'){
			require plugin_path('controllers/approve-controller.php');
		}else
		if(URL(2) == 'deny'){
			require plugin_path('controllers/deny-controller.php');
		}else
		if(URL(2) == 'cancel'){
			require plugin_path('controllers/cancel-controller.php');
		}else
		if(URL(2) == 'edit'){
			require plugin_path('controllers/edit-controller.php');
		}else
		if(URL(2) == 'delete'){
			require plugin_path('controllers/delete-controller.php');
		}else
		if(URL(2) == 'customer' && $req->posted()){
			require plugin_path('controllers/customer-update-controller.php');
		}else
		if(URL(2) == 'customer_new' && $req->posted()){
			require plugin_path('controllers/customer-create-controller.php');
		}else
			if(URL(2) == 'customer_approve' && $req->posted()){
				require plugin_path('controllers/customer-approve-controller.php');
			}else
			if(URL(2) == 'customer_deny' && $req->posted()){
				require plugin_path('controllers/customer-deny-controller.php');
			}else
			if(URL(2) == 'customer_transfer' && $req->posted()){
				require plugin_path('controllers/customer-transfer-controller.php');
			}else
			if(URL(2) == 'customer_withdraw' && $req->posted()){
				require plugin_path('controllers/customer-withdraw-controller.php');
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

	// If validation failed and page reloaded (no POST), restore old values
	if (!empty($errors) && empty($_POST)) {
		try {
			$oldPost = $ses->get('cashpickup_old_post');
			if (is_array($oldPost) && !empty($oldPost)) {
				$_POST = $oldPost;
				$ses->pop('cashpickup_old_post');
			}
		} catch (\Throwable $e) {
			// ignore
		}
	}

	$cashpickup = new \Cashpickups\Cashpickup;

		try {
			require_once plugin_path('helpers/customer-ledger.php');
			$dbSchema = new \Core\Database;
			\CashpickupLedger\ensure_cashpickup_columns($dbSchema);
			\CashpickupLedger\ensure_cashpickup_customer_schema($dbSchema);
			\CashpickupLedger\ensure_cashpickup_credit_schema($dbSchema);
			\CashpickupLedger\ensure_cashpickup_transfer_columns($dbSchema);
		} catch (\Throwable $e) {
			// ignore
		}

	if(URL(1) == $vars['plugin_route']){

		$id = URL(3) ?? null;
		if($id){
			$row = $cashpickup->first(['id'=>$id]);
		}

		$session = get_session();
		$getCashdrawerData = function() use ($session) {
			$cashdrawerDenos = [];
			$cashdrawerBalances = [];
			try {
				$drawerDb = new \Core\Database;
				$today = date('Y-m-d');
				$drawerRow = $drawerDb->query(
					"SELECT id FROM cashdrawers WHERE user_id = :user_id AND DATE(date_created) = :today ORDER BY id DESC LIMIT 1",
					['user_id' => $session->id, 'today' => $today]
				);
				if (empty($drawerRow)) {
					$drawerRow = $drawerDb->query(
						"SELECT id FROM cashdrawers WHERE user_id = :user_id ORDER BY date_created DESC, id DESC LIMIT 1",
						['user_id' => $session->id]
					);
				}
				if (!empty($drawerRow)) {
					$drawer_id = (int)$drawerRow[0]->id;
					$denoRows = $drawerDb->query(
						"SELECT denomination, SUM(count) as count FROM drawer_denos WHERE drawer_id = :drawer_id GROUP BY denomination",
						['drawer_id' => $drawer_id]
					);
					if (!empty($denoRows)) {
						foreach ($denoRows as $row) {
							$cashdrawerDenos[] = [
								'denomination' => $row->denomination,
								'count' => $row->count
							];
						}
					}
					$balRows = $drawerDb->query(
						"SELECT channel_key, closing_balance FROM drawer_balances WHERE drawer_id = :drawer_id",
						['drawer_id' => $drawer_id]
					);
					if (!empty($balRows)) {
						foreach ($balRows as $row) {
							$cashdrawerBalances[] = [
								'channel_key' => $row->channel_key,
								'closing_balance' => $row->closing_balance
							];
						}
					}
				}
			} catch (\Throwable $e) {
				// fail silently
			}
			return ['denos' => $cashdrawerDenos, 'balances' => $cashdrawerBalances];
		};
		$cashdrawerData = ['denos' => [], 'balances' => []];
		if (in_array((string)URL(2), ['add', 'edit', 'payout'], true)) {
			$cashdrawerData = $getCashdrawerData();
		}

		if(URL(2) == 'add'){
			require plugin_path('views/admin/add.php');
		}else
		if(URL(2) == 'customers'){
			require plugin_path('views/admin/customers.php');
		}else
		if(URL(2) == 'customer_new'){
			require plugin_path('views/admin/customer_new.php');
		}else
		if(URL(2) == 'customer'){
			require plugin_path('views/admin/customer_view.php');
		}else
		if(URL(2) == 'credit-payout'){
			$canCreateCreditPayout = user_can('is_admin') || user_can('is_manager');
			if(!$canCreateCreditPayout){
				$ses->set('error', 'Access denied');
				header('Location: '. ROOT .'/'.$admin_route.'/'.$plugin_route.'/customers');
				die;
			}
			require plugin_path('views/admin/credit_payout.php');
		}else
		if(URL(2) == 'payout'){
			if(user_can('is_manager') || user_can('is_admin')){
				$ses->set('error', 'Please transfer to agent to perform payout');
				header('Location: '. ROOT .'/'.$admin_route.'/'.$plugin_route);
				die;
			}
			$receiveId = URL(3) ?? null;
			$receiveRow = null;
			if($receiveId){
				$receiveRow = $cashpickup->first(['id'=>$receiveId]);
			}
			if(empty($receiveRow) || strtolower((string)($receiveRow->transaction_side ?? '')) !== 'receive'){
				$row = null;
				require plugin_path('views/admin/view.php');
			}else{
				try{
					$session = get_session();
					$uid = (int)($session->id ?? 0);
					$assignedTo = (int)($receiveRow->assigned_user_id ?? 0);
					$canAccessPayout = false;
					if($assignedTo > 0 && $uid > 0 && $uid === $assignedTo){
						$canAccessPayout = true;
					}
					try {
						$allocDb = new \Core\Database;
						if (function_exists('CashpickupTransferAllocation\\get_transfer_remaining_amount')) {
							$assignedGross = \CashpickupTransferAllocation\get_transfer_allocation($allocDb, (int)($receiveRow->id ?? 0), (int)$uid);
							$remainingGross = \CashpickupTransferAllocation\get_transfer_remaining_amount($allocDb, (int)($receiveRow->id ?? 0), (int)$uid);
							if ((float)($assignedGross['gross'] ?? 0) > 0.00001 && (float)$remainingGross > 0.00001) {
								$canAccessPayout = true;
							}
						}
					} catch (\Throwable $e) {}
					if(!$canAccessPayout){
						$ses->set('error', 'Access denied');
						header('Location: '. ROOT .'/'.$admin_route.'/'.$plugin_route.'/view/'.$receiveRow->id);
						die;
					}
				}catch(\Throwable $e){}

				$recvApproval = strtolower(trim((string)($receiveRow->approval_status ?? 'pending')));
				$recvStatus = strtolower(trim((string)($receiveRow->transaction_status ?? '')));
				$isCreditPayout = strtolower(trim((string)($receiveRow->credit_mode ?? ''))) === 'credit';
				if($recvApproval === '') $recvApproval = 'pending';
				$canOpenPendingCreditPayout = $isCreditPayout
					&& in_array($recvApproval, ['pending', 'approved'], true)
					&& in_array($recvStatus, ['', 'pending payout', 'approved'], true);
				if($recvApproval !== 'approved' && !$canOpenPendingCreditPayout){
					$ses->set('error', 'This cashpickup is not approved and cannot be paid out');
					header('Location: '. ROOT .'/'.$admin_route.'/'.$plugin_route.'/view/'.$receiveRow->id);
					die;
				}
				$payout_mode = true;
				$payouts = [];
				$remaining_balance = 0;
				$is_fully_paid = false;
				try {
					$dbP = new \Core\Database;
					$payouts = $dbP->query(
						"SELECT id, date_created, amount_requested, pickup_charge, amount_paid_out, payment_method, confirmed_by
						 FROM cashpickups
						 WHERE (deleted IS NULL OR deleted = 0)
						 AND LOWER(transaction_side) = 'pay'
						 AND receive_id = :rid
						 ORDER BY date_created DESC, id DESC",
						['rid' => (int)$receiveRow->id]
					) ?? [];
					$sumRow = $dbP->query(
						"SELECT COALESCE(SUM(COALESCE(amount_paid_out,0) + COALESCE(pickup_charge,0)),0) AS paid
						 FROM cashpickups
						 WHERE (deleted IS NULL OR deleted = 0)
						 AND LOWER(transaction_side) = 'pay'
						 AND (
							approval_status IS NULL
							OR approval_status = ''
							OR LOWER(approval_status) IN ('approved','pending')
						 )
						 AND transaction_status = 'Approved'
						 AND (confirmed_by IS NOT NULL AND confirmed_by != '')
						 AND COALESCE(amount_paid_out,0) > 0
						 AND receive_id = :rid",
						['rid' => (int)$receiveRow->id]
					);
					$alreadyPaid = (float)($sumRow[0]->paid ?? 0);
					$remaining_balance = max(0, (float)($receiveRow->amount_involved ?? 0) - $alreadyPaid);
					$is_fully_paid = ($remaining_balance <= 0.00001);
				} catch(\Throwable $e) {
					$payouts = [];
					$remaining_balance = (float)($receiveRow->amount_involved ?? 0);
				}
				$assignedGross = 0.0;
				$assignedCharge = 0.0;
				$assignedPayable = 0.0;
				$assignedRemainingGross = 0.0;
				try {
					$allocDb = new \Core\Database;
					if (function_exists('CashpickupTransferAllocation\\get_transfer_allocation')) {
						$allocation = \CashpickupTransferAllocation\get_transfer_allocation($allocDb, (int)($receiveRow->id ?? 0), (int)$uid);
						$assignedGross = (float)($allocation['gross'] ?? 0);
						$assignedCharge = (float)($allocation['charge'] ?? 0);
						$assignedPayable = (float)($allocation['payable'] ?? 0);
						$assignedRemainingGross = $assignedGross;
					}
				} catch (\Throwable $e) {
					$assignedGross = 0.0;
					$assignedCharge = 0.0;
					$assignedPayable = 0.0;
					$assignedRemainingGross = 0.0;
				}
				if ($assignedGross > 0) {
					$remaining_balance = $assignedRemainingGross;
				}
				$prefillGross = $assignedGross > 0 ? $assignedRemainingGross : (float)($remaining_balance ?? 0);
				$prefillCharge = $assignedGross > 0 ? $assignedCharge : 0.0;
				$prefillPayable = max(0, $prefillGross - $prefillCharge);
				$_POST = (array)$receiveRow;
				$_POST['transaction_side'] = 'pay';
				$_POST['receive_id'] = (int)($receiveRow->id ?? 0);
				$_POST['source'] = (string)($receiveRow->source ?? '');
				$_POST['payment_method'] = '';
				$_POST['pickup_charge'] = (string)number_format($prefillCharge, 2, '.', '');
				$_POST['amount_paid_out'] = (string)number_format($prefillPayable, 2, '.', '');
				$_POST['pay_type'] = 'all';
				$_POST['amount_requested'] = (string)number_format($prefillGross, 2, '.', '');
				$_POST['amount_transferred'] = (string)number_format($prefillGross, 2, '.', '');
				$_POST['amount_involved'] = (string)number_format($prefillGross, 2, '.', '');
				$_POST['assigned_payout_amount'] = (string)number_format($prefillGross, 2, '.', '');
				$_POST['assigned_payout_charge'] = (string)number_format($prefillCharge, 2, '.', '');
				$_POST['assigned_payout_payable'] = (string)number_format($prefillPayable, 2, '.', '');
				$_POST['confirmed_by'] = '';
				$_POST['pickup_person_idcard'] = '';
				$_POST['pickup_person_signature'] = '';
				$_POST['pickup_person_phone'] = '';
				$_POST['idcard_photo'] = '';
				$_POST['customer_photo'] = '';
				require plugin_path('views/admin/add.php');
			}
		}else
		if(URL(2) == 'edit'){
			if(!(user_can('is_manager') || user_can('is_admin'))){
				$ses->set('error', 'Access denied');
				header('Location: '. ROOT .'/'.$admin_route.'/'.$plugin_route);
				die;
			}
			require plugin_path('views/admin/edit.php');
		}else
		if(URL(2) == 'delete'){
			require plugin_path('views/admin/delete.php');
		}else
		if(URL(2) == 'view'){
			$isModalView = ((string)($_GET['modal'] ?? '') === '1');
			if(!$isModalView && !empty($row) && strtolower(trim((string)($row->transaction_side ?? ''))) === 'pay'){
				try {
					$dbView = new \Core\Database;
					$receiveId = (int)($row->receive_id ?? 0);
					$trackingId = trim((string)($row->tracking_id ?? ''));
					$receiveRows = [];
					if ($receiveId > 0) {
						$receiveRows = $dbView->query(
							"SELECT *
							 FROM cashpickups
							 WHERE (deleted IS NULL OR deleted = 0)
							 AND LOWER(transaction_side) = 'receive'
							 AND id = :id
							 LIMIT 1",
							['id' => $receiveId]
						) ?: [];
					}
					if (empty($receiveRows) && $trackingId !== '') {
						$receiveRows = $dbView->query(
							"SELECT *
							 FROM cashpickups
							 WHERE (deleted IS NULL OR deleted = 0)
							 AND LOWER(transaction_side) = 'receive'
							 AND tracking_id = :tracking_id
							 ORDER BY date_created ASC, id ASC
							 LIMIT 1",
							['tracking_id' => $trackingId]
						) ?: [];
					}
					if (!empty($receiveRows)) {
						$row = $receiveRows[0];
					}
				} catch (\Throwable $e) {}
			}
			require plugin_path('views/admin/view.php');
			}else
			if(URL(2) == '' || URL(2) == 'list'){
				// Cashpickup opens at the customer account list for every role.
				require plugin_path('views/admin/customers.php');
			}else
			{
				require plugin_path('views/admin/list.php');
			}

		?>
		<script>
			document.addEventListener('submit', async function(event){
				const form = event.target;
				if (!(form instanceof HTMLFormElement) || !form.matches('.js-cashpickup-assignment-cancel')) return;
				event.preventDefault();
				if (form.dataset.cancelPending === '1') return;
				form.dataset.cancelPending = '1';
				try {
					let confirmed = false;
					if (window.Swal && typeof window.Swal.fire === 'function') {
						const result = await window.Swal.fire({
							title: 'Cancel payout assignment?',
							text: form.dataset.cancelConfirmation || 'Cancel the remaining assigned payout balance?',
							icon: 'warning',
							showCancelButton: true,
							confirmButtonText: 'Yes, cancel assignment',
							cancelButtonText: 'Keep assignment',
							confirmButtonColor: '#dc3545',
							reverseButtons: true,
							focusCancel: true
						});
						confirmed = result.isConfirmed === true;
					} else {
						confirmed = window.confirm(form.dataset.cancelConfirmation || 'Cancel the remaining assigned payout balance?');
					}
					if (!confirmed) return;
					HTMLFormElement.prototype.submit.call(form);
				} catch (error) {
					console.error('Unable to confirm payout assignment cancellation.', error);
					if (window.Swal && typeof window.Swal.fire === 'function') {
						window.Swal.fire({icon: 'error', title: 'Unable to cancel assignment', text: 'Please try again.'});
					} else {
						window.alert('Unable to confirm payout assignment cancellation. Please try again.');
					}
				} finally {
					delete form.dataset.cancelPending;
				}
			});
		</script>
		<?php
	}
});

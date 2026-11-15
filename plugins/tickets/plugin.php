<?php

set_value([

	'admin_route'	=>'admin',
	'plugin_route'	=>'tickets',
	'tables'		=>[
		'tickets_table' 				=> 'tickets',
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

/** set ticket permissions for this plugin **/
add_filter('permissions',function($permissions){

	$permissions[] = 'view_tickets';
	$permissions[] = 'view_tickets_details';
	$permissions[] = 'add_tickets';
	$permissions[] = 'edit_tickets';
	$permissions[] = 'delete_tickets';

	return $permissions;
});

if(!function_exists('tickets_online_user_ids')){
	function tickets_online_user_ids(): array
	{
		$ids = [];
		$rows = get_online_users() ?: [];
		if(!is_array($rows)) {
			return [];
		}
		foreach($rows as $row){
			$id = (int)($row->user_id ?? 0);
			if($id > 0) {
				$ids[$id] = true;
			}
		}
		return array_keys($ids);
	}
}

if(!function_exists('tickets_users_with_cashdrawer_today')){
	function tickets_users_with_cashdrawer_today(int $excludeUserId = 0): array
	{
		try{
			$db = new \Core\Database;
			if(!$db->table_exists(['users', 'cashdrawers'])){
				return [];
			}
			$today = (new \DateTimeImmutable('now', new \DateTimeZone('Africa/Accra')))->format('Y-m-d');
			$params = ['today' => $today];
			$excludeSql = '';
			if($excludeUserId > 0){
				$excludeSql = ' AND u.id <> :exclude_user_id';
				$params['exclude_user_id'] = $excludeUserId;
			}
			$query = "
				SELECT DISTINCT u.*
				FROM users AS u
				INNER JOIN cashdrawers AS c ON c.user_id = u.id AND DATE(c.date_created) = :today
				WHERE u.deleted = 0 {$excludeSql}
				ORDER BY u.first_name ASC, u.last_name ASC, u.id ASC
			";
			$rows = $db->query($query, $params) ?: [];
			return is_array($rows) ? $rows : [];
		}catch(\Throwable $e){
			return [];
		}
	}
}

if(!function_exists('tickets_online_users')){
	function tickets_online_users(): array
	{
		$ids = tickets_online_user_ids();
		if(empty($ids)){
			return [];
		}
		try{
			$db = new \Core\Database;
			if(!$db->table_exists(['users'])){
				return [];
			}
			$in = implode(',', array_map('intval', $ids));
			$rows = $db->query("SELECT * FROM users WHERE deleted = 0 AND id IN ($in) ORDER BY first_name ASC, last_name ASC") ?: [];
			return is_array($rows) ? $rows : [];
		}catch(\Throwable $e){
			return [];
		}
	}
}

if(!function_exists('tickets_user_is_online')){
	function tickets_user_is_online(int $userId, array $onlineIds = []): bool
	{
		$userId = (int)$userId;
		if($userId <= 0){
			return false;
		}
		if(empty($onlineIds)){
			$onlineIds = tickets_online_user_ids();
		}
		return in_array($userId, array_map('intval', $onlineIds), true);
	}
}

if(!function_exists('tickets_online_indicator_html')){
	function tickets_online_indicator_html(int $userId, array $onlineIds = []): string
	{
		$isOnline = tickets_user_is_online($userId, $onlineIds);
		if(!$isOnline){
			return '';
		}

		return '<span class="d-inline-block rounded-circle bg-success ms-1 align-middle" title="Online" aria-label="Online" style="width:10px;height:10px;"></span>';
	}
}

if(!function_exists('tickets_online_users_strip_html')){
	function tickets_online_users_strip_html(array $onlineUsers): string
	{
		if(empty($onlineUsers)){
			return '<div class="small ticket-online-dots" data-ticket-online-users-strip="1"><strong>Online now:</strong> No users detected online.</div>';
		}
		$html = '<div class="p-0 m-0 ticket-online-dots d-flex flex-wrap gap-2" data-ticket-online-users-strip="1"><div class="fw-semibold mb-2">Online now</div><div class=" d-flex flex-wrap gap-2 text-muted">';
		foreach($onlineUsers as $user){
			$name = trim((string)($user->first_name ?? '') . ' ' . (string)($user->last_name ?? ''));
			$title = $name !== '' ? $name : ('User #' . (int)($user->id ?? 0));
			$html .= '<span class="d-inline-flex align-items-center gap-1 px-2 py-0 rounded-pill border" title="' . esc($title) . '" aria-label="' . esc($title) . ' online">';
			$html .= '<span class="d-inline-block rounded-circle bg-success" style="width:10px;height:10px;"></span>';
			$html .= '<span class="fw-semibold">' . esc($title) . '</span>';
			$html .= '</span>';
		}
		$html .= '</div></div>';
		return $html;
	}
}

if(!function_exists('tickets_user_can_view_all')){
	function tickets_user_can_view_all(): bool
	{
		return user_can('is_admin');
	}
}

if(!function_exists('tickets_unread_response_count')){
	function tickets_unread_response_count(int $userId): int
	{
		if($userId <= 0){
			return 0;
		}
		try{
			$db = new \Core\Database;
			if(!$db->table_exists(['tickets', 'ticket_responses'])){
				return 0;
			}
			$rows = $db->query(
				"SELECT COUNT(r.id) AS cnt
				 FROM ticket_responses AS r
				 INNER JOIN tickets AS t ON t.id = r.ticket_id
				 WHERE r.seen = 0
				 AND r.created_by != :uid
				 AND (t.created_by = :uid OR t.assigned = :uid)",
				['uid' => $userId]
			) ?: [];
			$unreadResponses = (int)($rows[0]->cnt ?? 0);

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
			$newAssignedTickets = (int)($rows[0]->cnt ?? 0);

			return $unreadResponses + $newAssignedTickets;
		}catch(\Throwable $e){
			return 0;
		}
	}
}

if(!function_exists('tickets_notification_script')){
	function tickets_notification_script(int $userId, string $adminRoute, string $pluginRoute): string
	{
		if($userId <= 0){
			return '';
		}
		ob_start();
		?>
		<script>
		(function(){
			const userId = <?=json_encode($userId)?>;
			const notifyUrl = <?=json_encode(ROOT . '/' . $adminRoute . '/' . $pluginRoute . '/notifications')?>;
			const onlineUrl = <?=json_encode(ROOT . '/' . $adminRoute . '/' . $pluginRoute . '/online-users')?>;
			const storageKey = 'tickets.notify.last.' + userId;
			let initialized = false;
			let last = {ticket_id: 0, response_id: 0};
			try {
				const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
				last.ticket_id = parseInt(saved.ticket_id || 0, 10) || 0;
				last.response_id = parseInt(saved.response_id || 0, 10) || 0;
			} catch(e) {}

			function beep(){
				if(window.tormorPlayAttentionSound){
					window.tormorPlayAttentionSound('tickets-live-' + Date.now());
					return;
				}
				try{
					const AudioCtx = window.AudioContext || window.webkitAudioContext;
					if(!AudioCtx) return;
					const ctx = new AudioCtx();
					const osc = ctx.createOscillator();
					const gain = ctx.createGain();
					osc.type = 'sine';
					osc.frequency.value = 880;
					gain.gain.setValueAtTime(0.001, ctx.currentTime);
					gain.gain.exponentialRampToValueAtTime(0.18, ctx.currentTime + 0.02);
					gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.28);
					osc.connect(gain);
					gain.connect(ctx.destination);
					osc.start();
					osc.stop(ctx.currentTime + 0.3);
				}catch(e){}
			}
			window.ticketsPlayNotificationBeep = beep;
			window.ticketsMarkNotificationSeen = function(ticketId, responseId){
				const tid = parseInt(ticketId || 0, 10) || 0;
				const rid = parseInt(responseId || 0, 10) || 0;
				if(tid > last.ticket_id) last.ticket_id = tid;
				if(rid > last.response_id) last.response_id = rid;
				try{ localStorage.setItem(storageKey, JSON.stringify(last)); }catch(e){}
			};
			function updateTicketsBadge(count){
				const n = parseInt(count || 0, 10) || 0;
				let badges = document.querySelectorAll('[data-notification-badge="view_tickets"]');
				if(!badges.length){
					const nav = document.querySelector('#navItem_Tickets a.nav-link');
					if(nav){
						const badge = document.createElement('span');
						badge.className = 'badge fw-bolder rounded-pill';
						badge.setAttribute('data-notification-badge', 'view_tickets');
						badge.setAttribute('data-notification-urgency', 'serious');
							nav.appendChild(badge);
							if(window && window.tormorApplyPillStyle){
								try{ window.tormorApplyPillStyle(badge); }catch(e){}
							}
							badges = [badge];
					}
				}
				badges.forEach(function(badge){
					if(n > 0){
						badge.textContent = String(n);
						badge.classList.remove('d-none');
					}else{
						badge.textContent = '';
						badge.classList.add('d-none');
					}
				});
			}
			async function refreshTicketNotifications(){
				try{
					const url = notifyUrl + '?ticket_after=' + encodeURIComponent(last.ticket_id) + '&response_after=' + encodeURIComponent(last.response_id);
					const res = await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
					const obj = await res.json();
					if(!obj || !obj.success || !obj.data) return;
					const data = obj.data || {};
					updateTicketsBadge(data.unread_total || 0);
					const latestTicket = parseInt(data.latest_ticket_id || 0, 10) || 0;
					const latestResponse = parseInt(data.latest_response_id || 0, 10) || 0;
					if(initialized && ((latestTicket > last.ticket_id) || (latestResponse > last.response_id))){
						beep();
					}
					if(latestTicket > last.ticket_id) last.ticket_id = latestTicket;
					if(latestResponse > last.response_id) last.response_id = latestResponse;
					try{ localStorage.setItem(storageKey, JSON.stringify(last)); }catch(e){}
					initialized = true;
				}catch(e){}
			}
			async function refreshOnlineUsers(){
				try{
					const strips = document.querySelectorAll('[data-ticket-online-users-strip="1"]');
					if(!strips.length) return;
					const res = await fetch(onlineUrl, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
					const obj = await res.json();
					if(!obj || !obj.success || !obj.html) return;
					strips.forEach(function(strip){
						const tmp = document.createElement('div');
						tmp.innerHTML = obj.html;
						const next = tmp.firstElementChild;
						if(next) strip.replaceWith(next);
					});
				}catch(e){}
			}
			refreshTicketNotifications();
			refreshOnlineUsers();
			setInterval(refreshTicketNotifications, 3000);
			setInterval(refreshOnlineUsers, 5000);
		})();
		</script>
		<?php
		return ob_get_clean();
	}
}


/** add to amin links **/
add_filter('basic-admin_before_admin_links', function($links) {

    $vars = get_value();
    $ticket = new \Tickets\Ticket;        
    $session = get_session();
    $user_id = $session->id;        

    $countBadge = '';
	try{
		$total_unseen = tickets_unread_response_count((int)$user_id);
		$countBadge = $total_unseen > 0 ? (string)$total_unseen : '';
	}catch(\Throwable $e){}

    $obj = (object)[];
    $obj->title = 'Tickets';
    $obj->id = 'view_tickets';
    $obj->link = ROOT . '/' . $vars['admin_route'] . '/' . $vars['plugin_route'];
    $obj->icon = 'fas fa-ticket-alt';
    $obj->parent = 0;
    $obj->count = $countBadge;

    $links[] = $obj;
    return $links;
});



/** run this after a form submit **/
add_action('controller',function(){

	$req = new \Core\Request;
	$vars = get_value();
	
	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];

	if(URL(1) == $vars['plugin_route'] && URL(2) == 'online-users')
	{
		$session = get_session();
		$user_id = (int)($session->id ?? 0);

		header('Content-Type: application/json');
		if(!$user_id){
			echo json_encode(['success'=>false,'html'=>'']);
			exit;
		}

		echo json_encode([
			'success' => true,
			'html' => tickets_online_users_strip_html(tickets_online_users()),
		]);
		exit;
	}

	if(URL(1) == $vars['plugin_route'] && URL(2) == 'detail')
	{
		$session = get_session();
		$user_id = (int)($session->id ?? 0);
		$ticketId = (int)(URL(3) ?? 0);

		header('Content-Type: application/json');
		if(!$user_id || !$ticketId){
			echo json_encode(['success'=>false,'message'=>'Ticket not found.','data'=>[]]);
			exit;
		}

		$db = new \Core\Database;
		$ticketRows = $db->query(
			"SELECT
				m.*,
				u.first_name AS user_first_name,
				u.last_name AS user_last_name,
				u.image AS user_image,
				u_assigned.first_name AS assigned_first_name,
				u_assigned.last_name AS assigned_last_name,
				u_assigned.image AS assigned_image
			 FROM tickets AS m
			 LEFT JOIN users AS u ON m.created_by = u.id
			 LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id
			 WHERE m.id = :id
			 LIMIT 1",
			['id'=>$ticketId]
		) ?: [];
		$row = $ticketRows[0] ?? null;
		$createdBy = (int)($row->created_by ?? 0);
		$assignedTo = (int)($row->assigned ?? 0);
		if(empty($row) || (!tickets_user_can_view_all() && $createdBy !== $user_id && $assignedTo !== $user_id)){
			echo json_encode(['success'=>false,'message'=>'Ticket not found or access denied.','data'=>[]]);
			exit;
		}

		$responseRows = $db->query(
			"SELECT r.*, u.first_name, u.last_name, u.image AS u_image
			 FROM ticket_responses AS r
			 LEFT JOIN users AS u ON r.created_by = u.id
			 WHERE r.ticket_id = :tid
			 ORDER BY r.id ASC",
			['tid'=>$ticketId]
		) ?: [];

		try{
			$db->query(
				"UPDATE ticket_responses SET seen = 1 WHERE ticket_id = :tid AND created_by != :uid AND seen = 0",
				['tid'=>$ticketId,'uid'=>$user_id]
			);
		}catch(\Throwable $e){}

		$responses = [];
		foreach($responseRows as $r){
			if(!is_object($r)) continue;
			$fullname = trim((string)(($r->first_name ?? '').' '.($r->last_name ?? '')));
			$responses[] = [
				'id' => (int)($r->id ?? 0),
				'ticket_id' => (int)($r->ticket_id ?? 0),
				'response' => (string)($r->response ?? ''),
				'image' => !empty($r->image) ? get_image($r->image) : null,
				'sender_id' => (int)($r->sender_id ?? $r->created_by ?? 0),
				'sender_name' => $fullname,
				'user_image' => get_image($r->u_image ?? null),
				'date_created' => !empty($r->date_created) ? get_date_time($r->date_created) : '',
				'seen' => (int)($r->seen ?? 0),
			];
		}

		echo json_encode([
			'success'=>true,
			'data'=>[
				'ticket'=>[
					'id'=>(int)($row->id ?? 0),
					'subject'=>(string)($row->subject ?? ''),
					'description'=>(string)($row->description ?? ''),
					'status'=>(string)($row->status ?? ''),
					'date_created'=>!empty($row->date_created) ? get_date_time($row->date_created) : '',
					'created_by'=>$createdBy,
					'creator_name'=>trim((string)(($row->user_first_name ?? '').' '.($row->user_last_name ?? ''))),
					'creator_image'=>get_image($row->user_image ?? null),
					'assigned'=>$assignedTo,
				],
				'responses'=>$responses,
				'total_unseen'=>tickets_unread_response_count($user_id),
			],
		]);
		exit;
	}

	if(URL(1) == $vars['plugin_route'] && URL(2) == 'poll')
	{
		$session = get_session();
		$user_id = (int)($session->id ?? 0);
		$ticketId = (int)(URL(3) ?? 0);
		$after = (int)($_GET['after'] ?? 0);

		header('Content-Type: application/json');
		if(!$user_id || !$ticketId){
			echo json_encode(['success'=>false,'data'=>[]]);
			exit;
		}

		$ticket = new \Tickets\Ticket;
		$ticketRow = $ticket->first(['id'=>$ticketId]);
		$createdBy = (int)($ticketRow->created_by ?? 0);
		$assignedTo = (int)($ticketRow->assigned ?? 0);
		if(empty($ticketRow) || (!tickets_user_can_view_all() && $createdBy !== $user_id && $assignedTo !== $user_id)){
			echo json_encode(['success'=>false,'data'=>[]]);
			exit;
		}

		$db = new \Core\Database;
		$query = "
			SELECT r.*, u.first_name, u.last_name, u.image AS u_image
			FROM ticket_responses AS r
			LEFT JOIN users AS u ON r.created_by = u.id
			WHERE r.ticket_id = :tid AND r.id > :after
			ORDER BY r.id ASC
		";
		$rows = $db->query($query, ['tid'=>$ticketId,'after'=>$after]) ?? [];
		if(!is_array($rows)) $rows = [];

		$data = [];
		foreach($rows as $r){
			if(!is_object($r)) continue;
			$fullname = trim((string)(($r->first_name ?? '').' '.($r->last_name ?? '')));
			$data[] = [
				'id' => (int)($r->id ?? 0),
				'ticket_id' => (int)($r->ticket_id ?? 0),
				'response' => (string)($r->response ?? ''),
				'image' => !empty($r->image) ? get_image($r->image) : null,
				'sender_id' => (int)($r->sender_id ?? $r->created_by ?? 0),
				'sender_name' => $fullname,
				'user_image' => get_image($r->u_image ?? null),
				'date_created' => !empty($r->date_created) ? get_date_time($r->date_created) : '',
			];
		}

		try{
			$db->query(
				"UPDATE ticket_responses SET seen = 1 WHERE ticket_id = :tid AND created_by != :uid AND seen = 0",
				['tid'=>$ticketId,'uid'=>$user_id]
			);
		}catch(\Throwable $e){}

		echo json_encode(['success'=>true,'data'=>$data]);
		exit;
	}

	if(URL(1) == $vars['plugin_route'] && URL(2) == 'seen')
	{
		$session = get_session();
		$user_id = (int)($session->id ?? 0);
		$ticketId = (int)(URL(3) ?? 0);
		$upto = (int)($_GET['upto'] ?? 0);

		header('Content-Type: application/json');
		if(!$user_id || !$ticketId || !$upto){
			echo json_encode(['success'=>false]);
			exit;
		}

		$ticket = new \Tickets\Ticket;
		$ticketRow = $ticket->first(['id'=>$ticketId]);
		$createdBy = (int)($ticketRow->created_by ?? 0);
		$assignedTo = (int)($ticketRow->assigned ?? 0);
		if(empty($ticketRow) || (!tickets_user_can_view_all() && $createdBy !== $user_id && $assignedTo !== $user_id)){
			echo json_encode(['success'=>false]);
			exit;
		}

		$db = new \Core\Database;
		try{
			$db->query(
				"UPDATE ticket_responses SET seen = 1 WHERE ticket_id = :tid AND id <= :upto AND created_by != :uid",
				['tid'=>$ticketId,'upto'=>$upto,'uid'=>$user_id]
			);
		}catch(\Throwable $e){}

		echo json_encode(['success'=>true]);
		exit;
	}

	if(URL(1) == $vars['plugin_route'] && URL(2) == 'counts')
	{
		$session = get_session();
		$user_id = (int)($session->id ?? 0);
		$ticketId = (int)(URL(3) ?? 0);

		header('Content-Type: application/json');
		if(!$user_id || !$ticketId){
			echo json_encode(['success'=>false,'data'=>[]]);
			exit;
		}

		$ticket = new \Tickets\Ticket;
		$ticketRow = $ticket->first(['id'=>$ticketId]);
		$createdBy = (int)($ticketRow->created_by ?? 0);
		$assignedTo = (int)($ticketRow->assigned ?? 0);
		if(empty($ticketRow) || (!tickets_user_can_view_all() && $createdBy !== $user_id && $assignedTo !== $user_id)){
			echo json_encode(['success'=>false,'data'=>[]]);
			exit;
		}

		$db = new \Core\Database;
		$ticketUnseen = 0;
		$totalUnseen = 0;

		try{
			$r = $db->query(
				"SELECT COUNT(*) AS cnt FROM ticket_responses WHERE ticket_id = :tid AND seen = 0 AND created_by != :uid",
				['tid'=>$ticketId,'uid'=>$user_id]
			);
			$ticketUnseen = (int)($r[0]->cnt ?? 0);
		}catch(\Throwable $e){}

		try{
			$totalUnseen = tickets_unread_response_count((int)$user_id);
		}catch(\Throwable $e){}

		echo json_encode(['success'=>true,'data'=>['ticket_unseen'=>$ticketUnseen,'total_unseen'=>$totalUnseen]]);
		exit;
	}

	if(URL(1) == $vars['plugin_route'] && URL(2) == 'notifications')
	{
		$session = get_session();
		$user_id = (int)($session->id ?? 0);
		$ticketAfter = (int)($_GET['ticket_after'] ?? 0);
		$responseAfter = (int)($_GET['response_after'] ?? 0);

		header('Content-Type: application/json');
		if(!$user_id){
			echo json_encode(['success'=>false,'data'=>[]]);
			exit;
		}

		$db = new \Core\Database;
		$latestTicketId = 0;
		$latestResponseId = 0;
		$newTickets = 0;
		$newResponses = 0;
		$unreadTotal = tickets_unread_response_count($user_id);

		try{
			$rows = $db->query(
				"SELECT COALESCE(MAX(id), 0) AS latest_id,
				        SUM(CASE WHEN id > :after_id THEN 1 ELSE 0 END) AS new_count
				 FROM tickets
				 WHERE assigned = :uid
				 AND created_by != :uid",
				['uid'=>$user_id, 'after_id'=>$ticketAfter]
			) ?: [];
			$latestTicketId = (int)($rows[0]->latest_id ?? 0);
			$newTickets = (int)($rows[0]->new_count ?? 0);
		}catch(\Throwable $e){}

		try{
			$rows = $db->query(
				"SELECT COALESCE(MAX(r.id), 0) AS latest_id,
				        SUM(CASE WHEN r.id > :after_id THEN 1 ELSE 0 END) AS new_count
				 FROM ticket_responses AS r
				 INNER JOIN tickets AS t ON t.id = r.ticket_id
				 WHERE r.created_by != :uid
				 AND (t.created_by = :uid OR t.assigned = :uid)",
				['uid'=>$user_id, 'after_id'=>$responseAfter]
			) ?: [];
			$latestResponseId = (int)($rows[0]->latest_id ?? 0);
			$newResponses = (int)($rows[0]->new_count ?? 0);
		}catch(\Throwable $e){}

		echo json_encode([
			'success'=>true,
			'data'=>[
				'latest_ticket_id'=>$latestTicketId,
				'latest_response_id'=>$latestResponseId,
				'new_tickets'=>$newTickets,
				'new_responses'=>$newResponses,
				'unread_total'=>$unreadTotal,
			],
		]);
		exit;
	}

	if(URL(1) == $vars['plugin_route'] && $req->posted())
	{
		if(in_array(URL(2), ['view', 'add', 'edit', 'delete'], true)){
			header('Content-Type: application/json; charset=utf-8');
		}

		$ses = new \Core\Session;		
		$session = get_session();
		$user_id = (int)($session->id ?? 0);
		$ticket = new \Tickets\Ticket;
		$response = new \Tickets\Ticket_response;
		$user_roles_map = new \Tickets\User_roles_map;

		$id = URL(3) ?? null;
		if($id){
			$row = $ticket->first(['id'=>$id]);
			$createdBy = (int)($row->created_by ?? 0);
			$assignedTo = (int)($row->assigned ?? 0);
			if(URL(2) == 'view' && !empty($row) && !tickets_user_can_view_all() && $createdBy !== (int)$user_id && $assignedTo !== (int)$user_id){
				$row = [];
			}
		}

		if(URL(2) == 'view'){
			require plugin_path('controllers/view-controller.php');
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
		
		$session = get_session();

		if(!empty($session->id)){
			$username = $session->first_name . ' '. $session->last_name;
			$user_id = $session->id;
		}

	$ses = new \Core\Session;
	$vars = get_value();

	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];
	
	$errors = $vars['errors'] ?? [];

	$ticket = new \Tickets\Ticket;
	$response = new \Tickets\Ticket_response;
	$user = new \Tickets\User;	

	if(URL(1) == $vars['plugin_route']){

		$id = URL(3) ?? null;

		if($id){
			
			$row = $ticket->first(['id'=>$id]);
		}
		
		$session = get_session();

		$onlineUsers = get_online_users();
		$onlineUserIds = tickets_online_user_ids();
		$onlineUserRows = tickets_online_users();

		$username = $session->first_name . ' '. $session->last_name;
		$user_id = $session->id;		
	
		$ticket::$query_id = 'get-user';
		$users = tickets_users_with_cashdrawer_today((int)$user_id);

		$subjectOptions = [];
		try{
			$links = do_filter('basic-admin_before_admin_links', []);
			if(!empty($links) && is_array($links)){
				foreach($links as $link){
					if(!is_object($link)) continue;
					$title = trim((string)($link->title ?? ''));
					if($title !== ''){
						$lt = strtolower($title);
						if(!in_array($lt, ['tickets','dashboard','home','logout'], true))
							$subjectOptions[] = $title;
					}
					$sublinks = $link->sublinks ?? [];
					if(!empty($sublinks) && is_array($sublinks)){
						foreach($sublinks as $sub){
							if(!is_object($sub)) continue;
							$st = trim((string)($sub->title ?? ''));
							if($st !== '') $subjectOptions[] = $st;
						}
					}
				}
			}
		}catch(\Throwable $e){}

		if(empty($subjectOptions)){
			try{
				$dbMenu = new \Core\Database;
				$menuRows = $dbMenu->query("SELECT title FROM menus WHERE disabled = 0 ORDER BY list_order ASC, title ASC") ?? [];
				if(!is_array($menuRows)) $menuRows = [];
				foreach($menuRows as $mr){
					if(!is_object($mr)) continue;
					$t = trim((string)($mr->title ?? ''));
					if($t === '') continue;
					$lt = strtolower($t);
					if(in_array($lt, ['tickets','dashboard','home','logout'], true)) continue;
					$subjectOptions[] = $t;
				}
			}catch(\Throwable $e){}
		}

		$subjectOptions = array_values(array_unique(array_filter($subjectOptions)));
		sort($subjectOptions);

		if(URL(2) == 'add'){
			$ticket_role = new \Tickets\User_role;
			require plugin_path('views/admin/add.php');
		}else
		if(URL(2) == 'edit'){			
			$ticket_role = new \Tickets\User_role;
			require plugin_path('views/admin/edit.php');
		}else
		if(URL(2) == 'delete'){
			require plugin_path('views/admin/delete.php');
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
			    FROM tickets AS m 
			    LEFT JOIN users AS u ON m.created_by = u.id 
			    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			    LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id 
			    LEFT JOIN ticket_responses AS responses ON m.id = responses.ticket_id 
			    WHERE m.id = :id 
			    LIMIT 1";

				$params = ['id'=>$id];
				if(!tickets_user_can_view_all()){
					$query = str_replace('WHERE m.id = :id', 'WHERE m.id = :id AND (m.created_by = :user_id OR m.assigned = :user_id)', $query);
					$params['user_id'] = $user_id;
				}

				$res = $ticket->query($query, $params);
				$row = [];
				if (!empty($res)) {
					$row = $res[0];
					//dd($row);
				}


			$query = "
			    SELECT 
			        m.*, 
			        u.first_name AS user_first_name, 
			        u.last_name AS user_last_name, 
			        u.image AS user_image
			    FROM ticket_responses AS m 
			    LEFT JOIN users AS u ON m.created_by = u.id 
			    WHERE m.ticket_id = :id ";

				$responses = $response->query($query, ['id'=>$id]);

			require plugin_path('views/admin/view.php');
		}else
		{

			$ticket = new \Tickets\Ticket;
			$user_roles_map = new \Tickets\User_roles_map;
			$perPage = (int)($_GET['per_page'] ?? 10);
			$allowedPerPage = [10, 25, 50, 100];
			if (!in_array($perPage, $allowedPerPage, true)) {
				$perPage = 10;
			}
			$limit = $perPage;
			$pager = new \Core\Pager($limit);
			$offset = $pager->offset;

			$ticket->limit = $limit;
			$ticket->offset = $offset;
			$ticket::$query_id = 'get-user';

			$isAjax = (!empty($_GET['ajax']) && $_GET['ajax'] == '1') || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
			$find = !empty($_GET['find']) ? trim((string)$_GET['find']) : '';
			$statusFilter = !empty($_GET['status']) ? strtolower(trim((string)$_GET['status'])) : '';
			$dateFrom = !empty($_GET['date_from']) ? trim((string)$_GET['date_from']) : '';
			$dateTo = !empty($_GET['date_to']) ? trim((string)$_GET['date_to']) : '';

			$params = [];
			$permissionWhere = '';
			if (!tickets_user_can_view_all()) {
				$permissionWhere = ' WHERE (m.created_by = :user_id OR m.assigned = :user_id) ';
				$params['user_id'] = $user_id;
			}

			$extraWhere = '';
			$extraPrefix = empty($permissionWhere) ? ' WHERE ' : ' AND ';
			if (!empty($find)) {
				$extraWhere .= $extraPrefix . ' (
					m.subject LIKE :find
					OR m.description LIKE :find
					OR m.date_created LIKE :find
					OR m.id LIKE :find
					OR m.status LIKE :find
					OR u.first_name LIKE :find
					OR u.last_name LIKE :find
					OR u_assigned.first_name LIKE :find
					OR u_assigned.last_name LIKE :find
				) ';
				$extraPrefix = ' AND ';
				$params['find'] = '%' . $find . '%';
			}
			if (!empty($statusFilter) && $statusFilter !== 'all') {
				$extraWhere .= $extraPrefix . ' LOWER(COALESCE(m.status,\'\')) = :status_filter ';
				$extraPrefix = ' AND ';
				$params['status_filter'] = $statusFilter;
			}
			if (!empty($dateFrom)) {
				$extraWhere .= $extraPrefix . ' DATE(m.date_created) >= :date_from ';
				$extraPrefix = ' AND ';
				$params['date_from'] = $dateFrom;
			}
			if (!empty($dateTo)) {
				$extraWhere .= $extraPrefix . ' DATE(m.date_created) <= :date_to ';
				$params['date_to'] = $dateTo;
			}

			$metrics = [
				'total_count' => 0,
				'unseen_total' => 0,
				'status_counts' => [],
			];

			try {
				$ticket::$query_id = 'tickets-metrics';
				$metricsQuery = "
					SELECT LOWER(COALESCE(m.status,'')) AS status_key, COUNT(*) AS cnt
					FROM tickets AS m
					LEFT JOIN users AS u ON m.created_by = u.id
					LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id
					$permissionWhere
					$extraWhere
					GROUP BY status_key
				";
				$metricsRows = $ticket->query($metricsQuery, $params) ?: [];
				$ticket::$query_id = 'get-user';
				foreach ($metricsRows as $mrow) {
					$key = (string)($mrow->status_key ?? '');
					$cnt = (int)($mrow->cnt ?? 0);
					$metrics['status_counts'][$key] = $cnt;
					$metrics['total_count'] += $cnt;
				}
			} catch (\Throwable $e) {
				$ticket::$query_id = 'get-user';
			}

			try {
				$ticket::$query_id = 'tickets-metrics';
				$unseenQuery = "
					SELECT SUM(t.unseen_responses) AS unseen_total
					FROM (
						SELECT
							m.id,
							SUM(CASE WHEN responses.seen = 0 THEN 1 ELSE 0 END) AS unseen_responses
						FROM tickets AS m
						LEFT JOIN ticket_responses AS responses ON m.id = responses.ticket_id
						LEFT JOIN users AS u ON m.created_by = u.id
						LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id
						$permissionWhere
						$extraWhere
						GROUP BY m.id
					) AS t
				";
				$unseenRows = $ticket->query($unseenQuery, $params) ?: [];
				$ticket::$query_id = 'get-user';
				if (!empty($unseenRows)) {
					$metrics['unseen_total'] = (int)($unseenRows[0]->unseen_total ?? 0);
				}
			} catch (\Throwable $e) {
				$ticket::$query_id = 'get-user';
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
					u_assigned.image AS assigned_image,
					COUNT(responses.id) AS total_responses,
					SUM(CASE WHEN responses.seen = 0 THEN 1 ELSE 0 END) AS unseen_responses
				FROM tickets AS m
				LEFT JOIN users AS u ON m.created_by = u.id
				LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id
				LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id
				LEFT JOIN ticket_responses AS responses ON m.id = responses.ticket_id
				$permissionWhere
				$extraWhere
				GROUP BY m.id
				ORDER BY m.id DESC
				LIMIT $limit OFFSET $offset
			";

			$rows = $ticket->query($query, $params);

			//dd($rows);
			require plugin_path('views/admin/list.php');
		}

	}
});

/** for manipulating data after a query operation **/
add_filter('after_query',function($data){

	
	if(empty($data['result']))
		return $data;

	if($data['query_id'] == 'get-user')
	{
		$role_map = new \Tickets\User_roles_map;
		foreach ($data['result'] as $key => $row) {
			if (!is_object($row) || empty($row->created_by)) {
				continue;
			}
			
			$query = "select * from user_roles where disabled = 0 && id in (select role_id from user_roles_map where disabled = 0 && user_id = :user_id)";
			$roles = $role_map->query($query,['user_id'=>$row->created_by]);
			if($roles)
				$data['result'][$key]->roles = array_column($roles, 'role');
			
			/** get ticket's roles **/
			$user_roles_map = new \Tickets\User_roles_map;
				
			$role_ids = $user_roles_map->where(['user_id'=>$row->created_by,'disabled'=>0]);
			if($role_ids)
				$data['result'][$key]->role_ids = array_column($role_ids, 'role_id');

		}
	}

	return $data;
});

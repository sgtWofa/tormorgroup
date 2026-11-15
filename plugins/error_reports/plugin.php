<?php

/**
 * Plugin name: Error Reports
 * Description: Track and manage application error reports
 * 
 * 
 **/

set_value([

	'admin_route'	=> 'admin',
	'plugin_route'	=> 'error_reports',
	'tables'		=> [
		'error_reports_table' => 'error_reports',
	],

	'optional_tables' => [
	],
	

]);

if(!function_exists('error_reports_json_response')){
	function error_reports_json_response(array $payload, int $status = 200): void
	{
		$GLOBALS['error_reports_json_response_sent'] = true;
		while(ob_get_level() > 0){
			ob_end_clean();
		}
		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		exit;
	}
}

$errorReportsJsonRoute = (URL(0) === 'admin' && URL(1) === 'error_reports' && in_array((string)(URL(2) ?? ''), ['ajax_list', 'ajax_trend', 'ajax_action'], true))
	|| (URL(0) === 'error_reports' && URL(1) === 'log');
if($errorReportsJsonRoute){
	ini_set('display_errors', '0');
	ini_set('display_startup_errors', '0');
	ob_start();
	$GLOBALS['error_reports_json_response_sent'] = false;
	register_shutdown_function(static function(): void {
		if(!empty($GLOBALS['error_reports_json_response_sent'])){
			return;
		}
		$error = error_get_last();
		if(empty($error) || !in_array((int)($error['type'] ?? 0), [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)){
			return;
		}
		error_reports_json_response(['success' => false, 'error' => 'Error Reports request failed. Please try again.'], 500);
	});
	set_exception_handler(static function(\Throwable $exception): void {
		error_reports_json_response(['success' => false, 'error' => 'Error Reports request failed. Please try again.'], 500);
	});
}

/** check if all tables exist **/
$db = new \Core\Database;
$tables = get_value()['tables'];

if(!$db->table_exists($tables)){
	try{
		// Attempt to auto-create table to avoid hard failure on first install
		$db->query("CREATE TABLE IF NOT EXISTS error_reports (
			id INT unsigned NOT NULL AUTO_INCREMENT,
			title VARCHAR(190) NULL,
			message LONGTEXT NULL,
			source VARCHAR(100) NULL,
			url VARCHAR(255) NULL,
			severity VARCHAR(20) NULL,
			stack LONGTEXT NULL,
			context_json LONGTEXT NULL,
			status VARCHAR(20) NULL,
			user_id INT NULL,
			user_name VARCHAR(100) NULL,
			resolved_by INT NULL,
			resolved_at DATETIME NULL,
			deleted TINYINT(1) unsigned NOT NULL DEFAULT 0,
			date_created DATETIME NULL,
			date_updated DATETIME NULL,
			date_deleted DATETIME NULL,
			PRIMARY KEY (id),
			KEY severity (severity),
			KEY status (status),
			KEY user_id (user_id),
			KEY date_created (date_created)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
	}catch(\Throwable $e){
		dd("Missing database tables in ".plugin_id() ." plugin: ". implode(",", $db->missing_tables));
		die;
	}
}

/** set permissions for this plugin **/
add_filter('permissions',function($permissions){

	$permissions[] = 'view_error_reports';
	$permissions[] = 'manage_error_reports';

	return $permissions;
});

if(!function_exists('error_reports_now')){
	function error_reports_now(): string
	{
		try{
			$tz = new \DateTimeZone('Africa/Accra');
			return (new \DateTime('now', $tz))->format('Y-m-d H:i:s');
		}catch(\Throwable $e){
			return date('Y-m-d H:i:s');
		}
	}
}

if(empty($GLOBALS['error_reports_handlers_registered'])){
	$GLOBALS['error_reports_handlers_registered'] = true;

	$log_server_error = function(array $data){
		try{
			// prevent recursive logging loops
			if(!empty($GLOBALS['error_reports_logging'])){
				return;
			}
			$GLOBALS['error_reports_logging'] = true;

			$db = new \Core\Database;
			$title = trim((string)($data['title'] ?? 'Server Error'));
			$message = (string)($data['message'] ?? '');
			$severity = strtoupper(trim((string)($data['severity'] ?? 'ERROR')));
			$source = trim((string)($data['source'] ?? 'server'));
			$url = trim((string)($data['url'] ?? (($_SERVER['REQUEST_URI'] ?? '') ?: '')));
			$stack = (string)($data['stack'] ?? '');
			$context = $data['context'] ?? null;

			if($title === '' && $message === ''){
				return;
			}

			$session = null;
			$user_id = null;
			$user_name = '';
			try{
				$session = get_session();
				$user_id = !empty($session->id) ? (int)$session->id : null;
				if(!empty($session->id)){
					$user_name = trim(($session->first_name ?? '') . ' ' . ($session->last_name ?? ''));
				}
			}catch(\Throwable $e){
			}

			$db->query("INSERT INTO error_reports (title,message,source,url,severity,stack,context_json,status,user_id,user_name,deleted,date_created) VALUES (:title,:message,:source,:url,:severity,:stack,:context_json,'OPEN',:user_id,:user_name,0,:date_created)", [
				'title' => $title,
				'message' => $message,
				'source' => $source,
				'url' => $url,
				'severity' => $severity,
				'stack' => $stack,
				'context_json' => is_null($context) ? null : json_encode($context),
				'user_id' => $user_id,
				'user_name' => $user_name,
				'date_created' => error_reports_now(),
			]);
		}catch(\Throwable $e){
			try{
				error_log('[error_reports] failed to log server error: ' . $e->getMessage());
			}catch(\Throwable $e2){
			}
		}finally{
			$GLOBALS['error_reports_logging'] = false;
		}
	};

	set_exception_handler(function($ex) use ($log_server_error){
		try{
			$log_server_error([
				'title' => 'Unhandled Exception',
				'message' => $ex->getMessage(),
				'severity' => 'FATAL',
				'source' => 'php_exception',
				'stack' => $ex->getTraceAsString(),
				'context' => ['file' => $ex->getFile(), 'line' => $ex->getLine()],
			]);
		}catch(\Throwable $e){
		}
		throw $ex;
	});

	set_error_handler(function($errno, $errstr, $errfile, $errline) use ($log_server_error){
		try{
			$sev = in_array($errno, [E_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR, E_CORE_ERROR, E_COMPILE_ERROR], true) ? 'FATAL' : 'ERROR';
			$log_server_error([
				'title' => 'PHP Error',
				'message' => $errstr,
				'severity' => $sev,
				'source' => 'php_error',
				'stack' => '',
				'context' => ['errno' => $errno, 'file' => $errfile, 'line' => $errline],
			]);
		}catch(\Throwable $e){
		}
		return false;
	});

	register_shutdown_function(function() use ($log_server_error){
		try{
			$err = error_get_last();
			if(empty($err)) return;
			$fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
			if(!in_array($err['type'] ?? 0, $fatalTypes, true)) return;

			$log_server_error([
				'title' => 'Fatal Error',
				'message' => (string)($err['message'] ?? ''),
				'severity' => 'FATAL',
				'source' => 'php_shutdown',
				'stack' => '',
				'context' => ['file' => $err['file'] ?? '', 'line' => $err['line'] ?? 0, 'type' => $err['type'] ?? 0],
			]);
		}catch(\Throwable $e){
		}
	});
}

add_action('before_view', function(){
	try{
		$accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
		$isAjax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
		$uri = (string)($_SERVER['REQUEST_URI'] ?? '');
		// Do not inject scripts into JSON/AJAX endpoints.
		if ($isAjax || strpos($accept, 'application/json') !== false || preg_match('/\/(request_action|payment_action|commissions_action|requests_data|payments_data|commissions_data)(\b|\/|\?|$)/i', $uri)) {
			return;
		}
		$vars = get_value();
		$plugin_route = $vars['plugin_route'] ?? 'error_reports';
		$endpoint = ROOT . '/' . $plugin_route . '/log';
		echo "<script>(function(){\n";
		echo "var ER_ENDPOINT=" . json_encode($endpoint) . ";\n";
		echo "var ER_RATE = 5;\n";
		echo "var ER_IGNORE = ['ResizeObserver loop limit exceeded','ResizeObserver loop completed with undelivered notifications'];\n";
		echo "var ER_SKIP_VALIDATION = /(^|\/)error_reports(\/|$)/.test(location.pathname) || /(^|\/)admin(\/|$)/.test(location.pathname) && /\/error_reports(\/|$)/.test(location.pathname);\n";
		echo "function erCanSend(){try{var k='er_rl';var now=Date.now();var m=Math.floor(now/60000);var o=JSON.parse(localStorage.getItem(k)||'{}');var c=o[m]||0;if(c>=ER_RATE)return false;o[m]=c+1;localStorage.setItem(k,JSON.stringify(o));return true;}catch(e){return true;}}\n";
		echo "function erPost(payload){try{if(!erCanSend())return;payload=payload||{};payload.url=payload.url||location.pathname+location.search;payload.source=payload.source||'frontend';payload.severity=payload.severity||'ERROR';fetch(ER_ENDPOINT,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)}).catch(function(){});}catch(e){}}\n";
		echo "function erHash(s){try{s=String(s||'');var h=0;for(var i=0;i<s.length;i++){h=((h<<5)-h)+s.charCodeAt(i);h|=0;}return String(h);}catch(e){return '0';}}\n";
		echo "function erText(el){try{return (el && (el.innerText||el.textContent)||'').trim();}catch(e){return '';}}\n";
		echo "function erClosestForm(el){try{return el && el.closest ? el.closest('form') : null;}catch(e){return null;}}\n";
		echo "function erIsVisible(el){try{if(!el) return false;var r=el.getBoundingClientRect();return (r.width>0 && r.height>0);}catch(e){return true;}}\n";
		echo "function erCollectValidation(){\n";
		echo "  try{\n";
		echo "    if(ER_SKIP_VALIDATION) return null;\n";
		echo "    var parts=[];\n";
		echo "    var nodes=document.querySelectorAll('.alert.alert-danger,.alert-danger,.invalid-feedback,.field-validation-error,.help-block.text-danger,.form-text.text-danger,.text-danger');\n";
		echo "    for(var i=0;i<nodes.length;i++){\n";
		echo "      var n=nodes[i];\n";
		echo "      if(!n) continue;\n";
		echo "      if(!erIsVisible(n)) continue;\n";
		echo "      if(n.classList && n.classList.contains('text-danger') && !n.classList.contains('invalid-feedback')){\n";
		echo "        if(!erClosestForm(n) && !(n.closest && (n.closest('.invalid-feedback')||n.closest('.is-invalid')||n.closest('.was-validated')))) continue;\n";
		echo "      }\n";
		echo "      var t=erText(n);\n";
		echo "      if(t && t.length>1) parts.push(t.replace(/\s+/g,' ').slice(0,500));\n";
		echo "    }\n";
		echo "    var invalid=document.querySelectorAll('.is-invalid');\n";
		echo "    if(invalid && invalid.length){\n";
		echo "      var names=[];\n";
		echo "      for(var j=0;j<invalid.length && j<20;j++){\n";
		echo "        var n=invalid[j].getAttribute('name')||invalid[j].getAttribute('id')||invalid[j].className||'field';\n";
		echo "        names.push(String(n).slice(0,60));\n";
		echo "      }\n";
		echo "      parts.push('Invalid fields: ' + names.join(', '));\n";
		echo "    }\n";
		echo "    var msg=parts.join(' | ');\n";
		echo "    msg=msg.trim();\n";
		echo "    if(!msg) return null;\n";
		echo "    return msg;\n";
		echo "  }catch(e){return null;}\n";
		echo "}\n";
		echo "function erLogValidationOnce(message, context){\n";
		echo "  try{\n";
		echo "    if(!message) return;\n";
		echo "    var key='er_val_'+location.pathname+'_'+erHash(message);\n";
		echo "    try{ if(sessionStorage.getItem(key)==='1') return; sessionStorage.setItem(key,'1'); }catch(e){}\n";
		echo "    erPost({title:'Validation Error',message:String(message).slice(0,2000),severity:'WARN',source:'validation',context:context||{}});\n";
		echo "  }catch(e){}\n";
		echo "}\n";
		echo "window.addEventListener('error',function(ev){try{\n";
		echo "  if(!ev) return;\n";
		echo "  if(ev.target && (ev.target.src || ev.target.href)) return;\n";
		echo "  var msg = String(ev.message || '');\n";
		echo "  if(!msg || msg.trim()==='') return;\n";
		echo "  if(msg === 'Script error.' || msg === 'Script error') return;\n";
		echo "  for(var i=0;i<ER_IGNORE.length;i++){ if(msg.indexOf(ER_IGNORE[i]) !== -1) return; }\n";
		echo "  var st='';\n";
		echo "  try{ st = String(ev.error && ev.error.stack || ''); }catch(e){}\n";
		echo "  if((!st || st.trim()==='') && (!ev.filename || !ev.lineno)) return;\n";
		echo "  erPost({title:'JS Error',message:msg,stack:st,context:{file:ev.filename||'',line:ev.lineno||0,col:ev.colno||0}});\n";
		echo "}catch(e){}},true);\n";
		echo "window.addEventListener('unhandledrejection',function(ev){try{\n";
		echo "  var r = ev && ev.reason;\n";
		echo "  var msg=''; var st='';\n";
		echo "  if(r && typeof r === 'object'){\n";
		echo "    msg = String(r.message || 'Unhandled rejection');\n";
		echo "    try{ st = String(r.stack || ''); }catch(e){}\n";
		echo "  }else{\n";
		echo "    msg = String(r || 'Unhandled rejection');\n";
		echo "  }\n";
		echo "  if(!msg || msg.trim()==='') return;\n";
		echo "  for(var i=0;i<ER_IGNORE.length;i++){ if(msg.indexOf(ER_IGNORE[i]) !== -1) return; }\n";
		echo "  if(msg === 'Unhandled rejection' && (!st || st.trim()==='')) return;\n";
		echo "  erPost({title:'Unhandled Promise Rejection',message:msg,stack:st,severity:'ERROR'});\n";
		echo "}catch(e){}},true);\n";
		echo "document.addEventListener('DOMContentLoaded',function(){\n";
		echo "  try{\n";
		echo "    if(ER_SKIP_VALIDATION) return;\n";
		echo "    var v=erCollectValidation();\n";
		echo "    if(v){ erLogValidationOnce(v,{type:'server_rendered'}); }\n";
		echo "    document.addEventListener('submit',function(){\n";
		echo "      try{\n";
		echo "        if(ER_SKIP_VALIDATION) return;\n";
		echo "        setTimeout(function(){\n";
		echo "          try{ var vv=erCollectValidation(); if(vv){ erLogValidationOnce(vv,{type:'after_submit'}); } }catch(e){}\n";
		echo "        }, 300);\n";
		echo "      }catch(e){}\n";
		echo "    }, true);\n";
		echo "    try{\n";
		echo "      var obs = new MutationObserver(function(){\n";
		echo "        try{ if(ER_SKIP_VALIDATION) return; var m=erCollectValidation(); if(m){ erLogValidationOnce(m,{type:'mutation'}); } }catch(e){}\n";
		echo "      });\n";
		echo "      obs.observe(document.body,{subtree:true,childList:true,attributes:true});\n";
		echo "      setTimeout(function(){try{obs.disconnect();}catch(e){}}, 5000);\n";
		echo "    }catch(e){}\n";
		echo "  }catch(e){}\n";
		echo "});\n";
		echo "document.addEventListener('invalid',function(ev){\n";
		echo "  try{\n";
		echo "    if(ER_SKIP_VALIDATION) return;\n";
		echo "    var t=ev && ev.target;\n";
		echo "    if(!t) return;\n";
		echo "    var name=t.getAttribute('name')||t.getAttribute('id')||'field';\n";
		echo "    var msg='HTML5 validation failed for: '+name;\n";
		echo "    erLogValidationOnce(msg,{type:'html5',field:name});\n";
		echo "  }catch(e){}\n";
		echo "},true);\n";
		echo "})();</script>";
	}catch(\Throwable $e){
	}
});

add_filter('user_permissions', function($permissions){
	try{
		$session = get_session();
		$role = strtolower((string)($session->roles[0] ?? ''));
		$isPrivileged = in_array($role, ['manager','admin','is_manager','is_admin'], true);
		if(!$isPrivileged){
			$permissions[] = 'view_error_reports';
		}
	}catch(\Throwable $e){
		// ignore
	}
	return $permissions;
});

/** run this after a form submit **/
add_action('controller',function(){
	$req = new \Core\Request;
	$vars = get_value();
	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];

	$base = 0;
	$is_admin_context = false;
	if(URL(0) === $admin_route){
		$base = 1;
		$is_admin_context = true;
	}

	if(URL($base) !== $plugin_route){
		return;
	}

	$action = URL($base + 1);

	if($action === 'ajax_list'){
		try{
			if(!$is_admin_context){
				error_reports_json_response(['success' => false, 'error' => 'Not allowed'], 403);
			}
			if(!user_can('view_error_reports')){
				error_reports_json_response(['success' => false, 'error' => 'Access denied'], 403);
			}

			$db = new \Core\Database;
			$find = trim((string)($_GET['find'] ?? ''));
			$severity = strtoupper(trim((string)($_GET['severity'] ?? '')));
			$status = strtoupper(trim((string)($_GET['status'] ?? '')));
			$dateFrom = trim((string)($_GET['date_from'] ?? ''));
			$dateTo = trim((string)($_GET['date_to'] ?? ''));
			$limit = (int)($_GET['limit'] ?? 30);
			$limit = $limit > 0 ? min(200, $limit) : 30;
			$page = (int)($_GET['page'] ?? 1);
			$page = $page < 1 ? 1 : $page;
			$offset = ($page - 1) * $limit;

			$params = [];
			$conditions = ['deleted = 0'];

			if($find !== ''){
				$conditions[] = '(LOWER(title) LIKE :find OR LOWER(message) LIKE :find OR LOWER(source) LIKE :find OR LOWER(url) LIKE :find OR LOWER(user_name) LIKE :find)';
				$params['find'] = '%' . strtolower($find) . '%';
			}

			if($severity !== ''){
				$conditions[] = 'UPPER(COALESCE(severity,\'\')) = :severity';
				$params['severity'] = $severity;
			}

			if($status !== ''){
				$conditions[] = 'UPPER(COALESCE(status,\'\')) = :status';
				$params['status'] = $status;
			}

			if($dateFrom !== ''){
				$conditions[] = 'DATE(date_created) >= :date_from';
				$params['date_from'] = $dateFrom;
			}

			if($dateTo !== ''){
				$conditions[] = 'DATE(date_created) <= :date_to';
				$params['date_to'] = $dateTo;
			}

			$where = implode(' AND ', $conditions);
			$countRow = $db->query("SELECT COUNT(*) as cnt FROM error_reports WHERE {$where}", $params);
			$total = (int)($countRow[0]->cnt ?? 0);
			$totalPages = $limit > 0 ? (int)ceil($total / $limit) : 1;
			if($totalPages < 1) $totalPages = 1;
			if($page > $totalPages){
				$page = $totalPages;
				$offset = ($page - 1) * $limit;
			}

			$rows = $db->query(
				"SELECT * FROM error_reports WHERE {$where} ORDER BY date_created DESC, id DESC LIMIT {$limit} OFFSET {$offset}",
				$params
			);

			$kpi = [
				'open' => 0,
				'resolved' => 0,
				'today' => 0,
				'week' => 0,
				'window_label' => 'Last 7 days',
				'window_days' => 7,
				'fatal_open' => 0,
			];

			try{
				$weekExpr = "date_created >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
				$windowLabel = 'Last 7 days';
				$windowDays = 7;

				if($dateFrom !== '' && $dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)){
					try{
						$tz = new \DateTimeZone('Africa/Accra');
					}catch(\Throwable $e){
						$tz = new \DateTimeZone(date_default_timezone_get());
					}
					try{
						$start = new \DateTime($dateFrom . ' 00:00:00', $tz);
						$end = new \DateTime($dateTo . ' 00:00:00', $tz);
						if($start > $end){ $tmp = $start; $start = $end; $end = $tmp; }
						$windowDays = (int)$start->diff($end)->format('%a') + 1;
						$weekExpr = "(DATE(date_created) >= :kpi_from AND DATE(date_created) <= :kpi_to)";
						$params['kpi_from'] = $start->format('Y-m-d');
						$params['kpi_to'] = $end->format('Y-m-d');
						$windowLabel = 'Custom (' . $windowDays . ' day' . ($windowDays === 1 ? '' : 's') . ')';
					}catch(\Throwable $e){
					}
				}

				$kpiRow = $db->query(
					"SELECT
						SUM(CASE WHEN UPPER(COALESCE(status,'OPEN')) = 'OPEN' THEN 1 ELSE 0 END) AS open_cnt,
						SUM(CASE WHEN UPPER(COALESCE(status,'')) = 'RESOLVED' THEN 1 ELSE 0 END) AS resolved_cnt,
						SUM(CASE WHEN DATE(date_created) = CURDATE() THEN 1 ELSE 0 END) AS today_cnt,
						SUM(CASE WHEN {$weekExpr} THEN 1 ELSE 0 END) AS week_cnt,
						SUM(CASE WHEN UPPER(COALESCE(status,'OPEN')) = 'OPEN' AND UPPER(COALESCE(severity,'')) = 'FATAL' THEN 1 ELSE 0 END) AS fatal_open_cnt
					FROM error_reports
					WHERE {$where}",
					$params
				);
				if(!empty($kpiRow)){
					$kpi['open'] = (int)($kpiRow[0]->open_cnt ?? 0);
					$kpi['resolved'] = (int)($kpiRow[0]->resolved_cnt ?? 0);
					$kpi['today'] = (int)($kpiRow[0]->today_cnt ?? 0);
					$kpi['week'] = (int)($kpiRow[0]->week_cnt ?? 0);
					$kpi['fatal_open'] = (int)($kpiRow[0]->fatal_open_cnt ?? 0);
					$kpi['window_label'] = $windowLabel;
					$kpi['window_days'] = (int)$windowDays;
				}
			}catch(\Throwable $e){
			}

			$canManage = user_can('manage_error_reports') ? 1 : 0;
			error_reports_json_response([
				'success' => true,
				'data' => [
					'rows' => $rows ?? [],
					'total' => $total,
					'page' => $page,
					'limit' => $limit,
					'total_pages' => $totalPages,
					'kpi' => $kpi,
					'can_manage' => $canManage,
				],
			]);
		}catch(\Throwable $e){
			error_reports_json_response(['success' => false, 'error' => 'Failed to load list'], 500);
		}
	}

	if($action === 'ajax_trend'){
		try{
			if(!$is_admin_context){
				error_reports_json_response(['success' => false, 'error' => 'Not allowed'], 403);
			}
			if(!user_can('view_error_reports')){
				error_reports_json_response(['success' => false, 'error' => 'Access denied'], 403);
			}

			$db = new \Core\Database;
			$mode = strtolower(trim((string)($_GET['mode'] ?? 'days')));
			$days = (int)($_GET['days'] ?? 7);
			$days = in_array($days, [7,30], true) ? $days : 7;
			$day = trim((string)($_GET['day'] ?? ''));
			$find = trim((string)($_GET['find'] ?? ''));
			$severity = strtoupper(trim((string)($_GET['severity'] ?? '')));
			$status = strtoupper(trim((string)($_GET['status'] ?? '')));
			$dateFrom = trim((string)($_GET['date_from'] ?? ''));
			$dateTo = trim((string)($_GET['date_to'] ?? ''));
			$tz = null;
			try{
				$tz = new \DateTimeZone('Africa/Accra');
			}catch(\Throwable $e){
				$tz = new \DateTimeZone(date_default_timezone_get());
			}

			$params = [];
			$conditions = ['deleted = 0'];
			if($find !== ''){
				$conditions[] = '(LOWER(title) LIKE :find OR LOWER(message) LIKE :find OR LOWER(source) LIKE :find OR LOWER(url) LIKE :find OR LOWER(user_name) LIKE :find)';
				$params['find'] = '%' . strtolower($find) . '%';
			}
			if($severity !== ''){
				$conditions[] = 'UPPER(COALESCE(severity,\'\')) = :severity';
				$params['severity'] = $severity;
			}
			if($status !== ''){
				$conditions[] = 'UPPER(COALESCE(status,\'\')) = :status';
				$params['status'] = $status;
			}
			if($dateFrom !== ''){
				$conditions[] = 'DATE(date_created) >= :date_from';
				$params['date_from'] = $dateFrom;
			}
			if($dateTo !== ''){
				$conditions[] = 'DATE(date_created) <= :date_to';
				$params['date_to'] = $dateTo;
			}
			$labels = [];
			$data = [];
			$rows = [];

			if($mode === 'hourly'){
				$now = new \DateTime('now', $tz);
				$start = null;
				if($day !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)){
					try{
						$start = new \DateTime($day . ' 00:00:00', $tz);
					}catch(\Throwable $e){
						$start = null;
					}
				}
				if(!$start){
					$start = (clone $now)->setTime(0, 0, 0);
				}
				$end = (clone $start)->modify('+1 day');
				$conditions[] = 'date_created >= :start_dt';
				$conditions[] = 'date_created < :end_dt';
				$params['start_dt'] = $start->format('Y-m-d H:i:s');
				$params['end_dt'] = $end->format('Y-m-d H:i:s');
				$where = implode(' AND ', $conditions);

				$rows = $db->query(
					"SELECT HOUR(date_created) as h, COUNT(*) as c
					FROM error_reports
					WHERE {$where}
					GROUP BY HOUR(date_created)
					ORDER BY h ASC",
					$params
				);

				$map = [];
				if(!empty($rows)){
					foreach($rows as $r){
						$map[(int)$r->h] = (int)$r->c;
					}
				}

				for($h = 0; $h <= 23; $h++){
					$labels[] = str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':00';
					$data[] = (int)($map[$h] ?? 0);
				}
			}else{
				$now = new \DateTime('now', $tz);
				$start = null;
				$end = null;
				$maxDays = 366;

				if($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)){
					try{ $start = new \DateTime($dateFrom . ' 00:00:00', $tz); }catch(\Throwable $e){ $start = null; }
				}
				if($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)){
					try{ $end = new \DateTime($dateTo . ' 00:00:00', $tz); }catch(\Throwable $e){ $end = null; }
				}

				if($start && $end){
					if($start > $end){
						$tmp = $start; $start = $end; $end = $tmp;
					}
					$diffDays = (int)$start->diff($end)->format('%a');
					$rangeDays = $diffDays + 1;
					if($rangeDays > $maxDays){
						$rangeDays = $maxDays;
						$end = (clone $start)->modify('+' . ($rangeDays - 1) . ' day');
					}
					$days = $rangeDays;
					$conditions[] = 'date_created >= :start_dt';
					$conditions[] = 'date_created < :end_dt';
					$params['start_dt'] = $start->format('Y-m-d H:i:s');
					$params['end_dt'] = (clone $end)->modify('+1 day')->format('Y-m-d H:i:s');
				}else{
					// fallback: last N days ending today (existing behavior)
					$start = (clone $now)->modify('-' . ($days - 1) . ' day')->setTime(0, 0, 0);
					$conditions[] = 'date_created >= :start_dt';
					$params['start_dt'] = $start->format('Y-m-d H:i:s');
				}

				$where = implode(' AND ', $conditions);

				$rows = $db->query(
					"SELECT DATE(date_created) as d, COUNT(*) as c
					FROM error_reports
					WHERE {$where}
					GROUP BY DATE(date_created)
					ORDER BY d ASC",
					$params
				);

				$map = [];
				if(!empty($rows)){
					foreach($rows as $r){
						$map[(string)$r->d] = (int)$r->c;
					}
				}

				for($i = 0; $i < $days; $i++){
					$dt = (clone $start)->modify('+' . $i . ' day')->format('Y-m-d');
					$labels[] = $dt;
					$data[] = (int)($map[$dt] ?? 0);
				}
			}

			error_reports_json_response([
				'success' => true,
				'data' => [
					'mode' => $mode === 'hourly' ? 'hourly' : 'days',
					'days' => $days,
					'labels' => $labels,
					'counts' => $data,
				],
			]);
		}catch(\Throwable $e){
			error_reports_json_response(['success' => false, 'error' => 'Failed to load trend'], 500);
		}
	}

	if($action === 'ajax_action' && $req->posted()){
		try{
			if(!$is_admin_context){
				error_reports_json_response(['success' => false, 'error' => 'Not allowed'], 403);
			}
			if(!user_can('manage_error_reports')){
				error_reports_json_response(['success' => false, 'error' => 'Access denied'], 403);
			}

			if(!csrf_verify($_POST)){
				error_reports_json_response(['success' => false, 'error' => 'Form expired. Please refresh and try again'], 400);
			}

			$id = (int)($_POST['id'] ?? 0);
			$action = (string)($_POST['action'] ?? '');
			if($id < 1){
				error_reports_json_response(['success' => false, 'error' => 'Missing report id'], 400);
			}

			$report = new \ErrorReports\ErrorReport;
			$row = $report->first(['id' => $id]);
			if(empty($row)){
				error_reports_json_response(['success' => false, 'error' => 'Report not found'], 404);
			}

			$session = get_session();
			$resolver = (int)($session->id ?? 0);

			if($action === 'resolve'){
				$report->update($id, [
					'status' => 'RESOLVED',
					'resolved_by' => $resolver ?: null,
					'resolved_at' => error_reports_now(),
				]);
				error_reports_json_response(['success' => true]);
			}

			if($action === 'reopen'){
				$report->update($id, [
					'status' => 'OPEN',
					'resolved_by' => null,
					'resolved_at' => null,
				]);
				error_reports_json_response(['success' => true]);
			}

			if($action === 'delete'){
				$report->update($id, [
					'deleted' => 1,
					'date_deleted' => error_reports_now(),
				]);
				error_reports_json_response(['success' => true]);
			}

			error_reports_json_response(['success' => false, 'error' => 'Unknown action'], 400);
		}catch(\Throwable $e){
			error_reports_json_response(['success' => false, 'error' => 'Request failed'], 500);
		}
	}

	// Public endpoint for reporting errors (expects POST JSON)
	if($action === 'log' && $req->posted()){
		try{
			$raw = file_get_contents('php://input');
			$payload = json_decode($raw, true);
			if(json_last_error() !== JSON_ERROR_NONE){
				error_reports_json_response(['success' => false, 'error' => 'Invalid JSON'], 400);
			}

			$title = trim((string)($payload['title'] ?? ''));
			$message = trim((string)($payload['message'] ?? ''));
			$source = trim((string)($payload['source'] ?? ''));
			$url = trim((string)($payload['url'] ?? ''));
			$severity = strtoupper(trim((string)($payload['severity'] ?? 'ERROR')));
			$stack = (string)($payload['stack'] ?? '');
			$context = $payload['context'] ?? null;

			if($title === '' && $message === ''){
				error_reports_json_response(['success' => false, 'error' => 'Missing title/message'], 400);
			}

			$session = get_session();
			$user_id = (int)($session->id ?? 0);
			$user_name = '';
			if(!empty($session->id)){
				$user_name = trim(($session->first_name ?? '') . ' ' . ($session->last_name ?? ''));
			}

			$report = new \ErrorReports\ErrorReport;
			$report->insert([
				'title' => $title !== '' ? $title : 'Client Error',
				'message' => $message,
				'source' => $source,
				'url' => $url,
				'severity' => $severity,
				'stack' => $stack,
				'context_json' => is_null($context) ? null : json_encode($context),
				'status' => 'OPEN',
				'user_id' => $user_id ?: null,
				'user_name' => $user_name,
				'deleted' => 0,
				'date_created' => error_reports_now(),
			]);

			error_reports_json_response(['success' => true]);
		}catch(\Throwable $e){
			error_reports_json_response(['success' => false, 'error' => 'Failed to log error'], 500);
		}
	}

	if(!$req->posted()){
		return;
	}

	if(!user_can('manage_error_reports')){
		message_fail('Access denied');
		redirect("{$admin_route}/{$plugin_route}");
	}

	if(!csrf_verify($_POST)){
		message_fail('Form expired. Please refresh and try again');
		redirect("{$admin_route}/{$plugin_route}");
	}

	$id = (int)($_POST['id'] ?? 0);
	$action = (string)($_POST['action'] ?? '');
	if($id < 1){
		message_fail('Missing report id');
		redirect("{$admin_route}/{$plugin_route}");
	}

	$report = new \ErrorReports\ErrorReport;
	$row = $report->first(['id' => $id]);
	if(empty($row)){
		message_fail('Report not found');
		redirect("{$admin_route}/{$plugin_route}");
	}

	$session = get_session();
	$resolver = (int)($session->id ?? 0);

	if($action === 'resolve'){
		$report->update($id, [
			'status' => 'RESOLVED',
			'resolved_by' => $resolver ?: null,
			'resolved_at' => error_reports_now(),
		]);
		message_success('Report resolved');
		redirect("{$admin_route}/{$plugin_route}/view/{$id}");
	}

	if($action === 'reopen'){
		$report->update($id, [
			'status' => 'OPEN',
			'resolved_by' => null,
			'resolved_at' => null,
		]);
		message_success('Report reopened');
		redirect("{$admin_route}/{$plugin_route}/view/{$id}");
	}

	if($action === 'delete'){
		$report->update($id, [
			'deleted' => 1,
			'date_deleted' => error_reports_now(),
		]);
		message_success('Report deleted');
		redirect("{$admin_route}/{$plugin_route}");
	}

	message_fail('Unknown action');
	redirect("{$admin_route}/{$plugin_route}");
});

/** displays the view file **/
add_action('basic-admin_main_content',function(){

	$vars = get_value();
	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];

	if(URL(1) !== $plugin_route){
		return;
	}

	if(!user_can('view_error_reports')){
		echo '<div class="alert alert-danger text-center">Access denied.</div>';
		return;
	}

	$report = new \ErrorReports\ErrorReport;

	$id = URL(3) ?? null;
	if(URL(2) === 'view' && $id){
		$row = $report->first(['id' => $id]);
		require plugin_path('views/admin/view.php');
		return;
	}

	require plugin_path('views/admin/list.php');
});


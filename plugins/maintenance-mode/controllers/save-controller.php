<?php

$vars = get_value();
$admin_route = $vars['admin_route'];
$plugin_route = $vars['plugin_route'];

if(!user_can('is_admin') && !user_can('manage_maintenance_mode')){
	message_fail('Access denied');
	redirect($admin_route);
	exit;
}

$req = new \Core\Request;

$enabled = (int)($req->input('enabled', '0'));
$durationMinutes = (int)($req->input('duration_minutes', '120'));
$durationMinutes = $durationMinutes > 0 ? $durationMinutes : 120;

$current = maintenance_mode_get_settings();
$startedAt = (string)($current['started_at'] ?? '');
$endsAt = (string)($current['ends_at'] ?? '');

if($enabled){
	if($startedAt === ''){
		$startedAt = date('Y-m-d H:i:s');
	}
	$startTs = strtotime($startedAt);
	if($startTs === false){
		$startedAt = date('Y-m-d H:i:s');
		$startTs = strtotime($startedAt);
	}
	$endsAt = date('Y-m-d H:i:s', ((int)$startTs) + ($durationMinutes * 60));
}else{
	$startedAt = '';
	$endsAt = '';
}

$allowed = $req->post('allowed_users');
if(!is_array($allowed)) $allowed = [];

$allowed = array_values(array_unique(array_map(function($v){ return (int)$v; }, $allowed)));
$allowed = array_values(array_filter($allowed, function($v){ return $v > 0; }));

$ok = maintenance_mode_save_settings($enabled, $allowed, $durationMinutes, $startedAt, $endsAt);
if(!$ok){
	message_fail('Failed to save maintenance mode settings.');
	redirect($admin_route.'/'.$plugin_route);
	exit;
}

message_success('Maintenance mode settings saved successfully.');
redirect($admin_route.'/'.$plugin_route);
exit;

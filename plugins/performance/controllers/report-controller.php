<?php

defined('ROOT') or die("Direct script access denied");

@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
@error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_STRICT & ~E_DEPRECATED);

if (!ob_get_level()) {
	ob_start();
}

$session = get_session();

if (!(user_can('is_admin') || user_can('is_manager'))) {
	http_response_code(403);
	echo 'Access denied';
	exit;
}

$req = new \Core\Request;

$requireAutoload = function (): void {
	$root = dirname(__DIR__, 3);
	$candidates = [
		$root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',
		$root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',
	];
	foreach ($candidates as $p) {
		if (is_file($p)) {
			require_once $p;
			return;
		}
	}
	throw new \Exception('Composer autoload.php not found. Checked: ' . implode(' , ', $candidates));
};

$format = strtolower(trim((string)($req->get('format') ?? 'pdf')));
$period = strtolower(trim((string)($req->get('period') ?? 'daily')));
$userId = trim((string)($req->get('user') ?? ''));

$isAllUsersReport = $userId === '';

$dailyDate = trim((string)($req->get('date') ?? ''));
$month = trim((string)($req->get('month') ?? ''));
$startDate = trim((string)($req->get('start_date') ?? ''));
$endDate = trim((string)($req->get('end_date') ?? ''));

$dt = function (string $s): ?\DateTime {
	try {
		$s = trim($s);
		if ($s === '') {
			return null;
		}
		return new \DateTime($s);
	} catch (\Throwable $e) {
		return null;
	}
};

$start = null;
$end = null;

if ($period === 'daily') {
	$d = $dt($dailyDate);
	if (!$d) {
		$d = new \DateTime('today');
	}
	$start = (clone $d)->setTime(0, 0, 0);
	$end = (clone $d)->setTime(23, 59, 59);
} elseif ($period === 'monthly') {
	$m = $dt($month . '-01');
	if (!$m) {
		$m = new \DateTime('first day of this month');
	}
	$start = (clone $m)->modify('first day of this month')->setTime(0, 0, 0);
	$end = (clone $m)->modify('last day of this month')->setTime(23, 59, 59);
} else {
	$s = $dt($startDate);
	$e = $dt($endDate);
	if (!$s || !$e) {
		$today = new \DateTime('today');
		$s = (clone $today)->setTime(0, 0, 0);
		$e = (clone $today)->setTime(23, 59, 59);
	}
	$start = (clone $s)->setTime(0, 0, 0);
	$end = (clone $e)->setTime(23, 59, 59);
}

$startYmd = $start->format('Y-m-d');
$endYmd = $end->format('Y-m-d');

$db = new \Core\Database;

$normalizeRows = function ($rows): array {
	if (!is_array($rows)) {
		return [];
	}
	$out = [];
	foreach ($rows as $r) {
		if (is_array($r)) {
			$out[] = $r;
			continue;
		}
		if (is_object($r)) {
			$out[] = (array)$r;
			continue;
		}
	}
	return $out;
};

$getSectionCount = function (string $section, array $sectionData): float {
	if ($section === 'attendance') {
		$rows = $sectionData['Days_Worked'] ?? [];
		if (!is_array($rows)) {
			return 0.0;
		}
		$sum = 0.0;
		foreach ($rows as $rr) {
			if (!is_array($rr) && !is_object($rr)) {
				continue;
			}
			$r = (array)$rr;
			$sum += (float)($r['present'] ?? 0) + (float)($r['late'] ?? 0) + (float)($r['absent'] ?? 0);
		}
		return $sum;
	}

	$ov = [];
	if (isset($sectionData['Overview']) && is_array($sectionData['Overview'])) {
		$ov = $sectionData['Overview'];
	} elseif (isset($sectionData['Summary']) && is_array($sectionData['Summary'])) {
		$ov = $sectionData['Summary'];
	}
	if (empty($ov)) {
		return -1.0;
	}
	return (float)($ov['total_transactions'] ?? $ov['customers_served'] ?? $ov['total_customers'] ?? $ov['total_quantity'] ?? $ov['total_count'] ?? $ov['count'] ?? 0);
};

$buildWhereFor = function (string $table, string $alias = '') use ($db, $startYmd, $endYmd, $normalizeRows): array {
	$prefix = $alias !== '' ? ($alias . '.') : '';
	$clauses = ['1=1'];
	$params = [];

	$cols = [];
	try {
		$schema = $db->query("SHOW COLUMNS FROM {$table}") ?: [];
		foreach ($schema as $c) {
			$name = '';
			if (is_object($c) && isset($c->Field)) {
				$name = (string)$c->Field;
			} elseif (is_array($c) && isset($c['Field'])) {
				$name = (string)$c['Field'];
			}
			if ($name !== '') {
				$cols[] = strtolower(trim($name));
			}
		}
	} catch (\Throwable $e) {
		$cols = [];
	}

	$has = function (string $col) use ($cols): bool {
		return in_array(strtolower($col), $cols, true);
	};

	$dateColCandidates = ['date_created', 'created_at', 'date', 'date_added', 'created_on'];
	$userColCandidates = ['created_by', 'user_id', 'staff_id', 'admin_id'];

	$dateCol = '';
	foreach ($dateColCandidates as $c) {
		if ($has($c)) {
			$dateCol = $c;
			break;
		}
	}
	$userCol = '';
	foreach ($userColCandidates as $c) {
		if ($has($c)) {
			$userCol = $c;
			break;
		}
	}

	if ($startYmd && $endYmd && $dateCol !== '') {
		$clauses[] = $prefix . $dateCol . ' BETWEEN :start AND :end';
		$params[':start'] = $startYmd . ' 00:00:00';
		$params[':end'] = $endYmd . ' 23:59:59';
	}

	return [
		'where' => 'WHERE ' . implode(' AND ', $clauses),
		'params' => $params,
		'user_col' => $userCol,
		'date_col' => $dateCol,
		'cols' => $cols,
	];
};

$getClearing = function (string $table, string $uid) use ($db, $buildWhereFor, $normalizeRows): array {
	if (!$db->table_exists([$table])) {
		return [];
	}
	$w = $buildWhereFor($table, 't');
	if (($w['user_col'] ?? '') === '') {
		return [];
	}

	$clauses = [trim((string)($w['where'] ?? 'WHERE 1=1'))];
	$params = is_array($w['params'] ?? []) ? $w['params'] : [];
	$clauses[] = 't.' . $w['user_col'] . ' = :uid';
	$params[':uid'] = $uid;
	$where = implode(' AND ', array_map(function ($c) {
		return preg_replace('/^WHERE\s+/i', '', trim((string)$c));
	}, $clauses));
	$where = 'WHERE ' . $where;

	$byNetwork = [];
	$byType = [];
	$byNetworkType = [];
	$byUserTop = [];

	try {
		$byNetwork = $normalizeRows($db->run("SELECT t.network, COUNT(*) AS total_count, SUM(CAST(t.amount AS DECIMAL(10,2))) AS total_amount FROM {$table} t {$where} GROUP BY t.network ORDER BY total_amount DESC", $params));
	} catch (\Throwable $e) {
		$byNetwork = [];
	}
	try {
		$byType = $normalizeRows($db->run("SELECT t.transaction_type, COUNT(*) AS total_count, SUM(CAST(t.amount AS DECIMAL(10,2))) AS total_amount FROM {$table} t {$where} GROUP BY t.transaction_type ORDER BY total_amount DESC", $params));
	} catch (\Throwable $e) {
		$byType = [];
	}
	try {
		$byNetworkType = $normalizeRows($db->run("SELECT t.network, t.transaction_type, COUNT(*) AS total_count, SUM(CAST(t.amount AS DECIMAL(10,2))) AS total_amount FROM {$table} t {$where} GROUP BY t.network, t.transaction_type ORDER BY total_amount DESC", $params));
	} catch (\Throwable $e) {
		$byNetworkType = [];
	}

	return [
		'By_Network' => $byNetwork,
		'By_Type' => $byType,
		'By_Network_Type' => $byNetworkType,
	];
};

$users = [];
if ($userId !== '') {
	$u = $db->query("SELECT id, first_name, last_name FROM users WHERE id = :id LIMIT 1", ['id' => $userId]);
	if (!empty($u[0])) {
		$users = [$u[0]];
	}
} else {
	try {
		$hasDisabled = false;
		$schema = $db->query("SHOW COLUMNS FROM users") ?: [];
		foreach ($schema as $c) {
			$name = '';
			if (is_object($c) && isset($c->Field)) {
				$name = (string)$c->Field;
			} elseif (is_array($c) && isset($c['Field'])) {
				$name = (string)$c['Field'];
			}
			if (strtolower(trim($name)) === 'disabled') {
				$hasDisabled = true;
				break;
			}
		}

		if ($hasDisabled) {
			$users = $db->query("SELECT id, first_name, last_name FROM users WHERE deleted = 0 AND disabled = 0 ORDER BY first_name ASC, last_name ASC") ?: [];
		} else {
			$users = $db->query("SELECT id, first_name, last_name FROM users WHERE deleted = 0 ORDER BY first_name ASC, last_name ASC") ?: [];
		}
	} catch (\Throwable $e) {
		$users = $db->query("SELECT id, first_name, last_name FROM users WHERE deleted = 0 ORDER BY first_name ASC, last_name ASC") ?: [];
	}
}

$activityTypes = [
	'agencybankings','airtimebundles','attendance','cfas','commissions',
	'deposits','ezwichs','momoremits','payments',
	'payment_services','requests','reversals','sendings','sims',
	'tickets','withdrawals',
	'cashpickups','reconciliations','error_reports'
];

$fetchPerf = function (string $uid) use ($activityTypes, $startYmd, $endYmd): array {
	$payload = [
		'user' => $uid,
		'transaction_type' => '',
		'category' => '',
		'start_date' => $startYmd,
		'end_date' => $endYmd,
		'activity' => $activityTypes,
	];

	$oldPost = $_POST;
	$_POST = $payload;
	$oldEmbedded = defined('PERF_EMBEDDED_MODE') ? PERF_EMBEDDED_MODE : null;
	if (!defined('PERF_EMBEDDED_MODE')) {
		define('PERF_EMBEDDED_MODE', true);
	}

	$perf = require plugin_path('controllers/performance-controller.php');

	$_POST = $oldPost;
	if ($oldEmbedded !== null) {
		// cannot undefine constants; keep original behavior by preserving existing defined value in the process
	}

	return is_array($perf) ? $perf : [];
};

$perfAllUsers = $fetchPerf('');

$toFloat = function ($v): float {
	if ($v === null) {
		return 0.0;
	}
	if (is_bool($v)) {
		return $v ? 1.0 : 0.0;
	}
	if (is_int($v) || is_float($v)) {
		return (float)$v;
	}
	if (is_string($v)) {
		$vv = trim($v);
		if ($vv === '') {
			return 0.0;
		}
		if (is_numeric($vv)) {
			return (float)$vv;
		}
		$vv = preg_replace('/[^0-9.\-]/', '', $vv);
		return $vv !== '' && is_numeric($vv) ? (float)$vv : 0.0;
	}
	return 0.0;
};

$buildServices = function (array $perf) use ($toFloat): array {
	$services = [];
	$addService = function (string $serviceKey, string $label, array $overview, array $byProvider = []) use (&$services, $toFloat): void {
		$count = $toFloat($overview['total_transactions'] ?? $overview['customers_served'] ?? $overview['total_customers'] ?? $overview['total_quantity'] ?? 0);
		$volume = $toFloat($overview['total_amount'] ?? $overview['total_ghs'] ?? $overview['total_volume'] ?? 0);
		$commission = $toFloat($overview['total_commission'] ?? $overview['commission'] ?? 0);
		$commission += $toFloat($overview['total_charge'] ?? $overview['charge'] ?? $overview['charges'] ?? 0);
		$customers = $count;

		$providersUsed = 0;
		$providersTotal = 0;
		if (!empty($byProvider)) {
			$providersUsed = count($byProvider);
			$providersTotal = count($byProvider);
		}

		$services[$serviceKey] = [
			'key' => $serviceKey,
			'label' => $label,
			'providers_used' => $providersUsed,
			'providers_total' => $providersTotal,
			'count' => $count,
			'volume' => $volume,
			'commission' => $commission,
			'customers' => $customers,
			'by_provider' => $byProvider,
		];
	};

	$addService('momoremits', 'MomoRemit', (array)($perf['momoremits']['Overview'] ?? []), (array)($perf['momoremits']['By_Network'] ?? []));
	$addService('agencybankings', 'Agency Banking', (array)($perf['agencybankings']['Overview'] ?? []), (array)($perf['agencybankings']['By_Network'] ?? []));
	$addService('sims', 'SIMs', (array)($perf['sims']['Summary'] ?? []), (array)($perf['sims']['By_Network'] ?? []));
	$addService('airtimebundles', 'Airtime Bundles', (array)($perf['airtimebundles']['Overview'] ?? []), (array)($perf['airtimebundles']['By_Network'] ?? []));
	$addService('cfas', 'CFA', (array)($perf['cfas']['Summary'] ?? []), []);
	$addService('ezwichs', 'Ezwich', (array)($perf['ezwichs']['Overview'] ?? []), []);
	$addService('payment_services', 'Payment Services', (array)($perf['payment_services']['Summary'] ?? []), (array)($perf['payment_services']['By_Category'] ?? []));
	$addService('cashpickups', 'Cash Pickups', (array)($perf['cashpickups']['Summary'] ?? []), (array)($perf['cashpickups']['By_Status'] ?? []));
	$addService('reversals', 'Reversals', (array)($perf['reversals']['Summary'] ?? []), []);
	$addService('deposits', 'Deposits', (array)($perf['deposits']['Overview'] ?? []), (array)($perf['deposits']['By_Network'] ?? []));
	$addService('withdrawals', 'Withdrawals', (array)($perf['withdrawals']['Overview'] ?? []), (array)($perf['withdrawals']['By_Network'] ?? []));
	$addService('payments', 'Payments', (array)($perf['payments']['Summary'] ?? []), []);
	$addService('sendings', 'Sendings', (array)($perf['sendings']['Overview'] ?? []), (array)($perf['sendings']['By_Network'] ?? []));
	$addService(
		'tickets',
		'Tickets',
		[
			'total_transactions' => array_sum(array_map(function ($r) {
				return (float)($r['count'] ?? 0);
			}, (array)($perf['tickets']['By_Status'] ?? []))),
		],
		(array)($perf['tickets']['By_Status'] ?? [])
	);
	$addService('backoffice_requests', 'Backoffice Requests', ['total_transactions' => array_sum(array_map(function ($r) { return (float)($r['total_count'] ?? 0); }, (array)($perf['requests']['Requests'] ?? [])))], []);
	$addService('backoffice_payments', 'Backoffice Payments', ['total_transactions' => array_sum(array_map(function ($r) { return (float)($r['total_transactions'] ?? 0); }, (array)($perf['requests']['Backoffice_Payments'] ?? [])))], []);
	$addService('commissions', 'Commissions', (array)($perf['commissions']['Summary'] ?? []), (array)($perf['commissions']['By_Network'] ?? []));

	return $services;
};

$allServices = $buildServices(is_array($perfAllUsers) ? $perfAllUsers : []);

$getClearingAll = function (string $table) use ($db, $buildWhereFor, $normalizeRows): array {
	if (!$db->table_exists([$table])) {
		return [];
	}
	$w = $buildWhereFor($table, 't');
	if (($w['date_col'] ?? '') === '') {
		return [];
	}
	$where = (string)($w['where'] ?? 'WHERE 1=1');
	$params = is_array($w['params'] ?? []) ? $w['params'] : [];
	$byNetwork = [];
	$byType = [];
	$byNetworkType = [];
	try {
		$byNetwork = $normalizeRows($db->run("SELECT t.network, COUNT(*) AS total_count, SUM(CAST(t.amount AS DECIMAL(10,2))) AS total_amount FROM {$table} t {$where} GROUP BY t.network ORDER BY total_amount DESC", $params));
	} catch (\Throwable $e) {
		$byNetwork = [];
	}
	try {
		$byType = $normalizeRows($db->run("SELECT t.transaction_type, COUNT(*) AS total_count, SUM(CAST(t.amount AS DECIMAL(10,2))) AS total_amount FROM {$table} t {$where} GROUP BY t.transaction_type ORDER BY total_amount DESC", $params));
	} catch (\Throwable $e) {
		$byType = [];
	}
	try {
		$byNetworkType = $normalizeRows($db->run("SELECT t.network, t.transaction_type, COUNT(*) AS total_count, SUM(CAST(t.amount AS DECIMAL(10,2))) AS total_amount FROM {$table} t {$where} GROUP BY t.network, t.transaction_type ORDER BY total_amount DESC", $params));
	} catch (\Throwable $e) {
		$byNetworkType = [];
	}
	return [
		'By_Network' => $byNetwork,
		'By_Type' => $byType,
		'By_Network_Type' => $byNetworkType,
	];
};

$clearingAll = [
	'agencybankings' => $getClearingAll('agencybankings'),
	'momoremits' => $getClearingAll('momoremits'),
	'airtimebundles' => $getClearingAll('airtimebundles'),
];

$agentRows = [];

$totalDaysInPeriod = (int)($start->diff($end)->days) + 1;

foreach ($users as $u) {
	$uid = (string)($u->id ?? '');
	if ($uid === '') {
		continue;
	}
	$name = trim((string)($u->first_name ?? '') . ' ' . (string)($u->last_name ?? ''));

	$perf = $fetchPerf($uid);
	$agencyClearing = $getClearing('agencybankings', $uid);
	$momoClearing = $getClearing('momoremits', $uid);
	$airtimeClearing = $getClearing('airtimebundles', $uid);

	$attendanceTime = '';
	$attendanceRemark = '';
	$attendancePresent = 0;
	$attendanceLate = 0;
	$attendanceAbsent = $totalDaysInPeriod;
	if ($db->table_exists(['attendance'])) {
		if ($period === 'daily') {
			$a = $db->query(
				"SELECT time, remarks FROM attendance WHERE user_id = :uid AND date = :d ORDER BY id DESC LIMIT 1",
				['uid' => $uid, 'd' => $startYmd]
			) ?: [];
		if (!empty($a[0])) {
			$attendanceTime = (string)($a[0]->time ?? '');
			$attendanceRemark = (string)($a[0]->remarks ?? '');
		}
	}

		$attendanceLateThreshold = '08:30:00';
		try {
			if ($db->table_exists(['settings'])) {
				$settingsRow = ($db->query("SELECT work_open, late_attendance_time FROM settings ORDER BY id DESC LIMIT 1") ?: [])[0] ?? null;
				$lateAttendanceTime = $settingsRow ? trim((string)($settingsRow->late_attendance_time ?? '')) : '';
				$workOpen = $settingsRow ? trim((string)($settingsRow->work_open ?? '')) : '';
				if ($lateAttendanceTime !== '') {
					if (strlen($lateAttendanceTime) === 5) $lateAttendanceTime .= ':00';
					$attendanceLateThreshold = $lateAttendanceTime;
				} else {
					if ($workOpen === '') $workOpen = '08:00:00';
					if (strlen($workOpen) === 5) $workOpen .= ':00';
					$workOpenTs = strtotime('1970-01-01 ' . $workOpen);
					$attendanceLateThreshold = ($workOpenTs !== false) ? date('H:i:s', $workOpenTs + (30 * 60)) : '08:30:00';
				}
			}
		} catch (\Throwable $e) {
			$attendanceLateThreshold = '08:30:00';
		}

		$attendanceRemarkUpper = strtoupper(trim($attendanceRemark));
		if (($attendanceRemarkUpper === 'PRESENT' || $attendanceRemarkUpper === 'LATE') && $attendanceTime !== '') {
			$attendanceTimeValue = strlen($attendanceTime) === 5 ? $attendanceTime . ':00' : $attendanceTime;
			$attendanceTimeTs = strtotime('1970-01-01 ' . $attendanceTimeValue);
			$attendanceThresholdTs = strtotime('1970-01-01 ' . $attendanceLateThreshold);
			if ($attendanceTimeTs !== false && $attendanceThresholdTs !== false) {
				$attendanceRemark = $attendanceTimeTs >= $attendanceThresholdTs ? 'LATE' : 'PRESENT';
			}
		}

		$attAgg = $db->query(
			"SELECT
				COUNT(DISTINCT date) AS present_days,
				SUM(
					CASE
						WHEN UPPER(remarks) = 'LATE' THEN 1
						WHEN UPPER(remarks) = 'PRESENT' AND time IS NOT NULL AND time <> '' AND TIME_TO_SEC(time) >= TIME_TO_SEC(:lateThreshold) THEN 1
						ELSE 0
					END
				) AS late_days
			FROM attendance
			WHERE user_id = :uid AND date BETWEEN :s AND :e",
			['uid' => $uid, 's' => $startYmd, 'e' => $endYmd, 'lateThreshold' => $attendanceLateThreshold]
		) ?: [];
		if (!empty($attAgg[0])) {
			$attendancePresent = (int)($attAgg[0]->present_days ?? 0);
			$attendanceLate = (int)($attAgg[0]->late_days ?? 0);
			$attendanceAbsent = max(0, $totalDaysInPeriod - $attendancePresent);
		}
	}

	$commissionEarned = 0;
	$sumFromPerf = function (array $perf) use ($toFloat): array {
		$sumCount = 0.0;
		$sumVolume = 0.0;
		$sumCommission = 0.0;

		$pick = function ($arr, array $keys) use ($toFloat): float {
			if (!is_array($arr)) {
				return 0.0;
			}
			foreach ($keys as $k) {
				if (isset($arr[$k])) {
					$v = $toFloat($arr[$k]);
					if ($v != 0.0 || $arr[$k] === 0 || $arr[$k] === '0') {
						return $v;
					}
				}
			}
			return 0.0;
		};

		foreach ($perf as $activity => $data) {
			if (!is_array($data)) {
				continue;
			}
			if ((string)$activity === 'attendance') {
				continue;
			}
			$top = [];
			if (isset($data['Overview']) && is_array($data['Overview'])) {
				$top = $data['Overview'];
			} elseif (isset($data['Summary']) && is_array($data['Summary'])) {
				$top = $data['Summary'];
			}
			if (!is_array($top)) {
				continue;
			}

			$sumCount += $pick($top, ['total_transactions', 'transaction_count', 'customers_served', 'total_customers', 'total_quantity', 'count']);
			$sumVolume += $pick($top, ['total_amount', 'total_ghs', 'total_volume', 'amount', 'total_debit', 'total_credit']);
			$sumCommission += $pick($top, ['total_commission', 'commission']);
			$sumCommission += $pick($top, ['total_charge', 'charge', 'charges']);
		}

		return [
			'count' => $sumCount,
			'volume' => $sumVolume,
			'commission' => $sumCommission,
		];
	};

	$commissionEarned = (float)($sumFromPerf(is_array($perf) ? $perf : [])['commission'] ?? 0);

	$servicesMap = $buildServices(is_array($perf) ? $perf : []);
	$services = array_values($servicesMap);

	$generalCount = 0.0;
	$generalVolume = 0.0;
	foreach ($services as $sv) {
		if (!is_array($sv)) {
			continue;
		}
		if (($sv['key'] ?? '') === 'commissions') {
			continue;
		}
		$generalCount += (float)($sv['count'] ?? 0);
		$generalVolume += (float)($sv['volume'] ?? 0);
	}

	// Reconciliation interpretation MUST match backoffice reconciliations view/list logic:
	// Overage/Shortage are derived from the "misc" row balance for each reconciliation session.
	$reconBalancedCount = 0;
	$reconOverageCount = 0;
	$reconShortageCount = 0;
	$overage = 0.0;
	$shortage = 0.0;
	$reconStatus = 'Balanced';

	if ($db->table_exists(['reconciliations_summary', 'reconciliations'])) {
		$reconRows = $db->query(
			"SELECT m.session_id, m.remarks, m.variance, m.date_created, COALESCE(misc.balance, 0) AS misc_balance
			 FROM reconciliations_summary AS m
			 LEFT JOIN reconciliations AS misc ON misc.session_id = m.session_id AND misc.wallet_id = 'misc'
			 WHERE (m.deleted IS NULL OR m.deleted = 0)
			 AND m.created_by = :uid
			 AND DATE(m.date_created) BETWEEN :s AND :e
			 ORDER BY m.date_created DESC",
			['uid' => $uid, 's' => $startYmd, 'e' => $endYmd]
		) ?: [];

		foreach ($reconRows as $rr) {
			$miscBalance = (float)($rr->misc_balance ?? 0);
			$remarks = (string)($rr->remarks ?? '');
			if (abs($miscBalance) >= 0.01) {
				if ($miscBalance > 0) {
					$reconShortageCount++;
					$shortage += abs($miscBalance);
				} else {
					$reconOverageCount++;
					$overage += abs($miscBalance);
				}
			} elseif (strtolower(trim($remarks)) === 'balanced') {
				$reconBalancedCount++;
			}
		}

		if ($reconOverageCount > 0) {
			$reconStatus = 'Overage';
		} elseif ($reconShortageCount > 0) {
			$reconStatus = 'Shortage';
		} elseif ($reconBalancedCount > 0) {
			$reconStatus = 'Balanced';
		}
	}

	$agentRows[] = [
		'user_id' => $uid,
		'name' => $name,
		'attendance_time' => $attendanceTime,
		'attendance_remark' => $attendanceRemark,
		'attendance_present' => $attendancePresent,
		'attendance_late' => $attendanceLate,
		'attendance_absent' => $attendanceAbsent,
		'general_count' => $generalCount,
		'general_volume' => $generalVolume,
		'general_commission' => $commissionEarned,
		'reconciliation_status' => $reconStatus,
		'reconciliation_balanced_count' => $reconBalancedCount,
		'reconciliation_overage_count' => $reconOverageCount,
		'reconciliation_shortage_count' => $reconShortageCount,
		'overage' => $overage,
		'shortage' => $shortage,
		'services' => $services,
		'services_map' => $servicesMap,
		'performance' => $perf,
		'clearing' => [
			'agencybankings' => $agencyClearing,
			'momoremits' => $momoClearing,
			'airtimebundles' => $airtimeClearing,
		],
		'clearing_all' => $clearingAll,
	];
};

// Fallback: if all-users baseline is empty/zero (observed in some monthly/custom runs),
// compute "All" totals by summing per-user metrics for the same period.
$allServicesTotalCount = 0.0;
foreach ($allServices as $sv) {
	$allServicesTotalCount += (float)($sv['count'] ?? 0);
}
if ((empty($allServices) || $allServicesTotalCount <= 0.0) && $db->table_exists(['users'])) {
	$usersAll = $db->query("SELECT id, first_name, last_name FROM users WHERE deleted = 0 ORDER BY first_name ASC, last_name ASC") ?: [];
	$agg = [];
	foreach ($usersAll as $uu) {
		$uid2 = (string)($uu->id ?? '');
		if ($uid2 === '') {
			continue;
		}
		$perf2 = $fetchPerf($uid2);
		$svc2 = $buildServices(is_array($perf2) ? $perf2 : []);
		foreach ($svc2 as $k => $sv) {
			if (!isset($agg[$k])) {
				$agg[$k] = $sv;
				$agg[$k]['providers_used'] = 0;
				$agg[$k]['providers_total'] = 0;
				$agg[$k]['by_provider'] = [];
				continue;
			}
			$agg[$k]['count'] = (float)($agg[$k]['count'] ?? 0) + (float)($sv['count'] ?? 0);
			$agg[$k]['volume'] = (float)($agg[$k]['volume'] ?? 0) + (float)($sv['volume'] ?? 0);
			$agg[$k]['customers'] = (float)($agg[$k]['customers'] ?? 0) + (float)($sv['customers'] ?? 0);
			$agg[$k]['commission'] = (float)($agg[$k]['commission'] ?? 0) + (float)($sv['commission'] ?? 0);
		}
	}
	if (!empty($agg)) {
		$allServices = $agg;
	}
}

$overallCount = 0.0;
$overallVolume = 0.0;
$overallCommission = 0.0;
foreach ($agentRows as $ar) {
	$overallCount += (float)($ar['general_count'] ?? 0);
	$overallVolume += (float)($ar['general_volume'] ?? 0);
	$overallCommission += (float)($ar['general_commission'] ?? 0);
}

foreach ($agentRows as $i => $ar) {
	$c = (float)($ar['general_count'] ?? 0);
	$v = (float)($ar['general_volume'] ?? 0);
	$m = (float)($ar['general_commission'] ?? 0);
	$agentRows[$i]['general_count_share'] = $overallCount > 0 ? round($c / $overallCount * 100, 2) : 0;
	$agentRows[$i]['general_volume_share'] = $overallVolume > 0 ? round($v / $overallVolume * 100, 2) : 0;
	$agentRows[$i]['general_commission_share'] = $overallCommission > 0 ? round($m / $overallCommission * 100, 2) : 0;
}

$reconSummaryOverall = [
	'balanced_count' => 0,
	'overage_count' => 0,
	'shortage_count' => 0,
	'overage_total' => 0.0,
	'shortage_total' => 0.0,
];

foreach ($agentRows as $ar) {
	$st = (string)($ar['reconciliation_status'] ?? '');
	if ($st === 'Overage') {
		$reconSummaryOverall['overage_count']++;
		$reconSummaryOverall['overage_total'] += (float)($ar['overage'] ?? 0);
	} elseif ($st === 'Shortage') {
		$reconSummaryOverall['shortage_count']++;
		$reconSummaryOverall['shortage_total'] += (float)($ar['shortage'] ?? 0);
	} else {
		$reconSummaryOverall['balanced_count']++;
	}
}

$reconSummaryOverall['net_variance'] = (float)$reconSummaryOverall['overage_total'] - (float)$reconSummaryOverall['shortage_total'];

$overallActivityTotals = [];
foreach ($agentRows as $ar) {
	$perf = $ar['performance'] ?? [];
	if (!is_array($perf)) {
		continue;
	}
	foreach ($perf as $activity => $data) {
		if (!is_array($data)) {
			continue;
		}
		$activity = (string)$activity;
		if ($activity === 'transactions' || $activity === 'attendance') {
			continue;
		}

		if (!isset($overallActivityTotals[$activity])) {
			$overallActivityTotals[$activity] = ['count' => 0.0, 'volume' => 0.0, 'commission' => 0.0];
		}

		$top = [];
		if (isset($data['Overview']) && is_array($data['Overview'])) {
			$top = $data['Overview'];
		} elseif (isset($data['Summary']) && is_array($data['Summary'])) {
			$top = $data['Summary'];
		}

		$pick = function ($arr, array $keys) use ($toFloat): float {
			if (!is_array($arr)) {
				return 0.0;
			}
			foreach ($keys as $k) {
				if (!array_key_exists($k, $arr)) {
					continue;
				}
				$v = $toFloat($arr[$k]);
				if ($v != 0.0 || $arr[$k] === 0 || $arr[$k] === '0') {
					return $v;
				}
			}
			return 0.0;
		};

		$cnt = $pick($top, ['total_transactions', 'customers_served', 'total_customers', 'total_quantity', 'transaction_count', 'count']);
		$vol = $pick($top, ['total_amount', 'total_ghs', 'total_volume', 'amount']);
		$com = $pick($top, ['total_commission', 'commission']);
		$com += $pick($top, ['total_charge', 'charge', 'charges']);

		// Special cases
		if ($activity === 'requests') {
			$reqRows = $data['Requests'] ?? [];
			$reqRowsN = is_array($reqRows) ? $reqRows : [];
			$cnt = 0.0;
			$vol = 0.0;
			foreach ($reqRowsN as $rr) {
				if (is_object($rr)) {
					$rr = (array)$rr;
				}
				if (is_array($rr)) {
					$cnt += (float)($rr['total_count'] ?? 0);
					$vol += (float)($rr['total_amount'] ?? 0);
				}
			}
		}
		if ($activity === 'tickets') {
			$ticketRows = $data['By_Status'] ?? [];
			$ticketRowsN = is_array($ticketRows) ? $ticketRows : [];
			$cnt = 0.0;
			foreach ($ticketRowsN as $rr) {
				if (is_object($rr)) {
					$rr = (array)$rr;
				}
				if (is_array($rr)) {
					$cnt += (float)($rr['count'] ?? 0);
				}
			}
		}

		$overallActivityTotals[$activity]['count'] += $cnt;
		$overallActivityTotals[$activity]['volume'] += $vol;
		$overallActivityTotals[$activity]['commission'] += $com;
	}
}

$titlePeriod = $period === 'daily'
	? $start->format('Y-m-d')
	: ($period === 'monthly'
		? $start->format('F Y')
		: ($startYmd . ' to ' . $endYmd));

$reportTitle = $period === 'daily'
	? 'End of Day Report per Agent'
	: ($period === 'monthly' ? 'Monthly Performance Report per Agent' : 'Performance Report per Agent');

if ($format === 'xlsx' || $format === 'excel') {
	$requireAutoload();

	$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
	$sheet = $spreadsheet->getActiveSheet();
	$sheet->setTitle($isAllUsersReport ? 'Summary' : 'EOD');

	$sanitizeSheetName = function (string $name): string {
		$name = trim($name);
		$name = preg_replace('/[\\\\\/\?\*\[\]:]/', '-', $name);
		$name = preg_replace('/\s+/', ' ', $name);
		$name = trim($name);
		if ($name === '') {
			$name = 'Agent';
		}
		if (strlen($name) > 31) {
			$name = substr($name, 0, 31);
		}
		return $name;
	};

	$writeAgentSheet = function (\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, array $ar) use ($allServices, $isAllUsersReport): void {
		$r2 = 1;
		$ws->setCellValue('A' . $r2, (string)($ar['name'] ?? ''));
		$r2 += 2;
		$ws->setCellValue('A' . $r2, 'Arrival Time');
		$ws->setCellValue('B' . $r2, (string)($ar['attendance_time'] ?? ''));
		$r2++;
		$ws->setCellValue('A' . $r2, 'Attendance');
		$ws->setCellValue('B' . $r2, 'Present: ' . (string)($ar['attendance_present'] ?? 0) . ' | Late: ' . (string)($ar['attendance_late'] ?? 0) . ' | Absent: ' . (string)($ar['attendance_absent'] ?? 0));
		$r2++;
		$ws->setCellValue('A' . $r2, 'Total Transactions');
		$ws->setCellValue('B' . $r2, (float)($ar['general_count'] ?? 0));
		$r2++;
		$ws->setCellValue('A' . $r2, 'Total Volume');
		$ws->setCellValue('B' . $r2, (float)($ar['general_volume'] ?? 0));
		$r2++;
		$ws->setCellValue('A' . $r2, 'Total Commission');
		$ws->setCellValue('B' . $r2, (float)($ar['general_commission'] ?? 0));
		$r2 += 2;

		$ws->setCellValue('A' . $r2, 'Service');
		$ws->setCellValue('B' . $r2, 'User/All Providers');
		$ws->setCellValue('C' . $r2, 'User Count');
		$ws->setCellValue('D' . $r2, 'All Count');
		$ws->setCellValue('E' . $r2, 'Count Share %');
		$ws->setCellValue('F' . $r2, 'User Amount');
		$ws->setCellValue('G' . $r2, 'All Volume');
		$ws->setCellValue('H' . $r2, 'Volume Share %');
		$ws->setCellValue('I' . $r2, 'User Customers');
		$ws->setCellValue('J' . $r2, 'All Customers');
		$ws->setCellValue('K' . $r2, 'Customers Share %');
		$ws->setCellValue('L' . $r2, 'User Commission');
		$ws->setCellValue('M' . $r2, 'All Commission');
		$ws->setCellValue('N' . $r2, 'Commission Share %');
		$r2++;

		$servicesMap = (array)($ar['services_map'] ?? []);
		foreach (array_keys($allServices) as $k) {
			$sv = $servicesMap[$k] ?? [];
			$allSv = $allServices[$k] ?? [];
			$uCnt = (float)($sv['count'] ?? 0);
			$aCnt = (float)($allSv['count'] ?? 0);
			$uVol = (float)($sv['volume'] ?? 0);
			$aVol = (float)($allSv['volume'] ?? 0);
			$uCust = (float)($sv['customers'] ?? 0);
			$aCust = (float)($allSv['customers'] ?? 0);
			$uCom = (float)($sv['commission'] ?? 0);
			$aCom = (float)($allSv['commission'] ?? 0);

			if (!$isAllUsersReport && $uCnt <= 0 && $uVol <= 0 && $uCust <= 0 && $uCom <= 0) {
				continue;
			}

			$ws->setCellValue('A' . $r2, (string)($allSv['label'] ?? ($sv['label'] ?? (string)$k)));
			$allProvidersUsed = (int)($allSv['providers_used'] ?? ($allSv['providers_total'] ?? 0));
			$ws->setCellValue('B' . $r2, (string)((int)($sv['providers_used'] ?? 0) . '/' . $allProvidersUsed));
			$ws->setCellValue('C' . $r2, $uCnt);
			$ws->setCellValue('D' . $r2, $aCnt);
			$ws->setCellValue('E' . $r2, $aCnt > 0 ? round($uCnt / $aCnt * 100, 2) : 0);
			$ws->setCellValue('F' . $r2, $uVol);
			$ws->setCellValue('G' . $r2, $aVol);
			$ws->setCellValue('H' . $r2, $aVol > 0 ? round($uVol / $aVol * 100, 2) : 0);
			$ws->setCellValue('I' . $r2, $uCust);
			$ws->setCellValue('J' . $r2, $aCust);
			$ws->setCellValue('K' . $r2, $aCust > 0 ? round($uCust / $aCust * 100, 2) : 0);
			$ws->setCellValue('L' . $r2, $uCom);
			$ws->setCellValue('M' . $r2, $aCom);
			$ws->setCellValue('N' . $r2, $aCom > 0 ? round($uCom / $aCom * 100, 2) : 0);
			$r2++;
		}
	};

	$r = 1;
	$sheet->setCellValue('A' . $r, $reportTitle);
	$sheet->setCellValue('B' . $r, $titlePeriod);
	$r += 2;

	if ($isAllUsersReport) {
		$sheet->setCellValue('A' . $r, 'Overall Summary');
		$r++;
		$sheet->setCellValue('A' . $r, 'Overall total transactions');
		$sheet->setCellValue('B' . $r, $overallCount);
		$r++;
		$sheet->setCellValue('A' . $r, 'Overall total volume');
		$sheet->setCellValue('B' . $r, $overallVolume);
		$r++;
		$sheet->setCellValue('A' . $r, 'Overall total commission');
		$sheet->setCellValue('B' . $r, $overallCommission);
		$r += 2;

		$sheet->setCellValue('A' . $r, 'Reconciliation Summary');
		$r++;
		$sheet->setCellValue('A' . $r, 'Balanced');
		$sheet->setCellValue('B' . $r, (int)($reconSummaryOverall['balanced_count'] ?? 0));
		$r++;
		$sheet->setCellValue('A' . $r, 'Overage');
		$sheet->setCellValue('B' . $r, (int)($reconSummaryOverall['overage_count'] ?? 0));
		$sheet->setCellValue('C' . $r, (float)($reconSummaryOverall['overage_total'] ?? 0));
		$r++;
		$sheet->setCellValue('A' . $r, 'Shortage');
		$sheet->setCellValue('B' . $r, (int)($reconSummaryOverall['shortage_count'] ?? 0));
		$sheet->setCellValue('C' . $r, (float)($reconSummaryOverall['shortage_total'] ?? 0));
		$r++;
		$sheet->setCellValue('A' . $r, 'Net variance');
		$sheet->setCellValue('B' . $r, (float)($reconSummaryOverall['net_variance'] ?? 0));
		$r += 2;

		$sheet->setCellValue('A' . $r, 'Overall Activity Totals');
		$r++;
		$sheet->setCellValue('A' . $r, 'Activity');
		$sheet->setCellValue('B' . $r, 'Count');
		$sheet->setCellValue('C' . $r, 'Volume');
		$sheet->setCellValue('D' . $r, 'Commission');
		$r++;
		foreach ($overallActivityTotals as $act => $tot) {
			$sheet->setCellValue('A' . $r, (string)$act);
			$sheet->setCellValue('B' . $r, (float)($tot['count'] ?? 0));
			$sheet->setCellValue('C' . $r, (float)($tot['volume'] ?? 0));
			$sheet->setCellValue('D' . $r, (float)($tot['commission'] ?? 0));
			$r++;
		}
		$r += 2;
	}

	if ($isAllUsersReport) {
		$usedNames = [];
		foreach ($agentRows as $ar) {
			$base = $sanitizeSheetName((string)($ar['name'] ?? 'Agent'));
			$name = $base;
			$i = 2;
			while (isset($usedNames[strtolower($name)])) {
				$suffix = ' ' . $i;
				$maxBase = 31 - strlen($suffix);
				$name = substr($base, 0, $maxBase) . $suffix;
				$i++;
			}
			$usedNames[strtolower($name)] = true;
			$userSheet = $spreadsheet->createSheet();
			$userSheet->setTitle($name);
			$writeAgentSheet($userSheet, $ar);
		}
	} else {
		$sheet->setCellValue('A' . $r, 'Agent');
		$sheet->setCellValue('B' . $r, 'Arrival Time');
		$sheet->setCellValue('C' . $r, 'Attendance');
		$sheet->setCellValue('D' . $r, 'Total Transactions');
		$sheet->setCellValue('E' . $r, 'Total Volume');
		$sheet->setCellValue('F' . $r, 'Total Commission');
		$sheet->setCellValue('G' . $r, 'Tx Share %');
		$sheet->setCellValue('H' . $r, 'Volume Share %');
		$sheet->setCellValue('I' . $r, 'Commission Share %');
		$sheet->setCellValue('J' . $r, 'Reconciliation');
		$sheet->setCellValue('K' . $r, 'Overage');
		$sheet->setCellValue('L' . $r, 'Shortage');
		$r++;

		foreach ($agentRows as $ar) {
			$sheet->setCellValue('A' . $r, (string)($ar['name'] ?? ''));
			$sheet->setCellValue('B' . $r, (string)($ar['attendance_time'] ?? ''));
			$sheet->setCellValue('C' . $r, 'Present: ' . (string)($ar['attendance_present'] ?? 0) . ' | Late: ' . (string)($ar['attendance_late'] ?? 0) . ' | Absent: ' . (string)($ar['attendance_absent'] ?? 0));
			$sheet->setCellValue('D' . $r, (float)($ar['general_count'] ?? 0));
			$sheet->setCellValue('E' . $r, (float)($ar['general_volume'] ?? 0));
			$sheet->setCellValue('F' . $r, (float)($ar['general_commission'] ?? 0));
			$sheet->setCellValue('G' . $r, (float)($ar['general_count_share'] ?? 0));
			$sheet->setCellValue('H' . $r, (float)($ar['general_volume_share'] ?? 0));
			$sheet->setCellValue('I' . $r, (float)($ar['general_commission_share'] ?? 0));
			$sheet->setCellValue(
				'J' . $r,
				'Balanced: ' . (string)($ar['reconciliation_balanced_count'] ?? 0)
					. ' | Overage: ' . (string)($ar['reconciliation_overage_count'] ?? 0)
					. ' | Shortage: ' . (string)($ar['reconciliation_shortage_count'] ?? 0)
			);
			$sheet->setCellValue('K' . $r, (float)($ar['overage'] ?? 0));
			$sheet->setCellValue('L' . $r, (float)($ar['shortage'] ?? 0));
			$r++;

			$sheet->setCellValue('B' . $r, 'Service');
			$sheet->setCellValue('C' . $r, 'User/All Providers');
			$sheet->setCellValue('D' . $r, 'User Count');
			$sheet->setCellValue('E' . $r, 'All Count');
			$sheet->setCellValue('F' . $r, 'Count Share %');
			$sheet->setCellValue('G' . $r, 'User Amount');
			$sheet->setCellValue('H' . $r, 'All Volume');
			$sheet->setCellValue('I' . $r, 'Volume Share %');
			$sheet->setCellValue('J' . $r, 'User Customers');
			$sheet->setCellValue('K' . $r, 'All Customers');
			$sheet->setCellValue('L' . $r, 'Customers Share %');
			$sheet->setCellValue('M' . $r, 'User Commission');
			$sheet->setCellValue('N' . $r, 'All Commission');
			$sheet->setCellValue('O' . $r, 'Commission Share %');
			$r++;

			$servicesMap = (array)($ar['services_map'] ?? []);
			$serviceKeys = array_keys($allServices);
			foreach ($serviceKeys as $k) {
				$sv = $servicesMap[$k] ?? [];
				$allSv = $allServices[$k] ?? [];

				$uCnt = (float)($sv['count'] ?? 0);
				$aCnt = (float)($allSv['count'] ?? 0);
				$uVol = (float)($sv['volume'] ?? 0);
				$aVol = (float)($allSv['volume'] ?? 0);
				$uCust = (float)($sv['customers'] ?? 0);
				$aCust = (float)($allSv['customers'] ?? 0);
				$uCom = (float)($sv['commission'] ?? 0);
				$aCom = (float)($allSv['commission'] ?? 0);

				if (!$isAllUsersReport && $uCnt <= 0 && $uVol <= 0 && $uCust <= 0 && $uCom <= 0) {
					continue;
				}

				$sheet->setCellValue('B' . $r, (string)($allSv['label'] ?? ($sv['label'] ?? (string)$k)));
				$allProvidersUsed = (int)($allSv['providers_used'] ?? ($allSv['providers_total'] ?? 0));
				$sheet->setCellValue('C' . $r, (string)((int)($sv['providers_used'] ?? 0) . '/' . $allProvidersUsed));
				$sheet->setCellValue('D' . $r, $uCnt);
				$sheet->setCellValue('E' . $r, $aCnt);
				$sheet->setCellValue('F' . $r, $aCnt > 0 ? round($uCnt / $aCnt * 100, 2) : 0);
				$sheet->setCellValue('G' . $r, $uVol);
				$sheet->setCellValue('H' . $r, $aVol);
				$sheet->setCellValue('I' . $r, $aVol > 0 ? round($uVol / $aVol * 100, 2) : 0);
				$sheet->setCellValue('J' . $r, $uCust);
				$sheet->setCellValue('K' . $r, $aCust);
				$sheet->setCellValue('L' . $r, $aCust > 0 ? round($uCust / $aCust * 100, 2) : 0);
				$sheet->setCellValue('M' . $r, $uCom);
				$sheet->setCellValue('N' . $r, $aCom);
				$sheet->setCellValue('O' . $r, $aCom > 0 ? round($uCom / $aCom * 100, 2) : 0);
				$r++;
			}

			$r++;
		}
	}

	$details = $spreadsheet->createSheet();
	$details->setTitle('Details');
	$dr = 1;
	$details->setCellValue('A' . $dr, 'Agent');
	$details->setCellValue('B' . $dr, 'Section');
	$details->setCellValue('C' . $dr, 'Subsection');
	$details->setCellValue('D' . $dr, 'Key');
	$details->setCellValue('E' . $dr, 'User');
	$details->setCellValue('F' . $dr, 'All');
	$details->setCellValue('G' . $dr, 'Share %');
	$dr++;

	$writeKeyValue = function ($agent, $section, $sub, $data, $allData = []) use (&$details, &$dr): void {
		if (!is_array($data)) {
			return;
		}
		foreach ($data as $k => $v) {
			if (is_array($v) || is_object($v)) {
				continue;
			}
			$allVal = '';
			$share = '';
			if (is_array($allData) && array_key_exists($k, $allData) && !is_array($allData[$k]) && !is_object($allData[$k])) {
				$allVal = $allData[$k];
				if (is_numeric($v) && is_numeric($allVal)) {
					$allNum = (float)$allVal;
					$share = $allNum > 0 ? round(((float)$v) / $allNum * 100, 2) : 0;
				}
			}
			$details->setCellValue('A' . $dr, (string)$agent);
			$details->setCellValue('B' . $dr, (string)$section);
			$details->setCellValue('C' . $dr, (string)$sub);
			$details->setCellValue('D' . $dr, (string)$k);
			$details->setCellValue('E' . $dr, $v);
			$details->setCellValue('F' . $dr, $allVal);
			$details->setCellValue('G' . $dr, $share);
			$dr++;
		}
	};

	$writeBreakdownComparison = function ($agent, $section, $sub, $userRows, $allRows) use (&$details, &$dr, $normalizeRows): void {
		$userN = $normalizeRows($userRows);
		$allN = $normalizeRows($allRows);
		if (empty($userN) && empty($allN)) {
			return;
		}

		if ((string)$section === 'attendance' && (string)$sub === 'Days_Worked') {
			$sum = function (array $rows, string $k): float {
				$s = 0.0;
				foreach ($rows as $rr) {
					if (!is_array($rr)) {
						continue;
					}
					$v = $rr[$k] ?? 0;
					$s += is_numeric($v) ? (float)$v : (float)preg_replace('/[^0-9.\-]/', '', (string)$v);
				}
				return $s;
			};

			foreach (['present', 'late', 'absent'] as $metric) {
				$uVal = $sum($userN, $metric);
				$aVal = $sum($allN, $metric);
				$details->setCellValue('A' . $dr, (string)$agent);
				$details->setCellValue('B' . $dr, (string)$section);
				$details->setCellValue('C' . $dr, (string)$sub);
				$details->setCellValue('D' . $dr, strtoupper($metric));
				$details->setCellValue('E' . $dr, $uVal);
				$details->setCellValue('F' . $dr, $aVal);
				$details->setCellValue('G' . $dr, $aVal > 0 ? round($uVal / $aVal * 100, 2) : 0);
				$dr++;
			}
			return;
		}

		$sample = !empty($allN) ? $allN[0] : (!empty($userN) ? $userN[0] : []);
		if (!is_array($sample)) {
			return;
		}

		$allKeys = [];
		foreach (array_merge($allN, $userN) as $rr) {
			if (!is_array($rr)) {
				continue;
			}
			foreach (array_keys($rr) as $k) {
				if (!in_array($k, $allKeys, true)) {
					$allKeys[] = $k;
				}
			}
		}

		$metricName = function (string $k): bool {
			return (bool)preg_match('/^(total_)?(amount|count|customers?|commission|debit|credit|volume)$/i', $k)
				|| (bool)preg_match('/(amount|count|customer|commission|debit|credit|volume)/i', $k);
		};

		$numericKeys = [];
		$keyCols = [];
		foreach ($allKeys as $k) {
			if ($metricName((string)$k)) {
				$numericKeys[] = $k;
				continue;
			}
			$isNum = false;
			foreach (array_merge($allN, $userN) as $rr) {
				$v = is_array($rr) ? ($rr[$k] ?? null) : null;
				if ($v === null || $v === '') {
					continue;
				}
				if (is_numeric($v)) {
					$isNum = true;
					break;
				}
			}
			if ($isNum) {
				$numericKeys[] = $k;
			} else {
				$keyCols[] = $k;
			}
		}

		$mkIndex = function (array $rr) use ($keyCols): string {
			$parts = [];
			foreach ($keyCols as $k) {
				$v = $rr[$k] ?? '';
				if (is_array($v) || is_object($v)) {
					$v = '';
				}
				$parts[] = (string)$v;
			}
			return implode(' | ', $parts);
		};

		$allIndex = [];
		foreach ($allN as $rr) {
			if (!is_array($rr)) {
				continue;
			}
			$allIndex[$mkIndex($rr)] = $rr;
		}

		$userIndex = [];
		foreach ($userN as $rr) {
			if (!is_array($rr)) {
				continue;
			}
			$userIndex[$mkIndex($rr)] = $rr;
		}

		$keysUnion = array_values(array_unique(array_merge(array_keys($allIndex), array_keys($userIndex))));
		foreach ($keysUnion as $idx) {
			$uRow = $userIndex[$idx] ?? [];
			$aRow = $allIndex[$idx] ?? [];
			foreach ($numericKeys as $nk) {
				$uVal = (float)($uRow[$nk] ?? 0);
				$aVal = (float)($aRow[$nk] ?? 0);
				if ($uVal == 0.0 && $aVal != 0.0 && is_array($uRow) && array_key_exists($nk, $uRow) && !is_numeric($uRow[$nk])) {
					$uVal = (float)preg_replace('/[^0-9.\-]/', '', (string)$uRow[$nk]);
				}
				if ($aVal == 0.0 && is_array($aRow) && array_key_exists($nk, $aRow) && !is_numeric($aRow[$nk])) {
					$aVal = (float)preg_replace('/[^0-9.\-]/', '', (string)$aRow[$nk]);
				}
				$details->setCellValue('A' . $dr, (string)$agent);
				$details->setCellValue('B' . $dr, (string)$section);
				$details->setCellValue('C' . $dr, (string)$sub);
				$details->setCellValue('D' . $dr, trim($idx . ' - ' . (string)$nk));
				$details->setCellValue('E' . $dr, $uVal);
				$details->setCellValue('F' . $dr, $aVal);
				$details->setCellValue('G' . $dr, $aVal > 0 ? round($uVal / $aVal * 100, 2) : 0);
				$dr++;
			}
		}
	};

	$writeTable = function ($agent, $section, $sub, $rows) use (&$details, &$dr, $normalizeRows): void {
		$rowsN = $normalizeRows($rows);
		if (empty($rowsN)) {
			return;
		}
		$keys = [];
		foreach ($rowsN as $rr) {
			foreach (array_keys($rr) as $k) {
				if (!in_array($k, $keys, true)) {
					$keys[] = $k;
				}
			}
		}
		foreach ($rowsN as $rr) {
			foreach ($keys as $k) {
				$v = $rr[$k] ?? '';
				if (is_array($v) || is_object($v)) {
					$v = '';
				}
				$details->setCellValue('A' . $dr, (string)$agent);
				$details->setCellValue('B' . $dr, (string)$section);
				$details->setCellValue('C' . $dr, (string)$sub);
				$details->setCellValue('D' . $dr, (string)$k);
				$details->setCellValue('E' . $dr, (string)$v);
				$details->setCellValue('F' . $dr, '');
				$details->setCellValue('G' . $dr, '');
				$dr++;
			}
			$dr++;
		}
	};

	foreach ($agentRows as $ar) {
		$agent = (string)($ar['name'] ?? '');
		$perf = $ar['performance'] ?? [];
		if (is_array($perf)) {
			foreach ($perf as $section => $sectionData) {
				if (!is_array($sectionData)) {
					continue;
				}
				$secCount = $getSectionCount((string)$section, $sectionData);
				$skipBreakdowns = ($secCount === 0.0);
				foreach ($sectionData as $sub => $subData) {
					if (is_array($subData)) {
						$isAssoc = array_keys($subData) !== range(0, count($subData) - 1);
						if ($isAssoc) {
							$allSection = is_array($perfAllUsers) ? ($perfAllUsers[$section] ?? []) : [];
							$allSub = is_array($allSection) ? ($allSection[$sub] ?? []) : [];
							$writeKeyValue($agent, (string)$section, (string)$sub, $subData, $allSub);
						} else {
							if ($skipBreakdowns) {
								continue;
							}
							$allSection = is_array($perfAllUsers) ? ($perfAllUsers[$section] ?? []) : [];
							$allSub = is_array($allSection) ? ($allSection[$sub] ?? []) : [];
							$writeBreakdownComparison($agent, (string)$section, (string)$sub, $subData, $allSub);
						}
					}
				}
				$dr++;
			}
		}

		$clr = $ar['clearing'] ?? [];
		if (is_array($clr)) {
			foreach ($clr as $svc => $svcData) {
				if (!is_array($svcData)) {
					continue;
				}
				foreach ($svcData as $sub => $rows) {
					$allClr = $ar['clearing_all'] ?? [];
					$allSvc = is_array($allClr) ? ($allClr[$svc] ?? []) : [];
					$allRows = is_array($allSvc) ? ($allSvc[$sub] ?? []) : [];
					$writeBreakdownComparison($agent, 'Clearing - ' . (string)$svc, (string)$sub, $rows, $allRows);
				}
				$dr++;
			}
		}
	}

	$fileNamePrefix = $period === 'daily' ? 'performance_daily_' : ($period === 'monthly' ? 'performance_monthly_' : 'performance_custom_');
	$fileName = $fileNamePrefix . date('Ymd_His') . '.xlsx';
	while (ob_get_level()) {
		ob_end_clean();
	}
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment; filename="' . $fileName . '"');
	header('Cache-Control: max-age=0');
	$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
	$writer->save('php://output');
	exit;
}

$requireAutoload();

$escape = function ($v): string {
	return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
};

$html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>';
$html .= 'body{font-family:Tahoma;font-size:12px;}';
$html .= 'h1{font-size:18px;margin:0 0 6px 0;}';
$html .= 'h2{font-size:14px;margin:14px 0 6px 0;}';
$html .= 'table{width:100%;border-collapse:collapse;margin:6px 0;}';
$html .= 'th,td{border:1px solid #ddd;padding:6px;vertical-align:top;}';
$html .= 'th{background:#f5f5f5;}';
$html .= '</style></head><body>';

$renderAgentHeader = function (string $agentName) use ($escape, $reportTitle, $titlePeriod): string {
	$out = '';
	$out .= '<h1>' . $escape($reportTitle) . '</h1>';
	$out .= '<div><strong>Period:</strong> ' . $escape($titlePeriod) . '</div>';
	$out .= '<div><strong>Generated:</strong> ' . $escape(date('Y-m-d H:i:s')) . '</div>';
	$out .= '<h2>' . $escape($agentName) . '</h2>';
	return $out;
};

if ($isAllUsersReport) {
	$html .= '<h1>' . $escape($reportTitle) . '</h1>';
	$html .= '<div><strong>Period:</strong> ' . $escape($titlePeriod) . '</div>';
	$html .= '<div><strong>Generated:</strong> ' . $escape(date('Y-m-d H:i:s')) . '</div>';
	$html .= '<h2>' . $escape('Overall Summary') . '</h2>';

	$html .= '<table>';
	$html .= '<tr><th style="width:40%">Overall total transactions</th><td>' . $escape($overallCount) . '</td></tr>';
	$html .= '<tr><th>Overall total volume</th><td>' . $escape($overallVolume) . '</td></tr>';
	$html .= '<tr><th>Overall total commission</th><td>' . $escape($overallCommission) . '</td></tr>';
	$html .= '</table>';

	$html .= '<h3>Reconciliation Summary</h3>';
	$html .= '<table>';
	$html .= '<tr><th>Status</th><th>Count</th><th>Variance</th></tr>';
	$html .= '<tr><td>Balanced</td><td>' . $escape((int)($reconSummaryOverall['balanced_count'] ?? 0)) . '</td><td>0</td></tr>';
	$html .= '<tr><td>Overage</td><td>' . $escape((int)($reconSummaryOverall['overage_count'] ?? 0)) . '</td><td>' . $escape((float)($reconSummaryOverall['overage_total'] ?? 0)) . '</td></tr>';
	$html .= '<tr><td>Shortage</td><td>' . $escape((int)($reconSummaryOverall['shortage_count'] ?? 0)) . '</td><td>' . $escape((float)($reconSummaryOverall['shortage_total'] ?? 0)) . '</td></tr>';
	$html .= '<tr><th colspan="2">Net variance</th><th>' . $escape((float)($reconSummaryOverall['net_variance'] ?? 0)) . '</th></tr>';
	$html .= '</table>';

	$html .= '<h3>Overall Activity Totals</h3>';
	$html .= '<table>';
	$html .= '<tr><th>Activity</th><th>Count</th><th>Volume</th><th>Commission</th></tr>';
	if (empty($overallActivityTotals)) {
		$html .= '<tr><td colspan="4">' . $escape('No data available') . '</td></tr>';
	} else {
		foreach ($overallActivityTotals as $act => $tot) {
			$html .= '<tr>';
			$html .= '<td>' . $escape(ucwords(str_replace('_', ' ', (string)$act))) . '</td>';
			$html .= '<td>' . $escape((float)($tot['count'] ?? 0)) . '</td>';
			$html .= '<td>' . $escape((float)($tot['volume'] ?? 0)) . '</td>';
			$html .= '<td>' . $escape((float)($tot['commission'] ?? 0)) . '</td>';
			$html .= '</tr>';
		}
	}
	$html .= '</table>';

	$html .= '<pagebreak />';
}

$idx = 0;
foreach ($agentRows as $ar) {
	$perf = $ar['performance'] ?? [];
	$clearing = $ar['clearing'] ?? [];
	if ($idx > 0) {
		$html .= '<pagebreak />';
	}
	$html .= $renderAgentHeader((string)($ar['name'] ?? ''));
	$html .= '<table><tr>';
	$html .= '<th>Time of arrival</th><th>Attendance</th><th>Total transactions</th><th>Total volume</th><th>Total commission</th><th>Tx share %</th><th>Volume share %</th><th>Commission share %</th><th>Reconciliation</th><th>Overage</th><th>Shortage</th>';
	$html .= '</tr><tr>';
	$html .= '<td>' . $escape($ar['attendance_time'] ?? '') . '</td>';
	$html .= '<td>' . $escape('Present: ' . ($ar['attendance_present'] ?? 0) . ' | Late: ' . ($ar['attendance_late'] ?? 0) . ' | Absent: ' . ($ar['attendance_absent'] ?? 0)) . '</td>';
	$html .= '<td>' . $escape($ar['general_count'] ?? 0) . '</td>';
	$html .= '<td>' . $escape($ar['general_volume'] ?? 0) . '</td>';
	$html .= '<td>' . $escape($ar['general_commission'] ?? 0) . '</td>';
	$html .= '<td>' . $escape($ar['general_count_share'] ?? 0) . '</td>';
	$html .= '<td>' . $escape($ar['general_volume_share'] ?? 0) . '</td>';
	$html .= '<td>' . $escape($ar['general_commission_share'] ?? 0) . '</td>';
	$html .= '<td>' . $escape(
		'Balanced: ' . ($ar['reconciliation_balanced_count'] ?? 0)
			. ' | Overage: ' . ($ar['reconciliation_overage_count'] ?? 0)
			. ' | Shortage: ' . ($ar['reconciliation_shortage_count'] ?? 0)
	) . '</td>';
	$html .= '<td>' . $escape($ar['overage'] ?? 0) . '</td>';
	$html .= '<td>' . $escape($ar['shortage'] ?? 0) . '</td>';
	$html .= '</tr></table>';

	$html .= '<table>';
	$html .= '<tr>';
	$html .= '<th>Service</th><th>User/All providers</th>';
	$html .= '<th>User count</th><th>All count</th><th>Count share %</th>';
	$html .= '<th>User amount</th><th>All amount</th><th>Amount share %</th>';
	$html .= '<th>User customers</th><th>All customers</th><th>Customers share %</th>';
	$html .= '<th>User commission</th><th>All commission</th><th>Commission share %</th>';
	$html .= '</tr>';

	$servicesMap = (array)($ar['services_map'] ?? []);
	foreach (array_keys($allServices) as $k) {
		$sv = $servicesMap[$k] ?? [];
		$allSv = $allServices[$k] ?? [];
		$uCnt = (float)($sv['count'] ?? 0);
		$aCnt = (float)($allSv['count'] ?? 0);
		$uVol = (float)($sv['volume'] ?? 0);
		$aVol = (float)($allSv['volume'] ?? 0);
		$uCust = (float)($sv['customers'] ?? 0);
		$aCust = (float)($allSv['customers'] ?? 0);
		$uCom = (float)($sv['commission'] ?? 0);
		$aCom = (float)($allSv['commission'] ?? 0);

		if (!$isAllUsersReport && $uCnt <= 0 && $uVol <= 0 && $uCust <= 0 && $uCom <= 0) {
			continue;
		}

		$html .= '<tr>';
		$html .= '<td>' . $escape($allSv['label'] ?? ($sv['label'] ?? (string)$k)) . '</td>';
		$allProvidersUsed = (int)($allSv['providers_used'] ?? ($allSv['providers_total'] ?? 0));
		$html .= '<td>' . $escape((int)($sv['providers_used'] ?? 0) . '/' . $allProvidersUsed) . '</td>';
		$html .= '<td>' . $escape($uCnt) . '</td>';
		$html .= '<td>' . $escape($aCnt) . '</td>';
		$html .= '<td>' . $escape($aCnt > 0 ? round($uCnt / $aCnt * 100, 2) : 0) . '</td>';
		$html .= '<td>' . $escape($uVol) . '</td>';
		$html .= '<td>' . $escape($aVol) . '</td>';
		$html .= '<td>' . $escape($aVol > 0 ? round($uVol / $aVol * 100, 2) : 0) . '</td>';
		$html .= '<td>' . $escape($uCust) . '</td>';
		$html .= '<td>' . $escape($aCust) . '</td>';
		$html .= '<td>' . $escape($aCust > 0 ? round($uCust / $aCust * 100, 2) : 0) . '</td>';
		$html .= '<td>' . $escape($uCom) . '</td>';
		$html .= '<td>' . $escape($aCom) . '</td>';
		$html .= '<td>' . $escape($aCom > 0 ? round($uCom / $aCom * 100, 2) : 0) . '</td>';
		$html .= '</tr>';
	}

	$html .= '</table>';

	if (is_array($clearing) && !empty($clearing)) {
		$html .= '<h3>Clearing Details</h3>';
		foreach ($clearing as $svc => $svcData) {
			$html .= '<h4>' . $escape(ucwords(str_replace('_', ' ', (string)$svc))) . '</h4>';
			$printedAny = false;
			if (is_array($svcData)) {
				foreach ($svcData as $sub => $rows) {
					$rowsN = $normalizeRows($rows);
					if (empty($rowsN)) {
						continue;
					}
					$allClearing = $ar['clearing_all'] ?? [];
					$allSvcData = is_array($allClearing) ? ($allClearing[$svc] ?? []) : [];
					$allRowsN = [];
					if (is_array($allSvcData) && isset($allSvcData[$sub])) {
						$allRowsN = $normalizeRows($allSvcData[$sub]);
					}
					$printedAny = true;

					$allKeys = [];
					foreach (array_merge($rowsN, $allRowsN) as $rr) {
						if (!is_array($rr)) {
							continue;
						}
						foreach (array_keys($rr) as $k) {
							if (!in_array($k, $allKeys, true)) {
								$allKeys[] = $k;
							}
						}
					}

					$metricName = function (string $k): bool {
						return (bool)preg_match('/^(total_)?(amount|count|customers?|commission|debit|credit|volume)$/i', $k)
							|| (bool)preg_match('/(amount|count|customer|commission|debit|credit|volume)/i', $k);
					};

					$numericKeys = [];
					$keyCols = [];
					foreach ($allKeys as $k) {
						if ($metricName((string)$k)) {
							$numericKeys[] = $k;
							continue;
						}
						$isNum = false;
						foreach (array_merge($rowsN, $allRowsN) as $rr) {
							if (!is_array($rr)) {
								continue;
							}
							$v = $rr[$k] ?? null;
							if ($v === null || $v === '') {
								continue;
							}
							if (is_numeric($v)) {
								$isNum = true;
								break;
							}
						}
						if ($isNum) {
							$numericKeys[] = $k;
						} else {
							$keyCols[] = $k;
						}
					}
					if (empty($numericKeys)) {
						$numericKeys = ['total_count', 'total_amount'];
					}

					$mkIndex = function (array $rr) use ($keyCols): string {
						if (empty($keyCols)) {
							return '';
						}
						$parts = [];
						foreach ($keyCols as $k) {
							$v = $rr[$k] ?? '';
							if (is_array($v) || is_object($v)) {
								$v = '';
							}
							$parts[] = (string)$v;
						}
						return implode(' | ', $parts);
					};

					$allIndex = [];
					foreach ($allRowsN as $rr) {
						if (!is_array($rr)) {
							continue;
						}
						$allIndex[$mkIndex($rr)] = $rr;
					}
					$userIndex = [];
					foreach ($rowsN as $rr) {
						if (!is_array($rr)) {
							continue;
						}
						$userIndex[$mkIndex($rr)] = $rr;
					}
					$keysUnion = array_values(array_unique(array_merge(array_keys($allIndex), array_keys($userIndex))));
					$html .= '<div style="margin-top:6px;font-weight:bold;">' . $escape(ucwords(str_replace('_', ' ', (string)$sub))) . '</div>';
					$html .= '<table><tr>';
					if (empty($keyCols)) {
						$html .= '<th>' . $escape('Metric') . '</th>';
					} else {
						foreach ($keyCols as $k) {
							$html .= '<th>' . $escape($k) . '</th>';
						}
					}
					foreach ($numericKeys as $nk) {
						$html .= '<th>' . $escape('User ' . (string)$nk) . '</th>';
						$html .= '<th>' . $escape('All ' . (string)$nk) . '</th>';
						$html .= '<th>' . $escape('Share %') . '</th>';
					}
					$html .= '</tr>';

					foreach ($keysUnion as $idxKey) {
						$uRow = $userIndex[$idxKey] ?? [];
						$aRow = $allIndex[$idxKey] ?? [];
						$html .= '<tr>';
						if (empty($keyCols)) {
							$html .= '<td>' . $escape(ucwords(str_replace('_', ' ', (string)$sub))) . '</td>';
						} else {
							foreach ($keyCols as $k) {
								$vv = is_array($uRow) ? ($uRow[$k] ?? '') : '';
								if ($vv === '' && is_array($aRow)) {
									$vv = $aRow[$k] ?? '';
								}
								if (is_array($vv) || is_object($vv)) {
									$vv = '';
								}
								$html .= '<td>' . $escape($vv) . '</td>';
							}
						}

						foreach ($numericKeys as $nk) {
							$uVal = is_array($uRow) ? ($uRow[$nk] ?? 0) : 0;
							$aVal = is_array($aRow) ? ($aRow[$nk] ?? 0) : 0;
							$uNum = is_numeric($uVal) ? (float)$uVal : (float)preg_replace('/[^0-9.\-]/', '', (string)$uVal);
							$aNum = is_numeric($aVal) ? (float)$aVal : (float)preg_replace('/[^0-9.\-]/', '', (string)$aVal);
							$share = $aNum > 0 ? round($uNum / $aNum * 100, 2) : 0;
							$html .= '<td>' . $escape($uNum) . '</td>';
							$html .= '<td>' . $escape($aNum) . '</td>';
							$html .= '<td>' . $escape($share) . '</td>';
						}
						$html .= '</tr>';
					}
					$html .= '</table>';
				}
			}
			if (!$printedAny) {
				$html .= '<table><tr><td>' . $escape('No data available') . '</td></tr></table>';
			}
		}
	}

	if (is_array($perf) && !empty($perf)) {
		$html .= '<h3>Performance Details</h3>';
		foreach ($perf as $section => $sectionData) {
			if (!is_array($sectionData)) {
				continue;
			}
			if ((string)$section === 'myaccounts') {
				continue;
			}
			$secCount = $getSectionCount((string)$section, $sectionData);
			$skipBreakdowns = ($secCount === 0.0);
			$html .= '<h4>' . $escape(ucwords(str_replace('_', ' ', (string)$section))) . '</h4>';
			$printedAnything = false;
			$mergedRows = [];

			foreach ($sectionData as $sub => $subData) {
				if (!is_array($subData)) {
					continue;
				}
				if ((string)$section === 'attendance' && (string)$sub === 'By_Gender') {
					continue;
				}
				$isAssoc = array_keys($subData) !== range(0, count($subData) - 1);
				$subTitle = ucwords(str_replace('_', ' ', (string)$sub));
				$hasNestedValues = false;
				foreach ($subData as $vv) {
					if (is_array($vv) || is_object($vv)) {
						$hasNestedValues = true;
						break;
					}
				}

				if ($isAssoc && !$hasNestedValues) {
					$html .= '<div style="margin-top:6px;font-weight:bold;">' . $escape($subTitle) . '</div>';
					$printedAny = false;
					$allSectionData = is_array($perfAllUsers) ? ($perfAllUsers[$section] ?? []) : [];
					$allSubData = is_array($allSectionData) ? ($allSectionData[$sub] ?? []) : [];
					$html .= '<table>';
					$html .= '<tr><th style="width:35%">' . $escape('Metric') . '</th><th>' . $escape('User') . '</th><th>' . $escape('All') . '</th><th>' . $escape('Share %') . '</th></tr>';
					foreach ($subData as $k => $v) {
						if (is_array($v) || is_object($v)) {
							continue;
						}
						$printedAny = true;
						$allVal = '';
						$share = '';
						if (is_array($allSubData) && array_key_exists($k, $allSubData) && !is_array($allSubData[$k]) && !is_object($allSubData[$k])) {
							$allVal = $allSubData[$k];
							if (is_numeric($v) && is_numeric($allVal)) {
								$allNum = (float)$allVal;
								$share = $allNum > 0 ? round(((float)$v) / $allNum * 100, 2) : 0;
							}
						}
						$html .= '<tr>';
						$html .= '<th style="width:35%">' . $escape((string)$k) . '</th>';
						$html .= '<td>' . $escape($v) . '</td>';
						$html .= '<td>' . $escape($allVal) . '</td>';
						$html .= '<td>' . $escape($share) . '</td>';
						$html .= '</tr>';
					}
					if (!$printedAny) {
						$html .= '<tr><td colspan="4">' . $escape('No data available') . '</td></tr>';
					}
					$html .= '</table>';
					$printedAnything = true;
					continue;
				}

				$rowsN = $normalizeRows($subData);
				if (empty($rowsN)) {
					continue;
				}
				if ($skipBreakdowns) {
					continue;
				}
				$allSectionData = is_array($perfAllUsers) ? ($perfAllUsers[$section] ?? []) : [];
				$allSubData = is_array($allSectionData) ? ($allSectionData[$sub] ?? []) : [];
				$allRowsN = $normalizeRows($allSubData);

				if ((string)$section === 'attendance' && (string)$sub === 'Days_Worked') {
					$sum = function (array $rows, string $k): float {
						$s = 0.0;
						foreach ($rows as $rr) {
							if (!is_array($rr)) {
								continue;
							}
							$v = $rr[$k] ?? 0;
							$s += is_numeric($v) ? (float)$v : (float)preg_replace('/[^0-9.\-]/', '', (string)$v);
						}
						return $s;
					};
					$printedAnything = true;
					$rr2 = ['Category' => $subTitle];
					foreach (['present', 'late', 'absent'] as $metric) {
						$uVal = $sum($rowsN, $metric);
						$aVal = $sum($allRowsN, $metric);
						$rr2['User ' . $metric] = $uVal;
						$rr2['All ' . $metric] = $aVal;
						$rr2['Share ' . $metric . ' %'] = $aVal > 0 ? round($uVal / $aVal * 100, 2) : 0;
					}
					$mergedRows[] = $rr2;
					continue;
				}

				$printedAnything = true;
				foreach ($rowsN as $rr) {
					$rr2 = $rr;
					$rr2['Category'] = $subTitle;
					$mergedRows[] = $rr2;
				}
			}

			if (!empty($mergedRows)) {
				$printedAnything = true;
				$html .= '<div style="margin-top:6px;font-weight:bold;">' . $escape('Breakdown') . '</div>';
				$keys = ['Category'];
				foreach ($mergedRows as $rr) {
					foreach (array_keys($rr) as $k) {
						if ($k === 'Category') {
							continue;
						}
						if (!in_array($k, $keys, true)) {
							$keys[] = $k;
						}
					}
				}
				$html .= '<table><tr>';
				foreach ($keys as $k) {
					$html .= '<th>' . $escape($k) . '</th>';
				}
				$html .= '</tr>';
				foreach ($mergedRows as $rr) {
					$html .= '<tr>';
					foreach ($keys as $k) {
						$v = $rr[$k] ?? '';
						if (is_array($v) || is_object($v)) {
							$v = '';
						}
						$html .= '<td>' . $escape($v) . '</td>';
					}
					$html .= '</tr>';
				}
				$html .= '</table>';
			}

			if (!$printedAnything) {
				$html .= '<table><tr><td>' . $escape('No data available') . '</td></tr></table>';
			}
		}
	}
	$idx++;
}

$html .= '</body></html>';

while (ob_get_level()) {
	ob_end_clean();
}

$mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'mode' => 'utf-8', 'format' => 'A4']);
$mpdf->SetTitle($reportTitle);
$mpdf->WriteHTML($html);

$fileNamePrefix = $period === 'daily' ? 'performance_daily_' : ($period === 'monthly' ? 'performance_monthly_' : 'performance_custom_');
$fileName = $fileNamePrefix . date('Ymd_His') . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
$mpdf->Output($fileName, \Mpdf\Output\Destination::INLINE);
exit;

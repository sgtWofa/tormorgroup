<?php
// api/analysis-controller.php
defined('ROOT') or die("Direct script access denied");

// Accept JSON or form-encoded POST
$raw = file_get_contents('php://input');
$post = json_decode($raw, true);
if (!is_array($post)) {
    $post = $_POST;
}

// —– (1) Filters —–
$user      = $post['user']            ?? '';
$txType    = $post['transaction_type']?? '';
$category  = $post['category']        ?? '';
$startDate = $post['start_date']      ?? '';
$endDate   = $post['end_date']        ?? '';

// —– (1a) Activities filter —–
$activities = $post['activity'] ?? [];
if (!is_array($activities)) {
    $activities = $activities === '' ? [] : [$activities];
}

// Instantiate DB helper
$db = new \Core\Database;

$columnCache = [];
$getColumns = function (string $table) use ($db, &$columnCache): array {
	if (isset($columnCache[$table])) {
		return $columnCache[$table];
	}
	try {
		$rows = $db->run(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table",
			[':schema' => DB_NAME, ':table' => $table]
		);
		$cols = [];
		foreach ($rows as $r) {
			if (is_array($r) && isset($r['COLUMN_NAME'])) {
				$cols[] = strtolower((string)$r['COLUMN_NAME']);
			} elseif (is_object($r) && isset($r->COLUMN_NAME)) {
				$cols[] = strtolower((string)$r->COLUMN_NAME);
			}
		}
		$columnCache[$table] = $cols;
		return $cols;
	} catch (\Throwable $e) {
		$columnCache[$table] = [];
		return [];
	}
};

$hasColumn = function (string $table, string $col) use ($getColumns): bool {
	$cols = $getColumns($table);
	return in_array(strtolower($col), $cols, true);
};

$buildWhere = function (string $table, string $alias = '') use ($startDate, $endDate, $user, $txType, $category, $hasColumn): array {
	$prefix = $alias !== '' ? ($alias . '.') : '';
	$clauses = ['1=1'];
	$params = [];

	$dateColCandidates = ['date_created', 'created_at', 'date', 'date_added', 'created_on'];
	$userColCandidates = ['created_by', 'user_id', 'staff_id', 'admin_id'];
	$txTypeCandidates  = ['transaction_type', 'type'];
	$categoryCandidates = ['category'];

	$dateCol = '';
	foreach ($dateColCandidates as $c) {
		if ($hasColumn($table, $c)) {
			$dateCol = $c;
			break;
		}
	}
	$userCol = '';
	foreach ($userColCandidates as $c) {
		if ($hasColumn($table, $c)) {
			$userCol = $c;
			break;
		}
	}
	$txTypeCol = '';
	foreach ($txTypeCandidates as $c) {
		if ($hasColumn($table, $c)) {
			$txTypeCol = $c;
			break;
		}
	}
	$categoryCol = '';
	foreach ($categoryCandidates as $c) {
		if ($hasColumn($table, $c)) {
			$categoryCol = $c;
			break;
		}
	}

	if ($startDate && $endDate && $dateCol !== '') {
		$clauses[] = $prefix . $dateCol . " BETWEEN :start AND :end";
		$params[':start'] = "$startDate 00:00:00";
		$params[':end'] = "$endDate 23:59:59";
	}
	if ($user !== '' && $userCol !== '') {
		$clauses[] = $prefix . $userCol . " = :user";
		$params[':user'] = $user;
	}
	if ($txType !== '' && $txTypeCol !== '') {
		$clauses[] = $prefix . $txTypeCol . " = :txType";
		$params[':txType'] = $txType;
	}
	if ($category !== '' && $categoryCol !== '') {
		$clauses[] = $prefix . $categoryCol . " = :category";
		$params[':category'] = $category;
	}

	return [
		'where' => 'WHERE ' . implode(' AND ', $clauses),
		'params' => $params,
		'cols' => [
			'date' => $dateCol,
			'user' => $userCol,
			'txType' => $txTypeCol,
			'category' => $categoryCol,
		],
	];
};

// Build attendance WHERE clauses (attendance uses different column names)
$t_clauses = ["1=1"];
$t_params = [];
if ($startDate && $endDate) {
	$t_clauses[] = "t.date_created BETWEEN :start AND :end";
	$t_params[':start'] = "$startDate 00:00:00";
	$t_params[':end'] = "$endDate   23:59:59";
}
if ($user !== '') {
	$t_clauses[] = "t.user_id = :user";
	$t_params[':user'] = $user;
}
if ($txType !== '') {
	$t_clauses[] = "t.transaction_type = :txType";
	$t_params[':txType'] = $txType;
}
if ($category !== '') {
	$t_clauses[] = "t.category = :category";
	$t_params[':category'] = $category;
}
$t_where = 'WHERE ' . implode(' AND ', $t_clauses);

// Attendance late threshold (settings.late_attendance_time or work_open + 30 minutes fallback)
$workOpen = '08:00:00';
$lateAttendanceTime = '';
try {
	if ($db->table_exists(['settings'])) {
		$srow = ($db->query("SELECT work_open, late_attendance_time FROM settings ORDER BY id DESC LIMIT 1") ?: [])[0] ?? null;
		$wo = $srow ? trim((string)($srow->work_open ?? '')) : '';
		$latt = $srow ? trim((string)($srow->late_attendance_time ?? '')) : '';
		if ($wo !== '') $workOpen = $wo;
		if ($latt !== '') $lateAttendanceTime = $latt;
	}
} catch (\Throwable $e) {
	$workOpen = '08:00:00';
	$lateAttendanceTime = '';
}
$lateThreshold = '';
if ($lateAttendanceTime !== '') {
	if (strlen($lateAttendanceTime) === 5) $lateAttendanceTime .= ':00';
	$lateThreshold = $lateAttendanceTime;
} else {
	if (strlen($workOpen) === 5) $workOpen .= ':00';
	$woTs = strtotime('1970-01-01 ' . $workOpen);
	$lateThreshold = ($woTs !== false) ? date('H:i:s', $woTs + (30 * 60)) : '08:30:00';
}
$t_params[':lateThreshold'] = $lateThreshold;

// —– (2) agencybankings —–
$wAgency = $buildWhere('agencybankings');
$agencyChargeExpr = ($hasColumn('agencybankings', 'charge') ? 'SUM(CAST(charge AS DECIMAL(10,2)))'
	: ($hasColumn('agencybankings', 'charges') ? 'SUM(CAST(charges AS DECIMAL(10,2)))'
	: ($hasColumn('agencybankings', 'fee') ? 'SUM(CAST(fee AS DECIMAL(10,2)))' : '0')));
$agencyOverview  = $db->runOne(" 
    SELECT SUM(amount) AS total_amount, {$agencyChargeExpr} AS total_charge, COUNT(*) AS total_transactions
    FROM agencybankings {$wAgency['where']}
", $wAgency['params']);

$agencyByNetwork = $db->run(" 
    SELECT network, SUM(amount) AS amount, COUNT(*) AS customers
    FROM agencybankings {$wAgency['where']}
    GROUP BY network
", $wAgency['params']);

$agencyByType    = $db->run(" 
    SELECT transaction_type, SUM(amount) AS amount, COUNT(*) AS customers
    FROM agencybankings {$wAgency['where']}
    GROUP BY transaction_type
", $wAgency['params']);

// —– (3) airtimebundles —–
$wAir = $buildWhere('airtimebundles');
$airOverview  = $db->runOne(" 
    SELECT
      SUM(CAST(amount AS DECIMAL(10,2)))     AS total_amount,
      SUM(CAST(commission AS DECIMAL(10,2))) AS total_commission,
      COUNT(*)                               AS total_transactions
    FROM airtimebundles {$wAir['where']}
", $wAir['params']);

$airByNetwork      = $db->run("SELECT network, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS customers FROM airtimebundles {$wAir['where']} GROUP BY network", $wAir['params']);

$airByTransType    = $db->run("SELECT transaction_type, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS customers FROM airtimebundles {$wAir['where']} GROUP BY transaction_type", $wAir['params']);

$airByStatus       = $db->run("SELECT transaction_status AS status, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS customers FROM airtimebundles {$wAir['where']} GROUP BY transaction_status", $wAir['params']);

$airByNetTypeStat  = $db->run("SELECT network, transaction_type, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS customers FROM airtimebundles {$wAir['where']} GROUP BY network, transaction_type", $wAir['params']);

// —– (4) attendance —–
// Count PRESENT vs ABSENT per user over the date range
$attendanceDays = $db->run(" 
  SELECT 
    u.first_name,
    u.last_name,
    SUM(CASE WHEN (UPPER(t.remarks) = 'PRESENT' AND (t.time IS NULL OR t.time = '' OR TIME_TO_SEC(t.time) < TIME_TO_SEC(:lateThreshold))) THEN 1 ELSE 0 END) AS present,
    SUM(CASE WHEN (UPPER(t.remarks) = 'LATE' OR (UPPER(t.remarks) = 'PRESENT' AND t.time IS NOT NULL AND t.time <> '' AND TIME_TO_SEC(t.time) >= TIME_TO_SEC(:lateThreshold))) THEN 1 ELSE 0 END) AS late,
    SUM(CASE WHEN UPPER(t.remarks) = 'ABSENT'  THEN 1 ELSE 0 END) AS absent
  FROM attendance t
  LEFT JOIN users u ON t.user_id = u.id
  $t_where
  GROUP BY t.user_id
  ORDER BY present DESC
", $t_params);

// Keep the gender breakdown as before
$attendanceByGender = $db->run(" 
    SELECT u.gender, COUNT(*) AS count
    FROM attendance t
    JOIN users u ON t.user_id = u.id
    $t_where
    GROUP BY u.gender
", $t_params);

// —– (5) cfas —–
$wCfa = $buildWhere('cfas');
$cfaSummary = $db->runOne(" 
    SELECT SUM(amount_cfa) AS total_cfa, SUM(amount_ghs) AS total_ghs, COUNT(*) AS total_transactions
    FROM cfas {$wCfa['where']}
", $wCfa['params']);
$cfaByType  = $db->run(" 
    SELECT transaction_type, SUM(amount_cfa) AS amount_cfa, SUM(amount_ghs) AS amount_ghs, COUNT(*) AS customers
    FROM cfas {$wCfa['where']}
    GROUP BY transaction_type
", $wCfa['params']);

// —– (6) commissions —–
$wComm = $buildWhere('commissions');
$wComm['where'] .= " AND LOWER(TRIM(COALESCE(transaction_status,''))) <> 'rejected'";
$commSummary   = $db->runOne("SELECT SUM(amount) AS total_commission, COUNT(*) AS total_transactions FROM commissions {$wComm['where']}", $wComm['params']);
$commByNetwork = $db->run("SELECT network, SUM(amount) AS amount, COUNT(*) AS transactions FROM commissions {$wComm['where']} GROUP BY network", $wComm['params']);

// —– (7) deposits —–
$wDep = $buildWhere('deposits');
$depSummary   = $db->runOne("SELECT SUM(CAST(amount AS DECIMAL(10,2))) AS total_amount, COUNT(*) AS total_transactions FROM deposits {$wDep['where']}", $wDep['params']);
$depByNetwork = $db->run("SELECT network, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS customers FROM deposits {$wDep['where']} GROUP BY network", $wDep['params']);

// —– (8) ezwichs —–
$wEzw = $buildWhere('ezwichs');
$ezwChargeExpr = $hasColumn('ezwichs', 'charge') ? 'SUM(charge)' : '0';
$ezwSummary    = $db->runOne("SELECT SUM(amount) AS total_amount, {$ezwChargeExpr} AS total_charge, COUNT(*) AS total_customers FROM ezwichs {$wEzw['where']}", $wEzw['params']);
$ezwByType     = $db->run("SELECT transaction_type, transaction_status, SUM(amount) AS amount, SUM(charge) AS charges, COUNT(*) AS customers FROM ezwichs {$wEzw['where']} GROUP BY transaction_type, transaction_status", $wEzw['params']);
$ezwByStatus   = $db->run("SELECT transaction_status AS status, SUM(amount) AS amount, SUM(charge) AS charges, COUNT(*) AS customers FROM ezwichs {$wEzw['where']} GROUP BY transaction_status", $wEzw['params']);

// —– (9) momoremits —–
$wMomo = $buildWhere('momoremits');
$momoChargeExpr = ($hasColumn('momoremits', 'charge') ? 'SUM(CAST(charge AS DECIMAL(10,2)))'
	: ($hasColumn('momoremits', 'charges') ? 'SUM(CAST(charges AS DECIMAL(10,2)))'
	: ($hasColumn('momoremits', 'fee') ? 'SUM(CAST(fee AS DECIMAL(10,2)))' : '0')));
$momoSummary         = $db->runOne("SELECT SUM(CAST(amount AS DECIMAL(10,2))) AS total_amount, {$momoChargeExpr} AS total_charge, SUM(amount_payable) AS total_payable, COUNT(*) AS customers_served FROM momoremits {$wMomo['where']}", $wMomo['params']);
$momoByNetwork       = $db->run("SELECT network, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS customers FROM momoremits {$wMomo['where']} GROUP BY network", $wMomo['params']);
$momoByNetworkType   = $db->run("SELECT network, transaction_type AS type, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS customers FROM momoremits {$wMomo['where']} GROUP BY network, transaction_type", $wMomo['params']);
$momoByTransType     = $db->run("SELECT transaction_type AS type, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS customers FROM momoremits {$wMomo['where']} GROUP BY transaction_type", $wMomo['params']);

// —– (10) myaccounts —–
$wAcc = $buildWhere('myaccounts');
$accSummary = $db->runOne("SELECT SUM(debit) AS total_debit, SUM(credit) AS total_credit, COUNT(*) AS total_customers FROM myaccounts {$wAcc['where']}", $wAcc['params']);

// —– (11) payments & backoffice —–
$wPay = $buildWhere('payments');
$paySummary   = $db->runOne("SELECT SUM(amount) AS total_amount, COUNT(*) AS total_transactions FROM payments {$wPay['where']}", $wPay['params']);
$wPayBo = $buildWhere('payments_backoffice');
$payBOsummary = $db->runOne("SELECT SUM(amount) AS total_amount, COUNT(*) AS total_transactions FROM payments_backoffice {$wPayBo['where']}", $wPayBo['params']);

// —– (12) payment_services —–
$wSvc = $buildWhere('payment_services');
$svcSummary    = $db->runOne("SELECT SUM(amount) AS total_amount, SUM(charge) AS total_charge, COUNT(*) AS total_customers FROM payment_services {$wSvc['where']}", $wSvc['params']);
$svcByCategory = $db->run("SELECT category, SUM(amount) AS total_amount, SUM(charge) AS total_charge, COUNT(*) AS total_customers FROM payment_services {$wSvc['where']} GROUP BY category", $wSvc['params']);

// —– (13) requests & backoffice —–
$wReq = $buildWhere('requests');
$reqSummary        = $db->run("SELECT network AS request_option, SUM(amount) AS total_amount, COUNT(*) AS total_count, transaction_status AS status FROM requests {$wReq['where']} GROUP BY network, transaction_status", $wReq['params']);
$paymentBOSummary  = $db->run("SELECT network AS payment_type, SUM(amount) AS total_payment, COUNT(*) AS total_transactions, transaction_status AS status FROM payments_backoffice {$wPayBo['where']} GROUP BY network, transaction_status", $wPayBo['params']);

// —– (14) reversals —–
$wRev = $buildWhere('reversals');
$revSummary = $db->runOne("SELECT SUM(amount) AS total_amount, COUNT(*) AS total_transactions FROM reversals {$wRev['where']}", $wRev['params']);

// —– (15) sendings —–
$wSend = $buildWhere('sendings');
$sendSummary    = $db->runOne("SELECT SUM(CAST(amount AS DECIMAL(10,2))) AS total_amount, COUNT(*) AS total_customers FROM sendings {$wSend['where']}", $wSend['params']);
$sendByNetwork  = $db->run("SELECT network, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS customers FROM sendings {$wSend['where']} GROUP BY network", $wSend['params']);

// —– (16) sims —–
$wSims = $buildWhere('sims');
$simsSummary   = $db->runOne("SELECT COUNT(*) AS total_quantity, SUM(amount) AS total_amount, SUM(commission) AS total_commission FROM sims {$wSims['where']}", $wSims['params']);
$simsByNetwork = $db->run("SELECT network, COUNT(*) AS qty, SUM(amount) AS total_amount, SUM(commission) AS total_commission FROM sims {$wSims['where']} GROUP BY network", $wSims['params']);
$simsByStatus  = $db->run("SELECT transaction_status AS sim_status, COUNT(*) AS quantity, SUM(amount) AS total_amount, SUM(commission) AS total_commission FROM sims {$wSims['where']} GROUP BY transaction_status", $wSims['params']);

// —– (17) tickets —–
$wTickets = $buildWhere('tickets');
$ticketsSummary = $db->run("SELECT status, COUNT(*) AS count FROM tickets {$wTickets['where']} GROUP BY status", $wTickets['params']);

// —– (18) cashpickups —–
$cpSummary = [];
try {
	if ($db->table_exists(['cashpickups'])) {
		$wCp = $buildWhere('cashpickups');
		$cpWhere = $wCp['where'];
		$cpParams = $wCp['params'];
		if ($hasColumn('cashpickups', 'deleted')) {
			$cpWhere .= " AND (deleted IS NULL OR deleted = 0 OR deleted = '0')";
		}
		$cpAmountCol = '';
		foreach (['amount_involved', 'total_amount', 'amount', 'cash_amount', 'value'] as $c) {
			if ($hasColumn('cashpickups', $c)) {
				$cpAmountCol = $c;
				break;
			}
		}
		$cpChargeExpr = ($hasColumn('cashpickups', 'pickup_charge') ? 'SUM(CAST(pickup_charge AS DECIMAL(10,2)))'
			: ($hasColumn('cashpickups', 'charge') ? 'SUM(CAST(charge AS DECIMAL(10,2)))'
			: ($hasColumn('cashpickups', 'charges') ? 'SUM(CAST(charges AS DECIMAL(10,2)))'
			: ($hasColumn('cashpickups', 'fee') ? 'SUM(CAST(fee AS DECIMAL(10,2)))'
			: ($hasColumn('cashpickups', 'total_charge') ? 'SUM(CAST(total_charge AS DECIMAL(10,2)))' : '0')))));
		$sumExpr = $cpAmountCol !== '' ? "SUM(CAST({$cpAmountCol} AS DECIMAL(10,2)))" : '0';
		$cpSummary = $db->runOne("SELECT {$sumExpr} AS total_amount, {$cpChargeExpr} AS total_charge, COUNT(*) AS total_transactions FROM cashpickups {$cpWhere}", $cpParams);
	}
} catch (\Throwable $e) {
	$cpSummary = [];
}

// —– (19) error_reports —–
$erSummary = [];
try {
	if ($db->table_exists(['error_reports'])) {
		$wEr = $buildWhere('error_reports');
		$statusCol = '';
		foreach (['status', 'state'] as $c) {
			if ($hasColumn('error_reports', $c)) {
				$statusCol = $c;
				break;
			}
		}
		if ($statusCol !== '') {
			$erSummary = $db->runOne("
				SELECT
					SUM(CASE WHEN UPPER({$statusCol}) IN ('OPEN','PENDING','NEW') THEN 1 ELSE 0 END) AS open_cnt,
					SUM(CASE WHEN UPPER({$statusCol}) IN ('CLOSED','RESOLVED','DONE') THEN 1 ELSE 0 END) AS closed_cnt,
					COUNT(*) AS total_cnt
				FROM error_reports {$wEr['where']}
			", $wEr['params']);
		} else {
			$erSummary = $db->runOne("SELECT COUNT(*) AS total_cnt FROM error_reports {$wEr['where']}", $wEr['params']);
			$erSummary['open_cnt'] = 0;
			$erSummary['closed_cnt'] = 0;
		}
	}
} catch (\Throwable $e) {
	$erSummary = [];
}

// —– (19) withdrawals —–
$wWithd = $buildWhere('withdrawals');
$withdOverview  = $db->runOne("SELECT SUM(CAST(amount AS DECIMAL(10,2))) AS total_amount, COUNT(*) AS total_transactions FROM withdrawals {$wWithd['where']}", $wWithd['params']);
$withdByNetwork = $db->run("SELECT network, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS transactions FROM withdrawals {$wWithd['where']} GROUP BY network", $wWithd['params']);

// —– (21) NEW KPI CALCULATIONS —–

// 21c) Failure Rate (%) in requests
$reqStats = $db->runOne("
    SELECT 
      SUM(CASE WHEN transaction_status != 'SUCCESS' THEN 1 ELSE 0 END) AS failures,
      COUNT(*) AS total
    FROM requests
    {$wReq['where']}
", $wReq['params']);
$failureRate = 0;
if (!empty($reqStats['total'])) {
    $failureRate = round($reqStats['failures'] / $reqStats['total'] * 100, 1);
}

// 21d) Average Ticket Resolution Time (hours)
$ticketStats = $db->runOne("
    SELECT 
      AVG(TIMESTAMPDIFF(HOUR, date_created, date_updated)) AS avg_resolution_hours
    FROM tickets
    {$wTickets['where']}
    AND date_updated IS NOT NULL
", $wTickets['params']);
$avgTicketTime = round($ticketStats['avg_resolution_hours'] ?? 0, 1);

// 21e) Deposit ↔ Withdrawal Ratio
$depSum = $depSummary['total_amount'] ?? 0;
$withdSum = $withdOverview['total_amount'] ?? 0;
$depWithRatio = $withdSum > 0 ? round($depSum / $withdSum, 2) : 0;

// 21g) Network Coverage (distinct networks across key tables)
$netParams = array_merge($wAgency['params'], $wSend['params'], $wMomo['params']);
$netCovRow = $db->runOne("
    SELECT COUNT(DISTINCT network) AS coverage FROM (
      SELECT network FROM agencybankings {$wAgency['where']}
      UNION
      SELECT network FROM sendings    {$wSend['where']}
      UNION
      SELECT network FROM momoremits  {$wMomo['where']}
    ) AS combined
", $netParams);
$networkCoverage = $netCovRow['coverage'] ?? 0;

// 21i) Attendance Penetration (%) — % of users who marked today
$today = date('Y-m-d');
$markedToday = $db->runOne("
    SELECT COUNT(DISTINCT user_id) AS marked
    FROM attendance
    WHERE date = :today
", ['today' => $today])['marked'] ?? 0;
$totalUsers = $db->runOne("SELECT COUNT(*) AS cnt FROM users WHERE deleted = 0")['cnt'] ?? 0;
$attendancePenetr = $totalUsers > 0 
    ? round($markedToday / $totalUsers * 100, 1) 
    : 0;
// 21k) Injection into output
$output['requests']['FailureRate']         = $failureRate;
$output['tickets']['AvgResolutionTime']    = $avgTicketTime;
$output['depositWithdrawalRatio']          = $depWithRatio;
$output['networkCoverage']                 = $networkCoverage;
$output['attendance']['Penetration']       = $attendancePenetr;

// —– (20) Combine & output —–
$output = [
  'agencybankings'   => ['Overview'=>$agencyOverview, 'By_Network'=>$agencyByNetwork, 'By_Type'=>$agencyByType],
  'airtimebundles'   => ['Overview'=>$airOverview, 'By_Network'=>$airByNetwork, 'By_Type'=>$airByTransType, 'By_Status'=>$airByStatus, 'By_Network_Type'=>$airByNetTypeStat],
  'attendance'       => ['Days_Worked'=>$attendanceDays, 'By_Gender'=>$attendanceByGender],
  'cashpickups'      => ['Summary'=>$cpSummary],
  'cfas'             => ['Summary'=>$cfaSummary, 'By_Type'=>$cfaByType],
  'commissions'      => ['Summary'=>$commSummary, 'By_Network'=>$commByNetwork],
  'deposits'         => ['Overview'=>$depSummary, 'By_Network'=>$depByNetwork],
  'error_reports'    => ['Summary'=>$erSummary],
  'ezwichs'          => ['Overview'=>$ezwSummary, 'By_Type'=>$ezwByType, 'By_Status'=>$ezwByStatus],
  'momoremits'       => ['Overview'=>$momoSummary, 'By_Network'=>$momoByNetwork, 'By_Type'=>$momoByTransType, 'By_Network_Type'=>$momoByNetworkType],
  'myaccounts'       => ['Summary'=>$accSummary],
  'payments'         => ['Summary'=>$paySummary],
  'payment_services' => ['Summary'=>$svcSummary, 'By_Category'=>$svcByCategory],
  'requests'         => ['Requests'=>$reqSummary, 'Backoffice_Payments'=>$paymentBOSummary],
  'reversals'        => ['Summary'=>$revSummary],
  'sendings'         => ['Overview'=>$sendSummary, 'By_Network'=>$sendByNetwork],
  'sims'             => ['Summary'=>$simsSummary, 'By_Network'=>$simsByNetwork, 'By_Status'=>$simsByStatus],
  'tickets'          => ['By_Status'=>$ticketsSummary],
  'withdrawals'      => ['Overview'=>$withdOverview, 'By_Network'=>$withdByNetwork],
];

// Now filter $output by $activities as before
if (!empty($activities)) {
    $filtered = [];
    foreach ($activities as $act) {
        if (isset($output[$act])) {
            $filtered[$act] = $output[$act];
        }
    }
    if (!empty($filtered)) {
        $output = $filtered;
    }
}


if (defined('PERF_EMBEDDED_MODE') && PERF_EMBEDDED_MODE) {
	return $output;
}

performance_json_response($output);
exit;

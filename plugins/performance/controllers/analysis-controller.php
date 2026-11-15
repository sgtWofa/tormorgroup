<?php
// api/analysis.php
defined('ROOT') or die("Direct script access denied");

// —– (1) Filters —–
$postdata   = $req->post();
$__perf_post = is_array($postdata) ? $postdata : [];
$_POST = $__perf_post;
require plugin_path('controllers/performance-controller.php');
exit;
$user       = $postdata['user']             ?? '';
$txType     = $postdata['transaction_type'] ?? '';
$category   = $postdata['category']         ?? '';
$startDate  = $postdata['start_date']       ?? '';
$endDate    = $postdata['end_date']         ?? '';

// <<< NEW: read activity as array, allow single string or missing
$activities = $postdata['activity'] ?? [];
if (!is_array($activities)) {
    $activities = $activities === '' ? [] : [ $activities ];
}

$clauses = ["1=1"];
$t_clauses = ["1=1"];
$params  = [];

if ($startDate && $endDate) {
    $clauses[]        = "date_created BETWEEN :start AND :end";
    $t_clauses[]        = "t.date_created BETWEEN :start AND :end";
    $params[':start'] = $startDate . ' 00:00:00';
    $params[':end']   = $endDate   . ' 23:59:59';
}
if ($user !== '') {
    $clauses[]       = "created_by = :user";
    $t_clauses[]       = "t.user_id = :user";
    $params[':user'] = $user;
}
if ($txType !== '') {
    $clauses[]         = "transaction_type = :txType";
    $t_clauses[]         = "t.transaction_type = :txType";
    $params[':txType'] = $txType;
}
if ($category !== '') {
    $clauses[]           = "category = :category";
    $t_clauses[]           = "t.category = :category";
    $params[':category'] = $category;
}

$where = 'WHERE ' . implode(' AND ', $clauses);
$t_where = 'WHERE ' . implode(' AND ', $t_clauses);

// Instantiate your DB helper
$db = new \Core\Database;

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
$params[':lateThreshold'] = $lateThreshold;

// Optional tables for extended activity tracking
$hasCashpickups = false;
$hasReconciliations = false;
$hasErrorReports = false;
try{
	$hasCashpickups = $db->table_exists(['cashpickups']);
}catch(\Throwable $e){
	$hasCashpickups = false;
}
try{
	$hasReconciliations = $db->table_exists(['reconciliations']);
}catch(\Throwable $e){
	$hasReconciliations = false;
}
try{
	$hasErrorReports = $db->table_exists(['error_reports']);
}catch(\Throwable $e){
	$hasErrorReports = false;
}

// —– (2) agencybankings —–
$agencyOverview   = $db->runOne("
    SELECT SUM(amount) AS total_amount, COUNT(*) AS total_transactions
    FROM agencybankings
    $where
", $params);

$agencyByNetwork = $db->run("
    SELECT network, SUM(amount) AS amount, COUNT(*) AS customers
    FROM agencybankings
    $where
    GROUP BY network
", $params);

$agencyTransactionType = $db->run("
    SELECT transaction_type, SUM(amount) AS amount, COUNT(*) AS customers
    FROM agencybankings
    $where
    GROUP BY transaction_type
", $params);

// —– (3) airtimebundles —–
$airOverview = $db->runOne("
    SELECT
      SUM(CAST(amount AS DECIMAL(10,2)))     AS total_amount,
      SUM(CAST(commission AS DECIMAL(10,2))) AS total_commission,
      COUNT(*)                               AS total_transactions
    FROM airtimebundles
    $where
", $params);

$airByNetwork = $db->run("
    SELECT network, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS customers
    FROM airtimebundles
    $where
    GROUP BY network
", $params);

$airByTransType = $db->run("
    SELECT transaction_type, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS customers
    FROM airtimebundles
    $where
    GROUP BY transaction_type
", $params);

$airByStatus = $db->run("
    SELECT transaction_status AS status, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS customers
    FROM airtimebundles
    $where
    GROUP BY transaction_status
", $params);

$airByNetworkTransTypeStatus = $db->run("
    SELECT network, transaction_type, transaction_status AS status, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS customers
    FROM airtimebundles
    $where
    GROUP BY network, transaction_type, transaction_status
", $params);

// —– (4) attendance —–
$attendanceDays = $db->run("
    SELECT CONCAT(u.first_name, ' ', u.last_name) AS full_name, 
    SUM(CASE WHEN (UPPER(t.remarks) = 'PRESENT' AND (t.time IS NULL OR t.time = '' OR TIME_TO_SEC(t.time) < TIME_TO_SEC(:lateThreshold))) THEN 1 ELSE 0 END) AS days_present,
    SUM(CASE WHEN (UPPER(t.remarks) = 'LATE' OR (UPPER(t.remarks) = 'PRESENT' AND t.time IS NOT NULL AND t.time <> '' AND TIME_TO_SEC(t.time) >= TIME_TO_SEC(:lateThreshold))) THEN 1 ELSE 0 END) AS days_late,
    SUM(CASE WHEN UPPER(t.remarks) = 'ABSENT'  THEN 1 ELSE 0 END) AS days_absent,
    COUNT(DISTINCT t.date) AS days_marked
    FROM attendance AS t LEFT JOIN users AS u ON t.user_id = u.id
    $t_where
    GROUP BY t.user_id, u.first_name, u.last_name
", $params);

// —– (5) cfas —–
$cfaSummary = $db->runOne("
    SELECT
      SUM(amount_cfa) AS total_cfa,
      SUM(amount_ghs) AS total_ghs,
      COUNT(*)        AS total_transactions
    FROM cfas
    $where
", $params);

$cfaByType = $db->run("
    SELECT transaction_type, SUM(amount_cfa) AS amount_cfa, SUM(amount_ghs) AS amount_ghs, COUNT(*) AS customers
    FROM cfas
    $where
    GROUP BY transaction_type
", $params);

// —– (6) commissions —–
$commissionWhere = $where . " AND (transaction_status IS NULL OR LOWER(TRIM(transaction_status)) <> 'rejected')";
$commSummary   = $db->runOne("
    SELECT SUM(amount) AS total_commission, COUNT(*) AS total_transactions
    FROM commissions
    $commissionWhere
", $params);

$commByNetwork = $db->run("
    SELECT network, SUM(amount) AS amount, COUNT(*) AS transactions
    FROM commissions
    $commissionWhere
    GROUP BY network
", $params);

// —– (7) denos —–
$denosSummary = $db->runOne("
    SELECT SUM(volume) AS total_volume, SUM(count) AS total_notes
    FROM denos
    $where
", $params);

$denosByDenom = $db->run("
    SELECT denomination, SUM(count) AS count, SUM(volume) AS volume
    FROM denos
    $where
    GROUP BY denomination
", $params);

// —– (8) deposits —–
$depSummary   = $db->runOne("
    SELECT SUM(CAST(amount AS DECIMAL(10,2))) AS total_amount, COUNT(*) AS total_transactions
    FROM deposits
    $where
", $params);

$depByNetwork = $db->run("
    SELECT network, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS customers
    FROM deposits
    $where
    GROUP BY network
", $params);

// —– (9) ezwichs —–
$ezwSummary = $db->runOne("
    SELECT
      SUM(amount)         AS total_amount,
      COUNT(*)            AS total_customers
    FROM ezwichs
    $where
", $params);

$ezwByType = $db->run("
    SELECT transaction_type, transaction_status, SUM(amount) AS amount,  sum(charge) AS charges, COUNT(*) AS customers
    FROM ezwichs
    $where
    GROUP BY transaction_type, transaction_status
", $params);

$ezwByStatus = $db->run("
    SELECT transaction_type, transaction_status, SUM(amount) AS amount,  sum(charge) AS charges, COUNT(*) AS customers
    FROM ezwichs
    $where
    GROUP BY transaction_status
", $params);

// —– (10) momoremits —–
$momoSummary = $db->runOne("
    SELECT
      SUM(CAST(amount AS DECIMAL(10,2))) AS total_amount,
      SUM(amount_payable)               AS total_payable,
      COUNT(*)                          AS customers_served
    FROM momoremits
    $where
", $params);

$momoByNetwork = $db->run("
    SELECT network, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS customers
    FROM momoremits
    $where
    GROUP BY network
", $params);

$momoByNetworkType = $db->run("
    SELECT network, transaction_type AS type, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS customers
    FROM momoremits
    $where
    GROUP BY network, transaction_type
", $params);

$momoByTransactionType = $db->run("
    SELECT transaction_type AS type, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS customers
    FROM momoremits
    $where
    GROUP BY transaction_type
", $params);

// —– (11) myaccounts —–
$accSummary = $db->runOne("
    SELECT SUM(debit) AS total_debit, SUM(credit) AS total_credit, COUNT(*) AS total_customers
    FROM myaccounts
    $where
", $params);

// —– (12) payments & payments_backoffice —–
$paySummary   = $db->runOne("
    SELECT SUM(amount) AS total_amount, COUNT(*) AS total_transactions
    FROM payments
    $where
", $params);

$payBOsummary = $db->runOne("
    SELECT SUM(amount) AS total_amount, COUNT(*) AS total_transactions
    FROM payments_backoffice
    $where
", $params);

// —– (13) payment_services —–
$svcSummary = $db->runOne("
    SELECT SUM(amount) AS total_amount, SUM(charge) AS total_charge, COUNT(*) AS total_customers
    FROM payment_services
    $where
", $params);

$svcByCategory = $db->run("
    SELECT category, SUM(amount) AS total_amount, SUM(charge) AS total_charge, COUNT(*) AS total_customers
    FROM payment_services
    $where
    GROUP BY category
", $params);

// —– (14) requests & payments_backoffice —–
$reqSummary   = $db->run("
    SELECT network AS request_option, SUM(amount) AS total_amount, COUNT(*) AS total_count, transaction_status AS status
    FROM requests
    $where
    GROUP BY network, transaction_status
", $params);

$paymentBOSummary = $db->run("
    SELECT network AS payment_type, SUM(amount) AS total_payment, COUNT(*) AS total_transactions, transaction_status AS status
    FROM payments_backoffice
    $where
    GROUP BY network, transaction_status
", $params);

// —– (13b) cashpickups —–
$cashpickupSummary = [];
$cashpickupByStatus = [];
if($hasCashpickups){
	try{
		$cashpickupSummary = $db->runOne("
			SELECT
				SUM(CAST(amount_involved AS DECIMAL(10,2))) AS total_amount,
				COUNT(*) AS total_transactions
			FROM cashpickups
			$where
		", $params);
		$cashpickupByStatus = $db->run("
			SELECT transaction_status AS status, COUNT(*) AS total_count, SUM(CAST(amount_involved AS DECIMAL(10,2))) AS total_amount
			FROM cashpickups
			$where
			GROUP BY transaction_status
		", $params);
	}catch(\Throwable $e){
		$cashpickupSummary = [];
		$cashpickupByStatus = [];
	}
}

// —– (13c) reconciliations —–
$reconSummary = [];
$reconByRemarks = [];
if($hasReconciliations){
	try{
		$reconSummary = $db->runOne("
			SELECT
				COUNT(*) AS total_reconciliations,
				SUM(CAST(expected_balance AS DECIMAL(10,2))) AS expected_total,
				SUM(CAST(balance AS DECIMAL(10,2))) AS actual_total,
				SUM(CAST(variance AS DECIMAL(10,2))) AS variance_total
			FROM reconciliations
			$where
		", $params);
		$reconByRemarks = $db->run("
			SELECT remarks, COUNT(*) AS total_count, SUM(CAST(variance AS DECIMAL(10,2))) AS variance_total
			FROM reconciliations
			$where
			GROUP BY remarks
		", $params);
	}catch(\Throwable $e){
		$reconSummary = [];
		$reconByRemarks = [];
	}
}

// —– (13d) error_reports —–
$errorReportsSummary = [];
$errorReportsBySeverity = [];
if($hasErrorReports){
	try{
		$erClauses = ["deleted = 0"]; // error_reports uses user_id instead of created_by
		$erParams = [];
		if($startDate && $endDate){
			$erClauses[] = "date_created BETWEEN :start AND :end";
			$erParams[':start'] = $startDate . ' 00:00:00';
			$erParams[':end'] = $endDate . ' 23:59:59';
		}
		if($user !== ''){
			$erClauses[] = "user_id = :er_user";
			$erParams[':er_user'] = $user;
		}
		if($category !== ''){
			// not applicable; ignore
		}
		if($txType !== ''){
			// not applicable; ignore
		}
		$erWhere = 'WHERE ' . implode(' AND ', $erClauses);

		$errorReportsSummary = $db->runOne("
			SELECT
				COUNT(*) AS total_reports,
				SUM(CASE WHEN UPPER(COALESCE(status,'OPEN')) = 'OPEN' THEN 1 ELSE 0 END) AS open_cnt,
				SUM(CASE WHEN UPPER(COALESCE(status,'')) = 'RESOLVED' THEN 1 ELSE 0 END) AS resolved_cnt,
				SUM(CASE WHEN UPPER(COALESCE(status,'OPEN')) = 'OPEN' AND UPPER(COALESCE(severity,'')) = 'FATAL' THEN 1 ELSE 0 END) AS fatal_open_cnt
			FROM error_reports
			$erWhere
		", $erParams);
		$errorReportsBySeverity = $db->run("
			SELECT severity, COUNT(*) AS total_count
			FROM error_reports
			$erWhere
			GROUP BY severity
		", $erParams);
	}catch(\Throwable $e){
		$errorReportsSummary = [];
		$errorReportsBySeverity = [];
	}
}

// —– (15) reversals —–
$revSummary = $db->runOne("
    SELECT SUM(amount) AS total_amount, COUNT(*) AS total_transactions
    FROM reversals
    $where
", $params);

// —– (16) sendings —–
$sendSummary   = $db->runOne("
    SELECT SUM(CAST(amount AS DECIMAL(10,2))) AS total_amount, COUNT(*) AS total_customers
    FROM sendings
    $where
", $params);

$sendByNetwork = $db->run("
    SELECT network, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS customers
    FROM sendings
    $where
    GROUP BY network
", $params);

// —– (17) sims —–
$simsSummary = $db->runOne("
    SELECT COUNT(*) AS total_quantity,  SUM(amount) AS total_amount, SUM(commission) AS total_commission
    FROM sims
    $where
", $params);

$simsByNetwork = $db->run("
    SELECT network, COUNT(*) AS qty, SUM(amount) AS total_amount, SUM(commission) AS total_commission
    FROM sims
    $where
    GROUP BY network
", $params);

$simsByStatus = $db->run("
    SELECT network, transaction_status AS sim_status, COUNT(*) AS quantity, SUM(amount) AS total_amount, SUM(commission) AS total_commission
    FROM sims
    $where
    GROUP BY transaction_status
", $params);

// —– (18) tickets —–
$ticketsSummary = $db->run("
    SELECT status, COUNT(*) AS count
    FROM tickets
    $where
    GROUP BY status
", $params);

/*// —– (19) transactions (preferred debit/credit + per‑service) —–
$transOverview = $db->runOne("
    SELECT
      SUM(CASE WHEN transaction_type='debit'  THEN amount ELSE 0 END) AS total_debit,
      SUM(CASE WHEN transaction_type='credit' THEN amount ELSE 0 END) AS total_credit,
      COUNT(CASE WHEN transaction_type='debit'  THEN 1 ELSE NULL END)  AS debit_count
      COUNT(CASE WHEN transaction_type='credit'  THEN 1 ELSE NULL END)  AS credit_count
    FROM transactions
    $where
", $params);

$transByService = $db->run("
    SELECT
      service,
      SUM(amount) AS amount,
      COUNT(*)     AS transactions
    FROM transactions
    $where
      AND transaction_type = 'debit'
    GROUP BY transaction_type
", $params);*/

// —– (19) transactions —–

// 1) Overview: sum up actual debits & credits, count each
$transOverview = $db->runOne("
    SELECT
      SUM(debit)  AS total_debit,
      SUM(credit) AS total_credit,
      COUNT(*)    AS transaction_count
    FROM transactions
    $where
", $params);

// 2) Breakdown by transaction_type
$transByType = $db->run("
    SELECT
      transaction_type,
      SUM(amount)  AS total,
      COUNT(*)    AS count
    FROM transactions
    $where
    GROUP BY transaction_type
", $params);


// —– (20) withdrawals —–
$withdOverview = $db->runOne("
    SELECT SUM(CAST(amount AS DECIMAL(10,2))) AS total_amount, COUNT(*) AS total_transactions
    FROM withdrawals
    $where
", $params);

$withdByNetwork = $db->run("
    SELECT network, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS transactions
    FROM withdrawals
    $where
    GROUP BY network
", $params);

// —– (21) Combine & output —–
$output = [
  'agencybankings'   => ['Overview'=>$agencyOverview,   'By_Network'=>$agencyByNetwork,   'By_Transaction_Type'=>$agencyTransactionType],
  'airtimebundles'   => ['Overview'=>$airOverview,      'By_Network'=>$airByNetwork,      'By_Type'=>$airByTransType,      'By_Status'=>$airByStatus,      'By_Network,_Type_&_Status'=>$airByNetworkTransTypeStatus],
  'attendance'       => ['Days_Worked'=>$attendanceDays],
  'cfas'             => ['Summary'=>$cfaSummary,        'By_Type'=>$cfaByType],
  'commissions'      => ['Summary'=>$commSummary,       'By_Network'=>$commByNetwork],
 // 'denos'            => ['Summary'=>$denosSummary,      'By_Denomination'=>$denosByDenom],
  'deposits'         => ['Overview'=>$depSummary,        'By_Network'=>$depByNetwork],
  'ezwichs'          => ['Overview'=>$ezwSummary,        'By_Type'=>$ezwByType,        'By_Status'=>$ezwByStatus],
  'momoremits'       => ['Overview'=>$momoSummary,       'By_Type'=>$momoByTransactionType,     'By_Network'=>$momoByNetwork,       'By_Network_and_Type'=>$momoByNetworkType],
  'myaccounts'       => ['Summary'=>$accSummary],
  'payments'         => ['Summary'=>$paySummary,       'Backoffice'=>$payBOsummary],
  'payment_services' => ['Summary'=>$svcSummary,   'By_Category'=>$svcByCategory],
  'requests'         => ['Requests'=>$reqSummary,       'Backoffice_Payments'=>$paymentBOSummary],
  'cashpickups'      => ['Summary'=>$cashpickupSummary, 'By_Status'=>$cashpickupByStatus],
  'reconciliations'  => ['Summary'=>$reconSummary, 'By_Remarks'=>$reconByRemarks],
  'error_reports'    => ['Summary'=>$errorReportsSummary, 'By_Severity'=>$errorReportsBySeverity],
  'reversals'        => ['Summary'=>$revSummary],
  'sendings'         => ['Overview'=>$sendSummary,      'By_Network'=>$sendByNetwork],
  'sims'             => ['Summary'=>$simsSummary,       'By_Network'=>$simsByNetwork,    'By_Status'=>$simsByStatus],
  'tickets'          => ['By_Status'=>$ticketsSummary],
  'transactions'     => ['Overview'=>$transOverview,    'By_Type'=>$transByType],
  'withdrawals'      => ['Overview'=>$withdOverview,    'By_network'=>$withdByNetwork],
];

// If an activity is selected, filter to only that key:
// <<< NEW: filter $output by selected activities if any
if (!empty($activities)) {
    $filtered = [];
    foreach ($activities as $act) {
        if (isset($output[$act])) {
            $filtered[$act] = $output[$act];
        }
    }
    // only overwrite if we found matching keys
    if (!empty($filtered)) {
        $output = $filtered;
    }
}

/*if ($activity && isset($output[$activity])) {
    $output = [ $activity => $output[$activity] ];
}*/

performance_json_response($output);

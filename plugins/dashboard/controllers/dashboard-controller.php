<?php
// api/analysis-controller.php
defined('ROOT') or die("Direct script access denied");

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// Accept JSON or form-encoded POST
$raw = file_get_contents('php://input');
$post = json_decode($raw, true);
if (!is_array($post)) {
    $post = $_POST;
}

// —– (1) Filters —–
$txType    = $post['transaction_type']?? '';
$category  = $post['category']        ?? '';
$startDate = $post['start_date']      ?? '';
$endDate   = $post['end_date']        ?? '';
$user      = $post['user']            ?? '';
$users     = $post['users']          ?? [];
if (!is_array($users)) {
    $users = $users === '' ? [] : [$users];
}
$users = array_values(array_filter(array_map('trim', $users)));

// Keep the date filters optional so the dashboard shows data when no range is chosen.
// This avoids false zeroes for historical datasets that do not contain today’s records.

// —– (1a) Activities filter —–
$activities = $post['activity'] ?? [];
if (!is_array($activities)) {
    $activities = $activities === '' ? [] : [$activities];
}

$session = get_session();

$isPrivilegedDashboard = false;
try {
    $isPrivilegedDashboard = (user_can('is_manager') || user_can('is_admin'));
} catch (\Throwable $e) {
    $isPrivilegedDashboard = false;
}

if(!empty($session->id)){
    $username = $session->first_name . ' '. $session->last_name;
    $user_id = $session->id;
}

if (!$isPrivilegedDashboard) {
    $user = $user_id;
}

// Build WHERE clauses
$clauses   = ["1=1"];
$t_clauses = ["1=1"];
$like_clauses = ["1=1"];
$params    = [];

if ($startDate !== '' || $endDate !== '') {
    if ($startDate !== '' && $endDate !== '') {
        $clauses[]        = "date_created BETWEEN :start AND :end";
        $t_clauses[]      = "t.date_created BETWEEN :start AND :end";
        $like_clauses[]   = "date_created BETWEEN :start AND :end";
        $params[':start'] = "$startDate 00:00:00";
        $params[':end']   = "$endDate 23:59:59";
    } elseif ($startDate !== '') {
        $clauses[]        = "date_created >= :start";
        $t_clauses[]      = "t.date_created >= :start";
        $like_clauses[]   = "date_created >= :start";
        $params[':start'] = "$startDate 00:00:00";
    } else {
        $clauses[]        = "date_created <= :end";
        $t_clauses[]      = "t.date_created <= :end";
        $like_clauses[]   = "date_created <= :end";
        $params[':end']   = "$endDate 23:59:59";
    }
}
if ($user !== '') {
    $clauses[]        = "created_by = :user";
    $t_clauses[]      = "t.user_id = :user";
    $like_clauses[]      = "created_by = :user";
    $params[':user']  = $user;
}
if (!empty($users)) {
    $userPlaceholders = [];
    foreach ($users as $index => $uid) {
        $placeholder = ':user_' . $index;
        $userPlaceholders[] = $placeholder;
        $params[$placeholder] = (int)$uid;
    }
    $userSql = 'created_by IN (' . implode(', ', $userPlaceholders) . ')';
    $clauses[] = $userSql;
    $t_clauses[] = 't.user_id IN (' . implode(', ', $userPlaceholders) . ')';
    $like_clauses[] = $userSql;
}
if ($txType !== '') {
    $clauses[]        = "transaction_type = :txType";
    $like_clauses[]        = "transaction_type LIKE :txType";
    $t_clauses[]      = "t.transaction_type = :txType";
    $params[':txType']= $txType;
}
if ($category !== '') {
    $clauses[]           = "category = :category";
    $t_clauses[]         = "t.category = :category";
    $params[':category'] = $category;
}

$where   = 'WHERE ' . implode(' AND ', $clauses);
$t_where = 'WHERE ' . implode(' AND ', $t_clauses);
$like_where = 'WHERE ' . implode(' AND ', $like_clauses);

// Instantiate DB helper
$db = new \Core\Database;

$hasStatusColumn = function ($table, $column) use ($db) {
    static $columnCache = [];
    $tableKey = strtolower((string)$table);
    if (!array_key_exists($tableKey, $columnCache)) {
        $rows = $db->run(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table
               AND COLUMN_NAME IN ('transaction_status','status','approval_status')",
            [':db' => DB_NAME, ':table' => $table]
        );
        if ($db->has_error) {
            throw new \RuntimeException('Unable to verify dashboard status columns for ' . $table . ': ' . $db->error);
        }

        $columnCache[$tableKey] = [];
        foreach ($rows as $row) {
            $columnName = is_array($row) ? ($row['COLUMN_NAME'] ?? '') : ($row->COLUMN_NAME ?? '');
            $columnCache[$tableKey][strtolower((string)$columnName)] = true;
        }
    }
    return !empty($columnCache[$tableKey][strtolower((string)$column)]);
};

$hasTransactionStatusColumn = function ($table) use ($hasStatusColumn) {
    return $hasStatusColumn($table, 'transaction_status');
};

$approvedOnlyWhere = function ($table, $whereClause) use ($hasStatusColumn) {
    $excludedStatuses = "'rejected','reject','declined','decline','denied','reversed','cancelled','canceled'";
    $conditions = [];
    foreach (['transaction_status', 'status', 'approval_status'] as $statusColumn) {
        if ($hasStatusColumn($table, $statusColumn)) {
            $normalizedStatus = "LOWER(TRIM($statusColumn))";
            $conditions[] = "($statusColumn IS NULL OR ($normalizedStatus NOT IN ($excludedStatuses) AND $normalizedStatus NOT LIKE '%reject%' AND $normalizedStatus NOT LIKE '%declin%'))";
        }
    }
    return empty($conditions) ? $whereClause : $whereClause . ' AND ' . implode(' AND ', $conditions);
};

$normalizeTableFilterForAgentServices = function ($whereClause, $params) {
    $normalizedWhere = preg_replace('/\bcreated_by\b/i', 'creator_id', (string)$whereClause);
    $normalizedWhere = preg_replace('/\buser_id\b/i', 'creator_id', $normalizedWhere);
    $normalizedWhere = preg_replace('/\btransaction_type\b/i', 'service_type', $normalizedWhere);
    $normalizedWhere = preg_replace('/\bcategory\b/i', 'service_type', $normalizedWhere);

    $normalizedParams = [];
    foreach ((array)$params as $key => $value) {
        $normalizedParams[$key] = $value;
    }

    return [$normalizedWhere, $normalizedParams];
};

$normalizePhone = function ($value) {
    if ($value === null) {
        return null;
    }

    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $digits = preg_replace('/\D+/', '', $value);
    if ($digits === '') {
        return null;
    }

    if (strlen($digits) > 10 && strpos($digits, '233') === 0) {
        return '+' . $digits;
    }

    if (strlen($digits) === 10) {
        return '+233' . substr($digits, 1);
    }

    if (strlen($digits) === 9) {
        return '+233' . $digits;
    }

    return '+' . $digits;
};

$resolvePhoneColumn = function ($table) use ($db) {
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
    if ($safeTable === '') {
        return false;
    }

    try {
        $columns = $db->run("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table", [
            ':db' => DB_NAME,
            ':table' => $safeTable,
        ]);
    } catch (\Throwable $e) {
        return false;
    }

    if (!is_array($columns)) {
        return false;
    }

    $candidates = ['phone', 'phone_number', 'mobile', 'customer_phone', 'sender_phone', 'receiver'];
    foreach ($candidates as $candidate) {
        foreach ($columns as $column) {
            $name = is_object($column) ? ($column->COLUMN_NAME ?? null) : ($column['COLUMN_NAME'] ?? null);
            if ($name !== null && strtolower((string)$name) === $candidate) {
                return $candidate;
            }
        }
    }

    foreach ($columns as $column) {
        $name = is_object($column) ? ($column->COLUMN_NAME ?? null) : ($column['COLUMN_NAME'] ?? null);
        if ($name !== null && stripos((string)$name, 'phone') !== false) {
            return (string)$name;
        }
    }

    return false;
};

$serviceReturningStats = [];
$globalPhoneCounts = [];
$globalAgentPhoneCounts = [];
$globalPhoneServiceCounts = [];
$globalAgentServiceCounts = [];
$agentNameCache = [];
$getAgentName = function ($agentId) use ($db, &$agentNameCache) {
    $agentId = (int)$agentId;
    if ($agentId <= 0) {
        return 'Unknown Agent';
    }

    if (!isset($agentNameCache[$agentId])) {
        try {
            $user = $db->runOne("SELECT first_name, last_name FROM users WHERE id = :id", [':id' => $agentId]);
        } catch (\Throwable $e) {
            $user = null;
        }

        $first = trim((string)($user['first_name'] ?? ($user->first_name ?? '')));
        $last = trim((string)($user['last_name'] ?? ($user->last_name ?? '')));
        $agentNameCache[$agentId] = trim($first . ' ' . $last) ?: "User {$agentId}";
    }

    return $agentNameCache[$agentId];
};

$serviceDefinitions = [
    ['table' => 'airtimebundles', 'label' => 'Airtimebundle'],
    ['table' => 'momoremits', 'label' => 'Momo Remit'],
    ['table' => 'agencybankings', 'label' => 'Agency Banking'],
    ['table' => 'ezwichs', 'label' => 'Ezwich'],
    ['table' => 'cashpickups', 'label' => 'Cashpickup'],
    ['table' => 'sims', 'label' => 'Sims'],
    ['table' => 'payment_services', 'label' => 'Payment Services'],
    ['table' => 'cfas', 'label' => 'CFA'],
];

foreach ($serviceDefinitions as $serviceDefinition) {
    $table = $serviceDefinition['table'];
    $label = $serviceDefinition['label'];
    $phoneColumn = $resolvePhoneColumn($table);
    if ($phoneColumn === false) {
        continue;
    }

    $serviceWhere = "WHERE 1=1";
    $serviceParams = [];
    if ($startDate !== '' || $endDate !== '') {
        if ($startDate !== '' && $endDate !== '') {
            $serviceWhere .= " AND date_created BETWEEN :start AND :end";
            $serviceParams[':start'] = "$startDate 00:00:00";
            $serviceParams[':end'] = "$endDate 23:59:59";
        } elseif ($startDate !== '') {
            $serviceWhere .= " AND date_created >= :start";
            $serviceParams[':start'] = "$startDate 00:00:00";
        } else {
            $serviceWhere .= " AND date_created <= :end";
            $serviceParams[':end'] = "$endDate 23:59:59";
        }
    }
    if ($user !== '') {
        $serviceWhere .= " AND created_by = :user";
        $serviceParams[':user'] = $user;
    }
    if (!empty($users)) {
        $serviceUserPlaceholders = [];
        foreach ($users as $index => $uid) {
            $servicePlaceholder = ':svc_user_' . $index;
            $serviceUserPlaceholders[] = $servicePlaceholder;
            $serviceParams[$servicePlaceholder] = (int)$uid;
        }
        $serviceWhere .= " AND created_by IN (" . implode(', ', $serviceUserPlaceholders) . ")";
    }

    try {
        $rows = $db->run("SELECT {$phoneColumn} AS phone, created_by FROM {$table} {$serviceWhere}", $serviceParams);
    } catch (\Throwable $e) {
        $rows = [];
    }

    $rows = is_array($rows) ? $rows : [];
    $phoneCounts = [];
    $agentPhoneCounts = [];

    foreach ($rows as $row) {
        $row = is_object($row) ? (array)$row : $row;
        $phone = $normalizePhone($row['phone'] ?? $row['phone_number'] ?? '');
        if ($phone === null) {
            continue;
        }

        $phoneCounts[$phone] = ($phoneCounts[$phone] ?? 0) + 1;
        $agentId = (int)($row['created_by'] ?? 0);
        if ($agentId > 0) {
            $agentPhoneCounts[$agentId] = $agentPhoneCounts[$agentId] ?? [];
            $agentPhoneCounts[$agentId][$phone] = ($agentPhoneCounts[$agentId][$phone] ?? 0) + 1;
        }
    }

    $totalCustomers = count($phoneCounts);
    $returningCustomers = count(array_filter($phoneCounts, function ($count) {
        return (int)$count > 1;
    }));
    $totalReturningServes = array_sum(array_map(function ($count) {
        return max((int)$count - 1, 0);
    }, $phoneCounts));

    $serviceCustomerRows = [];
    foreach ($phoneCounts as $phone => $count) {
        $returningServes = max((int)$count - 1, 0);
        $serviceCustomerRows[] = [
            'phone' => $phone,
            'total_serves' => (int)$count,
            'returning_serves' => $returningServes,
            'return_rate' => $totalReturningServes > 0 ? round(($returningServes / $totalReturningServes) * 100, 2) : 0,
        ];
    }

    usort($serviceCustomerRows, function ($left, $right) {
        $cmp = (($right['total_serves'] ?? 0) <=> ($left['total_serves'] ?? 0));
        if ($cmp !== 0) {
            return $cmp;
        }
        return (($right['returning_serves'] ?? 0) <=> ($left['returning_serves'] ?? 0));
    });
    $serviceCustomerRows = array_slice($serviceCustomerRows, 0, 10);

    $serviceReturningStats[] = [
        'service' => $label,
        'table' => $table,
        'total_customers' => $totalCustomers,
        'returning_customers' => $returningCustomers,
        'return_rate' => $totalCustomers > 0 ? round(($returningCustomers / $totalCustomers) * 100, 2) : 0,
        'customers' => $serviceCustomerRows,
    ];

    foreach ($phoneCounts as $phone => $count) {
        $globalPhoneCounts[$phone] = ($globalPhoneCounts[$phone] ?? 0) + $count;
        if (!isset($globalPhoneServiceCounts[$phone])) {
            $globalPhoneServiceCounts[$phone] = [];
        }
        $returningServes = max((int)$count - 1, 0);
        $globalPhoneServiceCounts[$phone][$table] = [
            'service' => $label,
            'service_key' => $table,
            'total_serves' => (int)$count,
            'returning_serves' => $returningServes,
            'return_rate' => $totalReturningServes > 0 ? round(($returningServes / $totalReturningServes) * 100, 2) : 0,
        ];
    }

    foreach ($agentPhoneCounts as $agentId => $phoneMap) {
        if (!isset($globalAgentPhoneCounts[$agentId])) {
            $globalAgentPhoneCounts[$agentId] = [];
        }
        foreach ($phoneMap as $phone => $count) {
            $globalAgentPhoneCounts[$agentId][$phone] = ($globalAgentPhoneCounts[$agentId][$phone] ?? 0) + $count;
        }

        if (!isset($globalAgentServiceCounts[$agentId])) {
            $globalAgentServiceCounts[$agentId] = [];
        }

        $servicePhoneCounts = [];
        foreach ($phoneMap as $phone => $count) {
            $servicePhoneCounts[$phone] = $count;
        }

        $agentReturningCustomers = count(array_filter($servicePhoneCounts, function ($count) {
            return (int)$count > 1;
        }));
        $agentTotalCustomers = count($servicePhoneCounts);

        $globalAgentServiceCounts[$agentId][$table] = [
            'service' => $label,
            'service_key' => $table,
            'total_customers' => $agentTotalCustomers,
            'returning_customers' => $agentReturningCustomers,
            'return_rate' => $agentTotalCustomers > 0 ? round(($agentReturningCustomers / $agentTotalCustomers) * 100, 2) : 0,
        ];
    }
}

$customerStats = [];
foreach ($globalPhoneCounts as $phone => $count) {
    $serviceBreakdown = [];
    if (!empty($globalPhoneServiceCounts[$phone])) {
        foreach ($globalPhoneServiceCounts[$phone] as $serviceKey => $stats) {
            $serviceBreakdown[] = [
                'service' => $stats['service'],
                'service_key' => $serviceKey,
                'total_serves' => (int)$stats['total_serves'],
                'returning_serves' => (int)$stats['returning_serves'],
                'return_rate' => (float)$stats['return_rate'],
            ];
        }
    }

    $customerStats[] = [
        'phone' => $phone,
        'total_serves' => (int)$count,
        'returning_serves' => max((int)$count - 1, 0),
        'return_rate' => $count > 1 ? 100 : 0,
        'services' => $serviceBreakdown,
    ];
}

usort($customerStats, function ($left, $right) {
    $cmp = (($right['total_serves'] ?? 0) <=> ($left['total_serves'] ?? 0));
    if ($cmp !== 0) {
        return $cmp;
    }
    return (($right['returning_serves'] ?? 0) <=> ($left['returning_serves'] ?? 0));
});

$customerStats = array_slice($customerStats, 0, 10);

$agentStats = [];
foreach ($globalAgentPhoneCounts as $agentId => $phoneMap) {
    $returningCustomers = count(array_filter($phoneMap, function ($count) {
        return (int)$count > 1;
    }));

    if ($returningCustomers <= 0) {
        continue;
    }

    $serviceBreakdown = [];
    if (!empty($globalAgentServiceCounts[$agentId])) {
        foreach ($globalAgentServiceCounts[$agentId] as $serviceKey => $stats) {
            $serviceBreakdown[] = [
                'service' => $stats['service'],
                'service_key' => $serviceKey,
                'total_customers' => (int)$stats['total_customers'],
                'returning_customers' => (int)$stats['returning_customers'],
                'return_rate' => (float)$stats['return_rate'],
            ];
        }
    }

    $agentStats[] = [
        'agent_id' => (int)$agentId,
        'agent_name' => $getAgentName($agentId),
        'returning_customers' => $returningCustomers,
        'total_customers' => count($phoneMap),
        'total_serves' => array_sum($phoneMap),
        'services' => $serviceBreakdown,
    ];
}

usort($agentStats, function ($left, $right) {
    $cmp = (($right['returning_customers'] ?? 0) <=> ($left['returning_customers'] ?? 0));
    if ($cmp !== 0) {
        return $cmp;
    }
    return (($right['total_serves'] ?? 0) <=> ($left['total_serves'] ?? 0));
});

$agentStats = array_slice($agentStats, 0, 10);

// Add total_returning to each agent for overall percent calculation
foreach ($agentStats as &$agent) {
    $agent['total_returning'] = (int)($agent['returning_customers'] ?? 0);
}
unset($agent);

// —– (2) agencybankings —–
$agencyWhere = $approvedOnlyWhere('agencybankings', $where);
$agencyOverview  = $db->runOne("
    SELECT SUM(amount) AS total_amount, COUNT(*) AS total_transactions
    FROM agencybankings $agencyWhere
", $params);

$agencyByNetwork = $db->run("
    SELECT network, SUM(amount) AS amount, COUNT(*) AS customers
    FROM agencybankings $agencyWhere
    GROUP BY network
", $params);

$agencyByNetworkType = $db->run("
    SELECT network, transaction_type AS type, SUM(amount) AS amount, COUNT(*) AS customers
    FROM agencybankings $agencyWhere
    GROUP BY network, transaction_type
", $params);

$agencyByType    = $db->run("
    SELECT transaction_type, SUM(amount) AS amount, COUNT(*) AS customers
    FROM agencybankings $agencyWhere
    GROUP BY transaction_type
", $params);

// —– (2b) agencybankings distribution by user —–
$agencyByCreatedBy = [];
$agencyByCreatedByBreakdown = [];
try {
	$abWhere = $agencyWhere;
	$abWhere = preg_replace('/\\bdate_created\\b/', 'ab.date_created', $abWhere);
	$abWhere = preg_replace('/\\bcreated_by\\b/', 'ab.created_by', $abWhere);
	$abWhere = preg_replace('/\\btransaction_type\\b/', 'ab.transaction_type', $abWhere);
	$abWhere = preg_replace('/\\bcategory\\b/', 'ab.category', $abWhere);

	$agencyByCreatedBy = $db->run("
		SELECT
			ab.created_by,
			COALESCE(NULLIF(CONCAT(TRIM(u.first_name), ' ', TRIM(u.last_name)), ' '), CONCAT('User ', ab.created_by)) AS full_name,
			COUNT(*) AS total_count,
			SUM(ab.amount) AS total_amount
		FROM agencybankings ab
		LEFT JOIN users u ON u.id = ab.created_by
		$abWhere
		GROUP BY ab.created_by, full_name
		ORDER BY total_count DESC
	", $params);

	$agencyByCreatedByBreakdown = $db->run("
		SELECT
			ab.created_by,
			ab.network,
			ab.transaction_type,
			SUM(ab.amount) AS total_amount,
			COUNT(*) AS total_count
		FROM agencybankings ab
		$abWhere
		GROUP BY ab.created_by, ab.network, ab.transaction_type
		ORDER BY total_amount DESC
	", $params);
} catch (\Throwable $e) {
	$agencyByCreatedBy = [];
	$agencyByCreatedByBreakdown = [];
}

// —– (3) airtimebundles —–
$airWhere = $approvedOnlyWhere('airtimebundles', $like_where);
$airOverview  = $db->runOne("
    SELECT
      SUM(CAST(amount AS DECIMAL(10,2)))     AS total_amount,
      SUM(CAST(commission AS DECIMAL(10,2))) AS total_commission,
      COUNT(*)                               AS total_transactions
    FROM airtimebundles $airWhere
", $params);

$airByNetwork      = $db->run("SELECT network, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS customers FROM airtimebundles $airWhere GROUP BY network", $params);

$airByNetworkType  = $db->run("SELECT network, transaction_type AS type, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS customers FROM airtimebundles $airWhere GROUP BY network, transaction_type", $params);

$airByTransType    = $db->run("SELECT transaction_type, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS customers FROM airtimebundles $airWhere GROUP BY transaction_type", $params);

$airByStatus       = $db->run("SELECT transaction_status AS status, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS customers FROM airtimebundles $airWhere GROUP BY transaction_status", $params);

$airByNetTypeStat  = $db->run("SELECT network, transaction_type, transaction_status AS status, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS customers FROM airtimebundles $airWhere GROUP BY network, transaction_type, transaction_status", $params);

try {
	$airWhere = $approvedOnlyWhere('airtimebundles', $like_where);
	$airWhere = preg_replace('/\bnetwork\b/', 'a.network', $airWhere);
	$airWhere = preg_replace('/\btransaction_type\b/', 'a.transaction_type', $airWhere);
	$airWhere = preg_replace('/\bcreated_by\b/', 'a.created_by', $airWhere);
	$airWhere = preg_replace('/\bdate_created\b/', 'a.date_created', $airWhere);
	$airWhere = preg_replace('/\bamount\b/', 'a.amount', $airWhere);

	$airByCreatedBy = $db->run("
		SELECT
			a.created_by,
			COALESCE(NULLIF(CONCAT(TRIM(u.first_name), ' ', TRIM(u.last_name)), ' '), CONCAT('User ', a.created_by)) AS full_name,
			COUNT(*) AS total_count,
			SUM(CAST(a.amount AS DECIMAL(10,2))) AS total_amount
		FROM airtimebundles a
		LEFT JOIN users u ON u.id = a.created_by
		$airWhere
		GROUP BY a.created_by, full_name
		ORDER BY total_count DESC
	", $params);

	$airByCreatedByBreakdown = $db->run("
		SELECT
			a.created_by,
			a.network,
			a.transaction_type,
			SUM(CAST(a.amount AS DECIMAL(10,2))) AS total_amount,
			COUNT(*) AS total_count
		FROM airtimebundles a
		$airWhere
		GROUP BY a.created_by, a.network, a.transaction_type
		ORDER BY total_amount DESC
	", $params);
} catch (\Throwable $e) {
	$airByCreatedBy = [];
	$airByCreatedByBreakdown = [];
}

// —– (4) attendance —–
$attendanceDays   = $db->run("
    SELECT u.first_name, u.last_name, t.remarks, COUNT(DISTINCT t.date) AS days_worked
    FROM attendance t
    LEFT JOIN users u ON t.user_id = u.id
    $t_where
    GROUP BY t.remarks
", $params);
$attendanceByGender = $db->run("
    SELECT u.gender, COUNT(*) AS count
    FROM attendance t
    JOIN users u ON t.user_id = u.id
    $t_where
    GROUP BY u.gender
", $params);

$attendanceSummary = $db->run("
    SELECT 
      u.id,
      u.first_name,
      u.last_name,
      SUM(CASE WHEN UPPER(t.remarks) = 'PRESENT' THEN 1 ELSE 0 END) AS days_present,
      SUM(CASE WHEN UPPER(t.remarks) = 'LATE' THEN 1 ELSE 0 END) AS days_late,
      SUM(CASE WHEN UPPER(t.remarks) = 'ABSENT'  THEN 1 ELSE 0 END) AS days_absent
    FROM attendance t
    JOIN users u ON u.id = t.user_id
    $t_where
    GROUP BY t.user_id
", $params);


// —– (5) cfas —–
$cfaWhere = $approvedOnlyWhere('cfas', $where);
$cfaSummary = $db->runOne("
    SELECT SUM(amount_cfa) AS total_cfa, SUM(amount_ghs) AS total_ghs, COUNT(*) AS total_transactions
    FROM cfas $cfaWhere
", $params);
$cfaByType  = $db->run("
    SELECT transaction_type, SUM(amount_cfa) AS amount_cfa, SUM(amount_ghs) AS amount_ghs, COUNT(*) AS customers
    FROM cfas $cfaWhere
    GROUP BY transaction_type
", $params);

// —– (6) commissions —–
$commissionWhere = $approvedOnlyWhere('commissions', $where);
$commSummary   = $db->runOne("SELECT SUM(amount) AS total_commission, COUNT(*) AS total_transactions FROM commissions $commissionWhere", $params);
$commByNetwork = $db->run("SELECT network, SUM(amount) AS amount, COUNT(*) AS transactions FROM commissions $commissionWhere GROUP BY network", $params);

// —– (7) deposits —–
$depWhere = $approvedOnlyWhere('deposits', $where);
$depSummary   = $db->runOne("SELECT SUM(CAST(amount AS DECIMAL(10,2))) AS total_amount, COUNT(*) AS total_transactions FROM deposits $depWhere", $params);
$depByNetwork = $db->run("SELECT network, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS customers FROM deposits $depWhere GROUP BY network", $params);

// —– (8) ezwichs —–

$ezwWhere = $approvedOnlyWhere('ezwichs', $where);
$ezwSummary    = $db->runOne("SELECT SUM(amount) AS total_amount, COUNT(*) AS total_customers FROM ezwichs $ezwWhere", $params);
$ezwByType     = $db->run("SELECT transaction_type, transaction_status, SUM(amount) AS amount, SUM(charge) AS charges, COUNT(*) AS customers FROM ezwichs $ezwWhere GROUP BY transaction_type, transaction_status", $params);
$ezwByStatus   = $db->run("SELECT transaction_status AS status, SUM(amount) AS amount, SUM(charge) AS charges, COUNT(*) AS customers FROM ezwichs $ezwWhere GROUP BY transaction_status", $params);

// —– (9) momoremits —–
$momoWhere = $approvedOnlyWhere('momoremits', $like_where);
$momoSummary         = $db->runOne("SELECT SUM(CAST(amount AS DECIMAL(10,2))) AS total_amount, SUM(amount_payable) AS total_payable, COUNT(*) AS customers_served FROM momoremits $momoWhere", $params);
$momoByNetwork       = $db->run("SELECT network, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS customers FROM momoremits $momoWhere GROUP BY network", $params);
$momoByNetworkType   = $db->run("SELECT network, transaction_type AS type, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS customers FROM momoremits $momoWhere GROUP BY network, transaction_type", $params);
$momoByTransType     = $db->run("SELECT transaction_type AS type, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS customers FROM momoremits $momoWhere GROUP BY transaction_type", $params);

// —– (9b) momoremits distribution by user —–
$momoByCreatedBy = [];
$momoByCreatedByBreakdown = [];
try {
	$mmWhere = $momoWhere;
	$mmWhere = preg_replace('/\bdate_created\b/', 'mm.date_created', $mmWhere);
	$mmWhere = preg_replace('/\bcreated_by\b/', 'mm.created_by', $mmWhere);
	$mmWhere = preg_replace('/\btransaction_type\b/', 'mm.transaction_type', $mmWhere);
	$mmWhere = preg_replace('/\bcategory\b/', 'mm.category', $mmWhere);

	$momoByCreatedBy = $db->run("
		SELECT
			mm.created_by,
			COALESCE(NULLIF(CONCAT(TRIM(u.first_name), ' ', TRIM(u.last_name)), ' '), CONCAT('User ', mm.created_by)) AS full_name,
			COUNT(*) AS total_count,
			SUM(CAST(mm.amount AS DECIMAL(10,2))) AS total_amount
		FROM momoremits mm
		LEFT JOIN users u ON u.id = mm.created_by
		$mmWhere
		GROUP BY mm.created_by, full_name
		ORDER BY total_amount DESC
	", $params);

	$momoByCreatedByBreakdown = $db->run("
		SELECT
			mm.created_by,
			mm.network,
			mm.transaction_type,
			SUM(CAST(mm.amount AS DECIMAL(10,2))) AS total_amount,
			COUNT(*) AS total_count
		FROM momoremits mm
		$mmWhere
		GROUP BY mm.created_by, mm.network, mm.transaction_type
		ORDER BY total_amount DESC
	", $params);
} catch (\Throwable $e) {
	$momoByCreatedBy = [];
	$momoByCreatedByBreakdown = [];
}

// —– (10) myaccounts —–
$accSummary = $db->runOne("SELECT SUM(debit) AS total_debit, SUM(credit) AS total_credit, COUNT(*) AS total_customers FROM myaccounts $where", $params);

// —– (11) payments & backoffice —–
$payWhere = $approvedOnlyWhere('payments', $where);
$paySummary   = $db->runOne("SELECT SUM(amount) AS total_amount, COUNT(*) AS total_transactions FROM payments $payWhere", $params);
$payBackofficeWhere = $approvedOnlyWhere('payments_backoffice', $where);
$payBOsummary = $db->runOne("SELECT SUM(amount) AS total_amount, COUNT(*) AS total_transactions FROM payments_backoffice $payBackofficeWhere", $params);

// —– (12) payment_services —–
$svcWhere = $approvedOnlyWhere('payment_services', $where);
$svcSummary    = $db->runOne("SELECT SUM(amount) AS total_amount, SUM(charge) AS total_charge, COUNT(*) AS total_customers FROM payment_services $svcWhere", $params);
$svcByCategory = $db->run("SELECT category, SUM(amount) AS total_amount, SUM(charge) AS total_charge, COUNT(*) AS total_customers FROM payment_services $svcWhere GROUP BY category", $params);

// —– (12b) agent services —–
[$agentServicesWhere, $agentServicesParams] = $normalizeTableFilterForAgentServices($where, $params);
$agentServicesWhere = $approvedOnlyWhere('agent_services', $agentServicesWhere);
$agentServicesSummary = $db->runOne("SELECT COUNT(*) AS total_transactions, SUM(CAST(amount AS DECIMAL(10,2))) AS total_amount FROM agent_services $agentServicesWhere", $agentServicesParams);

// —– (13) requests & backoffice —–
$requestWhere = $approvedOnlyWhere('requests', $where);
$paymentBackofficeWhere = $approvedOnlyWhere('payments_backoffice', $where);
$reqSummary        = $db->run("SELECT network AS request_option, SUM(amount) AS total_amount, COUNT(*) AS total_count, transaction_status AS status FROM requests $requestWhere GROUP BY network, transaction_status", $params);
$paymentBOSummary  = $db->run("SELECT network AS payment_type, SUM(amount) AS total_payment, COUNT(*) AS total_transactions, transaction_status AS status FROM payments_backoffice $paymentBackofficeWhere GROUP BY network, transaction_status", $params);

// —– (14) reversals —–
$revWhere = $approvedOnlyWhere('reversals', $where);
$revSummary = $db->runOne("SELECT SUM(amount) AS total_amount, COUNT(*) AS total_transactions FROM reversals $revWhere", $params);

// —– (15) sendings —–
$sendWhere = $approvedOnlyWhere('sendings', $where);
$sendSummary    = $db->runOne("SELECT SUM(CAST(amount AS DECIMAL(10,2))) AS total_amount, COUNT(*) AS total_customers FROM sendings $sendWhere", $params);
$sendByNetwork  = $db->run("SELECT network, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS customers FROM sendings $sendWhere GROUP BY network", $params);

// —– (16) sims —–
$simsWhere = $approvedOnlyWhere('sims', $where);
$simsSummary   = $db->runOne("SELECT COUNT(*) AS total_quantity, SUM(amount) AS total_amount, SUM(commission) AS total_commission FROM sims $simsWhere", $params);
$simsByNetwork = $db->run("SELECT network, COUNT(*) AS qty, SUM(amount) AS total_amount, SUM(commission) AS total_commission FROM sims $simsWhere GROUP BY network", $params);
$simsByStatus  = $db->run("SELECT transaction_status AS sim_status, COUNT(*) AS quantity, SUM(amount) AS total_amount, SUM(commission) AS total_commission FROM sims $simsWhere GROUP BY transaction_status", $params);

// —– (17) tickets —–
$ticketsSummary = $db->run("SELECT status, COUNT(*) AS count FROM tickets $where GROUP BY status", $params);

// —– (17b) cashpickups —–
$cashpickupScopeParams = [];
$cashpickupScopeClauses = ["(deleted IS NULL OR deleted = 0)"];

 $cashpickupCanViewAll = false;
 try {
     $cashpickupCanViewAll = (user_can('is_manager') || user_can('is_admin'));
 } catch (\Throwable $e) {
     $cashpickupCanViewAll = false;
 }

 try {
     $roles = (array)($session->roles ?? []);
     $roles = array_map(function($r){ return strtolower(trim((string)$r)); }, $roles);
     $role0 = strtolower(trim((string)($roles[0] ?? '')));
     $isPrivileged = in_array($role0, ['manager','admin','is_manager','is_admin'], true);
     if (!$isPrivileged && !empty($roles)) {
         foreach ($roles as $r) {
             if (in_array($r, ['manager','admin','is_manager','is_admin'], true)) {
                 $isPrivileged = true;
                 break;
             }
         }
     }
     if ($isPrivileged) {
         $cashpickupCanViewAll = true;
     }
 } catch (\Throwable $e) {
     // ignore
 }

 if (!empty($session->id) && !$cashpickupCanViewAll) {
     $cashpickupScopeClauses[] = "(created_by = :cashpickup_uid OR assigned_user_id = :cashpickup_uid)";
     $cashpickupScopeParams[':cashpickup_uid'] = $session->id;
 }

if ($startDate && $endDate) {
    $cashpickupScopeClauses[] = "date_created BETWEEN :start AND :end";
    $cashpickupScopeParams[':start'] = "$startDate 00:00:00";
    $cashpickupScopeParams[':end'] = "$endDate   23:59:59";
}

if (!empty($users)) {
    $cashpickupUserPlaceholders = [];
    foreach ($users as $index => $uid) {
        $placeholder = ':cashpickup_user_' . $index;
        $cashpickupUserPlaceholders[] = $placeholder;
        $cashpickupScopeParams[$placeholder] = (int)$uid;
    }
    $joinedUsers = implode(', ', $cashpickupUserPlaceholders);
    $cashpickupScopeClauses[] = "(created_by IN ($joinedUsers) OR assigned_user_id IN ($joinedUsers))";
}

$cashpickupWhere = 'WHERE ' . implode(' AND ', $cashpickupScopeClauses);
$cashpickupAnalyticsWhere = $approvedOnlyWhere('cashpickups', $cashpickupWhere);

$cashpickupsError = '';

$cashpickupsDebug = [];
try {
    $cashpickupsDebug['can_view_all'] = !empty($cashpickupCanViewAll) ? 1 : 0;
    $cashpickupsDebug['scope_uid'] = (int)($session->id ?? 0);
    $cashpickupsDebug['scope_where'] = $cashpickupWhere;
} catch (\Throwable $e) {
    $cashpickupsDebug = [];
}

$cashpickupApprovedWhere = $cashpickupAnalyticsWhere;
$cashpickupOverview = $db->runOne("
    SELECT
      SUM(CAST(amount_involved AS DECIMAL(10,2))) AS total_amount,
      COUNT(*) AS total_transactions
    FROM cashpickups
    $cashpickupApprovedWhere
", $cashpickupScopeParams);

if ($cashpickupOverview === false) {
    try {
        $cashpickupsError = (string)($db->error ?? '');
    } catch (\Throwable $e) {
        $cashpickupsError = '';
    }
    $cashpickupOverview = ['total_amount' => 0, 'total_transactions' => 0];
}

// extra debug counts to understand why totals might be 0
try {
    $rowAll = $db->runOne("SELECT COUNT(*) AS c FROM cashpickups");
    $rowDel0Num = $db->runOne("SELECT COUNT(*) AS c FROM cashpickups WHERE deleted = 0");
    $rowDel0Str = $db->runOne("SELECT COUNT(*) AS c FROM cashpickups WHERE deleted = '0'");
    $rowDelNullOr0 = $db->runOne("SELECT COUNT(*) AS c FROM cashpickups WHERE (deleted IS NULL OR deleted = 0)");
    $cashpickupsDebug['count_all'] = (int)($rowAll['c'] ?? 0);
    $cashpickupsDebug['count_deleted_eq_0'] = (int)($rowDel0Num['c'] ?? 0);
    $cashpickupsDebug['count_deleted_eq_str0'] = (int)($rowDel0Str['c'] ?? 0);
    $cashpickupsDebug['count_deleted_null_or_0'] = (int)($rowDelNullOr0['c'] ?? 0);
} catch (\Throwable $e) {
    // ignore
}

$cashpickupHasApprovalCol = false;
try {
    $hasApprovalColRow = $db->runOne(
        "SELECT 1 AS ok
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = :db
           AND TABLE_NAME = 'cashpickups'
           AND COLUMN_NAME = 'approval_status'
         LIMIT 1",
        ['db' => DB_NAME]
    );
    $cashpickupHasApprovalCol = !empty($hasApprovalColRow);
} catch (\Throwable $e) {
    $cashpickupHasApprovalCol = false;
}

$cashpickupByApproval = [];
if ($cashpickupHasApprovalCol) {
    $cashpickupByApproval = $db->run("
        SELECT
          LOWER(COALESCE(approval_status,'pending')) AS status,
          COUNT(*) AS total_count
        FROM cashpickups
        $cashpickupAnalyticsWhere
        GROUP BY LOWER(COALESCE(approval_status,'pending'))
    ", $cashpickupScopeParams);
    if ($cashpickupByApproval === false) {
        if ($cashpickupsError === '') {
            try {
                $cashpickupsError = (string)($db->error ?? '');
            } catch (\Throwable $e) {
                $cashpickupsError = '';
            }
        }
        $cashpickupByApproval = [];
    }
} else {
    // If the column doesn't exist, align with legacy behavior: treat as pending
    $cashpickupByApproval = [(object)['status' => 'pending', 'total_count' => (int)($cashpickupOverview['total_transactions'] ?? 0)]];
}

$cashpickupByStatus = $db->run("
    SELECT
      transaction_status AS status,
      COUNT(*) AS total_count,
      SUM(CAST(amount_involved AS DECIMAL(10,2))) AS total_amount
    FROM cashpickups
    $cashpickupAnalyticsWhere
    GROUP BY transaction_status
", $cashpickupScopeParams);

if ($cashpickupByStatus === false) {
    if ($cashpickupsError === '') {
        try {
            $cashpickupsError = (string)($db->error ?? '');
        } catch (\Throwable $e) {
            $cashpickupsError = '';
        }
    }
    $cashpickupByStatus = [];
}

$cashpickupByDate = $db->run("
    SELECT
      DATE(date_created) AS date,
      COUNT(*) AS count,
      SUM(CAST(amount_involved AS DECIMAL(10,2))) AS amount
    FROM cashpickups
    $cashpickupAnalyticsWhere
    GROUP BY DATE(date_created)
    ORDER BY date ASC
", $cashpickupScopeParams);

if ($cashpickupByDate === false) {
    if ($cashpickupsError === '') {
        try {
            $cashpickupsError = (string)($db->error ?? '');
        } catch (\Throwable $e) {
            $cashpickupsError = '';
        }
    }
    $cashpickupByDate = [];
}

$cashpickupBySide = $db->run("
    SELECT
      LOWER(COALESCE(transaction_side,'')) AS side,
      COUNT(*) AS total_count
    FROM cashpickups
    $cashpickupAnalyticsWhere
    GROUP BY LOWER(COALESCE(transaction_side,''))
", $cashpickupScopeParams);

if ($cashpickupBySide === false) {
    if ($cashpickupsError === '') {
        try {
            $cashpickupsError = (string)($db->error ?? '');
        } catch (\Throwable $e) {
            $cashpickupsError = '';
        }
    }
    $cashpickupBySide = [];
}

$cashpickupBySideApproval = [];
try {
    if (!empty($cashpickupHasApprovalCol)) {
        $cashpickupBySideApproval = $db->run("
            SELECT
              LOWER(COALESCE(transaction_side,'')) AS side,
              LOWER(COALESCE(approval_status,'pending')) AS status,
              COUNT(*) AS total_count,
              SUM(CAST(amount_involved AS DECIMAL(10,2))) AS total_amount
            FROM cashpickups
            $cashpickupAnalyticsWhere
            GROUP BY LOWER(COALESCE(transaction_side,'')), LOWER(COALESCE(approval_status,'pending'))
        ", $cashpickupScopeParams);
        if ($cashpickupBySideApproval === false) {
            if ($cashpickupsError === '') {
                try {
                    $cashpickupsError = (string)($db->error ?? '');
                } catch (\Throwable $e) {
                    $cashpickupsError = '';
                }
            }
            $cashpickupBySideApproval = [];
        }
    }
} catch (\Throwable $e) {
    $cashpickupBySideApproval = [];
}

$cashpickupApprovalCounts = [
    'approved' => 0,
    'pending' => 0,
    'draft' => 0,
    'denied' => 0,
];
foreach (($cashpickupByApproval ?: []) as $row) {
    $r = (array)$row;
    $st = strtolower(trim((string)($r['status'] ?? '')));
    if ($st === '') continue;
    if (isset($cashpickupApprovalCounts[$st])) {
        $cashpickupApprovalCounts[$st] = (int)($r['total_count'] ?? 0);
    }
}

$vars = get_value();
$rolesMapTable = $vars['optional_tables']['roles_map_table'] ?? 'user_roles_map';
$rolesTable = $vars['optional_tables']['roles_table'] ?? 'user_roles';
$usersTableForRoles = $vars['optional_tables']['users_table'] ?? 'users';

$topRolesError = '';
$topRoles = [];

// schema-aware user filters (some installs don't have users.disabled/users.deleted)
$hasUserDisabledCol = false;
$hasUserDeletedCol = false;
try {
    $chkDisabled = $db->runOne("SELECT 1 AS ok FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t AND COLUMN_NAME = 'disabled' LIMIT 1", [
        'db' => DB_NAME,
        't' => $usersTableForRoles,
    ]);
    $hasUserDisabledCol = (bool)$chkDisabled;

    $chkDeleted = $db->runOne("SELECT 1 AS ok FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t AND COLUMN_NAME = 'deleted' LIMIT 1", [
        'db' => DB_NAME,
        't' => $usersTableForRoles,
    ]);
    $hasUserDeletedCol = (bool)$chkDeleted;
} catch (\Throwable $e) {
    $hasUserDisabledCol = false;
    $hasUserDeletedCol = false;
}

$userRoleWhereParts = [
    'urm.disabled = 0',
    '(ur.disabled IS NULL OR ur.disabled = 0)',
];
if ($hasUserDisabledCol) {
    $userRoleWhereParts[] = '(u.disabled IS NULL OR u.disabled = 0)';
}
if ($hasUserDeletedCol) {
    $userRoleWhereParts[] = '(u.deleted IS NULL OR u.deleted = 0)';
}
$userRoleWhere = 'WHERE ' . implode("\n          AND ", $userRoleWhereParts);

$unassignedWhereParts = [];
if ($hasUserDisabledCol) {
    $unassignedWhereParts[] = '(u.disabled IS NULL OR u.disabled = 0)';
}
if ($hasUserDeletedCol) {
    $unassignedWhereParts[] = '(u.deleted IS NULL OR u.deleted = 0)';
}
$unassignedWhere = $unassignedWhereParts ? ('WHERE ' . implode(' AND ', $unassignedWhereParts)) : '';

try {
    // Count enabled role assignments
    $assignedRoleCounts = $db->run("
        SELECT
          COALESCE(NULLIF(TRIM(ur.role),''), 'Unassigned') AS role,
          COUNT(DISTINCT urm.user_id) AS cnt
        FROM {$rolesMapTable} urm
        JOIN {$usersTableForRoles} u
          ON u.id = urm.user_id
        JOIN {$rolesTable} ur
          ON ur.id = urm.role_id
        {$userRoleWhere}
        GROUP BY COALESCE(NULLIF(TRIM(ur.role),''), 'Unassigned')
    ");

    if (!empty($db->has_error)) {
        $topRolesError = (string)($db->error ?? '');
        throw new \Exception($topRolesError);
    }

    foreach (($assignedRoleCounts ?: []) as $row) {
        $r = (array)$row;
        $roleName = trim((string)($r['role'] ?? ''));
        $cnt = (int)($r['cnt'] ?? 0);
        if ($roleName === '' || $cnt <= 0) continue;
        $topRoles[$roleName] = ($topRoles[$roleName] ?? 0) + $cnt;
    }

    // Count unassigned users (no active role mapping)
    $unassignedRow = $db->runOne("
        SELECT COUNT(*) AS cnt
        FROM {$usersTableForRoles} u
        {$unassignedWhere}
          AND NOT EXISTS (
            SELECT 1
            FROM {$rolesMapTable} urm
            WHERE urm.user_id = u.id
              AND urm.disabled = 0
          )
    ");
    if (!empty($db->has_error)) {
        $topRolesError = (string)($db->error ?? '');
        throw new \Exception($topRolesError);
    }
    $unassignedCnt = (int)(is_array($unassignedRow) ? ($unassignedRow['cnt'] ?? 0) : 0);
    if ($unassignedCnt > 0) {
        $topRoles['Unassigned'] = ($topRoles['Unassigned'] ?? 0) + $unassignedCnt;
    }
} catch (\Throwable $e) {
    $topRoles = [];
    if ($topRolesError === '') {
        $topRolesError = trim((string)$e->getMessage());
    }
}

// normalize to list, sort desc by cnt, take top 10
$topRolesList = [];
foreach (($topRoles ?: []) as $roleName => $cnt) {
    $roleName = trim((string)$roleName);
    $cnt = (int)$cnt;
    if ($roleName === '' || $cnt <= 0) continue;
    $topRolesList[] = ['role' => $roleName, 'cnt' => $cnt];
}
usort($topRolesList, function($a, $b) {
    return ($b['cnt'] ?? 0) <=> ($a['cnt'] ?? 0);
});
$topRoles = array_slice($topRolesList, 0, 10);

// —– (18) transactions —–
// 1) Overview: counts, sums...
$transWhere = $approvedOnlyWhere('transactions', $where);
$transOverview = $db->runOne("
    SELECT
      COUNT(*)       AS transaction_count,
      SUM(amount)    AS total_amount,
      SUM(debit)     AS total_debit,
      SUM(credit)    AS total_credit
    FROM transactions
    $transWhere
", $params);

// 2) Breakdown by transaction_type
$transByType = $db->run("
    SELECT
      transaction_type,
      COUNT(*)    AS count,
      SUM(amount) AS total_amount
    FROM transactions
    $transWhere
    GROUP BY transaction_type
", $params);

// 3) By date (last 30 days)
$transByDate = $db->run("
    SELECT
      DATE(date_created) AS date,
      COUNT(*)            AS count,
      SUM(amount)         AS amount,
      SUM(debit)          AS total_debit,
      SUM(credit)         AS total_credit
    FROM transactions
    $transWhere
    GROUP BY DATE(date_created)
    ORDER BY date ASC
", $params);

// 4) Recent 40, with full name & image, formatted date, no commission
$transRecentRaw = $db->run("
    SELECT
      t.date_created,
      u.first_name,
      u.last_name,
      u.image,
      t.transaction_type,
      t.description,
      t.amount,
      t.debit,
      t.credit,
      t.balance
    FROM transactions t
    JOIN users u ON u.id = t.created_by
    WHERE t.created_by = $user_id
      " . ($hasTransactionStatusColumn('transactions') ? "AND (t.transaction_status IS NULL OR LOWER(TRIM(t.transaction_status)) NOT IN ('rejected','declined','denied','reversed'))" : '') . "
    ORDER BY t.date_created DESC
    LIMIT 40
");

// Apply PHP-side formatting to date and full_name
$transRecent = [];
foreach ($transRecentRaw as $row) {
    $r = (array)$row;
    $transRecent[] = [
        'date'             => get_date($r['date_created']),
        'full_name'        => trim($r['first_name'] . ' ' . $r['last_name']),
        'image'            => get_image($r['image']),
        'transaction_type' => $r['transaction_type'],
        'amount'           => $r['amount'],
        'debit'            => $r['debit'],
        'credit'           => $r['credit'],
        'balance'          => $r['balance'],
        'description'          => $r['description'],
    ];
}

// —– (19) withdrawals —–
$withdrawalWhere = $approvedOnlyWhere('withdrawals', $where);
$withdOverview  = $db->runOne("SELECT SUM(CAST(amount AS DECIMAL(10,2))) AS total_amount, COUNT(*) AS total_transactions FROM withdrawals $withdrawalWhere", $params);
$withdByNetwork = $db->run("SELECT network, SUM(CAST(amount AS DECIMAL(10,2))) AS amount, COUNT(*) AS transactions FROM withdrawals $withdrawalWhere GROUP BY network", $params);

// —– (21) NEW KPI CALCULATIONS —–

// 21a) Average Transaction Value
$avgValue = 0;
if (!empty($transOverview['transaction_count'])) {
    $avgValue = $transOverview['total_amount'] / $transOverview['transaction_count'];
}

// 21b) Customer Reach (distinct users in transactions)
$reachRow = $db->runOne("
    SELECT COUNT(DISTINCT created_by) AS customer_reach
    FROM transactions
    $where
", $params);
$customerReach = $reachRow['customer_reach'] ?? 0;

// 21c) Failure Rate (%) in requests
$reqStats = $db->runOne("
    SELECT 
      SUM(CASE WHEN transaction_status != 'SUCCESS' THEN 1 ELSE 0 END) AS failures,
      COUNT(*) AS total
    FROM requests
    $requestWhere
", $params);
$failureRate = 0;
if (!empty($reqStats['total'])) {
    $failureRate = round($reqStats['failures'] / $reqStats['total'] * 100, 1);
}

// 21d) Average Ticket Resolution Time (hours)
$ticketStats = $db->runOne("
    SELECT 
      AVG(TIMESTAMPDIFF(HOUR, date_created, date_updated)) AS avg_resolution_hours
    FROM tickets
    $where
    AND date_updated IS NOT NULL
", $params);
$avgTicketTime = round($ticketStats['avg_resolution_hours'] ?? 0, 1);

// 21e) Deposit ↔ Withdrawal Ratio
$depSum = $depSummary['total_amount'] ?? 0;
$withdSum = $withdOverview['total_amount'] ?? 0;
$depWithRatio = $withdSum > 0 ? round($depSum / $withdSum, 2) : 0;

// 21f) Commission Yield (%) — total commission ÷ transaction volume
$totalComm = $commSummary['total_commission'] ?? 0;
$totalVol = $transOverview['total_amount']/2 ?? 0;
$commissionYield = $totalVol > 0 ? round($totalComm / $totalVol, 4) : 0;

// 21g) Network Coverage (distinct networks across key tables)
$netCovRow = $db->runOne("
    SELECT COUNT(DISTINCT network) AS coverage FROM (
      SELECT network FROM agencybankings $agencyWhere
      UNION
      SELECT network FROM sendings    $where
      UNION
      SELECT network FROM momoremits  $where
    ) AS combined
", $params);
$networkCoverage = $netCovRow['coverage'] ?? 0;

// 21h) Growth Rate (%) vs. prior period (transaction count)
$rangeDays = (new DateTime($endDate))->diff(new DateTime($startDate))->days + 1;
$priorStart = (new DateTime($startDate))->modify("-{$rangeDays} days")->format('Y-m-d') . ' 00:00:00';
$priorEnd   = (new DateTime($startDate))->modify('-1 day')->format('Y-m-d') . ' 23:59:59';
$priorParams = $params;
$priorParams[':start'] = $priorStart;
$priorParams[':end']   = $priorEnd;

$priorStats = $db->runOne("
    SELECT COUNT(*) AS prior_count
    FROM transactions
    WHERE date_created BETWEEN :start AND :end
", $priorParams);
$priorCount = $priorStats['prior_count'] ?? 0;
$currentCount = $transOverview['transaction_count'] ?? 0;
$growthRate = $priorCount > 0 
    ? round(($currentCount - $priorCount) / $priorCount * 100, 1)
    : 0;

// 21i) Attendance Penetration (%) — % of users who marked today
$attendanceRangeClause = "1=1";
$attendanceRangeParams = [];
if ($startDate !== '' || $endDate !== '') {
    if ($startDate !== '' && $endDate !== '') {
        $attendanceRangeClause = "date BETWEEN :start AND :end";
        $attendanceRangeParams[':start'] = $startDate;
        $attendanceRangeParams[':end'] = $endDate;
    } elseif ($startDate !== '') {
        $attendanceRangeClause = "date >= :start";
        $attendanceRangeParams[':start'] = $startDate;
    } else {
        $attendanceRangeClause = "date <= :end";
        $attendanceRangeParams[':end'] = $endDate;
    }
}

if (!empty($users)) {
    $attendanceUserPlaceholders = [];
    foreach ($users as $index => $uid) {
        $placeholder = ':attendance_user_' . $index;
        $attendanceUserPlaceholders[] = $placeholder;
        $attendanceRangeParams[$placeholder] = (int)$uid;
    }
    $attendanceRangeClause .= ' AND user_id IN (' . implode(', ', $attendanceUserPlaceholders) . ')';
} elseif ($user !== '') {
    $attendanceRangeClause .= ' AND user_id = :user';
    $attendanceRangeParams[':user'] = $user;
}

$markedToday = $db->runOne(" 
    SELECT COUNT(DISTINCT user_id) AS marked
    FROM attendance
    WHERE $attendanceRangeClause
", $attendanceRangeParams)['marked'] ?? 0;
$totalUsers = $db->runOne("SELECT COUNT(*) AS cnt FROM users WHERE deleted = 0")['cnt'] ?? 0;
$presentOrLateToday = $db->runOne(" 
    SELECT COUNT(DISTINCT user_id) AS cnt
    FROM attendance
    WHERE $attendanceRangeClause
      AND (UPPER(remarks) = 'PRESENT' OR UPPER(remarks) = 'LATE')
", $attendanceRangeParams)['cnt'] ?? 0;
$attendancePenetr = $totalUsers > 0
    ? round($markedToday / $totalUsers * 100, 1)
    : 0;

// 21j) Average Commission per Transaction (₵)
$avgCommPerTx = $transOverview['transaction_count'] > 0 
    ? round($totalComm / $transOverview['transaction_count'], 2)
    : 0;

// —– (20) Combine & output —–

$roleNames = [];
try {
  $roleNames = $session->roles ?? [];
  if(!is_array($roleNames)){
    $roleNames = [$roleNames];
  }
} catch (\Throwable $e) {
  $roleNames = [];
}
$primaryRole = '';
try {
  $primaryRole = strtolower(trim((string)($roleNames[0] ?? '')));
} catch (\Throwable $e) {
  $primaryRole = '';
}

$requestsBacklogRow = $db->runOne("SELECT COUNT(*) AS backlog FROM requests $where AND (transaction_status = 'Pending' OR transaction_status = 'pending' OR transaction_status = 'Draft' OR transaction_status = 'draft')", $params);
$requestsBacklog = (int)($requestsBacklogRow['backlog'] ?? 0);

$reqFailureTrend = [];
try {
  $reqFailureTrend = $db->run("SELECT DATE(date_created) AS day, COUNT(*) AS total, SUM(CASE WHEN transaction_status = 'Failed' OR transaction_status = 'failed' THEN 1 ELSE 0 END) AS failures FROM requests $requestWhere GROUP BY DATE(date_created) ORDER BY day DESC LIMIT 7", $params);
  if(!is_array($reqFailureTrend)){
    $reqFailureTrend = [];
  }
  $reqFailureTrend = array_reverse($reqFailureTrend);
} catch (\Throwable $e) {
  $reqFailureTrend = [];
}

$ezwTrend = [];
try {
  $ezwTrend = $db->run("SELECT DATE(date_created) AS day, SUM(amount) AS total_amount, COUNT(*) AS total_customers FROM ezwichs $ezwWhere GROUP BY DATE(date_created) ORDER BY day DESC LIMIT 7", $params);
  if(!is_array($ezwTrend)){
    $ezwTrend = [];
  }
  $ezwTrend = array_reverse($ezwTrend);
} catch (\Throwable $e) {
  $ezwTrend = [];
}

$output = [
  'meta' => [
    'start_date' => $startDate,
    'end_date'   => $endDate,
    'username'   => $username,
    'user_id'    => $user_id,
    'roles'      => $roleNames,
    'primary_role' => $primaryRole,
    'build_id' => 'dash_roles_v2',
    'total_users' => $totalUsers,
    'present_or_late_today' => $presentOrLateToday,
    'cashpickups_total' => (int)($cashpickupOverview['total_transactions'] ?? 0),
    'cashpickups_error' => $cashpickupsError,
    'cashpickups_can_view_all' => (!empty($cashpickupCanViewAll) ? 1 : 0),
    'cashpickups_debug' => $cashpickupsDebug,
    'cashpickups_approved' => (int)($cashpickupApprovalCounts['approved'] ?? 0),
    'cashpickups_pending' => (int)($cashpickupApprovalCounts['pending'] ?? 0),
    'cashpickups_draft' => (int)($cashpickupApprovalCounts['draft'] ?? 0),
    'cashpickups_denied' => (int)($cashpickupApprovalCounts['denied'] ?? 0),
    'top_roles' => $topRoles,
    'top_roles_error' => $topRolesError,
    'overall_returning' => [
      'total_customers' => count($globalPhoneCounts),
      'total_returning_customers' => count(array_filter($globalPhoneCounts, function ($c) { return (int)$c > 1; })),
      'returning_rate' => (count($globalPhoneCounts) > 0) ? round((count(array_filter($globalPhoneCounts, function ($c) { return (int)$c > 1; })) / count($globalPhoneCounts)) * 100, 2) : 0,
    ],
  ],
  'agencybankings'   => [
	'Overview'=>$agencyOverview,
	'By_Network'=>$agencyByNetwork,
	'By_Network_Type'=>$agencyByNetworkType,
	'By_Type'=>$agencyByType,
	'By_Created_By'=>$agencyByCreatedBy,
	'By_Created_By_Breakdown'=>$agencyByCreatedByBreakdown
	],
  'airtimebundles'   => [
	'Overview'=>$airOverview,
	'By_Network'=>$airByNetwork,
	'By_Network_Type'=>$airByNetworkType,
	'By_Type'=>$airByTransType,
	'By_Status'=>$airByStatus,
	'By_Network_Type_Status'=>$airByNetTypeStat,
	'By_Created_By'=>$airByCreatedBy,
	'By_Created_By_Breakdown'=>$airByCreatedByBreakdown
	],
  'attendance'       => ['Summary' => $attendanceSummary, 'Days_Worked' => $attendanceDays, 'By_Gender' => $attendanceByGender ],
  'cfas'             => ['Summary'=>$cfaSummary, 'By_Type'=>$cfaByType],
  'commissions'      => ['Summary'=>$commSummary, 'By_Network'=>$commByNetwork],
  'cashpickups'      => [
    'Overview' => $cashpickupOverview,
    'By_Status' => $cashpickupByStatus,
    'By_Approval' => $cashpickupByApproval,
    'By_Date' => $cashpickupByDate,
    'By_Side' => $cashpickupBySide,
    'By_Side_Approval' => $cashpickupBySideApproval,
  ],
  'deposits'         => ['Overview'=>$depSummary, 'By_Network'=>$depByNetwork],
  'ezwichs'          => ['Overview'=>$ezwSummary, 'By_Type'=>$ezwByType, 'By_Status'=>$ezwByStatus],
  'momoremits'       => [
	'Overview'=>$momoSummary,
	'By_Network'=>$momoByNetwork,
	'By_Type'=>$momoByTransType,
	'By_Network_Type'=>$momoByNetworkType,
	'By_Created_By'=>$momoByCreatedBy,
	'By_Created_By_Breakdown'=>$momoByCreatedByBreakdown
	],
  'myaccounts'       => ['Summary'=>$accSummary],
  'payments'         => ['Summary'=>$paySummary],
  'payment_services' => ['Summary'=>$svcSummary, 'By_Category'=>$svcByCategory],
  'agent_services'   => ['Summary'=>$agentServicesSummary],
  'requests'         => [
    'Requests'=>$reqSummary,
    'Backoffice_Payments'=>$paymentBOSummary,
    'FailureRate' => $failureRate,
    'Backlog' => $requestsBacklog,
    'By_Date' => $reqFailureTrend,
  ],
  'reversals'        => ['Summary'=>$revSummary],
  'sendings'         => ['Overview'=>$sendSummary, 'By_Network'=>$sendByNetwork],
  'sims'             => ['Summary'=>$simsSummary, 'By_Network'=>$simsByNetwork, 'By_Status'=>$simsByStatus],
  'tickets'          => ['By_Status'=>$ticketsSummary, 'AvgResolutionTime' => $avgTicketTime],
  'transactions'     => [
    'Overview'=>$transOverview,
    'By_Type'=>$transByType,
    'By_Date'=>$transByDate,
    'Recent'=>$transRecent,
    'AverageValue' => $avgValue,
    'GrowthRate' => $growthRate,
    'AvgCommission' => $avgCommPerTx,
  ],
  'withdrawals'      => ['Overview'=>$withdOverview, 'By_Network'=>$withdByNetwork],
  'returningCustomers' => [
    'services' => $serviceReturningStats,
    'customers' => $customerStats,
    'agents' => $agentStats,
  ],
  'customerReach'                   => $customerReach,
  'depositWithdrawalRatio'          => $depWithRatio,
  'commissionYield'                 => $commissionYield,
  'networkCoverage'                 => $networkCoverage,
];

$output['attendance']['Penetration'] = $attendancePenetr;
$output['ezwichs']['By_Date'] = $ezwTrend;


echo json_encode($output, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
exit;

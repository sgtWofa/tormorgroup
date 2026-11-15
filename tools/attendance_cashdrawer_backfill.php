<?php
/**
 * One-time attendance backfill from cashdrawer history.
 *
 * CLI dry run:
 *   php tools/attendance_cashdrawer_backfill.php
 *
 * CLI apply:
 *   php tools/attendance_cashdrawer_backfill.php --apply
 *
 * Browser mode is disabled until BACKFILL_WEB_TOKEN is changed below.
 * Browser dry run:
 *   /tools/attendance_cashdrawer_backfill.php?token=YOUR_SECRET
 *
 * Browser apply:
 *   /tools/attendance_cashdrawer_backfill.php?token=YOUR_SECRET&apply=1
 *
 * Optional:
 *   --from=YYYY-MM-DD --to=YYYY-MM-DD --all-calendar-days --no-backup
 *   ?from=YYYY-MM-DD&to=YYYY-MM-DD&all_calendar_days=1&backup=0
 */

declare(strict_types=1);

date_default_timezone_set('Africa/Accra');

const BACKFILL_WEB_TOKEN = '15011992';

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
	header('Content-Type: text/plain; charset=utf-8');
	if (BACKFILL_WEB_TOKEN === '') {
		http_response_code(403);
		echo "Browser access is disabled. Edit this file and set BACKFILL_WEB_TOKEN to a long private value, or run it from CLI.\n";
		exit;
	}
	if (!hash_equals(BACKFILL_WEB_TOKEN, (string)($_GET['token'] ?? ''))) {
		http_response_code(403);
		echo "Invalid token.\n";
		exit;
	}
}

$rootPath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
require $rootPath . 'config.php';

$option = function (string $name, $default = null) use ($isCli) {
	if ($isCli) {
		global $argv;
		$prefix = '--' . $name . '=';
		foreach (($argv ?? []) as $arg) {
			if (strpos($arg, $prefix) === 0) {
				return substr($arg, strlen($prefix));
			}
		}
		return in_array('--' . $name, $argv ?? [], true) ? true : $default;
	}
	$key = str_replace('-', '_', $name);
	return array_key_exists($key, $_GET) ? $_GET[$key] : $default;
};

$apply = $isCli ? in_array('--apply', $argv ?? [], true) : ((string)($_GET['apply'] ?? '') === '1');
$backup = $isCli ? !in_array('--no-backup', $argv ?? [], true) : ((string)($_GET['backup'] ?? '1') !== '0');
$allCalendarDays = $isCli ? in_array('--all-calendar-days', $argv ?? [], true) : ((string)($_GET['all_calendar_days'] ?? '') === '1');
$fromDate = trim((string)$option('from', ''));
$toDate = trim((string)$option('to', ''));

$normalizeDate = function (string $value, string $label): ?string {
	if ($value === '') {
		return null;
	}
	$dt = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
	if (!$dt || $dt->format('Y-m-d') !== $value) {
		throw new RuntimeException("Invalid {$label}. Use YYYY-MM-DD.");
	}
	return $value;
};

$hasColumn = function (PDO $pdo, string $table, string $column): bool {
	$stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		if (strcasecmp((string)$row['Field'], $column) === 0) {
			return true;
		}
	}
	return false;
};

$normalizeTime = function ($time, string $fallback): string {
	$value = trim((string)$time);
	if ($value === '') {
		$value = $fallback;
	}
	if (strlen($value) === 5) {
		$value .= ':00';
	}
	$ts = strtotime('1970-01-01 ' . $value);
	return $ts === false ? $fallback : date('H:i:s', $ts);
};

$quoteIdentifier = function (string $name): string {
	return '`' . str_replace('`', '``', $name) . '`';
};

$dsn = DB_DRIVER . ':host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
$pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
	PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
	PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$yesterday = (new DateTimeImmutable('yesterday'))->format('Y-m-d');
$fromDate = $normalizeDate($fromDate, 'from date');
$toDate = $normalizeDate($toDate, 'to date') ?: $yesterday;
if ($toDate >= (new DateTimeImmutable('today'))->format('Y-m-d')) {
	throw new RuntimeException('The to date must be before today. This backfill never updates the current day.');
}

$settings = $pdo->query('SELECT work_open, work_close FROM settings ORDER BY id DESC LIMIT 1')->fetch() ?: [];
$workOpen = $normalizeTime($settings['work_open'] ?? '', '08:00:00');
$workClose = $normalizeTime($settings['work_close'] ?? '', '17:00:00');
$openTs = strtotime('1970-01-01 ' . $workOpen);
$lateThreshold = $openTs === false ? '08:30:00' : date('H:i:s', $openTs + 1800);

$userWhere = 'COALESCE(u.deleted,0)=0';
if ($hasColumn($pdo, 'users', 'disabled')) {
	$userWhere .= ' AND COALESCE(u.disabled,0)=0';
}
$cashdrawerHasDeleted = $hasColumn($pdo, 'cashdrawers', 'deleted');
$drawerDeletedCondition = $cashdrawerHasDeleted ? ' AND COALESCE(c.deleted,0)=0' : '';
$drawerDeletedWhere = $cashdrawerHasDeleted ? ' AND COALESCE(deleted,0)=0' : '';

if ($fromDate === null) {
	$sqlMin = "SELECT MIN(DATE(date_created)) AS min_date FROM cashdrawers WHERE DATE(date_created) <= :to_date {$drawerDeletedWhere}";
	$stmt = $pdo->prepare($sqlMin);
	$stmt->execute(['to_date' => $toDate]);
	$fromDate = (string)(($stmt->fetch() ?: [])['min_date'] ?? '');
	if ($fromDate === '') {
		echo json_encode(['success' => true, 'message' => 'No cashdrawer history found to backfill.'], JSON_PRETTY_PRINT) . PHP_EOL;
		exit;
	}
}
if ($fromDate > $toDate) {
	throw new RuntimeException('The from date cannot be after the to date.');
}

$countSql = function (string $sql, array $params = []) use ($pdo): int {
	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);
	$row = $stmt->fetch() ?: [];
	return (int)($row['c'] ?? 0);
};

$buildTempTables = function () use ($pdo, $fromDate, $toDate, $drawerDeletedWhere, $allCalendarDays): void {
	$pdo->exec('DROP TEMPORARY TABLE IF EXISTS tmp_att_first_drawers');
	$pdo->exec('DROP TEMPORARY TABLE IF EXISTS tmp_att_workdays');
	$stmt = $pdo->prepare("
		CREATE TEMPORARY TABLE tmp_att_first_drawers AS
		SELECT CAST(user_id AS CHAR(50)) AS user_id,
		       DATE(date_created) AS d,
		       MIN(date_created) AS first_drawer_at
		FROM cashdrawers
		WHERE DATE(date_created) >= :from_date
		  AND DATE(date_created) <= :to_date
		  {$drawerDeletedWhere}
		GROUP BY CAST(user_id AS CHAR(50)), DATE(date_created)
	");
	$stmt->execute(['from_date' => $fromDate, 'to_date' => $toDate]);
	$pdo->exec('ALTER TABLE tmp_att_first_drawers ADD INDEX idx_tmp_att_fd (user_id, d)');

	if ($allCalendarDays) {
		$pdo->exec('CREATE TEMPORARY TABLE tmp_att_workdays (d DATE NOT NULL PRIMARY KEY)');
		$start = new DateTimeImmutable($fromDate);
		$end = new DateTimeImmutable($toDate);
		$stmtIns = $pdo->prepare('INSERT INTO tmp_att_workdays (d) VALUES (:d)');
		for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
			$stmtIns->execute(['d' => $day->format('Y-m-d')]);
		}
		return;
	}

	$pdo->exec("
		CREATE TEMPORARY TABLE tmp_att_workdays AS
		SELECT DISTINCT d FROM tmp_att_first_drawers
	");
	$pdo->exec('ALTER TABLE tmp_att_workdays ADD INDEX idx_tmp_att_wd (d)');
};

$preview = function () use ($countSql, $userWhere, $lateThreshold, $workClose): array {
	return [
		'attendance_rows_to_correct_from_first_drawer' => $countSql("
			SELECT COUNT(*) AS c
			FROM attendance a
			INNER JOIN tmp_att_first_drawers fd ON fd.user_id = a.user_id AND fd.d = a.date
			WHERE COALESCE(a.deleted,0)=0
			  AND (
				a.time <> TIME(fd.first_drawer_at)
				OR a.date_created <> fd.first_drawer_at
				OR UPPER(COALESCE(a.remarks,'')) <> CASE WHEN TIME(fd.first_drawer_at) >= :late THEN 'LATE' ELSE 'PRESENT' END
			  )
		", ['late' => $lateThreshold]),
		'present_or_late_rows_to_insert_from_drawers' => $countSql("
			SELECT COUNT(*) AS c
			FROM tmp_att_first_drawers fd
			LEFT JOIN attendance a
			  ON a.user_id = fd.user_id
			 AND a.date = fd.d
			 AND COALESCE(a.deleted,0)=0
			WHERE a.id IS NULL
		"),
		'absent_existing_rows_to_correct' => $countSql("
			SELECT COUNT(*) AS c
			FROM attendance a
			INNER JOIN users u ON u.id = a.user_id
			INNER JOIN tmp_att_workdays wd ON wd.d = a.date
			LEFT JOIN tmp_att_first_drawers fd ON fd.user_id = a.user_id AND fd.d = a.date
			WHERE COALESCE(a.deleted,0)=0
			  AND {$userWhere}
			  AND fd.user_id IS NULL
			  AND (
				UPPER(COALESCE(a.remarks,'')) <> 'ABSENT'
				OR a.time <> :work_close
				OR a.date_created <> CONCAT(a.date, ' ', :work_close_created)
			  )
		", ['work_close' => $workClose, 'work_close_created' => $workClose]),
		'absent_rows_to_insert_for_no_cashdrawer' => $countSql("
			SELECT COUNT(*) AS c
			FROM tmp_att_workdays wd
			CROSS JOIN users u
			LEFT JOIN tmp_att_first_drawers fd ON fd.user_id = u.id AND fd.d = wd.d
			LEFT JOIN attendance a
			  ON a.user_id = u.id
			 AND a.date = wd.d
			 AND COALESCE(a.deleted,0)=0
			WHERE {$userWhere}
			  AND fd.user_id IS NULL
			  AND a.id IS NULL
		"),
	];
};

$buildTempTables();
$before = $preview();

if (!$apply) {
	echo json_encode([
		'success' => true,
		'mode' => 'dry-run',
		'from' => $fromDate,
		'to' => $toDate,
		'workdays_mode' => $allCalendarDays ? 'all calendar days' : 'days with at least one cashdrawer',
		'work_open' => $workOpen,
		'work_close' => $workClose,
		'late_threshold' => $lateThreshold,
		'before' => $before,
		'next_step' => $isCli ? 'Run with --apply after taking a database backup.' : 'Open with &apply=1 after taking a database backup.',
	], JSON_PRETTY_PRINT) . PHP_EOL;
	exit;
}

$backupTable = null;
if ($backup) {
	$backupTable = 'attendance_backfill_backup_' . date('Ymd_His');
	$pdo->exec('CREATE TABLE ' . $quoteIdentifier($backupTable) . ' AS SELECT * FROM attendance');
}

$runAt = date('Y-m-d H:i:s');
$pdo->beginTransaction();
try {
	$stmt = $pdo->prepare("
		UPDATE attendance a
		INNER JOIN tmp_att_first_drawers fd ON fd.user_id = a.user_id AND fd.d = a.date
		SET a.time = TIME(fd.first_drawer_at),
		    a.remarks = CASE WHEN TIME(fd.first_drawer_at) >= :late THEN 'LATE' ELSE 'PRESENT' END,
		    a.date_created = fd.first_drawer_at,
		    a.updated_by = 'system',
		    a.date_updated = :run_at
		WHERE COALESCE(a.deleted,0)=0
	");
	$stmt->execute(['late' => $lateThreshold, 'run_at' => $runAt]);
	$updatedFromDrawers = $stmt->rowCount();

	$stmt = $pdo->prepare("
		INSERT INTO attendance (date, user_id, time, remarks, created_by, deleted, date_created)
		SELECT fd.d,
		       fd.user_id,
		       TIME(fd.first_drawer_at),
		       CASE WHEN TIME(fd.first_drawer_at) >= :late THEN 'LATE' ELSE 'PRESENT' END,
		       'system',
		       0,
		       fd.first_drawer_at
		FROM tmp_att_first_drawers fd
		LEFT JOIN attendance a
		  ON a.user_id = fd.user_id
		 AND a.date = fd.d
		 AND COALESCE(a.deleted,0)=0
		WHERE a.id IS NULL
	");
	$stmt->execute(['late' => $lateThreshold]);
	$insertedFromDrawers = $stmt->rowCount();

	$stmt = $pdo->prepare("
		UPDATE attendance a
		INNER JOIN users u ON u.id = a.user_id
		INNER JOIN tmp_att_workdays wd ON wd.d = a.date
		LEFT JOIN tmp_att_first_drawers fd ON fd.user_id = a.user_id AND fd.d = a.date
		SET a.remarks = 'ABSENT',
		    a.time = :work_close,
		    a.date_created = CONCAT(a.date, ' ', :work_close_created),
		    a.updated_by = 'system',
		    a.date_updated = :run_at
		WHERE COALESCE(a.deleted,0)=0
		  AND {$userWhere}
		  AND fd.user_id IS NULL
	");
	$stmt->execute([
		'work_close' => $workClose,
		'work_close_created' => $workClose,
		'run_at' => $runAt,
	]);
	$updatedAbsent = $stmt->rowCount();

	$stmt = $pdo->prepare("
		INSERT INTO attendance (date, user_id, time, remarks, created_by, deleted, date_created)
		SELECT wd.d,
		       u.id,
		       :work_close,
		       'ABSENT',
		       'system',
		       0,
		       CONCAT(wd.d, ' ', :work_close_created)
		FROM tmp_att_workdays wd
		CROSS JOIN users u
		LEFT JOIN tmp_att_first_drawers fd ON fd.user_id = u.id AND fd.d = wd.d
		LEFT JOIN attendance a
		  ON a.user_id = u.id
		 AND a.date = wd.d
		 AND COALESCE(a.deleted,0)=0
		WHERE {$userWhere}
		  AND fd.user_id IS NULL
		  AND a.id IS NULL
	");
	$stmt->execute([
		'work_close' => $workClose,
		'work_close_created' => $workClose,
	]);
	$insertedAbsent = $stmt->rowCount();

	$pdo->commit();
} catch (Throwable $e) {
	$pdo->rollBack();
	throw $e;
}

$buildTempTables();
$after = $preview();

echo json_encode([
	'success' => true,
	'mode' => 'applied',
	'from' => $fromDate,
	'to' => $toDate,
	'workdays_mode' => $allCalendarDays ? 'all calendar days' : 'days with at least one cashdrawer',
	'work_open' => $workOpen,
	'work_close' => $workClose,
	'late_threshold' => $lateThreshold,
	'backup_table' => $backupTable,
	'before' => $before,
	'changed' => [
		'updated_from_cashdrawer_time' => $updatedFromDrawers,
		'inserted_from_cashdrawer_time' => $insertedFromDrawers,
		'updated_absent_no_cashdrawer' => $updatedAbsent,
		'inserted_absent_no_cashdrawer' => $insertedAbsent,
	],
	'after' => $after,
], JSON_PRETTY_PRINT) . PHP_EOL;

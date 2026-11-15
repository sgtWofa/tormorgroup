<?php

if (!function_exists('attendance_work_close_normalize_time')) {
	function attendance_work_close_normalize_time($time): ?string
	{
		$value = trim((string)$time);
		if ($value === '') {
			return null;
		}
		if (strlen($value) === 5) {
			$value .= ':00';
		}
		$ts = strtotime('1970-01-01 ' . $value);
		return $ts === false ? null : date('H:i:s', $ts);
	}
}

if (!function_exists('attendance_work_close_marker_dir')) {
	function attendance_work_close_marker_dir(): string
	{
		return ROOTPATH . 'temp' . DIRECTORY_SEPARATOR . 'attendance-work-close';
	}
}

if (!function_exists('attendance_work_close_marker_path')) {
	function attendance_work_close_marker_path(?string $date = null): string
	{
		$date = preg_replace('/[^0-9\-]/', '', (string)($date ?? ''));
		if ($date === '') {
			$date = date('Y-m-d');
		}
		return attendance_work_close_marker_dir() . DIRECTORY_SEPARATOR . 'absent-' . $date . '.json';
	}
}

if (!function_exists('attendance_work_close_has_marker')) {
	function attendance_work_close_has_marker(string $date): bool
	{
		return is_file(attendance_work_close_marker_path($date));
	}
}

if (!function_exists('attendance_work_close_write_marker')) {
	function attendance_work_close_write_marker(string $date, array $payload = []): void
	{
		try {
			$dir = attendance_work_close_marker_dir();
			if (!is_dir($dir)) {
				mkdir($dir, 0777, true);
			}
			$payload['date'] = $date;
			$payload['written_at'] = date('Y-m-d H:i:s');
			file_put_contents(attendance_work_close_marker_path($date), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		} catch (\Throwable $e) {
			error_log('[Attendance][WorkCloseAbsent] marker write failed: ' . $e->getMessage());
		}
	}
}

if (!function_exists('attendance_work_close_table_has_column')) {
	function attendance_work_close_table_has_column(\Core\Database $db, string $table, string $column): bool
	{
		try {
			$rows = $db->query("SHOW COLUMNS FROM {$table}") ?: [];
			foreach ($rows as $row) {
				$field = (string)($row->Field ?? $row->field ?? '');
				if (strcasecmp($field, $column) === 0) {
					return true;
				}
			}
		} catch (\Throwable $e) {
			return false;
		}
		return false;
	}
}

if (!function_exists('attendance_mark_absentees_after_work_close')) {
	function attendance_mark_absentees_after_work_close(?\Core\Database $db = null, ?\DateTimeInterface $now = null, bool $force = false): array
	{
		static $requestCache = [];
		try {
			$db = $db ?: new \Core\Database;
			if (!$db->table_exists(['settings', 'attendance', 'users', 'cashdrawers'])) {
				return ['ran' => false, 'reason' => 'missing_tables'];
			}

			$settings = ($db->query("SELECT work_close FROM settings ORDER BY id DESC LIMIT 1") ?: [])[0] ?? null;
			$workClose = attendance_work_close_normalize_time($settings->work_close ?? '');
			if ($workClose === null) {
				return ['ran' => false, 'reason' => 'missing_work_close'];
			}

			$tz = new \DateTimeZone('Africa/Accra');
			$nowDt = $now instanceof \DateTimeInterface
				? (new \DateTimeImmutable('@' . $now->getTimestamp()))->setTimezone($tz)
				: new \DateTimeImmutable('now', $tz);
			$todayDate = $nowDt->format('Y-m-d');
			$todayCloseDt = new \DateTimeImmutable($todayDate . ' ' . $workClose, $tz);
			$cacheKey = $todayDate . '|' . $workClose . '|' . ($force ? 'force' : 'normal');
			if (isset($requestCache[$cacheKey])) {
				return $requestCache[$cacheKey];
			}

			$candidateDates = [];
			for ($daysBack = 3; $daysBack >= 0; $daysBack--) {
				$candidateDate = $nowDt->modify("-{$daysBack} day")->format('Y-m-d');
				$candidateCloseDt = new \DateTimeImmutable($candidateDate . ' ' . $workClose, $tz);
				if ($nowDt < $candidateCloseDt) {
					continue;
				}
				if (!$force && attendance_work_close_has_marker($candidateDate)) {
					continue;
				}
				$candidateDates[] = $candidateDate;
			}

			if (empty($candidateDates)) {
				$reason = $nowDt < $todayCloseDt ? 'before_work_close' : 'already_processed';
				return $requestCache[$cacheKey] = ['ran' => false, 'reason' => $reason, 'date' => $todayDate, 'time' => $workClose];
			}

			$userWhere = "COALESCE(u.deleted, 0) = 0";
			if (attendance_work_close_table_has_column($db, 'users', 'disabled')) {
				$userWhere .= " AND COALESCE(u.disabled, 0) = 0";
			}
			$drawerDeletedWhere = '';
			if (attendance_work_close_table_has_column($db, 'cashdrawers', 'deleted')) {
				$drawerDeletedWhere = " AND COALESCE(c.deleted, 0) = 0";
			}

			$processed = [];
			$totalInserted = 0;
			$totalUpdated = 0;

			foreach ($candidateDates as $attendanceDate) {
				$closeDt = new \DateTimeImmutable($attendanceDate . ' ' . $workClose, $tz);
				$closeDateTime = $closeDt->format('Y-m-d H:i:s');

				$db->query(
					"INSERT INTO attendance (date, user_id, time, remarks, created_by, deleted, date_created)
					 SELECT :att_date, u.id, :att_time, 'ABSENT', 'system', 0, :date_created
					 FROM users u
					 LEFT JOIN cashdrawers c
					   ON c.user_id = u.id
					  AND DATE(c.date_created) = :drawer_date
					  {$drawerDeletedWhere}
					 LEFT JOIN attendance a
					   ON a.user_id = u.id
					  AND a.date = :existing_att_date
					  AND COALESCE(a.deleted, 0) = 0
					 WHERE {$userWhere}
					   AND c.id IS NULL
					   AND a.id IS NULL",
					[
						'att_date' => $attendanceDate,
						'att_time' => $workClose,
						'date_created' => $closeDateTime,
						'drawer_date' => $attendanceDate,
						'existing_att_date' => $attendanceDate,
					]
				);
				if (!empty($db->has_error)) {
					error_log('[Attendance][WorkCloseAbsent] insert failed: ' . (string)$db->error);
					return ['ran' => false, 'reason' => 'insert_failed', 'message' => (string)$db->error, 'date' => $attendanceDate];
				}
				$inserted = (int)($db->affected_rows ?? 0);

				$db->query(
					"UPDATE attendance a
					 JOIN users u ON u.id = a.user_id
					 LEFT JOIN cashdrawers c
					   ON c.user_id = a.user_id
					  AND DATE(c.date_created) = :drawer_date_update
					  {$drawerDeletedWhere}
					 SET a.remarks = 'ABSENT',
					     a.time = :att_time_update,
					     a.updated_by = 'system',
					     a.date_updated = :date_updated
					 WHERE COALESCE(a.deleted, 0) = 0
					   AND a.date = :att_date_update
					   AND {$userWhere}
					   AND c.id IS NULL
					   AND UPPER(COALESCE(a.remarks, '')) <> 'ABSENT'",
					[
						'drawer_date_update' => $attendanceDate,
						'att_time_update' => $workClose,
						'date_updated' => $closeDateTime,
						'att_date_update' => $attendanceDate,
					]
				);
				if (!empty($db->has_error)) {
					error_log('[Attendance][WorkCloseAbsent] update failed: ' . (string)$db->error);
					return ['ran' => false, 'reason' => 'update_failed', 'message' => (string)$db->error, 'date' => $attendanceDate];
				}
				$updated = (int)($db->affected_rows ?? 0);

				$dayResult = ['date' => $attendanceDate, 'time' => $workClose, 'inserted' => $inserted, 'updated' => $updated];
				$processed[] = $dayResult;
				$totalInserted += $inserted;
				$totalUpdated += $updated;
				if (!$force) {
					attendance_work_close_write_marker($attendanceDate, $dayResult);
				}
			}

			$result = [
				'ran' => true,
				'date' => end($candidateDates) ?: $todayDate,
				'time' => $workClose,
				'inserted' => $totalInserted,
				'updated' => $totalUpdated,
				'processed' => $processed,
			];

			return $requestCache[$cacheKey] = $result;
		} catch (\Throwable $e) {
			error_log('[Attendance][WorkCloseAbsent] ' . $e->getMessage());
			return ['ran' => false, 'reason' => 'error', 'message' => $e->getMessage()];
		}
	}
}

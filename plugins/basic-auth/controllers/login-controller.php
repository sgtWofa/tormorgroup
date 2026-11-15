<?php
    // Set timezone to Accra
    date_default_timezone_set('Africa/Accra');
    require_once ROOTPATH . 'plugins/attendance/helpers/work-close-attendance.php';

    $user = new \BasicAuth\User;
    $log = new \BasicAuth\Log;

	if(csrf_verify($req->post()))
	{
		$postdata = $req->post();
		$row = $user->first(['email'=>$postdata['email']]);
		
		if($row)
		{
			if((int)($row->deleted ?? 0) === 1)
			{

				message_fail('Your account was deleted');
				redirect('login');
				exit;
			}else if((int)($row->disabled ?? 0) === 1)
			{
				message_fail('Your account is disabled. Please contact admin.');
				redirect('login');
				exit;
			}else{

				if(password_verify($postdata['password'], $row->password))
				{
					$postLoginRedirect = trim((string)($_SESSION['post_login_redirect'] ?? ''));
					unset($_SESSION['post_login_redirect']);
					if ($postLoginRedirect !== '' && (strpos($postLoginRedirect, '/') === 0 || preg_match('/^[a-zA-Z0-9_-]+(?:\/|$)/', $postLoginRedirect))) {
						$postLoginRedirect = ltrim($postLoginRedirect, '/');
					} else {
						$postLoginRedirect = '';
					}
					$dailyGate = [
						'within_work_hours' => true,
						'is_after_close' => false,
						'today' => date('Y-m-d'),
						'now' => null,
						'work_open' => '',
						'work_close' => '',
						'late_attendance_time' => '',
						'has_drawer_today' => false,
						'drawer_opened_at' => '',
					];
					$gateDb = null;

					// Work-hours gate:
					// 1) Block all logins before work_open for the day.
					// 2) After work_close, allow login only if user already opened a cashdrawer today.
					//    Users without a drawer should be blocked; users with a drawer can reconcile.
					try {
						$gateDb = new \Core\Database;
						if ($gateDb->table_exists(['settings'])) {
							$settingsRow = ($gateDb->query("SELECT work_open, work_close, late_attendance_time FROM settings ORDER BY id DESC LIMIT 1") ?: [])[0] ?? null;
							$workOpenForGate = $settingsRow ? trim((string)($settingsRow->work_open ?? '')) : '';
							$workCloseForGate = $settingsRow ? trim((string)($settingsRow->work_close ?? '')) : '';
							$lateAttendanceGate = $settingsRow ? trim((string)($settingsRow->late_attendance_time ?? '')) : '';
							$dailyGate['work_open'] = $workOpenForGate;
							$dailyGate['work_close'] = $workCloseForGate;
							$dailyGate['late_attendance_time'] = $lateAttendanceGate;
							$tzGate = new \DateTimeZone('Africa/Accra');
							$nowGate = new \DateTime('now', $tzGate);
							$todayGate = $nowGate->format('Y-m-d');
							$dailyGate['now'] = $nowGate;
							$dailyGate['today'] = $todayGate;

							$uidGate = (int)($row->id ?? 0);
							if ($uidGate > 0 && $gateDb->table_exists(['cashdrawers'])) {
								$drawerRows = $gateDb->query(
									"SELECT date_created
									 FROM cashdrawers
									 WHERE user_id = :uid
									 AND DATE(date_created) = :d
									 ORDER BY date_created ASC, id ASC
									 LIMIT 1",
									['uid' => (string)$uidGate, 'd' => $todayGate]
								) ?: [];
								if (!empty($drawerRows)) {
									$dailyGate['has_drawer_today'] = true;
									$dailyGate['drawer_opened_at'] = (string)($drawerRows[0]->date_created ?? '');
								}
							}

							if ($workOpenForGate !== '') {
								if (strlen($workOpenForGate) === 5) $workOpenForGate .= ':00';
								$openTodayGate = new \DateTime($todayGate . ' ' . $workOpenForGate, $tzGate);
								if ($nowGate < $openTodayGate) {
									$dailyGate['within_work_hours'] = false;
									$message = 'Work has not started yet. Please login after ' . $workOpenForGate . '.';
									message_fail($message);
									redirect('login');
									exit;
								}
							}

							if ($workCloseForGate !== '') {
								if (strlen($workCloseForGate) === 5) $workCloseForGate .= ':00';
								$closeTodayGate = new \DateTime($todayGate . ' ' . $workCloseForGate, $tzGate);
								if ($nowGate >= $closeTodayGate) {
									if (function_exists('attendance_mark_absentees_after_work_close')) {
										attendance_mark_absentees_after_work_close($gateDb, $nowGate);
									}
									$dailyGate['within_work_hours'] = false;
									$dailyGate['is_after_close'] = true;
									if (empty($dailyGate['has_drawer_today'])) {
										set_value('work_closed_notice', [
											'title' => 'Work Closed',
											'message' => 'Work has closed for today. You did not open a cashdrawer.',
										]);
										message_fail('Work has closed for today. You did not open a cashdrawer.');
										redirect('login');
										exit;
									}
								}
							}
						}
					} catch (\Throwable $e) {
						// fail open; do not block login if gate lookup fails
					}

					$ses->auth($row);

        			$session = get_session();
					
					$redirectToRecon = false;
					$reconRedirectUrl = 'admin/back_office/reconciliation';

					if (!empty($dailyGate['has_drawer_today']) && !empty($dailyGate['drawer_opened_at'])) {
						try {
							$dbAttendance = $gateDb instanceof \Core\Database ? $gateDb : new \Core\Database;
							if ($dbAttendance->table_exists(['attendance'])) {
								$todayDate = (string)($dailyGate['today'] ?? date('Y-m-d'));
								$uid = (string)($session->id ?? $row->id);
								$existing = $dbAttendance->query(
									"SELECT id FROM attendance WHERE deleted = 0 AND user_id = :uid AND date = :d LIMIT 1",
									['uid' => $uid, 'd' => $todayDate]
								) ?: [];
								if (empty($existing)) {
									$attendanceDateTime = (string)$dailyGate['drawer_opened_at'];
									$attendanceTs = strtotime($attendanceDateTime);
									if ($attendanceTs !== false) {
										$attendanceTime = date('H:i:s', $attendanceTs);
										$remarksToSet = 'PRESENT';
										$workOpen = trim((string)($dailyGate['work_open'] ?? ''));
										$lateAttendanceTime = trim((string)($dailyGate['late_attendance_time'] ?? ''));
										if ($lateAttendanceTime !== '' || $workOpen !== '') {
											if ($lateAttendanceTime !== '') {
												if (strlen($lateAttendanceTime) === 5) $lateAttendanceTime .= ':00';
												$thr = $lateAttendanceTime;
											} else {
												if (strlen($workOpen) === 5) $workOpen .= ':00';
												$woTs = strtotime('1970-01-01 ' . $workOpen);
												$thr = ($woTs !== false) ? date('H:i:s', $woTs + (30 * 60)) : '';
											}
											$lt = strtotime('1970-01-01 ' . $attendanceTime);
											$th = $thr !== '' ? strtotime('1970-01-01 ' . $thr) : false;
											if ($lt !== false && $th !== false && $lt >= $th) {
												$remarksToSet = 'LATE';
											}
										}
										$dbAttendance->query(
											"INSERT INTO attendance (date, user_id, time, remarks, created_by, deleted, date_created) VALUES (:d, :uid, :t, :r, 'system', 0, :dc)",
											['d' => $todayDate, 'uid' => $uid, 't' => $attendanceTime, 'r' => $remarksToSet, 'dc' => $attendanceDateTime]
										);
									}
								}
							}
						} catch (\Throwable $e) {
							// fail silently; attendance should not block login
						}
					}

					 // Maintenance mode check
					$role0 = '';
					if (isset($session->roles) && is_array($session->roles) && isset($session->roles[0])) {
						$role0 = (string)$session->roles[0];
					}
	                if (file_exists(ROOTPATH.'maintenance.flag') && $role0 !== 'admin') {
	                	//dd($session);die;
	                    message_fail('System is under maintenance. Only administrators can access at this time.');
	                    //exit;
						redirect('maintenance');
	                }
					
					$username = $row->first_name . ' ' . $row->last_name ?? '';
                    //log changes in the log table
                    $action                             = 'logged in';
                    $date_created                   = date("Y-m-d H:i:s");
                    $table                              = 'users';
                    $description                    = "$username $action successfully.";

                    $log_activity = $log->insert([
                        'table_name'=>$table,
                        'action'=>$action,
                        'user_id'=>$row->id,
                        'user_name'=>$username,
                        'description'=>$description,
                        'date_created'=>$date_created,
                     ]);

					// after successful login, ensure user has an open cash drawer for today
					$drawer_redirect = false;
					try {
						$db = $gateDb instanceof \Core\Database ? $gateDb : new \Core\Database;
						$today = (string)($dailyGate['today'] ?? date('Y-m-d'));
						$uidForChecks = (int)($session->id ?? $row->id);
						$hasDrawerToday = !empty($dailyGate['has_drawer_today']);
						if ($uidForChecks > 0 && function_exists('operations_account_has_cashdrawer_today')) {
							$hasDrawerToday = operations_account_has_cashdrawer_today($uidForChecks, $today);
							$dailyGate['has_drawer_today'] = $hasDrawerToday;
						}

						// If closing time has passed and reconciliation not done for today,
						// do NOT force cashdrawer creation; go to reconciliation first.
						$workClose = trim((string)($dailyGate['work_close'] ?? ''));
						$now = $dailyGate['now'] instanceof \DateTimeInterface
							? (clone $dailyGate['now'])
							: new \DateTime('now', new \DateTimeZone('Africa/Accra'));
						if (!empty($dailyGate['is_after_close']) && $hasDrawerToday && $workClose !== '' && $db->table_exists(['reconciliations_summary'])) {
							$start = $now->format('Y-m-d 00:00:00');
							$end = (clone $now)->modify('+1 day')->format('Y-m-d 00:00:00');
							$uid = $uidForChecks;
							$reconRows = $db->query(
								"SELECT id FROM reconciliations_summary WHERE deleted = 0 AND created_by = :uid AND date_created >= :start AND date_created < :end LIMIT 1",
								['uid' => (string)$uid, 'start' => $start, 'end' => $end]
							) ?: [];
							$redirectToRecon = empty($reconRows);
						}
						if (!$redirectToRecon) {
							// check cashdrawers header for a drawer opened today
							if (!$hasDrawerToday && !empty($dailyGate['within_work_hours'])) {
								$drawer_redirect = true;
							}
						}
					} catch (\Throwable $e) {
						// fail silently and continue normal redirect if drawer check fails
					}
					
					if($postLoginRedirect !== '' && !empty($dailyGate['has_drawer_today']))
					{
						redirect($postLoginRedirect);
					}else
					if($redirectToRecon)
					{
						redirect($reconRedirectUrl);
					}else
					if($drawer_redirect)
					{
						redirect('admin/operations_accounts/add_cashdrawer');
					}else{
						redirect('admin');
					}
				}			
			}
		}

		message_fail('Wrong email or password');
	}else
	{
		message_fail('Form expired! Please refresh');
	}

<?php



// Set timezone to Accra

date_default_timezone_set('Africa/Accra');
require_once ROOTPATH . 'plugins/attendance/helpers/work-close-attendance.php';



$log = new \BasicAuth\Log;



// Retrieve session details

$session = get_session();

$username = trim(($session->first_name ?? '') . ' ' . ($session->last_name ?? ''));

$user_id = $session->id ?? 0;



$redirectToRecon = false;

$reconRedirectUrl = 'admin/back_office/reconciliation';

try {

	$db = new \Core\Database;

	if ($user_id && $db->table_exists(['settings', 'reconciliations_summary'])) {

		$settingsRow = ($db->query("SELECT work_close FROM settings ORDER BY id DESC LIMIT 1") ?: [])[0] ?? null;

		$workClose = $settingsRow ? trim((string)($settingsRow->work_close ?? '')) : '';

		if ($workClose !== '') {

			$tz = new \DateTimeZone('Africa/Accra');

			$now = new \DateTime('now', $tz);

			$closeToday = new \DateTime($now->format('Y-m-d') . ' ' . $workClose, $tz);

			if ($now >= $closeToday) {
				if (function_exists('attendance_mark_absentees_after_work_close')) {
					attendance_mark_absentees_after_work_close($db, $now);
				}

				$start = $now->format('Y-m-d 00:00:00');

				$end = (clone $now)->modify('+1 day')->format('Y-m-d 00:00:00');

				$reconRows = $db->query(

					"SELECT id, date_created FROM reconciliations_summary WHERE deleted = 0 AND created_by = :uid AND date_created >= :start AND date_created < :end ORDER BY date_created DESC, id DESC LIMIT 1",

					['uid' => (string)$user_id, 'start' => $start, 'end' => $end]

				) ?: [];

				$redirectToRecon = empty($reconRows);

				if (!$redirectToRecon && !empty($reconRows) && $db->table_exists(['cashdrawers'])) {

					$reconTs = (string)($reconRows[0]->date_created ?? '');

					if ($reconTs !== '') {
						// Prefer audit logs to detect any post-reconciliation activity by the user.
						// Some transactions may not update drawer_balances/drawer_denos timestamps consistently.
						if (!$redirectToRecon && $db->table_exists(['logs'])) {
							$activityRows = $db->query(
								"SELECT id FROM logs WHERE user_id = :uid AND date_created > :recon_ts AND action NOT IN ('logged in','logged out') AND NOT (table_name = 'reconciliations' AND action = 'added') ORDER BY date_created DESC, id DESC LIMIT 1",
								['uid' => (string)$user_id, 'recon_ts' => $reconTs]
							) ?: [];
							if (!empty($activityRows)) {
								$redirectToRecon = true;
							}
						}

						// Fallback: compare cashdrawer timestamps to reconciliation timestamp.
						if ($redirectToRecon) {
							// skip fallback
						} else {

						$drawerRows = $db->query(
							"SELECT id FROM cashdrawers WHERE user_id = :uid AND DATE(date_created) = :d ORDER BY id DESC LIMIT 1",
							['uid' => $user_id, 'd' => $now->format('Y-m-d')]
						) ?: [];
						if (empty($drawerRows)) {
							$drawerRows = $db->query(
								"SELECT id FROM cashdrawers WHERE user_id = :uid ORDER BY date_created DESC, id DESC LIMIT 1",
								['uid' => $user_id]
							) ?: [];
						}
						$drawerId = !empty($drawerRows) ? (int)($drawerRows[0]->id ?? 0) : 0;

						if ($drawerId > 0) {

							$lastTs = '';
							try {
								$subParts = [];
								$subParams = ['drawer_id' => $drawerId];
								if ($db->table_exists(['drawer_balances'])) {
									$subParts[] = "SELECT MAX(date_created) AS ts FROM drawer_balances WHERE drawer_id = :drawer_id";
								}
								if ($db->table_exists(['drawer_denos'])) {
									$subParts[] = "SELECT MAX(date_created) AS ts FROM drawer_denos WHERE drawer_id = :drawer_id";
								}
								if (!empty($subParts)) {
									$sqlLast = "SELECT MAX(ts) AS last_ts FROM (" . implode(" UNION ALL ", $subParts) . ") t";
									$lastActRows = $db->query($sqlLast, $subParams) ?: [];
									$lastTs = (string)($lastActRows[0]->last_ts ?? '');
								}
							} catch (\Throwable $e) {
								$lastTs = '';
							}

							if ($lastTs !== '' && strtotime($lastTs) !== false && strtotime($reconTs) !== false) {

								if (strtotime($lastTs) > strtotime($reconTs) + 3) {

									$redirectToRecon = true;

								}

							}

						}
						}

					}

				}

			}

		}

	}

} catch (\Throwable $e) {

	$redirectToRecon = false;

}



if ($redirectToRecon) {

	message_fail('Reconciliation required before logout.');

	redirect($reconRedirectUrl . '?recon_required=1&reason=changed');
	exit;

}



//log changes in the log table

$action                             = 'logged out';

$date_created                   = date("Y-m-d H:i:s");

$table                              = 'users';

$description                    = "$username $action successfully.";



$log_activity = $log->insert([

    'table_name'=>$table,

    'action'=>$action,

    'user_id'=>$user_id,

    'user_name'=>$username,

    'description'=>$description,

    'date_created'=>$date_created,

 ]);



$ses->logout();



redirect($vars['login_page']);


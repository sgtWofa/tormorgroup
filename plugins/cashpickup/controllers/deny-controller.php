<?php

$cashpickup = new \Cashpickups\Cashpickup;

$session = get_session();
$actorId = (int)($session->id ?? 0);

// Admin or General Manager can deny
$canDeny = user_can('is_admin');
if (!$canDeny) {
	try {
		$dbRole = new \Core\Database;
		$gm = $dbRole->query(
			"SELECT 1
			 FROM user_roles_map urm
			 JOIN user_roles ur ON ur.id = urm.role_id
			 WHERE urm.disabled = 0
			 AND urm.user_id = :uid
			 AND LOWER(ur.role) IN ('general manager','general_manager','generalmanager','gm','is_general_manager')
			 LIMIT 1",
			['uid' => $actorId]
		);
		$canDeny = !empty($gm);
	} catch (\Throwable $e) {
		$canDeny = false;
	}
}

if (!$canDeny) {
	message_fail('Access denied');
	redirect($admin_route.'/'.$plugin_route);
}

$postdata = $req->post();
$csrf = csrf_verify($postdata);
if (!$csrf) {
	message_fail('Form expired');
	redirect($admin_route.'/'.$plugin_route);
}

$reason = trim((string)($postdata['denial_reason'] ?? ''));
if ($reason === '') {
	message_fail('Reason is required');
	redirect($admin_route.'/'.$plugin_route);
}

$id = (int)(URL(3) ?? 0);
if ($id <= 0) {
	message_fail('Invalid record');
	redirect($admin_route.'/'.$plugin_route);
}

$row = $cashpickup->first(['id' => $id]);
if (empty($row)) {
	message_fail('Record not found');
	redirect($admin_route.'/'.$plugin_route);
}

$side = strtolower(trim((string)($row->transaction_side ?? '')));
$prevApproval = strtolower(trim((string)($row->approval_status ?? 'pending')));
if ($prevApproval === '') $prevApproval = 'pending';

$cashpickup->update($id, [
	'approval_status' => 'denied',
	'approved_by' => $actorId,
	'approved_at' => date('Y-m-d H:i:s'),
	'denial_reason' => $reason,
	'updated_by' => $actorId,
	'date_updated' => date('Y-m-d H:i:s'),
]);

if ($side === 'pay' && strtolower(trim((string)($row->credit_mode ?? ''))) === 'credit') {
	$linkReceiveId = (int)($row->receive_id ?? 0);
	if ($linkReceiveId > 0) {
		$cashpickup->update($linkReceiveId, [
			'approval_status' => 'pending',
			'approved_by' => null,
			'approved_at' => null,
			'transaction_status' => 'Pending payout',
			'denial_reason' => $reason,
			'updated_by' => $actorId,
			'date_updated' => date('Y-m-d H:i:s'),
		]);
	}
}

// If an approved payout is denied later, reverse its cashdrawer impact
if ($side === 'pay' && $prevApproval === 'approved') {
	try {
		$drawerUserId = (int)($row->created_by ?? 0);
		if ($drawerUserId > 0) {
			$pm = strtolower(trim((string)($row->payment_method ?? '')));
			$transId = (int)($row->id ?? 0);
			$bridgePath = realpath(__DIR__ . '/../../backoffice/helpers/cashdrawer-bridge.php');
			if ($bridgePath && file_exists($bridgePath)) {
				require_once $bridgePath;
				\BackOfficeCashdrawer\init();
				if ($pm === 'cash') {
					$denos = \BackOfficeCashdrawer\normalizeDenominations($row->denos_json ?? '[]', (float)($row->amount_paid_out ?? 0), 'GHS');
					// reverse cash OUT by syncing cash IN
					\BackOfficeCashdrawer\syncCashMovement((int)$drawerUserId, $denos, (float)($row->amount_paid_out ?? 0), 'Approved', $transId, 'in');
				} elseif ($pm === 'transfer') {
					$drawerId = 0;
					$ownerId = 0;
					try {
						$dbOwner = new \Core\Database;
						$today = date('Y-m-d');
						$r = $dbOwner->query("SELECT id, user_id FROM cashdrawers WHERE user_id = :user_id AND DATE(date_created) = :today ORDER BY id DESC LIMIT 1", ['user_id' => $drawerUserId, 'today' => $today]);
						if (empty($r)) {
							$r = $dbOwner->query("SELECT id, user_id FROM cashdrawers WHERE user_id = :user_id ORDER BY date_created DESC, id DESC LIMIT 1", ['user_id' => $drawerUserId]);
						}
						if (!empty($r)) {
							$drawerId = (int)($r[0]->id ?? 0);
							$ownerId = (int)($r[0]->user_id ?? 0);
						}
					} catch (\Throwable $e) {}
					if ($drawerId > 0 && $ownerId > 0) {
						$dbBal = new \Core\Database;
						if ($dbBal->table_exists(['drawer_balances'])) {
							$now = date('Y-m-d H:i:s');
							$channel = strtoupper(trim((string)($row->payout_channel ?? '')));
							$amt = (float)($row->amount_paid_out ?? 0);
							if ($channel !== '' && $amt > 0) {
								$rowsEcash = $dbBal->query(
									"SELECT * FROM drawer_balances
									 WHERE drawer_id = :drawer_id AND user_id = :user_id
									 AND channel_type = 'ecash'
									 AND (channel_key = :channel_key OR channel_key = :ecash_channel_key)
									 ORDER BY id DESC LIMIT 1",
									[
										'drawer_id' => $drawerId,
										'user_id' => $ownerId,
										'channel_key' => $channel,
										'ecash_channel_key' => 'ECASH_' . $channel,
									]
								);
								if (empty($rowsEcash)) {
									$opening = 0.0;
									$closing = $opening + $amt;
									$dbBal->query(
										"INSERT INTO drawer_balances (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status)
										 VALUES (:drawer_id, :user_id, 'ecash', :channel_key, :opening, :credit, :debit, :closing, :date_created, :trans_id, :trans_status)",
										[
											'drawer_id' => $drawerId,
											'user_id' => $ownerId,
											'channel_key' => $channel,
											'opening' => $opening,
											'credit' => $amt,
											'debit' => 0.0,
											'closing' => $closing,
											'date_created' => $now,
											'trans_id' => $transId,
											'trans_status' => 'Approved',
										]
									);
								} else {
									$rowEcash = $rowsEcash[0];
									$opening = (float)($rowEcash->opening_balance ?? 0);
									$newCredit = (float)($rowEcash->credit ?? 0);
									$newDebit = max(0.0, (float)($rowEcash->debit ?? 0) - $amt);
									$closing = $opening + $newCredit - $newDebit;
									$dbBal->query(
										"UPDATE drawer_balances
										 SET channel_key = :channel_key, credit = :credit, debit = :debit, closing_balance = :closing
										 WHERE id = :id",
										[
											'channel_key' => $channel,
											'credit' => $newCredit,
											'debit' => $newDebit,
											'closing' => $closing,
											'id' => (int)($rowEcash->id ?? 0),
										]
									);
								}
							}
						}
					}
				}
			}
		}
	} catch (\Throwable $e) {
		// fail silently
	}
}

message_success('Cashpickup denied');
redirect($admin_route.'/'.$plugin_route);

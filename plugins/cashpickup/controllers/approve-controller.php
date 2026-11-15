<?php

$cashpickup = new \Cashpickups\Cashpickup;

$session = get_session();
$actorId = (int)($session->id ?? 0);

// Admin or General Manager can approve
$canApprove = user_can('is_admin');
if (!$canApprove) {
	try {
		$dbRole = new \Core\Database;
		$gm = $dbRole->query(
			"SELECT 1
			 FROM user_roles_map urm
			 JOIN user_roles ur ON ur.id = urm.role_id
			 WHERE urm.disabled = 0
			 AND urm.user_id = :uid
			 AND LOWER(ur.role) IN ('general manager','general_manager','generalmanager','gm','is_general_manager','operations manager','operations_manager')
			 LIMIT 1",
			['uid' => $actorId]
		);
		$canApprove = !empty($gm);
	} catch (\Throwable $e) {
		$canApprove = false;
	}
}

if (!$canApprove) {
	message_fail('Access denied');
	redirect($admin_route.'/'.$plugin_route);
}

$postdata = $req->post();
$csrf = csrf_verify($postdata);
if (!$csrf) {
	message_fail('Form expired');
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
$isCreditReceive = $side === 'receive' && strtolower(trim((string)($row->credit_mode ?? ''))) === 'credit';

if ($isCreditReceive) {
	try {
		$dbCheck = new \Core\Database;
		$requiredAmount = (float)($row->amount_involved ?? 0);
		$paidRows = $dbCheck->query(
			"SELECT COALESCE(SUM(COALESCE(amount_paid_out,0) + COALESCE(pickup_charge,0)),0) AS paid
			 FROM cashpickups
			 WHERE (deleted IS NULL OR deleted = 0)
			 AND LOWER(transaction_side) = 'pay'
			 AND receive_id = :rid
			 AND LOWER(COALESCE(transaction_status,'')) = 'approved'
			 AND (confirmed_by IS NOT NULL AND confirmed_by != '')
			 AND COALESCE(amount_paid_out,0) > 0
			 AND (
			 	approval_status IS NULL
			 	OR approval_status = ''
			 	OR LOWER(approval_status) IN ('approved','pending')
			 )
			 LIMIT 1",
			['rid' => $id]
		) ?: [];
		$alreadyPaid = (float)($paidRows[0]->paid ?? 0);
		$isSettled = $requiredAmount > 0.00001 && $alreadyPaid >= ($requiredAmount - 0.00001);
		if (!$isSettled) {
			$cashpickup->update($id, [
				'approval_status' => 'pending',
				'approved_by' => null,
				'approved_at' => null,
				'transaction_status' => 'Pending payout',
				'denial_reason' => null,
				'updated_by' => $actorId,
				'date_updated' => date('Y-m-d H:i:s'),
			]);
			message_fail('Credit payout remains pending until the assigned user fully pays it out.');
			redirect($admin_route.'/'.$plugin_route.'/view/'.$id);
		}
	} catch (\Throwable $e) {
		message_fail('Credit payout remains pending until the assigned user fully pays it out.');
		redirect($admin_route.'/'.$plugin_route.'/view/'.$id);
	}
}

if ($side === 'pay' && $prevApproval !== 'approved') {
	try {
		$dbCheck = new \Core\Database;
		$receiveId = (int)($row->receive_id ?? 0);
		if ($receiveId > 0) {
			$recv = $dbCheck->query(
				"SELECT id, amount_involved
				 FROM cashpickups
				 WHERE (deleted IS NULL OR deleted = 0)
				 AND LOWER(transaction_side) = 'receive'
				 AND id = :id
				 LIMIT 1",
				['id' => $receiveId]
			);
			$receiveTotal = !empty($recv) ? (float)($recv[0]->amount_involved ?? 0) : 0.0;
			$approvedSumRow = $dbCheck->query(
				"SELECT COALESCE(SUM(COALESCE(amount_paid_out,0) + COALESCE(pickup_charge,0)),0) AS paid
				 FROM cashpickups
				 WHERE (deleted IS NULL OR deleted = 0)
				 AND LOWER(transaction_side) = 'pay'
				 AND receive_id = :rid
				 AND id != :this_id
				 AND (
					approval_status IS NULL
					OR approval_status = ''
					OR LOWER(approval_status) = 'approved'
				 )
				 AND transaction_status = 'Approved'
				 AND (confirmed_by IS NOT NULL AND confirmed_by != '')
				 AND COALESCE(amount_paid_out,0) > 0",
				['rid' => $receiveId, 'this_id' => $id]
			);
			$alreadyApproved = (float)($approvedSumRow[0]->paid ?? 0);
			$thisAmt = (float)($row->amount_paid_out ?? 0) + (float)($row->pickup_charge ?? 0);
			if (($alreadyApproved + $thisAmt) - $receiveTotal > 0.00001) {
				$sysReason = 'System denied: approving this payout would cause overage. Receive total: '.number_format((float)$receiveTotal,2).', already approved: '.number_format((float)$alreadyApproved,2).', this payout: '.number_format((float)$thisAmt,2).'.';
				$cashpickup->update($id, [
					'approval_status' => 'denied',
					'denial_reason' => $sysReason,
					'updated_by' => $actorId,
					'date_updated' => date('Y-m-d H:i:s'),
				]);
				message_fail('Payout denied automatically: would exceed receive total');
				redirect($admin_route.'/'.$plugin_route.'/view/'.$receiveId);
			}
		}
	} catch (\Throwable $e) {
		// ignore check failures and proceed
	}
}

// Enforce cashdrawer e-cash sufficiency for transfer payouts at approval time
// (prevents race conditions / negative balances if balance changed since request)
if ($side === 'pay' && $prevApproval !== 'approved') {
	$pm = strtolower(trim((string)($row->payment_method ?? '')));
	if ($pm === 'cash_ghs') {
		$pm = 'cash';
	} elseif (in_array($pm, ['transfer_telcos', 'transfer_bank'], true)) {
		$pm = 'transfer';
	}
	if ($pm === 'cash') {
		try {
			$drawerUserId = (int)($row->created_by ?? 0);
			$amt = (float)($row->amount_paid_out ?? 0);
			$receiveId = (int)($row->receive_id ?? 0);
			if ($drawerUserId <= 0 || $amt <= 0) {
				$cashpickup->update($id, [
					'approval_status' => 'denied',
					'denial_reason' => 'System denied: invalid cash payout configuration.',
					'updated_by' => $actorId,
					'date_updated' => date('Y-m-d H:i:s'),
				]);
				message_fail('Payout denied automatically: invalid cash payout configuration');
				redirect($admin_route.'/'.$plugin_route.'/view/'.$receiveId);
			}

			$bridgePath = realpath(__DIR__ . '/../../backoffice/helpers/cashdrawer-bridge.php');
			if (!$bridgePath || !file_exists($bridgePath)) {
				$cashpickup->update($id, [
					'approval_status' => 'denied',
					'denial_reason' => 'System denied: unable to locate cashdrawer helper for cash payout approval.',
					'updated_by' => $actorId,
					'date_updated' => date('Y-m-d H:i:s'),
				]);
				message_fail('Payout denied automatically: unable to validate cashdrawer balance');
				redirect($admin_route.'/'.$plugin_route.'/view/'.$receiveId);
			}

			require_once $bridgePath;
			\BackOfficeCashdrawer\init();
			if (!function_exists('BackOfficeCashdrawer\\checkCashDenominationSufficiencyCurrency')) {
				$cashpickup->update($id, [
					'approval_status' => 'denied',
					'denial_reason' => 'System denied: cashdrawer helper unavailable for cash payout approval.',
					'updated_by' => $actorId,
					'date_updated' => date('Y-m-d H:i:s'),
				]);
				message_fail('Payout denied automatically: unable to validate cashdrawer balance');
				redirect($admin_route.'/'.$plugin_route.'/view/'.$receiveId);
			}

			$denos = \BackOfficeCashdrawer\normalizeDenominations($row->denos_json ?? '[]', $amt, 'GHS');
			if (function_exists('BackOfficeCashdrawer\\checkCashDenominationCountSufficiencyCurrency')) {
				$countCheck = \BackOfficeCashdrawer\checkCashDenominationCountSufficiencyCurrency($drawerUserId, $denos, $amt, 'GHS');
				if (empty($countCheck['ok'])) {
					$shortageParts = [];
					foreach ((array)($countCheck['shortages'] ?? []) as $shortage) {
						$denom = (float)($shortage['denomination'] ?? 0);
						$missing = (int)($shortage['missing_count'] ?? 0);
						if ($denom <= 0 || $missing <= 0) {
							continue;
						}
						$shortageParts[] = 'GHS '.number_format($denom, 2).' x'.$missing;
					}
					$shortageText = !empty($shortageParts) ? implode(', ', $shortageParts) : 'requested mix is unavailable';
					$cashpickup->update($id, [
						'approval_status' => 'denied',
						'denial_reason' => 'System denied: insufficient cashdrawer notes for cash payout. Shortages: '.$shortageText,
						'updated_by' => $actorId,
						'date_updated' => date('Y-m-d H:i:s'),
					]);
					message_fail('Payout denied automatically: insufficient cashdrawer notes for cash payout. Shortages: '.$shortageText);
					redirect($admin_route.'/'.$plugin_route.'/view/'.$receiveId);
				}
			}
			$check = \BackOfficeCashdrawer\checkCashDenominationSufficiencyCurrency($drawerUserId, $denos, $amt, 'GHS');
			if (empty($check['ok'])) {
				$available = (float)($check['available'] ?? 0.0);
				$required = (float)($check['required'] ?? $amt);
				$shortfall = (float)($check['shortfall'] ?? max(0.0, $required - $available));
				$cashpickup->update($id, [
					'approval_status' => 'denied',
					'denial_reason' => 'System denied: insufficient cashdrawer balance for cash payout. Shortfall: GHS '.number_format($shortfall, 2).' (Available: GHS '.number_format($available, 2).' | Required: GHS '.number_format($required, 2).')',
					'updated_by' => $actorId,
					'date_updated' => date('Y-m-d H:i:s'),
				]);
				message_fail('Payout denied automatically: insufficient cashdrawer balance for cash payout. Shortfall: GHS '.number_format($shortfall, 2));
				redirect($admin_route.'/'.$plugin_route.'/view/'.$receiveId);
			}
		} catch (\Throwable $e) {
			$cashpickup->update($id, [
				'approval_status' => 'denied',
				'denial_reason' => 'System denied: unable to validate cashdrawer balance for cash payout at approval time.',
				'updated_by' => $actorId,
				'date_updated' => date('Y-m-d H:i:s'),
			]);
			message_fail('Payout denied automatically: unable to validate cashdrawer balance');
			redirect($admin_route.'/'.$plugin_route);
		}
	}
	if ($pm === 'transfer') {
		try {
			$agencyHelperPath = realpath(__DIR__ . '/../../agencybanking/helpers/cashdrawer-sync.php');
			if ($agencyHelperPath && file_exists($agencyHelperPath)) {
				require_once $agencyHelperPath;
			}
			$drawerUserId = (int)($row->created_by ?? 0);
			$channel = strtoupper(trim((string)($row->payout_channel ?? '')));
			$amt = (float)($row->amount_paid_out ?? 0);
			if ($drawerUserId <= 0 || $channel === '' || $amt <= 0) {
				$sysReason = 'System denied: invalid transfer payout configuration.';
				$cashpickup->update($id, [
					'approval_status' => 'denied',
					'denial_reason' => $sysReason,
					'updated_by' => $actorId,
					'date_updated' => date('Y-m-d H:i:s'),
				]);
				message_fail('Payout denied automatically: invalid transfer payout configuration');
				redirect($admin_route.'/'.$plugin_route);
			}

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

			if ($drawerId <= 0 || $ownerId <= 0) {
				$sysReason = 'System denied: no active cashdrawer found for payout owner.';
				$cashpickup->update($id, [
					'approval_status' => 'denied',
					'denial_reason' => $sysReason,
					'updated_by' => $actorId,
					'date_updated' => date('Y-m-d H:i:s'),
				]);
				message_fail('Payout denied automatically: no active cashdrawer found for payout owner');
				redirect($admin_route.'/'.$plugin_route);
			}

			$dbBal = new \Core\Database;
			if (!$dbBal->table_exists(['drawer_balances'])) {
				$sysReason = 'System denied: cashdrawer balances table missing.';
				$cashpickup->update($id, [
					'approval_status' => 'denied',
					'denial_reason' => $sysReason,
					'updated_by' => $actorId,
					'date_updated' => date('Y-m-d H:i:s'),
				]);
				message_fail('Payout denied automatically: cashdrawer balances table missing');
				redirect($admin_route.'/'.$plugin_route);
			}

			$balRow = $dbBal->query(
				"SELECT closing_balance
				 FROM drawer_balances
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
			$available = !empty($balRow) ? (float)($balRow[0]->closing_balance ?? 0) : 0.0;
			if ($available <= 0.0 && function_exists('Agencybankings\\agency_get_shared_ecash_balance')) {
				$sharedAvailable = (float)\Agencybankings\agency_get_shared_ecash_balance($channel, $channel);
				if ($sharedAvailable > 0) {
					$available = $sharedAvailable;
				}
			}
			if (($amt - $available) > 0.009) {
				$shortfall = max(0.0, $amt - $available);
				$sysReason = 'System denied: insufficient cashdrawer balance for '.$channel.'. Shortfall: GH₵ '.number_format($shortfall, 2).' (Available: GH₵ '.number_format($available, 2).' | Required: GH₵ '.number_format($amt, 2).')';
				$cashpickup->update($id, [
					'approval_status' => 'denied',
					'denial_reason' => $sysReason,
					'updated_by' => $actorId,
					'date_updated' => date('Y-m-d H:i:s'),
				]);
				message_fail('Payout denied automatically: insufficient cashdrawer balance for transfer payout. Shortfall: GH₵ '.number_format($shortfall, 2));
				redirect($admin_route.'/'.$plugin_route.'/view/'.(int)($row->receive_id ?? 0));
			}
		} catch (\Throwable $e) {
			// If we cannot validate, fail closed to avoid negative balances
			$sysReason = 'System denied: unable to validate cashdrawer balance for transfer payout at approval time.';
			$cashpickup->update($id, [
				'approval_status' => 'denied',
				'denial_reason' => $sysReason,
				'updated_by' => $actorId,
				'date_updated' => date('Y-m-d H:i:s'),
			]);
			message_fail('Payout denied automatically: unable to validate cashdrawer balance');
			redirect($admin_route.'/'.$plugin_route);
		}
	}
}

$cashpickup->update($id, [
	'approval_status' => 'approved',
	'approved_by' => $actorId,
	'approved_at' => date('Y-m-d H:i:s'),
	'denial_reason' => null,
	'updated_by' => $actorId,
	'date_updated' => date('Y-m-d H:i:s'),
]);
if ($cashpickup->has_error) {
	error_log('[cashpickup] Approval update failed for transaction ' . $id . ': ' . $cashpickup->error);
	message_fail('Unable to approve this cashpickup. Please try again.');
	redirect($admin_route.'/'.$plugin_route);
}

$approvedRow = $cashpickup->first(['id' => $id]);
if (empty($approvedRow) || strtolower(trim((string)($approvedRow->approval_status ?? ''))) !== 'approved') {
	message_fail('Cashpickup approval was not saved. Please refresh and try again.');
	redirect($admin_route.'/'.$plugin_route);
}

// If a payout was created as pending, apply cashdrawer updates at approval time
if ($side === 'pay' && $prevApproval !== 'approved') {
	try {
		$drawerUserId = (int)($row->created_by ?? 0);
		if ($drawerUserId > 0) {
			$pm = strtolower(trim((string)($row->payment_method ?? '')));
			if ($pm === 'cash_ghs') {
				$pm = 'cash';
			} elseif (in_array($pm, ['transfer_telcos', 'transfer_bank'], true)) {
				$pm = 'transfer';
			}
			$transId = (int)($row->id ?? 0);
			$bridgePath = realpath(__DIR__ . '/../../backoffice/helpers/cashdrawer-bridge.php');
			if ($bridgePath && file_exists($bridgePath)) {
				require_once $bridgePath;
				\BackOfficeCashdrawer\init();
				if ($pm === 'cash') {
					$denos = \BackOfficeCashdrawer\normalizeDenominations($row->denos_json ?? '[]', (float)($row->amount_paid_out ?? 0), 'GHS');
					$movement = \BackOfficeCashdrawer\syncCashMovement((int)$drawerUserId, $denos, (float)($row->amount_paid_out ?? 0), 'Approved', $transId, 'out');
					if ($movement === null) {
						$cashpickup->update($id, [
							'approval_status' => 'denied',
							'denial_reason' => 'System denied: cashdrawer sync failed while applying cash payout approval.',
							'updated_by' => $actorId,
							'date_updated' => date('Y-m-d H:i:s'),
						]);
						message_fail('Payout denied automatically: unable to apply cashdrawer deduction.');
						redirect($admin_route.'/'.$plugin_route.'/view/'.(int)($row->receive_id ?? 0));
					}
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
								$sharedOpening = 0.0;
								if (function_exists('Agencybankings\\agency_get_shared_ecash_balance')) {
									$sharedOpening = (float)\Agencybankings\agency_get_shared_ecash_balance($channel, $channel);
								}
								$writeKey = $channel;
								if (function_exists('Agencybankings\\agency_find_existing_ecash_channel_key')) {
									$existingKey = \Agencybankings\agency_find_existing_ecash_channel_key($drawerId, $ownerId, $writeKey);
									if (!empty($existingKey)) {
										$writeKey = (string)$existingKey;
									}
								}
								if ($writeKey === $channel && function_exists('Agencybankings\\agency_resolve_ecash_channel_key')) {
									$resolved = \Agencybankings\agency_resolve_ecash_channel_key($drawerId, $ownerId, $channel);
									if (!empty($resolved)) {
										$writeKey = (string)$resolved;
									}
								}
								$seedOpening = null;
								if (function_exists('Agencybankings\\agency_drawer_has_ecash_channel')) {
									$hasChannel = \Agencybankings\agency_drawer_has_ecash_channel($drawerId, $ownerId, $writeKey);
									if (!$hasChannel && $sharedOpening > 0) {
										$seedOpening = $sharedOpening;
									}
								}
								if (function_exists('Agencybankings\\agency_refresh_ecash_channel')) {
									\Agencybankings\agency_refresh_ecash_channel($drawerId, $ownerId, $writeKey, 'out', $amt, $transId, 'Approved', $seedOpening);
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

// If a receive cashpickup was created as pending, apply cashdrawer IN updates at approval time
if ($side === 'receive' && $prevApproval !== 'approved') {
	try {
		$drawerUserId = (int)($row->created_by ?? 0);
		if ($drawerUserId > 0) {
			$src = strtolower(trim((string)($row->source ?? '')));
			$transId = (int)($row->id ?? 0);
			$amt = (float)($row->amount_involved ?? 0);

			// Cash IN (denominations)
			try {
				$bridgePath = realpath(__DIR__ . '/../../backoffice/helpers/cashdrawer-bridge.php');
				if ($bridgePath && file_exists($bridgePath)) {
					require_once $bridgePath;
					\BackOfficeCashdrawer\init();
					if ($src === 'cash' || $src === '') {
						$denos = \BackOfficeCashdrawer\normalizeDenominations($row->denos_json ?? '[]', $amt, 'GHS');
						\BackOfficeCashdrawer\syncCashMovement((int)$drawerUserId, $denos, $amt, 'Approved', $transId, 'in');
					}
				}
			} catch (\Throwable $e) {
				// fail silently
			}

			// E-cash/bank IN (drawer_balances)
			try {
				if (in_array($src, ['mtn','telecel','at','bank'], true) && $amt > 0) {
					$chanKey = '';
					if (in_array($src, ['mtn','telecel','at'], true)) {
						$chanKey = strtoupper($src);
					} elseif ($src === 'bank') {
						$chanKey = trim((string)($row->account_paid_to ?? ''));
						$chanKey = preg_replace('/\s+/', ' ', $chanKey);
					}
					if ($chanKey !== '') {
						$dbOwner = new \Core\Database;
						$today = date('Y-m-d');
						$r = $dbOwner->query("SELECT id, user_id FROM cashdrawers WHERE user_id = :user_id AND DATE(date_created) = :today ORDER BY id DESC LIMIT 1", ['user_id' => $drawerUserId, 'today' => $today]);
						if (empty($r)) {
							$r = $dbOwner->query("SELECT id, user_id FROM cashdrawers WHERE user_id = :user_id ORDER BY date_created DESC, id DESC LIMIT 1", ['user_id' => $drawerUserId]);
						}
						$drawerId = !empty($r) ? (int)($r[0]->id ?? 0) : 0;
						$ownerId = !empty($r) ? (int)($r[0]->user_id ?? 0) : 0;
						if ($drawerId > 0 && $ownerId > 0) {
							$dbBal = new \Core\Database;
							if ($dbBal->table_exists(['drawer_balances'])) {
								$now = date('Y-m-d H:i:s');
								$transStatus = 'Approved';
								if ($src === 'bank') {
									$normalizeSlug = function(string $v): string {
										$v = strtoupper(trim($v));
										$v = preg_replace('/[^A-Z0-9]+/i', '_', $v);
										$v = trim($v, '_');
										return $v;
									};
									$needSlug = $normalizeSlug($chanKey);
									$needCompact = str_replace('_', '', $needSlug);
									$resolvedExisting = '';
									try {
										$existingKeys = $dbBal->query(
											"SELECT channel_key
											 FROM drawer_balances
											 WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash'
											 ORDER BY id DESC",
											['drawer_id' => $drawerId, 'user_id' => $ownerId]
										);
										if (!empty($existingKeys)) {
											foreach ($existingKeys as $ek) {
												$kRaw = trim((string)($ek->channel_key ?? ''));
												if ($kRaw === '') continue;
												$k = strtoupper($kRaw);
												$candidate = $k;
												if (strpos($candidate, 'ECASH_') === 0) {
													$candidate = substr($candidate, 6);
												}
												$candidate = $normalizeSlug($candidate);
												$compact = str_replace('_', '', $candidate);
												if ($compact !== '' && $compact === $needCompact) {
													$resolvedExisting = $kRaw;
													break;
												}
											}
										}
									} catch (\Throwable $e) {}
									if ($resolvedExisting !== '') {
										$chanKey = $resolvedExisting;
									} else {
										$chanKey = 'ECASH_' . $needSlug;
									}
								}
								$lookupKey = (string)$chanKey;
								$altLookupKey = $lookupKey;
								if (stripos($lookupKey, 'ECASH_') === 0) {
									$altLookupKey = substr($lookupKey, 6);
								} else {
									$altLookupKey = 'ECASH_' . $lookupKey;
								}
								$rowsEcash = $dbBal->query(
									"SELECT * FROM drawer_balances
									 WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash'
									 AND (channel_key = :channel_key OR channel_key = :ecash_channel_key)
									 ORDER BY id DESC LIMIT 1",
									[
										'drawer_id' => $drawerId,
										'user_id' => $ownerId,
										'channel_key' => $lookupKey,
										'ecash_channel_key' => $altLookupKey,
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
											'channel_key' => $chanKey,
											'opening' => $opening,
											'credit' => $amt,
											'debit' => 0.0,
											'closing' => $closing,
											'date_created' => $now,
											'trans_id' => $transId,
											'trans_status' => $transStatus,
										]
									);
								} else {
									$rowEcash = $rowsEcash[0];
									$opening = (float)($rowEcash->opening_balance ?? 0);
									$newCredit = (float)($rowEcash->credit ?? 0) + $amt;
									$newDebit = (float)($rowEcash->debit ?? 0);
									$closing = $opening + $newCredit - $newDebit;
									$dbBal->query(
										"UPDATE drawer_balances
										 SET channel_key = :channel_key, credit = :credit, debit = :debit, closing_balance = :closing
										 WHERE id = :id",
										[
											'channel_key' => $chanKey,
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
			} catch (\Throwable $e) {
				// fail silently
			}
		}
	} catch (\Throwable $e) {
		// fail silently
	}
}

if ($side === 'pay') {
	$linkReceiveId = (int)($row->receive_id ?? 0);
	$linkCreditMode = strtolower(trim((string)($row->credit_mode ?? '')));
	if ($linkReceiveId > 0 && $linkCreditMode === 'credit') {
		$cashpickup->update($linkReceiveId, [
			'approval_status' => 'approved',
			'approved_by' => $actorId,
			'approved_at' => date('Y-m-d H:i:s'),
			'transaction_status' => 'Approved',
			'denial_reason' => null,
			'updated_by' => $actorId,
			'date_updated' => date('Y-m-d H:i:s'),
		]);
	}
}

message_success('Cashpickup approved');
redirect($admin_route.'/'.$plugin_route);

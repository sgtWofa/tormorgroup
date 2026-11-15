<?php

require_once plugin_path('helpers/cashdrawer-sync.php');

$agencybanking = new \Agencybankings\Agencybanking;

if(!empty($row))
{
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$session = get_session();
	$currentUserId = (int)($session->id ?? 0);
	$rowOwnerId = (int)($row->created_by ?? 0);
	$canDelete = user_can('delete_agencybanking') || ($currentUserId > 0 && $rowOwnerId === $currentUserId);
	if (!$canDelete) {
		message_fail('Access denied');
		redirect($admin_route.'/'.$plugin_route);
	}
	
	$postdata = $req->post();

	$csrf = csrf_verify($postdata);
	if($csrf)
	{
 		if($canDelete)
		{
			$transId = (int)($row->id ?? 0);
			$currencyArray = [];
			$affectedCashDrawers = [];
			$ecashAdjustments = [];
			$ecashMeta = null;
			$ecashAmount = 0.0;

			if ($transId > 0) {
				// Pull saved denominations for accurate reversal
				try {
					$denoModel = new \Agencybankings\Deno;
					$denoRows = $denoModel->where(['trans_id' => $transId]) ?: [];
					if (!empty($denoRows)) {
						foreach ($denoRows as $d) {
							$currencyArray[] = [
								'currency' => $d->currency ?? 'GHS',
								'denomination' => (float)($d->denomination ?? 0),
								'count' => (int)($d->count ?? 0),
								'volume' => (float)($d->volume ?? 0),
							];
						}
					}
				} catch (\Throwable $e) {
					$currencyArray = [];
				}

				try {
					$ecashMeta = \Agencybankings\agency_determine_ecash_channel((array)$row);
					$cashAmount = \Agencybankings\agency_resolve_transaction_amount((array)$row, $currencyArray);
					$ecashAmount = \Agencybankings\agency_resolve_ecash_amount((array)$row, (float)$cashAmount);
				} catch (\Throwable $e) {
					$ecashMeta = null;
					$ecashAmount = 0.0;
				}

				// Identify drawers affected by cash denominations for refresh
				try {
					$db = new \Core\Database;
					if ($db->table_exists(['drawer_denos'])) {
						$cashRows = $db->query(
							"SELECT DISTINCT drawer_id, user_id FROM drawer_denos WHERE trans_id = :trans_id",
							['trans_id' => $transId]
						) ?: [];
						foreach ($cashRows as $r) {
							$drawerId = (int)($r->drawer_id ?? 0);
							$drawerUserId = (int)($r->user_id ?? 0);
							if ($drawerId > 0 && $drawerUserId > 0) {
								$affectedCashDrawers[$drawerId . ':' . $drawerUserId] = ['drawer_id' => $drawerId, 'drawer_user_id' => $drawerUserId];
							}
						}
					}
				} catch (\Throwable $e) {
					$affectedCashDrawers = [];
				}

				// Prepare ecash adjustments before deletion removes any rows
				if (!empty($ecashMeta) && $ecashAmount > 0) {
					$targets = [];
					try {
						$ownerId = (int)($row->created_by ?? 0);
						if ($ownerId > 0) {
							$ownerCtx = \Agencybankings\agency_find_active_drawer($ownerId);
							if (!empty($ownerCtx)) {
								$targets[$ownerCtx['drawer_id'] . ':' . $ownerCtx['drawer_user_id']] = $ownerCtx;
							}
						}
						$adminManagerDrawers = \Agencybankings\agency_fetch_admin_manager_drawers_today();
						if (!empty($adminManagerDrawers)) {
							foreach ($adminManagerDrawers as $ctx) {
								$drawerId = (int)($ctx['drawer_id'] ?? 0);
								$drawerUserId = (int)($ctx['drawer_user_id'] ?? 0);
								if ($drawerId > 0 && $drawerUserId > 0) {
									$targets[$drawerId . ':' . $drawerUserId] = ['drawer_id' => $drawerId, 'drawer_user_id' => $drawerUserId];
								}
							}
						}
					} catch (\Throwable $e) {
						$targets = [];
					}

					if (!empty($targets)) {
						$db = new \Core\Database;
						foreach ($targets as $ctx) {
							$drawerId = (int)($ctx['drawer_id'] ?? 0);
							$drawerUserId = (int)($ctx['drawer_user_id'] ?? 0);
							if ($drawerId <= 0 || $drawerUserId <= 0) {
								continue;
							}
							$baseChannelKey = (string)($ecashMeta['channel_key'] ?? '');
							$rawNameForResolve = (string)($ecashMeta['channel_name_raw'] ?? '');
							$writeKey = $baseChannelKey;
							$existingKey = $writeKey !== '' ? \Agencybankings\agency_find_existing_ecash_channel_key($drawerId, $drawerUserId, $writeKey) : null;
							if (!empty($existingKey)) {
								$writeKey = (string)$existingKey;
							} else {
								$resolved = \Agencybankings\agency_resolve_ecash_channel_key($drawerId, $drawerUserId, $rawNameForResolve);
								if (!empty($resolved)) {
									$writeKey = (string)$resolved;
								}
							}
							if ($writeKey === '' || !$db->table_exists(['drawer_balances'])) {
								continue;
							}

							$rowsBal = $db->query(
								"SELECT id, opening_balance, credit, debit FROM drawer_balances
								 WHERE drawer_id = :drawer_id AND user_id = :user_id
								   AND channel_type = 'ecash' AND channel_key = :channel_key
								 ORDER BY id DESC LIMIT 1",
								[
									'drawer_id' => $drawerId,
									'user_id' => $drawerUserId,
									'channel_key' => $writeKey,
								]
							) ?: [];
							if (empty($rowsBal)) {
								continue;
							}
							$rowBal = $rowsBal[0];
							$opening = (float)($rowBal->opening_balance ?? 0);
							$credit = (float)($rowBal->credit ?? 0);
							$debit = (float)($rowBal->debit ?? 0);
							$direction = strtolower((string)($ecashMeta['direction'] ?? ''));
							if ($direction === 'in') {
								$credit = max(0.0, $credit - (float)$ecashAmount);
							} else {
								$debit = max(0.0, $debit - (float)$ecashAmount);
							}
							$closing = $opening + $credit - $debit;

							$ecashAdjustments[] = [
								'drawer_id' => $drawerId,
								'drawer_user_id' => $drawerUserId,
								'channel_key' => $writeKey,
								'opening' => $opening,
								'credit' => $credit,
								'debit' => $debit,
								'closing' => $closing,
							];
						}
					}
				}

				// Remove cashdrawer entries linked to this transaction
				try {
					\Agencybankings\agency_remove_cashdrawer_entries($transId);
					\Agencybankings\agency_remove_drawer_balance_entries($transId);
				} catch (\Throwable $e) {
					// do not block deletion
				}

				// Refresh cash channel totals for affected drawers
				if (!empty($affectedCashDrawers)) {
					foreach ($affectedCashDrawers as $ctx) {
						$drawerId = (int)($ctx['drawer_id'] ?? 0);
						$drawerUserId = (int)($ctx['drawer_user_id'] ?? 0);
						if ($drawerId > 0 && $drawerUserId > 0) {
							\Agencybankings\agency_refresh_cash_channel($drawerId, $drawerUserId, $transId, 'Reversed');
						}
					}
				}

				// Apply ecash reversal to shared channels
				if (!empty($ecashAdjustments)) {
					try {
						$db = new \Core\Database;
						$now = date('Y-m-d H:i:s');
						foreach ($ecashAdjustments as $adj) {
							$drawerId = (int)($adj['drawer_id'] ?? 0);
							$drawerUserId = (int)($adj['drawer_user_id'] ?? 0);
							$channelKey = (string)($adj['channel_key'] ?? '');
							if ($drawerId <= 0 || $drawerUserId <= 0 || $channelKey === '') {
								continue;
							}
							$existing = $db->query(
								"SELECT id FROM drawer_balances
								 WHERE drawer_id = :drawer_id AND user_id = :user_id
								   AND channel_type = 'ecash' AND channel_key = :channel_key
								 ORDER BY id DESC LIMIT 1",
								[
									'drawer_id' => $drawerId,
									'user_id' => $drawerUserId,
									'channel_key' => $channelKey,
								]
							) ?: [];
							if (empty($existing)) {
								$db->query(
									"INSERT INTO drawer_balances (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status)
									 VALUES (:drawer_id, :user_id, 'ecash', :channel_key, :opening, :credit, :debit, :closing, :date_created, :trans_id, :trans_status)",
									[
										'drawer_id' => $drawerId,
										'user_id' => $drawerUserId,
										'channel_key' => $channelKey,
										'opening' => (float)($adj['opening'] ?? 0),
										'credit' => (float)($adj['credit'] ?? 0),
										'debit' => (float)($adj['debit'] ?? 0),
										'closing' => (float)($adj['closing'] ?? 0),
										'date_created' => $now,
										'trans_id' => $transId,
										'trans_status' => 'Reversed',
									]
								);
							} else {
								$db->query(
									"UPDATE drawer_balances
									 SET credit = :credit, debit = :debit, closing_balance = :closing, trans_id = :trans_id, trans_status = :trans_status
									 WHERE id = :id",
									[
										'credit' => (float)($adj['credit'] ?? 0),
										'debit' => (float)($adj['debit'] ?? 0),
										'closing' => (float)($adj['closing'] ?? 0),
										'trans_id' => $transId,
										'trans_status' => 'Reversed',
										'id' => (int)($existing[0]->id ?? 0),
									]
								);
							}
						}
					} catch (\Throwable $e) {
						// do not block deletion
					}
				}
			}

			//log changes in the log table
			$session = get_session();

			if(!empty($session->id)){
				$username = $session->first_name . ' '. $session->last_name;
				$user_id = $session->id;
			}else{
				$username = 'Unknown user';
			}

			$action 		= 'deleted';
			$table 			= 'agencybankings';
			$Oldagencybanking 		= $row->network . ' ' . $row->transaction_type;
			$transaction_id 		= $row->id;
			$table 			= 'agencybankings';
			$description 	= "$username $action transaction $Oldagencybanking with id $transaction_id from $table";
			$date_deleted 	= date("Y-m-d H:i:s");

		    $log = new \Agencybankings\Log;
		    $log_activity = $log->insert([
			 	'table_name'=>$table,
			 	'action'=>$action,
			 	'user_id'=>$user_id,
			 	'user_name'=>$username,
			 	'description'=>$description,
			 	'date_deleted'=>$date_deleted,
			 ]);

	 		$agencybanking->delete($row->id);
 
			message_success("Record deleted successfully!");
			redirect($admin_route.'/'.$plugin_route);
		}
	}

	$agencybanking->errors['network'] = "Form expired!";

	set_value('errors',$agencybanking->errors);
}else{

	message_fail("Record not found");
}

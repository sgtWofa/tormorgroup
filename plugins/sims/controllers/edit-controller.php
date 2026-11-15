<?php
require_once plugin_path('helpers/transactions-helper.php');
sim_ensure_runtime_columns();

if(!empty($row))
{
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$postdata = $req->post();
	$postdata['id'] = $row->id;

	$imageColumnExists = false;
	try {
		$db = new \Core\Database;
		$column = $db->query("SHOW COLUMNS FROM sims LIKE 'image'");
		$imageColumnExists = !empty($column);
	} catch (\Throwable $th) {
		$imageColumnExists = false;
	}

	$uploadedImage = '';
	$imageFile = $req->files('image');
	if (is_array($imageFile) && !empty($imageFile['name'])) {
		$originalName = basename(str_replace('\\', '/', (string)$imageFile['name']));
		$error = $imageFile['error'] ?? UPLOAD_ERR_NO_FILE;
		if ($error === UPLOAD_ERR_OK) {
			$type = $imageFile['type'] ?? '';
			if (!in_array($type, $req->upload_file_types, true)) {
				$req->upload_errors['image'] = 'The uploaded SIM image has an invalid file type.';
			} else {
				$size = (int)($imageFile['size'] ?? 0);
				if ($size > ($req->upload_max_size * 1024 * 1024)) {
					$req->upload_errors['image'] = 'The uploaded SIM image exceeds the maximum allowed size.';
				} else {
					$tmpName = $imageFile['tmp_name'] ?? '';
					if (!is_uploaded_file($tmpName)) {
						$req->upload_errors['image'] = 'The uploaded SIM image could not be processed.';
					} else {
						$folder = trim($req->upload_folder, '/') . '/';
						$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
						$baseName = strtolower(pathinfo($originalName, PATHINFO_FILENAME));
						$baseName = preg_replace('/[^a-z0-9_-]+/', '-', $baseName);
						$baseName = trim((string)$baseName, '-_');
						if ($baseName === '') {
							$baseName = 'sim-image';
						}
						if ($extension === '') {
							$extension = 'jpg';
						}
						$safeName = $baseName . '-' . date('YmdHis') . '-' . rand(1000, 9999) . '.' . $extension;
						$destination = $folder . $safeName;
						$num = 0;
						while (file_exists($destination) && $num < 10) {
							$num++;
							$destination = preg_replace('/\.[^.]+$/', '_' . $num . '.' . $extension, $destination);
						}
						if (!is_dir($folder)) {
							mkdir($folder, 0777, true);
						}
						if (!move_uploaded_file($tmpName, $destination)) {
							$req->upload_errors['image'] = 'The uploaded SIM image could not be saved.';
						} else {
							$uploadedImage = $destination;
						}
					}
				}
			}
		} elseif ($error !== UPLOAD_ERR_NO_FILE) {
			$req->upload_errors['image'] = 'The uploaded SIM image could not be uploaded.';
		}
	}

	if (!empty($req->upload_errors)) {
		$sim->errors['image'] = implode(' ', array_values($req->upload_errors));
	}

	if(!user_can('edit_sim')){
		message_fail('Access denied');
		redirect($admin_route.'/'.$plugin_route);
	}

	foreach (['network','puk','serial_number','number_assigned','assigned','buyer_phone','cost','wholesale_price','retail_price','amount','commission','denos'] as $field) {
		if(!array_key_exists($field, $postdata)){
			$postdata[$field] = $row->{$field} ?? '';
		}
	}

	$previousStatus = sim_normalize_status($row->transaction_status ?? '', 'Unassigned');
	$normalizedStatus = sim_normalize_status($postdata['transaction_status'] ?? $previousStatus, $previousStatus);
	$postdata['transaction_status'] = $normalizedStatus;
	$previousHasAccountingEffect = sim_status_has_accounting_effect($previousStatus);
	$willBeDeclinedOrRejected = sim_status_is_reversed($normalizedStatus);
	$isAlreadyDeclinedOrRejected = sim_status_is_reversed($previousStatus);
	$rejectionReason = trim((string)($postdata['rejection_reason'] ?? ''));
	$statusChangedToApproved = (sim_status_has_accounting_effect($normalizedStatus) && !sim_status_has_accounting_effect($previousStatus));

	$csrf = csrf_verify($postdata);

	if($csrf && $isAlreadyDeclinedOrRejected){
		$sim->errors['transaction_status'] = 'Declined or rejected SIM transactions can no longer be edited.';
		set_value('errors', array_merge($sim->errors, $req->upload_errors));
		return;
	}

	if($csrf && $willBeDeclinedOrRejected && $rejectionReason === ''){
		$sim->errors['rejection_reason'] = 'Rejection reason is required when declining or rejecting a SIM transaction.';
		set_value('errors', array_merge($sim->errors, $req->upload_errors));
		return;
	}

	if($csrf && $previousHasAccountingEffect)
	{
		if(!$willBeDeclinedOrRejected){
			$sim->errors['transaction_status'] = 'Approved or sold SIM transactions can only be changed to Declined or Rejected.';
			set_value('errors', array_merge($sim->errors, $req->upload_errors));
			return;
		}

		$postdata['date_updated'] = date("Y-m-d H:i:s");
		$session = get_session();

		if(!empty($session->id)){
			$username = $session->first_name . ' '. $session->last_name;
			$user_id = $session->id;
		}else{
			$username = $row->first_name . ' '. $row->last_name;
			$user_id = $row->id;
		}

		$reverseTransaction = clone $row;
		$reverseTransaction->updated_by = $user_id;
		$reverseTransaction->transaction_status = $normalizedStatus;
		$reverseResult = sim_reverse_approved_effects($reverseTransaction, $normalizedStatus);
		if(empty($reverseResult['ok'])){
			$sim->errors['transaction_status'] = $reverseResult['message'] ?: 'Unable to reverse the SIM transaction.';
			set_value('errors', array_merge($sim->errors, $req->upload_errors));
			return;
		}

		$updateData = [
			'transaction_status' => $normalizedStatus,
			'rejection_reason' => $rejectionReason,
			'updated_by' => $user_id,
			'date_updated' => $postdata['date_updated'],
		];
		$sim->update($row->id, $updateData);
		if(!empty($sim->has_error)){
			$sim->errors['transaction_status'] = !empty($sim->error) ? $sim->error : 'Unable to save the declined/rejected SIM status.';
			set_value('errors', array_merge($sim->errors, $req->upload_errors));
			return;
		}

		$action = strtolower($normalizedStatus);
		$transaction_id = $row->id;
		$serial_number = $row->serial_number ?? '';
		$table = 'sims';
		$description = "$username $action SIM transaction with Serial Number: $serial_number and id $transaction_id";
		$date_created = date("Y-m-d H:i:s");

		$log = new \Sims\Log;
		$log->insert([
			'table_name'=>$table,
			'action'=>$action,
			'user_id'=>$user_id,
			'user_name'=>$username,
			'description'=>$description,
			'date_created'=>$date_created,
		]);

		message_success("Transaction with id ($transaction_id) {$action} successfully and its cashdrawer impact reversed!");
		redirect($admin_route.'/'.$plugin_route);
	}

	if($csrf && $sim->validate_update($postdata))
	{

        if ($postdata['transaction_status'] === 'Assigned' && empty($postdata['assigned'])) {
               $sim->errors['assigned'] = "Incharge is required for assigned sims!";
               set_value('errors', $sim->errors);
        }else
		if(user_can('edit_sim'))
		{
            //dd($postdata); die;
			$postdata['date_updated'] = date("Y-m-d H:i:s");

			//log changes in the log table
			$session = get_session();

			if(!empty($session->id)){
				$username = $session->first_name . ' '. $session->last_name;
				$user_id = $session->id;
			}else{
				$username = $row->first_name . ' '. $row->last_name;
				$user_id = $row->id;
			}

			$postdata['updated_by'] = $user_id;

			if ($imageColumnExists && !empty($uploadedImage)) {
				$postdata['image'] = $uploadedImage;
			}

            // Cashdrawer is now the central accounting tracker - wallet operations removed

			//remove assigned if status is unassigned
			if ($postdata['transaction_status'] === "Unassigned") {                
                $postdata['assigned'] = '';
            }
			$postdata['rejection_reason'] = $willBeDeclinedOrRejected ? $rejectionReason : '';

			// Update cashdrawer when status changes to Approved - Mirror denominations then recompute
			if ($statusChangedToApproved) {
				$drawerOwnerId = (int)($row->created_by ?? $user_id ?? 0);
				if ($drawerOwnerId <= 0) {
					$drawerOwnerId = (int)($session->id ?? 0);
				}
				$drawer_id = 0;
				if ($drawerOwnerId > 0) {
					try {
						$drawerDb = new \Core\Database;
						$today = date('Y-m-d');
						$sqlDrawer = "SELECT id FROM cashdrawers WHERE user_id = :user_id AND DATE(date_created) = :today ORDER BY id DESC LIMIT 1";
						$drawerRow = $drawerDb->query($sqlDrawer, ['user_id' => $drawerOwnerId, 'today' => $today]);
						if (empty($drawerRow)) {
							$sqlDrawer = "SELECT id FROM cashdrawers WHERE user_id = :user_id ORDER BY date_created DESC, id DESC LIMIT 1";
							$drawerRow = $drawerDb->query($sqlDrawer, ['user_id' => $drawerOwnerId]);
						}
						if (!empty($drawerRow)) {
							$drawer_id = (int)$drawerRow[0]->id;
						}
					} catch (\Throwable $e) {
						$drawer_id = 0;
					}
				}

				// Get denominations from row if available
				$currencyArray = [];
				if (!empty($row->denos)) {
					$currencyArray = json_decode($row->denos, true) ?? [];
				}

				// Mirror denominations to drawer_denos if we have them
				if ($drawer_id > 0 && !empty($currencyArray)) {
					try {
						$dbDrawerDeno = new \Core\Database;
						if ($dbDrawerDeno->table_exists(['drawer_denos'])) {
							$drawerUserId = $drawerOwnerId;
							try {
								$userRow = $dbDrawerDeno->query("SELECT user_id FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawer_id]);
								if (!empty($userRow)) {
									$drawerUserId = (int)($userRow[0]->user_id ?? $drawerOwnerId);
								}
							} catch (\Throwable $e) {}

							// Sims is always a credit (sale/income)
							$sign = 1;

							foreach ($currencyArray as $denoRow) {
								$denCurrency = $denoRow['currency'] ?? 'GHS';
								if (strtoupper($denCurrency) !== 'GHS') {
									continue;
								}
								$trans_status = $postdata['transaction_status'] ?? 'Approved';
								$trans_id = $row->id;
								$sqlDeno = "INSERT INTO drawer_denos (drawer_id, user_id, denomination, count, volume, date_created, created_by, trans_id, trans_status) VALUES (:drawer_id, :user_id, :denomination, :count, :volume, :date_created, :created_by, :trans_id, :trans_status)";
								$paramsDeno = [
									'drawer_id' => $drawer_id,
									'user_id' => $drawerUserId,
									'denomination' => $denoRow['denomination'],
									'count' => $sign * (int)($denoRow['count'] ?? 0),
									'volume' => $sign * (float)($denoRow['volume'] ?? 0),
									'date_created' => $postdata['date_updated'],
									'created_by' => $drawerUserId,
									'trans_id' => $trans_id,
									'trans_status' => $trans_status,
								];
								$dbDrawerDeno->query($sqlDeno, $paramsDeno);
							}

							// Recompute CASH_DRAWER balance from denominations snapshot
							try {
								$sumRows = $dbDrawerDeno->query("SELECT SUM(volume) AS total_volume FROM drawer_denos WHERE drawer_id = :drawer_id AND user_id = :user_id", ['drawer_id' => $drawer_id, 'user_id' => $drawerUserId]);
								$currentClosing = !empty($sumRows) ? (float)($sumRows[0]->total_volume ?? 0) : 0.0;

								$dbBal = new \Core\Database;
								if ($dbBal->table_exists(['drawer_balances'])) {
									$rowsBal = $dbBal->query("SELECT * FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'cash' AND channel_key = 'CASH_DRAWER' LIMIT 1", ['drawer_id' => $drawer_id, 'user_id' => $drawerUserId]);
									$now = date('Y-m-d H:i:s');

									if (empty($rowsBal)) {
										$hdr = $dbBal->query("SELECT amount FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawer_id]);
										$opening = !empty($hdr) ? (float)($hdr[0]->amount ?? 0) : 0.0;
										$delta = $currentClosing - $opening;
										$credit = $delta > 0 ? $delta : 0;
										$debit = $delta < 0 ? -$delta : 0;

										$insertSql = "INSERT INTO drawer_balances (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status) VALUES (:drawer_id, :user_id, 'cash', 'CASH_DRAWER', :opening, :credit, :debit, :closing, :date_created, :trans_id, :trans_status)";
										$paramsBal = [
											'drawer_id' => $drawer_id,
											'user_id' => $drawerUserId,
											'opening' => $opening,
											'credit' => $credit,
											'debit' => $debit,
											'closing' => $currentClosing,
											'date_created' => $now,
											'trans_id' => $trans_id,
											'trans_status' => $trans_status,
										];
										$dbBal->query($insertSql, $paramsBal);
									} else {
										$rowBal = $rowsBal[0];
										$opening = (float)($rowBal->opening_balance ?? 0);
										$delta = $currentClosing - $opening;
										$credit = $delta > 0 ? $delta : 0;
										$debit = $delta < 0 ? -$delta : 0;

										$updateSql = "UPDATE drawer_balances SET credit = :credit, debit = :debit, closing_balance = :closing WHERE id = :id";
										$paramsBal = [
											'credit' => $credit,
											'debit' => $debit,
											'closing' => $currentClosing,
											'id' => $rowBal->id,
										];
										$dbBal->query($updateSql, $paramsBal);
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

				// Update e-cash channel if network is provided (Sims is credit/in)
				// For MTN, TELECEL, AT networks, consolidate to single channel (no ECASH_ prefix)
				if ($drawer_id > 0 && !empty($row->network)) {
					try {
						$dbBal = new \Core\Database;
						if ($dbBal->table_exists(['drawer_balances'])) {
							$drawerUserId = $drawerOwnerId;
							try {
								$userRow = $dbBal->query("SELECT user_id FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawer_id]);
								if (!empty($userRow)) {
									$drawerUserId = (int)($userRow[0]->user_id ?? $drawerOwnerId);
								}
							} catch (\Throwable $e) {}

							$networkUpper = strtoupper($row->network);
							$ecashChannelKey = in_array($networkUpper, ['MTN', 'TELECEL', 'AT']) ? $networkUpper : strtoupper(str_replace(' ', '_', $row->network));
							
							// For MTN, TELECEL, AT: check for both the direct name and ECASH_ prefixed version
							$rowsEcash = $dbBal->query("SELECT * FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash' AND (channel_key = :channel_key OR channel_key = :ecash_channel_key) LIMIT 1", ['drawer_id' => $drawer_id, 'user_id' => $drawerUserId, 'channel_key' => $ecashChannelKey, 'ecash_channel_key' => 'ECASH_' . $ecashChannelKey]);

							// Sims is always credit (e-cash in)
							$transactionAmount = (float)($row->amount ?? 0);
							$ecashCredit = $transactionAmount;
							$ecashDebit = 0;
							$now = date('Y-m-d H:i:s');

							if (empty($rowsEcash)) {
								$ecashOpening = 0;
								$ecashClosing = $ecashOpening + $ecashCredit - $ecashDebit;

								$insertEcashSql = "INSERT INTO drawer_balances (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status) VALUES (:drawer_id, :user_id, 'ecash', :channel_key, :opening, :credit, :debit, :closing, :date_created, :trans_id, :trans_status)";
								$paramsEcash = [
									'drawer_id' => $drawer_id,
									'user_id' => $drawerUserId,
									'channel_key' => $ecashChannelKey,
									'opening' => $ecashOpening,
									'credit' => $ecashCredit,
									'debit' => $ecashDebit,
									'closing' => $ecashClosing,
									'date_created' => $now,
									'trans_id' => $trans_id,
									'trans_status' => $trans_status,
								];
								$dbBal->query($insertEcashSql, $paramsEcash);
							} else {
								$rowEcash = $rowsEcash[0];
								$ecashOpening = (float)($rowEcash->opening_balance ?? 0);
								$ecashCredit = (float)($rowEcash->credit ?? 0) + $ecashCredit;
								$ecashDebit = (float)($rowEcash->debit ?? 0) + $ecashDebit;
								$ecashClosing = $ecashOpening + $ecashCredit - $ecashDebit;

								// If the old record had ECASH_ prefix, update it to use the new consolidated key
								$updateEcashSql = "UPDATE drawer_balances SET channel_key = :channel_key, credit = :credit, debit = :debit, closing_balance = :closing WHERE id = :id";
								$paramsEcash = [
									'channel_key' => $ecashChannelKey,
									'credit' => $ecashCredit,
									'debit' => $ecashDebit,
									'closing' => $ecashClosing,
									'id' => $rowEcash->id,
								];
								$dbBal->query($updateEcashSql, $paramsEcash);
							}
						}
					} catch (\Throwable $e) {
						// fail silently
					}
				}
			}

			unset($postdata['id']);
			$sim->update($row->id,$postdata);

            //log changes in the log table
            $action                      = 'edited';
            $serial_number         = $postdata['serial_number'];
            $assinged_number    = $postdata['number_assigned'];
            $transaction_id          = $row->id;
            $status                       = $postdata['transaction_status'];
            $transaction              = $postdata['network'] . "  sim  with Serial Number:". $serial_number;
            $table                        = 'sims';
            $description              = "$username $action a new $transaction";
			$date_created 	= date("Y-m-d H:i:s");

		    $log = new \Sims\Log;
		    $log_activity = $log->insert([
			 	'table_name'=>$table,
			 	'action'=>$action,
			 	'user_id'=>$user_id,
			 	'user_name'=>$username,
			 	'description'=>$description,
			 	'date_created'=>$date_created,
			 ]);

			message_success("Transaction with id ($transaction_id) edited successfully!");
			redirect($admin_route.'/'.$plugin_route);
		}
	}

	if(!$csrf)
		$sim->errors['usn'] = "Form expired!";

	set_value('errors',array_merge($sim->errors,$req->upload_errors));

}else{

	message_fail("Record not found");
}

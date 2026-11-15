<?php

$postdata = $req->post();
$row = $sim->first(['id'=>$postdata['id']]);
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$csrf = csrf_verify($postdata);

	if($csrf && $sim->validate_sell($postdata))
	{
		if(user_can('sell_sim'))
		{
			$postdata['date_updated'] = date("Y-m-d H:i:s");

			//log changes in the log table
			$session = get_session();

			if(!empty($session->id)){
				$username = $session->first_name . ' '. $session->last_name;
				$user_id = $session->id;
			}else{
				try {
					$username = $row->first_name . ' '. $row->last_name;
					$user_id = $row->id;
				} catch (\Throwable $e) {}
			}

			$postdata['updated_by'] = $user_id;

		// Calculate amount and commission based on transaction status
            if (($postdata['transaction_status'] == 'Sold - retail')) {                
                    $postdata['amount'] = $postdata['retail_price'];    
                    $postdata['commission'] =  $postdata['amount'] - $postdata['cost']; 
            }else
            if (($postdata['transaction_status'] == 'Sold - wholesale')) {
                $postdata['amount'] = $postdata['wholesale_price'];
                $postdata['commission'] =  $postdata['amount'] - $postdata['cost']; 
            }

            //remove assigned if status is unassigned
            if ($postdata['transaction_status'] === "Unassigned") {                
                $postdata['assigned'] = '';
            }

		// Sync with cashdrawer when sold (mirror denominations + cash balances)
		if (in_array($postdata['transaction_status'] ?? '', ['Sold - retail', 'Sold - wholesale'])) {
			try {
				$drawer_id = 0;
				$drawerDb = new \Core\Database;
				$today = date('Y-m-d');
				$sqlDrawer = "SELECT id FROM cashdrawers WHERE user_id = :user_id AND DATE(date_created) = :today ORDER BY id DESC LIMIT 1";
				$drawerRow = $drawerDb->query($sqlDrawer, ['user_id' => $user_id, 'today' => $today]);
				if (empty($drawerRow)) {
					$sqlDrawer = "SELECT id FROM cashdrawers WHERE user_id = :user_id ORDER BY date_created DESC, id DESC LIMIT 1";
					$drawerRow = $drawerDb->query($sqlDrawer, ['user_id' => $user_id]);
				}
				if (!empty($drawerRow)) {
					$drawer_id = (int)$drawerRow[0]->id;
				}

				// Update cashdrawer balances for sold sim (credit/income)
				if ($drawer_id > 0) {
					try {
						$dbBal = new \Core\Database;
						if ($dbBal->table_exists(['drawer_balances'])) {
							$drawerUserId = $user_id;
							try {
								$userRow = $dbBal->query("SELECT user_id FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawer_id]);
								if (!empty($userRow)) {
									$drawerUserId = (int)($userRow[0]->user_id ?? $user_id);
								}
							} catch (\Throwable $e) {}

							$transactionAmount = (float)($postdata['amount'] ?? 0);
							$denosPayload = json_decode($postdata['denos'] ?? '[]', true);
							if (!is_array($denosPayload)) {
								$denosPayload = [];
							}
							if (empty($denosPayload) && $transactionAmount > 0) {
								// Ensure denominations mirror exists even if cashier skips calculator
								$denosPayload[] = [
									'currency' => 'GHS',
									'denomination' => $transactionAmount,
									'count' => 1,
									'volume' => $transactionAmount,
								];
								$postdata['denos'] = json_encode($denosPayload);
							}
							$now = date('Y-m-d H:i:s');
							$trans_status = 'Approved';
							$trans_id = $row->id;

							// Mirror denominations into drawer_denos so physical volume/count increase
							if (!empty($denosPayload)) {
								$dbDrawerDeno = new \Core\Database;
								if ($dbDrawerDeno->table_exists(['drawer_denos'])) {
									foreach ($denosPayload as $denoRow) {
										$denomination = (float)($denoRow['denomination'] ?? 0);
										$count = (int)($denoRow['count'] ?? 0);
										$volume = (float)($denoRow['volume'] ?? 0);
										if ($volume <= 0) {
											continue;
										}
										$sqlDeno = "INSERT INTO drawer_denos (drawer_id, user_id, denomination, count, volume, date_created, created_by, trans_id, trans_status) VALUES (:drawer_id, :user_id, :denomination, :count, :volume, :date_created, :created_by, :trans_id, :trans_status)";
										$paramsDeno = [
											'drawer_id' => $drawer_id,
											'user_id' => $drawerUserId,
											'denomination' => $denomination,
											'count' => $count,
											'volume' => $volume,
											'date_created' => $now,
											'created_by' => $drawerUserId,
											'trans_id' => $trans_id,
											'trans_status' => $trans_status,
										];
										$dbDrawerDeno->query($sqlDeno, $paramsDeno);
									}
								}
							}

							// Recompute drawer balance snapshot from denominations
							$rowsBal = $dbBal->query("SELECT * FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'cash' AND channel_key = 'CASH_DRAWER' LIMIT 1", ['drawer_id' => $drawer_id, 'user_id' => $drawerUserId]);
							$sumRows = $dbBal->query("SELECT SUM(volume) AS total_volume FROM drawer_denos WHERE drawer_id = :drawer_id AND user_id = :user_id", ['drawer_id' => $drawer_id, 'user_id' => $drawerUserId]);
							$currentClosing = !empty($sumRows) ? (float)($sumRows[0]->total_volume ?? 0) : $transactionAmount;

							if (empty($rowsBal)) {
								$hdr = $dbBal->query("SELECT amount FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawer_id]);
								$opening = !empty($hdr) ? (float)($hdr[0]->amount ?? 0) : 0.0;
								$delta = $currentClosing - $opening;
								$credit = $delta > 0 ? $delta : 0;
								$debit = $delta < 0 ? -$delta : 0;
								$dbBal->query("INSERT INTO drawer_balances (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status) VALUES (:drawer_id, :user_id, 'cash', 'CASH_DRAWER', :opening, :credit, :debit, :closing, :date_created, :trans_id, :trans_status)", ['drawer_id' => $drawer_id, 'user_id' => $drawerUserId, 'opening' => $opening, 'credit' => $credit, 'debit' => $debit, 'closing' => $currentClosing, 'date_created' => $now, 'trans_id' => $trans_id, 'trans_status' => $trans_status]);
							} else {
								$rowBal = $rowsBal[0];
								$opening = (float)($rowBal->opening_balance ?? 0);
								$delta = $currentClosing - $opening;
								$credit = $delta > 0 ? $delta : 0;
								$debit = $delta < 0 ? -$delta : 0;
								$dbBal->query("UPDATE drawer_balances SET credit = :credit, debit = :debit, closing_balance = :closing, trans_status = :trans_status, date_updated = :date_updated WHERE id = :id", ['credit' => $credit, 'debit' => $debit, 'closing' => $currentClosing, 'trans_status' => $trans_status, 'date_updated' => $now, 'id' => $rowBal->id]);
							}
						}
					}
					catch(\Throwable $e){}
				}
			}
			catch(\Throwable $e) {}
		}

			// Update sim record
			$sim->update($row->id, $postdata);

            // Create commission entry for this sold SIM
            try {
                $commissionAmount = round((float)($postdata['commission'] ?? 0), 2);
                if ($commissionAmount !== 0.0) {
                    $commissionDb = new \Core\Database;
                    $commissionNetwork = trim((string)($postdata['network'] ?? $row->network ?? ''));
                    $serialNumber = trim((string)($postdata['serial_number'] ?? $row->serial_number ?? ''));
                    $assignedNumber = trim((string)($postdata['number_assigned'] ?? $row->number_assigned ?? ''));
                    $commissionDescription = 'SIM SALE COMMISSION | ' . ($commissionNetwork !== '' ? $commissionNetwork . ' | ' : '') . 'Serial: ' . $serialNumber . ' | Assigned: ' . $assignedNumber . ' | ' . ($postdata['transaction_status'] ?? 'Sold');
                    $commissionDb->query(
                        "INSERT INTO commissions (date_received, colletion_period, network, commission_type, description, pay_to, assigned, amount, transaction_status, created_by, updated_by, disabled, deleted, date_created, date_updated, date_deleted) VALUES (:date_received, :colletion_period, :network, :commission_type, :description, :pay_to, :assigned, :amount, :transaction_status, :created_by, :updated_by, :disabled, :deleted, :date_created, :date_updated, :date_deleted)",
                        [
                            'date_received' => $now,
                            'colletion_period' => 'INSTANT',
                            'network' => $commissionNetwork !== '' ? $commissionNetwork : null,
                            'commission_type' => 'SIM SALE COMMISSION',
                            'description' => $commissionDescription,
                            'pay_to' => $user_id,
                            'assigned' => $user_id,
                            'amount' => $commissionAmount,
                            'transaction_status' => 'Approved',
                            'created_by' => $user_id,
                            'updated_by' => null,
                            'disabled' => 0,
                            'deleted' => 0,
                            'date_created' => $now,
                            'date_updated' => null,
                            'date_deleted' => null,
                        ]
                    );
                }
            } catch (\Throwable $e) {
            }

            //log changes in the log table
            $action                      = 'Sold';
            $serial_number         = $postdata['serial_number'];
            $assinged_number    = $postdata['number_assigned'];
            $transaction              = $postdata['network'] . "  sim  with Serial Number:". $serial_number;
            $table                        = 'sims';
            $description = "Sold sim with serial number: $serial_number and assigned number: $assinged_number";
            $date_created = date("Y-m-d H:i:s");

		    $log = new \Sims\Log;
		    $log_activity = $log->insert([
			 	'table_name'=>$table,
			 	'action'=>$action,
			 	'user_id'=>$user_id,
			 	'user_name'=>$username,
			 	'description'=>$description,
			 	'date_created'=>$date_created,
			 ]);

			message_success("$row->network Sim with serial number: ($serial_number) Sold successfully and synced to cashdrawer!");
			redirect($admin_route.'/'.$plugin_route);
		}
	}

	if(!$csrf)
		$sim->errors['serial_number'] = "Form expired!";

	set_value('errors',array_merge($sim->errors,$req->upload_errors));


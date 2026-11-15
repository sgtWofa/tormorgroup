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

		// Sync with cashdrawer when sold
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
							if ($transactionAmount > 0) {
								$rowsBal = $dbBal->query("SELECT * FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'cash' AND channel_key = 'CASH_DRAWER' LIMIT 1", ['drawer_id' => $drawer_id, 'user_id' => $drawerUserId]);
								$now = date('Y-m-d H:i:s');
								$trans_status = 'Approved';
								$trans_id = $row->id;

								if (empty($rowsBal)) {
									$hdr = $dbBal->query("SELECT amount FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawer_id]);
									$opening = !empty($hdr) ? (float)($hdr[0]->amount ?? 0) : 0.0;
									$dbBal->query("INSERT INTO drawer_balances (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status) VALUES (:drawer_id, :user_id, 'cash', 'CASH_DRAWER', :opening, :credit, :debit, :closing, :date_created, :trans_id, :trans_status)", ['drawer_id' => $drawer_id, 'user_id' => $drawerUserId, 'opening' => $opening, 'credit' => $transactionAmount, 'debit' => 0, 'closing' => $opening + $transactionAmount, 'date_created' => $now, 'trans_id' => $trans_id, 'trans_status' => $trans_status]);
								} else {
									$rowBal = $rowsBal[0];
									$credit = (float)($rowBal->credit ?? 0) + $transactionAmount;
									$closing = (float)($rowBal->opening_balance ?? 0) + $credit - (float)($rowBal->debit ?? 0);
									$dbBal->query("UPDATE drawer_balances SET credit = :credit, closing_balance = :closing, date_updated = :updated WHERE id = :id", ['credit' => $credit, 'closing' => $closing, 'updated' => $now, 'id' => $rowBal->id]);
								}
							}
						}
					} catch (\Throwable $e) {}
				}
			} catch (\Throwable $e) {}
		}

			// Update sim record
			$sim->update($row->id, $postdata);

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


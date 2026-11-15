<?php
	require_once __DIR__ . '/../../agencybanking/helpers/cashdrawer-sync.php';
	
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$ezwich = new \Ezwichs\Ezwich;
	$deno = new \Ezwichs\Deno;

	$postdata = $req->post();
	$postdata['momo_image'] = $postdata['momo_image'] ?? '';
	$postdata['merchant_receipt'] = $postdata['merchant_receipt'] ?? '';
	$postdata['customer_receipt'] = $postdata['customer_receipt'] ?? '';

	try {
		$dbSchema = new \Core\Database;
		$neededCols = ['momo_image', 'merchant_receipt', 'customer_receipt'];
		$missingCols = [];
		foreach ($neededCols as $col) {
			$hasCol = $dbSchema->query(
				"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'ezwichs' AND COLUMN_NAME = :col LIMIT 1",
				['db' => DB_NAME, 'col' => $col]
			);
			if (empty($hasCol)) {
				$missingCols[] = $col;
			}
		}
		if (!empty($missingCols)) {
			foreach ($missingCols as $col) {
				try {
					$dbSchema->query("ALTER TABLE ezwichs ADD COLUMN {$col} VARCHAR(255) NULL");
				} catch (\Throwable $e) {
					$ezwich->errors[$col] = "Database column missing: ezwichs.{$col}";
					set_value('errors', array_merge($ezwich->errors, $req->upload_errors));
					return;
				}
			}
		}
	} catch (\Throwable $e) {
		$ezwich->errors['momo_image'] = 'Unable to validate ezwich photo storage.';
		set_value('errors', array_merge($ezwich->errors, $req->upload_errors));
		return;
	}

	$req->upload_folder = 'uploads/ezwich';
	$uploadedMomoImage = $req->upload_files('momo_image');
	if (!empty($uploadedMomoImage)) {
		$postdata['momo_image'] = $uploadedMomoImage;
	}
	$uploadedMerchantReceipt = $req->upload_files('merchant_receipt');
	if (!empty($uploadedMerchantReceipt)) {
		$postdata['merchant_receipt'] = $uploadedMerchantReceipt;
	}
	$uploadedCustomerReceipt = $req->upload_files('customer_receipt');
	if (!empty($uploadedCustomerReceipt)) {
		$postdata['customer_receipt'] = $uploadedCustomerReceipt;
	}

	$currencyDetails = $postdata['denos'] ?? '[]';
	// Decode the JSON currency details
	$currencyArray = json_decode($currencyDetails, true);

	$csrf = csrf_verify($postdata);
	if($csrf && $ezwich->validate_insert($postdata))
	{
		if(user_can('add_ezwich'))
		{
		//dd($postdata);die;
			$postdata['date_created'] = date("Y-m-d H:i:s");
			$postdata['id'] = time();

			$session = get_session();

			if(!empty($session->id)){
				$username = $session->first_name . ' '. $session->last_name;
				$user_id = $session->id;
			}else{
				$username = $row->first_name . ' '. $row->last_name;
				$user_id = $row->id;
			}

			$postdata['created_by'] = $user_id;

			// Ensure a meaningful amount is available for cashdrawer sync.
			// For some ezwich flows, amount_payable holds the client-facing value
			// while amount may remain zero if denominations are not captured yet.
			$effectiveAmount = (float)($postdata['amount'] ?? 0);
			if ($effectiveAmount <= 0 && !empty($postdata['amount_payable'])) {
				$postdata['amount'] = (float)$postdata['amount_payable'];
			}

			// Cashdrawer is the primary accounting source: sync this ezwich transaction
			$syncPayload = $postdata;
			$syncPayload['id'] = $postdata['id'];
			$syncPayload['network'] = 'MTN MOMOPAY';
			$syncPayload['date_created'] = $postdata['date_created'];
			$syncPayload['date_updated'] = $postdata['date_created'];
			try {
				\Agencybankings\agency_sync_cashdrawer(
					$syncPayload,
					$currencyArray,
					(int)($postdata['created_by'] ?? $user_id ?? 0),
					(int)($session->id ?? $user_id ?? 0)
				);
			} catch (\Throwable $e) {
				// Do not block the transaction if drawer sync fails; log for investigation
				error_log('EZWICH CASHDRAWER SYNC FAILED: ' . $e->getMessage());
			}

			// Explicit INSERT into ezwichs to avoid environment-specific model quirks
			try {
				$db = new \Core\Database;
				// Align columns with ezwichs table schema
				$insertData = [
					'id'               => $postdata['id'],
					'usn'              => $postdata['usn']              ?? null,
					'network'          => $postdata['network']          ?? null,
					'transaction_type' => $postdata['transaction_type'] ?? null,
					'phone'            => $postdata['phone']            ?? null,
					'momo_image'       => $postdata['momo_image']       ?? null,
					'merchant_receipt' => $postdata['merchant_receipt'] ?? null,
					'customer_receipt' => $postdata['customer_receipt'] ?? null,
					'wallet_in_id'     => $postdata['wallet_in_id']     ?? null,
					'wallet_out_id'    => $postdata['wallet_out_id']    ?? null,
					'balance'          => (float)($postdata['balance']        ?? 0),
					'amount_payable'   => (float)($postdata['amount_payable'] ?? 0),
					'amount'           => (float)($postdata['amount']        ?? 0),
					'destination_bank' => $postdata['destination_bank'] ?? null,
					'transaction_status'=> $postdata['transaction_status'] ?? null,
					'charge'           => (float)($postdata['charge'] ?? 0),
					'receiving'        => $postdata['receiving']        ?? null,
					'receiving_sim'    => $postdata['receiving_sim']    ?? null,
					'assigned'         => (int)($postdata['assigned']   ?? 0),
					'created_by'       => $postdata['created_by']       ?? null,
					'updated_by'       => null,
					'disabled'         => 0,
					'deleted'          => 0,
					'date_created'     => $postdata['date_created']     ?? null,
					'date_updated'     => null,
					'date_deleted'     => null,
					'transaction_id'   => $postdata['transaction_id']   ?? null,
				];
				$sql = "INSERT INTO ezwichs (".implode(',', array_keys($insertData)).") VALUES (:".implode(',:', array_keys($insertData)).")";
				$posted = $db->query($sql, $insertData);
				// Only treat as failure if the Database layer reports an error
				if ($db->has_error) {
					$errorMsg = $db->error ?? '';
					error_log('EZWICH DB ERROR: ' . $errorMsg);
					message_fail('Failed to save ezwich transaction. DB: ' . $errorMsg);
					redirect($admin_route.'/'.$plugin_route);
					return;
				}
			} catch (\Throwable $e) {
				error_log('EZWICH INSERT FAILED: ' . $e->getMessage());
				message_fail('Failed to save ezwich transaction.');
				redirect($admin_route.'/'.$plugin_route);
				return;
			}

			// Ezwich charges are recorded on the transaction only; they are not commissions.

	        if ($currencyArray) {
	            foreach ($currencyArray as $currency) {
	                $denosPost = [
	                   'trans_id' => $postdata['id'],
	                    'currency' => $currency['currency'],
	                    'denomination' => $currency['denomination'],
	                    'count' => (int)$currency['count'], // Cast to integer
	                    'volume' => (float)$currency['volume'], // Cast to float
	                    'created_by' => $user_id,
	                    'date_created' => $postdata['date_created']
	                ];

	                // Validate before insertion
	                if (empty($denosPost['trans_id'])) {
	                    dd("Invalid trans_id: " . $denosPost['trans_id']);
	                }
	                $deno->insert($denosPost);
	            }
	        }

			//log changes in the log table
			$action 		= 'added';
			$transaction 		= $postdata['usn'] . ' '. $postdata['transaction_type'];
			$transaction_id 		= $postdata['id'];
			$table 			= 'ezwichs';
			$description 	= "$username $action a new ezwich $transaction with id $transaction_id to $table";
			$date_created 	= date("Y-m-d H:i:s");

		    $log = new \Ezwichs\Log;
		    $log_activity = $log->insert([
			 	'table_name'=>$table,
			 	'action'=>$action,
			 	'user_id'=>$user_id,
			 	'user_name'=>$username,
			 	'description'=>$description,
			 	'date_created'=>$date_created,
			 ]);
 
			message_success("Transaction ($transaction) added successfully!");
			redirect($admin_route.'/'.$plugin_route);
		}
	}

	if(!$csrf)
		$ezwich->errors['usn'] = "Form expired!";
	
	set_value('errors',$ezwich->errors);


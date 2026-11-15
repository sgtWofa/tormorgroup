<?php
	
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$postdata = $req->post();
	$wallet = new \Wallets\Wallet;
	$transaction = new \Wallets\Transaction;

	$csrf = csrf_verify($postdata);
	if($csrf && $transaction->validate_insert($postdata))
	{
		if(user_can('transfer_funds'))
		{
			$postdata['date_updated'] = date("Y-m-d H:i:s");

			$session = get_session();

			if(!empty($session->id)){
				$username = $session->first_name . ' '. $session->last_name;
				$user_id = $session->id;
			}else{
				$username = $row->first_name . ' '. $row->last_name;
				$user_id = $row->id;
			}

			$postdata['updated_by'] = $user_id;

            // Process new transaction for Wallet Out
            if (!empty($postdata['wallet_out_id'])) {
                $walletOutRowNew = $wallet->first(['id' => $postdata['wallet_out_id']]);

                $postwallet_outNew = [
                    'debit' => $walletOutRowNew->debit + $postdata['amount'],
                    'balance' => $walletOutRowNew->balance - $postdata['amount']
                ];
                $wallet->update($walletOutRowNew->id, $postwallet_outNew);

                $categoryOutRowNew = $category->first(['id' => $walletOutRowNew->category]);
				
				$category_outNew = [
                    'balance' => $categoryOutRowNew->balance - $postdata['amount']
                ];
                $category->update($categoryOutRowNew->id, $category_outNew);

                $postTransactionOut = [
                    'wallet_id' => $postdata['wallet_out_id'],
                    'wallet_out_id' => $postdata['wallet_out_id'],
                    'description' => $postdata['description'],
                    'transaction_type' => 'debit',
                    'amount' => $postdata['amount'],
                    'debit' => $postdata['amount'],
                    'balance' => $postwallet_outNew['balance'],
                    'created_by' => $postdata['updated_by'],
                    'date_created' => $postdata['date_updated']
                ];
                $transaction->insert($postTransactionOut);
            }

			//log changes in the log table
			$action 		= 'withdrawal of funds';
			$out_id 		= $postdata['wallet_out_id'];
			$table 			= 'wallets and transactions';
			$description 	= "$username performed $action from a/c number $out_id";
			$date_created 	= date("Y-m-d H:i:s");

		    $log = new \Wallets\Log;
		    $log_activity = $log->insert([
			 	'table_name'=>$table,
			 	'action'=>$action,
			 	'user_id'=>$user_id,
			 	'user_name'=>$username,
			 	'description'=>$description,
			 	'date_created'=>$date_created,
			 ]);
 
			message_success("Funds withdrawal from a/c number $out_id successful!");
			redirect($admin_route.'/'.$plugin_route);
		}
	}

	if(!$csrf)
		$transaction->errors['description'] = "Form expired!";
	
	set_value('errors',$transaction->errors);


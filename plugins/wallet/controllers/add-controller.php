<?php
	
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$postdata = $req->post();
	$wallet = new \Wallets\Wallet;
	$category = new \Wallets\Category;
	$transaction = new \Wallets\Transaction;

	$csrf = csrf_verify($postdata);
	if($csrf && $wallet->validate_insert($postdata))
	{
		if(user_can('add_wallet'))
		{
			$postdata['date_created'] = date("Y-m-d H:i:s");
			$postdata['id'] = time();
			if (!empty($postdata['amount'])) {
				$postdata['credit'] = $postdata['amount'];
				$postdata['balance'] = $postdata['amount'];
			}
			$postdata['id'] = time();

			$session = get_session();

			if(!empty($session->id)){
				$username = $session->first_name . ' '. $session->last_name;
				$user_id = $session->id;
			}else{
				$username = $row->first_name . ' '. $row->last_name;
				$user_id = $row->id;
			}

			$categoryRow = $category->first(['id'=>$postdata['category']]);
			if (!empty($categoryRow)) {
                $postCategory = [
                   // 'credit' => $categoryRow->credit + $postdata['amount'],
                    'balance' => $categoryRow->balance + $postdata['balance']
                ];              
               $category->update($categoryRow->id, $postCategory);
            }


			//dd(['postdata'=>$postdata, 'categoryRow'=>$categoryRow, 'postCategory'=>$postCategory]);die;

			$postdata['created_by'] = $user_id;

			$posted = $wallet->insert($postdata);

			if (isset($posted)) {

				//populate transactions table with records
				$postTransaction 									= [];
				$postTransaction['id'] 								= $postdata['id'];
				$postTransaction['wallet_id'] 					= $postdata['id'];
				$postTransaction['wallet_in_id'] 				= $postdata['id'];
				$postTransaction['description'] 				= 'Opening wallet balance';
				$postTransaction['transaction_type'] 		= 'credit';
				$postTransaction['amount'] 					= $postdata['amount'];
				$postTransaction['credit'] 						= $postdata['credit'];
				$postTransaction['debit'] 						= $postdata['debit'];
				$postTransaction['balance'] 					= $postdata['balance'];
				$postTransaction['created_by'] 				= $postdata['created_by'];
				$postTransaction['date_created'] 			= $postdata['date_created'];

				$transaction->insert($postTransaction);
			}

			//log changes in the log table
			$action 		= 'added';
			$transaction 		= $postdata['category'] . ' '. $postdata['transaction_type'];
			$transaction_id 		= $postdata['id'];
			$table 			= 'wallets and transactions';
			$description 	= "$username $action a new wallet $transaction with id $transaction_id to $table";
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
 
			message_success("Account ($transaction) created successfully!");
			redirect($admin_route.'/'.$plugin_route);
		}
	}

	if(!$csrf)
		$wallet->errors['usn'] = "Form expired!";
	
	set_value('errors',$wallet->errors);


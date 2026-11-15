<?php
	
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$postdata = $req->post();
	$mywallet = new \MyWallets\Mywallet;
	$mycategory = new \MyWallets\Mycategory;
	$transaction = new \MyWallets\Transaction;

	$csrf = csrf_verify($postdata);
	if($csrf && $mywallet->validate_insert($postdata))
	{

    $sof = $mycategory->first(['id' => $postdata['parent_account']]);

		/* if ($postdata['amount'] > $sof->balance) {
	       $mywallet->errors['parent_account'] = "Insufficient balance in selected account!";
	       set_value('errors', $mywallet->errors);
	    }else*/
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

			$mycategoryRow = $mycategory->first(['id'=>$postdata['parent_account']]);
			if (!empty($mycategoryRow)) {
                $postCategory = [
                   // 'credit' => $mycategoryRow->credit + $postdata['amount'],
                    'balance' => $mycategoryRow->balance + $postdata['balance']
                ];              
               $mycategory->update($mycategoryRow->id, $postCategory);
            }


			//dd(['postdata'=>$postdata, 'categoryRow'=>$mycategoryRow, 'postCategory'=>$postCategory]);die;

			$postdata['created_by'] = $user_id;

			$mywallet->insert($postdata);

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

			//log changes in the log table
			$action 		= 'added';
			$transaction_id 		= $postdata['id'];
			$table 			= 'mywallets';
			$description 	= "$username $action a new wallet with id $transaction_id to $table";
			$date_created 	= date("Y-m-d H:i:s");

		    $log = new \MyWallets\Log;
		    $log_activity = $log->insert([
			 	'table_name'=>$table,
			 	'action'=>$action,
			 	'user_id'=>$user_id,
			 	'user_name'=>$username,
			 	'description'=>$description,
			 	'date_created'=>$date_created,
			 ]);
 
			message_success("New wallet with ID: $transaction_id created successfully!");
			redirect($admin_route.'/'.$plugin_route);
		}
	}

	if(!$csrf)
		$mywallet->errors['usn'] = "Form expired!";
	
	set_value('errors',$mywallet->errors);


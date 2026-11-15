<?php

if(!empty($row))
{
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$postdata = $req->post();
	$postdata['id'] = $row->id;

	$csrf = csrf_verify($postdata);

	if($csrf && $mywallet->validate_update($postdata))
	{
		if(user_can('edit_wallet'))
		{
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

			$categoryRow = $category->first(['id'=>$postdata['category']]);
			if (!empty($categoryRow)) {
                $postCategory = [
                   // 'credit' => $categoryRow->credit + $postdata['amount'],
                    'balance' => $categoryRow->balance + $postdata['balance']
                ];              
               $category->update($categoryRow->id, $postCategory);
            }

			unset($postdata['id']);
			$mywallet->update($row->id,$postdata);

			$transRow = $transaction->where(['wallet_in_id'=>$row->$id, 'date_created'=>$row->date_created])[0];
			if (!empty($transRow)) {		
				//dd($transRow);die;		
				$transaction->update($transRow->id,$postdata);
			}
			//dd(['postdata'=>$postdata]);

			$action 		= 'edited';
			$Oldwallet 		= $row->usn . ' ' . $row->transaction_type;
			$transaction_id 		= $row->id;
			$mywallet 		= $postdata['usn'] . ' '. $postdata['transaction_type'];
			$table 			= 'mywallets';
			$description 	= "$username $action wallet transaction $Oldwallet with id $transaction_id";
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

			message_success("Account with id ($transaction_id) edited successfully!");
			redirect($admin_route.'/'.$plugin_route);
		}
	}

	if(!$csrf)
		$mywallet->errors['usn'] = "Form expired!";

	set_value('errors',array_merge($mywallet->errors,$req->upload_errors));
}else{

	message_fail("Record not found");
}

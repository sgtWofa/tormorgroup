<?php

if(!empty($row))
{
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');
	
	$postdata = $req->post();

	$csrf = csrf_verify($postdata);
	if($csrf)
	{
 		if(user_can('delete_wallet'))
		{
			//log changes in the log table
			$session = get_session();

			if(!empty($session->id)){
				$username = $session->first_name . ' '. $session->last_name;
				$user_id = $session->id;
			}else{
				$username = 'Unknown user';
			}

			$action 		= 'deleted';
			$table 			= 'wallets';
			$Oldwallet 		= $row->usn . ' ' . $row->transaction_type;
			$transaction_id 		= $row->id;
			$table 			= 'wallets';
			$description 	= "$username $action transaction $Oldwallet with id $transaction_id from $table";
			$date_deleted 	= date("Y-m-d H:i:s");

		    $log = new \MyWallets\Log;
		    $log_activity = $log->insert([
			 	'table_name'=>$table,
			 	'action'=>$action,
			 	'user_id'=>$user_id,
			 	'user_name'=>$username,
			 	'description'=>$description,
			 	'date_deleted'=>$date_deleted,
			 ]);

		    $mywallet = new \MyWallets\Mywallet;
	 		$mywallet->delete($row->id);
 
			message_success("Record deleted successfully!");
			redirect($admin_route.'/'.$plugin_route);
		}
	}

	$mywallet->errors['usn'] = "Form expired!";

	set_value('errors',$mywallet->errors);
}else{

	message_fail("Record not found");
}

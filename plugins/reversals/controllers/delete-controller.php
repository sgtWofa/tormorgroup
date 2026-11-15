<?php

if(!empty($row))
{
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');
	
	$postdata = $req->post();

	$csrf = csrf_verify($postdata);
	if($csrf)
	{
 		if(user_can('delete_reversal'))
		{
			//log changes in the log table
			$session = get_session();

			if(!empty($session->id)){
				$username = $session->first_name . ' '. $session->last_name;
				$user_id = $session->id;
			}else{
				$username = 'Unknown user';
				$user_id = 0;
			}

			$action 		= 'deleted';
			$table 			= 'reversals';
			$Oldreversal 		= $row->network . ' ' . $row->service_type;
			$transaction_id 		= $row->id;
			$table 			= 'reversals';
			$description 	= "$username $action transaction $Oldreversal with id $transaction_id from $table";
			$date_deleted 	= date("Y-m-d H:i:s");

		    $log = new \Reversals\Log;
		    $log_activity = $log->insert([
			 	'table_name'=>$table,
			 	'action'=>$action,
			 	'user_id'=>$user_id,
			 	'user_name'=>$username,
			 	'description'=>$description,
			 	'date_deleted'=>$date_deleted,
			 ]);

		    $reversal = new \Reversals\Reversal;
	 		$reversal->delete($row->id);
 
			message_success("Record deleted successfully!");
			redirect($admin_route.'/'.$plugin_route);
		}
	}

	$reversal->errors['usn'] = "Form expired!";

	set_value('errors',$reversal->errors);
}else{

	message_fail("Record not found");
}

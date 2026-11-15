<?php

if(!empty($row))
{

	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');
	
	$postdata = $req->post();

	$csrf = csrf_verify($postdata);
	if($csrf)
	{
 		if(user_can('delete_momoremit'))
		{
			//log changes in the log table
			$session = get_session();

			if(!empty($session->id)){
				$username = $session->first_name . ' '. $session->last_name;
				$user_id = $session->id;
			}else{
				$username = 'Unknown user';
			}

			if ($row->transaction_type === 'Sending') {
				$sending->delete($row->id);		 
				}else
				if ($row->transaction_type === 'Withdrawal') {
		 			$withdrawal->delete($row->id);
		 		
				}else
				if ($row->transaction_type === 'Deposit') {
		 			$deposit->delete($row->id);
		 		
				}else
				if ($row->transaction_type === 'CIT') {
					$cit->update($row->id,$postdata);
		 			$cit->delete($row->id);
			}

			$action 		= 'deleted';
			$table 			= 'momoremits';
			$Oldmomoremit 		= $row->network . ' ' . $row->transaction_type;
			$transaction_id 		= $row->id;
			$table 			= 'momoremits';
			$description 	= "$username $action transaction $Oldmomoremit with id $transaction_id from $table";
			$date_deleted 	= date("Y-m-d H:i:s");

		    $log = new \Momoremits\Log;
		    $log_activity = $log->insert([
			 	'table_name'=>$table,
			 	'action'=>$action,
			 	'user_id'=>$user_id,
			 	'user_name'=>$username,
			 	'description'=>$description,
			 	'date_deleted'=>$date_deleted,
			 ]);

		    $momoremit = new \Momoremits\Momoremit;
	 		$momoremit->delete($row->id);
 
			message_success("Record deleted successfully!");
			redirect($admin_route.'/'.$plugin_route);
		}
	}

	$momoremit->errors['network'] = "Form expired!";

	set_value('errors',$momoremit->errors);
}else{

	message_fail("Record not found");
}

<?php

if(!empty($row))
{
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$session = get_session();
	$currentUserId = (int)($session->id ?? 0);
	$rowOwnerId = (int)($row->created_by ?? 0);
	if ($currentUserId <= 0 || (($rowOwnerId > 0 && $rowOwnerId !== $currentUserId) && !user_can('delete_category'))) {
		message_fail('Access denied');
		redirect($admin_route.'/'.$plugin_route);
	}
	
	$postdata = $req->post();

	$csrf = csrf_verify($postdata);
	if($csrf)
	{
	 		if(user_can('delete_category') || $rowOwnerId === $currentUserId || (int)($row->assigned ?? 0) === $currentUserId)
		{
			if(!empty($session->id)){
				$username = $session->first_name . ' '. $session->last_name;
				$user_id = (int)$session->id;
			}else{
				$username = 'Unknown user';
				$user_id = 0;
			}

			$action 		= 'deleted';
			$table 			= 'airtimebundles';
			$OldRecord 		= ($row->network ?? '') . ' ' . ($row->transaction_type ?? '');
			$transaction_id 		= $row->id;
			$description 	= "$username $action transaction $OldRecord with id $transaction_id from $table";
			$date_created 	= date("Y-m-d H:i:s");

		    $log = new \Airtimebundles\Log;
		    $log->insert([
			 	'table_name'=>$table,
			 	'action'=>$action,
			 	'user_id'=>$user_id,
			 	'user_name'=>$username,
			 	'description'=>$description,
			 	'date_created'=>$date_created,
			 ]);

			try {
				$transId = (int)($row->id ?? 0);
				if ($transId > 0) {
					$deno = new \Airtimebundles\Deno;
					$deno->query("DELETE FROM denos WHERE trans_id = :trans_id", ['trans_id' => $transId]);
				}
			} catch (\Throwable $e) {
				// ignore
			}

		    $airtimebundle->delete($row->id);
 
			message_success("Record deleted successfully!");
			redirect($admin_route.'/'.$plugin_route);
		}
	}

	$airtimebundle->errors['network'] = "Form expired!";

	set_value('errors',$airtimebundle->errors);
}else{

	message_fail("Record not found");
}

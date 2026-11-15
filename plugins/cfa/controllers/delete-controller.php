<?php

if(!empty($row))
{
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$session = get_session();
	$currentUserId = (int)($session->id ?? 0);
	$rowOwnerId = (int)($row->created_by ?? 0);
	if ($currentUserId <= 0 || (($rowOwnerId > 0 && $rowOwnerId !== $currentUserId) && !user_can('delete_cfa'))) {
		message_fail('Access denied');
		redirect($admin_route.'/'.$plugin_route);
	}
	
	$postdata = $req->post();

	$csrf = csrf_verify($postdata);
	if($csrf)
	{
	 		if(user_can('delete_cfa') || $rowOwnerId === $currentUserId)
		{
			//log changes in the log table
			if(!empty($session->id)){
				$username = $session->first_name . ' '. $session->last_name;
				$user_id = (int)$session->id;
			}else{
				$username = 'Unknown user';
				$user_id = 0;
			}

			$action 		= 'deleted';
			$table 			= 'cfas';
			$transactionLabel = 'CFA exchange : ' . ($row->transaction_type ?? '') . ', status: ' . ($row->transaction_status ?? '');
			$transaction_id 		= $row->id;
			$description 	= "$username $action $transactionLabel with id $transaction_id from $table";
			$date_created 	= date("Y-m-d H:i:s");

		    $log = new \Cfas\Log;
		    $log_activity = $log->insert([
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
					$deno = new \Cfas\Deno;
					$deno->query("DELETE FROM denos WHERE trans_id = :trans_id", ['trans_id' => $transId]);
					if (function_exists('Cfas\\cfa_remove_cashdrawer_entries')) {
						\Cfas\cfa_remove_cashdrawer_entries($transId);
					}
				}
			} catch (\Throwable $e) {
				// ignore cleanup failures
			}

		    $cfa->delete($row->id);
 
			message_success("Record deleted successfully!");
			redirect($admin_route.'/'.$plugin_route);
		}
	}

	$cfa->errors['transaction'] = "Form expired!";

	set_value('errors',$cfa->errors);
}else{

	message_fail("Record not found");
}

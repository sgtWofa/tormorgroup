<?php

$postdata = $req->post();
$isAjax = !empty($postdata['__ajax']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

if(!empty($row))
{
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$csrf = csrf_verify($postdata);
	if($csrf)
	{
 		if(user_can('delete_sim'))
		{
			$transactionStatus = strtolower(trim((string)($row->transaction_status ?? '')));
			if (in_array($transactionStatus, ['sold - retail', 'sold - wholesale'], true)) {
				if ($isAjax) {
					sims_json_response(['success' => false, 'message' => 'Sold SIM records cannot be deleted.'], 422);
				}
				message_fail('Sold SIM records cannot be deleted.');
				redirect($admin_route.'/'.$plugin_route);
			}

			//log changes in the log table
			$session = get_session();

			if(!empty($session->id)){
				$username = $session->first_name . ' '. $session->last_name;
				$user_id = $session->id;
			}else{
				$username = 'Unknown user';
			}

			$action = 'deleted';
			$table = 'sims';
			$oldSim = trim(($row->network ?? '') . ' ' . ($row->serial_number ?? ''));
			$transaction_id = $row->id;
			$description = "$username $action SIM $oldSim with id $transaction_id from $table";
			$date_deleted = date("Y-m-d H:i:s");

	    	$log = new \Sims\Log;
	    	$log_activity = $log->insert([
		 	'table_name'=>$table,
		 	'action'=>$action,
		 	'user_id'=>$user_id,
		 	'user_name'=>$username,
		 	'description'=>$description,
		 	'date_deleted'=>$date_deleted,
		 ]);

	    	$sim = new \Sims\Sim;
 		$sim->delete($row->id);
 
		if ($isAjax) {
			sims_json_response(['success' => true, 'message' => 'SIM record deleted successfully!']);
		}

		message_success("SIM record deleted successfully!");
		redirect($admin_route.'/'.$plugin_route);
		}
	}

	if (isset($sim)) {
		$sim->errors['serial_number'] = "Form expired!";
		set_value('errors',$sim->errors);
	}
}

if ($isAjax) {
	sims_json_response(['success' => false, 'message' => 'Unable to process delete request.'], 400);
}

message_fail('Unable to process delete request.');
redirect($admin_route.'/'.$plugin_route);

<?php

if(!empty($row))
{
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$postdata = $req->post();
	$postdata['id'] = $row->id;

	$csrf = csrf_verify($postdata);

	if($csrf && $request->validate_update($postdata))
	{
		if(user_can('edit_request'))
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

		//dd($postdata); die;
			unset($postdata['id']);
			$request->update($row->id,$postdata);

			//log changes in the log table
			$action 		= 'updated';
			$transaction 		= $postdata['network'] . ' '. $postdata['request_type'];
			$transaction_id 		= $row->id;
			$table 			= 'requests';
			$description 	= "$username $action a request for $transaction with id $transaction_id to $table";
			$date_created 	= date("Y-m-d H:i:s");

		    $log = new \Requests\Log;
		    $log_activity = $log->insert([
			 	'table_name'=>$table,
			 	'action'=>$action,
			 	'user_id'=>$user_id,
			 	'user_name'=>$username,
			 	'description'=>$description,
			 	'date_created'=>$date_created,
			 ]);

			message_success("Request with id: $transaction_id updated successfully!");
			redirect($admin_route.'/'.$plugin_route);
		}
	}

	if(!$csrf)
		$request->errors['usn'] = "Form expired!";

	set_value('errors',array_merge($request->errors,$req->upload_errors));
}else{

	message_fail("Record not found");
}

<?php
	
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$postdata = $req->post();

	$csrf = csrf_verify($postdata);
	if($csrf && $request->validate_insert($postdata))
	{
		if(user_can('add_request'))
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

			$postdata['created_by'] = $user_id;

			dd($postdata); die;

			$posted = $request->insert($postdata);

			//log changes in the log table
			$action 		= 'added';
			$transaction 		= $postdata['network'] . ' '. $postdata['request_type'];
			$transaction_id 		= $postdata['id'];
			$table 			= 'requests';
			$description 	= "$username $action a new request for $transaction with id $transaction_id to $table";
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
 
			message_success("$transaction request created successfully!");
			redirect($admin_route.'/'.$plugin_route);
		}
	}

	if(!$csrf)
		$request->errors['usn'] = "Form expired!";
	
	set_value('errors',$request->errors);


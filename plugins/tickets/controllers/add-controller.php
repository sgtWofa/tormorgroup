<?php
	header('Content-Type: application/json; charset=utf-8');
	
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$info = [];
	$info['success'] = false;
	$info['message'] = "";
	$info['errors'] = [];

	$postdata = $req->post();
	$filedata = $req->files();

	$files_ok = true;
	$ticketUploadErrors = [];
	if(!empty($filedata)){
		$postdata['image'] = $req->upload_files('image');
		if(!empty($req->upload_errors)){
			$files_ok = false;
			$ticketUploadErrors = $req->upload_errors;
		}
	}

	$ticket = new \Tickets\Ticket;
	$csrf = csrf_verify($postdata);
	$validTicket = $ticket->validate_insert($postdata);
	$session = get_session();
	$user_id = (int)($session->id ?? 0);
	if($user_id <= 0){
		$ticket->errors['subject'] = 'Your session has expired. Please sign in again.';
		$validTicket = false;
	}
	$eligibleRecipientIds = array_map(function($user){
		return (int)($user->id ?? 0);
	}, function_exists('tickets_users_with_cashdrawer_today') ? tickets_users_with_cashdrawer_today($user_id) : []);
	$assignedId = (int)($postdata['assigned'] ?? 0);
	if($assignedId <= 0 || !in_array($assignedId, $eligibleRecipientIds, true)){
		$ticket->errors['assigned'] = 'Send to must be a user who has created a cashdrawer today.';
		$validTicket = false;
	}
	if($csrf && $files_ok && $validTicket)
	{
		if(user_can('add_tickets'))
		{
			$postdata['date_created'] = date("Y-m-d H:i:s");	
			$postdata['id'] = time();

			$username = trim((string)($session->first_name ?? '') . ' ' . (string)($session->last_name ?? ''));
			$username = $username !== '' ? $username : 'User #' . $user_id;

			$postdata['user_id'] = $user_id;
			$postdata['created_by'] = $user_id;

           //dd($postdata); die;

			$posted = $ticket->insert($postdata);

			//log changes in the log table
			$action 							= 'added';
			$subject 							= $postdata['subject'];
			$status 							= $postdata['status'];
			$transaction 					= "new  ticket  with subject: :". $subject ." and status : ". $postdata['status'];
			$transaction_id 				= $postdata['id'];
			$table 								= 'tickets';
			$description 					= "$username $action a $transaction  to $table";
			$date_created 					= date("Y-m-d H:i:s");

		    $log = new \Tickets\Log;
		    $log_activity = $log->insert([
			 	'table_name'=>$table,
			 	'action'=>$action,
			 	'user_id'=>$user_id,
			 	'user_name'=>$username,
			 	'description'=>$description,
			 	'date_created'=>$date_created,
			 ]);

		    $info['success'] = true;
			$info['message'] = "Record added successfully!";
 			
			//message_success("($transaction) created successfully!");
			//redirect($admin_route.'/'.$plugin_route);

		}else{
        				
			$ticket->errors['subject'] = "You do not have permission to add tickets!";	}
			set_value('errors',$ticket->errors);		
		}

	if(!$csrf)
		$ticket->errors['subject'] = "Form expired!";
	if(!$files_ok && $ticketUploadErrors){
		$ticket->errors['image'] = implode(' ', array_map('strval', $ticketUploadErrors));
	}
	
	$info['errors'] = $ticket->errors;

	echo json_encode($info, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES);
	return;
	

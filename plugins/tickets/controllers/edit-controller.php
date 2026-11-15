<?php

	$info = [];
	$info['success'] = false;
	$info['message'] = "";
	$info['errors'] = [];

if(!empty($row))
{
	$postdata = $req->post();
	$filedata = $req->files();
	$postdata['id'] = $row->id;

	$csrf = csrf_verify($postdata);

	$files_ok = true;
	if(!empty($filedata)){
		$postdata['image'] = $req->upload_files('image');
		if(!empty($req->upload_errors))
			$files_ok = false;
	}

	$postdata = $req->post();
	$ticket = new \Tickets\Ticket;

	if($csrf && $files_ok && $ticket->validate_update($postdata))
	{
		if(user_can('edit_tickets'))
		{
			$content = new \Tickets\Content;

			$postdata['date_updated'] = date("Y-m-d H:i:s");
			unset($postdata['id']);

			$session = get_session();

			if(!empty($session->id)){
				$username = $session->first_name . ' '. $session->last_name;
				$user_id = $session->id;
			}else{
				$username = $row->first_name . ' '. $row->last_name;
				$user_id = $row->id;
			}

			$postdata['updated_by'] = $user_id;

			$ticket->update($row->id,$postdata);

			//log changes in the log table
			$action 							= 'updated';
			$subject 							= $postdata['subject'];
			$status 							= $postdata['status'];
			$transaction 					= " ticket  #: $row->id with subject: :". $subject ." and status : ". $postdata['status'];
			$transaction_id 				= $row->id;
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
			$info['message'] = "Ticket updated successfully!";
		}
	}

	if(!$csrf)
		$ticket->errors['description'] = "Form expired!";

	 $info['errors'] = array_merge($ticket->errors,$req->upload_errors);
}else{

	$info['message'] = "Record not found";
}

echo json_encode($info);
die;
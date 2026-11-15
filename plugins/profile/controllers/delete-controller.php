<?php

if(!empty($row))
{
	$postdata = $req->post();

	$session = get_session();
    $userId = $session->id;

	$csrf = csrf_verify($postdata);
	if($csrf)
	{
 		if(user_can('delete_profile') || $userId === $row->id)
		{

		    $postdata['deleted'] = 1;
		    $postdata['date_deleted'] = date("Y-m-d H:i:s");

	 		$user->update($row->id,$postdata);


			//log changes in the log table
			$session = get_session();

			if(!empty($session->id)){
				$username = $session->first_name . ' '. $session->last_name;
				$user_id = $session->id;
			}else{
				$username = $row->first_name . ' '. $row->last_name;
				$user_id = $row->id;
			}

			if($row->gender === 'Male' ){
				$title = "his";
			}else
			if($row->gender === 'Male' ){
				$title = 'her';
			}

			$action 		= 'edited';
			$newUser 		= $postdata['first_name'] . ' ' . $postdata['last_name'] ?? 'Noname';
			$table 			= 'users';
			$description 	= "$username $action $title profile";
			$date_created 	= date("Y-m-d H:i:s");

		    $log = new \UsersManager\Log;
		    $log_activity = $log->insert([
			 	'table_name'=>$table,
			 	'action'=>$action,
			 	'user_id'=>$user_id,
			 	'user_name'=>$username,
			 	'description'=>$description,
			 	'date_created'=>$date_created,
			 ]);

			message_success("Profile deleted successfully!");
			redirect('logout');
		}
	}

	$user->errors['email'] = "Form expired!";

	set_value('errors',$user->errors);
}else{

	message_fail("Record not found");
}

<?php

if(!empty($row))
{
	$postdata = $req->post();

	$csrf = csrf_verify($postdata);
	if($csrf)
	{
		if(user_can('delete_user'))
		{
			$postdata['deleted'] = 1;
			$postdata['disabled'] = 1;
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

			$action 		= 'deleted';
			$table 			= 'users';
			$newUser 		= $row->first_name . ' ' . $row->last_name ?? 'Noname';
			$description 	= "$username $action $newUser from $table";
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

			message_success("Record deleted successfully!");
			redirect($admin_route.'/'.$plugin_route);
		}
	}

	$user->errors['email'] = "Form expired!";

	set_value('errors',$user->errors);
}else{

	message_fail("Record not found");
}

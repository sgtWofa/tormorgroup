<?php

if(!empty($row))
{
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');
	$postdata = $req->post();
	$postdata['id'] = $row->id;

	$csrf = csrf_verify($postdata);

	if($csrf && $category->validate_update($postdata))
	{
		if(user_can('edit_category'))
		{

			unset($postdata['id']);
			$category->update($row->id,$postdata);

			//log changes in the log table
			$session = get_session();

			if(!empty($session->id)){
				$username = $session->first_name . ' '. $session->last_name;
				$user_id = $session->id;
			}else{
				$username = $row->first_name . ' '. $row->last_name;
				$user_id = $row->id;
			}

			$action 		= 'edited';
			$Oldcategory 		= $row->category;
			$category 		= $postdata['category'];
			$description 	= "$username $action category called $Oldcategory to $category";
			$date_created 	= date("Y-m-d H:i:s");
			$table 			= 'categories';

		    $log = new \UsersManager\Log;
		    $log_activity = $log->insert([
			 	'table_name'=>$table,
			 	'action'=>$action,
			 	'user_id'=>$user_id,
			 	'user_name'=>$username,
			 	'description'=>$description,
			 	'date_created'=>$date_created,
			 ]);

			message_success("Record edited successfully!");
			redirect($admin_route.'/'.$plugin_route);
		}
	}

	if(!$csrf)
		$category->errors['email'] = "Form expired!";

	set_value('errors',array_merge($category->errors,$req->upload_errors));
}else{

	message_fail("Record not found");
}

<?php

if(!empty($row))
{
	$postdata = $req->post();

	$csrf = csrf_verify($postdata);
	if($csrf)
	{
 		if(user_can('delete_category'))
		{
			//log changes in the log table
			$session = get_session();

			if(!empty($session->id)){
				$username = $session->first_name . ' '. $session->last_name;
				$user_id = $session->id;
			}else{
				$username = $row->category;
				$user_id = $row->id;
			}

			$action 		= 'deleted';
			$table 			= 'categories';
			$category 		= $row->category;
			$description 	= "$username $action a category called $category from $table";
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

		    $category = new \Categories\Category;
	 		$category->delete($row->id);
 
			message_success("Record deleted successfully!");
			redirect($admin_route.'/'.$plugin_route);
		}
	}

	$category->errors['email'] = "Form expired!";

	set_value('errors',$category->errors);
}else{

	message_fail("Record not found");
}

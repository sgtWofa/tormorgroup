<?php

$id = URL(3) ?? null;
if($id){
	$student = new \UsersManager\Student;
	$student::$query_id = 'get-students';
	$studentRow = $student->first(['id'=>$id]);
}

if(!empty($row))
{
	$postdata = $req->post();
	$filedata = $req->files();
	$postdata['id'] = $row->id;

	$session = get_session();
    $userId = $session->id;

	$csrf = csrf_verify($postdata);

	$files_ok = true;
	if(!empty($filedata)){
		$postdata['image'] = $req->upload_files('image');
		if(!empty($req->upload_errors))
			$files_ok = false;
	}

	if($csrf && $files_ok && $user->validate_update($postdata))
	{
		if(user_can('edit_profile') || $userId === $row->id)
		{

			if(isset($postdata['password']) && empty($postdata['password'])){
				unset($postdata['password']);
			}else{
				$postdata['password'] = password_hash($postdata['password'], PASSWORD_DEFAULT);
			}

			$postdata['date_updated'] = date("Y-m-d H:i:s");
			unset($postdata['id']);

			if(empty($postdata['image']))
				unset($postdata['image']);

			$user->update($row->id,$postdata);

			if(!empty($postdata['image']) && file_exists($row->image))
				unlink($row->image);

			$user_id = $row->id;

			/** save user roles **/
			if(user_can('edit_role'))
			{
				$roledata = [];
				
				foreach ($postdata as $key => $role_id) {
					
					if(!strstr($key, "role_"))
						continue;

					$roledata[] = $role_id;
				}
	 			
	 			/** disable all roles **/
	 			$user_roles_map->query('update '.$vars['optional_tables']['roles_map_table'] .' set disabled = 1 where user_id = :user_id',['user_id'=>$user_id]);
	 			
	 			/** saved to database **/
	 			foreach ($roledata as $role_id) {

 					$result = $user_roles_map->first(['role_id'=>$role_id,'user_id'=>$user_id]);
 					if($result)
 					{
 						$user_roles_map->update($result->id,['disabled'=>0]);
 					}else
 					{
 						$user_roles_map->insert([
 							'role_id'=>$role_id,
 							'user_id'=>$user_id,
 							'disabled'=>0,
 						]);

 					}

	 			}
			}

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
			$newUser 		= $postdata['first_name'] . ' ' . $postdata['last_name'] ?? 'Noname';
			$table 			= 'users';
			$description 	= "$username $action personal profile";
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

			message_success("Profile edited successfully!");
			redirect($admin_route.'/'.$plugin_route.'/view/'.$row->id);
		}
	}

	if(!$csrf)
		$user->errors['email'] = "Form expired!";

	set_value('errors',array_merge($user->errors,$req->upload_errors));
}else
if(!empty($studentRow))
{
	$postdata = $req->post();
	$filedata = $req->files();

	$session = get_session();
    $userId = $session->id;

	$csrf = csrf_verify($postdata);

	$files_ok = true;
	if(!empty($filedata)){
		$postdata['image'] = $req->upload_files('image');
		if(!empty($req->upload_errors))
			$files_ok = false;
	}

	if($csrf && $files_ok && $student->validate_update($postdata))
	{
		if(user_can('edit_profile') || $userId === $studentRow->id)
		{

			if(isset($postdata['password']) && empty($postdata['password'])){
				unset($postdata['password']);
			}else{
				$postdata['password'] = password_hash($postdata['password'], PASSWORD_DEFAULT);
			}

			$postdata['date_updated'] = date("Y-m-d H:i:s");

			if(empty($postdata['image']))
				unset($postdata['image']);

			$student->update($studentRow->id,$postdata);


			if(!empty($postdata['image']) && file_exists($studentRow->image))
				unlink($studentRow->image);

			$user_id = $studentRow->id;

			/** save user roles **/
			if(user_can('edit_role'))
			{
				$roledata = [];
				
				foreach ($postdata as $key => $role_id) {
					
					if(!strstr($key, "role_"))
						continue;

					$roledata[] = $role_id;
				}
	 			
	 			/** disable all roles **/
	 			$user_roles_map->query('update '.$vars['optional_tables']['roles_map_table'] .' set disabled = 1 where user_id = :user_id',['user_id'=>$user_id]);
	 			
	 			/** saved to database **/
	 			foreach ($roledata as $role_id) {

 					$result = $user_roles_map->first(['role_id'=>$role_id,'user_id'=>$user_id]);
 					if($result)
 					{
 						$user_roles_map->update($result->id,['disabled'=>0]);
 					}else
 					{
 						$user_roles_map->insert([
 							'role_id'=>$role_id,
 							'user_id'=>$user_id,
 							'disabled'=>0,
 						]);

 					}

	 			}
			}

			//log changes in the log table
			$session = get_session();

			if(!empty($session->id)){
				$username = $session->first_name . ' '. $session->last_name;
				$user_id = $session->id;
			}else{
				$username = $studentRow->first_name . ' '. $studentRow->last_name;
				$user_id = $studentRow->id;
			}

			
			$action 		= 'edited';
			$newUser 		= $postdata['first_name'] . ' ' . $postdata['last_name'] ?? 'Noname';
			$table 			= 'users';
			$description 	= "$username $action personal profile";
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

			message_success("Profile edited successfully!");
			redirect($admin_route.'/'.$plugin_route.'/view/'.$studentRow->id);
		}
	}

	if(!$csrf)
		$user->errors['email'] = "Form expired!";

	set_value('errors',array_merge($user->errors,$req->upload_errors));
}else{

	message_fail("Record not found");
}

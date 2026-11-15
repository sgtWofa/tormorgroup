<?php
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$postdata = $req->post();
	$filedata = $req->files();

	$files_ok = true;
	if(!empty($filedata)){
		$postdata['image'] = $req->upload_files('image');
		$postdata['signature'] = $req->upload_files('signature');
		if(!empty($req->upload_errors))
			$files_ok = false;
	}

	$csrf = csrf_verify($postdata);
	$postdata['disabled'] = !empty($postdata['disabled']) ? 1 : 0;
	$hasSimAssignmentsField = array_key_exists('sim_assignments_json', $postdata);
	$simAssignmentRows = function_exists('users_manager_normalize_sim_assignments')
		? users_manager_normalize_sim_assignments($postdata['sim_assignments_json'] ?? '[]')
		: [];

	if(function_exists('users_manager_validate_sim_assignments')){
		$simAssignmentErrors = users_manager_validate_sim_assignments($simAssignmentRows);
		if(!empty($simAssignmentErrors)){
			$user->errors['sim_assignments'] = implode(' ', $simAssignmentErrors);
		}
	}

	unset($postdata['sim_assignments_json']);

	$formIsValid = false;
	if($csrf && $files_ok){
		$user->validate_insert($postdata);
		$formIsValid = empty($user->errors);
	}

	if($formIsValid)
	{
	//dd($postdata);die;
		if(user_can('add_user'))
		{
			$postdata['password'] = password_hash($postdata['password'], PASSWORD_DEFAULT);
			$postdata['date_created'] = date("Y-m-d H:i:s");			
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
			
			$user->insert($postdata);
			
			$user_id = $user->insert_id;

			if($hasSimAssignmentsField && function_exists('users_manager_save_sim_assignments')){
				try {
					users_manager_save_sim_assignments((int)$user_id, $simAssignmentRows, (int)($postdata['created_by'] ?? 0));
				} catch (\Throwable $e) {
					error_log('[users-manager] Assigned SIM data was not saved for new user: ' . $e->getMessage());
				}
			}

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
 
					$user_roles_map->insert([
						'role_id'=>$role_id,
						'user_id'=>$user_id,
						'disabled'=>0,
					]);

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

			$action 		= 'added';
			$newUser 		= $postdata['first_name'] . ' ' . $postdata['last_name'] ?? 'Noname';
			$description 	= "$username $action a new user called $newUser";
			$date_created 	= date("Y-m-d H:i:s");
			$table 			= 'users';

		    $log = new \UsersManager\Log;
		    $log_activity = $log->insert([
			 	'table_name'=>$table,
			 	'action'=>$action,
			 	'user_id'=>$user_id,
			 	'user_name'=>$username,
			 	'description'=>$description,
			 	'date_created'=>$date_created,
			 ]);

			message_success("Record added successfully!");
			redirect($admin_route.'/'.$plugin_route.'/view/'.$user->insert_id);
		}
	}

	if(!$csrf)
		$user->errors['email'] = "Form expired!";
	
	set_value('errors',$user->errors);


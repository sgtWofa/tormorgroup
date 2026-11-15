<?php

if(!empty($row))
{
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');
	
	$postdata = $req->post();
	$filedata = $req->files();
	$postdata['id'] = $row->id;

	$csrf = csrf_verify($postdata);
	$postdata['disabled'] = !empty($postdata['disabled']) ? 1 : 0;
	$simAssignmentRows = function_exists('users_manager_normalize_sim_assignments')
		? users_manager_normalize_sim_assignments($postdata['sim_assignments_json'] ?? '[]')
		: [];
	$postedRoleIds = [];
	foreach($postdata as $key => $roleId){
		if(strpos((string)$key, 'role_') !== 0){
			continue;
		}
		$roleId = (int)$roleId;
		if($roleId > 0){
			$postedRoleIds[] = $roleId;
		}
	}
	$simAssignmentRoleNames = function_exists('users_manager_role_names_from_ids')
		? users_manager_role_names_from_ids($postedRoleIds)
		: [];

	$canManageSharedAccounts = user_can('is_admin');
	$sharedAccountAssignmentProvided = $canManageSharedAccounts && array_key_exists('shared_account_assignments_json', $postdata);
	$sharedAccountAssignments = [];
	$sharedAccountAssignmentRoleNames = [];
	if($canManageSharedAccounts){
		try {
			$sharedAccountAssignmentRoleNames = user_can('edit_role')
				? users_manager_shared_account_role_names_from_ids($postedRoleIds)
				: users_manager_shared_account_role_names_for_user((int)$row->id);
			if($sharedAccountAssignmentProvided){
				$sharedAccountAssignments = users_manager_normalize_shared_account_keys($postdata['shared_account_assignments_json']);
				if(!users_manager_shared_account_roles_eligible($sharedAccountAssignmentRoleNames) && !empty($sharedAccountAssignments)){
					throw new \InvalidArgumentException('Shared accounts may only be assigned to admins, operations managers, or general managers.');
				}
			}
			if(!users_manager_shared_account_roles_eligible($sharedAccountAssignmentRoleNames)){
				$sharedAccountAssignments = [];
			}
		} catch(\Throwable $e) {
			$user->errors['shared_accounts'] = $e->getMessage();
		}
	}

	if(function_exists('users_manager_validate_sim_assignments')){
		$simAssignmentErrors = users_manager_validate_sim_assignments($simAssignmentRows, [
			'target_role_names' => $simAssignmentRoleNames,
		]);
		if(!empty($simAssignmentErrors)){
			$user->errors['sim_assignments'] = implode(' ', $simAssignmentErrors);
		}
	}

	unset($postdata['sim_assignments_json']);
	unset($postdata['shared_account_assignments_json']);

	$files_ok = true;
	if(!empty($filedata)){
		$postdata['image'] = $req->upload_files('image');
		$postdata['signature'] = $req->upload_files('signature');
		if(!empty($req->upload_errors))
			$files_ok = false;
	}

	if (array_key_exists('branch_id', $postdata)) {
	    if (is_null($postdata['branch_id']) || trim((string)$postdata['branch_id']) === '') {
	        $postdata['branch_id'] = null; // Or use 0 if your database schema does not allow NULL
	    } else {
	        $postdata['branch_id'] = (int)$postdata['branch_id'];
	    }
	}

	$formIsValid = false;
	if($csrf && $files_ok){
		$user->validate_update($postdata);
		$formIsValid = empty($user->errors);
	}

	if($formIsValid)
	{
		if(user_can('edit_user') || user_can('edit_profile'))
		{
			$newPassword = trim((string)($postdata['password'] ?? ''));
			if($newPassword === ''){
				unset($postdata['password'], $postdata['retype_password']);
			}else{
				$postdata['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
				unset($postdata['retype_password']);
			}

			$postdata['date_updated'] = date("Y-m-d H:i:s");
			unset($postdata['id']);

			if(empty($postdata['image']))
				unset($postdata['image']);

			if(empty($postdata['signature']))
				unset($postdata['signature']);


			if(!empty($postdata['image']) && file_exists($row->image))
				unlink($row->image);

			if(!empty($postdata['signature']) && file_exists($row->signature))
				unlink($row->signature);//log changes in the log table
			
			$session = get_session();

			if(!empty($session->id)){
				$username = $session->first_name . ' '. $session->last_name;
				$user_id = $session->id;
			}else{
				$username = $row->first_name . ' '. $row->last_name;
				$user_id = $row->id;
			}

			$postdata['updated_by'] = $user_id;

			$user->update($row->id,$postdata);
			if(!empty($user->has_error)){
				$user->errors['general'] = 'User was not updated. ' . $user->error;
				message_fail($user->errors['general']);
				set_value('errors',array_merge($user->errors,$req->upload_errors));
				return;
			}

			$user_id = $row->id;

			if(function_exists('users_manager_save_sim_assignments')){
				try {
					users_manager_save_sim_assignments((int)$row->id, $simAssignmentRows, (int)$postdata['updated_by'], [
						'target_role_names' => $simAssignmentRoleNames,
					]);
				} catch (\Throwable $e) {
					$user->errors['sim_assignments'] = 'Assigned SIM data was not saved. ' . $e->getMessage();
					message_fail($user->errors['sim_assignments']);
					set_value('errors',array_merge($user->errors,$req->upload_errors));
					return;
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

			if($canManageSharedAccounts && ($sharedAccountAssignmentProvided
	 			|| !users_manager_shared_account_roles_eligible($sharedAccountAssignmentRoleNames))){
	 			try {
	 				users_manager_save_shared_account_assignments(
	 					(int)$row->id,
	 					$sharedAccountAssignments,
	 					(int)$postdata['updated_by']
	 				);
	 			} catch(\Throwable $e) {
	 				$user->errors['shared_accounts'] = 'Shared-account assignments were not saved. ' . $e->getMessage();
	 				message_fail($user->errors['shared_accounts']);
	 				set_value('errors', array_merge($user->errors, $req->upload_errors));
	 				return;
	 			}
			}

			$action 		= 'updated';
			$newUser 		= $postdata['first_name'] . ' ' . $postdata['last_name'] ?? 'Noname';
			$description 	= "$username $action records of user called $newUser";
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

			message_success("User updated successfully. Assigned SIM data saved.");
			redirect($admin_route.'/'.$plugin_route.'/view/'.$row->id);
		}else{
			$user->errors['general'] = "You don't have permission to edit this user.";
			message_fail($user->errors['general']);
		}
	}

	if(!$csrf)
		$user->errors['email'] = "Form expired!";
	else if(!$formIsValid && !empty($user->errors))
		message_fail("User was not updated. Please correct the highlighted errors.");

	set_value('errors',array_merge($user->errors,$req->upload_errors));
}else{

	message_fail("Record not found");
}

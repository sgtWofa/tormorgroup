<?php

if(!empty($row))
{
	$postdata = $req->post();
	$csrf = csrf_verify($postdata);

	if($csrf)
	{
		if(user_can('delete_user'))
		{
			if((int)($row->deleted ?? 0) !== 1)
			{
				message_fail("This user is not deleted.");
				redirect($admin_route.'/'.$plugin_route);
				exit;
			}

			$session = get_session();
			$username = trim((string)(($session->first_name ?? '') . ' ' . ($session->last_name ?? '')));
			$user_id = (int)($session->id ?? 0);
			if($username === ''){
				$username = 'System';
			}

			$user->update($row->id,[
				'deleted' => 0,
				'disabled' => 1,
				'date_deleted' => null,
				'date_updated' => date("Y-m-d H:i:s"),
				'updated_by' => $user_id,
			]);

			$restoredUser = trim((string)(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')));
			if($restoredUser === ''){
				$restoredUser = 'Noname';
			}

			$log = new \UsersManager\Log;
			$log->insert([
				'table_name' => 'users',
				'action' => 'restored',
				'user_id' => $user_id,
				'user_name' => $username,
				'description' => "$username restored $restoredUser in users",
				'date_created' => date("Y-m-d H:i:s"),
			]);

			message_success("User restored successfully. The profile remains disabled until an admin enables it.");
			redirect($admin_route.'/'.$plugin_route.'/deleted');
			exit;
		}
	}

	$user->errors['email'] = "Form expired!";
	set_value('errors',$user->errors);
}else{

	message_fail("Record not found");
}

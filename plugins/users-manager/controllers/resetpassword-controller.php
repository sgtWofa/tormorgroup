<?php

if(!empty($row))
{
    // Set timezone to Accra (Africa/Accra)
    date_default_timezone_set('Africa/Accra');
    
    $postdata = $req->post();
    $filedata = $req->files();
    $postdata['id'] = $row->id;

    $csrf = csrf_verify($postdata);

if($csrf)
    {
        if(user_can('reset_password'))
        {

        $keyLength = 8;
        $randStr = reset_paaword($keyLength);
        if (empty($randStr) || !function_exists('basic_auth_ensure_force_password_change_column') || !basic_auth_ensure_force_password_change_column()) {
            message_fail("Could not reset the password. Please try again or contact support.");
            return;
        }

        $db = new \Core\Database;
        $db->query(
            "UPDATE users
             SET password = :password, force_password_change = 1, date_updated = :date_updated
             WHERE id = :id",
            [
                'password' => password_hash($randStr, PASSWORD_DEFAULT),
                'date_updated' => date('Y-m-d H:i:s'),
                'id' => (int)$row->id,
            ]
        );
        if ($db->has_error || $db->affected_rows !== 1) {
            error_log('[users-manager] Admin password reset update failed: ' . ($db->error ?: 'User record was not updated.'));
            message_fail("Could not reset the password. Please try again or contact support.");
            return;
        }

        message_success("PASSWORD CHANGED TO: " . $randStr);
            
            //log changes in the log table
            $session = get_session();

            if(!empty($session->id)){
                $username = $session->first_name . ' '. $session->last_name;
                $user_id = $session->id;
            }else{
                $username = $row->first_name . ' '. $row->last_name;
                $user_id = $row->id;
            }

            $action         = 'password reset';
            $newUser        = $row->first_name . ' ' . $row->last_name ?? 'Noname';
            $table          = 'users';
            $description    = "$username $action the password of $newUser";
            $date_created   = date("Y-m-d H:i:s");

            $log = new \UsersManager\Log;
            $log_activity = $log->insert([
                'table_name'=>$table,
                'action'=>$action,
                'user_id'=>$user_id,
                'user_name'=>$username,
                'description'=>$description,
                'date_created'=>$date_created,
             ]);

            redirect($admin_route.'/'.$plugin_route.'/view/'.$row->id);
    }else{

        message_fail("Access Denied. You don't have permission to perform this action!!");
    }
}

    if(!$csrf)
    message_fail("Session Expired! Refresh page!!");

    set_value('errors',array_merge($user->errors));

}else{

    message_fail("Record not found");
}
	



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
        $postdata['password'] = password_hash($randStr, PASSWORD_DEFAULT);
        if(!empty($randStr)){
             message_success("PASSWORD CHANGED TO: ".$randStr);
        }       
    
        $user->update($id, $postdata);
            
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
	



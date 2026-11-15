<?php
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$postdata = $req->post();

	$csrf = csrf_verify($postdata);
	if($csrf && $mycategory->validate_insert($postdata))
	{
		
		if(empty($postdata['slug'])){
			$postdata['slug'] = $mycategory->str_to_url($postdata['category']);
		}

		$num = 0;
		while ($num < 10 && $mycategory->first(['slug'=>$postdata['slug']])) {
			$postdata['slug'] = $postdata['slug'] . rand(0,99);
			$num++;
		}
		
		$mycategory->insert($postdata);

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
		$mycategory 		= $postdata['category'];
		$table 			= 'mycategories';
		$description 	= "$username $action a new category called $mycategory to $table";
		$date_created 	= date("Y-m-d H:i:s");

	    $log = new \MyWallets\Log;
	    $log_activity = $log->insert([
		 	'table_name'=>$table,
		 	'action'=>$action,
		 	'user_id'=>$user_id,
		 	'user_name'=>$username,
		 	'description'=>$description,
		 	'date_created'=>$date_created,
		 ]);
 
		message_success("Record added successfully!");
		redirect($admin_route.'/'.$plugin_route);

	}

	if(!$csrf)
		$mycategory->errors['category'] = "Form expired!";
	
	set_value('errors',$mycategory->errors);


<?php
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$postdata = $req->post();

	$csrf = csrf_verify($postdata);
	if($csrf && $category->validate_insert($postdata))
	{
		if(user_can('add_category'))
		{
			
			if(empty($postdata['slug'])){
				$postdata['slug'] = $category->str_to_url($postdata['category']);
			}

			$num = 0;
			while ($num < 10 && $category->first(['slug'=>$postdata['slug']])) {
				$postdata['slug'] = $postdata['slug'] . rand(0,99);
				$num++;
			}
			
			$category->insert($postdata);

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
			$category 		= $postdata['category'];
			$table 			= 'categories';
			$description 	= "$username $action a new category called $category to $table";
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
 
			message_success("Record added successfully!");
			redirect($admin_route.'/'.$plugin_route);
		}
	}

	if(!$csrf)
		$category->errors['email'] = "Form expired!";
	
	set_value('errors',$category->errors);


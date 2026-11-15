<?php
	
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$info = [];
	$info['success'] = false;
	$info['message'] = "";
	$info['errors'] = [];

	$postdata = $req->post();
	$filedata = $req->files();

	$files_ok = true;
	if(!empty($filedata)){
		$postdata['image'] = $req->upload_files('image');
		$notificationSoundUpload = function_exists('admin_settings_upload_notification_sound') ? admin_settings_upload_notification_sound($req) : '';
		if($notificationSoundUpload !== ''){
			$postdata['notification_sound'] = $notificationSoundUpload;
		}
		if(!empty($req->upload_errors))
			$files_ok = false;
	}
	
	$csrf = csrf_verify($postdata);
	if($csrf && $files_ok && $setting->validate_insert($postdata))
	{
		if(user_can('add_settings'))
		{
			$postdata = $setting->normalize_hour_settings_payload($postdata);
			$postdata['user_id'] = $ses->user('id');
			$postdata['date_created'] = date("Y-m-d H:i:s");
			$setting->insert($postdata);

			if($setting->has_error){
				$info['message'] = "Could not save settings";
				$setting->errors['database'] = $setting->error ?: 'Database insert failed';
				$info['errors'] = array_merge($setting->errors,$req->upload_errors);
				echo json_encode($info);
				die;
			}
 			
 			$info['success'] = true;
			$info['message'] = "Record added successfully!";
			if(function_exists('admin_settings_record_cfa_rate_history')){
				admin_settings_record_cfa_rate_history($setting->insert_id ?? 0, $postdata['buying_rate'] ?? 0, $postdata['selling_rate'] ?? 0, 'settings-created');
			}
			if(function_exists('admin_settings_log_change')){
				admin_settings_log_change('created', 'System settings were created.');
			}

		}
	}

	if(!$csrf)
		$setting->errors['company_name'] = "Form expired!";
	
	$info['errors'] = $setting->errors;

	echo json_encode($info);
die;

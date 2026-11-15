<?php

	header('Content-Type: application/json; charset=utf-8');
	try {
	$editSetting = new \Settings\Setting;
	$editRequest = new \Core\Request;
	$editId = (int)(URL(3) ?? 0);
	$editRow = $editId > 0 ? $editSetting->first(['id' => $editId]) : null;

	$info = [];
	$info['success'] = false;
	$info['message'] = "Settings were not saved. Please review the errors and try again.";
	$info['errors'] = [];

if(!empty($editRow))
{
	$postdata = $editRequest->post();
	$filedata = $editRequest->files();
	$postdata['id'] = $editRow->id;

	$csrf = csrf_verify($postdata);

	$files_ok = true;
	if(!empty($filedata)){
		$postdata['image'] = $editRequest->upload_files('image');
		$notificationSoundUpload = function_exists('admin_settings_upload_notification_sound') ? admin_settings_upload_notification_sound($editRequest) : '';
		if($notificationSoundUpload !== ''){
			$postdata['notification_sound'] = $notificationSoundUpload;
		}
		if(!empty($editRequest->upload_errors))
			$files_ok = false;
	}

	if($csrf && $files_ok && $editSetting->validate_update($postdata))
	{
		if(user_can('edit_settings'))
		{
			$postdata = $editSetting->normalize_hour_settings_payload($postdata);
			$changedFields = [];
			foreach($postdata as $field => $newValue){
				if(in_array($field, ['id', 'csrf', 'date_updated'], true)){
					continue;
				}
				if($field === 'image' && empty($newValue)){
					continue;
				}

				$oldValue = property_exists($editRow, $field) ? $editRow->{$field} : null;
				$newString = trim((string)$newValue);
				$oldString = trim((string)$oldValue);
				if($newString !== $oldString){
					$changedFields[] = $field;
				}
			}

			$postdata['date_updated'] = date("Y-m-d H:i:s");
			unset($postdata['id']);

			if(empty($postdata['image']))
				unset($postdata['image']);

			$editSetting->update($editRow->id,$postdata);

			if($editSetting->has_error){
				$info['message'] = "Could not save settings";
				$editSetting->errors['database'] = $editSetting->error ?: 'Database update failed';
				$info['errors'] = array_merge($editSetting->errors,$editRequest->upload_errors);
				echo json_encode($info);
				die;
			}

			if(!empty($postdata['image']) && !empty($editRow->image) && file_exists($editRow->image))
				unlink($editRow->image);
 
			$info['success'] = true;
			$info['message'] = "Record edited successfully!";
			if(function_exists('admin_settings_record_cfa_rate_history')){
				$oldBuying = (float)($editRow->buying_rate ?? 0);
				$oldSelling = (float)($editRow->selling_rate ?? 0);
				$newBuying = (float)($postdata['buying_rate'] ?? $oldBuying);
				$newSelling = (float)($postdata['selling_rate'] ?? $oldSelling);
				if($oldBuying !== $newBuying || $oldSelling !== $newSelling){
					admin_settings_record_cfa_rate_history($editRow->id, $newBuying, $newSelling, 'settings-updated');
				}
			}
			if(function_exists('admin_settings_log_change')){
				$changedFields = array_values(array_unique($changedFields));
				$description = empty($changedFields)
					? 'System settings were saved with no detected field changes.'
					: 'System settings updated: ' . implode(', ', $changedFields) . '.';
				admin_settings_log_change('updated', $description);
			}
		}
		else{
			$info['message'] = 'You do not have permission to edit system settings.';
		}
	}

	if(!$csrf){
		$editSetting->errors['company_name'] = "Form expired!";
		$info['message'] = 'Your settings form expired. Refresh the page and try again.';
	}

	if(!$files_ok){
		$info['message'] = 'One or more uploaded files could not be processed.';
	}

	 $info['errors'] = array_merge($editSetting->errors,$editRequest->upload_errors);
}else{

	$info['message'] = "Record not found";
}

	} catch (\Throwable $exception) {
		error_log('[admin-settings] Settings update failed: ' . $exception->getMessage());
		$info['success'] = false;
		$info['message'] = 'The server encountered an error while saving settings. Check the server log and try again.';
		$info['errors'] = [];
	}

echo json_encode($info, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
die;

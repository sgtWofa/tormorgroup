<?php

if($req->posted())
{
	$errors = [];
	
	$new_password = $_POST['new_password'] ?? '';
	$confirm_password = $_POST['confirm_password'] ?? '';
	
	// Validate passwords
	if(empty($new_password)) {
		$errors['new_password'] = "New password is required";
	}
	elseif(strlen($new_password) < 6) {
		$errors['new_password'] = "Password must be at least 6 characters long";
	}
	
	if($new_password !== $confirm_password) {
		$errors['confirm_password'] = "Passwords do not match";
	}
	
	if(empty($errors)) {
		// Hash the new password
		$password_hash = password_hash($new_password, PASSWORD_DEFAULT);
		
		// Update the user's password
		$user->update($id, ['password' => $password_hash]);
		
		// Set success message
		message_success("Password has been reset successfully!");
		
		// Redirect back to edit page
		redirect($admin_route.'/'.$plugin_route.'/edit/'.$id);
	}
	else {
		// Store errors for display
		set_value('errors', $errors);
	}
}

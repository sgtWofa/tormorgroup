<?php

if (!$req->posted() || empty($row)) {
	return;
}

$session = get_session();
$userId = (int)($session->id ?? 0);
$targetUserId = (int)($row->id ?? 0);
$isOwnAccount = $userId > 0 && $userId === $targetUserId;
$isForcedPasswordChange = $isOwnAccount && (int)($session->force_password_change ?? 0) === 1;

if (!$isOwnAccount && !user_can('edit_profile')) {
	message_fail("Access denied. You don't have permission to change this password.");
	return;
}

$postdata = $req->post();
$errors = [];
$newPassword = (string)($postdata['new_password'] ?? '');
$confirmPassword = (string)($postdata['confirm_password'] ?? '');

if (!csrf_verify($postdata)) {
	$errors['general'] = 'Form expired. Please refresh and try again.';
}
if ($newPassword === '') {
	$errors['new_password'] = 'New password is required.';
} elseif (strlen($newPassword) < 6) {
	$errors['new_password'] = 'Password must be at least 6 characters long.';
}
if ($newPassword !== $confirmPassword) {
	$errors['confirm_password'] = 'Passwords do not match.';
}

if (empty($errors)) {
	if (!function_exists('basic_auth_ensure_force_password_change_column') || !basic_auth_ensure_force_password_change_column()) {
		$errors['general'] = 'Password change is temporarily unavailable. Please contact support.';
	} else {
		$db = new \Core\Database;
		$db->query(
			"UPDATE users
			 SET password = :password, force_password_change = 0, date_updated = :date_updated
			 WHERE id = :id",
			[
				'password' => password_hash($newPassword, PASSWORD_DEFAULT),
				'date_updated' => date('Y-m-d H:i:s'),
				'id' => $targetUserId,
			]
		);

		if ($db->has_error || $db->affected_rows !== 1) {
			error_log('[profile] Password update failed: ' . ($db->error ?: 'User record was not updated.'));
			$errors['general'] = 'Password could not be changed. Please try again.';
		} else {
			if ($isForcedPasswordChange) {
				message_success('Password changed. Sign in with your new password to continue.');
				$ses->logout();
				redirect('login');
			}

			message_success('Password has been changed successfully.');
			redirect($admin_route . '/' . $plugin_route . '/edit/' . $targetUserId);
		}
	}
}

set_value('errors', $errors);

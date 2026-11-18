<?php

namespace CashpickupPermissions;

use \Core\Database;

defined('ROOT') or die("Direct script access denied");

function has_role(Database $db, int $userId, array $roles): bool
{
	if ($userId <= 0) {
		return false;
	}

	$rows = $db->query(
		"SELECT LOWER(TRIM(ur.role)) AS role_name
		 FROM user_roles_map urm
		 JOIN user_roles ur ON ur.id = urm.role_id
		 WHERE urm.disabled = 0
		 AND urm.user_id = :user_id",
		['user_id' => $userId]
	) ?: [];
	if ($db->has_error) {
		throw new \RuntimeException('Unable to verify Cashpickup role.');
	}

	foreach ($rows as $row) {
		if (in_array(strtolower(trim((string)($row->role_name ?? ''))), $roles, true)) {
			return true;
		}
	}
	return false;
}

function is_operations_manager(Database $db, int $userId): bool
{
	return \user_can('is_operations_manager') || has_role($db, $userId, [
		'operations manager',
		'operations_manager',
		'operationsmanager',
		'ops manager',
		'ops_manager',
		'opsmanager',
		'is_operations_manager',
	]);
}

function is_general_manager(Database $db, int $userId): bool
{
	return \user_can('is_general_manager') || has_role($db, $userId, [
		'general manager',
		'general_manager',
		'generalmanager',
		'gm',
		'is_general_manager',
	]);
}

function can_assign_customer_payout(Database $db, int $userId): bool
{
	return \user_can('is_admin')
		|| is_operations_manager($db, $userId)
		|| is_general_manager($db, $userId);
}

function can_withdraw_customer_payout(Database $db, int $userId): bool
{
	if (\user_can('is_admin')) {
		return false;
	}
	if (is_operations_manager($db, $userId)) {
		return true;
	}
	return \user_can('add_cashpickup')
		&& !\user_can('is_manager')
		&& !is_general_manager($db, $userId);
}

function is_top_authority(Database $db, int $userId): bool
{
	if (\user_can('is_admin')
		|| \user_can('is_manager')
		|| \user_can('is_operations_manager')
		|| \user_can('is_general_manager')) {
		return true;
	}
	if ($userId <= 0) {
		return false;
	}

	$rows = $db->query(
		"SELECT LOWER(TRIM(ur.role)) AS role_name
		 FROM user_roles_map urm
		 JOIN user_roles ur ON ur.id = urm.role_id
		 WHERE urm.disabled = 0
		 AND urm.user_id = :user_id",
		['user_id' => $userId]
	) ?: [];
	if ($db->has_error) {
		throw new \RuntimeException('Unable to verify Cashpickup authority.');
	}

	$roleNames = [];
	foreach ($rows as $row) {
		$roleName = strtolower(trim((string)($row->role_name ?? '')));
		if ($roleName !== '') {
			$roleNames[] = $roleName;
		}
	}

	return !empty(array_intersect($roleNames, [
		'admin',
		'is_admin',
		'manager',
		'is_manager',
		'operations manager',
		'operations_manager',
		'operationsmanager',
		'ops manager',
		'ops_manager',
		'opsmanager',
		'is_operations_manager',
		'general manager',
		'general_manager',
		'generalmanager',
		'gm',
		'is_general_manager',
	]));
}

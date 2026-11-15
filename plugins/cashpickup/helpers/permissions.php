<?php

namespace CashpickupPermissions;

use \Core\Database;

defined('ROOT') or die("Direct script access denied");

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

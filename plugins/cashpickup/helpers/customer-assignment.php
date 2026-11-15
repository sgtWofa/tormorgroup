<?php

namespace CashpickupCustomerAssignment;

use \Core\Database;

defined('ROOT') or die("Direct script access denied");

require_once __DIR__ . '/customer-ledger.php';
require_once __DIR__ . '/transfer-allocation.php';

function ensure_schema(Database $db): void
{
	$db->query(
		"CREATE TABLE IF NOT EXISTS cashpickup_customer_assignments (
			id BIGINT unsigned NOT NULL AUTO_INCREMENT,
			customer_id BIGINT unsigned NOT NULL,
			assigned_user_id INT unsigned NOT NULL,
			amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			assigned_by INT unsigned NULL,
			date_created DATETIME NULL,
			cancelled_by INT unsigned NULL,
			date_cancelled DATETIME NULL,
			PRIMARY KEY (id),
			KEY customer_status (customer_id, status),
			KEY assigned_status (assigned_user_id, status)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
	);
	if ($db->has_error) {
		throw new \RuntimeException('Unable to prepare customer payout assignments.');
	}
	foreach ([
		'cancelled_by' => 'INT unsigned NULL',
		'date_cancelled' => 'DATETIME NULL',
	] as $columnName => $columnDefinition) {
		$column = $db->query(
			"SELECT COLUMN_NAME
			 FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = :db
			 AND TABLE_NAME = 'cashpickup_customer_assignments'
			 AND COLUMN_NAME = :column_name
			 LIMIT 1",
			['db' => DB_NAME, 'column_name' => $columnName]
		);
		if (empty($column)) {
			$db->query("ALTER TABLE cashpickup_customer_assignments ADD COLUMN {$columnName} {$columnDefinition}");
			if ($db->has_error) {
				throw new \RuntimeException('Unable to prepare customer payout cancellation history.');
			}
		}
	}

	$column = $db->query(
		"SELECT COLUMN_NAME
		 FROM INFORMATION_SCHEMA.COLUMNS
		 WHERE TABLE_SCHEMA = :db
		 AND TABLE_NAME = 'cashpickups'
		 AND COLUMN_NAME = 'customer_assignment_id'
		 LIMIT 1",
		['db' => DB_NAME]
	);
	if (empty($column)) {
		$db->query("ALTER TABLE cashpickups ADD COLUMN customer_assignment_id BIGINT unsigned NULL");
		if ($db->has_error) {
			throw new \RuntimeException('Unable to prepare customer payout tracking.');
		}
	}

	$index = $db->query(
		"SELECT INDEX_NAME
		 FROM INFORMATION_SCHEMA.STATISTICS
		 WHERE TABLE_SCHEMA = :db
		 AND TABLE_NAME = 'cashpickups'
		 AND INDEX_NAME = 'idx_cashpickups_customer_assignment'
		 LIMIT 1",
		['db' => DB_NAME]
	);
	if (empty($index)) {
		$db->query("CREATE INDEX idx_cashpickups_customer_assignment ON cashpickups (customer_assignment_id)");
		if ($db->has_error) {
			throw new \RuntimeException('Unable to index customer payout tracking.');
		}
	}
}

function migrate_legacy_customer_assignments(Database $db, int $customerId, int $actorId = 0): void
{
	if ($customerId <= 0 || !$db->table_exists(['cashpickup_transfers'])) {
		return;
	}

	ensure_schema($db);
	require_once __DIR__ . '/customer-ledger.php';
	require_once __DIR__ . '/transfer-allocation.php';
	$customerRows = $db->query(
		"SELECT phone_number, id_number
		 FROM cashpickup_customers
		 WHERE deleted = 0 AND id = :id
		 LIMIT 1",
		['id' => $customerId]
	) ?: [];
	if (empty($customerRows)) {
		return;
	}
	$customer = $customerRows[0];
	list($matchSql, $params) = \CashpickupLedger\build_customer_match_sql(
		$customerId,
		trim((string)($customer->phone_number ?? '')),
		trim((string)($customer->id_number ?? '')),
		'r',
		'migrate_assignment'
	);

	if (!$db->beginTransaction()) {
		throw new \RuntimeException('Unable to prepare existing payout assignments.');
	}
	try {
		$db->query(
			"SELECT id FROM cashpickup_customers WHERE id = :id AND deleted = 0 FOR UPDATE",
			['id' => $customerId]
		);
		$rows = $db->query(
			"SELECT t.id, t.receive_id, t.to_user_id
			 FROM cashpickup_transfers t
			 JOIN cashpickups r ON r.id = t.receive_id
			 WHERE t.receive_id IS NOT NULL
			 AND t.to_user_id IS NOT NULL AND t.to_user_id > 0
			 AND (t.approval_status IS NULL OR t.approval_status = '' OR LOWER(t.approval_status) IN ('approved','pending'))
			 AND (r.deleted IS NULL OR r.deleted = 0)
			 AND LOWER(r.transaction_side) = 'receive'
			 AND " . \CashpickupLedger\normalized_cashpickup_credit_mode_sql('r') . " <> 'credit'
			 AND (r.approval_status IS NULL OR r.approval_status = '' OR LOWER(r.approval_status) = 'approved')
			 AND {$matchSql}
			 ORDER BY t.id ASC
			 FOR UPDATE",
			$params
		) ?: [];
		$groups = [];
		foreach ($rows as $row) {
			$receiveId = (int)($row->receive_id ?? 0);
			$userId = (int)($row->to_user_id ?? 0);
			if ($receiveId > 0 && $userId > 0) {
				$groups[$receiveId . ':' . $userId] = [$receiveId, $userId];
			}
		}

		foreach ($groups as [$receiveId, $userId]) {
			$remaining = \CashpickupTransferAllocation\get_transfer_remaining_amount($db, $receiveId, $userId);
			$db->query(
				"UPDATE cashpickup_transfers
				 SET approval_status = 'reassigned'
				 WHERE receive_id = :receive_id
				 AND to_user_id = :user_id
				 AND (approval_status IS NULL OR approval_status = '' OR LOWER(approval_status) IN ('approved','pending'))",
				['receive_id' => $receiveId, 'user_id' => $userId]
			);
			if ($db->has_error) {
				throw new \RuntimeException('Unable to retire a legacy receive-specific assignment.');
			}
			if ($remaining <= 0.00001) {
				continue;
			}
			$db->query(
				"INSERT INTO cashpickup_customer_assignments
				 (customer_id, assigned_user_id, amount, status, assigned_by, date_created)
				 VALUES (:customer_id, :assigned_user_id, :amount, 'active', :assigned_by, :date_created)",
				[
					'customer_id' => $customerId,
					'assigned_user_id' => $userId,
					'amount' => round($remaining, 2),
					'assigned_by' => $actorId > 0 ? $actorId : null,
					'date_created' => date('Y-m-d H:i:s'),
				]
			);
			if ($db->has_error || $db->affected_rows !== 1) {
				throw new \RuntimeException('Unable to migrate an existing payout assignment.');
			}
		}
		if (!$db->commit()) {
			throw new \RuntimeException('Unable to complete existing assignment migration.');
		}
	} catch (\Throwable $e) {
		$db->rollBack();
		throw $e;
	}
}

function get_customer_assignments(Database $db, int $customerId): array
{
	if ($customerId <= 0) {
		return [];
	}

	ensure_schema($db);
	return $db->query(
		"SELECT a.id, a.customer_id, a.assigned_user_id, a.amount, a.status,
		        a.assigned_by, a.date_created, a.cancelled_by, a.date_cancelled,
		        u.first_name AS assigned_first_name, u.last_name AS assigned_last_name,
		        cancelled_user.first_name AS cancelled_first_name,
		        cancelled_user.last_name AS cancelled_last_name,
		        assigner.first_name AS assigner_first_name,
		        assigner.last_name AS assigner_last_name,
		        COALESCE(used.used_amount,0) AS used_amount,
		        GREATEST(0, a.amount - COALESCE(used.used_amount,0)) AS remaining_amount
		 FROM cashpickup_customer_assignments a
		 LEFT JOIN users u ON u.id = a.assigned_user_id
		 LEFT JOIN users cancelled_user ON cancelled_user.id = a.cancelled_by
		 LEFT JOIN users assigner ON assigner.id = a.assigned_by
		 LEFT JOIN (
			SELECT customer_assignment_id,
			       COALESCE(SUM(COALESCE(amount_paid_out,0) + COALESCE(pickup_charge,0)),0) AS used_amount
			FROM cashpickups
			WHERE (deleted IS NULL OR deleted = 0)
			AND LOWER(transaction_side) = 'pay'
			AND LOWER(COALESCE(source,'')) = 'customer_withdrawal'
			AND transaction_status = 'Approved'
			AND confirmed_by IS NOT NULL AND confirmed_by != ''
			AND COALESCE(amount_paid_out,0) > 0
			AND (
				approval_status IS NULL
				OR approval_status = ''
				OR LOWER(approval_status) IN ('approved','pending')
			)
			AND customer_assignment_id IS NOT NULL
			GROUP BY customer_assignment_id
		 ) AS used ON used.customer_assignment_id = a.id
		 WHERE a.customer_id = :customer_id
		 ORDER BY a.id DESC",
		['customer_id' => $customerId]
	) ?: [];
}

function get_remaining_amount(Database $db, int $customerId, int $assignedUserId = 0): float
{
	$remaining = 0.0;
	foreach (get_customer_assignments($db, $customerId) as $assignment) {
		if (strtolower(trim((string)($assignment->status ?? ''))) !== 'active') {
			continue;
		}
		if ($assignedUserId > 0 && (int)($assignment->assigned_user_id ?? 0) !== $assignedUserId) {
			continue;
		}
		$remaining += max(0.0, (float)($assignment->remaining_amount ?? 0));
	}
	return round($remaining, 2);
}

function plan_allocation(Database $db, int $customerId, int $assignedUserId, float $amount): array
{
	$result = ['slices' => [], 'allocated' => 0.0, 'shortfall' => round(max(0.0, $amount), 2)];
	if ($customerId <= 0 || $assignedUserId <= 0 || $amount <= 0) {
		return $result;
	}

	$remaining = round($amount, 2);
	foreach (get_customer_assignments($db, $customerId) as $assignment) {
		if (strtolower(trim((string)($assignment->status ?? ''))) !== 'active'
			|| (int)($assignment->assigned_user_id ?? 0) !== $assignedUserId) {
			continue;
		}
		$available = round(max(0.0, (float)($assignment->remaining_amount ?? 0)), 2);
		$take = min($available, $remaining);
		if ($take <= 0.00001) {
			continue;
		}
		$result['slices'][] = [
			'assignment_id' => (int)($assignment->id ?? 0),
			'amount' => round($take, 2),
		];
		$result['allocated'] += $take;
		$remaining = round(max(0.0, $remaining - $take), 2);
		if ($remaining <= 0.00001) {
			break;
		}
	}

	$result['allocated'] = round($result['allocated'], 2);
	$result['shortfall'] = round($remaining, 2);
	return $result;
}

function get_legacy_remaining_amount(Database $db, int $customerId, int $assignedUserId = 0): float
{
	$remaining = 0.0;
	foreach (get_legacy_allocation_slices($db, $customerId, $assignedUserId) as $slice) {
		$remaining += (float)($slice['amount'] ?? 0);
	}
	return round(max(0.0, $remaining), 2);
}

function get_legacy_allocation_slices(Database $db, int $customerId, int $assignedUserId): array
{
	if ($customerId <= 0 || !$db->table_exists(['cashpickup_transfers'])) {
		return [];
	}
	if (!function_exists('CashpickupTransferAllocation\\get_transfer_remaining_amount')) {
		return [];
	}

	$customerRows = $db->query(
		"SELECT phone_number, id_number
		 FROM cashpickup_customers
		 WHERE deleted = 0 AND id = :id
		 LIMIT 1",
		['id' => $customerId]
	) ?: [];
	if (empty($customerRows)) {
		return [];
	}
	$customer = $customerRows[0];
	list($matchSql, $params) = \CashpickupLedger\build_customer_match_sql(
		$customerId,
		trim((string)($customer->phone_number ?? '')),
		trim((string)($customer->id_number ?? '')),
		'r',
		'legacy_assignment'
	);
	if ($assignedUserId > 0) {
		$params['assigned_user_id'] = $assignedUserId;
	}
	$rows = $db->query(
		"SELECT t.receive_id, t.to_user_id
		 FROM cashpickup_transfers t
		 JOIN cashpickups r ON r.id = t.receive_id
		 WHERE t.receive_id IS NOT NULL
		 AND t.to_user_id IS NOT NULL AND t.to_user_id > 0
		 AND (t.approval_status IS NULL OR t.approval_status = '' OR LOWER(t.approval_status) IN ('approved','pending'))
		 AND (r.deleted IS NULL OR r.deleted = 0)
		 AND LOWER(r.transaction_side) = 'receive'
		 AND " . \CashpickupLedger\normalized_cashpickup_credit_mode_sql('r') . " <> 'credit'
		 AND (r.approval_status IS NULL OR r.approval_status = '' OR LOWER(r.approval_status) = 'approved')
		 AND {$matchSql}" . ($assignedUserId > 0 ? " AND t.to_user_id = :assigned_user_id" : '') . "
		 GROUP BY t.receive_id, t.to_user_id",
		$params
	) ?: [];

	$slices = [];
	foreach ($rows as $row) {
		$amount = \CashpickupTransferAllocation\get_transfer_remaining_amount(
			$db,
			(int)($row->receive_id ?? 0),
			(int)($row->to_user_id ?? 0)
		);
		if ($amount > 0.00001) {
			$slices[] = [
				'receive_id' => (int)($row->receive_id ?? 0),
				'amount' => round($amount, 2),
			];
		}
	}
	return $slices;
}

function get_unassigned_balance(Database $db, int $customerId): float
{
	$balance = \CashpickupLedger\fetch_customer_balance_metrics($db, $customerId);
	$reserved = get_remaining_amount($db, $customerId)
		+ get_legacy_remaining_amount($db, $customerId);
	return round(max(0.0, (float)($balance['balance'] ?? 0) - $reserved), 2);
}

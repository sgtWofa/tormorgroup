<?php

namespace CashpickupTransferAllocation;

use \Core\Database;

defined('ROOT') or die("Direct script access denied");

function ensure_cashpickup_transfer_schema(Database $db): void
{
	if (!$db->table_exists(['cashpickup_transfers'])) {
		$db->query(
			"CREATE TABLE IF NOT EXISTS cashpickup_transfers (
				id BIGINT unsigned NOT NULL AUTO_INCREMENT,
				receive_id BIGINT unsigned NULL,
				from_user_id INT unsigned NULL,
				source_user_id INT unsigned NULL,
				to_user_id INT unsigned NULL,
				amount FLOAT(10,2) DEFAULT 0.00,
				transfer_charge FLOAT(10,2) DEFAULT 0.00,
				approval_status varchar(20) null,
				cancelled_by INT unsigned NULL,
				date_cancelled DATETIME NULL,
				sender_mode varchar(20) null,
				sender_channel_key varchar(150) null,
				receiver_mode varchar(20) null,
				receiver_channel_key varchar(150) null,
				transfer_mode varchar(20) null,
				transfer_channel varchar(100) null,
				transfer_bank varchar(150) null,
				denos_json JSON null,
				created_by INT unsigned null,
				date_created datetime default null,
				PRIMARY KEY (id),
				KEY receive_id (receive_id),
				KEY from_user_id (from_user_id),
				KEY to_user_id (to_user_id),
				KEY date_created (date_created)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
		);
		return;
	}

	try {
		$hasCharge = $db->query(
			"SELECT COLUMN_NAME
			 FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = :db
			 AND TABLE_NAME = 'cashpickup_transfers'
			 AND COLUMN_NAME = 'transfer_charge'
			 LIMIT 1",
			['db' => DB_NAME]
		);
		if (empty($hasCharge)) {
			$db->query("ALTER TABLE cashpickup_transfers ADD COLUMN transfer_charge FLOAT(10,2) DEFAULT 0.00 AFTER amount");
		}
		$hasSourceUser = $db->query(
			"SELECT COLUMN_NAME
			 FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = :db
			 AND TABLE_NAME = 'cashpickup_transfers'
			 AND COLUMN_NAME = 'source_user_id'
			 LIMIT 1",
			['db' => DB_NAME]
		);
		if (empty($hasSourceUser)) {
			$db->query("ALTER TABLE cashpickup_transfers ADD COLUMN source_user_id INT unsigned NULL AFTER from_user_id");
		}
		$hasCancelledBy = $db->query(
			"SELECT COLUMN_NAME
			 FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = :db
			 AND TABLE_NAME = 'cashpickup_transfers'
			 AND COLUMN_NAME = 'cancelled_by'
			 LIMIT 1",
			['db' => DB_NAME]
		);
		if (empty($hasCancelledBy)) {
			$db->query("ALTER TABLE cashpickup_transfers ADD COLUMN cancelled_by INT unsigned NULL");
		}
		$hasDateCancelled = $db->query(
			"SELECT COLUMN_NAME
			 FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = :db
			 AND TABLE_NAME = 'cashpickup_transfers'
			 AND COLUMN_NAME = 'date_cancelled'
			 LIMIT 1",
			['db' => DB_NAME]
		);
		if (empty($hasDateCancelled)) {
			$db->query("ALTER TABLE cashpickup_transfers ADD COLUMN date_cancelled DATETIME NULL");
		}
	} catch (\Throwable $e) {
		// Older installations may not permit optional transfer-ledger migrations.
	}
}

function normalize_cashpickup_transfer_allocations(Database $db, int $receiveId): void
{
	if ($receiveId <= 0) {
		return;
	}

	try {
		if (!$db->table_exists(['cashpickup_transfers']) || !$db->table_exists(['cashpickups'])) {
			return;
		}

		$transferRows = $db->query(
			"SELECT id, to_user_id, amount, transfer_charge, approval_status, date_created
			 FROM cashpickup_transfers
			 WHERE receive_id = :rid
			 ORDER BY date_created ASC, id ASC",
			['rid' => $receiveId]
		);
		if (empty($transferRows)) {
			return;
		}

		$hasCustomerAssignmentColumn = has_customer_assignment_tracking_column($db);
		$assignmentSelect = $hasCustomerAssignmentColumn ? ', customer_assignment_id' : '';
		$payoutRows = $db->query(
			"SELECT id, created_by, assigned_user_id, amount_requested, amount_paid_out, pickup_charge, date_created{$assignmentSelect}
			 FROM cashpickups
			 WHERE (deleted IS NULL OR deleted = 0)
			 AND LOWER(transaction_side) = 'pay'
			 AND receive_id = :rid
			 AND transaction_status = 'Approved'
			 AND (confirmed_by IS NOT NULL AND confirmed_by != '')
			 AND COALESCE(amount_paid_out,0) > 0
			 AND (
				approval_status IS NULL
				OR approval_status = ''
				OR LOWER(approval_status) IN ('approved','pending')
			 )
			 ORDER BY date_created ASC, id ASC",
			['rid' => $receiveId]
		);
		if (empty($payoutRows)) {
			return;
		}

		$rowsByUser = [];
		foreach ($transferRows as $row) {
			$status = strtolower(trim((string)($row->approval_status ?? 'approved')));
			if ($status !== '' && !in_array($status, ['approved', 'pending'], true)) {
				continue;
			}
			$uid = (int)($row->to_user_id ?? 0);
			if ($uid <= 0) {
				continue;
			}
			if (!isset($rowsByUser[$uid])) {
				$rowsByUser[$uid] = [];
			}
			$rowsByUser[$uid][] = [
				'id' => (int)($row->id ?? 0),
				'amount' => round((float)($row->amount ?? 0), 2),
				'charge' => round((float)($row->transfer_charge ?? 0), 2),
				'status' => $status === '' ? 'approved' : $status,
				'dirty' => false,
			];
		}

		if (empty($rowsByUser)) {
			return;
		}

		foreach ($payoutRows as $payout) {
			if ((int)($payout->customer_assignment_id ?? 0) > 0) {
				continue;
			}
			$uid = (int)($payout->assigned_user_id ?? 0);
			if ($uid <= 0) {
				$uid = (int)($payout->created_by ?? 0);
			}
			if ($uid <= 0 || empty($rowsByUser[$uid])) {
				continue;
			}

			$remaining = round((float)($payout->amount_paid_out ?? 0) + (float)($payout->pickup_charge ?? 0), 2);
			if ($remaining <= 0) {
				continue;
			}

			foreach ($rowsByUser[$uid] as &$alloc) {
				if ($remaining <= 0) {
					break;
				}
				if (($alloc['amount'] ?? 0) <= 0) {
					continue;
				}

				$originalAmount = (float)($alloc['amount'] ?? 0);
				$originalCharge = (float)($alloc['charge'] ?? 0);
				$consume = min($remaining, $originalAmount);
				if ($consume <= 0) {
					continue;
				}

				$newAmount = round(max(0.0, $originalAmount - $consume), 2);
				if ($newAmount <= 0.00001) {
					$newCharge = 0.0;
					$newStatus = 'completed';
				} else {
					$ratio = $originalAmount > 0 ? ($newAmount / $originalAmount) : 0.0;
					$newCharge = round(max(0.0, $originalCharge * $ratio), 2);
					$newStatus = 'approved';
				}

				if (abs($newAmount - $originalAmount) > 0.00001 || abs($newCharge - $originalCharge) > 0.00001 || $newStatus !== ($alloc['status'] ?? 'approved')) {
					$alloc['amount'] = $newAmount;
					$alloc['charge'] = $newCharge;
					$alloc['status'] = $newStatus;
					$alloc['dirty'] = true;
				}

				$remaining = round(max(0.0, $remaining - $consume), 2);
			}
			unset($alloc);
		}

		foreach ($rowsByUser as $allocs) {
			foreach ($allocs as $alloc) {
				if (empty($alloc['dirty']) || (int)($alloc['id'] ?? 0) <= 0) {
					continue;
				}
				$db->query(
					"UPDATE cashpickup_transfers
					 SET amount = :amount,
					     transfer_charge = :transfer_charge,
					     approval_status = :approval_status
					 WHERE id = :id",
					[
						'id' => (int)$alloc['id'],
						'amount' => (float)$alloc['amount'],
						'transfer_charge' => (float)$alloc['charge'],
						'approval_status' => (string)$alloc['status'],
					]
				);
			}
		}
	} catch (\Throwable $e) {
		// Ignore normalization failures; callers will continue with the raw ledger.
	}
}

function has_customer_assignment_tracking_column(Database $db): bool
{
	static $checked = false;
	static $exists = false;
	if ($checked) {
		return $exists;
	}
	$checked = true;
	try {
		$rows = $db->query(
			"SELECT COLUMN_NAME
			 FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = :db
			 AND TABLE_NAME = 'cashpickups'
			 AND COLUMN_NAME = 'customer_assignment_id'
			 LIMIT 1",
			['db' => DB_NAME]
		);
		$exists = !empty($rows);
	} catch (\Throwable $e) {
		$exists = false;
	}
	return $exists;
}

function customer_assignment_payout_filter(Database $db): string
{
	return has_customer_assignment_tracking_column($db)
		? " AND (customer_assignment_id IS NULL OR customer_assignment_id = 0)"
		: '';
}

function get_transfer_allocation(Database $db, int $receiveId, int $userId, int $excludeId = 0): array
{
	$result = [
		'gross' => 0.0,
		'charge' => 0.0,
		'payable' => 0.0,
	];

	if ($receiveId <= 0 || $userId <= 0) {
		return $result;
	}

	try {
		if (!$db->table_exists(['cashpickup_transfers'])) {
			return $result;
		}
		$payoutPaid = get_actor_payout_total($db, $receiveId, $userId, $excludeId);
		if ($payoutPaid <= 0) {
			$payoutPaid = 0.0;
		}
		$rows = $db->query(
			"SELECT id, amount, transfer_charge, approval_status, date_created
			 FROM cashpickup_transfers
			 WHERE receive_id = :rid
			 AND to_user_id = :uid
			 AND (
				approval_status IS NULL
				OR approval_status = ''
				OR LOWER(approval_status) IN ('approved','pending')
			 )
			 ORDER BY date_created ASC, id ASC",
			[
				'rid' => $receiveId,
				'uid' => $userId,
			]
		);
		if (empty($rows)) {
			return $result;
		}

		foreach ($rows as $row) {
			$rowId = (int)($row->id ?? 0);
			if ($excludeId > 0 && $rowId === $excludeId) {
				continue;
			}

			$rowAmount = round(max(0.0, (float)($row->amount ?? 0)), 2);
			$rowCharge = round(max(0.0, (float)($row->transfer_charge ?? 0)), 2);
			if ($rowAmount <= 0.00001) {
				continue;
			}

			if ($payoutPaid <= 0.00001) {
				$result['gross'] += $rowAmount;
				$result['charge'] += $rowCharge;
				continue;
			}

			if ($payoutPaid >= $rowAmount) {
				$payoutPaid = round($payoutPaid - $rowAmount, 2);
				continue;
			}

			$remainingAmount = round(max(0.0, $rowAmount - $payoutPaid), 2);
			$ratio = $rowAmount > 0 ? ($remainingAmount / $rowAmount) : 0.0;
			$remainingCharge = round(max(0.0, $rowCharge * $ratio), 2);

			$result['gross'] += $remainingAmount;
			$result['charge'] += $remainingCharge;
			$payoutPaid = 0.0;
		}
		$result['gross'] = round($result['gross'], 2);
		$result['charge'] = round($result['charge'], 2);
		$result['payable'] = round(max(0, $result['gross'] - $result['charge']), 2);
	} catch (\Throwable $e) {
		// fallback to zero allocation
	}

	return $result;
}

function get_actor_payout_total(Database $db, int $receiveId, int $actorId, int $excludeId = 0): float
{
	if ($receiveId <= 0 || $actorId <= 0) {
		return 0.0;
	}

	try {
		$params = [
			'rid' => $receiveId,
			'uid' => $actorId,
		];
		$assignmentFilter = customer_assignment_payout_filter($db);
		$sql = "SELECT COALESCE(SUM(COALESCE(amount_paid_out,0) + COALESCE(pickup_charge,0)),0) AS paid
				FROM cashpickups
				WHERE (deleted IS NULL OR deleted = 0)
				AND LOWER(transaction_side) = 'pay'
				AND (
					approval_status IS NULL
					OR approval_status = ''
					OR LOWER(approval_status) IN ('approved','pending')
				)
				AND transaction_status = 'Approved'
				AND (confirmed_by IS NOT NULL AND confirmed_by != '')
				AND COALESCE(amount_paid_out,0) > 0
				AND receive_id = :rid
				AND (created_by = :uid OR assigned_user_id = :uid){$assignmentFilter}";
		if ($excludeId > 0) {
			$sql .= " AND id != :exclude_id";
			$params['exclude_id'] = $excludeId;
		}
		$rows = $db->query($sql, $params);
		if (!empty($rows)) {
			return round((float)($rows[0]->paid ?? 0), 2);
		}
	} catch (\Throwable $e) {
		// fallback to zero
	}

	return 0.0;
}

function get_transfer_remaining_amounts(Database $db, int $receiveId, array $userIds, int $excludeId = 0): array
{
	$ids = [];
	foreach ($userIds as $userId) {
		$userId = (int)$userId;
		if ($userId > 0) {
			$ids[$userId] = $userId;
		}
	}
	$ids = array_values($ids);

	$result = [];
	foreach ($ids as $userId) {
		$result[$userId] = 0.0;
	}
	if ($receiveId <= 0 || empty($ids)) {
		return $result;
	}

	try {
		if (!$db->table_exists(['cashpickup_transfers'])) {
			return $result;
		}

		$transferParams = ['rid' => $receiveId];
		$transferPlaceholders = [];
		foreach ($ids as $idx => $userId) {
			$key = 'tuid' . $idx;
			$transferPlaceholders[] = ':' . $key;
			$transferParams[$key] = $userId;
		}

		$grossByUser = array_fill_keys($ids, 0.0);
		$transferRows = $db->query(
			"SELECT to_user_id, COALESCE(SUM(amount),0) AS gross
			 FROM cashpickup_transfers
			 WHERE receive_id = :rid
			 AND to_user_id IN (" . implode(',', $transferPlaceholders) . ")
			 AND (
				approval_status IS NULL
				OR approval_status = ''
				OR LOWER(approval_status) IN ('approved','pending')
			 )
			 GROUP BY to_user_id",
			$transferParams
		);
		if (is_array($transferRows)) {
			foreach ($transferRows as $row) {
				$uid = (int)($row->to_user_id ?? 0);
				if (isset($grossByUser[$uid])) {
					$grossByUser[$uid] = round(max(0.0, (float)($row->gross ?? 0)), 2);
				}
			}
		}

		$payoutParams = ['rid' => $receiveId];
		$createdPlaceholders = [];
		$assignedPlaceholders = [];
		foreach ($ids as $idx => $userId) {
			$createdKey = 'cuid' . $idx;
			$assignedKey = 'auid' . $idx;
			$createdPlaceholders[] = ':' . $createdKey;
			$assignedPlaceholders[] = ':' . $assignedKey;
			$payoutParams[$createdKey] = $userId;
			$payoutParams[$assignedKey] = $userId;
		}

		$assignmentFilter = customer_assignment_payout_filter($db);
		$payoutSql = "SELECT created_by, assigned_user_id,
					(COALESCE(amount_paid_out,0) + COALESCE(pickup_charge,0)) AS paid
				FROM cashpickups
				WHERE (deleted IS NULL OR deleted = 0)
				AND LOWER(transaction_side) = 'pay'
				AND (
					approval_status IS NULL
					OR approval_status = ''
					OR LOWER(approval_status) IN ('approved','pending')
				)
				AND transaction_status = 'Approved'
				AND (confirmed_by IS NOT NULL AND confirmed_by != '')
				AND COALESCE(amount_paid_out,0) > 0
				AND receive_id = :rid
				AND (
					created_by IN (" . implode(',', $createdPlaceholders) . ")
					OR assigned_user_id IN (" . implode(',', $assignedPlaceholders) . ")
				){$assignmentFilter}";
		if ($excludeId > 0) {
			$payoutSql .= " AND id != :exclude_id";
			$payoutParams['exclude_id'] = $excludeId;
		}

		$paidByUser = array_fill_keys($ids, 0.0);
		$payoutRows = $db->query($payoutSql, $payoutParams);
		if (is_array($payoutRows)) {
			foreach ($payoutRows as $row) {
				$paid = round(max(0.0, (float)($row->paid ?? 0)), 2);
				if ($paid <= 0.00001) {
					continue;
				}

				$createdBy = (int)($row->created_by ?? 0);
				$assignedTo = (int)($row->assigned_user_id ?? 0);
				if (isset($paidByUser[$createdBy])) {
					$paidByUser[$createdBy] += $paid;
				}
				if ($assignedTo !== $createdBy && isset($paidByUser[$assignedTo])) {
					$paidByUser[$assignedTo] += $paid;
				}
			}
		}

		foreach ($ids as $userId) {
			$result[$userId] = round(max(0.0, (float)($grossByUser[$userId] ?? 0) - (float)($paidByUser[$userId] ?? 0)), 2);
		}
	} catch (\Throwable $e) {
		// Preserve the old fail-closed behavior.
	}

	return $result;
}

function get_transfer_remaining_amount(Database $db, int $receiveId, int $userId, int $excludeId = 0): float
{
	if ($receiveId <= 0 || $userId <= 0) {
		return 0.0;
	}

	try {
		$remaining = get_transfer_remaining_amounts($db, $receiveId, [$userId], $excludeId);
		return round(max(0, (float)($remaining[$userId] ?? 0)), 2);
	} catch (\Throwable $e) {
		return 0.0;
	}
}

function get_total_active_transfer_remaining(Database $db, int $receiveId): float
{
	if ($receiveId <= 0) {
		return 0.0;
	}

	try {
		if (!$db->table_exists(['cashpickup_transfers'])) {
			return 0.0;
		}

		$rows = $db->query(
			"SELECT DISTINCT to_user_id
			 FROM cashpickup_transfers
			 WHERE receive_id = :rid
			 AND to_user_id IS NOT NULL
			 AND to_user_id > 0
			 AND (
				approval_status IS NULL
				OR approval_status = ''
				OR LOWER(approval_status) IN ('approved','pending')
			 )",
			['rid' => $receiveId]
		);
		if (!is_array($rows)) {
			$rows = [];
		}

		$userIds = [];
		foreach ($rows as $row) {
			$uid = (int)($row->to_user_id ?? 0);
			if ($uid <= 0) {
				continue;
			}
			$userIds[] = $uid;
		}

		$total = 0.0;
		$remainingByUser = get_transfer_remaining_amounts($db, $receiveId, $userIds);
		foreach ($remainingByUser as $remaining) {
			$total += (float)$remaining;
		}

	return round(max(0.0, $total), 2);
	} catch (\Throwable $e) {
		return 0.0;
	}
}

function backfill_cashpickup_payout_assignees_once(Database $db): void
{
	$root = dirname(__DIR__, 3);
	$marker = $root . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . 'cashpickup_payout_assignee_backfill_20260419.done';
	if (file_exists($marker)) {
		return;
	}

	try {
		if (!$db->table_exists(['cashpickups'])) {
			return;
		}
		$assignedCol = $db->query(
			"SELECT COLUMN_NAME
			 FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = :db
			 AND TABLE_NAME = 'cashpickups'
			 AND COLUMN_NAME = 'assigned_user_id'
			 LIMIT 1",
			['db' => DB_NAME]
		);
		if (empty($assignedCol)) {
			return;
		}

		$db->query(
			"UPDATE cashpickups p
			 LEFT JOIN cashpickups r ON r.id = p.receive_id
			 SET p.assigned_user_id = COALESCE(NULLIF(r.assigned_user_id, 0), p.created_by)
			 WHERE p.transaction_side = 'pay'
			 AND (p.assigned_user_id IS NULL OR p.assigned_user_id = 0)
			 AND p.receive_id IS NOT NULL
			 AND p.receive_id > 0"
		);

		$dir = dirname($marker);
		if (!is_dir($dir)) {
			@mkdir($dir, 0777, true);
		}
		@file_put_contents($marker, 'done ' . date('c'));
	} catch (\Throwable $e) {
		// ignore; the live query fallbacks still work for new rows
	}
}

function get_visible_transfer_receive_ids(Database $db, int $userId): array
{
	if ($userId <= 0) {
		return [];
	}

	try {
		if (!$db->table_exists(['cashpickup_transfers'])) {
			return [];
		}

		$rows = $db->query(
			"SELECT DISTINCT receive_id
			 FROM cashpickup_transfers
			 WHERE receive_id IS NOT NULL
			 AND to_user_id = :uid
			 ORDER BY receive_id DESC",
			['uid' => $userId]
		);
		if (!is_array($rows)) {
			$rows = [];
		}

		$ids = [];
		foreach ($rows as $row) {
			$rid = (int)($row->receive_id ?? 0);
			if ($rid <= 0) {
				continue;
			}
			$ids[] = $rid;
		}

		$ids = array_values(array_unique(array_filter($ids, static function ($v) {
			return (int)$v > 0;
		})));
		rsort($ids);
		return $ids;
	} catch (\Throwable $e) {
		return [];
	}
}

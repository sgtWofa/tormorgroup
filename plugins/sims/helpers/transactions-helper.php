<?php

use Core\Database;

if (!function_exists('sim_ensure_runtime_columns')) {
	function sim_ensure_runtime_columns(): void
	{
		try {
			$db = new Database;
			$needed = [
				'rejection_reason' => 'TEXT NULL',
				'denos' => 'LONGTEXT NULL',
			];

			foreach ($needed as $column => $definition) {
				$exists = $db->query(
					"SELECT COLUMN_NAME
					 FROM INFORMATION_SCHEMA.COLUMNS
					 WHERE TABLE_SCHEMA = :db
					   AND TABLE_NAME = 'sims'
					   AND COLUMN_NAME = :column
					 LIMIT 1",
					['db' => DB_NAME, 'column' => $column]
				);

				if (empty($exists)) {
					try {
						$db->query("ALTER TABLE sims ADD COLUMN {$column} {$definition}");
					} catch (\Throwable $e) {
					}
				}
			}
		} catch (\Throwable $e) {
		}
	}
}

if (!function_exists('sim_normalize_status')) {
	function sim_normalize_status(?string $status, string $fallback = 'Unassigned'): string
	{
		$status = trim((string)$status);
		if ($status === '') {
			$status = trim($fallback);
		}

		$normalized = strtolower($status);
		return match ($normalized) {
			'approved' => 'Approved',
			'sold - retail' => 'Sold - retail',
			'sold - wholesale' => 'Sold - wholesale',
			'assigned' => 'Assigned',
			'unassigned' => 'Unassigned',
			'expired' => 'Expired',
			'declined' => 'Declined',
			'rejected', 'reject', 'rejection' => 'Rejected',
			default => $status !== '' ? ucfirst($status) : 'Unassigned',
		};
	}
}

if (!function_exists('sim_status_has_accounting_effect')) {
	function sim_status_has_accounting_effect(?string $status): bool
	{
		$status = strtolower(sim_normalize_status($status));
		return in_array($status, ['approved', 'sold - retail', 'sold - wholesale'], true);
	}
}

if (!function_exists('sim_status_is_reversed')) {
	function sim_status_is_reversed(?string $status): bool
	{
		$status = strtolower(sim_normalize_status($status));
		return in_array($status, ['declined', 'rejected'], true);
	}
}

if (!function_exists('sim_table_exists')) {
	function sim_table_exists(string $tableName): bool
	{
		static $cache = [];
		$tableName = trim($tableName);
		if ($tableName === '') {
			return false;
		}
		if (array_key_exists($tableName, $cache)) {
			return $cache[$tableName];
		}

		try {
			$db = new Database;
			$cache[$tableName] = $db->table_exists([$tableName]);
		} catch (\Throwable $e) {
			$cache[$tableName] = false;
		}
		return $cache[$tableName];
	}
}

if (!function_exists('sim_db_error_message')) {
	function sim_db_error_message(Database $db, string $fallback): string
	{
		$message = trim((string)($db->error ?? ''));
		return $message !== '' ? $message : $fallback;
	}
}

if (!function_exists('sim_find_transaction_drawer_context')) {
	function sim_find_transaction_drawer_context(object $transaction): ?array
	{
		$transId = (int)($transaction->id ?? 0);
		$db = new Database;

		if ($transId > 0 && sim_table_exists('drawer_denos')) {
			try {
				$rows = $db->query(
					"SELECT drawer_id, user_id
					 FROM drawer_denos
					 WHERE trans_id = :trans_id
					 ORDER BY id DESC
					 LIMIT 1",
					['trans_id' => $transId]
				) ?: [];
				if (!empty($rows)) {
					$drawerId = (int)($rows[0]->drawer_id ?? 0);
					$drawerUserId = (int)($rows[0]->user_id ?? 0);
					if ($drawerId > 0 && $drawerUserId > 0) {
						return ['drawer_id' => $drawerId, 'drawer_user_id' => $drawerUserId];
					}
				}
			} catch (\Throwable $e) {
			}
		}

		if ($transId > 0 && sim_table_exists('drawer_balances')) {
			try {
				$rows = $db->query(
					"SELECT drawer_id, user_id
					 FROM drawer_balances
					 WHERE trans_id = :trans_id
					   AND channel_type = 'cash'
					   AND channel_key = 'CASH_DRAWER'
					 ORDER BY id DESC
					 LIMIT 1",
					['trans_id' => $transId]
				) ?: [];
				if (!empty($rows)) {
					$drawerId = (int)($rows[0]->drawer_id ?? 0);
					$drawerUserId = (int)($rows[0]->user_id ?? 0);
					if ($drawerId > 0 && $drawerUserId > 0) {
						return ['drawer_id' => $drawerId, 'drawer_user_id' => $drawerUserId];
					}
				}
			} catch (\Throwable $e) {
			}
		}

		$userIds = array_values(array_unique(array_filter([
			(int)($transaction->updated_by ?? 0),
			(int)($transaction->assigned ?? 0),
			(int)($transaction->created_by ?? 0),
		])));
		if (empty($userIds) || !sim_table_exists('cashdrawers')) {
			return null;
		}

		$transactionDate = trim((string)($transaction->date_updated ?? $transaction->date_created ?? ''));
		if ($transactionDate === '') {
			return null;
		}

		foreach ($userIds as $userId) {
			try {
				$rows = $db->query(
					"SELECT id, user_id
					 FROM cashdrawers
					 WHERE user_id = :user_id
					   AND DATE(date_created) = DATE(:transaction_date)
					 ORDER BY id DESC
					 LIMIT 1",
					['user_id' => $userId, 'transaction_date' => $transactionDate]
				) ?: [];
				if (!empty($rows)) {
					$drawerId = (int)($rows[0]->id ?? 0);
					$drawerUserId = (int)($rows[0]->user_id ?? $userId);
					if ($drawerId > 0 && $drawerUserId > 0) {
						return ['drawer_id' => $drawerId, 'drawer_user_id' => $drawerUserId];
					}
				}
			} catch (\Throwable $e) {
			}
		}

		return null;
	}
}

if (!function_exists('sim_refresh_cashdrawer_balance')) {
	function sim_refresh_cashdrawer_balance(int $drawerId, int $drawerUserId, int $transId, string $transStatus): ?float
	{
		if ($drawerId <= 0 || $drawerUserId <= 0 || !sim_table_exists('drawer_balances') || !sim_table_exists('drawer_denos')) {
			return null;
		}

		$db = new Database;
		$sumRows = $db->query(
			"SELECT
				COALESCE(SUM(volume),0) AS total_volume,
				COALESCE(SUM(CASE WHEN volume > 0 THEN volume ELSE 0 END),0) AS total_credit,
				COALESCE(SUM(CASE WHEN volume < 0 THEN ABS(volume) ELSE 0 END),0) AS total_debit
			 FROM drawer_denos
			 WHERE drawer_id = :drawer_id AND user_id = :user_id",
			['drawer_id' => $drawerId, 'user_id' => $drawerUserId]
		) ?: [];
		$currentClosing = !empty($sumRows) ? (float)($sumRows[0]->total_volume ?? 0) : 0.0;
		$totalCredit = !empty($sumRows) ? (float)($sumRows[0]->total_credit ?? 0) : 0.0;
		$totalDebit = !empty($sumRows) ? (float)($sumRows[0]->total_debit ?? 0) : 0.0;

		$rowsBal = $db->query(
			"SELECT *
			 FROM drawer_balances
			 WHERE drawer_id = :drawer_id
			   AND user_id = :user_id
			   AND channel_type = 'cash'
			   AND channel_key = 'CASH_DRAWER'
			 LIMIT 1",
			['drawer_id' => $drawerId, 'user_id' => $drawerUserId]
		) ?: [];

		if (empty($rowsBal)) {
			$db->query(
				"INSERT INTO drawer_balances
				 (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status)
				 VALUES
				 (:drawer_id, :user_id, 'cash', 'CASH_DRAWER', 0, :credit, :debit, :closing, :date_created, :trans_id, :trans_status)",
				[
					'drawer_id' => $drawerId,
					'user_id' => $drawerUserId,
					'credit' => $totalCredit,
					'debit' => $totalDebit,
					'closing' => $currentClosing,
					'date_created' => date('Y-m-d H:i:s'),
					'trans_id' => $transId,
					'trans_status' => $transStatus,
				]
			);
			return $db->has_error ? null : $currentClosing;
		}

		$rowBal = $rowsBal[0];
		$opening = (float)($rowBal->opening_balance ?? 0);
		$credit = max($totalCredit - $opening, 0.0);
		$db->query(
			"UPDATE drawer_balances
			 SET credit = :credit,
			     debit = :debit,
			     closing_balance = :closing,
			     trans_id = :trans_id,
			     trans_status = :trans_status
			 WHERE id = :id",
			[
				'credit' => $credit,
				'debit' => $totalDebit,
				'closing' => $currentClosing,
				'trans_id' => $transId,
				'trans_status' => $transStatus,
				'id' => $rowBal->id,
			]
		);

		return $db->has_error ? null : $currentClosing;
	}
}

if (!function_exists('sim_insert_cashdrawer_delta_deno')) {
	function sim_insert_cashdrawer_delta_deno(int $drawerId, int $drawerUserId, float $delta, int $transId, string $transStatus, int $actorUserId = 0): array
	{
		if ($drawerId <= 0 || $drawerUserId <= 0 || abs($delta) < 0.00001 || !sim_table_exists('drawer_denos')) {
			return ['ok' => true, 'closing_balance' => null, 'message' => ''];
		}

		$db = new Database;
		$db->query(
			"INSERT INTO drawer_denos
			 (drawer_id, user_id, denomination, `count`, volume, date_created, created_by, trans_id, trans_status)
			 VALUES
			 (:drawer_id, :user_id, :denomination, :count, :volume, :date_created, :created_by, :trans_id, :trans_status)",
			[
				'drawer_id' => $drawerId,
				'user_id' => $drawerUserId,
				'denomination' => abs($delta),
				'count' => $delta >= 0 ? 1 : -1,
				'volume' => $delta,
				'date_created' => date('Y-m-d H:i:s'),
				'created_by' => $actorUserId > 0 ? $actorUserId : $drawerUserId,
				'trans_id' => $transId,
				'trans_status' => $transStatus,
			]
		);

		if ($db->has_error) {
			return ['ok' => false, 'closing_balance' => null, 'message' => sim_db_error_message($db, 'Cashdrawer denomination reversal could not be saved.')];
		}

		return ['ok' => true, 'closing_balance' => sim_refresh_cashdrawer_balance($drawerId, $drawerUserId, $transId, $transStatus), 'message' => ''];
	}
}

if (!function_exists('sim_reverse_drawer_denos')) {
	function sim_reverse_drawer_denos(int $drawerId, int $drawerUserId, int $transId, string $transStatus, float $expectedAmount, int $actorUserId = 0): array
	{
		$result = ['ok' => true, 'had_rows' => false, 'closing_balance' => null, 'message' => ''];
		if ($drawerId <= 0 || $drawerUserId <= 0 || $transId <= 0 || !sim_table_exists('drawer_denos')) {
			return $result;
		}

		$db = new Database;
		$rows = $db->query(
			"SELECT denomination, `count`, volume
			 FROM drawer_denos
			 WHERE drawer_id = :drawer_id
			   AND user_id = :user_id
			   AND trans_id = :trans_id
			 ORDER BY id ASC",
			['drawer_id' => $drawerId, 'user_id' => $drawerUserId, 'trans_id' => $transId]
		) ?: [];

		if (empty($rows)) {
			return $result;
		}

		$totalAbs = 0.0;
		foreach ($rows as $row) {
			$totalAbs += abs((float)($row->volume ?? 0));
		}

		if ($expectedAmount > 0 && abs($totalAbs - $expectedAmount) > 0.01) {
			// Bulk sales store the whole batch against one reference SIM. Reverse only this SIM's amount.
			return sim_insert_cashdrawer_delta_deno($drawerId, $drawerUserId, -1 * $expectedAmount, $transId, $transStatus, $actorUserId) + ['had_rows' => true];
		}

		$result['had_rows'] = true;
		$createdBy = $actorUserId > 0 ? $actorUserId : $drawerUserId;
		$now = date('Y-m-d H:i:s');
		foreach ($rows as $row) {
			$count = (int)($row->count ?? 0);
			$volume = (float)($row->volume ?? 0);
			if ($count === 0 && abs($volume) < 0.00001) {
				continue;
			}

			$db->query(
				"INSERT INTO drawer_denos
				 (drawer_id, user_id, denomination, `count`, volume, date_created, created_by, trans_id, trans_status)
				 VALUES
				 (:drawer_id, :user_id, :denomination, :count, :volume, :date_created, :created_by, :trans_id, :trans_status)",
				[
					'drawer_id' => $drawerId,
					'user_id' => $drawerUserId,
					'denomination' => $row->denomination ?? 0,
					'count' => -1 * $count,
					'volume' => -1 * $volume,
					'date_created' => $now,
					'created_by' => $createdBy,
					'trans_id' => $transId,
					'trans_status' => $transStatus,
				]
			);

			if ($db->has_error) {
				return ['ok' => false, 'had_rows' => true, 'closing_balance' => null, 'message' => sim_db_error_message($db, 'Cashdrawer denomination reversal could not be saved.')];
			}
		}

		$result['closing_balance'] = sim_refresh_cashdrawer_balance($drawerId, $drawerUserId, $transId, $transStatus);
		return $result;
	}
}

if (!function_exists('sim_apply_cashdrawer_delta')) {
	function sim_apply_cashdrawer_delta(int $drawerId, int $drawerUserId, float $delta, int $transId, string $transStatus, int $actorUserId = 0): array
	{
		$denoResult = sim_insert_cashdrawer_delta_deno($drawerId, $drawerUserId, $delta, $transId, $transStatus, $actorUserId);
		if (empty($denoResult['ok']) || $denoResult['closing_balance'] !== null) {
			return $denoResult;
		}

		if ($drawerId <= 0 || $drawerUserId <= 0 || abs($delta) < 0.00001) {
			return ['ok' => true, 'closing_balance' => null, 'message' => ''];
		}

		$db = new Database;
		if (!sim_table_exists('drawer_balances')) {
			return ['ok' => false, 'closing_balance' => null, 'message' => 'Cashdrawer balance table is not available.'];
		}

		$rows = $db->query(
			"SELECT *
			 FROM drawer_balances
			 WHERE drawer_id = :drawer_id
			   AND user_id = :user_id
			   AND channel_type = 'cash'
			   AND channel_key = 'CASH_DRAWER'
			 LIMIT 1",
			['drawer_id' => $drawerId, 'user_id' => $drawerUserId]
		) ?: [];

		$opening = 0.0;
		$credit = 0.0;
		$debit = 0.0;
		$rowId = 0;
		if (!empty($rows)) {
			$row = $rows[0];
			$rowId = (int)($row->id ?? 0);
			$opening = (float)($row->opening_balance ?? 0);
			$credit = (float)($row->credit ?? 0);
			$debit = (float)($row->debit ?? 0);
		} else {
			$hdr = $db->query("SELECT amount FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawerId]) ?: [];
			$opening = !empty($hdr) ? (float)($hdr[0]->amount ?? 0) : 0.0;
		}

		$currentClosing = $opening + $credit - $debit;
		if (($currentClosing + $delta) < -0.00001) {
			return ['ok' => false, 'closing_balance' => null, 'message' => 'Current cashdrawer balance is not enough to reverse this SIM transaction.'];
		}

		if ($delta >= 0) {
			$credit += $delta;
		} else {
			$debit += abs($delta);
		}
		$closing = $opening + $credit - $debit;

		if ($rowId <= 0) {
			$db->query(
				"INSERT INTO drawer_balances
				 (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status)
				 VALUES
				 (:drawer_id, :user_id, 'cash', 'CASH_DRAWER', :opening, :credit, :debit, :closing, :date_created, :trans_id, :trans_status)",
				[
					'drawer_id' => $drawerId,
					'user_id' => $drawerUserId,
					'opening' => $opening,
					'credit' => $credit,
					'debit' => $debit,
					'closing' => $closing,
					'date_created' => date('Y-m-d H:i:s'),
					'trans_id' => $transId,
					'trans_status' => $transStatus,
				]
			);
		} else {
			$db->query(
				"UPDATE drawer_balances
				 SET credit = :credit,
				     debit = :debit,
				     closing_balance = :closing,
				     trans_id = :trans_id,
				     trans_status = :trans_status
				 WHERE id = :id",
				[
					'credit' => $credit,
					'debit' => $debit,
					'closing' => $closing,
					'trans_id' => $transId,
					'trans_status' => $transStatus,
					'id' => $rowId,
				]
			);
		}

		if ($db->has_error) {
			return ['ok' => false, 'closing_balance' => null, 'message' => sim_db_error_message($db, 'Cashdrawer reversal could not be saved.')];
		}

		return ['ok' => true, 'closing_balance' => $closing, 'message' => ''];
	}
}

if (!function_exists('sim_reverse_approved_effects')) {
	function sim_reverse_approved_effects(object $transaction, string $transStatus): array
	{
		$result = ['ok' => true, 'cashdrawer_closing_balance' => null, 'message' => ''];
		$transId = (int)($transaction->id ?? 0);
		if ($transId <= 0) {
			return $result;
		}

		$context = sim_find_transaction_drawer_context($transaction);
		if (empty($context)) {
			$dateLabel = 'the transaction date';
			$dateRaw = trim((string)($transaction->date_updated ?? $transaction->date_created ?? ''));
			if ($dateRaw !== '') {
				$timestamp = strtotime($dateRaw);
				if ($timestamp !== false) {
					$dateLabel = date('F j, Y', $timestamp);
				}
			}
			return [
				'ok' => false,
				'cashdrawer_closing_balance' => null,
				'message' => 'No historical cashdrawer could be resolved for ' . $dateLabel . ' for the user who sold/approved this SIM transaction. To keep the records consistent, decline or reject it against that original day\'s cashdrawer.',
			];
		}

		$drawerId = (int)($context['drawer_id'] ?? 0);
		$drawerUserId = (int)($context['drawer_user_id'] ?? 0);
		$actorUserId = (int)($transaction->updated_by ?? 0);
		$amount = (float)($transaction->amount ?? 0);
		$cashDelta = -1 * $amount;

		if (abs($cashDelta) > 0.00001) {
			$cashResult = sim_reverse_drawer_denos($drawerId, $drawerUserId, $transId, $transStatus, $amount, $actorUserId);
			if (empty($cashResult['ok'])) {
				return ['ok' => false, 'cashdrawer_closing_balance' => null, 'message' => $cashResult['message'] ?: 'Cashdrawer reversal failed.'];
			}

			if (!empty($cashResult['had_rows'])) {
				$result['cashdrawer_closing_balance'] = $cashResult['closing_balance'] ?? null;
			} else {
				$fallback = sim_apply_cashdrawer_delta($drawerId, $drawerUserId, $cashDelta, $transId, $transStatus, $actorUserId);
				if (empty($fallback['ok'])) {
					return ['ok' => false, 'cashdrawer_closing_balance' => null, 'message' => $fallback['message'] ?: 'Cashdrawer reversal failed.'];
				}
				$result['cashdrawer_closing_balance'] = $fallback['closing_balance'] ?? null;
			}
		}

		return $result;
	}
}

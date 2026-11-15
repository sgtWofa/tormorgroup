<?php

if (!function_exists('operations_account_ensure_denomination_history_schema')) {
	function operations_account_ensure_denomination_history_schema(\Core\Database $db): void
	{
		foreach (['drawer_denos', 'drawer_fx_denos'] as $table) {
			if (!$db->table_exists([$table])) {
				continue;
			}

			$schema = $db->query("SHOW COLUMNS FROM `{$table}`");
			if ($db->has_error || !is_array($schema)) {
				throw new \RuntimeException("Unable to inspect {$table}.");
			}

			$columns = [];
			foreach ($schema as $column) {
				$columns[strtolower((string)($column->Field ?? ''))] = true;
			}

			$alters = [];
			if (empty($columns['trans_id'])) {
				$alters[] = 'ADD COLUMN trans_id BIGINT NULL';
			}
			if (empty($columns['trans_status'])) {
				$alters[] = 'ADD COLUMN trans_status VARCHAR(100) NULL';
			}
			if (!empty($alters)) {
				$db->query("ALTER TABLE `{$table}` " . implode(', ', $alters));
				if ($db->has_error) {
					throw new \RuntimeException("Unable to add denomination history markers to {$table}.");
				}
			}
		}
	}
}

if (!function_exists('operations_account_sync_denomination_snapshot')) {
	function operations_account_sync_denomination_snapshot(
		\Core\Database $db,
		string $table,
		int $drawerId,
		int $ownerId,
		int $actorId,
		string $openingStatus,
		array $desiredCounts,
		string $currency = ''
	): void {
		if (!in_array($table, ['drawer_denos', 'drawer_fx_denos'], true)) {
			throw new \InvalidArgumentException('Unsupported cashdrawer denomination table.');
		}
		if (!$db->table_exists([$table])) {
			if (array_sum($desiredCounts) > 0) {
				throw new \RuntimeException("The {$table} table is unavailable.");
			}
			return;
		}

		$schema = $db->query("SHOW COLUMNS FROM `{$table}`");
		if ($db->has_error || !is_array($schema)) {
			throw new \RuntimeException("Unable to inspect {$table}.");
		}
		$columns = [];
		foreach ($schema as $column) {
			$columns[strtolower((string)($column->Field ?? ''))] = true;
		}
		if (empty($columns['trans_status'])) {
			throw new \RuntimeException("{$table} is missing the opening snapshot marker.");
		}
		$hasCurrency = !empty($columns['currency']);

		$params = [
			'drawer_id' => $drawerId,
			'user_id' => $ownerId,
			'opening_status' => $openingStatus,
		];
		$where = "drawer_id = :drawer_id
			AND user_id = :user_id
			AND LOWER(TRIM(COALESCE(trans_status, ''))) = :opening_status";
		if ($currency !== '' && $hasCurrency) {
			$where .= ' AND currency = :currency';
			$params['currency'] = $currency;
		}
		$rows = $db->query(
			"SELECT id, denomination, `count` AS total_count
			 FROM `{$table}` WHERE {$where} ORDER BY id ASC",
			$params
		);
		if ($db->has_error) {
			throw new \RuntimeException("Unable to load the {$table} opening snapshot.");
		}

		$existingByDenomination = [];
		foreach ($rows ?: [] as $row) {
			$key = number_format((float)($row->denomination ?? 0), 2, '.', '');
			$existingByDenomination[$key][] = $row;
		}
		$denominations = array_unique(array_merge(
			array_keys($existingByDenomination),
			array_keys($desiredCounts)
		));

		foreach ($denominations as $denominationKey) {
			$denomination = (float)$denominationKey;
			$desiredCount = max(0, (int)($desiredCounts[$denominationKey] ?? 0));
			$existingRows = $existingByDenomination[$denominationKey] ?? [];
			$existingCount = 0;
			foreach ($existingRows as $existingRow) {
				$existingCount += (int)($existingRow->total_count ?? 0);
			}

			if (empty($existingRows) && $desiredCount === 0) {
				continue;
			}
			if (count($existingRows) === 1 && $existingCount === $desiredCount) {
				continue;
			}

			if ($desiredCount > 0 && !empty($existingRows)) {
				$primaryId = (int)$existingRows[0]->id;
				$db->query(
					"UPDATE `{$table}`
					 SET `count` = :count, volume = :volume
					 WHERE id = :id AND drawer_id = :drawer_id AND user_id = :user_id
					   AND LOWER(TRIM(COALESCE(trans_status, ''))) = :opening_status",
					[
						'count' => $desiredCount,
						'volume' => $denomination * $desiredCount,
						'id' => $primaryId,
						'drawer_id' => $drawerId,
						'user_id' => $ownerId,
						'opening_status' => $openingStatus,
					]
				);
				if ($db->has_error) {
					throw new \RuntimeException("Unable to update a {$table} opening denomination.");
				}
				array_shift($existingRows);
			} elseif ($desiredCount > 0) {
				$insertColumns = ['drawer_id', 'user_id', 'denomination', '`count`', 'volume', 'date_created', 'created_by', 'trans_status'];
				$insertValues = [':drawer_id', ':user_id', ':denomination', ':count', ':volume', ':date_created', ':created_by', ':trans_status'];
				$insertParams = [
					'drawer_id' => $drawerId,
					'user_id' => $ownerId,
					'denomination' => $denomination,
					'count' => $desiredCount,
					'volume' => $denomination * $desiredCount,
					'date_created' => date('Y-m-d H:i:s'),
					'created_by' => $actorId,
					'trans_status' => $openingStatus,
				];
				if (!empty($columns['trans_id'])) {
					$insertColumns[] = 'trans_id';
					$insertValues[] = ':trans_id';
					$insertParams['trans_id'] = $drawerId;
				}
				if ($currency !== '' && $hasCurrency) {
					$insertColumns[] = 'currency';
					$insertValues[] = ':currency';
					$insertParams['currency'] = $currency;
				}
				$db->query(
					"INSERT INTO `{$table}` (" . implode(', ', $insertColumns) . ")
					 VALUES (" . implode(', ', $insertValues) . ")",
					$insertParams
				);
				if ($db->has_error) {
					throw new \RuntimeException("Unable to add a {$table} opening denomination.");
				}
				continue;
			}

			foreach ($existingRows as $existingRow) {
				$db->query(
					"DELETE FROM `{$table}`
					 WHERE id = :id AND drawer_id = :drawer_id AND user_id = :user_id
					   AND LOWER(TRIM(COALESCE(trans_status, ''))) = :opening_status",
					[
						'id' => (int)$existingRow->id,
						'drawer_id' => $drawerId,
						'user_id' => $ownerId,
						'opening_status' => $openingStatus,
					]
				);
				if ($db->has_error) {
					throw new \RuntimeException("Unable to remove a {$table} opening denomination.");
				}
			}
		}
	}
}

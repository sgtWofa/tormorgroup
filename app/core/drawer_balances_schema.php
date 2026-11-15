<?php

function ensure_drawer_balances_schema(): void
{
	static $ensured = false;
	if($ensured){
		return;
	}
	$ensured = true;

	$db = new \Core\Database;
	$schemaStatus = $db->query(
		"SELECT c.EXTRA AS id_extra,
			COUNT(s.COLUMN_NAME) AS primary_column_count,
			MAX(s.COLUMN_NAME) AS primary_column
		 FROM information_schema.tables t
		 LEFT JOIN information_schema.columns c
			ON c.TABLE_SCHEMA = t.TABLE_SCHEMA
			AND c.TABLE_NAME = t.TABLE_NAME
			AND c.COLUMN_NAME = 'id'
		 LEFT JOIN information_schema.statistics s
			ON s.TABLE_SCHEMA = t.TABLE_SCHEMA
			AND s.TABLE_NAME = t.TABLE_NAME
			AND s.INDEX_NAME = 'PRIMARY'
		 WHERE t.TABLE_SCHEMA = DATABASE()
			AND t.TABLE_NAME = 'drawer_balances'
		 GROUP BY t.TABLE_NAME, c.EXTRA"
	);
	if($db->has_error){
		throw new \RuntimeException('Unable to verify the drawer_balances schema.');
	}
	if(empty($schemaStatus)){
		return;
	}
	$schemaStatus = $schemaStatus[0];
	if(stripos((string)($schemaStatus->id_extra ?? ''), 'auto_increment') !== false
		&& (int)($schemaStatus->primary_column_count ?? 0) === 1
		&& strtolower((string)($schemaStatus->primary_column ?? '')) === 'id'){
		return;
	}

	$lockRows = $db->query("SELECT GET_LOCK('drawer_balances_id_migration', 15) AS acquired");
	if($db->has_error || (int)($lockRows[0]->acquired ?? 0) !== 1){
		throw new \RuntimeException('Unable to acquire the drawer balances schema migration lock.');
	}

	try{
		$tableRows = $db->query(
			"SELECT COUNT(*) AS table_count
			 FROM information_schema.tables
			 WHERE table_schema = DATABASE() AND table_name = 'drawer_balances'"
		);
		if($db->has_error){
			throw new \RuntimeException('Unable to inspect the drawer_balances table.');
		}
		if((int)($tableRows[0]->table_count ?? 0) === 0){
			return;
		}

		$columns = $db->query("SHOW COLUMNS FROM drawer_balances");
		if($db->has_error || !is_array($columns)){
			throw new \RuntimeException('Unable to inspect drawer_balances columns.');
		}

		$columnByName = [];
		foreach($columns as $column){
			$columnByName[strtolower((string)($column->Field ?? ''))] = $column;
		}
		if(!isset($columnByName['id'])){
			throw new \RuntimeException('The drawer_balances table has no id column.');
		}

		$indexes = $db->query("SHOW INDEX FROM drawer_balances");
		if($db->has_error){
			throw new \RuntimeException('Unable to inspect drawer_balances indexes.');
		}
		$indexes = is_array($indexes) ? $indexes : [];

		$primaryColumns = [];
		foreach($indexes as $index){
			if(strtoupper((string)($index->Key_name ?? '')) === 'PRIMARY'){
				$primaryColumns[(int)($index->Seq_in_index ?? 0)] = strtolower((string)($index->Column_name ?? ''));
			}
		}
		ksort($primaryColumns);
		$hasIdPrimaryKey = array_values($primaryColumns) === ['id'];
		$idIsAutoIncrement = stripos((string)($columnByName['id']->Extra ?? ''), 'auto_increment') !== false;
		$migrationColumn = '__drawer_balance_migration_row';
		$hasMigrationColumn = isset($columnByName[$migrationColumn]);

		if($hasIdPrimaryKey && $idIsAutoIncrement){
			if($hasMigrationColumn){
				$db->query("ALTER TABLE drawer_balances DROP COLUMN `{$migrationColumn}`");
				if($db->has_error){
					throw new \RuntimeException('Unable to remove the completed drawer_balances migration column.');
				}
			}
			return;
		}

		if(count($primaryColumns) > 0 && !$hasIdPrimaryKey){
			throw new \RuntimeException('drawer_balances already has a primary key other than id.');
		}

		$zeroRows = $db->query("SELECT COUNT(*) AS row_count FROM drawer_balances WHERE id = 0");
		if($db->has_error){
			throw new \RuntimeException('Unable to inspect zero-ID drawer balance rows.');
		}

		if((int)($zeroRows[0]->row_count ?? 0) > 0){
			if($idIsAutoIncrement){
				throw new \RuntimeException('drawer_balances contains zero IDs while id is already auto-incrementing.');
			}
			if(!$hasMigrationColumn){
				$db->query(
					"ALTER TABLE drawer_balances
					 ADD COLUMN `{$migrationColumn}` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE"
				);
				if($db->has_error){
					throw new \RuntimeException('Unable to create a temporary row key for drawer_balances migration.');
				}
				$hasMigrationColumn = true;
			}

			$maxRows = $db->query("SELECT COALESCE(MAX(id), 0) AS max_id FROM drawer_balances");
			if($db->has_error){
				throw new \RuntimeException('Unable to determine the drawer_balances ID range.');
			}
			$maxId = (int)($maxRows[0]->max_id ?? 0);
			$db->query(
				"UPDATE drawer_balances
				 SET id = :offset + `{$migrationColumn}`
				 WHERE id = 0",
				['offset' => $maxId]
			);
			if($db->has_error){
				throw new \RuntimeException('Unable to assign unique IDs to legacy drawer balance rows.');
			}
		}

		$duplicateRows = $db->query(
			"SELECT COUNT(*) AS duplicate_count
			 FROM (
				SELECT id
				FROM drawer_balances
				GROUP BY id
				HAVING COUNT(*) > 1
			 ) AS duplicate_ids"
		);
		if($db->has_error || (int)($duplicateRows[0]->duplicate_count ?? 0) > 0){
			throw new \RuntimeException('drawer_balances contains duplicate nonzero IDs; refusing to apply a potentially destructive migration.');
		}

		$currentIndexes = $db->query("SHOW INDEX FROM drawer_balances");
		if($db->has_error){
			throw new \RuntimeException('Unable to recheck drawer_balances indexes before migration.');
		}
		$currentPrimary = [];
		foreach(is_array($currentIndexes) ? $currentIndexes : [] as $index){
			if(strtoupper((string)($index->Key_name ?? '')) === 'PRIMARY'){
				$currentPrimary[(int)($index->Seq_in_index ?? 0)] = strtolower((string)($index->Column_name ?? ''));
			}
		}
		ksort($currentPrimary);
		$currentPrimaryColumns = array_values($currentPrimary);
		if(count($currentPrimaryColumns) > 0
			&& $currentPrimaryColumns !== ['id']
			&& $currentPrimaryColumns !== [$migrationColumn]){
			throw new \RuntimeException('drawer_balances has an unexpected primary key; refusing to alter it.');
		}

		$alterations = [];
		if($currentPrimaryColumns === [$migrationColumn]){
			$alterations[] = 'DROP PRIMARY KEY';
		}
		if(!$idIsAutoIncrement){
			$alterations[] = 'MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT';
		}
		if($currentPrimaryColumns !== ['id']){
			$alterations[] = 'ADD PRIMARY KEY (id)';
		}
		if($hasMigrationColumn){
			$alterations[] = "DROP COLUMN `{$migrationColumn}`";
		}
		if(!empty($alterations)){
			$db->query('ALTER TABLE drawer_balances ' . implode(', ', $alterations));
		}
		if($db->has_error){
			throw new \RuntimeException('Unable to make drawer_balances.id a unique auto-incrementing primary key.');
		}
	}catch(\Throwable $e){
		error_log('[drawer-balances-schema] ' . $e->getMessage());
		throw $e;
	}finally{
		$db->query("SELECT RELEASE_LOCK('drawer_balances_id_migration') AS released");
	}
}

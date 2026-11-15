<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

class Fix_cashdrawers_primary_key extends Migration
{
	public function up()
	{
		if (!$this->table_exists(['cashdrawers'])) {
			return;
		}

		$columns = $this->query('SHOW COLUMNS FROM cashdrawers') ?: [];
		$columnNames = array_map(
			static fn($column) => strtolower((string)($column->Field ?? '')),
			$columns
		);
		if (!in_array('id', $columnNames, true)) {
			throw new \RuntimeException('Cannot repair cashdrawers: the id column is missing.');
		}

		$duplicateIds = $this->query(
			'SELECT id, COUNT(*) AS copies FROM cashdrawers GROUP BY id HAVING COUNT(*) > 1'
		) ?: [];

		if (!empty($duplicateIds)) {
			if (!$this->beginTransaction()) {
				throw new \RuntimeException('Could not start the cashdrawers duplicate repair transaction.');
			}

			try {
				foreach ($duplicateIds as $duplicate) {
					$id = (int)($duplicate->id ?? 0);
					$copies = (int)($duplicate->copies ?? 0);
					$rows = $this->query(
						'SELECT * FROM cashdrawers WHERE id = :id',
						['id' => $id]
					) ?: [];

					if ($id <= 0 || $copies < 2 || count($rows) !== $copies) {
						throw new \RuntimeException('Cashdrawers changed during duplicate repair; no changes were committed.');
					}

					$reference = serialize((array)$rows[0]);
					foreach ($rows as $row) {
						if (serialize((array)$row) !== $reference) {
							throw new \RuntimeException(
								"Cashdrawer ID {$id} has conflicting duplicate records and requires manual review."
							);
						}
					}

					$deleteCount = $copies - 1;
					$this->query(
						"DELETE FROM cashdrawers WHERE id = :id LIMIT {$deleteCount}",
						['id' => $id]
					);
					if ($this->has_error || $this->affected_rows !== $deleteCount) {
						throw new \RuntimeException("Could not remove identical duplicate rows for cashdrawer ID {$id}.");
					}
				}

				if (!$this->commit()) {
					throw new \RuntimeException('Could not commit the cashdrawers duplicate repair.');
				}
			} catch (\Throwable $error) {
				$this->rollBack();
				throw $error;
			}
		}

		$indexes = $this->query("SHOW INDEX FROM cashdrawers WHERE Key_name = 'PRIMARY'") ?: [];
		$primaryColumn = (string)($indexes[0]->Column_name ?? '');
		$idColumn = null;
		foreach ($columns as $column) {
			if (strtolower((string)($column->Field ?? '')) === 'id') {
				$idColumn = $column;
				break;
			}
		}

		if ($primaryColumn !== '' && strtolower($primaryColumn) !== 'id') {
			throw new \RuntimeException('Cannot repair cashdrawers: a different column is already the primary key.');
		}

		$hasAutoIncrement = str_contains(strtolower((string)($idColumn->Extra ?? '')), 'auto_increment');
		$modifyId = !$hasAutoIncrement
			? 'MODIFY id int(11) NOT NULL AUTO_INCREMENT'
			: '';
		$addPrimaryKey = $primaryColumn === '' ? 'ADD PRIMARY KEY (id)' : '';
		$alterParts = array_filter([$modifyId, $addPrimaryKey]);

		if (!empty($alterParts)) {
			$this->query('ALTER TABLE cashdrawers ' . implode(', ', $alterParts));
			if ($this->has_error) {
				throw new \RuntimeException('Could not enable the cashdrawers primary key and auto-increment: ' . $this->error);
			}
		}
	}

	public function down()
	{
		throw new \LogicException('The cashdrawers primary-key repair cannot be safely reversed.');
	}
}

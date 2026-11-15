<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

class Widen_denos_trans_id extends Migration
{
	private function getColumnType(): ?string
	{
		if (!$this->table_exists(['denos'])) {
			return null;
		}

		$rows = $this->query("SHOW COLUMNS FROM denos LIKE 'trans_id'");
		if (!$rows || empty($rows[0]->Type)) {
			return null;
		}

		return strtolower((string)$rows[0]->Type);
	}

	public function up()
	{
		$type = $this->getColumnType();
		if ($type === null || strpos($type, 'bigint') !== false) {
			return;
		}

		$this->query("ALTER TABLE denos MODIFY COLUMN trans_id BIGINT(20) NULL");
	}

	public function down()
	{
		$type = $this->getColumnType();
		if ($type === null || strpos($type, 'bigint') === false) {
			return;
		}

		$this->query("ALTER TABLE denos MODIFY COLUMN trans_id INT(11) NULL");
	}
}

<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

class Widen_airtimebundles_id extends Migration
{
	private function getColumnType(): ?string
	{
		if (!$this->table_exists(['airtimebundles'])) {
			return null;
		}

		$rows = $this->query("SHOW COLUMNS FROM airtimebundles LIKE 'id'");
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

		$this->query("ALTER TABLE airtimebundles MODIFY COLUMN id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT");
	}

	public function down()
	{
		$type = $this->getColumnType();
		if ($type === null || strpos($type, 'bigint') === false) {
			return;
		}

		$this->query("ALTER TABLE airtimebundles MODIFY COLUMN id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");
	}
}

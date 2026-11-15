<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

/**
 * Create_cfa_rate_history class
 */
class Create_cfa_rate_history extends Migration
{
	public function up()
	{
		$this->query("
			CREATE TABLE IF NOT EXISTS cfa_rate_history (
				id INT(11) NOT NULL AUTO_INCREMENT,
				cfa_id INT(11) NULL DEFAULT NULL,
				settings_id INT(11) NULL DEFAULT NULL,
				buying_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
				selling_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
				changed_by INT(11) NULL DEFAULT NULL,
				note VARCHAR(120) NULL DEFAULT NULL,
				date_created DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY cfa_id (cfa_id),
				KEY settings_id (settings_id),
				KEY date_created (date_created),
				KEY buying_rate (buying_rate),
				KEY selling_rate (selling_rate)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
		");
	}

	public function down()
	{
		$this->query("DROP TABLE IF EXISTS cfa_rate_history");
	}
}

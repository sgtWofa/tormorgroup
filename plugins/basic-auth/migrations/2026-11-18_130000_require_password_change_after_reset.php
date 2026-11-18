<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

class Require_password_change_after_reset extends Migration
{
	public function up()
	{
		$columns = $this->query("SHOW COLUMNS FROM users LIKE 'force_password_change'");
		if (empty($columns)) {
			$this->query("ALTER TABLE users ADD COLUMN force_password_change TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER password");
		}
	}

	public function down()
	{
		$columns = $this->query("SHOW COLUMNS FROM users LIKE 'force_password_change'");
		if (!empty($columns)) {
			$this->query("ALTER TABLE users DROP COLUMN force_password_change");
		}
	}
}

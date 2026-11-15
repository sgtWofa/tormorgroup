<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

class Add_inactivity_limit_minutes extends Migration
{

	public function up()
	{
		$this->query("ALTER TABLE settings ADD COLUMN inactivity_limit_minutes INT(11) NULL DEFAULT 5");
	}

	public function down()
	{
		$this->query("ALTER TABLE settings DROP COLUMN inactivity_limit_minutes");
	}
}

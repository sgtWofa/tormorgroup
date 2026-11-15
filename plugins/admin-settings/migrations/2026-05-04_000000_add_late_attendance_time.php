<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

class Add_late_attendance_time extends Migration
{

	public function up()
	{
		$this->query("ALTER TABLE settings ADD COLUMN late_attendance_time TIME DEFAULT NULL AFTER work_close");
	}

	public function down()
	{
		$this->query("ALTER TABLE settings DROP COLUMN late_attendance_time");
	}
}

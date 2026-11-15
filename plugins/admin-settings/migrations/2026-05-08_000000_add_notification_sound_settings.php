<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

class Add_notification_sound_settings extends Migration
{
	private function column_exists(string $column): bool
	{
		$rows = $this->query("SHOW COLUMNS FROM settings LIKE '{$column}'");
		return !empty($rows);
	}

	public function up()
	{
		if(!$this->column_exists('notification_sound')){
			$this->query("ALTER TABLE settings ADD COLUMN notification_sound VARCHAR(255) NULL DEFAULT NULL AFTER inactivity_limit_minutes");
		}

		if(!$this->column_exists('notification_sound_volume')){
			$this->query("ALTER TABLE settings ADD COLUMN notification_sound_volume TINYINT UNSIGNED NULL DEFAULT 90 AFTER notification_sound");
		}

		if(!$this->column_exists('notification_sound_enabled')){
			$this->query("ALTER TABLE settings ADD COLUMN notification_sound_enabled TINYINT(1) NULL DEFAULT 1 AFTER notification_sound_volume");
		}
	}

	public function down()
	{
		if($this->column_exists('notification_sound_enabled')){
			$this->query("ALTER TABLE settings DROP COLUMN notification_sound_enabled");
		}

		if($this->column_exists('notification_sound_volume')){
			$this->query("ALTER TABLE settings DROP COLUMN notification_sound_volume");
		}

		if($this->column_exists('notification_sound')){
			$this->query("ALTER TABLE settings DROP COLUMN notification_sound");
		}
	}
}

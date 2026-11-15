<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

class Add_notification_pill_color_settings extends Migration
{
    private function column_exists(string $column): bool
    {
        $rows = $this->query("SHOW COLUMNS FROM settings LIKE '{$column}'");
        return !empty($rows);
    }

    public function up()
    {
        if(!$this->column_exists('notification_pill_color_serious')){
            $this->query("ALTER TABLE settings ADD COLUMN notification_pill_color_serious VARCHAR(255) NULL DEFAULT NULL AFTER notification_sound_enabled");
        }

        if(!$this->column_exists('notification_pill_color_mild')){
            $this->query("ALTER TABLE settings ADD COLUMN notification_pill_color_mild VARCHAR(255) NULL DEFAULT NULL AFTER notification_pill_color_serious");
        }

        if(!$this->column_exists('notification_pill_color_regular')){
            $this->query("ALTER TABLE settings ADD COLUMN notification_pill_color_regular VARCHAR(255) NULL DEFAULT NULL AFTER notification_pill_color_mild");
        }
    }

    public function down()
    {
        if($this->column_exists('notification_pill_color_regular')){
            $this->query("ALTER TABLE settings DROP COLUMN notification_pill_color_regular");
        }

        if($this->column_exists('notification_pill_color_mild')){
            $this->query("ALTER TABLE settings DROP COLUMN notification_pill_color_mild");
        }

        if($this->column_exists('notification_pill_color_serious')){
            $this->query("ALTER TABLE settings DROP COLUMN notification_pill_color_serious");
        }
    }
}

<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

class Add_notification_pill_plugin_map extends Migration
{
    private function column_exists(string $column): bool
    {
        $rows = $this->query("SHOW COLUMNS FROM settings LIKE '{$column}'");
        return !empty($rows);
    }

    public function up()
    {
        if(!$this->column_exists('notification_pill_plugin_map')){
            // Use TEXT to allow flexible JSON payloads
            $this->query("ALTER TABLE settings ADD COLUMN notification_pill_plugin_map TEXT NULL DEFAULT NULL AFTER notification_pill_color_regular");
        }
    }

    public function down()
    {
        if($this->column_exists('notification_pill_plugin_map')){
            $this->query("ALTER TABLE settings DROP COLUMN notification_pill_plugin_map");
        }
    }
}

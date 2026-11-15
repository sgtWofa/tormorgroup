<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

class Logs_device_ip extends Migration
{

	public function up()
	{
		// Add columns if they don't exist
		try{
			$cols = $this->query("SHOW COLUMNS FROM logs") ?: [];
			$existing = [];
			foreach($cols as $c){
				if(!is_object($c) || empty($c->Field)) continue;
				$existing[strtolower((string)$c->Field)] = true;
			}

			if(empty($existing['ip_address'])){
				$this->query("ALTER TABLE logs ADD COLUMN ip_address varchar(45) null");
				$this->query("ALTER TABLE logs ADD KEY (ip_address)");
			}

			if(empty($existing['user_agent'])){
				$this->query("ALTER TABLE logs ADD COLUMN user_agent text null");
			}

			if(empty($existing['device_details'])){
				$this->query("ALTER TABLE logs ADD COLUMN device_details text null");
			}
		}catch(\Throwable $e){
			// Keep migration resilient; do not hard-fail in production environments
		}
	}

	public function down()
	{
		// No-op: keep historical audit data intact.
	}
}

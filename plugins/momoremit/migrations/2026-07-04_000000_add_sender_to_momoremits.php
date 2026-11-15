<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

class Add_sender_to_momoremits extends Migration
{
	public function up()
	{
		if ($this->table_exists(['momoremits'])) {
			$this->query("ALTER TABLE momoremits ADD COLUMN IF NOT EXISTS sender varchar(50) null");
			$this->query("ALTER TABLE momoremits ADD KEY (sender)");
		}

		if ($this->table_exists(['sendings'])) {
			$this->query("ALTER TABLE sendings ADD COLUMN IF NOT EXISTS sender varchar(50) null");
			$this->query("ALTER TABLE sendings ADD KEY (sender)");
		}
	}

	public function down()
	{
		if ($this->table_exists(['momoremits'])) {
			$this->query("ALTER TABLE momoremits DROP COLUMN sender");
		}

		if ($this->table_exists(['sendings'])) {
			$this->query("ALTER TABLE sendings DROP COLUMN sender");
		}
	}
}

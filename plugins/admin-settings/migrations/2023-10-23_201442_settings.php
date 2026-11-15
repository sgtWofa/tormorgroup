<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

/**
 * Settings class
 */
class Settings extends Migration
{

	public function up()
	{

		$this->addColumn('id int unsigned auto_increment');
		$this->addColumn('user_id int unsigned null');
		$this->addColumn('company_name varchar(100) null');
		$this->addColumn('image varchar(1024) null');
		$this->addColumn('ceo_name varchar(255) null');
		$this->addColumn('ceo_contact varchar(25) null');
		$this->addColumn('admin_contact varchar(25) null');
		$this->addColumn('title varchar(255) null');
		$this->addColumn('date_started datetime default null');
		$this->addColumn('transaction_fee varchar(25) null');
		$this->addColumn('selling_rate FLOAT(10,2) DEFAULT 0.00');
		$this->addColumn('buying_rate FLOAT(10,2) DEFAULT 0.00');
		$this->addColumn('work_open time DEFAULT NULL');
		$this->addColumn('work_close time DEFAULT NULL');
		$this->addColumn('date_created datetime default null');
		$this->addColumn('date_updated datetime default null');

		$this->addPrimaryKey('id');
		$this->addKey('company_name');
		$this->addKey('user_id');
		$this->addKey('selling_rate');
		$this->addKey('buying_rate');
		$this->addKey('work_open');
		$this->addKey('work_close');
		$this->addKey('transaction_fee');
		$this->addKey('date_created');
 
		$this->createTable('settings');

	}

	public function down()
	{
		$this->dropTable('settings');
	}
}
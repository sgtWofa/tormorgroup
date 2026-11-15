<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

/**
 * Logs class
 */
class Logs extends Migration
{

	public function up()
	{

		$this->addColumn('id int unsigned auto_increment');
		$this->addColumn('table_name varchar(50) null');
		$this->addColumn('action varchar(50) null');
		$this->addColumn('user_id INT null');
		$this->addColumn('user_name varchar(100) null');
		$this->addColumn('description TEXT null');
		$this->addColumn('date_created datetime default null');

		$this->addPrimaryKey('id');
		$this->addKey('table_name');
		$this->addKey('action');
		$this->addKey('user_id');
 
		$this->createTable('logs');
 
	}

	public function down()
	{
		$this->dropTable('logs');
	}
}
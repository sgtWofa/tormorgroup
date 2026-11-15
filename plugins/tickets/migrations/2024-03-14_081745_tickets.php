<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

/**
 * Tickets class
 */
class Tickets extends Migration
{

	public function up()
	{
		$this->addColumn('id int unsigned auto_increment');
		$this->addColumn('user_id INT NOT NULL');
		$this->addColumn('subject varchar(255) null');
		$this->addColumn('description TEXT NOT NULL');
		$this->addColumn('status varchar(50) null');
		$this->addColumn('assigned varchar(100) null'); 
		$this->addColumn('created_by varchar(100) null');
		$this->addColumn('updated_by varchar(100) null');
		$this->addColumn('date_created datetime default null');
		$this->addColumn('date_updated datetime default null');

		$this->addPrimaryKey('id');
		$this->addKey('user_id');
		$this->addKey('subject');
		$this->addKey('status');
		$this->addKey('created_by');
		$this->addKey('updated_by');
 
		$this->createTable('tickets');
	}

	public function down()
	{
		$this->dropTable('tickets');
	}
}

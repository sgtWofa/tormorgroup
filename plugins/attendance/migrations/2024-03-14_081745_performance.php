<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

/**
 * Payments class
 */
class Performance extends Migration
{

	public function up()
	{
		$this->addColumn('id int unsigned auto_increment');
		$this->addColumn('user_id varchar(50) null');
		$this->addColumn('attendance varchar(50) null');
		$this->addColumn('date varchar(1024) null'); 
		$this->addColumn('cash_in varchar(50) null');
		$this->addColumn('cash_out varchar(50) null');
		$this->addColumn('commissions varchar(50) null');
		$this->addColumn('created_by varchar(100) null');
		$this->addColumn('updated_by varchar(100) null');
		$this->addColumn('deleted tinyint(1) unsigned default 0');
		$this->addColumn('date_created datetime default null');
		$this->addColumn('date_updated datetime default null');
		$this->addColumn('date_deleted datetime default null');

		$this->addPrimaryKey('id');
		$this->addKey('user_id');
		$this->addKey('date');
		$this->addKey('created_by');
		$this->addKey('updated_by');
 
		$this->createTable('performance');
	}

	public function down()
	{
		$this->dropTable('performance');
	}
}

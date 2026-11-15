<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

/**
 * Denos class
 */
class Denos extends Migration
{

	public function up()
	{

		$this->addColumn('id int unsigned auto_increment');
		$this->addColumn('trans_id int null');
		$this->addColumn('currency varchar(50) null');
		$this->addColumn('denomination varchar(50) null');
		$this->addColumn('count int null');
		$this->addColumn('volume decimal(10, 2) null');
		$this->addColumn('created_by varchar(100) null');
		$this->addColumn('date_created datetime default null');

		$this->addPrimaryKey('id');
		$this->addKey('trans_id');
		$this->addKey('currency');
		$this->addKey('created_by');
 
		$this->createTable('denos');
 
	}

	public function down()
	{
		$this->dropTable('denos');
	}
}
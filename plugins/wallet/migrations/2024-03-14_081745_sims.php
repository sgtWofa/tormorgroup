<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

/**
 * Sims class
 */
class Sims extends Migration
{

	public function up()
	{
		$this->addColumn('id int unsigned auto_increment');
		$this->addColumn('name varchar(50) null');
		$this->addColumn('network varchar(50) null');
		$this->addColumn('sim_type varchar(50) null');
		$this->addColumn('serial_number varchar(1024) null'); 
		$this->addColumn('puk varchar(15) null'); 
		$this->addColumn('sim_number varchar(50) null');
		$this->addColumn('assigned varchar(100) null'); 
		$this->addColumn('location varchar(100) null'); 
		$this->addColumn('cost varchar(100) null'); 
		$this->addColumn('price varchar(100) null'); 
		$this->addColumn('balance varchar(100) null'); 
		$this->addColumn('sim_status varchar(100) null'); 
		$this->addColumn('created_by varchar(100) null');
		$this->addColumn('updated_by varchar(100) null');
		$this->addColumn('disabled tinyint(1) unsigned default 0');
		$this->addColumn('deleted tinyint(1) unsigned default 0');
		$this->addColumn('date_created datetime default null');
		$this->addColumn('date_updated datetime default null');
		$this->addColumn('date_deleted datetime default null');

		$this->addPrimaryKey('id');
		$this->addKey('network');
		$this->addKey('sim_type');
		$this->addKey('sim_number');
		$this->addKey('location');
		$this->addKey('disabled');
		$this->addKey('created_by');
		$this->addKey('updated_by');
 
		$this->createTable('sims');
	}

	public function down()
	{
		$this->dropTable('sims');
	}
}

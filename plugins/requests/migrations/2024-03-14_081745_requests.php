<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

/**
 *Requests class
 */
class Requests extends Migration
{

	public function up()
	{
		$this->addColumn('id int unsigned auto_increment');
		$this->addColumn('network varchar(50) null');
		$this->addColumn('request_type varchar(50) null');
		$this->addColumn('till_number varchar(50) null');
		$this->addColumn('wallet_number varchar(50) null');
		$this->addColumn('description varchar(1024) null'); 
		$this->addColumn('amount FLOAT(10,2) DEFAULT 0.00');
		$this->addColumn('assigned varchar(100) null'); 
		$this->addColumn('transaction_status varchar(15) null'); 
		$this->addColumn('created_by varchar(100) null');
		$this->addColumn('updated_by varchar(100) null');
		$this->addColumn('disabled tinyint(1) unsigned default 0');
		$this->addColumn('deleted tinyint(1) unsigned default 0');
		$this->addColumn('date_created datetime default null');
		$this->addColumn('date_updated datetime default null');
		$this->addColumn('date_deleted datetime default null');

		$this->addPrimaryKey('id');
		$this->addKey('request_type');
		$this->addKey('network');
		$this->addKey('transaction_status');
		$this->addKey('deleted');
		$this->addKey('created_by');
		$this->addKey('updated_by');
 
		$this->createTable('requests');
	}

	public function down()
	{
		$this->dropTable('requests');
	}
}

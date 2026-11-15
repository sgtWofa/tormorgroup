<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

/**
 * Cits class
 */
class Cits extends Migration
{

	public function up()
	{

		$this->addColumn('id int unsigned auto_increment');
		$this->addColumn('network varchar(50) null');
		$this->addColumn('phone varchar(50) null');
		$this->addColumn('amount varchar(50) null');
		$this->addColumn('wallet_in_id varchar(50) null');
		$this->addColumn('wallet_out_id varchar(50) null');
		$this->addColumn('charge varchar(50) null');
		$this->addColumn('slug varchar(100) null');
		$this->addColumn('created_by varchar(100) null');
		$this->addColumn('updated_by varchar(100) null');
		$this->addColumn('disabled tinyint(1) unsigned default 0');

		$this->addColumn('deleted tinyint(1) unsigned default 0');
		$this->addColumn('date_created datetime default null');
		$this->addColumn('date_updated datetime default null');
		$this->addColumn('date_deleted datetime default null');

		$this->addPrimaryKey('id');
		$this->addKey('network');
		$this->addKey('phone');
		$this->addKey('wallet_in_id');
		$this->addKey('wallet_out_id');
		$this->addKey('slug');
		$this->addKey('disabled');
		$this->addKey('created_by');
		$this->addKey('updated_by');
 
		$this->createTable('cits');
 
	}

	public function down()
	{
		$this->dropTable('cits');
	}
}
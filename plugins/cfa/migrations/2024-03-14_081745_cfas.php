<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

/**
 * Cfas class
 */
class Cfas extends Migration
{

	public function up()
	{
		$this->addColumn('id int unsigned auto_increment');
		$this->addColumn('description varchar(1024) null'); 
		$this->addColumn('wallet_in_id varchar(50) null');
		$this->addColumn('wallet_out_id varchar(50) null');
		$this->addColumn('buying_rate FLOAT(10,2) DEFAULT 0.00');
		$this->addColumn('selling_rate FLOAT(10,2) DEFAULT 0.00');
		$this->addColumn('amount_cfa FLOAT(10,2) DEFAULT 0.00');
		$this->addColumn('amount_ghs FLOAT(10,2) DEFAULT 0.00');
		$this->addColumn('transaction_type varchar(50) null');
		$this->addColumn('transaction_status varchar(50) null');
		$this->addColumn('created_by varchar(100) null');
		$this->addColumn('updated_by varchar(100) null');
		$this->addColumn('disabled tinyint(1) unsigned default 0');
		$this->addColumn('deleted tinyint(1) unsigned default 0');
		$this->addColumn('date_created datetime default null');
		$this->addColumn('date_updated datetime default null');
		$this->addColumn('date_deleted datetime default null');

		$this->addPrimaryKey('id');
		$this->addKey('wallet_in_id');
		$this->addKey('wallet_out_id');
		$this->addKey('disabled');
		$this->addKey('created_by');
		$this->addKey('updated_by');
 
		$this->createTable('cfas');
	}

	public function down()
	{
		$this->dropTable('cfas');
	}
}

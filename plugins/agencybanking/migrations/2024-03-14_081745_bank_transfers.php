<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

/**
 * Sendings class
 */
class Bank_transfers extends Migration
{

	public function up()
	{

		$this->addColumn('id int unsigned auto_increment');
		$this->addColumn('network varchar(50) null');
		$this->addColumn('transaction_type varchar(50) null');
		$this->addColumn('category varchar(50) null');
		$this->addColumn('phone varchar(50) null');
		$this->addColumn('wallet_id varchar(50) null');
		$this->addColumn('wallet_in_id varchar(50) null');
		$this->addColumn('wallet_out_id varchar(50) null');
		$this->addColumn('amount FLOAT(10,2) DEFAULT 0.00');
		$this->addColumn('receiver varchar(50) null');
		$this->addColumn('charge FLOAT(10,2) DEFAULT 0.00');
		$this->addColumn('transaction_status varchar(50) null');
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
		$this->addKey('transaction_type');
		$this->addKey('category');
		$this->addKey('receiver');
		$this->addKey('transaction_status');
		$this->addKey('slug');
		$this->addKey('disabled');
		$this->addKey('created_by');
		$this->addKey('updated_by');
 
		$this->createTable('bank_transfers');
 
	}

	public function down()
	{
		$this->dropTable('bank_transfers');
	}
}
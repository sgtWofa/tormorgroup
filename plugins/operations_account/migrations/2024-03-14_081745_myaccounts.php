<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

/**
 *Myaccounts class
 */
class Myaccounts extends Migration
{

	public function up()
	{
		$this->addColumn('id int unsigned auto_increment');
		$this->addColumn('name varchar(50) null');
		$this->addColumn('network varchar(50) null');
		$this->addColumn('wallet_type varchar(50) null');
		$this->addColumn('category varchar(50) null');
		$this->addColumn('parent_account varchar(50) null');
		$this->addColumn('description varchar(1024) null'); 
		$this->addColumn('transaction_status varchar(15) null'); 
		$this->addColumn('wallet_number varchar(50) null');
		$this->addColumn('amount FLOAT(10,2) DEFAULT 0.00');
		$this->addColumn('debit FLOAT(10,2) DEFAULT 0.00');  // Set as FLOAT with default 0.00
		$this->addColumn('credit FLOAT(10,2) DEFAULT 0.00');
		$this->addColumn('balance FLOAT(10,2) DEFAULT 0.00');
		$this->addColumn('assigned varchar(100) null'); 
		$this->addColumn('created_by varchar(100) null');
		$this->addColumn('updated_by varchar(100) null');
		$this->addColumn('disabled tinyint(1) unsigned default 0');
		$this->addColumn('deleted tinyint(1) unsigned default 0');
		$this->addColumn('date_created datetime default null');
		$this->addColumn('date_updated datetime default null');
		$this->addColumn('date_deleted datetime default null');

		$this->addPrimaryKey('id');
		$this->addKey('network');
		$this->addKey('category');
		$this->addKey('parent_account');
		$this->addKey('wallet_type');
		$this->addKey('wallet_number');
		$this->addKey('transaction_status');
		$this->addKey('disabled');
		$this->addKey('created_by');
		$this->addKey('updated_by');
 
		$this->createTable('myaccounts');
	}

	public function down()
	{
		$this->dropTable('myaccounts');
	}
}

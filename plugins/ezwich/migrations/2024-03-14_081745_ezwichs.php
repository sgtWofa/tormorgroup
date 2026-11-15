<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

/**
 * Ezwichs class
 */
class Ezwichs extends Migration
{

	public function up()
	{
		$this->addColumn('id int unsigned auto_increment');
		$this->addColumn('usn varchar(50) null');
		$this->addColumn('network varchar(50) null');
		$this->addColumn('transaction_type varchar(50) null');
		$this->addColumn('phone varchar(50) null');
		$this->addColumn('wallet_in_id varchar(50) null');
		$this->addColumn('wallet_out_id varchar(50) null');
		$this->addColumn('amount FLOAT(10,2) DEFAULT 0.00');  // Set as FLOAT with default 0.00
		$this->addColumn('destination_bank varchar(50) null');
		$this->addColumn('transaction_status varchar(50) null');
		$this->addColumn('charge FLOAT(10,2) DEFAULT 0.00'); 
		$this->addColumn('receiving varchar(100) null'); 
		$this->addColumn('receiving_sim varchar(100) null'); 
		$this->addColumn('assigned int unsigned');
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
		$this->addKey('usn');
		$this->addKey('transaction_status');
		$this->addKey('wallet_in_id');
		$this->addKey('wallet_out_id');
		$this->addKey('disabled');
		$this->addKey('created_by');
		$this->addKey('updated_by');
 
		$this->createTable('ezwichs');
	}

	public function down()
	{
		$this->dropTable('ezwichs');
	}
}

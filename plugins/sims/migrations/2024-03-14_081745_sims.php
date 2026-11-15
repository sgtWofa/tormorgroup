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
		$this->addColumn('network varchar(50) null');
		$this->addColumn('puk varchar(50) null');
		$this->addColumn('number_assigned varchar(50) null');
		$this->addColumn('serial_number varchar(50) null');
		$this->addColumn('assigned varchar(100) null'); 
		$this->addColumn('buyer_phone varchar(100) null'); 
		$this->addColumn('image varchar(1024) null');
		$this->addColumn('wallet_in_id varchar(50) null');
		$this->addColumn('wallet_out_id varchar(50) null');
		$this->addColumn('cost FLOAT(10,2) DEFAULT 0.00');  
		$this->addColumn('wholesale_price FLOAT(10,2) DEFAULT 0.00');  
		$this->addColumn('retail_price FLOAT(10,2) DEFAULT 0.00');  
		$this->addColumn('amount FLOAT(10,2) DEFAULT 0.00');  
		$this->addColumn('commission FLOAT(10,2) DEFAULT 0.00');  
		$this->addColumn('qty varchar(50) null');
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
		$this->addKey('network');
		$this->addKey('assigned');
		$this->addKey('puk');
		$this->addKey('number_assigned');
		$this->addKey('transaction_type');
		$this->addKey('transaction_status');
		$this->addKey('wallet_in_id');
		$this->addKey('wallet_out_id');
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

<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

/**
 * Paymentservices class
 */
class Payment_services extends Migration
{

	public function up()
	{
		$this->addColumn('id int unsigned auto_increment');
		$this->addColumn('category varchar(100) null');
		$this->addColumn('institution varchar(100) null');
		$this->addColumn('payment_medium varchar(50) null');
		$this->addColumn('bank varchar(100) null');
		$this->addColumn('id_type varchar(50) null');
		$this->addColumn('customer_id varchar(50) null');
		$this->addColumn('customer_phone varchar(50) null');
		$this->addColumn('wallet_in_id varchar(50) null');
		$this->addColumn('wallet_out_id varchar(50) null');
		$this->addColumn('assigned_to varchar(100) null');
		$this->addColumn('amount FLOAT(10,2) DEFAULT 0.00');  // Set as FLOAT with default 0.00
		$this->addColumn('transaction_status varchar(50) null');
		$this->addColumn('charge FLOAT(10,2) DEFAULT 0.00'); 
		$this->addColumn('description varchar(300) null');
		$this->addColumn('created_by varchar(100) null');
		$this->addColumn('updated_by varchar(100) null');
		$this->addColumn('disabled tinyint(1) unsigned default 0');
		$this->addColumn('deleted tinyint(1) unsigned default 0');
		$this->addColumn('date_created datetime default null');
		$this->addColumn('date_updated datetime default null');
		$this->addColumn('date_deleted datetime default null');

		$this->addPrimaryKey('id');
		$this->addKey('category');
		$this->addKey('institution');
		$this->addKey('payment_medium');
		$this->addKey('transaction_status');
		$this->addKey('wallet_in_id');
		$this->addKey('wallet_out_id');
		$this->addKey('assigned_to');
		$this->addKey('disabled');
		$this->addKey('created_by');
		$this->addKey('updated_by');
 
		$this->createTable('payment_services');
	}

	public function down()
	{
		$this->dropTable('payment_services');
	}
}

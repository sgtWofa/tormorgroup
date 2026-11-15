<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

/**
 * Agencybankings class
 */
class Agencybankings extends Migration
{

	public function up()
	{
		$this->addColumn('id int unsigned auto_increment');
		$this->addColumn('network varchar(50) null');
		$this->addColumn('transaction_type varchar(50) null');
		$this->addColumn('category varchar(50) null');
		$this->addColumn('phone varchar(50) null');
		$this->addColumn('amount FLOAT(10,2) DEFAULT 0.00');  // Set as FLOAT with default 0.00
		$this->addColumn('ussd varchar(50) null');
		$this->addColumn('account_number varchar(50) null');
		$this->addColumn('destination_bank varchar(50) null');
		$this->addColumn('balance FLOAT(10,2) DEFAULT 0.00'); // Set as FLOAT with default 0.00
		$this->addColumn('balance_before FLOAT(10,2) DEFAULT 0.00'); // Set as FLOAT with default 0.00
		$this->addColumn('cash_source varchar(50) null');
		$this->addColumn('wallet_id varchar(50) null');
		$this->addColumn('wallet_in_id varchar(50) null');
		$this->addColumn('wallet_out_id varchar(50) null');
		$this->addColumn('receiver varchar(50) null');
		$this->addColumn('sender_phone varchar(50) null');
		$this->addColumn('full_name varchar(50) null');
		$this->addColumn('dob datetime default null');
		$this->addColumn('house_number varchar(50) null');
		$this->addColumn('id_type varchar(50) null');
		$this->addColumn('id_number varchar(50) null');
		$this->addColumn('next_of_king varchar(50) null');
		$this->addColumn('nok_phone varchar(15) null');
		$this->addColumn('nok_relation varchar(100) null');
		$this->addColumn('transaction_status varchar(50) null');
		$this->addColumn('charge FLOAT(10,2) DEFAULT 0.00'); // Set as FLOAT with default 0.00
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
		$this->addKey('ussd');
		$this->addKey('wallet_id');
		$this->addKey('wallet_in_id');
		$this->addKey('wallet_out_id');
		$this->addKey('account_number');
		$this->addKey('next_of_king');
		$this->addKey('nok_phone');
		$this->addKey('transaction_status');
		$this->addKey('slug');
		$this->addKey('disabled');
		$this->addKey('created_by');
		$this->addKey('updated_by');
 
		$this->createTable('agencybankings');
	}

	public function down()
	{
		$this->dropTable('agencybankings');
	}
}

<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

/**
 * Reversals class
 */
class Reversals extends Migration
{

	public function up()
	{
		$this->addColumn('id int unsigned auto_increment');
		$this->addColumn('network varchar(50) null');
		$this->addColumn('assigned varchar(50) null');
		$this->addColumn('service_type varchar(50) null');
		$this->addColumn('sender_details varchar(1024) null'); 
		$this->addColumn('reversal_medium varchar(1024) null'); 
		$this->addColumn('receiver_details varchar(1024) null'); 
		$this->addColumn('reason varchar(1024) null'); 
		$this->addColumn('response varchar(1024) null'); 
		$this->addColumn('transaction_date datetime default null');
		$this->addColumn('agent_involved varchar(50) null');
		$this->addColumn('waiting_period varchar(50) null');
		$this->addColumn('amount FLOAT(10,2) DEFAULT 0.00');  
		$this->addColumn('transaction_status varchar(50) null');
		$this->addColumn('created_by varchar(100) null');
		$this->addColumn('updated_by varchar(100) null');
		$this->addColumn('reversal_received varchar(10) null');
		$this->addColumn('disabled tinyint(1) unsigned default 0');
		$this->addColumn('deleted tinyint(1) unsigned default 0');
		$this->addColumn('reversal_date datetime default null');
		$this->addColumn('date_created datetime default null');
		$this->addColumn('date_updated datetime default null');
		$this->addColumn('date_deleted datetime default null');

		$this->addPrimaryKey('id');
		$this->addKey('network');
		$this->addKey('assigned');
		$this->addKey('service_type');
		$this->addKey('transaction_status');
		$this->addKey('agent_involved');
		$this->addKey('transaction_date');
		$this->addKey('reversal_received');
		$this->addKey('deleted');
		$this->addKey('created_by');
		$this->addKey('updated_by');
 
		$this->createTable('reversals');
	}

	public function down()
	{
		$this->dropTable('reversals');
	}
}

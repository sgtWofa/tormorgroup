<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

/**
 * Agent_services class
 */
class Agent_services extends Migration
{

	public function up()
	{
		$this->addColumn('id int unsigned auto_increment');
		$this->addColumn('customer_type varchar(50) null');
		$this->addColumn('service_type varchar(50) null');
		$this->addColumn('creator_id int unsigned null');
		$this->addColumn('assigned_to int unsigned null');
		$this->addColumn('processor_id int unsigned null');
		$this->addColumn('approver_id int unsigned null');
		$this->addColumn('status varchar(50) default \'Pending\'');
		$this->addColumn('stage int unsigned default 1');
		$this->addColumn('amount FLOAT(10,2) DEFAULT 0.00');
		$this->addColumn('cashdrawer_id int unsigned null');
		$this->addColumn('drawer_user_id int unsigned null');
		$this->addColumn('cash_received FLOAT(10,2) DEFAULT 0.00');
		$this->addColumn('change_amount FLOAT(10,2) DEFAULT 0.00');
		$this->addColumn('change_denominations_json JSON null');
		$this->addColumn('commission_amount FLOAT(10,2) DEFAULT 0.00');
		$this->addColumn('commission_type varchar(20) null'); // +fee or -fee
		$this->addColumn('provider varchar(100) null');
		$this->addColumn('paying_network varchar(100) null');
		$this->addColumn('receiving_network varchar(100) null');
		$this->addColumn('agent_name varchar(100) null');
		$this->addColumn('agent_contact varchar(100) null');
		$this->addColumn('business_name varchar(100) null');
		$this->addColumn('agent_number varchar(100) null');
		$this->addColumn('proof_image varchar(255) null');
		$this->addColumn('metadata LONGTEXT null');
		$this->addColumn('denominations_json JSON null');
		$this->addColumn('remarks TEXT null');
		$this->addColumn('reject_reason TEXT null');
		$this->addColumn('date_completed datetime default null');
		$this->addColumn('created_by int unsigned null');
		$this->addColumn('updated_by int unsigned null');
		$this->addColumn('deleted tinyint(1) unsigned default 0');
		$this->addColumn('date_created datetime default null');
		$this->addColumn('date_updated datetime default null');
		$this->addColumn('date_deleted datetime default null');

		$this->addPrimaryKey('id');
		$this->addKey('customer_type');
		$this->addKey('service_type');
		$this->addKey('creator_id');
		$this->addKey('assigned_to');
		$this->addKey('status');
		$this->addKey('deleted');
 
		$this->createTable('agent_services');
	}

	public function down()
	{
		$this->dropTable('agent_services');
	}
}

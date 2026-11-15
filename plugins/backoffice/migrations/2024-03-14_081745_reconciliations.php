<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

/**
 *Reconciliations class
 */
class Reconciliations extends Migration
{

	public function up()
	{
		$this->addColumn('id int unsigned auto_increment');
		$this->addColumn('wallet_id varchar(50) null');
		$this->addColumn('session_id varchar(50) null');
		$this->addColumn('balance FLOAT(10,2) DEFAULT 0.00');
		$this->addColumn('expected_balance FLOAT(10,2) DEFAULT 0.00');
		$this->addColumn('variance FLOAT(10,2) DEFAULT 0.00');
		$this->addColumn('remarks varchar(50) null');
		$this->addColumn('proof_image varchar(255) null');
		$this->addColumn('denos_json LONGTEXT null');
		$this->addColumn('system_denos_json LONGTEXT null');
		$this->addColumn('created_by varchar(100) null');
		$this->addColumn('updated_by varchar(100) null');
		$this->addColumn('disabled tinyint(1) unsigned default 0');
		$this->addColumn('deleted tinyint(1) unsigned default 0');
		$this->addColumn('date_created datetime default null');
		$this->addColumn('date_updated datetime default null');
		$this->addColumn('date_deleted datetime default null');

		$this->addPrimaryKey('id');
		$this->addKey('wallet_id');
		$this->addKey('session_id');
		$this->addKey('date_created');
		$this->addKey('deleted');
		$this->addKey('created_by');
		$this->addKey('updated_by');
 
		$this->createTable('reconciliations');
	}

	public function down()
	{
		$this->dropTable('reconciliations');
	}
}

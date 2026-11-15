<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

class Error_reports extends Migration
{
	public function up()
	{
		$this->addColumn('id int unsigned auto_increment');
		$this->addColumn('title varchar(190) null');
		$this->addColumn('message LONGTEXT null');
		$this->addColumn('source varchar(100) null');
		$this->addColumn('url varchar(255) null');
		$this->addColumn('severity varchar(20) null');
		$this->addColumn('stack LONGTEXT null');
		$this->addColumn('context_json LONGTEXT null');
		$this->addColumn('status varchar(20) null');
		$this->addColumn('user_id INT null');
		$this->addColumn('user_name varchar(100) null');
		$this->addColumn('resolved_by INT null');
		$this->addColumn('resolved_at datetime default null');
		$this->addColumn('deleted tinyint(1) unsigned default 0');
		$this->addColumn('date_created datetime default null');
		$this->addColumn('date_updated datetime default null');
		$this->addColumn('date_deleted datetime default null');

		$this->addPrimaryKey('id');
		$this->addKey('severity');
		$this->addKey('status');
		$this->addKey('user_id');
		$this->addKey('date_created');
		$this->createTable('error_reports');
	}

	public function down()
	{
		$this->dropTable('error_reports');
	}
}

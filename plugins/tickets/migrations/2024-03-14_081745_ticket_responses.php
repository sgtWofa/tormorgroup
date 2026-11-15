<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

/**
 * Ticket_Responses class
 */
class Ticket_Responses extends Migration
{

	public function up()
	{
		$this->addColumn('id int unsigned auto_increment');
		$this->addColumn('ticket_id INT NOT NULL');
		$this->addColumn('sender_id varchar(255) null');
		$this->addColumn('response TEXT NOT NULL');
		$this->addColumn('image varchar(1024) null');
		$this->addColumn('seen INT NOT NULL');
		$this->addColumn('created_by varchar(100) null');
		$this->addColumn('date_created datetime default null');

		$this->addPrimaryKey('id');
		$this->addKey('ticket_id');
		$this->addKey('sender_id');
		$this->addKey('seen');
		$this->addKey('created_by');
 
		$this->createTable('ticket_responses');
	}

	public function down()
	{
		$this->dropTable('ticket_responses');
	}
}

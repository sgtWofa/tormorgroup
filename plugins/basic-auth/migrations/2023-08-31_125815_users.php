<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

/**
 * Users class
 */
class Users extends Migration
{

	public function up()
	{

		$this->addColumn('id int unsigned auto_increment');
		
		$this->addColumn('first_name varchar(30) null');
		$this->addColumn('last_name varchar(30) null');
		$this->addColumn('image varchar(1024) null');
		$this->addColumn('signature varchar(1025) null');
		$this->addColumn('gender varchar(15) null');
		$this->addColumn('phone varchar(15) null');
		$this->addColumn('email varchar(100) null');
		$this->addColumn('overview mediumtext null');
		$this->addColumn('password varchar(255) null');
		$this->addColumn('ghcard varchar(20) null');
		$this->addColumn('house_number varchar(20) null');
		$this->addColumn('gps_address varchar(20) null');
		$this->addColumn('nearest_landmark varchar(255) null');
		$this->addColumn('languages_spoken varchar(100) null');
		$this->addColumn('education_level varchar(100) null');
		$this->addColumn('past_reference varchar(100) null');
		$this->addColumn('guarantor_name varchar(100) null');
		$this->addColumn('guarantor_phone varchar(15) null');
		$this->addColumn('date_joined datetime default null');

		$this->addColumn('deleted tinyint(1) unsigned default 0');
		$this->addColumn('disabled tinyint(1) unsigned default 0');
		$this->addColumn('date_created datetime default null');
		$this->addColumn('created_by varchar(100) null');
		$this->addColumn('updated_by varchar(100) null');
		$this->addColumn('date_updated datetime default null');
		$this->addColumn('date_deleted datetime default null');

		$this->addPrimaryKey('id');
		$this->addKey('first_name');
		$this->addKey('last_name');
		$this->addKey('phone');
		$this->addKey('email');
		$this->addKey('guarantor_phone');
		$this->addKey('created_by');
		$this->addKey('updated_by');
		$this->addKey('deleted');
		$this->addKey('disabled');
		$this->addKey('date_created');
		$this->addKey('date_deleted');

		/** more keys:
		 * $this->addFullTextKey('column2');
		 * $this->addUniqueKey('deleted');
		 */

		$this->createTable('users');

		 //to seed data:
		 $this->addData([
		 	'first_name'=>'Admin',
		 	'last_name'=>'Admin',
		 	'email'=>'email@email.com',
		 	'phone'=>'0548214842',
		 	'password'=>password_hash('password', PASSWORD_DEFAULT),
		 	'gender'=>'male',
		 	'date_created'=>date("Y-m-d H:i:s"),
		 ]);

		 $this->insert('users');

	}

	public function down()
	{
		$this->dropTable('users');
	}
}

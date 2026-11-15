<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

/**
 * Categories class
 */
class Mycategories extends Migration
{

	public function up()
	{

		$this->addColumn('id int unsigned auto_increment');
		$this->addColumn('category varchar(255) null');
		$this->addColumn('slug varchar(100) null');
		$this->addColumn('balance FLOAT(10,2) DEFAULT 0.00');
		$this->addColumn('disabled tinyint(1) unsigned default 0');

		$this->addPrimaryKey('id');
		$this->addKey('category');
		$this->addKey('slug');
		$this->addKey('disabled');
 
		$this->createTable('mycategories');
 
	}

	public function down()
	{
		$this->dropTable('mycategories');
	}
}
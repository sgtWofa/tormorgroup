<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

class Petty_cash_transactions extends Migration
{
	public function up()
	{
		$this->addColumn('id INT UNSIGNED');
		$this->addColumn('reference varchar(80) not null');
		$this->addColumn('title varchar(150) null');
		$this->addColumn('description text null');
		$this->addColumn("currency varchar(10) not null default 'GHS'");
		$this->addColumn('budget_amount DECIMAL(12,2) DEFAULT 0.00');
		$this->addColumn('assigned_user_id INT UNSIGNED null');
		$this->addColumn("status varchar(40) not null default 'pending_ack'");
		$this->addColumn('paid_denos_json LONGTEXT null');
		$this->addColumn('spent_amount DECIMAL(12,2) null');
		$this->addColumn('change_amount DECIMAL(12,2) null');
		$this->addColumn('change_denos_json LONGTEXT null');
		$this->addColumn('voucher_photo varchar(255) null');
		$this->addColumn('completion_note text null');
		$this->addColumn('cashdrawer_id INT UNSIGNED null');
		$this->addColumn('drawer_user_id INT UNSIGNED null');
		$this->addColumn('change_cashdrawer_id INT UNSIGNED null');
		$this->addColumn('change_drawer_user_id INT UNSIGNED null');
		$this->addColumn('acknowledged_by INT UNSIGNED null');
		$this->addColumn('acknowledged_at datetime null');
		$this->addColumn('completed_by INT UNSIGNED null');
		$this->addColumn('completed_at datetime null');
		$this->addColumn('created_by INT UNSIGNED null');
		$this->addColumn('updated_by INT UNSIGNED null');
		$this->addColumn('deleted tinyint(1) unsigned default 0');
		$this->addColumn('date_created datetime null');
		$this->addColumn('date_updated datetime null');
		$this->addColumn('date_deleted datetime null');

		$this->addPrimaryKey('id');
		$this->addKey('reference');
		$this->addKey('status');
		$this->addKey('assigned_user_id');
		$this->addKey('created_by');
		$this->addKey('date_created');

		$this->createTable('petty_cash_transactions');
	}

	public function down()
	{
		$this->dropTable('petty_cash_transactions');
	}
}

<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

/**
 * Cashpickups class
 */
class Cashpickups extends Migration
{

	public function up()
	{
		$this->addColumn('id BIGINT unsigned');
		$this->addColumn('transaction_side varchar(20) null');
		$this->addColumn('date varchar(50) null');
		$this->addColumn('source varchar(100) null');
		$this->addColumn('account_paid_to varchar(100) null');
		$this->addColumn('sender_details varchar(150) null');
		$this->addColumn('phone_number varchar(50) null');
		$this->addColumn('tracking_id varchar(120) null');
		$this->addColumn('idcard_photo varchar(255) null');
		$this->addColumn('customer_photo varchar(255) null');
		$this->addColumn('pickup_person_idcard varchar(120) null');
		$this->addColumn('pickup_person_signature varchar(120) null');
		$this->addColumn('pickup_person_phone varchar(50) null');
		$this->addColumn('amount_involved FLOAT(10,2) DEFAULT 0.00');
		$this->addColumn('payment_method varchar(50) null');
		$this->addColumn('pickup_charge FLOAT(10,2) DEFAULT 0.00');
		$this->addColumn('amount_paid_out FLOAT(10,2) DEFAULT 0.00');
		$this->addColumn('amount_transferred FLOAT(10,2) DEFAULT 0.00');
		$this->addColumn('denos_json JSON null');
		$this->addColumn('assigned_user_id INT unsigned null');
		$this->addColumn('approved_by INT unsigned null');
		$this->addColumn('approved_at datetime default null');
		$this->addColumn('confirmed_by varchar(150) null');
		$this->addColumn('notes text null');
		$this->addColumn('transaction_status varchar(50) null');
		$this->addColumn('created_by varchar(100) null');
		$this->addColumn('updated_by varchar(100) null');
		$this->addColumn('disabled tinyint(1) unsigned default 0');
		$this->addColumn('deleted tinyint(1) unsigned default 0');
		$this->addColumn('date_created datetime default null');
		$this->addColumn('date_updated datetime default null');
		$this->addColumn('date_deleted datetime default null');

		$this->addPrimaryKey('id');
		$this->addKey('transaction_side');
		$this->addKey('tracking_id');
		$this->addKey('phone_number');
		$this->addKey('transaction_status');
		$this->addKey('created_by');
		$this->addKey('date_created');

		$this->createTable('cashpickups');
	}

	public function down()
	{
		$this->dropTable('cashpickups');
	}
}

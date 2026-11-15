<?php

namespace BackOffices;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * Payment class
 */
class Payment extends Model
{

	protected $table = 'payments_backoffice';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'id',
		'description',
		'amount',
		'network',
		'payment_type',
		'wallet_out_id',
		'pay_to',
		'receiving_wallet',
		'cashdrawer_id',
		'drawer_user_id',
		'denos_json',
		'verified_denos_json',
		'payment_breakdown_json',
		'approval_receipt_photo',
		'approval_receipt_uploaded_at',
		'transaction_status',
		'reject_reason',
		'disabled',
		'deleted',
		'created_by',
		'updated_by',
		'approved_by',
		'approved_at',
		'date_created',
		'date_updated',
		'date_deleted',
	];
	
	protected $allowedUpdateColumns = [
		'description',
		'amount',
		'network',
		'payment_type',
		'wallet_out_id',
		'pay_to',
		'receiving_wallet',
		'cashdrawer_id',
		'drawer_user_id',
		'denos_json',
		'verified_denos_json',
		'payment_breakdown_json',
		'approval_receipt_photo',
		'approval_receipt_uploaded_at',
		'transaction_status',
		'reject_reason',
		'disabled',
		'deleted',
		'updated_by',
		'approved_by',
		'approved_at',
		'date_updated',
		'date_deleted',
	];


	public function validate_insert(array $data):bool
	{
		$this->errors = [];

 		if(empty($data['transaction_status']))
 		{
 			$this->errors['transaction_status'] = 'Activity status is required';
 		}

 		if(empty($data['amount']) || !is_numeric($data['amount']) || $data['amount'] <= 0)
		{
			$this->errors['amount'] = 'Amount must be greater than zero';
		}

		return empty($this->errors);
	}

	public function validate_update(array $data):bool
	{
		$this->errors = [];

 		if(empty($data['transaction_status']))
		{
			$this->errors['transaction_status'] = 'Activity status is required';
		}

		if(empty($data['amount']) || !is_numeric($data['amount']) || $data['amount'] <= 0)
		{
			$this->errors['amount'] = 'Amount must be greater than zero';
		}

		return empty($this->errors);
	}
}

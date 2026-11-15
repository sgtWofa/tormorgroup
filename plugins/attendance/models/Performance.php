<?php

namespace Performances;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * Performance class
 */
class Performance extends Model
{

	protected $table = 'performances';
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
		'description',
		'transaction_status',
		'disabled',
		'deleted',
		'created_by',
		'date_created',
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
		'description',
		'transaction_status',
		'disabled',
		'deleted',
		'updated_by',
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

 		return empty($this->errors);
	}

	public function validate_update(array $data):bool
	{
		$this->errors = [];

 		if(empty($data['transaction_status']))
 		{
 			$this->errors['transaction_status'] = 'Activity status is required';
 		}

		return empty($this->errors);
	}
}
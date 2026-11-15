<?php

namespace Cfas;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * Cfa class
 */
class Cfa extends Model
{

	protected $table = 'cfas';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'id',
		'description',
		'amount_cfa',
		'amount_ghs',
		'buying_rate',
		'selling_rate',
		'transaction_type',
		'transaction_status',
		'rejection_reason',
        'customer_phone',
		'disabled',
		'deleted',
		'created_by',
		'date_created',
		'date_deleted',
	];
	
	protected $allowedUpdateColumns = [
		'description',
		'amount_cfa',
		'amount_ghs',
		'buying_rate',
		'selling_rate',
		'transaction_type',
		'transaction_status',
		'rejection_reason',
        'customer_phone',
		'disabled',
		'deleted',
		'updated_by',
		'date_updated',
		'date_deleted',
	];


	public function validate_insert(array $data):bool
	{
		$this->errors = [];


 		if(empty($data['amount_cfa']))
 		{
 			$this->errors['amount_cfa'] = 'Amount in cfa is required';
 		}else
 		if($data['amount_cfa'] <0.01)
 		{
 			$this->errors['amount_cfa'] = 'Minimum amount is 0.01';
 		}

 		if(empty($data['amount_ghs']))
 		{
 			$this->errors['amount_ghs'] = 'Amount in GHS is required';
 		}else
 		if($data['amount_ghs'] <0.01)
 		{
 			$this->errors['amount_ghs'] = 'Minimum amount is 0.01';
 		}

 		if(empty($data['description']))
 		{
 			$this->errors['description'] = 'Description is required';
 		}

 		if(empty($data['transaction_status']))
 		{
 			$this->errors['transaction_status'] = 'Transaction status is required';
 		}

 		if(empty($data['transaction_type']))
 		{
 			$this->errors['transaction_type'] = 'Transaction type is required';
 		}

 		if(empty($data['customer_phone']))
 		{
 			$this->errors['customer_phone'] = 'Customer\'s phone number is required';
 		}

 		return empty($this->errors);
	}

	public function validate_update(array $data):bool
	{
		$this->errors = [];


 		if(empty($data['description']))
 		{
 			$this->errors['description'] = 'Description is required';
 		}

 		if(empty($data['transaction_status']))
 		{
 			$this->errors['transaction_status'] = 'Activity status is required';
 		}

		return empty($this->errors);
	}
}

<?php

namespace Airtimebundles;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * Momoremit class
 */
class Momoremit extends Model
{

	protected $table = 'momoremits';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'id',
		'network',
		'transaction_type',
		'category',
		'sending_category',
		'phone',
		'amount',
		'balance_after',
		'receiver',
		'token',
		'id_type',
		'id_number',
		'secret_code',
		'charge',
		'wallet_in_id',
		'wallet_out_id',
		'slug',
		'disabled',
		'deleted',
		'created_by',
		'updated_by',
		'date_created',
		'date_updated',
		'date_deleted',
	];
	
	protected $allowedUpdateColumns = [
		'network',
		'transaction_type',
		'category',
		'sending_category',
		'phone',
		'amount',
		'balance_after',
		'receiver',
		'token',
		'id_type',
		'id_number',
		'secret_code',
		'charge',
		'wallet_in_id',
		'wallet_out_id',
		'slug',
		'disabled',
		'deleted',
		'updated_by',
		'date_updated',
		'date_deleted',
	];


	public function validate_insert(array $data):bool
	{
		$this->errors = [];

 		if(empty($data['network']))
 		{
 			$this->errors['network'] = 'Network type is required';
 		}

 		if(empty($data['transaction_type']))
 		{
 			$this->errors['transaction_type'] = 'Transaction type is required';
 		}

 		if(empty($data['amount']))
 		{
 			$this->errors['amount'] = 'Transaction amount is required';
 		}

 		if(empty($data['balance_after']))
 		{
 			$this->errors['balance_after'] = 'Transaction Balance after is required';
 		}

 		if(empty($data['wallet_in_id']))
 		{
 			$this->errors['wallet_in_id'] = 'Receiving wallet is required';
 		}

 		if(empty($data['wallet_out_id']))
 		{
 			$this->errors['wallet_out_id'] = 'Paying wallet is required';
 		}

 		if(empty($data['phone']))
 		{
 			$this->errors['phone'] = 'Phone number is required';
 		}else
 		if(!preg_match("/^(0\d{9}|\+233\d{9})$/", trim($data['phone'])))
		{
		    $this->errors['phone'] = 'Phone number not valid';
		}
 		
 		if(!empty($data['receiver']))
 		{
	 		if(!preg_match("/^(0\d{9}|\+233\d{9})$/", trim($data['receiver'])))
			{
			    $this->errors['receiver'] = "Receiver's phone number not valid";
			}
 		} 		

 		return empty($this->errors);
	}

	public function validate_update(array $data):bool
	{
		$this->errors = [];
		
		if(empty($data['network']))
 		{
 			$this->errors['network'] = 'Network type is required';
 		}

 		if(empty($data['transaction_type']))
 		{
 			$this->errors['transaction_type'] = 'Transaction type is required';
 		}

 		if(empty($data['phone']))
 		{
 			$this->errors['phone'] = 'Phone number is required';
 		}else
 		if(!preg_match("/^(0\d{9}|\+233\d{9})$/", trim($data['phone'])))
		{
		    $this->errors['phone'] = 'Phone number not valid';
		}
 		
 		if(!empty($data['receiver']))
 		{
	 		if(!preg_match("/^(0\d{9}|\+233\d{9})$/", trim($data['receiver'])))
			{
			    $this->errors['receiver'] = "Receiver's phone number not valid";
			}
 		}

		return empty($this->errors);
	}
}
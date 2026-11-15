<?php

namespace MyWallets;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * Myaccount class
 */
class Myaccount extends Model
{

	protected $table = 'myaccount';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'id',
		'name',
		'network',
		'category',
		'wallet_type',
		'parent_account',
		'wallet_number',
		'description',
		'debit',
		'amount',
		'credit',
		'balance',
		'transaction_status',
		'assigned',
		'disabled',
		'deleted',
		'created_by',
		'date_created',
		'date_deleted',
	];
	
	protected $allowedUpdateColumns = [
		'name',
		'description',
		'wallet_type',
		'category',
		'parent_account',
		'network',
		'amount',
		'wallet_number',
		'debit',
		'amount',
		'credit',
		'balance',
		'transaction_status',
		'assigned',
		'disabled',
		'deleted',
		'updated_by',
		'date_updated',
		'date_deleted',
	];


	public function validate_insert(array $data):bool
	{
		$this->errors = [];

 		if(empty($data['name']))
 		{
 			$this->errors['name'] = 'Account name is required';
 		}

 		if(empty($data['description']))
 		{
 			$this->errors['description'] = 'Description is required';
 		}

 		/*if(empty($data['category']))
 		{
 			$this->errors['category'] = 'Account category is required';
 		}*/

 		if(empty($data['transaction_status']))
 		{
 			$this->errors['transaction_status'] = 'Activity status is required';
 		}

 		if(empty($data['network']))
 		{
 			$this->errors['network'] = 'Account network is required';
 		}

 		if(empty($data['wallet_type']))
 		{
 			$this->errors['wallet_type'] = 'Account type is required';
 		}

 		if(empty($data['wallet_number']))
 		{
 			$this->errors['wallet_number'] = 'Account wallet number is required';
 		}

 		if(empty($data['assigned']))
 		{
 			$this->errors['assigned'] = 'Account bearer or branch is required';
 		}

 		if(empty($data['amount']))
 		{
 			$this->errors['amount'] = 'Transaction amount is required';
 		}

 		if(!empty($data['wallet_number']))
 		{
 			if($this->first(['wallet_number'=>$data['wallet_number']]))
	 		{
	 			$this->errors['wallet_number'] = 'That wallet number number is already in use';
	 		}
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

 		if(empty($data['network']))
 		{
 			$this->errors['network'] = 'Account network is required';
 		}

 		if(empty($data['wallet_type']))
 		{
 			$this->errors['wallet_type'] = 'Account wallet type is required';
 		}

 		if(empty($data['wallet_number']))
 		{
 			$this->errors['wallet_number'] = 'Account wallet number is required';
 		}

 		if(empty($data['assigned']))
 		{
 			$this->errors['assigned'] = 'Account bearer or branch is required';
 		}

 		if(empty($data['amount']))
 		{
 			$this->errors['amount'] = 'Transaction amount is required';
 		}

		$wallet_number_arr = [
			'wallet_number'=>$data['wallet_number']
		];
		$wallet_number_arr_not = [
			$this->primary_key => $data[$this->primary_key] ?? 0
		];

 		if(empty($data['wallet_number']))
 		{
 			$this->errors['wallet_number'] = 'Account number is required';
 		}else
 		if($this->first($wallet_number_arr,$wallet_number_arr_not))
 		{
 			$this->errors['wallet_number'] = 'That wallet number number is already in use';
 		}

		return empty($this->errors);
	}
}
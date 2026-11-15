<?php

namespace Momoremits;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * Withdrawal class
 */
class Withdrawal extends Model
{
	protected function validateTokenIdNumber(array $data): void
	{
		$category = strtolower(trim((string)($data['category'] ?? '')));
		$idNumber = trim((string)($data['id_number'] ?? ''));

		if ($category !== 'token') {
			return;
		}

		if ($idNumber === '') {
			$this->errors['id_number'] = 'ID Number is required for withdrawal token.';
			return;
		}

		if (!preg_match('/^GHA-\d{9}-\d$/', $idNumber)) {
			$this->errors['id_number'] = 'ID Number must be in the format GHA-123456789-1.';
		}
	}

	protected function validateDynamicFields(array $data): void
	{
		$category = strtolower(trim((string)($data['category'] ?? '')));

		if (in_array($category, ['3rd party', 'token'], true) && trim((string)($data['receiver'] ?? '')) === '') {
			$this->errors['receiver'] = "Receiver's phone number is required";
		}

		if ($category === 'token') {
			if (trim((string)($data['token'] ?? '')) === '') {
				$this->errors['token'] = 'Token number is required';
			}
			if (trim((string)($data['secret_code'] ?? '')) === '') {
				$this->errors['secret_code'] = 'Secret code is required';
			}
		}
	}

	protected $table = 'withdrawals';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'id',
		'network',
		'transaction_type',
		'phone',
		'amount',
		'receiver',
		'token',
		'id_type',
		'id_number',
		'secret_code',
		'charge',
		'wallet_in_id',
		'wallet_out_id',
		'category',
		'slug',
		'disabled',
		'deleted',
		'created_by',
		'date_created',
		'date_updated',
		'date_deleted',
	];
	
	protected $allowedUpdateColumns = [
		'network',
		'transaction_type',
		'phone',
		'amount',
		'receiver',
		'token',
		'id_type',
		'id_number',
		'secret_code',
		'charge',
		'wallet_in_id',
		'wallet_out_id',
		'category',
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

		if(empty($data['category']))
		{
			$this->errors['category'] = 'Category is required for withdrawal';
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

		$this->validateDynamicFields($data);
		$this->validateTokenIdNumber($data);
 		

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

		if(empty($data['category']))
		{
			$this->errors['category'] = 'Category is required for withdrawal';
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

		$this->validateDynamicFields($data);
		$this->validateTokenIdNumber($data);

		return empty($this->errors);
	}
}

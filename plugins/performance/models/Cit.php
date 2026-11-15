<?php

namespace Performances;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * Cit class
 */
class Cit extends Model
{

	protected $table = 'cits';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'id',
		'network',
		'phone',
		'amount',
		'amount_to_withdraw',
		'amount_to_receive',
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
		'phone',
		'amount',
		'amount_to_withdraw',
		'amount_to_receive',
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
 			$this->errors['network'] = 'Transaction source is required';
 		}

 		if(empty($data['phone']))
 		{
 			$this->errors['phone'] = 'Phone number is required';
 		}

 		if(empty($data['amount']))
 		{
 			$this->errors['amount'] = 'Amount is required';
 		}

 		if(empty($data['phone']))
 		{
 			$this->errors['phone'] = 'Phone number is required';
 		}else
 		if(!preg_match("/^(0\d{9}|\+233\d{9})$/", trim($data['phone'])))
		{
		    $this->errors['phone'] = 'Phone number not valid';
		}
 		

 		return empty($this->errors);
	}

	public function validate_update(array $data):bool
	{
		$this->errors = [];
		
		if(empty($data['network']))
 		{
 			$this->errors['network'] = 'Source type is required';
 		}

 		if(empty($data['phone']))
 		{
 			$this->errors['phone'] = 'Phone number is required';
 		}

 		if(empty($data['phone']))
 		{
 			$this->errors['phone'] = 'Phone number is required';
 		}else
 		if(!preg_match("/^(0\d{9}|\+233\d{9})$/", trim($data['phone'])))
		{
		    $this->errors['phone'] = 'Phone number not valid';
		}

		return empty($this->errors);
	}
}
<?php

namespace UsersManager;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * Sim class
 */
class Sim extends Model
{

	protected $table = 'sims';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'id',
		'name',
		'network',
		'sim_type',
		'serial_number',
		'puk',
		'sim_number',
		'assigned',
		'location',
		'cost',
		'price',
		'balance',
		'sim_status',
		'disabled',
		'deleted',
		'created_by',
		'date_created',
		'date_deleted',
	];
	
	protected $allowedUpdateColumns = [
		'name',
		'sim_type',
		'network',
		'amount',
		'serial_number',
		'puk',
		'sim_number',
		'assigned',
		'location',
		'cost',
		'price',
		'balance',
		'sim_status',
		'disabled',
		'deleted',
		'updated_by',
		'date_updated',
		'date_deleted',
	];


	public function validate_insert(array $data):bool
	{
		$this->errors = [];

 		if(empty($data['cost']))
 		{
 			$this->errors['cost'] = 'Sim cost is required';
 		}

 		if(empty($data['price']))
 		{
 			$this->errors['price'] = 'Sim sale price is required';
 		}

 		if(empty($data['assigned']))
 		{
 			$this->errors['assigned'] = 'Sim bearer assigned is required';
 		}

 		if(empty($data['sim_number']))
 		{
 			$this->errors['sim_number'] = "Sim number is required";
 		}else
 		if(!preg_match("/^(0\d{9}|\+233\d{9})$/", trim($data['sim_number'])))
		{
		    $this->errors['sim_number'] = 'Sim number not valid';
		}

 		return empty($this->errors);
	}

	public function validate_update(array $data):bool
	{
		$this->errors = [];
		
		if(empty($data['cost']))
 		{
 			$this->errors['cost'] = 'Sim cost is required';
 		}

 		if(empty($data['price']))
 		{
 			$this->errors['price'] = 'Sim sale price is required';
 		}

 		if(empty($data['assigned']))
 		{
 			$this->errors['assigned'] = 'Sim bearer assigned is required';
 		}

 		if (!empty($data['sim_number'])) {
 			if(!preg_match("/^(0\d{9}|\+233\d{9})$/", trim($data['sim_number'])))
			{
			    $this->errors['sim_number'] = 'Sim number not valid';
			}
 		}

		return empty($this->errors);
	}
}
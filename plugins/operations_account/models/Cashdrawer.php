<?php

namespace MyWallets;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * Cashdrawer class
 */
class Cashdrawer extends Model
{

	protected $table = 'cashdrawers';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'id',
		'user_id',
		'currency',
		'amount',
		'date_created',
		'created_by',
	];
	
	protected $allowedUpdateColumns = [
		'user_id',
		'currency',
		'amount',
		'date_updated',
		'updated_by',
	];


	public function validate_insert(array $data):bool
	{
		$this->errors = [];

 		if(empty($data['user_id']))
 		{
 			$this->errors['user_id'] = 'Cashdrawer user_id is required';
 		}

 		if(empty($data['currency']))
 		{
 			$this->errors['currency'] = 'Cashdrawer currency is required';
 		}	

 		return empty($this->errors);
	}

	public function validate_update(array $data):bool
	{
		$this->errors = [];

		return empty($this->errors);
	}
}

<?php

namespace MyWallets;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * DrawerDenos class
 */
class DrawerDenos extends Model
{

	protected $table = 'drawer_denos';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'drawer_id',
		'user_id',
		'denomination',
		'count',
		'volume',
		'trans_id',
		'trans_status',
		'date_created',
		'created_by',
	];
	
	protected $allowedUpdateColumns = [
		'drawer_id',
		'user_id',
		'denomination',
		'count',
		'volume',
		'trans_id',
		'trans_status',
		'date_updated',
		'updated_by',
	];


	public function validate_insert(array $data):bool
	{
		$this->errors = [];

 		if(empty($data['user_id']))
 		{
 			$this->errors['user_id'] = 'Wallet user_id is required';
 		}

 		return empty($this->errors);
	}

	public function validate_update(array $data):bool
	{
		$this->errors = [];

		return empty($this->errors);
	}
}
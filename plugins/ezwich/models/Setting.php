<?php

namespace Ezwichs;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * Setting class
 */
class Setting extends Model
{

	protected $table = 'settings';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'user_id',
		'company_name',
		'image',
		'ceo_name',
		'ceo_contact',
		'admin_contact',
		'title',
		'transaction_fee',
		'momo_charges',
		'ezwich_charges',
		'agencybanking_charges',
		'buying_rate',
		'selling_rate',
		'company_sim',
		'work_open',
		'work_close',
		'date_started',
		'date_created',
	];
	
	protected $allowedUpdateColumns = [
		'user_id',
		'company_name',
		'image',
		'ceo_name',
		'ceo_contact',
		'admin_contact',
		'title',
		'transaction_fee',
		'momo_charges',
		'ezwich_charges',
		'agencybanking_charges',
		'buying_rate',
		'selling_rate',
		'company_sim',
		'work_open',
		'work_close',
		'date_started',
		'date_updated',
	];


	public function validate_insert(array $data):bool
	{
		$this->errors = [];

 		if(empty($data['company_name']))
 		{
 			$this->errors['company_name'] = 'A company name is required';
 		}

 		if(empty($data['ceo_name']))
 		{
 			$this->errors['ceo_name'] = "CEO's name is required";
 		}

 		if(empty($data['date_started']))
 		{
 			$this->errors['date_started'] = "Date started is required";
 		}
 		

 		return empty($this->errors);
	}

	public function validate_update(array $data):bool
	{
		$this->errors = [];
		
		if(empty($data['company_name']))
 		{
 			$this->errors['company_name'] = 'A company name is required';
 		}

		return empty($this->errors);
	}
}
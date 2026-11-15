<?php

namespace Dashboard;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * User class
 */
class User extends Model
{

	protected $table = 'users';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'id',
		'first_name',
		'last_name',
		'image',
		'signature',
		'gender',
		'phone',
		'email',
		'overview',
		'password',
		'ghcard',
		'house_number',
		'gps_address',
		'nearest_landmark',
		'languages_spoken',
		'education_level',
		'past_reference',
		'guarantor_name',
		'guarantor_phone',
		'date_joined',
		'deleted',
		'date_created',
		'created_by',
		'date_deleted',
	];
	
	protected $allowedUpdateColumns = [
		'first_name',
		'last_name',
		'image',
		'signature',
		'gender',
		'phone',
		'email',
		'overview',
		'password',
		'ghcard',
		'house_number',
		'gps_address',
		'nearest_landmark',
		'languages_spoken',
		'education_level',
		'past_reference',
		'guarantor_name',
		'guarantor_phone',
		'date_joined',
		'date_updated',
		'updated_by',
		'date_deleted',
	];


	public function validate_insert(array $data):bool
	{

		if(empty($data['first_name']))
 		{
 			$this->errors['first_name'] = 'A first name is required';
 		}else
 		if(!preg_match("/^[a-zA-Z]+$/", trim($data['first_name'])))
 		{
 			$this->errors['first_name'] = 'Only letters with no spaces allowed in first name';
 		}

 		if(empty($data['last_name']))
 		{
 			$this->errors['last_name'] = 'A last name is required';
 		}else
 		if(!preg_match("/^[a-zA-Z ]+$/", trim($data['last_name'])))
 		{
 			$this->errors['last_name'] = 'Only letters allowed in last name';
 		}

		$phone_arr = [
			'phone'=>$data['phone']
		];
		$phone_arr_not = [
			$this->primary_key => $data[$this->primary_key] ?? 0
		];

 		if(empty($data['phone']))
 		{
 			$this->errors['phone'] = 'Phone number is required';
 		}else
 		if($this->first($phone_arr,$phone_arr_not))
 		{
 			$this->errors['phone'] = 'That phone number is already in use';
 		}else 		
 		if(!preg_match("/^(0\d{9}|\+233\d{9})$/", trim($data['phone'])))
		{
		    $this->errors['phone'] = 'Phone number not valid';
		}

 		if(!empty($data['guarantor_phone']))
 		{
 			if(!preg_match("/^(0\d{9}|\+233\d{9})$/", trim($data['guarantor_phone'])))
			{
			    $this->errors['guarantor_phone'] = "Guarantor's Phone number not valid";
			}
 		}		
 		
 		if(empty($data['gender']))
 		{
 			$this->errors['gender'] = 'Gender is required';
 		}

 		if(empty($data['password']))
 		{
 			$this->errors['password'] = 'A password is required';
 		}else
 		if($data['password'] != $data['retype_password'])
 		{
 			$this->errors['password'] = 'Passwords do not match';
 		}
 
 		return empty($this->errors);
	}

	public function validate_update(array $data):bool
	{
		
		if(empty($data['first_name']))
 		{
 			$this->errors['first_name'] = 'A first name is required';
 		}else
 		if(!preg_match("/^[a-zA-Z ]+$/", trim($data['first_name'])))
 		{
 			$this->errors['first_name'] = 'Only letters with allowed in first name';
 		}

 		if(empty($data['last_name']))
 		{
 			$this->errors['last_name'] = 'A last name is required';
 		}else
 		if(!preg_match("/^[a-zA-Z ]+$/", trim($data['last_name'])))
 		{
 			$this->errors['last_name'] = 'Only letters allowed in last name';
 		}

		$phone_arr = [
			'phone'=>$data['phone']
		];
		$phone_arr_not = [
			$this->primary_key => $data[$this->primary_key] ?? 0
		];
		
		if(empty($data['phone']))
 		{
 			$this->errors['phone'] = 'Phone number is required';
 		}else
 		if($this->first($phone_arr,$phone_arr_not))
 		{
 			$this->errors['phone'] = 'That phone number is already in use';
 		}else 		
 		if(!preg_match("/^(0\d{9}|\+233\d{9})$/", trim($data['phone'])))
		{
		    $this->errors['phone'] = 'Phone number not valid';
		}


 		if(empty($data['gender']))
 		{
 			$this->errors['gender'] = 'Gender is required';
 		}

 		if(!empty($data['password']))
 		{
	 		if($data['password'] != $data['retype_password'])
	 		{
	 			$this->errors['password'] = 'Passwords do not match';
	 		}
 		}

 		if(!empty($data['guarantor_phone']))
 		{
 			if(!preg_match("/^(0\d{9}|\+233\d{9})$/", trim($data['guarantor_phone'])))
			{
			    $this->errors['guarantor_phone'] = "Guarantor's Phone number not valid";
			}
 		}	

		return empty($this->errors);
	}
}
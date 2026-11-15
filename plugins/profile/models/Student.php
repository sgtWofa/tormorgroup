<?php

namespace UsersManager;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * Student class
 */
class Student extends Model
{

	protected $table = 'students';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'id',
		'first_name',
		'last_name',
		'gender',
		'dob',
		'age',
		'image',
		'residence',
		'index_number',
		'hometown',
		'region',
		'languages_spoken',
		'postal_address',
		'prev_school_name',
		'school_phone',
		'year_completed',
		'school_address',
		'boarding_status',
		'programme',
		'electives',
		'class',
		'house',
		'health_concern',
		'health_specific',
		'caregiver_first_name',
		'caregiver_last_name',
		'caregiver_phone',
		'address',
		'relation',
		'password',
		'date_created',
	];
	
	protected $allowedUpdateColumns = [
		'first_name',
		'last_name',
		'gender',
		'dob',
		'age',
		'image',
		'residence',
		'index_number',
		'hometown',
		'region',
		'languages_spoken',
		'postal_address',
		'prev_school_name',
		'school_phone',
		'year_completed',
		'school_address',
		'boarding_status',
		'programme',
		'electives',
		'house',
		'class',
		'health_concern',
		'health_specific',
		'caregiver_first_name',
		'caregiver_last_name',
		'caregiver_phone',
		'address',
		'relation',
		'password',
		'date_created',
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

 		if(empty($data['index_number']))
 		{
 			$this->errors['index_number'] = 'Index number is required';
 		}else
 		if($this->first(['index_number'=>$data['index_number']]))
 		{
 			$this->errors['index_number'] = 'That index number is already in use';
 		}else
 		if(!preg_match("/^[0-9]{12}$/", trim($data['index_number'])))
		{
			$this->errors['index_number'] = "Index number must be equall to 12";
		}
 		
 		if(empty($data['gender']))
 		{
 			$this->errors['gender'] = 'Gender is required';
 		}
 		
 		if(empty($data['dob']))
 		{
 			$this->errors['dob'] = 'Date of birth is required';
 		}
 		
 		if(empty($data['boarding_status']))
 		{
 			$this->errors['boarding_status'] = 'Boarding status is required';
 		}
 		
 		if(empty($data['programme']))
 		{
 			$this->errors['programme'] = 'Programme of study is required';
 		}
 		
 		/*if(empty($data['electives']))
 		{
 			$this->errors['electives'] = 'Elective subjects are required';
 		}*/
 		
 		if(empty($data['health_concern']))
 		{
 			$this->errors['health_concern'] = 'Health status is required';
 		}

 		if(!empty($data['school_phone']))
		{
			if(!preg_match("/^(0|\+233)[0-9]{9}$/", trim($data['school_phone'])))
			{
				$this->errors['school_phone'] = "Phone number not valid";
			}
		}

 		if(!empty($data['caregiver_phone']))
		{
			if(!preg_match("/^(0|\+233)[0-9]{9}$/", trim($data['caregiver_phone'])))
			{
				$this->errors['caregiver_phone'] = "Phone number not valid";
			}
		}else
		if(empty($data['caregiver_phone']))
		{
			$this->errors['caregiver_phone'] = "Caregiver's phone number is required";
		}

		if(empty($data['relation']))
		{
			$this->errors['relation'] = "Caregiver's relation is required";
		}

		if(empty($data['caregiver_first_name']))
		{
			$this->errors['caregiver_first_name'] = "Caregiver's firstName is required";
		}

		if(empty($data['caregiver_last_name']))
		{
			$this->errors['caregiver_last_name'] = "Caregiver's lastName is required";
		}

		if(empty($data['prev_school_name']))
		{
			$this->errors['prev_school_name'] = "Previous school's name is required";
		}

		if(empty($data['school_address']))
		{
			$this->errors['school_address'] = "Previous school's address is required";
		}

		if(empty($data['year_completed']))
		{
			$this->errors['year_completed'] = "Year of completion is required";
		}

 
 		return empty($this->errors);
	}

	public function validate_update(array $data):bool
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

 		if(!empty($data['school_phone']))
		{
			if(!preg_match("/^(0|\+233)[0-9]{9}$/", trim($data['school_phone'])))
			{
				$this->errors['school_phone'] = "Phone number not valid";
			}
		}

 		if(!empty($data['caregiver_phone']))
		{
			if(!preg_match("/^(0|\+233)[0-9]{9}$/", trim($data['caregiver_phone'])))
			{
				$this->errors['caregiver_phone'] = "Phone number not valid";
			}
		}else
		if(empty($data['caregiver_phone']))
		{
			$this->errors['caregiver_phone'] = "Caregiver's phone number is required";
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

		return empty($this->errors);
	}
}
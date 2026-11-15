<?php

namespace Performances;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * Attendance class
 */
class Attendance extends Model
{

	protected $table = 'attendance';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'id',
		'user_id',
		'date',
		'time',
		'remarks',
		'deleted',
		'created_by',
		'date_created',
		'date_deleted',
	];
	
	protected $allowedUpdateColumns = [
		
		'user_id',
		'date',
		'time',
		'remarks',
		'deleted',
		'updated_by',
		'date_updated',
		'date_deleted',
	];


	public function validate_insert(array $data):bool
	{
		$this->errors = [];


 		if(empty($data['remarks']))
 		{
 			$this->errors['remarks'] = 'Remarks is required';
 		}

 		return empty($this->errors);
	}

	public function validate_update(array $data):bool
	{
		$this->errors = [];[];


 		if(empty($data['remarks']))
 		{
 			$this->errors['remarks'] = 'Remarks is required';
 		}
		return empty($this->errors);
	}
}
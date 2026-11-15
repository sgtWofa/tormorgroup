<?php

namespace UsersManager;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * Student_categories_map class
 */
class Student_categories_map extends Model
{

	protected $table = 'students_categories_map';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'category_id',
		'student_id',
		'disabled',
	];
	
	protected $allowedUpdateColumns = [
		'category_id',
		'student_id',
		'disabled',
	];


	public function validate_insert(array $data):bool
	{
 
 		return empty($this->errors);
	}

	public function validate_update(array $data):bool
	{
 
		return empty($this->errors);
	}
}
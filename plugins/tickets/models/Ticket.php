<?php

namespace Tickets;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * Ticket class
 */
class Ticket extends Model
{

	protected $table = 'tickets';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'id',
		'user_id',
		'subject',
		'description',
		'status',
		'assigned',
		'created_by',
		'date_created',
];
	
	protected $allowedUpdateColumns = [
		'user_id',
		'subject',
		'description',
		'status',
		'assigned',
		'updated_by',
		'date_updated',
	];


	public function validate_insert(array $data):bool
	{
		$this->errors = [];

 		if(empty($data['subject']))
 		{
 			$this->errors['subject'] = 'Ticket subject is required';
 		}

 		if(empty($data['description']))
 		{
 			$this->errors['description'] = 'Description is required';
 		}

 		return empty($this->errors);
	}

	public function validate_update(array $data):bool
	{
		$this->errors = [];

 		if(empty($data['subject']))
 		{
 			$this->errors['subject'] = 'Ticket subject is required';
 		}

 		if(empty($data['description']))
 		{
 			$this->errors['description'] = 'Description is required';
 		}

		return empty($this->errors);
	}
}
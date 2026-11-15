<?php

namespace Tickets;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * Ticket_response class
 */
class Ticket_response extends Model
{

	protected $table = 'ticket_responses';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'id',
		'ticket_id',
		'sender_id',
		'seen',
		'image',
		'response',
		'created_by',
		'date_created',
];
	
	protected $allowedUpdateColumns = [
		
		'ticket_id',
		'sender_id',
		'seen',
		'image',
		'response',
	];


	public function validate_insert(array $data):bool
	{
		$this->errors = [];

 		if(empty($data['response']) && empty($data['image']))
 		{
 			$this->errors['response'] = 'Response message or image is required';
 		}

 		if(empty($data['ticket_id']))
 		{
 			$this->errors['ticket_id'] = 'Ticket id is required';
 		}

 		return empty($this->errors);
	}


	public function validate_image(array $data):bool
	{
		$this->errors = [];

 		if(empty($data['image']))
 		{
 			$this->errors['image'] = 'Image is required';
 		}

 		if(empty($data['ticket_id']))
 		{
 			$this->errors['ticket_id'] = 'Ticket id is required';
 		}

 		return empty($this->errors);
	}

	public function validate_update(array $data):bool
	{
		$this->errors = [];

 		if(empty($data['response']))
 		{
 			$this->errors['response'] = 'Ticket_response response is required';
 		}

 		if(empty($data['ticket_id']))
 		{
 			$this->errors['ticket_id'] = 'Ticket id is required';
 		}

		return empty($this->errors);
	}
}
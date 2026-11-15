<?php

namespace PaymentService;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * Deno class
 */
class Deno extends Model
{

	protected $table = 'denos';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'id',
		'trans_id',
		'currency',
		'denomination',
		'count',
		'volume',
		'created_by',
		'date_created',
	];
	
	protected $allowedUpdateColumns = [
		'trans_id',
		'currency',
		'denomination',
		'count',
		'volume',
		'created_by',
		'date_created',
	];


	public function validate_insert(array $data):bool
	{
		$this->errors = [];

 		return empty($this->errors);
	}

	public function validate_update(array $data):bool
	{
		$this->errors = [];
		
		return empty($this->errors);
	}
}
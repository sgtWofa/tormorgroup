<?php

namespace BackOffices;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * Transaction class
 */
class Transaction extends Model
{

	protected $table = 'transactions';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'id',
		'wallet_id',
		'wallet_in_id',
		'wallet_out_id',
		'description',
		'transaction_type',
		'amount',
		'debit',
		'credit',
		'balance',
		'created_by',
		'updated_by',
		'disabled',
		'deleted',
		'date_created',
		'date_updated',
		'date_deleted',
	];
	
	protected $allowedUpdateColumns = [
		'wallet_id',
		'wallet_in_id',
		'wallet_out_id',
		'description',
		'transaction_type',
		'amount',
		'debit',
		'credit',
		'balance',
		'updated_by',
		'disabled',
		'deleted',
		'date_updated',
		'date_deleted',
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
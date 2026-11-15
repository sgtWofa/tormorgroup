<?php

namespace Logs;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * Category class
 */
class Log extends Model
{

	protected $table = 'logs';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'id',
		'table_name',
		'action',
		'user_id',
		'description',
		'date_created',
	];
	
	protected $allowedUpdateColumns = [
		'id',
		'table_name',
		'action',
		'user_id',
		'description',
		'date_created',
	];


}
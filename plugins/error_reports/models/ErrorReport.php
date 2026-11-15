<?php

namespace ErrorReports;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

class ErrorReport extends Model
{
	protected $table = 'error_reports';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'id',
		'title',
		'message',
		'source',
		'url',
		'severity',
		'stack',
		'context_json',
		'status',
		'user_id',
		'user_name',
		'resolved_by',
		'resolved_at',
		'deleted',
		'date_created',
		'date_updated',
		'date_deleted',
	];

	protected $allowedUpdateColumns = [
		'id',
		'title',
		'message',
		'source',
		'url',
		'severity',
		'stack',
		'context_json',
		'status',
		'user_id',
		'user_name',
		'resolved_by',
		'resolved_at',
		'deleted',
		'date_created',
		'date_updated',
		'date_deleted',
	];
}

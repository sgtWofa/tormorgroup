<?php

namespace BackOffices;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * Commission class
 */
class Commission extends Model
{

	protected $table = 'commissions';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'id',
		'bulk_parent_id',
		'date_received',
		'amount',
		'network',
		'commission_type',
		'colletion_period',
		'pay_to',
		'assigned',
		'sim_assignment_id',
		'proof_image',
		'reject_reason',
		'assignment_snapshot_json',
		'receiving_wallet',
		'cashdrawer_id',
		'drawer_user_id',
		'denos_json',
		'description',
		'transaction_status',
		'disabled',
		'deleted',
		'created_by',
		'approved_by',
		'approved_at',
		'date_created',
		'date_updated',
		'date_deleted',
	];
	
	protected $allowedUpdateColumns = [
		'date_received',
		'amount',
		'network',
		'commission_type',
		'colletion_period',
		'pay_to',
		'assigned',
		'sim_assignment_id',
		'proof_image',
		'reject_reason',
		'assignment_snapshot_json',
		'receiving_wallet',
		'bulk_parent_id',
		'cashdrawer_id',
		'drawer_user_id',
		'denos_json',
		'description',
		'transaction_status',
		'disabled',
		'deleted',
		'updated_by',
		'approved_by',
		'approved_at',
		'date_updated',
		'date_deleted',
	];


	public function validate_insert(array $data):bool
	{
		$this->errors = [];
		$statusValue = trim((string)($data['transaction_status'] ?? ''));
		$assignedValue = (int)($data['assigned'] ?? ($data['pay_to'] ?? 0));

 		if($statusValue === '')
 		{
 			$this->errors['transaction_status'] = 'Activity status is required';
 		}

 		if($assignedValue <= 0)
 		{
 			$this->errors['assigned'] = 'Assigned approver is required';
 		}

		return empty($this->errors);
	}

	public function validate_update(array $data):bool
	{
		$this->errors = [];

 		if(empty($data['transaction_status']))
 		{
 			$this->errors['transaction_status'] = 'Activity status is required';
 		}

		return empty($this->errors);
	}
}

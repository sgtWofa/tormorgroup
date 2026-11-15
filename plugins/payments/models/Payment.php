<?php

namespace Payments;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * Payment class
 */
class Payment extends Model
{

	protected $table = 'payments';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'id',
		'category',
		'description',
		'amount',
		'wallet_out_id',
		'transaction_status',
		'salary_month',
		'breakdown_total',
		'assigned_staff_user_id',
		'cashdrawer_id',
		'drawer_user_id',
		'payment_mode',
		'ecash_network',
		'denos_json',
		'disabled',
		'deleted',
		'created_by',
		'date_created',
		'date_deleted',
	];
	
	protected $allowedUpdateColumns = [
		'category',
		'description',
		'amount',
		'wallet_out_id',
		'transaction_status',
		'salary_month',
		'breakdown_total',
		'assigned_staff_user_id',
		'cashdrawer_id',
		'drawer_user_id',
		'payment_mode',
		'ecash_network',
		'denos_json',
		'disabled',
		'deleted',
		'updated_by',
		'date_updated',
		'date_deleted',
	];


	public function validate_insert(array $data):bool
	{
		$this->errors = [];

		$cat = strtoupper(trim((string)($data['category'] ?? '')));
		if($cat === '')
		{
			$this->errors['category'] = 'Category is required';
		}

		if(empty($data['description']))
		{
			$this->errors['description'] = 'Description is required';
		}

		if(empty($data['transaction_status']))
		{
			$this->errors['transaction_status'] = 'Activity status is required';
		}

		if(empty($data['amount']))
		{
			$this->errors['amount'] = 'Transaction amount is required';
		}

		$paymentMode = strtolower(trim((string)($data['payment_mode'] ?? '')));
		if($paymentMode === '')
		{
			$this->errors['payment_mode'] = 'Mode of payment is required';
		}
		elseif($paymentMode === 'ecash' || $paymentMode === 'e-cash')
		{
			if(trim((string)($data['ecash_network'] ?? '')) === '')
			{
				$this->errors['ecash_network'] = 'Select the e-cash network';
			}
		}

		if($cat === 'SALARY')
		{
			$sm = trim((string)($data['salary_month'] ?? ''));
			if($sm === '')
			{
				$this->errors['salary_month'] = 'Salary month is required';
			}
		}else{
			$assignedStaffUserId = trim((string)($data['assigned_staff_user_id'] ?? ''));
			if($assignedStaffUserId === '')
			{
				$this->errors['assigned_staff_user_id'] = 'Assign the staff cashdrawer that should fund this payment';
			}
		}

		// Wallet validation removed - cashdrawer is central accounting system

		return empty($this->errors);
	}

	public function validate_update(array $data):bool
	{
		$this->errors = [];

		$cat = strtoupper(trim((string)($data['category'] ?? '')));
		if($cat === '')
		{
			$this->errors['category'] = 'Category is required';
		}

		if(empty($data['description']))
		{
			$this->errors['description'] = 'Description is required';
		}

		if(empty($data['transaction_status']))
		{
			$this->errors['transaction_status'] = 'Activity status is required';
		}

		if(empty($data['amount']))
		{
			$this->errors['amount'] = 'Transaction amount is required';
		}

		$paymentMode = strtolower(trim((string)($data['payment_mode'] ?? '')));
		if($paymentMode === '')
		{
			$this->errors['payment_mode'] = 'Mode of payment is required';
		}
		elseif($paymentMode === 'ecash' || $paymentMode === 'e-cash')
		{
			if(trim((string)($data['ecash_network'] ?? '')) === '')
			{
				$this->errors['ecash_network'] = 'Select the e-cash network';
			}
		}

		if($cat === 'SALARY')
		{
			$sm = trim((string)($data['salary_month'] ?? ''));
			if($sm === '')
			{
				$this->errors['salary_month'] = 'Salary month is required';
			}
		}else{
			$assignedStaffUserId = trim((string)($data['assigned_staff_user_id'] ?? ''));
			if($assignedStaffUserId === '')
			{
				$this->errors['assigned_staff_user_id'] = 'Assign the staff cashdrawer that should fund this payment';
			}
		}

		// Wallet validation removed - cashdrawer is central accounting system

		return empty($this->errors);
	}
}

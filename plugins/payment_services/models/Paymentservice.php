<?php

namespace PaymentService;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * Ezwich class
 */
class Paymentservice extends Model
{

	protected $table = 'payment_services';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'id',
		'category',
		'bank',
		'institution',
		'payment_medium',
		'network',
		'id_type',
		'customer_id',
		'customer_phone',
		'assigned_to',
		'amount',
		'amount_paid',
		'cash_received_amount',
		'change_required',
		'change_denominations',
		'transaction_status',
		'rejection_reason',
		'charge',
		'description',
		'transaction_image',
		'created_by',
		'date_created',
	];
	
	protected $allowedUpdateColumns = [
		'category',
		'bank',
		'institution',
		'payment_medium',
		'network',
		'id_type',
		'customer_id',
		'customer_phone',
		'wallet_in_id',
		'wallet_out_id',
		'assigned_to',
		'amount',
		'amount_paid',
		'cash_received_amount',
		'change_required',
		'change_denominations',
		'transaction_status',
		'rejection_reason',
		'charge',
		'description',
		'transaction_image',
		'updated_by',
		'disabled',
		'deleted',
		'date_updated',
		'date_deleted',
	];


	public function validate_insert(array $data):bool
	{
		$this->errors = [];

 		if(empty($data['category']))
 		{
 			$this->errors['category'] = 'Payment category is required';
 		}

 		if(empty($data['institution']))
 		{
 			$this->errors['institution'] = 'Institution is required';
 		}

 		if(empty($data['customer_id']))
 		{
 			$this->errors['customer_id'] = 'Customer ID is required';
 		}

 		if(empty($data['payment_medium']))
		{
			$this->errors['payment_medium'] = 'Payment medium is required';
		}

		$medium = strtolower(trim((string)($data['payment_medium'] ?? '')));
		if($medium === 'bank')
		{
			if(empty($data['assigned_to']))
			{
				$this->errors['assigned_to'] = 'Assigned user is required for bank payments';
			}
		}

		if(empty($data['transaction_status']))
 		{
 			$this->errors['transaction_status'] = 'Transaction status is required';
 		}

 		if(empty($data['amount']))
		{
			$this->errors['amount'] = 'Transaction amount is required';
		}

		// If we have both the denominations total and an amount paid, ensure cash received covers payable
		$denoTotal   = isset($data['deno_total']) ? (float)$data['deno_total'] : 0.0;
		$amountPaid  = isset($data['amount_paid']) ? (float)$data['amount_paid'] : 0.0;
		if($denoTotal > 0 && $amountPaid > 0 && ($amountPaid - $denoTotal) > 0.005){
			$this->errors['amount_paid'] = 'Amount payable cannot exceed the received denominations total.';
		}

 		if(empty($data['customer_phone']))
 		{
 			$this->errors['customer_phone'] = "Client's Phone number is required";
 		}else
 		if(!preg_match("/^(0\d{9}|\+233\d{9})$/", trim($data['customer_phone'])))
		{
		    $this->errors['customer_phone'] = 'Phone number not valid';
		}	

 		return empty($this->errors);
	}

	public function validate_update(array $data):bool
	{
		$this->errors = [];

		if(empty($data['transaction_status']))
		{
			$this->errors['transaction_status'] = 'Transaction status is required';
		}

		$medium = strtolower(trim((string)($data['payment_medium'] ?? '')));
		if($medium === 'bank')
		{
			if(empty($data['assigned_to']))
			{
				$this->errors['assigned_to'] = 'Assigned user is required for bank payments';
			}
		}
		
		if(empty($data['amount']))
 		{
 			$this->errors['amount'] = 'Amount is required';
 		}

 		
 		if(empty($data['customer_phone']))
 		{
 			$this->errors['customer_phone'] = 'Customer Phone number is required';
 		}else
 		if(!preg_match("/^(0\d{9}|\+233\d{9})$/", trim($data['customer_phone'])))
		{
		    $this->errors['customer_phone'] = 'Phone number not valid';
		}

		if(strcasecmp(trim((string)($data['transaction_status'] ?? '')), 'Declined') === 0 && trim((string)($data['rejection_reason'] ?? '')) === '')
		{
			$this->errors['rejection_reason'] = 'Rejection reason is required when declining a transaction';
		}

		return empty($this->errors);
	}
}

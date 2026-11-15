<?php

namespace Ezwichs;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * Ezwich class
 */
class Ezwich extends Model
{

	protected $table = 'ezwichs';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'id',
		'usn',
		'transaction_id',
		'network',
		'transaction_type',
		'wallet_in_id',
		'wallet_out_id',
		'phone',
		'momo_image',
		'merchant_receipt',
		'customer_receipt',
		'amount_payable',
		'balance',
		'amount',
		'destination_bank',
		'transaction_status',
		'rejection_reason',
		'charge',
		'receiving',
		'assigned',
		'receiving_sim',
		'disabled',
		'deleted',
		'created_by',
		'date_created',
		'date_deleted',
	];
	
	protected $allowedUpdateColumns = [
		'usn',
		'network',
		'transaction_type',
		'phone',
		'wallet_in_id',
		'wallet_out_id',
		'momo_image',
		'merchant_receipt',
		'customer_receipt',
		'amount',
		'destination_bank',
		'transaction_status',
		'rejection_reason',
		'charge',
		'receiving',
		'receiving_sim',
		'assigned',
		'disabled',
		'deleted',
		'updated_by',
		'date_updated',
		'date_deleted',
	];


	public function validate_insert(array $data):bool
	{
		$this->errors = [];

 		/*if(empty($data['network']))
 		{
 			$this->errors['network'] = 'Network type is required';
 		}*/

 		if(empty($data['transaction_type']))
 		{
 			$this->errors['transaction_type'] = 'Transaction type is required';
 		}

 		if (empty($data['transaction_id'])) {
	        $this->errors['transaction_id'] = 'Transaction ID is required';
	    } else {
	        $transactionId = trim($data['transaction_id']);

	        // Ensure the transaction ID is exactly 11 digits and contains only numbers
	        if (!preg_match("/^\d{11}$/", $transactionId) || !ctype_digit($transactionId)) {
	            $this->errors['transaction_id'] = 'Transaction ID must be an 11-digit number';
	        } else {
	            // Check if the transaction_id already exists in the database
	            $existingTransaction = $this->where(['transaction_id' => $transactionId]);
	            if (!empty($existingTransaction)) {
	                $this->errors['transaction_id'] = 'Transaction ID already exists';
	            }
	        }
	    }


 		if(empty($data['transaction_status']))
 		{
 			$this->errors['transaction_status'] = 'Transaction status is required';
 		}

 		// Wallet validation removed - cashdrawer is central accounting system

 		if(empty($data['amount']))
 		{
 			$this->errors['amount'] = 'Transaction amount is required';
 		}

 		if(empty($data['phone']))
 		{
 			$this->errors['phone'] = "Client's Phone number is required";
 		}else
 		if(!preg_match("/^(0\d{9}|\+233\d{9})$/", trim($data['phone'])))
		{
		    $this->errors['phone'] = 'Phone number not valid';
		}	

 		return empty($this->errors);
	}

	public function validate_update(array $data):bool
	{
		$this->errors = [];
		
		if(empty($data['network']))
 		{
 			$this->errors['network'] = 'Network type is required';
 		}

 		if(empty($data['transaction_type']))
 		{
 			$this->errors['transaction_type'] = 'Transaction type is required';
 		}

 		if(empty($data['phone']))
 		{
 			$this->errors['phone'] = 'Phone number is required';
 		}else
 		if(!preg_match("/^(0\d{9}|\+233\d{9})$/", trim($data['phone'])))
		{
		    $this->errors['phone'] = 'Phone number not valid';
		}

		return empty($this->errors);
	}
}

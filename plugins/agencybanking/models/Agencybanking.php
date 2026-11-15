<?php

namespace Agencybankings;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * Agencybanking class
 */
class Agencybanking extends Model
{

	protected $table = 'agencybankings';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'id',
		'network',
		'transaction_type',
		'category',
		'phone',
		'transaction_image',
		'amount',
		'cash_received_amount',
		'change_required',
		'change_denominations',
		'ussd',
		'account_number',
		'destination_bank',
		'balance',
		'balance_before',
		'cash_source',
		'sof_option',
		'payment_medium',
		'wallet_id',
		'wallet_in_id',
		'wallet_out_id',
		'receiver',
		'sender_phone',
		'full_name',
		'dob',
		'house_number',
		'id_type',
		'id_number',
		'next_of_king',
		'nok_phone',
		'nok_relation',
		'transaction_status',
		'rejection_reason',
		'charge',
		'charging_type',
		'slug',
		'created_by',
		'date_created',
		'amount_withdrawn',
		'amount_payable',
		'other_phone',
		'user_first_name',
		'user_last_name',
		'user_image',
	];
	
	protected $allowedUpdateColumns = [
		'network',
		'transaction_type',
		'wallet_id',
		'wallet_in_id',
		'wallet_out_id',
		'category',
		'phone',
		'transaction_image',
		'amount',
		'cash_received_amount',
		'change_required',
		'change_denominations',
		'ussd',
		'account_number',
		'destination_bank',
		'balance',
		'balance_before',
		'cash_source',
		'receiver',
		'sender_phone',
		'full_name',
		'dob',
		'house_number',
		'id_type',
		'id_number',
		'next_of_king',
		'nok_phone',
		'nok_relation',
		'transaction_status',
		'rejection_reason',
		'charge',
		'charging_type',
		'slug',
		'disabled',
		'deleted',
		'updated_by',
		'date_updated',
		'date_deleted',
	];


	public function validate_insert(array $data):bool
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

		$requiresPhone = true;
		$isCitBankWithdrawal = strcasecmp(
			trim((string)($data['transaction_type'] ?? '')),
			'Withdrawal (CIT)'
		) === 0 && in_array(
			strtolower(trim((string)($data['network'] ?? ''))),
			['calbank', 'ecobank', 'fidelity bank'],
			true
		);
		if ($isCitBankWithdrawal) {
			$requiresPhone = false;
		}

		$phone = trim((string)($data['phone'] ?? ''));
		if ($phone === '' && $requiresPhone) {
			$this->errors['phone'] = 'Phone number is required';
		} elseif ($phone !== '' && !preg_match("/^(0\d{9}|\+233\d{9})$/", $phone)) {
			$this->errors['phone'] = 'Phone number not valid';
		}
 		
 		if(!empty($data['receiver']))
 		{
	 		if(!preg_match("/^(0\d{9}|\+233\d{9})$/", trim($data['receiver'])))
			{
			    $this->errors['receiver'] = "Receiver's phone number not valid";
			}
 		}
 		
 		if(!empty($data['sender_phone']))
 		{
	 		if(!preg_match("/^(0\d{9}|\+233\d{9})$/", trim($data['sender_phone'])))
			{
			    $this->errors['sender_phone'] = "Sender's phone number not valid";
			}
 		}
 		
 		if(!empty($data['nok_phone']))
 		{
	 		if(!preg_match("/^(0\d{9}|\+233\d{9})$/", trim($data['nok_phone'])))
			{
			    $this->errors['nok_phone'] = "Next of king's phone number not valid";
			}
 		} 		

 		if(empty($data['transaction_image']))
		{
			$this->errors['transaction_image'] = 'Transaction message picture is required';
		}

		$limitError = agencybanking_validate_transaction_limit($data);
		if ($limitError !== null) {
			$this->errors['amount'] = $limitError;
		}

		return empty($this->errors);
	}

	public function validate_update(array $data):bool
	{
		$this->errors = [];

		$status = strtolower(trim((string)($data['transaction_status'] ?? '')));
		if (in_array($status, ['declined', 'rejected', 'reject', 'rejection'], true) && trim((string)($data['rejection_reason'] ?? '')) === '') {
			$this->errors['rejection_reason'] = 'Rejection reason is required when declining or rejecting a transaction.';
		}
		
		return empty($this->errors);
	}
}

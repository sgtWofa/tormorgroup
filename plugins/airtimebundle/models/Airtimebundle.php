<?php

namespace Airtimebundles;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * Airtimebundle class
 */
class Airtimebundle extends Model
{

	protected $table = 'airtimebundles';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'id',
		'network',
		'transaction_type',
		'category',
		'sending_category',
		'phone',
		'receiver',
		'amount',
		'amount_paid',
		'cash_received_amount',
		'change_required',
		'change_denominations',
		'amount_to_send',
		'commission',
		'transaction_image',
		'reseller_number',
		'data_size',
		'balance_after',
		'wallet_in_id',
		'wallet_out_id',
		'assigned',
		'transaction_status',
		'rejection_reason',
		'disabled',
		'deleted',
		'created_by',
		'updated_by',
		'date_created',
		'date_updated',
		'date_deleted',
	];
	
	protected $allowedUpdateColumns = [
		'network',
		'transaction_type',
		'category',
		'sending_category',
		'phone',
		'receiver',
		'amount',
		'amount_paid',
		'cash_received_amount',
		'change_required',
		'change_denominations',
		'amount_to_send',
		'commission',
		'transaction_image',
		'reseller_number',
		'data_size',
		'balance_after',
		'wallet_in_id',
		'wallet_out_id',
		'assigned',
		'transaction_status',
		'rejection_reason',
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

 		if(empty($data['transaction_status']))
 		{
 			$this->errors['transaction_status'] = 'Transaction status is required';
 		}

 		if(empty($data['transaction_image']))
 		{
 			$this->errors['transaction_image'] = 'Transaction image is required';
 		}

 		if(empty($data['phone']))
 		{
 			$this->errors['phone'] = 'Phone number is required';
 		}else
 		if(!preg_match("/^(0\d{9}|\+233\d{9})$/", trim($data['phone'])))
		{
		    $this->errors['phone'] = 'Phone number not valid';
		}
 		
 		if(!empty($data['receiver']))
 		{
	 		if(!preg_match("/^(0\d{9}|\+233\d{9})$/", trim($data['receiver'])))
			{
			    $this->errors['receiver'] = "Receiver's phone number not valid";
			}
 		} 		

 		// Validate that amount is provided and greater than 0
		if(empty($data['amount']) || (float)$data['amount'] <= 0)
		{
			$this->errors['amount'] = 'Please use the calculator to enter amount';
		}

		$transactionType = strtoupper(trim($data['transaction_type'] ?? ''));

		$category = strtoupper(trim($data['category'] ?? ''));
		$changeRequired = (float)($data['change_required'] ?? 0);
		$cashReceivedAmount = (float)($data['cash_received_amount'] ?? 0);
		$resolveNetPaidAmount = function(float $paidAmount) use ($cashReceivedAmount, $changeRequired): float {
			$receivedAmount = $cashReceivedAmount > 0 ? $cashReceivedAmount : $paidAmount;
			if ($changeRequired > 0.01) {
				return $receivedAmount - $changeRequired;
			}
			return $paidAmount;
		};
		if($transactionType === 'DATA BUNDLE')
		{
			if(in_array($category, ['MASHUP', 'MTN-DATA', 'AT-DATA', 'TELECEL-DATA']))
			{
				$loadAmount = (float)($data['mashup_amount'] ?? 0);
				$paidAmount = (float)($data['mashup_paid'] ?? 0);
				$netPaidAmount = $resolveNetPaidAmount($paidAmount);
				if($loadAmount <= 0)
				{
					$this->errors['mashup_amount'] = 'Load/Amount is required';
				}
				if($paidAmount <= 0)
				{
					$this->errors['mashup_paid'] = 'Amount paid is required';
				}
				if($loadAmount > 0 && $paidAmount > 0 && abs($netPaidAmount - $loadAmount) > 0.01)
				{
					$this->errors['mashup_paid'] = 'Amount paid must match load/amount';
				}
			}
			else if(in_array($category, ['5959', 'MTN UP2U', 'ISHARE', 'JUST4U']))
			{
				$loadAmount = (float)($data['special_amount_load'] ?? 0);
				$paidAmount = (float)($data['special_amount_paid'] ?? 0);
				$netPaidAmount = $resolveNetPaidAmount($paidAmount);
				if($loadAmount <= 0)
				{
					$this->errors['special_amount_load'] = 'Amount/Load is required';
				}
				if($paidAmount <= 0)
				{
					$this->errors['special_amount_paid'] = 'Amount paid is required';
				}
				if($loadAmount > 0 && $paidAmount > 0 && abs($netPaidAmount - $loadAmount) > 0.01)
				{
					$this->errors['special_amount_paid'] = ($category === 'JUST4U')
						? 'Amount paid must match amount/load. GHS 0.50 is recorded as commission.'
						: 'Amount paid must match amount/load';
				}
			}
			else
			{
				$loadAmount = (float)($data['amount_to_send'] ?? ($data['amount'] ?? 0));
				$paidAmount = (float)($data['amount_paid'] ?? ($data['amount'] ?? 0));

				if($loadAmount <= 0)
				{
					$this->errors['amount'] = 'Load/Amount is required';
				}
				if($paidAmount <= 0)
				{
					$this->errors['amount'] = 'Amount paid is required';
				}
				if($loadAmount > 0 && $paidAmount > 0 && abs($paidAmount - $loadAmount) > 0.01)
				{
					$this->errors['amount'] = 'Amount paid must match load/amount';
				}
			}
		}

		return empty($this->errors);
	}

	public function validate_update(array $data):bool
	{
		$this->errors = [];
 		
 		if(!empty($data['receiver']))
 		{
	 		if(!preg_match("/^(0\d{9}|\+233\d{9})$/", trim($data['receiver'])))
			{
			    $this->errors['receiver'] = "Receiver's phone number not valid";
			}
 		}

 		if(empty($data['transaction_status']))
 		{
 			$this->errors['transaction_status'] = 'Transaction status is required';
 		}

 		if(empty($data['transaction_image']))
 		{
 			$this->errors['transaction_image'] = 'Transaction image is required';
 		}

		return empty($this->errors);
	}
}

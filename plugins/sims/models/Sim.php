<?php

namespace Sims;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * Sim class
 */
class Sim extends Model
{

	protected $table = 'sims';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'id',
		'network',
		'puk',
		'serial_number',
		'number_assigned',
		'assigned',
		'buyer_phone',
		'wallet_in_id',
		'wallet_out_id',
		'cost',
		'wholesale_price',
		'retail_price',
		'amount',
		'commission',
		'image',
		'transaction_typ',
		'transaction_status',
		'rejection_reason',
		'created_by',
		'updated_by',
		'disabled',
		'deleted',
		'date_created',
		'date_updated',
		'date_deleted',	
];
	
	protected $allowedUpdateColumns = [
		'network',
		'puk',
		'serial_number',
		'number_assigned',
		'assigned',
		'buyer_phone',
		'wallet_in_id',
		'wallet_out_id',
		'cost',
		'wholesale_price',
		'retail_price',
		'amount',
		'commission',
		'image',
		'denos',
		'qty',
		'transaction_type',
		'transaction_typ',
		'transaction_status',
		'rejection_reason',
		'updated_by',
		'disabled',
		'deleted',
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

		$puk_arr = [
			'puk' => $data['puk'],
			'network' => $data['network'] ?? null,
		];
		$puk_arr_not = [
			$this->primary_key => $data[$this->primary_key] ?? 0
		];

 		if(empty($data['puk']))
 		{
 			$this->errors['puk'] = 'PUK is required';
 		}else
 		if($this->first($puk_arr,$puk_arr_not))
 		{
 			$this->errors['puk'] = 'That PUK already exists for this network';
 		}

 		if(empty($data['serial_number']))
 		{
 			$this->errors['serial_number'] = 'Serial number is required';
 		}else{
 			$serial_arr = [
 				'network' => $data['network'] ?? null,
 				'serial_number' => $data['serial_number'],
 			];
 			$serial_arr_not = [
 				$this->primary_key => $data[$this->primary_key] ?? 0
 			];
 			if($this->first($serial_arr,$serial_arr_not))
 			{
 				$this->errors['serial_number'] = 'That serial number already exists for this network';
 			}
 		}

 		if(empty($data['cost']))
 		{
 			$this->errors['cost'] = 'Cost price is required';
 		}

 		if(empty($data['wholesale_price']))
 		{
 			$this->errors['wholesale_price'] = 'Wholesale price is required';
 		}

 		if(empty($data['retail_price']))
 		{
 			$this->errors['retail_price'] = 'Retail price is required';
 		}

 		/*if(empty($data['transaction_status']))
 		{
 			$this->errors['transaction_status'] = 'Sim status is required';
 		}*/

 		// Wallet validation removed - cashdrawer is central accounting system

 		if(!empty($data['buyer_phone']))
 		{
	 		if(!preg_match("/^(0\d{9}|\+233\d{9})$/", trim($data['buyer_phone'])))
			{
			    $this->errors['buyer_phone'] = "Client's Phone number not valid";
			}
 		}	

 		return empty($this->errors);
	}

	public function validate_sell(array $data):bool
	{
		$this->errors = [];

 		if(empty($data['network']))
 		{
 			$this->errors['network'] = 'Network type is required';
 		}

 		if(empty($data['serial_number']))
 		{
 			$this->errors['serial_number'] = 'Serial number is required';
 		}

 		if(!empty($data['number_assigned']))
 		{
	 		if(!preg_match("/^(0\d{9}|\+233\d{9})$/", trim($data['number_assigned'])))
			{
			    $this->errors['number_assigned'] = "Number assigned not valid";
			}
 		}

 		if(!empty($data['buyer_phone']))
 		{
	 		if(!preg_match("/^(0\d{9}|\+233\d{9})$/", trim($data['buyer_phone'])))
			{
			    $this->errors['buyer_phone'] = "Client's Phone number not valid";
			}
 		}

 		return empty($this->errors);
	}

	public function validate_update(array $data):bool
	{
		$this->errors = [];

		$status = strtolower(trim((string)($data['transaction_status'] ?? '')));
		if (in_array($status, ['declined', 'rejected', 'reject', 'rejection'], true) && trim((string)($data['rejection_reason'] ?? '')) === '') {
			$this->errors['rejection_reason'] = 'Rejection reason is required when declining or rejecting a SIM transaction.';
		}
		
		
		$number_assigned_ar = [
			'number_assigned'=>$data['number_assigned']
		];
		$number_assigned_ar_not = [
			$this->primary_key => $data[$this->primary_key] ?? 0
		];

 		if(!empty($data['number_assigned']))
 		{
 			if($this->first($number_assigned_ar,$number_assigned_ar_not))
	 		{
	 			$this->errors['number_assigned'] = 'That assigned phone number is already in use';
	 		}else 		
	 		if(!preg_match("/^(0\d{9}|\+233\d{9})$/", trim($data['number_assigned'])))
			{
			    $this->errors['number_assigned'] = 'Assigned phone number not valid';
			}
 		}

 		if(!empty($data['number_assigned']))
 		{
	 		if(!preg_match("/^(0\d{9}|\+233\d{9})$/", trim($data['number_assigned'])))
			{
			    $this->errors['number_assigned'] = "Number assigned not valid";
			}
 		}

 		if(!empty($data['buyer_phone']))
 		{
	 		if(!preg_match("/^(0\d{9}|\+233\d{9})$/", trim($data['buyer_phone'])))
			{
			    $this->errors['buyer_phone'] = "Client's Phone number not valid";
			}
 		}

		return empty($this->errors);
	}
}

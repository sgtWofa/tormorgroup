<?php

namespace Cashpickups;

use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * Cashpickup class
 */
class Cashpickup extends Model
{

	protected $table = 'cashpickups';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'id',
		'transaction_side',
		'date',
		'source',
		'account_paid_to',
		'sender_details',
		'phone_number',
		'tracking_id',
		'idcard_photo',
		'customer_photo',
		'receive_proof_photo',
		'pickup_person_idcard',
		'pickup_person_signature',
		'pickup_person_phone',
		'amount_involved',
		'payment_method',
		'pickup_charge',
		'amount_paid_out',
		'amount_transferred',
		'receive_id',
		'pay_type',
		'amount_requested',
		'denos_json',
		'customer_id',
		'customer_id_type',
		'customer_id_number',
		'customer_location',
		'credit_mode',
		'payout_channel',
		'assigned_user_id',
		'transfer_mode',
		'transfer_channel',
		'transfer_bank',
		'approved_by',
		'approved_at',
		'confirmed_by',
		'notes',
		'approval_status',
		'denial_reason',
		'transaction_status',
		'ledger_transfer_from_customer_id',
		'ledger_transfer_to_customer_id',
		'ledger_transfer_pair_id',
		'ledger_transfer_direction',
		'cfa_amount',
		'cfa_denos_json',
		'created_by',
		'updated_by',
		'disabled',
		'deleted',
		'date_created',
		'date_updated',
		'date_deleted',
	];

	protected $allowedUpdateColumns = [
		'transaction_side',
		'date',
		'source',
		'account_paid_to',
		'sender_details',
		'phone_number',
		'tracking_id',
		'idcard_photo',
		'customer_photo',
		'receive_proof_photo',
		'pickup_person_idcard',
		'pickup_person_signature',
		'pickup_person_phone',
		'amount_involved',
		'payment_method',
		'pickup_charge',
		'amount_paid_out',
		'amount_transferred',
		'receive_id',
		'pay_type',
		'amount_requested',
		'denos_json',
		'customer_id',
		'customer_id_type',
		'customer_id_number',
		'customer_location',
		'credit_mode',
		'payout_channel',
		'assigned_user_id',
		'transfer_mode',
		'transfer_channel',
		'transfer_bank',
		'approved_by',
		'approved_at',
		'confirmed_by',
		'notes',
		'approval_status',
		'denial_reason',
		'transaction_status',
		'ledger_transfer_from_customer_id',
		'ledger_transfer_to_customer_id',
		'ledger_transfer_pair_id',
		'ledger_transfer_direction',
		'cfa_amount',
		'cfa_denos_json',
		'updated_by',
		'disabled',
		'deleted',
		'date_updated',
		'date_deleted',
	];

	public function validate_insert(array $data): bool
	{
		$this->errors = [];

		$normalizePhone = function($v): string {
			$v = (string)$v;
			$v = trim($v);
			$v = str_replace([' ', '-', '(', ')'], '', $v);
			return $v;
		};
		$isValidPhone = function(string $v): bool {
			if ($v === '') return false;
			if (!preg_match('/^\+?[0-9]+$/', $v)) return false;
			$digits = ltrim($v, '+');
			$len = strlen($digits);
			return $len >= 10 && $len <= 15;
		};

		$side = strtolower(trim((string)($data['transaction_side'] ?? '')));
		if ($side === '' || !in_array($side, ['receive', 'pay'], true)) {
			$this->errors['transaction_side'] = 'Transaction side is required';
		}

		if (trim((string)($data['source'] ?? '')) === '') {
			$this->errors['source'] = 'Source is required';
		}

		if (trim((string)($data['tracking_id'] ?? '')) === '') {
			$this->errors['tracking_id'] = 'Tracking # / Transaction ID is required';
		}

		// Side-specific required fields
		if ($side === 'receive') {
			if (trim((string)($data['sender_details'] ?? '')) === '') {
				$this->errors['sender_details'] = "Sender's details is required";
			}
			$phone = $normalizePhone($data['phone_number'] ?? '');
			$idNumber = trim((string)($data['customer_id_number'] ?? ''));
			if ($phone === '' && $idNumber === '') {
				$this->errors['phone_number'] = 'Phone number or ID number is required';
			} elseif ($phone !== '' && !$isValidPhone($phone)) {
				$this->errors['phone_number'] = 'Invalid phone number';
			}
			$amount = (float)($data['amount_involved'] ?? 0);
			if ($amount <= 0) {
				$this->errors['amount_involved'] = 'Amount involved is required';
			}
		}

		if ($side === 'pay') {
			$customerPhone = $normalizePhone($data['phone_number'] ?? '');
			if ($customerPhone === '') {
				$this->errors['phone_number'] = 'Customer number is required';
			} elseif (!$isValidPhone($customerPhone)) {
				$this->errors['phone_number'] = 'Invalid customer number';
			}
			if (trim((string)($data['pickup_person_idcard'] ?? '')) === '') {
				$this->errors['pickup_person_idcard'] = 'Pick-up person ID card is required';
			}
			$pickupPhone = $normalizePhone($data['pickup_person_phone'] ?? '');
			if ($pickupPhone === '') {
				$this->errors['pickup_person_phone'] = 'Pick-up person phone is required';
			} elseif (!$isValidPhone($pickupPhone)) {
				$this->errors['pickup_person_phone'] = 'Invalid pick-up person phone';
			}
		}

		if ($side === 'pay') {
			$amount = (float)($data['amount_involved'] ?? 0);
			if ($amount <= 0) {
				$this->errors['amount_involved'] = 'Amount involved is required';
			}

			$payout = (float)($data['amount_paid_out'] ?? 0);
			if ($payout <= 0) {
				$this->errors['amount_paid_out'] = 'Payout amount is required';
			}

			$payType = strtolower(trim((string)($data['pay_type'] ?? '')));
			if ($payType === '' || !in_array($payType, ['all','part'], true)) {
				$this->errors['pay_type'] = 'Pay type is required';
			}
			$reqAmt = (float)($data['amount_requested'] ?? 0);
			if ($reqAmt <= 0) {
				$this->errors['amount_requested'] = 'Amount requested is required';
			}

			$pm = strtolower(trim((string)($data['payment_method'] ?? '')));
			if ($pm === '') {
				$this->errors['payment_method'] = 'Payment method is required';
			}

			if (trim((string)($data['confirmed_by'] ?? '')) === '') {
				$this->errors['confirmed_by'] = 'Confirmed by is required';
			}

			if (trim((string)($data['idcard_photo'] ?? '')) === '') {
				$this->errors['idcard_photo'] = ($pm === 'transfer')
					? 'Transfer receipt is required'
					: 'ID Card photo is required';
			}
			if ($pm !== 'transfer' && trim((string)($data['customer_photo'] ?? '')) === '') {
				$this->errors['customer_photo'] = 'Customer photo is required';
			}
		}

		return empty($this->errors);
	}

	public function validate_update(array $data): bool
	{
		return $this->validate_insert($data);
	}
}

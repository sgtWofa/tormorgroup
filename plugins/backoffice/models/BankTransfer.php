<?php

namespace BackOffices;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

class BankTransfer extends Model
{
	protected $table = 'bank_transfers_backoffice';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'id',
		'transaction_type',
		'transfer_category',
		'bank_name',
		'network_name',
		'amount',
		'denos_json',
		'source_vault_id',
		'destination_vault_id',
		'notes',
		'withdrawing_user_id',
		'withdrawal_status',
		'withdrawn_by',
		'withdrawn_at',
		'cheque_number',
		'cheque_date',
		'cheque_photo',
		'second_party_user_id',
		'second_party_status',
		'second_party_verified_by',
		'second_party_verified_at',
		'treasury_slip_photo',
		'creator_bank_receipt_photo',
		'creator_bank_receipt_uploaded_at',
		'creator_receipt_status',
		'third_party_user_id',
		'third_party_status',
		'third_party_reviewed_by',
		'third_party_reviewed_at',
		'third_party_reject_reason',
		'assignment_snapshot_json',
		'transaction_status',
		'disabled',
		'deleted',
		'created_by',
		'updated_by',
		'approved_by',
		'approved_at',
		'date_created',
		'date_updated',
		'date_deleted',
	];

	protected $allowedUpdateColumns = [
		'transaction_type',
		'transfer_category',
		'bank_name',
		'network_name',
		'amount',
		'denos_json',
		'source_vault_id',
		'destination_vault_id',
		'notes',
		'withdrawing_user_id',
		'withdrawal_status',
		'withdrawn_by',
		'withdrawn_at',
		'cheque_number',
		'cheque_date',
		'cheque_photo',
		'second_party_user_id',
		'second_party_status',
		'second_party_verified_by',
		'second_party_verified_at',
		'treasury_slip_photo',
		'creator_bank_receipt_photo',
		'creator_bank_receipt_uploaded_at',
		'creator_receipt_status',
		'third_party_user_id',
		'third_party_status',
		'third_party_reviewed_by',
		'third_party_reviewed_at',
		'third_party_reject_reason',
		'assignment_snapshot_json',
		'transaction_status',
		'disabled',
		'deleted',
		'updated_by',
		'approved_by',
		'approved_at',
		'date_updated',
		'date_deleted',
	];

	public function validate_insert(array $data): bool
	{
		$this->errors = [];
		if (!in_array(trim((string)($data['transaction_type'] ?? '')), ['To Bank', 'From Bank', 'To Vault', 'From Vault'], true)) {
			$this->errors['transaction_type'] = 'Select To Bank, From Bank, To Vault, or From Vault.';
		}
		if (empty($data['bank_name'])) {
			$this->errors['bank_name'] = 'Bank selection is required.';
		}
		if (empty($data['amount']) || !is_numeric($data['amount']) || (float)$data['amount'] <= 0) {
			$this->errors['amount'] = 'Amount must be greater than zero.';
		}

		return empty($this->errors);
	}
}

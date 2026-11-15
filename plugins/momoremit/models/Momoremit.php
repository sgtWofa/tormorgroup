<?php

namespace Momoremits;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * Momoremit class
 */
class Momoremit extends Model
{
	protected function validateWithdrawalTokenIdNumber(array $data): void
	{
		$txType = trim((string)($data['transaction_type'] ?? ''));
		$category = strtolower(trim((string)($data['category'] ?? '')));
		$idNumber = trim((string)($data['id_number'] ?? ''));

		if ($txType !== 'Withdrawal' || $category !== 'token') {
			return;
		}

		if ($idNumber === '') {
			$this->errors['id_number'] = 'ID Number is required for withdrawal token.';
			return;
		}

		if (!preg_match('/^GHA-\d{9}-\d$/', $idNumber)) {
			$this->errors['id_number'] = 'ID Number must be in the format GHA-123456789-1.';
		}
	}

	protected function validateGhanapayBankName(array $data): void
	{
		$transactionType = trim((string)($data['transaction_type'] ?? ''));
		$network = strtoupper(trim((string)($data['network'] ?? '')));
		$simType = trim((string)($data['sim_type'] ?? ''));
		$bankName = trim((string)($data['bank_name'] ?? ''));

		if ($transactionType !== 'Cash in' || $network !== 'GHANAPAY' || strcasecmp($simType, 'Bank') !== 0) {
			return;
		}

		if ($bankName === '') {
			$this->errors['bank_name'] = 'Bank selection is required for GhanaPay bank cash in.';
			return;
		}

		$allowedBanks = [
			'ACCESS BANK',
			'ADB',
			'APEX BANK',
			'BANK OF AFRICA',
			'CBG',
			'FIRST ATLANTIC BANK',
			'FIRST BANK GHANA',
			'GTBANK',
			'GCB',
			'NIB',
			'REPUBLIC BANK',
			'OMNIBSIC BANK',
			'SOCIETE GENERALE',
			'STANBIC BANK',
			'PRUDENTIAL BANK',
			'STANDARD CHARTERED',
			'ZENITH BANK',
			'UNITED BANK FOR AFRICA',
		];

		if (!in_array(strtoupper($bankName), $allowedBanks, true)) {
			$this->errors['bank_name'] = 'Selected bank is not valid for GhanaPay bank cash in.';
		}
	}

	protected function validateConfiguredAmountLimit(array $data): void
	{
		$limit = momoremits_find_transaction_limit(
			$data['transaction_type'] ?? '',
			$data['sim_type'] ?? '',
			$data['network'] ?? ''
		);
		if ($limit === null) {
			return;
		}

		$amountCandidates = [
			$data['amount'] ?? 0,
			$data['amount_payable'] ?? 0,
			$data['amount_load'] ?? 0,
			$data['amount_to_withdraw'] ?? 0,
		];
		$amount = 0.0;
		foreach ($amountCandidates as $candidate) {
			$value = (float)str_replace(',', '', (string)$candidate);
			if ($value > $amount) {
				$amount = $value;
			}
		}
		if ($amount > $limit + 0.009) {
			$this->errors['amount'] = 'Maximum allowed amount for this transaction is GHS ' . number_format($limit, 2) . '.';
		}
	}

	protected function validateWithdrawalDynamicFields(array $data): void
	{
		$txType = trim((string)($data['transaction_type'] ?? ''));
		$category = strtolower(trim((string)($data['category'] ?? '')));
		if ($txType !== 'Withdrawal') {
			return;
		}

		if (in_array($category, ['3rd party', 'token'], true) && trim((string)($data['receiver'] ?? '')) === '') {
			$this->errors['receiver'] = "Receiver's phone number is required";
		}

		if ($category === 'token') {
			if (trim((string)($data['token'] ?? '')) === '') {
				$this->errors['token'] = 'Token number is required';
			}
			if (trim((string)($data['secret_code'] ?? '')) === '') {
				$this->errors['secret_code'] = 'Secret code is required';
			}
		}
	}

	protected $table = 'momoremits';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'id',
		'network',
		'transaction_type',
		'transaction_status',
		'category',
		'sending_category',
		'phone',
		'sender',
		'bank_name',
		'sim_type',
		'charging_type',
		'amount_load',
		'amount_payable',
		'amount_sendable',
		'cash_received_amount',
		'change_required',
		'change_denominations',
		'amount',
		'balance_after',
		'transaction_image',
		'rejection_reason',
		'receiver',
		'token',
		'id_type',
		'id_number',
		'secret_code',
		'charge',
		'wallet_in_id',
		'wallet_out_id',
		'slug',
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
		'transaction_status',
		'category',
		'sending_category',
		'phone',
		'sender',
		'bank_name',
		'sim_type',
		'charging_type',
		'amount_load',
		'amount_payable',
		'amount_sendable',
		'cash_received_amount',
		'change_required',
		'change_denominations',
		'amount',
		'balance_after',
		'transaction_image',
		'rejection_reason',
		'receiver',
		'token',
		'id_type',
		'id_number',
		'secret_code',
		'charge',
		'wallet_in_id',
		'wallet_out_id',
		'slug',
		'disabled',
		'deleted',
		'updated_by',
		'date_updated',
		'date_deleted',
	];

	protected function removeColumnFromData(array $data, string $column): array
	{
		foreach (array_keys($data) as $key) {
			if (strcasecmp((string)$key, $column) === 0) {
				unset($data[$key]);
			}
		}

		return $data;
	}

	protected function getUnknownColumnFromError(string $error): string
	{
		if (preg_match("/Unknown column '([^']+)'/i", $error, $matches)) {
			return (string)($matches[1] ?? '');
		}

		return '';
	}

	public function insert(array $data)
	{
		$attempts = 0;
		while ($attempts < 5) {
			parent::insert($data);
			if (!$this->has_error) {
				return false;
			}

			$unknownColumn = $this->getUnknownColumnFromError((string)$this->error);
			if ($unknownColumn === '') {
				return false;
			}

			$nextData = $this->removeColumnFromData($data, $unknownColumn);
			if ($nextData === $data) {
				return false;
			}

			$data = $nextData;
			$attempts++;
		}

		return false;
	}

	public function update(string|int $_my_id, array $data)
	{
		$attempts = 0;
		while ($attempts < 5) {
			parent::update($_my_id, $data);
			if (!$this->has_error) {
				return false;
			}

			$unknownColumn = $this->getUnknownColumnFromError((string)$this->error);
			if ($unknownColumn === '') {
				return false;
			}

			$nextData = $this->removeColumnFromData($data, $unknownColumn);
			if ($nextData === $data) {
				return false;
			}

			$data = $nextData;
			$attempts++;
		}

		return false;
	}


	public function validate_insert(array $data): bool
	{
		$this->errors = [];

		$network = trim((string)($data['network'] ?? ''));
		$transactionType = trim((string)($data['transaction_type'] ?? ''));
		$sendingCategory = trim((string)($data['sending_category'] ?? ''));
		$phone = trim((string)($data['phone'] ?? ''));
		$sender = trim((string)($data['sender'] ?? ''));
		$requiresDistinctSender = $transactionType === 'Cash in' && $sendingCategory === 'Sending';

		if ($network === '')
		{
			$this->errors['network'] = 'Network type is required';
		}

		if ($transactionType === '')
		{
			$this->errors['transaction_type'] = 'Transaction type is required';
		}

		if ($transactionType === 'Withdrawal' && empty($data['category']))
		{
			$this->errors['category'] = 'Category is required for withdrawal';
		}

		if ($phone === '')
		{
			$this->errors['phone'] = 'Phone number is required';
		}
		elseif (!preg_match("/^(0\d{9}|\+233\d{9})$/", $phone))
		{
			$this->errors['phone'] = 'Phone number not valid';
		}

		if ($requiresDistinctSender && $sender === '')
		{
			$this->errors['sender'] = 'Sender number is required for Sending transactions';
		}

		if ($sender !== '')
		{
			if (!preg_match("/^(0\d{9}|\+233\d{9})$/", $sender))
			{
				$this->errors['sender'] = 'Sender number not valid';
			}
			elseif ($requiresDistinctSender && $sender === $phone)
			{
				$this->errors['sender'] = 'Sender number and phone number cannot be the same';
			}
		}

		$this->validateWithdrawalDynamicFields($data);
		$this->validateWithdrawalTokenIdNumber($data);
		$this->validateGhanapayBankName($data);
		$this->validateConfiguredAmountLimit($data);

		return empty($this->errors);
	}

	public function validate_update(array $data): bool
	{
		$this->errors = [];

		$network = trim((string)($data['network'] ?? ''));
		$transactionType = trim((string)($data['transaction_type'] ?? ''));
		$sendingCategory = trim((string)($data['sending_category'] ?? ''));
		$phone = trim((string)($data['phone'] ?? ''));
		$sender = trim((string)($data['sender'] ?? ''));
		$requiresDistinctSender = $transactionType === 'Cash in' && $sendingCategory === 'Sending';
		$transactionStatus = strtolower(trim((string)($data['transaction_status'] ?? '')));

		if ($network === '')
		{
			$this->errors['network'] = 'Network type is required';
		}

		if ($transactionType === '')
		{
			$this->errors['transaction_type'] = 'Transaction type is required';
		}

		if ($transactionType === 'Withdrawal' && empty($data['category']))
		{
			$this->errors['category'] = 'Category is required for withdrawal';
		}

		if (empty($data['amount']))
		{
			$this->errors['amount'] = 'Transaction amount is required';
		}

		if (
			empty($data['balance_after'])
			&& !in_array($transactionType, ['CIT', 'Withdrawal'], true)
			&& !in_array($transactionStatus, ['declined', 'rejected'], true)
		)
		{
			$this->errors['balance_after'] = 'Transaction Balance after is required';
		}

		/*if (in_array($transactionType, ['Withdrawal', 'Cash in', 'CIT'], true) && empty($data['transaction_image']))
		{
			$this->errors['transaction_image'] = 'Transaction message picture is required';
		}*/

		if ($phone === '')
		{
			$this->errors['phone'] = 'Phone number is required';
		}
		elseif (!preg_match("/^(0\d{9}|\+233\d{9})$/", $phone))
		{
			$this->errors['phone'] = 'Phone number not valid';
		}

		if ($requiresDistinctSender && $sender === '')
		{
			$this->errors['sender'] = 'Sender number is required for Sending transactions';
		}

		if ($sender !== '')
		{
			if (!preg_match("/^(0\d{9}|\+233\d{9})$/", $sender))
			{
				$this->errors['sender'] = 'Sender number not valid';
			}
			elseif ($requiresDistinctSender && $sender === $phone)
			{
				$this->errors['sender'] = 'Sender number and phone number cannot be the same';
			}
		}

		$this->validateWithdrawalDynamicFields($data);
		$this->validateWithdrawalTokenIdNumber($data);
		$this->validateGhanapayBankName($data);
		$this->validateConfiguredAmountLimit($data);

		return empty($this->errors);
	}
}

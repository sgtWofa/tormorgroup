<?php

namespace PettyCashes;

use \Model\Model;

defined('ROOT') or die("Direct script access denied");

class PettyCash extends Model
{
	protected $table = 'petty_cash_transactions';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'id',
		'reference',
		'title',
		'description',
		'currency',
		'category',
		'budget_amount',
		'items_json',
		'assigned_user_id',
		'status',
		'paid_denos_json',
		'spent_amount',
		'change_amount',
		'change_denos_json',
		'voucher_photo',
		'completion_note',
		'cashdrawer_id',
		'drawer_user_id',
		'change_cashdrawer_id',
		'change_drawer_user_id',
		'acknowledged_by',
		'acknowledged_at',
		'completed_by',
		'completed_at',
		'created_by',
		'updated_by',
		'deleted',
		'date_created',
		'date_updated',
		'date_deleted',
	];

	protected $allowedUpdateColumns = [
		'title',
		'description',
		'currency',
		'category',
		'budget_amount',
		'items_json',
		'assigned_user_id',
		'status',
		'paid_denos_json',
		'spent_amount',
		'change_amount',
		'change_denos_json',
		'voucher_photo',
		'completion_note',
		'cashdrawer_id',
		'drawer_user_id',
		'change_cashdrawer_id',
		'change_drawer_user_id',
		'acknowledged_by',
		'acknowledged_at',
		'completed_by',
		'completed_at',
		'updated_by',
		'deleted',
		'date_updated',
		'date_deleted',
	];

	public function validate_insert(array $data): bool
	{
		$this->errors = [];

		/*if (trim((string)($data['title'] ?? '')) === '') {
			$this->errors['title'] = 'Purpose / title is required.';
		}*/

		if (trim((string)($data['description'] ?? '')) === '') {
			$this->errors['description'] = 'Description is required.';
		}

		$currency = strtoupper(trim((string)($data['currency'] ?? 'GHS')));
		if (!in_array($currency, ['GHS', 'CFA'], true)) {
			$this->errors['currency'] = 'Select a valid currency.';
		}

		$category = pettycash_normalize_category($data['category'] ?? '');
		if ($category === '') {
			$this->errors['category'] = 'Select a valid petty cash category.';
		}

		$budgetAmount = (float)($data['budget_amount'] ?? 0);
		if ($budgetAmount <= 0) {
			$this->errors['budget_amount'] = 'Budget amount must be greater than zero.';
		}

		$items = pettycash_decode_items($data['items_json'] ?? '[]', $currency);
		if (empty($items)) {
			$this->errors['items_json'] = 'Add at least one petty cash item with a budgeted amount.';
		}

		$assignedUserId = (int)($data['assigned_user_id'] ?? 0);
		if ($assignedUserId <= 0) {
			$this->errors['assigned_user_id'] = 'Select an assigned user.';
		}

		return empty($this->errors);
	}

	public function validate_update(array $data): bool
	{
		return $this->validate_insert($data);
	}
}

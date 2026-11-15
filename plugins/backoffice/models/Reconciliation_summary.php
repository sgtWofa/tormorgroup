<?php

namespace BackOffices;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * Reconciliation class
 */
class Reconciliation_summary extends Model
{
    protected $table = 'reconciliations_summary';
    public $primary_key = 'id';

    protected $allowedColumns = [
        'id',
        'session_id',
        'late_access_id',
        'balance',
        'expected_balance',
        'variance',
        'remarks',
        'transaction_status',
        'rejection_reason',
        'rejected_by',
        'date_rejected',
        'approved_by',
        'date_approved',
        'created_by',
        'updated_by',
        'disabled',
        'deleted',
        'date_created',
        'date_updated',
        'date_deleted',
    ];

    protected $allowedUpdateColumns = [
        'balance',
        'expected_balance',
        'variance',
        'remarks',
        'transaction_status',
        'rejection_reason',
        'rejected_by',
        'date_rejected',
        'approved_by',
        'date_approved',
        'updated_by',
        'disabled',
        'deleted',
        'date_updated',
        'date_deleted',
    ];

    public function validate_insert(array $data): bool
    {
        $this->errors = [];

        /*if (empty($data['balance'])) {
            $this->errors['balance'] = 'Balance is required';
        }*/

      // Log errors for debugging
        if (!empty($this->errors)) {
            error_log(print_r($this->errors, true));
        }

        return empty($this->errors);
    }

    public function validate_update(array $data): bool
    {
       $this->errors = [];

        // Log errors for debugging
        if (!empty($this->errors)) {
            error_log(print_r($this->errors, true));
        }

        return empty($this->errors);
    }
}

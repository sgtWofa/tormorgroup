<?php

namespace BackOffices;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * Reconciliation class
 */
class Reconciliation extends Model
{
    protected $table = 'reconciliations';
    public $primary_key = 'id';

    protected $allowedColumns = [
        'id',
        'wallet_id',
        'session_id',
        'balance',
        'expected_balance',
        'variance',
        'remarks',
        'proof_image',
		'denos_json',
		'system_denos_json',
        'created_by',
        'disabled',
        'deleted',
        'date_created',
        'date_deleted',
    ];

    protected $allowedUpdateColumns = [
        'balance',
        'expected_balance',
        'variance',
        'remarks',
        'proof_image',
		'denos_json',
		'system_denos_json',
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

        if (empty($data['balance'])) {
            $this->errors['balance'] = 'Balance is required';
        }

        // Log errors for debugging
        if (!empty($this->errors)) {
            error_log(print_r($this->errors, true));
        }

        return empty($this->errors);
    }
}

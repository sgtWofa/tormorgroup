<?php

namespace BackOffices;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * RequestClass class
 */
class RequestClass extends Model
{
    protected $table = 'requests';
    public $primary_key = 'id';

    protected $allowedColumns = [
        'id',
        'network',
        'request_type',
        'till_number',
        'wallet_number',
        'description',
        'response',
        'amount',
        'assigned',
        'cashdrawer_id',
        'drawer_user_id',
        'denos_json',
        'verified_denos_json',
        'approval_receipt_photo',
        'approval_receipt_uploaded_at',
        'transaction_status',
        'reject_reason',
        'created_by',
        'updated_by',
        'approved_by',
        'approved_at',
        'disabled',
        'deleted',
        'date_created',
        'date_updated',
        'date_deleted',
    ];

    protected $allowedUpdateColumns = [
        'network',
        'request_type',
        'till_number',
        'wallet_number',
        'description',
        'response',
        'amount',
        'assigned',
        'cashdrawer_id',
        'drawer_user_id',
        'denos_json',
        'verified_denos_json',
        'approval_receipt_photo',
        'approval_receipt_uploaded_at',
        'transaction_status',
        'reject_reason',
        'created_by',
        'updated_by',
        'approved_by',
        'approved_at',
        'disabled',
        'deleted',
        'date_created',
        'date_updated',
        'date_deleted',
    ];

    public function validate_insert(array $data): bool
    {
        $this->errors = [];

        if (empty($data['description'])) {
            $this->errors['description'] = 'Description is required';
        }

        if (empty($data['transaction_status'])) {
            $this->errors['transaction_status'] = 'Status is required';
        }

        if (empty($data['network'])) {
            $this->errors['network'] = 'Request option is required';
        }

        if (empty($data['request_type'])) {
            $this->errors['request_type'] = 'Request type is required';
        }

        if (empty($data['assigned'])) {
            $this->errors['assigned'] = 'Request assigned is required';
        }

        if (empty($data['amount']) || !is_numeric($data['amount']) || $data['amount'] <= 0) {
            $this->errors['amount'] = 'Request amount must be greater than zero';
        }

        // Log errors for debugging
        if (!empty($this->errors)) {
            error_log(print_r($this->errors, true));
        }

        return empty($this->errors);
    }

    public function validate_update(array $data): bool
    {
       $this->errors = [];

        if (empty($data['transaction_status'])) {
            $this->errors['transaction_status'] = 'Status is required';
        }

        // Log errors for debugging
        if (!empty($this->errors)) {
            error_log(print_r($this->errors, true));
        }

        return empty($this->errors);
    }
}

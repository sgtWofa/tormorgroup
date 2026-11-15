<?php

namespace Reversals;

use Model\Model;

defined('ROOT') or die("Direct script access denied");

class Reversal extends Model
{
    protected $table = 'reversals';
    public $primary_key = 'id';

    protected $allowedColumns = [
        'id',
        'source_transaction_type',
        'source_transaction_id',
        'source_table',
        'source_channel_kind',
        'source_channel_key',
        'source_snapshot_json',
        'network',
        'assigned',
        'service_type',
        'sender_details',
        'reversal_medium',
        'receiver_details',
        'reason',
        'reversal_type',
        'workflow_stage',
        'manager_review_status',
        'manager_review_note',
        'manager_reviewed_by',
        'manager_reviewed_at',
        'resolution_status',
        'resolution_description',
        'action_proof_image',
        'action_proof_uploaded_at',
        'tracking_days',
        'tracking_started_at',
        'tracking_due_at',
        'tracking_history_json',
        'followup_feedback',
        'latest_refund_amount',
        'latest_refund_proof_image',
        'latest_refund_status',
        'latest_refund_submitted_at',
        'latest_refund_reviewed_by',
        'latest_refund_reviewed_at',
        'latest_refund_review_note',
        'refund_history_json',
        'latest_claim_amount',
        'latest_claim_selfie_image',
        'latest_claim_signature_image',
        'latest_claim_denos_json',
        'latest_claim_status',
        'latest_claim_submitted_at',
        'latest_claim_reviewed_by',
        'latest_claim_reviewed_at',
        'latest_claim_review_note',
        'claim_history_json',
        'response',
        'transaction_date',
        'agent_involved',
        'waiting_period',
        'amount',
        'transaction_status',
        'created_by',
        'updated_by',
        'reversal_received',
        'total_refunded',
        'total_claimed',
        'source_effects_reversed_at',
        'source_effects_reversed_by',
        'source_effects_reversal_note',
        'closed_at',
        'disabled',
        'deleted',
        'reversal_date',
        'date_created',
        'date_updated',
        'date_deleted',
    ];

    protected $allowedUpdateColumns = [
        'source_transaction_type',
        'source_transaction_id',
        'source_table',
        'source_channel_kind',
        'source_channel_key',
        'source_snapshot_json',
        'network',
        'assigned',
        'service_type',
        'sender_details',
        'reversal_medium',
        'receiver_details',
        'reason',
        'reversal_type',
        'workflow_stage',
        'manager_review_status',
        'manager_review_note',
        'manager_reviewed_by',
        'manager_reviewed_at',
        'resolution_status',
        'resolution_description',
        'action_proof_image',
        'action_proof_uploaded_at',
        'tracking_days',
        'tracking_started_at',
        'tracking_due_at',
        'tracking_history_json',
        'followup_feedback',
        'latest_refund_amount',
        'latest_refund_proof_image',
        'latest_refund_status',
        'latest_refund_submitted_at',
        'latest_refund_reviewed_by',
        'latest_refund_reviewed_at',
        'latest_refund_review_note',
        'refund_history_json',
        'latest_claim_amount',
        'latest_claim_selfie_image',
        'latest_claim_signature_image',
        'latest_claim_denos_json',
        'latest_claim_status',
        'latest_claim_submitted_at',
        'latest_claim_reviewed_by',
        'latest_claim_reviewed_at',
        'latest_claim_review_note',
        'claim_history_json',
        'response',
        'transaction_date',
        'reversal_received',
        'reversal_date',
        'agent_involved',
        'waiting_period',
        'amount',
        'transaction_status',
        'updated_by',
        'total_refunded',
        'total_claimed',
        'source_effects_reversed_at',
        'source_effects_reversed_by',
        'source_effects_reversal_note',
        'closed_at',
        'disabled',
        'deleted',
        'date_updated',
        'date_deleted',
    ];

    public function validate_insert(array $data): bool
    {
        $this->errors = [];

        if (empty($data['source_transaction_type'])) {
            $this->errors['source_transaction_type'] = 'Transaction type is required.';
        }

        if (empty($data['source_transaction_id'])) {
            $this->errors['source_transaction_id'] = 'Transaction ID is required.';
        }

        if ((int)($data['assigned'] ?? 0) <= 0) {
            $this->errors['assigned'] = 'Assign this reversal to a valid general manager or admin.';
        }

        if (trim((string)($data['network'] ?? '')) === '') {
            $this->errors['network'] = 'Service provider or network is required.';
        }

        if (trim((string)($data['service_type'] ?? '')) === '') {
            $this->errors['service_type'] = 'Service type is required.';
        }

        if (trim((string)($data['receiver_details'] ?? '')) === '') {
            $this->errors['receiver_details'] = 'Receiver or account details are required.';
        }

        if (trim((string)($data['sender_details'] ?? '')) === '') {
            $this->errors['sender_details'] = 'Sender details are required.';
        }

        if (trim((string)($data['reversal_type'] ?? '')) === '') {
            $this->errors['reversal_type'] = 'Reversal type is required.';
        }

        $amount = (float)($data['amount'] ?? 0);
        if ($amount <= 0) {
            $this->errors['amount'] = 'Amount involved must be greater than zero.';
        }

        return empty($this->errors);
    }

    public function validate_update(array $data): bool
    {
        $this->errors = [];
        return true;
    }
}

<?php

use Core\Database;

if (!function_exists('reversals_timezone')) {
    function reversals_timezone(): DateTimeZone
    {
        static $tz = null;
        if ($tz instanceof DateTimeZone) {
            return $tz;
        }

        $tz = new DateTimeZone('Africa/Accra');
        return $tz;
    }
}

if (!function_exists('reversals_now')) {
    function reversals_now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', reversals_timezone());
    }
}

if (!function_exists('reversals_today')) {
    function reversals_today(): string
    {
        return reversals_now()->format('Y-m-d');
    }
}

if (!function_exists('reversals_transaction_type_options')) {
    function reversals_transaction_type_options(): array
    {
        return [
            'agencybanking' => 'Agencybanking',
            'airtimebundle' => 'Airtimebundle',
            'momoremit' => 'Momoremit',
            'payment_services' => 'Payment Services',
            'cashpickup_payout_transfer' => 'Cashpickup Payout Transfer',
        ];
    }
}

if (!function_exists('reversals_reversal_type_options')) {
    function reversals_reversal_type_options(): array
    {
        return [
            'pending' => 'Pending',
            'wrong number' => 'Wrong Number',
            'wrong account number' => 'Wrong Account Number',
            'duplicate' => 'Duplicate',
            'reversal' => 'Reversal',
        ];
    }
}

if (!function_exists('reversals_resolution_status_options')) {
    function reversals_resolution_status_options(): array
    {
        return [
            'awaiting' => 'Awaiting',
            'non-reversible' => 'Non-reversible',
        ];
    }
}

if (!function_exists('reversals_review_decision_options')) {
    function reversals_review_decision_options(): array
    {
        return [
            'acknowledged' => 'Acknowledge',
            'rejected' => 'Reject',
        ];
    }
}

if (!function_exists('reversals_status_filter_options')) {
    function reversals_status_filter_options(): array
    {
        return [
            'pending manager acknowledgement' => 'Pending Manager Acknowledgement',
            'manager acknowledged' => 'Manager Acknowledged',
            'manager rejected' => 'Manager Rejected',
            'awaiting proof of action' => 'Awaiting Proof Of Action',
            'proof uploaded - start tracking' => 'Proof Uploaded - Start Tracking',
            'tracking active' => 'Tracking Active',
            'follow up required' => 'Follow Up Required',
            'refund pending review' => 'Refund Pending Review',
            'refund rejected - resubmit' => 'Refund Rejected - Resubmit',
            'partial refund acknowledged' => 'Partial Refund Acknowledged',
            'full refund acknowledged' => 'Full Refund Acknowledged',
            'claim pending review' => 'Claim Pending Review',
            'claim rejected - resubmit' => 'Claim Rejected - Resubmit',
            'partial claim acknowledged' => 'Partial Claim Acknowledged',
            'non-reversible' => 'Non-reversible',
            'closed' => 'Closed',
        ];
    }
}

if (!function_exists('reversals_status_badge_class')) {
    function reversals_status_badge_class(?string $status): string
    {
        $status = strtolower(trim((string)$status));
        return match ($status) {
            'pending manager acknowledgement',
            'refund pending review',
            'claim pending review' => 'badge bg-warning text-dark',
            'manager acknowledged',
            'proof uploaded - start tracking',
            'tracking active',
            'follow up required',
            'partial refund acknowledged',
            'partial claim acknowledged' => 'badge bg-info text-dark',
            'full refund acknowledged',
            'closed' => 'badge bg-success',
            'manager rejected',
            'non-reversible',
            'refund rejected - resubmit',
            'claim rejected - resubmit' => 'badge bg-danger',
            default => 'badge bg-secondary',
        };
    }
}

if (!function_exists('reversals_normalize_role_names')) {
    function reversals_normalize_role_names($session = null): array
    {
        $session = $session ?: get_session();
        $roles = $session->roles ?? [];
        if (!is_array($roles)) {
            $roles = [$roles];
        }

        $normalized = [];
        foreach ($roles as $role) {
            $role = strtolower(trim((string)$role));
            if ($role !== '') {
                $normalized[] = $role;
            }
        }

        return array_values(array_unique($normalized));
    }
}

if (!function_exists('reversals_user_is_admin')) {
    function reversals_user_is_admin($session = null): bool
    {
        if (user_can('is_admin')) {
            return true;
        }

        if (function_exists('backoffice_user_is_admin')) {
            return backoffice_user_is_admin($session);
        }

        return in_array('admin', reversals_normalize_role_names($session), true);
    }
}

if (!function_exists('reversals_user_is_general_manager')) {
    function reversals_user_is_general_manager($session = null): bool
    {
        if (user_can('is_general_manager')) {
            return true;
        }

        if (function_exists('backoffice_user_is_general_manager')) {
            return backoffice_user_is_general_manager($session);
        }

        $needles = ['general manager', 'general_manager', 'generalmanager', 'gm'];
        return (bool)array_intersect(reversals_normalize_role_names($session), $needles);
    }
}

if (!function_exists('reversals_user_is_manager')) {
    function reversals_user_is_manager($session = null): bool
    {
        if (user_can('is_manager')) {
            return true;
        }

        $needles = ['manager'];
        return (bool)array_intersect(reversals_normalize_role_names($session), $needles);
    }
}

if (!function_exists('reversals_row_role_names')) {
    function reversals_row_role_names($row): array
    {
        $roles = $row->roles ?? [];
        if (!is_array($roles)) {
            $roles = [$roles];
        }

        $normalized = [];
        foreach ($roles as $role) {
            $role = strtolower(trim((string)$role));
            if ($role !== '') {
                $normalized[] = $role;
            }
        }

        return array_values(array_unique($normalized));
    }
}

if (!function_exists('reversals_row_is_admin_or_general_manager')) {
    function reversals_row_is_admin_or_general_manager($row): bool
    {
        $roles = reversals_row_role_names($row);
        $needles = ['admin', 'general manager', 'general_manager', 'generalmanager', 'gm'];
        return (bool)array_intersect($roles, $needles);
    }
}

if (!function_exists('reversals_assignable_reviewers')) {
    function reversals_assignable_reviewers(int $excludeUserId = 0): array
    {
        try {
            if (function_exists('BackOfficeCashdrawer\\getAssignableUsersToday')) {
                $rows = \BackOfficeCashdrawer\getAssignableUsersToday($excludeUserId);
                return array_values(array_filter($rows, function($row){
                    return reversals_row_is_admin_or_general_manager($row);
                }));
            }

            $db = new Database;
            if (!$db->table_exists(['users', 'cashdrawers'])) {
                return [];
            }

            $today = reversals_today();
            $params = ['today' => $today];
            $excludeSql = '';
            if ($excludeUserId > 0) {
                $excludeSql = ' AND u.id <> :exclude_user_id';
                $params['exclude_user_id'] = $excludeUserId;
            }

            $rows = $db->query(
                "SELECT DISTINCT u.*
                 FROM users u
                 INNER JOIN cashdrawers c ON c.user_id = u.id AND DATE(c.date_created) = :today
                 WHERE u.deleted = 0{$excludeSql}
                 ORDER BY u.first_name ASC, u.last_name ASC, u.id ASC",
                $params
            ) ?: [];

            return array_values(array_filter($rows, function($row){
                return reversals_row_is_admin_or_general_manager($row);
            }));
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('reversals_assigned_reviewer_id')) {
    function reversals_assigned_reviewer_id($row): int
    {
        return (int)($row->assigned ?? 0);
    }
}

if (!function_exists('reversals_user_is_assigned_reviewer')) {
    function reversals_user_is_assigned_reviewer($row, $session = null): bool
    {
        $session = $session ?: get_session();
        $assignedId = reversals_assigned_reviewer_id($row);
        $userId = (int)($session->id ?? 0);
        if ($assignedId <= 0 || $userId <= 0) {
            return false;
        }

        if ($userId !== $assignedId) {
            return false;
        }

        return reversals_user_is_admin($session) || reversals_user_is_general_manager($session);
    }
}

if (!function_exists('reversals_user_can_initial_review')) {
    function reversals_user_can_initial_review($session = null, $row = null): bool
    {
        if ($row !== null && reversals_assigned_reviewer_id($row) > 0) {
            return reversals_user_is_assigned_reviewer($row, $session);
        }

        return reversals_user_is_admin($session) || reversals_user_is_general_manager($session) || reversals_user_is_manager($session);
    }
}

if (!function_exists('reversals_user_can_refund_review')) {
    function reversals_user_can_refund_review($session = null, $row = null): bool
    {
        if ($row !== null && reversals_assigned_reviewer_id($row) > 0) {
            return reversals_user_is_assigned_reviewer($row, $session);
        }

        return reversals_user_is_admin($session) || reversals_user_is_general_manager($session);
    }
}

if (!function_exists('reversals_user_can_claim_review')) {
    function reversals_user_can_claim_review($session = null, $row = null): bool
    {
        if ($row !== null && reversals_assigned_reviewer_id($row) > 0) {
            return reversals_user_is_assigned_reviewer($row, $session);
        }

        return reversals_user_can_initial_review($session);
    }
}

if (!function_exists('reversals_fetch_user_summary')) {
    function reversals_fetch_user_summary(int $userId)
    {
        if ($userId <= 0) {
            return null;
        }

        try {
            $db = new Database;
            if (!$db->table_exists(['users'])) {
                return null;
            }

            $rows = $db->query(
                "SELECT id, first_name, last_name, image
                 FROM users
                 WHERE id = :id
                 LIMIT 1",
                ['id' => $userId]
            ) ?: [];

            return $rows[0] ?? null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('reversals_user_can_view_row')) {
    function reversals_user_can_view_row($row, $session = null): bool
    {
        $session = $session ?: get_session();
        $userId = (int)($session->id ?? 0);
        if ($userId <= 0) {
            return false;
        }

        if (reversals_user_can_initial_review($session) || reversals_user_can_refund_review($session)) {
            return true;
        }

        $createdBy = (int)($row->created_by ?? 0);
        $sourceUser = (int)($row->agent_involved ?? 0);
        return $createdBy === $userId || $sourceUser === $userId;
    }
}

if (!function_exists('reversals_user_can_creator_manage')) {
    function reversals_user_can_creator_manage($row, $session = null): bool
    {
        $session = $session ?: get_session();
        $userId = (int)($session->id ?? 0);
        return $userId > 0 && $userId === (int)($row->created_by ?? 0);
    }
}

if (!function_exists('reversals_ensure_workflow_schema')) {
    function reversals_ensure_workflow_schema(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $ensured = true;

        try {
            $db = new Database;
            $schema = $db->query("SHOW COLUMNS FROM reversals") ?: [];
            if (empty($schema)) {
                return;
            }

            $existing = [];
            foreach ($schema as $column) {
                $existing[strtolower((string)($column->Field ?? ''))] = true;
            }

            $columns = [
                'source_transaction_type' => "ALTER TABLE reversals ADD COLUMN source_transaction_type VARCHAR(100) NULL AFTER id",
                'source_transaction_id' => "ALTER TABLE reversals ADD COLUMN source_transaction_id VARCHAR(100) NULL AFTER source_transaction_type",
                'source_table' => "ALTER TABLE reversals ADD COLUMN source_table VARCHAR(100) NULL AFTER source_transaction_id",
                'source_channel_kind' => "ALTER TABLE reversals ADD COLUMN source_channel_kind VARCHAR(50) NULL AFTER source_table",
                'source_channel_key' => "ALTER TABLE reversals ADD COLUMN source_channel_key VARCHAR(100) NULL AFTER source_channel_kind",
                'source_snapshot_json' => "ALTER TABLE reversals ADD COLUMN source_snapshot_json LONGTEXT NULL AFTER source_channel_key",
                'reversal_type' => "ALTER TABLE reversals ADD COLUMN reversal_type VARCHAR(100) NULL AFTER reason",
                'workflow_stage' => "ALTER TABLE reversals ADD COLUMN workflow_stage VARCHAR(100) NULL AFTER reversal_type",
                'manager_review_status' => "ALTER TABLE reversals ADD COLUMN manager_review_status VARCHAR(50) NULL AFTER workflow_stage",
                'manager_review_note' => "ALTER TABLE reversals ADD COLUMN manager_review_note TEXT NULL AFTER manager_review_status",
                'manager_reviewed_by' => "ALTER TABLE reversals ADD COLUMN manager_reviewed_by BIGINT UNSIGNED NULL AFTER manager_review_note",
                'manager_reviewed_at' => "ALTER TABLE reversals ADD COLUMN manager_reviewed_at DATETIME NULL AFTER manager_reviewed_by",
                'resolution_status' => "ALTER TABLE reversals ADD COLUMN resolution_status VARCHAR(50) NULL AFTER manager_reviewed_at",
                'resolution_description' => "ALTER TABLE reversals ADD COLUMN resolution_description TEXT NULL AFTER resolution_status",
                'action_proof_image' => "ALTER TABLE reversals ADD COLUMN action_proof_image VARCHAR(255) NULL AFTER resolution_description",
                'action_proof_uploaded_at' => "ALTER TABLE reversals ADD COLUMN action_proof_uploaded_at DATETIME NULL AFTER action_proof_image",
                'tracking_days' => "ALTER TABLE reversals ADD COLUMN tracking_days INT NULL AFTER action_proof_uploaded_at",
                'tracking_started_at' => "ALTER TABLE reversals ADD COLUMN tracking_started_at DATETIME NULL AFTER tracking_days",
                'tracking_due_at' => "ALTER TABLE reversals ADD COLUMN tracking_due_at DATETIME NULL AFTER tracking_started_at",
                'tracking_history_json' => "ALTER TABLE reversals ADD COLUMN tracking_history_json LONGTEXT NULL AFTER tracking_due_at",
                'followup_feedback' => "ALTER TABLE reversals ADD COLUMN followup_feedback TEXT NULL AFTER tracking_history_json",
                'latest_refund_amount' => "ALTER TABLE reversals ADD COLUMN latest_refund_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER followup_feedback",
                'latest_refund_proof_image' => "ALTER TABLE reversals ADD COLUMN latest_refund_proof_image VARCHAR(255) NULL AFTER latest_refund_amount",
                'latest_refund_status' => "ALTER TABLE reversals ADD COLUMN latest_refund_status VARCHAR(50) NULL AFTER latest_refund_proof_image",
                'latest_refund_submitted_at' => "ALTER TABLE reversals ADD COLUMN latest_refund_submitted_at DATETIME NULL AFTER latest_refund_status",
                'latest_refund_reviewed_by' => "ALTER TABLE reversals ADD COLUMN latest_refund_reviewed_by BIGINT UNSIGNED NULL AFTER latest_refund_submitted_at",
                'latest_refund_reviewed_at' => "ALTER TABLE reversals ADD COLUMN latest_refund_reviewed_at DATETIME NULL AFTER latest_refund_reviewed_by",
                'latest_refund_review_note' => "ALTER TABLE reversals ADD COLUMN latest_refund_review_note TEXT NULL AFTER latest_refund_reviewed_at",
                'refund_history_json' => "ALTER TABLE reversals ADD COLUMN refund_history_json LONGTEXT NULL AFTER latest_refund_review_note",
                'latest_claim_amount' => "ALTER TABLE reversals ADD COLUMN latest_claim_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER refund_history_json",
                'latest_claim_selfie_image' => "ALTER TABLE reversals ADD COLUMN latest_claim_selfie_image VARCHAR(255) NULL AFTER latest_claim_amount",
                'latest_claim_signature_image' => "ALTER TABLE reversals ADD COLUMN latest_claim_signature_image VARCHAR(255) NULL AFTER latest_claim_selfie_image",
                'latest_claim_denos_json' => "ALTER TABLE reversals ADD COLUMN latest_claim_denos_json LONGTEXT NULL AFTER latest_claim_signature_image",
                'latest_claim_status' => "ALTER TABLE reversals ADD COLUMN latest_claim_status VARCHAR(50) NULL AFTER latest_claim_denos_json",
                'latest_claim_submitted_at' => "ALTER TABLE reversals ADD COLUMN latest_claim_submitted_at DATETIME NULL AFTER latest_claim_status",
                'latest_claim_reviewed_by' => "ALTER TABLE reversals ADD COLUMN latest_claim_reviewed_by BIGINT UNSIGNED NULL AFTER latest_claim_submitted_at",
                'latest_claim_reviewed_at' => "ALTER TABLE reversals ADD COLUMN latest_claim_reviewed_at DATETIME NULL AFTER latest_claim_reviewed_by",
                'latest_claim_review_note' => "ALTER TABLE reversals ADD COLUMN latest_claim_review_note TEXT NULL AFTER latest_claim_reviewed_at",
                'claim_history_json' => "ALTER TABLE reversals ADD COLUMN claim_history_json LONGTEXT NULL AFTER latest_claim_review_note",
                'total_refunded' => "ALTER TABLE reversals ADD COLUMN total_refunded DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER claim_history_json",
                'total_claimed' => "ALTER TABLE reversals ADD COLUMN total_claimed DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER total_refunded",
                'source_effects_reversed_at' => "ALTER TABLE reversals ADD COLUMN source_effects_reversed_at DATETIME NULL AFTER total_claimed",
                'source_effects_reversed_by' => "ALTER TABLE reversals ADD COLUMN source_effects_reversed_by BIGINT UNSIGNED NULL AFTER source_effects_reversed_at",
                'source_effects_reversal_note' => "ALTER TABLE reversals ADD COLUMN source_effects_reversal_note TEXT NULL AFTER source_effects_reversed_by",
                'closed_at' => "ALTER TABLE reversals ADD COLUMN closed_at DATETIME NULL AFTER total_claimed",
            ];

            foreach ($columns as $name => $sql) {
                if (!empty($existing[$name])) {
                    continue;
                }
                try {
                    $db->query($sql);
                } catch (Throwable $e) {
                    continue;
                }
            }
        } catch (Throwable $e) {
            return;
        }
    }
}

if (!function_exists('reversals_lookup_source_table')) {
    function reversals_lookup_source_table(string $transactionType): ?string
    {
        $transactionType = reversals_normalize_transaction_type($transactionType);
        return match ($transactionType) {
            'agencybanking' => 'agencybankings',
            'airtimebundle' => 'airtimebundles',
            'momoremit' => 'momoremits',
            'payment_services' => 'payment_services',
            'cashpickup_payout_transfer' => 'cashpickups',
            default => null,
        };
    }
}

if (!function_exists('reversals_normalize_transaction_type')) {
    function reversals_normalize_transaction_type(?string $transactionType): string
    {
        $transactionType = strtolower(trim((string)$transactionType));
        $map = [
            'payment services' => 'payment_services',
            'payment_service' => 'payment_services',
            'cashpickup payout transfer' => 'cashpickup_payout_transfer',
            'cashpickup_payout' => 'cashpickup_payout_transfer',
            'cashpickup' => 'cashpickup_payout_transfer',
        ];
        return $map[$transactionType] ?? $transactionType;
    }
}

if (!function_exists('reversals_normalize_provider')) {
    function reversals_normalize_provider($value): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        if (function_exists('users_manager_normalize_sim_network')) {
            return users_manager_normalize_sim_network($value);
        }

        $value = strtoupper(str_replace(['_', '-'], ' ', $value));
        return trim(preg_replace('/\s+/', ' ', $value));
    }
}

if (!function_exists('reversals_extract_source_channel')) {
    function reversals_extract_source_channel(string $transactionType, $row): array
    {
        $transactionType = reversals_normalize_transaction_type($transactionType);
        $provider = '';
        $serviceType = '';
        $receiver = '';
        $amount = 0.0;
        $sender = '';
        $channelKind = 'ecash';
        $channelKey = '';

        switch ($transactionType) {
            case 'agencybanking':
                $provider = reversals_normalize_provider($row->network ?? $row->destination_bank ?? '');
                $serviceType = trim((string)($row->transaction_type ?? 'Agency Banking'));
                $receiver = trim((string)($row->account_number ?? $row->receiver ?? $row->phone ?? ''));
                $amount = (float)($row->amount_payable ?? $row->amount_withdrawn ?? $row->amount ?? 0);
                $sender = trim((string)($row->sender_phone ?? ''));
                $channelKind = (stripos($provider, 'BANK') !== false) ? 'bank' : 'ecash';
                break;

            case 'airtimebundle':
                $provider = reversals_normalize_provider($row->network ?? '');
                $serviceType = trim((string)($row->category ?? $row->transaction_type ?? 'Airtimebundle'));
                $receiver = trim((string)($row->receiver ?? $row->phone ?? ''));
                $amount = (float)($row->amount_to_send ?? $row->amount_paid ?? $row->amount ?? 0);
                $sender = trim((string)($row->phone ?? ''));
                $channelKind = (strtolower($serviceType) === 'evd') ? 'evd' : 'ecash';
                break;

            case 'momoremit':
                $provider = reversals_normalize_provider($row->network ?? '');
                $serviceType = trim((string)($row->transaction_type ?? $row->category ?? 'MoMo Remit'));
                $receiver = trim((string)($row->receiver ?? $row->phone ?? ''));
                $amount = (float)($row->amount_payable ?? $row->amount_sendable ?? $row->amount ?? 0);
                $sender = trim((string)($row->phone ?? ''));
                $channelKind = (stripos($provider, 'BANK') !== false) ? 'bank' : 'ecash';
                break;

            case 'payment_services':
                $medium = strtoupper(trim((string)($row->payment_medium ?? '')));
                $provider = $medium === 'BANK'
                    ? reversals_normalize_provider($row->bank ?? $row->institution ?? $row->network ?? '')
                    : reversals_normalize_provider($row->network ?? $row->bank ?? $row->institution ?? '');
                $serviceType = trim((string)($row->category ?? $row->payment_medium ?? 'Payment Service'));
                $receiver = trim((string)($row->customer_id ?? $row->customer_phone ?? ''));
                $amount = (float)($row->amount ?? 0);
                $sender = trim((string)($row->customer_phone ?? ''));
                $channelKind = ($medium === 'BANK' || stripos($provider, 'BANK') !== false) ? 'bank' : ((strtolower($serviceType) === 'evd') ? 'evd' : 'ecash');
                break;

            case 'cashpickup_payout_transfer':
                $provider = reversals_normalize_provider($row->transfer_bank ?? $row->payout_channel ?? $row->transfer_channel ?? $row->source ?? '');
                $serviceType = trim((string)($row->transfer_mode ?? $row->transaction_side ?? 'Cashpickup'));
                $receiver = trim((string)($row->account_paid_to ?? $row->phone_number ?? ''));
                $amount = (float)($row->amount_paid_out ?? $row->amount_transferred ?? $row->amount_involved ?? 0);
                $sender = trim((string)($row->sender_details ?? ''));
                $mode = strtoupper(trim((string)($row->transfer_mode ?? '')));
                $channelKind = ($mode === 'BANK' || stripos($provider, 'BANK') !== false) ? 'bank' : ((strtolower($serviceType) === 'evd') ? 'evd' : 'ecash');
                break;
        }

        if ($channelKind === 'bank') {
            if (function_exists('users_manager_bank_network_to_channel_key')) {
                $channelKey = users_manager_bank_network_to_channel_key($provider);
            } else {
                $channelKey = 'ECASH_' . trim((string)preg_replace('/[^A-Z0-9]+/i', '_', strtoupper($provider)), '_');
            }
        } elseif ($channelKind === 'evd') {
            $channelKey = trim(reversals_normalize_provider($provider) . ' EVD');
        } else {
            $channelKey = reversals_normalize_provider($provider);
        }

        return [
            'provider' => $provider,
            'service_type' => $serviceType,
            'receiver' => $receiver,
            'amount' => round($amount, 2),
            'sender' => $sender,
            'channel_kind' => $channelKind,
            'channel_key' => $channelKey,
        ];
    }
}

if (!function_exists('reversals_lookup_source_transaction')) {
    function reversals_lookup_source_transaction(string $transactionType, string $transactionId): array
    {
        $transactionType = reversals_normalize_transaction_type($transactionType);
        $transactionId = trim($transactionId);

        if ($transactionType === '' || $transactionId === '') {
            return [
                'success' => false,
                'message' => 'Transaction type and transaction ID are required.',
            ];
        }

        $table = reversals_lookup_source_table($transactionType);
        if ($table === null) {
            return [
                'success' => false,
                'message' => 'Unsupported transaction type selected.',
            ];
        }

        try {
            $db = new Database;
            if (!$db->table_exists([$table])) {
                return [
                    'success' => false,
                    'message' => 'The selected transaction table is not available.',
                ];
            }

            $rows = $db->query(
                "SELECT * FROM {$table} WHERE id = :id LIMIT 1",
                ['id' => $transactionId]
            ) ?: [];

            if (empty($rows)) {
                return [
                    'success' => false,
                    'message' => 'No transaction was found for the selected type and ID.',
                ];
            }

            $row = $rows[0];
            $source = reversals_extract_source_channel($transactionType, $row);
            $dateCreated = trim((string)($row->date_created ?? $row->transaction_date ?? ''));

            return [
                'success' => true,
                'data' => [
                    'source_transaction_type' => $transactionType,
                    'source_transaction_id' => (string)$transactionId,
                    'source_table' => $table,
                    'service_provider' => $source['provider'],
                    'service_type' => $source['service_type'],
                    'receiver_details' => $source['receiver'],
                    'amount' => $source['amount'],
                    'transaction_date' => $dateCreated,
                    'source_sender_details' => $source['sender'],
                    'source_channel_kind' => $source['channel_kind'],
                    'source_channel_key' => $source['channel_key'],
                    'source_created_by' => (int)($row->created_by ?? 0),
                    'snapshot' => json_decode(json_encode($row), true),
                ],
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'Transaction lookup failed. Please try again.',
            ];
        }
    }
}

if (!function_exists('reversals_decode_json_list')) {
    function reversals_decode_json_list($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        $raw = trim((string)$raw);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('reversals_encode_json_list')) {
    function reversals_encode_json_list(array $items): string
    {
        return (string)json_encode(array_values($items), JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('reversals_remaining_amount')) {
    function reversals_remaining_amount($row): float
    {
        $amount = (float)($row->amount ?? 0);
        $totalRefunded = (float)($row->total_refunded ?? 0);
        return round(max(0, $amount - $totalRefunded), 2);
    }
}

if (!function_exists('reversals_claimable_balance')) {
    function reversals_claimable_balance($row): float
    {
        $totalRefunded = (float)($row->total_refunded ?? 0);
        $totalClaimed = (float)($row->total_claimed ?? 0);
        return round(max(0, $totalRefunded - $totalClaimed), 2);
    }
}

if (!function_exists('reversals_is_closed')) {
    function reversals_is_closed($row): bool
    {
        $status = strtolower(trim((string)($row->transaction_status ?? '')));
        if (in_array($status, ['closed', 'manager rejected', 'non-reversible'], true)) {
            return true;
        }
        return !empty($row->closed_at);
    }
}

if (!function_exists('reversals_is_tracking_status')) {
    function reversals_is_tracking_status(string $status): bool
    {
        $status = strtolower(trim($status));
        return in_array($status, [
            'proof uploaded - start tracking',
            'tracking active',
            'follow up required',
        ], true);
    }
}

if (!function_exists('reversals_is_closed_status')) {
    function reversals_is_closed_status(string $status): bool
    {
        $status = strtolower(trim($status));
        return in_array($status, [
            'closed',
            'manager rejected',
            'non-reversible',
        ], true);
    }
}

if (!function_exists('reversals_summarize_status_counts')) {
    function reversals_summarize_status_counts(array $statusCounts): array
    {
        $summary = [
            'total' => 0,
            'review' => 0,
            'tracking' => 0,
            'closed' => 0,
        ];

        foreach ($statusCounts as $status => $count) {
            $count = (int)$count;
            if ($count < 1) {
                continue;
            }

            $summary['total'] += $count;

            if (reversals_is_closed_status((string)$status)) {
                $summary['closed'] += $count;
                continue;
            }

            if (reversals_is_tracking_status((string)$status)) {
                $summary['tracking'] += $count;
                continue;
            }

            $summary['review'] += $count;
        }

        return $summary;
    }
}

if (!function_exists('reversals_append_history_item')) {
    function reversals_append_history_item($raw, array $item): string
    {
        $items = reversals_decode_json_list($raw);
        $items[] = $item;
        return reversals_encode_json_list($items);
    }
}

if (!function_exists('reversals_add_business_days')) {
    function reversals_add_business_days(string $startAt, int $days): ?string
    {
        $days = max(1, (int)$days);
        $startRaw = trim($startAt);
        if ($startRaw === '') {
            return null;
        }

        try {
            $date = new DateTimeImmutable($startRaw, reversals_timezone());
        } catch (Throwable $e) {
            return null;
        }

        $count = 0;
        $cursor = $date;
        while ($count < $days) {
            $weekday = (int)$cursor->format('N');
            if ($weekday < 6) {
                $count++;
                if ($count >= $days) {
                    break;
                }
            }
            $cursor = $cursor->modify('+1 day');
        }

        return $cursor->setTime(23, 59, 59)->format('Y-m-d H:i:s');
    }
}

if (!function_exists('reversals_followup_required')) {
    function reversals_followup_required($row): bool
    {
        if (reversals_is_closed($row)) {
            return false;
        }

        $dueAt = trim((string)($row->tracking_due_at ?? ''));
        if ($dueAt === '') {
            return false;
        }

        $latestRefundStatus = strtolower(trim((string)($row->latest_refund_status ?? '')));
        if ($latestRefundStatus === 'pending') {
            return false;
        }

        try {
            $due = new DateTimeImmutable($dueAt, reversals_timezone());
        } catch (Throwable $e) {
            return false;
        }

        return reversals_now() > $due && reversals_remaining_amount($row) > 0.009;
    }
}

if (!function_exists('reversals_tracking_days_left')) {
    function reversals_tracking_days_left($row): ?int
    {
        $dueAt = trim((string)($row->tracking_due_at ?? ''));
        if ($dueAt === '') {
            return null;
        }

        try {
            $due = new DateTimeImmutable($dueAt, reversals_timezone());
        } catch (Throwable $e) {
            return null;
        }

        $now = reversals_now();
        $diff = $due->diff($now);
        $days = (int)$diff->days;
        return $due < $now ? -1 * $days : $days;
    }
}

if (!function_exists('reversals_hydrate_row')) {
    function reversals_hydrate_row($row)
    {
        if (!is_object($row)) {
            return $row;
        }

        $row->source_snapshot = reversals_decode_json_list($row->source_snapshot_json ?? '');
        $row->tracking_history = reversals_decode_json_list($row->tracking_history_json ?? '');
        $row->refund_history = reversals_decode_json_list($row->refund_history_json ?? '');
        $row->claim_history = reversals_decode_json_list($row->claim_history_json ?? '');
        $row->remaining_amount = reversals_remaining_amount($row);
        $row->claimable_balance = reversals_claimable_balance($row);
        $row->followup_required = reversals_followup_required($row);
        $row->status_badge_class = reversals_status_badge_class($row->transaction_status ?? '');
        $row->is_closed = reversals_is_closed($row);
        $row->tracking_days_left = reversals_tracking_days_left($row);
        return $row;
    }
}

if (!function_exists('reversals_require_helper')) {
    function reversals_require_helper(string $relativePath): bool
    {
        $path = dirname(__DIR__, 2) . '/' . ltrim($relativePath, '/\\');
        if (!file_exists($path)) {
            return false;
        }

        require_once $path;
        return true;
    }
}

if (!function_exists('reversals_source_row')) {
    function reversals_source_row($row)
    {
        $table = trim((string)($row->source_table ?? ''));
        $sourceId = trim((string)($row->source_transaction_id ?? ''));
        if ($table === '' || $sourceId === '') {
            return null;
        }

        try {
            $db = new Database;
            if (!$db->table_exists([$table])) {
                return null;
            }

            $rows = $db->query("SELECT * FROM {$table} WHERE id = :id LIMIT 1", ['id' => $sourceId]) ?: [];
            return $rows[0] ?? null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('reversals_source_status_is_reversed')) {
    function reversals_source_status_is_reversed($sourceRow): bool
    {
        if (empty($sourceRow)) {
            return false;
        }

        $status = strtolower(trim((string)($sourceRow->transaction_status ?? $sourceRow->approval_status ?? '')));
        return in_array($status, ['reversed', 'declined', 'rejected', 'denied'], true);
    }
}

if (!function_exists('reversals_source_reversal_status')) {
    function reversals_source_reversal_status($row): string
    {
        return 'Reversed';
    }
}

if (!function_exists('reversals_mark_source_reversed')) {
    function reversals_mark_source_reversed($row, int $actorUserId, string $status = 'Reversed'): array
    {
        $table = trim((string)($row->source_table ?? ''));
        $sourceId = trim((string)($row->source_transaction_id ?? ''));
        if ($table === '' || $sourceId === '') {
            return ['ok' => false, 'message' => 'Source transaction details are missing.'];
        }

        try {
            $db = new Database;
            if (!$db->table_exists([$table])) {
                return ['ok' => false, 'message' => 'Source transaction table is not available.'];
            }

            $schema = $db->query("SHOW COLUMNS FROM {$table}") ?: [];
            $columns = [];
            foreach ($schema as $col) {
                $name = strtolower((string)($col->Field ?? ''));
                if ($name !== '') {
                    $columns[$name] = true;
                }
            }

            $sets = [];
            $params = ['id' => $sourceId];
            if (!empty($columns['transaction_status'])) {
                $sets[] = "transaction_status = :transaction_status";
                $params['transaction_status'] = $status;
            }
            if (!empty($columns['approval_status'])) {
                $sets[] = "approval_status = :approval_status";
                $params['approval_status'] = $status;
            }
            if (!empty($columns['rejection_reason'])) {
                $sets[] = "rejection_reason = :rejection_reason";
                $params['rejection_reason'] = 'Reversed through reversal #' . (string)($row->id ?? '');
            } elseif (!empty($columns['denial_reason'])) {
                $sets[] = "denial_reason = :denial_reason";
                $params['denial_reason'] = 'Reversed through reversal #' . (string)($row->id ?? '');
            }
            if (!empty($columns['updated_by'])) {
                $sets[] = "updated_by = :updated_by";
                $params['updated_by'] = $actorUserId;
            }
            if (!empty($columns['date_updated'])) {
                $sets[] = "date_updated = :date_updated";
                $params['date_updated'] = reversals_now()->format('Y-m-d H:i:s');
            }

            if (!empty($sets)) {
                $db->query("UPDATE {$table} SET " . implode(', ', $sets) . " WHERE id = :id LIMIT 1", $params);
                if ($db->has_error) {
                    return ['ok' => false, 'message' => 'Unable to update the source transaction status.'];
                }
            }

            return ['ok' => true, 'message' => ''];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Unable to update the source transaction status.'];
        }
    }
}

if (!function_exists('reversals_delete_related_commissions')) {
    function reversals_delete_related_commissions(int $sourceId): bool
    {
        if ($sourceId <= 0) {
            return true;
        }

        try {
            $db = new Database;
            if (!$db->table_exists(['commissions'])) {
                return true;
            }
            $db->query(
                "DELETE FROM commissions
                 WHERE description LIKE :needle_pipe
                    OR description LIKE :needle_space
                    OR description LIKE :needle_hash",
                [
                    'needle_pipe' => '%| ID ' . $sourceId . '%',
                    'needle_space' => '% ID ' . $sourceId . '%',
                    'needle_hash' => '%#' . $sourceId . '%',
                ]
            );
            return !$db->has_error;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('reversals_reverse_cashpickup_effects')) {
    function reversals_reverse_cashpickup_effects(object $transaction, string $transStatus, int $actorUserId): array
    {
        if (!reversals_require_helper('payment_services/helpers/transactions-helper.php') || !function_exists('payment_service_reverse_drawer_denos')) {
            return ['ok' => false, 'message' => 'Cashdrawer reversal tools are not available.'];
        }

        $transId = (int)($transaction->id ?? 0);
        if ($transId <= 0) {
            return ['ok' => true, 'message' => ''];
        }

        try {
            $db = new Database;
            if (!$db->table_exists(['drawer_denos'])) {
                return ['ok' => false, 'message' => 'Cashdrawer denomination table is not available.'];
            }

            $rows = $db->query(
                "SELECT drawer_id, user_id FROM drawer_denos WHERE trans_id = :trans_id ORDER BY id DESC LIMIT 1",
                ['trans_id' => $transId]
            ) ?: [];
            if (empty($rows)) {
                return ['ok' => true, 'message' => 'No cashdrawer denominations were found for this cash pickup.'];
            }

            $result = payment_service_reverse_drawer_denos(
                (int)($rows[0]->drawer_id ?? 0),
                (int)($rows[0]->user_id ?? 0),
                $transId,
                $transStatus,
                $actorUserId
            );
            if (empty($result['ok'])) {
                return ['ok' => false, 'message' => $result['message'] ?? 'Cash pickup cashdrawer reversal failed.'];
            }
            if (!reversals_delete_related_commissions($transId)) {
                return ['ok' => false, 'message' => 'Commission reversal failed for this cash pickup.'];
            }

            return ['ok' => true, 'message' => ''];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Cash pickup reversal failed.'];
        }
    }
}

if (!function_exists('reversals_reverse_source_effects')) {
    function reversals_reverse_source_effects($row, int $actorUserId): array
    {
        $sourceRow = reversals_source_row($row);
        if (empty($sourceRow)) {
            return ['ok' => false, 'message' => 'The original source transaction could not be found.'];
        }

        $sourceRow->updated_by = $actorUserId;
        $type = reversals_normalize_transaction_type((string)($row->source_transaction_type ?? ''));
        $sourceStatus = reversals_source_reversal_status($row);
        $sourceId = (int)($row->source_transaction_id ?? 0);
        $result = ['ok' => true, 'message' => ''];

        if (empty($row->source_effects_reversed_at) && !reversals_source_status_is_reversed($sourceRow)) {
            switch ($type) {
                case 'agencybanking':
                    if (!reversals_require_helper('agencybanking/helpers/cashdrawer-sync.php') || !function_exists('Agencybankings\\agency_reverse_approved_effects')) {
                        return ['ok' => false, 'message' => 'Agency banking reversal tools are not available.'];
                    }
                    $result = \Agencybankings\agency_reverse_approved_effects($sourceRow, $sourceStatus);
                    break;

                case 'airtimebundle':
                    if (!reversals_require_helper('airtimebundle/helpers/transactions-helper.php') || !function_exists('airtimebundle_reverse_sent_effects')) {
                        return ['ok' => false, 'message' => 'Airtime/bundle reversal tools are not available.'];
                    }
                    $result = airtimebundle_reverse_sent_effects($sourceRow, $sourceStatus);
                    break;

                case 'momoremit':
                    if (!reversals_require_helper('momoremit/helpers/transactions-helper.php') || !function_exists('momoremits_reverse_approval_effects')) {
                        return ['ok' => false, 'message' => 'MoMo Remit reversal tools are not available.'];
                    }
                    $result = momoremits_reverse_approval_effects($sourceRow, $sourceStatus);
                    break;

                case 'payment_services':
                    if (!reversals_require_helper('payment_services/helpers/transactions-helper.php') || !function_exists('payment_service_reverse_approved_effects')) {
                        return ['ok' => false, 'message' => 'Payment service reversal tools are not available.'];
                    }
                    $result = payment_service_reverse_approved_effects($sourceRow, $sourceStatus);
                    break;

                case 'cashpickup_payout_transfer':
                    $result = reversals_reverse_cashpickup_effects($sourceRow, $sourceStatus, $actorUserId);
                    break;

                default:
                    return ['ok' => false, 'message' => 'This source transaction type does not support automatic effect reversal yet.'];
            }

            if (empty($result['ok'])) {
                return ['ok' => false, 'message' => $result['message'] ?? 'Unable to reverse the source transaction effects.'];
            }
        }

        $statusResult = reversals_mark_source_reversed($row, $actorUserId, $sourceStatus);
        if (empty($statusResult['ok'])) {
            return $statusResult;
        }
        if (!reversals_delete_related_commissions($sourceId)) {
            return ['ok' => false, 'message' => 'Commission reversal failed for this transaction.'];
        }

        return ['ok' => true, 'message' => $result['message'] ?? ''];
    }
}

if (!function_exists('reversals_log_activity')) {
    function reversals_log_activity(string $action, int $userId, string $userName, string $description): void
    {
        try {
            $log = new \Reversals\Log;
            $log->insert([
                'table_name' => 'reversals',
                'action' => $action,
                'user_id' => $userId,
                'user_name' => $userName,
                'description' => $description,
                'date_created' => reversals_now()->format('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            return;
        }
    }
}

if (!function_exists('reversals_credit_creator_for_refund')) {
    function reversals_credit_creator_for_refund($row, float $amount): array
    {
        $userId = (int)($row->created_by ?? 0);
        if ($userId <= 0 || $amount <= 0) {
            return ['ok' => false, 'message' => 'A valid creator and refund amount are required.'];
        }

        if (!function_exists('BackOfficeCashdrawer\\getActiveDrawer') || !function_exists('BackOfficeCashdrawer\\syncEcashMovement')) {
            return ['ok' => false, 'message' => 'Cashdrawer bridge is not available for this action.'];
        }

        $drawer = \BackOfficeCashdrawer\getActiveDrawer($userId);
        if (empty($drawer)) {
            return ['ok' => false, 'message' => 'The creator must open a cashdrawer today before a refund can be acknowledged.'];
        }

        $channelKey = trim((string)($row->source_channel_key ?? ''));
        if ($channelKey === '') {
            $channelKind = strtolower(trim((string)($row->source_channel_kind ?? 'ecash')));
            $provider = reversals_normalize_provider($row->network ?? '');
            if ($channelKind === 'bank') {
                if (function_exists('users_manager_bank_network_to_channel_key')) {
                    $channelKey = users_manager_bank_network_to_channel_key($provider);
                } else {
                    $channelKey = 'ECASH_' . trim((string)preg_replace('/[^A-Z0-9]+/i', '_', strtoupper($provider)), '_');
                }
            } elseif ($channelKind === 'evd') {
                $channelKey = trim($provider . ' EVD');
            } else {
                $channelKey = $provider;
            }
        }

        if ($channelKey === '') {
            return ['ok' => false, 'message' => 'The source channel for this reversal could not be resolved.'];
        }

        $result = \BackOfficeCashdrawer\syncEcashMovement($userId, $channelKey, $amount, 'Approved', (int)($row->id ?? 0), 'in');
        if (empty($result)) {
            return ['ok' => false, 'message' => 'Failed to credit the creator cashdrawer for this refund.'];
        }

        return ['ok' => true, 'message' => ''];
    }
}

if (!function_exists('reversals_debit_creator_cash_for_claim')) {
    function reversals_debit_creator_cash_for_claim($row, string $denosJson, float $amount): array
    {
        $userId = (int)($row->created_by ?? 0);
        if ($userId <= 0 || $amount <= 0) {
            return ['ok' => false, 'message' => 'A valid claim amount is required.'];
        }

        if (!function_exists('BackOfficeCashdrawer\\normalizeDenominations') || !function_exists('BackOfficeCashdrawer\\calculateTotal') || !function_exists('BackOfficeCashdrawer\\syncCashMovementCurrency')) {
            return ['ok' => false, 'message' => 'Cashdrawer tools are not available for claim processing.'];
        }

        $denos = \BackOfficeCashdrawer\normalizeDenominations($denosJson, $amount, 'GHS');
        $total = \BackOfficeCashdrawer\calculateTotal($denos);
        if (abs($total - $amount) > 0.01) {
            return ['ok' => false, 'message' => 'Cash denominations total must match the claim amount exactly.'];
        }

        $sufficiency = \BackOfficeCashdrawer\checkCashDenominationSufficiencyCurrency($userId, $denos, $amount, 'GHS');
        if (empty($sufficiency['ok'])) {
            return ['ok' => false, 'message' => 'The creator cashdrawer balance is not enough for this claim payout.'];
        }

        $countCheck = \BackOfficeCashdrawer\checkCashDenominationCountSufficiencyCurrency($userId, $denos, $amount, 'GHS');
        if (empty($countCheck['ok'])) {
            return ['ok' => false, 'message' => 'The creator cashdrawer does not have the selected cash denominations for this claim payout.'];
        }

        $result = \BackOfficeCashdrawer\syncCashMovementCurrency($userId, $denos, $amount, 'Approved', (int)($row->id ?? 0), 'out', 'GHS');
        if (empty($result)) {
            return ['ok' => false, 'message' => 'Failed to deduct the claim payout from the creator cashdrawer.'];
        }

        return ['ok' => true, 'message' => ''];
    }
}

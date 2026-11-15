<?php

use function BackOfficeCashdrawer\calculateTotal;
use function BackOfficeCashdrawer\checkCashDenominationCountSufficiencyCurrency;
use function BackOfficeCashdrawer\checkCashDenominationSufficiencyCurrency;
use function BackOfficeCashdrawer\checkEcashChannelSufficiency;
use function BackOfficeCashdrawer\getActiveDrawer;
use function BackOfficeCashdrawer\getAssignableUsersToday;
use function BackOfficeCashdrawer\normalizeDenominations;
use function BackOfficeCashdrawer\syncCashMovementCurrency;
use function BackOfficeCashdrawer\syncEcashMovement;

date_default_timezone_set('Africa/Accra');

$session = get_session();
$userId = (int)($session->id ?? 0);
$postdata = $req->post();
$action = $postdata['action'] ?? '';

$req->upload_folder = 'uploads/backoffice/agent-services';
$maxFileSize = 5 * 1024 * 1024; // 5MB

$dbAgent = new \Core\Database;

function backoffice_agent_service_log($action, $transaction, $transactionId, $userId, $username) {
    $table = 'agent_services';
    $description = "$username $action agent service called $transaction with id $transactionId to $table";
    $log = new \BackOffices\Log;
    $log->insert([
        'table_name' => $table,
        'action' => $action,
        'user_id' => (int)$userId,
        'user_name' => $username,
        'description' => $description,
        'date_created' => date('Y-m-d H:i:s'),
    ]);
}

function backoffice_agent_service_drawer_context(int $actorId, int $serviceId, string $completedAt, int $fallbackDrawerId = 0, int $fallbackDrawerUserId = 0): array
{
    $db = new \Core\Database;
    $completedTimestamp = strtotime($completedAt);
    if ($completedTimestamp !== false && $db->table_exists(['cashdrawers'])) {
        $drawerDate = date('Y-m-d', $completedTimestamp);
        $drawers = $db->query(
            "SELECT id, user_id FROM cashdrawers
             WHERE user_id = :user_id AND DATE(date_created) = :drawer_date
             ORDER BY id DESC LIMIT 1",
            ['user_id' => $actorId, 'drawer_date' => $drawerDate]
        ) ?: [];
        if (!empty($drawers)) {
            return [
                'drawer_id' => (int)($drawers[0]->id ?? 0),
                'drawer_user_id' => (int)($drawers[0]->user_id ?? $actorId),
            ];
        }
    }

    \BackOfficeCashdrawer\loadCfaHelpers();
    $drawer = \BackOfficeCashdrawer\getActiveDrawer($actorId);
    if (!empty($drawer) && (int)($drawer['drawer_id'] ?? 0) > 0) {
        return $drawer;
    }
    if ($fallbackDrawerId > 0 && $fallbackDrawerUserId > 0) {
        return ['drawer_id' => $fallbackDrawerId, 'drawer_user_id' => $fallbackDrawerUserId];
    }

    $existing = $db->table_exists(['drawer_denos']) ? $db->query(
            "SELECT drawer_id, user_id
             FROM drawer_denos
             WHERE user_id = :user_id AND trans_id = :trans_id
             AND LOWER(TRIM(COALESCE(trans_status,''))) = 'completed'
             ORDER BY id DESC LIMIT 1",
            ['user_id' => $actorId, 'trans_id' => $serviceId]
        ) : [];
    if (!empty($existing)) {
        return [
            'drawer_id' => (int)($existing[0]->drawer_id ?? 0),
            'drawer_user_id' => (int)($existing[0]->user_id ?? $actorId),
        ];
    }

    return [];
}

function backoffice_agent_service_reverse_cash(int $actorId, array $denominations, float $amount, string $originalDirection, int $serviceId, int $fallbackDrawerId = 0, int $fallbackDrawerUserId = 0, string $completedAt = ''): void
{
    $drawer = backoffice_agent_service_drawer_context($actorId, $serviceId, $completedAt, $fallbackDrawerId, $fallbackDrawerUserId);
    $drawerId = (int)($drawer['drawer_id'] ?? 0);
    $drawerUserId = (int)($drawer['drawer_user_id'] ?? $actorId);
    if ($drawerId <= 0 || $drawerUserId <= 0) {
        throw new \RuntimeException('No cashdrawer could be found for a completed Agent Service movement.');
    }

    $payload = [];
    foreach ($denominations as $denomination) {
        $volume = abs((float)($denomination['volume'] ?? 0));
        $value = abs((float)($denomination['denomination'] ?? 0));
        $count = abs((int)($denomination['count'] ?? 0));
        if ($volume > 0 || $value > 0) {
            $payload[] = ['denomination' => $value, 'count' => $count, 'volume' => $volume];
        }
    }
    if (empty($payload) && $amount > 0) {
        $payload[] = ['denomination' => $amount, 'count' => 1, 'volume' => $amount];
    }
    if (empty($payload)) {
        return;
    }

    $db = new \Core\Database;
    if (!$db->table_exists(['drawer_denos'])) {
        throw new \RuntimeException('The cashdrawer denomination ledger is unavailable.');
    }
    $sign = strtolower($originalDirection) === 'in' ? -1 : 1;
    foreach ($payload as $denomination) {
        $db->query(
            "INSERT INTO drawer_denos (drawer_id, user_id, denomination, count, volume, date_created, created_by, trans_id, trans_status)
             VALUES (:drawer_id, :user_id, :denomination, :count, :volume, :date_created, :created_by, :trans_id, :trans_status)",
            [
                'drawer_id' => $drawerId,
                'user_id' => $drawerUserId,
                'denomination' => $denomination['denomination'],
                'count' => $sign * $denomination['count'],
                'volume' => $sign * $denomination['volume'],
                'date_created' => date('Y-m-d H:i:s'),
                'created_by' => $actorId,
                'trans_id' => $serviceId,
                'trans_status' => 'Agent Service Cancellation',
            ]
        );
        if ($db->has_error) {
            throw new \RuntimeException('Unable to write the cashdrawer reversal: ' . $db->error);
        }
    }

    if (!function_exists('Cfas\\cfa_refresh_cash_channel')) {
        throw new \RuntimeException('Cashdrawer balance refresh is unavailable.');
    }
    \Cfas\cfa_refresh_cash_channel($drawerId, $drawerUserId, 'CASH_DRAWER', $serviceId, 'Agent Service Cancellation', false, 'GHS');
}

function backoffice_agent_service_reverse_ecash(int $actorId, string $channel, float $amount, string $originalDirection, int $serviceId, string $completedAt = ''): void
{
    if ($channel === '' || $amount <= 0) {
        throw new \RuntimeException('Unable to determine an e-cash channel for the completed Agent Service.');
    }
    $drawer = backoffice_agent_service_drawer_context($actorId, $serviceId, $completedAt);
    $drawerId = (int)($drawer['drawer_id'] ?? 0);
    $drawerUserId = (int)($drawer['drawer_user_id'] ?? $actorId);
    if ($drawerId <= 0 || $drawerUserId <= 0) {
        throw new \RuntimeException('No cashdrawer could be found for the completed Agent Service e-cash movement.');
    }
    $db = new \Core\Database;
    if (!$db->table_exists(['drawer_balances'])) {
        throw new \RuntimeException('The cashdrawer balance ledger is unavailable.');
    }
    $channelKey = strtoupper(trim($channel));
    if (function_exists('BackOfficeCashdrawer\\resolveExistingEcashChannelKey')) {
        $channelKey = \BackOfficeCashdrawer\resolveExistingEcashChannelKey($drawerId, $drawerUserId, $channelKey);
    }
    if ($channelKey === '') {
        throw new \RuntimeException('The Agent Service e-cash channel could not be resolved.');
    }
    $reverseDirection = strtolower($originalDirection) === 'in' ? 'out' : 'in';
    $creditDelta = $reverseDirection === 'in' ? $amount : 0.0;
    $debitDelta = $reverseDirection === 'out' ? $amount : 0.0;
    $rows = $db->query(
        "SELECT * FROM drawer_balances
         WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash'
         AND (channel_key = :channel_key OR channel_key = :ecash_channel_key)
         LIMIT 1",
        [
            'drawer_id' => $drawerId,
            'user_id' => $drawerUserId,
            'channel_key' => $channelKey,
            'ecash_channel_key' => 'ECASH_' . $channelKey,
        ]
    ) ?: [];
    if (empty($rows)) {
        $db->query(
            "INSERT INTO drawer_balances
             (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status)
             VALUES (:drawer_id, :user_id, 'ecash', :channel_key, 0, :credit, :debit, :closing, :date_created, :trans_id, :trans_status)",
            [
                'drawer_id' => $drawerId,
                'user_id' => $drawerUserId,
                'channel_key' => $channelKey,
                'credit' => $creditDelta,
                'debit' => $debitDelta,
                'closing' => $creditDelta - $debitDelta,
                'date_created' => date('Y-m-d H:i:s'),
                'trans_id' => $serviceId,
                'trans_status' => 'Agent Service Cancellation',
            ]
        );
    } else {
        $balance = $rows[0];
        $credit = (float)($balance->credit ?? 0) + $creditDelta;
        $debit = (float)($balance->debit ?? 0) + $debitDelta;
        $db->query(
            "UPDATE drawer_balances
             SET channel_key = :channel_key, credit = :credit, debit = :debit,
                 closing_balance = :closing, trans_id = :trans_id, trans_status = :trans_status
             WHERE id = :id",
            [
                'channel_key' => $channelKey,
                'credit' => $credit,
                'debit' => $debit,
                'closing' => (float)($balance->opening_balance ?? 0) + $credit - $debit,
                'trans_id' => $serviceId,
                'trans_status' => 'Agent Service Cancellation',
                'id' => (int)($balance->id ?? 0),
            ]
        );
    }
    if ($db->has_error) {
        throw new \RuntimeException('Unable to reverse the Agent Service e-cash movement: ' . $db->error);
    }
}

if ($action === 'agent_services_cancel') {
    $id = (int)(URL(3) ?? 0);
    if (!user_can('is_admin')) {
        message_fail('Only an admin can cancel Agent Services transactions.');
        redirect($admin_route . '/' . $plugin_route . '/view_agent_service/' . $id);
        exit;
    }
    if (!csrf_verify($postdata)) {
        message_fail('Form expired. Please try again.');
        redirect($admin_route . '/' . $plugin_route . '/view_agent_service/' . $id);
        exit;
    }

    $reason = trim((string)($postdata['cancellation_reason'] ?? ''));
    if ($reason === '' || strlen($reason) > 500) {
        message_fail('Enter a cancellation reason (up to 500 characters).');
        redirect($admin_route . '/' . $plugin_route . '/view_agent_service/' . $id);
        exit;
    }
    if ($id <= 0 || !$dbAgent->beginTransaction()) {
        message_fail('Unable to prepare Agent Service cancellation.');
        redirect($admin_route . '/' . $plugin_route . '/view_agent_service/' . $id);
        exit;
    }

    try {
        $rows = $dbAgent->query("SELECT * FROM agent_services WHERE id = :id LIMIT 1 FOR UPDATE", ['id' => $id]) ?: [];
        $row = $rows[0] ?? null;
        if (!$row) {
            throw new \RuntimeException('Agent Service transaction not found.');
        }
        $lockedStatus = strtolower(trim((string)($row->status ?? '')));
        if (in_array($lockedStatus, ['cancelled', 'canceled'], true)) {
            throw new \RuntimeException('This Agent Service transaction is already cancelled.');
        }
        if (in_array($lockedStatus, ['rejected', 'declined', 'denied'], true)) {
            throw new \RuntimeException('Rejected Agent Service transactions cannot be cancelled.');
        }

        if ($lockedStatus === 'completed') {
            $amount = (float)($row->amount ?? 0);
            $creatorId = (int)($row->creator_id ?? 0);
            $assignedId = (int)($row->assigned_to ?? 0);
            $denominations = json_decode((string)($row->denominations_json ?? '[]'), true);
            $denominations = is_array($denominations) ? $denominations : [];
            $changeDenominations = json_decode((string)($row->change_denominations_json ?? '[]'), true);
            $changeDenominations = is_array($changeDenominations) ? $changeDenominations : [];
            $changeAmount = (float)($row->change_amount ?? 0);
            $cashReceived = (float)($row->cash_received ?? 0);
            $cashDrawerId = (int)($row->cashdrawer_id ?? 0);
            $cashDrawerUserId = (int)($row->drawer_user_id ?? 0);
            $completedAt = (string)($row->date_completed ?? '');
            $serviceType = trim((string)($row->service_type ?? ''));
            $customerType = trim((string)($row->customer_type ?? ''));

            if ($creatorId <= 0) {
                throw new \RuntimeException('The Agent Service creator could not be determined.');
            }

            if ($customerType === 'External' && $serviceType === 'Cash4Ecash') {
                $commission = (float)($row->commission_amount ?? 0);
                $commissionType = trim((string)($row->commission_type ?? ''));
                $targetCashIn = $commissionType === '+fee' ? $amount + $commission : $amount;
                $totalCashIn = $cashReceived > 0 ? $cashReceived : $targetCashIn;
                $totalEcashOut = $commissionType === '-fee' ? $amount - $commission : $amount;
                backoffice_agent_service_reverse_cash($creatorId, $denominations, $totalCashIn, 'in', $id, $cashDrawerId, $cashDrawerUserId, $completedAt);
                if ($changeAmount > 0) {
                    backoffice_agent_service_reverse_cash($creatorId, $changeDenominations, $changeAmount, 'out', $id, $cashDrawerId, $cashDrawerUserId, $completedAt);
                }
                backoffice_agent_service_reverse_ecash($creatorId, (string)($row->provider ?? ''), $totalEcashOut, 'out', $id, $completedAt);
            } elseif ($customerType === 'External' && $serviceType === 'Ecash4Cash') {
                $recipientId = $assignedId > 0 ? $assignedId : $creatorId;
                $cashPaid = $cashReceived > 0 ? $cashReceived : $amount;
                $channel = trim((string)($row->receiving_network ?: $row->provider ?: $row->paying_network));
                backoffice_agent_service_reverse_ecash($recipientId, $channel, $amount, 'in', $id, $completedAt);
                backoffice_agent_service_reverse_cash($creatorId, $denominations, $cashPaid, 'out', $id, $cashDrawerId, $cashDrawerUserId, $completedAt);
                if ($changeAmount > 0) {
                    backoffice_agent_service_reverse_cash($creatorId, $changeDenominations, $changeAmount, 'in', $id, $cashDrawerId, $cashDrawerUserId, $completedAt);
                }
            } elseif ($customerType === 'External' && $serviceType === 'Ecash4Ecash') {
                $recipientId = $assignedId > 0 ? $assignedId : $creatorId;
                backoffice_agent_service_reverse_ecash($creatorId, (string)($row->paying_network ?? ''), $amount, 'out', $id, $completedAt);
                backoffice_agent_service_reverse_ecash($recipientId, (string)($row->receiving_network ?? ''), $amount, 'in', $id, $completedAt);
            } elseif ($customerType === 'Internal' && $serviceType === 'Cash4Ecash') {
                $cashIn = $cashReceived > 0 ? $cashReceived : $amount;
                backoffice_agent_service_reverse_cash($creatorId, $denominations, $cashIn, 'out', $id, $cashDrawerId, $cashDrawerUserId, $completedAt);
                if ($changeAmount > 0) {
                    backoffice_agent_service_reverse_cash($creatorId, $changeDenominations, $changeAmount, 'in', $id, $cashDrawerId, $cashDrawerUserId, $completedAt);
                }
                backoffice_agent_service_reverse_ecash($creatorId, (string)($row->receiving_network ?? ''), $amount, 'in', $id, $completedAt);
                backoffice_agent_service_reverse_cash($assignedId, $denominations, $cashIn, 'in', $id, 0, 0, $completedAt);
                if ($changeAmount > 0) {
                    backoffice_agent_service_reverse_cash($assignedId, $changeDenominations, $changeAmount, 'out', $id, 0, 0, $completedAt);
                }
                backoffice_agent_service_reverse_ecash($assignedId, (string)($row->receiving_network ?? ''), $amount, 'out', $id, $completedAt);
            } elseif ($customerType === 'Internal' && $serviceType === 'Ecash4Ecash') {
                backoffice_agent_service_reverse_ecash($creatorId, (string)($row->paying_network ?? ''), $amount, 'out', $id, $completedAt);
                backoffice_agent_service_reverse_ecash($creatorId, (string)($row->receiving_network ?? ''), $amount, 'in', $id, $completedAt);
                backoffice_agent_service_reverse_ecash($assignedId, (string)($row->paying_network ?? ''), $amount, 'in', $id, $completedAt);
                backoffice_agent_service_reverse_ecash($assignedId, (string)($row->receiving_network ?? ''), $amount, 'out', $id, $completedAt);
            } elseif ($customerType === 'Internal' && $serviceType === 'Ecash4Cash') {
                $cashPaid = $cashReceived > 0 ? $cashReceived : $amount;
                backoffice_agent_service_reverse_ecash($creatorId, (string)($row->paying_network ?? ''), $amount, 'out', $id, $completedAt);
                backoffice_agent_service_reverse_cash($creatorId, $denominations, $cashPaid, 'in', $id, $cashDrawerId, $cashDrawerUserId, $completedAt);
                if ($changeAmount > 0) {
                    backoffice_agent_service_reverse_cash($creatorId, $changeDenominations, $changeAmount, 'out', $id, $cashDrawerId, $cashDrawerUserId, $completedAt);
                }
                backoffice_agent_service_reverse_ecash($assignedId, (string)($row->paying_network ?? ''), $amount, 'in', $id, $completedAt);
                backoffice_agent_service_reverse_cash($assignedId, $denominations, $cashPaid, 'out', $id, 0, 0, $completedAt);
                if ($changeAmount > 0) {
                    backoffice_agent_service_reverse_cash($assignedId, $changeDenominations, $changeAmount, 'in', $id, 0, 0, $completedAt);
                }
            } else {
                throw new \RuntimeException('The completed Agent Service type is not supported for automatic reversal.');
            }

            if ($dbAgent->table_exists(['commissions'])) {
                $dbAgent->query(
                    "UPDATE commissions
                     SET deleted = 1, date_deleted = :now
                     WHERE commission_type = 'AGENT SERVICE CHARGE'
                     AND description LIKE :description
                     AND (deleted IS NULL OR deleted = 0)",
                    [
                        'now' => date('Y-m-d H:i:s'),
                        'description' => '%| ID ' . $id,
                    ]
                );
                if ($dbAgent->has_error) {
                    throw new \RuntimeException('Unable to reverse the Agent Service commission: ' . $dbAgent->error);
                }
            }
        }

        $dbAgent->query(
            "UPDATE agent_services
             SET status = 'Cancelled', stage = 4, cancel_reason = :reason, cancelled_by = :cancelled_by,
                 date_cancelled = :now, updated_by = :updated_by, date_updated = :now
             WHERE id = :id",
            [
                'reason' => $reason,
                'cancelled_by' => $userId,
                'updated_by' => $userId,
                'now' => date('Y-m-d H:i:s'),
                'id' => $id,
            ]
        );
        if ($dbAgent->has_error || $dbAgent->affected_rows !== 1) {
            throw new \RuntimeException('Unable to mark the Agent Service transaction as cancelled.');
        }
        if (!$dbAgent->commit()) {
            throw new \RuntimeException('Unable to complete Agent Service cancellation.');
        }

        $username = trim((string)($session->first_name ?? '') . ' ' . (string)($session->last_name ?? ''));
        $transaction = trim((string)($row->service_type ?? 'agent service')) . ' | ' . trim((string)($row->customer_type ?? 'service'));
        backoffice_agent_service_log('cancelled', $transaction, $id, $userId, $username);
        message_success('Agent Service transaction cancelled and its recorded balances and commission were reversed.');
    } catch (\Throwable $e) {
        $dbAgent->rollBack();
        error_log('[agent service cancellation] transaction_id=' . $id . ' error=' . $e->getMessage());
        message_fail('Unable to cancel Agent Service transaction: ' . $e->getMessage());
    }
    redirect($admin_route . '/' . $plugin_route . '/view_agent_service/' . $id);
    exit;
}

if ($action === 'agent_services_create') {
    $ct = trim((string)($postdata['customer_type'] ?? ''));
    $st = trim((string)($postdata['service_type'] ?? ''));
    $assigned_to = (int)($postdata['assigned_to'] ?? 0);
    $amount = (float)($postdata['amount'] ?? 0);
    
    // Internal Cash4Ecash uses calculated amount
    if ($ct === 'Internal' && $st === 'Cash4Ecash' && empty($postdata['amount'])) {
        $amount = (float)($postdata['calculated_amount'] ?? 0);
    }

    $isAdminOrOps = backoffice_user_is_admin($session) || user_can('is_operations_manager');
    if ($ct === 'External') {
        if (!$isAdminOrOps && $assigned_to <= 0) {
            message_fail('External transactions must be assigned to an admin, manager, or operations manager.');
            redirect($admin_route . '/' . $plugin_route . '/make_agent_service');
            exit;
        }

        if ($assigned_to > 0) {
            $roleRows = $dbAgent->query(
                "SELECT LOWER(TRIM(ur.role)) AS role
                 FROM user_roles_map m
                 JOIN user_roles ur ON ur.id = m.role_id
                 WHERE m.disabled = 0 AND ur.disabled = 0 AND m.user_id = :uid",
                ['uid' => $assigned_to]
            ) ?: [];
            $assignedRoles = array_map(function ($row) {
                return trim((string)($row->role ?? ''));
            }, $roleRows);
            $assignedRoles = array_filter($assignedRoles, fn($role) => $role !== '');
            $allowedExternalRoles = ['admin', 'manager', 'operations manager', 'operation manager', 'operations_manager', 'operation_manager', 'operationsmanager', 'operationmanager'];
            $allowedMatch = false;
            foreach ($assignedRoles as $role) {
                if (in_array(strtolower($role), $allowedExternalRoles, true)) {
                    $allowedMatch = true;
                    break;
                }
            }
            if (!$allowedMatch && !$isAdminOrOps) {
                message_fail('Assigned user must be an admin, manager, or operations manager for external transactions.');
                redirect($admin_route . '/' . $plugin_route . '/make_agent_service');
                exit;
            }
        }
    }

    if ($ct === 'Internal' && $assigned_to <= 0) {
        message_fail('Internal transactions must be assigned to a user.');
        redirect($admin_route . '/' . $plugin_route . '/make_agent_service');
        exit;
    }

    if ($ct === 'Internal' && $assigned_to > 0) {
        $activeDrawerRow = $dbAgent->query(
            "SELECT id FROM cashdrawers WHERE user_id = :uid AND DATE(date_created) = :today LIMIT 1",
            ['uid' => $assigned_to, 'today' => date('Y-m-d')]
        );
        if (empty($activeDrawerRow)) {
            message_fail('Internal transactions can only be assigned to users with an active cashdrawer today.');
            redirect($admin_route . '/' . $plugin_route . '/make_agent_service');
            exit;
        }
    }

    if ($amount <= 0) {
        message_fail('Amount must be greater than zero.');
        redirect($admin_route . '/' . $plugin_route . '/make_agent_service');
        exit;
    }

    $creatorDrawer = getActiveDrawer($userId);
    $cashdrawerId = (int)($creatorDrawer['drawer_id'] ?? 0);
    $drawerUserId = (int)($creatorDrawer['drawer_user_id'] ?? 0);
    if ($cashdrawerId <= 0 || $drawerUserId <= 0) {
        message_fail('Open a valid cashdrawer before creating an agent service request.');
        redirect($admin_route . '/' . $plugin_route . '/make_agent_service');
        exit;
    }

    $providerValue = trim((string)($postdata['provider'] ?? ''));
    $payingNetworkValue = trim((string)($postdata['paying_network'] ?? ''));
    $receivingNetworkValue = trim((string)($postdata['receiving_network'] ?? ''));

    $bankProviderCheck = function ($value) {
        $name = strtolower(trim((string)$value));
        if ($name === '') {
            return false;
        }
        $blocked = ['fidelity', 'ecobank', 'access bank', 'gcb', 'fnb', 'fbn'];
        if (in_array($name, $blocked, true)) {
            return true;
        }
        return strpos($name, 'bank') !== false;
    };

    if (!$isAdminOrOps && $ct === 'External' && in_array($st, ['Cash4Ecash', 'Ecash4Cash'], true) && $bankProviderCheck($providerValue)) {
        message_fail('Selected service provider is not allowed for non-admin external transactions.');
        redirect($admin_route . '/' . $plugin_route . '/make_agent_service');
        exit;
    }

    if ($st === 'Ecash4Ecash' && $payingNetworkValue !== '' && $receivingNetworkValue !== '' && $payingNetworkValue === $receivingNetworkValue) {
        message_fail('For Ecash4Ecash, the sent ecash network must be different from the needed ecash network.');
        redirect($admin_route . '/' . $plugin_route . '/make_agent_service');
        exit;
    }

    $rawDenos = $postdata['denominations_json'] ?? '[]';
    $cash_received = (float)($postdata['cash_received'] ?? 0);
    $denosPayload = normalizeDenominations($rawDenos, $cash_received > 0 ? $cash_received : $amount, 'GHS');

    if ($ct === 'Internal' && $st === 'Cash4Ecash') {
        $denosTotal = calculateTotal($denosPayload);
        $expectedTotal = $cash_received > 0 ? $cash_received : $amount;
        if (abs($denosTotal - $expectedTotal) > 0.01) {
            message_fail('Calculator total must match the cash received total.');
            redirect($admin_route . '/' . $plugin_route . '/make_agent_service');
            exit;
        }
    }

    $change_amount = (float)($postdata['change_amount'] ?? 0);
    $change_amount = (float)($postdata['change_amount'] ?? 0);
    $rawChangeDenos = $postdata['change_denominations_json'] ?? '[]';
    $changeDenosPayload = normalizeDenominations($rawChangeDenos, $change_amount, 'GHS');

    $dbAgent->query(
        "INSERT INTO agent_services (
            customer_type, service_type, creator_id, assigned_to, status, stage, amount, cashdrawer_id, drawer_user_id, cash_received, change_amount, change_denominations_json, provider, paying_network, receiving_network, agent_name, agent_contact, business_name, agent_number, denominations_json, date_created
        ) VALUES (
            :ct, :st, :creator, :assigned, 'Pending', 2, :amount, :cashdrawer_id, :drawer_user_id, :cash_rec, :change_amt, :change_denos, :provider, :paying, :receiving, :name, :contact, :biz, :number, :denos, :now
        )",
        [
            'ct' => $ct, 'st' => $st, 'creator' => $userId, 'assigned' => $assigned_to, 'amount' => $amount,
            'cashdrawer_id' => $cashdrawerId, 'drawer_user_id' => $drawerUserId,
            'cash_rec' => $cash_received, 'change_amt' => $change_amount, 'change_denos' => json_encode($changeDenosPayload),
            'provider' => trim((string)($postdata['provider'] ?? '')),
            'paying' => trim((string)($postdata['paying_network'] ?? '')),
            'receiving' => trim((string)($postdata['receiving_network'] ?? '')),
            'name' => trim((string)($postdata['agent_name'] ?? '')),
            'contact' => trim((string)($postdata['agent_contact'] ?? '')),
            'biz' => trim((string)($postdata['business_name'] ?? '')),
            'number' => trim((string)($postdata['agent_number'] ?? '')),
            'denos' => json_encode($denosPayload),
            'now' => date('Y-m-d H:i:s')
        ]
    );

    $createdId = (int)($dbAgent->insert_id ?? 0);
    $txnName = trim((string)($st !== '' ? $st : 'agent service')) . ' | ' . trim((string)($ct !== '' ? $ct : 'service'));
    $username = trim((string)($session->first_name ?? '')) . ' ' . trim((string)($session->last_name ?? ''));
    backoffice_whatsapp_notify_transaction([
        'type' => 'Agent Service',
        'id' => $createdId,
        'status' => 'Pending',
        'creator_id' => $userId,
        'assigned_id' => $assigned_to,
        'details' => [
            'Customer type' => $ct,
            'Service' => $st,
            'Amount' => 'GHS ' . number_format($amount, 2),
            'Provider' => $postdata['provider'] ?? '',
        ],
        'view_url' => ROOT . '/admin/back_office/view_agent_service/' . $createdId,
    ]);
    backoffice_agent_service_log('added', $txnName, $createdId > 0 ? $createdId : 0, $userId, trim($username));

    message_success('Agent service request created successfully.');
    redirect($admin_route . '/' . $plugin_route . '#agent-services');
    exit;
}

if ($action === 'agent_services_update') {
    $id = (int)URL(3);
    $rows = $dbAgent->query("SELECT * FROM agent_services WHERE id = :id LIMIT 1", ['id' => $id]);
    if (empty($rows)) {
        message_fail('Transaction not found.');
        redirect($admin_route . '/' . $plugin_route . '#agent-services');
        exit;
    }
    $row = $rows[0];
    $statusLower = strtolower(trim((string)($row->status ?? '')));
    $stage = (int)($row->stage ?? 1);
    $isAdminOrOps = backoffice_user_is_admin($session) || user_can('is_operations_manager');
    // Allow edits for stage 1 pending (creator/admin/ops) OR stage 2 pending (creator/admin/ops)
    $canEditStage1 = ($stage === 1 && $statusLower === 'pending' && ($isAdminOrOps || $userId === (int)$row->creator_id));
    $canEditStage2 = ($stage === 2 && $statusLower === 'pending' && ($isAdminOrOps || $userId === (int)$row->creator_id));
    if (!($canEditStage1 || $canEditStage2)) {
        message_fail('Only the creator, admin, or operations manager may edit pending transactions at this stage.');
        redirect($admin_route . '/' . $plugin_route . '/view_agent_service/' . $id);
        exit;
    }

    $ct = trim((string)($postdata['customer_type'] ?? $row->customer_type ?? ''));
    $st = trim((string)($postdata['service_type'] ?? $row->service_type ?? ''));
    $amount = max(0.0, (float)($postdata['amount'] ?? $row->amount ?? 0));
    if ($amount <= 0) {
        message_fail('Amount must be greater than zero.');
        redirect($admin_route . '/' . $plugin_route . '/edit_agent_service/' . $id);
        exit;
    }

    $updateData = [
        'ct' => $ct,
        'st' => $st,
        'amount' => $amount,
        'provider' => trim((string)($postdata['provider'] ?? $row->provider ?? '')),
        'paying' => trim((string)($postdata['paying_network'] ?? $row->paying_network ?? '')),
        'receiving' => trim((string)($postdata['receiving_network'] ?? $row->receiving_network ?? '')),
        'name' => trim((string)($postdata['agent_name'] ?? $row->agent_name ?? '')),
        'contact' => trim((string)($postdata['agent_contact'] ?? $row->agent_contact ?? '')),
        'biz' => trim((string)($postdata['business_name'] ?? $row->business_name ?? '')),
        'number' => trim((string)($postdata['agent_number'] ?? $row->agent_number ?? '')),
        'updated_by' => $userId,
        'now' => date('Y-m-d H:i:s'),
        'id' => $id,
    ];

    $dbAgent->query(
        "UPDATE agent_services SET customer_type = :ct, service_type = :st, amount = :amount, provider = :provider, paying_network = :paying, receiving_network = :receiving, agent_name = :name, agent_contact = :contact, business_name = :biz, agent_number = :number, updated_by = :updated_by, date_updated = :now WHERE id = :id",
        $updateData
    );

    $username = trim((string)($session->first_name ?? '')) . ' ' . trim((string)($session->last_name ?? ''));
    backoffice_agent_service_log('updated', trim((string)($st !== '' ? $st : 'agent service')) . ' | ' . trim((string)($ct !== '' ? $ct : 'service')), $id, $userId, trim($username));

    backoffice_whatsapp_notify_creator_on_assignee_update([
        'type' => 'Agent Service',
        'id' => $id,
        'status' => $row->status ?? 'Pending',
        'creator_id' => (int)($row->creator_id ?? 0),
        'assigned_id' => (int)($row->assigned_to ?? 0),
        'details' => [
            'Customer type' => $ct,
            'Service' => $st,
            'Amount' => 'GHS ' . number_format($amount, 2),
        ],
        'view_url' => ROOT . '/admin/back_office/view_agent_service/' . $id,
    ], $userId);

    // If editing stage 2 and admin/ops supplied an assigned_to change, allow reassignment
    if ($canEditStage2 && $isAdminOrOps) {
        $newAssigned = (int)($postdata['assigned_to'] ?? 0);
        if ($newAssigned > 0 && $newAssigned !== (int)$row->assigned_to) {
            // Validate role for external transactions
            if ($ct === 'External') {
                $roleRows = $dbAgent->query(
                    "SELECT LOWER(TRIM(ur.role)) AS role
                     FROM user_roles_map m
                     JOIN user_roles ur ON ur.id = m.role_id
                     WHERE m.disabled = 0 AND ur.disabled = 0 AND m.user_id = :uid",
                    ['uid' => $newAssigned]
                ) ?: [];
                $assignedRoles = array_map(function ($r) { return trim((string)($r->role ?? '')); }, $roleRows);
                $assignedRoles = array_filter($assignedRoles, fn($role) => $role !== '');
                $allowedExternalRoles = ['admin', 'manager', 'operations manager', 'operation manager', 'operations_manager', 'operation_manager', 'operationsmanager', 'operationmanager'];
                $allowedMatch = false;
                foreach ($assignedRoles as $role) {
                    if (in_array(strtolower($role), $allowedExternalRoles, true)) { $allowedMatch = true; break; }
                }
                if (!$allowedMatch) {
                    message_fail('Assigned user must be an admin, manager, or operations manager for external transactions.');
                    redirect($admin_route . '/' . $plugin_route . '/edit_agent_service/' . $id);
                    exit;
                }
            }
            // Before assigning, ensure the new assignee has both an active cashdrawer and sufficient funds (for internal transactions)
            $needCheck = false;
            $sufficient = true;
            $assignedChannelKey = trim((string)($row->provider ?? $row->receiving_network ?? $row->paying_network ?? ''));
            if ($ct === 'Internal') {
                $activeDrawerRow = $dbAgent->query(
                    "SELECT id FROM cashdrawers WHERE user_id = :uid AND DATE(date_created) = :today LIMIT 1",
                    ['uid' => $newAssigned, 'today' => date('Y-m-d')]
                );
                if (empty($activeDrawerRow)) {
                    message_fail('Cannot assign to selected user: they do not have an active cashdrawer today.');
                    redirect($admin_route . '/' . $plugin_route . '/edit_agent_service/' . $id);
                    exit;
                }
                $needCheck = true;
                $serviceType = $row->service_type;
                if ($serviceType === 'Ecash4Cash') {
                    // Assigned must have cash
                    $assignedSnapshot = function_exists('Cfas\\cfa_get_cashdrawer_snapshot') ? \Cfas\cfa_get_cashdrawer_snapshot($newAssigned) : null;
                    $assignedCashAvailable = $assignedSnapshot ? (float)($assignedSnapshot['ghs_balance'] ?? 0.0) : null;
                    $sufficient = ($assignedCashAvailable !== null && $assignedCashAvailable >= (float)$row->amount);
                } elseif ($serviceType === 'Cash4Ecash') {
                    // Assigned must have ecash for paying_network
                    $assignedEcash = \BackOfficeCashdrawer\checkEcashChannelSufficiency($newAssigned, $row->paying_network ?? $assignedChannelKey, (float)$row->amount);
                    $sufficient = !empty($assignedEcash['ok']);
                } elseif ($serviceType === 'Ecash4Ecash') {
                    // For ecash<->ecash, check assigned has ecash on receiving_network
                    $assignedEcash = \BackOfficeCashdrawer\checkEcashChannelSufficiency($newAssigned, $row->receiving_network ?? $assignedChannelKey, (float)$row->amount);
                    $sufficient = !empty($assignedEcash['ok']);
                }
            }
            if ($needCheck && !$sufficient) {
                message_fail('Cannot assign to selected user: insufficient funds or e-cash on required channel.');
                redirect($admin_route . '/' . $plugin_route . '/edit_agent_service/' . $id);
                exit;
            }

            $dbAgent->query("UPDATE agent_services SET assigned_to = :assigned, updated_by = :updated_by, date_updated = :now WHERE id = :id", [
                'assigned' => $newAssigned, 'updated_by' => $userId, 'now' => date('Y-m-d H:i:s'), 'id' => $id
            ]);
        }
    }

    message_success('Pending agent service request updated successfully.');
    redirect($admin_route . '/' . $plugin_route . '/view_agent_service/' . $id);
    exit;
}

if ($action === 'agent_services_assign') {
    $id = (int)URL(3);
    $assigned_to = (int)($postdata['assigned_to'] ?? 0);
    if ($assigned_to > 0) {
        $dbAgent->query("UPDATE agent_services SET assigned_to = :assigned, status = 'Assigned', stage = 2, updated_by = :updated_by, date_updated = :now WHERE id = :id", [
            'assigned' => $assigned_to, 'updated_by' => $userId, 'now' => date('Y-m-d H:i:s'), 'id' => $id
        ]);
        $row = $dbAgent->query("SELECT * FROM agent_services WHERE id = :id LIMIT 1", ['id' => $id])[0] ?? null;
        $username = trim((string)($session->first_name ?? '')) . ' ' . trim((string)($session->last_name ?? ''));
        $txn = trim((string)($row->service_type ?? 'agent service')) . ' | ' . trim((string)($row->customer_type ?? 'service'));
        backoffice_agent_service_log('assigned', $txn, $id, $userId, trim($username));
        message_success('Assigned successfully.');
    }
    redirect($admin_route . '/' . $plugin_route . '/view_agent_service/' . $id);
    exit;
}

if ($action === 'agent_services_reject') {
    $id = (int)URL(3);
    $reason = trim((string)($postdata['reject_reason'] ?? ''));
    if ($reason === '') {
        message_fail('Reject reason is required before closing this transaction.');
        redirect($admin_route . '/' . $plugin_route . '/edit_agent_service/' . $id);
        exit;
    }

    $dbAgent->query("UPDATE agent_services SET status = 'Rejected', reject_reason = :reason, stage = 4, updated_by = :updated_by, date_updated = :now WHERE id = :id", [
        'reason' => $reason, 'updated_by' => $userId, 'now' => date('Y-m-d H:i:s'), 'id' => $id
    ]);
    $row = $dbAgent->query("SELECT * FROM agent_services WHERE id = :id LIMIT 1", ['id' => $id])[0] ?? null;
    $username = trim((string)($session->first_name ?? '')) . ' ' . trim((string)($session->last_name ?? ''));
    $txn = trim((string)($row->service_type ?? 'agent service')) . ' | ' . trim((string)($row->customer_type ?? 'service'));
    backoffice_agent_service_log('rejected', $txn, $id, $userId, trim($username));
    backoffice_whatsapp_notify_creator_on_assignee_update([
        'type' => 'Agent Service',
        'id' => $id,
        'status' => 'Rejected',
        'creator_id' => (int)($row->creator_id ?? 0),
        'assigned_id' => (int)($row->assigned_to ?? 0),
        'details' => ['Reason' => $reason],
        'view_url' => ROOT . '/admin/back_office/view_agent_service/' . $id,
    ], $userId);
    message_fail('Request rejected.');
    redirect($admin_route . '/' . $plugin_route . '/view_agent_service/' . $id);
    exit;
}

if ($action === 'agent_services_confirm') {
    $id = (int)URL(3);
    $rows = $dbAgent->query("SELECT * FROM agent_services WHERE id = :id", ['id' => $id]);
    if (empty($rows)) exit;
    $row = $rows[0];
    
    $updateData = ['stage' => 3, 'status' => 'Pending Creator', 'updated_by' => $userId, 'id' => $id, 'now' => date('Y-m-d H:i:s')];
    $sqlExtras = ", updated_by = :updated_by, date_updated = :now";

    // Stage 2 proof upload is allowed for internal flows and external ecash↔ecash flows.
    $proof_image = '';
    if (!empty($_FILES['proof_image']['name'])) {
        if ($row->customer_type === 'External' && !in_array($row->service_type, ['Ecash4Cash', 'Ecash4Ecash'], true)) {
            message_fail('Proof upload is not allowed during External stage 2 for this service type.');
            redirect($admin_route . '/' . $plugin_route . '/edit_agent_service/' . $id);
            exit;
        }
        $req->upload_errors = [];
        $proof_image = $req->upload_files('proof_image');
        if (empty($req->upload_errors) && !empty($proof_image)) {
            $meta = json_decode($row->metadata ?? '{}', true) ?: [];
            $proofMeta = is_array($meta['proof_images'] ?? null) ? $meta['proof_images'] : [];
            $proofMeta['stage2'] = $proof_image;
            $meta['proof_images'] = $proofMeta;
            $updateData['proof'] = $proof_image;
            $updateData['meta'] = json_encode($meta);
            $sqlExtras .= ", proof_image = :proof, metadata = :meta";
        } else {
            message_fail(implode(', ', $req->upload_errors));
            redirect($admin_route . '/' . $plugin_route . '/edit_agent_service/' . $id);
            exit;
        }
    }

    // External Cash4Ecash: assignee sets type_of_customer and commission
    if ($row->customer_type === 'External' && $row->service_type === 'Cash4Ecash') {
        $updateData['comm_amt'] = (float)($postdata['commission_amount'] ?? 0);
        $sqlExtras .= ", commission_amount = :comm_amt";
        
        $meta = json_decode($row->metadata ?? '{}', true);
        $meta['type_of_customer'] = $postdata['type_of_customer'] ?? '';
        $updateData['meta'] = json_encode($meta);
        $sqlExtras .= ", metadata = :meta";
    }

    // Save cash_received and denominations for stages that require it
    $cashReceived = (float)($postdata['cash_received'] ?? 0);
    if ($cashReceived > 0) {
        $updateData['cash_rec'] = $cashReceived;
        $sqlExtras .= ", cash_received = :cash_rec";
    }
    
    $rawDenos = $postdata['denominations_json'] ?? '[]';
    $denosPayload = normalizeDenominations($rawDenos, $cashReceived ?: (float)$row->amount, 'GHS');
    if (!empty($denosPayload)) {
        $updateData['denos'] = json_encode($denosPayload);
        $sqlExtras .= ", denominations_json = :denos";
    }

    // Save change denominations and change amount if provided
    $changeAmount = (float)($postdata['change_amount'] ?? 0);
    if ($changeAmount > 0) {
        $updateData['change_amt'] = $changeAmount;
        $sqlExtras .= ", change_amount = :change_amt";
    }
    $rawChangeDenos = $postdata['change_denominations_json'] ?? '[]';
    $changeDenosPayload = normalizeDenominations($rawChangeDenos, $changeAmount, 'GHS');
    if (!empty($changeDenosPayload)) {
        $updateData['change_denos'] = json_encode($changeDenosPayload);
        $sqlExtras .= ", change_denominations_json = :change_denos";
    }

    // Mark processor for internal transactions
    if ($row->customer_type === 'Internal') {
        $updateData['processor'] = $userId;
        $sqlExtras .= ", processor_id = :processor";
    }
    // BEFORE confirming stage 2, ensure assigned user has necessary funds for internal transactions
    $assignedId = (int)($row->assigned_to ?? 0);
    if ($row->customer_type === 'Internal' && $assignedId > 0) {
        $serviceType = $row->service_type;
        $assignedChannelKey = trim((string)($row->provider ?? $row->receiving_network ?? $row->paying_network ?? ''));
        $assignedSnapshot = function_exists('Cfas\\cfa_get_cashdrawer_snapshot') ? \Cfas\cfa_get_cashdrawer_snapshot($assignedId) : null;
        $assignedCashAvailable = $assignedSnapshot ? (float)($assignedSnapshot['ghs_balance'] ?? 0.0) : null;
        if ($serviceType === 'Ecash4Cash') {
            // Assigned must have cash to pay out
            if ($assignedCashAvailable === null || $assignedCashAvailable < (float)$row->amount) {
                message_fail('Assigned user does not have sufficient cash to process this Internal transaction.');
                redirect($admin_route . '/' . $plugin_route . '/edit_agent_service/' . $id);
                exit;
            }
        } elseif ($serviceType === 'Cash4Ecash') {
            // Assigned must have ecash on the exact receiving_network (the network they will send from)
            $assignedEcash = \BackOfficeCashdrawer\checkEcashChannelSufficiency($assignedId, trim((string)($row->receiving_network ?? $assignedChannelKey)), (float)$row->amount);
            if (empty($assignedEcash['ok'])) {
                message_fail('Assigned user does not have sufficient e-cash on the required network to process this Internal transaction.');
                redirect($admin_route . '/' . $plugin_route . '/edit_agent_service/' . $id);
                exit;
            }
        } elseif ($serviceType === 'Ecash4Ecash') {
            // Assigned must have ecash on the exact receiving_network (the network they will send from)
            $assignedEcash = \BackOfficeCashdrawer\checkEcashChannelSufficiency($assignedId, trim((string)($row->receiving_network ?? $assignedChannelKey)), (float)$row->amount);
            if (empty($assignedEcash['ok'])) {
                message_fail('Assigned user does not have sufficient e-cash on the required receiving network to process this Internal ecash↔ecash transaction.');
                redirect($admin_route . '/' . $plugin_route . '/edit_agent_service/' . $id);
                exit;
            }
        }
    }

    $dbAgent->query("UPDATE agent_services SET stage = :stage, status = :status $sqlExtras WHERE id = :id", $updateData);

    $username = trim((string)($session->first_name ?? '')) . ' ' . trim((string)($session->last_name ?? ''));
    $txn = trim((string)($row->service_type ?? 'agent service')) . ' | ' . trim((string)($row->customer_type ?? 'service'));
    backoffice_agent_service_log('confirmed', $txn, $id, $userId, trim($username));
    backoffice_whatsapp_notify_creator_on_assignee_update([
        'type' => 'Agent Service',
        'id' => $id,
        'status' => 'Pending Creator',
        'creator_id' => (int)($row->creator_id ?? 0),
        'assigned_id' => (int)($row->assigned_to ?? 0),
        'details' => [
            'Customer type' => $row->customer_type ?? '',
            'Service' => $row->service_type ?? '',
            'Amount' => 'GHS ' . number_format((float)($row->amount ?? 0), 2),
        ],
        'view_url' => ROOT . '/admin/back_office/view_agent_service/' . $id,
    ], $userId);

    message_success('Stage 2 confirmed. Waiting for creator to complete.');
    redirect($admin_route . '/' . $plugin_route . '/view_agent_service/' . $id);
    exit;
}


if ($action === 'agent_services_complete') {
    $id = (int)URL(3);
    $rows = $dbAgent->query("SELECT * FROM agent_services WHERE id = :id", ['id' => $id]);
    if (empty($rows)) exit;
    $row = $rows[0];
    if ((int)$row->stage !== 3 || $userId !== (int)$row->creator_id) {
        message_fail('Only the transaction creator may complete stage 3.');
        redirect($admin_route . '/' . $plugin_route . '/view_agent_service/' . $id);
        exit;
    }
    
    $amount = (float)$row->amount;
    $ct = $row->customer_type;
    $st = $row->service_type;
    
    $updateData = ['status' => 'Completed', 'updated_by' => $userId, 'id' => $id, 'now' => date('Y-m-d H:i:s')];
    $sqlExtras = ", updated_by = :updated_by";

    // Image Upload (Creator's side)
    if (!empty($_FILES['proof_image']['name'])) {
        $req->upload_errors = [];
        $proof_image = $req->upload_files('proof_image');
        if (empty($req->upload_errors) && !empty($proof_image)) {
            $meta = json_decode($row->metadata ?? '{}', true) ?: [];
            $proofMeta = is_array($meta['proof_images'] ?? null) ? $meta['proof_images'] : [];
            $proofMeta['stage3'] = $proof_image;
            $meta['proof_images'] = $proofMeta;
            $updateData['proof'] = $proof_image;
            $updateData['meta'] = json_encode($meta);
            $sqlExtras .= ", proof_image = :proof, metadata = :meta";
        }
    }

    if ($ct === 'External' && $st === 'Cash4Ecash') {
        $updateData['comm_type'] = $postdata['commission_type'] ?? '';
        $sqlExtras .= ", commission_type = :comm_type";
        if (!empty($postdata['agent_number'])) {
            $updateData['agent_number'] = $postdata['agent_number'];
            $sqlExtras .= ", agent_number = :agent_number";
        }
    }

    if ($ct === 'External' && $st === 'Ecash4Ecash') {
        if (!empty($postdata['agent_number'])) {
            $updateData['agent_number'] = $postdata['agent_number'];
            $sqlExtras .= ", agent_number = :agent_number";
        }
    }

    // --- CASHDRAWER UPDATES ---
    $denos = json_decode($postdata['denominations_json'] ?? $row->denominations_json ?? '[]', true);
    if (empty($denos)) {
        $denos = json_decode($row->denominations_json ?? '[]', true);
    }
    $changeDenos = json_decode($postdata['change_denominations_json'] ?? $row->change_denominations_json ?? '[]', true);
    if (empty($changeDenos)) {
        $changeDenos = json_decode($row->change_denominations_json ?? '[]', true);
    }
    $cashReceived = (float)($row->cash_received > 0 ? $row->cash_received : ($postdata['cash_received'] ?? 0));
    $changeAmount = (float)($row->change_amount > 0 ? $row->change_amount : ($postdata['change_amount'] ?? 0));

    // Persist posted denominations and change denominations (if the submitter provided them)
    if (isset($postdata['denominations_json'])) {
        $updateData['denos'] = json_encode($denos);
        $sqlExtras .= ", denominations_json = :denos";
    }
    if (isset($postdata['change_denominations_json'])) {
        $updateData['change_denos'] = json_encode($changeDenos);
        $sqlExtras .= ", change_denominations_json = :change_denos";
    }
    if (isset($postdata['cash_received'])) {
        $updateData['cash_rec'] = $cashReceived;
        $sqlExtras .= ", cash_received = :cash_rec";
    }
    if (isset($postdata['change_amount'])) {
        $updateData['change_amt'] = $changeAmount;
        $sqlExtras .= ", change_amount = :change_amt";
    }

    if ($ct === 'External' && $st === 'Cash4Ecash') {
        // Creator receives the cash and pays the ecash out.
        // Assigned user is not credited or debited for this flow.
        $comm = (float)$row->commission_amount;
        $commType = $postdata['commission_type'] ?? $row->commission_type ?? '';
        $targetCashIn = $commType === '+fee' ? $amount + $comm : $amount;
        $totalCashIn = $cashReceived > 0 ? $cashReceived : $targetCashIn;
        $totalEcashOut = $commType === '-fee' ? $amount - $comm : $amount;

        if ($cashReceived > 0) {
            if ($changeAmount > 0) {
                if (abs(($cashReceived - $changeAmount) - $targetCashIn) > 0.01) {
                    message_fail('Cash received minus change must equal the expected payable amount.');
                    redirect($admin_route . '/' . $plugin_route . '/edit_agent_service/' . $id);
                    exit;
                }
            } elseif (abs($cashReceived - $targetCashIn) > 0.01) {
                message_fail('Cash received must equal the expected payable amount or include correct change.');
                redirect($admin_route . '/' . $plugin_route . '/edit_agent_service/' . $id);
                exit;
            }
        }

        if ($changeAmount > 0) {
            $changeTotal = calculateTotal($changeDenos);
            if (abs($changeTotal - $changeAmount) > 0.01) {
                message_fail('Recorded change denominations must equal the change amount.');
                redirect($admin_route . '/' . $plugin_route . '/edit_agent_service/' . $id);
                exit;
            }
        }

        syncCashMovementCurrency($userId, $denos, $totalCashIn, 'Completed', $id, 'in', 'GHS');
        if ($changeAmount > 0 && !empty($changeDenos)) {
            syncCashMovementCurrency($userId, $changeDenos, $changeAmount, 'Completed', $id, 'out', 'GHS');
        }
        syncEcashMovement($userId, $row->provider, $totalEcashOut, 'Completed', $id, 'out');

        if ($comm > 0) {
            try {
                $commissionDb = new \Core\Database;
                $commissionInsert = [
                    'date_received' => date('Y-m-d H:i:s'),
                    'colletion_period' => 'INSTANT',
                    'network' => trim((string)($row->provider ?? '')),
                    'commission_type' => 'AGENT SERVICE CHARGE',
                    'description' => 'Agent Service Charge | ' . ($row->service_type ?? '') . ' | ID ' . $id,
                    'pay_to' => (int)($row->creator_id ?? 0),
                    'receiving_wallet' => null,
                    'amount' => $comm,
                    'transaction_status' => 'Completed',
                    'created_by' => $userId,
                    'updated_by' => null,
                    'disabled' => 0,
                    'deleted' => 0,
                    'date_created' => date('Y-m-d H:i:s'),
                    'date_updated' => null,
                    'date_deleted' => null,
                ];
                $commissionSql = "INSERT INTO commissions (" . implode(',', array_keys($commissionInsert)) . ") VALUES (:" . implode(',:', array_keys($commissionInsert)) . ")";
                $commissionDb->query($commissionSql, $commissionInsert);
            } catch (\Throwable $e) {
                error_log('AGENT SERVICE COMMISSION INSERT FAILED: ' . $e->getMessage());
            }
        }
    }
    elseif ($ct === 'External' && $st === 'Ecash4Cash') {
        // Assigned user receives the ecash when assigned; otherwise the creator receives it.
        // The creator pays the cash in both cases.
        $ecashChannel = trim((string)($row->receiving_network ?: $row->provider ?: $row->paying_network));
        $assignedUserId = (int)($row->assigned_to ?? 0);
        $ecashRecipientId = $assignedUserId > 0 ? $assignedUserId : $userId;
        $totalCashPaid = $cashReceived > 0 ? $cashReceived : $amount;

        if ($cashReceived > 0) {
            if ($changeAmount > 0) {
                if (abs(($cashReceived - $changeAmount) - $amount) > 0.01) {
                    message_fail('Cash received minus change must equal the expected payable amount.');
                    redirect($admin_route . '/' . $plugin_route . '/edit_agent_service/' . $id);
                    exit;
                }
            } elseif (abs($cashReceived - $amount) > 0.01) {
                message_fail('Cash received must equal the expected payable amount or include correct change.');
                redirect($admin_route . '/' . $plugin_route . '/edit_agent_service/' . $id);
                exit;
            }
        }

        if ($changeAmount > 0) {
            $changeTotal = calculateTotal($changeDenos);
            if (abs($changeTotal - $changeAmount) > 0.01) {
                message_fail('Recorded change denominations must equal the change amount.');
                redirect($admin_route . '/' . $plugin_route . '/edit_agent_service/' . $id);
                exit;
            }
        }

        if ($ecashChannel !== '') {
            syncEcashMovement($ecashRecipientId, $ecashChannel, $amount, 'Completed', $id, 'in');
        }

        if (!empty($denos)) {
            syncCashMovementCurrency($userId, $denos, $totalCashPaid, 'Completed', $id, 'out', 'GHS');
        } else {
            syncCashMovementCurrency($userId, [], $totalCashPaid, 'Completed', $id, 'out', 'GHS');
        }

        if ($changeAmount > 0 && !empty($changeDenos)) {
            syncCashMovementCurrency($userId, $changeDenos, $changeAmount, 'Completed', $id, 'in', 'GHS');
        }
    }
    elseif ($ct === 'External' && $st === 'Ecash4Ecash') {
        // Assigned user receives incoming ecash when assigned; otherwise the creator receives it.
        // The creator pays the outgoing ecash in both cases.
        $assignedUserId = (int)($row->assigned_to ?? 0);
        $ecashRecipientId = $assignedUserId > 0 ? $assignedUserId : $userId;
        syncEcashMovement($userId, $row->paying_network, $amount, 'Completed', $id, 'out');
        syncEcashMovement($ecashRecipientId, $row->receiving_network, $amount, 'Completed', $id, 'in');
    }
    elseif ($ct === 'Internal' && $st === 'Cash4Ecash') {
        // Creator gives physical cash to assignee and receives e-cash.
        // If change is returned, creator should receive the change back.
        $totalCashIn = $cashReceived > 0 ? $cashReceived : $amount;

        syncCashMovementCurrency($userId, $denos, $totalCashIn, 'Completed', $id, 'out', 'GHS');
        if ($changeAmount > 0 && !empty($changeDenos)) {
            syncCashMovementCurrency($userId, $changeDenos, $changeAmount, 'Completed', $id, 'in', 'GHS');
        }
        syncEcashMovement($userId, $row->receiving_network, $amount, 'Completed', $id, 'in');

        syncCashMovementCurrency($row->assigned_to, $denos, $totalCashIn, 'Completed', $id, 'in', 'GHS');
        if ($changeAmount > 0 && !empty($changeDenos)) {
            syncCashMovementCurrency($row->assigned_to, $changeDenos, $changeAmount, 'Completed', $id, 'out', 'GHS');
        }
        syncEcashMovement($row->assigned_to, $row->receiving_network, $amount, 'Completed', $id, 'out');
    }
    elseif ($ct === 'Internal' && $st === 'Ecash4Ecash') {
        // Creator Ecash OUT (sent), Creator Ecash IN (received)
        // Assignee Ecash IN (received), Assignee Ecash OUT (sent)
        syncEcashMovement($userId, $row->paying_network, $amount, 'Completed', $id, 'out');
        syncEcashMovement($userId, $row->receiving_network, $amount, 'Completed', $id, 'in');
        
        syncEcashMovement($row->assigned_to, $row->paying_network, $amount, 'Completed', $id, 'in');
        syncEcashMovement($row->assigned_to, $row->receiving_network, $amount, 'Completed', $id, 'out');
    }
    elseif ($ct === 'Internal' && $st === 'Ecash4Cash') {
        // Creator sends e-cash and receives physical cash from assignee.
        // If change is returned, creator gives change back to assignee.
        $totalCashPaid = $cashReceived > 0 ? $cashReceived : $amount;

        syncEcashMovement($userId, $row->paying_network, $amount, 'Completed', $id, 'out');
        syncCashMovementCurrency($userId, $denos, $totalCashPaid, 'Completed', $id, 'in', 'GHS');
        if ($changeAmount > 0 && !empty($changeDenos)) {
            syncCashMovementCurrency($userId, $changeDenos, $changeAmount, 'Completed', $id, 'out', 'GHS');
        }

        syncEcashMovement($row->assigned_to, $row->paying_network, $amount, 'Completed', $id, 'in');
        syncCashMovementCurrency($row->assigned_to, $denos, $totalCashPaid, 'Completed', $id, 'out', 'GHS');
        if ($changeAmount > 0 && !empty($changeDenos)) {
            syncCashMovementCurrency($row->assigned_to, $changeDenos, $changeAmount, 'Completed', $id, 'in', 'GHS');
        }
    }

    $dbAgent->query("UPDATE agent_services SET stage = 4, status = :status, date_completed = :now $sqlExtras WHERE id = :id", $updateData);

    $username = trim((string)($session->first_name ?? '')) . ' ' . trim((string)($session->last_name ?? ''));
    $txn = trim((string)($row->service_type ?? 'agent service')) . ' | ' . trim((string)($row->customer_type ?? 'service'));
    backoffice_agent_service_log('completed', $txn, $id, $userId, trim($username));

    message_success('Transaction completed successfully.');
    redirect($admin_route . '/' . $plugin_route . '/view_agent_service/' . $id);
    exit;
}

function backoffice_agent_services_assignable_users() {
    return getAssignableUsersToday();
}


$session = get_session();
$currentUserId = (int)($session->id ?? 0);
$commissionModel = new \BackOffices\Commission;

// AJAX Request processing
if (is_ajax()) {
    $params = [
        'page' => $_GET['page'] ?? 1,
        'per_page' => $_GET['per_page'] ?? 10,
        'status' => $_GET['status'] ?? '',
        'network' => $_GET['network'] ?? '',
        'search' => $_GET['search'] ?? '',
    ];

    $result = backoffice_commission_fetch_paginated($commissionModel, $params, $session);

    // Return JSON response for AJAX requests
    echo json_encode([
        'status' => 'success',
        'data' => $result['data'],
        'pagination' => $result['pagination'],
        'assignee_users' => backoffice_commission_assignable_users($currentUserId, $session, false) // Strictly exclude current user
    ]);
    exit;
}

// Initial View Load Parameters
$params = [
    'page' => $_GET['page'] ?? 1,
    'per_page' => $_GET['per_page'] ?? 10,
    'status' => $_GET['status'] ?? '',
    'network' => $_GET['network'] ?? '',
    'search' => $_GET['search'] ?? '',
];

$initialData = backoffice_commission_fetch_paginated($commissionModel, $params, $session);

// Load Assignable Users (strictly excluding current user)
$assignableUsers = backoffice_commission_assignable_users($currentUserId, $session, false);

return $this->view('agent-services', [
    'records' => $initialData['data'],
    'pagination' => $initialData['pagination'],
    'assignableUsers' => $assignableUsers,
    'currentUserId' => $currentUserId
]);

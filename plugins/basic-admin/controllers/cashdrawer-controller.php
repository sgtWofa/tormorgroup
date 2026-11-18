<?php
require_once dirname(__DIR__, 2) . '/users-manager/helpers/shared-account-assignments.php';

// Set timezone to Accra

date_default_timezone_set('Africa/Accra');



// Ensure JSON Content Type and suppress error display so JSON stays clean

header('Content-Type: application/json; charset=utf-8');

@ini_set('display_errors', '0');

@error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_STRICT & ~E_DEPRECATED);

while (ob_get_level() > 0) {
    ob_end_clean();
}

if (!function_exists('basic_admin_cashdrawer_json')) {
    function basic_admin_cashdrawer_json(array $payload): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}



// Initialize database connection for this controller

$db = new \Core\Database;



try {

    // Guard non-POST requests entirely

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

        echo json_encode(['success' => false, 'message' => 'Invalid request method.']);

        return;

    }



    // Use JSON already decoded by basic-admin/plugin.php to avoid

    // reading php://input multiple times.

    $rawData = $GLOBALS['cashdrawer_rawData'] ?? null;

    if (!is_array($rawData)) {

        echo json_encode([

            'success' => false,

            'message' => 'Invalid request payload for cash drawer. Please refresh the page and try again.',

        ]);

        return;

    }



    if (!isset($rawData['action'])) {

        echo json_encode(['success' => false, 'message' => 'No action specified.']);

        return;

    }



    $action = $rawData['action'];

    $info = ['success' => false, 'results' => []];



    if ($action === 'exchange-ecash') {

        $session = get_session();
        if (!csrf_verify(['csrf' => (string)($rawData['csrf'] ?? '')])) {
            echo json_encode(['success' => false, 'message' => 'Your session expired. Refresh the page and try again.']);
            return;
        }

        $normalizeEcashKey = function ($key) {

            $key = strtoupper(trim((string)$key));

            if ($key === '') {

                return '';

            }

            if (strpos($key, 'ECASH_') === 0) {

                $key = substr($key, 6);

            }

            $key = str_replace('_', '-', $key);

            $key = str_replace(' ', '-', $key);

            $key = trim($key, '-');

            return $key;

        };

        $drawerId = (int)($rawData['drawerId'] ?? 0);

        $fromKeyRaw = trim((string)($rawData['from_channel'] ?? ''));

        $toKeyRaw = trim((string)($rawData['to_channel'] ?? ''));

        $fromKey = $normalizeEcashKey($fromKeyRaw);

        $toKey = $normalizeEcashKey($toKeyRaw);

        $amount = round((float)($rawData['amount'] ?? 0), 2);



        if ($drawerId <= 0 || $fromKey === '' || $toKey === '' || $amount <= 0) {

            echo json_encode(['success' => false, 'message' => 'Invalid exchange payload.']);

            return;

        }

        if (strcasecmp($fromKey, $toKey) === 0) {

            echo json_encode(['success' => false, 'message' => 'Source and destination channels must be different.']);

            return;

        }



        if (!$db->table_exists(['drawer_balances', 'cashdrawers'])) {

            echo json_encode(['success' => false, 'message' => 'Cashdrawer balances not available.']);

            return;

        }



        $drawerRow = $db->query("SELECT user_id FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawerId])[0] ?? null;

        $drawerUserId = (int)($drawerRow->user_id ?? 0);

        if ($drawerUserId <= 0) {

            echo json_encode(['success' => false, 'message' => 'Cashdrawer not found.']);

            return;

        }



        $currentUserId = (int)($session->id ?? 0);

        if ($currentUserId <= 0) {

            echo json_encode(['success' => false, 'message' => 'Not authenticated.']);

            return;

        }



        $sharedRoleNames = [
            'admin',
            'is_admin',
            'manager',
            'is_manager',
            'general manager',
            'general_manager',
            'generalmanager',
            'gm',
            'is_general_manager',
            'operations manager',
            'operations_manager',
            'operationsmanager',
            'is_operations_manager',
            'isoperationsmanager',
            'op manager',
            'op_manager',
            'opmanager',
            'is_opmanager',
            'isopmanager',
            'ops manager',
            'ops_manager',
            'opsmanager',
            'is_ops_manager',
            'isopsmanager',
            'generalmanager',
            'is_general_manager',
            'isgeneralmanager',
            'administrator',
            'is_administrator',
        ];

        $sessionRolesRaw = [];
        try {
            $sessionRolesRaw = $session->roles ?? [];
        } catch (\Throwable $e) {
            $sessionRolesRaw = [];
        }
        if (!is_array($sessionRolesRaw)) {
            $sessionRolesRaw = [$sessionRolesRaw];
        }
        $sessionRolesLower = array_map(function ($role) {
            return strtolower(trim((string)$role));
        }, $sessionRolesRaw);
        $isManagerRole = false;
        foreach ($sessionRolesLower as $role) {
            if ($role === 'gm' || strpos($role, 'manager') !== false) {
                $isManagerRole = true;
                break;
            }
        }
        $canUseSharedAccounts = (user_can('is_admin') || user_can('is_manager') || user_can('is_general_manager') || user_can('is_operations_manager') || in_array('admin', $sessionRolesLower, true) || in_array('administrator', $sessionRolesLower, true) || in_array('is_administrator', $sessionRolesLower, true) || $isManagerRole);
        $isAgentExchangeUser = !$canUseSharedAccounts
            && (user_can('add_cashpickup') || in_array('agent', $sessionRolesLower, true));

        $bankAccountKeys = [
            'ADB', 'ADB_BANK',
            'ABSA', 'ABSA_BANK', 'ABSA_FORMERLY_BARCLAYS', 'ABSA_FORMERLY_BARCLAYS_BANK', 'BARCLAYS', 'BARCLAYS_BANK',
            'FIDELITY', 'FIDELITY_BANK',
            'APEX_NAFANA', 'APEX_NAFANA_BANK', 'NAFANA_RURAL_BANK', 'NAFANA_BANK',
            'APEX_SUMA', 'APEX_SUMA_BANK', 'SUMA_RURAL_BANK', 'SUMA_BANK',
            'ACCESS', 'ACCESS_BANK', 'CAL', 'CAL_BANK', 'CALBANK', 'ECOBANK', 'ECOBANK_BANK',
            'GCB', 'GCB_BANK', 'GT', 'GT_BANK', 'GTBANK', 'GTBANK_BANK', 'STANBIC', 'STANBIC_BANK',
            'FIRST_NATIONAL_BANK', 'FIRST_NATIONAL_BANK_FNB', 'FNB', 'FNB_BANK',
            'FBN', 'FBN_BANK', 'FIRST_BANK_NIGERIA',
            'FIRST_BANK_GHANA', 'FIRST_BANK_GHANA_FBG', 'FBG', 'FBG_BANK',
        ];

        $isBankChannelKey = function (string $key) use ($bankAccountKeys): bool {
            $key = strtoupper(trim($key));
            $key = preg_replace('/^ECASH[_-]*/', '', (string)$key);
            $key = preg_replace('/[^A-Z0-9]+/', '_', (string)$key);
            return in_array(trim((string)$key, '_'), $bankAccountKeys, true);
        };

        $isMomoPayKey = function (string $key): bool {
            $key = strtoupper(trim($key));
            $key = preg_replace('/^ECASH[_-]*/', '', (string)$key);
            return in_array(preg_replace('/[^A-Z0-9]/', '', (string)$key), ['MTNMOMOPAY', 'MTNMOMOPAYOUT'], true);
        };

        $isSharedAccountKey = function (string $key) use ($isBankChannelKey, $isMomoPayKey): bool {
            return $isBankChannelKey($key) || $isMomoPayKey($key);
        };

        if ($isSharedAccountKey($fromKey) || $isSharedAccountKey($toKey)) {
            $assignedSharedAccountKeys = users_manager_user_shared_account_keys($currentUserId);
            foreach ([$fromKey, $toKey] as $requestedKey) {
                if (!$isSharedAccountKey($requestedKey)) {
                    continue;
                }
                $assignedKey = users_manager_normalize_shared_account_key($requestedKey);
                if ($assignedKey === null || !in_array($assignedKey, $assignedSharedAccountKeys, true)) {
                    echo json_encode(['success' => false, 'message' => 'You are not assigned to use this shared account for channel exchange.']);
                    return;
                }
            }
        }

        $canonicalChannel = function (string $key) use ($isMomoPayKey): string {
            $key = strtoupper(trim($key));
            $key = preg_replace('/^ECASH[_-]*/', '', $key);
            $key = preg_replace('/[^A-Z0-9]+/', '_', (string)$key);
            $key = trim((string)$key, '_');
            if ($isMomoPayKey($key)) {
                return 'MTN_MOMOPAY';
            }

            $mobileAliases = [
                'MTN' => 'MTN',
                'MTN_EVD' => 'MTN_EVD',
                'TELECEL' => 'TELECEL',
                'TELECEL_EVD' => 'TELECEL_EVD',
                'AT' => 'AT',
                'AT_EVD' => 'AT_EVD',
                'AIRTELTIGO' => 'AT',
                'AIRTEL_TIGO' => 'AT',
                'GHANAPAY' => 'GHANAPAY',
                'GHANA_PAY' => 'GHANAPAY',
                'GMONEY' => 'GMONEY',
                'G_MONEY' => 'GMONEY',
            ];
            if (isset($mobileAliases[$key])) {
                return $mobileAliases[$key];
            }

            $bankAliases = [
                'ADB' => 'ADB', 'ADB_BANK' => 'ADB',
                'ABSA' => 'ABSA', 'ABSA_BANK' => 'ABSA', 'ABSA_FORMERLY_BARCLAYS' => 'ABSA',
                'ABSA_FORMERLY_BARCLAYS_BANK' => 'ABSA', 'BARCLAYS' => 'ABSA', 'BARCLAYS_BANK' => 'ABSA',
                'FIDELITY' => 'FIDELITY', 'FIDELITY_BANK' => 'FIDELITY',
                'CAL' => 'CAL', 'CAL_BANK' => 'CAL', 'CALBANK' => 'CAL',
                'ACCESS' => 'ACCESS', 'ACCESS_BANK' => 'ACCESS',
                'ECOBANK' => 'ECOBANK', 'ECOBANK_BANK' => 'ECOBANK',
                'GCB' => 'GCB', 'GCB_BANK' => 'GCB',
                'GT' => 'GTBANK', 'GT_BANK' => 'GTBANK', 'GTBANK' => 'GTBANK', 'GTBANK_BANK' => 'GTBANK',
                'STANBIC' => 'STANBIC', 'STANBIC_BANK' => 'STANBIC',
                'FNB' => 'FNB', 'FNB_BANK' => 'FNB', 'FIRST_NATIONAL_BANK' => 'FNB',
                'FIRST_NATIONAL_BANK_FNB' => 'FNB',
                'FBN' => 'FBN', 'FBN_BANK' => 'FBN', 'FIRST_BANK_NIGERIA' => 'FBN',
                'FBG' => 'FBG', 'FBG_BANK' => 'FBG', 'FIRST_BANK_GHANA' => 'FBG',
                'FIRST_BANK_GHANA_FBG' => 'FBG',
                'APEX_NAFANA' => 'APEX_NAFANA', 'APEX_NAFANA_BANK' => 'APEX_NAFANA',
                'NAFANA_RURAL_BANK' => 'APEX_NAFANA', 'NAFANA_BANK' => 'APEX_NAFANA',
                'APEX_SUMA' => 'APEX_SUMA', 'APEX_SUMA_BANK' => 'APEX_SUMA',
                'SUMA_RURAL_BANK' => 'APEX_SUMA', 'SUMA_BANK' => 'APEX_SUMA',
            ];

            return $bankAliases[$key] ?? $key;
        };

        $fromCanonical = $canonicalChannel($fromKey);
        $toCanonical = $canonicalChannel($toKey);
        if ($fromCanonical === $toCanonical) {
            echo json_encode(['success' => false, 'message' => 'Select two different supported channels.']);
            return;
        }

        $agentExchangePairs = [
            'MTN' => ['MTN_EVD'],
            'TELECEL' => ['TELECEL_EVD'],
            'AT' => ['AT_EVD'],
        ];
        $managementExchangePairs = [
            'MTN' => ['ABSA', 'STANBIC', 'FIDELITY', 'MTN_MOMOPAY', 'FBN', 'CAL', 'ECOBANK', 'FNB', 'ACCESS', 'GTBANK', 'MTN_EVD'],
            'TELECEL' => ['FIDELITY', 'TELECEL_EVD'],
            'AT' => ['FIDELITY', 'AT_EVD'],
            'STANBIC' => ['GHANAPAY'],
            'APEX_NAFANA' => ['GHANAPAY'],
            'APEX_SUMA' => ['GHANAPAY'],
            'GMONEY' => ['GCB'],
        ];
        $pairIsAllowed = static function (array $pairs, string $from, string $to): bool {
            return in_array($to, $pairs[$from] ?? [], true)
                || in_array($from, $pairs[$to] ?? [], true);
        };
        $isManagementExchangeUser = $canUseSharedAccounts;
        $exchangeAllowed = false;
        if ($isAgentExchangeUser) {
            $exchangeAllowed = $pairIsAllowed($agentExchangePairs, $fromCanonical, $toCanonical);
        } elseif ($isManagementExchangeUser) {
            $exchangeAllowed = $pairIsAllowed($managementExchangePairs, $fromCanonical, $toCanonical)
                || ($fromCanonical === 'GHANAPAY' && $isBankChannelKey($toKey))
                || ($toCanonical === 'GHANAPAY' && in_array($fromCanonical, ['STANBIC', 'APEX_NAFANA', 'APEX_SUMA'], true))
                || ($fromCanonical === 'GCB' && $isBankChannelKey($toKey) && $toCanonical !== 'GCB')
                || ($toCanonical === 'GCB' && $isBankChannelKey($fromKey) && $fromCanonical !== 'GCB');
        }
        if (!$exchangeAllowed) {
            echo json_encode(['success' => false, 'message' => 'This channel exchange is not allowed for your role.']);
            return;
        }

        $channelCandidates = function (string $key): array {
            $key = strtoupper(trim((string)$key));
            if ($key === '') {
                return [];
            }
            $key = preg_replace('/^ECASH[_-]*/', '', (string)$key);
            $key = str_replace(' ', '-', (string)$key);
            $key = trim((string)$key, '-');
            $underscore = str_replace('-', '_', $key);
            return array_values(array_unique([
                $key,
                'ECASH_' . $key,
                $underscore,
                'ECASH_' . $underscore,
            ]));
        };



        // Detect optional columns for trans grouping

        $cols = [];

        try {

            $schema = $db->query('SHOW COLUMNS FROM drawer_balances') ?: [];

            foreach ($schema as $col) {

                $cols[strtolower((string)($col->Field ?? ''))] = true;

            }

        } catch (\Throwable $e) {

            $cols = [];

        }

        $hasTransId = !empty($cols['trans_id']);

        $hasTransStatus = !empty($cols['trans_status']);

        $hasDateUpdated = !empty($cols['date_updated']);



        $now = date('Y-m-d H:i:s');

        $transId = time();

        $transStatus = trim((string)($rawData['trans_status'] ?? 'Exchange'));

        if ($transStatus === '') {

            $transStatus = 'Exchange';

        }



        $fetchLocalChannel = function (string $key) use ($db, $drawerId, $drawerUserId, $channelCandidates) {

            $candidates = $channelCandidates($key);
            if (empty($candidates)) {
                return null;
            }

            foreach ($candidates as $cand) {

                $rows = $db->query(

                    "SELECT * FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash' AND channel_key = :channel_key LIMIT 1",

                    ['drawer_id' => $drawerId, 'user_id' => $drawerUserId, 'channel_key' => $cand]

                );

                if (!empty($rows)) {

                    return $rows[0];

                }

            }

            return null;

        };

        $fetchSharedChannel = function (string $key) use ($db, $channelCandidates, $sharedRoleNames, $isSharedAccountKey) {

            if (!$isSharedAccountKey($key)) {
                return null;
            }

            $candidates = $channelCandidates($key);
            if (empty($candidates)) {
                return null;
            }

            $roleParams = [];
            $rolePlaceholders = [];
            foreach ($sharedRoleNames as $i => $roleName) {
                $param = 'role' . (string)$i;
                $roleParams[$param] = strtolower((string)$roleName);
                $rolePlaceholders[] = ':' . $param;
            }

            $candidateParams = [];
            $candidatePlaceholders = [];
            foreach ($candidates as $i => $candidate) {
                $param = 'candidate' . (string)$i;
                $candidateParams[$param] = (string)$candidate;
                $candidatePlaceholders[] = ':' . $param;
            }

            if (empty($rolePlaceholders) || empty($candidatePlaceholders)) {
                return null;
            }

            $rows = $db->query(
                "SELECT b.*
                 FROM drawer_balances AS b
                 INNER JOIN cashdrawers AS cd ON cd.id = b.drawer_id
                 INNER JOIN user_roles_map AS urm ON urm.user_id = cd.user_id AND (urm.disabled = 0 OR urm.disabled IS NULL)
                 INNER JOIN user_roles AS r ON r.id = urm.role_id AND (r.disabled = 0 OR r.disabled IS NULL)
                 WHERE b.channel_type = 'ecash'
                 AND DATE(cd.date_created) = :today
                 AND LOWER(r.role) IN (" . implode(',', $rolePlaceholders) . ")
                 AND b.channel_key IN (" . implode(',', $candidatePlaceholders) . ")
                 ORDER BY (LOWER(COALESCE(b.trans_status, '')) <> 'opening snapshot') DESC, b.id DESC
                 LIMIT 1",
                array_merge(['today' => date('Y-m-d')], $roleParams, $candidateParams)
            ) ?: [];

            return !empty($rows) ? $rows[0] : null;

        };

        $fetchChannel = function (string $key) use ($fetchLocalChannel, $fetchSharedChannel, $isSharedAccountKey) {

            if ($isSharedAccountKey($key)) {
                $shared = $fetchSharedChannel($key);
                if (!empty($shared)) {
                    return $shared;
                }
            }

            return $fetchLocalChannel($key);

        };



        $upsertChannel = function (string $key, float $creditDelta, float $debitDelta, $existingRow = null) use ($db, $drawerId, $drawerUserId, $now, $hasTransId, $hasTransStatus, $hasDateUpdated, $transId, $transStatus, $fetchChannel) {

            $rowBal = $existingRow ?: $fetchChannel($key);

            $keyToUse = $key;

            if (!empty($rowBal) && !empty($rowBal->channel_key)) {

                $keyToUse = (string)$rowBal->channel_key;

            }

            if (empty($rowBal)) {

                $opening = 0.0;

                $credit = $creditDelta;

                $debit = $debitDelta;

                $closing = $opening + $credit - $debit;

                $fields = "drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created";

                $values = ":drawer_id, :user_id, 'ecash', :channel_key, :opening, :credit, :debit, :closing, :date_created";

                $params = [

                    'drawer_id' => $drawerId,

                    'user_id' => $drawerUserId,

                    'channel_key' => $keyToUse,

                    'opening' => $opening,

                    'credit' => $credit,

                    'debit' => $debit,

                    'closing' => $closing,

                    'date_created' => $now,

                ];

                if ($hasTransId) {

                    $fields .= ", trans_id";

                    $values .= ", :trans_id";

                    $params['trans_id'] = $transId;

                }

                if ($hasTransStatus) {

                    $fields .= ", trans_status";

                    $values .= ", :trans_status";

                    $params['trans_status'] = $transStatus;

                }

                $db->query("INSERT INTO drawer_balances ($fields) VALUES ($values)", $params);

                return;

            }

            $opening = (float)($rowBal->opening_balance ?? 0);

            $credit = (float)($rowBal->credit ?? 0) + $creditDelta;

            $debit = (float)($rowBal->debit ?? 0) + $debitDelta;

            $closing = $opening + $credit - $debit;



            $set = "credit = :credit, debit = :debit, closing_balance = :closing";

            $params = [

                'credit' => $credit,

                'debit' => $debit,

                'closing' => $closing,

                'id' => (int)($rowBal->id ?? 0),

            ];

            if ($hasDateUpdated) {

                $set .= ", date_updated = :date_updated";

                $params['date_updated'] = $now;

            }

            if ($hasTransId) {

                $set .= ", trans_id = :trans_id";

                $params['trans_id'] = $transId;

            }

            if ($hasTransStatus) {

                $set .= ", trans_status = :trans_status";

                $params['trans_status'] = $transStatus;

            }



            $db->query("UPDATE drawer_balances SET $set WHERE id = :id", $params);

        };



        try {
            $fromRow = $fetchChannel($fromKey);

            $toRow = $fetchChannel($toKey);

            if (empty($fromRow)) {

                echo json_encode(['success' => false, 'message' => 'Source channel is not available for exchange.']);

                return;

            }

            $standardMobileChannels = ['MTN', 'TELECEL', 'AT', 'MTN-EVD', 'TELECEL-EVD', 'AT-EVD'];
            $isStandardMobileDestination = in_array(strtoupper($toKey), $standardMobileChannels, true);
            if (empty($toRow) && (!$isStandardMobileDestination || $isSharedAccountKey($toKey))) {
                echo json_encode(['success' => false, 'message' => 'Destination channel is not available for exchange.']);
                return;
            }

            if (!$canUseSharedAccounts) {

                $fromDrawerId = (int)($fromRow->drawer_id ?? 0);
                $fromUserId = (int)($fromRow->user_id ?? 0);

                if (($fromDrawerId !== $drawerId || $fromUserId !== $drawerUserId) && !$isSharedAccountKey($fromKey)) {

                    echo json_encode(['success' => false, 'message' => 'You can only exchange between channels in your own cashdrawer.']);

                    return;

                }
                if (!empty($toRow)) {
                    $toDrawerId = (int)($toRow->drawer_id ?? 0);
                    $toUserId = (int)($toRow->user_id ?? 0);
                    if (($toDrawerId !== $drawerId || $toUserId !== $drawerUserId) && !$isSharedAccountKey($toKey)) {
                        echo json_encode(['success' => false, 'message' => 'You can only exchange between channels in your own cashdrawer.']);
                        return;
                    }
                }

            }

            $fromClosing = 0.0;

            if (!empty($fromRow)) {

                $fromClosing = (float)($fromRow->opening_balance ?? 0) + (float)($fromRow->credit ?? 0) - (float)($fromRow->debit ?? 0);

            }

            $isMomoPayExchange = in_array($fromCanonical, ['MTN', 'MTN_MOMOPAY'], true)
                && in_array($toCanonical, ['MTN', 'MTN_MOMOPAY'], true);
            $isGcbBankExchange = ($fromCanonical === 'GCB' && $isBankChannelKey($toKey))
                || ($toCanonical === 'GCB' && $isBankChannelKey($fromKey));
            $feeApplies = $isMomoPayExchange || $isGcbBankExchange;
            $feeAmount = $feeApplies ? min(10.0, round($amount * 0.01, 2)) : 0.0;
            $totalDebit = round($amount + $feeAmount, 2);

            if ($fromClosing + 0.0001 < $totalDebit) {

                echo json_encode(['success' => false, 'message' => 'Insufficient balance in source channel for the exchange and applicable fee.']);

                return;

            }

            $remainingBalance = round($fromClosing - $totalDebit, 2);
            if ($remainingBalance < 0.01) {
                echo json_encode(['success' => false, 'message' => 'You must leave at least GHS 0.01 in the source channel; the entire balance cannot be exchanged.']);
                return;
            }

            if ($feeAmount > 0 && empty($rawData['confirm_fee'])) {
                $feeLabel = number_format($feeAmount, 2, '.', '');
                $amountLabel = number_format($amount, 2, '.', '');
                $debitLabel = number_format($totalDebit, 2, '.', '');
                echo json_encode([
                    'success' => false,
                    'confirmation_required' => true,
                    'fee_amount' => $feeAmount,
                    'total_debit' => $totalDebit,
                    'message' => "A GHS {$feeLabel} fee (1%, capped at GHS 10.00) applies. GHS {$amountLabel} will be exchanged and GHS {$debitLabel} will be deducted from the source balance. Continue?",
                ]);
                return;
            }



            $db->query('START TRANSACTION');

            $upsertChannel($fromKey, 0.0, $totalDebit, $fromRow);

            $upsertChannel($toKey, $amount, 0.0, $toRow);

            $db->query('COMMIT');



            // Track exchange in logs table so cashdrawer popup can show history
            try {

                if ($db->table_exists(['logs'])) {

                    $logCols = [];

                    try {

                        $logSchema = $db->query('SHOW COLUMNS FROM logs') ?: [];

                        foreach ($logSchema as $col) {

                            $logCols[strtolower((string)($col->Field ?? ''))] = true;

                        }

                    } catch (\Throwable $e) {

                        $logCols = [];

                    }

                    $hasUserName = !empty($logCols['user_name']);


                    $username = trim((string)(($session->first_name ?? '') . ' ' . ($session->last_name ?? '')));

                    if ($username === '') {

                        $username = trim((string)(($session->name ?? '') ?: ($session->username ?? '') ?: ($session->last_name ?? '')));

                    }

                    if ($username === '' && $currentUserId > 0 && $db->table_exists(['users'])) {

                        try {

                            $actorRow = $db->query(
                                "SELECT first_name, last_name FROM users WHERE id = :id LIMIT 1",
                                ['id' => $currentUserId]
                            )[0] ?? null;

                            $username = trim((string)(($actorRow->first_name ?? '') . ' ' . ($actorRow->last_name ?? '')));

                        } catch (\Throwable $e) {

                            $username = '';

                        }

                    }


                    $description = "Channel exchange for drawer_id: {$drawerId} from {$fromKey} to {$toKey}. Amount: " . number_format($amount, 2, '.', '');
                    if ($feeAmount > 0) {
                        $description .= '; Fee: GHS ' . number_format($feeAmount, 2, '.', '') . '; Total debited: GHS ' . number_format($totalDebit, 2, '.', '');
                    }
                    if ($username !== '') {
                        $description .= ' by "' . str_replace('"', "'", $username) . '"';
                    }

                    $fields = "id, table_name, action, user_id, description, date_created";

                    $values = ":id, :table_name, :action, :user_id, :description, :date_created";

                    $params = [

                        'id' => time(),

                        'table_name' => 'cashdrawer_channel_exchange',

                        'action' => 'exchange',

                        'user_id' => $currentUserId,

                        'description' => $description,

                        'date_created' => $now,

                    ];

                    if ($hasUserName) {

                        $fields .= ", user_name";

                        $values .= ", :user_name";

                        $params['user_name'] = $username;

                    }

                    $db->query("INSERT INTO logs ({$fields}) VALUES ({$values})", $params);

                }

            } catch (\Throwable $e) {

                // logging should not break exchange

            }



            $successMessage = $feeAmount > 0
                ? 'Exchange successful. GHS ' . number_format($feeAmount, 2, '.', '') . ' fee deducted; GHS ' . number_format($totalDebit, 2, '.', '') . ' debited.'
                : 'Exchange successful.';
            echo json_encode(['success' => true, 'message' => $successMessage]);

            return;

        } catch (\Throwable $e) {

            try {

                $db->query('ROLLBACK');

            } catch (\Throwable $rollbackError) {

            }

            echo json_encode(['success' => false, 'message' => 'Exchange failed. Please try again.']);

            return;

        }

    }



    if ($action === 'view-drawer' || $action === 'view-drawer-by-id') {

        // Prefer userId from JSON, but fall back to logged-in session id

        $session = get_session();

        $requestedDrawerId = isset($rawData['drawerId']) ? (int)$rawData['drawerId'] : 0;
        $jsonUserId = isset($rawData['userId']) ? (int)$rawData['userId'] : 0;
        $userId = $jsonUserId > 0 ? $jsonUserId : ((int)($session->id ?? 0));


        if (empty($userId)) {

            throw new Exception('No userId available for cash drawer lookup.');

        }


        // Fetch Cashdrawer Details
        $today = date('Y-m-d');
        $row = null;
        if ($requestedDrawerId > 0) {
            $query = "
                SELECT 
                    m.*, 
                    u.first_name AS user_first_name, 
                    u.last_name AS user_last_name, 
                    u.image AS user_image
                FROM cashdrawers AS m 
                LEFT JOIN users AS u ON m.user_id = u.id 
                WHERE m.id = :drawer_id
                LIMIT 1
            ";
            $row = $db->query($query, ['drawer_id' => $requestedDrawerId])[0] ?? null;
            if (!empty($row)) {
                $userId = (int)($row->user_id ?? 0);
            }
        }
        if (!$row) {
            // First, try to get today's drawer for this user (the "current" drawer)
            $query = "
                SELECT 
                    m.*, 
                    u.first_name AS user_first_name, 
                    u.last_name AS user_last_name, 
                    u.image AS user_image
                FROM cashdrawers AS m 
                LEFT JOIN users AS u ON m.user_id = u.id 
                WHERE m.user_id = :user_id
                  AND DATE(m.date_created) = :today
                ORDER BY m.id DESC
                LIMIT 1
            ";
            $row = $db->query($query, ['user_id' => $userId, 'today' => $today])[0] ?? null;
        }


        if (!$row) {
            if ($requestedDrawerId > 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Cashdrawer not found.',
                ]);
                return;
            }
            echo json_encode([
                'success' => false,
                'message' => 'No cashdrawer for today. Please open a cashdrawer to continue.',
            ]);
            return;
        }

        $drawerId = (int)($row->id ?? 0);
        $cash_channels = [];
        $ecash_channels = [];
        $cfaTotalVolume = 0.0;
        $cfaTotalCount = 0.0;
        $combinedBuy = null;
        $combinedSell = null;

        if (!empty($row)) {

            // Prefer denominations linked by drawer_id for this user.

            $drawerDate = substr($row->date_created ?? date('Y-m-d'), 0, 10);



            // Primary: this drawer's denominations

            $query = "SELECT denomination,

                             SUM(`count`)  AS count,

                             SUM(volume) AS volume

                      FROM drawer_denos

                      WHERE drawer_id = :drawer_id AND user_id = :user_id

                      GROUP BY denomination

                      HAVING ABS(COALESCE(SUM(volume),0)) > 0.009 OR ABS(COALESCE(SUM(`count`),0)) > 0

                      ORDER BY denomination DESC";



            $rows = $db->query($query, [

                'drawer_id' => $drawerId,

                'user_id'   => $row->user_id,

            ]);



            // Fallback: legacy rows scoped only by user + opening date

            if (empty($rows)) {

                $query = "SELECT denomination,

                                 SUM(`count`)  AS count,

                                 SUM(volume) AS volume

                          FROM drawer_denos

                          WHERE user_id = :user_id AND DATE(date_created) = :drawer_date

                          GROUP BY denomination

                          HAVING ABS(COALESCE(SUM(volume),0)) > 0.009 OR ABS(COALESCE(SUM(`count`),0)) > 0

                          ORDER BY denomination DESC";



                $rows = $db->query($query, [

                    'user_id'     => $row->user_id,

                    'drawer_date' => $drawerDate,

                ]);

            }



            // Totals for this drawer (physical cash snapshot)

            $query = "SELECT SUM(volume) AS total_volume, SUM(count) AS total_count

                      FROM drawer_denos

                      WHERE drawer_id = :drawer_id AND user_id = :user_id";

            $total_cashdrawer = $db->query($query, [

                'drawer_id' => $drawerId,

                'user_id'   => $row->user_id,

            ])[0] ?? null;



            // Movement breakdown (In / Out) from denominations for this drawer

            $deno_in = 0;

            $deno_out = 0;

            $query = "SELECT

                          SUM(CASE WHEN volume > 0 THEN volume ELSE 0 END) AS total_in,

                          SUM(CASE WHEN volume < 0 THEN -volume ELSE 0 END) AS total_out

                      FROM drawer_denos

                      WHERE drawer_id = :drawer_id AND user_id = :user_id";

            $mov_rows = $db->query($query, [

                'drawer_id' => $drawerId,

                'user_id'   => $row->user_id,

            ]);

            if (!empty($mov_rows)) {

                $deno_in  = (float)($mov_rows[0]->total_in  ?? 0);

                $deno_out = (float)($mov_rows[0]->total_out ?? 0);

            }



            // Fallback totals for legacy rows

            if (empty($total_cashdrawer)) {

                $query = "SELECT SUM(volume) AS total_volume, SUM(count) AS total_count

                          FROM drawer_denos

                          WHERE user_id = :user_id AND DATE(date_created) = :drawer_date";

                $total_cashdrawer = $db->query($query, [

                    'user_id'     => $row->user_id,

                    'drawer_date' => $drawerDate,

                ])[0] ?? null;

            }

            $total_volume = 0;

            $total_count = 0;

            if (!empty($total_cashdrawer)) {

                $total_volume = $total_cashdrawer->total_volume ?? 0;

                $total_count = $total_cashdrawer->total_count ?? 0;

            }

            $displayedDenoTotalVolume = 0.0;
            $displayedDenoTotalCount = 0;
            if (!empty($rows) && is_array($rows)) {
                foreach ($rows as $denoRow) {
                    $rowCount = (int)($denoRow->count ?? 0);
                    $rowVolume = (float)($denoRow->volume ?? 0);
                    if (abs($rowVolume) <= 0.009 && $rowCount !== 0) {
                        $rowVolume = (float)($denoRow->denomination ?? 0) * $rowCount;
                    }
                    $displayedDenoTotalCount += $rowCount;
                    $displayedDenoTotalVolume += $rowVolume;
                }
            }
            if ($displayedDenoTotalCount !== 0 || abs($displayedDenoTotalVolume) > 0.009) {
                $total_count = $displayedDenoTotalCount;
                $total_volume = $displayedDenoTotalVolume;
            }



            // Derive CASH DRAWER channel from opening amount and cumulative denominations movements

            $cash_channels = [];

            $ecash_channels = [];



            $openingCash = (float)($row->amount ?? 0); // initial float from cashdrawers table



            // Cumulative IN / OUT from signed drawer_denos rows, excluding opening snapshot

            $inOutQuery = "SELECT 

                SUM(CASE WHEN volume > 0 THEN volume ELSE 0 END)  AS total_positive,

                SUM(CASE WHEN volume < 0 THEN -volume ELSE 0 END) AS total_negative

                FROM drawer_denos

                WHERE drawer_id = :drawer_id AND user_id = :user_id";

            $inOutRow = $db->query($inOutQuery, [

                'drawer_id' => $row->id,

                'user_id'   => $row->user_id,

            ])[0] ?? null;

            $totalPositive = (float)($inOutRow->total_positive  ?? 0);

            $totalNegative = (float)($inOutRow->total_negative ?? 0);

            // Opening float is already represented by positive denominations equal to openingCash,

            // so we subtract it from the positive side to get true post-opening inflows.

            $cashIn  = max($totalPositive - $openingCash, 0);

            $cashOut = $totalNegative;



			$ledgerCashIn = 0.0;

			$ledgerCashOut = 0.0;



            // Also include ledger movements recorded in drawer_balances (e.g. SIM sales, exchanges)

            try {

                $ledgerDb = new \Core\Database;

                if ($ledgerDb->table_exists(['drawer_balances'])) {

                    $ledgerRow = $ledgerDb->query(

                        "SELECT SUM(credit) AS total_credit, SUM(debit) AS total_debit

                            FROM drawer_balances

                            WHERE drawer_id = :drawer_id

                              AND user_id = :user_id

                              AND channel_type = 'cash'

                              AND (trans_status IS NULL OR trans_status IN ('Approved','Sent'))",

                        [

                            'drawer_id' => $row->id,

                            'user_id'   => $row->user_id,

                        ]

                    );

                    if (!empty($ledgerRow)) {

                        $ledgerCashIn = (float)($ledgerRow[0]->total_credit ?? 0);

                        $ledgerCashOut = (float)($ledgerRow[0]->total_debit ?? 0);

                    }

                }

            } catch (\Throwable $e) {

                // fail silently; drawer summary still works without ledger rows

            }



            // Closing is opening plus cumulative in minus cumulative out

            $closingCash   = $openingCash + $cashIn - $cashOut;

            $headerBalance = $closingCash; // header Balance follows ledger closing



            // Optional CFA FX snapshot for this drawer

            $cfaVolume = 0.0;

            $cfaDenominations = [];

            $cfaTotalVolume = 0.0;

            $cfaTotalCount  = 0.0;

            try {

                if ($db->table_exists(['drawer_fx_denos'])) {

                    // Total CFA volume

                    $cfaRows = $db->query(

                        "SELECT SUM(volume) AS total_volume

                           FROM drawer_fx_denos

                           WHERE drawer_id = :drawer_id AND user_id = :user_id AND currency = 'CFA'",

                        [

                            'drawer_id' => $drawerId,

                            'user_id'   => $row->user_id,

                        ]

                    );

                    if (!empty($cfaRows)) {

                        $cfaVolume = (float)($cfaRows[0]->total_volume ?? 0);

                    }

                    // Per-denomination CFA breakdown

                    $cfaDenominations = $db->query(

                        "SELECT denomination,

                                SUM(`count`)  AS count,

                                SUM(volume) AS volume

                           FROM drawer_fx_denos

                           WHERE drawer_id = :drawer_id AND user_id = :user_id AND currency = 'CFA'

                           GROUP BY denomination

                           HAVING ABS(COALESCE(SUM(volume),0)) > 0.009 OR ABS(COALESCE(SUM(`count`),0)) > 0

                           ORDER BY denomination DESC",

                        [

                            'drawer_id' => $drawerId,

                            'user_id'   => $row->user_id,

                        ]

                    );

                    if (!empty($cfaDenominations)) {

                        foreach ($cfaDenominations as $fxRow) {

                            $cfaTotalVolume += (float)($fxRow->volume ?? 0);

                            $cfaTotalCount  += (float)($fxRow->count  ?? 0);

                        }

                    }

                }

            } catch (\Throwable $e) {

                // ignore FX snapshot failures; core drawer summary still works

            }



            // Load CFA buying/selling rates from settings if available

            $combinedBuy = null;

            $combinedSell = null;

            $cfaInGhsBuy = 0.0;

            $cfaInGhsSell = 0.0;

            try {

                if (class_exists('\\Settings\\Setting') && $cfaVolume != 0.0) {

                    $settingsModel = new \Settings\Setting;

                    $settingsRow   = $settingsModel->first(['id' => 1]);

                    $buyRate  = (float)($settingsRow->buying_rate  ?? 0);

                    $sellRate = (float)($settingsRow->selling_rate ?? 0);

                    if ($buyRate > 0 || $sellRate > 0) {

                        $cfaInThousands = $cfaVolume / 1000.0;

                        if ($buyRate > 0) {

                            $cfaInGhsBuy = $cfaInThousands * $buyRate;

                            $combinedBuy = $closingCash + $cfaInGhsBuy;

                        }

                        if ($sellRate > 0) {

                            $cfaInGhsSell = $cfaInThousands * $sellRate;

                            $combinedSell = $closingCash + $cfaInGhsSell;

                        }

                    }

                }

            } catch (\Throwable $e) {

                // ignore settings failures; core drawer summary still works

            }



            $cash_channels[] = (object) [

                'channel_type'    => 'cash',

                'channel_key'     => 'CASH_DRAWER',

                'opening_balance' => $openingCash,

                'credit'          => $cashIn,

                'debit'           => $cashOut,

                'closing_balance' => $closingCash,

                'trans_status'    => 'Sent',

            ];



            // Optional CASH_CFA cash channel from drawer_balances, mirroring main view (any status)

            try {

                $fxCashDb = new \Core\Database;

                if ($fxCashDb->table_exists(['drawer_balances'])) {

                    $fxCashRows = $db->query(

                                                "SELECT channel_type, channel_key, opening_balance, credit, debit, closing_balance, trans_status

                                                     FROM drawer_balances

                                                     WHERE drawer_id = :drawer_id

                                                         AND user_id   = :user_id

                                                         AND channel_type = 'cash'

                                                         AND channel_key  = 'CASH_CFA'

                                                     ORDER BY id DESC

                                                     LIMIT 1",

                        [

                            'drawer_id' => $row->id,

                            'user_id'   => $row->user_id,

                        ]

                    );

                    if (!empty($fxCashRows)) {

                        foreach ($fxCashRows as $fxCash) {

                            $openingFx = (float)($fxCash->opening_balance ?? 0);

                            $creditFx  = (float)($fxCash->credit ?? 0);

                            $debitFx   = (float)($fxCash->debit ?? 0);

                            $closingFx = (float)($fxCash->closing_balance ?? ($openingFx + $creditFx - $debitFx));

                            $fxCash->opening_balance = $openingFx;

                            $fxCash->credit          = $creditFx;

                            $fxCash->debit           = $debitFx;

                            $fxCash->closing_balance = $closingFx;

                            if (empty($fxCash->trans_status)) {

                                $fxCash->trans_status = 'Sent';

                            }

                            $cash_channels[] = $fxCash;

                        }

                    }

                }

            } catch (\Throwable $e) {

                // ignore FX cash channel failures

            }



            // Ecash channels still come from drawer_balances (all statuses, like main view)

            try {

				$canSeeBankChannels = false;
				$viewerUserId = 0;
				$sharedRoleNames = [
					'admin',
					'is_admin',
					'manager',
					'is_manager',
					'general manager',
					'general_manager',
					'generalmanager',
					'gm',
					'is_general_manager',
					'operations manager',
					'operations_manager',
					'operationsmanager',
					'op manager',
					'op_manager',
				];
				try {
					$session = get_session();
					$viewerUserId = (int)($session->id ?? 0);
					$rolesRaw = $session->roles ?? [];
					if (!is_array($rolesRaw)) {
						$rolesRaw = [$rolesRaw];
					}
					$rolesLower = array_map(function ($r) {
						return strtolower(trim((string)$r));
					}, $rolesRaw);
					$isManagerRole = false;
					foreach ($rolesLower as $role) {
						if ($role === 'gm' || strpos($role, 'manager') !== false) {
							$isManagerRole = true;
							break;
						}
					}
					$canSeeBankChannels = (user_can('is_admin') || user_can('is_manager') || user_can('is_general_manager') || user_can('is_operations_manager') || $isManagerRole);
				} catch (\Throwable $e) {
					$canSeeBankChannels = user_can('is_admin');
				}
				$assignedSharedAccountKeys = users_manager_user_shared_account_keys($viewerUserId);
				$bankChannelKeys = [
					'ADB', 'ADB_BANK',
					'ABSA', 'ABSA_BANK', 'ABSA_FORMERLY_BARCLAYS', 'ABSA_FORMERLY_BARCLAYS_BANK', 'BARCLAYS', 'BARCLAYS_BANK',
					'FIDELITY', 'FIDELITY_BANK',
					'APEX_NAFANA', 'APEX_NAFANA_BANK', 'APEX_SUMA', 'APEX_SUMA_BANK',
					'ACCESS', 'ACCESS_BANK', 'CAL', 'CAL_BANK', 'ECOBANK',
					'GCB', 'GCB_BANK', 'GT', 'GT_BANK', 'STANBIC', 'STANBIC_BANK',
					'FIRST_NATIONAL_BANK', 'FIRST_NATIONAL_BANK_FNB', 'FNB', 'FNB_BANK',
					'FIRST_BANK_GHANA', 'FIRST_BANK_GHANA_FBG', 'FBG', 'FBG_BANK',
				];
				$isBankKey = function (string $key) use ($bankChannelKeys): bool {
					$key = strtoupper(trim($key));
					if ($key === '') {
						return false;
					}
					$key = preg_replace('/^ECASH[_-]*/', '', $key);
					$key = preg_replace('/[^A-Z0-9]+/', '_', (string)$key);
					return in_array(trim((string)$key, '_'), $bankChannelKeys, true);
				};

                $db_channels = new \Core\Database;

                if ($db_channels->table_exists(['drawer_balances'])) {

                    $bal_query = "SELECT b.channel_type, b.channel_key, b.opening_balance, b.credit, b.debit, b.closing_balance, b.trans_status
                                   FROM drawer_balances b
                                   INNER JOIN (
                                       SELECT channel_key, MAX(id) AS max_id
                                       FROM drawer_balances
                                       WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash'
                                       GROUP BY channel_key
                                   ) last_row ON last_row.max_id = b.id";

                    $bal_rows = $db->query($bal_query, [

                        'drawer_id' => $row->id,

                        'user_id'   => $row->user_id,

                    ]);

					$normalizeChannelKey = function ($key): string {
						$channelKeyRaw = strtoupper(trim((string)$key));
						$channelKeyRaw = preg_replace('/^ECASH[_-]*/', '', (string)$channelKeyRaw);
						$channelKeyRaw = str_replace(['_', ' '], '-', (string)$channelKeyRaw);
						$channelKeyRaw = preg_replace('/-+/', '-', (string)$channelKeyRaw);
						return trim((string)$channelKeyRaw, '-');
					};
					$isMoMoPayKey = function (string $key): bool {
						$key = strtoupper(trim($key));
						$key = preg_replace('/^ECASH[_-]*/', '', $key);
						$compact = preg_replace('/[^A-Z0-9]/', '', (string)$key);
						return in_array($compact, ['MTNMOMOPAY', 'MTNMOMOPAYOUT'], true);
					};
					$isSharedChannelKey = function (string $key) use ($isBankKey, $isMoMoPayKey): bool {
						return $isBankKey($key) || $isMoMoPayKey($key);
					};
					$finalizeBalance = function ($bal, bool $isShared = false) use ($isMoMoPayKey) {
						$opening = (float)($bal->opening_balance ?? 0);
						$credit  = (float)($bal->credit ?? 0);
						$debit   = (float)($bal->debit ?? 0);
						$bal->closing_balance = $opening + $credit - $debit;
						if ($isMoMoPayKey((string)($bal->channel_key ?? ''))) {
							$bal->channel_key = 'MTN_MOMOPAY';
						}
						if (empty($bal->trans_status)) {
							$bal->trans_status = 'Sent';
						}
						$bal->is_shared_account = $isShared;
						return $bal;
					};

				$localSharedChannels = [];
				$localNonSharedChannels = [];
                    if (!empty($bal_rows)) {
                        foreach ($bal_rows as $bal) {
							$channelKeyRaw = $normalizeChannelKey((string)($bal->channel_key ?? ''));
							if (in_array($channelKeyRaw, ['EZWICH', 'E-ZWICH', 'CASH-DRAWER', 'CASH-CFA'], true)) {
								continue;
							}
								$isShared = $isSharedChannelKey($channelKeyRaw);
								if ($isShared) {
									if ($canSeeBankChannels || $isMoMoPayKey($channelKeyRaw)) {
										$localSharedChannels[] = $finalizeBalance($bal, true);
								}
								continue;
							}
								$localNonSharedChannels[] = $finalizeBalance($bal);
                        }
                    }
				$ecash_channels = $localNonSharedChannels;

					{
							$sharedChannels = [];
						try {
							$roleParams = [];
							$rolePlaceholders = [];
							foreach ($sharedRoleNames as $i => $roleName) {
								$key = 'role' . (string)$i;
								$roleParams[$key] = strtolower((string)$roleName);
								$rolePlaceholders[] = ':' . $key;
							}
							if (!empty($rolePlaceholders)) {
								$sharedSql = "SELECT b.channel_type, b.channel_key, b.opening_balance, b.credit, b.debit, b.closing_balance, b.trans_status
										FROM drawer_balances AS b
										INNER JOIN (
											SELECT db.channel_key,
												COALESCE(
													MAX(CASE WHEN LOWER(COALESCE(db.trans_status, '')) <> 'opening snapshot' THEN db.id END),
													MAX(db.id)
												) AS max_id
											FROM drawer_balances AS db
											INNER JOIN cashdrawers AS cd ON cd.id = db.drawer_id
											LEFT JOIN user_roles_map AS urm ON urm.user_id = cd.user_id AND (urm.disabled = 0 OR urm.disabled IS NULL)
											LEFT JOIN user_roles AS r ON r.id = urm.role_id AND (r.disabled = 0 OR r.disabled IS NULL)
											WHERE db.channel_type = 'ecash'
											  AND DATE(cd.date_created) = :today
											  AND (
												LOWER(r.role) IN (" . implode(',', $rolePlaceholders) . ")
												OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(db.channel_key)), 'ECASH_', ''), 'ECASH-', ''), '_', ''), '-', ''), ' ', '') IN ('MTNMOMOPAY', 'MTNMOMOPAYOUT')
											  )
											GROUP BY db.channel_key
										) last_row ON last_row.max_id = b.id";
								$sharedRows = $db->query($sharedSql, array_merge(['today' => $today], $roleParams)) ?: [];
								foreach ($sharedRows as $bal) {
									$channelKeyRaw = $normalizeChannelKey((string)($bal->channel_key ?? ''));
									if (in_array($channelKeyRaw, ['EZWICH', 'E-ZWICH', 'CASH-DRAWER', 'CASH-CFA'], true)) {
										continue;
									}
									if (!$canSeeBankChannels && !$isMoMoPayKey($channelKeyRaw)) {
										continue;
									}
									$finalized = $finalizeBalance($bal, true);
									if ($isSharedChannelKey($channelKeyRaw)) {
										$sharedChannels[] = $finalized;
									}
								}
							}
						} catch (\Throwable $e) {
							$sharedChannels = [];
						}

						if (!empty($sharedChannels)) {
							$ecash_channels = array_merge($ecash_channels, $sharedChannels);
						} elseif (!empty($localSharedChannels)) {
							$ecash_channels = array_merge($ecash_channels, $localSharedChannels);
						}
					}

                    $uniqueEcashChannels = [];
                    foreach ($ecash_channels as $channel) {
                        $channelKey = $normalizeChannelKey((string)($channel->channel_key ?? ''));
                        if ($channelKey === ''
                            || in_array($channelKey, ['CASH-DRAWER', 'CASH-CFA'], true)
                            || isset($uniqueEcashChannels[$channelKey])) {
                            continue;
                        }
                        $uniqueEcashChannels[$channelKey] = $channel;
                    }
                    $ecash_channels = array_values($uniqueEcashChannels);

                }

            } catch (\Throwable $e) {

                // fail silently; popup still shows core cash drawer

            }

        }



        if (!$row) {

            echo json_encode(['success' => false, 'message' => 'Cashdrawer not found. Contact operations to create one for you.']);

            return;

        }



        // Denomination movement history for this drawer (for popup History modal)

        $deno_history = [];

        try {

            $histDb = new \Core\Database;

            $ghsRows = [];

            $cfaRows = [];

            if ($histDb->table_exists(['drawer_denos'])) {

                $ddCols = [];

                try {

                    $ddSchema = $histDb->query("SHOW COLUMNS FROM drawer_denos") ?: [];

                    foreach ($ddSchema as $col) {

                        $ddCols[strtolower((string)($col->Field ?? ''))] = true;

                    }

                } catch (\Throwable $e) {

                    $ddCols = [];

                }

                $ddHasTransId = !empty($ddCols['trans_id']);

                $ddHasTransStatus = !empty($ddCols['trans_status']);
                $ddHasCreatedBy = !empty($ddCols['created_by']);
                $ddHasDrawerId = !empty($ddCols['drawer_id']);
                $ddHasUserId = !empty($ddCols['user_id']);

                $ddSelect = "SELECT " . ($ddHasTransId ? "d.trans_id" : "0 AS trans_id") . ", d.date_created, 'GHS' AS currency, d.denomination, d.count, d.volume, " . ($ddHasTransStatus ? "d.trans_status" : "NULL AS trans_status") . ",
                            " . ($ddHasCreatedBy ? "TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')))" : "''") . " AS actor_name
                       FROM drawer_denos AS d
                       " . ($ddHasCreatedBy ? "LEFT JOIN users AS u ON u.id = d.created_by" : "") . "
                       WHERE ";
                $ddOrder = " ORDER BY d.date_created DESC, trans_id DESC, d.denomination DESC";
                if ($ddHasDrawerId) {
                    $ddWhere = "d.drawer_id = :drawer_id" . ($ddHasUserId ? " AND d.user_id = :user_id" : "");
                    $ddParams = ['drawer_id' => $drawerId];
                    if ($ddHasUserId) {
                        $ddParams['user_id'] = $row->user_id;
                    }
                    $ghsRows = $histDb->query($ddSelect . $ddWhere . $ddOrder, $ddParams) ?: [];
                    if (empty($ghsRows) && $ddHasUserId) {
                        $ghsRows = $histDb->query($ddSelect . "d.drawer_id = :drawer_id" . $ddOrder, ['drawer_id' => $drawerId]) ?: [];
                    }
                }
                if (empty($ghsRows) && $ddHasUserId) {
                    $ghsRows = $histDb->query($ddSelect . "d.user_id = :user_id AND DATE(d.date_created) = :drawer_date" . $ddOrder, [
                        'user_id' => $row->user_id,
                        'drawer_date' => substr((string)($row->date_created ?? date('Y-m-d')), 0, 10),
                    ]) ?: [];
                }

            }

            if ($histDb->table_exists(['drawer_fx_denos'])) {

                $fxCols = [];

                try {

                    $fxSchema = $histDb->query("SHOW COLUMNS FROM drawer_fx_denos") ?: [];

                    foreach ($fxSchema as $col) {

                        $fxCols[strtolower((string)($col->Field ?? ''))] = true;

                    }

                } catch (\Throwable $e) {

                    $fxCols = [];

                }

                $fxHasTransId = !empty($fxCols['trans_id']);

                $fxHasTransStatus = !empty($fxCols['trans_status']);
                $fxHasCreatedBy = !empty($fxCols['created_by']);
                $fxHasDrawerId = !empty($fxCols['drawer_id']);
                $fxHasUserId = !empty($fxCols['user_id']);

                $fxSelect = "SELECT " . ($fxHasTransId ? "f.trans_id" : "0 AS trans_id") . ", f.date_created, 'CFA' AS currency, f.denomination, f.count, f.volume, " . ($fxHasTransStatus ? "f.trans_status" : "NULL AS trans_status") . ",
                            " . ($fxHasCreatedBy ? "TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')))" : "''") . " AS actor_name
                       FROM drawer_fx_denos AS f
                       " . ($fxHasCreatedBy ? "LEFT JOIN users AS u ON u.id = f.created_by" : "") . "
                       WHERE ";
                $fxOrder = " ORDER BY f.date_created DESC, trans_id DESC, f.denomination DESC";
                if ($fxHasDrawerId) {
                    $fxWhere = "f.drawer_id = :drawer_id" . ($fxHasUserId ? " AND f.user_id = :user_id" : "");
                    $fxParams = ['drawer_id' => $drawerId];
                    if ($fxHasUserId) {
                        $fxParams['user_id'] = $row->user_id;
                    }
                    $cfaRows = $histDb->query($fxSelect . $fxWhere . $fxOrder, $fxParams) ?: [];
                    if (empty($cfaRows) && $fxHasUserId) {
                        $cfaRows = $histDb->query($fxSelect . "f.drawer_id = :drawer_id" . $fxOrder, ['drawer_id' => $drawerId]) ?: [];
                    }
                }
                if (empty($cfaRows) && $fxHasUserId) {
                    $cfaRows = $histDb->query($fxSelect . "f.user_id = :user_id AND DATE(f.date_created) = :drawer_date" . $fxOrder, [
                        'user_id' => $row->user_id,
                        'drawer_date' => substr((string)($row->date_created ?? date('Y-m-d')), 0, 10),
                    ]) ?: [];
                }

            }

            $combinedHist = array_merge($ghsRows ?? [], $cfaRows ?? []);

            $seq = 0;

            $deno_history = [];

            foreach ($combinedHist as $hRow) {

                $rawTransId = (int)($hRow->trans_id ?? 0);

                $keyId = $rawTransId > 0 ? $rawTransId : (++$seq * -1);

                if (!isset($deno_history[$keyId])) {

                    $deno_history[$keyId] = [

                        'trans_id' => $rawTransId > 0 ? $rawTransId : null,

                        'date_created' => $hRow->date_created ?? null,

                        'trans_status' => $hRow->trans_status ?? null,
                        'actor_name' => trim((string)($hRow->actor_name ?? '')),
                        'source_tables' => [],

                        'lines' => [],

                        'meta' => [

                            'has_ghs' => false,

                            'has_cfa' => false,

                            'net_ghs' => 0.0,

                            'net_cfa' => 0.0,

                        ],

                    ];

                }

                $lineCurrency = $hRow->currency;

                $lineVolume   = (float)($hRow->volume ?? 0);
                $sourceTable = $lineCurrency === 'CFA' ? 'drawer_fx_denos' : 'drawer_denos';
                if (!in_array($sourceTable, $deno_history[$keyId]['source_tables'], true)) {
                    $deno_history[$keyId]['source_tables'][] = $sourceTable;
                }
                if (empty($deno_history[$keyId]['actor_name']) && !empty($hRow->actor_name)) {
                    $deno_history[$keyId]['actor_name'] = trim((string)$hRow->actor_name);
                }

                $deno_history[$keyId]['lines'][] = [

                    'currency' => $lineCurrency,

                    'denomination' => (float)($hRow->denomination ?? 0),

                    'count' => (int)($hRow->count ?? 0),

                    'volume' => $lineVolume,

                ];

                if ($lineCurrency === 'CFA') {

                    $deno_history[$keyId]['meta']['has_cfa'] = true;

                    $deno_history[$keyId]['meta']['net_cfa'] += $lineVolume;

                } else {

                    $deno_history[$keyId]['meta']['has_ghs'] = true;

                    $deno_history[$keyId]['meta']['net_ghs'] += $lineVolume;

                }

            }

            if (!empty($deno_history)) {

                $deno_history = array_values($deno_history);

                usort($deno_history, function ($a, $b) {

                    return strcmp($b['date_created'] ?? '', $a['date_created'] ?? '');

                });

                $runningGhs = 0.0;
                $runningCfa = 0.0;
                foreach (array_reverse(array_keys($deno_history)) as $historyIndex) {
                    $historyItem = &$deno_history[$historyIndex];
                    $historyItem['balance_before'] = ['ghs' => $runningGhs, 'cfa' => $runningCfa];
                    $runningGhs += (float)($historyItem['meta']['net_ghs'] ?? 0);
                    $runningCfa += (float)($historyItem['meta']['net_cfa'] ?? 0);
                    $historyItem['balance_after'] = ['ghs' => $runningGhs, 'cfa' => $runningCfa];
                }
                unset($historyItem);

                if (function_exists('operations_account_enrich_deno_history')) {
                    $deno_history = operations_account_enrich_deno_history($deno_history);
                }

                $deno_history = array_slice($deno_history, 0, 20);

            }

        } catch (\Throwable $e) {

            $deno_history = [];

        }

        $cashdrawer_edit_history = [];
        try {
            if ($db->table_exists(['logs'])) {
                $cashdrawer_edit_history = $db->query(
                    "SELECT user_name, description, date_created
                     FROM logs
                     WHERE table_name = 'cashdrawers'
                       AND action = 'edited'
                       AND description LIKE :needle
                     ORDER BY date_created DESC, id DESC
                     LIMIT 12",
                    ['needle' => '%[cashdrawer_id:' . (int)$drawerId . ']%']
                ) ?: [];
            }
        } catch (\Throwable $e) {
            $cashdrawer_edit_history = [];
        }



        // Channel exchange history (tracked in logs table)

        $exchange_history = [];

        try {

            if ($db->table_exists(['logs'])) {

                $logCols = [];
                try {
                    $logSchema = $db->query('SHOW COLUMNS FROM logs') ?: [];
                    foreach ($logSchema as $col) {
                        $logCols[strtolower((string)($col->Field ?? ''))] = true;
                    }
                } catch (\Throwable $e) {
                    $logCols = [];
                }

                $selectName = "TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS exchange_user_name";
                if (!empty($logCols['user_name'])) {
                    $selectName = "COALESCE(NULLIF(l.user_name, ''), TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')))) AS exchange_user_name";
                }

                $needleLegacy = "%channel_exchange drawer_id={$drawerId}%";
                $needleHuman = "%drawer_id: {$drawerId}%";

                $exchange_history = $db->query(

                    "SELECT l.description, l.date_created, {$selectName}
                     FROM logs AS l
                     LEFT JOIN users AS u ON u.id = l.user_id
                     WHERE l.table_name = 'cashdrawer_channel_exchange'
                       AND (l.description LIKE :needle_legacy OR l.description LIKE :needle_human)
                     ORDER BY l.date_created DESC, l.id DESC
                     LIMIT 20",

                    [
                        'needle_legacy' => $needleLegacy,
                        'needle_human' => $needleHuman,
                    ]

                ) ?: [];

            }

        } catch (\Throwable $e) {

            $exchange_history = [];

        }



        $prettyChannelKey = function($key){

            $k = strtoupper(trim((string)$key));

            if (stripos($k, 'ECASH_') === 0) {

                $k = substr($k, 6);

            }

            return trim(str_replace('_', ' ', $k));

        };



        $parseExchangeDescription = function($desc) use ($prettyChannelKey){

            $desc = (string)$desc;

            $out = ['from' => '', 'to' => '', 'amount' => 0.0];

            if (preg_match('/\bfrom=([^\s]+)\s+to=([^\s]+)\s+amount=([0-9.]+)/i', $desc, $m)) {

                $out['from'] = $prettyChannelKey($m[1] ?? '');

                $out['to'] = $prettyChannelKey($m[2] ?? '');

                $out['amount'] = (float)($m[3] ?? 0);

                return $out;

            }

            if (preg_match('/\bfrom\s+([A-Z0-9_\-]+)\s+to\s+([A-Z0-9_\-]+).*?\bAmount:\s*([0-9,.]+)/i', $desc, $m)) {

                $out['from'] = $prettyChannelKey($m[1] ?? '');

                $out['to'] = $prettyChannelKey($m[2] ?? '');

                $out['amount'] = (float)str_replace(',', '', (string)($m[3] ?? 0));

                return $out;

            }

            if (preg_match('/\bamount=([0-9.]+)/i', $desc, $m2)) {

                $out['amount'] = (float)($m2[1] ?? 0);

            }

            return $out;

        };



        $appName = defined('APP_NAME') ? APP_NAME : 'My Application';

        $appLOGO = defined('APP_LOGO') ? APP_LOGO : 'default_logo.png'; 

        $appLogo = get_image($appLOGO, 'product');

        $name = strtoupper(trim($row->user_first_name . ' ' . $row->user_last_name));
        $drawerTitle = esc($name . "'s Cash Drawer");
        $appNameDisplay = esc($appName);
        $appLogoDisplay = esc($appLogo);
        $drawerDateLabel = '';
        $drawerDateRaw = (string)($row->date_created ?? '');
        if ($drawerDateRaw !== '') {
            $drawerTs = strtotime($drawerDateRaw);
            if ($drawerTs !== false) {
                $drawerDateLabel = date('jS M, Y (H:i:s)', $drawerTs);
            } else {
                $drawerDateLabel = $drawerDateRaw;
            }
        }

        // Format totals as needed

        $effectiveBalance = $total_volume;

        $totalVolume = number_format($effectiveBalance, 2);

        $totalCount = number_format($total_count, 0);



        $html = "<div class='reportDiv mx-auto px-4' data-drawer-id='{$drawerId}'>";

        $html .= "<header class='cashdrawer-summary-header'>";
        $html .= "<div class='cashdrawer-summary-topline'>
                    <div class='cashdrawer-summary-brand'>
                        <span class='cashdrawer-summary-logo'><img src='{$appLogoDisplay}' alt='{$appNameDisplay}'></span>
                        <span class='cashdrawer-summary-app-name'>{$appNameDisplay}</span>
                    </div>";
        if ($drawerDateLabel !== '') {
            $drawerDateDisplay = esc($drawerDateLabel);
            $drawerDateValue = esc($drawerDateRaw);
            $html .= "<div class='cashdrawer-summary-created'><i class='fa-regular fa-clock'></i><span>Created <time datetime='{$drawerDateValue}'>{$drawerDateDisplay}</time></span></div>";
        }

        $html .= "</div>";
        $html .= "<div class='cashdrawer-summary-title'>
                    <span class='cashdrawer-summary-kicker'>Cash drawer overview</span>
                    <h3>{$drawerTitle}</h3>
                  </div>";
        $html .= "<div class='d-none'><button type='button' onclick='if(window.basicAdminNormalizeEcashChannels){window.basicAdminNormalizeEcashChannels();}'><i class='fa fa-compress-alt'></i> Normalize Bank Channels</button></div>";
        $html .= "<div class='cashdrawer-summary-metrics'>";
        $html .= "<div class='cashdrawer-summary-metric cashdrawer-summary-metric--ghs'>
                    <span class='cashdrawer-summary-metric-icon'><i class='fa-solid fa-money-bill-wave'></i></span>
                    <div><span class='cashdrawer-summary-metric-label'>GHS cash balance</span><strong>GHS {$totalVolume}</strong></div>
                  </div>";
        $html .= "<div class='cashdrawer-summary-metric cashdrawer-summary-metric--count'>
                    <span class='cashdrawer-summary-metric-icon'><i class='fa-solid fa-layer-group'></i></span>
                    <div><span class='cashdrawer-summary-metric-label'>Total cash count</span><strong>{$totalCount}</strong></div>
                  </div>";

        if ($combinedBuy !== null || $combinedSell !== null) {
            if ($combinedBuy !== null) {
                $buyLabel = number_format($combinedBuy, 2);
                $html .= "<div class='cashdrawer-summary-metric cashdrawer-summary-metric--buy'>
                            <span class='cashdrawer-summary-metric-icon'><i class='fa-solid fa-arrow-down'></i></span>
                            <div><span class='cashdrawer-summary-metric-label'>Available to buy</span><strong>GHS {$buyLabel}</strong></div>
                          </div>";
            }

            if ($combinedSell !== null) {
                $sellLabel = number_format($combinedSell, 2);
                $html .= "<div class='cashdrawer-summary-metric cashdrawer-summary-metric--sell'>
                            <span class='cashdrawer-summary-metric-icon'><i class='fa-solid fa-arrow-up'></i></span>
                            <div><span class='cashdrawer-summary-metric-label'>Available to sell</span><strong>GHS {$sellLabel}</strong></div>
                          </div>";
            }
        }

        if ($cfaTotalVolume > 0) {
            $cfaTotalVolumeLabel = number_format((float)$cfaTotalVolume, 2);
            $cfaTotalCountLabel  = number_format((float)$cfaTotalCount, 0);
            $html .= "<div class='cashdrawer-summary-metric cashdrawer-summary-metric--cfa'>
                        <span class='cashdrawer-summary-metric-icon'><i class='fa-solid fa-coins'></i></span>
                        <div><span class='cashdrawer-summary-metric-label'>CFA volume</span><strong>CFA {$cfaTotalVolumeLabel}</strong></div>
                      </div>";
            $html .= "<div class='cashdrawer-summary-metric cashdrawer-summary-metric--cfa-count'>
                        <span class='cashdrawer-summary-metric-icon'><i class='fa-solid fa-layer-group'></i></span>
                        <div><span class='cashdrawer-summary-metric-label'>CFA cash count</span><strong>{$cfaTotalCountLabel}</strong></div>
                      </div>";
        }

        $html .= "</div></header>";



        $html .= "<section class='cashdrawer-denomination-section cashdrawer-denomination-section--ghs'>";
        $html .= "<div class='cashdrawer-denomination-heading'>
                    <span class='cashdrawer-denomination-heading-icon'><i class='fa-solid fa-money-bill-wave'></i></span>
                    <div><h6>GHS denominations</h6><small>Physical cash composition</small></div>
                    <span class='cashdrawer-denomination-currency'>GHS</span>
                  </div>";
        $html .= "<div class='cashdrawer-denomination-table-wrap'>";
        $html .= "<table class='table table-sm cashdrawer-denomination-table cashdrawer-denomination-table--ghs' style='border-collapse: collapse; margin-bottom: 0;'>

                        <thead style='position: sticky; top: 0;'>

                            <tr>

                                <th style='padding: 10px; text-align: center; font-weight: 600;'>#</th>

                                <th style='padding: 10px; font-weight: 600;'>DENOMINATION</th>

                                <th style='padding: 10px; text-align: right; font-weight: 600;'>COUNT</th>

                                <th style='padding: 10px; text-align: right; font-weight: 600;'>VOLUME</th>

                            </tr>

                        </thead>

                        <tbody>";

        $index = 0;



        if (!empty($rows)) {

            $drawerData = [

                'date_created' => date("Y-m-d H:i:s"),

                'user_id'      => $userId,

                'created_by'   => get_session()->id ?? null,

            ];



            foreach ($rows as $cashdrawer) {

                $index++;

                $denomination = esc($cashdrawer->denomination ?? '');

                $count = number_format($cashdrawer->count ?? 0);

                $volume = number_format($cashdrawer->volume ?? 0, 2);

                $html .= "<tr class='cashdrawer-denomination-row'>
                            <td><span class='cashdrawer-denomination-index'>{$index}</span></td>
                            <td><span class='cashdrawer-denomination-value'>GHS {$denomination}</span></td>
                            <td class='text-end'><span class='cashdrawer-denomination-count'>{$count}</span></td>
                            <td class='text-end'><strong class='cashdrawer-denomination-volume'>GHS {$volume}</strong></td>

                          </tr>";

            }

        } else {

            $html .= "<tr>

                        <td colspan='4' style='padding: 20px; text-align: center; '>No denominations recorded</td>

                      </tr>";

        }

        $html .= "</tbody>
                        <tfoot class='cashdrawer-denomination-total'>

                            <tr>

                                <th colspan='2'>TOTAL GHS</th>

                                <th class='text-end'>{$totalCount}</th>

                                <th class='text-end'>GHS {$totalVolume}</th>

                            </tr>

                        </tfoot>

                        </table>";

        $html .= "</div></section>";



        if (!empty($cfaDenominations)) {

            $html .= "<section class='cashdrawer-denomination-section cashdrawer-denomination-section--cfa'>";
            $html .= "<div class='cashdrawer-denomination-heading'>
                        <span class='cashdrawer-denomination-heading-icon'><i class='fa-solid fa-coins'></i></span>
                        <div><h6>CFA denominations</h6><small>Physical cash composition</small></div>
                        <span class='cashdrawer-denomination-currency'>CFA</span>
                      </div>";
            $html .= "<div class='cashdrawer-denomination-table-wrap'>";
            $html .= "<table class='table table-sm cashdrawer-denomination-table cashdrawer-denomination-table--cfa' style='border-collapse: collapse; margin-bottom: 0;'>

                            <thead style='position: sticky; top: 0;'>

                                <tr>

                                    <th style='padding: 10px; text-align: center; font-weight: 600;'>#</th>

                                    <th style='padding: 10px; font-weight: 600;'>DENOMINATION</th>

                                    <th style='padding: 10px; text-align: right; font-weight: 600;'>COUNT</th>

                                    <th style='padding: 10px; text-align: right; font-weight: 600;'>VOLUME</th>

                                </tr>

                            </thead>

                            <tbody>";



            $cfaIndex = 0;

            foreach ($cfaDenominations as $fxRow) {

                $cfaIndex++;

                $denomination = number_format((float)($fxRow->denomination ?? 0), 0);

                $count = number_format((float)($fxRow->count ?? 0), 0);

                $volume = number_format((float)($fxRow->volume ?? 0), 2);

                $html .= "<tr class='cashdrawer-denomination-row'>
                            <td><span class='cashdrawer-denomination-index'>{$cfaIndex}</span></td>
                            <td><span class='cashdrawer-denomination-value'>CFA {$denomination}</span></td>
                            <td class='text-end'><span class='cashdrawer-denomination-count'>{$count}</span></td>
                            <td class='text-end'><strong class='cashdrawer-denomination-volume'>CFA {$volume}</strong></td>

                          </tr>";

            }



            $cfaTotalCountLabel  = number_format((float)$cfaTotalCount, 0);

            $cfaTotalVolumeLabel = number_format((float)$cfaTotalVolume, 2);



            $html .= "</tbody>
                            <tfoot class='cashdrawer-denomination-total'>

                                <tr>

                                    <th colspan='2'>TOTAL CFA</th>

                                    <th class='text-end'>{$cfaTotalCountLabel}</th>

                                    <th class='text-end'>CFA {$cfaTotalVolumeLabel}</th>

                                </tr>

                            </tfoot>

                          </table>";

            $html .= "</div></section>";

        }



        $ecash_channels_for_exchange = array_values(array_filter(
            $ecash_channels,
            static function ($channel) use ($isSharedChannelKey, $normalizeChannelKey, $assignedSharedAccountKeys): bool {
                $key = $normalizeChannelKey((string)($channel->channel_key ?? ''));
                if (!$isSharedChannelKey($key)) {
                    return true;
                }
                $canonical = users_manager_normalize_shared_account_key($key);
                return $canonical !== null && in_array($canonical, $assignedSharedAccountKeys, true);
            }
        ));
        $hasNonZeroChannelBalance = function ($ch): bool {
            return abs((float)($ch->opening_balance ?? 0)) > 0.009
                || abs((float)($ch->credit ?? 0)) > 0.009
                || abs((float)($ch->debit ?? 0)) > 0.009
                || abs((float)($ch->closing_balance ?? 0)) > 0.009;
        };
        $cash_channels = array_values(array_filter($cash_channels, $hasNonZeroChannelBalance));
        $ecash_channels = array_values(array_filter($ecash_channels, $hasNonZeroChannelBalance));

        // Optional channel summaries if drawer_balances exists

        if (!empty($cash_channels) || !empty($ecash_channels)) {

            $html .= "<div class='row g-3 mt-3'>";



            // Cash channels

            $html .= "<div class='col-12'>";

            $html .= "<h6 style='font-weight: 600; color: var(--bs-body-color); margin-bottom: 10px;'>Cash Channels</h6>";

            if (!empty($cash_channels)) {

                $html .= "<table class='table table-sm cashdrawer-channel-table' style='border-collapse: collapse; margin-bottom: 0;'>

                            <thead style='background: var(--bs-tertiary-bg); border-bottom: 2px solid var(--bs-border-color);'>

                                <tr>

                                    <th style='padding: 8px; color: var(--bs-body-color); font-weight: 600;'>Channel</th>

                                    <th style='padding: 8px; text-align: right; color: var(--bs-body-color); font-weight: 600;'>Opening</th>

                                    <th style='padding: 8px; text-align: right; color: var(--bs-body-color); font-weight: 600;'>In</th>

                                    <th style='padding: 8px; text-align: right; color: var(--bs-body-color); font-weight: 600;'>Out</th>

                                    <th style='padding: 8px; text-align: right; color: var(--bs-body-color); font-weight: 600;'>Closing</th>

                                </tr>

                            </thead>

                            <tbody>";

                foreach ($cash_channels as $ch) {

                    $isCfaCash = strtoupper($ch->channel_key ?? '') === 'CASH_CFA';
                    $currencyLabel = $isCfaCash ? 'CFA' : 'GHS';
                    $rowClass = $isCfaCash ? 'cashdrawer-channel-row--cfa' : 'cashdrawer-channel-row--ghs';
                    $badgeClass = $isCfaCash ? 'cashdrawer-channel-badge--cfa' : 'cashdrawer-channel-badge--ghs';
                    $label = esc($currencyLabel);

                    $html .= "<tr class='cashdrawer-channel-row {$rowClass}' style='border-bottom: 1px solid var(--bs-border-color);'>

                                <td style='padding: 8px; color: var(--bs-body-color);'><span class='cashdrawer-channel-badge {$badgeClass}' title='{$currencyLabel} cash' aria-label='{$currencyLabel} cash'><i class='fa fa-money-bill-wave' aria-hidden='true'></i> {$label}</span></td>

                                <td data-label='Opening' style='padding: 8px; text-align: right; color: var(--bs-body-color);'>" . $currencyLabel . " " . number_format($ch->opening_balance ?? 0, 2) . "</td>

                                <td data-label='In' style='padding: 8px; text-align: right; color: var(--bs-success); font-weight: 600;'>+" . $currencyLabel . " " . number_format($ch->credit ?? 0, 2) . "</td>

                                <td data-label='Out' style='padding: 8px; text-align: right; color: var(--bs-danger); font-weight: 600;'>-" . $currencyLabel . " " . number_format($ch->debit ?? 0, 2) . "</td>

                                <td data-label='Closing' style='padding: 8px; text-align: right; color: var(--bs-body-color); font-weight: 700;'>" . $currencyLabel . " " . number_format($ch->closing_balance ?? 0, 2) . "</td>

                              </tr>";

                }

                $html .= "</tbody></table>";

            } else {

                $html .= "<p style='color: var(--bs-secondary-color); font-size: 12px; margin: 0;'>No channel-level cash data yet.</p>";

            }

            $html .= "</div>";



            // Ecash channels

            $html .= "<div class='col-12'>";

            $html .= "<h6 style='font-weight: 600; color: var(--bs-body-color); margin-bottom: 10px;'>E-Cash &amp; Shared Accounts</h6>";

            if (!empty($ecash_channels)) {

                $html .= "<table class='table table-sm cashdrawer-channel-table cashdrawer-ecash-table' style='border-collapse: collapse; margin-bottom: 0;'>

                            <thead style='background: var(--bs-tertiary-bg); border-bottom: 2px solid var(--bs-border-color);'>

                                <tr>

                                    <th style='padding: 8px; color: var(--bs-body-color); font-size: 12px; font-weight: 600;'>Channel</th>

                                    <th style='padding: 8px; color: var(--bs-body-color); font-size: 12px; font-weight: 600;'>Opening</th>

                                    <th style='padding: 8px; color: var(--bs-body-color); font-size: 12px; font-weight: 600;'>Credit</th>

                                    <th style='padding: 8px; color: var(--bs-body-color); font-size: 12px; font-weight: 600;'>Debit</th>

                                    <th style='padding: 8px; color: var(--bs-body-color); font-size: 12px; font-weight: 600;'>Closing</th>

                                </tr>

                            </thead>

                            <tbody>";

                foreach ($ecash_channels as $ch) {

                    $labelKey = (string)($ch->channel_key ?? '');

                    if (stripos($labelKey, 'ECASH_') === 0) {

                        $labelKey = substr($labelKey, 6);

                    }

                    $label = esc(str_replace(['_', '-'], ' ', $labelKey));
                    $isSharedAccount = !empty($ch->is_shared_account);
                    $rowClass = $isSharedAccount ? 'cashdrawer-channel-row--shared' : 'cashdrawer-channel-row--ecash';
                    $badgeClass = $isSharedAccount ? 'cashdrawer-channel-badge--shared' : 'cashdrawer-channel-badge--ecash';
                    $badgeLabel = $isSharedAccount
                        ? "<i class='fa fa-building-columns' aria-hidden='true'></i>"
                        : "<i class='fa fa-wallet' aria-hidden='true'></i>";
                    $badgeAccessibility = $isSharedAccount
                        ? " title='Shared account' aria-label='Shared account'"
                        : " title='E-cash' aria-label='E-cash'";

                    $html .= "<tr class='cashdrawer-channel-row {$rowClass}' style='border-bottom: 1px solid var(--bs-border-color);'>

                                <td style='padding: 8px; color: var(--bs-body-color);'><div class='cashdrawer-channel-identity'><span class='cashdrawer-channel-badge {$badgeClass}'{$badgeAccessibility}>{$badgeLabel}</span><span class='cashdrawer-channel-label'>{$label}</span></div></td>

                                <td data-label='Opening' style='padding: 8px; color: var(--bs-body-color); font-size: 12px;'>" . number_format((float)($ch->opening_balance ?? 0), 2) . "</td>

                                <td data-label='Credit' style='padding: 8px; color: var(--bs-success-text); font-size: 12px; font-weight: 600;'>+" . number_format((float)($ch->credit ?? 0), 2) . "</td>

                                <td data-label='Debit' style='padding: 8px; color: var(--bs-danger-text); font-size: 12px; font-weight: 600;'>-" . number_format((float)($ch->debit ?? 0), 2) . "</td>

                                <td data-label='Closing' style='padding: 8px; color: var(--bs-body-color); font-size: 12px; font-weight: 600;'>" . number_format((float)($ch->closing_balance ?? 0), 2) . "</td>

                            </tr>";

                }

                $html .= "</tbody></table>";

            } else {

                $html .= "<p style='color: var(--bs-secondary-color); font-size: 12px; margin: 0;'>No e-cash balances recorded for this drawer yet.</p>";

            }

            $html .= "</div>";



            $html .= "</div>";

        }



        // Inline denomination history section inside drawer slide-in (no nested modal)
        if (!empty($deno_history)) {
            $html .= "<div style='margin-top: 24px; display:none;' id='drawerDenoHistoryWrapper'>";
            $html .= "<h6 style='font-weight:600; margin-bottom:10px; display:flex; align-items:center; gap:6px;'><span>🧾</span><span>Denomination History</span></h6>";
            $html .= "<div style='max-height:420px; overflow-y:auto; padding-right:4px;' id='drawerDenoHistory'>";

            foreach ($deno_history as $tx) {

                $txId = $tx['trans_id'] ?? null;

                $dateCreated = $tx['date_created'] ?? '';

                $status = $tx['trans_status'] ?? 'Unknown';

                $lines = $tx['lines'] ?? [];

                $meta  = $tx['meta'] ?? ['has_ghs' => false, 'has_cfa' => false, 'net_ghs' => 0.0, 'net_cfa' => 0.0];
                $actorName = trim((string)($tx['actor_name'] ?? ''));
                $detail = is_array($tx['transaction_detail'] ?? null) ? $tx['transaction_detail'] : null;

                $typeLabel = 'Movement';

                // Special label for denomination exchange movements

                if (stripos((string)$status, 'vault:') === 0) {

                    $typeLabel = 'Vault Transaction';

                } elseif (strcasecmp($status, 'Exchange') === 0) {

                    $typeLabel = 'Denomination Exchange';

                } elseif (stripos((string)$status, 'opening') !== false || empty($txId)) {

                    $typeLabel = 'Opening Snapshot';

                } elseif (!empty($meta['has_ghs']) && !empty($meta['has_cfa'])) {

                    $typeLabel = 'FX Exchange';

                } else {

                    $netGhs = (float)($meta['net_ghs'] ?? 0);

                    $netCfa = (float)($meta['net_cfa'] ?? 0);

                    $net    = $netGhs !== 0.0 ? $netGhs : $netCfa;

                    if ($net > 0) {

                        $typeLabel = 'Cash In';

                    } elseif ($net < 0) {

                        $typeLabel = 'Cash Out';

                    }

                }

                $totalGhs = 0.0; $totalCfa = 0.0;
                $givenParts = [];
                $receivedParts = [];

                foreach ($lines as $ln) {

                    if (($ln['currency'] ?? 'GHS') === 'CFA') {

                        $totalCfa += (float)($ln['volume'] ?? 0);

                    } else {

                        $totalGhs += (float)($ln['volume'] ?? 0);

                    }

                    $currLabel = (string)($ln['currency'] ?? 'GHS');
                    $denValue = (float)($ln['denomination'] ?? 0);
                    $countValue = (int)($ln['count'] ?? 0);
                    $absCount = abs($countValue);
                    if ($absCount > 0) {
                        $denText = $currLabel . ' ' . ($currLabel === 'CFA' ? number_format($denValue, 0) : number_format($denValue, 2)) . ' x ' . $absCount;
                        if ($countValue < 0) {
                            $givenParts[] = $denText;
                        } elseif ($countValue > 0) {
                            $receivedParts[] = $denText;
                        }
                    }

                }

                $html .= "<div class='transaction-item mb-3'>";

                $html .= "<div style='display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;'>";

                // Use a distinct badge for Exchange movements

                if (strcasecmp($status, 'Exchange') === 0) {

                    $badgeClass = 'status-exchange';

                } else {

                    $displayStatus = preg_replace('/^vault:\s*/i', '', (string)$status);
                    $badgeClass = (strcasecmp($displayStatus, 'Approved') === 0 ? 'status-pending' : 'status-draft');

                }

                $displayStatus = preg_replace('/^vault:\s*/i', '', (string)$status);
                $html .= "<span class='transaction-status {$badgeClass}'>" . esc($displayStatus) . "</span>";

                $html .= "<span style='font-size:12px; color:var(--text-secondary);'>" . esc($dateCreated) . "<br><strong>" . esc($typeLabel) . "</strong></span>";

                $html .= "</div>";

                $html .= "<div class='transaction-details'>";

                $html .= "<strong>TX #" . ($txId ? esc($txId) : 'N/A') . "</strong><br>";

                if (strcasecmp($status, 'Exchange') === 0) {
                    $html .= "<div><strong>Given out:</strong> " . esc(!empty($givenParts) ? implode(', ', $givenParts) : 'N/A') . "</div>";
                    $html .= "<div><strong>Received:</strong> " . esc(!empty($receivedParts) ? implode(', ', $receivedParts) : 'N/A') . "</div>";
                    if ($actorName !== '') {
                        $html .= "<div style='margin-top:2px; color:var(--bs-secondary-color);'><strong>By:</strong> " . esc($actorName) . "</div>";
                    }
                } else {
                    $html .= "GHS: <strong>" . number_format($totalGhs, 2) . "</strong> &nbsp; | &nbsp; CFA: <strong>" . number_format($totalCfa, 2) . "</strong>";
                    if ($actorName !== '') {
                        $html .= "<br><span style='color:var(--bs-secondary-color);'><strong>By:</strong> " . esc($actorName) . "</span>";
                    }
                }

                if (!empty($detail)) {
                    $detailBits = [];
                    foreach (['type' => 'Type', 'network' => 'Network', 'phone' => 'Phone', 'reference' => 'Ref', 'status' => 'Status'] as $detailKey => $detailLabel) {
                        if (!empty($detail[$detailKey])) {
                            $detailBits[] = $detailLabel . ': ' . (string)$detail[$detailKey];
                        }
                    }
                    if (array_key_exists('amount', $detail) && $detail['amount'] !== null) {
                        $detailBits[] = 'Amount: ' . esc((string)($detail['currency'] ?? 'GHS')) . ' ' . number_format((float)$detail['amount'], 2);
                    }
                    $detailId = (string)($detail['id'] ?? '');
                    $lookupId = (string)($detail['lookup_id'] ?? '');
                    $detailIdLabel = $detailId !== '' ? ' #' . $detailId : '';
                    if ($lookupId !== '' && $lookupId !== $detailId) {
                        $detailIdLabel .= ' (TX ' . $lookupId . ')';
                    }
                    $html .= "<div class='mt-2 p-2' style='border:1px solid var(--bs-border-color); border-radius:8px;'>";
                    $html .= "<div class='fw-semibold small'>" . esc((string)($detail['label'] ?? 'Transaction Detail')) . esc($detailIdLabel) . "</div>";
                    if (!empty($detailBits)) {
                        $html .= "<div class='small mt-1'>" . esc(implode(' | ', $detailBits)) . "</div>";
                    }
                    if (!empty($detail['summary']) && is_array($detail['summary'])) {
                        $summary = array_values(array_filter($detail['summary'], static function ($item): bool {
                            return trim((string)$item) !== '' && !preg_match('/:\s*$/', (string)$item);
                        }));
                        $html .= "<div class='small text-muted mt-1'>" . esc(implode(' | ', array_slice($summary, 0, 6))) . "</div>";
                    }
                    $html .= "</div>";
                }

                $html .= "</div>";

                $balanceBefore = $tx['balance_before'] ?? ['ghs' => 0, 'cfa' => 0];
                $balanceAfter = $tx['balance_after'] ?? ['ghs' => 0, 'cfa' => 0];
                $html .= "<div class='cash-balance text-muted' style='margin-top:8px; padding:8px; border-left:3px solid #0066cc; font-size:11px;'>";
                $html .= "<strong>Cash Balance</strong><br><div style='margin-top:4px; display:flex; justify-content:space-between; gap:15px;'>";
                $html .= "<span><strong>Before:</strong> GHS " . number_format((float)($balanceBefore['ghs'] ?? 0), 2) . " | CFA " . number_format((float)($balanceBefore['cfa'] ?? 0), 0) . "</span>";
                $html .= "<span><strong>After:</strong> GHS " . number_format((float)($balanceAfter['ghs'] ?? 0), 2) . " | CFA " . number_format((float)($balanceAfter['cfa'] ?? 0), 0) . "</span>";
                $html .= "</div></div>";

                $html .= "<div style='margin-top:6px;'>";

                $html .= "<table class='table table-sm mb-0'><thead><tr><th>Curr</th><th>Denom</th><th>Count</th><th>Volume</th></tr></thead><tbody>";

                foreach ($lines as $ln) {

                    $curr = $ln['currency'] ?? 'GHS';

                    $den  = (float)($ln['denomination'] ?? 0);

                    $denLabel = $curr === 'CFA' ? number_format($den, 0) : number_format($den, 2);

                    $html .= "<tr><td>" . esc($curr) . "</td><td>{$denLabel}</td><td>" . (int)($ln['count'] ?? 0) . "</td><td>" . number_format((float)($ln['volume'] ?? 0), 2) . "</td></tr>";

                }

                $html .= "</tbody></table></div></div>";

            }

            $html .= "</div></div>";
        }


        $html .= "<div style='margin-top: 16px;'>";
        $html .= "<h6 style='font-weight:600; margin-bottom:10px; display:flex; align-items:center; gap:6px;'><span>i</span><span>Cashdrawer Edit History</span></h6>";
        if (!empty($cashdrawer_edit_history)) {
            $html .= "<div style='max-height:220px; overflow-y:auto; padding-right:4px;'>";
            foreach ($cashdrawer_edit_history as $editLog) {
                $html .= function_exists('operations_account_render_cashdrawer_edit_history_entry')
                    ? operations_account_render_cashdrawer_edit_history_entry($editLog)
                    : ("<div class='transaction-item mb-2' style='padding:10px; border:1px solid var(--bs-border-color); border-radius:8px;'><div style='font-size:13px;'>" . esc((string)($editLog->description ?? '')) . "</div></div>");
            }
            $html .= "</div>";
        } else {
            $html .= "<div class='empty-state'><div class='empty-state-icon'>i</div><p>No cashdrawer edits recorded for this drawer yet.</p></div>";
        }
        $html .= "</div>";



        // Channel exchange history list

        $html .= "<div style='margin-top: 16px;'>";

        $html .= "<h6 style='font-weight:600; margin-bottom:10px; display:flex; align-items:center; gap:6px;'><span>🔁</span><span>Channel Exchange History</span></h6>";

        if (!empty($exchange_history)) {

            $html .= "<div style='max-height:220px; overflow-y:auto; padding-right:4px;'>";

            foreach ($exchange_history as $hx) {

                $d = (string)($hx->date_created ?? '');

                $desc = (string)($hx->description ?? '');

                $parsed = $parseExchangeDescription($desc);

                $fromLabel = trim((string)($parsed['from'] ?? ''));

                $toLabel = trim((string)($parsed['to'] ?? ''));

                $amt = (float)($parsed['amount'] ?? 0);

                $actorName = trim((string)($hx->exchange_user_name ?? ($hx->user_name ?? '')));

                if ($actorName === '' && preg_match('/\buser="([^"]+)"/i', $desc, $userMatch)) {

                    $actorName = trim((string)($userMatch[1] ?? ''));

                }

                $mainLine = '';

                if ($fromLabel !== '' && $toLabel !== '') {

                    $mainLine = $fromLabel . ' -> ' . $toLabel;

                }

                $amtLine = $amt > 0 ? ('GHS ' . number_format($amt, 2)) : '';

                $html .= "<div class='transaction-item mb-2' style='padding:10px; border:1px solid var(--bs-border-color); border-radius:8px;'>";

                $html .= "<div style='display:flex; justify-content:space-between; align-items:center; gap:10px;'>";

                $html .= "<span class='transaction-status status-exchange'>Exchange</span>";

                $html .= "<span style='font-size:12px; color:var(--text-secondary);'>" . esc($d) . "</span>";

                $html .= "</div>";

                if ($mainLine !== '' || $amtLine !== '') {

                    $html .= "<div style='margin-top:6px; font-size:13px; font-weight:600;'>" . esc(trim($mainLine)) . "</div>";

                    if ($amtLine !== '') {

                        $html .= "<div style='margin-top:2px; font-size:12px; color:var(--bs-secondary-color);'>" . esc($amtLine) . "</div>";

                    }

                    if ($actorName !== '') {

                        $html .= "<div style='margin-top:2px; font-size:12px; color:var(--bs-secondary-color);'>By " . esc($actorName) . "</div>";

                    }

                } else {

                    $html .= "<div style='margin-top:6px; font-size:13px;'>" . esc($desc) . "</div>";

                }

                $html .= "</div>";

            }

            $html .= "</div>";

        } else {

            $html .= "<div class='empty-state'><div class='empty-state-icon'>🔁</div><p>No channel exchanges recorded for this drawer yet.</p></div>";

        }

        $html .= "</div>";



        // Close report wrapper

        $html .= "</div>"; // reportDiv end



        $ecashKeys = [];

        if (!empty($ecash_channels_for_exchange)) {
            foreach ($ecash_channels_for_exchange as $ch) {

                $k = trim((string)($ch->channel_key ?? ''));

                if ($k !== '') {

                    $ecashKeys[$k] = true;

                }

            }

        }

        if (empty($ecashKeys) && !empty($drawerId) && !empty($row->user_id)) {
            try {
                $keyRows = $db->query(
                    "SELECT DISTINCT channel_key
                     FROM drawer_balances
                     WHERE drawer_id = :drawer_id
                       AND user_id = :user_id
                       AND channel_type = 'ecash'
                       AND COALESCE(channel_key, '') <> ''
                     ORDER BY channel_key ASC",
                    [
                        'drawer_id' => $drawerId,
                        'user_id' => (int)$row->user_id,
                    ]
                ) ?: [];

                foreach ($keyRows as $keyRow) {
                    $k = trim((string)($keyRow->channel_key ?? ''));
                    $normalizedKey = $normalizeChannelKey($k);
                    $canonicalKey = users_manager_normalize_shared_account_key($normalizedKey);
                    if ($k !== '' && (
                        !$isSharedChannelKey($normalizedKey)
                        || ($canonicalKey !== null && in_array($canonicalKey, $assignedSharedAccountKeys, true))
                    )) {
                        $ecashKeys[$k] = true;
                    }
                }
            } catch (\Throwable $e) {
                // The drawer can still render; the exchange modal will show no channels.
            }
        }



        echo json_encode([

            'success' => true,

            'html' => $html,

            'user_name' => $name ?? '',

            'drawer_id' => $drawerId,

            'ecash_keys' => array_keys($ecashKeys),

        ]);

        return;

    }



    if ($action === 'normalize-ecash-channels') {
        $drawerId = (int)($rawData['drawerId'] ?? 0);
        if ($drawerId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid payload: drawerId is required.']);
            return;
        }

        if (!$db->table_exists(['drawer_balances', 'cashdrawers'])) {
            echo json_encode(['success' => false, 'message' => 'Cashdrawer balances not available.']);
            return;
        }

        $drawerRow = $db->query("SELECT user_id FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawerId])[0] ?? null;
        $drawerUserId = (int)($drawerRow->user_id ?? 0);
        if ($drawerUserId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Cashdrawer not found.']);
            return;
        }

        $cols = [];
        try {
            $schema = $db->query('SHOW COLUMNS FROM drawer_balances') ?: [];
            foreach ($schema as $col) {
                $cols[strtolower((string)($col->Field ?? ''))] = true;
            }
        } catch (\Throwable $e) {
            $cols = [];
        }
        $hasProofImage = !empty($cols['proof_image']);
        $hasTransId = !empty($cols['trans_id']);
        $hasTransStatus = !empty($cols['trans_status']);

        $bankMap = [
            'ECASH_ABSA_BANK' => ['ABSA', 'ABSA_BANK', 'ABSA_FORMERLY_BARCLAYS', 'ABSA_FORMERLY_BARCLAYS_BANK', 'ABSA_BARCLAYS', 'BARCLAYS', 'BARCLAYS_BANK'],
            'ECASH_FIDELITY_BANK' => ['FIDELITY', 'FIDELITY_BANK'],
            'ECASH_APEX_NAFANA_BANK' => ['APEX_NAFANA', 'APEX_NAFANA_BANK'],
            'ECASH_APEX_SUMA_BANK' => ['APEX_SUMA', 'APEX_SUMA_BANK'],
            'ECASH_ACCESS_BANK' => ['ACCESS', 'ACCESS_BANK'],
            'ECASH_CAL_BANK' => ['CAL', 'CAL_BANK'],
            'ECASH_ECOBANK' => ['ECOBANK'],
            'ECASH_GCB_BANK' => ['GCB', 'GCB_BANK'],
            'ECASH_GT_BANK' => ['GT', 'GT_BANK'],
            'ECASH_STANBIC_BANK' => ['STANBIC', 'STANBIC_BANK'],
            'ECASH_FIRST_NATIONAL_BANK' => ['FIRST_NATIONAL_BANK', 'FNB', 'FNB_BANK', 'FIRST_NATIONAL_BANK_FNB'],
            'ECASH_FIRST_BANK_GHANA' => ['FIRST_BANK_GHANA', 'FBG', 'FBG_BANK', 'FIRST_BANK_GHANA_FBG'],
        ];

        $aliasToCanonical = [];
        foreach ($bankMap as $canonical => $aliases) {
            $aliasToCanonical[strtoupper(trim($canonical))] = $canonical;
            $canonicalSlug = strtoupper(trim(preg_replace('/[^A-Z0-9_]+/', '_', $canonical)));
            $canonicalSlug = preg_replace('/^ECASH_/', '', $canonicalSlug);
            $aliasToCanonical[$canonicalSlug] = $canonical;
            foreach ($aliases as $alias) {
                $alias = strtoupper(trim((string)$alias));
                if ($alias === '') continue;
                $aliasToCanonical[$alias] = $canonical;
            }
        }

        $normalizeSlug = function (string $key): string {
            $key = strtoupper(trim($key));
            if (strpos($key, 'ECASH_') === 0) {
                $key = substr($key, 6);
            }
            $key = preg_replace('/[^A-Z0-9_]+/', '_', $key);
            $key = trim($key, '_');
            return $key;
        };

        $rows = $db->query(
            "SELECT * FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash'",
            ['drawer_id' => $drawerId, 'user_id' => $drawerUserId]
        );
        if (empty($rows) || !is_array($rows)) {
            echo json_encode(['success' => true, 'message' => 'No e-cash rows found for this drawer.', 'drawerId' => $drawerId]);
            return;
        }

        $groups = [];
        foreach ($rows as $rowBal) {
            $key = strtoupper(trim((string)($rowBal->channel_key ?? '')));
            if ($key === '') continue;
            $slug = $normalizeSlug($key);
            $canonical = $aliasToCanonical[$slug] ?? null;
            if (!$canonical) continue;
            if (!isset($groups[$canonical])) $groups[$canonical] = [];
            $groups[$canonical][] = $rowBal;
        }

        if (empty($groups)) {
            echo json_encode(['success' => true, 'message' => 'No bank channels found to normalize.', 'drawerId' => $drawerId]);
            return;
        }

        $details = [];
        $totalDeleted = 0;

        try {
            $db->query('START TRANSACTION');

            foreach ($groups as $canonical => $group) {
                $detail = ['canonical' => $canonical, 'rows' => count($group), 'action' => 'ok', 'deleted' => 0];
                if (count($group) === 1) {
                    $one = $group[0];
                    $oneKey = strtoupper(trim((string)($one->channel_key ?? '')));
                    if ($oneKey !== $canonical) {
                        $db->query(
                            "UPDATE drawer_balances SET channel_key = :channel_key WHERE id = :id",
                            ['channel_key' => $canonical, 'id' => (int)($one->id ?? 0)]
                        );
                        $detail['action'] = 'renamed';
                    }
                    $details[] = $detail;
                    continue;
                }

                $keep = null;
                foreach ($group as $r) {
                    $rKey = strtoupper(trim((string)($r->channel_key ?? '')));
                    if ($rKey === $canonical) {
                        if ($keep === null || (int)$r->id > (int)$keep->id) $keep = $r;
                    }
                }
                if ($keep === null) {
                    foreach ($group as $r) {
                        if ($keep === null || (int)$r->id > (int)$keep->id) $keep = $r;
                    }
                }
                $keepId = (int)($keep->id ?? 0);
                if ($keepId <= 0) {
                    throw new Exception('Unable to select a canonical row to keep.');
                }

                $openingSum = 0.0;
                $creditSum = 0.0;
                $debitSum = 0.0;
                $proofImage = '';
                $transId = null;
                $transStatus = null;
                foreach ($group as $r) {
                    $openingSum += (float)($r->opening_balance ?? 0);
                    $creditSum += (float)($r->credit ?? 0);
                    $debitSum += (float)($r->debit ?? 0);
                    if ($hasProofImage && $proofImage === '' && !empty($r->proof_image)) {
                        $proofImage = (string)$r->proof_image;
                    }
                    if ($hasTransId && $transId === null && !empty($r->trans_id)) {
                        $transId = (int)$r->trans_id;
                    }
                    if ($hasTransStatus && $transStatus === null && !empty($r->trans_status)) {
                        $transStatus = (string)$r->trans_status;
                    }
                }
                $closing = $openingSum + $creditSum - $debitSum;

                $set = "channel_key = :channel_key, opening_balance = :opening, credit = :credit, debit = :debit, closing_balance = :closing";
                $params = [
                    'channel_key' => $canonical,
                    'opening' => $openingSum,
                    'credit' => $creditSum,
                    'debit' => $debitSum,
                    'closing' => $closing,
                    'id' => $keepId,
                ];
                if ($hasProofImage && $proofImage !== '') {
                    $set .= ", proof_image = :proof_image";
                    $params['proof_image'] = $proofImage;
                }
                if ($hasTransId && $transId !== null) {
                    $set .= ", trans_id = :trans_id";
                    $params['trans_id'] = $transId;
                }
                if ($hasTransStatus && $transStatus !== null) {
                    $set .= ", trans_status = :trans_status";
                    $params['trans_status'] = $transStatus;
                }
                $db->query("UPDATE drawer_balances SET {$set} WHERE id = :id", $params);

                $deleted = 0;
                foreach ($group as $r) {
                    $id = (int)($r->id ?? 0);
                    if ($id > 0 && $id !== $keepId) {
                        $db->query("DELETE FROM drawer_balances WHERE id = :id", ['id' => $id]);
                        $deleted++;
                    }
                }
                $detail['action'] = 'merged';
                $detail['deleted'] = $deleted;
                $totalDeleted += $deleted;
                $details[] = $detail;
            }

            $db->query('COMMIT');
            echo json_encode([
                'success' => true,
                'message' => 'Bank channel normalization complete.',
                'drawerId' => $drawerId,
                'groups_processed' => count($groups),
                'total_deleted' => $totalDeleted,
                'details' => $details,
            ]);
            return;
        } catch (\Throwable $e) {
            try {
                $db->query('ROLLBACK');
            } catch (\Throwable $rollbackError) {
            }
            echo json_encode(['success' => false, 'message' => 'Normalization failed. Please try again.']);
            return;
        }
    }



    throw new Exception('Unknown action');

} catch (Exception $e) {

    echo json_encode([

        'success' => false,

        'message' => $e->getMessage(),

    ]);

    return;

}

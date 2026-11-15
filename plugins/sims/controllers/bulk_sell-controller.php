<?php

/**
 * Bulk sell controller: handles multi-SIM sale submissions (AJAX + form).
 */

date_default_timezone_set('Africa/Accra');

if (!function_exists('sims_is_placeholder_msisdn')) {
    function sims_is_placeholder_msisdn(?string $value): bool
    {
        if ($value === null) {
            return false;
        }
        $normalized = strtoupper(trim($value));
        if ($normalized === '') {
            return false;
        }
        if (preg_match('/^(0|\+233)?X{5,}$/i', $normalized)) {
            return true;
        }
        return str_contains($normalized, 'XXXXX');
    }
}

if (!function_exists('sims_normalize_msisdn')) {
    function sims_normalize_msisdn(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        $digits = preg_replace('/\D+/', '', $value);
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '233')) {
            $digits = substr($digits, 3);
        }
        if (strlen($digits) === 9) {
            $digits = '0' . $digits;
        }
        if (strlen($digits) !== 10 || $digits[0] !== '0') {
            return '';
        }
        return $digits;
    }
}

$input = $req->post();

if (empty($input)) {
    $rawInput = file_get_contents('php://input');
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $input = array_merge($decoded, $input);
        foreach ($decoded as $key => $value) {
            if (!isset($_POST[$key])) {
                $_POST[$key] = $value;
            }
        }
    }
}

$isAjax = !empty($input['__ajax']);
$errors = [];

$session = get_session();
$userId = (int)($session->id ?? 0);
$username = trim(($session->first_name ?? '') . ' ' . ($session->last_name ?? '')) ?: 'System';

if (!$userId) {
    $errors['session'] = 'Session expired. Sign in again.';
}

// When requests come via Fetch JSON we may populate $_POST manually. Merge token sources so csrf_verify works.
$tokenizedInput = $input;
if (empty($tokenizedInput['csrf_token']) && !empty($_POST['csrf_token'])) {
    $tokenizedInput['csrf_token'] = $_POST['csrf_token'];
}

if (!csrf_verify($tokenizedInput, 'csrf_token')) {
    $errors['csrf'] = 'Form expired. Refresh and try again.';
}

if (!user_can('sell_sim')) {
    $errors['permission'] = 'You do not have permission to sell sims.';
}

$selectedIds = $input['selected_ids'] ?? [];
if (is_string($selectedIds)) {
    $decoded = json_decode($selectedIds, true);
    if (is_array($decoded)) {
        $selectedIds = $decoded;
    } else {
        $selectedIds = array_filter(array_map('intval', explode(',', $selectedIds)));
    }
}

$selectedIds = array_values(array_unique(array_map('intval', (array)$selectedIds)));
$selectedIds = array_filter($selectedIds, fn($id) => $id > 0);
if (empty($selectedIds)) {
    $errors['selected_ids'] = 'Select at least one sim to sell.';
}

$saleType = trim($input['sale_type'] ?? '');
$allowedSaleTypes = ['Sold - wholesale', 'Sold - retail'];
if (!in_array($saleType, $allowedSaleTypes, true)) {
    $errors['sale_type'] = 'Choose either wholesale or retail sale type.';
}

# Normalize assignment payload (supports JSON fetch submissions)
$assignments = $input['assignments'] ?? [];
if (is_string($assignments)) {
    $decodedAssignments = json_decode($assignments, true);
    if (is_array($decodedAssignments)) {
        $assignments = $decodedAssignments;
    } else {
        $assignments = [];
    }
}

$denosRaw = $input['denos'] ?? '[]';
$denosPayload = json_decode($denosRaw, true);
if (!is_array($denosPayload)) {
    $denosPayload = [];
}

$changeDenosRaw = $input['change_denominations'] ?? '[]';
$changePayload = json_decode($changeDenosRaw, true);
if (!is_array($changePayload)) {
    $changePayload = [];
}

$changeRequiredRaw = trim($input['change_required'] ?? '0');
$changeRequired = is_numeric($changeRequiredRaw) ? round((float)$changeRequiredRaw, 2) : 0.0;
$cashReceivedAmountRaw = trim($input['cash_received_amount'] ?? '0');
$cashReceivedAmount = is_numeric($cashReceivedAmountRaw) ? round((float)$cashReceivedAmountRaw, 2) : 0.0;

$note = trim($input['note'] ?? '');
$simModel = new \Sims\Sim;
$logModel = new \Sims\Log;

if (empty($errors)) {
    $params = [];
    $placeholders = [];
    foreach ($selectedIds as $idx => $id) {
        $key = 'id' . $idx;
        $placeholders[] = ':' . $key;
        $params[$key] = $id;
    }
    $params['user_id'] = $userId;
    $params['sold1'] = 'Sold - retail';
    $params['sold2'] = 'Sold - wholesale';

    $sql = "SELECT * FROM sims WHERE deleted = 0 AND id IN (" . implode(',', $placeholders) . ") AND (assigned = :user_id OR created_by = :user_id) AND transaction_status NOT IN (:sold1, :sold2)";
    $rows = $simModel->query($sql, $params);

    if (empty($rows)) {
        $errors['selected_ids'] = 'Selected sims are no longer available for sale.';
    } else {
        $rowsById = [];
        foreach ($rows as $row) {
            $rowsById[(int)$row->id] = $row;
        }

        $eligible = [];
        $totalAmount = 0;
        $mobilePattern = "/^(0\\d{9}|\\+233\\d{9})$/";
        foreach ($selectedIds as $id) {
            if (empty($rowsById[$id])) {
                continue;
            }
            $row = $rowsById[$id];
            $priceField = $saleType === 'Sold - retail' ? 'retail_price' : 'wholesale_price';
            $price = (float)($row->$priceField ?? 0);
            if ($price <= 0) {
                $errors['sim_' . $id] = "Sim " . $row->serial_number . " is missing a valid $priceField.";
                continue;
            }

            $assignData = $assignments[$id] ?? [];
            $numberAssignedRaw = trim((string)($assignData['number_assigned'] ?? ''));
            $buyerPhoneRaw = trim((string)($assignData['buyer_phone'] ?? ''));

            if ($numberAssignedRaw !== '' && sims_is_placeholder_msisdn($numberAssignedRaw)) {
                $numberAssignedRaw = '';
            }

            $numberAssigned = $numberAssignedRaw === '' ? '' : sims_normalize_msisdn($numberAssignedRaw);
            if ($numberAssignedRaw !== '' && ($numberAssigned === '' || !preg_match($mobilePattern, $numberAssigned))) {
                $errors['number_' . $id] = 'Assigned number for ' . $row->serial_number . ' is invalid.';
            }

            $buyerPhone = $buyerPhoneRaw === '' ? '' : sims_normalize_msisdn($buyerPhoneRaw);
            if ($buyerPhoneRaw !== '' && ($buyerPhone === '' || !preg_match($mobilePattern, $buyerPhone))) {
                $errors['buyer_' . $id] = 'Buyer phone for ' . $row->serial_number . ' is invalid.';
            }

            $eligible[$id] = [
                'row' => $row,
                'price' => round($price, 2),
                'number_assigned' => $numberAssigned,
                'buyer_phone' => $buyerPhone,
            ];
            $totalAmount += $price;
        }

        if (empty($eligible)) {
            $errors['selected_ids'] = 'No valid sims found for bulk sale.';
        } else {
            if (empty($denosPayload)) {
                $errors['denos'] = 'Capture the cash denominations before confirming the sale.';
            } else {
                $denoTotal = 0;
                foreach ($denosPayload as $deno) {
                    $denoTotal += (float)($deno['volume'] ?? 0);
                }
                $denoTotal = round($denoTotal, 2);
                $totalAmount = round($totalAmount, 2);

                if ($denoTotal <= 0) {
                    $errors['denos'] = 'Calculator total must be greater than zero.';
                } elseif ($denoTotal < $totalAmount - 0.01) {
                    $errors['amount_mismatch'] = 'Calculator total (GH₵' . number_format($denoTotal, 2) . ') is less than sale total (GH₵' . number_format($totalAmount, 2) . ').';
                } else {
                    $excess = round($denoTotal - $totalAmount, 2);
                    if ($excess > 0) {
                        if ($cashReceivedAmount <= 0 || abs($cashReceivedAmount - $denoTotal) > 0.01) {
                            $errors['change'] = 'Cash received amount does not match the calculator total.';
                        }
                        if (abs($changeRequired - $excess) > 0.01) {
                            $errors['change'] = 'Selected change amount must equal the excess cash.';
                        }
                        $changeTotal = 0;
                        foreach ($changePayload as $changeRow) {
                            $changeTotal += (float)($changeRow['volume'] ?? 0);
                        }
                        $changeTotal = round($changeTotal, 2);
                        if (abs($changeTotal - $excess) > 0.01) {
                            $errors['change'] = 'Change denominations must total GH₵' . number_format($excess, 2) . '.';
                        }
                    } elseif (abs($denoTotal - $totalAmount) > 0.01) {
                        $errors['amount_mismatch'] = 'Calculator total (GH₵' . number_format($denoTotal, 2) . ') must match sale total (GH₵' . number_format($totalAmount, 2) . ').';
                    }
                }
            }
        }
    }
}

if (!empty($errors)) {
    $primaryMessage = '';
    foreach ($errors as $err) {
        if (is_string($err) && $err !== '') {
            $primaryMessage = $err;
            break;
        }
    }

    if ($isAjax) {
        sims_json_response([
            'success' => false,
            'errors' => $errors,
            'message' => $primaryMessage,
        ]);
    }

    set_value('errors', $errors);
    message_fail($primaryMessage ?: 'Unable to process bulk sale.');
    redirect($admin_route . '/' . $plugin_route . '/sell');
    return;
}

$now = date('Y-m-d H:i:s');
$batchCode = 'SIM-BULK-' . date('YmdHis');
$updated = [];
$totalAmount = 0;

foreach ($eligible as $id => $info) {
    $row = $info['row'];
    $price = $info['price'];
    $commission = round($price - (float)($row->cost ?? 0), 2);
    $totalAmount += $price;

    $update = [
        'transaction_status' => $saleType,
        'amount' => $price,
        'commission' => $commission,
        'updated_by' => $userId,
        'date_updated' => $now,
        'denos' => $denosRaw,
    ];

    if ($info['number_assigned'] !== '') {
        $update['number_assigned'] = $info['number_assigned'];
    }
    if ($info['buyer_phone'] !== '') {
        $update['buyer_phone'] = $info['buyer_phone'];
    }

    $simModel->update($id, $update);

    try {
        $commissionDb = new \Core\Database;
        $commissionNetwork = trim((string)($row->network ?? ''));
        $serialNumber = trim((string)($row->serial_number ?? ''));
        $assignedNumber = trim((string)($info['number_assigned'] ?? ''));
        $commissionDescription = 'SIM SALE COMMISSION | ' . ($commissionNetwork !== '' ? $commissionNetwork . ' | ' : '') . 'Serial: ' . $serialNumber . ' | Assigned: ' . $assignedNumber . ' | ' . $saleType . ' | Batch: ' . $batchCode;
        if ($commission !== 0.0) {
            $commissionDb->query(
                "INSERT INTO commissions (date_received, colletion_period, network, commission_type, description, pay_to, assigned, amount, transaction_status, created_by, updated_by, disabled, deleted, date_created, date_updated, date_deleted) VALUES (:date_received, :colletion_period, :network, :commission_type, :description, :pay_to, :assigned, :amount, :transaction_status, :created_by, :updated_by, :disabled, :deleted, :date_created, :date_updated, :date_deleted)",
                [
                    'date_received' => $now,
                    'colletion_period' => 'INSTANT',
                    'network' => $commissionNetwork !== '' ? $commissionNetwork : null,
                    'commission_type' => 'SIM SALE COMMISSION',
                    'description' => $commissionDescription,
                    'pay_to' => $userId,
                    'assigned' => $userId,
                    'amount' => $commission,
                    'transaction_status' => 'Approved',
                    'created_by' => $userId,
                    'updated_by' => null,
                    'disabled' => 0,
                    'deleted' => 0,
                    'date_created' => $now,
                    'date_updated' => null,
                    'date_deleted' => null,
                ]
            );
        }
    } catch (\Throwable $e) {
    }

    $description = $username . ' bulk sold ' . $row->network . ' sim (Serial: ' . $row->serial_number . ') via ' . $saleType . ' [' . $batchCode . ']';
    if ($note !== '') {
        $description .= ' - ' . $note;
    }

    $logModel->insert([
        'table_name' => 'sims',
        'action' => 'bulk-sold',
        'user_id' => $userId,
        'user_name' => $username,
        'description' => $description,
        'date_created' => $now,
    ]);

    $updated[] = [
        'id' => $id,
        'serial' => $row->serial_number,
        'amount' => $price,
        'commission' => $commission,
    ];
}

sync_cashdrawer_bulk_sale($userId, $denosPayload, $changePayload, array_key_first($eligible), $totalAmount, $saleType, $batchCode);

$response = [
    'success' => true,
    'sold' => count($updated),
    'sale_type' => $saleType,
    'batch_code' => $batchCode,
    'total_amount' => round($totalAmount, 2),
    'updated_sims' => $updated,
];

if ($isAjax) {
    sims_json_response($response);
}

message_success(count($updated) . ' sim(s) sold successfully.');
redirect($admin_route . '/' . $plugin_route . '/sell');


function sync_cashdrawer_bulk_sale(int $userId, array $denosPayload, array $changePayload, ?int $referenceSimId, float $amount, string $saleType, string $batchCode): void
{
    if ($userId <= 0 || $amount <= 0) {
        return;
    }

    try {
        $drawerDb = new \Core\Database;
        $today = date('Y-m-d');
        $drawerRow = $drawerDb->query("SELECT id FROM cashdrawers WHERE user_id = :user_id AND DATE(date_created) = :today ORDER BY id DESC LIMIT 1", ['user_id' => $userId, 'today' => $today]);
        if (empty($drawerRow)) {
            $drawerRow = $drawerDb->query("SELECT id FROM cashdrawers WHERE user_id = :user_id ORDER BY date_created DESC, id DESC LIMIT 1", ['user_id' => $userId]);
        }
        if (empty($drawerRow)) {
            return;
        }
        $drawerId = (int)($drawerRow[0]->id ?? 0);
        if ($drawerId <= 0) {
            return;
        }

        if (empty($denosPayload)) {
            $denosPayload[] = [
                'currency' => 'GHS',
                'denomination' => $amount,
                'count' => 1,
                'volume' => $amount,
            ];
        }

        $dbBal = new \Core\Database;
        if (!$dbBal->table_exists(['drawer_balances'])) {
            return;
        }

        $drawerUserId = $userId;
        try {
            $userRow = $dbBal->query("SELECT user_id FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawerId]);
            if (!empty($userRow)) {
                $drawerUserId = (int)($userRow[0]->user_id ?? $userId);
            }
        } catch (\Throwable $th) {}

        $now = date('Y-m-d H:i:s');
        $transStatus = 'Approved';
        $referenceId = $referenceSimId ?? 0;
        $notes = 'Bulk SIM sale ' . $batchCode . ' (' . $saleType . ')';

        try {
            $dbDrawerDeno = new \Core\Database;
            if ($dbDrawerDeno->table_exists(['drawer_denos'])) {
                foreach ($denosPayload as $denoRow) {
                    $volume = (float)($denoRow['volume'] ?? 0);
                    if ($volume <= 0) {
                        continue;
                    }
                    $dbDrawerDeno->query(
                        "INSERT INTO drawer_denos (drawer_id, user_id, denomination, count, volume, date_created, created_by, trans_id, trans_status) VALUES (:drawer_id, :user_id, :denomination, :count, :volume, :date_created, :created_by, :trans_id, :trans_status)",
                        [
                            'drawer_id' => $drawerId,
                            'user_id' => $drawerUserId,
                            'denomination' => (float)($denoRow['denomination'] ?? 0),
                            'count' => (int)($denoRow['count'] ?? 0),
                            'volume' => $volume,
                            'date_created' => $now,
                            'created_by' => $drawerUserId,
                            'trans_id' => $referenceId,
                            'trans_status' => $transStatus,
                        ]
                    );
                }
                foreach ($changePayload as $changeRow) {
                    $volume = (float)($changeRow['volume'] ?? 0);
                    $count = (int)($changeRow['count'] ?? 0);
                    if ($volume <= 0 || $count === 0) {
                        continue;
                    }
                    $dbDrawerDeno->query(
                        "INSERT INTO drawer_denos (drawer_id, user_id, denomination, count, volume, date_created, created_by, trans_id, trans_status) VALUES (:drawer_id, :user_id, :denomination, :count, :volume, :date_created, :created_by, :trans_id, :trans_status)",
                        [
                            'drawer_id' => $drawerId,
                            'user_id' => $drawerUserId,
                            'denomination' => (float)($changeRow['denomination'] ?? 0),
                            'count' => -abs($count),
                            'volume' => -abs($volume),
                            'date_created' => $now,
                            'created_by' => $drawerUserId,
                            'trans_id' => $referenceId,
                            'trans_status' => $transStatus,
                        ]
                    );
                }
            }
        } catch (\Throwable $th) {}

        $rowsBal = $dbBal->query(
            "SELECT * FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'cash' AND channel_key = 'CASH_DRAWER' LIMIT 1",
            ['drawer_id' => $drawerId, 'user_id' => $drawerUserId]
        );

        $changeTotal = 0.0;
        foreach ($changePayload as $changeRow) {
            $changeTotal += (float)($changeRow['volume'] ?? 0);
        }
        $changeTotal = round($changeTotal, 2);

        if (empty($rowsBal)) {
            $hdr = $dbBal->query("SELECT amount FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawerId]);
            $opening = !empty($hdr) ? (float)($hdr[0]->amount ?? 0) : 0.0;
            $closing = $opening + $amount - $changeTotal;
            $dbBal->query(
                "INSERT INTO drawer_balances (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status) VALUES (:drawer_id, :user_id, 'cash', 'CASH_DRAWER', :opening, :credit, :debit, :closing, :date_created, :trans_id, :trans_status)",
                [
                    'drawer_id' => $drawerId,
                    'user_id' => $drawerUserId,
                    'opening' => $opening,
                    'credit' => $amount,
                    'debit' => $changeTotal,
                    'closing' => $closing,
                    'date_created' => $now,
                    'trans_id' => $referenceId,
                    'trans_status' => $transStatus,
                ]
            );
        } else {
            $rowBal = $rowsBal[0];
            $credit = (float)($rowBal->credit ?? 0) + $amount;
            $debit = (float)($rowBal->debit ?? 0) + $changeTotal;
            $closing = (float)($rowBal->closing_balance ?? $rowBal->opening_balance ?? 0) + $amount - $changeTotal;
            $dbBal->query(
                "UPDATE drawer_balances SET credit = :credit, debit = :debit, closing_balance = :closing, trans_status = :trans_status, date_updated = :date_updated WHERE id = :id",
                [
                    'credit' => $credit,
                    'debit' => $debit,
                    'closing' => $closing,
                    'trans_status' => $transStatus,
                    'date_updated' => $now,
                    'id' => $rowBal->id,
                ]
            );
        }
    } catch (\Throwable $th) {}
}

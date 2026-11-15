<?php

// Set timezone to Accra (Africa/Accra)
date_default_timezone_set('Africa/Accra');

$postdata = $req->post();

// Allow JSON payloads from fetch/API style submissions
if (empty($postdata)) {
    $rawInput = file_get_contents('php://input');
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded) && !empty($decoded)) {
        $postdata = array_merge($decoded, $postdata);
        foreach ($decoded as $key => $value) {
            if (!isset($_POST[$key])) {
                $_POST[$key] = $value;
            }
        }
    }
}

$isAjax = !empty($postdata['__ajax']);
$errors = [];
$sim = new \MyWallets\Sim;
$userModel = new \MyWallets\User;
$log = new \MyWallets\Log;
$session = get_session();
$actorId = $session->id ?? 0;
$actorName = trim(($session->first_name ?? '') . ' ' . ($session->last_name ?? '')) ?: 'System';

$csrf = csrf_verify($postdata);
if (!$csrf) {
    $errors['csrf'] = 'Form expired. Refresh the page and try again.';
}

if (!user_can('edit_sim')) {
    $errors['permission'] = 'You do not have permission to assign sims.';
}

$assignTo = (int)($postdata['assign_to'] ?? $postdata['assigned_user'] ?? 0);
if ($assignTo <= 0) {
    $errors['assign_to'] = 'Select the staff member who will receive these sims.';
}

$selectedRaw = $postdata['selected_ids'] ?? $postdata['sim_ids'] ?? [];
$selectedIds = [];

if (is_string($selectedRaw)) {
    $decoded = json_decode($selectedRaw, true);
    if (is_array($decoded)) {
        $selectedIds = $decoded;
    } else {
        $parts = array_filter(array_map('trim', explode(',', $selectedRaw)));
        $selectedIds = $parts;
    }
} elseif (is_array($selectedRaw)) {
    $selectedIds = $selectedRaw;
}

$selectedIds = array_values(array_unique(array_map('intval', $selectedIds)));
$selectedIds = array_filter($selectedIds, fn ($id) => $id > 0);

if (empty($selectedIds)) {
    $errors['selected_ids'] = 'Select at least one unassigned sim to continue.';
}

$targetUser = null;
if (empty($errors) && $assignTo > 0) {
    $targetUser = $userModel->first(['id' => $assignTo, 'deleted' => 0]);
    if (empty($targetUser)) {
        $errors['assign_to'] = 'The selected staff member could not be found.';
    }
}

$eligibleSims = [];
$skippedSims = [];

if (empty($errors)) {
    $placeholders = [];
    $params = [];
    foreach ($selectedIds as $idx => $id) {
        $key = ':id' . $idx;
        $placeholders[] = $key;
        $params['id' . $idx] = $id;
    }

    if (!empty($placeholders)) {
        $sql = 'SELECT id, serial_number, network, transaction_status FROM sims WHERE deleted = 0 AND id IN (' . implode(',', $placeholders) . ')';
        $rows = $sim->query($sql, $params);
        $rowsById = [];
        foreach ($rows as $record) {
            $rowsById[(int)$record->id] = $record;
        }

        foreach ($selectedIds as $id) {
            if (empty($rowsById[$id])) {
                $skippedSims[$id] = 'Sim no longer exists';
                continue;
            }

            $row = $rowsById[$id];
            $status = strtolower($row->transaction_status ?? '');
            if ($status !== 'unassigned') {
                $skippedSims[$id] = 'Currently ' . ($row->transaction_status ?? 'not available');
                continue;
            }

            $eligibleSims[$id] = $row;
        }
    }

    if (empty($eligibleSims)) {
        $errors['selected_ids'] = 'The chosen sims are no longer unassigned.';
    }
}

if (!empty($errors)) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'errors' => $errors,
        ]);
        exit;
    }

    set_value('errors', $errors);
    message_fail('Unable to assign the selected sims.');
    redirect($admin_route . '/' . $plugin_route);
}

$now = date('Y-m-d H:i:s');
$assignmentNote = trim($postdata['assignment_note'] ?? '');
$targetName = trim(($targetUser->first_name ?? '') . ' ' . ($targetUser->last_name ?? ''));
$updated = 0;

foreach ($eligibleSims as $id => $row) {
    $payload = [
        'assigned' => $assignTo,
        'transaction_status' => 'Assigned',
        'updated_by' => $actorId,
        'date_updated' => $now,
    ];
    $sim->update($id, $payload);
    $updated++;

    $description = $actorName . ' assigned ' . strtoupper($row->network ?? 'SIM') . ' sim (Serial: ' . ($row->serial_number ?? '-') . ') to ' . $targetName;
    if ($assignmentNote !== '') {
        $description .= ' | Note: ' . $assignmentNote;
    }

    $log->insert([
        'table_name' => 'sims',
        'action' => 'assigned',
        'user_id' => $actorId,
        'user_name' => $actorName,
        'description' => $description,
        'date_created' => $now,
    ]);
}

$response = [
    'success' => true,
    'assigned' => $updated,
    'assigned_to' => [
        'id' => $targetUser->id,
        'name' => $targetName,
        'image' => get_image($targetUser->image ?? ''),
    ],
    'skipped' => $skippedSims,
    'timestamp' => $now,
];

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

message_success($updated . ' sim(s) assigned to ' . $targetName . '.');
redirect($admin_route . '/' . $plugin_route);

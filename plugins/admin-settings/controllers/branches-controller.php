<?php

// Branch management controller

// Set timezone to Accra (Africa/Accra)
date_default_timezone_set('Africa/Accra');

$info = [];
$info['success'] = false;
$info['message'] = '';
$info['errors'] = [];

$postdata = $req->post();
$csrf = csrf_verify($postdata);

if(!$csrf) {
    $info['message'] = 'Form expired!';
    $info['errors']['csrf'] = 'Form expired';
    echo json_encode($info);
    die;
}

$branchId = isset($postdata['id']) ? (int)$postdata['id'] : 0;
$branchName = trim((string)($postdata['name'] ?? ''));
$branchCode = trim((string)($postdata['code'] ?? ''));
$branchActive = !empty($postdata['active']) ? 1 : 0;
$branchDeleted = !empty($postdata['deleted']) ? 1 : 0;

$errors = [];
if($branchName === '') {
    $errors['name'] = 'Branch name is required';
}

if(!empty($errors)){
    $info['errors'] = $errors;
    echo json_encode($info);
    die;
}

try {
    $db = new \Core\Database;

    if($branchCode !== ''){
        $existingCode = $db->query(
            'SELECT id FROM branches WHERE LOWER(TRIM(code)) = LOWER(TRIM(:code)) AND deleted = 0 AND id != :id LIMIT 1',
            [
                'code' => $branchCode,
                'id' => $branchId,
            ]
        );

        if(!empty($existingCode)){
            $info['message'] = 'Branch code already exists';
            $info['errors']['code'] = 'Branch code already exists';
            echo json_encode($info);
            die;
        }
    }

    if($branchId > 0){
        $existing = $db->query('SELECT id FROM branches WHERE id = :id LIMIT 1', ['id' => $branchId]);
        if(empty($existing)){
            $info['message'] = 'Branch record not found';
            echo json_encode($info);
            die;
        }

        $db->query(
            'UPDATE branches SET name = :name, code = :code, active = :active, deleted = :deleted, updated_by = :updated_by, date_updated = :date_updated WHERE id = :id',
            [
                'name' => $branchName,
                'code' => $branchCode,
                'active' => $branchActive,
                'deleted' => $branchDeleted,
                'updated_by' => get_session()->id ?? null,
                'date_updated' => date('Y-m-d H:i:s'),
                'id' => $branchId,
            ]
        );

        $info['success'] = true;
        $info['message'] = 'Branch updated successfully';
    } else {
        $db->query(
            'INSERT INTO branches (name, code, active, deleted, created_by, date_created) VALUES (:name, :code, :active, :deleted, :created_by, :date_created)',
            [
                'name' => $branchName,
                'code' => $branchCode,
                'active' => $branchActive,
                'deleted' => $branchDeleted,
                'created_by' => get_session()->id ?? null,
                'date_created' => date('Y-m-d H:i:s'),
            ]
        );

        $info['success'] = true;
        $info['message'] = 'Branch created successfully';
    }
} catch (\Throwable $e) {
    $info['message'] = 'Unable to save branch';
    $info['errors']['database'] = $e->getMessage();
}

echo json_encode($info);
die;

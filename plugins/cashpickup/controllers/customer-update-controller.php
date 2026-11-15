<?php

require_once plugin_path('helpers/customer-ledger.php');

if (!user_can('edit_cashpickup')) {
	message_fail('Access denied');
	redirect($admin_route . '/' . $plugin_route);
}

$postdata = $req->post();
$csrf = csrf_verify($postdata);
if (!$csrf) {
	message_fail('Form expired');
	redirect($admin_route . '/' . $plugin_route . '/customers');
}

$customerId = (int)(URL(3) ?? 0);
if ($customerId <= 0) {
	$customerId = (int)($postdata['customer_id'] ?? 0);
}
if ($customerId <= 0) {
	message_fail('Customer not found');
	redirect($admin_route . '/' . $plugin_route . '/customers');
}

$session = get_session();
$userId = (int)($session->id ?? 0);

$fullName = trim((string)($postdata['full_name'] ?? ''));
$phone = trim((string)($postdata['phone_number'] ?? ''));
$idType = trim((string)($postdata['id_type'] ?? ''));
$idNumber = trim((string)($postdata['id_number'] ?? ''));
$location = trim((string)($postdata['location'] ?? ''));

if ($phone === '' && $idNumber === '') {
	message_fail('Phone number or ID number is required.');
	redirect($admin_route . '/' . $plugin_route . '/customer/' . $customerId);
}

try {
	$db = new \Core\Database;
	\CashpickupLedger\ensure_cashpickup_customer_schema($db);
	\CashpickupLedger\ensure_cashpickup_columns($db);

	$db->query(
		"UPDATE cashpickup_customers
		 SET full_name = :full_name,
		     phone_number = :phone_number,
		     id_type = :id_type,
		     id_number = :id_number,
		     location = :location,
		     updated_by = :updated_by,
		     date_updated = :date_updated
		 WHERE id = :id",
		[
			'full_name' => $fullName !== '' ? $fullName : null,
			'phone_number' => $phone !== '' ? $phone : null,
			'id_type' => $idType !== '' ? $idType : null,
			'id_number' => $idNumber !== '' ? $idNumber : null,
			'location' => $location !== '' ? $location : null,
			'updated_by' => (string)$userId,
			'date_updated' => date('Y-m-d H:i:s'),
			'id' => $customerId,
		]
	);

	$payload = [
		'full_name' => $fullName,
		'phone_number' => $phone,
		'id_type' => $idType,
		'id_number' => $idNumber,
		'location' => $location,
	];

	\CashpickupLedger\link_customer_transactions($db, $customerId, $payload);

	// Also update existing linked transactions with missing bio fields
	$setParts = ['customer_id = :customer_id'];
	$params = [
		'customer_id' => $customerId,
		'full_name' => $fullName,
		'phone_number' => $phone,
		'id_type' => $idType,
		'id_number' => $idNumber,
		'location' => $location,
	];
	if ($fullName !== '') {
		$setParts[] = "sender_details = CASE WHEN sender_details IS NULL OR sender_details = '' THEN :full_name ELSE sender_details END";
	}
	if ($idType !== '') {
		$setParts[] = "customer_id_type = CASE WHEN customer_id_type IS NULL OR customer_id_type = '' THEN :id_type ELSE customer_id_type END";
	}
	if ($idNumber !== '') {
		$setParts[] = "customer_id_number = CASE WHEN customer_id_number IS NULL OR customer_id_number = '' THEN :id_number ELSE customer_id_number END";
	}
	if ($location !== '') {
		$setParts[] = "customer_location = CASE WHEN customer_location IS NULL OR customer_location = '' THEN :location ELSE customer_location END";
	}

	$whereParts = ['customer_id = :customer_id'];
	if ($phone !== '') {
		$whereParts[] = 'phone_number = :phone_number';
	}
	if ($idNumber !== '') {
		$whereParts[] = 'customer_id_number = :id_number';
	}

	$db->query(
		"UPDATE cashpickups SET " . implode(', ', $setParts) . " WHERE deleted = 0 AND (" . implode(' OR ', $whereParts) . ")",
		$params
	);

	message_success('Customer ledger updated successfully.');
	redirect($admin_route . '/' . $plugin_route . '/customer/' . $customerId);
} catch (\Throwable $e) {
	message_fail('Unable to update customer ledger.');
	redirect($admin_route . '/' . $plugin_route . '/customer/' . $customerId);
}

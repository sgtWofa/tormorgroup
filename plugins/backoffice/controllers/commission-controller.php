<?php

date_default_timezone_set('Africa/Accra');

$postdata = $req->post();
$commission = new \BackOffices\Commission;
$postdata['rows'] = isset($postdata['rows']) && is_array($postdata['rows']) ? $postdata['rows'] : [];
$postdata['assigned'] = (int)($postdata['assigned'] ?? ($postdata['pay_to'] ?? 0));
$postdata['pay_to'] = $postdata['assigned'];
$postdata['transaction_status'] = 'Pending';

$session = get_session();
$username = trim((string)(($session->first_name ?? '') . ' ' . ($session->last_name ?? '')));
$user_id = (int)($session->id ?? 0);
$csrf = csrf_verify($postdata);

if (function_exists('backoffice_ensure_commission_assignment_schema')) {
	backoffice_ensure_commission_assignment_schema();
}

if ($csrf && $commission->validate_insert($postdata)) {
	$now = date('Y-m-d H:i:s');
	$dbSchema = new \Core\Database;
	$commissionColumns = [];
	try {
		$schemaRows = $dbSchema->query("SHOW COLUMNS FROM commissions") ?: [];
		foreach ($schemaRows as $schemaRow) {
			$columnName = trim((string)($schemaRow->Field ?? ''));
			if ($columnName !== '') {
				$commissionColumns[$columnName] = true;
			}
		}
	} catch (\Throwable $e) {
		$commissionColumns = [];
	}
	$filterCommissionPayload = static function(array $payload) use ($commissionColumns): array {
		if (empty($commissionColumns)) {
			return $payload;
		}
		$filtered = [];
		foreach ($payload as $key => $value) {
			if (isset($commissionColumns[$key])) {
				$filtered[$key] = $value;
			}
		}
		return $filtered;
	};
	$dateReceivedInput = trim((string)($postdata['date_received'] ?? date('Y-m-d')));
	$dateReceived = date('Y-m-d', strtotime($dateReceivedInput));
	if ($dateReceived === '1970-01-01') {
		$commission->errors['date_received'] = 'Date received is required.';
	}

	$assignableUsers = backoffice_commission_assignable_users($user_id, $session, true);
	$assignableIds = array_values(array_unique(array_filter(array_map(function($row){
		return (int)($row->id ?? 0);
	}, $assignableUsers))));
	if ($postdata['assigned'] <= 0 || !in_array($postdata['assigned'], $assignableIds, true)) {
		$commission->errors['assigned'] = 'Select a valid general manager or admin to review this commission. If you are an admin or general manager, you may assign it to yourself.';
	}

	$assignmentRows = function_exists('users_manager_fetch_sim_assignments') ? users_manager_fetch_sim_assignments($user_id) : [];
	$assignmentRows = backoffice_commission_filter_assignments_for_session($assignmentRows, $session);
	$assignmentMap = [];
	$assignmentPaymentLineMap = [];
	foreach ($assignmentRows as $assignmentRow) {
		$assignmentId = (int)($assignmentRow->id ?? 0);
		if ($assignmentId > 0) {
			$assignmentMap[$assignmentId] = $assignmentRow;
			foreach (backoffice_commission_assignment_payment_lines($assignmentRow) as $paymentLine) {
				$field = trim((string)($paymentLine['commission_field'] ?? 'general'));
				$assignmentPaymentLineMap[$assignmentId . '|' . $field] = $paymentLine;
			}
		}
	}
	if (empty($assignmentMap)) {
		$commission->errors['rows'] = 'No SIM assignments were found for your account.';
	}

	$req->upload_folder = 'uploads/backoffice/commissions';
	$maxFileSize = 1 * 1024 * 1024;
	$totalAmount = 0.0;
	$selectedRows = [];

	foreach ($postdata['rows'] as $index => $row) {
		$assignmentId = (int)($row['sim_assignment_id'] ?? 0);
		$commissionField = trim((string)($row['commission_field'] ?? 'general'));
		if ($commissionField === '') {
			$commissionField = 'general';
		}
		$amountRaw = trim((string)($row['amount'] ?? ''));
		$amountValue = ($amountRaw === '') ? 0.0 : (float)$amountRaw;

		if ($amountValue < 0) {
			$commission->errors['amount_' . $index] = 'Amount cannot be negative.';
			continue;
		}

		if ($amountValue <= 0) {
			continue;
		}

		if ($assignmentId <= 0 || empty($assignmentMap[$assignmentId])) {
			$commission->errors['amount_' . $index] = 'Invalid assigned SIM row.';
			continue;
		}
		$paymentLineKey = $assignmentId . '|' . $commissionField;
		if (empty($assignmentPaymentLineMap[$paymentLineKey])) {
			$commission->errors['amount_' . $index] = 'Invalid commission payment row.';
			continue;
		}

		$fileKey = 'proof_image_' . $index;
		$hasIncomingProof = !empty($_FILES[$fileKey]['tmp_name']);
		if ($hasIncomingProof) {
			$fileSize = (int)($_FILES[$fileKey]['size'] ?? 0);
			if ($fileSize > $maxFileSize) {
				$commission->errors[$fileKey] = 'Proof image must not exceed 1MB.';
				continue;
			}
		}

		$uploadedProof = $hasIncomingProof ? $req->upload_files($fileKey) : '';
		$existingProof = trim((string)($row['existing_proof'] ?? ''));
		if ($uploadedProof !== '' && empty($req->upload_errors[$fileKey])) {
			$existingProof = $uploadedProof;
			$postdata['rows'][$index]['existing_proof'] = $uploadedProof;
		}

		if ($existingProof === '') {
			$commission->errors[$fileKey] = $req->upload_errors[$fileKey] ?? 'Upload proof for every SIM amount entered.';
			continue;
		}

		$assignment = $assignmentMap[$assignmentId];
		$paymentLine = $assignmentPaymentLineMap[$paymentLineKey];
		$snapshot = is_array($paymentLine['snapshot'] ?? null) ? $paymentLine['snapshot'] : backoffice_commission_assignment_snapshot($assignment);
		$commissionLabel = trim((string)($paymentLine['commission_label'] ?? ($snapshot['commission_label'] ?? ($snapshot['commission_type'] ?? 'Commission'))));
		$lineDescription = backoffice_commission_assignment_label($snapshot);
		if ($commissionLabel !== '') {
			$lineDescription .= ' - ' . $commissionLabel;
		}
		$totalAmount += $amountValue;
		$selectedRows[] = [
			'index' => (int)$index,
			'amount' => $amountValue,
			'assignment_id' => $assignmentId,
			'commission_field' => $commissionField,
			'commission_label' => $commissionLabel,
			'network' => $snapshot['network'] ?? '',
			'commission_type' => strtoupper($commissionLabel !== '' ? $commissionLabel : ($snapshot['commission_type'] ?? 'AGENT COMMISSION')),
			'proof_image' => $existingProof,
			'description' => $lineDescription,
			'snapshot' => $snapshot,
		];
	}

	if ($totalAmount <= 0) {
		$commission->errors['rows'] = 'Enter at least one commission amount.';
	}

	if (!empty($commission->errors)) {
		set_value('old_post', $postdata);
		set_value('errors', array_merge($commission->errors, $req->upload_errors));
		return;
	}

	$postdata['created_by'] = $user_id;
	$postdata['date_created'] = $now;
	$postdata['date_received'] = $dateReceived . ' ' . date('H:i:s');

	$networks = array_values(array_unique(array_filter(array_map(function($row){
		return trim((string)($row['network'] ?? ''));
	}, $selectedRows))));
	$parentDescription = trim((string)($postdata['description'] ?? ''));
	if ($parentDescription === '') {
		$parentDescription = 'SIM commission bulk (' . count($selectedRows) . ' items)';
	}

	$parentPayload = [
		'date_received' => $postdata['date_received'],
		'amount' => $totalAmount,
		'network' => count($networks) === 1 ? $networks[0] : 'MULTI',
		'commission_type' => 'OTHER SIM COMMISSION BULK',
		'colletion_period' => '',
		'pay_to' => $postdata['assigned'],
		'assigned' => $postdata['assigned'],
		'description' => $parentDescription,
		'transaction_status' => 'Pending',
		'created_by' => $user_id,
		'assignment_snapshot_json' => json_encode(array_map(function($row){
			return [
				'assignment' => $row['snapshot'],
				'commission_field' => $row['commission_field'],
				'commission_label' => $row['commission_label'],
				'amount' => $row['amount'],
				'proof_image' => $row['proof_image'],
			];
		}, $selectedRows), JSON_UNESCAPED_SLASHES),
		'date_created' => $now,
	];

	$commission->insert($filterCommissionPayload($parentPayload));
	$commissionId = (int)($commission->insert_id ?? 0);
	if ($commissionId <= 0) {
		$commissionError = trim((string)($commission->error ?? ''));
		$commission->errors['rows'] = 'Failed to save commission submission.' . ($commissionError !== '' ? ' ' . $commissionError : '');
		set_value('old_post', $postdata);
		set_value('errors', $commission->errors);
		return;
	}

	foreach ($selectedRows as $selectedRow) {
		$childPayload = [
			'bulk_parent_id' => $commissionId,
			'date_received' => $postdata['date_received'],
			'amount' => $selectedRow['amount'],
			'network' => $selectedRow['network'],
			'commission_type' => $selectedRow['commission_type'],
			'colletion_period' => '',
			'pay_to' => $postdata['assigned'],
			'assigned' => $postdata['assigned'],
			'sim_assignment_id' => $selectedRow['assignment_id'],
			'commission_field' => $selectedRow['commission_field'],
			'proof_image' => $selectedRow['proof_image'],
			'description' => $selectedRow['description'],
			'transaction_status' => 'Pending',
			'created_by' => $user_id,
			'assignment_snapshot_json' => json_encode($selectedRow['snapshot'], JSON_UNESCAPED_SLASHES),
			'date_created' => $now,
		];
		$commission->insert($filterCommissionPayload($childPayload));
		if (!empty($commission->has_error) || !empty($commission->error)) {
			$commissionError = trim((string)($commission->error ?? ''));
			$commission->errors['rows'] = 'Commission submission was saved, but one or more SIM rows could not be attached.' . ($commissionError !== '' ? ' ' . $commissionError : '');
			set_value('old_post', $postdata);
			set_value('errors', $commission->errors);
			return;
		}
	}

	$action = 'added';
	$transaction = 'commission pending';
	$table = 'commissions';
	$description = "$username $action a new $transaction with id $commissionId to $table";

	$log = new \BackOffices\Log;
	$log->insert([
		'table_name' => $table,
		'action' => $action,
		'user_id' => $user_id,
		'user_name' => $username,
		'description' => $description,
		'date_created' => $now,
	]);
	backoffice_whatsapp_notify_transaction([
		'type' => 'Commission',
		'id' => $commissionId,
		'status' => 'Pending',
		'creator_id' => (int)$user_id,
		'assigned_id' => (int)$postdata['assigned'],
		'details' => [
			'Network' => $parentPayload['network'],
			'Amount' => 'GHS ' . number_format($totalAmount, 2),
			'Description' => $parentDescription,
		],
		'view_url' => ROOT . '/admin/back_office/view_commission/' . $commissionId,
	]);

	message_success('Commission submission sent for approval successfully.');
	redirect($admin_route . '/' . $plugin_route);
}

if (!$csrf) {
	$commission->errors['description'] = 'Form expired!';
}

set_value('old_post', $postdata);
set_value('errors', array_merge($commission->errors, $req->upload_errors));

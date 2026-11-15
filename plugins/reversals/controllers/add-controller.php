<?php

date_default_timezone_set('Africa/Accra');

$postdata = $req->post();
$reversal = new \Reversals\Reversal;
$csrf = csrf_verify($postdata);

if ($csrf) {
	if (user_can('add_reversal')) {
		$session = get_session();
		$userId = (int)($session->id ?? 0);
		$username = trim((string)(($session->first_name ?? '') . ' ' . ($session->last_name ?? '')));

		$lookup = reversals_lookup_source_transaction(
			(string)($postdata['source_transaction_type'] ?? ''),
			(string)($postdata['source_transaction_id'] ?? '')
		);

		if (empty($lookup['success'])) {
			$reversal->errors['source_transaction_id'] = $lookup['message'] ?? 'Source transaction could not be found.';
		} else {
			$data = $lookup['data'] ?? [];
			$validationData = $postdata;
			$validationData['network'] = $data['service_provider'] ?? ($postdata['network'] ?? '');
			$validationData['service_type'] = $data['service_type'] ?? ($postdata['service_type'] ?? '');
			$validationData['receiver_details'] = $data['receiver_details'] ?? ($postdata['receiver_details'] ?? '');
			$validationData['amount'] = $data['amount'] ?? ($postdata['amount'] ?? 0);
			$validationData['transaction_date'] = $data['transaction_date'] ?? ($postdata['transaction_date'] ?? '');

			$assignedUserId = (int)($postdata['assigned'] ?? 0);
			$assignableReviewers = reversals_assignable_reviewers();
			$assignableReviewerIds = array_values(array_unique(array_filter(array_map(function($row){
				return (int)($row->id ?? 0);
			}, $assignableReviewers))));

			if (!$reversal->validate_insert($validationData)) {
				// errors already populated
			} elseif ($assignedUserId <= 0 || !in_array($assignedUserId, $assignableReviewerIds, true)) {
				$reversal->errors['assigned'] = 'Select a valid general manager or admin with a cashdrawer created for today.';
			} else {
			$id = time() + random_int(1, 99);
			$now = reversals_now()->format('Y-m-d H:i:s');
			$transactionDate = trim((string)($data['transaction_date'] ?? ''));
			if ($transactionDate === '') {
				$transactionDate = $now;
			}

			$payload = [
				'id' => $id,
				'source_transaction_type' => $data['source_transaction_type'] ?? '',
				'source_transaction_id' => $data['source_transaction_id'] ?? '',
				'source_table' => $data['source_table'] ?? '',
				'source_channel_kind' => $data['source_channel_kind'] ?? '',
				'source_channel_key' => $data['source_channel_key'] ?? '',
				'source_snapshot_json' => json_encode($data['snapshot'] ?? [], JSON_UNESCAPED_SLASHES),
				'network' => $data['service_provider'] ?? '',
				'assigned' => (string)$assignedUserId,
				'service_type' => $data['service_type'] ?? '',
				'sender_details' => trim((string)($postdata['sender_details'] ?? '')),
				'receiver_details' => $data['receiver_details'] ?? '',
				'reason' => trim((string)($postdata['reversal_type'] ?? '')),
				'reversal_type' => trim((string)($postdata['reversal_type'] ?? '')),
				'workflow_stage' => 'manager_review',
				'manager_review_status' => 'pending',
				'transaction_date' => $transactionDate,
				'agent_involved' => (int)($data['source_created_by'] ?? 0),
				'amount' => (float)($data['amount'] ?? 0),
				'reversal_medium' => $data['source_channel_kind'] ?? '',
				'transaction_status' => 'Pending Manager Acknowledgement',
				'created_by' => $userId,
				'date_created' => $now,
				'disabled' => 0,
				'deleted' => 0,
				'total_refunded' => 0,
				'total_claimed' => 0,
			];

			$reversal->insert($payload);
			reversals_log_activity(
				'added',
				$userId,
				$username,
				$username . ' created reversal #' . $id . ' for source transaction ' . ($payload['source_transaction_type'] ?? '') . ' / ' . ($payload['source_transaction_id'] ?? '')
			);

			message_success('Reversal submitted for manager acknowledgement successfully.');
			redirect($admin_route . '/' . $plugin_route . '/view/' . $id);
			}
		}
	} else {
		$reversal->errors['source_transaction_type'] = 'You do not have permission to add reversals.';
	}
}
else {
	$reversal->errors['source_transaction_type'] = 'Form expired!';
}

set_value('errors', $reversal->errors);

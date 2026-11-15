<?php

use function BackOfficeCashdrawer\calculateTotal;
use function BackOfficeCashdrawer\getActiveDrawer;
use function BackOfficeCashdrawer\normalizeDenominations;
use function BackOfficeCashdrawer\syncCashMovement;
use function BackOfficeCashdrawer\checkGhsDenominationSufficiency;
use function BackOfficeCashdrawer\checkCashDenominationCountSufficiencyCurrency;
use function BackOfficeCashdrawer\checkEcashChannelSufficiency;

if(!empty($row))
{
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$postdata = $req->post();
	$postdata['id'] = $row->id;

	$csrf = csrf_verify($postdata);
	$networkValue = strtoupper(trim((string)($postdata['network'] ?? $row->network ?? '')));
	$isCashNetwork = ($networkValue === 'CASH');
	$amountValue = (float)($postdata['amount'] ?? $row->amount ?? 0);
	$denosPayload = [];
	$denosTotal = 0;
	if ($isCashNetwork) {
		$rawDenos = $postdata['denos_json'] ?? ($postdata['denos'] ?? ($row->denos_json ?? '[]'));
		if (is_string($rawDenos) && trim($rawDenos) === '') {
			$rawDenos = '[]';
		}
		$denosPayload = normalizeDenominations($rawDenos, $amountValue);
		$denosTotal = calculateTotal($denosPayload);
	} else {
		unset($postdata['denos_json']);
		unset($postdata['denos']);
	}
	$statusTarget = strtolower($postdata['transaction_status'] ?? $row->transaction_status ?? '');
	$assignedUserId = (int)($postdata['assigned'] ?? $row->assigned ?? 0);
	if ($statusTarget === 'approved' && $isCashNetwork) {
		if (empty($denosPayload)) {
			$request->errors['denos_json'] = 'Capture the cash denominations before approval.';
		}
		if ($denosTotal > 0 && abs($denosTotal - $amountValue) > 0.01) {
			$request->errors['denos_json_mismatch'] = 'Calculator total must match request amount.';
		}
	}

	if($csrf && $request->validate_update($postdata))
	{
		if(!empty($request->errors)){
			set_value('errors',$request->errors);
			return;
		}
		if(user_can('edit_request'))
		{
			$postdata['date_updated'] = date("Y-m-d H:i:s");

			//log changes in the log table
			$session = get_session();

			if(!empty($session->id)){
				$username = $session->first_name . ' '. $session->last_name;
				$user_id = $session->id;
			}else{
				$username = $row->first_name . ' '. $row->last_name;
				$user_id = $row->id;
			}

			$postdata['updated_by'] = $user_id;
			if ($isCashNetwork) {
				if (!empty($denosPayload)) {
					$postdata['denos_json'] = json_encode($denosPayload);
				} else {
					unset($postdata['denos_json']);
				}
			}
			if ($statusTarget === 'approved' && $isCashNetwork) {
				if($assignedUserId <= 0){
					message_fail('Select a receiving user before approving requests.');
					redirect($admin_route.'/'.$plugin_route.'/edit_request/'.$row->id);
					exit;
				}
				$check = checkGhsDenominationSufficiency($assignedUserId, $denosPayload, $amountValue);
				if(!$check['ok']){
					$required = number_format($check['required'] ?? 0, 2);
					$available = number_format($check['available'] ?? 0, 2);
					$message = "Your cashdrawer does not have enough GHS notes to fulfill this request.";
					if(($check['required'] ?? 0) > 0){
						$message .= " Required: GHS {$required}, available: GHS {$available}.";
					}
					message_fail($message);
					redirect($admin_route.'/'.$plugin_route.'/edit_request/'.$row->id);
					exit;
				}
				$countsCheck = checkCashDenominationCountSufficiencyCurrency($assignedUserId, $denosPayload, $amountValue, 'GHS');
				if (empty($countsCheck['ok'])) {
					$shortages = $countsCheck['shortages'] ?? [];
					$msg = 'Your cashdrawer does not have sufficient GHS denominations to fulfill this request.';
					if (!empty($shortages) && is_array($shortages)) {
						$parts = [];
						foreach ($shortages as $s) {
							$den = (string)($s['denomination'] ?? '');
							$need = (int)($s['required_count'] ?? 0);
							$have = (int)($s['available_count'] ?? 0);
							if ($den === '' || $need <= 0) continue;
							$parts[] = "{$den} x{$need} (available {$have})";
						}
						if (!empty($parts)) {
							$msg .= ' Shortages: ' . implode(', ', $parts) . '.';
						}
					}
					message_fail($msg);
					redirect($admin_route.'/'.$plugin_route.'/edit_request/'.$row->id);
					exit;
				}
				$recipientDrawer = getActiveDrawer($assignedUserId);
				if (empty($recipientDrawer)) {
					message_fail('The receiving user must open a cashdrawer before approval.');
					redirect($admin_route.'/'.$plugin_route.'/edit_request/'.$row->id);
					exit;
				}
				$postdata['cashdrawer_id'] = $recipientDrawer['drawer_id'];
				$postdata['drawer_user_id'] = $recipientDrawer['drawer_user_id'];
				$postdata['approved_by'] = $user_id;
				$postdata['approved_at'] = date("Y-m-d H:i:s");
			}
			if ($statusTarget === 'approved' && !$isCashNetwork) {
				if ($assignedUserId <= 0) {
					message_fail('Select a receiving user before approving requests.');
					redirect($admin_route.'/'.$plugin_route.'/edit_request/'.$row->id);
					exit;
				}
				if ($amountValue > 0) {
					$ecashChannelKey = backoffice_request_resolved_channel_key(
						(string)($postdata['network'] ?? $row->network ?? ''),
						(string)($postdata['request_type'] ?? $row->request_type ?? '')
					);
					$ecashCheck = checkEcashChannelSufficiency($assignedUserId, $ecashChannelKey, $amountValue);
					if (empty($ecashCheck['ok'])) {
						$required = number_format((float)($ecashCheck['required'] ?? 0), 2);
						$available = number_format((float)($ecashCheck['available'] ?? 0), 2);
						message_fail("Insufficient E-Cash balance on {$ecashChannelKey}. Required: {$required}, available: {$available}.");
						redirect($admin_route.'/'.$plugin_route.'/edit_request/'.$row->id);
						exit;
					}
				}
				$postdata['approved_by'] = $user_id;
				$postdata['approved_at'] = date("Y-m-d H:i:s");
			}

			unset($postdata['id']);
			$request->update($row->id,$postdata);

			backoffice_whatsapp_notify_creator_on_assignee_update([
				'type' => 'Backoffice Request',
				'id' => (int)$row->id,
				'status' => $postdata['transaction_status'] ?? ($row->transaction_status ?? ''),
				'creator_id' => (int)($row->created_by ?? 0),
				'assigned_id' => $assignedUserId,
				'details' => [
					'Network' => $postdata['network'] ?? ($row->network ?? ''),
					'Request type' => $postdata['request_type'] ?? ($row->request_type ?? ''),
					'Amount' => 'GHS ' . number_format($amountValue, 2),
					'Description' => $postdata['description'] ?? ($row->description ?? ''),
				],
				'view_url' => ROOT . '/admin/back_office/view_request/' . (int)$row->id,
			], $user_id);

			if ($statusTarget === 'approved' && $isCashNetwork && !empty($denosPayload)) {
				// When approving a request, the original requester receives (IN)
				// and the assigned user pays out (OUT) from their cashdrawer.
				$requesterId = (int)($row->created_by ?? 0);
				if ($requesterId > 0) {
					syncCashMovement($requesterId, $denosPayload, $amountValue, 'Approved', $row->id, 'in');
				}
				if ($assignedUserId > 0) {
					syncCashMovement($assignedUserId, $denosPayload, $amountValue, 'Approved', $row->id, 'out');
				}
			}

			//log changes in the log table
			$action 		= 'updated';
			$transactionNetwork = trim((string)($postdata['network'] ?? $row->network ?? ''));
			$transactionType = trim((string)($postdata['request_type'] ?? $row->request_type ?? ''));
			$transaction 		= trim($transactionNetwork . ' ' . $transactionType);
			$transaction_id 		= $row->id;
			$table 		= 'requests';
			$description 	= "$username $action a request for $transaction with id $transaction_id to $table";
			$date_created 	= date("Y-m-d H:i:s");

		    $log = new \BackOffices\Log;
		    $log_activity = $log->insert([
			 	'table_name'=>$table,
			 	'action'=>$action,
			 	'user_id'=>$user_id,
			 	'user_name'=>$username,
			 	'description'=>$description,
			 	'date_created'=>$date_created,
			 ]);

			message_success("Request with id: $transaction_id updated successfully!");
			redirect($admin_route.'/'.$plugin_route);
		}
	}

	if(!$csrf)
		$request->errors['usn'] = "Form expired!";

	set_value('errors',array_merge($request->errors,$req->upload_errors));
}else{

	message_fail("Record not found");
}

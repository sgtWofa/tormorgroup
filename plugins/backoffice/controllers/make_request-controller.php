<?php
	
	use function BackOfficeCashdrawer\calculateTotal;
	use function BackOfficeCashdrawer\getActiveDrawer;
	use function BackOfficeCashdrawer\normalizeDenominations;
	use function BackOfficeCashdrawer\syncCashMovement;
    use function BackOfficeCashdrawer\checkGhsDenominationSufficiency;
	use function BackOfficeCashdrawer\checkCashDenominationCountSufficiencyCurrency;
	use function BackOfficeCashdrawer\checkEcashChannelSufficiency;

	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$postdata = $req->post();

	$csrf = csrf_verify($postdata);
	$request = new \BackOffices\RequestClass;

	$networkValue = strtoupper(trim((string)($postdata['network'] ?? '')));
	$isCashNetwork = ($networkValue === 'CASH');
	$amountValue = (float)($postdata['amount'] ?? 0);
	$denosPayload = [];
	$denosTotal = 0;
	if ($isCashNetwork) {
		$rawDenos = $postdata['denos_json'] ?? ($postdata['denos'] ?? '[]');
		if (is_string($rawDenos) && trim($rawDenos) === '') {
			$rawDenos = '[]';
		}
		$cashCurrencyForDenos = strtoupper(trim((string)($postdata['request_type'] ?? 'GHS')));
		if (!in_array($cashCurrencyForDenos, ['GHS', 'CFA'], true)) {
			$cashCurrencyForDenos = 'GHS';
		}
		$denosPayload = normalizeDenominations($rawDenos, $amountValue, $cashCurrencyForDenos);
		$denosTotal = calculateTotal($denosPayload);
		if (empty($denosPayload)) {
			$request->errors['denos_json'] = 'Capture the cash denominations before submitting this request.';
		}
		if ($denosTotal > 0 && abs($denosTotal - $amountValue) > 0.01) {
			$request->errors['denos_json_mismatch'] = 'Calculator total must match request amount.';
		}
	} else {
		unset($postdata['denos_json']);
		unset($postdata['denos']);
	}

	if($csrf && $request->validate_insert($postdata))
	{
		if (!$isCashNetwork && empty($postdata['request_type'])) {
			$request->errors['request_type'] = 'Request type is required';
		}
		if(!empty($request->errors)){
			set_value('old_post', $postdata);
			set_value('errors',$request->errors);
			return;
		}
		if(user_can('add_request'))
		{
			$postdata['date_created'] = date("Y-m-d H:i:s");
			if (!empty($postdata['amount'])) {
				$postdata['credit'] = $postdata['amount'];
				$postdata['balance'] = $postdata['amount'];
			}

			$session = get_session();

			if(!empty($session->id)){
				$username = $session->first_name . ' '. $session->last_name;
				$user_id = $session->id;
			}else{
				$username = $row->first_name . ' '. $row->last_name;
				$user_id = $row->id;
			}

			$assignedUserId = (int)($postdata['assigned'] ?? 0);
			if($assignedUserId <= 0){
				$request->errors['assigned'] = 'Select a receiving user before submitting this request.';
				set_value('old_post', $postdata);
				set_value('errors', $request->errors);
				return;
			}

			$assignedDrawer = getActiveDrawer($assignedUserId);
			if (empty($assignedDrawer)) {
				$request->errors['assigned'] = 'The assigned user must open a cashdrawer before this request can be submitted.';
				set_value('old_post', $postdata);
				set_value('errors', $request->errors);
				return;
			}

			$drawerUserId = (int)($assignedDrawer['drawer_user_id'] ?? $assignedUserId);
			$drawerId = (int)($assignedDrawer['drawer_id'] ?? 0);
			if ($drawerId <= 0 || $drawerUserId <= 0) {
				$request->errors['assigned'] = 'The assigned user must have a valid cashdrawer before this request can be submitted.';
				set_value('old_post', $postdata);
				set_value('errors', $request->errors);
				return;
			}
			if ($isCashNetwork) {
				$cashCurrency = strtoupper(trim((string)($postdata['request_type'] ?? 'GHS')));
				if (!in_array($cashCurrency, ['GHS', 'CFA'], true)) {
					$cashCurrency = 'GHS';
				}

				$balanceCheck = \BackOfficeCashdrawer\checkCashDenominationSufficiencyCurrency($drawerUserId, $denosPayload, $amountValue, $cashCurrency);
				if (empty($balanceCheck['ok'])) {
					$cur = strtoupper((string)($balanceCheck['currency'] ?? $cashCurrency));
					$required = number_format((float)($balanceCheck['required'] ?? 0), ($cur === 'CFA') ? 0 : 2);
					$available = number_format((float)($balanceCheck['available'] ?? 0), ($cur === 'CFA') ? 0 : 2);
					$details = '';
					$countsCheck = checkCashDenominationCountSufficiencyCurrency($drawerUserId, $denosPayload, $amountValue, $cashCurrency);
					if (empty($countsCheck['ok'])) {
						$shortages = $countsCheck['shortages'] ?? [];
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
								$details = ' Shortages: ' . implode(', ', $parts) . '.';
							}
						}
					}
					$request->errors['denos_json'] = "Insufficient {$cur} cash balance in the assigned user's cashdrawer. Required: {$cur} {$required}, available: {$cur} {$available}.{$details}";
					set_value('old_post', $postdata);
					set_value('errors', $request->errors);
					return;
				}

				$countsCheck = checkCashDenominationCountSufficiencyCurrency($drawerUserId, $denosPayload, $amountValue, $cashCurrency);
				if (empty($countsCheck['ok'])) {
					$cur = strtoupper((string)($countsCheck['currency'] ?? $cashCurrency));
					$shortages = $countsCheck['shortages'] ?? [];
					$msg = "Insufficient {$cur} denominations in the assigned user's cashdrawer.";
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
					$request->errors['denos_json'] = $msg;
					set_value('old_post', $postdata);
					set_value('errors', $request->errors);
					return;
				}
			} else {
				$ecashChannelKey = backoffice_request_resolved_channel_key((string)($postdata['network'] ?? ''), (string)($postdata['request_type'] ?? ''));
				$ecashCheck = checkEcashChannelSufficiency($drawerUserId, $ecashChannelKey, $amountValue);
				if (empty($ecashCheck['ok'])) {
					$required = number_format((float)($ecashCheck['required'] ?? 0), 2);
					$available = number_format((float)($ecashCheck['available'] ?? 0), 2);
					$request->errors['amount'] = "Insufficient {$ecashChannelKey} balance in the assigned user's cashdrawer. Required: {$required}, available: {$available}.";
					set_value('old_post', $postdata);
					set_value('errors', $request->errors);
					return;
				}
			}

			$postdata['created_by'] = $user_id;

			// Separation of duties: creator cannot approve at creation time.
			// Always force to Pending and let only the assigned user approve via request_action endpoint.
			$postdata['transaction_status'] = 'Pending';
			unset($postdata['approved_by']);
			unset($postdata['approved_at']);
			$postdata['cashdrawer_id'] = $drawerId;
			$postdata['drawer_user_id'] = $drawerUserId;
			if ($isCashNetwork) {
				if (!empty($denosPayload)) {
					$postdata['denos_json'] = json_encode($denosPayload);
				} else {
					unset($postdata['denos_json']);
				}
			}
			$request->insert($postdata);
			if (!empty($request->has_error)) {
				$request->errors['network'] = 'Failed to create request. ' . ($request->error ?? '');
				set_value('old_post', $postdata);
				set_value('errors', $request->errors);
				return;
			}
			$insertedId = (int)($request->insert_id ?? 0);
			if ($insertedId > 0) {
				$postdata['id'] = $insertedId;
			}
			backoffice_whatsapp_notify_transaction([
				'type' => 'Backoffice Request',
				'id' => $insertedId,
				'status' => $postdata['transaction_status'],
				'creator_id' => (int)$user_id,
				'assigned_id' => $assignedUserId,
				'details' => [
					'Network' => $postdata['network'] ?? '',
					'Request type' => $postdata['request_type'] ?? '',
					'Amount' => 'GHS ' . number_format($amountValue, 2),
					'Description' => $postdata['description'] ?? '',
				],
				'view_url' => ROOT . '/admin/back_office/view_request/' . $insertedId,
			]);

			//log changes in the log table
		    $user = new \BackOffices\User;
		    //get assigned information from users:
		    $assignedUser = $user->first(['id'=>$postdata['assigned']]);
		    $assigned_Name = $assignedUser->first_name . ' ' . $assignedUser->last_name;

			$action 		= 'added';
			$transaction 		= $postdata['network'] . ' '. $postdata['request_type'];
			$transaction_id 		= $postdata['id'] ?? $insertedId;
			$table 			= 'requests';
			$description 	= "$username $action a new request for $transaction with id $transaction_id and assigned to: " . $assigned_Name;
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
 
			message_success("$transaction request created successfully!");
			redirect($admin_route.'/'.$plugin_route);
		}
	}

	if(!$csrf)
		$request->errors['usn'] = "Form expired!";
	
	if (!empty($postdata)) {
		set_value('old_post', $postdata);
	}
	set_value('errors',$request->errors);

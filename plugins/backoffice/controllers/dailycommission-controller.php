<?php
	
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$postdata = $req->post();
	$commission = new \BackOffices\Commission;

	$csrf = csrf_verify($postdata);
	if($csrf && $commission->validate_insert($postdata))
	{
		$postdata['date_created'] = date("Y-m-d H:i:s");
	    $rows = $postdata['rows'];
	    $errors = [];

		$session = get_session();

		if(!empty($session->id)){
			$username = $session->first_name . ' '. $session->last_name;
			$user_id = $session->id;
		}else{
			$username = $row->first_name . ' '. $row->last_name;
			$user_id = $row->id;
		}

		$postdata['created_by'] = $user_id;

		$commissionData = [];
		$rawStatusLower = strtolower(trim($postdata['transaction_status'] ?? ''));
		$statusLower = in_array($rawStatusLower, ['complete', 'completed'], true) ? 'approved' : $rawStatusLower;
		$postdata['transaction_status'] = $statusLower;
		$payToId = (int)($postdata['pay_to'] ?? 0);
		$totalAmountValue = 0.0;
		$currencyFlags = ['GHS' => false, 'CFA' => false];
		$lineItems = [];
		foreach ($rows as $row) {
			$amountRow = (float)($row['amount'] ?? 0);
			if ($amountRow <= 0) {
				continue;
			}
			$totalAmountValue += $amountRow;
			$rowNetwork = strtoupper(trim((string)($row['network'] ?? '')));
			if ($rowNetwork === 'CFA') {
				$currencyFlags['CFA'] = true;
			} else {
				$currencyFlags['GHS'] = true;
			}
			$lineItems[] = $row;
		}
		$cashCurrency = $currencyFlags['CFA'] ? 'CFA' : 'GHS';
		$rawDenos = $postdata['denos_json'] ?? ($postdata['denos'] ?? '[]');
		$denosPayload = \BackOfficeCashdrawer\normalizeDenominations($rawDenos, $totalAmountValue, $cashCurrency);
		$denosTotal = \BackOfficeCashdrawer\calculateTotal($denosPayload);
		$parentCommissionId = 0;
		if ($rawStatusLower === 'approved') {
			if ($currencyFlags['GHS'] && $currencyFlags['CFA']) {
				$commission->errors['transaction_status'] = 'Cannot approve daily commissions with mixed GHS and CFA entries. Submit them separately.';
			}
			if (empty($denosPayload)) {
				$commission->errors['denos_json'] = 'Capture the cash denominations before approving.';
			}
			if ($denosTotal > 0 && abs($denosTotal - $totalAmountValue) > 0.01) {
				$commission->errors['denos_json'] = 'Calculator total must match daily commission total.';
			}
			$payerDrawer = \BackOfficeCashdrawer\getActiveDrawer($user_id);
			if (empty($payerDrawer)) {
				$commission->errors['transaction_status'] = 'You must open a cashdrawer before approving daily commissions.';
			}
			$drawerUserId = (int)($payerDrawer['drawer_user_id'] ?? $user_id);
			$balanceCheck = \BackOfficeCashdrawer\checkCashDenominationSufficiencyCurrency($drawerUserId, $denosPayload, $totalAmountValue, $cashCurrency);
			if (empty($balanceCheck['ok'])) {
				$cur = strtoupper((string)($balanceCheck['currency'] ?? $cashCurrency));
				$required = number_format((float)($balanceCheck['required'] ?? 0), ($cur === 'CFA') ? 0 : 2);
				$available = number_format((float)($balanceCheck['available'] ?? 0), ($cur === 'CFA') ? 0 : 2);
				$commission->errors['transaction_status'] = "Insufficient {$cur} cashdrawer balance. Required: {$cur} {$required}, available: {$cur} {$available}.";
			}

			$countsCheck = \BackOfficeCashdrawer\checkCashDenominationCountSufficiencyCurrency($drawerUserId, $denosPayload, $totalAmountValue, $cashCurrency);
			if (empty($countsCheck['ok'])) {
				$cur = strtoupper((string)($countsCheck['currency'] ?? $cashCurrency));
				$shortages = $countsCheck['shortages'] ?? [];
				$msg = "Insufficient {$cur} denominations in cashdrawer.";
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
				$commission->errors['denos_json'] = $msg;
			}
			$receiverDrawer = \BackOfficeCashdrawer\getActiveDrawer($payToId);
			if (empty($receiverDrawer)) {
				$commission->errors['pay_to'] = 'Pay-to user must open a cashdrawer before approving daily commissions.';
			}
			if (!empty($commission->errors)) {
				set_value('old_post', $postdata);
				set_value('errors', $commission->errors);
				return;
			}
		}

		// Insert bulk parent commission
		$parentDescription = strtoupper($cashCurrency) . ' Daily commissions bulk (' . count($lineItems) . ' items)';
		$parentData = [
			'date_received' => $postdata['date_created'],
			'pay_to' => $payToId,
			'network' => $cashCurrency,
			'commission_type' => 'DAILY BULK',
			'colletion_period' => 'INSTANT DIRECT',
			'description' => $parentDescription,
			'amount' => $totalAmountValue,
			'transaction_status' => $postdata['transaction_status'] ?? '',
			'date_created' => date("Y-m-d H:i:s"),
			'created_by' => $user_id,
		];
		if (!empty($denosPayload)) {
			$parentData['denos_json'] = json_encode($denosPayload);
		}
		if ($rawStatusLower === 'approved') {
			$parentData['cashdrawer_id'] = (int)($payerDrawer['drawer_id'] ?? 0);
			$parentData['drawer_user_id'] = (int)($payerDrawer['drawer_user_id'] ?? 0);
			$parentData['approved_by'] = $user_id;
			$parentData['approved_at'] = date("Y-m-d H:i:s");
		}
		$commission->insert($parentData);
		$parentCommissionId = (int)($commission->insert_id ?? 0);

		// Insert child breakdown rows
		foreach ($lineItems as $row) {
			$amountRow = (float)($row['amount'] ?? 0);
			if ($amountRow <= 0) {
				continue;
			}
			$commissionData = [
				'bulk_parent_id' => $parentCommissionId,
				'date_received' => $postdata['date_created'],
				'pay_to' => $payToId,
				'network' => $row['network'],
				'commission_type' => $row['commission_type'],
				'colletion_period' => $row['colletion_period'],
				'description' => $row['description'],
				'amount' => $amountRow,
				'transaction_status' => $postdata['transaction_status'] ?? '',
				'date_created' => date("Y-m-d H:i:s"),
				'created_by' => $user_id,
			];
			$commission->insert($commissionData);
		}

		// Sync cashdrawer once using parent id
		if ($rawStatusLower === 'approved' && $totalAmountValue > 0 && $parentCommissionId > 0) {
			\BackOfficeCashdrawer\syncCashMovementCurrency($user_id, $denosPayload, $totalAmountValue, 'Approved', $parentCommissionId, 'out', $cashCurrency);
			\BackOfficeCashdrawer\syncCashMovementCurrency($payToId, $denosPayload, $totalAmountValue, 'Approved', $parentCommissionId, 'in', $cashCurrency);
		}

        //dd(['POSTDATA'=>$postdata, 'commissionData'=>$commissionData]); 

       	//log changes in the log table
		$action 		= 'added';
		$transaction 		= 'daily commission ' . ($postdata['transaction_status'] ?? '');
		$transaction_id 		= 0;
		$table 			= 'commissions';
		$description 	= "$username $action a new $transaction to $table";
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

		message_success("Transaction ($transaction) added successfully!");
		redirect($admin_route.'/'.$plugin_route);
		
	}

	if(!$csrf)
		$commission->errors['description'] = "Form expired!";
	
	set_value('old_post', $postdata);
	set_value('errors',$commission->errors);


<?php

if(!empty($row))
{
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$postdata = $req->post();
	$postdata['id'] = $row->id;

	

	$csrf = csrf_verify($postdata);

	if($csrf && $commission->validate_update($postdata))
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

		$prevStatus = strtolower(trim($row->transaction_status ?? ''));
		$rawNewStatus = strtolower(trim($postdata['transaction_status'] ?? ''));
		$newStatus = in_array($rawNewStatus, ['complete', 'completed'], true) ? 'approved' : $rawNewStatus;
		$postdata['transaction_status'] = $newStatus;

		$isDailyBulk = false;
		$ctype = strtoupper(trim((string)($row->commission_type ?? '')));
		if ($ctype === 'DAILY BULK' || isset($postdata['rows'])) {
			$isDailyBulk = true;
		}

		if ($isDailyBulk && isset($postdata['rows']) && is_array($postdata['rows'])) {
			$rows = $postdata['rows'];
			$payToId = (int)($postdata['pay_to'] ?? ($row->pay_to ?? 0));
			$totalAmountValue = 0.0;
			$currencyFlags = ['GHS' => false, 'CFA' => false];
			$lineItems = [];
			foreach ($rows as $r) {
				$amountRow = (float)($r['amount'] ?? 0);
				if ($amountRow <= 0) {
					continue;
				}
				$totalAmountValue += $amountRow;
				$rowNetwork = strtoupper(trim((string)($r['network'] ?? '')));
				if ($rowNetwork === 'CFA') {
					$currencyFlags['CFA'] = true;
				} else {
					$currencyFlags['GHS'] = true;
				}
				$lineItems[] = $r;
			}
			$cashCurrency = $currencyFlags['CFA'] ? 'CFA' : 'GHS';
			$rawDenos = $postdata['denos_json'] ?? ($postdata['denos'] ?? ($row->denos_json ?? '[]'));
			$denosPayload = \BackOfficeCashdrawer\normalizeDenominations($rawDenos, $totalAmountValue, $cashCurrency);
			$denosTotal = \BackOfficeCashdrawer\calculateTotal($denosPayload);
			if (!empty($denosPayload)) {
				$postdata['denos_json'] = json_encode($denosPayload);
			}

			if ($prevStatus !== 'approved' && $rawNewStatus === 'approved') {
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
				$postdata['cashdrawer_id'] = (int)($payerDrawer['drawer_id'] ?? 0);
				$postdata['drawer_user_id'] = (int)($payerDrawer['drawer_user_id'] ?? 0);
				$postdata['approved_by'] = $user_id;
				$postdata['approved_at'] = date("Y-m-d H:i:s");
				\BackOfficeCashdrawer\syncCashMovementCurrency($user_id, $denosPayload, $totalAmountValue, 'Approved', (int)$row->id, 'out', $cashCurrency);
				\BackOfficeCashdrawer\syncCashMovementCurrency($payToId, $denosPayload, $totalAmountValue, 'Approved', (int)$row->id, 'in', $cashCurrency);
			}

			$postdata['pay_to'] = $payToId;
			$postdata['amount'] = $totalAmountValue;
			$postdata['network'] = $cashCurrency;
			$postdata['commission_type'] = 'DAILY BULK';
			$postdata['colletion_period'] = 'INSTANT DIRECT';
			$postdata['description'] = strtoupper($cashCurrency) . ' Daily commissions bulk (' . count($lineItems) . ' items)';
			$postdata['updated_by'] = $user_id;
			$commission->update($row->id, $postdata);

			try {
				$commission->query(
					"UPDATE commissions SET deleted = 1, date_deleted = :dd WHERE bulk_parent_id = :pid",
					['dd' => date("Y-m-d H:i:s"), 'pid' => (int)$row->id]
				);
			} catch (\Throwable $e) {
				// ignore
			}

			foreach ($lineItems as $r) {
				$amountRow = (float)($r['amount'] ?? 0);
				if ($amountRow <= 0) {
					continue;
				}
				$commissionData = [
					'bulk_parent_id' => (int)$row->id,
					'date_received' => date("Y-m-d H:i:s"),
					'pay_to' => $payToId,
					'network' => $r['network'] ?? '',
					'commission_type' => $r['commission_type'] ?? '',
					'colletion_period' => $r['colletion_period'] ?? 'INSTANT DIRECT',
					'description' => $r['description'] ?? '',
					'amount' => $amountRow,
					'transaction_status' => $postdata['transaction_status'] ?? '',
					'date_created' => date("Y-m-d H:i:s"),
					'created_by' => $user_id,
				];
				$commission->insert($commissionData);
			}
		} else {
			$payToId = (int)($row->pay_to ?? 0);
			$amountValue = (float)($row->amount ?? 0);
			$cashCurrency = (strtoupper(trim((string)($row->network ?? ''))) === 'CFA') ? 'CFA' : 'GHS';
			$rawDenos = $postdata['denos_json'] ?? ($postdata['denos'] ?? ($row->denos_json ?? '[]'));
			$denosPayload = \BackOfficeCashdrawer\normalizeDenominations($rawDenos, $amountValue, $cashCurrency);
			$denosTotal = \BackOfficeCashdrawer\calculateTotal($denosPayload);
			if ($prevStatus !== 'approved' && $rawNewStatus === 'approved') {
				if (empty($denosPayload)) {
					$commission->errors['denos_json'] = 'Capture the cash denominations before approving.';
				}
				if ($denosTotal > 0 && abs($denosTotal - $amountValue) > 0.01) {
					$commission->errors['denos_json'] = 'Calculator total must match commission amount.';
				}
				$payerDrawer = \BackOfficeCashdrawer\getActiveDrawer($user_id);
				if (empty($payerDrawer)) {
					$commission->errors['transaction_status'] = 'You must open a cashdrawer before approving this commission.';
				}
				$drawerUserId = (int)($payerDrawer['drawer_user_id'] ?? $user_id);
				$balanceCheck = \BackOfficeCashdrawer\checkCashDenominationSufficiencyCurrency($drawerUserId, $denosPayload, $amountValue, $cashCurrency);
				if (empty($balanceCheck['ok'])) {
					$cur = strtoupper((string)($balanceCheck['currency'] ?? $cashCurrency));
					$required = number_format((float)($balanceCheck['required'] ?? 0), ($cur === 'CFA') ? 0 : 2);
					$available = number_format((float)($balanceCheck['available'] ?? 0), ($cur === 'CFA') ? 0 : 2);
					$commission->errors['transaction_status'] = "Insufficient {$cur} cashdrawer balance. Required: {$cur} {$required}, available: {$cur} {$available}.";
				}

				$countsCheck = \BackOfficeCashdrawer\checkCashDenominationCountSufficiencyCurrency($drawerUserId, $denosPayload, $amountValue, $cashCurrency);
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
					$commission->errors['transaction_status'] = 'Pay-to user must open a cashdrawer before approving this commission.';
				}
				if (!empty($commission->errors)) {
					set_value('old_post', $postdata);
					set_value('errors', $commission->errors);
					return;
				}
				$postdata['denos_json'] = json_encode($denosPayload);
				$postdata['cashdrawer_id'] = (int)($payerDrawer['drawer_id'] ?? 0);
				$postdata['drawer_user_id'] = (int)($payerDrawer['drawer_user_id'] ?? 0);
				$postdata['approved_by'] = $user_id;
				$postdata['approved_at'] = date("Y-m-d H:i:s");
				\BackOfficeCashdrawer\syncCashMovementCurrency($user_id, $denosPayload, $amountValue, 'Approved', (int)$row->id, 'out', $cashCurrency);
				\BackOfficeCashdrawer\syncCashMovementCurrency($payToId, $denosPayload, $amountValue, 'Approved', (int)$row->id, 'in', $cashCurrency);
			}

			$commission->update($row->id,$postdata);
		}

		backoffice_whatsapp_notify_creator_on_assignee_update([
			'type' => 'Commission',
			'id' => (int)$row->id,
			'status' => $postdata['transaction_status'] ?? ($row->transaction_status ?? ''),
			'creator_id' => (int)($row->created_by ?? 0),
			'assigned_id' => (int)($postdata['assigned'] ?? $row->assigned ?? $postdata['pay_to'] ?? $row->pay_to ?? 0),
			'details' => [
				'Network' => $postdata['network'] ?? ($row->network ?? ''),
				'Amount' => 'GHS ' . number_format((float)($postdata['amount'] ?? $row->amount ?? 0), 2),
				'Description' => $postdata['description'] ?? ($row->description ?? ''),
			],
			'view_url' => ROOT . '/admin/back_office/view_commission/' . (int)$row->id,
		], $user_id);

       	//log changes in the log table
		$action 		= 'edited';
		$transaction 		= 'commission ' . $postdata['transaction_status'] ;
		$transaction_id 		= $row->id;
		$table 			= 'commissions';
		$description 	= "$username $action a new $transaction with id $transaction_id to $table";
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

			message_success("Commission with id: $transaction_id updated successfully!");
			redirect($admin_route.'/'.$plugin_route);
	}

	if(!$csrf)
		$commission->errors['transaction_status'] = "Form expired!";

	set_value('old_post', $postdata);
	set_value('errors',array_merge($commission->errors,$req->upload_errors));
}else{

	message_fail("Record not found");
}

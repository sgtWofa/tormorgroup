<?php
	
	use function \BackOfficeCashdrawer\getActiveDrawer;
	use function \BackOfficeCashdrawer\normalizeDenominations;
	use function \BackOfficeCashdrawer\calculateTotal;
	use function \BackOfficeCashdrawer\syncCashMovement;
	use function \BackOfficeCashdrawer\syncCashMovementCurrency;
	use function \BackOfficeCashdrawer\checkCashDenominationSufficiencyCurrency;
	use function \BackOfficeCashdrawer\checkCashDenominationCountSufficiencyCurrency;
	use function \BackOfficeCashdrawer\checkEcashChannelSufficiency;

	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$postdata = $req->post();
	$payment = new \BackOffices\Payment;

	$csrf = csrf_verify($postdata);
	$networkOption = strtoupper(trim((string)($postdata['network'] ?? '')));
	$isCashNetwork = ($networkOption === 'CASH');
	$isReconciliationBulkPayment = !empty($postdata['recon_bulk_payment']);
	$isDynamicBulkPayment = in_array($networkOption, ['E-CASH', 'EVD'], true) && !$isReconciliationBulkPayment;

	$amountValue = (float)($postdata['amount'] ?? 0);
	$rawDenos = $postdata['denos_json'] ?? ($postdata['denos'] ?? '[]');
	$dynamicBulkBreakdown = ['items' => [], 'total' => 0.0, 'errors' => []];
	if ($isDynamicBulkPayment) {
		$dynamicBulkBreakdown = backoffice_payment_normalize_breakdown($postdata['bulk_payment_items'] ?? '[]', $networkOption);
		$amountValue = (float)$dynamicBulkBreakdown['total'];
		$postdata['amount'] = number_format($amountValue, 2, '.', '');
		$postdata['payment_type'] = 'BULK';
		$postdata['payment_breakdown_json'] = json_encode($dynamicBulkBreakdown['items']);
	}
	$denosPayload = [];
	if (!$isCashNetwork || $isReconciliationBulkPayment) {
		$postdata['denos_json'] = '[]';
	}
	if ($isCashNetwork && !$isReconciliationBulkPayment) {
		$cashCurrencyForDenos = strtoupper(trim((string)($postdata['payment_type'] ?? 'GHS')));
		if (!in_array($cashCurrencyForDenos, ['GHS', 'CFA'], true)) {
			$cashCurrencyForDenos = 'GHS';
		}
		$denosPayload = normalizeDenominations($rawDenos, $amountValue, $cashCurrencyForDenos);
		$denosTotal = calculateTotal($denosPayload);
		if (empty($denosPayload)) {
			$payment->errors['denos_json'] = 'Capture the cash denominations before submitting.';
		}
		if ($denosTotal > 0 && abs($denosTotal - $amountValue) > 0.01) {
			$payment->errors['denos_json'] = 'Calculator total must match payment amount.';
		}
	}

	if ($csrf && $isReconciliationBulkPayment) {
		if (!user_can('add_payment')) {
			$payment->errors['description'] = 'You do not have permission to add payments.';
			set_value('errors', $payment->errors);
			return;
		}

		$session = get_session();
		$user_id = (int)($session->id ?? 0);
		$username = trim((string)($session->first_name ?? '') . ' ' . (string)($session->last_name ?? ''));
		if ($username === '') {
			$username = 'User #' . $user_id;
		}
		$receiverId = (int)($postdata['pay_to'] ?? 0);
		if ($receiverId <= 0) {
			$payment->errors['pay_to'] = 'Assigned to is required';
			set_value('old_post', $postdata);
			set_value('mp_restore', 1);
			set_value('errors', $payment->errors);
			return;
		}

		$bulkItemsRaw = (string)($postdata['recon_bulk_items'] ?? '[]');
		$bulkItems = json_decode($bulkItemsRaw, true);
		if (!is_array($bulkItems) || empty($bulkItems)) {
			$payment->errors['amount'] = 'No reconciliation e-cash channels were found for payment.';
			set_value('old_post', $postdata);
			set_value('mp_restore', 1);
			set_value('errors', $payment->errors);
			return;
		}

		$payerDrawer = getActiveDrawer((int)$user_id);
		if (empty($payerDrawer)) {
			$payment->errors['network'] = 'Open your cashdrawer for today before submitting this payment.';
			set_value('old_post', $postdata);
			set_value('mp_restore', 1);
			set_value('errors', $payment->errors);
			return;
		}

		$drawerUserId = (int)($payerDrawer['drawer_user_id'] ?? $user_id);
		$drawerId = (int)($payerDrawer['drawer_id'] ?? 0);
		if ($drawerId <= 0 || $drawerUserId <= 0) {
			$payment->errors['network'] = 'Your active cashdrawer is missing valid drawer details. Reopen your cashdrawer and try again.';
			set_value('old_post', $postdata);
			set_value('mp_restore', 1);
			set_value('errors', $payment->errors);
			return;
		}
		$preparedItems = [];
		foreach ($bulkItems as $item) {
			if (is_object($item)) {
				$item = get_object_vars($item);
			}
			if (!is_array($item)) {
				continue;
			}
			$channel = strtoupper(trim((string)($item['channel'] ?? '')));
			$amount = round((float)($item['amount'] ?? $item['balance'] ?? 0), 2);
			if ($channel === '' || $amount <= 0) {
				continue;
			}
			if (backoffice_payment_is_telecel_type($channel) && !backoffice_payment_amount_is_whole_number($amount)) {
				$payment->errors['amount'] = 'Telecel payments must use whole-number amounts only.';
				set_value('old_post', $postdata);
				set_value('mp_restore', 1);
				set_value('errors', $payment->errors);
				return;
			}
			$ecashChannelKey = backoffice_payment_resolved_channel_key($channel);
			$ecashCheck = checkEcashChannelSufficiency($drawerUserId, $ecashChannelKey, $amount);
			if (empty($ecashCheck['ok'])) {
				$required = number_format((float)($ecashCheck['required'] ?? 0), 2);
				$available = number_format((float)($ecashCheck['available'] ?? 0), 2);
				$payment->errors['amount'] = "Insufficient E-Cash balance on {$ecashChannelKey}. Required: {$required}, available: {$available}.";
				set_value('old_post', $postdata);
				set_value('mp_restore', 1);
				set_value('errors', $payment->errors);
				return;
			}
			$preparedItems[] = [
				'channel' => $ecashChannelKey,
				'amount' => $amount,
			];
		}

		if (empty($preparedItems)) {
			$payment->errors['amount'] = 'No valid reconciliation e-cash channels were found for payment.';
			set_value('old_post', $postdata);
			set_value('mp_restore', 1);
			set_value('errors', $payment->errors);
			return;
		}

		$now = date("Y-m-d H:i:s");
		$baseId = (int)round(microtime(true) * 1000);
		$inserted = 0;
		foreach ($preparedItems as $index => $item) {
			$channel = (string)$item['channel'];
			$amount = (float)$item['amount'];
			$rowData = [
				'id' => $baseId + $index,
				'description' => 'Outstanding reconciliation e-cash payment for ' . $channel,
				'amount' => $amount,
				'network' => 'E-CASH',
				'payment_type' => $channel,
				'pay_to' => $receiverId,
				'cashdrawer_id' => $drawerId,
				'drawer_user_id' => $drawerUserId,
				'denos_json' => '[]',
				'transaction_status' => 'Pending',
				'created_by' => $user_id,
				'date_created' => $now,
			];
			$payment = new \BackOffices\Payment;
			$payment->insert($rowData);
			if (!empty($payment->has_error) || !empty($payment->error)) {
				$payment->errors['description'] = 'Failed to create payment for ' . $channel . '. ' . ($payment->error ?? '');
				set_value('old_post', $postdata);
				set_value('mp_restore', 1);
				set_value('errors', $payment->errors);
				return;
			}
			backoffice_whatsapp_notify_transaction([
				'type' => 'Reconciliation Payment',
				'id' => (int)($rowData['id'] ?? 0),
				'status' => 'Pending',
				'creator_id' => $user_id,
				'assigned_id' => $receiverId,
				'details' => [
					'Channel' => $channel,
					'Amount' => 'GHS ' . number_format($amount, 2),
				],
				'view_url' => ROOT . '/admin/back_office/view_payment/' . (int)($rowData['id'] ?? 0),
			]);
			$inserted++;
		}

		$log = new \BackOffices\Log;
		$log->insert([
			'table_name' => 'payments',
			'action' => 'added',
			'user_id' => $user_id,
			'user_name' => $username,
			'description' => $username . ' added ' . $inserted . ' reconciliation e-cash payment request(s).',
			'date_created' => $now,
		]);

		set_value('old_post', []);
		set_value('errors', []);
		set_value('mp_restore', 0);
		set_value('mp_clear_draft', 1);
		message_success($inserted . ' outstanding reconciliation e-cash payment request(s) submitted successfully.');
		redirect($admin_route.'/'.$plugin_route);
		exit;
	}

	if($csrf && $payment->validate_insert($postdata))
	{
		if ($isDynamicBulkPayment) {
			if (empty($dynamicBulkBreakdown['items'])) {
				$payment->errors['amount'] = 'Enter at least one ' . $networkOption . ' payment amount.';
			}
			if (!empty($dynamicBulkBreakdown['errors'])) {
				$payment->errors['amount'] = implode(' ', $dynamicBulkBreakdown['errors']);
			}
		}
		if (!$isCashNetwork && !$isDynamicBulkPayment && empty($postdata['payment_type'])) {
			$payment->errors['payment_type'] = 'Payment type is required';
		}
		if (!$isCashNetwork && !$isDynamicBulkPayment && backoffice_payment_is_telecel_type((string)($postdata['payment_type'] ?? '')) && !backoffice_payment_amount_is_whole_number($amountValue)) {
			$payment->errors['amount'] = 'Telecel payments must use whole-number amounts only.';
		}
		if(!empty($payment->errors)){
			set_value('old_post', $postdata);
			set_value('mp_restore', 1);
			set_value('errors',$payment->errors);
			return;
		}
		else if(user_can('add_payment'))
		{
			$postdata['date_created'] = date("Y-m-d H:i:s");
			if (empty($postdata['id'])) {
				$postdata['id'] = time();
			}
			$session = get_session();

			if(!empty($session->id)){
				$username = $session->first_name . ' '. $session->last_name;
				$user_id = $session->id;
			}else{
				$username = $row->first_name . ' '. $row->last_name;
				$user_id = $row->id;
			}

			$payerDrawer = getActiveDrawer((int)$user_id);
			if (empty($payerDrawer)) {
				$payment->errors['network'] = 'Open your cashdrawer for today before submitting this payment.';
				set_value('old_post', $postdata);
				set_value('mp_restore', 1);
				set_value('errors', $payment->errors);
				return;
			}

			$drawerUserId = (int)($payerDrawer['drawer_user_id'] ?? $user_id);
			$drawerId = (int)($payerDrawer['drawer_id'] ?? 0);
			if ($drawerId <= 0 || $drawerUserId <= 0) {
				$payment->errors['network'] = 'Your active cashdrawer is missing valid drawer details. Reopen your cashdrawer and try again.';
				set_value('old_post', $postdata);
				set_value('mp_restore', 1);
				set_value('errors', $payment->errors);
				return;
			}
			if ($isCashNetwork) {
				$cashCurrency = strtoupper(trim((string)($postdata['payment_type'] ?? 'GHS')));
				if (!in_array($cashCurrency, ['GHS', 'CFA'], true)) {
					$cashCurrency = 'GHS';
				}

				$balanceCheck = checkCashDenominationSufficiencyCurrency($drawerUserId, $denosPayload, $amountValue, $cashCurrency);
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
					$payment->errors['denos_json'] = "Insufficient {$cur} cashdrawer balance. Required: {$cur} {$required}, available: {$cur} {$available}.{$details}";
					set_value('old_post', $postdata);
					set_value('mp_restore', 1);
					set_value('errors', $payment->errors);
					return;
				}

				$countsCheck = checkCashDenominationCountSufficiencyCurrency($drawerUserId, $denosPayload, $amountValue, $cashCurrency);
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
					$payment->errors['denos_json'] = $msg;
					set_value('old_post', $postdata);
					set_value('mp_restore', 1);
					set_value('errors', $payment->errors);
					return;
				}
			} else if ($isDynamicBulkPayment) {
				$insufficientMessages = [];
				foreach ($dynamicBulkBreakdown['items'] as $bulkItem) {
					$ecashChannelKey = backoffice_payment_resolved_channel_key((string)($bulkItem['channel'] ?? ''));
					$itemAmount = (float)($bulkItem['amount'] ?? 0);
					if ($ecashChannelKey === '' || $itemAmount <= 0) {
						continue;
					}
					$ecashCheck = checkEcashChannelSufficiency($drawerUserId, $ecashChannelKey, $itemAmount);
					if (empty($ecashCheck['ok'])) {
						$required = number_format((float)($ecashCheck['required'] ?? 0), 2);
						$available = number_format((float)($ecashCheck['available'] ?? 0), 2);
						$insufficientMessages[] = "{$ecashChannelKey}: required {$required}, available {$available}";
					}
				}
				if (!empty($insufficientMessages)) {
					$payment->errors['amount'] = "Insufficient E-Cash balance:\n" . implode("\n", $insufficientMessages);
					set_value('old_post', $postdata);
					set_value('mp_restore', 1);
					set_value('errors', $payment->errors);
					return;
				}
			} else {
				$providerRaw = strtoupper((string)($postdata['payment_type'] ?? ''));
				if ($providerRaw !== '' && $amountValue > 0) {
					$ecashChannelKey = backoffice_payment_resolved_channel_key($providerRaw);
					$ecashCheck = checkEcashChannelSufficiency($drawerUserId, $ecashChannelKey, $amountValue);
					if (empty($ecashCheck['ok'])) {
						$required = number_format((float)($ecashCheck['required'] ?? 0), 2);
						$available = number_format((float)($ecashCheck['available'] ?? 0), 2);
						$payment->errors['amount'] = "Insufficient E-Cash balance on {$ecashChannelKey}. Required: {$required}, available: {$available}.";
						set_value('old_post', $postdata);
						set_value('mp_restore', 1);
						set_value('errors', $payment->errors);
						return;
					}
				}
			}

			$postdata['created_by'] = $user_id;
			$postdata['cashdrawer_id'] = $drawerId;
			$postdata['drawer_user_id'] = $drawerUserId;
			$postdata['denos_json'] = $isCashNetwork ? json_encode($denosPayload) : '[]';
			if (!$isDynamicBulkPayment) {
				$postdata['payment_breakdown_json'] = null;
			}
			$receiverId = (int)($postdata['pay_to'] ?? 0);
			if ($receiverId <= 0) {
				$payment->errors['pay_to'] = 'Assigned to is required';
				set_value('old_post', $postdata);
				set_value('mp_restore', 1);
				set_value('errors', $payment->errors);
				return;
			}

			// Separation of duties: creator cannot approve at creation time.
			// Always force to Pending and let only the assigned/pay_to user approve via payment_action endpoint.
			$postdata['transaction_status'] = 'Pending';
			unset($postdata['approved_by']);
			unset($postdata['approved_at']);
			$payment->insert($postdata);
			$posted = empty($payment->has_error);
			if (!$posted && !empty($payment->error)) {
				error_log('Backoffice payment insert failed: ' . $payment->error);
			}
			if ($posted) {
				backoffice_whatsapp_notify_transaction([
					'type' => 'Backoffice Payment',
					'id' => (int)($postdata['id'] ?? 0),
					'status' => $postdata['transaction_status'],
					'creator_id' => (int)$user_id,
					'assigned_id' => $receiverId,
					'details' => [
						'Network' => $postdata['network'] ?? '',
						'Payment type' => $postdata['payment_type'] ?? '',
						'Amount' => 'GHS ' . number_format($amountValue, 2),
						'Description' => $postdata['description'] ?? '',
					],
					'view_url' => ROOT . '/admin/back_office/view_payment/' . (int)($postdata['id'] ?? 0),
				]);
			}

			$paymentId = (int)($postdata['id'] ?? 0);
			if ($paymentId <= 0) {
				$paymentId = time();
				$postdata['id'] = $paymentId;
			}

			//log changes in the log table
			$action 		= 'added';
			$transaction 		= 'outgoing payments ' . $postdata['transaction_status'] ;
			$transaction_id 		= $postdata['id'];
			$table 			= 'payments';
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
 
			// Clear any sticky form state so a fresh Make Payment doesn't restore old inputs
			set_value('old_post', []);
			set_value('errors', []);
			set_value('mp_restore', 0);
			set_value('mp_clear_draft', 1);
			message_success("Transaction ($transaction) added successfully!");
			redirect($admin_route.'/'.$plugin_route);
			exit;
		}
	}

	if(!$csrf)
		$payment->errors['description'] = "Form expired!";

	if (!empty($postdata)) {
		set_value('old_post', $postdata);
		set_value('mp_restore', 1);
		set_value('errors',$payment->errors);
	}

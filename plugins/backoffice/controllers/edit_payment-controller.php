<?php

use function \BackOfficeCashdrawer\calculateTotal;
use function \BackOfficeCashdrawer\getActiveDrawer;
use function \BackOfficeCashdrawer\normalizeDenominations;
use function \BackOfficeCashdrawer\syncCashMovement;
use function \BackOfficeCashdrawer\syncCashMovementCurrency;
use function \BackOfficeCashdrawer\checkCashDenominationSufficiencyCurrency;
use function \BackOfficeCashdrawer\checkCashDenominationCountSufficiencyCurrency;
use function \BackOfficeCashdrawer\checkEcashChannelSufficiency;

if(!empty($row))
{
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$postdata = $req->post();
	$payment = new \BackOffices\Payment;

	$csrf = csrf_verify($postdata);
	$networkOption = strtoupper((string)($row->network ?? $postdata['network'] ?? ''));
	$isCashNetwork = ($networkOption === 'CASH');
	$isDynamicBulkPayment = in_array($networkOption, ['E-CASH', 'EVD'], true);
	$oldStatus = strtolower((string)($row->transaction_status ?? ''));
	$newStatus = strtolower((string)($postdata['transaction_status'] ?? $row->transaction_status ?? ''));
	$isApprovalTransition = ($oldStatus !== 'approved' && $newStatus === 'approved');

	$postdata['amount'] = $postdata['amount'] ?? ($row->amount ?? 0);
	$amountValue = (float)$postdata['amount'];
	$dynamicBulkBreakdown = ['items' => [], 'total' => 0.0, 'errors' => []];
	if ($isDynamicBulkPayment) {
		$dynamicBulkBreakdown = backoffice_payment_normalize_breakdown($postdata['bulk_payment_items'] ?? ($row->payment_breakdown_json ?? '[]'), $networkOption);
		if (!empty($dynamicBulkBreakdown['items'])) {
			$amountValue = (float)$dynamicBulkBreakdown['total'];
			$postdata['amount'] = number_format($amountValue, 2, '.', '');
			$postdata['payment_type'] = 'BULK';
			$postdata['payment_breakdown_json'] = json_encode($dynamicBulkBreakdown['items']);
		}
	}
	$rawDenos = $postdata['denos_json'] ?? ($postdata['denos'] ?? ($row->denos_json ?? '[]'));
	$denosPayload = [];

	if (!$isCashNetwork) {
		$postdata['denos_json'] = '[]';
		if ($isApprovalTransition && empty($postdata['payment_type']) && empty($row->payment_type)) {
			$payment->errors['payment_type'] = 'Payment type is required';
		}
	}

	if ($isCashNetwork) {
		$cashCurrencyForDenos = strtoupper(trim((string)($row->payment_type ?? $postdata['payment_type'] ?? 'GHS')));
		if (!in_array($cashCurrencyForDenos, ['GHS', 'CFA'], true)) {
			$cashCurrencyForDenos = 'GHS';
		}
		$denosPayload = normalizeDenominations($rawDenos, $amountValue, $cashCurrencyForDenos);
		$denosTotal = calculateTotal($denosPayload);
		if (empty($denosPayload)) {
			$payment->errors['denos_json'] = 'Capture the cash denominations before approving.';
		}

		if ($denosTotal > 0 && abs($denosTotal - $amountValue) > 0.01) {
			$payment->errors['denos_json'] = 'Calculator total must match payment amount.';
		}
	} else {
		$postdata['denos_json'] = '[]';
	}

	if($csrf && $payment->validate_update($postdata))
	{
		if ($isDynamicBulkPayment) {
			if (empty($dynamicBulkBreakdown['items'])) {
				$payment->errors['amount'] = 'Enter at least one ' . $networkOption . ' payment amount.';
			}
			if (!empty($dynamicBulkBreakdown['errors'])) {
				$payment->errors['amount'] = implode(' ', $dynamicBulkBreakdown['errors']);
			}
		}
		if (!$isCashNetwork && !$isDynamicBulkPayment && backoffice_payment_is_telecel_type((string)($row->payment_type ?? $postdata['payment_type'] ?? '')) && !backoffice_payment_amount_is_whole_number($amountValue)) {
			$payment->errors['amount'] = 'Telecel payments must use whole-number amounts only.';
		}
		if(!empty($payment->errors)){
			set_value('old_post', $postdata);
			set_value('errors',$payment->errors);
			return;
		}

		if(user_can('edit_payment'))
		{
			$postdata['date_updated'] = date("Y-m-d H:i:s");

			$session = get_session();

			if(!empty($session->id)){
				$username = $session->first_name . ' '. $session->last_name;
				$user_id = $session->id;
			}else{
				$username = $row->first_name . ' '. $row->last_name;
				$user_id = $row->id;
			}

			$postdata['updated_by'] = $user_id;
			if ($isApprovalTransition) {
				$postdata['approved_by'] = $user_id;
				$postdata['approved_at'] = date('Y-m-d H:i:s');
			}
			if ($isCashNetwork) {
				if (!empty($denosPayload)) {
					$postdata['denos_json'] = json_encode($denosPayload);
				}
			} else {
				$postdata['denos_json'] = '[]';
			}

			if ($isApprovalTransition) {
				$payerId = (int)($row->created_by ?? 0);
				if ($payerId <= 0) {
					set_value('old_post', $postdata);
					message_fail('Payment creator not found. Cannot approve.');
					redirect($admin_route.'/'.$plugin_route);
					exit;
				}
				$payerDrawer = getActiveDrawer($payerId);
				if (empty($payerDrawer)) {
					set_value('old_post', $postdata);
					message_fail('The payment creator must open a cashdrawer before approval.');
					redirect($admin_route.'/'.$plugin_route);
					exit;
				}
				$drawerUserId = (int)($payerDrawer['drawer_user_id'] ?? $payerId);
				$networkOption = strtoupper((string)($row->network ?? $postdata['network'] ?? ''));
				$isCashNetwork = ($networkOption === 'CASH');
				if ($isCashNetwork) {
					$cashCurrency = strtoupper(trim((string)($row->payment_type ?? $postdata['payment_type'] ?? 'GHS')));
					if (!in_array($cashCurrency, ['GHS', 'CFA'], true)) {
						$cashCurrency = 'GHS';
					}
					$balanceCheck = checkCashDenominationSufficiencyCurrency($drawerUserId, $denosPayload, $amountValue, $cashCurrency);
					if (empty($balanceCheck['ok'])) {
						$cur = strtoupper((string)($balanceCheck['currency'] ?? $cashCurrency));
						$required = number_format((float)($balanceCheck['required'] ?? 0), ($cur === 'CFA') ? 0 : 2);
						$available = number_format((float)($balanceCheck['available'] ?? 0), ($cur === 'CFA') ? 0 : 2);
						set_value('old_post', $postdata);
						message_fail("Insufficient {$cur} cashdrawer balance. Required: {$cur} {$required}, available: {$cur} {$available}.");
						redirect($admin_route.'/'.$plugin_route);
						exit;
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
						set_value('old_post', $postdata);
						message_fail($msg);
						redirect($admin_route.'/'.$plugin_route);
						exit;
					}
				} else if ($isDynamicBulkPayment) {
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
							set_value('old_post', $postdata);
							message_fail("Insufficient E-Cash balance on {$ecashChannelKey}. Required: {$required}, available: {$available}.");
							redirect($admin_route.'/'.$plugin_route);
							exit;
						}
					}
				} else {
					$providerRaw = strtoupper((string)($row->payment_type ?? $postdata['payment_type'] ?? ''));
					if ($providerRaw !== '' && $amountValue > 0) {
						if (backoffice_payment_is_telecel_type($providerRaw) && !backoffice_payment_amount_is_whole_number($amountValue)) {
							set_value('old_post', $postdata);
							message_fail('Telecel payments must use whole-number amounts only.');
							redirect($admin_route.'/'.$plugin_route);
							exit;
						}
						$ecashChannelKey = backoffice_payment_resolved_channel_key($providerRaw);
						$ecashCheck = checkEcashChannelSufficiency($drawerUserId, $ecashChannelKey, $amountValue);
						if (empty($ecashCheck['ok'])) {
							$required = number_format((float)($ecashCheck['required'] ?? 0), 2);
							$available = number_format((float)($ecashCheck['available'] ?? 0), 2);
							set_value('old_post', $postdata);
							message_fail("Insufficient E-Cash balance on {$ecashChannelKey}. Required: {$required}, available: {$available}.");
							redirect($admin_route.'/'.$plugin_route);
							exit;
						}
					}
				}
				$postdata['cashdrawer_id'] = $payerDrawer['drawer_id'] ?? null;
				$postdata['drawer_user_id'] = $drawerUserId;
				$postdata['drawer_user_id'] = $payerDrawer['drawer_user_id'] ?? null;
			}
			$payment->update($row->id,$postdata);

			$assignedUserId = (int)($postdata['pay_to'] ?? $row->pay_to ?? 0);
			backoffice_whatsapp_notify_creator_on_assignee_update([
				'type' => 'Backoffice Payment',
				'id' => (int)$row->id,
				'status' => $postdata['transaction_status'] ?? ($row->transaction_status ?? ''),
				'creator_id' => (int)($row->created_by ?? 0),
				'assigned_id' => $assignedUserId,
				'details' => [
					'Network' => $postdata['network'] ?? ($row->network ?? ''),
					'Payment type' => $postdata['payment_type'] ?? ($row->payment_type ?? ''),
					'Amount' => 'GHS ' . number_format($amountValue, 2),
					'Description' => $postdata['description'] ?? ($row->description ?? ''),
				],
				'view_url' => ROOT . '/admin/back_office/view_payment/' . (int)$row->id,
			], $user_id);

			if ($isApprovalTransition) {
				$payerId = (int)($row->created_by ?? 0);
				$receiverId = (int)($row->pay_to ?? $postdata['pay_to'] ?? 0);
				$networkOption = strtoupper((string)($row->network ?? $postdata['network'] ?? ''));
				$isCashNetwork = ($networkOption === 'CASH');
				if ($payerId > 0) {
					if ($isCashNetwork) {
						$cashCurrency = strtoupper(trim((string)($row->payment_type ?? $postdata['payment_type'] ?? 'GHS')));
						if (!in_array($cashCurrency, ['GHS', 'CFA'], true)) {
							$cashCurrency = 'GHS';
						}
						syncCashMovementCurrency($payerId, $denosPayload, $amountValue, 'Approved', $row->id, 'out', $cashCurrency);
						if ($receiverId > 0) {
							$receiverDrawer = getActiveDrawer($receiverId);
							if (!empty($receiverDrawer)) {
								syncCashMovementCurrency($receiverId, $denosPayload, $amountValue, 'Approved', $row->id, 'in', $cashCurrency);
							} else {
								error_log('Backoffice edit payment: receiver has no active drawer; skipping receiver cash credit. receiver_id=' . $receiverId);
							}
						}
					} else {
						try {
							$dbBal = new \Core\Database;
							if ($dbBal->table_exists(['drawer_balances'])) {
								$ecashAdjustments = [];
								if (!empty($dynamicBulkBreakdown['items'])) {
									foreach ($dynamicBulkBreakdown['items'] as $bulkItem) {
										$channelKey = backoffice_payment_resolved_channel_key((string)($bulkItem['channel'] ?? ''));
										$itemAmount = (float)($bulkItem['amount'] ?? 0);
										if ($channelKey !== '' && $itemAmount > 0) {
											$ecashAdjustments[] = ['channel' => $channelKey, 'amount' => $itemAmount];
										}
									}
								} else {
									$providerRaw = strtoupper((string)($row->payment_type ?? $postdata['payment_type'] ?? ''));
									if ($providerRaw !== '') {
										$ecashAdjustments[] = ['channel' => backoffice_payment_resolved_channel_key($providerRaw), 'amount' => $amountValue];
									}
								}
								if (empty($ecashAdjustments)) {
									error_log('Backoffice edit payment: missing payment_type for ecash sync. payment_id=' . (int)($row->id ?? 0));
									return;
								}

								$transId = (int)($row->id ?? 0);
								$transStatus = 'Approved';
								$adjustEcash = function (int $actorUserId, string $direction, string $ecashChannelKey, float $lineAmount) use ($dbBal, $transId, $transStatus) {
									$drawerContext = getActiveDrawer($actorUserId);
									if (empty($drawerContext)) {
										return;
									}
									$drawerId = (int)($drawerContext['drawer_id'] ?? 0);
									$drawerUserId = (int)($drawerContext['drawer_user_id'] ?? $actorUserId);
									if ($drawerId <= 0) {
										return;
									}
									$resolvedChannelKey = \BackOfficeCashdrawer\resolveExistingEcashChannelKey($drawerId, $drawerUserId, $ecashChannelKey);
									$rowsEcash = $dbBal->query(
										"SELECT * FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash' AND (channel_key = :channel_key OR channel_key = :ecash_channel_key) LIMIT 1",
										[
											'drawer_id' => $drawerId,
											'user_id' => $drawerUserId,
											'channel_key' => $resolvedChannelKey,
											'ecash_channel_key' => 'ECASH_' . $resolvedChannelKey,
										]
									);

									$now = date('Y-m-d H:i:s');
									$creditDelta = (strtolower($direction) === 'in') ? $lineAmount : 0.0;
									$debitDelta  = (strtolower($direction) === 'out') ? $lineAmount : 0.0;
									if (empty($rowsEcash)) {
										$opening = 0.0;
										$closing = $opening + $creditDelta - $debitDelta;
										$dbBal->query(
											"INSERT INTO drawer_balances (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status) VALUES (:drawer_id, :user_id, 'ecash', :channel_key, :opening, :credit, :debit, :closing, :date_created, :trans_id, :trans_status)",
											[
												'drawer_id' => $drawerId,
												'user_id' => $drawerUserId,
												'channel_key' => $resolvedChannelKey,
												'opening' => $opening,
												'credit' => $creditDelta,
												'debit' => $debitDelta,
												'closing' => $closing,
												'date_created' => $now,
												'trans_id' => $transId,
												'trans_status' => $transStatus,
											]
										);
									} else {
										$rowEcash = $rowsEcash[0];
										$opening = (float)($rowEcash->opening_balance ?? 0);
										$credit  = (float)($rowEcash->credit ?? 0) + $creditDelta;
										$debit   = (float)($rowEcash->debit ?? 0) + $debitDelta;
										$closing = $opening + $credit - $debit;
										$dbBal->query(
											"UPDATE drawer_balances SET channel_key = :channel_key, credit = :credit, debit = :debit, closing_balance = :closing, trans_id = :trans_id, trans_status = :trans_status WHERE id = :id",
											[
												'channel_key' => $resolvedChannelKey,
												'credit' => $credit,
												'debit' => $debit,
												'closing' => $closing,
												'trans_id' => $transId,
												'trans_status' => $transStatus,
												'id' => $rowEcash->id,
											]
										);
									}
								};
								foreach ($ecashAdjustments as $adj) {
									$adjustEcash($payerId, 'out', (string)$adj['channel'], (float)$adj['amount']);
									if ($receiverId > 0) {
										$receiverDrawer = getActiveDrawer($receiverId);
										if (!empty($receiverDrawer)) {
											$adjustEcash($receiverId, 'in', (string)$adj['channel'], (float)$adj['amount']);
										} else {
											error_log('Backoffice edit payment: receiver has no active drawer; skipping receiver ecash credit. receiver_id=' . $receiverId);
										}
									} else {
									}
								}
							}
						} catch (\Throwable $e) {
							error_log('Backoffice edit payment ecash sync error: ' . $e->getMessage());
						}
					}
				}
			}

           	//log changes in the log table
			$action 		= 'updated';
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
 
			message_success("Transaction ($transaction) updated successfully!");
			redirect($admin_route.'/'.$plugin_route);
		}
	}

	if(!$csrf)
		$payment->errors['description'] = "Form expired!";
	
	set_value('errors',$payment->errors);

}else{

	message_fail("Record not found");
}

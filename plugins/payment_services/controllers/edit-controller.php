<?php

if (!empty($row)) {
	date_default_timezone_set('Africa/Accra');
	payment_service_ensure_runtime_columns();

	$postdata = $req->post();
	$postdata['id'] = $row->id;
	$postdata['amount_paid'] = isset($postdata['amount_paid']) && $postdata['amount_paid'] !== '' ? number_format((float)$postdata['amount_paid'], 2, '.', '') : number_format((float)($row->amount_paid ?? 0), 2, '.', '');
	$postdata['change_required'] = isset($postdata['change_required']) && $postdata['change_required'] !== '' ? number_format((float)$postdata['change_required'], 2, '.', '') : number_format((float)($row->change_required ?? 0), 2, '.', '');
	$postdata['cash_received_amount'] = isset($postdata['cash_received_amount']) && $postdata['cash_received_amount'] !== '' ? number_format((float)$postdata['cash_received_amount'], 2, '.', '') : number_format((float)($row->cash_received_amount ?? 0), 2, '.', '');
	$assignedToRaw = trim((string)($postdata['assigned_to'] ?? ($row->assigned_to ?? '')));
	$postdata['assigned_to'] = ($assignedToRaw !== '' && is_numeric($assignedToRaw)) ? (int)$assignedToRaw : 0;

	$previousStatus = payment_service_normalize_status($row->transaction_status ?? '', 'Pending');
	$isApprovedLocked = payment_service_status_is_approved($previousStatus);
	$isAlreadyDeclined = payment_service_status_is_declined($previousStatus);

	$csrf = csrf_verify($postdata);
	if ($csrf) {
		if (user_can('edit_payment_service')) {
			$session = get_session();
			if (!empty($session->id)) {
				$username = trim(($session->first_name ?? '') . ' ' . ($session->last_name ?? ''));
				$user_id = (int)$session->id;
			} else {
				$username = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
				$user_id = (int)($row->id ?? 0);
			}

			$dateUpdated = date("Y-m-d H:i:s");
			$normalizedStatus = payment_service_normalize_status($postdata['transaction_status'] ?? ($row->transaction_status ?? ''), $row->transaction_status ?? 'Pending');
			$rejectionReason = trim((string)($postdata['rejection_reason'] ?? ''));

			$storeErrors = function () use ($payment_service, $req) {
				set_value('errors', array_merge($payment_service->errors, $req->upload_errors));
			};

			$applyApprovalEffects = function (array $data) use ($row, $user_id, $payment_service, $req): ?string {
				$drawer_id = 0;
				$drawerOwnerId = 0;
				$medium = strtolower(trim((string)($data['payment_medium'] ?? $row->payment_medium ?? '')));
				$assignedTo = (int)($data['assigned_to'] ?? $row->assigned_to ?? 0);

				$drawerOwnerId = (int)($row->created_by ?? $user_id ?? 0);
				if ($drawerOwnerId > 0) {
					try {
						$drawerDb = new \Core\Database;
						$today = date('Y-m-d');
						$drawerRow = $drawerDb->query(
							"SELECT id FROM cashdrawers WHERE user_id = :user_id AND DATE(date_created) = :today ORDER BY id DESC LIMIT 1",
							['user_id' => $drawerOwnerId, 'today' => $today]
						);
						if (empty($drawerRow)) {
							$drawerRow = $drawerDb->query(
								"SELECT id FROM cashdrawers WHERE user_id = :user_id ORDER BY date_created DESC, id DESC LIMIT 1",
								['user_id' => $drawerOwnerId]
							);
						}
						if (!empty($drawerRow)) {
							$drawer_id = (int)$drawerRow[0]->id;
						}
					} catch (\Throwable $e) {
						$drawer_id = 0;
					}
				}

				if ($drawer_id > 0) {
					try {
						$dbBal = new \Core\Database;
						if ($dbBal->table_exists(['drawer_balances'])) {
							$drawerUserId = $drawerOwnerId;
							try {
								$userRow = $dbBal->query("SELECT user_id FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawer_id]);
								if (!empty($userRow)) {
									$drawerUserId = (int)($userRow[0]->user_id ?? $drawerOwnerId);
								}
							} catch (\Throwable $e) {
							}

							$transactionAmount = (float)($data['amount'] ?? $row->amount ?? 0);
							if ($transactionAmount > 0) {
								$rowsBal = $dbBal->query(
									"SELECT * FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'cash' AND channel_key = 'CASH_DRAWER' LIMIT 1",
									['drawer_id' => $drawer_id, 'user_id' => $drawerUserId]
								);
								$now = date('Y-m-d H:i:s');
								if (empty($rowsBal)) {
									$hdr = $dbBal->query("SELECT amount FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawer_id]);
									$opening = !empty($hdr) ? (float)($hdr[0]->amount ?? 0) : 0.0;
									$dbBal->query(
										"INSERT INTO drawer_balances (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status)
										 VALUES (:drawer_id, :user_id, 'cash', 'CASH_DRAWER', :opening, :credit, :debit, :closing, :date_created, :trans_id, :trans_status)",
										[
											'drawer_id' => $drawer_id,
											'user_id' => $drawerUserId,
											'opening' => $opening,
											'credit' => 0,
											'debit' => $transactionAmount,
											'closing' => $opening - $transactionAmount,
											'date_created' => $now,
											'trans_id' => $row->id,
											'trans_status' => 'Approved',
										]
									);
								} else {
									$rowBal = $rowsBal[0];
									$debit = (float)($rowBal->debit ?? 0) + $transactionAmount;
									$closing = (float)($rowBal->opening_balance ?? 0) + (float)($rowBal->credit ?? 0) - $debit;
									$dbBal->query(
										"UPDATE drawer_balances SET debit = :debit, closing_balance = :closing, date_updated = :updated WHERE id = :id",
										['debit' => $debit, 'closing' => $closing, 'updated' => $now, 'id' => $rowBal->id]
									);
								}
							}
						}
					} catch (\Throwable $e) {
					}
				}

				$channelName = $medium === 'bank'
					? trim((string)($data['bank'] ?? $row->bank ?? ''))
					: trim((string)($data['network'] ?? $row->network ?? ''));
				$channelOwnerId = ($medium === 'bank' && $assignedTo > 0) ? $assignedTo : $drawerOwnerId;

				if ($channelOwnerId > 0 && $channelName !== '') {
					try {
						$dbBal = new \Core\Database;
						if ($dbBal->table_exists(['drawer_balances'])) {
							// Resolved channel drawer and balance row
							$resolvedBalanceDrawerId = 0;
							$resolvedBalanceDrawerUserId = 0;
							$resolvedBalanceKey = null;
							$rowsEcash = [];
							
							// Use the exact channel name from the form for the balance lookup.
							$channelBase = trim($channelName);
							$channelUpper = strtoupper($channelBase);
							$requestedBalanceKey = $medium === 'bank'
								? ($channelUpper === 'MTN' || $channelUpper === 'TELECEL' || $channelUpper === 'AT'
									? 'ECASH_' . $channelUpper . '_BANK'
									: $channelBase . '_BANK')
								: ($channelUpper === 'MTN' || $channelUpper === 'TELECEL' || $channelUpper === 'AT'
									? 'ECASH_' . $channelUpper
									: $channelBase);
							$ecashChannelKey = $requestedBalanceKey;
							
							// Get drawer IDs for owner
							$channelDrawerId = 0;
							try {
								$drawerDb2 = new \Core\Database;
								$today2 = date('Y-m-d');
								$drawerRow2 = $drawerDb2->query(
									"SELECT id FROM cashdrawers WHERE user_id = :user_id AND DATE(date_created) = :today ORDER BY id DESC LIMIT 1",
									['user_id' => $channelOwnerId, 'today' => $today2]
								);
								if (empty($drawerRow2)) {
									$drawerRow2 = $drawerDb2->query(
										"SELECT id FROM cashdrawers WHERE user_id = :user_id ORDER BY date_created DESC, id DESC LIMIT 1",
										['user_id' => $channelOwnerId]
									);
								}
								if (!empty($drawerRow2)) {
									$channelDrawerId = (int)($drawerRow2[0]->id ?? 0);
								}
							} catch (\Throwable $e) {
								$channelDrawerId = 0;
							}
							
							if ($channelDrawerId <= 0) {
								return 'Cashdrawer not found for assigned user, so bank/e-cash balance cannot be validated.';
							}
							
							$drawerUserId = $channelOwnerId;
							try {
								$userRow = $dbBal->query("SELECT user_id FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $channelDrawerId]);
								if (!empty($userRow)) {
									$drawerUserId = (int)($userRow[0]->user_id ?? $channelOwnerId);
								}
							} catch (\Throwable $e) {}
							
							// Search for existing balance row across candidate owners
							$drawerCandidateOwners = array_values(array_unique(array_filter([
								$channelOwnerId,
								$user_id,
								(int)($row->created_by ?? 0),
								(int)($data['assigned_to'] ?? $row->assigned_to ?? 0),
							], static function ($value) {
								return (int)$value > 0;
							})));
							
							foreach ($drawerCandidateOwners as $candidateOwnerId) {
								$candidateDrawerId = 0;
								try {
									$candidateDrawerDb = new \Core\Database;
									$candidateToday = date('Y-m-d');
									$candidateDrawerRow = $candidateDrawerDb->query(
										"SELECT id FROM cashdrawers WHERE user_id = :user_id AND DATE(date_created) = :today ORDER BY id DESC LIMIT 1",
										['user_id' => $candidateOwnerId, 'today' => $candidateToday]
									);
									if (empty($candidateDrawerRow)) {
										$candidateDrawerRow = $candidateDrawerDb->query(
											"SELECT id FROM cashdrawers WHERE user_id = :user_id ORDER BY date_created DESC, id DESC LIMIT 1",
											['user_id' => $candidateOwnerId]
										);
									}
									if (!empty($candidateDrawerRow)) {
										$candidateDrawerId = (int)($candidateDrawerRow[0]->id ?? 0);
									}
								} catch (\Throwable $e) {
									$candidateDrawerId = 0;
								}
								
								if ($candidateDrawerId <= 0) {
									continue;
								}
								
								$candidateDrawerUserId = $candidateOwnerId;
								try {
									$candidateUserRow = $dbBal->query("SELECT user_id FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $candidateDrawerId]);
									if (!empty($candidateUserRow)) {
										$candidateDrawerUserId = (int)($candidateUserRow[0]->user_id ?? $candidateOwnerId);
									}
								} catch (\Throwable $e) {}
								
								$candidateChannelKey = payment_service_resolve_channel_key($candidateDrawerId, $candidateDrawerUserId, $channelName, $medium);
								if ($candidateChannelKey === null) {
									continue;
								}

								$rowsEcash = $dbBal->query(
									"SELECT * FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_key = :channel_key ORDER BY id DESC LIMIT 1",
									['drawer_id' => $candidateDrawerId, 'user_id' => $candidateDrawerUserId, 'channel_key' => $candidateChannelKey]
								);
								if (!empty($rowsEcash)) {
									$resolvedBalanceDrawerId = $candidateDrawerId;
									$resolvedBalanceDrawerUserId = $candidateDrawerUserId;
									$resolvedBalanceKey = (string)$candidateChannelKey;
									$ecashChannelKey = $resolvedBalanceKey;
									break;
								}
							}
							
							// Validate balance and apply transaction
							$transactionAmount = (float)($data['amount'] ?? $row->amount ?? 0);
							$currentBalance = 0.0;
							
							if (!empty($rowsEcash)) {
								$currentBalance = (float)($rowsEcash[0]->closing_balance ?? 0);
							}
							
							// If no balance row found or insufficient balance, error
							if (empty($rowsEcash)) {
								return 'Insufficient e-cash/bank balance in channel ' . $ecashChannelKey . ' (available 0.00, required ' . number_format($transactionAmount, 2) . ')';
							}
							
							$projected = $currentBalance - $transactionAmount;
							if ($transactionAmount > 0 && $projected < -0.00001) {
								return 'Insufficient e-cash/bank balance in channel ' . $ecashChannelKey . ' (available ' . number_format($currentBalance, 2) . ', required ' . number_format($transactionAmount, 2) . ')';
							}
							
							// Update existing balance row
							$ecashCredit = 0.0;
							$ecashDebit = $transactionAmount;
							$now = date('Y-m-d H:i:s');
							$rowEcash = $rowsEcash[0];
							$ecashOpening = (float)($rowEcash->opening_balance ?? 0);
							$ecashCredit = (float)($rowEcash->credit ?? 0) + $ecashCredit;
							$ecashDebit = (float)($rowEcash->debit ?? 0) + $ecashDebit;
							$ecashClosing = $ecashOpening + $ecashCredit - $ecashDebit;
							$dbBal->query(
								"UPDATE drawer_balances SET channel_key = :channel_key, credit = :credit, debit = :debit, closing_balance = :closing WHERE id = :id",
								[
									'channel_key' => $ecashChannelKey,
									'credit' => $ecashCredit,
									'debit' => $ecashDebit,
									'closing' => $ecashClosing,
									'id' => $rowEcash->id,
								]
							);
						}
					} catch (\Throwable $e) {
					}
				}
			};

$uploadTransactionImage = function () use (&$postdata, $payment_service, $req, $storeErrors): bool {
	try {
		$req->upload_folder = 'uploads/payment_services';
		$uploadedImage = $req->upload_files('transaction_image');
		if (!empty($uploadedImage)) {
			$postdata['transaction_image'] = $uploadedImage;
		}
	} catch (\Throwable $e) {
		$payment_service->errors['transaction_image'] = 'Unable to upload transaction image.';
		$storeErrors();
		return false;
	}

	return true;
};

$upsertChargeCommission = function (array $data) use ($row, $user_id, $dateUpdated) {
				$chargeValue = (float)($data['charge'] ?? ($row->charge ?? 0));
				try {
					$commissionDb = new \Core\Database;
					$mediumLabel = trim((string)($data['payment_medium'] ?? ($row->payment_medium ?? '')));
					$chargeNetwork = ($mediumLabel !== '' && strtolower($mediumLabel) === 'bank')
						? trim((string)($data['bank'] ?? ($row->bank ?? '')))
						: trim((string)($data['network'] ?? ($row->network ?? '')));
					$chargeNetwork = $chargeNetwork !== '' ? $chargeNetwork : $mediumLabel;
					$commissionDescription = 'PAYMENT SERVICES CHARGE | ' . ($data['category'] ?? ($row->category ?? '')) . ' | ' . ($data['institution'] ?? ($row->institution ?? '')) . ' | ID ' . ($row->id ?? '');

					$commissionDb->query(
						"DELETE FROM commissions WHERE commission_type = :commission_type AND description LIKE :needle",
						[
							'commission_type' => 'PAYMENT SERVICES CHARGE',
							'needle' => '%| ID ' . ($row->id ?? ''),
						]
					);

					if ($chargeValue > 0 && strcasecmp((string)($data['transaction_status'] ?? ''), 'Declined') !== 0) {
						$commissionInsert = [
							'date_received' => $dateUpdated,
							'colletion_period' => 'INSTANT',
							'network' => $chargeNetwork !== '' ? $chargeNetwork : null,
							'commission_type' => 'PAYMENT SERVICES CHARGE',
							'description' => $commissionDescription,
							'pay_to' => $user_id,
							'receiving_wallet' => null,
							'amount' => $chargeValue,
							'transaction_status' => $data['transaction_status'] ?? ($row->transaction_status ?? 'Approved'),
							'created_by' => $user_id,
							'updated_by' => null,
							'disabled' => 0,
							'deleted' => 0,
							'date_created' => $dateUpdated,
							'date_updated' => null,
							'date_deleted' => null,
						];
						$commissionSql = "INSERT INTO commissions (" . implode(',', array_keys($commissionInsert)) . ") VALUES (:" . implode(',:', array_keys($commissionInsert)) . ")";
						$commissionDb->query($commissionSql, $commissionInsert);
					}
				} catch (\Throwable $e) {
					error_log('PAYMENT SERVICES CHARGE COMMISSION UPSERT FAILED (EDIT): ' . $e->getMessage());
				}
			};

			if ($isAlreadyDeclined) {
				$payment_service->errors['transaction_status'] = 'Declined transactions can no longer be edited.';
				$storeErrors();
				return;
			}

			if ($isApprovedLocked) {
				if (strcasecmp($normalizedStatus, 'Declined') !== 0) {
					$payment_service->errors['transaction_status'] = 'Approved transactions can only be changed to Declined.';
					$storeErrors();
					return;
				}

				if ($rejectionReason === '') {
					$payment_service->errors['rejection_reason'] = 'Rejection reason is required when declining a transaction.';
					$storeErrors();
					return;
				}

				$reverseTransaction = clone $row;
				$reverseTransaction->updated_by = $user_id;
				$reverseTransaction->transaction_status = 'Declined';
				$reverseResult = payment_service_reverse_approved_effects($reverseTransaction, 'Declined');
				if (empty($reverseResult['ok'])) {
					$payment_service->errors['transaction_status'] = $reverseResult['message'] ?: 'Unable to reverse the approved transaction.';
					message_fail($payment_service->errors['transaction_status']);
					$storeErrors();
					return;
				}

				$updateData = [
					'transaction_status' => 'Declined',
					'rejection_reason' => $rejectionReason,
					'updated_by' => $user_id,
					'date_updated' => $dateUpdated,
				];
				$payment_service->update($row->id, $updateData);
				if (!empty($payment_service->has_error)) {
					$payment_service->errors['transaction_status'] = !empty($payment_service->error) ? $payment_service->error : 'Unable to save the declined status.';
					$storeErrors();
					return;
				}

				$action = 'declined';
			} else {
				if (!$payment_service->validate_update($postdata)) {
					$storeErrors();
					return;
				}

				if (!$uploadTransactionImage()) {
					return;
				}

				$postdata['transaction_status'] = $normalizedStatus;
				$postdata['rejection_reason'] = strcasecmp($normalizedStatus, 'Declined') === 0 ? $rejectionReason : '';
				$postdata['date_updated'] = $dateUpdated;
				$postdata['updated_by'] = $user_id;

				$statusChangedToApproved = ($normalizedStatus === 'Approved' && $previousStatus !== 'Approved');
				if ($statusChangedToApproved) {
					$approvalError = $applyApprovalEffects($postdata);
					if ($approvalError !== null) {
						$payment_service->errors['amount'] = $approvalError;
						$storeErrors();
						return;
					}
				}

				unset($postdata['id']);
				$payment_service->update($row->id, $postdata);
				if (!empty($payment_service->has_error)) {
					$payment_service->errors['transaction_status'] = !empty($payment_service->error) ? $payment_service->error : 'Unable to save the transaction changes.';
					$storeErrors();
					return;
				}

				$upsertChargeCommission($postdata);
				$action = 'edited';
			}

			$transactionId = $row->id ?? 0;
			$transactionLabel = trim(($postdata['category'] ?? $row->category ?? '') . ' ' . ($postdata['institution'] ?? $row->institution ?? ''));
			$description = $username . ' ' . $action . ' payment service transaction ' . $transactionLabel . ' with id ' . $transactionId;

			$log = new \PaymentService\Log;
			$log->insert([
				'table_name' => 'payment_services',
				'action' => $action,
				'user_id' => $user_id,
				'user_name' => $username,
				'description' => $description,
				'date_created' => $dateUpdated,
			]);

			$message = $action === 'declined'
				? "Transaction with id ($transactionId) declined successfully!"
				: "Transaction with id ($transactionId) edited successfully!";
			message_success($message);
			redirect($admin_route . '/' . $plugin_route);
		}
	}

	if (!$csrf) {
		$payment_service->errors['category'] = "Form expired!";
	}

	set_value('errors', array_merge($payment_service->errors, $req->upload_errors));
} else {
	message_fail("Record not found");
}

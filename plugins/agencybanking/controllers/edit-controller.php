<?php
require_once plugin_path('helpers/cashdrawer-sync.php');

if(!empty($row))
{
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$session = get_session();
	$currentUserId = (int)($session->id ?? 0);
	$rowOwnerId = (int)($row->created_by ?? 0);
	$submittedPost = $req->post();
	$isDirectApproval = strtolower(trim((string)($submittedPost['action'] ?? ''))) === 'approve';
	if ($isDirectApproval) {
		if (!user_can('approve_transactions')) {
			message_fail('You do not have permission to approve transactions.');
			redirect($admin_route.'/'.$plugin_route);
			return;
		}

		$transactionType = strtolower(trim((string)($row->transaction_type ?? '')));
		$status = strtolower(trim((string)($row->transaction_status ?? '')));
		if ($transactionType !== 'account opening' || strpos($status, 'pending') !== 0 || !empty($row->deleted)) {
			message_fail('This account opening is no longer pending approval.');
			redirect($admin_route.'/'.$plugin_route);
			return;
		}
	}
	$canEdit = user_can('edit_agencybanking')
		|| ($currentUserId > 0 && $rowOwnerId === $currentUserId)
		|| ($isDirectApproval && user_can('approve_transactions'));
	if (!$canEdit) {
		message_fail('Access denied');
		redirect($admin_route.'/'.$plugin_route);
	}

	$postdata = $isDirectApproval ? (array)$row : $submittedPost;
	if ($isDirectApproval) {
		$postdata['csrf'] = $submittedPost['csrf'] ?? '';
		$postdata['transaction_status'] = 'Approved';
	}
	$postdata['id'] = $row->id;
	$postdata['transaction_image'] = $postdata['transaction_image'] ?? ($row->transaction_image ?? '');

	$previousStatus = $row->transaction_status ?? '';
	$previousStatusLower = strtolower(trim((string)$previousStatus));
	$newStatusLower = strtolower(trim((string)($postdata['transaction_status'] ?? $previousStatus)));
	if (in_array($newStatusLower, ['reject', 'rejection'], true)) {
		$newStatusLower = 'rejected';
	}
	$wasApprovedOrSent = in_array($previousStatusLower, ['approved','sent'], true);
	$willBeApprovedOrSent = in_array($newStatusLower, ['approved','sent'], true);
	$willBeDeclinedOrRejected = in_array($newStatusLower, ['declined','rejected'], true);
	$statusChangedToApproved = ($willBeApprovedOrSent && !$wasApprovedOrSent);
	$rejectionReason = trim((string)($postdata['rejection_reason'] ?? ''));
	// Normalize persisted status casing to prevent case-sensitive logic bugs elsewhere.
	if ($newStatusLower !== '') {
		$postdata['transaction_status'] = ucfirst($newStatusLower);
	}

	try {
		$dbSchema = new \Core\Database;
		$hasCol = $dbSchema->query(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'agencybankings' AND COLUMN_NAME = 'transaction_image' LIMIT 1",
			['db' => DB_NAME]
		);
		if (empty($hasCol)) {
			$agencybanking->errors['transaction_image'] = 'Database column missing: agencybankings.transaction_image';
			set_value('errors', array_merge($agencybanking->errors, $req->upload_errors));
			return;
		}
	} catch (\Throwable $e) {
		$agencybanking->errors['transaction_image'] = 'Unable to validate transaction image storage.';
		set_value('errors', array_merge($agencybanking->errors, $req->upload_errors));
		return;
	}

	$req->upload_folder = 'uploads/agencybanking';
	$uploadedImage = $req->upload_files('transaction_image');
	if (!empty($uploadedImage)) {
		$postdata['transaction_image'] = $uploadedImage;
	}
	if (empty($postdata['transaction_image'])) {
		$postdata['transaction_image'] = $row->transaction_image ?? '';
	}

	if(!array_key_exists('network', $postdata) || $postdata['network'] === null || $postdata['network'] === ''){
		$postdata['network'] = $row->network ?? '';
	}
	if(!array_key_exists('transaction_type', $postdata) || $postdata['transaction_type'] === null || $postdata['transaction_type'] === ''){
		$postdata['transaction_type'] = $row->transaction_type ?? '';
	}
	$txTypeLimitRaw = trim((string)($postdata['transaction_type'] ?? $row->transaction_type ?? ''));
	if (stripos($txTypeLimitRaw, 'withdrawal') !== false) {
		$postdata['withdrawal_type'] = agencybanking_transaction_withdrawal_type($txTypeLimitRaw, $postdata['withdrawal_type'] ?? '');
	}
	$limitError = agencybanking_validate_transaction_limit($postdata);
	if ($limitError !== null) {
		$agencybanking->errors['amount'] = $limitError;
		set_value('errors', array_merge($agencybanking->errors, $req->upload_errors));
		return;
	}
	$existingTxTypeLimitRaw = trim((string)($row->transaction_type ?? ''));
	$isAitWithdrawal = (strcasecmp($txTypeLimitRaw, 'Withdrawal (AIT)') === 0);
	$wasAitWithdrawal = (strcasecmp($existingTxTypeLimitRaw, 'Withdrawal (AIT)') === 0);
	$amountWithdrawn = (float)($postdata['amount_withdrawn'] ?? $row->amount_withdrawn ?? 0);
	$existingAmountWithdrawn = (float)($row->amount_withdrawn ?? 0);
	$isLegacyOverLimitAitWithdrawal = $wasAitWithdrawal && $existingAmountWithdrawn > 5000;
	$isIncreasingLegacyOverLimitAmount = $amountWithdrawn > ($existingAmountWithdrawn + 0.00001);
	if ($isAitWithdrawal && $amountWithdrawn > 5000 && (!$isLegacyOverLimitAitWithdrawal || $isIncreasingLegacyOverLimitAmount)) {
		$agencybanking->errors['amount_withdrawn'] = 'Maximum allowed amount for Withdrawal (AIT) is GHS 5,000.00. Enter GHS 5,000.00 or less.';
		set_value('errors', array_merge($agencybanking->errors, $req->upload_errors));
		return;
	}

	$txTypeForChannelNormalize = trim((string)($postdata['transaction_type'] ?? ''));
	if ($txTypeForChannelNormalize !== '' && stripos($txTypeForChannelNormalize, 'direct transfer') !== false) {
		if ((!array_key_exists('source_bank', $postdata) || trim((string)$postdata['source_bank']) === '') && !empty($postdata['network'])) {
			$postdata['source_bank'] = $postdata['network'];
		}
	}

	$networkLabel = $postdata['network'] ?? '';
	$transactionTypeLabel = $postdata['transaction_type'] ?? '';

	$denoModel = new \Agencybankings\Deno;
	$currencyDetails = $postdata['denos'] ?? '';
	$submittedCurrencyArray = [];
	if($currencyDetails !== ''){
		$decodedCurrency = json_decode($currencyDetails, true);
		if(is_array($decodedCurrency)){
			foreach($decodedCurrency as $item){
				$denomination = (float)($item['denomination'] ?? 0);
				$count = (int)($item['count'] ?? 0);
				if($denomination <= 0 || $count <= 0){
					continue;
				}
				$volume = (float)($item['volume'] ?? ($denomination * $count));
				$submittedCurrencyArray[] = [
					'currency' => $item['currency'] ?? 'GHS',
					'denomination' => $denomination,
					'count' => $count,
					'volume' => $volume,
				];
			}
		}
	}

	if ($statusChangedToApproved) {
		try {
			$adminManagerDrawers = \Agencybankings\agency_fetch_admin_manager_drawers_today();
			if (empty($adminManagerDrawers)) {
				$agencybanking->errors['network'] = 'No Admin/Manager cashdrawer has been created for today. Please contact a Manager or Admin to create their cashdrawer before approving this transaction.';
				set_value('errors', $agencybanking->errors);
				return;
			}
		} catch (\Throwable $e) {
			$agencybanking->errors['network'] = 'Unable to validate Admin/Manager cashdrawer for today. Please contact a Manager or Admin.';
			set_value('errors', $agencybanking->errors);
			return;
		}
	}

	$csrf = csrf_verify($postdata);

	if ($csrf && $willBeDeclinedOrRejected && $rejectionReason === '') {
		$agencybanking->errors['rejection_reason'] = 'Rejection reason is required when declining or rejecting a transaction.';
		set_value('errors', array_merge($agencybanking->errors, $req->upload_errors));
		return;
	}

	if ($csrf && $wasApprovedOrSent) {
		if (!$willBeDeclinedOrRejected) {
			$agencybanking->errors['transaction_status'] = 'Approved agency banking transactions can only be changed to Declined or Rejected.';
			set_value('errors', array_merge($agencybanking->errors, $req->upload_errors));
			return;
		}

		if (!$agencybanking->validate_update($postdata)) {
			set_value('errors', array_merge($agencybanking->errors, $req->upload_errors));
			return;
		}

		$postdata['date_updated'] = date("Y-m-d H:i:s");
		$session = get_session();
		if(!empty($session->id)){
			$username = $session->first_name . ' '. $session->last_name;
			$user_id = $session->id;
		}else{
			$username = $row->first_name . ' '. $row->last_name;
			$user_id = $row->id;
		}

		$reverseTransaction = clone $row;
		$reverseTransaction->updated_by = $user_id;
		$reverseTransaction->transaction_status = $postdata['transaction_status'];
		$reverseResult = \Agencybankings\agency_reverse_approved_effects($reverseTransaction, (string)$postdata['transaction_status']);
		if (empty($reverseResult['ok'])) {
			$agencybanking->errors['transaction_status'] = $reverseResult['message'] ?: 'Unable to reverse the approved agency banking transaction.';
			message_fail($agencybanking->errors['transaction_status']);
			set_value('errors', array_merge($agencybanking->errors, $req->upload_errors));
			return;
		}

		$updateData = [
			'transaction_status' => $postdata['transaction_status'],
			'rejection_reason' => $rejectionReason,
			'updated_by' => $user_id,
			'date_updated' => $postdata['date_updated'],
		];
		$agencybanking->update($row->id, $updateData);
		if (!empty($agencybanking->has_error)) {
			$dbErr = trim((string)($agencybanking->error ?? ''));
			$agencybanking->errors['transaction_status'] = $dbErr !== '' ? $dbErr : 'Unable to save the declined/rejected status after reversal.';
			set_value('errors', array_merge($agencybanking->errors, $req->upload_errors));
			return;
		}

		$action = strtolower((string)$postdata['transaction_status']);
		$transaction_id = $row->id;
		$table = 'agencybankings';
		$description = "$username $action agencybanking transaction {$row->network} {$row->transaction_type} with id $transaction_id";
		$log = new \Agencybankings\Log;
		$log->insert([
			'table_name'=>$table,
			'action'=>$action,
			'user_id'=>$user_id,
			'user_name'=>$username,
			'description'=>$description,
			'date_created'=>$postdata['date_updated'],
		]);

		message_success("Transaction with id ($transaction_id) {$action} successfully and its cashdrawer impact reversed!");
		redirect($admin_route.'/'.$plugin_route);
	}

	if($csrf && $agencybanking->validate_update($postdata))
	{

			// Enforce availability of eCash/Bank channel balance when the resulting transaction
			// status is Approved/Sent and the transaction will send value out through the channel.
			try{
				if($willBeApprovedOrSent){
					$currencyArrayForChannel = $submittedCurrencyArray;
					$cashAmountForChannel = \Agencybankings\agency_resolve_transaction_amount($postdata, $currencyArrayForChannel);
					$ecashAmountForChannel = \Agencybankings\agency_resolve_ecash_amount($postdata, (float)$cashAmountForChannel);
					$ecashMeta = \Agencybankings\agency_determine_ecash_channel($postdata);
					if(!empty($ecashMeta) && (($ecashMeta['direction'] ?? '') === 'out') && $ecashAmountForChannel > 0){
						$adminManagerDrawers = \Agencybankings\agency_fetch_admin_manager_drawers_today();
						$channelName = trim((string)($ecashMeta['channel_name_raw'] ?? $postdata['network'] ?? ''));
						$minAvailable = null;

						foreach($adminManagerDrawers as $admCtx){
							$drawerIdCtx = (int)($admCtx['drawer_id'] ?? 0);
							$drawerUserIdCtx = (int)($admCtx['drawer_user_id'] ?? 0);
							if($drawerIdCtx <= 0 || $drawerUserIdCtx <= 0){
								continue;
							}
							$channelKey = (string)($ecashMeta['channel_key'] ?? '');
							if($channelKey !== '' && !\Agencybankings\agency_drawer_has_ecash_channel($drawerIdCtx, $drawerUserIdCtx, $channelKey)){
								$resolved = \Agencybankings\agency_resolve_ecash_channel_key($drawerIdCtx, $drawerUserIdCtx, (string)($ecashMeta['channel_name_raw'] ?? ''));
								if(!empty($resolved)){
									$channelKey = (string)$resolved;
								}
							}
							$available = 0;
							if($channelKey !== ''){
								$available = \Agencybankings\agency_get_drawer_channel_balance($drawerIdCtx, $drawerUserIdCtx, 'ecash', $channelKey);
							}
							if($minAvailable === null || (float)$available < (float)$minAvailable){
								$minAvailable = (float)$available;
							}
						}

						$minAvailable = (float)($minAvailable ?? 0);
						$deficit = \Agencybankings\agency_channel_deficit((float)$minAvailable, (float)$ecashAmountForChannel);
						if($deficit > 0){
							$deficitFmt = number_format($deficit, 2, '.', '');
							$roleRaw = strtolower(trim((string)($session->roles[0] ?? '')));
							$isPrivilegedRole = in_array($roleRaw, ['admin', 'manager', 'general manager'], true);
							$actionHint = $isPrivilegedRole
								? "Please load the account with at least GH₵{$deficitFmt} (or more) before approving this transaction."
								: "Please contact an Admin to deposit into the bank channel and increase the balance by at least GH₵{$deficitFmt} (or more) before approving this transaction.";
							$agencybanking->errors['network'] = "Insufficient eCash/Bank channel balance" . ($channelName !== '' ? (" for {$channelName}") : '') . ". {$actionHint}";
							message_fail($agencybanking->errors['network']);
							set_value('errors', $agencybanking->errors);
							return;
						}
					}
				}
			}catch(\Throwable $e){
				// If balance validation fails unexpectedly, do not block workflow.
			}
	    //dd($postdata); die;
			$postdata['date_updated'] = date("Y-m-d H:i:s");

			if (!array_key_exists('charge', $postdata)) {
				$postdata['charge'] = $row->charge ?? 0;
			} else {
				$rawCharge = $postdata['charge'];
				if (is_string($rawCharge) && trim($rawCharge) === '') {
					$postdata['charge'] = $row->charge ?? 0;
				}
			}
			// Force numeric charge to avoid DB errors on ''
			$postdata['charge'] = number_format((float)($postdata['charge'] ?? 0), 2, '.', '');
			$txTypeRawForCharge = trim((string)($postdata['transaction_type'] ?? $row->transaction_type ?? ''));
			$txTypeLowerForCharge = strtolower($txTypeRawForCharge);
			if (strpos($txTypeLowerForCharge, 'interbank') !== false || strpos($txTypeLowerForCharge, 'gip') !== false) {
				$charge = (float)($postdata['charge'] ?? 0);
				if ($charge > 0) {
					$cents = (int)round($charge * 100);
					$baseCents = (int)(floor($cents / 100) * 100);
					$fracCents = $cents - $baseCents;
					if ($fracCents > 0 && $fracCents < 50) {
						$cents = $baseCents + 50;
					} elseif ($fracCents > 50 && $fracCents < 100) {
						$cents = $baseCents + 100;
					}
					$postdata['charge'] = number_format((float)($cents / 100), 2, '.', '');
				}
			}
			if (!array_key_exists('charging_type', $postdata) || $postdata['charging_type'] === null || $postdata['charging_type'] === '') {
				$postdata['charging_type'] = $row->charging_type ?? null;
			}
			if (!array_key_exists('amount_withdrawn', $postdata) || $postdata['amount_withdrawn'] === null || $postdata['amount_withdrawn'] === '') {
				$postdata['amount_withdrawn'] = $row->amount_withdrawn ?? null;
			}
			if (!array_key_exists('amount_payable', $postdata) || $postdata['amount_payable'] === null || $postdata['amount_payable'] === '') {
				$postdata['amount_payable'] = $row->amount_payable ?? null;
			}
			if (!array_key_exists('cash_received_amount', $postdata) || $postdata['cash_received_amount'] === null || $postdata['cash_received_amount'] === '') {
				$postdata['cash_received_amount'] = $row->cash_received_amount ?? null;
			}
			if (!array_key_exists('change_required', $postdata) || $postdata['change_required'] === null || $postdata['change_required'] === '') {
				$postdata['change_required'] = $row->change_required ?? null;
			}
			if (!array_key_exists('change_denominations', $postdata) || $postdata['change_denominations'] === null || $postdata['change_denominations'] === '') {
				$postdata['change_denominations'] = $row->change_denominations ?? null;
			}
			$postdata['rejection_reason'] = $willBeDeclinedOrRejected ? $rejectionReason : '';

			$txTypeRaw = trim((string)($postdata['transaction_type'] ?? $row->transaction_type ?? ''));
			$networkRaw = trim((string)($postdata['network'] ?? $row->network ?? ''));
			$isWithdrawal = (stripos($txTypeRaw, 'withdrawal') !== false);
			$isGhqrCitWithdrawal = in_array(strtoupper(trim((string)$networkRaw)), ['GHQR', 'GCB'], true) && (strcasecmp($txTypeRaw, 'Withdrawal (CIT)') === 0);
			if ($isWithdrawal && !$willBeDeclinedOrRejected) {
				$postdata['transaction_status'] = 'Approved';
				if (!$isGhqrCitWithdrawal) {
					$postdata['charge'] = 0;
					$postdata['charging_type'] = null;
					$wd = (float)($postdata['amount_withdrawn'] ?? $row->amount_withdrawn ?? 0);
					if ($wd > 0) {
						$postdata['amount_payable'] = $wd;
					}
				}
			}

			// GHQR Withdrawal (CIT): cap both plus/minus charge at GH₵20
			if ($isGhqrCitWithdrawal) {
				$withdrawn = (float)($postdata['amount_withdrawn'] ?? $row->amount_withdrawn ?? 0);
				$payable = (float)($postdata['amount_payable'] ?? $row->amount_payable ?? 0);
				$charge = (float)($postdata['charge'] ?? $row->charge ?? 0);
				$baseForCap = max($withdrawn, $payable);
				if ($baseForCap > 2000 && $charge > 20) {
					$charge = 20;
					$postdata['charge'] = number_format($charge, 2, '.', '');
					if ($withdrawn > 0) {
						$postdata['amount_payable'] = number_format(max(0, $withdrawn - $charge), 2, '.', '');
					}
				}
			}

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

			$denoWasSubmitted = array_key_exists('denos', $postdata);
			if($denoWasSubmitted){
				try{
					$denoModel->query("DELETE FROM denos WHERE trans_id = :trans_id", ['trans_id' => $row->id]);
				}catch(\Throwable $e){
					// ignore delete failure, continue inserting to avoid blocking workflow
				}
				if(!empty($submittedCurrencyArray)){
					foreach($submittedCurrencyArray as $currency){
						$denoModel->insert([
							'trans_id' => $row->id,
							'currency' => $currency['currency'],
							'denomination' => $currency['denomination'],
							'count' => $currency['count'],
							'volume' => $currency['volume'],
							'created_by' => $user_id,
							'date_created' => $postdata['date_updated'],
						]);
					}
				}
			}

			$agencybanking->update($row->id, $postdata);
			if (!empty($agencybanking->has_error)) {
				$dbErr = trim((string)($agencybanking->error ?? ''));
				message_fail('Failed to update transaction.' . ($dbErr !== '' ? (' ' . $dbErr) : '') . ' No balances were adjusted. Please try again.');
				return;
			}

			$chargeValue = (float)($postdata['charge'] ?? 0);
			$isInterbankOrGipCharge = strpos($txTypeLowerForCharge, 'interbank') !== false || strpos($txTypeLowerForCharge, 'gip') !== false;
			try {
				$commissionDb = new \Core\Database;
				$commissionDescription = 'AGENCYBANKING CHARGE | ' . ($networkLabel ?? '') . ' | ' . ($transactionTypeLabel ?? '') . ' | ID ' . ($row->id ?? '');
				$commissionDb->query(
					"DELETE FROM commissions WHERE colletion_period = :period AND commission_type = :commission_type AND description LIKE :needle",
					[
						'period' => 'INSTANT',
						'commission_type' => 'AGENCYBANKING CHARGE',
						'needle' => '%| ID ' . ($row->id ?? ''),
					]
				);

				if ($chargeValue > 0 && !$isInterbankOrGipCharge) {
					$commissionInsert = [
						'date_received' => $postdata['date_updated'] ?? date('Y-m-d H:i:s'),
						'colletion_period' => 'INSTANT',
						'network' => $networkLabel ?: null,
						'commission_type' => 'AGENCYBANKING CHARGE',
						'description' => $commissionDescription,
						'pay_to' => $user_id,
						'receiving_wallet' => null,
						'amount' => $chargeValue,
						'transaction_status' => $postdata['transaction_status'] ?? null,
						'created_by' => $user_id,
						'updated_by' => null,
						'disabled' => 0,
						'deleted' => 0,
						'date_created' => $postdata['date_updated'] ?? date('Y-m-d H:i:s'),
						'date_updated' => null,
						'date_deleted' => null,
					];
					$commissionSql = "INSERT INTO commissions (".implode(',', array_keys($commissionInsert)).") VALUES (:".implode(',:', array_keys($commissionInsert)).")";
					$commissionDb->query($commissionSql, $commissionInsert);
				}
			} catch (\Throwable $e) {
				error_log('AGENCYBANKING CHARGE COMMISSION UPSERT FAILED (EDIT): ' . $e->getMessage());
			}

			$action 		= $isDirectApproval ? 'approved' : 'edited';
			$Oldagencybanking 		= $row->network . ' ' . $row->transaction_type;
			$transaction_id 		= $row->id;
			$newAgencybanking 		= $networkLabel . ' ' . $transactionTypeLabel;
			$table 			= 'agencybankings';
			$description 	= "$username $action agencybanking transaction $Oldagencybanking with id $transaction_id";
			$date_created 	= date("Y-m-d H:i:s");

		    $log = new \Agencybankings\Log;
		    $log_activity = $log->insert([
			 	'table_name'=>$table,
			 	'action'=>$action,
			 	'user_id'=>$user_id,
			 	'user_name'=>$username,
			 	'description'=>$description,
			 	'date_created'=>$date_created,
			 ]);

			// Keep cashdrawer balances consistent with status transitions.
			// - If moving to Approved/Sent, apply drawer entries (after sufficiency validation above).
			// - If moving away from Approved/Sent, remove any prior drawer entries.
			if ($wasApprovedOrSent || $willBeApprovedOrSent) {
				$currencyArray = $submittedCurrencyArray;
				if(empty($currencyArray)){
					try{
						$denoRows = $denoModel->where(['trans_id' => $row->id]) ?? [];
					}catch(\Throwable $e){
						$denoRows = [];
					}
					if(!empty($denoRows)){
						foreach($denoRows as $den){
							$currencyArray[] = [
								'currency' => $den->currency ?? 'GHS',
								'denomination' => (float)($den->denomination ?? 0),
								'count' => (int)($den->count ?? 0),
								'volume' => (float)($den->volume ?? 0),
							];
						}
					}
				}

				$syncPayload = $postdata;
				$syncPayload['id'] = $row->id;
				$syncPayload['date_created'] = $row->date_created ?? $postdata['date_updated'];
				$syncPayload['date_updated'] = $postdata['date_updated'];
				$syncPayload['debug_drawer_sync'] = $_GET['debug_drawer_sync'] ?? $_POST['debug_drawer_sync'] ?? null;
				if (!empty($syncPayload['debug_drawer_sync'])) {
					error_log('AGENCY CASHDRAWER SYNC INVOKE (EDIT): trans_id=' . (int)($syncPayload['id'] ?? 0) . ' owner_id=' . (int)($row->created_by ?? $user_id ?? 0) . ' session_id=' . (int)($session->id ?? $user_id ?? 0));
				}
				\Agencybankings\agency_sync_cashdrawer(
					$syncPayload,
					$currencyArray,
					(int)($row->created_by ?? $user_id ?? 0),
					(int)($session->id ?? $user_id ?? 0)
				);
			}

			message_success($isDirectApproval
				? "Account opening transaction with id ($transaction_id) approved successfully!"
				: "Transaction with id ($transaction_id) edited successfully!");
			redirect($admin_route.'/'.$plugin_route);
		}
	
	if(!$csrf)
		$agencybanking->errors['network'] = "Form expired!";

	set_value('errors',array_merge($agencybanking->errors,$req->upload_errors));
}else{

	message_fail("Record not found");
}

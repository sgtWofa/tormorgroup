<?php
	require_once plugin_path('helpers/cashdrawer-sync.php');
	
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$deno = new \Agencybankings\Deno;

	// Debug logging: capture raw and processed request data for online diagnostics
	$__rawPost = $_POST ?? [];
	$__rawInput = @file_get_contents('php://input');
	error_log('AGENCY ADD RAW_POST: ' . json_encode($__rawPost));
	error_log('AGENCY ADD RAW_INPUT: ' . (is_string($__rawInput) ? $__rawInput : '')); 

	$postdata = $req->post();
	$postdata['transaction_image'] = $postdata['transaction_image'] ?? '';

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
	error_log('AGENCY ADD POSTDATA: ' . json_encode($postdata));
	$currencyDetails = $postdata['denos'] ?? '[]';
	$currencyArray = json_decode($currencyDetails, true);
	if(!is_array($currencyArray)){
		$currencyArray = [];
	}
	$txTypeRaw = trim((string)($postdata['transaction_type'] ?? ''));
	$networkRaw = trim((string)($postdata['network'] ?? ''));
	if (stripos($txTypeRaw, 'withdrawal') !== false) {
		$postdata['withdrawal_type'] = agencybanking_transaction_withdrawal_type($txTypeRaw, $postdata['withdrawal_type'] ?? '');
	}
	$isAitWithdrawal = (strcasecmp($txTypeRaw, 'Withdrawal (AIT)') === 0);
	$amountWithdrawn = (float)($postdata['amount_withdrawn'] ?? 0);
	$depositLoadAmount = (float)($postdata['amount'] ?? 0);
	$cashReceivedAmount = (float)($postdata['cash_received_amount'] ?? ($postdata['amount_paid'] ?? $depositLoadAmount));
	$changeRequired = (float)($postdata['change_required'] ?? 0);
	$changeDenominationsRaw = trim((string)($postdata['change_denominations'] ?? ''));
	$submittedChangeRows = [];
	if ($changeDenominationsRaw !== '') {
		$decodedChangeRows = json_decode($changeDenominationsRaw, true);
		if (is_array($decodedChangeRows)) {
			foreach ($decodedChangeRows as $changeRow) {
				if (!is_array($changeRow)) {
					continue;
				}
				$denomination = (float)($changeRow['denomination'] ?? 0);
				$count = (int)($changeRow['count'] ?? 0);
				if ($denomination > 0 && $count > 0) {
					$submittedChangeRows[] = [
						'currency' => $changeRow['currency'] ?? 'GHS',
						'denomination' => $denomination,
						'count' => $count,
						'volume' => (float)($changeRow['volume'] ?? ($denomination * $count)),
					];
				}
			}
		}
	}
	if ($isAitWithdrawal && $amountWithdrawn > 5000) {
		$agencybanking->errors['amount_withdrawn'] = 'Maximum allowed amount for Withdrawal (AIT) is GHS 5,000.00. Enter GHS 5,000.00 or less.';
		set_value('errors', array_merge($agencybanking->errors, $req->upload_errors));
		return;
	}

	$session = get_session();

	// Ensure at least one admin/manager has created a cashdrawer for today.
	// Bank/e-cash channel movements are synchronized against admin/manager drawers.
	// Only enforce this when the transaction will be written to drawers (Approved/Sent).
	try {
		$statusCheck = strtolower(trim((string)($postdata['transaction_status'] ?? '')));
		$txTypeCheck = strtolower(trim((string)($postdata['transaction_type'] ?? '')));
		$isWithdrawalCheck = (strpos($txTypeCheck, 'withdrawal') !== false);
		$requiresAdminDrawer = in_array($statusCheck, ['approved','sent'], true) || $isWithdrawalCheck;
		if ($requiresAdminDrawer) {
			$adminManagerDrawers = \Agencybankings\agency_fetch_admin_manager_drawers_today();
			if (empty($adminManagerDrawers)) {
				$agencybanking->errors['network'] = 'No Admin/Manager cashdrawer has been created for today. Please contact a Manager or Admin to create their cashdrawer before proceeding.';
				message_fail($agencybanking->errors['network']);
				set_value('errors', $agencybanking->errors);
				return;
			}
		}
	} catch (\Throwable $e) {
		$agencybanking->errors['network'] = 'Unable to validate Admin/Manager cashdrawer for today. Please contact a Manager or Admin.';
		message_fail($agencybanking->errors['network']);
		set_value('errors', $agencybanking->errors);
		return;
	}

	if(!empty($session->id)){
		$username = $session->first_name . ' '. $session->last_name;
		$user_id = $session->id;
	}else{
		$username = $row->first_name . ' '. $row->last_name ?? null;
		$user_id = $row->id ?? null;
	}

	if (strcasecmp($txTypeRaw, 'Deposit') === 0 && $cashReceivedAmount > 0) {
		$expectedChange = round($cashReceivedAmount - $depositLoadAmount, 2);
		if (abs($changeRequired - $expectedChange) > 0.01) {
			$agencybanking->errors['change_required'] = 'Change required must match the difference between cash received and the deposit load.';
			set_value('errors', array_merge($agencybanking->errors, $req->upload_errors));
			return;
		}
		if ($changeRequired > 0.01) {
			$selectedChangeTotal = 0.0;
			foreach ($submittedChangeRows as $changeRow) {
				$selectedChangeTotal += (float)($changeRow['volume'] ?? 0);
			}
			if (abs($selectedChangeTotal - $changeRequired) > 0.01) {
				$agencybanking->errors['change_denominations'] = 'Selected change denominations must exactly match the required change amount.';
				set_value('errors', array_merge($agencybanking->errors, $req->upload_errors));
				return;
			}
			if ($user_id > 0) {
				try {
					$drawerCtx = \Agencybankings\agency_find_active_drawer((int)$user_id);
					if (!empty($drawerCtx)) {
						$drawerId = (int)($drawerCtx['drawer_id'] ?? 0);
						$drawerUserId = (int)($drawerCtx['drawer_user_id'] ?? 0);
						if ($drawerId > 0 && $drawerUserId > 0) {
							$drawerDb = new \Core\Database;
							$inventoryRows = $drawerDb->query(
								"SELECT denomination, SUM(count) AS count FROM drawer_denos WHERE drawer_id = :drawer_id AND user_id = :user_id GROUP BY denomination",
								['drawer_id' => $drawerId, 'user_id' => $drawerUserId]
							);
							$available = [];
							if (!empty($inventoryRows)) {
								foreach ($inventoryRows as $inventoryRow) {
									$available[(string)(float)($inventoryRow->denomination ?? 0)] = (int)($inventoryRow->count ?? 0);
								}
							}
							foreach ($submittedChangeRows as $changeRow) {
								$denKey = (string)(float)($changeRow['denomination'] ?? 0);
								$requestedCount = (int)($changeRow['count'] ?? 0);
								$availableCount = (int)($available[$denKey] ?? 0);
								if ($requestedCount > $availableCount) {
									$agencybanking->errors['change_denominations'] = 'Insufficient drawer denominations available for the requested change.';
									set_value('errors', array_merge($agencybanking->errors, $req->upload_errors));
									return;
								}
							}
						}
					}
				} catch (\Throwable $e) {
					$agencybanking->errors['change_denominations'] = 'Unable to validate drawer inventory for change denominations.';
					set_value('errors', array_merge($agencybanking->errors, $req->upload_errors));
					return;
				}
			}
		}
	}

	$csrf = csrf_verify($postdata);
	$isValidSubmission = $csrf && $agencybanking->validate_insert($postdata);
	if($isValidSubmission)
	{
		 $walletOutRow = [];
		 $walletInRow  = [];

		 // Use null coalescing operator to safely access array keys
		 $networkWallet = $wallet->where(['network'=>$postdata['network'] ?? ''])[0] ?? null;
		 $cashWallet    = $wallet->where(['assigned'=>$user_id, 'wallet_type'=>'Cash'])[0] ?? null;

		 // Auto-derive paying (cash) and receiving (network) wallets
		 $wallet_out_id = $cashWallet->id ?? null;      // paying wallet: physical cash
		 $wallet_in_id  = $networkWallet->id ?? null;   // receiving wallet: e-cash/network
		 // Persist auto-derived wallets into postdata so downstream logic uses them
		 $postdata['wallet_out_id'] = $wallet_out_id;
		 $postdata['wallet_in_id']  = $wallet_in_id;

		 if (!empty($networkWallet) && !empty($wallet_out_id)) {
		   	$walletOutRow = $wallet->first(['id' => $wallet_out_id]);
	   		$walletInRow  = !empty($wallet_in_id) ? $wallet->first(['id' => $wallet_in_id]) : [];
		 }
		 // If wallets are missing or have low balances, we no longer block the
		 // transaction. Drawer accounting is now the primary source of truth.
		if(user_can('add_agencybanking'))
		{
			$postdata['date_created'] = date("Y-m-d H:i:s");
			$postdata['id'] = time();

			$postdata['created_by'] = $user_id;

			// Disable wallet side-effects; system no longer uses wallets
			$useWalletSideEffects = false;

			// Withdrawal policy:
			// - Withdrawals are always Approved by default
			// - No charge for withdrawals EXCEPT GHQR/GCB + Withdrawal (CIT)
			$txTypeLower = strtolower($txTypeRaw);
			$isWithdrawal = (stripos($txTypeRaw, 'withdrawal') !== false);
			$isGhqrCitWithdrawal = in_array(strtoupper(trim((string)$networkRaw)), ['GHQR', 'GCB'], true) && (strcasecmp($txTypeRaw, 'Withdrawal (CIT)') === 0);
			if ($isWithdrawal) {
				$postdata['transaction_status'] = 'Approved';
				if (!$isGhqrCitWithdrawal) {
					$postdata['charge'] = 0;
					$postdata['charging_type'] = null;
					$wd = (float)($postdata['amount_withdrawn'] ?? 0);
					if ($wd > 0) {
						$postdata['amount_payable'] = $wd;
					}
				}
			}

			// Enforce the configured limit after transaction amounts and withdrawal
			// subtype have been normalized, immediately before any write occurs.
			$limitError = agencybanking_validate_transaction_limit($postdata);
			if ($limitError !== null) {
				$agencybanking->errors['amount'] = $limitError;
				message_fail($limitError);
				set_value('errors', array_merge($agencybanking->errors, $req->upload_errors));
				return;
			}

			// GHQR Withdrawal (CIT): cap both plus/minus charge at GH₵20
			if ($isGhqrCitWithdrawal) {
				$withdrawn = (float)($postdata['amount_withdrawn'] ?? 0);
				$payable = (float)($postdata['amount_payable'] ?? 0);
				$charge = (float)($postdata['charge'] ?? 0);
				$baseForCap = max($withdrawn, $payable);
				if ($baseForCap > 2000 && $charge > 20) {
					$charge = 20;
					$postdata['charge'] = number_format($charge, 2, '.', '');
					if ($withdrawn > 0) {
						$postdata['amount_payable'] = number_format(max(0, $withdrawn - $charge), 2, '.', '');
					}
				}
			}

			if (strpos($txTypeLower, 'interbank') !== false || strpos($txTypeLower, 'gip') !== false) {
				$charge = (float)($postdata['charge'] ?? 0);
				if ($charge > 0) {
					$base = floor($charge);
					$frac = $charge - $base;
					if ($frac > 0.00001 && $frac < 0.5) {
						$charge = $base + 0.5;
					} elseif ($frac > 0.5) {
						$charge = ceil($charge);
					}
					$postdata['charge'] = number_format((float)$charge, 2, '.', '');
				}
			}

			// Capture transaction status (after any normalization)
			$trans_status = $postdata['transaction_status'] ?? 'Approved';
			$transStatusLower = strtolower(trim((string)$trans_status));
			if ($transStatusLower !== '') {
				$postdata['transaction_status'] = ucfirst($transStatusLower);
				$trans_status = $postdata['transaction_status'];
			}
			$trans_id = $postdata['id'];

			if(empty($postdata['slug'])){
				$postdata['slug'] = $agencybanking->str_to_url($postdata['network'] . '_' . $postdata['transaction_type']);
			}

			$num = 0;
			while ($num < 10 && $agencybanking->first(['slug'=>$postdata['slug']])) {
				$postdata['slug'] = $postdata['slug'] . rand(0,99);
				$num++;
			}

			// Before doing any inserts or wallet/drawer updates, enforce that
			// cash withdrawals cannot request more notes than are available in
			// the current drawer inventory.
			$drawer_id = 0;
			try{
				$drawerDb = new \Core\Database;
				$today = date('Y-m-d');
				$sqlDrawer = "SELECT id FROM cashdrawers
				               WHERE user_id = :user_id
				                 AND DATE(date_created) = :today
				               ORDER BY id DESC
				               LIMIT 1";
				$drawerRow = $drawerDb->query($sqlDrawer, [
					'user_id' => $user_id,
					'today'   => $today,
				]);
				if(empty($drawerRow)){
					$sqlDrawer = "SELECT id FROM cashdrawers
					               WHERE user_id = :user_id
					               ORDER BY date_created DESC, id DESC
					               LIMIT 1";
					$drawerRow = $drawerDb->query($sqlDrawer, ['user_id' => $user_id]);
				}
				if(!empty($drawerRow)){
					$drawer_id = (int)$drawerRow[0]->id;
				}
			}catch(\Throwable $e){
				$drawer_id = 0;
			}

			try{
				$statusCheck = strtolower(trim((string)($postdata['transaction_status'] ?? '')));
				$isApprovedCheck = ($statusCheck === 'approved' || $statusCheck === 'sent');
				if($isApprovedCheck){
					$cashAmountForChannel = \Agencybankings\agency_resolve_transaction_amount($postdata, $currencyArray);
					$ecashAmountForChannel = \Agencybankings\agency_resolve_ecash_amount($postdata, (float)$cashAmountForChannel);
					$ecashMeta = \Agencybankings\agency_determine_ecash_channel($postdata);
					if(!empty($ecashMeta) && (($ecashMeta['direction'] ?? '') === 'out') && $ecashAmountForChannel > 0){
						$adminManagerDrawers = \Agencybankings\agency_fetch_admin_manager_drawers_today();
						$channelName = trim((string)($ecashMeta['channel_name_raw'] ?? $postdata['network'] ?? ''));
						$channelKey = (string)($ecashMeta['channel_key'] ?? '');
						$sharedAvailable = \Agencybankings\agency_get_shared_ecash_balance($channelKey, $channelName);
						$deficit = \Agencybankings\agency_channel_deficit((float)$sharedAvailable, (float)$ecashAmountForChannel);
						if($deficit > 0){
							$deficitFmt = number_format($deficit, 2, '.', '');
							$roleRaw = strtolower(trim((string)($session->roles[0] ?? '')));
							$isPrivilegedRole = in_array($roleRaw, ['admin', 'manager', 'general manager'], true);
							$actionHint = $isPrivilegedRole
								? "Please load the account with at least GH₵{$deficitFmt} (or more) before submitting this transaction."
								: "Please contact an Admin to deposit into the bank channel and increase the balance by at least GH₵{$deficitFmt} (or more) before submitting this transaction.";
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

			$isCashWithdrawal = in_array($postdata['transaction_type'] ?? '', ['Withdrawal (AIT)','Withdrawal (CIT)']);
			if($drawer_id > 0 && $isCashWithdrawal && ($postdata['transaction_status'] ?? '') === 'Approved' && !empty($currencyArray)){
				try{
					$dbInv = new \Core\Database;
					if($dbInv->table_exists(['drawer_denos'])){
						$normalizeDenominationKey = function($value): string {
							$raw = trim((string)$value);
							if ($raw === '') {
								return '0';
							}
							if (is_numeric($raw)) {
								$raw = rtrim(rtrim(sprintf('%.2F', (float)$raw), '0'), '.');
								if ($raw === '-0') {
									$raw = '0';
								}
							}
							return $raw;
						};

						// Build available inventory per denomination (net of previous ins/outs)
						$invRows = $dbInv->query("SELECT denomination, SUM(count) AS count
							FROM drawer_denos
							WHERE drawer_id = :drawer_id AND user_id = :user_id
							GROUP BY denomination", [
								'drawer_id' => $drawer_id,
								'user_id'   => $user_id,
							]);
						$available = [];
						if(!empty($invRows)){
							foreach($invRows as $r){
								$denKey = $normalizeDenominationKey($r->denomination ?? 0);
								$available[$denKey] = (int)($r->count ?? 0);
							}
						}

						// Build requested counts per denomination from the current transaction
						$requested = [];
						foreach($currencyArray as $denoRow){
							$denCurrency = $denoRow['currency'] ?? 'GHS';
							if(strtoupper($denCurrency) !== 'GHS'){
								continue;
							}
							$den = (float)($denoRow['denomination'] ?? 0);
							$cnt = (int)($denoRow['count'] ?? 0);
							if($cnt <= 0 || $den <= 0){
								continue;
							}
							$denKey = $normalizeDenominationKey($denoRow['denomination'] ?? $den);
							$requested[$denKey] = ($requested[$denKey] ?? 0) + $cnt;
						}

						$insufficient = [];
						foreach($requested as $denKey => $cnt){
							$have = $available[$denKey] ?? 0;
							if($cnt > $have){
								$insufficient[$denKey] = ['have' => $have, 'need' => $cnt];
							}
						}

						if(!empty($insufficient)){
							$parts = [];
							foreach($insufficient as $denKey => $info){
								$parts[] = $denKey . ' (have ' . ((int)($info['have'] ?? 0)) . ', need ' . ((int)($info['need'] ?? 0)) . ')';
							}
							$detail = !empty($parts) ? (' Short: ' . implode(', ', $parts) . '.') : '';
							$agencybanking->errors['denos'] = "Insufficient notes in drawer for this payout. Please adjust denominations to match drawer inventory." . $detail;
							message_fail($agencybanking->errors['denos']);
							set_value('errors', $agencybanking->errors);
							// Behave like validation failure: stop this controller so the
							// add view can re-render with errors and old input.
							return;
						}
					}
				}catch(\Throwable $e){
					// If inventory check fails unexpectedly, fall back to existing behavior
				}
			}

	        // Insert denominations for the agencybanking transaction only.
	        // Mirroring into drawer_denos is done later once drawer_id and direction are known.
	        if ($currencyArray) {
	            foreach ($currencyArray as $currency) {
	                $denosPost = [
	                    'trans_id' => $postdata['id'],
	                    'currency' => $currency['currency'],
	                    'denomination' => $currency['denomination'],
	                    'count' => (int)$currency['count'],
	                    'volume' => (float)$currency['volume'],
	                    'created_by' => $user_id,
	                    'date_created' => $postdata['date_created']
	                ];
	                $deno->insert($denosPost);
	            }
	        }
		if (strcasecmp($txTypeRaw, 'Deposit') === 0 && !empty($submittedChangeRows)) {
			foreach ($submittedChangeRows as $changeRow) {
				$denosPost = [
					'tans_id' => $postdata['id'],
					'currency' => $changeRow['currency'] ?? 'GHS',
					'denomination' => $changeRow['denomination'],
					'count' => -(int)($changeRow['count'] ?? 0),
					'volume' => -(float)($changeRow['volume'] ?? 0),
					'created_by' => $user_id,
					'date_created' => $postdata['date_created'],
				];
				$deno->insert($denosPost);
			}
		}

			 //dd(['postdata'=>$postdata]);die;
			// ** Double-Entry Rule Implementation (disabled)**
			if ($useWalletSideEffects) {
			    // Wallet In - Credit
			    $walletInRow = $wallet->first(['id' => $postdata['wallet_in_id']]) ?? [];
			    if (!empty($walletInRow) && $postdata['transaction_status'] === 'Approved' && !empty($postdata['amount'])) {
			        $postwallet_in = [
			            'credit' => $walletInRow->credit + $postdata['amount'],
			            'balance' => $walletInRow->balance + $postdata['amount'],
			        ];
			        $wallet->update($walletInRow->id, $postwallet_in);

			        // Record incoming transaction
			        $postTransactionIn = [
			            'wallet_id' => $postdata['wallet_in_id'],
			            'wallet_in_id' => $postdata['wallet_in_id'],
			            'description' => $postdata['network'] . ' ' . $postdata['transaction_type'],
			            'transaction_type' => 'credit',
			            'amount' => $postdata['amount'],
			            'credit' => $postdata['amount'],
			            'balance' => $postwallet_in['balance'],
			            'created_by' => $postdata['created_by'],
			            'date_created' => $postdata['date_created'],
			        ];
			        $transaction->insert($postTransactionIn);
			    }

			    // Wallet Out - Debit
			    $walletOutRow = $wallet->first(['id' => $postdata['wallet_out_id']]) ?? [];
			    if (!empty($walletOutRow) && $postdata['transaction_status'] === 'Approved' && !empty($postdata['amount'])) {
			        $postwallet_out = [
			            'debit' => $walletOutRow->debit + $postdata['amount'],
			            'balance' => $walletOutRow->balance - $postdata['amount'],
			        ];
			        $wallet->update($walletOutRow->id, $postwallet_out);

			        // Record outgoing transaction
			        $postTransactionOut = [
			            'wallet_id' => $postdata['wallet_out_id'],
			            'wallet_out_id' => $postdata['wallet_out_id'],
			            'description' => $postdata['network'] . ' ' . $postdata['transaction_type'],
			            'transaction_type' => 'debit',
			            'amount' => $postdata['amount'],
			            'debit' => $postdata['amount'],
			            'balance' => $postwallet_out['balance'],
			            'created_by' => $postdata['created_by'],
			            'date_created' => $postdata['date_created'],
			        ];
			        $transaction->insert($postTransactionOut);
			    }
			}

			// Try to insert the agencybanking row using an explicit SQL insert; only treat hard exceptions as failures
			$__verifyPayload = null;
			try {
				// Build explicit insert payload matching the agencybankings table columns
				$db = new \Core\Database;
				$normalizedCharge = (float)($postdata['charge'] ?? 0);
				$txTypeForCharge = strtolower(trim((string)($postdata['transaction_type'] ?? '')));
				if ($normalizedCharge > 0 && ($txTypeForCharge !== '') && (strpos($txTypeForCharge, 'interbank') !== false || strpos($txTypeForCharge, 'gip') !== false)) {
					// Cents-based rounding (precision-safe):
					// - 0.01..0.49 => round up to .50
					// - 0.51..0.99 => round up to next 1.00
					// - keep .00 and .50 as-is
					$cents = (int)round($normalizedCharge * 100);
					$baseCents = (int)(floor($cents / 100) * 100);
					$fracCents = $cents - $baseCents;
					if ($fracCents > 0 && $fracCents < 50) {
						$cents = $baseCents + 50;
					} elseif ($fracCents > 50 && $fracCents < 100) {
						$cents = $baseCents + 100;
					}
					$normalizedCharge = (float)number_format($cents / 100, 2, '.', '');
					$postdata['charge'] = number_format((float)$normalizedCharge, 2, '.', '');
				}
				$normalizedAmountWithdrawn = $postdata['amount_withdrawn'] ?? null;
				$normalizedAmountPayable = $postdata['amount_payable'] ?? null;
				$normalizedChargingType = $postdata['charging_type'] ?? null;
				$insertData = [
					'id'               => $postdata['id'],
					'network'          => $postdata['network']          ?? null,
					'transaction_type' => $postdata['transaction_type'] ?? null,
					'category'         => $postdata['category']         ?? null,
					'phone'            => $postdata['phone']            ?? null,
					'transaction_image'=> $postdata['transaction_image'] ?? null,
					'amount'           => (float)($postdata['amount']   ?? 0),
					'ussd'             => $postdata['ussd']             ?? null,
					'account_number'   => $postdata['account_number']   ?? null,
					'destination_bank' => $postdata['destination_bank'] ?? null,
					'balance'          => (float)($postdata['balance']        ?? 0),
					'balance_before'   => (float)($postdata['balance_before'] ?? 0),
					'cash_source'      => $postdata['cash_source']      ?? null,
					'sof_option'       => $postdata['sof_option']       ?? null,
					'payment_medium'   => $postdata['payment_medium']   ?? null,
					'wallet_id'        => $postdata['wallet_id']        ?? null,
					'wallet_in_id'     => $postdata['wallet_in_id']     ?? null,
					'wallet_out_id'    => $postdata['wallet_out_id']    ?? null,
					'receiver'         => $postdata['receiver']         ?? null,
					'sender_phone'     => $postdata['sender_phone']     ?? null,
					'full_name'        => $postdata['full_name']        ?? null,
					'dob'              => $postdata['dob']              ?? null,
					'house_number'     => $postdata['house_number']     ?? null,
					'id_type'          => $postdata['id_type']          ?? null,
					'id_number'        => $postdata['id_number']        ?? null,
					'next_of_king'     => $postdata['next_of_king']     ?? null,
					'nok_phone'        => $postdata['nok_phone']        ?? null,
					'nok_relation'     => $postdata['nok_relation']     ?? null,
					'transaction_status'=> $postdata['transaction_status'] ?? null,
					'charge'           => $normalizedCharge,
					'cash_received_amount' => (float)($postdata['cash_received_amount'] ?? $postdata['amount_paid'] ?? 0),
					'change_required'  => (float)($postdata['change_required'] ?? 0),
					'change_denominations' => $postdata['change_denominations'] ?? null,
					'charging_type'    => $normalizedChargingType,
					'slug'             => $postdata['slug']             ?? null,
					'created_by'       => $postdata['created_by']       ?? null,
					'updated_by'       => null,
					'disabled'         => 0,
					'deleted'          => 0,
					'date_created'     => $postdata['date_created']     ?? null,
					'date_updated'     => null,
					'date_deleted'     => null,
					'amount_withdrawn' => $normalizedAmountWithdrawn,
					'amount_payable'   => $normalizedAmountPayable,
					'other_phone'      => $postdata['other_phone']      ?? null,
				];
				$sql = "INSERT INTO agencybankings (".implode(',', array_keys($insertData)).") VALUES (:".implode(',:', array_keys($insertData)).")";
				$insertResult = $db->query($sql, $insertData);
				// Post-insert verification: attempt to read back the row by id
				try {
					$__verify = $agencybanking->first(['id' => $postdata['id']]);
					// Compact view of the insert payload for debugging (avoid dumping everything)
					$__insertSummary = [
						'id'                => $postdata['id']          ?? null,
						'transaction_type'  => $postdata['transaction_type'] ?? null,
						'network'           => $postdata['network']      ?? null,
						'category'          => $postdata['category']     ?? null,
						'amount'            => $postdata['amount']       ?? null,
						'charge'            => $postdata['charge']       ?? null,
						'created_by'        => $postdata['created_by']   ?? null,
						'transaction_status'=> $postdata['transaction_status'] ?? null,
						'keys'              => array_keys($postdata),
					];
					$__verifyPayload = [
						'id'           => $postdata['id'] ?? null,
						'insertResult' => $insertResult,
						'found'        => !empty($__verify),
						'row_exists'   => !empty($__verify),
						'db_error'     => $db->error ?? '',
						'db_has_error' => $db->has_error ?? false,
						'insert_summary'=> $__insertSummary,
					];
					error_log('AGENCY ADD POST-INSERT VERIFY: ' . json_encode($__verifyPayload));
				} catch (\Throwable $__e) {
					error_log('AGENCY ADD POST-INSERT VERIFY FAILED: ' . $__e->getMessage());
				}
			} catch (\Throwable $th) {
				$insertError = $th->getMessage();
				error_log('AGENCYBANKING INSERT FAILED: ' . $insertError);
				$message = 'Failed to save agency banking transaction.';
				if ($insertError) {
					$message .= ' Details: ' . $insertError;
				}
				message_fail($message);
				redirect($admin_route.'/'.$plugin_route);
				return;
			}

			$chargeValue = (float)($postdata['charge'] ?? 0);
			$isInterbankOrGipCharge = strpos((string)($txTypeForCharge ?? ''), 'interbank') !== false || strpos((string)($txTypeForCharge ?? ''), 'gip') !== false;
			if ($chargeValue > 0 && !$isInterbankOrGipCharge) {
				try {
					$commissionDb = new \Core\Database;
					$commissionDescription = 'AGENCYBANKING CHARGE | ' . ($postdata['network'] ?? '') . ' | ' . ($postdata['transaction_type'] ?? '') . ' | ID ' . ($postdata['id'] ?? '');
					$commissionInsert = [
						'date_received' => $postdata['date_created'] ?? date('Y-m-d H:i:s'),
						'colletion_period' => 'INSTANT',
						'network' => $postdata['network'] ?? null,
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
						'date_created' => $postdata['date_created'] ?? date('Y-m-d H:i:s'),
						'date_updated' => null,
						'date_deleted' => null,
					];
					$commissionSql = "INSERT INTO commissions (".implode(',', array_keys($commissionInsert)).") VALUES (:".implode(',:', array_keys($commissionInsert)).")";
					$commissionDb->query($commissionSql, $commissionInsert);
				} catch (\Throwable $e) {
					error_log('AGENCYBANKING CHARGE COMMISSION INSERT FAILED: ' . $e->getMessage());
				}
			}

			//log changes in the log table
			$action        = 'added';
			$transaction   = $postdata['network'] . ' '. $postdata['transaction_type'];
			$transaction_id = $postdata['id'];
			$table         = 'agencybankings';
			$description   = "$username $action a new agencybanking called $transaction with id $transaction_id to $table";
			$date_created  = date("Y-m-d H:i:s");

			$log = new \Agencybankings\Log;
			$log_activity = $log->insert([
				'table_name'=>$table,
				'action'=>$action,
				'user_id'=>$user_id,
				'user_name'=>$username,
				'description'=>$description,
				'date_created'=>$date_created,
			]);

			// Cashdrawer is the primary accounting source: sync this transaction
			$syncPayload = $postdata;
			$syncPayload['id'] = $postdata['id'];
			$syncPayload['date_created'] = $postdata['date_created'];
			$syncPayload['date_updated'] = $postdata['date_created'];
			$syncPayload['debug_drawer_sync'] = $_GET['debug_drawer_sync'] ?? $_POST['debug_drawer_sync'] ?? null;
			try {
				if (!empty($syncPayload['debug_drawer_sync'])) {
					error_log('AGENCY CASHDRAWER SYNC INVOKE: trans_id=' . (int)($syncPayload['id'] ?? 0) . ' owner_id=' . (int)($postdata['created_by'] ?? $user_id ?? 0) . ' session_id=' . (int)($session->id ?? $user_id ?? 0));
				}
				\Agencybankings\agency_sync_cashdrawer(
					$syncPayload,
					$currencyArray,
					(int)($postdata['created_by'] ?? $user_id ?? 0),
					(int)($session->id ?? $user_id ?? 0)
				);
			} catch (\Throwable $e) {
				// Do not block the transaction if drawer sync fails; log for investigation
				error_log('AGENCYBANKING CASHDRAWER SYNC FAILED: ' . $e->getMessage());
			}

			// Transaction saved successfully; debug details remain in error_log for diagnostics
			message_success("Transaction ($transaction) added successfully!");
			redirect($admin_route.'/'.$plugin_route);
        }
    }
	if (!$csrf) {
		$agencybanking->errors['network'] = "Form expired!";
	} elseif (empty($agencybanking->errors)) {
		$agencybanking->errors['network'] = 'Please review the transaction details and try again.';
	}
	
	set_value('errors', array_merge($agencybanking->errors, $req->upload_errors));

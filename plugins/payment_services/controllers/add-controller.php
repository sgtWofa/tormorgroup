<?php
	
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');
	$deno = new \PaymentService\Deno;

 	$postdata = $req->post();
	$postdata['transaction_image'] = $postdata['transaction_image'] ?? '';
	$postdata['amount_paid'] = isset($postdata['amount_paid']) && $postdata['amount_paid'] !== '' ? number_format((float)$postdata['amount_paid'], 2, '.', '') : '0.00';
	$postdata['change_required'] = isset($postdata['change_required']) && $postdata['change_required'] !== '' ? number_format((float)$postdata['change_required'], 2, '.', '') : '0.00';
	$postdata['change_denominations'] = $postdata['change_denominations'] ?? '[]';
	$postdata['cash_received_amount'] = isset($postdata['cash_received_amount']) && $postdata['cash_received_amount'] !== '' ? number_format((float)$postdata['cash_received_amount'], 2, '.', '') : '0.00';
	$assignedToRaw = trim((string)($postdata['assigned_to'] ?? ''));
	$postdata['assigned_to'] = ($assignedToRaw !== '' && is_numeric($assignedToRaw)) ? (int)$assignedToRaw : 0;

	// Ensure newly required columns exist before any insert attempts
	try {
		$dbSchema = new \Core\Database;
		$neededCols = [
			'amount_paid',
			'assigned_to',
			'transaction_image',
			'change_required',
			'change_denominations',
			'cash_received_amount',
		];
		$missing = [];
		foreach ($neededCols as $col) {
			$hasCol = $dbSchema->query(
				"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'payment_services' AND COLUMN_NAME = :col LIMIT 1",
				['db' => DB_NAME, 'col' => $col]
			);
			if (empty($hasCol)) {
				$missing[] = $col;
			}
		}
		if (!empty($missing)) {
			$typeMap = [
				'amount_paid' => 'FLOAT(10,2) DEFAULT 0.00',
				'assigned_to' => 'INT(11) DEFAULT 0',
				'transaction_image' => 'VARCHAR(255) NULL',
				'change_required' => 'FLOAT(10,2) DEFAULT 0.00',
				'change_denominations' => 'LONGTEXT NULL',
				'cash_received_amount' => 'FLOAT(10,2) DEFAULT 0.00',
			];
			foreach ($missing as $col) {
				$colType = $typeMap[$col] ?? 'VARCHAR(255) NULL';
				$dbSchema->query("ALTER TABLE payment_services ADD COLUMN {$col} {$colType}");
			}
		}
	} catch (\Throwable $e) {
		$payment_service->errors['amount'] = 'Unable to prepare payment services table for change tracking. Please contact admin.';
		set_value('errors', array_merge($payment_service->errors, $req->upload_errors));
		return;
	}

// Upload transaction message picture
try {
	$req->upload_folder = 'uploads/payment_services';
	$uploadedImage = $req->upload_files('transaction_image');
	if (!empty($uploadedImage)) {
		$postdata['transaction_image'] = $uploadedImage;
	}
} catch (\Throwable $e) {
	$payment_service->errors['transaction_image'] = 'Unable to upload transaction image.';
	set_value('errors', array_merge($payment_service->errors, $req->upload_errors));
	return;
}
 $currencyDetails = $postdata['denos'] ?? '[]';
//dd($postdata);die;
// Decode the JSON currency details
$currencyArray = json_decode($currencyDetails, true);
if (!is_array($currencyArray)) {
	$currencyArray = [];
}

// Compute total volume from denominations so validation can compare against amount_paid
$denoTotal = 0.0;
foreach($currencyArray as $c){
	$denoTotal += (float)($c['volume'] ?? 0);
}
$postdata['deno_total'] = $denoTotal;

	$csrf = csrf_verify($postdata);
	if($csrf && $payment_service->validate_insert($postdata))
		{
			$postdata['date_created'] = date("Y-m-d H:i:s");
			$postdata['id'] = time();
			$collisionTries = 0;
			while($payment_service->first(['id'=>$postdata['id']]) && $collisionTries < 5){
				$postdata['id']++;
				$collisionTries++;
			}
			$trans_status = $postdata['transaction_status'] ?? 'Approved';
			$trans_id = $postdata['id'];
			$medium = strtolower(trim((string)($postdata['payment_medium'] ?? '')));

			$session = get_session();

			if(!empty($session->id)){
				$username = $session->first_name . ' '. $session->last_name;
				$user_id = $session->id;
			}else{
				$username = $row->first_name . ' '. $row->last_name;
				$user_id = $row->id;
			}

			$postdata['created_by'] = $user_id;
			$assignedTo = (int)($postdata['assigned_to'] ?? 0);
			if ($medium === 'bank' && $assignedTo <= 0) {
				$payment_service->errors['assigned_to'] = 'Assigned user is required for bank payments';
				set_value('errors', array_merge($payment_service->errors, $req->upload_errors));
				return;
			}

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

			$cashReceivedAmount = (float)($postdata['cash_received_amount'] ?? ($postdata['amount'] ?? 0));
			$transactionAmount = (float)($postdata['amount'] ?? 0);
			$amountPayable = (float)($postdata['amount_paid'] ?? 0);
			$changeRequired = (float)($postdata['change_required'] ?? 0);
			$changeDenominations = json_decode($postdata['change_denominations'] ?? '[]', true);
			if (!is_array($changeDenominations)) {
				$changeDenominations = [];
			}

			$changeDenominations = array_values(array_filter(array_map(function($row){
				$denomination = (float)($row['denomination'] ?? 0);
				$count = (int)($row['count'] ?? 0);
				if ($denomination <= 0 || $count <= 0) {
					return null;
				}
				return [
					'currency' => 'GHS',
					'denomination' => $denomination,
					'count' => $count,
					'volume' => round($denomination * $count, 2),
				];
			}, $changeDenominations)));

			$changeGivenTotal = 0.0;
			foreach ($changeDenominations as $changeRow) {
				$changeGivenTotal += (float)($changeRow['volume'] ?? 0);
			}
			$changeGivenTotal = round($changeGivenTotal, 2);

			if ($cashReceivedAmount <= 0 && $transactionAmount > 0) {
				$cashReceivedAmount = $transactionAmount;
			}
			$computedChangeRequired = ($cashReceivedAmount > $amountPayable)
				? round($cashReceivedAmount - $amountPayable, 2)
				: 0.0;

			if ($cashReceivedAmount > 0 && $amountPayable > 0 && $cashReceivedAmount + 0.00001 < $amountPayable) {
				$payment_service->errors['amount_paid'] = 'Amount payable cannot exceed the cash received.';
				set_value('errors', array_merge($payment_service->errors, $req->upload_errors));
				return;
			}

			if ($computedChangeRequired > 0.01) {
				if ($changeRequired <= 0 || abs($changeRequired - $computedChangeRequired) > 0.01) {
					$payment_service->errors['change_required'] = 'Change required does not match the excess cash received.';
					set_value('errors', array_merge($payment_service->errors, $req->upload_errors));
					return;
				}

				if (empty($changeDenominations)) {
					$payment_service->errors['change_denominations'] = 'Select the denominations to give out as change.';
					set_value('errors', array_merge($payment_service->errors, $req->upload_errors));
					return;
				}

				if (abs($changeGivenTotal - $computedChangeRequired) > 0.01) {
					$payment_service->errors['change_denominations'] = 'Selected change denominations must total GHS ' . number_format($computedChangeRequired, 2) . '.';
					set_value('errors', array_merge($payment_service->errors, $req->upload_errors));
					return;
				}
			} else {
				$changeRequired = 0.0;
				$changeDenominations = [];
				$changeGivenTotal = 0.0;
				$postdata['change_required'] = '0.00';
				$postdata['change_denominations'] = '[]';
			}

			$transactionAmount = round($cashReceivedAmount - $changeGivenTotal, 2);
			if ($amountPayable > 0 && abs($transactionAmount - $amountPayable) > 0.01) {
				$payment_service->errors['change_denominations'] = 'Net cash after change must match the amount payable.';
				set_value('errors', array_merge($payment_service->errors, $req->upload_errors));
				return;
			}

			$postdata['cash_received_amount'] = number_format($cashReceivedAmount, 2, '.', '');
			$postdata['amount'] = number_format($transactionAmount, 2, '.', '');
			$postdata['amount_paid'] = number_format($amountPayable, 2, '.', '');
			if ($changeRequired > 0.01) {
				$postdata['change_required'] = number_format($changeRequired, 2, '.', '');
				$postdata['change_denominations'] = json_encode($changeDenominations, JSON_UNESCAPED_SLASHES);
			}

			$drawer_id_for_change = 0;
			try {
				$drawerDb = new \Core\Database;
				$today = date('Y-m-d');
				$drawerRow = $drawerDb->query("SELECT id FROM cashdrawers WHERE user_id = :user_id AND DATE(date_created) = :today ORDER BY id DESC LIMIT 1", ['user_id' => $user_id, 'today' => $today]);
				if (empty($drawerRow)) {
					$drawerRow = $drawerDb->query("SELECT id FROM cashdrawers WHERE user_id = :user_id ORDER BY date_created DESC, id DESC LIMIT 1", ['user_id' => $user_id]);
				}
				if (!empty($drawerRow)) {
					$drawer_id_for_change = (int)$drawerRow[0]->id;
				}
			} catch (\Throwable $e) {
				$drawer_id_for_change = 0;
			}

			if (!empty($changeDenominations)) {
				if ($drawer_id_for_change <= 0) {
					$payment_service->errors['change_denominations'] = 'Cashdrawer not found for this user, so change denominations cannot be validated.';
					set_value('errors', array_merge($payment_service->errors, $req->upload_errors));
					return;
				}
				try {
					$dbChangeInv = new \Core\Database;
					if (!$dbChangeInv->table_exists(['drawer_denos'])) {
						$payment_service->errors['change_denominations'] = 'Cannot validate change because drawer inventory table (drawer_denos) is missing. Please contact admin.';
						set_value('errors', array_merge($payment_service->errors, $req->upload_errors));
						return;
					}

					$drawerUserId = $user_id;
					try {
						$userRow = $dbChangeInv->query("SELECT user_id FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawer_id_for_change]);
						if (!empty($userRow)) {
							$drawerUserId = (int)($userRow[0]->user_id ?? $user_id);
						}
					} catch (\Throwable $e) {}

					$invRows = $dbChangeInv->query(
						"SELECT denomination, SUM(count) AS count FROM drawer_denos WHERE drawer_id = :drawer_id AND user_id = :user_id GROUP BY denomination",
						['drawer_id' => $drawer_id_for_change, 'user_id' => $drawerUserId]
					);

					$available = [];
					if (!empty($invRows)) {
						foreach ($invRows as $r) {
							$denKey = $normalizeDenominationKey($r->denomination ?? 0);
							$available[$denKey] = (int)($r->count ?? 0);
						}
					}

					foreach ($currencyArray as $denoRow) {
						$denCurrency = strtoupper((string)($denoRow['currency'] ?? 'GHS'));
						if ($denCurrency !== 'GHS') {
							continue;
						}
						$den = (float)($denoRow['denomination'] ?? 0);
						$cnt = (int)($denoRow['count'] ?? 0);
						if ($den <= 0 || $cnt <= 0) {
							continue;
						}
						$denKey = $normalizeDenominationKey($denoRow['denomination'] ?? $den);
						$available[$denKey] = ($available[$denKey] ?? 0) + $cnt;
					}

					$requested = [];
					foreach ($changeDenominations as $changeRow) {
						$denKey = $normalizeDenominationKey($changeRow['denomination'] ?? 0);
						$cnt = (int)($changeRow['count'] ?? 0);
						if ($denKey === '0' || $cnt <= 0) {
							continue;
						}
						$requested[$denKey] = ($requested[$denKey] ?? 0) + $cnt;
					}

					$insufficient = [];
					foreach ($requested as $denKey => $cnt) {
						$have = $available[$denKey] ?? 0;
						if ($cnt > $have) {
							$insufficient[$denKey] = ['have' => $have, 'need' => $cnt];
						}
					}

					if (!empty($insufficient)) {
						$parts = [];
						foreach ($insufficient as $denKey => $info) {
							$parts[] = 'GHS ' . $denKey . ' (need ' . ((int)($info['need'] ?? 0)) . ', available ' . ((int)($info['have'] ?? 0)) . ')';
						}
						$payment_service->errors['change_denominations'] = 'Insufficient denominations available for change. ' . implode(' | ', $parts);
						set_value('errors', array_merge($payment_service->errors, $req->upload_errors));
						return;
					}
				} catch (\Throwable $e) {
					// fail silently; do not block if inventory lookup fails
				}
			}

	        if ($currencyArray) {
	            foreach ($currencyArray as $currency) {
	                $denosPost = [
	                   'trans_id' => $postdata['id'],
	                    'currency' => $currency['currency'],
	                    'denomination' => $currency['denomination'],
	                    'count' => (int)$currency['count'], // Cast to integer
	                    'volume' => (float)$currency['volume'], // Cast to float
	                    'created_by' => $user_id,
	                    'date_created' => $postdata['date_created']
	                ];

	                // Validate before insertion
	                if (empty($denosPost['trans_id'])) {
	                    dd("Invalid trans_id: " . $denosPost['trans_id']);
	                }

	                $deno->insert($denosPost);
	            }
	        }

	        if (!empty($changeDenominations)) {
	        	foreach ($changeDenominations as $currency) {
	        		$denosPost = [
	        			'trans_id' => $postdata['id'],
	        			'currency' => $currency['currency'] ?? 'GHS',
	        			'denomination' => $currency['denomination'],
	        			'count' => -1 * abs((int)($currency['count'] ?? 0)),
	        			'volume' => -1 * abs((float)($currency['volume'] ?? 0)),
	        			'created_by' => $user_id,
	        			'date_created' => $postdata['date_created']
	        		];
	        		if (empty($denosPost['trans_id'])) {
	        			dd("Invalid trans_id: " . $denosPost['trans_id']);
	        		}
	        		$deno->insert($denosPost);
	        	}
	        }


			// Cashdrawer update - Mirror denominations to drawer_denos, then recompute drawer_balances
		try {
			$drawer_id = 0;
			$drawerDb = new \Core\Database;
			$today = date('Y-m-d');
			$drawerRow = $drawerDb->query("SELECT id FROM cashdrawers WHERE user_id = :user_id AND DATE(date_created) = :today ORDER BY id DESC LIMIT 1", ['user_id' => $user_id, 'today' => $today]);
			if (empty($drawerRow)) {
				$drawerRow = $drawerDb->query("SELECT id FROM cashdrawers WHERE user_id = :user_id ORDER BY date_created DESC, id DESC LIMIT 1", ['user_id' => $user_id]);
			}
			if (!empty($drawerRow)) {
				$drawer_id = (int)$drawerRow[0]->id;
			}

			// Mirror denominations to drawer_denos if we have them
			if ($drawer_id > 0 && !empty($currencyArray)) {
				try {
					$dbDrawerDeno = new \Core\Database;
					if ($dbDrawerDeno->table_exists(['drawer_denos'])) {
						$drawerUserId = $user_id;
						try {
							$userRow = $dbDrawerDeno->query("SELECT user_id FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawer_id]);
							if (!empty($userRow)) {
								$drawerUserId = (int)($userRow[0]->user_id ?? $user_id);
							}
						} catch (\Throwable $e) {}

						// Payment Services cash is treated as money coming IN to the drawer
						$sign = 1;

						foreach ($currencyArray as $denoRow) {
							$denCurrency = $denoRow['currency'] ?? 'GHS';
							if (strtoupper($denCurrency) !== 'GHS') {
								continue;
							}
							// Capture transaction status (default to 'Approved' if not provided)
							$trans_status = $postdata['transaction_status'] ?? 'Approved';
							$trans_id = $postdata['id'];
							$sqlDeno = "INSERT INTO drawer_denos (drawer_id, user_id, denomination, count, volume, date_created, created_by, trans_id, trans_status) VALUES (:drawer_id, :user_id, :denomination, :count, :volume, :date_created, :created_by, :trans_id, :trans_status)";
							$paramsDeno = [
								'drawer_id' => $drawer_id,
								'user_id' => $drawerUserId,
								'denomination' => $denoRow['denomination'],
								'count' => $sign * (int)($denoRow['count'] ?? 0),
								'volume' => $sign * (float)($denoRow['volume'] ?? 0),
								'date_created' => $postdata['date_created'],
								'created_by' => $drawerUserId,
								'trans_id' => $trans_id,
								'trans_status' => $trans_status,
							];
							$dbDrawerDeno->query($sqlDeno, $paramsDeno);
						}

						if (!empty($changeDenominations)) {
							foreach ($changeDenominations as $denoRow) {
								$sqlDeno = "INSERT INTO drawer_denos (drawer_id, user_id, denomination, count, volume, date_created, created_by, trans_id, trans_status) VALUES (:drawer_id, :user_id, :denomination, :count, :volume, :date_created, :created_by, :trans_id, :trans_status)";
								$paramsDeno = [
									'drawer_id' => $drawer_id,
									'user_id' => $drawerUserId,
									'denomination' => $denoRow['denomination'],
									'count' => -1 * abs((int)($denoRow['count'] ?? 0)),
									'volume' => -1 * abs((float)($denoRow['volume'] ?? 0)),
									'date_created' => $postdata['date_created'],
									'created_by' => $drawerUserId,
									'trans_id' => $trans_id,
									'trans_status' => 'Change',
								];
								$dbDrawerDeno->query($sqlDeno, $paramsDeno);
							}
						}

						// Recompute CASH_DRAWER balance from denominations snapshot
						try {
							$sumRows = $dbDrawerDeno->query("SELECT SUM(volume) AS total_volume FROM drawer_denos WHERE drawer_id = :drawer_id AND user_id = :user_id", ['drawer_id' => $drawer_id, 'user_id' => $drawerUserId]);
							$currentClosing = !empty($sumRows) ? (float)($sumRows[0]->total_volume ?? 0) : 0.0;

							$dbBal = new \Core\Database;
							if ($dbBal->table_exists(['drawer_balances'])) {
								$rowsBal = $dbBal->query("SELECT * FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'cash' AND channel_key = 'CASH_DRAWER' LIMIT 1", ['drawer_id' => $drawer_id, 'user_id' => $drawerUserId]);
								$now = date('Y-m-d H:i:s');

								if (empty($rowsBal)) {
									$hdr = $dbBal->query("SELECT amount FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawer_id]);
									$opening = !empty($hdr) ? (float)($hdr[0]->amount ?? 0) : 0.0;
									$delta = $currentClosing - $opening;
									$credit = $delta > 0 ? $delta : 0;
									$debit = $delta < 0 ? -$delta : 0;

									$insertSql = "INSERT INTO drawer_balances (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status) VALUES (:drawer_id, :user_id, 'cash', 'CASH_DRAWER', :opening, :credit, :debit, :closing, :date_created, :trans_id, :trans_status)";
									$paramsBal = [
										'drawer_id' => $drawer_id,
										'user_id' => $drawerUserId,
										'opening' => $opening,
										'credit' => $credit,
										'debit' => $debit,
										'closing' => $currentClosing,
										'date_created' => $now,
										'trans_id' => $trans_id,
										'trans_status' => $trans_status,
									];
									$dbBal->query($insertSql, $paramsBal);
								} else {
									$rowBal = $rowsBal[0];
									$opening = (float)($rowBal->opening_balance ?? 0);
									$delta = $currentClosing - $opening;
									$credit = $delta > 0 ? $delta : 0;
									$debit = $delta < 0 ? -$delta : 0;

									$updateSql = "UPDATE drawer_balances SET credit = :credit, debit = :debit, closing_balance = :closing, trans_id = :trans_id, trans_status = :trans_status WHERE id = :id";
									$paramsBal = [
										'credit' => $credit,
										'debit' => $debit,
										'closing' => $currentClosing,
										'trans_id' => $trans_id,
										'trans_status' => $trans_status,
										'id' => $rowBal->id,
									];
									$dbBal->query($updateSql, $paramsBal);
								}
							}
						} catch (\Throwable $e) {
							// fail silently
						}
					}
				} catch (\Throwable $e) {
					// fail silently
				}
			}
		} catch (\Throwable $e) {
			// fail silently
		}

		// Update e-cash channel if network is provided (Payment Services is debit/out)
		// For MTN, TELECEL, AT networks, consolidate to single channel (no ECASH_ prefix)
		$channelName = null;
		if ($medium === 'bank') {
			$channelName = trim((string)($postdata['bank'] ?? ''));
		} else {
			$channelName = trim((string)($postdata['network'] ?? ''));
		}
		$channelOwnerId = ($medium === 'bank' && $assignedTo > 0) ? $assignedTo : $user_id;
		$channelNamePresent = ($channelName !== null && $channelName !== '');

		if ($trans_status === 'Approved' && $channelOwnerId > 0 && $channelNamePresent) {
			try {
				$dbBal = new \Core\Database;
				if ($dbBal->table_exists(['drawer_balances'])) {
					$channelDrawerId = 0;
					try {
						$drawerDb2 = new \Core\Database;
						$today2 = date('Y-m-d');
						$drawerRow2 = $drawerDb2->query("SELECT id FROM cashdrawers WHERE user_id = :user_id AND DATE(date_created) = :today ORDER BY id DESC LIMIT 1", ['user_id' => $channelOwnerId, 'today' => $today2]);
						if (empty($drawerRow2)) {
							$drawerRow2 = $drawerDb2->query("SELECT id FROM cashdrawers WHERE user_id = :user_id ORDER BY date_created DESC, id DESC LIMIT 1", ['user_id' => $channelOwnerId]);
						}
						if (!empty($drawerRow2)) {
							$channelDrawerId = (int)($drawerRow2[0]->id ?? 0);
						}
					} catch (\Throwable $e) {
						$channelDrawerId = 0;
					}
					if ($channelDrawerId <= 0) {
						$payment_service->errors['amount'] = 'Cashdrawer not found for assigned user, so bank/e-cash balance cannot be validated.';
						set_value('errors', array_merge($payment_service->errors, $req->upload_errors));
						return;
					}

					$drawerUserId = $channelOwnerId;
					try {
						$userRow = $dbBal->query("SELECT user_id FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $channelDrawerId]);
						if (!empty($userRow)) {
							$drawerUserId = (int)($userRow[0]->user_id ?? $channelOwnerId);
						}
					} catch (\Throwable $e) {}

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

					$drawerCandidateOwners = array_values(array_unique(array_filter([
						$channelOwnerId,
						$user_id,
						(int)($postdata['created_by'] ?? 0),
						(int)($postdata['assigned_to'] ?? 0),
					], static function ($value) {
						return (int)$value > 0;
					})));
					$rowsEcash = [];
					$resolvedBalanceDrawerId = $channelDrawerId;
					$resolvedBalanceDrawerUserId = $drawerUserId;
					$resolvedBalanceKey = $ecashChannelKey;
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

						$candidateChannelKey = payment_service_resolve_channel_key($candidateDrawerId, $candidateDrawerUserId, $channelName, strtoupper($medium));
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

					$channelDrawerId = $resolvedBalanceDrawerId > 0 ? $resolvedBalanceDrawerId : $channelDrawerId;
					$drawerUserId = $resolvedBalanceDrawerUserId > 0 ? $resolvedBalanceDrawerUserId : $drawerUserId;
					$ecashChannelKey = $resolvedBalanceKey ?: $ecashChannelKey;

					$transactionAmount = (float)($postdata['amount'] ?? 0);
					$currentBalance = 0.0;
					$resolvedKey = null;
					if (!empty($rowsEcash)) {
						$resolvedKey = (string)($rowsEcash[0]->channel_key ?? $ecashChannelKey);
						$currentBalance = (float)($rowsEcash[0]->closing_balance ?? 0);
					}
					if (empty($rowsEcash)) {
						$payment_service->errors['amount'] = 'Insufficient e-cash/bank balance in channel ' . $ecashChannelKey . ' (available 0.00, required ' . number_format($transactionAmount, 2) . ')';
						set_value('errors', array_merge($payment_service->errors, $req->upload_errors));
						return;
					}
					$projected = $currentBalance - $transactionAmount;
					if ($transactionAmount > 0 && $projected < -0.00001) {
						$payment_service->errors['amount'] = 'Insufficient e-cash/bank balance in channel ' . $resolvedKey . ' (available ' . number_format($currentBalance, 2) . ', required ' . number_format($transactionAmount, 2) . ')';
						set_value('errors', array_merge($payment_service->errors, $req->upload_errors));
						return;
					}
					$ecashCredit = 0;
					$ecashDebit = $transactionAmount;
					$now = date('Y-m-d H:i:s');

					$rowEcash = $rowsEcash[0];
					$ecashOpening = (float)($rowEcash->opening_balance ?? 0);
					$ecashCredit = (float)($rowEcash->credit ?? 0) + $ecashCredit;
					$ecashDebit = (float)($rowEcash->debit ?? 0) + $ecashDebit;
					$ecashClosing = $ecashOpening + $ecashCredit - $ecashDebit;

					$updateEcashSql = "UPDATE drawer_balances SET channel_key = :channel_key, credit = :credit, debit = :debit, closing_balance = :closing WHERE id = :id";
					$paramsEcash = [
						'channel_key' => $ecashChannelKey,
						'credit' => $ecashCredit,
						'debit' => $ecashDebit,
						'closing' => $ecashClosing,
						'id' => $rowEcash->id,
					];
					$dbBal->query($updateEcashSql, $paramsEcash);
				}
			} catch (\Throwable $e) {
				// fail silently
			}
		}

			//dd(['postdata'=>$postdata, 'postwallet_in'=>$postwallet_in, 'postTransactionIn'=>$postTransactionIn, 'postwallet_out'=>$postwallet_out, 'postTransactionOut'=>$postTransactionOut]);die;

			$payment_service->insert($postdata);
			if(!empty($payment_service->has_error)){
				$err = !empty($payment_service->error) ? $payment_service->error : 'Failed to save payment service transaction.';
				$payment_service->errors['amount'] = $err;
				set_value('errors', array_merge($payment_service->errors, $req->upload_errors));
				return;
			}

			$chargeValue = (float)($postdata['charge'] ?? 0);
			if ($chargeValue > 0) {
				try {
					$commissionDb = new \Core\Database;
					$mediumLabel = trim((string)($postdata['payment_medium'] ?? ''));
					$chargeNetwork = ($mediumLabel !== '' && strtolower($mediumLabel) === 'bank')
						? trim((string)($postdata['bank'] ?? ''))
						: trim((string)($postdata['network'] ?? ''));
					$chargeNetwork = $chargeNetwork !== '' ? $chargeNetwork : $mediumLabel;
					$commissionDescription = 'PAYMENT SERVICES CHARGE | ' . ($postdata['category'] ?? '') . ' | ' . ($postdata['institution'] ?? '') . ' | ID ' . ($postdata['id'] ?? '');
					$commissionDb->query(
						"DELETE FROM commissions WHERE commission_type = :commission_type AND description LIKE :needle",
						[
							'commission_type' => 'PAYMENT SERVICES CHARGE',
							'needle' => '%| ID ' . ($postdata['id'] ?? ''),
						]
					);
					$commissionInsert = [
						'date_received' => $postdata['date_created'] ?? date('Y-m-d H:i:s'),
						'colletion_period' => 'INSTANT',
						'network' => $chargeNetwork !== '' ? $chargeNetwork : null,
						'commission_type' => 'PAYMENT SERVICES CHARGE',
						'description' => $commissionDescription,
						'pay_to' => $user_id,
						'receiving_wallet' => null,
						'amount' => $chargeValue,
						'transaction_status' => $postdata['transaction_status'] ?? 'Approved',
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
					error_log('PAYMENT SERVICES CHARGE COMMISSION INSERT FAILED: ' . $e->getMessage());
				}
			}

			//log changes in the log table
			$action 		= 'added';
			$transaction 		= $postdata['category'] . ' '. $postdata['institution'];
			$transaction_id 		= $postdata['id'];
			$table 			= 'payment_services';
			$description 	= "$username $action a new payment_service $transaction";
			$date_created 	= date("Y-m-d H:i:s");

		    $log = new \PaymentService\Log;
		    $log_activity = $log->insert([
			 	'table_name'=>$table,
			 	'action'=>$action,
			 	'user_id'=>$user_id,
			 	'user_name'=>$username,
			 	'description'=>$description,
			 	'date_created'=>$date_created,
			 ]);

			message_success("Payment for $transaction added successfully!");
			redirect($admin_route.'/'.$plugin_route);
		}

	if(!$csrf)
		$payment_service->errors['description'] = "Form expired!";
	
	set_value('errors',$payment_service->errors);

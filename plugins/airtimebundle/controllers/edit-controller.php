<?php



if (!empty($row)) {

    // Set timezone to Accra



    $log = new \Airtimebundles\Log;

    $deno = new \Airtimebundles\Deno;

    $wallet = new \Airtimebundles\Wallet;

    $transaction = new \Airtimebundles\Transaction;

    date_default_timezone_set('Africa/Accra');

    $ensureAirtimebundleColumns = function() {
        try {
            $dbSchema = new \Core\Database;
            $neededCols = [
                'cash_received_amount',
                'change_required',
                'change_denominations',
                'rejection_reason',
                'transaction_image',
            ];
            $missing = [];
            foreach ($neededCols as $col) {
                $hasCol = $dbSchema->query(
                    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'airtimebundles' AND COLUMN_NAME = :col LIMIT 1",
                    ['db' => DB_NAME, 'col' => $col]
                );
                if (empty($hasCol)) {
                    $missing[] = $col;
                }
            }
            if (!empty($missing)) {
                $typeMap = [
                'cash_received_amount' => 'FLOAT(10,2) DEFAULT 0.00',
                'change_required' => 'FLOAT(10,2) DEFAULT 0.00',
                'change_denominations' => 'LONGTEXT NULL',
                'rejection_reason' => 'LONGTEXT NULL',
                'transaction_image' => 'VARCHAR(255) NULL',
            ];
                foreach ($missing as $col) {
                    $colType = $typeMap[$col] ?? 'VARCHAR(255) NULL';
                    $dbSchema->query("ALTER TABLE airtimebundles ADD COLUMN {$col} {$colType}");
                }
            }
        } catch (\Throwable $e) {
            $airtimebundle->errors['amount'] = 'Unable to prepare airtimebundle table for change tracking. Please contact admin.';
            set_value('errors', $airtimebundle->errors);
            message_fail($airtimebundle->errors['amount']);
            return false;
        }
        return true;
    };

    $schemaReady = $ensureAirtimebundleColumns();
    if (!$schemaReady) {
        return;
    }



    $postdata = $req->post();

    $postdata['id'] = $row->id;
	$postdata['transaction_image'] = $row->transaction_image ?? '';

	$req->upload_folder = 'uploads/airtimebundle';
	$uploadedImage = $req->upload_files('transaction_image');
	if (!empty($uploadedImage)) {
		$postdata['transaction_image'] = $uploadedImage;
	}
	if (!empty($req->upload_errors)) {
		$airtimebundle->errors['transaction_image'] = $req->upload_errors['transaction_image'] ?? 'Transaction image could not be uploaded.';
		set_value('errors', array_merge($airtimebundle->errors, $req->upload_errors));
		message_fail($airtimebundle->errors['transaction_image']);
		return;
	}
	if (empty($postdata['transaction_image'])) {
		$airtimebundle->errors['transaction_image'] = 'Transaction image is required.';
		set_value('errors', $airtimebundle->errors);
		message_fail($airtimebundle->errors['transaction_image']);
		return;
	}

	$existingStatus = airtimebundle_normalize_status($row->transaction_status ?? '', 'Draft');
	$isSentLockedRecord = airtimebundle_status_is_sent($existingStatus);
	if ($isSentLockedRecord) {
		$requestedSentStatus = airtimebundle_normalize_status($postdata['transaction_status'] ?? $existingStatus, $existingStatus);
		$lockedFields = [
			'network',
			'transaction_type',
			'category',
			'sending_category',
			'phone',
			'receiver',
			'amount',
			'amount_paid',
			'cash_received_amount',
			'change_required',
			'change_denominations',
			'amount_to_send',
			'commission',
			'reseller_number',
			'data_size',
			'balance_after',
			'wallet_in_id',
			'wallet_out_id',
			'assigned',
			'disabled',
			'deleted',
			'created_by',
			'date_created',
			'date_deleted',
		];
		foreach ($lockedFields as $field) {
			$postdata[$field] = $row->{$field} ?? ($postdata[$field] ?? null);
		}

		$storedReceivedDenos = airtimebundle_fetch_saved_currency_rows((int)$row->id, 'received');
		$storedChangeDenos = airtimebundle_fetch_saved_currency_rows((int)$row->id, 'change');
		$storedReceivedJson = json_encode($storedReceivedDenos, JSON_UNESCAPED_SLASHES);
		$storedChangeJson = json_encode($storedChangeDenos, JSON_UNESCAPED_SLASHES);
		$storedReceivedJson = $storedReceivedJson === false ? '[]' : $storedReceivedJson;
		$storedChangeJson = $storedChangeJson === false ? '[]' : $storedChangeJson;

		$postdata['denos'] = $storedReceivedJson;
		$postdata['denos_mashup'] = $storedReceivedJson;
		$postdata['denos_special'] = $storedReceivedJson;
		$postdata['mashup_amount'] = $row->amount_to_send ?? ($row->amount ?? ($postdata['mashup_amount'] ?? 0));
		$postdata['mashup_paid'] = $row->amount_paid ?? ($postdata['mashup_paid'] ?? 0);
		$postdata['special_amount_load'] = $row->amount ?? ($row->amount_to_send ?? ($postdata['special_amount_load'] ?? 0));
		$postdata['special_amount_paid'] = $row->amount_paid ?? ($postdata['special_amount_paid'] ?? 0);
		$postdata['change_denominations'] = trim((string)($postdata['change_denominations'] ?? '')) !== ''
			? $postdata['change_denominations']
			: $storedChangeJson;
		$postdata['transaction_status'] = $requestedSentStatus;
	}

	$requestedStatus = airtimebundle_normalize_status($postdata['transaction_status'] ?? $existingStatus, $existingStatus);
	$requestedStatusUpper = strtoupper(trim((string)$requestedStatus));
	$rejectionReason = trim((string)($postdata['rejection_reason'] ?? ($row->rejection_reason ?? '')));
	$postdata['transaction_status'] = $requestedStatus;
	$postdata['rejection_reason'] = ($requestedStatusUpper === 'REJECTED') ? $rejectionReason : null;

	// Decode denominations JSON from the edit form (same logic as add)
	$transactionTypeIncoming = $postdata['transaction_type'] ?? ($row->transaction_type ?? '');
	$categoryIncoming = $postdata['category'] ?? ($row->category ?? '');
	$currencyDetails = '[]';
	$categoryUpperIncoming = strtoupper(trim((string)$categoryIncoming));
	if (in_array($categoryUpperIncoming, ['MASHUP', 'MTN-DATA', 'AT-DATA', 'TELECEL-DATA'], true)) {
		$currencyDetails = $postdata['denos_mashup'] ?? ($postdata['denos'] ?? '[]');
	} elseif (in_array($categoryUpperIncoming, ['5959', 'MTN UP2U', 'ISHARE', 'JUST4U'], true)) {
		$currencyDetails = $postdata['denos_special'] ?? ($postdata['denos'] ?? '[]');
	} else {
		$currencyDetails = $postdata['denos'] ?? '[]';
	}
	$currencyArray = json_decode((string)$currencyDetails, true);
	if (!is_array($currencyArray)) {
		$currencyArray = [];
	}
	$currencyArray = array_values(array_filter(array_map(function ($item) {
		if (!is_array($item)) {
			return null;
		}
		$den = isset($item['denomination']) ? (float)$item['denomination'] : 0.0;
		$cnt = isset($item['count']) ? (int)$item['count'] : 0;
		$vol = isset($item['volume']) ? (float)$item['volume'] : ($den * $cnt);
		if ($cnt <= 0 || $den <= 0) {
			return null;
		}
		return [
			'currency' => (string)($item['currency'] ?? 'GHS'),
			'row_index' => $item['row_index'] ?? null,
			'denomination' => $den,
			'count' => $cnt,
			'volume' => (float)number_format($vol, 2, '.', ''),
		];
	}, $currencyArray), function ($x) {
		return !empty($x);
	}));

	// Map edit form amount fields to the same canonical fields used by add flow.
	$transactionTypeIncoming = $postdata['transaction_type'] ?? ($row->transaction_type ?? '');
	$categoryIncoming = $postdata['category'] ?? ($row->category ?? '');
	$categoryUpperIncoming = strtoupper(trim((string)$categoryIncoming));

	if ($transactionTypeIncoming === 'Airtime') {
		$rawLoadAmount = $postdata['amount'] ?? ($row->amount ?? 0);
		$rawLoadAmountStr = is_string($rawLoadAmount) ? trim($rawLoadAmount) : $rawLoadAmount;
		$loadAmount = ($rawLoadAmount === null || $rawLoadAmountStr === '') ? 0 : (float)$rawLoadAmountStr;
		$paidAmount = (float)($postdata['amount_paid'] ?? ($row->amount_paid ?? $row->amount ?? 0));

		if ($categoryUpperIncoming === 'EVD' && $loadAmount <= 0 && $paidAmount > 0) {
			$loadAmount = $paidAmount;
		}

		$rawAmountToSend = $postdata['amount_to_send'] ?? ($row->amount_to_send ?? null);
		$rawAmountToSendStr = is_string($rawAmountToSend) ? trim($rawAmountToSend) : $rawAmountToSend;
		if ($rawAmountToSend === null || $rawAmountToSendStr === '' || (float)$rawAmountToSendStr <= 0) {
			$postdata['amount_to_send'] = $loadAmount;
		} else {
			$postdata['amount_to_send'] = $rawAmountToSend;
		}

		$postdata['amount_paid'] = $paidAmount;
		$postdata['amount'] = $loadAmount;
	} else {
		if (in_array($categoryUpperIncoming, ['MASHUP', 'MTN-DATA', 'AT-DATA', 'TELECEL-DATA'], true)) {
			$postdata['mashup_amount'] = $postdata['mashup_amount'] ?? ($row->amount ?? 0);
			$postdata['mashup_paid'] = $postdata['mashup_paid'] ?? ($postdata['amount_paid'] ?? ($row->amount_paid ?? $row->amount ?? 0));
			$postdata['amount_to_send'] = $postdata['mashup_amount'];
			$postdata['amount_paid'] = $postdata['mashup_paid'];
			$postdata['amount'] = $postdata['mashup_amount'];
		} elseif (in_array($categoryUpperIncoming, ['5959', 'MTN UP2U', 'ISHARE', 'JUST4U'], true)) {
			$postdata['special_amount_load'] = $postdata['special_amount_load'] ?? ($postdata['amount'] ?? ($row->amount ?? ($row->amount_to_send ?? 0)));
			$postdata['special_amount_paid'] = $postdata['special_amount_paid'] ?? ($postdata['amount_paid'] ?? ($row->amount_paid ?? $row->amount ?? 0));
			$postdata['amount_to_send'] = $isSentLockedRecord
				? ($row->amount_to_send ?? $postdata['special_amount_load'])
				: $postdata['special_amount_load'];
			$postdata['amount_paid'] = $postdata['special_amount_paid'];
			$postdata['amount'] = $postdata['special_amount_load'];
		} else {
			$postdata['amount'] = $postdata['amount'] ?? ($row->amount ?? 0);
			$postdata['amount_paid'] = $postdata['amount_paid'] ?? ($row->amount_paid ?? $postdata['amount'] ?? 0);
			$postdata['amount_to_send'] = $postdata['amount_to_send'] ?? ($row->amount_to_send ?? ($postdata['amount'] ?? 0));
		}
	}

	$numericFields = ['amount', 'amount_paid', 'amount_to_send', 'commission', 'balance_after', 'mashup_amount', 'mashup_paid', 'special_amount_load', 'special_amount_paid'];
	foreach ($numericFields as $field) {
		if (!array_key_exists($field, $postdata)) {
			continue;
		}

		$value = $postdata[$field];
		if ($value === '' || $value === null) {
			$postdata[$field] = 0;
			continue;
		}

		if (is_string($value)) {
			$value = str_replace([',', ' '], '', $value);
		}

		$postdata[$field] = (float)$value;
	}

	$isJust4uCategory = ($categoryUpperIncoming === 'JUST4U');
	$isAirtimeEvdCategory = ($transactionTypeIncoming === 'Airtime' && $categoryUpperIncoming === 'EVD');
	$just4uFixedCharge = 0.50;
	if (!$isSentLockedRecord && $isJust4uCategory) {
		$just4uLoadAmount = (float)($postdata['special_amount_load'] ?? ($postdata['amount'] ?? ($postdata['amount_to_send'] ?? 0)));
		$just4uEcashPayable = max(0, $just4uLoadAmount - $just4uFixedCharge);
		$postdata['amount_to_send'] = number_format($just4uEcashPayable, 2, '.', '');
		$postdata['special_amount_load'] = number_format($just4uLoadAmount, 2, '.', '');
		$postdata['amount'] = number_format($just4uLoadAmount, 2, '.', '');
		if (empty($postdata['special_amount_paid'])) {
			$postdata['special_amount_paid'] = number_format($just4uLoadAmount, 2, '.', '');
		}
		if (empty($postdata['amount_paid'])) {
			$postdata['amount_paid'] = $postdata['special_amount_paid'];
		}
		if (empty($postdata['cash_received_amount'])) {
			$postdata['cash_received_amount'] = $postdata['special_amount_paid'];
		}
		$postdata['commission'] = number_format($just4uFixedCharge, 2, '.', '');
	} elseif ($isAirtimeEvdCategory) {
		$evdLoadAmount = (float)($postdata['amount'] ?? ($postdata['amount_paid'] ?? ($row->amount ?? 0)));
		$postdata['amount_to_send'] = number_format($evdLoadAmount, 2, '.', '');
		$postdata['amount'] = number_format($evdLoadAmount, 2, '.', '');
		$postdata['commission'] = '0.00';
	}

	$mashupCategories = ['MASHUP', 'MTN-DATA', 'AT-DATA', 'TELECEL-DATA'];
	$specialCategories = ['5959', 'MTN UP2U', 'ISHARE', 'JUST4U'];
$isAirtimeChangeEligible = ($transactionTypeIncoming === 'Airtime' && in_array($categoryUpperIncoming, ['AIRTIME', 'CREDIT TRANSFER', 'EVD'], true));
	$isBundleChangeEligible = ($transactionTypeIncoming !== 'Airtime' && (in_array($categoryUpperIncoming, $mashupCategories, true) || in_array($categoryUpperIncoming, $specialCategories, true)));
	$isChangeEligible = ($isAirtimeChangeEligible || $isBundleChangeEligible);
	$paidFieldKey = 'amount_paid';
	if (in_array($categoryUpperIncoming, $mashupCategories, true)) {
		$paidFieldKey = 'mashup_paid';
	} elseif (in_array($categoryUpperIncoming, $specialCategories, true)) {
		$paidFieldKey = 'special_amount_paid';
	}
	if (array_key_exists('amount_paid', $postdata) && !array_key_exists('mashup_paid', $postdata) && !array_key_exists('special_amount_paid', $postdata)) {
		$paidFieldKey = 'amount_paid';
	}

	if ($transactionTypeIncoming === 'Airtime') {
		$payableAmount = (float)($postdata['amount'] ?? 0);
	} elseif (in_array($categoryUpperIncoming, $mashupCategories, true)) {
		$payableAmount = (float)($postdata['mashup_amount'] ?? ($postdata['amount'] ?? 0));
	} elseif (in_array($categoryUpperIncoming, $specialCategories, true)) {
		$payableAmount = (float)($postdata['special_amount_load'] ?? ($postdata['amount'] ?? 0));
	} else {
		$payableAmount = (float)($postdata['amount'] ?? 0);
	}
	$payableAmount = (float)number_format($payableAmount, 2, '.', '');

	$cashReceivedAmount = (float)($postdata['cash_received_amount'] ?? ($postdata['amount_paid'] ?? ($row->amount_paid ?? $row->amount ?? 0)));
	$cashReceivedAmount = (float)number_format($cashReceivedAmount, 2, '.', '');
	$changeRequired = (float)($postdata['change_required'] ?? 0);
	$changeRequired = (float)number_format($changeRequired, 2, '.', '');
	$changeDenominations = json_decode($postdata['change_denominations'] ?? '[]', true);
	if (!is_array($changeDenominations)) {
		$changeDenominations = [];
	}
	$changeDenominations = array_values(array_filter(array_map(function ($changeRow) {
		$denomination = (float)($changeRow['denomination'] ?? 0);
		$count = (int)($changeRow['count'] ?? 0);
		$volume = (float)($changeRow['volume'] ?? ($denomination * $count));
		if ($denomination <= 0 || $count <= 0 || $volume <= 0) {
			return null;
		}
		return [
			'currency' => (string)($changeRow['currency'] ?? 'GHS'),
			'denomination' => $denomination,
			'count' => $count,
			'volume' => (float)number_format($volume, 2, '.', ''),
		];
	}, $changeDenominations)));

	$changeGivenTotal = 0.0;
	foreach ($changeDenominations as $changeRow) {
		$changeGivenTotal += (float)($changeRow['volume'] ?? 0);
	}
	$changeGivenTotal = (float)number_format($changeGivenTotal, 2, '.', '');



    $csrf = csrf_verify($postdata);

	if ($csrf && $requestedStatusUpper === 'REJECTED' && $rejectionReason === '') {
		$airtimebundle->errors['rejection_reason'] = 'Rejection reason is required when rejecting an airtimebundle transaction.';
	}

    if ($csrf && empty($airtimebundle->errors) && $airtimebundle->validate_update($postdata)) {

        

        $postdata['date_updated'] = date("Y-m-d H:i:s");



        // Get user session details

        $session = get_session();

        $username = $session->id ? $session->first_name . ' ' . $session->last_name : $row->first_name . ' ' . $row->last_name;

        $user_id = $session->id ?? $row->id;

        $postdata['updated_by'] = $user_id;



        // Check if status changed for cashdrawer sync

        $oldStatus = airtimebundle_normalize_status($row->transaction_status ?? '', 'Draft');

        $newStatus = airtimebundle_normalize_status($postdata['transaction_status'] ?? $oldStatus, $oldStatus);
		$postdata['transaction_status'] = $newStatus;

        $statusChanged = (strcasecmp($oldStatus, $newStatus) !== 0);

		// In Airtimebundle, accounting sync occurs only when transaction is Sent
		$oldStatusUpper = strtoupper(trim((string)$oldStatus));
		$newStatusUpper = strtoupper(trim((string)$newStatus));
		$oldWasSent = ($oldStatusUpper === 'SENT');
		$newIsSent = ($newStatusUpper === 'SENT');
		$isSentRejection = ($oldWasSent && $newStatusUpper === 'REJECTED');
		if ($oldWasSent && !$isSentRejection) {
			$airtimebundle->errors['transaction_status'] = 'Sent airtimebundle transactions can only be changed to Rejected.';
			set_value('errors', $airtimebundle->errors);
			message_fail($airtimebundle->errors['transaction_status']);
			goto end_edit;
		}

		// Resolve cashdrawer (used by both cash + ecash sync)
		$drawer_id = 0;
		try {
			$drawerDb = new \Core\Database;
			$today = date('Y-m-d');
			$drawerRow = $drawerDb->query(
				"SELECT id FROM cashdrawers WHERE user_id = :user_id AND DATE(date_created) = :today ORDER BY id DESC LIMIT 1",
				['user_id' => $user_id, 'today' => $today]
			);
			if (empty($drawerRow)) {
				$drawerRow = $drawerDb->query(
					"SELECT id FROM cashdrawers WHERE user_id = :user_id ORDER BY date_created DESC, id DESC LIMIT 1",
					['user_id' => $user_id]
				);
			}
			if (!empty($drawerRow)) {
				$drawer_id = (int)($drawerRow[0]->id ?? 0);
			}
		} catch (\Throwable $e) {
			$drawer_id = 0;
		}

		$drawerUserId = $user_id;
		if ($drawer_id > 0) {
			try {
				$dbDrawerUser = new \Core\Database;
				$userRow = $dbDrawerUser->query("SELECT user_id FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawer_id]);
				if (!empty($userRow)) {
					$drawerUserId = (int)($userRow[0]->user_id ?? $user_id);
				}
			} catch (\Throwable $e) {
				$drawerUserId = $user_id;
			}
		}

		// Compute new vs old amounts used for ecash debit
		$oldDebitAmount = (float)($row->amount_to_send ?? 0);
		if ($oldDebitAmount <= 0) {
			$oldDebitAmount = (float)($row->amount ?? 0);
		}
		$newDebitAmount = (float)($postdata['amount_to_send'] ?? 0);
		if ($newDebitAmount <= 0) {
			$newDebitAmount = (float)($postdata['amount'] ?? 0);
		}

		$denoTotal = 0.0;
		foreach (($currencyArray ?? []) as $currencyRow) {
			$denoTotal += (float)($currencyRow['volume'] ?? 0);
		}
		$denoTotal = (float)number_format($denoTotal, 2, '.', '');

		if ($cashReceivedAmount <= 0 && $denoTotal > 0) {
			$cashReceivedAmount = $denoTotal;
		}

		if ($denoTotal > 0 && abs($denoTotal - $cashReceivedAmount) > 0.01) {
			$airtimebundle->errors[$paidFieldKey] = 'Cash denominations total (' . number_format($denoTotal, 2) . ') must match Amount Paid (' . number_format($cashReceivedAmount, 2) . ').';
			set_value('errors', $airtimebundle->errors);
			message_fail($airtimebundle->errors[$paidFieldKey]);
			goto end_edit;
		}

		if ($isChangeEligible) {
			if ($cashReceivedAmount + 0.01 < $payableAmount) {
				$airtimebundle->errors[$paidFieldKey] = 'Amount paid cannot be less than the payable amount (' . number_format($payableAmount, 2) . ').';
				set_value('errors', $airtimebundle->errors);
				message_fail($airtimebundle->errors[$paidFieldKey]);
				goto end_edit;
			}

			$computedChangeRequired = ($cashReceivedAmount > $payableAmount)
				? (float)number_format($cashReceivedAmount - $payableAmount, 2, '.', '')
				: 0.0;

			if ($computedChangeRequired > 0.01) {
				if ($changeRequired <= 0 || abs($changeRequired - $computedChangeRequired) > 0.01) {
					$airtimebundle->errors['change_required'] = 'Change required does not match the excess cash received.';
					set_value('errors', $airtimebundle->errors);
					message_fail($airtimebundle->errors['change_required']);
					goto end_edit;
				}

				if (empty($changeDenominations)) {
					$airtimebundle->errors['change_denominations'] = 'Select the denominations to give out as change.';
					set_value('errors', $airtimebundle->errors);
					message_fail($airtimebundle->errors['change_denominations']);
					goto end_edit;
				}

				if (abs($changeGivenTotal - $computedChangeRequired) > 0.01) {
					$airtimebundle->errors['change_denominations'] = 'Selected change denominations must total GHS ' . number_format($computedChangeRequired, 2) . '.';
					set_value('errors', $airtimebundle->errors);
					message_fail($airtimebundle->errors['change_denominations']);
					goto end_edit;
				}
			} else {
				$changeRequired = 0.0;
				$changeDenominations = [];
				$changeGivenTotal = 0.0;
				$postdata['change_required'] = '';
				$postdata['change_denominations'] = '[]';
			}

			$netTransactionAmount = (float)number_format($cashReceivedAmount - $changeGivenTotal, 2, '.', '');
			if ($payableAmount > 0 && abs($netTransactionAmount - $payableAmount) > 0.01) {
				$airtimebundle->errors['change_denominations'] = 'Net cash after change must match the payable amount.';
				set_value('errors', $airtimebundle->errors);
				message_fail($airtimebundle->errors['change_denominations']);
				goto end_edit;
			}

			$postdata['cash_received_amount'] = number_format($cashReceivedAmount, 2, '.', '');
			$postdata['amount_paid'] = number_format($cashReceivedAmount, 2, '.', '');
			$postdata['amount'] = number_format($netTransactionAmount, 2, '.', '');
			if ($changeRequired > 0.01) {
				$postdata['change_required'] = number_format($changeRequired, 2, '.', '');
				$postdata['change_denominations'] = json_encode($changeDenominations, JSON_UNESCAPED_SLASHES);
			}
		} else {
			if ($payableAmount > 0 && abs($cashReceivedAmount - $payableAmount) > 0.01) {
				$airtimebundle->errors[$paidFieldKey] = 'Amount paid must match the transaction amount (' . number_format($payableAmount, 2) . ').';
				set_value('errors', $airtimebundle->errors);
				message_fail($airtimebundle->errors[$paidFieldKey]);
				goto end_edit;
			}
			$postdata['amount_paid'] = number_format($cashReceivedAmount, 2, '.', '');
			$postdata['amount'] = number_format($payableAmount, 2, '.', '');
			$postdata['change_required'] = '';
			$postdata['change_denominations'] = '[]';
		}

		$amountPaid = (float)($postdata['amount_paid'] ?? 0);
		if ($newIsSent && $amountPaid > 0 && empty($currencyArray)) {
			$airtimebundle->errors['amount_paid'] = 'Please select cash denominations for this transaction.';
			set_value('errors', $airtimebundle->errors);
			message_fail('Please select cash denominations for this transaction.');
			goto end_edit;
		}

		if ($newIsSent && $isChangeEligible && !empty($changeDenominations)) {
			if ($drawer_id <= 0) {
				$airtimebundle->errors['change_denominations'] = 'Cashdrawer not found for this user, so change denominations cannot be validated.';
				set_value('errors', $airtimebundle->errors);
				message_fail($airtimebundle->errors['change_denominations']);
				goto end_edit;
			}

			$dbChangeInv = new \Core\Database;
			if (!$dbChangeInv->table_exists(['drawer_denos'])) {
				$airtimebundle->errors['change_denominations'] = 'Cannot validate change because drawer inventory table (drawer_denos) is missing. Please contact admin.';
				set_value('errors', $airtimebundle->errors);
				message_fail($airtimebundle->errors['change_denominations']);
				goto end_edit;
			}

			$drawerCounts = [];
			$invRows = $dbChangeInv->query(
				"SELECT denomination, SUM(count) AS total_count FROM drawer_denos WHERE drawer_id = :drawer_id AND user_id = :user_id GROUP BY denomination",
				['drawer_id' => $drawer_id, 'user_id' => $drawerUserId]
			);
			if (!empty($invRows)) {
				foreach ($invRows as $invRow) {
					$key = number_format((float)($invRow->denomination ?? 0), 2, '.', '');
					$drawerCounts[$key] = (int)($invRow->total_count ?? 0);
				}
			}

			foreach (($currencyArray ?? []) as $receivedRow) {
				$key = number_format((float)($receivedRow['denomination'] ?? 0), 2, '.', '');
				if ($key === '0.00') {
					continue;
				}
				$drawerCounts[$key] = (int)($drawerCounts[$key] ?? 0) + (int)($receivedRow['count'] ?? 0);
			}

			$requestedCounts = [];
			foreach ($changeDenominations as $changeRow) {
				$key = number_format((float)($changeRow['denomination'] ?? 0), 2, '.', '');
				if ($key === '0.00') {
					continue;
				}
				$requestedCounts[$key] = (int)($requestedCounts[$key] ?? 0) + abs((int)($changeRow['count'] ?? 0));
			}

			$insufficient = [];
			foreach ($requestedCounts as $denKey => $need) {
				$have = (int)($drawerCounts[$denKey] ?? 0);
				if ($need > $have) {
					$insufficient[] = 'GHS ' . $denKey . ' (need ' . $need . ', available ' . $have . ')';
				}
			}

			if (!empty($insufficient)) {
				$airtimebundle->errors['change_denominations'] = 'Insufficient denominations available for change in drawer #' . $drawer_id . '. ' . implode(' | ', $insufficient);
				set_value('errors', $airtimebundle->errors);
				message_fail($airtimebundle->errors['change_denominations']);
				goto end_edit;
			}
		}

		// Guards: prevent e-cash channel from going negative when Sent (apply delta only)
		if ($newIsSent && $drawer_id > 0) {
			$deltaDebit = $newDebitAmount - ($oldWasSent ? $oldDebitAmount : 0.0);
			if ($deltaDebit > 0.00001) {
				try {
					$dbBalGuard = new \Core\Database;
					if ($dbBalGuard->table_exists(['drawer_balances'])) {
						$network = trim((string)($postdata['network'] ?? ($row->network ?? '')));
						$catUpper = strtoupper(trim((string)($postdata['category'] ?? ($row->category ?? ''))));
						$txTypeUpper = strtoupper(trim((string)($postdata['transaction_type'] ?? ($row->transaction_type ?? ''))));
						$isMtnEvdBundle = (strtoupper($network) === 'MTN' && in_array($catUpper, ['MASHUP', 'JUST4U', 'MTN-DATA'], true));
						$isEvd = ($catUpper === 'EVD');
						$networkUpper = strtoupper($network);
						$isTelecelEvdAirtime = ($txTypeUpper === 'AIRTIME' && $networkUpper === 'TELECEL' && $isEvd);
						$networkKey = strtoupper(str_replace(' ', '_', $network));
						$ecashBaseKey = in_array($networkUpper, ['MTN', 'TELECEL', 'AT'], true) ? $networkUpper : $networkKey;
						$desiredChannelKey = 'ECASH_' . $ecashBaseKey . (($isEvd && !$isTelecelEvdAirtime) ? '_EVD' : '');
						if ($isMtnEvdBundle) {
							$keysToTry = array_values(array_unique([
								'ECASH_MTN_EVD',
								'MTN-EVD',
								'MTN_EVD',
								'ECASH_MTN-EVD',
								'ECASH_MTN_EVD_OUT',
								'ECASH_MTN-EVD-OUT',
							]));
						} elseif ($isTelecelEvdAirtime) {
							$keysToTry = array_values(array_unique([
								$desiredChannelKey,
								'ECASH_' . $networkUpper,
								'ECASH_' . $networkKey,
								$networkUpper,
								$networkKey,
								'BANK_' . $networkUpper,
								'BANK_' . $networkKey,
							]));
						} else {
							$keysToTry = [];
							if ($isEvd) {
								$keysToTry = array_merge($keysToTry, [
									$desiredChannelKey,
									'ECASH_' . $ecashBaseKey . '_EVD',
									$ecashBaseKey . '_EVD',
									$ecashBaseKey . '-EVD',
									'ECASH_' . $ecashBaseKey . '-EVD',
									'ECASH_' . $ecashBaseKey . '_EVD_OUT',
									'ECASH_' . $ecashBaseKey . '-EVD-OUT',
								]);
							}
							$keysToTry = array_merge($keysToTry, [
								$networkUpper,
								$networkKey,
								'ECASH_' . $networkUpper,
								'ECASH_' . $networkKey,
								'BANK_' . $networkUpper,
								'BANK_' . $networkKey,
							]);
							$keysToTry = array_values(array_unique($keysToTry));
						}

						$resolvedKey = null;
						$currentBalance = 0.0;
						foreach ($keysToTry as $k) {
							$rows = $dbBalGuard->query(
								"SELECT channel_key, closing_balance FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash' AND channel_key = :channel_key ORDER BY id DESC LIMIT 1",
								['drawer_id' => $drawer_id, 'user_id' => $drawerUserId, 'channel_key' => $k]
							);
							if (!empty($rows)) {
								$resolvedKey = (string)($rows[0]->channel_key ?? $k);
								$currentBalance = (float)($rows[0]->closing_balance ?? 0);
								break;
							}
						}
						if ($resolvedKey === null) {
							$airtimebundle->errors['network'] = 'E-cash channel not found for network ' . $networkUpper . '. Please add the channel to the cashdrawer before sending.';
							set_value('errors', $airtimebundle->errors);
							message_fail($airtimebundle->errors['network']);
							goto end_edit;
						}

						$projected = $currentBalance - $deltaDebit;
						if ($projected < -0.00001) {
							$airtimebundle->errors['amount'] = 'Insufficient e-cash balance in channel ' . $resolvedKey . ' (available ' . number_format($currentBalance, 2) . ', required ' . number_format($deltaDebit, 2) . ')';
							set_value('errors', $airtimebundle->errors);
							message_fail($airtimebundle->errors['amount']);
							goto end_edit;
						}
						$postdata['balance_after'] = number_format($projected, 2, '.', '');
					}
				} catch (\Throwable $e) {
					// If lookup fails, block posting to prevent silent negative balances
					$airtimebundle->errors['network'] = 'Unable to validate e-cash channel balance. Please try again.';
					set_value('errors', $airtimebundle->errors);
					message_fail($airtimebundle->errors['network']);
					goto end_edit;
				}
			}
		}



        // Update airtimebundle and related tables

		$sentReversal = null;
		if ($isSentRejection) {
			$sentReversal = airtimebundle_reverse_sent_effects((object)array_merge((array)$row, $postdata), $newStatus);
			if (empty($sentReversal['ok'])) {
				$airtimebundle->errors['transaction_status'] = $sentReversal['message'] ?: 'Unable to reverse the sent transaction impacts.';
				set_value('errors', $airtimebundle->errors);
				message_fail($airtimebundle->errors['transaction_status']);
				goto end_edit;
			}

			if ($sentReversal['ecash_closing_balance'] !== null) {
				$postdata['balance_after'] = number_format((float)$sentReversal['ecash_closing_balance'], 2, '.', '');
			}
		}

        unset($postdata['id']);

        //dd($postdata);die;

		// Update denominations rows to match the edited values
		try {
			$denoDbUpdate = new \Core\Database;
			$denoDbUpdate->query("DELETE FROM airtimebundles_denos WHERE trans_id = :trans_id", ['trans_id' => $row->id]);
			$denoDbUpdate->query("DELETE FROM denos WHERE trans_id = :trans_id", ['trans_id' => $row->id]);
		} catch (\Throwable $e) {}
		if (!empty($currencyArray)) {
			foreach ($currencyArray as $currency) {
				try {
					$denosPost = [
						'trans_id' => $row->id,
						'trans_status' => $newStatus,
						'currency' => $currency['currency'] ?? 'GHS',
						'denomination' => (float)($currency['denomination'] ?? 0),
						'count' => (int)($currency['count'] ?? 0),
						'volume' => (float)($currency['volume'] ?? 0),
						'created_by' => $user_id,
						'date_created' => $postdata['date_updated'] ?? date('Y-m-d H:i:s'),
						'user_id' => $user_id,
					];
					$deno->insert($denosPost);
				} catch (\Throwable $e) {}
			}
		}
		if ($isChangeEligible && !empty($changeDenominations)) {
			foreach ($changeDenominations as $currency) {
				try {
					$denosPost = [
						'trans_id' => $row->id,
						'trans_status' => 'Change',
						'currency' => $currency['currency'] ?? 'GHS',
						'denomination' => (float)($currency['denomination'] ?? 0),
						'count' => -1 * abs((int)($currency['count'] ?? 0)),
						'volume' => -1 * abs((float)($currency['volume'] ?? 0)),
						'created_by' => $user_id,
						'date_created' => $postdata['date_updated'] ?? date('Y-m-d H:i:s'),
						'user_id' => $user_id,
					];
					$deno->insert($denosPost);
				} catch (\Throwable $e) {}
			}
		}

		$airtimebundle->update($row->id, $postdata);

		if ($isSentRejection) {
			try {
				$statusDb = new \Core\Database;
				$statusParams = [
					'transaction_status' => 'Rejected',
					'rejection_reason' => $rejectionReason,
					'date_updated' => $postdata['date_updated'] ?? date('Y-m-d H:i:s'),
					'updated_by' => $user_id,
					'id' => (int)$row->id,
				];
				$statusDb->query(
					"UPDATE airtimebundles
					 SET transaction_status = :transaction_status,
					     rejection_reason = :rejection_reason,
					     date_updated = :date_updated,
					     updated_by = :updated_by
					 WHERE id = :id",
					$statusParams
				);
				if ($statusDb->table_exists(['drawer_denos'])) {
					$statusDb->query(
						"UPDATE drawer_denos SET trans_status = :transaction_status WHERE trans_id = :id",
						['transaction_status' => 'Rejected', 'id' => (int)$row->id]
					);
				}
				if ($statusDb->table_exists(['drawer_balances'])) {
					$statusDb->query(
						"UPDATE drawer_balances SET trans_status = :transaction_status WHERE trans_id = :id",
						['transaction_status' => 'Rejected', 'id' => (int)$row->id]
					);
				}
			} catch (\Throwable $e) {
				error_log('AIRTIMEBUNDLE SENT REJECTION STATUS FORCE UPDATE FAILED: ' . $e->getMessage());
			}
			$postdata['transaction_status'] = 'Rejected';
		}



		$txTypeForCommission = strtolower(trim((string)($postdata['transaction_type'] ?? ($row->transaction_type ?? ''))));

		$categoryForCommission = strtoupper(trim((string)($postdata['category'] ?? ($row->category ?? ''))));

		$networkForCommission = strtoupper(trim((string)($postdata['network'] ?? ($row->network ?? ''))));

		$commissionAmount = 0.0;



		if ($categoryForCommission === 'JUST4U') {

			$commissionAmount = 0.50;

		}



		try {

			$commissionDb = new \Core\Database;

			$commissionDb->query(

				"DELETE FROM commissions WHERE colletion_period = :period AND commission_type = :commission_type AND description LIKE :needle",

				[

					'period' => 'INSTANT',

					'commission_type' => 'AIRTIMEBUNDLE INSTANT',

					'needle' => '%| ID ' . ($row->id ?? ''),

				]

			);



			if ($commissionAmount > 0 && $newIsSent) {

				$commissionDescription = 'AIRTIMEBUNDLE INSTANT | ' . strtoupper((string)($postdata['transaction_type'] ?? ($row->transaction_type ?? ''))) . ' | ' . strtoupper((string)($postdata['category'] ?? ($row->category ?? ''))) . ' | ' . strtoupper((string)($postdata['network'] ?? ($row->network ?? ''))) . ' | ID ' . ($row->id ?? '');

				$commissionInsert = [

					'date_received' => $postdata['date_updated'] ?? date('Y-m-d H:i:s'),

					'colletion_period' => 'INSTANT',

					'network' => $postdata['network'] ?? ($row->network ?? null),

					'commission_type' => 'AIRTIMEBUNDLE INSTANT',

					'description' => $commissionDescription,

					'pay_to' => $user_id,

					'receiving_wallet' => null,

					'amount' => $commissionAmount,

					'transaction_status' => 'Approved',

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

			error_log('AIRTIMEBUNDLE INSTANT COMMISSION UPSERT FAILED (EDIT): ' . $e->getMessage());

		}



		// Sync to cashdrawer/ecash if needed (Sent-only accounting)
		if (!$isSentRejection && $drawer_id > 0 && ($statusChanged || $newIsSent || $oldWasSent)) {
			try {
				$dbUpdate = new \Core\Database;
				$trans_id = $row->id;
				$dbUpdate->query("UPDATE drawer_denos SET trans_status = :trans_status WHERE trans_id = :trans_id", ['trans_status' => $newStatus, 'trans_id' => $trans_id]);
				$dbUpdate->query("UPDATE drawer_balances SET trans_status = :trans_status WHERE trans_id = :trans_id", ['trans_status' => $newStatus, 'trans_id' => $trans_id]);

				// CASH DENOS: when Sent, persist this transaction's denos into drawer_denos; otherwise remove them
				$dbDrawerDeno = new \Core\Database;
				if ($dbDrawerDeno->table_exists(['drawer_denos'])) {
					$now = date('Y-m-d H:i:s');
					$dbDrawerDeno->query("DELETE FROM drawer_denos WHERE trans_id = :trans_id", ['trans_id' => $row->id]);
					if ($newIsSent && !empty($currencyArray)) {
						foreach ($currencyArray as $denoRow) {
							$sqlDeno = "INSERT INTO drawer_denos (drawer_id, user_id, denomination, count, volume, date_created, created_by, trans_id, trans_status) VALUES (:drawer_id, :user_id, :denomination, :count, :volume, :date_created, :created_by, :trans_id, :trans_status)";
							$paramsDeno = [
								'drawer_id' => $drawer_id,
								'user_id' => $drawerUserId,
								'denomination' => $denoRow['denomination'],
								'count' => (int)($denoRow['count'] ?? 0),
								'volume' => (float)($denoRow['volume'] ?? 0),
								'date_created' => $now,
								'created_by' => $drawerUserId,
								'trans_id' => $row->id,
								'trans_status' => $newStatus,
							];
							$dbDrawerDeno->query($sqlDeno, $paramsDeno);
						}
						if ($isChangeEligible && !empty($changeDenominations)) {
							foreach ($changeDenominations as $denoRow) {
								$sqlDeno = "INSERT INTO drawer_denos (drawer_id, user_id, denomination, count, volume, date_created, created_by, trans_id, trans_status) VALUES (:drawer_id, :user_id, :denomination, :count, :volume, :date_created, :created_by, :trans_id, :trans_status)";
								$paramsDeno = [
									'drawer_id' => $drawer_id,
									'user_id' => $drawerUserId,
									'denomination' => $denoRow['denomination'],
									'count' => -1 * abs((int)($denoRow['count'] ?? 0)),
									'volume' => -1 * abs((float)($denoRow['volume'] ?? 0)),
									'date_created' => $now,
									'created_by' => $drawerUserId,
									'trans_id' => $row->id,
									'trans_status' => 'Change',
								];
								$dbDrawerDeno->query($sqlDeno, $paramsDeno);
							}
						}
					}
				}

				// Recompute CASH_DRAWER balance snapshot from drawer_denos
				try {
					$now = date('Y-m-d H:i:s');
					$sumRows = $dbUpdate->query(
						"SELECT SUM(volume) AS total_volume FROM drawer_denos WHERE drawer_id = :drawer_id AND user_id = :user_id",
						['drawer_id' => $drawer_id, 'user_id' => $drawerUserId]
					);
					$currentClosing = !empty($sumRows) ? (float)($sumRows[0]->total_volume ?? 0) : 0.0;
					$dbBal = new \Core\Database;
					if ($dbBal->table_exists(['drawer_balances'])) {
						$rowsBal = $dbBal->query(
							"SELECT * FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'cash' AND channel_key = 'CASH_DRAWER' LIMIT 1",
							['drawer_id' => $drawer_id, 'user_id' => $drawerUserId]
						);
						$hdr = $dbBal->query("SELECT amount FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawer_id]);
						$opening = !empty($hdr) ? (float)($hdr[0]->amount ?? 0) : 0.0;
						$delta = $currentClosing - $opening;
						$credit = $delta > 0 ? $delta : 0;
						$debit = $delta < 0 ? -$delta : 0;
						if (empty($rowsBal)) {
							$insertSql = "INSERT INTO drawer_balances (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status) VALUES (:drawer_id, :user_id, 'cash', 'CASH_DRAWER', :opening, :credit, :debit, :closing, :date_created, :trans_id, :trans_status)";
							$paramsBal = [
								'drawer_id' => $drawer_id,
								'user_id' => $drawerUserId,
								'opening' => $opening,
								'credit' => $credit,
								'debit' => $debit,
								'closing' => $currentClosing,
								'date_created' => $now,
								'trans_id' => $row->id,
								'trans_status' => $newStatus,
							];
							$dbBal->query($insertSql, $paramsBal);
						} else {
							$rowBal = $rowsBal[0];
							$updateSql = "UPDATE drawer_balances SET credit = :credit, debit = :debit, closing_balance = :closing, trans_id = :trans_id, trans_status = :trans_status WHERE id = :id";
							$paramsBal = [
								'credit' => $credit,
								'debit' => $debit,
								'closing' => $currentClosing,
								'trans_id' => $row->id,
								'trans_status' => $newStatus,
								'id' => $rowBal->id,
							];
							$dbBal->query($updateSql, $paramsBal);
						}
					}
				} catch (\Throwable $e) {}

				// E-CASH update-only sync (never insert): apply delta when entering/while Sent, reverse when leaving Sent
					$dbDrawerDeno = new \Core\Database;
				if (!empty($postdata['network'] ?? $row->network) && ($newIsSent || $oldWasSent)) {
					try {
						$dbBal = new \Core\Database;
						if ($dbBal->table_exists(['drawer_balances'])) {
							$networkUpper = strtoupper(trim((string)($postdata['network'] ?? ($row->network ?? ''))));
							$networkKey = strtoupper(str_replace(' ', '_', (string)($postdata['network'] ?? ($row->network ?? ''))));
							$catUpper = strtoupper(trim((string)($postdata['category'] ?? ($row->category ?? ''))));
							$txTypeUpper = strtoupper(trim((string)($postdata['transaction_type'] ?? ($row->transaction_type ?? ''))));
							$isMtnEvdBundle = ($networkUpper === 'MTN' && in_array($catUpper, ['MASHUP', 'JUST4U', 'MTN-DATA'], true));
							$isEvd = ($catUpper === 'EVD');
							$isTelecelEvdAirtime = ($txTypeUpper === 'AIRTIME' && $networkUpper === 'TELECEL' && $isEvd);
							$ecashBaseKey = in_array($networkUpper, ['MTN', 'TELECEL', 'AT'], true) ? $networkUpper : $networkKey;
							$desiredChannelKey = 'ECASH_' . $ecashBaseKey . (($isEvd && !$isTelecelEvdAirtime) ? '_EVD' : '');
							if ($isMtnEvdBundle) {
								$keysToTry = array_values(array_unique([
									'ECASH_MTN_EVD',
									'MTN-EVD',
									'MTN_EVD',
									'ECASH_MTN-EVD',
									'ECASH_MTN_EVD_OUT',
									'ECASH_MTN-EVD-OUT',
								]));
							} elseif ($isTelecelEvdAirtime) {
								$keysToTry = array_values(array_unique([
									$desiredChannelKey,
									'ECASH_' . $networkUpper,
									'ECASH_' . $networkKey,
									$networkUpper,
									$networkKey,
								]));
							} else {
								$keysToTry = [];
								if ($isEvd) {
									$keysToTry = array_merge($keysToTry, [
										$desiredChannelKey,
										'ECASH_' . $ecashBaseKey . '_EVD',
										$ecashBaseKey . '_EVD',
										$ecashBaseKey . '-EVD',
										'ECASH_' . $ecashBaseKey . '-EVD',
										'ECASH_' . $ecashBaseKey . '_EVD_OUT',
										'ECASH_' . $ecashBaseKey . '-EVD-OUT',
									]);
								}
								$keysToTry = array_merge($keysToTry, [
									$networkUpper,
									$networkKey,
									'ECASH_' . $networkUpper,
									'ECASH_' . $networkKey,
									$desiredChannelKey,
								]);
							}
							$resolvedKey = null;
							foreach ($keysToTry as $k) {
								$rowsKey = $dbBal->query("SELECT channel_key FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash' AND channel_key = :channel_key ORDER BY id DESC LIMIT 1", ['drawer_id' => $drawer_id, 'user_id' => $drawerUserId, 'channel_key' => $k]);
								if (!empty($rowsKey) && !empty($rowsKey[0]->channel_key)) { $resolvedKey = (string)$rowsKey[0]->channel_key; break; }
							}
							if (!empty($resolvedKey)) { $desiredChannelKey = $resolvedKey; }
							$rowsEcash = $dbBal->query("SELECT * FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash' AND channel_key = :channel_key LIMIT 1", ['drawer_id' => $drawer_id, 'user_id' => $drawerUserId, 'channel_key' => $desiredChannelKey]);

							$transactionAmountOld = $oldWasSent ? $oldDebitAmount : 0.0;
							$transactionAmountNew = $newIsSent ? $newDebitAmount : 0.0;
							$deltaDebit = $transactionAmountNew - $transactionAmountOld;
							if (abs($deltaDebit) > 0.00001 && !empty($rowsEcash)) {
								$rowEcash = $rowsEcash[0];
								$ecashOpening = (float)($rowEcash->opening_balance ?? 0);
								$ecashCredit = (float)($rowEcash->credit ?? 0) + ($deltaDebit < 0 ? abs($deltaDebit) : 0.0);
								$ecashDebit = (float)($rowEcash->debit ?? 0) + ($deltaDebit > 0 ? $deltaDebit : 0.0);
								$ecashClosing = $ecashOpening + $ecashCredit - $ecashDebit;
								$updateEcashSql = "UPDATE drawer_balances SET channel_key = :channel_key, credit = :credit, debit = :debit, closing_balance = :closing, trans_id = :trans_id, trans_status = :trans_status WHERE id = :id";
								$paramsEcash = ['channel_key' => $desiredChannelKey, 'credit' => $ecashCredit, 'debit' => $ecashDebit, 'closing' => $ecashClosing, 'trans_id' => $row->id, 'trans_status' => $newStatus, 'id' => $rowEcash->id];
								$dbBal->query($updateEcashSql, $paramsEcash);
							}
						}
					} catch (\Throwable $e) {}
				}
			} catch (\Throwable $e) {}
		}

        /// Log activity

        $action = 'updated';

        $transaction = $postdata['network'] . ' ' .  $postdata['transaction_type'];

        $transaction_id = $row->id;

        $table = 'airtimebundles';

        $description = "$username $action $transaction with id $transaction_id to $table";



        $log->insert([

            'table_name' => $table,

            'action' => $action,

            'user_id' => $user_id,

            'user_name' => $username,

            'description' => $description,

            'date_created' => date("Y-m-d H:i:s")

        ]);



		message_success("Transaction with id ($row->id) edited successfully!");
		redirect($admin_route . '/' . $plugin_route);

    } elseif (!$csrf) {

        $airtimebundle->errors['network'] = "Form expired!";

    }



    set_value('errors', array_merge($airtimebundle->errors, $req->upload_errors));
	end_edit:

} else {

    message_fail("Record not found");

}

?>

<?php



// Set timezone to Accra (Africa/Accra)

date_default_timezone_set('Africa/Accra');



$log = new \Airtimebundles\Log;

$deno = new \Airtimebundles\Deno;

$transaction = new \Airtimebundles\Transaction;

$ensureAirtimebundleColumns = function() {
	try {
		$dbSchema = new \Core\Database;
	$neededCols = [
		'amount_paid',
		'amount_to_send',
		'sending_category',
		'cash_received_amount',
		'change_required',
		'change_denominations',
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
				'amount_paid' => 'FLOAT(10,2) DEFAULT 0.00',
				'amount_to_send' => 'FLOAT(10,2) DEFAULT 0.00',
				'sending_category' => 'VARCHAR(50) NULL',
				'cash_received_amount' => 'FLOAT(10,2) DEFAULT 0.00',
				'change_required' => 'FLOAT(10,2) DEFAULT 0.00',
				'change_denominations' => 'LONGTEXT NULL',
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
	goto end_post;
}

$postdata = $req->post();
$postdata['transaction_image'] = '';

$req->upload_folder = 'uploads/airtimebundle';
$uploadedImage = $req->upload_files('transaction_image');
if (!empty($uploadedImage)) {
	$postdata['transaction_image'] = $uploadedImage;
}
if (!empty($req->upload_errors)) {
	$airtimebundle->errors['transaction_image'] = $req->upload_errors['transaction_image'] ?? 'Transaction image could not be uploaded.';
	set_value('errors', array_merge($airtimebundle->errors, $req->upload_errors));
	message_fail($airtimebundle->errors['transaction_image']);
	goto end_post;
}
if (empty($postdata['transaction_image'])) {
	$airtimebundle->errors['transaction_image'] = 'Transaction image is required.';
	set_value('errors', $airtimebundle->errors);
	message_fail($airtimebundle->errors['transaction_image']);
	goto end_post;
}



// Get denominations from the correct field based on category

$category = $postdata['category'] ?? '';

$currencyDetails = '';



if (in_array($category, ['MASHUP', 'MTN-DATA', 'AT-DATA', 'TELECEL-DATA'])) {

    $currencyDetails = $postdata['denos_mashup'] ?? '[]';

} elseif (in_array($category, ['5959', 'MTN UP2U', 'ISHARE', 'JUST4U'])) {

    $currencyDetails = $postdata['denos_special'] ?? '[]';

} else {

    // For STAFF BUNDLE or other categories

    $currencyDetails = $postdata['denos'] ?? '[]';

}



$currencyArray = json_decode($currencyDetails, true);



// Map amount fields to standard amount field BEFORE validation

$transactionType = $postdata['transaction_type'] ?? '';

$category = $postdata['category'] ?? '';



$categoryUpper = strtoupper(trim($category));


// For Airtime: use amount_paid (actual cash received from customer)

if ($transactionType === 'Airtime') {

	$rawLoadAmount = $postdata['amount'] ?? null;

	$rawLoadAmountStr = is_string($rawLoadAmount) ? trim($rawLoadAmount) : $rawLoadAmount;

	$loadAmount = ($rawLoadAmount === null || $rawLoadAmountStr === '') ? 0 : (float)$rawLoadAmountStr;

	$paidAmount = (float)($postdata['amount_paid'] ?? 0);



	// For Airtime EVD, Load/Amount field is hidden in UI; use Amount Paid as the load value if load is missing.

	if ($categoryUpper === 'EVD' && $loadAmount <= 0 && $paidAmount > 0) {

		$loadAmount = $paidAmount;

	}



	$rawAmountToSend = $postdata['amount_to_send'] ?? null;

	$rawAmountToSendStr = is_string($rawAmountToSend) ? trim($rawAmountToSend) : $rawAmountToSend;

	if ($rawAmountToSend === null || $rawAmountToSendStr === '' || (float)$rawAmountToSendStr <= 0) {

		$postdata['amount_to_send'] = $loadAmount;

	} else {

		$postdata['amount_to_send'] = $rawAmountToSend;

	}

	$postdata['amount_paid'] = $paidAmount;

	$postdata['amount'] = $loadAmount;

} else {

    // For Bundle: use the amount_paid field based on category

    if (in_array($category, ['MASHUP', 'MTN-DATA', 'AT-DATA', 'TELECEL-DATA'])) {

        $postdata['amount_to_send'] = $postdata['mashup_amount'] ?? 0;

        $postdata['amount_paid'] = $postdata['mashup_paid'] ?? 0;

        $postdata['amount'] = $postdata['mashup_amount'] ?? 0;

    } elseif (in_array($category, ['5959', 'MTN UP2U', 'ISHARE', 'JUST4U'])) {

        $postdata['amount_to_send'] = $postdata['special_amount_load'] ?? 0;

        $postdata['amount_paid'] = $postdata['special_amount_paid'] ?? 0;

        $postdata['amount'] = $postdata['special_amount_load'] ?? 0;

    } else {

        // For STAFF BUNDLE or other categories

        $postdata['amount_paid'] = $postdata['amount'] ?? 0;

        $postdata['amount_to_send'] = $postdata['amount_to_send'] ?? ($postdata['amount'] ?? 0);

        $postdata['amount'] = $postdata['amount_paid'] ?? 0;

    }

}



// Normalize numeric fields (avoid inserting empty strings into DECIMAL columns)

$numericFields = ['amount', 'amount_paid', 'amount_to_send', 'commission', 'balance_after'];

foreach($numericFields as $field){

	if(!array_key_exists($field, $postdata)){

		continue;

	}

	$value = $postdata[$field];

	if($value === '' || $value === null){

		$postdata[$field] = 0;

		continue;

	}

	if(is_string($value)){

		$value = str_replace([',',' '], '', $value);

	}

	$postdata[$field] = (float)$value;

}

$isJust4uCategory = ($categoryUpper === 'JUST4U');
$isAirtimeEvdCategory = ($transactionType === 'Airtime' && $categoryUpper === 'EVD');
$just4uFixedCharge = 0.50;
if ($isJust4uCategory) {
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
	$evdLoadAmount = (float)($postdata['amount'] ?? ($postdata['amount_paid'] ?? 0));
	$postdata['amount_to_send'] = number_format($evdLoadAmount, 2, '.', '');
	$postdata['amount'] = number_format($evdLoadAmount, 2, '.', '');
	$postdata['commission'] = '0.00';
}

$mashupCategories = ['MASHUP', 'MTN-DATA', 'AT-DATA', 'TELECEL-DATA'];
$specialCategories = ['5959', 'MTN UP2U', 'ISHARE', 'JUST4U'];
$isAirtimeChangeEligible = ($transactionType === 'Airtime' && in_array($categoryUpper, ['AIRTIME', 'CREDIT TRANSFER', 'EVD'], true));
$isBundleChangeEligible = ($transactionType !== 'Airtime' && (in_array($category, $mashupCategories, true) || in_array($category, $specialCategories, true)));
$isChangeEligible = ($isAirtimeChangeEligible || $isBundleChangeEligible);
$paidFieldKey = 'amount_paid';
if (in_array($category, $mashupCategories, true)) {
	$paidFieldKey = 'mashup_paid';
} elseif (in_array($category, $specialCategories, true)) {
	$paidFieldKey = 'special_amount_paid';
}

$payableAmount = 0.0;
if ($transactionType === 'Airtime') {
	$payableAmount = (float)($postdata['amount'] ?? 0);
} elseif (in_array($category, $mashupCategories, true)) {
	$payableAmount = (float)($postdata['mashup_amount'] ?? ($postdata['amount'] ?? 0));
} elseif (in_array($category, $specialCategories, true)) {
	$payableAmount = (float)($postdata['special_amount_load'] ?? ($postdata['amount'] ?? 0));
} else {
	$payableAmount = (float)($postdata['amount'] ?? 0);
}
$payableAmount = (float)number_format($payableAmount, 2, '.', '');

$cashReceivedAmount = (float)($postdata['cash_received_amount'] ?? ($postdata['amount_paid'] ?? 0));
$cashReceivedAmount = (float)number_format($cashReceivedAmount, 2, '.', '');
$changeRequired = (float)($postdata['change_required'] ?? 0);
$changeRequired = (float)number_format($changeRequired, 2, '.', '');
$changeDenominations = json_decode($postdata['change_denominations'] ?? '[]', true);
if (!is_array($changeDenominations)) {
	$changeDenominations = [];
}
$changeDenominations = array_values(array_filter(array_map(function($row){
	$denomination = (float)($row['denomination'] ?? 0);
	$count = (int)($row['count'] ?? 0);
	$volume = (float)($row['volume'] ?? ($denomination * $count));
	if ($denomination <= 0 || $count <= 0 || $volume <= 0) {
		return null;
	}
	$row['currency'] = $row['currency'] ?? 'GHS';
	$row['denomination'] = $denomination;
	$row['count'] = $count;
	$row['volume'] = (float)number_format($volume, 2, '.', '');
	return $row;
}, $changeDenominations)));

$changeGivenTotal = 0.0;
foreach ($changeDenominations as $changeRow) {
	$changeGivenTotal += (float)($changeRow['volume'] ?? 0);
}
$changeGivenTotal = (float)number_format($changeGivenTotal, 2, '.', '');



// Verify CSRF token

$csrf = csrf_verify($postdata);



if ($csrf && $airtimebundle->validate_insert($postdata)) {

    if (user_can('add_momoremit')) {

        $postdata['date_created'] = date("Y-m-d H:i:s");



        // Session info for user

        $session = get_session();

        $user_id = $session->id ?? $row->id;

        $username = $session->first_name . ' ' . $session->last_name;



        $postdata['created_by'] = $user_id;

        $postdata['updated_by'] = $user_id;

        $postdata['disabled'] = 0;

        $postdata['deleted'] = 0;

        

        // Capture transaction status (default to 'Draft' if not provided)

        $trans_status = $postdata['transaction_status'] ?? 'Draft';

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
			goto end_post;
		}

		if ($isChangeEligible) {
			if ($cashReceivedAmount + 0.01 < $payableAmount) {
				$airtimebundle->errors[$paidFieldKey] = 'Amount paid cannot be less than the payable amount (' . number_format($payableAmount, 2) . ').';
				set_value('errors', $airtimebundle->errors);
				message_fail($airtimebundle->errors[$paidFieldKey]);
				goto end_post;
			}

			$computedChangeRequired = ($cashReceivedAmount > $payableAmount)
				? (float)number_format($cashReceivedAmount - $payableAmount, 2, '.', '')
				: 0.0;

			if ($computedChangeRequired > 0.01) {
				if ($changeRequired <= 0 || abs($changeRequired - $computedChangeRequired) > 0.01) {
					$airtimebundle->errors['change_required'] = 'Change required does not match the excess cash received.';
					set_value('errors', $airtimebundle->errors);
					message_fail($airtimebundle->errors['change_required']);
					goto end_post;
				}

				if (empty($changeDenominations)) {
					$airtimebundle->errors['change_denominations'] = 'Select the denominations to give out as change.';
					set_value('errors', $airtimebundle->errors);
					message_fail($airtimebundle->errors['change_denominations']);
					goto end_post;
				}

				if (abs($changeGivenTotal - $computedChangeRequired) > 0.01) {
					$airtimebundle->errors['change_denominations'] = 'Selected change denominations must total GHS ' . number_format($computedChangeRequired, 2) . '.';
					set_value('errors', $airtimebundle->errors);
					message_fail($airtimebundle->errors['change_denominations']);
					goto end_post;
				}
			} else {
				$changeRequired = 0.0;
				$changeDenominations = [];
				$changeGivenTotal = 0.0;
				$postdata['change_required'] = '0.00';
				$postdata['change_denominations'] = '[]';
			}

			$netTransactionAmount = (float)number_format($cashReceivedAmount - $changeGivenTotal, 2, '.', '');
			if ($payableAmount > 0 && abs($netTransactionAmount - $payableAmount) > 0.01) {
				$airtimebundle->errors['change_denominations'] = 'Net cash after change must match the payable amount.';
				set_value('errors', $airtimebundle->errors);
				message_fail($airtimebundle->errors['change_denominations']);
				goto end_post;
			}

			$postdata['cash_received_amount'] = number_format($cashReceivedAmount, 2, '.', '');
			$postdata['amount_paid'] = number_format($cashReceivedAmount, 2, '.', '');
			$postdata['amount'] = number_format($netTransactionAmount, 2, '.', '');
			if ($changeRequired > 0.01) {
				$postdata['change_required'] = number_format($changeRequired, 2, '.', '');
				$postdata['change_denominations'] = json_encode($changeDenominations, JSON_UNESCAPED_SLASHES);
			}
		} else {
			$postdata['amount_paid'] = number_format($cashReceivedAmount, 2, '.', '');
			$postdata['amount'] = number_format($payableAmount, 2, '.', '');
			$postdata['change_required'] = '0.00';
			$postdata['change_denominations'] = '[]';
		}

		// -----------------------------
		// Momoremit-style pre-submit guards
		// -----------------------------
		try {
			// Airtimebundle status options: Draft, Pending, Rejected, Sent
			// Treat 'Sent' as the posted status for enforcing drawer/channel guards.
			$isPostedForGuards = (strtoupper(trim((string)$trans_status)) === 'SENT');
			$network = trim((string)($postdata['network'] ?? ''));
			$txType = trim((string)($postdata['transaction_type'] ?? ''));

			// Require cash denominations when we are posting a transaction with cash received
			// (Airtime/Bundle are cash-in flows; denos represent cash received from customer)
			$amountPaid = (float)($postdata['amount_paid'] ?? 0);
			if ($isPostedForGuards && $amountPaid > 0 && empty($currencyArray)) {
				$airtimebundle->errors['amount_paid'] = 'Please select cash denominations for this transaction.';
				set_value('errors', $airtimebundle->errors);
				message_fail('Please select cash denominations for this transaction.');
				goto end_post;
			}

			// Resolve cashdrawer
			$drawer_id = 0;
			$drawerUserId = $user_id;
			$drawerDbGuard = new \Core\Database;
			$today = date('Y-m-d');
			$drawerRow = $drawerDbGuard->query(
				"SELECT id FROM cashdrawers WHERE user_id = :user_id AND DATE(date_created) = :today ORDER BY id DESC LIMIT 1",
				['user_id' => $user_id, 'today' => $today]
			);
			if (empty($drawerRow)) {
				$drawerRow = $drawerDbGuard->query(
					"SELECT id FROM cashdrawers WHERE user_id = :user_id ORDER BY date_created DESC, id DESC LIMIT 1",
					['user_id' => $user_id]
				);
			}
			if (!empty($drawerRow)) {
				$drawer_id = (int)($drawerRow[0]->id ?? 0);
			}
			if ($drawer_id > 0) {
				try {
					$drawerUserRow = $drawerDbGuard->query("SELECT user_id FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawer_id]);
					if (!empty($drawerUserRow)) {
						$drawerUserId = (int)($drawerUserRow[0]->user_id ?? $user_id);
					}
				} catch (\Throwable $e) {
					$drawerUserId = $user_id;
				}
			}

			// Validate e-cash/bank channel availability + sufficient balance
			// Airtime/Bundle debits e-cash for the load value (amount_to_send).
			$requiredEcash = 0.0;
			if ($requiredEcash <= 0) {
				$requiredEcash = (float)($postdata['amount_to_send'] ?? 0);
				if ($requiredEcash <= 0) {
					$requiredEcash = (float)($postdata['amount'] ?? 0);
				}
				$requiredEcash = (float)number_format($requiredEcash, 2, '.', '');
			}

			$computedBalanceAfter = null;
			if ($isPostedForGuards && $requiredEcash > 0 && $network !== '') {
				if ($drawer_id <= 0) {
					$airtimebundle->errors['network'] = 'Cashdrawer not found for this user, so e-cash balance cannot be validated.';
					set_value('errors', $airtimebundle->errors);
					message_fail($airtimebundle->errors['network']);
					goto end_post;
				}

				$dbBalGuard = new \Core\Database;
				if ($dbBalGuard->table_exists(['drawer_balances'])) {
					$networkUpper = strtoupper($network);
					$catUpperGuard = strtoupper(trim((string)($postdata['category'] ?? '')));
					$txTypeUpperGuard = strtoupper(trim((string)($postdata['transaction_type'] ?? '')));
					$isMtnEvdBundle = ($networkUpper === 'MTN' && in_array($catUpperGuard, ['MASHUP', 'JUST4U', 'MTN-DATA'], true));
					$isEvd = ($catUpperGuard === 'EVD');
					$isTelecelEvdAirtime = ($txTypeUpperGuard === 'AIRTIME' && $networkUpper === 'TELECEL' && $isEvd);
					$networkKey = strtoupper(str_replace(' ', '_', $network));
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
								'ECASH_' . $networkUpper . '_EVD',
								$networkUpper . '_EVD',
								$networkUpper . '-EVD',
								'ECASH_' . $networkUpper . '-EVD',
								'ECASH_' . $networkUpper . '_EVD_OUT',
								'ECASH_' . $networkUpper . '-EVD-OUT',
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

					// Do not create new channels implicitly; require existing channel
					if ($resolvedKey === null) {
						$airtimebundle->errors['network'] = 'E-cash channel not available in cashdrawer for the selected network.';
						set_value('errors', $airtimebundle->errors);
						message_fail($airtimebundle->errors['network']);
						goto end_post;
					}

					$projected = $currentBalance - $requiredEcash;
					if ($projected < -0.00001) {
						$airtimebundle->errors['amount_to_send'] = 'Insufficient e-cash balance in channel ' . $resolvedKey . ' (available ' . number_format($currentBalance, 2) . ', required ' . number_format($requiredEcash, 2) . ')';
						set_value('errors', $airtimebundle->errors);
						message_fail($airtimebundle->errors['amount_to_send']);
						goto end_post;
					}
					$computedBalanceAfter = number_format($projected, 2, '.', '');
				}
			}

			if ($isPostedForGuards && $isChangeEligible && !empty($changeDenominations)) {
				if ($drawer_id <= 0) {
					$airtimebundle->errors['change_denominations'] = 'Cashdrawer not found for this user, so change denominations cannot be validated.';
					set_value('errors', $airtimebundle->errors);
					message_fail($airtimebundle->errors['change_denominations']);
					goto end_post;
				}

				$dbChangeInv = new \Core\Database;
				if (!$dbChangeInv->table_exists(['drawer_denos'])) {
					$airtimebundle->errors['change_denominations'] = 'Cannot validate change because drawer inventory table (drawer_denos) is missing. Please contact admin.';
					set_value('errors', $airtimebundle->errors);
					message_fail($airtimebundle->errors['change_denominations']);
					goto end_post;
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
					goto end_post;
				}
			}
		} catch (\Throwable $e) {
			// If guard fails unexpectedly, block posting when status is SENT.
			if (strtoupper(trim((string)$trans_status)) === 'SENT') {
				$airtimebundle->errors['network'] = 'Unable to validate e-cash channel balance at the moment. Please try again.';
				set_value('errors', $airtimebundle->errors);
				message_fail($airtimebundle->errors['network']);
				goto end_post;
			}
		}

		if ($computedBalanceAfter !== null) {
			$postdata['balance_after'] = $computedBalanceAfter;
		}



		$createdIds = [];

		$master_trans_id = null;

		$didSplit = false;

		if (!$didSplit) {

			$master_trans_id = time();

			$postdata['id'] = $master_trans_id;

			$airtimebundle->insert($postdata);

			if (empty($airtimebundle->has_error) && (int)($airtimebundle->affected_rows ?? 0) > 0) {

				$createdIds[] = $master_trans_id;

			} else {

				$airtimebundle->errors['amount'] = 'Failed to create transaction. ' . ($airtimebundle->error ?? '');

			}

		}

		if (!$didSplit && empty($createdIds)) {
			$baseMsg = 'Transaction failed to save. Please try again.';
			$dbMsg = trim((string)($airtimebundle->error ?? ''));
			if ($dbMsg !== '' && user_can('is_admin')) {
				$baseMsg .= ' (DB: ' . $dbMsg . ')';
			}
			message_fail($baseMsg);
			set_value('errors', $airtimebundle->errors);
			return;
		}



		// Insert denominations ONLY ONCE (cash received), against the master transaction id

		if ($currencyArray) {

			foreach ($currencyArray as $currency) {

				$denosPost = [

					'trans_id' => $master_trans_id,

					'trans_status' => $trans_status,

					'currency' => $currency['currency'],

					'denomination' => $currency['denomination'],

					'count' => (int)$currency['count'],

					'volume' => (float)$currency['volume'],

					'created_by' => $user_id,

					'date_created' => $postdata['date_created'],

					'user_id' => $user_id,

				];

				$deno->insert($denosPost);

			}

		}

		if ($isChangeEligible && !empty($changeDenominations)) {

			foreach ($changeDenominations as $currency) {

				$denosPost = [

					'trans_id' => $master_trans_id,

					'trans_status' => 'Change',

					'currency' => $currency['currency'] ?? 'GHS',

					'denomination' => $currency['denomination'],

					'count' => -1 * abs((int)($currency['count'] ?? 0)),

					'volume' => -1 * abs((float)($currency['volume'] ?? 0)),

					'created_by' => $user_id,

					'date_created' => $postdata['date_created'],

					'user_id' => $user_id,

				];

				$deno->insert($denosPost);

			}

		}



        // Resolve cashdrawer (used by both cash + ecash sync)

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



        $drawerUserId = $user_id;

        if ($drawer_id > 0) {

            try {

                $drawerUserRow = $drawerDb->query("SELECT user_id FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawer_id]);

                if (!empty($drawerUserRow)) {

                    $drawerUserId = (int)($drawerUserRow[0]->user_id ?? $user_id);

                }

            } catch (\Throwable $e) {}

        }



		// Only post to cashdrawer/ecash when transaction is SENT

		$transStatusUpper = strtoupper(trim((string)$trans_status));

		$isSent = ($transStatusUpper === 'SENT');



		// Sync CASH (denominations) to cashdrawer

		if ($drawer_id > 0 && !empty($currencyArray) && $isSent) {

			try {

				$dbDrawerDeno = new \Core\Database;

				if ($dbDrawerDeno->table_exists(['drawer_denos'])) {

					$sign = 1;

					$now = date('Y-m-d H:i:s');

					foreach ($currencyArray as $denoRow) {

						$sqlDeno = "INSERT INTO drawer_denos (drawer_id, user_id, denomination, count, volume, date_created, created_by, trans_id, trans_status) VALUES (:drawer_id, :user_id, :denomination, :count, :volume, :date_created, :created_by, :trans_id, :trans_status)";

						$paramsDeno = [

							'drawer_id' => $drawer_id,

							'user_id' => $drawerUserId,

							'denomination' => $denoRow['denomination'],

							'count' => $sign * (int)($denoRow['count'] ?? 0),

							'volume' => $sign * (float)($denoRow['volume'] ?? 0),

							'date_created' => $now,

							'created_by' => $drawerUserId,

							'trans_id' => $master_trans_id,

							'trans_status' => $trans_status,

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

								'trans_id' => $master_trans_id,

								'trans_status' => 'Change',

							];

							$dbDrawerDeno->query($sqlDeno, $paramsDeno);

						}

					}



					$sumRows = $dbDrawerDeno->query(

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

								'trans_id' => $master_trans_id,

								'trans_status' => $trans_status,

							];

							$dbBal->query($insertSql, $paramsBal);

						} else {

							$rowBal = $rowsBal[0];

							$opening = (float)($rowBal->opening_balance ?? 0);

							$delta = $currentClosing - $opening;

							$credit = $delta > 0 ? $delta : 0;

							$debit = $delta < 0 ? -$delta : 0;

							$updateSql = "UPDATE drawer_balances SET credit = :credit, debit = :debit, closing_balance = :closing WHERE id = :id";

							$paramsBal = [

								'credit' => $credit,

								'debit' => $debit,

'debit' => $debit,

'closing' => $currentClosing,

'id' => $rowBal->id,

];

						}

					}

				}

			} catch (\Throwable $e) {

				error_log('AirtimeBundle cashdrawer cash sync failed: ' . $e->getMessage());

			}

		}

// Sync E-CASH (debit) to cashdrawer (update-only, no inserts)

if ($drawer_id > 0 && !empty($postdata['network']) && $isSent) {

	try {
		$dbBal = new \Core\Database;
		if ($dbBal->table_exists(['drawer_balances'])) {
			$networkUpper = strtoupper(trim((string)($postdata['network'] ?? '')));
			$networkKey = strtoupper(str_replace(' ', '_', (string)($postdata['network'] ?? '')));
			$catUpper = strtoupper(trim((string)($postdata['category'] ?? '')));
			$txTypeUpper = strtoupper(trim((string)($postdata['transaction_type'] ?? '')));
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
						'ECASH_' . $networkUpper . '_EVD',
						$networkUpper . '_EVD',
						$networkUpper . '-EVD',
					]);
				}
				$keysToTry = array_merge($keysToTry, [
					$networkUpper,
					$networkKey,
					'ECASH_' . $networkUpper,
					'ECASH_' . $networkKey,
				]);
				$keysToTry = array_values(array_unique($keysToTry));
			}

			$resolvedKey = null;
			foreach ($keysToTry as $k) {
				$rowsKey = $dbBal->query(
					"SELECT channel_key FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash' AND channel_key = :channel_key ORDER BY id DESC LIMIT 1",
					['drawer_id' => $drawer_id, 'user_id' => $drawerUserId, 'channel_key' => $k]
				);
				if (!empty($rowsKey) && !empty($rowsKey[0]->channel_key)) {
					$resolvedKey = (string)$rowsKey[0]->channel_key;
					break;
				}
			}
			if (!empty($resolvedKey)) {
				$desiredChannelKey = $resolvedKey;
			}

			$debitParts = [];
			$debitAmount = (float)($postdata['amount_to_send'] ?? 0);
			if ($debitAmount <= 0) {
				$debitAmount = (float)($postdata['amount'] ?? 0);
			}
			$debitParts[] = ['id' => $master_trans_id, 'amount' => $debitAmount];

			$totalDebit = 0.0;
			foreach ($debitParts as $p) {
				$totalDebit += (float)($p['amount'] ?? 0);
			}
			$totalDebit = (float)number_format($totalDebit, 2, '.', '');
			if ($totalDebit <= 0) {
				return;
			}

			$rowsEcash = $dbBal->query(
				"SELECT * FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash' AND channel_key = :channel_key LIMIT 1",
				['drawer_id' => $drawer_id, 'user_id' => $drawerUserId, 'channel_key' => $desiredChannelKey]
			);
			if (empty($rowsEcash)) {
				error_log('AirtimeBundle ecash sync skipped (missing channel): drawer_id=' . (int)$drawer_id . ' desired=' . (string)$desiredChannelKey);
				return;
			}
			$rowEcash = $rowsEcash[0];
			$ecashOpening = (float)($rowEcash->opening_balance ?? 0);
			$ecashCredit = (float)($rowEcash->credit ?? 0);
			$ecashDebit = (float)($rowEcash->debit ?? 0) + $totalDebit;
			$ecashClosing = $ecashOpening + $ecashCredit - $ecashDebit;
			$updateEcashSql = "UPDATE drawer_balances SET channel_key = :channel_key, credit = :credit, debit = :debit, closing_balance = :closing, trans_id = :trans_id, trans_status = :trans_status WHERE id = :id";
			$paramsEcash = [
				'channel_key' => $desiredChannelKey,
				'credit' => $ecashCredit,
				'debit' => $ecashDebit,
				'closing' => $ecashClosing,
				'trans_id' => (int)($master_trans_id ?? 0),
				'trans_status' => $trans_status,
				'id' => $rowEcash->id,
			];
			$dbBal->query($updateEcashSql, $paramsEcash);
		}
	} catch (\Throwable $e) {
		error_log('AirtimeBundle ecash sync failed: ' . $e->getMessage());
	}

}

$instantCommissionParts = [];

$txTypeForCommission = strtolower(trim((string)($postdata['transaction_type'] ?? '')));

$categoryForCommission = strtoupper(trim((string)($postdata['category'] ?? '')));

$networkForCommission = strtoupper(trim((string)($postdata['network'] ?? '')));

$categoryForCommission = strtoupper(trim((string)($postdata['category'] ?? '')));

$networkForCommission = strtoupper(trim((string)($postdata['network'] ?? '')));


if ($categoryForCommission === 'JUST4U') {

$commissionAmount = 0.50;

if ($commissionAmount > 0) {

foreach (($createdIds ?? []) as $id) {

$instantCommissionParts[] = ['id' => $id, 'amount' => $commissionAmount];

}
}

}


if (!empty($instantCommissionParts)) {

try {

$commissionDb = new \Core\Database;

foreach ($instantCommissionParts as $part) {

$partId = (int)($part['id'] ?? 0);

$partAmount = (float)($part['amount'] ?? 0);

if ($partId <= 0 || $partAmount <= 0) {

continue;

}

$commissionDescription = 'AIRTIMEBUNDLE INSTANT | ' . strtoupper((string)($postdata['transaction_type'] ?? '')) . ' | ' . strtoupper((string)($postdata['category'] ?? '')) . ' | ' . strtoupper((string)($postdata['network'] ?? '')) . ' | ID ' . $partId;

$commissionInsert = [

'date_received' => $postdata['date_created'] ?? date('Y-m-d H:i:s'),

'colletion_period' => 'INSTANT',

'network' => $postdata['network'] ?? null,

'commission_type' => 'AIRTIMEBUNDLE INSTANT',

'description' => $commissionDescription,

'pay_to' => $user_id,

'receiving_wallet' => null,

'amount' => $partAmount,

'transaction_status' => 'Approved',

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

}

} catch (\Throwable $e) {

error_log('AIRTIMEBUNDLE INSTANT COMMISSION INSERT FAILED: ' . $e->getMessage());

}

}

message_success("Transaction added successfully!");

// Log activity

$action = 'added';

$transaction = $postdata['network'] . ' ' .  $postdata['transaction_type'];

$transaction_id = $master_trans_id;

$table = 'airtimebundles';

$description = "$username $action a new $transaction with id $transaction_id to $table";

$log->insert([

'table_name' => $table,

'action' => $action,

'user_id' => $user_id,

'user_name' => $username,

'description' => $description,

'date_created' => date("Y-m-d H:i:s")

]);

redirect($admin_route . '/' . $plugin_route);

} else {

$airtimebundle->errors['permission'] = "You do not have permission to add airtime bundles.";

set_value('errors', $airtimebundle->errors);

goto end_post;

}

} else {

if (!$csrf) {

$airtimebundle->errors['network'] = "Form expired!";

} else {

// Log validation errors for debugging

error_log("Validation errors: " . json_encode($airtimebundle->errors));

error_log("Postdata: " . json_encode($postdata));

}

set_value('errors', $airtimebundle->errors);

}

end_post:

?>

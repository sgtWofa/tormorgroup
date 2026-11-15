<?php

// Set timezone to Accra (Africa/Accra)
date_default_timezone_set('Africa/Accra');

$log = new \Momoremits\Log;
$deno = new \Momoremits\Deno;
$sending = new \Momoremits\Sending;
$withdrawal = new \Momoremits\Withdrawal;
$deposit = new \Momoremits\Deposit;
$cit = new \Momoremits\Cit;

$postdata = $req->post();
$postdata['transaction_image'] = $postdata['transaction_image'] ?? '';
$postdata['sim_type'] = $postdata['sim_type'] ?? '';
$postdata['charging_type'] = $postdata['charging_type'] ?? '';
$postdata['amount_load'] = $postdata['amount_load'] ?? '0.00';
$postdata['amount_sendable'] = $postdata['amount_sendable'] ?? '0.00';
$postdata['balance_after_display'] = $postdata['balance_after_display'] ?? '';

$prepareStoredUploadPath = function(string $path): string {
	$path = trim(str_replace('\\', '/', $path));
	if ($path === '') {
		return '';
	}

	$dir = str_replace('\\', '/', dirname($path));
	if ($dir === '.' || $dir === '') {
		$dir = 'uploads/momoremit';
	}

	$originalBase = basename($path);
	$cleanBase = preg_replace('/[^A-Za-z0-9._-]+/', '_', $originalBase);
	$cleanBase = trim((string)$cleanBase, '._-');
	$ext = strtolower((string)pathinfo($cleanBase !== '' ? $cleanBase : $originalBase, PATHINFO_EXTENSION));
	$ext = preg_replace('/[^a-z0-9]+/', '', $ext);
	if (strlen((string)$ext) > 8) {
		$ext = '';
	}

	$needsRename = $cleanBase === '' || $cleanBase !== $originalBase || strlen($path) > 240 || strlen($cleanBase) > 120;
	if (!$needsRename) {
		return $path;
	}

	try {
		$suffix = (string)random_int(1000, 9999);
	} catch (\Throwable $e) {
		$suffix = (string)mt_rand(1000, 9999);
	}
	$newBase = 'momoremit_' . date('Ymd_His') . '_' . $suffix . ($ext !== '' ? '.' . $ext : '');
	$newPath = rtrim($dir, '/') . '/' . $newBase;

	if (is_file($path) && @rename($path, $newPath)) {
		return $newPath;
	}

	return strlen($path) <= 240 ? $path : '';
};

// Upload transaction message picture for withdrawals
if (in_array(($postdata['transaction_type'] ?? ''), ['Withdrawal', 'Cash in', 'CIT'], true)) {
	$req->upload_folder = 'uploads/momoremit';
	$uploadedImage = $req->upload_files('transaction_image');
	if (!empty($uploadedImage)) {
		$postdata['transaction_image'] = $prepareStoredUploadPath((string)$uploadedImage);
	}
}

$currencyDetails = $postdata['denos'] ?? '[]';
// Decode the JSON currency details
$currencyArray = json_decode($currencyDetails, true);
if(!is_array($currencyArray)){
	$currencyArray = [];
}

$cashdrawerClosingBalance = null;
$ecashClosingBalance = null;

// Normalize free-form money inputs before validation and insert.
$normalizeMoneyField = function($value): string {
	$raw = trim((string)$value);
	if($raw === ''){
		return '';
	}
	$raw = str_replace(['GHS', 'GH₵', 'GH¢', '₵', '¢', ','], '', $raw);
	if(preg_match('/-?\d+(?:\.\d+)?/', $raw, $matches)){
		return number_format((float)$matches[0], 2, '.', '');
	}
	return $raw;
};

$normalizePhoneNumberField = function($value): string {
	$raw = trim((string)$value);
	if($raw === ''){
		return '';
	}
	return preg_replace('/\s+/', '', $raw);
};

$postdata['phone'] = $normalizePhoneNumberField($postdata['phone'] ?? '');
$postdata['sender'] = $normalizePhoneNumberField($postdata['sender'] ?? '');
$postdata['bank_name'] = trim((string)($postdata['bank_name'] ?? ''));

// Withdrawal forms can rely on the denomination modal to populate the paid amount.
// Normalize it here so a missed client-side sync does not block the save.
if (strcasecmp(trim((string)($postdata['transaction_type'] ?? '')), 'Withdrawal') === 0) {
	$withdrawalTargetAmount = (float)($postdata['amount_to_withdraw'] ?? $postdata['amount'] ?? 0);
	$withdrawalDenosTotal = 0.0;
	foreach ($currencyArray as $denoRow) {
		$withdrawalDenosTotal += (float)($denoRow['volume'] ?? ((float)($denoRow['denomination'] ?? 0) * (float)($denoRow['count'] ?? 0)));
	}

	if ($withdrawalTargetAmount <= 0 && $withdrawalDenosTotal > 0) {
		$withdrawalTargetAmount = $withdrawalDenosTotal;
	}

	if ($withdrawalTargetAmount > 0) {
		$formattedWithdrawalAmount = number_format($withdrawalTargetAmount, 2, '.', '');
		$postdata['amount'] = $formattedWithdrawalAmount;
		$postdata['amount_to_withdraw'] = $formattedWithdrawalAmount;
		$postdata['amount_payable'] = $formattedWithdrawalAmount;
	}

	$postdata['balance_after'] = $normalizeMoneyField($postdata['balance_after'] ?? '');
	$postdata['charge'] = $normalizeMoneyField($postdata['charge'] ?? '');
	$postdata['amount'] = $normalizeMoneyField($postdata['amount'] ?? $postdata['amount_to_withdraw'] ?? '');
	$postdata['amount_to_withdraw'] = $normalizeMoneyField($postdata['amount_to_withdraw'] ?? $postdata['amount'] ?? '');
	$postdata['amount_payable'] = $normalizeMoneyField($postdata['amount_payable'] ?? $postdata['amount_to_withdraw'] ?? '');

	if ($withdrawalTargetAmount > 0 && $withdrawalDenosTotal > 0 && abs($withdrawalDenosTotal - $withdrawalTargetAmount) > 0.01) {
		$momoremit->errors['denos'] = 'Withdrawal denominations must total GHS ' . number_format($withdrawalTargetAmount, 2) . '.';
		set_value('errors', array_merge($momoremit->errors, $req->upload_errors));
		return;
	}
}

if (strcasecmp(trim((string)($postdata['transaction_type'] ?? '')), 'CIT') === 0) {
	$citAmount = (float)($postdata['amount_to_withdraw'] ?? $postdata['amount'] ?? 0);
	if ($citAmount > 0) {
		$formattedCitAmount = number_format($citAmount, 2, '.', '');
        $postdata['amount'] = $formattedCitAmount;
		$postdata['amount_to_withdraw'] = $formattedCitAmount;
		$postdata['amount_to_receive'] = $formattedCitAmount;
		$postdata['amount_payable'] = $formattedCitAmount;
	}
	$postdata['charge'] = '0.00';
	$postdata['amount_sendable'] = '0.00';
}

$csrf = csrf_verify($postdata);

if ($csrf && $momoremit->validate_insert($postdata)) {
    if (user_can('add_momoremit')) {
		unset($postdata['balance_after_display']);
		$generateTransactionId = function() use ($momoremit): int {
			$candidate = (int)time();
			for ($attempts = 0; $attempts < 1000; $attempts++) {
				try {
					$exists = $momoremit->query("SELECT id FROM momoremits WHERE id = :id LIMIT 1", ['id' => $candidate]);
					if (empty($exists)) {
						return $candidate;
					}
				} catch (\Throwable $e) {
					break;
				}
				$candidate++;
			}
			return (int)time();
		};
		$txTypeGate = trim((string)($postdata['transaction_type'] ?? ''));
		$networkGate = strtoupper(trim((string)($postdata['network'] ?? '')));
		if (strcasecmp($txTypeGate, 'Cash in') === 0) {
			$allowedNetworks = ['MTN', 'TELECEL', 'AT', 'ZEEPAY', 'G-MONEY', 'GHANAPAY'];
			if ($networkGate === '' || !in_array($networkGate, $allowedNetworks, true)) {
				$momoremit->errors['network'] = 'Invalid network for MoMoRemit Cash in.';
				set_value('errors', array_merge($momoremit->errors, $req->upload_errors));
				return;
			}
		}
        $postdata['date_created'] = date("Y-m-d H:i:s");
        $postdata['id'] = $generateTransactionId();

        // Session info for user
        $session = get_session();
        if (!empty($session->id)) {
            $username = $session->first_name . ' ' . $session->last_name;
            $user_id = $session->id;
        } else {
            $username = $row->first_name . ' ' . $row->last_name;
            $user_id = $row->id;
        }

        $postdata['created_by'] = $user_id;

        // Generate slug for momoremit entry
        if (empty($postdata['slug'])) {
            $postdata['slug'] = $momoremit->str_to_url($postdata['network'] . '_' . $postdata['transaction_type']);
        }

        //dd($postdata);die;
        // Handle slug uniqueness
        $num = 0;
        while ($num < 10 && $momoremit->first(['slug' => $postdata['slug']])) {
            $postdata['slug'] .= rand(0, 99);
            $num++;
        }

        // Capture transaction status (default to 'Approved' if not provided)
        $trans_status = momoremits_normalize_status($postdata['transaction_status'] ?? null, 'Approved');
        $postdata['transaction_status'] = $trans_status;
        $trans_id = $postdata['id'];

        // Enforce that cash-out transactions cannot request more notes than are available
        // in the current drawer inventory.
        $drawer_id = 0;
        try{
            $drawerDb = new \Core\Database;
            $today = date('Y-m-d');
            $drawerRow = $drawerDb->query(
                "SELECT id FROM cashdrawers WHERE user_id = :user_id AND DATE(date_created) = :today ORDER BY id DESC LIMIT 1",
                ['user_id' => $user_id, 'today' => $today]
            );
            if(empty($drawerRow)){
                $drawerRow = $drawerDb->query(
                    "SELECT id FROM cashdrawers WHERE user_id = :user_id ORDER BY date_created DESC, id DESC LIMIT 1",
                    ['user_id' => $user_id]
                );
            }
            if(!empty($drawerRow)){
                $drawer_id = (int)($drawerRow[0]->id ?? 0);
            }
        }catch(\Throwable $e){
            $drawer_id = 0;
        }

        $normalizeDenominationKey = function($value): string {
            $raw = trim((string)$value);
            if($raw === ''){
                return '0';
            }
            if(is_numeric($raw)){
                $raw = rtrim(rtrim(sprintf('%.2F', (float)$raw), '0'), '.');
                if($raw === '-0'){
                    $raw = '0';
                }
            }
            return $raw;
        };

        // Enforce that e-cash / bank channels cannot go negative
        // (similar to denomination availability validation for cash).
        $txType = (string)($postdata['transaction_type'] ?? '');
        $network = trim((string)($postdata['network'] ?? ''));
        if (strcasecmp($txType, 'Cash in') === 0) {
            foreach (['amount_load','amount_sendable','amount_payable','cash_received_amount','change_required','amount','balance_after','charge'] as $moneyField) {
                $postdata[$moneyField] = $normalizeMoneyField($postdata[$moneyField] ?? '');
            }
            if (function_exists('momoremits_apply_merchant_cashin_sending_amounts')) {
                $postdata = momoremits_apply_merchant_cashin_sending_amounts($postdata);
            }
        }
        $cashReceivedAmount = (float)($postdata['cash_received_amount'] ?? ($postdata['amount'] ?? 0));
        $transactionAmount = (float)($postdata['amount'] ?? 0);
        $amountPayable = (float)($postdata['amount_payable'] ?? 0);
        $amountSendable = (float)($postdata['amount_sendable'] ?? 0);
        $changeRequired = (float)($postdata['change_required'] ?? 0);
        $changeDenominations = json_decode($postdata['change_denominations'] ?? '[]', true);
        if(!is_array($changeDenominations)){
            $changeDenominations = [];
        }

        $changeDenominations = array_values(array_filter(array_map(function($row){
            $denomination = (float)($row['denomination'] ?? 0);
            $count = (int)($row['count'] ?? 0);
            if($denomination <= 0 || $count <= 0){
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
        foreach($changeDenominations as $changeRow){
            $changeGivenTotal += (float)($changeRow['volume'] ?? 0);
        }
        $changeGivenTotal = round($changeGivenTotal, 2);

        if (strcasecmp($txType, 'Cash in') === 0) {
            if ($cashReceivedAmount <= 0 && $transactionAmount > 0) {
                $cashReceivedAmount = $transactionAmount;
            }

            if ($amountSendable <= 0) {
                $momoremit->errors['amount_sendable'] = 'Amount to send is required for Cash in.';
                set_value('errors', $momoremit->errors);
                return;
            }

            $computedChangeRequired = ($cashReceivedAmount > $amountPayable)
                ? round($cashReceivedAmount - $amountPayable, 2)
                : 0.0;

            if ($cashReceivedAmount > 0 && $amountPayable > 0 && $cashReceivedAmount + 0.00001 < $amountPayable) {
                $momoremit->errors['amount'] = 'Amount received cannot be less than the amount payable.';
                set_value('errors', $momoremit->errors);
                return;
            }

            if ($computedChangeRequired > 0.01) {
                if ($changeRequired <= 0 || abs($changeRequired - $computedChangeRequired) > 0.01) {
                    $momoremit->errors['change_required'] = 'Change required does not match the excess cash received.';
                    set_value('errors', $momoremit->errors);
                    return;
                }

                if (empty($changeDenominations)) {
                    $momoremit->errors['change_denominations'] = 'Select the denominations to give out as change.';
                    set_value('errors', $momoremit->errors);
                    return;
                }

                if (abs($changeGivenTotal - $computedChangeRequired) > 0.01) {
                    $momoremit->errors['change_denominations'] = 'Selected change denominations must total GHS ' . number_format($computedChangeRequired, 2) . '.';
                    set_value('errors', $momoremit->errors);
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
                $momoremit->errors['change_denominations'] = 'Net cash after change must match the amount payable.';
                set_value('errors', $momoremit->errors);
                return;
            }

            $postdata['cash_received_amount'] = number_format($cashReceivedAmount, 2, '.', '');
            $postdata['amount'] = number_format($transactionAmount, 2, '.', '');
            $postdata['amount_payable'] = number_format($amountPayable, 2, '.', '');
            $postdata['amount_sendable'] = number_format($amountSendable, 2, '.', '');
            $postdata['amount_load'] = number_format((float)($postdata['amount_load'] ?? 0), 2, '.', '');
            $postdata['charge'] = number_format((float)($postdata['charge'] ?? 0), 2, '.', '');
            if ($changeRequired > 0.01) {
                $postdata['change_required'] = number_format($changeRequired, 2, '.', '');
                $postdata['change_denominations'] = json_encode($changeDenominations, JSON_UNESCAPED_SLASHES);
            }
        }

        if (strcasecmp($txType, 'Cash in') === 0) {
            $simTypeNorm = strtolower(trim((string)($postdata['sim_type'] ?? '')));
            $categoryNorm = strtolower(trim((string)($postdata['category'] ?? '')));
            $categoryNorm = str_replace([' ', '/', '-', '_'], '', $categoryNorm);
            if (in_array($simTypeNorm, ['subscriber', 'agent'], true) && $categoryNorm === 'inout') {
                $savedCharge = round(max(0, $amountPayable - $amountSendable), 2);
                if ($savedCharge > 0) {
                    $postdata['charge'] = number_format($savedCharge, 2, '.', '');
                }
            }
        }

        $cashinEcashChannelKey = null;
        if ($trans_status === 'Approved' && $txType === 'Cash in' && $transactionAmount > 0 && $network !== '') {
            if ($drawer_id <= 0) {
                $momoremit->errors['network'] = 'Cashdrawer not found for this user, so e-cash balance cannot be validated.';
                set_value('errors', $momoremit->errors);
                return;
            }

            $drawerUserId = $user_id;
            try {
                $drawerDbGuard = new \Core\Database;
                $drawerUserRow = $drawerDbGuard->query("SELECT user_id FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawer_id]);
                if (!empty($drawerUserRow)) {
                    $drawerUserId = (int)($drawerUserRow[0]->user_id ?? $user_id);
                }
            } catch (\Throwable $e) {
                $drawerUserId = $user_id;
            }

            try {
                $dbBalGuard = new \Core\Database;
                if ($dbBalGuard->table_exists(['drawer_balances'])) {
                    $keysToTry = function_exists('momoremits_ecash_channel_candidates')
                        ? momoremits_ecash_channel_candidates(
                            $network,
                            (string)($postdata['bank_name'] ?? ''),
                            (string)($postdata['sim_type'] ?? '')
                        )
                        : [];

                    $ecashTargetDrawerId = $drawer_id;
                    $ecashTargetUserId = $drawerUserId;
                    if (!empty($keysToTry) && function_exists('momoremits_is_momopay_channel_key') && momoremits_is_momopay_channel_key((string)$keysToTry[0])) {
                        $sharedContext = momoremits_find_shared_momopay_context($drawer_id, $drawerUserId);
                        if (!empty($sharedContext)) {
                            $ecashTargetDrawerId = (int)($sharedContext['drawer_id'] ?? $drawer_id);
                            $ecashTargetUserId = (int)($sharedContext['drawer_user_id'] ?? $drawerUserId);
                        }
                    }

                    $resolvedKey = null;
                    $currentBalance = 0.0;
                    $foundChannelRow = false;
                    foreach ($keysToTry as $k) {
                        $rows = $dbBalGuard->query(
                            "SELECT channel_key, closing_balance FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash' AND channel_key = :channel_key ORDER BY id DESC LIMIT 1",
                            ['drawer_id' => $ecashTargetDrawerId, 'user_id' => $ecashTargetUserId, 'channel_key' => $k]
                        );
                        if (!empty($rows)) {
                            $resolvedKey = (string)($rows[0]->channel_key ?? $k);
                            $currentBalance = (float)($rows[0]->closing_balance ?? 0);
                            $foundChannelRow = true;
                            break;
                        }
                    }

                    if ($resolvedKey === null) {
                        $resolvedKey = $keysToTry[0] ?? strtoupper($network);
                        $currentBalance = 0.0;
                    }

                    if (!$foundChannelRow) {
                        $momoremit->errors['balance_after_display'] = 'Current balance for channel ' . $resolvedKey . ' could not be found.';
                        set_value('errors', $momoremit->errors);
                        return;
                    }
                    $cashinEcashChannelKey = $resolvedKey;

                    $ecashDebitRequired = function_exists('momoremits_cashin_ecash_debit_amount')
                        ? momoremits_cashin_ecash_debit_amount($postdata)
                        : $amountSendable;
                    $projected = $currentBalance - $ecashDebitRequired;

                    $postdata['balance_after'] = number_format($projected, 2, '.', '');
                    if ($projected < -0.00001) {
                        $momoremit->errors['amount'] = 'Insufficient e-cash/bank balance in channel ' . $resolvedKey . ' (available ' . number_format($currentBalance, 2) . ', required ' . number_format($ecashDebitRequired, 2) . ')';
                        set_value('errors', $momoremit->errors);
                        return;
                    }
                }
            } catch (\Throwable $e) {
                error_log('MOMOREMIT ECASH BALANCE LOOKUP FAILED: ' . $e->getMessage());
                $momoremit->errors['balance_after_display'] = 'Unable to verify the current e-cash/bank balance. Please try again or contact admin.';
                set_value('errors', $momoremit->errors);
                return;
            }
        }

        $txType = (string)($postdata['transaction_type'] ?? '');
        $isCashOut = in_array($txType, ['Withdrawal','CIT'], true);
        if($isCashOut && ($trans_status === 'Approved') && empty($currencyArray)){
            $momoremit->errors['denos'] = "Please select cash denominations for this payout.";
            set_value('errors', $momoremit->errors);
            return;
        }
        if($drawer_id <= 0 && $isCashOut && ($trans_status === 'Approved') && !empty($currencyArray)){
            $momoremit->errors['denos'] = "Cashdrawer not found for this user, so denominations cannot be validated. Please open/select a cashdrawer and try again.";
            set_value('errors', $momoremit->errors);
            return;
        }
        if($drawer_id > 0 && $isCashOut && ($trans_status === 'Approved') && !empty($currencyArray)){
            try{
                $dbInv = new \Core\Database;
                if(!$dbInv->table_exists(['drawer_denos'])){
                    $momoremit->errors['denos'] = "Cannot validate denominations because drawer inventory table (drawer_denos) is missing. Please contact admin.";
                    set_value('errors', $momoremit->errors);
                    return;
                }
                if($dbInv->table_exists(['drawer_denos'])){
                    $drawerUserId = $user_id;
                    try{
                        $userRow = $dbInv->query("SELECT user_id FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawer_id]);
                        if(!empty($userRow)){
                            $drawerUserId = (int)($userRow[0]->user_id ?? $user_id);
                        }
                    }catch(\Throwable $e){}

                    $invRows = $dbInv->query(
                        "SELECT denomination, SUM(count) AS count FROM drawer_denos WHERE drawer_id = :drawer_id AND user_id = :user_id GROUP BY denomination",
                        ['drawer_id' => $drawer_id, 'user_id' => $drawerUserId]
                    );
                    $available = [];
                    if(!empty($invRows)){
                        foreach($invRows as $r){
                            $denKey = $normalizeDenominationKey($r->denomination ?? 0);
                            $available[$denKey] = (int)($r->count ?? 0);
                        }
                    }

                    $requested = [];
                    $unsupportedCurrencies = [];
                    foreach($currencyArray as $denoRow){
                        $denCurrency = $denoRow['currency'] ?? 'GHS';
                        $denCurrencyUpper = strtoupper((string)$denCurrency);
                        if($denCurrencyUpper !== 'GHS'){
                            $unsupportedCurrencies[$denCurrencyUpper] = true;
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

                    if(!empty($unsupportedCurrencies)){
                        $momoremit->errors['denos'] = "Unsupported currency in denominations payload: " . implode(', ', array_keys($unsupportedCurrencies)) . ". Only GHS can be validated against the cashdrawer.";
                        set_value('errors', $momoremit->errors);
                        return;
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
                            $parts[] = 'GHS ' . $denKey . ' (need ' . ((int)($info['need'] ?? 0)) . ', available ' . ((int)($info['have'] ?? 0)) . ')';
                        }
                        $detail = !empty($parts) ? (' ' . implode(' | ', $parts)) : '';
                        $momoremit->errors['denos'] = "Insufficient cash denominations in drawer #{$drawer_id}." . $detail;
                        set_value('errors', $momoremit->errors);
                        return;
                    }
                }
            }catch(\Throwable $e){
                // fail silently; do not block if inventory lookup fails
            }
        }

        if (strcasecmp($txType, 'Cash in') === 0 && !empty($changeDenominations)) {
            if ($drawer_id <= 0) {
                $momoremit->errors['change_denominations'] = "Cashdrawer not found for this user, so change denominations cannot be validated.";
                set_value('errors', $momoremit->errors);
                return;
            }

            try {
                $dbChangeInv = new \Core\Database;
                if (!$dbChangeInv->table_exists(['drawer_denos'])) {
                    $momoremit->errors['change_denominations'] = "Cannot validate change because drawer inventory table (drawer_denos) is missing. Please contact admin.";
                    set_value('errors', $momoremit->errors);
                    return;
                }

                $drawerUserId = $user_id;
                try {
                    $userRow = $dbChangeInv->query("SELECT user_id FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawer_id]);
                    if (!empty($userRow)) {
                        $drawerUserId = (int)($userRow[0]->user_id ?? $user_id);
                    }
                } catch (\Throwable $e) {}

                $invRows = $dbChangeInv->query(
                    "SELECT denomination, SUM(count) AS count FROM drawer_denos WHERE drawer_id = :drawer_id AND user_id = :user_id GROUP BY denomination",
                    ['drawer_id' => $drawer_id, 'user_id' => $drawerUserId]
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
                    $momoremit->errors['change_denominations'] = "Insufficient denominations available for change in drawer #{$drawer_id}. " . implode(' | ', $parts);
                    set_value('errors', $momoremit->errors);
                    return;
                }
            } catch (\Throwable $e) {
                // fail silently; do not block if inventory lookup fails
            }
        }

        $dbMutation = new \Core\Database;
        $dbMutation->query('START TRANSACTION');

        if ($currencyArray) {
            foreach ($currencyArray as $currency) {
                $denosPost = [
                   'trans_id' => $trans_id,
                    'trans_status' => $trans_status,
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

        if (strcasecmp($txType, 'Cash in') === 0 && !empty($changeDenominations)) {
            foreach ($changeDenominations as $currency) {
                $denosPost = [
                    'trans_id' => $trans_id,
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

        // Cashdrawer update - Mirror denominations to drawer_denos, then recompute drawer_balances + e-cash channel
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

            $txType = $postdata['transaction_type'] ?? '';
            $network = $postdata['network'] ?? '';
            $transactionAmount = (float)($postdata['amount'] ?? 0);
            $amountSendable = (float)($postdata['amount_sendable'] ?? 0);
            $currencyDetails = $postdata['denos'] ?? '[]';
            $currencyArray = json_decode($currencyDetails, true);

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

                        $ecashDrawerId = $drawer_id;
                        $ecashDrawerUserId = $drawerUserId;
                        $ecashSharedChannelKey = null;
                        if (function_exists('momoremits_is_momopay_channel_key') && momoremits_is_momopay_channel_key((string)$network)) {
                            $sharedContext = momoremits_find_shared_momopay_context($drawer_id, $drawerUserId);
                            if (!empty($sharedContext)) {
                                $ecashDrawerId = (int)($sharedContext['drawer_id'] ?? $drawer_id);
                                $ecashDrawerUserId = (int)($sharedContext['drawer_user_id'] ?? $drawerUserId);
                                $ecashSharedChannelKey = (string)($sharedContext['channel_key'] ?? '');
                            }
                        }

                        // Determine sign for cash: Withdrawal/CIT = cash out (-1), Cash in = cash in (+1)
                        $sign = (in_array($txType, ['Withdrawal', 'CIT'])) ? -1 : 1;

                        foreach ($currencyArray as $denoRow) {
                            $denCurrency = $denoRow['currency'] ?? 'GHS';
                            if (strtoupper($denCurrency) !== 'GHS') {
                                continue;
                            }
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

                        if (strcasecmp($txType, 'Cash in') === 0 && !empty($changeDenominations)) {
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
                            if (strcasecmp($txType, 'Cash in') !== 0) {
                                $cashdrawerClosingBalance = $currentClosing;
                            }

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

                                    $updateSql = "UPDATE drawer_balances SET credit = :credit, debit = :debit, closing_balance = :closing WHERE id = :id";
                                    $paramsBal = [
                                        'credit' => $credit,
                                        'debit' => $debit,
                                        'closing' => $currentClosing,
                                        'id' => $rowBal->id,
                                    ];
                                    $dbBal->query($updateSql, $paramsBal);
                                }

                                // Update e-cash channel: Cash in = e-cash OUT, Withdrawal/CIT = e-cash IN
                                // For MTN, TELECEL, AT networks, consolidate to single channel (no ECASH_ prefix)
                                $ecashMovementAmount = ($txType === 'Cash in')
                                    ? (function_exists('momoremits_cashin_ecash_debit_amount') ? momoremits_cashin_ecash_debit_amount($postdata) : $amountSendable)
                                    : $transactionAmount;
                                if ($ecashMovementAmount > 0 && !empty($network)) {
                                    $networkUpper = strtoupper($network);
                                    $ecashChannelKey = $cashinEcashChannelKey;
                                    $keysToTry = function_exists('momoremits_ecash_channel_candidates')
                                        ? momoremits_ecash_channel_candidates(
                                            $network,
                                            (string)($postdata['bank_name'] ?? ''),
                                            (string)($postdata['sim_type'] ?? '')
                                        )
                                        : [];
                                    if ($ecashChannelKey !== null) {
                                        array_unshift($keysToTry, $ecashChannelKey);
                                        $keysToTry = array_values(array_unique($keysToTry));
                                    }
                                    if ($ecashSharedChannelKey !== null && $ecashSharedChannelKey !== '') {
                                        array_unshift($keysToTry, $ecashSharedChannelKey);
                                        $keysToTry = array_values(array_unique($keysToTry));
                                    }
                                    if ($ecashDrawerId !== $drawer_id || $ecashDrawerUserId !== $drawerUserId) {
                                        $keysToTry = array_values(array_unique($keysToTry));
                                    }

                                    $rowsEcash = [];
                                    $resolvedExistingKey = null;
                                    foreach ($keysToTry as $k) {
                                        $rowsEcash = $dbBal->query(
                                            "SELECT * FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash' AND channel_key = :channel_key LIMIT 1",
                                            ['drawer_id' => $ecashDrawerId, 'user_id' => $ecashDrawerUserId, 'channel_key' => $k]
                                        );
                                        if (!empty($rowsEcash)) {
                                            $resolvedExistingKey = (string)($rowsEcash[0]->channel_key ?? $k);
                                            break;
                                        }
                                    }

                                    if (!empty($resolvedExistingKey)) {
                                        $ecashChannelKey = $resolvedExistingKey;
                                    } elseif ($ecashChannelKey === null) {
                                        $ecashChannelKey = $keysToTry[0] ?? $networkUpper;
                                    }

                                    // Cash in = e-cash debit (out) by the sendable amount, Withdrawal/CIT = e-cash credit (in)
                                    $isEcashOut = ($txType === 'Cash in');
                                    $ecashCredit = $isEcashOut ? 0 : $ecashMovementAmount;
                                    $ecashDebit = $isEcashOut ? $ecashMovementAmount : 0;

                                    if (empty($rowsEcash)) {
                                        $ecashOpening = 0;
                                        $ecashClosing = $ecashOpening + $ecashCredit - $ecashDebit;
                                        $ecashClosingBalance = $ecashClosing;

                                        $insertEcashSql = "INSERT INTO drawer_balances (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status) VALUES (:drawer_id, :user_id, 'ecash', :channel_key, :opening, :credit, :debit, :closing, :date_created, :trans_id, :trans_status)";
                                        $paramsEcash = [
                                            'drawer_id' => $ecashDrawerId,
                                            'user_id' => $ecashDrawerUserId,
                                            'channel_key' => $ecashChannelKey,
                                            'opening' => $ecashOpening,
                                            'credit' => $ecashCredit,
                                            'debit' => $ecashDebit,
                                            'closing' => $ecashClosing,
                                            'date_created' => $now,
                                            'trans_id' => $trans_id,
                                            'trans_status' => $trans_status,
                                        ];
                                        $dbBal->query($insertEcashSql, $paramsEcash);
                                    } else {
                                        $rowEcash = $rowsEcash[0];
                                        $ecashOpening = (float)($rowEcash->opening_balance ?? 0);
                                        $ecashCredit = (float)($rowEcash->credit ?? 0) + ($isEcashOut ? 0 : $ecashMovementAmount);
                                        $ecashDebit = (float)($rowEcash->debit ?? 0) + ($isEcashOut ? $ecashMovementAmount : 0);
                                        $ecashClosing = $ecashOpening + $ecashCredit - $ecashDebit;
                                        $ecashClosingBalance = $ecashClosing;

                                        // If the old record had ECASH_ prefix, update it to use the new consolidated key
                                        $updateEcashSql = "UPDATE drawer_balances SET channel_key = :channel_key, credit = :credit, debit = :debit, closing_balance = :closing, trans_id = :trans_id, trans_status = :trans_status WHERE id = :id";
                                        $paramsEcash = [
                                            'channel_key' => $ecashChannelKey,
                                            'credit' => $ecashCredit,
                                            'debit' => $ecashDebit,
                                            'closing' => $ecashClosing,
                                            'trans_id' => $trans_id,
                                            'trans_status' => $trans_status,
                                            'id' => $rowEcash->id,
                                        ];
                                        $dbBal->query($updateEcashSql, $paramsEcash);
                                    }
                                }
                            }
                        } catch (\Throwable $e) {
                            error_log('MOMOREMIT ECASH CASHDRAWER UPDATE FAILED: ' . $e->getMessage());
                        }
                    }
                } catch (\Throwable $e) {
                    error_log('MOMOREMIT CASHDRAWER UPDATE FAILED: ' . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            error_log('MOMOREMIT CASHDRAWER SYNC FAILED: ' . $e->getMessage());
        }

        if ($ecashClosingBalance !== null) {
            $postdata['balance_after'] = number_format($ecashClosingBalance, 2, '.', '');
        }

        $maxDbStringLengths = [
            'network' => 50,
            'transaction_type' => 50,
            'transaction_status' => 50,
            'category' => 50,
            'sending_category' => 50,
            'phone' => 50,
            'sender' => 50,
            'sim_type' => 50,
            'charging_type' => 50,
            'receiver' => 50,
            'token' => 50,
            'id_type' => 50,
            'id_number' => 50,
            'secret_code' => 50,
            'wallet_in_id' => 50,
            'wallet_out_id' => 50,
            'slug' => 100,
            'transaction_image' => 240,
        ];
        foreach ($maxDbStringLengths as $field => $maxLength) {
            if (isset($postdata[$field]) && is_string($postdata[$field]) && strlen($postdata[$field]) > $maxLength) {
                $postdata[$field] = substr($postdata[$field], 0, $maxLength);
            }
        }

        // Insert into specific transaction type tables
        switch ($postdata['transaction_type']) {
            case 'Cash in':
                $sending->insert($postdata);
                break;
            case 'Withdrawal':
                $withdrawal->insert($postdata);
                break;
            case 'Deposit':
                $deposit->insert($postdata);
                break;
            case 'CIT':
                // Prepare CIT data
                $citData = $postdata;
                $citData['amount_to_withdraw'] = $postdata['amount_to_withdraw'] ?? 0;
                $citData['amount_to_receive'] = $postdata['amount_payable'] ?? 0;
                $citData['slug'] = $cit->str_to_url($postdata['network'] . '_CIT_' . $postdata['phone']);
                $cit->insert($citData);
                break;
        }

        // Insert the momoremit record
        $momoremit->insert($postdata);
        // Database::query() returns false for non-SELECT statements even on success.
        if ($momoremit->has_error || !empty($momoremit->error)) {
            try {
                $dbMutation->query('ROLLBACK');
            } catch (\Throwable $e) {}
            error_log(
                'MOMOREMIT INSERT FAILED [' . ($postdata['transaction_type'] ?? 'unknown') . ']: ' .
                ($momoremit->error ?: 'unknown database error')
            );
            $momoremit->errors['amount'] = 'Unable to save this MoMoRemit transaction on the server. Please try again or contact admin.';
            set_value('errors', array_merge($momoremit->errors, $req->upload_errors));
            return;
        }

        $chargeValue = (float)($postdata['charge'] ?? 0);
        $networkForCommission = trim((string)($postdata['network'] ?? ''));
        $transactionTypeForCommission = trim((string)($postdata['transaction_type'] ?? ''));
        $sendingCategoryForCommission = trim((string)($postdata['sending_category'] ?? ''));
        $statusForCommission = trim((string)($postdata['transaction_status'] ?? $trans_status ?? ''));
        $networkUpper = strtoupper($networkForCommission);
        $commissionAmount = function_exists('momoremits_calc_charge_commission_amount')
            ? momoremits_calc_charge_commission_amount($postdata)
            : $chargeValue;
        if ($chargeValue > 0 && $commissionAmount > 0) {
            try {
                $commissionDb = new \Core\Database;
                $commissionDescription = 'MOMOREMIT CHARGE | ' . ($networkUpper ?: ($postdata['network'] ?? '')) . ' | ' . strtoupper((string)$transactionTypeForCommission) . ' | ' . strtoupper((string)$sendingCategoryForCommission) . ' | ID ' . ($postdata['id'] ?? '');
                $commissionInsert = [
                    'date_received' => $postdata['date_created'] ?? date('Y-m-d H:i:s'),
                    'colletion_period' => 'INSTANT',
                    'network' => $postdata['network'] ?? null,
                    'commission_type' => 'MOMOREMIT CHARGE',
                    'description' => $commissionDescription,
                    'pay_to' => $user_id,
                    'receiving_wallet' => null,
                    'amount' => $commissionAmount,
                    'transaction_status' => $statusForCommission ?: 'Approved',
                    'created_by' => $user_id,
                    'updated_by' => null,
                    'disabled' => 0,
                    'deleted' => 0,
                    'date_created' => $postdata['date_created'] ?? date('Y-m-d H:i:s'),
                    'date_updated' => null,
                    'date_deleted' => null,
                ];
                $commissionSql = "INSERT INTO commissions (" . implode(',', array_keys($commissionInsert)) . ") VALUES (:" . implode(',:', array_keys($commissionInsert)) . ")";
                $commissionDb->query($commissionSql, $commissionInsert);
            } catch (\Throwable $e) {
                error_log('MOMOREMIT CHARGE COMMISSION INSERT FAILED: ' . $e->getMessage());
            }
        }

        
        $postdata['created_by'] = $user_id;

        // Table is created automatically if it doesn't exist

        // Calculate and record cashout commission strictly for withdrawal / cashout transactions
        $postdata['created_by'] = $user_id;
        $txTypeForCashout = trim((string)($postdata['transaction_type'] ?? ''));
        
        if (in_array($txTypeForCashout, ['Withdrawal', 'CIT'], true)) {
            momoremit_ensure_cashout_commissions_table();
            $cashoutCommissionData = momoremit_calculate_cashout_commission($postdata);
            if ($cashoutCommissionData !== null) {
                try {
                    error_log('MOMOREMIT CASHOUT COMMISSION RECORDED: Transaction ID ' . ($postdata['id'] ?? 0) . 
                              ', Amount: GHS ' . number_format($cashoutCommissionData['commission_amount'], 2) .
                              ', Status: PENDING');
                } catch (\Throwable $e) {
                    error_log('Error logging cashout commission: ' . $e->getMessage());
                }
            }
        }


        // Log activity
        $action = 'added';
        $transaction = $postdata['network'] . ' ' . $postdata['transaction_type'];
        $transaction_id = $postdata['id'];
        $table = 'momoremits';
        $description = "$username $action a new momoremit called $transaction with id $transaction_id to $table";
        $date_created = date("Y-m-d H:i:s");
        $log->insert([
            'table_name' => $table,
            'action' => $action,
            'user_id' => $user_id,
            'user_name' => $username,
            'description' => $description,
            'date_created' => $date_created,
        ]);

        try {
            $dbMutation->query('COMMIT');
        } catch (\Throwable $e) {}

        message_success("Transaction ($transaction) added successfully!");
       redirect($admin_route . '/' . $plugin_route);
    }
}

// Handle CSRF failure
if (!$csrf) {
    $momoremit->errors['network'] = "Form expired!";
}

set_value('errors', array_merge($momoremit->errors, $req->upload_errors));
?>

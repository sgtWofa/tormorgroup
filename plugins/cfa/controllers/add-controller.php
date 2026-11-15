<?php

use function Cfas\cfa_fetch_ghs_denominations;
use function Cfas\cfa_validate_ghs_supply;
use function Cfas\cfa_fetch_fx_denominations;
use function Cfas\cfa_validate_fx_supply;
use function Cfas\cfa_normalize_transaction_type;

	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$postdata = $req->post();
	$deno = new \Cfas\Deno;

	// Ensure customer_phone column exists automatically in cfas table
	$ensureCustomerPhoneColumn = function() use ($cfa) {
		try {
			$dbSchema = new \Core\Database;
			$hasColumn = $dbSchema->query(
				"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'cfas' AND COLUMN_NAME = 'customer_phone' LIMIT 1",
				['db' => DB_NAME]
			);
			if (empty($hasColumn)) {
				$dbSchema->query("ALTER TABLE cfas ADD COLUMN customer_phone VARCHAR(30) NULL");
			}
		} catch (\Throwable $e) {
			$cfa->errors['customer_phone'] = 'Unable to prepare customer phone storage. Please contact admin.';
			return false;
		}
		return true;
	};
	if (!$ensureCustomerPhoneColumn()) {
		set_value('errors', $cfa->errors);
		return;
	}

	// Dynamically append customer_phone to allowed columns if model exists
	if (isset($cfa->allowed_columns) && is_array($cfa->allowed_columns) && !in_array('customer_phone', $cfa->allowed_columns, true)) {
		$cfa->allowed_columns[] = 'customer_phone';
	}

	$csrf = csrf_verify($postdata);
    $currencyDetails = $postdata['denos'] ?? '[]';

    // Decode the JSON currency details
    $currencyArray = json_decode($currencyDetails, true);
    if(!is_array($currencyArray)){
        $currencyArray = [];
    }
    $currencyArray = array_values(array_filter(array_map(function($row) {
        if (!is_array($row)) {
            return null;
        }
        $currency = strtoupper(trim((string)($row['currency'] ?? '')));
        $denomination = (float)($row['denomination'] ?? 0);
        $count = (int)($row['count'] ?? 0);
        $volume = (float)($row['volume'] ?? ($denomination * abs($count)));
        if (!in_array($currency, ['GHS', 'CFA'], true) || $denomination <= 0 || $count === 0 || $volume == 0.0) {
            return null;
        }
        $sign = ($count < 0 || $volume < 0) ? -1 : 1;
        return [
            'currency' => $currency,
            'denomination' => $denomination,
            'count' => $sign * abs($count),
            'volume' => $sign * abs($volume),
        ];
    }, $currencyArray)));

    $session = get_session();
    $user_id = !empty($session->id) ? $session->id : null;
    $statusTarget = strtolower($postdata['transaction_status'] ?? '');
    $transactionType = cfa_normalize_transaction_type($postdata['transaction_type'] ?? '');
    $descriptionInput = trim((string)($postdata['description'] ?? ''));
    $customerPhone = trim((string)($postdata['customer_phone'] ?? ''));
    $postdata['customer_phone'] = $customerPhone !== '' ? $customerPhone : null;

    $ghsPayload = array_filter($currencyArray, function($row){
        return strtoupper($row['currency'] ?? '') === 'GHS' && (int)($row['count'] ?? 0) > 0;
    });
    $cfaPayload = array_filter($currencyArray, function($row){
        return strtoupper($row['currency'] ?? '') === 'CFA' && (int)($row['count'] ?? 0) > 0;
    });
    $sumPayload = function(string $currency, bool $positiveOnly = false, bool $negativeOnly = false) use ($currencyArray): float {
        $total = 0.0;
        foreach ($currencyArray as $row) {
            if (strtoupper($row['currency'] ?? '') !== strtoupper($currency)) {
                continue;
            }
            $volume = (float)($row['volume'] ?? 0);
            if ($positiveOnly && $volume <= 0) {
                continue;
            }
            if ($negativeOnly && $volume >= 0) {
                continue;
            }
            $total += $volume;
        }
        return (float)number_format($total, 2, '.', '');
    };
    if($transactionType === 'cfa to ghs'){
        $normalizedGhs = cfa_normalize_payable_ghs_amount($postdata['amount_ghs'] ?? 0);
        $postdata['amount_ghs'] = $normalizedGhs;
        if (isset($postdata['amount_out'])) {
            $postdata['amount_out'] = $normalizedGhs;
        }
        $expectedCfaPayable = (float)($postdata['amount_in'] ?? $postdata['amount_cfa'] ?? 0);
        if($expectedCfaPayable > 0 && abs(fmod($expectedCfaPayable, 50.0)) > 0.01 && abs(fmod($expectedCfaPayable, 50.0) - 50.0) > 0.01){
            $cfa->errors['amount_cfa'] = 'Amount in CFA must be a payable multiple of 50. Select one of the suggested conversions.';
        }
    }
    if($transactionType === 'ghs to cfa'){
        $amountCfaPayable = (float)($postdata['amount_cfa'] ?? 0);
		$converterCfa = (float)($postdata['amount_out'] ?? $amountCfaPayable);
		if($converterCfa > 0 && $converterCfa < 1000){
			$postdata['amount_in'] = cfa_normalize_payable_ghs_amount($postdata['amount_in'] ?? 0);
		}
        if($amountCfaPayable > 0 && abs(fmod($amountCfaPayable, 50.0)) > 0.01 && abs(fmod($amountCfaPayable, 50.0) - 50.0) > 0.01){
            $cfa->errors['amount_cfa'] = 'Amount in CFA must be a payable multiple of 50. Select one of the suggested conversions.';
        }
    }

    if($descriptionInput === ''){
        $amountCfa = (float)($postdata['amount_cfa'] ?? 0);
        $amountGhs = (float)($postdata['amount_ghs'] ?? 0);
        if($transactionType === 'cfa to ghs'){
            $rate = (float)($postdata['buying_rate'] ?? 0);
            $postdata['description'] = 'Buy CFA - Customer paid CFA '.number_format($amountCfa,2).', customer received GH₵ '.number_format($amountGhs,2).'.';
            if($rate > 0){
                $postdata['description'] .= ' Rate: CFA1,000 = GH₵ '.number_format($rate,2).'.';
            }
        }elseif($transactionType === 'ghs to cfa'){
            $rate = (float)($postdata['selling_rate'] ?? 0);
            $postdata['description'] = 'Sell CFA - Customer paid GH₵ '.number_format($amountGhs,2).', customer received CFA '.number_format($amountCfa,2).'.';
            if($rate > 0){
                $postdata['description'] .= ' Rate: CFA1,000 = GH₵ '.number_format($rate,2).'.';
            }
        }else{
            $postdata['description'] = 'CFA exchange';
        }
    }

    if(in_array($statusTarget, ['pending', 'complete'], true)){
        if(empty($user_id)){
            $cfa->errors['cashdrawer'] = 'Login required to verify cashdrawer balances.';
        }else{
            $drawerContext = \Cfas\cfa_find_active_drawer($user_id);
            if(empty($drawerContext)){
                $cfa->errors['cashdrawer'] = 'Open a cashdrawer before completing this exchange.';
            }else{
                try {
                    \Cfas\cfa_assert_flow_denominations_available(
                        (int)$drawerContext['drawer_id'],
                        (int)$drawerContext['drawer_user_id'],
                        \Cfas\cfa_group_denominations($currencyArray),
                        \Cfas\cfa_determine_flow($transactionType, (float)($postdata['amount_cfa'] ?? 0), (float)($postdata['amount_ghs'] ?? 0))
                    );
                } catch (\Throwable $e) {
                    $cfa->errors['denos'] = $e->getMessage();
                }

                if($transactionType === 'cfa to ghs'){
                    $expectedCfaIn = (float)($postdata['amount_in'] ?? 0);
                    $actualCfaIn = $sumPayload('CFA', true, false);
                    $cfaChangeGiven = abs($sumPayload('CFA', false, true));
                    $requiredCfaChange = max(0, (float)number_format($actualCfaIn - $expectedCfaIn, 2, '.', ''));
                    if ($expectedCfaIn > 0 && $requiredCfaChange > 0.01 && abs($cfaChangeGiven - $requiredCfaChange) > 0.01) {
                        $cfa->errors['denos'] = 'CFA change denominations must total CFA ' . number_format($requiredCfaChange, 2) . '.';
                    } elseif ($expectedCfaIn > 0 && $requiredCfaChange <= 0.01 && $cfaChangeGiven > 0.01) {
                        $cfa->errors['denos'] = 'CFA change was selected but no CFA change is required.';
                    } elseif ($expectedCfaIn > 0 && abs(($actualCfaIn - $cfaChangeGiven) - $expectedCfaIn) > 0.01) {
                        $cfa->errors['denos'] = 'Net CFA received after change must match the converter amount.';
                    }
                    if(empty($ghsPayload)){
                        $cfa->errors['denos'] = 'Capture the GH₵ denominations being paid out before completion.';
                    }else{
                        $availableBalances = cfa_fetch_ghs_denominations($drawerContext['drawer_id'], $drawerContext['drawer_user_id']);
                        $availabilityErrors = cfa_validate_ghs_supply($ghsPayload, $availableBalances);
                        if(!empty($availabilityErrors)){
                            $cfa->errors['denos'] = implode(' ', $availabilityErrors);
                        }
                    }
                }

                if($transactionType === 'ghs to cfa'){
                    $expectedGhsIn = (float)($postdata['amount_in'] ?? 0);
                    $actualGhsIn = $sumPayload('GHS', true, false);
                    $ghsChangeGiven = abs($sumPayload('GHS', false, true));
                    $requiredGhsChange = max(0, (float)number_format($actualGhsIn - $expectedGhsIn, 2, '.', ''));
                    if ($expectedGhsIn > 0 && $requiredGhsChange > 0.01 && abs($ghsChangeGiven - $requiredGhsChange) > 0.01) {
                        $cfa->errors['denos'] = 'GHS change denominations must total GHS ' . number_format($requiredGhsChange, 2) . '.';
                    } elseif ($expectedGhsIn > 0 && $requiredGhsChange <= 0.01 && $ghsChangeGiven > 0.01) {
                        $cfa->errors['denos'] = 'GHS change was selected but no GHS change is required.';
                    } elseif ($expectedGhsIn > 0 && abs(($actualGhsIn - $ghsChangeGiven) - $expectedGhsIn) > 0.01) {
                        $cfa->errors['denos'] = 'Net GHS received after change must match the converter amount.';
                    }
                    if(empty($cfaPayload)){
                        $cfa->errors['denos'] = 'Capture the CFA denominations being paid out before completion.';
                    }else{
                        $availableBalances = cfa_fetch_fx_denominations($drawerContext['drawer_id'], $drawerContext['drawer_user_id'], 'CFA');
                        $availabilityErrors = cfa_validate_fx_supply($cfaPayload, $availableBalances, 'CFA');
                        if(!empty($availabilityErrors)){
                            $cfa->errors['denos'] = implode(' ', $availabilityErrors);
                        }
                    }
                }
            }
        }
    }

	if($csrf && empty($cfa->errors) && $cfa->validate_insert($postdata))
	{
		if(user_can('add_cfa'))
		{
			$postdata['date_created'] = date("Y-m-d H:i:s");
			$postdata['id'] = time();

			$instantCommissionAmount = 0.0;
			$instantCommissionDescription = '';
			if ($statusTarget === 'complete' && $transactionType === 'ghs to cfa') {
				$amountCfa = (float)($postdata['amount_cfa'] ?? 0);
				$sellingRate = (float)($postdata['selling_rate'] ?? 0);
				$buyingRate = (float)($postdata['buying_rate'] ?? 0);
				$rateDiff = $sellingRate - $buyingRate;

				if ($rateDiff > 0 && $amountCfa > 0) {
					$instantCommissionAmount = (float)number_format($amountCfa * ($rateDiff / 1000), 2, '.', '');
					if ($instantCommissionAmount < 0.01) {
						$instantCommissionAmount = 0.0;
					}
				}

				if ($instantCommissionAmount > 0) {
					$instantCommissionDescription = 'CFA INSTANT | ' . strtoupper($transactionType) . ' | ID ' . ($postdata['id'] ?? '');
				}
			}

			if(!empty($session->id)){
				$username = $session->first_name . ' '. $session->last_name;
				$user_id = $session->id;
			}else{
				$username = $row->first_name . ' '. $row->last_name;
				$user_id = $row->id;
			}

			$postdata['created_by'] = $user_id;

	        $denosPost = [];

	        // Prepare and insert denominations without duplicates
	        $processed = [];
	        if (!empty($currencyArray)) {
	            foreach ($currencyArray as $currency) {
	                // build a unique key per denomination and direction
	                $countValue = (int)($currency['count'] ?? 0);
	                $key = ($currency['currency'] ?? '') . '-' . ($currency['denomination'] ?? 0) . '-' . ($countValue < 0 ? 'change' : 'main');
	                // skip if already processed or count is zero
	                if (isset($processed[$key]) || $countValue === 0) {
	                    continue;
	                }
	                $processed[$key] = true;

	                // prepare insertion data
	                $denosPost = [
	                    'trans_id'     => $postdata['id'],
	                    'currency'     => $currency['currency'],
	                    'denomination' => $currency['denomination'],
	                    'count'        => (int)$currency['count'],
	                    'volume'       => (float)$currency['volume'],
	                    'created_by'   => $user_id,
	                    'date_created' => $postdata['date_created'],
	                ];

	                // insert the deno record
	                $deno->insert($denosPost);
	            }
	        }


	        $cfa->insert($postdata);
	        if(function_exists('Cfas\\cfa_record_transaction_rate_history') && !empty($cfa->insert_id)){
	        	$rateHistoryRow = (object)$postdata;
	        	$rateHistoryRow->id = $cfa->insert_id;
	        	\Cfas\cfa_record_transaction_rate_history($rateHistoryRow);
	        }
	        if (!empty($session->id)) {
	            try {
	                \Cfas\cfa_sync_cashdrawer_channels($postdata, $currencyArray ?? [], $session->id);
	            } catch (\Throwable $e) {
	                try {
	                    $cfa->delete($postdata['id']);
	                } catch (\Throwable $cleanupError) {}
	                $cfa->errors['denos'] = $e->getMessage();
	                set_value('errors', $cfa->errors);
	                message_fail($cfa->errors['denos']);
	                return;
	            }
	        }

			if ($instantCommissionAmount > 0) {
				try {
					$commissionDb = new \Core\Database;
					$commissionInsert = [
						'date_received' => $postdata['date_created'] ?? date('Y-m-d H:i:s'),
						'colletion_period' => 'INSTANT',
						'network' => 'CFA',
						'commission_type' => 'CFA INSTANT',
						'description' => $instantCommissionDescription,
						'pay_to' => $user_id,
						'receiving_wallet' => null,
						'amount' => $instantCommissionAmount,
						'transaction_status' => 'Approved',
						'created_by' => $user_id,
						'updated_by' => null,
						'approved_by' => null,
						'approved_at' => null,
						'disabled' => 0,
						'deleted' => 0,
						'date_created' => $postdata['date_created'] ?? date('Y-m-d H:i:s'),
						'date_updated' => null,
						'date_deleted' => null,
					];
					$commissionSql = "INSERT INTO commissions (".implode(',', array_keys($commissionInsert)).") VALUES (:".implode(',:', array_keys($commissionInsert)).")";
					$commissionDb->query($commissionSql, $commissionInsert);
				} catch (\Throwable $e) {
					error_log('CFA INSTANT COMMISSION INSERT FAILED: ' . $e->getMessage());
				}
			}

			//log changes in the log table
			$action 		= 'added';
			$transaction 		= 'CFA exchange : ' . $postdata['transaction_type'] . ', status: ' . $postdata['transaction_status'] ;
			$transaction_id 		= $postdata['id'];
			$table 			= 'cfas';
			$description 	= "$username $action a new $transaction with id $transaction_id to $table";
			$date_created 	= date("Y-m-d H:i:s");

		    $log = new \Cfas\Log;
		    $log_activity = $log->insert([
			 	'table_name'=>$table,
			 	'action'=>$action,
			 	'user_id'=>$user_id,
			 	'user_name'=>$username,
			 	'description'=>$description,
			 	'date_created'=>$date_created,
			 ]);
 
			message_success("Transaction ($transaction) added successfully!");
			redirect($admin_route.'/'.$plugin_route);
		}
	}

	if(!$csrf)
		$cfa->errors['description'] = "Form expired!";
	
	set_value('errors',$cfa->errors);
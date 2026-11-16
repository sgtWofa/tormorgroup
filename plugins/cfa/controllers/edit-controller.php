<?php

use function Cfas\cfa_fetch_ghs_denominations;
use function Cfas\cfa_validate_ghs_supply;
use function Cfas\cfa_remove_cashdrawer_entries;
use function Cfas\cfa_normalize_transaction_type;

	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	if (in_array(strtolower(trim((string)($row->transaction_status ?? ''))), ['cancelled', 'canceled'], true)) {
		$message = 'Cancelled CFA transactions cannot be edited.';
		$cfa->errors['transaction_status'] = $message;
		set_value('errors', $cfa->errors);
		message_fail($message);
		return;
	}

	$postdata = $req->post();
	$deno = new \Cfas\Deno;
	$csrf = csrf_verify($postdata);

	// Ensure database columns exist automatically
	$ensureCfaColumns = function() use ($cfa) {
		try {
			$dbSchema = new \Core\Database;
			$hasRejection = $dbSchema->query(
				"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'cfas' AND COLUMN_NAME = 'rejection_reason' LIMIT 1",
				['db' => DB_NAME]
			);
			if (empty($hasRejection)) {
				$dbSchema->query("ALTER TABLE cfas ADD COLUMN rejection_reason LONGTEXT NULL");
			}

			$hasPhone = $dbSchema->query(
				"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'cfas' AND COLUMN_NAME = 'customer_phone' LIMIT 1",
				['db' => DB_NAME]
			);
			if (empty($hasPhone)) {
				$dbSchema->query("ALTER TABLE cfas ADD COLUMN customer_phone VARCHAR(30) NULL");
			}
		} catch (\Throwable $e) {
			$cfa->errors['customer_phone'] = 'Unable to prepare database schema storage. Please contact admin.';
			return false;
		}
		return true;
	};
	if (!$ensureCfaColumns()) {
		set_value('errors', $cfa->errors);
		return;
	}

	// Dynamically add customer_phone to allowed columns
	if (isset($cfa->allowed_columns) && is_array($cfa->allowed_columns) && !in_array('customer_phone', $cfa->allowed_columns, true)) {
		$cfa->allowed_columns[] = 'customer_phone';
	}

	$session = get_session();
	$user_id = !empty($session->id) ? $session->id : null;
	$transactionType = cfa_normalize_transaction_type($postdata['transaction_type'] ?? ($row->transaction_type ?? ''));
	$amountCfa = (float)($postdata['amount_cfa'] ?? ($row->amount_cfa ?? 0));
	$amountGhs = (float)($postdata['amount_ghs'] ?? ($row->amount_ghs ?? 0));
	if($transactionType === 'cfa to ghs'){
		$amountGhs = cfa_normalize_payable_ghs_amount($amountGhs);
		$postdata['amount_ghs'] = $amountGhs;
		if (isset($postdata['amount_out'])) {
			$postdata['amount_out'] = $amountGhs;
		}
		$expectedCfaPayable = (float)($postdata['amount_in'] ?? $amountCfa ?? 0);
		if($expectedCfaPayable > 0 && abs(fmod($expectedCfaPayable, 50.0)) > 0.01 && abs(fmod($expectedCfaPayable, 50.0) - 50.0) > 0.01){
			$cfa->errors['amount_cfa'] = 'Amount in CFA must be a payable multiple of 50. Select one of the suggested conversions.';
		}
	}
	if($transactionType === 'ghs to cfa'){
		if($amountCfa > 0 && abs(fmod($amountCfa, 50.0)) > 0.01 && abs(fmod($amountCfa, 50.0) - 50.0) > 0.01){
			$cfa->errors['amount_cfa'] = 'Amount in CFA must be a payable multiple of 50. Select one of the suggested conversions.';
		}
	}
	$descriptionInput = trim((string)($postdata['description'] ?? ($row->description ?? '')));
	$customerPhone = trim((string)($postdata['customer_phone'] ?? ($row->customer_phone ?? '')));
	$postdata['customer_phone'] = $customerPhone !== '' ? $customerPhone : null;

	$existingStatus = strtolower(trim($row->transaction_status ?? ''));
	$statusTarget = strtolower($postdata['transaction_status'] ?? $existingStatus);
	$rejectionReason = trim((string)($postdata['rejection_reason'] ?? ($row->rejection_reason ?? '')));
	$postdata['rejection_reason'] = ($statusTarget === 'rejected') ? $rejectionReason : null;

	if ($existingStatus === 'complete') {
		if ($statusTarget !== 'rejected' && $statusTarget !== 'complete') {
			$cfa->errors['transaction_status'] = 'Completed CFA transactions can only be changed to Rejected.';
		}
		
		$lockedFields = [
			'transaction_type',
			'amount_cfa',
			'amount_ghs',
			'buying_rate',
			'selling_rate',
			'description',
			'date_created',
			'customer_phone'
		];
		
		foreach ($lockedFields as $field) {
			$postdata[$field] = $row->{$field} ?? ($postdata[$field] ?? null);
		}

		// Recreate currency details from DB, overriding any new form submission
		$existingDenos = $deno->where(['trans_id' => $row->id]);
		$recreatedDenos = [];
		if(!empty($existingDenos)){
			foreach($existingDenos as $existing){
				$recreatedDenos[] = [
					'currency' => $existing->currency ?? '',
					'denomination' => (float)($existing->denomination ?? 0),
					'count' => (int)($existing->count ?? 0),
					'volume' => (float)($existing->volume ?? 0),
				];
			}
		}
		$postdata['denos'] = json_encode($recreatedDenos);
	}

	$currencyDetails = $postdata['denos'] ?? '[]';
	$currencyArray = json_decode($currencyDetails, true);
	if(!is_array($currencyArray)){
		$currencyArray = [];
	}

	if(empty($currencyArray) && !empty($row->id ?? null)){
		$existingDenos = $deno->where(['trans_id' => $row->id]);
		if(!empty($existingDenos)){
			foreach($existingDenos as $existing){
				$currencyArray[] = [
					'currency' => $existing->currency ?? '',
					'denomination' => (float)($existing->denomination ?? 0),
					'count' => (int)($existing->count ?? 0),
					'volume' => (float)($existing->volume ?? 0),
				];
			}
		}
	}

	$hasGhsEntries = false;
	$hasCfaEntries = false;
	foreach($currencyArray as $entry){
		if(strtoupper($entry['currency'] ?? '') === 'GHS' && (int)($entry['count'] ?? 0) > 0){
			$hasGhsEntries = true;
		}
		if(strtoupper($entry['currency'] ?? '') === 'CFA' && (int)($entry['count'] ?? 0) > 0){
			$hasCfaEntries = true;
		}
	}

	if(!$hasGhsEntries && $amountGhs > 0){
		$currencyArray[] = [
			'currency' => 'GHS',
			'denomination' => $amountGhs,
			'count' => 1,
			'volume' => $amountGhs,
		];
		$hasGhsEntries = true;
	}

	if(!$hasCfaEntries && $amountCfa > 0){
		$currencyArray[] = [
			'currency' => 'CFA',
			'denomination' => $amountCfa,
			'count' => 1,
			'volume' => $amountCfa,
		];
		$hasCfaEntries = true;
	}

	$statusTarget = strtolower($postdata['transaction_status'] ?? '');
	$flowMap = \Cfas\cfa_determine_flow($transactionType, $amountCfa, $amountGhs);
	$requiresGhsPayout = !empty($flowMap['GHS']) && (($flowMap['GHS']['sign'] ?? 0) < 0);
	$ghsPayload = array_filter($currencyArray, function($row){
		return strtoupper($row['currency'] ?? '') === 'GHS' && (int)($row['count'] ?? 0) > 0;
	});

	if($descriptionInput === ''){
		if($transactionType === 'cfa to ghs'){
			$rate = (float)($postdata['buying_rate'] ?? ($row->buying_rate ?? 0));
			$postdata['description'] = 'Buy CFA - Customer paid CFA '.number_format($amountCfa,2).', customer received GH₵ '.number_format($amountGhs,2).'.';
			if($rate > 0){
				$postdata['description'] .= ' Rate: CFA1,000 = GH₵ '.number_format($rate,2).'.';
			}
		}elseif($transactionType === 'ghs to cfa'){
			$rate = (float)($postdata['selling_rate'] ?? ($row->selling_rate ?? 0));
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
						$flowMap
					);
				} catch (\Throwable $e) {
					$cfa->errors['denos'] = $e->getMessage();
				}
			}
		}
	}

	if(in_array($statusTarget, ['pending', 'complete'], true) && $requiresGhsPayout){
		if(empty($user_id)){
			$cfa->errors['cashdrawer'] = 'Login required to verify cashdrawer balances.';
		}else{
			$drawerContext = \Cfas\cfa_find_active_drawer($user_id);
			if(empty($drawerContext)){
				$cfa->errors['cashdrawer'] = 'Open a cashdrawer before completing this exchange.';
			}elseif(empty($ghsPayload)){
				$cfa->errors['denos'] = 'Capture the GH₵ denominations paid out before completion.';
			}else{
				$availableBalances = cfa_fetch_ghs_denominations($drawerContext['drawer_id'], $drawerContext['drawer_user_id']);
				$availabilityErrors = cfa_validate_ghs_supply($ghsPayload, $availableBalances);
				if(!empty($availabilityErrors)){
					$cfa->errors['denos'] = implode(' ', $availabilityErrors);
				}
			}
		}
	}

	if($statusTarget === 'rejected' && $rejectionReason === ''){
		$cfa->errors['rejection_reason'] = 'Rejection reason is required when rejecting a CFA transaction.';
	}

	if($csrf && empty($cfa->errors) && $cfa->validate_update($postdata))
	{
		if(user_can('edit_cfa'))
		{
			$postdata['date_updated'] = date("Y-m-d H:i:s");
			unset($postdata['wallet_in_id'], $postdata['wallet_out_id']);
			$postdata['amount_cfa'] = $amountCfa;
			$postdata['amount_ghs'] = $amountGhs;

			$instantCommissionAmount = 0.0;
			$instantCommissionDescription = '';
			if ($statusTarget === 'complete' && $transactionType === 'ghs to cfa') {
				$sellingRate = (float)($postdata['selling_rate'] ?? ($row->selling_rate ?? 0));
				$buyingRate = (float)($postdata['buying_rate'] ?? ($row->buying_rate ?? 0));
				$rateDiff = $sellingRate - $buyingRate;

				if ($rateDiff > 0 && $amountCfa > 0) {
					$instantCommissionAmount = (float)number_format($amountCfa * ($rateDiff / 1000), 2, '.', '');
					if ($instantCommissionAmount < 0.01) {
						$instantCommissionAmount = 0.0;
					}
				}

				if ($instantCommissionAmount > 0) {
					$instantCommissionDescription = 'CFA INSTANT | ' . strtoupper($transactionType) . ' | ID ' . ($row->id ?? '');
				}
			}

			if(!empty($session->id)){
				$username = $session->first_name . ' '. $session->last_name;
				$user_id = $session->id;
			}else{
				$username = $row->first_name . ' '. $row->last_name;
				$user_id = $row->id;
			}

			$postdata['updated_by'] = $user_id;
			$transId = (int)($row->id ?? 0);

			if($transId <= 0){
				$cfa->errors['transaction'] = 'Missing exchange record reference.';
			}else{
				// refresh denomination breakdown
				$deno->query("DELETE FROM denos WHERE trans_id = :trans_id", ['trans_id' => $transId]);
				$processed = [];
				if (!empty($currencyArray)) {
					foreach ($currencyArray as $currency) {
						$key = ($currency['currency'] ?? '') . '-' . ($currency['denomination'] ?? 0);
						if (isset($processed[$key]) || (int)($currency['count'] ?? 0) <= 0) {
							continue;
						}
						$processed[$key] = true;
						$deno->insert([
							'trans_id'     => $transId,
							'currency'     => $currency['currency'],
							'denomination' => $currency['denomination'],
							'count'        => (int)$currency['count'],
							'volume'       => (float)$currency['volume'],
							'created_by'   => $user_id,
							'date_created' => $row->date_created ?? date("Y-m-d H:i:s"),
						]);
					}
				}

				$updateData = $postdata;
				unset($updateData['id']);
				$cfa->update($transId, $updateData);
				if(function_exists('Cfas\\cfa_record_transaction_rate_history')){
					$rateHistoryRow = (object)$updateData;
					$rateHistoryRow->id = $transId;
					$rateHistoryRow->created_by = $updateData['updated_by'] ?? ($row->created_by ?? 0);
					$rateHistoryRow->date_created = $updateData['date_created'] ?? ($row->date_created ?? date('Y-m-d H:i:s'));
					\Cfas\cfa_record_transaction_rate_history($rateHistoryRow);
				}

				if (!empty($session->id)) {
					cfa_remove_cashdrawer_entries($transId);
					$syncPayload = $postdata;
					$syncPayload['id'] = $transId;
					$syncPayload['date_created'] = $row->date_created ?? date("Y-m-d H:i:s");
					\Cfas\cfa_sync_cashdrawer_channels($syncPayload, $currencyArray, $session->id);
				}

				try {
					$commissionDb = new \Core\Database;
					$commissionDb->query(
						"DELETE FROM commissions WHERE network = :network AND colletion_period = :period AND commission_type IN ('CFA INSTANT','CFA ROUNDING') AND description LIKE :needle",
						[
							'network' => 'CFA',
							'period' => 'INSTANT',
							'needle' => '%| ID ' . $transId,
						]
					);

					if ($instantCommissionAmount > 0 && $statusTarget !== 'rejected') {
						if ($instantCommissionDescription === '') {
							$instantCommissionDescription = 'CFA INSTANT | ' . strtoupper($transactionType) . ' | ID ' . ($transId ?? '');
						}
						$commissionInsert = [
							'date_received' => $postdata['date_updated'] ?? date('Y-m-d H:i:s'),
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
							'date_created' => $postdata['date_updated'] ?? date('Y-m-d H:i:s'),
							'date_updated' => null,
							'date_deleted' => null,
						];
						$commissionSql = "INSERT INTO commissions (".implode(',', array_keys($commissionInsert)).") VALUES (:".implode(',:', array_keys($commissionInsert)).")";
						$commissionDb->query($commissionSql, $commissionInsert);
					}
				} catch (\Throwable $e) {
					error_log('CFA INSTANT COMMISSION UPSERT FAILED (EDIT): ' . $e->getMessage());
				}

				//log changes in the log table
				$action 		= 'updated';
				$transaction 		= 'CFA exchange : ' . ($postdata['transaction_type'] ?? $row->transaction_type) . ', status to ' . ($postdata['transaction_status'] ?? $row->transaction_status) ;
				$table 			= 'cfas';
				$description 	= "$username $action a $transaction";
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

				message_success("Transaction ($transaction) updated successfully!");
				redirect($admin_route.'/'.$plugin_route);
			}
		}
	}

	if(!$csrf)
		$cfa->errors['description'] = "Form expired!";

	if(!empty($cfa->errors)){
		$firstError = reset($cfa->errors);
		if(is_array($firstError)){
			$firstError = implode(' ', $firstError);
		}
		message_fail($firstError ?: 'Unable to update CFA exchange. Please check the highlighted fields.');
	}
	
	set_value('errors',$cfa->errors);
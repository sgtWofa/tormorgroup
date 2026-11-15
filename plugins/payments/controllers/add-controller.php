<?php
	
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$postdata = $req->post();
	$wallet = new \Payments\Wallet;
	$transaction = new \Payments\Transaction;
	$db = new \Core\Database;

	$csrf = csrf_verify($postdata);
	if($csrf && $payment->validate_insert($postdata))
	{
		if(user_can('add_payment'))
		{
			$session = get_session();
			if(!empty($session->id)){
				$username = $session->first_name . ' '. $session->last_name;
				$user_id = (int)$session->id;
			}else{
				$username = $row->first_name . ' '. $row->last_name;
				$user_id = (int)$row->id;
			}

			$category = strtoupper(trim((string)($postdata['category'] ?? '')));
			$salaryMonth = trim((string)($postdata['salary_month'] ?? ''));
			$salaryJson = (string)($postdata['salary_breakdown'] ?? '');
			$itemsJson = (string)($postdata['items_breakdown'] ?? '');

			$salaryBreakdown = [];
			$itemBreakdown = [];

			if($category === 'SALARY'){
				try{
					$salaryBreakdown = json_decode($salaryJson, true);
					if(!is_array($salaryBreakdown)) $salaryBreakdown = [];
				}catch(\Throwable $e){ $salaryBreakdown = []; }
				$breakdownTotal = 0.0;
				foreach($salaryBreakdown as $r){
					$amt = (float)($r['amount_due'] ?? 0);
					if($amt > 0) $breakdownTotal += $amt;
				}
				$postdata['breakdown_total'] = $breakdownTotal;
				$postdata['amount'] = $breakdownTotal;
				$postdata['salary_month'] = $salaryMonth;
			}

			if($category === 'MAINTENANCE' || $category === 'OPERATIONAL' || $category === 'LOGISTICS'){
				try{
					$itemBreakdown = json_decode($itemsJson, true);
					if(!is_array($itemBreakdown)) $itemBreakdown = [];
				}catch(\Throwable $e){ $itemBreakdown = []; }
				$itemTotal = 0.0;
				foreach($itemBreakdown as $it){
					$amt = (float)($it['amount'] ?? 0);
					if($amt > 0) $itemTotal += $amt;
				}
				$postdata['breakdown_total'] = $itemTotal;
				$postdata['amount'] = $itemTotal;
			}elseif($category !== 'SALARY'){
				$postdata['breakdown_total'] = 0.0;
			}

			$postdata['date_created'] = date("Y-m-d H:i:s");
			$postdata['id'] = payments_generate_unique_id($db, 'payments');
			$postdata['created_by'] = $user_id;
			$postdata['cashdrawer_id'] = null;
			$postdata['drawer_user_id'] = null;

			$cashdrawerOwner = payments_resolve_cashdrawer_owner($postdata, $user_id, null);
			if(empty($cashdrawerOwner['ok'])){
				$payment->errors['assigned_staff_user_id'] = $cashdrawerOwner['message'] ?? 'Assign the staff cashdrawer that should fund this payment.';
			}else{
				if(($cashdrawerOwner['mode'] ?? '') === 'assigned'){
					$postdata['assigned_staff_user_id'] = (int)($cashdrawerOwner['user_id'] ?? 0);
				}else{
					$postdata['assigned_staff_user_id'] = null;
				}
			}

			$fundingPayload = payments_prepare_funding_payload($postdata, (float)($postdata['amount'] ?? 0), null);
			if(empty($fundingPayload['ok'])){
				$payment->errors[(string)($fundingPayload['field'] ?? 'payment_mode')] = $fundingPayload['message'] ?? 'Invalid payment funding details.';
			}else{
				$postdata['payment_mode'] = (string)($fundingPayload['payment_mode'] ?? '');
				$postdata['ecash_network'] = $fundingPayload['ecash_network'] ?? null;
				$postdata['denos_json'] = (string)($fundingPayload['denos_json'] ?? '[]');
			}

			$isApproved = (($postdata['transaction_status'] ?? '') === 'Approved');
			if($isApproved && empty($payment->errors) && !empty($cashdrawerOwner['ok']) && !empty($fundingPayload['ok'])){
				$cashdrawerCheck = payments_validate_funding_availability(
					$fundingPayload,
					(int)($cashdrawerOwner['user_id'] ?? 0),
					(float)($postdata['amount'] ?? 0),
					(string)($cashdrawerOwner['owner_label'] ?? 'Selected staff')
				);
				if(empty($cashdrawerCheck['ok'])){
					$payment->errors[(string)($fundingPayload['payment_mode'] ?? '') === 'ecash' ? 'ecash_network' : 'denos_json'] = $cashdrawerCheck['message'] ?? 'Unable to validate the selected cashdrawer.';
				}
			}

			if(!empty($payment->errors)){
				set_value('errors', $payment->errors);
				return;
			}

			$payment->insert($postdata);
			if(!empty($payment->has_error) || (int)($payment->affected_rows ?? 0) < 1){
				$payment->errors['description'] = !empty($payment->error)
					? 'Failed to save this payment: ' . $payment->error
					: 'Failed to save this payment. Please try again.';
				set_value('errors', $payment->errors);
				return;
			}

			if($category === 'SALARY' && !empty($salaryBreakdown) && $db->table_exists(['payment_salary_breakdowns'])){
				try{
					foreach($salaryBreakdown as $b){
						$staffId = trim((string)($b['staff_id'] ?? ''));
						if($staffId === '') continue;
						$db->query(
							"INSERT INTO payment_salary_breakdowns (payment_id, staff_id, staff_name, staff_role, amount_due, salary_month, created_by, date_created)
							 VALUES (:payment_id, :staff_id, :staff_name, :staff_role, :amount_due, :salary_month, :created_by, :date_created)",
							[
								'payment_id' => $postdata['id'],
								'staff_id' => $staffId,
								'staff_name' => (string)($b['staff_name'] ?? ''),
								'staff_role' => (string)($b['staff_role'] ?? ''),
								'amount_due' => (float)($b['amount_due'] ?? 0),
								'salary_month' => $salaryMonth,
								'created_by' => $user_id,
								'date_created' => $postdata['date_created'],
							]
						);
					}
				}catch(\Throwable $e){
					// fail silently
				}
			}

			if(($category === 'MAINTENANCE' || $category === 'OPERATIONAL' || $category === 'LOGISTICS') && !empty($itemBreakdown) && $db->table_exists(['payment_item_breakdowns'])){
				try{
					foreach($itemBreakdown as $it){
						$name = trim((string)($it['item_name'] ?? ''));
						$amt = (float)($it['amount'] ?? 0);
						if($name === '' && $amt <= 0) continue;
						$db->query(
							"INSERT INTO payment_item_breakdowns (payment_id, category, item_name, amount, created_by, date_created)
							 VALUES (:payment_id, :category, :item_name, :amount, :created_by, :date_created)",
							[
								'payment_id' => $postdata['id'],
								'category' => $category,
								'item_name' => $name,
								'amount' => $amt,
								'created_by' => $user_id,
								'date_created' => $postdata['date_created'],
							]
						);
					}
				}catch(\Throwable $e){
					// fail silently
				}
			}

			if($isApproved && !empty($cashdrawerOwner['ok']) && !empty($fundingPayload['ok'])){
				$cashdrawerSync = payments_sync_funding(
					$fundingPayload,
					(int)($cashdrawerOwner['user_id'] ?? 0),
					(float)($postdata['amount'] ?? 0),
					(int)$postdata['id'],
					(string)($postdata['transaction_status'] ?? 'Approved'),
					(string)($cashdrawerOwner['owner_label'] ?? 'Selected staff')
				);

				if(empty($cashdrawerSync['ok'])){
					try{
						if($db->table_exists(['payment_salary_breakdowns'])){
							$db->query("DELETE FROM payment_salary_breakdowns WHERE payment_id = :pid", ['pid' => $postdata['id']]);
						}
						if($db->table_exists(['payment_item_breakdowns'])){
							$db->query("DELETE FROM payment_item_breakdowns WHERE payment_id = :pid", ['pid' => $postdata['id']]);
						}
						$db->query("DELETE FROM payments WHERE id = :id LIMIT 1", ['id' => $postdata['id']]);
					}catch(\Throwable $e){
						// ignore rollback issues
					}

					$payment->errors[(string)($fundingPayload['payment_mode'] ?? '') === 'ecash' ? 'ecash_network' : 'denos_json'] = $cashdrawerSync['message'] ?? 'Failed to sync this payment to the cashdrawer balance.';
					set_value('errors', $payment->errors);
					return;
				}

				if(!empty($cashdrawerSync['drawer_context'])){
					$drawerContext = $cashdrawerSync['drawer_context'];
					$payment->update($postdata['id'], [
						'cashdrawer_id' => (int)($drawerContext['drawer_id'] ?? 0),
						'drawer_user_id' => (int)($drawerContext['drawer_user_id'] ?? 0),
					]);
				}
			}

			//log changes in the log table
			$action 		= 'added';
			$transaction 		= 'outgoing payments ' . $postdata['transaction_status'] ;
			$transaction_id 		= $postdata['id'];
			$table 			= 'payments';
			$description 	= "$username $action a new $transaction with id $transaction_id to $table";
			$date_created 	= date("Y-m-d H:i:s");

		    $log = new \Payments\Log;
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
		$payment->errors['description'] = "Form expired!";
	
	set_value('errors',$payment->errors);


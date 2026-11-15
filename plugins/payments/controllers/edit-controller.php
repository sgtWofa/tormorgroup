<?php
	
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$postdata = $req->post();
	$wallet = new \Payments\Wallet;
	$transaction = new \Payments\Transaction;
	$db = new \Core\Database;

	$previousStatus = $row->transaction_status ?? '';
	$statusChangedToApproved = (($postdata['transaction_status'] ?? '') === 'Approved' && $previousStatus !== 'Approved');

	$csrf = csrf_verify($postdata);
	if($csrf && $payment->validate_update($postdata))
	{
		if(user_can('edit_payment'))
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
			}else{
				$postdata['salary_month'] = null;
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

			$postdata['date_updated'] = date("Y-m-d H:i:s");
			$postdata['updated_by'] = $user_id;

			$postdata['cashdrawer_id'] = null;
			$postdata['drawer_user_id'] = null;

			$cashdrawerOwner = payments_resolve_cashdrawer_owner($postdata, (int)($row->created_by ?? $user_id), $row);
			if(empty($cashdrawerOwner['ok'])){
				$payment->errors['assigned_staff_user_id'] = $cashdrawerOwner['message'] ?? 'Assign the staff cashdrawer that should fund this payment.';
			}else{
				if(($cashdrawerOwner['mode'] ?? '') === 'assigned'){
					$postdata['assigned_staff_user_id'] = (int)($cashdrawerOwner['user_id'] ?? 0);
				}else{
					$postdata['assigned_staff_user_id'] = null;
				}
			}

			$fundingPayload = payments_prepare_funding_payload($postdata, (float)($postdata['amount'] ?? $row->amount ?? 0), $row);
			if(empty($fundingPayload['ok'])){
				$payment->errors[(string)($fundingPayload['field'] ?? 'payment_mode')] = $fundingPayload['message'] ?? 'Invalid payment funding details.';
			}else{
				$postdata['payment_mode'] = (string)($fundingPayload['payment_mode'] ?? '');
				$postdata['ecash_network'] = $fundingPayload['ecash_network'] ?? null;
				$postdata['denos_json'] = (string)($fundingPayload['denos_json'] ?? '[]');
			}

			if($statusChangedToApproved && empty($payment->errors) && !empty($cashdrawerOwner['ok']) && !empty($fundingPayload['ok'])){
				$cashdrawerCheck = payments_validate_funding_availability(
					$fundingPayload,
					(int)($cashdrawerOwner['user_id'] ?? 0),
					(float)($postdata['amount'] ?? $row->amount ?? 0),
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

            // Update momoremit and related tables
            unset($postdata['id']);
            $payment->update($row->id, $postdata);

			if(!empty($cashdrawerOwner['ok']) && payments_user_has_cashdrawer_today((int)($cashdrawerOwner['user_id'] ?? 0))){
				$drawerContext = \BackOfficeCashdrawer\getActiveDrawer((int)($cashdrawerOwner['user_id'] ?? 0));
				if(!empty($drawerContext)){
					$payment->update($row->id, [
						'cashdrawer_id' => (int)($drawerContext['drawer_id'] ?? 0),
						'drawer_user_id' => (int)($drawerContext['drawer_user_id'] ?? 0),
					]);
				}
			}

			if($statusChangedToApproved && !empty($cashdrawerOwner['ok']) && !empty($fundingPayload['ok'])){
				$cashdrawerSync = payments_sync_funding(
					$fundingPayload,
					(int)($cashdrawerOwner['user_id'] ?? 0),
					(float)($postdata['amount'] ?? $row->amount ?? 0),
					(int)$row->id,
					(string)($postdata['transaction_status'] ?? 'Approved'),
					(string)($cashdrawerOwner['owner_label'] ?? 'Selected staff')
				);

				if(!empty($cashdrawerSync['ok']) && !empty($cashdrawerSync['drawer_context'])){
					$drawerContext = $cashdrawerSync['drawer_context'];
					$payment->update($row->id, [
						'cashdrawer_id' => (int)($drawerContext['drawer_id'] ?? 0),
						'drawer_user_id' => (int)($drawerContext['drawer_user_id'] ?? 0),
					]);
				}
			}

			// Refresh breakdown tables for this payment
			try{
				if($db->table_exists(['payment_salary_breakdowns'])){
					$db->query("DELETE FROM payment_salary_breakdowns WHERE payment_id = :pid", ['pid' => $row->id]);
				}
				if($db->table_exists(['payment_item_breakdowns'])){
					$db->query("DELETE FROM payment_item_breakdowns WHERE payment_id = :pid", ['pid' => $row->id]);
				}
			}catch(\Throwable $e){
				// ignore
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
								'payment_id' => $row->id,
								'staff_id' => $staffId,
								'staff_name' => (string)($b['staff_name'] ?? ''),
								'staff_role' => (string)($b['staff_role'] ?? ''),
								'amount_due' => (float)($b['amount_due'] ?? 0),
								'salary_month' => $salaryMonth,
								'created_by' => $user_id,
								'date_created' => $postdata['date_updated'],
							]
						);
					}
				}catch(\Throwable $e){
					// ignore
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
								'payment_id' => $row->id,
								'category' => $category,
								'item_name' => $name,
								'amount' => $amt,
								'created_by' => $user_id,
								'date_created' => $postdata['date_updated'],
							]
						);
					}
				}catch(\Throwable $e){
					// ignore
				}
			}

			//log changes in the log table
			$action 		= 'updated';
			$transaction 		= 'outgoing payments ' . $postdata['transaction_status'] ;
			$transaction_id 		= $row->id;
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
 
			message_success("Transaction ($transaction) updated successfully!");
			redirect($admin_route.'/'.$plugin_route);
		}
	}

	if(!$csrf)
		$payment->errors['description'] = "Form expired!";
	
	set_value('errors',$payment->errors);


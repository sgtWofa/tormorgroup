<?php

if(!empty($row))
{
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$postdata = $req->post();
	$postdata['id'] = $row->id;

	$csrf = csrf_verify($postdata);

	if($csrf && $sim->validate_update($postdata))
	{

        if (($postdata['transaction_status'] ?? '') === 'Assigned' && empty($postdata['assigned'])) {
               $sim->errors['assigned'] = "Incharge is required for assigned sims!";
               set_value('errors', $sim->errors);
        }else
		if(user_can('edit_sim'))
		{
            //dd($postdata); die;
			$postdata['date_updated'] = date("Y-m-d H:i:s");

			$amount = (float)($postdata['amount'] ?? $row->amount ?? 0);
			$postwallet_in = ['balance' => (float)($row->wallet_in_balance ?? 0)];

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

                // Insert reversal transaction (guarded)
                if (!empty($row->wallet_in_id) && $amount != 0 && isset($transaction) && is_object($transaction) && method_exists($transaction, 'insert')) {
                    $reverseTransactionIn = [
                        'wallet_id' => $row->wallet_in_id,
                        'wallet_in_id' => $row->wallet_in_id,
                        'description' => 'Sale of: ' . ($row->network ?? '') . ' simcard',
                        'transaction_type' => 'credit',
                        'amount' => -$amount,
                        'credit' => $amount,
                        'balance' => (float)($postwallet_in['balance'] ?? 0),
                        'created_by' => $postdata['updated_by'],
                        'date_created' => date("Y-m-d H:i:s")
                    ];
                    $transaction->insert($reverseTransactionIn);
                }

			//remove assigned if status is unassigned
			if (($postdata['transaction_status'] ?? '') === "Unassigned") {                
				$postdata['assigned'] = '';
			}

			unset($postdata['id']);
			$sim->update($row->id,$postdata);

            //log changes in the log table
            $action                      = 'edited';
            $serial_number         = $postdata['serial_number'] ?? '';
            $assinged_number    = $postdata['number_assigned'] ?? '';
            $transaction_id          = $row->id;
            $status                       = $postdata['transaction_status'] ?? '';
            $transactionDesc              = ($postdata['network'] ?? ($row->network ?? '')) . "  sim  with Serial Number:". $serial_number;
            $table                        = 'sims';
            $description              = "$username $action a new $transactionDesc";
			$date_created 	= date("Y-m-d H:i:s");

		    $log = new \MyWallets\Log;
		    $log_activity = $log->insert([
			 	'table_name'=>$table,
			 	'action'=>$action,
			 	'user_id'=>$user_id,
			 	'user_name'=>$username,
			 	'description'=>$description,
			 	'date_created'=>$date_created,
			 ]);

			message_success("Sim with serial_number:$serial_number edited successfully!");
			redirect($admin_route.'/'.$plugin_route);
		
		}
	}

	if(!$csrf)
		$sim->errors['usn'] = "Form expired!";

	set_value('errors',array_merge($sim->errors,$req->upload_errors));

}else{

	message_fail("Record not found");
}

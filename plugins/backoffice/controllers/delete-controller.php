<?php

if(!empty($row))
{
	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$session = get_session();
	$currentUserId = (int)($session->id ?? 0);
	$rowOwnerId = (int)($row->created_by ?? 0);
	$rowAssignedId = (int)($row->assigned ?? 0);
	$statusLower = strtolower((string)($row->transaction_status ?? ''));
	$canDelete = user_can('delete_request') || ($currentUserId > 0 && ($rowOwnerId === $currentUserId || $rowAssignedId === $currentUserId));
	if (!$canDelete) {
		message_fail('Access denied');
		redirect($admin_route.'/'.$plugin_route);
	}
	if (!in_array($statusLower, ['pending','draft'], true)) {
		message_fail('Only pending/draft requests can be deleted');
		redirect($admin_route.'/'.$plugin_route);
	}
	
	$postdata = $req->post();

	$csrf = csrf_verify($postdata);
	if($csrf)
	{
		if(!empty($session->id)){
			$username = $session->first_name . ' '. $session->last_name;
			$user_id = (int)$session->id;
		}else{
			$username = 'Unknown user';
			$user_id = 0;
		}

		$action = 'deleted';
		$table = 'requests';
		$network = trim((string)($row->network ?? ''));
		$type = trim((string)($row->request_type ?? ''));
		$amount = (float)($row->amount ?? 0);
		$transactionLabel = 'Request' . ($network !== '' ? (': ' . $network) : '') . ($type !== '' ? (' ' . $type) : '') . ', amount: ' . number_format($amount, 2) . ', status: ' . (string)($row->transaction_status ?? '');
		$transaction_id = $row->id;
		$description = "$username $action $transactionLabel with id $transaction_id from $table";
		$date_deleted = date("Y-m-d H:i:s");

		$log = new \BackOffices\Log;
		$log->insert([
			'table_name'=>$table,
			'action'=>$action,
			'user_id'=>$user_id,
			'user_name'=>$username,
			'description'=>$description,
			'date_deleted'=>$date_deleted,
		]);

		$requestModel = new \BackOffices\RequestClass;
		$requestModel->delete($row->id);

		message_success("Record deleted successfully!");
		redirect($admin_route.'/'.$plugin_route);
	}

	$tmpRequest = new \BackOffices\RequestClass;
	$tmpRequest->errors['transaction'] = "Form expired!";
	set_value('errors',$tmpRequest->errors);
}else{

	message_fail("Record not found");
}

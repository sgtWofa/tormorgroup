<?php

if (!empty($row))
{
	date_default_timezone_set('Africa/Accra');

	$session = get_session();
	$currentUserId = (int)($session->id ?? 0);
	$rowOwnerId = (int)($row->created_by ?? 0);
	$rowPayToId = (int)($row->pay_to ?? 0);
	$statusLower = strtolower((string)($row->transaction_status ?? ''));

	$canDelete = user_can('delete_payment') || ($currentUserId > 0 && ($rowOwnerId === $currentUserId || $rowPayToId === $currentUserId));
	if (!$canDelete) {
		message_fail('Access denied');
		redirect($admin_route.'/'.$plugin_route);
	}

	if (!in_array($statusLower, ['pending','draft'], true)) {
		message_fail('Only pending/draft payments can be deleted');
		redirect($admin_route.'/'.$plugin_route);
	}

	$postdata = $req->post();
	$csrf = csrf_verify($postdata);
	if ($csrf)
	{
		if (user_can('delete_payment') || ($rowOwnerId > 0 && $rowOwnerId === $currentUserId) || ($rowPayToId > 0 && $rowPayToId === $currentUserId))
		{
			if (!empty($session->id)) {
				$username = $session->first_name . ' ' . $session->last_name;
				$user_id = (int)$session->id;
			} else {
				$username = 'Unknown user';
				$user_id = 0;
			}

			$action = 'deleted';
			$table = 'payments_backoffice';
			$transactionLabel = 'Payment: ' . ($row->description ?? '') . ', status: ' . ($row->transaction_status ?? '');
			$transaction_id = $row->id;
			$description = "$username $action $transactionLabel with id $transaction_id from $table";
			$date_deleted = date("Y-m-d H:i:s");

			$log = new \BackOffices\Log;
			$log->insert([
				'table_name' => $table,
				'action' => $action,
				'user_id' => $user_id,
				'user_name' => $username,
				'description' => $description,
				'date_deleted' => $date_deleted,
			]);

			$payment = new \BackOffices\Payment;
			$payment->delete($row->id);

			message_success("Record deleted successfully!");
			redirect($admin_route.'/'.$plugin_route);
		}
	}

	$tmpPayment = new \BackOffices\Payment;
	$tmpPayment->errors['transaction'] = "Form expired!";
	set_value('errors', $tmpPayment->errors);
}
else
{
	message_fail("Record not found");
}

<?php

if (!empty($row))
{
	date_default_timezone_set('Africa/Accra');

	$session = get_session();
	$currentUserId = (int)($session->id ?? 0);
	$rowOwnerId = (int)($row->created_by ?? 0);
	$rowPayToId = (int)($row->pay_to ?? 0);
	$canDelete = backoffice_commission_can_delete_pending($row, $session);
	if (!$canDelete) {
		message_fail('Access denied');
		redirect($admin_route.'/'.$plugin_route);
	}

	$postdata = $req->post();
	$csrf = csrf_verify($postdata);
	if ($csrf)
	{
		if (backoffice_commission_can_delete_pending($row, $session))
		{
			if (!empty($session->id)) {
				$username = $session->first_name . ' ' . $session->last_name;
				$user_id = (int)$session->id;
			} else {
				$username = 'Unknown user';
				$user_id = 0;
			}

			$action = 'deleted';
			$table = 'commissions';
			$transactionLabel = 'Commission: ' . ($row->description ?? '') . ', status: ' . ($row->transaction_status ?? '');
			$transaction_id = $row->id;
			$description = "$username $action $transactionLabel with id $transaction_id from $table";
			$date_deleted = date("Y-m-d H:i:s");
			$deletedRows = [$row];
			$deletedIds = [(int)$row->id];
			$commission = new \BackOffices\Commission;
			try {
				$childRows = $commission->query(
					"SELECT * FROM commissions WHERE bulk_parent_id = :pid AND (deleted IS NULL OR deleted = 0) ORDER BY id ASC",
					['pid' => (int)$row->id]
				) ?: [];
				foreach ($childRows as $childRow) {
					$deletedRows[] = $childRow;
					$deletedIds[] = (int)($childRow->id ?? 0);
				}
			} catch (\Throwable $e) {
				$childRows = [];
			}

			$reactivatedOverdueEntries = [];
			if (function_exists('backoffice_commission_deleted_reactivation_entries')) {
				$reactivatedOverdueEntries = backoffice_commission_deleted_reactivation_entries($deletedRows, $deletedIds);
			}

			$log = new \BackOffices\Log;
			$log->insert([
				'table_name' => $table,
				'action' => $action,
				'user_id' => $user_id,
				'user_name' => $username,
				'description' => $description,
				'date_created' => $date_deleted,
			]);

			if (!empty($reactivatedOverdueEntries)) {
				foreach ($reactivatedOverdueEntries as $entry) {
					$reactivationDescription = $username . ' deleted commission #' . $transaction_id
						. '; overdue commission reactivated for ' . ($entry['assignment_label'] ?? 'assigned SIM')
						. ' (' . ($entry['commission_label'] ?? 'Commission') . '). Reason: ' . ($entry['reason'] ?? 'Deleted submitted commission no longer counts as a valid submission.');
					$log->insert([
						'table_name' => $table,
						'action' => 'overdue reactivated',
						'user_id' => $user_id,
						'user_name' => $username,
						'description' => $reactivationDescription,
						'date_created' => $date_deleted,
					]);
				}
			}

			if (!empty($childRows)) {
				try {
					$commission->query(
						"UPDATE commissions SET deleted = 1, date_deleted = :date_deleted WHERE bulk_parent_id = :pid",
						['date_deleted' => $date_deleted, 'pid' => (int)$row->id]
					);
				} catch (\Throwable $e) {
					// The parent delete below still removes the submitted bulk record.
				}
			}
			$commission->delete($row->id);

			if (!empty($reactivatedOverdueEntries)) {
				message_success("Record deleted successfully! " . count($reactivatedOverdueEntries) . " overdue commission item(s) were reactivated.");
			} else {
				message_success("Record deleted successfully!");
			}
			redirect($admin_route.'/'.$plugin_route);
		}
	}

	$tmpCommission = new \BackOffices\Commission;
	$tmpCommission->errors['transaction'] = "Form expired!";
	set_value('errors', $tmpCommission->errors);
}
else
{
	message_fail("Record not found");
}

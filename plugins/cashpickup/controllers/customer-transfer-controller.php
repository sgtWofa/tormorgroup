<?php

require_once plugin_path('helpers/customer-ledger.php');

// Agents (view/add cashpickup) AND managers/admins may transfer between customers.
if(!(user_can('view_cashpickup') || user_can('add_cashpickup') || user_can('is_manager') || user_can('is_admin'))){
	message_fail('Access denied');
	redirect($admin_route.'/'.$plugin_route.'/customers');
}

$session = get_session();
$actorId = (int)($session->id ?? 0);
$fromCustomerId = (int)(URL(3) ?? ($req->post('from_customer_id') ?? 0));
$toCustomerId = (int)($req->post('to_customer_id') ?? 0);
$amount = (float)($req->post('amount') ?? 0);
$note = trim((string)($req->post('notes') ?? ''));

if($fromCustomerId <= 0 || $toCustomerId <= 0 || $fromCustomerId === $toCustomerId){
	message_fail('Select two different customers for the transfer.');
	redirect($admin_route.'/'.$plugin_route.'/customer/'.$fromCustomerId);
}
if($amount <= 0){
	message_fail('Enter a valid transfer amount.');
	redirect($admin_route.'/'.$plugin_route.'/customer/'.$fromCustomerId);
}

$csrf = csrf_verify($req->post());
if(!$csrf){
	message_fail('Form expired.');
	redirect($admin_route.'/'.$plugin_route.'/customer/'.$fromCustomerId);
}

try{
	$db = new \Core\Database;
	\CashpickupLedger\ensure_cashpickup_customer_schema($db);
	\CashpickupLedger\ensure_cashpickup_columns($db);
	\CashpickupLedger\ensure_cashpickup_transfer_columns($db);

	$fromRows = $db->query("SELECT * FROM cashpickup_customers WHERE deleted = 0 AND id = :id LIMIT 1", ['id' => $fromCustomerId]) ?: [];
	$toRows = $db->query("SELECT * FROM cashpickup_customers WHERE deleted = 0 AND id = :id LIMIT 1", ['id' => $toCustomerId]) ?: [];
	if(empty($fromRows) || empty($toRows)){
		message_fail('Customer not found.');
		redirect($admin_route.'/'.$plugin_route.'/customer/'.$fromCustomerId);
	}

	// Destination customer must be approved to receive funds.
	if(!\CashpickupLedger\customer_is_approved($toRows[0])){
		message_fail('The destination customer is not approved.');
		redirect($admin_route.'/'.$plugin_route.'/customer/'.$fromCustomerId);
	}

	$balance = \CashpickupLedger\fetch_customer_balance_metrics($db, $fromCustomerId);
	$available = (float)($balance['balance'] ?? 0);
	if(($amount - $available) > 0.00001){
		message_fail('Transfer amount exceeds this customer balance (' . number_format($available, 2) . ').');
		redirect($admin_route.'/'.$plugin_route.'/customer/'.$fromCustomerId);
	}

	$from = $fromRows[0];
	$to = $toRows[0];
	$now = date('Y-m-d H:i:s');
	$pairId = (int)floor(microtime(true) * 1000000);
	$tracking = 'CUST-TRF-' . $pairId;
	$description = 'Customer transfer to ' . trim((string)($to->full_name ?? 'Customer'));
	if($note !== ''){
		$description .= ' - ' . $note;
	}

	$common = [
		'date' => date('Y-m-d'),
		'tracking_id' => $tracking,
		'payment_method' => 'customer_transfer',
		'pickup_charge' => 0,
		'amount_transferred' => $amount,
		'confirmed_by' => trim((string)(($session->first_name ?? '') . ' ' . ($session->last_name ?? ''))),
		'notes' => $note,
		'approval_status' => 'approved',
		'transaction_status' => 'Approved',
		'created_by' => $actorId,
		'updated_by' => null,
		'disabled' => 0,
		'deleted' => 0,
		'date_created' => $now,
		'date_updated' => null,
		'date_deleted' => null,
		'ledger_transfer_from_customer_id' => $fromCustomerId,
		'ledger_transfer_to_customer_id' => $toCustomerId,
		'ledger_transfer_pair_id' => $pairId,
	];

	$debit = $common + [
		'id' => $pairId,
		'transaction_side' => 'pay',
		'source' => 'customer_transfer',
		'account_paid_to' => '',
		'sender_details' => (string)($from->full_name ?? ''),
		'phone_number' => (string)($from->phone_number ?? ''),
		'amount_involved' => $amount,
		'amount_paid_out' => $amount,
		'amount_requested' => $amount,
		'customer_id' => $fromCustomerId,
		'customer_id_type' => (string)($from->id_type ?? ''),
		'customer_id_number' => (string)($from->id_number ?? ''),
		'customer_location' => (string)($from->location ?? ''),
		'ledger_transfer_direction' => 'out',
	];
	$credit = $common + [
		'id' => $pairId + 1,
		'transaction_side' => 'receive',
		'source' => 'customer_transfer',
		'account_paid_to' => '',
		'sender_details' => (string)($to->full_name ?? ''),
		'phone_number' => (string)($to->phone_number ?? ''),
		'amount_involved' => $amount,
		'amount_paid_out' => 0,
		'amount_requested' => 0,
		'customer_id' => $toCustomerId,
		'customer_id_type' => (string)($to->id_type ?? ''),
		'customer_id_number' => (string)($to->id_number ?? ''),
		'customer_location' => (string)($to->location ?? ''),
		'ledger_transfer_direction' => 'in',
		'notes' => 'Customer transfer from ' . trim((string)($from->full_name ?? 'Customer')) . ($note !== '' ? ' - ' . $note : ''),
	];

	$cols = array_keys($debit);
	$sql = "INSERT INTO cashpickups (".implode(',', $cols).") VALUES (:".implode(',:', $cols).")";

	// Insert with a duplicate-PK retry (mirrors add-controller.php pattern).
	$insertedIds = [];
	for ($attempt = 0; $attempt < 4; $attempt++) {
		try {
			$debit['id'] = (int)floor(microtime(true) * 1000000);
			$credit['id'] = $debit['id'] + 1;
			$debit['ledger_transfer_pair_id'] = $debit['id'];
			$credit['ledger_transfer_pair_id'] = $debit['id'];
			$db->query($sql, $debit);
			$insertedIds[] = $debit['id'];
			$db->query($sql, $credit);
			$insertedIds[] = $credit['id'];
			break;
		} catch (\Throwable $insertErr) {
			// Roll back anything already inserted on this attempt.
			foreach ($insertedIds as $rollbackId) {
				try { $db->query("UPDATE cashpickups SET deleted = 1, date_deleted = :now WHERE id = :id", ['now' => $now, 'id' => $rollbackId]); } catch (\Throwable $e2) {}
			}
			$insertedIds = [];
			if ($attempt >= 3) {
				throw $insertErr;
			}
			usleep(80000);
		}
	}

	message_success('Customer funds transferred successfully.');
	redirect($admin_route.'/'.$plugin_route.'/customer/'.$fromCustomerId.'#customer-statement-pane');
}catch(\Throwable $e){
	message_fail('Unable to transfer customer funds.');
	redirect($admin_route.'/'.$plugin_route.'/customer/'.$fromCustomerId);
}

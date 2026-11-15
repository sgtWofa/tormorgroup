<?php

require_once plugin_path('helpers/transfer-allocation.php');

if (!user_can('is_admin')) {
	message_fail('Only an admin can cancel payout assignments.');
	redirect($admin_route . '/' . $plugin_route);
	exit;
}

$postdata = $req->post();
if (!$req->posted() || !csrf_verify($postdata)) {
	message_fail('Form expired or invalid request.');
	redirect($admin_route . '/' . $plugin_route);
	exit;
}

$transferId = (int)(URL(3) ?? 0);
$db = new \Core\Database;
$session = get_session();
$actorId = (int)($session->id ?? 0);
$receiveId = 0;
try {
	if (function_exists('CashpickupTransferAllocation\\ensure_cashpickup_transfer_schema')) {
		\CashpickupTransferAllocation\ensure_cashpickup_transfer_schema($db);
	}
} catch (\Throwable $e) {
	message_fail('Unable to prepare cancellation audit fields.');
	redirect($admin_route . '/' . $plugin_route);
	exit;
}
if ($transferId <= 0 || !$db->beginTransaction()) {
	message_fail('Unable to prepare assignment cancellation.');
	redirect($admin_route . '/' . $plugin_route);
	exit;
}

try {
	$transferRows = $db->query(
		"SELECT id, receive_id, to_user_id, amount, approval_status
		 FROM cashpickup_transfers
		 WHERE id = :id
		 LIMIT 1
		 FOR UPDATE",
		['id' => $transferId]
	);
	if ($db->has_error) {
		throw new \RuntimeException('Unable to load the payout assignment.');
	}
	$transfer = $transferRows[0] ?? null;
	$receiveId = (int)($transfer->receive_id ?? 0);
	$targetUserId = (int)($transfer->to_user_id ?? 0);
	if (!$transfer || $receiveId <= 0 || $targetUserId <= 0) {
		throw new \RuntimeException('Payout assignment not found.');
	}

	$transferStatus = strtolower(trim((string)($transfer->approval_status ?? 'approved')));
	if ($transferStatus !== '' && !in_array($transferStatus, ['approved', 'pending'], true)) {
		throw new \RuntimeException('This payout assignment is no longer active.');
	}

	$allocations = $db->query(
		"SELECT id, amount
		 FROM cashpickup_transfers
		 WHERE receive_id = :receive_id
		 AND to_user_id = :user_id
		 AND (
			approval_status IS NULL
			OR approval_status = ''
			OR LOWER(approval_status) IN ('approved','pending')
		 )
		 ORDER BY date_created ASC, id ASC
		 FOR UPDATE",
		['receive_id' => $receiveId, 'user_id' => $targetUserId]
	);
	if ($db->has_error || !is_array($allocations)) {
		throw new \RuntimeException('Unable to verify the assigned payout balance.');
	}

	$payoutRows = $db->query(
		"SELECT id, amount_paid_out, pickup_charge
		 FROM cashpickups
		 WHERE (deleted IS NULL OR deleted = 0)
		 AND LOWER(transaction_side) = 'pay'
		 AND (
			approval_status IS NULL
			OR approval_status = ''
			OR LOWER(approval_status) IN ('approved','pending')
		 )
		 AND transaction_status = 'Approved'
		 AND confirmed_by IS NOT NULL AND confirmed_by != ''
		 AND COALESCE(amount_paid_out,0) > 0
		 AND receive_id = :receive_id
		 AND (created_by = :user_id OR assigned_user_id = :user_id)
		 ORDER BY id ASC
		 FOR UPDATE",
		['receive_id' => $receiveId, 'user_id' => $targetUserId]
	);
	if ($db->has_error) {
		throw new \RuntimeException('Unable to verify payout history.');
	}
	// An unused assignment normally has no payout rows; query() returns false for empty results.
	$payoutRows = is_array($payoutRows) ? $payoutRows : [];

	$paidToUser = 0.0;
	foreach ($payoutRows as $payout) {
		$paidToUser += max(0.0, (float)($payout->amount_paid_out ?? 0))
			+ max(0.0, (float)($payout->pickup_charge ?? 0));
	}
	$remainingPaid = round($paidToUser, 2);
	$targetConsumed = 0.0;
	$targetFound = false;
	foreach ($allocations as $allocation) {
		$allocationAmount = round(max(0.0, (float)($allocation->amount ?? 0)), 2);
		$consumed = min($remainingPaid, $allocationAmount);
		if ((int)($allocation->id ?? 0) === $transferId) {
			$targetConsumed = $consumed;
			$targetFound = true;
			break;
		}
		$remainingPaid = round(max(0.0, $remainingPaid - $consumed), 2);
	}
	if (!$targetFound) {
		throw new \RuntimeException('The payout assignment is no longer active.');
	}
	if ($targetConsumed > 0.00001) {
		throw new \RuntimeException('This assignment has already been used for a payout and cannot be cancelled.');
	}

	$receiveRows = $db->query(
		"SELECT id, transaction_side
		 FROM cashpickups
		 WHERE id = :id
		 AND (deleted IS NULL OR deleted = 0)
		 LIMIT 1
		 FOR UPDATE",
		['id' => $receiveId]
	);
	if ($db->has_error || empty($receiveRows)
		|| strtolower(trim((string)($receiveRows[0]->transaction_side ?? ''))) !== 'receive') {
		throw new \RuntimeException('The related cashpickup receive transaction could not be found.');
	}

	$db->query(
		"UPDATE cashpickup_transfers
		 SET approval_status = 'cancelled',
		     cancelled_by = :cancelled_by,
		     date_cancelled = :date_cancelled
		 WHERE id = :id
		 AND (
			approval_status IS NULL
			OR approval_status = ''
			OR LOWER(approval_status) IN ('approved','pending')
		 )",
		[
			'id' => $transferId,
			'cancelled_by' => $actorId > 0 ? $actorId : null,
			'date_cancelled' => date('Y-m-d H:i:s'),
		]
	);
	if ($db->has_error || $db->affected_rows !== 1) {
		throw new \RuntimeException('The payout assignment changed before it could be cancelled. Refresh and try again.');
	}

	$activeTotalRows = $db->query(
		"SELECT COALESCE(SUM(amount),0) AS active_total
		 FROM cashpickup_transfers
		 WHERE receive_id = :receive_id
		 AND (
			approval_status IS NULL
			OR approval_status = ''
			OR LOWER(approval_status) IN ('approved','pending')
		 )",
		['receive_id' => $receiveId]
	);
	if ($db->has_error) {
		throw new \RuntimeException('Unable to recalculate active assignment totals.');
	}
	$latestAssigneeRows = $db->query(
		"SELECT to_user_id
		 FROM cashpickup_transfers
		 WHERE receive_id = :receive_id
		 AND to_user_id IS NOT NULL AND to_user_id > 0
		 AND (
			approval_status IS NULL
			OR approval_status = ''
			OR LOWER(approval_status) IN ('approved','pending')
		 )
		 ORDER BY id DESC
		 LIMIT 1",
		['receive_id' => $receiveId]
	);
	if ($db->has_error) {
		throw new \RuntimeException('Unable to recalculate active assignments.');
	}

	$activeTotal = round(max(0.0, (float)($activeTotalRows[0]->active_total ?? 0)), 2);
	$latestAssigneeId = (int)($latestAssigneeRows[0]->to_user_id ?? 0);
	$db->query(
		"UPDATE cashpickups
		 SET assigned_user_id = :assigned_user_id,
		     amount_transferred = :amount_transferred,
		     updated_by = :updated_by,
		     date_updated = :date_updated
		 WHERE id = :id",
		[
			'assigned_user_id' => $latestAssigneeId > 0 ? $latestAssigneeId : null,
			'amount_transferred' => $activeTotal,
			'updated_by' => (int)($session->id ?? 0),
			'date_updated' => date('Y-m-d H:i:s'),
			'id' => $receiveId,
		]
	);
	if ($db->has_error || $db->affected_rows !== 1) {
		throw new \RuntimeException('Unable to update the cashpickup assignment summary.');
	}
	if (!$db->commit()) {
		throw new \RuntimeException('Unable to complete assignment cancellation.');
	}

	message_success('Payout assignment cancelled. The cashpickup deposit remains unchanged.');
	redirect($admin_route . '/' . $plugin_route . '/view/' . $receiveId . '#transfer');
	exit;
} catch (\Throwable $e) {
	$db->rollBack();
	message_fail('Assignment cancellation failed: ' . $e->getMessage());
	redirect($admin_route . '/' . $plugin_route . ($receiveId > 0 ? '/view/' . $receiveId . '#transfer' : ''));
	exit;
}

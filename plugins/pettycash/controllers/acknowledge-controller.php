<?php

$id = (int)(URL(3) ?? 0);
$row = pettycash_find_row($id);
$postdata = $req->post();
$csrf = csrf_verify($postdata);

if (!$row) {
	message_fail('Petty cash transaction not found.');
	redirect($admin_route . '/' . $plugin_route);
	exit;
}

$session = get_session();
$userId = (int)($session->id ?? 0);

if (!$csrf) {
	message_fail('Form expired. Please try again.');
	redirect($admin_route . '/' . $plugin_route . '/view/' . $row->id);
	exit;
}

if (!pettycash_user_can_access_row($row, $userId, $session) || pettycash_user_is_privileged($session) || !user_can('acknowledge_pettycash')) {
	message_fail('Access denied.');
	redirect($admin_route . '/' . $plugin_route . '/view/' . $row->id);
	exit;
}

if (strtolower((string)($row->status ?? 'pending_ack')) !== 'pending_ack') {
	message_fail('This petty cash transaction has already been acknowledged.');
	redirect($admin_route . '/' . $plugin_route . '/view/' . $row->id);
	exit;
}

$currency = strtoupper(trim((string)($row->currency ?? 'GHS')));
if (!in_array($currency, ['GHS', 'CFA'], true)) {
	$currency = 'GHS';
}

$budgetAmount = (float)($row->budget_amount ?? 0);
$rawDenos = $postdata['paid_denos_json'] ?? ($postdata['denos_json'] ?? '[]');
$denosPayload = \BackOfficeCashdrawer\normalizeDenominations($rawDenos, $budgetAmount, $currency);
$denosTotal = \BackOfficeCashdrawer\calculateTotal($denosPayload);

if (empty($denosPayload)) {
	message_fail('Enter the denominations used to make the petty cash payment.');
	redirect($admin_route . '/' . $plugin_route . '/view/' . $row->id);
	exit;
}

if ($budgetAmount <= 0 || abs($denosTotal - $budgetAmount) > 0.01) {
	message_fail('Denomination calculator total must exactly match the assigned petty cash amount.');
	redirect($admin_route . '/' . $plugin_route . '/view/' . $row->id);
	exit;
}

$drawer = \BackOfficeCashdrawer\getActiveDrawer($userId);
if (empty($drawer)) {
	message_fail('Open your cashdrawer before acknowledging this petty cash transaction.');
	redirect($admin_route . '/' . $plugin_route . '/view/' . $row->id);
	exit;
}

$drawerUserId = (int)($drawer['drawer_user_id'] ?? $userId);
$balanceCheck = \BackOfficeCashdrawer\checkCashDenominationSufficiencyCurrency($drawerUserId, $denosPayload, $budgetAmount, $currency);
if (empty($balanceCheck['ok'])) {
	$required = pettycash_format_amount((float)($balanceCheck['required'] ?? $budgetAmount), $currency);
	$available = pettycash_format_amount((float)($balanceCheck['available'] ?? 0), $currency);
	message_fail("Insufficient cashdrawer balance. Required: {$required}, available: {$available}.");
	redirect($admin_route . '/' . $plugin_route . '/view/' . $row->id);
	exit;
}

$countCheck = \BackOfficeCashdrawer\checkCashDenominationCountSufficiencyCurrency($drawerUserId, $denosPayload, $budgetAmount, $currency);
if (empty($countCheck['ok'])) {
	$parts = [];
	foreach (($countCheck['shortages'] ?? []) as $shortage) {
		$parts[] = ($shortage['denomination'] ?? '') . ' x' . (int)($shortage['required_count'] ?? 0) . ' (available ' . (int)($shortage['available_count'] ?? 0) . ')';
	}
	$message = 'Insufficient denomination counts in your cashdrawer.';
	if (!empty($parts)) {
		$message .= ' Shortages: ' . implode(', ', $parts) . '.';
	}
	message_fail($message);
	redirect($admin_route . '/' . $plugin_route . '/view/' . $row->id);
	exit;
}

$syncResult = \BackOfficeCashdrawer\syncCashMovementCurrency($userId, $denosPayload, $budgetAmount, 'Acknowledged', (int)$row->id, 'out', $currency);
if ($syncResult === null) {
	message_fail('Unable to deduct the petty cash denominations from your cashdrawer.');
	redirect($admin_route . '/' . $plugin_route . '/view/' . $row->id);
	exit;
}

$pettycash = new \PettyCashes\PettyCash;
$pettycash->update($row->id, [
	'status' => 'acknowledged',
	'paid_denos_json' => json_encode($denosPayload, JSON_UNESCAPED_SLASHES),
	'cashdrawer_id' => $syncResult['drawer_id'] ?? null,
	'drawer_user_id' => $syncResult['drawer_user_id'] ?? $drawerUserId,
	'acknowledged_by' => $userId,
	'acknowledged_at' => date('Y-m-d H:i:s'),
	'updated_by' => $userId,
	'date_updated' => date('Y-m-d H:i:s'),
]);

message_success('Petty cash payment acknowledged successfully.');
redirect($admin_route . '/' . $plugin_route . '/view/' . $row->id);
exit;

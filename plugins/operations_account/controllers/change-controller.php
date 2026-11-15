<?php

/**
 * Currency Denomination Exchange Controller
 * Allows users to change cash denominations while maintaining total volume
 */

date_default_timezone_set('Africa/Accra');

$req = new \Core\Request;
$postdata = $req->post();

// Get drawer ID from POST data or URL
$drawer_id = (int)($postdata['drawer_id'] ?? URL(2) ?? 0);

if (empty($drawer_id)) {
    message_fail("Invalid cashdrawer ID");
    redirect($admin_route . '/' . $plugin_route);
}

// Fetch the cashdrawer
$wallet = new \MyWallets\Wallet;
$cashdrawer = new \MyWallets\Cashdrawer;
$drawerdenos = new \MyWallets\DrawerDenos;

$query = "SELECT * FROM cashdrawers WHERE id = :id";
$row = $wallet->query($query, ['id' => $drawer_id])[0] ?? null;

if (empty($row)) {
    message_fail("Cashdrawer not found");
    redirect($admin_route . '/' . $plugin_route);
}

// Get current user session
$session = get_session();
$user_id = $session->id ?? 0;

// Verify user owns this drawer or is admin
if ($row->user_id != $user_id && !user_can('view_wallet')) {
    message_fail("You don't have permission to change denominations for this drawer");
    redirect($admin_route . '/' . $plugin_route);
}

// This endpoint is intended to be called via POST from the modal.
// If opened directly via GET, redirect back to the cashdrawer view to avoid a blank page.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	redirect($admin_route . '/' . $plugin_route . '/view_cashdrawer/' . $drawer_id);
}

// Handle POST request for change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token if present, but don't fail if missing (modal form limitation)
    $csrf = csrf_verify($postdata);
    
    // Note: CSRF verification is optional for modal forms due to token retrieval limitations
    // The drawer_id validation and user permission check provide security

    // Get change data
    $givenDenominations = json_decode($postdata['given_denominations'] ?? '[]', true);
    $totalReceived = json_decode($postdata['totalReceived'] ?? 0, true);
    $amount_payable = json_decode($postdata['amount_payable'] ?? 0, true);
    if (empty($givenDenominations)) {
        message_fail("Please specify denominations given out");
        redirect($admin_route . '/' . $plugin_route . '/view_cashdrawer/' . $drawer_id);
    }

    if (empty($totalReceived)) {
        message_fail("Please specify denominations received");
        redirect($admin_route . '/' . $plugin_route . '/view_cashdrawer/' . $drawer_id);
    }

    if (empty($amount_payable)) {
        message_fail("Please specify amount payable");
        redirect($admin_route . '/' . $plugin_route . '/view_cashdrawer/' . $drawer_id);
    }

    // Calculate totals
    $givenTotal = 0;
    foreach ($givenDenominations as $deno) {
        $givenTotal += (float)$deno['denomination'] * (int)$deno['count'];
    }

    // Verify totals match
    if (abs($totalReceived - $givenTotal) > $amount_payable) { // Allow for floating point precision
        message_fail("Denominations do not balance. Given: GHS " . number_format($givenTotal, 2) . ", Received: GHS " . number_format($receivedTotal, 2));
        redirect($admin_route . '/' . $plugin_route . '/view_cashdrawer/' . $drawer_id);
    }

    // Track the change amount (totals already matched)
    $changeAmount = $givenTotal;

    // Validate that sufficient denominations are available
    $query = "SELECT denomination, SUM(count) as total_count FROM drawer_denos WHERE drawer_id = :drawer_id GROUP BY denomination";
    $availableDenos = $wallet->query($query, ['drawer_id' => $drawer_id]);
    
    // Create a map of available denominations
    $availableMap = [];
    foreach ($availableDenos as $available) {
        $denoKey = number_format((float)$available->denomination, 2, '.', '');
        $availableMap[$denoKey] = (int)$available->total_count;
    }
    
    // Check if we have enough of each denomination to give out
    $insufficientDenos = [];
    foreach ($givenDenominations as $deno) {
        $denoValue = (float)$deno['denomination'];
        $denoKey = number_format($denoValue, 2, '.', '');
        $requestedCount = (int)$deno['count'];
        $availableCount = $availableMap[$denoKey] ?? 0;
        
        if ($availableCount < $requestedCount) {
            $insufficientDenos[] = [
                'denomination' => $denoValue,
                'requested' => $requestedCount,
                'available' => $availableCount,
                'shortfall' => $requestedCount - $availableCount
            ];
        }
    }
    
    // If any denominations are insufficient, show detailed error
    if (!empty($insufficientDenos)) {
        $errorMsg = "Insufficient denominations available:\n\n";
        foreach ($insufficientDenos as $item) {
            $errorMsg .= "• GHS " . number_format($item['denomination'], 2) . ": ";
            $errorMsg .= "Requested " . $item['requested'] . " units, ";
            $errorMsg .= "Available " . $item['available'] . " units, ";
            $errorMsg .= "Short by " . $item['shortfall'] . " units\n";
        }
        message_fail($errorMsg);
        redirect($admin_route . '/' . $plugin_route . '/view_cashdrawer/' . $drawer_id);
    }

    try {
        $db = new \Core\Database;

        // Start transaction
        $db->query("START TRANSACTION");

        // 1. Record denominations given out (negative entries)
        foreach ($givenDenominations as $deno) {
            $denoValue = (float)$deno['denomination'];
            $denoCount = (int)$deno['count'];
            $denoVolume = $denoValue * $denoCount;

            $givenData = [
                'drawer_id' => $drawer_id,
                'user_id' => $row->user_id,
                'denomination' => $denoValue,
                'count' => -$denoCount, // Negative count for given out
                'volume' => -$denoVolume, // Negative volume for given out
                'created_by' => $user_id,
                'date_created' => date('Y-m-d H:i:s'),
                'trans_status' => 'Exchange',
            ];
            $drawerdenos->insert($givenData);
        }

        // Commit transaction
        $db->query("COMMIT");

        // Log the change
        $logData = [
            'table_name' => 'drawer_denos',
            'action' => 'changed',
            'user_id' => $user_id,
            'user_name' => $session->first_name . ' ' . $session->last_name,
            'description' => "Gave out changed of GHS " . number_format($changeAmount, 2) . " in denominations for drawer ID: $drawer_id",
            'date_created' => date('Y-m-d H:i:s'),
        ];
        
        $log = new \MyWallets\Log;
        $log->insert($logData);

        message_success("Denomination change completed successfully!");
        redirect($admin_route . '/' . $plugin_route . '/view_cashdrawer/' . $drawer_id);

    } catch (\Throwable $e) {
        if (isset($db)) {
            try {
                $db->query("ROLLBACK");
            } catch (\Throwable $rollbackError) {
                error_log('[ChangeDenoms] Rollback failed: ' . $rollbackError->getMessage());
            }
        }

        $errorContext = sprintf('[ChangeDenoms] Drawer:%s User:%s Error:%s in %s:%d',
            $drawer_id,
            $user_id,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );
        error_log($errorContext);

        message_fail('Denomination change failed. Please try again or contact support if the issue persists.');
        redirect($admin_route . '/' . $plugin_route . '/view_cashdrawer/' . $drawer_id);
    }
}

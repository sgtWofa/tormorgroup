<?php

/**
 * Get Drawer Denominations API
 * Returns available denominations for a specific cashdrawer
 */

date_default_timezone_set('Africa/Accra');

$req = new \Core\Request;

// Get drawer ID from URL
$drawer_id = (int)(URL(3) ?? URL(2) ?? 0);
$currency = strtoupper(trim((string)($_GET['currency'] ?? 'GHS')));
if (!in_array($currency, ['GHS', 'CFA'], true)) {
    $currency = 'GHS';
}

if (empty($drawer_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid drawer ID']);
    exit;
}

// Fetch the cashdrawer
$wallet = new \MyWallets\Wallet;

$query = "SELECT * FROM cashdrawers WHERE id = :id";
$row = $wallet->query($query, ['id' => $drawer_id])[0] ?? null;

if (empty($row)) {
    echo json_encode(['success' => false, 'message' => 'Cashdrawer not found']);
    exit;
}

// Get current user session
$session = get_session();
$user_id = $session->id ?? 0;

// Verify user owns this drawer or is admin
if ($row->user_id != $user_id && !user_can('view_wallet')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

$db = new \Core\Database;
if ($currency === 'CFA') {
    $cfaHelper = dirname(__DIR__, 2) . '/cfa/helpers/cashdrawer-sync.php';
    if (is_file($cfaHelper)) {
        require_once $cfaHelper;
    }
    if (function_exists('Cfas\\cfa_ensure_fx_table')) {
        \Cfas\cfa_ensure_fx_table();
    }
    if (!$db->table_exists(['drawer_fx_denos'])) {
        echo json_encode(['success' => true, 'currency' => $currency, 'denominations' => []]);
        exit;
    }
    $query = "SELECT denomination, SUM(count) as total_count, SUM(volume) as total_volume
              FROM drawer_fx_denos
              WHERE drawer_id = :drawer_id AND user_id = :user_id AND currency = 'CFA'
              GROUP BY denomination
              ORDER BY denomination DESC";
    $denominations = $wallet->query($query, ['drawer_id' => $drawer_id, 'user_id' => $row->user_id]);
} else {
    // Fetch available denominations
    $query = "SELECT denomination, SUM(count) as total_count, SUM(volume) as total_volume
              FROM drawer_denos
              WHERE drawer_id = :drawer_id
              GROUP BY denomination
              ORDER BY denomination DESC";
    $denominations = $wallet->query($query, ['drawer_id' => $drawer_id]);
}

if (function_exists('operations_account_normalize_denomination_rows')) {
    $denominations = operations_account_normalize_denomination_rows($denominations, $currency);
}

// Format response
$denoList = [];
foreach ($denominations as $deno) {
    $count = (int)($deno->count ?? $deno->total_count ?? 0);
    $volume = (float)($deno->volume ?? $deno->total_volume ?? 0);
    $denoList[] = [
        'denomination' => (float)($deno->denomination ?? 0),
        'total_count' => $count,
        'total_volume' => $volume
    ];
}

echo json_encode([
    'success' => true,
    'currency' => $currency,
    'denominations' => $denoList
]);
exit;

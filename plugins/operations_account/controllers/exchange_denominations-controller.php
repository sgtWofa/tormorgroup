<?php

/**
 * Currency Denomination Exchange Controller
 * Allows users to exchange cash denominations while maintaining total volume.
 */

date_default_timezone_set('Africa/Accra');

$req = new \Core\Request;
$postdata = $req->post();
$isAjax = (
    strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
    || strtolower((string)($postdata['ajax'] ?? '')) === '1'
);

$respond = function (bool $success, string $message, array $extra = []) use ($isAjax, $admin_route, $plugin_route, &$drawer_id): void {
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge([
            'success' => $success,
            'message' => $message,
            'drawer_id' => (int)($drawer_id ?? 0),
        ], $extra));
        exit;
    }

    if ($success) {
        message_success($message);
    } else {
        message_fail($message);
    }
    redirect($admin_route . '/' . $plugin_route . '/view_cashdrawer/' . (int)($drawer_id ?? 0));
    exit;
};

$drawer_id = (int)($postdata['drawer_id'] ?? URL(3) ?? URL(2) ?? 0);
$currency = strtoupper(trim((string)($postdata['currency'] ?? 'GHS')));
if (!in_array($currency, ['GHS', 'CFA'], true)) {
    $currency = 'GHS';
}
$currencyLabel = $currency;

if (empty($drawer_id)) {
    $respond(false, 'Invalid cashdrawer ID');
}

$wallet = new \MyWallets\Wallet;
$drawerdenos = new \MyWallets\DrawerDenos;
$db = new \Core\Database;

if ($currency === 'CFA') {
    $cfaHelper = dirname(__DIR__, 2) . '/cfa/helpers/cashdrawer-sync.php';
    if (is_file($cfaHelper)) {
        require_once $cfaHelper;
    }
    if (function_exists('Cfas\\cfa_ensure_fx_table')) {
        \Cfas\cfa_ensure_fx_table();
    }
}

$query = "SELECT * FROM cashdrawers WHERE id = :id";
$row = $wallet->query($query, ['id' => $drawer_id])[0] ?? null;

if (empty($row)) {
    $respond(false, 'Cashdrawer not found');
}

$session = get_session();
$user_id = $session->id ?? 0;

if ($row->user_id != $user_id && !user_can('view_wallet')) {
    $respond(false, "You don't have permission to exchange denominations for this drawer");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $respond(false, 'Invalid request method.');
}

if ($currency === 'CFA' && !$db->table_exists(['drawer_fx_denos'])) {
    $respond(false, 'CFA denomination storage is not available. Please contact admin.');
}

$csrf = csrf_verify($postdata);

$givenDenominations = json_decode($postdata['given_denominations'] ?? '[]', true);
$receivedDenominations = json_decode($postdata['received_denominations'] ?? '[]', true);

if (empty($givenDenominations)) {
    $respond(false, 'Please specify denominations given out.');
}

if (empty($receivedDenominations)) {
    $respond(false, 'Please specify denominations received.');
}

$normalizeDenominations = static function (array $rows): array {
    $out = [];
    foreach ($rows as $deno) {
        $denomination = (float)($deno['denomination'] ?? 0);
        $count = (int)($deno['count'] ?? 0);
        if ($denomination <= 0 || $count <= 0) {
            continue;
        }
        $out[] = [
            'denomination' => $denomination,
            'count' => $count,
            'volume' => $denomination * $count,
        ];
    }
    return $out;
};

$givenDenominations = $normalizeDenominations(is_array($givenDenominations) ? $givenDenominations : []);
$receivedDenominations = $normalizeDenominations(is_array($receivedDenominations) ? $receivedDenominations : []);

if (empty($givenDenominations) || empty($receivedDenominations)) {
    $respond(false, 'Please enter valid denominations for both given and received.');
}

$sumDenominations = static function (array $rows): float {
    $total = 0.0;
    foreach ($rows as $deno) {
        $total += (float)($deno['volume'] ?? 0);
    }
    return $total;
};

$givenTotal = $sumDenominations($givenDenominations);
$receivedTotal = $sumDenominations($receivedDenominations);

if (abs($givenTotal - $receivedTotal) > 0.01) {
    $respond(false, "Denominations do not balance. Given: {$currencyLabel} " . number_format($givenTotal, 2) . ", Received: {$currencyLabel} " . number_format($receivedTotal, 2));
}

if ($currency === 'CFA') {
    $availableDenos = $wallet->query(
        "SELECT denomination, SUM(count) as total_count
         FROM drawer_fx_denos
         WHERE drawer_id = :drawer_id AND user_id = :user_id AND currency = 'CFA'
         GROUP BY denomination",
        ['drawer_id' => $drawer_id, 'user_id' => $row->user_id]
    );
} else {
    $availableDenos = $wallet->query(
        "SELECT denomination, SUM(count) as total_count
         FROM drawer_denos
         WHERE drawer_id = :drawer_id
         GROUP BY denomination",
        ['drawer_id' => $drawer_id]
    );
}

$availableMap = [];
foreach (($availableDenos ?: []) as $available) {
    $denoKey = number_format((float)$available->denomination, 2, '.', '');
    $availableMap[$denoKey] = (int)$available->total_count;
}

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
            'shortfall' => $requestedCount - $availableCount,
        ];
    }
}

if (!empty($insufficientDenos)) {
    $errorMsg = "Insufficient denominations available:\n\n";
    foreach ($insufficientDenos as $item) {
        $errorMsg .= "- {$currencyLabel} " . number_format($item['denomination'], 2) . ": ";
        $errorMsg .= "Requested " . $item['requested'] . " units, ";
        $errorMsg .= "Available " . $item['available'] . " units, ";
        $errorMsg .= "Short by " . $item['shortfall'] . " units\n";
    }
    $respond(false, trim($errorMsg));
}

try {
    $db->query("START TRANSACTION");
    $transId = time();

    $insertDeno = static function (array $deno, int $sign) use ($currency, $drawer_id, $row, $user_id, $drawerdenos, $db, $transId): void {
        $denoValue = (float)$deno['denomination'];
        $denoCount = (int)$deno['count'];
        $denoVolume = $denoValue * $denoCount;
        $now = date('Y-m-d H:i:s');

        if ($currency === 'CFA') {
            $fxSchema = $db->query("SHOW COLUMNS FROM drawer_fx_denos") ?: [];
            $fxCols = [];
            foreach ($fxSchema as $col) {
                $fxCols[strtolower((string)($col->Field ?? ''))] = true;
            }

            $fields = "drawer_id, user_id, currency, denomination, count, volume, created_by, date_created, trans_status";
            $values = ":drawer_id, :user_id, 'CFA', :denomination, :count, :volume, :created_by, :date_created, 'Exchange'";
            $params = [
                'drawer_id' => $drawer_id,
                'user_id' => $row->user_id,
                'denomination' => $denoValue,
                'count' => $sign * $denoCount,
                'volume' => $sign * $denoVolume,
                'created_by' => $user_id,
                'date_created' => $now,
            ];
            if (!empty($fxCols['trans_id'])) {
                $fields .= ", trans_id";
                $values .= ", :trans_id";
                $params['trans_id'] = $transId;
            }
            $db->query("INSERT INTO drawer_fx_denos ({$fields}) VALUES ({$values})", $params);
            return;
        }

        $drawerdenos->insert([
            'drawer_id' => $drawer_id,
            'user_id' => $row->user_id,
            'denomination' => $denoValue,
            'count' => $sign * $denoCount,
            'volume' => $sign * $denoVolume,
            'created_by' => $user_id,
            'date_created' => $now,
            'trans_status' => 'Exchange',
            'trans_id' => $transId,
        ]);
    };

    foreach ($givenDenominations as $deno) {
        $insertDeno($deno, -1);
    }
    foreach ($receivedDenominations as $deno) {
        $insertDeno($deno, 1);
    }

    $db->query("COMMIT");

    $log = new \MyWallets\Log;
    $actorName = trim((string)(($session->first_name ?? '') . ' ' . ($session->last_name ?? '')));
    if ($actorName === '') {
        $actorName = trim((string)(($session->name ?? '') ?: ($session->username ?? '')));
    }
    $givenSummary = implode(', ', array_map(static function (array $deno) use ($currencyLabel): string {
        return $currencyLabel . ' ' . number_format((float)$deno['denomination'], 2) . ' x ' . (int)$deno['count'];
    }, $givenDenominations));
    $receivedSummary = implode(', ', array_map(static function (array $deno) use ($currencyLabel): string {
        return $currencyLabel . ' ' . number_format((float)$deno['denomination'], 2) . ' x ' . (int)$deno['count'];
    }, $receivedDenominations));
    $log->insert([
        'table_name' => $currency === 'CFA' ? 'drawer_fx_denos' : 'drawer_denos',
        'action' => 'exchanged',
        'user_id' => $user_id,
        'user_name' => $actorName,
        'description' => "Denomination exchange on cashdrawer #{$drawer_id}: gave {$givenSummary}; received {$receivedSummary}; total {$currencyLabel} " . number_format($givenTotal, 2) . ($actorName !== '' ? "; by {$actorName}" : ''),
        'date_created' => date('Y-m-d H:i:s'),
    ]);

    $respond(true, 'Denomination exchange completed successfully.', ['trans_id' => $transId]);
} catch (\Throwable $e) {
    try {
        $db->query("ROLLBACK");
    } catch (\Throwable $rollbackError) {
        error_log('[ExchangeDenoms] Rollback failed: ' . $rollbackError->getMessage());
    }

    error_log(sprintf(
        '[ExchangeDenoms] Drawer:%s Currency:%s User:%s Error:%s in %s:%d',
        $drawer_id,
        $currency,
        $user_id,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));

    $respond(false, 'Denomination exchange failed. Please try again or contact support if the issue persists.');
}

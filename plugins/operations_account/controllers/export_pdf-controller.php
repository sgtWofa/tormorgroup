<?php
// Standalone PDF Export Controller - No Admin Layout
// This file outputs a clean, professional PDF export without any admin UI

// Prevent any output buffering or layout loading
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Get the cashdrawer ID from URL - check segments 3, 4, and 5
$id = null;
for ($i = 3; $i <= 5; $i++) {
    $segment = URL($i);
    if (is_numeric($segment)) {
        $id = $segment;
        break;
    }
}

if (empty($id)) {
    http_response_code(404);
    echo "Cashdrawer not found";
    exit;
}

// Initialize database models
$wallet = new \MyWallets\Wallet;

// Fetch cashdrawer data
$query = "
    SELECT 
        m.*, 
        u.first_name AS user_first_name, 
        u.last_name AS user_last_name, 
        u.image AS user_image
    FROM cashdrawers AS m 
    LEFT JOIN users AS u ON m.user_id = u.id 
    WHERE m.id = :id LIMIT 1
";

$queryResult = $wallet->query($query, ['id' => $id]);
$row = !empty($queryResult) ? $queryResult[0] : null;

if (empty($row)) {
    http_response_code(404);
    echo "Cashdrawer not found";
    exit;
}

$drawerId = (int)$row->id;
$drawerUserId = (int)($row->user_id ?? 0);
$session = get_session();
$currentUserId = (int)($session->id ?? 0);
$canView = user_can('view_wallet_details') || user_can('view_wallet');
if (!$canView && $currentUserId > 0) {
    $canView = ($drawerUserId === $currentUserId);
}
if (!$canView) {
    http_response_code(403);
    echo "Access denied";
    exit;
}

$db = new \Core\Database;
$denomination_history = [];
$historyOffset = 0;
do {
    $historyPage = operations_account_collect_deno_history($drawerId, $drawerUserId, $historyOffset, 100);
    $historyMovements = $historyPage['movements'] ?? [];
    $denomination_history = array_merge($denomination_history, $historyMovements);
    $fetchedMovements = count($historyMovements);
    $historyOffset += $fetchedMovements;
} while (!empty($historyPage['has_more']) && $fetchedMovements > 0);

$cashdrawer_edit_history = [];
$channel_exchange_history = [];
if ($db->table_exists(['logs'])) {
    $logColumns = [];
    foreach (($db->query('SHOW COLUMNS FROM logs') ?: []) as $column) {
        $logColumns[strtolower((string)($column->Field ?? ''))] = true;
    }

    $userJoin = '';
    $fallbackUserName = "''";
    if (!empty($logColumns['user_id'])) {
        $userJoin = ' LEFT JOIN users AS u ON u.id = l.user_id';
        $fallbackUserName = "TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')))";
    }
    $logUserName = !empty($logColumns['user_name'])
        ? "COALESCE(NULLIF(l.user_name, ''), {$fallbackUserName})"
        : $fallbackUserName;

    $cashdrawer_edit_history = $db->query(
        "SELECT {$logUserName} AS user_name, l.description, l.date_created
         FROM logs AS l{$userJoin}
         WHERE l.table_name = 'cashdrawers'
           AND l.action = 'edited'
           AND l.description LIKE :needle
         ORDER BY l.date_created DESC, l.id DESC",
        ['needle' => '%[cashdrawer_id:' . $drawerId . ']%']
    ) ?: [];

    $channel_exchange_history = $db->query(
        "SELECT {$logUserName} AS exchange_user_name, l.description, l.date_created
         FROM logs AS l{$userJoin}
         WHERE l.table_name = 'cashdrawer_channel_exchange'
           AND (l.description LIKE :needle_legacy OR l.description LIKE :needle_human)
         ORDER BY l.date_created DESC, l.id DESC",
        [
            'needle_legacy' => "%channel_exchange drawer_id={$drawerId}%",
            'needle_human' => "%drawer_id: {$drawerId}%",
        ]
    ) ?: [];
}

$drawerDate = substr($row->date_created ?? date('Y-m-d'), 0, 10);

// Fetch the same currency-specific denomination breakdown shown in the drawer UI.
$query = "SELECT denomination, SUM(`count`) AS count, SUM(volume) AS volume
         FROM drawer_denos
         WHERE drawer_id = :drawer_id AND user_id = :user_id
         GROUP BY denomination
         HAVING ABS(COALESCE(SUM(volume), 0)) > 0.009 OR ABS(COALESCE(SUM(`count`), 0)) > 0
         ORDER BY denomination DESC";

$denominations = $wallet->query($query, [
    'drawer_id' => $drawerId,
    'user_id'   => $row->user_id,
]);

// Legacy fallback
if (empty($denominations)) {
    $query = "SELECT denomination, SUM(`count`) AS count, SUM(volume) AS volume
             FROM drawer_denos
             WHERE user_id = :user_id AND DATE(date_created) = :drawer_date
             GROUP BY denomination
             HAVING ABS(COALESCE(SUM(volume), 0)) > 0.009 OR ABS(COALESCE(SUM(`count`), 0)) > 0
             ORDER BY denomination DESC";

    $denominations = $wallet->query($query, [
        'user_id'     => $row->user_id,
        'drawer_date' => $drawerDate,
    ]);
}

$denominations = array_values(array_filter((array)$denominations, function ($deno): bool {
    if (is_array($deno)) {
        $deno = (object)$deno;
    }
    $count = (float)($deno->count ?? 0);
    $volume = (float)($deno->volume ?? 0);
    return abs($count) > 0 || abs($volume) > 0.009;
}));
$denominations = array_map(static function ($deno) {
    return is_array($deno) ? (object)$deno : $deno;
}, $denominations);

$denomination_total_volume = 0.0;
$denomination_total_count = 0.0;
foreach ($denominations as $deno) {
    $denomination_total_volume += (float)($deno->volume ?? 0);
    $denomination_total_count += (int)($deno->count ?? 0);
}
$total_volume = $denomination_total_volume;
$total_count = $denomination_total_count;

$cfa_denominations = [];
$cfa_total_volume = 0.0;
$cfa_total_count = 0;
if ($db->table_exists(['drawer_fx_denos'])) {
    $cfa_denominations = $wallet->query(
        "SELECT denomination, SUM(`count`) AS count, SUM(volume) AS volume
         FROM drawer_fx_denos
         WHERE drawer_id = :drawer_id AND user_id = :user_id AND currency = 'CFA'
         GROUP BY denomination
         HAVING ABS(COALESCE(SUM(volume), 0)) > 0.009 OR ABS(COALESCE(SUM(`count`), 0)) > 0
         ORDER BY denomination DESC",
        [
            'drawer_id' => $drawerId,
            'user_id' => $drawerUserId,
        ]
    ) ?: [];
}
foreach ($cfa_denominations as $denomination) {
    $cfa_total_volume += (float)($denomination->volume ?? 0);
    $cfa_total_count += (int)($denomination->count ?? 0);
}

// Fetch cash channels
$cash_channels = [];
$openingCash = (float)($row->amount ?? 0);

$inOutQuery = "SELECT 
    SUM(CASE WHEN volume > 0 THEN volume ELSE 0 END) AS total_positive,
    SUM(CASE WHEN volume < 0 THEN -volume ELSE 0 END) AS total_negative
    FROM drawer_denos
    WHERE drawer_id = :drawer_id AND user_id = :user_id";
$inOutRow = $wallet->query($inOutQuery, [
    'drawer_id' => $drawerId,
    'user_id'   => $row->user_id,
])[0] ?? null;

$totalPositive = (float)($inOutRow->total_positive ?? 0);
$totalNegative = (float)($inOutRow->total_negative ?? 0);
$cashIn = max($totalPositive - $openingCash, 0);
$cashOut = $totalNegative;
$closingCash = $openingCash + $cashIn - $cashOut;

$cash_channels[] = (object) [
    'channel_type'    => 'cash',
    'channel_key'     => 'CASH_DRAWER',
    'opening_balance' => $openingCash,
    'credit'          => $cashIn,
    'debit'           => $cashOut,
    'closing_balance' => $closingCash,
];

// Include the latest CFA cash balance, as the drawer UI does.
$ecash_channels = [];
if ($db->table_exists(['drawer_balances'])) {
    try {
        $cfaCashRows = $wallet->query(
            "SELECT channel_type, channel_key, opening_balance, credit, debit, closing_balance
             FROM drawer_balances
             WHERE drawer_id = :drawer_id AND user_id = :user_id
               AND channel_type = 'cash' AND channel_key = 'CASH_CFA'
             ORDER BY id DESC
             LIMIT 1",
            [
                'drawer_id' => $drawerId,
                'user_id' => $drawerUserId,
            ]
        ) ?: [];
        if (!empty($cfaCashRows)) {
            $cashChannels = $cfaCashRows[0];
            $cashChannels->opening_balance = (float)($cashChannels->opening_balance ?? 0);
            $cashChannels->credit = (float)($cashChannels->credit ?? 0);
            $cashChannels->debit = (float)($cashChannels->debit ?? 0);
            $cashChannels->closing_balance = (float)($cashChannels->closing_balance
                ?? ($cashChannels->opening_balance + $cashChannels->credit - $cashChannels->debit));
            $cash_channels[] = $cashChannels;
        }

        $localRows = $wallet->query(
            "SELECT b.channel_type, b.channel_key, b.opening_balance, b.credit, b.debit, b.closing_balance
             FROM drawer_balances AS b
             INNER JOIN (
                 SELECT channel_key, MAX(id) AS max_id
                 FROM drawer_balances
                 WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash'
                 GROUP BY channel_key
             ) AS last_row ON last_row.max_id = b.id",
            [
                'drawer_id' => $drawerId,
                'user_id' => $drawerUserId,
            ]
        ) ?: [];
    } catch (\Throwable $e) {
        error_log('Cashdrawer channel export failed for drawer ' . $drawerId . ': ' . $e->getMessage());
        http_response_code(500);
        echo 'Unable to load cashdrawer channel balances.';
        exit;
    }

    $sharedRoleNames = [
        'admin', 'is_admin', 'manager', 'is_manager', 'general manager', 'general_manager',
        'generalmanager', 'gm', 'is_general_manager', 'operations manager',
        'operations_manager', 'operationsmanager', 'op manager', 'op_manager',
    ];
    $rolesRaw = $session->roles ?? [];
    if (!is_array($rolesRaw)) {
        $rolesRaw = [$rolesRaw];
    }
    $rolesLower = array_map(static function ($role): string {
        return strtolower(trim((string)$role));
    }, $rolesRaw);
    $canSeeBankChannels = user_can('is_admin') || user_can('is_manager')
        || user_can('is_general_manager') || user_can('is_operations_manager');
    foreach ($rolesLower as $role) {
        if ($role === 'gm' || strpos($role, 'manager') !== false) {
            $canSeeBankChannels = true;
            break;
        }
    }

    $bankChannelKeys = [
        'ADB', 'ADB_BANK', 'ABSA', 'ABSA_BANK', 'ABSA_FORMERLY_BARCLAYS',
        'ABSA_FORMERLY_BARCLAYS_BANK', 'BARCLAYS', 'BARCLAYS_BANK', 'FIDELITY',
        'FIDELITY_BANK', 'APEX_NAFANA', 'APEX_NAFANA_BANK', 'APEX_SUMA',
        'APEX_SUMA_BANK', 'ACCESS', 'ACCESS_BANK', 'CAL', 'CAL_BANK', 'ECOBANK',
        'GCB', 'GCB_BANK', 'GT', 'GT_BANK', 'STANBIC', 'STANBIC_BANK',
        'FIRST_NATIONAL_BANK', 'FIRST_NATIONAL_BANK_FNB', 'FNB', 'FNB_BANK',
        'FIRST_BANK_GHANA', 'FIRST_BANK_GHANA_FBG', 'FBG', 'FBG_BANK',
    ];
    $normalizeChannelKey = static function ($key): string {
        $key = strtoupper(trim((string)$key));
        $key = preg_replace('/^ECASH[_-]*/', '', $key);
        $key = str_replace(['_', ' '], '-', (string)$key);
        return trim((string)preg_replace('/-+/', '-', $key), '-');
    };
    $isMoMoPayKey = static function (string $key): bool {
        $key = preg_replace('/^ECASH[_-]*/', '', strtoupper(trim($key)));
        return in_array(preg_replace('/[^A-Z0-9]/', '', (string)$key), ['MTNMOMOPAY', 'MTNMOMOPAYOUT'], true);
    };
    $isBankKey = static function (string $key) use ($bankChannelKeys): bool {
        $key = strtoupper(trim($key));
        $key = preg_replace('/^ECASH[_-]*/', '', $key);
        $key = preg_replace('/[^A-Z0-9]+/', '_', (string)$key);
        return in_array(trim((string)$key, '_'), $bankChannelKeys, true);
    };
    $localSharedChannels = [];
    $localNonSharedChannels = [];
    foreach ($localRows as $channel) {
        $channelKey = $normalizeChannelKey($channel->channel_key ?? '');
        if ($channelKey === '' || in_array($channelKey, ['EZWICH', 'E-ZWICH', 'CASH-DRAWER', 'CASH-CFA'], true)) {
            continue;
        }
        $channel->opening_balance = (float)($channel->opening_balance ?? 0);
        $channel->credit = (float)($channel->credit ?? 0);
        $channel->debit = (float)($channel->debit ?? 0);
        $channel->closing_balance = $channel->opening_balance + $channel->credit - $channel->debit;
        if ($isBankKey($channelKey) || $isMoMoPayKey($channelKey)) {
            if ($canSeeBankChannels || $isMoMoPayKey($channelKey)) {
                $localSharedChannels[] = $channel;
            }
        } else {
            $localNonSharedChannels[] = $channel;
        }
    }

    $ecash_channels = $localNonSharedChannels;
    $roleParams = [];
    $rolePlaceholders = [];
    foreach ($sharedRoleNames as $index => $roleName) {
        $key = 'role' . $index;
        $roleParams[$key] = strtolower($roleName);
        $rolePlaceholders[] = ':' . $key;
    }
    $sharedRows = [];
    if ($db->table_exists(['user_roles_map']) && $db->table_exists(['user_roles'])) {
        try {
            $sharedRows = $wallet->query(
                "SELECT b.channel_type, b.channel_key, b.opening_balance, b.credit, b.debit, b.closing_balance
                 FROM drawer_balances AS b
                 INNER JOIN (
                     SELECT db.channel_key,
                            COALESCE(
                                MAX(CASE WHEN LOWER(COALESCE(db.trans_status, '')) <> 'opening snapshot' THEN db.id END),
                                MAX(db.id)
                            ) AS max_id
                     FROM drawer_balances AS db
                     INNER JOIN cashdrawers AS cd ON cd.id = db.drawer_id
                     LEFT JOIN user_roles_map AS urm ON urm.user_id = cd.user_id AND (urm.disabled = 0 OR urm.disabled IS NULL)
                     LEFT JOIN user_roles AS r ON r.id = urm.role_id AND (r.disabled = 0 OR r.disabled IS NULL)
                     WHERE db.channel_type = 'ecash'
                       AND DATE(cd.date_created) = :today
                       AND (
                           LOWER(r.role) IN (" . implode(',', $rolePlaceholders) . ")
                           OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(db.channel_key)), 'ECASH_', ''), 'ECASH-', ''), '_', ''), '-', ''), ' ', '') IN ('MTNMOMOPAY', 'MTNMOMOPAYOUT')
                       )
                     GROUP BY db.channel_key
                 ) AS last_row ON last_row.max_id = b.id",
                array_merge(['today' => date('Y-m-d')], $roleParams)
            ) ?: [];
        } catch (\Throwable $e) {
            error_log('Cashdrawer shared-channel export failed for drawer ' . $drawerId . ': ' . $e->getMessage());
            http_response_code(500);
            echo 'Unable to load shared cashdrawer channels.';
            exit;
        }
    }
    $sharedChannels = [];
    foreach ($sharedRows as $channel) {
        $channelKey = $normalizeChannelKey($channel->channel_key ?? '');
        if ($channelKey === '' || in_array($channelKey, ['EZWICH', 'E-ZWICH', 'CASH-DRAWER', 'CASH-CFA'], true)) {
            continue;
        }
        $isMoMoPay = $isMoMoPayKey($channelKey);
        if (!$canSeeBankChannels && !$isMoMoPay) {
            continue;
        }
        if (!$isBankKey($channelKey) && !$isMoMoPay) {
            continue;
        }
        $channel->opening_balance = (float)($channel->opening_balance ?? 0);
        $channel->credit = (float)($channel->credit ?? 0);
        $channel->debit = (float)($channel->debit ?? 0);
        $channel->closing_balance = $channel->opening_balance + $channel->credit - $channel->debit;
        $sharedChannels[] = $channel;
    }
    if (!empty($sharedChannels)) {
        $ecash_channels = array_merge($ecash_channels, $sharedChannels);
    } else {
        $ecash_channels = array_merge($ecash_channels, $localSharedChannels);
    }

    $uniqueEcashChannels = [];
    foreach ($ecash_channels as $channel) {
        $channelKey = $normalizeChannelKey($channel->channel_key ?? '');
        if ($channelKey !== '' && !isset($uniqueEcashChannels[$channelKey])) {
            $uniqueEcashChannels[$channelKey] = $channel;
        }
    }
    $ecash_channels = array_values($uniqueEcashChannels);
}

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashdrawer Statement - <?= htmlspecialchars($row->user_first_name . ' ' . $row->user_last_name) ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #253247;
            background: #fff;
            font-family: dejavusans, sans-serif;
            font-size: 9.5pt;
            line-height: 1.45;
        }
        .container { width: 100%; }
        .header {
            margin-bottom: 16px;
            padding: 16px 18px 14px;
            border-bottom: 4px solid #16834a;
            border-radius: 8px;
            background: #15263e;
            color: #fff;
        }
        .brand-table, .header-details {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        .brand-table td {
            padding: 0 0 7px;
            border: 0;
            background-color: #15263e !important;
            color: #d7e1ef;
            font-size: 8pt;
        }
        .brand-table .brand-mark {
            width: 31px;
            padding: 4px 0 !important;
            border-radius: 5px;
            background-color: #16834a !important;
            color: #fff;
            font-size: 9pt;
            font-weight: bold;
            text-align: center;
        }
        .brand-table .brand-name-cell { padding-left: 9px !important; }
        .brand-name {
            display: block;
            color: #fff;
            font-size: 10pt;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .brand-tagline {
            display: block;
            margin-top: 2px;
            color: #b7c7dc;
            font-size: 7pt;
        }
        .brand-meta {
            color: #d7e1ef !important;
            font-size: 7pt !important;
            letter-spacing: .7px;
            text-align: right;
        }
        .header h1 {
            margin: 5px 0 11px;
            color: #fff;
            font-size: 19pt;
            font-weight: bold;
        }
        .header-details td {
            width: 33.33%;
            padding: 7px 9px;
            border: 1px solid #344962;
            background: #1c3452;
            color: #fff;
            text-align: left;
        }
        .detail-label {
            display: block;
            margin-bottom: 2px;
            color: #b7c7dc;
            font-size: 7pt;
            font-weight: bold;
            letter-spacing: .6px;
            text-transform: uppercase;
        }
        .detail-value { font-size: 9pt; font-weight: bold; }
        .section-title {
            margin: 16px 0 7px;
            padding: 7px 9px;
            border-left: 3px solid #2f6fed;
            background: #eef3fb;
            color: #183153;
            font-size: 10pt;
            font-weight: bold;
            letter-spacing: .35px;
        }
        .channel-section { page-break-inside: avoid; }
        .channel-table { page-break-inside: avoid; }
        .channel-table-title {
            padding: 7px 8px;
            border-color: #d5deea;
            border-left: 3px solid #2f6fed;
            background: #eef3fb !important;
            color: #183153 !important;
            font-size: 9pt;
        }
        table {
            width: 100%;
            margin: 0 0 10px;
            border-collapse: collapse;
            background: #fff;
            font-size: 8.5pt;
        }
        th {
            padding: 6px 7px;
            border: 1px solid #d5deea;
            background: #203854;
            color: #fff;
            font-size: 7.5pt;
            font-weight: bold;
            text-align: left;
        }
        td {
            padding: 5px 7px;
            border: 1px solid #e0e6ee;
            background: #fff;
            color: #253247;
            text-align: left;
            vertical-align: top;
        }
        tbody tr:nth-child(even) td { background: #f7f9fc; }
        .denomination-card {
            margin: 0 0 13px;
            border: 1px solid #b9d9c8;
            border-radius: 8px;
            background: #fff;
        }
        .denomination-card--cfa { border-color: #d2c4eb; }
        .denomination-heading {
            width: 100%;
            margin: 0;
            border-collapse: collapse;
        }
        .denomination-heading td {
            padding: 9px 11px;
            border: 0;
            border-bottom: 1px solid #dce8df;
            background: #edf7f0;
        }
        .denomination-card--cfa .denomination-heading td {
            border-bottom-color: #e5dcf2;
            background: #f4effb;
        }
        .denomination-name {
            color: #17643b;
            font-size: 11pt;
            font-weight: bold;
        }
        .denomination-card--cfa .denomination-name { color: #62419b; }
        .denomination-subtitle {
            display: block;
            margin-top: 2px;
            color: #6b7788;
            font-size: 7.5pt;
        }
        .currency-pill {
            width: 58px;
            border: 1px solid #16834a !important;
            border-radius: 12px;
            background: #fff !important;
            color: #16834a !important;
            font-size: 8pt;
            font-weight: bold;
            text-align: center;
        }
        .denomination-card--cfa .currency-pill {
            border-color: #7250b5 !important;
            color: #7250b5 !important;
        }
        .denomination-table { margin-bottom: 0; }
        .denomination-card--ghs .denomination-table th {
            border-color: #cce2d4;
            background: #eaf5ee;
            color: #17643b;
        }
        .denomination-card--cfa .denomination-table th {
            border-color: #e1d7f0;
            background: #f1ebf9;
            color: #62419b;
        }
        .denomination-index {
            color: #69788b;
            font-weight: bold;
        }
        .denomination-value { font-weight: bold; }
        .denomination-count {
            color: #17643b;
            font-weight: bold;
            text-align: right;
        }
        .denomination-card--cfa .denomination-count { color: #62419b; }
        .denomination-volume {
            color: #17643b;
            font-weight: bold;
            text-align: right;
        }
        .denomination-card--cfa .denomination-volume { color: #62419b; }
        .denomination-total td {
            border-top: 2px solid #16834a;
            background: #edf7f0 !important;
            color: #17643b;
            font-weight: bold;
        }
        .denomination-card--cfa .denomination-total td {
            border-top-color: #7250b5;
            background: #f4effb !important;
            color: #62419b;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-success { color: #16834a; }
        .text-danger { color: #c0394b; }
        .footer {
            margin-top: 18px;
            padding-top: 8px;
            border-top: 1px solid #dce3ed;
            color: #7a8798;
            font-size: 7.5pt;
            text-align: center;
        }
        .muted { color: #69788b; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <table class="brand-table">
                <tr>
                    <td class="brand-mark">TG</td>
                    <td class="brand-name-cell">
                        <span class="brand-name"><?= htmlspecialchars(APP_NAME ?? 'TormorGroup') ?></span>
                        <span class="brand-tagline">Cash management</span>
                    </td>
                    <td class="brand-meta">FINANCIAL STATEMENT</td>
                </tr>
            </table>
            <h1><?= strtoupper(htmlspecialchars($row->user_first_name . ' ' . $row->user_last_name)) ?>'s Cash Drawer</h1>
            <table class="header-details">
                <tr>
                    <td><span class="detail-label">Drawer ID</span><br><span class="detail-value"><?= htmlspecialchars((string)$row->id) ?></span></td>
                    <td><span class="detail-label">Created</span><br><span class="detail-value"><?= date('F d, Y', strtotime($row->date_created)) ?></span></td>
                    <td><span class="detail-label">Statement generated</span><br><span class="detail-value"><?= date('F d, Y H:i') ?></span></td>
                </tr>
            </table>
        </div>

        <div class="denomination-card denomination-card--ghs">
            <table class="denomination-heading">
                <tr>
                    <td><span class="denomination-name">GHS denominations</span><span class="denomination-subtitle">Physical cash composition</span></td>
                    <td class="currency-pill">GHS</td>
                </tr>
            </table>
            <table class="denomination-table">
                <thead><tr><th style="width:9%;">#</th><th>DENOMINATION</th><th class="text-right">COUNT</th><th class="text-right">VOLUME</th></tr></thead>
                <tbody>
                    <?php if (!empty($denominations)): $id = 1; ?>
                        <?php foreach ($denominations as $deno): ?>
                            <?php if (is_array($deno)) $deno = (object)$deno; ?>
                            <tr>
                                <td class="denomination-index"><?= $id++ ?></td>
                                <td class="denomination-value">GHS <?= number_format((float)($deno->denomination ?? 0), 2) ?></td>
                                <td class="denomination-count"><?= number_format((float)($deno->count ?? 0), 0) ?></td>
                                <td class="denomination-volume">GHS <?= number_format((float)($deno->volume ?? 0), 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="muted text-center">No GHS denominations recorded.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot><tr class="denomination-total">
                    <td colspan="2">TOTAL GHS</td>
                    <td class="text-right"><?= number_format((float)$total_count, 0) ?></td>
                    <td class="text-right">GHS <?= number_format((float)$total_volume, 2) ?></td>
                </tr></tfoot>
            </table>
        </div>

        <div class="denomination-card denomination-card--cfa">
            <table class="denomination-heading">
                <tr>
                    <td><span class="denomination-name">CFA denominations</span><span class="denomination-subtitle">Physical CFA cash composition</span></td>
                    <td class="currency-pill">CFA</td>
                </tr>
            </table>
            <table class="denomination-table">
                <thead><tr><th style="width:9%;">#</th><th>DENOMINATION</th><th class="text-right">COUNT</th><th class="text-right">VOLUME</th></tr></thead>
                <tbody>
                    <?php if (!empty($cfa_denominations)): $cfaIndex = 1; ?>
                        <?php foreach ($cfa_denominations as $deno): ?>
                            <tr>
                                <td class="denomination-index"><?= $cfaIndex++ ?></td>
                                <td class="denomination-value">CFA <?= number_format((float)($deno->denomination ?? 0), 0) ?></td>
                                <td class="denomination-count"><?= number_format((float)($deno->count ?? 0), 0) ?></td>
                                <td class="denomination-volume">CFA <?= number_format((float)($deno->volume ?? 0), 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="muted text-center">No CFA denominations recorded.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot><tr class="denomination-total">
                    <td colspan="2">TOTAL CFA</td>
                    <td class="text-right"><?= number_format((float)$cfa_total_count, 0) ?></td>
                    <td class="text-right">CFA <?= number_format((float)$cfa_total_volume, 2) ?></td>
                </tr></tfoot>
            </table>
        </div>

        <!-- Cash Channels Section -->
        <?php if (!empty($cash_channels)): ?>
            <div class="channel-section">
            <table class="channel-table">
                <thead>
                    <tr><th colspan="5" class="channel-table-title">CASH CHANNELS</th></tr>
                    <tr>
                        <th style="width: 25%;">Channel</th>
                        <th style="width: 18%;" class="text-right">Opening Balance</th>
                        <th style="width: 18%;" class="text-right">Credit (In)</th>
                        <th style="width: 18%;" class="text-right">Debit (Out)</th>
                        <th style="width: 21%;" class="text-right">Closing Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cash_channels as $ch): ?>
                        <?php
                            $channelKey = strtoupper((string)($ch->channel_key ?? ''));
                            $currencyLabel = $channelKey === 'CASH_CFA' ? 'CFA' : 'GHS';
                            $channelLabel = str_replace('_', ' ', (string)($ch->channel_key ?? ''));
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($channelLabel) ?></td>
                            <td class="text-right"><?= $currencyLabel ?> <?= number_format((float)($ch->opening_balance ?? 0), 2) ?></td>
                            <td class="text-right text-success"><?= $currencyLabel ?> <?= number_format((float)($ch->credit ?? 0), 2) ?></td>
                            <td class="text-right text-danger"><?= $currencyLabel ?> <?= number_format((float)($ch->debit ?? 0), 2) ?></td>
                            <td class="text-right"><strong><?= $currencyLabel ?> <?= number_format((float)($ch->closing_balance ?? 0), 2) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>

        <!-- E-Cash Channels Section -->
        <?php if (!empty($ecash_channels)): ?>
            <div class="channel-section">
            <table class="channel-table">
                <thead>
                    <tr><th colspan="5" class="channel-table-title">E-CASH &amp; SHARED ACCOUNTS</th></tr>
                    <tr>
                        <th style="width: 25%;">Channel</th>
                        <th style="width: 18%;" class="text-right">Opening Balance</th>
                        <th style="width: 18%;" class="text-right">Credit (In)</th>
                        <th style="width: 18%;" class="text-right">Debit (Out)</th>
                        <th style="width: 21%;" class="text-right">Closing Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ecash_channels as $ch): ?>
                        <?php
                            $channelLabel = (string)($ch->channel_key ?? '');
                            $channelLabel = preg_replace('/^ECASH[_-]*/i', '', $channelLabel);
                            $channelLabel = str_replace(['_', '-'], ' ', $channelLabel);
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($channelLabel) ?></td>
                            <td class="text-right">GHS <?= number_format((float)($ch->opening_balance ?? 0), 2) ?></td>
                            <td class="text-right text-success">GHS <?= number_format((float)($ch->credit ?? 0), 2) ?></td>
                            <td class="text-right text-danger">GHS <?= number_format((float)($ch->debit ?? 0), 2) ?></td>
                            <td class="text-right"><strong>GHS <?= number_format((float)($ch->closing_balance ?? 0), 2) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>

        <div class="section-title">DENOMINATION HISTORY</div>
        <?php if (!empty($denomination_history)): ?>
            <?php foreach ($denomination_history as $movement): ?>
                <?php
                    $movementLines = $movement['lines'] ?? [];
                    $movementStatus = (string)($movement['trans_status'] ?? 'Movement');
                    $movementMeta = $movement['meta'] ?? [];
                    $movementDetail = $movement['transaction_detail'] ?? [];
                    $movementType = 'Movement';
                    if (stripos($movementStatus, 'opening') !== false || empty($movement['trans_id'])) {
                        $movementType = 'Opening Snapshot';
                    } elseif (!empty($movementMeta['has_ghs']) && !empty($movementMeta['has_cfa'])) {
                        $movementType = 'FX Exchange';
                    } elseif ((float)($movementMeta['net_ghs'] ?? 0) > 0 || (float)($movementMeta['net_cfa'] ?? 0) > 0) {
                        $movementType = 'Cash In';
                    } elseif ((float)($movementMeta['net_ghs'] ?? 0) < 0 || (float)($movementMeta['net_cfa'] ?? 0) < 0) {
                        $movementType = 'Cash Out';
                    } elseif (strcasecmp($movementStatus, 'Exchange') === 0) {
                        $movementType = 'Denomination Exchange';
                    }
                    $actorName = trim((string)($movement['actor_name'] ?? ''));
                ?>
                <table>
                    <thead>
                        <tr>
                            <th colspan="4">
                                <?= htmlspecialchars($movementType) ?>
                                <?php if (!empty($movement['trans_id'])): ?> · TX #<?= htmlspecialchars((string)$movement['trans_id']) ?><?php endif; ?>
                                · <?= htmlspecialchars((string)($movement['date_created'] ?? '')) ?>
                            </th>
                        </tr>
                        <tr>
                            <th>Status / User</th>
                            <th>Currency</th>
                            <th>Denomination × Count</th>
                            <th>Volume</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($movementLines)): ?>
                            <?php foreach ($movementLines as $line): ?>
                                <tr>
                                    <td><?= htmlspecialchars($movementStatus . ($actorName !== '' ? ' · ' . $actorName : '')) ?></td>
                                    <td><?= htmlspecialchars((string)($line['currency'] ?? 'GHS')) ?></td>
                                    <td><?= number_format((float)($line['denomination'] ?? 0), (($line['currency'] ?? 'GHS') === 'CFA' ? 0 : 2)) ?> × <?= (int)($line['count'] ?? 0) ?></td>
                                    <td><?= htmlspecialchars((string)($line['currency'] ?? 'GHS')) ?> <?= number_format((float)($line['volume'] ?? 0), 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4">No denomination lines recorded.</td></tr>
                        <?php endif; ?>
                        <?php
                            $balanceBefore = $movement['balance_before'] ?? [];
                            $balanceAfter = $movement['balance_after'] ?? [];
                        ?>
                        <tr>
                            <td colspan="4">
                                <strong>Balance:</strong>
                                GHS <?= number_format((float)($balanceBefore['ghs'] ?? 0), 2) ?> →
                                GHS <?= number_format((float)($balanceAfter['ghs'] ?? 0), 2) ?>;
                                CFA <?= number_format((float)($balanceBefore['cfa'] ?? 0), 0) ?> →
                                CFA <?= number_format((float)($balanceAfter['cfa'] ?? 0), 0) ?>
                            </td>
                        </tr>
                        <?php if (!empty($movementDetail['label'])): ?>
                            <tr><td colspan="4"><strong><?= htmlspecialchars((string)$movementDetail['label']) ?></strong>
                                <?php foreach (['type' => 'Type', 'network' => 'Network', 'phone' => 'Phone', 'reference' => 'Reference', 'status' => 'Status'] as $detailKey => $detailLabel): ?>
                                    <?php if (!empty($movementDetail[$detailKey])): ?> · <?= htmlspecialchars($detailLabel . ': ' . (string)$movementDetail[$detailKey]) ?><?php endif; ?>
                                <?php endforeach; ?>
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No denomination history recorded for this drawer.</p>
        <?php endif; ?>

        <div class="section-title">CASHDRAWER EDIT HISTORY</div>
        <?php if (!empty($cashdrawer_edit_history)): ?>
            <table>
                <thead><tr><th>Date</th><th>User</th><th>Changes</th></tr></thead>
                <tbody>
                    <?php foreach ($cashdrawer_edit_history as $editLog): ?>
                        <?php
                            $editDescription = (string)($editLog->description ?? '');
                            $parsedEdit = function_exists('operations_account_parse_cashdrawer_edit_description')
                                ? operations_account_parse_cashdrawer_edit_description($editDescription)
                                : ['actor' => '', 'raw' => $editDescription];
                            $editActor = trim((string)($editLog->user_name ?? ''));
                            if ($editActor === '') {
                                $editActor = trim((string)($parsedEdit['actor'] ?? ''));
                            }
                            $editSummary = [];
                            foreach (($parsedEdit['summary'] ?? []) as $change) {
                                $editSummary[] = ($change['label'] ?? 'Change') . ': ' . ($change['old'] ?? '') . ' → ' . ($change['new'] ?? '');
                            }
                            foreach (($parsedEdit['denomination_changes'] ?? []) as $change) {
                                $editSummary[] = ($change['label'] ?? 'Denomination') . ': ' . ($change['old_count'] ?? 0) . ' → ' . ($change['new_count'] ?? 0);
                            }
                            if (empty($editSummary)) {
                                $editSummary[] = (string)($parsedEdit['raw'] ?? $editDescription);
                            }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars((string)($editLog->date_created ?? '')) ?></td>
                            <td><?= htmlspecialchars($editActor !== '' ? $editActor : 'Unknown') ?></td>
                            <td><?= htmlspecialchars(implode('; ', $editSummary)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No cashdrawer edits recorded for this drawer.</p>
        <?php endif; ?>

        <div class="section-title">CHANNEL EXCHANGE HISTORY</div>
        <?php if (!empty($channel_exchange_history)): ?>
            <table>
                <thead><tr><th>Date</th><th>User</th><th>Exchange</th></tr></thead>
                <tbody>
                    <?php foreach ($channel_exchange_history as $exchange): ?>
                        <?php
                            $exchangeDescription = (string)($exchange->description ?? '');
                            $exchangeDetails = $exchangeDescription;
                            if (preg_match('/\bfrom=([^\s]+)\s+to=([^\s]+)\s+amount=([0-9.]+)/i', $exchangeDescription, $exchangeMatch)) {
                                $from = str_replace('_', ' ', preg_replace('/^ECASH_/i', '', $exchangeMatch[1]));
                                $to = str_replace('_', ' ', preg_replace('/^ECASH_/i', '', $exchangeMatch[2]));
                                $exchangeDetails = $from . ' → ' . $to . ' · GHS ' . number_format((float)$exchangeMatch[3], 2);
                            } elseif (preg_match('/\bfrom\s+([A-Z0-9_\-]+)\s+to\s+([A-Z0-9_\-]+).*?\bAmount:\s*([0-9,.]+)/i', $exchangeDescription, $exchangeMatch)) {
                                $from = str_replace('_', ' ', preg_replace('/^ECASH_/i', '', $exchangeMatch[1]));
                                $to = str_replace('_', ' ', preg_replace('/^ECASH_/i', '', $exchangeMatch[2]));
                                $exchangeDetails = $from . ' → ' . $to . ' · GHS ' . number_format((float)str_replace(',', '', $exchangeMatch[3]), 2);
                            }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars((string)($exchange->date_created ?? '')) ?></td>
                            <td><?= htmlspecialchars(trim((string)($exchange->exchange_user_name ?? '')) ?: 'Unknown') ?></td>
                            <td><?= htmlspecialchars($exchangeDetails) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No channel exchanges recorded for this drawer.</p>
        <?php endif; ?>

        <!-- Footer -->
        <div class="footer">
            <p>This is an automatically generated cashdrawer statement.</p>
            <p>Generated on <?= date('Y-m-d H:i:s') ?></p>
        </div>
    </div>
</body>
</html>
<?php
$html = ob_get_clean();
try {
    $autoload = ROOTPATH . 'assets/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new \RuntimeException('PDF library autoloader is unavailable.');
    }
    require_once $autoload;
    if (!class_exists(\Mpdf\Mpdf::class)) {
        throw new \RuntimeException('PDF generator is unavailable.');
    }

    $pdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 12,
        'margin_right' => 12,
        'margin_top' => 14,
        'margin_bottom' => 14,
        'tempDir' => sys_get_temp_dir(),
    ]);
    $pdf->SetTitle('Cashdrawer Statement - ' . trim($row->user_first_name . ' ' . $row->user_last_name));
    $pdf->WriteHTML($html);
    $pdf->Output('cashdrawer-statement-' . $drawerId . '.pdf', 'D');
} catch (\Throwable $e) {
    error_log('Cashdrawer PDF export failed: ' . $e->getMessage());
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unable to generate the cashdrawer PDF.';
}
exit;

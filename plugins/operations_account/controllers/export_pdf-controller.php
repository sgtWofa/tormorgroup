<?php
// Standalone PDF Export Controller - No Admin Layout
// This file outputs a clean, professional PDF export without any admin UI

// Prevent any output buffering or layout loading
if (ob_get_level()) ob_end_clean();

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
$drawerDate = substr($row->date_created ?? date('Y-m-d'), 0, 10);

// Fetch denominations
$query = "SELECT denomination, SUM(count) AS count, SUM(volume) AS volume
         FROM drawer_denos
         WHERE drawer_id = :drawer_id AND user_id = :user_id
         GROUP BY denomination
         ORDER BY denomination DESC";

$denominations = $wallet->query($query, [
    'drawer_id' => $drawerId,
    'user_id'   => $row->user_id,
]);

// Legacy fallback
if (empty($denominations)) {
    $query = "SELECT denomination, SUM(count) AS count, SUM(volume) AS volume
             FROM drawer_denos
             WHERE user_id = :user_id AND DATE(date_created) = :drawer_date
             GROUP BY denomination
             ORDER BY denomination DESC";

    $denominations = $wallet->query($query, [
        'user_id'     => $row->user_id,
        'drawer_date' => $drawerDate,
    ]);
}

if (function_exists('operations_account_normalize_denomination_rows')) {
    $denominations = operations_account_normalize_denomination_rows($denominations, 'GHS');
}

$denominations = array_values(array_filter((array)$denominations, function ($deno): bool {
    if (is_array($deno)) {
        $deno = (object)$deno;
    }
    $count = (float)($deno->count ?? 0);
    $volume = (float)($deno->volume ?? 0);
    return $count > 0 && $volume > 0.009;
}));

// Calculate totals
$query = "SELECT SUM(volume) AS total_volume, SUM(count) AS total_count
          FROM drawer_denos
          WHERE drawer_id = :drawer_id AND user_id = :user_id";
$total_cashdrawer = $wallet->query($query, [
    'drawer_id' => $drawerId,
    'user_id'   => $row->user_id,
])[0] ?? null;

if (empty($total_cashdrawer)) {
    $query = "SELECT SUM(volume) AS total_volume, SUM(count) AS total_count
              FROM drawer_denos
              WHERE user_id = :user_id AND DATE(date_created) = :drawer_date";
    $total_cashdrawer = $wallet->query($query, [
        'user_id'     => $row->user_id,
        'drawer_date' => $drawerDate,
    ])[0] ?? null;
}

$total_volume = 0;
$total_count = 0;
if ($total_cashdrawer) {
    $total_volume = (float)($total_cashdrawer->total_volume ?? 0);
    $total_count = (float)($total_cashdrawer->total_count ?? 0);
}

$denomination_total_volume = 0.0;
$denomination_total_count = 0.0;
foreach ($denominations as $deno) {
    $denomination_total_volume += (float)($deno->volume ?? 0);
    $denomination_total_count += (int)($deno->count ?? 0);
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

// Fetch e-cash channels
$ecash_channels = [];
try {
    $db_channels = new \Core\Database;
    if ($db_channels->table_exists(['drawer_balances'])) {
        $bal_query = "SELECT channel_type, channel_key, opening_balance, credit, debit, closing_balance
                      FROM drawer_balances
                      WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash'";
        $bal_rows = $wallet->query($bal_query, [
            'drawer_id' => $row->id,
            'user_id'   => $row->user_id,
        ]);
        if (!empty($bal_rows)) {
            foreach ($bal_rows as $bal) {
                $opening = (float)($bal->opening_balance ?? 0);
                $credit = (float)($bal->credit ?? 0);
                $debit = (float)($bal->debit ?? 0);
                $closing = $opening + $credit - $debit;
                $bal->closing_balance = $closing;
                $ecash_channels[] = $bal;
            }
        }
    }
} catch (\Throwable $e) {
    // fail silently
}

$hasPositiveClosingBalance = function ($channel): bool {
    if (is_array($channel)) {
        $channel = (object)$channel;
    }
    return (float)($channel->closing_balance ?? 0) > 0.009;
};

$cash_channels = array_values(array_filter($cash_channels, $hasPositiveClosingBalance));
$ecash_channels = array_values(array_filter($ecash_channels, $hasPositiveClosingBalance));

// Output HTML for PDF
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashdrawer Statement - <?= htmlspecialchars($row->user_first_name . ' ' . $row->user_last_name) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 13px;
            color: #000;
            background-color: #fff;
            line-height: 1.6;
        }
        
        body {
            padding: 0;
            margin: 0;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 15px 20px;
            background-color: #fff;
        }
        
        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #1a1a1a;
            padding-bottom: 10px;
        }
        
        .header-top {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
            gap: 10px;
        }
        
        .logo {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .app-info h2 {
            font-size: 18px;
            font-weight: 700;
            color: #1a1a1a;
            margin: 0;
        }
        
        .app-info p {
            font-size: 11px;
            color: #999;
            margin: 2px 0 0 0;
        }
        
        .header h1 {
            font-size: 20px;
            font-weight: 700;
            margin: 5px 0 5px 0;
            color: #000;
            letter-spacing: -0.5px;
        }
        
        .header-details {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 5px;
            font-size: 11px;
        }
        
        .header-detail {
            text-align: center;
        }
        
        .header-detail label {
            font-weight: 600;
            color: #333;
            display: block;
            margin-bottom: 1px;
            font-size: 10px;
        }
        
        .header-detail value {
            color: #666;
            display: block;
            font-size: 11px;
        }
        
        .header p {
            font-size: 12px;
            color: #666;
            margin: 3px 0;
        }
        
        .section-title {
            font-size: 13px;
            font-weight: 700;
            margin-top: 12px;
            margin-bottom: 8px;
            padding: 6px 8px;
            background-color: #f5f5f5;
            border-left: 3px solid #333;
            color: #000;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            background-color: #fff;
            font-size: 11px;
        }
        
        table thead {
            background-color: #333;
            color: #fff;
        }
        
        table th {
            padding: 6px 8px;
            text-align: left;
            font-weight: 600;
            border: 1px solid #333;
            background-color: #333;
            color: #fff;
            font-size: 11px;
        }
        
        table td {
            padding: 5px 8px;
            border: 1px solid #ddd;
            background-color: #fff;
            color: #000;
            text-align: left;
            font-size: 11px;
        }
        
        table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        table tbody tr:hover {
            background-color: #f0f0f0;
        }
        
        table tr.total {
            background-color: #e8e8e8;
            font-weight: 600;
            border-top: 2px solid #333;
        }
        
        table tr.total td {
            background-color: #e8e8e8;
            color: #000;
            text-align: left;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-left {
            text-align: left;
        }
        
        .text-success {
            color: #28a745;
        }
        
        .text-danger {
            color: #dc3545;
        }
        
        .footer {
            margin-top: 15px;
            padding-top: 8px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 10px;
            color: #999;
        }
        
        @media print {
            * {
                background: white !important;
                color: black !important;
                box-shadow: none !important;
            }
            
            body {
                margin: 0;
                padding: 0;
            }
            
            .container {
                max-width: 100%;
                padding: 20px;
                margin: 0;
            }
            
            table {
                page-break-inside: avoid;
            }
            
            tr {
                page-break-inside: avoid;
            }
            
            .no-print {
                display: none !important;
            }
        }
    </style>
    <script>
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
                // Close window after print dialog is closed
                window.setTimeout(function() {
                    window.close();
                }, 1000);
            }, 500);
        });
        
        // Handle print dialog close/cancel
        window.addEventListener('afterprint', function() {
            window.close();
        });
    </script>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-top">
                <div class="logo">
                    <?php if (defined('APP_LOGO2') && !empty(APP_LOGO2)): ?>
                        <img src="<?= htmlspecialchars(APP_LOGO2) ?>" alt="<?= htmlspecialchars(APP_NAME ?? 'App Logo') ?>">
                    <?php else: ?>
                        <img src="<?= ROOT ?>/assets/images/tormorgroup3.png" alt="<?= htmlspecialchars(APP_NAME ?? 'App Logo') ?>">
                    <?php endif; ?>
                </div>
                <div class="app-info">
                    <h2><?= htmlspecialchars(APP_NAME ?? 'TormorGroup') ?></h2>
                    <p>Financial Management System</p>
                </div>
            </div>
            
            <h1><?= strtoupper(htmlspecialchars($row->user_first_name . ' ' . $row->user_last_name)) ?>'s CASH DRAWER</h1>
            
            <div class="header-details">
                <div class="header-detail">
                    <label>Drawer ID</label>
                    <value><?= htmlspecialchars($row->id) ?></value>
                </div>
                <div class="header-detail">
                    <label>Created Date</label>
                    <value><?= date('F d, Y', strtotime($row->date_created)) ?></value>
                </div>
                <div class="header-detail">
                    <label>Statement Date</label>
                    <value><?= date('F d, Y H:i') ?></value>
                </div>
            </div>
        </div>

        <!-- Cashdrawer Details Section -->
        <div class="section-title">CASHDRAWER DETAILS</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 8%;">#</th>
                    <th style="width: 32%;">Denomination</th>
                    <th style="width: 30%;">Count</th>
                    <th style="width: 30%;">Volume</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($denominations)): $id = 1; ?>
                    <?php foreach ($denominations as $deno): ?>
                        <tr>
                            <td class="text-left"><?= $id++ ?></td>
                            <td class="text-left">GHS <?= number_format($deno->denomination, 2) ?></td>
                            <td class="text-left"><?= htmlspecialchars($deno->count ?? '0') ?></td>
                            <td class="text-left">GHS <?= number_format($deno->volume ?? 0, 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-left">No denomination records</td>
                    </tr>
                <?php endif; ?>
                <tr class="total">
                    <td colspan="2" class="text-left">TOTAL</td>
                    <td class="text-left"><?= number_format((float)($denomination_total_count ?? $total_count ?? 0)) ?></td>
                    <td class="text-left">GHS <?= number_format((float)($denomination_total_volume ?? $total_volume ?? 0), 2) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Cash Channels Section -->
        <?php if (!empty($cash_channels)): ?>
            <div class="section-title">CASH CHANNELS</div>
            <table>
                <thead>
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
                        <tr>
                            <td><?= htmlspecialchars($ch->channel_key) ?></td>
                            <td class="text-right">GHS <?= number_format($ch->opening_balance, 2) ?></td>
                            <td class="text-right text-success">GHS <?= number_format($ch->credit, 2) ?></td>
                            <td class="text-right text-danger">GHS <?= number_format($ch->debit, 2) ?></td>
                            <td class="text-right"><strong>GHS <?= number_format($ch->closing_balance, 2) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- E-Cash Channels Section -->
        <?php if (!empty($ecash_channels)): ?>
            <div class="section-title">E-CASH CHANNELS (MTN / TELECEL / AT)</div>
            <table>
                <thead>
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
                        <tr>
                            <td><?= htmlspecialchars(str_replace('_', ' ', $ch->channel_key)) ?></td>
                            <td class="text-right">GHS <?= number_format($ch->opening_balance, 2) ?></td>
                            <td class="text-right text-success">GHS <?= number_format($ch->credit, 2) ?></td>
                            <td class="text-right text-danger">GHS <?= number_format($ch->debit, 2) ?></td>
                            <td class="text-right"><strong>GHS <?= number_format($ch->closing_balance, 2) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Footer -->
        <div class="footer">
            <p>This is an automatically generated cashdrawer statement. Print this document for your records.</p>
            <p>Generated on <?= date('Y-m-d H:i:s') ?></p>
        </div>
    </div>
</body>
</html>
<?php
exit;

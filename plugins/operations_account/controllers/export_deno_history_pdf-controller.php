<?php
// Standalone PDF Export Controller for Denomination History
// This file outputs a clean, professional PDF export of the history without any admin UI

// Prevent any output buffering or layout loading
if (ob_get_level()) ob_end_clean();

// Ensure we're working with the prepared data from the AJAX endpoint
if (empty($pdfData)) {
    http_response_code(500);
    echo "Error: No PDF data available";
    exit;
}

$drawer = $pdfData['drawer'] ?? null;
$history = $pdfData['history'] ?? [];
$totalMovements = $pdfData['total_movements'] ?? 0;

if (empty($drawer)) {
    http_response_code(500);
    echo "Error: No drawer data available";
    exit;
}

// Helper function to format currency
$formatCurrency = function($amount, $decimals = 2) {
    return number_format((float)$amount, $decimals, '.', ',');
};

// Helper function to get transaction type label
$getTransType = function($movement) {
    $status = strtolower(trim($movement['trans_status'] ?? ''));
    $hasGhs = $movement['meta']['has_ghs'] ?? false;
    $hasCfa = $movement['meta']['has_cfa'] ?? false;
    $netGhs = $movement['meta']['net_ghs'] ?? 0;
    $netCfa = $movement['meta']['net_cfa'] ?? 0;
    
    if (stripos($status, 'opening') !== false) {
        return 'Opening';
    }
    
    if ($hasGhs && $hasCfa) {
        return 'Exchange';
    }
    
    if ($netGhs > 0 || $netCfa > 0) {
        return 'Cash In';
    }
    
    if ($netGhs < 0 || $netCfa < 0) {
        return 'Cash Out';
    }
    
    return ucfirst($status);
};

// Output HTML for PDF
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashdrawer History Export - <?= htmlspecialchars($drawer->name ?? 'Drawer') ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
            color: #000;
            background-color: #fff;
            line-height: 1.5;
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
            margin-bottom: 20px;
            border-bottom: 2px solid #1a1a1a;
            padding-bottom: 10px;
        }
        
        .header h1 {
            font-size: 18px;
            margin-bottom: 5px;
            color: #1a1a1a;
        }
        
        .header .subtitle {
            font-size: 13px;
            color: #666;
            margin: 5px 0;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            font-size: 12px;
        }
        
        .info-label {
            font-weight: bold;
            min-width: 150px;
        }
        
        .info-value {
            text-align: right;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        table thead {
            background-color: #f0f0f0;
            border-top: 1px solid #999;
            border-bottom: 2px solid #333;
        }
        
        table th {
            padding: 10px 5px;
            text-align: left;
            font-weight: bold;
            font-size: 12px;
            color: #000;
        }
        
        table td {
            padding: 8px 5px;
            border-bottom: 1px solid #ddd;
            font-size: 11px;
        }
        
        table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .numeric {
            text-align: right;
            font-family: 'Courier New', monospace;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
            background-color: #e8f4f8;
            color: #0066cc;
        }
        
        .summary {
            margin: 20px 0;
            padding: 15px;
            background-color: #f5f5f5;
            border-left: 4px solid #0066cc;
        }
        
        .summary h3 {
            font-size: 13px;
            margin-bottom: 10px;
            color: #1a1a1a;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin: 6px 0;
            font-size: 12px;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 11px;
            color: #666;
        }
        
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header Section -->
        <div class="header">
            <h1>Cashdrawer History Report</h1>
            <div class="subtitle">Denomination Transaction History</div>
            <div class="subtitle"><?= htmlspecialchars($drawer->name ?? 'Drawer') ?></div>
        </div>
        
        <!-- Drawer Information -->
        <div style="margin: 15px 0; padding: 10px; background-color: #f9f9f9; border: 1px solid #ddd;">
            <div class="info-row">
                <span class="info-label">Drawer ID:</span>
                <span class="info-value"><?= htmlspecialchars($drawer->id ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Owner:</span>
                <span class="info-value"><?= htmlspecialchars($drawer->user_first_name . ' ' . $drawer->user_last_name ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Created:</span>
                <span class="info-value"><?= htmlspecialchars(substr($drawer->date_created ?? '', 0, 19)) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Total Transactions:</span>
                <span class="info-value"><?= $totalMovements ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Export Date:</span>
                <span class="info-value"><?= date('Y-m-d H:i:s') ?></span>
            </div>
        </div>
        
        <?php if (!empty($history)): ?>
        <!-- History Table -->
        <table>
            <thead>
                <tr>
                    <th style="width: 8%;">Trans ID</th>
                    <th style="width: 12%;">Date</th>
                    <th style="width: 10%;">Type</th>
                    <th style="width: 10%;">Status</th>
                    <th style="width: 10%;" class="numeric">Denom</th>
                    <th style="width: 8%;" class="numeric">Count</th>
                    <th style="width: 10%;" class="numeric">Volume</th>
                    <th style="width: 12%;" class="numeric">Before</th>
                    <th style="width: 12%;" class="numeric">After</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $idx => $movement): 
                    $transId = $movement['trans_id'] ?? '';
                    $dateCreated = $movement['date_created'] ?? '';
                    $status = $movement['trans_status'] ?? '';
                    $transType = $getTransType($movement);
                    $lines = $movement['lines'] ?? [];
                    $balanceBefore = $movement['balance_before']['ghs'] ?? 0;
                    $balanceAfter = $movement['balance_after']['ghs'] ?? 0;
                    
                    $isFirstLine = true;
                    if (empty($lines)) {
                        // No denos - show single row
                        $lines = [['currency' => 'GHS', 'denomination' => 0, 'count' => 0, 'volume' => 0]];
                    }
                    
                    foreach ($lines as $lineIdx => $line):
                        $lineCurrency = $line['currency'] ?? 'GHS';
                        $denomination = $line['denomination'] ?? 0;
                        $count = $line['count'] ?? 0;
                        $volume = $line['volume'] ?? 0;
                ?>
                <tr>
                    <?php if ($isFirstLine): ?>
                        <td rowspan="<?= count($lines) ?>"><?= htmlspecialchars($transId) ?></td>
                        <td rowspan="<?= count($lines) ?>"><?= htmlspecialchars(substr($dateCreated, 0, 19)) ?></td>
                        <td rowspan="<?= count($lines) ?>"><?= htmlspecialchars($transType) ?></td>
                        <td rowspan="<?= count($lines) ?>">
                            <span class="status-badge"><?= htmlspecialchars($status) ?></span>
                        </td>
                    <?php endif; ?>
                    <td class="numeric"><?= $lineCurrency === 'CFA' ? (int)$denomination : $formatCurrency($denomination) ?></td>
                    <td class="numeric"><?= (int)$count ?></td>
                    <td class="numeric"><?= $formatCurrency($volume) ?></td>
                    <?php if ($isFirstLine): ?>
                        <td rowspan="<?= count($lines) ?>" class="numeric"><?= $formatCurrency($balanceBefore) ?></td>
                        <td rowspan="<?= count($lines) ?>" class="numeric"><?= $formatCurrency($balanceAfter) ?></td>
                    <?php endif; ?>
                </tr>
                <?php 
                        $isFirstLine = false;
                    endforeach;
                endforeach; ?>
            </tbody>
        </table>
        
        <?php else: ?>
        <div style="text-align: center; padding: 30px; color: #999;">
            <p>No transaction history found for this drawer.</p>
        </div>
        <?php endif; ?>
        
        <!-- Summary Section -->
        <div class="summary">
            <h3>Report Summary</h3>
            <div class="summary-row">
                <span>Total Transactions:</span>
                <span class="numeric"><?= count($history) ?></span>
            </div>
            <div class="summary-row">
                <span>Report Generated:</span>
                <span class="numeric"><?= date('Y-m-d H:i:s') ?></span>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>This is an automated export. For official records, please verify with system administrators.</p>
            <p>© <?= date('Y') ?> Tormor Group. All rights reserved.</p>
        </div>
    </div>
</body>
</html>

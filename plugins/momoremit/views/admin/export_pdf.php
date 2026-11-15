<?php

require_once __DIR__ . '/../../../../assets/vendor/autoload.php';

if (!is_dir(__DIR__ . '/tmp')) {
    @mkdir(__DIR__ . '/tmp', 0777, true);
}

function generatePrintContent($row)
{
    $dateCreated = is_object($row) && isset($row->date_created) ? ($row->date_created ?? '') : '';
    $dateLabel = function_exists('get_date_time') ? get_date_time($dateCreated) : date('Y-m-d H:i:s', strtotime($dateCreated ?: 'now'));
    $agentName = trim((($row->user_first_name ?? '') . ' ' . ($row->user_last_name ?? '')));

    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title><?= ucfirst(page()) ?> | <?= APP_NAME ?></title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; color: #222; margin: 0; padding: 16px; }
            h1 { font-size: 18px; margin: 0 0 12px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
            th { background: #f5f5f5; }
            .muted { color: #666; }
            .small { font-size: 10px; color: #666; }
        </style>
    </head>
    <body>
        <?php if (!empty($row)): ?>
            <h1><?= APP_NAME ?> - Transaction Receipt</h1>
            <p><strong>Transaction ID:</strong> <?= esc($row->id ?? '') ?></p>
            <p><strong>Type:</strong> <?= esc($row->transaction_type ?? '') ?></p>
            <p><strong>Network:</strong> <?= esc($row->network ?? '') ?></p>
            <p><strong>Phone:</strong> <?= esc($row->phone ?? '') ?></p>
            <p><strong>Amount:</strong> <?= number_format((float)($row->amount ?? 0), 2) ?></p>
            <p><strong>Charge:</strong> <?= number_format((float)($row->charge ?? 0), 2) ?></p>
            <p><strong>Date:</strong> <?= $dateLabel ?></p>
            <p><strong>Agent:</strong> <?= esc($agentName) ?></p>
            <table>
                <tr><th>Field</th><th>Value</th></tr>
                <tr><td class="muted">Category</td><td><?= esc($row->category ?? '') ?></td></tr>
                <tr><td class="muted">Amount Payable</td><td><?= number_format((float)($row->amount_payable ?? 0), 2) ?></td></tr>
                <tr><td class="muted">Balance After</td><td><?= number_format((float)($row->balance_after ?? 0), 2) ?></td></tr>
            </table>
            <p class="small">Printed on: <?= date('Y-m-d H:i:s') ?></p>
        <?php else: ?>
            <p>No transaction data was found.</p>
        <?php endif; ?>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

$htmlContent = generatePrintContent($row ?? null);

try {
    $mpdf = new \Mpdf\Mpdf([
        'tempDir' => __DIR__ . '/tmp',
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_top' => 12,
        'margin_bottom' => 12,
        'margin_header' => 5,
        'margin_footer' => 5,
    ]);

    $transactionType = preg_replace('/[^a-zA-Z0-9]/', '_', (string)($row->transaction_type ?? 'transaction'));
    $network = preg_replace('/[^a-zA-Z0-9]/', '_', (string)($row->network ?? 'network'));
    $fileName = sprintf('%s_%s_%s.pdf', $transactionType, $network, date('Ymd_His'));

    $mpdf->SetTitle('Momoremit transaction receipt');
    $mpdf->WriteHTML($htmlContent);

    while (ob_get_level()) {
        ob_end_clean();
    }

    $mpdf->Output($fileName, \Mpdf\Output\Destination::INLINE);
} catch (\Mpdf\MpdfException $e) {
    error_log('mPDF Error: ' . $e->getMessage());
    echo 'Error generating PDF: ' . $e->getMessage();
}

exit();
?>

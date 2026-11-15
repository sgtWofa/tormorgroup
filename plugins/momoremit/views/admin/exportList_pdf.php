<?php

require_once __DIR__ . '/../../../../assets/vendor/autoload.php';

if (!is_dir(__DIR__ . '/tmp')) {
    @mkdir(__DIR__ . '/tmp', 0777, true);
}

$filterFind = trim($_GET['find'] ?? '');
$filterDateFrom = trim($_GET['date_from'] ?? '');
$filterDateTo = trim($_GET['date_to'] ?? '');
$filterCreatedBy = trim($_GET['created_by'] ?? '');
$filterTransactionType = trim($_GET['transaction_type'] ?? '');

$db = new \Core\Database;
$conditions = ['m.deleted = 0'];
$params = [];

if ($filterFind !== '') {
    $conditions[] = '(m.network LIKE :find OR m.id LIKE :find OR m.transaction_type LIKE :find OR m.sending_category LIKE :find OR m.category LIKE :find OR m.phone LIKE :find OR u.first_name LIKE :find OR u.last_name LIKE :find)';
    $params['find'] = "%$filterFind%";
}
if ($filterDateFrom !== '') {
    $conditions[] = 'DATE(m.date_created) >= :date_from';
    $params['date_from'] = date('Y-m-d', strtotime($filterDateFrom));
}
if ($filterDateTo !== '') {
    $conditions[] = 'DATE(m.date_created) <= :date_to';
    $params['date_to'] = date('Y-m-d', strtotime($filterDateTo));
}
if ($filterCreatedBy !== '') {
    $conditions[] = 'm.created_by = :created_by';
    $params['created_by'] = (int)$filterCreatedBy;
}
if ($filterTransactionType !== '') {
    $conditions[] = 'LOWER(m.transaction_type) = :transaction_type';
    $params['transaction_type'] = strtolower($filterTransactionType);
}

$whereSql = 'WHERE ' . implode(' AND ', $conditions);

$query = "SELECT 
    m.*, 
    u.first_name AS user_first_name,
    u.last_name AS user_last_name,
    u.image AS user_image,
    u_updated.first_name AS updated_by_first_name,
    u_updated.last_name AS updated_by_last_name,
    u_updated.image AS updated_by_image,
    in_wallet.name AS in_wallet_name,
    in_wallet.network AS in_wallet_network,
    out_wallet.name AS out_wallet_name,
    out_wallet.network AS out_wallet_network
FROM momoremits AS m
LEFT JOIN users AS u ON m.created_by = u.id
LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id
LEFT JOIN mywallets AS in_wallet ON m.wallet_in_id = in_wallet.id
LEFT JOIN mywallets AS out_wallet ON m.wallet_out_id = out_wallet.id
$whereSql
ORDER BY m.id DESC";

$rows = $db->query($query, $params) ?: [];

$total = $db->query("SELECT COALESCE(SUM(m.amount),0) AS total_amount, COALESCE(SUM(m.charge),0) AS total_charge FROM momoremits AS m $whereSql", $params)[0] ?? null;
$total_amount = (float)($total->total_amount ?? 0);
$total_charge = (float)($total->total_charge ?? 0);

function generatePDFContent($rows, $total_amount, $total_charge, $filters)
{
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title><?= ucfirst(page()) ?> | <?= APP_NAME ?></title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; color: #222; margin: 0; padding: 16px; }
            .header { border-bottom: 2px solid #f59e0b; padding-bottom: 10px; margin-bottom: 12px; }
            .company { font-size: 18px; font-weight: bold; color: #111827; }
            .sub { color: #6b7280; font-size: 10px; margin-top: 3px; }
            .meta { margin: 8px 0 12px; padding: 8px 10px; background: #f9fafb; border: 1px solid #e5e7eb; }
            table { width: 100%; border-collapse: collapse; margin-top: 8px; }
            th, td { border: 1px solid #d1d5db; padding: 6px; text-align: left; }
            th { background: #f3f4f6; font-weight: bold; }
            .totals { font-weight: bold; background: #fef3c7; }
            .small { font-size: 10px; color: #6b7280; }
            .footer { border-top: 1px solid #e5e7eb; margin-top: 16px; padding-top: 8px; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="company"><?= APP_NAME ?></div>
            <div class="sub">Momoremit Transaction Report</div>
        </div>
        <div class="meta">
            <strong>Generated:</strong> <?= date('Y-m-d H:i:s') ?><br>
            <?php if (!empty($filters['find'])): ?><strong>Search:</strong> <?= esc($filters['find']) ?> &nbsp;<?php endif; ?>
            <?php if (!empty($filters['date_from'])): ?><strong>From:</strong> <?= esc($filters['date_from']) ?> &nbsp;<?php endif; ?>
            <?php if (!empty($filters['date_to'])): ?><strong>To:</strong> <?= esc($filters['date_to']) ?> &nbsp;<?php endif; ?>
            <?php if (!empty($filters['created_by'])): ?><strong>Created by:</strong> <?= esc($filters['created_by']) ?> &nbsp;<?php endif; ?>
            <?php if (!empty($filters['transaction_type'])): ?><strong>Type:</strong> <?= esc($filters['transaction_type']) ?><?php endif; ?>
        </div>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Transaction ID</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Charge</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($rows)): $id = 0; foreach ($rows as $row): $id++; ?>
                    <tr>
                        <td><?= $id ?></td>
                        <td><?= esc($row->id ?? '') ?></td>
                        <td><?= esc($row->date_created ?? '') ?></td>
                        <td><?= esc(($row->network ?? '') . ' - ' . ($row->transaction_type ?? '')) ?></td>
                        <td><?= number_format((float)($row->amount ?? 0), 2) ?></td>
                        <td><?= number_format((float)($row->charge ?? 0), 2) ?></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="6">No records to show.</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="totals">
                    <th colspan="4">Totals</th>
                    <th><?= number_format((float)$total_amount, 2) ?></th>
                    <th><?= number_format((float)$total_charge, 2) ?></th>
                </tr>
            </tfoot>
        </table>
        <div class="footer">
            <div class="small">Prepared for <?= APP_NAME ?></div>
            <div class="small">Printed on: <?= date('Y-m-d H:i:s') ?></div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

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

    $mpdf->SetTitle('Momoremit export');
    $mpdf->SetHeader(APP_NAME);
    $mpdf->SetFooter('{PAGENO}');
    $mpdf->WriteHTML(generatePDFContent($rows ?? [], $total_amount ?? 0, $total_charge ?? 0, [
        'find' => $filterFind,
        'date_from' => $filterDateFrom,
        'date_to' => $filterDateTo,
        'created_by' => $filterCreatedBy,
        'transaction_type' => $filterTransactionType,
    ]));

    while (ob_get_level()) {
        ob_end_clean();
    }

    $mpdf->Output('MomoRemitances_Report_' . date('Ymd_His') . '.pdf', \Mpdf\Output\Destination::INLINE);
} catch (\Mpdf\MpdfException $e) {
    error_log('mPDF Error: ' . $e->getMessage());
    echo 'Error generating PDF: ' . $e->getMessage();
} catch (\Exception $e) {
    error_log('PDF Export Error: ' . $e->getMessage());
    echo 'Error generating PDF: ' . $e->getMessage();
}

exit();
?>

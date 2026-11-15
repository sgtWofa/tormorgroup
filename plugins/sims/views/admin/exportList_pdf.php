<?php

require_once __DIR__ . '/../../../../assets/vendor/autoload.php';

if (!isset($_GET['phase'])) {
    $pdf_url = $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') === false ? '?' : '&') . 'phase=pdf';

    echo '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            #preloader {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: white;
                z-index: 9999;
                display: flex;
                justify-content: center;
                align-items: center;
                flex-direction: column;
            }
            .progress-bar {
                width: 300px;
                height: 20px;
                background: #f0f0f0;
                border-radius: 10px;
                overflow: hidden;
                margin-top: 20px;
            }
            .progress {
                width: 0%;
                height: 100%;
                background: #28a745;
                transition: width 0.3s ease;
            }
        </style>
    </head>
    <body>
        <div id="preloader">
            <h3>Generating PDF...</h3>
            <div class="progress-bar">
                <div class="progress" id="progress"></div>
            </div>
        </div>
        <script>
            let progress = 0;
            const interval = setInterval(() => {
                progress += Math.random() * 15;
                document.getElementById("progress").style.width = Math.min(progress, 100) + "%";
                if (progress >= 100) {
                    clearInterval(interval);
                    window.location.href = "' . $pdf_url . '";
                }
            }, 200);
        </script>
    </body>
    </html>';
    exit();
}

if (isset($_GET['phase']) && $_GET['phase'] === 'pdf') {
    try {
        while (ob_get_level()) {
            ob_end_clean();
        }

        if (!class_exists('Mpdf\\Mpdf')) {
            throw new Exception('mPDF library is missing');
        }

        $find = trim($_GET['find'] ?? '');
        $titleSuffix = $find !== '' ? (' (Filter: ' . $find . ')') : '';

        $rows = $rows ?? [];
        if (!is_array($rows)) {
            $rows = [];
        }

        $total_amount = (float)($total_amount ?? 0);

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Sims Report | <?=APP_NAME?></title>
            <style>
                body { font-family: Tahoma; font-size: 12px; }
                h2 { margin: 0 0 6px 0; }
                .meta { color: #666; margin-bottom: 14px; }
                table { width: 100%; border-collapse: collapse; table-layout: fixed; }
                th, td { border: 1px solid #ddd; padding: 7px 6px; vertical-align: top; word-wrap: break-word; }
                th { background: #f6f6f6; text-transform: uppercase; font-size: 11px; color: #333; }
                tfoot td { font-weight: bold; background: #f9f9f9; }
            </style>
        </head>
        <body>
            <div style="text-align:center; margin-bottom: 14px;">
                <div style="font-size: 14px; font-weight: 700;"><?=APP_NAME?></div>
                <h2>SIMS Report<?=$titleSuffix?></h2>
                <div class="meta">Generated: <?=date('Y-m-d H:i:s')?></div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th style="width: 120px;">Date</th>
                        <th style="width: 80px;">Network</th>
                        <th style="width: 120px;">Status</th>
                        <th style="width: 170px;">Serial</th>
                        <th style="width: 120px;">PUK</th>
                        <th style="width: 90px;">Amount</th>
                        <th style="width: 120px;">Assigned</th>
                        <th style="width: 120px;">Buyer</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rows)): ?>
                        <?php $i = 0; foreach ($rows as $row): $i++; ?>
                            <tr>
                                <td><?= (int)$i ?></td>
                                <td><?= htmlspecialchars((string)($row->date_updated ?? $row->date_created ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string)($row->network ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string)($row->transaction_status ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string)($row->serial_number ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string)($row->puk ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= number_format((float)($row->amount ?? 0), 2) ?></td>
                                <td><?= htmlspecialchars(trim((string)($row->assigned_first_name ?? '') . ' ' . (string)($row->assigned_last_name ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string)($row->buyer_phone ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align:center; padding: 18px;">No records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6">TOTAL</td>
                        <td colspan="3">GHS <?=number_format($total_amount, 2)?></td>
                    </tr>
                </tfoot>
            </table>
        </body>
        </html>
        <?php
        $html = ob_get_clean();

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_top' => 10,
            'margin_bottom' => 10,
        ]);

        $mpdf->WriteHTML($html);

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="sims_report.pdf"');
        $mpdf->Output('sims_report.pdf', 'I');
        exit;
    } catch (Throwable $e) {
        echo 'Error generating PDF';
        exit;
    }
}

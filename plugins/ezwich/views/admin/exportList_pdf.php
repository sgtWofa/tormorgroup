<?php

require_once __DIR__ . '/../../../../assets/vendor/autoload.php'; // Ensure this path is correct

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// First phase: Show preloader
if (!isset($_GET['phase'])) {
    $pdf_url = $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') === false ? '?' : '&') . 'phase=pdf';
    ?>
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
                    window.location.href = "<?= $pdf_url ?>";
                }
            }, 200);
        </script>
    </body>
    </html>
    <?php
    exit();
}

// Second phase: Generate PDF
if (isset($_GET['phase']) && $_GET['phase'] === 'pdf') {
    ob_clean();

    function generatePDFContent($rows, $total_amount, $total_charge, $total_qty)
    {            
            ob_start();
            ?>
          <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="utf-8">
                    <link rel="stylesheet" type="text/css" href="<?=ROOT?>/assets/css/bootstrap.min.css">
                    <title><?=ucfirst(page())?> | <?=APP_NAME?></title>
                    <meta name="viewport" content="width=device-width, initial-scale=1">

                    <style>
                        body { 
                            font-family: Tahoma;
                            margin: 0;
                            padding: 20px;
                        }
                        .table { 
                            width: 100%; 
                            border-collapse: collapse !important;
                            table-layout: fixed;
                        }
                        th, td { 
                            padding: 12px 8px !important;
                            vertical-align: middle !important;
                            border: 1px solid #dee2e6 !important;
                        }
                         /* Add this FIRST before Bootstrap to ensure priority */
                            .badge {
                                -mpdf-border-radius: 20px !important;
                                overflow: hidden !important;
                                background-clip: padding-box !important;
                                border: 0 !important;
                                border-radius: 20px !important;
                                min-width: 85px !important;
                                padding: 6px 12px !important;
                            }

                        /* Status colors - use solid colors (gradients won't work well in PDF) */
                        .bg-success { background: #28a745 !important; color: white !important; }
                        .bg-warning { background: #ffc107 !important; color: black !important; }
                        .bg-danger { background: #dc3545 !important; color: white !important; }
                        .bg-secondary { background: #6c757d !important; color: white !important; }

                        .text-success { color: #28a745 !important; }
                        .text-warning { color: #ffc107 !important; }
                        .text-danger { color: #dc3545 !important; }
                        .text-secondary { color: #6c757d !important; }

                        .d-flex.align-items-center {
                            align-items: center !important;
                        }
                        .ms-2 {
                            margin-left: 0.5rem !important;
                        }
                        #receipt-content {
                            width: 100% !important;
                            margin: 0 auto !important;
                            border: none !important;
                        }
                        .container {
                            max-width: 100% !important;
                            padding: 0 20px;
                        }
                        .text-center.mb-4 {
                            margin-bottom: 2rem !important;
                        }
                        tfoot.bg-light td {
                            background-color: #f8f9fa !important;
                            font-weight: bold;
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="text-center mb-4">
                            <div class="badge">
                                <img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;;max-width:20px;max-height: 20px;object-fit: cover;"><br>
                                <strong style="font-size:15px; color: gray;"> <?=APP_NAME?></strong>
                            </div>
                            <h3><?= strtoupper('EZWICH REPORT')?></h3>
                        </div>
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th class="bg-warning">#</th>
                                    <th class="bg-warning">ID</th>
                                    <th class="bg-warning">Reference</th>
                                    <th class="bg-warning">type</th>
                                    <th class="bg-warning">Status</th>
                                    <th class="bg-warning">Amount</th>
                                    <th class="bg-warning">Phone</th>
                                    <th class="bg-warning">Date</th>
                                    <th class="bg-warning">Agent</th>
                                </tr>
                            </thead>
                            <tbody id="activityTable">
                            <?php if(!empty($rows)): $id = 0;?>
                                <?php foreach($rows as $row): $id ++;?>
                                    <tr>
                                        <td><?=$id?></td>
                                        <td><?=$row->id?></td>
                                        <td><?=esc($row->transaction_id ?? '')?> </a>
                                        <td><?=esc($row->transaction_type)?> </td>
                                        </td>
                                        <td>
                                            <?php
                                                    // Determine badge class based on transaction status
                                                    $badgeClass = '';
                                                    switch (strtolower($row->transaction_status ?? 'approved')) {
                                                        case 'pending':
                                                            $badgeClass = 'badge text-warning';
                                                            break;
                                                        case 'approved':
                                                            $badgeClass = 'badge text-success';
                                                            break;
                                                        case 'rejected':
                                                            $badgeClass = 'badge text-danger';
                                                            break;
                                                        default:
                                                            $badgeClass = 'badge text-secondary';
                                                            break;
                                                    }
                                                ?>
                                            <span class="<?= $badgeClass ?>"><?=esc($row->transaction_status)?></span>
                                        </td>
                                        <td><?=number_format($row->amount ?? 0, 2)?></td>
                                        <td><?=esc($row->phone ?? '')?></td>
                                        <td><?=get_date_time($row->date_created)?></td>
                                        <td><?=$row->user_first_name . ' ' . $row->user_last_name ?></td>
                                    </tr>
                                <?php endforeach?>
                            <?php else:?>
                                <tr><td colspan="8" class="text-center">No records to show!!</td></tr>
                            <?php endif?>
                            </tbody>
                            <tfoot id="tableFooter">
                                <tr>
                                    <th colspan="5" class="bg-warning"></th>
                                    <th colspan="2" class="bg-warning">Summary</th>
                                    <th class="bg-warning">Amount</th>
                                    <th class="bg-warning">Charges</th>
                                </tr>
                                <tr>
                                    <th colspan="5" style="border:none;"></th>
                                    <th colspan="2" class="bg-warning">Total Transactions:</th>
                                    <th class="bg-warning"><?=number_format($total_amount ?? 0, 2)?></th>
                                    <th class="bg-warning"><?=number_format($total_charge ?? 0, 2)?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
            
                    <!-- Inline footer -->
                    <div style="margin-top: 5px; text-align: center; font-size: 10px;">
                        <div style='text-align: center; font-size: 10px; opacity: 5;'>
                            <span style='padding:5px; font-weight:450px; ' class="text-warning">
                                <?=APP_NAME ?? ''?>
                            </span><br>
                            <span>Printed on: <?= date('Y-m-d H:i:s') ?></span><br>
                            <span><a style="text-decoration: none; color:gray; " href="httsp://www.tormorgroup.com">&copy;www.tormorgroup.com</a></span>
                        </div>
                    </div>
                </body>
                </html>
        <?php
        return ob_get_clean();
    }

    try {

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_top' => 10,
            'margin_bottom' => 20, // Increased for footer space
            'margin_header' => 8,
            'margin_footer' => 10,
            'tempDir' => __DIR__ . '/tmp',
            'allowCSSBorders' => true,
            'useSubstitutions' => false,
            'simpleTables' => true
        ]);

        // Set pagination footer
        $mpdf->SetFooter('{PAGENO}', 'R');
        $mpdf->SetHTMLFooter('
        <div style="text-align: center; font-family: Tahoma; font-size: 10px;">
            Page {PAGENO} of {nbpg}
        </div>', 'O');
        
        // Clean output buffer before sending
        ob_end_clean();

        // Before mpdf->Output(), add:
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="ezwich_Report.pdf"');

        $mpdf->WriteHTML(generatePDFContent($rows, $total_amount, $total_charge, $total_qty));
        $mpdf->Output('ezwich_transactions_'.date('Ymd_His').'.pdf', \Mpdf\Output\Destination::INLINE);

    } catch (\Mpdf\MpdfException $e) {
        die("PDF Error: " . $e->getMessage());
    } catch (\Exception $e) {
        die("Error: " . $e->getMessage());
    }
}
?>

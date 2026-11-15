<?php

require_once __DIR__ . '/../../../../assets/vendor/autoload.php'; // Ensure this path is correct

// First phase: Show preloader with automatic refresh
if (!isset($_GET['phase'])) {
    // Get the current URL with phase parameter
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
            // Automatic progress animation
            let progress = 0;
            const interval = setInterval(() => {
                progress += Math.random() * 15;
                document.getElementById("progress").style.width = Math.min(progress, 100) + "%";
                if (progress >= 100) {
                    clearInterval(interval);
                    // Refresh to PDF generation phase
                    window.location.href = "' . $pdf_url . '";
                }
            }, 200);
        </script>
    </body>
    </html>';
    exit();
}

// Second phase: Generate PDF
if (isset($_GET['phase']) && $_GET['phase'] === 'pdf') {
    function generatePrintContent($row)
    {
        // Add inline Bootstrap CSS
        $bootstrap_css = file_get_contents(ROOT.'/assets/css/bootstrap.min.css');
        
        // Force accordion to show content
        $accordion_fix = '
        <style>
            .accordion-collapse.collapse {
                display: block !important;
                height: auto !important;
            }
            .accordion-button::after {
                display: none !important;
            }
        </style>';

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
                        #receipt-content {
                            width: 100% !important;
                            margin: 0 auto !important;
                            border: none !important;
                        }
                        .table {
                            width: 100%;
                            border-collapse: collapse;
                        }
                        #receipt-content {
                            border: 2px solid #e0e0e0;
                            border-radius: 10px;
                            margin: 10px auto;
                            padding: 25px;
                            background: #fff;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            margin: 15px 0;
                        }
                        th {
                            background-color: #f8f9fa;
                            color: darkgray !important;
                            padding: 5px;
                            text-align: left;
                            width: 60%;
                        }
                        td {
                            padding: 5px;
                            border-bottom: 1px solid #6c757d !important;
                            color: #212529;
                        }                         /* Add this FIRST before Bootstrap to ensure priority */
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
                        h4 {
                            color: #343a40;
                            margin-bottom: 10px;
                        }
                        @media print {
                            body {
                                margin: 0;
                                padding: 20px;
                            }
                            #receipt-content {
                                box-shadow: none;
                                border: none;
                            }
                        }

                        @media print {
                            .page-break { 
                                page-break-before: always; 
                            }
                        }
                    </style>
                </head>
                <body>

                    <?php if(!empty($row)): ?>
                        <?php
                            $denos = $denos ?? [];
                            if (!is_iterable($denos)) {
                                $denos = [];
                            }

                            $receivedDenosMap = [];
                            $changeDenosMap = [];
                            $receivedTotalCount = 0;
                            $receivedTotalVolume = 0.0;
                            $changeTotalCount = 0;
                            $changeTotalVolume = 0.0;

                            foreach($denos as $denoRow){
                                $denomination = (string)($denoRow->denomination ?? '');
                                $count = (int)($denoRow->count ?? 0);
                                $volume = (float)($denoRow->volume ?? 0);

                                if($denomination === '' || ($count === 0 && abs($volume) < 0.005)){
                                    continue;
                                }

                                $isChangeRow = ($count < 0 || $volume < 0);
                                $displayCount = abs($count);
                                $displayVolume = abs($volume);
                                $targetMap = $isChangeRow ? $changeDenosMap : $receivedDenosMap;

                                if(!isset($targetMap[$denomination])){
                                    $item = new stdClass();
                                    $item->denomination = $denomination;
                                    $item->count = 0;
                                    $item->volume = 0.0;
                                    $targetMap[$denomination] = $item;
                                }

                                $targetMap[$denomination]->count += $displayCount;
                                $targetMap[$denomination]->volume += $displayVolume;

                                if($isChangeRow){
                                    $changeTotalCount += $displayCount;
                                    $changeTotalVolume += $displayVolume;
                                    $changeDenosMap = $targetMap;
                                }else{
                                    $receivedTotalCount += $displayCount;
                                    $receivedTotalVolume += $displayVolume;
                                    $receivedDenosMap = $targetMap;
                                }
                            }

                            $sortDenominationRows = function($left, $right){
                                return (float)($right->denomination ?? 0) <=> (float)($left->denomination ?? 0);
                            };

                            $receivedDenos = array_values($receivedDenosMap);
                            $changeDenos = array_values($changeDenosMap);
                            usort($receivedDenos, $sortDenominationRows);
                            usort($changeDenos, $sortDenominationRows);

                            $hasChangeGiven = !empty($changeDenos) && $changeTotalVolume > 0;
                        ?>
                        <div id="receipt-content" class="row col-10 col-lg-6 mx-auto shadow p-4 mb-5 rounded">
                    
                        <center>
                            <div class="badge">
                                <img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;;max-width:20px;max-height: 20px;object-fit: cover;"><br>
                                <strong style="font-size:15px; color: gray;"> <?=APP_NAME?></strong>
                            </div>
                            <h4 class="text-center" style="font-weight: bolder;">
                                <strong style="font-weight:bolder; font-size: 20px; font-weight:bolder;">Airtime & Bundles</strong> <br>
                            <b class="text-center text-warning"><?=$row->transaction_type?> <?=$row->network?></b><br>
                            </h4>
                        </center>

                        <div class="col col-md-12 table-responsive">
                            <table class="table table-striped table-hover border-bottom table-sm" style="height: fit-content !important;">
                                <thead>
                                    <tr><th colspan="2" class=" text-primary">TRANSACTION DETAILS</th></tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <th class="text-muted">Date: </th>
                                        <td><?=get_date_time($row->date_created)?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Transaction ID: </th>
                                        <td><?=esc($row->id)?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Type: </th>
                                        <td><?=esc($row->transaction_type)?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Network: </th>
                                        <td><?=esc($row->network)?></td>
                                    </tr>
                                    <?php if(!empty($row->category)):?>
                                    <tr>
                                        <th class="text-muted">Category: </th>
                                        <td><?=esc($row->category)?></td>
                                    </tr>
                                    <?php endif;?>
                                    <tr>
                                        <th class="text-muted">Customer's Number: </th>
                                        <td><?=esc($row->phone)?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Amount: </th>
                                        <td><?=number_format($row->amount, 2)?></td>
                                    </tr>
                                    <?php if($hasChangeGiven):?>
                                    <tr>
                                        <th class="text-muted">Change Given: </th>
                                        <td class="text-warning"><?=number_format($changeTotalVolume, 2)?></td>
                                    </tr>
                                    <?php endif;?>
                                    <?php if(!empty($row->data_size)):?>
                                    <tr>
                                        <th class="text-muted">Data Size: </th>
                                        <td><?=esc($row->data_size)?></td>
                                    </tr>
                                    <?php endif;?>
                                    <?php if(!empty($row->commission)):?>
                                    <tr>
                                        <th class="text-muted">Commission: </th>
                                        <td><?=number_format($row->commission, 2)?></td>
                                    </tr>
                                    <?php endif;?>
                                    <?php if(!empty($row->balance_after)):?>
                                    <tr>
                                        <th class="text-muted">Balance After: </th>
                                        <td><?=number_format($row->balance_after, 2)?></td>
                                    </tr>
                                    <?php endif;?>
                                    <?php if(!empty($row->wallet_in_id)):?>
                                    <tr>
                                        <th class="text-muted">Receiving wallet: </th>                      
                                        <td><?=esc($row->in_wallet_name ?? '')?> (<?=esc($row->in_wallet_network ?? '')?>)</td>
                                    </tr>
                                    <?php endif;?>
                                    <?php if(!empty($row->wallet_out_id)):?>
                                    <tr>
                                        <th class="text-muted">Paying wallet: </th>                     
                                        <td><?=esc($row->out_wallet_name ?? '')?> (<?=esc($row->out_wallet_network ?? '')?>)</td>
                                    </tr>
                                    <?php endif;?>
                                    <tr><th colspan="2" class=" text-primary">OTHER DETAILS: </th></tr>
                                    <?php if(!empty($row->receiver)):?>
                                    <tr>
                                        <th class="text-muted">Receiver's phone: </th>
                                        <td><?=esc($row->receiver)?></td>
                                    </tr>
                                    <?php endif;?>          
                                    <?php if(!empty($row->reseller_number)):?>
                                    <tr>
                                        <th class="text-muted">Reseller's Number: </th>
                                        <td><?=esc($row->reseller_number)?></td>
                                    </tr>
                                    <?php endif;?>          
                                    <?php if(!empty($row->transaction_status)): ?>
                                    <tr>
                                        <?php
                                                // Determine badge class based on transaction status
                                                $badgeClass = '';
                                                switch (strtolower($row->transaction_status)) {
                                                    case 'pending':
                                                        $badgeClass = 'badge text-warning';
                                                        break;
                                                    case 'sent':
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
                                        <th class="text-muted">Delivery Status: </th>
                                        <td>
                                            <span class="<?= $badgeClass ?>"><?=esc($row->transaction_status ?? 'N/A')?></span>
                                        </td>
                                    </tr>
                                    <?php endif;?>
                                    <tr class="wrap">
                                        <th class="text-muted">Created by:</th>
                                        <td>
                                            <span><img src="<?=get_image($row->user_image)?>" style="width: 20px; height: 20px; object-fit: cover; border-radius: 50%; margin: 0px;"></span> 
                                            <?=$row->user_first_name . ' ' . $row->user_last_name ?>
                                        </td>
                                    </tr>
                                    <?php if(!empty($row->assigned)):?>
                                        <tr class="wrap">
                                            <th class="text-muted">Assigned to:</th>
                                            <td>
                                                <span><img src="<?=get_image($row->assigned_image)?>" style="width: 20px; height: 20px; object-fit: cover; border-radius: 50%; margin: 0px;"></span> 
                                                <?=$row->assigned_first_name . ' ' . $row->assigned_last_name ?>
                                            </td>
                                        </tr>
                                    <?php endif;?>
                                    <?php if(!empty($row->date_updated)):?>
                                        <tr class="wrap">
                                            <th class="text-muted">Last updated:</th>
                                            <td><?=get_date_time($row->date_updated)?></td>
                                        </tr>
                                        <tr class="wrap">
                                            <th class="text-muted">Last updated by:</th>
                                            <td>
                                                <span><img src="<?=get_image($row->updated_by_image)?>" style="width: 20px; height: 20px; object-fit: cover; border-radius: 50%; margin: 0px;"></span> 
                                                <?=$row->updated_by_first_name . ' ' . $row->updated_by_last_name ?>
                                            </td>
                                        </tr>
                                    <?php endif;?>
                                     <?php if (!empty($receivedDenos) || !empty($changeDenos)): ?>
                                        <tr>
                                            <th colspan="2" class="text-primary">Denominations Breakdown</th>
                                        </tr>
                                        <tr>
                                            <td colspan="2">
                                                <div class="accordion accordion-flush" id="denominationsAccordion">
                                                    <div class="accordion-item">
                                                        <div class="accordion-body">
                                                            <?php if(!empty($receivedDenos)):?>
                                                            <div class="text-primary" style="font-weight:600; margin-bottom:8px;"><?=$hasChangeGiven ? 'Received Denominations' : 'Denominations Breakdown'?></div>
                                                            <table class="table table-bordered table-hover table-sm">
                                                                <thead>
                                                                    <tr>
                                                                        <th>DENOMINATION</th>
                                                                        <th>COUNT</th>
                                                                        <th>VOLUME</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($receivedDenos as $deno): ?>
                                                                    <tr>
                                                                        <td>GH&cent;<?=esc($deno->denomination ?? '')?></td>
                                                                        <td><?=number_format($deno->count ?? 0)?></td>
                                                                        <td><?=number_format($deno->volume ?? 0, 2)?></td>
                                                                    </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                                <tfoot>
                                                                    <tr class="text-primary">
                                                                        <th>SUMMARY</th>
                                                                        <th><?=number_format($receivedTotalCount ?? 0)?></th>
                                                                        <th><?=number_format($receivedTotalVolume ?? 0, 2)?></th>
                                                                    </tr>
                                                                </tfoot>
                                                            </table>
                                                            <?php endif;?>
                                                            <?php if($hasChangeGiven):?>
                                                            <div class="text-warning" style="font-weight:600; margin-top:12px; margin-bottom:8px;">Change Given</div>
                                                            <table class="table table-bordered table-hover table-sm">
                                                                <thead>
                                                                    <tr>
                                                                        <th>DENOMINATION</th>
                                                                        <th>COUNT</th>
                                                                        <th>VOLUME</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($changeDenos as $deno): ?>
                                                                    <tr>
                                                                        <td>GH&cent;<?=esc($deno->denomination ?? '')?></td>
                                                                        <td><?=number_format($deno->count ?? 0)?></td>
                                                                        <td><?=number_format($deno->volume ?? 0, 2)?></td>
                                                                    </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                                <tfoot>
                                                                    <tr class="text-warning">
                                                                        <th>CHANGE TOTAL</th>
                                                                        <th><?=number_format($changeTotalCount ?? 0)?></th>
                                                                        <th><?=number_format($changeTotalVolume ?? 0, 2)?></th>
                                                                    </tr>
                                                                </tfoot>
                                                            </table>
                                                            <?php endif;?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                     <?php elseif(!empty($row->amount_paid) || !empty($row->amount)): ?>
                                        <tr>
                                            <th colspan="2" class="text-primary">Denominations Breakdown</th>
                                        </tr>
                                        <tr>
                                            <td colspan="2" class="text-muted">No denomination records were saved for this transaction.</td>
                                        </tr>
                                     <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                     <?php endif;?>
            
                    <!-- Inline footer -->
                    <?php 
                        // Get current date and time
                        $currentDateTime = date('Y-m-d H:i:s');  // Format: YYYY-MM-DD HH:MM:SS
                    ?>
                    <div style="margin-top: 5px; text-align: center; font-size: 10px;">
                        <div style='text-align: center; font-size: 10px; opacity: 5;'>
                            <span style='padding:5px; font-weight:450px; ' class="text-warning">
                                <?=APP_NAME ?? ''?>
                            </span><br>
                            <span>Printed on: <?= $currentDateTime ?></span>
                        </div>
                    </div>
                </body>
                </html>
        <?php
        return ob_get_clean();
    }

    // Generate the HTML content
    $htmlContent = generatePrintContent($row);

    try {
        $mpdf = new \Mpdf\Mpdf([
            'tempDir' => __DIR__ . '/tmp',
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_top' => 15,
            'margin_bottom' => 15,
            'margin_header' => 5,
            'margin_footer' => 5
        ]);
        
        // Set dynamic filename
        $transaction_type = preg_replace('/[^a-zA-Z0-9]/', '_', $row->transaction_type);
        $network = preg_replace('/[^a-zA-Z0-9]/', '_', $row->network);
        $date = date('Y-m-d_His');
        $file_name = "{$transaction_type}_{$network}_{$date}.pdf";

        // Generate PDF
        $mpdf->WriteHTML($htmlContent);
        
        // Clear output buffers completely
        while (ob_get_level()) {
            ob_end_clean();
        }
        // Output PDF directly
        $mpdf->Output($file_name, \Mpdf\Output\Destination::INLINE);

    } catch (\Mpdf\MpdfException $e) {
        // Handle the exception (e.g., log it or display an error message)
        error_log('mPDF Error: ' . $e->getMessage());
        echo 'Error generating PDF: ' . $e->getMessage();
    }
    exit();
}
?>

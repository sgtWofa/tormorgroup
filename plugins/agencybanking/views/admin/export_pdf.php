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
                            $db = new \Core\Database;
                            $query = "SELECT * FROM denos WHERE trans_id = :trans_id GROUP BY denomination ORDER BY denomination DESC";
                            $denos = $db->query($query,['trans_id'=>$row->id]) ?? [];

                            $query = "SELECT SUM(count) AS total_count, SUM(volume) AS total_volume FROM denos WHERE trans_id =:trans_id";
                            $denosTotal = $db->query($query, ['trans_id'=>$row->id]);
                            if (!empty($denosTotal)) {
                                $denosTotal = $denosTotal[0];
                                $totalCount = $denosTotal->total_count ?? 0;
                                $totalVolume = $denosTotal->total_volume ?? 0;
                            }
                        ?>
                        <div id="receipt-content" class="row col-10 col-lg-6 mx-auto shadow p-4 mb-5 rounded">
                    
                        <center>
                            <div class="badge">
                                <img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;;max-width:20px;max-height: 20px;object-fit: cover;"><br>
                                <strong style="font-size:15px; color: gray;"> <?=APP_NAME?></strong>
                            </div>
                            <h4 class="text-center" style="font-weight: bolder;">
                                <strong style="font-weight:bolder; font-size: 20px; font-weight:bolder;">Agency Banking</strong> <br>
                            <b class="text-center text-warning"><?=$row->transaction_type?></b><br>
                            <small class="text-center"><?=$row->network?></small>
                            </h4>
                        </center>

                        <div class="col col-md-12 table-responsive">
                            <table class="table border-bottom table-sm" style="height: fit-content !important;">
                                <thead>
                                    <tr><th colspan="2" class="text-warning">TRANSACTION DETAILS</th></tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <th class="text-muted">Date (Time): </th>
                                        <td><?=get_date_time($row->date_created)?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Transaction ID: </th>
                                        <td><?=esc($row->id)?></td>
                                    </tr>

                                    <tr>
                                        <th class="text-muted">Type: </th>
                                        <td><?=esc($row->transaction_type)?></td>
                                    </tr>                       <tr>
                                        <th class="text-muted">Source: </th>
                                        <td><?=esc($row->network)?></td>
                                    </tr>
                                    <?php if(!empty($row->category)): ?>
                                    <tr>
                                        <th class="text-muted">Category</th>
                                        <td><?=esc($row->category)?></td>
                                    </tr>
                                    <?php endif;?>          
                                    <?php if(!empty($row->full_name)): ?>
                                    <tr>
                                        <th class="text-muted">Full name: </th>
                                        <td><?=esc($row->full_name)?></td>
                                    </tr>
                                    <?php endif;?>      
                                    <?php if($row->transaction_type === 'Account opening'): ?>
                                    <tr>
                                        <th class="text-muted">DoB: </th>
                                        <td><?=get_date($row->dob)?></td>
                                    </tr>
                                    <?php endif;?>
                                    <tr>
                                        <th class="text-muted">Phone: </th>
                                        <td><?=esc($row->phone)?></td>
                                    </tr>       
                                    <?php if(!empty($row->other_phone)): ?>
                                    <tr>
                                        <th class="text-muted">3rd Party's Number: </th>
                                        <td><?=esc($row->other_phone)?></td>
                                    </tr>
                                    <?php endif;?>  
                                    <?php if(!empty($row->house_number)): ?>
                                    <tr>
                                        <th class="text-muted">House Number: </th>
                                        <td><?=esc($row->house_number)?></td>
                                    </tr>
                                    <?php endif;?>      
                                    <?php if(!empty($row->nearest_landmark)): ?>
                                    <tr>
                                        <th class="text-muted">Nearest landmark: </th>
                                        <td><?=esc($row->nearest_landmark)?></td>
                                    </tr>
                                    <?php endif;?>                  
                                    <?php if(!empty($row->account_number)): ?>
                                    <tr>
                                        <th class="text-muted">Account #: </th>
                                        <td><?=esc($row->account_number)?></td>
                                    </tr>
                                    <?php endif;?>                  
                                    <?php if(!empty($row->destination_bank)): ?>
                                    <tr>
                                        <th class="text-muted">Destination bank: </th>
                                        <td><?=esc($row->destination_bank)?></td>
                                    </tr>
                                    <?php endif;?>              
                                    <?php if(!empty($row->payment_medium)): ?>
                                    <tr>
                                        <th class="text-muted">Medium Used: </th>
                                        <td><?=esc($row->payment_medium)?></td>
                                    </tr>
                                    <?php endif;?>          
                                    <?php if(!empty($row->source_bank)): ?>
                                    <tr>
                                        <th class="text-muted">Source bank: </th>
                                        <td><?=esc($row->source_bank)?></td>
                                    </tr>
                                    <?php endif;?>          
                                    <?php if(!empty($row->sender_phone)): ?>
                                    <tr>
                                        <th class="text-muted">Sender's phone: </th>
                                        <td><?=esc($row->sender_phone)?></td>
                                    </tr>
                                    <?php endif;?>
                                    <?php if(!empty($row->balance_before)): ?>
                                    <tr>
                                        <th class="text-muted">Balance before: </th>
                                        <td><?=number_format($row->balance_before, 2)?></td>
                                    </tr>
                                    <?php endif;?>                  
                                    <?php if(!empty($row->amount_withdrawn)): ?>
                                    <tr>
                                        <th class="text-muted">Amount Requested</th>
                                        <td><?=number_format($row->amount_withdrawn, 2)?></td>
                                    </tr>
                                    <?php endif;?>              
                                    <?php if(!empty($row->amount_payable)): ?>
                                    <tr>
                                        <th class="text-muted">Amount After Charge</th>
                                        <td><?=number_format($row->amount_payable, 2)?></td>
                                    </tr>
                                    <?php endif;?>              
                                    <?php if(!empty($row->amount)): ?>
                                    <tr>
                                        <th class="text-muted">Amount Paid</th>
                                        <td><?=number_format($row->amount, 2)?></td>
                                    </tr>
                                    <?php endif;?>
                                    <?php if(!empty($row->charge)): ?>
                                    <tr>
                                        <th class="text-muted">Charges</th>
                                        <td><?=number_format($row->charge, 2)?></td>
                                    </tr>
                                    <?php endif;?>          
                                    <?php if(!empty($row->balance)): ?>
                                    <tr>
                                        <th class="text-muted">Balance : </th>
                                        <th><?=number_format($row->balance, 2)?></th>
                                    </tr>
                                    <?php endif;?>
                                    <tr><th colspan="2" class="text-warning">OTHER DETAILS</th></tr>           
                                    <?php if(!empty($row->id_type)): ?>
                                    <tr>
                                        <th class="text-muted"><?=$row->id_type?></th>
                                        <td><?=esc($row->id_number ?? 'ID number')?></td>
                                    </tr>
                                    <?php endif;?>              
                                    <?php if(!empty($row->next_of_king)): ?>
                                    <tr>
                                        <th class="text-muted">NoK: </th>
                                        <td><?=esc($row->next_of_king)?></td>
                                    </tr>
                                    <?php endif;?>                  
                                    <?php if(!empty($row->nok_phone)): ?>
                                    <tr>
                                        <th class="text-muted">NoK's phone: </th>
                                        <td><?=esc($row->nok_phone)?></td>
                                    </tr>
                                    <?php endif;?>          
                                    <?php if(!empty($row->nok_relation)): ?>
                                    <tr>
                                        <th class="text-muted">NoK's relation: </th>
                                        <td><?=esc($row->nok_relation)?></td>
                                    </tr>
                                    <?php endif;?>
                                    <?php if(!empty($row->ussd)): ?>
                                    <tr>
                                        <th class="text-muted">USSD used: </th>
                                        <td><?=esc($row->ussd)?></td>
                                    </tr>
                                    <?php endif;?>          
                                    <?php if(!empty($row->cash_source)): ?>
                                    <tr>
                                        <th class="text-muted">SoF: </th>
                                        <td><?=esc($row->cash_source)?></td>
                                    </tr>
                                    <?php endif;?>          
                                    <?php if(!empty($row->sender_phone)): ?>
                                    <tr>
                                        <th class="text-muted">Sender's phone: </th>
                                        <td><?=esc($row->sender_phone)?></td>
                                    </tr>
                                    <?php endif;?>
                                    <?php if(!empty($row->receiver_phone)): ?>
                                    <tr>
                                        <th class="text-muted">Receiver's phone</th>
                                        <td><?=esc($row->receiver_phone)?></td>
                                    </tr>
                                    <?php endif;?>          
                                    <?php if(!empty($row->transaction_status)): ?>
                                    <tr>
                                        <?php
                                            // Determine badge class based on transaction status
                                            $badgeClass = '';
                                            switch (strtolower($row->transaction_status)) {
                                                case 'pending':
                                                    $badgeClass = 'badge text-warning fw-bolder';
                                                    break;
                                                case 'approved':
                                                    $badgeClass = 'badge text-success fw-bolder';
                                                    break;
                                                case 'rejected':
                                                    $badgeClass = 'badge text-danger fw-bolder';
                                                    break;
                                                default:
                                                    $badgeClass = 'badge text-secondary fw-bolder';
                                                    break;
                                            }
                                        ?>
                                    <th class="text-muted">Status: </th>
                                    <td>
                                        <span class="<?= $badgeClass ?>"><?=esc($row->transaction_status)?></span>
                                    </td>
                                </tr>
                                <?php endif;?>
                                <tr class="wrap">
                                    <th class="text-muted">Created by:</th>
                                    <td>
                                        <?=$row->user_first_name . ' ' . $row->user_last_name ?>
                                        <span class="badge" style="font-size: 11px; font-weight:bolder;"><?=get_date_time($row->date_created)?></span>
                                    </td>
                                </tr>
                                <?php if(!empty($row->date_updated)):?>
                                <tr class="wrap">
                                    <th class="text-muted">Last updated:</th>
                                    <td>
                                        <?=$row->updated_by_first_name . ' ' . $row->updated_by_last_name ?>
                                        <span class="badge" style="font-size: 11px; font-weight:bolder;"><?=get_date_time($row->date_updated)?></span>
                                    </td>
                                </tr>
                                <?php endif;?>
                                     <?php if (!empty($denos)): ?>
                                        <tr>
                                            <th colspan="2" class="text-secondary">Denominations Breakdown</th>
                                        </tr>
                                        <tr>
                                            <td colspan="2">
                                                <div class="accordion accordion-flush" id="denominationsAccordion">
                                                    <div class="accordion-item">
                                                        <div class="accordion-body">
                                                            <table class="table table-bordered table-sm">
                                                                <thead>
                                                                    <tr>
                                                                        <th>DENOMINATION</th>
                                                                        <th>COUNT</th>
                                                                        <th>VOLUME</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($denos as $deno): ?>
                                                                    <tr class="">
                                                                        <td class="">GH&cent;<?=esc($deno->denomination ?? '')?></td>
                                                                        <td class=""><?=number_format($deno->count ?? 0)?></td>
                                                                        <td class=""><?=number_format($deno->volume ?? 0, 2)?></td>
                                                                    </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                                <tfoot>
                                                                    <tr class="text-secondary">
                                                                        <th>SUMMARY</th>
                                                                        <th><?=number_format($totalCount ?? 0)?></th>
                                                                        <th><?=number_format($totalVolume ?? 0, 2)?></th>
                                                                    </tr>
                                                                </tfoot>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
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

<?php
// Export Cashdrawer to HTML for printing as PDF
if (!empty($row)) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Cashdrawer Statement</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            html, body { 
                font-family: Arial, sans-serif; 
                font-size: 12px; 
                color: #000 !important;
                background-color: #fff !important;
            }
            .container { 
                max-width: 900px; 
                margin: 0 auto; 
                padding: 20px;
                background-color: #fff !important;
                color: #000 !important;
            }
            .header { 
                text-align: center; 
                margin-bottom: 20px; 
                border-bottom: 2px solid #000; 
                padding-bottom: 10px;
                background-color: #fff !important;
                color: #000 !important;
            }
            .header h1 { 
                font-size: 18px; 
                margin-bottom: 5px;
                color: #000 !important;
            }
            .header p {
                color: #000 !important;
            }
            .section-title { 
                font-size: 14px; 
                font-weight: bold; 
                margin-top: 20px; 
                margin-bottom: 10px; 
                background-color: #f0f0f0 !important; 
                padding: 8px; 
                border-left: 4px solid #000;
                color: #000 !important;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-bottom: 15px;
                background-color: #fff !important;
            }
            table th { 
                background-color: #333 !important; 
                color: #fff !important; 
                padding: 10px; 
                text-align: left; 
                font-weight: bold;
                border: 1px solid #000;
            }
            table td { 
                padding: 8px; 
                border-bottom: 1px solid #ddd;
                background-color: #fff !important;
                color: #000 !important;
            }
            table tr:nth-child(even) { 
                background-color: #f9f9f9 !important;
            }
            table tr.total { 
                background-color: #e0e0e0 !important; 
                font-weight: bold;
                color: #000 !important;
            }
            .text-right { text-align: right; }
            .text-center { text-align: center; }
            @media print { 
                * { background: white !important; color: black !important; }
                body { margin: 0; padding: 0; background: white !important; } 
                .container { max-width: 100%; background: white !important; }
                table { background: white !important; }
                table th { background-color: #333 !important; color: white !important; }
                table td { background-color: white !important; color: black !important; }
            }
        </style>
        <script>
            window.onload = function() { setTimeout(function() { window.print(); }, 500); };
        </script>
    </head>
    <body style="background-color: #fff !important; color: #000 !important;">
        <div class="container">
            <div class="header">
                <h1><?= strtoupper($row->user_first_name . "'s CASH DRAWER") ?></h1>
                <p>Statement Date: <?= date('Y-m-d H:i:s') ?></p>
            </div>

            <div class="section-title">CASHDRAWER DETAILS</div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 10%;">#</th>
                        <th style="width: 30%;">Denomination</th>
                        <th style="width: 30%;">Count</th>
                        <th style="width: 30%;">Volume</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rows)): $id = 1; ?>
                        <?php foreach ($rows as $rRow): ?>
                            <tr>
                                <td class="text-center"><?= $id++ ?></td>
                                <td class="text-center">GHS <?= number_format($rRow->denomination, 2) ?></td>
                                <td class="text-center"><?= esc($rRow->count ?? '') ?></td>
                                <td class="text-center">GHS <?= number_format($rRow->volume ?? 0, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center">No records to show</td></tr>
                    <?php endif; ?>
                    <tr class="total">
                        <td colspan="2" class="text-center">TOTAL</td>
                        <td class="text-center"><?= number_format($total_count ?? 0) ?></td>
                        <td class="text-center">GHS <?= number_format($total_volume ?? 0, 2) ?></td>
                    </tr>
                </tbody>
            </table>

            <?php if (!empty($cash_channels)): ?>
                <div class="section-title">CASH CHANNELS</div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 25%;">Channel</th>
                            <th style="width: 18%;" class="text-right">Opening</th>
                            <th style="width: 18%;" class="text-right">Credit</th>
                            <th style="width: 18%;" class="text-right">Debit</th>
                            <th style="width: 21%;" class="text-right">Closing</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cash_channels as $ch): ?>
                            <tr>
                                <td><?= $ch->channel_key ?></td>
                                <td class="text-right">GHS <?= number_format($ch->opening_balance, 2) ?></td>
                                <td class="text-right">GHS <?= number_format($ch->credit, 2) ?></td>
                                <td class="text-right">GHS <?= number_format($ch->debit, 2) ?></td>
                                <td class="text-right"><strong>GHS <?= number_format($ch->closing_balance, 2) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if (!empty($ecash_channels)): ?>
                <div class="section-title">E-CASH CHANNELS</div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 25%;">Channel</th>
                            <th style="width: 18%;" class="text-right">Opening</th>
                            <th style="width: 18%;" class="text-right">Credit</th>
                            <th style="width: 18%;" class="text-right">Debit</th>
                            <th style="width: 21%;" class="text-right">Closing</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ecash_channels as $ch): ?>
                            <tr>
                                <td><?= esc(str_replace('_', ' ', $ch->channel_key)) ?></td>
                                <td class="text-right">GHS <?= number_format($ch->opening_balance, 2) ?></td>
                                <td class="text-right">GHS <?= number_format($ch->credit, 2) ?></td>
                                <td class="text-right">GHS <?= number_format($ch->debit, 2) ?></td>
                                <td class="text-right"><strong>GHS <?= number_format($ch->closing_balance, 2) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
} else {
    echo "Cashdrawer not found!";
}
?>

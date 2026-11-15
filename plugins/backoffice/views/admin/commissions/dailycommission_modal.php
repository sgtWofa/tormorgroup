<!-- Calculator modal -->
<div class="modal fade" id="staticBackdrop<?=$row->id?>" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel<?=$row->id?>" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header w-100">
                <center class="mx-auto">
	                	<div class="badge">
											<strong style="font-size:15px; color: gray;"> <?=APP_NAME?></strong>
										</div>
                    <h3 class="modal-title" id="staticBackdropLabel">STATEMENT OF ACCOUNTS</h3>
                    <h5><?= esc($row->assigned_first_name ?? '') ?> <?= esc($row->assigned_last_name ?? '') ?></h5>
                    <p><?= get_date_time(date("Y-m-d H:i:s")) ?></p>
                </center>
            </div>

            <!-- Modal Body -->
            <div class="modal-body">
                <div class="row g-3 mx-auto rounded">
                    <div class="">
                        <table class="table table-hover table-bordeless table-sm">
                            <thead>
                                <tr>
                                    <th>WALLET NAME</th>
                                    <th>RECEIPT</th>
                                    <th>PAYMENT</th>
                                    <th>BALANCE</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mywallets as $wallet): ?>
                                    <tr>
    								    <td class="px-1">
    								        <?= esc($wallet->network) ?>
    								    </td>
    								    <td style="text-align:right;" class="text-success"><?= number_format($wallet->credit, 2) ?></td>
    								    <td style="text-align:right;" class="text-danger"><?= number_format($wallet->debit, 2) ?></td>
    								    <td style="text-align:right;" class="<?= $wallet->balance > 0 ? 'text-success' : 'text-danger' ?>">
    								        <?= number_format($wallet->balance, 2) ?>
    								    </td>
    								</tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th class="px-1">TOTAL</th>
                                    <th style="text-align:right;" class="text-success"><?= number_format($sum->total_credit ?? 0, 2) ?></th>
                                    <th style="text-align:right;" class="text-success"><?= number_format($sum->total_debit ?? 0, 2) ?></th>
                                    <th style="text-align:right;" class="<?= $sum->total_balance > 0 ? 'text-success' : 'text-danger' ?>"><?= number_format($sum->total_balance ?? 0, 2) ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="modal-footer no-print">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button id="printModal<?=$row->id?>" class="btn btn-sm btn-outline-secondary col-2" onclick="printModalContent('staticBackdrop<?=$row->id?>')">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>
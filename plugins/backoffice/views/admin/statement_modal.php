<?php
	// Request tab query data
	$mywallets = $mywallet->where(['assigned' => $row->assigned]);

	$query = "SELECT SUM(debit) AS total_debit, SUM(credit) AS total_credit, SUM(balance) AS total_balance FROM mywallets WHERE assigned = :assigned";
	$sum = $mywallet->query($query, ['assigned' => $row->assigned])[0];

?>

<!-- Calculator modal -->
<div class="modal fade" id="staticBackdrop<?= $row->id ?>" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel<?= $row->id ?>" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header">
                <div class="text-center w-100">
                    <div class="badge">
                        <strong style="font-size:15px; color: gray;"> <?= APP_NAME ?></strong>
                    </div>
                    <h3 class="modal-title" id="staticBackdropLabel">STATEMENT OF ACCOUNTS</h3>
                    <h5><?= esc($row->assigned_first_name ?? '') ?> <?= esc($row->assigned_last_name ?? '') ?></h5>
                    <p><?= get_date_time(date("Y-m-d H:i:s")) ?></p>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="modal-body">
                <?php if (!empty($mywallets)): ?>
                    <!-- Responsive scrollable table -->
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-hover table-bordered table-sm print-table">
                            <thead class="table-light">
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
                                            <?= esc($wallet->name) ?> (<?= esc($wallet->wallet_type) ?> <?= esc($wallet->network) ?>)
                                        </td>
                                        <td class="text-end text-success"><?= number_format($wallet->credit, 2) ?></td>
                                        <td class="text-end text-danger"><?= number_format($wallet->debit, 2) ?></td>
                                        <td class="text-end <?= $wallet->balance > 0 ? 'text-success' : 'text-danger' ?>">
                                            <?= number_format($wallet->balance, 2) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th class="px-1">TOTAL</th>
                                    <th class="text-end text-success"><?= number_format($sum->total_credit ?? 0, 2) ?></th>
                                    <th class="text-end text-danger"><?= number_format($sum->total_debit ?? 0, 2) ?></th>
                                    <th class="text-end <?= $sum->total_balance > 0 ? 'text-success' : 'text-danger' ?>"><?= number_format($sum->total_balance ?? 0, 2) ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center">No records to show!</p>
                <?php endif; ?>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button id="printModal<?= $row->id ?>" class="btn btn-sm btn-outline-secondary" onclick="printModalContent('staticBackdrop<?= $row->id ?>')">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Print Styles -->
<style>
@media print {
    body * {
        visibility: hidden; /* Hide everything else */
    }

    #printableContent, #printableContent * {
        visibility: visible; /* Only show printable content */
    }

    #printableContent {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%; /* Full width for standard paper */
        padding: 10px; /* Add padding for readability */
    }

    .modal-footer {
        display: none; /* Hide footer in print */
    }

    .print-table {
        width: 100%; /* Use full width */
        border-collapse: collapse; /* Simplify borders */
    }

    .print-table th, .print-table td {
        padding: 5px; /* Add padding for readability */
        text-align: left; /* Align text */
        border: 1px solid black; /* Add borders */
    }

    .print-table th {
        font-weight: bold;
        background-color: #f1f1f1; /* Gray header */
    }
}

/* For smaller paper widths (like receipts) */
@media print and (max-width: 80mm) {
    #printableContent {
        width: 80mm; /* Restrict width for receipt size */
        font-size: 12px; /* Smaller font for receipts */
        line-height: 1.2; /* Tighter spacing for clarity */
    }

    .print-table th, .print-table td {
        font-size: 11px; /* Adjust font size for narrow tables */
        padding: 2px; /* Compact padding */
    }

    .print-table th {
        background-color: #f9f9f9; /* Slight gray header */
    }
}
</style>

<!-- Print Script -->
<script type="text/javascript">
    function printModalContent(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) {
            console.error('Modal not found');
            return;
        }

        const modalContent = modal.querySelector('.modal-content').cloneNode(true);

        const printContainer = document.createElement('div');
        printContainer.id = 'printableContent';
        printContainer.style.position = 'absolute';
        printContainer.style.top = '0';
        printContainer.style.left = '0';
        printContainer.style.width = '100%';
        printContainer.appendChild(modalContent);

        document.body.appendChild(printContainer);
        window.print();
        document.body.removeChild(printContainer);
    }
</script>

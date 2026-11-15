
<div class="card col-lg-6 col-md-10 mx-auto" id="card">
    <div class="card-content">
        <!-- View Header -->
        <div class="card-header">
            <center class="mx-auto">
                <div class="badge">
                    <strong style="font-size:15px; color: gray;"><?= APP_NAME ?></strong>
                </div>
                <h3 class="card-title" id="staticBackdropLabel">COMMISSION</h3>
                <h5 class="text-center">
                    <?= ucfirst($row->network ?? 'N/A') ?>
                </h5>
                <p>
                    <?= esc($row->user_first_name ?? '') ?> <?= esc($row->user_last_name ?? '') ?><br>
                    <small style="font-size:12px;"><?= get_date_time($row->date_created ?? '') ?></small>
                </p>
				<div class="d-flex justify-content-center gap-2 mb-2">
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export?entity=commissions&resource=view&id=<?=$row->id?>&format=pdf" class="btn btn-outline-secondary btn-sm">Export PDF</a>
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export?entity=commissions&resource=view&id=<?=$row->id?>&format=xlsx" class="btn btn-outline-success btn-sm">Export Excel</a>
				</div>
            </center>
        </div>
        
        <!-- View Body -->
        <div class="card-body">
            <?php
                $denosByCurrency = [];
                $rawDenos = $row->denos_json ?? '';
                $decoded = [];
                if (is_string($rawDenos) && $rawDenos !== '') {
                    $decoded = json_decode($rawDenos, true);
                }
                if (is_array($decoded)) {
                    foreach ($decoded as $d) {
                        $cur = strtoupper(trim((string)($d['currency'] ?? 'GHS')));
                        if (!in_array($cur, ['GHS', 'CFA'], true)) {
                            $cur = 'GHS';
                        }
                        $denosByCurrency[$cur][] = $d;
                    }
                }
            ?>

            <?php if (!empty($breakdown)): ?>
                <div class="mb-3">
                    <h6 class="text-muted">Breakdown</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">#</th>
                                    <th style="width:300px;">Network</th>
                                    <th class="text-end">Amount</th>
                                    <th style="width: 120px;">Proof</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($breakdown as $index => $b): ?>
                                    <?php
                                        $snapshot = json_decode((string)($b->assignment_snapshot_json ?? ''), true);
                                        $snapshot = is_array($snapshot) ? $snapshot : [];
                                        $detailLabel = backoffice_commission_assignment_label($snapshot);
                                        $proofImage = trim((string)($b->proof_image ?? ''));
                                        $proofImageUrl = $proofImage !== '' ? get_image($proofImage) : '';
                                        $commissionLabel = trim((string)($snapshot['commission_label'] ?? ($b->commission_type ?? 'Commission')));
                                        $expectedDay = (int)($snapshot['expected_day'] ?? 0);
                                        $expectedLabels = [];
                                        foreach (backoffice_commission_day_fields() as $field => $label) {
                                            $days = (int)($snapshot[$field] ?? 0);
                                            if ($days > 0) {
                                                $simTypeForLabel = strtolower(trim((string)($snapshot['sim_type'] ?? '')));
                                                $displayLabel = backoffice_commission_day_payment_label($field, $label, $simTypeForLabel);
                                                $expectedLabels[] = $displayLabel . ': every ' . backoffice_commission_expected_interval_label($days);
                                            }
                                        }
                                    ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td>
                                            <div class="fw-semibold"><?= esc($b->network ?? ($snapshot['network'] ?? '')) ?></div>
                                            <div class="mt-1"><span class="badge bg-secondary text-white border"><?= esc($commissionLabel) ?></span></div>
                                            <?php if ($expectedDay > 0): ?>
                                                <div class="text-muted small">Collected every <?= esc(backoffice_commission_expected_interval_label($expectedDay)) ?></div>
                                            <?php elseif (!empty($expectedLabels)): ?>
                                                <div class="text-muted small"><?= esc(implode(' | ', $expectedLabels)) ?></div>
                                            <?php endif; ?>
                                            <?php if ($detailLabel !== ''): ?>
                                                <div class="text-muted small"><?= esc($detailLabel) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end"><?= number_format($b->amount ?? 0, 2) ?></td>
                                        <td>
                                            <?php if ($proofImageUrl !== ''): ?>
                                                <a href="<?=esc($proofImageUrl)?>" target="_blank" rel="noopener">
                                                    <img src="<?=esc($proofImageUrl)?>" alt="Proof image" style="width:60px;height:44px;object-fit:cover;border-radius:8px;border:1px solid rgba(0,0,0,0.08);">
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">No proof</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($denosByCurrency)): ?>
                <div class="mb-3">
                    <h6 class="text-muted">Cash Denominations</h6>
                    <?php foreach ($denosByCurrency as $cur => $rows): ?>
                        <div class="mb-2">
                            <strong><?= esc($cur) ?></strong>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Denomination</th>
                                            <th class="text-end">Count</th>
                                            <th class="text-end">Volume</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $subTotal = 0.0; ?>
                                        <?php foreach ($rows as $d): ?>
                                            <?php $subTotal += (float)($d['volume'] ?? 0); ?>
                                            <tr>
                                                <td><?= esc($d['denomination'] ?? '') ?></td>
                                                <td class="text-end"><?= esc($d['count'] ?? '') ?></td>
                                                <td class="text-end"><?= number_format((float)($d['volume'] ?? 0), ($cur === 'CFA') ? 0 : 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="2" class="text-end">Subtotal</th>
                                            <th class="text-end"><?= number_format($subTotal, ($cur === 'CFA') ? 0 : 2) ?></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <table class="table table-hover table-striped">
                <tbody>
                    <tr>
                        <td class="text-muted">Date Received:</td>
                        <td><?= get_date($row->date_received ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Date Submitted:</td>
                        <td><?= get_date_time($row->date_created ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Network:</td>
                        <td><?= esc($row->network ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Commission Type:</td>
                        <td><?= esc($row->commission_type ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Description:</td>
                        <td><?= esc($row->description ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Amount:</td>
                        <td><?= number_format($row->amount ?? 0, 2) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Created By:</td>
                        <td>
                            <img src="<?= get_image($row->user_image) ?>" style="width: 20px; height: 20px; object-fit: cover; border-radius: 50%;">
                            <?= esc($row->user_first_name . ' ' . $row->user_last_name) ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Assigned To:</td>
                        <td>
                            <img src="<?= get_image($row->assigned_image ?? $row->payto_image ?? '') ?>" style="width: 20px; height: 20px; object-fit: cover; border-radius: 50%;">
                            <?= esc(trim((string)($row->assigned_first_name ?? $row->payto_first_name ?? '') . ' ' . (string)($row->assigned_last_name ?? $row->payto_last_name ?? ''))) ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Status:</td>
                        <td>
                            <?php
                                $badgeClass = match (strtolower($row->transaction_status ?? '')) {
                                    'approved' => 'badge bg-success',
                                    'rejected' => 'badge bg-danger',
                                    'pending' => 'badge bg-warning text-dark',
                                    default => 'badge bg-secondary',
                                };
                            ?>
                            <span class="<?= $badgeClass ?>"><?= esc($row->transaction_status ?? '') ?></span>
                        </td>
                    </tr>
                    <?php if(trim((string)($row->reject_reason ?? '')) !== ''): ?>
                    <tr>
                        <td class="text-muted">Reject Reason:</td>
                        <td><?= esc($row->reject_reason) ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- View Footer -->
        <div class="card-footer no-print justify-content-between d-flex mb-5">
            <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
              <button type="button" class="btn btn-danger">
                <i class="fa-solid fa-chevron-left"></i> Exit
              </button>
            </a>
            <button id="printView<?= $row->id ?>" class="btn btn-sm btn-outline-secondary col-2" onclick="printViewContent('card')">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>
</div>

<script type="text/javascript">               
  function printViewContent(cardId) {
      // Get the card content by ID
      const card = document.getElementById(cardId);
      if (!card) {
          console.error('View not found');
          return;
      }

      // Clone the card
      const clonedView = card.cloneNode(true);

      // Remove the footer from the cloned card
      const footer = clonedView.querySelector('.card-footer');
      if (footer) {
          footer.remove();
      }

      // Create a new window for printing
      const printWindow = window.open('', '_blank');
      if (!printWindow) {
          console.error('Unable to open print window');
          return;
      }

      // Prepare the content for the print window
      printWindow.document.open();
      printWindow.document.write(`
          <html>
          <head>
              <title>Print View</title>
              <!-- Include Bootstrap CSS -->
              <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
              <!-- Include Font Awesome -->
              <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
              <style>
                  body { margin: 0; padding: 20px; font-family: Arial, sans-serif; background-color: white; }
                  .table th, .table td { border: 1px solid #000; }
              </style>
          </head>
          <body onload="window.print(); window.close();">
              ${clonedView.outerHTML}
          </body>
          </html>
      `);
      printWindow.document.close();
  }
</script>

<?php
	// Ensure $id is valid
	if (empty($id)) {
	    throw new Exception("Invalid ID provided.");
	}

	// Define query
	$query = "
	    SELECT 
	        m.*, 
	        u.first_name AS user_first_name, 
	        u.last_name AS user_last_name, 
	        u.image AS user_image,
	        u_updated.first_name AS updated_by_first_name, 
	        u_updated.last_name AS updated_by_last_name, 
	        u_updated.image AS updated_by_image,
	        u_assigned.first_name AS assigned_first_name, 
	        u_assigned.last_name AS assigned_last_name, 
	        u_assigned.image AS assigned_image
	    FROM requests AS m 
	    LEFT JOIN users AS u ON m.created_by = u.id 
	    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
	    LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id 
	    WHERE m.id = :id 
	    LIMIT 1";

	// Execute query
	$requestrow = $request->query($query, ['id' => $row->id]);
	if (empty($requestrow)) {
	    throw new Exception("No request found for the given ID.");
	}

	// Use the first result row
	$requestrow = $requestrow[0];
	$requestAssignedSnapshot = function_exists('backoffice_assignment_snapshot_lines')
		? backoffice_assignment_snapshot_lines((int)($requestrow->created_by ?? 0), $requestrow->network ?? '', $requestrow->request_type ?? '')
		: [];
	$denoPayload = [];
	$denoTotals = [];
	$verifiedDenoPayload = [];
	$verifiedDenoTotals = [];
	$approvalReceiptImageUrl = !empty($requestrow->approval_receipt_photo) ? get_image($requestrow->approval_receipt_photo) : '';
	if (!empty($requestrow->denos_json)) {
		$decoded = json_decode($requestrow->denos_json, true);
		if (is_array($decoded)) {
			foreach ($decoded as $entry) {
				$currency = strtoupper($entry['currency'] ?? 'GHS');
				$denomination = (float)($entry['denomination'] ?? 0);
				$count = (int)($entry['count'] ?? 0);
				$volume = (float)($entry['volume'] ?? 0);
				if ($count <= 0 && $volume <= 0) {
					continue;
				}
				$denoPayload[$currency][] = [
					'denomination' => $denomination,
					'count' => $count,
					'volume' => $volume,
				];
				$denoTotals[$currency] = ($denoTotals[$currency] ?? 0) + $volume;
			}
		}
	}
	if (!empty($requestrow->verified_denos_json)) {
		$decodedVerified = json_decode($requestrow->verified_denos_json, true);
		if (is_array($decodedVerified)) {
			foreach ($decodedVerified as $entry) {
				$currency = strtoupper($entry['currency'] ?? 'GHS');
				$denomination = (float)($entry['denomination'] ?? 0);
				$count = (int)($entry['count'] ?? 0);
				$volume = (float)($entry['volume'] ?? 0);
				if ($count <= 0 && $volume <= 0) {
					continue;
				}
				$verifiedDenoPayload[$currency][] = [
					'denomination' => $denomination,
					'count' => $count,
					'volume' => $volume,
				];
				$verifiedDenoTotals[$currency] = ($verifiedDenoTotals[$currency] ?? 0) + $volume;
			}
		}
	}
?>

<div class="card col-lg-6 col-md-10 mx-auto" id="card">
    <div class="card-content">
        <!-- View Header -->
        <div class="card-header w-100">
            <center class="mx-auto">
                <div class="d-flex flex-column align-items-center gap-2">
                    <img src="<?=APP_LOGO2 ?? ''?>" class="img-fluid" width="48" height="48" alt="<?=APP_NAME?> logo">
                    <strong class="text-muted text-uppercase small"><?=APP_NAME?></strong>
                </div>
                <h4><?=strtoupper($row->network)?> REQUEST</h4>
                <h5><?=esc($requestrow->user_first_name ?? '')?> <?=esc($requestrow->user_last_name ?? '')?></h5>
                <p><?=get_date_time($requestrow->date_created ?? '')?></p>
				<div class="d-flex justify-content-center gap-2">
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export?entity=requests&resource=view&id=<?=$row->id?>&format=pdf" class="btn btn-outline-secondary btn-sm">Export PDF</a>
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export?entity=requests&resource=view&id=<?=$row->id?>&format=xlsx" class="btn btn-outline-success btn-sm">Export Excel</a>
				</div>
            </center>
        </div>

        <!-- View Body -->
        <div class="card-body">
            <?php if (!empty($requestrow)): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-body-secondary">
                        <h6 class="mb-0 text-uppercase text-muted">Request snapshot</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 align-middle text-start">
                                <tbody class="fw-semibold">
                                    <tr>
                                        <th class="text-muted text-uppercase small">Amount</th>
                                        <td><?=number_format($row->amount ?? 0, 2)?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted text-uppercase small">Status</th>
                                        <td>
                                            <?php
                                                $badgeClass = match (strtolower($row->transaction_status ?? '')) {
                                                    'approved' => 'bg-success text-white',
                                                    'rejected' => 'bg-danger text-white',
                                                    'pending' => 'bg-warning text-dark',
                                                    default => 'bg-secondary text-white',
                                                };
                                            ?>
                                            <span class="badge <?=$badgeClass?> text-uppercase fw-semibold"><?=esc($row->transaction_status ?? 'Pending')?></span>
                                            <?php if (strtolower($row->transaction_status ?? '') === 'rejected' && !empty($row->reject_reason)): ?>
                                                <span class="ms-2 small text-danger fst-italic">Reason: <?=esc($row->reject_reason)?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted text-uppercase small">Network</th>
                                        <td><?=esc($row->network)?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted text-uppercase small">Type</th>
                                        <td><?=esc($row->request_type)?></td>
                                    </tr>
                                    <?php if(!empty($requestAssignedSnapshot)): ?>
                                        <tr>
                                            <th class="text-muted text-uppercase small">Assigned SIM / Wallet</th>
                                            <td>
                                                <?php if(!empty($requestAssignedSnapshot['missing'])): ?>
                                                    <span class="text-warning fw-semibold"><?=esc($requestAssignedSnapshot['message'] ?? 'No assigned SIM/wallet details found.')?></span>
                                                <?php else: ?>
                                                    <?php foreach(($requestAssignedSnapshot['lines'] ?? []) as $line): ?>
                                                        <div>
                                                            <span class="text-muted"><?=esc($line['label'] ?? '')?>:</span>
                                                            <?php if(!empty($line['missing'])): ?>
                                                                <span class="text-warning fw-semibold">Not saved</span>
                                                            <?php else: ?>
                                                                <span class="fw-semibold"><?=esc($line['value'] ?? '')?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-body-secondary">
                        <h6 class="mb-0 text-uppercase text-muted">Request details</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 align-middle text-start">
                                <tbody>
                            <tr>
                                <th scope="row">Network</th>
                                <td><?=esc($row->network)?></td>
                            </tr>
                            <tr>
                                <th scope="row">Request type</th>
                                <td><?=esc($row->request_type)?></td>
                            </tr>
                            <tr>
                                <th scope="row">Till / Code</th>
                                <td><?=esc($row->till_number ?? '—')?></td>
                            </tr>
                            <tr>
                                <th scope="row">Amount</th>
                                <td><?=number_format($row->amount ?? 0, 2)?></td>
                            </tr>
                            <?php if (!empty($row->description)): ?>
                                <tr>
                                    <th class="text-muted text-uppercase small">Narration</th>
                                    <td class="fw-semibold"><?=esc($row->description)?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if (!empty($row->assigned)): ?>
                                <tr>
                                    <th class="text-muted text-uppercase small">Assigned to</th>
                                    <td class="fw-semibold">
                                        <img src="<?=get_image($row->assigned_image)?>" class="rounded-circle me-2" width="24" height="24" alt="<?=esc($row->assigned_first_name ?? '')?>">
                                        <?=esc($row->assigned_first_name . ' ' . $row->assigned_last_name)?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <?php if (!empty($row->date_created)): ?>
                                <tr>
                                    <th class="text-muted text-uppercase small">Created by</th>
                                    <td class="fw-semibold">
                                        <img src="<?=get_image($row->user_image)?>" class="rounded-circle me-2" width="24" height="24" alt="<?=esc($row->user_first_name ?? '')?>">
                                        <?=esc($row->user_first_name . ' ' . $row->user_last_name)?>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-muted text-uppercase small">Date created</th>
                                    <td class="fw-semibold"><?=get_date_time($row->date_created, 2)?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if (!empty($row->date_updated)): ?>
                                <tr>
                                    <th class="text-muted text-uppercase small">Last updated</th>
                                    <td class="fw-semibold"><?=get_date_time($row->date_updated)?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted text-uppercase small">Updated by</th>
                                    <td class="fw-semibold">
                                        <img src="<?=get_image($row->updated_by_image)?>" class="rounded-circle me-2" width="24" height="24" alt="<?=esc($row->updated_by_first_name ?? '')?>">
                                        <?=esc($row->updated_by_first_name . ' ' . $row->updated_by_last_name)?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <?php if (!empty($row->transaction_status)): ?>
                                <tr>
                                    <th class="text-muted text-uppercase small">Status</th>
                                    <td>
                                        <?php
                                            $badgeClass = match (strtolower($row->transaction_status ?? '')) {
                                                'approved' => 'bg-success text-white',
                                                'rejected' => 'bg-danger text-white',
                                                'pending' => 'bg-warning text-dark',
                                                default => 'bg-secondary text-white',
                                            };
                                        ?>
                                        <span class="badge <?=$badgeClass?> text-uppercase fw-semibold"><?=esc($row->transaction_status ?? '')?></span>
                                        <?php if (strtolower($row->transaction_status ?? '') === 'rejected' && !empty($row->reject_reason)): ?>
                                            <span class="ms-2 small text-danger fst-italic">Reason: <?=esc($row->reject_reason)?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php if (!empty($denoPayload)): ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-body-secondary d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2 text-uppercase small text-muted">
                                <i class="fa-solid fa-coins"></i>
                                <span>Creator Denomination Breakdown</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <?php foreach ($denoPayload as $currency => $entries): ?>
                                    <?php $currencyTotal = $denoTotals[$currency] ?? 0; ?>
                                    <div class="col-12">
                                        <div class="border rounded-3">
                                            <div class="bg-body-tertiary px-3 py-2 d-flex justify-content-between align-items-center">
                                                <span class="fw-semibold text-uppercase small"><?=$currency?> Notes</span>
                                                <span class="badge bg-primary-subtle text-primary">Total: <?=number_format($currencyTotal, 2)?></span>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-sm mb-0 align-middle text-nowrap text-start">
                                                    <thead class="text-uppercase text-muted small">
                                                        <tr>
                                                            <th>Denomination</th>
                                                            <th>Count</th>
                                                            <th>Volume</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($entries as $entry): ?>
                                                            <tr>
                                                                <td><?=number_format($entry['denomination'], ($currency === 'CFA') ? 0 : 2)?></td>
                                                                <td><?=number_format($entry['count'])?></td>
                                                                <td class="fw-semibold"><?=number_format($entry['volume'], 2)?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($verifiedDenoPayload)): ?>
                    <div class="card border-0 shadow-sm mt-4">
                        <div class="card-header bg-body-secondary d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2 text-uppercase small text-muted">
                                <i class="fa-solid fa-check-double"></i>
                                <span>Verified Denomination Breakdown</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <?php foreach ($verifiedDenoPayload as $currency => $entries): ?>
                                    <?php $currencyTotal = $verifiedDenoTotals[$currency] ?? 0; ?>
                                    <div class="col-12">
                                        <div class="border rounded-3">
                                            <div class="bg-body-tertiary px-3 py-2 d-flex justify-content-between align-items-center">
                                                <span class="fw-semibold text-uppercase small"><?=$currency?> Notes</span>
                                                <span class="badge bg-success-subtle text-success">Total: <?=number_format($currencyTotal, 2)?></span>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-sm mb-0 align-middle text-nowrap text-start">
                                                    <thead class="text-uppercase text-muted small">
                                                        <tr>
                                                            <th>Denomination</th>
                                                            <th>Count</th>
                                                            <th>Volume</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($entries as $entry): ?>
                                                            <tr>
                                                                <td><?=number_format($entry['denomination'], ($currency === 'CFA') ? 0 : 2)?></td>
                                                                <td><?=number_format($entry['count'])?></td>
                                                                <td class="fw-semibold"><?=number_format($entry['volume'], 2)?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($approvalReceiptImageUrl !== ''): ?>
                    <div class="card border-0 shadow-sm mt-4">
                        <div class="card-header bg-body-secondary d-flex align-items-center gap-2 text-uppercase small text-muted">
                            <i class="fa-solid fa-receipt"></i>
                            <span>Approval Receipt</span>
                        </div>
                        <div class="card-body">
                            <a href="<?=esc($approvalReceiptImageUrl)?>" target="_blank" class="d-inline-block text-decoration-none">
                                <img src="<?=esc($approvalReceiptImageUrl)?>" alt="Approval receipt" class="img-fluid rounded border" style="max-height:260px;">
                            </a>
                            <div class="mt-2">
                                <a href="<?=esc($approvalReceiptImageUrl)?>" target="_blank" class="btn btn-outline-secondary btn-sm">Open receipt</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="card-footer no-print justify-content-between d-flex mb-5">
            <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
              <button type="button" class="btn btn-danger">
                <i class="fa-solid fa-chevron-left"></i> Exit
              </button>
            </a>
            <button id="printView<?=$row->id?>" class="btn btn-sm btn-outline-secondary col-2" onclick="printViewContent('card')">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>
</div>

<script type="text/javascript">               
  function printViewContent(cardId) {
      const card = document.getElementById(cardId);
      if (!card) {
          console.error('View not found');
          return;
      }

      const headerHtml = card.querySelector('.card-header')?.innerHTML ?? '';
      const bodyHtml = card.querySelector('.card-body')?.innerHTML ?? '';
      const printMarkup = `
          <div class="print-card">
              <header class="print-header">
                  ${headerHtml}
              </header>
              <section class="print-body">
                  ${bodyHtml}
              </section>
          </div>`;

      const printWindow = window.open('', '_blank');
      if (!printWindow) {
          console.error('Unable to open print window');
          return;
      }

      printWindow.document.open();
      printWindow.document.write(`
          <html>
          <head>
              <title>Print View</title>
              <style>
                  @page {
                      size: A4;
                      margin: 18mm 15mm;
                  }
                  html, body {
                      margin: 0;
                      padding: 0;
                      font-family: "Segoe UI", Arial, sans-serif;
                      background-color: #fff;
                      color: #111;
                  }
                  .print-wrapper {
                      width: 100%;
                      display: flex;
                      justify-content: center;
                  }
                  .print-card {
                      width: 100%;
                      max-width: 900px;
                      border: 1px solid #d5d8df;
                      border-radius: 8px;
                      padding: 24px;
                      box-sizing: border-box;
                  }
                  .print-header {
                      text-align: center;
                      border-bottom: 1px solid #d5d8df;
                      padding-bottom: 12px;
                      margin-bottom: 18px;
                  }
                  .print-body .card,
                  .print-body table {
                      box-shadow: none !important;
                      border-color: #d5d8df;
                  }
                  .print-body table {
                      width: 100%;
                      border-collapse: collapse;
                  }
                  .print-body table th,
                  .print-body table td {
                      border: 1px solid #909193ff;
                      padding: 6px 10px;
                      font-size: 12px;
                  }
                  .print-body table thead th {
                      background: #bcbec1ff;
                      text-transform: uppercase;
                      font-size: 11px;
                  }
                  .print-body .card-header,
                  .print-body .card-footer {
                      background: #929293ff !important;
                  }
                  .print-body .badge {
                      border-radius: 4px;
                  }
                  .print-card .btn,
                  .print-card .card-footer,
                  .print-card .no-print {
                      display: none !important;
                  }
              </style>
          </head>
          <body onload="window.print(); window.close();">
              <div class="print-wrapper">
                  ${printMarkup}
              </div>
          </body>
          </html>
      `);
      printWindow.document.close();
  }
</script>

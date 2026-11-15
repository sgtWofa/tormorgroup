<style>
    .form-control, .form-select {
      border-top: none !important;
      border-left: 0.5px solid gray !important;
      border-right: none !important;
      border: 1px solid yellowgreen;
      border-radius: 5px !important;
    }
    .input-group-text {
      font-weight: bolder;
    }
    .form-label {
      color: gray;
      font-size: 12px;
    }
    .network-group {
      font-weight: bold;
      text-transform: uppercase;
    }

    /* Table header sticky fix */
    .table thead th {
      position: sticky;
      top: 0;
      z-index: 10;
      background-color: var(--bs-body-bg);
      border-bottom: 2px solid var(--bs-border-color);
    }

    .table tbody tr:nth-child(odd) {
      background-color: rgba(0,0,0,0.02);
    }

    [data-bs-theme="dark"] .table thead th {
      background-color: var(--bs-body-bg);
      border-bottom-color: var(--bs-border-color);
    }

    .recon-kpi {
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 10px;
      padding: 10px 12px;
      background: rgba(0,0,0,0.08);
    }
    .recon-kpi .label {
      font-size: 11px;
      color: #9aa4ad;
      text-transform: uppercase;
      letter-spacing: 0.6px;
    }
    .recon-kpi .value {
      font-size: 16px;
      font-weight: 700;
    }
    .variance-badge {
      font-size: 11px;
      padding: 3px 8px;
      border-radius: 999px;
      display: inline-block;
    }
    .variance-ok { background: rgba(25,135,84,0.15); color: #6fe2a8; border: 1px solid rgba(25,135,84,0.35); }
    .variance-bad { background: rgba(220,53,69,0.9); color: #ffffff8f; border: 1px solid rgba(220,53,69,0.35); }
    .variance-misc { background: rgba(255, 193, 7, 0.9); color: #6b480a; border: 1px solid rgba(255,193,7,0.35); }

	.recon-proof-thumb-view {
		width: 46px;
		height: 38px;
		border-radius: 10px;
		overflow: hidden;
		position: relative;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		background: rgba(0,0,0,0.06);
		border: 1px solid rgba(0,0,0,0.12);
	}
	.recon-proof-thumb-view:hover {
		filter: brightness(0.98);
		border-color: rgba(13,110,253,0.35);
	}
	.recon-proof-thumb-view img {
		width: 100%;
		height: 100%;
		object-fit: cover;
		display: block;
	}
	.recon-denos-btn {
		width: 46px;
		height: 38px;
		border-radius: 10px;
		overflow: hidden;
		position: relative;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		background: rgba(13,110,253,0.08);
		border: 1px solid rgba(13,110,253,0.22);
		color: rgba(13,110,253,0.95);
	}
	.recon-denos-btn:hover {
		filter: brightness(0.98);
		border-color: rgba(13,110,253,0.35);
	}
  </style>

  <?php
    $miscRow = null;
    $grouped = [];
    $systemTotal = 0.0;
    $enteredTotal = 0.0;
    $cashBreakdown = [
      'GHS' => [
        'system_total' => 0.0,
        'entered_total' => 0.0,
        'variance_total' => 0.0,
      ],
      'CFA' => [
        'system_total' => 0.0,
        'entered_total' => 0.0,
        'variance_total' => 0.0,
      ],
    ];
    $varianceBreakdown = [
      'ghs_cash' => 0.0,
      'cfa_cash' => 0.0,
      'ecash' => 0.0,
      'bank' => 0.0,
      'commission' => 0.0,
      'other' => 0.0,
    ];
    $bankChannelNeedles = [
      'BANK',
      'ADB',
      'ABSA',
      'FIDELITY',
      'APEX',
      'ACCESS',
      'CAL',
      'ECOBANK',
      'GCB',
      'GT',
      'STANBIC',
      'FNB',
      'FIRST',
    ];
    $isBankChannel = function (string $walletId) use ($bankChannelNeedles): bool {
      $walletId = strtoupper(trim($walletId));
      if (strpos($walletId, 'ECASH:') !== 0) {
        return false;
      }

      $labelKey = strtoupper(trim(substr($walletId, 6)));
      if ($labelKey === '') {
        return false;
      }

      foreach ($bankChannelNeedles as $needle) {
        if ($needle !== '' && strpos($labelKey, $needle) !== false) {
          return true;
        }
      }

      return false;
    };
    $parseDenos = function ($raw, string $currencyHint = 'GHS'): array {
      $currencyHint = strtoupper(trim($currencyHint ?: 'GHS'));
      if (!in_array($currencyHint, ['GHS', 'CFA'], true)) {
        $currencyHint = 'GHS';
      }

      $decoded = [];
      if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
          $decoded = [];
        }
      } elseif (is_array($raw)) {
        $decoded = $raw;
      }

      $rows = [];
      foreach ($decoded as $item) {
        if (!is_array($item)) {
          continue;
        }

        $denomination = (float)($item['denomination'] ?? 0);
        $count = (int)($item['count'] ?? 0);
        $volume = (float)($item['volume'] ?? ($denomination * $count));
        if ($denomination <= 0 || $count === 0) {
          continue;
        }

        $currency = strtoupper(trim((string)($item['currency'] ?? $currencyHint)));
        if (!in_array($currency, ['GHS', 'CFA'], true)) {
          $currency = $currencyHint;
        }

        $rows[] = [
          'currency' => $currency,
          'denomination' => $denomination,
          'count' => $count,
          'volume' => round($volume, 2),
        ];
      }

      usort($rows, function (array $a, array $b): int {
        return (float)$b['denomination'] <=> (float)$a['denomination'];
      });

      return $rows;
    };
    $denoKey = function (string $currency, float $denomination): string {
      return strtoupper($currency) === 'CFA'
        ? (string)(int)round($denomination)
        : number_format($denomination, 2, '.', '');
    };

    if (!empty($reconciliations)) {
      foreach ($reconciliations as $rec) {
        $walletId = strtolower(trim((string)($rec->wallet_id ?? '')));
        if ($walletId === 'misc') {
          $miscRow = $rec;
          continue;
        }

        $group = 'Other';
        $label = (string)($rec->wallet_id ?? '');
        if (strpos($walletId, 'cash:') === 0) {
          $group = 'Cash';
          $label = 'Cash (' . strtoupper(substr($walletId, 5)) . ')';
        } elseif (strpos($walletId, 'ecash:') === 0) {
          $group = 'E-Cash';
          $label = 'E-Cash (' . strtoupper(substr($walletId, 6)) . ')';
        } elseif (strpos($walletId, 'commission:') === 0) {
          $group = 'Commissions';
          $label = 'Commissions (Auto)';
        }

        $expected = (float)($rec->expected_balance ?? 0);
        $entered = (float)($rec->balance ?? 0);
        $variance = (float)($rec->variance ?? ($expected - $entered));

        $systemTotal += $expected;
        $enteredTotal += $entered;

        $currency = '';
        $systemDenos = [];
        $enteredDenos = [];
        if (strpos($walletId, 'cash:') === 0) {
          $currency = strtoupper(substr($walletId, 5));
          if (!in_array($currency, ['GHS', 'CFA'], true)) {
            $currency = '';
          }
        }
        if ($currency !== '') {
          $cashBreakdown[$currency]['system_total'] += $expected;
          $cashBreakdown[$currency]['entered_total'] += $entered;
          $cashBreakdown[$currency]['variance_total'] += $variance;
          if ($currency === 'GHS') {
            $varianceBreakdown['ghs_cash'] += $variance;
          } else {
            $varianceBreakdown['cfa_cash'] += $variance;
          }
          $systemDenos = $parseDenos($rec->system_denos_json ?? '', $currency);
          $enteredDenos = $parseDenos($rec->denos_json ?? '', $currency);
        } elseif ($group === 'E-Cash') {
          if ($isBankChannel($walletId)) {
            $varianceBreakdown['bank'] += $variance;
          } else {
            $varianceBreakdown['ecash'] += $variance;
          }
        } elseif ($group === 'Commissions') {
          $varianceBreakdown['commission'] += $variance;
        } else {
          $varianceBreakdown['other'] += $variance;
        }

        if (!isset($grouped[$group])) {
          $grouped[$group] = [];
        }
        $grouped[$group][] = [
          'group' => $group,
          'label' => $label,
          'expected' => $expected,
          'entered' => $entered,
          'variance' => $variance,
			  'wallet_id' => (string)($rec->wallet_id ?? ''),
			  'proof_image' => (string)($rec->proof_image ?? ''),
			  'denos_json' => (string)($rec->denos_json ?? ''),
          'system_denos_json' => (string)($rec->system_denos_json ?? ''),
          'currency' => $currency,
          'system_denos' => $systemDenos,
          'entered_denos' => $enteredDenos,
        ];
      }
    }

    $cashSystemTotal = (float)($cashBreakdown['GHS']['system_total'] ?? 0) + (float)($cashBreakdown['CFA']['system_total'] ?? 0);
    $cashEnteredTotal = (float)($cashBreakdown['GHS']['entered_total'] ?? 0) + (float)($cashBreakdown['CFA']['entered_total'] ?? 0);
    $cashVarianceTotal = (float)($cashBreakdown['GHS']['variance_total'] ?? 0) + (float)($cashBreakdown['CFA']['variance_total'] ?? 0);
    $ghsCashVariance = (float)($varianceBreakdown['ghs_cash'] ?? 0);
    $cfaCashVariance = (float)($varianceBreakdown['cfa_cash'] ?? 0);
    $ecashVariance = (float)($varianceBreakdown['ecash'] ?? 0);
    $bankVariance = (float)($varianceBreakdown['bank'] ?? 0);
    $otherVariance = (float)($varianceBreakdown['commission'] ?? 0) + (float)($varianceBreakdown['other'] ?? 0);
    $ghsVariance = $ghsCashVariance + $ecashVariance + $bankVariance;
    $cfaVariance = $cfaCashVariance;

    $miscAdjustment = (float)($miscRow->balance ?? 0);
    $finalTotal = $enteredTotal + $miscAdjustment;
    $hasMisc = $miscRow && abs($miscAdjustment) >= 0.01;
    $miscType = '';
    if ($hasMisc) {
      $miscType = ($miscAdjustment > 0) ? 'Shortage' : 'Overage';
    }
    $summaryStatusLabel = trim((string)($summary->transaction_status ?? 'Submitted'));
    if ($summaryStatusLabel === '') {
      $summaryStatusLabel = 'Submitted';
    }
    $summaryStatusLower = strtolower($summaryStatusLabel);
    $summaryRejected = in_array($summaryStatusLower, ['rejected', 'declined'], true);
    $summarySubmitted = $summaryStatusLower === 'submitted';
    $summaryStatusClass = $summaryRejected ? 'bg-danger' : ($summarySubmitted ? 'bg-warning text-dark' : 'bg-success');
    $summaryRejectionReason = trim((string)($summary->rejection_reason ?? ''));
    $canRejectReconciliations = !empty($canRejectReconciliations);
    $canApproveReconciliations = !empty($canApproveReconciliations);
    $viewUserFirstName = $reconciliations[0]->user_first_name ?? ($summary->user_first_name ?? '');
    $viewUserLastName = $reconciliations[0]->user_last_name ?? ($summary->user_last_name ?? '');
    $viewDateCreated = $reconciliations[0]->date_created ?? ($summary->date_created ?? '');
  ?>
  
  <div class="card col-12 col-md-11 col-lg-10 col-xl-9 mx-auto" id="card">
    <div class="card-content">
      <!-- View Header -->
      <div class="card-header">
        <center class=" mx-auto">
            <h3 class="card-title" id="staticBackdropLabel">RECONCILIATION</h3>
            <h5><?=esc($viewUserFirstName)?> <?=esc($viewUserLastName)?></h5>
            <small><?=get_date_time($viewDateCreated)?></small>
            <div class="mt-2">
              <span class="badge <?=$summaryStatusClass?>"><?=esc($summaryStatusLabel)?></span>
            </div>
            <div class="mt-3 no-print">
              <a class="btn btn-outline-danger btn-sm" target="_blank" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export?entity=reconciliations&resource=view&id=<?=esc((string)($summary->id ?? $reconciliationSessionId ?? ''))?>&format=pdf">
                <i class="fa-solid fa-file-pdf"></i> Export PDF
              </a>
            </div>
        </center>
      </div>

      <!-- View Body -->
      <div class="card-body">

        <?php if ($summaryRejected && $summaryRejectionReason !== ''): ?>
          <div class="alert alert-danger" style="border-radius:10px;">
            <div class="fw-bold">Rejection reason</div>
            <div><?=esc($summaryRejectionReason)?></div>
          </div>
        <?php endif; ?>

        <?php if ($canApproveReconciliations && $summarySubmitted && !empty($summary->id)): ?>
          <form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/approve_reconciliation/<?=esc((string)$summary->id)?>" class="alert alert-success no-print" style="border-radius:10px;">
            <?=csrf()?>
            <div class="fw-bold mb-1">Approve submitted reconciliation</div>
            <div class="small mb-2">Approving will freeze this user’s regular page access until this reconciliation is rejected for that day.</div>
            <button type="submit" class="btn btn-sm btn-outline-success">
              <i class="fa-solid fa-circle-check"></i> Approve
            </button>
          </form>
        <?php endif; ?>

        <?php if ($canRejectReconciliations && !$summaryRejected && !empty($summary->id)): ?>
          <form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/reject_reconciliation/<?=esc((string)$summary->id)?>" class="alert alert-warning no-print" style="border-radius:10px;">
            <?=csrf()?>
            <label class="form-label fw-bold">Reject this reconciliation to allow redo</label>
            <textarea name="rejection_reason" class="form-control mb-2" rows="2" placeholder="State the reason clearly" required></textarea>
            <button type="submit" class="btn btn-sm btn-outline-danger">
              <i class="fa-solid fa-ban"></i> Reject / Allow Redo
            </button>
          </form>
        <?php endif; ?>

        <div class="row g-2 mb-3">
          <div class="col-md-4">
            <div class="recon-kpi">
              <div class="label">System total (cashdrawer)</div>
              <div class="value d-none"><?=number_format($systemTotal ?? 0, 2)?></div>
              <div class="small text-muted">
                <div>GHS: <?=number_format((float)($cashBreakdown['GHS']['system_total'] ?? 0), 2)?></div>
                <div>CFA: <?=number_format((float)($cashBreakdown['CFA']['system_total'] ?? 0), 2)?></div>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="recon-kpi">
              <div class="label">Entered total</div>
              <div class="value d-none"><?=number_format($enteredTotal ?? 0, 2)?></div>
              <div class="small text-muted">
                <div>GHS: <?=number_format((float)($cashBreakdown['GHS']['entered_total'] ?? 0), 2)?></div>
                <div>CFA: <?=number_format((float)($cashBreakdown['CFA']['entered_total'] ?? 0), 2)?></div>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="recon-kpi">
              <div class="label"><?= $hasMisc ? esc($miscType) . ' (Miscellaneous)' : 'Miscellaneous' ?></div>
              <div class="small lh-sm">
                <div class="fw-semibold text-dark">Variance breakdown</div>
                <b class="text-muted">GHS total <?= $ghsVariance >= 0 ? 'shortage' : 'overage' ?>: <?=number_format(abs($ghsVariance), 2)?></b>
                <div class="ps-3 text-muted">Cash GHS <?= $ghsCashVariance >= 0 ? 'shortage' : 'overage' ?>: <?=number_format(abs($ghsCashVariance), 2)?></div>
                <div class="ps-3 text-muted">E-Cash <?= $ecashVariance >= 0 ? 'shortage' : 'overage' ?>: <?=number_format(abs($ecashVariance), 2)?></div>
                <div class="ps-3 text-muted mb-2">Bank <?= $bankVariance >= 0 ? 'shortage' : 'overage' ?>: <?=number_format(abs($bankVariance), 2)?></div>
                <b class="text-muted">
                  CFA total <?= $cfaVariance >= 0 ? 'shortage' : 'overage' ?>: <?=number_format(abs($cfaVariance), 2)?>
                </b>
                <?php if (abs($otherVariance) >= 0.01): ?>
                  <div>Other / commissions <?= $otherVariance >= 0 ? 'shortage' : 'overage' ?>: <?=number_format(abs($otherVariance), 2)?></div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <?php if ($hasMisc): ?>
          <div class="alert alert-warning" style="border-radius:10px;">
            <div style="font-weight:700;">Miscellaneous (<?=esc($miscType)?>) explained</div>
            <div class="text-muted" style="font-size: 13px;">
              Miscellaneous is recorded automatically when the balances you entered do not match the cashdrawer system balances.
              It represents the overall <b><?=strtolower($miscType)?></b> across all channels for this reconciliation and makes the final totals tie out.
            </div>
          </div>
        <?php endif; ?>

        <table class="table table-striped table-hover table-bordered table-sm">
          <thead>
            <tr>
              <th>DESCRIPTION</th>
              <th class="text-end">SYSTEM</th>
              <th class="text-end">ENTERED</th>
              <th class="text-end">VARIANCE</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($grouped)):?>
              <?php foreach ($grouped as $group => $items): ?>
                <tr>
                  <th colspan="4" class="network-group text-primary" style="font-size:14px;"><?=esc($group)?>: </th>
                </tr>
                <?php foreach ($items as $item): ?>
                  <?php
                    $expected = (float)($item['expected'] ?? 0);
                    $entered = (float)($item['entered'] ?? 0);
                    $variance = (float)($item['variance'] ?? ($expected - $entered));
                    $isOk = abs($variance) < 0.01;
                    $varianceText = number_format($variance, 2);
                    $walletId = strtolower(trim((string)($item['wallet_id'] ?? '')));
                    $proofImage = (string)($item['proof_image'] ?? '');
                    $denosJson = (string)($item['denos_json'] ?? '');
                    $systemDenosJson = (string)($item['system_denos_json'] ?? '');
                    $hasProof = (strpos($walletId, 'ecash:') === 0) && $proofImage !== '';
                    $cashCurrency = '';
                    if (strpos($walletId, 'cash:') === 0) {
                      $cashCurrency = strtoupper(substr($walletId, 5));
                      if (!in_array($cashCurrency, ['GHS', 'CFA'], true)) {
                        $cashCurrency = 'GHS';
                      }
                    }
                    $systemDenos = [];
                    if (trim($systemDenosJson) !== '') {
                      $decodedSystem = json_decode($systemDenosJson, true);
                      if (is_array($decodedSystem)) {
                        $systemDenos = $decodedSystem;
                      }
                    }
                    $enteredDenos = [];
                    if (trim($denosJson) !== '') {
                      $decodedEntered = json_decode($denosJson, true);
                      if (is_array($decodedEntered)) {
                        $enteredDenos = $decodedEntered;
                      }
                    }
                    $hasDenos = ($cashCurrency !== '') && (!empty($systemDenos) || !empty($enteredDenos));
                    // Extract channel name from wallet_id (e.g., cash:GHS -> GHS, ecash:ZEEPAY -> ZEEPAY)
                    $channelName = '';
                    if ($cashCurrency !== '') {
                      $channelName = $cashCurrency;
                    } elseif (strpos($walletId, 'ecash:') === 0) {
                      $channelName = strtoupper(substr($walletId, 6));
                    } elseif (strpos($walletId, 'commission:') === 0) {
                      $channelName = 'COMMISSIONS';
                    }
                    $proofSrc = '';
                    if ($hasProof) {
                      $proofSrc = rtrim(ROOT, '/') . '/' . ltrim($proofImage, '/');
                    }
                    $denosPayload = '';
                    if ($hasDenos) {
                      $denosPayload = base64_encode(json_encode([
                        'currency' => $cashCurrency,
                        'label' => $channelName,
                        'system' => $systemDenos,
                        'entered' => $enteredDenos,
                        'system_total' => $expected,
                        'entered_total' => $entered,
                        'variance_total' => $variance,
                      ], JSON_UNESCAPED_SLASHES));
                    }
                  ?>
                  <tr>
                    <td class="px-1" style="white-space: nowrap;">
                      <div class="d-flex align-items-center justify-content-between gap-2">
                        <span><?=esc($channelName)?></span>
                        <div class="d-flex align-items-center gap-2">
                          <?php if ($hasDenos): ?>
                            <a href="#" class="recon-denos-open" data-wallet-id="<?=esc($walletId)?>" data-breakdown="<?=esc($denosPayload)?>" style="text-decoration:none;">
                              <span class="recon-denos-btn" title="View cash breakdown">
                                <i class="fa-solid fa-list-check"></i>
                              </span>
                            </a>
                          <?php endif; ?>
                          <?php if ($hasProof): ?>
                            <a href="#" class="recon-proof-open" data-proof-src="<?=esc($proofSrc)?>" style="text-decoration:none;">
                              <span class="recon-proof-thumb-view" title="View proof">
                                <img src="<?=esc($proofSrc)?>" alt="Proof" loading="lazy" />
                              </span>
                            </a>
                          <?php endif; ?>
                        </div>
                      </div>
                    </td>
                    <td class="text-end"><?=number_format($expected, 2)?></td>
                    <td class="text-end"><?=number_format($entered, 2)?></td>
                    <td class="text-end" style="white-space: nowrap;">
                      <?php if ($isOk): ?>
                        <span class="variance-badge variance-ok">OK</span>
                        <span class="text-muted ms-1">0.00</span>
                      <?php elseif ($cashCurrency !== ''): ?>
                        <?php if ($variance > 0): ?>
                          <span class="variance-badge variance-bad">Shortage</span>
                          <span class="text-muted ms-1"><?=esc(number_format(abs($variance), 2))?></span>
                        <?php else: ?>
                          <span class="variance-badge variance-misc">Overage</span>
                          <span class="text-muted ms-1"><?=esc(number_format(abs($variance), 2))?></span>
                        <?php endif; ?>
                      <?php else: ?>
                        <?php if ($hasMisc): ?>
                          <span class="variance-badge variance-misc">Captured</span>
                          <span class="text-muted ms-1"><?=esc($varianceText)?></span>
                        <?php else: ?>
                          <span class="variance-badge variance-bad">Mismatch</span>
                          <span class="text-muted ms-1"><?=esc($varianceText)?></span>
                        <?php endif; ?>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
            <?php else:?>
                <tr><td colspan="10" class="text-center justify-content-center">No records to show!</td></tr>
            <?php endif; ?>
          </tbody>
          <tfoot class="d-none">
            <tr>
              <th class="px-1">TOTAL</th>
              <th class="text-end"><?=number_format($systemTotal ?? 0, 2)?></th>
              <th class="text-end"><?=number_format($finalTotal ?? 0, 2)?></th>
              <th class="text-end">
                <?php 
                   if (!empty($summary) && ($summary->remarks ?? '') == 'Balanced') {
                      $remarksBadge = '<span class="variance-badge variance-ok">Balanced</span>';
                   } else {
                      $remarksBadge = '<span class="variance-badge variance-bad">Not Balanced</span>';
                   }
                ?>
                <?=$remarksBadge?>
              </th>
            </tr>
          </tfoot>
        </table>
      </div>
      <div class="card-footer no-print d-flex justify-content-between mb-5 px-3">
        <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
          <button type="button" class="btn btn-danger">
            <i class="fa-solid fa-chevron-left"></i> Exit
          </button>
        </a>
    </div>
    </div>
  </div>

	<div class="modal fade" id="reconProofModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Photo proof</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<img id="reconProofModalImg" src="" alt="Proof" style="width:100%;max-height:70vh;object-fit:contain;border-radius:10px;" />
				</div>
			</div>
		</div>
	</div>

	<div class="modal fade" id="reconDenosModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered modal-xl">
			<div class="modal-content">
				<div class="modal-header">
					<div>
						<h5 class="modal-title mb-0" id="reconDenosModalTitle">Cash breakdown</h5>
						<small class="text-muted" id="reconDenosModalSubtitle"></small>
					</div>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body" id="reconDenosModalBody"></div>
			</div>
		</div>
	</div>

<script type="text/javascript">               
  function printViewContent(cardId) {
      // Get the card content by ID
      const card = document.getElementById(cardId);
      if (!card) {
          console.error('Card not found');
          return;
      }

      // Clone the card
      const clonedCard = card.cloneNode(true);

      // Remove the footer from the cloned card
      const footer = clonedCard.querySelector('.card-footer');
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
              <title>Print Card</title>
              <!-- Include Bootstrap CSS -->
              <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
              <!-- Include Font Awesome -->
              <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
              <style>
                  body { margin: 0; padding: 20px; font-family: Arial, sans-serif; background-color: white; }
                  .table th, .table td { border: 1px solid #000; }
					.recon-proof-thumb-view {
						width: 46px;
						height: 38px;
						border-radius: 10px;
						overflow: hidden;
						display: inline-flex;
						align-items: center;
						justify-content: center;
						border: 1px solid rgba(0,0,0,0.25);
						background: rgba(0,0,0,0.03);
					}
					.recon-proof-thumb-view img {
						width: 100% !important;
						height: 100% !important;
						max-width: 46px;
						max-height: 38px;
						object-fit: cover;
						display: block;
					}
					.recon-denos-btn {
						width: 46px;
						height: 38px;
						display: inline-flex;
						align-items: center;
						justify-content: center;
						border-radius: 10px;
						border: 1px solid rgba(0,0,0,0.25);
					}
					a { color: inherit; text-decoration: none; }
					@media print {
						body { padding: 0; }
						.recon-proof-open, .recon-denos-open { pointer-events: none; }
					}
              </style>
          </head>
          <body onload="window.print(); window.close();">
              ${clonedCard.outerHTML}
          </body>
          </html>
      `);
      printWindow.document.close();
  }
</script>

	<script>
		document.addEventListener('DOMContentLoaded', function(){
			const modalEl = document.getElementById('reconProofModal');
			const modalImg = document.getElementById('reconProofModalImg');
			if (!modalEl || !modalImg) return;

			document.querySelectorAll('.recon-proof-open').forEach((el) => {
				el.addEventListener('click', function(e){
					e.preventDefault();
					const src = el.getAttribute('data-proof-src') || '';
					if (!src) return;
					modalImg.src = src;
					if (window.bootstrap && typeof window.bootstrap.Modal === 'function') {
						window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
					}
				});
			});
		});
	</script>

	<script>
		document.addEventListener('DOMContentLoaded', function(){
			const modalEl = document.getElementById('reconDenosModal');
			const titleEl = document.getElementById('reconDenosModalTitle');
			const subtitleEl = document.getElementById('reconDenosModalSubtitle');
			const bodyEl = document.getElementById('reconDenosModalBody');
			if (!modalEl || !titleEl || !subtitleEl || !bodyEl) return;

			function decodePayload(payload64) {
				if (!payload64) return {};
				try {
					const parsed = JSON.parse(atob(payload64));
					return (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) ? parsed : {};
				} catch (err) {
					return {};
				}
			}

			function escapeHtml(value) {
				return String(value ?? '')
					.replace(/&/g, '&amp;')
					.replace(/</g, '&lt;')
					.replace(/>/g, '&gt;')
					.replace(/"/g, '&quot;')
					.replace(/'/g, '&#39;');
			}

			function money(value) {
				const n = Number(value || 0);
				return n.toFixed(2);
			}

			function formatDenomination(currency, value) {
				const cur = String(currency || 'GHS').toUpperCase();
				const n = Number(value || 0);
				if (cur === 'CFA') {
					return String(Math.round(n));
				}
				return n.toFixed(2);
			}

			function normalizeRows(rows, currency) {
				const cur = String(currency || 'GHS').toUpperCase() === 'CFA' ? 'CFA' : 'GHS';
				const normalized = [];
				(rows || []).forEach((row) => {
					if (!row || typeof row !== 'object') return;
					const denom = Number(row.denomination || 0);
					const count = Number(row.count || 0);
					const volume = Number(row.volume || (denom * count));
					if (!(denom > 0) || count === 0) return;
					normalized.push({
						currency: cur,
						denomination: denom,
						count: count,
						volume: volume,
					});
				});
				normalized.sort((a, b) => b.denomination - a.denomination);
				return normalized;
			}

			function indexRows(rows) {
				const indexed = {};
				(rows || []).forEach((row) => {
					const key = String(row.denomination);
					if (!indexed[key]) {
						indexed[key] = {
							currency: row.currency,
							denomination: Number(row.denomination || 0),
							count: 0,
							volume: 0,
						};
					}
					indexed[key].count += Number(row.count || 0);
					indexed[key].volume += Number(row.volume || 0);
				});
				return indexed;
			}

			function renderRows(rows, currency, emptyMessage) {
				if (!rows.length) {
					return `<tr><td colspan="3" class="text-muted">${escapeHtml(emptyMessage)}</td></tr>`;
				}
				return rows.map((row) => {
					return `
						<tr>
							<td>${escapeHtml((currency === 'CFA' ? 'CFA ' : 'GH₵') + formatDenomination(currency, row.denomination))}</td>
							<td class="text-end">${escapeHtml(String(row.count))}</td>
							<td class="text-end">${escapeHtml(money(row.volume))}</td>
						</tr>
					`;
				}).join('');
			}

			function renderComparisonRows(systemRows, enteredRows, currency) {
				const systemMap = indexRows(systemRows);
				const enteredMap = indexRows(enteredRows);
				if (!systemRows.length || !enteredRows.length) {
					return `<tr><td colspan="8" class="text-muted">Per-denomination comparison needs both the system capture and user-entered breakdowns. One side is missing for this record.</td></tr>`;
				}
				const keys = Array.from(new Set([
					...Object.keys(systemMap),
					...Object.keys(enteredMap),
				])).sort((a, b) => Number(b) - Number(a));

				if (!keys.length) {
					return `<tr><td colspan="8" class="text-muted">No denomination breakdown captured for this record.</td></tr>`;
				}

				return keys.map((key) => {
					const system = systemMap[key] || { count: 0, volume: 0, denomination: Number(key) || 0 };
					const entered = enteredMap[key] || { count: 0, volume: 0, denomination: Number(key) || 0 };
					const countDiff = (Number(entered.count || 0) - Number(system.count || 0));
					const volumeDiff = (Number(entered.volume || 0) - Number(system.volume || 0));
					const countDiffText = countDiff > 0 ? `+${countDiff}` : String(countDiff);
					const volumeDiffText = volumeDiff > 0 ? `+${money(volumeDiff)}` : money(volumeDiff);
					let badge = 'OK';
					let badgeClass = 'variance-ok';
					if (Math.abs(countDiff) > 0.00001 || Math.abs(volumeDiff) > 0.01) {
						if (volumeDiff < 0) {
							badge = 'Shortage';
							badgeClass = 'variance-bad';
						} else {
							badge = 'Overage';
							badgeClass = 'variance-misc';
						}
					}

					return `
						<tr>
							<td>${escapeHtml((currency === 'CFA' ? 'CFA ' : 'GH₵') + formatDenomination(currency, system.denomination || entered.denomination || key))}</td>
							<td class="text-end">${escapeHtml(String(system.count || 0))}</td>
							<td class="text-end">${escapeHtml(String(entered.count || 0))}</td>
							<td class="text-end">${escapeHtml(countDiffText)}</td>
							<td class="text-end">${escapeHtml(money(system.volume || 0))}</td>
							<td class="text-end">${escapeHtml(money(entered.volume || 0))}</td>
							<td class="text-end">${escapeHtml(volumeDiffText)}</td>
							<td class="text-end"><span class="variance-badge ${badgeClass}">${escapeHtml(badge)}</span></td>
						</tr>
					`;
				}).join('');
			}

			function renderBreakdown(payload) {
				const currency = String(payload.currency || 'GHS').toUpperCase() === 'CFA' ? 'CFA' : 'GHS';
				const label = String(payload.label || currency);
				const systemRows = normalizeRows(payload.system || [], currency);
				const enteredRows = normalizeRows(payload.entered || [], currency);
				const systemTotal = Number(payload.expected_total ?? payload.system_total ?? systemRows.reduce((sum, row) => sum + Number(row.volume || 0), 0));
				const enteredTotal = Number(payload.entered_total ?? enteredRows.reduce((sum, row) => sum + Number(row.volume || 0), 0));
				const varianceTotal = enteredTotal - systemTotal;
				const summaryBadge = Math.abs(varianceTotal) < 0.01
					? '<span class="variance-badge variance-ok">Balanced</span>'
					: (varianceTotal < 0 ? '<span class="variance-badge variance-bad">Shortage</span>' : '<span class="variance-badge variance-misc">Overage</span>');

				titleEl.textContent = label + ' cash breakdown';
				subtitleEl.textContent = 'System captured vs user entered by denomination';

				const systemNotice = systemRows.length
					? ''
					: '<div class="alert alert-warning mb-3">System denomination breakdown was not stored for this record.</div>';
				const enteredNotice = enteredRows.length
					? ''
					: '<div class="alert alert-warning mb-3">User-entered denomination breakdown was not stored for this record.</div>';

				bodyEl.innerHTML = `
					<div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
						<div>
							<div class="fw-bold">Summary</div>
							<div class="small text-muted">
								System: ${escapeHtml(money(systemTotal))} · Entered: ${escapeHtml(money(enteredTotal))}
							</div>
						</div>
						<div>
							${summaryBadge}
							<span class="ms-2">${escapeHtml(money(Math.abs(varianceTotal)))}</span>
						</div>
					</div>
					<div class="row g-3">
						<div class="col-lg-6">
							<div class="card h-100 shadow-sm">
								<div class="card-header fw-bold">System captured</div>
								<div class="card-body table-responsive">
									${systemNotice}
									<table class="table table-sm align-middle mb-0">
										<thead>
											<tr>
												<th>Denomination</th>
												<th class="text-end">Count</th>
												<th class="text-end">Volume</th>
											</tr>
										</thead>
										<tbody>${renderRows(systemRows, currency, 'No system rows captured.')}</tbody>
										<tfoot>
											<tr>
												<th>Total</th>
												<th></th>
												<th class="text-end">${escapeHtml(money(systemTotal))}</th>
											</tr>
										</tfoot>
									</table>
								</div>
							</div>
						</div>
						<div class="col-lg-6">
							<div class="card h-100 shadow-sm">
								<div class="card-header fw-bold">User entered</div>
								<div class="card-body table-responsive">
									${enteredNotice}
									<table class="table table-sm align-middle mb-0">
										<thead>
											<tr>
												<th>Denomination</th>
												<th class="text-end">Count</th>
												<th class="text-end">Volume</th>
											</tr>
										</thead>
										<tbody>${renderRows(enteredRows, currency, 'No user-entered rows captured.')}</tbody>
										<tfoot>
											<tr>
												<th>Total</th>
												<th></th>
												<th class="text-end">${escapeHtml(money(enteredTotal))}</th>
											</tr>
										</tfoot>
									</table>
								</div>
							</div>
						</div>
					</div>
					<div class="table-responsive mt-3">
						<table class="table table-sm align-middle table-bordered">
							<thead>
								<tr>
									<th>Denomination</th>
									<th class="text-end">System Count</th>
									<th class="text-end">Entered Count</th>
									<th class="text-end">Count Diff</th>
									<th class="text-end">System Volume</th>
									<th class="text-end">Entered Volume</th>
									<th class="text-end">Volume Diff</th>
									<th class="text-end">Status</th>
								</tr>
							</thead>
							<tbody>${renderComparisonRows(systemRows, enteredRows, currency)}</tbody>
						</table>
					</div>
				`;
			}

			document.querySelectorAll('.recon-denos-open').forEach((el) => {
				el.addEventListener('click', function(e){
					e.preventDefault();
					const payload64 = el.getAttribute('data-breakdown') || '';
					const payload = decodePayload(payload64);
					if (!payload || typeof payload !== 'object') return;
					renderBreakdown(payload);

					if (window.bootstrap && typeof window.bootstrap.Modal === 'function') {
						window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
					}
				});
			});
		});
	</script>

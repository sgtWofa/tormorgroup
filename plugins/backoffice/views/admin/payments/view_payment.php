<?php if(user_can('view_reversal')):?>
	<style type="text/css">
		tbody tr{
			white-space: nowrap;
		}
		tbody tr.wrap{
			white-space: normal;
		}
	</style>
	<?php if(!empty($row)): ?>
		<?php
			$paymentBreakdown = function_exists('backoffice_payment_row_breakdown') ? backoffice_payment_row_breakdown($row) : ['items' => [], 'total' => 0.0];
			$paymentBreakdownItems = $paymentBreakdown['items'] ?? [];
			$isBulkPaymentView = !empty($paymentBreakdownItems);
			$paymentAssignedSnapshot = (!$isBulkPaymentView && function_exists('backoffice_assignment_snapshot_lines'))
				? backoffice_assignment_snapshot_lines((int)($row->pay_to ?? 0), $row->network ?? '', $row->payment_type ?? '')
				: [];
			$denoPayload = [];
			$denoTotals = [];
			$verifiedDenoPayload = [];
			$verifiedDenoTotals = [];
			$approvalReceiptImageUrl = !empty($row->approval_receipt_photo) ? get_image($row->approval_receipt_photo) : '';
			if (!empty($row->denos_json)) {
				$decoded = json_decode($row->denos_json, true);
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
			if (!empty($row->verified_denos_json)) {
				$decodedVerified = json_decode($row->verified_denos_json, true);
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

		<div class="row g-3 col-sm-11 col-md-10 col-lg-6 mx-auto shadow p-4 rounded">
		
			<center>
				<div class="badge">
					<img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:40px;max-height: 40px;object-fit: cover;">
					<strong style="font-size:15px; color: gray;"> <?=APP_NAME?></strong>
				</div>
				<h4 class="text-center" style="font-weight: bolder;">
					<?=ucfirst($row->network)?> <?=ucfirst($row->payment_type ?? '')?> PAYMENT
				</h4><br>
				<div class="d-flex justify-content-center gap-2 mb-2">
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export?entity=payments&resource=view&id=<?=$row->id?>&format=pdf" class="btn btn-outline-secondary btn-sm">Export PDF</a>
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export?entity=payments&resource=view&id=<?=$row->id?>&format=xlsx" class="btn btn-outline-success btn-sm">Export Excel</a>
				</div>
			</center>

			<div class="col col-12 d-flex table-responsive">
				<table class="table table-striped table-hover border-bottom col-lg-6 col-md-12 table-sm" style="height: fit-content !important;">
					<thead>
						<tr><th colspan="2" class="text-primary">PAYMENT DETAILS</th></tr>
					</thead>
					<tbody>
						<tr>
							<th class="text-muted">Payment type: </th>
							<td><?=esc($row->payment_type ?? '')?> <?=esc($row->network ?? '')?></td>
						</tr>
						<?php if(!empty($paymentAssignedSnapshot)): ?>
							<tr class="wrap">
								<th class="text-muted">Assigned SIM / Wallet:</th>
								<td style="white-space:normal;">
									<?php if(!empty($paymentAssignedSnapshot['missing'])): ?>
										<span class="text-warning fw-semibold"><?=esc($paymentAssignedSnapshot['message'] ?? 'No assigned SIM/wallet details found.')?></span>
									<?php else: ?>
										<?php foreach(($paymentAssignedSnapshot['lines'] ?? []) as $line): ?>
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
						<tr>
							<th class="text-muted">Amount: </th>
							<td><?=number_format($row->amount ?? 0, 2)?></td>
						</tr>	
						<?php if($isBulkPaymentView): ?>
							<tr><th colspan="2" class="text-warning text-uppercase">Bulk breakdown:</th></tr>
							<tr class="wrap">
								<td colspan="2" style="white-space:normal;">
									<div class="table-responsive">
										<table class="table table-sm mb-0 align-middle">
											<thead>
												<tr>
													<th class="text-muted">Payment Type</th>
													<th class="text-end text-muted">Amount Involved</th>
													<th class="text-muted">Receipt</th>
												</tr>
											</thead>
											<tbody>
												<?php foreach($paymentBreakdownItems as $item): ?>
													<?php $receiptUrl = !empty($item['receipt_photo']) ? get_image($item['receipt_photo']) : ''; ?>
													<tr>
														<td class="text-muted px-3"><?=esc($item['channel'] ?? '')?></td>
														<td class="text-end fw-semibold text-muted"><?=number_format((float)($item['amount'] ?? 0), 2)?></td>
														<td class="text-muted">
															<?php if($receiptUrl !== ''): ?>
																<a href="<?=esc($receiptUrl)?>" target="_blank" class="btn btn-outline-secondary btn-sm">Open receipt</a>
															<?php else: ?>
																<span class="text-muted">Not uploaded</span>
															<?php endif; ?>
														</td>
													</tr>
												<?php endforeach; ?>
											</tbody>
											<tfoot>
												<tr>
													<th class="text-muted px-3">Total</th>
													<th class="text-end text-muted"><?=number_format((float)($paymentBreakdown['total'] ?? $row->amount ?? 0), 2)?></th>
													<td class="text-muted">***</td>
												</tr>
											</tfoot>
										</table>
									</div>
								</td>
							</tr>
						<?php endif; ?>
						<?php if(!empty($row->description)): ?>
							<tr>
								<th class="text-muted">Description:</th>
								<td style="white-space:normal;"><?=esc($row->description)?></td>
							</tr>
						<?php endif;?>	
						<?php if(!empty($row->pay_to)): ?>
						<tr>
							<th class="text-muted">Paid to:</th>
							<td>
								<span><img src="<?=get_image($row->payto_image)?>" style="width: 20px; height: 20px; object-fit: cover; border-radius: 50%; margin: 0px;"></span> 
								<?=$row->payto_first_name . ' ' . $row->payto_last_name ?>
							</td>
						</tr>	
						<?php endif;?>
						<?php if(!empty($row->assigned)): ?>
						<tr>
							<th class="text-muted">Assigned to:</th>
							<td>
								<span><img src="<?=get_image($row->assigned_image)?>" style="width: 20px; height: 20px; object-fit: cover; border-radius: 50%; margin: 0px;"></span> 
								<?=$row->assigned_first_name . ' ' . $row->assigned_last_name ?>
							</td>
						</tr>
						<?php endif;?>	
						<?php if(!empty($row->date_created)): ?>
							<tr>
								<th class="text-muted">Created by:</th>
								<td>
									<span><img src="<?=get_image($row->user_image)?>" style="width: 20px; height: 20px; object-fit: cover; border-radius: 50%; margin: 0px;"></span> 
									<?=$row->user_first_name . ' ' . $row->user_last_name ?>
								</td>
							</tr>
							<tr>
								<th class="text-muted">Date created</th>
								<td><?=get_date_time($row->date_created, 2)?></td>
							</tr>
						<?php endif;?>

							<?php if(!empty($row->date_updated)):?>
							<tr >
								<th class="text-muted">Last updated:</th>
								<td><?=get_date_time($row->date_updated)?></td>
							</tr>

							<tr >
								<th class="text-muted">Updated by:</th>
								<td>
									<span><img src="<?=get_image($row->updated_by_image)?>" style="width: 20px; height: 20px; object-fit: cover; border-radius: 50%; margin: 0px;"></span> 
									<?=$row->updated_by_first_name . ' ' . $row->updated_by_last_name ?>
								</td>
							</tr>
						<?php endif;?>

						<?php if(!empty($row->transaction_status)): ?>
						<tr>
							<?php
								// Determine badge class based on transaction status
								$badgeClass = '';
								switch (strtolower($row->transaction_status ?? '')) {
									
									case 'approved':
										$badgeClass = 'badge bg-success';
										break;
									case 'rejected':
										$badgeClass = 'badge bg-danger';
										break;
									case 'pending':
										$badgeClass = 'badge bg-warning text-dark';
										break;
									default:
										$badgeClass = 'badge bg-secondary';
										break;
								}
							?>
							<th class="text-muted">Status: </th>
							<td>
								<span class="<?= $badgeClass ?>"><?=esc($row->transaction_status ?? '')?></span>
								<?php if (strtolower($row->transaction_status ?? '') === 'rejected' && !empty($row->reject_reason)): ?>
									<span class="ms-2 small text-danger fst-italic">Reason: <?=esc($row->reject_reason)?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endif;?>
					</tbody>
				</table>
			</div>

			<?php if (!empty($denoPayload)): ?>
				<div class="col-12">
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
				</div>
			<?php endif; ?>

			<?php if (!empty($verifiedDenoPayload)): ?>
				<div class="col-12">
					<div class="card border-0 shadow-sm mt-3">
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
				</div>
			<?php endif; ?>

			<?php if ($approvalReceiptImageUrl !== ''): ?>
				<div class="col-12">
					<div class="card border-0 shadow-sm mt-3">
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
				</div>
			<?php endif; ?>

			<div class="d-flex justify-content-between">
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
					<button class="btn btn-md btn-outline-primary btn-borderless">
						<i class="fa-solid fa-chevron-left"></i> Back
					</button>
				</a>				
                <?php if (in_array(strtolower($row->transaction_status), ['draft', 'pending'])):?>
                <a href="<?= ROOT ?>/<?= $admin_route ?>/<?= $plugin_route ?>/edit_payment/<?= $row->id ?>">
                    <button class="btn btn-md btn-outline-warning btn-borderless">
                        <i class="fa-solid fa-pen-to-square"></i> Update
                    </button>
                </a>
                <?php endif; ?>
			</div>
		</div>

	<?php else:?>
		<div class="alert alert-danger text-center">
			That record was not found!
		</div>
		
		<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
			<button class="btn btn-primary">Back</button>
		</a>
	<?php endif?>

<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You don't have permission for this action.
	</div>
<?php endif?>

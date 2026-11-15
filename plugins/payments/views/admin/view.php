<?php if(user_can('view_payment_details')):?>
	<style type="text/css">
		#salaryBreakdownTable tbody tr{
			white-space: nowrap;
		}
		#salaryBreakdownTable td.wrap{
			white-space: normal;
		}
	</style>

	<?php if(!empty($row)): ?>
		<?php
			$cat = strtoupper((string)($row->category ?? ''));
			$paymentMode = strtolower(trim((string)($row->payment_mode ?? '')));
			$paymentModeLabel = $paymentMode === 'ecash' ? 'E-Cash' : ($paymentMode === 'cash' ? 'Cash' : '-');
			$badgeClass = 'badge bg-secondary';
			switch (strtolower($row->transaction_status ?? '')) {
				case 'pending': $badgeClass = 'badge bg-warning text-dark'; break;
				case 'approved': $badgeClass = 'badge bg-success'; break;
				case 'rejected': $badgeClass = 'badge bg-danger'; break;
				default: $badgeClass = 'badge bg-secondary'; break;
			}
			$salaryMonth = (string)($row->salary_month ?? '');
			$salaryLabel = $salaryMonth;
			if(preg_match('/^(\d{4})-(\d{2})$/', $salaryMonth, $m)){
				$map = [
					'01'=>'January','02'=>'February','03'=>'March','04'=>'April','05'=>'May','06'=>'June',
					'07'=>'July','08'=>'August','09'=>'September','10'=>'October','11'=>'November','12'=>'December'
				];
				$salaryLabel = ($map[$m[2]] ?? $m[2]) . ' ' . $m[1];
			}
			$denoPayload = [];
			$denoTotal = 0.0;
			if (!empty($row->denos_json)) {
				try {
					$decoded = json_decode((string)$row->denos_json, true);
					if (is_array($decoded)) {
						foreach ($decoded as $entry) {
							$denomination = (float)($entry['denomination'] ?? 0);
							$count = (int)($entry['count'] ?? 0);
							$volume = (float)($entry['volume'] ?? ($denomination * $count));
							if ($denomination <= 0 || $count <= 0 || $volume <= 0) {
								continue;
							}
							$denoPayload[] = [
								'denomination' => $denomination,
								'count' => $count,
								'volume' => $volume,
							];
							$denoTotal += $volume;
						}
						usort($denoPayload, function(array $a, array $b): int {
							return (float)$b['denomination'] <=> (float)$a['denomination'];
						});
					}
				} catch (\Throwable $e) {
					$denoPayload = [];
					$denoTotal = 0.0;
				}
			}
		?>

		<div class="row g-3 col-md-12 col-lg-10 mx-auto shadow p-4 rounded">
			<div class="d-flex justify-content-between align-items-start">
				<div>
					<h4 class="mb-1">
						<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:40px;max-height: 40px;object-fit: cover;"></span>
						Payment Details
					</h4>
					<div class="text-muted">ID: <strong><?=esc($row->id ?? '')?></strong></div>
				</div>
				<div class="text-end">
					<div class="mb-1"><span class="<?= $badgeClass ?>"><?=esc($row->transaction_status ?? '')?></span></div>
					<div class="text-muted">Created: <?=get_date_time($row->date_created ?? '')?></div>
				</div>
			</div>

			<div class="row g-3 mt-2">
				<div class="col-md-4">
					<div class="text-muted">Category</div>
					<div class="fw-bold"><?=esc($cat)?></div>
					<?php if($cat === 'SALARY' && $salaryLabel !== ''): ?>
						<div class="text-muted mt-1">Salary Period</div>
						<div class="fw-bold"><?=esc($salaryLabel)?></div>
					<?php endif; ?>
				</div>
				<div class="col-md-4">
					<div class="text-muted">Amount</div>
					<div class="fw-bold">GH₵ <?=number_format((float)($row->amount ?? 0), 2)?></div>
					<?php if($cat === 'SALARY'): ?>
						<div class="text-muted mt-1">Breakdown Total</div>
						<div class="fw-bold">GH₵ <?=number_format((float)($row->breakdown_total ?? 0), 2)?></div>
					<?php endif; ?>
				</div>
			</div>

				<div class="col-md-4">
					<div class="text-muted">Mode of payment</div>
					<div class="fw-bold"><?=esc($paymentModeLabel)?></div>
					<?php if($paymentMode === 'ecash' && !empty($row->ecash_network)): ?>
						<div class="text-muted mt-1">E-Cash Network</div>
						<div class="fw-bold"><?=esc($row->ecash_network ?? '')?></div>
					<?php endif; ?>
				</div>
			</div>

			<div class="mt-3">
				<div class="text-muted">Purpose</div>
				<div class="p-2 border rounded"><?=esc($row->description ?? '')?></div>
			</div>

			<div class="row g-3 mt-2">
				<div class="col-md-6">
					<div class="text-muted">Created by</div>
					<div class="d-flex align-items-center gap-2">
						<img src="<?=get_image($row->user_image ?? '')?>" style="width: 30px; height: 30px; object-fit: cover; border-radius: 50%;">
						<div class="fw-bold"><?=esc(trim((string)($row->user_first_name ?? '') . ' ' . (string)($row->user_last_name ?? '')))?></div>
					</div>
				</div>
				<div class="col-md-6">
					<div class="text-muted">Deduct from cashdrawer</div>
					<?php if($cat === 'SALARY'): ?>
						<div class="text-muted mb-2">Creator cashdrawer (Salary)</div>
					<?php elseif(!empty($row->assigned_staff_first_name) || !empty($row->assigned_staff_last_name)): ?>
						<div class="d-flex align-items-center gap-2 mb-2">
							<img src="<?=get_image($row->assigned_staff_image ?? '')?>" style="width: 30px; height: 30px; object-fit: cover; border-radius: 50%;">
							<div class="fw-bold"><?=esc(trim((string)($row->assigned_staff_first_name ?? '') . ' ' . (string)($row->assigned_staff_last_name ?? '')))?></div>
						</div>
					<?php else: ?>
						<div class="text-muted mb-2">-</div>
					<?php endif; ?>

					<div class="text-muted">Updated by</div>
					<?php if(!empty($row->updated_by_first_name) || !empty($row->updated_by_last_name)): ?>
						<div class="d-flex align-items-center gap-2">
							<img src="<?=get_image($row->updated_by_image ?? '')?>" style="width: 30px; height: 30px; object-fit: cover; border-radius: 50%;">
							<div class="fw-bold"><?=esc(trim((string)($row->updated_by_first_name ?? '') . ' ' . (string)($row->updated_by_last_name ?? '')))?></div>
						</div>
					<?php else: ?>
						<div class="text-muted">-</div>
					<?php endif; ?>
				</div>
			</div>

			<?php if($cat === 'SALARY'): ?>
				<div class="mt-4">
					<div class="d-flex justify-content-between align-items-center">
						<h5 class="mb-0">Salary Breakdown</h5>
						<div class="text-muted"><?=count($salary_breakdowns ?? [])?> staff</div>
					</div>
					<div class="table-responsive mt-2">
						<table class="table table-sm table-striped" id="salaryBreakdownTable">
							<thead>
								<tr>
									<th>#</th>
									<th>Staff</th>
									<th>Role</th>
									<th style="text-align:right;">Amount Due</th>
								</tr>
							</thead>
							<tbody>
								<?php if(!empty($salary_breakdowns)): $i = 0; $sum = 0; ?>
									<?php foreach($salary_breakdowns as $b): $i++; $sum += (float)($b->amount_due ?? 0); ?>
										<tr>
											<td><?=$i?></td>
											<td class="wrap"><?=esc($b->staff_name ?? '')?></td>
											<td class="wrap"><?=esc($b->staff_role ?? '')?></td>
											<td style="text-align:right;"><?=number_format((float)($b->amount_due ?? 0), 2)?></td>
										</tr>
									<?php endforeach; ?>
								<?php else: ?>
									<tr><td colspan="4" class="text-center">No breakdown rows found</td></tr>
								<?php endif; ?>
							</tbody>
							<tfoot>
								<tr>
									<th colspan="3" class="text-end">TOTAL</th>
									<th style="text-align:right;"><?=number_format((float)($sum ?? 0), 2)?></th>
								</tr>
							</tfoot>
						</table>
					</div>
				</div>
			<?php endif; ?>

			<?php if($cat === 'MAINTENANCE' || $cat === 'OPERATIONAL' || $cat === 'LOGISTICS'): ?>
				<div class="mt-4">
					<div class="d-flex justify-content-between align-items-center">
						<h5 class="mb-0">Itemized Breakdown</h5>
						<div class="text-muted"><?=count($item_breakdowns ?? [])?> item(s)</div>
					</div>
					<div class="table-responsive mt-2">
						<table class="table table-sm table-striped">
							<thead>
								<tr>
									<th>#</th>
									<th>Item</th>
									<th style="text-align:right;">Amount</th>
								</tr>
							</thead>
							<tbody>
								<?php if(!empty($item_breakdowns)): $i2 = 0; $sum2 = 0; ?>
									<?php foreach($item_breakdowns as $it): $i2++; $sum2 += (float)($it->amount ?? 0); ?>
										<tr>
											<td><?=$i2?></td>
											<td class="wrap"><?=esc($it->item_name ?? '')?></td>
											<td style="text-align:right;"><?=number_format((float)($it->amount ?? 0), 2)?></td>
										</tr>
									<?php endforeach; ?>
								<?php else: ?>
									<tr><td colspan="3" class="text-center">No items found</td></tr>
								<?php endif; ?>
							</tbody>
							<tfoot>
								<tr>
									<th colspan="2" class="text-end">TOTAL</th>
									<th style="text-align:right;"><?=number_format((float)($sum2 ?? 0), 2)?></th>
								</tr>
							</tfoot>
						</table>
					</div>
				</div>
			<?php endif; ?>

			<?php if($paymentMode === 'cash' && !empty($denoPayload)): ?>
				<div class="mt-4">
					<div class="d-flex justify-content-between align-items-center">
						<h5 class="mb-0">Cash Denominations</h5>
						<div class="text-muted">Total: GHS <?=number_format($denoTotal, 2)?></div>
					</div>
					<div class="table-responsive mt-2">
						<table class="table table-sm table-striped">
							<thead>
								<tr>
									<th>Denomination</th>
									<th>Count</th>
									<th style="text-align:right;">Volume</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach($denoPayload as $entry): ?>
									<tr>
										<td>GHS <?=number_format((float)($entry['denomination'] ?? 0), 2)?></td>
										<td><?=number_format((int)($entry['count'] ?? 0))?></td>
										<td style="text-align:right;"><?=number_format((float)($entry['volume'] ?? 0), 2)?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			<?php endif; ?>

			<div class="d-flex justify-content-between mt-3">
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
					<button class="btn btn-md btn-outline-primary" style="border:none;">
						<i class="fa-solid fa-chevron-left"></i> Back
					</button>
				</a>
				<?php if(user_can('edit_payment') && (strtolower($row->transaction_status ?? '') === 'draft' || strtolower($row->transaction_status ?? '') === 'pending')): ?>
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$row->id?>">
					<button class="btn btn-md btn-outline-warning" style="border:none;">
						<i class="fa-solid fa-pen-to-square"></i> Edit
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

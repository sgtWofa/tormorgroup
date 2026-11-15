
<?php if(user_can('view_payment_service_details')):?>
	<?php
		$receivedDenosMap = [];
		$changeDenosMap = [];
		$receivedTotalCount = 0;
		$receivedTotalVolume = 0.0;
		$changeTotalCount = 0;
		$changeTotalVolume = 0.0;

		foreach(($denos ?? []) as $denoRow){
			$denomination = (string)($denoRow->denomination ?? '');
			$count = (float)($denoRow->count ?? 0);
			$volume = (float)($denoRow->volume ?? 0);
			$isChangeRow = ($count < 0 || $volume < 0);
			$displayCount = abs($count);
			$displayVolume = abs($volume);
			$targetMap = $isChangeRow ? $changeDenosMap : $receivedDenosMap;

			if(!isset($targetMap[$denomination])){
				$item = new stdClass();
				$item->denomination = $denomination;
				$item->count = 0;
				$item->volume = 0;
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

		$receivedDenos = array_values($receivedDenosMap);
		$changeDenos = array_values($changeDenosMap);
		$sortDenominationRows = function($a, $b){
			$aVal = (float)($a->denomination ?? 0);
			$bVal = (float)($b->denomination ?? 0);
			if($aVal == $bVal) return 0;
			return ($aVal > $bVal) ? -1 : 1;
		};
		usort($receivedDenos, $sortDenominationRows);
		usort($changeDenos, $sortDenominationRows);

		$hasChangeGiven = !empty($changeDenos) && $changeTotalVolume > 0;
	?>
	<style type="text/css">
		tbody tr{
			white-space: nowrap;
		}
		tbody tr.wrap{
			white-space: normal;
		}
	</style>
	<?php if(!empty($row)): ?>

		<div class="row col-md-9 col-lg-6 mx-auto shadow p-2 rounded">
		
			<center>
				<div class="badge">
					<img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:40px;max-height: 40px;object-fit: cover;">
					<strong style="font-size:15px; color: gray;"> <?=APP_NAME?></strong>
				</div>
				<h4 class="text-center" style="font-weight: bolder;">
					<?=strtoupper($row->category);?>
				</h4>
				<b class="text-muted"><?=$row->institution?></b>
			</center>

			<div class="d-flex justify-content-end gap-2 my-2">
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export_view/<?=esc($row->id)?>/excel" class="btn btn-sm btn-outline-success">
					<i class="fa fa-file-excel"></i> Excel
				</a>
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export_view/<?=esc($row->id)?>/pdf" class="btn btn-sm btn-outline-danger">
					<i class="fa fa-file-pdf"></i> PDF
				</a>
			</div>

			<div class="table-responsive">
				<table class="table table-striped table-hover border-bottom table-sm" style="height: fit-content !important;">
					<thead>
						<tr><th colspan="2" class="text-primary">TRANSACTION DETAILS</th></tr>
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
						<?php if(!empty($row->description)): ?>
						<tr>
							<th class="text-muted">Description: </th>
							<td><?=esc($row->description)?></td>
						</tr>
						<?php endif;?>	
						<tr>
							<th class="text-muted">Category: </th>
							<td><?=esc($row->category)?></td>
						</tr>
						<?php if(!empty($row->institution)): ?>
						<tr>
							<th class="text-muted">Institution</th>
							<td><?=esc($row->institution)?></td>
						</tr>
						<?php endif;?>
						<tr>
							<th class="text-muted">Customer's phone: </th>
							<td><?=esc($row->customer_phone)?></td>
						</tr>				
						<?php if(!empty($row->account_number)): ?>
						<tr>
							<th class="text-muted">Customer's ID: </th>
							<td><?=esc($row->customer_id)?></td>
						</tr>
						<?php endif;?>					
						<?php if(!empty($row->payment_medium)): ?>
						<tr>
							<th class="text-muted">Payment medium: </th>
							<td><?=esc($row->payment_medium)?></td>
						</tr>
						<?php endif;?>			
						<?php if(!empty($row->payment_medium) && strtolower($row->payment_medium) === 'bank' && !empty($row->bank)): ?>
						<tr>
							<th class="text-muted">Channel / Network: </th>
							<td><?=esc($row->bank)?></td>
						</tr>
						<?php elseif(!empty($row->network)): ?>
						<tr>
							<th class="text-muted">Channel / Network: </th>
							<td><?=esc($row->network)?></td>
						</tr>
						<?php endif;?>
						<?php if(!empty($row->wallet_out_id)): ?>
						<tr>
							<th class="text-muted">Paying wallet: </th>
							<td><?=esc($row->wallet_out_id)?></td>
						</tr>
						<?php endif;?>				
						<?php if(!empty($row->amount)): ?>
						<tr>
							<th class="text-muted">Amount:</th>
							<td><?=number_format($row->amount, 2)?></td>
						</tr>
						<?php endif;?>
						<?php if(isset($row->amount_paid) && $row->amount_paid !== null): ?>
						<tr>
							<th class="text-muted">Amount paid:</th>
							<td><?=number_format($row->amount_paid, 2)?></td>
						</tr>
						<?php endif;?>
						<?php if($hasChangeGiven):?>
						<tr>
							<th class="text-muted">Change Given:</th>
							<td class="text-warning"><?=number_format($changeTotalVolume, 2)?></td>
						</tr>
						<?php endif;?>
						<?php if(!empty($row->charge)): ?>
						<tr>
							<th class="text-muted">Charges:</th>
							<td><?=number_format($row->charge, 2)?></td>
						</tr>
						<?php endif;?>	
						<?php if(!empty($row->transaction_image)):?>
						<tr class="wrap">
							<th class="text-muted">Transaction message:</th>
							<td>
								<img
									id="transactionImageThumb"
									src="<?=get_image($row->transaction_image)?>"
									data-image-src="<?=get_image($row->transaction_image)?>"
									alt="Transaction message picture"
									style="cursor:pointer;width:100%;max-width:340px;max-height:260px;object-fit:contain;border:1px solid #e5e7eb;border-radius:8px;">
							</td>
						</tr>
						<?php endif;?>
						<tr><th colspan="2" class="text-primary">OTHER DETAILS</th></tr>
						<?php if(!empty($row->transaction_status)): ?>
							<tr>
								<?php
										// Determine badge class based on transaction status
										$badgeClass = '';
										switch (strtolower($row->transaction_status)) {
											case 'pending':
												$badgeClass = 'badge bg-warning text-dark';
												break;
										case 'approved':
											$badgeClass = 'badge bg-success';
											break;
										case 'rejected':
											$badgeClass = 'badge bg-danger';
											break;
										case 'declined':
										case 'reversed':
											$badgeClass = 'badge bg-danger';
											break;
										default:
											$badgeClass = 'badge bg-secondary';
												break;
										}
									?>
								<th class="text-muted">Status: </th>
								<td>
									<span class="<?= $badgeClass ?>"><?=esc($row->transaction_status)?></span>
								</td>
							</tr>
							<?php endif;?>
							<?php if(!empty($row->rejection_reason)): ?>
							<tr class="wrap">
								<th class="text-muted">Rejection Reason:</th>
								<td><?=nl2br(esc($row->rejection_reason))?></td>
							</tr>
							<?php endif;?>
							<tr class="wrap">
								<th class="text-muted">Created by:</th>
								<td>
									<span><img src="<?=get_image($row->user_image)?>" style="width: 20px; height: 20px; object-fit: cover; border-radius: 50%; margin: 0px;"></span> 
									<?=$row->user_first_name . ' ' . $row->user_last_name ?>
								</td>
							</tr>
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
				                        <h2 class="accordion-header" id="headingDenominations">
				                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDenominations" aria-expanded="false" aria-controls="collapseDenominations">
				                                View Denominations Breakdown
				                            </button>
				                        </h2>
				                        <div id="collapseDenominations" class="accordion-collapse collapse" aria-labelledby="headingDenominations" data-bs-parent="#denominationsAccordion">
				                            <div class="accordion-body">
				                            	<div class="fw-semibold text-primary mb-2"><?=$hasChangeGiven ? 'Received Denominations' : 'Denominations Breakdown'?></div>
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

				                                <?php if($hasChangeGiven):?>
				                                <div class="fw-semibold text-warning mt-3 mb-2">Change Given</div>
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
				                </div>
				            </td>
				        </tr>
				     <?php endif; ?>
					</tbody>
				</table>
			</div>
			<div class="d-flex justify-content-between">
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
					<button class="btn btn-md btn-outline-primary btn-borderless">
						<i class="fa-solid fa-chevron-left"></i> Back
					</button>
				</a>
				<?php if(user_can('edit_payment_service') && !payment_service_status_is_declined($row->transaction_status ?? '')): ?>
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$row->id?>">
						<button class="btn btn-md btn-outline-warning btn-borderless">
							<i class="fa-solid fa-pen-to-square"></i> Edit
						</button>
					</a>
				<?php endif; ?>
			</div>
		</div>

		<?php if(!empty($row->transaction_image)):?>
			<div class="modal fade" id="transactionImageModal" tabindex="-1" aria-hidden="true">
				<div class="modal-dialog modal-dialog-centered modal-lg">
					<div class="modal-content">
						<div class="modal-header">
							<h5 class="modal-title">Transaction message picture</h5>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
						</div>
						<div class="modal-body text-center">
							<img id="transactionImageModalImg" src="" alt="Transaction message picture" style="width:100%;max-height:70vh;object-fit:contain;">
						</div>
						<div class="modal-footer">
							<a id="transactionImageDownload" class="btn btn-outline-primary" href="#" download>
								<i class="fa-solid fa-download"></i> Download
							</a>
						</div>
					</div>
				</div>
			</div>
			<script>
				document.addEventListener('DOMContentLoaded', function(){
					const thumb = document.getElementById('transactionImageThumb');
					const modalEl = document.getElementById('transactionImageModal');
					const modalImg = document.getElementById('transactionImageModalImg');
					const downloadLink = document.getElementById('transactionImageDownload');
					if(!thumb || !modalEl || !modalImg || !downloadLink) return;
					const modal = new bootstrap.Modal(modalEl);
					thumb.addEventListener('click', function(){
						const src = this.getAttribute('data-image-src') || this.src;
						modalImg.src = src;
						downloadLink.href = src;
						modal.show();
					});
				});
			</script>
		<?php endif;?>

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

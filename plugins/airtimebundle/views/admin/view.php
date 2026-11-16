<?php if(user_can('view_momoremit')):?>
	<style type="text/css">
		tbody tr{
			white-space: nowrap;
		}
		tbody tr.wrap {
			white-space: normal; /* Allow wrapping only for rows with the 'wrap' class */
		}
	</style>
	<?php if(!empty($row)): ?>
		<?php
			$allDenos = $denos ?? [];
			if (!is_iterable($allDenos)) {
				$allDenos = [];
			}
			$receivedDenosMap = [];
			$changeDenosMap = [];
			$receivedTotalCount = 0;
			$receivedTotalVolume = 0.0;
			$changeTotalCount = 0;
			$changeTotalVolume = 0.0;

			foreach($allDenos as $denoRow){
				$denomination = (string)($denoRow->denomination ?? '');
				$count = (int)($denoRow->count ?? 0);
				$volume = (float)($denoRow->volume ?? 0);

				if($denomination === '' || ($count === 0 && abs($volume) < 0.005)){
					continue;
				}

				$isChangeRow = ($count < 0 || $volume < 0);
				$displayCount = abs($count);
				$displayVolume = abs($volume);
				$targetMap = $isChangeRow ? $changeDenosMap : $receivedDenosMap;

				if(!isset($targetMap[$denomination])){
					$item = new stdClass();
					$item->denomination = $denomination;
					$item->count = 0;
					$item->volume = 0.0;
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

			$sortDenominationRows = function($left, $right){
				return (float)($right->denomination ?? 0) <=> (float)($left->denomination ?? 0);
			};

			$receivedDenos = array_values($receivedDenosMap);
			$changeDenos = array_values($changeDenosMap);
			usort($receivedDenos, $sortDenominationRows);
			usort($changeDenos, $sortDenominationRows);

			$hasChangeGiven = !empty($changeDenos) && $changeTotalVolume > 0;
		?>

			
		<div class="row col-md-10 col-lg-6 mx-auto shadow p-4 rounded">
			<center>
				<div class="badge">
					<img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:40px;max-height: 40px;object-fit: cover;">
					<strong style="font-size:15px; color: gray;"> <?=APP_NAME?></strong>
				</div>
				<h4 class="text-center" style="font-weight: bolder;">
					<?=$row->network?> <?=$row->transaction_type?> 
				</h4>
			</center>

			<div class="col  d-flex table-responsive">
				<table class="table table-striped table-hover border-bottom table-sm" style="height: fit-content !important;">
						<thead>
							<tr><th colspan="2" class=" text-primary">TRANSACTION DETAILS</th></tr>
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
						<tr>
							<th class="text-muted">Type: </th>
							<td><?=esc($row->transaction_type)?></td>
						</tr>
						<tr>
							<th class="text-muted">Network: </th>
							<td><?=esc($row->network)?></td>
						</tr>
						<?php if(!empty($row->category)):?>
						<tr>
							<th class="text-muted">Category: </th>
							<td><?=esc($row->category)?></td>
						</tr>
						<?php endif;?>
						<?php if(!empty($row->transaction_image)):?>
						<tr class="wrap">
							<th class="text-muted">Transaction Image: </th>
							<td>
								<img
									id="transactionImageThumb"
									src="<?=get_image($row->transaction_image)?>"
									data-image-src="<?=get_image($row->transaction_image)?>"
									alt="Transaction image"
									style="cursor:pointer;width:100%;max-width:340px;max-height:260px;object-fit:contain;border:1px solid #e5e7eb;border-radius:8px;">
							</td>
						</tr>
						<?php endif;?>
						<tr>
							<th class="text-muted">Customer's Number: </th>
							<td><?=esc($row->phone)?></td>
						</tr>
						<tr>
							<th class="text-muted">Amount: </th>
							<td><?=number_format((float)($row->amount ?? 0), 2)?></td>
						</tr>
						<?php
							$amountToSendLabel = strtoupper(trim((string)($row->category ?? ''))) === 'JUST4U'
								? 'Amount Payable'
								: 'Load Amount';
						?>
						<?php if(!empty($row->amount_to_send) && abs((float)$row->amount_to_send - (float)($row->amount ?? 0)) > 0.01):?>
						<tr>
							<th class="text-muted"><?=$amountToSendLabel?>: </th>
							<td><?=number_format((float)($row->amount_to_send ?? 0), 2)?></td>
						</tr>
						<?php endif;?>
						<?php if($hasChangeGiven):?>
						<tr>
							<th class="text-muted">Change Given: </th>
							<td class="text-warning"><?=number_format((float)$changeTotalVolume, 2)?></td>
						</tr>
						<?php endif;?>
						<?php if(!empty($row->data_size)):?>
						<tr>
							<th class="text-muted">Data Size: </th>
							<td><?=esc($row->data_size)?></td>
						</tr>
						<?php endif;?>
						<?php if(!empty($row->commission)):?>
						<tr>
							<th class="text-muted">Commission: </th>
							<td><?=number_format((float)($row->commission ?? 0), 2)?></td>
						</tr>
						<?php endif;?>
						<?php if(!empty($row->balance_after)):?>
						<tr>
							<th class="text-muted">Balance After: </th>
							<td><?=number_format((float)($row->balance_after ?? 0), 2)?></td>
						</tr>
						<?php endif;?>
						<?php if(!empty($row->wallet_in_id)):?>
						<tr>
							<th class="text-muted">Receiving wallet: </th>						
							<td><?=esc($row->in_wallet_name ?? '')?> (<?=esc($row->in_wallet_network ?? '')?>)</td>
						</tr>
						<?php endif;?>
						<?php if(!empty($row->wallet_out_id)):?>
						<tr>
							<th class="text-muted">Paying wallet: </th>						
							<td><?=esc($row->out_wallet_name ?? '')?> (<?=esc($row->out_wallet_network ?? '')?>)</td>
						</tr>
						<?php endif;?>
						<tr><th colspan="2" class=" text-primary">OTHER DETAILS: </th></tr>
						<?php if(!empty($row->receiver)):?>
						<tr>
							<th class="text-muted">Receiver's phone: </th>
							<td><?=esc($row->receiver)?></td>
						</tr>
						<?php endif;?>			
						<?php if(!empty($row->reseller_number)):?>
						<tr>
							<th class="text-muted">Reseller's Number: </th>
							<td><?=esc($row->reseller_number)?></td>
						</tr>
						<?php endif;?>			
						<?php if(!empty($row->transaction_status)): ?>
						<tr>
							<?php
									// Determine badge class based on transaction status
									$badgeClass = '';
									switch (strtolower($row->transaction_status)) {
										case 'pending':
											$badgeClass = 'badge bg-warning text-dark';
											break;
										case 'sent':
											$badgeClass = 'badge bg-success';
											break;
										case 'rejected':
										case 'reversed':
										case 'cancelled':
										case 'canceled':
											$badgeClass = 'badge bg-danger';
											break;
										default:
											$badgeClass = 'badge bg-secondary';
											break;
									}
								?>
							<th class="text-muted">Delivery Status: </th>
							<td>
								<span class="<?= $badgeClass ?>"><?=esc($row->transaction_status ?? 'N/A')?></span>
							</td>
						</tr>
						<?php endif;?>
						<?php if(!empty($row->rejection_reason)):?>
						<tr class="wrap">
							<th class="text-muted">Rejection Reason: </th>
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
						<?php if(!empty($row->assigned)):?>
							<tr class="wrap">
								<th class="text-muted">Assigned to:</th>
								<td>
									<span><img src="<?=get_image($row->assigned_image)?>" style="width: 20px; height: 20px; object-fit: cover; border-radius: 50%; margin: 0px;"></span> 
									<?=$row->assigned_first_name . ' ' . $row->assigned_last_name ?>
								</td>
							</tr>
						<?php endif;?>
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
					                            	<?php if(!empty($receivedDenos)):?>
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
					                                <?php endif;?>
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
					     <?php elseif(!empty($row->amount_paid) || !empty($row->amount)): ?>
					        <tr>
					            <th colspan="2" class="text-primary">Denominations Breakdown</th>
					        </tr>
					        <tr>
					            <td colspan="2" class="text-muted">No denomination records were saved for this transaction.</td>
					        </tr>
					     <?php endif; ?>
					</tbody>
				</table>
			</div>
 
			<div class="d-flex justify-content-between" style="margin-top: 10px;">
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
					<button class="btn btn-md btn-outline-danger float-right" style="border:none;">
						<i class="fa-solid fa-chevron-left"></i> Back
					</button>
				</a>			
						
				<?php if (user_can('edit_category') && !in_array(strtolower(trim((string)($row->transaction_status ?? ''))), ['cancelled', 'canceled', 'declined', 'rejected', 'reversed'], true)):?>
					<?php if($row->transaction_type === 'Data bundle'):?>
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/bundle_edit/<?=$row->id?>">
						<button class="btn btn-md btn-outline-warning float-right" style="border:none;">
							<i class="fa-solid fa-pen-to-square"></i> Edit
						</button>
					</a> 
					<?php elseif($row->transaction_type === 'Airtime'):?>
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/airtime_edit/<?=$row->id?>">
						<button class="btn btn-md btn-outline-warning float-right" style="border:none;">
							<i class="fa-solid fa-pen-to-square"></i> Edit
						</button>
					</a> 
					<?php endif;?>
				<?php endif;?>
			</div>
		</div>

		<!-- Buttons for Print and Export PDF -->
		<div class="text-center mt-3">
			<button class="btn btn-danger" onclick="exportPdf(this.id)" id="<?=$row->id?>">
    			<i class="fa fa-file-pdf"></i> Export to PDF
    		</button>	
		</div>

		<?php if(!empty($row->transaction_image)):?>
			<div class="modal fade" id="transactionImageModal" tabindex="-1" aria-hidden="true">
				<div class="modal-dialog modal-dialog-centered modal-lg">
					<div class="modal-content">
						<div class="modal-header">
							<h5 class="modal-title">Transaction image</h5>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
						</div>
						<div class="modal-body text-center">
							<img id="transactionImageModalImg" src="" alt="Transaction image" style="width:100%;max-height:70vh;object-fit:contain;">
						</div>
						<div class="modal-footer">
							<a id="transactionImageDownload" class="btn btn-outline-primary" href="#" download>
								<i class="fa-solid fa-download"></i> Download
							</a>
							<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</div>
			</div>
		<?php endif;?>

		<script>

				function exportPdf(buttonId) {
				    const ID = buttonId;
				    window.open(
				        `<?=ROOT?>/admin/airtimebundle/export_pdf/${ID}`,
				        'pdf_window',
				        'width=800,height=600'
				    );
				}  

				(function(){
					const thumb = document.getElementById('transactionImageThumb');
					const modalEl = document.getElementById('transactionImageModal');
					const modalImg = document.getElementById('transactionImageModalImg');
					const downloadLink = document.getElementById('transactionImageDownload');
					if(!thumb || !modalEl || !modalImg || !downloadLink) return;

					const bootstrapModal = (window.bootstrap && window.bootstrap.Modal)
						? new window.bootstrap.Modal(modalEl)
						: null;
					thumb.addEventListener('click', function(){
						const src = this.getAttribute('data-image-src') || this.src;
						modalImg.src = src;
						downloadLink.href = src;
						if(bootstrapModal){
							bootstrapModal.show();
						}else{
							window.open(src, '_blank');
						}
					});
					if(bootstrapModal){
						modalEl.addEventListener('hidden.bs.modal', function(){
							modalImg.src = '';
							downloadLink.href = '#';
						});
					}
				})();
		</script>

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
		Access denied. You dont have permission for this action
	</div>
<?php endif?>

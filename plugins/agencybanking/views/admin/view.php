<?php if(user_can('view_agencybanking_details')):?>
	<style type="text/css">
		tbody tr{
			white-space: nowrap;
		}
		tbody tr.wrap{
			white-space: normal;
		} 

		body {
            font-family: Tahoma !important;
        }
        @media print {
            .page-break { 
                page-break-before: always; 
            }
        }
	</style>
	<?php if(!empty($row)):?>

		<div id="receipt-content" class="row col-10 col-lg-6 mx-auto shadow p-4 mb-5 rounded">
		
			<center>
				<div class="badge">
					<img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:40px;max-height: 40px;object-fit: cover;">
					<strong style="font-size:15px; color: gray;"> <?=APP_NAME?></strong>
				</div>
				<h4 class="text-center" style="font-weight: bolder;">
					Agency Banking
				</h4>
				<b class="text-warning"><?=$row->transaction_type?></b><br>
				<small><?=$row->network?></small>
			</center>

			<div class="col col-md-12 table-responsive">
				<table class="table table-striped table-hover border-bottom table-sm" style="height: fit-content !important;">
					<thead>
						<tr><th colspan="2" class="text-warning">TRANSACTION DETAILS</th></tr>
					</thead>
					<tbody>
						<tr>
							<th class="text-muted">Date (Time): </th>
							<td><?=get_date_time($row->date_created)?></td>
						</tr>
						<tr>
							<th class="text-muted">Transaction ID: </th>
							<td><?=esc($row->id)?></td>
						</tr>

						<tr>
							<th class="text-muted">Type: </th>
							<td><?=esc($row->transaction_type)?></td>
						</tr>						<tr>
							<th class="text-muted">Source: </th>
							<td><?=esc($row->network)?></td>
						</tr>
						<?php if(!empty($row->category)): ?>
						<tr>
							<th class="text-muted">Category</th>
							<td><?=esc($row->category)?></td>
						</tr>
						<?php endif;?>			
						<?php if(!empty($row->full_name)): ?>
						<tr>
							<th class="text-muted">Full name: </th>
							<td><?=esc($row->full_name)?></td>
						</tr>
						<?php endif;?>		
						<?php if($row->transaction_type === 'Account opening'): ?>
						<tr>
							<th class="text-muted">DoB: </th>
							<td><?=get_date($row->dob)?></td>
						</tr>
						<?php endif;?>
						<tr>
							<th class="text-muted">Phone: </th>
							<td><?=esc($row->phone)?></td>
						</tr>		
						<?php if(!empty($row->other_phone)): ?>
						<tr>
							<th class="text-muted">3rd Party's Number: </th>
							<td><?=esc($row->other_phone)?></td>
						</tr>
						<?php endif;?>	
						<?php if(!empty($row->house_number)): ?>
						<tr>
							<th class="text-muted">House Number: </th>
							<td><?=esc($row->house_number)?></td>
						</tr>
						<?php endif;?>		
						<?php if(!empty($row->nearest_landmark)): ?>
						<tr>
							<th class="text-muted">Nearest landmark: </th>
							<td><?=esc($row->nearest_landmark)?></td>
						</tr>
						<?php endif;?>					
						<?php if(!empty($row->account_number)): ?>
						<tr>
							<th class="text-muted">Account #: </th>
							<td><?=esc($row->account_number)?></td>
						</tr>
						<?php endif;?>					
						<?php if(!empty($row->destination_bank)): ?>
						<tr>
							<th class="text-muted">Destination bank: </th>
							<td><?=esc($row->destination_bank)?></td>
						</tr>
						<?php endif;?>				
						<?php if(!empty($row->payment_medium)): ?>
						<tr>
							<th class="text-muted">Medium Used: </th>
							<td><?=esc($row->payment_medium)?></td>
						</tr>
						<?php endif;?>			
						<?php if(!empty($row->source_bank)): ?>
						<tr>
							<th class="text-muted">Source bank: </th>
							<td><?=esc($row->source_bank)?></td>
						</tr>
						<?php endif;?>			
						<?php if(!empty($row->sender_phone)): ?>
						<tr>
							<th class="text-muted">Sender's phone: </th>
							<td><?=esc($row->sender_phone)?></td>
						</tr>
						<?php endif;?>
						<?php if(!empty($row->balance_before)): ?>
						<tr>
							<th class="text-muted">Balance before: </th>
							<td><?=number_format($row->balance_before, 2)?></td>
						</tr>
						<?php endif;?>					
						<?php if(!empty($row->amount_withdrawn)): ?>
						<tr>
							<th class="text-muted">Amount Requested</th>
							<td><?=number_format($row->amount_withdrawn, 2)?></td>
						</tr>
						<?php endif;?>				
						<?php if(!empty($row->amount_payable)): ?>
						<tr>
							<th class="text-muted">Amount After Charge</th>
							<td><?=number_format($row->amount_payable, 2)?></td>
						</tr>
						<?php endif;?>				
						<?php if(!empty($row->amount)): ?>
						<tr>
							<th class="text-muted">Amount Paid</th>
							<td><?=number_format($row->amount, 2)?></td>
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
						<?php if(!empty($row->charge)): ?>
						<tr>
							<th class="text-muted">Charges</th>
							<td><?=number_format($row->charge, 2)?></td>
						</tr>
						<?php endif;?>			
						<?php if(!empty($row->balance)): ?>
						<tr>
							<th class="text-muted">Balance : </th>
							<th><?=number_format($row->balance, 2)?></th>
						</tr>
						<?php endif;?>
						<tr><th colspan="2" class="text-warning">OTHER DETAILS</th></tr>
						<tr>
							<th class="text-muted">Slug:</th>
							<td><?=esc($row->slug)?></td>
						</tr>			
						<?php if(!empty($row->id_type)): ?>
						<tr>
							<th class="text-muted"><?=$row->id_type?></th>
							<td><?=esc($row->id_number ?? 'ID number')?></td>
						</tr>
						<?php endif;?>				
						<?php if(!empty($row->next_of_king)): ?>
						<tr>
							<th class="text-muted">NoK: </th>
							<td><?=esc($row->next_of_king)?></td>
						</tr>
						<?php endif;?>					
						<?php if(!empty($row->nok_phone)): ?>
						<tr>
							<th class="text-muted">NoK's phone: </th>
							<td><?=esc($row->nok_phone)?></td>
						</tr>
						<?php endif;?>			
						<?php if(!empty($row->nok_relation)): ?>
						<tr>
							<th class="text-muted">NoK's relation: </th>
							<td><?=esc($row->nok_relation)?></td>
						</tr>
						<?php endif;?>
						<?php if(!empty($row->ussd)): ?>
						<tr>
							<th class="text-muted">USSD used: </th>
							<td><?=esc($row->ussd)?></td>
						</tr>
						<?php endif;?>			
						<?php if(!empty($row->cash_source)): ?>
						<tr>
							<th class="text-muted">SoF: </th>
							<td><?=esc($row->cash_source)?></td>
						</tr>
						<?php endif;?>			
						<?php if(!empty($row->sender_phone)): ?>
						<tr>
							<th class="text-muted">Sender's phone: </th>
							<td><?=esc($row->sender_phone)?></td>
						</tr>
						<?php endif;?>
						<?php if(!empty($row->receiver_phone)): ?>
						<tr>
							<th class="text-muted">Receiver's phone</th>
							<td><?=esc($row->receiver_phone)?></td>
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
									case 'approved':
										$badgeClass = 'badge bg-success';
										break;
									case 'declined':
									case 'rejected':
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
					<?php if(in_array(strtolower((string)($row->transaction_status ?? '')), ['declined','rejected','reversed'], true) && !empty($row->rejection_reason)):?>
					<tr class="wrap">
						<th class="text-muted">Rejection reason:</th>
						<td><?=esc($row->rejection_reason)?></td>
					</tr>
					<?php endif;?>
					<tr class="wrap">
						<th class="text-muted">Created by:</th>
						<td>
							<span><img src="<?=get_image($row->user_image)?>" style="width: 20px; height: 20px; object-fit: cover; border-radius: 50%; margin: 0px;"></span> 
							<?=$row->user_first_name . ' ' . $row->user_last_name ?>
							<span class="badge bg-secondary text-white" style="font-size: 11px; font-weight:bolder;"><?=get_date_time($row->date_created)?></span>
						</td>
					</tr>
					<?php if(!empty($row->date_updated)):?>
					<tr class="wrap">
						<th class="text-muted">Last updated:</th>
						<td>
							<span><img src="<?=get_image($row->updated_by_image)?>" style="width: 20px; height: 20px; object-fit: cover; border-radius: 50%; margin: 0px;"></span> 
							<?=$row->updated_by_first_name . ' ' . $row->updated_by_last_name ?>
							<span class="badge bg-secondary text-white" style="font-size: 11px; font-weight:bolder;"><?=get_date_time($row->date_updated)?></span>
						</td>
					</tr>
					<?php endif;?>
						 <?php if (!empty($denos)): ?>
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
					                                <table class="table table-bordered table-hover table-sm">
					                                    <thead>
					                                        <tr>
					                                            <th>DENOMINATION</th>
					                                            <th>COUNT</th>
					                                            <th>VOLUME</th>
					                                        </tr>
					                                    </thead>
					                                    <tbody>
					                                        <?php foreach ($denos as $deno): ?>
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
					                                            <th><?=number_format($totalCount ?? 0)?></th>
					                                            <th><?=number_format($totalVolume ?? 0, 2)?></th>
					                                    	</tr>
					                                    </tfoot>
					                                </table>
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
					<button class="btn btn-md btn-outline-danger btn-borderless" style="border:none;">
						<i class="fa-solid fa-chevron-left"></i> Back
					</button>
				</a>
				<?php if(user_can('edit_agencybanking') && !in_array(strtolower((string)($row->transaction_status ?? '')), ['declined','rejected','reversed'], true)):?>
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$row->id?>">
					<button class="btn btn-md btn-outline-warning btn-borderless" style="border:none;">
						<i class="fa-solid fa-pen-to-square"></i> Edit
					</button>
				</a>
				<!-- <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/delete/<?=$row->id?>">
					<button class="btn btn-md btn-outline-danger btn-borderless" style="border:none;">
						<i class="fa-solid fa-trash"></i> Delete
					</button>
				</a> -->
				<?php endif;?>
			</div>
		</div>

		<!-- Buttons for Print and Export PDF -->
		<div class="text-center mt-3">
			<button class="btn btn-danger export-pdf-btn" onclick="exportPdf(this.id)" id="<?=$row->id?>">
    			<i class="fa fa-file-pdf"></i> Export to PDF
    		</button>	
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
							<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</div>
			</div>
		<?php endif;?>

		<script>

				function exportPdf(buttonId) {
				    // Add loading state
				    const btn = document.querySelector('.export-pdf-btn');
				    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating...';

				    const ID = buttonId;
				    window.open(
				        `<?=ROOT?>/admin/agencybanking/export_pdf/${ID}`,
				        'pdf_window',
				        'width=800,height=600'
				    );
				    
				    // Reset button after 5 seconds
				    setTimeout(() => {
				        btn.innerHTML = '<i class="fa fa-file-pdf"></i> Export to PDF';
				    }, 5000);
				}  

				(function(){
					const thumb = document.getElementById('transactionImageThumb');
					const modalEl = document.getElementById('transactionImageModal');
					const modalImg = document.getElementById('transactionImageModalImg');
					const downloadLink = document.getElementById('transactionImageDownload');
					if(!thumb || !modalEl || !modalImg || !downloadLink) return;

					const hasBootstrapModal = (typeof window.bootstrap !== 'undefined') && window.bootstrap && window.bootstrap.Modal;
					const bootstrapModal = hasBootstrapModal ? new window.bootstrap.Modal(modalEl) : null;
					let backdropEl = null;
					let escHandler = null;

					const resetModalState = () => {
						modalImg.src = '';
						downloadLink.href = '#';
					};

					const hideFallback = () => {
						modalEl.classList.remove('show');
						modalEl.style.display = 'none';
						modalEl.setAttribute('aria-hidden', 'true');
						modalEl.removeAttribute('aria-modal');
						document.body.classList.remove('modal-open');
						if(backdropEl){
							backdropEl.remove();
							backdropEl = null;
						}
						if(escHandler){
							document.removeEventListener('keydown', escHandler);
							escHandler = null;
						}
						resetModalState();
					};

					const showFallback = () => {
						modalEl.style.display = 'block';
						modalEl.classList.add('show');
						modalEl.removeAttribute('aria-hidden');
						modalEl.setAttribute('aria-modal', 'true');
						document.body.classList.add('modal-open');
						backdropEl = document.createElement('div');
						backdropEl.className = 'modal-backdrop fade show';
						document.body.appendChild(backdropEl);
						backdropEl.addEventListener('click', hideFallback);
						escHandler = (e) => {
							if(e.key === 'Escape'){
								hideFallback();
							}
						};
						document.addEventListener('keydown', escHandler);
					};

					const showModal = () => {
						if(bootstrapModal){
							bootstrapModal.show();
							return;
						}
						showFallback();
					};

					thumb.addEventListener('click', function(){
						const src = this.getAttribute('data-image-src') || this.src;
						modalImg.src = src;
						downloadLink.href = src;
						showModal();
					});

					if(bootstrapModal){
						modalEl.addEventListener('hidden.bs.modal', resetModalState);
					} else {
						modalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach((btn) => {
							btn.addEventListener('click', hideFallback);
						});
						modalEl.addEventListener('click', function(e){
							if(e.target === modalEl){
								hideFallback();
							}
						});
					}
				})();
		</script>


	<?php else:?>
		<div class="alert alert-danger text-center">
			That record was not found!
		</div>
		
		<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
			<button class="btn btn-outline-danger" style="border:none;">Back</button>
		</a>
	<?php endif?>

<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You don't have permission for this action.
	</div>
<?php endif?>

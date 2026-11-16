<?php if(user_can('view_ezwich_details')): ?>
	<style type="text/css">
		tbody tr{
			white-space: nowrap;
		}
		tbody tr.wrap{
			white-space: normal;
		}
	</style>
	<?php if(!empty($row)):?>
		<?php $viewStatus = ezwich_normalize_status($row->transaction_status ?? '', 'Draft'); ?>

		<div class="row g-3 col-md-8 col-lg-6 mx-auto shadow p-2 rounded">
		
			<center>
				<div class="badge">
					<img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:40px;max-height: 40px;object-fit: cover;">
					<strong style="font-size:15px; color: gray;"> <?=APP_NAME?></strong>
				</div>
				<h4 class="text-center" style="font-weight: bolder;">
					Ezwich Transaction
				</h4>
				<b class="text-muted"><?=$row->transaction_type?></b><br>
				<small><?=$row->network?></small>
			</center>

			<div class="col table-responsive">
				<table class="table table-striped table-hover border-bottom col-lg-6 col-md-12 table-sm" style="height: fit-content !important;">
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
						<tr>
							<th class="text-muted">Reference ID: </th>
							<td><?=esc($row->transaction_id)?></td>
						</tr>
						<tr>
							<th class="text-muted">Type: </th>
							<td><?=esc($row->transaction_type)?></td>
						</tr>
						<?php if(!empty($row->usn)): ?>
						<tr>
							<th class="text-muted">USN</th>
							<td><?=esc($row->usn)?></td>
						</tr>
						<?php endif;?>
						<tr>
							<th class="text-muted">Phone: </th>
							<td><?=esc($row->phone)?></td>
						</tr>				
						<?php if(!empty($row->account_number)): ?>
						<tr>
							<th class="text-muted">Account #: </th>
							<td><?=esc($row->account_number)?></td>
						</tr>
						<?php endif;?>					
						<?php if(!empty($row->amount_payable)): ?>
						<tr>
							<th class="text-muted">Amount Payable: </th>
							<td><?=number_format($row->amount_payable, 2)?></td>
						</tr>
						<?php endif;?>					
						<?php if(!empty($row->amount)): ?>
						<tr>
							<th class="text-muted">Amount Paid</th>
							<td><?=number_format($row->amount, 2)?></td>
						</tr>
						<?php endif;?>
						<?php
							$ezwichPhotos = [];
							if(!empty($row->momo_image)){
								$ezwichPhotos[] = ['label' => 'MoMo image', 'path' => $row->momo_image];
							}
							if(!empty($row->merchant_receipt)){
								$ezwichPhotos[] = ['label' => 'Merchant receipt', 'path' => $row->merchant_receipt];
							}
							if(!empty($row->customer_receipt)){
								$ezwichPhotos[] = ['label' => 'Customer receipt', 'path' => $row->customer_receipt];
							}
						?>
						<?php if(!empty($ezwichPhotos)):?>
						<tr class="wrap">
							<th class="text-muted">Transaction photos:</th>
							<td>
								<div class="row g-2">
									<?php foreach($ezwichPhotos as $p):?>
										<?php $src = get_image($p['path']); ?>
										<div class="col-12 col-md-6">
											<div style="border:1px solid #e5e7eb;border-radius:10px;padding:8px;">
												<div class="text-muted" style="font-size:12px;font-weight:600;margin-bottom:6px;"><?=esc($p['label'])?></div>
												<img
													class="js-ezwich-image-thumb"
													src="<?=$src?>"
													data-image-src="<?=$src?>"
													data-image-title="<?=esc($p['label'])?>"
													alt="<?=esc($p['label'])?>"
													style="cursor:pointer;width:100%;max-height:260px;object-fit:contain;border-radius:8px;">
											</div>
										</div>
									<?php endforeach;?>
								</div>
							</td>
						</tr>
						<?php endif;?>
						<?php if(!empty($row->charge)): ?>
						<tr>
							<th class="text-muted">Charges</th>
							<td><?=number_format($row->charge, 2)?></td>
						</tr>
						<?php endif;?>			
						<!-- <?php if(!empty($row->balance)): ?>
						<tr>
							<th class="text-muted">Balance After: </th>
							<th><?=number_format($row->balance, 2)?></th>
						</tr>
						<?php endif;?> -->
						<tr><th colspan="2" class="text-primary">OTHER DETAILS</th></tr>
						<?php if(!empty($row->receiving)): ?>
						<tr>
							<th class="text-muted">Transfer to:</th>
							<td><?=esc($row->receiving)?> ( <?=esc($row->receiving_sim)?> )</td>
						</tr>	
						<?php endif;?>
						<?php if(!empty($row->transaction_status)): ?>
						<tr>
							<?php
									// Determine badge class based on transaction status
									$badgeClass = '';
									switch (strtolower($viewStatus)) {
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
											$badgeClass = 'badge bg-danger';
											break;
										case 'cancelled':
										case 'canceled':
											$badgeClass = 'badge bg-danger';
											break;
										default:
											$badgeClass = 'badge bg-secondary';
											break;
									}
								?>
							<th class="text-muted">Status: </th>
							<td>
								<span class="<?= $badgeClass ?>"><?=esc($viewStatus)?></span>
							</td>
						</tr>
						<?php endif;?>
						<?php if(!empty($row->rejection_reason)): ?>
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
				                                            <td><?=$deno->currency ?? 'CFA'?><?=esc($deno->denomination ?? '')?></td>
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
					<button class="btn btn-md btn-outline-primary btn-borderless">
						<i class="fa-solid fa-chevron-left"></i> Back
					</button>
				</a>
				<?php if(user_can('edit_ezwich') && !in_array(strtolower(trim((string)$viewStatus)), ['cancelled', 'canceled', 'declined', 'rejected', 'reversed'], true)):?>
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$row->id?>">
					<button class="btn btn-md btn-outline-warning btn-borderless">
						<i class="fa-solid fa-pen-to-square"></i> Edit
					</button>
				</a>
			<?php endif;?>
			</div>
		</div>

		<!-- Buttons for Print and Export PDF -->
		<div class="text-center mt-3">
			<button class="btn btn-danger export-pdf-btn" onclick="exportPdf(this.id)" id="<?=$row->id?>">
    			<i class="fa fa-file-pdf"></i> Export to PDF
    		</button>	
		</div>

		<?php if(!empty($row->momo_image) || !empty($row->merchant_receipt) || !empty($row->customer_receipt)):?>
			<div class="modal fade" id="ezwichImageModal" tabindex="-1" aria-hidden="true">
				<div class="modal-dialog modal-dialog-centered modal-lg">
					<div class="modal-content">
						<div class="modal-header">
							<h5 class="modal-title" id="ezwichImageModalTitle">Transaction photo</h5>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
						</div>
						<div class="modal-body text-center">
							<img id="ezwichImageModalImg" src="" alt="Transaction photo" style="width:100%;max-height:70vh;object-fit:contain;">
						</div>
						<div class="modal-footer">
							<a id="ezwichImageDownload" class="btn btn-outline-primary" href="#" download>
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
			        `<?=ROOT?>/admin/ezwich/export_pdf/${ID}`,
			        'pdf_window',
			        'width=800,height=600'
			    );
			    
			    // Reset button after 5 seconds
			    setTimeout(() => {
			        btn.innerHTML = '<i class="fa fa-file-pdf"></i> Export to PDF';
			    }, 8000);
			}

			(function(){
				const thumbs = document.querySelectorAll('.js-ezwich-image-thumb');
				const modalEl = document.getElementById('ezwichImageModal');
				const modalImg = document.getElementById('ezwichImageModalImg');
				const modalTitle = document.getElementById('ezwichImageModalTitle');
				const downloadLink = document.getElementById('ezwichImageDownload');
				if(!thumbs || thumbs.length === 0 || !modalEl || !modalImg || !downloadLink) return;

				const hasBootstrapModal = (typeof window.bootstrap !== 'undefined') && window.bootstrap && window.bootstrap.Modal;
				const bootstrapModal = hasBootstrapModal ? new window.bootstrap.Modal(modalEl) : null;
				let backdropEl = null;
				let escHandler = null;

				const resetModalState = () => {
					modalImg.src = '';
					downloadLink.href = '#';
					if(modalTitle) modalTitle.textContent = 'Transaction photo';
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

				thumbs.forEach((thumb) => {
					thumb.addEventListener('click', function(){
						const src = this.getAttribute('data-image-src') || this.src;
						const title = this.getAttribute('data-image-title') || 'Transaction photo';
						modalImg.src = src;
						downloadLink.href = src;
						if(modalTitle) modalTitle.textContent = title;
						showModal();
					});
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
			<button class="btn btn-primary">Back</button>
		</a>
	<?php endif?>

<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You don't have permission for this action.
	</div>
<?php endif?>

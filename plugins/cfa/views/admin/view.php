<?php if(user_can('view_agencybanking_details')):?>
	<style type="text/css">
		tbody tr{
			white-space: nowrap;
		}
		tbody tr.wrap {
			white-space: normal; /* Allow wrapping only for rows with the 'wrap' class */
		}
	</style>
	<?php if(!empty($row)): ?>

			
		<div class="row g-3 col-md-8 col-lg-6 mx-auto shadow p-2 rounded">
			<center>
				<div class="badge">
					<img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:40px;max-height: 40px;object-fit: cover;">
					<strong style="font-size:15px; color: gray;"> <?=APP_NAME?></strong>
				</div>
				<h4 class="text-center" style="font-weight: bolder;">
					Transaction Details
				</h4>
				<b class="text-muted"><?=$row->transaction_type?></b><br>
				<small>Transaction Id: <?=$row->id?></small>
			</center>

			<div class="col  table-responsive">
				<table class="table table-striped table-hover border-bottom table-sm" style="height: fit-content !important;">
						<thead>
							<tr><th colspan="2" class=" text-warning">TRANSACTION DETAILS</th></tr>
						</thead>
						<tbody>
							<tr>
								<th class="text-muted">Date: </th>
								<td><?=get_date_time($row->date_created)?></td>
							</tr>
							<tr>
								<th class="text-muted">Transaction Id: </th>
								<td><?=esc($row->id)?></td>
							</tr>
							<tr>
								<th class="text-muted">Transaction type: </th>
								<td><?=esc($row->transaction_type)?></td>
							</tr>

							<tr>
								<th class="text-muted">Description: </th>
								<td><?=esc($row->description)?></td>
							</tr>
                            <?php if (!empty($row->customer_phone)): ?>
							<tr>
								<th class="text-muted">Customer: </th>
								<td><?=esc($row->customer_phone)?></td>
							</tr>
							<?php endif;?>							
							<?php if (!empty($row->buying_rate)): ?>							
								<tr>
									<th class="text-muted">Buying Rate: </th>
									<td><?=number_format($row->buying_rate, 2)?></td>
								</tr>
							<?php endif ?>
							<?php if (!empty($row->selling_rate)): ?>							
								<tr>
									<th class="text-muted">Selling Rate: </th>
									<td><?=number_format($row->selling_rate, 2)?></td>
								</tr>
							<?php endif ?>
							<tr>
								<th class="text-muted">Amount (CFA): </th>
								<td><?=number_format($row->amount_cfa, 2)?></td>
							</tr>
							<tr>
								<th class="text-muted">Amount (GHS): </th>
								<td><?=number_format($row->amount_ghs, 2)?></td>
							</tr>
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
							<tr><th colspan="2" class=" text-warning">OTHER DETAILS: </th></tr>
							<tr>
								<th class="text-muted">Status: </th>
								<td>
									<?php
										// Determine badge class based on transaction status
										$badgeClass = '';
										switch (strtolower($row->transaction_status ?? 'complete')) {
											case 'pending':
												$badgeClass = 'badge bg-warning text-dark';
												break;
											case 'complete':
												$badgeClass = 'badge bg-success';
												break;
											case 'rejected':
											case 'cancelled':
											case 'canceled':
												$badgeClass = 'badge bg-danger';
												break;
											default:
												$badgeClass = 'badge bg-secondary';
												break;
										}
									?>
								<span class="<?= $badgeClass ?>"><?=esc($row->transaction_status)?></span>
								</td>
							</tr>
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
					                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDenominationsGhs" aria-expanded="false" aria-controls="collapseDenominationsGhs">
					                                CFA Denominations Breakdown
					                            </button>
					                        </h2>
					                        <div id="collapseDenominationsGhs" class="accordion-collapse collapse" aria-labelledby="headingDenominations" data-bs-parent="#denominationsAccordion">
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
					                                        	<?php if ($deno->currency == "CFA"): ?>
							                                        <tr>
							                                            <td><?=$deno->currency ?? 'CFA'?><?=esc($deno->denomination ?? '')?></td>
							                                            <td><?=number_format($deno->count ?? 0)?></td>
							                                            <td><?=number_format($deno->volume ?? 0, 2)?></td>
							                                        </tr>					                                        		
					                                        	<?php endif ?>
					                                        <?php endforeach; ?>
					                                    </tbody>
					                                    <tfoot>
					                                    	<tr class="text-primary">
					                                            <th>SUMMARY</th>
					                                            <th><?=number_format($totalCountCfa ?? 0)?></th>
					                                            <th><?=number_format($totalVolumeCfa ?? 0, 2)?></th>
					                                    	</tr>
					                                    </tfoot>
					                                </table>
					                            </div>
					                        </div>
					                    </div>
					                </div>
					            </td>
					        </tr>
					        <tr>
					            <td colspan="2">
					                <div class="accordion accordion-flush" id="denominationsAccordion">
					                    <div class="accordion-item">
					                        <h2 class="accordion-header" id="headingDenominations">
					                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDenominationscFA" aria-expanded="false" aria-controls="collapseDenominationscFA">
					                                GHS Denominations Breakdown
					                            </button>
					                        </h2>
					                        <div id="collapseDenominationscFA" class="accordion-collapse collapse" aria-labelledby="headingDenominations" data-bs-parent="#denominationsAccordion">
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
					                                        	<?php if ($deno->currency == "GHS"): ?>
							                                        <tr>
							                                            <td><?=$deno->currency ?? 'CFA'?><?=esc($deno->denomination ?? '')?></td>
							                                            <td><?=number_format($deno->count ?? 0)?></td>
							                                            <td><?=number_format($deno->volume ?? 0, 2)?></td>
							                                        </tr>					                                        		
					                                        	<?php endif ?>
					                                        <?php endforeach; ?>
					                                    </tbody>
					                                    <tfoot>
					                                    	<tr class="text-primary">
					                                            <th>SUMMARY</th>
					                                            <th><?=number_format($totalCountGhs ?? 0)?></th>
					                                            <th><?=number_format($totalVolumeGhs ?? 0, 2)?></th>
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
			<div class="d-flex justify-content-between" style="margin-top: 10px;">
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
					<button class="btn btn-md btn-outline-danger float-right" style="border:none;">
						<i class="fa-solid fa-chevron-left"></i> Back
					</button>
				</a>
				<?php if (!in_array(strtolower(trim((string)($row->transaction_status ?? ''))), ['complete', 'cancelled', 'canceled', 'rejected', 'declined', 'reversed'], true)): ?>
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$row->id?>">
						<button class="btn btn-md btn-outline-warning float-right" style="border:none;">
							<i class="fa-solid fa-pen-to-square"></i> Edit
						</button>
					</a>
				<?php endif ?>
			</div>
		</div>

		<!-- Buttons for Print and Export PDF -->
		<div class="text-center mt-3">
			<button class="btn btn-danger export-pdf-btn" onclick="exportPdf(this.id)" id="<?=$row->id?>">
    			<i class="fa fa-file-pdf"></i> Export to PDF
    		</button>	
		</div>

		<script>

			function exportPdf(buttonId) {
			    // Add loading state
			    const btn = document.querySelector('.export-pdf-btn');
			    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating...';

			    const ID = buttonId;
			    window.open(
			        `<?=ROOT?>/admin/cfa/export_pdf/${ID}`,
			        'pdf_window',
			        'width=800,height=600'
			    );
			    
			    // Reset button after 5 seconds
			    setTimeout(() => {
			        btn.innerHTML = '<i class="fa fa-file-pdf"></i> Export to PDF';
			    }, 8000);
			}  
		</script>

	<?php else:?>
		<div class="alert alert-danger text-center">
			That record was not found!
		</div>
		
		<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
			<button class="btn btn-warning">Back</button>
		</a>
	<?php endif?>

<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You don't have permission for this action.
	</div>
<?php endif?>

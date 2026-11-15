<?php

        //mypayments tab data
				$query = "
			    SELECT 
			        m.*, 
			        u.first_name AS user_first_name, 
			        u.last_name AS user_last_name, 
			        u.image AS user_image,
			        w.name AS wallet_out_name,
			        w.network AS wallet_out_network,
			        u.image AS user_image,
			        u_updated.first_name AS updated_by_first_name, 
			        u_updated.last_name AS updated_by_last_name, 
			        u_updated.image AS updated_by_image 
			    FROM payment_services AS m 
			    LEFT JOIN mywallets AS w ON m.wallet_out_id = w.id 
			    LEFT JOIN users AS u ON m.created_by = u.id 
			    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id  
			    WHERE m.id = :id 
			    LIMIT 1";

				$paymentrow = $payment_service->query($query, ['id'=>$row->id])[0];

			//$paymentrow = $payment->query($query, ['id'=>$rowId])[0];
    ?>
    <!-- Calculator modal -->
      <div class="modal fade" id="staticBackdrop<?=$row->id?>" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel<?=$row->id?>" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header  w-100">
              <center class=" mx-auto">
                  <div class="badge">
											<img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:40px;max-height: 40px;object-fit: cover;">
											<strong style="font-size:15px; color: gray;"> <?=APP_NAME?></strong>
										</div>
                  	<h3 class="modal-title" id="staticBackdropLabel">PAYMENT</h3>
										<h5 class="text-center fw-bolder"><?=strtoupper($paymentrow->category ?? '')?></h5>
										<h6>Agent: <?=esc($paymentrow->user_first_name ?? '')?> <?=esc($paymentrow->user_last_name ?? '')?></h6>
                  <p><?=get_date_time($paymentrow->date_created ?? '')?></p>
              </center>
            </div>

            <!-- Modal Body -->
            <div class="modal-body">
             <?php if(!empty($paymentrow)): ?>

				<div class="row g-3 mx-auto p-4 rounded">
					<div class="col col-12 d-flex table-responsive">
						<table class="table table-striped table-hover border-bottom table-sm" style="height: fit-content !important;">
								<tbody>	
									<tr><th colspan="2" class="text-primary">TRANSACTION DETAILS</th></tr>
									<tr>
										<th class="text-muted">Date: </th>
										<td><?=get_date_time($paymentrow->date_created)?></td>
									</tr>					
									<?php if(!empty($paymentrow->description)): ?>
									<tr>
										<th class="text-muted">Description: </th>
										<td style="white-space:wrap;"><?=esc($paymentrow->description)?></td>
									</tr>
									<?php endif;?>	
									<tr>
										<th class="text-muted">Category: </th>
										<td><?=esc($paymentrow->category)?></td>
									</tr>
									<?php if(!empty($paymentrow->institution)): ?>
									<tr>
										<th class="text-muted">Institution</th>
										<td style="white-space:wrap;"><?=esc($paymentrow->institution)?></td>
									</tr>
									<?php endif;?>
									<tr>
										<th class="text-muted">Customer's phone: </th>
										<td><?=esc($paymentrow->customer_phone)?></td>
									</tr>				
									<?php if(!empty($paymentrow->account_number)): ?>
									<tr>
										<th class="text-muted">Customer's ID: </th>
										<td><?=esc($paymentrow->customer_id)?></td>
									</tr>
									<?php endif;?>					
									<?php if(!empty($paymentrow->payment_medium)): ?>
									<tr>
										<th class="text-muted">Payment medium: </th>
										<td><?=esc($paymentrow->payment_medium)?></td>
									</tr>
									<?php endif;?>			
									<?php if(!empty($paymentrow->wallet_out_id)): ?>
									<tr>
										<th class="text-muted">Paying wallet: </th>
										<td><?=esc($paymentrow->wallet_out_name ?? 'N/A')?> (<?=esc($paymentrow->wallet_out_network ?? '')?>)</td>
									</tr>
									<?php endif;?>				
									<?php if(!empty($paymentrow->amount)): ?>
									<tr>
										<th class="text-muted">Amount:</th>
										<td><?=number_format($paymentrow->amount, 2)?></td>
									</tr>
									<?php endif;?>
									<?php if(!empty($paymentrow->charge)): ?>
									<tr>
										<th class="text-muted">Charges:</th>
										<td><?=number_format($paymentrow->charge, 2)?></td>
									</tr>
									<?php endif;?>
									<tr><th colspan="2" class="text-primary">OTHER DETAILS</th></tr>
									<?php if(!empty($paymentrow->transaction_status)): ?>
									<tr>
										<?php
												// Determine badge class based on transaction status
												$badgeClass = '';
												switch (strtolower($paymentrow->transaction_status)) {
													case 'pending':
														$badgeClass = 'badge bg-warning text-dark';
														break;
													case 'approved':
														$badgeClass = 'badge bg-success';
														break;
													case 'rejected':
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
											<span class="<?= $badgeClass ?>"><?=esc($paymentrow->transaction_status)?></span>
										</td>
									</tr>
									<?php endif;?>
									<tr class="wrap">
										<th class="text-muted">Created by:</th>
										<td>
											<span><img src="<?=get_image($paymentrow->user_image)?>" style="width: 20px; height: 20px; object-fit: cover; border-radius: 50%; margin: 0px;"></span> 
											<?=$paymentrow->user_first_name . ' ' . $paymentrow->user_last_name ?>
										</td>
									</tr>
									<?php if(!empty($paymentrow->date_updated)):?>
									<tr class="wrap">
										<th class="text-muted">Last updated:</th>
										<td><?=get_date_time($paymentrow->date_updated)?></td>
									</tr>
									<tr class="wrap">
										<th class="text-muted">Last updated by:</th>
										<td>
											<span><img src="<?=get_image($paymentrow->updated_by_image)?>" style="width: 20px; height: 20px; object-fit: cover; border-radius: 50%; margin: 0px;"></span> 
											<?=$paymentrow->updated_by_first_name . ' ' . $paymentrow->updated_by_last_name ?>
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
				</div>
			<?php endif?>
            </div>
            <div class="modal-footer no-print">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              <button id="printModal<?=$paymentrow->id?>" class="btn btn-sm btn-outline-secondary col-2" onclick="printModalContent('staticBackdrop<?=$paymentrow->id?>')">
                  <i class="fas fa-print"></i> Print
              </button>
          </div>
          </div>
        </div>
      </div>

      <script type="text/javascript">               
        function printModalContent(modalId) {
            // Get the modal content by ID
            const modal = document.getElementById(modalId);
            if (!modal) {
                console.error('Modal not found' + modalId);
                return;
            }

            // Clone the modal content
            const modalContent = modal.querySelector('.modal-content').cloneNode(true);

            // Create a new printable container
            const printContainer = document.createElement('div');
            printContainer.id = 'printableContent';
            printContainer.style.position = 'absolute';
            printContainer.style.top = '0';
            printContainer.style.left = '0';
            printContainer.style.width = '100%';
            printContainer.appendChild(modalContent);

            // Append the container to the body
            document.body.appendChild(printContainer);

            // Trigger print action
            window.print();

            // Remove the printable container after printing
            document.body.removeChild(printContainer);
        }
      </script>

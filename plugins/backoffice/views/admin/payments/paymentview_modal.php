<?php

        //mypayments tab data
		$query = "
			    SELECT 
			        m.*, 
			        u.first_name AS user_first_name, 
			        u.last_name AS user_last_name, 
			        u.image AS user_image,
			        u_updated.first_name AS updated_by_first_name, 
			        u_updated.last_name AS updated_by_last_name, 
			        u_updated.image AS updated_by_image,
			        u_payto.first_name AS payto_first_name, 
			        u_payto.last_name AS payto_last_name, 
			        u_payto.image AS payto_image,
		            out_wallet.name AS out_wallet_name, 
		            out_wallet.network AS out_wallet_network,
		            receiving_wallet.name AS receiving_wallet_name, 
		            receiving_wallet.network AS receiving_wallet_network
			    FROM payments_backoffice AS m 
			    LEFT JOIN users AS u ON m.created_by = u.id 
			    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			    LEFT JOIN users AS u_payto ON m.pay_to = u_payto.id 
		        LEFT JOIN mywallets AS out_wallet ON m.wallet_out_id = out_wallet.id  
		        LEFT JOIN mywallets AS receiving_wallet ON m.receiving_wallet = receiving_wallet.id  
		        WHERE m.id = :id
			";

			$paymentrow = $payment->query($query, ['id'=>$rowId])[0];
			$paymentBreakdown = function_exists('backoffice_payment_row_breakdown') ? backoffice_payment_row_breakdown($paymentrow) : ['items' => [], 'total' => 0.0];
			$paymentBreakdownItems = $paymentBreakdown['items'] ?? [];
    ?>
    <!-- Calculator modal -->
      <div class="modal fade" id="staticBackdrop<?=$row->id?>" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel<?=$row->id?>" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content p-0">
            <!-- Modal Header -->
            <div class="modal-header  w-100">
              <center class=" mx-auto">
                  <div class="badge">
											<strong style="font-size:15px; color: gray;"> <?=APP_NAME?></strong>
										</div>
                  	<h3 class="modal-title" id="staticBackdropLabel">PAYMENT</h3>
										<h5 class="text-center">
											<?=ucfirst($paymentrow->network)?> PAYMENT
										</h5>
										<h5><?=esc($paymentrow->user_first_name ?? '')?> <?=esc($paymentrow->user_last_name ?? '')?></h5><br>
                  <p><?=get_date_time($paymentrow->date_created ?? '')?></p>
              </center>
            </div>

          <!-- Modal Body -->
          <div class="modal-body">
             <?php if(!empty($paymentrow)): ?>
						<table class="table table-striped table-hover border-bottom table-sm" style="height: fit-content !important;">
							<thead>
								<tr><th colspan="2" class="text-primary">PAYMENT DETAILS</th></tr>
							</thead>
							<tbody>
								<tr>
									<td class="text-muted">Payment type: </td>
									<td><?=esc($paymentrow->payment_type ?? '')?> <?=esc($paymentrow->network ?? '')?></td>
								</tr>
								<tr>
									<td class="text-muted">Amount: </td>
									<td><?=number_format($paymentrow->amount ?? 0, 2)?></td>
								</tr>	
								<?php if(!empty($paymentBreakdownItems)): ?>
									<tr>
										<td class="text-muted">Bulk breakdown:</td>
										<td style="white-space:normal;">
											<div class="table-responsive">
												<table class="table table-sm mb-0">
													<thead><tr><th>Payment Type</th><th class="text-end">Amount</th><th>Receipt</th></tr></thead>
													<tbody>
														<?php foreach($paymentBreakdownItems as $item): ?>
															<?php $receiptUrl = !empty($item['receipt_photo']) ? get_image($item['receipt_photo']) : ''; ?>
															<tr>
																<td><?=esc($item['channel'] ?? '')?></td>
																<td class="text-end"><?=number_format((float)($item['amount'] ?? 0), 2)?></td>
																<td>
																	<?php if($receiptUrl !== ''): ?>
																		<a href="<?=esc($receiptUrl)?>" target="_blank">Open</a>
																	<?php else: ?>
																		<span class="text-muted">Not uploaded</span>
																	<?php endif; ?>
																</td>
															</tr>
														<?php endforeach; ?>
													</tbody>
												</table>
											</div>
										</td>
									</tr>
								<?php endif; ?>
								<tr>
									<td class="text-muted">Paying wallet: </td>
									<td><?=esc($paymentrow->out_wallet_name ?? '')?></td>
								</tr>
								<tr>
									<td class="text-muted">Receiving wallet: </td>
									<td><?=esc($paymentrow->receiving_wallet_name ?? '')?></td>
								</tr>
								<?php if(!empty($paymentrow->description)): ?>
									<tr>
										<td class="text-muted">Description:</td>
										<td style="white-space:normal;"><?=esc($paymentrow->description)?></td>
									</tr>
								<?php endif;?>	
								<?php if(!empty($paymentrow->pay_to)): ?>
								<tr>
									<td class="text-muted">Paid to:</td>
									<td>
										<span><img src="<?=get_image($paymentrow->payto_image)?>" style="width: 20px; height: 20px; object-fit: cover; border-radius: 50%; margin: 0px;"></span> 
										<?=$paymentrow->payto_first_name . ' ' . $paymentrow->payto_last_name ?>
									</td>
								</tr>	
								<?php endif;?>
								<?php if(!empty($paymentrow->assigned)): ?>
								<tr>
									<td class="text-muted">Assigned to:</td>
									<td>
										<span><img src="<?=get_image($paymentrow->assigned_image)?>" style="width: 20px; height: 20px; object-fit: cover; border-radius: 50%; margin: 0px;"></span> 
										<?=$paymentrow->assigned_first_name . ' ' . $paymentrow->assigned_last_name ?>
									</td>
								</tr>
								<?php endif;?>	
								<?php if(!empty($paymentrow->date_created)): ?>
									<tr>
										<td class="text-muted">Created by:</td>
										<td>
											<span><img src="<?=get_image($paymentrow->user_image)?>" style="width: 20px; height: 20px; object-fit: cover; border-radius: 50%; margin: 0px;"></span> 
											<?=$paymentrow->user_first_name . ' ' . $paymentrow->user_last_name ?>
										</td>
									</tr>
									<tr>
										<td class="text-muted">Date created</td>
										<td><?=get_date_time($paymentrow->date_created, 2)?></td>
									</tr>
								<?php endif;?>

									<?php if(!empty($paymentrow->date_updated)):?>
									<tr >
										<td class="text-muted">Last updated:</td>
										<td><?=get_date_time($paymentrow->date_updated)?></td>
									</tr>

									<tr >
										<td class="text-muted">Updated by:</td>
										<td>
											<span><img src="<?=get_image($paymentrow->updated_by_image)?>" style="width: 20px; height: 20px; object-fit: cover; border-radius: 50%; margin: 0px;"></span> 
											<?=$paymentrow->updated_by_first_name . ' ' . $paymentrow->updated_by_last_name ?>
										</td>
									</tr>
								<?php endif;?>

								<?php if(!empty($paymentrow->transaction_status)): ?>
								<tr>
									<?php
										// Determine badge class based on transaction status
										$badgeClass = '';
										switch (strtolower($paymentrow->transaction_status ?? '')) {
											
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
									<td class="text-muted">Status: </td>
									<td>
										<span class="<?= $badgeClass ?>"><?=esc($paymentrow->transaction_status ?? '')?></span>
									</td>
								</tr>
							<?php endif;?>
							</tbody>
						</table>
			<?php endif?>
            </div>
            <div class="modal-footer no-print">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              <button id="printModal<?=$row->id?>" class="btn btn-sm btn-outline-secondary col-2" onclick="printModalContent('staticBackdrop<?=$row->id?>')">
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
                console.error('Modal not found');
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

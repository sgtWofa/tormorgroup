<!-- Wallets Tab -->
  <h4>Wallets</h4>
      <p>
    		<i>All wallets assigned to <?=$row->first_name . ' ' . $row->last_name ?? ''?></i>
    </p>
	<div class="table-responsive">
	<table class="table table-striped table-hover">
		<tr>
			<th>#</th>
			<th>Transaction type</th>
			<th>Status</th>
			<th>Amount</th>
			<th>Charge</th>
			<th>Date created</th>
		</tr>
  		<?php if(!empty($wallets)): $id = 0;?>
	        	<?php foreach($wallets as $trans): $id ++;?>
						<tr>
							<td><span class="badge bg-warning text-black rounded-circle pill"><?=$id?></span><?=$trans->id ?></td>
							<td>
								<a style="text-decoration: none;" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$trans->id ?>">
									<?=esc($trans->wallet_type  . ' (' . $trans->network  . ')')?>
								</a>
							</td>
							<td>
								<?php
										// Determine badge class based on transaction status
										$badgeClass = '';
										switch (strtolower($trans->transaction_status  ?? 'approved')) {
											case 'pending':
												$badgeClass = 'badge bg-warning text-dark';
												break;
											case 'approved':
												$badgeClass = 'badge bg-success';
												break;
											case 'rejected':
												$badgeClass = 'badge bg-danger';
												break;
											default:
												$badgeClass = 'badge bg-secondary';
												break;
										}
									?>
								<span class="<?= $badgeClass ?>"><?=esc($trans->transaction_status )?></span>
							</td>
							<td><?=number_format($trans->amount  ?? 0, 2)?></td>
							<td><?=number_format($trans->charge  ?? 0, 2)?></td>
							<td><?=get_date_time($trans->date_created )?></td>
						</tr>
				<?php endforeach?>
			<?php else:?>
				<tr><td colspan="6" class="text-center">No records to show!</td></tr>
      <?php endif;?>
		</table>
</div>
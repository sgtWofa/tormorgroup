<style type="text/css">
	tr{
		white-space: nowrap;
	}
</style>

<!-- Transactions Tab -->
<h4>Sims</h4>
    <p>Here, sims assigned to <?=$row->first_name . ' ' . $row->last_name ?? ''?> are displayed.</p>         
  	<div class="table-responsive">
		<div class="table-responsive">
			<table class="table table-striped table-sm table-hover">
				<thead>
					<tr>
						<th class="bg-secondary text-warning">#</th>
						<th class="bg-secondary text-warning">Sim</th>
						<th class="bg-secondary text-warning">Status</th>
						<th class="bg-secondary text-warning">PUK</th>
						<th class="bg-secondary text-warning">Serial #</th>
						<th class="bg-secondary text-warning">Cost</th>
						<th class="bg-secondary text-warning">Wholesale</th>
						<th class="bg-secondary text-warning">Retail</th>
						<th class="bg-secondary text-warning">Date created</th>
						<th class="bg-secondary text-warning"></th>
					</tr>
				</thead>
				<?php if(!empty($sims)): $id = 0;?>
					<?php
						//session data
						$session = get_session();
						//dd($session);
						if(!empty($session->id)){
							$username = $session->first_name . ' '. $session->last_name;
							$user_id = $session->id;
						}
					?>

					<tbody>
					<?php foreach($sims as $row): $id ++;?>
						<?php if(user_can('approve_transactions') || $session->id == $row->assigned ?? ''):?>
							<tr>
								<td><span class="badge bg-warning text-black rounded-circle pill"><?=$id?></span><?=$row->id?></td>
								<td>
									<a style="text-decoration: none;" href="<?=ROOT?>/<?=$admin_route?>/sims/view/<?=$row->id?>">
										<?=esc($row->network ?? '')?>
										<?php if(($row->transaction_status === 'Assigned')): ?>
											(<?=$row->number_assigned ?? 'unassigned'?>)
										<?php else: ?>
											(<?=$row->buyer_phone ?? 'unassigned'?>)
										<?php endif;?>
									</a>
								</td>
								<td>
									<?php
											// Determine badge class based on transaction status
											$badgeClass = '';
											switch (strtolower($row->transaction_status ?? 'unassigned')) {
												case 'expired':
													$badgeClass = 'badge bg-danger';
													break;
												case 'sold - retail':
												case 'sold - wholesale':
													$badgeClass = 'badge bg-success';
													break;
												case 'unassigned':
													$badgeClass = 'badge bg-warning text-dark';
													break;
												case 'assigned':
													$badgeClass = 'badge bg-primary';
													break;
												default:
													$badgeClass = 'badge bg-secondary';
													break;
											}
										?>
									<span class="<?= $badgeClass ?>"><?=esc($row->transaction_status)?></span>
								</td>
								<td><?=esc($row->puk ?? '')?></td>
								<td><?=esc($row->serial_number ?? '')?></td>
								<td><?=number_format($row->cost, 2 ?? '')?></td>
								<td><?=number_format($row->wholesale_price, 2 ?? '')?></td>
								<td><?=number_format($row->retail_price, 2 ?? '')?></td>
								<td><?=get_date_time($row->date_created ?? '')?></td>
								<td>
									<?php if(user_can('view_category_details')):?>
									<a href="<?=ROOT?>/<?=$admin_route?>/sims/view/<?=$row->id?>">
										<button class="btn btn-primary btn-sm" title="View">
											<i class="fa-solid fa-eye"></i> 
										</button>
									</a>
									<?php endif?>

									<?php if(user_can('edit_category') AND ($row->transaction_status === 'Unassigned' || $row->transaction_status === 'Assigned' )):?>
									<a href="<?=ROOT?>/<?=$admin_route?>/sims/edit/<?=$row->id?>">
										<button class="btn btn-warning btn-sm" title="Edit">
											<i class="fa-solid fa-pen-to-square"></i>
										</button>
									</a>
									<?php endif?>

									<!-- <?php if(user_can('delete_category') AND ($row->transaction_status !== 'Sold' || $row->transaction_status !== 'Draft')):?>
									<a href="<?=ROOT?>/<?=$admin_route?>/sims/delete/<?=$row->id?>">
										<button class="btn btn-danger btn-sm" title="Delete">
											<i class="fa-solid fa-trash"></i>
										</button>
									</a>
									<?php endif?> -->
								</td>
							</tr>
						<?php endif;?>
						<?php endforeach?>
						<?php else:?>
							<tr>
								<td colspan="10" class="text-center">No records to show!</td>
							</tr>
					</tbody>				
					<?php endif?>
					<tfoot>
						<tr>
							<th colspan="7" class="bg-secondary text-warning"></th>
							<th class="bg-secondary text-warning">Summary</th>
							<th class="bg-secondary text-warning">Amount</th>
							<th class="bg-secondary text-warning">Commission</th>
						</tr>
						<tr>
							<th colspan="7" style="border:none;"></th>
							<th>Unassigned: </th>
							<td><?=esc($totalUnassigned_amount ?? 0, 2)?> </td>
							<td><?=esc($totalUnassigned_commission ?? 0, 2)?></td>
						</tr>
						<tr>
							<th colspan="7" style="border:none;"></th>
							<th>Assigned: </th>
							<td><?=number_format($totalAssigned_amount ?? 0, 2)?></td>
							<td><?=number_format($totalAssigned_commission ?? 0, 2)?></td>
						</tr>
						<tr>
							<th colspan="7" style="border:none;"></th>
							<th>Retails: </th>
							<td><?=number_format($totalSoldRetail_amount ?? 0, 2)?></td>
							<td><?=number_format($totalSoldRetail_commission ?? 0, 2)?></td>
						</tr>
						<tr>
							<th colspan="7" style="border:none;"></th>
							<th>Wholesales: </th>
							<td><?=number_format($totalSoldWholesale_amount ?? 0, 2)?></td>
							<td><?=number_format($totalSoldWholesale_commission ?? 0, 2)?></td>
						</tr>
						<tr>
							<th colspan="7" style="border:none;"></th>
							<th>Expired: </th>
							<td><?=number_format($totalExpired_amount ?? 0, 2)?></td>
							<td><?=number_format($totalExpired_commission ?? 0, 2)?></td>
						</tr>
						<tr>
							<th colspan="7" style="border:none;"></th>
							<th class="bg-secondary text-warning">Sims: </th>
							<th class="bg-secondary text-warning"><?=number_format($total_amount ?? 0, 2)?></th>
							<th class="bg-secondary text-warning"><?=number_format($total_commission ?? 0, 2)?></th>
						</tr>
					</tfoot>
				</table>

			<?=$pager->display()?>
		</div>
	</div>
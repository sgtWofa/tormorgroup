<?php if(user_can('view_agencybanking')):?>
<style type="text/css">
		tbody tr{
			white-space: nowrap;
		}
	</style>

<div class="row my-3 mx-auto" >
	<div class="input-group">	
	<!-- dropdown -->
	<div class="dropdown">
	    <button class="btn btn-sm btn-warning dropdown-toggle" type="button" id="dropdownMenuButton<?=$id?>" data-bs-toggle="dropdown" aria-expanded="false" style="border: none;">
	        Transactions
	    </button>
	    <?php
	        $session = get_session();
	        $userId = $session->id;
	    ?>
	    <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?=$id?>">
	        <?php if(user_can("view_momoremit")):?>
	            <li>
	                <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
	                    <i class="fa fa-list text-muted"></i> All transactions
	                </a>
	            </li>
	            <li>
	                <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/withdrawals">
	                    <i class="fa fa-arrow-up text-muted"></i> Withdrawals
	                </a>
	            </li>
	            <li>
	                <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/transfers">
	                    <i class="fa fa-exchange-alt text-muted"></i> Transfers
	                </a>
	            </li>
	        <?php endif?>
	    </ul>
	</div>    
    <span class="mx-2">
		<div class="dropdown">
		    <button class="btn btn-sm btn-warning dropdown-toggle" type="button" id="dropdownMenuButton<?=$id?>" data-bs-toggle="dropdown" aria-expanded="false" style="border: none;">
		        <i class="fa-solid fa-plus"></i> New
		    </button>
		    <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?=$id?>">
		        <?php if(user_can("view_momoremit")):?>
		            <li>
				            <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/withdrawal">
				                <i class="fa-solid fa-money-bill-wave text-warning"></i> Withdrawal
				            </a>
				        </li>
				        <li>
				            <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/transfer">
				                <i class="fa-solid fa-exchange-alt text-warning"></i> Transfer
				            </a>
				        </li>
		        <?php endif?>
		    </ul>
		</div>
	</span>
	<center class="mx-auto text-center"><h2>EZWICH WITHDRAWALS</h2></center>
	</div>
</div>
<div class="table-responsive">
	<table class="table table-striped table-hover">
		<thead>
			<tr>
				<th class="bg-secondary text-warning">#</th>
				<th class="bg-secondary text-warning">Transaction type</th>
				<th class="bg-secondary text-warning">Status</th>
				<th class="bg-secondary text-warning">Amount</th>
				<th class="bg-secondary text-warning">Charge</th>
				<th class="bg-secondary text-warning">Date created</th>
				<th class="bg-secondary text-warning">Created by</th>
				<th class="bg-secondary text-warning"></th>
			</tr>
		</thead>
		<?php if(!empty($rows)): $id = 0;?>
			<?php foreach($rows as $row): $id ++;?>
				<tbody>
					<tr>
						<td><span class="badge bg-warning text-black rounded-circle pill"><?=$id?></span><?=$row->id?></td>
						<td>
							<a style="text-decoration: none;" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>">
								<?=esc($row->transaction_type . ' (' . $row->network . ')')?>
							</a>
						</td>
						<td>
							<?php
									// Determine badge class based on transaction status
									$badgeClass = '';
									switch (strtolower($row->transaction_status ?? 'approved')) {
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
							<span class="<?= $badgeClass ?>"><?=esc($row->transaction_status)?></span>
						</td>
						<td><?=number_format($row->amount ?? 0, 2)?></td>
						<td><?=number_format($row->charge ?? 0, 2)?></td>
						<td><?=get_date_time($row->date_created)?></td>
						<td>
							<span><img src="<?=get_image($row->user_image)?>" style="width: 30px; height: 30px; object-fit: cover; border-radius: 50%;"></span>
							 <?=$row->user_first_name . ' ' . $row->user_last_name ?>
						</td>

						<td>
							<?php if(user_can('view_category_details')):?>
							<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>">
								<button class="btn btn-primary btn-sm" title="View">
									<i class="fa-solid fa-eye"></i> 
								</button>
							</a>
							<?php endif?>

							<?php if(user_can('edit_category') AND ($row->transaction_status === 'Pending' || $row->transaction_status === 'Draft')):?>
							<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$row->id?>">
								<button class="btn btn-warning btn-sm" title="Edit">
									<i class="fa-solid fa-pen-to-square"></i>
								</button>
							</a>
							<?php endif?>

							<?php if(user_can('delete_category') AND ($row->transaction_status === 'Pending' || $row->transaction_status === 'Draft')):?>
							<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/delete/<?=$row->id?>">
								<button class="btn btn-danger btn-sm" title="Delete">
									<i class="fa-solid fa-trash"></i>
								</button>
							</a>
							<?php endif?>
						</td>
					</tr>
				</tbody>
			<?php endforeach?>
		<?php endif?>
		<tfoot>
			<tr>
				<th colspan="5" class="bg-secondary text-warning"></th>
				<th class="bg-secondary text-warning">Summary <span class="badge bg-warning text-black rounded-circle pill float-end">Qty</span></th>
				<th class="bg-secondary text-warning">Amount</th>
				<th class="bg-secondary text-warning">Charges</th>
			</tr>
			<tr>
				<th colspan="5" style="border:none;"></th>
				<th>Total Approved: <span class="badge bg-success rounded-circle pill float-end"><?=$totalApproved_qty ?? 0?></span></th>
				<td><?=number_format($totalApproved_amount ?? 0, 2)?> </td>
				<td><?=number_format($totalApproved_charge ?? 0, 2)?></td>
			</tr>
			<tr>
				<th colspan="5" style="border:none;"></th>
				<th>Total Draft: <span class="badge bg-secondary rounded-circle pill float-end"><?=$totalDraft_qty ?? 0?></span></th>
				<td><?=number_format($totalDraft_amount ?? 0, 2)?></td>
				<td><?=number_format($totalDraft_charge ?? 0, 2)?></td>
			</tr>
			<tr>
				<th colspan="5" style="border:none;"></th>
				<th>Total Pending: <span class="badge bg-warning text-black rounded-circle pill float-end"><?=$totalPending_qty ?? 0?></span></th>
				<td><?=number_format($totalPending_amount ?? 0, 2)?></td>
				<td><?=number_format($totalPending_charge ?? 0, 2)?></td>
			</tr>
			<tr>
				<th colspan="5" style="border:none;"></th>
				<th>Total Rejected: <span class="badge bg-danger rounded-circle pill float-end"><?=$totalRejected_qty ?? 0?></span></th>
				<td><?=number_format($totalRejected_amount ?? 0, 2)?></td>
				<td><?=number_format($totalRejected_charge ?? 0, 2)?></td>
			</tr>
			<tr>
				<th colspan="5" style="border:none;"></th>
				<th class="bg-secondary text-warning">Total Transactions: <span class="badge bg-warning text-black rounded-circle pill float-end"><?=$total_qty ?? 0?></span></th>
				<th class="bg-secondary text-warning"><?=number_format($total_amount ?? 0, 2)?></th>
				<th class="bg-secondary text-warning"><?=number_format($total_charge ?? 0, 2)?></th>
			</tr>
		</tfoot>
	</table>

	<?=$pager->display()?>
</div>
<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>

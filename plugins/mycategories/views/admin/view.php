<?php if(user_can('view_category_details')):?>
<style type="text/css">
	tbody tr{
		white-space: nowrap;
	}
	thead tr{
		white-space: nowrap;
	}
	tfoot tr{
		white-space: nowrap;
	}
</style>
	<?php if(!empty($row)):?>
		<div class="table-responsive">
			<table class="table table-striped table-hover">
				<thead>
					<tr>
						<th class="bg-secondary text-warning">#</th>
						<th class="bg-secondary text-warning">Name (Currency)</th>
						<th class="bg-secondary text-warning">Category</th>
						<th class="bg-secondary text-warning">Status</th>
						<th class="bg-secondary text-warning">Balance</th>
						<th class="bg-secondary text-warning">Date created</th>
						<th class="bg-secondary text-warning">Created by</th>
						<th class="bg-secondary text-warning">Action</th>
					</tr>
				</thead>
				<tbody>
				<?php if(!empty($rows)): $id = 0; ?>
					<?php foreach($rows as $row): $id ++;?>
						<tr>
							<td><span class="badge bg-warning text-black rounded-circle pill"><?=$id?></span><?=$row->id?></td>
							<td>
								<a style="text-decoration: none;" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>">
									<?=esc($row->name . ' (' . $row->network . ')')?>
								</a>
							</td>
							<td><?=esc($row->category_name ?? '')?></td>
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
												$badgeClass = 'badge bg-danger';
												break;
											default:
												$badgeClass = 'badge bg-secondary';
												break;
										}
									?>
								<span class="<?= $badgeClass ?>"><?=esc($row->transaction_status)?></span>
							</td>
							<td><?=number_format($row->balance ?? 0, 2)?></td>
							<td><?=get_date_time($row->date_created)?></td>
							<td>
								<span><img src="<?=get_image($row->image)?>" style="width: 30px; height: 30px; object-fit: cover; border-radius: 50%;"></span>
								 <?=$row->first_name . ' ' . $row->last_name ?>
							</td>

							<td>
								<div class="dropdown">
		                            <button class="btn btn-sm btn-outline-warning dropdown-toggle" type="button" id="dropdownMenuButton<?=$id?>" data-bs-toggle="dropdown" aria-expanded="false" style="border: none;">
		                            	<i class="fa-solid fa-ellipsis-vertical"></i>
		                            </button>
		                            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?=$id?>">
		                                <?php if(user_can("transfer_funds") || $user_id ?? '' === $row->assigned):?>
		                                    <li>
		                                        <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/transfer/<?=$row->id?>">
		                                            <i class="fas fa-right-left text-warning"></i> Transfer funds
		                                        </a>
		                                    </li>
		                                <?php endif;?>
		                                <?php if(user_can("edit_wallet") || $user_id ?? '' === $row->assigned):?>
		                                    <li>
		                                        <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$row->id?>">
		                                            <i class="fa fa-pen text-warning"></i> Edit Wallet
		                                        </a>
		                                    </li>
		                                <?php endif;?>
		                                <?php if(user_can('delete_user') || $user_id ?? '' === $row->id):?>
		                                    <li>
						                        <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>">
						                           <i class="fa-solid fa-file-invoice text-warning"></i> A/c Statement
						                        </a>
		                                    </li>
						                <?php endif?>
		                                <?php if(user_can("delete_wallet") || $user_id ?? '' === $row->id):?>
		                                    <li>
		                                        <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/resetpassword/<?=$row->id?>">
		                                            <i class="fa fa-trash text-warning"></i> Delete
		                                        </a>
		                                    </li>
		                                <?php endif;?>
		                            </ul>
		                        </div>
							</td>
						</tr>
					<?php endforeach?>
				<?php else:?>
					<tr><td colspan="7" class="text-center justify-content-center">No records to show!</td></tr>
				<?php endif?>
				</tbody>
				<tfoot>			
					<tr>
						<th class="bg-secondary text-warning"></th>
						<th colspan="2" class="bg-secondary text-warning">TOTAL BALANCE</th>
						<th class="bg-secondary text-warning"></th>
						<th class="bg-secondary text-warning"><?=number_format($total_balance ?? 0, 2)?></th>
						<th class="bg-secondary text-warning"></th>
						<th class="bg-secondary text-warning"></th>
						<th class="bg-secondary text-warning"></th>
					</tr>
				</tfoot>
			</table>

			<?=$pager->display()?>
		</div>

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
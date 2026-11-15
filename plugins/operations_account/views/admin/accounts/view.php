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
	<?php if(!empty($row)): ?>
		<div class="table-responsive">
			<table class="table table-striped table-hover">
				<thead>
					<tr>
		            	<th colspan="5" class="text-primary"><?=strtoupper($row->category ?? '')?> ACCOUNTS</th>
		            	<th class="text-primary">
							<!-- dropdown aligned to the extreme right -->
				            <div class="d-flex justify-content-end">
				                <div class="dropdown">
				                    <button class="btn btn-sm btn-warning dropdown-toggle" type="button" id="dropdownMenuButton<?=$id?>" data-bs-toggle="dropdown" aria-expanded="false" style="border: none;">
				                        Sellect Account
				                    </button>
				                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton<?=$id?>">
				                        <?php if(user_can("view_wallet")):?>
				                        <?php if(!empty($mycategories)):?>
				                        	<?php foreach($mycategories as $account):?>
					                            <li>
					                                <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_account/<?=$account->id?>">
					                                    <i class="fa fa-university text-muted"></i> <?=$account->category ?? ''?> (<?=number_format($account->balance, 2 ?? '')?>)
					                                </a>
					                            </li>
				                        	<?php endforeach?>
				                        <?php endif?>
				                        <?php endif?>
				                    </ul>
				                </div>
				            </div>
		            	</th>
		            </tr>
					<tr>
						<th class="">#</th>
						<th class="">Name (Currency)</th>
						<th class="">Balance</th>
						<th class="">Date created</th>
						<th class="">Created by</th>
						<th class=""></th>
					</tr>
				</thead>
				<tbody>
				<?php if(!empty($rows)): $id = 0; ?>
					<?php foreach($rows as $row): $id ++;?>
						<tr>
							<td><span class="badge bg-warning text-black rounded-circle pill"><?=$id?></span><?=$row->id?></td>
							<td>
								<a style="text-decoration: none;" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_wallet/<?=$row->id?>">
									<?=esc($row->name . ' (' . $row->network . ')')?>
								</a>
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
		                                        <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit_wallet/<?=$row->id?>">
		                                            <i class="fa fa-pen text-warning"></i> Edit Wallet
		                                        </a>
		                                    </li>
		                                <?php endif;?>
		                                <?php if(user_can('delete_user') || $user_id ?? '' === $row->id):?>
		                                    <li>
						                        <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_wallet/<?=$row->id?>">
						                           <i class="fa-solid fa-file-invoice text-warning"></i> A/c Statement
						                        </a>
		                                    </li>
						                <?php endif?>
		                                <?php if(user_can("delete_wallet") || $user_id ?? '' === $row->id):?>
		                                    <li>
		                                        <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/delete_wallet/<?=$row->id?>">
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
					<tr><td colspan="8" class="text-center justify-content-center">No records to show!</td></tr>
				<?php endif?>
				</tbody>
				<tfoot>			
					<tr>
						<th colspan="2" class="">TOTAL BALANCE</th>
						<th class=""><?=number_format($total_balance ?? 0, 2)?></th>
						<th class=""></th>
						<th class=""></th>
						<th class=""></th>
					</tr>
				</tfoot>
			</table>

			<?=$pager->display()?>
			<div class="d-flex justify-content-between">
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
					<button class="btn btn-md btn-outline-primary" style="border:none;">
						<i class="fa-solid fa-chevron-left"></i> Back
					</button>
				</a>
			</div>
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
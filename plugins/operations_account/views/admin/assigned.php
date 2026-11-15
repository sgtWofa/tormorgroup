<?php if(user_can('view_wallet')):?>
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

	<label><i>Page: <?=$pager->page_number?></i></label>

<div class="row my-3 mx-auto" >
	<form class="input-group col-10 d-flex mb-3" >
	  <input placeholder="Search by Name" type="text" class="form-control" value="<?=old_value('find','','get')?>" name="find" autofocus="true">
	  <button class="input-group-text bg-warning text-white" id="basic-addon1">
	  	Search
	  </button>
	</form>
</div>
<div class="table-responsive">
	<table class="table table-striped table-hover">
		<thead>
			<tr>
				<th class="bg-secondary text-warning">#</th>
				<th class="bg-secondary text-warning">Name (Number)</th>
				<th class="bg-secondary text-warning">Network</th>
				<th class="bg-secondary text-warning">Balance</th>
				<th class="bg-secondary text-warning">In Charge</th>
				<th class="bg-secondary text-warning">Created by</th>
				<th class="bg-secondary text-warning">Date created</th>
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
							<?=esc($row->name . ' (' . $row->wallet_number . ')')?>
						</a>
					</td>
					<td><?=$row->network . ' - '. $row->wallet_type ?? ''?></td>
					<td><?=number_format($row->balance ?? 0, 2)?></td>
					<td>
						<span><img src="<?=get_image($row->assigned_image)?>" style="width: 30px; height: 30px; object-fit: cover; border-radius: 50%;"></span>
						 <?=$row->assigned_first_name ?>
					</td>
					<td>
						<span><img src="<?=get_image($row->user_image)?>" style="width: 30px; height: 30px; object-fit: cover; border-radius: 50%;"></span>
						 <?=$row->user_first_name  ?>
					</td>
					<td><?=get_date($row->date_created)?></td>
					<td>
						<div class="dropdown">
                            <button class="btn btn-sm btn-outline-warning dropdown-toggle" type="button" id="dropdownMenuButton<?=$id?>" data-bs-toggle="dropdown" aria-expanded="false" style="border: none;">
                            	<i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?=$id?>">
                                <?php if(user_can("transfer_funds") || $user_id === $row->assigned):?>
                                    <li>
                                        <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/transfer/<?=$row->id?>">
                                            <i class="fas fa-right-left text-warning"></i> Transfer funds
                                        </a>
                                    </li>
                                <?php endif;?>
                                <?php if(user_can("edit_wallet") || $user_id === $row->assigned):?>
                                    <li>
                                        <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$row->id?>">
                                            <i class="fa fa-pen text-warning"></i> Edit Wallet
                                        </a>
                                    </li>
                                <?php endif;?>
                                <?php if(user_can('delete_user') || $user_id === $row->id):?>
                                    <li>
				                        <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>">
				                           <i class="fa-solid fa-file-invoice text-warning"></i> A/c Statement
				                        </a>
                                    </li>
				                <?php endif?>
                                <?php if(user_can("delete_wallet") || $user_id === $row->id):?>
                                    <li>
                                        <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/delete/<?=$row->id?>">
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
				<th class="bg-secondary text-warning"></th>
				<th colspan="2" class="bg-secondary text-warning">TOTAL BALANCE</th>
				<th class="bg-secondary text-warning"><?=number_format($total_balance, 2)?></th>
				<th class="bg-secondary text-warning"></th>
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
		Access denied. You dont have permission for this action
	</div>
<?php endif?>
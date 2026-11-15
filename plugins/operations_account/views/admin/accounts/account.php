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

<div class="row my-3 mx-auto" >
	<div class="input-group">	
	<span class="mx-2">
			<?php if(user_can('add_wallet')):?>
			<a style="text-decoration: none;" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/add_account">
				<button class="btn btn-warning btn-sm">
					<i class="fa-solid fa-plus"></i> New Account
				</button>
			</a>
			<?php endif?>
		</span>
	</div>
</div>
<div class="table-responsive">
	<table class="table table-striped table-hover">
		<thead>
			<?php if (!empty($assignedFunds) && $assignedFunds > 0): ?>
			    <tr class="card-header text-muted fst-italic">
			        <th></th>
			        <th>Total Assigned Funds</th>
			        <th></th>
			        <th></th>
			        <th><?= number_format($assignedFunds, 2) ?></th>
			    </tr>
			<?php endif; ?>
			<tr>
				<th>#</th>
				<th>Category Name</th>
				<th>Active</th>
				<th>Balance</th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			
			<?php if(!empty($accountRows)): $id = 0;?>
				<?php foreach($accountRows as $row): $id++;?>
					<tr>
						<td><?=$id?></td>
						<td>
							<a style="text-decoration: none;" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_account/<?=$row->id?>">
								<?=esc($row->category)?>
							</a>
						</td>
						<td><?=($row->disabled) ? 'No':'Yes'?></td>
						<td><?=number_format($row->balance, 2)?></td>
						<td>
							<?php if(user_can('view_category_details')):?>
							<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_account/<?=$row->id?>">
								<button class="btn btn-primary btn-sm" title="View">
									<i class="fa-solid fa-eye"></i> 
								</button>
							</a>
							<?php endif?>

							<?php if(user_can('edit_category')):?>
							<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit_account/<?=$row->id?>">
								<button class="btn btn-warning btn-sm" title="Edit">
									<i class="fa-solid fa-pen-to-square"></i>
								</button>
							</a>
							<?php endif?>

							<!-- <?php if(user_can('delete_category')):?>
							<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/delete_account/<?=$row->id?>">
								<button class="btn btn-danger btn-sm" title="Delete">
									<i class="fa-solid fa-trash"></i>
								</button>
							</a>
							<?php endif?> -->
						</td>
					</tr>
				<?php endforeach?>
			<?php else:?>
				<tr><td colspan="5" class="text-center">No records to show!</td></tr>
			<?php endif?>
		</tbody>
		<tfoot>
		    <tr>
		        <th></th>
		        <th>Total Accounts Balance : </th>
		        <th></th>
		        <th><?= number_format($total_balance ?? 0, 2) ?></th>
		        <th></th>
		    </tr>
		    <?php if (!empty($assignedFunds) && $assignedFunds > 0): ?>
		        <tr class="card-footer text-muted fst-italic">
		            <th></th>
		            <th>Remaining Assigned Funds : </th>
		            <th></th>
		            <th></th>
		            <th><?= number_format($assignedRemaining ?? 0, 2) ?></th>
		        </tr>
		    <?php endif; ?>
		</tfoot>
	</table>

	<?=$pager->display()?>
</div>
<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>
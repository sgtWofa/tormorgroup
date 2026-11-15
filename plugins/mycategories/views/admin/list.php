<?php if(user_can('view_categories')):?>

<div class="table-responsive">

	<form class="input-group my-3 mx-auto" >
	  <input placeholder="Search by Name" type="text" class="form-control" value="<?=old_value('find','','get')?>" name="find" autofocus="true">
	  <button class="input-group-text bg-primary text-white" id="basic-addon1">
	  	Search
	  </button>
	</form>

	<table class="table table-striped table-hover">
		<thead>
			<tr>
				<th class="bg-secondary text-warning">#</th>
				<th class="bg-secondary text-warning">Category Name</th>
				<th class="bg-secondary text-warning">Active</th>
				<th class="bg-secondary text-warning">Balance</th>

				<th class="bg-secondary text-warning">
					<?php if(user_can('add_category')):?>
					<a style="text-decoration: none;" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/add">
						<button class="btn btn-bd-primary btn-sm">
							<i class="fa-solid fa-plus"></i> Add New
						</button>
					</a>
					<?php endif?>
				</th>
			</tr>
		</thead>
		<tbody>
			<?php if(!empty($rows)): $id = 0;?>
				<?php foreach($rows as $row): $id++;?>
					<tr>
						<td><?=$id?></td>
						<td>
							<a style="text-decoration: none;" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>">
								<?=esc($row->category)?>
							</a>
						</td>
						<td><?=($row->disabled) ? 'No':'Yes'?></td>
						<td><?=number_format($row->balance, 2)?></td>
						<td>
							<?php if(user_can('view_category_details')):?>
							<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>">
								<button class="btn btn-primary btn-sm" title="View">
									<i class="fa-solid fa-eye"></i> 
								</button>
							</a>
							<?php endif?>

							<?php if(user_can('edit_category')):?>
							<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$row->id?>">
								<button class="btn btn-warning btn-sm" title="Edit">
									<i class="fa-solid fa-pen-to-square"></i>
								</button>
							</a>
							<?php endif?>

							<?php if(user_can('delete_category')):?>
							<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/delete/<?=$row->id?>">
								<button class="btn btn-danger btn-sm" title="Delete">
									<i class="fa-solid fa-trash"></i>
								</button>
							</a>
							<?php endif?>
						</td>
					</tr>
				<?php endforeach?>
			<?php else:?>
				<tr><td colspan="5" class="text-center">No records to show!</td></tr>
			<?php endif?>
		</tbody>
		<tfoot>
			<tr>
				<th class="bg-secondary text-warning"></th>
				<th class="bg-secondary text-warning">Total Balance : </th>
				<th class="bg-secondary text-warning"></th>
				<th class="bg-secondary text-warning"><?=number_format($total_balance ?? 0,2)?></th>
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
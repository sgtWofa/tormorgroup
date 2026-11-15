<style>
	a{
		text-decoration: none;
	}
</style>
<?php if(user_can('view_pages')):?>
	
<div class="table-responsive">

	<form class="input-group my-3 mx-auto" >
	  <input placeholder="Search by Title" type="text" class="form-control" value="<?=old_value('find','','get')?>" name="find" autofocus="true">
	  <button class="input-group-text bg-primary text-white" id="basic-addon1">
	  	Search
	  </button>
	</form>

	<table class="table table-striped table-hover">
		<tr>
			<th>Company's Name</th>
			<th>Name of CEO</th>
			<th>Title</th>
			<th>Signature</th>
			<th>Date Started</th>
			<th>Transaction fee</th>
			<th>Date Created</th>
			<th>
				<?php if(user_can('add_page')):?>
					<?php if(empty($rows)):?>
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/add">
						<button class="btn btn-bd-primary btn-sm">
							<i class="fa-solid fa-plus"></i> Add New
						</button>
					</a>
					<?php endif?>
				<?php endif?>
			</th>
		</tr>

		<?php if(!empty($rows)):?>
			<?php foreach($rows as $row):;?>
				<tr>
					<td>
						<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>">
							<?=esc($row->company_name ?? '')?>
						</a>
					</td>
					<td><?=$row->ceo_name ?? ''?></td>
					<td><?=$row->title ?? ''?></td>
					<td>
						<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>">
							<img src="<?=get_image($row->image)?>" class="img-thumbnail" style="width:80px;height:80px;object-fit:contain;"/>
						</a>
					</td>
					<td><?=get_date($row->date_started)?></td>
					<td><?=number_format($row->transaction_fee ?? 0, 2)?></td>
					<td><?=get_date($row->date_created)?></td>
					<td>
						<?php if(user_can('view_pages')):?>
						<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>">
							<button class="btn btn-primary btn-sm" title="View">
								<i class="fa-solid fa-eye"></i> View
							</button>
						</a>
						<?php endif?>

						<?php if(user_can('edit_page')):?>
						<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$row->id?>">
							<button class="btn btn-warning btn-sm" title="Edit">
								<i class="fa-solid fa-pen-to-square"></i> Edit
							</button>
						</a>
						<?php endif?>
					</td>
				</tr>
			<?php endforeach?>
		<?php endif?>
	</table>

	<?=$pager->display()?>

</div>
<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>
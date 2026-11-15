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
	<div class="input-group">
	
	<span class="mx-2">
			<?php if(user_can('add_request')):?>
			<a style="text-decoration: none;" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/add">
				<button class="btn btn-warning btn-sm">
					<i class="fa-solid fa-plus"></i> New Request
				</button>
			</a>
			<?php endif?>
		</span>
	</div>
</div>
<div class="table-responsive">
	<table class="table table-striped table-hover">
		<thead>
			<tr>
				<th class="bg-secondary text-warning">#</th>
				<th class="bg-secondary text-warning">Date created</th>
				<th class="bg-secondary text-warning">Request type</th>
				<th class="bg-secondary text-warning">Status</th>
				<th class="bg-secondary text-warning">Wallet number</th>
				<th class="bg-secondary text-warning">Till/Code</th>
				<th class="bg-secondary text-warning">Amount</th>
				<th class="bg-secondary text-warning">From</th>
				<th class="bg-secondary text-warning">To</th>
				<th class="bg-secondary text-warning">Action</th>
			</tr>
		</thead>
		<tbody>
		<?php if(!empty($rows)): $id = 0; ?>
			<?php foreach($rows as $row): $id ++;?>
				<tr>
					<td><span class="badge bg-warning text-black rounded-circle pill"><?=$id?></span></td>
					<td><?=get_date_time($row->date_created)?></td>
					<td>
						<a style="text-decoration: none;" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>">
							<?=esc($row->network . ' - ' . $row->request_type ?? '')?>
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
										$badgeClass = 'badge bg-danger';
										break;
									default:
										$badgeClass = 'badge bg-secondary';
										break;
								}
							?>
						<span class="<?= $badgeClass ?>"><?=esc($row->transaction_status)?></span>
					</td>
					<td><?=esc($row->wallet_number ?? '')?></td>
					<td><?=esc($row->till_number ?? '')?></td>
					<td><?=number_format($row->amount ?? 0, 2)?></td>
					<td>
						<span><img src="<?=get_image($row->user_image)?>" style="width: 30px; height: 30px; object-fit: cover; border-radius: 50%;"></span>
						 <?=$row->user_first_name . ' ' . $row->user_last_name ?>
					</td>
					<td>
						<span><img src="<?=get_image($row->assigned_image)?>" style="width: 30px; height: 30px; object-fit: cover; border-radius: 50%;"></span>
						 <?=$row->assigned_first_name . ' ' . $row->assigned_last_name ?>
					</td>

					<td>
						<div class="dropdown">
                            <button class="btn btn-sm btn-outline-warning dropdown-toggle" type="button" id="dropdownMenuButton<?=$id?>" data-bs-toggle="dropdown" aria-expanded="false" style="border: none;">
                            	<i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?=$id?>">                            	
                                <li>
                                    <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>">
                                        <i class="fa fa-eye text-primary"></i> View request
                                    </a>
                                </li>
                                <?php if (in_array(strtolower($row->transaction_status), ['draft', 'pending'])):?>
                                <li>
                                    <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$row->id?>">
                                        <i class="fas fa-pen text-warning"></i> Update request
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
	</table>

	<?=$pager->display()?>
</div>
<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>
<?php if(user_can('view_agencybanking')):?>
<style type="text/css">
		tbody tr{
			white-space: nowrap;
		}
	</style>

	<label><i>Page: <?=$pager->page_number?></i></label>

<div class="row my-3 mx-auto" >
	<form class="input-group col-10 d-flex mb-3" >
	  <input placeholder="Search by Name" type="text" class="form-control" value="<?=old_value('find','','get')?>" name="find" autofocus="true">
	  <button class="input-group-text bg-primary text-white" id="basic-addon1">
	  	Search
	  </button>
	</form>
	<div class="input-group">
	
	<!-- dropdown -->
	<div class="dropdown">
	    <button class="btn btn-sm btn-primary dropdown-toggle" type="button" id="dropdownMenuButton<?=$id?>" data-bs-toggle="dropdown" aria-expanded="false" style="border: none;">
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
	                <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/deposits">
	                    <i class="fa fa-arrow-down text-muted"></i> Deposits
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
	            <li>
	                <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/interbanks">
	                    <i class="fa fa-university text-muted"></i> Interbank (gip)
	                </a>
	            </li>
	            <li>
	                <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/bankaccounts">
	                    <i class="fa fa-id-card text-muted"></i> Account related
	                </a>
	            </li>
	        <?php endif?>
	    </ul>
	</div>
    
    <span class="mx-2">
		<div class="dropdown">
		    <button class="btn btn-sm btn-warning dropdown-toggle" type="button" id="dropdownMenuButton<?=$id?>" data-bs-toggle="dropdown" aria-expanded="false" style="border: none;">
		        <i class="fa-solid fa-plus"></i> Add
		    </button>
		    <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?=$id?>">
		        <?php if(user_can("view_momoremit")):?>
		            <li>
		                <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/account_opening">
		                    <i class="fa fa-user-plus text-warning"></i> Account Opening
		                </a>
		            </li>
		            <li>
		                <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/balance_enquiry">
		                    <i class="fa fa-balance-scale text-warning"></i> Balance Enquiry
		                </a>
		            </li>
		            <li>
		                <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/deposit">
		                    <i class="fa fa-arrow-down text-warning"></i> Deposit
		                </a>
		            </li>
		            <li>
		                <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/direct_transfer">
		                    <i class="fa fa-exchange text-warning"></i>Direct Transfers
		                </a>
		            </li>
		            <li>
		                <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/funds_transfer">
		                    <i class="fa fa-exchange-alt text-warning"></i>Funds Transfers
		                </a>
		            </li>
		            <li>
		                <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/interbank">
		                    <i class="fa fa-university text-warning"></i> Interbank (gip)
		                </a>
		            </li>
		            <li>
		                <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/mini_statement">
		                    <i class="fas fa-receipt text-warning"></i> Mini Statement
		                </a>
		            </li>
		            <li>
		                <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/withdrawal">
		                    <i class="fa fa-arrow-up text-warning"></i> Withdrawal
		                </a>
		            </li>
		        <?php endif?>
		    </ul>
		</div>
	</span>
		<center class="mx-auto text-center"><h2>BANK WITHDRAWALS</h2></center>
	</div>
</div>
<div class="table-responsive">
	<table class="table table-striped table-hover">
		<thead>
			<tr>
				<th class="bg-secondary text-warning">#</th>
				<th class="bg-secondary text-warning">Transaction type</th>
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
		<?php else:?>
			<tr><td colspan="7" class="text-center"> No records to show!!</td></tr>
		<?php endif?>
		<tfoot>
			<tr>
				<th colspan="4" class="bg-secondary text-white"></th>
				<th class="bg-secondary text-white">Summary</th>
				<th class="bg-secondary text-white">Total Amount</th>
				<th class="bg-secondary text-white">Total Charges</th>
			</tr>
			<tr>
				<th colspan="4" style="border:none;"></th>
				<th class="text-muted">Total Transactions: </th>
				<th class="text-muted"><?=number_format($total_amount ?? 0, 2)?></th>
				<th class="text-muted"><?=number_format($total_charge ?? 0, 2)?></th>
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
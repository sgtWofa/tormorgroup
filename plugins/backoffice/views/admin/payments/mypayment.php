<?php if(user_can('view_payment')):?>
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

		td.wrap{
			white-space: normal;
		}

		a{
			text-decoration: none;
		}
	</style>

<div class="row my-3 mx-auto" >	
	<div class="input-group">	
		<span class="mx-2">
			<?php if(user_can('add_payment')):?>
			<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/make_payment">
				<button class="btn btn-warning btn-sm">
					<i class="fa-solid fa-plus"></i> Make Payment
				</button>
			</a>
			<?php endif?>
		</span>
	</div>
</div>
<div class="table-responsive">
	<table class="table table-striped table-hover">
		<thead>
			<tr><th colspan="8" class="text-center fs-2">MY PAYMENTS</th></tr>
			<tr>
				<th>#</th>
				<th>Date created</th>
				<th>Description</th>
				<th>Amount</th>
				<th>Status</th>
				<th>SOF</th>
				<th>Created by</th>
				<th>Action</th>
			</tr>
		</thead>
		<tbody>
		    <?php if (!empty($mypayments)): $id = 0; ?>
		        <?php foreach ($mypayments as $row): $id++; ?>
		            <tr>
		                <td><span class="badge bg-warning text-black rounded-circle pill"><?= $id ?></span></td>
		                <td>
		                    <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_payment/<?=$row->id?>">
		                        <?= get_date_time($row->date_created) ?>
		                    </a>
		                </td>
		                <td class="wrap"><?= esc($row->description ?? '') ?></td>
		                <td><?= number_format($row->amount ?? 0, 2) ?></td>
		                <td>
		                    <?php
		                        $badgeClass = match (strtolower($row->transaction_status ?? 'approved')) {
		                            'pending' => 'badge bg-warning text-dark',
		                            'approved' => 'badge bg-success',
		                            'rejected' => 'badge bg-danger',
		                            default => 'badge bg-secondary',
		                        };
		                    ?>
		                    <span class="<?= $badgeClass ?>"><?= esc($row->transaction_status) ?></span>
		                </td>
		                <td><?= esc($row->out_wallet_name . ' ' . $row->out_wallet_network ?? '') ?></td>
		                <td>
		                    <span>
		                        <img src="<?= get_image($row->user_image) ?>" 
		                             style="width: 30px; height: 30px; object-fit: cover; border-radius: 50%;">
		                    </span>
		                    <?= esc($row->user_first_name . ' ' . $row->user_last_name) ?>
		                </td>
		                <td>
		                    <!-- Include Modal for this Row -->
		                    <?php 
		                        // Pass the payment ID to the modal for fetching data
		                        $rowId = $row->id;
		                        require plugin_path('views/admin/payments/paymentview_modal.php'); 
		                    ?>

		                    <div class="dropdown">
		                        <button class="btn btn-sm btn-outline-warning dropdown-toggle" type="button" id="dropdownMenuButton<?= $rowId ?>" data-bs-toggle="dropdown" aria-expanded="false" style="border: none;">
		                            <i class="fa-solid fa-ellipsis-vertical"></i>
		                        </button>
		                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?= $rowId ?>">
		                            <li>
		                                <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_payment/<?=$row->id?>">
		                                    <i class="fas fa-eye text-warning"></i> Details
		                                </a>
		                            </li>
		                            <?php if (user_can("edit_payment") && (strtolower($row->transaction_status) === 'draft' || strtolower($row->transaction_status) === 'pending')): ?>
		                                <li>
		                                    <a class="dropdown-item" href="<?= ROOT ?>/<?= $admin_route ?>/<?= $plugin_route ?>/edit_payment/<?= $row->id ?>">
		                                        <i class="fa fa-pen text-warning"></i> Update
		                                    </a>
		                                </li>
		                            <?php endif; ?>
		                        </ul>
		                    </div>
		                </td>
		            </tr>
		        <?php endforeach; ?>
		    <?php else: ?>
		        <tr>
		            <td colspan="8" class="text-center">No records to show!</td>
		        </tr>
		    <?php endif; ?>
		</tbody>

		<tfoot>			
			<tr>
				<th colspan="2" class="text-center">TOTAL : </th>
				<th></th>
				<th><?=number_format($mytotal_payments ?? 0, 2)?></th>
				<th></th>
				<th></th>
				<th></th>
				<th></th>
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
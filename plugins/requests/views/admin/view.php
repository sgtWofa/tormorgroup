<?php if(user_can('view_reversal')):?>
	<style type="text/css">
		tbody tr{
			white-space: nowrap;
		}
		tbody tr.wrap{
			white-space: normal;
		}
	</style>
	<?php if(!empty($row)): ?>

		<div class="row g-3 col-sm-11 col-md-10 col-lg-6 mx-auto shadow p-4 rounded">
		
			<center>
				<div class="badge">
					<img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:40px;max-height: 40px;object-fit: cover;">
					<strong style="font-size:15px; color: gray;"> <?=APP_NAME?></strong>
				</div>
				<h4 class="text-center" style="font-weight: bolder;">
					<?=ucfirst($row->network)?> <?=ucfirst($row->request_type ?? '')?> Request
				</h4><br>
			</center>

			<div class="col col-12 d-flex table-responsive">
				<table class="table table-striped table-hover border-bottom col-lg-6 col-md-12 table-sm" style="height: fit-content !important;">
					<thead>
						<tr><th colspan="2" class="text-primary">REQUEST DETAILS</th></tr>
					</thead>
					<tbody>
						<tr>
							<th class="text-muted">Network: </th>
							<td><?=esc($row->network)?></td>
						</tr>
						<tr>
							<th class="text-muted">Request type: </th>
							<td><?=esc($row->request_type)?></td>
						</tr>
						<tr>
							<th class="text-muted">Wallet number: </th>
							<td><?=esc($row->wallet_number ?? '')?></td>
						</tr>
						<tr>
							<th class="text-muted">Till/code: </th>
							<td><?=esc($row->till_number ?? '')?></td>
						</tr>
						<tr>
							<th class="text-muted">Amount: </th>
							<td><?=number_format($row->amount ?? 0, 2)?></td>
						</tr>	
						<?php if(!empty($row->description)): ?>
							<tr>
								<th class="text-muted">Narration:</th>
								<td style="white-space:normal;"><?=esc($row->description, 2)?></td>
							</tr>
						<?php endif;?>		
						<?php if(!empty($row->assigned)): ?>
						<tr>
							<th class="text-muted">Assigned to:</th>
							<td>
								<span><img src="<?=get_image($row->assigned_image)?>" style="width: 20px; height: 20px; object-fit: cover; border-radius: 50%; margin: 0px;"></span> 
								<?=$row->assigned_first_name . ' ' . $row->assigned_last_name ?>
							</td>
						</tr>
						<?php endif;?>	
						<?php if(!empty($row->date_created)): ?>
							<tr>
								<th class="text-muted">Created by:</th>
								<td>
									<span><img src="<?=get_image($row->user_image)?>" style="width: 20px; height: 20px; object-fit: cover; border-radius: 50%; margin: 0px;"></span> 
									<?=$row->user_first_name . ' ' . $row->user_last_name ?>
								</td>
							</tr>
							<tr>
								<th class="text-muted">Date created</th>
								<td><?=get_date_time($row->date_created, 2)?></td>
							</tr>
						<?php endif;?>

							<?php if(!empty($row->date_updated)):?>
							<tr >
								<th class="text-muted">Last updated:</th>
								<td><?=get_date_time($row->date_updated)?></td>
							</tr>

							<tr >
								<th class="text-muted">Updated by:</th>
								<td>
									<span><img src="<?=get_image($row->updated_by_image)?>" style="width: 20px; height: 20px; object-fit: cover; border-radius: 50%; margin: 0px;"></span> 
									<?=$row->updated_by_first_name . ' ' . $row->updated_by_last_name ?>
								</td>
							</tr>
						<?php endif;?>

						<?php if(!empty($row->transaction_status)): ?>
						<tr>
							<?php
								// Determine badge class based on transaction status
								$badgeClass = '';
								switch (strtolower($row->transaction_status ?? '')) {
									
									case 'approved':
										$badgeClass = 'badge bg-success';
										break;
									case 'rejected':
										$badgeClass = 'badge bg-danger';
										break;
									case 'pending':
										$badgeClass = 'badge bg-warning text-dark';
										break;
									default:
										$badgeClass = 'badge bg-secondary';
										break;
								}
							?>
							<th class="text-muted">Status: </th>
							<td>
								<span class="<?= $badgeClass ?>"><?=esc($row->transaction_status ?? '')?></span>
							</td>
						</tr>
					<?php endif;?>
					</tbody>
				</table>
			</div>

			<div class="d-flex justify-content-between">
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
					<button class="btn btn-md btn-outline-primary btn-borderless">
						<i class="fa-solid fa-chevron-left"></i> Back
					</button>
				</a>				
                <?php if (in_array(strtolower($row->transaction_status), ['draft', 'pending'])):?>
                <a href="<?= ROOT ?>/<?= $admin_route ?>/<?= $plugin_route ?>/edit/<?= $row->id ?>">
                    <button class="btn btn-md btn-outline-warning btn-borderless">
                        <i class="fa-solid fa-pen-to-square"></i> Update
                    </button>
                </a>
                <?php endif; ?>
			</div>
				<!-- collapse -->
			<!-- <p class="d-inline-flex gap-1 input-group">
				  <a class="btn btn-primary input-group-lable" data-bs-toggle="collapse" href="#collapseExample" role="button" aria-expanded="false" aria-controls="collapseExample">
				    <i class="fa-solid fa-eye"></i> View more
				  </a>
				</p>
				<div class="collapse" id="collapseExample">
				  <div class="card card-body">
				    Some placeholder content for the collapse component. This panel is hidden by default but revealed when the user activates the relevant trigger.
				  </div>
				</div> -->
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
		Access denied. You don't have permission for this action.
	</div>
<?php endif?>

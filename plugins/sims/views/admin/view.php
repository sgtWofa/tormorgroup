<?php if(user_can('view_sim_details')):?>
	<style type="text/css">
		tbody tr{
			white-space: nowrap;
		}
		tbody tr.wrap{
			white-space: normal;
		}

		.img-circle {
		        width: 20px;
		        height: 20px;
		        object-fit: cover;
		        border-radius: 50%;
		        margin: 0px;
		    }
	</style>
	<?php if(!empty($row)): ?>

		<div class="row g-3 col-md-8 col-lg-6 col-sm-12 mx-auto shadow p-2 rounded">
		
			<center>
				<div class="badge">
					<img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:40px;max-height: 40px;object-fit: cover;">
					<strong style="font-size:15px; color: gray;"> <?=APP_NAME?></strong>
				</div>
				<h4 class="text-center" style="font-weight: bolder;">
					Sim Details
				</h4>
				<b class="text-muted"><?=$row->network?> <?=$row->number_assigned?></b><br>
			</center>

			<div class="table-responsive">
		    <table class="table table-striped table-hover border-bottom table-sm">
		        <thead>
		            <tr>
		                <th colspan="2" class="text-primary">TRANSACTION DETAILS</th>
		            </tr>
		        </thead>
		        <tbody>
		            <?php 
		            // Function to render a table row
		            function renderRow($label, $value, $isBadge = false, $badgeClass = '') {
		                if (empty($value)) return;
		                echo "<tr>
		                        <th class='text-muted'>{$label}</th>
		                        <td>" . ($isBadge ? "<span class='{$badgeClass}'>{$value}</span>" : esc($value)) . "</td>
		                      </tr>";
		            }

		            // Transaction details
		            renderRow('Network:', $row->network);
		            renderRow('Assigned #:', $row->number_assigned);
		            renderRow('PUK:', $row->puk);
		            renderRow('Serial #:', $row->serial_number);
		            renderRow('Cost:', number_format($row->cost, 2));
		            renderRow('Wholesale Price:', number_format($row->wholesale_price, 2));
		            renderRow('Retail Price:', number_format($row->retail_price, 2));
		            renderRow('Commission:', number_format($row->commission, 2));

		            // Transaction status
		            if (!empty($row->transaction_status)) {
		                $badgeClass = match (strtolower($row->transaction_status)) {
		                    'expired' => 'badge bg-danger',
		                    'sold - retail', 'sold - wholesale' => 'badge bg-success',
		                    'unassigned' => 'badge bg-warning text-dark',
		                    'assigned' => 'badge bg-primary',
		                    'declined', 'rejected' => 'badge bg-danger',
		                    default => 'badge bg-secondary',
		                };
		                renderRow('Status:', $row->transaction_status, true, $badgeClass);
		                if (in_array(strtolower($row->transaction_status), ['declined','rejected'], true) && !empty($row->rejection_reason)) {
		                    renderRow('Rejection reason:', $row->rejection_reason);
		                }
		            }
		            ?>
		        </tbody>
		    </table>

		    <table class="table table-striped table-hover border-bottom table-sm">
		        <thead>
		            <tr>
		                <th colspan="2" class="text-primary">OTHER DETAILS</th>
		            </tr>
		        </thead>
		        <tbody>
		            <?php 
		            // Render other details
		            if (!empty($row->assigned)) {
		                echo "<tr>
		                        <th class='text-muted'>In charge:</th>
		                        <td>
		                            <span><img src='" . get_image($row->assigned_image) . "' class='img-circle'></span> 
		                            " . esc($row->assigned_first_name . ' ' . $row->assigned_last_name) . "
		                        </td>
		                      </tr>";
		            }

	            if (!empty($row->wallet_in_id)) {
	                echo "<tr>
	                        <th class='text-muted'>Receiving wallet:</th>
	                        <td>" . esc($row->in_wallet_name . ' ' . $row->in_wallet_network) . " 
	                            <span class='float-end'>" . esc($row->in_wallet_number) . "</span>
	                        </td>
	                      </tr>";
	            }

	            echo "<tr>
	                    <th class='text-muted'>SIM image:</th>
	                    <td><img src='" . get_image($row->image ?? '') . "' data-app-image-viewer data-app-image='" . get_image($row->image ?? '') . "' class='rounded' style='width:80px;height:80px;object-fit:cover;cursor:pointer;'></td>
	                  </tr>";
	            renderRow('Date created:', get_date_time($row->date_created));
	
		            renderRow('Last updated:', get_date_time($row->date_updated));
		            // Last updated
		            if (!empty($row->date_updated)) {
		                echo "<tr>
		                        <th class='text-muted'>Updated by:</th>
		                        <td>
		                            <span><img src='" . get_image($row->updated_by_image) . "' class='img-circle'></span> 
		                            " . esc($row->updated_by_first_name . ' ' . $row->updated_by_last_name) . "
		                        </td>
		                      </tr>";
		            }
		            ?>
		        </tbody>
		    </table>
		</div>


			<div class="d-flex justify-content-between">
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
					<button class="btn btn-md btn-outline-primary btn-borderless">
						<i class="fa-solid fa-chevron-left"></i> Back
					</button>
				</a>
				<?php if(user_can('edit_sim') && !in_array(strtolower((string)($row->transaction_status ?? '')), ['declined','rejected'], true)):?>
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$row->id?>">
					<button class="btn btn-md btn-outline-warning btn-borderless">
						<i class="fa-solid fa-pen-to-square"></i> Edit
					</button>
				</a>
				<!-- <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/delete/<?=$row->id?>">
					<button class="btn btn-md btn-outline-danger btn-borderless">
						<i class="fa-solid fa-trash"></i> Delete
					</button>
				</a> -->
			<?php endif;?>
<?=get_app_image_viewer_inline_source()?>
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
		Access denied. You don't have permission for this action.
	</div>
<?php endif?>

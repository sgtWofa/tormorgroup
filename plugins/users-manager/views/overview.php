			<h4>Overview</h4>
	          <p>
          		<i><?=$row->overview ?? ''?></i>
          </p>
          <div class="col table-responsive">
				<table class="table table-striped table-hover border-bottom col-lg-6 col-md-12 table-sm" style="height: fit-content !important;">
					<thead>
						<tr><th colspan="2" class="text-warning">BIO DETAILS</th></tr>
					</thead>
					<tbody>
						<tr>
							<th class="text-muted">Firstname: </th>
							<td><?=esc($row->first_name ?? '')?></td>
						</tr>
						<tr>
							<th class="text-muted">Lastname: </th>
							<td><?=esc($row->last_name ?? '')?></td>
						</tr>
						<?php if(!empty($row->gender)): ?>
						<tr>
							<th class="text-muted">Gender</th>
							<td><?=esc($row->gender)?></td>
						</tr>
						<?php endif;?>			
						<?php if(!empty($row->email)): ?>
						<tr>
							<th class="text-muted">Email: </th>
							<td><?=esc($row->email)?></td>
						</tr>
						<?php endif;?>		
						<tr>
							<th class="text-muted">Phone: </th>
							<td><?=esc($row->phone)?></td>
						</tr>		
						<tr>
							<th class="text-muted">Role: </th>
							<td>
								<?php if(!empty($row->roles)): ?>
						            <?php foreach($row->roles as $role): ?>
						              <?= esc($role) ?>,
						            <?php endforeach ?>
						          <?php endif ?>
							</td>
						</tr>	
						<?php if(!empty($row->education_level)): ?>
						<tr>
							<th class="text-muted">Highest education: </th>
							<td><?=esc($row->education_level, 2)?></td>
						</tr>
						<?php endif;?>				
						<?php if(!empty($row->languages_spoken)): ?>
						<tr>
							<th class="text-muted">Language(s) spoken: </th>
							<td><?=esc($row->languages_spoken)?></td>
						</tr>
						<?php endif;?>			
						<?php if(!empty($row->date_joined)): ?>
						<tr>
							<th class="text-muted">Date joined: </th>
							<td><?=get_date($row->date_joined)?></td>
						</tr>
						<?php endif;?>			
						<?php if(!empty($row->signature)): ?>
						<tr>
							<th class="text-muted">Signature: </th>
							<td><img src="<?=get_image($row->signature, $row->gender)?>" class="img-thumbnail" alt="User Profile Picture" style="cursor:pointer;width:100%;max-width:fit-content;max-height: 80px;object-fit: fill; border: none;"></td>
						</tr>
						<?php endif;?>
					</tbody>
				</table>
			</div>

			<div class="col table-responsive">
				<table class="table table-striped table-hover border-bottom col-lg-6 col-md-12 table-sm" style="height: fit-content !important;">
					<thead>
						<tr><th colspan="2" class="text-warning">OTHER DETAILS</th></tr>
					</thead>
					<tbody>
						
						<?php if(!empty($row->nearest_landmark)): ?>
						<tr>
							<th class="text-muted">Nearest landmark: </th>
							<td><?=esc($row->nearest_landmark)?></td>
						</tr>
						<?php endif;?>					
						<?php if(!empty($row->house_number)): ?>
						<tr>
							<th class="text-muted">House #: </th>
							<td><?=esc($row->house_number)?></td>
						</tr>
						<?php endif;?>					
						<?php if(!empty($row->ghcard)): ?>
						<tr>
							<th class="text-muted">Ghana card #: </th>
							<td><?=esc($row->ghcard)?></td>
						</tr>
						<?php endif;?>				
						<?php if(!empty($row->gps_address)): ?>
						<tr>
							<th class="text-muted">GPS: </th>
							<td><?=esc($row->gps_address)?></td>
						</tr>
						<?php endif;?>				
						<?php if(!empty($row->past_reference)): ?>
						<tr>
							<th class="text-muted">Past reference</th>
							<td><?=esc($row->past_reference, 2)?></td>
						</tr>
						<?php endif;?>
						<?php if(!empty($row->guarantor_name)): ?>
						<tr>
							<th class="text-muted">Guarantor:</th>
							<td><?=esc($row->guarantor_name, 2)?></td>
						</tr>
						<?php endif;?>			
						<?php if(!empty($row->guarantor_phone)): ?>
						<tr>
							<th class="text-muted">Guarantor phone : </th>
							<th><?=esc($row->guarantor_phone, 2)?></th>
						</tr>
						<?php endif;?>
						<tr class="wrap">
							<th class="text-muted">Created :</th>
							<td>
								<span><img src="<?=get_image($row->user_image)?>" style="width: 20px; height: 20px; object-fit: cover; border-radius: 50%; margin: 0px;"></span> 
								<?=$row->user_first_name ?? 'Self' ?> <?=$row->user_last_name ?? ''?>
								<span class="badge bg-secondary text-white" style="font-size: 11px; font-weight:bolder;"><?=get_date_time($row->date_created)?></span>
							</td>
						</tr>
						<?php if(!empty($row->date_updated)):?>
						<tr class="wrap">
							<th class="text-muted">Last updated:</th>
							<td>
								<span><img src="<?=get_image($row->updated_by_image)?>" style="width: 20px; height: 20px; object-fit: cover; border-radius: 50%; margin: 0px;"></span> 
								<?=$row->updated_by_first_name . ' ' . $row->updated_by_last_name ?>
								<span class="badge bg-secondary text-white" style="font-size: 11px; font-weight:bolder;"><?=get_date_time($row->date_updated)?></span>
							</td>
						</tr>
					<?php endif;?>
					</tbody>
				</table>
			</div>
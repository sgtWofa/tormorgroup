<!-- Transactions Tab -->
  <h4>Transactions</h4>
        <p>
      		<i>All transactions undertaken by <?=$row->first_name . ' ' . $row->last_name ?? ''?></i>
      </p>
  	<div class="table-responsive">
		<table class="table table-striped table-hover">
			<tr>
				<th>#</th>
				<th>Type</th>
				<th>Description</th>
				<th>In</th>
				<th>Out</th>
				<th>Date created</th>
			</tr>
    		<?php if(!empty($trans)): $id = 0; ?>
		        	<?php foreach($trans as $trans): $id ++;?>
							<tr>
								<td><span class="badge bg-warning text-black rounded-circle pill"><?=$id?></span><?=$trans->id?></td>
								<td><i><?=$trans->transaction_type ?? ''?></i></td>
								<td class="wrap w-100">
									<a style="text-decoration: none;" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$trans->id?>">
										<?=esc($trans->description ?? '')?>
									</a>
								</td>
								<td><?=number_format($trans->debit ?? 0, 2)?></td>
								<td><?=number_format($trans->credit ?? 0, 2)?></td>
								<td class="wrap"><?=get_date_time($trans->date_created)?></td>
							</tr>
					<?php endforeach?>
				<?php else:?>
					<tr><td colspan="7" class="text-center">No records to show!</td></tr>
	      <?php endif;?>
  		</table>
	<?=$pager->display()?>
	</div>
<?php if(user_can('delete_reversal')):?>
	<?php if(!empty($row)):?>
		<div class="row col-md-8 col-lg-6 mx-auto shadow p-4 rounded" style="margin-top:40px;">
			<form method="post">
				<?=csrf()?>
				<h4 class="text-center mb-3">Delete Reversal</h4>
				<div class="alert alert-danger text-center">Are you sure you want to delete this reversal record?</div>

				<div class="mb-3">
					<label class="form-label">Reversal</label>
					<div class="form-control">#<?=esc($row->id)?> - <?=esc($row->network ?? '')?> / <?=esc($row->service_type ?? '')?></div>
				</div>
				<div class="mb-3">
					<label class="form-label">Source Transaction</label>
					<div class="form-control"><?=esc(reversals_transaction_type_options()[$row->source_transaction_type ?? ''] ?? ($row->source_transaction_type ?? ''))?> / <?=esc($row->source_transaction_id ?? '')?></div>
				</div>
				<div class="mb-3">
					<label class="form-label">Amount</label>
					<div class="form-control"><?=number_format((float)($row->amount ?? 0), 2)?></div>
				</div>

				<div class="d-flex justify-content-between">
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>" class="btn btn-outline-primary">
						<i class="fa-solid fa-chevron-left"></i> Back
					</a>
					<button class="btn btn-danger">
						<i class="fa-solid fa-trash"></i> Delete
					</button>
				</div>
			</form>
		</div>
	<?php else:?>
		<div class="alert alert-danger text-center">That record was not found.</div>
	<?php endif?>
<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>

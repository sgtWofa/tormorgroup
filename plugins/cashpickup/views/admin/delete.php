<?php if(!user_can('delete_cashpickup')): ?>
	<div class="alert alert-danger text-center">Access denied.</div>
<?php else: ?>

<?php if(empty($row)): ?>
	<div class="alert alert-warning text-center">Record not found.</div>
<?php else: ?>
	<form method="post">
		<div class="card col-md-6 mx-auto mt-5 shadow">
			<div class="card-body text-center">
				<?=csrf()?>
				<h5>Delete Cash Pickup #<?=esc($row->id)?></h5>
				<p class="text-muted">This will mark the record as deleted.</p>
				<div class="d-flex justify-content-center gap-2">
					<a class="btn btn-secondary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">Cancel</a>
					<button class="btn btn-danger" type="submit">Delete</button>
				</div>
			</div>
		</div>
	</form>
<?php endif; ?>

<?php endif; ?>

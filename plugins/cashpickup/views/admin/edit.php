<?php if(!(user_can('is_admin') || user_can('is_manager'))): ?>
	<div class="alert alert-danger text-center">Access denied.</div>
<?php else: ?>

<?php if(empty($row)): ?>
	<div class="alert alert-warning text-center">Record not found.</div>
<?php else: ?>

	<?php if(!empty($row->idcard_photo) || !empty($row->customer_photo)): ?>
		<div class="row g-2 mb-3">
			<?php if(!empty($row->idcard_photo)): ?>
				<div class="col-md-6">
					<div class="border rounded p-3">
						<div class="text-muted">Current ID Card Photo</div>
						<a href="<?=ROOT?>/<?=esc($row->idcard_photo)?>" target="_blank">
							<img src="<?=ROOT?>/<?=esc($row->idcard_photo)?>" style="width:100%;max-width:320px;max-height:220px;object-fit:cover;border-radius:10px;border:1px solid rgba(148,163,184,0.6);" />
						</a>
					</div>
				</div>
			<?php endif; ?>
			<?php if(!empty($row->customer_photo)): ?>
				<div class="col-md-6">
					<div class="border rounded p-3">
						<div class="text-muted">Current Customer Photo</div>
						<a href="<?=ROOT?>/<?=esc($row->customer_photo)?>" target="_blank">
							<img src="<?=ROOT?>/<?=esc($row->customer_photo)?>" style="width:100%;max-width:320px;max-height:220px;object-fit:cover;border-radius:10px;border:1px solid rgba(148,163,184,0.6);" />
						</a>
					</div>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php
		$is_edit_mode = true;
		$_POST = (array)$row;
	?>
	<?php require plugin_path('views/admin/add.php'); ?>

<?php endif; ?>
<?php endif; ?>

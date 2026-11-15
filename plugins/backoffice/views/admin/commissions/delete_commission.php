<?php
	$session = get_session();
	$canDelete = backoffice_commission_can_delete_pending($row ?? null, $session);
?>

<?php if($canDelete):?>
	<style>
		form {
			margin-top: 40px !important;
		}
		.form-control, .form-select {
			border-top: none !important;
			border-left: 0.5px solid gray !important;
			border-right: none !important;
			border: 1px solid yellowgreen;
			border-radius: 5px !important;
		}
		.form-label {
			color: gray;
			font-size: 12px;
		}
	</style>
	<?php if(!empty($row)):?>

		<form method="post" enctype="multipart/form-data">
			<div class="row g-3 col-md-10 col-lg-8 mx-auto shadow p-4 rounded">
				<?=csrf()?>

				<h4 class="text-center">
					<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
					Delete Record
				</h4>
				<div class="alert alert-danger text-center">Are you sure you want to delete this record?!</div>

				<div class="mb-3 col-md-12 col-lg-6">
					<label class="form-label">Description</label>
					<div class="form-control"><?=esc($row->description ?? '')?></div>
				</div>

				<div class="mb-3 col-md-12 col-lg-6">
					<label class="form-label">Status</label>
					<div class="form-control"><?=esc($row->transaction_status ?? '')?></div>
				</div>

				<div class="mb-3 col-md-12 col-lg-6">
					<label class="form-label">Amount</label>
					<div class="form-control"><?=esc(number_format((float)($row->amount ?? 0), 2))?></div>
				</div>

				<div class="mb-3 col-md-12 col-lg-6">
					<label class="form-label">Date created</label>
					<div class="form-control"><?=esc(get_date_time($row->date_created ?? ''))?></div>
				</div>

				<div class="d-flex justify-content-between">
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
						<button type="button" class="btn btn-primary">
							<i class="fa-solid fa-chevron-left"></i> Back
						</button>
					</a>
					<button class="btn btn-danger">
						<i class="fa-solid fa-save"></i> Delete
					</button>
				</div>
			</div>
		</form>

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
		Access denied. You dont have permission for this action
	</div>
<?php endif?>

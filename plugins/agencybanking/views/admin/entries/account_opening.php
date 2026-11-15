<?php if(user_can('add_agencybanking') || user_can('edit_momoremit')):?>
	<link rel="stylesheet" href="<?=plugin_http_path('assets/css/agency-forms.css')?>?v=<?= (int)filemtime(__DIR__ . '/../../../assets/css/agency-forms.css') ?>">
	<form method="post" enctype="multipart/form-data" class="agency-entry-form agency-account-opening-form">
		<div class="row g-3 account-opening-card">
			<?=csrf()?>

			<h4 class="text-center mb-4 text-body-emphasis">Account Opening Form</h4>

			<input type="hidden" name="transaction_type" value="Account opening">
			<?php if (!empty($row) && !empty($row->id)): ?>
				<div class="mb-3">
					<label for="transaction_status" class="form-label">Status</label>
					<select class="form-select" name="transaction_status" id="transaction_status">
						<option value="">---Select---</option>
						<option <?=old_select('transaction_status','Draft', $row->transaction_status ?? '')?> value="Draft">Draft</option>
						<option <?=old_select('transaction_status','Pending', $row->transaction_status ?? '')?> value="Pending">Pending</option>
						<?php if(user_can('approve_transactions')):?>
							<option <?=old_select('transaction_status','Declined', $row->transaction_status ?? '')?> value="Declined">Declined</option>
							<option <?=old_select('transaction_status','Rejected', $row->transaction_status ?? '')?> value="Rejected">Rejected</option>
							<option <?=old_select('transaction_status','Approved', $row->transaction_status ?? '')?> value="Approved">Approved</option>
						<?php endif; ?>
					</select>
					<?php if(!empty($errors['transaction_status'])):?>
						<small class="text-danger"><?=$errors['transaction_status']?></small>
					<?php endif?>
				</div>
			<?php else: ?>
				<input type="hidden" name="transaction_status" value="Pending">
			<?php endif; ?>

			<div class="mb-3">
				<label for="full_name" class="form-label">Name on Ghana card</label>
				<input name="full_name" value="<?=old_value('full_name')?>" type="text" class="form-control" id="full_name" placeholder="Enter name on Ghana card">
				<?php if(!empty($errors['full_name'])):?>
					<small class="text-danger"><?=$errors['full_name']?></small>
				<?php endif?>
			</div>

			<div class="mb-3">
				<label for="phone" class="form-label">Phone #</label>
				<input name="phone" value="<?=old_value('phone')?>" type="text" class="form-control" id="phone" placeholder="Enter phone number">
				<?php if(!empty($errors['phone'])):?>
					<small class="text-danger"><?=$errors['phone']?></small>
				<?php endif?>
			</div>

			<div class="mb-3">
				<label for="network" class="form-label">Account opened</label>
				<select class="form-select" name="network" id="network">
					<option value="">---Select---</option>
					<option <?=old_select('network','Ghanapay')?> value="Ghanapay">Ghanapay</option>
					<option <?=old_select('network','Calbank')?> value="Calbank">Calbank</option>
					<option <?=old_select('network','Ecobank')?> value="Ecobank">Ecobank</option>
					<option <?=old_select('network','Fidelity')?> value="Fidelity">Fidelity</option>
					<option <?=old_select('network','FBN')?> value="FBN">FBN</option>
					<option <?=old_select('network','FNB')?> value="FNB">FNB</option>
					<option <?=old_select('network','Nafana rural bank')?> value="Nafana rural bank">Nafana rural bank</option>
					<option <?=old_select('network','Suma rural bank')?> value="Suma rural bank">Suma rural bank</option>
				</select>
				<?php if(!empty($errors['network'])):?>
					<small class="text-danger"><?=$errors['network']?></small>
				<?php endif?>
			</div>

			<div class="mb-3">
				<label for="transaction_image" class="form-label">Proof of Account opened</label>
				<input name="transaction_image" type="file" class="form-control" id="transaction_image" accept="image/*">
				<?php if(!empty($errors['transaction_image'])):?>
					<small class="text-danger"><?=$errors['transaction_image']?></small>
				<?php endif?>
			</div>

			<div class="mb-4">
				<div class="alert alert-info mb-0">
					Assigned to operations manager or admin for approval.
				</div>
			</div>

			<div class="d-flex justify-content-between">
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>" class="btn btn-outline-secondary">
					<i class="fa-solid fa-chevron-left"></i> Back
				</a>
				<button class="btn btn-primary">
					<i class="fa-solid fa-send"></i> Submit for Approval
				</button>
			</div>
		</div>
	</form>

<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>

<?php
	$session = get_session();
	$currentUserId = (int)($session->id ?? 0);
	$rowOwnerId = (is_object($row ?? null) ? (int)($row->created_by ?? 0) : 0);
	$rowAssignedId = (is_object($row ?? null) ? (int)($row->assigned ?? 0) : 0);
	$statusLower = strtolower((string)($row->transaction_status ?? ''));
	$canDelete = (user_can('delete_request') || ($currentUserId > 0 && ($rowOwnerId === $currentUserId || $rowAssignedId === $currentUserId))) && in_array($statusLower, ['pending','draft'], true);
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

				<?php
					$createdName = trim((string)($row->user_first_name ?? '') . ' ' . (string)($row->user_last_name ?? ''));
					$assignedName = trim((string)($row->assigned_first_name ?? '') . ' ' . (string)($row->assigned_last_name ?? ''));
					$details = [];
					$net = trim((string)($row->network ?? ''));
					if($net !== '') $details[] = ['Network', $net];
					$rtype = trim((string)($row->request_type ?? ''));
					if($rtype !== '') $details[] = ['Request type', $rtype];
					$till = trim((string)($row->till_number ?? ''));
					if($till !== '') $details[] = ['Till / Code', $till];
					$wallet = trim((string)($row->wallet_number ?? ''));
					if($wallet !== '') $details[] = ['Wallet number', $wallet];
					$details[] = ['Amount', number_format((float)($row->amount ?? 0), 2)];
					$stat = trim((string)($row->transaction_status ?? ''));
					if($stat !== '') $details[] = ['Status', $stat];
					if($createdName !== '') $details[] = ['Created by', $createdName];
					if($assignedName !== '') $details[] = ['Assigned to', $assignedName];
					$dc = !empty($row->date_created) ? get_date_time($row->date_created, 2) : '';
					if(!empty($dc)) $details[] = ['Date created', $dc];
				?>

				<?php foreach($details as $pair): ?>
					<div class="mb-3 col-md-12 col-lg-6">
						<label class="form-label"><?=esc($pair[0])?></label>
						<div class="form-control"><?=esc($pair[1])?></div>
					</div>
				<?php endforeach; ?>

				<?php if(!empty(trim((string)($row->description ?? '')))): ?>
					<div class="mb-3 col-12">
						<label class="form-label">Narration</label>
						<div class="form-control"><?=esc($row->description ?? '')?></div>
					</div>
				<?php endif; ?>

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

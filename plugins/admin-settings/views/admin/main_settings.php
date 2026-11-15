<style type="text/css">
	.settings-shell{max-width: 1100px; margin: auto;}
	.settings-card{
		border: 1px solid rgba(0,0,0,.06);
		border-radius: 16px;
		box-shadow: 0 12px 30px rgba(16,24,40,.08);
		background: var(--bs-body-bg);
	}
	.settings-label{min-width: 220px;}
	.settings-card .input-group{
		display: grid;
		grid-template-columns: 260px 1fr;
		align-items: start;
		gap: 10px;
	}
	.settings-card .input-group > .input-group-text{
		width: auto;
		border-radius: 12px;
		padding: .7rem .9rem;
		justify-content: flex-start;
		white-space: normal;
		line-height: 1.2;
	}
	.settings-card .input-group > .form-control{
		width: 100%;
		border-radius: 12px;
		margin: 0;
		min-width: 0;
	}
	.settings-card .input-group > .input-group-text,
	.settings-card .input-group > .form-control{
		grid-row: 1;
	}
	.settings-card .input-group > .input-group-text{grid-column: 1;}
	.settings-card .input-group > .form-control{grid-column: 2;}
	@media (max-width: 768px){
		.settings-card .input-group{grid-template-columns: 1fr;}
		.settings-card .input-group > .input-group-text{grid-column: 1;grid-row: 1;}
		.settings-card .input-group > .form-control{grid-column: 1;grid-row: 2;}
	}
</style>
<?php if(!empty($row)): ?>
	<div class="settings-shell mx-auto">
		<div class="card settings-card">
			<div class="card-body p-4">
				<div class="row g-3 mb-5 pb-5 col-md-10 mx-auto">
					<h4 class="text-center"><span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span> Settings</h4>
					
					<div class="row">
				<label class="col-md-3 text-center">
					<img src="<?=get_image($row->image)?>" class="img-thumbnail" style="cursor:pointer;width:100%;max-width:200px;max-height:200px;object-fit:contain;">
					<label for="image" class="form-label fw-bolder text-muted">CEO's signature</label>
				</label>

				<div class="col-md-9">
					<div class="mb-3 col-md-12 input-group">
					  <label for="company_name" class="input-group-text fw-bolder text-muted col-4">Company Name</label>
					  <div class="form-control"><?=$row->company_name ?? ''?></div>
					</div>
					<div class="mb-3 col-md-12 input-group">
					  <label for="ceo_name" class="input-group-text fw-bolder text-muted col-4">CEO's Name</label>
					  <div class="form-control"><?= $row->ceo_name ?? ''?></div>
					</div>

					<div class="mb-3 col-md-12 input-group">
					  <label for="title" class="input-group-text fw-bolder text-muted col-4">Title</label>
					  <div class="form-control"><?=$row->title ?? 'Title'?></div>
					</div>

					<div class="mb-3 col-md-12 input-group">
					  <label for="ceo_contact" class="input-group-text fw-bolder text-muted col-4">CEO's Contact</label>
					  <div class="form-control"><?=$row->ceo_contact ?? "Head's Contact"?></div>
					</div>

					<div class="mb-3 col-md-12 input-group">
					  <label for="admin_contact" class="input-group-text fw-bolder text-muted col-4">Office Contact</label>
					  	<div class="form-control"><?=$row->admin_contact ?? 'Office Contact'?></div>
					</div>

					<div class="mb-3 col-md-12 input-group">
					  <label for="admin_contact" class="input-group-text fw-bolder text-muted col-4">Date Started</label>
					  	<div class="form-control"><?=get_date($row->date_started)?></div>
					</div>

					<div class="mb-3 col-md-12 input-group">
					  <label for="transaction_fee" class="input-group-text fw-bolder text-muted col-4">Transaction charges</label>
					  <div class="form-control" ><?=number_format($row->transaction_fee ?? 0, 2)?></div>
					</div>

					<div class="mb-3 col-md-12 input-group">
					  <label for="momo_charges" class="input-group-text fw-bolder text-muted col-4">Momo charges ( in %)</label>
					  <div class="form-control" ><?=$row->momo_charges ?? 0?></div>
					</div>

					<div class="mb-3 col-md-12 input-group">
					  <label for="ezwich_charges" class="input-group-text fw-bolder text-muted col-4">Ezwich charges ( in %)</label>
					  <div class="form-control" ><?=$row->ezwich_charges ?? 0?></div>
					</div>

					<div class="mb-3 col-md-12 input-group">
					  <label for="agencybanking_charges" class="input-group-text fw-bolder text-muted col-4">Agencybanking charges ( in %)</label>
					  <div class="form-control" ><?=$row->agencybanking_charges ?? 0?></div>
					</div>

					<div class="mb-3 col-md-12 input-group">
					  <label for="date_started" class="input-group-text fw-bolder text-muted col-4">CFA Buying Rate</label>
					  <div class="form-control" >CFA1,000 = GHS<?=number_format($row->buying_rate ?? 0, 2)?></div>
					</div>

					<div class="mb-3 col-md-12 input-group">
					  <label for="date_started" class="input-group-text fw-bolder text-muted col-4">CFA Selling Rate</label>
					  <div class="form-control" >CFA1,000 = GHS<?=number_format($row->selling_rate ?? 0, 2)?></div>
					</div>

					<div class="mb-3 col-md-12 input-group">
					  <label for="company_sim" class="input-group-text fw-bolder text-muted col-4">Company's Sim</label>
					  <div class="form-control" ><?=esc($row->company_sim ?? '')?></div>
					</div>

					<div class="mb-3 col-md-12 input-group">
					  <label for="date_started" class="input-group-text fw-bolder text-muted col-4">Opening Time </label>
					  <div class="form-control" ><?=esc($row->work_open ?? '')?></div>
					</div>

					<div class="mb-3 col-md-12 input-group">
					  <label for="date_started" class="input-group-text fw-bolder text-muted col-4">Closing Time</label>
					  <div class="form-control" ><?=esc($row->work_close ?? '')?></div>
					</div>
				</div>
			</div>
			<div class="d-flex justify-content-between">
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
					<button type="button" class="btn btn-danger">
						<i class="fa-solid fa-chevron-left"></i> Back
					</button>
				</a>
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$row->id?>">
					<button type="button" class="btn btn-primary">
						<i class="fa-solid fa-pencil"></i> Edit
					</button>
				</a>
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
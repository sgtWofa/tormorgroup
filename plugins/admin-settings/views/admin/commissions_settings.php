
		<div class="row g-3 mb-5 pb-5 col-md-10 mx-auto">
			<h4 class="text-center"><span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>Charges Settings</h4>
			
			<div class="row">
				<h3>CHARGES CONTENT HERE</h3>
				<div class="">
					
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
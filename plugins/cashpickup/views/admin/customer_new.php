<?php if(!(user_can('add_cashpickup') || user_can('edit_cashpickup'))): ?>
	<div class="alert alert-danger text-center">Access denied.</div>
<?php else: ?>

<?php
	$prefill = [
		'full_name' => trim((string)($_GET['full_name'] ?? '')),
		'phone' => trim((string)($_GET['phone'] ?? '')),
		'id_type' => trim((string)($_GET['id_type'] ?? '')),
		'id_number' => trim((string)($_GET['id_number'] ?? '')),
		'location' => trim((string)($_GET['location'] ?? '')),
	];
?>

<div class="d-flex align-items-center justify-content-between mb-3">
	<div>
		<h4 class="mb-0">Register Customer</h4>
		<small class="text-muted">Add a cash pickup customer profile</small>
	</div>
	<div class="d-flex gap-2">
		<?php if (user_can('view_customers_ledger')): ?>
			<a class="btn btn-outline-secondary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customers"> <i class="fa-solid fa-arrow-left"></i> Back to Customers</a>			
		<?php else: ?>
			<a class="btn btn-outline-danger" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>"> <i class="fa-solid fa-arrow-left"></i> Back to Cashpickup</a>			
		<?php endif ?>
	</div>
</div>

<div class="card border-0 shadow-sm">
	<div class="card-body">
		<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer_new">
			<?=csrf()?>
			<input type="hidden" name="existing_customer_id" id="existing_customer_id" value="">
			<div class="row g-3 col-md-10 mx-auto">
				<div class="col-12">
					<div class="alert alert-info d-none" id="customerLookupAlert" role="alert">
						<span id="customerLookupText">Customer lookup</span>
						<a class="btn btn-sm btn-outline-primary ms-2 d-none" id="customerLookupLink" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customers">Open existing ledger</a>
					</div>
				</div>
				<div class="col-md-6">
					<label class="form-label">Full name</label>
					<input name="full_name" id="full_name" type="text" class="form-control" value="<?=esc(old_value('full_name') ?: $prefill['full_name'])?>">
				</div>
				<div class="col-md-6">
					<label class="form-label">Phone number</label>
					<input name="phone_number" id="phone_number" type="text" class="form-control" value="<?=esc(old_value('phone_number') ?: $prefill['phone'])?>">
				</div>
				<div class="col-md-4">
					<label class="form-label">ID type</label>
					<?php $idTypeVal = old_value('id_type') ?: $prefill['id_type']; ?>
					<select name="id_type" id="id_type" class="form-select">
						<option value="Ghana Card" <?=($idTypeVal === 'Ghana Card') ? 'selected' : ''?>>Ghana Card</option>
					</select>
				</div>
				<div class="col-md-4">
					<label class="form-label">ID number</label>
					<input name="id_number" id="id_number" type="text" class="form-control" value="<?=esc(old_value('id_number') ?: $prefill['id_number'])?>">
				</div>
				<div class="col-md-4">
					<label class="form-label">Location / Address</label>
					<input name="location" id="location" type="text" class="form-control" value="<?=esc(old_value('location') ?: $prefill['location'])?>">
				</div>
				<div class="col-12 d-flex justify-content-end">
					<button type="submit" class="btn btn-primary" id="customerRegisterSubmit">Save</button>
				</div>
			</div>
		</form>
	</div>
</div>

<script>
	(function(){
		const phoneInput = document.getElementById('phone_number');
		const idInput = document.getElementById('id_number');
		const nameInput = document.getElementById('full_name');
		const idTypeInput = document.getElementById('id_type');
		const locationInput = document.getElementById('location');
		const existingInput = document.getElementById('existing_customer_id');
		const alertEl = document.getElementById('customerLookupAlert');
		const alertText = document.getElementById('customerLookupText');
		const alertLink = document.getElementById('customerLookupLink');
		const submitBtn = document.getElementById('customerRegisterSubmit');
		let timer = null;
		let isPopulating = false;

		const setAlert = (message, link) => {
			if(!alertEl) return;
			if(!message){
				alertEl.classList.add('d-none');
				if(alertLink) alertLink.classList.add('d-none');
				return;
			}
			alertEl.classList.remove('d-none');
			if(alertText) alertText.textContent = message;
			if(alertLink){
				if(link){
					alertLink.href = link;
					alertLink.classList.remove('d-none');
				} else {
					alertLink.classList.add('d-none');
				}
			}
		};

		const setSubmitLabel = (label) => {
			if(submitBtn) submitBtn.textContent = label;
		};

		const setExisting = (data) => {
			isPopulating = true;
			if(existingInput) existingInput.value = data.id ? String(data.id) : '';
			if(nameInput) nameInput.value = data.full_name || '';
			if(phoneInput) phoneInput.value = data.phone_number || '';
			if(idTypeInput) idTypeInput.value = data.id_type || '';
			if(idInput) idInput.value = data.id_number || '';
			if(locationInput) locationInput.value = data.location || '';
			const link = data.id ? `<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer/${data.id}` : '';
			setAlert('Customer already exists. Details loaded below.', link);
			setSubmitLabel('Update Customer');
			isPopulating = false;
		};

		const clearExisting = () => {
			if(existingInput) existingInput.value = '';
			setAlert('', '');
			setSubmitLabel('Save');
		};

		const doLookup = async () => {
			const phone = phoneInput && phoneInput.value ? phoneInput.value.trim() : '';
			const idNumber = idInput && idInput.value ? idInput.value.trim() : '';
			if(phone === '' && idNumber === ''){
				clearExisting();
				return;
			}
			setAlert('Checking customer ledger...', '');
			try{
				const url = `<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer_lookup?phone=${encodeURIComponent(phone)}&id_number=${encodeURIComponent(idNumber)}`;
				const res = await fetch(url, {headers: {'Accept':'application/json'}});
				const json = await res.json();
				if(!json || !json.success || !json.data){
					clearExisting();
					return;
				}
				setExisting(json.data);
			}catch(e){
				clearExisting();
			}
		};

		const scheduleLookup = () => {
			if(isPopulating) return;
			if(timer) clearTimeout(timer);
			timer = setTimeout(doLookup, 350);
		};

		phoneInput && phoneInput.addEventListener('input', scheduleLookup);
		idInput && idInput.addEventListener('input', scheduleLookup);
		idTypeInput && idTypeInput.addEventListener('change', scheduleLookup);

		doLookup();
	})();
</script>

<?php endif; ?>

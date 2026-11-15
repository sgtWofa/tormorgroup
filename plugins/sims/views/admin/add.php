<?php if(user_can('add_sim')): ?>
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
		.input-group-text{
			font-weight: bolder;
		}
		.form-label {
			color: gray;
			font-size: 12px;
		}
		.identifier-thumbnail {
			width: 100%;
			min-height: 100px;
			background: rgba(255,255,255,.05);
			border: 1px dashed rgba(255,255,255,.2);
			border-radius: 8px;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			cursor: pointer;
			overflow: hidden;
		}
		.identifier-thumbnail img {
			width: 100%;
			height: 100%;
			object-fit: cover;
		}
		.identifier-thumbnail .placeholder {
			background: var(--bg-color);
			font-size: 13px;
			text-align: center;
			padding: 0 6px;
			border-radius: 5px;
			color: darkorange;
		}
	</style>
	<form method="post" enctype="multipart/form-data" id="sim-multi-form">		
		<div class="row g-3 col-md-10 col-lg-8 mx-auto shadow p-4 rounded">

			<?=csrf()?>

			<h4 class="text-center">
				<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
				New Sim
			</h4>

			<div class="mb-3 col-md-12 col-lg-6">
				<label for="network" class="form-label">Network</label>
				<select class="form-select" name="network" id="network">	
				  <option value="">---Select---</option>
				  <option <?=old_select('network','mtn')?> value="mtn">mtn</option>
				  <option <?=old_select('network','at')?> value="at">at</option>
				  <option <?=old_select('network','telecel')?> value="telecel">telecel</option>
				  <option <?=old_select('network','starLink')?> value="starLink">starLink</option>
				</select>
				<?php if(!empty($errors['network'])): ?>
				  <small class="text-danger"><?=$errors['network']?></small>
				<?php endif ?>
			</div>

			<div class="mb-3 col-md-12 col-lg-6">
			  <label for="cost" class="form-label">Cost price </label>
			  <div class="input-group">
			  	<div id="currencySymbol" class="input-group-text">GH₵</div>
			  	<input name="cost" value="<?=old_value('cost')?>" type="text" class="form-control" id="cost" placeholder="GH&cent;0.00">
			  	<input name="transaction_status" value="Unassigned" type="hidden">
			  </div>				
				<?php if(!empty($errors['cost'])): ?>
				  <small class="text-danger"><?=$errors['cost']?></small>
				<?php endif ?>
			</div>

			<div class="mb-3 col-md-12 col-lg-6">
			  <label for="wholesale_price" class="form-label">Wholesale price </label>
			  <div class="input-group">
			  	<div id="currencySymbol" class="input-group-text">GH₵</div>
			  	<input name="wholesale_price" value="<?=old_value('wholesale_price')?>" type="text" class="form-control" id="wholesale_price" placeholder="GH&cent;0.00">
			  </div>				
				<?php if(!empty($errors['wholesale_price'])): ?>
				  <small class="text-danger"><?=$errors['wholesale_price']?></small>
				<?php endif ?>
			</div>

			<div class="mb-3 col-md-12 col-lg-6">
			  <label for="retail_price" class="form-label">Retail price </label>
			  <div class="input-group">
			  	<div id="currencySymbol" class="input-group-text">GH₵</div>
			  	<input name="retail_price" value="<?=old_value('retail_price')?>" type="text" class="form-control" id="retail_price" placeholder="GH&cent;0.00">
			  </div>				
				<?php if(!empty($errors['retail_price'])): ?>
				  <small class="text-danger"><?=$errors['retail_price']?></small>
				<?php endif ?>
			</div>

			<div class="col-12">
				<div class="d-flex align-items-center justify-content-between">
					<label class="form-label mb-0">SIM Identifiers</label>
					<div class="btn-group">
						<button type="button" class="btn btn-outline-success btn-sm" id="add-identifier">
							<i class="fa-solid fa-plus"></i> Add SIM
						</button>
						<button type="button" class="btn btn-outline-secondary btn-sm d-none" id="collapse-identifiers">
							<i class="fa-solid fa-layer-group"></i> Toggle Compact
						</button>
					</div>
				</div>
				<div class="form-text text-muted mb-2">Network, pricing, and status apply to all Sims. Each entry needs a unique Serial & PUK.</div>
				<div id="identifier-list" class="d-flex flex-column gap-3"></div>
				<input type="hidden" name="sim_entries" id="sim_entries" value="[]">
				<?php if(!empty($errors['serial_number'])): ?>
				  <small class="text-danger d-block"><?=$errors['serial_number']?></small>
				<?php endif ?>
				<?php if(!empty($errors['puk'])): ?>
				  <small class="text-danger d-block"><?=$errors['puk']?></small>
				<?php endif ?>
			</div>

			<!-- <div class="mb-3 col-md-12 col-lg-6 transaction_status">
			  <label for="transaction_status" class="form-label">Sim Status</label>
			  <select class="form-select transaction_status" id="transaction_status" name="transaction_status">
				  <option value="">---Select---</option>
				<?php if(user_can('approve_transactions')):?>
				  <option <?=old_select('transaction_status','Unassigned')?> value="Unassigned">Unassigned</option>
				  <option <?=old_select('transaction_status','Assigned')?> value="Assigned">Assigned</option>
				  <option <?=old_select('transaction_status','Expired')?> value="Expired">Expired</option>
				<?php endif;?>
				  <option <?=old_select('transaction_status','Sold - wholesale')?> value="Sold - wholesale">Sold - wholesale</option>
				  <option <?=old_select('transaction_status','Sold - retail')?> value="Sold - retail">Sold - retail</option>
			  </select>

				<?php if(!empty($errors['transaction_status'])): ?>
				  <small class="text-danger"><?=$errors['transaction_status']?></small>
				<?php endif ?>
			</div> -->

			<!-- <div class="mb-3 col-md-12 col-lg-6 assigned">
			  <label for="assigned" class="form-label">In charge</label>
			  <select class="form-select assigned" name="assigned">
				  <option value="">---Select---</option>
				  <?php if(!empty($users)):?>
				  	<?php foreach ($users as $user): ?>				  		
				  		<option <?=old_select('assigned',$user->id ?? '')?> value="<?=$user->id ?? ' ' ?>"><?=$user->first_name . ' '. $user->last_name ?? ''?></option>
				  	<?php endforeach;?>
				  	<?php endif;?>
			  </select>
			  	<?php if(!empty($errors['assigned'])): ?>
				  <small class="text-danger"><?=$errors['assigned']?></small>
				<?php endif ?>
			</div> -->

			<div class="d-flex justify-content-between flex-wrap gap-2 align-items-center" style="margin-top:10px;">
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
					<button type="button" class="btn btn-primary">
						<i class="fa-solid fa-chevron-left"></i> Back
					</button>
				</a>
				<div class="d-flex align-items-center gap-3">
					<div class="text-danger fw-bold d-none" id="global-error"></div>
					<button class="btn btn-danger" type="submit" id="save-button">
						<i class="fa-solid fa-save"></i> Save
					</button>
				</div>
			</div>
		</div>
	</form>

<?php else: ?>
	<div class="alert alert-danger text-center">
		Access denied. You don’t have permission for this action.
	</div>
<?php endif ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
	const identifierList = document.getElementById('identifier-list');
	const addButton = document.getElementById('add-identifier');
	const collapseButton = document.getElementById('collapse-identifiers');
	const entriesInput = document.getElementById('sim_entries');
	const networkSelect = document.getElementById('network');
	const saveButton = document.getElementById('save-button');
	const globalError = document.getElementById('global-error');
	const csrfToken = document.querySelector("input[name='token']")?.value ?? '';

	let compactMode = false;
	let nextIndex = 0;

	const template = ({ key, serial = '', puk = '' }) => `
		<div class="identifier-card border rounded p-3 position-relative bg-dark text-white" data-key="${key}">
			<div class="position-absolute top-0 end-0 p-2 d-flex gap-2">
				<button type="button" class="btn btn-sm btn-outline-warning duplicate" data-key="${key}"><i class="fa-solid fa-copy"></i></button>
				<button type="button" class="btn btn-sm btn-outline-danger remove" data-key="${key}"><i class="fa-solid fa-trash"></i></button>
			</div>
			<div class="row g-3 align-items-center">
				<div class="col-md-6">
					<label class="form-label">Serial Number</label>
					<input type="text" class="form-control identifier-serial" data-key="${key}" value="${serial}" placeholder="e.g 894xxxxxxxxxxx">
					<div class="text-danger small mt-1 identifier-serial-error" data-key="${key}"></div>
				</div>
				<div class="col-md-6">
					<label class="form-label">PUK</label>
					<input type="text" class="form-control identifier-puk" data-key="${key}" value="${puk}" placeholder="e.g 12345678">
					<div class="text-danger small mt-1 identifier-puk-error" data-key="${key}"></div>
				</div>
			</div>
			<div class="row g-3 align-items-end">
				<div class="col-md-4">
					<label class="form-label d-block">Photo (optional)</label>
					<label class="identifier-thumbnail" for="image_${key}">
						<img class="identifier-image-preview" data-key="${key}" alt="SIM image preview" style="display:none;" />
						<span class="placeholder"><i class="fa-solid fa-photo"></i> Upload SIM photo</span>
					</label>
					<input type="file" id="image_${key}" name="image[${key}]" accept="image/*" class="d-none identifier-image" data-key="${key}">
					<div class="text-danger small mt-1 identifier-image-error" data-key="${key}"></div>
				</div>
			</div>
		</div>
	`;

	const getEntries = () => {
		return Array.from(identifierList.querySelectorAll('.identifier-card')).map(card => {
			const key = card.dataset.key;
			const serial = card.querySelector('.identifier-serial').value.trim();
			const puk = card.querySelector('.identifier-puk').value.trim();
			return { key, serial, puk };
		});
	};

	const handleImageChange = (input) => {
		const key = input.dataset.key;
		const preview = identifierList.querySelector(`.identifier-image-preview[data-key='${key}']`);
		const file = input.files && input.files[0];
		if (!preview) return;
		if (file) {
			const reader = new FileReader();
			reader.onload = () => {
				preview.src = reader.result;
				preview.style.display = 'block';
			};
			reader.readAsDataURL(file);
		} else {
			preview.src = '';
			preview.style.display = 'none';
		}
	};

	const createEntryNode = ({ key, serial = '', puk = '' }) => {
		const temp = document.createElement('template');
		temp.innerHTML = template({ key, serial, puk }).trim();
		const node = temp.content.firstChild;
		const input = node.querySelector('.identifier-image');
		const preview = node.querySelector('.identifier-image-preview');
		if (input) {
			input.value = '';
			input.addEventListener('change', () => handleImageChange(input));
		}
		if (preview) {
			preview.src = '';
			preview.style.display = 'none';
		}
		return node;
	};

	const appendEntry = (entry = {}) => {
		if (!entry.key) {
			entry.key = `sim_${Date.now()}_${nextIndex++}`;
		}
		if (identifierList.querySelector('.alert')) {
			identifierList.innerHTML = '';
		}
		const node = createEntryNode(entry);
		identifierList.appendChild(node);
		toggleEmptyState();
		syncHiddenInput();
		validateClient();
	};

	const toggleEmptyState = () => {
		const hasEntries = identifierList.querySelectorAll('.identifier-card').length > 0;
		if (!hasEntries) {
			identifierList.innerHTML = `<div class="alert alert-warning text-center">
				<i class="fa-solid fa-layer-group me-2"></i>No SIM entries yet. Click "Add SIM" to begin.
			</div>`;
		}
	};

	const syncHiddenInput = () => {
		entriesInput.value = JSON.stringify(getEntries());
	};

	const validateClient = () => {
		const entries = getEntries();
		const serialSet = new Map();
		const pukSet = new Map();
		let hasError = false;
		globalError.classList.add('d-none');
		globalError.textContent = '';

		entries.forEach(({ key, serial, puk }) => {
			const serialError = identifierList.querySelector(`.identifier-serial-error[data-key='${key}']`);
			const pukError = identifierList.querySelector(`.identifier-puk-error[data-key='${key}']`);
			serialError.textContent = '';
			pukError.textContent = '';

			if(!serial){
				serialError.textContent = 'Serial number is required.';
				hasError = true;
			}else if(serialSet.has(serial)){
				serialError.textContent = 'Duplicate serial in form.';
				identifierList.querySelector(`.identifier-serial-error[data-key='${serialSet.get(serial)}']`).textContent = 'Duplicate serial in form.';
				hasError = true;
			}else{
				serialSet.set(serial, key);
			}

			if(!puk){
				pukError.textContent = 'PUK is required.';
				hasError = true;
			}else if(pukSet.has(puk)){
				pukError.textContent = 'Duplicate PUK in form.';
				identifierList.querySelector(`.identifier-puk-error[data-key='${pukSet.get(puk)}']`).textContent = 'Duplicate PUK in form.';
				hasError = true;
			}else{
				pukSet.set(puk, key);
			}
		});

		saveButton.disabled = hasError || entries.length === 0 || !networkSelect.value;
		return !hasError;
	};

	const remoteValidate = async () => {
		const entries = getEntries();
		if(entries.length === 0 || !networkSelect.value){
			return;
		}

		saveButton.disabled = true;
		const response = await fetch('<?=ROOT?>/admin/<?=$plugin_route?>', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({
				action: 'validate-sim-identifiers',
				token: csrfToken,
				network: networkSelect.value,
				entries,
			}),
		});
		const payload = await response.json();
		let hasTaken = false;

		entries.forEach(({ key }) => {
			identifierList.querySelector(`.identifier-serial-error[data-key='${key}']`).dataset.remote = '';
			identifierList.querySelector(`.identifier-puk-error[data-key='${key}']`).dataset.remote = '';
		});

		if(payload.success){
			payload.results.forEach(({ key, serialExists, pukExists }) => {
				const serialError = identifierList.querySelector(`.identifier-serial-error[data-key='${key}']`);
				const pukError = identifierList.querySelector(`.identifier-puk-error[data-key='${key}']`);
				serialError.dataset.remote = serialExists ? 'exists' : '';
				pukError.dataset.remote = pukExists ? 'exists' : '';
				if(serialExists){
					serialError.textContent = 'Serial already exists for this network.';
					hasTaken = true;
				}
				if(pukExists){
					pukError.textContent = 'PUK already exists for this network.';
					hasTaken = true;
				}
			});
		}else{
			globalError.textContent = payload.message || 'Could not validate identifiers. Try again.';
			globalError.classList.remove('d-none');
		}

		saveButton.disabled = hasTaken || !validateClient();
	};

	const addEntry = (prefill = {}) => {
		const key = `sim_${Date.now()}_${nextIndex++}`;
		appendEntry({ key, serial: prefill.serial || '', puk: prefill.puk || '' });
	};

	identifierList.addEventListener('click', (event) => {
		const btn = event.target.closest('button');
		if(!btn){
			return;
		}
		const key = btn.dataset.key;
		if(btn.classList.contains('remove')){
			const card = identifierList.querySelector(`.identifier-card[data-key='${key}']`);
			if (card) {
				card.remove();
			}
			toggleEmptyState();
			syncHiddenInput();
			validateClient();
		}else if(btn.classList.contains('duplicate')){
			const card = identifierList.querySelector(`.identifier-card[data-key='${key}']`);
			if(card){
				const serial = card.querySelector('.identifier-serial').value.trim();
				const puk = card.querySelector('.identifier-puk').value.trim();
				appendEntry({ serial, puk });
			}
		}
	});

	identifierList.addEventListener('input', () => {
		syncHiddenInput();
		validateClient();
	});

	identifierList.addEventListener('change', (event) => {
		const input = event.target.closest('.identifier-image');
		if (!input) return;
		const key = input.dataset.key;
		const preview = identifierList.querySelector(`.identifier-image-preview[data-key='${key}']`);
		const file = input.files && input.files[0];
		if (preview) {
			if (file) {
				const reader = new FileReader();
				reader.onload = () => {
					preview.src = reader.result;
					preview.style.display = 'block';
				};
				reader.readAsDataURL(file);
			} else {
				preview.src = '';
				preview.style.display = 'none';
			}
		}
	});

	addButton.addEventListener('click', () => {
		appendEntry();
		remoteValidate();
	});

	collapseButton.addEventListener('click', () => {
		compactMode = !compactMode;
		identifierList.classList.toggle('compact-mode', compactMode);
	});

	networkSelect.addEventListener('change', () => {
		validateClient();
		remoteValidate();
	});

	const form = document.getElementById('sim-multi-form');
	form.addEventListener('submit', (event) => {
		syncHiddenInput();
		if(!validateClient() || saveButton.disabled){
			event.preventDefault();
			globalError.textContent = 'Fix highlighted identifier issues before saving.';
			globalError.classList.remove('d-none');
		}else{
			globalError.classList.add('d-none');
			globalError.textContent = '';
		}
	});

	// Seed with one entry (either from old value JSON or blank)
	const initial = (() => {
		try {
			return JSON.parse(entriesInput.value || '[]');
		} catch (error) {
			return [];
		}
	})();
	if(initial.length){
		initial.forEach(entry => appendEntry(entry));
	}else{
		appendEntry();
	}
});
</script>

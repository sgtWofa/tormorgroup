<?php
/**
 * Currency Denomination Exchange Modal View
 */
if (empty($row)) {
	return;
}
?>

<div class="modal fade" id="exchangeDenoModal" tabindex="-1" aria-labelledby="exchangeDenoLabel" aria-hidden="true">
	<div class="modal-dialog modal-xl">
		<div class="modal-content">
			<div class="modal-header bg-primary text-white">
				<h5 class="modal-title" id="exchangeDenoLabel">
					<i class="fa fa-exchange-alt"></i> Exchange denominations
				</h5>
				<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<form id="exchangeForm" method="POST" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/exchange_denominations/<?=$row->id?>" novalidate>
				<div class="modal-body">
					<?=csrf_field()?>
					<input type="hidden" name="drawer_id" value="<?=$row->id?>">

					<div class="row g-3 align-items-stretch">
						<div class="col-lg-4">
							<div class="card h-100 border-0 shadow-sm">
								<div class="card-body">
									<h6 class="text-uppercase text-muted">Drawer snapshot</h6>
									<div class="small text-muted mb-2">Available denominations</div>
									<div id="availableDenos" class="bg-body-secondary rounded p-3 small" style="min-height:120px; overflow-y:auto;">
										<span class="text-muted">Loading…</span>
									</div>
								</div>
							</div>
						</div>
						<div class="col-lg-8">
							<div class="card border-0 shadow-sm h-100">
								<div class="card-body">
									<div class="row g-3">
										<div class="col-md-6">
											<label class="form-label fw-semibold">Cash leaving drawer (Given out)</label>
											<div id="givenRows" class="deno-wrapper">
												<div class="deno-row row g-2 align-items-end">
													<div class="col-6">
														<label class="form-label">Denomination (GHS)</label>
														<input type="number" step="0.01" min="0.01" class="form-control deno-value" placeholder="50.00">
													</div>
													<div class="col-4">
														<label class="form-label">Count</label>
														<input type="number" min="1" step="1" class="form-control deno-count" placeholder="1">
													</div>
													<div class="col-2 text-end">
														<button type="button" class="btn btn-sm btn-outline-danger remove-row" style="display:none;">
															<i class="fa fa-xmark"></i>
														</button>
													</div>
												</div>
											</div>
											<button type="button" class="btn btn-sm btn-outline-primary mt-2" id="addGivenRow">
												<i class="fa fa-plus"></i> Add row
											</button>
										</div>
										<div class="col-md-6">
											<label class="form-label fw-semibold">Cash received (Replacement)</label>
											<div id="receivedRows" class="deno-wrapper">
												<div class="deno-row row g-2 align-items-end">
													<div class="col-6">
														<label class="form-label">Denomination (GHS)</label>
														<input type="number" step="0.01" min="0.01" class="form-control deno-value" placeholder="20.00">
													</div>
													<div class="col-4">
														<label class="form-label">Count</label>
														<input type="number" min="1" step="1" class="form-control deno-count" placeholder="2">
													</div>
													<div class="col-2 text-end">
														<button type="button" class="btn btn-sm btn-outline-danger remove-row" style="display:none;">
															<i class="fa fa-xmark"></i>
														</button>
													</div>
												</div>
											</div>
											<button type="button" class="btn btn-sm btn-outline-primary mt-2" id="addReceivedRow">
												<i class="fa fa-plus"></i> Add row
											</button>
										</div>
									</div>

									<div class="row g-3 mt-3">
										<div class="col-md-6">
											<label class="form-label">Given total (auto)</label>
											<div class="form-control bg-body-secondary" readonly id="givenTotal">GHS 0.00</div>
										</div>
										<div class="col-md-6">
											<label class="form-label">Received total (auto)</label>
											<div class="form-control bg-body-secondary" readonly id="receivedTotal">GHS 0.00</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<div class="text-start me-auto small text-muted" id="exchangeHelper"></div>
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary" id="submitExchange">
						<i class="fa fa-check"></i> Confirm exchange
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
(function(){
	const modal = document.getElementById('exchangeDenoModal');
	if(!modal) return;

	const form = modal.querySelector('#exchangeForm');
	const givenContainer = modal.querySelector('#givenRows');
	const receivedContainer = modal.querySelector('#receivedRows');
	const addGivenBtn = modal.querySelector('#addGivenRow');
	const addReceivedBtn = modal.querySelector('#addReceivedRow');
	const givenTotalEl = modal.querySelector('#givenTotal');
	const receivedTotalEl = modal.querySelector('#receivedTotal');
	const helperEl = modal.querySelector('#exchangeHelper');
	const availableBox = modal.querySelector('#availableDenos');

	const setHelper = (message, state = 'muted') => {
		if(!helperEl) return;
		helperEl.textContent = message;
		helperEl.classList.remove('text-danger','text-success','text-muted');
		helperEl.classList.add('text-' + state);
	};

	const cloneRow = (template) => {
		const newRow = template.cloneNode(true);
		newRow.querySelectorAll('input').forEach(input => input.value = '');
		attachRowListeners(newRow);
		return newRow;
	};

	const updateDeleteButtons = (container) => {
		const rows = container.querySelectorAll('.deno-row');
		rows.forEach(row => {
			const removeBtn = row.querySelector('.remove-row');
			if(removeBtn){
				removeBtn.style.display = rows.length > 1 ? 'inline-flex' : 'none';
			}
		});
	};

	const calculateTotals = () => {
		const sumRows = (container) => {
			let total = 0;
			container.querySelectorAll('.deno-row').forEach(row => {
				const value = parseFloat(row.querySelector('.deno-value').value) || 0;
				const count = parseInt(row.querySelector('.deno-count').value) || 0;
				total += value * count;
			});
			return total;
		};

		const givenTotal = sumRows(givenContainer);
		const receivedTotal = sumRows(receivedContainer);

		givenTotalEl.textContent = 'GHS ' + givenTotal.toFixed(2);
		receivedTotalEl.textContent = 'GHS ' + receivedTotal.toFixed(2);

		const diff = givenTotal - receivedTotal;
		if(helperEl){
			if(givenTotal === 0 && receivedTotal === 0){
				setHelper('Enter denominations on both sides.', 'muted');
			}else if(Math.abs(diff) < 0.01){
				setHelper('Totals balanced', 'success');
			}else{
				setHelper('Totals must match before submission', 'danger');
			}
		}

		return { givenTotal, receivedTotal };
	};

	const attachRowListeners = (row) => {
		if(!row) return;
		const removeBtn = row.querySelector('.remove-row');
		if(removeBtn){
			removeBtn.addEventListener('click', () => {
				row.remove();
				const wrapper = row.closest('.deno-wrapper');
				if(wrapper){
					updateDeleteButtons(wrapper);
				}
				calculateTotals();
			});
		}
		row.querySelectorAll('input').forEach(input => {
			input.addEventListener('input', calculateTotals);
		});
	};

	const serializeRows = (container) => {
		const payload = [];
		container.querySelectorAll('.deno-row').forEach(row => {
			const value = parseFloat(row.querySelector('.deno-value').value);
			const count = parseInt(row.querySelector('.deno-count').value);
			if(value > 0 && count > 0){
				payload.push({
					denomination: parseFloat(value.toFixed(2)),
					count: count
				});
			}
		});
		return payload;
	};

	const ensureHiddenInput = (name) => {
		let input = form.querySelector(`[name="${name}"]`);
		if(!input){
			input = document.createElement('input');
			input.type = 'hidden';
			input.name = name;
			form.appendChild(input);
		}
		return input;
	};

	[givenContainer, receivedContainer].forEach(container => {
		const row = container.querySelector('.deno-row');
		attachRowListeners(row);
		updateDeleteButtons(container);
	});

	addGivenBtn.addEventListener('click', (e) => {
		e.preventDefault();
		const template = givenContainer.querySelector('.deno-row');
		if(!template) return;
		const newRow = cloneRow(template);
		givenContainer.appendChild(newRow);
		updateDeleteButtons(givenContainer);
		calculateTotals();
	});

	addReceivedBtn.addEventListener('click', (e) => {
		e.preventDefault();
		const template = receivedContainer.querySelector('.deno-row');
		if(!template) return;
		const newRow = cloneRow(template);
		receivedContainer.appendChild(newRow);
		updateDeleteButtons(receivedContainer);
		calculateTotals();
	});

	form.addEventListener('submit', (e) => {
		const givenPayload = serializeRows(givenContainer);
		const receivedPayload = serializeRows(receivedContainer);
		const totals = calculateTotals();
		const diff = Math.abs(totals.givenTotal - totals.receivedTotal);

		if(!givenPayload.length || !receivedPayload.length){
			e.preventDefault();
			setHelper('Provide at least one denomination on both sides before submitting.', 'danger');
			return;
		}

		if(diff >= 0.01){
			e.preventDefault();
			setHelper('Totals must match before submission.', 'danger');
			return;
		}

		const givenInput = ensureHiddenInput('given_denominations');
		givenInput.value = JSON.stringify(givenPayload);

		const receivedInput = ensureHiddenInput('received_denominations');
		receivedInput.value = JSON.stringify(receivedPayload);
	});

	const drawerId = '<?=$row->id?>';
	let snapshotLoaded = false;

	const renderAvailableDenos = (list) => {
		if(!availableBox) return;
		if(!list.length){
			availableBox.innerHTML = '<span class="text-muted">No denominations recorded for this drawer yet.</span>';
			return;
		}
		availableBox.innerHTML = list.map(item => {
			const value = parseFloat(item.denomination).toFixed(2);
			const count = parseInt(item.total_count ?? item.count, 10);
			const volume = item.total_volume ?? (parseFloat(item.denomination) * count);
			return `<div class="d-flex justify-content-between align-items-center py-1 border-bottom">
				<span>GHS ${value}</span>
				<span class="fw-semibold">${count} pcs <small class="text-muted">(GHS ${parseFloat(volume).toFixed(2)})</small></span>
			</div>`;
		}).join('');
	};

	const loadDrawerSnapshot = () => {
		if(!availableBox) return;
		availableBox.innerHTML = '<span class="text-muted">Loading…</span>';
		fetch('<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/get_drawer_denominations/<?=$row->id?>', {
			headers: { 'Accept': 'application/json' }
		})
		.then(response => response.json())
		.then(data => {
			if(data.success && Array.isArray(data.denominations)){
				renderAvailableDenos(data.denominations);
			}else{
				availableBox.innerHTML = '<span class="text-danger">Unable to load denominations.</span>';
			}
		})
		.catch(() => {
			availableBox.innerHTML = '<span class="text-danger">Failed to load drawer snapshot.</span>';
		});
	};

	modal.addEventListener('show.bs.modal', () => {
		if(snapshotLoaded) return;
		snapshotLoaded = true;
		loadDrawerSnapshot();
	});

	calculateTotals();
	setHelper('Enter denominations on both sides.', 'muted');
})();
</script>

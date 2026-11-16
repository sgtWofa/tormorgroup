(function () {
	'use strict';

	function getTodayInAccra() {
		const parts = new Intl.DateTimeFormat('en', {
			timeZone: 'Africa/Accra',
			year: 'numeric',
			month: '2-digit',
			day: '2-digit',
		}).formatToParts(new Date());
		const values = Object.fromEntries(parts.map(part => [part.type, part.value]));
		return `${values.year}-${values.month}-${values.day}`;
	}

	function isCreatedToday(value, today = getTodayInAccra()) {
		const createdDate = String(value || '').trim().slice(0, 10);
		return createdDate !== '' && createdDate === today;
	}

	function hideOlderCancelButtons() {
		const today = getTodayInAccra();
		document.querySelectorAll('.js-direct-cancel').forEach(button => {
			if (!isCreatedToday(button.dataset.cancelDate, today)) {
				button.remove();
			}
		});
	}

	hideOlderCancelButtons();
	if (document.body) {
		new MutationObserver(hideOlderCancelButtons).observe(document.body, { childList: true, subtree: true });
		window.setInterval(hideOlderCancelButtons, 60_000);
	}

	const csrfField = document.querySelector('[data-direct-cancel-csrf] input[name="csrf"]');
	if (!csrfField) {
		return;
	}

	const modalMarkup = `
		<div class="modal fade" id="directCancelModal" tabindex="-1" aria-labelledby="directCancelTitle" aria-describedby="directCancelDescription" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content border-0 shadow-lg">
					<div class="modal-header border-0 pb-0">
						<div class="d-flex align-items-center gap-3">
							<span class="direct-cancel-icon" aria-hidden="true"><i class="fa-solid fa-arrow-rotate-left"></i></span>
							<div>
								<h5 class="modal-title mb-1" id="directCancelTitle">Cancel transaction</h5>
								<div class="small text-muted" data-cancel-reference></div>
							</div>
						</div>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body pt-3">
						<div class="direct-cancel-warning d-flex gap-3 align-items-start" id="directCancelDescription">
							<i class="fa-solid fa-triangle-exclamation mt-1" aria-hidden="true"></i>
							<div>
								<strong>This action cannot be undone.</strong>
								<p class="mb-0 mt-1 small">Recorded cashdrawer, e-cash, denomination, commission, and linked transaction effects will be reversed where applicable. The cancellation will be recorded against your account.</p>
							</div>
						</div>
						<div class="small text-danger mt-3 d-none" role="alert" data-cancel-error></div>
					</div>
					<div class="modal-footer border-0 pt-0">
						<button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Keep transaction</button>
						<button type="button" class="btn btn-danger rounded-pill px-3" data-cancel-confirm>
							<i class="fa-solid fa-ban me-1" aria-hidden="true"></i> Cancel transaction
						</button>
					</div>
				</div>
			</div>
		</div>`;

	const style = document.createElement('style');
	style.textContent = `
		#directCancelModal .modal-content { border-radius: 1.15rem; }
		#directCancelModal .direct-cancel-icon {
			width: 2.75rem; height: 2.75rem; border-radius: 50%;
			display: inline-flex; align-items: center; justify-content: center;
			color: #b42318; background: rgba(220, 53, 69, .1); font-size: 1.1rem;
		}
		#directCancelModal .direct-cancel-warning {
			padding: 1rem; border: 1px solid rgba(220, 53, 69, .2);
			border-radius: .85rem; background: rgba(220, 53, 69, .045);
		}
		#directCancelModal .direct-cancel-warning > i { color: #b42318; }
		.js-direct-cancel { white-space: nowrap; }
	`;
	document.head.appendChild(style);
	document.body.insertAdjacentHTML('beforeend', modalMarkup);

	const modal = document.getElementById('directCancelModal');
	const confirmButton = modal.querySelector('[data-cancel-confirm]');
	const reference = modal.querySelector('[data-cancel-reference]');
	const errorBox = modal.querySelector('[data-cancel-error]');
	let selectedButton = null;
	let modalInstance = null;

	document.addEventListener('click', function (event) {
		const button = event.target.closest('.js-direct-cancel');
		if (!button) {
			return;
		}
		if (!isCreatedToday(button.dataset.cancelDate)) {
			button.remove();
			return;
		}
		event.preventDefault();
		selectedButton = button;
		reference.textContent = button.dataset.cancelLabel || `Transaction #${button.dataset.id}`;
		errorBox.textContent = '';
		errorBox.classList.add('d-none');
		if (!modalInstance && window.bootstrap && window.bootstrap.Modal) {
			modalInstance = window.bootstrap.Modal.getOrCreateInstance(modal);
		}
		if (modalInstance) {
			modalInstance.show();
			return;
		}
		const existingError = button.parentElement.querySelector('[data-cancel-dialog-error]');
		if (existingError) {
			existingError.remove();
		}
		const dialogError = document.createElement('div');
		dialogError.className = 'alert alert-danger py-1 px-2 mt-2 small';
		dialogError.setAttribute('role', 'alert');
		dialogError.dataset.cancelDialogError = '';
		dialogError.textContent = 'Cancellation dialog is unavailable. Refresh the page and try again.';
		button.insertAdjacentElement('afterend', dialogError);
		console.error('Bootstrap Modal is unavailable; transaction cancellation was not started.');
	});

	confirmButton.addEventListener('click', cancelTransaction);
	modal.addEventListener('shown.bs.modal', function () {
		confirmButton.focus();
	});

	async function cancelTransaction() {
		if (!selectedButton || confirmButton.disabled) {
			return;
		}
		const originalContent = confirmButton.innerHTML;
		confirmButton.disabled = true;
		confirmButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Reversing...';
		errorBox.textContent = '';
		errorBox.classList.add('d-none');

		try {
			const body = new URLSearchParams({
				csrf: csrfField.value,
				id: selectedButton.dataset.id || '',
			});
			const response = await fetch(selectedButton.dataset.cancelEndpoint, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
					'X-Requested-With': 'XMLHttpRequest',
				},
				body: body.toString(),
			});
			const payload = await response.json();
			if (!response.ok || !payload.success) {
				throw new Error(payload.message || 'The transaction could not be cancelled.');
			}

			confirmButton.innerHTML = '<i class="fa-solid fa-check me-1" aria-hidden="true"></i>Cancelled';
			window.setTimeout(function () {
				if (modalInstance) {
					modalInstance.hide();
				}
				window.location.reload();
			}, 700);
		} catch (error) {
			errorBox.textContent = error.message || 'The transaction could not be cancelled. Please try again.';
			errorBox.classList.remove('d-none');
			confirmButton.disabled = false;
			confirmButton.innerHTML = originalContent;
		}
	}
})();

<style>
	.cashpickup-cancel-modal .modal-content{border:0;border-radius:1.15rem;overflow:hidden;box-shadow:0 1.5rem 4rem rgba(15,23,42,.22)}
	.cashpickup-cancel-modal .modal-header{padding:1.35rem 1.5rem;background:linear-gradient(125deg,#fff1f2,#fff 68%)}
	.cashpickup-cancel-modal .cancel-icon{width:2.75rem;height:2.75rem;display:grid;place-items:center;border-radius:.9rem;background:#ffe4e6;color:#be123c;font-size:1.15rem}
	.cashpickup-cancel-modal .modal-body{padding:1.35rem 1.5rem}
	.cashpickup-cancel-modal .form-control{border-color:#cbd5e1;border-radius:.75rem}
	.cashpickup-cancel-modal .form-control:focus{border-color:#fb7185;box-shadow:0 0 0 .22rem rgba(244,63,94,.12)}
	.cashpickup-cancel-modal .cancel-notice{border:1px solid #fed7aa;border-radius:.75rem;background:#fff7ed;color:#9a3412}
</style>
<div class="modal fade cashpickup-cancel-modal" id="cashpickupCancelModal" tabindex="-1" aria-labelledby="cashpickupCancelTitle" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header border-0">
				<div class="d-flex align-items-center gap-3">
					<div class="cancel-icon"><i class="fa fa-ban" aria-hidden="true"></i></div>
					<div>
						<h5 class="modal-title fw-bold mb-1" id="cashpickupCancelTitle">Cancel transaction</h5>
						<div class="small text-secondary">Transaction <span class="fw-semibold" id="cashpickupCancelTransactionId">—</span></div>
					</div>
				</div>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<form method="post" id="cashpickupCancelForm">
				<div class="modal-body pt-0">
					<?=csrf()?>
					<div class="cancel-notice p-3 small mb-3">
						<i class="fa fa-circle-info me-1" aria-hidden="true"></i>
						This action will cancel the transaction. Approved cashdrawer movements will be reversed.
					</div>
					<label class="form-label fw-semibold" for="cashpickupCancellationReason">Reason for cancellation <span class="text-danger">*</span></label>
					<textarea class="form-control" id="cashpickupCancellationReason" name="cancellation_reason" rows="3" maxlength="500" required placeholder="Explain why this transaction needs to be cancelled"></textarea>
					<div class="d-flex justify-content-between mt-2">
						<div class="form-text">Required for the audit record.</div>
						<div class="form-text"><span id="cashpickupCancelReasonCount">0</span>/500</div>
					</div>
				</div>
				<div class="modal-footer border-0 pt-0 px-4 pb-4">
					<button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Keep transaction</button>
					<button type="submit" class="btn btn-danger px-4"><i class="fa fa-ban me-1" aria-hidden="true"></i> Confirm cancellation</button>
				</div>
			</form>
		</div>
	</div>
</div>
<script>
	(function(){
		const modalEl = document.getElementById('cashpickupCancelModal');
		const formEl = document.getElementById('cashpickupCancelForm');
		const reasonEl = document.getElementById('cashpickupCancellationReason');
		const countEl = document.getElementById('cashpickupCancelReasonCount');
		const transactionEl = document.getElementById('cashpickupCancelTransactionId');
		if(!modalEl || !formEl) return;

		document.addEventListener('click', function(event){
			const trigger = event.target && event.target.closest ? event.target.closest('[data-cashpickup-cancel]') : null;
			if(!trigger || !window.bootstrap || !bootstrap.Modal) return;
			event.preventDefault();
			formEl.action = trigger.getAttribute('data-cancel-url') || '';
			if(transactionEl) transactionEl.textContent = '#' + (trigger.getAttribute('data-transaction-id') || '');
			if(reasonEl) reasonEl.value = '';
			if(countEl) countEl.textContent = '0';
			bootstrap.Modal.getOrCreateInstance(modalEl).show();
		});

		if(reasonEl && countEl){
			reasonEl.addEventListener('input', function(){ countEl.textContent = String(reasonEl.value.length); });
			modalEl.addEventListener('shown.bs.modal', function(){ reasonEl.focus(); });
		}
	})();
</script>

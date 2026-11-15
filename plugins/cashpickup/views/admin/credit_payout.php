<?php if(!user_can('view_cashpickup')): ?>
	<div class="alert alert-danger text-center">Access denied.</div>
<?php else: ?>

<?php
	require_once plugin_path('helpers/customer-ledger.php');
	$db = new \Core\Database;
	\CashpickupLedger\ensure_cashpickup_customer_schema($db);
	\CashpickupLedger\ensure_cashpickup_columns($db);
	\CashpickupLedger\ensure_cashpickup_credit_schema($db);

	$customerId = (int)(URL(3) ?? old_value('customer_id') ?? 0);
	$customerRows = $customerId > 0
		? ($db->query("SELECT * FROM cashpickup_customers WHERE deleted = 0 AND id = :id LIMIT 1", ['id' => $customerId]) ?: [])
		: [];
	$customer = !empty($customerRows) ? $customerRows[0] : null;

	$eligibleAgents = [];
	$regularBalance = 0.0;
	$creditMetrics = [
		'total_credit_given' => 0.0,
		'total_credit_paid' => 0.0,
		'total_amount_in_credit' => 0.0,
	];

	if (!empty($customer)) {
		try {
			$paidOutExpr = "(CASE
				WHEN LOWER(transaction_side) = 'pay'
					AND " . \CashpickupLedger\normalized_cashpickup_credit_mode_sql() . " <> 'credit'
					AND LOWER(COALESCE(transaction_status,'')) = 'approved'
					AND (confirmed_by IS NOT NULL AND confirmed_by != '')
					AND COALESCE(amount_paid_out,0) > 0
					AND (
						approval_status IS NULL
						OR approval_status = ''
						OR LOWER(approval_status) IN ('approved','pending')
					)
				THEN (COALESCE(amount_paid_out,0) + COALESCE(pickup_charge,0))
				ELSE 0
			END)";
			$phone = trim((string)($customer->phone_number ?? ''));
			$idNumber = trim((string)($customer->id_number ?? ''));
			$params = ['cid' => $customerId];
			$matchParts = ['customer_id = :cid'];
			if ($phone !== '') {
				$matchParts[] = 'phone_number = :phone';
				$params['phone'] = $phone;
			}
			if ($idNumber !== '') {
				$matchParts[] = 'customer_id_number = :id_number';
				$params['id_number'] = $idNumber;
			}
			$summary = $db->query(
				"SELECT
					COALESCE(SUM(CASE WHEN LOWER(transaction_side) = 'receive' AND " . \CashpickupLedger\normalized_cashpickup_credit_mode_sql() . " <> 'credit' THEN amount_involved ELSE 0 END),0) AS total_received,
					COALESCE(SUM({$paidOutExpr}),0) AS total_paid
				 FROM cashpickups
				 WHERE deleted = 0
				 AND (" . implode(' OR ', $matchParts) . ')',
				$params
			) ?: [];
			$regularBalance = (float)($summary[0]->total_received ?? 0) - (float)($summary[0]->total_paid ?? 0);
		} catch (\Throwable $e) {
			$regularBalance = 0.0;
		}

		try {
			$creditMetrics = \CashpickupLedger\fetch_customer_credit_metrics($db, $customerId);
			$regularBalance -= (float)($creditMetrics['total_credit_given'] ?? 0);
		} catch (\Throwable $e) {
			$creditMetrics = [
				'total_credit_given' => 0.0,
				'total_credit_paid' => 0.0,
				'total_amount_in_credit' => 0.0,
			];
		}

		try {
			$session = get_session();
			$eligibleAgents = $db->query(
				"SELECT u.id, u.first_name, u.last_name
				 FROM users u
				 JOIN (
					SELECT DISTINCT a.user_id
					FROM attendance a
					WHERE a.deleted = 0
					AND a.date = CURDATE()
					AND UPPER(a.remarks) IN ('PRESENT','LATE')
				 ) present_today ON present_today.user_id = u.id
				 LEFT JOIN (
					SELECT DISTINCT urm.user_id
					FROM user_roles_map urm
					JOIN user_roles ur ON ur.id = urm.role_id
					WHERE urm.disabled = 0
					AND LOWER(ur.role) IN (
						'manager','admin','is_manager','is_admin',
						'general manager','general_manager','generalmanager','gm','is_general_manager'
					)
				 ) blocked_roles ON blocked_roles.user_id = u.id
				 WHERE u.deleted = 0
				 AND u.id != :actor_id
				 AND blocked_roles.user_id IS NULL
				 ORDER BY u.first_name ASC, u.last_name ASC",
				['actor_id' => (int)($session->id ?? 0)]
			) ?: [];
		} catch (\Throwable $e) {
			$eligibleAgents = [];
		}
	}
?>

<?php if (empty($customer)): ?>
	<div class="alert alert-warning text-center">Customer not found.</div>
<?php else: ?>
	<div class="d-flex align-items-center justify-content-between mb-3">
		<div>
			<h4 class="mb-0">Credit Payout</h4>
			<small class="text-muted">Assign a credit payout for this customer</small>
		</div>
		<a class="btn btn-outline-secondary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer/<?=esc((string)$customerId)?>">Back to Customer</a>
	</div>

	<div class="row g-2 mb-3">
		<div class="col-12 col-md-3">
			<div class="border rounded p-3 h-100">
				<div class="text-muted">Cashpickup Balance</div>
				<div class="fw-bold">GH&#8373; <?=number_format((float)$regularBalance, 2)?></div>
			</div>
		</div>
		<div class="col-12 col-md-3">
			<div class="border rounded p-3 h-100">
				<div class="text-muted">Total Credit Given</div>
				<div class="fw-bold">GH&#8373; <?=number_format((float)($creditMetrics['total_credit_given'] ?? 0), 2)?></div>
			</div>
		</div>
		<div class="col-12 col-md-3">
			<div class="border rounded p-3 h-100">
				<div class="text-muted">Total Credit Paid</div>
				<div class="fw-bold">GH&#8373; <?=number_format((float)($creditMetrics['total_credit_paid'] ?? 0), 2)?></div>
			</div>
		</div>
		<div class="col-12 col-md-3">
			<div class="border rounded p-3 h-100">
				<div class="text-muted">Amount in Credit</div>
				<div class="fw-bold">GH&#8373; <?=number_format((float)($creditMetrics['total_amount_in_credit'] ?? 0), 2)?></div>
			</div>
		</div>
	</div>

	<div class="card border-0 shadow-sm">
		<div class="card-body">
			<form method="post" class="row g-3">
				<?=csrf()?>
				<input type="hidden" name="customer_id" value="<?=esc((string)$customerId)?>">

				<div class="col-12 col-md-6">
					<label class="form-label">Customer</label>
					<input type="text" class="form-control" value="<?=esc((string)($customer->full_name ?? ''))?>" readonly>
				</div>
				<div class="col-12 col-md-3">
					<label class="form-label">Phone</label>
					<input type="text" class="form-control" value="<?=esc((string)($customer->phone_number ?? ''))?>" readonly>
				</div>
				<div class="col-12 col-md-3">
					<label class="form-label">ID Number</label>
					<input type="text" class="form-control" value="<?=esc((string)($customer->id_number ?? ''))?>" readonly>
				</div>

				<div class="col-12 col-md-6">
					<label class="form-label">Assign to</label>
					<select name="assign_to_user_id" class="form-select">
						<option value="">---Select---</option>
						<?php foreach($eligibleAgents as $agent): ?>
							<?php $agentName = trim(((string)($agent->first_name ?? '')) . ' ' . ((string)($agent->last_name ?? ''))); ?>
							<option value="<?=esc((string)($agent->id ?? 0))?>" <?=((string)old_value('assign_to_user_id') === (string)($agent->id ?? 0)) ? 'selected' : ''?>>
								<?=esc($agentName !== '' ? $agentName : ('User #' . (string)($agent->id ?? 0)))?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php if(!empty($errors['assign_to_user_id'])): ?>
						<small class="text-danger"><?=$errors['assign_to_user_id']?></small>
					<?php endif; ?>
				</div>

				<div class="col-12 col-md-3">
					<label class="form-label">Credit amount</label>
					<div class="input-group">
						<div class="input-group-text">GH&#8373;</div>
						<input name="credit_amount" id="creditAmount" type="number" step="0.01" min="0" class="form-control" value="<?=esc((string)old_value('credit_amount'))?>" placeholder="0.00">
					</div>
					<?php if(!empty($errors['credit_amount'])): ?>
						<small class="text-danger"><?=$errors['credit_amount']?></small>
					<?php endif; ?>
				</div>

				<div class="col-12 col-md-3">
					<label class="form-label">Pick-up charge</label>
					<div class="input-group">
						<div class="input-group-text">GH&#8373;</div>
						<input name="pickup_charge" id="creditCharge" type="number" step="0.01" min="0" class="form-control" value="<?=esc((string)(old_value('pickup_charge') !== '' ? old_value('pickup_charge') : '0.00'))?>" placeholder="0.00">
					</div>
					<?php if(!empty($errors['pickup_charge'])): ?>
						<small class="text-danger"><?=$errors['pickup_charge']?></small>
					<?php endif; ?>
				</div>

				<div class="col-12 col-md-6">
					<label class="form-label">Notes</label>
					<input name="notes" type="text" class="form-control" value="<?=esc((string)old_value('notes'))?>" placeholder="Optional notes">
				</div>

				<div class="col-12 col-md-3">
					<label class="form-label">Total authorized</label>
					<div class="form-control disabled" id="creditAuthorizedTotal">GH&#8373; 0.00</div>
				</div>

				<div class="col-12 d-flex justify-content-between">
					<a class="btn btn-outline-secondary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer/<?=esc((string)$customerId)?>">
						<i class="fa-solid fa-chevron-left"></i> Back
					</a>
					<button class="btn btn-primary" type="submit">
						<i class="fa-solid fa-save"></i> Assign Credit Payout
					</button>
				</div>
			</form>
		</div>
	</div>

	<script>
		(function(){
			const amountEl = document.getElementById('creditAmount');
			const chargeEl = document.getElementById('creditCharge');
			const totalEl = document.getElementById('creditAuthorizedTotal');
			const toNumber = (value) => {
				const n = parseFloat((value ?? '').toString());
				return Number.isFinite(n) ? n : 0;
			};
			const syncTotal = () => {
				if(!totalEl) return;
				const total = toNumber(amountEl && amountEl.value) + Math.max(0, toNumber(chargeEl && chargeEl.value));
				totalEl.textContent = 'GH\u20B5 ' + total.toFixed(2);
			};
			amountEl && amountEl.addEventListener('input', syncTotal);
			chargeEl && chargeEl.addEventListener('input', syncTotal);
			syncTotal();
		})();
	</script>
<?php endif; ?>

<?php endif; ?>

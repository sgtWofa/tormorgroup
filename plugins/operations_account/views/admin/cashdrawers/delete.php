<?php if(user_can('delete_wallet')): ?>
	<style>
		.cashdrawer-delete-shell {
			max-width: 1180px;
			margin: 32px auto;
		}
		.cashdrawer-delete-card {
			border: 0.4px dashed rgba(0,0,0,0.08);
			border-radius: 18px;
			box-shadow: 0 18px 50px rgba(15, 23, 42, 0.08);
			padding: 28px;
			margin-bottom: 20px;
		}
		.cashdrawer-delete-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
			gap: 14px;
		}
		.cashdrawer-delete-metric {
			border: 1px solid rgba(0,0,0,0.08);
			border-radius: 14px;
			padding: 16px;
			background: #eaeaea20;
		}
		.cashdrawer-delete-label {
			font-size: 12px;
			text-transform: uppercase;
			letter-spacing: 0.08em;
			color: #64748b;
			margin-bottom: 6px;
		}
		.cashdrawer-delete-value {
			font-size: 20px;
			font-weight: 700;
		}
		.cashdrawer-delete-record {
			border: 1px solid rgba(0,0,0,0.08);
			border-radius: 14px;
			padding: 3px;
			background: #eaeaea25;
		}
		.cashdrawer-delete-record + .cashdrawer-delete-record {
			margin-top: 12px;
		}
		.cashdrawer-delete-record-head {
			background: #eaeaea15;
			display: flex;
			justify-content: space-between;
			gap: 16px;
			flex-wrap: wrap;
			align-items: center;
			padding: 0 10px;
		}
		.cashdrawer-delete-table {
			width: 100%;
			border-collapse: collapse;
		}
		.cashdrawer-delete-table th,
		.cashdrawer-delete-table td {
			padding: 10px 12px;
			border-bottom: 1px solid rgba(0,0,0,0.06);
			font-size: 14px;
			vertical-align: top;
		}
		.cashdrawer-delete-table th {
			font-weight: 700;
			background: #eaeaea15;
		}
		.cashdrawer-delete-actions {
			display: flex;
			justify-content: space-between;
			gap: 12px;
			flex-wrap: wrap;
			margin-top: 24px;
		}
	</style>

	<?php if(!empty($row)): ?>
		<?php
			$cashdrawer_issue_report = $cashdrawer_issue_report ?? ['issue_count' => 0, 'issues' => []];
			$cashdrawer_dependencies = $cashdrawer_dependencies ?? ['direct_records' => [], 'ledger_refs' => []];
			$directRecords = $cashdrawer_dependencies['direct_records'] ?? [];
			$ledgerRefs = $cashdrawer_dependencies['ledger_refs'] ?? [];
			$issueCount = (int)($cashdrawer_issue_report['issue_count'] ?? 0);
			$openingDenoTotal = (float)($cashdrawer_issue_report['opening_deno_total'] ?? 0);
			$drawerAgentName = trim(($row->user_first_name ?? '').' '.($row->user_last_name ?? ''));
			if($drawerAgentName === ''){
				$drawerAgentName = trim(($row->assigned_first_name ?? '').' '.($row->assigned_last_name ?? ''));
			}
			if($drawerAgentName === ''){
				$drawerAgentName = 'User #'.($row->user_id ?? '');
			}
		?>

		<form method="post" class="cashdrawer-delete-shell" id="cashdrawerDeleteForm">
			<?=csrf()?>

			<div class="cashdrawer-delete-card">
				<div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
					<div>
						<h3 class="mb-1">Delete Cashdrawer</h3>
						<p class="text-muted mb-0">Review the inconsistencies and linked records first. Checked records will be deleted with the cashdrawer. Unchecked linked records will be detached from this cashdrawer so they are not left pointing to a deleted drawer.</p>
					</div>
					<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2">Admin Repair Action</span>
				</div>

				<div class="cashdrawer-delete-grid">
					<div class="cashdrawer-delete-metric">
						<div class="cashdrawer-delete-label">Cashdrawer</div>
						<div class="cashdrawer-delete-value">#<?=esc($row->id)?></div>
					</div>
					<div class="cashdrawer-delete-metric">
						<div class="cashdrawer-delete-label">Agent</div>
						<div class="cashdrawer-delete-value"><?=esc($drawerAgentName)?></div>
					</div>
					<div class="cashdrawer-delete-metric">
						<div class="cashdrawer-delete-label">Header Opening Cash</div>
						<div class="cashdrawer-delete-value">GHS <?=number_format((float)($row->amount ?? 0), 2)?></div>
					</div>
					<div class="cashdrawer-delete-metric">
						<div class="cashdrawer-delete-label">Opening Deno Total</div>
						<div class="cashdrawer-delete-value">GHS <?=number_format($openingDenoTotal, 2)?></div>
					</div>
					<div class="cashdrawer-delete-metric">
						<div class="cashdrawer-delete-label">Detected Issues</div>
						<div class="cashdrawer-delete-value"><?=$issueCount?></div>
					</div>
					<div class="cashdrawer-delete-metric">
						<div class="cashdrawer-delete-label">Date Created</div>
						<div class="cashdrawer-delete-value" style="font-size:16px;"><?=get_date($row->date_created ?? '')?></div>
					</div>
				</div>
			</div>

			<div class="cashdrawer-delete-card">
				<h5 class="mb-3">Consistency Review</h5>
				<?php if($issueCount > 0): ?>
					<div class="alert alert-warning mb-0">
						<ul class="mb-0 ps-3">
							<?php foreach(($cashdrawer_issue_report['issues'] ?? []) as $issue): ?>
								<li class="mb-2">
									<strong><?=esc($issue['label'] ?? 'Issue detected')?></strong><br>
									<span><?=esc($issue['details'] ?? '')?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php else: ?>
					<div class="alert alert-success mb-0">No structural inconsistency was detected in the opening denominations or channel balances. You can still delete this drawer if you need to rebuild it.</div>
				<?php endif; ?>
			</div>

			<div class="cashdrawer-delete-card">
				<div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
					<div>
						<h5 class="mb-1">Directly Linked Records</h5>
						<p class="text-muted mb-0">These records point to this cashdrawer.</p>
						<small class="text-muted small">Unchecked ledger-linked records stay in place; deleting the drawer removes only their ledger rows.</small>
					</div>
					<span class="badge bg-warning text-dark"><?=count($directRecords)?> linked record(s)</span>
				</div>

				<?php if(!empty($directRecords)): ?>
					<?php foreach($directRecords as $record): ?>
						<?php
							$deleteDefault = !array_key_exists('delete_default', $record) || !empty($record['delete_default']);
							$canDetach = !array_key_exists('can_detach', $record) || !empty($record['can_detach']);
						?>
						<div class="cashdrawer-delete-record">
							<table class="table table-sm">
								<tr>
									<td colspan="3">
										<div class="cashdrawer-delete-record-head">
											<div>
												<div class="fw-bold"><?=esc($record['label'] ?? 'Linked Record')?> #<?=esc($record['id'] ?? '')?></div>
												<div class="text-muted small"><?=esc($record['table'] ?? '')?> via <?=esc($record['ref_column'] ?? '')?></div>
											</div>
											<div class="form-check m-0">
												<input class="form-check-input" type="checkbox" name="delete_linked[]" value="<?=esc($record['key'] ?? '')?>" id="delete_<?=md5((string)($record['key'] ?? ''))?>" <?=$deleteDefault ? 'checked' : ''?>>
												<label class="form-check-label" for="delete_<?=md5((string)($record['key'] ?? ''))?>"><span class="Delete this record too">Delete this record too</span>
												</label>
												
											</div>
										</div>
									</td>									
								</tr>
								<tr>							
									<td>
										<div class="text-muted">summary/Status</div>
										<div><?=esc($record['summary'] ?? 'No extra summary')?></div>
										<div class="badge bg-primary"><?=esc($record['status'] ?? 'N/A')?></div>
									</td>
									<td>
										<div class="text-muted">Amount</div>
										<div><?=isset($record['amount']) && $record['amount'] !== null ? 'GHS '.number_format((float)$record['amount'], 2) : 'N/A'?></div>
									</td>
									<td>
										<div class="text-muted">Created</div>
										<div><?=!empty($record['date_created']) ? esc(get_date_time($record['date_created'])) : 'N/A'?></div>
									</td>
								</tr>
							</table>
						</div>
					<?php endforeach; ?>
				<?php else: ?>
					<div class="alert alert-light border mb-0">No direct business records currently point to this cashdrawer.</div>
				<?php endif; ?>
			</div>

			<div class="cashdrawer-delete-card">
				<div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
					<div>
						<h5 class="mb-1">Drawer History References</h5>
						<p class="text-muted mb-0">These are `trans_id` references found in denominations or channel ledgers for this drawer. They are shown for review before the drawer rows are removed.</p>
					</div>
					<span class="badge bg-secondary"><?=count($ledgerRefs)?> reference(s)</span>
				</div>

				<?php if(!empty($ledgerRefs)): ?>
					<div class="table-responsive">
						<table class="cashdrawer-delete-table">
							<thead>
								<tr>
									<th>Source</th>
									<th>Transaction ID</th>
									<th>Status</th>
									<th>Lines</th>
									<th>Net Amount</th>
									<th>Date</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach($ledgerRefs as $ref): ?>
									<tr>
										<td><?=esc($ref['source_table'] ?? '')?></td>
										<td>#<?=esc($ref['trans_id'] ?? 0)?></td>
										<td><?=esc($ref['trans_status'] ?? '')?></td>
										<td><?=number_format((int)($ref['line_count'] ?? 0))?></td>
										<td><?=number_format((float)($ref['net_amount'] ?? 0), 2)?></td>
										<td><?=!empty($ref['date_created']) ? esc(get_date($ref['date_created'])) : 'N/A'?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php else: ?>
					<div class="alert alert-light border mb-0">No denomination or channel movement references were found for this drawer.</div>
				<?php endif; ?>
			</div>

			<div class="cashdrawer-delete-card border-danger">
				<div class="alert alert-danger mb-0">
					This action permanently removes the cashdrawer header, all its denomination rows, CFA rows, channel balances, and every checked linked record. Use edit first if you only need to correct the opening values.
				</div>
				<div class="cashdrawer-delete-actions">
					<div class="d-flex gap-2 flex-wrap">
						<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_cashdrawer/<?=$row->id?>" class="btn btn-outline-secondary">
							<i class="fa fa-arrow-left"></i> Back
						</a>
						<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit_cashdrawer/<?=$row->id?>" class="btn btn-outline-warning">
							<i class="fa fa-pen"></i> Edit Instead
						</a>
					</div>
					<button class="btn btn-danger" type="submit" id="cashdrawerDeleteSubmit">
						<i class="fa fa-trash"></i> Delete Cashdrawer
					</button>
				</div>
			</div>
		</form>
		<script>
			document.addEventListener('DOMContentLoaded', function () {
				const form = document.getElementById('cashdrawerDeleteForm');
				const submitBtn = document.getElementById('cashdrawerDeleteSubmit');
				if (!form || !submitBtn) return;

				let allowNativeSubmit = false;
				const showConfirm = (text, title) => {
					if (window.basicAdminSwalConfirm && typeof window.basicAdminSwalConfirm === 'function') {
						return window.basicAdminSwalConfirm(text, title);
					}
					if (window.Swal && typeof window.Swal.fire === 'function') {
						return window.Swal.fire({
							title: title || 'Confirm',
							text: text || 'Are you sure?',
							icon: 'warning',
							showCancelButton: true,
							confirmButtonText: 'Delete',
							cancelButtonText: 'Cancel'
						});
					}
					return Promise.resolve({ isConfirmed: window.confirm(String(text || 'Are you sure?')) });
				};

				const submitConfirmed = function () {
					allowNativeSubmit = true;
					submitBtn.disabled = true;
					if (typeof form.requestSubmit === 'function') {
						form.requestSubmit();
						return;
					}
					form.submit();
				};

				submitBtn.addEventListener('click', function (e) {
					if (allowNativeSubmit) return;
					e.preventDefault();
					showConfirm('Delete this cashdrawer and apply the selected cleanup?', 'Delete cashdrawer?')
						.then(function (result) {
							const confirmed = (typeof result === 'boolean') ? result : !!(result && result.isConfirmed);
							if (!confirmed) return;
							submitConfirmed();
						});
				});

				form.addEventListener('submit', function (e) {
					if (allowNativeSubmit) return;
					e.preventDefault();
				});
			});
		</script>
	<?php else: ?>
		<div class="alert alert-danger text-center">That cashdrawer was not found.</div>
	<?php endif; ?>
<?php else: ?>
	<div class="alert alert-danger text-center">
		Access denied. You do not have permission for this action.
	</div>
<?php endif; ?>

<?php if(user_can('view_settings')):?>
	<style>
		.settings-shell{max-width: 1100px; margin: auto;}
		.settings-card{
			border: 1px solid rgba(0,0,0,.06);
			border-radius: 16px;
			box-shadow: 0 12px 30px rgba(16,24,40,.08);
			background: var(--bs-body-bg);
		}
		.settings-card .card-header{
			background: linear-gradient(135deg, rgba(65,84,241,.10), rgba(65,84,241,.03));
			border-bottom: 1px solid rgba(0,0,0,.06);
		}
		.settings-section-title{font-weight: 700;letter-spacing: .02em;}
		.settings-help{color: var(--bs-secondary-color);font-size: .9rem;}
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
		.settings-card .input-group > small.text-danger{grid-column: 2;grid-row: 2;margin-top: 6px;}
		@media (max-width: 768px){
			.settings-label{min-width: 140px;}
			.settings-card .input-group{grid-template-columns: 1fr;}
			.settings-card .input-group > .input-group-text{grid-column: 1;grid-row: 1;}
			.settings-card .input-group > .form-control{grid-column: 1;grid-row: 2;}
		}
		.readonly{background: var(--bs-tertiary-bg);}
	</style>

	<div class="settings-shell mx-auto">
		<div class="card settings-card">
			<div class="card-header px-4 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
				<div class="d-flex align-items-center gap-2">
					<img src="<?=APP_LOGO2 ?? ''?>" style="width:44px;height:44px;object-fit:cover;" class="rounded" />
					<div>
						<div class="settings-section-title">System Settings</div>
						<div class="settings-help">View your organization profile, rates and charges.</div>
					</div>
				</div>
				<div class="d-flex align-items-center gap-2">
					<?php if(!empty($row) && !empty($row->date_updated)):?>
						<span class="badge text-bg-light border">Last updated: <?=get_date($row->date_updated)?></span>
					<?php endif?>
				</div>
			</div>
			<div class="card-body p-4">
				<?php $branches = function_exists('admin_settings_fetch_branches') ? admin_settings_fetch_branches(['include_deleted' => false]) : []; ?>
				<div class="alert alert-light border d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
					<div>
						<strong>Branch and Commissions management</strong>
						<div class="small text-muted">Manage branches that are available for user assignment  and commissions calculations.</div>
					</div>
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/branches" class="btn btn-outline-primary btn-sm">
						<i class="fa-solid fa-code-branch"></i> Manage branches
					</a>
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/commission-rates" class="btn btn-outline-warning btn-sm">
						<i class="fa-solid fa-code-branch"></i> Manage commissions
					</a>
				</div>
				<?php if(empty($row)):?>
					<div class="alert alert-warning mb-0">
						Settings are not configured yet.
					</div>
				<?php else:?>
					<div class="row g-4">
						<div class="col-md-3 text-center">
							<img src="<?=get_image($row->image)?>" class="img-thumbnail" style="width:100%;max-width:200px;max-height:200px;object-fit:contain;" />
							<div class="mt-2 fw-semibold text-muted">CEO's signature</div>
						</div>
						<div class="col-md-9">
							<div class="mb-4">
								<div class="settings-section-title mb-1">Company</div>
								<div class="settings-help">Brand and identity shown across the system.</div>
							</div>
							<div class="mb-3 input-group">
								<label class="input-group-text fw-bolder text-muted settings-label">Company Name</label>
								<input class="form-control readonly" readonly value="<?=esc($row->company_name ?? APP_NAME)?>" />
							</div>
							<div class="mb-3 input-group">
								<label class="input-group-text fw-bolder text-muted settings-label">CEO's Name</label>
								<input class="form-control readonly" readonly value="<?=esc($row->ceo_name ?? 'CEO')?>" />
							</div>
							<div class="mb-3 input-group">
								<label class="input-group-text fw-bolder text-muted settings-label">Title</label>
								<input class="form-control readonly" readonly value="<?=esc($row->title ?? '')?>" />
							</div>
							<div class="mb-3 input-group">
								<label class="input-group-text fw-bolder text-muted settings-label">CEO's Contact</label>
								<input class="form-control readonly" readonly value="<?=esc($row->ceo_contact ?? '')?>" />
							</div>
							<div class="mb-3 input-group">
								<label class="input-group-text fw-bolder text-muted settings-label">Office Contact</label>
								<input class="form-control readonly" readonly value="<?=esc($row->admin_contact ?? '')?>" />
							</div>

							<div class="mb-4 mt-4">
								<div class="settings-section-title mb-1">Charges</div>
								<div class="settings-help">Fees applied to transactions.</div>
							</div>
							<div class="mb-3 input-group">
								<label class="input-group-text fw-bolder text-muted settings-label">Transaction charges</label>
								<input class="form-control readonly" readonly value="<?=number_format($row->transaction_fee ?? 0, 2)?>" />
							</div>
							<div class="mb-3 input-group">
								<label class="input-group-text fw-bolder text-muted settings-label">Momo charges</label>
								<input class="form-control readonly" readonly value="<?=esc($row->momo_charges ?? 0)?>" />
							</div>
							<div class="mb-3 input-group">
								<label class="input-group-text fw-bolder text-muted settings-label">Ezwich charges</label>
								<input class="form-control readonly" readonly value="<?=esc($row->ezwich_charges ?? 0)?>" />
							</div>
							<div class="mb-3 input-group">
								<label class="input-group-text fw-bolder text-muted settings-label">Agency Banking charges</label>
								<input class="form-control readonly" readonly value="<?=esc($row->agencybanking_charges ?? 0)?>" />
							</div>

							<div class="mb-4 mt-4">
								<div class="settings-section-title mb-1">Operations</div>
								<div class="settings-help">Opening hours and operational dates.</div>
							</div>
							<div class="mb-3 input-group">
								<label class="input-group-text fw-bolder text-muted settings-label">Date Started</label>
								<input class="form-control readonly" readonly value="<?=esc(get_date($row->date_started))?>" />
							</div>

							<div class="mb-4 mt-4">
								<div class="settings-section-title mb-1">Rates</div>
								<div class="settings-help">Exchange rates used by CFA operations.</div>
							</div>
							<div class="mb-3 input-group">
								<label class="input-group-text fw-bolder text-muted settings-label">CFA Buying rate</label>
								<input class="form-control readonly" readonly value="<?=number_format($row->buying_rate ?? 0, 2)?>" />
							</div>
							<div class="mb-3 input-group">
								<label class="input-group-text fw-bolder text-muted settings-label">CFA Selling rate</label>
								<input class="form-control readonly" readonly value="<?=number_format($row->selling_rate ?? 0, 2)?>" />
							</div>

							<div class="mb-4 mt-4">
								<div class="settings-section-title mb-1">Branches</div>
								<div class="settings-help">Active branches currently available in the system.</div>
							</div>
							<div class="table-responsive mb-4">
								<table class="table table-sm align-middle">
									<thead>
										<tr>
											<th>Name</th>
											<th>Code</th>
											<th>Status</th>
										</tr>
									</thead>
									<tbody>
										<?php if(!empty($branches)):?>
											<?php foreach($branches as $branch):?>
												<tr>
													<td><?=esc($branch->name ?? '')?></td>
													<td><?=esc($branch->code ?? '')?></td>
													<td><?=((int)($branch->active ?? 0) === 1) ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'?></td>
												</tr>
											<?php endforeach?>
										<?php else:?>
											<tr><td colspan="3" class="text-muted">No branches created yet.</td></tr>
										<?php endif?>
									</tbody>
								</table>
							</div>

							<div class="mb-4 mt-4">
								<div class="settings-section-title mb-1">SIM & Hours</div>
								<div class="settings-help">Used in transaction references and operational controls.</div>
							</div>
							<?php
								$formatTimeDisplayValue = static function($value, string $fallback = ''): string {
									$value = trim((string)$value);
									if($value === ''){
										return $fallback;
									}

									if(strlen($value) === 5){
										return $value;
									}

									$timeTs = strtotime('1970-01-01 ' . $value);
									return $timeTs !== false ? date('H:i', $timeTs) : $fallback;
								};

								$viewWorkOpen = $formatTimeDisplayValue($row->work_open ?? '08:00', '08:00');
								$viewWorkClose = $formatTimeDisplayValue($row->work_close ?? '17:00', '17:00');
								$viewLateAttendance = $formatTimeDisplayValue($row->late_attendance_time ?? '');
								if($viewLateAttendance === ''){
									$viewWorkOpenSeed = strlen($viewWorkOpen) === 5 ? $viewWorkOpen . ':00' : $viewWorkOpen;
									$viewWorkOpenTs = strtotime('1970-01-01 ' . $viewWorkOpenSeed);
									$viewLateAttendance = $viewWorkOpenTs !== false ? date('H:i', $viewWorkOpenTs + (30 * 60)) : '08:30';
								}
							?>
							<div class="mb-3 input-group">
								<label class="input-group-text fw-bolder text-muted settings-label">Company Sim</label>
								<input class="form-control readonly" readonly value="<?=esc($row->company_sim ?? '')?>" />
							</div>
							<div class="mb-3 input-group">
								<label class="input-group-text fw-bolder text-muted settings-label">Opening time</label>
								<input class="form-control readonly" readonly value="<?=esc($viewWorkOpen)?>" />
							</div>
							<div class="mb-3 input-group">
								<label class="input-group-text fw-bolder text-muted settings-label">Closing time</label>
								<input class="form-control readonly" readonly value="<?=esc($viewWorkClose)?>" />
							</div>
							<div class="mb-3 input-group">
								<label class="input-group-text fw-bolder text-muted settings-label">Late attendance time</label>
								<input class="form-control readonly" readonly value="<?=esc($viewLateAttendance)?>" />
							</div>
						</div>
					</div>
				<?php endif?>
			</div>
		</div>
	</div>
<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>

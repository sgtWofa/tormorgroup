<?php

$enabled = !empty($settings['enabled']);
$allowedUsers = $settings['allowed_users'] ?? [];
$allowedUsers = is_array($allowedUsers) ? $allowedUsers : [];
$durationMinutes = (int)($settings['duration_minutes'] ?? 120);
$durationMinutes = $durationMinutes > 0 ? $durationMinutes : 120;

$success = message_success('', true);
$fail = message_fail('', true);

?>

<div class="container-fluid">
	<div class="row">
		<div class="col-lg-8">
			<div class="card shadow-sm mb-3">
				<div class="card-body">
					<div class="d-flex align-items-center justify-content-between">
						<div>
							<h5 class="mb-1">Maintenance Mode</h5>
							<div class="text-muted small">Enable maintenance mode and allow selected users to bypass it.</div>
						</div>
						<div>
							<span class="badge <?= $enabled ? 'bg-warning text-dark' : 'bg-success' ?>">
								<?= $enabled ? 'Enabled' : 'Disabled' ?>
							</span>
						</div>
					</div>

					<?php if(!empty($success)): ?>
						<div class="alert alert-success mt-3 mb-0"><?=$success?></div>
					<?php endif; ?>
					<?php if(!empty($fail)): ?>
						<div class="alert alert-danger mt-3 mb-0"><?=$fail?></div>
					<?php endif; ?>
				</div>
			</div>

			<form method="post" action="<?=$form_action?>">
				<div class="card shadow-sm">
					<div class="card-body">
						<div class="form-check form-switch mb-3">
							<input class="form-check-input" type="checkbox" role="switch" id="maint-enabled" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?> >
							<label class="form-check-label fw-semibold" for="maint-enabled">Enable maintenance mode</label>
						</div>

						<div class="row g-3 mb-3">
							<div class="col-sm-6">
								<label for="maint-duration" class="form-label fw-semibold">Maintenance duration (minutes)</label>
								<input type="number" min="1" class="form-control" id="maint-duration" name="duration_minutes" value="<?=esc((string)$durationMinutes)?>">
								<div class="form-text">Used to drive the countdown and expected completion on the maintenance page.</div>
							</div>
							<div class="col-sm-6 d-flex align-items-end">
								<div class="d-flex flex-wrap gap-2">
									<button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('maint-duration').value='30'">30m</button>
									<button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('maint-duration').value='60'">1h</button>
									<button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('maint-duration').value='120'">2h</button>
									<button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('maint-duration').value='240'">4h</button>
								</div>
							</div>
						</div>

						<label class="fw-semibold mb-2">Users allowed to access the app during maintenance</label>
						<div class="text-muted small mb-2">If maintenance mode is enabled, users not selected here will only see the maintenance page.</div>

						<div class="border rounded p-2" style="max-height: 420px; overflow:auto;">
							<?php if(!empty($users) && is_array($users)): ?>
								<?php foreach($users as $u): ?>
									<?php
										$uid = (int)($u->id ?? 0);
										$name = trim((string)($u->first_name ?? '') . ' ' . (string)($u->last_name ?? ''));
										$email = (string)($u->email ?? '');
										$checked = in_array($uid, $allowedUsers, true);
									?>
									<div class="form-check">
										<input class="form-check-input" type="checkbox" name="allowed_users[]" value="<?=$uid?>" id="allow-user-<?=$uid?>" <?=$checked ? 'checked' : ''?> >
										<label class="form-check-label" for="allow-user-<?=$uid?>">
											<span class="fw-semibold"><?=esc($name ?: ('User #'.$uid))?></span>
											<?php if(!empty($email)): ?>
												<span class="text-muted small">(<?=esc($email)?>)</span>
											<?php endif; ?>
										</label>
									</div>
								<?php endforeach; ?>
							<?php else: ?>
								<div class="text-muted">No users found.</div>
							<?php endif; ?>
						</div>

						<div class="d-flex justify-content-end mt-3">
							<button class="btn btn-primary" type="submit">Save</button>
						</div>
					</div>
				</div>
			</form>
		</div>

		<div class="col-lg-4">
			<div class="card shadow-sm">
				<div class="card-body">
					<h6 class="mb-2">Storage</h6>
					<div class="small text-muted mb-2">Settings are stored in:</div>
					<div class="small"><code><?=esc((string)($settings['file'] ?? ''))?></code></div>
				</div>
			</div>
		</div>
	</div>
</div>

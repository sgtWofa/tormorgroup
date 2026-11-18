<style>
	.reset-password-card{max-width:38rem;border:1px solid var(--bs-border-color);border-radius:1rem;background:var(--bs-body-bg);box-shadow:0 1rem 2.5rem rgba(15,23,42,.08)}
	.reset-password-icon{width:3.25rem;height:3.25rem;display:grid;place-items:center;border-radius:1rem;background:rgba(var(--bs-primary-rgb),.12);color:var(--bs-primary);font-size:1.35rem}
	.reset-password-modal .modal-content{border:0;border-radius:1.1rem;overflow:hidden;box-shadow:0 1.5rem 4rem rgba(15,23,42,.24)}
	.reset-password-modal .modal-header{padding:1.35rem 1.5rem;background:linear-gradient(125deg,rgba(var(--bs-warning-rgb),.16),var(--bs-body-bg) 70%)}
	.reset-password-modal .modal-body{padding:1.35rem 1.5rem}
	.reset-password-modal .reset-user-summary{border:1px solid var(--bs-border-color);border-radius:.8rem;background:var(--bs-tertiary-bg);padding:1rem}
	.reset-password-modal .reset-password-warning{border:1px solid rgba(var(--bs-warning-rgb),.4);border-radius:.8rem;background:rgba(var(--bs-warning-rgb),.1)}
</style>

<div class="container-fluid py-4">
	<?php if (!empty($row)): ?>
		<form method="post" id="resetPasswordForm">
			<?=csrf()?>
			<input type="hidden" name="password" value="">
			<div class="reset-password-card mx-auto p-4 p-md-5">
				<div class="d-flex align-items-center gap-3 mb-4">
					<div class="reset-password-icon"><i class="fa fa-unlock" aria-hidden="true"></i></div>
					<div>
						<div class="text-uppercase small text-body-secondary fw-semibold">Account security</div>
						<h3 class="mb-0">Reset password</h3>
					</div>
				</div>
				<div class="mb-3">
					<label class="form-label text-body-secondary">User</label>
					<div class="form-control bg-body-tertiary"><?=esc($row->first_name . ' ' . $row->last_name)?></div>
				</div>
				<div class="mb-4">
					<label class="form-label text-body-secondary">Email address</label>
					<div class="form-control bg-body-tertiary"><?=esc($row->email)?></div>
				</div>
				<div class="alert alert-info d-flex gap-2 align-items-start mb-4">
					<i class="fa fa-circle-info mt-1" aria-hidden="true"></i>
					<div>A new temporary password will be generated. Confirm before continuing.</div>
				</div>
				<div class="d-flex flex-wrap justify-content-between gap-2">
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>" class="btn btn-outline-secondary">
						<i class="fa-solid fa-chevron-left me-1" aria-hidden="true"></i>Back
					</a>
					<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#resetPasswordConfirmModal">
						<i class="fa fa-unlock me-1" aria-hidden="true"></i>Reset password
					</button>
				</div>
			</div>

			<div class="modal fade reset-password-modal" id="resetPasswordConfirmModal" tabindex="-1" aria-labelledby="resetPasswordConfirmTitle" aria-describedby="resetPasswordConfirmDescription" aria-hidden="true">
				<div class="modal-dialog modal-dialog-centered">
					<div class="modal-content">
						<div class="modal-header border-0">
							<div class="d-flex align-items-center gap-3">
								<div class="reset-password-icon"><i class="fa fa-shield-halved" aria-hidden="true"></i></div>
								<div>
									<h5 class="modal-title fw-bold mb-1" id="resetPasswordConfirmTitle">Confirm password reset</h5>
									<div class="small text-body-secondary">This action changes the user's sign-in credentials.</div>
								</div>
							</div>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
						</div>
						<div class="modal-body pt-0">
							<div class="reset-user-summary mb-3">
								<div class="fw-semibold"><?=esc($row->first_name . ' ' . $row->last_name)?></div>
								<div class="small text-body-secondary"><?=esc($row->email)?></div>
							</div>
							<div class="reset-password-warning p-3 small" id="resetPasswordConfirmDescription">
								<i class="fa fa-triangle-exclamation me-1 text-warning-emphasis" aria-hidden="true"></i>
								A new temporary password will be generated and displayed after the reset. Continue only if you intend to reset this account.
							</div>
						</div>
						<div class="modal-footer border-0 pt-0 px-4 pb-4">
							<button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
							<button type="submit" class="btn btn-primary px-4">
								<i class="fa fa-lock me-1" aria-hidden="true"></i>Confirm reset
							</button>
						</div>
					</div>
				</div>
			</div>
		</form>
	<?php else: ?>
		<div class="alert alert-danger text-center">Record not found</div>
		<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>" class="btn btn-danger">
			<i class="fa fa-ban me-1" aria-hidden="true"></i>Cancel
		</a>
	<?php endif; ?>
</div>

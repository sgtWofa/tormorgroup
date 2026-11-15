<?php if(user_can('edit_profile')):?>

	<?php if(!empty($row)):?>
		<?php
			$simAssignmentsForForm = function_exists('users_manager_fetch_sim_assignments') ? users_manager_fetch_sim_assignments((int)$row->id) : [];
			$oldSimAssignmentsJson = old_value('sim_assignments_json', '');
			if(is_string($oldSimAssignmentsJson) && trim($oldSimAssignmentsJson) !== ''){
				$decodedSimAssignments = json_decode(html_entity_decode($oldSimAssignmentsJson, ENT_QUOTES, 'UTF-8'), true);
				if(is_array($decodedSimAssignments)){
					$simAssignmentsForForm = $decodedSimAssignments;
				}
			}
			$simAssignmentsJson = htmlspecialchars(json_encode($simAssignmentsForForm, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
			$isUserDisabled = !empty($_POST) ? !empty($_POST['disabled']) : ((int)($row->disabled ?? 0) === 1);
			$canManageSharedAccounts = user_can('is_admin');
			$sharedAccountCatalog = users_manager_shared_account_catalog();
			$sharedAccountSelections = [];
			$sharedAccountOwnersByKey = [];
			$sharedAccountOwnersLoaded = false;
			$sharedAccountLoadError = '';
			if($canManageSharedAccounts){
				try {
					$sharedAccountSelections = users_manager_fetch_shared_account_assignments((int)$row->id);
					$sharedAccountOwnersByKey = users_manager_fetch_shared_account_assignment_owners();
					$sharedAccountOwnersLoaded = true;
				} catch(\Throwable $e) {
					error_log('[users-manager] Shared-account assignments or owners could not be loaded: ' . $e->getMessage());
					$sharedAccountLoadError = 'Shared-account assignments and current owners could not be loaded. Please retry before saving changes.';
				}
				$oldSharedAccountSelections = old_value('shared_account_assignments_json', '');
				if(is_string($oldSharedAccountSelections) && trim($oldSharedAccountSelections) !== ''){
					try {
						$sharedAccountSelections = users_manager_normalize_shared_account_keys(
							html_entity_decode($oldSharedAccountSelections, ENT_QUOTES, 'UTF-8')
						);
					} catch(\Throwable $e) {
						$sharedAccountLoadError = 'The submitted shared-account selection is invalid. Review the selections and try again.';
					}
				}
			}
		?>

		<style>
			.user-edit-form {
				max-width: 1440px;
				margin-inline: auto;
				padding-block: clamp(1rem, 3vw, 2.25rem);
			}
			.user-edit-form .card {
				border: 1px solid var(--bs-border-color);
				border-radius: 1.1rem;
				box-shadow: 0 .45rem 1.6rem rgba(33, 37, 41, .08);
				overflow: hidden;
			}
			.user-edit-form .card-header {
				background: var(--bs-tertiary-bg);
				padding: 1.25rem clamp(1rem, 2.5vw, 2rem);
			}
			.user-edit-form .card-header h4 {
				font-size: 1.2rem;
				font-weight: 700;
				letter-spacing: -.02em;
			}
			.user-edit-form .card-body {
				padding: clamp(1rem, 2.5vw, 2rem);
			}
			.user-edit-form .form-label {
				color: var(--bs-emphasis-color);
				font-size: .88rem;
				font-weight: 600;
				margin-bottom: .45rem;
			}
			.user-edit-form .form-control,
			.user-edit-form .form-select {
				border-radius: .7rem;
				min-height: 2.75rem;
				transition: border-color .15s ease, box-shadow .15s ease;
			}
			.user-edit-form textarea.form-control {
				min-height: 6rem;
			}
			.user-edit-form .form-control:focus,
			.user-edit-form .form-select:focus {
				box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .14);
			}
			.user-edit-form .border.p-3.rounded,
			.user-edit-form .border.rounded-3 {
				background: var(--bs-tertiary-bg);
				border-color: var(--bs-border-color) !important;
				border-radius: .9rem !important;
			}
			.user-edit-form .shared-account-chip {
				transition: transform .15s ease;
			}
			.user-edit-form .shared-account-chip:hover {
				transform: translateY(-1px);
			}
			.user-edit-form .shared-account-chip > .shared-account-choice {
				padding: .5rem .8rem;
			}
			.user-edit-form .btn {
				border-radius: .7rem;
				font-weight: 600;
				min-height: 2.55rem;
				padding-inline: 1rem;
			}
			.user-edit-form .user-edit-actions {
				border-top: 1px solid var(--bs-border-color);
				padding-top: 1.25rem;
			}
			.user-edit-form .img-thumbnail {
				background: var(--bs-body-bg);
				border: 1px solid var(--bs-border-color);
				border-radius: .9rem;
				padding: .35rem;
			}
		</style>
		<form onsubmit="return submit_form(event)" method="post" enctype="multipart/form-data" class="user-edit-form container-fluid">
			<div class="row">
				<div class="col-xl-9 col-lg-10 mx-auto">
					<div class="card shadow-sm">
						<div class="card-header border-bottom">
							<div class="d-flex align-items-center gap-3">
								<span class="rounded-3 bg-primary-subtle text-primary p-2"><i class="fa-solid fa-user-pen" aria-hidden="true"></i></span>
								<div>
									<h4 class="mb-1">Edit User Record</h4>
									<div class="small text-muted">Update profile details, access, and assigned services.</div>
								</div>
							</div>
						</div>
						<div class="card-body">

			<?=csrf()?>

			<?php if(!empty($errors['general']) || !empty($errors['sim_assignments']) || !empty($errors['shared_accounts']) || $sharedAccountLoadError !== ''):?>
				<div class="alert alert-danger" role="alert">
					<?php if(!empty($errors['general'])):?>
						<div><?=esc($errors['general'])?></div>
					<?php endif?>
					<?php if(!empty($errors['sim_assignments'])):?>
						<div><?=esc($errors['sim_assignments'])?></div>
					<?php endif?>
					<?php if(!empty($errors['shared_accounts'])):?>
						<div><?=esc($errors['shared_accounts'])?></div>
					<?php endif?>
					<?php if($sharedAccountLoadError !== ''):?>
						<div><?=esc($sharedAccountLoadError)?></div>
					<?php endif?>
				</div>
			<?php endif?>

			<div class="row mb-4">
				<div class="col-md-4 mx-auto text-center">
					<label style="cursor:pointer; display:block;">
						<img src="<?=get_image($row->image, $row->gender)?>" class="img-thumbnail" style="width:100%;max-width:150px;max-height: 150px;object-fit: cover;">
						<input onchange="display_image(event)" type="file" name="image" class="d-none">
					</label>
					<small class="text-muted d-block mt-2">Click to change photo</small>
					<?php if(!empty($errors['image'])):?>
					  <small class="text-danger d-block"><?=$errors['image']?></small>
					<?php endif?>
				</div>
			</div>

			<div class="row g-3 mb-3">
				<div class="col-md-6">
				  <label for="first_name" class="form-label">First Name</label>
				  <input name="first_name" value="<?=old_value('first_name',$row->first_name ?? '')?>" type="text" class="form-control" id="first_name" placeholder="First Name">
					<?php if(!empty($errors['first_name'])):?>
					  <small class="text-danger"><?=$errors['first_name']?></small>
					<?php endif?>
				</div>

				<div class="col-md-6">
				  <label for="last_name" class="form-label">Last Name</label>
				  <input name="last_name" value="<?=old_value('last_name',$row->last_name ?? '')?>" type="text" class="form-control" id="last_name" placeholder="Last Name">
					<?php if(!empty($errors['last_name'])):?>
					  <small class="text-danger"><?=$errors['last_name']?></small>
					<?php endif?>
				</div>
			</div>

			<div class="row g-3 mb-3">
				<div class="col-md-6">
				  <label for="phone" class="form-label">Phone</label>
				  <input name="phone" value="<?=old_value('phone',$row->phone ?? '')?>" type="text" class="form-control" id="phone" placeholder="Phone">
					<?php if(!empty($errors['phone'])):?>
					  <small class="text-danger"><?=$errors['phone']?></small>
					<?php endif?>
				</div>

				<div class="col-md-6">
				  <label for="email" class="form-label">Email</label>
				  <input name="email" value="<?=old_value('email', $row->email ?? '')?>" type="email" class="form-control" id="email" placeholder="Email">
					<?php if(!empty($errors['email'])):?>
					  <small class="text-danger"><?=$errors['email']?></small>
					<?php endif?>
				</div>
			</div>

			<div class="row g-3 mb-3">
				<div class="col-md-6">
					<label for="gender" class="form-label">Gender</label>
					<select class="form-select" name="gender" id="gender">
					  <option value="">--Select Gender--</option>
					  <option <?=old_select('gender','male',$row->gender ?? '')?> value="male">Male</option>
					  <option <?=old_select('gender','female',$row->gender ?? '')?> value="female">Female</option>
					</select>
					<?php if(!empty($errors['gender'])):?>
					  <small class="text-danger"><?=$errors['gender']?></small>
					<?php endif?>
				</div>

				<div class="col-md-6">
				  <label for="branch_id" class="form-label">Branch</label>
				  <select class="form-select <?= !empty($errors['branch_id']) ? 'is-invalid' : '' ?>" name="branch_id" id="branch_id">
				    <option value="">--Select Branch--</option>
				    <?php $branchOptions = function_exists('users_manager_branch_options') ? users_manager_branch_options() : []; ?>
				    <?php $selectedBranchId = old_value('branch_id', $row->branch_id ?? ''); ?>
				    <?php foreach ($branchOptions as $branchId => $branchName): ?>
				      <option value="<?= esc($branchId) ?>" <?= ((string)$selectedBranchId === (string)$branchId) ? 'selected' : '' ?>><?= esc($branchName) ?></option>
				    <?php endforeach; ?>
				  </select>
				  <?php if(!empty($errors['branch_id'])):?>
				    <small class="text-danger"><?=$errors['branch_id']?></small>
				  <?php endif?>
				</div>
			</div>

			<div class="row g-3 mb-3">
				<div class="col-md-6">
				  <label for="date_joined" class="form-label">Date Joined</label>
				  <input name="date_joined" value="<?=old_date('date_joined', $row->date_joined ?? '')?>" type="date" class="form-control" id="date_joined">
					<?php if(!empty($errors['date_joined'])):?>
					  <small class="text-danger"><?=$errors['date_joined']?></small>
					<?php endif?>
				</div>

				<div class="col-md-6">
				  <label for="ghcard" class="form-label">Ghana Card</label>
				  <input name="ghcard" value="<?=old_value('ghcard', $row->ghcard ?? '')?>" type="text" class="form-control" id="ghcard" placeholder="Ghana card number">
					<?php if(!empty($errors['ghcard'])):?>
					  <small class="text-danger"><?=$errors['ghcard']?></small>
					<?php endif?>
				</div>
			</div>

			<div class="row g-3 mb-3">
				<div class="col-md-6">
				  <label for="house_number" class="form-label">House Number</label>
				  <input name="house_number" value="<?=old_value('house_number', $row->house_number ?? '')?>" type="text" class="form-control" id="house_number" placeholder="House number">
					<?php if(!empty($errors['house_number'])):?>
					  <small class="text-danger"><?=$errors['house_number']?></small>
					<?php endif?>
				</div>

				<div class="col-md-6">
				  <label for="gps_address" class="form-label">GPS Address</label>
				  <input name="gps_address" value="<?=old_value('gps_address', $row->gps_address ?? '')?>" type="text" class="form-control" id="gps_address" placeholder="GPS address">
					<?php if(!empty($errors['gps_address'])):?>
					  <small class="text-danger"><?=$errors['gps_address']?></small>
					<?php endif?>
				</div>
			</div>

			<div class="row g-3 mb-3">
				<div class="col-md-6">
				  <label for="nearest_landmark" class="form-label">Nearest Landmark</label>
				  <input name="nearest_landmark" value="<?=old_value('nearest_landmark', $row->nearest_landmark ?? '')?>" type="text" class="form-control" id="nearest_landmark" placeholder="Nearest landmark">
					<?php if(!empty($errors['nearest_landmark'])):?>
					  <small class="text-danger"><?=$errors['nearest_landmark']?></small>
					<?php endif?>
				</div>

				<div class="col-md-6">
				  <label for="languages_spoken" class="form-label">Languages Spoken</label>
				  <input name="languages_spoken" value="<?=old_value('languages_spoken', $row->languages_spoken ?? '')?>" type="text" class="form-control" id="languages_spoken" placeholder="Languages spoken">
					<?php if(!empty($errors['languages_spoken'])):?>
					  <small class="text-danger"><?=$errors['languages_spoken']?></small>
					<?php endif?>
				</div>
			</div>

			<div class="row g-3 mb-3">
				<div class="col-md-6">
				  <label for="education_level" class="form-label">Level of Education</label>
				  <input name="education_level" value="<?=old_value('education_level', $row->education_level ?? '')?>" type="text" class="form-control" id="education_level" placeholder="Level of education">
					<?php if(!empty($errors['education_level'])):?>
					  <small class="text-danger"><?=$errors['education_level']?></small>
					<?php endif?>
				</div>

				<div class="col-md-6">
				  <label for="past_reference" class="form-label">Past Reference</label>
				  <input name="past_reference" value="<?=old_value('past_reference', $row->past_reference ?? '')?>" type="text" class="form-control" id="past_reference" placeholder="Past reference">
					<?php if(!empty($errors['past_reference'])):?>
					  <small class="text-danger"><?=$errors['past_reference']?></small>
					<?php endif?>
				</div>
			</div>

			<div class="row g-3 mb-3">
				<div class="col-md-6">
				  <label for="guarantor_name" class="form-label">Guarantor's Name</label>
				  <input name="guarantor_name" value="<?=old_value('guarantor_name', $row->guarantor_name ?? '')?>" type="text" class="form-control" id="guarantor_name" placeholder="Guarantor's name">
					<?php if(!empty($errors['guarantor_name'])):?>
					  <small class="text-danger"><?=$errors['guarantor_name']?></small>
					<?php endif?>
				</div>

				<div class="col-md-6">
				  <label for="guarantor_phone" class="form-label">Guarantor's Phone</label>
				  <input name="guarantor_phone" value="<?=old_value('guarantor_phone', $row->guarantor_phone ?? '')?>" type="text" class="form-control" id="guarantor_phone" placeholder="Guarantor's phone number">
					<?php if(!empty($errors['guarantor_phone'])):?>
					  <small class="text-danger"><?=$errors['guarantor_phone']?></small>
					<?php endif?>
				</div>
			</div>

			<div class="row g-3 mb-4">
				<div class="col-md-6">
					<label class="form-label">Upload Signature</label>
					<label style="cursor:pointer; display:block;">
						<img src="<?=get_image($row->signature ?? '')?>" class="img-thumbnail" style="width:100%;max-width:200px;max-height: 80px;object-fit: cover;">
						<input onchange="display_image(event)" type="file" name="signature" class="d-none">
					</label>
					<small class="text-muted d-block mt-1">Click to upload</small>
					<?php if(!empty($errors['signature'])):?>
					  <small class="text-danger d-block"><?=$errors['signature']?></small>
					<?php endif?>
				</div>
			</div>

			<div class="row mb-4">
				<div class="col-12">
					<div class="border p-3 rounded">
						<label class="form-label fw-semibold mb-3">Roles</label>
						<div class="row g-3">
							<?php 
								$query = "select * from user_roles where disabled = 0";
								$roles = $user_role->query($query); 
								$roleNameById = [];
								foreach(($roles ?? []) as $roleItem){
									$roleNameById[(int)($roleItem->id ?? 0)] = strtolower(trim((string)($roleItem->role ?? '')));
								}
								$selectedRoleIds = [];
								if(!empty($_POST)){
									foreach($_POST as $postKey => $postValue){
										if(strpos((string)$postKey, 'role_') === 0){
											$selectedRoleIds[] = (int)$postValue;
										}
									}
								}else{
									$selectedRoleIds = array_map('intval', $row->role_ids ?? []);
								}
								$selectedRoleIds = array_values(array_unique(array_filter($selectedRoleIds, function($id){
									return $id > 0;
								})));
								$selectedRoleNames = [];
								foreach($selectedRoleIds as $selectedRoleId){
									if(isset($roleNameById[$selectedRoleId])){
										$selectedRoleNames[] = $roleNameById[$selectedRoleId];
									}
								}
								$selectedRoleNames = array_values(array_unique(array_filter($selectedRoleNames)));
								$bankNetworkOptions = function_exists('users_manager_bank_network_options')
									? users_manager_bank_network_options()
									: [['value' => 'CAL BANK', 'label' => 'Cal Bank']];
								$bankAliasMap = function_exists('users_manager_bank_network_alias_map')
									? users_manager_bank_network_alias_map()
									: ['CAL BANK' => 'CAL BANK', 'CALBANK' => 'CAL BANK'];
							?>

							<?php if(!empty($roles)):$num = 0?>
								<?php foreach($roles as $role):$num++?>
									<div class="col-md-6">
										<div class="form-check">
										  <input <?=in_array((int)$role->id, $selectedRoleIds, true) ? ' checked ':''?> name="role_<?=$num?>" class="form-check-input" type="checkbox" value="<?=$role->id?>" id="check<?=$num?>">
										  <label class="form-check-label" for="check<?=$num?>" style="cursor:pointer;">
										    <?=esc(str_replace("_", " ", $role->role))?>
										  </label>
										</div>
									</div>
								<?php endforeach?>
							<?php endif?>
						</div>
					</div>
				</div>
			</div>

			<?php if($canManageSharedAccounts): ?>
				<?php
					$sharedAccountEligible = users_manager_shared_account_roles_eligible($selectedRoleNames);
					if(!$sharedAccountEligible){
						$sharedAccountSelections = [];
					}
					$sharedAccountSelectionsJson = htmlspecialchars(
						json_encode($sharedAccountSelections, JSON_UNESCAPED_SLASHES),
						ENT_QUOTES,
						'UTF-8'
					);
				?>
				<style>
					.shared-account-chip{position:relative;display:inline-flex;cursor:pointer}
					.shared-account-chip input{position:absolute;opacity:0;pointer-events:none}
					.shared-account-choice{display:flex;flex-direction:column;gap:.2rem;min-width:10rem;border:1px solid var(--bs-border-color);border-radius:.85rem;background:var(--bs-body-bg);color:var(--bs-body-color);font-size:.75rem;font-weight:600;padding:.5rem .8rem;transition:background-color .15s ease,color .15s ease,border-color .15s ease}
					.shared-account-status{font-size:.68rem;font-weight:500;line-height:1.25;opacity:.75;overflow-wrap:anywhere}
					.shared-account-chip.has-assignment .shared-account-choice{border-color:rgba(var(--bs-warning-rgb),.55)}
					.shared-account-chip.assigned-to-current .shared-account-choice{border-color:rgba(var(--bs-primary-rgb),.55)}
					.shared-account-chip input:checked+.shared-account-choice{background:var(--bs-primary);border-color:var(--bs-primary);color:#fff}
					.shared-account-chip input:checked+.shared-account-choice .shared-account-status{opacity:.9}
					.shared-account-chip input:focus-visible+.shared-account-choice{outline:3px solid rgba(var(--bs-primary-rgb),.3);outline-offset:2px}
					.shared-account-chip input:disabled+.shared-account-choice{cursor:not-allowed;opacity:.55}
				</style>
				<div class="row mb-4">
					<div class="col-12">
						<div class="border rounded-3 p-3 p-md-4 bg-body-tertiary">
							<div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-2">
								<div>
									<h6 class="fw-semibold mb-1"><i class="fa-solid fa-building-columns text-primary me-2"></i>Shared Account Assignments</h6>
									<p class="small text-muted mb-0">Select accounts for this user. Selecting an account assigned elsewhere transfers it to this user when saved.</p>
								</div>
								<span class="badge rounded-pill text-bg-light border" id="sharedAccountAssignmentCount"><?=count($sharedAccountSelections)?> selected</span>
							</div>
							<div class="small text-muted mb-3" id="sharedAccountEligibilityMessage">
								<?php if($sharedAccountEligible): ?>
									Select one or more accounts. Only assigned accounts will appear in this user's reconciliation and channel-exchange options.
								<?php else: ?>
									Assignment is available only to users with an Admin, Operations Manager, or General Manager role. Saving without an eligible role removes any existing assignments.
								<?php endif; ?>
							</div>
							<input type="hidden" name="shared_account_assignments_json" id="sharedAccountAssignmentsJson" value="<?=$sharedAccountSelectionsJson?>">
							<div class="d-flex flex-wrap gap-2" id="sharedAccountAssignmentOptions">
								<?php foreach($sharedAccountCatalog as $accountKey => $account): ?>
									<?php
										$accountOwners = $sharedAccountOwnersByKey[$accountKey] ?? [];
										$accountOwnerNames = array_values(array_map(
											static fn($owner): string => (string)($owner['name'] ?? ''),
											$accountOwners
										));
										$isAssignedToCurrentUser = count(array_filter(
											$accountOwners,
											static fn($owner): bool => (int)($owner['user_id'] ?? 0) === (int)$row->id
										)) > 0;
										$otherAccountOwnerNames = array_values(array_map(
											static fn($owner): string => (string)($owner['name'] ?? ''),
											array_filter(
												$accountOwners,
												static fn($owner): bool => (int)($owner['user_id'] ?? 0) !== (int)$row->id
											)
										));
										$accountOwnerStatus = !$sharedAccountOwnersLoaded
											? 'Assignment status unavailable'
											: ($isAssignedToCurrentUser
												? (!empty($otherAccountOwnerNames)
													? 'Assigned here; also ' . implode(', ', $otherAccountOwnerNames)
													: 'Assigned to this user')
												: (!empty($accountOwnerNames)
													? 'Assigned to ' . implode(', ', $accountOwnerNames)
													: 'Available'));
									?>
									<label class="shared-account-chip <?=$accountOwners ? 'has-assignment' : ''?> <?=$isAssignedToCurrentUser ? 'assigned-to-current' : ''?>">
										<input
											type="checkbox"
											class="shared-account-assignment-option"
											value="<?=esc($accountKey)?>"
											data-account-key="<?=esc($accountKey)?>"
											<?=in_array($accountKey, $sharedAccountSelections, true) ? 'checked' : ''?>
											<?=(!$sharedAccountEligible || $sharedAccountLoadError !== '') ? 'disabled' : ''?>
										>
										<span class="shared-account-choice">
											<span><?=esc($account['label'])?></span>
											<small class="shared-account-status"><?=esc($accountOwnerStatus)?></small>
										</span>
									</label>
								<?php endforeach; ?>
							</div>
							<?php if(!empty($errors['shared_accounts'])): ?>
								<div class="small text-danger mt-2"><?=esc($errors['shared_accounts'])?></div>
							<?php endif; ?>
						</div>
					</div>
				</div>
				<script>
					(function(){
						const hidden = document.getElementById('sharedAccountAssignmentsJson');
						const options = Array.from(document.querySelectorAll('.shared-account-assignment-option'));
						const eligibilityMessage = document.getElementById('sharedAccountEligibilityMessage');
						const assignmentCount = document.getElementById('sharedAccountAssignmentCount');
						const roleNameById = <?=json_encode($roleNameById, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)?>;
						const ownersByAccount = <?=json_encode($sharedAccountOwnersByKey, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)?>;
						const ownersLoaded = <?=json_encode($sharedAccountOwnersLoaded)?>;
						const targetUserId = <?=json_encode((int)$row->id)?>;
						if(!hidden || !options.length) return;
						const eligibleRoles = new Set(['admin', 'isadmin', 'administrator', 'isadministrator', 'operationsmanager', 'isoperationsmanager', 'operationmanager', 'isoperationmanager', 'opmanager', 'isopmanager', 'opsmanager', 'isopsmanager', 'generalmanager', 'isgeneralmanager', 'gm', 'isgm']);
						const roleOptions = Array.from(document.querySelectorAll('input[name^="role_"]'));
						const isEligible = () => roleOptions.some(function(input){
							if(!input.checked) return false;
							const role = String(roleNameById[String(input.value)] || roleNameById[Number(input.value)] || '').toLowerCase().replace(/[^a-z0-9]/g, '');
							return eligibleRoles.has(role);
						});
						const updateAssignmentStatus = (option) => {
							if(!ownersLoaded) return;
							const status = option.closest('.shared-account-chip')?.querySelector('.shared-account-status');
							if(!status) return;
							const owners = Array.isArray(ownersByAccount[option.dataset.accountKey])
								? ownersByAccount[option.dataset.accountKey]
								: [];
							const currentOwner = owners.some(owner => Number(owner.user_id) === targetUserId);
							const otherOwners = owners
								.filter(owner => Number(owner.user_id) !== targetUserId)
								.map(owner => String(owner.name || ''))
								.filter(Boolean);
							if(option.checked){
								if(otherOwners.length){
									status.textContent = (currentOwner ? 'Already assigned here; ' : '')
										+ 'will transfer from ' + otherOwners.join(', ') + ' on save';
								}else{
									status.textContent = currentOwner ? 'Already assigned to this user' : 'Will assign to this user';
								}
							}else if(currentOwner){
								status.textContent = 'Will unassign from this user on save';
							}else if(otherOwners.length){
								status.textContent = 'Assigned to ' + otherOwners.join(', ');
							}else{
								status.textContent = 'Available';
							}
						};
						const sync = () => {
							const eligible = isEligible();
							options.forEach(function(option){
								option.disabled = !eligible || <?=json_encode($sharedAccountLoadError !== '')?>;
								updateAssignmentStatus(option);
							});
							hidden.value = JSON.stringify(eligible
								? options.filter(option => option.checked).map(option => option.value)
								: []);
							if(assignmentCount){
								const count = eligible ? options.filter(option => option.checked).length : 0;
								assignmentCount.textContent = count + ' selected';
							}
							if(eligibilityMessage){
								eligibilityMessage.textContent = eligible
									? 'Select one or more accounts. Only assigned accounts will appear in this user\'s reconciliation and channel-exchange options.'
									: 'Assignment is available only to users with an Admin, Operations Manager, or General Manager role. Saving without an eligible role removes any existing assignments.';
							}
						};
						options.forEach(option => option.addEventListener('change', sync));
						roleOptions.forEach(option => option.addEventListener('change', sync));
						sync();
					})();
				</script>
			<?php endif; ?>

			<div class="row mb-4">
				<div class="col-12">
					<div class="border p-3 rounded">
						<label class="form-label fw-semibold mb-0">Account Status</label>
						<div class="form-check form-switch mt-3">
							<input name="disabled" value="1" class="form-check-input" type="checkbox" id="user_disabled" <?=$isUserDisabled ? 'checked' : ''?>>
							<label class="form-check-label" for="user_disabled">Disable this user's login</label>
						</div>
						<?php if(!empty($errors['disabled'])):?>
						  <small class="text-danger"><?=$errors['disabled']?></small>
						<?php endif?>
					</div>
				</div>
			</div>

			<div class="row mb-4">
				<div class="col-12">
					<div class="border p-3 rounded">
						<div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
							<div>
								<label class="form-label fw-semibold mb-0">Assigned SIM Data</label>
								<div class="small text-muted">Save user-specific SIM, wallet, and bank details in bulk.</div>
							</div>
							<button type="button" class="btn btn-sm btn-outline-primary" id="addSimAssignmentRow">
								<i class="fa-solid fa-plus"></i> Add row
							</button>
						</div>
						<input type="hidden" name="sim_assignments_json" id="simAssignmentsJson" value="<?=$simAssignmentsJson?>">
						<div class="alert alert-danger d-none" id="simAssignmentsAlert"></div>
						<?php if(!empty($errors['sim_assignments'])):?>
						  <small class="text-danger d-block mb-2"><?=$errors['sim_assignments']?></small>
						<?php endif?>
						<div class="d-grid gap-3" id="simAssignmentRows"></div>
					</div>
				</div>
			</div>

			<div class="row mb-4">
				<div class="col-12">
					<div class="user-edit-actions d-flex justify-content-between gap-2 flex-wrap">
						<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>" class="btn btn-secondary">
							<i class="fa-solid fa-chevron-left"></i> Back
						</a>
						<button type="submit" class="btn btn-primary">
							<i class="fa-solid fa-save"></i> Save Changes
						</button>
					</div>
				</div>
			</div>

						</div>
					</div>
				</div>
			</div>
		</form>

		<script type="text/javascript">

			window.valid_image = window.valid_image || true;
			function display_image(e)
			{
				const inputEl = e.currentTarget;
				const aspectRatio = inputEl && inputEl.name === 'signature' ? 2 : 1;
				if(window.AppImageCropper && typeof window.AppImageCropper.handleLegacyChange === 'function'){
					window.AppImageCropper.handleLegacyChange(e, {
						aspectRatio: aspectRatio,
						maxBytes: window.AppImageCropper.MAX_BYTES,
						modalTitle: aspectRatio === 2 ? 'Crop signature image' : 'Crop profile image',
						useSwal: true,
						errorTitle: 'Invalid image'
					}).then(function(result){
						valid_image = !result || !!result.ok || !!result.cancelled;
					});
					return;
				}

				let allowed = ['image/jpeg','image/png','image/webp'];
				let file = inputEl.files[0];

				if(!allowed.includes(file.type)){
					Swal.fire({
						icon: 'error',
						title: 'Invalid File Type',
						text: "Only files of this type allowed: " + allowed.toString().replaceAll('image/','')
					});
					valid_image = false;
					return;
				}
				valid_image = true;
				inputEl.parentNode.querySelector('img').src = URL.createObjectURL(file);
			}

			function submit_form(e)
			{
				if(!valid_image)
				{
					e.preventDefault()
					Swal.fire({
						icon: 'error',
						title: 'Invalid Image',
						text: "Please add a valid image"
					});
					return false;
				}

				if(typeof window.validateSimAssignmentsForSubmit === 'function' && !window.validateSimAssignmentsForSubmit())
				{
					e.preventDefault();
					return false;
				}

				return true;
			}

			(function(){
				const hidden = document.getElementById('simAssignmentsJson');
				const rowsHolder = document.getElementById('simAssignmentRows');
				const addButton = document.getElementById('addSimAssignmentRow');
				const alertBox = document.getElementById('simAssignmentsAlert');
				if(!hidden || !rowsHolder || !addButton) return;

				const baseNetworkOptionsByType = {
					agent: ['', 'MTN', 'AT', 'TELECEL', 'G-MONEY', 'GHANAPAY', 'ZEEPAY'],
					evd: ['', 'MTN', 'AT', 'TELECEL'],
					whatsapp: ['', 'MTN', 'AT', 'TELECEL'],
				};
				const bankNetworkOptionsAll = <?=json_encode($bankNetworkOptions, JSON_UNESCAPED_SLASHES)?>;
				const bankNetworkOptionsDefault = [{value: 'CAL BANK', label: 'Cal Bank'}];
				const bankAliasMap = <?=json_encode($bankAliasMap, JSON_UNESCAPED_SLASHES)?>;
				const roleNameById = <?=json_encode($roleNameById, JSON_UNESCAPED_SLASHES)?>;
				const adminOrGeneralManagerRoles = ['admin', 'general manager', 'general_manager', 'generalmanager', 'gm'];
				const types = [
					{value: '', label: 'Select type'},
					{value: 'agent', label: 'Agent'},
					{value: 'evd', label: 'EVD'},
					{value: 'bank', label: 'Bank'},
					{value: 'whatsapp', label: 'WhatsApp'},
				];
				const commissionDayFields = [
					{field: 'evd_commission_day', label: 'EVD commission days'},
					{field: 'agent_commission_day', label: 'Agent commission days'},
					{field: 'interest_commission_day', label: 'Interest commission days'},
					{field: 'cashout_commission_day', label: 'Cashout commission days'},
					{field: 'dstv_commission_day', label: 'DSTV commission days'},
					{field: 'others_commission_day', label: 'Others days'},
				];
				const ezwichDayField = {field: 'ezwich_commission_day', label: 'eZwich days'};

				function blankRow(){
					return {
						network: '',
						sim_type: '',
						phone_number: '',
						till_number: '',
						shortcode: '',
						account_number: '',
						evd_commission_day: '',
						agent_commission_day: '',
						interest_commission_day: '',
						cashout_commission_day: '',
						dstv_commission_day: '',
						others_commission_day: '',
						ezwich_commission_day: '',
						notes: ''
					};
				}

				function escapeHtml(value){
					return String(value ?? '').replace(/[&<>"']/g, function(char){
						return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char];
					});
				}

				function normalizeNetwork(value){
					let network = String(value || '').trim().toUpperCase().replace(/[_-]/g, ' ').replace(/\s+/g, ' ');
					if(['G MONEY', 'GMONEY', 'G-MONEY'].includes(network)) return 'G-MONEY';
					if(bankAliasMap[network]) return bankAliasMap[network];
					return network;
				}

				function isFidelityBank(type, network){
					return String(type || '').toLowerCase() === 'bank' && normalizeNetwork(network) === 'FIDELITY BANK';
				}

				function defaultEzwichDay(){
					const date = new Date();
					const day = date.getDay();
					return day === 5 ? 3 : (day === 6 ? 2 : 1);
				}

				function currentSelectedRoleNames(){
					return Array.from(document.querySelectorAll('input[name^="role_"]:checked')).map(function(input){
						return String(roleNameById[String(input.value)] || roleNameById[Number(input.value)] || '').trim().toLowerCase();
					}).filter(Boolean);
				}

				function canUseAllBankNetworks(){
					return currentSelectedRoleNames().some(function(role){
						return adminOrGeneralManagerRoles.includes(role);
					});
				}

				function networkOptionsForType(type){
					type = String(type || '').toLowerCase();
					if(type === 'bank'){
						return canUseAllBankNetworks() ? bankNetworkOptionsAll : bankNetworkOptionsDefault;
					}
					return baseNetworkOptionsByType[type] || [''];
				}

				function fieldVisibleForType(type, field){
					type = String(type || '').toLowerCase();
					if(type === 'bank'){
						return field === 'account_number' || field === 'notes';
					}
					if(type === 'evd' || type === 'whatsapp'){
						return field === 'phone_number' || field === 'notes';
					}
					if(type === 'agent'){
						return field !== 'account_number';
					}
					return true;
				}

				function normalizeRowForType(row){
					row = Object.assign(blankRow(), row || {});
					row.sim_type = String(row.sim_type || '').toLowerCase();
					row.network = normalizeNetwork(row.network);
					const allowed = networkOptionsForType(row.sim_type).map(function(option){
						return normalizeNetwork(typeof option === 'string' ? option : option.value);
					});
					if(row.sim_type === 'bank'){
						if(!allowed.includes(row.network)){
							row.network = allowed.length === 1 ? allowed[0] : '';
						}
					}else if(!allowed.includes(row.network)){
						row.network = '';
					}
					if(row.sim_type === 'bank'){
						row.phone_number = '';
						row.shortcode = '';
						row.till_number = '';
						row.interest_commission_day = '';
						row.dstv_commission_day = '';
					}else if(row.sim_type === 'evd' || row.sim_type === 'whatsapp'){
						row.account_number = '';
						row.shortcode = '';
						row.till_number = '';
						if(row.sim_type === 'evd' && !row.evd_commission_day && row.expected_commission_day){
							row.evd_commission_day = row.expected_commission_day;
						}
					}else if(row.sim_type === 'agent'){
						row.account_number = '';
					}

					if(row.sim_type === 'evd'){
						commissionDayFields.concat([ezwichDayField]).forEach(function(item){
							if(item.field !== 'evd_commission_day') row[item.field] = '';
						});
					}else if(!['agent', 'bank'].includes(row.sim_type)){
						commissionDayFields.concat([ezwichDayField]).forEach(function(item){
							row[item.field] = '';
						});
					}else if(!isFidelityBank(row.sim_type, row.network)){
						row.ezwich_commission_day = '';
					}else if(!row.ezwich_commission_day){
						row.ezwich_commission_day = defaultEzwichDay();
					}
					if(['agent', 'bank'].includes(row.sim_type)){
						if(!row.agent_commission_day && row.expected_commission_day){
							row.agent_commission_day = row.expected_commission_day;
						}
					}
					return row;
				}

				function readRows(){
					try{
						const parsed = JSON.parse(hidden.value || '[]');
						return Array.isArray(parsed) ? parsed : [];
					}catch(e){
						return [];
					}
				}

				function rowHasAnyValue(row){
					return Object.keys(blankRow()).some(function(key){
						return String(row[key] || '').trim() !== '';
					});
				}

				function clearValidationNotice(){
					if(!alertBox) return;
					alertBox.classList.add('d-none');
					alertBox.innerHTML = '';
				}

				function collectRows(){
					const rows = [];
					rowsHolder.querySelectorAll('.sim-assignment-row').forEach(function(rowEl){
						let row = blankRow();
						Object.keys(row).forEach(function(field){
							const input = rowEl.querySelector('[data-field="' + field + '"]');
							if(input) row[field] = input.value.trim();
						});
						if(rowHasAnyValue(row)){
							row = normalizeRowForType(row);
							rows.push(row);
						}
					});
					hidden.value = JSON.stringify(rows);
					return rows;
				}

				function optionHtml(options, selected){
					return options.map(function(option){
						const value = typeof option === 'string' ? option : option.value;
						const label = typeof option === 'string' ? (option || 'Select network') : option.label;
						return '<option value="' + escapeHtml(value) + '"' + (String(value) === String(selected || '') ? ' selected' : '') + '>' + escapeHtml(label) + '</option>';
					}).join('');
				}

				function requiredMessage(row){
					const network = normalizeNetwork(row.network);
					const type = String(row.sim_type || '').toLowerCase();
					if(type === 'bank') return 'Required: account number';
					if(type === 'whatsapp') return 'Required: phone number';
					if(network === 'TELECEL') return 'Required: till number and shortcode';
					if(network === 'AT' && type === 'evd') return 'Required: phone number';
					if(['AT', 'G-MONEY'].includes(network)) return 'Required: shortcode';
					if(['GHANAPAY', 'ZEEPAY', 'MTN'].includes(network)) return 'Required: phone number';
					return 'Select network and type to apply required fields';
				}

				function commissionDayInputsHtml(row){
					const type = String(row.sim_type || '').toLowerCase();
					if(!['agent', 'bank', 'evd'].includes(type)){
						return '';
					}

					const fields = commissionDayFields.filter(function(item){
						if(type === 'evd') return item.field === 'evd_commission_day';
						if(item.field === 'evd_commission_day') return false;
						if(type !== 'bank') return true;
						return !['interest_commission_day', 'dstv_commission_day'].includes(item.field);
					});
					if(isFidelityBank(type, row.network)){
						fields.push(ezwichDayField);
					}

					return fields.map(function(item){
						return `
							<div class="col-md-3">
								<label class="form-label small">${escapeHtml(item.label)}</label>
								<input type="number" min="1" max="365" step="1" class="form-control form-control-sm" data-field="${escapeHtml(item.field)}" value="${escapeHtml(row[item.field])}" placeholder="1-365">
							</div>
						`;
					}).join('');
				}

				function render(){
					let rows = readRows();
					if(rows.length === 0) rows = [blankRow()];
					rowsHolder.innerHTML = rows.map(function(row, index){
						row = normalizeRowForType(row);
						const network = normalizeNetwork(row.network);
						const networkOptions = networkOptionsForType(row.sim_type || '');
						const showPhone = fieldVisibleForType(row.sim_type, 'phone_number');
						const showShortcode = fieldVisibleForType(row.sim_type, 'shortcode');
						const showTill = fieldVisibleForType(row.sim_type, 'till_number');
						const showAccount = fieldVisibleForType(row.sim_type, 'account_number');
						return `
							<div class="sim-assignment-row border rounded p-3" data-index="${index}">
								<div class="d-flex justify-content-between align-items-center gap-2 mb-2">
									<div>
										<div class="fw-semibold">Assignment ${index + 1}</div>
										<div class="text-muted small sim-assignment-rule">${escapeHtml(requiredMessage(row))}</div>
									</div>
									<button type="button" class="btn btn-sm btn-outline-danger" data-remove-row="${index}">
										<i class="fa-solid fa-trash"></i>
									</button>
								</div>
								<div class="row g-2">
									<div class="col-md-3">
										<label class="form-label small">Type</label>
										<select class="form-select form-select-sm" data-field="sim_type">${optionHtml(types, row.sim_type || '')}</select>
									</div>
									<div class="col-md-3">
										<label class="form-label small">Network</label>
										<select class="form-select form-select-sm" data-field="network">${optionHtml(networkOptions, network)}</select>
									</div>
									<div class="col-md-3 ${showPhone ? '' : 'd-none'}">
										<label class="form-label small">Phone number</label>
										<input type="text" class="form-control form-control-sm" data-field="phone_number" value="${escapeHtml(row.phone_number)}">
									</div>
									<div class="col-md-3 ${showShortcode ? '' : 'd-none'}">
										<label class="form-label small">Shortcode</label>
										<input type="text" class="form-control form-control-sm" data-field="shortcode" value="${escapeHtml(row.shortcode)}">
									</div>
									<div class="col-md-3 ${showTill ? '' : 'd-none'}">
										<label class="form-label small">Till number</label>
										<input type="text" class="form-control form-control-sm" data-field="till_number" value="${escapeHtml(row.till_number)}">
									</div>
									<div class="col-md-3 ${showAccount ? '' : 'd-none'}">
										<label class="form-label small">Account number</label>
										<input type="text" class="form-control form-control-sm" data-field="account_number" value="${escapeHtml(row.account_number)}">
									</div>
									${commissionDayInputsHtml(row)}
									<div class="col-md-6">
										<label class="form-label small">Notes</label>
										<input type="text" class="form-control form-control-sm" data-field="notes" value="${escapeHtml(row.notes)}">
									</div>
								</div>
							</div>
						`;
					}).join('');
					collectRows();
				}

				function validateRows(){
					const rows = collectRows();
					const errors = [];
					const seen = {};
					const uniqueFields = [
						{field: 'phone_number', label: 'Phone number'},
						{field: 'till_number', label: 'Till number'},
						{field: 'shortcode', label: 'Shortcode'},
						{field: 'account_number', label: 'Account number'},
					];
					rows.forEach(function(row, index){
						const rowNo = index + 1;
						const network = normalizeNetwork(row.network);
						const type = String(row.sim_type || '').toLowerCase();
						if(!network) errors.push('SIM row ' + rowNo + ': network is required.');
						if(!type) errors.push('SIM row ' + rowNo + ': type is required.');
						if(type === 'agent' && !baseNetworkOptionsByType.agent.includes(network)){
							errors.push('SIM row ' + rowNo + ': Agent network must be MTN, AT, TELECEL, G-MONEY, GHANAPAY or ZEEPAY.');
						}else if(type === 'evd' && !baseNetworkOptionsByType.evd.includes(network)){
							errors.push('SIM row ' + rowNo + ': EVD network must be MTN, AT or TELECEL.');
						}else if(type === 'whatsapp' && !baseNetworkOptionsByType.whatsapp.includes(network)){
						    errors.push('SIM row ' + rowNo + ': WhatsApp network must be MTN, AT or TELECEL.');
						}else if(type === 'bank'){
							const allowedBankNetworks = networkOptionsForType('bank').map(function(option){
								return normalizeNetwork(typeof option === 'string' ? option : option.value);
							});
							if(!allowedBankNetworks.includes(network)){
								const bankLabels = networkOptionsForType('bank').map(function(option){
									return typeof option === 'string' ? option : option.label;
								}).join(', ');
								errors.push('SIM row ' + rowNo + ': Bank network must be ' + bankLabels + '.');
							}
						}

						uniqueFields.forEach(function(item){
							const value = String(row[item.field] || '').trim();
							if(!value) return;

							// Allow duplicate phone_number entries across forms/users when sim_type is whatsapp
							if(type === 'whatsapp' && item.field === 'phone_number'){
								return;
							}

							const key = String(network || 'any-network').toLowerCase() + '|' + item.field + '|' + value.toLowerCase();
							if(seen[key]){
								errors.push('SIM row ' + rowNo + ': ' + item.label + ' "' + value + '" is repeated for ' + (network || 'this bank assignment') + '.');
							}
							seen[key] = true;
						});

						if(type === 'bank'){
							if(!row.account_number) errors.push('SIM row ' + rowNo + ': account number is required for bank type.');
						}

						commissionDayFields.concat([ezwichDayField]).forEach(function(item){
							if(type === 'evd' && item.field !== 'evd_commission_day') return;
							if(['agent', 'bank'].includes(type) && item.field === 'evd_commission_day') return;
							const value = String(row[item.field] || '').trim();
							if(!value) return;
							const commissionDay = Number(value);
							if(!Number.isInteger(commissionDay) || commissionDay < 1 || commissionDay > 365){
								errors.push('SIM row ' + rowNo + ': ' + item.label + ' must be between 1 and 365.');
							}
						});

						// Handle type requirements
						if(type === 'whatsapp'){
							if(!row.phone_number) errors.push('SIM row ' + rowNo + ': phone number is required for WhatsApp.');
							return; // Skip shortcode/till validation rules for whatsapp type
						}

						if(type === 'bank'){
							return;
						}

						if(network === 'TELECEL'){
							if(!row.till_number) errors.push('SIM row ' + rowNo + ': till number is required for TELECEL.');
							if(!row.shortcode) errors.push('SIM row ' + rowNo + ': shortcode is required for TELECEL.');
						}else if(network === 'AT' && type === 'evd'){
							if(!row.phone_number) errors.push('SIM row ' + rowNo + ': phone number is required for AT EVD.');
						}else if(['AT', 'G-MONEY'].includes(network)){
							if(!row.shortcode) errors.push('SIM row ' + rowNo + ': shortcode is required for ' + network + '.');
						}else if(['GHANAPAY', 'ZEEPAY', 'MTN'].includes(network)){
							if(!row.phone_number) errors.push('SIM row ' + rowNo + ': phone number is required for ' + network + '.');
						}
					});

					if(errors.length){
						if(alertBox){
							alertBox.classList.remove('d-none');
							alertBox.innerHTML = errors.map(escapeHtml).join('<br>');
						}
						if(window.Swal){
							Swal.fire({icon: 'error', title: 'Assigned SIM data needs attention', html: errors.map(escapeHtml).join('<br>')});
						}
						return false;
					}

					if(alertBox){
						alertBox.classList.add('d-none');
						alertBox.innerHTML = '';
					}
					return true;
				}

				addButton.addEventListener('click', function(){
					const rows = collectRows();
					rows.push(blankRow());
					hidden.value = JSON.stringify(rows);
					render();
				});

				document.querySelectorAll('input[name^="role_"]').forEach(function(input){
					input.addEventListener('change', function(){
						clearValidationNotice();
						render();
					});
				});

				rowsHolder.addEventListener('click', function(e){
					const button = e.target.closest('[data-remove-row]');
					if(!button) return;
					const index = parseInt(button.getAttribute('data-remove-row') || '-1', 10);
					const rows = collectRows();
					if(index >= 0) rows.splice(index, 1);
					hidden.value = JSON.stringify(rows);
					render();
				});

				rowsHolder.addEventListener('input', function(e){
					if(!e.target.matches('[data-field]')) return;
					clearValidationNotice();
					collectRows();
				});

				rowsHolder.addEventListener('change', function(e){
					if(!e.target.matches('[data-field]')) return;
					clearValidationNotice();
					collectRows();
					render();
				});

				window.validateSimAssignmentsForSubmit = validateRows;

				render();
			})();

		</script>
	<?php else:?>
		<div class="alert alert-danger text-center">
			That record was not found!
		</div>
		
		<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
			<button class="btn btn-primary">Back</button>
		</a>
	<?php endif?>

<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>
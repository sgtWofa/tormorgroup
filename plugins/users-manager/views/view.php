<?php if(user_can('view_profile_details')): ?>
<style>
	.user-profile-page {
		max-width: 1600px;
		margin-inline: auto;
		padding-block: clamp(1rem, 3vw, 2.25rem);
	}
	.user-profile-page .profile-header {
		display: flex;
		flex-direction: column;
		align-items: center;
		align-self: start;
		background: var(--bs-body-bg);
		border: 1px solid var(--bs-border-color);
		border-radius: 1.1rem;
		box-shadow: 0 .4rem 1.5rem rgba(33, 37, 41, .07);
		margin-bottom: 1.25rem;
		padding: 1.5rem 1.25rem;
		text-align: center;
	}
	.user-profile-page .profile-header > img {
		background: var(--bs-tertiary-bg);
		border: 4px solid var(--bs-body-bg);
		border-radius: 50%;
		box-shadow: 0 0 0 1px var(--bs-border-color), 0 .35rem 1rem rgba(33, 37, 41, .12);
		height: 112px;
		object-fit: cover;
		width: 112px;
	}
	.user-profile-page .profile-header > div {
		width: 100%;
	}
	.user-profile-page .profile-header h2 {
		font-size: clamp(1.2rem, 2vw, 1.6rem);
		font-weight: 700;
		letter-spacing: -.025em;
		margin: 1rem 0 .5rem;
		overflow-wrap: anywhere;
	}
	.user-profile-page .profile-header p {
		color: var(--bs-secondary-color);
		font-size: .9rem;
		margin: .4rem 0;
		overflow-wrap: anywhere;
	}
	.user-profile-page .profile-header .profile-meta {
		border-top: 1px solid var(--bs-border-color);
		margin-top: 1rem;
		padding-top: .75rem;
	}
	.user-profile-page .profile-actions {
		display: grid;
		gap: .5rem;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		margin-top: 1.25rem;
	}
	.user-profile-page .profile-actions .btn {
		align-items: center;
		display: inline-flex;
		font-size: .82rem;
		font-weight: 600;
		gap: .45rem;
		justify-content: center;
		min-height: 2.5rem;
		padding: .45rem .6rem;
	}
	.user-profile-page .nav-tabs {
		background: var(--bs-tertiary-bg);
		border: 1px solid var(--bs-border-color);
		border-radius: .95rem;
		display: flex;
		flex-wrap: nowrap;
		gap: .35rem;
		overflow-x: auto;
		padding: .4rem;
		scrollbar-width: thin;
	}
	.user-profile-page .nav-tabs .nav-link {
		border: 0;
		border-radius: .7rem;
		color: var(--bs-secondary-color);
		font-size: .9rem;
		font-weight: 600;
		padding: .65rem .85rem;
		transition: color .15s ease, background-color .15s ease, box-shadow .15s ease;
		white-space: nowrap;
	}
	.user-profile-page .nav-tabs .nav-link:hover {
		background: var(--bs-secondary-bg);
		color: var(--bs-emphasis-color);
	}
	.user-profile-page .nav-tabs .nav-link.active {
		background: var(--bs-primary);
		box-shadow: 0 .2rem .55rem rgba(var(--bs-primary-rgb), .22);
		color: #fff;
	}
	.user-profile-page .nav-tabs .nav-link:focus-visible {
		box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .22);
		outline: 0;
	}
	.user-profile-page .tab-content {
		background: var(--bs-body-bg);
		border: 1px solid var(--bs-border-color);
		border-radius: 1rem;
		box-shadow: 0 .4rem 1.5rem rgba(33, 37, 41, .06);
		margin-top: .85rem;
		min-height: 18rem;
		padding: clamp(1rem, 2vw, 1.75rem);
	}
	.user-profile-page .tab-pane h4 {
		font-size: 1.15rem;
		font-weight: 700;
		letter-spacing: -.015em;
	}
	.user-profile-page .table-responsive {
		border: 1px solid var(--bs-border-color);
		border-radius: .8rem;
	}
	.user-profile-page tbody tr {
		white-space: nowrap;
	}
	.user-profile-page .wrap,
	.user-profile-page tbody tr.wrap {
		white-space: normal;
	}
	.user-profile-page .shared-account-summary {
		background: var(--bs-tertiary-bg);
		border: 1px solid var(--bs-border-color);
		border-radius: .9rem;
		padding: 1rem;
	}
	.user-profile-page .shared-account-badge {
		align-items: center;
		display: inline-flex;
		font-size: .82rem;
		gap: .45rem;
		padding: .55rem .8rem;
	}
	@media (max-width: 767.98px) {
		.user-profile-page .profile-header {
			margin-bottom: 1rem;
		}
		.user-profile-page .profile-actions {
			grid-template-columns: repeat(2, minmax(0, 1fr));
		}
	}
</style>

<?php if(!empty($row)):?>
<?php $isDeletedProfile = (int)($row->deleted ?? 0) === 1; ?>

<div class="user-profile-page container-fluid">
  <div class="row">    
    <!-- Profile Header (Left Column) -->
    <div class="col-lg-3 col-md-4 profile-header">
      <img src="<?=get_image($row->image, $row->gender)?>" data-app-image-viewer data-app-image="<?=get_image($row->image, $row->gender)?>" class="img-thumbnail" alt="User profile picture" style="cursor:pointer;">
      <div>
        <h2><?= esc(trim((string)($row->first_name ?? '') . ' ' . (string)($row->last_name ?? '')))?></h2>
        <?php if((int)($row->disabled ?? 0) === 1):?>
          <span class="badge rounded-pill text-bg-danger mb-2"><i class="fa-solid fa-circle-xmark me-1"></i>Disabled</span>
        <?php else:?>
          <span class="badge rounded-pill text-bg-success mb-2"><i class="fa-solid fa-circle-check me-1"></i>Active</span>
        <?php endif?>
        <?php if((int)($row->deleted ?? 0) === 1):?>
          <div class="alert alert-warning py-2 px-3 mt-2 mb-2 text-start">
            <strong>Deleted user record.</strong>
            This profile is hidden from active users. Historical transactions and activity remain preserved for admin audit.
            <?php if(!empty($row->date_deleted)):?>
              <div class="small mt-1">Deleted: <?=esc(get_date($row->date_deleted))?></div>
            <?php endif?>
          </div>
        <?php endif?>
        <div class="d-flex flex-wrap justify-content-center gap-2 mt-2 mb-3">
          <?php if(!empty($row->roles)): ?>
            <?php foreach($row->roles as $role): ?>
              <span class="badge rounded-pill text-bg-light border"><?=esc($role)?></span>
            <?php endforeach ?>
          <?php endif ?>
        </div>
        <div class="profile-meta">
          <?php if(!empty($row->phone)):?>
            <p><i class="fa-solid fa-phone me-2"></i><?=esc($row->phone)?></p>
          <?php endif?>
          <?php if(!empty($row->email)):?>
            <p><i class="fa-solid fa-envelope me-2"></i><?=esc($row->email)?></p>
          <?php endif?>
        <?php
          $userViewBranch = null;
          if(!empty($row->branch_id) && function_exists('admin_settings_fetch_branch')){
            $userViewBranch = admin_settings_fetch_branch((int)$row->branch_id);
          }
        ?>
        <?php if(!empty($userViewBranch) && !empty($userViewBranch->name)): ?>
          <p><small><i class="fa-solid fa-code-branch me-1"></i>Branch: <?= esc($userViewBranch->name) ?></small></p>
        <?php elseif(!empty($row->branch_id)): ?>
          <p><small><i class="fa-solid fa-code-branch me-1"></i>Branch ID: <?= esc((string)$row->branch_id) ?></small></p>
        <?php endif ?>
          <p><small><i class="fa-regular fa-calendar me-1"></i>Joined <?=get_date($row->date_joined)?></small></p>
        </div>
        
        <div class="profile-actions">
          <a class="btn btn-outline-secondary" aria-label="Back to users" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?><?=$isDeletedProfile ? '/deleted' : ''?>">
            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i><span>Back</span>
          </a>
          <?php if($isDeletedProfile && user_can('delete_user')):?>
            <a class="btn btn-success" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/restore/<?=$row->id?>">
              <i class="fa-solid fa-rotate-left" aria-hidden="true"></i><span>Restore</span>
            </a>
          <?php else:?>
            <a class="btn btn-primary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$row->id?>">
              <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i><span>Edit</span>
            </a>
            <a class="btn btn-outline-danger" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/delete/<?=$row->id?>">
              <i class="fa-solid fa-trash-alt" aria-hidden="true"></i><span>Delete</span>
            </a>
            <a class="btn btn-outline-warning" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/resetpassword/<?=$row->id?>">
              <i class="fa-solid fa-lock-open" aria-hidden="true"></i><span>Reset password</span>
            </a>
          <?php endif;?>
        </div>

<div class="modal fade" id="cashdrawerModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Cashdrawer Details</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body" style="min-height: 70vh;">
				<div id="cashdrawerModalContent" class="p-2">
					<div class="text-center text-muted py-4">Loading...</div>
				</div>
			</div>
		</div>
	</div>
</div>
<div class="modal fade" id="denoHistoryModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Denomination History</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div id="denoHistoryContent" class="d-flex flex-column gap-3">
					<div class="text-center text-muted py-4">Loading...</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-primary btn-sm" id="denoHistoryLoadMoreBtn" data-drawer-id="" data-page="1" data-limit="20">Load more</button>
			</div>
		</div>
	</div>
</div>
      </div>
    </div>
    
    <!-- Content Container with Tabs (Right Column) -->
    <div class="col-lg-9 col-md-8">
      <!-- Tabs Navigation -->
      <ul class="nav nav-tabs" id="profileTabs" role="tablist" aria-label="User profile sections">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab" aria-controls="overview" aria-selected="true"><i class="fa-solid fa-house-user me-2" aria-hidden="true"></i>Overview</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab" aria-controls="attendance" aria-selected="false"><i class="fa-solid fa-calendar-check me-2" aria-hidden="true"></i>Attendance</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="cashdrawers-tab" data-bs-toggle="tab" data-bs-target="#cashdrawers" type="button" role="tab" aria-controls="cashdrawers" aria-selected="false"><i class="fa-solid fa-cash-register me-2" aria-hidden="true"></i>Cashdrawers</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="assigned-sims-tab" data-bs-toggle="tab" data-bs-target="#assigned-sims" type="button" role="tab" aria-controls="assigned-sims" aria-selected="false"><i class="fa-solid fa-sim-card me-2" aria-hidden="true"></i>Assignments</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab" aria-controls="activity" aria-selected="false"><i class="fa-solid fa-clock-rotate-left me-2" aria-hidden="true"></i>Activity</button>
        </li>
      </ul>

      <!-- Tabs Content -->
      <div class="tab-content" id="profileTabsContent">
        <!-- Overview Tab -->
        <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview-tab">
          
          <?php require plugin_path('views/overview.php'); ?>
					
        </div>

			<!-- Attendance Tab -->
			<div class="tab-pane fade" id="attendance" role="tabpanel" aria-labelledby="attendance-tab">
				<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
					<div>
						<h4 class="mb-0">Attendance</h4>
						<div class="text-muted" style="font-size: 13px;">Last 30 days</div>
					</div>
				</div>

				<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
					<div class="d-flex align-items-center gap-2">
						<label class="text-muted" style="font-size:12px;">Items per page</label>
						<select class="form-select form-select-sm" style="width:110px;" id="attLimit">
							<option value="10" selected>10</option>
							<option value="25">25</option>
							<option value="50">50</option>
							<option value="100">100</option>
						</select>
					</div>
					<div class="d-flex align-items-center gap-2">
						<button type="button" class="btn btn-outline-secondary btn-sm" id="attPrev">Prev</button>
						<span class="text-muted" style="font-size:12px;" id="attPageLabel">Page 1</span>
						<button type="button" class="btn btn-outline-secondary btn-sm" id="attNext">Next</button>
					</div>
				</div>
				<div class="table-responsive">
					<table class="table table-striped table-hover align-middle table-sm">
						<thead>
							<tr>
								<th>Date</th>
								<th>Time</th>
								<th>Remarks</th>
								<th>Marked by</th>
								<th>Created</th>
							</tr>
						</thead>
						<tbody id="attTbody">
							<tr><td colspan="5" class="text-center text-muted py-4">Loading...</td></tr>
						</tbody>
					</table>
				</div>
			</div>

			<!-- Cashdrawers Tab -->
			<div class="tab-pane fade" id="cashdrawers" role="tabpanel" aria-labelledby="cashdrawers-tab">
				<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
					<div>
						<h4 class="mb-0">Cashdrawers</h4>
						<div class="text-muted" style="font-size: 13px;">Last 30 days</div>
					</div>
				</div>

				<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
					<div class="d-flex align-items-center gap-2">
						<label class="text-muted" style="font-size:12px;">Items per page</label>
						<select class="form-select form-select-sm" style="width:110px;" id="drLimit">
							<option value="10" selected>10</option>
							<option value="25">25</option>
							<option value="50">50</option>
							<option value="100">100</option>
						</select>
					</div>
					<div class="d-flex align-items-center gap-2">
						<button type="button" class="btn btn-outline-secondary btn-sm" id="drPrev">Prev</button>
						<span class="text-muted" style="font-size:12px;" id="drPageLabel">Page 1</span>
						<button type="button" class="btn btn-outline-secondary btn-sm" id="drNext">Next</button>
					</div>
				</div>

				<div class="table-responsive">
					<table class="table table-striped table-hover align-middle table-sm">
						<thead>
							<tr>
								<th style="width:84px;">Drawer</th>
								<th style="width:170px;">Date</th>
								<th style="min-width:220px;">Denominations</th>
								<th class="text-end" style="width:200px;">
									<div class="fw-semibold">Float (opening)</div>
									<div class="text-muted" style="font-size:11px;">Starting cash by currency</div>
								</th>
								<th style="width:180px;">Created by</th>
								<th style="width:120px;"></th>
							</tr>
						</thead>
						<tbody id="drTbody">
							<tr><td colspan="6" class="text-center text-muted py-4">Loading...</td></tr>
						</tbody>
					</table>
				</div>
			</div>

			<!-- Assigned SIMs Tab -->
			<div class="tab-pane fade" id="assigned-sims" role="tabpanel" aria-labelledby="assigned-sims-tab">
				<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
					<div>
						<h4 class="mb-0">User Assignments</h4>
						<div class="text-muted small">SIM, wallet, bank and shared-account responsibilities</div>
					</div>
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$row->id?>" class="btn btn-outline-primary btn-sm">
						<i class="fa-solid fa-pen-to-square me-1" aria-hidden="true"></i>Edit assignments
					</a>
				</div>
				<?php
					$profileSharedAccountCatalog = function_exists('users_manager_shared_account_catalog')
						? users_manager_shared_account_catalog()
						: [];
					$profileSharedAccountKeys = $userSharedAccountAssignments ?? [];
				?>
				<section class="shared-account-summary mb-4" aria-labelledby="shared-account-summary-title">
					<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
						<div>
							<h5 id="shared-account-summary-title" class="fw-semibold mb-1">
								<i class="fa-solid fa-building-columns text-primary me-2" aria-hidden="true"></i>Shared Accounts
							</h5>
							<p class="text-muted small mb-0">Accounts assigned to this user for reconciliation and channel exchange.</p>
						</div>
						<span class="badge rounded-pill text-bg-light border"><?=count($profileSharedAccountKeys)?> assigned</span>
					</div>
					<?php if(!empty($userSharedAccountAssignmentError)):?>
						<div class="alert alert-warning d-flex align-items-center gap-2 mb-0" role="status">
							<i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
							<span><?=esc($userSharedAccountAssignmentError)?></span>
						</div>
					<?php elseif(!empty($profileSharedAccountKeys)):?>
						<div class="d-flex flex-wrap gap-2">
							<?php foreach($profileSharedAccountKeys as $accountKey):?>
								<?php $accountLabel = $profileSharedAccountCatalog[$accountKey]['label'] ?? $accountKey;?>
								<span class="badge rounded-pill text-bg-primary shared-account-badge">
									<i class="fa-solid fa-building-columns" aria-hidden="true"></i><?=esc($accountLabel)?>
								</span>
							<?php endforeach;?>
						</div>
					<?php else:?>
						<div class="d-flex align-items-center gap-3 text-muted">
							<span class="rounded-circle bg-body p-2"><i class="fa-regular fa-folder-open" aria-hidden="true"></i></span>
							<span>No shared accounts are assigned to this user.</span>
						</div>
					<?php endif;?>
				</section>
				<div class="d-flex align-items-center gap-2 mb-2">
					<span class="badge rounded-pill bg-primary-subtle text-primary-emphasis"><i class="fa-solid fa-sim-card me-1" aria-hidden="true"></i>SIM &amp; service details</span>
				</div>
				<div class="table-responsive">
					<table class="table table-striped table-hover align-middle table-sm">
						<thead>
							<tr>
								<th>Network</th>
								<th>Type</th>
								<th>Commission Days</th>
								<th>Details</th>
								<th>Notes</th>
								<th>Added</th>
							</tr>
						</thead>
						<tbody>
							<?php $assignedRows = $userSimAssignments ?? []; ?>
							<?php if(!empty($assignedRows)):?>
								<?php foreach($assignedRows as $assignment):?>
									<?php
										$details = [];
										if(!empty($assignment->phone_number)) $details[] = 'Phone: ' . $assignment->phone_number;
										if(!empty($assignment->till_number)) $details[] = 'Till: ' . $assignment->till_number;
										if(!empty($assignment->shortcode)) $details[] = 'Shortcode: ' . $assignment->shortcode;
										if(!empty($assignment->account_number)) $details[] = 'Account: ' . $assignment->account_number;
										$commissionDays = [];
										$commissionDayLabels = function_exists('users_manager_sim_commission_day_fields') ? users_manager_sim_commission_day_fields() : [];
										foreach($commissionDayLabels as $field => $label){
											if(!empty($assignment->{$field})){
												$commissionDays[] = $label . ': ' . $assignment->{$field};
											}
										}
									?>
									<tr>
										<td><span class="badge bg-primary"><?=esc($assignment->network ?? '')?></span></td>
										<td><?=esc(ucfirst((string)($assignment->sim_type ?? '')))?></td>
										<td class="wrap"><?=!empty($commissionDays) ? esc(implode(' | ', $commissionDays)) : '<span class="text-muted">-</span>'?></td>
										<td class="wrap"><?=esc(implode(' | ', $details))?></td>
										<td class="wrap"><?=esc($assignment->notes ?? '')?></td>
										<td><?=get_date($assignment->date_created ?? '')?></td>
									</tr>
								<?php endforeach?>
							<?php else:?>
								<tr>
									<td colspan="6" class="text-center text-muted py-4">No assigned SIM data saved for this user.</td>
								</tr>
							<?php endif?>
						</tbody>
					</table>
				</div>
			</div>

			<!-- Activity Tab -->
			<div class="tab-pane fade" id="activity" role="tabpanel" aria-labelledby="activity-tab">
				<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
					<div>
						<h4 class="mb-0">Activity</h4>
						<div class="text-muted" style="font-size: 13px;">User actions and system logs</div>
					</div>
				</div>

				<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
					<div class="d-flex align-items-center gap-2">
						<label class="text-muted" style="font-size:12px;">Items per page</label>
						<select class="form-select form-select-sm" style="width:110px;" id="logLimit">
							<option value="10" selected>10</option>
							<option value="25">25</option>
							<option value="50">50</option>
							<option value="100">100</option>
						</select>
					</div>
					<div class="d-flex align-items-center gap-2">
						<button type="button" class="btn btn-outline-secondary btn-sm" id="logPrev">Prev</button>
						<span class="text-muted" style="font-size:12px;" id="logPageLabel">Page 1</span>
						<button type="button" class="btn btn-outline-secondary btn-sm" id="logNext">Next</button>
					</div>
				</div>
				<div class="table-responsive">
					<table class="table table-striped table-hover align-middle table-sm">
						<thead>
							<tr>
								<th style="width:220px;">Meta</th>
								<th style="width:160px;">Activity</th>
								<th>Description</th>
							</tr>
						</thead>
						<tbody id="logTbody">
							<tr><td colspan="3" class="text-center text-muted py-4">Loading...</td></tr>
						</tbody>
					</table>
				</div>
			</div>
      </div>
    </div>

  </div>
</div>

<?=get_app_image_viewer_inline_source()?>

<?php else: ?>
  <div class="alert alert-danger text-center">
    That record was not found!
  </div>
  
  <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
    <button class="btn btn-primary">Back</button>
  </a>
<?php endif ?>

<?php else: ?>
  <div class="alert alert-danger text-center">
    Access denied. You don't have permission for this action
  </div>
<?php endif ?>


<script>
	(function(){
		var profileUserId = <?= (int)($row->id ?? 0) ?>;
		var adminRoute = "<?=esc($admin_route ?? 'admin')?>";
		var profileRoute = "profile";
		var baseAjax = "<?=ROOT?>/" + adminRoute + "/" + profileRoute + "/";

		var state = {
			att: {page: 1, limit: 10, totalPages: 1, loaded: false},
			dr: {page: 1, limit: 10, totalPages: 1, loaded: false},
			log: {page: 1, limit: 10, totalPages: 1, loaded: false},
		};

		function escHtml(str){
			if(str === undefined || str === null) str = '';
			return String(str).replace(/[&<>"']/g, function(m){
				return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#039;'})[m];
			});
		}

		function formatDateHuman(dtStr){
			if(!dtStr) return '—';
			var d = new Date(dtStr);
			if(isNaN(d.getTime())){
				var forced = new Date(String(dtStr).replace(' ', 'T'));
				if(!isNaN(forced.getTime())) d = forced;
			}
			if(isNaN(d.getTime())) return String(dtStr);
			try{
				return new Intl.DateTimeFormat(undefined, {
					year: 'numeric',
					month: 'short',
					day: '2-digit',
					hour: '2-digit',
					minute: '2-digit'
				}).format(d);
			}catch(e){
				return d.toLocaleString();
			}
		}

		function setLoading(tbodyId, colspan){
			var tb = document.getElementById(tbodyId);
			if(!tb) return;
			tb.innerHTML = '<tr><td colspan="'+colspan+'" class="text-center text-muted py-4">Loading...</td></tr>';
		}

		async function fetchJson(url){
			var res = await fetch(url, {credentials: 'same-origin'});
			return await res.json();
		}

		function nonZeroDenos(denos){
			return (Array.isArray(denos) ? denos : []).filter(function(d){
				var cnt = Number(d && d.count ? d.count : 0);
				var vol = Number(d && d.volume ? d.volume : 0);
				return cnt !== 0 || Math.abs(vol) > 0.009;
			});
		}

		function formatCurrencyAmount(value, currency){
			var cur = currency ? String(currency).toUpperCase() : '';
			var num = Number(value || 0);
			var decimals = cur === 'CFA' ? 0 : 2;
			return (cur ? cur + ' ' : '') + num.toLocaleString(undefined, {minimumFractionDigits: decimals, maximumFractionDigits: decimals});
		}

		function historyTypeLabel(tx){
			var status = String((tx && tx.trans_status) ? tx.trans_status : '').toLowerCase();
			var meta = (tx && tx.meta) ? tx.meta : {};
			if(status.indexOf('exchange') !== -1) return 'Denomination Exchange';
			if(status.indexOf('opening') !== -1 || !(tx && tx.trans_id)) return 'Opening Snapshot';
			if(meta.has_ghs && meta.has_cfa) return 'FX Exchange';
			var net = Number(meta.net_ghs || 0) || Number(meta.net_cfa || 0);
			if(net > 0) return 'Cash In';
			if(net < 0) return 'Cash Out';
			return 'Movement';
		}

		function renderHistoryDetail(detail){
			if(!detail || typeof detail !== 'object') return '';
			var title = detail.label || detail.title || detail.table || 'Transaction detail';
			var parts = [];
			['type','network','phone','reference','amount','status','account_number','customer_name'].forEach(function(key){
				if(detail[key] !== undefined && detail[key] !== null && String(detail[key]).trim() !== ''){
					parts.push(key.replace(/_/g, ' ') + ': ' + detail[key]);
				}
			});
			if(detail.summary) parts.push(detail.summary);
			if(!parts.length) return '';
			return '<div class="border rounded p-2 mt-2" style="font-size:12px; background:rgba(255,255,255,.03);">'+
				'<div class="fw-semibold mb-1">'+escHtml(title)+'</div>'+
				'<div class="text-muted">'+escHtml(parts.join(' | '))+'</div>'+
			'</div>';
		}

		function renderHistoryCard(tx){
			var lines = nonZeroDenos((tx && tx.lines) ? tx.lines : []);
			if(!lines.length) return '';
			var status = escHtml((tx && tx.trans_status) ? tx.trans_status : 'Unknown');
			var date = escHtml(formatDateHuman((tx && tx.date_created) ? tx.date_created : ''));
			var txId = (tx && tx.trans_id) ? ('TX #' + tx.trans_id) : 'Opening snapshot';
			var meta = (tx && tx.meta) ? tx.meta : {};
			var netParts = [];
			if(meta.has_ghs) netParts.push(formatCurrencyAmount(meta.net_ghs || 0, 'GHS'));
			if(meta.has_cfa) netParts.push(formatCurrencyAmount(meta.net_cfa || 0, 'CFA'));
			var rowsHtml = lines.map(function(line){
				var cur = String((line && line.currency) ? line.currency : 'GHS').toUpperCase();
				var denom = Number(line && line.denomination ? line.denomination : 0);
				var denomLabel = cur === 'CFA' ? denom.toLocaleString(undefined, {maximumFractionDigits:0}) : denom.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
				var cnt = Number(line && line.count ? line.count : 0);
				var vol = Number(line && line.volume ? line.volume : 0);
				return '<tr>'+
					'<td>'+escHtml(cur)+'</td>'+
					'<td>'+escHtml(denomLabel)+'</td>'+
					'<td class="text-end">'+escHtml(cnt.toLocaleString())+'</td>'+
					'<td class="text-end">'+escHtml(formatCurrencyAmount(vol, cur))+'</td>'+
				'</tr>';
			}).join('');
			return '<div class="border-start border-warning border-3 rounded p-3" style="background:rgba(255,255,255,.035);">'+
				'<div class="d-flex flex-wrap justify-content-between gap-2 mb-2">'+
					'<div><span class="badge bg-light text-dark">'+status+'</span><div class="fw-semibold mt-2">'+escHtml(txId)+'</div><div class="text-muted small">'+escHtml(netParts.join(' | '))+'</div></div>'+
					'<div class="text-end small"><div>'+date+'</div><div class="fw-semibold">'+escHtml(historyTypeLabel(tx))+'</div></div>'+
				'</div>'+
				renderHistoryDetail((tx && tx.transaction_detail) ? tx.transaction_detail : null)+
				'<div class="table-responsive mt-2"><table class="table table-sm mb-0"><thead><tr><th>Curr</th><th>Denom</th><th class="text-end">Count</th><th class="text-end">Volume</th></tr></thead><tbody>'+rowsHtml+'</tbody></table></div>'+
			'</div>';
		}

		function setHistoryLoadMore(drawerId, page, hasMore){
			var btn = document.getElementById('denoHistoryLoadMoreBtn');
			if(!btn) return;
			btn.setAttribute('data-drawer-id', drawerId || '');
			btn.setAttribute('data-page', String(page || 1));
			btn.style.display = hasMore ? '' : 'none';
			btn.disabled = false;
			btn.textContent = 'Load more';
		}

		async function loadDenoHistory(drawerId, append){
			var content = document.getElementById('denoHistoryContent');
			var btn = document.getElementById('denoHistoryLoadMoreBtn');
			if(!content || !drawerId) return;
			var page = append && btn ? Number(btn.getAttribute('data-page') || 1) + 1 : 1;
			var limit = btn ? Number(btn.getAttribute('data-limit') || 20) : 20;
			if(!append) content.innerHTML = '<div class="text-center text-muted py-4">Loading...</div>';
			if(btn){ btn.disabled = true; btn.textContent = 'Loading...'; }
			try{
				var url = baseAjax + 'ajax_deno_history?user_id=' + encodeURIComponent(profileUserId) + '&drawer_id=' + encodeURIComponent(drawerId) + '&page=' + encodeURIComponent(page) + '&limit=' + encodeURIComponent(limit);
				var data = await fetchJson(url);
				if(!data || !data.success){
					if(!append) content.innerHTML = '<div class="alert alert-warning">'+escHtml((data && data.message) ? data.message : 'No denomination history found.')+'</div>';
					setHistoryLoadMore(drawerId, page, false);
					return;
				}
				var cards = [];
				if(!append && Array.isArray(data.opening)){
					data.opening.forEach(function(tx){ var html = renderHistoryCard(tx); if(html) cards.push(html); });
				}
				(Array.isArray(data.items) ? data.items : []).forEach(function(tx){ var html = renderHistoryCard(tx); if(html) cards.push(html); });
				if(!append){
					content.innerHTML = cards.length ? cards.join('') : '<div class="text-center text-muted py-4">No denomination history found.</div>';
				}else if(cards.length){
					content.insertAdjacentHTML('beforeend', cards.join(''));
				}
				setHistoryLoadMore(drawerId, page, !!data.has_more);
			}catch(err){
				if(!append) content.innerHTML = '<div class="alert alert-danger">Error loading denomination history.</div>';
				setHistoryLoadMore(drawerId, page, false);
			}
		}

		async function loadAttendance(){
			setLoading('attTbody', 5);
			var url = baseAjax + 'ajax_attendance?user_id=' + encodeURIComponent(profileUserId) + '&page=' + state.att.page + '&limit=' + state.att.limit;
			var data = await fetchJson(url);
			var tb = document.getElementById('attTbody');
			if(!tb) return;
			if(!data || !data.success){
				tb.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No attendance records found.</td></tr>';
				return;
			}
			state.att.totalPages = Number((data.pagination && data.pagination.total_pages) ? data.pagination.total_pages : 1);
			var label = document.getElementById('attPageLabel');
			if(label) label.textContent = 'Page ' + state.att.page + ' of ' + state.att.totalPages;
			var rows = data.rows || [];
			if(!rows.length){
				tb.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No attendance records found.</td></tr>';
				return;
			}
			tb.innerHTML = rows.map(function(r){
				var rem = String((r && r.remarks) ? r.remarks : '').trim().toUpperCase();
				var remHtml = rem ? '<span class="badge bg-secondary">'+escHtml(rem)+'</span>' : '<span class="text-muted">—</span>';
				if(rem === 'PRESENT') remHtml = '<span class="badge bg-success">PRESENT</span>';
				if(rem === 'LATE') remHtml = '<span class="badge bg-warning text-dark">LATE</span>';
				if(rem === 'ABSENT') remHtml = '<span class="badge bg-danger">ABSENT</span>';
				return '<tr>'+
					'<td>'+escHtml(r.date || '—')+'</td>'+
					'<td>'+escHtml(r.time || '—')+'</td>'+
					'<td>'+remHtml+'</td>'+
					'<td>'+escHtml(r.created_by || '—')+'</td>'+
					'<td>'+escHtml(formatDateHuman(r.date_created || '—'))+'</td>'+
				'</tr>';
			}).join('');
			state.att.loaded = true;
		}

		async function loadCashdrawers(){
			setLoading('drTbody', 6);
			var url = baseAjax + 'ajax_cashdrawers?user_id=' + encodeURIComponent(profileUserId) + '&page=' + state.dr.page + '&limit=' + state.dr.limit;
			var data = await fetchJson(url);
			var tb = document.getElementById('drTbody');
			if(!tb) return;
			if(!data || !data.success){
				tb.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No cashdrawer entries found.</td></tr>';
				return;
			}
			state.dr.totalPages = Number((data.pagination && data.pagination.total_pages) ? data.pagination.total_pages : 1);
			var label = document.getElementById('drPageLabel');
			if(label) label.textContent = 'Page ' + state.dr.page + ' of ' + state.dr.totalPages;
			var rows = data.rows || [];
			if(!rows.length){
				tb.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No cashdrawer entries found.</td></tr>';
				return;
			}
			function formatDenoStack(label, denos){
				denos = nonZeroDenos(denos);
				if(!denos.length) return '';
				var totalVol = denos.reduce(function(acc, d){ return acc + Number(d && d.volume ? d.volume : 0); }, 0);
				var top = denos.slice(0).sort(function(a,b){ return Number(b && b.volume ? b.volume : 0) - Number(a && a.volume ? a.volume : 0); }).slice(0, 3);
				var lines = top.map(function(d){
					var denom = Number(d && d.denomination ? d.denomination : 0);
					var cnt = Number(d && d.count ? d.count : 0);
					var vol = Number(d && d.volume ? d.volume : 0);
					var denomLabel = (label === 'CFA') ? denom.toLocaleString(undefined, {maximumFractionDigits:0}) : denom.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
					var volLabel = vol.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
					return '<div style="font-size:12px; line-height:1.2;">'+escHtml(label)+' '+escHtml(denomLabel)+' × '+escHtml(cnt)+' <span class="text-muted">('+escHtml(volLabel)+')</span></div>';
				});
				var totalLabel = totalVol.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
				return '<div class="mb-1" style="font-size:12px;"><span class="fw-semibold">'+escHtml(label)+':</span> <span class="text-muted">'+escHtml(totalLabel)+'</span></div>' + lines;
			}

			tb.innerHTML = rows.map(function(r){
				var ghsStack = formatDenoStack('GHS', (r && r.ghs_denos) ? r.ghs_denos : []);
				var cfaStack = formatDenoStack('CFA', (r && r.cfa_denos) ? r.cfa_denos : []);
				var ghsDenos = nonZeroDenos((r && r.ghs_denos) ? r.ghs_denos : []);
				var cfaDenos = nonZeroDenos((r && r.cfa_denos) ? r.cfa_denos : []);
				var ghsTotal = ghsDenos.reduce(function(a,d){return a + Number(d && d.volume ? d.volume : 0);}, 0);
				var cfaTotal = cfaDenos.reduce(function(a,d){return a + Number(d && d.volume ? d.volume : 0);}, 0);
				var hasAnyFloat = (Math.abs(ghsTotal) > 0.009) || (Math.abs(cfaTotal) > 0.009);
				var amt = Number((r && r.amount) ? r.amount : 0).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
				var cur = (r && r.currency) ? String(r.currency) : '';
				var floatHtml = '';
				if(hasAnyFloat){
					if(Math.abs(ghsTotal) > 0.009){
						floatHtml += '<div class="fw-semibold">GHS ' + escHtml(ghsTotal.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})) + '</div>';
					}
					if(Math.abs(cfaTotal) > 0.009){
						floatHtml += '<div class="fw-semibold">CFA ' + escHtml(cfaTotal.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})) + '</div>';
					}
				}else{
					floatHtml = '<div class="fw-semibold">'+escHtml((cur ? (cur + ' ') : '') + amt)+'</div>';
				}
				var denoBlocks = '';
				if(ghsStack){
					denoBlocks += '<div class="p-2 rounded" style="background:rgba(13,110,253,.06);">'+ghsStack+'</div>';
				}
				if(cfaStack){
					denoBlocks += '<div class="p-2 rounded" style="background:rgba(32,201,151,.06);">'+cfaStack+'</div>';
				}
				if(!denoBlocks){
					denoBlocks = '<div class="text-muted" style="font-size:12px;">No denominations recorded</div>';
				}
				return '<tr>'+
					'<td><span class="badge bg-warning text-black">#'+escHtml(r.id || '—')+'</span></td>'+
					'<td>'+escHtml(formatDateHuman(r.date_created || '—'))+'</td>'+
					'<td>'+
						'<div class="d-flex flex-column gap-1">'+
							denoBlocks+
						'</div>'+
					'</td>'+
					'<td class="text-end">'+floatHtml+'</td>'+
					'<td>'+escHtml(r.created_by || '—')+'</td>'+
					'<td class="text-end">'+
						'<div class="btn-group btn-group-sm" role="group">'+
						'<button type="button" class="btn btn-outline-primary btn-sm js-open-drawer" data-drawer-id="'+escHtml(r.id)+'" title="View details">'+
							'<i class="fa-solid fa-eye"></i> <span class="d-none d-md-inline">View</span>'+
						'</button>'+
						'<button type="button" class="btn btn-outline-info btn-sm js-open-deno-history" data-drawer-id="'+escHtml(r.id)+'" title="Denomination history">'+
							'<i class="fa-solid fa-clock-rotate-left"></i>'+
						'</button>'+
						'</div>'+
					'</td>'+
				'</tr>';
			}).join('');
			state.dr.loaded = true;
		}

		async function loadActivity(){
			setLoading('logTbody', 3);
			var url = baseAjax + 'ajax_activity?user_id=' + encodeURIComponent(profileUserId) + '&page=' + state.log.page + '&limit=' + state.log.limit;
			var data = await fetchJson(url);
			var tb = document.getElementById('logTbody');
			if(!tb) return;
			if(!data || !data.success){
				tb.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">No activity logs found.</td></tr>';
				return;
			}
			state.log.totalPages = Number((data.pagination && data.pagination.total_pages) ? data.pagination.total_pages : 1);
			var label = document.getElementById('logPageLabel');
			if(label) label.textContent = 'Page ' + state.log.page + ' of ' + state.log.totalPages;
			var rows = data.rows || [];
			if(!rows.length){
				tb.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">No activity logs found.</td></tr>';
				return;
			}
			tb.innerHTML = rows.map(function(r){
				var dt = escHtml(formatDateHuman(r.date_created || '—'));
				var table = escHtml(r.table_name || '—');
				var act = String((r && r.action) ? r.action : '—').toUpperCase();
				var actBadge = '<span class="badge bg-secondary">'+escHtml(act)+'</span>';
				if(act === 'CREATE') actBadge = '<span class="badge bg-success">CREATE</span>';
				if(act === 'UPDATE') actBadge = '<span class="badge bg-primary">UPDATE</span>';
				if(act === 'DELETE') actBadge = '<span class="badge bg-danger">DELETE</span>';
				var ip = (r && r.ip_address) ? String(r.ip_address) : '';
				var plat = (r && r.device_platform) ? String(r.device_platform) : '';
				var lang = (r && r.device_language) ? String(r.device_language) : '';
				var ua = (r && r.user_agent) ? String(r.user_agent) : '';
				var metaParts = [];
				if(ip) metaParts.push('IP: ' + ip);
				if(plat) metaParts.push(plat);
				if(lang) metaParts.push(lang);
				var metaLine = metaParts.length ? ('<div class="text-muted" style="font-size:12px;"' + (ua ? (' title="'+escHtml(ua)+'"') : '') + '>'+escHtml(metaParts.join(' • '))+'</div>') : '';
				return '<tr>'+
					'<td>'+
						'<div class="fw-semibold">'+dt+'</div>'+
						'<div class="text-muted" style="font-size:12px;">'+table+'</div>'+
					'</td>'+
					'<td>'+actBadge+'</td>'+
					'<td class="profile-activity-desc">'+escHtml(r.description || '')+ metaLine +'</td>'+
				'</tr>';
			}).join('');
			state.log.loaded = true;
		}

		function bindPaging(prefix, key, loader){
			var prev = document.getElementById(prefix+'Prev');
			var next = document.getElementById(prefix+'Next');
			var limitSel = document.getElementById(prefix+'Limit');
			if(prev){
				prev.addEventListener('click', function(){
					if(state[key].page <= 1) return;
					state[key].page -= 1;
					loader();
				});
			}
			if(next){
				next.addEventListener('click', function(){
					if(state[key].page >= state[key].totalPages) return;
					state[key].page += 1;
					loader();
				});
			}
			if(limitSel){
				limitSel.addEventListener('change', function(){
					state[key].limit = Number(limitSel.value || 10);
					state[key].page = 1;
					loader();
				});
			}
		}

		bindPaging('att', 'att', loadAttendance);
		bindPaging('dr', 'dr', loadCashdrawers);
		bindPaging('log', 'log', loadActivity);

		loadAttendance();
		var attTab = document.getElementById('attendance-tab');
		if(attTab){
			attTab.addEventListener('shown.bs.tab', function(){ if(!state.att.loaded) loadAttendance(); });
		}
		var drTab = document.getElementById('cashdrawers-tab');
		if(drTab){
			drTab.addEventListener('shown.bs.tab', function(){ if(!state.dr.loaded) loadCashdrawers(); });
		}
		var logTab = document.getElementById('activity-tab');
		if(logTab){
			logTab.addEventListener('shown.bs.tab', function(){ if(!state.log.loaded) loadActivity(); });
		}

		// Cashdrawer modal opener
		document.addEventListener('click', function(e){
			var btn = e.target.closest('.js-open-drawer');
			if(!btn) return;
			e.preventDefault();
			var drawerId = btn.getAttribute('data-drawer-id') || '';
			if(!drawerId) return;
			var content = document.getElementById('cashdrawerModalContent');
			if(content){
				content.innerHTML = '<div class="text-center text-muted py-4">Loading...</div>';
			}
			var el = document.getElementById('cashdrawerModal');
			if(el && window.bootstrap && bootstrap.Modal){
				bootstrap.Modal.getOrCreateInstance(el).show();
			}
			fetch("<?=ROOT?>/" + adminRoute, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({action: 'view-drawer-by-id', drawerId: parseInt(drawerId || '0')})
			}).then(function(r){
				return r.json();
			}).then(function(data){
				if(!content) return;
				if(data && data.success){
					content.innerHTML = data.html || '<div class="text-center text-muted py-4">No data</div>';
				}else{
					content.innerHTML = '<div class="alert alert-warning">'+escHtml((data && data.message) ? data.message : 'Failed to load cashdrawer')+'</div>';
				}
			}).catch(function(){
				if(!content) return;
				content.innerHTML = '<div class="alert alert-danger">Error loading cashdrawer.</div>';
			});
		});

		document.addEventListener('click', function(e){
			var btn = e.target.closest('.js-open-deno-history');
			if(!btn) return;
			e.preventDefault();
			var drawerId = btn.getAttribute('data-drawer-id') || '';
			if(!drawerId) return;
			var content = document.getElementById('denoHistoryContent');
			if(content){
				content.innerHTML = '<div class="text-center text-muted py-4">Loading...</div>';
			}
			setHistoryLoadMore(drawerId, 1, false);
			var el = document.getElementById('denoHistoryModal');
			if(el && window.bootstrap && bootstrap.Modal){
				bootstrap.Modal.getOrCreateInstance(el).show();
			}
			loadDenoHistory(drawerId, false);
		});

		var historyMore = document.getElementById('denoHistoryLoadMoreBtn');
		if(historyMore){
			historyMore.addEventListener('click', function(){
				var drawerId = historyMore.getAttribute('data-drawer-id') || '';
				if(!drawerId) return;
				loadDenoHistory(drawerId, true);
			});
		}

		document.addEventListener('hidden.bs.modal', function(e){
			if(!e || !e.target || e.target.id !== 'cashdrawerModal') return;
			var content = document.getElementById('cashdrawerModalContent');
			if(content) content.innerHTML = '';
		});
		document.addEventListener('hidden.bs.modal', function(e){
			if(!e || !e.target || e.target.id !== 'denoHistoryModal') return;
			var content = document.getElementById('denoHistoryContent');
			if(content) content.innerHTML = '';
			setHistoryLoadMore('', 1, false);
		});
	})();
</script>

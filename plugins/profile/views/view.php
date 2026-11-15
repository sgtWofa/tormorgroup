<style>
	  /* General styling */
	  body {
	    font-family: Arial, sans-serif;
	   }
	  .container {
	    max-width: 1200px;
	    margin: 40px auto;
	    padding: 20px;
	  }
	  .profile-header {
	    display: flex;
	    flex-direction: column;
	    align-items: center;
	    text-align: center;
	    margin-bottom: 20px;
	  }
	  .profile-header img {
	    border-radius: 50%;
	    width: 120px;
	    height: 120px;
	    object-fit: cover;
	  }
	  .profile-header h2 {
	    margin: 10px 0 5px;
	    font-size: 1.8rem;
	    color: #4154f1;
	  }
	  .profile-header p {
	    margin: 5px 0;
	    color: #777;
	  }
	  .profile-actions {
	    display: flex;
	    gap: 10px;
	    margin-top: 10px;
	  }
	  .profile-actions .btn {
	    font-size: 0.9rem;
	    padding: 5px 10px;
	  }
	  
	  /* Tabs styling */
	  .tab-content {
	    padding: 20px;
	    background-color: transparent;
	    border-radius: 10px;
	    box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.1);
	  }

	  .profile-activity-desc{
		white-space: normal;
		word-break: break-word;
		overflow-wrap: anywhere;
	  }
	  .profile-activity-meta{
		min-width: 220px;
	  }

	  .active {
		  background-color: transparent; !important;
		  color: var(--bs-secondary-color) !important;
		}
	 .nav-tabs .nav-link {
	  color: var(--bs-secondary-color) !important;
	  font-weight: bold;
	}
	  .nav-tabs .nav-link.active {
	    color: #4154f1;
	    border-bottom: 2px solid #4154f1;
	  }

  tbody tr{
			white-space: nowrap;
		}
		tbody tr.wrap{
			white-space: normal;
		}
</style>

<?php
$session = get_session();
$userId = $session->id;

$__dbProfile = new \Core\Database;
$__profileUserId = (int)($row->id ?? 0);

$__hasAttendance = false;
$__hasCashdrawers = false;
$__hasAssignedSims = false;
$__hasLogs = false;

try {
	$__hasAttendance = $__dbProfile->table_exists(['attendance']);
} catch (\Throwable $e) {
	$__hasAttendance = false;
}

try {
	$__hasCashdrawers = $__dbProfile->table_exists(['cashdrawers']);
} catch (\Throwable $e) {
	$__hasCashdrawers = false;
}

try {
	$__hasAssignedSims = $__dbProfile->table_exists(['user_sim_assignments']);
} catch (\Throwable $e) {
	$__hasAssignedSims = false;
}

try {
	$__hasLogs = $__dbProfile->table_exists(['logs']);
} catch (\Throwable $e) {
	$__hasLogs = false;
}

$__attRows = [];
$__attKpis = [
	'total' => 0,
	'present' => 0,
	'late' => 0,
	'absent' => 0,
	'other' => 0,
	'from' => null,
	'to' => null,
];

if ($__profileUserId > 0 && $__hasAttendance) {
	$__workOpen = '';
	$__lateAttendanceTime = '';
	try {
		if ($__dbProfile->table_exists(['settings'])) {
			$__srow = ($__dbProfile->query("SELECT work_open, late_attendance_time FROM settings ORDER BY id DESC LIMIT 1") ?: [])[0] ?? null;
			$__workOpen = $__srow ? trim((string)($__srow->work_open ?? '')) : '';
			$__lateAttendanceTime = $__srow ? trim((string)($__srow->late_attendance_time ?? '')) : '';
		}
	} catch (\Throwable $e) {
		$__workOpen = '';
		$__lateAttendanceTime = '';
	}
	if ($__lateAttendanceTime !== '') {
		if (strlen($__lateAttendanceTime) === 5) $__lateAttendanceTime .= ':00';
		$__lateThreshold = $__lateAttendanceTime;
	} else {
		if ($__workOpen === '') $__workOpen = '08:00:00';
		if (strlen($__workOpen) === 5) $__workOpen .= ':00';
		$__workOpenTs = strtotime('1970-01-01 ' . $__workOpen);
		$__lateThreshold = ($__workOpenTs !== false) ? date('H:i:s', $__workOpenTs + (30 * 60)) : '08:30:00';
	}

	$__attFrom = date('Y-m-d', strtotime('-30 days'));
	$__attTo = date('Y-m-d');
	$__attKpis['from'] = $__attFrom;
	$__attKpis['to'] = $__attTo;

	$__attRows = $__dbProfile->query(
		"SELECT a.*
		FROM attendance a
		WHERE a.deleted = 0 AND a.user_id = :uid AND a.date >= :dfrom AND a.date <= :dto
		ORDER BY a.date DESC, a.time DESC",
		['uid' => (string)$__profileUserId, 'dfrom' => $__attFrom, 'dto' => $__attTo]
	) ?: [];

	$__attKpis['total'] = is_array($__attRows) ? count($__attRows) : 0;
	foreach (($__attRows ?: []) as $__r) {
		$__remRaw = strtoupper(trim((string)($__r->remarks ?? '')));
		$__t = trim((string)($__r->time ?? ''));
		if (strlen($__t) === 5) $__t .= ':00';
		$__eff = $__remRaw;
		if ($__remRaw === 'PRESENT' || $__remRaw === 'LATE') {
			$__tTs = $__t !== '' ? strtotime('1970-01-01 ' . $__t) : false;
			$__thrTs = strtotime('1970-01-01 ' . $__lateThreshold);
			if ($__tTs !== false && $__thrTs !== false && $__tTs >= $__thrTs) {
				$__eff = 'LATE';
			} else {
				$__eff = 'PRESENT';
			}
		}

		if ($__eff === 'PRESENT') {
			$__attKpis['present']++;
		} elseif ($__eff === 'LATE') {
			$__attKpis['late']++;
		} elseif ($__eff === 'ABSENT') {
			$__attKpis['absent']++;
		} else {
			$__attKpis['other']++;
		}
	}
}

$__drawerRows = [];
$__drawerKpis = [
	'total' => 0,
	'last_30_total_amount' => 0.0,
	'currency_breakdown' => [],
	'from' => null,
	'to' => null,
];

if ($__profileUserId > 0 && $__hasCashdrawers) {
	$__drFrom = date('Y-m-d 00:00:00', strtotime('-30 days'));
	$__drTo = date('Y-m-d 23:59:59');
	$__drawerKpis['from'] = $__drFrom;
	$__drawerKpis['to'] = $__drTo;

	$__drawerRows = $__dbProfile->query(
		"SELECT c.*
		FROM cashdrawers c
		WHERE c.user_id = :uid AND c.date_created >= :dfrom AND c.date_created <= :dto
		ORDER BY c.date_created DESC
		LIMIT 200",
		['uid' => (string)$__profileUserId, 'dfrom' => $__drFrom, 'dto' => $__drTo]
	) ?: [];

	$__drawerKpis['total'] = is_array($__drawerRows) ? count($__drawerRows) : 0;
	foreach (($__drawerRows ?: []) as $__r) {
		$__amt = (float)($__r->amount ?? 0);
		$__drawerKpis['last_30_total_amount'] += $__amt;
		$__cur = strtoupper(trim((string)($__r->currency ?? '')));
		if ($__cur === '') $__cur = '—';
		if (!isset($__drawerKpis['currency_breakdown'][$__cur])) {
			$__drawerKpis['currency_breakdown'][$__cur] = 0.0;
		}
		$__drawerKpis['currency_breakdown'][$__cur] += $__amt;
	}
}

$userSimAssignments = $userSimAssignments ?? [];
if ($__profileUserId > 0 && $__hasAssignedSims && empty($userSimAssignments)) {
	$userSimAssignments = $__dbProfile->query(
		"SELECT *
		FROM user_sim_assignments
		WHERE user_id = :uid
		AND deleted = 0
		ORDER BY sim_type ASC, network ASC, id ASC",
		['uid' => (string)$__profileUserId]
	) ?: [];
}

$__logRows = [];
if ($__profileUserId > 0 && $__hasLogs) {
	$__logRows = $__dbProfile->query(
		"SELECT l.*
		FROM logs l
		WHERE l.user_id = :uid
		ORDER BY l.date_created DESC
		LIMIT 200",
		['uid' => (string)$__profileUserId]
	) ?: [];
}

?>
<?php if(user_can('view_profile_details') || $userId === $row->id):?>
	<?php if(!empty($row)):?>
			
		<div class="container">
		  <!-- Profile Header and Tabs Section -->
		  <div class="row">
		    
		    <!-- Profile Header (Left Column) -->
		    <div class="col-lg-3 col-md-4 profile-header">
		      <img src="<?=get_image($row->image, $row->gender)?>" data-app-image-viewer data-app-image="<?=get_image($row->image, $row->gender)?>" class="img-thumbnail" alt="User Profile Picture" style="cursor:pointer;">
		      <div>
		        <h2><?= $row->first_name . ' ' . $row->last_name ?></h2>
		        <p>
		          <?php if(!empty($row->roles)): ?>
		            <?php foreach($row->roles as $role): ?>
		              <?= esc($role) ?>, 
		            <?php endforeach ?>
		          <?php endif ?>
		        </p>
		        <p><?= $row->email ?? '' ?></p>
		        <p><?= $row->phone ?? '' ?></p>
		        <?php
		          $profileViewBranch = null;
		          if(!empty($row->branch_id) && function_exists('admin_settings_fetch_branch')){
		            $profileViewBranch = admin_settings_fetch_branch((int)$row->branch_id);
		          }
		        ?>
		        <?php if(!empty($profileViewBranch) && !empty($profileViewBranch->name)): ?>
		          <p><small><i class="fa-solid fa-code-branch me-1"></i>Branch: <?= esc($profileViewBranch->name) ?></small></p>
		        <?php elseif(!empty($row->branch_id)): ?>
		          <p><small><i class="fa-solid fa-code-branch me-1"></i>Branch ID: <?= esc((string)$row->branch_id) ?></small></p>
		        <?php endif ?>
		        <p><small>Joined: <?= get_date($row->date_joined) ?></small></p>
		        
		        <div class="profile-actions">
		          <a href="<?=ROOT?>/<?=$admin_route?>/">
		            <button class="btn btn-warning">
		              <i class="fa-solid fa-chevron-left" title="Back"></i>
		            </button>
		          </a>
		          <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$row->id?>">
		            <button class="btn btn-primary">
		              <i class="fa-solid fa-pen-to-square" title="Edit"></i>
		            </button>
		          </a>
		          <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/delete/<?=$row->id?>">
		            <button class="btn btn-danger">
		              <i class="fa-solid fa-trash-alt" title="Delete"></i>
		            </button>
		          </a>
		          <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/resetpassword/<?=$row->id?>">
		            <button class="btn btn-warning">
		              <i class="fa-solid fa-lock-open" title="Reset Password"></i>
		            </button>
		          </a>
		        </div>
		      </div>
		    </div>
		    
		    <!-- Content Container with Tabs (Right Column) -->
		    <div class="col-lg-9 col-md-8">
		      <!-- Tabs Navigation -->
		      <ul class="nav nav-tabs" id="profileTabs" role="tablist">
		        <li class="nav-item" role="presentation">
		          <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">Overview</button>
		        </li>
		        <li class="nav-item" role="presentation">
		          <button class="nav-link" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab">Attendance</button>
		        </li>
		        <li class="nav-item" role="presentation">
		          <button class="nav-link" id="cashdrawers-tab" data-bs-toggle="tab" data-bs-target="#cashdrawers" type="button" role="tab">Cashdrawers</button>
		        </li>
		        <li class="nav-item" role="presentation">
		          <button class="nav-link" id="assigned-sims-tab" data-bs-toggle="tab" data-bs-target="#assigned-sims" type="button" role="tab">Assigned SIMs</button>
		        </li>
		        <li class="nav-item" role="presentation">
		          <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab">Activity</button>
		        </li>
		      </ul>

		      <!-- Tabs Content -->
		      <div class="tab-content" id="profileTabsContent">
		        <!-- Overview Tab -->
		        <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview-tab">
		          <h4 style="color:darkslateblue !important;">Overview</h4>
		          <p style="color:darkslateblue !important;">
		          		<i><?=$row->overview ?? ''?></i>
		          </p>
		          <div class="col table-responsive">
						<table class="table table-striped table-hover border-bottom col-lg-6 col-md-12 table-sm" style="height: fit-content !important;">
							<thead>
								<tr><th colspan="2" class="text-primary">BIO DETAILS</th></tr>
							</thead>
							<tbody>
								<tr>
									<th class="text-muted">Firstname: </th>
									<td><?=esc($row->first_name ?? '')?></td>
								</tr>
								<tr>
									<th class="text-muted">Lastname: </th>
									<td><?=esc($row->last_name ?? '')?></td>
								</tr>
								<?php if(!empty($row->gender)): ?>
								<tr>
									<th class="text-muted">Gender</th>
									<td><?=esc($row->gender)?></td>
								</tr>
								<?php endif;?>			
								<?php if(!empty($row->email)): ?>
								<tr>
									<th class="text-muted">Email: </th>
									<td><?=esc($row->email)?></td>
								</tr>
								<?php endif;?>		
								<tr>
									<th class="text-muted">Phone: </th>
									<td><?=esc($row->phone)?></td>
								</tr>		
								<tr>
									<th class="text-muted">Role: </th>
									<td>
										<?php if(!empty($row->roles)): ?>
								            <?php foreach($row->roles as $role): ?>
								              <?= esc($role) ?>,
								            <?php endforeach ?>
								          <?php endif ?>
									</td>
								</tr>	
								<?php if(!empty($row->education_level)): ?>
								<tr>
									<th class="text-muted">Highest education: </th>
									<td><?=esc($row->education_level, 2)?></td>
								</tr>
								<?php endif;?>				
								<?php if(!empty($row->languages_spoken)): ?>
								<tr>
									<th class="text-muted">Language(s) spoken: </th>
									<td><?=esc($row->languages_spoken)?></td>
								</tr>
								<?php endif;?>			
								<?php if(!empty($row->date_joined)): ?>
								<tr>
									<th class="text-muted">Date joined: </th>
									<td><?=get_date($row->date_joined)?></td>
								</tr>
								<?php endif;?>			
								<?php if(!empty($row->signature)): ?>
								<tr>
									<th class="text-muted">Signature: </th>
									<td><img src="<?=get_image($row->signature, $row->gender)?>" data-app-image-viewer data-app-image="<?=get_image($row->signature, $row->gender)?>" class="img-thumbnail" alt="User Profile Picture" style="cursor:pointer;width:100%;max-width:fit-content;max-height: 80px;object-fit: fill; border: none;"></td>
								</tr>
								<?php endif;?>
							</tbody>
						</table>
					</div>

					<div class="col table-responsive">
						<table class="table table-striped table-hover border-bottom col-lg-6 col-md-12 table-sm" style="height: fit-content !important;">
							<thead>
								<tr><th colspan="2" class="text-primary">OTHER DETAILS</th></tr>
							</thead>
							<tbody>
								
								<?php if(!empty($row->nearest_landmark)): ?>
								<tr>
									<th class="text-muted">Nearest landmark: </th>
									<td><?=esc($row->nearest_landmark)?></td>
								</tr>
								<?php endif;?>					
								<?php if(!empty($row->house_number)): ?>
								<tr>
									<th class="text-muted">Account #: </th>
									<td><?=esc($row->house_number)?></td>
								</tr>
								<?php endif;?>					
								<?php if(!empty($row->ghcard)): ?>
								<tr>
									<th class="text-muted">Ghana card #: </th>
									<td><?=esc($row->ghcard)?></td>
								</tr>
								<?php endif;?>				
								<?php if(!empty($row->gps_address)): ?>
								<tr>
									<th class="text-muted">GPS: </th>
									<td><?=esc($row->gps_address)?></td>
								</tr>
								<?php endif;?>				
								<?php if(!empty($row->past_reference)): ?>
								<tr>
									<th class="text-muted">Past reference</th>
									<td><?=esc($row->past_reference, 2)?></td>
								</tr>
								<?php endif;?>
								<?php if(!empty($row->guarantor_name)): ?>
								<tr>
									<th class="text-muted">Guarantor:</th>
									<td><?=esc($row->guarantor_name, 2)?></td>
								</tr>
								<?php endif;?>			
								<?php if(!empty($row->guarantor_phone)): ?>
								<tr>
									<th class="text-muted">Guarantor phone : </th>
									<th><?=esc($row->guarantor_phone, 2)?></th>
								</tr>
								<?php endif;?>
								<tr class="wrap">
									<th class="text-muted">Created :</th>
									<td>
										<span><img src="<?=get_image($row->user_image)?>" style="width: 20px; height: 20px; object-fit: cover; border-radius: 50%; margin: 0px;"></span> 
										<?=$row->user_first_name . ' ' . $row->user_last_name ?>
										<span class="badge bg-secondary text-white" style="font-size: 11px; font-weight:bolder;"><?=get_date_time($row->date_created)?></span>
									</td>
								</tr>
								<?php if(!empty($row->date_updated)):?>
								<tr class="wrap">
									<th class="text-muted">Last updated:</th>
									<td>
										<span><img src="<?=get_image($row->updated_by_image)?>" style="width: 20px; height: 20px; object-fit: cover; border-radius: 50%; margin: 0px;"></span> 
										<?=$row->updated_by_first_name . ' ' . $row->updated_by_last_name ?>
										<span class="badge bg-secondary text-white" style="font-size: 11px; font-weight:bolder;"><?=get_date_time($row->date_updated)?></span>
									</td>
								</tr>
							<?php endif;?>
							</tbody>
						</table>
					</div>
		        </div>
		        
		        <!-- Attendance Tab -->
		        <div class="tab-pane fade" id="attendance" role="tabpanel" aria-labelledby="attendance-tab">
		        	<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
					<div>
						<h4 class="mb-0">Attendance</h4>
						<div class="text-muted" style="font-size: 13px;">Last 30 days (<?=esc($__attKpis['from'] ?? '')?> to <?=esc($__attKpis['to'] ?? '')?>)</div>
					</div>
					<span class="badge bg-secondary">Records: <?= (int)($__attKpis['total'] ?? 0) ?></span>
				</div>

				<?php if(!$__hasAttendance):?>
					<div class="alert alert-warning">Attendance module not available.</div>
				<?php else:?>
					<div class="row g-3 mb-3">
						<div class="col-md-3">
							<div class="card shadow-sm">
								<div class="card-body">
									<div class="text-muted" style="font-size:12px;">Present</div>
									<div class="fw-bolder" style="font-size:22px;"><?= (int)($__attKpis['present'] ?? 0) ?></div>
								</div>
							</div>
						</div>
						<div class="col-md-3">
							<div class="card shadow-sm">
								<div class="card-body">
									<div class="text-muted" style="font-size:12px;">Late</div>
									<div class="fw-bolder" style="font-size:22px;"><?= (int)($__attKpis['late'] ?? 0) ?></div>
								</div>
							</div>
						</div>
						<div class="col-md-3">
							<div class="card shadow-sm">
								<div class="card-body">
									<div class="text-muted" style="font-size:12px;">Absent</div>
									<div class="fw-bolder" style="font-size:22px;"><?= (int)($__attKpis['absent'] ?? 0) ?></div>
								</div>
							</div>
						</div>
						<div class="col-md-3">
							<div class="card shadow-sm">
								<div class="card-body">
									<div class="text-muted" style="font-size:12px;">Other</div>
									<div class="fw-bolder" style="font-size:22px;"><?= (int)($__attKpis['other'] ?? 0) ?></div>
								</div>
							</div>
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
				<?php endif?>
		        </div>

		        <!-- Cashdrawers Tab -->
		        <div class="tab-pane fade" id="cashdrawers" role="tabpanel" aria-labelledby="cashdrawers-tab">
		        	<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
					<div>
						<h4 class="mb-0">Cashdrawers</h4>
						<div class="text-muted" style="font-size: 13px;">Last 30 days (<?=esc($__drawerKpis['from'] ?? '')?> to <?=esc($__drawerKpis['to'] ?? '')?>)</div>
					</div>
					<span class="badge bg-secondary">Entries: <?= (int)($__drawerKpis['total'] ?? 0) ?></span>
				</div>

				<?php if(!$__hasCashdrawers):?>
					<div class="alert alert-warning">Cashdrawer data not available.</div>
				<?php else:?>
					<div class="row g-3 mb-3">
						<div class="col-md-6">
							<div class="card shadow-sm">
								<div class="card-body">
									<div class="text-muted" style="font-size:12px;">Total amount (all currencies)</div>
									<div class="fw-bolder" style="font-size:22px;"><?=number_format((float)($__drawerKpis['last_30_total_amount'] ?? 0), 2)?></div>
									<div class="text-muted" style="font-size:12px;">This is a sum of the `amount` field for the period.</div>
								</div>
							</div>
						</div>
						<div class="col-md-6">
							<div class="card shadow-sm">
								<div class="card-body">
									<div class="text-muted" style="font-size:12px;">By currency</div>
									<?php if(empty($__drawerKpis['currency_breakdown'])):?>
										<div class="text-muted">—</div>
									<?php else:?>
										<?php foreach($__drawerKpis['currency_breakdown'] as $__cur => $__sum):?>
											<div class="d-flex justify-content-between">
												<span class="fw-semibold"><?=esc($__cur)?></span>
												<span><?=number_format((float)$__sum, 2)?></span>
											</div>
										<?php endforeach?>
									<?php endif?>
								</div>
							</div>
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
				<?php endif?>
		        </div>

		        <!-- Assigned SIMs Tab -->
		        <div class="tab-pane fade" id="assigned-sims" role="tabpanel" aria-labelledby="assigned-sims-tab">
		        	<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
					<div>
						<h4 class="mb-0">Assigned SIM Data</h4>
						<div class="text-muted" style="font-size: 13px;">SIM, wallet and bank details assigned to this profile</div>
					</div>
					<span class="badge bg-secondary">Entries: <?= is_array($userSimAssignments) ? count($userSimAssignments) : 0 ?></span>
				</div>

				<?php if(!$__hasAssignedSims):?>
					<div class="alert alert-warning">Assigned SIM data is not available.</div>
				<?php else:?>
					<div class="table-responsive">
						<table class="table table-striped table-hover align-middle table-sm">
							<thead>
								<tr>
									<th style="width:120px;">Type</th>
									<th style="width:140px;">Network</th>
									<th style="min-width:260px;">Details</th>
									<th style="min-width:180px;">Notes</th>
									<th style="width:150px;">Added</th>
								</tr>
							</thead>
							<tbody>
								<?php $assignedRows = is_array($userSimAssignments) ? $userSimAssignments : []; ?>
								<?php if(!empty($assignedRows)):?>
									<?php foreach($assignedRows as $assignment):?>
										<?php
											$details = [];
											if(!empty($assignment->phone_number)) $details[] = 'Phone: ' . $assignment->phone_number;
											if(!empty($assignment->till_number)) $details[] = 'Till: ' . $assignment->till_number;
											if(!empty($assignment->shortcode)) $details[] = 'Shortcode: ' . $assignment->shortcode;
											if(!empty($assignment->account_number)) $details[] = 'Account: ' . $assignment->account_number;
										?>
										<tr>
											<td><?=esc(ucfirst((string)($assignment->sim_type ?? '')))?></td>
											<td><span class="badge bg-primary"><?=esc($assignment->network ?? '')?></span></td>
											<td style="white-space:normal;overflow-wrap:anywhere;"><?=esc(implode(' | ', $details))?></td>
											<td style="white-space:normal;overflow-wrap:anywhere;"><?=esc($assignment->notes ?? '')?></td>
											<td><?=get_date($assignment->date_created ?? '')?></td>
										</tr>
									<?php endforeach?>
								<?php else:?>
									<tr>
										<td colspan="5" class="text-center text-muted py-4">No assigned SIM data saved for this profile.</td>
									</tr>
								<?php endif?>
							</tbody>
						</table>
					</div>
				<?php endif?>
		        </div>

		        <!-- Activity Tab -->
		        <div class="tab-pane fade" id="activity" role="tabpanel" aria-labelledby="activity-tab">
		          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
					<div>
						<h4 class="mb-0">Activity</h4>
						<div class="text-muted" style="font-size: 13px;">User actions and system logs</div>
					</div>
					<span class="badge bg-secondary">Logs: <?= is_array($__logRows) ? count($__logRows) : 0 ?></span>
				</div>

				<?php if(!$__hasLogs):?>
					<div class="alert alert-warning">Logs data not available.</div>
				<?php else:?>
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
				<?php endif?>
		        </div>
		      </div>
		    </div>
		  </div>
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

	<?php elseif(!empty($studentRow)):?>
			
		<div class="row g-3 col-lg-8 col-md-12 col-sm-12 mx-auto shadow p-4 rounded">
			

				<center>
					<?php if(message_success()):?>
						<div class="alert alert-success text-center">
							<?=esc(message_success('',true))?>
						</div>
					<?php endif?>
					<?php if(message_fail()):?>
						<div class="alert alert-danger text-center">
							<?=esc(message_fail('',true))?>
						</div>
					<?php endif?>

					<h4 class="fw-bolder"><i class="fas fa-user-shied"></i><br> <?=$studentRow->first_name . ' '. $studentRow->last_name?></h4>
					<p><?=$studentRow->index_number?> : <?=$studentRow->programme?> <br> <?=$studentRow->boarding_status?></p>
				</center>
			
			<label class="text-right">
				<img src="<?=get_image($studentRow->image, $studentRow->gender)?>" class="img-thumbnail" style="width:100%;max-width:200px;max-height: 200px;object-fit: cover;">
			</label>
			<div id="phase_2" class="bg-muted rounded row d-flex">
				<legend>PERSONAL DETAILS</legend>

				<div class="form-floating mb-3 col-md-6">
				  <div class="form-control" id="floatingInput"><?=esc($studentRow->first_name)?></div>
				  <label for="first_name" class="form-label">First Name</label>
				</div>

				<div class="form-floating mb-3 col-md-6">
				  <div class="form-control" id="floatingInput"><?=esc($studentRow->last_name)?></div>
				  <label for="last_name" class="form-label">Last Name</label>
				</div>
				
				<div class="form-floating mb-3 col-md-6">
				  <div class="form-control" id="floatingInput"><?=esc($studentRow->index_number)?></div>
				  <label for="floatingInput" class="form-label">Index Number</label>
				</div>
				
				<div class="form-floating mb-3 col-md-6">
				  <div class="form-control" id="floatingInput"><?=esc($studentRow->gender ? :'')?></div>
				  <label for="floatingInput" class="form-label">Gender</label>
				</div>

				<div class="form-floating mb-3 col-md-6">
					<div class="form-control" id="floatingInput"><?=get_date($studentRow->dob)?></div>
					<label for="email" class="form-label">Date of Birth</label>
				</div>

				<div class="form-floating mb-3 col-md-6">
					<div class="form-control" id="floatingInput"><?=esc($studentRow->age)?> years</div>
					<label for="email" class="form-label">Age</label>
				</div>

				<div class="form-floating mb-3 col-md-6">
					<div class="form-control" id="floatingInput"><?=esc($studentRow->residence)?></div>
					<label for="email" class="form-label">Residence</label>
				</div>

				<div class="form-floating mb-3 col-md-6">
					<div class="form-control" id="floatingInput"><?=esc($studentRow->hometown)?></div>
					<label for="email" class="form-label">Home Town</label>
				</div>

				<div class="form-floating mb-3 col-md-6">
					<div class="form-control" id="floatingInput"><?=esc($studentRow->region)?></div>
					<label for="email" class="form-label">Region</label>
				</div>

				<div class="form-floating mb-3 col-md-6">
					<div class="form-control" id="floatingInput"><?=esc($studentRow->address)?></div>
					<label for="email" class="form-label">Address</label>
				</div>

				<div class="form-floating mb-3 col-md-6">
					<div class="form-control" id="floatingInput"><?=esc($studentRow->postal_address)?></div>
					<label for="email" class="form-label">Postal Address</label>
				</div>

				<div class="form-floating mb-3 col-md-6">
					<div class="form-control" id="floatingInput"><?=esc($studentRow->languages_spoken)?></div>
					<label for="email" class="form-label">Languages Spoken</label>
				</div>
				
			</div>
				

			<div id="phase_2" class="bg-muted rounded row d-flex">
				<legend>PLACEMENT DETAILS</legend>	

				<div class="form-floating mb-3 col-md-6">
					<div class="form-control" id="floatingInput"><?=esc($studentRow->boarding_status ? : 'Not specified')?></div>
					<label for="email" class="form-label">Boarding Status</label>
				</div>	

				<div class="form-floating mb-3 col-md-6">
					<div class="form-control" id="floatingInput"><?=esc($studentRow->programme)?></div>
					<label for="email" class="form-label">Programme of Study</label>
				</div>
				
				<div class="form-floating mb-3 col-md-6">
					<div class="form-control" id="floatingInput"><?=esc($studentRow->class ? :'unknown')?></div>
					<label for="Class" class="form-label">Assigned Class</label>
				</div>

				<div class="form-floating mb-3 col-md-6">
					<div class="form-control" id="floatingInput"><?=esc($studentRow->house ? :'unknown')?></div>
					<label for="email" class="form-label">House</label>
				</div>		
				<div class="form-floating mb-3 col-md-6">
					<div class="form-control" id="floatingInput"><?=esc($studentRow->health_concern ? :'none')?></div>
					<label for="email" class="form-label">Any health Condition?</label>
				</div>
			
				<div class="form-floating mb-3 col-md-6">
				  <div class="form-control" id="floatingInput"><?=esc($studentRow->health_specific ? :'none')?></div>
				  <label for="floatingInput" class="form-label">Specific health condition:</label>
				</div>
			</div>

			<div id="phase_3" class="bg-muted rounded row d-flex">
				<legend>PREVIOUS SCHOOL'S INFORMATION</legend>				
			
				<div class="form-floating mb-3 col-md-6">
				  <div class="form-control" id="floatingInput"><?=esc($studentRow->prev_school_name ? :'none')?></div>
				  <label for="floatingInput" class="form-label">Name of Previous School:</label>
				</div>				
			
				<div class="form-floating mb-3 col-md-6">
				  <div class="form-control" id="floatingInput"><?=esc($studentRow->school_phone ? :'none')?></div>
				  <label for="floatingInput" class="form-label">Phone Number:</label>
				</div>				
			
				<div class="form-floating mb-3 col-md-6">
				  <div class="form-control" id="floatingInput"><?=esc($studentRow->year_completed ? :'none')?></div>
				  <label for="floatingInput" class="form-label">Year of Completion:</label>
				</div>				
			
				<div class="form-floating mb-3 col-md-6">
				  <div class="form-control" id="floatingInput"><?=esc($studentRow->school_address ? :'none')?></div>
				  <label for="floatingInput" class="form-label">School Address:</label>
				</div>
			</div>

			<div id="phase_3" class="bg-muted rounded row d-flex">
				<legend>CAREGIVER'S INFORMATION</legend>				
			
				<div class="form-floating mb-3 col-md-6">
				  <div class="form-control" id="floatingInput"><?=esc($studentRow->caregiver_first_name . ' ' . $studentRow->caregiver_last_name ? :'none')?></div>
				  <label for="floatingInput" class="form-label">Name:</label>
				</div>				
			
				<div class="form-floating mb-3 col-md-6">
				  <div class="form-control" id="floatingInput"><?=esc($studentRow->relation ? :'none')?></div>
				  <label for="floatingInput" class="form-label">Relationship:</label>
				</div>				
			
				<div class="form-floating mb-3 col-md-6">
				  <div class="form-control" id="floatingInput"><?=esc($studentRow->caregiver_phone ? :'none')?></div>
				  <label for="floatingInput" class="form-label">Phone:</label>
				</div>				
			
				<div class="form-floating mb-3 col-md-6">
				  <div class="form-control" id="floatingInput"><?=esc($studentRow->address ? :'none')?></div>
				  <label for="floatingInput" class="form-label">Address:</label>
				</div>
			</div>

			<div class="d-flex justify-content-between">
				<a href="<?=ROOT?>/<?=$admin_route?>">
					<button class="btn btn-primary">
						<i class="fa-solid fa-chevron-left"></i> Back
					</button>
				</a>
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$studentRow->id?>">
					<button class="btn btn-warning">
						<i class="fa-solid fa-pen-to-square"></i> Edit
					</button>
				</a>

				<button class="btn btn-secondary" onclick="print_invoice(this.id)" id="<?=$studentRow->index_number?>">
    			<i class="fas fa-print"></i> Print
    		</button>
    		
    		<button class="btn btn-secondary" onclick="print_admission(this.id)" id="<?=$studentRow->index_number?>">
    			<i class="fa fa-print"></i> Admission Letter
    		</button>

			</div>
		</div>

	<?php else:?>
		<div class="alert alert-danger text-center">
			That record was not found!
		</div>
		
		<a href="<?=ROOT?>/<?=$admin_route?>">
			<button class="btn btn-primary">Back</button>
		</a>
	<?php endif?>

<script>
  var RECEIPT_WINDOW = "";

	(function(){
		var profileUserId = <?= (int)($__profileUserId ?? 0) ?>;
		var adminRoute = "<?=esc($admin_route ?? 'admin')?>";
		var profileRoute = "<?=esc($plugin_route ?? 'profile')?>";
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

			document.getElementById('attPageLabel').textContent = 'Page ' + state.att.page + ' of ' + state.att.totalPages;
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

			document.getElementById('drPageLabel').textContent = 'Page ' + state.dr.page + ' of ' + state.dr.totalPages;
			var rows = data.rows || [];
			if(!rows.length){
				tb.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No cashdrawer entries found.</td></tr>';
				return;
			}
			function formatDenoStack(label, denos){
				denos = Array.isArray(denos) ? denos : [];
				if(!denos.length){
					return '<div class="text-muted" style="font-size:12px;">'+escHtml(label)+': —</div>';
				}
				var totalVol = denos.reduce(function(acc, d){ return acc + Number(d && d.volume ? d.volume : 0); }, 0);
				var top = denos.slice(0).sort(function(a,b){ return Number(b && b.volume ? b.volume : 0) - Number(a && a.volume ? a.volume : 0); }).slice(0, 3);
				var lines = top.map(function(d){
					var denom = Number(d && d.denomination ? d.denomination : 0);
					var cnt = Number(d && d.count ? d.count : 0);
					var vol = Number(d && d.volume ? d.volume : 0);
					var denomLabel = (label === 'CFA') ? denom.toLocaleString(undefined, {maximumFractionDigits:0}) : denom.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
					var volLabel = vol.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
					return '<div style="font-size:12px; line-height:1.2;">'+escHtml(label)+' '+escHtml(denomLabel)+' × '+escHtml(cnt)+' <span class="text-muted">('+escHtml(volLabel)+')</span></div>';
				}).join('');
				var totalLabel = totalVol.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
				return '<div class="mb-1" style="font-size:12px;"><span class="fw-semibold">'+escHtml(label)+':</span> <span class="text-muted">'+escHtml(totalLabel)+'</span></div>' + lines;
			}

			tb.innerHTML = rows.map(function(r){
				var amt = Number((r && r.amount) ? r.amount : 0).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
				var cur = (r && r.currency) ? String(r.currency) : '';
				var ghsStack = formatDenoStack('GHS', (r && r.ghs_denos) ? r.ghs_denos : []);
				var cfaStack = formatDenoStack('CFA', (r && r.cfa_denos) ? r.cfa_denos : []);
				var ghsTotal = Array.isArray(r && r.ghs_denos ? r.ghs_denos : null) ? (r.ghs_denos || []).reduce(function(a,d){return a + Number(d && d.volume ? d.volume : 0);}, 0) : 0;
				var cfaTotal = Array.isArray(r && r.cfa_denos ? r.cfa_denos : null) ? (r.cfa_denos || []).reduce(function(a,d){return a + Number(d && d.volume ? d.volume : 0);}, 0) : 0;
				var hasAnyFloat = (ghsTotal > 0) || (cfaTotal > 0);
				var floatHtml = '';
				if(hasAnyFloat){
					if(ghsTotal > 0){
						floatHtml += '<div class="fw-semibold">GHS ' + escHtml(ghsTotal.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})) + '</div>';
					}
					if(cfaTotal > 0){
						floatHtml += '<div class="fw-semibold">CFA ' + escHtml(cfaTotal.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})) + '</div>';
					}
				}else{
					floatHtml = '<div class="fw-semibold">'+escHtml((cur ? (cur + ' ') : '') + amt)+'</div>';
				}
				return '<tr>'+
					'<td><span class="badge bg-warning text-black">#'+escHtml(r.id || '—')+'</span></td>'+
					'<td>'+escHtml(formatDateHuman(r.date_created || '—'))+'</td>'+
					'<td>'+
						'<div class="d-flex flex-column gap-1">'+
							'<div class="p-2 rounded" style="background:rgba(13,110,253,.06);">'+ghsStack+'</div>'+
							'<div class="p-2 rounded" style="background:rgba(32,201,151,.06);">'+cfaStack+'</div>'+
						'</div>'+
					'</td>'+
					'<td class="text-end">'+floatHtml+'</td>'+
					'<td>'+escHtml(r.created_by || '—')+'</td>'+
					'<td class="text-end">'+
						'<button type="button" class="btn btn-outline-primary btn-sm js-open-drawer" data-drawer-id="'+escHtml(r.id)+'" title="View details">'+
							'<i class="fa-solid fa-eye"></i> <span class="d-none d-md-inline">View</span>'+
						'</button>'+
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

			document.getElementById('logPageLabel').textContent = 'Page ' + state.log.page + ' of ' + state.log.totalPages;
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

		// Initial load when page renders; also refresh when tabs are opened
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
		document.addEventListener('hidden.bs.modal', function(e){
			if(!e || !e.target || e.target.id !== 'cashdrawerModal') return;
			var content = document.getElementById('cashdrawerModalContent');
			if(content) content.innerHTML = '';
		});
	})();
  
  function print_invoice(buttonId) {
    const studentID = buttonId; // Use the button's ID
    RECEIPT_WINDOW = window.open(`<?=ROOT?>/print/${studentID}`, 'printpage', 'width=900px');
    // Close the window after 2000 milliseconds (2 seconds)
    setTimeout(close_receipt_window, 6000);
}

 function print_admission(buttonId) {
    const studentID = buttonId; // Use the button's ID
    RECEIPT_WINDOW = window.open(`<?=ROOT?>/admission_print/${studentID}`, 'printpage', 'width=900px');
    // Close the window after 2000 milliseconds (2 seconds)
    setTimeout(close_receipt_window, 6000);
}  

  function close_receipt_window()
  {
    RECEIPT_WINDOW.close();
  }

</script>

<?=get_app_image_viewer_inline_source()?>

<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>

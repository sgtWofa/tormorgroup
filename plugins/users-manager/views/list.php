<?php if(user_can('view_users')):?>
	<?php
		$stats = $stats ?? [];
		$roleOptions = $roleOptions ?? [];
		$searchValue = old_value('find', $searchTerm ?? '', 'get');
		$selectedRole = old_value('role', $selectedRole ?? '', 'get');
		$selectedLimit = (int)old_value('limit', 10, 'get');
		$selectedLimit = $selectedLimit > 0 ? $selectedLimit : 10;
		$selectedLimit = max(5, $selectedLimit);
		$rows = $rows ?? [];
		if(!is_array($rows)) $rows = [];
		$showDeleted = !empty($showDeleted);

		$roleBadgeClass = function(string $role): string {
			$key = strtolower(trim($role));
			$map = [
				'admin' => 'bg-danger',
				'operations manager' => 'bg-primary',
				'solo agent' => 'bg-success',
				'momo agent' => 'bg-warning text-dark',
			];
			return $map[$key] ?? 'bg-secondary';
		};
	?>
	<style>
		.users-page{background:var(--bs-body-bg);color:var(--bs-body-color);}
		.users-hero{
			border:1px solid var(--bs-border-color);
			border-radius:1.5rem;
			background:linear-gradient(135deg,rgba(var(--bs-primary-rgb,13,110,253),.1),rgba(var(--bs-warning-rgb,255,193,7),.1));
			box-shadow:0 25px 45px rgba(15,23,42,.08);
		}
		.users-actions .btn,.users-actions .dropdown-toggle{border-radius:999px;font-weight:500;}
		.users-stat-card{border-radius:1rem;border:1px solid rgba(15,23,42,.08);background:var(--bs-body-bg);padding:1.25rem;display:flex;justify-content:space-between;align-items:center;gap:1rem;}
		.users-stat-card h3{font-weight:700;margin-bottom:.25rem;}
		.users-stat-icon{width:44px;height:44px;border-radius:999px;background:rgba(var(--bs-primary-rgb,13,110,253),.12);color:var(--bs-primary);display:inline-flex;align-items:center;justify-content:center;}
		body[data-bs-theme="dark"] .users-hero .text-muted{color:rgba(255,255,255,.88)!important;font-weight:600;}
		body[data-bs-theme="dark"] .users-hero h1{color:#fef08a;font-weight:700;}
		body[data-bs-theme="dark"] .users-stat-icon{background:rgba(247,201,72,.18)!important;color:#f7c948!important;font-weight:700;}
		@media(max-width:767px){.users-hero{padding:1.25rem!important;}}
		@media (max-width: 992px){.users-hero{padding:1.5rem;}}
	</style>
	<section class="container-fluid py-3">
		<div class="users-page">
			<div class="users-hero mb-4 p-4">
				<div class="row align-items-center g-3">
					<div class="col-lg-8">
						<p class="text-uppercase text-muted mb-1 small fw-semibold">People operations</p>
						<h1 class="mb-2"><?=$showDeleted ? 'Deleted users' : 'Users directory'?></h1>
						<p class="mb-0 text-secondary"><?=$showDeleted ? 'Review removed profiles and restore them when needed. Historical transactions remain preserved.' : 'Track profiles, roles, and engagement signals in a single modern workspace.'?></p>
					</div>
					<div class="col-lg-4 text-lg-end">
						<div class="users-actions d-flex flex-wrap justify-content-lg-end gap-2">
							<?php if(user_can('delete_user')):?>
								<?php if($showDeleted):?>
									<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">Active users</a>
								<?php else:?>
									<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/deleted" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">Deleted users</a>
								<?php endif;?>
							<?php endif;?>
							<?php if(user_can('add_user')):?>
								<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/add" class="btn btn-warning text-dark d-inline-flex align-items-center gap-2">
									<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-plus">
										<path d="M15 14c2.761 0 5 2.239 5 5" />
										<path d="M20 19h-6" />
										<path d="M17 16v6" />
										<circle cx="9" cy="7" r="4" />
										<path d="M3 21v-1a6 6 0 0 1 6-6h0a6 6 0 0 1 6 6v1" />
									</svg>
									Add user
								</a>
							<?php endif;?>
						</div>
					</div>
				</div>

				<?php if(!empty($stats)):?>
					<div class="row g-3 mt-1">
						<?php foreach($stats as $index => $stat):?>
							<?php
								$colClass = 'col-12 col-sm-6 col-lg-4';
								if((int)$index === 2){
									$colClass = 'col-12 col-lg-4';
								}
								$iconStyle = '';
								if((int)$index === 1){
									$iconStyle = ' style="background:rgba(var(--bs-success-rgb,25,135,84),.12);color:var(--bs-success);"';
								}else if((int)$index === 2){
									$iconStyle = ' style="background:rgba(var(--bs-warning-rgb,255,193,7),.2);color:var(--bs-warning);"';
								}
							?>
							<div class="<?=$colClass?>">
								<div class="users-stat-card">
									<div>
										<p class="text-uppercase small text-muted mb-1"><?=esc($stat['label'])?></p>
										<h3 class="fw-bolder fw-semibold mb-1"><?=number_format($stat['value'])?></h3>
										<small class="text-muted"><?=esc($stat['subLabel'])?> · <?=number_format($stat['subValue'])?></small>
									</div>
									<span class="users-stat-icon"<?=$iconStyle?>>
										<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-4"/></svg>
									</span>
								</div>
							</div>
						<?php endforeach;?>
					</div>
				<?php endif;?>
			</div>

		<div class="card border-0 shadow-sm mb-4">
			<div class="card-body">
				<form id="usersFiltersForm" class="d-flex flex-nowrap gap-2 align-items-end" method="get" style="overflow:auto;">
					<?php if($showDeleted):?>
						<input type="hidden" name="deleted" value="1">
					<?php endif;?>
					<div class="flex-grow-1 input-group" style="min-width: 160px;">
						<input type="text" name="find" value="<?=esc($searchValue)?>" class="form-control" placeholder="Name, email or phone" autocomplete="off">
						 <button class="btn btn-warning input-group-text">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search me-1">
                                <circle cx="11" cy="11" r="8" />
                                <path d="m21 21-4.35-4.35" />
                            </svg>
                        </button>
					</div>
					<div style="width: fit-content; min-width: 150px;">
						<label class="form-label fw-semibold">Filter by role</label>
						<select name="role" class="form-select">
							<option value="">All roles</option>
							<?php foreach($roleOptions as $role):?>
								<option value="<?=esc($role->role)?>" <?=$selectedRole === ($role->role ?? '') ? 'selected' : ''?>><?=esc($role->role)?></option>
							<?php endforeach;?>
						</select>
					</div>
					<div style="width: fit-content; min-width: 60px;">
						<label class="form-label fw-semibold">Items</label>
						<select name="limit" class="form-select">
							<?php foreach([5,10,20,25,50,100] as $opt):?>
								<option value="<?=$opt?>" <?=$selectedLimit === $opt ? 'selected' : ''?>><?=$opt?></option>
							<?php endforeach;?>
						</select>
					</div>
					<div class="d-flex gap-2" style="width: fit-content; white-space: nowrap;">
						<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?><?=$showDeleted ? '/deleted' : ''?>" class="btn btn-outline-secondary" title="Reset filters">Reset</a>
					</div>
				</form>
			</div>
		</div>

		<div class="card border-0 shadow-sm">
			<div class="card-body position-relative">
				<div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
					<div>
						<p class="text-muted mb-0 small" id="usersMeta">Page <?=$pager->page_number?> • <?=count($rows ?? [])?> results</p>
						<h5 class="mb-0"><?=$showDeleted ? 'Deleted roster' : 'User roster'?></h5>
					</div>
					<div class="text-muted small">Sorted by latest created</div>
				</div>

				<div id="usersLoader" class="position-absolute top-0 start-0 w-100 h-100 d-none" style="z-index: 5; background: rgba(0,0,0,.08);">
					<div class="position-absolute top-50 start-50 translate-middle text-center">
						<div class="spinner-border text-warning" role="status" aria-hidden="true"></div>
						<div class="small text-muted mt-2">Loading...</div>
					</div>
				</div>

				<div class="d-lg-none">
					<?php if(!empty($rows)): $id = ($pager->offset ?? 0) + 1;?>
						<div class="d-grid gap-3" id="usersCards">
							<?php foreach($rows as $row):?>
								<?php $isDeleted = (int)($row->deleted ?? 0) === 1; $isDisabled = (int)($row->disabled ?? 0) === 1; ?>
								<div class="card border-0 shadow-sm">
									<div class="card-body">
										<div class="row g-3 align-items-center">
											<div class="col-12 col-sm-4">
												<div class="d-flex align-items-center gap-2">
													<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>" class="text-decoration-none">
														<img src="<?=get_image($row->image, $row->gender)?>" data-app-image-viewer data-app-image="<?=get_image($row->image, $row->gender)?>" alt="<?=esc($row->first_name)?>" class="rounded-circle" width="44" height="44" style="object-fit: cover;cursor:pointer;">
													</a>
													<div>
														<div class="fw-semibold">
															<?=esc($row->first_name . ' ' . $row->last_name)?>
															<span class="badge <?=$isDeleted ? 'bg-warning text-dark' : ($isDisabled ? 'bg-danger' : 'bg-success')?> ms-1"><?=$isDeleted ? 'Deleted' : ($isDisabled ? 'Disabled' : 'Active')?></span>
														</div>
														<div class="text-muted small">Branch: <?=esc(trim((string)($row->branch_name ?? '')) ?: 'Unassigned')?></div>
														<?php if(!empty($row->phone)):?>
															<div class="text-muted small"><?=esc($row->phone)?></div>
														<?php endif;?>
														<?php if(!empty($row->email)):?>
															<div class="text-muted small"><?=esc($row->email)?></div>
														<?php endif;?>
													</div>
												</div>
											</div>
											<div class="col-12 col-sm-4 text-sm-end">
												<?php if(!empty($row->roles)):?>
													<div class="d-flex flex-wrap gap-1 justify-content-sm-end">
														<?php foreach($row->roles as $role):?>
															<span class="badge <?=$roleBadgeClass((string)$role)?> border"><?=esc($role)?></span>
														<?php endforeach;?>
													</div>
												<?php else:?>
													<span class="text-muted small">No roles</span>
												<?php endif;?>
											</div>
											<div class="col-12 col-sm-4 text-sm-end">
												<div class="dropdown d-inline-block">
													<?php $session = get_session(); $userId = $session->id ?? null; ?>
													<button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButtonSm<?=$row->id?>" data-bs-toggle="dropdown" aria-expanded="false">
														Actions
													</button>
													<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButtonSm<?=$row->id?>">
														<?php if(user_can('view_profile') || $userId === $row->id):?>
															<li><a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>">Profile</a></li>
														<?php endif;?>
														<?php if(!$isDeleted && (user_can('edit_user') || $userId === $row->id)):?>
															<li><a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$row->id?>">Edit user</a></li>
														<?php endif;?>
														<?php if(!$isDeleted && (user_can('reset_password') || $userId === $row->id)):?>
															<li><a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/resetpassword/<?=$row->id?>">Reset password</a></li>
														<?php endif;?>
														<?php if($isDeleted && user_can('delete_user')):?>
															<li><a class="dropdown-item text-success" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/restore/<?=$row->id?>">Restore user</a></li>
														<?php elseif(user_can('delete_user') || $userId === $row->id):?>
															<li><a class="dropdown-item text-danger" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/delete/<?=$row->id?>">Delete</a></li>
														<?php endif;?>
													</ul>
												</div>
											</div>
										</div>
									</div>
								</div>
							<?php endforeach;?>
						</div>
					<?php else:?>
						<div class="text-center py-5" id="usersCards">
							<div class="text-muted">No <?=$showDeleted ? 'deleted ' : ''?>users match the current filters.</div>
						</div>
					<?php endif;?>
				</div>

				<div class="table-responsive d-none d-lg-block">
					<table class="table align-middle table-hover mb-0">
						<thead class="">
							<tr>
								<th scope="col">#</th>
								<th scope="col">Profile</th>
								<th scope="col">Contact</th>
								<th scope="col">Roles</th>
								<th scope="col">Created</th>
								<th scope="col">Updated</th>
								<th scope="col" class="text-end">Actions</th>
							</tr>
						</thead>
						<tbody id="usersTableBody">
							<?php if(!empty($rows)): $id = ($pager->offset ?? 0) + 1;?>
								<?php foreach($rows as $row):?>
									<?php $isDeleted = (int)($row->deleted ?? 0) === 1; $isDisabled = (int)($row->disabled ?? 0) === 1; ?>
									<tr>
										<td><?=$id++;?></td>
										<td>
											<div class="d-flex align-items-center gap-2">
												<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>" class="text-decoration-none">
													<img src="<?=get_image($row->image, $row->gender)?>" data-app-image-viewer data-app-image="<?=get_image($row->image, $row->gender)?>" alt="<?=esc($row->first_name)?>" class="rounded-circle" width="48" height="48" style="object-fit: cover;cursor:pointer;">
												</a>
												<div>
													<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>" class="fw-semibold text-decoration-none"><?=esc($row->first_name . ' ' . $row->last_name)?></a>
													<span class="badge <?=$isDeleted ? 'bg-warning text-dark' : ($isDisabled ? 'bg-danger' : 'bg-success')?> ms-1"><?=$isDeleted ? 'Deleted' : ($isDisabled ? 'Disabled' : 'Active')?></span>
													<div class="text-muted small">Branch: <?=esc(trim((string)($row->branch_name ?? '')) ?: 'Unassigned')?></div>
													<p class="text-muted small mb-0"><?=esc(ucfirst($row->gender ?? ''))?></p>
												</div>
											</div>
										</td>
										<td>
											<div class="text-muted small"><?=esc($row->phone)?></div>
											<div><?=esc($row->email)?></div>
										</td>
										<td>
											<?php if(!empty($row->roles)):?>
												<div class="d-flex flex-wrap gap-1">
													<?php foreach($row->roles as $role):?>
														<span class="badge <?=$roleBadgeClass((string)$role)?> border"><?=esc($role)?></span>
													<?php endforeach;?>
												</div>
											<?php else:?>
												<span class="text-muted small">No roles</span>
											<?php endif;?>
										</td>
										<td><?=get_date($row->date_created)?></td>
										<td><?=get_date($row->date_updated)?></td>
										<td class="text-end">
											<div class="dropdown">
												<?php $session = get_session(); $userId = $session->id ?? null; ?>
												<button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton<?=$row->id?>" data-bs-toggle="dropdown" aria-expanded="false">
													Actions
												</button>
												<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton<?=$row->id?>">
													<?php if(user_can('view_profile') || $userId === $row->id):?>
														<li><a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>">Profile</a></li>
													<?php endif;?>
													<?php if(!$isDeleted && (user_can('edit_user') || $userId === $row->id)):?>
														<li><a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$row->id?>">Edit user</a></li>
													<?php endif;?>
													<?php if(!$isDeleted && (user_can('reset_password') || $userId === $row->id)):?>
														<li><a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/resetpassword/<?=$row->id?>">Reset password</a></li>
													<?php endif;?>
													<?php if($isDeleted && user_can('delete_user')):?>
														<li><a class="dropdown-item text-success" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/restore/<?=$row->id?>">Restore user</a></li>
													<?php elseif(user_can('delete_user') || $userId === $row->id):?>
														<li><a class="dropdown-item text-danger" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/delete/<?=$row->id?>">Delete</a></li>
													<?php endif;?>
												</ul>
											</div>
										</td>
									</tr>
								<?php endforeach;?>
							<?php else:?>
								<tr>
									<td colspan="7" class="text-center py-5">
										<div class="text-muted">No <?=$showDeleted ? 'deleted ' : ''?>users match the current filters.</div>
									</td>
								</tr>
							<?php endif;?>
						</tbody>
					</table>
				</div>
				<div class="mt-3">
					<div id="usersPagination"></div>
				</div>

				<script>
					(function(){
						const form = document.getElementById('usersFiltersForm');
						const cards = document.getElementById('usersCards');
						const tableBody = document.getElementById('usersTableBody');
						const pagination = document.getElementById('usersPagination');
						const meta = document.getElementById('usersMeta');
						const loader = document.getElementById('usersLoader');
						if(!form || !pagination) return;

						const baseUrl = '<?=ROOT?>/<?=esc($admin_route)?>/<?=esc($plugin_route)?>/ajax';
						let debounceTimer = null;
						let isLoading = false;

						function toParams(page){
							const fd = new FormData(form);
							const params = new URLSearchParams();
							for(const [k,v] of fd.entries()){
								if(v !== null && String(v).trim() !== '') params.set(k, String(v));
							}
							params.set('page', String(page || 1));
							let limit = parseInt(params.get('limit') || '10', 10);
							if(!isFinite(limit) || limit < 5) limit = 10;
							params.set('limit', String(Math.max(5, limit)));
							return params;
						}

						function updateUrl(params){
							try{
								const next = new URL(window.location.href);
								next.search = params.toString();
								window.history.replaceState({}, '', next.toString());
							}catch(e){}
						}

						function setLoading(on){
							if(!loader) return;
							if(on){
								loader.classList.remove('d-none');
							}else{
								loader.classList.add('d-none');
							}
						}

						async function loadPage(page){
							if(isLoading) return;
							isLoading = true;
							const params = toParams(page);
							try{
								setLoading(true);
								pagination.style.opacity = '0.6';
								const requestUrl = baseUrl + '?' + params.toString();
								console.log('[users-ajax] GET', requestUrl);
								const res = await fetch(requestUrl, {headers:{'Accept':'application/json'}});
								const contentType = (res.headers && res.headers.get) ? (res.headers.get('content-type') || '') : '';
								//console.log('[users-ajax] status', res.status, res.statusText, 'content-type:', contentType);
								const rawText = await res.text();
								//console.log('[users-ajax] raw (first 800 chars):', rawText.slice(0, 800));
								let data = null;
								try{
									data = JSON.parse(rawText);
								}catch(parseErr){
									//console.log('[users-ajax] JSON parse error:', parseErr);
									return;
								}
								//console.log('[users-ajax] parsed:', data);
								if(!data || !data.success) return;

								if(cards){
									cards.innerHTML = data.cardsHtml && String(data.cardsHtml).trim() !== '' ? data.cardsHtml : '<div class="text-center py-5"><div class="text-muted">No users match the current filters.</div></div>';
								}
								if(tableBody){
									tableBody.innerHTML = data.tableHtml && String(data.tableHtml).trim() !== '' ? data.tableHtml : '<tr><td colspan="7" class="text-center py-5"><div class="text-muted">No users match the current filters.</div></td></tr>';
								}
								pagination.innerHTML = data.paginationHtml || '';
								if(meta){
									meta.textContent = 'Page ' + (data.page || 1) + ' • ' + (data.totalRows || 0) + ' results';
								}
								updateUrl(params);
							}catch(e){
								console.log('[users-ajax] request failed:', e);
							}finally{
								pagination.style.opacity = '';
								isLoading = false;
								setLoading(false);
							}
						}

						function schedule(){
							clearTimeout(debounceTimer);
							debounceTimer = setTimeout(function(){
								loadPage(1);
							}, 1000);
						}

						const findInput = form.querySelector('input[name="find"]');
						const roleSelect = form.querySelector('select[name="role"]');
						const limitSelect = form.querySelector('select[name="limit"]');
						if(findInput) findInput.addEventListener('input', schedule);
						if(roleSelect) roleSelect.addEventListener('change', schedule);
						if(limitSelect) limitSelect.addEventListener('change', schedule);

						form.addEventListener('submit', function(e){
							e.preventDefault();
							loadPage(1);
						});

						pagination.addEventListener('click', function(e){
							const a = e.target && e.target.closest ? e.target.closest('a[data-page]') : null;
							if(!a) return;
							e.preventDefault();
							const p = parseInt(a.getAttribute('data-page') || '1', 10);
							loadPage(isFinite(p) && p > 0 ? p : 1);
						});

						const initialPage = parseInt(new URLSearchParams(window.location.search).get('page') || '1', 10);
						loadPage(isFinite(initialPage) && initialPage > 0 ? initialPage : 1);
					})();
				</script>
<?=get_app_image_viewer_inline_source()?>
			</div>
		</div>
	</section>
<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You don't have permission for this action
	</div>
<?php endif?>

<?php

if(!user_can('view_error_reports')){
	echo '<div class="alert alert-danger text-center">Access denied.</div>';
	return;
}

$vars = get_value();
$admin_route = $vars['admin_route'];
$plugin_route = $vars['plugin_route'];

if(empty($row)){
	echo '<div class="alert alert-warning text-center">Report not found</div>';
	return;
}

$severity = strtoupper((string)($row->severity ?? 'ERROR'));
$status = strtoupper((string)($row->status ?? 'OPEN'));

$prev_id = null;
$next_id = null;
try{
	$dbNav = new \Core\Database;
	$curDate = (string)($row->date_created ?? '');
	$curId = (int)($row->id ?? 0);

	// List ordering is: date_created DESC, id DESC
	// Prev = newer (appears above in the list)
	$prevRow = $dbNav->query(
		"SELECT id FROM error_reports WHERE deleted = 0 AND (date_created > :d OR (date_created = :d AND id > :id)) ORDER BY date_created ASC, id ASC LIMIT 1",
		['d' => $curDate, 'id' => $curId]
	);
	if(!empty($prevRow)){
		$prev_id = (int)($prevRow[0]->id ?? 0);
		if($prev_id <= 0) $prev_id = null;
	}

	// Next = older (appears below in the list)
	$nextRow = $dbNav->query(
		"SELECT id FROM error_reports WHERE deleted = 0 AND (date_created < :d OR (date_created = :d AND id < :id)) ORDER BY date_created DESC, id DESC LIMIT 1",
		['d' => $curDate, 'id' => $curId]
	);
	if(!empty($nextRow)){
		$next_id = (int)($nextRow[0]->id ?? 0);
		if($next_id <= 0) $next_id = null;
	}
}catch(\Throwable $e){
}

?>

<style>
	:root{
		--er-surface: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(248,250,252,0.95) 35%, rgba(255,255,255,0.92) 100%);
		--er-surface-2: linear-gradient(135deg, rgba(255,255,255,0.92) 0%, rgba(245,247,255,0.92) 40%, rgba(255,255,255,0.9) 100%);
		--er-border: rgba(0,0,0,0.10);
		--er-text: #111827;
		--er-muted: rgba(17,24,39,0.65);
		--er-pre-bg: rgba(248,250,252,0.9);
	}
	html[data-bs-theme="dark"]{
		--er-surface: linear-gradient(135deg, rgba(17,24,39,0.92) 0%, rgba(2,6,23,0.88) 55%, rgba(17,24,39,0.92) 100%);
		--er-surface-2: linear-gradient(135deg, rgba(15,23,42,0.92) 0%, rgba(2,6,23,0.88) 55%, rgba(15,23,42,0.92) 100%);
		--er-border: rgba(255,255,255,0.14);
		--er-text: rgba(255,255,255,0.92);
		--er-muted: rgba(255,255,255,0.62);
		--er-pre-bg: rgba(2,6,23,0.35);
	}
	html[data-bs-theme="dark"] .text-muted{color: var(--er-muted) !important;}
	html[data-bs-theme="dark"] .btn-outline-secondary{border-color: rgba(255,255,255,0.24); color: rgba(255,255,255,0.86);}
	html[data-bs-theme="dark"] .btn-outline-secondary:hover{background: rgba(255,255,255,0.10);}

	.er-head{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;flex-wrap:wrap;}
	.er-sub{color:#6c757d;font-size:0.9rem;}
	.er-pill{border-radius:999px;padding:0.25rem 0.6rem;font-weight:900;display:inline-block;}
	.er-card{border:1px solid var(--er-border);border-radius:14px;background: var(--er-surface-2);box-shadow: 0 10px 24px rgba(0,0,0,0.06);}
	.er-card .card-body{padding:14px;}
	.er-kv{display:grid;grid-template-columns:1fr;gap:10px;}
	.er-kv .k{color:#6c757d;font-size:0.82rem;font-weight:800;}
	.er-kv .v{font-weight:900;word-break:break-word;}
	.er-pre{white-space:pre-wrap;margin:0;}
	.er-pre-dark{white-space:pre;overflow:auto;margin:0;}
	.er-pre-box{background: var(--er-pre-bg) !important;}
	.er-message{
		border: 1px solid var(--er-border);
		border-left: 4px solid #0d6efd;
		border-radius: 14px;
		background: var(--er-surface);
		box-shadow: 0 12px 28px rgba(0,0,0,0.07);
	}
	.er-message .er-pre-box{
		border: 1px solid rgba(13,110,253,0.18);
		border-radius: 12px;
	}
	html[data-bs-theme="dark"] .er-message .er-pre-box{
		border-color: rgba(13,110,253,0.30);
	}
</style>

<div class="container-fluid">
	<div class="er-head mb-3">
		<div>
			<h3 class="m-0 fw-bolder">Error Report #<?= (int)$row->id ?></h3>
			<div class="er-sub"><?= htmlspecialchars((string)($row->title ?? '')) ?></div>
			<div class="mt-2">
				<span class="badge <?= $severity==='FATAL'?'bg-danger':($severity==='ERROR'?'bg-warning text-dark':($severity==='WARN'?'bg-info text-dark':'bg-secondary')) ?> er-pill">
					<?= htmlspecialchars($severity) ?>
				</span>
				<span class="badge ms-1 <?= $status==='RESOLVED'?'bg-success':'bg-primary' ?> er-pill">
					<?= htmlspecialchars($status) ?>
				</span>
			</div>
		</div>
		<div class="d-flex align-items-center gap-2 flex-wrap">
			<a class="btn btn-outline-danger btn-sm" href="<?= ROOT . '/' . $admin_route . '/' . $plugin_route ?>">Back</a>
			<?php if(!empty($prev_id)): ?>
				<a class="btn btn-outline-primary btn-sm" href="<?= ROOT . '/' . $admin_route . '/' . $plugin_route . '/view/' . (int)$prev_id ?>">Prev</a>
			<?php else: ?>
				<button type="button" class="btn btn-outline-primary btn-sm" disabled>Prev</button>
			<?php endif; ?>
			<?php if(!empty($next_id)): ?>
				<a class="btn btn-primary btn-sm" href="<?= ROOT . '/' . $admin_route . '/' . $plugin_route . '/view/' . (int)$next_id ?>">Next</a>
			<?php else: ?>
				<button type="button" class="btn btn-primary btn-sm" disabled>Next</button>
			<?php endif; ?>
		</div>
	</div>

	<div class="row g-3">
		<div class="col-lg-8">
			<div class="er-card">
				<div class="card-body">
					<div class="mb-3 er-message p-3">
						<div class="fw-bolder mb-2">Message</div>
						<pre class="er-pre-box p-3 rounded er-pre"><?= htmlspecialchars((string)($row->message ?? '')) ?></pre>
					</div>

					<?php if(!empty($row->stack)): ?>
						<div class="mb-3">
							<div class="fw-bolder">Stack trace</div>
							<pre class="bg-dark text-light p-3 rounded er-pre-dark"><?= htmlspecialchars((string)$row->stack) ?></pre>
						</div>
					<?php endif; ?>

					<?php if(!empty($row->context_json)): ?>
						<div class="mb-0">
							<div class="fw-bolder">Context</div>
							<pre class="er-pre-box p-3 rounded er-pre-dark" style="white-space:normal;"><?= htmlspecialchars((string)$row->context_json) ?></pre>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="col-lg-4">
			<div class="er-card">
				<div class="card-body">
					<div class="er-kv">
						<div>
							<div class="k">User</div>
							<div class="v"><?= htmlspecialchars((string)($row->user_name ?? '')) ?></div>
						</div>
						<div>
							<div class="k">Source</div>
							<div class="v"><?= htmlspecialchars((string)($row->source ?? '')) ?></div>
						</div>
						<div>
							<div class="k">URL</div>
							<div class="v"><?= htmlspecialchars((string)($row->url ?? '')) ?></div>
						</div>
						<div>
							<div class="k">Created</div>
							<div class="v"><?= htmlspecialchars(get_date_time($row->date_created ?? '')) ?></div>
						</div>
						<div>
							<div class="k">Resolved</div>
							<div class="v"><?= htmlspecialchars((string)($row->resolved_at ?? '—')) ?></div>
						</div>
					</div>

					<?php if(user_can('manage_error_reports')): ?>
						<hr>
						<form method="post" class="d-grid gap-2">
							<?= csrf() ?>
							<input type="hidden" name="id" value="<?= (int)$row->id ?>">
							<?php if($status === 'RESOLVED'): ?>
								<button name="action" value="reopen" class="btn btn-warning">Reopen</button>
							<?php else: ?>
								<button name="action" value="resolve" class="btn btn-success">Mark Resolved</button>
							<?php endif; ?>
							<button name="action" value="delete" class="btn btn-outline-danger" onclick="return erConfirmDelete(this.form);">Delete</button>
						</form>
						<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
						<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
						<script>
							function erIsDarkTheme(){
								try{
									var theme = (document.documentElement && document.documentElement.getAttribute('data-bs-theme')) || '';
									if(!theme && document.body){ theme = document.body.getAttribute('data-bs-theme') || ''; }
									theme = String(theme || '').toLowerCase();
									if(theme === 'dark') return true;
									if(theme === 'light') return false;
									if(theme === 'auto' || theme === ''){
										if(window.matchMedia){
											return window.matchMedia('(prefers-color-scheme: dark)').matches;
										}
									}
									return false;
								}catch(e){
									return false;
								}
							}
							function erSwalThemeClass(){
								try{
									var isDark = erIsDarkTheme();
									return isDark ? 'bg-dark text-light' : 'bg-body text-body';
								}catch(e){
									return 'bg-body text-body';
								}
							}
							function erSwalBg(){
								try{
									var isDark = erIsDarkTheme();
									return isDark ? '#0b1220' : '#ffffff';
								}catch(e){
									return '#ffffff';
								}
							}
							function erSwalColor(){
								try{
									var isDark = erIsDarkTheme();
									return isDark ? '#f8fafc' : '#111827';
								}catch(e){
									return '#111827';
								}
							}
							function erConfirmDelete(form){
								try{
									if(typeof Swal === 'undefined' || !Swal || !Swal.fire){
										return confirm('Delete this report?');
									}
									Swal.fire({
										icon: 'warning',
										title: 'Delete report?',
										html: 'This action cannot be undone.',
										showCancelButton: true,
										confirmButtonText: 'Delete',
										cancelButtonText: 'Cancel',
										buttonsStyling: false,
										reverseButtons: true,
										customClass: {
											confirmButton: 'btn btn-danger',
											cancelButton: 'btn btn-outline-secondary ms-2',
											popup: erSwalThemeClass(),
										},
										background: erSwalBg(),
										color: erSwalColor(),
									}).then(function(result){
										if(result && result.isConfirmed){
											try{ form.submit(); }catch(e){}
										}
									});
									return false;
								}catch(e){
									return confirm('Delete this report?');
								}
							}
						</script>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>

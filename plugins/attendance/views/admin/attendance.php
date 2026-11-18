<?php
	$__att_session = function_exists('get_session') ? get_session() : (object)[];
	$__att_generated_by = '';
	if(is_object($__att_session)){
		$fn = isset($__att_session->first_name) ? (string)$__att_session->first_name : '';
		$ln = isset($__att_session->last_name) ? (string)$__att_session->last_name : '';
		$__att_generated_by = trim($fn . ' ' . $ln);
	}
?>
<script>
	window.ATT_GENERATED_BY = <?= json_encode($__att_generated_by) ?>;
	window.ATT_LIB_URLS = {
		chart: 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
		jspdf: 'https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js',
		autotable: 'https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.2/dist/jspdf.plugin.autotable.min.js',
		xlsx: 'https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js'
	};
</script>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<!-- jQuery (needed for Select2) -->
<script defer src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Select2 JS -->
<script defer src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
    [data-bs-theme="light"] {
      --text-color: #000;
    }

    [data-bs-theme="dark"] {
      --text-color: #fff;
    }
    tbody tr, thead tr, tfoot tr { white-space: nowrap; }
    td.wrap { white-space: normal; }
    a { text-decoration: none; }
    /* Row layout for controls */
    .control-row { 
        margin-top: 1rem;
        border-bottom: 2px solid yellow;
        margin-bottom: 5px;
        margin-bottom: 1rem;
     }
    /* Cards container arranged vertically */
    #attendanceCards { margin-top: 1.5rem; }
    .card { border-radius: 0.5rem; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 1rem; }
    .card-header { font-size: 1.25rem; font-weight: bold; text-align: center; }
    /* Summary card styles */
    #attendanceSummary { 
        margin-top: 1.5rem; 
        background-color: rgba(255, 165,   0, 0.5) ;
        padding: 1rem;
        border-radius: 5px;
    }
    /* Preloader */
    .preloader {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        display: flex;
        gap: 5px;
        font-size: 2rem;
        font-weight: bold;
        z-index: 1000;
        color: #000;
        visibility: hidden;
        opacity: 0;
        transition: opacity 0.3s ease-in-out;
    }
    .preloader.visible { visibility: visible; opacity: 1; }
    .word { animation: color-change 1s infinite; }
    .word:nth-child(1) { animation-delay: 0.1s; }
    .word:nth-child(2) { animation-delay: 0.2s; }
    .word:nth-child(3) { animation-delay: 0.3s; }
    .word:nth-child(4) { animation-delay: 0.4s; }
    .word:nth-child(5) { animation-delay: 0.5s; }
    .word:nth-child(6) { animation-delay: 0.6s; }
    .word:nth-child(7) { animation-delay: 0.7s; }
    @keyframes color-change {
        0% { color: #3498db; }
        25% { color: #e74c3c; }
        50% { color: #f1c40f; }
        75% { color: #2ecc71; }
        100% { color: #3498db; }
    }

    /* Enhanced layout for side-by-side attendance cards */
    .attendance-row {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .attendance-card {
        flex: 1;
        min-width: 48%;
        border-radius: 0.75rem;
        box-shadow: 0 0 10px rgba(0,0,0,0.05);
        overflow-x: auto;
        transition: transform 0.2s ease-in-out;
    }

    @media (max-width: 960px) {
        .attendance-row {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .attendance-card {
            flex: 1;
            min-width: 90%;
            border-radius: 0.75rem;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
            overflow-x: auto;
            transition: transform 0.2s ease-in-out;
        }

        #miniAttendanceSummary{
            display: none;
        }
    }
    .attendance-card:hover {
        transform: scale(1.01);
    }

    .attendance-card .card-header {
        padding: 0.75rem 1rem;
        font-weight: bold;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .card-marked .card-header {
        background-color: #28a745;
        color: #fff;
    }

    .card-unmarked .card-header {
        background-color: #dc3545;
        color: #fff;
    }

    .attendance-card .card-body {
        padding: 1rem;
    }

    .attendance-icon {
        font-size: 1.5rem;
    }

    .attendance-table img {
        width: 28px;
        height: 28px;
        object-fit: cover;
        border-radius: 50%;
        margin-right: 5px;
    }

    .attendance-table td, .attendance-table th {
        vertical-align: middle;
    }

    /* Grouped attendance: side-by-side per date */
    .grouped-row {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    /* Summary table compact layout */
    #attendanceSummary .card-body {
        padding: 1rem;
    }
    #attendanceSummary .summary-table {
        width: 100%;
        text-align: center;
        border-collapse: collapse;
    }
    #attendanceSummary .summary-table th,
    #attendanceSummary .summary-table td {
        padding: 0.5rem;
    }
    #attendanceSummary .summary-circle {
        display: inline-block;
        width: 2rem;
        height: 2rem;
        border-radius: 50%;
        line-height: 2rem;
        color: #fff;
    }

    /*mini summary*/
    .attendance-summary-mini {
        font-size: 0.85rem;
        margin-bottom: 0.5rem;
    }

    .summary-table {
        border-collapse: collapse;
    }

    .summary-table th,
    .summary-table td {
        padding: 0.25rem 0.5rem;
        text-align: center;
    }

    .summary-circle {
        display: inline-block;
        min-width: 30px;
        height: 30px;
        border-radius: 50%;
        background-color: gray;
        color: #fff;
        font-weight: bold;
        line-height: 30px;
        font-size: 0.8rem;
    }

    .summary-circle.present {
        background-color: #28a745;
    }

    .summary-circle.absent {
        background-color: #dc3545;
    }

    .summary-circle.total {
        background-color: #007bff;
    }

    .attendance-shell{
        max-width: 1400px;
    }

    .attendance-hero{
        background: linear-gradient(135deg, rgba(255,193,7,0.18), rgba(13,110,253,0.10));
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 14px;
        padding: 16px;
    }

    .attendance-hero h1{
        letter-spacing: 0.5px;
    }

    .attendance-kpis{
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-top: 12px;
    }

    @media (max-width: 1100px){
        .attendance-kpis{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 520px){
        .attendance-kpis{ grid-template-columns: repeat(1, minmax(0, 1fr)); }
    }

    .kpi-card{
        border-radius: 14px;
        border: 1px solid rgba(255,255,255,0.08);
        background: rgba(0,0,0,0.10);
        padding: 12px 14px;
        min-height: 88px;
    }

    .kpi-title{ font-size: 0.78rem; opacity: 0.8; }
    .kpi-value{ font-size: 1.6rem; font-weight: 800; line-height: 1.2; }
    .kpi-sub{ font-size: 0.8rem; opacity: 0.8; }

    .kpi-present .kpi-value{ color: #3ddc84; }
    .kpi-absent .kpi-value{ color: #ff6b6b; }
    .kpi-marked .kpi-value{ color: #ffd166; }
    .kpi-unmarked .kpi-value{ color: #4dabf7; }

    .attendance-filters{
        border-radius: 14px;
        border: 1px solid rgba(255,255,255,0.08);
        background: rgba(0,0,0,0.10);
        padding: 12px;
        margin-top: 12px;
        margin-bottom: 12px;
    }

    .control-row{
        border-bottom: none;
        margin: 0;
    }

    #attendanceMode{
        min-width: 170px;
    }

    .attendance-table .badge{
        font-weight: 800;
        letter-spacing: 0.3px;
    }

    .attendance-table .remarks-pick{
        min-width: 150px;
    }

    .attendance-table img{
        box-shadow: 0 0 0 2px rgba(255,255,255,0.08);
    }

    .attendance-user-badge{
        display:inline-flex;
        align-items:center;
        gap:4px;
        margin-left:8px;
        padding:2px 8px;
        border-radius:999px;
        font-size:11px;
        font-weight:800;
        letter-spacing:0.2px;
        border:1px solid rgba(220,53,69,0.30);
        background:rgba(220,53,69,0.14);
        color:#dc3545;
        vertical-align:middle;
    }

    .attendance-page-title{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap: 12px;
        flex-wrap: wrap;
    }

    .attendance-page-title .title-wrap{
        display:flex;
        align-items:center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .attendance-page-title .title-icon{
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display:flex;
        align-items:center;
        justify-content:center;
        background: rgba(255,193,7,0.22);
        border: 1px solid rgba(255,193,7,0.25);
    }

    .attendance-page-title .title-icon i{ font-size: 1.1rem; color: #ffc107; }

    #alert:empty{ display:none; }

    .select2-container .select2-selection--multiple{
        border-radius: 10px;
        border: 1px solid rgba(255,255,255,0.12);
        min-height: 38px;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice{
        border-radius: 999px;
        border: 1px solid rgba(255,255,255,0.12);
    }

    .attendance-tabs{
        margin-top: 12px;
    }

    .attendance-tabs .nav-link{
        border-radius: 12px !important;
        font-weight: 800;
    }

    .attendance-tabs .nav-link.active{
        border: 1px solid rgba(255,193,7,0.25);
        color: #ffc107;
    }

    .active {
        background-color: var(--bg-color) !important;
        color: var(--text-color) !important;
        }

    /* Select2 dark mode fixes (multi-select staff picker) */
    .select2-container--default .select2-selection--multiple{
        background: rgba(0,0,0,0.10) !important;
        border: 1px solid rgba(255,255,255,0.12) !important;
        min-height: 38px;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__rendered{
        color: rgba(255,255,255,0.92) !important;
        padding: 4px 8px !important;
    }
    .select2-container--default .select2-selection--multiple .select2-search__field{
        color: rgba(255,255,255,0.92) !important;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice{
        background: rgba(13,110,253,0.20) !important;
        border: 1px solid rgba(13,110,253,0.35) !important;
        color: rgba(255,255,255,0.92) !important;
        font-weight: 800;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove{
        color: rgba(255,255,255,0.92) !important;
        margin-right: 6px;
    }
    .select2-dropdown{
        background: #1f252b !important;
        border: 1px solid rgba(255,255,255,0.12) !important;
    }
    .select2-container--default .select2-results__option{
        color: rgba(255,255,255,0.92) !important;
    }
    .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable{
        background: rgba(13,110,253,0.35) !important;
        color: rgba(255,255,255,0.95) !important;
    }
    .select2-container--default .select2-results__option--selected{
        background: rgba(255,193,7,0.20) !important;
        color: rgba(255,255,255,0.95) !important;
    }

    .analytics-shell{
        border-radius: 14px;
        border: 1px solid rgba(255,255,255,0.08);
        background: rgba(0,0,0,0.10);
        padding: 12px;
    }

    .analytics-grid{
        display: grid;
        grid-template-columns: 1.4fr 1fr;
        gap: 12px;
    }

    @media (max-width: 1100px){
        .analytics-grid{ grid-template-columns: 1fr; }
    }

    .analytics-card{
        border-radius: 14px;
        border: 1px solid rgba(255,255,255,0.08);
        background: rgba(0,0,0,0.10);
        padding: 12px;
        overflow: hidden;
    }

    .analytics-card canvas{
        display: block;
        width: 100% !important;
        height: 260px !important;
        max-height: 260px !important;
        max-width: 100% !important;
    }

    @media (max-width: 1100px){
        .analytics-card canvas{ height: 240px !important; max-height: 240px !important; }
    }

    .analytics-card h5{ margin: 0; font-weight: 900; }

    .analytics-kpis{
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 12px;
    }

    .att-register-wrap{
        border-radius: 14px;
        border: 1px solid rgba(255,255,255,0.08);
        background: rgba(0,0,0,0.06);
        overflow: hidden;
    }

    .att-register-scroll{
        width: 100%;
        overflow: auto;
        -webkit-overflow-scrolling: touch;
    }

    .att-register-table{ margin: 0; min-width: 980px; }
    .att-register-table th, .att-register-table td{ vertical-align: middle; white-space: nowrap; border: 1px solid rgba(0,0,0,0.10); }
    .att-register-table thead th{ position: sticky; top: 0; z-index: 5; background: rgba(0,0,0,0.06); }

    .att-month-sep{ border-left: 3px solid rgba(0,0,0,0.35) !important; }
    .att-summary-sep{ border-left: 3px solid rgba(0,0,0,0.45) !important; }

    .att-sticky-col{ position: sticky; left: 0; z-index: 6; background: rgba(255,255,255,0.92); }
    .att-sticky-summary{ position: sticky; z-index: 6; background: rgba(255,255,255,0.92); }
    .att-sticky-s1{ right: 176px; }
    .att-sticky-s2{ right: 132px; }
    .att-sticky-s3{ right: 88px; }
    .att-sticky-s4{ right: 44px; }
    .att-sticky-s5{ right: 0; }

    .att-sum-head{ font-weight: 900; text-align: center; width: 44px; min-width: 44px; }
    .att-sum-cell{ text-align: center; width: 44px; min-width: 44px; font-weight: 900; }
    .att-sum-rate{ color: #0d6efd; }

    .att-sum-p{ background: rgba(25,135,84,0.14) !important; }
    .att-sum-l{ background: rgba(255,193,7,0.20) !important; }
    .att-sum-a{ background: rgba(220,53,69,0.14) !important; }
    .att-sum-nm{ background: rgba(108,117,125,0.12) !important; }
    .att-sum-r{ background: rgba(13,110,253,0.10) !important; }

    .att-month-row th{ font-weight: 900; letter-spacing: .2px; text-transform: uppercase; font-size: 12px; }
    .att-day-row th{ font-weight: 800; font-size: 12px; }
    .att-day-num{ display: inline-block; min-width: 18px; text-align: center; }
    .att-day-dow{ display: inline-block; opacity: .65; margin-left: 6px; font-size: 11px; }

    .att-cell{ text-align: center; padding: 0.35rem 0.35rem; vertical-align: middle; }
    .att-mark-stack{ display: inline-flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; min-width: 34px; }
    .att-pill{ display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 999px; font-weight: 900; border: 1px solid rgba(0,0,0,0.08); }
    .att-pill-present{ background: rgba(25,135,84,0.18); color: #198754; border-color: rgba(25,135,84,0.28); }
    .att-pill-late{ background: rgba(255,193,7,0.22); color: #8a6d00; border-color: rgba(255,193,7,0.40); }
    .att-pill-absent{ background: rgba(220,53,69,0.18); color: #dc3545; border-color: rgba(220,53,69,0.28); }
    .att-pill-nm{ background: rgba(108,117,125,0.14); color: rgba(0,0,0,0.55); border-color: rgba(0,0,0,0.08); }
    .att-time-mini{ display:block; max-width: 44px; padding: 1px 4px; border-radius: 999px; font-size: 9px; line-height: 1.15; font-weight: 800; white-space: nowrap; letter-spacing: .01em; }
    .att-time-present{ color: #198754; background: rgba(25,135,84,0.10); }
    .att-time-late{ color: #8a6d00; background: rgba(255,193,7,0.16); }

    .att-legend{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; justify-content:flex-end; }
    .att-legend-item{ display:flex; gap:6px; align-items:center; font-weight:800; font-size:12px; opacity:.95; }

    @media (max-width: 768px){
        .att-register-table{ min-width: 860px; }
    }

    @media (max-width: 1100px){
        .analytics-kpis{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 520px){
        .analytics-kpis{ grid-template-columns: repeat(1, minmax(0, 1fr)); }
    }

    .analytics-table td, .analytics-table th{ vertical-align: middle; }
    .analytics-table .rate-pill{
        font-weight: 900;
        border-radius: 999px;
        padding: 0.25rem 0.6rem;
        display: inline-block;
        border: 1px solid rgba(255,255,255,0.12);
        background: rgba(0,0,0,0.18);
    }


</style>

<div class="container mt-4 attendance-shell">
	<div class="attendance-hero">
		<div id="alert" class="col-12 p-2 rounded"></div>
		<div class="attendance-page-title">
			<div class="title-wrap">
				<div class="title-icon"><i class="fa fa-clipboard-check"></i></div>
				<div>
					<h1 class="m-0 fw-bolder">Attendance</h1>
					<div class="text-muted" style="font-size: 0.9rem;">Register, monitor, and export daily attendance with live metrics</div>
				</div>
			</div>
			<div class="text-end">
				<div class="text-muted" style="font-size: 0.8rem;">Timezone</div>
				<div class="fw-bolder">Africa/Accra</div>
			</div>
		</div>
		<div class="attendance-kpis" id="attendanceKpis"></div>
	</div>

	<div class="attendance-filters">
		<div class="row align-items-center control-row g-2">
			<div class="col-auto">
				<select class="form-select" id="attendanceMode">
					<option value="view" selected>View Attendance</option>
					<option value="specific">Specific Date</option>
					<option value="range">Date Range</option>
					<option value="student">By Staff</option>
				</select>
			</div>
			<div class="col-auto" id="specificDateGroup">
				<input type="date" id="specificDate" class="form-control">
			</div>
			<div class="col-auto" id="specificViewToggleGroup" style="display: none;">
				<button type="button" class="btn btn-outline-primary fw-bolder" id="specificViewToggle"></button>
			</div>
			<div class="col-auto" id="startDateGroup" style="display: none;">
				<input type="date" id="startDate" class="form-control">
			</div>
			<div class="col-auto" id="endDateGroup" style="display: none;">
				<input type="date" id="endDate" class="form-control">
			</div>

            <!-- New student selector, hidden by default -->
            <div class="col-auto" id="studentSelectGroup" style="display: none;">
              <select id="studentSelect" class="form-select select2" multiple="multiple" data-placeholder="Search & select staff…">
                <?php foreach ($users as $user): ?>
                  <option value="<?= $user->id ?>">
                <?= htmlspecialchars($user->first_name . ' ' . $user->last_name . (!empty($user->disabled) ? ' (Disabled)' : '')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>


			<div class="col-auto">
				<button id="loadRegister" class="btn btn-warning fw-bolder">
					<i class="fa fa-search"></i> Fetch
				</button>
			</div>
			<div class="col-auto">
				<button id="clearAttendanceFilters" class="btn btn-outline-secondary fw-bolder">
					<i class="fa fa-eraser"></i> Clear
				</button>
			</div>
			<div class="col-auto ms-auto">
				<div id="miniAttendanceSummary"></div>
			</div>
		</div>
	</div>

	<div class="attendance-tabs">
		<ul class="nav nav-pills gap-2" id="attendanceTabs" role="tablist">
			<li class="nav-item" role="presentation">
				<button class="nav-link active" id="attendance-register-tab" data-bs-toggle="pill" data-bs-target="#attendance-register-pane" type="button" role="tab" aria-controls="attendance-register-pane" aria-selected="true">
					<i class="fa fa-clipboard-check me-1"></i> Register
				</button>
			</li>
			<li class="nav-item" role="presentation">
				<button class="nav-link" id="attendance-analytics-tab" data-bs-toggle="pill" data-bs-target="#attendance-analytics-pane" type="button" role="tab" aria-controls="attendance-analytics-pane" aria-selected="false">
					<i class="fa fa-chart-line me-1"></i> Analytics
				</button>
			</li>
		</ul>
	</div>

    <!-- Preloader -->
    <div id="preloader" class="preloader">
        <span class="word">L</span><span class="word">o</span><span class="word">a</span><span class="word">d</span><span class="word">i</span><span class="word">n</span><span class="word">g</span>
    </div>

	<div class="tab-content mt-3" id="attendanceTabsContent">
		<div class="tab-pane fade show active registerContainer" id="attendance-register-pane" role="tabpanel" aria-labelledby="attendance-register-tab" tabindex="0">
			<div id="attendanceCards"></div>
			<div id="attendanceSummary"></div>
		</div>
		<div class="tab-pane fade" id="attendance-analytics-pane" role="tabpanel" aria-labelledby="attendance-analytics-tab" tabindex="0">
			<div class="analytics-shell">
				<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
					<div>
						<div class="fw-bolder" style="font-size: 1.05rem;">Attendance Analytics</div>
						<div class="text-muted" style="font-size: 0.88rem;">Performance sheet across selected duration</div>
					</div>
					<div class="d-flex align-items-center gap-2">
						<button class="btn btn-outline-warning fw-bolder" id="refreshAnalytics">
							<i class="fa fa-sync"></i> Refresh
						</button>
					</div>
				</div>

				<div class="analytics-kpis" id="analyticsKpis"></div>

				<div class="analytics-grid">
					<div class="analytics-card">
						<div class="d-flex align-items-center justify-content-between mb-2">
							<h5>Attendance Mix</h5>
							<div class="text-muted" id="analyticsRangeLabel" style="font-size:0.85rem;"></div>
						</div>
						<canvas id="attendanceMixChart" height="140"></canvas>
					</div>
					<div class="analytics-card">
						<div class="d-flex align-items-center justify-content-between mb-2">
							<h5>Top Performers</h5>
							<div class="text-muted" style="font-size:0.85rem;">Top 10 by %</div>
						</div>
						<canvas id="attendanceTopChart" height="140"></canvas>
					</div>
				</div>

				<div class="analytics-card mt-3">
					<div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
						<h5>Analytics Sheet</h5>
						<div class="text-muted" id="analyticsMeta" style="font-size:0.85rem;"></div>
					</div>
					<ul class="nav nav-pills gap-2 mt-2" id="analyticsUserTabs" role="tablist"></ul>
					<div class="table-responsive mt-2">
						<table class="table table-hover analytics-table" id="analyticsTable">
							<thead>
								<tr>
									<th>#</th>
									<th>User</th>
									<th>Present</th>
									<th>Late</th>
									<th>Absent</th>
									<th>Unmarked</th>
									<th>Total Days</th>
									<th>Rate</th>
								</tr>
							</thead>
							<tbody id="analyticsTableBody"></tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
    function formatLongDate(dateStr) {
        var dateObj = new Date(dateStr);
        var day = dateObj.getDate();
        var suffix = 'th';
        if (day % 10 === 1 && day !== 11) { suffix = 'st'; }
        else if (day % 10 === 2 && day !== 12) { suffix = 'nd'; }
        else if (day % 10 === 3 && day !== 13) { suffix = 'rd'; }
        var monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        var month = monthNames[dateObj.getMonth()];
        var year = dateObj.getFullYear();
        return String(day) + suffix + ' ' + month + ', ' + String(year);
    }

    function getAccraTodayInputValue() {
        try {
            var parts = new Intl.DateTimeFormat('en-CA', {
                timeZone: 'Africa/Accra',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit'
            }).formatToParts(new Date());
            var mapped = {};
            for (var i = 0; i < parts.length; i++) {
                if (parts[i] && parts[i].type) {
                    mapped[parts[i].type] = parts[i].value;
                }
            }
            if (mapped.year && mapped.month && mapped.day) {
                return mapped.year + '-' + mapped.month + '-' + mapped.day;
            }
        } catch (e) { }

        var now = new Date();
        var month = String(now.getMonth() + 1).padStart(2, '0');
        var day = String(now.getDate()).padStart(2, '0');
        return String(now.getFullYear()) + '-' + month + '-' + day;
    }

	var attendanceModeSelect;
	var specificDateGroup;
	var startDateGroup;
	var endDateGroup;
	var specificDateInput;
	var specificViewToggleGroup;
	var specificViewToggle;
	var startDateInput;
	var endDateInput;
	var studentGroup;
	var loadRegisterButton;
	var attendanceCards;
	var preloader;
	var attendanceSummary;
	var analyticsTab;
	var refreshAnalyticsButton;
	var analyticsKpis;
	var analyticsRangeLabel;
	var analyticsMeta;
	var analyticsTableBody;
	var analyticsMixChart;
	var analyticsTopChart;
	var attendanceFetchXhr;
	var attendanceFetchDebounceTimer;
	var clearAttendanceFiltersButton;
	var attendanceFetchRequestSeq;
	var attendanceLazyScripts = {};

	function loadAttendanceScript(key, url) {
		if (!url) {
			return Promise.reject(new Error('Missing script URL for ' + key));
		}
		if (attendanceLazyScripts[key]) {
			return attendanceLazyScripts[key];
		}
		attendanceLazyScripts[key] = new Promise(function(resolve, reject) {
			var script = document.createElement('script');
			script.src = url;
			script.async = true;
			script.onload = function() { resolve(true); };
			script.onerror = function() { reject(new Error('Failed to load ' + key)); };
			document.head.appendChild(script);
		});
		return attendanceLazyScripts[key];
	}

	function ensureAttendanceAnalyticsLibs() {
		if (window.Chart) {
			return Promise.resolve(true);
		}
		return loadAttendanceScript('chart', window.ATT_LIB_URLS ? window.ATT_LIB_URLS.chart : '');
	}

	function ensureAttendanceXlsxLib() {
		if (window.XLSX) {
			return Promise.resolve(true);
		}
		return loadAttendanceScript('xlsx', window.ATT_LIB_URLS ? window.ATT_LIB_URLS.xlsx : '');
	}

	function ensureAttendancePdfLibs() {
		var ensureJspdf = (window.jspdf && window.jspdf.jsPDF) || window.jsPDF
			? Promise.resolve(true)
			: loadAttendanceScript('jspdf', window.ATT_LIB_URLS ? window.ATT_LIB_URLS.jspdf : '');
		return ensureJspdf.then(function() {
			if (typeof window.jspdfAutoTable === 'function') {
				return true;
			}
			return loadAttendanceScript('autotable', window.ATT_LIB_URLS ? window.ATT_LIB_URLS.autotable : '');
		});
	}

    document.addEventListener('DOMContentLoaded', function () {
		attendanceModeSelect = document.getElementById('attendanceMode');
		specificDateGroup = document.getElementById('specificDateGroup');
		startDateGroup = document.getElementById('startDateGroup');
		endDateGroup = document.getElementById('endDateGroup');
		specificDateInput = document.getElementById('specificDate');
		specificViewToggleGroup = document.getElementById('specificViewToggleGroup');
		specificViewToggle = document.getElementById('specificViewToggle');
		startDateInput = document.getElementById('startDate');
		endDateInput = document.getElementById('endDate');
		studentGroup = document.getElementById('studentSelectGroup');
		loadRegisterButton = document.getElementById('loadRegister');
		clearAttendanceFiltersButton = document.getElementById('clearAttendanceFilters');
		attendanceCards = document.getElementById('attendanceCards');
		preloader = document.getElementById('preloader');
		attendanceSummary = document.getElementById('attendanceSummary');
		analyticsTab = document.getElementById('attendance-analytics-tab');
		refreshAnalyticsButton = document.getElementById('refreshAnalytics');
		analyticsKpis = document.getElementById('analyticsKpis');
		analyticsRangeLabel = document.getElementById('analyticsRangeLabel');
		analyticsMeta = document.getElementById('analyticsMeta');
		analyticsTableBody = document.getElementById('analyticsTableBody');

		try{
			if (typeof $ !== 'undefined' && $.fn && typeof $.fn.select2 === 'function') {
				var $sel = $('#studentSelect');
				if ($sel && $sel.length) {
					$sel.select2({
						width: '260px',
						dropdownParent: $('#studentSelectGroup')
					});
				}
			}
		}catch(e){ }

        // Initialize dates with the current Africa/Accra date
        var today = getAccraTodayInputValue();
        specificDateInput.value = today;
        startDateInput.value = today;
        endDateInput.value = today;

        // on mode change: show/hide inputs
        attendanceModeSelect.addEventListener('change', function () {
            var mode = attendanceModeSelect.value;
            specificDateGroup.style.display = mode === 'specific' ? 'block' : 'none';
            if(specificViewToggleGroup){
                specificViewToggleGroup.style.display = mode === 'specific' ? 'block' : 'none';
            }
            startDateGroup.style.display = (mode === 'range' || mode === 'student' || mode === 'view') ? 'block' : 'none';
            endDateGroup.style.display = (mode === 'range' || mode === 'student' || mode === 'view') ? 'block' : 'none';
            studentGroup.style.display = (mode === 'view' || mode === 'student') ? 'block' : 'none';
        });

        function getSpecificViewMode(){
            var m = '';
            try { m = localStorage.getItem('attendanceSpecificViewMode') || ''; } catch(e) { m = ''; }
            m = String(m || '').toLowerCase();
            if(m !== 'grid' && m !== 'mark') m = 'mark';
            return m;
        }
        function setSpecificViewMode(m){
            m = String(m || '').toLowerCase();
            if(m !== 'grid' && m !== 'mark') m = 'mark';
            try { localStorage.setItem('attendanceSpecificViewMode', m); } catch(e) { }
            return m;
        }
        function renderSpecificToggle(){
            if(!specificViewToggle) return;
            var m = getSpecificViewMode();
            if(m === 'grid'){
                specificViewToggle.innerHTML = '<i class="fa fa-edit"></i> Mark Attendance';
            }else{
                specificViewToggle.innerHTML = '<i class="fa fa-table"></i> Register Sheet';
            }
            if(m === 'grid'){
                specificViewToggle.classList.remove('btn-outline-primary');
                specificViewToggle.classList.add('btn-primary');
            }else{
                specificViewToggle.classList.add('btn-outline-primary');
                specificViewToggle.classList.remove('btn-primary');
            }
        }
        function toggleSpecificViewMode(){
            var m = getSpecificViewMode();
            m = (m === 'grid') ? 'mark' : 'grid';
            setSpecificViewMode(m);
            renderSpecificToggle();
            scheduleFetchAttendance();
            try {
                var pane = document.getElementById('attendance-analytics-pane');
                if (pane && pane.classList && pane.classList.contains('show')) {
                    fetchAttendanceAnalytics();
                }
            } catch (e) { }
        }
        if(specificViewToggle){
            specificViewToggle.addEventListener('click', toggleSpecificViewMode);
        }
        renderSpecificToggle();

        attendanceFetchRequestSeq = 0;

        function scheduleFetchAttendance() {
            if (attendanceFetchDebounceTimer) clearTimeout(attendanceFetchDebounceTimer);
            attendanceFetchDebounceTimer = setTimeout(function () { fetchAttendance(); }, 180);
        }

		function isAnalyticsPaneActive() {
			try {
				var pane = document.getElementById('attendance-analytics-pane');
				return !!(pane && pane.classList && pane.classList.contains('show'));
			} catch (e) {
				return false;
			}
		}

		function handleAttendanceFilterChange() {
			// Always update the main KPIs (Present/Late/Absent/Not Marked)
			scheduleFetchAttendance();
			// If Analytics tab is currently visible, refresh it too
			if (isAnalyticsPaneActive()) {
				fetchAttendanceAnalytics();
			}
		}

        loadRegisterButton.addEventListener('click', scheduleFetchAttendance);
		if (attendanceModeSelect) {
			attendanceModeSelect.addEventListener('change', handleAttendanceFilterChange);
		}
		if (specificDateInput) {
			specificDateInput.addEventListener('change', handleAttendanceFilterChange);
		}
		if (startDateInput) {
			startDateInput.addEventListener('change', handleAttendanceFilterChange);
		}
		if (endDateInput) {
			endDateInput.addEventListener('change', handleAttendanceFilterChange);
		}
		try {
			var staffSel = document.getElementById('studentSelect');
			if (staffSel) {
				staffSel.addEventListener('change', handleAttendanceFilterChange);
			}
			if (typeof $ !== 'undefined') {
				$('#studentSelect').on('change', handleAttendanceFilterChange);
			}
		} catch (e) { }
        if (clearAttendanceFiltersButton) {
            clearAttendanceFiltersButton.addEventListener('click', function () {
                var today = getAccraTodayInputValue();
                try {
                    attendanceModeSelect.value = 'specific';
                    specificDateInput.value = today;
                    startDateInput.value = today;
                    endDateInput.value = today;
                    setSpecificViewMode('mark');
                    renderSpecificToggle();
                    var mode = attendanceModeSelect.value;
                    specificDateGroup.style.display = mode === 'specific' ? 'block' : 'none';
                    if(specificViewToggleGroup){
                        specificViewToggleGroup.style.display = mode === 'specific' ? 'block' : 'none';
                    }
                    startDateGroup.style.display = (mode === 'range' || mode === 'student' || mode === 'view') ? 'block' : 'none';
                    endDateGroup.style.display = (mode === 'range' || mode === 'student' || mode === 'view') ? 'block' : 'none';
                    studentGroup.style.display = (mode === 'view' || mode === 'student') ? 'block' : 'none';
                    if (typeof $ !== 'undefined') {
                        $('#studentSelect').val(null).trigger('change');
                    }
                } catch (e) { }

                attendanceCards.innerHTML = '';
                attendanceSummary.innerHTML = '';
				handleAttendanceFilterChange();
            });
        }
        fetchAttendance(); // initial load

        function timeToInputValue(time) {
            if (!time) return '';
            var t = String(time).trim();
            if (t === '') return '';
            var parts = t.split(':');
            if (parts.length < 2) return '';
            var hh = String(parts[0] || '');
            var mm = String(parts[1] || '');
            if (hh.length < 2) hh = '0' + hh;
            if (mm.length < 2) mm = '0' + mm;
            return hh + ':' + mm;
        }

        function formatTimeDisplay(time) {
            if (!time) return '';
            var t = String(time).trim();
            if (t === '') return '';
            var parts = t.split(':');
            if (parts.length < 2) return t;
            var hours = parseInt(parts[0], 10);
            var minutes = parseInt(parts[1], 10);
            if (isNaN(hours) || isNaN(minutes)) return t;
            var ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12;
            var m2 = String(minutes);
            if (m2.length < 2) m2 = '0' + m2;
            return String(hours) + ':' + m2 + ' ' + ampm;
        }

        function isDisabledUser(user) {
            return !!(user && (String(user.disabled || '0') === '1' || user.disabled === 1 || user.disabled === true));
        }

        function disabledBadgeHtml(user) {
            return isDisabledUser(user) ? '<span class="attendance-user-badge">Disabled</span>' : '';
        }

        function renderKpis(kpis) {
            var wrap = document.getElementById('attendanceKpis');
            if (!wrap) return;
            var total = Math.max(0, (kpis.present || 0) + (kpis.late || 0) + (kpis.absent || 0) + (kpis.unmarked || 0));
            var marked = Math.max(0, (kpis.present || 0) + (kpis.late || 0) + (kpis.absent || 0));
            var rate = total > 0 ? Math.round((marked / total) * 100) : 0;
            var html = '';
            html += '<div class="kpi-card kpi-present"><div class="kpi-title">Present</div><div class="kpi-value">' + (kpis.present || 0) + '</div><div class="kpi-sub">' + rate + '% marked</div></div>';
  html += '<div class="kpi-card"><div class="kpi-title">Late</div><div class="kpi-value">' + (kpis.late || 0) + '</div><div class="kpi-sub">After late attendance time</div></div>';
            html += '<div class="kpi-card kpi-absent"><div class="kpi-title">Absent</div><div class="kpi-value">' + (kpis.absent || 0) + '</div><div class="kpi-sub">' + total + ' total</div></div>';
            html += '<div class="kpi-card kpi-marked"><div class="kpi-title">Marked</div><div class="kpi-value">' + marked + '</div><div class="kpi-sub">Present + Late + Absent</div></div>';
            html += '<div class="kpi-card kpi-unmarked"><div class="kpi-title">Not Marked</div><div class="kpi-value">' + (kpis.unmarked || 0) + '</div><div class="kpi-sub">Needs action</div></div>';
            wrap.innerHTML = html;
        }

        function fetchAttendance() {
            var mode = attendanceModeSelect.value;
            preloader.classList.add('visible');
            attendanceCards.innerHTML = '';
            attendanceSummary.innerHTML = '';
			var requestSeq = ++attendanceFetchRequestSeq;

			try {
				if (attendanceFetchXhr && attendanceFetchXhr.readyState !== 4) {
					attendanceFetchXhr.abort();
				}
			} catch (e) { }

            // Base payload uses the default fetch_attendance action
            var payload = { action: 'fetch_attendance', mode: mode };

            if (mode === 'specific') {
                var date = specificDateInput.value;
                if (!date) {
                    showAlert('Please select a date!', 'warning');
                    preloader.classList.remove('visible');
                    return;
                }
                payload.date = date;

            } else if (mode === 'student') {
                // 1) Collect selected student IDs
                var sel = [];
                try{
                    var opts = document.getElementById('studentSelect').selectedOptions || [];
                    for(var si = 0; si < opts.length; si++) sel.push(opts[si].value);
                }catch(e){ sel = []; }
                if (!sel.length) {
                    showAlert('Pick at least one student', 'warning');
                    preloader.classList.remove('visible');
                    return;
                }

                // 2) Require both start & end
                var sd = startDateInput.value;
                var ed = endDateInput.value;
                if (!sd || !ed) {
                    showAlert(
                        'Please select both start & end dates for student mode',
                        'warning'
                    );
                    preloader.classList.remove('visible');
                    return;
                }

                // 3) Switch action and attach range + IDs
                payload.action = 'fetch_attendance_by_students';
                payload.startDate = sd;
                payload.endDate = ed;
                payload.studentIds = sel;

            } else if (mode === 'view') {
                // 1) Collect selected student IDs
                var sel = [];
                try{
                    var opts = document.getElementById('studentSelect').selectedOptions || [];
                    for(var si = 0; si < opts.length; si++) sel.push(opts[si].value);
                }catch(e){ sel = []; }
                /* if (!sel.length) {
                    showAlert('Pick at least one student', 'warning');
                    preloader.classList.remove('visible');
                    return;
               }*/

                // 2) Require both start & end
                var sd = startDateInput.value;
                var ed = endDateInput.value;
                if (!sd || !ed) {
                    showAlert(
                        'Please select both start & end dates for student mode',
                        'warning'
                    );
                    preloader.classList.remove('visible');
                    return;
                }

                // 3) Switch action and attach range + IDs
                payload.action = 'view_attendance_by_students';
                payload.startDate = sd;
                payload.endDate = ed;
                payload.studentIds = sel;

            } else if (mode === 'range') {
                var sd = startDateInput.value;
                var ed = endDateInput.value;
                if (!sd || !ed) {
                    showAlert('Please select both dates!', 'warning');
                    preloader.classList.remove('visible');
                    return;
                }
                payload.startDate = sd;
                payload.endDate = ed;
            }

            // Send request
			var xhr = new XMLHttpRequest();
			attendanceFetchXhr = xhr;
            xhr.open('POST', '', true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;
                preloader.classList.remove('visible');
				if (requestSeq !== attendanceFetchRequestSeq) return;

                if (xhr.status === 0) {
                    // request aborted
                    return;
                }
                if (xhr.status !== 200) {
                    showAlert('Failed to fetch attendance.', 'danger');
                    return;
                }

                try {
                    var response = JSON.parse(String(xhr.responseText || '').trim());

                    if (!response.success) {
                        showAlert(response.error || 'An error occurred.', 'danger');
                        return;
                    }

                    if ((mode === 'range' || mode === 'student') && response.data) {
                        loadViewAttendanceData(response.data);
                        var __unmarkedCount = 0;
                        try {
                            var __vals = Object.values(response.data);
                            for(var vi = 0; vi < __vals.length; vi++){
                                var g = __vals[vi] || {};
                                var um = g.unmarked || [];
                                __unmarkedCount += (Array.isArray(um) ? um.length : 0);
                            }
                        } catch (e) { __unmarkedCount = 0; }
                        displayAttendanceSummary(response.data, 'range', { unmarkedCount: __unmarkedCount, skipMain: true });
                    } else if (mode === 'view' && response.data) {
                        loadViewAttendanceData(response.data);
                        var __unmarkedCount = 0;
                        try {
                            var __vals = Object.values(response.data);
                            for(var vi = 0; vi < __vals.length; vi++){
                                var g = __vals[vi] || {};
                                var um = g.unmarked || [];
                                __unmarkedCount += (Array.isArray(um) ? um.length : 0);
                            }
                        } catch (e) { __unmarkedCount = 0; }
                        displayAttendanceSummary(response.data, 'range', { unmarkedCount: __unmarkedCount, skipMain: true });
                    } else {
                        loadAttendanceData(
                            response.unmarked,
                            response.marked,
                            specificDateInput.value
                        );
                        displayAttendanceSummary(response.marked || [], 'specific', { unmarkedCount: (response.unmarked || []).length });
                    }

                    try {
                        if (typeof $ !== 'undefined' && $.fn && $.fn.DataTable) {
                            var tables = document.querySelectorAll('table.attendance-table');
                            var shouldInit = (mode === 'specific' || tables.length <= 4);
                            if (shouldInit) {
                                for (var ti = 0; ti < tables.length; ti++) {
                                    var t = tables[ti];
                                    if ($.fn.DataTable.isDataTable(t)) {
                                        $(t).DataTable().destroy();
                                    }
                                    $(t).DataTable();
                                }
                            }
                        }
                    } catch (e) { }

                } catch (e) {
                    try { console.error('Error parsing response:', xhr.responseText); } catch (e2) { }
                }
            };

            //console.log('payload:', payload);
            xhr.send(JSON.stringify(payload));
        }

        function loadAttendanceData(unmarkedData, markedData, date) {
            var formattedDate = formatLongDate(date);
            var activeUnmarked = (unmarkedData || []).filter(function(user){ return !isDisabledUser(user); });
            var activeMarked = (markedData || []).filter(function(user){ return !isDisabledUser(user); });
            var disabledMarked = (markedData || []).filter(isDisabledUser);
            var html = '<ul class="nav nav-pills gap-2 mb-3" role="tablist">'
                + '<li class="nav-item"><button type="button" class="nav-link active" data-attendance-status="active">Active staff</button></li>'
                + '<li class="nav-item"><button type="button" class="nav-link" data-attendance-status="disabled">Disabled staff (' + disabledMarked.length + ')</button></li>'
                + '</ul>';

            function renderDailyCards(unmarked, marked, includeMarking) {
                var cards = '<div class="attendance-row">';
                if (includeMarking && unmarked.length > 0) {
                    cards += ''
                        + '<div class="attendance-card card-unmarked">'
                        + '<div class="card-header"><i class="fa fa-user-times attendance-icon"></i> NOT MARKED (' + unmarked.length + ') — ' + formattedDate + '</div>'
                        + '<div class="card-body">'
                        + '<div id="unmarkedContainer_' + date + '">' + (window.buildTableHTML ? window.buildTableHTML(unmarked, true, date) : '') + '</div>'
                        + '<button class="btn btn-sm btn-danger mt-2" id="submitAttendance_' + date + '"><i class="fa fa-check-circle"></i> Submit</button>'
                        + '</div>'
                        + '</div>';
                }
                cards += ''
                    + '<div class="attendance-card card-marked">'
                    + '<div class="card-header"><i class="fa fa-user-check attendance-icon"></i> MARKED (' + marked.length + ') — ' + formattedDate + '</div>'
                    + '<div class="card-body">' + (marked.length && window.buildTableHTML ? window.buildTableHTML(marked, false, date) : '<div class="text-muted">No attendance records for this date.</div>') + '</div>'
                    + '</div></div>';
                return cards;
            }

            html += '<div data-attendance-status-panel="active">' + renderDailyCards(activeUnmarked, activeMarked, true) + '</div>';
            html += '<div data-attendance-status-panel="disabled" hidden>' + renderDailyCards([], disabledMarked, false) + '</div>';
            attendanceCards.innerHTML = html;
            attendanceCards.querySelectorAll('[data-attendance-status]').forEach(function(button){
                button.addEventListener('click', function(){
                    var status = button.getAttribute('data-attendance-status');
                    attendanceCards.querySelectorAll('[data-attendance-status]').forEach(function(tab){
                        tab.classList.toggle('active', tab === button);
                    });
                    attendanceCards.querySelectorAll('[data-attendance-status-panel]').forEach(function(panel){
                        panel.hidden = panel.getAttribute('data-attendance-status-panel') !== status;
                    });
                });
            });
            if (window.attachSubmitAttendanceForGroup && activeUnmarked.length > 0) window.attachSubmitAttendanceForGroup(date);
        }

        function loadGroupedAttendanceData(groupedData) {
            var html = '';
            var dates = Object.keys(groupedData).sort();
            dates.forEach(function (date) {
                var group = groupedData[date];
                var formattedDate = formatLongDate(date);

                html += '<div class="grouped-row">';

                // Only render the UNMARKED card if there are unmarked users
                if (group.unmarked.length > 0) {
                    html += ''
                        + '<div class="attendance-card card-unmarked">'
                        + '<div class="card-header"><i class="fa fa-user-times attendance-icon"></i> NOT MARKED (' + group.unmarked.length + ') - ' + formattedDate + '</div>'
                        + '<div class="card-body">'
                        + '<div id="unmarkedContainer_' + date + '">' + (window.buildTableHTML ? window.buildTableHTML(group.unmarked, true, date) : '') + '</div>'
                        + '<button class="btn btn-sm btn-danger mt-2" id="submitAttendance_' + date + '"><i class="fa fa-check-circle"></i> Submit</button>'
                        + '</div>'
                        + '</div>';
                }

                // Always render the MARKED card
                html += ''
                    + '<div class="attendance-card card-marked">'
                    + '<div class="card-header"><i class="fa fa-user-check attendance-icon"></i> MARKED (' + group.marked.length + ') - ' + formattedDate + '</div>'
                    + '<div class="card-body">' + (window.buildTableHTML ? window.buildTableHTML(group.marked, false, date) : '') + '</div>'
                    + '</div>';

                html += '</div>'; // close grouped-row
            });

            attendanceCards.innerHTML = html;
            dates.forEach(function (date) { if (window.attachSubmitAttendanceForGroup) window.attachSubmitAttendanceForGroup(date); });
        }

        function loadViewAttendanceData(groupedData) {
			function esc(str){
				if(str === undefined || str === null) return '';
				return String(str).replace(/[&<>"']/g, function(m){
					return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#039;'})[m];
				});
			}
			function csvEscape(val){
				var s = (val === undefined || val === null) ? '' : String(val);
				if(/[",\n\r]/.test(s)){
					s = '"' + s.replace(/"/g, '""') + '"';
				}
				return s;
			}
			function parseIso(d){
				var x = new Date(String(d) + 'T00:00:00');
				return isNaN(x.getTime()) ? null : x;
			}
			function monthKey(d){
				var dt = parseIso(d);
				if(!dt) return '';
				var y = dt.getFullYear();
				var m = dt.getMonth() + 1;
				return y + '-' + (m < 10 ? ('0' + m) : m);
			}
			function monthLabel(d){
				var dt = parseIso(d);
				if(!dt) return '';
				try{
					return new Intl.DateTimeFormat(undefined, {month:'long', year:'numeric'}).format(dt);
				}catch(e){
					return dt.toLocaleDateString(undefined, {month:'long', year:'numeric'});
				}
			}
			function dayLabel(d){
				var dt = parseIso(d);
				if(!dt) return {num:'', dow:''};
				var num = String(dt.getDate());
				var dow = '';
				try{
					dow = new Intl.DateTimeFormat(undefined, {weekday:'short'}).format(dt);
				}catch(e){
					dow = dt.toLocaleDateString(undefined, {weekday:'short'});
				}
				return {num: num, dow: dow};
			}
			function normRemark(r){
				var up = String(r || '').trim().toUpperCase();
				if(up === 'PRESENT' || up === 'LATE' || up === 'ABSENT') return up;
				return '';
			}
			function fullName(u){
				var fn = u && u.first_name ? String(u.first_name) : '';
				var ln = u && u.last_name ? String(u.last_name) : '';
				return (fn + ' ' + ln).trim();
			}

			var dates = Object.keys(groupedData || {}).sort();
			if(!dates.length){
				attendanceCards.innerHTML = '<div class="alert alert-secondary">No attendance records found for the selected range.</div>';
				return;
			}

			var usersById = {};
			var statusByUserDate = {};
			dates.forEach(function(date){
				var g = groupedData[date] || {};
				var marked = Array.isArray(g.marked) ? g.marked : [];
				var unmarked = Array.isArray(g.unmarked) ? g.unmarked : [];
				marked.forEach(function(r){
					var rawId = '';
					if(r && r.user_id !== undefined && r.user_id !== null){ rawId = r.user_id; }
					else if(r && r.id !== undefined && r.id !== null){ rawId = r.id; }
					var uid = String(rawId || '');
					if(!uid) return;
					if(!usersById[uid]) usersById[uid] = r;
					if(!statusByUserDate[uid]) statusByUserDate[uid] = {};
					statusByUserDate[uid][date] = {
						remark: normRemark(r.remarks || ''),
						time: (r && r.time) ? String(r.time) : ''
					};
				});
				unmarked.forEach(function(u){
					var uid = String((u && u.id) ? u.id : '');
					if(!uid) return;
					if(!usersById[uid]) usersById[uid] = u;
				});
			});

			var users = Object.keys(usersById).map(function(uid){
				var u = usersById[uid] || {};
				return {
					id: uid,
					name: fullName(u) || ('#' + uid),
					image: (u && u.image) ? String(u.image) : '',
					disabled: isDisabledUser(u)
				};
			}).sort(function(a,b){
				return a.name.localeCompare(b.name);
			});
			var allRegisterUsers = users;
			var activeRegisterUsers = allRegisterUsers.filter(function(user){ return !user.disabled; });
			var disabledRegisterUsers = allRegisterUsers.filter(function(user){ return user.disabled; });

			var monthGroups = [];
			var currentKey = '';
			var current = null;
			var monthSepByDate = {};
			dates.forEach(function(d){
				var mk = monthKey(d);
				if(mk !== currentKey){
					if(currentKey){ monthSepByDate[d] = true; }
					currentKey = mk;
					current = {key: mk, label: monthLabel(d), dates: []};
					monthGroups.push(current);
				}
				current.dates.push(d);
			});

			var legendHtml = '';
			legendHtml += '<div class="att-legend">';
			legendHtml += '<div class="att-legend-item"><span class="att-pill att-pill-present" title="Present (on time)"><i class="fa fa-check"></i></span> Present</div>';
			legendHtml += '<div class="att-legend-item"><span class="att-pill att-pill-late" title="Late"><i class="fa fa-check"></i></span> Late</div>';
			legendHtml += '<div class="att-legend-item"><span class="att-pill att-pill-absent" title="Absent"><i class="fa fa-times"></i></span> Absent</div>';
			legendHtml += '<div class="att-legend-item"><span class="att-pill att-pill-nm" title="Not marked">—</span> Not marked</div>';
			legendHtml += '</div>';

			var html = '';
			html += '<div class="attendance-card card-marked" style="min-width:100%">';
			html += '<div class="card-header d-flex align-items-center justify-content-between gap-2">';
			html += '<div class="d-flex align-items-center gap-2"><i class="fa fa-clipboard-list attendance-icon"></i><span>Register Sheet</span></div>';
			html += '<div class="d-flex align-items-center gap-2 flex-wrap">' + legendHtml + '<button type="button" class="btn btn-sm btn-outline-success" id="attExportExcel"><i class="fa fa-file-excel"></i> Excel</button><button type="button" class="btn btn-sm btn-outline-danger" id="attExportPdf"><i class="fa fa-file-pdf"></i> PDF</button></div>';
			html += '</div>';
			html += '<ul class="nav nav-pills gap-2 p-3 pb-0" role="tablist">';
			html += '<li class="nav-item"><button type="button" class="nav-link active" data-attendance-user-status="active">Active staff (' + activeRegisterUsers.length + ')</button></li>';
			html += '<li class="nav-item"><button type="button" class="nav-link" data-attendance-user-status="disabled">Disabled staff (' + disabledRegisterUsers.length + ')</button></li>';
			html += '</ul>';
			html += '<div class="card-body p-0">';
			html += '<div class="att-register-wrap">';
			html += '<div class="att-register-scroll">';
			html += '<table class="table table-hover att-register-table">';
			html += '<thead>';
			html += '<tr class="att-month-row">';
			html += '<th class="att-sticky-col">Staff</th>';
			monthGroups.forEach(function(g, gi){
				var sepClass = gi ? ' att-month-sep' : '';
				html += '<th colspan="' + g.dates.length + '" class="text-center' + sepClass + '">' + esc(g.label) + '</th>';
			});
			html += '<th colspan="5" class="text-center att-summary-sep">Summary</th>';
			html += '</tr>';
			html += '<tr class="att-day-row">';
			html += '<th class="att-sticky-col">&nbsp;</th>';
			dates.forEach(function(d){
				var dl = dayLabel(d);
				var sepClass = monthSepByDate[d] ? ' att-month-sep' : '';
				html += '<th class="text-center' + sepClass + '" title="' + esc(d) + '"><span class="att-day-num">' + esc(dl.num) + '</span><span class="att-day-dow">' + esc(dl.dow) + '</span></th>';
			});
			html += '<th class="att-sticky-summary att-sticky-s1 att-sum-head att-summary-sep">P</th>';
			html += '<th class="att-sticky-summary att-sticky-s2 att-sum-head">L</th>';
			html += '<th class="att-sticky-summary att-sticky-s3 att-sum-head">A</th>';
			html += '<th class="att-sticky-summary att-sticky-s4 att-sum-head">NM</th>';
			html += '<th class="att-sticky-summary att-sticky-s5 att-sum-head">%</th>';
			html += '</tr>';
			html += '</thead>';
			html += '<tbody>';
			users.forEach(function(u){
				var present = 0, late = 0, absent = 0, notMarked = 0;
				var nameHtml = '';
				nameHtml += '<div class="d-flex align-items-center gap-2">';
				if(u.image){
					nameHtml += '<img src="' + esc(u.image) + '" class="img-thumbnail" style="width:30px; height:30px;">';
				}else{
					nameHtml += '<div class="rounded-circle" style="width:30px; height:30px; background:rgba(0,0,0,0.08);"></div>';
				}
				nameHtml += '<div class="fw-semibold">' + esc(u.name) + disabledBadgeHtml(u) + '</div>';
				nameHtml += '</div>';
				html += '<tr data-attendance-user-row data-disabled="' + (u.disabled ? '1' : '0') + '"' + (u.disabled ? ' hidden' : '') + '>';
				html += '<td class="att-sticky-col">' + nameHtml + '</td>';
				dates.forEach(function(d){
					var cell = (statusByUserDate[u.id] && statusByUserDate[u.id][d]) ? statusByUserDate[u.id][d] : null;
					var remark = cell ? cell.remark : '';
					var time = cell ? (cell.time || '') : '';
					var title = u.name + ' • ' + d;
					var inner = '<span class="att-pill att-pill-nm" title="' + esc(title + ' • Not marked') + '">—</span>';
					if(remark === 'PRESENT'){
						present++;
						inner = '<span class="att-mark-stack" title="' + esc(title + ' • Present' + (time ? (' • ' + time) : '')) + '"><span class="att-pill att-pill-present"><i class="fa fa-check"></i></span>' + (time ? '<span class="att-time-mini att-time-present">' + esc(formatTimeDisplay(time)) + '</span>' : '') + '</span>';
					}else if(remark === 'LATE'){
						late++;
						inner = '<span class="att-mark-stack" title="' + esc(title + ' • Late' + (time ? (' • ' + time) : '')) + '"><span class="att-pill att-pill-late"><i class="fa fa-check"></i></span>' + (time ? '<span class="att-time-mini att-time-late">' + esc(formatTimeDisplay(time)) + '</span>' : '') + '</span>';
					}else if(remark === 'ABSENT'){
						absent++;
						inner = '<span class="att-pill att-pill-absent" title="' + esc(title + ' • Absent') + '"><i class="fa fa-times"></i></span>';
					}else{
						notMarked++;
					}
					var sepClass = monthSepByDate[d] ? ' att-month-sep' : '';
					html += '<td class="att-cell' + sepClass + '">' + inner + '</td>';
				});
				var total = dates.length;
				var rate = total ? Math.round(((present + late) / total) * 100) : 0;
				html += '<td class="att-sticky-summary att-sticky-s1 att-sum-cell att-sum-p att-summary-sep" title="Present">' + present + '</td>';
				html += '<td class="att-sticky-summary att-sticky-s2 att-sum-cell att-sum-l" title="Late">' + late + '</td>';
				html += '<td class="att-sticky-summary att-sticky-s3 att-sum-cell att-sum-a" title="Absent">' + absent + '</td>';
				html += '<td class="att-sticky-summary att-sticky-s4 att-sum-cell att-sum-nm" title="Not marked">' + notMarked + '</td>';
				html += '<td class="att-sticky-summary att-sticky-s5 att-sum-cell att-sum-rate att-sum-r" title="Attendance rate">' + rate + '</td>';
				html += '</tr>';
			});
			html += '</tbody>';
			html += '</table>';
			html += '</div>';
			html += '</div>';
			html += '</div>';
			html += '</div>';
			attendanceCards.innerHTML = html;
			users = activeRegisterUsers;
			attendanceCards.querySelectorAll('[data-attendance-user-status]').forEach(function(button){
				button.addEventListener('click', function(){
					var status = button.getAttribute('data-attendance-user-status');
					users = status === 'disabled' ? disabledRegisterUsers : activeRegisterUsers;
					attendanceCards.querySelectorAll('[data-attendance-user-status]').forEach(function(tab){
						tab.classList.toggle('active', tab === button);
					});
					attendanceCards.querySelectorAll('[data-attendance-user-row]').forEach(function(row){
						row.hidden = (row.getAttribute('data-disabled') === '1') !== (status === 'disabled');
					});
				});
			});

			function buildExportMatrix(useIcons){
				function remarkToCell(remark){
					var r = (remark || '').toString();
					if(useIcons){
						if(r === 'PRESENT') return '\u2714';
						if(r === 'LATE') return '\u2714';
						if(r === 'ABSENT') return '\u2716';
						return '\u2014';
					}
					return r || 'NM';
				}
				function remarkToMeta(remark){
					var r = (remark || '').toString();
					if(r === 'PRESENT') return {code: 'PRESENT', icon: '\u2714'};
					if(r === 'LATE') return {code: 'LATE', icon: '\u2714'};
					if(r === 'ABSENT') return {code: 'ABSENT', icon: '\u2716'};
					return {code: 'NM', icon: '\u2014'};
				}
				var header = ['Staff'];
				dates.forEach(function(d){ header.push(d); });
				header.push('P');
				header.push('L');
				header.push('A');
				header.push('NM');
				header.push('%');
				var rows = [header];
				users.forEach(function(u){
					var present = 0, late = 0, absent = 0, notMarked = 0;
					var row = [u.name];
					dates.forEach(function(d){
						var cell = (statusByUserDate[u.id] && statusByUserDate[u.id][d]) ? statusByUserDate[u.id][d] : null;
						var remark = cell ? cell.remark : '';
						if(remark === 'PRESENT') present++;
						else if(remark === 'LATE') late++;
						else if(remark === 'ABSENT') absent++;
						else notMarked++;
						if(useIcons){
							var meta = remarkToMeta(remark);
							row.push({content: meta.icon, remark: meta.code});
						}else{
							row.push(remarkToCell(remark));
						}
					});
					var total = dates.length;
					var rate = total ? Math.round(((present + late) / total) * 100) : 0;
					row.push(present);
					row.push(late);
					row.push(absent);
					row.push(notMarked);
					row.push(rate);
					rows.push(row);
				});
				return rows;
			}

			function getReportHeaderLines(){
				var appName = '';
				try{ appName = (document && document.title) ? String(document.title) : ''; }catch(e){ appName = ''; }
				if(!appName){ appName = 'Attendance'; }
				var rangeLabel = '';
				if(dates && dates.length){
					rangeLabel = dates[0] + ' to ' + dates[dates.length - 1];
				}
				var generatedBy = '';
				try{ generatedBy = (window && window.ATT_GENERATED_BY) ? String(window.ATT_GENERATED_BY) : ''; }catch(e2){ generatedBy = ''; }
				var now = new Date();
				var stamp = now.toLocaleString ? now.toLocaleString() : (now.toDateString() + ' ' + now.toTimeString());
				return [appName, 'Attendance Register Sheet', (rangeLabel ? ('Range: ' + rangeLabel) : ''), (generatedBy ? ('Generated by: ' + generatedBy) : ''), ('Generated: ' + stamp)];
			}

			function buildExcelWorkbookHtml(){
				var matrix = buildExportMatrix(true);
				var lines = getReportHeaderLines();
				var monthSepByDate = {};
				var prevMk = '';
				for(var mdi=0; mdi<dates.length; mdi++){
					var dd = dates[mdi];
					var mk = monthKey(dd);
					if(prevMk && mk !== prevMk){ monthSepByDate[dd] = true; }
					prevMk = mk;
				}
				function sepStyle(d){ return monthSepByDate[d] ? 'border-left:3px solid #333 !important;' : ''; }
				function computeAnalytics(){
					var res = {
						staffCount: Math.max(0, matrix.length - 1),
						daysCount: (dates && dates.length) ? dates.length : 0,
						totalMarks: 0,
						present: 0,
						late: 0,
						absent: 0,
						notMarked: 0,
						overallRate: 0,
						avgStaffRate: 0,
						top: [],
						bottom: []
					};
					res.totalMarks = res.staffCount * res.daysCount;
					if(matrix.length <= 1) return res;
					var sumRate = 0;
					var list = [];
					for(var i=1;i<matrix.length;i++){
						var r = matrix[i];
						var base = 1 + res.daysCount;
						var p = parseInt(r[base + 0], 10) || 0;
						var l = parseInt(r[base + 1], 10) || 0;
						var a = parseInt(r[base + 2], 10) || 0;
						var nm = parseInt(r[base + 3], 10) || 0;
						var rate = parseInt(r[base + 4], 10);
						if(isNaN(rate)){
							var den = res.daysCount || 0;
							rate = den ? Math.round(((p + l) / den) * 100) : 0;
						}
						res.present += p;
						res.late += l;
						res.absent += a;
						res.notMarked += nm;
						sumRate += rate;
						list.push({name: String(r[0] || ''), rate: rate, p: p, l: l, a: a, nm: nm});
					}
					res.overallRate = res.totalMarks ? Math.round(((res.present + res.late) / res.totalMarks) * 100) : 0;
					res.avgStaffRate = res.staffCount ? Math.round(sumRate / res.staffCount) : 0;
					list.sort(function(a,b){ return (b.rate - a.rate) || (a.name > b.name ? 1 : -1); });
					res.top = list.slice(0, 5);
					var list2 = list.slice(0);
					list2.sort(function(a,b){ return (a.rate - b.rate) || (a.name > b.name ? 1 : -1); });
					res.bottom = list2.slice(0, 5);
					return res;
				}
				var analytics = computeAnalytics();
				var css = '';
				css += 'table{border-collapse:collapse;font-family:Arial,sans-serif;font-size:11pt;}';
				css += 'td,th{border:1px solid #cfd4da;padding:6px;vertical-align:middle;}';
				css += '.hdr1{font-size:16pt;font-weight:bold;border:none;padding:2px 0 10px 0;}';
				css += '.hdr2{font-size:12pt;font-weight:bold;border:none;padding:0 0 6px 0;}';
				css += '.hdr3{font-size:10pt;color:#555;border:none;padding:0 0 2px 0;}';
				css += '.month{background:#198754;color:#fff;text-align:center;font-weight:bold;}';
				css += '.day{background:#f2f4f7;text-align:center;font-weight:bold;}';
				css += '.staff{font-weight:bold;white-space:nowrap;}';
				css += '.sumhead{background:#e9ecef;text-align:center;font-weight:bold;}';
				css += '.p{background:#d1e7dd;text-align:center;font-weight:bold;}';
				css += '.l{background:#fff3cd;text-align:center;font-weight:bold;}';
				css += '.a{background:#f8d7da;text-align:center;font-weight:bold;}';
				css += '.nm{background:#f1f3f5;text-align:center;font-weight:bold;}';
				css += '.cell{width:28px;text-align:center;font-weight:bold;}';
				css += '.rate{background:#e7f1ff;text-align:center;font-weight:bold;}';
				css += '.legend-title{border:none;padding:14px 0 6px 0;font-weight:bold;font-size:11pt;}';
				css += '.legend-table td{border:1px solid #cfd4da;padding:6px;}';
				css += '.legend-swatch{width:28px;text-align:center;font-weight:bold;}';
				css += '.kpi-wrap{margin-top:12px;}';
				css += '.kpi-h{background:#e9ecef;font-weight:bold;}';
				css += '.kpi-v{font-weight:bold;text-align:center;}';
				css += '.mini{font-size:10pt;color:#444;}';

				var totalCols = 1 + dates.length + 5;
				var html = '';
				html += '<html><head><meta charset="UTF-8"><style>' + css + '</style></head><body>';
				html += '<table>';
				html += '<tr><td class="hdr1" colspan="' + totalCols + '">' + esc(lines[0] || '') + '</td></tr>';
				html += '<tr><td class="hdr2" colspan="' + totalCols + '">' + esc(lines[1] || '') + '</td></tr>';
				if(lines[2]) html += '<tr><td class="hdr3" colspan="' + totalCols + '">' + esc(lines[2]) + '</td></tr>';
				if(lines[3]) html += '<tr><td class="hdr3" colspan="' + totalCols + '">' + esc(lines[3]) + '</td></tr>';
				if(lines[4]) html += '<tr><td class="hdr3" colspan="' + totalCols + '">' + esc(lines[4]) + '</td></tr>';
				html += '<tr><td colspan="' + totalCols + '" style="border:none;height:8px"></td></tr>';

				// month row
				html += '<tr>';
				html += '<th class="month">Staff</th>';
				monthGroups.forEach(function(g, gi){
					var st = gi ? 'border-left:3px solid #333 !important;' : '';
					html += '<th class="month" style="' + st + '" colspan="' + g.dates.length + '">' + esc(g.label) + '</th>';
				});
				html += '<th class="month" style="border-left:3px solid #333 !important;" colspan="5">Summary</th>';
				html += '</tr>';

				// day row
				html += '<tr>';
				html += '<th class="day">&nbsp;</th>';
				dates.forEach(function(d){
					var dl = dayLabel(d);
					var dow2 = dl.dow ? String(dl.dow).substr(0,2) : '';
					html += '<th class="day" style="' + sepStyle(d) + '">' + esc(dl.num) + ' ' + esc(dow2) + '</th>';
				});
				html += '<th class="sumhead" style="border-left:3px solid #333 !important;">P</th>';
				html += '<th class="sumhead">L</th>';
				html += '<th class="sumhead">A</th>';
				html += '<th class="sumhead">NM</th>';
				html += '<th class="sumhead">%</th>';
				html += '</tr>';

				// data rows
				for(var i=1;i<matrix.length;i++){
					var r = matrix[i];
					html += '<tr>';
					html += '<td class="staff">' + esc(r[0]) + '</td>';
					for(var c=1;c<1+dates.length;c++){
						var cell = r[c];
						var content = '';
						var remark = 'NM';
						if(cell && typeof cell === 'object'){
							content = cell.content || '';
							remark = cell.remark || 'NM';
						}else{
							content = cell;
						}
						var cls = 'nm';
						if(remark === 'PRESENT') cls = 'p';
						else if(remark === 'LATE') cls = 'l';
						else if(remark === 'ABSENT') cls = 'a';
						html += '<td class="cell ' + cls + '" style="' + sepStyle(dates[c-1]) + '">' + esc(content) + '</td>';
					}
					var base = 1 + dates.length;
					html += '<td class="p" style="border-left:3px solid #333 !important;">' + esc(r[base + 0]) + '</td>';
					html += '<td class="l">' + esc(r[base + 1]) + '</td>';
					html += '<td class="a">' + esc(r[base + 2]) + '</td>';
					html += '<td class="nm">' + esc(r[base + 3]) + '</td>';
					html += '<td class="rate">' + esc(r[base + 4]) + '</td>';
					html += '</tr>';
				}

				html += '</table>';
				// legend
				html += '<table class="legend-table" style="margin-top:14px">';
				html += '<tr><td class="legend-title" colspan="2" style="border:none">Key</td></tr>';
				html += '<tr><td class="legend-swatch p">\u2714</td><td>Present</td></tr>';
				html += '<tr><td class="legend-swatch l">\u2714</td><td>Late</td></tr>';
				html += '<tr><td class="legend-swatch a">\u2716</td><td>Absent</td></tr>';
				html += '<tr><td class="legend-swatch nm">\u2014</td><td>Not marked</td></tr>';
				html += '</table>';
				// metrics
				html += '<table class="kpi-wrap" style="margin-top:16px">';
				html += '<tr><td class="legend-title" colspan="6" style="border:none">Metrics & Analytics</td></tr>';
				html += '<tr>';
				html += '<td class="kpi-h">Staff</td><td class="kpi-v">' + esc(analytics.staffCount) + '</td>';
				html += '<td class="kpi-h">Days</td><td class="kpi-v">' + esc(analytics.daysCount) + '</td>';
				html += '<td class="kpi-h">Total Marks</td><td class="kpi-v">' + esc(analytics.totalMarks) + '</td>';
				html += '</tr>';
				html += '<tr>';
				html += '<td class="kpi-h">Present</td><td class="kpi-v p">' + esc(analytics.present) + '</td>';
				html += '<td class="kpi-h">Late</td><td class="kpi-v l">' + esc(analytics.late) + '</td>';
				html += '<td class="kpi-h">Absent</td><td class="kpi-v a">' + esc(analytics.absent) + '</td>';
				html += '</tr>';
				html += '<tr>';
				html += '<td class="kpi-h">Not marked</td><td class="kpi-v nm">' + esc(analytics.notMarked) + '</td>';
				html += '<td class="kpi-h">Overall Rate</td><td class="kpi-v rate">' + esc(analytics.overallRate) + '%</td>';
				html += '<td class="kpi-h">Avg Staff Rate</td><td class="kpi-v rate">' + esc(analytics.avgStaffRate) + '%</td>';
				html += '</tr>';
				html += '</table>';
				// highlights
				html += '<table class="kpi-wrap" style="margin-top:12px">';
				html += '<tr><td class="kpi-h" colspan="2">Top 5 Staff (by %)</td><td class="kpi-h" colspan="2">Bottom 5 Staff (by %)</td></tr>';
				html += '<tr><td class="kpi-h">Name</td><td class="kpi-h">%</td><td class="kpi-h">Name</td><td class="kpi-h">%</td></tr>';
				for(var ti=0;ti<5;ti++){
					var t = analytics.top[ti] || null;
					var b = analytics.bottom[ti] || null;
					html += '<tr>';
					html += '<td class="mini">' + esc(t ? t.name : '') + '</td><td class="kpi-v">' + esc(t ? t.rate : '') + '</td>';
					html += '<td class="mini">' + esc(b ? b.name : '') + '</td><td class="kpi-v">' + esc(b ? b.rate : '') + '</td>';
					html += '</tr>';
				}
				html += '</table>';
				html += '</body></html>';
				return html;
			}

			function buildXlsxWorkbook(){
				if(!window.XLSX) return null;
				var XLSX = window.XLSX;
				var wb = XLSX.utils.book_new();
				var ws_data = [];
				var headerLines = getReportHeaderLines();
				var i;
				for(i=0;i<headerLines.length;i++){
					if(headerLines[i]) ws_data.push([headerLines[i]]);
				}
				ws_data.push([]);

				var totalCols = 1 + dates.length + 5;
				var monthRow = [];
				monthRow.push('Staff');
				for(i=0;i<dates.length;i++){ monthRow.push(''); }
				monthRow.push(''); monthRow.push(''); monthRow.push(''); monthRow.push(''); monthRow.push('');
				ws_data.push(monthRow);

				var dayRow = [];
				dayRow.push('');
				for(i=0;i<dates.length;i++){
					var dl = dayLabel(dates[i]);
					var dow2 = dl.dow ? String(dl.dow).substr(0,2) : '';
					dayRow.push(String(dl.num) + ' ' + dow2);
				}
				dayRow.push('P'); dayRow.push('L'); dayRow.push('A'); dayRow.push('NM'); dayRow.push('%');
				ws_data.push(dayRow);

				// body rows
				for(i=1;i<matrix.length;i++){
					var r = matrix[i];
					var out = [];
					out.push(r[0]);
					var c;
					for(c=1;c<1+dates.length;c++){
						var cell = r[c];
						if(cell && typeof cell === 'object') out.push(cell.content || '');
						else out.push(cell || '');
					}
					var base = 1 + dates.length;
					out.push(r[base + 0]);
					out.push(r[base + 1]);
					out.push(r[base + 2]);
					out.push(r[base + 3]);
					out.push(r[base + 4]);
					ws_data.push(out);
				}

				var ws = XLSX.utils.aoa_to_sheet(ws_data);
				ws['!cols'] = [];
				ws['!cols'][0] = {wch: 24};
				for(i=1;i<=dates.length;i++) ws['!cols'][i] = {wch: 4};
				ws['!cols'][1 + dates.length] = {wch: 5};
				ws['!cols'][2 + dates.length] = {wch: 5};
				ws['!cols'][3 + dates.length] = {wch: 5};
				ws['!cols'][4 + dates.length] = {wch: 5};
				ws['!cols'][5 + dates.length] = {wch: 5};

				// merges for month headers
				ws['!merges'] = ws['!merges'] || [];
				// header line merges across all columns
				var hdrRows = 0;
				for(i=0;i<headerLines.length;i++){ if(headerLines[i]) hdrRows++; }
				for(i=0;i<hdrRows;i++){
					ws['!merges'].push({s:{r:i,c:0}, e:{r:i,c: totalCols-1}});
				}
				// month row (after hdrRows + 1 blank row)
				var monthRowIndex = hdrRows + 1;
				var colStart = 1;
				for(i=0;i<monthGroups.length;i++){
					var span = monthGroups[i].dates.length;
					ws['!merges'].push({s:{r:monthRowIndex,c:colStart}, e:{r:monthRowIndex,c:colStart + span - 1}});
					var cellRef = XLSX.utils.encode_cell({r:monthRowIndex,c:colStart});
					ws[cellRef] = ws[cellRef] || {t:'s', v:''};
					ws[cellRef].v = monthGroups[i].label;
					colStart += span;
				}
				// summary header merge
				ws['!merges'].push({s:{r:monthRowIndex,c:1+dates.length}, e:{r:monthRowIndex,c: totalCols-1}});
				var sumRef = XLSX.utils.encode_cell({r:monthRowIndex,c:1+dates.length});
				ws[sumRef] = ws[sumRef] || {t:'s', v:''};
				ws[sumRef].v = 'Summary';

				XLSX.utils.book_append_sheet(wb, ws, 'Register');
				return wb;
			}

			async function exportExcel(){
				try { await ensureAttendanceXlsxLib(); } catch (eLib) {}
				function exportStamp(){
					function pad(n){ return (n < 10) ? ('0' + n) : String(n); }
					var d = new Date();
					return d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate())
						+ '_' + pad(d.getHours()) + '-' + pad(d.getMinutes()) + '-' + pad(d.getSeconds());
				}
				var stamp = exportStamp();
				var xlsxName = 'attendance_register_' + stamp + '.xlsx';
				var pdfName = 'attendance_register_' + stamp + '.pdf';

				function downloadServerXlsx(){
					var mode = attendanceModeSelect ? attendanceModeSelect.value : 'range';
					var payload = { action: 'export_register_xlsx', mode: mode };
					if (mode === 'specific') {
						payload.date = specificDateInput ? specificDateInput.value : '';
					} else {
						payload.startDate = startDateInput ? startDateInput.value : '';
						payload.endDate = endDateInput ? endDateInput.value : '';
					}
					if (mode === 'student' || mode === 'view') {
						payload.staffIds = getSelectedStaffIds();
					}
					var xhr = new XMLHttpRequest();
					xhr.open('POST', '', true);
					xhr.responseType = 'blob';
					xhr.setRequestHeader('Content-Type', 'application/json');
					xhr.onreadystatechange = function(){
						if(xhr.readyState !== 4) return;
						if(xhr.status !== 200){
							try{ alert('Failed to export Excel (.xlsx).'); }catch(e){}
							return;
						}
						try{
							var blob = xhr.response;
							var url = URL.createObjectURL(blob);
							var a = document.createElement('a');
							a.href = url;
							a.download = xlsxName;
							document.body.appendChild(a);
							a.click();
							document.body.removeChild(a);
							setTimeout(function(){ URL.revokeObjectURL(url); }, 1000);
						}catch(e2){
							try{ alert('Failed to export Excel (.xlsx).'); }catch(e3){}
						}
					};
					xhr.send(JSON.stringify(payload));
				}

				var usedXlsx = false;
				try{
					if(!window.XLSX){
						try{ console.log('[Attendance Export] XLSX library not loaded; using server .xlsx export'); }catch(e0){}
					}else{
						var wb = buildXlsxWorkbook();
						if(wb && window.XLSX && window.XLSX.write){
							var out = window.XLSX.write(wb, {bookType:'xlsx', type:'array'});
							var blob = new Blob([out], {type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'});
							var url = URL.createObjectURL(blob);
							var a = document.createElement('a');
							a.href = url;
							a.download = xlsxName;
							document.body.appendChild(a);
							a.click();
							document.body.removeChild(a);
							setTimeout(function(){ URL.revokeObjectURL(url); }, 1000);
							usedXlsx = true;
							return;
						}
						try{ console.log('[Attendance Export] XLSX workbook build/write failed; falling back to .xls'); }catch(e1){}
					}
				}catch(e){
					try{ console.log('[Attendance Export] XLSX export threw error:', e); }catch(e2){}
				}
				if(!usedXlsx){
					downloadServerXlsx();
					return;
				}
			}

			async function exportPdf(){
				try {
					await ensureAttendancePdfLibs();
				} catch (eLib) {
					alert('PDF export is not available.');
					return;
				}
				function exportStamp(){
					function pad(n){ return (n < 10) ? ('0' + n) : String(n); }
					var d = new Date();
					return d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate())
						+ '_' + pad(d.getHours()) + '-' + pad(d.getMinutes()) + '-' + pad(d.getSeconds());
				}
				var stamp = exportStamp();
				var matrix = buildExportMatrix(true);
				var allDates = (dates && dates.slice) ? dates.slice(0) : [];
				function buildMonthGroupsFromDates(dArr){
					var out = [];
					var curr = null;
					for(var i=0;i<dArr.length;i++){
						var d = dArr[i];
						var mk = monthKey(d);
						if(!curr || curr.key !== mk){
							curr = {key: mk, label: monthLabel(d), dates: []};
							out.push(curr);
						}
						curr.dates.push(d);
					}
					return out;
				}
				var allMonthGroups = buildMonthGroupsFromDates(allDates);
				var jsPDFRef = (window.jspdf && window.jspdf.jsPDF) ? window.jspdf.jsPDF : (window.jsPDF || null);
				var hasGlobalAutoTable = (typeof window.jspdfAutoTable === 'function');
				if(!jsPDFRef){
					alert('PDF export is not available.');
					return;
				}
				var maxMonthDates = 0;
				if(allMonthGroups && allMonthGroups.length){
					for(var mgi=0;mgi<allMonthGroups.length;mgi++){
						var ml = (allMonthGroups[mgi] && allMonthGroups[mgi].dates) ? allMonthGroups[mgi].dates.length : 0;
						if(ml > maxMonthDates) maxMonthDates = ml;
					}
				}
				if(!maxMonthDates){
					maxMonthDates = (allDates && allDates.length) ? allDates.length : 0;
				}
				var colsCount = 1 + maxMonthDates + 5;
				var pageFormat = 'a4';
				if(colsCount > 55) pageFormat = 'a2';
				else if(colsCount > 38) pageFormat = 'a3';
				var doc;
				try{
					doc = new jsPDFRef({orientation:'landscape', unit:'pt', format: pageFormat});
				}catch(e){
					alert('PDF export is not available.');
					return;
				}
				var hasAutoTable = (doc && typeof doc.autoTable === 'function');
				if(!hasAutoTable && !hasGlobalAutoTable){
					alert('PDF export is not available.');
					return;
				}
				var sheets = [];
				if(allMonthGroups && allMonthGroups.length > 1){
					for(var sgi=0;sgi<allMonthGroups.length;sgi++){
						sheets.push({label: allMonthGroups[sgi].label, dates: allMonthGroups[sgi].dates, monthGroups: [allMonthGroups[sgi]]});
					}
				}else{
					sheets.push({label: '', dates: allDates, monthGroups: allMonthGroups && allMonthGroups.length ? allMonthGroups : [{label:'', dates: allDates}]});
				}
				for(var si=0;si<sheets.length;si++){
					// Temporarily reuse existing rendering code by swapping the active slice variables
					dates = sheets[si].dates;
					monthGroups = sheets[si].monthGroups;
					if(si > 0){ doc.addPage(); }
					var boundaryCols = {};
					// Bold separator between Summary block and the day columns
					boundaryCols[6] = true;
					var body = [];
					for(var ri=1;ri<matrix.length;ri++){
						var row = [];
						row.push(matrix[ri][0]);
						var p=0,l=0,a=0,nm=0;
						var dateCells = [];
						for(var di=0;di<dates.length;di++){
							var d = dates[di];
							var allIdx = allDates.indexOf(d);
							var cell = (allIdx >= 0) ? matrix[ri][1 + allIdx] : null;
							var remark = 'NM';
							if(cell && typeof cell === 'object'){
								remark = cell.remark || 'NM';
							}
							dateCells.push({content:'', remark: remark});
							if(remark === 'PRESENT') p++;
							else if(remark === 'LATE') l++;
							else if(remark === 'ABSENT') a++;
							else nm++;
						}
						var den = dates.length || 0;
						var rate = den ? Math.round(((p + l) / den) * 100) : 0;
						// Summary first (keeps it on the left; never pushed to a separate horizontal page)
						row.push(String(p));
						row.push(String(l));
						row.push(String(a));
						row.push(String(nm));
						row.push(String(rate));
						// Then the day columns
						for(var dci=0; dci<dateCells.length; dci++){
							row.push(dateCells[dci]);
						}
						body.push(row);
					}
					var analytics = (function(){
						var staffCount = body.length;
						var daysCount = dates.length;
						var totalMarks = staffCount * daysCount;
						var present = 0, late = 0, absent = 0, notMarked = 0;
						var sumRate = 0;
						var list = [];
						for(var i=0;i<body.length;i++){
							var r = body[i];
							// Row layout is: [0]=Staff, [1]=P,[2]=L,[3]=A,[4]=NM,[5]=%, then day cells...
							var p = parseInt(r[1], 10) || 0;
							var l = parseInt(r[2], 10) || 0;
							var a = parseInt(r[3], 10) || 0;
							var nm = parseInt(r[4], 10) || 0;
							var rate = parseInt(r[5], 10);
							if(isNaN(rate)){
								var den = daysCount || 0;
								rate = den ? Math.round(((p + l) / den) * 100) : 0;
							}
							present += p; late += l; absent += a; notMarked += nm;
							sumRate += rate;
							list.push({name: String(r[0] || ''), rate: rate});
						}
						var overallRate = totalMarks ? Math.round(((present + late) / totalMarks) * 100) : 0;
						var avgStaffRate = staffCount ? Math.round(sumRate / staffCount) : 0;
						list.sort(function(a,b){ return (b.rate - a.rate) || (a.name > b.name ? 1 : -1); });
						var top = list.slice(0,5);
						var list2 = list.slice(0);
						list2.sort(function(a,b){ return (a.rate - b.rate) || (a.name > b.name ? 1 : -1); });
						var bottom = list2.slice(0,5);
						return {staffCount:staffCount, daysCount:daysCount, totalMarks:totalMarks, present:present, late:late, absent:absent, notMarked:notMarked, overallRate:overallRate, avgStaffRate:avgStaffRate, top:top, bottom:bottom};
					})();
				var headerLines = getReportHeaderLines();
				doc.setFontSize(13);
				doc.text(String(headerLines[0] || ''), 40, 28);
				doc.setFontSize(11);
				doc.text(String(headerLines[1] || ''), 40, 45);
				doc.setFontSize(9);
				if(headerLines[2]) doc.text(String(headerLines[2]), 40, 60);
				if(headerLines[3]) doc.text(String(headerLines[3]), 40, 72);
				if(headerLines[4]) doc.text(String(headerLines[4]), 40, 84);
				var sheetHasLabel = !!(sheets[si].label);
				if(sheetHasLabel) doc.text(String(sheets[si].label), 40, 96);

				// Build grouped headers like the UI (months row + days row)
				var headRow1 = [];
				var headRow2 = [];
				headRow1.push({content: 'Staff', rowSpan: 2, styles: {fillColor:[25,135,84], textColor:255, halign:'left', fontStyle:'bold'}});
				headRow1.push({content: 'Summary', colSpan: 5, styles: {fillColor:[25,135,84], textColor:255, halign:'center', fontStyle:'bold'}});
				monthGroups.forEach(function(g){
					headRow1.push({content: g.label, colSpan: g.dates.length, styles: {fillColor:[25,135,84], textColor:255, halign:'center', fontStyle:'bold'}});
				});
				headRow2.push({content:'P', styles:{fillColor:[233,236,239], textColor:20, halign:'center', fontStyle:'bold'}});
				headRow2.push({content:'L', styles:{fillColor:[233,236,239], textColor:20, halign:'center', fontStyle:'bold'}});
				headRow2.push({content:'A', styles:{fillColor:[233,236,239], textColor:20, halign:'center', fontStyle:'bold'}});
				headRow2.push({content:'NM', styles:{fillColor:[233,236,239], textColor:20, halign:'center', fontStyle:'bold'}});
				headRow2.push({content:'%', styles:{fillColor:[233,236,239], textColor:20, halign:'center', fontStyle:'bold'}});
				dates.forEach(function(d){
					var dl = dayLabel(d);
					var dow2 = dl.dow ? String(dl.dow).substr(0,2) : '';
					headRow2.push({content: (dl.num + ' ' + dow2), styles: {fillColor:[242,244,247], textColor:20, halign:'center', fontStyle:'bold'}});
				});

				var dateCols = (dates && dates.length) ? dates.length : 0;
				var fontSize = 8;
				var cellPadding = 2;
				if(dateCols > 45){ fontSize = 6; cellPadding = 1; }
				else if(dateCols > 34){ fontSize = 7; cellPadding = 1; }
				else if(dateCols > 25){ fontSize = 7; cellPadding = 2; }
				var staffColW = (dateCols > 40) ? 120 : 150;
				var sumColW = (dateCols > 34) ? 18 : 20;
				var pctColW = (dateCols > 34) ? 20 : 24;
				// Dynamically size day columns so headers fit and the sheet spans the page when possible
				var pageWidthPts = (doc && doc.internal && doc.internal.pageSize && doc.internal.pageSize.getWidth) ? doc.internal.pageSize.getWidth() : 0;
				var marginL = 20;
				var marginR = 20;
				var sumBlockW = (sumColW * 4) + pctColW;
				var dateColW = (dateCols > 45) ? 10 : (dateCols > 34 ? 11 : 13);
				if(pageWidthPts && dateCols){
					var avail = pageWidthPts - marginL - marginR - staffColW - sumBlockW - 10;
					var ideal = Math.floor(avail / dateCols);
					var minW = (dateCols > 45) ? 9 : 12;
					var maxW = (dateCols > 34) ? 13 : 18;
					if(ideal < minW) ideal = minW;
					if(ideal > maxW) ideal = maxW;
					dateColW = ideal;
				}
				var colStyles = {0: {cellWidth: staffColW}};
				// Summary columns are now indices 1..5
				colStyles[1] = {cellWidth: sumColW};
				colStyles[2] = {cellWidth: sumColW};
				colStyles[3] = {cellWidth: sumColW};
				colStyles[4] = {cellWidth: sumColW};
				colStyles[5] = {cellWidth: pctColW};
				// Date columns start at index 6
				for(var ci=0; ci<dateCols; ci++){
					colStyles[6 + ci] = {cellWidth: dateColW};
				}
				var opts = {
					head: [headRow1, headRow2],
					body: body,
					startY: sheetHasLabel ? 112 : 98,
					theme: 'grid',
					styles: {fontSize: fontSize, cellPadding: cellPadding, valign: 'middle'},
					margin: {left: 20, right: 20},
					columnStyles: colStyles,
					tableWidth: 'auto',
					horizontalPageBreak: (dateCols > 55),
					didDrawCell: function(data){
						if(!data || !data.cell || !data.column) return;
						var ci = data.column.index;
						if(!boundaryCols[ci]) return;
						if(data.section !== 'head' && data.section !== 'body') return;
						var x = data.cell.x;
						var y = data.cell.y;
						var h = data.cell.height;
						if(typeof doc.setLineWidth === 'function') doc.setLineWidth(1.2);
						if(typeof doc.setDrawColor === 'function') doc.setDrawColor(40);
						if(typeof doc.line === 'function') doc.line(x, y, x, y + h);
					},
					didParseCell: function(data){
						// Color body cells to match UI
						if(!data || !data.cell) return;
						if(data.section !== 'body') return;
						var colIndex = data.column.index;
						// date columns are 6..(5+dates.length)
						if(colIndex >= 6 && colIndex <= (5 + dates.length)){
							var raw = data.cell.raw;
							var remark = raw && raw.remark ? raw.remark : 'NM';
							var glyph = '';
							data.cell.styles.halign = 'center';
							data.cell.styles.fontStyle = 'bold';
							if(remark === 'PRESENT' || remark === 'LATE'){
								glyph = '4';
								data.cell.styles.font = 'zapfdingbats';
								data.cell.text = [glyph];
							}else if(remark === 'ABSENT'){
								glyph = '8';
								data.cell.styles.font = 'zapfdingbats';
								data.cell.text = [glyph];
							}else{
								data.cell.styles.font = 'helvetica';
								data.cell.text = ['—'];
								data.cell.styles.fontStyle = 'normal';
							}
							if(remark === 'PRESENT') data.cell.styles.fillColor = [209,231,221];
							else if(remark === 'LATE') data.cell.styles.fillColor = [255,243,205];
							else if(remark === 'ABSENT') data.cell.styles.fillColor = [248,215,218];
							else data.cell.styles.fillColor = [241,243,245];
						}
						// summary columns are now right after Staff (1..5)
						if(colIndex === 1) data.cell.styles.fillColor = [209,231,221];
						if(colIndex === 2) data.cell.styles.fillColor = [255,243,205];
						if(colIndex === 3) data.cell.styles.fillColor = [248,215,218];
						if(colIndex === 4) data.cell.styles.fillColor = [241,243,245];
						if(colIndex === 5) data.cell.styles.fillColor = [231,241,255];
					}
				};
				if(hasAutoTable){
					doc.autoTable(opts);
				}else{
					window.jspdfAutoTable(doc, opts);
				}
				// legend + metrics (side-by-side)
				var legendStartY = (doc.lastAutoTable && doc.lastAutoTable.finalY) ? (doc.lastAutoTable.finalY + 14) : null;
				if(!legendStartY){ legendStartY = 86; }
				var analyticsLeft = 40;
				var analyticsGap = 20;
				var metricsLeft = 0;
				var legendBody = [
					[{content:'4', styles:{font:'zapfdingbats', halign:'center', fillColor:[209,231,221], fontStyle:'bold'}}, 'Present'],
					[{content:'4', styles:{font:'zapfdingbats', halign:'center', fillColor:[255,243,205], fontStyle:'bold'}}, 'Late'],
					[{content:'8', styles:{font:'zapfdingbats', halign:'center', fillColor:[248,215,218], fontStyle:'bold'}}, 'Absent'],
					[{content:'—', styles:{font:'helvetica', halign:'center', fillColor:[241,243,245]}}, 'Not marked']
				];
				var legendW = 220;
				var metricsW = 560;
				metricsLeft = analyticsLeft + legendW + analyticsGap;
				var legendOpts = {
					startY: legendStartY,
					head: [[{content:'Key', colSpan:2, styles:{fillColor:[233,236,239], textColor:[20,20,20], fontStyle:'bold'}}]],
					body: legendBody,
					theme: 'grid',
					tableWidth: legendW,
					margin: {left: analyticsLeft},
					styles: {fontSize: 9, cellPadding: 4},
					columnStyles: {0:{cellWidth:34, halign:'center'}, 1:{cellWidth:186}}
				};
				if(hasAutoTable){
					doc.autoTable(legendOpts);
				}else{
					window.jspdfAutoTable(doc, legendOpts);
				}
				var yLegendFinal = (doc.lastAutoTable && doc.lastAutoTable.finalY) ? doc.lastAutoTable.finalY : legendStartY;
				// metrics & analytics
				var mStartY = legendStartY;
				var metricsBody = [
					['Staff', String(analytics.staffCount), 'Days', String(analytics.daysCount), 'Total Marks', String(analytics.totalMarks)],
					['Present', String(analytics.present), 'Late', String(analytics.late), 'Absent', String(analytics.absent)],
					['Not marked', String(analytics.notMarked), 'Overall Rate', String(analytics.overallRate) + '%', 'Avg Staff Rate', String(analytics.avgStaffRate) + '%']
				];
				var metricsOpts = {
					startY: mStartY,
					head: [[{content:'Metrics & Analytics', colSpan:6, styles:{fillColor:[233,236,239], textColor:[20,20,20], fontStyle:'bold'}}]],
					body: metricsBody,
					theme: 'grid',
					tableWidth: metricsW,
					margin: {left: metricsLeft},
					styles: {fontSize: 9, cellPadding: 4},
					columnStyles: {
						0:{cellWidth:95, fontStyle:'bold'},
						1:{cellWidth:60, halign:'center'},
						2:{cellWidth:95, fontStyle:'bold'},
						3:{cellWidth:60, halign:'center'},
						4:{cellWidth:120, fontStyle:'bold'},
						5:{cellWidth:90, halign:'center'}
					}
				};
				if(hasAutoTable){ doc.autoTable(metricsOpts); } else { window.jspdfAutoTable(doc, metricsOpts); }
				var yMetricsFinal = (doc.lastAutoTable && doc.lastAutoTable.finalY) ? doc.lastAutoTable.finalY : mStartY;
				var hStartY = (Math.max(yLegendFinal, yMetricsFinal) + 12);
				var highlightsBody = [];
				for(var hi=0;hi<5;hi++){
					var t = analytics.top[hi] || null;
					var b = analytics.bottom[hi] || null;
					highlightsBody.push([t ? t.name : '', t ? (String(t.rate) + '%') : '', b ? b.name : '', b ? (String(b.rate) + '%') : '']);
				}
				var highlightsW = 520;
				var highlightsOpts = {
					startY: hStartY,
					head: [[{content:'Top 5 Staff (by %)', colSpan:2, styles:{fillColor:[233,236,239], textColor:[20,20,20], fontStyle:'bold'}}, {content:'Bottom 5 Staff (by %)', colSpan:2, styles:{fillColor:[233,236,239], textColor:[20,20,20], fontStyle:'bold'}}],
							['Name','%','Name','%']],
					body: highlightsBody,
					theme: 'grid',
					tableWidth: highlightsW,
					margin: {left: analyticsLeft},
					styles: {fontSize: 9, cellPadding: 4},
					columnStyles: {0:{cellWidth:200},1:{cellWidth:60, halign:'center', fontStyle:'bold'},2:{cellWidth:200},3:{cellWidth:60, halign:'center', fontStyle:'bold'}}
				};
				if(hasAutoTable){ doc.autoTable(highlightsOpts); } else { window.jspdfAutoTable(doc, highlightsOpts); }
				}
				doc.save('attendance_register_' + stamp + '.pdf');
				// restore
				dates = allDates;
				monthGroups = allMonthGroups;
			}

			var excelBtn = document.getElementById('attExportExcel');
			if(excelBtn){ excelBtn.onclick = exportExcel; }
			var pdfBtn = document.getElementById('attExportPdf');
			if(pdfBtn){ pdfBtn.onclick = exportPdf; }
        }

        function buildTableHTML(data, isUnmarked, date) {
            var table = '';
            table += '<table class="table table-hover attendance-table"><thead><tr>';
            table += '<th>#</th><th>Name</th><th>Time</th><th>Remarks</th>';
            table += (isUnmarked ? '<th></th>' : '');
            table += '</tr></thead><tbody>';

            data.forEach(function (user, index) {
                var remarksHtml = '';
                if (isUnmarked) {
                    remarksHtml = ''
                        + '<select class="form-select remarks remarks-pick" data-user-id="' + user.id + '">' 
                        + '<option value="">--Select--</option>'
                        + '<option value="PRESENT" ' + (user.remarks === 'PRESENT' ? 'selected' : '') + '>PRESENT</option>'
                        + '<option value="LATE" ' + (user.remarks === 'LATE' ? 'selected' : '') + '>LATE</option>'
                        + '<option value="ABSENT" ' + (user.remarks === 'ABSENT' ? 'selected' : '') + '>ABSENT</option>'
                        + '</select>'
                        + '<small class="text-danger d-none remarks-error">Remarks are required!</small>';
                } else {
                    (function () {
                        var r = (user.remarks || '').toString();
                        var up = r.toUpperCase();
                        if (up === 'PRESENT') { remarksHtml = '<span class="badge bg-success">PRESENT</span>'; return; }
                        if (up === 'LATE') { remarksHtml = '<span class="badge bg-warning text-dark">LATE</span>'; return; }
                        if (up === 'ABSENT') { remarksHtml = '<span class="badge bg-danger">ABSENT</span>'; return; }
                        remarksHtml = '<span class="badge bg-secondary">' + (r || 'N/A') + '</span>';
                    })();
                }

                table += '<tr>';
                table += '<td>' + (index + 1) + '</td>';
                table += '<td><img src="' + user.image + '" data-app-image-viewer data-app-image="' + user.image + '" class="img-thumbnail" style="width:30px; height:30px; cursor:pointer;"> ' + user.first_name + ' ' + user.last_name + disabledBadgeHtml(user) + '</td>';
                if (isUnmarked) {
                    table += '<td><input type="time" class="form-control form-control-sm" value="' + (timeToInputValue(user.time) || '') + '"></td>';
                } else {
                    table += '<td>' + formatTimeDisplay(user.time) + '</td>';
                }
                table += '<td>' + remarksHtml + '</td>';
                if (isUnmarked) {
                    table += '<td><div class="checkbox-container">'
                        + '<input type="checkbox" class="markAttendanceCheckbox d-none" data-user-id="' + user.id + '">' 
                        + '<i class="fa fa-check text-success ms-2 d-none icon-marked" aria-hidden="true"></i>'
                        + '</div></td>';
                }
                table += '</tr>';
            });

            table += '</tbody></table>';
            return table;
        }

        function buildTableRowsHTML(data, isUnmarked, date) {
            var tableRow = '';
            data.forEach(function (user, index) {
                var remarksHtml = '';
                if (isUnmarked) {
                    remarksHtml = ''
                        + '<select class="form-select remarks" data-user-id="' + user.id + '">' 
                        + '<option value="">--Select--</option>'
                        + '<option value="PRESENT" ' + (user.remarks === 'PRESENT' ? 'selected' : '') + '>PRESENT</option>'
                        + '<option value="LATE" ' + (user.remarks === 'LATE' ? 'selected' : '') + '>LATE</option>'
                        + '<option value="ABSENT" ' + (user.remarks === 'ABSENT' ? 'selected' : '') + '>ABSENT</option>'
                        + '</select>'
                        + '<small class="text-danger d-none remarks-error">Remarks are required!</small>';
                } else {
                    (function () {
                        var r = (user.remarks || '').toString();
                        var up = r.toUpperCase();
                        if (up === 'PRESENT') { remarksHtml = '<span class="badge bg-success">PRESENT</span>'; return; }
                        if (up === 'LATE') { remarksHtml = '<span class="badge bg-warning text-dark">LATE</span>'; return; }
                        if (up === 'ABSENT') { remarksHtml = '<span class="badge bg-danger">ABSENT</span>'; return; }
                        remarksHtml = '<span class="badge bg-secondary">' + (r || 'N/A') + '</span>';
                    })();
                }

                tableRow += '<tr>';
                tableRow += '<td>' + (index + 1) + '</td>';
                tableRow += '<td>' + date + '</td>';
                tableRow += '<td><img src="' + user.image + '" data-app-image-viewer data-app-image="' + user.image + '" class="img-thumbnail" style="width:30px; height:30px; cursor:pointer;"> ' + user.first_name + ' ' + user.last_name + disabledBadgeHtml(user) + '</td>';
                tableRow += '<td>' + formatTimeDisplay(user.time) + '</td>';
                tableRow += '<td>' + remarksHtml + '</td>';
                tableRow += '</tr>';
            });
            return tableRow;
        }

        function attachSubmitAttendanceForGroup(date) {
            var submitBtn = document.getElementById('submitAttendance_' + date);
            if (!submitBtn) return;
            submitBtn.addEventListener('click', function () {
                var hasErrors = false;
                var container = document.getElementById('unmarkedContainer_' + date);
                if(!container) return;
                var rows = container.querySelectorAll('tr');
                var dataToSubmit = [];
                for (var ri = 0; ri < rows.length; ri++) {
                    var row = rows[ri];
                    var checkbox = row.querySelector('.markAttendanceCheckbox');
                    if (!checkbox) continue;
                    var remarksSelect = row.querySelector('.remarks');
                    var timeInput = row.querySelector('input[type="time"]');
                    var remarksError = row.querySelector('.remarks-error');
                    var timeValue = timeInput ? timeInput.value : '';
                    var remarksValue = remarksSelect ? remarksSelect.value : '';
                    if (timeValue && !remarksValue) {
                        hasErrors = true;
                        if(remarksError){
                            remarksError.textContent = 'Remarks are required for the provided time!';
                            remarksError.classList.remove('d-none');
                        }
                        if(timeInput){ timeInput.style.borderColor = 'red'; }
                        row.style.backgroundColor = 'rgba(255,0,0,0.1)';
                        continue;
                    }
                    if (remarksValue && !timeValue) {
                        hasErrors = true;
                        if(remarksError){
                            remarksError.textContent = 'Time is required for the selected remarks!';
                            remarksError.classList.remove('d-none');
                        }
                        if(timeInput){ timeInput.style.borderColor = 'red'; }
                        row.style.backgroundColor = 'rgba(255,0,0,0.1)';
                        continue;
                    }
                    if(remarksError){ remarksError.classList.add('d-none'); }
                    if(timeInput){ timeInput.style.borderColor = ''; }
                    row.style.backgroundColor = '';
                    if (!timeValue && !remarksValue) continue;
                    dataToSubmit.push({
                        userId: checkbox.dataset.userId,
                        time: timeValue,
                        remarks: remarksValue,
                        date: date
                    });
                }
                if (hasErrors) {
                    showAlert('Please fix errors before submitting for ' + formatLongDate(date) + '!', 'danger');
                    return;
                }
                if (dataToSubmit.length === 0) {
                    showAlert('No valid attendance data to submit for ' + formatLongDate(date) + '!', 'warning');
                    return;
                }
                var payload = {
                    action: 'mark_attendance',
                    mode: 'range',
                    data: dataToSubmit,
                    date: date,
                    startDate: startDateInput.value,
                    endDate: endDateInput.value
                };
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '', true);
                xhr.setRequestHeader('Content-Type', 'application/json');
                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            try {
                                var response = JSON.parse(xhr.responseText);
                                if (response.success) {
                                    showAlert('Attendance for ' + formatLongDate(date) + ' marked successfully!', 'success');
                                    if (response.data && response.data[date]) { updateGroupCard(date, response.data[date]); }
                                } else {
                                    showAlert(response.error || 'An error occurred for ' + formatLongDate(date) + '!', 'danger');
                                }
                            } catch (error) {
                                showAlert('Invalid server response for ' + formatLongDate(date) + '!', 'danger');
                            }
                        } else {
                            showAlert('Failed to submit attendance for ' + formatLongDate(date) + '!', 'danger');
                        }
                    }
                };
                xhr.send(JSON.stringify(payload));
            });
        }

        function updateGroupCard(date, groupData) {
            fetchAttendance(); // For simplicity, re-fetch all data.
        }

        function displayAttendanceSummary(data, mode, meta) {
            // 1. Compute totals
            var malePresent = 0, maleLate = 0, maleAbsent = 0;
            var femalePresent = 0, femaleLate = 0, femaleAbsent = 0;
            var totalPresent = 0, totalLate = 0, totalAbsent = 0;

            if (mode === 'specific') {
                for (var i = 0; i < data.length; i++) {
                    var record = data[i] || {};
                    var remark = (record.remarks || '').toUpperCase();
                    var gender = (record.gender || '').toLowerCase();
                    if (remark === 'PRESENT') {
                        if (gender === 'male') malePresent++;
                        else if (gender === 'female') femalePresent++;
                        totalPresent++;
                    }
                    else if (remark === 'LATE') {
                        if (gender === 'male') maleLate++;
                        else if (gender === 'female') femaleLate++;
                        totalLate++;
                    }
                    else if (remark === 'ABSENT') {
                        if (gender === 'male') maleAbsent++;
                        else if (gender === 'female') femaleAbsent++;
                        totalAbsent++;
                    }
                }
            } else {
                // range mode
                var groups = [];
                try { groups = Object.values(data); } catch(e) { groups = []; }
                for (var gi = 0; gi < groups.length; gi++) {
                    var group = groups[gi] || {};
                    var marked = group.marked || [];
                    for (var mi = 0; mi < marked.length; mi++) {
                        var record = marked[mi] || {};
                        var remark = (record.remarks || '').toUpperCase();
                        var gender = (record.gender || '').toLowerCase();
                        if (remark === 'PRESENT') {
                            if (gender === 'male') malePresent++;
                            else if (gender === 'female') femalePresent++;
                            totalPresent++;
                        }
                        else if (remark === 'LATE') {
                            if (gender === 'male') maleLate++;
                            else if (gender === 'female') femaleLate++;
                            totalLate++;
                        }
                        else if (remark === 'ABSENT') {
                            if (gender === 'male') maleAbsent++;
                            else if (gender === 'female') femaleAbsent++;
                            totalAbsent++;
                        }
                    }
                }
            }

            var maleTotal = malePresent + maleLate + maleAbsent;
            var femaleTotal = femalePresent + femaleLate + femaleAbsent;

            // 2. Build compact summary table
            var summaryHTML = '';
            summaryHTML += '<div class="attendance-summary-mini">';
            summaryHTML += '<table class="summary-table">';
            summaryHTML += '<thead><tr><th>Details</th><th>Males</th><th>Females</th></tr></thead>';
            summaryHTML += '<tbody>';
            summaryHTML += '<tr><th class="text-muted">Present</th><td><span class="summary-circle present">' + malePresent + '</span></td><td><span class="summary-circle present">' + femalePresent + '</span></td></tr>';
            summaryHTML += '<tr><th class="text-muted">Late</th><td><span class="summary-circle total">' + maleLate + '</span></td><td><span class="summary-circle total">' + femaleLate + '</span></td></tr>';
            summaryHTML += '<tr><th class="text-muted">Absent</th><td><span class="summary-circle absent">' + maleAbsent + '</span></td><td><span class="summary-circle absent">' + femaleAbsent + '</span></td></tr>';
            summaryHTML += '<tr><th class="text-muted">Total</th><td><span class="summary-circle total">' + maleTotal + '</span></td><td><span class="summary-circle total">' + femaleTotal + '</span></td></tr>';
            summaryHTML += '</tbody></table></div>';

            // 3. Inject into the mini-summary placeholder
            document.getElementById('miniAttendanceSummary').innerHTML = summaryHTML;

            var skipMain = (meta && meta.skipMain) ? true : false;
            if(skipMain){
                document.getElementById('attendanceSummary').innerHTML = '';
            }else{
                document.getElementById('attendanceSummary').innerHTML = summaryHTML;
            }

            var unmarkedCount = (meta && typeof meta.unmarkedCount === 'number') ? meta.unmarkedCount : 0;
            renderKpis({ present: totalPresent, late: totalLate, absent: totalAbsent, unmarked: unmarkedCount });

            // (Optional) If you still want the full card‑style summary below:
            // ... keep your existing export buttons logic here ...
        }

        function exportAttendance(exportType, mode) {
            var payload = { action: 'export_attendance', exportType: exportType, mode: mode };
            if (mode === 'specific') {
                payload.date = specificDateInput.value;
            } else {
                payload.startDate = startDateInput.value;
                payload.endDate = endDateInput.value;
            }

            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'attendance-controller.php', true);
            xhr.responseType = 'blob';
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;
                if (xhr.status !== 200) {
                    showAlert('Failed to export attendance', 'danger');
                    return;
                }
                var blob = xhr.response;
                var filename = (exportType === 'csv') ? 'attendance_export.csv' : 'attendance_export.pdf';
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
            };
            xhr.send(JSON.stringify(payload));
        }

        function resolveAnalyticsRange() {
            var mode = attendanceModeSelect.value;
            if (mode === 'specific') {
                var d = specificDateInput.value;
                return { startDate: d, endDate: d };
            }
            var sd = startDateInput.value;
            var ed = endDateInput.value;
            return { startDate: sd, endDate: ed };
        }

		function getSelectedStaffIds() {
			var el = document.getElementById('studentSelect');
			if (!el) return [];
			var out = [];
			var opts = el.selectedOptions || [];
			for (var i = 0; i < opts.length; i++) {
				var v = opts[i] && opts[i].value ? String(opts[i].value) : '';
				if (v) out.push(v);
			}
			return out;
		}

        function renderAnalyticsKpis(meta) {
            if (!analyticsKpis) return;
            var users = meta && meta.users ? meta.users : 0;
            var totalDays = meta && meta.totalDays ? meta.totalDays : 0;
            var presentTotal = meta && meta.presentDaysTotal ? meta.presentDaysTotal : 0;
			var lateTotal = meta && meta.lateDaysTotal ? meta.lateDaysTotal : 0;
            var absentTotal = meta && meta.absentDaysTotal ? meta.absentDaysTotal : 0;
            var markedTotal = meta && meta.markedDaysTotal ? meta.markedDaysTotal : 0;
            var expectedMarks = users * totalDays;
            var unmarkedTotal = Math.max(0, expectedMarks - markedTotal);
            var markedRate = expectedMarks > 0 ? Math.round((markedTotal / expectedMarks) * 100) : 0;
            var html = '';
            html += '<div class="kpi-card kpi-present"><div class="kpi-title">Present Days</div><div class="kpi-value">' + presentTotal + '</div><div class="kpi-sub">Across all users</div></div>';
  html += '<div class="kpi-card"><div class="kpi-title">Late Days</div><div class="kpi-value">' + lateTotal + '</div><div class="kpi-sub">After late attendance time</div></div>';
            html += '<div class="kpi-card kpi-absent"><div class="kpi-title">Absent Days</div><div class="kpi-value">' + absentTotal + '</div><div class="kpi-sub">Across all users</div></div>';
            html += '<div class="kpi-card kpi-marked"><div class="kpi-title">Marked</div><div class="kpi-value">' + markedRate + '%</div><div class="kpi-sub">' + markedTotal + '/' + expectedMarks + '</div></div>';
            html += '<div class="kpi-card kpi-unmarked"><div class="kpi-title">Not Marked</div><div class="kpi-value">' + unmarkedTotal + '</div><div class="kpi-sub">Needs attention</div></div>';
            analyticsKpis.innerHTML = html;
        }

        function renderAnalyticsTable(rows) {
            var table = document.getElementById('analyticsTable');
            if (!table) return;

            var tbody = table.querySelector('tbody') || document.getElementById('analyticsTableBody');
            if (!tbody) return;
            analyticsTableBody = tbody;

            var allData = Array.isArray(rows) ? rows.slice() : [];
            var activeData = allData.filter(function(user){ return !isDisabledUser(user); });
            var disabledData = allData.filter(isDisabledUser);
            var tabs = document.getElementById('analyticsUserTabs');
            if (tabs) {
                tabs.innerHTML = ''
                    + '<li class="nav-item"><button type="button" class="nav-link active" data-analytics-user-status="active">Active staff (' + activeData.length + ')</button></li>'
                    + '<li class="nav-item"><button type="button" class="nav-link" data-analytics-user-status="disabled">Disabled staff (' + disabledData.length + ')</button></li>';
            }

            function renderRows(status) {
                try {
                    if (typeof $ !== 'undefined' && $.fn && $.fn.DataTable && $.fn.DataTable.isDataTable(table)) {
                        $(table).DataTable().destroy();
                    }
                } catch (e) { }

                var data = (status === 'disabled' ? disabledData : activeData).slice();
                data.sort(function (a, b) {
                    var bRate = parseFloat(b.attendance_rate || 0);
                    var aRate = parseFloat(a.attendance_rate || 0);
                    if (bRate !== aRate) return bRate - aRate;
                    var bPresent = parseFloat(b.present_days || 0);
                    var aPresent = parseFloat(a.present_days || 0);
                    if (bPresent !== aPresent) return bPresent - aPresent;
                    return parseFloat(b.late_days || 0) - parseFloat(a.late_days || 0);
                });
                var html = '';
                data.forEach(function (u, idx) {
                    var fullName = ((u.first_name || '') + ' ' + (u.last_name || '')).replace(/\s+/g, ' ').trim();
                    var nameHtml = fullName + disabledBadgeHtml(u);
                    var rate = (typeof u.attendance_rate === 'number') ? u.attendance_rate : parseFloat(u.attendance_rate || 0);
                    var pillClass = 'bg-secondary';
                    if (rate >= 90) pillClass = 'bg-success';
                    else if (rate >= 70) pillClass = 'bg-primary';
                    else if (rate >= 50) pillClass = 'bg-warning text-dark';
                    else pillClass = 'bg-danger';

                    html += ''
                        + '<tr>'
                        + '<td>' + (idx + 1) + '</td>'
                        + '<td><img src="' + u.image + '" data-app-image-viewer data-app-image="' + u.image + '" class="img-thumbnail" style="width:30px; height:30px; cursor:pointer;"> ' + nameHtml + '</td>'
                        + '<td><span class="badge bg-success">' + u.present_days + '</span></td>'
                        + '<td><span class="badge bg-warning text-dark">' + (u.late_days || 0) + '</span></td>'
                        + '<td><span class="badge bg-danger">' + u.absent_days + '</span></td>'
                        + '<td><span class="badge bg-secondary">' + u.unmarked_days + '</span></td>'
                        + '<td>' + u.total_days + '</td>'
                        + '<td><span class="rate-pill ' + pillClass + '">' + rate + '%</span></td>'
                        + '</tr>';
                });
                tbody.innerHTML = html || '<tr><td colspan="8" class="text-center text-muted">No users in this tab.</td></tr>';

                try {
                    if (typeof $ !== 'undefined' && $.fn && $.fn.DataTable) {
                        $(table).DataTable({
                            pageLength: -1,
                            lengthMenu: [[-1, 10, 25, 50, 100], ['All', 10, 25, 50, 100]],
                            order: [[7, 'desc']]
                        });
                    }
                } catch (e) { }
            }

            if (tabs) {
                tabs.querySelectorAll('[data-analytics-user-status]').forEach(function(button){
                    button.addEventListener('click', function(){
                        var status = button.getAttribute('data-analytics-user-status');
                        tabs.querySelectorAll('[data-analytics-user-status]').forEach(function(tab){
                            tab.classList.toggle('active', tab === button);
                        });
                        renderRows(status);
                    });
                });
            }
            renderRows('active');
        }

        function renderAnalyticsCharts(meta, rows) {
            if (typeof Chart === 'undefined') return;
            var users = meta && meta.users ? meta.users : 0;
            var totalDays = meta && meta.totalDays ? meta.totalDays : 0;
            var presentTotal = meta && meta.presentDaysTotal ? meta.presentDaysTotal : 0;
			var lateTotal = meta && meta.lateDaysTotal ? meta.lateDaysTotal : 0;
            var absentTotal = meta && meta.absentDaysTotal ? meta.absentDaysTotal : 0;
            var markedTotal = meta && meta.markedDaysTotal ? meta.markedDaysTotal : 0;
            var expectedMarks = users * totalDays;
            var unmarkedTotal = Math.max(0, expectedMarks - markedTotal);
			var chartTextColor = function () {
				var root = document.documentElement || document.body;
				var resolved = root && window.getComputedStyle ? window.getComputedStyle(root).getPropertyValue('--text-color').trim() : '';
				if (resolved) return resolved;
				var theme = String((document.documentElement && document.documentElement.getAttribute('data-bs-theme')) || (document.body && document.body.getAttribute('data-bs-theme')) || '').toLowerCase();
				return theme === 'dark' ? '#fff' : '#000';
			};

            var mixCtx = document.getElementById('attendanceMixChart');
            if (mixCtx) {
                if (analyticsMixChart) {
                    try { analyticsMixChart.destroy(); } catch (e) { }
                }
                analyticsMixChart = new Chart(mixCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Present', 'Late', 'Absent', 'Unmarked'],
                        datasets: [{
                            data: [presentTotal, lateTotal, absentTotal, unmarkedTotal],
                            backgroundColor: ['#3ddc84', '#ffd166', '#ff6b6b', '#4dabf7'],
                            borderColor: 'rgba(255,255,255,0.12)',
                            borderWidth: 1
                        }]
                    },
                    options: {
						responsive: true,
						maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { color: chartTextColor } }
                        }
                    }
                });
            }

            var topCtx = document.getElementById('attendanceTopChart');
            if (topCtx) {
                if (analyticsTopChart) {
                    try { analyticsTopChart.destroy(); } catch (e) { }
                }

                var percentOfTotalDays = function (value, total) {
                    value = parseFloat(value || 0);
                    total = parseFloat(total || 0);
                    return total > 0 ? Math.round((value / total) * 1000) / 10 : 0;
                };
                var list = Array.isArray(rows) ? rows.slice() : [];
                list.sort(function (a, b) {
                    var bPresentRate = percentOfTotalDays(b.present_days || 0, b.total_days || totalDays);
                    var aPresentRate = percentOfTotalDays(a.present_days || 0, a.total_days || totalDays);
                    if (bPresentRate !== aPresentRate) {
                        return bPresentRate - aPresentRate;
                    }

                    var bCombinedRate = percentOfTotalDays((b.present_days || 0) + (b.late_days || 0), b.total_days || totalDays);
                    var aCombinedRate = percentOfTotalDays((a.present_days || 0) + (a.late_days || 0), a.total_days || totalDays);
                    return bCombinedRate - aCombinedRate;
                });
                var top = list.filter(function(user){ return !isDisabledUser(user); }).slice(0, 10);
                var labels = top.map(function (u) {
                    var label = ((u.first_name || '') + ' ' + (u.last_name || '')).replace(/\s+/g, ' ').trim();
                    return isDisabledUser(u) ? (label + ' (Disabled)') : label;
                });
                var presentValues = top.map(function (u) {
                    return percentOfTotalDays(u.present_days || 0, u.total_days || totalDays);
                });
                var lateValues = top.map(function (u) {
                    return percentOfTotalDays(u.late_days || 0, u.total_days || totalDays);
                });

                analyticsTopChart = new Chart(topCtx, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [
                            {
                                label: 'Present',
                                data: presentValues,
                                backgroundColor: 'rgba(25,135,84,0.82)',
                                borderColor: 'rgba(25,135,84,1)',
                                borderWidth: 1,
                                borderSkipped: false
                            },
                            {
                                label: 'Late',
                                data: lateValues,
                                backgroundColor: 'rgba(255,193,7,0.82)',
                                borderColor: 'rgba(255,193,7,1)',
                                borderWidth: 1,
                                borderSkipped: false
                            }
                        ]
                    },
                    options: {
						responsive: true,
						maintainAspectRatio: false,
                        indexAxis: 'y',
                        scales: {
                            x: {
                                stacked: true,
                                suggestedMax: 100,
                                max: 100,
                                ticks: {
                                    color: chartTextColor,
                                    callback: function (value) { return value + '%'; }
                                },
                                grid: { color: 'rgba(255,255,255,0.08)' }
                            },
                            y: {
                                stacked: true,
                                ticks: { color: chartTextColor },
                                grid: { color: 'rgba(255,255,255,0.08)' }
                            }
                        },
                        plugins: {
                            legend: { display: true, position: 'bottom', labels: { color: chartTextColor } },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        var row = top[ctx.dataIndex] || {};
                                        var days = ctx.dataset.label === 'Late' ? (row.late_days || 0) : (row.present_days || 0);
                                        return ctx.dataset.label + ': ' + ctx.parsed.x + '% (' + days + ' days)';
                                    },
                                    footer: function (items) {
                                        if (!items || !items.length) return '';
                                        var row = top[items[0].dataIndex] || {};
                                        var presentDays = parseFloat(row.present_days || 0);
                                        var lateDays = parseFloat(row.late_days || 0);
                                        var total = parseFloat(row.total_days || totalDays || 0);
                                        var combined = percentOfTotalDays(presentDays + lateDays, total);
                                        return 'Present + Late: ' + combined + '%';
                                    }
                                }
                            }
                        }
                    }
                });
				if (!window.ATT_CHART_THEME_BOUND) {
					window.ATT_CHART_THEME_BOUND = true;
					document.querySelectorAll('[data-bs-theme-value]').forEach(function (themeButton) {
						themeButton.addEventListener('click', function () {
							setTimeout(function () {
								try { if (analyticsMixChart) analyticsMixChart.update('none'); } catch (e) { }
								try { if (analyticsTopChart) analyticsTopChart.update('none'); } catch (e) { }
							}, 0);
						});
					});
				}
            }
        }

        async function fetchAttendanceAnalytics() {
            var range = resolveAnalyticsRange();
            var startDate = range ? range.startDate : '';
            var endDate = range ? range.endDate : '';
            if (!startDate || !endDate) {
                showAlert('Please select a valid date range for analytics', 'warning');
                return;
            }
            try {
                await ensureAttendanceAnalyticsLibs();
            } catch (eLib) {
                showAlert('Analytics tools could not be loaded right now.', 'danger');
                return;
            }
            preloader.classList.add('visible');
            if (analyticsTableBody) analyticsTableBody.innerHTML = '';
            if (analyticsMeta) analyticsMeta.textContent = '';
            if (analyticsRangeLabel) analyticsRangeLabel.textContent = '';

            var payload = {
                action: 'fetch_attendance_analytics',
                startDate: startDate,
                endDate: endDate
            };

			var mode = attendanceModeSelect.value;
			if (mode === 'student' || mode === 'view') {
				payload.staffIds = getSelectedStaffIds();
			}

            var xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;
                preloader.classList.remove('visible');
                if (xhr.status !== 200) {
                    showAlert('Failed to load analytics', 'danger');
                    return;
                }
                try {
                    var response = JSON.parse(String(xhr.responseText || '').trim());
                    if (!response.success) {
                        showAlert(response.error || 'Failed to load analytics', 'danger');
                        return;
                    }

                    var meta = response.meta || {};
                    var data = response.data || [];
                    if (analyticsRangeLabel) {
                        analyticsRangeLabel.textContent = formatLongDate(meta.startDate || startDate) + ' — ' + formatLongDate(meta.endDate || endDate);
                    }
                    if (analyticsMeta) {
                        var activeCount = data.filter(function(user){ return !isDisabledUser(user); }).length;
                        var disabledCount = data.filter(isDisabledUser).length;
                        analyticsMeta.textContent = activeCount + ' active • ' + disabledCount + ' disabled • ' + (meta.totalDays || 0) + ' days';
                    }
                    renderAnalyticsKpis(meta);
                    renderAnalyticsTable(data);
                    renderAnalyticsCharts(meta, data);
                } catch (e) {
                    showAlert('Invalid analytics response', 'danger');
                }
            };
            xhr.send(JSON.stringify(payload));
        }

        if (analyticsTab) {
            analyticsTab.addEventListener('click', function () {
                fetchAttendanceAnalytics();
            });
        }
        if (refreshAnalyticsButton) {
            refreshAnalyticsButton.addEventListener('click', function () {
                fetchAttendanceAnalytics();
            });
        }

        function showAlert(message, type) {
            var alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-' + type;
            alertDiv.textContent = message;
            document.getElementById('alert').prepend(alertDiv);
            setTimeout(function () {
                try { alertDiv.remove(); } catch(e) {
                    try { if(alertDiv && alertDiv.parentNode) alertDiv.parentNode.removeChild(alertDiv); } catch(e2) { }
                }
            }, 3000);
        }

        // expose helpers to global functions
        window.timeToInputValue = timeToInputValue;
        window.formatTimeDisplay = formatTimeDisplay;
        window.renderKpis = renderKpis;
        window.fetchAttendance = fetchAttendance;
        window.fetchAttendanceAnalytics = fetchAttendanceAnalytics;
        if (typeof buildTableHTML === 'function') window.buildTableHTML = buildTableHTML;
        if (typeof buildTableRowsHTML === 'function') window.buildTableRowsHTML = buildTableRowsHTML;
        if (typeof attachSubmitAttendanceForGroup === 'function') window.attachSubmitAttendanceForGroup = attachSubmitAttendanceForGroup;
        if (typeof updateGroupCard === 'function') window.updateGroupCard = updateGroupCard;
    });
</script><?=get_app_image_viewer_inline_source()?>
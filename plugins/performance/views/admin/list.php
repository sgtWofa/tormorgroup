<?php 
  // Fetch users server-side
  $db = new \Core\Database;
  $users = $db->query("SELECT * FROM users WHERE deleted = 0");
  $activityTypes = [
    'agencybankings','airtimebundles','attendance','cfas','commissions',
    'deposits','ezwichs','momoremits','payments',
    'payment_services','requests','reversals','sendings','sims',
    'tickets','withdrawals',
    'cashpickups','reconciliations','error_reports'
  ];
?>
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<!-- jQuery (needed for Select2) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
  .tabs { display: flex; border-bottom:2px solid #ddd; margin-bottom:1rem; }
  .tab { padding:10px 20px; cursor:pointer; font-weight:600; border:2px solid transparent; border-bottom:none; }
  .tab.active { border:1px solid #007bff; border-bottom:3px solid var(--text-color); background:#f0f8ff; color:#007bff; }
  .pane-content.active{background:transparent !important; }
  .pane-content { display:none; }
  .pane-content.active { display:block !important; }
  .dashboard-cards { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:1rem; }
  .card + .card { margin-top:1.5rem; }
  .card-header { font-weight:600; font-size:1.1rem; }
  tbody tr { white-space:nowrap; }
  td.wrap { white-space:normal; }

  .perf-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin-bottom:14px;}
  @media (max-width: 1200px){.perf-kpis{grid-template-columns:repeat(3,minmax(0,1fr));}}
  @media (max-width: 992px){.perf-kpis{grid-template-columns:repeat(2,minmax(0,1fr));}}
  @media (max-width: 576px){.perf-kpis{grid-template-columns:repeat(1,minmax(0,1fr));}}
  .perf-kpi{border:1px solid rgba(0,0,0,0.10);border-radius:14px;padding:12px;background:rgba(255,255,255,0.9);box-shadow: 0 10px 24px rgba(0,0,0,0.06);}
  html[data-bs-theme="dark"] .perf-kpi{border-color: rgba(255,255,255,0.14); background: rgba(17,24,39,0.55);}
  .perf-kpi .k{font-size:0.85rem;color:rgba(17,24,39,0.65);font-weight:800;}
  html[data-bs-theme="dark"] .perf-kpi .k{color: rgba(255,255,255,0.62);}
  .perf-kpi .v{font-size:1.35rem;font-weight:900;line-height:1.15;}
  .perf-kpi .s{font-size:0.8rem;color:rgba(17,24,39,0.55);}
  html[data-bs-theme="dark"] .perf-kpi .s{color: rgba(255,255,255,0.55);}

  .howto-body {
    overflow: hidden;
  }

  .toggle-icon {
    transition: transform 0.1s ease;
  }

  .rotate {
    transform: rotate(180deg);
  }

</style>

<div class="container py-4 mx-auto col-lg-10 col-md-10">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
      <h3 class="m-0 fw-bolder">Performance Report</h3>
      <div class="text-muted" style="font-size:0.9rem;">Unified activity analytics across operations</div>
    </div>
    <div class="text-muted" style="font-size:0.85rem;" id="perfMeta">Loading…</div>
  </div>

  <?php if(user_can('is_admin') || user_can('is_manager')): ?>
  <div class="card mb-4">
    <div class="card-header fw-bold">Generate Reports</div>
    <div class="card-body">
      <form id="perfReportForm" class="row g-3 align-items-end" onsubmit="return false;">
        <div class="col-md-3">
          <label>Period</label>
          <select id="perfReportPeriod" class="form-select">
            <option value="daily">Daily</option>
            <option value="monthly">Monthly</option>
            <option value="custom">Custom</option>
          </select>
        </div>
        <div class="col-md-3" id="perfReportDailyWrap">
          <label>Date</label>
          <input type="date" id="perfReportDate" class="form-control" />
        </div>
        <div class="col-md-3 d-none" id="perfReportMonthWrap">
          <label>Month</label>
          <select id="perfReportMonth" class="form-select">
            <?php
              $mNow = new \DateTime('first day of this month');
              for($i=0;$i<36;$i++){
                $mOpt = (clone $mNow)->modify("-{$i} months");
                $val = $mOpt->format('Y-m');
                $label = $mOpt->format('F Y');
                echo '<option value="'.$val.'">'.htmlspecialchars($label).'</option>';
              }
            ?>
          </select>
        </div>
        <div class="col-md-3 d-none" id="perfReportStartWrap">
          <label>Start date</label>
          <input type="date" id="perfReportStart" class="form-control" />
        </div>
        <div class="col-md-3 d-none" id="perfReportEndWrap">
          <label>End date</label>
          <input type="date" id="perfReportEnd" class="form-control" />
        </div>
        <div class="col-md-3">
          <label>User</label>
          <select id="perfReportUser" class="form-select">
            <option value="">All users</option>
            <?php foreach($users as $u): ?>
              <option value="<?= $u->id ?>"><?= htmlspecialchars($u->first_name . ' ' . $u->last_name) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label>Format</label>
          <select id="perfReportFormat" class="form-select">
            <option value="pdf">PDF</option>
            <option value="xlsx">Excel</option>
          </select>
        </div>
        <div class="col-12 text-end">
          <a id="perfReportDownload" class="btn btn-success" href="#" target="_blank" rel="noopener">Download</a>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <div class="perf-kpis" id="perfKpis">
    <div class="perf-kpi">
      <div class="k">Activity Count</div>
      <div class="v" id="kpiTxCount">—</div>
      <div class="s">Total count</div>
    </div>
    <div class="perf-kpi">
      <div class="k">Total Volume</div>
      <div class="v text-danger" id="kpiDebit">—</div>
      <div class="s">Sum of volumes</div>
    </div>
    <div class="perf-kpi">
      <div class="k">Total Commission</div>
      <div class="v text-success" id="kpiCredit">—</div>
      <div class="s">Sum of commissions</div>
    </div>
    <div class="perf-kpi">
      <div class="k">Cashpickups</div>
      <div class="v" id="kpiCashpickups">—</div>
      <div class="s">Total amount involved</div>
    </div>
    <div class="perf-kpi">
      <div class="k">Open Error Reports</div>
      <div class="v text-primary" id="kpiErOpen">—</div>
      <div class="s">Requires attention</div>
    </div>
  </div>

  <!-- How-To Card (Collapsible) -->
  <div class="card mb-4 border-info">
    <div class="card-header bg-info text-white howto-toggle" style="cursor:pointer">
      <i class="fa fa-info-circle me-2"></i> How to use this page
      <i class="fa fa-chevron-down float-end toggle-icon"></i>
    </div>
    <div class="card-body howto-body" style="display:none;">
      <ol class="mb-0">
        <li>Select a <strong>User</strong> (or leave “All users”).</li>
        <li>Pick one or more <strong>Activity Types</strong>.</li>
        <li>Choose a <strong>Date Range</strong>.</li>
        <li>Click <kbd>Apply Filters</kbd>.</li>
      </ol>
    </div>
  </div>


  <!-- Filters -->
  <form id="filterForm" class="row g-3 align-items-end mb-4">
    <div class="col-md-4">
      <label>User</label>
      <select id="userFilter" name="user" class="form-select">
        <option value="">All users</option>
        <?php foreach($users as $u): ?>
          <option value="<?= $u->id ?>"><?= htmlspecialchars($u->first_name . ' ' . $u->last_name) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label>Activity Type</label>
      <select id="activityFilter" name="activity[]" class="form-select select2" multiple data-placeholder="Search & select…">
        <?php foreach($activityTypes as $act): ?>
          <option value="<?= $act ?>"><?= ucwords(str_replace('_',' ',$act)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label>Date Range</label>
      <div class="input-group">
        <input type="date" name="start_date" class="form-control"/>
        <span class="input-group-text">to</span>
        <input type="date" name="end_date" class="form-control"/>
      </div>
    </div>
    <div class="col-12 text-end">
      <button type="button" id="applyFilters" class="btn btn-primary">Apply Filters</button>
    </div>
  </form>

  <!-- Tabs -->
  <div class="tabs">
    <div class="tab d-none" data-tab="list">📋 List View</div>
    <div class="tab" data-tab="summary">📌 Summary View</div>
    <div class="tab" data-tab="grid">📈 Grid View</div>
  </div>

  <!-- List Container -->
  <div id="list" class="pane-content">
    <div id="listResults"><p class="text-muted">Loading…</p></div>
  </div>

  <!-- Grid Container -->
  <div id="grid" class="pane-content rounded">
    <div id="dashboard" class="dashboard-cards p-2"></div>
  </div>

  <!-- Summary Container -->
  <div id="summary" class="pane-content rounded">
    <div id="summaryResults"><p class="text-muted p-2">Loading summary…</p></div>
  </div>
</div>

<script>
$(function(){
  // Init Select2
  $('.select2').select2({width:'100%'});
  const chartInstances = {};

  function updatePerfReportFields(){
    const period = ($('#perfReportPeriod').val()||'daily');
    const dailyWrap = document.getElementById('perfReportDailyWrap');
    const monthWrap = document.getElementById('perfReportMonthWrap');
    const startWrap = document.getElementById('perfReportStartWrap');
    const endWrap = document.getElementById('perfReportEndWrap');
    if(!dailyWrap || !monthWrap || !startWrap || !endWrap) return;

    dailyWrap.classList.add('d-none');
    monthWrap.classList.add('d-none');
    startWrap.classList.add('d-none');
    endWrap.classList.add('d-none');

    if(period === 'monthly'){
      monthWrap.classList.remove('d-none');
    }else if(period === 'custom'){
      startWrap.classList.remove('d-none');
      endWrap.classList.remove('d-none');
    }else{
      dailyWrap.classList.remove('d-none');
    }
  }

  function buildPerfReportUrl(){
    const period = ($('#perfReportPeriod').val()||'daily');
    const format = ($('#perfReportFormat').val()||'pdf');
    const user = ($('#perfReportUser').val()||'');

    const params = new URLSearchParams();
    params.set('period', period);
    params.set('format', format);
    if(user !== '') params.set('user', user);

    if(period === 'monthly'){
      const m = ($('#perfReportMonth').val()||'');
      if(m) params.set('month', m);
    }else if(period === 'custom'){
      const s = ($('#perfReportStart').val()||'');
      const e = ($('#perfReportEnd').val()||'');
      if(s) params.set('start_date', s);
      if(e) params.set('end_date', e);
    }else{
      const d = ($('#perfReportDate').val()||'');
      if(d) params.set('date', d);
    }

    return '<?=ROOT?>/<?= $admin_route ?>/<?= $plugin_route ?>/report?' + params.toString();
  }

  $('#perfReportPeriod, #perfReportFormat, #perfReportUser, #perfReportDate, #perfReportMonth, #perfReportStart, #perfReportEnd').on('change input', function(){
    updatePerfReportFields();
    const a = document.getElementById('perfReportDownload');
    if(a) a.setAttribute('href', buildPerfReportUrl());
  });

  updatePerfReportFields();
  const aInit = document.getElementById('perfReportDownload');
  if(aInit) aInit.setAttribute('href', buildPerfReportUrl());

  // stacked‐bar helper
  function renderBarStacked(el, labels, dataA, dataB, labelA, labelB, dataC, labelC){
    const c = document.getElementById(el);
    if (!c || !c.getContext) return;
    const ctx = c.getContext('2d');

    if (chartInstances[el]) chartInstances[el].destroy();
    chartInstances[el] = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { label: labelA, data: dataA, backgroundColor: 'rgba(40,167,69,0.7)' },
          ...(Array.isArray(dataC) ? [{ label: labelC, data: dataC, backgroundColor: 'rgba(255,193,7,0.75)' }] : []),
          { label: labelB, data: dataB, backgroundColor: 'rgba(220,53,69,0.7)' }
        ]
      },
      options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } },
        scales: {
          x: { stacked: true },
          y: { stacked: true, beginAtZero: true }
        }
      }
    });
  }

  // Tab switching + persistence
  function activateTab(tab){
    $('.tab, .pane-content').removeClass('active');
    $('.tab[data-tab="'+tab+'"]').addClass('active');
    $('#'+tab).addClass('active');
    localStorage.setItem('activeTab', tab);
  }
  $('.tab').on('click', function(){
    activateTab($(this).data('tab'));
    //fetchAnalysis();
  });
  activateTab(localStorage.getItem('activeTab')||'list');

  // Format helpers
  function formatValue(v){
    if(v==null||v==='') return '';
    const n = Number(v);
    return !isNaN(n)
      ? n.toLocaleString('en-US',{minimumFractionDigits:0,maximumFractionDigits:2})
      : v;
  }

  function num(v){
    const n = Number(v);
    return isNaN(n) ? 0 : n;
  }

  function setText(id, value){
    const el = document.getElementById(id);
    if(el) el.textContent = value;
  }

  function renderKpis(data){
    try{
      let totalCount = 0;
      let totalVolume = 0;
      let totalCommission = 0;
      if(data && typeof data === 'object'){
        Object.entries(data).forEach(([sect, parts]) => {
          if(!parts || typeof parts !== 'object') return;
          if(sect === 'myaccounts') return;
          const ov = (parts.Overview && typeof parts.Overview === 'object')
            ? parts.Overview
            : ((parts.Summary && typeof parts.Summary === 'object') ? parts.Summary : null);
          if(!ov) return;

          const c = num(
            ov.total_transactions ?? ov.transaction_count ?? ov.customers_served ?? ov.total_customers ?? ov.total_quantity ?? ov.total_count ?? ov.count
          );
          const v = num(
            ov.total_amount ?? ov.total_ghs ?? ov.total_volume ?? ov.total_vol ?? ov.amount ?? ov.volume
          );
          const com = num(
            ov.total_commission ?? ov.commission
          );
          totalCount += c;
          totalVolume += v;
          totalCommission += com;
        });
      }

      const cp = data?.cashpickups?.Summary || {};
      const cpAmt = num(cp.total_amount);

      const er = data?.error_reports?.Summary || {};
      const erOpen = num(er.open_cnt);

      setText('kpiTxCount', formatValue(totalCount));
      setText('kpiDebit', formatValue(totalVolume));
      setText('kpiCredit', formatValue(totalCommission));
      setText('kpiCashpickups', formatValue(cpAmt));
      setText('kpiErOpen', formatValue(erOpen));

      const start = $('input[name="start_date"]').val();
      const end = $('input[name="end_date"]').val();
      const user = $('#userFilter option:selected').text() || 'All users';
      const metaParts = [];
      if(start && end) metaParts.push(start + ' to ' + end);
      if(user) metaParts.push(user);
      document.getElementById('perfMeta').textContent = metaParts.length ? metaParts.join(' • ') : 'Ready';
    }catch(e){
    }
  }

  function renderTable(rows){
    if(!Array.isArray(rows)||!rows.length) return '<p class="text-muted">No data.</p>';
    let h = '<div class="table-responsive"><table class="table table-bordered"><thead><tr>'
      + Object.keys(rows[0]).map(k=>'<th>'+k.replace(/_/g,' ')+'</th>').join('')
      + '</tr></thead><tbody>';
    rows.forEach(r=>{
      h += '<tr>'+Object.values(r).map(c=>'<td>'+formatValue(c)+'</td>').join('')+'</tr>';
    });
    return h+'</tbody></table></div>';
  }

  function renderList(obj){
    if(typeof obj!=='object'||Array.isArray(obj)) return '<p class="text-muted">No data.</p>';
    let h = '<ul class="list-group">';
    Object.entries(obj).forEach(([k,v])=>{
      h += '<li class="list-group-item d-flex justify-content-between wrap">'
         + '<span>'+k.replace(/_/g,' ')+'</span>'
         + '<span>'+formatValue(v)+'</span></li>';
    });
    return h+'</ul>';
  }

  // List renderer
  function renderListResults(data){
    const act = $('#activityFilter').val();
    const secs = act && data[act] ? { [act]: data[act] } : data;
    let html = '';
    Object.entries(secs).forEach(([sect,parts])=>{
      if(sect === 'myaccounts') return;
      Object.entries(parts).forEach(([sub,val])=>{
        if(sect === 'attendance' && sub === 'By_Gender') return;
        const title = sect.replace(/s$/,'')+' — '+sub.replace(/_/g,' ');
        html += '<div class="card mb-4">'
          + '<div class="card-header alert alert-warning">'+title+'</div>'
          + '<div class="card-body">'+(Array.isArray(val)?renderTable(val):renderList(val))+'</div>'
          + '</div>';
      });
    });
    document.getElementById('listResults').innerHTML = html||'<p class="text-muted">No data.</p>';
  }

  function renderGridResults(data){
    const cnt = $('#dashboard').empty();
    const act = $('#activityFilter').val();
    const secs = act && data[act] ? { [act]: data[act] } : data;

    // compute total days
    const start = new Date($('input[name="start_date"]').val());
    const end   = new Date($('input[name="end_date"]').val());
    const dayCount = Math.floor((end - start)/(1000*60*60*24)) + 1;

    let cid = 0;
    Object.entries(secs).forEach(([sect, parts])=>{
      if(sect === 'myaccounts') return;
      Object.entries(parts).forEach(([sub, val])=>{
        if(sect === 'attendance' && sub === 'By_Gender') return;
        const title = sect.replace(/s$/,'') + ' — ' + sub.replace(/_/g,' ');
        const card = $('<div class="card p-3 shadow-sm mb-4"><h5>'+title+'</h5></div>');

        // Attendance → Days_Worked stacked bar
        if (sect==='attendance' && sub==='Days_Worked' && Array.isArray(val)) {
          const labels  = val.map(r=>r.full_name);
          const present = val.map(r=>Number(r.days_present));
          const late    = val.map(r=>Number(r.days_late));
          const absent  = val.map(r=>Number(r.days_absent));
          const cID     = 'chart-'+(cid++);
          card.append('<canvas id="'+cID+'" height="200"></canvas>');
          cnt.append(card);

          renderBarStacked(
            cID,
            labels,
            present,
            absent,
            'Present',
            'Absent',
            late,
            'Late'
          );
          return;  // skip the generic renderer
        }

        // generic Chart.js renderer
        if (Array.isArray(val) && val.length) {
          const cID = 'chart-' + (cid++);
          card.append('<canvas id="' + cID + '" height="180"></canvas>');
          cnt.append(card);

          let labels = [];
          let values = [];

          const sectSingular = sect.replace(/s$/, '');
          const isSpecialSect = ['momoremit','ezwich','airtimebundle','sim'].includes(sectSingular);
          const sample = val[0];

          //console.log('isSpecialSect: ', isSpecialSect , ' sample: ', sample);

          let keys = Object.keys(sample);

          if (isSpecialSect) {
            if (sample.transaction_type && sample.transaction_status) {
              labels = val.map(r => `${r.transaction_type} - ${r.transaction_status}`);
            } else if (sample.network && sample.transaction_status) {
              labels = val.map(r => `${r.network} - ${r.transaction_status}`);
            } else {
              // fallback: use first two keys
              const [k1, k2] = keys;
              labels = val.map(r => `${r[k1]} - ${r[k2]}`);
            }
          } else {
            const lab = keys[0];
            labels = val.map(r => r[lab]);
          }


          //const keys = Object.keys(sample);
          const numKey = keys.find(k => typeof sample[k] === 'number') || keys[1];
          values = val.map(r => r[numKey]);

          const ctx = document.getElementById(cID).getContext('2d');
          if (chartInstances[cID]) chartInstances[cID].destroy();
          chartInstances[cID] = new Chart(ctx, {
            type: 'bar',
            data: {
              labels: labels,
              datasets: [{ label: numKey, data: values }]
            },
            options: {
              responsive: true,
              plugins: { legend: { display: false } },
              scales: { y: { beginAtZero: true } }
            }
          });
        } else {
          card.append(renderList(val));
          cnt.append(card);
        }
      });
    });

    if (!cnt.children().length) {
      cnt.html('<p class="text-muted">No data available for Grid View.</p>');
    }
  }

  // Summary renderer
  function renderSummaryResults(data){
    const act = $('#activityFilter').val();
    const secs = act && data[act] ? { [act]: data[act] } : data;
    let html = '';
    Object.entries(secs).forEach(([sect,parts])=>{
      if(sect === 'myaccounts') return;
      Object.entries(parts).forEach(([sub,val])=>{
        if(sect === 'attendance' && sub === 'By_Gender') return;
        const title = sect.replace(/s$/,'')+' — '+sub.replace(/_/g,' ');
        html += '<div class="card mb-4">'
          + '<div class="card-header bg-secondary text-white">'+title+'</div>'
          + '<div class="card-body">'
          + (Array.isArray(val)
             ? renderTable(val)
             : renderList(val))
          + '</div></div>';
      });
    });
    document.getElementById('summaryResults').innerHTML = html||'<p class="text-muted">No data.</p>';
  }

  // Fetch + inject all three
  function fetchAnalysis(){
    document.getElementById('listResults').innerHTML    = '<p class="text-muted">Loading…</p>';
    $('#dashboard').html('<p class="text-muted">Loading dashboard…</p>');
    document.getElementById('summaryResults').innerHTML = '<p class="text-muted">Loading summary…</p>';

    $.post('', $('#filterForm').serialize(), function(data){
      renderKpis(data);
      renderListResults(data);
      renderGridResults(data);
      renderSummaryResults(data);
    }, 'json');
  }

  $('#applyFilters').on('click', fetchAnalysis);
  fetchAnalysis();

  // Collapsible How-To Card
  $('.howto-toggle').on('click', function(){
    const $body = $(this).next('.howto-body');
    const $icon = $(this).find('.toggle-icon');
    $body.slideToggle(300);
    $icon.toggleClass('rotate');
  });

});
</script>

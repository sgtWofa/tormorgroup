<link rel="stylesheet" type="text/css" href="<?=plugin_http_path('assets/css/style.css')?>">
<style>
    *{
        box-sizing: border-box;
    }

    body {
        font-family: 'Arial', sans-serif;
        margin: 0;
        padding: 0;
    }

    .dashboard {
        display: flex;
        width: 100%;
    }

    /* Main content styling */
    .main-content {
        background-color: #f8f9fa;
        width: 80%;
        padding: 20px;
    }

    .cards-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .card {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        text-align: center;
    }

    .card h3 {
        margin: 0;
        font-size: 1.2rem;
        color: #444;
    }

    .card p {
        font-size: 1.5rem;
        font-weight: bold;
        color: #ff5722;
    }

    .form-control{
        border: none;
        border-bottom: 0.5px solid lightgreen;
    }

    legend{
        color: lightgreen;
        font-family: tahoma;
        font-weight: bolder;
    }

    .dashboard-card {
        margin: 20px;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 8px;
        text-align: center;
        transition: 0.3s ease-in-out;
    }
    .dashboard-card .fa {
        font-size: 24px;
        margin-bottom: 10px;
    }
    .dashboard-card h3, .dashboard-card table, {
        margin-top: 0;
    }

    .dashboard-card:hover {
        color: rgb(108, 117, 125) !important;
        background-color: yellow !important;
        font-size: 15px !important;
    }

    a{
        text-decoration: none;
    }

    .recent-activities, .graphContainer {
        border-radius: 8px;
        padding: 20px;
        margin-top: 20px;
    }
    .recent-activities h3, .graphContainer h3 {
        margin-top: 0;
        font-size: 24px;
        border-bottom: 2px solid #e0e0e0;
        padding-bottom: 10px;
    }
    .recent-activities ul {
        list-style-type: none;
        padding-left: 0;
    }
    .recent-activities li {
        border-bottom: 1px solid lightgreen;
        border-radius: 10px;
        padding: 10px;
        margin-bottom: 10px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }
    .recent-activities li span {
        display: block;
        font-size: 12px;
    }

    .activityContainer{
        height: 53vh;
        overflow-y: scroll;
    }

   .custom-popover {
        --bs-popover-max-width: 200px;
        --bs-popover-border-color: lightgreen !important;
        --bs-popover-header-bg:  lightgreen !important;
        --bs-popover-header-color: red;
        --bs-popover-body-padding-x: 1rem;
        --bs-popover-body-padding-y: .5rem;
    }

    a{
        color: var(--text-color);
    }

    .text-yellow { color: #ffc107; }
      .card-ash { background: transparent; border: 1px dashed yellow; border-radius:5px; }
      .card-ash .card-header { border-bottom: 2px solid #ffc107; }
      .sparkline { height: 30px; }
      .chart-header { font-weight: 600; }
      #imgcard{
        width: 20px;
        height: 20px;
        border-radius: 5px;
        object-fit: fill;
      }

      /* → make each metric card a fixed, compact box */
      #metricsRow .card-ash {
        padding: 0.25rem;   /* a little less padding */
        margin: 0 auto;     /* center inside the col */
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
      }

      /* → adjust sparkline to fit the new height */
      #metricsRow .sparkline {
        margin-top: 0.25rem;
      }

      /* → a gentle lift & shadow on hover */
      #metricsRow .card-ash:hover {
        transform: translateY(-4px) scale(1.03);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
      }
      /*#metricsRow .col-md-3 { flex: 0 0 15%; max-width: 15%; }  or adjust % as needed */

      /* → dark-mode shadow tweak */
      [data-theme="dark"] #metricsRow .card-ash:hover {
        box-shadow: 0 8px 20px rgba(255, 255, 255, 0.3);
      }

      /* → lighten up the label text so it doesn’t crowd */
      #metricsRow .card-ash p {
        margin-bottom: 0.25rem;
        font-size: 0.85rem;
      }

      /* → shrink the icon slightly */
      #metricsRow .card-ash i.fa-2x {
        font-size: 1.6rem;
      }

      /* → smaller metric value font */
      #metricsRow .card-ash h4 {
        font-size: 1.25rem;
        margin: 0.25rem 0;
      }
    </style>

<link href="<?=plugin_http_path('assets/css/bootstrap.min.css')?>" rel="stylesheet">
<link href="<?=plugin_http_path('assets/css/select2.min.css')?>" rel="stylesheet">
<link href="<?=plugin_http_path('assets/css/dataTables.bootstrap5.min.css')?>" rel="stylesheet">

<?php if(user_can('view_dashboard')):?>

<div class="container-fluid py-3">

    <!-- Header & Filters -->
    <div class="row mb-4 align-items-end">
      <div class="col-auto">
        <label>Start Date</label>
        <input type="date" id="globalStart" class="form-control">
      </div>
      <div class="col-auto">
        <label>End Date</label>
        <input type="date" id="globalEnd" class="form-control">
      </div>
      <div class="col-auto">
        <label>User</label>
        <select id="userFilter" name="user" class="form-select">
          <option value="">All users</option>
          <?php foreach($users as $u): ?>
            <option value="<?=$u->id?>"><?=htmlspecialchars($u->first_name . ' '. $u->last_name)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label">Activity Type</label>
        <select id="activityFilter" name="activity[]" class="form-select select2" multiple data-placeholder="Search & select activities…">
          <option value="">All Activities</option>
          <?php foreach($activityTypes as $act): ?>
            <option value="<?=$act?>"><?=ucwords(str_replace('_',' ',$act))?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <button id="applyFilters" class="btn btn-primary">Apply Filters</button>
      </div>
    </div>

    <!-- Info -->
    <div class="card-ash mb-4 p-3">
      <i class="fa fa-info-circle text-yellow me-2"></i>
      Use the filters above to refresh metrics & charts below.
    </div>

    <!-- Key Metrics -->
    <div class="row mb-4" id="metricsRow"></div>

    <!-- Charts Row 1 -->
    <div class="row mb-4">
      <div class="col-lg-6 mb-3">
        <div class="card-ash p-3">
          <div class="chart-header">Service Performance Progress</div>
          <div id="serviceProgress" class="mt-2"></div>
        </div>
      </div>
      <div class="col-lg-6 mb-3">
        <div class="card-ash p-3">
          <div class="chart-header">Volume vs. Commission</div>
          <canvas id="chartVolComm" height="150"></canvas>
        </div>
      </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="row mb-4">
      <div class="col-lg-6 mb-3">
        <div class="card-ash p-3">
          <div class="chart-header">Agency Banking By Network</div>
          <canvas id="chartByNetwork" height="150"></canvas>
        </div>
      </div>
      <div class="col-lg-6 mb-3">
        <div class="card-ash p-3">
          <div class="chart-header">Agency Banking By Transaction Type</div>
          <canvas id="chartByType" height="150"></canvas>
        </div>
      </div>
    </div>

    <!-- ── NEW Charts Row for Momo Remits ── -->
    <div class="row mb-4">
      <div class="col-lg-6 mb-3">
        <div class="card-ash p-3">
          <div class="chart-header">Momo Remits by Type</div>
          <canvas id="chartMomoByType" height="150"></canvas>
        </div>
      </div>
      <div class="col-lg-6 mb-3">
        <div class="card-ash p-3">
          <div class="chart-header">Momo Remits by Network</div>
          <canvas id="chartMomoByNetwork" height="150"></canvas>
        </div>
      </div>
    </div>  

    <div class="row mb-4">
        <!-- Include Chart.js library -->
        <div class="col-lg-8 col-md-12 mb-5">   
          <!-- Recent Transactions -->
          <div class="card-ash p-3">
            <div class="chart-header mb-2 table-responsive">Recent Transactions</div>
            <table id="tblRecent" class="table table-striped">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Image</th>
                  <th>User</th>
                  <th>Description</th>
                  <th>Activity</th>
                  <th>Amount</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>

        <!-- Display recent activities -->
        <div class="col-lg-4 col-md-12 mb-5">

          <div class="mb-3">
            <div class="card-ash p-3">
              <div class="chart-header">Days Worked per User</div>
              <canvas id="chartAttendance" height="150"></canvas>
            </div>
          </div>

          <div class="mb-3">
            <div class="card-ash p-3">
              <div class="chart-header">Gender Breakdown</div>
              <canvas id="chartGender" height="150"></canvas>
            </div>
          </div>
          <div class="card-ash p-3">
            <div class="recent-activities">
                <!-- Card Title -->
                <h3 class="card-title mb-3">Recent Activities Log</h3>
                <!-- Activities Table -->
                <table class="table table-striped table-borderless border-bottom table-hover">
                  <thead>
                    <tr>
                      <th>Date/Time</th>
                      <th>Description</th>
                    </tr>
                  </thead>
                  <tbody id="activityTable">
                    <?php if (!empty($recentActivities)): ?>
                      <?php foreach ($recentActivities as $activity): ?>
                        <tr>
                          <td class="alert-primary"><?= get_date_time($activity->date_created); ?></td>
                          <td class="alert-primary"><?= htmlspecialchars($activity->description); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
                <!-- Total Pages Display -->
                <p id="totalPagesText" class="text-center mt-3"></p>

                <!-- Pagination -->
                <nav id="paginationNav" aria-label="Page navigation" class="mt-1">
                  <ul class="pagination justify-content-center" style="font-size: 8px;"></ul>
                </nav>

                <!-- Scripts -->
                <script>
                  document.addEventListener("DOMContentLoaded", function () {
                    const rowsPerPage = 5;
                    const table = document.getElementById("activityTable");
                    const paginationNav = document.querySelector("#paginationNav .pagination");
                    const searchInput = document.getElementById("searchInput");
                    const totalPagesText = document.getElementById("totalPagesText");

                    let currentPage = 1;
                    let originalRows = Array.from(table.rows); // Store the original rows for filtering
                    let filteredRows = [...originalRows]; // To hold filtered rows

                    // Function to display the current page
                    function displayPage(page) {
                      const start = (page - 1) * rowsPerPage;
                      const end = start + rowsPerPage;

                      // Hide all rows initially
                      filteredRows.forEach((row, index) => {
                        row.style.display = index >= start && index < end ? "" : "none";
                      });

                      currentPage = page;
                      updatePagination();
                    }

                    // Function to update pagination controls
                    function updatePagination() {
                      paginationNav.innerHTML = "";
                      const totalPages = Math.ceil(filteredRows.length / rowsPerPage);

                      // Update total pages display
                      totalPagesText.textContent = `Total Pages: ${totalPages}`;

                      // Generate pagination range
                      const paginationRange = getPaginationRange(currentPage, totalPages);

                      // Render pagination navigation with ellipsis
                      paginationRange.forEach((item) => {
                        const li = document.createElement("li");
                        li.className = "page-item" + (item === currentPage ? " active" : "");
                        const a = document.createElement("a");
                        a.className = "page-link";

                        if (item === "...") {
                          li.classList.add("disabled");
                          a.textContent = "...";
                        } else {
                          a.textContent = item;
                          a.href = "#";
                          a.addEventListener("click", (e) => {
                            e.preventDefault();
                            if (typeof item === "number") displayPage(item);
                          });
                        }

                        li.appendChild(a);
                        paginationNav.appendChild(li);
                      });
                    }

                    // Function to calculate pagination range with ellipsis
                    function getPaginationRange(current, total) {
                      const range = [];
                      const maxVisiblePages = 5;

                      if (total <= maxVisiblePages) {
                        // Show all pages if total <= maxVisiblePages
                        for (let i = 1; i <= total; i++) range.push(i);
                      } else {
                        range.push(1); // Always include the first page

                        const start = Math.max(2, current - 1); // Start from one page before the current page
                        for (let i = start; i < Math.min(start + 3, total - 1); i++) {
                          range.push(i);
                        }

                        if (current < total - 3) range.push("..."); // Add ellipsis if necessary

                        range.push(total - 1); // Include the second-to-last page
                        range.push(total); // Include the last page
                      }
                      return range;
                    }

                    // Initialize the table with the first page
                    displayPage(1);
                  });
                </script>
            </div>
          </div>
        </div>
    </div>
  </div>

<!-- Dependencies -->
<script src="<?=plugin_http_path('assets/js/jquery-3.6.0.min.js')?>"></script>
<script src="<?=plugin_http_path('assets/js/bootstrap.bundle.min.js')?>"></script>
<script src="<?=plugin_http_path('assets/js/select2.min.js')?>"></script>
<script src="<?=plugin_http_path('assets/js/chart.js')?>"></script>
<script src="<?=plugin_http_path('assets/js/jquery.dataTables.min.js')?>"></script>
<!-- FIXED: correct DataTables Bootstrap include -->
<script src="<?=plugin_http_path('assets/js/dataTables.bootstrap5.min.js')?>"></script>
<script>
  const chartInstances = {};

  $(document).ready(function(){
    $('.select2').select2({
      width: '100%',
      placeholder: $('#activityFilter').data('placeholder'),
      allowClear: true
    });
    $('#tblRecent').DataTable();
    $('#applyFilters').click(fetchDashboard);
    fetchDashboard();
  });

  function fetchDashboard(){
    // get raw select2 value (could be null, string, or array)
    let acts = $('#activityFilter').val() || [];
    // normalize to array
    if (!Array.isArray(acts)) acts = [acts];
    // strip blank entries
    acts = acts.filter(a => a);

    // build payload
    const payload = {
      user:       $('#userFilter').val(),
      start_date: $('#globalStart').val(),
      end_date:   $('#globalEnd').val()
    };
    // only include activity array if non-empty
    if (acts.length) {
      payload.activity = acts;
    }

    // debug: uncomment to verify
    // console.log('Fetching with payload:', payload);

    $.ajax({
      url: '',    // same page
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify(payload),
      success: renderDashboard
    });
  }

  function renderDashboard(out) {
    // — Helper to clear & destroy a chart canvas —
    function destroyAndClear(id) {
      if (chartInstances[id]) {
        chartInstances[id].destroy();
        delete chartInstances[id];
      }
      const ctx = document.getElementById(id).getContext('2d');
      ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
    }

    // 1) Core metrics
    const txOv  = out.transactions?.Overview || { transaction_count: 0, total_amount: 0 };
    const comOv = out.commissions?.Summary  || { total_commission: 0 };
    const att   = out.attendance?.Days_Worked || [];

    const coreMetrics = [
      {
        id:    'totalTx',
        icon:  'fa-list',
        label: 'Transactions',
        value: txOv.transaction_count
      },
      {
        id:    'totalVol',
        icon:  'fa-coins',
        label: 'Volume (₵)',
        value: `₵${Number(txOv.total_amount).toLocaleString()}`
      },
      {
        id:    'totalComm',
        icon:  'fa-percent',
        label: 'Commission (₵)',
        value: `₵${Number(comOv.total_commission).toLocaleString()}`
      },
      {
        id:    'activeUsers',
        icon:  'fa-users',
        label: 'Active Users',
        value: att.length
      }
    ];

    // 2) Additional per-activity metrics
    const activityMetrics = [
      ['agencybankings',    'fa-landmark',        out.agencybankings?.Overview?.total_transactions],
      ['airtimebundles',    'fa-phone-alt',       out.airtimebundles?.Overview?.total_transactions],
      ['cfas',              'fa-file-invoice',    out.cfas?.Summary?.total_transactions],
      ['commissions',       'fa-percent',         out.commissions?.Summary?.total_transactions],
      ['deposits',          'fa-piggy-bank',      out.deposits?.Overview?.total_transactions],
      ['ezwichs',           'fa-credit-card',     out.ezwichs?.Overview?.total_customers],
      ['momoremits',        'fa-wallet',          out.momoremits?.Overview?.customers_served],
      ['payments',          'fa-hand-holding-usd',out.payments?.Summary?.total_transactions],
      ['payment_services',  'fa-concierge-bell',  out.payment_services?.Summary?.total_customers],
      ['requests',          'fa-envelope-open-text',
                             (out.requests?.Requests || []).reduce((sum, r) => sum + (r.total_count||0), 0)
      ],
      ['reversals',         'fa-undo',            out.reversals?.Summary?.total_transactions],
      ['sendings',          'fa-paper-plane',     out.sendings?.Overview?.total_customers],
      ['sims',              'fa-sim-card',        out.sims?.Summary?.total_quantity],
      ['tickets',           'fa-ticket-alt',
                             (out.tickets?.By_Status || []).reduce((sum, t) => sum + (t.count||0), 0)
      ],
      ['withdrawals',       'fa-money-check-alt', out.withdrawals?.Overview?.total_transactions],
      // duplicate transactions to illustrate you can include it again:
     // ['transactions',      'fa-list',            out.transactions?.Overview?.transaction_count]
    ].map(([id, icon, raw]) => ({
      id,
      icon,
      label: id.replace(/([A-Z])/g, ' $1')
               .replace(/_/g, ' ')
               .replace(/^\w/, c => c.toUpperCase()),
      raw: raw || 0,
      value: isNaN(raw) ? '—' : raw
    }));

    // 3) Combine & filter >0
    const allMetrics = coreMetrics .map(m => ({
       ...m, 
       raw: parseFloat(String(m.value).replace(/[^0-9.-]/g,'')) || 0 
     }))
      .concat(activityMetrics);

    const toRender = allMetrics.filter(m => m.raw > 0);

    // 4) Render
    $('#metricsRow').empty();
    toRender.forEach(m => {
      $('#metricsRow').append(`
          <div class="col-xl-2 col-lg-3 col-md-4 col-sm-4 col-6 mb-3">
            <div class="card-ash text-center p-3">
              <i class="fa ${m.icon} fa-2x text-yellow mb-2"></i>
              <h4 id="${m.id}">${m.value}</h4>
              <p>${m.label}</p>
              <canvas class="sparkline" id="spark_${m.id}"></canvas>
            </div>
          </div>
        `);
    });

    // 3) Sparklines (last 7 days)
    const byDate = out.transactions?.By_Date || [];
    const dates  = byDate.slice(-7).map(r => r.date);
    const counts = byDate.slice(-7).map(r => r.count);
    renderSpark('spark_totalTx', dates, counts);
    renderSpark('spark_totalComm', dates, counts);

    // 4) Replace time-series chart with progress-bar style chart
    renderServiceProgress('serviceProgress', {
      agencybankings:   out.agencybankings?.Overview?.total_transactions || 0,
      sims:             out.sims?.Summary?.total_quantity || 0,
      payment_services: out.payment_services?.Summary?.total_customers || 0,
    });

    // Keep dual-axis chart for volume vs commission
    renderDual('chartVolComm',
      dates,
      dates.map(d => byDate.find(r => r.date === d)?.amount      || 0),
      dates.map(d => byDate.find(r => r.date === d)?.total_debit || 0),
      'Volume','Debit'
    );

    // 5) By Network (if present)
    const bnKey = Object.keys(out).find(k => Array.isArray(out[k]?.By_Network));
    if (bnKey) {
      renderBar(
        'chartByNetwork',
        out[bnKey].By_Network.map(r => r.network),
        out[bnKey].By_Network.map(r => r.amount),
        'Amount'
      );
    } else {
      destroyAndClear('chartByNetwork');
    }

    // 6) By Transaction Type (if present)
    const btKey = Object.keys(out).find(k => Array.isArray(out[k]?.By_Type) || Array.isArray(out[k]?.By_Transaction_Type));
    const btArr = btKey
      ? (out[btKey].By_Type || out[btKey].By_Transaction_Type)
      : null;
    if (btArr) {
      renderBar(
        'chartByType',
        btArr.map(r => r.transaction_type || r.status),
        btArr.map(r => r.amount),
        'Count'
      );
    } else {
      destroyAndClear('chartByType');
    }

    // ── NEW: Momo Remits by Transaction Type ──
    const momoType = out.momoremits?.By_Type || [];
    if (momoType.length) {
      renderBar(
        'chartMomoByType',
        momoType.map(r => r.type),
        momoType.map(r => r.amount),
        'Amount'
      );
    } else {
      destroyAndClear('chartMomoByType');
    }

    // ── NEW: Momo Remits by Network ──
    const momoNet = out.momoremits?.By_Network || [];
    if (momoNet.length) {
      renderBar(
        'chartMomoByNetwork',
        momoNet.map(r => r.network),
        momoNet.map(r => r.amount),
        'Amount'
      );
    } else {
      destroyAndClear('chartMomoByNetwork');
    }

    // 7) Attendance Days Worked
    const aw = out.attendance?.Days_Worked || [];
    if (Array.isArray(aw) && aw.length) {
      const labels = aw.map(r => r.first_name);
      const present = aw.map(r => Number(r.present || 0));
      const late = aw.map(r => Number(r.late || 0));
      const absent = aw.map(r => Number(r.absent || 0));

      if (chartInstances['chartAttendance']) chartInstances['chartAttendance'].destroy();
      chartInstances['chartAttendance'] = new Chart(document.getElementById('chartAttendance'), {
        type: 'bar',
        data: {
          labels,
          datasets: [
            { label: 'Present', data: present, backgroundColor: '#20c997', stack: 's' },
            { label: 'Late', data: late, backgroundColor: '#ffc107', stack: 's' },
            { label: 'Absent', data: absent, backgroundColor: '#dc3545', stack: 's' },
          ]
        },
        options: {
          responsive: true,
          plugins: { legend: { position: 'bottom' } },
          scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } }
        }
      });
    } else {
      destroyAndClear('chartAttendance');
    }

    // 9) Recent Transactions
    const tbl = $('#tblRecent').DataTable();
    tbl.clear();
    (out.transactions?.Recent || []).forEach(r => {
      tbl.row.add([
        r.date,
        `<div class="user-cell"><img src="${r.image}" id="imgcard" alt=""></div>`,
        `<div class="user-cell"><span class="mx-2">${r.full_name}</span></div>`,
        r.description,
        r.transaction_type,
        `${Number(r.amount).toLocaleString()}`,
        //`${Number(r.balance).toLocaleString()}`
      ]);
    });
    tbl.draw();
  }

  // Utility to clear or destroy a missing chart
  function destroyAndClear(canvasId) {
    if (chartInstances[canvasId]) {
      chartInstances[canvasId].destroy();
    }
    const ctx = document.getElementById(canvasId).getContext('2d');
    ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
  }


  // Chart helpers
  function renderSpark(el, labels, data){
    if (chartInstances[el]) chartInstances[el].destroy();
    chartInstances[el] = new Chart(document.getElementById(el), {
      type:'line', data:{labels,datasets:[{data,fill:false,borderColor:'#ffc107',tension:0.3}]},
      options:{plugins:{legend:{display:false}},scales:{x:{display:false},y:{display:false}}}
    });
  }
  function renderLine(el, labels, data, label){
    if (chartInstances[el]) chartInstances[el].destroy();
    chartInstances[el] = new Chart(document.getElementById(el), {
      type:'line', data:{labels,datasets:[{label,data,borderColor:'#ffc107',backgroundColor:'rgba(255,193,7,0.2)',fill:true}]},
      options:{scales:{y:{beginAtZero:true}}}
    });
  }
  function renderDual(el, labels, data1, data2, l1, l2){
    if (chartInstances[el]) chartInstances[el].destroy();
    chartInstances[el] = new Chart(document.getElementById(el), {
      type:'line',
      data:{labels,datasets:[
        {label:l1,data:data1,borderColor:'#ffc107',backgroundColor:'rgba(255,193,7,0.2)',yAxisID:'y1'},
        {label:l2,data:data2,borderColor:'#888',backgroundColor:'rgba(128,128,128,0.2)',yAxisID:'y2'}
      ]},
      options:{scales:{y1:{type:'linear',position:'left',beginAtZero:true},y2:{type:'linear',position:'right',beginAtZero:true,grid:{drawOnChartArea:false}}}}
    });
  }
  function renderBar(el, labels, data, label){
    if (chartInstances[el]) chartInstances[el].destroy();
    chartInstances[el] = new Chart(document.getElementById(el), {
      type:'bar', data:{labels,datasets:[{label,data,backgroundColor:'#ffc107'}]}, options:{scales:{y:{beginAtZero:true}}}
    });
  }
  function renderPie(el, labels, data){
    if (chartInstances[el]) chartInstances[el].destroy();
    chartInstances[el] = new Chart(document.getElementById(el), {
      type:'doughnut', data:{labels,datasets:[{data,backgroundColor:labels.map(g=>g==='male'?'#f0f0f0':'#ffc107')}]},
      options:{plugins:{legend:{position:'bottom'}}}
    });
  }

  // Progress-bar style chart for key services
  function renderServiceProgress(containerId, services){
    const container = document.getElementById(containerId);
    if(!container){
      return;
    }

    const entries = Object.entries(services || {});
    if(!entries.length){
      container.innerHTML = '<p class="text-muted mb-0">No activity yet for Sims, Agency Banking or Payment Services.</p>';
      return;
    }

    const maxVal = Math.max(...entries.map(([_, v]) => Number(v) || 0), 0) || 1;
    let html = '';

    entries.forEach(([key, value]) => {
      const raw = Number(value) || 0;
      const pct = Math.round((raw / maxVal) * 100);
      const label = key
        .replace(/_/g, ' ')
        .replace(/s$/, '')
        .replace(/^\w/, c => c.toUpperCase());

      html += `
        <div class="mb-2">
          <div class="d-flex justify-content-between small mb-1">
            <span>${label}</span>
            <span>${raw.toLocaleString()} (${pct}%)</span>
          </div>
          <div class="progress" style="height: 8px;">
            <div class="progress-bar bg-warning" role="progressbar" style="width: ${pct}%;" aria-valuenow="${pct}" aria-valuemin="0" aria-valuemax="100"></div>
          </div>
        </div>`;
    });

    container.innerHTML = html;
  }
</script>



<?php else:?>
<div class="alert alert-danger text-center">
    Access denied. You dont have permission for this action
</div>
<?php endif;?>
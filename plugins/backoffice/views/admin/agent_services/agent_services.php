<div class="card border-0 shadow-sm mb-4">
  <div class="card-body">
    <!-- Filters and Action Bar -->
    <div class="row g-2 mb-3 align-items-center">
      <div class="col-12 col-lg-6">
        <div class="input-group">
          <span class="input-group-text text-muted border-end-0">
            <i class="fas fa-search"></i>
          </span>
          <input type="text" class="form-control border-start-0" id="agentServicesSearch" value="<?=esc($_GET['search'] ?? '')?>" placeholder="Search services, agents, numbers...">
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="input-group">
          <span class="input-group-text text-muted border-end-0">
            <i class="fas fa-calendar-alt"></i>
          </span>
          <input type="date" class="form-control border-start-0" id="startDateFilter" value="<?=esc($_GET['start_date'] ?? '')?>" placeholder="Start date">
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="input-group">
          <span class="input-group-text text-muted border-end-0">
            <i class="fas fa-calendar-alt"></i>
          </span>
          <input type="date" class="form-control border-start-0" id="endDateFilter" value="<?=esc($_GET['end_date'] ?? '')?>" placeholder="End date">
        </div>
      </div>
      <div class="col-12 d-lg-none">
        <button type="button" id="toggleAgentServicesFilters" class="btn btn-outline-secondary btn-sm rounded-pill w-100">
          <i class="fas fa-filter me-1"></i>
          <span>More Filters</span>
        </button>
      </div>
      <div class="col-12 d-none d-lg-block">
        <div class="row g-2 align-items-center">
          <div class="col-lg-3">
            <div class="input-group">
              <span class="input-group-text text-muted border-end-0">
                <i class="fa-solid fa-list-check"></i>
              </span>
              <select class="form-select" id="statusFilter">
                <option value="">All Statuses</option>
                <option value="pending" <?=($_GET['status'] ?? '') === 'pending' ? 'selected' : ''?>>Pending</option>
                <option value="assigned" <?=($_GET['status'] ?? '') === 'assigned' ? 'selected' : ''?>>Assigned</option>
                <option value="completed" <?=($_GET['status'] ?? '') === 'completed' ? 'selected' : ''?>>Completed</option>
                <option value="rejected" <?=($_GET['status'] ?? '') === 'rejected' ? 'selected' : ''?>>Rejected</option>
                <option value="cancelled" <?=($_GET['status'] ?? '') === 'cancelled' ? 'selected' : ''?>>Cancelled</option>
              </select>
            </div>
          </div>
          <div class="col-lg-3">
            <div class="input-group">
              <span class="input-group-text text-muted border-end-0">
                <i class="fa-solid fa-network-wired"></i>
              </span>
              <select class="form-select" id="providerFilter">
                <option value="">All Networks / Providers</option>
                <?php foreach ($agentServiceNetworks ?? [] as $network): ?>
                  <option value="<?=esc($network->network)?>" <?=($_GET['provider'] ?? '') === ($network->network ?? '') ? 'selected' : ''?>><?=esc($network->network)?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="col-lg-3">
            <div class="input-group">
              <span class="input-group-text text-muted border-end-0">
                <i class="fa-solid fa-user-group"></i>
              </span>
              <select class="form-select" id="userFilter">
                <option value="">All Users</option>
                <?php foreach ($agentServiceUsers ?? [] as $userOption): ?>
                  <option value="<?=esc($userOption->id)?>" <?=((int)($_GET['user'] ?? 0) === (int)$userOption->id) ? 'selected' : ''?>><?=esc(trim(($userOption->first_name ?? '') . ' ' . ($userOption->last_name ?? '')))?> (<?=esc($userOption->id)?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="col-lg-3">
            <div class="input-group">
              <span class="input-group-text text-muted border-end-0">
                <i class="fa-solid fa-sliders"></i>
              </span>
              <select class="form-select" id="perPageFilter">
                <?php foreach ([10, 25, 50, 100] as $size): ?>
                  <option value="<?=$size?>" <?=((int)($_GET['per_page'] ?? 10) === $size) ? 'selected' : ''?>><?=$size?> per page</option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div id="agentServicesExtraFilters" class="row g-2 mb-3 align-items-center d-none d-lg-none">
      <div class="col-6 col-sm-6">
        <div class="input-group">
          <span class="input-group-text text-muted border-end-0">
            <i class="fa-solid fa-list-check"></i>
          </span>
          <select class="form-select" id="statusFilterMobile">
            <option value="">All Statuses</option>
            <option value="pending" <?=($_GET['status'] ?? '') === 'pending' ? 'selected' : ''?>>Pending</option>
            <option value="assigned" <?=($_GET['status'] ?? '') === 'assigned' ? 'selected' : ''?>>Assigned</option>
            <option value="completed" <?=($_GET['status'] ?? '') === 'completed' ? 'selected' : ''?>>Completed</option>
            <option value="rejected" <?=($_GET['status'] ?? '') === 'rejected' ? 'selected' : ''?>>Rejected</option>
            <option value="cancelled" <?=($_GET['status'] ?? '') === 'cancelled' ? 'selected' : ''?>>Cancelled</option>
          </select>
        </div>
      </div>
      <div class="col-6 col-sm-6">
        <div class="input-group">
          <span class="input-group-text text-muted border-end-0">
            <i class="fa-solid fa-network-wired"></i>
          </span>
          <select class="form-select" id="providerFilterMobile">
            <option value="">All Networks / Providers</option>
            <?php foreach ($agentServiceNetworks ?? [] as $network): ?>
              <option value="<?=esc($network->network)?>" <?=($_GET['provider'] ?? '') === ($network->network ?? '') ? 'selected' : ''?>><?=esc($network->network)?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="col-12 col-sm-6">
        <div class="input-group">
          <span class="input-group-text text-muted border-end-0">
            <i class="fa-solid fa-user-group"></i>
          </span>
          <select class="form-select" id="userFilterMobile">
            <option value="">All Users</option>
            <?php foreach ($agentServiceUsers ?? [] as $userOption): ?>
              <option value="<?=esc($userOption->id)?>" <?=((int)($_GET['user'] ?? 0) === (int)$userOption->id) ? 'selected' : ''?>><?=esc(trim(($userOption->first_name ?? '') . ' ' . ($userOption->last_name ?? '')))?> (<?=esc($userOption->id)?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="col-12 col-sm-6">
        <div class="input-group">
          <span class="input-group-text text-muted border-end-0">
            <i class="fa-solid fa-sliders"></i>
          </span>
          <select class="form-select" id="perPageFilterMobile">
            <?php foreach ([10, 25, 50, 100] as $size): ?>
              <option value="<?=$size?>" <?=((int)($_GET['per_page'] ?? 10) === $size) ? 'selected' : ''?>><?=$size?> per page</option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>
    <div class="row g-2 mb-3 align-items-center">
      <div class="col-md-12 text-end">
        <div class="d-flex justify-content-end gap-2 flex-wrap">

          <div class="col-lg-2">
            <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/make_agent_service" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm w-100">
              <i class="fas fa-plus-circle me-1"></i> Add Service
            </a>
          </div>
          <button type="button" id="agentServicesExportPdf" class="btn btn-outline-secondary btn-sm rounded-pill px-3">Export PDF</button>
          <button type="button" id="agentServicesExportExcel" class="btn btn-outline-success btn-sm rounded-pill px-3">Export Excel</button>
        </div>
      </div>
    </div>

    <!-- Table Container -->
    <div class="agent-services-table-wrap">
      <table class="table table-hover align-middle mb-0" id="agentServicesTable">
        <thead>
          <tr>
            <th class="border-top-0">Service</th>
            <th class="border-top-0">Agent / People</th>
            <th class="border-top-0">Amount / Status</th>
            <th class="border-top-0">Action</th>
          </tr>
        </thead>
        <tbody id="agentServicesTbody">
          <?php require plugin_path('views/admin/agent_services/agent_services_rows.php'); ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination Controls -->
    <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
      <div id="paginationInfo" class="text-muted small">
        Showing Page <?=esc($page ?? 1)?> of <?=esc($total_pages ?? 1)?> (Total <?=esc($total_records ?? count($agent_services_rows ?? []))?> records)
      </div>
      <nav>
        <ul class="pagination pagination-sm mb-0" id="paginationNav">
          <!-- Dynamically populated via JS -->
        </ul>
      </nav>
    </div>
  </div>
</div>

<style>
  .agent-services-table-wrap {
    width: 100%;
    overflow-x: hidden;
  }
  #agentServicesTable {
    width: 100%;
    table-layout: fixed;
    font-size: .82rem;
  }
  #agentServicesTable th,
  #agentServicesTable td {
    padding: .55rem .4rem;
    overflow-wrap: anywhere;
    word-break: normal;
    min-width: 0;
    vertical-align: middle;
  }
  #agentServicesTable th:nth-child(1) { width: 23%; }
  #agentServicesTable th:nth-child(2) { width: 35%; }
  #agentServicesTable th:nth-child(3) { width: 20%; }
  #agentServicesTable th:nth-child(4) { width: 22%; }
  #agentServicesTable .service-dates,
  #agentServicesTable .service-people {
    line-height: 1.35;
    max-width: 100%;
    white-space: normal;
    overflow-wrap: anywhere;
  }
  #agentServicesTable .service-dates span,
  #agentServicesTable .service-people > div {
    display: block;
    max-width: 100%;
    white-space: normal;
    overflow-wrap: anywhere;
  }
  #agentServicesTable th {
    font-size: .76rem;
  }
  #agentServicesTable .service-financial {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: .3rem;
  }
  #agentServicesTable .service-financial .badge {
    max-width: 100%;
    white-space: normal !important;
    overflow-wrap: anywhere;
    text-align: center;
  }
  #agentServicesTable .service-actions {
    display: flex;
    flex-wrap: wrap;
    gap: .25rem;
  }
  #agentServicesTable .service-actions .btn {
    padding: .25rem .4rem;
    line-height: 1.1;
  }
  @media (max-width: 767.98px) {
    #agentServicesTable,
    #agentServicesTable tbody {
      display: block;
      width: 100%;
    }
    #agentServicesTable thead {
      display: none;
    }
    #agentServicesTable tbody tr {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: .1rem .45rem;
      padding: .4rem .5rem;
      margin-bottom: .45rem;
      border: 1px solid var(--bs-border-color);
      border-radius: .6rem;
      background: var(--bs-body-bg);
    }
    #agentServicesTable tbody td {
      display: block;
      min-width: 0;
      padding: .25rem .15rem;
      border: 0;
    }
    #agentServicesTable tbody td::before {
      content: attr(data-label);
      display: block;
      margin-bottom: .1rem;
      color: var(--bs-secondary-color);
      font-size: .6rem;
      font-weight: 700;
      letter-spacing: .04em;
      text-transform: uppercase;
    }
    #agentServicesTable tbody td[data-label="Agent / People"] {
      grid-column: 2;
      grid-row: 1;
    }
    #agentServicesTable tbody td[data-label="Amount / Status"] {
      grid-column: 1;
      grid-row: 2;
    }
    #agentServicesTable tbody td[data-label="Action"] {
      grid-column: 2;
      grid-row: 2;
      align-self: center;
    }
    #agentServicesTable .service-financial {
      flex-direction: row;
      align-items: center;
      flex-wrap: wrap;
      gap: .3rem .45rem;
    }
    #agentServicesTable .service-financial .badge {
      max-width: 100%;
      min-width: 0;
      padding: .25em .45em;
      font-size: .62rem;
      line-height: 1.15;
      white-space: normal !important;
      text-align: center;
    }
    #agentServicesTable .service-people,
    #agentServicesTable .service-dates {
      font-size: .72rem;
    }
    #agentServicesTable .service-actions {
      gap: .2rem;
    }
    #agentServicesTable .service-actions .btn {
      padding: .2rem .35rem;
    }
    #agentServicesTable tbody td[colspan] {
      grid-column: 1 / -1;
      grid-row: auto;
      text-align: center;
    }
  }
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    let currentPage = <?= (int)($page ?? 1) ?>;
    let totalPages = <?= (int)($total_pages ?? 1) ?>;
    let debounceTimer;

    var searchInput = document.getElementById('agentServicesSearch');
    var statusFilter = document.getElementById('statusFilter');
    var providerFilter = document.getElementById('providerFilter');
    var userFilter = document.getElementById('userFilter');
    var statusFilterMobile = document.getElementById('statusFilterMobile');
    var providerFilterMobile = document.getElementById('providerFilterMobile');
    var userFilterMobile = document.getElementById('userFilterMobile');
    var startDateFilter = document.getElementById('startDateFilter');
    var endDateFilter = document.getElementById('endDateFilter');
    var perPageFilter = document.getElementById('perPageFilter');
    var perPageFilterMobile = document.getElementById('perPageFilterMobile');
    var extraFiltersToggle = document.getElementById('toggleAgentServicesFilters');
    var extraFiltersPanel = document.getElementById('agentServicesExtraFilters');

    function syncMobileFilterValues() {
        if (statusFilter && statusFilterMobile) {
            statusFilterMobile.value = statusFilter.value;
        }
        if (providerFilter && providerFilterMobile) {
            providerFilterMobile.value = providerFilter.value;
        }
        if (userFilter && userFilterMobile) {
            userFilterMobile.value = userFilter.value;
        }
        if (perPageFilter && perPageFilterMobile) {
            perPageFilterMobile.value = perPageFilter.value;
        }
    }

    function syncDesktopFilterValues() {
        if (statusFilter && statusFilterMobile) {
            statusFilter.value = statusFilterMobile.value;
        }
        if (providerFilter && providerFilterMobile) {
            providerFilter.value = providerFilterMobile.value;
        }
        if (userFilter && userFilterMobile) {
            userFilter.value = userFilterMobile.value;
        }
        if (perPageFilter && perPageFilterMobile) {
            perPageFilter.value = perPageFilterMobile.value;
        }
    }

    if (extraFiltersToggle && extraFiltersPanel) {
        extraFiltersToggle.addEventListener('click', function() {
            const isHidden = extraFiltersPanel.classList.contains('d-none');
            extraFiltersPanel.classList.toggle('d-none', !isHidden);
            const label = this.querySelector('span');
            if (label) {
                label.textContent = isHidden ? 'Hide Filters' : 'More Filters';
            }
        });
    }

    // Event listeners
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => fetchAgentServices(1), 300);
        });
    }
    function registerFilterChangeHandlers() {
        if (statusFilter) statusFilter.addEventListener('change', () => fetchAgentServices(1));
        if (providerFilter) providerFilter.addEventListener('change', () => fetchAgentServices(1));
        if (userFilter) userFilter.addEventListener('change', () => fetchAgentServices(1));
        if (perPageFilter) perPageFilter.addEventListener('change', () => fetchAgentServices(1));
        if (statusFilterMobile) statusFilterMobile.addEventListener('change', function() {
            syncDesktopFilterValues();
            fetchAgentServices(1);
        });
        if (providerFilterMobile) providerFilterMobile.addEventListener('change', function() {
            syncDesktopFilterValues();
            fetchAgentServices(1);
        });
        if (userFilterMobile) userFilterMobile.addEventListener('change', function() {
            syncDesktopFilterValues();
            fetchAgentServices(1);
        });
        if (perPageFilterMobile) perPageFilterMobile.addEventListener('change', function() {
            syncDesktopFilterValues();
            fetchAgentServices(1);
        });
    }
    registerFilterChangeHandlers();
    if (startDateFilter) startDateFilter.addEventListener('change', () => fetchAgentServices(1));
    if (endDateFilter) endDateFilter.addEventListener('change', () => fetchAgentServices(1));

    // Load the tab only when it is opened; other backoffice pages share this shell.
    renderPagination(currentPage, totalPages);
    const agentServicesTab = document.getElementById('agent-services-tab');
    let agentServicesLoaded = false;
    const loadAgentServices = function() {
        if (!agentServicesLoaded) {
            agentServicesLoaded = true;
            fetchAgentServices(currentPage);
        }
    };
    if (agentServicesTab) {
        agentServicesTab.addEventListener('shown.bs.tab', loadAgentServices);
        if (agentServicesTab.classList.contains('active')) loadAgentServices();
    }

    function fetchAgentServices(page = 1) {
        currentPage = page;
        const search = searchInput ? searchInput.value.trim() : '';
        const activeStatus = statusFilter ? statusFilter.value : (statusFilterMobile ? statusFilterMobile.value : '');
        const activeProvider = providerFilter ? providerFilter.value : (providerFilterMobile ? providerFilterMobile.value : '');
        const activeUser = userFilter ? userFilter.value : (userFilterMobile ? userFilterMobile.value : '');
        const startDate = startDateFilter ? startDateFilter.value : '';
        const endDate = endDateFilter ? endDateFilter.value : '';
        const perPage = perPageFilter ? perPageFilter.value : (perPageFilterMobile ? perPageFilterMobile.value : '');

        const status = activeStatus;
        const provider = activeProvider;
        const user = activeUser;

        const params = new URLSearchParams({
            page: page,
            search: search,
            status: status,
            provider: provider,
            user: user,
            start_date: startDate,
            end_date: endDate,
            per_page: perPage
        });

        // Set loading state
        const tbody = document.getElementById('agentServicesTbody');
        if (!tbody) return;
        tbody.style.opacity = '0.5';

        fetch(`<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/agent_services_data?${params.toString()}`, {
            method: 'GET',
            cache: 'no-store',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          credentials: 'include'
        })
        .then(window.backofficeParseJson)
        .then(response => {
            tbody.style.opacity = '1';
            if (response.status === 'success') {
                tbody.innerHTML = response.html;
                currentPage = response.current_page || currentPage;
                totalPages = response.total_pages || totalPages;
                document.getElementById('paginationInfo').innerText = 
                    `Showing Page ${currentPage} of ${totalPages} (Total ${response.total_records} records)`;
                renderPagination(currentPage, totalPages);
            } else {
                agentServicesLoaded = false;
                console.error('Agent services response error', response);
            }
        })
        .catch(err => {
            agentServicesLoaded = false;
            tbody.style.opacity = '1';
            console.error("AJAX Error:", err);
        });
    }

    function renderPagination(current, total) {
        const nav = document.getElementById('paginationNav');
        nav.innerHTML = '';
        total = Math.max(1, total);

        // Previous button
        nav.innerHTML += `
            <li class="page-item ${current === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${current - 1}">&laquo;</a>
            </li>
        `;

        // Page links
        for (let i = 1; i <= total; i++) {
            if (i === 1 || i === total || (i >= current - 1 && i <= current + 1)) {
                nav.innerHTML += `
                    <li class="page-item ${i === current ? 'active' : ''}">
                        <a class="page-link" href="#" data-page="${i}">${i}</a>
                    </li>
                `;
            } else if (i === current - 2 || i === current + 2) {
                nav.innerHTML += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }

        // Next button
        nav.innerHTML += `
            <li class="page-item ${current === total ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${current + 1}">&raquo;</a>
            </li>
        `;

        // Attach click handlers
        nav.querySelectorAll('a.page-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetPage = parseInt(this.getAttribute('data-page'));
                if (targetPage && targetPage !== current && targetPage >= 1 && targetPage <= total) {
                    fetchAgentServices(targetPage);
                }
            });
        });
    }

    function buildExportUrl(format) {
        const params = new URLSearchParams({
            entity: 'agent_services',
            resource: 'list',
            format: format,
            search: searchInput ? searchInput.value.trim() : '',
            status: statusFilter ? statusFilter.value : (statusFilterMobile ? statusFilterMobile.value : ''),
            provider: providerFilter ? providerFilter.value : (providerFilterMobile ? providerFilterMobile.value : ''),
            user: userFilter ? userFilter.value : (userFilterMobile ? userFilterMobile.value : ''),
            start_date: startDateFilter ? startDateFilter.value : '',
            end_date: endDateFilter ? endDateFilter.value : ''
        });
        return `<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export?${params.toString()}`;
    }

    const exportPdfBtn = document.getElementById('agentServicesExportPdf');
    const exportExcelBtn = document.getElementById('agentServicesExportExcel');
    if (exportPdfBtn) {
        exportPdfBtn.addEventListener('click', function() {
            window.open(buildExportUrl('pdf'), 'agent_services_export_pdf', 'width=1000,height=700');
        });
    }
    if (exportExcelBtn) {
        exportExcelBtn.addEventListener('click', function() {
            window.location.href = buildExportUrl('xlsx');
        });
    }
});
</script>
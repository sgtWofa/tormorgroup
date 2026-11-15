/**
 * Operations Account Plugin - JavaScript
 * Handles cashdrawer export and modal interactions
 */

// Export current cashdrawer to PDF
function exportCashdrawerPDF(drawerId) {
    if (!drawerId) {
        alert('Cashdrawer ID not found');
        return false;
    }
    
    // Use the ROOT constant if available, otherwise compute from current URL
    let baseUrl = '';
    if (typeof ROOT !== 'undefined' && ROOT) {
        baseUrl = ROOT;
    } else {
        // Compute from current URL - find the first /admin/ occurrence
        const path = window.location.pathname;
        const adminPos = path.indexOf('/admin/');
        if (adminPos !== -1) {
            baseUrl = path.substring(0, adminPos);
        } else if (path.endsWith('/admin')) {
            // Handle /admin (no trailing slash) - get everything before /admin
            baseUrl = path.substring(0, path.length - 6);
        }
    }
    
    // Ensure baseUrl doesn't end with trailing slash
    baseUrl = baseUrl.replace(/\/$/, '');
    
    // Construct export URL (prepend origin to make absolute URL)
    let exportUrl = window.location.origin + baseUrl + '/admin/operations_accounts/export_cashdrawer_pdf/' + encodeURIComponent(drawerId);
    
    // Fix: Remove duplicate /admin/ if present (handle multiple occurrences)
    while (exportUrl.includes('/admin/admin/')) {
        exportUrl = exportUrl.replace('/admin/admin/', '/admin/');
    }
    
    // Debug: log the URL
    console.log('PDF Export URL:', exportUrl);
    
    // Open in new window
    const windowFeatures = 'width=900,height=1200,menubar=yes,toolbar=yes,location=no,status=yes,scrollbars=yes,resizable=yes';
    window.open(exportUrl, 'CashdrawerPDF', windowFeatures);
    return false;
}

// Open exchange denominations modal
function openExchangeModal(drawerId) {
    if (!drawerId) {
        alert('Cashdrawer ID not found');
        return false;
    }
    
    // Store drawer ID for later use
    window.currentDrawerId = drawerId;
    
    // Create and show modal
    const modal = new bootstrap.Modal(document.getElementById('exchangeDenoModal'));
    modal.show();
    return false;
}

// Open exchange denominations modal
function openChangeModal(drawerId) {
    if (!drawerId) {
        alert('Cashdrawer ID not found');
        return false;
    }
    
    // Store drawer ID for later use
    window.currentDrawerId = drawerId;
    
    // Create and show modal
    const modal = new bootstrap.Modal(document.getElementById('changeModal'));
    modal.show();
    return false;
}

// Get current cashdrawer ID from page context
function getCurrentCashdrawerId() {
    // Check if there's a data attribute on the page
    const drawerElement = document.querySelector('[data-drawer-id]');
    if (drawerElement) {
        return drawerElement.getAttribute('data-drawer-id');
    }
    
    // Check URL for drawer ID
    const urlMatch = window.location.pathname.match(/view_cashdrawer\/(\d+)/);
    if (urlMatch) {
        return urlMatch[1];
    }
    
    // Check for hidden input or data attribute
    const drawerInput = document.querySelector('input[name="drawer_id"], input[data-drawer-id]');
    if (drawerInput) {
        return drawerInput.value;
    }
    
    return null;
}

// Initialize export buttons and cashdrawer AJAX on page load
function initOperationsAccountPlugin() {
    if (window.__opsAccountPluginInitialized) {
        return;
    }
    window.__opsAccountPluginInitialized = true;

    // Handle export buttons with data attributes
    const exportButtons = document.querySelectorAll('[data-export-drawer]');
    exportButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const drawerId = this.getAttribute('data-export-drawer');
            exportCashdrawerPDF(drawerId);
        });
    });

    // Handle export links that need drawer ID injection
    const exportLinks = document.querySelectorAll('a[href*="export_cashdrawer_pdf"]');
    exportLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Allow normal link behavior - it should already have the correct ID
        });
    });

    // Cashdrawers dynamic pagination + filtering via existing ajax_cashdrawers endpoint
    const cashRegion = document.getElementById('opsCashdrawersRegion');
    if (cashRegion) {
        const ajaxUrl = cashRegion.getAttribute('data-ajax-url');
        if (ajaxUrl) {
            function initUserFilterSelect2() {
                const userFilterEl = cashRegion.querySelector('[data-role="ops-user-filter"]');
                if (!userFilterEl) {
                    return;
                }

                const jq = window.jQuery || window.$;
                if (!jq || !jq.fn || !jq.fn.select2) {
                    setTimeout(initUserFilterSelect2, 200);
                    return;
                }

                try {
                    if (!userFilterEl.dataset.opsSelect2Bound) {
                        if (userFilterEl && !userFilterEl.name) {
                            userFilterEl.name = 'user_id[]';
                        }
                        jq(userFilterEl).select2({
                            width: '100%',
                            allowClear: true,
                            placeholder: userFilterEl.getAttribute('data-placeholder') || 'Search users',
                            dropdownParent: jq(userFilterEl).parent(),
                            closeOnSelect: false,
                            multiple: true
                        });
                        if (!userFilterEl.__opsSelect2ChangeBound) {
                            jq(userFilterEl).on('change.opsUserFilter', function() {
                                loadCash(1);
                            });
                            userFilterEl.__opsSelect2ChangeBound = true;
                        }
                        userFilterEl.dataset.opsSelect2Bound = '1';
                    }
                } catch (select2Err) {
                    console.warn('Select2 init failed for cashdrawer user filter', select2Err);
                }
            }

            async function loadCash(page) {
                try {
                    const fromEl = cashRegion.querySelector('[data-role="ops-date-from"]');
                    const toEl = cashRegion.querySelector('[data-role="ops-date-to"]');
                    const userFilterEl = cashRegion.querySelector('[data-role="ops-user-filter"]');
                    const pageSizeEl = cashRegion.querySelector('[data-role="ops-page-size"]');
                    const currentLimit = pageSizeEl && pageSizeEl.value ? pageSizeEl.value : '';

                    const url = new URL(ajaxUrl, window.location.origin);
                    if (fromEl && fromEl.value) url.searchParams.set('date_from', fromEl.value);
                    if (toEl && toEl.value) url.searchParams.set('date_to', toEl.value);
                    if (userFilterEl) {
                        const selectedUserIds = Array.from(userFilterEl.selectedOptions)
                            .map(opt => opt.value.trim())
                            .filter(v => v !== '');
                        if (selectedUserIds.length > 0) {
                            selectedUserIds.forEach((id, index) => {
                                url.searchParams.append('user_id[]', id);
                            });
                        }
                    }
                    if (currentLimit) url.searchParams.set('limit', currentLimit);
                    if (page) url.searchParams.set('page', page);

                    const resp = await fetch(url.toString(), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const html = await resp.text();
                    cashRegion.innerHTML = html;
                    initUserFilterSelect2();

                    if (currentLimit) {
                        const newPageSizeEl = cashRegion.querySelector('[data-role="ops-page-size"]');
                        if (newPageSizeEl) {
                            newPageSizeEl.value = currentLimit;
                        }
                    }

                    bindCashPager();
                } catch (err) {
                    console.error('Failed to load cashdrawers via AJAX', err);
                }
            }

            function bindCashPager() {
                const links = cashRegion.querySelectorAll('.pagination a');
                links.forEach(link => {
                    link.addEventListener('click', function(e) {
                        const href = this.getAttribute('href');
                        if (!href || href === '#') return;
                        e.preventDefault();
                        try {
                            const u = new URL(href, window.location.origin);
                            const page = u.searchParams.get('page') || '1';
                            loadCash(page);
                        } catch (err) {
                            console.error(err);
                        }
                    });
                });
            }

            if (!cashRegion.__opsCashFiltersBound) {
                cashRegion.addEventListener('change', function(e) {
                    const target = e.target;
                    if (!target) return;
                    if (
                        target.matches('[data-role="ops-date-from"]') ||
                        target.matches('[data-role="ops-date-to"]') ||
                        target.matches('[data-role="ops-user-filter"]') ||
                        target.matches('[data-role="ops-page-size"]')
                    ) {
                        loadCash(1);
                    }
                });
                cashRegion.__opsCashFiltersBound = true;
            }

            initUserFilterSelect2();
            loadCash(1);
        }
    }

    // Sims dynamic pagination via ajax_sims endpoint (page + limit + filters + search)
    const simsRegion = document.getElementById('opsSimsRegion');
    if (simsRegion) {
        const ajaxUrl = simsRegion.getAttribute('data-ajax-url');
        if (ajaxUrl) {

            async function loadSims(page) {
                try {
                    // Read current controls from the live DOM
                    const pageSizeEl   = simsRegion.querySelector('[data-role="ops-page-size"]');
                    const searchEl     = simsRegion.querySelector('[data-role="ops-sim-search"]');
                    const currentLimit = pageSizeEl && pageSizeEl.value ? pageSizeEl.value : '';
                    const currentQ     = searchEl && searchEl.value ? searchEl.value.trim() : '';
                    // Bulk network filter lives outside the ajax region
                    const networkFilterEl = document.getElementById('bulkNetworkFilter');
                    const currentNetwork  = networkFilterEl && networkFilterEl.value ? networkFilterEl.value : '';

                    const url = new URL(ajaxUrl, window.location.origin);
                    if (currentLimit)   url.searchParams.set('limit', currentLimit);
                    if (currentQ)       url.searchParams.set('q', currentQ);
                    if (currentNetwork) url.searchParams.set('network', currentNetwork);
                    if (page)           url.searchParams.set('page', page);

                    const resp = await fetch(url.toString(), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const html = await resp.text();
                    simsRegion.innerHTML = html;

                    try {
                        document.dispatchEvent(new CustomEvent('ops:sims:updated'));
                    } catch (e) {
                    }

                    // Re-apply current controls so UI stays in sync
                    if (currentLimit) {
                        const newPageSizeEl = simsRegion.querySelector('[data-role="ops-page-size"]');
                        if (newPageSizeEl) newPageSizeEl.value = currentLimit;
                    }
                    if (currentQ) {
                        const newSearchEl = simsRegion.querySelector('[data-role="ops-sim-search"]');
                        if (newSearchEl) {
                            newSearchEl.value = currentQ;
                            // Keep search bar active so user can continue typing after page updates
                            newSearchEl.focus();
                            const len = newSearchEl.value.length;
                            newSearchEl.setSelectionRange(len, len);
                        }
                    }

                    // Update Sims header KPIs using metrics from the fragment
                    const metricsEl = simsRegion.querySelector('[data-role="ops-sims-metrics"]');
                    if (metricsEl) {
                        const totalSims      = Number(metricsEl.getAttribute('data-total-sims') || '0');
                        const totalSold      = Number(metricsEl.getAttribute('data-total-sold') || '0');
                        const sellThrough    = Number(metricsEl.getAttribute('data-sell-through') || '0');
                        const activeNetworks = Number(metricsEl.getAttribute('data-active-networks') || '0');

                        const fmtInt = n => new Intl.NumberFormat().format(Math.round(n || 0));
                        const totalEl = document.getElementById('opsSimsTotalValue');
                        const soldEl  = document.getElementById('opsSimsSoldValue');
                        const sellEl  = document.getElementById('opsSimsSellValue');
                        const netsEl  = document.getElementById('opsSimsNetworksValue');
                        const trendEl = document.getElementById('opsSimsSellTrend');

                        if (totalEl) totalEl.textContent = fmtInt(totalSims);
                        if (soldEl)  soldEl.textContent  = fmtInt(totalSold);
                        if (sellEl)  sellEl.textContent  = sellThrough.toFixed(1) + '%';
                        if (netsEl)  netsEl.textContent  = fmtInt(activeNetworks);

                        if (trendEl) {
                            trendEl.classList.remove('positive', 'negative');
                            const cls = sellThrough >= 50 ? 'positive' : 'negative';
                            trendEl.classList.add(cls);
                            trendEl.textContent = sellThrough >= 50
                                ? 'Healthy rotation'
                                : 'Slow moving inventory';
                        }
                    }

                    bindSimsPager();
                } catch (err) {
                    console.error('Failed to load sims via AJAX', err);
                }
            }

            function bindSimsPager() {
                const links = simsRegion.querySelectorAll('.pagination a');
                links.forEach(link => {
                    link.addEventListener('click', function(e) {
                        const href = this.getAttribute('href');
                        if (!href || href === '#') return;
                        e.preventDefault();
                        try {
                            const u = new URL(href, window.location.origin);
                            const page = u.searchParams.get('page') || '1';
                            loadSims(page);
                        } catch (err) {
                            console.error(err);
                        }
                    });
                });
            }

            // Delegated handlers for Sims filters/search within the region
            if (!simsRegion.__opsSimsFiltersBound) {
                // Page-size reacts on change
                simsRegion.addEventListener('change', function(e) {
                    const target = e.target;
                    if (!target) return;
                    if (target.matches('[data-role="ops-page-size"]')) {
                        loadSims(1);
                    }
                });

                // Search reacts on input (debounced)
                let simsSearchTimer = null;
                simsRegion.addEventListener('input', function(e) {
                    const target = e.target;
                    if (!target) return;
                    if (target.matches('[data-role="ops-sim-search"]')) {
                        if (simsSearchTimer) {
                            clearTimeout(simsSearchTimer);
                        }
                        simsSearchTimer = setTimeout(function() {
                            loadSims(1);
                        }, 400);
                    }
                });

                // Bulk network filter (outside simsRegion) triggers server-side filter as well
                const networkFilterEl = document.getElementById('bulkNetworkFilter');
                if (networkFilterEl && !networkFilterEl.__opsSimsNetworkBound) {
                    networkFilterEl.addEventListener('change', function() {
                        loadSims(1);
                    });
                    networkFilterEl.__opsSimsNetworkBound = true;
                }

                simsRegion.__opsSimsFiltersBound = true;
            }

            // Initial wire for the existing pager
            bindSimsPager();

            loadSims(1);
        }
    }
}

window.initOperationsAccountPlugin = initOperationsAccountPlugin;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        initOperationsAccountPlugin();
    });
} else {
    initOperationsAccountPlugin();
}
# CRM Feature Implementation Plan

## Current Status: Task 2 In Progress

### Task 1 ✓ Completed
- Explored plugin architecture and located customers management page

### Customers Page Location & Structure
- **Location**: `plugins/cashpickup/views/admin/customers.php`
- **URL**: `/admin/cashpickup/customers`
- **Current Tabs**: 
  1. "customers" (Customers Ledger)
  2. "gl" (General Ledger)
- **Tab Handling**: Uses `[data-cashpickup-tab]` buttons, switches via JavaScript click handlers
- **AJAX Endpoint**: `ROOT/admin/cashpickup/customers_ajax` (POST)
- **Permissions**: `view_cashpickup`, with agent visibility scoping for non-privileged users

### Current Tab Structure (Line 533-541)
```html
<ul class="nav nav-tabs mb-3" id="cashpickupCustomersTabs" role="tablist">
	<li class="nav-item" role="presentation">
		<button class="nav-link active" data-cashpickup-tab="customers">
	<li class="nav-item" role="presentation">
		<button class="nav-link" data-cashpickup-tab="gl">General Ledger (GL)</button>
```

### JavaScript Tab Handling (Line 716-722)
- Tab buttons listen for click event
- Update `tabInput.value` with nextTab value
- Call `setActiveTabUi()` to highlight active tab
- Call `submitFilters()` to load tab content via AJAX

### AJAX Response Handler
- Receives HTML from POST request to endpoint
- Injects HTML into `#cashpickupCustomersContainer`
- Re-renders pager, updates URLs via `history.replaceState`

## CRM Tab Implementation Plan

### Step 1: Update customers.php Tab Navigation ✓ IN PROGRESS
- Add new tab button: `<button class="nav-link" data-cashpickup-tab="crm">CRM</button>`
- Update placeholder text logic for CRM tab
- Update tab validation to include 'crm' in allowed tabs list
- Add CRM data fetching logic
- Create render function for CRM

### AJAX Flow Understanding
- customers_ajax POST endpoint in plugin.php passes $_POST as $_GET and includes customers.php
- customers.php checks `$isAjax = (string)($_GET['ajax'] ?? '') === '1'`
- At line ~529: `if($isAjax){ ($activeTab === 'gl' ? $renderGeneralLedger : $renderResults)(); return; }`
- For non-AJAX (full page load), it renders the full HTML with nav/header
- For AJAX, it only renders the tab content (no header/nav)

### Implementation Strategy
1. Modify customers.php line 23-25 to include 'crm' in allowed tabs
2. Create conditional block: `if ($activeTab === 'crm') { /* CRM data logic */ }`
3. Create `$renderCRM = function() { /* CRM UI */ };`
4. Modify AJAX return to handle CRM tab (line ~529)
5. Add CRM tab button to navigation (line 533-541)
6. Update placeholder logic (line ~556) for CRM filter

### CRM View Content Structure
- **Section 1**: Phone Search Input (large, prominent)
- **Section 2**: Advanced Filters (collapsible)
- **Section 3**: Customer Profile Summary (if phone search has result)
- **Section 4**: Metrics Grid (transactions, amounts, returning rate per service)
- **Section 5**: Top Rankings (top customers/agents per service)
- **Section 6**: Transaction History Table (scrollable)

### Database Tables to Query
- agencybankings
- airtimebundles
- momoremits
- payments
- payment_services
- reversals
- sims
- cashpickups
- ezwichs
- cfas

### Utility Functions to Leverage
- `normalizePhone()` - from dashboard
- `$approvedOnlyWhere()` - dashboard filtering helper
- `user_can()` - permission checks
- `Core\Session` - agent info

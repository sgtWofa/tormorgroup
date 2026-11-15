<script>
  window.backofficeParseJson = async function(response) {
    const text = await response.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch (error) {
      const contentType = response.headers.get('content-type') || '';
      throw new Error(contentType.includes('text/html')
        ? 'The server returned an HTML page. Your session may have expired; refresh and sign in again.'
        : 'The server returned an invalid Back Office response.');
    }
    if (!response.ok) {
      throw new Error(data.message || `Back Office request failed (${response.status}).`);
    }
    return data;
  };
</script>
<style>
  
  /* Shell */
  .backoffice-shell {
    max-width: 100%;
    margin: 1.5rem auto;
  }
  .bo-hero {
    border-radius: 1.25rem;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1rem;
    border: 1px solid var(--bs-border-color);
		background: linear-gradient(135deg, rgba(var(--bs-primary-rgb, 13,110,253),0.12), rgba(var(--bs-warning-rgb, 255,193,7),0.12));
    box-shadow: 0 18px 40px rgba(15,23,42,.4);
  }
  body[data-bs-theme="light"] .bo-hero {
    background: linear-gradient(135deg, #0f172a, #111827);
  }
  .bo-hero-label {
    font-size: .7rem;
    text-transform: uppercase;
    letter-spacing: .08em;
  }
  .bo-hero-title {
    font-size: 1.3rem;
    font-weight: 600;
    margin-bottom: .25rem;
  }
  .bo-hero-sub {
    font-size: .88rem;
  }

  /* Tabs */
  #profileTabs {
    margin-bottom: .75rem;
    border-bottom: none;
    gap: .35rem;
  }
  #profileTabs .nav-link {
    border-radius: 999px;
    border: 1px solid transparent;
    padding: .4rem .85rem;
    font-size: .85rem;
    font-weight: 500;
    color: var(--bs-secondary-color) !important;
    background: transparent;
  }
  #profileTabs .nav-link .badge {
    margin-left: .35rem;
  }

  .active,#profileTabs .nav-link.active,
  #profileTabs .nav-link.active:focus,
  #profileTabs .nav-link.active:hover {
		background-image: linear-gradient(135deg, rgba(var(--bs-primary-rgb, 13,110,253),0.12), rgba(var(--bs-warning-rgb, 255,193,7),0.12)) !important;
		background-color: transparent !important;
		color: var(--bs-body-color) !important;
		border-color: rgba(var(--bs-primary-rgb,13,110,253), 0.25) !important;
  }

  /* Tab panes */
  #profileTabsContent .tab-pane {
    border-radius: 1.25rem;
    border: 1px solid var(--bs-border-color);
    background: var(--bs-body-bg);
    padding: 1.2rem 1.3rem;
    box-shadow: 0 25px 45px rgba(179, 180, 182, 0.08);
  }
  body[data-bs-theme="dark"] #profileTabsContent .tab-pane {
    background: radial-gradient(circle at top left, rgba(30,64,175,.35), rgba(15,23,42,1));
  }
  #profileTabsContent h4 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--bs-body-color);
  }
  body[data-bs-theme="dark"] #profileTabsContent h4 {
    color: rgba(255,255,255,0.92) !important;
  }
  #profileTabsContent p {
    font-size: .84rem;
    color: var(--bs-secondary-color);
  }

  /* Tables inside panes */
  #profileTabsContent table.table {
    border-radius: .75rem;
    overflow: hidden;
  }

<?php
  $notificationPillColorConfig = function_exists('admin_settings_notification_pill_color_config') ? admin_settings_notification_pill_color_config() : ['serious'=>'#dc3545','mild'=>'#ffc107','regular'=>'#0d6efd'];
  $notificationPillPluginMap = function_exists('admin_settings_notification_pill_plugin_map_config') ? admin_settings_notification_pill_plugin_map_config() : ['serious'=>[],'mild'=>[],'regular'=>[]];
  $notificationPillUrgency = static function(string $id) use ($notificationPillPluginMap): string {
    $id = strtolower(trim((string)$id));
    foreach(['serious','mild','regular'] as $cat){
      if(!empty($notificationPillPluginMap[$cat]) && is_array($notificationPillPluginMap[$cat])){
        foreach($notificationPillPluginMap[$cat] as $pid){
          $pid = strtolower(trim((string)$pid));
          if($pid === '') continue;
          if($id === $pid || strpos($id, $pid) !== false){
            return $cat;
          }
        }
      }
    }
    $serious_specific = ['backoffice_payment', 'backoffice payment', 'backoffice_request', 'backoffice request', 'to&from bank', 'to_from_bank', 'bank_transfer'];
    foreach ($serious_specific as $kw) {
      if (strpos($id, $kw) !== false) {
        return 'serious';
      }
    }
    $mild_specific = ['backoffice_commission', 'backoffice commission', 'backoffice_reconciliation', 'backoffice reconciliation'];
    foreach ($mild_specific as $kw) {
      if (strpos($id, $kw) !== false) {
        return 'mild';
      }
    }
    $serious = ['agencybanking', 'airtimebundle', 'cfa', 'momoremit', 'payment_service', 'ezwich', 'sim', 'cashpickup'];
    foreach ($serious as $kw) {
      if (strpos($id, $kw) !== false) {
        return 'serious';
      }
    }
    $mild = ['reversal', 'pettycash'];
    foreach ($mild as $kw) {
      if (strpos($id, $kw) !== false) {
        return 'mild';
      }
    }
    return 'regular';
  };
  $notificationPillBadgeAttributes = static function(string $urgency, array $config, int $count): string {
    $value = trim((string)($config[$urgency] ?? ''));
    $classes = ['badge', 'fw-bolder', 'rounded-circle', 'pill'];
    $styles = [];
    if(preg_match('/^#[0-9A-Fa-f]{6}$/', $value)){
      $classes[] = 'text-white';
      $styles[] = 'background-color:' . $value;
      $r = hexdec(substr($value,1,2));
      $g = hexdec(substr($value,3,2));
      $b = hexdec(substr($value,5,2));
      $brightness = ($r * 299 + $g * 587 + $b * 114) / 1000;
      $styles[] = 'color:' . ($brightness < 150 ? '#ffffff' : '#000000');
    } elseif($value !== ''){
      $classes = array_merge($classes, preg_split('/\s+/', $value));
    } else {
      $classes[] = 'bg-warning';
      $classes[] = 'text-dark';
    }
    if($count <= 0){
      $classes[] = 'd-none';
    }
    $attr = 'class="' . esc(implode(' ', $classes)) . '"';
    if(!empty($styles)){
      $attr .= ' style="' . esc(implode(';', $styles)) . '"';
    }
    return $attr;
  };
?>
  tbody tr {
    white-space: nowrap;
  }
  .wrap,
  tbody tr.wrap {
    white-space: normal;
  }

  .bo-stack-cell{
    white-space: normal;
    max-width: 520px;
  }
  .bo-stack-cell .k{
    font-size: .68rem;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--bs-secondary-color);
  }
  .bo-stack-cell .line{
    display: flex;
    gap: .45rem;
    align-items: baseline;
  }
  .bo-stack-cell .line + .line{
    margin-top: .12rem;
  }
  .bo-stack-amount{
    text-align: end;
    white-space: normal;
    min-width: 120px;
  }

  @media (max-width: 768px) {
    .bo-hero {
      padding: 1rem 1.1rem;
    }
    .bo-hero-title {
      font-size: 1.1rem;
    }
    #profileTabs {
      flex-wrap: wrap;
    }
  }

  @media (min-width: 992px) {
    .d-lg-table-header-group {
      display: table-header-group !important;
    }
    .d-lg-table-footer-group {
      display: table-footer-group !important;
    }
  }
</style>

<?php
$vaultAttentionCount = function_exists('Vaults\\attentionCount')
  ? \Vaults\attentionCount((int)(get_session()->id ?? 0))
  : 0;
?>
<div class="backoffice-shell col-md-12 mx-auto">
  <div class="bo-hero">
    <div class="bo-hero-label mb-1">Backoffice workspace</div>
    <div class="bo-hero-title">Requests, payments, bank flows & reconciliations</div>
    <p class="bo-hero-sub mb-0">Review staff movements, commissions and wallet activity from a single modern view.</p>
  </div>

  <?php if(!empty($overdueCommissionCount)): ?>
    <div class="alert alert-warning border shadow-sm" data-attention-sound="1" data-attention-key="backoffice-overdue-commissions-<?=date('Y-m-d')?>-<?= (int)$overdueCommissionCount ?>">
      <div class="fw-semibold mb-1">Commission submission required</div>
      <div><?=esc($overdueCommissionMessage ?? '')?></div>
      <div class="mt-2">
        <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/pay_commission" class="btn btn-sm btn-dark">Submit overdue commissions</a>
      </div>
    </div>
  <?php endif; ?>

  <div>
    
    <!-- Tabs Navigation -->
    <ul class="nav nav-tabs" id="profileTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="request-tab" data-bs-toggle="tab" data-bs-target="#request" type="button" role="tab">REQUESTS 
          <span id="bo-tab-requests-badge" <?= $notificationPillBadgeAttributes($notificationPillUrgency('backoffice_request'), $notificationPillColorConfig, (int)($countBadge ?? 0)) ?> data-notification-urgency="<?= esc($notificationPillUrgency('backoffice_request')) ?>" data-notification-badge="backoffice_request"><?= !empty($countBadge) && $countBadge > 0 ? esc($countBadge) : '' ?></span>
        </button>
      </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab">PAYMENTS 
            <span id="bo-tab-payments-badge" <?= $notificationPillBadgeAttributes($notificationPillUrgency('backoffice_payment'), $notificationPillColorConfig, (int)($countPayAll ?? 0)) ?> data-notification-urgency="<?= esc($notificationPillUrgency('backoffice_payment')) ?>" data-notification-badge="backoffice_payment"><?= !empty($countPayAll) && $countPayAll > 0 ? esc($countPayAll) : '' ?></span>
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="commissions-tab" data-bs-toggle="tab" data-bs-target="#commissions" type="button" role="tab">COMMISSIONS
            <span id="bo-tab-commissions-badge" data-overdue-count="<?= (int)($overdueCommissionCount ?? 0) ?>" <?= $notificationPillBadgeAttributes($notificationPillUrgency('backoffice_commission'), $notificationPillColorConfig, (int)($countComissions ?? 0)) ?> data-notification-urgency="<?= esc($notificationPillUrgency('backoffice_commission')) ?>" data-notification-badge="backoffice_commission"><?= !empty($countComissions) && $countComissions > 0 ? esc($countComissions) : '' ?></span>
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="bank-transfers-tab" data-bs-toggle="tab" data-bs-target="#bank-transfers" type="button" role="tab">TO &amp; FROM
            <span <?= $notificationPillBadgeAttributes($notificationPillUrgency('backoffice_bank_transfers'), $notificationPillColorConfig, (int)($countBankTransfersBadge ?? 0)) ?> data-notification-urgency="<?= esc($notificationPillUrgency('backoffice_bank_transfers')) ?>" data-notification-badge="backoffice_bank_transfers"><?= !empty($countBankTransfersBadge) && $countBankTransfersBadge > 0 ? esc($countBankTransfersBadge) : '' ?></span>
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="vaults-tab" data-bs-toggle="tab" data-bs-target="#vaults" type="button" role="tab">VAULTS
            <?php if($vaultAttentionCount > 0): ?><span class="badge rounded-pill bg-danger ms-1" aria-label="<?=$vaultAttentionCount?> Vault activities require your attention"><?=esc((string)$vaultAttentionCount)?></span><?php endif; ?>
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="agent-services-tab" data-bs-toggle="tab" data-bs-target="#agent-services" type="button" role="tab">AGENT SERVICES
            <span <?= $notificationPillBadgeAttributes($notificationPillUrgency('backoffice_agent_services'), $notificationPillColorConfig, (int)($countAgentServicesBadge ?? 0)) ?> data-notification-urgency="<?= esc($notificationPillUrgency('backoffice_agent_services')) ?>" data-notification-badge="backoffice_agent_services"><?= !empty($countAgentServicesBadge) && $countAgentServicesBadge > 0 ? esc($countAgentServicesBadge) : '' ?></span>
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="reconciliations-tab" data-bs-toggle="tab" data-bs-target="#reconciliations" type="button" role="tab">RECONCILIATIONS</button>
        </li>
        <?php if(function_exists('backoffice_user_can_view_general_balance') && backoffice_user_can_view_general_balance(get_session())): ?>
          <li class="nav-item" role="presentation">
            <a class="nav-link" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/general_balance">GENERAL BALANCING</a>
          </li>
        <?php endif; ?>
    </ul>

    <!-- Tabs Content -->
    <div class="tab-content" id="profileTabsContent">
      <!-- Overview Tab -->
      <div class="tab-pane fade show active" id="request" role="tabpanel" aria-labelledby="request-tab">          
        <h4>Requests</h4>
        <p>Users can manage their requests here.</p>        
        <?php require plugin_path('views/admin/requests/request.php'); ?>					
      </div>
      
      <!-- Transactions Tab -->
      <div class="tab-pane fade" id="mypayments" role="tabpanel" aria-labelledby="mypayments-tab">    
        <h4>My Payments</h4>
        <p>Users can manage their payments to backoffice here.</p>        
        	<?php require plugin_path('views/admin/payments/mypayment.php'); ?>	
      </div>
      
      <!-- Transactions Tab -->
      <div class="tab-pane fade" id="payments" role="tabpanel" aria-labelledby="payments-tab">
        <h4>Payments</h4>
        <p>Users can manage their payments and that of other users to backoffice here.</p>  
          <?php require plugin_path('views/admin/payments/payment.php'); ?> 
      </div>

      <div class="tab-pane fade" id="bank-transfers" role="tabpanel" aria-labelledby="bank-transfers-tab">
        <h4>TO &amp; FROM</h4>
        <p>Track bank movements from a cashdrawer or branch vault, verification, receipts, and approval here.</p>
        <?php require plugin_path('views/admin/bank_transfers/bank_transfers.php'); ?>
      </div>

      <div class="tab-pane fade" id="vaults" role="tabpanel" aria-labelledby="vaults-tab">
        <?php if(function_exists('vaults_render_backoffice_tab')): ?>
          <?php vaults_render_backoffice_tab(); ?>
        <?php else: ?>
          <div class="alert alert-warning">Vaults are currently unavailable. Please refresh or contact an administrator.</div>
        <?php endif; ?>
      </div>

      <div class="tab-pane fade" id="agent-services" role="tabpanel" aria-labelledby="agent-services-tab">
        <h4>AGENT SERVICES</h4>
        <p>Manage multi-stage agent service requests and cash/ecash transactions here.</p>
        <?php require plugin_path('views/admin/agent_services/agent_services.php'); ?>
      </div>
      
      <!-- Sims Tab -->
      <div class="tab-pane fade" id="reconciliations" role="tabpanel" aria-labelledby="reconciliations-tab">          
        	<?php require plugin_path('views/admin/reconciliations/reconciliations_list.php'); ?>
      </div>
      
      <!-- Wallets Tab -->
      <div class="tab-pane fade" id="wallets" role="tabpanel" aria-labelledby="wallets-tab">
        	<?php require plugin_path('views/admin/wallets.php'); ?>
      </div>
      
      <!-- Statements Tab -->
      <div class="tab-pane fade" id="statements" role="tabpanel" aria-labelledby="statements-tab">
          <?php require plugin_path('views/admin/statements.php'); ?>
      </div>
      
      <!-- Statements Tab -->
      <div class="tab-pane fade" id="commissions" role="tabpanel" aria-labelledby="commissions-tab">
          <?php require plugin_path('views/admin/commissions/commissions.php'); ?>
      </div>
      
      <!-- Statements Tab
      <div class="tab-pane fade" id="evdselfstocking" role="tabpanel" aria-labelledby="evdselfstocking-tab">            
        <h4>Attendance</h4>
        <p>Users can manage their attendances here.</p>    
      </div> -->
      
      <!-- Security Tab -->
      <div class="tab-pane fade" id="attendance" role="tabpanel" aria-labelledby="attendance-tab">
        <h4>Attendance</h4>
        <p>Users can manage their attendances here.</p>		    
      </div>
    </div>
  </div>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const storageKey = "backoffice.activeTab";
  const tabs = Array.from(document.querySelectorAll("#profileTabs .nav-link[data-bs-target]"));
  if (!tabs.length) {
    return;
  }

  const validTargets = new Set(tabs.map(tab => tab.getAttribute("data-bs-target")));
  const hashTarget = window.location.hash && validTargets.has(window.location.hash) ? window.location.hash : "";
  const storedTarget = localStorage.getItem(storageKey);
  const initialTarget = hashTarget || (storedTarget && validTargets.has(storedTarget) ? storedTarget : "");

  function activateTabByTarget(target) {
    if (!target || !validTargets.has(target)) {
      return;
    }
    const tabEl = document.querySelector(`#profileTabs .nav-link[data-bs-target="${target}"]`);
    const paneEl = document.querySelector(`#profileTabsContent > ${target}`);
    if (!tabEl) {
      return;
    }
    tabs.forEach(item => {
      const itemTarget = item.getAttribute("data-bs-target") || "";
      const itemPane = itemTarget ? document.querySelector(`#profileTabsContent > ${itemTarget}`) : null;
      const isActive = item === tabEl;
      item.classList.toggle("active", isActive);
      item.setAttribute("aria-selected", isActive ? "true" : "false");
      if (itemPane) {
        itemPane.classList.toggle("active", isActive);
        itemPane.classList.toggle("show", isActive);
      }
    });
    if (window.bootstrap && window.bootstrap.Tab) {
      window.bootstrap.Tab.getOrCreateInstance(tabEl).show();
      return;
    }
    if (paneEl) {
      paneEl.classList.add("active", "show");
    }
  }

  if (initialTarget) {
    activateTabByTarget(initialTarget);
  }

  tabs.forEach(tab => {
    tab.addEventListener("shown.bs.tab", function (event) {
      const target = event.target.getAttribute("data-bs-target") || "";
      if (!target) {
        return;
      }
      tabs.forEach(item => {
        const itemTarget = item.getAttribute("data-bs-target") || "";
        const itemPane = itemTarget ? document.querySelector(`#profileTabsContent > ${itemTarget}`) : null;
        const isActive = item === event.target;
        item.classList.toggle("active", isActive);
        item.setAttribute("aria-selected", isActive ? "true" : "false");
        if (itemPane) {
          itemPane.classList.toggle("active", isActive);
          itemPane.classList.toggle("show", isActive);
        }
      });
      localStorage.setItem(storageKey, target);
      if (window.location.hash !== target) {
        history.replaceState(null, "", target);
      }
    });
  });
});
</script>

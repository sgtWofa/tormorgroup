<?php if (!empty($agent_services_rows)): ?>
    <?php foreach ($agent_services_rows as $row): ?>
<?php
            $status = trim((string)($row->status ?? 'Pending'));
            $statusLower = strtolower($status);
            $badgeClass = 'bg-secondary';
            if ($statusLower === 'completed') $badgeClass = 'bg-success';
            elseif ($statusLower === 'rejected') $badgeClass = 'bg-danger';
            elseif (strpos($statusLower, 'pending') !== false) $badgeClass = 'bg-warning-subtle text-warning';
            elseif ($statusLower === 'assigned') $badgeClass = 'bg-info-subtle text-warning';
            elseif (in_array($statusLower, ['cancelled', 'canceled'], true)) $badgeClass = 'bg-dark';
            
            $providerText = !empty($row->provider) ? $row->provider : ($row->paying_network ?? '');
            if (empty($providerText) && !empty($row->receiving_network)) {
                $providerText = $row->receiving_network;
            }
            $createdDateTime = !empty($row->date_created) ? get_date_time($row->date_created) : '-';
            $updatedDateTime = !empty($row->date_updated) ? get_date_time($row->date_updated) : '';
        ?>
        <tr>
            <td data-label="Service">
                <div class="fw-bold text-primary">#<?=esc($row->id ?? '')?></div>
                <div class="fw-semibold"><?=esc($row->customer_type ?? '')?> <span class="text-muted fw-normal"><?=esc($row->service_type ?? '')?></span></div>
                <div class="service-dates small text-muted mt-1">
                    <span>Created <?=esc($createdDateTime)?></span>
                    <?php if ($updatedDateTime !== ''): ?><span class="d-block">Updated <?=esc($updatedDateTime)?></span><?php endif; ?>
                </div>
            </td>
            <td data-label="Agent / People">
                <div class="fw-semibold"><?=esc(!empty($row->agent_name) ? $row->agent_name : (!empty($row->business_name) ? $row->business_name : '-'))?></div>
                <small class="text-muted"><?=esc($providerText)?> <?=esc($row->agent_number ?? '')?></small>
              <div class="service-people">
                <div><span class="text-muted">By</span> <?=esc(trim(($row->creator_first_name ?? '') . ' ' . ($row->creator_last_name ?? ''))) ?: '-'?></div>
                <?php if (!empty($row->assigned_to)): ?><div class="small text-muted">Assigned <?=esc(trim(($row->assigned_first_name ?? '') . ' ' . ($row->assigned_last_name ?? '')))?></div><?php endif; ?>
                <?php if (!empty($row->updated_by_first_name) || !empty($row->updated_by_last_name)): ?><div class="small text-muted">Handled <?=esc(trim(($row->updated_by_first_name ?? '') . ' ' . ($row->updated_by_last_name ?? '')))?></div><?php endif; ?>
              </div>
            </td>
            <td data-label="Amount / Status">
                <div class="service-financial">
                    <span class="fw-semibold"><?=number_format((float)($row->amount ?? 0), 2)?></span>
                    <span class="badge <?=esc($badgeClass)?> rounded-pill">
                        <?=esc($status)?>
                        <?= (isset($row->stage) && (int)$row->stage > 1 && strpos($statusLower, 'pending') !== false) ? '(Stage '.esc($row->stage).')' : '' ?>
                    </span>
                </div>
                <?php if ($statusLower === 'rejected' && !empty($row->reject_reason)): ?>
                <div class="small text-danger mt-1">Reason: <?=esc($row->reject_reason)?></div>
                <?php endif; ?>
                <?php if (in_array($statusLower, ['cancelled', 'canceled'], true) && !empty($row->cancel_reason)): ?>
                <div class="small text-muted mt-1">Reason: <?=esc($row->cancel_reason)?></div>
                <?php endif; ?>
            </td>
            <td data-label="Action">
                <?php
                    $listCurrentUserId = (int)(function_exists('get_session') && isset(get_session()->id) ? get_session()->id : 0);
                    $listIsAdmin = function_exists('user_can') ? user_can('is_admin') : false;
                    $listIsOps = function_exists('user_can') ? user_can('is_operations_manager') : false;
                    $listIsAssignee = (int)($row->assigned_to ?? 0) === $listCurrentUserId;
                    $listIsCreator = (int)($row->creator_id ?? 0) === $listCurrentUserId;
                    $listStage = (int)($row->stage ?? 1);
                ?>
                <div class="service-actions">
                <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_agent_service/<?=$row->id ?? 0?>" class="btn btn-sm btn-outline-primary" title="View transaction" aria-label="View transaction"><i class="fas fa-eye"></i><span class="d-none d-xl-inline ms-1">View</span></a>
                <?php if ($listIsAdmin && !in_array($statusLower, ['cancelled', 'canceled', 'rejected', 'declined', 'denied'], true)): ?>
                <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_agent_service/<?=$row->id ?? 0?>#cancel-agent-service" class="btn btn-sm btn-outline-danger" title="Cancel transaction" aria-label="Cancel transaction">
                    <i class="fas fa-ban"></i><span class="d-none d-xl-inline ms-1">Cancel</span>
                </a>
                <?php endif; ?>
<?php
                    if ($listStage == 1 && $statusLower === 'pending' && ($listIsAdmin || $listIsOps || $listIsCreator)): ?>
                <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit_agent_service/<?=$row->id?>" class="btn btn-sm btn-outline-primary" title="Edit transaction" aria-label="Edit transaction">
                    <i class="fas fa-pencil"></i><span class="d-none d-xl-inline ms-1">Edit</span>
                </a>
                <?php elseif ($listStage == 2 && strpos($statusLower, 'pending') !== false && !in_array($statusLower, ['completed','rejected'])): 
                        if ($listIsAssignee): ?>
                <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/process_agent_service/<?=$row->id?>" class="btn btn-sm btn-outline-success" title="Process stage 2" aria-label="Process stage 2">
                    <i class="fas fa-gears"></i><span class="d-none d-xl-inline ms-1">Process</span>
                </a>
                <?php elseif ($listIsAdmin || $listIsOps || $listIsCreator): ?>
                <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit_agent_service/<?=$row->id?>" class="btn btn-sm btn-outline-primary" title="Edit transaction" aria-label="Edit transaction">
                    <i class="fas fa-pencil"></i><span class="d-none d-xl-inline ms-1">Edit</span>
                </a>
                <?php endif; ?>
                <?php elseif ($listStage == 3 && $listIsCreator && !in_array($statusLower, ['completed','rejected'])): ?>
                <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit_agent_service/<?=$row->id?>" class="btn btn-sm btn-outline-success" title="Complete transaction" aria-label="Complete transaction">
                    <i class="fas fa-check"></i><span class="d-none d-xl-inline ms-1">Complete</span>
                </a>
                <?php elseif ($listStage == 1 && $statusLower === 'pending' && ($listIsAdmin || $listIsCreator)): ?>
                <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_agent_service/<?=$row->id?>" class="btn btn-sm btn-outline-info" title="Assign transaction" aria-label="Assign transaction">
                    <i class="fas fa-user-plus"></i><span class="d-none d-xl-inline ms-1">Assign</span>
                </a>
                <?php endif; ?>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr>
        <td colspan="4" class="text-center text-muted py-4">
            <i class="fas fa-inbox fs-3 d-block mb-2 text-secondary"></i>
            No agent service requests found
        </td>
    </tr>
<?php endif; ?>

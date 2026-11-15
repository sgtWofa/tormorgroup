<?php if(!empty($rows)): $id = 0; ?>
    <tbody id="simListBody">
    <?php foreach($rows as $row): $id++; ?>
        <?php
            $isAssignedToCurrentUser = !empty($row->assigned)
                && !empty($user_id)
                && ((int)$row->assigned === (int)$user_id)
                && !in_array(strtolower((string)($row->transaction_status ?? '')), ['sold - retail','sold - wholesale'], true);
            $textClass = 'text-muted';
            switch (strtolower((string)($row->network ?? 'mtn'))) {
                case 'telecel': $textClass = 'text-danger'; break;
                case 'starlink': $textClass = 'text-success'; break;
                case 'mtn': $textClass = 'text-warning'; break;
                case 'at': $textClass = 'text-primary'; break;
            }
            $badgeClass = 'bg-secondary';
            switch (strtolower((string)($row->transaction_status ?? 'unassigned'))) {
                case 'expired':
                case 'declined':
                case 'rejected': $badgeClass = 'bg-danger'; break;
                case 'sold - retail':
                case 'sold - wholesale': $badgeClass = 'bg-success'; break;
                case 'unassigned': $badgeClass = 'bg-warning text-dark'; break;
                case 'assigned': $badgeClass = 'bg-primary'; break;
            }
            $status = strtolower((string)($row->transaction_status ?? ''));
        ?>
        <tr class="<?= $isAssignedToCurrentUser ? 'highlight-assigned-sim' : '' ?>" <?= $isAssignedToCurrentUser ? 'style="background-color: ' . htmlspecialchars($simHighlightColor, ENT_QUOTES, 'UTF-8') . ' !important;"' : '' ?>>
            <td class="text-center"><span class="badge rounded-pill bg-warning text-dark fs-6 px-2"><?= $id ?></span></td>
            <td><div class="fw-semibold text-body fs-6"><?= get_date($row->date_created ?? '') ?></div></td>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <div>
                        <i class="fa-solid fa-signal me-1 <?= $textClass ?>" data-app-image-viewer title="Click to view image"></i>
                        <a class="fw-bold text-decoration-none <?= $textClass ?> fs-6" href="<?= ROOT ?>/<?= $admin_route ?>/<?= $plugin_route ?>/view/<?= $row->id ?>"><?= strtoupper($row->network ?? '') ?></a>
                        <div class="table-text-muted font-monospace"><?= esc($row->number_assigned ?? 'Unassigned') ?></div>
                    </div>
                </div>
            </td>
            <td>
                <span class="badge <?= $badgeClass ?> text-uppercase px-2 py-1"><?= esc($row->transaction_status ?? '') ?></span>
                <?php if(in_array($status, ['declined','rejected'], true) && !empty($row->rejection_reason)): ?>
                    <div class="table-text-muted text-danger mt-1" style="white-space:normal;max-width:220px;"><i class="fa-solid fa-circle-exclamation me-1"></i><?= esc($row->rejection_reason) ?></div>
                <?php endif; ?>
            </td>
            <td>
                <div class="font-monospace fs-6"><span class="table-text-muted">PUK:</span> <?= esc($row->puk ?? 'N/A') ?></div>
                <div class="font-monospace fs-6"><span class="table-text-muted">S/N:</span> <?= esc($row->serial_number ?? 'N/A') ?></div>
            </td>
            <td class="text-end font-monospace">
                <div class="table-text-muted" title="Cost Price">Cost: GHS <?= number_format((float)($row->cost ?? 0), 2) ?></div>
                <div class="text-primary fw-semibold fs-6" title="Wholesale Price">WS: GHS <?= number_format((float)($row->wholesale_price ?? 0), 2) ?></div>
                <div class="fw-bold text-success fs-6" title="Retail Price">Ret: GHS <?= number_format((float)($row->retail_price ?? 0), 2) ?></div>
            </td>
            <td>
                <div class="d-flex align-items-center gap-2 mb-1" title="In Charge / Assigned Agent">
                    <img src="<?= get_image($row->assigned_image ?? '') ?>" class="sim-avatar-sm" alt="Assigned">
                    <span class="fw-semibold fs-6"><?= esc(trim(($row->assigned_first_name ?? '') . ' ' . ($row->assigned_last_name ?? ''))) ?: 'Unassigned' ?></span>
                </div>
                <div class="d-flex align-items-center gap-2" title="Created By">
                    <img src="<?= get_image($row->user_image ?? '') ?>" class="sim-avatar-sm" alt="Creator">
                    <span class="table-text-muted"><?= esc(trim(($row->user_first_name ?? '') . ' ' . ($row->user_last_name ?? ''))) ?></span>
                </div>
            </td>
            <td class="text-end">
                <div class="btn-group" role="group">
                <?php if(user_can('view_sim')):?>
                    <a href="<?= ROOT ?>/<?= $admin_route ?>/<?= $plugin_route ?>/view/<?= $row->id ?>" class="btn btn-outline-primary btn-sm px-2" title="View Details"><i class="fa-solid fa-eye fs-6"></i></a>
                <?php endif?>
                <?php if(user_can('edit_sim') && !in_array($status, ['declined','rejected','sold - retail','sold - wholesale'], true)):?>
                    <a href="<?= ROOT ?>/<?= $admin_route ?>/<?= $plugin_route ?>/edit/<?= $row->id ?>" class="btn btn-outline-warning btn-sm px-2" title="Edit SIM"><i class="fa-solid fa-pen-to-square fs-6"></i></a>
                <?php endif?>
                <?php if(user_can('delete_sim') && !in_array($status, ['sold - retail','sold - wholesale'], true)):?>
                    <button type="button" class="btn btn-outline-danger btn-sm px-2 sim-delete-button" data-sim-id="<?= $row->id ?>" title="Delete Record"><i class="fa-solid fa-trash fs-6"></i></button>
                <?php endif?>
                </div>
            </td>
        </tr>
    <?php endforeach?>
    </tbody>
<?php else: ?>
    <tbody id="simListBody">
        <tr>
            <td colspan="8" class="text-center py-4 text-muted fs-5"><i class="fa-solid fa-inbox fs-2 d-block mb-2"></i> No records found!</td>
        </tr>
    </tbody>
<?php endif?>

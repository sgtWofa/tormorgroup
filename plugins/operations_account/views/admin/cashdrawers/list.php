<?php if(user_can('view_wallet')):?>
	<?php $opsCashdrawerIsAjax = !empty($is_ajax_cashdrawers); ?>
	<?php if (!$opsCashdrawerIsAjax): ?>
	<link rel="stylesheet" type="text/css" href="<?=plugin_http_path('assets/css/style.css')?>">
	<link rel="stylesheet" type="text/css" href="<?=ROOT?>/plugins/basic-admin/assets/css/select2.min.css">
	<style>
		.select2-container--default .select2-selection--multiple {
			background-color: #1b1f2a !important;
			border-color: #3b4154 !important;
			color: #f8fafc !important;
		}
		.select2-container--default .select2-selection--multiple .select2-search__field {
			color: #f8fafc !important;
			background-color: transparent !important;
		}
		.select2-container--default .select2-selection--multiple .select2-selection__choice {
			background-color: #2c3150 !important;
			border: 1px solid #475069 !important;
			color: #f8fafc !important;
		}
		.select2-container--default .select2-results__option {
			background-color: #1b1f2a !important;
			color: #f8fafc !important;
		}
		.select2-container--default .select2-results__option--highlighted[aria-selected],
		.select2-container--default .select2-results__option--highlighted {
			background-color: #2c3150 !important;
			color: #f8fafc !important;
		}
		.select2-container--default .select2-dropdown {
			background-color: #1b1f2a !important;
			border-color: #3b4154 !important;
		}
		.select2-container--default .select2-search--dropdown .select2-search__field {
			background-color: #1b1f2a !important;
			color: #f8fafc !important;
		}
		.select2-container--default .select2-selection__choice__remove {
			color: #f8fafc !important;
		}
		.ops-kpi-card--ghs { background: linear-gradient(135deg, rgba(16,185,129,0.18), rgba(15,23,42,0.95)); border: 1px solid rgba(16,185,129,0.35); }
		.ops-kpi-card--cfa { background: linear-gradient(135deg, rgba(234,179,8,0.18), rgba(15,23,42,0.95)); border: 1px solid rgba(234,179,8,0.35); }
		.ops-kpi-card--evd { background: linear-gradient(135deg, rgba(139,92,246,0.18), rgba(15,23,42,0.95)); border: 1px solid rgba(139,92,246,0.35); }
		.ops-kpi-card--ecash { background: linear-gradient(135deg, rgba(56,189,248,0.18), rgba(15,23,42,0.95)); border: 1px solid rgba(56,189,248,0.35); }
		.ops-kpi-card--bank { background: linear-gradient(135deg, rgba(249,115,22,0.18), rgba(15,23,42,0.95)); border: 1px solid rgba(249,115,22,0.35); }
		.ops-kpi-card--ghs .ops-kpi-icon,
		.ops-kpi-card--cfa .ops-kpi-icon,
		.ops-kpi-card--evd .ops-kpi-icon,
		.ops-kpi-card--ecash .ops-kpi-icon,
		.ops-kpi-card--bank .ops-kpi-icon {
			color: #f8fafc;
		}
	</style>

<div id="opsCashdrawersRegion" data-ajax-url="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/ajax_cashdrawers">
	<?php endif; ?>
	<div class="ops-cashdrawer-fragment">
    <?php
        $opsCashTotal   = isset($cashdrawers_total) ? (float)$cashdrawers_total : 0.0;
        $opsCfaTotal    = isset($cashdrawers_cfa_total) ? (float)$cashdrawers_cfa_total : 0.0;
        $opsEvdTotal    = isset($cashdrawers_evd_total) ? (float)$cashdrawers_evd_total : 0.0;
        $opsEcashTotal  = isset($cashdrawers_ecash_total) ? (float)$cashdrawers_ecash_total : 0.0;
        $opsBankTotal   = isset($cashdrawers_bank_total) ? (float)$cashdrawers_bank_total : 0.0;
        $opsDrawerCount = !empty($cashdrawers) && is_array($cashdrawers) ? count($cashdrawers) : 0;
        $opsUsers = [];
        try {
            $opsUsersDb = new \Core\Database;
            $opsUsers = $opsUsersDb->query("SELECT id, first_name, last_name FROM users WHERE deleted = 0 ORDER BY first_name ASC, last_name ASC") ?: [];
        } catch (\Throwable $e) {
            $opsUsers = [];
        }
        $opsFormatCashdrawerMeta = static function($row) {
            $updatedBy = trim(($row->updated_by_first_name ?? '') . ' ' . ($row->updated_by_last_name ?? ''));
            $hasUpdatedDate = !empty($row->date_updated) && $row->date_updated !== '0000-00-00 00:00:00';

            return [
                'created' => !empty($row->date_created) ? get_date($row->date_created) : '--',
                'has_updated' => $hasUpdatedDate,
                'updated' => $hasUpdatedDate ? get_date_time($row->date_updated) : '',
                'updated_by' => ($hasUpdatedDate && $updatedBy !== '') ? $updatedBy : '',
            ];
        };
    ?>
    <div class="ops-kpi-grid mb-3">
        <div class="ops-kpi-card ops-kpi-card--ghs">
            <i class="fa-solid fa-wallet ops-kpi-icon" aria-hidden="true"></i>
            <div class="ops-kpi-label">TOTAL GHS</div>
            <div class="ops-kpi-value" id="opsCashdrawerTotalValue"><?=number_format($opsCashTotal, 2)?></div>
            <div class="ops-kpi-sub">Filtered drawer GHS totals</div>
        </div>
        <div class="ops-kpi-card ops-kpi-card--cfa">
            <i class="fa-solid fa-coins ops-kpi-icon" aria-hidden="true"></i>
            <div class="ops-kpi-label">TOTAL CFA</div>
            <div class="ops-kpi-value" id="opsCashdrawerCfaValue"><?=number_format($opsCfaTotal, 2)?></div>
            <div class="ops-kpi-sub">Filtered drawer CFA totals</div>
        </div>
        <div class="ops-kpi-card ops-kpi-card--evd">
            <i class="fa-solid fa-sim-card ops-kpi-icon" aria-hidden="true"></i>
            <div class="ops-kpi-label">TOTAL EVD</div>
            <div class="ops-kpi-value" id="opsCashdrawerEvdValue"><?=number_format($opsEvdTotal, 2)?></div>
            <div class="ops-kpi-sub">Filtered EVD channel totals</div>
        </div>
        <div class="ops-kpi-card ops-kpi-card--ecash">
            <i class="fa-solid fa-mobile ops-kpi-icon" aria-hidden="true"></i>
            <div class="ops-kpi-label">TOTAL ECASH</div>
            <div class="ops-kpi-value" id="opsCashdrawerEcashValue"><?=number_format($opsEcashTotal, 2)?></div>
            <div class="ops-kpi-sub">Filtered e-cash totals</div>
        </div>
        <div class="ops-kpi-card ops-kpi-card--bank">
            <i class="fa-solid fa-university ops-kpi-icon" aria-hidden="true"></i>
            <div class="ops-kpi-label">TOTAL BANK</div>
            <div class="ops-kpi-value" id="opsCashdrawerBankValue"><?=number_format($opsBankTotal, 2)?></div>
            <div class="ops-kpi-sub">Filtered bank channel totals</div>
        </div>
    </div>

    <div class="row my-3 mx-auto align-items-end ops-cashdrawer-toolbar">
        <div class="col-md-3 mb-2 ops-cashdrawer-filter">
            <label class="form-label small text-muted">From date</label>
            <input type="date" class="form-control form-control-sm" data-role="ops-date-from" value="<?=htmlspecialchars($_GET['date_from'] ?? '')?>">
        </div>
        <div class="col-md-3 mb-2 ops-cashdrawer-filter">
            <label class="form-label small text-muted">To date</label>
            <input type="date" class="form-control form-control-sm" data-role="ops-date-to" value="<?=htmlspecialchars($_GET['date_to'] ?? '')?>">
        </div>
        <div class="col-md-3 mb-2 ops-cashdrawer-filter ops-cashdrawer-search">
            <label class="form-label small text-muted" for="opsUserFilter">User</label>
            <?php
                $selectedUserIds = [];
                $rawUserIds = $_GET['user_id'] ?? [];
                if (!is_array($rawUserIds)) {
                    $rawUserIds = explode(',', (string)$rawUserIds);
                }
                foreach ($rawUserIds as $idValue) {
                    $idValue = trim((string)$idValue);
                    if ($idValue !== '' && is_numeric($idValue)) {
                        $selectedUserIds[] = (int)$idValue;
                    }
                }
                $selectedUserIds = array_unique($selectedUserIds);
            ?>
            <select id="opsUserFilter" name="user_id[]" class="form-select form-select-sm ops-user-filter" data-role="ops-user-filter" data-placeholder="Search users" multiple>
                <?php foreach ($opsUsers as $opsUser): ?>
                    <?php $opsUserName = trim((string)(($opsUser->first_name ?? '') . ' ' . ($opsUser->last_name ?? ''))); ?>
                    <?php $opsUserLabel = $opsUserName !== '' ? $opsUserName : ('User #' . (int)($opsUser->id ?? 0)); ?>
                    <option value="<?= (int)($opsUser->id ?? 0) ?>" <?= in_array((int)($opsUser->id ?? 0), $selectedUserIds, true) ? 'selected' : '' ?>><?= htmlspecialchars($opsUserLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 mb-2 d-flex justify-content-end gap-2 align-items-end ops-cashdrawer-actions">
            <?php if(user_can('add_wallet')):?>
            <a style="text-decoration: none;" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/add_cashdrawer">
                <button class="btn btn-warning btn-sm">
                    <i class="fa-solid fa-plus"></i> New Cashdrawer
                </button>
            </a>
            <?php endif?>
            <select class="form-select form-select-sm w-auto ms-2" data-role="ops-page-size">
                <?php $pageLimit = isset($pager) ? (int)$pager->limit : 10; ?>
                <option value="5"  <?=$pageLimit == 5  ? 'selected' : ''?>>5</option>
                <option value="10" <?=$pageLimit == 10 ? 'selected' : ''?>>10</option>
                <option value="25" <?=$pageLimit == 25 ? 'selected' : ''?>>25</option>
                <option value="50" <?=$pageLimit == 50 ? 'selected' : ''?>>50</option>
            </select>
        </div>
    </div>

    <div class="table-responsive ops-cashdrawer-table-wrap" style="width:100%; max-width:100%;">
        <table class="table table-striped table-hover ops-cashdrawer-table" style="width:100%; min-width:100%;">
            <thead>
                <tr>
                    <th class="bg-secondary text-warning">#</th>
                    <th class="bg-secondary text-warning">ID</th>
                    <th class="bg-secondary text-warning d-none d-lg-table-cell">Agent</th>
                    <th class="bg-secondary text-warning">Balances</th>
                    <th class="bg-secondary text-warning ops-cashdrawer-date-col d-none d-lg-table-cell">Date</th>
                    <th class="bg-secondary text-warning"></th>
                </tr>
            </thead>
            <tbody>
            <?php if(!empty($cashdrawers)): $id = 0; ?>
                <?php foreach($cashdrawers as $row): $id ++;?>
                    <?php $opsMeta = $opsFormatCashdrawerMeta($row); ?>
                    <tr>
                        <td><span class="badge bg-warning text-black rounded-circle pill"><?=$id?></span></td>
                        <td>
                            <a style="text-decoration: none;" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_cashdrawer/<?=$row->id?>"><?=$row->id?>
                            </a>
                            <div class="ops-cashdrawer-mobile-meta d-lg-none">
                                <div>
                                    <strong>Agent:</strong>
                                    <img src="<?=get_image($row->assigned_image)?>" style="width: 22px; height: 22px; object-fit: cover; border-radius: 50%;">
                                    <?=esc(trim((string)(($row->assigned_first_name ?? '') . ' ' . ($row->assigned_last_name ?? ''))))?>
                                </div>
                                <div class="text-muted"><strong>Created:</strong> <?=$opsMeta['created']?></div>
                                <?php if ($opsMeta['has_updated']): ?>
                                    <div class="text-muted"><strong>Updated:</strong> <?=$opsMeta['updated']?></div>
                                    <?php if ($opsMeta['updated_by'] !== ''): ?>
                                        <div class="text-muted"><strong>By:</strong> <?=htmlspecialchars($opsMeta['updated_by'])?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="d-none d-lg-table-cell">
                            <span><img src="<?=get_image($row->assigned_image)?>" style="width: 22px; height: 22px; object-fit: cover; border-radius: 50%;"></span>
                             <?=$row->assigned_first_name . ' ' . $row->assigned_last_name ?>
                        </td>
                        <td>
                            <div style="line-height:1.25;">
                                <div><strong>GHS</strong> <?=number_format((float)($row->ghs_balance ?? $row->amount ?? 0), 2)?></div>
                                <?php if (isset($row->cfa_balance)): ?>
                                    <div class="text-secondary small"><strong>CFA</strong> <?=number_format((float)$row->cfa_balance, 2)?></div>
                                <?php endif; ?>
                                <?php if (isset($row->ecash_balance)): ?>
                                    <div class="text-muted small"><strong>E-Cash</strong> <?=number_format((float)$row->ecash_balance, 2)?></div>
                                <?php endif; ?>
                                <?php if (isset($row->bank_balance)): ?>
                                    <div class="text-primary small"><strong>Bank</strong> <?=number_format((float)$row->bank_balance, 2)?></div>
                                <?php endif; ?>
                                <?php if(!empty($row->has_repair_issue)): ?>
                                    <?php
                                        $repairIssueTitles = [];
                                        foreach(($row->repair_issues ?? []) as $repairIssue){
                                            $issueText = trim((string)($repairIssue['label'] ?? 'Repair issue'));
                                            $issueDetails = trim((string)($repairIssue['details'] ?? ''));
                                            if($issueDetails !== ''){
                                                $issueText .= ': ' . $issueDetails;
                                            }
                                            $repairIssueTitles[] = $issueText;
                                        }
                                        $repairIssueTitle = implode("\n", $repairIssueTitles);
                                    ?>
                                    <div class="small mt-1">
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle" title="<?=esc($repairIssueTitle)?>">
                                            <?=$row->repair_issue_count?> repair issue<?=$row->repair_issue_count == 1 ? '' : 's'?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="ops-cashdrawer-date-col d-none d-lg-table-cell">
                            <div style="line-height: 1.25; min-width: 220px;">
                                <div><strong>Created:</strong> <?=$opsMeta['created']?></div>
                                <?php if ($opsMeta['has_updated']): ?>
                                    <div class="small text-muted"><strong>Updated:</strong> <?=$opsMeta['updated']?></div>
                                    <?php if ($opsMeta['updated_by'] !== ''): ?>
                                        <div class="small text-muted"><strong>By:</strong> <?=htmlspecialchars($opsMeta['updated_by'])?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-warning dropdown-toggle" type="button" id="dropdownMenuButton<?=$id?>" data-bs-toggle="dropdown" aria-expanded="false" style="border: none;">
                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?=$id?>">
                                    <?php if(user_can('view_wallet') || $user_id === $row->id):?>
                                        <li>
                                            <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_cashdrawer/<?=$row->id?>">
                                               <i class="fa-solid fa-briefcase text-warning"></i> View Drawer
                                            </a>
                                        </li>
                                    <?php endif?>
                                    <?php if(user_can('view_wallet') || $user_id === $row->id):?>
                                        <li>
                                            <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export_cashdrawer_pdf/<?=$row->id?>" target="_blank">
                                               <i class="fa fa-file-pdf text-danger"></i> Export PDF
                                            </a>
                                        </li>
                                    <?php endif?>
                                    <?php if(user_can("edit_wallet") || $user_id === $row->user_id):?>
                                        <li>
                                            <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit_cashdrawer/<?=$row->id?>">
                                                <i class="fa fa-pen text-warning"></i> <?=!empty($row->has_repair_issue) ? 'Repair Cashdrawer' : 'Edit Cashdrawer'?>
                                            </a>
                                        </li>
                                    <?php endif;?>
                                    <?php if(user_can("delete_wallet") || $user_id === $row->id):?>
                                        <li>
                                            <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/delete_cashdrawer/<?=$row->id?>">
                                                <i class="fa fa-trash text-warning"></i> <?=!empty($row->has_repair_issue) ? 'Delete / Rebuild' : 'Delete'?>
                                            </a>
                                        </li>
                                    <?php endif;?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach?>
            <?php else:?>
                <tr><td colspan="7" class="text-center justify-content-center">No records to show!</td></tr>
            <?php endif?>
            </tbody>
            <tfoot>         
                <tr>
                    <th class="bg-secondary text-warning"></th>
                    <th colspan="2" class="bg-secondary text-warning">GHS TOTAL</th>
                    <th class="bg-secondary text-warning"><?=number_format($cashdrawers_total ?? 0, 2)?></th>
                    <th class="bg-secondary text-warning"></th>
                    <th class="bg-secondary text-warning"></th>
                </tr>
            </tfoot>
        </table>

        <?=$pager->display()?>
    </div>
	</div>
	<?php if (!$opsCashdrawerIsAjax): ?>
</div>
	<?php endif; ?>
<?php else:?>
    <div class="alert alert-danger text-center">
        Access denied. You dont have permission for this action
    </div>
<?php endif?>

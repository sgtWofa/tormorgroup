<?php if(user_can('view_roles')):?>

<form method="post">

    <?=csrf()?>
    <?php
        $perms = array_values(array_unique(APP('permissions')));
        sort($perms);
        $permGroups = [];
        foreach($perms as $perm){
            $groupKey = $perm === 'all' ? 'general' : strtolower(explode('_', (string)$perm, 2)[0] ?? 'general');
            if($groupKey === '') $groupKey = 'general';
            $permGroups[$groupKey][] = $perm;
        }
        ksort($permGroups);
    ?>

    <style>
        .perm-sticky-tools{position:sticky;top:0;z-index:10;background:var(--bs-body-bg);border:1px solid var(--bs-border-color);border-radius:.75rem;box-shadow:0 .5rem 1.25rem rgba(0,0,0,.06)}
        .perm-dirty-dot{width:.5rem;height:.5rem;border-radius:999px;display:inline-block;background:var(--bs-warning)}
    </style>

    <div class="card border-0 shadow-sm mb-3 perm-sticky-tools">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted mb-1">Role</label>
                    <select class="form-select form-select-sm" id="permRoleFilter">
                        <option value="">All roles</option>
                        <?php if(!empty($rows)) foreach($rows as $r): ?>
                            <option value="<?=esc($r->id)?>"><?=esc($r->role)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-5">
                    <label class="form-label small text-muted mb-1">Search permissions</label>
                    <input type="text" class="form-control form-control-sm" id="permSearch" placeholder="Type to filter permissions...">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted mb-1">Group</label>
                    <select class="form-select form-select-sm" id="permGroupFilter">
                        <option value="">All groups</option>
                        <?php foreach(array_keys($permGroups) as $g): ?>
                            <option value="<?=esc($g)?>"><?=esc(ucwords(str_replace('_',' ', $g)))?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-1">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" value="1" id="permSelectedOnly">
                        <label class="form-check-label small" for="permSelectedOnly">Selected</label>
                    </div>
                </div>

                <div class="col-12">
                    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mt-2">
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="permExpandAll">Expand all</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="permCollapseAll">Collapse all</button>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="permSelectVisible">Select visible</button>
                            <button type="button" class="btn btn-outline-danger btn-sm" id="permClearVisible">Clear visible</button>
                        </div>
                        <div class="small text-muted" id="permDirtySummary" style="display:none;">
                            <span class="perm-dirty-dot me-1"></span>
                            Unsaved changes: <span id="permDirtyCount">0</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped">
            <tr>
                <th>#</th>
                <th>Role</th>
                <th>Active</th>
                <th>
                    <?php if(user_can('edit_permissions')):?>
                        <div class="d-flex justify-content-between">
                            Permissions
                            <button class="btn btn-warning btn-sm">
                                <i class="fa-solid fa-save"></i> Save Permissions
                            </button>
                        </div>
                    <?php endif;?>
                </th>
                <th>
                    <?php if(user_can('add_role')):?>
                    <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/add">
                        <button type="button" class="btn btn-secondary btn-sm">
                            <i class="fa-solid fa-plus"></i> Add New
                        </button>
                    </a>
                    <?php endif?>
                </th>
            </tr>

            <?php if(!empty($rows)):?>
                <?php foreach($rows as $row):?>
                    <tr data-role-id="<?=$row->id?>">
                        <td><?=$row->id?></td>
                        <td>
                            <?=esc($row->role)?>
                        </td>
                        <td>
                            <?=esc($row->disabled ? 'No':'Yes')?>
                        </td>

                        <td style="max-width:500px">
                            <div id="role-<?=$row->id?>" class="bo-perm-role" data-role-name="<?=esc(strtolower((string)$row->role))?>">
                                <?php if(!empty($permGroups)):$num = 0?>
                                    <div class="accordion" id="permAccRole<?=$row->id?>">
                                        <?php foreach($permGroups as $groupKey => $groupPerms):
                                            $groupTitle = ucwords(str_replace('_',' ', (string)$groupKey));
                                            $collapseId = 'permCollapse_'.$row->id.'_'.$groupKey;
                                        ?>
                                            <div class="accordion-item">
                                                <h2 class="accordion-header" id="permHeading<?=$row->id?>_<?=$groupKey?>">
                                                    <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#<?=$collapseId?>" aria-expanded="false" aria-controls="<?=$collapseId?>">
                                                        <span class="me-2 fw-semibold"><?=esc($groupTitle)?></span>
                                                        <span class="badge bg-secondary-subtle text-secondary" data-group-count></span>
                                                    </button>
                                                </h2>
                                                <div id="<?=$collapseId?>" class="accordion-collapse collapse" aria-labelledby="permHeading<?=$row->id?>_<?=$groupKey?>" data-bs-parent="#permAccRole<?=$row->id?>">
                                                    <div class="accordion-body py-2">
                                                        <div class="row g-2">
                                                            <?php foreach($groupPerms as $perm):$num++;
                                                                $label = esc(str_replace('_', ' ', (string)$perm));
                                                                $isChecked = in_array($perm, $row->permissions ?? []);
                                                            ?>
                                                                <div class="form-check col-lg-4 col-md-6 perm-item" data-group="<?=esc($groupKey)?>" data-perm="<?=esc($perm)?>" data-search="<?=esc(strtolower((string)$perm.' '.$label.' '.$groupTitle))?>" data-checked="<?=$isChecked ? '1':'0'?>">
                                                                    <input <?=$isChecked ? ' checked ':''?> name="checkbox_<?=$row->id?>_<?=$num?>" class="form-check-input permission-checkbox" type="checkbox" value="<?=$perm?>" id="checkbox_<?=$row->id?>_<?=$num?>">
                                                                    <label class="form-check-label" for="checkbox_<?=$row->id?>_<?=$num?>" style="cursor:pointer;">
                                                                        <?=$label?>
                                                                    </label>
                                                                </div>
                                                            <?php endforeach?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach?>
                                    </div>
                                <?php endif?>
                            </div>
                        </td>
                        <td>
 
                            <?php if(user_can('edit_role')):?>
                            <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$row->id?>">
                                <button type="button" class="btn btn-outline-warning btn-sm" title="Edit" style="border:none;">
                                    <i class="fa-solid fa-pencil fs-5"></i>
                                </button>
                            </a>
                            <?php endif?>

                            <?php if(user_can('delete_role')):?>
                            <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/delete/<?=$row->id?>">
                                <button type="button" class="btn btn-outline-danger btn-sm" title="Delete" style="border:none;">
                                    <i class="fa-solid fa-trash fs-5"></i>
                                </button>
                            </a>
                            <?php endif?>
                        </td>
                    </tr>
                <?php endforeach?>
            <?php endif?>
        </table>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roleRows = document.querySelectorAll('[id^="role-"]');
    const roleFilter = document.getElementById('permRoleFilter');
    const permSearch = document.getElementById('permSearch');
    const groupFilter = document.getElementById('permGroupFilter');
    const selectedOnly = document.getElementById('permSelectedOnly');
    const expandAllBtn = document.getElementById('permExpandAll');
    const collapseAllBtn = document.getElementById('permCollapseAll');
    const selectVisibleBtn = document.getElementById('permSelectVisible');
    const clearVisibleBtn = document.getElementById('permClearVisible');
    const dirtySummary = document.getElementById('permDirtySummary');
    const dirtyCountEl = document.getElementById('permDirtyCount');

    const setAccordionState = (collapseEl, expand) => {
        try{
            if (window.bootstrap && window.bootstrap.Collapse) {
                const inst = window.bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false });
                expand ? inst.show() : inst.hide();
                return;
            }
        }catch(e){}
        collapseEl.classList.toggle('show', !!expand);
    };

    const updateDirtySummary = () => {
        const dirtyRoles = document.querySelectorAll('tr[data-role-id].perm-dirty');
        const count = dirtyRoles ? dirtyRoles.length : 0;
        if (dirtyCountEl) dirtyCountEl.textContent = String(count);
        if (dirtySummary) dirtySummary.style.display = count ? '' : 'none';
    };

    roleRows.forEach(roleRow => {
        const checkboxes = roleRow.querySelectorAll('.permission-checkbox');
        const groupAccordions = roleRow.querySelectorAll('.accordion-item');

        checkboxes.forEach(cb => {
            cb.dataset.initialChecked = cb.checked ? '1' : '0';
        });

        const disableOtherCheckboxes = () => {
            const isChecked = Array.from(checkboxes).some(box => box.checked && box.value === 'all');
            checkboxes.forEach(box => {
                if (!isChecked || box.value === 'all') {
                    box.disabled = false;
                } else {
                    box.disabled = true;
                }
            });
        };

        const updateGroupCounts = () => {
            groupAccordions.forEach(item => {
                const countEl = item.querySelector('[data-group-count]');
                const permItems = item.querySelectorAll('.perm-item');
                let visibleCount = 0;
                let checkedCount = 0;
                permItems.forEach(p => {
                    const isHidden = p.classList.contains('d-none');
                    if (!isHidden) visibleCount++;
                    const cb = p.querySelector('input.permission-checkbox');
                    if (cb && cb.checked) checkedCount++;
                });
                if (countEl) countEl.textContent = checkedCount + '/' + visibleCount;
            });
        };

        const updateRoleDirtyState = () => {
            const tr = roleRow.closest('tr[data-role-id]');
            if (!tr) return;
            const dirty = Array.from(checkboxes).some(cb => (cb.checked ? '1':'0') !== (cb.dataset.initialChecked || '0'));
            tr.classList.toggle('perm-dirty', dirty);
            updateDirtySummary();
        };

        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function(){
                disableOtherCheckboxes();
                updateGroupCounts();
                updateRoleDirtyState();
            });
        });

        disableOtherCheckboxes();
        updateGroupCounts();
        updateRoleDirtyState();
    });

    const applyFilters = () => {
        const roleId = (roleFilter && roleFilter.value) ? roleFilter.value.trim() : '';
        const term = (permSearch && permSearch.value) ? permSearch.value.trim().toLowerCase() : '';
        const group = (groupFilter && groupFilter.value) ? groupFilter.value.trim().toLowerCase() : '';
        const onlySelected = !!(selectedOnly && selectedOnly.checked);

        document.querySelectorAll('tr[data-role-id]').forEach(tr => {
            if (!roleId || tr.getAttribute('data-role-id') === roleId) {
                tr.classList.remove('d-none');
            } else {
                tr.classList.add('d-none');
            }
        });

        document.querySelectorAll('.bo-perm-role').forEach(roleBlock => {
            const tr = roleBlock.closest('tr[data-role-id]');
            if (tr && tr.classList.contains('d-none')) return;

            roleBlock.querySelectorAll('.perm-item').forEach(item => {
                const searchHay = (item.getAttribute('data-search') || '').toLowerCase();
                const itemGroup = (item.getAttribute('data-group') || '').toLowerCase();
                const cb = item.querySelector('input.permission-checkbox');
                const isChecked = !!(cb && cb.checked);

                let show = true;
                if (term && !searchHay.includes(term)) show = false;
                if (group && itemGroup !== group) show = false;
                if (onlySelected && !isChecked) show = false;

                item.classList.toggle('d-none', !show);
            });

            roleBlock.querySelectorAll('.accordion-item').forEach(accItem => {
                const anyVisible = !!accItem.querySelector('.perm-item:not(.d-none)');
                accItem.classList.toggle('d-none', !anyVisible);

                const collapseEl = accItem.querySelector('.accordion-collapse');
                if (collapseEl && term) {
                    setAccordionState(collapseEl, anyVisible);
                }
            });

            const groupAccordions = roleBlock.querySelectorAll('.accordion-item');
            groupAccordions.forEach(item => {
                const countEl = item.querySelector('[data-group-count]');
                const permItems = item.querySelectorAll('.perm-item');
                let visibleCount = 0;
                let checkedCount = 0;
                permItems.forEach(p => {
                    const isHidden = p.classList.contains('d-none');
                    if (!isHidden) visibleCount++;
                    const cb = p.querySelector('input.permission-checkbox');
                    if (cb && cb.checked) checkedCount++;
                });
                if (countEl) countEl.textContent = checkedCount + '/' + visibleCount;
            });
        });
    };

    const bulkSetVisible = (checked) => {
        document.querySelectorAll('tr[data-role-id]').forEach(tr => {
            if (tr.classList.contains('d-none')) return;
            tr.querySelectorAll('.perm-item:not(.d-none) input.permission-checkbox').forEach(cb => {
                if (cb.disabled) return;
                cb.checked = !!checked;
                cb.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    };

    if (expandAllBtn) expandAllBtn.addEventListener('click', function(){
        document.querySelectorAll('tr[data-role-id]').forEach(tr => {
            if (tr.classList.contains('d-none')) return;
            tr.querySelectorAll('.accordion-collapse').forEach(c => setAccordionState(c, true));
        });
    });
    if (collapseAllBtn) collapseAllBtn.addEventListener('click', function(){
        document.querySelectorAll('tr[data-role-id]').forEach(tr => {
            if (tr.classList.contains('d-none')) return;
            tr.querySelectorAll('.accordion-collapse').forEach(c => setAccordionState(c, false));
        });
    });
    if (selectVisibleBtn) selectVisibleBtn.addEventListener('click', function(){ bulkSetVisible(true); });
    if (clearVisibleBtn) clearVisibleBtn.addEventListener('click', function(){ bulkSetVisible(false); });

    if (roleFilter) roleFilter.addEventListener('change', applyFilters);
    if (groupFilter) groupFilter.addEventListener('change', applyFilters);
    if (selectedOnly) selectedOnly.addEventListener('change', applyFilters);
    if (permSearch) permSearch.addEventListener('input', applyFilters);
    applyFilters();
    updateDirtySummary();
});
</script>

<?php else:?>
    <div class="alert alert-danger text-center">
        Access denied. You dont have permission for this action
    </div>
<?php endif?>

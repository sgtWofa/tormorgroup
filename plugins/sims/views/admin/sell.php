<?php if(user_can('sell_sim')): ?>
<?php
$networkOptions = [
    '' => 'All networks',
    'mtn' => 'MTN',
    'at' => 'AT',
    'telecel' => 'Telecel',
];

$sims = is_array($sims ?? null) ? $sims : [];
$simCount = count($sims);
$bulkSellUrl = ROOT . '/' . $admin_route . '/' . $plugin_route . '/bulk-sell';
?>

<style>
    :root {
        --bulk-shell-bg: var(--bs-body-bg, #f8fafc);
        --bulk-card-bg: var(--bs-card-bg, #ffffff);
        --bulk-card-border: var(--bs-border-color, #edf2f7);
        --bulk-surface-muted: #f1f5f9;
        --bulk-text-muted: var(--bs-secondary-color, #64748b);
        --bulk-text-primary: var(--bs-body-color, #0f172a);
        --bulk-chip-bg: var(--bs-tertiary-bg, #ffffff);
        --bulk-chip-border: var(--bs-border-color, #e5e7eb);
        --bulk-sale-outline: var(--bs-border-color, #e2e8f0);
        --bulk-table-row: var(--bs-body-bg, #ffffff);
    }

    [data-bs-theme="dark"] {
        --bulk-shell-bg: #0b1120;
        --bulk-card-bg: #111827;
        --bulk-surface-muted: #1f2937;
        --bulk-card-border: rgba(255,255,255,0.08);
        --bulk-text-muted: rgba(248,250,252,0.65);
        --bulk-text-primary: #f8fafc;
        --bulk-chip-bg: rgba(255,255,255,0.06);
        --bulk-chip-border: rgba(255,255,255,0.15);
        --bulk-sale-outline: rgba(255,255,255,0.25);
        --bulk-table-row: rgba(15,23,42,0.65);
    }

    .bulk-shell{background:var(--bulk-shell-bg);min-height:calc(100vh - 140px);padding-bottom:2rem;} 
    .bulk-card{background:var(--bulk-card-bg);border-radius:24px;border:1px solid var(--bulk-card-border);box-shadow:0 30px 80px rgba(15,23,42,.12);} 
    .sale-type-btn{border-radius:999px;padding:6px 16px;border:1px solid var(--bulk-sale-outline);background:var(--bulk-card-bg);font-weight:600;color:var(--bulk-text-primary);transition:.2s;} 
    .sale-type-btn.active{background:linear-gradient(120deg,#ef4444,#f97316);color:#fff;border-color:transparent;box-shadow:0 10px 30px rgba(248,113,113,.35);} 
    .sim-row{background:var(--bulk-table-row);} 
    .sim-row.row-selected{background:rgba(239,68,68,.12);box-shadow:inset 0 0 0 2px rgba(239,68,68,.25);} 
    .sim-row td{background:transparent;color:var(--bulk-text-primary);} 
    .table thead, .table thead th{background:var(--bulk-surface-muted);color:var(--bulk-text-primary);border-color:var(--bulk-sale-outline);} 
    .bulk-summary{position:sticky;top:90px;} 
    .selection-chip{border-radius:999px;border:1px solid var(--bulk-chip-border);padding:2px 10px;background:var(--bulk-chip-bg);font-size:.85rem;display:inline-flex;align-items:center;gap:.35rem;color:var(--bulk-text-primary);} 
    .selection-chip button{border:none;background:transparent;color:#ef4444;line-height:1;} 
    .metric-label{text-transform:uppercase;font-size:.75rem;color:var(--bulk-text-muted);letter-spacing:.08em;} 
    .metric-value{font-size:1.4rem;font-weight:700;color:var(--bulk-text-primary);} 
    #bulkFeedback .alert{border-radius:14px;} 
    #simInventoryTable.sale-type-retail .price-wholesale,
    #simInventoryTable.sale-type-wholesale .price-retail{opacity:.35;} 
    .bulk-filters .form-control,
    .bulk-filters .form-select{height:48px;border-radius:14px;background:var(--bulk-surface-muted);color:var(--bulk-text-primary);border-color:var(--bulk-sale-outline);} 
    .bulk-filters .form-control:focus,
    .bulk-filters .form-select:focus{box-shadow:0 0 0 .2rem rgba(239,68,68,0.15);border-color:#f97316;color:var(--bulk-text-primary);background:var(--bulk-surface-muted);} 
    .bulk-input-addon{background:var(--bulk-surface-muted);border-color:var(--bulk-sale-outline);color:var(--bulk-text-primary);} 
    .bulk-input-field{background:var(--bulk-surface-muted);color:var(--bulk-text-primary);border-color:var(--bulk-sale-outline);} 
    .bulk-input-field:focus{background:var(--bulk-surface-muted);color:var(--bulk-text-primary);} 
    #inventoryCard .badge{background:var(--bulk-surface-muted);color:var(--bulk-text-primary);} 
    @media(max-width:991px){
        .bulk-summary{position:static;margin-top:1rem;}
    }
    @media(max-width:768px){
        .bulk-card{border-radius:18px;}
        .bulk-filters{flex-direction:column;}
        .bulk-filters > div{width:100%;}
        #simInventoryTable{font-size:.9rem;}
        #inventoryCard .table-responsive{overflow-x:auto;}
    }
</style>

<div class="bulk-shell py-4">
    <div class="container-fluid px-3 px-md-4">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <span class="rounded-circle bg-danger bg-opacity-10 p-3 text-danger">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2l-2 7v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-2-7z"></path><line x1="3" y1="9" x2="21" y2="9"></line><path d="M9 9V4a3 3 0 0 1 6 0v5"></path></svg>
                </span>
                <div>
                    <h2 class="h4 mb-1 fw-bold">Bulk SIM sale</h2>
                    <p class="text-muted mb-0">Pick multiple SIMs, personalize buyer details, enter cash once, and sync the drawer instantly.</p>
                </div>
            </div>
            <div class="text-lg-end">
                <div class="badge bg-light text-dark border mb-2">Available: <span id="availableCount"><?=$simCount?></span></div>
                <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>" class="btn btn-outline-secondary btn-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="me-1"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    Back to SIMs
                </a>
            </div>
        </div>

        <?php if($simCount === 0): ?>
            <div class="bulk-card p-5 text-center">
                <h4 class="fw-semibold">No SIMs ready for sale</h4>
                <p class="text-muted">Add or assign new SIMs to yourself, then return to bulk sell them.</p>
                <a class="btn btn-danger" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">Go to inventory</a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="bulk-card p-3 p-md-4" id="inventoryCard">
                        <div class="row g-2 mb-3 bulk-filters">
                            <div class="col-md-6">
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text border-end-0 bulk-input-addon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></span>
                                    <input class="form-control border-start-0 bulk-input-field" id="simSearch" placeholder="Search serial / buyer / assigned">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select id="networkFilter" class="form-select bulk-input-field">
                                    <?php foreach($networkOptions as $value => $label): ?>
                                        <option value="<?=$value?>"><?=$label?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 text-md-end">
                                <button type="button" class="btn btn-link text-decoration-none" id="clearFiltersBtn">Reset filters</button>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted">Tick SIMs to include in this batch.</small>
                            <button type="button" class="btn btn-link btn-sm d-none" id="clearSelectionBtn">Clear selection</button>
                        </div>
                        <div id="selectionChips" class="d-flex flex-wrap gap-2 mb-3">
                            <span class="text-muted small">No SIM selected yet.</span>
                        </div>
                        <div class="table-responsive rounded-4" style="background:var(--bulk-card-bg);">
                            <table class="table align-middle" id="simInventoryTable" data-sale-type="retail">
                                <thead>
                                    <tr>
                                        <th style="width:40px"><input class="form-check-input" type="checkbox" id="selectAllSims"></th>
                                        <th>Serial / Network</th>
                                        <th>Status</th>
                                        <th class="text-end">Cost</th>
                                        <th class="text-end">Retail</th>
                                        <th class="text-end">Wholesale</th>
                                        <th>Assigned number</th>
                                    </tr>
                                </thead>
                                <tbody id="simsBody">
                                    <?php foreach($sims as $sim): ?>
                                        <?php
                                            $rowData = [
                                                'serial' => (string)($sim->serial_number ?? ''),
                                                'network' => strtolower($sim->network ?? ''),
                                                'status' => (string)($sim->transaction_status ?? 'Unassigned'),
                                                'cost' => (float)($sim->cost ?? 0),
                                                'retail' => (float)($sim->retail_price ?? 0),
                                                'wholesale' => (float)($sim->wholesale_price ?? 0),
                                                'number_assigned' => (string)($sim->number_assigned ?? ''),
                                                'buyer_phone' => (string)($sim->buyer_phone ?? ''),
                                            ];
                                        ?>
                                        <tr class="sim-row" data-sim-id="<?=$sim->id?>" data-row='<?=htmlspecialchars(json_encode($rowData),ENT_QUOTES,'UTF-8')?>'>
                                            <td><input class="form-check-input js-row-select" type="checkbox"></td>
                                            <td>
                                                <div class="fw-semibold"><?=htmlspecialchars($sim->serial_number ?? '')?></div>
                                                <small class="text-uppercase text-muted fw-semibold"><?=htmlspecialchars($sim->network ?? '')?></small>
                                            </td>
                                            <td><span class="badge bg-light text-dark border"><?=htmlspecialchars($sim->transaction_status ?? 'Unassigned')?></span></td>
                                            <td class="text-end text-muted">GH₵ <?=number_format((float)($sim->cost ?? 0),2)?></td>
                                            <td class="text-end price-retail">GH₵ <?=number_format((float)($sim->retail_price ?? 0),2)?></td>
                                            <td class="text-end price-wholesale">GH₵ <?=number_format((float)($sim->wholesale_price ?? 0),2)?></td>
                                            <td><input type="text" class="form-control form-control-sm js-number-assigned" placeholder="0XXXXXXXXX" value="<?=htmlspecialchars($sim->number_assigned ?? '')?>"></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div id="emptyFilterState" class="text-center py-4 d-none">
                            <p class="fw-semibold mb-1">No SIMs match your filters.</p>
                            <p class="text-muted mb-0">Clear the search or network filter to view everything.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="bulk-card p-4 bulk-summary">
                        <form id="bulkSellForm" onsubmit="return false;">
                            <?=csrf('csrf_token')?>
                            <input type="hidden" id="saleTypeInput" value="Sold - retail">
                            <input type="hidden" id="denosInput" value="">
                            <input type="hidden" id="changeDenosInput" value="">
                            <input type="hidden" id="changeRequiredInput" value="0">
                            <input type="hidden" id="cashReceivedAmountInput" value="0">
                            <input type="hidden" id="calculatorTotalInput" value="0">
                            <input type="hidden" id="assignmentsInput" value="{}">
                            <input type="hidden" id="selectedIdsInput" value="[]">

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Sale type</label>
                                <div class="d-flex gap-2">
                                    <button type="button" class="sale-type-btn active" data-sale="Sold - retail">Retail</button>
                                    <button type="button" class="sale-type-btn" data-sale="Sold - wholesale">Wholesale</button>
                                </div>
                            </div>

                            <div class="rounded-4 p-3 mb-3" style="background:var(--bulk-surface-muted);">
                                <div class="d-flex justify-content-between mb-2">
                                    <div>
                                        <div class="metric-label">Selected SIMs</div>
                                        <div class="metric-value" id="selectedCount">0</div>
                                    </div>
                                    <div>
                                        <div class="metric-label">Sale total (GH₵)</div>
                                        <div class="metric-value" id="saleTotal">0.00</div>
                                    </div>
                                </div>
                                <div class="text-muted small" id="commissionHint">Commission updates as you select SIMs.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Cash calculator</label>
                                <div class="input-group" id="calculatorTrigger" data-bs-toggle="modal" data-bs-target="#denoModal">
                                    <span class="input-group-text bulk-input-addon"><i class="fa-solid fa-calculator text-danger"></i></span>
                                    <input type="text" class="form-control bulk-input-field" id="calculatorSummary" placeholder="Tap to capture cash" readonly>
                                </div>
                                <small class="text-muted" id="calculatorWarning">Required before confirming sale.</small>
                                <small class="text-danger d-none" id="calculatorMismatch">Calculator total must match sale total or be balanced with change.</small>
                                <div class="alert alert-info mt-2 d-none" id="bulkChangeSummary">
                                    <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                        <div>
                                            <div class="fw-semibold" id="bulkChangeText">Excess cash detected: GH₵ 0.00</div>
                                            <small class="text-muted">Select change denominations to balance the sale.</small>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-primary" id="openChangeModalBtn">Select change</button>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Buyer phone</label>
                                <div class="input-group">
                                    <span class="input-group-text bulk-input-addon"><i class="fa-solid fa-phone"></i></span>
                                    <input type="text" class="form-control bulk-input-field" id="buyerPhoneGlobal" placeholder="Enter buyer phone once">
                                </div>
                                <small class="text-muted">Applied to every SIM in this bulk sale.</small>
                                <small class="text-danger d-none" id="buyerPhoneError"></small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Cash drawer note</label>
                                <textarea id="bulkNote" class="form-control bulk-input-field" rows="2" placeholder="Optional context for finance..."></textarea>
                            </div>

                            <div id="bulkFeedback"></div>

                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-danger" id="submitBulkSale" disabled>
                                    <i class="fa-solid fa-check me-2"></i>Confirm bulk sale
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="resetBulkSale">Reset</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="changeModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Select change denominations</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body table-responsive">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th>NOTES</th>
                            <th>COUNT</th>
                            <th>VOLUME</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            $denominations = [200,100,50,20,10,5,2,1,'coins',2,1,0.5,0.2,0.1];
                            $labels = [
                                '200' => 'GH₵200',
                                '100' => 'GH₵100',
                                '50'  => 'GH₵50',
                                '20'  => 'GH₵20',
                                '10'  => 'GH₵10',
                                '5'   => 'GH₵5',
                                '2'   => 'GH₵2',
                                '1'   => 'GH₵1',
                                '0.5' => '50p',
                                '0.2' => '20p',
                                '0.1' => '10p',
                            ];
                        ?>
                        <?php foreach($denominations as $deno): ?>
                            <?php if($deno === 'coins'): ?>
                                <tr><td colspan="3" class="fw-semibold text-muted">COINS</td></tr>
                                <?php continue; ?>
                            <?php endif; ?>
                            <?php $denoKey = is_float($deno) ? rtrim(rtrim(number_format($deno, 2, '.', ''), '0'), '.') : (string)$deno; ?>
                            <tr data-denomination="<?=$deno?>">
                                <td class="px-5"><?=$labels[$denoKey] ?? ('GH₵' . $deno)?></td>
                                <td><input type="number" class="form-control p-1 change-count-input" min="0" placeholder="0"></td>
                                <td><input type="text" class="form-control p-1 change-volume-input" placeholder="Volume" readonly></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th class="px-5 bg-secondary text-warning">CHANGE TOTAL</th>
                            <th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="change-total-count" style="border:none;" readonly></th>
                            <th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="change-total-volume" style="border:none;" readonly></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" id="changeReset">Reset</button>
                <button type="button" class="btn btn-primary" id="changeApply">Confirm change</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="changeModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Select change denominations</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body table-responsive">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th>NOTES</th>
                            <th>COUNT</th>
                            <th>VOLUME</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            $denominations = [200,100,50,20,10,5,2,1,'coins',2,1,0.5,0.2,0.1];
                            $labels = [
                                '200' => 'GH₵200',
                                '100' => 'GH₵100',
                                '50'  => 'GH₵50',
                                '20'  => 'GH₵20',
                                '10'  => 'GH₵10',
                                '5'   => 'GH₵5',
                                '2'   => 'GH₵2',
                                '1'   => 'GH₵1',
                                '0.5' => '50p',
                                '0.2' => '20p',
                                '0.1' => '10p',
                            ];
                        ?>
                        <?php foreach($denominations as $deno): ?>
                            <?php if($deno === 'coins'): ?>
                                <tr><td colspan="3" class="fw-semibold text-muted">COINS</td></tr>
                                <?php continue; ?>
                            <?php endif; ?>
                            <?php $denoKey = is_float($deno) ? rtrim(rtrim(number_format($deno, 2, '.', ''), '0'), '.') : (string)$deno; ?>
                            <tr data-denomination="<?=$deno?>">
                                <td class="px-5"><?=$labels[$denoKey] ?? ('GH₵' . $deno)?></td>
                                <td><input type="number" class="form-control p-1 change-count-input" min="0" placeholder="0"></td>
                                <td><input type="text" class="form-control p-1 change-volume-input" placeholder="Volume" readonly></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th class="px-5 bg-secondary text-warning">CHANGE TOTAL</th>
                            <th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="change-total-count" style="border:none;" readonly></th>
                            <th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="change-total-volume" style="border:none;" readonly></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" id="changeReset">Reset</button>
                <button type="button" class="btn btn-primary" id="changeApply">Confirm change</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="denoModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Currency notes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body table-responsive">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th>NOTES</th>
                            <th>COUNT</th>
                            <th>VOLUME</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            $denominations = [200,100,50,20,10,5,2,1,'coins',2,1,0.5,0.2,0.1];
                            $labels = [
                                '200' => 'GH&cent;200',
                                '100' => 'GH&cent;100',
                                '50'  => 'GH&cent;50',
                                '20'  => 'GH&cent;20',
                                '10'  => 'GH&cent;10',
                                '5'   => 'GH&cent;5',
                                '2'   => 'GH&cent;2',
                                '1'   => 'GH&cent;1',
                                '0.5' => '50p',
                                '0.2' => '20p',
                                '0.1' => '10p',
                            ];
                        ?>
                        <?php foreach($denominations as $deno): ?>
                            <?php if($deno === 'coins'): ?>
                                <tr><td colspan="3" class="fw-semibold text-muted">COINS</td></tr>
                                <?php continue; ?>
                            <?php endif; ?>
                            <?php $denoKey = is_float($deno) ? rtrim(rtrim(number_format($deno, 2, '.', ''), '0'), '.') : (string)$deno; ?>
                            <tr data-denomination="<?=$deno?>">
                                <td class="px-5"><?=$labels[$denoKey] ?? ('GH&cent;' . $deno)?></td>
                                <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
                                <td><input type="text" class="form-control p-1 volume-input" placeholder="Volume" readonly></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th class="px-5 bg-secondary text-warning">TOTAL</th>
                            <th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="total-count" style="border:none;" readonly></th>
                            <th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="total-volume" style="border:none;" readonly></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" id="denoReset">Reset</button>
                <button type="button" class="btn btn-warning" id="denoRestore">Restore</button>
                <button type="button" class="btn btn-primary" id="denoApply">Confirm</button>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
    <div class="alert alert-danger text-center">
        Access denied. You don’t have permission for this action.
    </div>
<?php endif ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const bulkSellUrl = '<?=$bulkSellUrl?>';
    const simsWrapper = document.getElementById('simsBody');
    const searchInput = document.getElementById('simSearch');
    const networkFilter = document.getElementById('networkFilter');
    const clearFiltersBtn = document.getElementById('clearFiltersBtn');
    const selectionChips = document.getElementById('selectionChips');
    const clearSelectionBtn = document.getElementById('clearSelectionBtn');
    const selectAllCheckbox = document.getElementById('selectAllSims');
    const simRows = Array.from(document.querySelectorAll('.sim-row'));
    const saleTypeButtons = document.querySelectorAll('.sale-type-btn');
    const saleTypeInput = document.getElementById('saleTypeInput');
    const selectedCountEl = document.getElementById('selectedCount');
    const saleTotalEl = document.getElementById('saleTotal');
    const bulkFeedback = document.getElementById('bulkFeedback');
    bulkFeedback.dataset.persist = 'false';
    const submitBtn = document.getElementById('submitBulkSale');
    const resetBtn = document.getElementById('resetBulkSale');
    const denosInput = document.getElementById('denosInput');
    const calculatorSummary = document.getElementById('calculatorSummary');
    const calculatorWarning = document.getElementById('calculatorWarning');
    const calculatorMismatch = document.getElementById('calculatorMismatch');
    const selectedIdsInput = document.getElementById('selectedIdsInput');
    const assignmentsInput = document.getElementById('assignmentsInput');
    const buyerPhoneInput = document.getElementById('buyerPhoneGlobal');
    const buyerPhoneError = document.getElementById('buyerPhoneError');
    const changeDenosInput = document.getElementById('changeDenosInput');
    const changeRequiredInput = document.getElementById('changeRequiredInput');
    const cashReceivedAmountInput = document.getElementById('cashReceivedAmountInput');
    const calculatorTotalInput = document.getElementById('calculatorTotalInput');
    const bulkChangeSummary = document.getElementById('bulkChangeSummary');
    const bulkChangeText = document.getElementById('bulkChangeText');
    const openChangeModalBtn = document.getElementById('openChangeModalBtn');
    const csrfInput = document.querySelector('#bulkSellForm input[name="csrf_token"]');
    const csrfToken = csrfInput ? csrfInput.value : '';
    const bulkNote = document.getElementById('bulkNote');
    const availableCount = document.getElementById('availableCount');
    const emptyFilterState = document.getElementById('emptyFilterState');
    const tableEl = document.getElementById('simInventoryTable');

    const selectedMap = new Map();
    const buyerPhonePattern = /^(0\d{9}|\+233\d{9})$/;

    const getRowData = (row) => {
        try {
            return JSON.parse(row.dataset.row || '{}');
        } catch (e) {
            return {};
        }
    };

    const formatCurrency = (value) => new Intl.NumberFormat('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));

    function updateSelectionState() {
        selectedCountEl.textContent = selectedMap.size;
        selectedIdsInput.value = JSON.stringify(Array.from(selectedMap.keys()));

        if (bulkFeedback.dataset.persist === 'true' && selectedMap.size > 0) {
            bulkFeedback.dataset.persist = 'false';
        }

        if (selectedMap.size === 0) {
            selectionChips.innerHTML = '<span class="text-muted small">No SIM selected yet.</span>';
            clearSelectionBtn.classList.add('d-none');
        } else {
            clearSelectionBtn.classList.remove('d-none');
            selectionChips.innerHTML = '';
            selectedMap.forEach((info, id) => {
                const chip = document.createElement('span');
                chip.className = 'selection-chip';
                chip.innerHTML = `<strong>${info.serial}</strong><button type="button" data-id="${id}">×</button>`;
                chip.querySelector('button').addEventListener('click', () => toggleRowSelection(id, false));
                selectionChips.appendChild(chip);
            });
        }

        let saleTotal = 0;
        const currentSaleType = saleTypeInput.value;
        let hasMissingPrice = false;

        selectedMap.forEach((info) => {
            const price = currentSaleType === 'Sold - retail' ? Number(info.retail) : Number(info.wholesale);
            if (price <= 0) {
                hasMissingPrice = true;
            }
            saleTotal += price;
        });

        saleTotalEl.textContent = formatCurrency(saleTotal);
        const buyerPhoneValue = buyerPhoneInput.value.trim();
        let buyerPhoneValid = true;
        if (buyerPhoneValue && !buyerPhonePattern.test(buyerPhoneValue)) {
            buyerPhoneValid = false;
            buyerPhoneError.textContent = 'Enter a valid Ghana phone (0XXXXXXXXX or +233XXXXXXXXX).';
            buyerPhoneError.classList.remove('d-none');
        } else {
            buyerPhoneError.classList.add('d-none');
        }

        const assignments = {};
        selectedMap.forEach((info, id) => {
            assignments[id] = {
                number_assigned: info.number_assigned || '',
                buyer_phone: buyerPhoneValue,
            };
        });
        assignmentsInput.value = JSON.stringify(assignments);

        const denomCaptured = !!denosInput.value;
        const calculatorTotal = parseFloat(calculatorTotalInput.value) || 0;
        const changeRequired = getRequiredChange();
        const changeTotal = getChangeTotal();
        const changeValid = changeRequired <= 0 || Math.abs(changeTotal - changeRequired) < 0.01;
        let totalsAligned = true;
        if (selectedMap.size > 0 && denomCaptured) {
            totalsAligned = Math.abs(calculatorTotal - saleTotal) < 0.01 || (changeRequired > 0 && changeValid);
        }

        if (calculatorMismatch) {
            if (!totalsAligned && denomCaptured && selectedMap.size > 0) {
                calculatorMismatch.classList.remove('d-none');
                if (changeRequired > 0) {
                    calculatorMismatch.textContent = `Excess cash of GH₵ ${formatCurrency(changeRequired)} requires matching change denominations.`;
                } else {
                    calculatorMismatch.textContent = `Calculator total (GH₵ ${formatCurrency(calculatorTotal)}) must match sale total (GH₵ ${formatCurrency(saleTotal)}).`;
                }
            } else {
                calculatorMismatch.classList.add('d-none');
            }
        }

        calculatorWarning.classList.toggle('text-danger', !denomCaptured);
        calculatorWarning.textContent = denomCaptured ? 'Calculator is synced for this sale.' : 'Required before confirming sale.';

        changeRequiredInput.value = changeRequired.toFixed(2);
        cashReceivedAmountInput.value = calculatorTotal.toFixed(2);
        updateChangeSummary();

        const canSubmit = selectedMap.size > 0 && denomCaptured && !hasMissingPrice && buyerPhoneValid && totalsAligned;
        submitBtn.disabled = !canSubmit;

        if (hasMissingPrice) {
            renderFeedback('danger', 'One or more SIMs are missing a valid retail/wholesale price. Please edit them first.');
        } else if (!denomCaptured && selectedMap.size > 0) {
            renderFeedback('info', 'Capture the cash denominations to continue.');
        } else if (!buyerPhoneValid && buyerPhoneValue) {
            renderFeedback('danger', 'Enter a valid buyer phone before confirming.');
        } else if (!totalsAligned && selectedMap.size > 0) {
            renderFeedback('danger', calculatorMismatch.textContent);
        } else {
            clearFeedback();
        }
    }

    const clearFeedback = (force = false) => {
        if (!force && bulkFeedback.dataset.persist === 'true') {
            return;
        }
        bulkFeedback.dataset.persist = 'false';
        bulkFeedback.innerHTML = '';
    };

    function renderFeedback(type, message, persist = false) {
        bulkFeedback.dataset.persist = persist ? 'true' : 'false';
        bulkFeedback.innerHTML = `<div class="alert alert-${type} py-2 px-3 mb-3">${message}</div>`;
    }

    function toggleRowSelection(id, checked) {
        const row = simRows.find(r => Number(r.dataset.simId) === Number(id));
        if (!row) return;
        const checkbox = row.querySelector('.js-row-select');
        checkbox.checked = checked;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
    }

    simsWrapper.addEventListener('change', (event) => {
        const checkbox = event.target.closest('.js-row-select');
        if (!checkbox) return;
        const row = checkbox.closest('.sim-row');
        const simId = Number(row.dataset.simId);
        const rowData = getRowData(row);

        row.classList.toggle('row-selected', checkbox.checked);

        const numberInput = row.querySelector('.js-number-assigned');
        const payload = {
            serial: rowData.serial || '',
            retail: rowData.retail || 0,
            wholesale: rowData.wholesale || 0,
            number_assigned: numberInput.value.trim(),
        };

        if (checkbox.checked) {
            selectedMap.set(simId, payload);
        } else {
            selectedMap.delete(simId);
        }
        updateSelectionState();
    });

    simsWrapper.addEventListener('input', (event) => {
        const row = event.target.closest('.sim-row');
        if (!row) return;
        const simId = Number(row.dataset.simId);
        if (!selectedMap.has(simId)) return;

        const current = selectedMap.get(simId);
        if (event.target.classList.contains('js-number-assigned')) {
            current.number_assigned = event.target.value.trim();
        }
        selectedMap.set(simId, current);
        updateSelectionState();
    });

    saleTypeButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            saleTypeButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            saleTypeInput.value = btn.dataset.sale;
            tableEl.dataset.saleType = btn.dataset.sale === 'Sold - retail' ? 'retail' : 'wholesale';
            updateSelectionState();
        });
    });

    const applyFilters = () => {
        const needle = searchInput.value.trim().toLowerCase();
        const network = networkFilter.value;
        let visibleCount = 0;

        simRows.forEach(row => {
            const rowData = getRowData(row);
            const matchesSearch = !needle || `${rowData.serial} ${rowData.number_assigned} ${rowData.buyer_phone}`.toLowerCase().includes(needle);
            const matchesNetwork = !network || rowData.network === network;
            const visible = matchesSearch && matchesNetwork;
            row.classList.toggle('d-none', !visible);
            if (visible) visibleCount += 1;
        });

        emptyFilterState.classList.toggle('d-none', visibleCount !== 0);
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = false;
    };

    searchInput.addEventListener('input', applyFilters);
    networkFilter.addEventListener('change', applyFilters);
    clearFiltersBtn.addEventListener('click', () => {
        searchInput.value = '';
        networkFilter.value = '';
        applyFilters();
    });

    selectAllCheckbox.addEventListener('change', () => {
        const visibleRows = simRows.filter(row => !row.classList.contains('d-none'));
        visibleRows.forEach(row => {
            const checkbox = row.querySelector('.js-row-select');
            checkbox.checked = selectAllCheckbox.checked;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });

    clearSelectionBtn.addEventListener('click', () => {
        selectedMap.clear();
        simRows.forEach(row => {
            row.classList.remove('row-selected');
            row.querySelector('.js-row-select').checked = false;
        });
        selectAllCheckbox.checked = false;
        updateSelectionState();
    });

    resetBtn.addEventListener('click', () => {
        bulkNote.value = '';
        denosInput.value = '';
        changeDenosInput.value = '';
        changeRequiredInput.value = '0';
        cashReceivedAmountInput.value = '0';
        calculatorSummary.value = '';
        calculatorTotalInput.value = '0';
        buyerPhoneInput.value = '';
        buyerPhoneError.classList.add('d-none');
        selectedMap.clear();
        simRows.forEach(row => {
            row.classList.remove('row-selected');
            row.querySelector('.js-row-select').checked = false;
        });
        selectAllCheckbox.checked = false;
        saleTypeButtons.forEach(b => b.classList.remove('active'));
        saleTypeButtons[0].classList.add('active');
        saleTypeInput.value = 'Sold - retail';
        tableEl.dataset.saleType = 'retail';
        clearFeedback(true);
        updateSelectionState();
    });

    const changeModal = document.getElementById('changeModal');
    const denoModal = document.getElementById('denoModal');
    const countInputs = denoModal.querySelectorAll('.count-input');
    const totalCountInput = document.getElementById('total-count');
    const totalVolumeInput = document.getElementById('total-volume');
    const denoResetBtn = document.getElementById('denoReset');
    const denoRestoreBtn = document.getElementById('denoRestore');
    const denoApplyBtn = document.getElementById('denoApply');
    const changeCountInputs = document.querySelectorAll('.change-count-input');
    const changeTotalCountInput = document.getElementById('change-total-count');
    const changeTotalVolumeInput = document.getElementById('change-total-volume');
    const changeResetBtn = document.getElementById('changeReset');
    const changeApplyBtn = document.getElementById('changeApply');
    let calculatorModalInstance = null;
    let calculatorEventsBound = false;
    let changeModalInstance = null;
    let changeEventsBound = false;

    const getCalculatorModal = () => {
        if (!calculatorModalInstance && window.bootstrap) {
            calculatorModalInstance = window.bootstrap.Modal.getOrCreateInstance(denoModal);
        }
        return calculatorModalInstance;
    };

    const getChangeModal = () => {
        if (!changeModalInstance && window.bootstrap) {
            changeModalInstance = window.bootstrap.Modal.getOrCreateInstance(changeModal);
        }
        return changeModalInstance;
    };

    const parseAmount = (value) => Number(String(value || '').replace(/,/g, '')) || 0;
    const roundMoney = (value) => Math.round((Number(value) + Number.EPSILON) * 100) / 100;
    const getSaleTotalFromUI = () => parseAmount(saleTotalEl.textContent);
    const getChangeSnapshot = () => {
        try {
            return JSON.parse(changeDenosInput.value || '[]');
        } catch (e) {
            return [];
        }
    };
    const getChangeTotal = () => {
        return roundMoney(getChangeSnapshot().reduce((sum, row) => sum + (Number(row.volume) || 0), 0));
    };
    const getRequiredChange = () => {
        const total = parseFloat(calculatorTotalInput.value) || 0;
        const sale = getSaleTotalFromUI();
        return roundMoney(Math.max(0, total - sale));
    };
    const updateChangeSummary = () => {
        const required = getRequiredChange();
        const changeTotal = getChangeTotal();
        const hasExcess = required > 0;
        bulkChangeText.textContent = `Excess cash detected: GH₵ ${formatCurrency(required)}`;
        bulkChangeSummary.classList.toggle('d-none', !hasExcess);
        if (hasExcess && changeTotal > 0) {
            bulkChangeText.textContent = `Excess cash GH₵ ${formatCurrency(required)}; change set GH₵ ${formatCurrency(changeTotal)}`;
        }
    };
    const recalcTotals = () => {
        let count = 0;
        let volume = 0;
        const snapshot = [];
        countInputs.forEach((input) => {
            const row = input.closest('tr');
            const denom = parseFloat(row.dataset.denomination);
            const qty = parseFloat(input.value) || 0;
            const vol = denom * qty;
            const volInput = row.querySelector('.volume-input');
            if (volInput) {
                volInput.value = qty ? vol.toFixed(2) : '';
            }
            if (qty > 0) {
                snapshot.push({ denomination: denom, count: qty, volume: vol, currency: 'GHS' });
            }
            count += qty;
            volume += vol;
        });
        totalCountInput.value = count || '';
        totalVolumeInput.value = volume ? volume.toFixed(2) : '';
        const cached = {
            selectedIds: Array.from(selectedMap.keys()),
            saleTotal: getSaleTotalFromUI(),
            snapshot,
        };
        localStorage.setItem('sim_bulk_denos', JSON.stringify(cached));
        return { snapshot, count, volume };
    };

    const restoreCalculatorSnapshot = () => {
        if (!denosInput.value) {
            countInputs.forEach((input) => {
                input.value = '';
                const volInput = input.closest('tr').querySelector('.volume-input');
                if (volInput) volInput.value = '';
            });
            totalCountInput.value = '';
            totalVolumeInput.value = '';
            calculatorTotalInput.value = '0';
            calculatorSummary.value = '';
            localStorage.removeItem('sim_bulk_denos');
            return;
        }

        let saved = [];
        try {
            const cache = localStorage.getItem('sim_bulk_denos');
            if (cache) {
                const parsed = JSON.parse(cache);
                const currentIds = Array.from(selectedMap.keys()).map(Number);
                const cachedIds = Array.isArray(parsed.selectedIds) ? parsed.selectedIds.map(Number) : [];
                const sameSelection = currentIds.length === cachedIds.length && currentIds.every((id, index) => id === cachedIds[index]);
                const sameSaleTotal = parseFloat(parsed.saleTotal) === getSaleTotalFromUI();
                saved = sameSelection && sameSaleTotal && Array.isArray(parsed.snapshot) ? parsed.snapshot : [];
            }
        } catch (error) {
            saved = [];
        }
        if (!Array.isArray(saved) || !saved.length) {
            countInputs.forEach((input) => {
                input.value = '';
                const volInput = input.closest('tr').querySelector('.volume-input');
                if (volInput) volInput.value = '';
            });
            totalCountInput.value = '';
            totalVolumeInput.value = '';
            return;
        }
        countInputs.forEach(input => {
            const row = input.closest('tr');
            const denom = parseFloat(row.dataset.denomination);
            const savedRow = saved.find(p => Number(p.denomination) === denom);
            input.value = savedRow ? savedRow.count : '';
        });
        recalcTotals();
    };

    const bindCalculatorEvents = () => {
        if (calculatorEventsBound) return;
        calculatorEventsBound = true;

        countInputs.forEach((input) => {
            input.addEventListener('input', () => {
                recalcTotals();
            });
        });

        denoResetBtn.addEventListener('click', () => {
            countInputs.forEach((input) => {
                input.value = '';
                const volInput = input.closest('tr').querySelector('.volume-input');
                if (volInput) volInput.value = '';
            });
            totalCountInput.value = '';
            totalVolumeInput.value = '';
            localStorage.removeItem('sim_bulk_denos');
            changeDenosInput.value = '';
            changeRequiredInput.value = '0';
            cashReceivedAmountInput.value = '0';
            updateChangeSummary();
        });

        denoRestoreBtn.addEventListener('click', () => {
            restoreCalculatorSnapshot();
        });

        denoApplyBtn.addEventListener('click', () => {
            const { snapshot, volume } = recalcTotals();
            denosInput.value = JSON.stringify(snapshot);
            calculatorTotalInput.value = volume.toFixed(2);
            calculatorSummary.value = volume ? `GH₵ ${formatCurrency(volume)}` : '';
            cashReceivedAmountInput.value = volume.toFixed(2);
            clearFeedback();
            const modal = getCalculatorModal();
            if (modal) modal.hide();
            updateSelectionState();
            const required = getRequiredChange();
            if (required > 0 && !changeDenosInput.value) {
                handleChangeOpen();
            }
        });
    };

    const restoreChangeSnapshot = () => {
        let saved = [];
        try {
            const cache = changeDenosInput.value || '[]';
            saved = cache ? JSON.parse(cache) : [];
        } catch (error) {
            saved = [];
        }
        changeCountInputs.forEach((input) => {
            const row = input.closest('tr');
            const denom = parseFloat(row.dataset.denomination);
            const savedRow = saved.find(p => Number(p.denomination) === denom);
            input.value = savedRow ? savedRow.count : '';
            const volInput = row.querySelector('.change-volume-input');
            if (volInput) {
                const qty = parseFloat(input.value) || 0;
                volInput.value = qty ? (denom * qty).toFixed(2) : '';
            }
        });
        let changeCount = 0;
        let changeVolume = 0;
        saved.forEach(item => {
            changeCount += Number(item.count) || 0;
            changeVolume += Number(item.volume) || 0;
        });
        changeTotalCountInput.value = changeCount || '';
        changeTotalVolumeInput.value = changeVolume ? changeVolume.toFixed(2) : '';
        updateChangeSummary();
    };

    const bindChangeEvents = () => {
        if (changeEventsBound) return;
        changeEventsBound = true;

        changeCountInputs.forEach((input) => {
            input.addEventListener('input', () => {
                let totalCount = 0;
                let totalVolume = 0;
                changeCountInputs.forEach((countInput) => {
                    const row = countInput.closest('tr');
                    const denom = parseFloat(row.dataset.denomination);
                    const qty = parseFloat(countInput.value) || 0;
                    const vol = denom * qty;
                    const volInput = row.querySelector('.change-volume-input');
                    if (volInput) {
                        volInput.value = qty ? vol.toFixed(2) : '';
                    }
                    totalCount += qty;
                    totalVolume += vol;
                });
                changeTotalCountInput.value = totalCount || '';
                changeTotalVolumeInput.value = totalVolume ? totalVolume.toFixed(2) : '';
                updateChangeSummary();
            });
        });

        changeResetBtn.addEventListener('click', () => {
            changeCountInputs.forEach((input) => {
                input.value = '';
                const volInput = input.closest('tr').querySelector('.change-volume-input');
                if (volInput) volInput.value = '';
            });
            changeTotalCountInput.value = '';
            changeTotalVolumeInput.value = '';
            changeDenosInput.value = '';
            changeRequiredInput.value = '0';
            updateChangeSummary();
        });

        changeApplyBtn.addEventListener('click', () => {
            const snapshot = [];
            let totalVolume = 0;
            changeCountInputs.forEach((input) => {
                const row = input.closest('tr');
                const denom = parseFloat(row.dataset.denomination);
                const qty = parseFloat(input.value) || 0;
                if (qty > 0) {
                    const volume = roundMoney(denom * qty);
                    snapshot.push({ denomination: denom, count: qty, volume, currency: 'GHS' });
                    totalVolume += volume;
                }
            });
            changeDenosInput.value = JSON.stringify(snapshot);
            changeRequiredInput.value = getRequiredChange().toFixed(2);
            cashReceivedAmountInput.value = parseFloat(calculatorTotalInput.value || '0').toFixed(2);
            updateChangeSummary();
            const modal = getChangeModal();
            if (modal) modal.hide();
            updateSelectionState();
        });
    };

    const handleCalculatorOpen = () => {
        bindCalculatorEvents();
        restoreCalculatorSnapshot();
        const modal = getCalculatorModal();
        if (modal) {
            modal.show();
        }
    };

    const handleChangeOpen = () => {
        bindChangeEvents();
        restoreChangeSnapshot();
        const modal = getChangeModal();
        if (modal) {
            modal.show();
        }
    };

    document.getElementById('calculatorTrigger').addEventListener('click', handleCalculatorOpen);
    openChangeModalBtn.addEventListener('click', handleChangeOpen);
    buyerPhoneInput.addEventListener('input', updateSelectionState);

    submitBtn.addEventListener('click', async () => {
        if (submitBtn.disabled) return;
        const payload = {
            __ajax: 1,
            csrf_token: csrfToken,
            sale_type: saleTypeInput.value,
            selected_ids: Array.from(selectedMap.keys()),
            assignments: JSON.parse(assignmentsInput.value || '{}'),
            denos: denosInput.value,
            change_denominations: changeDenosInput.value,
            change_required: changeRequiredInput.value,
            cash_received_amount: cashReceivedAmountInput.value,
            note: bulkNote.value.trim(),
            buyer_phone: buyerPhoneInput.value.trim(),
        };

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
        bulkFeedback.innerHTML = '';

        try {
            const response = await fetch(bulkSellUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload),
            });

            const data = await response.json();
            if (!data.success) {
                renderFeedback('danger', data.message || 'Unable to complete bulk sale. Fix highlighted issues and try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-check me-2"></i>Confirm bulk sale';
                return;
            }

            renderFeedback('success', `${data.sold} SIM(s) sold successfully. Drawer synced with batch ${data.batch_code}.`, true);
            data.updated_sims.forEach(row => {
                const tableRow = simRows.find(tr => Number(tr.dataset.simId) === Number(row.id));
                if (tableRow) tableRow.remove();
            });
            availableCount.textContent = document.querySelectorAll('.sim-row').length;
            selectedMap.clear();
            denosInput.value = '';
            changeDenosInput.value = '';
            changeRequiredInput.value = '0';
            cashReceivedAmountInput.value = '0';
            calculatorSummary.value = '';
            calculatorTotalInput.value = '0';
            bulkNote.value = '';
            submitBtn.innerHTML = '<i class="fa-solid fa-check me-2"></i>Confirm bulk sale';
            updateSelectionState();
            applyFilters();
        } catch (error) {
            renderFeedback('danger', 'Network error. Please check your connection and retry.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-check me-2"></i>Confirm bulk sale';
        }
    });

    applyFilters();
    updateSelectionState();
});
</script>

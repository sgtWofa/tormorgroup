<?php if(user_can('view_backoffice')): ?>
 	<?php
 		$__old_post = get_value('old_post');
 		if (is_array($__old_post) && !empty($__old_post)) {
 			$_POST = $__old_post;
 			set_value('old_post', []);
 		}
 	?>
    <style>
        form {
            margin-top: 40px !important;
        }
        .form-control, .form-select {
            border-top: none !important;
            border-left: 0.5px solid gray !important;
            border-right: none !important;
            border: 1px solid yellowgreen;
            border-radius: 5px !important;
        }
        .input-group-text {
            font-weight: bolder;
        }
        .form-label {
            color: gray;
            font-size: 12px;
        }
        .network-group {
            font-weight: bold;
            text-transform: uppercase;
        }
    </style>

    <?php if(!empty($row)): ?>
        <?php
            $rowStatus = strtolower(trim((string)($row->transaction_status ?? '')));
            $rawDenosJson = (string)old_value('denos_json', $row->denos_json ?? '');
            $rawDenosJsonTrim = trim($rawDenosJson);
            $denosJsonValue = ($rawDenosJsonTrim === '') ? '[]' : $rawDenosJsonTrim;
            if (function_exists('BackOfficeCashdrawer\\normalizeDenominations')) {
                $currency = (strtoupper(trim((string)($row->network ?? ''))) === 'CFA') ? 'CFA' : 'GHS';
                $normalized = \BackOfficeCashdrawer\normalizeDenominations($denosJsonValue, (float)($row->amount ?? 0), $currency);
                $denosJsonValue = json_encode($normalized);
            }

            $amountMap = [];
            if (!empty($bulkChildren) && is_array($bulkChildren)) {
                foreach ($bulkChildren as $c) {
                    $k = strtoupper(trim((string)($c->network ?? ''))) . '|' . strtoupper(trim((string)($c->commission_type ?? '')));
                    $amountMap[$k] = (float)($c->amount ?? 0);
                }
            }
        ?>

        <form method="post" enctype="multipart/form-data">
            <div class="row col-md-12 col-md-10 col-lg-8 mx-auto shadow p-2 rounded mb-5">

                <?=csrf()?>

                <h4 class="text-center">
                    <span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span><br>
                    UPDATE DAILY COMMISSION
                </h4>

                <div class="mb-3 col-md-6">
                    <label for="pay_to" class="form-label">Pay to</label>
                    <select class="form-select" name="pay_to" id="pay_to">
                        <option value="">---Select---</option>
                        <?php if(!empty($users)): ?>
                            <?php foreach ($users as $wallet): ?>
                                <option <?=old_select('pay_to', $wallet->id, $row->pay_to)?> value="<?=$wallet->id?>">
                                    <?=$wallet->first_name . ' ' . $wallet->last_name ?? ''?> [<?=$wallet->roles[0]?>]
                                </option>
                            <?php endforeach ?>
                        <?php endif; ?>
                    </select>
                    <?php if(!empty($errors['pay_to'])): ?>
                        <small class="text-danger"><?=$errors['pay_to']?></small>
                    <?php endif ?>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th>COMMISSION TYPE</th>
                                <th>AMOUNT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($mywallets)): $globalIndex = 0; ?>
                                <?php
                                $grouped_wallets = [];
                                foreach ($mywallets as $r) {
                                    $grouped_wallets[$r->network][] = $r;
                                }

                                $networkOptions = [
                                    "AT" => ["SENDING", "EVD", "DATA", "SIM"],
                                    "MTN" => ["SENDING", "EVD", "DATA", "SIM"],
                                    "TELECEL" => ["SENDING", "EVD", "DATA", "SIM"],
                                    "GHANAPAY" => ["AIRTIME COMMISSION", "SENDING"],
                                    "G-MONEY" => ["SENDING"],
                                    "GHANAPAY" => ["SENDING"],
                                    "ZEEPAY" => ["SENDING", "EVD", "DATA"],
                                    "GhQR" => ["AGENT COMMISSION"],
                                    "CFA" => ["BUY & SELL"],
                                ];

                                function matchNetworkDailyEdit($walletNetwork, $networkOptions) {
                                    foreach ($networkOptions as $key => $options) {
                                        if (stripos($walletNetwork, $key) !== false) {
                                            return $options;
                                        }
                                    }
                                    return null;
                                }
                                ?>

                                <?php foreach ($grouped_wallets as $network => $wallets): ?>
                                    <?php
                                    $matchingOptions = matchNetworkDailyEdit($network, $networkOptions);
                                    if ($matchingOptions):
                                        $wallet0 = !empty($wallets) ? $wallets[0] : null;
                                    ?>
                                        <tr>
                                            <td colspan="3" class="network-group text-muted"><?= strtoupper($network) ?>:</td>
                                        </tr>

                                        <?php foreach ($matchingOptions as $option): ?>
                                            <?php
                                                $key = strtoupper($network) . '|' . strtoupper($option);
                                                $prefillAmount = $amountMap[$key] ?? '';
                                            ?>
                                            <tr>
                                                <td style="white-space: nowrap;" class="px-1">
                                                    <?= strtoupper($network) ?> <?= esc($option) ?>:
                                                    <input type="hidden" name="rows[<?= $globalIndex ?>][commission_type]" value="<?= strtoupper($option) ?>">
                                                    <input type="hidden" name="rows[<?= $globalIndex ?>][network]" value="<?= strtoupper($network) ?>">
                                                    <input type="hidden" name="rows[<?= $globalIndex ?>][colletion_period]" class="d-none" value="INSTANT DIRECT">
                                                    <input type="hidden" name="rows[<?= $globalIndex ?>][description]" class="d-none" value="<?=strtoupper($network) . ' ' . $option?> daily commission submission">
                                                </td>
                                                <td>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        name="rows[<?= $globalIndex ?>][amount]"
                                                        class="form-control p-1 amount-input"
                                                        placeholder="Enter current amount"
                                                        value="<?= htmlspecialchars((string)$prefillAmount, ENT_QUOTES) ?>"
                                                        data-row-index="<?= $globalIndex ?>">
                                                    <input type="hidden" name="rows[<?= $globalIndex ?>][expected_amount]" value="<?= $wallet0->amount ?? 0 ?>">
                                                </td>
                                            </tr>
                                            <?php $globalIndex++; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>

                                <tr>
                                    <th>Transaction Status: </th>
                                    <th style="white-space: nowrap;">
                                        <div class="transaction_status">
                                            <select class="form-select transaction_status" name="transaction_status">
                                                <option value="">---Select---</option>
                                                <option <?=old_select('transaction_status','Draft', ucfirst($rowStatus))?> value="Draft">Draft</option>
                                                <option <?=old_select('transaction_status','Pending', ucfirst($rowStatus))?> value="Pending">Pending</option>
                                                <option <?=old_select('transaction_status','Approved', ucfirst($rowStatus))?> value="Approved">Approved</option>
                                            </select>
                                            <?php if (!empty($errors['transaction_status'])): ?>
                                                <small class="text-danger"><?= $errors['transaction_status'] ?></small>
                                            <?php endif ?>
                                        </div>
                                    </th>
                                </tr>
                            <?php else: ?>
                                <tr><td colspan="10" class="text-center justify-content-center">No records to show!</td></tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th class="px-1">TOTAL</th>
                                <th>
                                    <div class="input-group input-group-sm" id="dailyCommissionCashAmountGroup" data-bs-toggle="modal" data-bs-target="#dailyCommissionDenoModal" style="cursor:pointer;">
                                        <span class="input-group-text"><i class="fa-solid fa-calculator text-warning"></i></span>
                                        <input type="text" class="form-control p-1" id="total-amount" name="total_balance" style="border:none;" placeholder="Total" readonly>
                                    </div>
                                    <small class="text-warning d-block fw-semibold" id="dailyCommissionDenoPrompt">Click the calculator to enter cash denominations before submitting.</small>
                                    <input type="hidden" id="denos_json" name="denos_json" value="<?=htmlspecialchars($denosJsonValue, ENT_QUOTES)?>" />
                                    <small class="text-danger d-none" id="dailyCommissionDenoRequiredHint">Cash denominations are required when Status is Approved.</small>
                                    <?php if(!empty($errors['denos_json']) || !empty($errors['denos_json_mismatch'])): ?>
                                        <small class="text-danger d-block"><?=$errors['denos_json'] ?? $errors['denos_json_mismatch']?></small>
                                    <?php endif ?>
                                </th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="d-flex justify-content-between mb-5" style="margin-top:10px;">
                    <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route ?>">
                        <button type="button" class="btn btn-danger">
                            <i class="fa-solid fa-chevron-left"></i> Exit
                        </button>
                    </a>
                    <button class="btn btn-primary" type="submit" id="submit">
                        <i class="fa-solid fa-save"></i> Submit
                    </button>
                </div>
            </div>
        </form>

        <div class="modal fade" id="dailyCommissionDenoModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Currency Notes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body table-responsive">
                <table class="table table-sm align-middle">
                  <thead>
                    <tr>
                      <th>Notes</th>
                      <th style="width:90px;">Count</th>
                      <th style="width:120px;">Volume</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php $denomsGhs = [200,100,50,20,10,5,2,1,0.5,0.2,0.1]; ?>
                    <?php $denomsCfa = [10000,5000,2000,1000,500,200,100,50,20,10]; ?>
                    <?php foreach($denomsGhs as $deno): ?>
                    <tr data-currency="GHS" data-denomination="<?=$deno?>">
                      <td>GH&#x20B5;<?=rtrim(rtrim(number_format($deno,2), '0'), '.')?></td>
                      <td><input type="number" min="0" class="form-control form-control-sm deno-count" /></td>
                      <td><input type="text" class="form-control form-control-sm deno-volume" readonly /></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php foreach($denomsCfa as $deno): ?>
                    <tr data-currency="CFA" data-denomination="<?=$deno?>">
                      <td><?=number_format($deno,0)?> CFA</td>
                      <td><input type="number" min="0" class="form-control form-control-sm deno-count" /></td>
                      <td><input type="text" class="form-control form-control-sm deno-volume" readonly /></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                  <tfoot>
                    <tr>
                      <th>Total</th>
                      <th><input type="text" class="form-control form-control-sm" id="dailyCommissionDenoCount" readonly /></th>
                      <th><input type="text" class="form-control form-control-sm" id="dailyCommissionDenoTotal" readonly /></th>
                    </tr>
                  </tfoot>
                </table>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="dailyCommissionDenoReset">Reset</button>
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="dailyCommissionDenoApply">Apply</button>
              </div>
            </div>
          </div>
        </div>

        <script>
            document.addEventListener("DOMContentLoaded", function () {
                const amountInputs = document.querySelectorAll('.amount-input');
                const denosInput = document.getElementById('denos_json');
                const totalAmountField = document.getElementById('total-amount');
                const modalEl = document.getElementById('dailyCommissionDenoModal');
                const statusSelect = document.querySelector('select[name="transaction_status"]');
                const submitBtn = document.getElementById('submit');
                const denosRequiredHint = document.getElementById('dailyCommissionDenoRequiredHint');
                let lastCurrency = null;

                function getStatusLower(){
                    return statusSelect ? String(statusSelect.value || '').trim().toLowerCase() : '';
                }
                function getTotalAmountValue(){
                    return parseFloat((totalAmountField && totalAmountField.value) ? totalAmountField.value : '0') || 0;
                }
                function hasDenosPayload(){
                    if(!denosInput) return false;
                    const raw = String(denosInput.value || '').trim();
                    if(!raw) return false;
                    try{
                        const payload = JSON.parse(raw);
                        return Array.isArray(payload) && payload.length > 0;
                    }catch(e){
                        return false;
                    }
                }
                function toggleSubmitAvailability(){
                    if(!submitBtn) return;
                    const isApproved = getStatusLower() === 'approved';
                    const totalValue = getTotalAmountValue();
                    const requiresDenos = isApproved && totalValue > 0;
                    const ok = (!requiresDenos) || hasDenosPayload();
                    submitBtn.disabled = !ok;
                    if(denosRequiredHint){
                        denosRequiredHint.classList.toggle('d-none', ok || !requiresDenos);
                    }
                }

                function getSelectedCurrency(){
                    let hasGhs = false;
                    let hasCfa = false;
                    amountInputs.forEach(input => {
                        const amount = parseFloat(input.value || '0') || 0;
                        if (amount <= 0) return;
                        const row = input.closest('tr');
                        if(!row) return;
                        const networkField = row.querySelector('input[name$="[network]"]');
                        const network = networkField ? String(networkField.value || '').toUpperCase() : '';
                        if (network === 'CFA') {
                            hasCfa = true;
                        } else {
                            hasGhs = true;
                        }
                    });
                    if (hasGhs && hasCfa) return 'MIXED';
                    if (hasCfa) return 'CFA';
                    return 'GHS';
                }

				function getCurrencyFromDenosPayload(){
					if(!denosInput) return null;
					const raw = String(denosInput.value || '').trim();
					if(!raw) return null;
					try{
						const payload = JSON.parse(raw);
						if(!Array.isArray(payload) || payload.length === 0) return null;
						let hasGhs = false;
						let hasCfa = false;
						payload.forEach(p => {
							const c = String(p.currency || '').toUpperCase();
							if(c === 'CFA') hasCfa = true;
							if(c === 'GHS') hasGhs = true;
						});
						if(hasGhs && hasCfa) return 'MIXED';
						if(hasCfa) return 'CFA';
						if(hasGhs) return 'GHS';
						return null;
					}catch(e){
						return null;
					}
				}

				function getCurrencyForModal(){
					return getCurrencyFromDenosPayload() || getSelectedCurrency();
				}

                function updateTotalAmount() {
                    let totalAmount = 0;
                    amountInputs.forEach(input => {
                        let value = parseFloat(input.value) || 0;
                        totalAmount += value;
                    });
                    if(totalAmountField) totalAmountField.value = totalAmount.toFixed(2);
                    toggleSubmitAvailability();
                }

                amountInputs.forEach(input => {
                    input.addEventListener('blur', function () {
                        let value = parseFloat(this.value) || 0;
                        this.value = value ? value.toFixed(2) : '';
                        updateTotalAmount();
                        const cur = getSelectedCurrency();
                        if (lastCurrency !== null && cur !== lastCurrency) {
                            lastCurrency = cur;
                            if (denosInput) denosInput.value = '';
                        } else {
                            lastCurrency = cur;
                        }
                        toggleSubmitAvailability();
                    });
                });

                updateTotalAmount();
                lastCurrency = getSelectedCurrency();

                (function(){
                    if(!modalEl) return;
                    const countField = document.getElementById('dailyCommissionDenoCount');
                    const totalField = document.getElementById('dailyCommissionDenoTotal');
                    const resetBtn = document.getElementById('dailyCommissionDenoReset');
                    const applyBtn = document.getElementById('dailyCommissionDenoApply');
                    const countInputs = modalEl.querySelectorAll('.deno-count');
                    const denomRows = modalEl.querySelectorAll('tbody tr[data-denomination]');

                    function applyCurrencyVisibility(){
						const cur = getCurrencyForModal();
                        denomRows.forEach(row => {
                            row.style.display = (String(row.dataset.currency || '').toUpperCase() === cur) ? '' : 'none';
                        });
                    }
                    function resetCounts(){
                        countInputs.forEach(input => {
                            input.value='';
                            const row = input.closest('tr');
                            if(row){
                                const vol=row.querySelector('.deno-volume');
                                if(vol) vol.value='';
                            }
                        });
                        recalc();
                    }
                    function recalc(){
                        applyCurrencyVisibility();
                        let totalCount=0;
                        let totalVolume=0;
                        const snapshot=[];
						const cur = getCurrencyForModal();
                        denomRows.forEach(row => {
                            if(row.style.display === 'none') return;
                            const denom=parseFloat(row.dataset.denomination||'0')||0;
                            const input=row.querySelector('.deno-count');
                            const count=input?(parseInt(input.value||'0',10)||0):0;
                            const volume=denom*count;
                            const volInput=row.querySelector('.deno-volume');
                            if(volInput) volInput.value=volume?volume.toFixed((cur === 'CFA') ? 0 : 2):'';
                            if(count>0 && volume>0){
                                snapshot.push({currency:cur, denomination:denom, count, volume});
                                totalCount += count;
                                totalVolume += volume;
                            }
                        });
                        if(countField) countField.value = totalCount ? String(totalCount) : '';
                        if(totalField) totalField.value = totalVolume ? totalVolume.toFixed((cur === 'CFA') ? 0 : 2) : '';
                        return {snapshot, totalVolume};
                    }
                    function restoreFromHidden(){
                        countInputs.forEach(inp => {
                            inp.value = '';
                            const row = inp.closest('tr');
                            if(row){
                                const vol=row.querySelector('.deno-volume');
                                if(vol) vol.value='';
                            }
                        });
                        applyCurrencyVisibility();
                        try{
                            const payload = JSON.parse((denosInput && denosInput.value) ? denosInput.value : '[]');
                            if(Array.isArray(payload)){
                                payload.forEach(entry => {
                                    countInputs.forEach(inp => {
                                        const row=inp.closest('tr');
                                        if(!row) return;
                                        const denom=parseFloat(row.dataset.denomination||'0')||0;
                                        const rowCur = String(row.dataset.currency||'').toUpperCase();
                                        const entryCur = String(entry.currency||'').toUpperCase();
                                        if(rowCur === entryCur && Math.abs(denom - (parseFloat(entry.denomination||0)||0)) < 0.0001){
                                            inp.value = String(parseInt(entry.count||0,10)||0);
                                        }
                                    });
                                });
                            }
                        }catch(e){}
                        recalc();
                    }

                    restoreFromHidden();
                    modalEl.addEventListener('shown.bs.modal', function(){
						const cur = getCurrencyForModal();
                        if (cur === 'MIXED') {
                            alert('Cannot mix GHS and CFA entries. Submit them separately.');
                            const bsModal = bootstrap.Modal.getInstance(modalEl);
                            if(bsModal) bsModal.hide();
                            return;
                        }
                        restoreFromHidden();
                    });
                    countInputs.forEach(input => input.addEventListener('input', recalc));
                    if(resetBtn) resetBtn.addEventListener('click', () => {
                        if(denosInput) denosInput.value='';
                        resetCounts();
                        toggleSubmitAvailability();
                    });
                    if(applyBtn) applyBtn.addEventListener('click', () => {
						const cur = getCurrencyForModal();
                        if (cur === 'MIXED') {
                            alert('Cannot mix GHS and CFA entries. Submit them separately.');
                            return;
                        }
                        const {snapshot, totalVolume} = recalc();
                        const expected = parseFloat((totalAmountField && totalAmountField.value) ? totalAmountField.value : '0') || 0;
                        if (expected > 0 && Math.abs(totalVolume - expected) > 0.01) {
                            alert('Calculator total must match daily commission total.');
                            return;
                        }
                        if(denosInput) denosInput.value = JSON.stringify(snapshot);
                        toggleSubmitAvailability();
                        const bsModal = bootstrap.Modal.getInstance(modalEl);
                        if(bsModal) bsModal.hide();
                    });
                })();

                if(statusSelect) statusSelect.addEventListener('change', toggleSubmitAvailability);
                if(denosInput) denosInput.addEventListener('input', toggleSubmitAvailability);
                toggleSubmitAvailability();
            });
        </script>
    <?php else: ?>
        <div class="alert alert-danger text-center">That record was not found!</div>
    <?php endif ?>

<?php else: ?>
    <div class="alert alert-danger text-center">
        Access denied. You don’t have permission for this action.
    </div>
<?php endif ?>

<?php if(user_can('add_agencybanking') || user_can('edit_momoremit')):?>
<style>
 form {margin-top:40px!important;}
 .form-control,.form-select {
 border:1px solid yellowgreen!important;
 border-radius:5px!important;
 border-left:0.5px solid gray!important;
 border-top:none!important;
 border-right:none!important;
 }
 .form-label {color:gray;font-size:12px;}
 .input-group-text{font-weight:bolder;}
 .text-error{font-size:0.85em;}

 .transaction-preview-card{
 	position: relative;
 	width: 100%;
 	max-width: 240px;
 	margin: 0 auto;
 	border-radius: 14px;
 	overflow: hidden;
 	border: 1px solid rgba(229,231,235,0.25);
 	background: rgba(15, 23, 42, 0.10);
 	box-shadow: 0 12px 28px rgba(0,0,0,0.18);
 	cursor: pointer;
 	outline: none;
 }
 .transaction-preview-card:focus{
 	box-shadow: 0 0 0 3px rgba(234,179,8,0.35), 0 12px 28px rgba(0,0,0,0.18);
 }
 .transaction-preview-card img{
 	display:block;
 	width:100%;
 	height: 160px;
 	object-fit: cover;
 }
 .transaction-preview-placeholder{
 	height: 160px;
 	display:flex;
 	align-items:center;
 	justify-content:center;
 	flex-direction:column;
 	gap:.35rem;
 	border: 1px dashed rgba(234,179,8,0.45);
 	border-radius: 14px;
 	margin: .5rem;
 	color: rgba(229,231,235,0.85);
 	background: rgba(2,6,23,0.35);
 }
 .transaction-preview-placeholder .title{
 	font-weight: 700;
 	font-size: .9rem;
 }
 .transaction-preview-placeholder .hint{
 	font-size: .78rem;
 	opacity: .85;
 }
 .transaction-preview-overlay{
 	position:absolute;
 	inset:0;
 	display:flex;
 	align-items:flex-end;
 	justify-content:flex-end;
 	padding: .5rem;
 	background: linear-gradient(180deg, rgba(2,6,23,0) 30%, rgba(2,6,23,0.75) 100%);
 	opacity: 0;
 	transition: opacity .18s ease;
 }
 .transaction-preview-card:hover .transaction-preview-overlay{
 	opacity: 1;
 }
 .transaction-preview-badge{
 	display:inline-flex;
 	align-items:center;
 	gap:.35rem;
 	font-size: .8rem;
 	font-weight: 600;
 	padding: .3rem .6rem;
 	border-radius: 999px;
 	background: rgba(234,179,8,0.92);
 	color: #111827;
 }
 .visually-hidden-file{
 	position:absolute !important;
 	width:1px !important;
 	height:1px !important;
 	padding:0 !important;
 	margin:-1px !important;
 	overflow:hidden !important;
 	clip: rect(0,0,0,0) !important;
 	white-space:nowrap !important;
 	border:0 !important;
 }
</style>

<form method="post" enctype="multipart/form-data">
 <div class="row col-9 col-lg-5 mx-auto shadow p-2 mb-5 rounded">
 <?=csrf()?>

 <h4 class="text-center">
  <img src="<?=APP_LOGO2?>" style="width:60px;height:60px;object-fit:cover;">
  Interbank (GIP)
 </h4>

  <div class="mb-2">
  <label class="form-label">Transaction type</label>
  <select id="transaction_type" name="transaction_type" class="form-select"
    onchange="if (typeof updateSourceOptions === 'function') { updateSourceOptions(); }">
  <option value="">---Select---</option>
  <option <?=old_select('transaction_type','Interbank (gip)')?> value="Interbank (gip)" selected>
   Interbank (gip)
  </option>
  </select>
  <div id="transaction_type_error" class="text-error text-danger d-none">Transaction type is required</div>
 </div>

  <div class="mb-2">
  <label class="form-label">Service provider</label>
  <select id="network" name="network" class="form-select"></select>
  <div id="networkError" class="text-error text-danger d-none">Service provider is required</div>
 </div>

  <div class="mb-2 category d-none">
  <label class="form-label">Category</label>
  <select id="category" name="category" class="form-select"></select>
 </div>

  <div class="mb-2">
  <label class="form-label">Phone</label>
  <input id="phone" name="phone" type="text" class="form-control" placeholder="Enter phone" value="<?=old_value('phone')?>">
  <div id="phoneError" class="text-error text-danger d-none">Phone is required</div>
 </div>

  <div class="mb-2 account_number d-none">
  <label class="form-label">Account number</label>
  <input id="account_number" name="account_number" type="text" class="form-control" value="<?=old_value('account_number')?>">
  <div id="accountNumberError" class="text-error text-danger d-none">Account number is required</div>
 </div>

  <div class="mb-2 destination_bank d-none">
  <label for="destination_bank" class="form-label">Destination bank</label>
  <select class="form-select destination_bank" name="destination_bank">
   <option value="">---Select---</option>
   <option <?=old_select('destination_bank','ABSA')?> value="ABSA">ABSA</option>
   <option <?=old_select('destination_bank','Access Bank')?> value="Access Bank">Access Bank</option>
   <option <?=old_select('destination_bank','ADB')?> value="ADB">ADB</option>
   <option <?=old_select('destination_bank','APEX BANK')?> value="APEX BANK">APEX BANK</option>
   <option <?=old_select('destination_bank','Bank of Africa')?> value="Bank of Africa">Bank of Africa</option>
   <option <?=old_select('destination_bank','CBG')?> value="CBG">CBG</option>
   <option <?=old_select('destination_bank','Cal Bank')?> value="Cal Bank">Cal Bank</option>
   <option <?=old_select('destination_bank','Ecobank')?> value="Ecobank">Ecobank</option>
   <option <?=old_select('destination_bank','Fidelity')?> value="Fidelity">Fidelity</option>
   <option <?=old_select('destination_bank','First Atlantic Bank')?> value="First Atlantic Bank">First Atlantic Bank</option>
   <option <?=old_select('destination_bank','First Bank Ghana')?> value="First Bank Ghana">First Bank Ghana</option>
   <option <?=old_select('destination_bank','FBN')?> value="FBN">FBN</option>
   <option <?=old_select('destination_bank','FNB')?> value="FNB">FNB</option>
   <option <?=old_select('destination_bank','Gtbank')?> value="Gtbank">Gtbank</option>
   <option <?=old_select('destination_bank','GCB')?> value="GCB">GCB</option>
   <option <?=old_select('destination_bank','NIB')?> value="NIB">NIB</option>
   <option <?=old_select('destination_bank','Republic Bank')?> value="Republic Bank">Republic Bank</option>
   <option <?=old_select('destination_bank','OmniBSIC Bank')?> value="OmniBSIC Bank">OmniBSIC Bank</option>
   <option <?=old_select('destination_bank','Society General')?> value="Society General">Society General</option>
   <option <?=old_select('destination_bank','Stanbic Bank')?> value="Stanbic Bank">Stanbic Bank</option>
   <option <?=old_select('destination_bank','Prudential Bank')?> value="Prudential Bank">Prudential Bank</option>
   <option <?=old_select('destination_bank','Standard Charted')?> value="Standard Charted">Standard Charted</option>
   <option <?=old_select('destination_bank','Zenit Bank')?> value="Zenit Bank">Zenit Bank</option>
   <option <?=old_select('destination_bank','umb')?> value="umb">umb</option>
   <option <?=old_select('destination_bank','Zenit Bank')?> value="Zenit Bank">Zenit Bank</option>
  </select>
  <div id="destination_bank_error" class="text-error text-danger d-none">Destination bank is required</div>
 </div>

  <div class="mb-2 source_bank d-none">
  <label class="form-label">Source bank</label>
  <input id="source_bank" name="source_bank" type="text" class="form-control">
 </div>

  <div class="mb-2 sender_phone d-none">
  <label class="form-label">Sender's phone</label>
  <input id="sender_phone" name="sender_phone" type="text" class="form-control">
 </div>

  <div class="mb-2 ussd d-none">
  <label class="form-label">USSD used</label>
  <input id="ussd" name="ussd" type="text" class="form-control">
 </div>

  <div class="mb-2 cash_source d-none">
  <label class="form-label">Source of funds</label>
  <input id="cash_source" name="cash_source" type="text" class="form-control">
 </div>

  <div class="mb-2">
  <label class="form-label">Load amount (≤ 50,000)</label>
  <input id="load" name="amount" type="number" step="0.01" class="form-control"
   placeholder="Enter load" max="50000"
   value="<?=old_value('amount')?>">
  <div id="loadError" class="text-error text-danger d-none">Load amount is required &amp; must be ≤ 50,000</div>
 </div>

  <div class="mb-2">
  <label class="form-label">GIP charge (1%, capped ₵10.00)</label>
  <input id="charge" name="charge" type="text" class="form-control" readonly>
 </div>

  <div class="mb-2">
  <label class="form-label">Amount payable</label>
  <input id="payable" name="payable" type="text" class="form-control" readonly>
 </div>

  <div class="col-md-12">
    <label for="transaction_image" class="form-label">Transaction image</label>
    <div class="mt-2" id="transactionImagePreviewWrap">
      <div class="transaction-preview-card" id="transactionImagePreviewCard" role="button" tabindex="0">
        <div class="transaction-preview-placeholder" id="transactionImagePreviewPlaceholder">
          <i class="fa-solid fa-cloud-arrow-up" style="font-size:1.4rem;"></i>
          <div class="title">Upload transaction picture</div>
          <div class="hint">Tap to choose an image</div>
        </div>
        <img id="transactionImagePreview" src="" alt="Transaction message picture" style="display:none;">
        <div class="transaction-preview-overlay">
          <span class="transaction-preview-badge"><i class="fa-solid fa-camera"></i> Change</span>
        </div>
      </div>
      <small class="text-muted d-block mt-1 text-center">Tap the preview to change the image</small>
    </div>
    <small class="text-danger js-transaction-image-error text-center d-block"><?=(string)($errors['transaction_image'] ?? '')?></small>
    <input name="transaction_image" type="file" class="visually-hidden-file" id="transaction_image" accept="image/*">
  </div>

  <div class="mb-2">
  <label class="form-label">Amount paid</label>
  <label class="input-group" data-bs-toggle="modal" data-bs-target="#staticBackdrop">
  <i class="fa-solid fa-calculator input-group-text text-warning"></i>
  <input id="paid" name="paid" type="number" step="0.01" class="form-control" readonly value="<?=old_value('paid')?>">
  <input type="hidden" id="denos" name="denos" value="<?=htmlspecialchars((string)old_value('denos'), ENT_QUOTES, 'UTF-8')?>">
  <div id="paidError" class="text-error text-danger d-none">Amount paid is required</div>
  <div id="mismatchError" class="text-error text-danger d-none">Paid must equal Payable</div>
 </div>

  <div class="mb-2 transaction_status">
  <label class="form-label">Status</label>
  <select name="transaction_status" id="transaction_status" class="form-select">
  <option value="">---Select---</option>
  <option <?=old_select('transaction_status','Draft')?> value="Draft">Draft</option>
  <option <?=old_select('transaction_status','Pending')?> value="Pending">Pending</option>
  <?php if(user_can('approve_transactions')):?>
   <option <?=old_select('transaction_status','Rejected')?> value="Rejected">Rejected</option>
   <option <?=old_select('transaction_status','Approved')?> value="Approved">Approved</option>
  <?php endif?>
  </select>
 </div>

 <div class="d-flex justify-content-between mt-3">
  <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>" class="btn btn-primary">
  <i class="fa-solid fa-chevron-left"></i> Back
  </a>
  <button type="submit" id="save" class="btn btn-danger" disabled>
  <i class="fa-solid fa-save"></i> Save
  </button>
 </div>
 </div>
</form>

<div class="modal fade" id="staticBackdrop" tabindex="-1" aria-hidden="true">
 <div class="modal-dialog"><div class="modal-content">
 <div class="modal-header">
  <h5 class="modal-title">Currency Notes</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
 </div>
 <div class="modal-body table-responsive">
  <table class="table table-sm">
  <thead><tr><th>NOTES</th><th>COUNT</th><th>VOLUME</th></tr></thead>
  <tbody>
   <?php foreach([200,100,50,20,10,5,2,1] as $d):?>
   <tr data-denomination="<?=$d?>">
   <td>GH₵<?=$d?></td>
   <td><input class="form-control p-1 count-input"></td>
   <td><input class="form-control p-1 volume-input" readonly></td>
   </tr>
   <?php endforeach;?>
   <tr><td colspan="3">COINS</td></tr>
   <?php foreach([0.5,0.2,0.1] as $d):?>
   <tr data-denomination="<?=$d?>">
   <td><?= $d<1?($d*100).'p':'GH₵'.$d?></td>
   <td><input class="form-control p-1 count-input"></td>
   <td><input class="form-control p-1 volume-input" readonly></td>
   </tr>
   <?php endforeach;?>
  </tbody>
  <tfoot class="bg-secondary text-warning">
   <tr>
   <th>Total</th>
   <th><input id="total-count" class="form-control p-1" readonly></th>
   <th><input id="total-volume" class="form-control p-1" readonly></th>
   </tr>
  <tr><th colspan="3" class="text-center"><p class="text-info mb-1" id="required-currency-total">Required total: 0.00</p></th></tr>
  </tfoot>
  </table>
 </div>
 <div class="modal-footer">
  <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
  <button class="btn btn-danger reset">Reset</button>
  <button class="btn btn-warning restore">Restore</button>
  <button class="btn btn-primary submit">Confirm</button>
 </div>
  <div id="modalMismatch" class="text-error text-danger d-none mb-2"></div>

 </div></div>
</div>

<?php else:?>
 <div class="alert alert-danger text-center">
 Access denied.
 </div>
<?php endif?>

<script>
<?=get_app_image_cropper_inline_source()?>
document.addEventListener("DOMContentLoaded", function() {
 const oldNetworkValue = "<?=htmlspecialchars((string)old_value('network'), ENT_QUOTES, 'UTF-8')?>";
 const sourceOptions = {
  "Interbank (gip)": [
   { value: "", text: "---Select---" },
   { value: "Gtbank", text: "Gtbank" },
   { value: "Ecobank", text: "Ecobank" },
   { value: "FBN", text: "FBN" },
   { value: "Access Bank", text: "Access Bank" },
  ]
 };

 const form = document.querySelector('form');
 const transactionTypeSelect = document.getElementById('transaction_type');
 const networkSelect = document.getElementById('network');
 const phoneInput = document.getElementById('phone');
 const accountNumberInput = document.getElementById('account_number');
 const destinationBankSelect = document.querySelector('.destination_bank select');
 const loadInput = document.getElementById('load');
 const paidInput = document.getElementById('paid');
 const saveBtn = document.getElementById('save');
 const payableEl = document.getElementById('payable');
 const chargeEl = document.getElementById('charge');
 const requiredCurrencyTotalEl = document.getElementById('required-currency-total');
 const updateRequiredCurrencyTotal = () => {
  const payable = parseFloat(String(payableEl ? payableEl.value : '').replace(/,/g, '')) || 0;
  if (requiredCurrencyTotalEl) requiredCurrencyTotalEl.textContent = `Required total: ${payable.toFixed(2)}`;
  return payable;
 };

 const requiredFields = [
  { id: 'transaction_type', errorId: 'transaction_type_error' },
  { id: 'network', errorId: 'networkError' },
  { id: 'phone', errorId: 'phoneError' },
  { id: 'account_number', errorId: 'accountNumberError' },
  { id: 'destination_bank', errorId: 'destination_bank_error' },
  { id: 'load', errorId: 'loadError' },
  { id: 'paid', errorId: 'paidError' },
 ];

 // Get all form elements that need to be validated
 const formElements = document.querySelectorAll('input, select');
 let formIsValid = false;

 // Denominations modal elements
 const countInputs = document.querySelectorAll('.count-input');
 const totalVolumeField = document.getElementById('total-volume');
 const denosField = document.getElementById('denos');
 const confirmBtn = document.querySelector('.modal-footer .submit');
 const restoreBtn = document.querySelector('.modal-footer .restore');
 const modalMismatch = document.getElementById('modalMismatch');
 const mismatchError = document.getElementById('mismatchError');
 const paidError = document.getElementById('paidError');
 const staticBackdropModal = new bootstrap.Modal(document.getElementById('staticBackdrop'));

 function updateSourceOptions() {
  const transactionTypeValue = transactionTypeSelect.value;
  networkSelect.innerHTML = ""; // Clear current options
  if (sourceOptions[transactionTypeValue]) {
   sourceOptions[transactionTypeValue].forEach(option => {
    const opt = document.createElement('option');
    opt.value = option.value;
    opt.textContent = option.text;
    networkSelect.appendChild(opt);
   });
  }
  if (oldNetworkValue) {
   networkSelect.value = oldNetworkValue;
  }
  checkFormValidity();
 }

 function updateChargeAndPayable() {
  let load = parseFloat(loadInput.value) || 0;
  if (load > 50000) {
   loadInput.value = 50000;
   load = 50000;
  }
  let charge = Math.min(load * 0.01, 10);
  // Apply cents-based rounding rule:
  // - 0.01..0.49 => .50
  // - 0.51..0.99 => next 1.00
  // - keep .00 and .50 as-is
  let cents = Math.round(charge * 100);
  let baseCents = Math.floor(cents / 100) * 100;
  let fracCents = cents - baseCents;
  if (fracCents > 0 && fracCents < 50) {
   cents = baseCents + 50;
  } else if (fracCents > 50 && fracCents < 100) {
   cents = baseCents + 100;
  }
  charge = cents / 100;
  chargeEl.value = charge.toFixed(2);
  payableEl.value = (load + charge).toFixed(2);
  updateRequiredCurrencyTotal();
  checkFormValidity();
 }

 function checkFormValidity() {
  let isValid = true;
  let isMismatch = false;

  requiredFields.forEach(field => {
   const input = document.getElementById(field.id);
   const error = document.getElementById(field.errorId);
   const parentDiv = input ? input.closest('.mb-2') : null;

   // Only validate visible fields
   if (parentDiv && !parentDiv.classList.contains('d-none')) {
    if (!input || !input.value.trim()) {
     isValid = false;
     error.classList.remove('d-none');
    } else {
     error.classList.add('d-none');
    }
   }
  });

  // Check for mismatch between paid and payable
  const payableAmount = parseFloat(payableEl.value) || 0;
  const paidAmount = parseFloat(paidInput.value) || 0;
  isMismatch = paidAmount !== payableAmount && paidAmount !== 0;

  if (isMismatch) {
   mismatchError.classList.remove('d-none');
   isValid = false;
  } else {
   mismatchError.classList.add('d-none');
  }

  // Update the save button state
  saveBtn.disabled = !isValid;
  formIsValid = isValid;
 }

 function updateVolumes() {
  let totalCount = 0;
  let totalVolume = 0;
  const currency = [];

  countInputs.forEach(input => {
   const row = input.closest("tr");
   const denomination = parseFloat(row.getAttribute("data-denomination"));
   const count = parseFloat(input.value) || 0;
   const volumeField = row.querySelector(".volume-input");
   const volume = denomination * count;

   volumeField.value = volume.toFixed(2);
   totalCount += count;
   totalVolume += volume;

   if (count > 0) {
    const denos = {
     currency: `GHS`,
     denomination: denomination,
     count: count,
     volume: volume,
    };
    currency.push(denos);
   }
  });

  document.getElementById('total-count').value = totalCount;
  totalVolumeField.value = totalVolume.toFixed(2);
  denosField.value = JSON.stringify(currency);
 }

 function restoreFromDenos() {
  let raw = (denosField && denosField.value) ? String(denosField.value).trim() : '';
  if (!raw) {
   updateVolumes();
   return;
  }
  let parsed = null;
  try {
   parsed = JSON.parse(raw);
  } catch (e) {
   parsed = null;
  }
  if (!Array.isArray(parsed)) {
   updateVolumes();
   return;
  }

  const map = {};
  parsed.forEach((item) => {
   if (!item || typeof item !== 'object') return;
   const den = parseFloat(item.denomination);
   const cnt = parseInt(item.count, 10);
   if (!isFinite(den) || !isFinite(cnt)) return;
   const key = String(den);
   map[key] = (map[key] || 0) + cnt;
  });

  countInputs.forEach((input) => {
   const row = input.closest('tr');
   if (!row) return;
   const den = parseFloat(row.getAttribute('data-denomination'));
   const key = String(den);
   const cnt = map[key] || 0;
   input.value = cnt > 0 ? String(cnt) : '';
  });

  updateVolumes();
 }

 // Event listeners
 formElements.forEach(element => {
  element.addEventListener('input', checkFormValidity);
 });
 
 // Specific listeners for dynamic calculations
 loadInput.addEventListener('input', updateChargeAndPayable);
 transactionTypeSelect.addEventListener('change', updateSourceOptions);
 paidInput.addEventListener('input', checkFormValidity);
 
 // Form submission handling
 form.addEventListener('submit', function(e) {
  checkFormValidity();
  if (!formIsValid) {
   e.preventDefault();
  }
 });

 // Calculator modal logic
 countInputs.forEach(input => {
  input.addEventListener("input", updateVolumes);
 });

 if (restoreBtn) {
  restoreBtn.addEventListener('click', (e) => {
   e.preventDefault();
   restoreFromDenos();
  });
 }

 const modalEl = document.getElementById('staticBackdrop');
 if (modalEl) {
  modalEl.addEventListener('show.bs.modal', () => {
   restoreFromDenos();
   updateRequiredCurrencyTotal();
  });
 }

 confirmBtn.addEventListener('click', () => {
  updateVolumes();
  const payableAmount = parseFloat(payableEl.value) || 0;
  const paidAmount = parseFloat(totalVolumeField.value) || 0;
  const requiredTotal = document.getElementById('required-currency-total');
  updateRequiredCurrencyTotal();
  if (paidAmount !== payableAmount) {
   const diff = (paidAmount - payableAmount).toFixed(2);
   const sign = diff > 0 ? '+' : '−';
   modalMismatch.textContent = `Mismatch: ${sign}GH₵${Math.abs(diff)}`;
   modalMismatch.classList.remove('d-none');
   // Keep the modal open
  } else {
   paidInput.value = paidAmount.toFixed(2);
   modalMismatch.classList.add('d-none');
   staticBackdropModal.hide();
   checkFormValidity();
  }
 });

 document.querySelector('.modal-footer .reset').addEventListener('click', () => {
  countInputs.forEach(input => input.value = '');
  updateVolumes();
  paidInput.value = ''; // Also clear paid field
  checkFormValidity();
 });
 
 // Initial setup
 updateSourceOptions();
 updateChargeAndPayable();
 restoreFromDenos();
 checkFormValidity();

	(function(){
 		if(!window.AppImageCropper || typeof window.AppImageCropper.bindPreviewCard !== 'function') return;

 		window.AppImageCropper.bindPreviewCard({
 			formEl: typeof form !== 'undefined' ? form : null,
 			inputId: 'transaction_image',
 			cardId: 'transactionImagePreviewCard',
 			previewId: 'transactionImagePreview',
 			placeholderId: 'transactionImagePreviewPlaceholder',
 			errorSelector: '.js-transaction-image-error',
 			aspectRatio: 1.5,
 			maxBytes: window.AppImageCropper.MAX_BYTES,
 			modalTitle: 'Crop transaction picture',
 			required: true,
 			requiredMessage: 'Transaction picture is required'
 		});
	})();
});
</script>

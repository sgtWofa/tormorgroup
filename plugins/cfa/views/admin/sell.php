<?php if(user_can('add_cfa')): ?>

	<style>

		.cfa-sell-form {

			margin-top: 40px !important;

			padding-bottom: 2rem;

		}

		@media (max-width: 576px){

			.cfa-sell-form { padding-bottom: calc(1.5rem + env(safe-area-inset-bottom)); }

		}

		.cfa-sell-form .form-control, .cfa-sell-form .form-select {

			border-top: none !important;

			border-left: 0.5px solid gray !important;

			border-right: none !important;

			border: 1px solid yellowgreen;

			border-radius: 5px !important;

		}

		.cfa-sell-form .input-group-text{

			font-weight: bolder;

		}

		.cfa-sell-form .form-label {

			color: gray;

			font-size: 12px;

		}

		.cfa-sell-form .currency-converter .form-control {

			min-width: 0;

		}

		.cfa-form-card {
			min-width: 0;
		}

		@media (max-width: 767.98px) {
			.cfa-sell-form,
			.cfa-form-card {
				width: 100%;
				max-width: 100%;
			}

			.cfa-form-card .form-control,
			.cfa-form-card .form-select {
				min-width: 0;
				min-height: 44px;
				font-size: 16px;
			}

			.modal.fade.col-7,
			.modal.fade.col-10 {
				width: 100%;
				max-width: 100%;
			}

			.modal.fade.col-7 .modal-dialog,
			.modal.fade.col-10 .modal-dialog {
				width: calc(100vw - 1rem);
				max-width: 680px;
				margin: .5rem auto;
			}
		}

	</style>

	<form class="cfa-sell-form" method="post" enctype="multipart/form-data">

		<div class="col-12 col-sm-11 col-md-10 col-lg-5 mx-auto shadow p-3 p-sm-4 rounded cfa-form-card">

			

			<?=csrf()?>

			<center class="text-center mb-3">

				<h4>

					<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>

					Sell CFA

					<button type="button" class="btn btn-sm btn-outline-secondary ms-2 py-0 px-2" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="bottom" title="Sell CFA" data-bs-content="Enter either GH₵ or CFA; the other amount is calculated. Customer pays GHS and receives CFA.">

						<i class="fa-solid fa-circle-info"></i>

					</button>

				</h4>

				<small class="text-primary">Current Selling rate: CFA1,000 = GH&cent;<?=$settings->selling_rate ?? 0?></small>			



				<div class="alert alert-secondary d-flex align-items-center gap-2 py-2">

					<i class="fa-solid fa-circle-info"></i>

					<span>Enter either currency in the converter. Customer pays GHS and receives CFA.</span>

				</div>

			</center>
			

			<div class=" transaction_type">
			  <label for="transaction_type" class="form-label">Transaction type</label>
			  <select class="form-select transaction_type" name="transaction_type" id="transaction_type">
				  <option <?=old_select('transaction_type','GH₵ to CFA')?> value="GH₵ to CFA" selected>GH₵ to CFA (Sell)</option>
			  </select>
				<?php if(!empty($errors['transaction_type'])): ?>
				  <small class="text-danger"><?=$errors['transaction_type']?></small>
				<?php endif ?>
			</div>
            

            <!-- Customer Phone -->
			<div class="mb-3 customer_phone">
				<label for="customer_phone" class="form-label">Customer Phone</label>
				<input type="text" class="form-control" name="customer_phone" id="customer_phone" value="<?=old_value('customer_phone')?>" placeholder="e.g. 024XXXXXXX">
				<?php if(!empty($errors['customer_phone'])): ?>
					<small class="text-danger"><?=$errors['customer_phone']?></small>
				<?php endif ?>
			</div>


	    <!-- Amount Input with Calculator Modal Trigger -->

	    <div class="currency-converter">
	      <div class="form-label">Currency Converter</div>
	      <div class="row g-2">
	        <div class="col-12 col-sm-6">
	          <div class="input-group">
	            <label class="input-group-text" for="amount_in">GH₵</label>
	            <input name="amount_in" type="number" step="0.01" class="form-control" id="amount_in" placeholder="GHS" aria-label="Amount in GHS">
	          </div>
	        </div>
	        <div class="col-12 col-sm-6">
	          <div class="input-group">
	            <label class="input-group-text" for="amount_out">CFA</label>
	            <input name="amount_out" type="number" step="0.01" class="form-control" id="amount_out" placeholder="CFA" aria-label="Amount in CFA">
	          </div>
	        </div>
	      </div>
	      <div id="cfaPayableSuggestion" class="alert alert-warning py-2 px-3 mt-2 d-none">
	      	<div class="fw-semibold mb-1">Select a payable CFA conversion</div>
	      	<div class="small text-muted mb-2" id="cfaPayableSuggestionText"></div>
	      	<div class="d-flex flex-wrap gap-2" id="cfaPayableSuggestionButtons"></div>
	      </div>

	    </div>



	    <!-- Amount Input with Calculator Modal Trigger -->

	    <div class="">

	      <label for="amount_ghs" class="form-label">Amount In</label>

	      <label class="input-group" data-bs-toggle="modal" data-bs-target="#staticBackdropGhs"> 

	        <label class="input-group-text"><i class="fa-solid fa-calculator px-2 text-success"></i>GH₵</label>

	          <input name="amount_ghs" value="<?=old_value('amount_ghs')?>" type="number" step="0.1" class="form-control" id="amount_ghs" placeholder="Enter amount in GH₵" readonly>

	        		<input type="hidden" id="denos" name="denos" />

	      </label>

	      <?php if(!empty($errors['amount_ghs'])):?>

	        <small class="text-danger"><?=$errors['amount_ghs']?></small>

	      <?php endif?>

	        <small class="text-danger js-amount-in-error"></small>

	    </div>



	    <div class="">

	      <label for="amount_cfa" class="form-label">Amount Out</label>

	      <label class="input-group" data-bs-toggle="modal" data-bs-target="#staticBackdropCfa"> 

	        <label class="input-group-text"><i class="fa-solid fa-calculator px-2 text-warning"></i> CFA</label>

	          <input name="amount_cfa" value="<?=old_value('amount_cfa')?>" type="number" step="0.01" class="form-control" id="amount_cfa" placeholder="Enter amount in CFA" readonly>

	          <input name="buying_rate" value="<?=$settings->buying_rate?>" type="hidden" step="0.01" class="form-control" id="buying_rate">

	          <input name="selling_rate" value="<?=$settings->selling_rate?>" type="hidden" step="0.01" class="form-control" id="selling_rate">

	      </label>

	      <?php if(!empty($errors['amount_cfa'])):?>

	        <small class="text-danger"><?=$errors['amount_cfa']?></small>

	      <?php endif?>

	        <small class="text-danger js-amount-out-error"></small>

	    </div>



		<?php if(!empty($errors['cashdrawer']) || !empty($errors['denos'])): ?>

			<div class="alert alert-danger py-2">

				<?php if(!empty($errors['cashdrawer'])): ?>

					<div><?=esc($errors['cashdrawer'])?></div>

				<?php endif; ?>

				<?php if(!empty($errors['denos'])): ?>

					<div><?=esc($errors['denos'])?></div>

				<?php endif; ?>

			</div>

		<?php endif; ?>



			<div class=" transaction_status">

			  <label for="transaction_status" class="form-label">Status</label>

			  <select class="form-select transaction_status" name="transaction_status">
				  <option value="">---Select---</option>
				  <option <?=old_select('transaction_status','Draft')?> value="Draft">Draft</option>
				  <option <?=old_select('transaction_status','Pending')?> value="Pending">Pending</option>
				  <option <?=old_select('transaction_status','Complete')?> value="Complete">Complete</option>
			  </select>

				

				<?php if(!empty($errors['transaction_status'])): ?>

				  <small class="text-danger"><?=$errors['transaction_status']?></small>

				<?php endif ?>

			</div>

				

			<div class=" ">

			  <label for="description" class="form-label">Description</label>

				<textarea rows="2" id="description" class="form-control" name="description"  placeholder="Write a brief description for this transaction"><?=old_value('description') ?: 'Sell CFA - Customer paid GH₵, customer received CFA.'?></textarea>			

				<?php if(!empty($errors['description'])):?>

				  <small class="text-danger w-100"><?=$errors['description']?></small>

				<?php endif?>

			</div>

	 

			<div class="d-flex justify-content-between mb-5" style="margin-top:10px;">

				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">

					<button type="button" class="btn btn-primary">

						<i class="fa-solid fa-chevron-left"></i> Back

					</button>

				</a>

				<button class="btn btn-danger" id="save" type="submit">

					<i class="fa-solid fa-save"></i> Save

				</button>

			</div>

		</div>

	</form>



	<!-- Calculator modal CFA-->

	<div class="modal fade col-7 mx-auto" id="staticBackdropCfa" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">

	  <div class="modal-dialog">

	    <div class="modal-content">

	      <!-- Modal Header -->

	      <div class="modal-header">

	        <h5 class="modal-title" id="staticBackdropLabel">Currency Notes</h5>

	        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

	      </div>



	      <!-- Modal Body -->

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

	            <tr data-denomination="10000" data-currency="CFA">

	              <td class="px-5">10000CFA</td>

	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>

	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Volume" readonly></td>

	            </tr>

	            <tr data-denomination="5000" data-currency="CFA">

	              <td class="px-5">5000CFA</td>

	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>

	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Volume" readonly></td>

	            </tr>

	            <tr data-denomination="2000" data-currency="CFA">

	              <td class="px-5">2000CFA</td>

	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>

	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Volume" readonly></td>

	            </tr>

	            <tr data-denomination="1000" data-currency="CFA">

	              <td class="px-5">1000CFA</td>

	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>

	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Volume" readonly></td>

	            </tr>

	            <tr data-denomination="500" data-currency="CFA">

	              <td class="px-5">500CFA</td>

	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>

	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Volume" readonly></td>

	            </tr>

	            <tr data-denomination="200" data-currency="CFA">

	              <td class="px-5">200CFA</td>

	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>

	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Volume" readonly></td>

	            </tr>

	            <tr data-denomination="100" data-currency="CFA">

	              <td class="px-5">100CFA</td>

	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>

	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Volume" readonly></td>

	            </tr>

	             <tr data-denomination="50" data-currency="CFA">

	              <td class="px-5">50CFA</td>

	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>

	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>

	            </tr>

	            <tr data-denomination="20" data-currency="CFA">

	              <td class="px-5">20CFA</td>

	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>

	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>

	            </tr>

	            <tr data-denomination="10" data-currency="CFA">

	              <td class="px-5">10CFA</td>

	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>

	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>

	            </tr>

	            <tr data-denomination="5" data-currency="CFA">

	              <td class="px-5">5CFA</td>

	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>

	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>

	            </tr>

	            <tr data-denomination="2" data-currency="CFA">

	              <td class="px-5">2CFA</td>

	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>

	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>

	            </tr>

	            <tr data-denomination="1" data-currency="CFA">

	              <td class="px-5">1CFA</td>

	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>

	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>

	            </tr>

	          </tbody>

	          <tfoot>

	            <tr>

	              <th class="px-5 bg-secondary text-warning">TOTAL</th>

	              <th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="total-countCfa" style="border:none; " placeholder="Total count" readonly></th>

	              <th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="total-volumeCfa" style="border:none; " placeholder="Total volume" readonly></th>

	            </tr>

	          </tfoot>

	        </table>

	      </div>



	      <!-- Modal Footer -->

	      <div class="modal-footer">

	        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>

	        <button type="button" class="btn btn-primary submit">Confirm</button>

	      </div>

	    </div>

	  </div>

	</div>



	<!-- Calculator modal GHS-->

	<div class="modal fade col-10 mx-auto" id="staticBackdropGhs" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">

	  <div class="modal-dialog">

	    <div class="modal-content">

	      <!-- Modal Header -->

	      <div class="modal-header">

	        <h5 class="modal-title" id="staticBackdropLabel">Currency Notes</h5>

	        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

	      </div>



	      <!-- Modal Body -->

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

	            <tr data-denomination="200" data-currency="GHS">

	              <td class="px-5">GH&cent;200</td>

	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>

	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Volume" readonly></td>

	            </tr>

	            <tr data-denomination="100" data-currency="GHS">

	              <td class="px-5">GH&cent;100</td>

	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>

	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Volume" readonly></td>

	            </tr>

	             <tr data-denomination="50" data-currency="GHS">

	              <td class="px-5">GH&cent;50</td>

	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>

	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>

	            </tr>

	            <tr data-denomination="20" data-currency="GHS">

	              <td class="px-5">GH&cent;20</td>

	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>

	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>

	            </tr>

	            <tr data-denomination="10" data-currency="GHS">

	              <td class="px-5">GH&cent;10</td>

	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>

	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>

	            </tr>

	            <tr data-denomination="5" data-currency="GHS">

	              <td class="px-5">GH&cent;5</td>

	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>

	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>

	            </tr>

	            <tr data-denomination="2" data-currency="GHS">

	              <td class="px-5">GH&cent;2</td>

	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>

	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>

	            </tr>

	            <tr data-denomination="1" data-currency="GHS">

	              <td class="px-5">GH&cent;1</td>

	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>

	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>

	            </tr>

	            <tr>

	              <td colspan="3">COINS</td>

	            </tr>

	            <tr data-denomination="2" data-currency="GHS">

	              <td class="px-5">GH&cent;2</td>

	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>

	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>

	            </tr>



	            <tr data-denomination="1" data-currency="GHS">

	              <td class="px-5">GH&cent;1</td>

	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>

	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>

	            </tr>



	            <tr data-denomination="0.5" data-currency="GHS">

	              <td class="px-5">50p</td>

	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>

	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>

	            </tr>



	            <tr data-denomination="0.2" data-currency="GHS">

	              <td class="px-5">20p</td>

	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>

	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>

	            </tr>



	            <tr data-denomination="0.1" data-currency="GHS">

	              <td class="px-5">10p</td>

	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>

	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>

	            </tr>

	          </tbody>

	          <tfoot>

	            <tr>

	              <th class="px-5 bg-secondary text-warning">TOTAL</th>

	              <th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="total-countGhs" style="border:none; " placeholder="Total count" readonly></th>

	              <th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="total-volumeGhs" style="border:none; " placeholder="Total volume" readonly></th>

	            </tr>

	          </tfoot>

	        </table>

	      </div>



	      <!-- Modal Footer -->

	      <div class="modal-footer">

	        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>

	        <button type="button" class="btn btn-primary submitGhs">Confirm</button>

	      </div>

	    </div>

	  </div>

	</div>



<?php else: ?>

	<div class="alert alert-danger text-center">

		Access denied. You don’t have permission for this action.

	</div>

<?php endif ?>



<!-- JavaScript: Calculate Charges, Validate Wallet, Show Fields -->

<script>

	  document.addEventListener("DOMContentLoaded", function () {

	    const countInputs = document.querySelectorAll(".count-input");

	    const totalCountCfaField = document.getElementById("total-countCfa");

	    const totalVolumeCfaField = document.getElementById("total-volumeCfa");

	    const totalCountGhsField = document.getElementById("total-countGhs");

	    const totalVolumeGhsField = document.getElementById("total-volumeGhs");

	  const denosField = document.querySelector("#denos");

	  const submitBtn = document.querySelector('.submit');

	  const submitGhsBtn = document.querySelector('.submitGhs');

	  const cfaModalEl = document.getElementById('staticBackdropCfa');

	  const ghsModalEl = document.getElementById('staticBackdropGhs');

	  const staticBackdropModalCfa = (typeof bootstrap !== 'undefined' && cfaModalEl)

	    ? bootstrap.Modal.getOrCreateInstance(cfaModalEl)

	    : null;

	  const staticBackdropModalGhs = (typeof bootstrap !== 'undefined' && ghsModalEl)

	    ? bootstrap.Modal.getOrCreateInstance(ghsModalEl)

	    : null;

	  const saveBtn = document.getElementById('save');

	  const amountIn = document.getElementById('amount_in');

    const amountOut = document.getElementById('amount_out');

    const amountInError = document.querySelector('.js-amount-in-error');

    const amountOutError = document.querySelector('.js-amount-out-error');
    const payableSuggestion = document.getElementById('cfaPayableSuggestion');
    const payableSuggestionText = document.getElementById('cfaPayableSuggestionText');
    const payableSuggestionButtons = document.getElementById('cfaPayableSuggestionButtons');



    const amountCfaInput = document.getElementById('amount_cfa');

    const amountGhsInput = document.getElementById('amount_ghs');

	    const buyingRate = <?=$settings->buying_rate ?? 0?>;

	    const sellingRate = <?=$settings->selling_rate ?? 0?>;
	    let changeDenos = [];
	    const changeCurrency = 'GHS';
	    const changeDenominations = [200, 100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1];
	    const drawerSnapshot = <?=json_encode($drawerSnapshot ?? [], JSON_UNESCAPED_SLASHES)?>;
	    const changeCurrencyLabel = 'GHS';
	    const receiptCurrencyType = 'GHS';



	    if(typeof bootstrap !== 'undefined' && bootstrap.Popover){

	      document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => {

	        try { new bootstrap.Popover(el); } catch (e) {}

	      });

	    }



	  const form = document.querySelector('form');

	  if(form && saveBtn){

		  form.addEventListener('submit', function(event){

			  if(!validateCfaDrawerPayable(true)){
				  event.preventDefault();
				  event.stopPropagation();
				  saveBtn.classList.remove('disabled');
				  saveBtn.removeAttribute('disabled');
				  return false;
			  }

			  saveBtn.setAttribute('disabled', 'disabled');

			  saveBtn.classList.add('disabled');

		  });

	  }



	  function floorWholeFixed(value){
		  const num = Math.max(0, parseFloat(value) || 0);
		  const nearest = Math.round(num);
		  const normalized = Math.abs(num - nearest) <= 0.00001
			  ? nearest
			  : Math.floor(num + 0.00001);
		  return normalized.toFixed(2);
	  }

	  function roundGhsToTenth(value, mode){
		  const num = Math.max(0, parseFloat(value) || 0);
		  const scaled = num * 10;
		  const nearest = Math.round(scaled);
		  if (Math.abs(scaled - nearest) <= 0.00001) {
			  return Math.max(0, nearest / 10);
		  }
		  const rounded = mode === 'up'
			  ? Math.ceil(scaled - 0.00001)
			  : Math.floor(scaled + 0.00001);
		  return Math.max(0, rounded / 10);
	  }

	  function formatGhs(value){
		  return (Math.round(((parseFloat(value) || 0) + Number.EPSILON) * 100) / 100).toFixed(2);
	  }

	  function formatCfa(value){
		  return (Math.round(parseFloat(value) || 0)).toFixed(2);
	  }

	  function showCfaPayableError(message){
		  if(typeof Swal !== 'undefined' && Swal && typeof Swal.fire === 'function'){
			  Swal.fire({
				  icon: 'error',
				  title: 'CFA payout unavailable',
				  text: message
			  });
			  return;
		  }
		  alert(message);
	  }

	  function getCfaInventoryRows(){
		  const source = (drawerSnapshot && Array.isArray(drawerSnapshot.cfa_denominations) && drawerSnapshot.cfa_denominations.length)
			  ? drawerSnapshot.cfa_denominations
			  : ((drawerSnapshot && drawerSnapshot.cfa_history) || []);
		  const grouped = {};
		  source.forEach(row => {
			  const denomination = Math.round(parseFloat(row.denomination) || 0);
			  const count = parseInt(row.count, 10) || 0;
			  if(denomination <= 0 || count <= 0){
				  return;
			  }
			  grouped[denomination] = (grouped[denomination] || 0) + count;
		  });
		  return Object.keys(grouped)
			  .map(key => ({denomination: parseInt(key, 10), count: grouped[key]}))
			  .filter(row => row.denomination > 0 && row.count > 0)
			  .sort((a, b) => b.denomination - a.denomination);
	  }

	  function getCfaAvailableTotal(rows){
		  return rows.reduce((sum, row) => sum + (row.denomination * row.count), 0);
	  }

	  function buildReachableCfaAmounts(rows, limit){
		  const max = Math.max(0, Math.round(parseFloat(limit) || 0));
		  const reachable = new Set([0]);
		  rows.forEach(row => {
			  let remaining = Math.max(0, parseInt(row.count, 10) || 0);
			  let bundle = 1;
			  while(remaining > 0){
				  const qty = Math.min(bundle, remaining);
				  const value = row.denomination * qty;
				  const additions = [];
				  reachable.forEach(amount => {
					  const next = amount + value;
					  if(next <= max){
						  additions.push(next);
					  }
				  });
				  additions.forEach(amount => reachable.add(amount));
				  remaining -= qty;
				  bundle *= 2;
			  }
		  });
		  return reachable;
	  }

	  function findCfaPayableOptions(targetValue, seedGhsValue){
		  const target = Math.max(0, Math.round(parseFloat(targetValue) || 0));
		  const seedGhs = parseFloat(seedGhsValue) || (sellingRate > 0 ? target * (sellingRate / 1000) : 0);
		  const rows = getCfaInventoryRows();
		  const total = getCfaAvailableTotal(rows);
		  if(target <= 0){
			  return {target, total, exact: false, options: [], message: ''};
		  }
		  if(!rows.length || total <= 0){
			  return {
				  target,
				  total,
				  exact: false,
				  options: [],
				  message: 'Your cashdrawer has no available CFA denominations to pay this transaction.'
			  };
		  }

		  let limit = Math.min(total, Math.max(Math.ceil(target / 50) * 50 + 100000, Math.ceil(target * 1.25), 50000));
		  let reachable = buildReachableCfaAmounts(rows, limit);
		  const exact = isCfaPayableMultiple(target) && reachable.has(target);
		  let down = null;
		  let up = null;

		  const ensureReachableLimit = amount => {
			  if(amount <= limit || amount > total){
				  return;
			  }
			  const nextLimit = Math.min(total, Math.max(amount, limit + 250000));
			  if(nextLimit > limit){
				  limit = nextLimit;
				  reachable = buildReachableCfaAmounts(rows, limit);
			  }
		  };
		  const findByGhs = direction => {
			  if(sellingRate <= 0){
				  return null;
			  }
			  const seedTenths = direction === 'up'
				  ? Math.ceil((seedGhs * 10) - Number.EPSILON)
				  : Math.floor((seedGhs * 10) + Number.EPSILON);
			  const step = direction === 'up' ? 1 : -1;
			  for(let i = 0; i <= 10000; i++){
				  const tenths = seedTenths + (i * step);
				  if(tenths <= 0){
					  break;
				  }
				  const ghs = tenths / 10;
				  const cfa = cfaFromGhsPayable(ghs);
				  if(cfa > total && direction === 'up'){
					  break;
				  }
				  ensureReachableLimit(cfa);
				  if(isCfaPayableMultiple(cfa) && reachable.has(cfa)){
					  return {cfa, ghs};
				  }
			  }
			  return null;
		  };

		  down = findByGhs('down');
		  up = findByGhs('up');

		  const options = [];
		  if(down){
			  options.push({label: down.cfa < target ? 'Down' : 'Payable', cfa: down.cfa, ghs: down.ghs});
		  }
		  if(up && (!down || Math.abs(up.ghs - down.ghs) > 0.00001 || up.cfa !== down.cfa)){
			  options.push({label: up.cfa > target ? 'Up' : 'Payable', cfa: up.cfa, ghs: up.ghs});
		  }

		  return {
			  target,
			  total,
			  exact,
			  options,
			  message: options.length
				  ? 'The current CFA amount cannot be paid with the available CFA denominations in your cashdrawer.'
				  : 'Your cashdrawer does not have a sufficient CFA denomination mix to pay this transaction.'
		  };
	  }

	  function canPayCfaFromDrawer(cfaAmount){
		  const target = Math.max(0, Math.round(parseFloat(cfaAmount) || 0));
		  if(!isCfaPayableMultiple(target)){
			  return false;
		  }
		  return findCfaPayableOptions(target, parseFloat(amountIn.value) || 0).exact;
	  }

	  function validateCfaDrawerPayable(showAlert){
		  const cfaValue = parseFloat(amountOut.value) || 0;
		  if(cfaValue <= 0){
			  return true;
		  }
		  const result = findCfaPayableOptions(cfaValue, parseFloat(amountIn.value) || 0);
		  if(result.exact){
			  return true;
		  }
		  if(showAlert){
			  showCfaPayableError(result.message || 'Select a payable CFA conversion based on your available cashdrawer denominations before continuing.');
			  updatePayableSuggestion();
		  }
		  return false;
	  }

	  function cfaFromGhsPayable(ghsAmount){
		  return sellingRate > 0 ? parseFloat(floorWholeFixed(ghsAmount * (1000 / sellingRate))) : 0;
	  }

	  function isCfaPayableMultiple(value){
		  const cfa = Math.round(parseFloat(value) || 0);
		  return cfa > 0 && Math.abs(cfa % 50) <= 0.00001;
	  }

	  function findNearestPayableGhs(seedGhs, direction){
		  if(sellingRate <= 0){
			  return null;
		  }
		  const seedTenths = direction === 'up'
			  ? Math.ceil(((parseFloat(seedGhs) || 0) * 10) - Number.EPSILON)
			  : Math.floor(((parseFloat(seedGhs) || 0) * 10) + Number.EPSILON);
		  const step = direction === 'up' ? 1 : -1;
		  for(let i = 0; i <= 1000; i++){
			  const tenths = seedTenths + (i * step);
			  if(tenths <= 0){
				  break;
			  }
			  const ghs = tenths / 10;
			  const cfa = cfaFromGhsPayable(ghs);
			  if(isCfaPayableMultiple(cfa)){
				  return {ghs, cfa};
			  }
		  }
		  return null;
	  }

	  function applyPayableSuggestion(cfaAmount, ghsAmount){
		  amountOut.value = formatCfa(cfaAmount);
		  amountIn.value = formatGhs(ghsAmount);
		  changeDenos = [];
		  updateVolumesAndTotals('GHS');
		  updatePayableSuggestion();
		  checkAmountPaid('out');
		  checkAmountPaid('in');
		  maybeShowChangeModal();
	  }

	  function updatePayableSuggestion(){
		  if(!payableSuggestion || !payableSuggestionButtons || !payableSuggestionText){
			  return;
		  }
		  const ghsValue = parseFloat(amountIn.value) || 0;
		  const cfaValue = parseFloat(amountOut.value) || 0;
		  if(cfaValue <= 0){
			  payableSuggestion.classList.add('d-none');
			  payableSuggestionButtons.innerHTML = '';
			  return;
		  }
		  const payable = findCfaPayableOptions(cfaValue, ghsValue);
		  if(payable.exact){
			  payableSuggestion.classList.add('d-none');
			  payableSuggestionButtons.innerHTML = '';
			  return;
		  }
		  const options = payable.options;
		  if(!options.length){
			  payableSuggestion.classList.remove('d-none');
			  payableSuggestion.classList.remove('alert-warning');
			  payableSuggestion.classList.add('alert-danger');
			  payableSuggestionText.textContent = payable.message || 'No payable CFA conversion is available from your cashdrawer denominations.';
			  payableSuggestionButtons.innerHTML = '';
			  return;
		  }
		  payableSuggestion.classList.remove('alert-danger');
		  payableSuggestion.classList.add('alert-warning');
		  payableSuggestionText.textContent = payable.message + ' Current conversion is CFA ' + formatCfa(cfaValue) + '.';
		  payableSuggestionButtons.innerHTML = options.map((option, index) => {
			  return '<button type="button" class="btn btn-sm btn-outline-primary cfa-payable-option" data-index="' + index + '">' +
				  option.label + ': CFA ' + formatCfa(option.cfa) + ' = GHS ' + formatGhs(option.ghs) +
				  '</button>';
		  }).join('');
		  payableSuggestionButtons.querySelectorAll('.cfa-payable-option').forEach(button => {
			  button.addEventListener('click', function(){
				  const option = options[parseInt(this.dataset.index || '0', 10)];
				  if(option){
					  applyPayableSuggestion(option.cfa, option.ghs);
				  }
			  });
		  });
		  payableSuggestion.classList.remove('d-none');
	  }



    // Ensure elements exist before proceeding

    if (!amountCfaInput || !amountGhsInput) {

      console.error("One or more elements not found: 'amount_cfa', 'amount_ghs'. Check HTML element IDs.");

      return; // Exit script if elements are missing

    }



    countInputs.forEach(input => {

        const row = input.closest("tr");

        const currencyType = row.getAttribute("data-currency");

      input.addEventListener("input", function(){

      	if (currencyType === "CFA") {

      		updateVolumesAndTotals("CFA");

      	}

      	if (currencyType === "GHS") {

      		updateVolumesAndTotals("GHS");

      	}

      });

    });



    // Initialize input fields with zero

    amountCfaInput.value = (0).toFixed(2);

    amountGhsInput.value = (0).toFixed(2);



    function updateVolumesAndTotals(type) {

      let totalCountCfa = 0;

      let totalCountGhs = 0;

      let totalVolumeCfa = 0;

      let totalVolumeGhs = 0;

      const currency = [];



      countInputs.forEach(input => {

        const row = input.closest("tr");

        const denomination = parseFloat(row.getAttribute("data-denomination"));

        const currencyType = row.getAttribute("data-currency");

        const count = parseFloat(input.value) || 0;

        const volumeField = row.querySelector(".volume-input");

        const volume = denomination * count;

        volumeField.value = volume.toFixed(2);

        if (currencyType === "CFA") {

	        totalCountCfa += count;

	        totalVolumeCfa += volume;

        }



        if (currencyType === "GHS") {

	        totalCountGhs += count;

	        totalVolumeGhs += volume;

        }



	      // Add to currency array

	      let denos = {

	        currency: currencyType,

	        denomination: denomination,

	        count: count,

	        volume: volume,

	      };

	      currency.push(denos);

      });



      if (type === 'CFA') {

		      totalCountCfaField.value = totalCountCfa;

		      totalVolumeCfaField.value = totalVolumeCfa.toFixed(2);

      }



      if (type === 'GHS') {

		      totalCountGhsField.value = totalCountGhs;

		      totalVolumeGhsField.value = totalVolumeGhs.toFixed(2);

      }



	    // Store serialized currency details in both localStorage and the hidden field

	    changeDenos.forEach(row => currency.push(row));
	    const serializedCurrency = JSON.stringify(currency);

	    localStorage.setItem("denosData", serializedCurrency);

	    denosField.value = serializedCurrency;

    }

	function getChangeTotal(){
		return changeDenos.reduce((sum, row) => sum + Math.abs(parseFloat(row.volume) || 0), 0);
	}

	function getRequiredChange(){
		const received = parseFloat(amountGhsInput.value) || 0;
		const expected = parseFloat(amountIn.value) || 0;
		return Math.max(0, Math.round((received - expected + Number.EPSILON) * 100) / 100);
	}

	function formatChangeMoney(value){ return (Math.round(((parseFloat(value) || 0) + Number.EPSILON) * 100) / 100).toFixed(2); }
	function denomKey(value){ return String(parseFloat(value) || 0).replace(/\.0+$/,'').replace(/(\.\d*?)0+$/,'$1'); }
	function getReceiptRows(){ return Array.from(countInputs).map(input => {
		const row = input.closest('tr');
		const currency = row ? row.getAttribute('data-currency') : '';
		const denomination = row ? parseFloat(row.getAttribute('data-denomination')) || 0 : 0;
		const count = parseInt(input.value || '0', 10) || 0;
		return {currency, denomination, count};
	}).filter(row => row.currency === receiptCurrencyType && row.denomination > 0 && row.count > 0); }
	function getAvailabilityMap(){
		const map = {};
		((drawerSnapshot && drawerSnapshot.ghs_history) || []).forEach(row => {
			const key = denomKey(row.denomination);
			map[key] = (map[key] || 0) + (parseInt(row.count, 10) || 0);
		});
		getReceiptRows().forEach(row => {
			const key = denomKey(row.denomination);
			map[key] = (map[key] || 0) + row.count;
		});
		return map;
	}
	function buildChangeOptions(selected){
		const current = selected ? denomKey(selected) : '';
		return '<option value="">---Amount (' + changeCurrencyLabel + ')---</option>' + changeDenominations.map(value => {
			const key = denomKey(value);
			return '<option value="' + key + '"' + (key === current ? ' selected' : '') + '>' + formatChangeMoney(value) + '</option>';
		}).join('');
	}
	function getRowsFromModal(){
		const el = ensureChangeModal();
		return Array.from(el.querySelectorAll('.cfa-change-row')).map(row => {
			const denomination = parseFloat(row.querySelector('.cfa-change-value').value || '0') || 0;
			const count = parseInt(row.querySelector('.cfa-change-count').value || '0', 10) || 0;
			return {denomination, count};
		}).filter(row => row.denomination > 0 && row.count > 0);
	}
	function getSelectedChangeTotal(rows){ return rows.reduce((sum, row) => sum + (row.denomination * row.count), 0); }
	function renderChangeRows(seedRows){
		const wrap = document.getElementById('cfaChangeRows');
		if(!wrap) return;
		const rows = (seedRows && seedRows.length) ? seedRows : [{denomination:'', count:''}];
		wrap.innerHTML = rows.map(row => `
			<div class="cfa-change-row mb-2">
				<div class="row g-1 align-items-end">
					<div class="col-5"><select class="form-select form-control-sm cfa-change-value">${buildChangeOptions(row.denomination)}</select></div>
					<div class="col-5"><input type="number" class="form-control form-control-sm cfa-change-count" placeholder="Count" step="1" min="1" value="${row.count || ''}"></div>
					<div class="col-2"><button type="button" class="btn btn-sm btn-outline-danger cfa-delete-change"><i class="fa fa-trash"></i></button></div>
				</div>
			</div>`).join('');
		wrap.querySelectorAll('.cfa-change-value,.cfa-change-count').forEach(input => {
			input.addEventListener('input', updateChangeModalState);
			input.addEventListener('change', updateChangeModalState);
		});
		wrap.querySelectorAll('.cfa-delete-change').forEach(btn => btn.addEventListener('click', function(){
			const rows = wrap.querySelectorAll('.cfa-change-row');
			if(rows.length <= 1){
				const row = this.closest('.cfa-change-row');
				row.querySelector('.cfa-change-value').value = '';
				row.querySelector('.cfa-change-count').value = '';
			}else{
				this.closest('.cfa-change-row').remove();
			}
			updateChangeModalState();
		}));
		updateChangeModalState();
	}
	function ensureChangeModal(){
		let el = document.getElementById('cfaChangeModal');
		if(el) return el;
		document.body.insertAdjacentHTML('beforeend', `
			<div class="modal fade" id="cfaChangeModal" tabindex="-1" aria-hidden="true">
				<div class="modal-dialog modal-md"><div class="modal-content border-0 shadow-lg">
					<div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold"><i class="fa fa-exchange-alt me-2"></i> Give Change</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
					<div class="modal-body">
						<div class="alert alert-info py-2 px-3 mb-3"><div class="fw-semibold">Change required: <span id="cfaChangeRequiredText">${changeCurrencyLabel} 0.00</span></div><small class="text-muted">The full received denominations will be added first, then the selected change denominations will be deducted from the cashdrawer.</small></div>
						<div class="alert alert-light border py-2 px-3 mb-3" style="font-size:0.9rem;"><strong>Available after receipt:</strong><span id="cfaAvailableDenosText" class="ms-1 text-muted">No denominations available.</span></div>
						<div id="cfaChangeRows" class="border rounded p-2" style="max-height:200px; overflow-y:auto; background-color:var(--bs-body-bg);"></div>
						<div class="d-flex align-items-center gap-2 mt-2"><small class="text-muted">Selected change: <strong id="cfaChangeSelectedText" class="text-danger">${changeCurrencyLabel} 0.00</strong></small><small class="text-muted ms-auto">Net cash: <strong id="cfaChangeNetText">${changeCurrencyLabel} 0.00</strong></small></div>
						<div class="mt-1"><small id="cfaChangeBalanceText" class="text-warning">Add ${changeCurrencyLabel} 0.00 more to balance.</small></div>
						<div class="d-flex justify-content-end mt-2"><button type="button" class="btn btn-sm btn-danger" id="cfaAddChangeRowBtn"><i class="fa fa-plus me-1"></i> Add</button></div>
						<div class="alert py-2 px-3 mt-3 mb-0" id="cfaChangeAlert"><span id="cfaChangeAlertText">Select the denominations to give out.</span></div>
					</div>
					<div class="modal-footer border-0 pt-0"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-sm btn-primary" id="cfaApplyChange" disabled><i class="fa fa-check me-1"></i> Apply change</button></div>
				</div></div>
			</div>`);
		el = document.getElementById('cfaChangeModal');
		document.getElementById('cfaAddChangeRowBtn').addEventListener('click', () => {
			const rows = getRowsFromModal();
			rows.push({denomination:'', count:''});
			renderChangeRows(rows);
		});
		document.getElementById('cfaApplyChange').addEventListener('click', function(){
			const required = getRequiredChange();
			const rows = getRowsFromModal();
			const selected = getSelectedChangeTotal(rows);
			if(Math.abs(selected - required) > 0.01) return;
			changeDenos = rows.map(row => ({currency: changeCurrency, denomination: row.denomination, count: -1 * row.count, volume: -1 * row.denomination * row.count}));
			updateVolumesAndTotals('GHS');
			checkAmountPaid('in');
			bootstrap.Modal.getOrCreateInstance(el).hide();
		});
		return el;
	}
	function updateChangeModalState(){
		const el = ensureChangeModal();
		const rows = getRowsFromModal();
		const selected = getSelectedChangeTotal(rows);
		const required = getRequiredChange();
		const received = parseFloat(amountGhsInput.value) || 0;
		const netCash = received - selected;
		const gap = required - selected;
		const availability = getAvailabilityMap();
		const requested = {};
		rows.forEach(row => { const key = denomKey(row.denomination); requested[key] = (requested[key] || 0) + row.count; });
		const insufficient = Object.keys(requested).filter(key => requested[key] > (availability[key] || 0)).map(key => changeCurrencyLabel + ' ' + key + ' (need ' + requested[key] + ', available ' + (availability[key] || 0) + ')');
		document.getElementById('cfaChangeRequiredText').textContent = changeCurrencyLabel + ' ' + formatChangeMoney(required);
		document.getElementById('cfaChangeSelectedText').textContent = changeCurrencyLabel + ' ' + formatChangeMoney(selected);
		document.getElementById('cfaChangeNetText').textContent = changeCurrencyLabel + ' ' + formatChangeMoney(netCash);
		document.getElementById('cfaAvailableDenosText').textContent = Object.keys(availability).filter(k => availability[k] > 0).sort((a,b)=>parseFloat(b)-parseFloat(a)).map(k => changeCurrencyLabel + ' ' + formatChangeMoney(k) + ' (' + availability[k] + ' units)').join(', ') || 'No denominations available.';
		const balance = document.getElementById('cfaChangeBalanceText');
		balance.className = Math.abs(gap) <= 0.01 ? 'text-success' : (gap > 0 ? 'text-warning' : 'text-danger');
		balance.textContent = Math.abs(gap) <= 0.01 ? 'Balanced. Change selection matches the required amount.' : (gap > 0 ? 'Add ' + changeCurrencyLabel + ' ' + formatChangeMoney(gap) + ' more to balance.' : 'Reduce change by ' + changeCurrencyLabel + ' ' + formatChangeMoney(Math.abs(gap)) + ' to balance.');
		const alert = document.getElementById('cfaChangeAlert');
		const alertText = document.getElementById('cfaChangeAlertText');
		const apply = document.getElementById('cfaApplyChange');
		if(!rows.length){ alert.className = 'alert alert-info py-2 px-3 mt-3 mb-0'; alertText.textContent = 'Select the denominations to give out. Add ' + changeCurrencyLabel + ' ' + formatChangeMoney(required) + ' more to balance.'; apply.disabled = true; return; }
		if(insufficient.length){ alert.className = 'alert alert-danger py-2 px-3 mt-3 mb-0'; alertText.textContent = 'Insufficient denominations after receipt: ' + insufficient.join(' | '); apply.disabled = true; return; }
		if(Math.abs(gap) > 0.01){ alert.className = 'alert alert-warning py-2 px-3 mt-3 mb-0'; alertText.textContent = gap > 0 ? 'Add ' + changeCurrencyLabel + ' ' + formatChangeMoney(gap) + ' more change so the net cash equals the converter amount.' : 'Reduce change by ' + changeCurrencyLabel + ' ' + formatChangeMoney(Math.abs(gap)) + ' so the net cash equals the converter amount.'; apply.disabled = true; return; }
		alert.className = 'alert alert-success py-2 px-3 mt-3 mb-0'; alertText.textContent = 'Change selection is balanced and ready to apply.'; apply.disabled = false;
	}

	function maybeShowChangeModal(){
		const required = getRequiredChange();
		if(required <= 0.01){
			changeDenos = [];
			updateVolumesAndTotals('GHS');
			return;
		}
		const el = ensureChangeModal();
		renderChangeRows(changeDenos.map(row => ({denomination: Math.abs(row.denomination || 0), count: Math.abs(row.count || 0)})));
		updateChangeModalState();
		setTimeout(() => bootstrap.Modal.getOrCreateInstance(el).show(), 250);
	}



    // Calculate GHS value based on CFA input change

    amountIn.addEventListener("input", function() {

      calculateExchangeValue('GHS');

      checkAmountPaid('in');

    });



    // Calculate CFA value based on GHS output change

    amountOut.addEventListener("input", function() {

      calculateExchangeValue('CFA');

      checkAmountPaid('out');

    });



    // Update exchange values based on the input source

    function calculateExchangeValue(source) {

      //const ghsAmount = parseFloat(amountGhsInput.value) || 0;

      //const cfaAmount = parseFloat(amountCfaInput.value) || 0;

      const amountInValue = parseFloat(amountIn.value) || 0;

      const amountOutValue = parseFloat(amountOut.value) || 0;



	      if (source === 'GHS') {

	        amountOut.value = sellingRate > 0 ? floorWholeFixed(amountInValue * (1000 / sellingRate)) : '0.00';
	        updatePayableSuggestion();

	      } else if (source === 'CFA') {

	        const roundingMode = amountOutValue < 1000 ? 'down' : 'up';
	        amountIn.value = sellingRate > 0 ? formatGhs(roundGhsToTenth(amountOutValue * (sellingRate / 1000), roundingMode)) : '0.00';
	        updatePayableSuggestion();

	      }

	    }



	    function checkAmountPaid(source) {

	    	const amountInValue = parseFloat(amountIn.value) || 0;

	    	const amountOutValue = parseFloat(amountOut.value) || 0;

	    	const ghsTotal = parseFloat(amountGhsInput.value) || 0;

	    	const cfaTotal = parseFloat(amountCfaInput.value) || 0;

	    	const epsilon = 0.01;

	    	if (source === "in") {
	    		const changeTotal = getChangeTotal();
	    		const netGhsTotal = ghsTotal - changeTotal;

	    		if (Math.abs(amountInValue - netGhsTotal) <= epsilon) {

	    			amountInError.textContent = "";

	    			amountIn.classList.remove("border-danger");

	    		}else{

	    			amountInError.textContent = "Amount in must be equal to GH₵ amount";

	    			amountIn.classList.add("border-danger");

	    		}

	    }

	    	if (source === "out") {

	    		if (Math.abs(amountOutValue % 50) > epsilon) {
	    			amountOutError.textContent = "CFA amount must be a multiple of 50. Select a suggested conversion.";
	    			amountOut.classList.add("border-danger");
	    		}else if (!canPayCfaFromDrawer(amountOutValue)) {
	    			amountOutError.textContent = "Cashdrawer cannot pay this CFA amount with available denominations. Select a suggested conversion.";
	    			amountOut.classList.add("border-danger");
	    		}else if (Math.abs(amountOutValue - cfaTotal) <= epsilon) {

	    			amountOutError.textContent = "";

	    			amountOut.classList.remove("border-danger");

	    		}else{

	    			amountOutError.textContent = "Amount out must be equal to CFA amount";

	    			amountOut.classList.add("border-danger");

	    		}

	    }



	    if (amountOutValue && amountInValue) {

	    				if (Math.abs(amountOutValue % 50) <= epsilon && canPayCfaFromDrawer(amountOutValue) && Math.abs(amountOutValue - cfaTotal) <= epsilon && Math.abs(amountInValue - (ghsTotal - getChangeTotal())) <= epsilon) {

					saveBtn.classList.remove('disabled');

					saveBtn.removeAttribute('disabled');

				}else{



						saveBtn.classList.add('disabled');

						saveBtn.setAttribute('disabled','disabled');

					}

	    }

	    }



	    // Update CFA and GHS fields when Confirm button is clicked

	    submitBtn.addEventListener('click', function() {

	      amountCfaInput.value = totalVolumeCfaField.value;

	  		updateVolumesAndTotals('CFA');

	      staticBackdropModalCfa && staticBackdropModalCfa.hide();

	      checkAmountPaid('out');
	      updatePayableSuggestion();

	      //calculateExchangeValue('CFA');

	    });	 



	    submitGhsBtn.addEventListener('click', function() {

	      amountGhsInput.value = totalVolumeGhsField.value;

	  		updateVolumesAndTotals('GHS');

	      staticBackdropModalGhs && staticBackdropModalGhs.hide();
	      maybeShowChangeModal();

	      checkAmountPaid('in');

	      //calculateExchangeValue('CFA');

	    });	    

  });

</script>

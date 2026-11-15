<?php if(user_can('add_agencybanking') || user_can('edit_momoremit')):?>
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
		.input-group-text{
			font-weight: bolder;
		}
		.form-label {
			color: gray;
			font-size: 12px;
		}

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
		<div class="row col-9 col-lg-5 mx-auto shadow mb-5 p-4 rounded">
			
			<?=csrf()?>

			<h4 class="text-center">
				<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
				New Withdrawal
			</h4>
	 
			<div class="mb-2">
				<label for="transaction_type" class="form-label">Transaction type</label>
				<select class="form-select transaction_type" id="transaction_type" name="transaction_type"  onchange="updateSourceOptions()">
				  <option value="">---Select---</option>
				  <option <?=old_select('transaction_type','Withdrawal (AIT)')?> value="Withdrawal (AIT)">Withdrawal (AIT)</option>
				  <option <?=old_select('transaction_type','Withdrawal (CIT)')?> value="Withdrawal (CIT)">Withdrawal (CIT)</option>
				</select>

				<?php if(!empty($errors['transaction_type'])):?>
				  <small class="text-danger"><?=$errors['transaction_type']?></small>
				<?php endif?>
			</div>
	 
			<div class="mb-2">
				<label for="network" class="form-label">Service provider</label>
				<select class="form-select" name="network" id="network">			  
				  </select>

				<?php if(!empty($errors['network'])):?>
				  <small class="text-danger"><?=$errors['network']?></small>
				<?php endif?>
			</div>
	 
			<div class="mb-2 d-none category">
				<label for="category" class="form-label">CIT Type</label>
				<select class="form-select" name="category" id="category">		
				  <option value="">---Select---</option>
				  <option <?=old_select('category','Self')?> value="Self">Self</option>
				  <option <?=old_select('category','3rd Party')?> value="3rd Party">3rd Party</option>	  
				</select>
				<?php if(!empty($errors['category'])):?>
				  <small class="text-danger"><?=$errors['category']?></small>
				<?php endif?>
			</div>
	 
			<div class="mb-2 d-none payment_medium">
				<label for="payment_medium" class="form-label">Medium Used</label>
				<select class="form-select" name="payment_medium" id="payment_medium">		
				  <option value="">---Select---</option>
				  <option <?=old_select('payment_medium','Fidelity App')?> value="Fidelity App">Fidelity App</option>
				  <option <?=old_select('payment_medium','Fidelity-GhQr ussd')?> value="Fidelity-GhQr ussd">Fidelity-GhQr ussd</option>	  
				  <option <?=old_select('payment_medium','Fidelity ussd')?> value="Fidelity ussd">Fidelity ussd</option>	  
				  <option <?=old_select('payment_medium','Other ussd')?> value="Other ussd">Other ussd</option>	  
				</select>
				<?php if(!empty($errors['payment_medium'])):?>
				  <small class="text-danger"><?=$errors['payment_medium']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 d-none charging_type">
				<label for="charging_type" class="form-label">Charging type</label>
				<select class="form-select" name="charging_type" id="charging_type">
					<option value="">---Select---</option>
					<option <?=old_select('charging_type','minus')?> value="minus">- charge</option>
					<option <?=old_select('charging_type','plus')?> value="plus">+ charge</option>
				</select>
				<?php if(!empty($errors['charging_type'])):?>
					<small class="text-danger"><?=$errors['charging_type']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 phone">
			  <label for="phone" class="form-label phoneLabel">Phone</label>
			  <input name="phone" value="<?=old_value('phone')?>" type="text" class="form-control" id="phone" placeholder="Enter phone number">				
				<?php if(!empty($errors['phone'])):?>
				  <small class="text-danger"><?=$errors['phone']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 otherPartyNumber d-none">
			  <label for="other_phone" class="form-label">3rd Party's number</label>
			  <input name="other_phone" value="<?=old_value('other_phone')?>" type="text" class="form-control" id="other_phone" placeholder="Enter other_phone number">				
				<?php if(!empty($errors['other_phone'])):?>
				  <small class="text-danger"><?=$errors['other_phone']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 account_number">
			  <label for="account_number" class="form-label">Account number</label>
			  <input name="account_number" value="<?=old_value('account_number')?>" type="text" class="form-control" id="account_number" placeholder="Enter account number">
				
				<?php if(!empty($errors['account_number'])):?>
				  <small class="text-danger"><?=$errors['account_number']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 amount_withdrawn d-none">
			  <label for="amount_withdrawn" class="form-label">Amount withdrawn</label>
			  <input name="amount_withdrawn" value="<?=old_value('amount_withdrawn')?>" type="number" step="0.01" class="form-control" id="amount_withdrawn" placeholder="Enter amount withdrawn">
				
				<?php if(!empty($errors['amount_withdrawn'])):?>
				  <small class="text-danger"><?=$errors['amount_withdrawn']?></small>
				<?php endif?>
				<small class="text-danger d-block" id="amount-withdrawn-limit-error" style="display:none;"></small>
				<small class="text-muted d-block" id="amount-withdrawn-limit-hint" style="display:none;"></small>
			</div>
	 
			<div class="mb-2 sof_option d-none">
				<label for="sof_option" class="form-label">Source Option</label>
				<select class="form-select" name="sof_option" id="sof_option">		
				  <option value="">---Select---</option>
				  <option <?=old_select('sof_option','Telco')?> value="Telco">Telco</option>
				  <option <?=old_select('sof_option','Bank')?> value="Bank">Bank</option>	  
				</select>
				<?php if(!empty($errors['sof_option'])):?>
				  <small class="text-danger"><?=$errors['sof_option']?></small>
				<?php endif?>
			</div>
	 
			<div class="mb-2 sof d-none">
				<label for="sof" class="form-label">Source of Funds</label>
				<select class="form-select" name="cash_source" id="sof">		
				  <option value="">---Select---</option>
				  </select>
				<?php if(!empty($errors['cash_source'])):?>
				  <small class="text-danger"><?=$errors['cash_source']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 amount_payable d-none">
			  <label for="amount_payable" class="form-label">Amount after Charge (amount payable)</label>
			  <input name="amount_payable" value="<?=old_value('amount_payable')?>" type="number" step="0.01" class="form-control disabled" id="amount_payable" placeholder="Amount payable" readonly>
			  <input name="charge" value="<?=old_value('charge')?>" type="hidden" id="charge">
				
				<?php if(!empty($errors['amount_payable'])):?>
				  <small class="text-danger"><?=$errors['amount_payable']?></small>
				<?php endif?>
			</div>

			<div class="col-md-12" id="transaction_image_wrapper">
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

			    <div class="col-md-12" id="amount_paid_out_wrapper">
			      <label for="amount" class="form-label">Amount Paid out</label>
				   <label class="input-group" data-bs-toggle="modal" data-bs-target="#staticBackdrop"> 
			        <i class="fa-solid fa-calculator input-group-text text-warning"></i>
			          <input name="amount" value="<?=old_value('amount')?>" type="number" step="0.01" class="form-control" id="amount" placeholder="Enter amount" readonly>
				      <input type="hidden" id="denos" name="denos" value="<?=htmlspecialchars((string)old_value('denos'), ENT_QUOTES, 'UTF-8')?>">
			      </label>
			      <?php if(!empty($errors['amount'])):?>
			        <small class="text-danger"><?=$errors['amount']?></small>
			      <?php endif?>
					<?php if(!empty($errors['wallet_out_id'])):?>
					  <small class="text-danger"><?=$errors['wallet_out_id']?></small>
					<?php endif?>
					<?php if(!empty($errors['denos'])):?>
					  <small class="text-danger"><?=$errors['denos']?></small>
					<?php endif?>
					<small class="text-danger" id="amount-match-error" style="display: none;">Amount withdrawn must match amount paid out</small>
			    </div>

				<input type="hidden" id="transaction_status_hidden" name="transaction_status" value="Approved">
				<div class="mb-2 transaction_status d-none">
				  <label for="transaction_status_select" class="form-label">Status</label>
				  <select class="form-select" id="transaction_status_select">
					<option value="Draft">Draft</option>
					<option value="Pending">Pending</option>
					<option value="Approved" selected>Approved</option>
				  </select>
				</div>

				<div class="d-flex justify-content-between" style="margin-top:10px;">
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
						<button type="button" class="btn btn-primary">
							<i class="fa-solid fa-chevron-left"></i> Back
						</button>
					</a>
					<button class="btn btn-danger save disabled">
						<i class="fa-solid fa-save"></i> Save
					</button>
				</div>
			</div>
		</form>

		<div class="modal fade" id="staticBackdrop" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
		  <div class="modal-dialog">
		    <div class="modal-content">
		      <div class="modal-header">
		        <h5 class="modal-title" id="staticBackdropLabel">Currency Notes</h5>
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
		            <tr data-denomination="200">
		              <td class="px-5">GH&cent;200</td>
		              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
		              <td><input type="text" class="form-control p-1 volume-input" placeholder="Volume" readonly></td>
		            </tr>
		            <tr data-denomination="100">
		              <td class="px-5">GH&cent;100</td>
		              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
		              <td><input type="text" class="form-control p-1 volume-input" placeholder="Volume" readonly></td>
		            </tr>
		             <tr data-denomination="50">
		              <td class="px-5">GH&cent;50</td>
		              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
		              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
		            </tr>
		            <tr data-denomination="20">
		              <td class="px-5">GH&cent;20</td>
		              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
		              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
		            </tr>
		            <tr data-denomination="10">
		              <td class="px-5">GH&cent;10</td>
		              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
		              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
		            </tr>
		            <tr data-denomination="5">
		              <td class="px-5">GH&cent;5</td>
		              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
		              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
		            </tr>
		            <tr data-denomination="2">
		              <td class="px-5">GH&cent;2</td>
		              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
		              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
		            </tr>
		            <tr data-denomination="1">
		              <td class="px-5">GH&cent;1</td>
		              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
		              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
		            </tr>
		            <tr>
		              <td colspan="3">COINS</td>
		            </tr>
		            <tr data-denomination="0.5">
		              <td class="px-5">50p</td>
		              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
		              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
		            </tr>

		            <tr data-denomination="0.2">
		              <td class="px-5">20p</td>
		              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
		              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
		            </tr>

		            <tr data-denomination="0.1">
		              <td class="px-5">10p</td>
		              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
		              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
		            </tr>
		          </tbody>
		          <tfoot>
		            <tr>
		              <th class="px-5 bg-secondary text-warning">TOTAL</th>
		              <th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="total-count" style="border:none; " placeholder="Total count" readonly></th>
		              <th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="total-volume" style="border:none; " placeholder="Total volume" readonly></th>
		            </tr>
		            <tr>
		              <th colspan="3" class="text-center">
		                <p class="text-info mt-2 mb-1" id="expected-currency-total">Required total: 0.00</p>
		                <p class="text-danger mt-2" id="variance-display" style="display: none;"></p>
		              </th>
		            </tr>
		          </tfoot>
		        </table>
		      </div>

		      <div class="modal-footer">
		        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
		        <button type="button" class="btn btn-danger reset" >Reset</button>
		        <button type="button" class="btn btn-warning restore" >Restore</button>
		        <button type="button" class="btn btn-primary submit">Confirm</button>
		      </div>
		    </div>
		  </div>
		</div>

<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>

<script>
	<?=get_app_image_cropper_inline_source()?>

	const oldNetworkValue = "<?=htmlspecialchars((string)old_value('network'), ENT_QUOTES, 'UTF-8')?>";
	const oldCashSourceValue = "<?=htmlspecialchars((string)old_value('cash_source'), ENT_QUOTES, 'UTF-8')?>";

	const sourceOptions = {
		"Withdrawal (CIT)": [
			{ value: "", text: "---Select---" },
			{ value: "GhQR", text: "GhQR" },
			{ value: "GCB", text: "GCB" },
			{ value: "Calbank", text: "Calbank" },
			{ value: "Ecobank", text: "Ecobank" },
			{ value: "Fidelity Bank", text: "Fidelity Bank [Account to Account]" },
		],
		"Withdrawal (AIT)": [
			{ value: "", text: "---Select---" },
			{ value: "ABSA Bank", text: "ABSA Bank" },
			{ value: "Access Bank", text: "Access Bank" },
			{ value: "Cal Bank", text: "Cal Bank" },
			{ value: "FBN", text: "FBN" },
			{ value: "Fidelity Bank", text: "Fidelity Bank" },
			{ value: "FNB", text: "FNB" },
			{ value: "GT Bank", text: "GT Bank" },
			{ value: "Apex Nafana Bank", text: "Nafana Rural Bank" },
		],
	};

	const transactionType = document.getElementById('transaction_type');
	const sourceSelect = document.getElementById('network');
	const amountInput = document.getElementById('amount');
	const amountWithdrawnInput = document.getElementById('amount_withdrawn');
	const amountPayableInput = document.getElementById('amount_payable');
	const chargingTypeSelect = document.getElementById('charging_type');
	const chargeInput = document.getElementById('charge');
	const saveButton = document.querySelector('.save');
	const denosInput = document.getElementById('denos');
	const transactionStatusHidden = document.getElementById('transaction_status_hidden');
	const transactionStatusSelect = document.getElementById('transaction_status_select');
	const AIT_WITHDRAWAL_MAX = 5000;
	const LIMIT_EPSILON = 0.001;
	const existingAitWithdrawalAmount = <?= json_encode(!empty($row) ? (float)($row->amount_withdrawn ?? 0) : 0) ?>;
	const amountWithdrawnLimitError = document.getElementById('amount-withdrawn-limit-error');
	const amountWithdrawnLimitHint = document.getElementById('amount-withdrawn-limit-hint');
	const amountWithdrawnLegacyHint = document.querySelector('.amount_withdrawn small.text-muted:not(#amount-withdrawn-limit-hint)');

	(function(){
		const modalEl = document.getElementById('staticBackdrop');
		if (!modalEl) return;

		const rows = Array.from(modalEl.querySelectorAll('tbody tr[data-denomination]'));
		const totalCountEl = document.getElementById('total-count');
		const totalVolumeEl = document.getElementById('total-volume');
		const varianceEl = document.getElementById('variance-display');
		const expectedTotalEl = document.getElementById('expected-currency-total');
		const resetBtn = modalEl.querySelector('button.reset, .reset');
		const restoreBtn = modalEl.querySelector('button.restore, .restore');
		const confirmBtn = modalEl.querySelector('button.submit, .submit');

		const STORAGE_KEY = 'agency_withdrawal_currency_notes_v1';

		const restoreFromHiddenDenos = () => {
			const raw = denosInput ? String(denosInput.value || '').trim() : '';
			if (!raw) return false;
			let parsed;
			try { parsed = JSON.parse(raw); } catch (e) { parsed = null; }
			if (!Array.isArray(parsed) || parsed.length === 0) return false;
			const byDenom = new Map();
			parsed.forEach((row) => {
				const denom = String(row.denomination ?? row.denom ?? '').trim();
				if (!denom) return;
				const count = String(row.count ?? '').trim();
				if (!count) return;
				byDenom.set(denom, count);
			});
			if (byDenom.size === 0) return false;
			rows.forEach((tr) => {
				const denom = String(tr.getAttribute('data-denomination') ?? '');
				const countInput = tr.querySelector('input.count-input');
				if (countInput && byDenom.has(denom)) {
					countInput.value = byDenom.get(denom);
				}
			});
			compute();
			return true;
		};

		const parseNum = (v) => {
			const n = Number(String(v ?? '').trim());
			return Number.isFinite(n) ? n : 0;
		};

		const compute = () => {
			let totalCount = 0;
			let totalVolume = 0;
			const denos = [];

			rows.forEach((tr) => {
				const denom = parseNum(tr.getAttribute('data-denomination'));
				const countInput = tr.querySelector('input.count-input');
				const volumeInput = tr.querySelector('input.volume-input');
				const count = parseNum(countInput ? countInput.value : 0);
				const volume = denom * count;

				if (volumeInput) {
					volumeInput.value = volume ? volume.toFixed(2) : '';
				}

				totalCount += count;
				totalVolume += volume;

				if (count > 0) {
					denos.push({
						currency: 'GHS',
						denomination: denom,
						count,
						volume,
					});
				}
			});

			if (totalCountEl) totalCountEl.value = totalCount ? String(totalCount) : '';
			if (totalVolumeEl) totalVolumeEl.value = totalVolume ? totalVolume.toFixed(2) : '';
			if (expectedTotalEl) {
				const expected = (typeof isGhqrCit === 'function' && isGhqrCit())
					? parseNum(amountPayableInput ? amountPayableInput.value : 0)
					: parseNum(amountWithdrawnInput ? amountWithdrawnInput.value : 0);
				expectedTotalEl.textContent = `Required total: ${expected.toFixed(2)}`;
			}
			setVariance(totalVolume);

			if (denosInput) {
				denosInput.value = JSON.stringify(denos);
			}
		};

		const clearAll = () => {
			rows.forEach((tr) => {
				const countInput = tr.querySelector('input.count-input');
				const volumeInput = tr.querySelector('input.volume-input');
				if (countInput) countInput.value = '';
				if (volumeInput) volumeInput.value = '';
			});
			if (denosInput) denosInput.value = '';
			if (totalCountEl) totalCountEl.value = '';
			if (totalVolumeEl) totalVolumeEl.value = '';
			setVariance(0);
		};

		const saveState = () => {
			try {
				const state = rows.map((tr) => {
					const denom = String(tr.getAttribute('data-denomination') ?? '');
					const countInput = tr.querySelector('input.count-input');
					return { denom, count: countInput ? String(countInput.value ?? '') : '' };
				});
				localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
			} catch (e) {
				// ignore
			}
		};

		const restoreState = () => {
			let state;
			try {
				state = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
			} catch (e) {
				state = null;
			}
			if (!Array.isArray(state)) return;

			const byDenom = new Map(state.map(x => [String(x.denom), String(x.count ?? '')]));
			rows.forEach((tr) => {
				const denom = String(tr.getAttribute('data-denomination') ?? '');
				const countInput = tr.querySelector('input.count-input');
				if (countInput && byDenom.has(denom)) {
					countInput.value = byDenom.get(denom);
				}
			});
			compute();
		};

		const setVariance = (totalVolume) => {
			if (!varianceEl) return;
			const isGhqr = (typeof isGhqrCit === 'function') && isGhqrCit();
			const compareAmount = isGhqr
				? parseNum(amountPayableInput ? amountPayableInput.value : 0)
				: parseNum(amountWithdrawnInput ? amountWithdrawnInput.value : 0);
			if (!compareAmount || compareAmount <= 0) {
				varianceEl.style.display = 'none';
				varianceEl.textContent = '';
				return;
			}
			const diff = totalVolume - compareAmount;
			if (Math.abs(diff) < 0.001) {
				varianceEl.style.display = 'none';
				varianceEl.textContent = '';
				return;
			}

			varianceEl.style.display = 'block';
			varianceEl.textContent = `Required total: ${compareAmount.toFixed(2)} | Entered total: ${totalVolume.toFixed(2)} | Variance: ${diff > 0 ? '+' : ''}${diff.toFixed(2)}`;
		};

		rows.forEach((tr) => {
			const countInput = tr.querySelector('input.count-input');
			if (!countInput) return;

			countInput.addEventListener('input', function(){
				compute();
				saveState();
			});
		});

		if (resetBtn) {
			resetBtn.addEventListener('click', function(){
				clearAll();
				try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
			});
		}

		if (restoreBtn) {
			restoreBtn.addEventListener('click', function(){
				restoreState();
			});
		}

		try{
			modalEl.addEventListener('show.bs.modal', function(){
				if (!restoreFromHiddenDenos()) {
					restoreState();
				}
			});
		}catch(e){}

		if (confirmBtn) {
			confirmBtn.addEventListener('click', function(e){
				if (e && typeof e.preventDefault === 'function') e.preventDefault();
				if (e && typeof e.stopPropagation === 'function') e.stopPropagation();
				compute();
				const isGhqr = (typeof isGhqrCit === 'function') && isGhqrCit();
				const expectedTotal = isGhqr
					? parseNum(amountPayableInput ? amountPayableInput.value : 0)
					: parseNum(amountWithdrawnInput ? amountWithdrawnInput.value : 0);
				const enteredTotal = parseNum(totalVolumeEl ? totalVolumeEl.value : 0);
				if(expectedTotal <= 0 || Math.abs(enteredTotal - expectedTotal) > 0.001){
					if(varianceEl){
						varianceEl.style.display = 'block';
						varianceEl.classList.add('text-danger');
						varianceEl.textContent = `Currency total must equal ${expectedTotal.toFixed(2)}. Entered total: ${enteredTotal.toFixed(2)}.`;
					}
					if(amountInput) amountInput.value = '';
					return;
				}
				if(varianceEl) varianceEl.classList.remove('text-danger');
				if (amountInput) {
					const paidOutValue = totalVolumeEl ? (totalVolumeEl.value || '') : '';
					amountInput.value = paidOutValue;
					amountInput.dispatchEvent(new Event('input', { bubbles: true }));
				}
				validateForm();
				try {
					if (window.bootstrap && window.bootstrap.Modal) {
						const instance = window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
						instance.hide();
					}
				} catch (err) {
					// ignore
				}
			});
		}

		if (amountWithdrawnInput) {
			amountWithdrawnInput.addEventListener('input', function(){
				compute();
			});
		}

		if (!restoreFromHiddenDenos()) {
			restoreState();
		}
		compute();
	})();
	function updateSourceOptions() {
		const transactionTypeValue = transactionType.value;
		const existingValue = sourceSelect.value;
		sourceSelect.innerHTML = "";

		if (sourceOptions[transactionTypeValue]) {
			sourceOptions[transactionTypeValue].forEach(option => {
				const opt = document.createElement('option');
				opt.value = option.value;
				opt.textContent = option.text;
				sourceSelect.appendChild(opt);
			});
		}

		if (oldNetworkValue) {
			const hasExisting = Array.from(sourceSelect.options).some(o => o.value === oldNetworkValue);
			if (hasExisting) {
				sourceSelect.value = oldNetworkValue;
			}
		}

		showFields();
		calculateAmountPayable();
		validateForm();
	}

	function isGhqrCit() {
		const provider = (sourceSelect.value || '').trim();
		return transactionType.value === 'Withdrawal (CIT)' && ['GhQR', 'GCB'].includes(provider);
	}

	function syncTransactionStatusUi() {
		const useStatusSelect = isGhqrCit();
		const statusWrapper = document.querySelector('.transaction_status');
		if (statusWrapper) {
			statusWrapper.classList.toggle('d-none', !useStatusSelect);
		}
		if (transactionStatusSelect) {
			transactionStatusSelect.disabled = !useStatusSelect;
			if (useStatusSelect) {
				if (!transactionStatusSelect.value) {
					transactionStatusSelect.value = transactionStatusHidden && transactionStatusHidden.value ? transactionStatusHidden.value : 'Approved';
				}
				if (transactionStatusHidden) {
					transactionStatusHidden.value = transactionStatusSelect.value;
					transactionStatusHidden.disabled = true;
				}
			} else if (transactionStatusHidden) {
				transactionStatusHidden.disabled = false;
				if (!transactionStatusHidden.value) {
					transactionStatusHidden.value = 'Approved';
				}
			}
		} else if (transactionStatusHidden) {
			transactionStatusHidden.disabled = false;
		}
	}

	function isAitWithdrawal() {
		return transactionType.value === 'Withdrawal (AIT)';
	}

	function validateAitWithdrawalLimit() {
		if (!amountWithdrawnInput) {
			return true;
		}

		const amountWithdrawn = parseFloat(amountWithdrawnInput.value) || 0;
		const isLegacyOverLimitAitWithdrawal = existingAitWithdrawalAmount > (AIT_WITHDRAWAL_MAX + LIMIT_EPSILON);
		const isReducingOrUnchangedLegacyAmount = isLegacyOverLimitAitWithdrawal && amountWithdrawn <= (existingAitWithdrawalAmount + LIMIT_EPSILON);
		const exceedsAitLimit = isAitWithdrawal() && amountWithdrawn > (AIT_WITHDRAWAL_MAX + LIMIT_EPSILON) && !isReducingOrUnchangedLegacyAmount;
		const message = exceedsAitLimit
			? 'Maximum allowed amount for Withdrawal (AIT) is GHS 5,000.00. Enter GHS 5,000.00 or less.'
			: '';

		amountWithdrawnInput.setCustomValidity(message);
		if (amountWithdrawnLimitError) {
			amountWithdrawnLimitError.textContent = message;
			amountWithdrawnLimitError.style.display = message ? 'block' : 'none';
		}

		return !exceedsAitLimit;
	}

	function syncAitWithdrawalLimitUi() {
		if (!amountWithdrawnInput) {
			return;
		}

		const isLegacyOverLimitAitWithdrawal = existingAitWithdrawalAmount > (AIT_WITHDRAWAL_MAX + LIMIT_EPSILON);
		if (isAitWithdrawal() && !isLegacyOverLimitAitWithdrawal) {
			amountWithdrawnInput.setAttribute('max', String(AIT_WITHDRAWAL_MAX));
		} else {
			amountWithdrawnInput.removeAttribute('max');
		}

		if (amountWithdrawnLimitHint) {
			amountWithdrawnLimitHint.style.display = isAitWithdrawal() ? 'block' : 'none';
		}
		if (amountWithdrawnLegacyHint) {
			amountWithdrawnLegacyHint.style.display = 'none';
		}
		if (!isAitWithdrawal()) {
			amountWithdrawnInput.setCustomValidity('');
			if (amountWithdrawnLimitError) {
				amountWithdrawnLimitError.textContent = '';
				amountWithdrawnLimitError.style.display = 'none';
			}
		}
	}

	function getChargeMode() {
		const mode = (chargingTypeSelect && chargingTypeSelect.value) ? chargingTypeSelect.value : '';
		return mode || 'minus';
	}

	function roundGhqrFee(value) {
		const x = Number(value || 0);
		if (!x || x <= 0) return 0;
		const base = Math.floor(x);
		const frac = x - base;
		if (frac === 0) return base;
		if (frac <= 0.5) return base + 0.5;
		return base + 1.0;
	}

	function applyGhqrModeUi() {
		if (!isGhqrCit()) return;
		const mode = getChargeMode();
		if (mode === 'plus') {
			amountPayableInput.readOnly = false;
			amountPayableInput.disabled = false;
			amountPayableInput.removeAttribute('readonly');
			amountPayableInput.removeAttribute('disabled');
			amountPayableInput.classList.remove('disabled');
			amountPayableInput.style.pointerEvents = 'auto';

			amountWithdrawnInput.readOnly = false;
			amountWithdrawnInput.disabled = false;
			amountWithdrawnInput.removeAttribute('readonly');
			amountWithdrawnInput.removeAttribute('disabled');
			amountWithdrawnInput.classList.remove('disabled');
			amountWithdrawnInput.style.pointerEvents = 'auto';
		} else {
			amountPayableInput.readOnly = true;
			amountPayableInput.setAttribute('readonly', 'readonly');
			amountPayableInput.classList.add('disabled');
			amountPayableInput.style.pointerEvents = 'none';

			amountWithdrawnInput.readOnly = false;
			amountWithdrawnInput.disabled = false;
			amountWithdrawnInput.removeAttribute('readonly');
			amountWithdrawnInput.removeAttribute('disabled');
		}
	}

	function solveGhqrPayableFromWithdrawn(withdrawnAmount) {
		let withdrawn = Number(withdrawnAmount || 0);
		if (!withdrawn || withdrawn <= 0) {
			return { payable: 0, fee: 0 };
		}

		const computeFee = function(baseAmount){
			let computed = roundGhqrFee((Number(baseAmount || 0)) * 0.01);
			if ((Number(baseAmount || 0)) > 2000) {
				computed = Math.min(computed, 20);
			}
			return computed;
		};

		// In plus mode:
		// withdrawn = payable + roundGhqrFee(payable * 0.01)
		// Solve by fixed-point iteration (few steps is enough due to small fee).
		let payable = withdrawn;
		for (let i = 0; i < 6; i++) {
			const fee = computeFee(payable);
			payable = withdrawn - fee;
			if (payable < 0) payable = 0;
		}
		const fee = computeFee(payable);
		return { payable, fee };
	}

	function calculateGhqrAmounts() {
		if (!isGhqrCit()) return;
		applyGhqrModeUi();
		const mode = getChargeMode();
		let withdrawn = parseFloat(amountWithdrawnInput.value) || 0;
		let payable = parseFloat(amountPayableInput.value) || 0;
		let fee = 0;

		const computeFee = function(baseAmount){
			let computed = roundGhqrFee((Number(baseAmount || 0)) * 0.01);
			if ((Number(baseAmount || 0)) > 2000) {
				computed = Math.min(computed, 20);
			}
			return computed;
		};

		if (mode === 'plus') {
			// In plus mode, keep amount_withdrawn editable and treat it as the TOTAL.
			// Solve payable and fee from withdrawn without overwriting the user's input.
			withdrawn = parseFloat(amountWithdrawnInput.value) || 0;
			const solved = solveGhqrPayableFromWithdrawn(withdrawn);
			payable = Number(solved.payable || 0);
			fee = Number(solved.fee || 0);
			amountPayableInput.value = payable ? payable.toFixed(2) : '';
		} else {
			withdrawn = parseFloat(amountWithdrawnInput.value) || 0;
			fee = computeFee(withdrawn);
			payable = withdrawn - fee;
			amountPayableInput.value = payable.toFixed(2);
		}

		if (chargeInput) {
			chargeInput.value = fee.toFixed(2);
		}
		validateForm();
	}

	function calculateAmountPayable() {
		const withdrawn = parseFloat(amountWithdrawnInput.value) || 0;
		if (isGhqrCit()) {
			calculateGhqrAmounts();
			return;
		}

		if (chargeInput) {
			chargeInput.value = '0';
		}
		if (amountPayableInput) {
			amountPayableInput.value = withdrawn ? withdrawn.toFixed(2) : '';
		}
		validateForm();
	}

	const sofOptions = {

		"Telco": [
			{ value: "", text: "---Select---" },
			{ value: "AT", text: "AT" },
			{ value: "MTN-SUBSCRIBER", text: "MTN-SUBSCRIBER" },
			{ value: "MTN-AGENT", text: "MTN-AGENT" },
			{ value: "MTN-MERCHANT", text: "MTN-MERCHANT" },
			{ value: "TELECEL-SUBSCRIBER", text: "TELECEL-SUBSCRIBER" },
			{ value: "G-MONEY", text: "G-MONEY" },
			{ value: "GHANAPAY", text: "GHANAPAY" },
			{ value: "ZEEPAY", text: "ZEEPAY" },
			{ value: "OTHER MERCHANTS", text: "OTHER MERCHANTS" },
		],

		"Bank": [
			{ value: "", text: "---Select---" },
			{ value: "ABSA", text: "ABSA" },
			{ value: " Access Bank", text: " Access Bank" },
			{ value: "ADB", text: "ADB" },
			{ value: "APEX BANK", text: "APEX BANK" },
			{ value: "Bank of Africa", text: "Bank of Africa" },
			{ value: "CBG", text: "CBG" },
			{ value: "Cal Bank", text: "Cal Bank" },
			{ value: "Ecobank", text: "Ecobank" },
			{ value: "Fidelity", text: "Fidelity" },
			{ value: "First Atlantic Bank", text: "First Atlantic Bank" },
			{ value: "First Bank Ghana", text: "First Bank Ghana" },
			{ value: "FBN", text: "FBN" },
			{ value: "FNB", text: "FNB" },
			{ value: "Gtbank", text: "Gtbank" },
			{ value: "GCB", text: "GCB" },
			{ value: "NIB", text: "NIB" },
			{ value: "Republic Bank", text: "Republic Bank" },
			{ value: "OmniBSIC Bank", text: "OmniBSIC Bank" },
			{ value: "Society General", text: "Society General" },
			{ value: " Stanbic Bank", text: " Stanbic Bank" },
			{ value: "Prudential Bank", text: "Prudential Bank" },
			{ value: "Standard Charted", text: "Standard Charted" },
			{ value: "Zenit Bank", text: "Zenit Bank" },
			{ value: "umb", text: "umb" },
			{ value: "Zenit Bank", text: "Zenit Bank" },
		],
	};

	const sof_optionSelect = document.getElementById('sof_option');
	const sofSelect = document.getElementById('sof');
	const sofDiv = document.querySelector('.sof');
	const categorySelect = document.getElementById('category');

	function updateSofOptions() {
		const sofSelectValue = sof_optionSelect.value;
		sofSelect.innerHTML = "";

		if (sofOptions[sofSelectValue]) {
			sofOptions[sofSelectValue].forEach(option => {
				const opt = document.createElement('option');
				opt.value = option.value;
				opt.textContent = option.text;
				sofSelect.appendChild(opt);
			});
		}
		if (oldCashSourceValue) {
			const hasExisting = Array.from(sofSelect.options).some(o => o.value === oldCashSourceValue);
			if (hasExisting) {
				sofSelect.value = oldCashSourceValue;
			}
		}
	}

	sof_optionSelect.addEventListener('change', function(){
		updateSofOptions();
		validateForm();
	});

	if (chargingTypeSelect) {
		chargingTypeSelect.addEventListener('change', function(){
			applyGhqrModeUi();
			calculateGhqrAmounts();
			applyGhqrModeUi();
			validateForm();
		});
	}

	sourceSelect.addEventListener('change', function(){
		showFields();
		calculateAmountPayable();
		validateForm();
	});

	categorySelect.addEventListener('change', function(){
		showFields();
		validateForm();
	});

	function showFields() {
		const transactionTypeValue = transactionType.value;
		const sourceValue = sourceSelect.value;
		const category = document.querySelector('.category');
		const payment_medium = document.querySelector('.payment_medium');
		const amountWithdrawn = document.querySelector('.amount_withdrawn');
		const amount_payable = document.querySelector('.amount_payable');
		const charging_type = document.querySelector('.charging_type');
		const sof_option = document.querySelector('.sof_option');
		const sof = document.querySelector('.sof');
		const phone = document.querySelector('.phone');
		const otherPartyNumber = document.querySelector('.otherPartyNumber');
		const phoneLabel = document.querySelector('.phoneLabel');
		const account_number = document.querySelector('.account_number');
		const amountMatchError = document.getElementById('amount-match-error');
		const transactionImageWrapper = document.getElementById('transaction_image_wrapper');
		const amountPaidOutWrapper = document.getElementById('amount_paid_out_wrapper');
		const statusWrapper = document.querySelector('.transaction_status');
		const actionWrapper = saveButton ? saveButton.closest('.d-flex') : null;
		const isGcbService = sourceValue === 'GCB';
		const isGhqrLikeCit = isGhqrCit();

		// Hide all fields initially
		category.classList.add('d-none');
		payment_medium.classList.add('d-none');
		amountWithdrawn.classList.add('d-none');
		sof_option.classList.add('d-none');
		sof.classList.add('d-none');
		amount_payable.classList.add('d-none');
		if (charging_type) charging_type.classList.add('d-none');
		otherPartyNumber.classList.add('d-none');
		account_number.classList.add('d-none');
		if (phone) phone.classList.add('d-none');
		if (transactionImageWrapper) transactionImageWrapper.style.order = '';
		if (amountPaidOutWrapper) amountPaidOutWrapper.style.order = '';
		if (statusWrapper) statusWrapper.style.order = '';
		if (phone) phone.style.order = '';
		if (amountWithdrawn) amountWithdrawn.style.order = '';
		if (amount_payable) amount_payable.style.order = '';
		if (charging_type) charging_type.style.order = '';
		if (actionWrapper) actionWrapper.style.order = '99';

		// Show fields based on selected transaction type
		if (transactionTypeValue === 'Withdrawal (CIT)') {
			amountWithdrawn.classList.remove('d-none');
			amountMatchError.style.display = 'none';

			if (isGcbService) {
				phone.classList.remove('d-none');
				phoneLabel.innerText = "Customer's phone number";
				if (charging_type) charging_type.classList.remove('d-none');
				amount_payable.classList.remove('d-none');
				if (transactionImageWrapper) transactionImageWrapper.style.order = '5';
				if (amountPaidOutWrapper) amountPaidOutWrapper.style.order = '6';
				if (statusWrapper) statusWrapper.style.order = '7';
				if (actionWrapper) actionWrapper.style.order = '8';
				if (phone) phone.style.order = '1';
				if (charging_type) charging_type.style.order = '2';
				if (amountWithdrawn) amountWithdrawn.style.order = '3';
				if (amount_payable) amount_payable.style.order = '4';
			} else if (isGhqrLikeCit) {
				category.classList.remove('d-none');
				payment_medium.classList.remove('d-none');
				if (charging_type) charging_type.classList.remove('d-none');
				sof_option.classList.remove('d-none');
				sof.classList.remove('d-none');
				amount_payable.classList.remove('d-none');
				account_number.classList.remove('d-none');
				if (categorySelect.value === 'Self') {
					phoneLabel.innerText = "Customer's Number";
					phone.classList.remove('d-none');
				} else if (categorySelect.value === '3rd Party') {
					phoneLabel.innerText = "Customer's Number";
					phone.classList.remove('d-none');
					otherPartyNumber.classList.remove('d-none');
				}
			}

		} else if (transactionTypeValue === 'Withdrawal (AIT)') {
			amountWithdrawn.classList.remove('d-none');
			amountMatchError.style.display = 'none';
			if (phone) phone.classList.remove('d-none');
		}
		syncAitWithdrawalLimitUi();
		syncTransactionStatusUi();
		validateForm();
	}

	function validateForm() {
		const transactionTypeValue = transactionType.value;
		const amountWithdrawnValue = amountWithdrawnInput.value;
		const amountPaidOutValue = amountInput.value;
		const isGhQR_CIT = isGhqrCit();
		const isAitAmountValid = validateAitWithdrawalLimit();
	
		// Check for basic required fields (wallets are now derived server-side)
		if (!transactionTypeValue || !amountWithdrawnValue || !amountPaidOutValue || !isAitAmountValid) {
			saveButton.classList.add('disabled');
			return false;
		}

		// Ensure amounts match with a small tolerance
		let amountToMatch = parseFloat(amountWithdrawnValue);

		if (isGhQR_CIT) {
			amountToMatch = parseFloat(amountPayableInput.value) || 0;
		}

		if (Math.abs(amountToMatch - parseFloat(amountPaidOutValue)) > LIMIT_EPSILON) {
			saveButton.classList.add('disabled');
			return false;
		}

		// If all checks pass, enable the button
		saveButton.classList.remove('disabled');
		return true;
	}

	amountWithdrawnInput.addEventListener('input', calculateAmountPayable);
	amountWithdrawnInput.addEventListener('input', validateForm);
	amountWithdrawnInput.addEventListener('input', syncAitWithdrawalLimitUi);
	
	amountInput.addEventListener('input', validateForm);
	
	if (transactionStatusSelect) {
		transactionStatusSelect.addEventListener('change', function(){
			if (transactionStatusHidden) {
				transactionStatusHidden.value = transactionStatusSelect.value;
			}
			validateForm();
		});
	}

	document.getElementById('phone').addEventListener('input', validateForm);

	document.getElementById('other_phone').addEventListener('input', validateForm);
	document.getElementById('account_number').addEventListener('input', validateForm);
	document.getElementById('amount_payable').addEventListener('input', validateForm);
	document.getElementById('sof').addEventListener('change', validateForm);
	document.getElementById('payment_medium').addEventListener('change', validateForm);

	// Prefill dependent selects/fields on load (after validation errors)
	updateSourceOptions();
	updateSofOptions();
	showFields();
	syncAitWithdrawalLimitUi();
	calculateAmountPayable();
	validateForm();

	(function(){
		if(!window.AppImageCropper || typeof window.AppImageCropper.bindPreviewCard !== 'function') return;

		window.AppImageCropper.bindPreviewCard({
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
</script>

<?php if(user_can('add_cashpickup') || (!empty($is_edit_mode) && (user_can('is_admin') || user_can('is_manager')))): ?>
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
		.js-hide-payout-form .js-side-pay{ display:none !important; }
		.customer-search-results{
			margin-top: .5rem;
			border: 1px solid rgba(255,255,255,0.08);
			border-radius: 8px;
			background: rgba(15, 23, 42, 0.55);
			max-height: 260px;
			overflow-y: auto;
		}
		.customer-search-option{
			width: 100%;
			border: 0;
			background: transparent;
			color: inherit;
			padding: .85rem 1rem;
			text-align: left;
			border-bottom: 1px solid rgba(255,255,255,0.06);
		}
		.customer-search-option:last-child{
			border-bottom: 0;
		}
		.customer-search-option:hover,
		.customer-search-option:focus{
			background: rgba(59, 130, 246, 0.12);
			outline: none;
		}
		.customer-search-option .meta{
			display: block;
			font-size: .82rem;
			color: rgba(229,231,235,0.72);
		}
		.customer-registration-notice{
			border-radius: 8px;
			border: 1px solid rgba(250, 204, 21, 0.35);
			background: rgba(120, 53, 15, 0.35);
			color: #fef3c7;
		}
	</style>

	<?php
		$prefillCustomer = null;
		$prefillCustomerId = (int)($_GET['customer_id'] ?? 0);
		$prefillPhone = trim((string)($_GET['phone'] ?? ''));
		$prefillIdNumber = trim((string)($_GET['id_number'] ?? ''));
		$prefillName = trim((string)($_GET['full_name'] ?? ''));
		$prefillIdType = trim((string)($_GET['id_type'] ?? ''));
		$prefillLocation = trim((string)($_GET['location'] ?? ''));
		try {
			require_once plugin_path('helpers/customer-ledger.php');
			$prefillDb = new \Core\Database;
			\CashpickupLedger\ensure_cashpickup_customer_schema($prefillDb);
			if ($prefillCustomerId > 0) {
				$rows = $prefillDb->query(
					"SELECT * FROM cashpickup_customers
					 WHERE deleted = 0
					 AND id = :id
					 AND (approval_status IS NULL OR approval_status = '' OR LOWER(approval_status) = 'approved')
					 LIMIT 1",
					['id' => $prefillCustomerId]
				);
				if (!empty($rows)) {
					$prefillCustomer = $rows[0];
				}
			} elseif ($prefillPhone !== '' || $prefillIdNumber !== '') {
				$prefillCustomer = \CashpickupLedger\find_customer($prefillDb, $prefillIdNumber, $prefillPhone);
			}
		} catch (\Throwable $e) {
			$prefillCustomer = null;
		}

		if ($prefillCustomer) {
			if (empty($_POST['customer_id'])) $_POST['customer_id'] = (string)($prefillCustomer->id ?? '');
			if (!empty($prefillCustomer->full_name) && empty($_POST['sender_details'])) $_POST['sender_details'] = (string)$prefillCustomer->full_name;
			if (!empty($prefillCustomer->phone_number) && empty($_POST['phone_number'])) $_POST['phone_number'] = (string)$prefillCustomer->phone_number;
			if (!empty($prefillCustomer->id_type) && empty($_POST['customer_id_type'])) $_POST['customer_id_type'] = (string)$prefillCustomer->id_type;
			if (!empty($prefillCustomer->id_number) && empty($_POST['customer_id_number'])) $_POST['customer_id_number'] = (string)$prefillCustomer->id_number;
			if (!empty($prefillCustomer->location) && empty($_POST['customer_location'])) $_POST['customer_location'] = (string)$prefillCustomer->location;
		} else {
			if ($prefillCustomerId > 0 && empty($_POST['customer_id'])) $_POST['customer_id'] = (string)$prefillCustomerId;
			if ($prefillName !== '' && empty($_POST['sender_details'])) $_POST['sender_details'] = $prefillName;
			if ($prefillPhone !== '' && empty($_POST['phone_number'])) $_POST['phone_number'] = $prefillPhone;
			if ($prefillIdType !== '' && empty($_POST['customer_id_type'])) $_POST['customer_id_type'] = $prefillIdType;
			if ($prefillIdNumber !== '' && empty($_POST['customer_id_number'])) $_POST['customer_id_number'] = $prefillIdNumber;
			if ($prefillLocation !== '' && empty($_POST['customer_location'])) $_POST['customer_location'] = $prefillLocation;
		}
		$selectedCustomerType = strtolower(trim((string)old_value('customer_type')));
		$hasExistingCustomerPrefill = ($prefillCustomer || (string)old_value('customer_id') !== '');
		if (!in_array($selectedCustomerType, ['existing', 'new'], true)) {
			$selectedCustomerType = $hasExistingCustomerPrefill ? 'existing' : '';
		}
		$existingCustomerSearchValue = '';
		if ($prefillCustomer) {
			$existingCustomerSearchValue = trim((string)($prefillCustomer->full_name ?? ''));
			if (!empty($prefillCustomer->phone_number)) {
				$existingCustomerSearchValue .= ($existingCustomerSearchValue !== '' ? ' - ' : '') . (string)$prefillCustomer->phone_number;
			}
		} elseif ((string)old_value('customer_id') !== '') {
			$existingCustomerSearchValue = trim((string)old_value('sender_details'));
			if ((string)old_value('phone_number') !== '') {
				$existingCustomerSearchValue .= ($existingCustomerSearchValue !== '' ? ' - ' : '') . (string)old_value('phone_number');
			}
		}
		$receiveExistingReadonly = (empty($payout_mode) && $selectedCustomerType === 'existing' && $hasExistingCustomerPrefill);
		$payoutPrefillReadonly = !empty($payout_mode);
		$payoutPrefillHiddenClass = !empty($payout_mode) ? ' d-none js-payout-prefill-hidden' : '';
	?>

	<?php $hidePayoutForm = (!empty($payout_mode) && isset($remaining_balance) && (float)$remaining_balance <= 0.00001); ?>
	<form method="post" enctype="multipart/form-data">
		<div class="row g-3 col-md-10 col-lg-10 mx-auto shadow p-4 rounded <?=$hidePayoutForm ? 'js-hide-payout-form' : ''?>">
			<?=csrf()?>
			<input type="hidden" name="customer_id" id="customer_id" value="<?=esc(old_value('customer_id'))?>">

			<h4 class="text-center">
				<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
				Cash Pick-up Transactions Booklet
			</h4>

			<div class="mb-3 col-md-12 col-lg-6<?=$payoutPrefillHiddenClass?>">
				<label for="transaction_side" class="form-label">Pickup Side</label>
				<?php if(!empty($payout_mode)): ?>
					<input type="hidden" name="transaction_side" id="transaction_side" value="pay">
					<input class="form-control" type="text" value="Pay to Customer" readonly>
				<?php else: ?>
					<input type="hidden" name="transaction_side" id="transaction_side" value="receive">
					<input class="form-control" type="text" value="Receive from Customer" readonly>
				<?php endif; ?>
				<?php if(!empty($errors['transaction_side'])): ?>
					<small class="text-danger"><?=$errors['transaction_side']?></small>
				<?php endif ?>
			</div>

			<?php if(empty($payout_mode)): ?>
				<div class="mb-3 col-md-12 col-lg-6 js-side-receive" id="customerTypeWrap">
					<label for="customer_type" class="form-label">Customer type</label>
					<select class="form-select" name="customer_type" id="customer_type">
						<option value="" <?=$selectedCustomerType === '' ? 'selected' : ''?>>---Select---</option>
						<option value="existing" <?=$selectedCustomerType === 'existing' ? 'selected' : ''?>>Existing customer</option>
						<option value="new" <?=$selectedCustomerType === 'new' ? 'selected' : ''?>>New customer</option>
					</select>
				</div>
			<?php endif; ?>


			<div class="mb-3 col-md-12 col-lg-6 js-side-receive" id="existingCustomerSearchWrap" style="display:none;">
				<label for="existing_customer_search" class="form-label">Search existing customer</label>
				<input type="search" class="form-control" id="existing_customer_search" placeholder="Search approved customer by name or phone number" value="<?=esc($existingCustomerSearchValue)?>" autocomplete="off">
				<div class="customer-search-results" id="existingCustomerSearchResults" style="display:none;"></div>
				<small class="text-muted">Search approved customers and select one to auto-fill the customer details.</small>
			</div>

			<div class="mb-3 col-md-12 js-side-both col-lg-3<?=$payoutPrefillHiddenClass?>">
				<label for="source" class="form-label">Source</label>
				<?php if(!empty($payout_mode)): ?>
					<input class="form-control" type="text" value="<?=old_value('source')?>" readonly>
					<input type="hidden" name="source" id="source" value="<?=old_value('source')?>">
				<?php else: ?>
					<?php if(user_can('is_manager') || user_can('is_admin')): ?>
						<select class="form-select" name="source" id="source" required>
							<option value="">---Select---</option>
							<option <?=old_select('source','cash')?> value="cash">cash</option>
							<option <?=old_select('source','mtn')?> value="mtn">mtn</option>
							<option <?=old_select('source','at')?> value="at">at</option>
							<option <?=old_select('source','telecel')?> value="telecel">telecel</option>
							<option <?=old_select('source','bank')?> value="bank">bank</option>
						</select>
					<?php else: ?>
						<input class="form-control" type="text" value="Cash" readonly>
						<input type="hidden" name="source" id="source" value="Cash">
					<?php endif; ?>
				<?php endif; ?>
				<?php if(!empty($errors['source'])): ?>
					<small class="text-danger"><?=$errors['source']?></small>
				<?php endif ?>
			</div>

			<div class="mb-3 col-12 col-md-6 js-side-both col-lg-3<?=$payoutPrefillHiddenClass?>">
				<label for="amount_involved" class="form-label">Amount involved</label>
				<?php if(empty($payout_mode) && (user_can('is_manager') || user_can('is_admin'))): ?>
					<label class="input-group js-side-receive" id="amountInvolvedReceiveWrap" style="display:none;">
						<i class="fa-solid fa-calculator input-group-text text-warning" id="amountInvolvedReceiveCalcIcon" style="display:none;"></i>
						<div class="input-group-text" id="amountInvolvedReceiveCurrencyPrefix">GHS</div>
						<input name="amount_involved" value="<?=old_value('amount_involved')?>" type="number" step="0.01" class="form-control" id="amount_involved" placeholder="Enter amount">
					</label>
				<?php else: ?>
					<label class="input-group js-side-receive" id="amountInvolvedReceiveWrap" data-bs-toggle="modal" data-bs-target="#cashPickupDenosModal" style="display:none;">
						<i class="fa-solid fa-calculator input-group-text text-warning"></i>
						<input name="amount_involved" value="<?=old_value('amount_involved')?>" type="number" step="0.01" class="form-control" id="amount_involved" placeholder="System generated" readonly>
					</label>
				<?php endif; ?>
				<div class="input-group js-side-pay" style="display:none;">
					<div class="input-group-text">GHS</div>
					<input value="<?=old_value('amount_involved')?>" type="number" step="0.01" class="form-control" id="amount_involved_pay" placeholder="Auto populated" readonly>
				</div>
				<?php if(!empty($errors['amount_involved'])): ?>
					<small class="text-danger"><?=$errors['amount_involved']?></small>
				<?php endif ?>
				<small class="text-danger"><?=(string)($errors['denos_json'] ?? '')?></small>
			</div>

			<?php if(empty($payout_mode) && (user_can('is_manager') || user_can('is_admin'))): ?>
				<div class="mb-3 col-md-12 col-lg-4 d-none" id="cashpickupSourceBankWrap">
					<label class="form-label">Source bank</label>
					<select class="form-select" name="account_paid_to" id="cashpickupSourceBank">
						<option value="">---Select---</option>
						<?php
							$banks = [
								'Absa Bank',
								'Apex Nafana Bank',
								'Apex Suma Bank',
								'Access Bank',
								'Cal Bank',
								'Ecobank',
								'Fidelity Bank',
								'First National Bank (FNB)',
								'First Bank Ghana (FBG)',
								'GCB Bank',
								'GT Bank',
								'Stanbic Bank',
							];
						?>
						<?php foreach($banks as $b): ?>
							<option <?=old_select('account_paid_to',$b)?> value="<?=esc($b)?>"><?=esc($b)?></option>
						<?php endforeach; ?>
					</select>
					<?php if(!empty($errors['account_paid_to'])): ?>
						<small class="text-danger"><?=$errors['account_paid_to']?></small>
					<?php endif ?>
				</div>
			<?php endif; ?>

			<?php if(!empty($payout_mode)): ?>
				<input type="hidden" name="receive_id" value="<?=old_value('receive_id')?>">
			<?php endif; ?>

			<div class="mb-3 col-md-12 col-lg-4 d-none">
				<label for="assigned_user_id" class="form-label">Assign user</label>
				<select name="assigned_user_id" id="assigned_user_id" class="form-select">
					<option value="">-- Optional --</option>
					<?php
						try {
							$dbUsers = new \Core\Database;
							$assignUsers = $dbUsers->query("SELECT id, first_name, last_name FROM users WHERE deleted = 0 ORDER BY first_name ASC") ?? [];
						} catch(\Throwable $e) {
							$assignUsers = [];
						}
					?>
					<?php foreach($assignUsers as $u): $un = trim(($u->first_name ?? '').' '.($u->last_name ?? '')); ?>
						<option value="<?=esc($u->id)?>" <?=((string)old_value('assigned_user_id') === (string)$u->id)?'selected':''?>><?=esc($un)?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="mb-3 col-12 js-side-receive" id="customerRegistrationNotice" style="display:none;">
				<div class="alert customer-registration-notice mb-0">
					<?php if(user_can('is_manager') || user_can('is_admin')): ?>
						Register the customer first. Once the registration is approved, you can return here and complete the cashpickup.
					<?php else: ?>
						Register the customer first. A manager or admin must approve the registration before cashpickup can be recorded.
					<?php endif; ?>
				</div>
			</div>

			<div class="mb-3 col-md-12 col-lg-6 js-customer-detail-field<?=$payoutPrefillHiddenClass?>">
				<label for="sender_details" class="form-label">Customer full name</label>
				<input name="sender_details" value="<?=old_value('sender_details')?>" type="text" class="form-control" id="sender_details" placeholder="e.g. Peter Jalula" <?=($receiveExistingReadonly || $payoutPrefillReadonly) ? 'readonly' : ''?>>
				<?php if(!empty($errors['sender_details'])): ?>
					<small class="text-danger"><?=$errors['sender_details']?></small>
				<?php endif ?>
			</div>

			<div class="mb-3 col-md-12 col-lg-6 js-customer-detail-field<?=$payoutPrefillHiddenClass?>">
				<label for="phone_number" class="form-label" id="phone_number_label">Phone number</label>
				<input name="phone_number" value="<?=old_value('phone_number')?>" type="text" class="form-control" id="phone_number" placeholder="e.g. 05xxxxxxxx" <?=($receiveExistingReadonly || $payoutPrefillReadonly) ? 'readonly' : ''?>>
				<?php if(!empty($errors['phone_number'])): ?>
					<small class="text-danger"><?=$errors['phone_number']?></small>
				<?php endif ?>
			</div>

			<div class="mb-3 col-md-12 col-lg-6 js-customer-detail-field<?=$payoutPrefillHiddenClass?>">
				<label for="customer_id_number" class="form-label">ID number</label>
				<input name="customer_id_number" value="<?=old_value('customer_id_number')?>" type="text" class="form-control" id="customer_id_number" placeholder="Enter ID number" <?=($receiveExistingReadonly || $payoutPrefillReadonly) ? 'readonly' : ''?>>
				<?php if(!empty($errors['customer_id_number'])): ?>
					<small class="text-danger"><?=$errors['customer_id_number']?></small>
				<?php endif ?>
			</div>

			<div class="mb-3 col-md-12 col-lg-6 js-customer-detail-field<?=$payoutPrefillHiddenClass?>">
				<label for="customer_id_type" class="form-label">ID type</label>
				<select name="customer_id_type" id="customer_id_type" class="form-select" <?=$receiveExistingReadonly ? 'disabled' : ''?>>
					<option <?=old_select('customer_id_type','Ghana Card')?> value="Ghana Card">Ghana Card</option>
				</select>
				<?php if(!empty($errors['customer_id_type'])): ?>
					<small class="text-danger"><?=$errors['customer_id_type']?></small>
				<?php endif ?>
			</div>

			<div class="mb-3 col-md-12 col-lg-6 js-customer-detail-field<?=$payoutPrefillHiddenClass?>">
				<label for="customer_location" class="form-label">Location / Address</label>
				<input name="customer_location" value="<?=old_value('customer_location')?>" type="text" class="form-control" id="customer_location" placeholder="e.g. East Legon, Accra" <?=($receiveExistingReadonly || $payoutPrefillReadonly) ? 'readonly' : ''?>>
				<?php if(!empty($errors['customer_location'])): ?>
					<small class="text-danger"><?=$errors['customer_location']?></small>
				<?php endif ?>
				<small class="text-muted" id="customerLedgerHint"></small>
			</div>

			<div class="mb-3 col-md-12 col-lg-4 js-side-pay" style="display:none;">
				<label for="tracking_id" class="form-label">Tracking # / Transaction ID</label>
				<input name="tracking_id" value="<?=old_value('tracking_id')?>" type="text" class="form-control" id="tracking_id" placeholder="Enter tracking/transaction id" <?=$payoutPrefillReadonly ? 'readonly' : ''?>>
				<?php if(!empty($errors['tracking_id'])): ?>
					<small class="text-danger"><?=$errors['tracking_id']?></small>
				<?php endif ?>
				<small class="text-danger" id="lookup_error"></small>
			</div>
			<input class="js-side-receive" type="hidden" value="<?=old_value('tracking_id')?>" style="display:none;">

			<div class="mb-3 col-md-12 col-lg-4 js-side-pay" style="display:none;">
				<label for="pickup_person_idcard" class="form-label">Pick-up person ID card</label>
				<input name="pickup_person_idcard" value="<?=old_value('pickup_person_idcard')?>" type="text" class="form-control" id="pickup_person_idcard" placeholder="Enter ID card">
				<?php if(!empty($errors['pickup_person_idcard'])): ?>
					<small class="text-danger"><?=$errors['pickup_person_idcard']?></small>
				<?php endif ?>
			</div>

			<div class="mb-3 col-md-12 col-lg-4 js-side-pay" style="display:none;">
				<label for="pickup_person_phone" class="form-label">Pick-up person Phone</label>
				<input name="pickup_person_phone" value="<?=old_value('pickup_person_phone')?>" type="text" class="form-control" id="pickup_person_phone" placeholder="Enter phone">
				<?php if(!empty($errors['pickup_person_phone'])): ?>
					<small class="text-danger"><?=$errors['pickup_person_phone']?></small>
				<?php endif ?>
			</div>

			<div class="mb-3 col-md-12 col-lg-4 js-side-pay" style="display:none;">
				<label for="pay_type" class="form-label">Pay type</label>
				<select name="pay_type" id="pay_type" class="form-select">
					<option value="">---Select---</option>
					<option <?=old_select('pay_type','all')?> value="all">All</option>
					<option <?=old_select('pay_type','part')?> value="part">Part</option>
				</select>
				<?php if(!empty($errors['pay_type'])): ?>
					<small class="text-danger"><?=$errors['pay_type']?></small>
				<?php endif ?>
			</div>

			<div class="mb-3 col-md-12 col-lg-6 js-side-pay" style="display:none;">
				<label for="amount_requested" class="form-label">Amount requested</label>
				<input name="amount_requested" value="<?=old_value('amount_requested')?>" type="number" step="0.01" class="form-control" id="amount_requested" placeholder="Enter amount requested">
				<?php if(!empty($errors['amount_requested'])): ?>
					<small class="text-danger"><?=$errors['amount_requested']?></small>
				<?php endif ?>
			</div>

			<div class="row">
				<div class="col-md-12 col-lg-6" style="display:none;" id="idcardPhotoFieldWrap">
					<label for="idcard_photo" class="form-label" id="idcardPhotoLabel">ID Card Photo</label>
					<div class="mt-2" id="idcardPhotoPreviewWrap">
						<div class="transaction-preview-card" id="idcardPhotoPreviewCard" role="button" tabindex="0">
							<div class="transaction-preview-placeholder" id="idcardPhotoPreviewPlaceholder" style="display:<?=!empty(old_value('idcard_photo')) ? 'none' : 'flex'?>;">
								<i class="fa-solid fa-cloud-arrow-up" style="font-size:1.4rem;"></i>
								<div class="title" id="idcardPhotoPlaceholderTitle">Upload ID card photo</div>
								<div class="hint">Tap to choose an image</div>
							</div>
							<img id="idcardPhotoPreview" src="<?=!empty(old_value('idcard_photo')) ? (ROOT . '/' . esc(old_value('idcard_photo'))) : ''?>" alt="ID card photo" style="display:<?=!empty(old_value('idcard_photo')) ? 'block' : 'none'?>;">
							<div class="transaction-preview-overlay">
								<span class="transaction-preview-badge"><i class="fa-solid fa-camera"></i> Change</span>
							</div>
						</div>
						<small class="text-muted d-block mt-1 text-center">Tap the preview to change the image</small>
					</div>
					<small class="text-danger js-idcard-photo-error text-center d-block"><?=(string)($errors['idcard_photo'] ?? '')?></small>
					<input name="idcard_photo" type="file" class="visually-hidden-file" id="idcard_photo" accept="image/*">
				</div>

				<div class="col-md-12 col-lg-6 js-side-pay" style="display:none;" id="customerPhotoFieldWrap">
					<label for="customer_photo" class="form-label" id="customerPhotoLabel">Customer Photo</label>
					<div class="mt-2" id="customerPhotoPreviewWrap">
						<div class="transaction-preview-card" id="customerPhotoPreviewCard" role="button" tabindex="0">
							<div class="transaction-preview-placeholder" id="customerPhotoPreviewPlaceholder" style="display:<?=!empty(old_value('customer_photo')) ? 'none' : 'flex'?>;">
								<i class="fa-solid fa-cloud-arrow-up" style="font-size:1.4rem;"></i>
								<div class="title" id="customerPhotoPlaceholderTitle">Upload customer photo</div>
								<div class="hint">Tap to choose an image</div>
							</div>
							<img id="customerPhotoPreview" src="<?=!empty(old_value('customer_photo')) ? (ROOT . '/' . esc(old_value('customer_photo'))) : ''?>" alt="Customer photo" style="display:<?=!empty(old_value('customer_photo')) ? 'block' : 'none'?>;">
							<div class="transaction-preview-overlay">
								<span class="transaction-preview-badge"><i class="fa-solid fa-camera"></i> Change</span>
							</div>
						</div>
						<small class="text-muted d-block mt-1 text-center">Tap the preview to change the image</small>
					</div>
					<small class="text-danger js-customer-photo-error text-center d-block"><?=(string)($errors['customer_photo'] ?? '')?></small>
					<input name="customer_photo" type="file" class="visually-hidden-file" id="customer_photo" accept="image/*">
				</div>

				<div class="col-md-12 col-lg-6 js-side-receive-proof" style="display:none;">
					<label for="receive_proof_photo" class="form-label">Cash received proof photo</label>
					<div class="mt-2" id="receiveProofPhotoPreviewWrap">
						<div class="transaction-preview-card" id="receiveProofPhotoPreviewCard" role="button" tabindex="0">
							<div class="transaction-preview-placeholder" id="receiveProofPhotoPreviewPlaceholder" style="display:<?=!empty(old_value('receive_proof_photo')) ? 'none' : 'flex'?>;">
								<i class="fa-solid fa-cloud-arrow-up" style="font-size:1.4rem;"></i>
								<div class="title">Upload cash received proof photo</div>
								<div class="hint">Tap to choose an image</div>
							</div>
							<img id="receiveProofPhotoPreview" src="<?=!empty(old_value('receive_proof_photo')) ? (ROOT . '/' . esc(old_value('receive_proof_photo'))) : ''?>" alt="Cash received proof photo" style="display:<?=!empty(old_value('receive_proof_photo')) ? 'block' : 'none'?>;">
							<div class="transaction-preview-overlay">
								<span class="transaction-preview-badge"><i class="fa-solid fa-camera"></i> Change</span>
							</div>
						</div>
						<small class="text-muted d-block mt-1 text-center">Tap the preview to change the image</small>
					</div>
					<small class="text-danger js-receive-proof-photo-error text-center d-block"><?=(string)($errors['receive_proof_photo'] ?? '')?></small>
					<input name="receive_proof_photo" type="file" class="visually-hidden-file" id="receive_proof_photo" accept="image/*">
				</div>
			</div>

			<input type="hidden" name="denos_json" id="denos_json" value="<?=esc(old_value('denos_json') ?: old_value('denos'))?>">

			<div class="mb-3 col-12 col-md-6 col-lg-4 js-side-pay" style="display:none;">
				<label for="payment_method" class="form-label">Payment method</label>
				<select class="form-select" name="payment_method" id="payment_method">
					<option value="">---Select---</option>
					<option <?=old_select('payment_method','Cash')?> value="Cash">Cash</option>
					<option <?=old_select('payment_method','Transfer')?> value="Transfer">Transfer</option>
				</select>
				<?php if(!empty($errors['payment_method'])): ?>
					<small class="text-danger"><?=$errors['payment_method']?></small>
				<?php endif ?>
			</div>

			<div class="mb-3 col-12 col-md-6 col-lg-4 js-side-pay d-none" id="payoutTransferAsWrap" style="display:none;">
				<label for="payout_transfer_as" class="form-label">Transfer as</label>
				<select class="form-select" name="payout_channel" id="payout_transfer_as">
					<option value="">---Select---</option>
					<option <?=old_select('payout_channel','mtn')?> value="mtn">MTN</option>
					<option <?=old_select('payout_channel','telecel')?> value="telecel">Telecel</option>
					<option <?=old_select('payout_channel','at')?> value="at">AT</option>
				</select>
				<?php if(!empty($errors['payout_channel'])): ?>
					<small class="text-danger"><?=$errors['payout_channel']?></small>
				<?php endif ?>
			</div>

			<div class="mb-3 col-12 col-md-6 col-lg-4 js-side-pay" style="display:none;">
				<label for="pickup_charge" class="form-label">Pick-up Charge</label>
				<div class="input-group">
					<div class="input-group-text">GH₵</div>
					<input name="pickup_charge" value="<?=old_value('pickup_charge')?>" type="number" step="0.01" class="form-control" id="pickup_charge" placeholder="0.00">
				</div>
			</div>

			<div class="mb-3 col-12 col-md-6 col-lg-4 js-side-pay" style="display:none;">
				<label for="amount_paid_out" class="form-label">Amount paid out</label>
				<div class="input-group">
					<div class="input-group-text">GH₵</div>
					<?php $paidOutDefault = strtoupper((string)(old_value('payment_method') ?? '')) === 'TRANSFER' ? old_value('amount_paid_out') : ''; ?>
					<input name="amount_paid_out" value="<?=esc($paidOutDefault)?>" type="number" step="0.01" class="form-control" id="amount_paid_out" placeholder="0.00">
					<button type="button" class="btn btn-outline-warning" id="payDenosBtn" data-bs-toggle="modal" data-bs-target="#cashPickupDenosOutModal" title="Cash calculator" style="display:none;">
						<i class="fa-solid fa-calculator"></i>
					</button>
				</div>
				<div class="small text-muted mt-1" id="payDenosSummary" style="display:none;"></div>
			</div>

			<div class="mb-3 col-12 col-md-6 col-lg-4 js-side-pay" style="display:none;">
				<label for="amount_transferred" class="form-label">Amount transferred</label>
				<div class="input-group">
					<div class="input-group-text">GH₵</div>
					<input name="amount_transferred" value="<?=old_value('amount_transferred')?>" type="number" step="0.01" class="form-control" id="amount_transferred" placeholder="0.00">
				</div>
			</div>

			<div class="mb-3 col-md-12 col-lg-6 js-side-pay" style="display:none;">
				<label for="confirmed_by" class="form-label">Confirmed by</label>
				<?php $sessionUser = get_session(); $confirmedDefault = trim((string)(($sessionUser->first_name ?? '') . ' ' . ($sessionUser->last_name ?? ''))); ?>
				<input name="confirmed_by" value="<?=esc(old_value('confirmed_by') ?: $confirmedDefault)?>" type="text" class="form-control" id="confirmed_by" placeholder="Enter confirmer">
			</div>

			<div class="mb-3 col-md-12 js-side-both<?=$payoutPrefillHiddenClass?>" style="display: none;">
				<label for="notes" class="form-label">Notes</label>
				<input name="notes" value="<?=old_value('notes')?>" type="text" class="form-control" id="notes" placeholder="Optional notes" <?=$payoutPrefillReadonly ? 'readonly' : ''?>>
			</div>

			<input type="hidden" name="transaction_status" value="Approved">

			<div class="d-flex justify-content-between" style="margin-top:10px;">
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
					<button type="button" class="btn btn-primary">
						<i class="fa-solid fa-chevron-left"></i> Back
					</button>
				</a>
				<?php if(!$hidePayoutForm): ?>
					<button class="btn btn-danger" type="submit" id="cashpickupSubmitBtn">
						<i class="fa-solid fa-save"></i> Save
					</button>
				<?php else: ?>
					<div class="alert alert-success mb-0" role="alert">
						This cashpickup is fully paid. Payout form is disabled.
					</div>
				<?php endif; ?>
			</div>
		</div>
	</form>

	<div class="modal fade" id="cashPickupDenosModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Currency Notes (Cash In)</h5>
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
							<?php $denoms = [200,100,50,20,10,5,2,1,0.5,0.2,0.1]; ?>
							<?php foreach($denoms as $d): ?>
								<tr data-denomination="<?=$d?>">
									<td class="px-3">GHS <?=esc($d)?></td>
									<td><input type="number" class="form-control p-1 count-input" placeholder="0" min="0" step="1"></td>
									<td><input type="text" class="form-control p-1 volume-input" placeholder="0.00" readonly></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
						<tfoot>
							<tr>
								<th class="px-3 bg-secondary text-warning">TOTAL</th>
								<th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="total-count" style="border:none;" readonly></th>
								<th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="total-volume" style="border:none;" readonly></th>
							</tr>
						</tfoot>
					</table>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
					<button type="button" class="btn btn-danger" id="denos_reset">Reset</button>
					<button type="button" class="btn btn-primary" id="denos_confirm">Confirm</button>
				</div>
			</div>
		</div>
	</div>

	<div class="modal fade" id="cashPickupDenosOutModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Currency Notes (Cash Out)</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body table-responsive">
					<div class="row g-1 mb-2">
						<div class="col-12 col-md-4">
							<div class="border rounded p-2">
								<div class="text-muted small">Amount requested</div>
								<div class="fw-semibold" id="payDenosRequested">GH₵ 0.00</div>
							</div>
						</div>
						<div class="col-12 col-md-4">
							<div class="border rounded p-2">
								<div class="text-muted small">Charge</div>
								<div class="fw-semibold" id="payDenosCharge">GH₵ 0.00</div>
							</div>
						</div>
						<div class="col-12 col-md-4">
							<div class="border rounded p-2">
								<div class="text-muted small">Amount payable</div>
								<div class="fw-bold" id="payDenosPayable">GH₵ 0.00</div>
								<div class="small" id="payDenosDiff"></div>
							</div>
						</div>
					</div>
					<table class="table table-hover table-sm">
						<thead>
							<tr>
								<th>NOTES</th>
								<th>COUNT</th>
								<th>VOLUME</th>
							</tr>
						</thead>
						<tbody>
							<?php $denomsOut = [200,100,50,20,10,5,2,1,0.5,0.2,0.1]; ?>
							<?php foreach($denomsOut as $d): ?>
								<tr data-denomination="<?=$d?>">
									<td class="px-3">GHS <?=esc($d)?></td>
									<td><input type="number" class="form-control p-1 count-input-out" placeholder="0" min="0" step="1"></td>
									<td><input type="text" class="form-control p-1 volume-input-out" placeholder="0.00" readonly></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
						<tfoot>
							<tr>
								<th class="px-3 bg-secondary text-warning">TOTAL</th>
								<th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="total-count-out" style="border:none;" readonly></th>
								<th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="total-volume-out" style="border:none;" readonly></th>
							</tr>
						</tfoot>
					</table>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
					<button type="button" class="btn btn-danger" id="denos_reset_out">Reset</button>
					<button type="button" class="btn btn-primary" id="denos_confirm_out">Confirm</button>
				</div>
			</div>
		</div>
	</div>

	<script>
		(function(){
			const receiveFields = Array.from(document.querySelectorAll('.js-side-receive'));
			const payFields = Array.from(document.querySelectorAll('.js-side-pay'));
			const bothSidesFields = Array.from(document.querySelectorAll('.js-side-both'));
			const side = document.getElementById('transaction_side');
			const submitBtn = document.getElementById('cashpickupSubmitBtn');
			const amountInvolvedReceive = document.getElementById('amount_involved');
			const amountInvolvedPay = document.getElementById('amount_involved_pay');
			const denosJson = document.getElementById('denos_json');
			const phoneNumber = document.getElementById('phone_number');
			const trackingId = document.getElementById('tracking_id');
			const senderDetails = document.getElementById('sender_details');
			const notes = document.getElementById('notes');
			const customerIdType = document.getElementById('customer_id_type');
			const customerIdNumber = document.getElementById('customer_id_number');
			const customerLocation = document.getElementById('customer_location');
			const customerLedgerHint = document.getElementById('customerLedgerHint');
			const customerTypeWrap = document.getElementById('customerTypeWrap');
			const customerTypeEl = document.getElementById('customer_type');
			const existingCustomerSearchWrap = document.getElementById('existingCustomerSearchWrap');
			const existingCustomerSearch = document.getElementById('existing_customer_search');
			const existingCustomerSearchResults = document.getElementById('existingCustomerSearchResults');
			const customerRegistrationNotice = document.getElementById('customerRegistrationNotice');
			const customerIdInput = document.getElementById('customer_id');
			const lookupError = document.getElementById('lookup_error');
			const phoneLabel = document.getElementById('phone_number_label');
			const charge = document.getElementById('pickup_charge');
			const paidOut = document.getElementById('amount_paid_out');
			const transferred = document.getElementById('amount_transferred');
			const payType = document.getElementById('pay_type');
			const amountRequested = document.getElementById('amount_requested');
			const paymentMethod = document.getElementById('payment_method');
			const payDenosBtn = document.getElementById('payDenosBtn');
			const payDenosSummary = document.getElementById('payDenosSummary');
			const idcardPhotoFieldWrap = document.getElementById('idcardPhotoFieldWrap');
			const idcardPhotoLabel = document.getElementById('idcardPhotoLabel');
			const idcardPhotoPlaceholderTitle = document.getElementById('idcardPhotoPlaceholderTitle');
			const customerPhotoFieldWrap = document.getElementById('customerPhotoFieldWrap');
			const customerPhotoLabel = document.getElementById('customerPhotoLabel');
			const customerPhotoPlaceholderTitle = document.getElementById('customerPhotoPlaceholderTitle');
			const idcardPhotoInput = document.getElementById('idcard_photo');
			const customerPhotoInput = document.getElementById('customer_photo');
			const receiveProofPhotoInput = document.getElementById('receive_proof_photo');
			const idcardPhotoPreview = document.getElementById('idcardPhotoPreview');
			const customerPhotoPreview = document.getElementById('customerPhotoPreview');
			const receiveProofPhotoPreview = document.getElementById('receiveProofPhotoPreview');
			const customerDetailFields = Array.from(document.querySelectorAll('.js-customer-detail-field'));
			const receiveProofEls = Array.from(document.querySelectorAll('.js-side-receive-proof'));
			const sourceEl = document.getElementById('source');
			const sourceBankEl = document.getElementById('cashpickupSourceBank');
			const isManagerOrAdmin = <?=((user_can('is_manager') || user_can('is_admin')) ? 'true' : 'false')?>;
			const amountInvolvedReceiveWrap = document.getElementById('amountInvolvedReceiveWrap');
			const amountInvolvedReceiveCalcIcon = document.getElementById('amountInvolvedReceiveCalcIcon');
			const amountInvolvedReceiveCurrencyPrefix = document.getElementById('amountInvolvedReceiveCurrencyPrefix');
			const pickupIdCard = document.getElementById('pickup_person_idcard');
			const pickupPhone = document.getElementById('pickup_person_phone');
			const confirmedBy = document.getElementById('confirmed_by');
			const remainingBalance = <?=json_encode(!empty($payout_mode) ? (float)($remaining_balance ?? 0) : 0)?>;
			const payoutAssignedGross = <?=json_encode((float)(old_value('assigned_payout_amount') ?: 0))?>;
			const payoutAssignedCharge = <?=json_encode((float)(old_value('assigned_payout_charge') ?: 0))?>;
			const payoutAssignedPayable = <?=json_encode((float)(old_value('assigned_payout_payable') ?: 0))?>;
			const payoutAssignmentLocked = <?=!empty($payout_mode) && ((float)(old_value('assigned_payout_amount') ?: 0) > 0) ? 'true' : 'false'?>;
			const customerSearchUrl = `<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer_search`;

			const fmt = (n) => {
				const v = (typeof n === 'number' && !isNaN(n)) ? n : 0;
				return 'GH₵ ' + v.toFixed(2);
			};
			const currentSide = () => (side && side.value) ? String(side.value).toLowerCase() : '';
			const currentCustomerType = () => {
				if (currentSide() !== 'receive') return 'existing';
				const v = (customerTypeEl && customerTypeEl.value) ? String(customerTypeEl.value).toLowerCase() : '';
				if (v === 'existing' || v === 'new') return v;
				return '';
			};
			const isReceiveExistingCustomer = () => currentSide() === 'receive' && currentCustomerType() === 'existing';
			const isReceiveNewCustomer = () => currentSide() === 'receive' && currentCustomerType() === 'new';
			const isNewCustomer = () => currentCustomerType() === 'new';
			const hasExistingCustomerSelected = () => !!(customerIdInput && String(customerIdInput.value || '').trim() !== '');
			const isCashPay = () => {
				const v = (paymentMethod && paymentMethod.value) ? String(paymentMethod.value).toLowerCase() : '';
				return v === 'cash';
			};
			const isTransferPay = () => {
				const v = (paymentMethod && paymentMethod.value) ? String(paymentMethod.value).toLowerCase() : '';
				return v === 'transfer';
			};

			function hasSelectedImageFile(inputEl, previewEl){
				if (inputEl && inputEl.files && inputEl.files.length > 0) return true;
				const src = previewEl ? String(previewEl.getAttribute('src') || '').trim() : '';
				return src !== '';
			}

			function clearUploadCard(inputEl, previewEl, placeholderEl, errorEl){
				if (inputEl) inputEl.value = '';
				if (previewEl) previewEl.src = '';
				if (placeholderEl) placeholderEl.style.display = 'flex';
				if (previewEl) previewEl.style.display = 'none';
				if (errorEl) errorEl.textContent = '';
			}

			function syncPayoutReceiptUi(){
				const transfer = isTransferPay();
				if (idcardPhotoLabel) idcardPhotoLabel.textContent = transfer ? 'Transfer Receipt' : 'ID Card Photo';
				if (idcardPhotoPlaceholderTitle) idcardPhotoPlaceholderTitle.textContent = transfer ? 'Upload transfer receipt' : 'Upload ID card photo';
				if (customerPhotoFieldWrap) customerPhotoFieldWrap.style.display = transfer ? 'none' : '';
				if (customerPhotoInput) customerPhotoInput.disabled = transfer;
				if (customerPhotoLabel) customerPhotoLabel.textContent = 'Customer Photo';
				if (customerPhotoPlaceholderTitle) customerPhotoPlaceholderTitle.textContent = 'Upload customer photo';
				if (transfer) {
					clearUploadCard(
						customerPhotoInput,
						customerPhotoPreview,
						document.getElementById('customerPhotoPreviewPlaceholder'),
						document.querySelector('.js-customer-photo-error')
					);
				}
			}

			function denosTotal(){
				if(!denosJson) return 0;
				const raw = (denosJson.value || '').trim();
				if(raw === '') return 0;
				try{
					const parsed = JSON.parse(raw);
					if(!Array.isArray(parsed)) return 0;
					let sum = 0;
					parsed.forEach(r => {
						sum += parseFloat((r && r.volume) ? r.volume : 0) || 0;
					});
					return Math.round(sum * 100) / 100;
				}catch(e){
					return 0;
				}
			}

			function isFilled(el){
				if(!el) return false;
				return String(el.value || '').trim() !== '';
			}
			function asFloat(el){
				if(!el) return 0;
				const n = parseFloat(String(el.value || '0'));
				return isNaN(n) ? 0 : n;
			}
			function setLedgerState(matched, message){
				if(customerLedgerHint) customerLedgerHint.textContent = message || '';
			}
			function hideExistingCustomerResults(){
				if(!existingCustomerSearchResults) return;
				existingCustomerSearchResults.innerHTML = '';
				existingCustomerSearchResults.style.display = 'none';
			}
			function resetCustomerFields(){
				if(senderDetails) senderDetails.value = '';
				if(phoneNumber) phoneNumber.value = '';
				if(customerIdNumber) customerIdNumber.value = '';
				if(customerLocation) customerLocation.value = '';
				if(customerIdType) customerIdType.value = 'Ghana Card';
				if(customerIdInput) customerIdInput.value = '';
			}
			function setCustomerFieldsEditable(editable){
				if(senderDetails) senderDetails.readOnly = !editable;
				if(phoneNumber) phoneNumber.readOnly = !editable;
				if(customerIdNumber) customerIdNumber.readOnly = !editable;
				if(customerLocation) customerLocation.readOnly = !editable;
				if(customerIdType) customerIdType.disabled = !editable;
			}
			function formatCustomerSearchLabel(data){
				const name = data && data.full_name ? String(data.full_name).trim() : '';
				const phone = data && data.phone_number ? String(data.phone_number).trim() : '';
				if(name !== '' && phone !== '') return `${name} - ${phone}`;
				return name || phone || '';
			}
			function fillCustomerFields(data){
				if(!data) return;
				if(customerIdInput) customerIdInput.value = data.id ? String(data.id) : '';
				if(senderDetails) senderDetails.value = data.full_name || '';
				if(phoneNumber) phoneNumber.value = data.phone_number || '';
				if(customerIdType) customerIdType.value = data.id_type || 'Ghana Card';
				if(customerIdNumber) customerIdNumber.value = data.id_number || '';
				if(customerLocation) customerLocation.value = data.location || '';
				if(existingCustomerSearch && isReceiveExistingCustomer()){
					existingCustomerSearch.value = formatCustomerSearchLabel(data);
				}
			}
			function syncCustomerDetailVisibility(){
				const showDetails = (currentSide() === 'pay') || isReceiveNewCustomer() || (isReceiveExistingCustomer() && hasExistingCustomerSelected());
				customerDetailFields.forEach((el) => {
					el.style.display = showDetails ? '' : 'none';
				});
			}
			function setSubmitButtonLabel(){
				if(!submitBtn) return;
				if(isReceiveNewCustomer()){
					submitBtn.innerHTML = isManagerOrAdmin
						? '<i class="fa-solid fa-user-plus"></i> Register Customer and Continue'
						: '<i class="fa-solid fa-paper-plane"></i> Submit Customer for Approval';
					return;
				}
				submitBtn.innerHTML = '<i class="fa-solid fa-save"></i> Save';
			}

			function syncSubmitState(){
				if(!submitBtn) return;
				const s = currentSide();

				// Receive-side requirements
				if(s === 'receive'){
					if(currentCustomerType() === ''){
						submitBtn.disabled = true;
						return;
					}
					const senderOk = isFilled(senderDetails);
					const customerKeyOk = isFilled(phoneNumber) || isFilled(customerIdNumber);
					if(isReceiveNewCustomer()){
						submitBtn.disabled = !(senderOk && customerKeyOk);
						return;
					}
					const ledgerOk = customerIdInput && String(customerIdInput.value || '').trim() !== '';
					if(isManagerOrAdmin){
						const src = sourceEl ? String(sourceEl.value || '').toLowerCase() : '';
						const sourceOk = src !== '';
						const proofOk = hasSelectedImageFile(receiveProofPhotoInput, receiveProofPhotoPreview);
						if(src === 'cash'){
							const denosOk = denosTotal() > 0;
							submitBtn.disabled = !(senderOk && customerKeyOk && ledgerOk && sourceOk && denosOk && proofOk);
							return;
						}
						const bankOk = (src !== 'bank') || (sourceBankEl && String(sourceBankEl.value || '').trim() !== '');
						const amtOk = asFloat(amountInvolvedReceive) > 0;
						submitBtn.disabled = !(senderOk && customerKeyOk && ledgerOk && sourceOk && bankOk && amtOk && proofOk);
						return;
					}
					const denosOk = denosTotal() > 0;
					submitBtn.disabled = !(senderOk && customerKeyOk && ledgerOk && denosOk);
					return;
				}

				// Pay-side requirements (payout)
				if(s === 'pay'){
					const phoneOk = isFilled(phoneNumber);
					const trackingOk = isFilled(trackingId);
					const idCardOk = isFilled(pickupIdCard);
					const pickupPhoneOk = isFilled(pickupPhone);
					const payTypeOk = isFilled(payType);
					const reqOk = asFloat(amountRequested) > 0;
					const pmOk = isFilled(paymentMethod);
					const transferAsEl = document.getElementById('payout_transfer_as');
					const transferAsOk = !isTransferPay() || (transferAsEl && String(transferAsEl.value || '').trim() !== '');
					const confOk = isFilled(confirmedBy);
					const payoutOk = asFloat(paidOut) > 0;
					const idOk = hasSelectedImageFile(idcardPhotoInput, idcardPhotoPreview);
					const custOk = isTransferPay() ? true : hasSelectedImageFile(customerPhotoInput, customerPhotoPreview);
					const ledgerOk = customerIdInput && String(customerIdInput.value || '').trim() !== '';

					let cashDenosOk = true;
					if (isCashPay()) {
						const expected = Math.round(asFloat(paidOut) * 100) / 100;
						const total = denosTotal();
						cashDenosOk = total > 0 && Math.abs(total - expected) <= 0.01;
					}

					submitBtn.disabled = !(phoneOk && trackingOk && idCardOk && pickupPhoneOk && payTypeOk && reqOk && pmOk && transferAsOk && confOk && payoutOk && idOk && custOk && cashDenosOk && ledgerOk);
					return;
				}

				submitBtn.disabled = false;
			}

			function toggleSideUI(){
				const s = currentSide();
				const src = sourceEl ? String(sourceEl.value || '').toLowerCase() : '';
				const customerType = currentCustomerType();
				const newCustomer = isReceiveNewCustomer();
				const existingCustomer = isReceiveExistingCustomer();
				const existingSelected = hasExistingCustomerSelected();
				receiveFields.forEach(el => el.style.display = (s === 'receive' ? '' : 'none'));
				payFields.forEach(el => el.style.display = (s === 'pay' ? '' : 'none'));
				if(isNewCustomer()){
					bothSidesFields.forEach(el => el.style.display = 'none');
				}else{
					bothSidesFields.forEach(el => el.style.display = 'block');
				}
				if(customerTypeWrap){
					customerTypeWrap.style.display = (s === 'receive') ? '' : 'none';
				}
				if(existingCustomerSearchWrap){
					existingCustomerSearchWrap.style.display = existingCustomer ? '' : 'none';
				}
				if(customerRegistrationNotice){
					customerRegistrationNotice.style.display = newCustomer ? '' : 'none';
				}
				if (customerPhotoFieldWrap) {
					const showCustomerPhoto = (s === 'pay' && !isTransferPay());
					customerPhotoFieldWrap.style.display = showCustomerPhoto ? '' : 'none';
				}
				if(idcardPhotoFieldWrap){
					idcardPhotoFieldWrap.style.display = (s === 'pay' || (s === 'receive' && isManagerOrAdmin && !newCustomer)) ? '' : 'none';
				}
				const payoutTransferWrap = document.getElementById('payoutTransferAsWrap');
				const transferAsEl = document.getElementById('payout_transfer_as');
				if(payoutTransferWrap){
					const showTransferAs = (s === 'pay' && isTransferPay());
					payoutTransferWrap.classList.toggle('d-none', !showTransferAs);
					payoutTransferWrap.style.display = showTransferAs ? '' : 'none';
					if(!showTransferAs && transferAsEl) transferAsEl.value = '';
				}
				receiveProofEls.forEach(el => {
					el.style.display = (s === 'receive' && isManagerOrAdmin && !newCustomer) ? '' : 'none';
				});
				if(phoneLabel){
					phoneLabel.textContent = (s === 'pay') ? 'Customer number' : 'Phone number';
				}
				if(senderDetails){
					if (s === 'pay') {
						senderDetails.placeholder = 'Auto populated from receive record';
					} else if (existingCustomer) {
						senderDetails.placeholder = 'Auto filled from selected customer';
					} else {
						senderDetails.placeholder = 'e.g. Peter Jalula';
					}
				}
				if(phoneNumber){
					phoneNumber.readOnly = payoutMode ? true : ((s === 'pay') ? false : existingCustomer);
				}
				if(trackingId){
					trackingId.readOnly = payoutMode ? true : false;
				}
				if(amountRequested){
					amountRequested.readOnly = payoutMode ? true : false;
				}
				if(notes){
					notes.readOnly = payoutMode ? true : false;
				}
				if(s === 'pay'){
					setCustomerFieldsEditable(false);
				}else if(newCustomer){
					setCustomerFieldsEditable(true);
				}else if(existingCustomer){
					setCustomerFieldsEditable(false);
				}
				if(sourceEl && sourceEl.tagName === 'SELECT'){
					sourceEl.disabled = newCustomer;
					sourceEl.required = !newCustomer;
				}
				if(amountInvolvedReceive){
					const useCalc = (s === 'receive' && isManagerOrAdmin && src === 'cash' && !newCustomer);
					const enableReceiveModal = (s === 'receive' && !newCustomer && (!isManagerOrAdmin || src === 'cash'));
					amountInvolvedReceive.readOnly = newCustomer ? true : (useCalc ? true : !(s === 'receive' && isManagerOrAdmin));
					if(amountInvolvedReceiveWrap){
						if(enableReceiveModal){
							amountInvolvedReceiveWrap.setAttribute('data-bs-toggle','modal');
							amountInvolvedReceiveWrap.setAttribute('data-bs-target','#cashPickupDenosModal');
						}else{
							amountInvolvedReceiveWrap.removeAttribute('data-bs-toggle');
							amountInvolvedReceiveWrap.removeAttribute('data-bs-target');
						}
					}
					if(amountInvolvedReceiveCalcIcon){
						amountInvolvedReceiveCalcIcon.style.display = useCalc ? '' : 'none';
					}
					if(amountInvolvedReceiveCurrencyPrefix){
						amountInvolvedReceiveCurrencyPrefix.style.display = useCalc ? 'none' : '';
					}
				}
				if(sourceBankEl){
					sourceBankEl.disabled = newCustomer;
					if(newCustomer) sourceBankEl.value = '';
				}
				if(amountInvolvedPay){
					amountInvolvedPay.readOnly = true;
				}
				if(lookupError){
					lookupError.textContent = '';
				}
				syncCustomerDetailVisibility();
				if(customerType === ''){
					hideExistingCustomerResults();
					setLedgerState(false, 'Select customer type to continue.');
				}else if(newCustomer){
					hideExistingCustomerResults();
					if(!isFilled(senderDetails) && !isFilled(phoneNumber) && !isFilled(customerIdNumber)){
						setLedgerState(false, 'Enter the new customer details and submit the registration first.');
					}
				}else if(existingCustomer && !existingSelected){
					setLedgerState(false, 'Search and select an approved customer before saving.');
				}
				setSubmitButtonLabel();
				syncSubmitState();
			}

			const payoutMode = <?=!empty($payout_mode) ? 'true' : 'false'?>;
			function applyPayoutMode(){
				if(!payoutMode) return;
				if(side) side.value = 'pay';
				toggleSideUI();
				if(phoneNumber) phoneNumber.readOnly = true;
				if(trackingId) trackingId.readOnly = true;
				if(senderDetails) senderDetails.readOnly = true;
				if(customerIdNumber) customerIdNumber.readOnly = true;
				if(customerLocation) customerLocation.readOnly = true;
				if(notes) notes.readOnly = true;
				if(amountRequested) amountRequested.readOnly = true;
				if(payType && (payType.value || '') === '') payType.value = 'all';
				if (payoutAssignmentLocked) {
					if (amountRequested) {
						amountRequested.value = payoutAssignedGross.toFixed(2);
						amountRequested.readOnly = true;
					}
					if (charge) {
						charge.value = payoutAssignedCharge.toFixed(2);
						charge.readOnly = true;
					}
					if (amountInvolvedPay) amountInvolvedPay.value = payoutAssignedGross.toFixed(2);
					if (paidOut) {
						paidOut.readOnly = true;
					}
					if (transferred) transferred.value = payoutAssignedGross.toFixed(2);
				}
				triggerLookup();
				syncPayoutReceiptUi();
				syncPayDenosControls();
				syncSubmitState();
			}

			function toNumber(v){
				const n = parseFloat((v ?? '').toString());
				return isNaN(n) ? 0 : n;
			}
			function syncPaidOut(){
				if(!paidOut || !amountRequested) return;
				if (payoutAssignmentLocked) {
					amountRequested.value = payoutAssignedGross.toFixed(2);
					if (charge) charge.value = payoutAssignedCharge.toFixed(2);
					if (transferred) transferred.value = payoutAssignedGross.toFixed(2);
					syncSubmitState();
					return;
				}
				const reqAmt = toNumber(amountRequested.value);
				const ch = toNumber(charge ? charge.value : 0);
				if(transferred){
					transferred.value = reqAmt.toFixed(2);
				}
				syncSubmitState();
			}

			function syncPayDenosControls(){
				if(!payDenosBtn || !paidOut) return;
				const cash = (side && side.value === 'pay' && isCashPay());
				const transfer = (side && side.value === 'pay' && isTransferPay());
				if (payoutAssignmentLocked) {
					payDenosBtn.style.display = cash ? '' : 'none';
					if (payDenosSummary) payDenosSummary.style.display = cash ? '' : 'none';
					if (cash) {
						paidOut.value = '';
					} else if (transfer) {
						const payable = payableAmount();
						paidOut.value = payable > 0 ? payable.toFixed(2) : '';
					}
					if (cash && payDenosSummary) payDenosSummary.textContent = '';
					paidOut.readOnly = true;
					syncSubmitState();
					return;
				}
				payDenosBtn.style.display = cash ? '' : 'none';
				if (payDenosSummary) payDenosSummary.style.display = cash ? '' : 'none';
				if (cash) {
					paidOut.value = '';
				} else if (transfer) {
					const payable = payableAmount();
					paidOut.value = payable > 0 ? payable.toFixed(2) : '';
				}
				paidOut.readOnly = true;
				if (cash && payDenosSummary) payDenosSummary.textContent = '';
				syncSubmitState();
			}

			function payableAmount(){
				if (payoutAssignmentLocked) {
					return payoutAssignedPayable;
				}
				const reqAmt = toNumber(amountRequested ? amountRequested.value : 0);
				const ch = toNumber(charge ? charge.value : 0);
				return Math.max(0, reqAmt - ch);
			}

			function updatePayDenosSummary(total){
				if(!payDenosSummary) return;
				const pay = payableAmount();
				const diff = (total - pay);
				let msg = 'Payable: ' + fmt(pay) + ' | Cash selected: ' + fmt(total);
				if (Math.abs(diff) <= 0.009) {
					msg += ' | Match';
				} else if (diff > 0) {
					msg += ' | Over by ' + fmt(diff);
				} else {
					msg += ' | Under by ' + fmt(Math.abs(diff));
				}
				payDenosSummary.textContent = msg;
			}
			function syncPayType(){
				if(!payType || !amountRequested) return;
				if (payoutAssignmentLocked) {
					payType.value = 'all';
					amountRequested.readOnly = true;
					if (charge) charge.readOnly = true;
					amountRequested.value = payoutAssignedGross.toFixed(2);
					if (charge) charge.value = payoutAssignedCharge.toFixed(2);
					if (amountInvolvedPay) amountInvolvedPay.value = payoutAssignedGross.toFixed(2);
					syncPaidOut();
					syncSubmitState();
					return;
				}
				const t = (payType.value || '').toLowerCase();
				if(t === 'all'){
					amountRequested.readOnly = true;
					const base = toNumber(amountInvolvedPay ? amountInvolvedPay.value : 0);
					const rem = toNumber(remainingBalance);
					const useVal = (rem > 0) ? rem : base;
					if(useVal > 0) amountRequested.value = useVal.toFixed(2);
				}else{
					amountRequested.readOnly = payoutMode ? true : false;
				}
				syncPaidOut();
				syncSubmitState();
			}

			const wireUploadCard = (opts) => {
				if (window.AppImageCropper && typeof window.AppImageCropper.bindPreviewCard === 'function') {
					window.AppImageCropper.bindPreviewCard({
						inputId: opts.inputId,
						cardId: opts.cardId,
						previewId: opts.previewId,
						placeholderId: opts.placeholderId,
						errorSelector: opts.errorSelector,
						aspectRatio: opts.aspectRatio || 1.5,
						maxBytes: window.AppImageCropper.MAX_BYTES,
						modalTitle: opts.modalTitle || 'Crop image',
						onComplete: function(){
							syncSubmitState();
						}
					});
					return;
				}

				const inputEl = document.getElementById(opts.inputId);
				const cardEl = document.getElementById(opts.cardId);
				const previewEl = document.getElementById(opts.previewId);
				const placeholderEl = document.getElementById(opts.placeholderId);
				const errorEl = opts.errorSelector ? document.querySelector(opts.errorSelector) : null;
				if(!inputEl || !cardEl || !previewEl || !placeholderEl) return;

				const setPreviewState = (hasImage) => {
					placeholderEl.style.display = hasImage ? 'none' : 'flex';
					previewEl.style.display = hasImage ? 'block' : 'none';
				};

				if ((previewEl.getAttribute('src') || '').trim() !== '') {
					setPreviewState(true);
				}

				cardEl.addEventListener('click', function(){
					inputEl.click();
				});
				cardEl.addEventListener('keydown', function(e){
					if (e.key === 'Enter' || e.key === ' ') {
						e.preventDefault();
						inputEl.click();
					}
				});

				inputEl.addEventListener('change', function(){
					const file = (this.files && this.files[0]) ? this.files[0] : null;
					if(!file){
						previewEl.src = '';
						setPreviewState(false);
						syncSubmitState();
						return;
					}
					if (!file.type || !file.type.startsWith('image/')) {
						this.value = '';
						previewEl.src = '';
						setPreviewState(false);
						if(errorEl) errorEl.textContent = 'Invalid file type. Please select an image.';
						syncSubmitState();
						return;
					}
					const reader = new FileReader();
					reader.onload = function(ev){
						previewEl.src = ev.target.result;
						setPreviewState(true);
						if(errorEl) errorEl.textContent = '';
						syncSubmitState();
					};
					reader.readAsDataURL(file);
				});
			};

			function bindSourceBankToggle(){
				const transactionSideEl = document.getElementById('transaction_side') || side;
				const sourceEl = document.getElementById('source');
				const wrap = document.getElementById('cashpickupSourceBankWrap');
				const bankEl = document.getElementById('cashpickupSourceBank');
				if(!sourceEl || !wrap || !bankEl) return;
				const sync = () => {
					const currentSide = transactionSideEl ? String(transactionSideEl.value || '').toLowerCase() : '';
					const src = String(sourceEl.value || '').toLowerCase();
					const customerType = (customerTypeEl && customerTypeEl.value) ? String(customerTypeEl.value || '').toLowerCase() : 'existing';
					const show = (currentSide === 'receive' && customerType !== 'new' && src === 'bank');
					wrap.classList.toggle('d-none', !show);
					if(!show){
						bankEl.value = '';
					}
					syncSubmitState();
				};
				sourceEl.addEventListener('change', sync);
				transactionSideEl && transactionSideEl.addEventListener('change', sync);
				sync();
			}

			function syncComputed(){
				const s = (side && side.value) ? side.value : '';
				if (payoutAssignmentLocked && s === 'pay') {
					if (amountRequested) amountRequested.value = payoutAssignedGross.toFixed(2);
					if (charge) charge.value = payoutAssignedCharge.toFixed(2);
					if (transferred) transferred.value = payoutAssignedGross.toFixed(2);
					return;
				}
				const amt = (s === 'pay') ? (parseFloat((amountInvolvedPay && amountInvolvedPay.value) || '0') || 0) : (parseFloat((amountInvolvedReceive && amountInvolvedReceive.value) || '0') || 0);
				const fee = parseFloat(charge.value || '0') || 0;

				if(s === 'pay'){
					if (isTransferPay() && paidOut) {
						const payable = payableAmount();
						paidOut.value = payable > 0 ? payable.toFixed(2) : '';
					} else if (isCashPay() && paidOut) {
						paidOut.value = '';
					}
					if((transferred.value || '').trim() === '' && amt > 0){
						transferred.value = amt.toFixed(2);
					}
				}
			}

			['change','input'].forEach(evt => {
				side && side.addEventListener(evt, function(){
					bindSourceBankToggle();
					toggleSideUI();
					syncComputed();
					syncPayoutReceiptUi();
					syncPayDenosControls();
					syncSubmitState();
				});
				amountInvolvedReceive && amountInvolvedReceive.addEventListener(evt, syncComputed);
				amountInvolvedPay && amountInvolvedPay.addEventListener(evt, syncComputed);
				charge && charge.addEventListener(evt, syncComputed);
			});
			['change','input'].forEach(evt => {
				senderDetails && senderDetails.addEventListener(evt, syncSubmitState);
				phoneNumber && phoneNumber.addEventListener(evt, syncSubmitState);
				customerIdType && customerIdType.addEventListener(evt, syncSubmitState);
				customerIdNumber && customerIdNumber.addEventListener(evt, syncSubmitState);
				customerLocation && customerLocation.addEventListener(evt, syncSubmitState);
				trackingId && trackingId.addEventListener(evt, syncSubmitState);
				sourceEl && sourceEl.addEventListener(evt, function(){ bindSourceBankToggle(); toggleSideUI(); syncSubmitState(); });
				sourceBankEl && sourceBankEl.addEventListener(evt, syncSubmitState);
				amountInvolvedReceive && amountInvolvedReceive.addEventListener(evt, syncSubmitState);
				pickupIdCard && pickupIdCard.addEventListener(evt, syncSubmitState);
				pickupPhone && pickupPhone.addEventListener(evt, syncSubmitState);
				payType && payType.addEventListener(evt, syncSubmitState);
				amountRequested && amountRequested.addEventListener(evt, syncSubmitState);
				paymentMethod && paymentMethod.addEventListener(evt, function(){ syncPayoutReceiptUi(); toggleSideUI(); syncPayDenosControls(); syncSubmitState(); });
				confirmedBy && confirmedBy.addEventListener(evt, syncSubmitState);
				paidOut && paidOut.addEventListener(evt, syncSubmitState);
				denosJson && denosJson.addEventListener(evt, syncSubmitState);
			});
			paymentMethod && paymentMethod.addEventListener('change', function(){
				syncPayoutReceiptUi();
				syncPayDenosControls();
				toggleSideUI();
				syncSubmitState();
			});

			let lookupTimer = null;
			function triggerLookup(){
				const s = (side && side.value) ? side.value : '';
				if(s !== 'pay') return;
				if(lookupTimer) clearTimeout(lookupTimer);
				lookupTimer = setTimeout(doLookup, 250);
			}
			async function doLookup(){
				const s = (side && side.value) ? side.value : '';
				if(s !== 'pay') return;
				const customer = (phoneNumber && phoneNumber.value) ? phoneNumber.value.trim() : '';
				const tracking = (trackingId && trackingId.value) ? trackingId.value.trim() : '';
				if(customer === '' || tracking === '') return;
				if(lookupError) lookupError.textContent = 'Looking up...';
				try{
					const url = `<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/lookup?customer_number=${encodeURIComponent(customer)}&tracking_id=${encodeURIComponent(tracking)}`;
					const res = await fetch(url, {headers: {'Accept':'application/json'}});
					const json = await res.json();
					if(!json || !json.success){
						if(lookupError) lookupError.textContent = (json && json.message) ? json.message : 'No matching receive record found';
						if(senderDetails) senderDetails.value = '';
						if(!payoutAssignmentLocked && amountInvolvedPay) amountInvolvedPay.value = '';
						syncComputed();
						syncSubmitState();
						return;
					}
					if(lookupError) lookupError.textContent = '';
					if(senderDetails) senderDetails.value = (json.data && json.data.customer_details) ? json.data.customer_details : '';
					if(customerIdType) customerIdType.value = (json.data && json.data.customer_id_type) ? json.data.customer_id_type : '';
					if(customerIdNumber) customerIdNumber.value = (json.data && json.data.customer_id_number) ? json.data.customer_id_number : '';
					if(customerLocation) customerLocation.value = (json.data && json.data.customer_location) ? json.data.customer_location : '';
					if(customerIdInput) customerIdInput.value = (json.data && json.data.customer_id) ? String(json.data.customer_id) : '';
					if(!payoutAssignmentLocked && amountInvolvedPay) amountInvolvedPay.value = (json.data && typeof json.data.amount_involved !== 'undefined') ? (parseFloat(json.data.amount_involved || 0).toFixed(2)) : '';
					if(!payoutAssignmentLocked && amountInvolvedReceive) amountInvolvedReceive.value = (amountInvolvedPay && amountInvolvedPay.value) ? amountInvolvedPay.value : '';
					syncComputed();
					syncPayType();
					if(customerIdInput && String(customerIdInput.value || '').trim() !== ''){
						setLedgerState(true, 'Ledger match found. Details loaded.');
					}
					syncSubmitState();
				}catch(e){
					if(lookupError) lookupError.textContent = 'Lookup failed';
					if(customerIdInput) customerIdInput.value = '';
					syncSubmitState();
				}
			}

			phoneNumber && phoneNumber.addEventListener('input', triggerLookup);
			trackingId && trackingId.addEventListener('input', triggerLookup);

			let customerSearchTimer = null;
			function renderExistingCustomerResults(rows, message){
				if(!existingCustomerSearchResults) return;
				existingCustomerSearchResults.innerHTML = '';
				const items = Array.isArray(rows) ? rows : [];
				if(items.length === 0){
					const emptyState = document.createElement('div');
					emptyState.className = 'px-3 py-2 text-muted small';
					emptyState.textContent = message || 'No approved customer found.';
					existingCustomerSearchResults.appendChild(emptyState);
					existingCustomerSearchResults.style.display = '';
					return;
				}
				items.forEach((row) => {
					const btn = document.createElement('button');
					btn.type = 'button';
					btn.className = 'customer-search-option';
					const title = document.createElement('strong');
					title.textContent = row.full_name || 'Unnamed customer';
					const meta = document.createElement('span');
					meta.className = 'meta';
					const metaParts = [];
					if (row.phone_number) metaParts.push(row.phone_number);
					if (row.id_number) metaParts.push(row.id_number);
					if (row.location) metaParts.push(row.location);
					meta.textContent = metaParts.join(' | ');
					btn.appendChild(title);
					btn.appendChild(meta);
					btn.addEventListener('click', function(){
						fillCustomerFields(row);
						setCustomerFieldsEditable(false);
						hideExistingCustomerResults();
						setLedgerState(true, 'Approved customer selected. Details loaded.');
						syncCustomerDetailVisibility();
						syncSubmitState();
					});
					existingCustomerSearchResults.appendChild(btn);
				});
				existingCustomerSearchResults.style.display = '';
			}
			async function doExistingCustomerSearch(){
				if(!isReceiveExistingCustomer() || !existingCustomerSearch) return;
				const term = existingCustomerSearch.value ? existingCustomerSearch.value.trim() : '';
				if(term.length < 2){
					hideExistingCustomerResults();
					if(!(customerIdInput && String(customerIdInput.value || '').trim() !== '')){
						setLedgerState(false, term === '' ? 'Search and select an approved customer before saving.' : 'Type at least 2 characters to search.');
					}
					syncSubmitState();
					return;
				}
				setLedgerState(false, 'Searching approved customers...');
				try{
					const url = `${customerSearchUrl}?term=${encodeURIComponent(term)}`;
					const res = await fetch(url, {headers: {'Accept':'application/json'}});
					const json = await res.json();
					if(!json || !json.success){
						hideExistingCustomerResults();
						setLedgerState(false, (json && json.message) ? json.message : 'Customer search failed.');
						syncSubmitState();
						return;
					}
					const rows = Array.isArray(json.data) ? json.data : [];
					renderExistingCustomerResults(rows, 'No approved customer matches this search.');
					if(rows.length === 0){
						setLedgerState(false, 'No approved customer matches this search.');
					}else{
						setLedgerState(false, 'Select an approved customer from the search results.');
					}
					syncSubmitState();
				}catch(e){
					hideExistingCustomerResults();
					setLedgerState(false, 'Customer search failed.');
					syncSubmitState();
				}
			}
			function triggerExistingCustomerSearch(){
				if(!isReceiveExistingCustomer()){
					hideExistingCustomerResults();
					return;
				}
				if(customerSearchTimer) clearTimeout(customerSearchTimer);
				if(customerIdInput) customerIdInput.value = '';
				if(senderDetails) senderDetails.value = '';
				if(phoneNumber) phoneNumber.value = '';
				if(customerIdNumber) customerIdNumber.value = '';
				if(customerLocation) customerLocation.value = '';
				if(customerIdType) customerIdType.value = 'Ghana Card';
				customerSearchTimer = setTimeout(doExistingCustomerSearch, 250);
			}

			existingCustomerSearch && existingCustomerSearch.addEventListener('input', triggerExistingCustomerSearch);
			existingCustomerSearch && existingCustomerSearch.addEventListener('focus', function(){
				if(isReceiveExistingCustomer() && String(existingCustomerSearch.value || '').trim().length >= 2 && existingCustomerSearchResults && existingCustomerSearchResults.innerHTML.trim() !== ''){
					existingCustomerSearchResults.style.display = '';
				}
			});
			document.addEventListener('click', function(e){
				if(!existingCustomerSearchWrap) return;
				if(existingCustomerSearchWrap.contains(e.target)) return;
				hideExistingCustomerResults();
			});
			customerTypeEl && customerTypeEl.addEventListener('change', function(){
				resetCustomerFields();
				if(existingCustomerSearch) existingCustomerSearch.value = '';
				hideExistingCustomerResults();
				if(currentCustomerType() === ''){
					setLedgerState(false, 'Select customer type to continue.');
				}else if(isReceiveNewCustomer()){
					setLedgerState(false, 'Enter the new customer details and submit the registration first.');
				}else{
					setLedgerState(false, 'Search and select an approved customer before saving.');
				}
				toggleSideUI();
			});

			(function(){
				const countInputs = document.querySelectorAll('#cashPickupDenosModal .count-input');
				const totalCountField = document.getElementById('total-count');
				const totalVolumeField = document.getElementById('total-volume');
				const confirmBtn = document.getElementById('denos_confirm');
				const resetBtn = document.getElementById('denos_reset');
				if(!countInputs || countInputs.length === 0 || !confirmBtn || !totalVolumeField) return;

				function updateTotals(){
					let totalCount = 0;
					let totalVolume = 0;
					const currency = [];
					countInputs.forEach((input) => {
						const row = input.closest('tr');
						const denomination = parseFloat(row.getAttribute('data-denomination'));
						let count = parseFloat(input.value) || 0;
						count = Math.floor(count);
						input.value = count;
						const volumeField = row.querySelector('.volume-input');
						const volume = denomination * count;
						volumeField.value = volume.toFixed(2);
						totalCount += count;
						totalVolume += volume;
						if(count > 0){
							currency.push({currency:'GHS', denomination: denomination, count: count, volume: parseFloat(volume.toFixed(2))});
						}
					});
					if(totalCountField) totalCountField.value = totalCount;
					totalVolumeField.value = totalVolume.toFixed(2);
					if(denosJson) denosJson.value = JSON.stringify(currency);
					syncSubmitState();
				}

				countInputs.forEach((input) => input.addEventListener('input', updateTotals));
				resetBtn && resetBtn.addEventListener('click', function(){
					countInputs.forEach((input) => {
						input.value = '';
						const row = input.closest('tr');
						const volumeField = row.querySelector('.volume-input');
						if(volumeField) volumeField.value = '';
					});
					updateTotals();
				});
				confirmBtn.addEventListener('click', function(){
					updateTotals();
					const total = parseFloat(totalVolumeField.value || '0') || 0;
					if(amountInvolvedReceive) amountInvolvedReceive.value = total.toFixed(2);
					syncComputed();
					syncSubmitState();
					if (typeof window.bootstrap !== 'undefined' && window.bootstrap.Modal) {
						const modalEl = document.getElementById('cashPickupDenosModal');
						const m = window.bootstrap.Modal.getInstance(modalEl);
						m && m.hide();
					}
				});
				updateTotals();
			})();

			(function(){
				const modalEl = document.getElementById('cashPickupDenosOutModal');
				const countInputs = document.querySelectorAll('#cashPickupDenosOutModal .count-input-out');
				const totalCountField = document.getElementById('total-count-out');
				const totalVolumeField = document.getElementById('total-volume-out');
				const confirmBtn = document.getElementById('denos_confirm_out');
				const resetBtn = document.getElementById('denos_reset_out');
				const lblRequested = document.getElementById('payDenosRequested');
				const lblCharge = document.getElementById('payDenosCharge');
				const lblPayable = document.getElementById('payDenosPayable');
				const lblDiff = document.getElementById('payDenosDiff');
				if(!modalEl || !countInputs || countInputs.length === 0 || !confirmBtn || !totalVolumeField) return;

				function setHeader(total){
					const reqAmt = toNumber(amountRequested ? amountRequested.value : 0);
					const ch = toNumber(charge ? charge.value : 0);
					const pay = Math.max(0, reqAmt - ch);
					if(lblRequested) lblRequested.textContent = fmt(reqAmt);
					if(lblCharge) lblCharge.textContent = fmt(ch);
					if(lblPayable) lblPayable.textContent = fmt(pay);
					const diff = (total - pay);
					if(lblDiff){
						if (Math.abs(diff) <= 0.009) {
							lblDiff.className = 'small text-success';
							lblDiff.textContent = 'Cash matches amount payable.';
						} else if (diff > 0) {
							lblDiff.className = 'small text-warning';
							lblDiff.textContent = 'Over by ' + fmt(diff);
						} else {
							lblDiff.className = 'small text-danger';
							lblDiff.textContent = 'Under by ' + fmt(Math.abs(diff));
						}
					}
				}

				function updateTotals(){
					let totalCount = 0;
					let totalVolume = 0;
					const currency = [];
					countInputs.forEach((input) => {
						const row = input.closest('tr');
						const denomination = parseFloat(row.getAttribute('data-denomination'));
						let count = parseFloat(input.value) || 0;
						count = Math.floor(count);
						input.value = count;
						const volumeField = row.querySelector('.volume-input-out');
						const volume = denomination * count;
						volumeField.value = volume.toFixed(2);
						totalCount += count;
						totalVolume += volume;
						if(count > 0){
							currency.push({currency:'GHS', denomination: denomination, count: count, volume: parseFloat(volume.toFixed(2))});
						}
					});
					if(totalCountField) totalCountField.value = totalCount;
					totalVolumeField.value = totalVolume.toFixed(2);
					if(denosJson) denosJson.value = JSON.stringify(currency);
					setHeader(totalVolume);
					updatePayDenosSummary(totalVolume);
					syncSubmitState();
				}

				countInputs.forEach((input) => input.addEventListener('input', updateTotals));
				resetBtn && resetBtn.addEventListener('click', function(){
					countInputs.forEach((input) => {
						input.value = '';
						const row = input.closest('tr');
						const volumeField = row.querySelector('.volume-input-out');
						if(volumeField) volumeField.value = '';
					});
					updateTotals();
				});
				confirmBtn.addEventListener('click', function(){
					updateTotals();
					const total = parseFloat(totalVolumeField.value || '0') || 0;
					if (payoutAssignmentLocked) {
						if (Math.abs(total - payoutAssignedPayable) > 0.009) {
							alert('Cash selected must match the assigned payable amount.');
							return;
						}
						if (paidOut) paidOut.value = payoutAssignedPayable.toFixed(2);
					} else if(paidOut) {
						paidOut.value = total.toFixed(2);
					}
					syncSubmitState();
					if (typeof window.bootstrap !== 'undefined' && window.bootstrap.Modal) {
						const m = window.bootstrap.Modal.getInstance(modalEl);
						m && m.hide();
					}
				});

				modalEl.addEventListener('show.bs.modal', function(){
					setHeader(parseFloat(totalVolumeField.value || '0') || 0);
				});

				updateTotals();
			})();

			wireUploadCard({
				inputId: 'idcard_photo',
				cardId: 'idcardPhotoPreviewCard',
				previewId: 'idcardPhotoPreview',
				placeholderId: 'idcardPhotoPreviewPlaceholder',
				errorSelector: '.js-idcard-photo-error',
				aspectRatio: 1.5,
				modalTitle: 'Crop ID card photo'
			});
			wireUploadCard({
				inputId: 'customer_photo',
				cardId: 'customerPhotoPreviewCard',
				previewId: 'customerPhotoPreview',
				placeholderId: 'customerPhotoPreviewPlaceholder',
				errorSelector: '.js-customer-photo-error',
				aspectRatio: 1.5,
				modalTitle: 'Crop customer photo'
			});
			wireUploadCard({
				inputId: 'receive_proof_photo',
				cardId: 'receiveProofPhotoPreviewCard',
				previewId: 'receiveProofPhotoPreview',
				placeholderId: 'receiveProofPhotoPreviewPlaceholder',
				errorSelector: '.js-receive-proof-photo-error',
				aspectRatio: 1.5,
				modalTitle: 'Crop cash receive proof'
			});

			syncPayoutReceiptUi();
			bindSourceBankToggle();
			toggleSideUI();
			syncComputed();
			syncPayDenosControls();
			syncSubmitState();
			if(payType){
				payType.addEventListener('change', syncPayType);
			}
			if(amountRequested){
				amountRequested.addEventListener('input', syncPaidOut);
			}
			if(charge){
				charge.addEventListener('input', syncPaidOut);
			}
			paidOut && paidOut.addEventListener('input', syncSubmitState);
			triggerLookup();
			if(customerIdInput && String(customerIdInput.value || '').trim() !== ''){
				setLedgerState(true, 'Approved customer selected. Details loaded.');
			} else if(isReceiveNewCustomer()){
				setLedgerState(false, 'Enter the new customer details and submit the registration first.');
			} else if(isReceiveExistingCustomer()){
				setLedgerState(false, 'Search and select an approved customer before saving.');
				if(existingCustomerSearch && String(existingCustomerSearch.value || '').trim().length >= 2){
					triggerExistingCustomerSearch();
				}
			} else if(currentSide() === 'receive'){
				setLedgerState(false, 'Select customer type to continue.');
			}
			applyPayoutMode();
			syncPayType();
			syncSubmitState();
		})();
	</script>

	<?php if(!empty($payout_mode) && isset($remaining_balance)): ?>
		<?php $payoutCardAmount = (float)(old_value('assigned_payout_amount') ?: $remaining_balance); ?>
		<div class="row g-3 col-md-10 col-lg-10 mx-auto mt-3">
			<div class="col-12">
				<div class="card border-0 shadow-sm">
					<div class="card-body">
						<div class="d-flex justify-content-between align-items-center">
							<div>
								<div class="text-muted"><?=!empty(old_value('assigned_payout_amount')) ? 'Assigned balance' : 'Remaining balance'?></div>
								<div class="fs-4 fw-bold">GH₵ <?=number_format((float)$payoutCardAmount, 2)?></div>
							</div>
							<div class="text-end">
								<div class="text-muted"><?=!empty(old_value('assigned_payout_amount')) ? 'Assigned total' : 'Received total'?></div>
								<div class="fw-semibold">GH₵ <?=number_format((float)old_value('amount_involved'), 2)?></div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<?php if(!empty($payouts) && is_array($payouts)): ?>
				<div class="col-12">
					<div class="card border-0 shadow-sm">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center mb-2">
								<div class="fw-semibold">Payout history</div>
								<div class="d-flex gap-2">
									<a class="btn btn-sm btn-outline-secondary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export?<?=http_build_query(['resource' => 'payouts', 'id' => (int)old_value('receive_id'), 'format' => 'pdf'])?>">PDF</a>
									<a class="btn btn-sm btn-outline-success" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export?<?=http_build_query(['resource' => 'payouts', 'id' => (int)old_value('receive_id'), 'format' => 'xlsx'])?>">Excel</a>
									<a class="btn btn-sm btn-outline-primary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export?<?=http_build_query(['resource' => 'payouts', 'id' => (int)old_value('receive_id'), 'format' => 'csv'])?>">CSV</a>
								</div>
							</div>
							<div class="table-responsive">
								<table class="table table-sm">
									<thead>
										<tr>
											<th>Date</th>
											<th class="text-end">Requested</th>
											<th class="text-end">Charge</th>
											<th class="text-end">Paid out</th>
											<th>Method</th>
											<th>Confirmed by</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach($payouts as $p): ?>
											<tr>
												<td><?=esc($p->date_created ?? '')?></td>
												<td class="text-end"><?=number_format((float)($p->amount_requested ?? 0), 2)?></td>
												<td class="text-end"><?=number_format((float)($p->pickup_charge ?? 0), 2)?></td>
												<td class="text-end"><?=number_format((float)($p->amount_paid_out ?? 0), 2)?></td>
												<td><?=esc($p->payment_method ?? '')?></td>
												<td><?=esc($p->confirmed_by ?? '')?></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

<?php else: ?>
	<div class="alert alert-danger text-center">
		Access denied. You don’t have permission for this action.
	</div>
<?php endif ?>

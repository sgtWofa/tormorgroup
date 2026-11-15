<?php
	$errors = $errors ?? [];
	$formCurrency = strtoupper((string)($_POST['currency'] ?? 'GHS'));
	if (!in_array($formCurrency, ['GHS', 'CFA'], true)) {
		$formCurrency = 'GHS';
	}
	$formCategory = pettycash_normalize_category($_POST['category'] ?? '') ?: 'GENERAL';
	$formItemsJson = (string)($_POST['items_json'] ?? '[]');
?>
<div class="d-flex justify-content-between align-items-center gap-2 my-3">
	<div>
		<h4 class="mb-1">New Petty Cash Transaction</h4>
		<div class="text-muted small">Create and assign a petty cash instruction with itemized budget lines that total automatically.</div>
	</div>
	<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>" class="btn btn-outline-secondary btn-sm">Back</a>
</div>

<?php if (!empty($errors['general'])): ?>
	<div class="alert alert-danger"><?=$errors['general']?></div>
<?php endif; ?>

<form method="post" class="card shadow-sm border-0" id="pettyCashForm">
	<div class="card-body row g-3">
		<!-- <div class="col-md-6">
			<label class="form-label">Purpose / Title</label>
			<input type="text" name="title" class="form-control" value="<?=esc($_POST['title'] ?? '')?>" placeholder="What is this petty cash for?">
			<?php if (!empty($errors['title'])): ?><small class="text-danger"><?=$errors['title']?></small><?php endif; ?>
		</div> -->
		<div class="col-md-6">
			<label class="form-label">Category</label>
			<select name="category" id="petty_category" class="form-select">
				<?php foreach (pettycash_categories() as $categoryValue => $categoryLabel): ?>
					<option value="<?=esc($categoryValue)?>" <?=$formCategory === $categoryValue ? 'selected' : ''?>><?=esc($categoryLabel)?></option>
				<?php endforeach; ?>
			</select>
			<?php if (!empty($errors['category'])): ?><small class="text-danger"><?=$errors['category']?></small><?php endif; ?>
		</div>
		<div class="col-md-6">
			<label class="form-label">Currency</label>
			<select name="currency" id="petty_currency" class="form-select">
				<option value="GHS" <?=$formCurrency === 'GHS' ? 'selected' : ''?>>GHS</option>
				<option value="CFA" <?=$formCurrency === 'CFA' ? 'selected' : ''?>>CFA</option>
			</select>
			<?php if (!empty($errors['currency'])): ?><small class="text-danger"><?=$errors['currency']?></small><?php endif; ?>
		</div>
		<div class="col-12">
			<div class="border rounded-3 p-3 bg-body-tertiary">
				<div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
					<div>
						<div class="fw-semibold">Budget Items</div>
						<div class="text-muted small">Add one or more petty cash items. The total budget updates automatically from these rows.</div>
					</div>
					<button type="button" class="btn btn-outline-primary btn-sm" id="petty_add_item">
						<i class="fa fa-plus"></i> Add Item
					</button>
				</div>

				<input type="hidden" name="items_json" id="petty_items_json" value="<?=htmlspecialchars($formItemsJson, ENT_QUOTES)?>">

				<div class="table-responsive mt-3">
					<table class="table table-sm align-middle mb-0">
						<thead>
							<tr>
								<th>Item</th>
								<th style="width: 180px;">Budgeted Amount</th>
								<th style="width: 70px;"></th>
							</tr>
						</thead>
						<tbody id="petty_items_rows"></tbody>
					</table>
				</div>
				<?php if (!empty($errors['items_json'])): ?><small class="text-danger d-block mt-2"><?=$errors['items_json']?></small><?php endif; ?>
			</div>
		</div>

		<div class="col-12">
			<label class="form-label">Description</label>
			<textarea name="description" rows="3" class="form-control" placeholder="Add any vendor guidance, purchase context, or budget note for the assignee..."><?=esc($_POST['description'] ?? '')?></textarea>
			<?php if (!empty($errors['description'])): ?><small class="text-danger"><?=$errors['description']?></small><?php endif; ?>
		</div>
		
		<div class="col-md-6">
			<label class="form-label">Assign To</label>
			<select name="assigned_user_id" class="form-select">
				<option value="">Select user</option>
				<?php $assignedValue = (int)($_POST['assigned_user_id'] ?? 0); ?>
				<?php foreach ($assignableUsers as $userRow): ?>
					<?php $userName = trim((string)($userRow->first_name ?? '') . ' ' . (string)($userRow->last_name ?? '')); ?>
					<option value="<?=esc($userRow->id)?>" <?=$assignedValue === (int)$userRow->id ? 'selected' : ''?>>
						<?=esc($userName !== '' ? $userName : ('User #' . (int)$userRow->id))?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php if (!empty($errors['assigned_user_id'])): ?><small class="text-danger"><?=$errors['assigned_user_id']?></small><?php endif; ?>
			<?php if (empty($assignableUsers)): ?>
				<small class="text-warning d-block mt-1">No eligible users with active cashdrawers were found for assignment.</small>
			<?php endif; ?>
		</div>
		<div class="col-md-6">
			<label class="form-label">Budget Amount (Auto Total)</label>
			<input type="text" name="budget_amount" id="petty_budget_amount" class="form-control" value="<?=esc($_POST['budget_amount'] ?? '0.00')?>" readonly>
			<?php if (!empty($errors['budget_amount'])): ?><small class="text-danger"><?=$errors['budget_amount']?></small><?php endif; ?>
		</div>
	</div>
	<div class="card-footer bg-transparent d-flex justify-content-end gap-2">
		<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>" class="btn btn-outline-secondary">Cancel</a>
		<button class="btn btn-warning" <?=empty($assignableUsers) ? 'disabled' : ''?>>Create Transaction</button>
	</div>
	<?=csrf()?>
</form>

<script>
(function(){
	var currencyEl = document.getElementById('petty_currency');
	var itemsJsonEl = document.getElementById('petty_items_json');
	var rowsEl = document.getElementById('petty_items_rows');
	var addBtn = document.getElementById('petty_add_item');
	var totalEl = document.getElementById('petty_budget_amount');
	var items = [];

	function decimals(){
		return currencyEl && currencyEl.value === 'CFA' ? 0 : 2;
	}

	function money(value){
		var num = parseFloat(value);
		if(isNaN(num)) num = 0;
		return num.toFixed(decimals());
	}

	function roundValue(value){
		var factor = Math.pow(10, decimals());
		var num = parseFloat(value);
		if(isNaN(num)) num = 0;
		return Math.round(num * factor) / factor;
	}

	function parseInitial(){
		try{
			var parsed = JSON.parse(itemsJsonEl ? (itemsJsonEl.value || '[]') : '[]');
			if(Array.isArray(parsed)) return parsed;
		}catch(e){}
		return [];
	}

	function sanitizeItems(){
		var clean = [];
		var total = 0;
		var inputs = rowsEl ? rowsEl.querySelectorAll('tr') : [];
		inputs.forEach(function(row){
			var nameEl = row.querySelector('[data-field="item_name"]');
			var amountEl = row.querySelector('[data-field="amount"]');
			var name = nameEl ? String(nameEl.value || '').trim() : '';
			var amount = roundValue(amountEl ? amountEl.value : 0);
			if(name !== '' && amount > 0){
				clean.push({ item_name: name, amount: amount });
				total += amount;
			}
		});
		total = roundValue(total);
		if(itemsJsonEl) itemsJsonEl.value = JSON.stringify(clean);
		if(totalEl) totalEl.value = money(total);
	}

	function bindRowEvents(row){
		var nameEl = row.querySelector('[data-field="item_name"]');
		var amountEl = row.querySelector('[data-field="amount"]');
		var removeBtn = row.querySelector('[data-action="remove"]');
		if(nameEl) nameEl.addEventListener('input', sanitizeItems);
		if(amountEl) amountEl.addEventListener('input', sanitizeItems);
		if(removeBtn){
			removeBtn.addEventListener('click', function(){
				row.remove();
				if(!rowsEl.children.length){
					appendRow({ item_name: '', amount: '' });
				}
				sanitizeItems();
			});
		}
	}

	function appendRow(item){
		var tr = document.createElement('tr');
		tr.innerHTML = ''
			+ '<td><input type="text" class="form-control" data-field="item_name" placeholder="Item name" value="' + escapeHtml(item.item_name || '') + '"></td>'
			+ '<td><input type="number" min="0" step="' + (decimals() === 0 ? '1' : '0.01') + '" class="form-control" data-field="amount" placeholder="0.00" value="' + escapeHtml(item.amount !== undefined && item.amount !== null && item.amount !== '' ? item.amount : '') + '"></td>'
			+ '<td class="text-end"><button type="button" class="btn btn-outline-danger btn-sm" data-action="remove"><i class="fa fa-trash"></i></button></td>';
		rowsEl.appendChild(tr);
		bindRowEvents(tr);
	}

	function escapeHtml(value){
		return String(value).replace(/[&<>"']/g, function(m){
			return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m];
		});
	}

	function rebuild(){
		var parsed = parseInitial();
		rowsEl.innerHTML = '';
		if(!parsed.length){
			appendRow({ item_name: '', amount: '' });
		}else{
			parsed.forEach(function(item){ appendRow(item || {}); });
		}
		sanitizeItems();
	}

	if(addBtn){
		addBtn.addEventListener('click', function(){
			appendRow({ item_name: '', amount: '' });
		});
	}

	if(currencyEl){
		currencyEl.addEventListener('change', function(){
			var amountInputs = rowsEl.querySelectorAll('[data-field="amount"]');
			amountInputs.forEach(function(input){
				if(String(input.value || '').trim() !== ''){
					input.value = money(input.value);
				}
				input.step = decimals() === 0 ? '1' : '0.01';
			});
			sanitizeItems();
		});
	}

	rebuild();
})();
</script>

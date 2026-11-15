<?php if(user_can('edit_momoremit')):?>
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
	</style>

	<?php
		$seedDenosJson = '[]';
		$seedChangeJson = '[]';
		$seedReceivedTotal = 0.0;
		$seedChangeTotal = 0.0;
		$seedTransId = (!empty($row) && !empty($row->id)) ? (int)$row->id : 0;
		if ($seedTransId > 0) {
			try {
				$dbSeed = new \Core\Database;
				$rowsSeed = $dbSeed->query(
					"SELECT denomination, `count`, volume FROM denos WHERE trans_id = :trans_id ORDER BY denomination DESC",
					['trans_id' => $seedTransId]
				);
				if (empty($rowsSeed)) {
					$rowsSeed = $dbSeed->query(
						"SELECT denomination, `count`, volume FROM airtimebundles_denos WHERE trans_id = :trans_id ORDER BY denomination DESC",
						['trans_id' => $seedTransId]
					);
				}
				if (!empty($rowsSeed)) {
					$receivedSeed = [];
					$changeSeed = [];
					$receivedIdx = 0;
					$changeIdx = 0;
					foreach ($rowsSeed as $r) {
						$denomination = (float)($r->denomination ?? 0);
						$count = (int)($r->count ?? 0);
						$volume = (float)($r->volume ?? 0);
						$isChangeRow = ($count < 0 || $volume < 0);
						$seedRow = [
							'currency' => 'GHS',
							'row_index' => $isChangeRow ? $changeIdx : $receivedIdx,
							'denomination' => abs($denomination),
							'count' => abs($count),
							'volume' => abs($volume),
						];
						if ($isChangeRow) {
							$changeSeed[] = $seedRow;
							$seedChangeTotal += abs($volume);
							$changeIdx++;
						} else {
							$receivedSeed[] = $seedRow;
							$seedReceivedTotal += abs($volume);
							$receivedIdx++;
						}
					}
					$seedDenosJson = json_encode($receivedSeed);
					$seedChangeJson = json_encode($changeSeed);
				}
			} catch (\Throwable $e) {
				$seedDenosJson = '[]';
				$seedChangeJson = '[]';
			}
		}

		$seedAmountPaid = old_value('amount_paid', $row->amount_paid ?? ($seedReceivedTotal > 0 ? number_format($seedReceivedTotal, 2, '.', '') : ''));
		$seedCashReceived = old_value('cash_received_amount', $seedAmountPaid);
		$seedChangeRequired = old_value('change_required', $seedChangeTotal > 0 ? number_format($seedChangeTotal, 2, '.', '') : '');
		$isSentRecord = airtimebundle_status_is_sent($row->transaction_status ?? '');
	?>

	<?php if(!empty($row)): ?>

		<form method="post" enctype="multipart/form-data" class="mb-5">
		
			<div class="row g-2 col-md-8 col-lg-5 mx-auto shadow p-2 rounded">
				
				<?=csrf()?>
				<input type="hidden" id="cashdrawerDenos" value="<?=htmlspecialchars(json_encode($cashdrawerData['denos'] ?? []), ENT_QUOTES, 'UTF-8')?>">
				<input type="hidden" id="denos" name="denos" value="<?=htmlspecialchars((string)old_value('denos', $seedDenosJson ?? '[]'), ENT_QUOTES, 'UTF-8')?>">

				<h4 class="text-center">
					<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
					UPDATE AIRTIME
				</h4>

				<?php if($isSentRecord):?>
				<div class="col-md-12">
					<div class="alert alert-warning py-2 mb-2">
						This sent transaction is locked. Only the status can be changed to Rejected, and rejecting it will reverse its drawer impacts.
					</div>
				</div>
				<?php endif;?>

			    <!-- Transaction Type Selection -->
			    <div class="col-md-12">
			      <label for="transaction_type" class="form-label">Transaction type</label>
			      <select class="form-select" id="transaction_type" name="transaction_type" disabled>
			        <option <?=old_select('transaction_type','Airtime', $row->transaction_type)?> value="Airtime" selected>Airtime</option>
			      </select>
			      <?php if(!empty($errors['transaction_type'])):?>
			        <small class="text-danger"><?=$errors['transaction_type']?></small>
			      <?php endif?>
			    </div>

			    <!-- Category Selection -->
			    <div class="col-md-12">
			      <label for="category" class="form-label">Category</label>
			      <select class="form-select" id="category" name="category" disabled>
			        <option value="" >---Select Category---</option>
			        <option <?=old_select('category','Airtime', $row->category ?? '')?> value="Airtime" >Airtime</option>
			        <option <?=old_select('category','Credit Transfer', $row->category ?? '')?> value="Credit Transfer" >Credit Transfer</option>
			        <option <?=old_select('category','EVD', $row->category ?? '')?> value="EVD" >EVD</option>
			      </select>
			      <?php if(!empty($errors['category'])):?>
			        <small class="text-danger"><?=$errors['category']?></small>
			      <?php endif?>
			    </div>
			 
					<!-- Network Selection -->
			    <div class="col-md-12">
			      <label for="network" class="form-label">Network</label>
			      <input class="form-select" name="network" id="network" value="<?=old_value('network', $row->network ?? '');?>" readonly>
			      <?php if(!empty($errors['network'])):?>
			        <small class="text-danger"><?=$errors['network']?></small>
			      <?php endif?>
					  <?php if(!empty($errors['wallet_in_id'])):?>
							  <small class="text-danger"><?=$errors['wallet_in_id']?></small>
							<?php endif?>     
						  <small class="text-danger js-wallet-in-error"></small>
			    </div>

			    <!-- Receiver's Number Input -->
			    <div class="col-md-12">
			      <label for="receiver" class="form-label">Receiver's Number</label>
			      <input name="receiver" value="<?=old_value('receiver', $row->receiver ?? '')?>" type="text" class="form-control" id="receiver" placeholder="Enter receiver number" readonly>
			      <?php if(!empty($errors['receiver'])):?>
			        <small class="text-danger"><?=$errors['receiver']?></small>
			      <?php endif?>
			    </div>

			    <!-- Reseller's Number Input -->
			    <div class="col-md-12 d-none reseller_number" id="reseller_number">
			      <label for="reseller_number" class="form-label">Reseller's Number</label>
			      <input name="reseller_number" value="<?=old_value('reseller_number', $row->reseller_number ?? '')?>" type="text" class="form-control" id="reseller_number" placeholder="Enter reseller's number" readonly>
			      <?php if(!empty($errors['reseller_number'])):?>
			        <small class="text-danger"><?=$errors['reseller_number']?></small>
			      <?php endif?>
			    </div>

			    <!-- Commission Input -->
			    <div class="col-md-12 d-none commission" id="commission">
			      <label for="commission" class="form-label">Commission</label>
			      <input name="commission" value="<?=old_value('commission', $row->commission ?? '')?>" type="text" class="form-control" id="commission" placeholder="Enter commission number">
			      <?php if(!empty($errors['commission'])):?>
			        <small class="text-danger"><?=$errors['commission']?></small>
			      <?php endif?>
			    </div>

			    <!-- Receiver's Number Input -->
			    <div class="col-md-12">
			      <label for="phone" class="form-label">Walk in Customer's Number</label>
			      <input name="phone" value="<?=old_value('phone', $row->phone ?? '')?>" type="text" class="form-control" id="phone" placeholder="Enter client's phone number" readonly>
			      <?php if(!empty($errors['phone'])):?>
			        <small class="text-danger"><?=$errors['phone']?></small>
			      <?php endif?>
			    </div>

			    <!-- Load/Amount -->
			    <div class="col-md-12">
			      <label for="amount_input" class="form-label">Load/Amount</label>
				  <input name="amount" value="<?=old_value('amount', $row->amount ?? '')?>" type="number" step="0.01" class="form-control" id="amount_input" placeholder="Enter amount" readonly>
			      <?php if(!empty($errors['amount'])):?>
			        <small class="text-danger"><?=$errors['amount']?></small>
			      <?php endif?>
					<?php if(!empty($errors['wallet_out_id'])):?>
					  <small class="text-danger"><?=$errors['wallet_out_id']?></small>
					<?php endif?>
			    </div>

			    <!-- Amount Paid Input with Calculator Modal Trigger -->
			    <div class="col-md-12">
			      <label for="amount_paid_input" class="form-label">Amount Paid</label>
				  <label class="input-group" data-bs-toggle="modal" data-bs-target="#staticBackdrop">
					<i class="fa-solid fa-calculator input-group-text text-warning"></i>
					<input name="amount_paid" value="<?=htmlspecialchars((string)$seedAmountPaid, ENT_QUOTES, 'UTF-8')?>" type="number" step="0.01" class="form-control" id="amount_paid_input" placeholder="Enter amount paid" readonly>
					<input type="hidden" id="change_denominations" name="change_denominations" value="<?=htmlspecialchars((string)old_value('change_denominations', $seedChangeJson ?? '[]'), ENT_QUOTES, 'UTF-8')?>">
					<input type="hidden" id="change_required" name="change_required" value="<?=htmlspecialchars((string)$seedChangeRequired, ENT_QUOTES, 'UTF-8')?>">
					<input type="hidden" id="cash_received_amount" name="cash_received_amount" value="<?=htmlspecialchars((string)$seedCashReceived, ENT_QUOTES, 'UTF-8')?>">
				  </label>
			      <?php if(!empty($errors['amount_paid'])):?>
			        <small class="text-danger d-block"><?=$errors['amount_paid']?></small>
			      <?php endif?>
			      <?php if(!empty($errors['change_denominations'])):?>
			        <small class="text-danger d-block"><?=$errors['change_denominations']?></small>
			      <?php endif?>
			      <?php if(!empty($errors['change_required'])):?>
			        <small class="text-danger d-block"><?=$errors['change_required']?></small>
			      <?php endif?>
			      <div id="airtimeChangeSummary" class="alert alert-warning py-2 px-3 mt-2 d-none">
			        <div class="d-flex flex-wrap align-items-center gap-2">
			          <div class="me-auto">
			            <div class="fw-semibold" id="airtimeChangeSummaryText">Change required: GHS 0.00</div>
			            <small class="text-muted" id="airtimeChangeSummaryNote">Select the denominations to give out so the net cash matches the payable amount.</small>
			          </div>
			          <button type="button" class="btn btn-sm btn-outline-primary" id="airtimeOpenChangeBtn">Select change</button>
			          <button type="button" class="btn btn-sm btn-outline-danger d-none" id="airtimeClearChangeBtn">Clear</button>
			        </div>
			      </div>
			    </div>

			    <div class="col-md-12">
			      <small class="text-danger js-amount-mismatch-error"></small>
			    </div>

			    <!-- Charge Display (dynamically calculated) -->
			    <div class="col-md-12">
			      <label for="balance_after" class="form-label">Balance after</label>
			      <div class="input-group">
			        <div id="currencySymbol" class="input-group-text">GH₵</div>
			        <input name="balance_after" value="<?=old_value('balance_after', $row->balance_after ?? '')?>" type="text" class="form-control" id="balance_after" placeholder="GH&cent;0.00" readonly>
			      </div>     
					  <?php if(!empty($errors['balance_after'])):?>
							  <small class="text-danger"><?=$errors['balance_after']?></small>
							<?php endif?> 
						  <small class="text-danger js-balance-after-error"></small>
			    </div>

				<div class="col-md-12  assigned">
				  <label for="assigned" class="form-label">Assigned to </label>
				  <select class="form-select assigned" name="assigned" id="assigned" disabled>
					  <option value="">---Select---</option>
					  <?php if(!empty($users)):?>
					  	<?php foreach ($users as $user): ?>				  		
					  		<option <?=old_select('assigned',$user->id, $row->assigned)?> value="<?=$user->id ?? ' ' ?>"><?=$user->first_name . ' '. $user->last_name ?? ''?> (<?=$user->roles[0] ?? ''?>)</option>
					  	<?php endforeach;?>
					  	<?php endif;?>
				  </select>
				  	<?php if(!empty($errors['assigned'])): ?>
					  <small class="text-danger"><?=$errors['assigned']?></small>
					<?php endif ?>
				</div>

				<!-- Paying Wallet -->
				<div class="mb-3 col-md-12 wallet_out_id d-none">
				  <label for="wallet_out_id" class="form-label">Paying Wallet</label>
				  <select class="form-select id_type" name="wallet_out_id" id="wallet_out_id" disabled>
				  	<!-- Options will display dynamically -->
				  </select>
				</div>

				<!-- Receiving Wallet -->
				<div class="mb-3 col-md-12 wallet_in_id d-none">
				  <label for="wallet_in_id" class="form-label">Receiving Wallet</label>
				  <select class="form-select id_type" name="wallet_in_id" id="wallet_in_id" disabled>
				  	<!-- Options will display dynamically -->
				  </select>
				</div>

				<?php require plugin_path('views/admin/entries/transaction_image_upload.php'); ?>

				<div class="mb-2 transaction_status">
				  <label for="transaction_status" class="form-label">Delivery Status</label>
				  <select class="form-select transaction_status" name="transaction_status">
					  <option value="">---Select---</option>
					  <?php if($isSentRecord):?>
					  <option <?=old_select('transaction_status','Sent', $row->transaction_status ?? '')?> value="Sent">Sent</option>
					  <option <?=old_select('transaction_status','Rejected', $row->transaction_status ?? '')?> value="Rejected">Rejected</option>
					  <?php else:?>
					  <option <?=old_select('transaction_status','Draft', $row->transaction_status ?? '')?> value="Draft">Draft</option>
					  <option <?=old_select('transaction_status','Pending', $row->transaction_status ?? '')?> value="Pending">Pending</option>
					  <option <?=old_select('transaction_status','Rejected', $row->transaction_status ?? '')?> value="Rejected">Rejected</option>
					  <option <?=old_select('transaction_status','Sent', $row->transaction_status ?? '')?> value="Sent">Sent</option>
					  <?php endif;?>
				  </select>				
					<?php if(!empty($errors['transaction_status'])):?>
					  <small class="text-danger"><?=$errors['transaction_status']?></small>
					<?php endif?>
				</div>

				<div class="mb-2 rejection_reason <?= strtoupper((string)old_value('transaction_status', $row->transaction_status ?? '')) === 'REJECTED' ? '' : 'd-none' ?>" id="airtimeRejectionReasonWrap">
				  <label for="rejection_reason" class="form-label">Rejection reason</label>
				  <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3" placeholder="State clearly why this transaction is being rejected."><?=esc(old_value('rejection_reason', $row->rejection_reason ?? ''))?></textarea>
				  <?php if(!empty($errors['rejection_reason'])):?>
					<small class="text-danger d-block"><?=$errors['rejection_reason']?></small>
				  <?php endif?>
				  <small class="text-danger js-rejection-reason-error"></small>
				</div>
		 
				<div class="d-flex justify-content-between" style="margin-top:10px;">
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
						<button type="button" class="btn btn-outline-primary" style="border:none;">
							<i class="fa-solid fa-chevron-left"></i> Back
						</button>
					</a>
					<button class="btn btn-outline-danger save" style="border:none;">
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
		              <th class="px-5">TOTAL</th>
		              <th><input type="text" class="form-control p-1" id="total-count" style="border:none; " placeholder="Total count" readonly></th>
		              <th><input type="text" class="form-control p-1" id="total-volume" style="border:none; " placeholder="Total volume" readonly></th>
		            </tr>
		          </tfoot>
		        </table>
		      </div>
		      <div class="modal-footer">
		        <button type="button" class="btn btn-outline-secondary restore">Restore</button>
		        <button type="button" class="btn btn-outline-danger reset">Reset</button>
		        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
		        <button type="button" class="btn btn-primary submit">Confirm</button>
		      </div>
		    </div>
		  </div>
		</div>

		<div class="modal fade" id="airtimeChangeModal" tabindex="-1" aria-labelledby="airtimeChangeLabel" aria-hidden="true">
		  <div class="modal-dialog modal-md">
		    <div class="modal-content border-0 shadow-lg">
		      <div class="modal-header border-0 pb-0">
		        <h5 class="modal-title fw-bold" id="airtimeChangeLabel">
		          <i class="fa fa-exchange-alt me-2"></i> Give Change
		        </h5>
		        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
		      </div>
		      <div class="modal-body">
		        <div class="alert alert-info py-2 px-3 mb-3">
		          <div class="fw-semibold">Change required: <span id="airtimeChangeRequiredText">GHS 0.00</span></div>
		          <small class="text-muted">The full received denominations will be added first, then the selected change denominations will be deducted from the cashdrawer.</small>
		        </div>

		        <div class="alert alert-light border py-2 px-3 mb-3" style="font-size:0.9rem;">
		          <strong>Available after receipt:</strong>
		          <span id="airtimeAvailableDenosText" class="ms-1 text-muted">No denominations available.</span>
		        </div>

		        <div id="airtimeChangeRows" class="border rounded p-2" style="max-height: 200px; overflow-y: auto; background-color: var(--bs-body-bg);"></div>

		        <div class="d-flex align-items-center gap-2 mt-2">
		          <small class="text-muted">Selected change: <strong id="airtimeChangeSelectedText" class="text-danger">GHS 0.00</strong></small>
		          <small class="text-muted ms-auto">Net cash: <strong id="airtimeChangeNetText">GHS 0.00</strong></small>
		        </div>
		        <div class="mt-1">
		          <small id="airtimeChangeBalanceText" class="text-warning">Add GHS 0.00 more to balance.</small>
		        </div>

		        <div class="d-flex justify-content-end mt-2">
		          <button type="button" class="btn btn-sm" id="airtimeAddChangeRowBtn" style="background-color:#dc3545; color:white; border:none; padding:0.35rem 0.75rem;">
		            <i class="fa fa-plus me-1"></i> Add
		          </button>
		        </div>

		        <div class="alert py-2 px-3 mt-3 mb-0" id="airtimeChangeAlert" role="alert">
		          <span id="airtimeChangeAlertText">Select the denominations to give out.</span>
		        </div>
		      </div>
		      <div class="modal-footer border-0 pt-0">
		        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
		        <button type="button" class="btn btn-sm btn-primary" id="airtimeApplyChangeBtn" disabled>
		          <i class="fa fa-check me-1"></i> Apply change
		        </button>
		      </div>
		    </div>
		  </div>
		</div>
	<?php else:?>
		<div class="alert alert-danger text-center">
			That record was not found!
		</div>
		
		<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
			<button class="btn btn-primary">Back</button>
		</a>
	<?php endif?>

<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>

	<!-- JavaScript: Calculate Charges, Validate Wallet, Show Fields -->
	<script>

			const networkOptions = {
				
				"Airtime": [
					{ value: "", text: "---Select---" },
					{ value: "AT", text: "AT" },
					{ value: "MTN", text: "MTN" },
					{ value: "TELECEL", text: "TELECEL" },
				],
				
				"Credit Transfer": [
					{ value: "", text: "---Select---" },
					{ value: "AT", text: "AT" },
					{ value: "MTN", text: "MTN" },
				],
				"EVD": [
					{ value: "", text: "---Select---" },
					{ value: "AT", text: "AT" },
					{ value: "MTN", text: "MTN" },
					{ value: "TELECEL", text: "TELECEL" },
				],
			};

		const transactionType = document.getElementById('transaction_type'); // Corrected ID selection
		const categorySelect = document.getElementById('category');
		const networkSelect = document.getElementById('network');
		const categoryDiv = document.querySelector('.category');

		function updateNetworkOptions() {
			const categoryValue = category.value;
			networkSelect.innerHTML = ""; // Clear current options

			// Populate options based on selected transaction type
			if (networkOptions[categoryValue]) {
				networkOptions[categoryValue].forEach(option => {
					const opt = document.createElement('option');
					opt.value = option.value;
					opt.textContent = option.text;
					networkSelect.appendChild(opt);
				});
			} 
			showFields();
		}

		function showFields() {
			const networkValue = networkSelect.value;
			const transactionTypeValue = transactionType.value;
			const categoryValue = categorySelect.value;
			const resellerNumber = document.querySelector('.reseller_number');
			const commission = document.querySelector('.commission');
			const commissionInput = document.querySelector('input[name="commission"]');
			const transactionStatus = document.querySelector('.transaction_status');
				resellerNumber.classList.add('d-none');
				commission.classList.add('d-none');
				if (commissionInput && categoryValue !== 'Credit Transfer') {
					commissionInput.value = '0.00';
				}
				//transactionStatus.classList.add('d-none');

			// Show fields based on selected category for Airtime
			if (categoryValue === 'Credit Transfer') {
				resellerNumber.classList.remove('d-none');
				commission.classList.remove('d-none');
			}else
			if (categoryValue === 'EVD') {
				transactionStatus.classList.remove('d-none');
			}else{
			}
		}

		categorySelect.addEventListener("change", updateNetworkOptions);

	  document.addEventListener("DOMContentLoaded", function () {
		  const countInputs = document.querySelectorAll(".count-input");
		  const totalCountField = document.getElementById("total-count");
		  const totalVolumeField = document.getElementById("total-volume");
		  const denosField = document.querySelector("#denos");
		  const submitBtn = document.querySelector('.submit');
		  const restoreBtn = document.querySelector('.restore');
		  const resetBtn = document.querySelector('.reset');
		  const staticBackdropModal = new bootstrap.Modal(document.getElementById('staticBackdrop'));
		  const amountInput = document.getElementById("amount_paid_input");
		  const walletOutSelect = document.getElementById("wallet_out_id");
		  const walletInSelect = document.getElementById("wallet_in_id");
		  const walletOutDiv = document.querySelector('.wallet_out_id');
		  const walletInDiv = document.querySelector(".wallet_in_id");
		  const networkSelect = document.querySelector('[name="network"]');
		  const wallets = <?= json_encode($wallets); ?> || [];
		  const userId = <?= json_encode($user_id); ?>;
		  const walletInError = document.querySelector('.js-wallet-in-error');
		  const walletOutError = document.querySelector('.js-wallet-out-error');
		  const balanceAfter = document.getElementById('balance_after');
		  const balanceAfterError = document.querySelector('.js-balance-after-error');

		  // Restore selected wallet values from localStorage
		  const savedPayingWallet = localStorage.getItem("payingWallet");
		  const savedReceivingWallet = localStorage.getItem("receivingWallet");

		  function getSeedDenos(){
			const fromField = (denosField && (denosField.value || '').trim() !== '') ? (denosField.value || '').trim() : '';
			if(fromField && fromField !== '[]'){
				try{
					const parsed = JSON.parse(fromField);
					if(Array.isArray(parsed)) return parsed;
				}catch(e){}
			}
			try{
				const saved = localStorage.getItem('denosData');
				const parsed = saved ? JSON.parse(saved) : null;
				return Array.isArray(parsed) ? parsed : [];
			}catch(e){
				return [];
			}
		  }

		  function restoreDenominations() {
			const currency = getSeedDenos();
			if (currency && currency.length) {
				const rowMeta = Array.from(countInputs).map((input, idx) => {
					const row = input.closest('tr');
					return {
						idx,
						denomination: row ? parseFloat(row.getAttribute('data-denomination')) : NaN,
					};
				});
				const usedRowIdx = new Set();

				currency.forEach((item) => {
					const denom = parseFloat(item.denomination);
					let targetIdx = (item && item.row_index !== undefined && item.row_index !== null) ? parseInt(item.row_index) : NaN;
					if (Number.isInteger(targetIdx) && countInputs[targetIdx]) {
						const meta = rowMeta[targetIdx];
						if (!meta || meta.denomination !== denom) {
							targetIdx = NaN;
						}
					} else {
						targetIdx = NaN;
					}
					if (!Number.isInteger(targetIdx)) {
						const match = rowMeta.find(m => !usedRowIdx.has(m.idx) && m.denomination === denom);
						if (match) targetIdx = match.idx;
					}
					if (!Number.isInteger(targetIdx)) return;
					if (usedRowIdx.has(targetIdx)) return;

					const input = countInputs[targetIdx];
					const row = input.closest('tr');
					const volumeField = row ? row.querySelector('.volume-input') : null;
					input.value = item.count;
					if (volumeField) {
						volumeField.value = (parseFloat(item.denomination) * (parseFloat(item.count) || 0)).toFixed(2);
					}
					usedRowIdx.add(targetIdx);
				});

				updateVolumesAndTotals();
			}
		  }

		  function resetDenominations() {
			countInputs.forEach((input) => {
				const row = input.closest('tr');
				const volumeField = row ? row.querySelector('.volume-input') : null;
				input.value = '';
				if (volumeField) volumeField.value = '';
			});
			updateVolumesAndTotals();
			try {
				localStorage.setItem('denosData', JSON.stringify([]));
			} catch (e) {}
			if (denosField) denosField.value = JSON.stringify([]);
		  }

		  function updateVolumesAndTotals() {
			let totalCount = 0;
			let totalVolume = 0;
			const currency = [];

			countInputs.forEach((input, rowIndex) => {
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
						row_index: rowIndex,
						denomination: denomination,
						count: count,
						volume: volume,
					};
					currency.push(denos);
				}
			});

			totalCountField.value = totalCount;
			totalVolumeField.value = totalVolume.toFixed(2);
			const serializedCurrency = JSON.stringify(currency);
			localStorage.setItem("denosData", serializedCurrency);
			if (denosField) denosField.value = serializedCurrency;
		  }

		  countInputs.forEach((input) => {
			input.addEventListener('input', updateVolumesAndTotals);
		  });
		  if (submitBtn) {
			submitBtn.addEventListener('click', function () {
				updateVolumesAndTotals();
				if (amountInput) amountInput.value = totalVolumeField.value;
				staticBackdropModal.hide();
			});
		  }
		  if (restoreBtn) {
			restoreBtn.addEventListener('click', function () {
				restoreDenominations();
			});
		  }
		  if (resetBtn) {
			resetBtn.addEventListener('click', function () {
				resetDenominations();
			});
		  }
		  const denosModalEl = document.getElementById('staticBackdrop');
		  if (denosModalEl) {
			denosModalEl.addEventListener('shown.bs.modal', function () {
				restoreDenominations();
			});
		  }

		  updateNetworkOptions();
		  restoreDenominations();

	  function validatePayingAndReceivingWallet() {
		  const selectedNetwork = networkSelect.value.toLowerCase();
		  const assignedSelect = document.getElementById('assigned');
		  const assigned = assignedSelect.value
		  const addedWalletsOut = new Set();
		  const addedWalletsIn = new Set();
		  let hasPayingWallet = false;
		  let hasReceivingWallet = false;

		  // Clear existing options
		  walletOutSelect.innerHTML = '';
		  walletInSelect.innerHTML = '';

		  // Populate Paying Wallets
		  wallets.forEach(wallet => {
		    // When assigned is selected, match wallets with the assigned user
		    if (assigned) {
		      if (wallet.assigned == assigned && wallet.wallet_type.toLowerCase() === "cash") {
		        if (!addedWalletsOut.has(wallet.id)) {
		          const option = document.createElement("option");
		          option.value = wallet.id;
		          option.textContent = `${wallet.name} (${wallet.network}) [${parseFloat(wallet.balance).toFixed(2)}]`;
		          walletOutSelect.appendChild(option);
		          addedWalletsOut.add(wallet.id);
		          hasPayingWallet = true;
		        }
		      }
		    } else {
		      // If no assigned value, show wallets for the current user
		      if (wallet.assigned == userId && wallet.wallet_type.toLowerCase() === "cash") {
		        if (!addedWalletsOut.has(wallet.id)) {
		          const option = document.createElement("option");
		          option.value = wallet.id;
		          option.textContent = `${wallet.name} (${wallet.network}) [${parseFloat(wallet.balance).toFixed(2)}]`;
		          walletOutSelect.appendChild(option);
		          addedWalletsOut.add(wallet.id);
		          hasPayingWallet = true;
		        }
		      }
		    }
		  });

		  // Populate Receiving Wallets
		  wallets.forEach(wallet => {
		    if (assigned) {
		      if (wallet.assigned == assigned && wallet.network.toLowerCase().includes(selectedNetwork)) {
		        if (!addedWalletsIn.has(wallet.id)) {
		          const option = document.createElement("option");
		          option.value = wallet.id;
		          option.textContent = `${wallet.name} (${wallet.network}) [${parseFloat(wallet.balance).toFixed(2)}]`;
		          walletInSelect.appendChild(option);
		          addedWalletsIn.add(wallet.id);
		          hasReceivingWallet = true;
		        }
		      }
		    } else {
		      if (wallet.assigned == userId && wallet.network.toLowerCase().includes(selectedNetwork)) {
		        if (!addedWalletsIn.has(wallet.id)) {
		          const option = document.createElement("option");
		          option.value = wallet.id;
		          option.textContent = `${wallet.name} (${wallet.network}) [${parseFloat(wallet.balance).toFixed(2)}]`;
		          walletInSelect.appendChild(option);
		          addedWalletsIn.add(wallet.id);
		          hasReceivingWallet = true;
		        }
		      }
		    }
		  });

		  // Display error messages if no wallets are available
		  if (!hasPayingWallet) {
		    walletOutDiv.classList.add('d-none');
		    walletInError.textContent = 'No paying wallet available.';
		  } else {
		    walletOutDiv.classList.remove('d-none');
		    walletInError.textContent = '';
		  }

		  if (!hasReceivingWallet) {
		    walletInDiv.classList.add('d-none');
		    walletInError.textContent = 'No receiving wallet available.';
		  } else {
		    walletInDiv.classList.remove('d-none');
		    walletInError.textContent = '';
		  }
		}

	  // Event Listeners
	  networkSelect.addEventListener("change", validatePayingAndReceivingWallet);
		document.querySelector('.assigned').addEventListener("change", validatePayingAndReceivingWallet);


	  // Save selected wallet values to localStorage
	  walletOutSelect.addEventListener("change", () => {
	    localStorage.setItem("payingWallet", walletOutSelect.value);
	  });

	  const transactionType = document.getElementById('transaction_type');

		  });
	</script>

<script>
  document.addEventListener("DOMContentLoaded", function () {
    const lockSentTransaction = <?= $isSentRecord ? 'true' : 'false' ?>;
    const airtimeForm = document.querySelector('form');
    const categorySelect = document.getElementById('category');
    const networkSelect = document.getElementById('network');
    const loadAmountInput = document.getElementById('amount_input');
    const amountPaidInput = document.getElementById('amount_paid_input');
    const saveBtn = document.querySelector('.save');
    const transactionStatusSelect = document.querySelector('[name="transaction_status"]');
    const amountMismatchError = document.querySelector('.js-amount-mismatch-error');
    const rejectionReasonWrap = document.getElementById('airtimeRejectionReasonWrap');
    const rejectionReasonField = document.getElementById('rejection_reason');
    const rejectionReasonError = document.querySelector('.js-rejection-reason-error');
    const denosField = document.getElementById('denos');
    const changeDenominationsField = document.getElementById('change_denominations');
    const changeRequiredField = document.getElementById('change_required');
    const cashReceivedAmountField = document.getElementById('cash_received_amount');
    const cashdrawerDenosField = document.getElementById('cashdrawerDenos');
    const submitBtn = document.querySelector('.submit');
    const countInputs = document.querySelectorAll('.count-input');
    const airtimeChangeSummary = document.getElementById('airtimeChangeSummary');
    const airtimeChangeSummaryText = document.getElementById('airtimeChangeSummaryText');
    const airtimeChangeSummaryNote = document.getElementById('airtimeChangeSummaryNote');
    const airtimeOpenChangeBtn = document.getElementById('airtimeOpenChangeBtn');
    const airtimeClearChangeBtn = document.getElementById('airtimeClearChangeBtn');
    const airtimeChangeRows = document.getElementById('airtimeChangeRows');
    const airtimeAddChangeRowBtn = document.getElementById('airtimeAddChangeRowBtn');
    const airtimeApplyChangeBtn = document.getElementById('airtimeApplyChangeBtn');
    const airtimeChangeRequiredText = document.getElementById('airtimeChangeRequiredText');
    const airtimeAvailableDenosText = document.getElementById('airtimeAvailableDenosText');
    const airtimeChangeSelectedText = document.getElementById('airtimeChangeSelectedText');
    const airtimeChangeNetText = document.getElementById('airtimeChangeNetText');
    const airtimeChangeBalanceText = document.getElementById('airtimeChangeBalanceText');
    const airtimeChangeAlert = document.getElementById('airtimeChangeAlert');
    const airtimeChangeAlertText = document.getElementById('airtimeChangeAlertText');
    const changeModalEl = document.getElementById('airtimeChangeModal');
    const airtimeChangeModal = changeModalEl ? new bootstrap.Modal(changeModalEl) : null;
    const cashDenominationOptions = [200, 100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1];

    if (!airtimeForm || !categorySelect || !networkSelect || !loadAmountInput || !amountPaidInput || !saveBtn || !denosField) {
      return;
    }

    function roundMoney(value) {
      return Math.round((Number(value) || 0) * 100) / 100;
    }

    function formatMoney(value) {
      return roundMoney(value).toFixed(2);
    }

    function valuesMatch(a, b) {
      return Math.abs(roundMoney(a) - roundMoney(b)) <= 0.01;
    }

    function normalizeDenominationKey(value) {
      const num = Number(value);
      if (!Number.isFinite(num) || num <= 0) {
        return '';
      }
      return num.toFixed(2);
    }

    function parseJsonArray(inputEl) {
      if (!inputEl || !inputEl.value) {
        return [];
      }
      try {
        const parsed = JSON.parse(inputEl.value);
        return Array.isArray(parsed) ? parsed : [];
      } catch (e) {
        return [];
      }
    }

    function sanitizeChangeRows(rows) {
      if (!Array.isArray(rows)) {
        return [];
      }

      return rows.reduce((carry, item) => {
        const denomination = roundMoney(item && item.denomination);
        const count = parseInt(item && item.count, 10) || 0;
        if (denomination > 0 && count > 0) {
          carry.push({
            denomination,
            count,
            volume: roundMoney(denomination * count),
          });
        }
        return carry;
      }, []);
    }

    function aggregateDenominationCounts(rows) {
      const map = {};
      (rows || []).forEach(item => {
        const key = normalizeDenominationKey(item && item.denomination);
        const count = parseInt(item && item.count, 10) || 0;
        if (!key || count <= 0) {
          return;
        }
        map[key] = (map[key] || 0) + count;
      });
      return map;
    }

    function isChangeEligibleCategory() {
      return ['Airtime', 'Credit Transfer'].includes(categorySelect.value);
    }

    function setSaveEnabled(enabled) {
      if (!saveBtn) {
        return;
      }

      if (enabled) {
        saveBtn.classList.remove('disabled');
        saveBtn.removeAttribute('disabled');
      } else {
        saveBtn.classList.add('disabled');
        saveBtn.setAttribute('disabled', 'disabled');
      }
    }

    function statusRequiresRejectionReason() {
      const statusValue = transactionStatusSelect ? String(transactionStatusSelect.value || '').trim().toLowerCase() : '';
      return statusValue === 'rejected';
    }

    function hasRejectionReason() {
      return !statusRequiresRejectionReason() || (rejectionReasonField && String(rejectionReasonField.value || '').trim() !== '');
    }

    function syncRejectionReasonState() {
      const requiresReason = statusRequiresRejectionReason();
      if (rejectionReasonWrap) {
        rejectionReasonWrap.classList.toggle('d-none', !requiresReason);
      }
      if (rejectionReasonField) {
        if (requiresReason) {
          rejectionReasonField.setAttribute('required', 'required');
        } else {
          rejectionReasonField.removeAttribute('required');
        }
      }
      if (!requiresReason && rejectionReasonError) {
        rejectionReasonError.textContent = '';
      }
    }

    function getPayableAmount() {
      if (!isChangeEligibleCategory()) {
        return 0;
      }
      return roundMoney(loadAmountInput.value);
    }

    function getCashReceivedAmount() {
      const hiddenValue = roundMoney(cashReceivedAmountField && cashReceivedAmountField.value);
      if (hiddenValue > 0) {
        return hiddenValue;
      }
      return roundMoney(amountPaidInput.value);
    }

    function getRequiredChangeAmount() {
      const payable = getPayableAmount();
      const received = getCashReceivedAmount();
      if (payable > 0 && received > payable + 0.01) {
        return roundMoney(received - payable);
      }
      return 0;
    }

    function getSelectedChangeRows() {
      return sanitizeChangeRows(parseJsonArray(changeDenominationsField));
    }

    function getSelectedChangeTotal(rows) {
      return roundMoney((rows || []).reduce((sum, item) => {
        return sum + (roundMoney(item.denomination) * (parseInt(item.count, 10) || 0));
      }, 0));
    }

    function getDrawerAvailabilityMap() {
      return aggregateDenominationCounts(parseJsonArray(cashdrawerDenosField));
    }

    function getReceivedDenominationMap() {
      return aggregateDenominationCounts(parseJsonArray(denosField));
    }

    function getCombinedAvailabilityMap() {
      const combined = getDrawerAvailabilityMap();
      const received = getReceivedDenominationMap();
      Object.keys(received).forEach(key => {
        combined[key] = (combined[key] || 0) + (received[key] || 0);
      });
      return combined;
    }

    function resetChangeSelection() {
      if (changeDenominationsField) {
        changeDenominationsField.value = '[]';
      }
      if (changeRequiredField) {
        changeRequiredField.value = '';
      }
      syncChangeSummary();
    }

    function syncChangeSummary() {
      if (!airtimeChangeSummary) {
        return;
      }

      const payable = getPayableAmount();
      const received = getCashReceivedAmount();
      const required = getRequiredChangeAmount();
      const selectedRows = getSelectedChangeRows();
      const selectedTotal = getSelectedChangeTotal(selectedRows);
      const netCash = roundMoney(received - selectedTotal);
      const hasSelection = selectedRows.length > 0;
      const isBalanced = required > 0 && hasSelection && valuesMatch(selectedTotal, required) && valuesMatch(netCash, payable);

      if (!isChangeEligibleCategory() || required <= 0.01) {
        airtimeChangeSummary.classList.add('d-none');
        airtimeChangeSummary.classList.remove('alert-warning', 'alert-success', 'alert-danger');
        if (airtimeClearChangeBtn) {
          airtimeClearChangeBtn.classList.add('d-none');
        }
        return;
      }

      airtimeChangeSummary.classList.remove('d-none', 'alert-warning', 'alert-success', 'alert-danger');
      airtimeChangeSummary.classList.add(isBalanced ? 'alert-success' : (hasSelection ? 'alert-danger' : 'alert-warning'));
      airtimeChangeSummaryText.textContent = 'Change required: GHS ' + formatMoney(required);

      if (isBalanced) {
        airtimeChangeSummaryNote.textContent = 'Received GHS ' + formatMoney(received) + '. Change selected GHS ' + formatMoney(selectedTotal) + '. Net cash GHS ' + formatMoney(netCash) + ' matches the payable amount.';
      } else if (hasSelection) {
        airtimeChangeSummaryNote.textContent = 'Selected change totals GHS ' + formatMoney(selectedTotal) + '. Adjust it to exactly GHS ' + formatMoney(required) + '.';
      } else {
        airtimeChangeSummaryNote.textContent = 'Select the denominations to give out so the net cash matches the payable amount.';
      }

      if (airtimeOpenChangeBtn) {
        airtimeOpenChangeBtn.textContent = hasSelection ? 'Edit change' : 'Select change';
      }
      if (airtimeClearChangeBtn) {
        airtimeClearChangeBtn.classList.toggle('d-none', !hasSelection);
      }
    }

    function syncChangeRequirement() {
      const received = roundMoney(amountPaidInput.value);
      if (cashReceivedAmountField) {
        cashReceivedAmountField.value = received > 0 ? formatMoney(received) : '';
      }

      const payable = getPayableAmount();
      if (!isChangeEligibleCategory() || payable <= 0.01) {
        resetChangeSelection();
        return { received, payable, required: 0 };
      }

      const required = getRequiredChangeAmount();
      const previousRequired = roundMoney(changeRequiredField && changeRequiredField.value);

      if (required <= 0.01) {
        resetChangeSelection();
        return { received, payable, required: 0 };
      }

      if (changeRequiredField) {
        changeRequiredField.value = formatMoney(required);
      }

      if (!valuesMatch(previousRequired, required) && getSelectedChangeRows().length > 0 && changeDenominationsField) {
        changeDenominationsField.value = '[]';
      }

      syncChangeSummary();

      return { received, payable, required };
    }

    function buildChangeSelectOptions(selectedValue) {
      const currentKey = normalizeDenominationKey(selectedValue);
      return ['<option value="">---Amount (GHS)---</option>']
        .concat(cashDenominationOptions.map(value => {
          const key = normalizeDenominationKey(value);
          const selected = key === currentKey ? ' selected' : '';
          return '<option value="' + formatMoney(value) + '"' + selected + '>' + formatMoney(value) + '</option>';
        }))
        .join('');
    }

    function getRawChangeRowsFromModal() {
      if (!airtimeChangeRows) {
        return [];
      }
      return Array.from(airtimeChangeRows.querySelectorAll('.airtime-change-row')).map(row => {
        const valueEl = row.querySelector('.airtime-change-value');
        const countEl = row.querySelector('.airtime-change-count');
        return {
          denomination: valueEl ? valueEl.value : '',
          count: countEl ? countEl.value : '',
        };
      });
    }

    function getChangeRowsFromModal() {
      return sanitizeChangeRows(getRawChangeRowsFromModal());
    }

    function syncChangeSelectOptions() {
      if (!airtimeChangeRows) {
        return;
      }

      const selects = Array.from(airtimeChangeRows.querySelectorAll('.airtime-change-value'));
      const selected = new Set();

      selects.forEach(select => {
        const key = normalizeDenominationKey(select.value);
        if (key) {
          selected.add(key);
        }
      });

      selects.forEach(select => {
        const currentKey = normalizeDenominationKey(select.value);
        Array.from(select.options).forEach((option, index) => {
          if (index === 0) {
            option.disabled = false;
            option.hidden = false;
            return;
          }
          const optionKey = normalizeDenominationKey(option.value || option.text);
          const taken = optionKey && selected.has(optionKey) && optionKey !== currentKey;
          option.disabled = !!taken;
          option.hidden = !!taken;
        });
      });
    }

    function updateChangeModalState() {
      if (!airtimeChangeRows || !airtimeApplyChangeBtn || !airtimeChangeAlert || !airtimeChangeAlertText) {
        return;
      }

      syncChangeSelectOptions();

      const required = getRequiredChangeAmount();
      const received = getCashReceivedAmount();
      const payable = getPayableAmount();
      const rows = getChangeRowsFromModal();
      const selectedTotal = getSelectedChangeTotal(rows);
      const netCash = roundMoney(received - selectedTotal);
      const balanceGap = roundMoney(required - selectedTotal);
      const availability = getCombinedAvailabilityMap();
      const requested = {};

      rows.forEach(item => {
        const key = normalizeDenominationKey(item.denomination);
        if (!key) {
          return;
        }
        requested[key] = (requested[key] || 0) + (parseInt(item.count, 10) || 0);
      });

      const insufficient = [];
      Object.keys(requested).forEach(key => {
        const have = parseInt(availability[key], 10) || 0;
        const need = parseInt(requested[key], 10) || 0;
        if (need > have) {
          insufficient.push('GHS ' + key + ' (need ' + need + ', available ' + have + ')');
        }
      });

      if (airtimeChangeRequiredText) {
        airtimeChangeRequiredText.textContent = 'GHS ' + formatMoney(required);
      }
      if (airtimeChangeSelectedText) {
        airtimeChangeSelectedText.textContent = 'GHS ' + formatMoney(selectedTotal);
      }
      if (airtimeChangeNetText) {
        airtimeChangeNetText.textContent = 'GHS ' + formatMoney(netCash);
      }
      if (airtimeChangeBalanceText) {
        airtimeChangeBalanceText.classList.remove('text-muted', 'text-warning', 'text-danger', 'text-success');

        if (!isChangeEligibleCategory() || required <= 0.01) {
          airtimeChangeBalanceText.textContent = 'No extra change is needed for this transaction.';
          airtimeChangeBalanceText.classList.add('text-muted');
        } else if (balanceGap > 0.01) {
          airtimeChangeBalanceText.textContent = 'Add GHS ' + formatMoney(balanceGap) + ' more to balance.';
          airtimeChangeBalanceText.classList.add('text-warning');
        } else if (balanceGap < -0.01) {
          airtimeChangeBalanceText.textContent = 'Reduce change by GHS ' + formatMoney(Math.abs(balanceGap)) + ' to balance.';
          airtimeChangeBalanceText.classList.add('text-danger');
        } else {
          airtimeChangeBalanceText.textContent = 'Balanced. Change selection matches the required amount.';
          airtimeChangeBalanceText.classList.add('text-success');
        }
      }
      if (airtimeAvailableDenosText) {
        const labels = Object.keys(availability)
          .filter(key => (parseInt(availability[key], 10) || 0) > 0)
          .sort((a, b) => parseFloat(b) - parseFloat(a))
          .map(key => 'GHS ' + key + ' (' + availability[key] + ' units)');
        airtimeAvailableDenosText.textContent = labels.length ? labels.join(', ') : 'No denominations available.';
      }

      if (!isChangeEligibleCategory()) {
        airtimeChangeAlert.className = 'alert alert-info py-2 px-3 mt-3 mb-0';
        airtimeChangeAlertText.textContent = 'Change is only used when this category has a separate payable amount.';
        airtimeApplyChangeBtn.disabled = true;
        return;
      }

      if (required <= 0.01) {
        airtimeChangeAlert.className = 'alert alert-info py-2 px-3 mt-3 mb-0';
        airtimeChangeAlertText.textContent = 'No change is required for this transaction.';
        airtimeApplyChangeBtn.disabled = true;
        return;
      }

      if (received + 0.01 < payable) {
        airtimeChangeAlert.className = 'alert alert-warning py-2 px-3 mt-3 mb-0';
        airtimeChangeAlertText.textContent = 'Amount received is below the payable amount, so change cannot be configured yet.';
        airtimeApplyChangeBtn.disabled = true;
        return;
      }

      if (!rows.length) {
        airtimeChangeAlert.className = 'alert alert-info py-2 px-3 mt-3 mb-0';
        airtimeChangeAlertText.textContent = 'Select the denominations to give out. Add GHS ' + formatMoney(required) + ' more to balance.';
        airtimeApplyChangeBtn.disabled = true;
        return;
      }

      if (insufficient.length) {
        airtimeChangeAlert.className = 'alert alert-danger py-2 px-3 mt-3 mb-0';
        airtimeChangeAlertText.textContent = 'Insufficient denominations after receipt: ' + insufficient.join(' | ');
        airtimeApplyChangeBtn.disabled = true;
        return;
      }

      if (!valuesMatch(selectedTotal, required) || !valuesMatch(netCash, payable)) {
        airtimeChangeAlert.className = 'alert alert-warning py-2 px-3 mt-3 mb-0';
        if (balanceGap > 0.01) {
          airtimeChangeAlertText.textContent = 'Add GHS ' + formatMoney(balanceGap) + ' more change so the net cash equals the payable amount.';
        } else if (balanceGap < -0.01) {
          airtimeChangeAlertText.textContent = 'Reduce change by GHS ' + formatMoney(Math.abs(balanceGap)) + ' so the net cash equals the payable amount.';
        } else {
          airtimeChangeAlertText.textContent = 'Change must total exactly GHS ' + formatMoney(required) + ' so the net cash equals the payable amount.';
        }
        airtimeApplyChangeBtn.disabled = true;
        return;
      }

      airtimeChangeAlert.className = 'alert alert-success py-2 px-3 mt-3 mb-0';
      airtimeChangeAlertText.textContent = 'Change selection is balanced and ready to apply.';
      airtimeApplyChangeBtn.disabled = false;
    }

    function bindChangeRowEvents() {
      if (!airtimeChangeRows) {
        return;
      }

      airtimeChangeRows.querySelectorAll('.airtime-change-value, .airtime-change-count').forEach(input => {
        input.addEventListener('input', updateChangeModalState);
        input.addEventListener('change', updateChangeModalState);
      });

      airtimeChangeRows.querySelectorAll('.airtime-delete-change').forEach(button => {
        button.addEventListener('click', function () {
          const rows = airtimeChangeRows.querySelectorAll('.airtime-change-row');
          if (rows.length <= 1) {
            const row = this.closest('.airtime-change-row');
            if (row) {
              const select = row.querySelector('.airtime-change-value');
              const count = row.querySelector('.airtime-change-count');
              if (select) select.value = '';
              if (count) count.value = '';
            }
          } else {
            const row = this.closest('.airtime-change-row');
            if (row) row.remove();
          }
          updateChangeModalState();
        });
      });
    }

    function renderChangeRows(seedRows) {
      if (!airtimeChangeRows) {
        return;
      }

      const rows = Array.isArray(seedRows) ? seedRows : [];
      const sourceRows = rows.length ? rows : [{ denomination: '', count: '' }];

      airtimeChangeRows.innerHTML = sourceRows.map(row => {
        const denomination = row && row.denomination !== undefined && row.denomination !== null && row.denomination !== ''
          ? formatMoney(row.denomination)
          : '';
        const count = row && row.count !== undefined && row.count !== null && row.count !== ''
          ? String(row.count)
          : '';
        return `
          <div class="airtime-change-row d-flex align-items-center gap-2 mb-2">
            <select class="form-select airtime-change-value">${buildChangeSelectOptions(denomination)}</select>
            <input type="number" min="1" step="1" class="form-control airtime-change-count" placeholder="Count" value="${count}">
            <button type="button" class="btn btn-sm btn-outline-danger airtime-delete-change"><i class="fa fa-trash"></i></button>
          </div>
        `;
      }).join('');

      bindChangeRowEvents();
      syncChangeSelectOptions();
      updateChangeModalState();
    }

    function showChangeModal() {
      if (!airtimeChangeModal) {
        return;
      }

      const state = syncChangeRequirement();
      if (!isChangeEligibleCategory() || state.required <= 0.01 || state.received + 0.01 < state.payable) {
        return;
      }

      renderChangeRows(getSelectedChangeRows());
      updateChangeModalState();
      airtimeChangeModal.show();
    }

    function validateAirtimeChangeState() {
      syncRejectionReasonState();
      if (rejectionReasonError) {
        rejectionReasonError.textContent = '';
      }
      if (lockSentTransaction) {
        const statusValue = transactionStatusSelect ? String(transactionStatusSelect.value || '').trim().toLowerCase() : '';
        if (statusValue === 'rejected') {
          const reasonProvided = hasRejectionReason();
          amountMismatchError.textContent = '';
          if (rejectionReasonError) {
            rejectionReasonError.textContent = reasonProvided ? '' : 'Rejection reason is required.';
          }
          setSaveEnabled(reasonProvided);
          return reasonProvided;
        }

        amountMismatchError.textContent = 'Select Rejected to reverse this sent transaction.';
        setSaveEnabled(false);
        return false;
      }

      const categoryValue = categorySelect.value;
      const payable = getPayableAmount();
      const received = roundMoney(amountPaidInput.value);
      const required = getRequiredChangeAmount();
      const selectedRows = getSelectedChangeRows();
      const selectedTotal = getSelectedChangeTotal(selectedRows);
      const netCash = roundMoney(received - selectedTotal);

      if (categoryValue === 'Airtime' || categoryValue === 'Credit Transfer') {
        if (payable <= 0) {
          amountMismatchError.textContent = 'Load/Amount is required';
          setSaveEnabled(false);
          return false;
        }

        if (received <= 0) {
          amountMismatchError.textContent = 'Please use the calculator to enter amount paid';
          setSaveEnabled(false);
          return false;
        }

        if (received + 0.01 < payable) {
          amountMismatchError.textContent = 'Amount Paid cannot be less than Load/Amount';
          setSaveEnabled(false);
          return false;
        }

        if (required > 0.01 && (!selectedRows.length || !valuesMatch(selectedTotal, required) || !valuesMatch(netCash, payable))) {
          amountMismatchError.textContent = 'Change of GHS ' + formatMoney(required) + ' is required. Select the denominations to give out.';
          setSaveEnabled(false);
          return false;
        }

        if (categoryValue === 'Credit Transfer' && !networkSelect.value) {
          setSaveEnabled(false);
          return false;
        }
      } else {
        if (payable <= 0) {
          amountMismatchError.textContent = 'Load/Amount is required';
          setSaveEnabled(false);
          return false;
        }

        if (received <= 0) {
          amountMismatchError.textContent = 'Please use the calculator to enter amount paid';
          setSaveEnabled(false);
          return false;
        }

        if (!valuesMatch(received, payable)) {
          amountMismatchError.textContent = 'Amount Paid must match Load/Amount';
          setSaveEnabled(false);
          return false;
        }
      }

      if (required <= 0.01 || !isChangeEligibleCategory()) {
        if (amountMismatchError.textContent.indexOf('Change of GHS') === 0 || amountMismatchError.textContent === 'Amount Paid cannot be less than Load/Amount') {
          amountMismatchError.textContent = '';
        }
      } else if (valuesMatch(netCash, payable)) {
        amountMismatchError.textContent = '';
      }

      if (!hasRejectionReason()) {
        if (rejectionReasonError) {
          rejectionReasonError.textContent = 'Rejection reason is required.';
        }
        setSaveEnabled(false);
        return false;
      }

      setSaveEnabled(true);
      return true;
    }

    function syncFromCalculatorTotals() {
      if (cashReceivedAmountField) {
        cashReceivedAmountField.value = roundMoney(amountPaidInput.value) > 0 ? formatMoney(amountPaidInput.value) : '';
      }
      syncChangeRequirement();
      validateAirtimeChangeState();
    }

    syncFromCalculatorTotals();

    categorySelect.addEventListener('change', syncFromCalculatorTotals);
    networkSelect.addEventListener('change', syncFromCalculatorTotals);
    loadAmountInput.addEventListener('input', syncFromCalculatorTotals);
    amountPaidInput.addEventListener('input', syncFromCalculatorTotals);
    if (transactionStatusSelect) {
      transactionStatusSelect.addEventListener('change', validateAirtimeChangeState);
    }
    if (rejectionReasonField) {
      rejectionReasonField.addEventListener('input', validateAirtimeChangeState);
    }

    countInputs.forEach(input => {
      input.addEventListener('input', function () {
        setTimeout(syncFromCalculatorTotals, 0);
      });
    });

    if (submitBtn) {
      submitBtn.addEventListener('click', function () {
        setTimeout(function () {
          syncFromCalculatorTotals();
          const state = syncChangeRequirement();
          if (isChangeEligibleCategory() && state.required > 0.01) {
            showChangeModal();
          }
        }, 0);
      });
    }

    if (airtimeOpenChangeBtn) {
      airtimeOpenChangeBtn.addEventListener('click', showChangeModal);
    }

    if (airtimeClearChangeBtn) {
      airtimeClearChangeBtn.addEventListener('click', function () {
        resetChangeSelection();
        validateAirtimeChangeState();
      });
    }

    if (airtimeAddChangeRowBtn) {
      airtimeAddChangeRowBtn.addEventListener('click', function () {
        const rows = getRawChangeRowsFromModal();
        rows.push({ denomination: '', count: '' });
        renderChangeRows(rows);
      });
    }

    if (airtimeApplyChangeBtn) {
      airtimeApplyChangeBtn.addEventListener('click', function () {
        const rows = getChangeRowsFromModal();
        const required = getRequiredChangeAmount();
        const selectedTotal = getSelectedChangeTotal(rows);
        const netCash = roundMoney(getCashReceivedAmount() - selectedTotal);

        if (!rows.length || !valuesMatch(selectedTotal, required) || !valuesMatch(netCash, getPayableAmount())) {
          updateChangeModalState();
          return;
        }

        if (changeDenominationsField) {
          changeDenominationsField.value = JSON.stringify(rows);
        }
        if (changeRequiredField) {
          changeRequiredField.value = formatMoney(required);
        }
        syncChangeSummary();
        airtimeChangeModal.hide();
        validateAirtimeChangeState();
      });
    }

    if (airtimeForm) {
      airtimeForm.addEventListener('submit', function (e) {
        syncFromCalculatorTotals();
        if (!validateAirtimeChangeState()) {
          e.preventDefault();
          if (getRequiredChangeAmount() > 0.01) {
            showChangeModal();
          }
        }
      });
    }

    renderChangeRows(getSelectedChangeRows());
    syncFromCalculatorTotals();
  });
</script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const lockSentTransaction = <?= $isSentRecord ? 'true' : 'false' ?>;
    if (!lockSentTransaction) {
      return;
    }

    const form = document.querySelector('form');
    if (!form) {
      return;
    }

    form.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (field) {
      const name = String(field.getAttribute('name') || '').toLowerCase();
      if (!name || name === 'transaction_status' || name === 'rejection_reason' || name.startsWith('csrf')) {
        return;
      }
      field.setAttribute('disabled', 'disabled');
    });
  });
</script>

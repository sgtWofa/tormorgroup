<?php if (!user_can('view_agencybanking')): ?>
	<div class="alert alert-danger text-center">Access denied. You do not have permission to view Agency Banking transactions.</div>
<?php else: ?>
	<?php
		$session = get_session();
		$currentUserId = (int)($session->id ?? 0);
		$canAddTransactions = user_can('add_agencybanking');
	?>
	<style>
		.agencybanking-list-table tbody tr { white-space: nowrap; }
	</style>

	<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
		<div>
			<h3 class="mb-1">Agency Banking</h3>
			<p class="text-muted mb-0">View and manage Agency Banking transactions.</p>
		</div>
		<div class="d-flex flex-wrap gap-2">
			<?php if (user_can('is_admin')): ?>
				<a class="btn btn-outline-secondary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/limits">
					<i class="fa fa-sliders me-1"></i>Transaction limits
				</a>
			<?php endif; ?>
			<?php if ($canAddTransactions): ?>
				<div class="dropdown">
					<button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
						<i class="fa fa-plus me-1"></i>New transaction
					</button>
					<ul class="dropdown-menu dropdown-menu-end">
						<li><a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/withdrawal">Withdrawal</a></li>
						<li><a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/deposit">Deposit</a></li>
						<li><a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/direct_transfer">Direct transfer</a></li>
						<li><a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/funds_transfer">Funds transfer</a></li>
						<li><a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/interbank">Interbank transfer</a></li>
						<li><a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/international_transfer">International transfer</a></li>
						<li><a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/account_opening">Account opening</a></li>
						<li><a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/balance_enquiry">Balance enquiry</a></li>
						<li><a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/mini_statement">Mini statement</a></li>
					</ul>
				</div>
			<?php endif; ?>
			<a class="btn btn-outline-primary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/exportList_pdf" target="_blank" rel="noopener">
				<i class="fa fa-file-pdf me-1"></i>Export
			</a>
		</div>
	</div>

	<form class="row g-2 mb-3" method="get" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
		<div class="col-12 col-md-8 col-lg-6">
			<label class="visually-hidden" for="agencybanking-search">Search transactions</label>
			<input
				id="agencybanking-search"
				class="form-control"
				type="search"
				name="find"
				value="<?=esc($_GET['find'] ?? '')?>"
				placeholder="Search by transaction, network, phone, status, or agent">
		</div>
		<div class="col-auto">
			<button class="btn btn-outline-primary" type="submit"><i class="fa fa-search me-1"></i>Search</button>
		</div>
		<?php if (!empty($_GET['find'])): ?>
			<div class="col-auto">
				<a class="btn btn-outline-secondary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">Clear</a>
			</div>
		<?php endif; ?>
	</form>

	<div class="table-responsive">
		<table class="table table-striped table-hover align-middle agencybanking-list-table">
			<thead>
				<tr>
					<th scope="col">#</th>
					<th scope="col">Transaction</th>
					<th scope="col">Phone</th>
					<th scope="col" class="text-end">Amount</th>
					<th scope="col" class="text-end">Charge</th>
					<th scope="col">Status</th>
					<th scope="col">Date created</th>
					<th scope="col">Created by</th>
					<th scope="col" class="text-end">Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php if (!empty($rows)): ?>
					<?php foreach ($rows as $index => $row): ?>
						<?php
							$status = strtolower(trim((string)($row->transaction_status ?? '')));
							$statusClass = match ($status) {
								'approved', 'sent' => 'bg-success',
								'pending' => 'bg-warning text-dark',
								'draft' => 'bg-secondary',
								'declined', 'rejected', 'reversed' => 'bg-danger',
								default => 'bg-secondary',
							};
							$isEditableStatus = in_array($status, ['pending', 'draft'], true);
							$rowId = (int)($row->id ?? 0);
							$rowOwnerId = (int)($row->created_by ?? 0);
						?>
						<tr>
							<td><?=($pager->offset ?? 0) + $index + 1?></td>
							<td>
								<?php if (user_can('view_agencybanking_details')): ?>
									<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$rowId?>">
										<?=esc(trim((string)($row->transaction_type ?? 'Transaction') . ' (' . (string)($row->network ?? '') . ')'))?>
									</a>
								<?php else: ?>
									<?=esc(trim((string)($row->transaction_type ?? 'Transaction') . ' (' . (string)($row->network ?? '') . ')'))?>
								<?php endif; ?>
								<div class="small text-muted">#<?=$rowId?></div>
							</td>
							<td><?=esc($row->phone ?? '')?></td>
							<td class="text-end"><?=number_format((float)($row->amount ?? 0), 2)?></td>
							<td class="text-end"><?=number_format((float)($row->charge ?? 0), 2)?></td>
							<td><span class="badge <?=$statusClass?>"><?=esc($row->transaction_status ?? 'Unknown')?></span></td>
							<td><?=esc(get_date_time($row->date_created ?? ''))?></td>
							<td>
								<?php if (!empty($row->user_image)): ?>
									<img src="<?=esc(get_image($row->user_image))?>" alt="" class="rounded-circle me-1" style="width:28px;height:28px;object-fit:cover">
								<?php endif; ?>
								<?=esc(trim((string)($row->user_first_name ?? '') . ' ' . (string)($row->user_last_name ?? '')))?>
							</td>
							<td class="text-end text-nowrap">
								<?php if (user_can('view_agencybanking_details')): ?>
									<a class="btn btn-sm btn-outline-primary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$rowId?>" title="View transaction" aria-label="View transaction">
										<i class="fa fa-eye"></i>
									</a>
								<?php endif; ?>
								<?php if ($isEditableStatus && (user_can('edit_agencybanking') || ($currentUserId > 0 && $rowOwnerId === $currentUserId))): ?>
									<a class="btn btn-sm btn-outline-warning" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$rowId?>" title="Edit transaction" aria-label="Edit transaction">
										<i class="fa fa-pen"></i>
									</a>
								<?php endif; ?>
								<?php if ($isEditableStatus && (user_can('delete_agencybanking') || ($currentUserId > 0 && $rowOwnerId === $currentUserId))): ?>
									<a class="btn btn-sm btn-outline-danger" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/delete/<?=$rowId?>" title="Delete transaction" aria-label="Delete transaction">
										<i class="fa fa-trash"></i>
									</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else: ?>
					<tr><td colspan="9" class="text-center text-muted py-4">No Agency Banking transactions found.</td></tr>
				<?php endif; ?>
			</tbody>
			<?php if (isset($total_amount) || isset($total_charge)): ?>
				<tfoot>
					<tr>
						<th colspan="3" class="text-end">Totals</th>
						<th class="text-end"><?=number_format((float)($total_amount ?? 0), 2)?></th>
						<th class="text-end"><?=number_format((float)($total_charge ?? 0), 2)?></th>
						<th colspan="4"></th>
					</tr>
				</tfoot>
			<?php endif; ?>
		</table>
	</div>

	<?php if (isset($pager)): ?>
		<?=$pager->display()?>
	<?php endif; ?>
<?php endif; ?>
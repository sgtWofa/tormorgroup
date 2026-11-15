<?php if(user_can('view_reversal')):?>
	<?php if(!empty($row)): ?>
		<?php
			$renderViewImageCard = static function(string $label, string $imagePath, string $emptyLabel): void {
				$imageUrl = trim((string)($imagePath !== '' ? get_image($imagePath) : ''));
				?>
				<div class="rev-upload-preview">
					<div class="rev-upload-thumb">
						<?php if($imageUrl !== ''): ?>
							<a href="<?=$imageUrl?>" target="_blank" class="d-block w-100 h-100">
								<img src="<?=$imageUrl?>" alt="<?=esc($label)?>">
							</a>
						<?php else: ?>
							<div class="rev-upload-empty">
								<i class="fa-regular fa-image d-block mb-2 fs-4"></i>
								<?=esc($emptyLabel)?>
							</div>
						<?php endif; ?>
					</div>
					<div class="rev-upload-meta">
						<div class="title"><?=esc($label)?></div>
						<?php if($imageUrl !== ''): ?>
							<div class="sub"><a href="<?=$imageUrl?>" target="_blank">Open image preview</a></div>
						<?php else: ?>
							<div class="sub">No image uploaded yet.</div>
						<?php endif; ?>
					</div>
				</div>
				<?php
			};
		?>
		<style>
			.rev-view-wrap {
				max-width: 960px;
				margin: 28px auto;
			}
			.rev-view-card {
				border-radius: 18px;
				box-shadow: 0 16px 38px rgba(25, 35, 58, 0.08);
				overflow: hidden;
			}
			.rev-view-head {
				padding: 22px 26px;
				background: linear-gradient(135deg, #fff6d220 0%, #fffdf740 100%);
			}
			.rev-view-body {
				padding: 24px 26px;
			}
			.rev-view-grid {
				display: grid;
				grid-template-columns: repeat(2, minmax(0, 1fr));
				gap: 14px 18px;
			}
			.rev-view-grid .item {
				padding: 12px 14px;
				border-radius: 12px;
				background: #fafbfd10;
				border: 0.2px solid #edf1f7;
			}
			.rev-view-grid .item.full {
				grid-column: 1 / -1;
			}
			.rev-view-grid .label {
				display: block;
				font-size: 12px;
				color: #6c757d;
				margin-bottom: 4px;
			}
			.rev-view-grid .value {
				font-weight: 600;
			}
			.rev-person {
				display: flex;
				align-items: center;
				gap: .55rem;
			}
			.rev-person img {
				width: 34px;
				height: 34px;
				object-fit: cover;
				border-radius: 50%;
				background: #f1f3f5;
				flex: 0 0 34px;
			}
			.rev-upload-preview{
				margin-top: .5rem;
				display: grid;
				grid-template-columns: 120px 1fr;
				gap: 1rem;
				align-items: start;
			}
			.rev-upload-thumb{
				width: 120px;
				height: 120px;
				border-radius: 12px;
				border: 1px solid var(--bs-border-color);
				background: rgba(var(--bs-secondary-rgb, 148, 163, 184), 0.08);
				display: flex;
				align-items: center;
				justify-content: center;
				overflow: hidden;
			}
			.rev-upload-thumb img{
				width: 100%;
				height: 100%;
				object-fit: cover;
			}
			.rev-upload-empty{
				text-align: center;
				padding: .75rem;
				font-size: .82rem;
				color: var(--bs-secondary-color);
				line-height: 1.35;
			}
			.rev-upload-meta{
				min-height: 120px;
				display: flex;
				flex-direction: column;
				justify-content: center;
			}
			.rev-upload-meta .title{
				font-weight: 700;
				margin-bottom: .35rem;
			}
			.rev-upload-meta .sub{
				font-size: .9rem;
				color: var(--bs-secondary-color);
			}
			@media (max-width: 768px) {
				.rev-view-grid {
					grid-template-columns: 1fr;
				}
				.rev-upload-preview {
					grid-template-columns: 1fr;
				}
			}
		</style>

		<div class="rev-view-wrap">
			<div class="rev-view-card">
				<div class="rev-view-head d-flex justify-content-between align-items-center gap-3 flex-wrap">
					<div>
						<h3 class="mb-1">Reversal #<?=esc($row->id)?></h3>
						<div class="text-muted">Source transaction: <?=esc(reversals_transaction_type_options()[$row->source_transaction_type ?? ''] ?? ($row->source_transaction_type ?? ''))?> / <?=esc($row->source_transaction_id ?? '')?></div>
					</div>
					<span class="<?=$row->status_badge_class?>"><?=esc($row->transaction_status ?? '')?></span>
				</div>

				<div class="rev-view-body">
					<div class="rev-view-grid">
						<div class="item">
							<span class="label">Service Provider / Network</span>
							<div class="value"><?=esc($row->network ?? '')?></div>
						</div>
						<div class="item">
							<span class="label">Service Type</span>
							<div class="value"><?=esc($row->service_type ?? '')?></div>
						</div>
						<div class="item">
							<span class="label">Receiver Details</span>
							<div class="value"><?=esc($row->receiver_details ?? '')?></div>
						</div>
						<div class="item">
							<span class="label">Sender Details</span>
							<div class="value"><?=esc($row->sender_details ?? '')?></div>
						</div>
						<div class="item">
							<span class="label">Amount Involved</span>
							<div class="value"><?=number_format((float)($row->amount ?? 0), 2)?></div>
						</div>
						<div class="item">
							<span class="label">Reversal Type</span>
							<div class="value"><?=esc($row->reversal_type ?? '')?></div>
						</div>
						<div class="item">
							<span class="label">Manager Review</span>
							<div class="value"><?=esc(ucwords(str_replace('_', ' ', (string)($row->manager_review_status ?? 'pending'))))?></div>
						</div>
						<div class="item">
							<span class="label">Initiated By</span>
							<?php
								$initiatorLabel = trim((string)(($row->user_first_name ?? '') . ' ' . ($row->user_last_name ?? '')));
								$initiatorLabel = $initiatorLabel !== '' ? $initiatorLabel : ((int)($row->created_by ?? 0) > 0 ? ('User #' . (int)$row->created_by) : 'Unknown user');
							?>
							<div class="value rev-person">
								<img src="<?=get_image($row->user_image ?? '')?>" alt="">
								<span><?=esc($initiatorLabel)?></span>
							</div>
						</div>
						<?php if(!empty($row->manager_review_note)): ?>
							<div class="item full">
								<span class="label"><?=strtolower(trim((string)($row->manager_review_status ?? ''))) === 'rejected' ? 'Manager Rejection Reason' : 'Manager Review Note'?></span>
								<div class="value"><?=esc($row->manager_review_note)?></div>
							</div>
						<?php endif; ?>
						<div class="item">
							<span class="label">Assigned Reviewer</span>
							<?php $assignedReviewerLabel = trim((string)(($row->assigned_first_name ?? '') . ' ' . ($row->assigned_last_name ?? ''))); ?>
							<div class="value rev-person">
								<img src="<?=get_image($row->assigned_image ?? '')?>" alt="">
								<span><?=esc($assignedReviewerLabel !== '' ? $assignedReviewerLabel : ((int)($row->assigned ?? 0) > 0 ? ('User #' . (int)$row->assigned) : 'Not assigned'))?></span>
							</div>
						</div>
						<div class="item">
							<span class="label">Resolution Status</span>
							<div class="value"><?=esc($row->resolution_status ? ucfirst((string)$row->resolution_status) : 'Pending')?></div>
						</div>
						<div class="item">
							<span class="label">Tracking Due</span>
							<div class="value"><?=!empty($row->tracking_due_at) ? get_date_time($row->tracking_due_at) : 'Not started'?></div>
						</div>
						<div class="item">
							<span class="label">Follow Up</span>
							<div class="value"><?=$row->followup_required ? 'Required' : 'Not required'?></div>
						</div>
						<div class="item">
							<span class="label">Total Refunded</span>
							<div class="value"><?=number_format((float)($row->total_refunded ?? 0), 2)?></div>
						</div>
						<div class="item">
							<span class="label">Total Claimed</span>
							<div class="value"><?=number_format((float)($row->total_claimed ?? 0), 2)?></div>
						</div>
						<div class="item full">
							<span class="label">Resolution Description</span>
							<div class="value"><?=esc($row->resolution_description ?? 'Not provided yet')?></div>
						</div>
						<?php if(!empty($row->action_proof_image)): ?>
							<div class="item full">
								<span class="label">Proof Of Action</span>
								<?php $renderViewImageCard('Proof Of Action', (string)$row->action_proof_image, 'No proof of action uploaded'); ?>
							</div>
						<?php endif; ?>
						<?php if(!empty($row->latest_refund_proof_image)): ?>
							<div class="item full">
								<span class="label">Latest Refund Proof</span>
								<?php $renderViewImageCard('Latest Refund Proof', (string)$row->latest_refund_proof_image, 'No refund proof uploaded'); ?>
							</div>
						<?php endif; ?>
						<?php if(!empty($row->latest_claim_selfie_image) || !empty($row->latest_claim_signature_image)): ?>
							<div class="item full">
								<span class="label">Claim Images</span>
								<div class="d-grid gap-3">
									<?php $renderViewImageCard('Claimee Selfie', (string)($row->latest_claim_selfie_image ?? ''), 'No claimee selfie uploaded'); ?>
									<?php $renderViewImageCard('Claimee Signature', (string)($row->latest_claim_signature_image ?? ''), 'No claimee signature uploaded'); ?>
								</div>
							</div>
						<?php endif; ?>
					</div>

					<?php if(!empty($row->refund_history) || !empty($row->claim_history)): ?>
						<div class="mt-4 d-grid gap-4">
							<?php if(!empty($row->refund_history)): ?>
								<div class="item full">
									<span class="label">Refund Proof History</span>
									<div class="d-grid gap-4 mt-2">
										<?php foreach(array_reverse($row->refund_history) as $entry): ?>
											<div class="border rounded-3 p-3 bg-light bg-opacity-25">
												<div class="fw-semibold"><?=number_format((float)($entry['amount'] ?? 0), 2)?> - <?=esc(ucfirst((string)($entry['review_status'] ?? 'pending')))?></div>
												<div class="small text-muted">Submitted: <?=!empty($entry['submitted_at']) ? get_date_time($entry['submitted_at']) : 'Pending'?></div>
												<div class="small text-muted">Reviewed: <?=!empty($entry['reviewed_at']) ? get_date_time($entry['reviewed_at']) : 'Pending'?></div>
												<?php if(!empty($entry['review_note'])): ?><div class="small mt-1"><?=esc($entry['review_note'])?></div><?php endif; ?>
												<?php if(!empty($entry['proof_image'])): ?>
													<div class="mt-3">
														<?php $renderViewImageCard('Refund Proof', (string)$entry['proof_image'], 'No refund proof uploaded'); ?>
													</div>
												<?php endif; ?>
											</div>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endif; ?>

							<?php if(!empty($row->claim_history)): ?>
								<div class="item full">
									<span class="label">Claim Image History</span>
									<div class="d-grid gap-4 mt-2">
										<?php foreach(array_reverse($row->claim_history) as $entry): ?>
											<div class="border rounded-3 p-3 bg-light bg-opacity-25">
												<div class="fw-semibold"><?=number_format((float)($entry['amount'] ?? 0), 2)?> - <?=esc(ucfirst((string)($entry['review_status'] ?? 'pending')))?></div>
												<div class="small text-muted">Submitted: <?=!empty($entry['submitted_at']) ? get_date_time($entry['submitted_at']) : 'Pending'?></div>
												<div class="small text-muted">Reviewed: <?=!empty($entry['reviewed_at']) ? get_date_time($entry['reviewed_at']) : 'Pending'?></div>
												<?php if(!empty($entry['review_note'])): ?><div class="small mt-1"><?=esc($entry['review_note'])?></div><?php endif; ?>
												<?php if(!empty($entry['selfie_image']) || !empty($entry['signature_image'])): ?>
													<div class="d-grid gap-3 mt-3">
														<?php if(!empty($entry['selfie_image'])): ?>
															<?php $renderViewImageCard('Claimee Selfie', (string)$entry['selfie_image'], 'No claimee selfie uploaded'); ?>
														<?php endif; ?>
														<?php if(!empty($entry['signature_image'])): ?>
															<?php $renderViewImageCard('Claimee Signature', (string)$entry['signature_image'], 'No claimee signature uploaded'); ?>
														<?php endif; ?>
													</div>
												<?php endif; ?>
											</div>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<div class="d-flex justify-content-between mt-4">
						<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>" class="btn btn-outline-primary">
							<i class="fa-solid fa-chevron-left"></i> Back
						</a>
						<?php if(!$row->is_closed): ?>
							<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$row->id?>" class="btn btn-outline-warning">
								<i class="fa-solid fa-pen-to-square"></i> Continue Workflow
							</a>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	<?php else: ?>
		<div class="alert alert-danger text-center">That record was not found.</div>
	<?php endif; ?>
<?php else: ?>
	<div class="alert alert-danger text-center">
		Access denied. You don't have permission for this action.
	</div>
<?php endif?>

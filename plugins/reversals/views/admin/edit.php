<?php if(user_can('edit_reversal')): ?>
	<?php
		$currentSession = get_session();
		$currentUserId = (int)($currentSession->id ?? 0);
		$isCreator = reversals_user_can_creator_manage($row, $currentSession);
		$canInitialReview = reversals_user_can_initial_review($currentSession, $row);
		$canRefundReview = reversals_user_can_refund_review($currentSession, $row);
		$canClaimReview = reversals_user_can_claim_review($currentSession, $row);
		$assignedReviewerLabel = trim((string)(($assignedReviewer->first_name ?? '') . ' ' . ($assignedReviewer->last_name ?? '')));
		$managerReviewed = strtolower(trim((string)($row->manager_review_status ?? '')));
		$resolutionStatus = strtolower(trim((string)($row->resolution_status ?? '')));
		$latestRefundStatus = strtolower(trim((string)($row->latest_refund_status ?? '')));
		$latestClaimStatus = strtolower(trim((string)($row->latest_claim_status ?? '')));
		$trackingDueLabel = !empty($row->tracking_due_at) ? get_date_time($row->tracking_due_at) : 'Not started';
		$claimAmount = number_format((float)($row->claimable_balance ?? 0), 2, '.', '');
		$isFullyRefunded = ((float)($row->total_refunded ?? 0) + 0.01) >= (float)($row->amount ?? 0);
		$renderUploadPreviewCard = static function(string $label, string $imagePath, string $emptyLabel, ?string $meta = null): void {
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
						<?php if($meta): ?><div class="sub"><?=$meta?></div><?php endif; ?>
					<?php else: ?>
						<div class="sub">No image uploaded yet.</div>
					<?php endif; ?>
				</div>
			</div>
			<?php
		};
	?>

	<style>
		.rev-shell {
			max-width: 1100px;
			margin: 28px auto;
			display: grid;
			grid-template-columns: 1.1fr 0.9fr;
			gap: 22px;
		}
		.rev-card {
			border-radius: 18px;
			box-shadow: 0 16px 38px rgba(25, 35, 58, 0.08);
		}
		.rev-card .rev-head {
			padding: 18px 22px;
			background: linear-gradient(135deg, #fff6d220 0%, #fffdf720 100%);
		}
		.rev-card .rev-body {
			padding: 22px;
		}
		.rev-summary {
			display: grid;
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 14px 18px;
		}
		.rev-summary .item {
			padding: 12px 14px;
			border-radius: 12px;
			background: #fafbfd20;
			border: 0.5px solid #edf1f7;
		}
		.rev-summary .item.full {
			grid-column: 1 / -1;
		}
		.rev-summary .label {
			display: block;
			font-size: 12px;
			color: #6c757d;
			margin-bottom: 4px;
		}
		.rev-summary .value {
			font-weight: 600;
		}
		.rev-timeline {
			display: flex;
			flex-direction: column;
			gap: 12px;
		}
		.rev-step {
			padding: 14px 16px;
			border-radius: 14px;
			background: #f9fbff20;
		}
		.rev-step .title {
			font-weight: 700;
			margin-bottom: 5px;
		}
		.rev-action-form {
			display: grid;
			gap: 14px;
		}
		.rev-action-form .btn-row {
			display: flex;
			justify-content: space-between;
			gap: 10px;
			flex-wrap: wrap;
		}
		.rev-proof {
			display: flex;
			align-items: center;
			gap: 12px;
			padding: 10px 12px;
			border-radius: 12px;
			background: #f8fafc20;
			border: 0.5px solid #edf1f5;
		}
		.rev-proof img {
			width: 64px;
			height: 64px;
			object-fit: cover;
			border-radius: 10px;
			border: 0.5px solid #e4e7eb;
		}
		.rev-history {
			max-height: 360px;
			overflow: auto;
		}
		.rev-history .entry {
			padding: 12px 0;
			border-bottom: 1px solid #edf1f5;
		}
		.rev-history .entry:last-child {
			border-bottom: 0;
		}
		.rev-upload-card{
			border-radius: 14px;
			padding: 1rem;
			background: rgba(var(--bs-secondary-rgb, 148, 163, 184), 0.06);
		}
		.rev-upload-actions{
			display: flex;
			align-items: center;
			gap: .75rem;
			flex-wrap: wrap;
		}
		.rev-upload-trigger{
			display: inline-flex;
			align-items: center;
			gap: .5rem;
			padding: .6rem .9rem;
			border-radius: 10px;
			border: 1px solid var(--bs-border-color);
			background: var(--bs-body-bg);
			cursor: pointer;
			font-weight: 600;
		}
		.rev-upload-preview{
			margin-top: 1rem;
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
		@media (max-width: 900px) {
			.rev-shell {
				grid-template-columns: 1fr;
			}
			.rev-summary {
				grid-template-columns: 1fr;
			}
		}
		@media (max-width: 575.98px) {
			.rev-upload-preview{
				grid-template-columns: 1fr;
			}
		}
	</style>

	<?php if(!empty($row)): ?>
		<div class="rev-shell">
			<div class="d-flex flex-column gap-4">
				<div class="rev-card">
					<div class="rev-head d-flex justify-content-between align-items-center gap-3 flex-wrap">
						<div>
							<h3 class="mb-1">Reversal #<?=esc($row->id)?></h3>
							<div class="text-muted">Track the full reversal workflow from acknowledgement to refund and claim.</div>
						</div>
						<span class="<?=$row->status_badge_class?>"><?=esc($row->transaction_status ?? '')?></span>
					</div>
					<div class="rev-body">
						<div class="rev-summary">
							<div class="item">
								<span class="label">Transaction Type</span>
								<div class="value"><?=esc(reversals_transaction_type_options()[$row->source_transaction_type ?? ''] ?? ($row->source_transaction_type ?? ''))?></div>
							</div>
							<div class="item">
								<span class="label">Transaction ID</span>
								<div class="value"><?=esc($row->source_transaction_id ?? '')?></div>
							</div>
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
								<div class="value"><?=esc(ucwords(str_replace('_', ' ', (string)($row->manager_review_status ?? 'Pending'))))?></div>
							</div>
							<div class="item">
								<span class="label">Assigned Reviewer</span>
								<div class="value"><?=esc($assignedReviewerLabel !== '' ? $assignedReviewerLabel : ((int)($row->assigned ?? 0) > 0 ? ('User #' . (int)$row->assigned) : 'Not assigned'))?></div>
							</div>
							<div class="item">
								<span class="label">Tracking Due</span>
								<div class="value"><?=$trackingDueLabel?></div>
							</div>
							<div class="item">
								<span class="label">Total Refunded</span>
								<div class="value"><?=number_format((float)($row->total_refunded ?? 0), 2)?></div>
							</div>
							<div class="item">
								<span class="label">Claimable Balance</span>
								<div class="value"><?=number_format((float)($row->claimable_balance ?? 0), 2)?></div>
							</div>
							<div class="item full">
								<span class="label">Resolution Description</span>
								<div class="value"><?=esc($row->resolution_description ?? 'Not provided yet')?></div>
							</div>
							<?php if(!empty($row->action_proof_image)): ?>
								<div class="item full">
									<span class="label">Proof Of Action</span>
									<div class="rev-proof">
										<img src="<?=get_image($row->action_proof_image)?>" alt="Proof of action">
										<div>
											<div><a href="<?=get_image($row->action_proof_image)?>" target="_blank">Open proof image</a></div>
											<div class="text-muted small">Uploaded <?=get_date_time($row->action_proof_uploaded_at ?? '')?></div>
										</div>
									</div>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<?php if(!$row->is_closed): ?>
					<div class="rev-card">
						<div class="rev-head">
							<h5 class="mb-1">Workflow Actions</h5>
							<div class="text-muted small">Only the creator or the assigned reviewer can continue the relevant phase.</div>
						</div>
						<div class="rev-body d-flex flex-column gap-4">
							<?php if($canInitialReview && $managerReviewed === 'pending'): ?>
								<form method="post" class="rev-action-form">
									<?=csrf()?>
									<input type="hidden" name="workflow_action" value="manager_review">
									<div class="fw-semibold">Manager acknowledgement</div>
									<div>
										<label class="form-label">Decision</label>
										<select class="form-select" name="manager_decision">
											<option value="">Select decision</option>
											<?php foreach(($reviewDecisionOptions ?? []) as $value => $label): ?>
												<option <?=old_select('manager_decision', $value)?> value="<?=$value?>"><?=$label?></option>
											<?php endforeach; ?>
										</select>
										<?php if(!empty($errors['manager_decision'])): ?><small class="text-danger"><?=$errors['manager_decision']?></small><?php endif; ?>
									</div>
									<div>
										<label class="form-label">Manager Note</label>
										<textarea class="form-control" rows="3" name="manager_review_note" placeholder="Add a note for the creator"><?=old_value('manager_review_note')?></textarea>
									</div>
									<div class="btn-row">
										<span class="text-muted small align-self-center">Reject ends the reversal. Acknowledge unlocks the creator action stage.</span>
										<button class="btn btn-warning" type="submit">Save Manager Decision</button>
									</div>
								</form>
							<?php endif; ?>

							<?php if($isCreator && $managerReviewed === 'acknowledged' && $resolutionStatus === ''): ?>
								<form method="post" class="rev-action-form">
									<?=csrf()?>
									<input type="hidden" name="workflow_action" value="creator_resolution">
									<div class="fw-semibold">Creator resolution</div>
									<div>
										<label class="form-label">Reversal Status</label>
										<select class="form-select" name="resolution_status">
											<option value="">Select status</option>
											<?php foreach(($resolutionStatusOptions ?? []) as $value => $label): ?>
												<option <?=old_select('resolution_status', $value)?> value="<?=$value?>"><?=$label?></option>
											<?php endforeach; ?>
										</select>
										<?php if(!empty($errors['resolution_status'])): ?><small class="text-danger"><?=$errors['resolution_status']?></small><?php endif; ?>
									</div>
									<div>
										<label class="form-label">Description</label>
										<textarea class="form-control" rows="3" name="resolution_description" placeholder="Describe the action plan or why this is non-reversible"><?=old_value('resolution_description')?></textarea>
										<?php if(!empty($errors['resolution_description'])): ?><small class="text-danger"><?=$errors['resolution_description']?></small><?php endif; ?>
									</div>
									<div class="btn-row">
										<span class="text-muted small align-self-center">Selecting non-reversible ends the workflow immediately.</span>
										<button class="btn btn-primary" type="submit">Submit Resolution</button>
									</div>
								</form>
							<?php endif; ?>

							<?php if($isCreator && $resolutionStatus === 'awaiting' && empty($row->action_proof_image)): ?>
								<form method="post" enctype="multipart/form-data" class="rev-action-form">
									<?=csrf()?>
									<input type="hidden" name="workflow_action" value="upload_action_proof">
									<div class="fw-semibold">Upload proof of action</div>
									<div>
										<label class="form-label">Proof Of Action Photo</label>
										<div class="rev-upload-card">
											<input type="file" class="d-none" name="action_proof_image" id="reversalActionProofInput" accept="image/*">
											<div class="rev-upload-actions">
												<label for="reversalActionProofInput" class="rev-upload-trigger">
													<i class="fa-regular fa-image"></i>
													<span>Choose Proof</span>
												</label>
												<div class="text-muted small">The selected proof image will open in the cropper before submission.</div>
											</div>
											<div class="rev-upload-preview">
												<div class="rev-upload-thumb">
													<div class="rev-upload-empty" id="reversalActionProofEmpty">
														<i class="fa-regular fa-image d-block mb-2 fs-4"></i>
														No proof selected yet
													</div>
													<img id="reversalActionProofPreview" alt="Proof of action preview" style="display:none;">
												</div>
												<div class="rev-upload-meta" id="reversalActionProofMeta">
													<div class="title">Proof of action preview</div>
													<div class="sub">Selected file details and thumbnail will appear here.</div>
												</div>
											</div>
										</div>
										<?php if(!empty($errors['action_proof_image'])): ?><small class="text-danger"><?=$errors['action_proof_image']?></small><?php endif; ?>
									</div>
									<div class="btn-row">
										<span class="text-muted small align-self-center">Tracking can only start after proof of action is uploaded.</span>
										<button class="btn btn-primary" type="submit">Upload Proof</button>
									</div>
								</form>
							<?php endif; ?>

							<?php if($isCreator && !empty($row->action_proof_image) && (empty($row->tracking_started_at) || $row->followup_required)): ?>
								<form method="post" class="rev-action-form">
									<?=csrf()?>
									<input type="hidden" name="workflow_action" value="<?=$row->followup_required ? 'followup_restart' : 'start_tracking'?>">
									<div class="fw-semibold"><?=$row->followup_required ? 'Follow-up and restart tracking' : 'Start tracking'?></div>
									<?php if($row->followup_required): ?>
										<div class="alert alert-warning mb-0">No full refund has been received before the tracking countdown expired. Enter follow-up feedback and start a new business-day countdown.</div>
									<?php endif; ?>
									<div>
										<label class="form-label">Number Of Days To Resolve</label>
										<input type="number" min="1" max="365" class="form-control" name="tracking_days" value="<?=old_value('tracking_days')?>" placeholder="Enter business days">
										<?php if(!empty($errors['tracking_days'])): ?><small class="text-danger"><?=$errors['tracking_days']?></small><?php endif; ?>
									</div>
									<div>
										<label class="form-label">Feedback From Follow-up</label>
										<textarea class="form-control" rows="3" name="tracking_feedback" placeholder="Only required when the previous countdown has expired"><?=old_value('tracking_feedback')?></textarea>
										<?php if(!empty($errors['tracking_feedback'])): ?><small class="text-danger"><?=$errors['tracking_feedback']?></small><?php endif; ?>
									</div>
									<div class="btn-row">
										<span class="text-muted small align-self-center">Weekends are excluded automatically from the countdown.</span>
										<button class="btn btn-primary" type="submit"><?=$row->followup_required ? 'Restart Tracking' : 'Start Countdown'?></button>
									</div>
								</form>
							<?php endif; ?>

							<?php if($isCreator && !$isFullyRefunded && !empty($row->action_proof_image) && $latestRefundStatus !== 'pending'): ?>
								<form method="post" enctype="multipart/form-data" class="rev-action-form">
									<?=csrf()?>
									<input type="hidden" name="workflow_action" value="submit_refund">
									<div class="fw-semibold">Submit refund received</div>
									<div>
										<label class="form-label">Amount Refunded</label>
										<input type="number" step="0.01" min="0.01" class="form-control" name="refund_amount" value="<?=old_value('refund_amount')?>" placeholder="Enter refund amount">
										<?php if(!empty($errors['refund_amount'])): ?><small class="text-danger"><?=$errors['refund_amount']?></small><?php endif; ?>
									</div>
									<div>
										<label class="form-label">Proof Of Refund</label>
										<div class="rev-upload-card">
											<input type="file" class="d-none" name="refund_proof_image" id="reversalRefundProofInput" accept="image/*">
											<div class="rev-upload-actions">
												<label for="reversalRefundProofInput" class="rev-upload-trigger">
													<i class="fa-regular fa-image"></i>
													<span>Choose Refund Proof</span>
												</label>
												<div class="text-muted small">The selected refund proof image will open in the cropper before submission.</div>
											</div>
											<div class="rev-upload-preview">
												<div class="rev-upload-thumb">
													<div class="rev-upload-empty" id="reversalRefundProofEmpty">
														<i class="fa-regular fa-image d-block mb-2 fs-4"></i>
														No refund proof selected yet
													</div>
													<img id="reversalRefundProofPreview" alt="Refund proof preview" style="display:none;">
												</div>
												<div class="rev-upload-meta" id="reversalRefundProofMeta">
													<div class="title">Refund proof preview</div>
													<div class="sub">Selected file details and thumbnail will appear here.</div>
												</div>
											</div>
										</div>
										<?php if(!empty($errors['refund_proof_image'])): ?><small class="text-danger"><?=$errors['refund_proof_image']?></small><?php endif; ?>
									</div>
									<div class="btn-row">
										<span class="text-muted small align-self-center">Admins or general managers will review this refund before it affects the cashdrawer.</span>
										<button class="btn btn-success" type="submit">Submit Refund For Review</button>
									</div>
								</form>
							<?php elseif($latestRefundStatus === 'pending'): ?>
								<div class="alert alert-info mb-0">A refund submission is waiting for review before another one can be sent.</div>
							<?php endif; ?>

							<?php if($canRefundReview && $latestRefundStatus === 'pending'): ?>
								<form method="post" class="rev-action-form">
									<?=csrf()?>
									<input type="hidden" name="workflow_action" value="review_refund">
									<div class="fw-semibold">Refund review</div>
									<div class="rev-proof">
										<img src="<?=get_image($row->latest_refund_proof_image ?? '')?>" alt="Refund proof">
										<div>
											<div><strong>Amount:</strong> <?=number_format((float)($row->latest_refund_amount ?? 0), 2)?></div>
											<div class="text-muted small">Submitted <?=get_date_time($row->latest_refund_submitted_at ?? '')?></div>
										</div>
									</div>
									<div>
										<label class="form-label">Decision</label>
										<select class="form-select" name="refund_review_decision">
											<option value="">Select decision</option>
											<?php foreach(($reviewDecisionOptions ?? []) as $value => $label): ?>
												<option <?=old_select('refund_review_decision', $value)?> value="<?=$value?>"><?=$label?></option>
											<?php endforeach; ?>
										</select>
										<?php if(!empty($errors['refund_review_decision'])): ?><small class="text-danger"><?=$errors['refund_review_decision']?></small><?php endif; ?>
									</div>
									<div>
										<label class="form-label">Review Note</label>
										<textarea class="form-control" rows="3" name="refund_review_note" placeholder="Add any note for the creator"><?=old_value('refund_review_note')?></textarea>
									</div>
									<div class="btn-row">
										<span class="text-muted small align-self-center">Acknowledging this refund credits the creator cashdrawer based on the original channel.</span>
										<button class="btn btn-warning" type="submit">Save Refund Review</button>
									</div>
								</form>
							<?php endif; ?>

							<?php if($isCreator && (float)($row->claimable_balance ?? 0) > 0.009 && $latestClaimStatus !== 'pending'): ?>
								<form method="post" enctype="multipart/form-data" class="rev-action-form" id="reversalClaimForm">
									<?=csrf()?>
									<input type="hidden" name="workflow_action" value="submit_claim">
									<input type="hidden" name="claim_denos_json" id="claim_denos_json" value="<?=htmlspecialchars((string)old_value('claim_denos_json'), ENT_QUOTES)?>">
									<div class="fw-semibold">Claim payout</div>
									<div class="alert alert-light mb-0">Mode of payment is fixed to <strong>Cash GHS</strong>. The cash denominations total must equal the claim amount exactly.</div>
									<div>
										<label class="form-label">Amount Paid</label>
										<div class="input-group" data-bs-toggle="modal" data-bs-target="#claimDenoModal" style="cursor:pointer;">
											<span class="input-group-text"><i class="fa-solid fa-calculator text-warning"></i></span>
											<input type="number" step="0.01" readonly class="form-control" name="claim_amount" id="claim_amount" value="<?=old_value('claim_amount', $claimAmount)?>">
										</div>
										<?php if(!empty($errors['claim_amount'])): ?><small class="text-danger"><?=$errors['claim_amount']?></small><?php endif; ?>
										<?php if(!empty($errors['claim_denos_json'])): ?><small class="text-danger d-block"><?=$errors['claim_denos_json']?></small><?php endif; ?>
									</div>
									<div>
										<label class="form-label">Claimee Selfie Photo</label>
										<div class="rev-upload-card">
											<input type="file" class="d-none" name="claim_selfie_image" id="reversalClaimSelfieInput" accept="image/*">
											<div class="rev-upload-actions">
												<label for="reversalClaimSelfieInput" class="rev-upload-trigger">
													<i class="fa-regular fa-image"></i>
													<span>Choose Selfie</span>
												</label>
												<div class="text-muted small">The claimee selfie will preview here before submission.</div>
											</div>
											<div class="rev-upload-preview">
												<div class="rev-upload-thumb">
													<div class="rev-upload-empty" id="reversalClaimSelfieEmpty">
														<i class="fa-regular fa-image d-block mb-2 fs-4"></i>
														No selfie selected yet
													</div>
													<img id="reversalClaimSelfiePreview" alt="Claim selfie preview" style="display:none;">
												</div>
												<div class="rev-upload-meta" id="reversalClaimSelfieMeta">
													<div class="title">Claimee selfie preview</div>
													<div class="sub">Selected file details and thumbnail will appear here.</div>
												</div>
											</div>
										</div>
										<?php if(!empty($errors['claim_selfie_image'])): ?><small class="text-danger"><?=$errors['claim_selfie_image']?></small><?php endif; ?>
									</div>
									<div>
										<label class="form-label">Claimee Signature Photo</label>
										<div class="rev-upload-card">
											<input type="file" class="d-none" name="claim_signature_image" id="reversalClaimSignatureInput" accept="image/*">
											<div class="rev-upload-actions">
												<label for="reversalClaimSignatureInput" class="rev-upload-trigger">
													<i class="fa-regular fa-image"></i>
													<span>Choose Signature</span>
												</label>
												<div class="text-muted small">The claimee signature will preview here before submission.</div>
											</div>
											<div class="rev-upload-preview">
												<div class="rev-upload-thumb">
													<div class="rev-upload-empty" id="reversalClaimSignatureEmpty">
														<i class="fa-regular fa-image d-block mb-2 fs-4"></i>
														No signature selected yet
													</div>
													<img id="reversalClaimSignaturePreview" alt="Claim signature preview" style="display:none;">
												</div>
												<div class="rev-upload-meta" id="reversalClaimSignatureMeta">
													<div class="title">Claimee signature preview</div>
													<div class="sub">Selected file details and thumbnail will appear here.</div>
												</div>
											</div>
										</div>
										<?php if(!empty($errors['claim_signature_image'])): ?><small class="text-danger"><?=$errors['claim_signature_image']?></small><?php endif; ?>
									</div>
									<div class="btn-row">
										<span class="text-muted small align-self-center">Managers acknowledge the cash claim before the payout is deducted from the creator drawer.</span>
										<button class="btn btn-primary" type="submit">Submit Claim For Review</button>
									</div>
								</form>
							<?php elseif($latestClaimStatus === 'pending'): ?>
								<div class="alert alert-info mb-0">A claim payout is waiting for review before another claim can be created.</div>
							<?php endif; ?>

							<?php if($canClaimReview && $latestClaimStatus === 'pending'): ?>
								<form method="post" class="rev-action-form">
									<?=csrf()?>
									<input type="hidden" name="workflow_action" value="review_claim">
									<div class="fw-semibold">Claim review</div>
									<div class="rev-proof">
										<img src="<?=get_image($row->latest_claim_selfie_image ?? '')?>" alt="Claimee selfie">
										<img src="<?=get_image($row->latest_claim_signature_image ?? '')?>" alt="Claimee signature">
										<div>
											<div><strong>Claim amount:</strong> <?=number_format((float)($row->latest_claim_amount ?? 0), 2)?></div>
											<div class="text-muted small">Submitted <?=get_date_time($row->latest_claim_submitted_at ?? '')?></div>
										</div>
									</div>
									<div>
										<label class="form-label">Decision</label>
										<select class="form-select" name="claim_review_decision">
											<option value="">Select decision</option>
											<?php foreach(($reviewDecisionOptions ?? []) as $value => $label): ?>
												<option <?=old_select('claim_review_decision', $value)?> value="<?=$value?>"><?=$label?></option>
											<?php endforeach; ?>
										</select>
										<?php if(!empty($errors['claim_review_decision'])): ?><small class="text-danger"><?=$errors['claim_review_decision']?></small><?php endif; ?>
									</div>
									<div>
										<label class="form-label">Review Note</label>
										<textarea class="form-control" rows="3" name="claim_review_note" placeholder="Add any note for the creator"><?=old_value('claim_review_note')?></textarea>
									</div>
									<div class="btn-row">
										<span class="text-muted small align-self-center">Acknowledging this claim deducts the exact denominations from the creator cashdrawer.</span>
										<button class="btn btn-warning" type="submit">Save Claim Review</button>
									</div>
								</form>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>

			<div class="d-flex flex-column gap-4">
				<div class="rev-card">
					<div class="rev-head">
						<h5 class="mb-1">Workflow Summary</h5>
						<div class="text-muted small">Current progress and deadlines.</div>
					</div>
					<div class="rev-body">
						<div class="rev-timeline">
							<div class="rev-step">
								<div class="title">1. Manager acknowledgement</div>
								<div class="text-muted small">Status: <?=esc(ucwords(str_replace('_', ' ', (string)($row->manager_review_status ?? 'pending'))))?></div>
								<?php if(!empty($row->manager_review_note)): ?><div class="small mt-2"><?=esc($row->manager_review_note)?></div><?php endif; ?>
							</div>
							<div class="rev-step">
								<div class="title">2. Creator action</div>
								<div class="text-muted small">Resolution: <?=esc($row->resolution_status ? ucfirst((string)$row->resolution_status) : 'Not set yet')?></div>
								<div class="text-muted small">Description: <?=esc($row->resolution_description ?: 'Pending')?></div>
							</div>
							<div class="rev-step">
								<div class="title">3. Tracking</div>
								<div class="text-muted small">Due: <?=$trackingDueLabel?></div>
								<div class="text-muted small">Countdown: <?php if($row->tracking_days_left === null): ?>Not started<?php elseif($row->tracking_days_left < 0): ?>Expired<?php else: ?><?=$row->tracking_days_left?> day(s) left<?php endif; ?></div>
								<?php if($row->followup_required): ?><div class="text-danger small mt-2">Follow-up is required because the countdown expired before the issue was fully resolved.</div><?php endif; ?>
							</div>
							<div class="rev-step">
								<div class="title">4. Refund</div>
								<div class="text-muted small">Refunded: <?=number_format((float)($row->total_refunded ?? 0), 2)?> / <?=number_format((float)($row->amount ?? 0), 2)?></div>
								<div class="text-muted small">Latest refund review: <?=esc($row->latest_refund_status ? ucfirst((string)$row->latest_refund_status) : 'None yet')?></div>
							</div>
							<div class="rev-step">
								<div class="title">5. Claim</div>
								<div class="text-muted small">Claimed: <?=number_format((float)($row->total_claimed ?? 0), 2)?></div>
								<div class="text-muted small">Available to claim: <?=number_format((float)($row->claimable_balance ?? 0), 2)?></div>
							</div>
						</div>
					</div>
				</div>

				<div class="rev-card">
					<div class="rev-head">
						<h5 class="mb-1">Tracking History</h5>
					</div>
					<div class="rev-body rev-history">
						<?php if(!empty($row->tracking_history)): ?>
							<?php foreach(array_reverse($row->tracking_history) as $entry): ?>
								<div class="entry">
									<div class="fw-semibold"><?=!empty($entry['is_followup']) ? 'Follow-up cycle' : 'Tracking cycle'?></div>
									<div class="small text-muted">Started: <?=!empty($entry['started_at']) ? get_date_time($entry['started_at']) : 'N/A'?></div>
									<div class="small text-muted">Due: <?=!empty($entry['due_at']) ? get_date_time($entry['due_at']) : 'N/A'?></div>
									<div class="small text-muted">Days: <?=esc((string)($entry['days'] ?? ''))?></div>
									<?php if(!empty($entry['feedback'])): ?><div class="small mt-2"><?=esc($entry['feedback'])?></div><?php endif; ?>
								</div>
							<?php endforeach; ?>
						<?php else: ?>
							<div class="text-muted">No tracking cycles yet.</div>
						<?php endif; ?>
					</div>
				</div>

				<div class="rev-card">
					<div class="rev-head">
						<h5 class="mb-1">Refund And Claim History</h5>
					</div>
					<div class="rev-body rev-history">
						<?php if(!empty($row->refund_history)): ?>
							<div class="fw-semibold mb-2">Refund reviews</div>
							<?php foreach(array_reverse($row->refund_history) as $entry): ?>
								<div class="entry">
									<div><strong><?=number_format((float)($entry['amount'] ?? 0), 2)?></strong> - <?=esc(ucfirst((string)($entry['review_status'] ?? 'pending')))?></div>
									<div class="small text-muted">Submitted: <?=!empty($entry['submitted_at']) ? get_date_time($entry['submitted_at']) : 'Pending'?></div>
									<div class="small text-muted">Reviewed: <?=!empty($entry['reviewed_at']) ? get_date_time($entry['reviewed_at']) : 'Pending'?></div>
									<?php if(!empty($entry['review_note'])): ?><div class="small mt-1"><?=esc($entry['review_note'])?></div><?php endif; ?>
									<?php if(!empty($entry['proof_image'])): ?>
										<div class="mt-3">
											<?php $renderUploadPreviewCard('Refund Proof', (string)$entry['proof_image'], 'No refund proof uploaded', !empty($entry['submitted_at']) ? ('Submitted ' . get_date_time($entry['submitted_at'])) : null); ?>
										</div>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>

						<?php if(!empty($row->claim_history)): ?>
							<div class="fw-semibold mt-3 mb-2">Claim reviews</div>
							<?php foreach(array_reverse($row->claim_history) as $entry): ?>
								<div class="entry">
									<div><strong><?=number_format((float)($entry['amount'] ?? 0), 2)?></strong> - <?=esc(ucfirst((string)($entry['review_status'] ?? 'pending')))?></div>
									<div class="small text-muted">Submitted: <?=!empty($entry['submitted_at']) ? get_date_time($entry['submitted_at']) : 'Pending'?></div>
									<div class="small text-muted">Reviewed: <?=!empty($entry['reviewed_at']) ? get_date_time($entry['reviewed_at']) : 'Pending'?></div>
									<?php if(!empty($entry['review_note'])): ?><div class="small mt-1"><?=esc($entry['review_note'])?></div><?php endif; ?>
									<?php if(!empty($entry['selfie_image']) || !empty($entry['signature_image'])): ?>
										<div class="d-grid gap-3 mt-3">
											<?php if(!empty($entry['selfie_image'])): ?>
												<?php $renderUploadPreviewCard('Claimee Selfie', (string)$entry['selfie_image'], 'No claimee selfie uploaded', !empty($entry['submitted_at']) ? ('Submitted ' . get_date_time($entry['submitted_at'])) : null); ?>
											<?php endif; ?>
											<?php if(!empty($entry['signature_image'])): ?>
												<?php $renderUploadPreviewCard('Claimee Signature', (string)$entry['signature_image'], 'No claimee signature uploaded', !empty($entry['submitted_at']) ? ('Submitted ' . get_date_time($entry['submitted_at'])) : null); ?>
											<?php endif; ?>
										</div>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>

						<?php if(empty($row->refund_history) && empty($row->claim_history)): ?>
							<div class="text-muted">No refund or claim history yet.</div>
						<?php endif; ?>
					</div>
				</div>

				<div class="d-flex justify-content-between">
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>" class="btn btn-outline-primary">
						<i class="fa-solid fa-chevron-left"></i> Back
					</a>
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>" class="btn btn-outline-secondary">
						<i class="fa-solid fa-eye"></i> View Summary
					</a>
				</div>
			</div>
		</div>

		<div class="modal fade" id="claimDenoModal" tabindex="-1" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">Cash GHS Denominations</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body table-responsive">
						<table class="table table-sm align-middle">
							<thead>
								<tr>
									<th>Note</th>
									<th style="width:90px;">Count</th>
									<th style="width:120px;">Volume</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach([200,100,50,20,10,5,2,1,0.5,0.2,0.1] as $deno): ?>
									<tr data-denomination="<?=$deno?>">
										<td>GH&#x20B5;<?=rtrim(rtrim(number_format($deno, 2), '0'), '.')?></td>
										<td><input type="number" min="0" class="form-control form-control-sm claim-deno-count"></td>
										<td><input type="text" class="form-control form-control-sm claim-deno-volume" readonly></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
							<tfoot>
								<tr>
									<th>Total</th>
									<th><input type="text" class="form-control form-control-sm" id="claimDenoCount" readonly></th>
									<th><input type="text" class="form-control form-control-sm" id="claimDenoTotal" readonly></th>
								</tr>
							</tfoot>
						</table>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-outline-secondary" id="claimDenoReset">Reset</button>
						<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
						<button type="button" class="btn btn-primary" id="claimDenoApply">Apply</button>
					</div>
				</div>
			</div>
		</div>

		<script>
			(function(){
				const modalEl = document.getElementById('claimDenoModal');
				const hiddenInput = document.getElementById('claim_denos_json');
				const amountInput = document.getElementById('claim_amount');

				function formatFileSize(bytes) {
					const size = Number(bytes || 0);
					if (size >= 1024 * 1024) return (size / (1024 * 1024)).toFixed(2) + ' MB';
					if (size >= 1024) return (size / 1024).toFixed(1) + ' KB';
					return size + ' bytes';
				}

				function attachImagePreview(config) {
					if (!config || !config.input) return;
					const input = config.input;
					const previewEl = config.preview;
					const placeholderEl = config.placeholder;
					const metaEl = config.meta;
					const title = String(config.title || 'Image preview');
					const modalTitle = String(config.modalTitle || title);
					const errorTitle = String(config.errorTitle || 'Invalid image');

					function resetPreview() {
						if (previewEl) {
							const objectUrl = String(previewEl.dataset.objectUrl || '').trim();
							if (objectUrl) {
								try { URL.revokeObjectURL(objectUrl); } catch (e) {}
								delete previewEl.dataset.objectUrl;
							}
							previewEl.removeAttribute('src');
							previewEl.style.display = 'none';
						}
						if (placeholderEl) placeholderEl.style.display = '';
						if (metaEl) {
							metaEl.innerHTML = '<div class="title">' + title + '</div><div class="sub">Selected file details and thumbnail will appear here.</div>';
						}
					}

					function updateMeta(file) {
						if (!metaEl || !file) return;
						metaEl.innerHTML =
							'<div class="title">' + String(file.name || title) + '</div>' +
							'<div class="sub">Type: ' + String(file.type || 'image') + '</div>' +
							'<div class="sub">Size: ' + formatFileSize(file.size || 0) + '</div>';
					}

					input.addEventListener('change', function () {
						const file = input.files && input.files[0] ? input.files[0] : null;
						if (!file) {
							resetPreview();
							return;
						}

						if (window.AppImageCropper && typeof window.AppImageCropper.handleLegacyChange === 'function') {
							window.AppImageCropper.handleLegacyChange({ currentTarget: input }, {
								previewEl: previewEl,
								placeholderEl: placeholderEl,
								maxBytes: window.AppImageCropper.MAX_BYTES,
								modalTitle: modalTitle,
								errorTitle: errorTitle,
								onSuccess: function (result) {
									if (result && result.file) {
										updateMeta(result.file);
									}
								}
							}).then(function (result) {
								const finalFile = input.files && input.files[0] ? input.files[0] : null;
								if (!result || !result.ok || !finalFile) {
									resetPreview();
								} else {
									updateMeta(finalFile);
								}
							});
							return;
						}

						const reader = new FileReader();
						reader.onload = function (event) {
							if (previewEl) {
								previewEl.src = String(event.target && event.target.result ? event.target.result : '');
								previewEl.style.display = 'block';
							}
							if (placeholderEl) placeholderEl.style.display = 'none';
							updateMeta(file);
						};
						reader.readAsDataURL(file);
					});
				}

				attachImagePreview({
					input: document.getElementById('reversalActionProofInput'),
					preview: document.getElementById('reversalActionProofPreview'),
					placeholder: document.getElementById('reversalActionProofEmpty'),
					meta: document.getElementById('reversalActionProofMeta'),
					title: 'Proof of action preview',
					modalTitle: 'Crop reversal proof of action',
					errorTitle: 'Invalid proof of action image'
				});
				attachImagePreview({
					input: document.getElementById('reversalRefundProofInput'),
					preview: document.getElementById('reversalRefundProofPreview'),
					placeholder: document.getElementById('reversalRefundProofEmpty'),
					meta: document.getElementById('reversalRefundProofMeta'),
					title: 'Refund proof preview',
					modalTitle: 'Crop reversal refund proof',
					errorTitle: 'Invalid refund proof image'
				});
				attachImagePreview({
					input: document.getElementById('reversalClaimSelfieInput'),
					preview: document.getElementById('reversalClaimSelfiePreview'),
					placeholder: document.getElementById('reversalClaimSelfieEmpty'),
					meta: document.getElementById('reversalClaimSelfieMeta'),
					title: 'Claimee selfie preview',
					modalTitle: 'Crop claimee selfie',
					errorTitle: 'Invalid claimee selfie image'
				});
				attachImagePreview({
					input: document.getElementById('reversalClaimSignatureInput'),
					preview: document.getElementById('reversalClaimSignaturePreview'),
					placeholder: document.getElementById('reversalClaimSignatureEmpty'),
					meta: document.getElementById('reversalClaimSignatureMeta'),
					title: 'Claimee signature preview',
					modalTitle: 'Crop claimee signature',
					errorTitle: 'Invalid claimee signature image'
				});

				if (!modalEl || !hiddenInput || !amountInput) {
					return;
				}

				const countInputs = modalEl.querySelectorAll('.claim-deno-count');
				const countField = document.getElementById('claimDenoCount');
				const totalField = document.getElementById('claimDenoTotal');
				const resetBtn = document.getElementById('claimDenoReset');
				const applyBtn = document.getElementById('claimDenoApply');

				function recalc(){
					let totalCount = 0;
					let totalVolume = 0;
					const snapshot = [];
					countInputs.forEach(input => {
						const row = input.closest('tr');
						const denomination = parseFloat(row.dataset.denomination || '0');
						const count = parseInt(input.value || '0', 10) || 0;
						const volume = denomination * count;
						row.querySelector('.claim-deno-volume').value = count > 0 ? volume.toFixed(2) : '';
						totalCount += count;
						totalVolume += volume;
						if (count > 0) {
							snapshot.push({
								currency: 'GHS',
								denomination: denomination,
								count: count,
								volume: volume
							});
						}
					});
					if (countField) countField.value = totalCount ? String(totalCount) : '';
					if (totalField) totalField.value = totalVolume ? totalVolume.toFixed(2) : '';
					return {snapshot, totalVolume};
				}

				function restore(){
					countInputs.forEach(input => {
						input.value = '';
						const row = input.closest('tr');
						row.querySelector('.claim-deno-volume').value = '';
					});
					try {
						const payload = JSON.parse(hiddenInput.value || '[]');
						if (Array.isArray(payload)) {
							payload.forEach(item => {
								countInputs.forEach(input => {
									const row = input.closest('tr');
									const denomination = parseFloat(row.dataset.denomination || '0');
									if (Math.abs(denomination - parseFloat(item.denomination || 0)) < 0.0001) {
										input.value = String(parseInt(item.count || 0, 10) || 0);
									}
								});
							});
						}
					} catch (error) {}
					recalc();
				}

				countInputs.forEach(input => input.addEventListener('input', recalc));
				modalEl.addEventListener('shown.bs.modal', restore);

				if (resetBtn) {
					resetBtn.addEventListener('click', function(){
						hiddenInput.value = '';
						countInputs.forEach(input => {
							input.value = '';
							const row = input.closest('tr');
							row.querySelector('.claim-deno-volume').value = '';
						});
						recalc();
					});
				}

				if (applyBtn) {
					applyBtn.addEventListener('click', function(){
						const result = recalc();
						hiddenInput.value = JSON.stringify(result.snapshot);
						if (result.totalVolume) {
							amountInput.value = result.totalVolume.toFixed(2);
						}
						if (Math.abs(parseFloat(amountInput.value || '0') - result.totalVolume) > 0.01) {
							alert('Cash denominations total must equal the refund amount exactly.');
							return;
						}
						const modal = bootstrap.Modal.getInstance(modalEl);
						if (modal) modal.hide();
					});
				}

			})();
		</script>
	<?php else: ?>
		<div class="alert alert-danger text-center">Record not found.</div>
	<?php endif; ?>

<?php else: ?>
	<div class="alert alert-danger text-center">
		Access denied. You don't have permission for this action.
	</div>
<?php endif ?>

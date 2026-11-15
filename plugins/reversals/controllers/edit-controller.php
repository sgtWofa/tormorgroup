<?php

if (!empty($row)) {
	date_default_timezone_set('Africa/Accra');

	$postdata = $req->post();
	$postdata['id'] = $row->id;
	$csrf = csrf_verify($postdata);
	$reversal = new \Reversals\Reversal;
	$session = get_session();
	$userId = (int)($session->id ?? 0);
	$username = trim((string)(($session->first_name ?? '') . ' ' . ($session->last_name ?? '')));
	$now = reversals_now()->format('Y-m-d H:i:s');
	$action = trim((string)($postdata['workflow_action'] ?? ''));

	if ($csrf) {
		$updates = [
			'updated_by' => $userId,
			'date_updated' => $now,
		];

		$refreshRow = function () use ($reversal, $row) {
			return reversals_hydrate_row($reversal->first(['id' => $row->id]));
		};

		switch ($action) {
			case 'manager_review':
				if (!reversals_user_can_initial_review($session, $row)) {
					$reversal->errors['manager_decision'] = reversals_assigned_reviewer_id($row) > 0
						? 'Only the assigned general manager or admin can acknowledge this reversal.'
						: 'Only a manager, general manager or admin can review this reversal.';
					break;
				}

				$decision = strtolower(trim((string)($postdata['manager_decision'] ?? '')));
				if (!in_array($decision, ['acknowledged', 'rejected'], true)) {
					$reversal->errors['manager_decision'] = 'Choose whether to acknowledge or reject this reversal.';
					break;
				}

				$note = trim((string)($postdata['manager_review_note'] ?? ''));
				$updates['manager_review_status'] = $decision;
				$updates['manager_review_note'] = $note;
				$updates['manager_reviewed_by'] = $userId;
				$updates['manager_reviewed_at'] = $now;
				if ($decision === 'rejected') {
					$updates['workflow_stage'] = 'ended';
					$updates['transaction_status'] = 'Manager Rejected';
					$updates['closed_at'] = $now;
				} else {
					$updates['workflow_stage'] = 'creator_resolution';
					$updates['transaction_status'] = 'Manager Acknowledged';
					$updates['closed_at'] = null;
				}
				break;

			case 'creator_resolution':
				if (!reversals_user_can_creator_manage($row, $session)) {
					$reversal->errors['resolution_status'] = 'Only the creator can continue this reversal after manager acknowledgement.';
					break;
				}
				if (strtolower(trim((string)($row->manager_review_status ?? ''))) !== 'acknowledged') {
					$reversal->errors['resolution_status'] = 'This reversal has not been acknowledged by a manager yet.';
					break;
				}

				$resolutionStatus = strtolower(trim((string)($postdata['resolution_status'] ?? '')));
				$resolutionDescription = trim((string)($postdata['resolution_description'] ?? ''));
				if (!in_array($resolutionStatus, ['awaiting', 'non-reversible'], true)) {
					$reversal->errors['resolution_status'] = 'Choose whether this reversal is awaiting or non-reversible.';
				}
				if ($resolutionDescription === '') {
					$reversal->errors['resolution_description'] = 'Description is required.';
				}
				if (!empty($reversal->errors)) {
					break;
				}

				$updates['resolution_status'] = $resolutionStatus;
				$updates['resolution_description'] = $resolutionDescription;
				if ($resolutionStatus === 'non-reversible') {
					$updates['workflow_stage'] = 'ended';
					$updates['transaction_status'] = 'Non-reversible';
					$updates['closed_at'] = $now;
				} else {
					$updates['workflow_stage'] = 'action_proof';
					$updates['transaction_status'] = 'Awaiting Proof of Action';
					$updates['closed_at'] = null;
				}
				break;

			case 'upload_action_proof':
				if (!reversals_user_can_creator_manage($row, $session)) {
					$reversal->errors['action_proof_image'] = 'Only the creator can upload proof of action.';
					break;
				}
				$req->upload_folder = 'uploads/reversals';
				$uploaded = $req->upload_files('action_proof_image');
				if ($uploaded === '') {
					$reversal->errors['action_proof_image'] = $req->upload_errors['action_proof_image'] ?? 'Proof of action image is required.';
					break;
				}

				$updates['action_proof_image'] = $uploaded;
				$updates['action_proof_uploaded_at'] = $now;
				$updates['workflow_stage'] = 'tracking_pending';
				$updates['transaction_status'] = 'Proof Uploaded - Start Tracking';
				break;

			case 'start_tracking':
			case 'followup_restart':
				if (!reversals_user_can_creator_manage($row, $session)) {
					$reversal->errors['tracking_days'] = 'Only the creator can manage tracking for this reversal.';
					break;
				}

				$trackingDays = (int)($postdata['tracking_days'] ?? 0);
				$trackingFeedback = trim((string)($postdata['tracking_feedback'] ?? ''));
				if ($trackingDays < 1 || $trackingDays > 365) {
					$reversal->errors['tracking_days'] = 'Tracking days must be between 1 and 365.';
				}
				if ($action === 'followup_restart' && $trackingFeedback === '') {
					$reversal->errors['tracking_feedback'] = 'Feedback is required before restarting tracking.';
				}
				if (!empty($reversal->errors)) {
					break;
				}

				$startAt = $action === 'followup_restart'
					? $now
					: trim((string)($row->action_proof_uploaded_at ?? $now));
				$dueAt = reversals_add_business_days($startAt, $trackingDays);
				if ($dueAt === null) {
					$reversal->errors['tracking_days'] = 'Tracking due date could not be calculated.';
					break;
				}

				$trackingHistory = reversals_append_history_item($row->tracking_history_json ?? '', [
					'started_at' => $startAt,
					'due_at' => $dueAt,
					'days' => $trackingDays,
					'feedback' => $trackingFeedback,
					'is_followup' => $action === 'followup_restart',
					'created_by' => $userId,
				]);

				$updates['tracking_days'] = $trackingDays;
				$updates['tracking_started_at'] = $startAt;
				$updates['tracking_due_at'] = $dueAt;
				$updates['tracking_history_json'] = $trackingHistory;
				$updates['followup_feedback'] = $trackingFeedback;
				$updates['workflow_stage'] = 'tracking';
				$updates['transaction_status'] = 'Tracking Active';
				break;

			case 'submit_refund':
				if (!reversals_user_can_creator_manage($row, $session)) {
					$reversal->errors['refund_amount'] = 'Only the creator can submit refund details.';
					break;
				}
				$refundAmount = round((float)($postdata['refund_amount'] ?? 0), 2);
				if ($refundAmount <= 0) {
					$reversal->errors['refund_amount'] = 'Refund amount must be greater than zero.';
				}
				if ($refundAmount - reversals_remaining_amount($row) > 0.01) {
					$reversal->errors['refund_amount'] = 'Refund amount cannot be more than the outstanding reversal amount.';
				}
				$req->upload_folder = 'uploads/reversals';
				$refundProof = $req->upload_files('refund_proof_image');
				if ($refundProof === '') {
					$reversal->errors['refund_proof_image'] = $req->upload_errors['refund_proof_image'] ?? 'Proof of refund is required.';
				}
				if (!empty($reversal->errors)) {
					break;
				}

				$updates['latest_refund_amount'] = $refundAmount;
				$updates['latest_refund_proof_image'] = $refundProof;
				$updates['latest_refund_status'] = 'pending';
				$updates['latest_refund_submitted_at'] = $now;
				$updates['latest_refund_reviewed_by'] = null;
				$updates['latest_refund_reviewed_at'] = null;
				$updates['latest_refund_review_note'] = null;
				$updates['workflow_stage'] = 'refund_review';
				$updates['transaction_status'] = 'Refund Pending Review';
				break;

			case 'review_refund':
				if (!reversals_user_can_refund_review($session, $row)) {
					$reversal->errors['refund_review_decision'] = reversals_assigned_reviewer_id($row) > 0
						? 'Only the assigned general manager or admin can review this refund submission.'
						: 'Only an admin or general manager can review refund submissions.';
					break;
				}

				$decision = strtolower(trim((string)($postdata['refund_review_decision'] ?? '')));
				$reviewNote = trim((string)($postdata['refund_review_note'] ?? ''));
				if (!in_array($decision, ['acknowledged', 'rejected'], true)) {
					$reversal->errors['refund_review_decision'] = 'Choose whether to acknowledge or reject this refund.';
					break;
				}

				$latestRefundAmount = round((float)($row->latest_refund_amount ?? 0), 2);
				if ($latestRefundAmount <= 0) {
					$reversal->errors['refund_review_decision'] = 'There is no pending refund amount to review.';
					break;
				}

				if ($decision === 'acknowledged') {
					$sourceReverseResult = reversals_reverse_source_effects($row, $userId);
					if (empty($sourceReverseResult['ok'])) {
						$reversal->errors['refund_review_decision'] = $sourceReverseResult['message'] ?? 'Unable to reverse the original transaction effects.';
						break;
					}

					$creditResult = reversals_credit_creator_for_refund($row, $latestRefundAmount);
					if (empty($creditResult['ok'])) {
						$reversal->errors['refund_review_decision'] = $creditResult['message'] ?? 'Failed to credit creator cashdrawer.';
						break;
					}

					$newTotalRefunded = round((float)($row->total_refunded ?? 0) + $latestRefundAmount, 2);
					$refundState = $newTotalRefunded + 0.01 >= (float)($row->amount ?? 0) ? 'full refund' : 'partial refund';
					$updates['total_refunded'] = $newTotalRefunded;
					$updates['latest_refund_status'] = 'acknowledged';
					$updates['latest_refund_reviewed_by'] = $userId;
					$updates['latest_refund_reviewed_at'] = $now;
					$updates['latest_refund_review_note'] = $reviewNote;
					$updates['source_effects_reversed_at'] = $now;
					$updates['source_effects_reversed_by'] = $userId;
					$updates['source_effects_reversal_note'] = $sourceReverseResult['message'] ?? '';
					$updates['refund_history_json'] = reversals_append_history_item($row->refund_history_json ?? '', [
						'amount' => $latestRefundAmount,
						'proof_image' => (string)($row->latest_refund_proof_image ?? ''),
						'submitted_at' => (string)($row->latest_refund_submitted_at ?? ''),
						'reviewed_at' => $now,
						'reviewed_by' => $userId,
						'review_status' => 'acknowledged',
						'review_note' => $reviewNote,
						'refund_state' => $refundState,
					]);
					$updates['workflow_stage'] = 'claim';
					$updates['transaction_status'] = $refundState === 'full refund' ? 'Full Refund Acknowledged' : 'Partial Refund Acknowledged';
				} else {
					$updates['latest_refund_status'] = 'rejected';
					$updates['latest_refund_reviewed_by'] = $userId;
					$updates['latest_refund_reviewed_at'] = $now;
					$updates['latest_refund_review_note'] = $reviewNote;
					$updates['refund_history_json'] = reversals_append_history_item($row->refund_history_json ?? '', [
						'amount' => $latestRefundAmount,
						'proof_image' => (string)($row->latest_refund_proof_image ?? ''),
						'submitted_at' => (string)($row->latest_refund_submitted_at ?? ''),
						'reviewed_at' => $now,
						'reviewed_by' => $userId,
						'review_status' => 'rejected',
						'review_note' => $reviewNote,
					]);
					$updates['workflow_stage'] = 'refund_rejected';
					$updates['transaction_status'] = 'Refund Rejected - Resubmit';
				}
				break;

			case 'submit_claim':
				if (!reversals_user_can_creator_manage($row, $session)) {
					$reversal->errors['claim_amount'] = 'Only the creator can submit a claim payout.';
					break;
				}

				$claimableBalance = reversals_claimable_balance($row);
				$claimAmount = round((float)($postdata['claim_amount'] ?? 0), 2);
				if ($claimableBalance <= 0) {
					$reversal->errors['claim_amount'] = 'There is no acknowledged refund available to claim yet.';
				} elseif (abs($claimAmount - $claimableBalance) > 0.01) {
					$reversal->errors['claim_amount'] = 'Claim amount must match the acknowledged refund balance available for payout.';
				}

				$req->upload_folder = 'uploads/reversals';
				$claimSelfie = $req->upload_files('claim_selfie_image');
				$claimSignature = $req->upload_files('claim_signature_image');
				if ($claimSelfie === '') {
					$reversal->errors['claim_selfie_image'] = $req->upload_errors['claim_selfie_image'] ?? 'Claimee selfie photo is required.';
				}
				if ($claimSignature === '') {
					$reversal->errors['claim_signature_image'] = $req->upload_errors['claim_signature_image'] ?? 'Claimee signature photo is required.';
				}
				$claimDenosJson = trim((string)($postdata['claim_denos_json'] ?? ''));
				if ($claimDenosJson === '') {
					$reversal->errors['claim_denos_json'] = 'Currency denominations are required for the claim payout.';
				}
				if (!empty($reversal->errors)) {
					break;
				}

				$updates['latest_claim_amount'] = $claimAmount;
				$updates['latest_claim_selfie_image'] = $claimSelfie;
				$updates['latest_claim_signature_image'] = $claimSignature;
				$updates['latest_claim_denos_json'] = $claimDenosJson;
				$updates['latest_claim_status'] = 'pending';
				$updates['latest_claim_submitted_at'] = $now;
				$updates['latest_claim_reviewed_by'] = null;
				$updates['latest_claim_reviewed_at'] = null;
				$updates['latest_claim_review_note'] = null;
				$updates['workflow_stage'] = 'claim_review';
				$updates['transaction_status'] = 'Claim Pending Review';
				break;

			case 'review_claim':
				if (!reversals_user_can_claim_review($session, $row)) {
					$reversal->errors['claim_review_decision'] = reversals_assigned_reviewer_id($row) > 0
						? 'Only the assigned general manager or admin can review this claim payout.'
						: 'Only a manager, general manager or admin can review claim payouts.';
					break;
				}

				$decision = strtolower(trim((string)($postdata['claim_review_decision'] ?? '')));
				$reviewNote = trim((string)($postdata['claim_review_note'] ?? ''));
				if (!in_array($decision, ['acknowledged', 'rejected'], true)) {
					$reversal->errors['claim_review_decision'] = 'Choose whether to acknowledge or reject this claim payout.';
					break;
				}

				$latestClaimAmount = round((float)($row->latest_claim_amount ?? 0), 2);
				if ($latestClaimAmount <= 0) {
					$reversal->errors['claim_review_decision'] = 'There is no pending claim amount to review.';
					break;
				}

				if ($decision === 'acknowledged') {
					$debitResult = reversals_debit_creator_cash_for_claim($row, (string)($row->latest_claim_denos_json ?? ''), $latestClaimAmount);
					if (empty($debitResult['ok'])) {
						$reversal->errors['claim_review_decision'] = $debitResult['message'] ?? 'Failed to deduct creator cashdrawer for this claim payout.';
						break;
					}

					$newTotalClaimed = round((float)($row->total_claimed ?? 0) + $latestClaimAmount, 2);
					$newClaimable = round(max(0, (float)($row->total_refunded ?? 0) - $newTotalClaimed), 2);
					$updates['total_claimed'] = $newTotalClaimed;
					$updates['latest_claim_status'] = 'acknowledged';
					$updates['latest_claim_reviewed_by'] = $userId;
					$updates['latest_claim_reviewed_at'] = $now;
					$updates['latest_claim_review_note'] = $reviewNote;
					$updates['claim_history_json'] = reversals_append_history_item($row->claim_history_json ?? '', [
						'amount' => $latestClaimAmount,
						'selfie_image' => (string)($row->latest_claim_selfie_image ?? ''),
						'signature_image' => (string)($row->latest_claim_signature_image ?? ''),
						'denos_json' => (string)($row->latest_claim_denos_json ?? ''),
						'submitted_at' => (string)($row->latest_claim_submitted_at ?? ''),
						'reviewed_at' => $now,
						'reviewed_by' => $userId,
						'review_status' => 'acknowledged',
						'review_note' => $reviewNote,
					]);

					$sourceAmount = (float)($row->amount ?? 0);
					if ($newTotalClaimed + 0.01 >= $sourceAmount && ((float)($row->total_refunded ?? 0) + 0.01) >= $sourceAmount) {
						$updates['workflow_stage'] = 'closed';
						$updates['transaction_status'] = 'Closed';
						$updates['closed_at'] = $now;
					} elseif ($newClaimable > 0.01) {
						$updates['workflow_stage'] = 'claim';
						$updates['transaction_status'] = 'Partial Claim Acknowledged';
					} elseif ($sourceAmount - $newTotalClaimed > 0.01) {
						$updates['workflow_stage'] = 'tracking';
						$updates['transaction_status'] = 'Tracking Active';
					} else {
						$updates['workflow_stage'] = 'closed';
						$updates['transaction_status'] = 'Closed';
						$updates['closed_at'] = $now;
					}
				} else {
					$updates['latest_claim_status'] = 'rejected';
					$updates['latest_claim_reviewed_by'] = $userId;
					$updates['latest_claim_reviewed_at'] = $now;
					$updates['latest_claim_review_note'] = $reviewNote;
					$updates['claim_history_json'] = reversals_append_history_item($row->claim_history_json ?? '', [
						'amount' => $latestClaimAmount,
						'selfie_image' => (string)($row->latest_claim_selfie_image ?? ''),
						'signature_image' => (string)($row->latest_claim_signature_image ?? ''),
						'denos_json' => (string)($row->latest_claim_denos_json ?? ''),
						'submitted_at' => (string)($row->latest_claim_submitted_at ?? ''),
						'reviewed_at' => $now,
						'reviewed_by' => $userId,
						'review_status' => 'rejected',
						'review_note' => $reviewNote,
					]);
					$updates['workflow_stage'] = 'claim';
					$updates['transaction_status'] = 'Claim Rejected - Resubmit';
				}
				break;

			default:
				$reversal->errors['workflow_action'] = 'Invalid workflow action.';
				break;
		}

		if (empty($reversal->errors)) {
			$reversal->update($row->id, $updates);
			reversals_log_activity(
				'updated',
				$userId,
				$username,
				$username . ' updated reversal #' . $row->id . ' using action ' . $action
			);

			$row = $refreshRow();
			message_success('Reversal updated successfully.');
			redirect($admin_route . '/' . $plugin_route . '/edit/' . $row->id);
		}
	}

	if (!$csrf) {
		$reversal->errors['workflow_action'] = 'Form expired!';
	}

	set_value('errors', array_merge($reversal->errors, $req->upload_errors));
} else {
	message_fail("Record not found");
}

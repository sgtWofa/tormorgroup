<?php

defined('ROOT') or die('Direct script access denied');

$commissionBankRoute = $admin_route . '/' . $plugin_route . '/commission_bank_transfers';
$commissionBankIsPdf = URL(3) === 'pdf';
$commissionBankPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$commissionBankIsAjax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
	|| strpos(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json') !== false;
if ($commissionBankPost && $commissionBankIsAjax && ob_get_level() === 0) {
	ob_start();
}
$commissionBankSession = get_session();
$commissionBankUserId = (int)($commissionBankSession->id ?? 0);
$commissionBankIsAdmin = backoffice_user_is_admin($commissionBankSession);
$commissionBankRespond = static function (bool $success, string $message, array $data = [], ?int $status = null) use ($commissionBankIsAjax, $commissionBankRoute): void {
	if ($commissionBankIsAjax) {
		while (ob_get_level()) {
			ob_end_clean();
		}
		http_response_code($status ?? ($success ? 200 : 422));
		header('Content-Type: application/json; charset=UTF-8');
		$payload = json_encode(
			['success' => $success, 'message' => $message] + $data,
			JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
		);
		echo $payload !== false ? $payload : '{"success":false,"message":"Unable to encode the transfer response."}';
		exit;
	}
	if ($success) {
		message_success($message);
	} else {
		message_fail($message);
	}
	redirect($data['redirect'] ?? $commissionBankRoute);
	exit;
};
$commissionBankCanInitiate = $commissionBankIsAdmin
	|| backoffice_user_is_general_manager($commissionBankSession)
	|| backoffice_user_is_operations_manager($commissionBankSession);

if (!$commissionBankCanInitiate) {
	if ($commissionBankPost && $commissionBankIsAjax) {
		$commissionBankRespond(false, 'Only operations managers, general managers, and admins can manage commission bank transfers.', [], 403);
	}
	message_fail('Only operations managers, general managers, and admins can manage commission bank transfers.');
	redirect($admin_route . '/' . $plugin_route . '/list');
	exit;
}

try {
	backoffice_ensure_commission_bank_schema();
} catch (\Throwable $schemaError) {
	error_log('[commission bank transfer schema] ' . $schemaError->getMessage());
	if ($commissionBankPost && $commissionBankIsAjax) {
		$commissionBankRespond(false, 'Commission bank transfers are temporarily unavailable because the ledger could not be prepared.', [], 500);
	}
	message_fail('Commission bank transfers are temporarily unavailable because the ledger could not be prepared.');
	redirect($admin_route . '/' . $plugin_route . '/list');
	exit;
}

$commissionBankDb = new \Core\Database;
if ($commissionBankPost && $commissionBankIsAjax) {
	set_exception_handler(static function (\Throwable $error) use ($commissionBankRespond): void {
		error_log('[commission bank transfer ajax] ' . $error->getMessage());
		$commissionBankRespond(false, 'The server could not complete the transfer action. Please retry; if it continues, contact an administrator.', [], 500);
	});
}
$commissionBankApprovers = $commissionBankIsPdf ? [] : backoffice_commission_bank_admin_approvers();

if ($commissionBankPost) {
	$postdata = $req->post();
	if (!csrf_verify($postdata)) {
		$commissionBankRespond(false, 'Form expired. Please try again.');
	}

	$action = strtolower(trim((string)($postdata['action'] ?? '')));
	$reviewAction = strtolower(trim((string)($postdata['review_decision'] ?? '')));
	if ($action === 'review_transfer' && in_array($reviewAction, ['approve', 'reject'], true)) {
		$action = $reviewAction;
	}
	$transferId = (int)($postdata['transfer_id'] ?? 0);
	$now = date('Y-m-d H:i:s');

	if ($action === 'submit_transfer') {
		$bankName = trim((string)($postdata['bank_name'] ?? ''));
		$assignedAdminId = (int)($postdata['assigned_admin_id'] ?? 0);
		$bankOptions = backoffice_commission_bank_options();
		$validApproverIds = array_map(static fn($approver): int => (int)($approver->id ?? 0), $commissionBankApprovers);
		$allocations = $postdata['allocations'] ?? [];
		$errors = [];
		if (!in_array($bankName, $bankOptions, true)) {
			$errors[] = 'Select a bank from the available list.';
		}
		if ($assignedAdminId <= 0 || !in_array($assignedAdminId, $validApproverIds, true)) {
			$errors[] = 'Assign an active admin with a cashdrawer to approve this transfer.';
		}
		if (!is_array($allocations) || count($allocations) > 500) {
			$errors[] = 'Select valid commission amounts for this transfer.';
			$allocations = [];
		}
		$normalizedAllocations = [];
		$totalRequested = 0.0;
		foreach ($allocations as $rawUserId => $rawAmount) {
			if (!ctype_digit((string)$rawUserId) || !is_scalar($rawAmount)) {
				$errors[] = 'A selected commission user or amount is invalid.';
				continue;
			}
			$selectedUserId = (int)$rawUserId;
			$amountString = trim((string)$rawAmount);
			if ($selectedUserId <= 0 || $amountString === '' || !is_numeric($amountString) || !is_finite((float)$amountString)) {
				$errors[] = 'Enter a valid amount for each selected user.';
				continue;
			}
			$selectedAmount = round((float)$amountString, 2);
			if ($selectedAmount < 0 || $selectedAmount > 9999999999.99) {
				$errors[] = 'Commission transfer amounts must be positive and within the supported limit.';
				continue;
			}
			if ($selectedAmount > 0) {
				$normalizedAllocations[$selectedUserId] = $selectedAmount;
				$totalRequested += $selectedAmount;
			}
		}
		ksort($normalizedAllocations, SORT_NUMERIC);
		$totalRequested = round($totalRequested, 2);
		if ($totalRequested <= 0) {
			$errors[] = 'Enter an amount for at least one user.';
		} elseif ($totalRequested > 9999999999.99) {
			$errors[] = 'The combined transfer exceeds the maximum supported bank-transfer amount.';
		}

		$proofFile = $_FILES['proof_image'] ?? null;
		$hasProof = is_array($proofFile) && (int)($proofFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
		if (!$hasProof || (int)($proofFile['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
			$errors[] = 'Upload the bank transfer proof image.';
		} elseif ((int)($proofFile['size'] ?? 0) <= 0 || (int)$proofFile['size'] > 1048576) {
			$errors[] = 'Proof image must not exceed 1MB.';
		} elseif (!is_uploaded_file((string)($proofFile['tmp_name'] ?? '')) || @getimagesize((string)$proofFile['tmp_name']) === false) {
			$errors[] = 'Upload a valid image file as proof.';
		} else {
			$imageInfo = @getimagesize((string)$proofFile['tmp_name']);
			$imageMime = is_array($imageInfo) ? strtolower((string)($imageInfo['mime'] ?? '')) : '';
			if (!in_array($imageMime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
				$errors[] = 'Proof must be a JPEG, PNG, or WebP image.';
			} else {
				$canonicalExtension = $imageMime === 'image/jpeg' ? 'jpg' : ($imageMime === 'image/png' ? 'png' : 'webp');
				$_FILES['proof_image']['name'] = 'transfer-proof.' . $canonicalExtension;
				$_FILES['proof_image']['type'] = $imageMime;
			}
		}

		if ($errors) {
			$commissionBankRespond(false, implode(' ', array_unique($errors)));
		}

		$req->upload_folder = 'uploads/backoffice/commission-bank-transfers';
		$req->upload_max_size = 1;
		$proofPath = $req->upload_files('proof_image');
		if (!is_string($proofPath) || $proofPath === '' || !empty($req->upload_errors['proof_image'])) {
			$commissionBankRespond(false, 'The proof image could not be saved. Please try again with a JPEG, PNG, or WebP image under 1MB.');
		}

		if (!$commissionBankDb->beginTransaction() || $commissionBankDb->has_error) {
			if (is_file($proofPath)) {
				unlink($proofPath);
			}
			$commissionBankRespond(false, 'Unable to start the commission transfer. Please try again.');
		}

		try {
			$lockedAllocations = [];
			foreach ($normalizedAllocations as $selectedUserId => $requestedAmount) {
				$userRows = $commissionBankDb->query(
					'SELECT id FROM users WHERE id = :user_id LIMIT 1 FOR UPDATE',
					['user_id' => $selectedUserId]
				) ?: [];
				if ($commissionBankDb->has_error || !$userRows) {
					throw new \RuntimeException('A selected commission user could not be found.');
				}
				$eligibleCommissions = $commissionBankDb->query(
					"SELECT m.id, m.amount, u.branch_id
					 FROM commissions m
					 LEFT JOIN users u ON u.id = m.created_by
					 WHERE m.created_by = :user_id
					   AND LOWER(TRIM(COALESCE(m.transaction_status,''))) = 'approved'
					   AND (m.deleted IS NULL OR m.deleted = 0)
					   AND (m.disabled IS NULL OR m.disabled = 0)
					   AND (m.bulk_parent_id IS NULL OR m.bulk_parent_id = 0)
					 ORDER BY COALESCE(NULLIF(m.date_received, '0000-00-00 00:00:00'), m.date_created), m.id
					 FOR UPDATE",
					['user_id' => $selectedUserId]
				) ?: [];
				if ($commissionBankDb->has_error || !$eligibleCommissions) {
					throw new \RuntimeException('One or more selected users no longer have approved commission available.');
				}

				$userRemaining = 0.0;
				$commissionRemaining = [];
				foreach ($eligibleCommissions as $eligibleCommission) {
					$commissionId = (int)($eligibleCommission->id ?? 0);
					$allocatedRows = $commissionBankDb->query(
						"SELECT COALESCE(SUM(i.amount),0) AS reserved
						 FROM backoffice_commission_bank_transfer_items i
						 JOIN backoffice_commission_bank_transfers t ON t.id = i.transfer_id
						 WHERE i.commission_id = :commission_id
						   AND t.deleted = 0
						   AND LOWER(TRIM(t.status)) IN ('pending','approved')",
						['commission_id' => $commissionId]
					) ?: [];
					if ($commissionBankDb->has_error) {
						throw new \RuntimeException('Unable to validate previously submitted commission transfers.');
					}
					$remaining = round(max((float)($eligibleCommission->amount ?? 0) - (float)($allocatedRows[0]->reserved ?? 0), 0), 2);
					if ($remaining > 0) {
						$commissionRemaining[] = [
							'id' => $commissionId,
							'branch_id' => (int)($eligibleCommission->branch_id ?? 0) ?: null,
							'remaining' => $remaining,
						];
						$userRemaining += $remaining;
					}
				}

				if ($requestedAmount - $userRemaining > 0.009) {
					throw new \RuntimeException('A selected amount exceeds the user’s currently available approved commission. Refresh and try again.');
				}
				$toAllocate = $requestedAmount;
				foreach ($commissionRemaining as $commissionLine) {
					if ($toAllocate <= 0.009) {
						break;
					}
					$lineAmount = round(min($toAllocate, $commissionLine['remaining']), 2);
					if ($lineAmount > 0) {
						$lockedAllocations[] = [
							'commission_id' => $commissionLine['id'],
							'user_id' => $selectedUserId,
							'branch_id' => $commissionLine['branch_id'],
							'amount' => $lineAmount,
						];
						$toAllocate = round($toAllocate - $lineAmount, 2);
					}
				}
				if ($toAllocate > 0.009) {
					throw new \RuntimeException('The selected amount could not be allocated to approved commission records.');
				}
			}

			$commissionBankDb->query(
				"INSERT INTO backoffice_commission_bank_transfers
				 (bank_name, amount, proof_image, status, created_by, assigned_admin_id, updated_by, date_created, date_updated)
				 VALUES (:bank_name, :amount, :proof_image, 'Pending', :created_by, :assigned_admin_id, :updated_by, :date_created, :date_updated)",
				[
					'bank_name' => $bankName,
					'amount' => $totalRequested,
					'proof_image' => $proofPath,
					'created_by' => $commissionBankUserId,
					'assigned_admin_id' => $assignedAdminId,
					'updated_by' => $commissionBankUserId,
					'date_created' => $now,
					'date_updated' => $now,
				]
			);
			$newTransferId = (int)$commissionBankDb->insert_id;
			if ($commissionBankDb->has_error || $newTransferId <= 0) {
				throw new \RuntimeException('Unable to save the commission transfer request.');
			}
			foreach ($lockedAllocations as $allocation) {
				$commissionBankDb->query(
					"INSERT INTO backoffice_commission_bank_transfer_items
					 (transfer_id, commission_id, user_id, branch_id, amount)
					 VALUES (:transfer_id, :commission_id, :user_id, :branch_id, :amount)",
					[
						'transfer_id' => $newTransferId,
						'commission_id' => $allocation['commission_id'],
						'user_id' => $allocation['user_id'],
						'branch_id' => $allocation['branch_id'],
						'amount' => $allocation['amount'],
					]
				);
				if ($commissionBankDb->has_error) {
					throw new \RuntimeException('Unable to save a commission transfer detail.');
				}
			}
			if (!$commissionBankDb->commit() || $commissionBankDb->has_error) {
				throw new \RuntimeException('Unable to complete the commission transfer request.');
			}
		} catch (\Throwable $saveError) {
			$commissionBankDb->rollBack();
			if (is_file($proofPath)) {
				unlink($proofPath);
			}
			error_log('[commission bank transfer submit] ' . $saveError->getMessage());
			$commissionBankRespond(false, $saveError->getMessage());
		}

		$assignedAdminName = 'Admin #' . $assignedAdminId;
		foreach ($commissionBankApprovers as $approver) {
			if ((int)($approver->id ?? 0) === $assignedAdminId) {
				$assignedAdminName = trim((string)($approver->first_name ?? '') . ' ' . (string)($approver->last_name ?? '')) ?: $assignedAdminName;
				break;
			}
		}
		$transactionUrl = rtrim(ROOT, '/') . '/' . $commissionBankRoute . '?transfer=' . $newTransferId;
		$whatsappMessage = "👋 Hello *{$assignedAdminName}*!\n\n"
			. "🔔 A commission bank transfer is waiting for your review.\n\n"
			. "🏦 *Bank:* {$bankName}\n"
			. "💰 *Amount:* GH₵ " . number_format($totalRequested, 2) . "\n"
			. "🧾 *Request:* #{$newTransferId}\n\n"
			. "🔎 *Review this transaction:*\n{$transactionUrl}\n\n"
			. "Please review and approve or reject the request when you can. Thank you! 🙏";
		$whatsappUrl = '';
		$whatsappUnavailableReason = 'No WhatsApp number is configured for the assigned admin.';
		require_once dirname(__DIR__) . '/helpers/whatsapp-notifications.php';
		$assignedAdminPhone = backoffice_whatsapp_phone($assignedAdminId);
		if (!empty($assignedAdminPhone['phone'])) {
			$whatsappUrl = 'https://wa.me/' . rawurlencode((string)$assignedAdminPhone['phone'])
				. '?text=' . rawurlencode($whatsappMessage);
			$whatsappUnavailableReason = '';
		} elseif (!empty($assignedAdminPhone['reason'])) {
			$whatsappUnavailableReason = (string)$assignedAdminPhone['reason'];
		}
		$commissionBankRespond(true, 'Commission transfer submitted for admin approval.', [
			'transfer_id' => $newTransferId,
			'whatsapp_prompt' => [
				'admin_name' => $assignedAdminName,
				'message' => $whatsappMessage,
				'whatsapp_url' => $whatsappUrl,
				'transaction_url' => $transactionUrl,
				'unavailable_reason' => $whatsappUnavailableReason,
			],
		]);
	}

	if (in_array($action, ['approve', 'reject'], true)) {
		if (!$commissionBankIsAdmin) {
			$commissionBankRespond(false, 'Only an admin can approve or reject commission bank transfers.');
		}
		if ($transferId <= 0) {
			$commissionBankRespond(false, 'Select a valid commission transfer.');
		}
		$rejectReason = trim((string)($postdata['reject_reason'] ?? ''));
		if ($action === 'reject' && ($rejectReason === '' || strlen($rejectReason) > 500)) {
			$commissionBankRespond(false, 'Enter a rejection reason of up to 500 characters.');
		}
		if (!$commissionBankDb->beginTransaction() || $commissionBankDb->has_error) {
			$commissionBankRespond(false, 'Unable to start the review. Please try again.');
		}
		try {
			$transferRows = $commissionBankDb->query(
				"SELECT id, status, bank_name, amount, created_by, assigned_admin_id FROM backoffice_commission_bank_transfers WHERE id = :id AND deleted = 0 LIMIT 1 FOR UPDATE",
				['id' => $transferId]
			) ?: [];
			$transfer = $transferRows[0] ?? null;
			if (!$transfer || strtolower(trim((string)$transfer->status)) !== 'pending') {
				throw new \RuntimeException('This transfer is no longer awaiting admin review.');
			}
			$assignedAdminId = (int)($transfer->assigned_admin_id ?? 0);
			if ($assignedAdminId > 0 && $assignedAdminId !== $commissionBankUserId) {
				throw new \RuntimeException('This transfer is assigned to another admin for review.');
			}
			if ($action === 'approve') {
				$transferAllocationRows = $commissionBankDb->query(
					"SELECT i.commission_id, i.amount, m.amount AS commission_amount,
					        m.transaction_status, m.deleted, m.disabled,
					        COALESCE((
					        	SELECT SUM(i2.amount)
					        	FROM backoffice_commission_bank_transfer_items i2
					        	JOIN backoffice_commission_bank_transfers t2 ON t2.id = i2.transfer_id
					        	WHERE i2.commission_id = i.commission_id
					        	  AND t2.deleted = 0
					        	  AND LOWER(TRIM(t2.status)) IN ('pending','approved')
					        ),0) AS reserved_amount
					 FROM backoffice_commission_bank_transfer_items i
					 JOIN commissions m ON m.id = i.commission_id
					 WHERE i.transfer_id = :transfer_id
					 FOR UPDATE",
					['transfer_id' => $transferId]
				) ?: [];
				if ($commissionBankDb->has_error || !$transferAllocationRows) {
					throw new \RuntimeException('The transfer has no valid commission allocation details.');
				}
				$verifiedAllocationTotal = 0.0;
				foreach ($transferAllocationRows as $allocationRow) {
					if (
						strtolower(trim((string)($allocationRow->transaction_status ?? ''))) !== 'approved'
						|| (int)($allocationRow->deleted ?? 0) !== 0
						|| (int)($allocationRow->disabled ?? 0) !== 0
						|| (float)($allocationRow->reserved_amount ?? 0) - (float)($allocationRow->commission_amount ?? 0) > 0.009
					) {
						throw new \RuntimeException('An allocated commission is no longer eligible. Reject this request and submit a refreshed transfer.');
					}
					$verifiedAllocationTotal += (float)($allocationRow->amount ?? 0);
				}
				$transferAmountRows = $commissionBankDb->query(
					'SELECT amount FROM backoffice_commission_bank_transfers WHERE id = :id LIMIT 1',
					['id' => $transferId]
				) ?: [];
				$expectedTransferAmount = (float)($transferAmountRows[0]->amount ?? 0);
				if (abs($verifiedAllocationTotal - $expectedTransferAmount) > 0.009) {
					throw new \RuntimeException('The transfer details do not match the submitted total.');
				}
				require_once dirname(__DIR__) . '/helpers/cashdrawer-bridge.php';
				$sharedAccountOwnerId = $assignedAdminId > 0 ? $assignedAdminId : (int)$transfer->created_by;
				$drawerContext = \BackOfficeCashdrawer\getActiveDrawer($sharedAccountOwnerId);
				if (empty($drawerContext)) {
					throw new \RuntimeException('The assigned shared-account admin has no cashdrawer available to receive the bank credit.');
				}
				$drawerId = (int)($drawerContext['drawer_id'] ?? 0);
				$drawerUserId = (int)($drawerContext['drawer_user_id'] ?? $transfer->created_by);
				$bankChannel = \BackOfficeCashdrawer\resolveExistingEcashChannelKey($drawerId, $drawerUserId, (string)$transfer->bank_name);
				if ($drawerId <= 0 || $drawerUserId <= 0 || trim($bankChannel) === '') {
					throw new \RuntimeException('Unable to identify the receiving bank channel in the assigned admin’s shared-account cashdrawer.');
				}
				$bankBalanceRows = $commissionBankDb->query(
					"SELECT * FROM drawer_balances
					 WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash'
					   AND (channel_key = :channel_key OR channel_key = :ecash_channel_key)
					 LIMIT 1 FOR UPDATE",
					[
						'drawer_id' => $drawerId,
						'user_id' => $drawerUserId,
						'channel_key' => $bankChannel,
						'ecash_channel_key' => 'ECASH_' . $bankChannel,
					]
				) ?: [];
				if ($commissionBankDb->has_error) {
					throw new \RuntimeException('Unable to lock the receiving bank balance in the cashdrawer.');
				}
				$bankBalance = $bankBalanceRows[0] ?? null;
				if ($bankBalance) {
					$updatedCredit = round((float)($bankBalance->credit ?? 0) + $expectedTransferAmount, 2);
					$updatedClosing = round((float)($bankBalance->opening_balance ?? 0) + $updatedCredit - (float)($bankBalance->debit ?? 0), 2);
					$commissionBankDb->query(
						"UPDATE drawer_balances
						 SET credit = :credit, closing_balance = :closing_balance, trans_id = :transfer_id,
						     trans_status = 'Approved commission bank transfer'
						 WHERE id = :id",
						[
							'credit' => $updatedCredit,
							'closing_balance' => $updatedClosing,
							'transfer_id' => $transferId,
							'id' => (int)$bankBalance->id,
						]
					);
				} else {
					$commissionBankDb->query(
						"INSERT INTO drawer_balances
						 (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status)
						 VALUES (:drawer_id, :user_id, 'ecash', :channel_key, 0, :credit, 0, :closing_balance, :date_created, :transfer_id, 'Approved commission bank transfer')",
						[
							'drawer_id' => $drawerId,
							'user_id' => $drawerUserId,
							'channel_key' => $bankChannel,
							'credit' => $expectedTransferAmount,
							'closing_balance' => $expectedTransferAmount,
							'date_created' => $now,
							'transfer_id' => $transferId,
						]
					);
				}
				if ($commissionBankDb->has_error) {
					throw new \RuntimeException('Unable to credit the selected bank in the assigned admin’s shared-account cashdrawer.');
				}
				$commissionBankDb->query(
					"UPDATE backoffice_commission_bank_transfers
					 SET status = 'Approved', approved_by = :approved_by, updated_by = :updated_by,
					     cashdrawer_credited = 1, approved_at = :approved_at, date_updated = :date_updated
					 WHERE id = :id",
					[
						'approved_by' => $commissionBankUserId,
						'updated_by' => $commissionBankUserId,
						'approved_at' => $now,
						'date_updated' => $now,
						'id' => $transferId,
					]
				);
			} else {
				$commissionBankDb->query(
					"UPDATE backoffice_commission_bank_transfers
					 SET status = 'Rejected', reject_reason = :reject_reason, approved_by = :reviewed_by,
					     updated_by = :updated_by, approved_at = :reviewed_at, date_updated = :date_updated
					 WHERE id = :id",
					[
						'reject_reason' => $rejectReason,
						'reviewed_by' => $commissionBankUserId,
						'updated_by' => $commissionBankUserId,
						'reviewed_at' => $now,
						'date_updated' => $now,
						'id' => $transferId,
					]
				);
			}
			if ($commissionBankDb->has_error || !$commissionBankDb->commit() || $commissionBankDb->has_error) {
				throw new \RuntimeException('Unable to save the admin review decision.');
			}
		} catch (\Throwable $reviewError) {
			$commissionBankDb->rollBack();
			error_log('[commission bank transfer review] transfer_id=' . $transferId . ' ' . $reviewError->getMessage());
			$commissionBankRespond(false, $reviewError->getMessage());
		}
		$commissionBankRespond(
			true,
			$action === 'approve' ? 'Commission transfer approved and credited to the selected shared bank account.' : 'Commission transfer rejected; its commission amounts are available again.',
			['transfer_id' => $transferId]
		);
	}

	$commissionBankRespond(false, 'Choose a valid commission transfer action.');
}

try {
	$commissionBankRows = $commissionBankIsPdf ? [] : ($commissionBankDb->query(
		"SELECT m.id AS commission_id, m.created_by AS user_id, m.amount, m.network, m.commission_type, m.description,
		        m.date_received, m.date_created, u.first_name, u.last_name, u.branch_id, b.name AS branch_name,
		        GREATEST(COALESCE(m.amount,0) - COALESCE(reserved.amount,0),0) AS available_amount
		 FROM commissions m
		 LEFT JOIN users u ON u.id = m.created_by
		 LEFT JOIN branches b ON b.id = u.branch_id
		 LEFT JOIN (
			SELECT i.commission_id, SUM(i.amount) AS amount
			FROM backoffice_commission_bank_transfer_items i
			JOIN backoffice_commission_bank_transfers t ON t.id = i.transfer_id
			WHERE t.deleted = 0 AND LOWER(TRIM(t.status)) IN ('pending','approved')
			GROUP BY i.commission_id
		 ) reserved ON reserved.commission_id = m.id
		 WHERE LOWER(TRIM(COALESCE(m.transaction_status,''))) = 'approved'
		   AND (m.deleted IS NULL OR m.deleted = 0)
		   AND (m.disabled IS NULL OR m.disabled = 0)
		   AND (m.bulk_parent_id IS NULL OR m.bulk_parent_id = 0)
		 HAVING available_amount > 0.009
		 ORDER BY COALESCE(NULLIF(TRIM(b.name),''),'Unassigned branch'), u.first_name, u.last_name, m.date_received, m.id"
	) ?: []);
	if ($commissionBankDb->has_error) {
		throw new \RuntimeException((string)$commissionBankDb->error);
	}

	$commissionBankBranches = [];
	$commissionBankTotalAvailable = 0.0;
	foreach ($commissionBankRows as $commissionBankRow) {
		$branchId = (int)($commissionBankRow->branch_id ?? 0);
		$branchKey = $branchId > 0 ? (string)$branchId : 'unassigned';
		$userId = (int)($commissionBankRow->user_id ?? 0);
		$userKey = (string)$userId;
		$branchName = trim((string)($commissionBankRow->branch_name ?? '')) ?: 'Unassigned branch';
		$userName = trim((string)($commissionBankRow->first_name ?? '') . ' ' . (string)($commissionBankRow->last_name ?? ''));
		if ($userName === '') {
			$userName = 'User #' . $userId;
		}
		if (!isset($commissionBankBranches[$branchKey])) {
			$commissionBankBranches[$branchKey] = [
				'id' => $branchId,
				'name' => $branchName,
				'total_available' => 0.0,
				'users' => [],
			];
		}
		if (!isset($commissionBankBranches[$branchKey]['users'][$userKey])) {
			$commissionBankBranches[$branchKey]['users'][$userKey] = [
				'id' => $userId,
				'name' => $userName,
				'available' => 0.0,
				'commission_count' => 0,
				'categories' => [],
			];
		}
		$available = round((float)($commissionBankRow->available_amount ?? 0), 2);
		$commissionBankBranches[$branchKey]['users'][$userKey]['available'] += $available;
		$commissionBankBranches[$branchKey]['users'][$userKey]['commission_count']++;
		$categoryName = trim((string)($commissionBankRow->commission_type ?? '')) ?: 'Other commission';
		$categoryKey = strtolower($categoryName);
		if (!isset($commissionBankBranches[$branchKey]['users'][$userKey]['categories'][$categoryKey])) {
			$commissionBankBranches[$branchKey]['users'][$userKey]['categories'][$categoryKey] = [
				'name' => $categoryName,
				'available' => 0.0,
				'record_count' => 0,
				'networks' => [],
			];
		}
		$commissionBankBranches[$branchKey]['users'][$userKey]['categories'][$categoryKey]['available'] += $available;
		$commissionBankBranches[$branchKey]['users'][$userKey]['categories'][$categoryKey]['record_count']++;
		$networkName = trim((string)($commissionBankRow->network ?? ''));
		if ($networkName !== '') {
			$commissionBankBranches[$branchKey]['users'][$userKey]['categories'][$categoryKey]['networks'][$networkName] = $networkName;
		}
		$commissionBankBranches[$branchKey]['total_available'] += $available;
		$commissionBankTotalAvailable += $available;
	}
	foreach ($commissionBankBranches as &$commissionBankBranch) {
		foreach ($commissionBankBranch['users'] as &$commissionBankUser) {
			foreach ($commissionBankUser['categories'] as &$commissionBankCategory) {
				$commissionBankCategory['available'] = round((float)$commissionBankCategory['available'], 2);
				$commissionBankCategory['networks'] = array_values($commissionBankCategory['networks']);
			}
			unset($commissionBankCategory);
			$commissionBankUser['categories'] = array_values($commissionBankUser['categories']);
		}
		unset($commissionBankUser);
		$commissionBankBranch['users'] = array_values($commissionBankBranch['users']);
	}
	unset($commissionBankBranch);
	$commissionBankBranches = array_values($commissionBankBranches);

	$commissionBankSearch = trim((string)($_GET['search'] ?? ''));
	$commissionBankStartDate = trim((string)($_GET['start_date'] ?? ''));
	$commissionBankEndDate = trim((string)($_GET['end_date'] ?? ''));
	$commissionBankDatePattern = '/^\d{4}-\d{2}-\d{2}$/';
	foreach (['commissionBankStartDate', 'commissionBankEndDate'] as $dateVariable) {
		if ($$dateVariable !== '' && (!preg_match($commissionBankDatePattern, $$dateVariable) || \DateTimeImmutable::createFromFormat('!Y-m-d', $$dateVariable) === false)) {
			$$dateVariable = '';
		}
	}
	$commissionBankTransferWhere = ['t.deleted = 0'];
	$commissionBankTransferParams = [];
	if ($commissionBankSearch !== '') {
		$commissionBankTransferWhere[] = "(t.bank_name LIKE :search_bank OR t.status LIKE :search_status OR CONCAT_WS(' ', creator.first_name, creator.last_name) LIKE :search_creator OR CONCAT_WS(' ', assigned_admin.first_name, assigned_admin.last_name) LIKE :search_assigned_admin OR CAST(t.id AS CHAR) = :search_id)";
		$commissionBankTransferParams['search_bank'] = '%' . $commissionBankSearch . '%';
		$commissionBankTransferParams['search_status'] = '%' . $commissionBankSearch . '%';
		$commissionBankTransferParams['search_creator'] = '%' . $commissionBankSearch . '%';
		$commissionBankTransferParams['search_assigned_admin'] = '%' . $commissionBankSearch . '%';
		$commissionBankTransferParams['search_id'] = $commissionBankSearch;
	}
	if ($commissionBankStartDate !== '') {
		$commissionBankTransferWhere[] = 't.date_created >= :filter_start_date';
		$commissionBankTransferParams['filter_start_date'] = $commissionBankStartDate . ' 00:00:00';
	}
	if ($commissionBankEndDate !== '') {
		$commissionBankTransferWhere[] = 't.date_created < :filter_end_date';
		$commissionBankTransferParams['filter_end_date'] = (new \DateTimeImmutable($commissionBankEndDate))->modify('+1 day')->format('Y-m-d 00:00:00');
	}
	$commissionBankTransfers = $commissionBankDb->query(
		"SELECT t.*, CONCAT_WS(' ', creator.first_name, creator.last_name) AS creator_name,
		        CONCAT_WS(' ', assigned_admin.first_name, assigned_admin.last_name) AS assigned_admin_name,
		        CONCAT_WS(' ', reviewer.first_name, reviewer.last_name) AS reviewer_name,
		        CONCAT_WS(' ', updater.first_name, updater.last_name) AS updated_by_name
		 FROM backoffice_commission_bank_transfers t
		 LEFT JOIN users creator ON creator.id = t.created_by
		 LEFT JOIN users assigned_admin ON assigned_admin.id = t.assigned_admin_id
		 LEFT JOIN users reviewer ON reviewer.id = t.approved_by
		 LEFT JOIN users updater ON updater.id = t.updated_by
		 WHERE " . implode(' AND ', $commissionBankTransferWhere) . "
		 ORDER BY CASE WHEN LOWER(TRIM(t.status)) = 'pending' THEN 0 ELSE 1 END, t.date_created DESC, t.id DESC
		 LIMIT 500",
		$commissionBankTransferParams
	) ?: [];
	if ($commissionBankDb->has_error) {
		throw new \RuntimeException((string)$commissionBankDb->error);
	}
	$commissionBankLedgerSummaryRows = $commissionBankDb->query(
		"SELECT
			SUM(CASE WHEN LOWER(TRIM(status)) = 'pending' THEN 1 ELSE 0 END) AS pending_count,
			COALESCE(SUM(CASE WHEN LOWER(TRIM(status)) = 'approved' THEN amount ELSE 0 END),0) AS approved_total
		 FROM backoffice_commission_bank_transfers
		 WHERE deleted = 0"
	) ?: [];
	if ($commissionBankDb->has_error) {
		throw new \RuntimeException((string)$commissionBankDb->error);
	}
	$commissionBankLedgerSummary = $commissionBankLedgerSummaryRows[0] ?? (object)['pending_count' => 0, 'approved_total' => 0];
	$commissionBankTransferIds = array_values(array_filter(array_map(
		static fn($transfer): int => (int)($transfer->id ?? 0),
		$commissionBankTransfers
	)));
	$commissionBankTransferDetails = [];
	if ($commissionBankTransferIds) {
		$idList = implode(',', $commissionBankTransferIds);
		$commissionBankItems = $commissionBankDb->query(
			"SELECT i.transfer_id, i.user_id, i.branch_id, SUM(i.amount) AS amount,
			        CONCAT_WS(' ', u.first_name, u.last_name) AS user_name,
			        COALESCE(NULLIF(b.name,''),'Unassigned branch') AS branch_name
			 FROM backoffice_commission_bank_transfer_items i
			 LEFT JOIN users u ON u.id = i.user_id
			 LEFT JOIN branches b ON b.id = i.branch_id
			 WHERE i.transfer_id IN ({$idList})
			 GROUP BY i.transfer_id, i.user_id, i.branch_id, u.first_name, u.last_name, b.name
			 ORDER BY branch_name, user_name"
		) ?: [];
		if ($commissionBankDb->has_error) {
			throw new \RuntimeException((string)$commissionBankDb->error);
		}
		foreach ($commissionBankItems as $commissionBankItem) {
			$commissionBankTransferDetails[(int)$commissionBankItem->transfer_id][] = $commissionBankItem;
		}
	}

	if ($commissionBankIsPdf) {
		$autoloadCandidates = [
			dirname(__DIR__, 3) . '/assets/vendor/autoload.php',
			dirname(__DIR__, 3) . '/vendor/autoload.php',
		];
		foreach ($autoloadCandidates as $autoload) {
			if (is_file($autoload)) {
				require_once $autoload;
				if (class_exists('Mpdf\\Mpdf') || class_exists('Dompdf\\Dompdf')) {
					break;
				}
			}
		}
		$escapePdf = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
		$pdfRows = '';
		$pdfStatusSummary = [
			'approved' => ['count' => 0, 'amount' => 0.0, 'background' => '#dcfce7', 'text' => '#166534', 'label' => 'Approved'],
			'rejected' => ['count' => 0, 'amount' => 0.0, 'background' => '#fee2e2', 'text' => '#991b1b', 'label' => 'Rejected'],
			'pending' => ['count' => 0, 'amount' => 0.0, 'background' => '#fef3c7', 'text' => '#92400e', 'label' => 'Pending'],
		];
		foreach ($commissionBankTransfers as $transfer) {
			$transferId = (int)$transfer->id;
			$status = strtolower(trim((string)($transfer->status ?? 'pending')));
			$statusLabel = ucfirst($status !== '' ? $status : 'pending');
			if (isset($pdfStatusSummary[$status])) {
				$pdfStatusSummary[$status]['count']++;
				$pdfStatusSummary[$status]['amount'] += (float)($transfer->amount ?? 0);
			}
			$statusColors = [
				'approved' => ['background' => '#dcfce7', 'text' => '#166534'],
				'rejected' => ['background' => '#fee2e2', 'text' => '#991b1b'],
				'pending' => ['background' => '#fef3c7', 'text' => '#92400e'],
			];
			$statusColor = $statusColors[$status] ?? ['background' => '#e2e8f0', 'text' => '#334155'];
			$statusBadge = '<span style="display:inline-block;padding:3px 8px;border-radius:10px;background-color:' . $statusColor['background']
				. ';color:' . $statusColor['text'] . ';font-weight:bold">' . $escapePdf($statusLabel) . '</span>';
			$allocationsHtml = '';
			foreach ($commissionBankTransferDetails[$transferId] ?? [] as $item) {
				$allocationsHtml .= '<tr><td style="padding-left:18px;color:#475569">' . $escapePdf($item->user_name ?: 'User #' . $item->user_id)
					. ' · ' . $escapePdf($item->branch_name) . '</td><td align="right">' . $escapePdf('GH₵ ' . number_format((float)$item->amount, 2)) . '</td></tr>';
			}
			$pdfRows .= '<tr><td><strong>' . $escapePdf($transfer->bank_name) . ' · #' . $transferId . '</strong><br>'
				. '<strong>Status:</strong> ' . $statusBadge . ' · ' . $escapePdf($transfer->date_created)
				. '<br>Created by ' . $escapePdf($transfer->creator_name ?: 'Unknown')
				. ' · Assigned admin ' . $escapePdf($transfer->assigned_admin_name ?: 'Unassigned')
				. ' · Updated by ' . $escapePdf($transfer->updated_by_name ?: '—')
				. '<br>Updated ' . $escapePdf($transfer->date_updated ?: '—') . '</td><td align="right"><strong>'
				. $escapePdf('GH₵ ' . number_format((float)$transfer->amount, 2)) . '</strong></td></tr>' . $allocationsHtml;
		}
		$filterLabel = 'All transfer requests';
		if ($commissionBankStartDate !== '' || $commissionBankEndDate !== '') {
			$filterLabel = 'Created ' . ($commissionBankStartDate !== '' ? $commissionBankStartDate : 'any time')
				. ' to ' . ($commissionBankEndDate !== '' ? $commissionBankEndDate : 'today');
		}
		if ($commissionBankSearch !== '') {
			$filterLabel .= ' · Search: ' . $commissionBankSearch;
		}
		$pdfSummaryCards = '';
		foreach ($pdfStatusSummary as $summary) {
			$pdfSummaryCards .= '<td style="width:33.33%;background-color:' . $summary['background'] . ';color:' . $summary['text']
				. ';padding:10px;border:1px solid #cbd5e1"><strong>' . $escapePdf($summary['label']) . '</strong><br>'
				. '<span style="font-size:14pt;font-weight:bold">GH₵ ' . number_format($summary['amount'], 2) . '</span><br>'
				. $summary['count'] . ' transfer' . ($summary['count'] === 1 ? '' : 's') . '</td>';
		}
		$pdfHtml = '<html><head><meta charset="UTF-8"><style>body{font-family:sans-serif;color:#1e293b;font-size:9pt}h1{font-size:18pt}table{width:100%;border-collapse:collapse}th{background:#e2e8f0;color:#0f172a;text-align:left}td,th{padding:7px;border:1px solid #cbd5e1}td{vertical-align:top}</style></head><body>'
			. '<h1>Commission bank transfer ledger</h1><p>' . $escapePdf($filterLabel) . ' · Generated ' . $escapePdf(date('Y-m-d H:i:s')) . '</p>'
			. '<h2 style="font-size:12pt;margin-bottom:6px">Status summary</h2><table style="margin-bottom:16px"><tbody><tr>' . $pdfSummaryCards . '</tr></tbody></table>'
			. '<table><thead><tr><th>Transfer, audit and allocation details</th><th align="right">Amount (GHS)</th></tr></thead><tbody>'
			. ($pdfRows !== '' ? $pdfRows : '<tr><td colspan="2">No transfers match these filters.</td></tr>')
			. '</tbody></table></body></html>';
		$pdfBytes = null;
		$rendererErrors = [];
		if (class_exists('Mpdf\\Mpdf')) {
			try {
				$pdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'margin_left' => 12, 'margin_right' => 12]);
				$pdf->SetTitle('Commission bank transfer ledger');
				$pdf->WriteHTML($pdfHtml);
				$pdfBytes = $pdf->Output('', 'S');
			} catch (\Throwable $rendererError) {
				$rendererErrors[] = 'mPDF: ' . $rendererError->getMessage();
			}
		}
		if ((!is_string($pdfBytes) || substr($pdfBytes, 0, 4) !== '%PDF') && class_exists('Dompdf\\Dompdf')) {
			try {
				$options = new \Dompdf\Options();
				$options->set('isRemoteEnabled', false);
				$options->set('tempDir', sys_get_temp_dir());
				$dompdf = new \Dompdf\Dompdf($options);
				$dompdf->loadHtml($pdfHtml, 'UTF-8');
				$dompdf->setPaper('A4', 'landscape');
				$dompdf->render();
				$pdfBytes = $dompdf->output();
			} catch (\Throwable $rendererError) {
				$rendererErrors[] = 'Dompdf: ' . $rendererError->getMessage();
			}
		}
		if (!is_string($pdfBytes) || substr($pdfBytes, 0, 4) !== '%PDF') {
			if (!$rendererErrors) {
				$rendererErrors[] = class_exists('Mpdf\\Mpdf') || class_exists('Dompdf\\Dompdf')
					? 'Available PDF renderers returned an invalid document.'
					: 'No supported PDF renderer is installed.';
			}
			$rendererDetail = implode(' | ', $rendererErrors);
			throw new \RuntimeException('Unable to render commission transfer PDF. ' . $rendererDetail);
		}
		while (ob_get_level()) {
			ob_end_clean();
		}
		header('Content-Type: application/pdf');
		header('Content-Disposition: attachment; filename="commission-bank-transfers-' . date('Y-m-d_H-i-s') . '.pdf"');
		header('Content-Length: ' . strlen($pdfBytes));
		exit($pdfBytes);
	}

	$commissionBankSelectedTransferId = max(0, (int)($_GET['transfer'] ?? 0));
	require plugin_path('views/admin/commissions/commission_bank_transfers.php');
} catch (\Throwable $loadError) {
	error_log(($commissionBankIsPdf ? '[commission bank transfer pdf] ' : '[commission bank transfer page] ') . $loadError->getMessage());
	if ($commissionBankIsPdf) {
		http_response_code(500);
		header('Content-Type: text/plain; charset=UTF-8');
		exit('Commission bank transfer PDF could not be generated. Please try again or contact an administrator.');
	}
	message_fail('Commission bank transfers could not be loaded. Please try again or contact an administrator.');
	redirect($admin_route . '/' . $plugin_route . '/list#commissions');
	exit;
}

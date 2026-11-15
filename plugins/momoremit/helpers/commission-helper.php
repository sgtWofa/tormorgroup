<?php

/**
 * Momoremit Commission Helpers
 * 
 * Handles commission calculation and transfer for withdrawal/withdrawal cit transactions.
 * Commissions are first stored in cashout_commissions (PENDING), then transferred
 * to backoffice commissions table when user decides to do so.
 */

defined('ROOT') or die("Direct script access denied");

if (!function_exists('momoremit_cashout_log_event')) {
	function momoremit_cashout_log_event(string $action, int $actorId, $commission, string $description): void
	{
		try {
			$actorName = 'User #' . $actorId;
			$db = new \Core\Database;
			$userRows = $db->query('SELECT first_name, last_name FROM users WHERE id = :id LIMIT 1', ['id' => $actorId]) ?: [];
			if (!empty($userRows)) {
				$name = trim((string)($userRows[0]->first_name ?? '') . ' ' . (string)($userRows[0]->last_name ?? ''));
				if ($name !== '') {
					$actorName = $name;
				}
			}
			$log = new \Momoremits\Log;
			$log->insert([
				'table_name' => 'cashout_commissions',
				'action' => $action,
				'user_id' => $actorId,
				'user_name' => $actorName,
				'description' => $description,
				'date_created' => date('Y-m-d H:i:s'),
			]);
		} catch (\Throwable $e) {
			error_log('MOMOREMIT CASHOUT COMMISSION LOG FAILED: ' . $e->getMessage());
		}
	}
}

if (!function_exists('momoremit_notify_cashout_transfer_whatsapp')) {
	function momoremit_notify_cashout_transfer_whatsapp(array $adminIds, array $transferEvents): array
	{
		$summary = ['sent' => 0, 'ready' => 0, 'failed' => 0, 'skipped' => 0, 'reasons' => [], 'links' => []];
		$adminIds = array_values(array_unique(array_filter(array_map('intval', $adminIds))));
		if (empty($adminIds) || empty($transferEvents)) {
			return $summary;
		}
		if (!function_exists('backoffice_whatsapp_phone')) {
			$summary['failed'] = count($adminIds);
			$summary['reasons'][] = 'WhatsApp integration is unavailable.';
			return $summary;
		}

		try {
			$db = new \Core\Database;
			$today = (new \DateTimeImmutable('now', new \DateTimeZone('Africa/Accra')))->format('Y-m-d');
			$placeholders = [];
			$params = ['today' => $today];
			foreach ($adminIds as $index => $adminId) {
				$key = 'admin_' . $index;
				$placeholders[] = ':' . $key;
				$params[$key] = $adminId;
			}
			$admins = $db->query(
				"SELECT DISTINCT u.id, u.first_name, u.last_name
				 FROM users u
				 INNER JOIN user_roles_map urm ON urm.user_id = u.id AND (urm.disabled = 0 OR urm.disabled IS NULL)
				 INNER JOIN user_roles ur ON ur.id = urm.role_id AND (ur.disabled = 0 OR ur.disabled IS NULL)
				 INNER JOIN cashdrawers cd ON cd.user_id = u.id AND DATE(cd.date_created) = :today
				 WHERE u.deleted = 0 AND LOWER(TRIM(ur.role)) = 'admin' AND u.id IN (" . implode(',', $placeholders) . ")",
				$params
			) ?: [];
			$icon = static function (string $unicode): string {
				$unicode = strtoupper(trim($unicode));
				$json = strlen($unicode) <= 4
					? '"\\u' . str_pad($unicode, 4, '0', STR_PAD_LEFT) . '"'
					: '"\\u' . substr($unicode, 0, 4) . '\\u' . substr($unicode, 4, 4) . '"';
				return (string)json_decode($json);
			};
			$eventStatus = trim((string)($transferEvents[0]['status'] ?? 'Submitted for approval'));
			$statusLower = strtolower($eventStatus);
			$statusIcon = $icon('23F3');
			if (strpos($statusLower, 'approved') !== false) {
				$statusIcon = $icon('2705');
			} elseif (strpos($statusLower, 'rejected') !== false) {
				$statusIcon = $icon('274C');
			}
			$lines = [
				$icon('D83DDCE2') . ' *MOMOREMIT CASHOUT COMMISSION NOTIFICATION*',
				'──────────────────────────',
				$icon('1F4B0') . ' *Commission Requests:* *' . count($transferEvents) . '*',
				$statusIcon . ' *Status:* *' . $eventStatus . '*',
				'',
				'*Details:*',
			];
			foreach ($transferEvents as $event) {
				$eventId = (int)($event['cashout_id'] ?? 0);
				$amount = number_format((float)($event['amount'] ?? 0), 2);
				$lines[] = $icon('1F4CC') . ' *Commission ID:* `#' . $eventId . '`';
				$lines[] = $icon('1F4B5') . ' *Amount:* _GHS ' . $amount . '_';
				if (!empty($event['network'])) {
					$lines[] = $icon('1F4F6') . ' *Network:* _' . trim((string)$event['network']) . '_';
				}
			}
			$viewUrl = rtrim((string)ROOT, '/') . '/admin/momoremit/cashout-commissions';
			$lines[] = '';
			$lines[] = '──────────────────────────';
			$lines[] = $icon('D83DDD17') . ' *View Cashout Commissions:*';
			$lines[] = $viewUrl;
			$message = implode("\n", $lines);
			$validIds = [];
			foreach ($admins as $admin) {
				$adminId = (int)($admin->id ?? 0);
				$validIds[$adminId] = true;
				$phoneResult = backoffice_whatsapp_phone($adminId);
				if (($phoneResult['phone'] ?? '') === '') {
					$summary['skipped']++;
					$summary['reasons'][] = $phoneResult['reason'] ?? 'Admin WhatsApp number is unavailable.';
					continue;
				}
				$summary['ready']++;
				$summary['links'][] = [
					'label' => trim((string)($admin->first_name ?? '') . ' ' . (string)($admin->last_name ?? '')),
					'phone' => $phoneResult['phone'],
					'url' => 'https://wa.me/' . $phoneResult['phone'] . '?text=' . rawurlencode($message),
				];
			}
			$summary['skipped'] += count(array_diff($adminIds, array_keys($validIds)));
		} catch (\Throwable $e) {
			$summary['failed']++;
			$summary['reasons'][] = 'Unable to send WhatsApp notifications.';
			error_log('MOMOREMIT CASHOUT WHATSAPP FAILED: ' . $e->getMessage());
		}
		$summary['reasons'] = array_values(array_unique($summary['reasons']));
		return $summary;
	}
}

if (!function_exists('momoremit_ensure_cashout_commissions_table')) {
	function momoremit_ensure_cashout_commissions_table()
	{
		$db = new \Core\Database;
		if (!empty($db->query("SHOW TABLES LIKE 'cashout_commissions'"))) {
			return true;
		}

		$sql = "CREATE TABLE IF NOT EXISTS `cashout_commissions` (
			`id` INT(11) NOT NULL AUTO_INCREMENT,
			`transaction_id` BIGINT(20) UNSIGNED DEFAULT NULL,
			`transaction_type` VARCHAR(50) DEFAULT NULL,
			`network` VARCHAR(50) DEFAULT NULL,
			`commission_type` VARCHAR(255) DEFAULT NULL,
			`commission_rate` DECIMAL(10,2) DEFAULT 0.00,
			`transaction_amount` DECIMAL(10,2) DEFAULT 0.00,
			`is_capped` TINYINT(1) DEFAULT 0,
			`cap_value` DECIMAL(10,2) DEFAULT 0.00,
			`commission_amount` DECIMAL(10,2) DEFAULT 0.00,
			`expected_value_percentage` DECIMAL(10,2) DEFAULT 0.00,
			`expected_value_amount` DECIMAL(10,2) DEFAULT 0.00,
			`status` VARCHAR(50) DEFAULT 'PENDING',
			`transferred_to_commission_id` INT(11) DEFAULT NULL,
			`transferred_amount` DECIMAL(10,2) DEFAULT 0.00,
			`user_id` INT(11) DEFAULT NULL,
			`created_by` INT(11) DEFAULT NULL,
			`transferred_by` INT(11) DEFAULT NULL,
			`transferred_at` DATETIME DEFAULT NULL,
			`notes` TEXT DEFAULT NULL,
			`disabled` TINYINT(1) DEFAULT 0,
			`deleted` TINYINT(1) DEFAULT 0,
			`date_created` DATETIME DEFAULT CURRENT_TIMESTAMP,
			`date_updated` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			`date_deleted` DATETIME DEFAULT NULL,
			PRIMARY KEY (`id`),
			KEY `transaction_id` (`transaction_id`),
			KEY `user_id` (`user_id`),
			KEY `status` (`status`),
			KEY `date_created` (`date_created`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

		try {
			$db->query($sql);
			return !$db->has_error;
		} catch (\Throwable $e) {
			error_log('Failed to create cashout_commissions table: ' . $e->getMessage());
			return false;
		}
	}
}

/**
 * Calculate and store a pending commission for withdrawal transaction
 * 
 * Called when withdrawal transaction is created in add-controller.php
 * Looks up commission rate from commission_rates table, calculates commission,
 * stores in cashout_commissions table as PENDING status.
 * 
 * Automatically creates the cashout_commissions table if it doesn't exist.
 * 
 * @param array $transactionData Transaction data from form
 * @return array|null Commission data if created, null if no rate configured
 */
function momoremit_calculate_cashout_commission($transactionData)
{
	try {
		// Ensure cashout_commissions table exists before attempting insert
		momoremit_ensure_cashout_commissions_table();
		
		$network = trim((string)($transactionData['network'] ?? ''));
		$transactionType = trim((string)($transactionData['transaction_type'] ?? ''));
		$amount = (float)($transactionData['amount'] ?? $transactionData['amount_to_withdraw'] ?? $transactionData['amount_payable'] ?? 0);
		$transactionId = (int)($transactionData['id'] ?? 0);
		$userId = (int)($transactionData['created_by'] ?? 0);
		
		// Validate required data
		if (empty($network) || empty($transactionType) || $amount <= 0 || $transactionId <= 0 || $userId <= 0) {
			return null;
		}

		// Only calculate commission for Withdrawal and Cash in
		$eligibleTypes = ['Withdrawal', 'Withdrawal CIT', 'CIT', 'cit' ];
		if (!in_array($transactionType, $eligibleTypes, true)) {
			return null;
		}

		// Normalize transaction type: "Cash in" → "CASH IN", "Withdrawal" → "WITHDRAWAL"
		$normalizedType = strtoupper(str_replace(' ', ' ', $transactionType));
		$networkUpper = strtoupper(trim($network));
		$commissionType = $networkUpper . ' ' . $normalizedType; // e.g., "MTN WITHDRAWAL"
		$commissionTypeCashout = $networkUpper . ' CASHOUT'; // e.g., "MTN CASHOUT" (fallback)
		
		// Look up commission rate from commission_rates table (admin-settings plugin)
		// Try specific format first (e.g., "MTN WITHDRAWAL"), then fallback to cashout format (e.g., "MTN CASHOUT")
		$db = new \Core\Database;
		$rateRow = $db->query(
			"SELECT * FROM commission_rates WHERE (commission_type = :commission_type OR commission_type = :commission_type_cashout) AND status = 1 LIMIT 1",
			['commission_type' => $commissionType, 'commission_type_cashout' => $commissionTypeCashout]
		);
		
		if (empty($rateRow)) {
			return null; // No commission rate configured for this type
		}

		$rate = $rateRow[0];
		$commissionRate = (float)($rate->commission_rate ?? 0);
		
		if ($commissionRate <= 0) {
			return null; // No commission to calculate
		}

		// Commission formula: (Amount × Rate%) / 100
		$commission = ($amount * $commissionRate) / 100;
		
		// Apply cap if enabled. If capped, the full commission amount cannot exceed the cap value.
		$isCapped = (int)($rate->is_capped ?? 0) === 1;
		$capValue = (float)($rate->cap_value ?? 0);
		if ($isCapped && $capValue > 0) {
			$commission = min($commission, $capValue);
		}

		// Calculate expected value from the final capped commission amount.
		$expectedValuePercentage = (float)($rate->expected_value ?? 0);
		$expectedValue = ($commission * $expectedValuePercentage) / 100;

		// Store the actual commission_type from the rate record (could be "MTN WITHDRAWAL" or "MTN CASHOUT")
		$storedCommissionType = (string)($rate->commission_type ?? $commissionType);

		// Insert into cashout_commissions table (staging)
		$commissionData = [
			'transaction_id' => $transactionId,
			'transaction_type' => $normalizedType,
			'network' => strtoupper($network),
			'commission_type' => $storedCommissionType,
			'commission_rate' => $commissionRate,
			'transaction_amount' => $amount,
			'is_capped' => $isCapped ? 1 : 0,
			'cap_value' => $capValue,
			'commission_amount' => $commission,
			'expected_value_percentage' => $expectedValuePercentage,
			'expected_value_amount' => $expectedValue,
			'status' => 'PENDING',
			'user_id' => $userId,
			'created_by' => $userId,
			'date_created' => date('Y-m-d H:i:s'),
		];

		// Insert the pending commission
		$insertSql = "INSERT INTO cashout_commissions (" . implode(',', array_keys($commissionData)) . ") 
		              VALUES (:" . implode(',:', array_keys($commissionData)) . ")";
		
		$db->query($insertSql, $commissionData);
		
		error_log('MOMOREMIT: Cashout commission recorded for transaction ' . $transactionId . 
			' - Amount: GHS ' . number_format($commission, 2));

		return $commissionData;
		
	} catch (\Throwable $e) {
		error_log('MOMOREMIT: Error calculating cashout commission: ' . $e->getMessage());
		return null;
	}
}

/**
 * Get all commissions (pending and transferred) for a user
 * 
 * @param int $userId User ID
 * @param bool $includeAllForAdmin Include all records for admins or operations managers
 * @return array ['commissions' => array, 'summary' => object]
 */
function momoremit_get_user_pending_commissions($userId, $includeAllForAdmin = false)
{
	try {
		$userId = (int)$userId;
		$includeAllForAdmin = (bool)$includeAllForAdmin;
		if ($userId <= 0) {
			return ['commissions' => [], 'summary' => (object)[
				'pending_count' => 0,
				'pending_amount' => 0.00,
				'pending_expected_amount' => 0.00,
				'transferred_count' => 0,
				'transferred_amount' => 0.00,
				'transferred_expected_amount' => 0.00,
				'total_expected_amount' => 0.00,
				'total_collected_amount' => 0.00,
				'by_commission_type' => [],
			]];
		}

		$db = new \Core\Database;
		$canViewAllCommissions = (bool)$includeAllForAdmin && (user_can('is_admin') || user_can('is_operations_manager'));
		$whereClause = 'WHERE c.deleted = 0';
		$params = [];
		if (!$canViewAllCommissions) {
			$whereClause .= ' AND c.user_id = :user_id';
			$params['user_id'] = $userId;
		}

		$commissions = $db->query(
			"SELECT c.*, u.first_name AS creator_first_name, u.last_name AS creator_last_name
			 FROM cashout_commissions c
			 LEFT JOIN users u ON u.id = c.created_by
			 {$whereClause}
			 ORDER BY c.date_created DESC",
			$params
		) ?: [];

		$summary = (object)[
			'pending_count' => 0,
			'pending_amount' => 0.00,
			'pending_expected_amount' => 0.00,
			'transferred_count' => 0,
			'transferred_amount' => 0.00,
			'transferred_expected_amount' => 0.00,
			'total_expected_amount' => 0.00,
			'total_collected_amount' => 0.00,
			'by_commission_type' => [],
		];

		foreach ($commissions as $commission) {
			$status = strtoupper(trim((string)($commission->status ?? '')));
			$expectedValue = max(0.0, (float)($commission->expected_value_amount ?? 0));
			$transferredAmount = max(0.0, (float)($commission->transferred_amount ?? 0));
			$remainingExpected = max(0.0, $expectedValue - $transferredAmount);
			$commissionType = strtoupper(trim((string)($commission->commission_type ?? 'UNKNOWN')));
			if ($commissionType === '') {
				$commissionType = 'UNKNOWN';
			}

			if (!isset($summary->by_commission_type[$commissionType])) {
				$summary->by_commission_type[$commissionType] = [
					'commission_type' => $commissionType,
					'count' => 0,
					'pending_count' => 0,
					'transferred_count' => 0,
					'pending_amount' => 0.0,
					'transferred_amount' => 0.0,
					'expected_amount' => 0.0,
				];
			}

			$summary->by_commission_type[$commissionType]['count'] += 1;
			$summary->by_commission_type[$commissionType]['expected_amount'] += $expectedValue;

			if ($status === 'PENDING') {
				$summary->pending_count += 1;
				$summary->pending_amount += $remainingExpected;
				$summary->pending_expected_amount += $remainingExpected;
				$summary->by_commission_type[$commissionType]['pending_count'] += 1;
				$summary->by_commission_type[$commissionType]['pending_amount'] += $remainingExpected;
			} elseif ($status === 'PARTIALLY_TRANSFERRED') {
				$summary->pending_count += 1;
				$summary->pending_amount += $remainingExpected;
				$summary->pending_expected_amount += $remainingExpected;
				$summary->by_commission_type[$commissionType]['pending_count'] += 1;
				$summary->by_commission_type[$commissionType]['pending_amount'] += $remainingExpected;

				$summary->transferred_count += 1;
				$summary->transferred_amount += $transferredAmount;
				$summary->transferred_expected_amount += $transferredAmount;
				$summary->by_commission_type[$commissionType]['transferred_count'] += 1;
				$summary->by_commission_type[$commissionType]['transferred_amount'] += $transferredAmount;
			} elseif ($status === 'TRANSFERRED') {
				$summary->transferred_count += 1;
				$summary->transferred_amount += $expectedValue;
				$summary->transferred_expected_amount += $expectedValue;
				$summary->by_commission_type[$commissionType]['transferred_count'] += 1;
				$summary->by_commission_type[$commissionType]['transferred_amount'] += $expectedValue;
			}

			$summary->total_collected_amount += $transferredAmount;
			$summary->total_expected_amount += $expectedValue;
		}

		$summary->by_commission_type = array_values($summary->by_commission_type);
		usort($summary->by_commission_type, function ($a, $b) {
			return ($b['expected_amount'] ?? 0.0) <=> ($a['expected_amount'] ?? 0.0);
		});

		$summary->pending_amount = (float)($summary->pending_expected_amount ?? 0.00);
		$summary->transferred_amount = (float)($summary->transferred_expected_amount ?? 0.00);

		return [
			'commissions' => $commissions,
			'summary' => $summary,
		];
	} catch (\Throwable $e) {
		error_log('MOMOREMIT: Error getting user commissions: ' . $e->getMessage());
		return ['commissions' => [], 'summary' => (object)[
			'pending_count' => 0,
			'pending_amount' => 0.00,
			'pending_expected_amount' => 0.00,
			'transferred_count' => 0,
			'transferred_amount' => 0.00,
			'transferred_expected_amount' => 0.00,
			'total_expected_amount' => 0.00,
			'total_collected_amount' => 0.00,
			'by_commission_type' => [],
		]];
	}
}

/**
 * Transfer commissions from cashout_commissions to backoffice commissions
 * 
 * Creates record in backoffice commissions table with proper fields.
 * Marks cashout_commissions as TRANSFERRED with link to new commission record.
 * Uses backoffice commission creation flow/fields.
 * 
 * @param array $commissionIds IDs from cashout_commissions table
 * @param float $amount Optional amount for partial transfers
 * @param int $userId User doing the transfer
 * @return array ['success' => bool, 'message' => string, 'commission_ids' => array]
 */
function momoremit_transfer_commissions_to_main($commissionIds, $amount = null, $userId = 0, $perIdAmounts = [], $proofImages = [], $whatsappAdminIds = [])
{
	try {
		$userId = (int)$userId;
		if ($userId <= 0 || empty($commissionIds)) {
			return ['success' => false, 'message' => 'Invalid user or commission IDs'];
		}

		$db = new \Core\Database;
		try {
			$commissionSchema = $db->query("SHOW COLUMNS FROM commissions") ?: [];
			$hasProofImage = false;
			foreach ($commissionSchema as $column) {
				if (strtolower(trim((string)($column->Field ?? ''))) === 'proof_image') {
					$hasProofImage = true;
					break;
				}
			}
			if (!$hasProofImage) {
				$db->query('ALTER TABLE commissions ADD COLUMN proof_image VARCHAR(255) NULL');
				if ($db->has_error) {
					throw new \RuntimeException($db->error ?: 'Unable to add the proof-image field.');
				}
			}
		} catch (\Throwable $schemaError) {
			throw new \RuntimeException('The commissions proof-image field is unavailable.');
		}
		$commissionColumns = [];
		foreach (($db->query('SHOW COLUMNS FROM commissions') ?: []) as $column) {
			$commissionColumns[(string)($column->Field ?? '')] = true;
		}
		$transferredIds = [];
		$transferEvents = [];
		$now = date('Y-m-d H:i:s');
		$remainingAmount = $amount === null ? null : round((float)$amount, 2);
		if ($remainingAmount !== null && $remainingAmount <= 0) {
			return ['success' => false, 'message' => 'Transfer amount must be greater than zero.'];
		}

		$pendingCommissions = [];
		$totalRemainingExpectedAmount = 0.0;
		$normalizedPerIdAmounts = [];
		foreach ($commissionIds as $commissionId) {
			$normalizedId = (int)$commissionId;
			if ($normalizedId <= 0) {
				continue;
			}
			$isAdmin = user_can('is_admin');
			$pendingRows = $db->query(
				"SELECT * FROM cashout_commissions
				 WHERE id = :id AND status IN ('PENDING', 'PARTIALLY_TRANSFERRED')
				   AND deleted = 0
				   AND (transferred_to_commission_id IS NULL OR NOT EXISTS (
					   SELECT 1 FROM commissions linked
					   WHERE linked.id = cashout_commissions.transferred_to_commission_id
					     AND linked.deleted = 0
					     AND LOWER(COALESCE(linked.transaction_status, '')) IN ('pending', 'draft')
				   ))
				 LIMIT 1",
				['id' => $normalizedId]
			);
			if (empty($pendingRows)) {
				continue;
			}

			$pending = $pendingRows[0];
			$ownerUserId = (int)($pending->user_id ?? 0);
			$creatorUserId = (int)($pending->created_by ?? 0);
			if ($isAdmin) {
				if ($creatorUserId !== $userId) {
					continue;
				}
			} elseif ($ownerUserId !== $userId && $creatorUserId !== $userId) {
				continue;
			}

			$expectedVal = max(0.0, (float)($pending->expected_value_amount ?? 0));
			$alreadyTransferredVal = max(0.0, (float)($pending->transferred_amount ?? 0));
			$remExpectedVal = max(0.0, $expectedVal - $alreadyTransferredVal);

			$pendingCommissions[] = $pending;
			$totalRemainingExpectedAmount += $remExpectedVal;

			$flatKey = (string)$normalizedId;
			if (array_key_exists($flatKey, $perIdAmounts) || array_key_exists($normalizedId, $perIdAmounts)) {
				$rawAmount = $perIdAmounts[$flatKey] ?? $perIdAmounts[$normalizedId] ?? null;
				$normalizedPerIdAmounts[$normalizedId] = $rawAmount === null || $rawAmount === '' ? null : max(0.0, (float)$rawAmount);
			}
		}

		if (empty($pendingCommissions)) {
			return ['success' => false, 'message' => 'No pending commissions found for transfer.'];
		}
		if ($remainingAmount !== null && $remainingAmount > round($totalRemainingExpectedAmount, 2) + 0.005) {
			return ['success' => false, 'message' => 'Transfer amount exceeds the remaining expected amount of the selected pending commissions.'];
		}

		$db->query('START TRANSACTION');

		foreach ($pendingCommissions as $pending) {
			$commissionId = (int)$pending->id;
			$expectedAmount = max(0.0, (float)($pending->expected_value_amount ?? 0));
			$alreadyTransferred = max(0.0, (float)($pending->transferred_amount ?? 0));
			$remainingExpected = max(0.0, $expectedAmount - $alreadyTransferred);

			if ($remainingExpected <= 0.005) {
				continue;
			}

			$customAmount = array_key_exists($commissionId, $normalizedPerIdAmounts) ? $normalizedPerIdAmounts[$commissionId] : null;
			$transferLimit = $customAmount !== null ? $customAmount : ($remainingAmount === null ? $remainingExpected : min($remainingExpected, $remainingAmount));
			$transferAmount = round(min($remainingExpected, max(0.0, $transferLimit)), 2);

			if ($transferAmount <= 0) {
				continue;
			}
			if ($remainingAmount !== null && $remainingAmount <= 0.005) {
				break;
			}

			$approverId = 0;
			if (function_exists('backoffice_commission_assignable_users')) {
				$approvers = backoffice_commission_assignable_users($userId, get_session(), true);
				$approverId = (int)($approvers[0]->id ?? 0);
			}
			if ($approverId <= 0 && user_can('is_admin')) {
				$approverId = $userId;
			}
			if ($approverId <= 0) {
				throw new \RuntimeException('No administrator or general manager is available to approve this commission.');
			}

			$backofficeData = [
				'date_received' => $now,
				'network' => trim((string)($pending->network ?? '')),
				'commission_type' => 'MOMOREMIT ' . strtoupper(trim((string)($pending->transaction_type ?? ''))) . ' COMMISSION',
				'amount' => $transferAmount,
				'transaction_status' => 'Pending',
				'colletion_period' => 'INSTANT',
				'created_by' => $userId,
				'assigned' => $approverId,
				'pay_to' => $approverId,
				'description' => 'Cashout commission from transaction ID ' . (int)($pending->transaction_id ?? 0) . ' [momoremit_cashout_commission_id:' . $commissionId . ']',
				'disabled' => 0,
				'deleted' => 0,
				'date_created' => $now,
			];
			if (!empty($commissionColumns['proof_image']) && !empty($proofImages[$commissionId])) {
				$backofficeData['proof_image'] = (string)$proofImages[$commissionId];
			}

			$insertSql = "INSERT INTO commissions (" . implode(',', array_keys($backofficeData)) . ")
			              VALUES (:" . implode(',:', array_keys($backofficeData)) . ")";
			$db->query($insertSql, $backofficeData);
			if ($db->has_error) {
				throw new \RuntimeException($db->error ?: 'Failed to create commission record.');
			}

			$backofficeId = (int)($db->insert_id ?? 0);
			if ($backofficeId <= 0) {
				throw new \RuntimeException('Failed to create commission record.');
			}

			$nextStatus = $alreadyTransferred > 0.005 ? 'PARTIALLY_TRANSFERRED' : 'PENDING';
			$updateSql = "UPDATE cashout_commissions
			             SET status = :status,
			                 transferred_to_commission_id = :commission_id,
			                 transferred_amount = :transferred_amount,
			                 transferred_by = :user_id,
			                 transferred_at = :transferred_at,
			                 notes = NULL,
			                 date_updated = :now
			             WHERE id = :id";

			$db->query($updateSql, [
				'status' => $nextStatus,
				'commission_id' => $backofficeId,
				'transferred_amount' => $alreadyTransferred,
				'user_id' => $userId,
				'transferred_at' => null,
				'now' => $now,
				'id' => $commissionId,
			]);
			if ($db->has_error) {
				throw new \RuntimeException($db->error ?: 'Failed to update cashout commission.');
			}

			$transferredIds[] = $backofficeId;
			$transferEvents[] = [
				'cashout_id' => $commissionId,
				'backoffice_id' => $backofficeId,
				'amount' => $transferAmount,
				'network' => trim((string)($pending->network ?? '')),
			];
			if ($remainingAmount !== null) {
				$remainingAmount = round(max(0.0, $remainingAmount - $transferAmount), 2);
			}
		}

		$db->query('COMMIT');
		if (empty($transferredIds)) {
			return ['success' => false, 'message' => 'No commission amount was available to transfer.'];
		}
		foreach ($transferEvents as $event) {
			momoremit_cashout_log_event(
				'transferred',
				$userId,
				null,
				'Cashout commission #' . (int)$event['cashout_id'] . ' transferred to commission #' . (int)$event['backoffice_id'] . ' for GHS ' . number_format((float)$event['amount'], 2) . '.'
			);
		}
		$whatsapp = momoremit_notify_cashout_transfer_whatsapp($whatsappAdminIds, $transferEvents);

		return [
			'success' => true,
				'message' => count($transferredIds) . ' commission request(s) submitted for admin approval',
			'commission_ids' => $transferredIds,
			'whatsapp' => $whatsapp,
		];
	} catch (\Throwable $e) {
		try {
			$db = new \Core\Database;
			$db->query('ROLLBACK');
		} catch (\Throwable $rollbackError) {}

		error_log('MOMOREMIT: Error transferring commissions: ' . $e->getMessage());
		return ['success' => false, 'message' => 'Error transferring commissions: ' . $e->getMessage()];
	}
}

if (!function_exists('momoremit_normalize_commission_description')) {
	function momoremit_normalize_commission_description($description): string
	{
		$text = trim((string)($description ?? ''));
		$text = preg_replace('/\s*\[\s*momoremit_cashout_commission_id\s*:\s*\d+\s*\]\s*/i', ' ', $text);
		$text = preg_replace('/\s+/', ' ', $text);
		return trim($text);
	}
}

if (!function_exists('momoremit_finalize_pending_commission_review')) {
	function momoremit_approve_transfer_request(int $cashoutId, int $reviewerId, array $whatsappAdminIds = []): array
	{
		$db = new \Core\Database;
		$sourceRows = $db->query(
			"SELECT * FROM cashout_commissions
			 WHERE id = :id AND deleted = 0 LIMIT 1",
			['id' => $cashoutId]
		) ?: [];
		$source = $sourceRows[0] ?? null;
		$backofficeId = (int)($source->transferred_to_commission_id ?? 0);
		if (!$source || $backofficeId <= 0) {
			return ['success' => false, 'message' => 'Pending commission request was not found.'];
		}
		if (!in_array(strtoupper(trim((string)($source->status ?? ''))), ['PENDING', 'PARTIALLY_TRANSFERRED'], true)) {
			return ['success' => false, 'message' => 'This cashout commission is no longer awaiting transfer approval.'];
		}

		$mainRows = $db->query('SELECT * FROM commissions WHERE id = :id AND deleted = 0 LIMIT 1', ['id' => $backofficeId]) ?: [];
		$main = $mainRows[0] ?? null;
		if (!$main) {
			return ['success' => false, 'message' => 'The linked commission request was not found.'];
		}
		if (!in_array(strtolower(trim((string)($main->transaction_status ?? ''))), ['pending', 'draft'], true)) {
			return ['success' => false, 'message' => 'This linked commission is no longer awaiting approval.'];
		}

		$transferUserId = (int)($main->created_by ?? $source->created_by ?? 0);
		$amount = max(0.0, (float)($main->amount ?? 0));
		$networkKey = function_exists('backoffice_commission_credit_channel_key')
			? backoffice_commission_credit_channel_key($main)
			: strtoupper(trim((string)($source->network ?? '')));
		if ($transferUserId <= 0 || $amount <= 0 || $networkKey === '') {
			return ['success' => false, 'message' => 'The commission request is missing a valid user, amount, or network.'];
		}
		if (!function_exists('BackOfficeCashdrawer\\getActiveDrawer') || !function_exists('BackOfficeCashdrawer\\syncEcashMovement')) {
			return ['success' => false, 'message' => 'Cashdrawer commission integration is unavailable.'];
		}

		$drawer = \BackOfficeCashdrawer\getActiveDrawer($transferUserId);
		if (empty($drawer)) {
			return ['success' => false, 'message' => 'The transferring user must open a cashdrawer before approval.'];
		}
		try {
			$movement = \BackOfficeCashdrawer\syncEcashMovement($transferUserId, $networkKey, $amount, 'Approved', $backofficeId, 'in');
			if ($movement === null) {
				return ['success' => false, 'message' => 'Failed to credit the transferring user\'s network cashdrawer.'];
			}
		} catch (\Throwable $error) {
			return ['success' => false, 'message' => 'Failed to credit the transferring user\'s network cashdrawer: ' . $error->getMessage()];
		}

		$now = date('Y-m-d H:i:s');
		$db->query(
			"UPDATE commissions SET transaction_status = 'Approved', approved_by = :approved_by, approved_at = :approved_at, updated_by = :updated_by, date_updated = :date_updated WHERE id = :id",
			['approved_by' => $reviewerId, 'approved_at' => $now, 'updated_by' => $reviewerId, 'date_updated' => $now, 'id' => $backofficeId]
		);
		if ($db->has_error) {
			return ['success' => false, 'message' => 'Cashdrawer was credited, but the commission approval could not be saved.'];
		}

		if (!momoremit_finalize_pending_commission_review($main, $reviewerId, true)) {
			return ['success' => false, 'message' => 'Commission was approved, but the Momoremit staging record could not be finalized.'];
		}
		momoremit_cashout_log_event(
			'approved',
			$reviewerId,
			$source,
			'Cashout commission #' . $cashoutId . ' transfer request approved for GHS ' . number_format($amount, 2) . '.'
		);
		$whatsapp = momoremit_notify_cashout_transfer_whatsapp($whatsappAdminIds, [[
			'cashout_id' => $cashoutId,
			'amount' => $amount,
			'network' => trim((string)($source->network ?? '')),
			'status' => 'Approved',
		]]);
		return ['success' => true, 'message' => 'Commission approved and added to the transferring user\'s ' . $networkKey . ' cashdrawer.', 'whatsapp' => $whatsapp];
	}

	function momoremit_reject_transfer_request(int $cashoutId, int $reviewerId, string $reason, array $whatsappAdminIds = []): array
	{
		$reason = trim($reason);
		if ($cashoutId <= 0 || $reviewerId <= 0 || $reason === '') {
			return ['success' => false, 'message' => 'A rejection reason is required.'];
		}
		$db = new \Core\Database;
		$sourceRows = $db->query('SELECT * FROM cashout_commissions WHERE id = :id AND deleted = 0 LIMIT 1', ['id' => $cashoutId]) ?: [];
		$source = $sourceRows[0] ?? null;
		$backofficeId = (int)($source->transferred_to_commission_id ?? 0);
		if (!$source || $backofficeId <= 0) {
			return ['success' => false, 'message' => 'Pending commission request was not found.'];
		}
		$now = date('Y-m-d H:i:s');
		$db->query(
			"UPDATE commissions SET transaction_status = 'Rejected', reject_reason = :reason, updated_by = :updated_by, date_updated = :date_updated WHERE id = :id",
			['reason' => $reason, 'updated_by' => $reviewerId, 'date_updated' => $now, 'id' => $backofficeId]
		);
		if ($db->has_error) {
			return ['success' => false, 'message' => 'The commission request could not be rejected.'];
		}
		$db->query(
			"UPDATE cashout_commissions
			 SET transferred_to_commission_id = NULL,
			     transferred_at = NULL,
			     notes = :notes,
			     date_updated = :date_updated
			 WHERE id = :id",
			[
				'notes' => 'Rejected by administrator: ' . $reason,
				'date_updated' => $now,
				'id' => $cashoutId,
			]
		);
		if ($db->has_error) {
			return ['success' => false, 'message' => 'The rejection reason could not be saved.'];
		}
		momoremit_cashout_log_event(
			'rejected',
			$reviewerId,
			$source,
			'Cashout commission #' . $cashoutId . ' transfer request rejected. Reason: ' . $reason
		);
		$whatsapp = momoremit_notify_cashout_transfer_whatsapp($whatsappAdminIds, [[
			'cashout_id' => $cashoutId,
			'amount' => (float)($source->expected_value_amount ?? 0),
			'network' => trim((string)($source->network ?? '')),
			'status' => 'Rejected',
		]]);
		return ['success' => true, 'message' => 'Commission request rejected and returned to pending expected.', 'whatsapp' => $whatsapp];
	}

	function momoremit_finalize_pending_commission_review($commissionRow, int $reviewerId, bool $approved): bool
	{
		$description = (string)($commissionRow->description ?? '');
		if (!preg_match('/\[momoremit_cashout_commission_id:(\d+)\]/', $description, $match)) {
			return true;
		}

		$cashoutId = (int)($match[1] ?? 0);
		if ($cashoutId <= 0) {
			return false;
		}

		$db = new \Core\Database;
		$sourceRows = $db->query(
			"SELECT * FROM cashout_commissions
			 WHERE id = :id AND deleted = 0 LIMIT 1",
			['id' => $cashoutId]
		) ?: [];
		$source = $sourceRows[0] ?? null;
		if (!$source) {
			return false;
		}

		if (!$approved) {
			$db->query(
				"UPDATE cashout_commissions
				 SET transferred_to_commission_id = NULL,
				     transferred_at = NULL,
				     date_updated = :date_updated
				 WHERE id = :id",
				['date_updated' => date('Y-m-d H:i:s'), 'id' => $cashoutId]
			);
			return !$db->has_error;
		}

		$requestedAmount = max(0.0, (float)($commissionRow->amount ?? 0));
		$expectedAmount = max(0.0, (float)($source->expected_value_amount ?? 0));
		$alreadyTransferred = max(0.0, (float)($source->transferred_amount ?? 0));
		$updatedTransferred = round(min($expectedAmount, $alreadyTransferred + $requestedAmount), 2);
		$nextStatus = ($expectedAmount - $updatedTransferred) <= 0.005 ? 'TRANSFERRED' : 'PARTIALLY_TRANSFERRED';
		$now = date('Y-m-d H:i:s');
		$db->query(
			"UPDATE cashout_commissions
			 SET status = :status,
			     transferred_amount = :transferred_amount,
			     transferred_by = :transferred_by,
			     transferred_at = :transferred_at,
			     date_updated = :date_updated
			 WHERE id = :id",
			[
				'status' => $nextStatus,
				'transferred_amount' => $updatedTransferred,
				'transferred_by' => (int)($commissionRow->created_by ?? $reviewerId),
				'transferred_at' => $now,
				'date_updated' => $now,
				'id' => $cashoutId,
			]
		);
		return !$db->has_error;
	}
}

/**
 * Get commission by transaction ID
 */
function momoremit_get_commission_by_transaction($transactionId)
{
	try {
		$transactionId = (int)$transactionId;
		$db = new \Core\Database;
		
		$rows = $db->query(
			"SELECT * FROM cashout_commissions WHERE transaction_id = :transaction_id LIMIT 1",
			['transaction_id' => $transactionId]
		);

		return empty($rows) ? null : $rows[0];

	} catch (\Throwable $e) {
		return null;
	}
}

/**
 * Check if commission rates are configured
 */
function momoremit_has_commission_rate()
{
	try {
		$db = new \Core\Database;
		$rows = $db->query("SELECT COUNT(*) as cnt FROM commission_rates WHERE status = 1 LIMIT 1");
		return !empty($rows) && ((int)($rows[0]->cnt ?? 0) > 0);
	} catch (\Throwable $e) {
		return false;
	}
}

/**
 * Format commission for display
 */
function momoremit_format_commission_display($commission)
{
	if (!is_object($commission)) {
		return '';
	}

	$network = trim((string)($commission->network ?? ''));
	$type = trim((string)($commission->transaction_type ?? ''));
	$amount = number_format((float)($commission->commission_amount ?? 0), 2);
	$rate = number_format((float)($commission->commission_rate ?? 0), 2);

	return "$network $type - GHS $amount (Rate: {$rate}%)";
}

function momoremit_commission_payload($commission)
{
	if (!is_object($commission)) {
		return null;
	}

	$creatorFirst = trim((string)($commission->creator_first_name ?? ''));
	$creatorLast = trim((string)($commission->creator_last_name ?? ''));
	$createdByName = trim($creatorFirst . ' ' . $creatorLast);

	return [
		'id' => (int)($commission->id ?? 0),
		'network' => (string)($commission->network ?? ''),
		'commission_type' => (string)($commission->commission_type ?? ''),
		'transaction_amount' => number_format((float)($commission->transaction_amount ?? 0), 2, '.', ''),
		'commission_rate' => number_format((float)($commission->commission_rate ?? 0), 2, '.', ''),
		'commission_amount' => number_format((float)($commission->commission_amount ?? 0), 2, '.', ''),
		'transferred_amount' => number_format((float)($commission->transferred_amount ?? 0), 2, '.', ''),
		'expected_value_amount' => number_format((float)($commission->expected_value_amount ?? 0), 2, '.', ''),
		'expected_value_percentage' => number_format((float)($commission->expected_value_percentage ?? 0), 2, '.', ''),
		'created_by_name' => $createdByName !== '' ? $createdByName : '—',
		'created_by' => (int)($commission->created_by ?? 0),
		'status' => strtoupper(trim((string)($commission->status ?? ''))),
		'date_created' => (string)($commission->date_created ?? ''),
		'transferred_at' => (string)($commission->transferred_at ?? ''),
	];
}
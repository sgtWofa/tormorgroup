<?php

require_once plugin_path('helpers/customer-ledger.php');
$transferHelperPath = realpath(__DIR__ . '/../helpers/transfer-allocation.php');
if ($transferHelperPath && file_exists($transferHelperPath)) {
	require_once $transferHelperPath;
}

// Set timezone to Accra (Africa/Accra)
date_default_timezone_set('Africa/Accra');

$cashpickup = new \Cashpickups\Cashpickup;

$postdata = $req->post();

$session = get_session();
$userId = (int)($session->id ?? 0);
$canEditCashpickup = (user_can('is_admin') || user_can('is_manager'));
if(!$canEditCashpickup){
	$cashpickup->errors['date'] = "Access denied";
	set_value('errors',$cashpickup->errors);
	return;
}

$postdata['idcard_photo'] = $postdata['idcard_photo'] ?? ($row->idcard_photo ?? '');
$postdata['customer_photo'] = $postdata['customer_photo'] ?? ($row->customer_photo ?? '');
$postdata['receive_proof_photo'] = $postdata['receive_proof_photo'] ?? ($row->receive_proof_photo ?? '');

try {
	$dbSchema = new \Core\Database;
	$neededCols = ['idcard_photo', 'customer_photo', 'receive_proof_photo', 'denos_json', 'assigned_user_id', 'approved_by', 'approved_at', 'receive_id', 'pay_type', 'amount_requested'];
	$neededCols[] = 'customer_id';
	$neededCols[] = 'customer_id_type';
	$neededCols[] = 'customer_id_number';
	$neededCols[] = 'customer_location';
	$missingCols = [];
	foreach ($neededCols as $col) {
		$hasCol = $dbSchema->query(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'cashpickups' AND COLUMN_NAME = :col LIMIT 1",
			['db' => DB_NAME, 'col' => $col]
		);
		if (empty($hasCol)) {
			$missingCols[] = $col;
		}
	}
	if (!empty($missingCols)) {
		$typeMap = [
			'idcard_photo' => 'VARCHAR(255) NULL',
			'customer_photo' => 'VARCHAR(255) NULL',
			'receive_proof_photo' => 'VARCHAR(255) NULL',
			'denos_json' => 'JSON NULL',
			'assigned_user_id' => 'INT unsigned NULL',
			'approved_by' => 'INT unsigned NULL',
			'approved_at' => 'datetime default null',
			'receive_id' => 'BIGINT unsigned NULL',
			'pay_type' => 'varchar(20) null',
			'amount_requested' => 'FLOAT(10,2) DEFAULT 0.00',
			'customer_id' => 'BIGINT unsigned NULL',
			'customer_id_type' => 'VARCHAR(40) NULL',
			'customer_id_number' => 'VARCHAR(80) NULL',
			'customer_location' => 'VARCHAR(150) NULL',
		];
		foreach ($missingCols as $col) {
			try {
				$colType = $typeMap[$col] ?? 'VARCHAR(255) NULL';
				$dbSchema->query("ALTER TABLE cashpickups ADD COLUMN {$col} {$colType}");
			} catch (\Throwable $e) {
				$cashpickup->errors[$col] = "Database column missing: cashpickups.{$col}";
				set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
				return;
			}
		}
	}
} catch (\Throwable $e) {
	$cashpickup->errors['idcard_photo'] = 'Unable to validate cash pickup photo storage.';
	set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
	return;
}

try {
	$ledgerDb = new \Core\Database;
	\CashpickupLedger\ensure_cashpickup_customer_schema($ledgerDb);
	\CashpickupLedger\ensure_cashpickup_columns($ledgerDb);
} catch (\Throwable $e) {
	// ledger is optional; ignore failures
}

$req->upload_folder = 'uploads/cashpickup';
$uploadedIdCard = $req->upload_files('idcard_photo');
if (!empty($uploadedIdCard)) {
	$postdata['idcard_photo'] = $uploadedIdCard;
}
$uploadedCustomer = $req->upload_files('customer_photo');
if (!empty($uploadedCustomer)) {
	$postdata['customer_photo'] = $uploadedCustomer;
}
$uploadedProof = $req->upload_files('receive_proof_photo');
if (!empty($uploadedProof)) {
	$postdata['receive_proof_photo'] = $uploadedProof;
}

$postdata['denos_json'] = $postdata['denos_json'] ?? ($postdata['denos'] ?? ($row->denos_json ?? ''));

$postdata['amount_involved'] = (float)($postdata['amount_involved'] ?? 0);
$postdata['pickup_charge'] = (float)($postdata['pickup_charge'] ?? 0);
$postdata['amount_paid_out'] = (float)($postdata['amount_paid_out'] ?? 0);
$postdata['amount_transferred'] = (float)($postdata['amount_transferred'] ?? 0);
$postdata['amount_requested'] = (float)($postdata['amount_requested'] ?? 0);

// normalize customer bio fields
$postdata['customer_id_type'] = trim((string)($postdata['customer_id_type'] ?? ($row->customer_id_type ?? '')));
$postdata['customer_id_number'] = trim((string)($postdata['customer_id_number'] ?? ($row->customer_id_number ?? '')));
$postdata['customer_location'] = trim((string)($postdata['customer_location'] ?? ($row->customer_location ?? '')));

$side = strtolower(trim((string)($postdata['transaction_side'] ?? '')));
$originalSide = strtolower(trim((string)($row->transaction_side ?? '')));
$originalSource = strtolower(trim((string)($row->source ?? '')));
$originalBank = trim((string)($row->account_paid_to ?? ''));
$originalAmount = (float)($row->amount_involved ?? 0);

$toFloat = function($v): float {
	if (is_string($v)) {
		$v = trim($v);
		$v = str_replace([',', ' '], '', $v);
		$v = preg_replace('/[^0-9.\-]/', '', $v);
	}
	return (float)$v;
};

// status is always system-controlled
$postdata['transaction_status'] = 'Approved';

if ($side === 'receive') {
	$postdata['source'] = $postdata['source'] ?? ($row->source ?? 'Cash');
	$postdata['account_paid_to'] = $postdata['account_paid_to'] ?? ($row->account_paid_to ?? '');
	$srcLower = strtolower(trim((string)$postdata['source']));
	if ($srcLower !== 'bank') {
		$postdata['account_paid_to'] = '';
	}

	$denosRaw = (string)($postdata['denos_json'] ?? '');
	$decoded = json_decode($denosRaw, true);
	if (is_array($decoded) && !empty($decoded)) {
		$sum = 0.0;
		foreach ($decoded as $d) {
			$sum += (float)($d['volume'] ?? 0);
		}
		$postdata['amount_involved'] = (float)number_format($sum, 2, '.', '');
	}
}

if ($side === 'pay') {
	// Auto-populate amount + customer details from receive record (payout-driven)
	try {
		$dbLookup = new \Core\Database;
		$receiveId = (int)($postdata['receive_id'] ?? ($row->receive_id ?? 0));
		if ($receiveId > 0) {
			$receive = $dbLookup->query(
				"SELECT id, phone_number, tracking_id, sender_details, amount_involved, source,
				        customer_id, customer_id_type, customer_id_number, customer_location
				 FROM cashpickups
				 WHERE (deleted IS NULL OR deleted = 0)
				 AND LOWER(transaction_side) = 'receive'
				 AND id = :id
				 ORDER BY date_created DESC, id DESC LIMIT 1",
				['id' => $receiveId]
			);
			if (!empty($receive)) {
				$receiveRow = $receive[0];
				$postdata['sender_details'] = (string)($receiveRow->sender_details ?? '');
				$postdata['phone_number'] = (string)($receiveRow->phone_number ?? '');
				$postdata['tracking_id'] = (string)($receiveRow->tracking_id ?? '');
				$postdata['source'] = (string)($receiveRow->source ?? '');
				$postdata['customer_id'] = (int)($receiveRow->customer_id ?? 0);
				$postdata['customer_id_type'] = (string)($receiveRow->customer_id_type ?? $postdata['customer_id_type'] ?? '');
				$postdata['customer_id_number'] = (string)($receiveRow->customer_id_number ?? $postdata['customer_id_number'] ?? '');
				$postdata['customer_location'] = (string)($receiveRow->customer_location ?? $postdata['customer_location'] ?? '');
				$receiveTotal = $toFloat($receiveRow->amount_involved ?? 0);

				$payoutSum = $dbLookup->query(
					"SELECT COALESCE(SUM(COALESCE(amount_paid_out,0) + COALESCE(pickup_charge,0)),0) AS paid
					 FROM cashpickups
					 WHERE (deleted IS NULL OR deleted = 0)
					 AND LOWER(transaction_side) = 'pay'
					 AND (
						approval_status IS NULL
						OR approval_status = ''
						OR LOWER(approval_status) IN ('approved','pending')
					 )
					 AND transaction_status = 'Approved'
					 AND (confirmed_by IS NOT NULL AND confirmed_by != '')
					 AND COALESCE(amount_paid_out,0) > 0
					 AND receive_id = :rid
					 AND id != :this_id",
					['rid' => $receiveId, 'this_id' => (int)($row->id ?? 0)]
				);
				$alreadyPaid = $toFloat($payoutSum[0]->paid ?? 0);
				$remaining = max(0, $receiveTotal - $alreadyPaid);
				$assignedGross = 0.0;
				$assignedCharge = 0.0;
				$assignedPayable = 0.0;
				$assignedRemaining = 0.0;
				$hasAssignedAllocation = false;
				try {
					$allocDb = new \Core\Database;
					if (function_exists('CashpickupTransferAllocation\\get_transfer_allocation')) {
						$allocation = \CashpickupTransferAllocation\get_transfer_allocation($allocDb, $receiveId, (int)$userId);
						$assignedGross = (float)($allocation['gross'] ?? 0);
						$assignedCharge = (float)($allocation['charge'] ?? 0);
						$assignedPayable = (float)($allocation['payable'] ?? 0);
						if ($assignedGross > 0.00001) {
							$hasAssignedAllocation = true;
							$assignedRemaining = $assignedGross;
						}
					}
				} catch (\Throwable $e) {
					$assignedGross = 0.0;
					$assignedCharge = 0.0;
					$assignedPayable = 0.0;
					$assignedRemaining = 0.0;
					$hasAssignedAllocation = false;
				}
				if ($hasAssignedAllocation && $assignedRemaining <= 0.00001) {
					$cashpickup->errors['amount_requested'] = 'This assigned payout has already been fully used.';
					set_value('errors', array_merge($cashpickup->errors, $req->upload_errors));
					return;
				}

				$payType = strtolower(trim((string)($postdata['pay_type'] ?? '')));
				if ($hasAssignedAllocation) {
					$payType = 'all';
					$postdata['pay_type'] = 'all';
				}
				if ($payType === 'all') {
					$postdata['amount_requested'] = $hasAssignedAllocation ? min($remaining, $assignedRemaining) : $remaining;
				}
				$postdata['amount_requested'] = $toFloat($postdata['amount_requested'] ?? 0);
				if ($postdata['amount_requested'] > $remaining) {
					$postdata['amount_requested'] = $remaining;
				}
				if ($hasAssignedAllocation && $postdata['amount_requested'] > $assignedRemaining + 0.00001) {
					$postdata['amount_requested'] = $assignedRemaining;
				}
				$postdata['pickup_charge'] = $toFloat($postdata['pickup_charge'] ?? 0);
				if ($hasAssignedAllocation) {
					$postdata['pickup_charge'] = min((float)$assignedCharge, (float)$postdata['amount_requested']);
				}
				$postdata['amount_paid_out'] = max(0, $postdata['amount_requested'] - (float)($postdata['pickup_charge'] ?? 0));
				$postdata['amount_involved'] = (float)($postdata['amount_requested'] ?? 0);
				$postdata['amount_transferred'] = (float)($postdata['amount_requested'] ?? 0);
			}
		}
	} catch (\Throwable $e) {
		// ignore lookup failures during edit
	}
}

if ($side === 'receive') {
	// Pay-only fields
	$postdata['payment_method'] = '';
	$postdata['pickup_charge'] = 0;
	$postdata['amount_paid_out'] = 0;
	$postdata['amount_transferred'] = 0;
	$postdata['receive_id'] = 0;
	$postdata['pay_type'] = '';
	$postdata['amount_requested'] = 0;
	$postdata['confirmed_by'] = '';
}

if ($side === 'pay') {
	if ($postdata['amount_paid_out'] <= 0 && $postdata['amount_involved'] > 0) {
		$postdata['amount_paid_out'] = max(0, $postdata['amount_involved'] - $postdata['pickup_charge']);
	}
	if ($postdata['amount_transferred'] <= 0 && $postdata['amount_involved'] > 0) {
	$postdata['amount_transferred'] = $postdata['amount_involved'];
	}
}

// Prevent any spoofed date values; date is system-generated
unset($postdata['date']);

$csrf = csrf_verify($postdata);
if ($csrf && $cashpickup->validate_update($postdata))
{
	if ($canEditCashpickup)
	{
		// Upsert customer ledger and link transactions (only after validation passes)
		try {
			$ledgerDb = new \Core\Database;
			$customerPayload = [
				'full_name' => (string)($postdata['sender_details'] ?? ''),
				'phone_number' => (string)($postdata['phone_number'] ?? ''),
				'id_type' => (string)($postdata['customer_id_type'] ?? ''),
				'id_number' => (string)($postdata['customer_id_number'] ?? ''),
				'location' => (string)($postdata['customer_location'] ?? ''),
			];
			$actorId = (int)($postdata['updated_by'] ?? $userId);
			$ledgerRow = \CashpickupLedger\upsert_customer($customerPayload, $actorId, $ledgerDb);
			if (!empty($ledgerRow['id'])) {
				$postdata['customer_id'] = (int)$ledgerRow['id'];
				$postdata['customer_id_type'] = (string)($ledgerRow['id_type'] ?? $postdata['customer_id_type'] ?? '');
				$postdata['customer_id_number'] = (string)($ledgerRow['id_number'] ?? $postdata['customer_id_number'] ?? '');
				$postdata['customer_location'] = (string)($ledgerRow['location'] ?? $postdata['customer_location'] ?? '');
				\CashpickupLedger\link_customer_transactions($ledgerDb, (int)$ledgerRow['id'], $customerPayload);
			}
		} catch (\Throwable $e) {
			// do not block transaction
		}

		$session = get_session();
		$user_id = (int)($session->id ?? 0);
		$postdata['updated_by'] = $user_id;
		$postdata['date_updated'] = date("Y-m-d H:i:s");
		unset($postdata['date']);
		$postdata['approved_by'] = $user_id;
		$postdata['approved_at'] = date('Y-m-d H:i:s');
		if ($side === 'pay' && ((int)($postdata['assigned_user_id'] ?? 0)) <= 0) {
			$postdata['assigned_user_id'] = (int)$user_id;
		}

		$cashpickup->update($row->id, $postdata);

		$chargeValue = (float)($postdata['pickup_charge'] ?? ($row->pickup_charge ?? 0));
		try {
			$commissionDb = new \Core\Database;
			$sideLabel = strtoupper((string)$side);
			$methodLabel = $side === 'pay'
				? trim((string)($postdata['payment_method'] ?? ($row->payment_method ?? '')))
				: trim((string)($postdata['source'] ?? ($row->source ?? '')));
			$chargeNetwork = $side === 'pay'
				? trim((string)($postdata['payout_channel'] ?? ($row->payout_channel ?? ($postdata['payment_method'] ?? ($row->payment_method ?? '')))))
				: trim((string)($postdata['source'] ?? ($row->source ?? '')));
			$commissionDescription = 'CASHPICKUP CHARGE | ' . $sideLabel . ' | ' . $methodLabel . ' | ID ' . ($row->id ?? '');
			$commissionDb->query(
				"DELETE FROM commissions WHERE commission_type = :commission_type AND description LIKE :needle",
				[
					'commission_type' => 'CASHPICKUP CHARGE',
					'needle' => '%| ID ' . ($row->id ?? ''),
				]
			);
			if ($chargeValue > 0) {
				$commissionInsert = [
					'date_received' => $postdata['date_updated'] ?? date('Y-m-d H:i:s'),
					'colletion_period' => 'INSTANT',
					'network' => $chargeNetwork !== '' ? $chargeNetwork : null,
					'commission_type' => 'CASHPICKUP CHARGE',
					'description' => $commissionDescription,
					'pay_to' => $user_id,
					'receiving_wallet' => null,
					'amount' => $chargeValue,
					'transaction_status' => $postdata['transaction_status'] ?? ($postdata['approval_status'] ?? ($row->transaction_status ?? 'Approved')),
					'created_by' => $user_id,
					'updated_by' => null,
					'disabled' => 0,
					'deleted' => 0,
					'date_created' => $postdata['date_updated'] ?? date('Y-m-d H:i:s'),
					'date_updated' => null,
					'date_deleted' => null,
				];
				$commissionSql = "INSERT INTO commissions (".implode(',', array_keys($commissionInsert)).") VALUES (:".implode(',:', array_keys($commissionInsert)).")";
				$commissionDb->query($commissionSql, $commissionInsert);
			}
		} catch (\Throwable $e) {
			error_log('CASHPICKUP CHARGE COMMISSION UPSERT FAILED (EDIT): ' . $e->getMessage());
		}

		// Sync cash IN for receive-side transactions
		if ($side === 'receive') {
			try {
				$bridgePath = realpath(__DIR__ . '/../../backoffice/helpers/cashdrawer-bridge.php');
				if ($bridgePath && file_exists($bridgePath)) {
					require_once $bridgePath;
					\BackOfficeCashdrawer\init();
					$denos = \BackOfficeCashdrawer\normalizeDenominations($postdata['denos_json'] ?? '[]', (float)($postdata['amount_involved'] ?? 0), 'GHS');
					\BackOfficeCashdrawer\syncCashMovement((int)$user_id, $denos, (float)($postdata['amount_involved'] ?? 0), (string)($postdata['transaction_status'] ?? 'Approved'), (int)($row->id ?? 0), 'in');
				}
			} catch (\Throwable $e) {
				// fail silently
			}
		}

		// Sync shared bank channels for receive-side transactions (edit reversal + apply)
		if ($side === 'receive') {
			try {
				$applySharedBank = function(string $bankName, float $amount, string $direction, int $transId, string $transStatus, int $actorUserId): void {
					$bankName = trim((string)$bankName);
					if ($bankName === '' || $amount <= 0) {
						return;
					}
					$agencyHelper = realpath(__DIR__ . '/../../agencybanking/helpers/cashdrawer-sync.php');
					if ($agencyHelper && file_exists($agencyHelper)) {
						require_once $agencyHelper;
					}
					if (!function_exists('Agencybankings\\agency_refresh_ecash_channel')) {
						return;
					}
					if (function_exists('Agencybankings\\agency_canonicalize_drawer_bank_name')) {
						$bankName = \Agencybankings\agency_canonicalize_drawer_bank_name($bankName);
					}
					$network = strtoupper(preg_replace('/[^A-Z0-9]+/i', '_', $bankName));
					$network = trim((string)$network, '_');
					if ($network === '') {
						return;
					}
					$baseChannelKey = 'ECASH_' . $network;
					$sharedOpening = 0.0;
					if (function_exists('Agencybankings\\agency_get_shared_ecash_balance')) {
						$sharedOpening = (float)\Agencybankings\agency_get_shared_ecash_balance($baseChannelKey, $bankName);
					}

					$appliedTargets = [];
					$markApplied = function(int $dId, int $uId) use (&$appliedTargets): void {
						$appliedTargets[$dId . ':' . $uId] = true;
					};
					$hasApplied = function(int $dId, int $uId) use (&$appliedTargets): bool {
						return !empty($appliedTargets[$dId . ':' . $uId]);
					};
					$applyToDrawer = function(int $drawerId, int $drawerUserId) use ($baseChannelKey, $bankName, $amount, $direction, $transId, $transStatus, $sharedOpening): void {
						if ($drawerId <= 0 || $drawerUserId <= 0) {
							return;
						}
						$writeKey = $baseChannelKey;
						if (function_exists('Agencybankings\\agency_find_existing_ecash_channel_key')) {
							$existingKey = \Agencybankings\agency_find_existing_ecash_channel_key($drawerId, $drawerUserId, $writeKey);
							if (!empty($existingKey)) {
								$writeKey = (string)$existingKey;
							}
						}
						if ($writeKey === $baseChannelKey && function_exists('Agencybankings\\agency_resolve_ecash_channel_key')) {
							$resolved = \Agencybankings\agency_resolve_ecash_channel_key($drawerId, $drawerUserId, $bankName);
							if (!empty($resolved)) {
								$writeKey = (string)$resolved;
							}
						}
						$seedOpening = null;
						if (function_exists('Agencybankings\\agency_drawer_has_ecash_channel')) {
							$hasChannel = \Agencybankings\agency_drawer_has_ecash_channel($drawerId, $drawerUserId, $writeKey);
							if (!$hasChannel && $sharedOpening > 0) {
								$seedOpening = $sharedOpening;
							}
						}
						\Agencybankings\agency_refresh_ecash_channel($drawerId, $drawerUserId, $writeKey, $direction, $amount, $transId, $transStatus, $seedOpening);
					};

					$drawerId = 0;
					$drawerUserId = 0;
					if (function_exists('Agencybankings\\agency_find_active_drawer')) {
						$ctx = \Agencybankings\agency_find_active_drawer((int)$actorUserId);
						if (!empty($ctx)) {
							$drawerId = (int)($ctx['drawer_id'] ?? 0);
							$drawerUserId = (int)($ctx['drawer_user_id'] ?? 0);
						}
					}
					if ($drawerId <= 0 || $drawerUserId <= 0) {
						try {
							$dbOwner = new \Core\Database;
							$today = date('Y-m-d');
							$row = $dbOwner->query("SELECT id, user_id FROM cashdrawers WHERE user_id = :user_id AND DATE(date_created) = :today ORDER BY id DESC LIMIT 1", ['user_id' => $actorUserId, 'today' => $today]);
							if (empty($row)) {
								$row = $dbOwner->query("SELECT id, user_id FROM cashdrawers WHERE user_id = :user_id ORDER BY date_created DESC, id DESC LIMIT 1", ['user_id' => $actorUserId]);
							}
							if (!empty($row)) {
								$drawerId = (int)($row[0]->id ?? 0);
								$drawerUserId = (int)($row[0]->user_id ?? 0);
							}
						} catch (\Throwable $e) {}
					}
					if ($drawerId > 0 && $drawerUserId > 0) {
						$applyToDrawer($drawerId, $drawerUserId);
						$markApplied($drawerId, $drawerUserId);
					}

					if (function_exists('Agencybankings\\agency_fetch_admin_manager_drawers_today')) {
						$targets = \Agencybankings\agency_fetch_admin_manager_drawers_today();
						if (!empty($targets)) {
							foreach ($targets as $ctx) {
								$targetDrawerId = (int)($ctx['drawer_id'] ?? 0);
								$targetDrawerUserId = (int)($ctx['drawer_user_id'] ?? 0);
								if ($targetDrawerId <= 0 || $targetDrawerUserId <= 0) {
									continue;
								}
								if ($hasApplied($targetDrawerId, $targetDrawerUserId)) {
									continue;
								}
								$applyToDrawer($targetDrawerId, $targetDrawerUserId);
								$markApplied($targetDrawerId, $targetDrawerUserId);
							}
						}
					}
				};

				if ($originalSide === 'receive' && $originalSource === 'bank' && $originalAmount > 0 && $originalBank !== '') {
					$applySharedBank($originalBank, $originalAmount, 'out', (int)($row->id ?? 0), 'Reversed', (int)$user_id);
				}

				$updatedSource = strtolower(trim((string)($postdata['source'] ?? $row->source ?? '')));
				$updatedBank = trim((string)($postdata['account_paid_to'] ?? $row->account_paid_to ?? ''));
				$updatedAmount = (float)($postdata['amount_involved'] ?? $row->amount_involved ?? 0);
				if ($updatedSource === 'bank' && $updatedAmount > 0 && $updatedBank !== '') {
					$applySharedBank($updatedBank, $updatedAmount, 'in', (int)($row->id ?? 0), (string)($postdata['transaction_status'] ?? 'Approved'), (int)$user_id);
				}
			} catch (\Throwable $e) {
				// fail silently
			}
		}

		message_success("Cash pickup updated successfully!");
		redirect($admin_route.'/'.$plugin_route);
	}
}

if(!$csrf)
	$cashpickup->errors['transaction_side'] = "Form expired!";

set_value('errors',$cashpickup->errors);

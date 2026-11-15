<?php if(!user_can('view_cashpickup')): ?>
	<div class="alert alert-danger text-center">Access denied.</div>
<?php else: ?>

<?php if(empty($row)): ?>
	<div class="alert alert-warning text-center">Record not found.</div>
<?php else: ?>

<?php
	$side = strtolower(trim((string)($row->transaction_side ?? '')));
	$isCreditPayout = $side === 'receive' && strtolower(trim((string)($row->credit_mode ?? ''))) === 'credit';
	$badgeClass = $side === 'receive' ? 'bg-success' : ($side === 'pay' ? 'bg-warning text-dark' : 'bg-secondary');
	$isModal = ((string)($_GET['modal'] ?? '') === '1');
	$approvalStatus = strtolower(trim((string)($row->approval_status ?? 'pending')));
	if ($approvalStatus === '') $approvalStatus = 'pending';
	$cancelStatuses = $approvalStatus . ' ' . strtolower(trim((string)($row->transaction_status ?? '')));
	$canCancelCashpickup = !$isModal && user_can('is_admin')
		&& preg_match('/denied|deny|rejected|reject|declined|decline|cancelled|canceled/i', $cancelStatuses) !== 1;
	$displayApprovalStatus = $approvalStatus;
	$displayApprovalText = ucfirst($approvalStatus);
	$approvedByName = '';
	$denosRaw = $row->denos_json ?? '';
	$denos = [];
	if (is_string($denosRaw) && trim($denosRaw) !== '') {
		$tmp = json_decode($denosRaw, true);
		if (is_array($tmp)) {
			$denos = $tmp;
		}
	}
	$session = get_session();
	$user_id = (int)($session->id ?? 0);
	require_once plugin_path('helpers/customer-ledger.php');
	$transferHelperPath = realpath(__DIR__ . '/../../helpers/transfer-allocation.php');
	if ($transferHelperPath && file_exists($transferHelperPath)) {
		require_once $transferHelperPath;
	}
	$isGeneralManager = false;
	$isOperationsManager = false;
	$roleNames = [];
	try {
		$dbRole = new \Core\Database;
		$roleRows = $dbRole->query(
			"SELECT LOWER(TRIM(ur.role)) AS role_name
			 FROM user_roles_map urm
			 JOIN user_roles ur ON ur.id = urm.role_id
			 WHERE urm.disabled = 0
			 AND urm.user_id = :uid",
			['uid' => $user_id]
		) ?: [];
		if (is_array($roleRows)) {
			foreach ($roleRows as $roleRow) {
				$roleName = strtolower(trim((string)($roleRow->role_name ?? '')));
				if ($roleName !== '') {
					$roleNames[] = $roleName;
				}
			}
		}
	} catch (\Throwable $e) {}
	$isGeneralManager = !empty(array_intersect($roleNames, ['general manager', 'general_manager', 'generalmanager', 'gm', 'is_general_manager']));
	$isOperationsManager = !empty(array_intersect($roleNames, ['operations manager', 'operations_manager', 'operationsmanager', 'ops manager', 'ops_manager', 'opsmanager', 'is_operations_manager']));
	$canApproveDeny = (user_can('is_admin') || $isGeneralManager);
	$canEditCashpickup = (user_can('is_admin') || user_can('is_manager'));
	$customerLedgerId = (int)($row->customer_id ?? 0);
	if ($side === 'receive' && !$isCreditPayout && $customerLedgerId <= 0) {
		$customerLookupDb = new \Core\Database;
		$customerLedgerId = \CashpickupLedger\find_customer_id_for_cashpickup($customerLookupDb, $row);
	}
	$isPooledCustomerAssignment = $side === 'receive' && !$isCreditPayout && $customerLedgerId > 0;
	$backUrl = ROOT . '/' . $admin_route . '/' . $plugin_route;
	if ((user_can('is_admin') || $isGeneralManager || $isOperationsManager) && $customerLedgerId > 0) {
		$backUrl = ROOT . '/' . $admin_route . '/' . $plugin_route . '/customer/' . $customerLedgerId;
	}
	$csrfField = '';
	if(!$isModal){
		$csrfField = csrf();
	}
	$cancelCsrfField = csrf();
	$denosTotal = 0.0;
	foreach ($denos as $d) {
		$denosTotal += (float)($d['volume'] ?? 0);
	}

	$payouts = [];
	$alreadyPaid = 0.0;
	$pendingReserved = 0.0;
	$pendingParts = 0;
	$totalPaidOut = 0.0;
	$totalCharge = 0.0;
	$totalTransferred = 0.0;
	$remaining_balance = 0.0;
	$overage = 0.0;
	$fullyPaid = false;
	$canTransfer = false;
	$eligibleAgents = [];
	$assignedName = '';
	$createdByName = '';
	$originalSourceLabel = '';
	$transferredAsLabel = '';
	$alreadyTransferredToAgents = 0.0;
	$remaining_transferable = 0.0;
	$assignedPayoutGross = 0.0;
	$assignedPayoutCharge = 0.0;
	$assignedPayoutPayable = 0.0;
	$assignedPayoutRemaining = 0.0;
	$currentUserAllocationRemaining = 0.0;
	$receivedHistory = [];
	$historyReceiveIds = [];
	$transferHistory = [];
	$activeTransferSources = [];
	$photoItems = [];
	$requestPath = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
	$requestBasePath = '';
	$adminRouteForPaths = trim((string)($admin_route ?? 'admin'), '/');
	if ($requestPath !== '' && $adminRouteForPaths !== '') {
		$marker = '/' . $adminRouteForPaths . '/';
		$markerPos = stripos($requestPath, $marker);
		if ($markerPos !== false) {
			$requestBasePath = rtrim(substr($requestPath, 0, $markerPos), '/');
		}
	}
	$rootPath = trim((string)parse_url((string)ROOT, PHP_URL_PATH), '/');
	$rootBasePath = $rootPath !== '' ? '/' . $rootPath : '';
	$basePath = $requestBasePath !== '' ? $requestBasePath : $rootBasePath;
	$buildPhotoUrl = function(string $path) use ($basePath): string {
		$normalized = str_replace('\\', '/', trim($path));
		$normalized = ltrim($normalized, '/');
		if ($normalized === '') {
			return '';
		}

		if ($basePath !== '') {
			$baseTrimmed = ltrim($basePath, '/');
			if (strpos($normalized, $baseTrimmed . '/') === 0) {
				$normalized = ltrim(substr($normalized, strlen($baseTrimmed)), '/');
			}
		}

		$segments = array_values(array_filter(explode('/', $normalized), static function($segment) {
			return $segment !== '';
		}));
		if (empty($segments)) {
			return '';
		}

		$encodedSegments = array_map(static function($segment) {
			return rawurlencode(rawurldecode((string)$segment));
		}, $segments);

		return ($basePath !== '' ? $basePath : '') . '/' . implode('/', $encodedSegments);
	};
	$imgUrl = function($path) use ($buildPhotoUrl) {
		$p = trim((string)($path ?? ''));
		if ($p === '') return '';
		if (preg_match('/^https?:\/\//i', $p)) {
			$urlPath = parse_url($p, PHP_URL_PATH);
			if (is_string($urlPath) && $urlPath !== '') {
				$uploadsPos = stripos(str_replace('\\', '/', $urlPath), '/uploads/');
				if ($uploadsPos !== false) {
					$p = ltrim(substr(str_replace('\\', '/', $urlPath), $uploadsPos + 1), '/');
				} else {
					return $p;
				}
			} else {
				return $p;
			}
		}
		$p = str_replace('\\', '/', $p);
		$p = ltrim($p, '/');
		$uploadsPos = stripos($p, 'uploads/');
		if ($uploadsPos !== false) {
			$p = substr($p, $uploadsPos);
		}
		if (preg_match('/^[a-zA-Z]:\//', $p)) {
			$uploadsPos2 = stripos($p, '/uploads/');
			if ($uploadsPos2 !== false) {
				$p = ltrim(substr($p, $uploadsPos2 + 1), '/');
			}
		}
		return $buildPhotoUrl($p);
	};
	$appendPhotoItems = function(array &$items, $photoRow, array $onlyTypes = []) {
		if (!is_object($photoRow) && !is_array($photoRow)) {
			return;
		}

		$getValue = function($key) use ($photoRow) {
			if (is_object($photoRow)) {
				return $photoRow->{$key} ?? null;
			}
			return $photoRow[$key] ?? null;
		};

		$recordId = (int)($getValue('id') ?? 0);
		$dateCreated = trim((string)($getValue('date_created') ?? ''));
		$rowSide = strtolower(trim((string)($getValue('transaction_side') ?? '')));
		$paymentMethod = strtolower(trim((string)($getValue('payment_method') ?? '')));
		$idPhotoLabel = ($rowSide === 'pay' && $paymentMethod === 'transfer') ? 'Transfer Receipt' : 'ID Card Photo';
		$push = function(string $type, string $label, $path) use (&$items, $recordId, $dateCreated, $onlyTypes) {
			if (!empty($onlyTypes) && !in_array($type, $onlyTypes, true)) {
				return;
			}

			$rawPath = trim((string)($path ?? ''));
			if ($rawPath === '') {
				return;
			}

			$pathKey = strtolower(str_replace('\\', '/', $rawPath));
			$key = $type . '|' . $pathKey;
			if (isset($items[$key])) {
				return;
			}

			$items[$key] = [
				'type' => $type,
				'label' => $label,
				'path' => $rawPath,
				'record_id' => $recordId,
				'date_created' => $dateCreated,
			];
		};

		$push('idcard_photo', $idPhotoLabel, $getValue('idcard_photo'));
		$push('receive_proof_photo', 'Cash received proof photo', $getValue('receive_proof_photo'));
		$push('customer_photo', 'Customer Photo', $getValue('customer_photo'));
	};
	if ($side === 'receive') {
		$appendPhotoItems($photoItems, $row, ['idcard_photo', 'receive_proof_photo']);
	} else {
		$appendPhotoItems($photoItems, $row, ['idcard_photo', 'customer_photo']);
	}
	try {
		$dbP = $dbP ?? new \Core\Database;

		$createdByVal = (string)($row->created_by ?? '');
		$createdById = (int)$createdByVal;
		if ($createdById > 0) {
			$cbRow = $dbP->query("SELECT first_name, last_name FROM users WHERE deleted = 0 AND id = :id LIMIT 1", ['id' => $createdById]);
			if(!empty($cbRow)){
				$createdByName = trim(($cbRow[0]->first_name ?? '').' '.($cbRow[0]->last_name ?? ''));
			}
		}
		if ($createdByName === '' && $createdByVal !== '') {
			$createdByName = $createdByVal;
		}

		$assignedId = (int)($row->assigned_user_id ?? 0);
		if($assignedId > 0){
			$assignedRow = $dbP->query("SELECT first_name, last_name FROM users WHERE deleted = 0 AND id = :id LIMIT 1", ['id'=>$assignedId]);
			if(!empty($assignedRow)){
				$assignedName = trim(($assignedRow[0]->first_name ?? '').' '.($assignedRow[0]->last_name ?? ''));
			}
		}
		$approvedById = (int)($row->approved_by ?? 0);
		if ($approvedById > 0) {
			$abRow = $dbP->query("SELECT first_name, last_name FROM users WHERE deleted = 0 AND id = :id LIMIT 1", ['id' => $approvedById]);
			if(!empty($abRow)){
			$approvedByName = trim(($abRow[0]->first_name ?? '').' '.($abRow[0]->last_name ?? ''));
			}
		}
	} catch (\Throwable $e) {}
	if ($side === 'pay') {
		$pm = strtolower(trim((string)($row->payment_method ?? '')));
		if ($pm === 'cash') {
			$transferredAsLabel = 'Cash';
		} elseif ($pm === 'transfer') {
			$pc = strtoupper(trim((string)($row->payout_channel ?? '')));
			if ($pc !== '') {
				$transferredAsLabel = 'Transfer - ' . $pc;
			} else {
				$transferredAsLabel = 'Transfer';
			}
		}
	}
	if ($side === 'receive') {
		try {
			$dbP = $dbP ?? new \Core\Database;
			// Credit payouts can stay re-assignable while awaiting actual payout completion.
			$isPrivileged = (user_can('is_manager') || user_can('is_admin'));
			$canTransfer = $isPrivileged && ($approvalStatus === 'approved' || $isCreditPayout);

			$src = strtolower(trim((string)($row->source ?? '')));
			if ($src === 'bank') {
				$bank = trim((string)($row->account_paid_to ?? ''));
				$originalSourceLabel = $bank !== '' ? ('Bank - ' . $bank) : 'Bank';
			} elseif ($src !== '') {
				$originalSourceLabel = strtoupper($src);
			}

			$tMode = strtolower(trim((string)($row->transfer_mode ?? '')));
			$tChan = strtolower(trim((string)($row->transfer_channel ?? '')));
			$tBank = trim((string)($row->transfer_bank ?? ''));
			if ($tMode === 'cash' || $tChan === 'cash') {
				$transferredAsLabel = 'Cash';
			} elseif ($tChan === 'bank') {
				$transferredAsLabel = $tBank !== '' ? ('Bank - ' . $tBank) : 'Bank';
			} elseif ($tChan !== '') {
				$transferredAsLabel = strtoupper($tChan);
			}
			$trackingId = trim((string)($row->tracking_id ?? ''));
			$receivedWhere = 'm.id = :rid';
			$receivedParams = ['rid' => (int)($row->id ?? 0)];
			if ($trackingId !== '') {
				$receivedWhere = "(m.id = :rid OR LOWER(TRIM(COALESCE(m.tracking_id,''))) = :tracking_id)";
				$receivedParams['tracking_id'] = strtolower($trackingId);
			}
			$receivedHistory = $dbP->query(
				"SELECT m.id, m.transaction_side, m.payment_method, m.date_created, m.source, m.account_paid_to, m.amount_involved, m.approval_status, m.transaction_status, m.created_by,
				        m.idcard_photo, m.customer_photo, m.receive_proof_photo,
				        u.first_name AS created_first_name, u.last_name AS created_last_name
				 FROM cashpickups m
				 LEFT JOIN users u ON u.id = m.created_by
				 WHERE (m.deleted IS NULL OR m.deleted = 0)
				 AND LOWER(m.transaction_side) = 'receive'
				 AND {$receivedWhere}
				 ORDER BY m.date_created ASC, m.id ASC",
				$receivedParams
			) ?? [];
			if (!is_array($receivedHistory)) {
				$receivedHistory = [];
			}
			foreach ($receivedHistory as $rx) {
				$appendPhotoItems($photoItems, $rx, ['idcard_photo', 'receive_proof_photo']);
				$rxId = (int)($rx->id ?? 0);
				if ($rxId > 0) {
					$historyReceiveIds[$rxId] = $rxId;
				}
			}
			if (empty($historyReceiveIds) && (int)($row->id ?? 0) > 0) {
				$historyReceiveIds[(int)$row->id] = (int)$row->id;
			}
			$receiveHasIdCardPhoto = false;
			foreach ($photoItems as $photoItem) {
				if (($photoItem['type'] ?? '') === 'idcard_photo') {
					$receiveHasIdCardPhoto = true;
					break;
				}
			}
			if($canTransfer){
				$eligibleAgents = $dbP->query(
					"SELECT u.id, u.first_name, u.last_name
					 FROM users u
					 JOIN (
						SELECT DISTINCT a.user_id
						FROM attendance a
						WHERE a.deleted = 0
						AND a.date = CURDATE()
						AND UPPER(a.remarks) IN ('PRESENT','LATE')
					 ) present_today ON present_today.user_id = u.id
					 LEFT JOIN (
						SELECT DISTINCT urm.user_id
						FROM user_roles_map urm
						JOIN user_roles ur ON ur.id = urm.role_id
						WHERE urm.disabled = 0
						AND LOWER(ur.role) IN (
							'manager','admin','is_manager','is_admin',
							'general manager','general_manager','generalmanager','gm','is_general_manager'
						)
					 ) blocked_roles ON blocked_roles.user_id = u.id
					 WHERE u.deleted = 0
					 AND u.id != :actor_id
					 AND blocked_roles.user_id IS NULL
					 ORDER BY u.first_name ASC"
				, ['actor_id' => (int)($session->id ?? 0)]) ?? [];
			}
			$payoutMatchSql = 'receive_id = :rid';
			$payoutMatchParams = ['rid' => (int)($row->id ?? 0)];
			if ($trackingId !== '') {
				$payoutMatchSql = "(receive_id = :rid OR LOWER(TRIM(COALESCE(tracking_id,''))) = :tracking_id)";
				$payoutMatchParams['tracking_id'] = strtolower($trackingId);
			}
			$transferIds = array_values(array_unique(array_map('intval', array_values($historyReceiveIds))));
			if (empty($transferIds) && (int)($row->id ?? 0) > 0) {
				$transferIds = [(int)$row->id];
			}
			$transferMatchParams = [];
			$transferPlaceholders = [];
			foreach ($transferIds as $idx => $rxId) {
				if ($rxId <= 0) {
					continue;
				}
				$key = 'trid' . $idx;
				$transferPlaceholders[] = ':' . $key;
				$transferMatchParams[$key] = $rxId;
			}
			if (empty($transferPlaceholders)) {
				$transferPlaceholders[] = ':trid0';
				$transferMatchParams['trid0'] = (int)($row->id ?? 0);
			}
			$transferMatchSql = 'receive_id IN (' . implode(',', $transferPlaceholders) . ')';
			$transferMatchSqlAlias = 't.receive_id IN (' . implode(',', $transferPlaceholders) . ')';
			$paidGrossExpr = "(COALESCE(amount_paid_out,0) + COALESCE(pickup_charge,0))";
			$payouts = $dbP->query(
				"SELECT id, date_created, amount_requested, pickup_charge, amount_paid_out, amount_transferred, payment_method, confirmed_by, approval_status, transaction_status, denial_reason
				 FROM cashpickups
				 WHERE (deleted IS NULL OR deleted = 0)
				 AND LOWER(transaction_side) = 'pay'
				 AND {$payoutMatchSql}
				 ORDER BY date_created DESC, id DESC",
				$payoutMatchParams
			) ?? [];
			if (!is_array($payouts)) {
				$payouts = [];
			}
			if (!$receiveHasIdCardPhoto) {
				$fallbackPayRows = $dbP->query(
					"SELECT id, transaction_side, payment_method, date_created, idcard_photo
					 FROM cashpickups
					 WHERE (deleted IS NULL OR deleted = 0)
					 AND LOWER(transaction_side) = 'pay'
					 AND COALESCE(idcard_photo,'') <> ''
					 AND {$payoutMatchSql}
					 ORDER BY
						CASE
							WHEN LOWER(COALESCE(approval_status,'')) = 'approved' THEN 0
							WHEN LOWER(COALESCE(approval_status,'')) = 'pending' THEN 1
							ELSE 2
						END,
						date_created DESC,
						id DESC
					 LIMIT 1",
					$payoutMatchParams
				) ?? [];
				if (!empty($fallbackPayRows)) {
					$appendPhotoItems($photoItems, $fallbackPayRows[0], ['idcard_photo']);
				}
			}
			$alreadyPaid = 0.0;
			$pendingReserved = 0.0;
			$pendingParts = 0;
			$totalCharge = 0.0;
			$totalPaidOut = 0.0;
			$totalTransferred = 0.0;
			foreach ($payouts as $payoutRow) {
				$pApproval = strtolower(trim((string)($payoutRow->approval_status ?? '')));
				$pTxStatus = strtolower(trim((string)($payoutRow->transaction_status ?? '')));
				$pConfirmedBy = trim((string)($payoutRow->confirmed_by ?? ''));
				$pPaidOut = (float)($payoutRow->amount_paid_out ?? 0);
				$pGross = $pPaidOut + (float)($payoutRow->pickup_charge ?? 0);
				$isActivePayout = ($pApproval === '' || in_array($pApproval, ['approved', 'pending'], true))
					&& $pTxStatus === 'approved'
					&& $pConfirmedBy !== ''
					&& $pPaidOut > 0;
				if (!$isActivePayout) {
					continue;
				}
				$alreadyPaid += $pGross;
				$totalCharge += (float)($payoutRow->pickup_charge ?? 0);
				$totalPaidOut += $pPaidOut;
				$totalTransferred += (float)($payoutRow->amount_transferred ?? 0);
				if ($pApproval === 'pending') {
					$pendingReserved += $pGross;
					$pendingParts++;
				}
			}
			try {
				$hasLedger = $dbP->table_exists(['cashpickup_transfers']);
				if($hasLedger){
					$trSum = $dbP->query(
						"SELECT COALESCE(SUM(amount),0) AS transferred
						 FROM cashpickup_transfers
						 WHERE {$transferMatchSql}
						 AND (
							approval_status IS NULL
							OR approval_status = ''
							OR LOWER(approval_status) IN ('approved','pending')
						 )",
						$transferMatchParams
					);
					$alreadyTransferredToAgents = !empty($trSum) ? (float)($trSum[0]->transferred ?? 0) : 0.0;
				}else{
					$alreadyTransferredToAgents = (float)($row->amount_transferred ?? 0);
				}
			} catch (\Throwable $e) { $alreadyTransferredToAgents = (float)($row->amount_transferred ?? 0); }
			$totalReceivedForTracking = 0.0;
			foreach ((is_array($receivedHistory) ? $receivedHistory : []) as $rx) {
				$totalReceivedForTracking += (float)($rx->amount_involved ?? 0);
			}
			if ($totalReceivedForTracking <= 0.00001) {
				$totalReceivedForTracking = (float)($row->amount_involved ?? 0);
			}
			$rawRemaining = (float)$totalReceivedForTracking - $alreadyPaid;
			$remaining_balance = max(0, (float)$rawRemaining);
			$overage = max(0, (float)(-$rawRemaining));
			$activeTransferRemaining = 0.0;
			try {
				$activeTransferRemaining = function_exists('CashpickupTransferAllocation\\get_total_active_transfer_remaining')
					? \CashpickupTransferAllocation\get_total_active_transfer_remaining($dbP, (int)($row->id ?? 0))
					: (float)$alreadyTransferredToAgents;
			} catch (\Throwable $e) {
				$activeTransferRemaining = (float)$alreadyTransferredToAgents;
			}
			$remaining_transferable = max(0, (float)$remaining_balance - (float)$activeTransferRemaining);
			$fullyPaid = $remaining_balance <= 0.00001;
			if ($isCreditPayout && $approvalStatus !== 'denied' && !$fullyPaid) {
				$displayApprovalStatus = 'pending';
				$displayApprovalText = 'Pending payout';
			}
			if (function_exists('CashpickupTransferAllocation\\get_transfer_allocation')) {
				$allocation = \CashpickupTransferAllocation\get_transfer_allocation($dbP, (int)($row->id ?? 0), (int)$assignedId);
				$assignedPayoutGross = (float)($allocation['gross'] ?? 0);
				$assignedPayoutCharge = (float)($allocation['charge'] ?? 0);
				$assignedPayoutPayable = (float)($allocation['payable'] ?? 0);
				$assignedPayoutRemaining = $assignedPayoutGross > 0.00001
					? $assignedPayoutGross
					: $remaining_transferable;
			} else {
				$assignedPayoutRemaining = $remaining_transferable;
			}
			try {
					if (function_exists('CashpickupTransferAllocation\\ensure_cashpickup_transfer_schema')) {
						\CashpickupTransferAllocation\ensure_cashpickup_transfer_schema($dbP);
					}
					$hasLedger = $dbP->table_exists(['cashpickup_transfers']);
				if($hasLedger){
					$transferHistory = $dbP->query(
							"SELECT t.id, t.source_user_id, t.to_user_id, t.created_by, t.amount, t.transfer_charge, t.approval_status, t.transfer_mode, t.transfer_channel, t.transfer_bank, t.date_created, t.cancelled_by, t.date_cancelled,
							su.first_name AS source_first_name, su.last_name AS source_last_name,
								tu.first_name AS to_first_name, tu.last_name AS to_last_name,
								au.first_name AS assigner_first_name, au.last_name AS assigner_last_name,
								cu.first_name AS cancelled_first_name, cu.last_name AS cancelled_last_name
						 FROM cashpickup_transfers t
						 LEFT JOIN users su ON su.id = t.source_user_id
						 LEFT JOIN users tu ON tu.id = t.to_user_id
							 LEFT JOIN users au ON au.id = t.created_by
							 LEFT JOIN users cu ON cu.id = t.cancelled_by
						 WHERE {$transferMatchSqlAlias}
						 ORDER BY t.id DESC, t.date_created DESC",
						$transferMatchParams
					);
					if (!is_array($transferHistory)) {
						$transferHistory = [];
					}
					$activeTransferSources = [];
					foreach ($transferHistory as $tr) {
						$trStatus = strtolower(trim((string)($tr->approval_status ?? 'approved')));
						if ($trStatus !== '' && !in_array($trStatus, ['approved', 'pending'], true)) {
							continue;
						}
						$uid = (int)($tr->to_user_id ?? 0);
						if ($uid <= 0) {
							continue;
						}
						$uName = trim(((string)($tr->to_first_name ?? '')).' '.((string)($tr->to_last_name ?? '')));
						if ($uName === '') {
							$uName = 'User #' . $uid;
						}
						if (!isset($activeTransferSources[$uid])) {
							$activeTransferSources[$uid] = [
								'id' => $uid,
								'name' => $uName,
								'remaining' => 0.0,
							];
						}
					}
					$remainingUserIds = array_map('intval', array_keys($activeTransferSources));
					if ((int)$user_id > 0) {
						$remainingUserIds[] = (int)$user_id;
					}
					$remainingByUser = function_exists('CashpickupTransferAllocation\\get_transfer_remaining_amounts')
						? \CashpickupTransferAllocation\get_transfer_remaining_amounts($dbP, (int)($row->id ?? 0), $remainingUserIds)
						: [];
					$currentUserAllocationRemaining = (float)($remainingByUser[(int)$user_id] ?? 0);
					foreach ($activeTransferSources as $uid => &$srcInfo) {
						$srcInfo['remaining'] = (float)($remainingByUser[(int)$uid] ?? 0);
						if ($srcInfo['remaining'] <= 0.00001) {
							unset($activeTransferSources[$uid]);
						}
					}
					unset($srcInfo);
					$defaultSourceUserId = 0;
					$defaultSourceRemaining = (float)$remaining_transferable;
					if (!empty($activeTransferSources)) {
						if ($assignedId > 0 && isset($activeTransferSources[$assignedId])) {
							$defaultSourceUserId = $assignedId;
						} elseif ((float)$remaining_transferable <= 0.00001) {
							$firstSource = reset($activeTransferSources);
							if (is_array($firstSource) && !empty($firstSource['id'])) {
								$defaultSourceUserId = (int)$firstSource['id'];
							}
						}
						if ($defaultSourceUserId > 0 && isset($activeTransferSources[$defaultSourceUserId])) {
							$defaultSourceRemaining = (float)($activeTransferSources[$defaultSourceUserId]['remaining'] ?? 0);
						}
					}
				}
			} catch (\Throwable $e) { $transferHistory = []; $activeTransferSources = []; }
		} catch(\Throwable $e) {
			$payouts = [];
			$alreadyPaid = 0.0;
			$pendingReserved = 0.0;
			$pendingParts = 0;
			$totalPaidOut = 0.0;
			$totalCharge = 0.0;
			$totalTransferred = 0.0;
			$remaining_balance = (float)($row->amount_involved ?? 0);
			$overage = 0.0;
			$fullyPaid = false;
			$canTransfer = false;
			$eligibleAgents = [];
			$assignedName = '';
			$createdByName = '';
			$originalSourceLabel = '';
			$transferredAsLabel = '';
			$alreadyTransferredToAgents = 0.0;
			$remaining_transferable = 0.0;
			$transferHistory = [];
		}
	}
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
	<div>
		<div class="d-flex align-items-center gap-2">
			<h4 class="mb-0">Cash Pickup</h4>
			<span class="badge <?=$badgeClass?>"><?=esc(strtoupper((string)($row->transaction_side ?? '')))?></span>
		</div>
		<div class="text-muted small">#<?=esc($row->id)?> | <?=esc((string)($row->date_created ?? $row->date ?? ''))?></div>
	</div>
	<div class="d-flex gap-2">
	<?php if($canCancelCashpickup): ?>
		<button class="btn btn-outline-danger" type="button" data-cashpickup-cancel data-cancel-url="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/cancel/<?=esc((string)($row->id ?? 0))?>" data-transaction-id="<?=esc((string)($row->id ?? 0))?>">
			<i class="fa fa-ban me-1" aria-hidden="true"></i> Cancel transaction
		</button>
	<?php endif; ?>
	<?php if(!$isModal): ?>
		<a class="btn btn-outline-secondary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export?<?=http_build_query(['resource' => 'view', 'id' => (int)($row->id ?? 0), 'format' => 'pdf'])?>">PDF</a>
		<a class="btn btn-outline-success" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export?<?=http_build_query(['resource' => 'view', 'id' => (int)($row->id ?? 0), 'format' => 'xlsx'])?>">Excel</a>
		<a class="btn btn-outline-primary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export?<?=http_build_query(['resource' => 'view', 'id' => (int)($row->id ?? 0), 'format' => 'csv'])?>">CSV</a>
	<?php endif; ?>
	<a class="btn btn-outline-secondary" href="<?=esc($backUrl)?>"><i class="fa fa-chevron-left"></i> Back</a>
	<?php if(!$isModal && $canEditCashpickup && !in_array($approvalStatus, ['denied','reversed'], true) && !($side === 'receive' && $fullyPaid)): ?>
		<a class="btn btn-danger" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$row->id?>"><i class="fa fa-pen"></i> Edit</a>
	<?php endif; ?>
	</div>
</div>
<?php if($canCancelCashpickup): ?>
	<?php require plugin_path('views/admin/cancel-modal.php'); ?>
<?php endif; ?>

<div class="row g-3">
	<div class="col-lg-8">
		<div class="border rounded p-3 shadow">
			<div class="row g-3">
				<div class="col-md-4">
					<div class="text-muted small">Source</div>
					<div class="fw-semibold"><?=esc((string)($row->source ?? ''))?></div>
				</div>
				<div class="col-md-4">
					<div class="text-muted small">Customer number</div>
					<div class="fw-semibold"><?=esc((string)($row->phone_number ?? ''))?></div>
				</div>
				<div class="col-md-4">
					<div class="text-muted small">Tracking ID</div>
					<div class="fw-semibold"><?=esc((string)($row->tracking_id ?? ''))?></div>
				</div>

				<div class="col-md-8">
					<div class="text-muted small">Customer full name</div>
					<div class="fw-semibold"><?=esc((string)($row->sender_details ?? ''))?></div>
				</div>
				<div class="col-md-4">
					<div class="text-muted small">Customer ID type</div>
					<div class="fw-semibold"><?=esc((string)($row->customer_id_type ?? ''))?></div>
				</div>
				<div class="col-md-4">
					<div class="text-muted small">Customer ID number</div>
					<div class="fw-semibold"><?=esc((string)($row->customer_id_number ?? ''))?></div>
				</div>
				<div class="col-md-8">
					<div class="text-muted small">Customer location</div>
					<div class="fw-semibold"><?=esc((string)($row->customer_location ?? ''))?></div>
				</div>
				<div class="col-md-4">
					<div class="text-muted small">Payment method</div>
					<div class="fw-semibold"><?=esc((string)($row->payment_method ?? ''))?></div>
				</div>

				<?php if(!empty($row->pickup_person_idcard) || !empty($row->pickup_person_phone)): ?>
					<div class="col-12"><hr class="my-1"></div>
					<div class="col-md-4">
						<div class="text-muted small">Pick-up person ID card</div>
						<div class="fw-semibold"><?=esc((string)($row->pickup_person_idcard ?? ''))?></div>
					</div>
					<div class="col-md-4">
						<div class="text-muted small">Pick-up person phone</div>
						<div class="fw-semibold"><?=esc((string)($row->pickup_person_phone ?? ''))?></div>
					</div>
				<?php endif; ?>

				<div class="col-12"><hr class="my-1"></div>
				<div class="col-md-6">
					<div class="text-muted small">Notes</div>
					<div class="fw-semibold"><?=esc((string)($row->notes ?? ''))?></div>
				</div>
				<div class="col-md-6">
					<div class="text-muted small">Confirmed by</div>
					<div class="fw-semibold"><?=esc((string)($row->confirmed_by ?? ''))?></div>
				</div>
			</div>
		</div>

		<?php if($side === 'receive'): ?>
			<div class="border rounded p-3 mt-3 shadow" id="transfer">
				<div class="d-flex justify-content-between align-items-center">
					<div>
						<div class="text-muted">Remaining balance</div>
						<div class="fs-4 fw-bold">GHS <?=number_format((float)$remaining_balance, 2)?></div>
					</div>
					<div class="text-end">
						<div class="text-muted">Total paid (parts)</div>
						<div class="fw-semibold">GHS <?=number_format((float)$alreadyPaid, 2)?><?php if(is_array($payouts)): ?> <span class="text-muted">(<?=number_format((float)count($payouts))?>)</span><?php endif; ?></div>
						<?php if(!empty($pendingParts) && (float)$pendingReserved > 0.00001): ?>
							<div class="small text-warning">Pending reserved: GHS <?=number_format((float)$pendingReserved, 2)?> <span class="text-muted">(<?=number_format((float)$pendingParts)?>)</span></div>
						<?php endif; ?>
						<?php if((float)$overage > 0.00001): ?>
							<div class="small text-danger">Overage: GHS <?=number_format((float)$overage, 2)?></div>
						<?php endif; ?>
					</div>
				</div>
				<?php 
					// Admin/GM can always assign/reassign approved cashpickups; regular users need remaining transferable amount
					$isPrivileged = (user_can('is_manager') || user_can('is_admin'));
					$canShowAssign = !$isPooledCustomerAssignment && $canTransfer
						&& $approvalStatus !== 'denied' 
						&& ($isPrivileged || (float)$remaining_transferable > 0.00001 || !empty($activeTransferSources));
				?>
				<?php if($isPooledCustomerAssignment && $isPrivileged): ?>
					<hr class="my-3">
					<div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2 mb-0">
						<span><i class="fa fa-users me-1"></i> This customer's payout assignments use their aggregate account balance, not this individual deposit.</span>
						<a class="btn btn-sm btn-primary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer/<?=esc((string)$customerLedgerId)?>#withdraw-section">Manage assignments</a>
					</div>
				<?php endif; ?>
				<?php if($canShowAssign): ?>
					<hr class="my-3">
					<div class="fw-semibold mb-2">Assign</div>
					<div class="text-muted small mb-2">Only non-manager/admin users marked Present or Late today can be assigned.</div>
					<?php if((float)$remaining_transferable <= 0.00001 && !empty($activeTransferSources)): ?>
						<div class="alert alert-info py-2 small mb-2">No unassigned balance remains. Select a source allocation below to reassign existing balance.</div>
					<?php endif; ?>
					<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/transfer/<?=$row->id?>" class="row g-2 align-items-end">
						<input type="hidden" id="transfer_remaining_max" value="<?=esc((string)number_format((float)$remaining_transferable, 2, '.', ''))?>">
						<div class="col-12 col-md-3">
							<label class="form-label">Source allocation</label>
							<select name="source_user_id" id="source_user_id" class="form-select">
								<option value="0" data-remaining="<?=esc((string)number_format((float)$remaining_transferable, 2, '.', ''))?>" <?=((int)($defaultSourceUserId ?? 0) === 0 ? 'selected' : '')?>>New allocation / unassigned balance</option>
								<?php foreach(($activeTransferSources ?? []) as $src): ?>
									<option value="<?=esc((string)$src['id'])?>" data-remaining="<?=esc((string)number_format((float)($src['remaining'] ?? 0), 2, '.', ''))?>" <?=((int)($defaultSourceUserId ?? 0) === (int)($src['id'] ?? 0) ? 'selected' : '')?>>
										<?=esc((string)($src['name'] ?? 'User'))?> — remaining GH₵ <?=number_format((float)($src['remaining'] ?? 0), 2)?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-12 col-md-4">
							<label class="form-label">Assign to</label>
							<select name="assigned_user_id" class="form-select" required>
								<option value="">Select user</option>
								<?php foreach((is_array($eligibleAgents ?? null) ? $eligibleAgents : []) as $u): $un = trim(($u->first_name ?? '').' '.($u->last_name ?? '')); ?>
									<option value="<?=esc($u->id)?>" <?=((int)($defaultSourceUserId ?? 0) !== (int)($row->assigned_user_id ?? 0) && (string)($row->assigned_user_id ?? '') === (string)$u->id) ? 'selected' : ''?>><?=esc($un)?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-12 col-md-2">
							<label class="form-label">Assign type</label>
							<select name="assignment_type" id="assignment_type" class="form-select">
								<option value="full">Full</option>
								<option value="partial">Partial</option>
							</select>
						</div>
						<div class="col-12 col-md-2">
							<label class="form-label">Assign amount</label>
							<input name="transfer_amount" id="transfer_amount" value="<?=esc((string)number_format((float)($defaultSourceRemaining ?? $remaining_transferable), 2, '.', ''))?>" type="number" step="0.01" class="form-control" placeholder="0.00">
						</div>
						<div class="col-12 col-md-2" id="transfer_charge_wrap">
							<label class="form-label">Assign charge</label>
							<input name="transfer_charge" id="transfer_charge" value="0.00" type="number" step="0.01" class="form-control" placeholder="0.00">
						</div>
						<div class="col-12 col-md-2">
							<label class="form-label d-block">&nbsp;</label>
							<button class="btn btn-primary w-100" type="submit">Assign</button>
						</div>
						<div class="col-12">
							<div class="text-muted small d-flex flex-wrap gap-3">
								<span>Current: <?=esc($assignedName !== '' ? $assignedName : ((string)($row->assigned_user_id ?? '')))?></span>
								<span>
									<?php if($assignedPayoutGross > 0.00001): ?>
										Allocation: <strong>GH₵ <?=number_format((float)$assignedPayoutGross, 2)?></strong> / Charge: <strong>GH₵ <?=number_format((float)$assignedPayoutCharge, 2)?></strong> / Payable: <strong id="transfer_payable_preview">GH₵ <?=number_format((float)$assignedPayoutPayable, 2)?></strong>
									<?php else: ?>
										Net payable preview: <strong id="transfer_payable_preview">GH₵ <?=number_format((float)$remaining_transferable, 2)?></strong>
									<?php endif; ?>
								</span>
								<span class="text-primary">Source remaining: <strong id="transfer_source_remaining_preview">GH₵ <?=number_format((float)($defaultSourceRemaining ?? $remaining_transferable), 2)?></strong></span>
							</div>
						</div>
					</form>
					<script>
						(function(){
							const typeEl = document.getElementById('assignment_type');
							const sourceEl = document.getElementById('source_user_id');
							const amountEl = document.getElementById('transfer_amount');
							const chargeEl = document.getElementById('transfer_charge');
							const chargeWrap = document.getElementById('transfer_charge_wrap');
							const remainingMaxEl = document.getElementById('transfer_remaining_max');
							const payablePreview = document.getElementById('transfer_payable_preview');
							const sourcePreview = document.getElementById('transfer_source_remaining_preview');
							if(!typeEl || !amountEl || !chargeEl || !remainingMaxEl || !payablePreview) return;
							const remainingMax = parseFloat(remainingMaxEl.value || '0') || 0;

							const getSourceRemaining = () => {
								if (!sourceEl) return remainingMax;
								const opt = sourceEl.selectedOptions && sourceEl.selectedOptions.length ? sourceEl.selectedOptions[0] : null;
								const raw = opt ? opt.getAttribute('data-remaining') : '';
								const v = parseFloat(raw || '0') || 0;
								return Math.max(0, v);
							};

							const sync = () => {
								const mode = String(typeEl.value || 'full').toLowerCase();
								const sourceRemaining = getSourceRemaining();
								if (mode === 'full') {
									amountEl.readOnly = true;
									amountEl.value = sourceRemaining.toFixed(2);
								} else {
									amountEl.readOnly = false;
									if (!amountEl.value || Number.isNaN(parseFloat(amountEl.value))) {
										amountEl.value = sourceRemaining.toFixed(2);
									}
								}
								chargeEl.readOnly = false;
								if (!chargeEl.value || Number.isNaN(parseFloat(chargeEl.value))) {
									chargeEl.value = '0.00';
								}
								if (chargeWrap) chargeWrap.style.display = '';
								if (sourcePreview) {
									sourcePreview.textContent = 'GH₵ ' + sourceRemaining.toFixed(2);
								}
								const amt = parseFloat(amountEl.value || '0') || 0;
								const ch = parseFloat(chargeEl.value || '0') || 0;
								const payable = Math.max(0, amt - ch);
								payablePreview.textContent = 'GH₵ ' + payable.toFixed(2);
							};
							if (sourceEl) {
								sourceEl.addEventListener('change', () => {
									amountEl.value = '';
									sync();
								});
							}
							amountEl.addEventListener('focus', () => {
								if (!amountEl.readOnly) {
									try { amountEl.select(); } catch (e) {}
								}
							});
							chargeEl.addEventListener('focus', () => {
								if (!chargeEl.readOnly) {
									try { chargeEl.select(); } catch (e) {}
								}
							});
							typeEl.addEventListener('change', sync);
							amountEl.addEventListener('input', sync);
							chargeEl.addEventListener('input', sync);
							sync();
						})();
					</script>
				<?php endif; ?>

				<?php if(!empty($receivedHistory) && is_array($receivedHistory)): ?>
					<hr class="my-3">
					<div class="fw-semibold mb-2">Received history</div>
					<div class="table-responsive">
						<table class="table table-sm mb-0">
							<thead>
								<tr>
									<th>Date</th>
									<th>Source</th>
									<th class="text-end">Received</th>
									<th>Status</th>
									<th>Created by</th>
									<th class="text-end">View</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach($receivedHistory as $rx): ?>
									<?php
										$rxApproval = strtolower(trim((string)($rx->approval_status ?? 'pending')));
										if ($rxApproval === '') $rxApproval = 'pending';
										$rxDisplayApproval = ($isCreditPayout && (int)($rx->id ?? 0) === (int)($row->id ?? 0) && !in_array($rxApproval, ['denied','reversed'], true) && !$fullyPaid) ? 'pending' : $rxApproval;
										$rxSource = trim((string)($rx->source ?? ''));
										$rxBank = trim((string)($rx->account_paid_to ?? ''));
										if (strtolower($rxSource) === 'bank' && $rxBank !== '') {
											$rxSource .= ' - ' . $rxBank;
										}
										$rxCreatedBy = trim(((string)($rx->created_first_name ?? '')).' '.((string)($rx->created_last_name ?? '')));
										if ($rxCreatedBy === '') {
											$rxCreatedBy = (string)($rx->created_by ?? '');
										}
									?>
									<tr>
										<td><?=esc((string)($rx->date_created ?? ''))?></td>
										<td><?=esc($rxSource)?></td>
										<td class="text-end"><?=number_format((float)($rx->amount_involved ?? 0), 2)?></td>
										<td>
											<?php if($rxDisplayApproval === 'approved'): ?>
												<span class="badge bg-success">Approved</span>
											<?php elseif($rxDisplayApproval === 'reversed'): ?>
												<span class="badge bg-danger">Reversed</span>
											<?php elseif($rxDisplayApproval === 'denied'): ?>
												<span class="badge bg-danger">Denied</span>
											<?php else: ?>
												<span class="badge bg-warning text-dark"><?=esc(($isCreditPayout && (int)($rx->id ?? 0) === (int)($row->id ?? 0) && !$fullyPaid) ? 'Pending payout' : 'Pending')?></span>
											<?php endif; ?>
										</td>
										<td><?=esc($rxCreatedBy)?></td>
										<td class="text-end">
											<a class="btn btn-sm btn-outline-primary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=esc((string)($rx->id ?? 0))?>">Open</a>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>

				<?php if(!empty($transferHistory) && is_array($transferHistory)): ?>
					<hr class="my-3">
					<div class="fw-semibold mb-2">Transfer history</div>
					<div class="table-responsive">
						<table class="table table-sm mb-0">
							<thead>
								<tr>
									<th>Date</th>
									<th>Movement</th>
									<th class="text-end">Amount</th>
									<th class="text-end">Charge</th>
									<th class="text-end">Net</th>
									<th>From</th>
									<th>To</th>
									<th>Status</th>
									<?php if(user_can('is_admin')): ?><th class="text-end">Action</th><?php endif; ?>
								</tr>
							</thead>
							<tbody>
								<?php foreach($transferHistory as $tr): ?>
									<?php
										$sourceId = (int)($tr->source_user_id ?? 0);
										$targetId = (int)($tr->to_user_id ?? 0);
										$sourceName = trim(((string)($tr->source_first_name ?? '')).' '.((string)($tr->source_last_name ?? '')));
										$targetName = trim(((string)($tr->to_first_name ?? '')).' '.((string)($tr->to_last_name ?? '')));
										$assignedByName = trim(((string)($tr->assigner_first_name ?? '')).' '.((string)($tr->assigner_last_name ?? '')));
										$assignedById = (int)($tr->created_by ?? 0);
										if ($assignedByName === '' && $assignedById > 0) {
											$assignedByName = 'User #' . $assignedById;
										}
										$cancelledByName = trim(((string)($tr->cancelled_first_name ?? '')).' '.((string)($tr->cancelled_last_name ?? '')));
										$cancelledById = (int)($tr->cancelled_by ?? 0);
										if ($cancelledByName === '' && $cancelledById > 0) {
											$cancelledByName = 'User #' . $cancelledById;
										}
										if ($sourceName === '' && $sourceId > 0) {
											$sourceName = 'User #' . $sourceId;
										}
										if ($targetName === '' && $targetId > 0) {
											$targetName = 'User #' . $targetId;
										}
										$movementLabel = $sourceId > 0
											? ('Reassigned from ' . ($sourceName !== '' ? $sourceName : 'Unknown') . ' to ' . ($targetName !== '' ? $targetName : 'Unknown'))
											: ('Assigned to ' . ($targetName !== '' ? $targetName : 'Unknown'));
										$trStatus = strtolower(trim((string)($tr->approval_status ?? 'approved')));
										if ($trStatus === '') $trStatus = 'approved';
										$netAmount = max(0, (float)($tr->amount ?? 0) - (float)($tr->transfer_charge ?? 0));
									?>
									<tr>
										<td><?=esc((string)get_date_time($tr->date_created ?? ''))?></td>
										<td>
											<?=esc($movementLabel)?>
											<div class="small text-body-secondary">Assigned by: <?=esc($assignedByName !== '' ? $assignedByName : 'Unknown user')?></div>
										</td>
										<td class="text-end"><?=number_format((float)($tr->amount ?? 0), 2)?></td>
										<td class="text-end"><?=number_format((float)($tr->transfer_charge ?? 0), 2)?></td>
										<td class="text-end"><?=number_format((float)$netAmount, 2)?></td>
										<td><?=esc($sourceName !== '' ? $sourceName : '—')?></td>
										<td><?=esc($targetName !== '' ? $targetName : '—')?></td>
										<td>
											<?php if($trStatus === 'approved'): ?>
												<span class="badge bg-success">Approved</span>
											<?php elseif($trStatus === 'pending'): ?>
												<span class="badge bg-warning text-dark">Pending</span>
											<?php else: ?>
												<span class="badge bg-secondary"><?=esc(ucfirst($trStatus))?></span>
											<?php endif; ?>
											<?php if($trStatus === 'cancelled'): ?>
												<div class="small text-body-secondary mt-1">Cancelled by: <?=esc($cancelledByName !== '' ? $cancelledByName : 'Unknown user')?></div>
												<?php $cancelledAt = get_date_time($tr->date_cancelled ?? ''); ?>
												<div class="small text-body-secondary"><?=esc($cancelledAt !== '' ? $cancelledAt : 'Date/time not recorded')?></div>
											<?php endif; ?>
										</td>
										<?php if(user_can('is_admin')): ?>
											<td class="text-end">
												<?php if(in_array($trStatus, ['approved', 'pending'], true)): ?>
													<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/cancel-assignment/<?=esc((string)($tr->id ?? 0))?>" class="d-inline js-cashpickup-assignment-cancel" data-cancel-confirmation="Cancel this payout assignment? The deposit itself will remain unchanged.">
														<?=$cancelCsrfField?>
														<button class="btn btn-sm btn-outline-danger" type="submit" title="Cancel payout assignment">
															<i class="fa fa-user-xmark me-1" aria-hidden="true"></i> Cancel assignment
														</button>
													</form>
												<?php else: ?>
													<span class="text-muted">-</span>
												<?php endif; ?>
											</td>
										<?php endif; ?>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>

				<?php if(!empty($payouts) && is_array($payouts)): ?>
					<hr class="my-3">
					<div class="fw-semibold mb-2">Payout history</div>
					<div class="table-responsive">
						<table class="table table-sm mb-0">
							<thead>
								<tr>
									<th>Date</th>
									<th class="text-end">Requested</th>
									<th class="text-end">Charge</th>
									<th class="text-end">Paid out</th>
									<th>Method</th>
									<th>Confirmed by</th>
									<th>Status</th>
									<th class="text-end">View</th>
									<th class="text-end">Action</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach($payouts as $p): ?>
									<?php
										$pApproval = strtolower(trim((string)($p->approval_status ?? 'pending')));
										if ($pApproval === '') $pApproval = 'pending';
										$denyReason = trim((string)($p->denial_reason ?? ''));
										$shortfallLabel = '';
										if ($pApproval === 'denied' && $denyReason !== '' && preg_match('/Shortfall:\s*([^()]+)/i', $denyReason, $m)) {
											$shortfallLabel = trim((string)($m[1] ?? ''));
										}
									?>
									<tr>
										<td><?=esc($p->date_created ?? '')?></td>
										<td class="text-end"><?=number_format((float)($p->amount_requested ?? 0), 2)?></td>
										<td class="text-end"><?=number_format((float)($p->pickup_charge ?? 0), 2)?></td>
										<td class="text-end"><?=number_format((float)($p->amount_paid_out ?? 0), 2)?></td>
										<td><?=esc($p->payment_method ?? '')?></td>
										<td><?=esc($p->confirmed_by ?? '')?></td>
										<td>
											<?php if($pApproval === 'approved'): ?>
												<span class="badge bg-success">Approved</span>
											<?php elseif($pApproval === 'reversed'): ?>
												<span class="badge bg-danger">Reversed</span>
											<?php elseif($pApproval === 'denied'): ?>
												<span class="badge bg-danger">Denied</span>
												<?php if($shortfallLabel !== ''): ?>
													<div class="small fw-semibold text-danger mt-1">Shortfall: <?=esc($shortfallLabel)?></div>
												<?php endif; ?>
												<?php if($denyReason !== ''): ?>
													<div class="small text-danger mt-1"><?=esc($denyReason)?></div>
												<?php endif; ?>
											<?php else: ?>
												<span class="badge bg-warning text-dark">Pending</span>
											<?php endif; ?>
										</td>
										<td class="text-end">
											<button
												type="button"
												class="btn btn-sm btn-outline-primary js-payout-open"
												data-bs-toggle="modal"
												data-bs-target="#cashpickupPayoutViewModal"
												data-view-url="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=esc($p->id)?>?modal=1"
											>Open</button>
										</td>
										<td class="text-end">
											<?php if($canApproveDeny && $pApproval === 'pending'): ?>
												<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/approve/<?=esc($p->id)?>" class="d-inline">
													<?=$csrfField?>
													<button class="btn btn-sm btn-success" type="submit" title="Approve"><i class="fa fa-check"></i></button>
												</form>
												<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/deny/<?=esc($p->id)?>" class="d-inline js-deny-form">
													<?=$csrfField?>
													<input type="hidden" name="denial_reason" value="">
													<button class="btn btn-sm btn-danger" type="submit" title="Deny"><i class="fa fa-times"></i></button>
												</form>
											<?php else: ?>
												<span class="text-muted">---</span>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php else: ?>
					<div class="text-muted">No payouts yet.</div>
				<?php endif; ?>
				<div class="mt-3">
					<?php
						$currentUserAllocationRemaining = (float)($currentUserAllocationRemaining ?? 0.0);
						$canAddPayout = (!$fullyPaid && ($approvalStatus === 'approved' || ($isCreditPayout && $displayApprovalStatus === 'pending')))
							&& !(user_can('is_manager') || user_can('is_admin'))
							&& (
								$currentUserAllocationRemaining > 0.00001
								|| ($isCreditPayout && (int)($row->assigned_user_id ?? 0) === (int)$user_id)
							);
					?>
					<?php if($canAddPayout): ?>
						<?php if($approvalStatus === 'approved' || ($isCreditPayout && $displayApprovalStatus === 'pending')): ?>
							<a class="btn btn-warning" href="<?=$isCreditPayout || $customerLedgerId <= 0 ? ROOT.'/'.$admin_route.'/'.$plugin_route.'/payout/'.esc((string)($row->id ?? 0)) : ROOT.'/'.$admin_route.'/'.$plugin_route.'/customer/'.esc((string)$customerLedgerId).'#withdraw-section'?>"><i class="fa fa-money-bill-wave"></i> Add payout</a>
						<?php else: ?>
							<button class="btn btn-warning" type="button" disabled title="Cashpickup is not approved">
								<i class="fa fa-money-bill-wave"></i> Add payout
							</button>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<div class="modal fade" id="cashpickupPayoutViewModal" tabindex="-1" aria-hidden="true">
			<div class="modal-dialog modal-xl modal-dialog-scrollable">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">Payout Details</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body" id="cashpickupPayoutViewBody">Loading...</div>
				</div>
			</div>
		</div>

		<script>
			(function(){
				const modalEl = document.getElementById('cashpickupPayoutViewModal');
				const bodyEl = document.getElementById('cashpickupPayoutViewBody');
				if(!modalEl || !bodyEl) return;
				let abort = null;

				function extractMainFromHtml(html){
					try{
						const parser = new DOMParser();
						const doc = parser.parseFromString(html, 'text/html');
						const main = doc.querySelector('#mainBody');
						if(main) return main.innerHTML;
						const container = doc.querySelector('.container-fluid');
						if(container) return container.innerHTML;
						const body = doc.body;
						if(body) return body.innerHTML;
						return html;
					}catch(e){
						return html;
					}
				}

				modalEl.addEventListener('show.bs.modal', function(ev){
					const btn = ev && ev.relatedTarget ? ev.relatedTarget : null;
					const url = btn ? btn.getAttribute('data-view-url') : '';
					if (abort) abort.abort();
					abort = new AbortController();
					bodyEl.textContent = 'Loading...';
					if(!url){
						bodyEl.textContent = 'Unable to load view';
						return;
					}
					fetch(url, {headers: {'Accept': 'text/html'}, signal: abort.signal})
						.then(r => r.text())
						.then(html => {
							bodyEl.innerHTML = extractMainFromHtml(html);
						})
						.catch(() => {
							bodyEl.textContent = 'Unable to load view';
						});
				});
				modalEl.addEventListener('hidden.bs.modal', function(){
					if (abort) abort.abort();
					abort = null;
					bodyEl.textContent = '';
				});

				document.addEventListener('submit', function(e){
					const formEl = e.target;
					if(!formEl || !formEl.classList || !formEl.classList.contains('js-deny-form')) return;
					const reasonInput = formEl.querySelector('input[name="denial_reason"]');
					if(!reasonInput) return;
					const existing = String(reasonInput.value || '').trim();
					let reason = existing;
					if(reason === ''){
						reason = window.prompt('Enter denial reason:');
					}
					reason = String(reason || '').trim();
					if(reason === ''){
						e.preventDefault();
						return;
					}
					reasonInput.value = reason;
				});
			})();
		</script>

		<?php if(!empty($denos)): ?>
			<div class="border rounded p-3 mt-3 shadow">
				<div class="d-flex align-items-center justify-content-between mb-2">
					<div class="fw-semibold">Currency Denominations</div>
					<div class="text-muted small">Total: GHS <?=number_format((float)$denosTotal, 2)?></div>
				</div>
				<div class="table-responsive">
					<table class="table table-sm table-hover mb-0">
						<thead>
							<tr>
								<th>Currency</th>
								<th>Denomination</th>
								<th class="text-end">Count</th>
								<th class="text-end">Volume</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach($denos as $d): ?>
								<tr>
									<td><?=esc((string)($d['currency'] ?? 'GHS'))?></td>
									<td><?=number_format((float)($d['denomination'] ?? 0), ((float)($d['denomination'] ?? 0) < 1 ? 1 : 0))?></td>
									<td class="text-end"><?=number_format((float)($d['count'] ?? 0))?></td>
									<td class="text-end"><?=number_format((float)($d['volume'] ?? 0), 2)?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<div class="col-lg-4">
		<div class="border rounded p-3 shadow">
			<div class="fw-semibold mb-2">Amounts</div>
			<div class="d-flex align-items-center justify-content-between py-1">
				<div class="text-muted">Amount involved</div>
				<div class="fw-bold">GHS <?=number_format((float)($row->amount_involved ?? 0),2)?></div>
			</div>
			<div class="d-flex align-items-center justify-content-between py-1">
				<div class="text-muted">Charge</div>
				<div class="fw-bold">GHS <?=number_format((float)(($side === 'receive') ? $totalCharge : ($row->pickup_charge ?? 0)),2)?></div>
			</div>
			<div class="d-flex align-items-center justify-content-between py-1">
				<div class="text-muted">Paid out</div>
				<div class="fw-bold">GHS <?=number_format((float)(($side === 'receive') ? $totalPaidOut : ($row->amount_paid_out ?? 0)),2)?></div>
			</div>
		</div>

		<div class="border rounded p-3 mt-3 shadow">
			<div class="fw-semibold mb-2">Details</div>
			<div class="d-flex align-items-center justify-content-between py-1">
				<div class="text-muted">Approval status</div>
				<div class="fw-semibold text-end">
					<?php if($displayApprovalStatus === 'approved'): ?>
						<span class="badge bg-success">Approved</span>
					<?php elseif($displayApprovalStatus === 'reversed'): ?>
						<span class="badge bg-danger">Reversed</span>
					<?php elseif($displayApprovalStatus === 'denied'): ?>
						<span class="badge bg-danger">Denied</span>
					<?php else: ?>
						<span class="badge bg-warning text-dark"><?=esc($displayApprovalText)?></span>
					<?php endif; ?>
				</div>
			</div>
			<div class="d-flex align-items-center justify-content-between py-1">
				<div class="text-muted"><?=($displayApprovalStatus === 'reversed' ? 'Reversed by' : ($displayApprovalStatus === 'denied' ? 'Denied by' : ($displayApprovalStatus === 'pending' && $isCreditPayout ? 'Assigned to' : 'Approved by')))?></div>
				<div class="fw-semibold text-end"><?=esc($displayApprovalStatus === 'pending' && $isCreditPayout ? ($assignedName !== '' ? $assignedName : ((string)($row->assigned_user_id ?? '---'))) : ($approvedByName !== '' ? $approvedByName : ((string)($row->approved_by ?? '---'))))?></div>
			</div>
			<?php if(in_array($displayApprovalStatus, ['denied', 'cancelled', 'canceled'], true) && trim((string)($row->denial_reason ?? '')) !== ''): ?>
				<div class="d-flex align-items-center justify-content-between py-1">
					<div class="text-muted"><?=in_array($displayApprovalStatus, ['cancelled', 'canceled'], true) ? 'Cancellation reason' : 'Denial reason'?></div>
					<div class="fw-semibold text-end text-danger"><?=esc((string)$row->denial_reason)?></div>
				</div>
			<?php endif; ?>
			<div class="d-flex align-items-center justify-content-between py-1">
				<div class="text-muted"><?=($displayApprovalStatus === 'pending' ? 'Pending since' : 'Action date')?></div>
				<div class="fw-semibold text-end"><?=esc((string)(($displayApprovalStatus === 'pending' && $isCreditPayout) ? ($row->date_created ?? '---') : ($row->approved_at ?? $row->date_created ?? '---')))?></div>
			</div>
			<div class="d-flex align-items-center justify-content-between py-1">
				<div class="text-muted">Created by</div>
				<div class="fw-semibold text-end"><?=esc($createdByName !== '' ? $createdByName : ((string)($row->created_by ?? '')))?></div>
			</div>
			<div class="d-flex align-items-center justify-content-between py-1">
				<div class="text-muted">Assigned to</div>
				<div class="fw-semibold text-end"><?=esc($assignedName !== '' ? $assignedName : ((string)($row->assigned_user_id ?? '---')))?></div>
			</div>
			<div class="d-flex align-items-center justify-content-between py-1">
				<div class="text-muted">Original source</div>
				<div class="fw-semibold text-end"><?=esc($originalSourceLabel !== '' ? $originalSourceLabel : ((string)($row->source ?? '')))?></div>
			</div>
		</div>

		<?php $photoGallery = array_values($photoItems); ?>
		<?php if(!empty($photoGallery)): ?>
			<div class="border rounded p-3 mt-3 shadow">
				<div class="fw-semibold mb-2">Photos</div>
				<div class="row g-2">
					<?php foreach($photoGallery as $photo): ?>
						<?php $photoUrl = $imgUrl($photo['path'] ?? ''); ?>
						<?php if($photoUrl === '') continue; ?>
						<div class="col-12">
							<a href="<?=esc($photoUrl)?>" target="_blank" class="text-decoration-none">
								<img src="<?=esc($photoUrl)?>" loading="lazy" decoding="async" style="width:100%;max-height:220px;object-fit:cover;border-radius:12px;border:1px solid rgba(148,163,184,0.6);" />
								<div class="small text-muted mt-1">
									<div><?=esc((string)($photo['label'] ?? 'Photo'))?></div>
									<?php if(!empty($photo['record_id']) || !empty($photo['date_created'])): ?>
										<div>
											<?php if(!empty($photo['record_id'])): ?>
												<span>Transaction #<?=esc((string)$photo['record_id'])?></span>
											<?php endif; ?>
											<?php if(!empty($photo['record_id']) && !empty($photo['date_created'])): ?>
												<span> | </span>
											<?php endif; ?>
											<?php if(!empty($photo['date_created'])): ?>
												<span><?=esc((string)$photo['date_created'])?></span>
											<?php endif; ?>
										</div>
									<?php endif; ?>
								</div>
							</a>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>

	</div>
</div>

<?php endif; ?>
<?php endif; ?>

<?php
require_once dirname(__DIR__) . '/helpers/shared-account-reconciliation.php';

// Set timezone to Accra (Africa/Accra)
date_default_timezone_set('Africa/Accra');

$postdata = $req->post();

$csrf = csrf_verify($postdata);

if ($csrf && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $now = date("Y-m-d H:i:s");
    $postdata['date_created'] = $now;

    $session = get_session();
    $username = $session->first_name . ' ' . $session->last_name;
    $user_id = $session->id;
    $lateAccessId = (int)($postdata['late_access_id'] ?? ($_GET['late_access'] ?? 0));
    $lateReconciliationAccess = $lateAccessId > 0 ? backoffice_active_late_reconciliation_access((int)$user_id, $lateAccessId) : null;
    if ($lateAccessId > 0 && empty($lateReconciliationAccess)) {
        message_fail('Late reconciliation access is no longer active.');
        redirect($admin_route . '/' . $plugin_route);
        exit;
    }

    if (empty($lateReconciliationAccess) && !backoffice_user_is_admin($session)) {
        $acknowledgedPettyCash = backoffice_reconciliation_acknowledged_petty_cash((int)$user_id);
        if (!empty($acknowledgedPettyCash)) {
            message_fail(backoffice_reconciliation_acknowledged_petty_cash_message($acknowledgedPettyCash));
            redirect($admin_route . '/' . $plugin_route . '/reconciliation');
            exit;
        }
    }

    if (!backoffice_user_is_admin($session) && empty($lateReconciliationAccess)) {
        $activeReconciliation = backoffice_active_reconciliation_for_day((int)$user_id);
        if (!empty($activeReconciliation)) {
            $activeStatus = strtolower(trim((string)($activeReconciliation->transaction_status ?? 'Submitted')));
            $activeId = (string)($activeReconciliation->id ?? $activeReconciliation->session_id ?? '');
            message_fail(backoffice_reconciliation_lock_message($activeReconciliation));
            if ($activeStatus === 'approved' && $activeId !== '') {
                redirect($admin_route . '/' . $plugin_route . '/view_reconciliation/' . $activeId);
            } else {
                redirect($admin_route . '/' . $plugin_route . '/reconciliation');
            }
            exit;
        }
    }

    $rows = $postdata['rows'] ?? [];
	// Store what the user entered so the form can be repopulated after redirect
	if (!empty($rows) && is_array($rows)) {
		set_value('recon_old', ['rows' => $rows]);
	}
    $successCount = 0;
    $postdata['id'] = time();
    $errors = [];
    $formErrors = [];

    $dbSetup = new \Core\Database;
    if (!$dbSetup->table_exists(['reconciliations'])) {
        message_fail('Reconciliation detail table is missing. Please repair the reconciliations table before submitting a new reconciliation.');
        redirect($admin_route . '/' . $plugin_route . '/reconciliation');
        exit;
    }

    $drawerSnapshot = [];
    $drawerContext = null;
    $ecashBalances = [];
    $assignedSharedAccountKeys = users_manager_user_shared_account_keys((int)$user_id);
    if (!empty($lateReconciliationAccess)) {
        $drawerSnapshot = backoffice_cashdrawer_snapshot_by_id((int)$lateReconciliationAccess->cashdrawer_id, (int)$user_id);
        $drawerContext = !empty($drawerSnapshot)
            ? ['drawer_id' => (int)($drawerSnapshot['drawer_id'] ?? 0), 'drawer_user_id' => (int)($drawerSnapshot['drawer_user_id'] ?? 0)]
            : null;
    } elseif ($user_id > 0 && function_exists('Cfas\\cfa_get_cashdrawer_snapshot')) {
        $drawerSnapshot = \Cfas\cfa_get_cashdrawer_snapshot((int)$user_id);
        $drawerContext = !empty($drawerSnapshot)
            ? ['drawer_id' => (int)($drawerSnapshot['drawer_id'] ?? 0), 'drawer_user_id' => (int)($drawerSnapshot['drawer_user_id'] ?? 0)]
            : null;
    }
    $normalizeDenoHistory = function (array $history, string $currency): string {
        $currency = strtoupper(trim($currency ?: 'GHS'));
        if (!in_array($currency, ['GHS', 'CFA'], true)) {
            $currency = 'GHS';
        }

        $rows = [];
        foreach ($history as $item) {
            if (is_object($item)) {
                $item = get_object_vars($item);
            }
            if (!is_array($item)) {
                continue;
            }
            $denomination = (float)($item['denomination'] ?? 0);
            $count = (int)($item['count'] ?? 0);
            $volume = (float)($item['volume'] ?? ($denomination * $count));
            if ($denomination <= 0 || $count < 0) {
                continue;
            }
            $rows[] = [
                'currency' => $currency,
                'denomination' => $denomination,
                'count' => $count,
                'volume' => round($volume, 2),
            ];
        }

        usort($rows, function (array $a, array $b): int {
            return ($b['denomination'] <=> $a['denomination']);
        });

        return json_encode(array_values($rows), JSON_UNESCAPED_SLASHES);
    };
    if (empty($drawerContext)) {
        message_fail('Open a cashdrawer before running reconciliation.');
        redirect($admin_route . '/' . $plugin_route . '/reconciliation');
        exit;
    }

    $dbRecon = new \Core\Database;
    $rowsEcash = $dbRecon->query(
        "SELECT channel_key, closing_balance FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash'",
        [
            'drawer_id' => (int)$drawerContext['drawer_id'],
            'user_id' => (int)$drawerContext['drawer_user_id'],
        ]
    ) ?: [];
    foreach ($rowsEcash as $r) {
        $key = strtoupper(trim((string)($r->channel_key ?? '')));
        if ($key === '') {
            continue;
        }
        if (strpos($key, 'ECASH_') === 0) {
            $key = substr($key, 6);
        }
        $key = str_replace('_', '-', $key);
        $key = str_replace(' ', '-', $key);
        $key = trim($key, '-');
        $sharedAccountKey = users_manager_normalize_shared_account_key($key);
        if ($sharedAccountKey !== null) {
            if (!in_array($sharedAccountKey, $assignedSharedAccountKeys, true)) {
                continue;
            }
            $key = str_replace('_', '-', $sharedAccountKey);
        }
        if (in_array($key, ['ECASH', 'E-CASH', 'E-CASH-', 'E-CASH--', 'EZWICH', 'E-ZWICH'], true)) {
            continue;
        }
        $ecashBalances[$key] = (float)($r->closing_balance ?? 0);
    }
    $reconciliationDate = !empty($lateReconciliationAccess->drawer_date)
        ? (string)$lateReconciliationAccess->drawer_date
        : date('Y-m-d');
    foreach (backoffice_reconciliation_assigned_shared_account_balances($assignedSharedAccountKeys, $reconciliationDate) as $sharedKey => $balance) {
        $ecashBalances[str_replace('_', '-', $sharedKey)] = $balance;
    }
	if (in_array('MTN_MOMOPAY', $assignedSharedAccountKeys, true)
		&& function_exists('Agencybankings\\agency_find_shared_momopay_context')) {
		$sharedMomoContext = \Agencybankings\agency_find_shared_momopay_context(
			null,
			$reconciliationDate
		);
		if (!empty($sharedMomoContext)) {
			$ecashBalances['MTN-MOMOPAY'] = (float)($sharedMomoContext['closing_balance'] ?? 0);
		}
	}

    $defaultEcashChannels = ['AT', 'G-MONEY', 'MTN', 'MTN-MOMOPAY', 'TELECEL', 'ZEEPAY', 'GHANAPAY'];
    $allEcashChannels = array_values(array_unique(array_merge($defaultEcashChannels, array_keys($ecashBalances))));
    sort($allEcashChannels);
    $outstandingEcashChannels = backoffice_reconciliation_outstanding_ecash_channels($ecashBalances);
    if (empty($lateReconciliationAccess)
        && !backoffice_user_is_admin($session)
        && !backoffice_user_is_operations_manager($session)
        && !empty($outstandingEcashChannels)) {
        $redirectPath = user_can('add_payment')
            ? ($admin_route . '/' . $plugin_route . '/make_payment?recon_payment=1' . ($lateAccessId > 0 ? '&late_access=' . $lateAccessId : ''))
            : ($admin_route . '/' . $plugin_route . '/reconciliation');
        set_value('reconciliation_outstanding_payment_context', [
            'user_id' => (int)$user_id,
            'late_access_id' => (int)$lateAccessId,
            'channels' => $outstandingEcashChannels,
            'created_at' => time(),
        ]);
        message_fail(backoffice_reconciliation_outstanding_ecash_message($outstandingEcashChannels));
        redirect($redirectPath);
        exit;
    }

	$normalizeChannelId = function (string $channelId): string {
		$channelId = trim((string)$channelId);
		if ($channelId === '') {
			return '';
		}
		if (strpos($channelId, 'commission:') === 0) {
			return $channelId;
		}
		$lower = strtolower($channelId);
		if (strpos($lower, 'cash') === 0 && strpos($channelId, 'cash:') !== 0) {
			$rest = trim(substr($channelId, 4));
			$rest = ltrim($rest, ':');
			$rest = strtoupper(trim($rest));
			if ($rest !== '') {
				return 'cash:' . $rest;
			}
		}
		if (strpos($lower, 'ecash') === 0 && strpos($channelId, 'ecash:') !== 0) {
			$rest = trim(substr($channelId, 5));
			$rest = ltrim($rest, ':');
			$rest = strtoupper(trim($rest));
			$rest = str_replace(['_', ' '], '-', $rest);
			$rest = preg_replace('/-+/', '-', (string)$rest);
			$rest = trim($rest, '-');
			if ($rest !== '') {
				return 'ecash:' . $rest;
			}
		}
		if (strpos($channelId, 'ecash:') === 0) {
			$rest = strtoupper(trim(substr($channelId, 6)));
			$rest = str_replace(['_', ' '], '-', $rest);
			$rest = preg_replace('/-+/', '-', (string)$rest);
			$rest = trim($rest, '-');
			return 'ecash:' . $rest;
		}
		return $channelId;
	};

	$isReconPrivileged = (user_can('is_admin') || user_can('is_manager'));
	$isGeneralManager = false;
	try {
		$rolesRaw = $session->roles ?? [];
		if (!is_array($rolesRaw)) {
			$rolesRaw = [$rolesRaw];
		}
		$rolesLower = array_map(function ($r) {
			return strtolower(trim((string)$r));
		}, $rolesRaw);
		$isGeneralManager = (bool)array_intersect($rolesLower, ['general manager', 'general_manager', 'generalmanager', 'gm', 'is_general_manager']);
	} catch (\Throwable $e) {
		$isGeneralManager = false;
	}
	$canSeeBankChannels = (user_can('is_admin') || $isGeneralManager);
	$bankChannelNeedles = [
		'BANK',
		'ADB',
		'ABSA',
		'FIDELITY',
		'APEX',
		'ACCESS',
		'CAL',
		'ECOBANK',
		'GCB',
		'GT',
		'STANBIC',
		'FNB',
		'FIRST',
	];
	$isBankKey = function (string $key) use ($bankChannelNeedles): bool {
		$key = strtoupper(trim($key));
		if ($key === '') {
			return false;
		}
		foreach ($bankChannelNeedles as $needle) {
			if ($needle !== '' && strpos($key, $needle) !== false) {
				return true;
			}
		}
		return false;
	};

	try {
		$dbPending = new \Core\Database;
		$pendingIssues = [];
		$uid = (int)$user_id;
		$pendingChecks = [
			[
				'table' => 'agencybankings',
				'label' => 'Agency Banking',
				'query' => "SELECT id FROM agencybankings WHERE LOWER(COALESCE(transaction_status,'')) = 'pending' AND created_by = :uid LIMIT 1",
			],
			[
				'table' => 'cashpickups',
				'label' => 'Cash Pickup',
				'query' => "SELECT id FROM cashpickups
					WHERE (deleted IS NULL OR deleted = 0)
					AND LOWER(COALESCE(transaction_status,'')) NOT LIKE '%cancel%'
					AND LOWER(COALESCE(transaction_status,'')) NOT LIKE '%reject%'
					AND LOWER(COALESCE(transaction_status,'')) NOT LIKE '%deni%'
					AND LOWER(COALESCE(transaction_status,'')) NOT LIKE '%declin%'
					AND LOWER(COALESCE(transaction_status,'')) NOT LIKE '%complet%'
					AND LOWER(COALESCE(approval_status,'')) NOT LIKE '%cancel%'
					AND LOWER(COALESCE(approval_status,'')) NOT LIKE '%reject%'
					AND LOWER(COALESCE(approval_status,'')) NOT LIKE '%deni%'
					AND LOWER(COALESCE(approval_status,'')) NOT LIKE '%declin%'
					AND (
						LOWER(COALESCE(transaction_status,'')) LIKE '%pending%'
						OR LOWER(COALESCE(approval_status,'')) IN ('pending','draft')
					)
					AND (created_by = :uid OR assigned_user_id = :uid)
					LIMIT 1",
			],
			[
				'table' => 'payments',
				'label' => 'Payments',
				'query' => "SELECT id FROM payments WHERE LOWER(COALESCE(transaction_status,'')) = 'pending' AND created_by = :uid LIMIT 1",
			],
			[
				'table' => 'payment_services',
				'label' => 'Payment Services',
				'query' => "SELECT id FROM payment_services WHERE (deleted IS NULL OR deleted = 0) AND LOWER(COALESCE(transaction_status,'')) = 'pending' AND created_by = :uid LIMIT 1",
			],
			[
				'table' => 'requests',
				'label' => 'Backoffice Requests',
				'query' => "SELECT id FROM requests WHERE LOWER(COALESCE(transaction_status,'')) = 'pending' AND (created_by = :uid OR assigned = :uid) LIMIT 1",
			],
			[
				'table' => 'payments_backoffice',
				'label' => 'Backoffice Payments',
				'query' => "SELECT id FROM payments_backoffice WHERE LOWER(COALESCE(transaction_status,'')) = 'pending' AND (created_by = :uid OR pay_to = :uid) LIMIT 1",
			],
			[
				'table' => 'reversals',
				'label' => 'Reversals',
				'query' => "SELECT id FROM reversals WHERE LOWER(COALESCE(transaction_status,'')) = 'pending' AND (created_by = :uid OR agent_involved = :uid) LIMIT 1",
			],
		];

		if ($uid > 0) {
			foreach ($pendingChecks as $check) {
				if (!$dbPending->table_exists([$check['table']])) {
					continue;
				}

				$hasPendingRows = $dbPending->query($check['query'], ['uid' => $uid]);
				if (!empty($hasPendingRows)) {
					$pendingIssues[] = $check['label'];
				}
			}

			if (
				$dbPending->table_exists(['cashpickups'])
				&& $dbPending->table_exists(['cashpickup_transfers'])
			) {
				$assignedReceiveRows = $dbPending->query(
					"SELECT cp.id
					 FROM cashpickups cp
					 WHERE (cp.deleted IS NULL OR cp.deleted = 0)
					 AND LOWER(COALESCE(cp.transaction_side,'')) = 'receive'
					 AND LOWER(COALESCE(cp.approval_status,'')) = 'approved'
					 AND LOWER(COALESCE(cp.transaction_status,'')) NOT LIKE '%cancel%'
					 AND LOWER(COALESCE(cp.transaction_status,'')) NOT LIKE '%reject%'
					 AND LOWER(COALESCE(cp.transaction_status,'')) NOT LIKE '%deni%'
					 AND LOWER(COALESCE(cp.transaction_status,'')) NOT LIKE '%declin%'
					 AND LOWER(COALESCE(cp.transaction_status,'')) NOT LIKE '%complet%'
					 AND EXISTS (
						SELECT 1
						FROM cashpickup_transfers t
						WHERE t.receive_id = cp.id
						AND t.to_user_id = :transfer_user
						AND (
							t.approval_status IS NULL
							OR t.approval_status = ''
							OR LOWER(t.approval_status) IN ('approved','pending')
						)
					 )
					 AND (
						COALESCE((
							SELECT SUM(t.amount)
							FROM cashpickup_transfers t
							WHERE t.receive_id = cp.id
							AND t.to_user_id = :allocation_user
							AND (
								t.approval_status IS NULL
								OR t.approval_status = ''
								OR LOWER(t.approval_status) IN ('approved','pending')
							)
						), 0)
						-
						COALESCE((
							SELECT SUM(COALESCE(p.amount_paid_out,0) + COALESCE(p.pickup_charge,0))
							FROM cashpickups p
							WHERE p.receive_id = cp.id
							AND (p.deleted IS NULL OR p.deleted = 0)
							AND LOWER(COALESCE(p.transaction_side,'')) = 'pay'
							AND LOWER(COALESCE(p.transaction_status,'')) = 'approved'
							AND (p.confirmed_by IS NOT NULL AND p.confirmed_by != '')
							AND COALESCE(p.amount_paid_out,0) > 0
							AND (
								p.approval_status IS NULL
								OR p.approval_status = ''
								OR LOWER(p.approval_status) IN ('approved','pending')
							)
							AND (p.created_by = :payout_user OR p.assigned_user_id = :payout_assigned_user)
						), 0)
					 ) > 0.00001
					 LIMIT 1",
					[
						'transfer_user' => $uid,
						'allocation_user' => $uid,
						'payout_user' => $uid,
						'payout_assigned_user' => $uid,
					]
				);
				if (!empty($assignedReceiveRows)) {
					$pendingIssues[] = 'Cash Pickup';
				}
			}
		}

		if (!empty($pendingIssues)) {
			message_fail('Process all pending transactions before reconciliation. Pending found in: ' . implode(', ', $pendingIssues) . '.');
			redirect($admin_route . '/' . $plugin_route . '/reconciliation');
			exit;
		}
	} catch (\Throwable $e) {
		message_fail('Unable to validate pending transactions. Please resolve pending work and retry reconciliation.');
		redirect($admin_route . '/' . $plugin_route . '/reconciliation');
		exit;
	}

	$expectedMap = [];
	$addExpectedChannel = function (string $channelId, float $expected) use (&$expectedMap): void {
		$expectedMap[$channelId] = $expected;
	};
	$addExpectedChannel('cash:GHS', (float)($drawerSnapshot['ghs_balance'] ?? 0));
	$addExpectedChannel('cash:CFA', (float)($drawerSnapshot['cfa_balance'] ?? 0));
	foreach ($allEcashChannels as $ch) {
		$key = strtoupper(trim((string)$ch));
		if ($key === '') {
			continue;
		}
		$key = str_replace('_', '-', $key);
		$key = str_replace(' ', '-', $key);
		$key = trim($key, '-');
		if (in_array($key, ['ECASH', 'E-CASH', 'E-CASH-', 'E-CASH--'], true)) {
			continue;
		}
		if (in_array($key, ['EZWICH', 'E-ZWICH'], true)) {
			continue;
		}
		$sharedAccountKey = users_manager_normalize_shared_account_key($key);
		$isSharedChannel = $isBankKey($key) || backoffice_reconciliation_is_shared_channel($key);
		if (($isSharedChannel || $sharedAccountKey !== null)
			&& ($sharedAccountKey === null || !in_array($sharedAccountKey, $assignedSharedAccountKeys, true))) {
			continue;
		}
		$addExpectedChannel('ecash:' . $key, (float)($ecashBalances[$key] ?? 0));
	}

	$commissionTotal = 0.0;
	try {
		$dbComm = new \Core\Database;
		if ($dbComm->table_exists(['commissions'])) {
			$today = !empty($lateReconciliationAccess) && !empty($lateReconciliationAccess->drawer_date)
				? (string)$lateReconciliationAccess->drawer_date
				: date('Y-m-d');
			$commWhereParts = [
				"(deleted IS NULL OR deleted = 0)",
				"(disabled IS NULL OR disabled = 0)",
				"(pay_to = :uid OR created_by = :uid)",
				"DATE(COALESCE(NULLIF(NULLIF(NULLIF(TRIM(date_received),''),'0000-00-00 00:00:00'),'0000-00-00'), date_created)) = :today",
				"(
					UPPER(COALESCE(commission_type,'')) LIKE '%CHARGE%'
					OR UPPER(COALESCE(commission_type,'')) = 'MOMOREMIT CASHIN'
					OR UPPER(COALESCE(colletion_period,'')) = 'INSTANT'
					OR UPPER(COALESCE(commission_type,'')) LIKE '%BULK%'
					OR UPPER(COALESCE(description,'')) LIKE '%COMMISSION BULK%'
				)"
			];
			try {
				$bulkCol = $dbComm->query("SHOW COLUMNS FROM commissions LIKE 'bulk_parent_id'") ?: [];
				if (!empty($bulkCol)) {
					$commWhereParts[] = "(bulk_parent_id IS NULL OR bulk_parent_id = 0)";
				}
			} catch (\Throwable $e) {
				// ignore bulk parent check if column lookup fails
			}
			$commWhereSql = implode(' AND ', $commWhereParts);
			$commissionRow = $dbComm->query(
				"SELECT COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(transaction_status,''))) <> 'rejected' THEN amount ELSE 0 END),0) AS total_amount
				 FROM commissions
				 WHERE {$commWhereSql}",
				[
					'uid' => (int)$user_id,
					'today' => $today,
				]
			);
			if (!empty($commissionRow)) {
				$commissionTotal = (float)($commissionRow[0]->total_amount ?? 0);
			}
		}
	} catch (\Throwable $e) {
		$commissionTotal = 0.0;
	}
	if ($commissionTotal > 0.009) {
		$expectedMap['commission:charges'] = $commissionTotal;
	}

	if (empty($rows) || !is_array($rows)) {
		message_fail('Enter all balances before submitting reconciliation.');
		redirect($admin_route . '/' . $plugin_route . '/reconciliation');
		exit;
	}

	if (!empty($expectedMap['commission:charges'])) {
		$hasCommissionRow = false;
		foreach ($rows as $row) {
			$rawChannelId = isset($row['channel_id']) ? (string)$row['channel_id'] : '';
			if ($normalizeChannelId($rawChannelId) === 'commission:charges') {
				$hasCommissionRow = true;
				break;
			}
		}
		if (!$hasCommissionRow) {
			$rows[] = [
				'channel_id' => 'commission:charges',
				'balance' => (string)$expectedMap['commission:charges'],
			];
		}
	}

	$requiredCount = count($expectedMap);
	if (count($rows) !== $requiredCount) {
		message_fail('Enter all balances (cash, e-cash, and commissions) before submitting reconciliation.');
		redirect($admin_route . '/' . $plugin_route . '/reconciliation');
		exit;
	}

	$seenChannels = [];
	foreach ($rows as $rowIndex => $row) {
		$rawChannelId = isset($row['channel_id']) ? (string)$row['channel_id'] : '';
		if (trim($rawChannelId) === '') {
			message_fail('Enter all balances (cash, e-cash, and commissions) before submitting reconciliation.');
			redirect($admin_route . '/' . $plugin_route . '/reconciliation');
			exit;
		}
		$channelId = $normalizeChannelId($rawChannelId);
		if ($channelId === '' || !array_key_exists($channelId, $expectedMap)) {
			message_fail('Enter all balances (cash, e-cash, and commissions) before submitting reconciliation.');
			redirect($admin_route . '/' . $plugin_route . '/reconciliation');
			exit;
		}
		if (isset($seenChannels[$channelId])) {
			message_fail('Enter all balances (cash, e-cash, and commissions) before submitting reconciliation.');
			redirect($admin_route . '/' . $plugin_route . '/reconciliation');
			exit;
		}
		$seenChannels[$channelId] = true;
	}
	if (count($seenChannels) !== $requiredCount) {
		message_fail('Enter all balances (cash, e-cash, and commissions) before submitting reconciliation.');
		redirect($admin_route . '/' . $plugin_route . '/reconciliation');
		exit;
	}

    $totalExpected = 0.0;
    foreach ($expectedMap as $v) {
        $totalExpected += (float)$v;
    }

    $totalEntered = 0.0;
    $hasMismatch = false;
    $req->upload_folder = 'uploads/reconciliations';
    $now = date("Y-m-d H:i:s");
    $reconciliationCreatedAt = $now;
    if (!empty($lateReconciliationAccess)) {
        $drawerDate = trim((string)($lateReconciliationAccess->drawer_date ?? ''));
        if ($drawerDate !== '') {
            $reconciliationCreatedAt = $drawerDate . ' ' . date('H:i:s');
            $postdata['date_created'] = $reconciliationCreatedAt;
        }
    }
    // Max file size 1MB
    $maxFileSize = 1 * 1024 * 1024;
    foreach ($rows as $rowIndex => $row) {
        if (empty($row['channel_id'])) {
            $errors[] = 'Invalid reconciliation row.';
            continue;
        }
        $channelId = $normalizeChannelId((string)$row['channel_id']);
        $rows[$rowIndex]['channel_id'] = $channelId;
        if (!array_key_exists($channelId, $expectedMap)) {
            $errors[] = 'Invalid reconciliation channel: ' . $channelId;
            continue;
        }

        $isCash = (strpos($channelId, 'cash:') === 0);
        $isEcash = (strpos($channelId, 'ecash:') === 0);
        $isCommission = (strpos($channelId, 'commission:') === 0);
        $expected = (float)$expectedMap[$channelId];
        $hasNonZeroExpected = (abs($expected) >= 0.01);
        $userProvidedBalance = (isset($row['balance']) && trim((string)$row['balance']) !== '');
        if ($isCash && !$userProvidedBalance) {
            if ($hasNonZeroExpected) {
                $formErrors['balance_' . (string)$rowIndex] = 'Enter balance.';
                $errors[] = 'Enter all balances before submitting reconciliation.';
                continue;
            }
            // If expected cash is zero, allow blank and assume it matches expected
            $rows[$rowIndex]['balance'] = (string)$expected;
        }
        if ($isCommission && !$userProvidedBalance) {
            if ($hasNonZeroExpected) {
                $rows[$rowIndex]['balance'] = (string)$expected;
            } else {
                $rows[$rowIndex]['balance'] = '0';
            }
        }
        if ($isEcash && !$userProvidedBalance) {
            if ($hasNonZeroExpected) {
                $formErrors['balance_' . (string)$rowIndex] = 'Enter balance.';
                $errors[] = 'Enter all balances before submitting reconciliation.';
                continue;
            }
            // For better UX: if expected is zero, allow blank e-cash balance and assume it matches expected
            $rows[$rowIndex]['balance'] = (string)$expected;
        }

        $proofImagePath = '';
        $denosJson = null;
        if ($isCommission) {
            // no proof or denominations required for auto commissions
        } elseif (!$isCash) {
            $isBankChannel = false;
            if (strpos($channelId, 'ecash:') === 0) {
                $labelKey = strtoupper(trim(substr($channelId, 6)));
                foreach ($bankChannelNeedles as $needle) {
                    if ($needle !== '' && strpos($labelKey, $needle) !== false) {
                        $isBankChannel = true;
                        break;
                    }
                }
            }
            $requireProofBase = (!empty($lateReconciliationAccess) || $isReconPrivileged || !$isBankChannel);
            $enteredNonZeroBalance = $userProvidedBalance && abs((float)$row['balance']) >= 0.01;
            $requireProof = ($requireProofBase && $enteredNonZeroBalance);
            $fileKey = 'proof_image_' . (string)$rowIndex;
            $hasIncomingProof = !empty($_FILES[$fileKey]['tmp_name']);
            
            // Validate file size before upload
            if ($hasIncomingProof) {
                $fileSize = (int)($_FILES[$fileKey]['size'] ?? 0);
                if ($fileSize > $maxFileSize) {
                    $maxFileSizeMB = round($maxFileSize / (1024 * 1024), 0);
                    $formErrors[$fileKey] = "Image size exceeds {$maxFileSizeMB}MB limit. Please upload a smaller image.";
                    $errors[] = 'Upload proof for ' . $channelId . ' - Image exceeds ' . $maxFileSizeMB . 'MB';
                    continue;
                }
            }
            
            $uploadedProof = $hasIncomingProof ? $req->upload_files($fileKey) : '';
            $existingProof = '';
            if (!empty($row['existing_proof'])) {
                $existingProof = trim((string)$row['existing_proof']);
                if ($existingProof === '') {
                    $existingProof = '';
                }
            }
            $hasExistingProof = ($existingProof !== '');
            if ($requireProof && (empty($uploadedProof) || !empty($req->upload_errors[$fileKey]))) {
                if (!$hasExistingProof) {
                    $formErrors[$fileKey] = $req->upload_errors[$fileKey] ?? 'Photo proof is required for non-cash channels.';
                    $errors[] = 'Upload proof for ' . $channelId;
                    continue;
                }
            }
            if (!empty($uploadedProof) && empty($req->upload_errors[$fileKey])) {
                $proofImagePath = $uploadedProof;
                $rows[$rowIndex]['existing_proof'] = $uploadedProof;
            } elseif ($hasExistingProof) {
                $proofImagePath = $existingProof;
            }
        } else {
            if (isset($row['denos_json']) && trim((string)$row['denos_json']) !== '') {
                $candidate = trim((string)$row['denos_json']);
                $decoded = json_decode($candidate, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $denosJson = $candidate;
                }
            }
        }

        $balance = (float)$rows[$rowIndex]['balance'];
        $variance = $expected - $balance;
        $remarks = (abs($variance) < 0.01) ? 'Balanced' : 'Not Balanced';
        if ($remarks !== 'Balanced') {
            $hasMismatch = true;
        }
        $totalEntered += $balance;

        $reconciliationData = [
            'session_id' => $postdata['id'],
            'wallet_id' => $channelId,
            'balance' => $balance,
            'expected_balance' => $expected,
            'variance' => $variance,
            'remarks' => $remarks,
            'proof_image' => $proofImagePath,
            'denos_json' => $denosJson,
            'system_denos_json' => $isCash ? (
                $channelId === 'cash:GHS'
                    ? $normalizeDenoHistory((array)($drawerSnapshot['ghs_history'] ?? []), 'GHS')
                    : (
                        $channelId === 'cash:CFA'
                            ? $normalizeDenoHistory((array)($drawerSnapshot['cfa_history'] ?? []), 'CFA')
                            : ''
                    )
            ) : '',
            'date_created' => $reconciliationCreatedAt,
            'created_by' => $user_id,
        ];

        $reconciliation->insert($reconciliationData);
        if (!empty($reconciliation->has_error)) {
            $errors[] = 'Failed to insert reconciliation for channel ' . $channelId . ': ' . ($reconciliation->error ?? '');
        } else {
            $successCount++;
        }
    }

    if (!empty($formErrors)) {
        set_value('errors', $formErrors);
    }
    if (!empty($errors)) {
		// Keep the user's input and any successfully uploaded proofs
		set_value('recon_old', ['rows' => $rows]);
        message_fail(implode(' ', $errors));
        redirect($admin_route . '/' . $plugin_route . '/reconciliation');
        exit;
    }

    $diff = $totalExpected - $totalEntered;
    $totalFinal = $totalEntered;
    if (abs($diff) >= 0.01) {
        $miscData = [
            'session_id' => $postdata['id'],
            'wallet_id' => 'misc',
            'balance' => $diff,
            'expected_balance' => 0,
            'variance' => 0,
            'remarks' => 'Miscellaneous',
            'date_created' => $reconciliationCreatedAt,
            'created_by' => $user_id,
        ];
        $reconciliation->insert($miscData);
        $totalFinal = $totalEntered + $diff;
        $hasMismatch = false;
    }

    // Prepare summary data for insertion
    $reconciliation_summary = new \BackOffices\Reconciliation_summary;

    $summaryRemarks = $hasMismatch ? 'Not Balanced' : 'Balanced';
    $variance = 0;
    $summaryStatus = backoffice_user_is_admin($session) ? 'Approved' : 'Submitted';

    $reconciliationSummary = [
        'id' => $postdata['id'],
        'session_id' => $postdata['id'],
        'late_access_id' => !empty($lateReconciliationAccess) ? (int)($lateReconciliationAccess->id ?? 0) : null,
        'balance' => $totalFinal,
        'expected_balance' => $totalExpected,
        'variance' => $variance,
        'remarks' => $summaryRemarks,
        'transaction_status' => $summaryStatus,
        'rejection_reason' => '',
        'approved_by' => $summaryStatus === 'Approved' ? (string)$user_id : null,
        'date_approved' => $summaryStatus === 'Approved' ? $now : null,
        'date_created' => $reconciliationCreatedAt,
        'date_updated' => $now,
        'created_by' => $user_id,
    ];

    $reconciliation_summary->insert($reconciliationSummary);

    if (!empty($reconciliation_summary->has_error)) {
        message_fail('Failed to save reconciliation summary: ' . ($reconciliation_summary->error ?? ''));
        redirect($admin_route . '/' . $plugin_route . '/reconciliation');
        exit;
    }

    backoffice_whatsapp_notify_transaction([
        'type' => 'Reconciliation',
        'id' => (int)$postdata['id'],
        'status' => $summaryStatus,
        'creator_id' => (int)$user_id,
        'assigned_id' => 0,
        'details' => [
            'Balance' => 'GHS ' . number_format($totalFinal, 2),
            'Expected balance' => 'GHS ' . number_format($totalExpected, 2),
            'Result' => $summaryRemarks,
        ],
        'view_url' => ROOT . '/admin/back_office/view_reconciliation/' . (int)$postdata['id'],
    ]);

    if (!empty($lateReconciliationAccess)) {
        try {
            $dbLateAccess = new \Core\Database;
            $dbLateAccess->query(
                "UPDATE reconciliation_late_access
                 SET status = 'used', used_at = :used_at, notification_seen_at = COALESCE(notification_seen_at, :used_at), date_updated = :used_at
                 WHERE id = :id AND user_id = :user_id",
                [
                    'used_at' => $now,
                    'id' => (int)$lateReconciliationAccess->id,
                    'user_id' => (int)$user_id,
                ]
            );
        } catch (\Throwable $e) {
            // Reconciliation has already been saved.
        }
    }

    // Log the action
    $action = 'added';
    $transaction = get_date($postdata['date_created']);
    $table = 'reconciliations';
    $description = "$username $action a new reconciliation for $transaction";
    $date_created = $now;

    $log = new \BackOffices\Log;
    $log_activity = $log->insert([
        'table_name' => $table,
        'action' => $action,
        'user_id' => $user_id,
        'user_name' => $username,
        'description' => $description,
        'date_created' => $date_created,
    ]);

    message_success("New reconciliation added successfully!");
	set_value('recon_old', null);
    redirect($admin_route . '/' . $plugin_route);
}

if (!$csrf) {
    $reconciliation->errors['usn'] = "Form expired!";
}

set_value('errors', $reconciliation->errors);
?>

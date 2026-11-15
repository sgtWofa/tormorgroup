<?php

namespace Agencybankings;

use Core\Database;

if (!function_exists(__NAMESPACE__ . '\\agency_find_active_drawer')) {
    function agency_find_active_drawer(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $db = new Database;
        $today = date('Y-m-d');
        $row = $db->query(
            "SELECT id, user_id FROM cashdrawers WHERE user_id = :user_id AND DATE(date_created) = :today ORDER BY id DESC LIMIT 1",
            ['user_id' => $userId, 'today' => $today]
        );

        if (empty($row)) {
            $row = $db->query(
                "SELECT id, user_id FROM cashdrawers WHERE user_id = :user_id ORDER BY date_created DESC, id DESC LIMIT 1",
                ['user_id' => $userId]
            );
        }

        if (empty($row)) {
            return null;
        }

        return [
            'drawer_id' => (int)($row[0]->id ?? 0),
            'drawer_user_id' => (int)($row[0]->user_id ?? $userId),
        ];
    }
}

if (!function_exists(__NAMESPACE__ . '\\agency_normalize_denominations')) {
	function agency_normalize_denominations(array $currencyArray, float $fallbackAmount = 0.0): array
	{
		$out = [];
		if (!is_array($currencyArray)) {
			return $out;
		}

		foreach ($currencyArray as $row) {
			if (!is_array($row)) {
				continue;
			}
			$currency = strtoupper(trim((string)($row['currency'] ?? 'GHS')));
			if ($currency !== 'GHS') {
				continue;
			}

			$denom = (float)($row['denomination'] ?? 0);
			$count = (int)($row['count'] ?? 0);
			$volume = (float)($row['volume'] ?? 0);
			if (!is_finite($denom) || $denom <= 0 || $count <= 0) {
				continue;
			}
			if (!is_finite($volume) || $volume <= 0) {
				$volume = $denom * $count;
			}
			if (!is_finite($volume) || $volume <= 0) {
				continue;
			}

			$out[] = [
				'currency' => 'GHS',
				'denomination' => $denom,
				'count' => $count,
				'volume' => $volume,
			];
		}

		return $out;
	}
}

if (!function_exists(__NAMESPACE__ . '\\agency_remove_cashdrawer_entries')) {
	function agency_remove_cashdrawer_entries(int $transId): void
	{
		if ($transId <= 0) {
			return;
		}
		$db = new Database;
		if ($db->table_exists(['drawer_denos'])) {
			$db->query(
				"DELETE FROM drawer_denos WHERE trans_id = :trans_id",
				['trans_id' => $transId]
			);
		}
	}
}

if (!function_exists(__NAMESPACE__ . '\\agency_remove_drawer_balance_entries')) {
	function agency_remove_drawer_balance_entries(int $transId): void
	{
		if ($transId <= 0) {
			return;
		}
		$db = new Database;
		if ($db->table_exists(['drawer_balances'])) {
			$db->query(
				"DELETE FROM drawer_balances WHERE trans_id = :trans_id",
				['trans_id' => $transId]
			);
		}
	}
}

if (!function_exists(__NAMESPACE__ . '\\agency_refresh_cash_channel')) {
	function agency_refresh_cash_channel(int $drawerId, int $drawerUserId, int $transId, string $transStatus): void
	{
		if ($drawerId <= 0 || $drawerUserId <= 0) {
			return;
		}
		$db = new Database;
		if (!$db->table_exists(['drawer_balances', 'drawer_denos'])) {
			return;
		}

		$sumRows = $db->query(
			"SELECT 
				COALESCE(SUM(volume),0) AS total_volume,
				COALESCE(SUM(CASE WHEN volume > 0 THEN volume ELSE 0 END),0) AS total_credit,
				COALESCE(SUM(CASE WHEN volume < 0 THEN ABS(volume) ELSE 0 END),0) AS total_debit
			 FROM drawer_denos
			 WHERE drawer_id = :drawer_id AND user_id = :user_id",
			[
				'drawer_id' => $drawerId,
				'user_id' => $drawerUserId,
			]
		);
		$currentClosing = !empty($sumRows) ? (float)($sumRows[0]->total_volume ?? 0) : 0.0;
		$totalCredit = !empty($sumRows) ? (float)($sumRows[0]->total_credit ?? 0) : 0.0;
		$totalDebit = !empty($sumRows) ? (float)($sumRows[0]->total_debit ?? 0) : 0.0;

		$rowsBal = $db->query(
			"SELECT * FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'cash' AND channel_key = 'CASH_DRAWER' LIMIT 1",
			[
				'drawer_id' => $drawerId,
				'user_id' => $drawerUserId,
			]
		);
		$now = date('Y-m-d H:i:s');
		if (empty($rowsBal)) {
			$db->query(
				"INSERT INTO drawer_balances (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status)
				 VALUES (:drawer_id, :user_id, 'cash', 'CASH_DRAWER', :opening, :credit, :debit, :closing, :date_created, :trans_id, :trans_status)",
				[
					'drawer_id' => $drawerId,
					'user_id' => $drawerUserId,
					'opening' => 0.0,
					'credit' => $totalCredit,
					'debit' => $totalDebit,
					'closing' => $currentClosing,
					'date_created' => $now,
					'trans_id' => $transId,
					'trans_status' => $transStatus,
				]
			);
			return;
		}

		$rowBal = $rowsBal[0];
		$opening = (float)($rowBal->opening_balance ?? 0);
		$credit = max($totalCredit - $opening, 0.0);
		$db->query(
			"UPDATE drawer_balances SET credit = :credit, debit = :debit, closing_balance = :closing, trans_id = :trans_id, trans_status = :trans_status WHERE id = :id",
			[
				'credit' => $credit,
				'debit' => $totalDebit,
				'closing' => $currentClosing,
				'trans_id' => $transId,
				'trans_status' => $transStatus,
				'id' => $rowBal->id,
			]
		);
	}
}

if (!function_exists(__NAMESPACE__ . '\\agency_refresh_ecash_channel')) {
	function agency_refresh_ecash_channel(int $drawerId, int $drawerUserId, string $channelKey, string $direction, float $amount, int $transId, string $transStatus, ?float $seedOpening = null): void
	{
		if ($drawerId <= 0 || $drawerUserId <= 0) {
			return;
		}
		$channelKey = strtoupper(trim((string)$channelKey));
		if ($channelKey === '') {
			return;
		}
		$direction = strtolower(trim((string)$direction));
		$amount = (float)$amount;
		if (!is_finite($amount) || $amount <= 0) {
			return;
		}

		$db = new Database;
		if (!$db->table_exists(['drawer_balances'])) {
			return;
		}

		$rowsBal = $db->query(
			"SELECT id, opening_balance, credit, debit FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash' AND channel_key = :channel_key ORDER BY id DESC LIMIT 1",
			[
				'drawer_id' => $drawerId,
				'user_id' => $drawerUserId,
				'channel_key' => $channelKey,
			]
		);

		$opening = 0.0;
		$totalCredit = 0.0;
		$totalDebit = 0.0;
		$existingId = 0;
		if (!empty($rowsBal)) {
			$rowBal = $rowsBal[0];
			$existingId = (int)($rowBal->id ?? 0);
			$opening = (float)($rowBal->opening_balance ?? 0);
			$totalCredit = (float)($rowBal->credit ?? 0);
			$totalDebit = (float)($rowBal->debit ?? 0);
		} elseif ($seedOpening !== null && is_finite($seedOpening) && $seedOpening > 0) {
			$opening = (float)$seedOpening;
		}

		if ($direction === 'in') {
			$totalCredit += $amount;
		} else {
			$totalDebit += $amount;
		}

		$closing = $opening + $totalCredit - $totalDebit;
		$now = date('Y-m-d H:i:s');

		if ($existingId <= 0) {
			$db->query(
				"INSERT INTO drawer_balances (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status)
				 VALUES (:drawer_id, :user_id, 'ecash', :channel_key, :opening, :credit, :debit, :closing, :date_created, :trans_id, :trans_status)",
				[
					'drawer_id' => $drawerId,
					'user_id' => $drawerUserId,
					'channel_key' => $channelKey,
					'opening' => $opening,
					'credit' => $totalCredit,
					'debit' => $totalDebit,
					'closing' => $closing,
					'date_created' => $now,
					'trans_id' => $transId,
					'trans_status' => $transStatus,
				]
			);
			return;
		}

		$db->query(
			"UPDATE drawer_balances SET credit = :credit, debit = :debit, closing_balance = :closing, trans_id = :trans_id, trans_status = :trans_status WHERE id = :id",
			[
				'credit' => $totalCredit,
				'debit' => $totalDebit,
				'closing' => $closing,
				'trans_id' => $transId,
				'trans_status' => $transStatus,
				'id' => $existingId,
			]
		);
	}
}

if (!function_exists(__NAMESPACE__ . '\\agency_fetch_admin_manager_drawers_today')) {
    function agency_fetch_admin_manager_drawers_today(): array
    {
        $db = new Database;
        $today = date('Y-m-d');

        // Roles are stored in user_roles.role (e.g. 'admin', 'manager', 'operations manager')
        try {
            $roleNames = [
                'admin',
                'manager',
                'general manager',
                'general_manager',
                'generalmanager',
                'gm',
                'operations manager',
                'operations_manager',
                'operationsmanager',
                'op manager',
                'op_manager',
            ];
            $roleParams = [];
            $rolePlaceholders = [];
            foreach ($roleNames as $i => $roleName) {
                $key = 'role' . (string)$i;
                $roleParams[$key] = strtolower((string)$roleName);
                $rolePlaceholders[] = ':' . $key;
            }
            $params = array_merge(['today' => $today], $roleParams);
            $rows = $db->query(
                "SELECT DISTINCT c.id AS drawer_id, c.user_id AS drawer_user_id
                 FROM cashdrawers AS c
                 INNER JOIN user_roles_map AS urm ON urm.user_id = c.user_id AND (urm.disabled = 0 OR urm.disabled IS NULL)
                 INNER JOIN user_roles AS r ON r.id = urm.role_id AND (r.disabled = 0 OR r.disabled IS NULL)
                 WHERE DATE(c.date_created) = :today
                   AND LOWER(r.role) IN (" . implode(',', $rolePlaceholders) . ")",
                $params
            );
        } catch (\Throwable $e) {
            $rows = [];
        }

        if (empty($rows) || !is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $drawerId = (int)($r->drawer_id ?? 0);
            $drawerUserId = (int)($r->drawer_user_id ?? 0);
            if ($drawerId > 0 && $drawerUserId > 0) {
                $out[] = ['drawer_id' => $drawerId, 'drawer_user_id' => $drawerUserId];
            }
        }
        return $out;
    }
}

if (!function_exists(__NAMESPACE__ . '\\agency_find_existing_ecash_channel_key')) {
    function agency_find_existing_ecash_channel_key(int $drawerId, int $drawerUserId, string $channelKey): ?string
    {
        if ($drawerId <= 0 || $drawerUserId <= 0 || $channelKey === '') {
            return null;
        }

        $db = new Database;
        if (!$db->table_exists(['drawer_balances'])) {
            return null;
        }

        $channelKey = strtoupper(trim($channelKey));
		if ($channelKey === '') {
			return null;
		}

		$base = $channelKey;
		if (strpos($base, 'ECASH_') === 0) {
			$base = substr($base, 6);
		}
		$base = strtoupper(trim($base));
		if ($base === '') {
			return null;
		}

		$variants = [];
		foreach (agency_expand_ecash_channel_aliases($base) as $aliasBase) {
			$variantUnderscore = str_replace([' ', '-'], '_', $aliasBase);
			$variantUnderscore = preg_replace('/_+/', '_', (string)$variantUnderscore);
			$variantUnderscore = trim((string)$variantUnderscore, '_');

			$variantHyphen = str_replace([' ', '_'], '-', $aliasBase);
			$variantHyphen = preg_replace('/-+/', '-', (string)$variantHyphen);
			$variantHyphen = trim((string)$variantHyphen, '-');

			$variants[] = $aliasBase;
			$variants[] = $variantUnderscore;
			$variants[] = $variantHyphen;
			$variants[] = 'ECASH_' . $aliasBase;
			$variants[] = 'ECASH_' . $variantUnderscore;
			$variants[] = 'ECASH_' . $variantHyphen;
		}
		$variants = array_values(array_unique(array_filter($variants)));

        try {
			$params = [
				'drawer_id' => $drawerId,
				'user_id' => $drawerUserId,
			];
			$whereParts = [];
			foreach ($variants as $i => $v) {
				$key = 'k' . (string)$i;
				$params[$key] = $v;
				$whereParts[] = 'channel_key = :' . $key;
			}
			if (empty($whereParts)) {
				return null;
			}
			$sql = "SELECT channel_key FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash' AND (" . implode(' OR ', $whereParts) . ") ORDER BY id DESC LIMIT 1";
			$rows = $db->query($sql, $params);
            if (empty($rows)) {
                return null;
            }
            $stored = strtoupper(trim((string)($rows[0]->channel_key ?? '')));
            return $stored !== '' ? $stored : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists(__NAMESPACE__ . '\\agency_drawer_has_ecash_channel')) {
    function agency_drawer_has_ecash_channel(int $drawerId, int $drawerUserId, string $channelKey): bool
    {
        if ($drawerId <= 0 || $drawerUserId <= 0 || $channelKey === '') {
            return false;
        }

        $db = new Database;
        if (!$db->table_exists(['drawer_balances'])) {
            return false;
        }

        $channelKey = strtoupper(trim($channelKey));
        $altKey = $channelKey;
        if (strpos($channelKey, 'ECASH_') === 0) {
            $altKey = substr($channelKey, 6);
        } else {
            $altKey = 'ECASH_' . $channelKey;
        }

        $rows = $db->query(
            "SELECT id FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash' AND (channel_key = :channel_key OR channel_key = :alt_key) LIMIT 1",
            [
                'drawer_id' => $drawerId,
                'user_id' => $drawerUserId,
                'channel_key' => $channelKey,
                'alt_key' => $altKey,
            ]
        );

        return !empty($rows);
    }
}

if (!function_exists(__NAMESPACE__ . '\\agency_expand_ecash_channel_aliases')) {
	function agency_expand_ecash_channel_aliases(string $base): array
	{
		$base = strtoupper(trim((string)$base));
		$base = preg_replace('/[^A-Z0-9]+/i', '_', $base);
		$base = trim((string)$base, '_');
		if ($base === '') {
			return [];
		}

		$aliases = [$base];
		if (in_array($base, ['MTN_MOMOPAY', 'MTN_MOMOPAY_OUT'], true)) {
			$aliases[] = 'MTN_MOMOPAY';
			$aliases[] = 'MTN_MOMOPAY_OUT';
		}
		$aliasMap = [
			'FIDELITY' => ['FIDELITY_BANK'],
			'FIDELITY_BANK' => ['FIDELITY'],
		];

		foreach (($aliasMap[$base] ?? []) as $alias) {
			$aliases[] = $alias;
		}

		return array_values(array_unique(array_filter($aliases)));
	}
}

if (!function_exists(__NAMESPACE__ . '\\agency_build_ecash_channel_variants')) {
	function agency_build_ecash_channel_variants(string $channelKeyRaw, string $networkRaw = ''): array
	{
		$variants = [];
		$raws = [$channelKeyRaw, $networkRaw];
		foreach ($raws as $raw) {
			$raw = trim((string)$raw);
			if ($raw === '') {
				continue;
			}
			$upper = strtoupper($raw);
			$upper = preg_replace('/[^A-Z0-9]+/i', '_', $upper);
			$upper = trim((string)$upper, '_');
			if ($upper === '') {
				continue;
			}
			$base = $upper;
			if (strpos($base, 'ECASH_') === 0) {
				$base = substr($base, 6);
			}
			$base = trim((string)$base, '_');
			foreach (agency_expand_ecash_channel_aliases($base) as $aliasBase) {
				$variants[] = $aliasBase;
				$variants[] = 'ECASH_' . $aliasBase;
			}
		}
		$variants = array_values(array_unique(array_filter($variants)));
		return $variants;
	}
}

if (!function_exists(__NAMESPACE__ . '\\agency_find_shared_momopay_context')) {
	function agency_find_shared_momopay_context(?int $transId = null, ?string $date = null): ?array
	{
		$db = new Database;
		if (!$db->table_exists(['drawer_balances', 'cashdrawers'])) {
			return null;
		}

		$normalizedKeySql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(db.channel_key)), 'ECASH_', ''), 'ECASH-', ''), '_', ''), '-', ''), ' ', '')";
		$params = [];
		$where = [
			"db.channel_type = 'ecash'",
			$normalizedKeySql . " IN ('MTNMOMOPAY', 'MTNMOMOPAYOUT')",
		];

		if (($transId ?? 0) > 0) {
			$where[] = 'db.trans_id = :trans_id';
			$params['trans_id'] = $transId;
		} else {
			$where[] = 'DATE(cd.date_created) = :drawer_date';
			$params['drawer_date'] = $date ?: date('Y-m-d');
		}

		$rows = $db->query(
			"SELECT db.drawer_id, db.user_id AS drawer_user_id, db.channel_key,
			        db.opening_balance, db.credit, db.debit, db.closing_balance, db.trans_id
			 FROM drawer_balances AS db
			 INNER JOIN cashdrawers AS cd ON cd.id = db.drawer_id
			 WHERE " . implode(' AND ', $where) . "
			 ORDER BY CASE WHEN LOWER(COALESCE(db.trans_status, '')) = 'opening snapshot' THEN 1 ELSE 0 END,
			          db.id DESC
			 LIMIT 1",
			$params
		);
		if (!empty($db->has_error)) {
			throw new \RuntimeException('Unable to resolve the shared MTN MoMoPay cashdrawer account.');
		}

		if (empty($rows)) {
			return null;
		}

		$row = $rows[0];
		$row->closing_balance = (float)($row->closing_balance ?? (
			(float)($row->opening_balance ?? 0) +
			(float)($row->credit ?? 0) -
			(float)($row->debit ?? 0)
		));
		return [
			'drawer_id' => (int)($row->drawer_id ?? 0),
			'drawer_user_id' => (int)($row->drawer_user_id ?? 0),
			'channel_key' => (string)($row->channel_key ?? ''),
			'closing_balance' => (float)$row->closing_balance,
			'trans_id' => (int)($row->trans_id ?? 0),
		];
	}
}

if (!function_exists(__NAMESPACE__ . '\\agency_get_shared_ecash_balance')) {
	function agency_get_shared_ecash_balance(string $channelKeyRaw, string $networkRaw = '', ?string $today = null): float
	{
		$variants = agency_build_ecash_channel_variants($channelKeyRaw, $networkRaw);
		if (empty($variants)) {
			return 0.0;
		}

		$db = new Database;
		if (!$db->table_exists(['drawer_balances','cashdrawers','user_roles_map','user_roles'])) {
			return 0.0;
		}

		$today = $today ?: date('Y-m-d');
		$params = ['today' => $today];
		$whereParts = [];
		foreach ($variants as $i => $v) {
			$key = 'k' . (string)$i;
			$params[$key] = $v;
			$whereParts[] = 'db.channel_key = :' . $key;
		}
		if (empty($whereParts)) {
			return 0.0;
		}

		$roleNames = [
			'admin',
			'manager',
			'general manager',
			'general_manager',
			'generalmanager',
			'gm',
			'operations manager',
			'operations_manager',
			'operationsmanager',
			'op manager',
			'op_manager',
		];
		$rolePlaceholders = [];
		foreach ($roleNames as $i => $roleName) {
			$key = 'role' . (string)$i;
			$params[$key] = strtolower((string)$roleName);
			$rolePlaceholders[] = ':' . $key;
		}

		$normalizedMomoPaySql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(db.channel_key)), 'ECASH_', ''), 'ECASH-', ''), '_', ''), '-', ''), ' ', '')";
		$sql = "SELECT db.opening_balance, db.credit, db.debit, db.closing_balance
		        FROM drawer_balances AS db
		        INNER JOIN cashdrawers AS cd ON cd.id = db.drawer_id
		        LEFT JOIN user_roles_map AS urm ON urm.user_id = cd.user_id AND (urm.disabled = 0 OR urm.disabled IS NULL)
		        LEFT JOIN user_roles AS r ON r.id = urm.role_id AND (r.disabled = 0 OR r.disabled IS NULL)
		        WHERE db.channel_type = 'ecash'
		          AND DATE(cd.date_created) = :today
		          AND (
		            LOWER(r.role) IN (" . implode(',', $rolePlaceholders) . ")
		            OR " . $normalizedMomoPaySql . " IN ('MTNMOMOPAY', 'MTNMOMOPAYOUT')
		          )
		          AND (" . implode(' OR ', $whereParts) . ")
		        ORDER BY CASE
		                   WHEN " . $normalizedMomoPaySql . " IN ('MTNMOMOPAY', 'MTNMOMOPAYOUT')
		                        AND LOWER(COALESCE(db.trans_status, '')) = 'opening snapshot' THEN 1
		                   ELSE 0
		                 END,
		                 db.id DESC
		        LIMIT 1";

		try {
			$row = ($db->query($sql, $params) ?: [])[0] ?? null;
			if (!$row) {
				return 0.0;
			}
			$opening = (float)($row->opening_balance ?? 0);
			$credit  = (float)($row->credit ?? 0);
			$debit   = (float)($row->debit ?? 0);
			$closing = (float)($row->closing_balance ?? ($opening + $credit - $debit));
			return is_finite($closing) ? $closing : 0.0;
		} catch (\Throwable $e) {
			return 0.0;
		}
	}
}

if (!function_exists(__NAMESPACE__ . '\\agency_get_drawer_channel_balance')) {
    function agency_get_drawer_channel_balance(int $drawerId, int $drawerUserId, string $channelType, string $channelKey): float
    {
        if ($drawerId <= 0 || $drawerUserId <= 0 || $channelType === '' || $channelKey === '') {
            return 0.0;
        }

        $db = new Database;
        if (!$db->table_exists(['drawer_balances'])) {
            return 0.0;
        }

        $channelType = strtolower(trim($channelType));
        $channelKey = strtoupper(trim($channelKey));
        if ($channelType === '' || $channelKey === '') {
            return 0.0;
        }

        $params = [
            'drawer_id' => $drawerId,
            'user_id' => $drawerUserId,
            'channel_type' => $channelType,
            'channel_key' => $channelKey,
        ];

        $sql = "SELECT closing_balance FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = :channel_type AND channel_key = :channel_key ORDER BY id DESC LIMIT 1";

        if ($channelType === 'ecash') {
            $altKey = $channelKey;
            if (strpos($channelKey, 'ECASH_') === 0) {
                $altKey = substr($channelKey, 6);
            } else {
                $altKey = 'ECASH_' . $channelKey;
            }
            $params['alt_key'] = $altKey;
            $sql = "SELECT closing_balance FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = :channel_type AND (channel_key = :channel_key OR channel_key = :alt_key) ORDER BY id DESC LIMIT 1";
        }

        try {
            $rows = $db->query($sql, $params);
            if (empty($rows)) {
                return 0.0;
            }
            $bal = (float)($rows[0]->closing_balance ?? 0);
            return is_finite($bal) ? $bal : 0.0;
        } catch (\Throwable $e) {
            return 0.0;
        }
    }
}

if (!function_exists(__NAMESPACE__ . '\\agency_channel_deficit')) {
    function agency_channel_deficit(float $available, float $required): float
    {
        $available = (float)$available;
        $required = (float)$required;
        if (!is_finite($available)) {
            $available = 0.0;
        }
        if (!is_finite($required)) {
            $required = 0.0;
        }
        $deficit = $required - $available;
        return $deficit > 0 ? $deficit : 0.0;
    }
}

if (!function_exists(__NAMESPACE__ . '\\agency_resolve_ecash_channel_key')) {
    function agency_resolve_ecash_channel_key(int $drawerId, int $drawerUserId, string $networkRaw): ?string
    {
        if ($drawerId <= 0 || $drawerUserId <= 0) {
            return null;
        }

        $networkRaw = trim((string)$networkRaw);
        if ($networkRaw === '') {
            return null;
        }

        $normalized = strtoupper(preg_replace('/[^A-Z0-9]+/i', '_', $networkRaw));
        $normalized = trim($normalized, '_');
        if ($normalized === '') {
            return null;
        }
        $compactNeedle = str_replace('_', '', $normalized);

        $db = new Database;
        if (!$db->table_exists(['drawer_balances'])) {
            return null;
        }

        $rows = $db->query(
            "SELECT channel_key FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash'",
            [
                'drawer_id' => $drawerId,
                'user_id' => $drawerUserId,
            ]
        );
        if (empty($rows)) {
            return null;
        }

        $bestKey = null;
        $bestScore = 0;
        foreach ($rows as $row) {
            $key = strtoupper(trim((string)($row->channel_key ?? '')));
            if ($key === '') {
                continue;
            }

            $candidate = $key;
            if (strpos($candidate, 'ECASH_') === 0) {
                $candidate = substr($candidate, 6);
            }
            $compactSlug = str_replace('_', '', $candidate);
            if ($compactSlug === $compactNeedle) {
                return $key;
            }

            $score = 0;
            if ($compactNeedle !== '' && strpos($compactSlug, $compactNeedle) !== false) {
                $score = strlen($compactNeedle);
            } elseif ($compactSlug !== '' && strpos($compactNeedle, $compactSlug) !== false) {
                $score = strlen($compactSlug);
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestKey = $key;
            }
        }

        return $bestKey;
    }
}

if (!function_exists(__NAMESPACE__ . '\\agency_resolve_transaction_amount')) {
    function agency_resolve_transaction_amount(array $transaction, array $currencyArray): float
    {
        $candidates = [
            $transaction['amount'] ?? null,
            $transaction['load'] ?? null,
            $transaction['amount_withdrawn'] ?? null,
            $transaction['amount_payable'] ?? null,
            $transaction['payable'] ?? null,
            $transaction['amount_to_pay'] ?? null,
            $transaction['balance_before'] ?? null,
            $transaction['paid'] ?? null,
            $transaction['amount_paid'] ?? null,
        ];

        foreach ($candidates as $value) {
            $amount = (float)($value ?? 0);
            if (is_finite($amount) && $amount > 0) {
                return $amount;
            }
        }

        $sum = 0.0;
        if (is_array($currencyArray)) {
            foreach ($currencyArray as $row) {
                $currency = strtoupper((string)($row['currency'] ?? 'GHS'));
                if ($currency !== 'GHS') {
                    continue;
                }
                $volume = (float)($row['volume'] ?? 0);
                if (is_finite($volume) && $volume > 0) {
                    $sum += $volume;
                }
            }
        }
        return $sum;
    }
}

if (!function_exists(__NAMESPACE__ . '\\agency_resolve_ecash_amount')) {
	function agency_resolve_ecash_amount(array $transaction, float $cashAmount): float
	{
		$cashAmount = (float)$cashAmount;
		if (!is_finite($cashAmount) || $cashAmount < 0) {
			$cashAmount = 0;
		}

		$txType = strtoupper(trim((string)($transaction['transaction_type'] ?? '')));
		$network = strtoupper(trim((string)($transaction['network'] ?? '')));
		$isGhqrCit = ($network === 'GHQR' && strpos($txType, strtoupper('Withdrawal (CIT)')) !== false);

		// Withdrawals: e-cash movement is typically the amount withdrawn (cash paid out can be higher/lower).
		if (strpos($txType, 'WITHDRAWAL') !== false) {
			$withdrawn = (float)($transaction['amount_withdrawn'] ?? 0);
			if (is_finite($withdrawn) && $withdrawn > 0) {
				return $withdrawn;
			}

			if ($isGhqrCit) {
				$payable = (float)($transaction['amount_payable'] ?? 0);
				if (is_finite($payable) && $payable > 0) {
					return $payable;
				}
			}

			return $cashAmount;
		}

		// Deposits: e-cash movement follows the deposit amount.
		if (strpos($txType, 'DEPOSIT') !== false) {
			$amount = (float)($transaction['amount'] ?? 0);
			if (is_finite($amount) && $amount > 0) {
				return $amount;
			}
			$paid = (float)($transaction['amount_paid'] ?? 0);
			if (is_finite($paid) && $paid > 0) {
				return $paid;
			}
			return $cashAmount;
		}

		return $cashAmount;
	}
}

if (!function_exists(__NAMESPACE__ . '\\agency_is_ezwich_transfer')) {
	function agency_is_ezwich_transfer(array $transaction): bool
	{
		// Ezwich plugin submits USN / transaction_id fields.
		$hasUsn = trim((string)($transaction['usn'] ?? '')) !== '';
		$txIdRaw = trim((string)($transaction['transaction_id'] ?? ''));
		$hasTxId = $txIdRaw !== '';
		if (!$hasUsn && !$hasTxId) {
			return false;
		}
		// Extra guard: ezwich transaction_id is typically an 11-digit number.
		if ($hasTxId && preg_match('/^\d{11}$/', $txIdRaw)) {
			return true;
		}
		// USN is a strong signal as well.
		if ($hasUsn) {
			return true;
		}
		return false;
	}
}

    if (!function_exists(__NAMESPACE__ . '\\agency_canonicalize_drawer_bank_name')) {
        function agency_canonicalize_drawer_bank_name(string $name): string
        {
            $name = trim(preg_replace('/\s+/', ' ', (string)$name));
            if ($name === '') {
                return '';
            }

            $upper = strtoupper($name);
            $upper = preg_replace('/[^A-Z0-9]+/', '_', $upper);
            $upper = trim($upper, '_');

            if (in_array($upper, ['ABSA_FORMERLY_BARCLAYS', 'ABSA_FORMERLY_BARCLAYS_BANK', 'ABSA_BARCLAYS', 'BARCLAYS', 'BARCLAYS_BANK'], true)) {
                return 'Absa Bank';
            }

            $needsBankSuffix = [
                'ABSA' => 'Absa Bank',
                'FIDELITY' => 'Fidelity Bank',
                'GCB' => 'GCB Bank',
                'APEX_NAFANA' => 'Apex Nafana Bank',
                'APEX_SUMA' => 'Apex Suma Bank',
            ];

            if (isset($needsBankSuffix[$upper])) {
                return $needsBankSuffix[$upper];
            }

            if (in_array($upper, ['APEX-NAFANA', 'APEX_NAFANA_BANK', 'APEX_NAFANA'], true)) {
                return 'Apex Nafana Bank';
            }
            if (in_array($upper, ['APEX-SUMA', 'APEX_SUMA_BANK', 'APEX_SUMA'], true)) {
                return 'Apex Suma Bank';
            }

            return $name;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\\agency_pick_ecash_channel_name')) {
        function agency_pick_ecash_channel_name(array $transaction): string
        {
            $txType = strtolower(trim((string)($transaction['transaction_type'] ?? '')));
            $network = strtoupper(trim((string)($transaction['network'] ?? '')));

            // Ezwich transfers are paid to the shared MTN MoMoPay account.
            if (agency_is_ezwich_transfer($transaction)) {
                return 'MTN MOMOPAY';
            }
            if ($network === 'GHQR' && $txType === strtolower('Withdrawal (CIT)')) {
                return 'Fidelity';
            }
            // ... existing logic ...
            return $network;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\\agency_determine_ecash_channel')) {
        function agency_determine_ecash_channel(array $transaction): ?array
        {
            $channelNameRaw = agency_pick_ecash_channel_name($transaction);
            $channelNameRaw = agency_canonicalize_drawer_bank_name($channelNameRaw);
            if ($channelNameRaw === '') {
                return null;
            }

            $network = strtoupper(preg_replace('/[^A-Z0-9]+/i', '_', $channelNameRaw));
            $network = trim($network, '_');
            $walletOutId = (int)($transaction['wallet_out_id'] ?? 0);
            $walletInId = (int)($transaction['wallet_in_id'] ?? 0);
            $direction = null;
            $channelKeyPrefix = 'ECASH_';

            // Hard-normalize eZwich transactions to MTN MOMOPAY (separate from other MTN channels like MTN / MTN EVD).
            if (agency_is_ezwich_transfer($transaction)) {
                $channelNameRaw = 'MTN MOMOPAY';
                $network = 'MTN_MOMOPAY';
                $direction = 'in';
            }

            $walletNetworkRaw = trim((string)($transaction['network'] ?? ''));

            try {
                $walletModel = new \Agencybankings\Wallet;
                $networkWallet = $walletNetworkRaw !== '' ? ($walletModel->where(['network' => $walletNetworkRaw])[0] ?? null) : null;
                if (!empty($networkWallet)) {
                    $channelKeyPrefix = 'ECASH_';
                    $networkWalletId = (int)($networkWallet->id ?? 0);
                    if ($networkWalletId > 0) {
                        if ($walletOutId === $networkWalletId) {
                            $direction = 'out';
                        } elseif ($walletInId === $networkWalletId) {
                            $direction = 'in';
                        }
                    }
                }
            } catch (\Throwable $th) {
                // Ignore lookup errors and fall back to heuristics
            }

            // Force business direction from transaction type (overrides wallet-id heuristics)
            $txType = strtoupper((string)($transaction['transaction_type'] ?? ''));
            if (strpos($txType, 'WITHDRAWAL') !== false) {
                // Withdrawals: agent pays cash out and receives e-cash/bank value in
                $direction = 'in';
            } elseif (strpos($txType, 'DEPOSIT') !== false) {
                // Deposits: agent receives cash and sends e-cash/bank value out
                $direction = 'out';
            } elseif (strpos($txType, 'INTERBANK') !== false || strpos($txType, 'GIP') !== false) {
                // Interbank/GIP: funds leave the bank channel to settle the transfer
                $direction = 'out';
            } elseif (strpos($txType, 'TRANSFER') !== false) {
                // Transfers: default to value being sent out through the selected bank/telco channel
                // BUT: eZwich transfers are paid to the shared MTN MoMoPay account (value comes in).
                $direction = agency_is_ezwich_transfer($transaction) ? 'in' : 'out';
            } elseif ($direction === null) {
                $direction = 'in';
            }

            // Final override: eZwich should ALWAYS credit MTN MOMOPAY (value comes in).
            if (agency_is_ezwich_transfer($transaction)) {
                $direction = 'in';
            }

            return [
                'channel_key' => $channelKeyPrefix . $network,
                'direction' => $direction,
                'channel_name_raw' => $channelNameRaw,
            ];
        }
    }

if (!function_exists(__NAMESPACE__ . '\\agency_table_exists')) {
	function agency_table_exists(string $tableName): bool
	{
		static $cache = [];
		$tableName = trim($tableName);
		if ($tableName === '') {
			return false;
		}
		if (array_key_exists($tableName, $cache)) {
			return $cache[$tableName];
		}

		try {
			$db = new Database;
			$cache[$tableName] = $db->table_exists([$tableName]);
		} catch (\Throwable $e) {
			$cache[$tableName] = false;
		}
		return $cache[$tableName];
	}
}

if (!function_exists(__NAMESPACE__ . '\\agency_db_error_message')) {
	function agency_db_error_message(Database $db, string $fallback): string
	{
		$message = trim((string)($db->error ?? ''));
		return $message !== '' ? $message : $fallback;
	}
}

if (!function_exists(__NAMESPACE__ . '\\agency_fetch_admin_manager_drawers_for_date')) {
	function agency_fetch_admin_manager_drawers_for_date(string $date): array
	{
		$date = trim($date);
		if ($date === '') {
			return [];
		}

		$db = new Database;
		if (!$db->table_exists(['cashdrawers', 'user_roles_map', 'user_roles'])) {
			return [];
		}

		$roleNames = [
			'admin',
			'manager',
			'general manager',
			'general_manager',
			'generalmanager',
			'gm',
			'operations manager',
			'operations_manager',
			'operationsmanager',
			'op manager',
			'op_manager',
		];

		$params = ['drawer_date' => $date];
		$rolePlaceholders = [];
		foreach ($roleNames as $i => $roleName) {
			$key = 'role' . (string)$i;
			$params[$key] = strtolower((string)$roleName);
			$rolePlaceholders[] = ':' . $key;
		}

		try {
			$rows = $db->query(
				"SELECT DISTINCT c.id AS drawer_id, c.user_id AS drawer_user_id
				 FROM cashdrawers AS c
				 INNER JOIN user_roles_map AS urm ON urm.user_id = c.user_id AND (urm.disabled = 0 OR urm.disabled IS NULL)
				 INNER JOIN user_roles AS r ON r.id = urm.role_id AND (r.disabled = 0 OR r.disabled IS NULL)
				 WHERE DATE(c.date_created) = DATE(:drawer_date)
				   AND LOWER(r.role) IN (" . implode(',', $rolePlaceholders) . ")",
				$params
			) ?: [];
		} catch (\Throwable $e) {
			$rows = [];
		}

		$out = [];
		foreach ($rows as $row) {
			$drawerId = (int)($row->drawer_id ?? 0);
			$drawerUserId = (int)($row->drawer_user_id ?? 0);
			if ($drawerId > 0 && $drawerUserId > 0) {
				$out[] = ['drawer_id' => $drawerId, 'drawer_user_id' => $drawerUserId];
			}
		}
		return $out;
	}
}

if (!function_exists(__NAMESPACE__ . '\\agency_find_transaction_drawer_context')) {
	function agency_find_transaction_drawer_context(object $transaction): ?array
	{
		$transId = (int)($transaction->id ?? 0);
		$creatorId = (int)($transaction->created_by ?? 0);
		$db = new Database;

		if ($transId > 0 && agency_table_exists('drawer_denos')) {
			try {
				$rows = $db->query(
					"SELECT drawer_id, user_id
					 FROM drawer_denos
					 WHERE trans_id = :trans_id
					 ORDER BY id DESC
					 LIMIT 1",
					['trans_id' => $transId]
				) ?: [];
				if (!empty($rows)) {
					$drawerId = (int)($rows[0]->drawer_id ?? 0);
					$drawerUserId = (int)($rows[0]->user_id ?? 0);
					if ($drawerId > 0 && $drawerUserId > 0) {
						return ['drawer_id' => $drawerId, 'drawer_user_id' => $drawerUserId];
					}
				}
			} catch (\Throwable $e) {
			}
		}

		if ($transId > 0 && agency_table_exists('drawer_balances')) {
			try {
				$rows = $db->query(
					"SELECT drawer_id, user_id
					 FROM drawer_balances
					 WHERE trans_id = :trans_id
					   AND channel_type = 'cash'
					   AND channel_key = 'CASH_DRAWER'
					 ORDER BY id DESC
					 LIMIT 1",
					['trans_id' => $transId]
				) ?: [];
				if (!empty($rows)) {
					$drawerId = (int)($rows[0]->drawer_id ?? 0);
					$drawerUserId = (int)($rows[0]->user_id ?? 0);
					if ($drawerId > 0 && $drawerUserId > 0) {
						return ['drawer_id' => $drawerId, 'drawer_user_id' => $drawerUserId];
					}
				}
			} catch (\Throwable $e) {
			}
		}

		if ($creatorId <= 0 || !agency_table_exists('cashdrawers')) {
			return null;
		}

		$transactionDate = trim((string)($transaction->date_created ?? ''));
		if ($transactionDate === '') {
			return null;
		}

		try {
			$rows = $db->query(
				"SELECT id, user_id
				 FROM cashdrawers
				 WHERE user_id = :user_id
				   AND DATE(date_created) = DATE(:transaction_date)
				 ORDER BY id DESC
				 LIMIT 1",
				[
					'user_id' => $creatorId,
					'transaction_date' => $transactionDate,
				]
			) ?: [];
			if (!empty($rows)) {
				$drawerId = (int)($rows[0]->id ?? 0);
				$drawerUserId = (int)($rows[0]->user_id ?? $creatorId);
				if ($drawerId > 0 && $drawerUserId > 0) {
					return ['drawer_id' => $drawerId, 'drawer_user_id' => $drawerUserId];
				}
			}
		} catch (\Throwable $e) {
		}

		return null;
	}
}

if (!function_exists(__NAMESPACE__ . '\\agency_refresh_cashdrawer_balance')) {
	function agency_refresh_cashdrawer_balance(int $drawerId, int $drawerUserId, int $transId, string $transStatus): ?float
	{
		if ($drawerId <= 0 || $drawerUserId <= 0 || !agency_table_exists('drawer_balances') || !agency_table_exists('drawer_denos')) {
			return null;
		}

		$db = new Database;
		$sumRows = $db->query(
			"SELECT
				COALESCE(SUM(volume),0) AS total_volume,
				COALESCE(SUM(CASE WHEN volume > 0 THEN volume ELSE 0 END),0) AS total_credit,
				COALESCE(SUM(CASE WHEN volume < 0 THEN ABS(volume) ELSE 0 END),0) AS total_debit
			 FROM drawer_denos
			 WHERE drawer_id = :drawer_id AND user_id = :user_id",
			['drawer_id' => $drawerId, 'user_id' => $drawerUserId]
		) ?: [];

		$currentClosing = !empty($sumRows) ? (float)($sumRows[0]->total_volume ?? 0) : 0.0;
		$totalCredit = !empty($sumRows) ? (float)($sumRows[0]->total_credit ?? 0) : 0.0;
		$totalDebit = !empty($sumRows) ? (float)($sumRows[0]->total_debit ?? 0) : 0.0;

		$rowsBal = $db->query(
			"SELECT * FROM drawer_balances
			 WHERE drawer_id = :drawer_id
			   AND user_id = :user_id
			   AND channel_type = 'cash'
			   AND channel_key = 'CASH_DRAWER'
			 LIMIT 1",
			['drawer_id' => $drawerId, 'user_id' => $drawerUserId]
		) ?: [];

		if (empty($rowsBal)) {
			$db->query(
				"INSERT INTO drawer_balances
				 (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status)
				 VALUES
				 (:drawer_id, :user_id, 'cash', 'CASH_DRAWER', 0, :credit, :debit, :closing, :date_created, :trans_id, :trans_status)",
				[
					'drawer_id' => $drawerId,
					'user_id' => $drawerUserId,
					'credit' => $totalCredit,
					'debit' => $totalDebit,
					'closing' => $currentClosing,
					'date_created' => date('Y-m-d H:i:s'),
					'trans_id' => $transId,
					'trans_status' => $transStatus,
				]
			);
			return $db->has_error ? null : $currentClosing;
		}

		$rowBal = $rowsBal[0];
		$opening = (float)($rowBal->opening_balance ?? 0);
		$credit = max($totalCredit - $opening, 0.0);
		$db->query(
			"UPDATE drawer_balances
			 SET credit = :credit,
			     debit = :debit,
			     closing_balance = :closing,
			     trans_id = :trans_id,
			     trans_status = :trans_status
			 WHERE id = :id",
			[
				'credit' => $credit,
				'debit' => $totalDebit,
				'closing' => $currentClosing,
				'trans_id' => $transId,
				'trans_status' => $transStatus,
				'id' => $rowBal->id,
			]
		);

		return $db->has_error ? null : $currentClosing;
	}
}

if (!function_exists(__NAMESPACE__ . '\\agency_apply_cashdrawer_delta')) {
	function agency_apply_cashdrawer_delta(int $drawerId, int $drawerUserId, float $delta, int $transId, string $transStatus): array
	{
		if ($drawerId <= 0 || $drawerUserId <= 0 || abs($delta) < 0.00001) {
			return ['ok' => true, 'closing_balance' => null, 'message' => ''];
		}

		$db = new Database;
		if (!agency_table_exists('drawer_balances')) {
			return ['ok' => false, 'closing_balance' => null, 'message' => 'Cashdrawer balance table is not available.'];
		}

		$rows = $db->query(
			"SELECT *
			 FROM drawer_balances
			 WHERE drawer_id = :drawer_id
			   AND user_id = :user_id
			   AND channel_type = 'cash'
			   AND channel_key = 'CASH_DRAWER'
			 LIMIT 1",
			['drawer_id' => $drawerId, 'user_id' => $drawerUserId]
		) ?: [];

		$opening = 0.0;
		$credit = 0.0;
		$debit = 0.0;
		$rowId = 0;
		if (!empty($rows)) {
			$row = $rows[0];
			$rowId = (int)($row->id ?? 0);
			$opening = (float)($row->opening_balance ?? 0);
			$credit = (float)($row->credit ?? 0);
			$debit = (float)($row->debit ?? 0);
		} else {
			$hdr = $db->query("SELECT amount FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawerId]) ?: [];
			$opening = !empty($hdr) ? (float)($hdr[0]->amount ?? 0) : 0.0;
		}

		$currentClosing = $opening + $credit - $debit;
		if (($currentClosing + $delta) < -0.00001) {
			return ['ok' => false, 'closing_balance' => null, 'message' => 'Current cashdrawer balance is not enough to reverse this approved agency banking transaction.'];
		}

		if ($delta >= 0) {
			$credit += $delta;
		} else {
			$debit += abs($delta);
		}
		$closing = $opening + $credit - $debit;

		if ($rowId <= 0) {
			$db->query(
				"INSERT INTO drawer_balances
				 (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status)
				 VALUES
				 (:drawer_id, :user_id, 'cash', 'CASH_DRAWER', :opening, :credit, :debit, :closing, :date_created, :trans_id, :trans_status)",
				[
					'drawer_id' => $drawerId,
					'user_id' => $drawerUserId,
					'opening' => $opening,
					'credit' => $credit,
					'debit' => $debit,
					'closing' => $closing,
					'date_created' => date('Y-m-d H:i:s'),
					'trans_id' => $transId,
					'trans_status' => $transStatus,
				]
			);
		} else {
			$db->query(
				"UPDATE drawer_balances
				 SET credit = :credit,
				     debit = :debit,
				     closing_balance = :closing,
				     trans_id = :trans_id,
				     trans_status = :trans_status
				 WHERE id = :id",
				[
					'credit' => $credit,
					'debit' => $debit,
					'closing' => $closing,
					'trans_id' => $transId,
					'trans_status' => $transStatus,
					'id' => $rowId,
				]
			);
		}

		if ($db->has_error) {
			return ['ok' => false, 'closing_balance' => null, 'message' => agency_db_error_message($db, 'Cashdrawer reversal could not be saved.')];
		}

		return ['ok' => true, 'closing_balance' => $closing, 'message' => ''];
	}
}

if (!function_exists(__NAMESPACE__ . '\\agency_reverse_drawer_denos')) {
	function agency_reverse_drawer_denos(int $drawerId, int $drawerUserId, int $transId, string $transStatus, int $actorUserId = 0): array
	{
		$result = ['ok' => true, 'had_rows' => false, 'closing_balance' => null, 'message' => ''];
		if ($drawerId <= 0 || $drawerUserId <= 0 || $transId <= 0 || !agency_table_exists('drawer_denos')) {
			return $result;
		}

		$db = new Database;
		$rows = $db->query(
			"SELECT denomination, `count`, volume
			 FROM drawer_denos
			 WHERE drawer_id = :drawer_id
			   AND user_id = :user_id
			   AND trans_id = :trans_id
			 ORDER BY id ASC",
			['drawer_id' => $drawerId, 'user_id' => $drawerUserId, 'trans_id' => $transId]
		) ?: [];

		if (empty($rows)) {
			return $result;
		}

		$result['had_rows'] = true;
		$requiredDenos = [];
		foreach ($rows as $row) {
			$count = (int)($row->count ?? 0);
			if ($count <= 0) {
				continue;
			}
			$denomination = (float)($row->denomination ?? 0);
			if ($denomination <= 0) {
				continue;
			}
			$denoKey = number_format($denomination, 2, '.', '');
			$requiredDenos[$denoKey] = ($requiredDenos[$denoKey] ?? 0) + $count;
		}

		if (!empty($requiredDenos)) {
			$availableRows = $db->query(
				"SELECT denomination, SUM(`count`) AS total_count
				 FROM drawer_denos
				 WHERE drawer_id = :drawer_id
				   AND user_id = :user_id
				 GROUP BY denomination",
				['drawer_id' => $drawerId, 'user_id' => $drawerUserId]
			) ?: [];

			$availableDenos = [];
			foreach ($availableRows as $availableRow) {
				$denoKey = number_format((float)($availableRow->denomination ?? 0), 2, '.', '');
				$availableDenos[$denoKey] = (int)($availableRow->total_count ?? 0);
			}

			$shortages = [];
			foreach ($requiredDenos as $denoKey => $requiredCount) {
				$availableCount = (int)($availableDenos[$denoKey] ?? 0);
				if ($availableCount < $requiredCount) {
					$shortages[] = 'GHS ' . number_format((float)$denoKey, 2) . ' notes: required ' . $requiredCount . ', available ' . $availableCount;
				}
			}

			if (!empty($shortages)) {
				return ['ok' => false, 'had_rows' => true, 'closing_balance' => null, 'message' => 'Cashdrawer denomination balance is not enough to decline or reject this agency banking transaction. ' . implode(' | ', $shortages)];
			}
		}

		$createdBy = $actorUserId > 0 ? $actorUserId : $drawerUserId;
		$now = date('Y-m-d H:i:s');
		foreach ($rows as $row) {
			$count = (int)($row->count ?? 0);
			$volume = (float)($row->volume ?? 0);
			if ($count === 0 && abs($volume) < 0.00001) {
				continue;
			}

			$db->query(
				"INSERT INTO drawer_denos
				 (drawer_id, user_id, denomination, `count`, volume, date_created, created_by, trans_id, trans_status)
				 VALUES
				 (:drawer_id, :user_id, :denomination, :count, :volume, :date_created, :created_by, :trans_id, :trans_status)",
				[
					'drawer_id' => $drawerId,
					'user_id' => $drawerUserId,
					'denomination' => $row->denomination ?? 0,
					'count' => -1 * $count,
					'volume' => -1 * $volume,
					'date_created' => $now,
					'created_by' => $createdBy,
					'trans_id' => $transId,
					'trans_status' => $transStatus,
				]
			);

			if ($db->has_error) {
				return ['ok' => false, 'had_rows' => true, 'closing_balance' => null, 'message' => agency_db_error_message($db, 'Cashdrawer denomination reversal could not be saved.')];
			}
		}

		$result['closing_balance'] = agency_refresh_cashdrawer_balance($drawerId, $drawerUserId, $transId, $transStatus);
		return $result;
	}
}

if (!function_exists(__NAMESPACE__ . '\\agency_apply_resolved_channel_delta')) {
	function agency_apply_resolved_channel_delta(int $drawerId, int $drawerUserId, string $channelKey, float $delta, int $transId, string $transStatus): array
	{
		if ($drawerId <= 0 || $drawerUserId <= 0 || abs($delta) < 0.00001) {
			return ['ok' => true, 'closing_balance' => null, 'message' => ''];
		}

		$channelKey = strtoupper(trim($channelKey));
		if ($channelKey === '') {
			return ['ok' => false, 'closing_balance' => null, 'message' => 'The original e-cash/bank channel could not be resolved.'];
		}

		$db = new Database;
		if (!agency_table_exists('drawer_balances')) {
			return ['ok' => false, 'closing_balance' => null, 'message' => 'Drawer balances table is not available for the e-cash reversal.'];
		}

		$rows = $db->query(
			"SELECT *
			 FROM drawer_balances
			 WHERE drawer_id = :drawer_id
			   AND user_id = :user_id
			   AND channel_type = 'ecash'
			   AND channel_key = :channel_key
			 ORDER BY id DESC
			 LIMIT 1",
			['drawer_id' => $drawerId, 'user_id' => $drawerUserId, 'channel_key' => $channelKey]
		) ?: [];

		$opening = 0.0;
		$credit = 0.0;
		$debit = 0.0;
		$rowId = 0;
		if (!empty($rows)) {
			$row = $rows[0];
			$rowId = (int)($row->id ?? 0);
			$opening = (float)($row->opening_balance ?? 0);
			$credit = (float)($row->credit ?? 0);
			$debit = (float)($row->debit ?? 0);
		} elseif ($delta < 0) {
			return ['ok' => false, 'closing_balance' => null, 'message' => 'There is no balance for channel ' . $channelKey . ' on the original drawer to reverse this approved transaction.'];
		}

		$currentClosing = $opening + $credit - $debit;
		if (($currentClosing + $delta) < -0.00001) {
			return ['ok' => false, 'closing_balance' => null, 'message' => 'Current balance in channel ' . $channelKey . ' is not enough to reverse this approved transaction.'];
		}

		if ($delta >= 0) {
			$credit += $delta;
		} else {
			$debit += abs($delta);
		}
		$closing = $opening + $credit - $debit;

		if ($rowId <= 0) {
			$db->query(
				"INSERT INTO drawer_balances
				 (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status)
				 VALUES
				 (:drawer_id, :user_id, 'ecash', :channel_key, :opening, :credit, :debit, :closing, :date_created, :trans_id, :trans_status)",
				[
					'drawer_id' => $drawerId,
					'user_id' => $drawerUserId,
					'channel_key' => $channelKey,
					'opening' => $opening,
					'credit' => $credit,
					'debit' => $debit,
					'closing' => $closing,
					'date_created' => date('Y-m-d H:i:s'),
					'trans_id' => $transId,
					'trans_status' => $transStatus,
				]
			);
		} else {
			$db->query(
				"UPDATE drawer_balances
				 SET credit = :credit,
				     debit = :debit,
				     closing_balance = :closing,
				     trans_id = :trans_id,
				     trans_status = :trans_status
				 WHERE id = :id",
				[
					'credit' => $credit,
					'debit' => $debit,
					'closing' => $closing,
					'trans_id' => $transId,
					'trans_status' => $transStatus,
					'id' => $rowId,
				]
			);
		}

		if ($db->has_error) {
			return ['ok' => false, 'closing_balance' => null, 'message' => agency_db_error_message($db, 'E-cash/bank reversal could not be saved.')];
		}

		return ['ok' => true, 'closing_balance' => $closing, 'message' => ''];
	}
}

if (!function_exists(__NAMESPACE__ . '\\agency_find_transaction_channel_contexts')) {
	function agency_find_transaction_channel_contexts(object $transaction, int $cashDrawerId, int $cashDrawerUserId): array
	{
		$transId = (int)($transaction->id ?? 0);
		$db = new Database;
		$contexts = [];
		$mark = function (int $drawerId, int $drawerUserId, string $channelKey) use (&$contexts): void {
			$channelKey = strtoupper(trim($channelKey));
			if ($drawerId <= 0 || $drawerUserId <= 0 || $channelKey === '') {
				return;
			}
			$contexts[$drawerId . ':' . $drawerUserId . ':' . $channelKey] = [
				'drawer_id' => $drawerId,
				'drawer_user_id' => $drawerUserId,
				'channel_key' => $channelKey,
			];
		};

		if ($transId > 0 && agency_table_exists('drawer_balances')) {
			try {
				$rows = $db->query(
					"SELECT drawer_id, user_id, channel_key
					 FROM drawer_balances
					 WHERE trans_id = :trans_id
					   AND channel_type = 'ecash'
					 ORDER BY id ASC",
					['trans_id' => $transId]
				) ?: [];
				foreach ($rows as $row) {
					$mark((int)($row->drawer_id ?? 0), (int)($row->user_id ?? 0), (string)($row->channel_key ?? ''));
				}
			} catch (\Throwable $e) {
			}
		}

		$transactionArray = get_object_vars($transaction);
		$ecashMeta = agency_determine_ecash_channel($transactionArray);
		if (empty($ecashMeta)) {
			return array_values($contexts);
		}

		$baseChannelKey = (string)($ecashMeta['channel_key'] ?? '');
		$rawNameForResolve = (string)($ecashMeta['channel_name_raw'] ?? '');
		$transactionDate = trim((string)($transaction->date_created ?? ''));
		$targets = [];
		if ($cashDrawerId > 0 && $cashDrawerUserId > 0) {
			$targets[] = ['drawer_id' => $cashDrawerId, 'drawer_user_id' => $cashDrawerUserId];
		}
		foreach (agency_fetch_admin_manager_drawers_for_date($transactionDate) as $ctx) {
			$targets[] = $ctx;
		}

		foreach ($targets as $ctx) {
			$drawerId = (int)($ctx['drawer_id'] ?? 0);
			$drawerUserId = (int)($ctx['drawer_user_id'] ?? 0);
			if ($drawerId <= 0 || $drawerUserId <= 0) {
				continue;
			}
			$writeKey = $baseChannelKey;
			$existingKey = $writeKey !== '' ? agency_find_existing_ecash_channel_key($drawerId, $drawerUserId, $writeKey) : null;
			if (!empty($existingKey)) {
				$writeKey = (string)$existingKey;
			} else {
				$resolved = agency_resolve_ecash_channel_key($drawerId, $drawerUserId, $rawNameForResolve);
				if (!empty($resolved)) {
					$writeKey = (string)$resolved;
				}
			}
			$mark($drawerId, $drawerUserId, $writeKey);
		}

		return array_values($contexts);
	}
}

if (!function_exists(__NAMESPACE__ . '\\agency_delete_instant_commission')) {
	function agency_delete_instant_commission(int $transId): bool
	{
		if ($transId <= 0 || !agency_table_exists('commissions')) {
			return true;
		}

		$db = new Database;
		$db->query(
			"DELETE FROM commissions
			 WHERE colletion_period = :period
			   AND commission_type = :commission_type
			   AND description LIKE :needle",
			[
				'period' => 'INSTANT',
				'commission_type' => 'AGENCYBANKING CHARGE',
				'needle' => '%| ID ' . $transId,
			]
		);

		return !$db->has_error;
	}
}

if (!function_exists(__NAMESPACE__ . '\\agency_reverse_approved_effects')) {
	function agency_reverse_approved_effects(object $transaction, string $transStatus): array
	{
		$result = ['ok' => true, 'cashdrawer_closing_balance' => null, 'ecash_closing_balance' => null, 'message' => ''];
		$transId = (int)($transaction->id ?? 0);
		if ($transId <= 0) {
			return $result;
		}

		$cashContext = agency_find_transaction_drawer_context($transaction);
		if (empty($cashContext)) {
			$dateLabel = 'the transaction date';
			$dateRaw = trim((string)($transaction->date_created ?? ''));
			if ($dateRaw !== '') {
				$timestamp = strtotime($dateRaw);
				if ($timestamp !== false) {
					$dateLabel = date('F j, Y', $timestamp);
				}
			}
			return [
				'ok' => false,
				'cashdrawer_closing_balance' => null,
				'ecash_closing_balance' => null,
				'message' => 'No historical cashdrawer could be resolved for ' . $dateLabel . ' for the user who created this transaction. To keep the records consistent, decline or reject it against that original day\'s cashdrawer.',
			];
		}

		$drawerId = (int)($cashContext['drawer_id'] ?? 0);
		$drawerUserId = (int)($cashContext['drawer_user_id'] ?? 0);
		$actorUserId = (int)($transaction->updated_by ?? 0);
		$transactionArray = get_object_vars($transaction);
		$cashAmount = agency_resolve_transaction_amount($transactionArray, []);
		$txType = strtolower((string)($transaction->transaction_type ?? ''));
		$cashSign = strpos($txType, 'withdrawal') !== false ? -1 : 1;
		if (agency_is_ezwich_transfer($transactionArray)) {
			$cashSign = -1;
		}
		$cashDelta = -1 * $cashSign * (float)$cashAmount;

		if (abs($cashDelta) > 0.00001) {
			$cashResult = agency_reverse_drawer_denos($drawerId, $drawerUserId, $transId, $transStatus, $actorUserId);
			if (empty($cashResult['ok'])) {
				return ['ok' => false, 'cashdrawer_closing_balance' => null, 'ecash_closing_balance' => null, 'message' => $cashResult['message'] ?: 'Cashdrawer reversal failed.'];
			}

			if (!empty($cashResult['had_rows'])) {
				$result['cashdrawer_closing_balance'] = $cashResult['closing_balance'] ?? null;
			} else {
				$cashFallback = agency_apply_cashdrawer_delta($drawerId, $drawerUserId, $cashDelta, $transId, $transStatus);
				if (empty($cashFallback['ok'])) {
					return ['ok' => false, 'cashdrawer_closing_balance' => null, 'ecash_closing_balance' => null, 'message' => $cashFallback['message'] ?: 'Cashdrawer reversal failed.'];
				}
				$result['cashdrawer_closing_balance'] = $cashFallback['closing_balance'] ?? null;
			}
		}

		$ecashMeta = agency_determine_ecash_channel($transactionArray);
		$ecashAmount = agency_resolve_ecash_amount($transactionArray, (float)$cashAmount);
		if (!empty($ecashMeta) && $ecashAmount > 0) {
			$direction = strtolower((string)($ecashMeta['direction'] ?? ''));
			$channelDelta = $direction === 'in' ? (-1 * (float)$ecashAmount) : (float)$ecashAmount;
			$channelContexts = agency_find_transaction_channel_contexts($transaction, $drawerId, $drawerUserId);
			if (empty($channelContexts)) {
				return ['ok' => false, 'cashdrawer_closing_balance' => $result['cashdrawer_closing_balance'], 'ecash_closing_balance' => null, 'message' => 'The original e-cash/bank channel for this agency banking transaction could not be resolved.'];
			}

			foreach ($channelContexts as $ctx) {
				$channelResult = agency_apply_resolved_channel_delta(
					(int)($ctx['drawer_id'] ?? 0),
					(int)($ctx['drawer_user_id'] ?? 0),
					(string)($ctx['channel_key'] ?? ''),
					$channelDelta,
					$transId,
					$transStatus
				);
				if (empty($channelResult['ok'])) {
					return ['ok' => false, 'cashdrawer_closing_balance' => $result['cashdrawer_closing_balance'], 'ecash_closing_balance' => null, 'message' => $channelResult['message'] ?: 'E-cash/bank reversal failed.'];
				}
				$result['ecash_closing_balance'] = $channelResult['closing_balance'] ?? $result['ecash_closing_balance'];
			}
		}

		$txTypeForCommission = strtolower((string)($transaction->transaction_type ?? ''));
		$isInterbankOrGipCharge = strpos($txTypeForCommission, 'interbank') !== false || strpos($txTypeForCommission, 'gip') !== false;
		if ($isInterbankOrGipCharge) {
			// Interbank/GIP charges are recorded on the transaction only; cleanup legacy commission rows if present.
			agency_delete_instant_commission($transId);
		} elseif (!agency_delete_instant_commission($transId)) {
			return ['ok' => false, 'cashdrawer_closing_balance' => $result['cashdrawer_closing_balance'], 'ecash_closing_balance' => $result['ecash_closing_balance'], 'message' => 'Commission reversal failed for this transaction.'];
		}

		return $result;
	}
}

    if (!function_exists(__NAMESPACE__ . '\\agency_sync_cashdrawer')) {
        function agency_sync_cashdrawer(array $transaction, array $currencyArray, int $drawerOwnerId, int $sessionUserId): void
        {
            $debug = false;
        	try {
			$debug = !empty($_GET['debug_drawer_sync']) || !empty($_POST['debug_drawer_sync']) || !empty($transaction['debug_drawer_sync']);
		} catch (\Throwable $e) {
			$debug = false;
		}

        $statusRaw = trim((string)($transaction['transaction_status'] ?? ''));
        $status = strtolower($statusRaw);
        if ($status === '') {
            $status = 'pending';
            $statusRaw = 'Pending';
        }

        $allowedStatuses = ['approved', 'pending', 'draft', 'sent'];
        if (!in_array($status, $allowedStatuses, true)) {
            $transIdFallback = (int)($transaction['id'] ?? 0);
            if ($debug) {
                error_log('AGENCY CASHDRAWER SYNC SKIP: status=' . $statusRaw . ' trans_id=' . $transIdFallback);
            }
            agency_remove_cashdrawer_entries($transIdFallback);
            agency_remove_drawer_balance_entries($transIdFallback);
            return;
        }

        $transStatusForWrite = $statusRaw !== '' ? $statusRaw : ucfirst($status);

        $transId = (int)($transaction['id'] ?? 0);
        if ($transId <= 0) {
            error_log('AGENCY CASHDRAWER SYNC SKIP: missing trans_id');
            return;
        }

        $ownerId = $drawerOwnerId > 0 ? $drawerOwnerId : $sessionUserId;
        $context = agency_find_active_drawer($ownerId);
        if (empty($context)) {
            error_log('AGENCY CASHDRAWER SYNC SKIP: no drawer for owner_id=' . $ownerId . ' trans_id=' . $transId);
            return;
        }

        $drawerId = (int)$context['drawer_id'];
        $drawerUserId = (int)$context['drawer_user_id'];
        if ($drawerId <= 0 || $drawerUserId <= 0) {
            error_log('AGENCY CASHDRAWER SYNC SKIP: invalid drawer context drawer_id=' . $drawerId . ' drawer_user_id=' . $drawerUserId . ' trans_id=' . $transId);
            return;
        }

        $hasDrawerDenos = false;
        try {
            $dbSchema = new Database;
            if (!$dbSchema->table_exists(['drawer_balances'])) {
                error_log('AGENCY CASHDRAWER SYNC SKIP: missing required tables trans_id=' . $transId . ' missing=' . json_encode($dbSchema->missing_tables ?? []));
                return;
            }
            $hasDrawerDenos = $dbSchema->table_exists(['drawer_denos']);
        } catch (\Throwable $e) {
            error_log('AGENCY CASHDRAWER SYNC SKIP: schema check failed trans_id=' . $transId . ' error=' . $e->getMessage());
            return;
        }

        $cashAmount = agency_resolve_transaction_amount($transaction, $currencyArray);
        if ($cashAmount <= 0) {
            error_log('AGENCY CASHDRAWER SYNC SKIP: zero amount trans_id=' . $transId . ' tx_type=' . ($transaction['transaction_type'] ?? '') . ' status=' . $transStatusForWrite);
            return;
        }

        $ecashAmount = agency_resolve_ecash_amount($transaction, $cashAmount);
        if ($debug) {
            error_log('AGENCY CASHDRAWER SYNC START: trans_id=' . $transId . ' status=' . $transStatusForWrite . ' owner_id=' . $ownerId . ' drawer_id=' . $drawerId . ' drawer_user_id=' . $drawerUserId . ' tx_type=' . ($transaction['transaction_type'] ?? '') . ' cash_amount=' . $cashAmount . ' ecash_amount=' . $ecashAmount);
        }
        $normalizedDenos = $hasDrawerDenos ? agency_normalize_denominations($currencyArray, $cashAmount) : [];
        $changeDenoms = [];
        $changeDenomsRaw = trim((string)($transaction['change_denominations'] ?? ''));
        if ($changeDenomsRaw !== '') {
            $decodedChangeRows = json_decode($changeDenomsRaw, true);
            if (is_array($decodedChangeRows)) {
                foreach ($decodedChangeRows as $changeRow) {
                    if (!is_array($changeRow)) {
                        continue;
                    }
                    $denom = (float)($changeRow['denomination'] ?? 0);
                    $count = (int)($changeRow['count'] ?? 0);
                    $volume = (float)($changeRow['volume'] ?? ($denom * $count));
                    if ($denom > 0 && $count > 0) {
                        $changeDenoms[] = [
                            'denomination' => $denom,
                            'count' => $count,
                            'volume' => $volume,
                        ];
                    }
                }
            }
        }
        $timestamp = $transaction['date_updated'] ?? $transaction['date_created'] ?? date('Y-m-d H:i:s');
        $txType = strtolower($transaction['transaction_type'] ?? '');
        $sign = strpos($txType, 'withdrawal') !== false ? -1 : 1;
		// Ezwich transfers pay cash out from the drawer.
		if (agency_is_ezwich_transfer($transaction)) {
			$sign = -1;
		}
        $ecashMeta = agency_determine_ecash_channel($transaction);
        if ($ecashMeta) {
            $rawNetworkForChannel = trim((string)($ecashMeta['channel_name_raw'] ?? ''));

            $channelKey = (string)($ecashMeta['channel_key'] ?? '');
            $existingKey = $channelKey !== '' ? agency_find_existing_ecash_channel_key($drawerId, $drawerUserId, $channelKey) : null;
            if (!empty($existingKey)) {
                $ecashMeta['channel_key'] = $existingKey;
            } else {
                $resolved = agency_resolve_ecash_channel_key($drawerId, $drawerUserId, $rawNetworkForChannel);
                if (!empty($resolved)) {
                    $ecashMeta['channel_key'] = $resolved;
                }
            }
            if ($debug) {
                error_log('AGENCY CASHDRAWER SYNC ECASH: trans_id=' . $transId . ' channel=' . ($ecashMeta['channel_key'] ?? '') . ' direction=' . ($ecashMeta['direction'] ?? '') . ' raw=' . ($ecashMeta['channel_name_raw'] ?? ''));
            }
        } elseif ($debug) {
            error_log('AGENCY CASHDRAWER SYNC ECASH SKIP: no ecashMeta trans_id=' . $transId);
        }

        agency_remove_cashdrawer_entries($transId);
        agency_remove_drawer_balance_entries($transId);

        $isApproved = ($status === 'approved' || $status === 'sent');

        if (!$isApproved) {
            return;
        }


        if (!empty($normalizedDenos)) {
            $db = new Database;
            if ($db->table_exists(['drawer_denos'])) {
                foreach ($normalizedDenos as $deno) {
                    $db->query(
                        "INSERT INTO drawer_denos (drawer_id, user_id, denomination, count, volume, date_created, created_by, trans_id, trans_status) VALUES (:drawer_id, :user_id, :denomination, :count, :volume, :date_created, :created_by, :trans_id, :trans_status)",
                        [
                            'drawer_id' => $drawerId,
                            'user_id' => $drawerUserId,
                            'denomination' => $deno['denomination'],
                            'count' => $sign * (int)($deno['count'] ?? 0),
                            'volume' => $sign * (float)($deno['volume'] ?? 0),
                            'date_created' => $timestamp,
                            'created_by' => $drawerUserId,
                            'trans_id' => $transId,
                            'trans_status' => $transStatusForWrite,
                        ]
                    );
                }
            }
        }

        if (!empty($changeDenoms) && $db->table_exists(['drawer_denos'])) {
            foreach ($changeDenoms as $changeDeno) {
                $db->query(
                    "INSERT INTO drawer_denos (drawer_id, user_id, denomination, count, volume, date_created, created_by, trans_id, trans_status) VALUES (:drawer_id, :user_id, :denomination, :count, :volume, :date_created, :created_by, :trans_id, :trans_status)",
                    [
                        'drawer_id' => $drawerId,
                        'user_id' => $drawerUserId,
                        'denomination' => (float)($changeDeno['denomination'] ?? 0),
                        'count' => -(int)($changeDeno['count'] ?? 0),
                        'volume' => -(float)($changeDeno['volume'] ?? 0),
                        'date_created' => $timestamp,
                        'created_by' => $drawerUserId,
                        'trans_id' => $transId,
                        'trans_status' => 'Change',
                    ]
                );
            }
        }

        if ($hasDrawerDenos) {
            agency_refresh_cash_channel($drawerId, $drawerUserId, $transId, $transStatusForWrite);
        }

        if ($ecashMeta) {
            $amountToApply = max(0, (float)$ecashAmount);
            $directionToApply = (string)($ecashMeta['direction'] ?? '');
            $rawNameForResolve = (string)($ecashMeta['channel_name_raw'] ?? '');
            $baseChannelKey = (string)($ecashMeta['channel_key'] ?? '');
			$sharedOpening = agency_get_shared_ecash_balance($baseChannelKey, $rawNameForResolve);
            $appliedTargets = [];
            $markApplied = function(int $dId, int $uId) use (&$appliedTargets): void {
				$appliedTargets[$dId . ':' . $uId] = true;
			};
			$hasApplied = function(int $dId, int $uId) use (&$appliedTargets): bool {
				return !empty($appliedTargets[$dId . ':' . $uId]);
			};

			// Always apply to the current drawer too (ensures the channel exists/updates even when
			// there are no admin/manager drawers or when viewing the user's own cashdrawer).
			if ($amountToApply > 0 && $baseChannelKey !== '') {
				$seedOpening = null;
				if (!agency_drawer_has_ecash_channel($drawerId, $drawerUserId, $baseChannelKey) && $sharedOpening > 0) {
					$seedOpening = $sharedOpening;
				}
				agency_refresh_ecash_channel(
					$drawerId,
					$drawerUserId,
					$baseChannelKey,
					$directionToApply,
					$amountToApply,
					$transId,
					$transStatusForWrite,
					$seedOpening
				);
				$markApplied($drawerId, $drawerUserId);
			}

            $adminManagerDrawers = agency_fetch_admin_manager_drawers_today();
            if (empty($adminManagerDrawers)) {
                if ($debug) {
                    error_log('AGENCY CASHDRAWER SYNC ECASH NOTE: no admin/manager drawers today; applied to current drawer only trans_id=' . $transId);
                }
                return;
            }

            $uniqueTargets = [];
            foreach ($adminManagerDrawers as $ctx) {
                $targetDrawerId = (int)($ctx['drawer_id'] ?? 0);
                $targetDrawerUserId = (int)($ctx['drawer_user_id'] ?? 0);
                if ($targetDrawerId <= 0 || $targetDrawerUserId <= 0) {
                    continue;
                }
                $uniqueTargets[$targetDrawerId . ':' . $targetDrawerUserId] = true;
            }

            foreach ($uniqueTargets as $targetKey => $dummy) {
                list($targetDrawerId, $targetDrawerUserId) = explode(':', $targetKey);
                $targetDrawerId = (int)$targetDrawerId;
                $targetDrawerUserId = (int)$targetDrawerUserId;
                if ($hasApplied($targetDrawerId, $targetDrawerUserId)) {
                    continue;
                }

                $writeKey = $baseChannelKey;
                $existingKey = $writeKey !== '' ? agency_find_existing_ecash_channel_key($targetDrawerId, $targetDrawerUserId, $writeKey) : null;
                if (!empty($existingKey)) {
                    $writeKey = (string)$existingKey;
                } else {
                    $resolved = agency_resolve_ecash_channel_key($targetDrawerId, $targetDrawerUserId, $rawNameForResolve);
                    if (!empty($resolved)) {
                        $writeKey = (string)$resolved;
                    }
                }

				$seedOpening = null;
				if (!agency_drawer_has_ecash_channel($targetDrawerId, $targetDrawerUserId, $writeKey) && $sharedOpening > 0) {
					$seedOpening = $sharedOpening;
				}
                agency_refresh_ecash_channel(
                    $targetDrawerId,
                    $targetDrawerUserId,
                    $writeKey,
                    $directionToApply,
                    $amountToApply,
                    $transId,
                    $transStatusForWrite,
					$seedOpening
                );
            }
        }
    }
}

<?php

namespace Cfas;

use \Core\Database;

if (!function_exists('cfa_normalize_transaction_type')) {
    function cfa_normalize_transaction_type(string $transactionType): string
    {
        $t = strtolower(trim($transactionType));
        // normalize odd whitespace
        $t = str_replace(["\xC2\xA0"], ' ', $t);
        $t = preg_replace('/\s+/', ' ', $t);

        // normalize GHS symbols/variants
        $t = str_replace(['gh₵', 'gh¢', 'ghc', 'ghs'], 'ghs', $t);

        // normalize separators
        $t = str_replace(['→', '-', '_'], ' to ', $t);
        $t = preg_replace('/\s+to\s+/', ' to ', $t);

        if (preg_match('/\bcfa\s*to\s*ghs\b/', $t)) {
            return 'cfa to ghs';
        }
        if (preg_match('/\bghs\s*to\s*cfa\b/', $t)) {
            return 'ghs to cfa';
        }

        return $t;
    }
}

if (!function_exists('cfa_sync_cashdrawer_channels')) {
    function cfa_sync_cashdrawer_channels(array $postdata, array $currencyArray, int $sessionUserId): void
    {
        $drawerContext = cfa_find_active_drawer($sessionUserId);
        if (empty($drawerContext)) {
            return;
        }

        $drawerId = $drawerContext['drawer_id'];
        $drawerUserId = $drawerContext['drawer_user_id'];
        $transId = (int)($postdata['id'] ?? 0);
        $transStatus = $postdata['transaction_status'] ?? 'Pending';
        $transactionType = cfa_normalize_transaction_type($postdata['transaction_type'] ?? '');

        $amountCfa = (float)($postdata['amount_cfa'] ?? 0);
        $amountGhs = (float)($postdata['amount_ghs'] ?? 0);

        $flow = cfa_determine_flow($transactionType, $amountCfa, $amountGhs);
        if (empty($flow)) {
            return;
        }

        $groupedDenos = cfa_group_denominations($currencyArray);
        $timestamp = $postdata['date_created'] ?? date('Y-m-d H:i:s');

        if (strtolower(trim($transStatus)) !== 'rejected') {
            cfa_assert_flow_denominations_available($drawerId, $drawerUserId, $groupedDenos, $flow);
        }

        // Handle GHS movements via legacy drawer_denos
        if (!empty($flow['GHS'])) {
            if (strtolower(trim($transStatus)) !== 'rejected') {
                cfa_process_ghs_denominations($drawerId, $drawerUserId, $groupedDenos['GHS'] ?? [], $flow['GHS']['sign'], $transStatus, $transId, $timestamp, $flow['GHS']['fallback']);
            }
            cfa_refresh_cash_channel($drawerId, $drawerUserId, 'CASH_DRAWER', $transId, $transStatus, false);
        }

        // Handle CFA movements via dedicated fx mirror
        if (!empty($flow['CFA'])) {
            if (strtolower(trim($transStatus)) !== 'rejected') {
                cfa_process_fx_denominations($drawerId, $drawerUserId, $groupedDenos['CFA'] ?? [], $flow['CFA']['sign'], 'CFA', $transStatus, $transId, $timestamp, $flow['CFA']['fallback']);
            }
            cfa_refresh_cash_channel($drawerId, $drawerUserId, 'CASH_CFA', $transId, $transStatus, true, 'CFA');
        }
    }
}

if (!function_exists('Cfas\\cfa_assert_flow_denominations_available')) {
    function cfa_assert_flow_denominations_available(int $drawerId, int $drawerUserId, array $groupedDenos, array $flow): void
    {
        foreach (['GHS', 'CFA'] as $currency) {
            if (empty($flow[$currency])) {
                continue;
            }

            $sign = (int)($flow[$currency]['sign'] ?? 0);
            if ($sign === 0) {
                continue;
            }

            $available = ($currency === 'GHS')
                ? cfa_fetch_current_ghs_counts($drawerId, $drawerUserId)
                : cfa_fetch_current_fx_counts($drawerId, $drawerUserId, $currency);

            $projected = $available;
            $rows = $groupedDenos[$currency] ?? [];
            $fallbackAmount = (float)($flow[$currency]['fallback'] ?? 0);
            if (empty($rows) && $fallbackAmount > 0) {
                $rows = [[
                    'denomination' => $fallbackAmount,
                    'count' => 1,
                    'volume' => $fallbackAmount,
                ]];
            }

            foreach ($rows as $row) {
                $denomination = (float)($row['denomination'] ?? 0);
                $count = (int)($row['count'] ?? 0);
                if ($denomination <= 0 || $count === 0) {
                    continue;
                }

                $key = cfa_count_denom_key($denomination);
                $projected[$key] = (int)($projected[$key] ?? 0) + ($sign * $count);
            }

            $insufficient = [];
            foreach ($projected as $key => $countAfter) {
                if ($countAfter < 0) {
                    $have = (int)($available[$key] ?? 0);
                    $need = $have + abs($countAfter);
                    $label = $currency === 'GHS' ? 'GHS' : $currency;
                    $insufficient[] = $label . ' ' . number_format((float)$key, 2) . ' (available ' . max(0, $have) . ', required ' . $need . ')';
                }
            }

            if (!empty($insufficient)) {
                throw new \RuntimeException('Insufficient ' . $currency . ' denominations in cashdrawer: ' . implode(' | ', $insufficient));
            }
        }
    }
}

if (!function_exists('Cfas\\cfa_count_denom_key')) {
    function cfa_count_denom_key($denomination): string
    {
        return rtrim(rtrim(sprintf('%.6F', (float)$denomination), '0'), '.');
    }
}

if (!function_exists('Cfas\\cfa_fetch_current_ghs_counts')) {
    function cfa_fetch_current_ghs_counts(int $drawerId, int $drawerUserId): array
    {
        $db = new Database;
        if (!$db->table_exists(['drawer_denos'])) {
            return [];
        }

        $rows = $db->query(
            "SELECT denomination, SUM(count) AS total_count
             FROM drawer_denos
             WHERE drawer_id = :drawer_id AND user_id = :user_id
             GROUP BY denomination",
            ['drawer_id' => $drawerId, 'user_id' => $drawerUserId]
        ) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $key = cfa_count_denom_key($row->denomination ?? 0);
            if ($key !== '') {
                $out[$key] = (int)($row->total_count ?? 0);
            }
        }
        return $out;
    }
}

if (!function_exists('Cfas\\cfa_fetch_current_fx_counts')) {
    function cfa_fetch_current_fx_counts(int $drawerId, int $drawerUserId, string $currency): array
    {
        cfa_ensure_fx_table();
        $db = new Database;
        if (!$db->table_exists(['drawer_fx_denos'])) {
            return [];
        }

        $rows = $db->query(
            "SELECT denomination, SUM(count) AS total_count
             FROM drawer_fx_denos
             WHERE drawer_id = :drawer_id AND user_id = :user_id AND currency = :currency
             GROUP BY denomination",
            ['drawer_id' => $drawerId, 'user_id' => $drawerUserId, 'currency' => strtoupper($currency)]
        ) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $key = cfa_count_denom_key($row->denomination ?? 0);
            if ($key !== '') {
                $out[$key] = (int)($row->total_count ?? 0);
            }
        }
        return $out;
    }
}

if (!function_exists('cfa_get_cashdrawer_snapshot')) {
    function cfa_get_cashdrawer_snapshot(int $userId): array
    {
        $context = cfa_find_active_drawer($userId);
        if (empty($context)) {
            return [];
        }

        $drawerId = $context['drawer_id'];
        $drawerUserId = $context['drawer_user_id'];
        $db = new Database;

        $ghsTotals = $db->query(
            "SELECT COALESCE(SUM(volume),0) AS total_volume FROM drawer_denos WHERE drawer_id = :drawer_id AND user_id = :user_id",
            ['drawer_id' => $drawerId, 'user_id' => $drawerUserId]
        );
        $ghsHistory = $db->query(
            "SELECT denomination, SUM(count) AS count, SUM(volume) AS volume, MAX(date_created) AS last_date 
             FROM drawer_denos 
             WHERE drawer_id = :drawer_id AND user_id = :user_id 
             GROUP BY denomination 
             ORDER BY last_date DESC 
             LIMIT 10",
            ['drawer_id' => $drawerId, 'user_id' => $drawerUserId]
        );

        cfa_ensure_fx_table();
        $cfaTotals = $db->query(
            "SELECT COALESCE(SUM(volume),0) AS total_volume FROM drawer_fx_denos WHERE drawer_id = :drawer_id AND user_id = :user_id AND currency = 'CFA'",
            ['drawer_id' => $drawerId, 'user_id' => $drawerUserId]
        );
        $cfaHistory = $db->query(
            "SELECT denomination, SUM(count) AS count, SUM(volume) AS volume, date_created AS last_date 
             FROM drawer_fx_denos 
             WHERE drawer_id = :drawer_id AND user_id = :user_id AND currency = 'CFA' 
             GROUP BY denomination, date_created 
             ORDER BY date_created DESC 
             LIMIT 10",
            ['drawer_id' => $drawerId, 'user_id' => $drawerUserId]
        );

        $normalizeHistory = function ($rows): array {
            $out = [];
            foreach ((array)$rows as $row) {
                if (is_object($row)) {
                    $row = get_object_vars($row);
                }
                if (!is_array($row)) {
                    continue;
                }
                $out[] = [
                    'denomination' => (float)($row['denomination'] ?? 0),
                    'count' => (int)($row['count'] ?? 0),
                    'volume' => round((float)($row['volume'] ?? 0), 2),
                    'last_date' => (string)($row['last_date'] ?? ''),
                ];
            }
            return $out;
        };

        $normalizeBalances = function (array $balances): array {
            $out = [];
            foreach ($balances as $denomination => $row) {
                $count = (int)($row['count'] ?? 0);
                $volume = (float)($row['volume'] ?? 0);
                if ($count <= 0 || $volume <= 0) {
                    continue;
                }
                $out[] = [
                    'denomination' => (float)$denomination,
                    'count' => $count,
                    'volume' => round($volume, 2),
                ];
            }
            usort($out, function ($a, $b) {
                return ($b['denomination'] <=> $a['denomination']);
            });
            return $out;
        };

        $ghsDenominations = function_exists('Cfas\\cfa_fetch_ghs_denominations')
            ? cfa_fetch_ghs_denominations($drawerId, $drawerUserId)
            : [];
        $cfaDenominations = function_exists('Cfas\\cfa_fetch_fx_denominations')
            ? cfa_fetch_fx_denominations($drawerId, $drawerUserId, 'CFA')
            : [];

        return [
            'drawer_id' => $drawerId,
            'drawer_user_id' => $drawerUserId,
            'ghs_balance' => round((float)($ghsTotals[0]->total_volume ?? 0), 2),
            'cfa_balance' => round((float)($cfaTotals[0]->total_volume ?? 0), 2),
            'ghs_history' => $normalizeHistory($ghsHistory),
            'cfa_history' => $normalizeHistory($cfaHistory),
            'ghs_denominations' => $normalizeBalances($ghsDenominations),
            'cfa_denominations' => $normalizeBalances($cfaDenominations),
        ];
    }
}

if (!function_exists('cfa_manual_fx_adjustment')) {
    function cfa_manual_fx_adjustment(int $userId, array $currencyPayload, float $fallbackAmount, string $direction = 'add', string $transStatus = 'Manual Adjustment'): bool
    {
        $context = cfa_find_active_drawer($userId);
        if (empty($context)) {
            return false;
        }

        $drawerId = $context['drawer_id'];
        $drawerUserId = $context['drawer_user_id'];
        $sign = strtolower($direction) === 'remove' ? -1 : 1;
        $timestamp = date('Y-m-d H:i:s');
        $transId = time();

        $grouped = cfa_group_denominations($currencyPayload);
        $cfaDenos = $grouped['CFA'] ?? [];

        cfa_process_fx_denominations(
            $drawerId,
            $drawerUserId,
            $cfaDenos,
            $sign,
            'CFA',
            $transStatus,
            $transId,
            $timestamp,
            abs($fallbackAmount)
        );

        cfa_refresh_cash_channel($drawerId, $drawerUserId, 'CASH_CFA', $transId, $transStatus, true, 'CFA');
        return true;
    }
}

if (!function_exists('cfa_find_active_drawer')) {
    function cfa_find_active_drawer(int $userId): ?array
    {
        $db = new Database;
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime($today . ' +1 day'));
        $row = $db->query(
            "SELECT id, user_id FROM cashdrawers
             WHERE user_id = :user_id
               AND date_created >= :day_start AND date_created < :day_end
             ORDER BY id DESC LIMIT 1",
            [
                'user_id' => $userId,
                'day_start' => $today . ' 00:00:00',
                'day_end' => $tomorrow . ' 00:00:00',
            ]
        );
        if (empty($row)) {
            $row = $db->query("SELECT id, user_id FROM cashdrawers WHERE user_id = :user_id ORDER BY date_created DESC, id DESC LIMIT 1", ['user_id' => $userId]);
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

if (!function_exists('cfa_determine_flow')) {
    function cfa_determine_flow(string $transactionType, float $amountCfa, float $amountGhs): array
    {
        $transactionType = cfa_normalize_transaction_type($transactionType);
        $flow = [];
        if ($transactionType === 'cfa to ghs') {
            $flow['CFA'] = ['sign' => 1, 'fallback' => $amountCfa]; // cash in
            $flow['GHS'] = ['sign' => -1, 'fallback' => $amountGhs]; // cash out
        } elseif ($transactionType === 'ghs to cfa') {
            $flow['GHS'] = ['sign' => 1, 'fallback' => $amountGhs];
            $flow['CFA'] = ['sign' => -1, 'fallback' => $amountCfa];
        }
        return $flow;
    }
}

if (!function_exists('cfa_group_denominations')) {
    function cfa_group_denominations(array $payload): array
    {
        $grouped = [
            'GHS' => [],
            'CFA' => [],
        ];
        foreach ($payload as $row) {
            $currency = strtoupper($row['currency'] ?? '');
            if (!isset($grouped[$currency])) {
                $grouped[$currency] = [];
            }
            $grouped[$currency][] = $row;
        }
        return $grouped;
    }
}

if (!function_exists('cfa_process_ghs_denominations')) {
    function cfa_process_ghs_denominations(int $drawerId, int $drawerUserId, array $denos, int $sign, string $transStatus, int $transId, string $timestamp, float $fallbackAmount, string $transSource = ''): void
    {
        if ($drawerId <= 0 || $sign === 0) {
            return;
        }
        if (empty($denos) && $fallbackAmount > 0) {
            $denos[] = [
                'denomination' => $fallbackAmount,
                'count' => 1,
                'volume' => $fallbackAmount,
            ];
        }
        if (empty($denos)) {
            return;
        }
        $db = new Database;
        if (!$db->table_exists(['drawer_denos'])) {
            return;
        }
        $storedStatus = $transSource !== '' ? $transSource . ': ' . $transStatus : $transStatus;
        foreach ($denos as $deno) {
            $volume = (float)($deno['volume'] ?? 0);
            $count = (int)($deno['count'] ?? 0);
            $denomination = (float)($deno['denomination'] ?? 0);
            if ($volume == 0 && $denomination == 0) {
                continue;
            }
            $db->query(
                "INSERT INTO drawer_denos (drawer_id, user_id, denomination, count, volume, date_created, created_by, trans_id, trans_status) VALUES (:drawer_id, :user_id, :denomination, :count, :volume, :date_created, :created_by, :trans_id, :trans_status)",
                [
                    'drawer_id' => $drawerId,
                    'user_id' => $drawerUserId,
                    'denomination' => $denomination,
                    'count' => $sign * $count,
                    'volume' => $sign * $volume,
                    'date_created' => $timestamp,
                    'created_by' => $drawerUserId,
                    'trans_id' => $transId,
                    'trans_status' => $storedStatus,
                ]
            );
        }
    }
}

if (!function_exists('cfa_process_fx_denominations')) {
    function cfa_process_fx_denominations(int $drawerId, int $drawerUserId, array $denos, int $sign, string $currency, string $transStatus, int $transId, string $timestamp, float $fallbackAmount, string $transSource = ''): void
    {
        if ($drawerId <= 0 || $sign === 0) {
            return;
        }
        if (empty($denos) && $fallbackAmount > 0) {
            $denos[] = [
                'currency' => $currency,
                'denomination' => $fallbackAmount,
                'count' => 1,
                'volume' => $fallbackAmount,
            ];
        }
        if (empty($denos)) {
            return;
        }
        cfa_ensure_fx_table();
        $db = new Database;
        $storedStatus = $transSource !== '' ? $transSource . ': ' . $transStatus : $transStatus;
        foreach ($denos as $deno) {
            $volume = (float)($deno['volume'] ?? 0);
            $count = (int)($deno['count'] ?? 0);
            $denomination = (float)($deno['denomination'] ?? 0);
            if ($volume == 0 && $denomination == 0) {
                continue;
            }
            $db->query(
                "INSERT INTO drawer_fx_denos (drawer_id, user_id, currency, denomination, count, volume, date_created, created_by, trans_id, trans_status) VALUES (:drawer_id, :user_id, :currency, :denomination, :count, :volume, :date_created, :created_by, :trans_id, :trans_status)",
                [
                    'drawer_id' => $drawerId,
                    'user_id' => $drawerUserId,
                    'currency' => strtoupper($currency),
                    'denomination' => $denomination,
                    'count' => $sign * $count,
                    'volume' => $sign * $volume,
                    'date_created' => $timestamp,
                    'created_by' => $drawerUserId,
                    'trans_id' => $transId,
                    'trans_status' => $storedStatus,
                ]
            );
        }
    }
}

if (!function_exists('cfa_ensure_fx_table')) {
    function cfa_ensure_fx_table(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $db = new Database;
        $db->query("CREATE TABLE IF NOT EXISTS drawer_fx_denos (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            drawer_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            currency VARCHAR(10) NOT NULL,
            denomination DECIMAL(18,2) NOT NULL,
            count INT NOT NULL,
            volume DECIMAL(18,2) NOT NULL,
            date_created DATETIME NOT NULL,
            created_by INT UNSIGNED NOT NULL,
            trans_id INT UNSIGNED DEFAULT 0,
            trans_status VARCHAR(50) DEFAULT NULL,
            INDEX drawer_fx_idx (drawer_id),
            INDEX drawer_fx_currency_idx (currency)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $ensured = true;
    }
}

if (!function_exists('cfa_refresh_cash_channel')) {
    function cfa_refresh_cash_channel(int $drawerId, int $drawerUserId, string $channelKey, int $transId, string $transStatus, bool $isFx = false, string $currency = 'GHS'): void
    {
        if ($drawerId <= 0) {
            return;
        }
        $db = new Database;
        $db->query('SELECT id FROM drawer_balances LIMIT 0');
        if ($db->has_error) {
            throw new \RuntimeException('The cashdrawer balance table is unavailable: ' . $db->error);
        }
		$isOpeningSnapshot = stripos((string)$transStatus, 'opening') !== false;
        if ($isFx) {
            cfa_ensure_fx_table();
            $sumRows = $db->query(
                "SELECT 
                    COALESCE(SUM(volume),0) AS total_volume,
                    COALESCE(SUM(CASE
                        WHEN volume > 0
                         AND NOT (
                            LOWER(TRIM(COALESCE(trans_status,''))) IN ('opening snapshot','opening cfa snapshot')
                            OR (
                                trans_id = :opening_trans_id_credit
                                AND LOWER(TRIM(COALESCE(trans_status,''))) NOT LIKE 'vault:%'
                            )
                         )
                        THEN volume ELSE 0 END),0) AS total_credit,
                    COALESCE(SUM(CASE
                        WHEN volume < 0
                         AND NOT (
                            LOWER(TRIM(COALESCE(trans_status,''))) IN ('opening snapshot','opening cfa snapshot')
                            OR (
                                trans_id = :opening_trans_id_debit
                                AND LOWER(TRIM(COALESCE(trans_status,''))) NOT LIKE 'vault:%'
                            )
                         )
                        THEN ABS(volume) ELSE 0 END),0) AS total_debit
                 FROM drawer_fx_denos 
                 WHERE drawer_id = :drawer_id AND user_id = :user_id AND currency = :currency",
                [
                    'drawer_id' => $drawerId,
                    'user_id' => $drawerUserId,
                    'currency' => strtoupper($currency),
                    'opening_trans_id_credit' => $drawerId,
                    'opening_trans_id_debit' => $drawerId,
                ]
            );
        } else {
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
        }
        if ($db->has_error) {
            throw new \RuntimeException('Unable to calculate the cashdrawer cash balance: ' . $db->error);
        }
        $currentClosing = !empty($sumRows) ? (float)($sumRows[0]->total_volume ?? 0) : 0.0;
        $totalCredit = !empty($sumRows) ? (float)($sumRows[0]->total_credit ?? 0) : 0.0;
        $totalDebit = !empty($sumRows) ? (float)($sumRows[0]->total_debit ?? 0) : 0.0;
        $rowsBal = $db->query("SELECT * FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'cash' AND channel_key = :channel_key ORDER BY id DESC LIMIT 1", [
            'drawer_id' => $drawerId,
            'user_id' => $drawerUserId,
            'channel_key' => $channelKey,
        ]);
        if ($db->has_error) {
            throw new \RuntimeException('Unable to read the cashdrawer balance: ' . $db->error);
        }
        $now = date('Y-m-d H:i:s');
        if (empty($rowsBal)) {
			$openingBalance = 0.0;
			$credit = $totalCredit;
			$debit = $totalDebit;
			if ($isOpeningSnapshot) {
				$openingBalance = $currentClosing;
				$credit = 0.0;
				$debit = 0.0;
			}
            $db->query(
                "INSERT INTO drawer_balances (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status) VALUES (:drawer_id, :user_id, 'cash', :channel_key, :opening, :credit, :debit, :closing, :date_created, :trans_id, :trans_status)",
                [
                    'drawer_id' => $drawerId,
                    'user_id' => $drawerUserId,
                    'channel_key' => $channelKey,
					'opening' => $openingBalance,
					'credit' => $credit,
					'debit' => $debit,
                    'closing' => $currentClosing,
                    'date_created' => $now,
                    'trans_id' => $transId,
                    'trans_status' => $transStatus,
                ]
            );
            if ($db->has_error) {
                throw new \RuntimeException('Unable to create the cashdrawer balance: ' . $db->error);
            }
            return;
        }
        $rowBal = $rowsBal[0];
		$credit = $totalCredit;
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
        if ($db->has_error) {
            throw new \RuntimeException('Unable to refresh the cashdrawer balance: ' . $db->error);
        }
    }
}

if (!function_exists('cfa_remove_cashdrawer_entries')) {
    function cfa_remove_cashdrawer_entries(int $transId): void
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

        cfa_ensure_fx_table();
        if ($db->table_exists(['drawer_fx_denos'])) {
            $db->query(
                "DELETE FROM drawer_fx_denos WHERE trans_id = :trans_id",
                ['trans_id' => $transId]
            );
        }
    }

    if (!function_exists('cfa_reverse_transaction_entries')) {
        function cfa_reverse_transaction_entries(int $transId, int $actorUserId, string $timestamp): array
        {
            if ($transId <= 0) {
                return ['ok' => false, 'message' => 'Missing CFA transaction reference.'];
            }

            cfa_ensure_fx_table();
            $db = new Database;
            $entriesByTable = [
                'drawer_denos' => ['currency' => null, 'channel' => 'CASH_DRAWER'],
                'drawer_fx_denos' => ['currency' => 'CFA', 'channel' => 'CASH_CFA'],
            ];
            $affectedChannels = [];

            foreach ($entriesByTable as $table => $details) {
                if (!$db->table_exists([$table])) {
                    continue;
                }
                $params = ['trans_id' => $transId];
                $currencyFilter = '';
                if ($details['currency'] !== null) {
                    $currencyFilter = ' AND currency = :currency';
                    $params['currency'] = $details['currency'];
                }
                $entries = $db->query(
                    "SELECT drawer_id, user_id, denomination, `count`, volume" .
                    ($details['currency'] !== null ? ', currency' : '') .
                    " FROM {$table} WHERE trans_id = :trans_id{$currencyFilter} ORDER BY id ASC",
                    $params
                ) ?: [];
                if ($db->has_error) {
                    return ['ok' => false, 'message' => 'Unable to read the original CFA cashdrawer entries: ' . $db->error];
                }

                foreach ($entries as $entry) {
                    $drawerId = (int)($entry->drawer_id ?? 0);
                    $drawerUserId = (int)($entry->user_id ?? 0);
                    $count = (int)($entry->count ?? 0);
                    $volume = (float)($entry->volume ?? 0);
                    if ($drawerId <= 0 || $drawerUserId <= 0 || ($count === 0 && abs($volume) < 0.00001)) {
                        continue;
                    }

                    $balanceParams = [
                        'drawer_id' => $drawerId,
                        'user_id' => $drawerUserId,
                        'denomination' => $entry->denomination,
                    ];
                    if ($details['currency'] !== null) {
                        $balanceParams['currency'] = $details['currency'];
                    }
                    $balanceRows = $db->query(
                        "SELECT COALESCE(SUM(`count`), 0) AS balance_count
                         FROM {$table}
                         WHERE drawer_id = :drawer_id AND user_id = :user_id AND denomination = :denomination{$currencyFilter}",
                        $balanceParams
                    ) ?: [];
                    if ($db->has_error) {
                        return ['ok' => false, 'message' => 'Unable to validate the CFA drawer balance: ' . $db->error];
                    }
                    $availableCount = (int)($balanceRows[0]->balance_count ?? 0);
                    if ($availableCount - $count < 0) {
                        return [
                            'ok' => false,
                            'message' => 'The cashdrawer no longer has the original denomination balance required to reverse this CFA transaction.',
                        ];
                    }

                    $insertParams = [
                        'drawer_id' => $drawerId,
                        'user_id' => $drawerUserId,
                        'denomination' => $entry->denomination,
                        'count' => -$count,
                        'volume' => -$volume,
                        'date_created' => $timestamp,
                        'created_by' => $actorUserId,
                        'trans_id' => $transId,
                        'trans_status' => 'Cancelled',
                    ];
                    if ($details['currency'] !== null) {
                        $db->query(
                            "INSERT INTO {$table}
                             (drawer_id, user_id, currency, denomination, `count`, volume, date_created, created_by, trans_id, trans_status)
                             VALUES (:drawer_id, :user_id, :currency, :denomination, :count, :volume, :date_created, :created_by, :trans_id, :trans_status)",
                            array_merge($insertParams, ['currency' => $entry->currency ?? $details['currency']])
                        );
                    } else {
                        $db->query(
                            "INSERT INTO {$table}
                             (drawer_id, user_id, denomination, `count`, volume, date_created, created_by, trans_id, trans_status)
                             VALUES (:drawer_id, :user_id, :denomination, :count, :volume, :date_created, :created_by, :trans_id, :trans_status)",
                            $insertParams
                        );
                    }
                    if ($db->has_error) {
                        return ['ok' => false, 'message' => 'Unable to save the CFA drawer reversal: ' . $db->error];
                    }
                    $key = $drawerId . ':' . $drawerUserId . ':' . $details['channel'];
                    $affectedChannels[$key] = [
                        'drawer_id' => $drawerId,
                        'user_id' => $drawerUserId,
                        'channel' => $details['channel'],
                        'is_fx' => $details['currency'] !== null,
                        'currency' => $details['currency'] ?? 'GHS',
                    ];
                }
            }

            foreach ($affectedChannels as $channel) {
                cfa_refresh_cash_channel(
                    $channel['drawer_id'],
                    $channel['user_id'],
                    $channel['channel'],
                    $transId,
                    'Cancelled',
                    $channel['is_fx'],
                    $channel['currency']
                );
            }

            return ['ok' => true, 'message' => ''];
        }
    }
}

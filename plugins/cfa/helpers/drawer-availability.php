<?php

namespace Cfas;

use Core\Database;

if (!function_exists('cfa_denom_key')) {
    function cfa_denom_key($denomination): string
    {
        $d = (float)$denomination;
        if ($d <= 0) {
            return '';
        }
        $s = rtrim(rtrim(sprintf('%.6F', $d), '0'), '.');
        return $s;
    }
}

if (!function_exists('cfa_fetch_ghs_denominations')) {
    function cfa_fetch_ghs_denominations(int $drawerId, int $drawerUserId): array
    {
        if ($drawerId <= 0) {
            return [];
        }
        $db = new Database;
        $rows = $db->query(
            "SELECT denomination, SUM(count) AS total_count, SUM(volume) AS total_volume
             FROM drawer_denos
             WHERE drawer_id = :drawer_id AND user_id = :user_id
             GROUP BY denomination",
            ['drawer_id' => $drawerId, 'user_id' => $drawerUserId]
        );
        if (!is_array($rows) && !is_object($rows)) {
            $rows = [];
        }
        $balances = [];
        foreach ($rows as $row) {
            $denomination = (float)($row->denomination ?? 0);
            if ($denomination <= 0) {
                continue;
            }
            $denomKey = cfa_denom_key($denomination);
            if ($denomKey === '') {
                continue;
            }
            $balances[$denomKey] = [
                'count' => (int)($row->total_count ?? 0),
                'volume' => (float)($row->total_volume ?? 0),
            ];
        }
        return $balances;
    }
}

if (!function_exists('cfa_validate_ghs_supply')) {
    function cfa_validate_ghs_supply(array $ghsPayload, array $availableBalances): array
    {
        $errors = [];
        foreach ($ghsPayload as $row) {
            $denomination = (float)($row['denomination'] ?? 0);
            $count = (int)($row['count'] ?? 0);
            if ($denomination <= 0 || $count <= 0) {
                continue;
            }
            $denomKey = cfa_denom_key($denomination);
            $available = ($denomKey !== '' ? ($availableBalances[$denomKey]['count'] ?? 0) : 0);
            if ($available < $count) {
                $errors[] = sprintf(
                    'Insufficient GH₵ %.2f notes. Available: %d, requested: %d.',
                    $denomination,
                    max(0, $available),
                    $count
                );
            }
        }
        return $errors;
    }
}

if (!function_exists('cfa_fetch_fx_denominations')) {
    function cfa_fetch_fx_denominations(int $drawerId, int $drawerUserId, string $currency = 'CFA'): array
    {
        if ($drawerId <= 0) {
            return [];
        }

        if (function_exists('Cfas\\cfa_ensure_fx_table')) {
            \Cfas\cfa_ensure_fx_table();
        }

        $db = new Database;
        if (!$db->table_exists(['drawer_fx_denos'])) {
            return [];
        }

        $rows = $db->query(
            "SELECT denomination, SUM(count) AS total_count, SUM(volume) AS total_volume
             FROM drawer_fx_denos
             WHERE drawer_id = :drawer_id AND user_id = :user_id AND currency = :currency
             GROUP BY denomination",
            ['drawer_id' => $drawerId, 'user_id' => $drawerUserId, 'currency' => strtoupper($currency)]
        );
        if (!is_array($rows) && !is_object($rows)) {
            $rows = [];
        }

        $balances = [];
        foreach ($rows as $row) {
            $denomination = (float)($row->denomination ?? 0);
            if ($denomination <= 0) {
                continue;
            }
            $denomKey = cfa_denom_key($denomination);
            if ($denomKey === '') {
                continue;
            }
            $balances[$denomKey] = [
                'count' => (int)($row->total_count ?? 0),
                'volume' => (float)($row->total_volume ?? 0),
            ];
        }
        return $balances;
    }
}

if (!function_exists('cfa_validate_fx_supply')) {
    function cfa_validate_fx_supply(array $payload, array $availableBalances, string $currencyLabel = 'CFA'): array
    {
        $errors = [];
        foreach ($payload as $row) {
            $denomination = (float)($row['denomination'] ?? 0);
            $count = (int)($row['count'] ?? 0);
            if ($denomination <= 0 || $count <= 0) {
                continue;
            }
            $denomKey = cfa_denom_key($denomination);
            $available = ($denomKey !== '' ? ($availableBalances[$denomKey]['count'] ?? 0) : 0);
            if ($available < $count) {
                $errors[] = sprintf(
                    'Insufficient %s %.2f notes. Available: %d, requested: %d.',
                    $currencyLabel,
                    $denomination,
                    max(0, $available),
                    $count
                );
            }
        }
        return $errors;
    }
}

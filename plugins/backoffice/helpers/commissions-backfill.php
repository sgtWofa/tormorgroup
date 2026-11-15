<?php

namespace BackofficeCommissions;

use Core\Database;

defined('ROOT') or die("Direct script access denied");

function backoffice_has_column(Database $db, string $table, string $column): bool
{
	try {
		$row = $db->query(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table AND COLUMN_NAME = :col LIMIT 1",
			['db' => DB_NAME, 'table' => $table, 'col' => $column]
		);
		return !empty($row);
	} catch (\Throwable $e) {
		return false;
	}
}

function backoffice_round_special(float $value): float
{
	$v = (float)$value;
	if (!is_finite($v) || $v <= 0) {
		return 0.0;
	}
	$whole = floor($v + 1e-9);
	$frac = $v - $whole;
	if ($frac < 0) $frac = 0;
	if ($frac > 0.999999) $frac = 0.999999;
	if ($frac > 0.000001 && $frac < 0.5 - 0.000001) {
		return $whole + 0.5;
	}
	if ($frac > 0.5 + 0.000001) {
		return $whole + 1;
	}
	if (abs($frac - 0.5) <= 0.000001) {
		return $whole + 0.5;
	}
	return $whole;
}

function backoffice_normalize_momoremit_fee_option($value): string
{
	return preg_replace('/[^a-z0-9+\-]+/', '', strtolower(trim((string)$value)));
}

function backoffice_calc_momoremit_merchant_cashin_sending(object $row): array
{
	$amount = round(max(0, (float)($row->amount_load ?? 0)), 2);
	$category = backoffice_normalize_momoremit_fee_option($row->category ?? '');
	$chargingType = backoffice_normalize_momoremit_fee_option($row->charging_type ?? '');

	$inFee = backoffice_round_special($amount * 0.01);
	$outFee = $category === 'inout' ? backoffice_round_special(min($amount * 0.005, 20)) : 0.0;
	$sendBeforeMerchantCharge = $amount;
	$amountPayable = $amount;

	if ($category === 'subtractin') {
		$sendBeforeMerchantCharge = max(0, round($amount - $inFee, 2));
	} elseif ($category === 'onlyin') {
		$amountPayable = round($amount + $inFee, 2);
	} elseif ($category === 'inout') {
		$sendBeforeMerchantCharge = round($amount + $outFee, 2);
		$amountPayable = round($amount + $inFee + $outFee, 2);
	}

	$rawMerchantCharge = round(min($sendBeforeMerchantCharge * 0.005, 25), 2);
	$merchantCharge = backoffice_round_special($rawMerchantCharge);
	if ($chargingType === '-fee') {
		$amountSendable = max(0, round($sendBeforeMerchantCharge - $rawMerchantCharge, 2));
		$ecashDebit = $sendBeforeMerchantCharge;
	} else {
		$amountSendable = $sendBeforeMerchantCharge;
		$amountPayable = round($amountPayable + $merchantCharge, 2);
		$ecashDebit = round($sendBeforeMerchantCharge + $rawMerchantCharge, 2);
	}

	return [
		'amount_payable' => round($amountPayable, 2),
		'amount_sendable' => round($amountSendable, 2),
		'charge' => round($inFee + $outFee + $merchantCharge, 2),
		'in_fee' => $inFee,
		'out_fee' => $outFee,
		'merchant_charge' => $merchantCharge,
		'raw_merchant_charge' => $rawMerchantCharge,
		'ecash_debit' => round($ecashDebit, 2),
	];
}

function backoffice_calc_momoremit_commission_amount(object $row): float
{
	$chargeValue = (float)($row->charge ?? 0);
	if ($chargeValue <= 0) {
		return 0.0;
	}

	$transactionType = strtolower(trim((string)($row->transaction_type ?? '')));
	$sendingCategory = strtolower(trim((string)($row->sending_category ?? '')));
	$network = strtoupper(trim((string)($row->network ?? '')));
	$simType = strtolower(trim((string)($row->sim_type ?? '')));
	if ($transactionType === strtolower('Cash in') && $network === 'MTN' && $simType === 'merchant') {
		if ($sendingCategory === strtolower('Deposit')) {
			return 0.0;
		}
	}
	if ($transactionType === strtolower('Cash in') && $sendingCategory === strtolower('Sending') && $simType === 'merchant') {
		$calc = backoffice_calc_momoremit_merchant_cashin_sending($row);
		return round(max(0, (float)$calc['in_fee']), 2);
	}

	if ($transactionType !== strtolower('Cash in') || $sendingCategory !== strtolower('Sending')) {
		return $chargeValue;
	}

	$chargingType = strtolower(trim((string)($row->charging_type ?? '')));
	$category = strtolower(trim((string)($row->category ?? '')));
	$category = str_replace([' ', '/', '-', '_'], '', $category);
	if ($chargingType === '+fee' && $category === 'inout') {
		$amountLoad = (float)($row->amount_load ?? 0);
		if ($amountLoad > 0) {
			$inFee = backoffice_round_special($amountLoad * 0.01);
			$inFee = (float)number_format($inFee, 2, '.', '');
			if ($inFee > 0) {
				return min($inFee, $chargeValue);
			}
		}
	}

	return $chargeValue;
}

function backoffice_commission_exists(Database $db, string $commissionType, string $needle): bool
{
	try {
		$rows = $db->query(
			"SELECT id FROM commissions WHERE commission_type = :commission_type AND description LIKE :needle LIMIT 1",
			['commission_type' => $commissionType, 'needle' => $needle]
		);
		return !empty($rows);
	} catch (\Throwable $e) {
		return false;
	}
}

function backoffice_ensure_backfill_log(Database $db): void
{
	try {
		$db->query(
			"CREATE TABLE IF NOT EXISTS commissions_backfill_log (
				id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
				slug VARCHAR(100) NOT NULL,
				last_run DATETIME NULL,
				notes TEXT NULL
			)"
		);
	} catch (\Throwable $e) {
		// ignore
	}
}

function backoffice_should_run_backfill(Database $db, string $slug, bool $force): bool
{
	if ($force) {
		return true;
	}
	try {
		$row = $db->query(
			"SELECT id FROM commissions_backfill_log WHERE slug = :slug LIMIT 1",
			['slug' => $slug]
		);
		return empty($row);
	} catch (\Throwable $e) {
		return $force;
	}
}

function backoffice_mark_backfill(Database $db, string $slug, array $stats): void
{
	try {
		$note = json_encode($stats, JSON_UNESCAPED_SLASHES);
		$db->query(
			"INSERT INTO commissions_backfill_log (slug, last_run, notes) VALUES (:slug, :last_run, :notes)",
			[
				'slug' => $slug,
				'last_run' => date('Y-m-d H:i:s'),
				'notes' => $note,
			]
		);
	} catch (\Throwable $e) {
		// ignore
	}
}

function backoffice_run_charges_backfill(Database $db, bool $force = false): array
{
	$stats = [
		'inserted' => 0,
		'skipped_existing' => 0,
		'skipped_zero' => 0,
		'skipped_record_only' => 0,
		'errors' => 0,
	];

	if (!$db->table_exists(['commissions'])) {
		return $stats;
	}

	backoffice_ensure_backfill_log($db);
	$slug = 'charges_backfill_v1';
	if (!backoffice_should_run_backfill($db, $slug, $force)) {
		return $stats;
	}

	$now = date('Y-m-d H:i:s');

	// Momoremit charges
	if ($db->table_exists(['momoremits'])) {
		$cols = ['id','charge','network','transaction_type','sending_category','category','transaction_status','created_by','date_created'];
		if (backoffice_has_column($db, 'momoremits', 'sim_type')) {
			$cols[] = 'sim_type';
		}
		if (backoffice_has_column($db, 'momoremits', 'charging_type')) {
			$cols[] = 'charging_type';
		}
		if (backoffice_has_column($db, 'momoremits', 'amount_load')) {
			$cols[] = 'amount_load';
		}
		$selectCols = implode(',', array_unique($cols));
		$rows = $db->query("SELECT {$selectCols} FROM momoremits WHERE (deleted IS NULL OR deleted = 0) AND COALESCE(charge,0) > 0") ?: [];
		foreach ($rows as $row) {
			try {
				$commissionAmount = backoffice_calc_momoremit_commission_amount($row);
				if ($commissionAmount <= 0) {
					$stats['skipped_zero']++;
					continue;
				}
				$commissionDescription = 'MOMOREMIT CHARGE | ' . strtoupper((string)($row->network ?? '')) . ' | ' . strtoupper((string)($row->transaction_type ?? '')) . ' | ' . strtoupper((string)($row->sending_category ?? '')) . ' | ID ' . ($row->id ?? '');
				$needle = '%| ID ' . ($row->id ?? '');
				if (backoffice_commission_exists($db, 'MOMOREMIT CHARGE', $needle) || backoffice_commission_exists($db, 'MOMOREMIT CASHIN', $needle)) {
					$stats['skipped_existing']++;
					continue;
				}
				$commissionInsert = [
					'date_received' => $row->date_created ?? $now,
					'colletion_period' => 'INSTANT',
					'network' => $row->network ?? null,
					'commission_type' => 'MOMOREMIT CHARGE',
					'description' => $commissionDescription,
					'pay_to' => (int)($row->created_by ?? 0),
					'receiving_wallet' => null,
					'amount' => $commissionAmount,
					'transaction_status' => $row->transaction_status ?? 'Approved',
					'created_by' => (int)($row->created_by ?? 0),
					'updated_by' => null,
					'disabled' => 0,
					'deleted' => 0,
					'date_created' => $row->date_created ?? $now,
					'date_updated' => null,
					'date_deleted' => null,
				];
				$commissionSql = "INSERT INTO commissions (".implode(',', array_keys($commissionInsert)).") VALUES (:".implode(',:', array_keys($commissionInsert)).")";
				$db->query($commissionSql, $commissionInsert);
				$stats['inserted']++;
			} catch (\Throwable $e) {
				$stats['errors']++;
			}
		}
	}

	// Agency banking charges
	if ($db->table_exists(['agencybankings'])) {
		$rows = $db->query("SELECT id, charge, network, transaction_type, transaction_status, created_by, date_created FROM agencybankings WHERE (deleted IS NULL OR deleted = 0) AND COALESCE(charge,0) > 0") ?: [];
		foreach ($rows as $row) {
			try {
				$chargeValue = (float)($row->charge ?? 0);
				if ($chargeValue <= 0) {
					$stats['skipped_zero']++;
					continue;
				}
				$transactionType = strtolower(trim((string)($row->transaction_type ?? '')));
				$needle = '%| ID ' . ($row->id ?? '');
				if (strpos($transactionType, 'interbank') !== false || strpos($transactionType, 'gip') !== false) {
					$db->query(
						"DELETE FROM commissions WHERE commission_type = :commission_type AND description LIKE :needle",
						[
							'commission_type' => 'AGENCYBANKING CHARGE',
							'needle' => $needle,
						]
					);
					$stats['skipped_record_only']++;
					continue;
				}
				$commissionDescription = 'AGENCYBANKING CHARGE | ' . ($row->network ?? '') . ' | ' . ($row->transaction_type ?? '') . ' | ID ' . ($row->id ?? '');
				if (backoffice_commission_exists($db, 'AGENCYBANKING CHARGE', $needle)) {
					$stats['skipped_existing']++;
					continue;
				}
				$commissionInsert = [
					'date_received' => $row->date_created ?? $now,
					'colletion_period' => 'INSTANT',
					'network' => $row->network ?? null,
					'commission_type' => 'AGENCYBANKING CHARGE',
					'description' => $commissionDescription,
					'pay_to' => (int)($row->created_by ?? 0),
					'receiving_wallet' => null,
					'amount' => $chargeValue,
					'transaction_status' => $row->transaction_status ?? 'Approved',
					'created_by' => (int)($row->created_by ?? 0),
					'updated_by' => null,
					'disabled' => 0,
					'deleted' => 0,
					'date_created' => $row->date_created ?? $now,
					'date_updated' => null,
					'date_deleted' => null,
				];
				$commissionSql = "INSERT INTO commissions (".implode(',', array_keys($commissionInsert)).") VALUES (:".implode(',:', array_keys($commissionInsert)).")";
				$db->query($commissionSql, $commissionInsert);
				$stats['inserted']++;
			} catch (\Throwable $e) {
				$stats['errors']++;
			}
		}
	}

	// Payment services charges
	if ($db->table_exists(['payment_services'])) {
		$rows = $db->query("SELECT id, charge, category, institution, payment_medium, bank, network, transaction_status, created_by, date_created FROM payment_services WHERE (deleted IS NULL OR deleted = 0) AND COALESCE(charge,0) > 0") ?: [];
		foreach ($rows as $row) {
			try {
				$chargeValue = (float)($row->charge ?? 0);
				if ($chargeValue <= 0) {
					$stats['skipped_zero']++;
					continue;
				}
				$medium = strtolower(trim((string)($row->payment_medium ?? '')));
				$chargeNetwork = ($medium === 'bank') ? trim((string)($row->bank ?? '')) : trim((string)($row->network ?? ''));
				$chargeNetwork = $chargeNetwork !== '' ? $chargeNetwork : ($row->payment_medium ?? null);
				$commissionDescription = 'PAYMENT SERVICES CHARGE | ' . ($row->category ?? '') . ' | ' . ($row->institution ?? '') . ' | ID ' . ($row->id ?? '');
				$needle = '%| ID ' . ($row->id ?? '');
				if (backoffice_commission_exists($db, 'PAYMENT SERVICES CHARGE', $needle)) {
					$stats['skipped_existing']++;
					continue;
				}
				$commissionInsert = [
					'date_received' => $row->date_created ?? $now,
					'colletion_period' => 'INSTANT',
					'network' => $chargeNetwork !== '' ? $chargeNetwork : null,
					'commission_type' => 'PAYMENT SERVICES CHARGE',
					'description' => $commissionDescription,
					'pay_to' => (int)($row->created_by ?? 0),
					'receiving_wallet' => null,
					'amount' => $chargeValue,
					'transaction_status' => $row->transaction_status ?? 'Approved',
					'created_by' => (int)($row->created_by ?? 0),
					'updated_by' => null,
					'disabled' => 0,
					'deleted' => 0,
					'date_created' => $row->date_created ?? $now,
					'date_updated' => null,
					'date_deleted' => null,
				];
				$commissionSql = "INSERT INTO commissions (".implode(',', array_keys($commissionInsert)).") VALUES (:".implode(',:', array_keys($commissionInsert)).")";
				$db->query($commissionSql, $commissionInsert);
				$stats['inserted']++;
			} catch (\Throwable $e) {
				$stats['errors']++;
			}
		}
	}

	// Ezwich charges are record-only, not commissions. Remove legacy rows and do not backfill them.
	if ($db->table_exists(['ezwichs'])) {
		try {
			$db->query(
				"DELETE FROM commissions WHERE commission_type = :commission_type",
				['commission_type' => 'EZWICH CHARGE']
			);
		} catch (\Throwable $e) {
			$stats['errors']++;
		}
	}

	// Cashpickup charges
	if ($db->table_exists(['cashpickups'])) {
		$rows = $db->query("SELECT id, pickup_charge, transaction_side, payment_method, payout_channel, source, transaction_status, approval_status, created_by, date_created FROM cashpickups WHERE (deleted IS NULL OR deleted = 0) AND COALESCE(pickup_charge,0) > 0") ?: [];
		foreach ($rows as $row) {
			try {
				$chargeValue = (float)($row->pickup_charge ?? 0);
				if ($chargeValue <= 0) {
					$stats['skipped_zero']++;
					continue;
				}
				$side = strtolower(trim((string)($row->transaction_side ?? '')));
				$methodLabel = $side === 'pay'
					? trim((string)($row->payment_method ?? ''))
					: trim((string)($row->source ?? ''));
				$chargeNetwork = $side === 'pay'
					? trim((string)($row->payout_channel ?? ($row->payment_method ?? '')))
					: trim((string)($row->source ?? ''));
				$commissionDescription = 'CASHPICKUP CHARGE | ' . strtoupper($side) . ' | ' . $methodLabel . ' | ID ' . ($row->id ?? '');
				$needle = '%| ID ' . ($row->id ?? '');
				if (backoffice_commission_exists($db, 'CASHPICKUP CHARGE', $needle)) {
					$stats['skipped_existing']++;
					continue;
				}
				$commissionInsert = [
					'date_received' => $row->date_created ?? $now,
					'colletion_period' => 'INSTANT',
					'network' => $chargeNetwork !== '' ? $chargeNetwork : null,
					'commission_type' => 'CASHPICKUP CHARGE',
					'description' => $commissionDescription,
					'pay_to' => (int)($row->created_by ?? 0),
					'receiving_wallet' => null,
					'amount' => $chargeValue,
					'transaction_status' => $row->transaction_status ?? ($row->approval_status ?? 'Approved'),
					'created_by' => (int)($row->created_by ?? 0),
					'updated_by' => null,
					'disabled' => 0,
					'deleted' => 0,
					'date_created' => $row->date_created ?? $now,
					'date_updated' => null,
					'date_deleted' => null,
				];
				$commissionSql = "INSERT INTO commissions (".implode(',', array_keys($commissionInsert)).") VALUES (:".implode(',:', array_keys($commissionInsert)).")";
				$db->query($commissionSql, $commissionInsert);
				$stats['inserted']++;
			} catch (\Throwable $e) {
				$stats['errors']++;
			}
		}
	}

	backoffice_mark_backfill($db, $slug, $stats);
	return $stats;
}

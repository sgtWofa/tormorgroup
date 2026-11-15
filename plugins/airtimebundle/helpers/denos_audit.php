<?php

(defined('ROOT') || defined('FCPATH')) or die("Direct script access denied");

if (!function_exists('airtimebundle_denos_audit_db')) {
	function airtimebundle_denos_audit_db(): \PDO
	{
		static $pdo = null;

		if ($pdo instanceof \PDO) {
			return $pdo;
		}

		$dsn = DB_DRIVER . ':host=' . DB_HOST . ';dbname=' . DB_NAME;
		$pdo = new \PDO($dsn, DB_USER, DB_PASSWORD, [
			\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
			\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
		]);

		return $pdo;
	}
}

if (!function_exists('airtimebundle_denos_audit_money')) {
	function airtimebundle_denos_audit_money($value): float
	{
		return round((float)$value, 2);
	}
}

if (!function_exists('airtimebundle_denos_audit_approx')) {
	function airtimebundle_denos_audit_approx(float $left, float $right, float $epsilon = 0.01): bool
	{
		return abs($left - $right) <= $epsilon;
	}
}

if (!function_exists('airtimebundle_denos_audit_sum_field')) {
	function airtimebundle_denos_audit_sum_field(array $rows, string $field): float
	{
		$total = 0.0;
		foreach ($rows as $row) {
			$total += airtimebundle_denos_audit_money($row[$field] ?? 0);
		}
		return round($total, 2);
	}
}

if (!function_exists('airtimebundle_denos_audit_csv_list')) {
	function airtimebundle_denos_audit_csv_list(array $values): string
	{
		$values = array_values(array_filter(array_map(static function ($value) {
			return $value === null ? '' : trim((string)$value);
		}, $values), static fn($value) => $value !== ''));

		return implode(', ', $values);
	}
}

if (!function_exists('airtimebundle_denos_audit_fetch_all_rows')) {
	function airtimebundle_denos_audit_fetch_all_rows(string $sql, array $params = []): array
	{
		$stmt = airtimebundle_denos_audit_db()->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll() ?: [];
	}
}

if (!function_exists('airtimebundle_denos_audit_fetch_batch_transactions')) {
	function airtimebundle_denos_audit_fetch_batch_transactions(string $createdBy, string $dateCreated): array
	{
		return airtimebundle_denos_audit_fetch_all_rows(
			"SELECT a.id,
					a.transaction_type,
					a.category,
					a.network,
					a.phone,
					a.receiver,
					ROUND(CAST(COALESCE(NULLIF(a.amount, ''), '0') AS DECIMAL(18,2)), 2) AS amount_value,
					ROUND(COALESCE(a.amount_paid, CAST(COALESCE(NULLIF(a.amount, ''), '0') AS DECIMAL(18,2))), 2) AS amount_paid_value,
					a.transaction_status,
					a.created_by,
					DATE_FORMAT(a.date_created, '%Y-%m-%d %H:%i:%s') AS date_created,
					CASE WHEN EXISTS (SELECT 1 FROM denos d WHERE d.trans_id = a.id) THEN 1 ELSE 0 END AS has_denos
			 FROM airtimebundles a
			 WHERE a.created_by = :created_by
			   AND a.date_created = :date_created
			 ORDER BY a.id ASC",
			[
				'created_by' => $createdBy,
				'date_created' => $dateCreated,
			]
		);
	}
}

if (!function_exists('airtimebundle_denos_audit_fetch_buckets')) {
	function airtimebundle_denos_audit_fetch_buckets(string $table, string $createdBy, string $dateCreated): array
	{
		return airtimebundle_denos_audit_fetch_all_rows(
			"SELECT trans_id,
					ROUND(SUM(CASE WHEN volume > 0 THEN volume ELSE 0 END), 2) AS positive_total,
					ROUND(SUM(volume), 2) AS net_total,
					GROUP_CONCAT(CONCAT(COALESCE(denomination, ''), ' x ', COALESCE(`count`, ''), ' = ', COALESCE(volume, '')) ORDER BY denomination DESC SEPARATOR ' | ') AS breakdown
			 FROM {$table}
			 WHERE created_by = :created_by
			   AND date_created = :date_created
			 GROUP BY trans_id
			 ORDER BY trans_id ASC",
			[
				'created_by' => $createdBy,
				'date_created' => $dateCreated,
			]
		);
	}
}

if (!function_exists('airtimebundle_denos_audit_classify_batch')) {
	function airtimebundle_denos_audit_classify_batch(array $missingRows, array $allRows, array $denoBuckets, array $drawerBuckets): array
	{
		$missingAmount = airtimebundle_denos_audit_sum_field($missingRows, 'amount_value');
		$missingPaid = airtimebundle_denos_audit_sum_field($missingRows, 'amount_paid_value');
		$allAmount = airtimebundle_denos_audit_sum_field($allRows, 'amount_value');
		$allPaid = airtimebundle_denos_audit_sum_field($allRows, 'amount_paid_value');

		$singleMissing = count($missingRows) === 1;
		$singleDeno = count($denoBuckets) === 1 ? $denoBuckets[0] : null;
		$singleDrawer = count($drawerBuckets) === 1 ? $drawerBuckets[0] : null;

		if ($singleMissing && $singleDeno
			&& airtimebundle_denos_audit_approx(airtimebundle_denos_audit_money($singleDeno['positive_total'] ?? 0), $missingPaid)
			&& airtimebundle_denos_audit_approx(airtimebundle_denos_audit_money($singleDeno['net_total'] ?? 0), $missingAmount)) {
			return [
				'code' => 'SAFE_SINGLE_DENO_MATCH',
				'recommendation' => 'Single missing transaction with one exact denos bucket. Safe for manual backfill review.',
			];
		}

		if ($singleMissing && $singleDrawer
			&& airtimebundle_denos_audit_approx(airtimebundle_denos_audit_money($singleDrawer['positive_total'] ?? 0), $missingPaid)
			&& airtimebundle_denos_audit_approx(airtimebundle_denos_audit_money($singleDrawer['net_total'] ?? 0), $missingAmount)) {
			return [
				'code' => 'SAFE_SINGLE_DRAWER_MATCH',
				'recommendation' => 'Single missing transaction with one exact drawer_denos bucket. Safe for manual backfill review.',
			];
		}

		if ((count($allRows) > 1 && ($singleDeno || $singleDrawer))
			|| ($singleDeno && (airtimebundle_denos_audit_approx(airtimebundle_denos_audit_money($singleDeno['positive_total'] ?? 0), $allPaid) || airtimebundle_denos_audit_approx(airtimebundle_denos_audit_money($singleDeno['net_total'] ?? 0), $allAmount)))
			|| ($singleDrawer && (airtimebundle_denos_audit_approx(airtimebundle_denos_audit_money($singleDrawer['positive_total'] ?? 0), $allPaid) || airtimebundle_denos_audit_approx(airtimebundle_denos_audit_money($singleDrawer['net_total'] ?? 0), $allAmount)))) {
			return [
				'code' => 'AMBIGUOUS_SHARED_BATCH',
				'recommendation' => 'One historical denomination bucket appears to cover multiple same-timestamp transactions. Review manually and do not auto-backfill.',
			];
		}

		return [
			'code' => 'NO_SAFE_EVIDENCE',
			'recommendation' => 'No unique exact historical denomination evidence was found for a reliable backfill.',
		];
	}
}

if (!function_exists('airtimebundle_denos_audit_summary_markdown')) {
	function airtimebundle_denos_audit_summary_markdown(array $counts, string $generatedAt): string
	{
		$lines = [];
		$lines[] = '# Airtimebundle Denomination Recovery Audit';
		$lines[] = '';
		$lines[] = 'Generated: ' . $generatedAt;
		$lines[] = '';
		$lines[] = '## Summary';
		$lines[] = '';
		$lines[] = '- Missing airtimebundle transactions: ' . ($counts['total_missing_transactions'] ?? 0);
		$lines[] = '- Missing timestamp batches: ' . ($counts['total_batches'] ?? 0);
		$lines[] = '- Safe single-row denos matches: ' . ($counts['safe_single_deno_match'] ?? 0);
		$lines[] = '- Safe single-row drawer matches: ' . ($counts['safe_single_drawer_match'] ?? 0);
		$lines[] = '- Ambiguous shared batches: ' . ($counts['ambiguous_shared_batch'] ?? 0);
		$lines[] = '- No-safe-evidence batches: ' . ($counts['no_safe_evidence'] ?? 0);
		$lines[] = '';
		$lines[] = '## Interpretation';
		$lines[] = '';
		$lines[] = '- `SAFE_SINGLE_DENO_MATCH`: one missing transaction and one exact `denos` bucket match its totals.';
		$lines[] = '- `SAFE_SINGLE_DRAWER_MATCH`: one missing transaction and one exact `drawer_denos` bucket match its totals.';
		$lines[] = '- `AMBIGUOUS_SHARED_BATCH`: one historical bucket appears to cover multiple same-timestamp transactions, so auto-recovery would be guesswork.';
		$lines[] = '- `NO_SAFE_EVIDENCE`: no unique exact history was found for a reliable backfill.';
		$lines[] = '';
		$lines[] = '## Files';
		$lines[] = '';
		$lines[] = '- `batches.csv`: one row per ambiguous timestamp batch.';
		$lines[] = '- `transactions.csv`: one row per missing transaction for staff case-by-case review.';
		$lines[] = '';

		return implode(PHP_EOL, $lines) . PHP_EOL;
	}
}

if (!function_exists('airtimebundle_generate_denos_audit_report')) {
	function airtimebundle_generate_denos_audit_report(bool $writeFiles = true): array
	{
		$missingRows = airtimebundle_denos_audit_fetch_all_rows(
			"SELECT a.id,
					a.transaction_type,
					a.category,
					a.network,
					a.phone,
					a.receiver,
					ROUND(CAST(COALESCE(NULLIF(a.amount, ''), '0') AS DECIMAL(18,2)), 2) AS amount_value,
					ROUND(COALESCE(a.amount_paid, CAST(COALESCE(NULLIF(a.amount, ''), '0') AS DECIMAL(18,2))), 2) AS amount_paid_value,
					a.transaction_status,
					a.created_by,
					DATE_FORMAT(a.date_created, '%Y-%m-%d %H:%i:%s') AS date_created
			 FROM airtimebundles a
			 LEFT JOIN denos d
			   ON d.trans_id = a.id
			 WHERE d.id IS NULL
			 ORDER BY a.date_created DESC, a.id ASC"
		);

		$groups = [];
		foreach ($missingRows as $row) {
			$key = ($row['created_by'] ?? '') . '|' . ($row['date_created'] ?? '');
			$groups[$key][] = $row;
		}

		$batchRows = [];
		$transactionRows = [];
		$counts = [
			'total_missing_transactions' => count($missingRows),
			'total_batches' => count($groups),
			'safe_single_deno_match' => 0,
			'safe_single_drawer_match' => 0,
			'ambiguous_shared_batch' => 0,
			'no_safe_evidence' => 0,
		];

		foreach ($groups as $groupRows) {
			$createdBy = (string)($groupRows[0]['created_by'] ?? '');
			$dateCreated = (string)($groupRows[0]['date_created'] ?? '');

			$allRows = airtimebundle_denos_audit_fetch_batch_transactions($createdBy, $dateCreated);
			$denoBuckets = airtimebundle_denos_audit_fetch_buckets('denos', $createdBy, $dateCreated);
			$drawerBuckets = airtimebundle_denos_audit_fetch_buckets('drawer_denos', $createdBy, $dateCreated);
			$class = airtimebundle_denos_audit_classify_batch($groupRows, $allRows, $denoBuckets, $drawerBuckets);

			$key = strtolower($class['code']);
			if (isset($counts[$key])) {
				$counts[$key]++;
			}

			$linkedIds = [];
			$allIds = [];
			foreach ($allRows as $allRow) {
				$allIds[] = $allRow['id'] ?? '';
				if (!empty($allRow['has_denos'])) {
					$linkedIds[] = $allRow['id'] ?? '';
				}
			}

			$denoBucketIds = array_map(static fn($row) => (string)($row['trans_id'] ?? ''), $denoBuckets);
			$drawerBucketIds = array_map(static fn($row) => (string)($row['trans_id'] ?? ''), $drawerBuckets);

			$batchRows[] = [
				'date_created' => $dateCreated,
				'created_by' => $createdBy,
				'missing_tx_count' => count($groupRows),
				'missing_tx_ids' => airtimebundle_denos_audit_csv_list(array_column($groupRows, 'id')),
				'all_tx_count_same_timestamp' => count($allRows),
				'all_tx_ids_same_timestamp' => airtimebundle_denos_audit_csv_list($allIds),
				'linked_tx_ids_same_timestamp' => airtimebundle_denos_audit_csv_list($linkedIds),
				'missing_amount_total' => number_format(airtimebundle_denos_audit_sum_field($groupRows, 'amount_value'), 2, '.', ''),
				'missing_amount_paid_total' => number_format(airtimebundle_denos_audit_sum_field($groupRows, 'amount_paid_value'), 2, '.', ''),
				'all_amount_total_same_timestamp' => number_format(airtimebundle_denos_audit_sum_field($allRows, 'amount_value'), 2, '.', ''),
				'all_amount_paid_total_same_timestamp' => number_format(airtimebundle_denos_audit_sum_field($allRows, 'amount_paid_value'), 2, '.', ''),
				'denos_bucket_count' => count($denoBuckets),
				'denos_bucket_trans_ids' => airtimebundle_denos_audit_csv_list($denoBucketIds),
				'denos_positive_total' => number_format(airtimebundle_denos_audit_sum_field($denoBuckets, 'positive_total'), 2, '.', ''),
				'denos_net_total' => number_format(airtimebundle_denos_audit_sum_field($denoBuckets, 'net_total'), 2, '.', ''),
				'denos_breakdowns' => airtimebundle_denos_audit_csv_list(array_column($denoBuckets, 'breakdown')),
				'drawer_bucket_count' => count($drawerBuckets),
				'drawer_bucket_trans_ids' => airtimebundle_denos_audit_csv_list($drawerBucketIds),
				'drawer_positive_total' => number_format(airtimebundle_denos_audit_sum_field($drawerBuckets, 'positive_total'), 2, '.', ''),
				'drawer_net_total' => number_format(airtimebundle_denos_audit_sum_field($drawerBuckets, 'net_total'), 2, '.', ''),
				'drawer_breakdowns' => airtimebundle_denos_audit_csv_list(array_column($drawerBuckets, 'breakdown')),
				'recommendation_code' => $class['code'],
				'recommendation' => $class['recommendation'],
			];

			foreach ($groupRows as $row) {
				$transactionRows[] = [
					'date_created' => $dateCreated,
					'created_by' => $createdBy,
					'transaction_id' => $row['id'] ?? '',
					'transaction_type' => $row['transaction_type'] ?? '',
					'category' => $row['category'] ?? '',
					'network' => $row['network'] ?? '',
					'phone' => $row['phone'] ?? '',
					'receiver' => $row['receiver'] ?? '',
					'amount' => number_format(airtimebundle_denos_audit_money($row['amount_value'] ?? 0), 2, '.', ''),
					'amount_paid' => number_format(airtimebundle_denos_audit_money($row['amount_paid_value'] ?? 0), 2, '.', ''),
					'transaction_status' => $row['transaction_status'] ?? '',
					'all_tx_ids_same_timestamp' => airtimebundle_denos_audit_csv_list($allIds),
					'linked_tx_ids_same_timestamp' => airtimebundle_denos_audit_csv_list($linkedIds),
					'denos_bucket_trans_ids' => airtimebundle_denos_audit_csv_list($denoBucketIds),
					'drawer_bucket_trans_ids' => airtimebundle_denos_audit_csv_list($drawerBucketIds),
					'recommendation_code' => $class['code'],
					'recommendation' => $class['recommendation'],
				];
			}
		}

		$generatedAt = gmdate('Y-m-d H:i:s') . ' UTC';
		$summaryMarkdown = airtimebundle_denos_audit_summary_markdown($counts, $generatedAt);
		$result = [
			'generated_at' => $generatedAt,
			'counts' => $counts,
			'batch_rows' => $batchRows,
			'transaction_rows' => $transactionRows,
			'summary_markdown' => $summaryMarkdown,
			'files' => [],
		];

		if ($writeFiles) {
			$result['files'] = airtimebundle_write_denos_audit_files($result);
		}

		return $result;
	}
}

if (!function_exists('airtimebundle_csv_from_rows')) {
	function airtimebundle_csv_from_rows(array $rows): string
	{
		$handle = fopen('php://temp', 'r+');
		if ($handle === false) {
			return '';
		}

		if (!empty($rows)) {
			fputcsv($handle, array_keys($rows[0]));
			foreach ($rows as $row) {
				fputcsv($handle, $row);
			}
		} else {
			fputcsv($handle, ['message']);
			fputcsv($handle, ['No rows available.']);
		}

		rewind($handle);
		$content = stream_get_contents($handle);
		fclose($handle);

		return $content === false ? '' : $content;
	}
}

if (!function_exists('airtimebundle_write_denos_audit_files')) {
	function airtimebundle_write_denos_audit_files(array $report): array
	{
		$basePath = defined('ROOTPATH')
			? ROOTPATH
			: (defined('FCPATH') ? FCPATH : dirname(__DIR__, 3) . DIRECTORY_SEPARATOR);

		$outputDir = $basePath . 'reports' . DIRECTORY_SEPARATOR . 'airtimebundle-denos-audit';
		if (!is_dir($outputDir)) {
			mkdir($outputDir, 0777, true);
		}

		$summaryPath = $outputDir . DIRECTORY_SEPARATOR . 'summary.md';
		$batchCsvPath = $outputDir . DIRECTORY_SEPARATOR . 'batches.csv';
		$transactionCsvPath = $outputDir . DIRECTORY_SEPARATOR . 'transactions.csv';

		file_put_contents($summaryPath, $report['summary_markdown'] ?? '');
		file_put_contents($batchCsvPath, airtimebundle_csv_from_rows($report['batch_rows'] ?? []));
		file_put_contents($transactionCsvPath, airtimebundle_csv_from_rows($report['transaction_rows'] ?? []));

		return [
			'output_dir' => $outputDir,
			'summary' => $summaryPath,
			'batches' => $batchCsvPath,
			'transactions' => $transactionCsvPath,
		];
	}
}

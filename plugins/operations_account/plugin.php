<?php

/**
 * Plugin name: MyWallet
 * Description: A way for admin to manage account
 * 
 * 
 **/

set_value([

	'admin_route'	=>'admin',
	'plugin_route'	=>'operations_accounts',
	'tables'		=>[
		'account_table' 				=> 'mywallets',
	],

	'optional_tables'		=>[
	],
	

]);


/** check if all tables exist **/
$db = new \Core\Database;
$tables = get_value()['tables'];

if(!$db->table_exists($tables)){
	dd("Missing database tables in ".plugin_id() ." plugin: ". implode(",", $db->missing_tables));
	die;
}

if(!function_exists('sims_runtime_marker_path')){
	function sims_runtime_marker_path(string $name): string
	{
		$dir = __DIR__ . DIRECTORY_SEPARATOR . '.runtime';
		if(!is_dir($dir)){
			@mkdir($dir, 0777, true);
		}
		return $dir . DIRECTORY_SEPARATOR . $name;
	}
}

if(!function_exists('sims_ensure_schema')){
	function sims_ensure_schema(): void
	{
		static $ensured = false;
		if($ensured){
			return;
		}
		$ensured = true;
		$marker = sims_runtime_marker_path('sims_schema_20260427.done');
		if(file_exists($marker)){
			// Schema was checked previously; still verify required columns in case the table was altered or the marker was created prematurely.
		}
		try{
			$db = new \Core\Database;
			$schema = $db->query("SHOW COLUMNS FROM sims");
			if(!$schema || !is_array($schema)){
				return;
			}
			$existing = [];
			foreach($schema as $column){
				$existing[strtolower($column->Field ?? '')] = true;
			}
			$alters = [];
			if(empty($existing['rejection_reason'])){
				$alters[] = "ADD COLUMN rejection_reason TEXT NULL AFTER transaction_status";
			}
			if(empty($existing['image'])){
				$alters[] = "ADD COLUMN image VARCHAR(1024) NULL AFTER buyer_phone";
			}
			if(empty($existing['denos'])){
				$alters[] = "ADD COLUMN denos LONGTEXT NULL AFTER commission";
			}
			if(!empty($alters)){
				$db->query("ALTER TABLE sims " . implode(',', $alters));
			}
			@file_put_contents($marker, '1');
		}catch(\Throwable $e){
			return;
		}
	}
}

sims_ensure_schema();

if(!function_exists('operations_account_ensure_cashdrawer_id_schema')){
	function operations_account_ensure_cashdrawer_id_schema(\Core\Database $db): void
	{
		static $ensured = false;
		if($ensured){
			return;
		}

		$columns = $db->query('SHOW COLUMNS FROM cashdrawers') ?: [];
		if($db->has_error){
			throw new \RuntimeException('Unable to inspect the cashdrawers ID schema: ' . $db->error);
		}

		$idColumn = null;
		foreach($columns as $column){
			if(strtolower((string)($column->Field ?? '')) === 'id'){
				$idColumn = $column;
				break;
			}
		}
		if($idColumn === null){
			throw new \RuntimeException('Cashdrawer schema is missing its ID column. Ask an administrator to repair the cashdrawers table.');
		}

		$idType = trim((string)($idColumn->Type ?? ''));
		if(!preg_match('/^(tinyint|smallint|mediumint|int|integer|bigint)(?:\(\d+\))?(?:\s+unsigned)?$/i', $idType)){
			throw new \RuntimeException('Cashdrawer ID type cannot be safely repaired automatically: ' . $idType);
		}

		$indexes = $db->query('SHOW INDEX FROM cashdrawers') ?: [];
		if($db->has_error){
			throw new \RuntimeException('Unable to inspect cashdrawers indexes: ' . $db->error);
		}
		$primaryColumns = [];
		foreach($indexes as $index){
			if(strtoupper((string)($index->Key_name ?? '')) === 'PRIMARY'){
				$primaryColumns[(int)($index->Seq_in_index ?? 0)] = strtolower((string)($index->Column_name ?? ''));
			}
		}
		ksort($primaryColumns);
		$primaryColumns = array_values($primaryColumns);
		if(!empty($primaryColumns) && $primaryColumns !== ['id']){
			throw new \RuntimeException('Cashdrawers has a different primary key; the ID schema requires administrator review.');
		}

		$hasAutoIncrement = str_contains(strtolower((string)($idColumn->Extra ?? '')), 'auto_increment');
		$needsPrimaryKey = empty($primaryColumns);
		if(!$hasAutoIncrement || $needsPrimaryKey){
			if($needsPrimaryKey){
				$duplicates = $db->query(
					'SELECT id FROM cashdrawers GROUP BY id HAVING COUNT(*) > 1 LIMIT 1'
				) ?: [];
				if($db->has_error){
					throw new \RuntimeException('Unable to check cashdrawers for duplicate IDs: ' . $db->error);
				}
				if(!empty($duplicates)){
					throw new \RuntimeException('Cashdrawers contains duplicate IDs. Resolve them before enabling the primary key.');
				}

				$nullIds = $db->query('SELECT id FROM cashdrawers WHERE id IS NULL LIMIT 1') ?: [];
				if($db->has_error){
					throw new \RuntimeException('Unable to check cashdrawers for empty IDs: ' . $db->error);
				}
				if(!empty($nullIds)){
					throw new \RuntimeException('Cashdrawers contains empty IDs. Resolve them before enabling the primary key.');
				}
			}

			$alterParts = [];
			if(!$hasAutoIncrement){
				$alterParts[] = 'MODIFY id ' . $idType . ' NOT NULL AUTO_INCREMENT';
			}
			if($needsPrimaryKey){
				$alterParts[] = 'ADD PRIMARY KEY (id)';
			}
			$db->query('ALTER TABLE cashdrawers ' . implode(', ', $alterParts));
			if($db->has_error){
				throw new \RuntimeException('Could not repair the cashdrawers ID schema: ' . $db->error);
			}
		}

		$verifiedColumns = $db->query('SHOW COLUMNS FROM cashdrawers') ?: [];
		$verifiedIdColumn = null;
		foreach($verifiedColumns as $column){
			if(strtolower((string)($column->Field ?? '')) === 'id'){
				$verifiedIdColumn = $column;
				break;
			}
		}
		$verifiedIndexes = $db->query("SHOW INDEX FROM cashdrawers WHERE Key_name = 'PRIMARY'") ?: [];
		if(
			$db->has_error
			|| $verifiedIdColumn === null
			|| !str_contains(strtolower((string)($verifiedIdColumn->Extra ?? '')), 'auto_increment')
			|| strtolower((string)($verifiedIndexes[0]->Column_name ?? '')) !== 'id'
		){
			throw new \RuntimeException('Cashdrawers ID schema is still not configured for database-generated IDs.');
		}

		$ensured = true;
	}
}

if(!function_exists('operations_account_has_cashdrawer_today')){
	function operations_account_has_cashdrawer_today(int $userId, ?string $today = null): bool
	{
		if ($userId <= 0) {
			return false;
		}

		$today = trim((string)($today ?? ''));
		if ($today === '') {
			$tz = new \DateTimeZone('Africa/Accra');
			$today = (new \DateTime('now', $tz))->format('Y-m-d');
		}

		try {
			$db = new \Core\Database;
			if (!$db->table_exists(['cashdrawers'])) {
				return false;
			}

			$tz = new \DateTimeZone('Africa/Accra');
			$dayStartDt = new \DateTime($today . ' 00:00:00', $tz);
			$dayEndDt = (clone $dayStartDt)->modify('+1 day');
			$dayStart = $dayStartDt->format('Y-m-d H:i:s');
			$dayEnd = $dayEndDt->format('Y-m-d H:i:s');

			$rows = $db->query(
				"SELECT id
				 FROM cashdrawers
				 WHERE user_id = :user_id
				 AND date_created >= :day_start
				 AND date_created < :day_end
				 ORDER BY id DESC
				 LIMIT 1",
				['user_id' => $userId, 'day_start' => $dayStart, 'day_end' => $dayEnd]
			) ?: [];

			return !empty($rows);
		} catch (\Throwable $e) {
			return false;
		}
	}
}

if(!function_exists('operations_account_has_opened_or_owned_cashdrawer_today')){
	function operations_account_has_opened_or_owned_cashdrawer_today(int $userId, ?string $today = null): bool
	{
		if ($userId <= 0) {
			return false;
		}

		$today = trim((string)($today ?? ''));
		if ($today === '') {
			$tz = new \DateTimeZone('Africa/Accra');
			$today = (new \DateTime('now', $tz))->format('Y-m-d');
		}

		try {
			$db = new \Core\Database;
			if (!$db->table_exists(['cashdrawers'])) {
				return false;
			}

			$tz = new \DateTimeZone('Africa/Accra');
			$dayStartDt = new \DateTime($today . ' 00:00:00', $tz);
			$dayEndDt = (clone $dayStartDt)->modify('+1 day');
			$dayStart = $dayStartDt->format('Y-m-d H:i:s');
			$dayEnd = $dayEndDt->format('Y-m-d H:i:s');

			$rows = $db->query(
				"SELECT id
				 FROM cashdrawers
				 WHERE (user_id = :user_id OR created_by = :user_id)
				 AND date_created >= :day_start
				 AND date_created < :day_end
				 ORDER BY id DESC
				 LIMIT 1",
				['user_id' => $userId, 'day_start' => $dayStart, 'day_end' => $dayEnd]
			) ?: [];

			return !empty($rows);
		} catch (\Throwable $e) {
			return false;
		}
	}
}

if(!function_exists('operations_account_cashdrawer_vault_limits')){
	function operations_account_cashdrawer_vault_limits(int $userId): array
	{
		$result = [
			'branch_assigned' => false,
			'branch_id' => 0,
			'vault_count' => 0,
			'available' => ['GHS' => [], 'CFA' => []],
			'lookup_error' => '',
		];
		if($userId <= 0){
			return $result;
		}

		$db = new \Core\Database;
		$userRows = $db->query('SELECT branch_id FROM users WHERE id = :user_id LIMIT 1', ['user_id' => $userId]);
		if($db->has_error){
			throw new \RuntimeException('Unable to check the staff member’s branch assignment: ' . $db->error);
		}
		$result['branch_id'] = (int)($userRows[0]->branch_id ?? 0);
		$result['branch_assigned'] = $result['branch_id'] > 0;
		if(!$result['branch_assigned']){
			return $result;
		}

		if(!$db->table_exists(['vaults', 'vault_stock'])){
			throw new \RuntimeException('Vault storage is not available to validate this branch cashdrawer.');
		}

		$vaultRows = $db->query(
			'SELECT v.id FROM vaults v WHERE v.branch_id = :branch_id AND v.active = 1',
			['branch_id' => $result['branch_id']]
		);
		if($db->has_error){
			throw new \RuntimeException('Unable to check active vaults for this branch: ' . $db->error);
		}
		$result['vault_count'] = count($vaultRows ?: []);
		if($result['vault_count'] === 0){
			return $result;
		}

		$stockRows = $db->query(
			'SELECT s.currency, s.denomination, SUM(s.note_count) AS available_count
			 FROM vault_stock s
			 INNER JOIN vaults v ON v.id = s.vault_id
			 WHERE v.branch_id = :branch_id AND v.active = 1
			 GROUP BY s.currency, s.denomination',
			['branch_id' => $result['branch_id']]
		);
		if($db->has_error){
			throw new \RuntimeException('Unable to check branch vault denominations: ' . $db->error);
		}
		foreach($stockRows ?: [] as $line){
			$currency = strtoupper((string)$line->currency);
			if(!isset($result['available'][$currency])){
				continue;
			}
			$key = number_format((float)$line->denomination, 2, '.', '');
			$result['available'][$currency][$key] = max(0, (int)$line->available_count);
		}
		return $result;
	}
}

if(!function_exists('operations_account_previous_cashdrawer_snapshot')){
	function operations_account_previous_cashdrawer_snapshot(int $userId): ?array
	{
		if ($userId <= 0) {
			return null;
		}

		$timezone = new \DateTimeZone('Africa/Accra');
		$dayStart = (new \DateTimeImmutable('today', $timezone))->format('Y-m-d H:i:s');
		$db = new \Core\Database;
		$previousRows = $db->query(
			'SELECT id, amount, date_created
			 FROM cashdrawers
			 WHERE user_id = :user_id AND date_created < :day_start
			 ORDER BY date_created DESC, id DESC
			 LIMIT 1',
			['user_id' => $userId, 'day_start' => $dayStart]
		) ?: [];
		if ($db->has_error) {
			throw new \RuntimeException('Unable to load the previous cashdrawer: ' . $db->error);
		}
		if (!$previousRows) {
			return null;
		}

		$drawer = $previousRows[0];
		$drawerId = (int)$drawer->id;
		$snapshot = [
			'id' => $drawerId,
			'date_created' => (string)$drawer->date_created,
			'denominations' => [],
			'ecash' => [],
			'ghs_total' => 0.0,
			'cfa_total' => 0.0,
		];

		if ($db->table_exists(['drawer_denos'])) {
			$rows = $db->query(
				'SELECT denomination, SUM(count) AS note_count
				 FROM drawer_denos
				 WHERE drawer_id = :drawer_id AND user_id = :user_id
				 GROUP BY denomination
				 HAVING SUM(count) > 0
				 ORDER BY denomination DESC',
				['drawer_id' => $drawerId, 'user_id' => $userId]
			) ?: [];
			if ($db->has_error) {
				throw new \RuntimeException('Unable to load previous GHS denomination balances: ' . $db->error);
			}
			foreach ($rows as $row) {
				$denomination = (float)$row->denomination;
				$count = (int)$row->note_count;
				$volume = $denomination * $count;
				$snapshot['denominations'][] = [
					'currency' => 'GHS',
					'denomination' => $denomination,
					'count' => $count,
					'volume' => $volume,
				];
				$snapshot['ghs_total'] += $volume;
			}
		}

		if ($db->table_exists(['drawer_fx_denos'])) {
			$rows = $db->query(
				"SELECT denomination, SUM(count) AS note_count
				 FROM drawer_fx_denos
				 WHERE drawer_id = :drawer_id AND user_id = :user_id AND UPPER(currency) = 'CFA'
				 GROUP BY denomination
				 HAVING SUM(count) > 0
				 ORDER BY denomination DESC",
				['drawer_id' => $drawerId, 'user_id' => $userId]
			) ?: [];
			if ($db->has_error) {
				throw new \RuntimeException('Unable to load previous CFA denomination balances: ' . $db->error);
			}
			foreach ($rows as $row) {
				$denomination = (float)$row->denomination;
				$count = (int)$row->note_count;
				$volume = $denomination * $count;
				$snapshot['denominations'][] = [
					'currency' => 'CFA',
					'denomination' => $denomination,
					'count' => $count,
					'volume' => $volume,
				];
				$snapshot['cfa_total'] += $volume;
			}
		}

		if ($db->table_exists(['drawer_balances'])) {
			$rows = $db->query(
				"SELECT db_current.channel_key, db_current.opening_balance, db_current.credit, db_current.debit,
				        db_current.closing_balance, db_current.proof_image
				 FROM drawer_balances db_current
				 INNER JOIN (
				     SELECT channel_key, MAX(id) AS id
				     FROM drawer_balances
				     WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash'
				     GROUP BY channel_key
				 ) latest ON latest.id = db_current.id
				 ORDER BY db_current.channel_key",
				['drawer_id' => $drawerId, 'user_id' => $userId]
			) ?: [];
			if ($db->has_error) {
				throw new \RuntimeException('Unable to load previous e-cash balances: ' . $db->error);
			}
			foreach ($rows as $row) {
				$key = strtoupper(trim((string)$row->channel_key));
				if ($key === '') {
					continue;
				}
				$label = preg_replace('/^ECASH_/', '', $key);
				$label = ucwords(strtolower(str_replace('_', ' ', (string)$label)));
				$opening = (float)($row->opening_balance ?? 0);
				$credit = (float)($row->credit ?? 0);
				$debit = (float)($row->debit ?? 0);
				$closing = $row->closing_balance !== null
					? (float)$row->closing_balance
					: $opening + $credit - $debit;
				if ($closing <= 0) {
					continue;
				}
				$snapshot['ecash'][$key] = [
					'name' => $label,
					'balance' => $closing,
					'proof_image' => (string)($row->proof_image ?? ''),
				];
			}
		}

		return $snapshot;
	}
}

if(!function_exists('operations_account_enrich_drawer_entry')){
	function operations_account_enrich_drawer_entry(object $entry): object
	{
		$transId = (int)($entry->trans_id ?? 0);
		if($transId <= 0){
			return $entry;
		}
		static $metaDb = null;
		if($metaDb === null){
			$metaDb = new \Core\Database;
		}
		try{
			$metaRows = $metaDb->query(
				"SELECT a.date_created, u.first_name, u.last_name FROM agencybankings AS a LEFT JOIN users AS u ON a.created_by = u.id WHERE a.id = :id LIMIT 1",
				['id' => $transId]
			);
			if(!empty($metaRows)){
				$meta = $metaRows[0];
				$entry->meta_date_created = $meta->date_created ?? null;
				$entry->meta_created_by = trim(($meta->first_name ?? '').' '.($meta->last_name ?? '')) ?: null;
			}
		}catch(\Throwable $th){
			// ignore metadata failures
		}
		return $entry;
	}
}

if(!function_exists('operations_account_denomination_catalog')){
	function operations_account_denomination_catalog(string $currency = 'GHS'): array
	{
		$currency = strtoupper(trim($currency));
		if ($currency === 'CFA') {
			return [10000, 5000, 2000, 1000, 500, 200, 100, 50, 20, 10];
		}

		return [200, 100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1];
	}
}

if(!function_exists('operations_account_normalize_denomination_rows')){
	/** @param mixed $rows */
	function operations_account_normalize_denomination_rows($rows, string $currency = 'GHS'): array
	{
		if (!is_array($rows)) {
			$rows = [];
		}
		$currency = strtoupper(trim($currency));
		$catalog = operations_account_denomination_catalog($currency);
		$bucket = [];

		foreach ($rows as $row) {
			if (is_array($row)) {
				$row = (object)$row;
			}
			if (!is_object($row)) {
				continue;
			}

			$denomination = (float)($row->denomination ?? 0);
			if (!is_finite($denomination) || $denomination <= 0) {
				continue;
			}

			$count = (float)($row->count ?? $row->total_count ?? 0);
			if (!is_finite($count)) {
				$count = 0.0;
			}

			$key = number_format($denomination, 2, '.', '');
			if (!isset($bucket[$key])) {
				$bucket[$key] = [
					'denomination' => $denomination,
					'count' => 0.0,
				];
			}
			$bucket[$key]['count'] += $count;
		}

		$ordered = [];
		foreach ($catalog as $denomination) {
			$key = number_format((float)$denomination, 2, '.', '');
			$ordered[$key] = (float)$denomination;
		}
		foreach ($bucket as $key => $info) {
			if (!isset($ordered[$key])) {
				$ordered[$key] = (float)($info['denomination'] ?? 0);
			}
		}
		arsort($ordered, SORT_NUMERIC);

		$out = [];
		foreach ($ordered as $key => $denomination) {
			$count = (int)round($bucket[$key]['count'] ?? 0);
			if ($count < 0) {
				$count = 0;
			}
			$out[] = (object) [
				'currency' => $currency,
				'denomination' => (float)$denomination,
				'count' => $count,
				'volume' => (float)$count * (float)$denomination,
			];
		}

		return $out;
	}
}

if(!function_exists('operations_account_cashdrawer_edit_history')){
	function operations_account_cashdrawer_edit_history(int $drawerId, int $limit = 12): array
	{
		if ($drawerId <= 0) {
			return [];
		}
		$limit = max(1, min(50, $limit));
		try {
			$db = new \Core\Database;
			if (!$db->table_exists(['logs'])) {
				return [];
			}
			return $db->query(
				"SELECT user_name, description, date_created
				 FROM logs
				 WHERE table_name = 'cashdrawers'
				   AND action = 'edited'
				   AND description LIKE :needle
				 ORDER BY date_created DESC, id DESC
				 LIMIT {$limit}",
				['needle' => '%[cashdrawer_id:' . $drawerId . ']%']
			) ?: [];
		} catch (\Throwable $e) {
			return [];
		}
	}
}

if(!function_exists('operations_account_cashdrawer_amount_label')){
	function operations_account_cashdrawer_amount_label(float $amount, string $currency = 'GHS'): string
	{
		$currency = strtoupper(trim($currency));
		return $currency . ' ' . number_format($amount, 2);
	}
}

if(!function_exists('operations_account_cashdrawer_denomination_label')){
	function operations_account_cashdrawer_denomination_label(string $currency, float $denomination): string
	{
		$currency = strtoupper(trim($currency));
		$formatted = $currency === 'CFA'
			? number_format($denomination, 0)
			: (($denomination >= 1) ? number_format($denomination, 0) : number_format($denomination, 2));
		return $currency . ' ' . $formatted;
	}
}

if(!function_exists('operations_account_parse_cashdrawer_edit_description')){
	function operations_account_parse_cashdrawer_edit_description(?string $description): array
	{
		$description = trim((string)$description);
		$clean = preg_replace('/\s*\[cashdrawer_id:\d+\]\s*/i', '', $description);
		$out = [
			'raw' => trim((string)$clean),
			'actor' => '',
			'drawer_id' => null,
			'summary' => [],
			'denomination_changes' => [],
		];

		if (preg_match('/^(.*?)\s+edited cashdrawer\s+#(\d+)/i', $clean, $m)) {
			$out['actor'] = trim((string)($m[1] ?? ''));
			$out['drawer_id'] = (int)($m[2] ?? 0);
		}

		$parseMoneyPair = static function(string $pattern, string $label, string $currency, string $text) use (&$out): void {
			if (preg_match($pattern, $text, $m)) {
				$old = (float)str_replace(',', '', (string)($m[1] ?? '0'));
				$new = (float)str_replace(',', '', (string)($m[2] ?? '0'));
				$out['summary'][] = [
					'label' => $label,
					'old' => operations_account_cashdrawer_amount_label($old, $currency),
					'new' => operations_account_cashdrawer_amount_label($new, $currency),
				];
			}
		};

		$parseMoneyPair('/Opening GHS:\s*([0-9,]+\.\d+)\s*->\s*([0-9,]+\.\d+)/i', 'Opening cash', 'GHS', $clean);
		$parseMoneyPair('/GHS total:\s*([0-9,]+\.\d+)\s*->\s*([0-9,]+\.\d+)/i', 'Cash total', 'GHS', $clean);
		$parseMoneyPair('/CFA total:\s*([0-9,]+\.\d+)\s*->\s*([0-9,]+\.\d+)/i', 'CFA total', 'CFA', $clean);

		if (preg_match('/E-cash total:\s*([0-9,]+\.\d+)\s*\(([\d,]+)\s+channels\)\s*->\s*([0-9,]+\.\d+)\s*\(([\d,]+)\s+channels\)/i', $clean, $m)) {
			$oldAmount = (float)str_replace(',', '', (string)($m[1] ?? '0'));
			$oldChannels = (int)str_replace(',', '', (string)($m[2] ?? '0'));
			$newAmount = (float)str_replace(',', '', (string)($m[3] ?? '0'));
			$newChannels = (int)str_replace(',', '', (string)($m[4] ?? '0'));
			$out['summary'][] = [
				'label' => 'E-cash total',
				'old' => operations_account_cashdrawer_amount_label($oldAmount, 'GHS') . ' across ' . number_format($oldChannels) . ' channels',
				'new' => operations_account_cashdrawer_amount_label($newAmount, 'GHS') . ' across ' . number_format($newChannels) . ' channels',
			];
		}

		$dedupedSummary = [];
		foreach ($out['summary'] as $summaryRow) {
			$key = strtolower(trim((string)($summaryRow['label'] ?? ''))) . '|' . trim((string)($summaryRow['old'] ?? '')) . '|' . trim((string)($summaryRow['new'] ?? ''));
			$dedupedSummary[$key] = $summaryRow;
		}
		$out['summary'] = array_values($dedupedSummary);

		if (count($out['summary']) >= 2) {
			$openingIdx = null;
			$cashTotalIdx = null;
			foreach ($out['summary'] as $idx => $summaryRow) {
				if (($summaryRow['label'] ?? '') === 'Opening cash') {
					$openingIdx = $idx;
				}
				if (($summaryRow['label'] ?? '') === 'Cash total') {
					$cashTotalIdx = $idx;
				}
			}
			if ($openingIdx !== null && $cashTotalIdx !== null) {
				$openingRow = $out['summary'][$openingIdx];
				$cashTotalRow = $out['summary'][$cashTotalIdx];
				if (($openingRow['old'] ?? '') === ($cashTotalRow['old'] ?? '') && ($openingRow['new'] ?? '') === ($cashTotalRow['new'] ?? '')) {
					unset($out['summary'][$cashTotalIdx]);
					$out['summary'] = array_values($out['summary']);
				}
			}
		}

		if (preg_match_all('/\b(GHS|CFA)\s+([0-9]+(?:\.[0-9]+)?):\s*([\d,]+)(?:[^-]*?)\s*->\s*([\d,]+)/i', $clean, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				$currency = strtoupper((string)($match[1] ?? 'GHS'));
				$denomination = (float)($match[2] ?? 0);
				$oldCount = (int)str_replace(',', '', (string)($match[3] ?? '0'));
				$newCount = (int)str_replace(',', '', (string)($match[4] ?? '0'));
				$out['denomination_changes'][] = [
					'currency' => $currency,
					'label' => operations_account_cashdrawer_denomination_label($currency, $denomination),
					'denomination' => $denomination,
					'old_count' => $oldCount,
					'new_count' => $newCount,
					'old_volume' => $denomination * $oldCount,
					'new_volume' => $denomination * $newCount,
				];
			}
		}

		return $out;
	}
}

if(!function_exists('operations_account_render_cashdrawer_edit_history_entry')){
	/** @param mixed $editLog */
	function operations_account_render_cashdrawer_edit_history_entry($editLog): string
	{
		if (is_array($editLog)) {
			$editLog = (object)$editLog;
		}
		if (!is_object($editLog)) {
			return '';
		}

		$parsed = operations_account_parse_cashdrawer_edit_description((string)($editLog->description ?? ''));
		$dateCreated = esc((string)($editLog->date_created ?? ''));
		$actor = trim((string)($parsed['actor'] ?? ''));
		$summaryRows = is_array($parsed['summary'] ?? null) ? $parsed['summary'] : [];
		$denominationChanges = is_array($parsed['denomination_changes'] ?? null) ? $parsed['denomination_changes'] : [];

		$html = "<div class='transaction-item mb-2' style='padding:10px; border:1px solid var(--bs-border-color); border-radius:8px;'>";
		$html .= "<div style='display:flex; justify-content:space-between; align-items:center; gap:10px;'>";
		$html .= "<span class='transaction-status status-exchange'>Edited</span>";
		$html .= "<span style='font-size:12px; color:var(--text-secondary);'>" . $dateCreated . "</span>";
		$html .= "</div>";

		if ($actor !== '') {
			$html .= "<div style='margin-top:6px; font-size:13px; font-weight:600;'>" . esc($actor) . " updated this drawer</div>";
		}

		if (empty($summaryRows) && empty($denominationChanges)) {
			$html .= "<div style='margin-top:6px; font-size:13px;'>" . esc((string)($parsed['raw'] ?? '')) . "</div>";
			$html .= "</div>";
			return $html;
		}

		if (!empty($summaryRows)) {
			$html .= "<div style='margin-top:8px; display:grid; gap:4px;'>";
			foreach ($summaryRows as $summaryRow) {
				$label = esc((string)($summaryRow['label'] ?? 'Change'));
				$old = esc((string)($summaryRow['old'] ?? ''));
				$new = esc((string)($summaryRow['new'] ?? ''));
				$html .= "<div style='font-size:13px;'><strong>{$label}:</strong> {$old} -> {$new}</div>";
			}
			$html .= "</div>";
		}

		if (!empty($denominationChanges)) {
			$changesByCurrency = [];
			foreach ($denominationChanges as $changeRow) {
				$currencyKey = strtoupper((string)($changeRow['currency'] ?? 'GHS'));
				if (!isset($changesByCurrency[$currencyKey])) {
					$changesByCurrency[$currencyKey] = [];
				}
				$changesByCurrency[$currencyKey][] = $changeRow;
			}
			foreach ($changesByCurrency as $currencyKey => $currencyRows) {
				$title = $currencyKey === 'CFA' ? 'CFA denomination changes' : 'Cash denomination changes';
				$html .= "<div style='margin-top:10px;'>";
				$html .= "<div style='font-size:12px; font-weight:600; margin-bottom:6px;'>" . esc($title) . "</div>";
				$html .= "<div style='overflow:auto;'>";
				$html .= "<table class='table table-sm mb-0'><thead><tr><th>Denomination</th><th>Prev Count</th><th>Prev Volume</th><th>New Count</th><th>New Volume</th></tr></thead><tbody>";
				foreach ($currencyRows as $changeRow) {
					$label = esc((string)($changeRow['label'] ?? ''));
					$oldCount = number_format((int)($changeRow['old_count'] ?? 0));
					$newCount = number_format((int)($changeRow['new_count'] ?? 0));
					$oldVolume = esc(operations_account_cashdrawer_amount_label((float)($changeRow['old_volume'] ?? 0), $currencyKey));
					$newVolume = esc(operations_account_cashdrawer_amount_label((float)($changeRow['new_volume'] ?? 0), $currencyKey));
					$html .= "<tr><td>{$label}</td><td>{$oldCount}</td><td>{$oldVolume}</td><td>{$newCount}</td><td>{$newVolume}</td></tr>";
				}
				$html .= "</tbody></table>";
				$html .= "</div>";
				$html .= "</div>";
			}
		}

		$html .= "</div>";
		return $html;
	}
}

if(!function_exists('operations_account_is_opening_snapshot_status')){
	function operations_account_is_opening_snapshot_status(?string $status): bool
	{
		$status = strtolower(trim((string)$status));
		return in_array($status, ['opening snapshot', 'opening cfa snapshot'], true);
	}
}

if(!function_exists('operations_account_is_bank_channel_key')){
	function operations_account_is_bank_channel_key(?string $key): bool
	{
		$key = strtoupper(trim((string)$key));
		if($key === ''){
			return false;
		}
		$normalized = preg_replace('/[^A-Z0-9_]+/', '_', $key);
		$normalized = trim((string)$normalized, '_');
		$compact = str_replace(['ECASH_', '_'], '', $normalized);
		$aliases = [
			'ADB', 'ADBBANK',
			'ABSA', 'ABSABANK', 'ABSAFORMERLYBARCLAYS', 'ABSAFORMERLYBARCLAYSBANK', 'BARCLAYS', 'BARCLAYSBANK',
			'FIDELITY', 'FIDELITYBANK',
			'APEXNAFANA', 'APEXNAFANABANK', 'APEXSUMA', 'APEXSUMABANK',
			'ACCESS', 'ACCESSBANK', 'CAL', 'CALBANK', 'ECOBANK',
			'GCB', 'GCBBANK', 'GT', 'GTBANK', 'STANBIC', 'STANBICBANK',
			'FIRSTNATIONALBANK', 'FIRSTNATIONALBANKFNB', 'FNB', 'FNBBANK',
			'FIRSTBANKGHANA', 'FIRSTBANKGHANAFBG', 'FBG', 'FBGBANK',
		];
		return in_array($compact, $aliases, true);
	}
}

if(!function_exists('operations_account_is_momo_pay_channel_key')){
	function operations_account_is_momo_pay_channel_key(?string $key): bool
	{
		$key = strtoupper(trim((string)$key));
		$key = preg_replace('/^ECASH[_-]*/', '', $key);
		$compact = preg_replace('/[^A-Z0-9]/', '', (string)$key);
		return in_array($compact, ['MTNMOMOPAY', 'MTNMOMOPAYOUT'], true);
	}
}

if(!function_exists('operations_account_is_shared_channel_key')){
	function operations_account_is_shared_channel_key(?string $key): bool
	{
		return operations_account_is_bank_channel_key($key) || operations_account_is_momo_pay_channel_key($key);
	}
}

if(!function_exists('operations_account_is_evd_channel_key')){
	function operations_account_is_evd_channel_key(?string $key): bool
	{
		$key = strtoupper(trim((string)$key));
		if($key === ''){
			return false;
		}
		return strpos($key, 'EVD') !== false;
	}
}

if(!function_exists('operations_account_get_cashdrawer_row')){
	function operations_account_get_cashdrawer_row(int $drawerId): ?object
	{
		if($drawerId <= 0){
			return null;
		}

		try{
			$db = new \Core\Database;
			$rows = $db->query(
				"SELECT c.*, u.first_name AS user_first_name, u.last_name AS user_last_name, u.image AS user_image
				 FROM cashdrawers c
				 LEFT JOIN users u ON u.id = c.user_id
				 WHERE c.id = :id
				 LIMIT 1",
				['id' => $drawerId]
			) ?: [];
			return !empty($rows) ? $rows[0] : null;
		}catch(\Throwable $e){
			return null;
		}
	}
}

if(!function_exists('operations_account_db_table_list')){
	function operations_account_db_table_list(\Core\Database $db): array
	{
		static $cache = null;
		if(is_array($cache)){
			return $cache;
		}

		$cache = [];
		try{
			$rows = $db->query("SHOW TABLES") ?: [];
			foreach($rows as $row){
				if(is_array($row)){
					$row = (object)$row;
				}
				if(!is_object($row)){
					continue;
				}
				foreach(get_object_vars($row) as $value){
					$table = trim((string)$value);
					if($table !== ''){
						$cache[] = $table;
					}
				}
			}
		}catch(\Throwable $e){
			$cache = [];
		}

		return $cache;
	}
}

if(!function_exists('operations_account_db_table_columns')){
	function operations_account_db_table_columns(\Core\Database $db, string $table): array
	{
		static $cache = [];
		$key = strtolower(trim($table));
		if(isset($cache[$key])){
			return $cache[$key];
		}

		$cache[$key] = [];
		$tableSql = str_replace('`', '``', $table);
		try{
			$rows = $db->query("SHOW COLUMNS FROM `{$tableSql}`") ?: [];
			foreach($rows as $row){
				$field = strtolower(trim((string)($row->Field ?? '')));
				if($field !== ''){
					$cache[$key][] = $field;
				}
			}
		}catch(\Throwable $e){
			$cache[$key] = [];
		}

		return $cache[$key];
	}
}

if(!function_exists('operations_account_pretty_table_label')){
	function operations_account_pretty_table_label(string $table): string
	{
		$map = [
			'momoremits' => 'MoMo Remit Transaction',
			'cfas' => 'CFA Transaction',
			'ezwichs' => 'Ezwich Transaction',
			'payments_backoffice' => 'Backoffice Payment',
			'requests' => 'Backoffice Request',
			'commissions' => 'Commission',
			'bank_transfers_backoffice' => 'Bank Transfer',
			'reconciliations' => 'Reconciliation',
			'reconciliations_summary' => 'Reconciliation Summary',
			'agencybankings' => 'Agency Banking Transaction',
			'airtimebundles' => 'Airtime/Bundle Transaction',
			'payment_services' => 'Payment Service Transaction',
			'agent_services' => 'Agent Service Transaction',
			'cashpickups' => 'Cash Pickup',
			'petty_cash_transactions' => 'Petty Cash Transaction',
		];

		$key = strtolower(trim($table));
		if(isset($map[$key])){
			return $map[$key];
		}

		return ucwords(str_replace('_', ' ', $table));
	}
}

if(!function_exists('operations_account_find_transaction_detail')){
	function operations_account_find_transaction_detail(\Core\Database $db, int $transId, string $sourceStatus = ''): ?array
	{
		if($transId <= 0){
			return null;
		}

		if(stripos(trim($sourceStatus), 'vault:') === 0 && $db->table_exists(['vault_transactions'])){
			$rows = $db->query(
				"SELECT t.id, t.transaction_type, t.status, t.currency, t.amount, t.date_created, t.notes,
				        t.cashdrawer_id, t.drawer_user_id,
				        t.reject_reason, sv.name AS source_vault_name, dv.name AS destination_vault_name,
				        TRIM(CONCAT(COALESCE(requester.first_name, ''), ' ', COALESCE(requester.last_name, ''))) AS requested_by_name,
				        TRIM(CONCAT(COALESCE(drawer_user.first_name, ''), ' ', COALESCE(drawer_user.last_name, ''))) AS drawer_user_name,
				        TRIM(CONCAT(COALESCE(first_party.first_name, ''), ' ', COALESCE(first_party.last_name, ''))) AS first_party_name,
				        TRIM(CONCAT(COALESCE(approver.first_name, ''), ' ', COALESCE(approver.last_name, ''))) AS approver_name,
				        TRIM(CONCAT(COALESCE(reviewer.first_name, ''), ' ', COALESCE(reviewer.last_name, ''))) AS reviewer_name
				 FROM vault_transactions t
				 LEFT JOIN vaults sv ON sv.id = t.source_vault_id
				 LEFT JOIN vaults dv ON dv.id = t.destination_vault_id
				 LEFT JOIN users requester ON requester.id = t.requested_by
				 LEFT JOIN users drawer_user ON drawer_user.id = t.drawer_user_id
				 LEFT JOIN users first_party ON first_party.id = t.assigned_first_party_id
				 LEFT JOIN users approver ON approver.id = t.assigned_approver_id
				 LEFT JOIN users reviewer ON reviewer.id = t.reviewed_by
				 WHERE t.id = :id LIMIT 1",
				['id' => $transId]
			) ?: [];
			if(!empty($rows)){
				$row = $rows[0];
				$currency = strtoupper((string)($row->currency ?? ''));
				return [
					'table' => 'vault_transactions',
					'label' => 'Vault Transaction',
					'id' => (int)($row->id ?? $transId),
					'lookup_id' => $transId,
					'status' => (string)($row->status ?? ''),
					'date_created' => (string)($row->date_created ?? ''),
					'amount' => (float)($row->amount ?? 0),
					'currency' => $currency,
					'type' => (string)($row->transaction_type ?? ''),
					'network' => '',
					'phone' => '',
					'reference' => '',
					'summary' => [
						'Movement: ' . ucwords(str_replace('_', ' ', (string)($row->transaction_type ?? 'vault transaction'))),
						'Amount: ' . $currency . ' ' . number_format((float)($row->amount ?? 0), 2),
						'Vault: ' . trim((string)($row->source_vault_name ?? '') . ' ' . (string)($row->destination_vault_name ?? '')),
						'Cashdrawer: ' . ((int)($row->cashdrawer_id ?? 0) > 0 ? '#' . (int)$row->cashdrawer_id : ''),
						'Drawer user: ' . trim((string)($row->drawer_user_name ?? '')),
						'Requested by: ' . trim((string)($row->requested_by_name ?? '')),
						'First party: ' . trim((string)($row->first_party_name ?? '')),
						'Approver: ' . trim((string)($row->approver_name ?? '')),
						'Reviewed by: ' . trim((string)($row->reviewer_name ?? '')),
						'Notes: ' . trim((string)($row->notes ?? '')),
						'Reason: ' . trim((string)($row->reject_reason ?? '')),
					],
				];
			}
		}

		$tables = [
			'momoremits',
			'agencybankings',
			'airtimebundles',
			'cfas',
			'ezwichs',
			'payment_services',
			'cashpickups',
			'agent_services',
			'cashpickup_transfers',
			'petty_cash_transactions',
			'payments_backoffice',
			'payments',
			'requests',
			'commissions',
			'bank_transfers_backoffice',
			'reversals',
		];

		foreach($tables as $table){
			try{
				if(!$db->table_exists([$table])){
					continue;
				}
				$columns = operations_account_db_table_columns($db, $table);
				if(empty($columns)){
					continue;
				}

				$lookupColumns = [];
				foreach(['id','trans_id','master_trans_id','transaction_id','reference_id','tracking_id','receive_id'] as $candidateColumn){
					if(in_array($candidateColumn, $columns, true)){
						$lookupColumns[] = $candidateColumn;
					}
				}
				if(empty($lookupColumns)){
					continue;
				}

				$whereParts = [];
				$params = [];
				foreach($lookupColumns as $lookupColumn){
					$colSql = str_replace('`', '``', $lookupColumn);
					if(in_array($lookupColumn, ['tracking_id','reference_id'], true)){
						$key = 'lookup_text_' . $lookupColumn;
						$whereParts[] = "`{$colSql}` = :{$key}";
						$params[$key] = (string)$transId;
					}else{
						$key = 'lookup_id_' . $lookupColumn;
						$whereParts[] = "`{$colSql}` = :{$key}";
						$params[$key] = $transId;
					}
				}
				if(empty($whereParts)){
					continue;
				}

				$tableSql = str_replace('`', '``', $table);
				$rows = $db->query(
					"SELECT * FROM `{$tableSql}` WHERE (" . implode(' OR ', $whereParts) . ") ORDER BY " . (in_array('id', $columns, true) ? 'id' : $lookupColumns[0]) . " DESC LIMIT 1",
					$params
				) ?: [];
				if(empty($rows)){
					continue;
				}

				$row = $rows[0];
				if(is_array($row)){
					$row = (object)$row;
				}
				if(!is_object($row)){
					continue;
				}

				$pick = function(array $keys) use ($row){
					foreach($keys as $key){
						if(isset($row->$key) && $row->$key !== '' && $row->$key !== null){
							return $row->$key;
						}
					}
					return null;
				};

				$status = $pick(['transaction_status','approval_status','status','withdrawal_status','creator_receipt_status','second_party_status','third_party_status']);
				$dateCreated = $pick(['date_created','created_at','approved_at','date_updated','updated_at']);
				$amount = $pick(['amount','amount_involved','amount_paid_out','amount_payable','amount_sendable','balance','credit','debit','closing_balance','budget_amount','spent_amount']);
				$type = $pick(['transaction_type','sending_category','category','request_type','payment_type','commission_type','source','transaction_side']);
				$network = $pick(['network','bank_name','receiving_wallet','wallet_number','channel_key']);
				$phone = $pick(['phone','phone_number','recipient_phone','sender_phone','customer_phone']);
				$reference = $pick(['reference','tracking_id','receipt_number','transaction_reference','external_reference','slug']);

				$summary = [];
				foreach(['customer_name','sender_details','receiver_details','account_name','account_number','description','remarks','notes','rejection_reason'] as $field){
					$value = trim((string)($row->$field ?? ''));
					if($value !== ''){
						$summary[] = ucwords(str_replace('_', ' ', $field)) . ': ' . $value;
					}
					if(count($summary) >= 3){
						break;
					}
				}

				return [
					'table' => $table,
					'label' => operations_account_pretty_table_label($table),
					'id' => (int)($row->id ?? $transId),
					'lookup_id' => $transId,
					'status' => $status !== null ? (string)$status : '',
					'date_created' => $dateCreated !== null ? (string)$dateCreated : '',
					'amount' => is_numeric($amount) ? (float)$amount : null,
					'type' => $type !== null ? (string)$type : '',
					'network' => $network !== null ? (string)$network : '',
					'phone' => $phone !== null ? (string)$phone : '',
					'reference' => $reference !== null ? (string)$reference : '',
					'summary' => $summary,
				];
			}catch(\Throwable $e){
				continue;
			}
		}

		return null;
	}
}

if(!function_exists('operations_account_enrich_deno_history')){
	function operations_account_enrich_deno_history(array $history): array
	{
		if(empty($history)){
			return [];
		}
		$db = new \Core\Database;
		$cache = [];
		foreach($history as &$tx){
			$transId = (int)($tx['trans_id'] ?? 0);
			if($transId <= 0){
				$tx['transaction_detail'] = null;
				continue;
			}
			$sourceStatus = trim((string)($tx['trans_status'] ?? ''));
			$cacheKey = $transId . '|' . strtolower($sourceStatus);
			if(!array_key_exists($cacheKey, $cache)){
				$cache[$cacheKey] = operations_account_find_transaction_detail($db, $transId, $sourceStatus);
			}
			$detail = $cache[$cacheKey];
			if(empty($detail)){
				$status = trim((string)($tx['trans_status'] ?? ''));
				$sourceTables = is_array($tx['source_tables'] ?? null) ? $tx['source_tables'] : [];
				$isInternalMovement = stripos($status, 'vault:') === 0
					|| ($status !== '' && in_array(strtolower($status), ['exchange','change','complete','completed','sent','approved','pending','draft','reversed'], true));
				if($isInternalMovement){
					$detail = [
						'table' => implode(',', $sourceTables),
						'label' => stripos($status, 'vault:') === 0
							? 'Vault Transaction'
							: (in_array(strtolower($status), ['exchange','change'], true)
							? 'Internal Denomination ' . ucfirst(strtolower($status))
							: 'Cashdrawer Denomination Movement'),
						'id' => $transId,
						'lookup_id' => $transId,
						'status' => stripos($status, 'vault:') === 0 ? trim(substr($status, strlen('vault:'))) : $status,
						'date_created' => (string)($tx['date_created'] ?? ''),
						'amount' => null,
						'type' => stripos($status, 'vault:') === 0 ? 'Vault transaction' : $status,
						'network' => '',
						'phone' => '',
						'reference' => '',
						'summary' => !empty($sourceTables)
							? ['Recorded in ' . implode(', ', array_map('operations_account_pretty_table_label', $sourceTables))]
							: [],
					];
				}
			}
			$tx['transaction_detail'] = $detail;
		}
		unset($tx);
		return $history;
	}
}

if(!function_exists('operations_account_collect_deno_history')){
	function operations_account_collect_deno_history(int $drawerId, int $userId, int $offset = 0, int $limit = 20): array
	{
		if($drawerId <= 0){
			return ['movements' => [], 'opening' => [], 'has_more' => false, 'total_movements' => 0];
		}
		$offset = max(0, $offset);
		$limit = max(1, min(100, $limit));
		$denoHistory = [];
		$openingHistory = [];

		try{
			$dbHist = new \Core\Database;
			$ghsRows = [];
			$cfaRows = [];
			if($dbHist->table_exists(['drawer_denos'])){
				$ghsRows = $dbHist->query(
					"SELECT 'drawer_denos' AS source_table, trans_id, date_created, 'GHS' AS currency, denomination, count, volume, trans_status
					   FROM drawer_denos
					   WHERE drawer_id = :drawer_id AND user_id = :user_id
					   ORDER BY date_created DESC, trans_id DESC, denomination DESC",
					['drawer_id' => $drawerId, 'user_id' => $userId]
				);
				if(empty($ghsRows)){
					$ghsRows = $dbHist->query(
						"SELECT 'drawer_denos' AS source_table, trans_id, date_created, 'GHS' AS currency, denomination, count, volume, trans_status
						   FROM drawer_denos
						   WHERE drawer_id = :drawer_id
						   ORDER BY date_created DESC, trans_id DESC, denomination DESC",
						['drawer_id' => $drawerId]
					) ?: [];
				}
			}
			if($dbHist->table_exists(['drawer_fx_denos'])){
				$cfaRows = $dbHist->query(
					"SELECT 'drawer_fx_denos' AS source_table, trans_id, date_created, 'CFA' AS currency, denomination, count, volume, trans_status
					   FROM drawer_fx_denos
					   WHERE drawer_id = :drawer_id AND user_id = :user_id
					   ORDER BY date_created DESC, trans_id DESC, denomination DESC",
					['drawer_id' => $drawerId, 'user_id' => $userId]
				);
				if(empty($cfaRows)){
					$cfaRows = $dbHist->query(
						"SELECT 'drawer_fx_denos' AS source_table, trans_id, date_created, 'CFA' AS currency, denomination, count, volume, trans_status
						   FROM drawer_fx_denos
						   WHERE drawer_id = :drawer_id
						   ORDER BY date_created DESC, trans_id DESC, denomination DESC",
						['drawer_id' => $drawerId]
					) ?: [];
				}
			}

			$combinedHist = array_merge($ghsRows ?? [], $cfaRows ?? []);
			$seq = 0;
			foreach($combinedHist as $hRow){
				$rawTransId = (int)($hRow->trans_id ?? 0);
				$rawStatus = trim((string)($hRow->trans_status ?? ''));
				$keyId = $rawTransId > 0
					? $rawTransId . '|' . strtolower($rawStatus)
					: 'unlinked-' . (++$seq);
				if(!isset($denoHistory[$keyId])){
					$denoHistory[$keyId] = [
						'trans_id' => $rawTransId > 0 ? $rawTransId : null,
						'date_created' => $hRow->date_created ?? null,
						'trans_status' => $rawStatus,
						'source_tables' => [],
						'lines' => [],
						'meta' => [
							'has_ghs' => false,
							'has_cfa' => false,
							'net_ghs' => 0.0,
							'net_cfa' => 0.0,
						],
					];
				}
				$sourceTable = (string)($hRow->source_table ?? '');
				if($sourceTable !== '' && !in_array($sourceTable, $denoHistory[$keyId]['source_tables'], true)){
					$denoHistory[$keyId]['source_tables'][] = $sourceTable;
				}
				$lineCurrency = (string)($hRow->currency ?? 'GHS');
				$lineVolume = (float)($hRow->volume ?? 0);
				$denoHistory[$keyId]['lines'][] = [
					'source_table' => $sourceTable,
					'currency' => $lineCurrency,
					'denomination' => (float)($hRow->denomination ?? 0),
					'count' => (int)($hRow->count ?? 0),
					'volume' => $lineVolume,
				];
				if($lineCurrency === 'CFA'){
					$denoHistory[$keyId]['meta']['has_cfa'] = true;
					$denoHistory[$keyId]['meta']['net_cfa'] += $lineVolume;
				}else{
					$denoHistory[$keyId]['meta']['has_ghs'] = true;
					$denoHistory[$keyId]['meta']['net_ghs'] += $lineVolume;
				}
			}

			if(!empty($denoHistory)){
				$denoHistory = array_values($denoHistory);
				usort($denoHistory, function($a, $b){
					return strcmp($b['date_created'] ?? '', $a['date_created'] ?? '');
				});
				$isOpeningSnapshotTx = function(array $tx) use ($drawerId): bool {
					$status = strtolower(trim((string)($tx['trans_status'] ?? '')));
					if(in_array($status, ['opening snapshot', 'opening cfa snapshot'], true)){
						return true;
					}
					$txId = (int)($tx['trans_id'] ?? 0);
					if($txId > 0 && $txId === $drawerId){
						return true;
					}
					return empty($tx['trans_id']);
				};
				foreach($denoHistory as &$tx){
					if($isOpeningSnapshotTx($tx)){
						if(!operations_account_is_opening_snapshot_status($tx['trans_status'] ?? null)){
							$tx['trans_status'] = (!empty($tx['meta']['has_cfa']) && empty($tx['meta']['has_ghs']))
								? 'Opening CFA Snapshot'
								: 'Opening Snapshot';
						}
					}
				}
				unset($tx);
				$totalMovements = count($denoHistory);
				// Calculate running balances for each transaction (from earliest to latest)
			// The array is sorted DESC (newest first), so reverse indices to process chronologically
			$reversedIndices = array_reverse(array_keys($denoHistory));
			$ghsRunningBalance = 0.0;
			$cfaRunningBalance = 0.0;
			
			foreach($reversedIndices as $idx){
				$tx = &$denoHistory[$idx];
				$txGhsBefore = $ghsRunningBalance;
				$txCfaBefore = $cfaRunningBalance;
				
				$ghsRunningBalance += (float)($tx['meta']['net_ghs'] ?? 0);
				$cfaRunningBalance += (float)($tx['meta']['net_cfa'] ?? 0);
				
				$tx['balance_before'] = [
					'ghs' => $txGhsBefore,
					'cfa' => $txCfaBefore,
				];
				$tx['balance_after'] = [
					'ghs' => $ghsRunningBalance,
					'cfa' => $cfaRunningBalance,
				];
			}
			unset($tx);
				$pageRows = array_slice($denoHistory, $offset, $limit + 1);
				$hasMore = count($pageRows) > $limit;
				if($hasMore){
					$pageRows = array_slice($pageRows, 0, $limit);
				}
				
				return [
					'movements' => operations_account_enrich_deno_history($pageRows),
					'opening' => [],
					'has_more' => $hasMore || ($offset + $limit < $totalMovements),
					'total_movements' => $totalMovements,
				];
			}
		}catch(\Throwable $e){
			return ['movements' => [], 'opening' => [], 'has_more' => false, 'total_movements' => 0];
		}

		return ['movements' => [], 'opening' => [], 'has_more' => false, 'total_movements' => 0];
	}
}

if(!function_exists('operations_account_pick_record_value')){
	function operations_account_pick_record_value(object $row, array $keys): mixed
	{
		foreach($keys as $key){
			if(isset($row->$key) && $row->$key !== '' && $row->$key !== null){
				return $row->$key;
			}
		}
		return null;
	}
}

if(!function_exists('operations_account_cashdrawer_issue_report')){
	function operations_account_cashdrawer_issue_report(int|object $drawer): array
	{
		$row = is_object($drawer) ? $drawer : operations_account_get_cashdrawer_row((int)$drawer);
		if(empty($row) || empty($row->id)){
			return [
				'issue_count' => 0,
				'issues' => [],
				'opening_deno_total' => 0.0,
			];
		}

		$drawerId = (int)($row->id ?? 0);
		$userId = (int)($row->user_id ?? 0);
		$headerAmount = (float)($row->header_amount ?? $row->amount ?? 0);
		$issues = [];
		$openingDenoTotal = 0.0;

		try{
			$db = new \Core\Database;

			if($db->table_exists(['drawer_denos'])){
				$openingRows = $db->query(
					"SELECT denomination, SUM(`count`) AS total_count, SUM(volume) AS volume
					 FROM drawer_denos
					 WHERE drawer_id = :drawer_id
					   AND user_id = :user_id
					   AND (
					   		(trans_status IS NOT NULL AND LOWER(TRIM(trans_status)) = 'opening snapshot')
					   		OR trans_id = :trans_id
					   )
					 GROUP BY denomination
					 ORDER BY denomination DESC",
					[
						'drawer_id' => $drawerId,
						'user_id' => $userId,
						'trans_id' => $drawerId,
					]
				) ?: [];

				$normalized = operations_account_normalize_denomination_rows($openingRows, 'GHS');
				foreach($normalized as $denoRow){
					$openingDenoTotal += (float)($denoRow->volume ?? 0);
				}

				if(abs($openingDenoTotal - $headerAmount) > 0.009){
					$issues[] = [
						'type' => 'denominations',
						'label' => 'Opening denominations do not match header amount',
						'details' => 'Header opening cash is GHS ' . number_format($headerAmount, 2) . ' but opening denomination total is GHS ' . number_format($openingDenoTotal, 2) . '.',
					];
				}
			}

			if($db->table_exists(['drawer_balances'])){
				$cashRows = $db->query(
					"SELECT *
					 FROM drawer_balances
					 WHERE drawer_id = :drawer_id
					   AND user_id = :user_id
					   AND channel_type = 'cash'
					 ORDER BY id DESC",
					[
						'drawer_id' => $drawerId,
						'user_id' => $userId,
					]
				) ?: [];

				$latestCashByKey = [];
				foreach($cashRows as $cashRow){
					$key = strtoupper(trim((string)($cashRow->channel_key ?? 'CASH_DRAWER')));
					if(!isset($latestCashByKey[$key])){
						$latestCashByKey[$key] = $cashRow;
					}
				}

				if(isset($latestCashByKey['CASH_DRAWER'])){
					$cashRow = $latestCashByKey['CASH_DRAWER'];
					$opening = (float)($cashRow->opening_balance ?? 0);
					$credit = (float)($cashRow->credit ?? 0);
					$debit = (float)($cashRow->debit ?? 0);
					$closing = (float)($cashRow->closing_balance ?? 0);
					$expectedClosing = $opening + $credit - $debit;

					if(abs($opening - $headerAmount) > 0.009){
						$issues[] = [
							'type' => 'cash_channel',
							'label' => 'Cash channel opening balance does not match header amount',
							'details' => 'CASH_DRAWER opens at GHS ' . number_format($opening, 2) . ' while the cashdrawer header opens at GHS ' . number_format($headerAmount, 2) . '.',
						];
					}

					if(abs($expectedClosing - $closing) > 0.009){
						$issues[] = [
							'type' => 'cash_channel',
							'label' => 'Cash channel closing balance is inconsistent',
							'details' => 'CASH_DRAWER closing balance is GHS ' . number_format($closing, 2) . ' but opening + credit - debit equals GHS ' . number_format($expectedClosing, 2) . '.',
						];
					}
				}elseif($headerAmount > 0){
					$issues[] = [
						'type' => 'cash_channel',
						'label' => 'Primary cash channel is missing',
						'details' => 'The drawer has an opening amount of GHS ' . number_format($headerAmount, 2) . ' but no CASH_DRAWER channel row was found.',
					];
				}

				$ecashRows = $db->query(
					"SELECT b.*
					 FROM drawer_balances b
					 INNER JOIN (
					 	SELECT MAX(id) AS max_id
					 	FROM drawer_balances
					 	WHERE drawer_id = :drawer_id
					 	  AND user_id = :user_id
					 	  AND channel_type = 'ecash'
					 	GROUP BY channel_key
					 ) x ON x.max_id = b.id",
					[
						'drawer_id' => $drawerId,
						'user_id' => $userId,
					]
				) ?: [];

				foreach($ecashRows as $ecashRow){
					$channelKey = strtoupper(trim((string)($ecashRow->channel_key ?? 'ECASH')));
					$opening = (float)($ecashRow->opening_balance ?? 0);
					$credit = (float)($ecashRow->credit ?? 0);
					$debit = (float)($ecashRow->debit ?? 0);
					$closing = (float)($ecashRow->closing_balance ?? 0);
					$expectedClosing = $opening + $credit - $debit;

					if(abs($expectedClosing - $closing) > 0.009){
						$issues[] = [
							'type' => 'ecash_channel',
							'label' => 'Channel balance mismatch for ' . $channelKey,
							'details' => $channelKey . ' closes at GHS ' . number_format($closing, 2) . ' but opening + credit - debit equals GHS ' . number_format($expectedClosing, 2) . '.',
						];
					}

					if(!operations_account_is_bank_channel_key($channelKey) && $opening > 0 && empty($ecashRow->proof_image)){
						$issues[] = [
							'type' => 'ecash_proof',
							'label' => 'Missing opening proof for ' . $channelKey,
							'details' => $channelKey . ' has an opening balance of GHS ' . number_format($opening, 2) . ' but no proof image is attached.',
						];
					}
				}
			}
		}catch(\Throwable $e){
			$issues[] = [
				'type' => 'diagnostic',
				'label' => 'Unable to fully analyse this cashdrawer',
				'details' => 'A database error prevented a full consistency check.',
			];
		}

		return [
			'issue_count' => count($issues),
			'issues' => $issues,
			'opening_deno_total' => $openingDenoTotal,
		];
	}
}

if(!function_exists('operations_account_collect_cashdrawer_dependencies')){
	function operations_account_collect_cashdrawer_dependencies(int|object $drawer): array
	{
		$row = is_object($drawer) ? $drawer : operations_account_get_cashdrawer_row((int)$drawer);
		if(empty($row) || empty($row->id)){
			return [
				'direct_records' => [],
				'ledger_refs' => [],
			];
		}

		$drawerId = (int)($row->id ?? 0);
		$userId = (int)($row->user_id ?? 0);
		$directRecords = [];
		$directRecordKeys = [];
		$ledgerRefs = [];

		try{
			$db = new \Core\Database;

			$appendDirectRecord = function(string $table, object $record, string $refColumn, array $columns, string $linkType = 'direct', array $sourceRefs = []) use (&$directRecords, &$directRecordKeys): void {
				$recordId = (int)($record->id ?? 0);
				if($table === '' || $recordId <= 0){
					return;
				}

				$key = $table . ':' . $recordId;
				if(isset($directRecordKeys[$key])){
					return;
				}

				$amount = operations_account_pick_record_value($record, ['amount','balance','expected_balance','variance','credit','debit','closing_balance','pickup_charge','budget_amount','spent_amount']);
				$status = operations_account_pick_record_value($record, ['transaction_status','status','withdrawal_status','creator_receipt_status','second_party_status','third_party_status']);
				$dateCreated = operations_account_pick_record_value($record, ['date_created','created_at','approved_at','date_updated','updated_at']);
				$summaryBits = [];
				foreach(['description','remarks','notes','request_type','commission_type','payment_type','transaction_type','network','bank_name','receiving_wallet','wallet_number','title','reference'] as $field){
					$value = trim((string)($record->$field ?? ''));
					if($value !== ''){
						$summaryBits[] = $value;
					}
					if(count($summaryBits) >= 3){
						break;
					}
				}

				$directRecordKeys[$key] = true;
				$directRecords[] = [
					'key' => $key,
					'table' => $table,
					'label' => operations_account_pretty_table_label($table),
					'id' => $recordId,
					'ref_column' => $refColumn,
					'ref_user_column' => $linkType === 'direct' && in_array('drawer_user_id', $columns, true) ? 'drawer_user_id' : null,
					'columns' => $columns,
					'amount' => is_numeric($amount) ? (float)$amount : null,
					'status' => $status !== null ? (string)$status : '',
					'date_created' => $dateCreated !== null ? (string)$dateCreated : '',
					'summary' => implode(' | ', $summaryBits),
					'link_type' => $linkType,
					'can_detach' => $linkType === 'direct',
					'delete_default' => $linkType === 'direct',
					'source_refs' => $sourceRefs,
				];
			};

			$excludedTables = [
				'cashdrawers',
				'drawer_balances',
				'drawer_denos',
				'drawer_fx_denos',
				'logs',
				'sessions',
				'user_roles',
				'user_roles_map',
				'users',
			];

			foreach(operations_account_db_table_list($db) as $table){
				$tableKey = strtolower(trim($table));
				if($tableKey === '' || in_array($tableKey, $excludedTables, true)){
					continue;
				}

				$columns = operations_account_db_table_columns($db, $table);
				if(empty($columns) || !in_array('id', $columns, true)){
					continue;
				}

				$refColumn = null;
				if(in_array('cashdrawer_id', $columns, true)){
					$refColumn = 'cashdrawer_id';
				}elseif(in_array('drawer_id', $columns, true)){
					$refColumn = 'drawer_id';
				}

				if($refColumn === null){
					continue;
				}

				$tableSql = str_replace('`', '``', $table);
				$refSql = str_replace('`', '``', $refColumn);
				$rows = $db->query(
					"SELECT *
					 FROM `{$tableSql}`
					 WHERE `{$refSql}` = :drawer_id
					 ORDER BY id DESC",
					['drawer_id' => $drawerId]
				) ?: [];

				foreach($rows as $record){
					$appendDirectRecord($table, $record, $refColumn, $columns);
				}
			}

			if($db->table_exists(['drawer_denos'])){
				$rows = $db->query(
					"SELECT 'drawer_denos' AS source_table, trans_id, trans_status, MAX(date_created) AS date_created, SUM(volume) AS net_amount, COUNT(*) AS line_count
					 FROM drawer_denos
					 WHERE drawer_id = :drawer_id
					   AND user_id = :user_id
					   AND (trans_id IS NOT NULL AND trans_id > 0)
					   AND trans_id <> :trans_id
					   AND (trans_status IS NULL OR LOWER(TRIM(trans_status)) <> 'opening snapshot')
					 GROUP BY trans_id, trans_status",
					[
						'drawer_id' => $drawerId,
						'user_id' => $userId,
						'trans_id' => $drawerId,
					]
				) ?: [];
				foreach($rows as $ref){
					$ledgerRefs[] = [
						'source_table' => 'drawer_denos',
						'trans_id' => (int)($ref->trans_id ?? 0),
						'trans_status' => (string)($ref->trans_status ?? ''),
						'date_created' => (string)($ref->date_created ?? ''),
						'net_amount' => (float)($ref->net_amount ?? 0),
						'line_count' => (int)($ref->line_count ?? 0),
					];
				}
			}

			if($db->table_exists(['drawer_balances'])){
				$rows = $db->query(
					"SELECT 'drawer_balances' AS source_table, trans_id, trans_status, MAX(date_created) AS date_created, SUM(credit - debit) AS net_amount, COUNT(*) AS line_count
					 FROM drawer_balances
					 WHERE drawer_id = :drawer_id
					   AND user_id = :user_id
					   AND (trans_id IS NOT NULL AND trans_id > 0)
					   AND trans_id <> :trans_id
					   AND (trans_status IS NULL OR LOWER(TRIM(trans_status)) NOT IN ('opening snapshot','opening cfa snapshot'))
					 GROUP BY trans_id, trans_status",
					[
						'drawer_id' => $drawerId,
						'user_id' => $userId,
						'trans_id' => $drawerId,
					]
				) ?: [];
				foreach($rows as $ref){
					$ledgerRefs[] = [
						'source_table' => 'drawer_balances',
						'trans_id' => (int)($ref->trans_id ?? 0),
						'trans_status' => (string)($ref->trans_status ?? ''),
						'date_created' => (string)($ref->date_created ?? ''),
						'net_amount' => (float)($ref->net_amount ?? 0),
						'line_count' => (int)($ref->line_count ?? 0),
					];
				}
			}

			$ledgerRefsByTransId = [];
			foreach($ledgerRefs as $ledgerRef){
				$transId = (int)($ledgerRef['trans_id'] ?? 0);
				if($transId <= 0){
					continue;
				}
				if(!isset($ledgerRefsByTransId[$transId])){
					$ledgerRefsByTransId[$transId] = [];
				}
				$ledgerRefsByTransId[$transId][] = $ledgerRef;
			}

			if(!empty($ledgerRefsByTransId)){
				$ledgerTransIds = array_keys($ledgerRefsByTransId);
				$ledgerBusinessTables = [
					'agencybankings',
					'airtimebundles',
					'payment_services',
					'cashpickups',
					'payments',
					'payments_backoffice',
					'requests',
					'commissions',
					'bank_transfers_backoffice',
					'reversals',
					'petty_cash_transactions',
				];

				$placeholders = [];
				$params = [];
				foreach($ledgerTransIds as $index => $transId){
					$key = 'ledger_trans_id_' . $index;
					$placeholders[] = ':' . $key;
					$params[$key] = (int)$transId;
				}

				foreach($ledgerBusinessTables as $table){
					if(!$db->table_exists([$table])){
						continue;
					}

					$columns = operations_account_db_table_columns($db, $table);
					if(empty($columns) || !in_array('id', $columns, true)){
						continue;
					}

					$tableSql = str_replace('`', '``', $table);
					$rows = $db->query(
						"SELECT *
						 FROM `{$tableSql}`
						 WHERE id IN (" . implode(',', $placeholders) . ")
						 ORDER BY id DESC",
						$params
					) ?: [];

					foreach($rows as $record){
						$recordId = (int)($record->id ?? 0);
						$sourceRefs = $ledgerRefsByTransId[$recordId] ?? [];
						$appendDirectRecord($table, $record, 'drawer ledger trans_id', $columns, 'ledger_trans_id', $sourceRefs);
					}
				}
			}
		}catch(\Throwable $e){
			$directRecords = [];
			$ledgerRefs = [];
		}

		usort($directRecords, function(array $a, array $b){
			return strcmp((string)($b['date_created'] ?? ''), (string)($a['date_created'] ?? ''));
		});

		usort($ledgerRefs, function(array $a, array $b){
			return strcmp((string)($b['date_created'] ?? ''), (string)($a['date_created'] ?? ''));
		});

		return [
			'direct_records' => $directRecords,
			'ledger_refs' => $ledgerRefs,
		];
	}
}

if(!function_exists('operations_account_delete_cashdrawer_and_links')){
	function operations_account_delete_cashdrawer_and_links(int|object $drawer, array $selectedDirectRecords = []): array
	{
		$row = is_object($drawer) ? $drawer : operations_account_get_cashdrawer_row((int)$drawer);
		if(empty($row) || empty($row->id)){
			throw new \RuntimeException('Cashdrawer not found.');
		}

		$drawerId = (int)($row->id ?? 0);
		$dependencies = operations_account_collect_cashdrawer_dependencies($row);
		$selectedMap = array_fill_keys(array_map('strval', $selectedDirectRecords), true);
		$db = new \Core\Database;
		$deletedLinked = 0;
		$detachedLinked = 0;
		$deletedRows = 0;
		$proofPaths = [];

		if($db->table_exists(['drawer_balances'])){
			$proofRows = $db->query(
				"SELECT proof_image
				 FROM drawer_balances
				 WHERE drawer_id = :drawer_id
				   AND proof_image IS NOT NULL
				   AND proof_image <> ''",
				['drawer_id' => $drawerId]
			) ?: [];
			foreach($proofRows as $proofRow){
				$proof = trim((string)($proofRow->proof_image ?? ''));
				if($proof !== ''){
					$proofPaths[] = $proof;
				}
			}
		}

		if(!$db->beginTransaction()){
			throw new \RuntimeException('Unable to start cashdrawer delete transaction.');
		}

		try{
			foreach(($dependencies['direct_records'] ?? []) as $record){
				$table = (string)($record['table'] ?? '');
				$recordId = (int)($record['id'] ?? 0);
				$refColumn = (string)($record['ref_column'] ?? '');
				$refUserColumn = (string)($record['ref_user_column'] ?? '');
				$columns = is_array($record['columns'] ?? null) ? $record['columns'] : [];
				$linkType = (string)($record['link_type'] ?? 'direct');
				if($table === '' || $recordId <= 0 || $refColumn === ''){
					continue;
				}

				$tableSql = str_replace('`', '``', $table);

				if(isset($selectedMap[$record['key']])){
					if($table === 'commissions' && in_array('bulk_parent_id', $columns, true)){
						$db->query(
							"DELETE FROM `{$tableSql}`
							 WHERE id = :id OR bulk_parent_id = :id",
							['id' => $recordId]
						);
						$deletedLinked += max(1, (int)$db->affected_rows);
					}else{
						$db->query(
							"DELETE FROM `{$tableSql}`
							 WHERE id = :id
							 LIMIT 1",
							['id' => $recordId]
						);
						$deletedLinked += (int)$db->affected_rows;
					}

					if($db->has_error){
						throw new \RuntimeException('Failed to delete linked record from ' . $table . '.');
					}
					continue;
				}

				if($linkType !== 'direct'){
					continue;
				}

				$setSql = [];
				if(in_array($refColumn, $columns, true)){
					$setSql[] = '`' . str_replace('`', '``', $refColumn) . '` = NULL';
				}
				if($refUserColumn !== '' && in_array($refUserColumn, $columns, true)){
					$setSql[] = '`' . str_replace('`', '``', $refUserColumn) . '` = NULL';
				}

				if(!empty($setSql)){
					$db->query(
						"UPDATE `{$tableSql}`
						 SET " . implode(', ', $setSql) . "
						 WHERE id = :id
						 LIMIT 1",
						['id' => $recordId]
					);
					if($db->has_error){
						throw new \RuntimeException('Failed to detach linked record from ' . $table . '.');
					}
					$detachedLinked += (int)$db->affected_rows;
				}
			}

			if($db->table_exists(['drawer_fx_denos'])){
				$db->query("DELETE FROM drawer_fx_denos WHERE drawer_id = :drawer_id", ['drawer_id' => $drawerId]);
				if($db->has_error){
					throw new \RuntimeException('Failed to delete CFA denomination rows.');
				}
				$deletedRows += (int)$db->affected_rows;
			}

			if($db->table_exists(['drawer_denos'])){
				$db->query("DELETE FROM drawer_denos WHERE drawer_id = :drawer_id", ['drawer_id' => $drawerId]);
				if($db->has_error){
					throw new \RuntimeException('Failed to delete denomination rows.');
				}
				$deletedRows += (int)$db->affected_rows;
			}

			if($db->table_exists(['drawer_balances'])){
				$db->query("DELETE FROM drawer_balances WHERE drawer_id = :drawer_id", ['drawer_id' => $drawerId]);
				if($db->has_error){
					throw new \RuntimeException('Failed to delete channel balance rows.');
				}
				$deletedRows += (int)$db->affected_rows;
			}

			$db->query("DELETE FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawerId]);
			if($db->has_error || (int)$db->affected_rows < 1){
				throw new \RuntimeException('Failed to delete the cashdrawer.');
			}

			if(!$db->commit()){
				throw new \RuntimeException('Failed to commit the cashdrawer delete transaction.');
			}
		}catch(\Throwable $e){
			if($db->inTransaction()){
				$db->rollBack();
			}
			throw $e;
		}

		foreach(array_unique($proofPaths) as $proofPath){
			$absolutePath = realpath(ROOTPATH . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $proofPath), DIRECTORY_SEPARATOR));
			if($absolutePath && is_file($absolutePath)){
				@unlink($absolutePath);
			}
		}

		return [
			'deleted_linked' => $deletedLinked,
			'detached_linked' => $detachedLinked,
			'deleted_drawer_rows' => $deletedRows + 1,
		];
	}
}

if(!function_exists('operations_account_backfill_drawer_denos_once')){
	function operations_account_backfill_drawer_denos_once(): void
	{
		static $ran = false;
		if($ran){
			return;
		}
		$ran = true;

		$rootPath = dirname(__DIR__, 2);
		$markerDir = $rootPath . DIRECTORY_SEPARATOR . 'temp';
		if(!is_dir($markerDir)){
			@mkdir($markerDir, 0775, true);
		}
		$markerFile = $markerDir . DIRECTORY_SEPARATOR . 'operations_account_drawer_denos_fx_backfill_20260418_v2.done';
		if(is_file($markerFile)){
			return;
		}

		try{
			$db = new \Core\Database;
			$updated = 0;

			$tableConfigs = [
				[
					'table' => 'drawer_denos',
					'currency' => 'GHS',
					'opening_status' => 'Opening Snapshot',
				],
				[
					'table' => 'drawer_fx_denos',
					'currency' => 'CFA',
					'opening_status' => 'Opening CFA Snapshot',
				],
			];

			foreach ($tableConfigs as $config) {
				$table = $config['table'];
				if(!$db->table_exists([$table])){
					continue;
				}

				$rows = $db->query(
					"SELECT id, drawer_id, denomination, `count`, volume, trans_id, trans_status" . ($table === 'drawer_fx_denos' ? ", currency" : "") . "
					 FROM {$table}
					 ORDER BY id ASC"
				) ?: [];

				foreach($rows as $row){
					$id = (int)($row->id ?? 0);
					$drawerId = (int)($row->drawer_id ?? 0);
					$transId = (int)($row->trans_id ?? 0);
					$denomination = (float)($row->denomination ?? 0);
					if($id <= 0 || $denomination <= 0){
						continue;
					}

					$currentCount = (float)($row->count ?? 0);
					$currentVolume = (float)($row->volume ?? 0);

					$normalizedCount = (int)round($currentCount);
					if($normalizedCount === 0 && abs($currentVolume) > 0.0001){
						$normalizedCount = (int)round($currentVolume / $denomination);
					}
					$normalizedVolume = (float)$normalizedCount * $denomination;

					$normalizedStatus = trim((string)($row->trans_status ?? ''));
					if($normalizedStatus === ''){
						if($transId > 0 && $drawerId > 0 && $transId === $drawerId && $normalizedCount > 0){
							$normalizedStatus = $config['opening_status'];
						}elseif($normalizedCount !== 0 || abs($normalizedVolume) > 0.0001){
							$normalizedStatus = 'Exchange';
						}
					}

					$needsUpdate = false;
					if(abs($currentCount - $normalizedCount) > 0.0001){
						$needsUpdate = true;
					}
					if(abs($currentVolume - $normalizedVolume) > 0.01){
						$needsUpdate = true;
					}
					if($normalizedStatus !== trim((string)($row->trans_status ?? ''))){
						$needsUpdate = true;
					}
					if($table === 'drawer_fx_denos' && strtoupper(trim((string)($row->currency ?? ''))) !== 'CFA'){
						$needsUpdate = true;
					}

					if(!$needsUpdate){
						continue;
					}

					$params = [
						'id' => $id,
						'count' => $normalizedCount,
						'volume' => $normalizedVolume,
					];
					$sql = "UPDATE {$table} SET `count` = :count, volume = :volume";
					if($normalizedStatus !== ''){
						$sql .= ", trans_status = :trans_status";
						$params['trans_status'] = $normalizedStatus;
					}
					if($table === 'drawer_fx_denos'){
						$sql .= ", currency = :currency";
						$params['currency'] = 'CFA';
					}
					$sql .= " WHERE id = :id";
					$db->query($sql, $params);
					$updated++;
				}
			}

			@file_put_contents(
				$markerFile,
				json_encode([
					'updated' => $updated,
					'completed_at' => date('Y-m-d H:i:s'),
				], JSON_UNESCAPED_SLASHES)
			);
		}catch(\Throwable $e){
			// If the backfill fails, leave the marker absent so we can retry later.
			error_log('[OperationsAccount][DrawerDenosBackfill] ' . $e->getMessage());
		}
	}
}

if(php_sapi_name() === 'cli'){
	operations_account_backfill_drawer_denos_once();
}


/** set mywallet permissions for this plugin **/
add_filter('permissions',function($permissions){

	$permissions[] = 'view_wallet';
	$permissions[] = 'view_transactions';
	$permissions[] = 'view_account_statements';
	$permissions[] = 'view_wallet_details';
	$permissions[] = 'add_wallet';
	$permissions[] = 'edit_wallet';
	$permissions[] = 'approve_transactions';
	$permissions[] = 'transfer_funds';
	$permissions[] = 'delete_wallet';

	return $permissions;
});


/** add to amin links **/
add_filter('basic-admin_before_admin_links',function($links){

$db = new \Core\Database;
	
		$vars = get_value();

	if (user_can('view_operations')) {
	    $obj = (object)[];
	    $obj->title = 'Operations';
	    $obj->id = 'view_operations';
	    $obj->link = '#';
	    $obj->icon = 'fas fa-cogs'; // Updated main icon for "Operations"
	    $obj->parent = 0;

	    $obj->sublinks = []; // Initialize sublinks array within the main link object

	    // Add sublinks in ascending order by title
	    $sub = (object)[];
	    $sub->title = 'Accounts';
	    $sub->link = ROOT . '/' . $vars['admin_route'] . '/operations_accounts';
	    $sub->icon = 'fas fa-briefcase'; // Briefcase for administrative tasks
	    $sub->parent = $obj->id;
	    $sub->id = 'accounts';
	    $obj->sublinks[] = $sub;

	    // Add sublinks in ascending order by title
	    $sub = (object)[];
	    $sub->title = 'Attendance';
	    $sub->link = ROOT . '/' . $vars['admin_route'] . '/attendance';
	    $sub->icon = 'fa fa-clipboard-check'; // Briefcase for administrative tasks
	    $sub->parent = $obj->id;
	    $sub->id = 'attendance';
	    $obj->sublinks[] = $sub;

	    // Add sublinks in ascending order by title
	    $sub = (object)[];
	    $sub->title = 'Customers Management';
	    $sub->link = ROOT . '/' . $vars['admin_route'] . '/cashpickup/customers';
	    $sub->icon = 'fa fa-users'; // Briefcase for administrative tasks
	    $sub->parent = $obj->id;
	    $sub->id = 'customers';
	    $obj->sublinks[] = $sub;

	    $sub = (object)[];
	    $sub->title = 'Error Reports';
	    $sub->link = ROOT . '/' . $vars['admin_route'] . '/error_reports';
	    $sub->icon = 'fas fa-exclamation-circle'; // Warning icon for errors
	    $sub->parent = $obj->id;
	    $sub->id = 'error_reports';
	    $obj->sublinks[] = $sub;

	    $sub = (object)[];
	    $sub->title = 'Performance';
	    $sub->link = ROOT . '/' . $vars['admin_route'] . '/performance';
	    $sub->icon = 'fas fa-chart-line'; // Line chart for performance
	    $sub->parent = $obj->id;
	    $sub->id = 'performance';
	    $obj->sublinks[] = $sub;

	    $sub = (object)[];
	    $sub->title = 'Trainings';
	    $sub->link = ROOT . '/' . $vars['admin_route'] . '/trainings';
	    $sub->icon = 'fas fa-chalkboard-teacher'; // Training/education icon
	    $sub->parent = $obj->id;
	    $sub->id = 'trainings';
	    $obj->sublinks[] = $sub;

	    $links[] = $obj;
	}


	return $links;

});

/** Handle API requests (GET) **/
add_action('controller',function(){

	$req = new \Core\Request;
	$vars = get_value();
	
	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];

	// Handle API endpoints (GET requests)
	if(URL(1) == $vars['plugin_route'] && URL(2) == 'get_drawer_denominations')
	{
		require plugin_path('controllers/get_drawer_denominations-controller.php');
	}
});

/** Lightweight AJAX endpoints (GET/POST) for metrics & cashdrawers list **/
add_action('controller', function(){
	$vars = get_value();
	$plugin_route = $vars['plugin_route'] ?? '';
	$req = new \Core\Request;
	
	// Don't hard-stop based on URL(1) because routes can be either
	// /operations_accounts/... or /admin/operations_accounts/...
	$seg1 = URL(1) ?? '';
	$seg2 = URL(2) ?? '';
	$seg3 = URL(3) ?? '';

	// Embedded cashdrawer view (for iframes/modals): returns ONLY the view content
	// and does not include the admin sidebar/header layout.
	if ($seg2 === 'embed_cashdrawer' || $seg3 === 'embed_cashdrawer') {
		// URL could be /operations_accounts/embed_cashdrawer/{id} OR /admin/operations_accounts/embed_cashdrawer/{id}
		$drawer_id = (int)(($seg2 === 'embed_cashdrawer') ? (URL(3) ?? 0) : (URL(4) ?? 0));
		if ($drawer_id <= 0) {
			header('Content-Type: text/plain; charset=utf-8');
			echo 'Missing cashdrawer id';
			exit;
		}
		$admin_route = $vars['admin_route'] ?? 'admin';
		$plugin_route = $vars['plugin_route'] ?? 'operations_accounts';
		if (!user_can('view_wallet_details')) {
			http_response_code(403);
			header('Content-Type: text/plain; charset=utf-8');
			echo 'Unauthorized';
			exit;
		}

		ini_set('display_errors', '0');
		error_reporting(E_ERROR | E_PARSE);
		header('Content-Type: text/html; charset=utf-8');

		$ses = new \Core\Session;
		$wallet = new \MyWallets\Wallet;
		$db = new \Core\Database;

		// Load drawer header
		$query = "SELECT m.*, u.first_name AS user_first_name, u.last_name AS user_last_name, u.image AS user_image
			FROM cashdrawers AS m
			LEFT JOIN users AS u ON m.user_id = u.id
			WHERE m.id = :id LIMIT 1";
		$row = ($wallet->query($query, ['id' => $drawer_id]) ?: [])[0] ?? null;

		// Initialize variables expected by the view
		$denominations = [];
		$total_volume = 0;
		$total_count = 0;
		$denomination_total_volume = 0.0;
		$denomination_total_count = 0.0;
		$cash_channels = [];
		$ecash_channels = [];
		$headerBalance = 0.0;
		$deno_history = [];
		$cashdrawer_edit_history = [];

		if (!empty($row)) {
			$drawerId = (int)($row->id ?? 0);
			$drawerDate = substr($row->date_created ?? date('Y-m-d'), 0, 10);
			$cashdrawer_edit_history = operations_account_cashdrawer_edit_history($drawerId);

			// Denomination snapshot (prefer drawer_id)
			try {
				if ($db->table_exists(['drawer_denos'])) {
					$denominations = $wallet->query(
						"SELECT denomination, SUM(count) AS count, SUM(volume) AS volume
						 FROM drawer_denos
						 WHERE drawer_id = :drawer_id AND user_id = :user_id
						 GROUP BY denomination
						 ORDER BY denomination DESC",
						['drawer_id' => $drawerId, 'user_id' => $row->user_id]
					) ?: [];
					if (empty($denominations)) {
						$denominations = $wallet->query(
							"SELECT denomination, SUM(count) AS count, SUM(volume) AS volume
							 FROM drawer_denos
							 WHERE user_id = :user_id AND DATE(date_created) = :drawer_date
							 GROUP BY denomination
							 ORDER BY denomination DESC",
							['user_id' => $row->user_id, 'drawer_date' => $drawerDate]
						) ?: [];
					}
				}
			} catch (\Throwable $e) {
				$denominations = [];
			}

			// Totals
			try {
				if ($db->table_exists(['drawer_denos'])) {
					$total_cashdrawer = ($wallet->query(
						"SELECT SUM(volume) AS total_volume, SUM(count) AS total_count
						 FROM drawer_denos
						 WHERE drawer_id = :drawer_id AND user_id = :user_id",
						['drawer_id' => $drawerId, 'user_id' => $row->user_id]
					) ?: [])[0] ?? null;
					if (empty($total_cashdrawer)) {
						$total_cashdrawer = ($wallet->query(
							"SELECT SUM(volume) AS total_volume, SUM(count) AS total_count
							 FROM drawer_denos
							 WHERE user_id = :user_id AND DATE(date_created) = :drawer_date",
							['user_id' => $row->user_id, 'drawer_date' => $drawerDate]
						) ?: [])[0] ?? null;
					}
					if ($total_cashdrawer) {
						$total_volume = (float)($total_cashdrawer->total_volume ?? 0);
						$total_count = (float)($total_cashdrawer->total_count ?? 0);
					}
				}
			} catch (\Throwable $e) {}

			$denominations = operations_account_normalize_denomination_rows($denominations, 'GHS');
			$denomination_total_volume = 0.0;
			$denomination_total_count = 0.0;
			foreach ($denominations as $denoRow) {
				$denomination_total_volume += (float)($denoRow->volume ?? 0);
				$denomination_total_count += (int)($denoRow->count ?? 0);
			}
			if ($denomination_total_count > 0 || abs($denomination_total_volume) > 0.009) {
				$total_count = $denomination_total_count;
				$total_volume = $denomination_total_volume;
			}

			// Basic headerBalance so view can render
			$headerBalance = (float)($row->amount ?? 0);

			// Deno history (optional)
			try {
				if ($db->table_exists(['drawer_denos'])) {
					$ghsRows = $db->query(
						"SELECT trans_id, date_created, 'GHS' AS currency, denomination, count, volume, trans_status
						 FROM drawer_denos
						 WHERE drawer_id = :drawer_id AND user_id = :user_id
						 ORDER BY date_created DESC, trans_id DESC, denomination DESC",
						['drawer_id' => $drawerId, 'user_id' => $row->user_id]
					) ?: [];
					$seq = 0;
					foreach ($ghsRows as $hRow) {
						$rawTransId = (int)($hRow->trans_id ?? 0);
						$keyId = $rawTransId > 0 ? $rawTransId : (++$seq * -1);
						if (!isset($deno_history[$keyId])) {
							$deno_history[$keyId] = [
								'trans_id' => $rawTransId > 0 ? $rawTransId : null,
								'date_created' => $hRow->date_created ?? null,
								'currency' => $hRow->currency ?? 'GHS',
								'items' => [],
								'trans_status' => $hRow->trans_status ?? null,
							];
						}
						$deno_history[$keyId]['items'][] = [
							'denomination' => $hRow->denomination ?? null,
							'count' => $hRow->count ?? null,
							'volume' => $hRow->volume ?? null,
						];
					}
				}
			} catch (\Throwable $e) {}
		}

		ob_start();
		require plugin_path('views/admin/cashdrawers/view.php');
		$viewHtml = ob_get_clean();

		// Iframe-safe styling: remove page margins, prevent horizontal overflow,
		// and keep fixed-position side panels contained within the iframe.
		$embedCss = '<style>'
			. '*,*::before,*::after{box-sizing:border-box!important;}'
			. 'html,body{margin:0!important;padding:0!important;overflow-x:hidden!important;background:#fff!important;width:100%!important;max-width:100%!important;}'
			. 'body{min-height:100vh!important;}'
			. '.cashdrawer-container{margin:0!important;border-radius:0!important;width:100%!important;max-width:100%!important;box-sizing:border-box!important;padding:16px!important;}'
			. '.cashdrawer-header{gap:12px!important;}'
			. '.action-buttons{flex-wrap:wrap!important;justify-content:flex-start!important;}'
			. '.btn-modern{max-width:100%!important;}'
			. '.channels-grid{grid-template-columns:repeat(auto-fit,minmax(240px,1fr))!important;gap:12px!important;}'
			. '.channel-card{max-width:100%!important;overflow:hidden!important;}'
			. '.draft-pending-sidebar,.draft-pending-sidebar.open{display:none!important;}'
			. '#denoHistorySidebar{display:none!important;}'
			. '.sidebar-header{position:static!important;}'
			. '</style>';

		echo $viewHtml . $embedCss;
		exit;
	}

	// AJAX performance metrics by period
	// Accept both JSON POST and simple query params so the frontend can
	// call this endpoint via GET or POST.
	if ($seg2 === 'ajax_performance_metrics' || $seg3 === 'ajax_performance_metrics') {
		// Force a clean JSON response for this endpoint only
		// so front-end fetch(...).json() does not break on PHP warnings.
		ini_set('display_errors', '0');
		error_reporting(E_ERROR | E_PARSE);
		header('Content-Type: application/json');
		$rawBody = file_get_contents('php://input');
		$body = json_decode($rawBody, true);
		if (!is_array($body)) {
			$body = [];
		}
		$period = $body['period']
			?? ($_POST['period'] ?? null)
			?? ($_GET['period'] ?? 'today');
		
		$now = new \DateTime('now');
		$start = null;
		if ($period === 'today') {
			$start = (clone $now)->setTime(0, 0, 0);
		} elseif ($period === '7d') {
			$start = (clone $now)->modify('-6 days')->setTime(0, 0, 0);
		} elseif ($period === '30d') {
			$start = (clone $now)->modify('-29 days')->setTime(0, 0, 0);
		}
		$startSql = $start ? $start->format('Y-m-d H:i:s') : null;
		$endSql = $now->format('Y-m-d H:i:s');
		$todayDate = $now->format('Y-m-d');

		$db = new \Core\Database;
		// Build DB-driven period filters so we rely on MySQL time (UTC) instead of PHP clock
		$periodDateFilter = '';
		$periodDateFilterSims = '';
		if ($period === 'today') {
			$periodDateFilter = " AND DATE(date_created) = CURDATE()";
			$periodDateFilterSims = " AND DATE(COALESCE(date_updated, date_created)) = CURDATE()";
		} elseif ($period === '7d') {
			$periodDateFilter = " AND DATE(date_created) BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()";
			$periodDateFilterSims = " AND DATE(COALESCE(date_updated, date_created)) BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()";
		} elseif ($period === '30d') {
			$periodDateFilter = " AND DATE(date_created) BETWEEN DATE_SUB(CURDATE(), INTERVAL 29 DAY) AND CURDATE()";
			$periodDateFilterSims = " AND DATE(COALESCE(date_updated, date_created)) BETWEEN DATE_SUB(CURDATE(), INTERVAL 29 DAY) AND CURDATE()";
		}
		$cashFloat = 0.0;
		$drawerCount = 0;
		$activeDrawersToday = 0;
		$networks = [];
		$totalSims = 0;
		$totalSold = 0;
		$txCount = 0;
		$txVolume = 0.0;
		$utilIndex = 0.0;
		$userRanking = [];
		$seriesCfas = [];
		$seriesMomo = [];
		$seriesAirtime = [];
		$seriesEzwich = [];
		$seriesAgency = [];
		$seriesPayServices = [];
		$seriesSims = [];

		// Cashdrawer float (opening amounts) and count over selected period
		try {
			$sql = "SELECT COUNT(id) AS total_drawers, SUM(amount) AS total_cashdrawer FROM cashdrawers WHERE 1";
			$params = [];
			if ($startSql) {
				$sql .= " AND date_created BETWEEN :start AND :end";
				$params['start'] = $startSql;
				$params['end'] = $endSql;
			}
			$row = $db->query($sql, $params)[0] ?? null;
			$cashFloat = (float)($row->total_cashdrawer ?? 0);
			$drawerCount = (int)($row->total_drawers ?? 0);
		} catch (\Throwable $e) {}

		// Active drawers today: strictly drawers whose date is today
		try {
			$sql = "SELECT COUNT(id) AS active_drawers FROM cashdrawers WHERE DATE(date_created) = :today";
			$row = $db->query($sql, ['today' => $todayDate])[0] ?? null;
			$activeDrawersToday = (int)($row->active_drawers ?? 0);
		} catch (\Throwable $e) {}

		// SIMs by network and sold state (period-aware by sale date using DB clock)
		try {
			$sql = "SELECT network,
			               COUNT(id) AS total,
			               SUM(CASE WHEN transaction_status IN ('Sold - retail','Sold - wholesale') THEN 1 ELSE 0 END) AS sold
			        FROM sims
			        WHERE deleted = 0" . $periodDateFilterSims . " GROUP BY network";
			$rows = $db->query($sql) ?: [];
			foreach ($rows as $r) {
				$net = $r->network ?? '';
				$tot = (int)($r->total ?? 0);
				$sol = (int)($r->sold ?? 0);
				if ($net === '') { continue; }
				$networks[$net] = [
					'total' => $tot,
					'sold'  => $sol,
				];
				$totalSims += $tot;
				$totalSold += $sol;
			}
		} catch (\Throwable $e) {}

		// Transactions aggregation (count + volume) across core services (period-aware via DB time)
		try {
			$dateWhere = '';
			if ($period === 'today') {
				$dateWhere = " WHERE DATE(activity_date) = CURDATE()";
			} elseif ($period === '7d') {
				$dateWhere = " WHERE DATE(activity_date) BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()";
			} elseif ($period === '30d') {
				$dateWhere = " WHERE DATE(activity_date) BETWEEN DATE_SUB(CURDATE(), INTERVAL 29 DAY) AND CURDATE()";
			}
			$sql = "SELECT COUNT(*) AS total_tx, SUM(amount) AS total_volume FROM (
				SELECT amount, date_created AS activity_date FROM transactions
				UNION ALL
				SELECT amount, date_created AS activity_date FROM agencybankings
				UNION ALL
				SELECT amount, date_created AS activity_date FROM payment_services
				UNION ALL
				SELECT amount, COALESCE(date_updated, date_created) AS activity_date
				FROM sims
				WHERE deleted = 0 AND transaction_status IN ('Sold - retail','Sold - wholesale')
			) AS combined" . $dateWhere;
			$row = $db->query($sql)[0] ?? null;
			$txCount = (int)($row->total_tx ?? 0);
			$txVolume = (float)($row->total_volume ?? 0);
		} catch (\Throwable $e) {}

		// User performance ranking: top users by combined service volume (period-aware via DB time)
		try {
			$dateWhere = '';
			if ($period === 'today') {
				$dateWhere = " WHERE DATE(c.activity_date) = CURDATE()";
			} elseif ($period === '7d') {
				$dateWhere = " WHERE DATE(c.activity_date) BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()";
			} elseif ($period === '30d') {
				$dateWhere = " WHERE DATE(c.activity_date) BETWEEN DATE_SUB(CURDATE(), INTERVAL 29 DAY) AND CURDATE()";
			}
			$sql = "SELECT u.id, u.first_name, u.last_name,
			               COUNT(*) AS tx_count,
			               SUM(c.amount) AS tx_volume
			        FROM (
			          SELECT created_by, amount, date_created AS activity_date FROM transactions
			          UNION ALL
			          SELECT created_by, amount, date_created AS activity_date FROM agencybankings
			          UNION ALL
			          SELECT created_by, amount, date_created AS activity_date FROM payment_services
			          UNION ALL
			          SELECT created_by, amount, COALESCE(date_updated, date_created) AS activity_date
			          FROM sims
			          WHERE deleted = 0 AND transaction_status IN ('Sold - retail','Sold - wholesale')
			        ) AS c
			        LEFT JOIN users u ON c.created_by = u.id" . $dateWhere . "
			        GROUP BY u.id, u.first_name, u.last_name
			        HAVING tx_count > 0
			        ORDER BY tx_volume DESC
			        LIMIT 10";
			$rows = $db->query($sql) ?: [];
			foreach ($rows as $r) {
				$userRanking[] = [
					'id' => (int)($r->id ?? 0),
					'name' => trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')),
					'tx_count' => (int)($r->tx_count ?? 0),
					'tx_volume' => (float)($r->tx_volume ?? 0),
				];
			}
		} catch (\Throwable $e) {}

		try {
			// Agencybankings (period-aware via DB time)
			$where = '';
			if ($period === 'today') {
				$where = " WHERE DATE(date_created) = CURDATE()";
			} elseif ($period === '7d') {
				$where = " WHERE DATE(date_created) BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()";
			} elseif ($period === '30d') {
				$where = " WHERE DATE(date_created) BETWEEN DATE_SUB(CURDATE(), INTERVAL 29 DAY) AND CURDATE()";
			}
			$sql = "SELECT DATE(date_created) AS d, SUM(amount) AS total
			        FROM agencybankings" . $where . " GROUP BY DATE(date_created) ORDER BY d ASC";
			$rows = $db->query($sql) ?: [];
			foreach ($rows as $r) {
				$seriesAgency[] = [
					'date' => $r->d ?? null,
					'total' => (float)($r->total ?? 0),
				];
			}
		} catch (\Throwable $e) {}

		try {
			// Payment services (period-aware via DB time)
			$where = '';
			if ($period === 'today') {
				$where = " WHERE DATE(date_created) = CURDATE()";
			} elseif ($period === '7d') {
				$where = " WHERE DATE(date_created) BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()";
			} elseif ($period === '30d') {
				$where = " WHERE DATE(date_created) BETWEEN DATE_SUB(CURDATE(), INTERVAL 29 DAY) AND CURDATE()";
			}
			$sql = "SELECT DATE(date_created) AS d, SUM(amount) AS total
			        FROM payment_services" . $where . " GROUP BY DATE(date_created) ORDER BY d ASC";
			$rows = $db->query($sql) ?: [];
			foreach ($rows as $r) {
				$seriesPayServices[] = [
					'date' => $r->d ?? null,
					'total' => (float)($r->total ?? 0),
				];
			}
		} catch (\Throwable $e) {}

		try {
			// Sims (sold SIMs volume, by sale date, period-aware via DB time)
			$where = " WHERE deleted = 0 AND transaction_status IN ('Sold - retail','Sold - wholesale')";
			if ($period === 'today') {
				$where .= " AND DATE(COALESCE(date_updated, date_created)) = CURDATE()";
			} elseif ($period === '7d') {
				$where .= " AND DATE(COALESCE(date_updated, date_created)) BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()";
			} elseif ($period === '30d') {
				$where .= " AND DATE(COALESCE(date_updated, date_created)) BETWEEN DATE_SUB(CURDATE(), INTERVAL 29 DAY) AND CURDATE()";
			}
			$sql = "SELECT DATE(COALESCE(date_updated, date_created)) AS d, SUM(amount) AS total
			        FROM sims" . $where . " GROUP BY DATE(COALESCE(date_updated, date_created)) ORDER BY d ASC";
			$rows = $db->query($sql) ?: [];
			foreach ($rows as $r) {
				$seriesSims[] = [
					'date' => $r->d ?? null,
					'total' => (float)($r->total ?? 0),
				];
			}
		} catch (\Throwable $e) {}

		// Time-series performance: CFAs (use amount_ghs as base), momoremits, airtimebundles, ezwichs
		try {
			// CFAs (period-aware via DB time)
			$where = '';
			if ($period === 'today') {
				$where = " WHERE DATE(date_created) = CURDATE()";
			} elseif ($period === '7d') {
				$where = " WHERE DATE(date_created) BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()";
			} elseif ($period === '30d') {
				$where = " WHERE DATE(date_created) BETWEEN DATE_SUB(CURDATE(), INTERVAL 29 DAY) AND CURDATE()";
			}
			$sql = "SELECT DATE(date_created) AS d, SUM(amount_ghs) AS total
			        FROM cfas" . $where . " GROUP BY DATE(date_created) ORDER BY d ASC";
			$rows = $db->query($sql) ?: [];
			foreach ($rows as $r) {
				$seriesCfas[] = [
					'date' => $r->d ?? null,
					'total' => (float)($r->total ?? 0),
				];
			}
		} catch (\Throwable $e) {}

		try {
			// Momoremits (period-aware via DB time)
			$where = '';
			if ($period === 'today') {
				$where = " WHERE DATE(date_created) = CURDATE()";
			} elseif ($period === '7d') {
				$where = " WHERE DATE(date_created) BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE";
			} elseif ($period === '30d') {
				$where = " WHERE DATE(date_created) BETWEEN DATE_SUB(CURDATE(), INTERVAL 29 DAY) AND CURDATE";
			}
			$sql = "SELECT DATE(date_created) AS d, SUM(CAST(amount AS DECIMAL(10,2))) AS total
			        FROM momoremits" . $where . " GROUP BY DATE(date_created) ORDER BY d ASC";
			$rows = $db->query($sql) ?: [];
			foreach ($rows as $r) {
				$seriesMomo[] = [
					'date' => $r->d ?? null,
					'total' => (float)($r->total ?? 0),
				];
			}
		} catch (\Throwable $e) {}

		try {
			// Airtimebundles (period-aware)
			$sql = "SELECT DATE(date_created) AS d, SUM(CAST(amount AS DECIMAL(10,2))) AS total
			        FROM airtimebundles WHERE 1";
			$params = [];
			if ($startSql) {
				$sql .= " AND date_created BETWEEN :start AND :end";
				$params['start'] = $startSql;
				$params['end'] = $endSql;
			}
			$sql .= " GROUP BY DATE(date_created) ORDER BY d ASC";
			$rows = $db->query($sql, $params) ?: [];
			foreach ($rows as $r) {
				$seriesAirtime[] = [
					'date' => $r->d ?? null,
					'total' => (float)($r->total ?? 0),
				];
			}
		} catch (\Throwable $e) {}

		try {
			// Ezwichs (period-aware)
			$sql = "SELECT DATE(date_created) AS d, SUM(amount) AS total
			        FROM ezwichs WHERE 1";
			$params = [];
			if ($startSql) {
				$sql .= " AND date_created BETWEEN :start AND :end";
				$params['start'] = $startSql;
				$params['end'] = $endSql;
			}
			$sql .= " GROUP BY DATE(date_created) ORDER BY d ASC";
			$rows = $db->query($sql, $params) ?: [];
			foreach ($rows as $r) {
				$seriesEzwich[] = [
					'date' => $r->d ?? null,
					'total' => (float)($r->total ?? 0),
				];
			}
		} catch (\Throwable $e) {}

		$sellThrough = $totalSims > 0 ? round(($totalSold / $totalSims) * 100, 1) : 0.0;
		if ($cashFloat > 0 && $txVolume > 0) {
			$utilIndex = min(500.0, max(0.0, round(($txVolume / $cashFloat) * 100, 1)));
		}

		echo json_encode([
			'success' => true,
			'cashdrawer_float' => $cashFloat,
			'drawer_count' => $drawerCount,
			'active_drawers_today' => $activeDrawersToday,
			'networks' => $networks,
			'total_sims' => $totalSims,
			'total_sold' => $totalSold,
			'sell_through' => $sellThrough,
			'tx_count' => $txCount,
			'tx_volume' => $txVolume,
			'util_index' => $utilIndex,
			'user_ranking' => $userRanking,
			'series' => [
				'cfas' => $seriesCfas,
				'momoremits' => $seriesMomo,
				'airtimebundles' => $seriesAirtime,
				'ezwichs' => $seriesEzwich,
				'agencybankings' => $seriesAgency,
				'payment_services' => $seriesPayServices,
				'sims' => $seriesSims,
			],
		]);
		exit;
	}

	// AJAX per-user performance drill (HTML fragment)
	if ($seg2 === 'ajax_user_performance' || $seg3 === 'ajax_user_performance') {
		header('Content-Type: text/html; charset=utf-8');
		$now = new \DateTime('now');
		$period = $_GET['period'] ?? 'today';
		$start = null;
		if ($period === 'today') {
			$start = (clone $now)->setTime(0, 0, 0);
		} elseif ($period === '7d') {
			$start = (clone $now)->modify('-6 days')->setTime(0, 0, 0);
		} elseif ($period === '30d') {
			$start = (clone $now)->modify('-29 days')->setTime(0, 0, 0);
		}
		$startSql = $start ? $start->format('Y-m-d H:i:s') : null;
		$endSql = $now->format('Y-m-d H:i:s');

		$userId = (int)($_GET['user_id'] ?? 0);
		if ($userId <= 0) {
			echo '<div class="text-muted">No user selected.</div>';
			exit;
		}

		$db = new \Core\Database;
		$userRow = null;
		$drawerSummary = ['count' => 0, 'float' => 0.0];
		$latestDrawers = [];
		$txSummary = ['count' => 0, 'volume' => 0.0];
		$peerSummary = [
			'drawers_per_user' => 0.0,
			'float_per_user'   => 0.0,
			'tx_per_user'      => 0.0,
			'volume_per_user'  => 0.0,
		];
		$svcSeries = [
			'cfas' => [],
			'momoremits' => [],
			'airtimebundles' => [],
			'ezwichs' => [],
			'agencybankings' => [],
			'payment_services' => [],
			'sims' => [],
		];
		try {
			$uRows = $db->query("SELECT id, first_name, last_name, image FROM users WHERE id = :id LIMIT 1", ['id' => $userId]);
			$userRow = $uRows[0] ?? null;
		} catch (\Throwable $e) {}

		try {
			$sql = "SELECT COUNT(id) AS drawer_count, SUM(amount) AS drawer_float FROM cashdrawers WHERE user_id = :uid";
			$params = ['uid' => $userId];
			if ($startSql) {
				$sql .= " AND date_created BETWEEN :start AND :end";
				$params['start'] = $startSql;
				$params['end'] = $endSql;
			}
			$row = $db->query($sql, $params)[0] ?? null;
			$drawerSummary['count'] = (int)($row->drawer_count ?? 0);
			$drawerSummary['float'] = (float)($row->drawer_float ?? 0);
		} catch (\Throwable $e) {}

		try {
			$sql = "SELECT id, amount, date_created, transaction_status FROM cashdrawers WHERE user_id = :uid";
			$params = ['uid' => $userId];
			if ($startSql) {
				$sql .= " AND date_created BETWEEN :start AND :end";
				$params['start'] = $startSql;
				$params['end'] = $endSql;
			}
			$sql .= " ORDER BY id DESC LIMIT 5";
			$latestDrawers = $db->query($sql, $params) ?: [];
		} catch (\Throwable $e) {}

		try {
			// Combined per-user transaction footprint across core services
			$params = ['uid' => $userId];
			$sql = "SELECT COUNT(*) AS tx_count, SUM(amount) AS tx_volume FROM (
				SELECT amount, date_created AS activity_date FROM transactions WHERE created_by = :uid
				UNION ALL
				SELECT amount, date_created AS activity_date FROM agencybankings WHERE created_by = :uid
				UNION ALL
				SELECT amount, date_created AS activity_date FROM payment_services WHERE created_by = :uid
				UNION ALL
				SELECT amount, COALESCE(date_updated, date_created) AS activity_date
				FROM sims
				WHERE created_by = :uid AND deleted = 0 AND transaction_status IN ('Sold - retail','Sold - wholesale')
			) AS combined";
			if ($startSql) {
				$sql .= " WHERE activity_date BETWEEN :start AND :end";
				$params['start'] = $startSql;
				$params['end'] = $endSql;
			}
			$row = $db->query($sql, $params)[0] ?? null;
			$txSummary['count'] = (int)($row->tx_count ?? 0);
			$txSummary['volume'] = (float)($row->tx_volume ?? 0);
		} catch (\Throwable $e) {}

		// Per-user time-series progress for key services (including agency, services, sims)
		try {
			// CFAs (amount_ghs, period-aware per user)
			$sql = "SELECT DATE(date_created) AS d, SUM(amount_ghs) AS total FROM cfas WHERE created_by = :uid";
			$params = ['uid' => $userId];
			if ($startSql) {
				$sql .= " AND date_created BETWEEN :start AND :end";
				$params['start'] = $startSql;
				$params['end'] = $endSql;
			}
			$sql .= " GROUP BY DATE(date_created) ORDER BY d ASC";
			$rows = $db->query($sql, $params) ?: [];
			foreach ($rows as $r) {
				$svcSeries['cfas'][] = [
					'date' => $r->d ?? null,
					'total' => (float)($r->total ?? 0),
				];
			}
		} catch (\Throwable $e) {}

		try {
			// Momoremits (period-aware per user)
			$sql = "SELECT DATE(date_created) AS d, SUM(CAST(amount AS DECIMAL(10,2))) AS total FROM momoremits WHERE created_by = :uid";
			$params = ['uid' => $userId];
			if ($startSql) {
				$sql .= " AND date_created BETWEEN :start AND :end";
				$params['start'] = $startSql;
				$params['end'] = $endSql;
			}
			$sql .= " GROUP BY DATE(date_created) ORDER BY d ASC";
			$rows = $db->query($sql, $params) ?: [];
			foreach ($rows as $r) {
				$svcSeries['momoremits'][] = [
					'date' => $r->d ?? null,
					'total' => (float)($r->total ?? 0),
				];
			}
		} catch (\Throwable $e) {}

		try {
			// Airtimebundles (period-aware per user)
			$sql = "SELECT DATE(date_created) AS d, SUM(CAST(amount AS DECIMAL(10,2))) AS total FROM airtimebundles WHERE created_by = :uid";
			$params = ['uid' => $userId];
			if ($startSql) {
				$sql .= " AND date_created BETWEEN :start AND :end";
				$params['start'] = $startSql;
				$params['end'] = $endSql;
			}
			$sql .= " GROUP BY DATE(date_created) ORDER BY d ASC";
			$rows = $db->query($sql, $params) ?: [];
			foreach ($rows as $r) {
				$svcSeries['airtimebundles'][] = [
					'date' => $r->d ?? null,
					'total' => (float)($r->total ?? 0),
				];
			}
		} catch (\Throwable $e) {}

		try {
			// Ezwichs (period-aware per user)
			$sql = "SELECT DATE(date_created) AS d, SUM(amount) AS total FROM ezwichs WHERE created_by = :uid";
			$params = ['uid' => $userId];
			if ($startSql) {
				$sql .= " AND date_created BETWEEN :start AND :end";
				$params['start'] = $startSql;
				$params['end'] = $endSql;
			}
			$sql .= " GROUP BY DATE(date_created) ORDER BY d ASC";
			$rows = $db->query($sql, $params) ?: [];
			foreach ($rows as $r) {
				$svcSeries['ezwichs'][] = [
					'date' => $r->d ?? null,
					'total' => (float)($r->total ?? 0),
				];
			}
		} catch (\Throwable $e) {}
		try {
			// Agencybankings (period-aware per user)
			$sql = "SELECT DATE(date_created) AS d, SUM(amount) AS total FROM agencybankings WHERE created_by = :uid";
			$params = ['uid' => $userId];
			if ($startSql) {
				$sql .= " AND date_created BETWEEN :start AND :end";
				$params['start'] = $startSql;
				$params['end'] = $endSql;
			}
			$sql .= " GROUP BY DATE(date_created) ORDER BY d ASC";
			$rows = $db->query($sql, $params) ?: [];
			foreach ($rows as $r) {
				$svcSeries['agencybankings'][] = [
					'date' => $r->d ?? null,
					'total' => (float)($r->total ?? 0),
				];
			}
		} catch (\Throwable $e) {}
		try {
			// Payment services (period-aware per user)
			$sql = "SELECT DATE(date_created) AS d, SUM(amount) AS total FROM payment_services WHERE created_by = :uid";
			$params = ['uid' => $userId];
			if ($startSql) {
				$sql .= " AND date_created BETWEEN :start AND :end";
				$params['start'] = $startSql;
				$params['end'] = $endSql;
			}
			$sql .= " GROUP BY DATE(date_created) ORDER BY d ASC";
			$rows = $db->query($sql, $params) ?: [];
			foreach ($rows as $r) {
				$svcSeries['payment_services'][] = [
					'date' => $r->d ?? null,
					'total' => (float)($r->total ?? 0),
				];
			}
		} catch (\Throwable $e) {}
		try {
			// Sims (per-user, sold only, by sale date, period-aware)
			$sql = "SELECT DATE(COALESCE(date_updated, date_created)) AS d, SUM(amount) AS total
			        FROM sims
			        WHERE created_by = :uid AND deleted = 0
			          AND transaction_status IN ('Sold - retail','Sold - wholesale')";
			$params = ['uid' => $userId];
			if ($startSql) {
				$sql .= " AND COALESCE(date_updated, date_created) BETWEEN :start AND :end";
				$params['start'] = $startSql;
				$params['end'] = $endSql;
			}
			$sql .= " GROUP BY DATE(COALESCE(date_updated, date_created)) ORDER BY d ASC";
			$rows = $db->query($sql, $params) ?: [];
			foreach ($rows as $r) {
				$svcSeries['sims'][] = [
					'date' => $r->d ?? null,
					'total' => (float)($r->total ?? 0),
				];
			}
		} catch (\Throwable $e) {}

		// Peer averages across all active users in the same period
		try {
			// Drawers peers
			$sql = "SELECT COUNT(id) AS drawer_count, COUNT(DISTINCT user_id) AS user_count, SUM(amount) AS total_float
			        FROM cashdrawers WHERE 1";
			$params = [];
			if ($startSql) {
				$sql .= " AND date_created BETWEEN :start AND :end";
				$params['start'] = $startSql;
				$params['end'] = $endSql;
			}
			$row = $db->query($sql, $params)[0] ?? null;
			$drawerCountAll = (int)($row->drawer_count ?? 0);
			$drawerUsersAll = max(1, (int)($row->user_count ?? 0));
			$totalFloatAll  = (float)($row->total_float ?? 0);

			// Transactions peers
			$sql = "SELECT COUNT(id) AS tx_count, COUNT(DISTINCT created_by) AS user_count, SUM(amount) AS total_volume
			        FROM transactions WHERE 1";
			$params = [];
			if ($startSql) {
				$sql .= " AND date_created BETWEEN :start AND :end";
				$params['start'] = $startSql;
				$params['end'] = $endSql;
			}
			$rowTx = $db->query($sql, $params)[0] ?? null;
			$txCountAll   = (int)($rowTx->tx_count ?? 0);
			$txUsersAll   = max(1, (int)($rowTx->user_count ?? 0));
			$volumeAll    = (float)($rowTx->total_volume ?? 0);

			$peerSummary['drawers_per_user'] = $drawerCountAll > 0 ? $drawerCountAll / $drawerUsersAll : 0.0;
			$peerSummary['float_per_user']   = $totalFloatAll  > 0 ? $totalFloatAll  / $drawerUsersAll : 0.0;
			$peerSummary['tx_per_user']      = $txCountAll     > 0 ? $txCountAll     / $txUsersAll     : 0.0;
			$peerSummary['volume_per_user']  = $volumeAll      > 0 ? $volumeAll      / $txUsersAll     : 0.0;
		} catch (\Throwable $e) {}

		$name = 'User ' . $userId;
		if ($userRow) {
			$full = trim(($userRow->first_name ?? '') . ' ' . ($userRow->last_name ?? ''));
			if ($full !== '') {
				$name = $full;
			}
		}

		$fmtInt = function ($n) {
			return number_format((int)$n);
		};
		$fmtMoney = function ($n) {
			return number_format((float)$n, 2);
		};

		// Lightweight narrative based on relative performance
		$drawerCount = $drawerSummary['count'];
		$drawerFloat = $drawerSummary['float'];
		$txCountUser = $txSummary['count'];
		$txVolUser   = $txSummary['volume'];
		$avgDrawers  = $peerSummary['drawers_per_user'];
		$avgTx       = $peerSummary['tx_per_user'];
		$drawerPos   = 'in line with peers';
		$txPos       = 'in line with peers';
		if ($avgDrawers > 0) {
			$ratio = $drawerCount / $avgDrawers;
			if ($ratio >= 1.4)      $drawerPos = 'well above peer drawer activity';
			elseif ($ratio <= 0.6)  $drawerPos = 'below typical drawer activity';
		}
		if ($avgTx > 0) {
			$ratio = $txCountUser / $avgTx;
			if ($ratio >= 1.4)      $txPos = 'a high-volume transaction profile';
			elseif ($ratio <= 0.6)  $txPos = 'a relatively quiet transaction profile';
		}

		echo '<div style="font-size:.8rem;">';
		echo '<div class="mb-1"><strong>' . htmlspecialchars($name) . '</strong></div>';
		echo '<p class="text-muted" style="font-size:.75rem;">';
		if ($drawerCount === 0 && $txCountUser === 0) {
			echo 'There is no drawer or transaction activity for this user in the selected period.';
		} else {
			echo 'In this period, the user operates with ' . $drawerPos . ' and ' . $txPos . ', based on drawers and transactions recorded in cashdrawers and transactions.';
		}
		echo '</p>';
		echo '<div class="ops-drill-metrics">';
		echo '<div class="ops-drill-pill"><small>Drawers in period</small><span>' . $fmtInt($drawerSummary['count']) . '</span></div>';
		echo '<div class="ops-drill-pill"><small>Drawer float</small><span>' . $fmtMoney($drawerSummary['float']) . '</span></div>';
		echo '<div class="ops-drill-pill"><small>TX count</small><span>' . $fmtInt($txSummary['count']) . '</span></div>';
		echo '<div class="ops-drill-pill"><small>TX volume</small><span>' . $fmtMoney($txSummary['volume']) . '</span></div>';
		echo '</div>';
		echo '</div>';

		if (!empty($latestDrawers)) {
			echo '<div class="mt-2" style="font-size:.78rem;">';
			echo '<div class="ops-drill-section-title">Latest drawers</div>';
			echo '<ul class="ops-drill-list">';
			foreach ($latestDrawers as $dr) {
				$did = (int)($dr->id ?? 0);
				$amt = $fmtMoney($dr->amount ?? 0);
				$dt = htmlspecialchars(substr($dr->date_created ?? '', 0, 16));
				$st = htmlspecialchars($dr->transaction_status ?? '');
				echo '<li>';
				echo '<div><strong>#' . $did . '</strong><div class="text-muted" style="font-size:.7rem;">' . $dt . '</div></div>';
				echo '<div class="text-end"><div>' . $amt . '</div><div style="font-size:.68rem;" class="text-muted">' . $st . '</div></div>';
				echo '</li>';
			}
			echo '</ul>';
			echo '</div>';
			// Simple SWOT-style hints derived from volume and drawer usage
			echo '<div class="mt-3" style="font-size:.76rem;">';
			echo '<div class="ops-drill-section-title">SWOT snapshot</div>';
			echo '<ul class="ops-drill-list">';
			echo '<li><span>Strengths</span><span>' . ($txVolUser > $peerSummary['volume_per_user'] ? 'Above-peer cash throughput' : 'Consistent but scalable volumes') . '</span></li>';
			echo '<li><span>Weaknesses</span><span>' . ($drawerCount === 0 ? 'No active drawer activity recorded' : 'Exposure to drawer-level breaks if controls weaken') . '</span></li>';
			echo '<li><span>Opportunities</span><span>Targeted campaigns or float optimisation can unlock more volume.</span></li>';
			echo '<li><span>Threats</span><span>Operational errors or chargebacks could erode realised performance.</span></li>';
			echo '</ul>';
			echo '</div>';
		} else {
			if ($drawerSummary['count'] > 0) {
				echo '<div class="text-muted mt-2" style="font-size:.78rem;">';
				echo 'There are ' . $fmtInt($drawerSummary['count']) . ' drawers for this user in the selected period, but their detailed breakdown is not available in this view. Use the Cashdrawer tab to inspect them line by line.';
				echo '</div>';
			} else {
				echo '<div class="text-muted mt-2" style="font-size:.78rem;">No drawers found for this user in the selected period.</div>';
			}
		}

		// Per-user service progress bars (including agency, services, sims) in this period
		$svcTotals = [
			'cfas' => 0.0,
			'momoremits' => 0.0,
			'airtimebundles' => 0.0,
			'ezwichs' => 0.0,
			'agencybankings' => 0.0,
			'payment_services' => 0.0,
			'sims' => 0.0,
		];
		foreach ($svcTotals as $key => $_) {
			if (!empty($svcSeries[$key]) && is_array($svcSeries[$key])) {
				$sum = 0.0;
				foreach ($svcSeries[$key] as $pt) {
					$sum += (float)($pt['total'] ?? 0);
				}
				$svcTotals[$key] = $sum;
			}
		}
		$maxSvcTotal = max($svcTotals) ?: 0.0;
		$labelsMap = [
			'cfas' => 'CFAs',
			'momoremits' => 'Momoremits',
			'airtimebundles' => 'Airtime bundles',
			'ezwichs' => 'Ezwich',
			'agencybankings' => 'Agency banking',
			'payment_services' => 'Payment services',
			'sims' => 'SIMs',
		];
		echo '<div class="mt-3" style="font-size:.78rem;">';
		echo '<div class="ops-drill-section-title">Service progress in period</div>';
		if ($maxSvcTotal > 0) {
			foreach ($svcTotals as $k => $val) {
				if ($val <= 0) { continue; }
				$ratio = $maxSvcTotal > 0 ? min(100, max(2, ($val / $maxSvcTotal) * 100)) : 0;
				$label = $labelsMap[$k] ?? $k;
				echo '<div class="mb-1">';
				echo '<div class="d-flex justify-content-between"><span>' . htmlspecialchars($label) . '</span><span>' . $fmtMoney($val) . '</span></div>';
				echo '<div class="ops-bar-track"><div class="ops-bar-fill" style="width: ' . $ratio . '%"></div></div>';
				echo '</div>';
			}
		} else {
			echo '<div class="text-muted">No CFA, momo, airtime, ezwich, agency, services or SIM volume recorded for this user in the selected period.</div>';
		}
		echo '</div>';
		exit;
	}

	// AJAX cashdrawers list (filter + pagination, HTML fragment)
	if ($seg2 === 'ajax_cashdrawers' || $seg3 === 'ajax_cashdrawers') {
		header('Content-Type: text/html; charset=utf-8');
		$session = get_session();
		$user_id = $session->id ?? 0;
		$sim = new \MyWallets\Sim;
		// Ensure routes are available for the included view
		$admin_route  = 'admin';
		$plugin_route = 'operations_accounts';
		$is_ajax_cashdrawers = true;
		
		$dateFrom = trim($_GET['date_from'] ?? '');
		$dateTo   = trim($_GET['date_to'] ?? '');
		$agent      = trim($_GET['agent'] ?? '');
		$userIdsRaw = $_GET['user_id'] ?? [];
		$userIds    = [];
		if (!is_array($userIdsRaw)) {
			$userIdsRaw = explode(',', (string)$userIdsRaw);
		}
		foreach ($userIdsRaw as $userIdRow) {
			$userIdRow = trim((string)$userIdRow);
			if ($userIdRow !== '' && is_numeric($userIdRow)) {
				$userIds[] = (int)$userIdRow;
			}
		}
		$userIds = array_unique($userIds);
		$page      = (int)($_GET['page'] ?? 1);
		if ($page < 1) { $page = 1; }
		// Items per page (min 5)
		$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
		if ($limit < 5) { $limit = 5; }
		$pager = new \Core\Pager($limit);
		$offset = $pager->offset;

		$conditions = [];
		$params = [];

		if ($dateFrom !== '') {
			$conditions[] = 'DATE(m.date_created) >= :date_from';
			$params['date_from'] = $dateFrom;
		}
		if ($dateTo !== '') {
			$conditions[] = 'DATE(m.date_created) <= :date_to';
			$params['date_to'] = $dateTo;
		}
		if ($agent !== '') {
			$conditions[] = '(u_assigned.first_name LIKE :agent OR u_assigned.last_name LIKE :agent OR CAST(m.user_id AS CHAR) LIKE :agent OR CAST(m.id AS CHAR) LIKE :agent)';
			$params['agent'] = '%' . $agent . '%';
		}
		if (!empty($userIds)) {
			$placeholders = [];
			foreach ($userIds as $index => $uid) {
				$key = ':user_id_' . $index;
				$placeholders[] = $key;
				$params[ltrim($key, ':')] = $uid;
			}
			$conditions[] = 'm.user_id IN (' . implode(', ', $placeholders) . ')';
		}

		$whereSql = '';
		if (!empty($conditions)) {
			$whereSql = 'WHERE ' . implode(' AND ', $conditions);
		}

		$drawerTotalsSql = "(
			SELECT drawer_id, SUM(volume) AS total_volume
			FROM drawer_denos
			GROUP BY drawer_id
		) AS drawer_totals";
		$legacyTotalsSql = "(
			SELECT user_id, DATE(date_created) AS drawer_date, SUM(volume) AS total_volume
			FROM drawer_denos
			GROUP BY user_id, DATE(date_created)
		) AS legacy_totals";

		$metricDrawerIds = [];
		$metricDrawerQuery = "SELECT m.id FROM cashdrawers AS m LEFT JOIN users AS u_assigned ON m.user_id = u_assigned.id $whereSql ORDER BY m.id DESC";
		$metricDrawerRows = $sim->query($metricDrawerQuery, $params) ?: [];
		foreach ((array)$metricDrawerRows as $metricDrawerRow) {
			$metricDrawerId = (int)($metricDrawerRow->id ?? 0);
			if ($metricDrawerId > 0) {
				$metricDrawerIds[] = $metricDrawerId;
			}
		}
		$metricDrawerIds = array_values(array_unique($metricDrawerIds));

		$cashdrawers_ghs_total = 0.0;
		$cashdrawers_cfa_total = 0.0;
		$cashdrawers_evd_total = 0.0;
		$cashdrawers_ecash_total = 0.0;
		$cashdrawers_bank_total = 0.0;
		if (!empty($metricDrawerIds)) {
			try {
				$metaDb = new \Core\Database;
				$idList = implode(',', array_map('intval', $metricDrawerIds));
				if ($metaDb->table_exists(['drawer_denos'])) {
					$ghsTotals = $metaDb->query("SELECT SUM(volume) AS total_volume FROM drawer_denos WHERE drawer_id IN ({$idList})") ?: [];
					$cashdrawers_ghs_total = (float)($ghsTotals[0]->total_volume ?? 0);
				}
				if ($metaDb->table_exists(['drawer_fx_denos'])) {
					$cfaTotals = $metaDb->query("SELECT SUM(volume) AS total_volume FROM drawer_fx_denos WHERE drawer_id IN ({$idList}) AND currency = 'CFA'") ?: [];
					$cashdrawers_cfa_total = (float)($cfaTotals[0]->total_volume ?? 0);
				}
				if ($metaDb->table_exists(['drawer_balances'])) {
					$balanceRows = $metaDb->query(
						"SELECT b.channel_key, b.opening_balance, b.credit, b.debit, b.closing_balance
						 FROM drawer_balances b
						 INNER JOIN (
							SELECT drawer_id, channel_key, MAX(id) AS max_id
							FROM drawer_balances
							WHERE drawer_id IN ({$idList}) AND channel_type = 'ecash'
							GROUP BY drawer_id, channel_key
						) last_row ON last_row.max_id = b.id"
					) ?: [];
					foreach ($balanceRows as $balanceRow) {
						$channelKey = strtoupper(trim((string)($balanceRow->channel_key ?? '')));
						$amount = (float)($balanceRow->opening_balance ?? 0)
							+ (float)($balanceRow->credit ?? 0)
							- (float)($balanceRow->debit ?? 0);
						if (operations_account_is_bank_channel_key($channelKey)) {
							$cashdrawers_bank_total += $amount;
						} elseif (operations_account_is_evd_channel_key($channelKey)) {
							$cashdrawers_evd_total += $amount;
						} else {
							$cashdrawers_ecash_total += $amount;
						}
					}
				}
			} catch (\Throwable $e) {
				// Keep the view rendering even if metric aggregation fails.
			}
		}
		$cashdrawers_total = $cashdrawers_ghs_total;

		// Paged rows
		$query = "
		    SELECT 
		        m.*, 
		        u.first_name AS user_first_name, 
		        u.last_name AS user_last_name, 
		        u.image AS user_image,
		        u_updated.first_name AS updated_by_first_name,
		        u_updated.last_name AS updated_by_last_name,
		        u_updated.image AS updated_by_image,
		        u_assigned.first_name AS assigned_first_name, 
		        u_assigned.last_name AS assigned_last_name, 
		        u_assigned.image AS assigned_image,
		        COALESCE(drawer_totals.total_volume, legacy_totals.total_volume, 0) AS current_amount
		    FROM cashdrawers AS m 
		    LEFT JOIN users AS u ON m.created_by = u.id 
		    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id
		    LEFT JOIN users AS u_assigned ON m.user_id = u_assigned.id 
		    LEFT JOIN {$drawerTotalsSql} ON drawer_totals.drawer_id = m.id
		    LEFT JOIN {$legacyTotalsSql} ON legacy_totals.user_id = m.user_id AND legacy_totals.drawer_date = DATE(m.date_created)
		    $whereSql
		    ORDER BY m.id DESC LIMIT $limit OFFSET $offset
		";

		$cashdrawers = $sim->query($query, $params);
		if (!empty($cashdrawers)) {
			foreach ($cashdrawers as $drawerRow) {
				$drawerRow->header_amount = (float)($drawerRow->amount ?? 0);
				$drawerRow->current_amount = (float)($drawerRow->current_amount ?? $drawerRow->amount ?? 0);
			}
		}

		$drawerIds = [];
		foreach ((array)$cashdrawers as $drawerRow) {
			$drawerIdVal = (int)($drawerRow->id ?? 0);
			if ($drawerIdVal > 0) {
				$drawerIds[] = $drawerIdVal;
			}
		}
		$drawerIds = array_values(array_unique($drawerIds));
		$ghsTotalsByDrawer = [];
		$cfaTotalsByDrawer = [];
		$ecashTotalsByDrawer = [];
		$bankTotalsByDrawer = [];

		if (!empty($drawerIds)) {
			try {
				$metaDb = new \Core\Database;
				$idList = implode(',', array_map('intval', $drawerIds));

				if ($metaDb->table_exists(['drawer_denos'])) {
					$ghsTotals = $metaDb->query(
						"SELECT drawer_id, SUM(volume) AS total_volume
						 FROM drawer_denos
						 WHERE drawer_id IN ({$idList})
						 GROUP BY drawer_id"
					) ?: [];
					foreach ($ghsTotals as $totalRow) {
						$ghsTotalsByDrawer[(int)($totalRow->drawer_id ?? 0)] = (float)($totalRow->total_volume ?? 0);
					}
				}

				if ($metaDb->table_exists(['drawer_fx_denos'])) {
					$cfaTotals = $metaDb->query(
						"SELECT drawer_id, SUM(volume) AS total_volume
						 FROM drawer_fx_denos
						 WHERE drawer_id IN ({$idList}) AND currency = 'CFA'
						 GROUP BY drawer_id"
					) ?: [];
					foreach ($cfaTotals as $totalRow) {
						$cfaTotalsByDrawer[(int)($totalRow->drawer_id ?? 0)] = (float)($totalRow->total_volume ?? 0);
					}
				}

				if ($metaDb->table_exists(['drawer_balances'])) {
					$channelRows = $metaDb->query(
						"SELECT b.drawer_id, b.channel_key, b.opening_balance, b.credit, b.debit, b.closing_balance
						 FROM drawer_balances b
						 INNER JOIN (
							SELECT drawer_id, channel_key, MAX(id) AS max_id
							FROM drawer_balances
							WHERE drawer_id IN ({$idList}) AND channel_type = 'ecash'
							GROUP BY drawer_id, channel_key
						 ) last_row ON last_row.max_id = b.id"
					) ?: [];
					foreach ($channelRows as $channelRow) {
						$drawerIdVal = (int)($channelRow->drawer_id ?? 0);
						if ($drawerIdVal <= 0) {
							continue;
						}
						$opening = (float)($channelRow->opening_balance ?? 0);
						$credit  = (float)($channelRow->credit ?? 0);
						$debit   = (float)($channelRow->debit ?? 0);
						$balance = $opening + $credit - $debit;
						if (operations_account_is_bank_channel_key($channelRow->channel_key ?? '')) {
							if (!isset($bankTotalsByDrawer[$drawerIdVal])) {
								$bankTotalsByDrawer[$drawerIdVal] = 0.0;
							}
							$bankTotalsByDrawer[$drawerIdVal] += $balance;
						} else {
							if (!isset($ecashTotalsByDrawer[$drawerIdVal])) {
								$ecashTotalsByDrawer[$drawerIdVal] = 0.0;
							}
							$ecashTotalsByDrawer[$drawerIdVal] += $balance;
						}
					}
				}
			} catch (\Throwable $e) {
				// Keep list rendering even if a balance side query fails.
			}
		}

		if (!empty($cashdrawers)) {
			foreach ($cashdrawers as $drawerRow) {
				$drawerIdVal = (int)($drawerRow->id ?? 0);
				if ($drawerIdVal <= 0) {
					continue;
				}
				$drawerRow->ghs_balance = $ghsTotalsByDrawer[$drawerIdVal] ?? (float)($drawerRow->amount ?? 0);
				$drawerRow->cfa_balance = array_key_exists($drawerIdVal, $cfaTotalsByDrawer) ? $cfaTotalsByDrawer[$drawerIdVal] : null;
				$drawerRow->ecash_balance = array_key_exists($drawerIdVal, $ecashTotalsByDrawer) ? $ecashTotalsByDrawer[$drawerIdVal] : null;
				$drawerRow->bank_balance = array_key_exists($drawerIdVal, $bankTotalsByDrawer) ? $bankTotalsByDrawer[$drawerIdVal] : null;
				$drawerRow->repair_issue_count = 0;
				$drawerRow->has_repair_issue = false;
				if(user_can('delete_wallet') || user_can('edit_wallet')){
					$issueReport = operations_account_cashdrawer_issue_report($drawerRow);
					$drawerRow->repair_issue_count = (int)($issueReport['issue_count'] ?? 0);
					$drawerRow->repair_issues = $issueReport['issues'] ?? [];
					$drawerRow->has_repair_issue = $drawerRow->repair_issue_count > 0;
				}
			}
		}

		// Total for pagination + total amount
		$countSql = "
			SELECT COUNT(m.id) AS total_rows,
			       SUM(COALESCE(drawer_totals.total_volume, legacy_totals.total_volume, 0)) AS total_cashdrawer
			FROM cashdrawers AS m
			LEFT JOIN users AS u_assigned ON m.user_id = u_assigned.id
			LEFT JOIN {$drawerTotalsSql} ON drawer_totals.drawer_id = m.id
			LEFT JOIN {$legacyTotalsSql} ON legacy_totals.user_id = m.user_id AND legacy_totals.drawer_date = DATE(m.date_created)
			$whereSql
		";
		$totalRow = $sim->query($countSql, $params)[0] ?? null;
		$totalRows = (int)($totalRow->total_rows ?? 0);
		$cashdrawers_total = (float)($totalRow->total_cashdrawer ?? 0);
		// Make sure pager window cannot exceed real page count
		if ($limit > 0 && $totalRows >= 0) {
			$totalPages = max(1, (int)ceil($totalRows / $limit));
			// Clamp current page within bounds
			if ($pager->page_number > $totalPages) {
				$pager->page_number = $totalPages;
			}
			$pager->total_pages = $totalPages;
			// Fix "next" link so it never goes past the last page
			$currentLink = $pager->links['current'];
			$nextPage = $pager->page_number + 1;
			if ($nextPage > $totalPages) {
				$nextPage = $totalPages;
			}
			$pager->links['next'] = preg_replace("/page=[0-9]+/", "page=".$nextPage, $currentLink);
		}

		// Render existing list view as fragment
		ob_start();
		require plugin_path('views/admin/cashdrawers/list.php');
		$html = ob_get_clean();
		echo $html;
		exit;
	}

	// AJAX sims list (pagination + items-per-page, filters + search, HTML fragment)
	if ($seg2 === 'ajax_sims' || $seg3 === 'ajax_sims') {
		header('Content-Type: text/html; charset=utf-8');
		$session = get_session();
		$user_id = $session->id ?? 0;
		$sim = new \MyWallets\Sim;
		// Ensure routes are available for the included view
		$admin_route  = 'admin';
		$plugin_route = 'operations_accounts';
		$is_ajax_sims = true;

		$page  = (int)($_GET['page'] ?? 1);
		if ($page < 1) { $page = 1; }
		$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
		if ($limit < 5) { $limit = 5; }
		$pager = new \Core\Pager($limit);
		$pager->page_number = $page;
		$pager->offset = ($page - 1) * $limit;
		$offset = $pager->offset;

		$network = trim($_GET['network'] ?? '');
		$search  = trim($_GET['q'] ?? '');

		$conditions = [];
		$params     = [];

		if ($network !== '') {
			$conditions[] = 'LOWER(m.network) = :network';
			$params['network'] = strtolower($network);
		}
		if ($search !== '') {
			$conditions[] = '(
			    m.serial_number   LIKE :q
			    OR m.number_assigned LIKE :q
			    OR CAST(m.id AS CHAR) LIKE :q
			    OR u_assigned.first_name LIKE :q
			    OR u_assigned.last_name  LIKE :q
			    OR u.first_name          LIKE :q
			    OR u.last_name           LIKE :q
			)';
			$params['q'] = '%'.$search.'%';
		}

		$whereSql = '';
		if (!empty($conditions)) {
			$whereSql = 'WHERE ' . implode(' AND ', $conditions);
		}

		// Shared FROM clause so rows, counts and metrics all see the same joins
		$fromSql = "FROM sims AS m 
		    LEFT JOIN users AS u ON m.created_by = u.id 
		    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
		    LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id";

		// Base sims rows mirroring non-AJAX listing, but filtered and paged
		// Total rows for pager window, using same filters
		$countSql = "SELECT COUNT(m.id) AS total_rows $fromSql $whereSql";
		$totalRow = $sim->query($countSql, $params)[0] ?? null;
		$totalRows = (int)($totalRow->total_rows ?? 0);
		$totalPages = max(1, (int)ceil($totalRows / $limit));
		if ($pager->page_number > $totalPages) {
			$pager->page_number = $totalPages;
		}
		$pager->total_pages = $totalPages;
		$offset = ($pager->page_number - 1) * $limit;
		$pager->offset = $offset;
		$currentLink = preg_replace("/page=[0-9]+/", "page=".$pager->page_number, $pager->links['current']);
		$pager->links['current'] = $currentLink;
		$nextPage = min($totalPages, $pager->page_number + 1);
		$pager->links['next'] = preg_replace("/page=[0-9]+/", "page=".$nextPage, $currentLink);

		$query = "
		    SELECT 
		        m.*, 
		        u.first_name AS user_first_name, 
		        u.last_name AS user_last_name, 
		        u.image AS user_image,
		        u_updated.first_name AS updated_by_first_name, 
		        u_updated.last_name AS updated_by_last_name, 
		        u_updated.image AS updated_by_image,
		        u_assigned.first_name AS assigned_first_name, 
		        u_assigned.last_name AS assigned_last_name, 
		        u_assigned.image AS assigned_image 
		    $fromSql
		    $whereSql
		    ORDER BY m.id DESC LIMIT $limit OFFSET $offset
		";
		$sims = $sim->query($query, $params);

		// Metrics (unassigned, assigned, sold, expired, totals) using same filters
		$metricsBaseConditions = ['m.deleted = 0'];
		if (!empty($conditions)) {
			$metricsBaseConditions = array_merge($metricsBaseConditions, $conditions);
		}
		$metricsWhere = 'WHERE ' . implode(' AND ', $metricsBaseConditions);

		$metricsSql = "SELECT COUNT(m.id) AS total_count, SUM(m.amount) AS total_amount, SUM(m.commission) AS total_commission
		              $fromSql
		              $metricsWhere AND m.transaction_status = :status";

		// Helper to run a status metrics query while preserving filter params
		$runStatusMetrics = function(string $status) use ($sim, $metricsSql, $params) {
			$statusParams = $params;
			$statusParams['status'] = $status;
			return $sim->query($metricsSql, $statusParams)[0] ?? null;
		};

		$row = $runStatusMetrics('Unassigned');
		if ($row) {
			$totalUnassigned_amount     = $row->total_amount ?? 0;
			$totalUnassigned_commission = $row->total_commission ?? 0;
			$totalUnassigned_count      = $row->total_count ?? 0;
		}
		$row = $runStatusMetrics('Assigned');
		if ($row) {
			$totalAssigned_amount     = $row->total_amount ?? 0;
			$totalAssigned_commission = $row->total_commission ?? 0;
			$totalAssigned_count      = $row->total_count ?? 0;
		}
		$row = $runStatusMetrics('Sold - wholesale');
		if ($row) {
			$totalSoldWholesale_amount     = $row->total_amount ?? 0;
			$totalSoldWholesale_commission = $row->total_commission ?? 0;
			$totalSoldWholesale_count      = $row->total_count ?? 0;
		}
		$row = $runStatusMetrics('Sold - retail');
		if ($row) {
			$totalSoldRetail_amount     = $row->total_amount ?? 0;
			$totalSoldRetail_commission = $row->total_commission ?? 0;
			$totalSoldRetail_count      = $row->total_count ?? 0;
		}
		$row = $runStatusMetrics('Expired');
		if ($row) {
			$totalExpired_amount     = $row->total_amount ?? 0;
			$totalExpired_commission = $row->total_commission ?? 0;
			$totalExpired_count      = $row->total_count ?? 0;
		}

		// Global totals for the filtered set (all statuses)
		$globalSql = "SELECT COUNT(m.id) AS total_count, SUM(m.amount) AS total_amount, SUM(m.commission) AS total_commission
		             $fromSql
		             $metricsWhere";
		$total = $sim->query($globalSql, $params)[0] ?? null;
		if ($total) {
			$total_amount     = $total->total_amount ?? 0;
			$total_commission = $total->total_commission ?? 0;
			$total_count      = $total->total_count ?? 0;
		}

		// High-level SIMS KPIs for the Sims tab header, based on the same filtered dataset
		$opsTotalSimsKpi = (int)($total_count ?? 0);
		$opsTotalSoldKpi = (int)(($totalSoldWholesale_count ?? 0) + ($totalSoldRetail_count ?? 0));
		$opsSellThroughKpi = $opsTotalSimsKpi > 0
			? round(($opsTotalSoldKpi / $opsTotalSimsKpi) * 100, 1)
			: 0;

		// Active networks with inventory in the filtered set
		$activeSql = "SELECT COUNT(DISTINCT m.network) AS active_networks
		             $fromSql
		             $metricsWhere";
		$active = $sim->query($activeSql, $params)[0] ?? null;
		$opsActiveNetworksKpi = (int)($active->active_networks ?? 0);

		// Render sims list view as fragment
		ob_start();
		require plugin_path('views/admin/sims/list.php');
		$html = ob_get_clean();
		echo $html;
		exit;
	}
});

/** Handle AJAX check-cashdrawer-today request **/
add_action('controller',function(){
	$req = new \Core\Request;
	
	if($req->posted()){
		$inputData = json_decode(file_get_contents("php://input"), true);
		$action = $inputData['action'] ?? '';
		
		if($action === 'check-cashdrawer-today'){
			header('Content-Type: application/json');
			
			$session = get_session();
			$userId = $session->id ?? 0;
			
			if(empty($userId)){
				echo json_encode(['success' => false, 'exists' => false]);
				exit;
			}
			
			$db = new \Core\Database;
			$today = date('Y-m-d');
			$exists = operations_account_has_cashdrawer_today((int)$userId, $today);
			
			echo json_encode(['success' => true, 'exists' => $exists]);
			exit;
		}
	}
});

/** run this after a form submit **/
add_action('controller',function(){

	$req = new \Core\Request;
	$vars = get_value();
	
	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];

	if(URL(1) == $vars['plugin_route'] && $req->posted())
	{
		$ses = new \Core\Session;
		$mywallet = new \MyWallets\Mywallet;
		$wallet = new \MyWallets\Wallet;
		$sim = new \MyWallets\Sim;
		$myaccount = new \MyWallets\Myaccount;
		$transaction = new \MyWallets\Transaction;
		$mycategory = new \MyWallets\Mycategory;
		$mywallet_roles_map = new \MyWallets\User_roles_map;

		$id = URL(3) ?? null;
		if($id)
			$row = $mywallet->first(['id'=>$id]);
			$mycategories = $mycategory->where(['disabled'=>0]);


		if(URL(2) == '' && isset($_POST['user_id'], $_POST['currency'])){
			require plugin_path('controllers/add_cashdrawer-controller.php');
		}else
		if(URL(2) == 'add_account'){
			require plugin_path('controllers/add_account-controller.php');
		}else
		if(URL(2) == 'edit_account'){
			
			require plugin_path('controllers/edit_account-controller.php');
		}else
		if(URL(2) == 'delete_account'){

			require plugin_path('controllers/delete_account-controller.php');
		}else
		if(URL(2) == 'add_wallet'){
			require plugin_path('controllers/add_wallet-controller.php');
		}else
		if(URL(2) == 'edit_wallet'){			
			require plugin_path('controllers/edit_wallet-controller.php');
		}else
		if(URL(2) == 'delete_wallet'){
			require plugin_path('controllers/delete_wallet-controller.php');
		}else
		if(URL(2) == 'add_cashdrawer'){
			require plugin_path('controllers/add_cashdrawer-controller.php');
		}else
		if(URL(2) == 'edit_cashdrawer'){			
			$cashdrawer = new \MyWallets\Cashdrawer;
			$row = $cashdrawer->first(['id'=>$id]);
			require plugin_path('controllers/edit_cashdrawer-controller.php');
		}else
		if(URL(2) == 'delete_cashdrawer'){
			$cashdrawer = new \MyWallets\Cashdrawer;
			$row = $cashdrawer->first(['id'=>$id]);
			require plugin_path('controllers/delete_cashdrawer-controller.php');
		}else
		if(URL(2) == 'add_sim'){
			require plugin_path('controllers/add_sim-controller.php');
		}else
		if(URL(2) == 'bulk_assign_sims'){
			require plugin_path('controllers/bulk_assign_sims-controller.php');
		}else
		if(URL(2) == 'edit_sim'){
			$row = $sim->first(['id'=>$id]); 
			require plugin_path('controllers/edit_sim-controller.php');
		}else
		if(URL(2) == 'transfer'){
			require plugin_path('controllers/transfer_wallet-controller.php');
		}else
		if(URL(2) == 'exchange_denominations'){
			require plugin_path('controllers/exchange_denominations-controller.php');
		}else
		if(URL(2) == 'change_denominations'){
			require plugin_path('controllers/change-controller.php');
		}

	}
});


/** displays the view file **/
add_action('basic-admin_main_content',function(){

	$ses = new \Core\Session;
	$mywallet = new \MyWallets\Mywallet;
	$wallet = new \MyWallets\Wallet;
	$transaction = new \MyWallets\Transaction;
	$sim = new \MyWallets\Sim;
	$mycategory = new \MyWallets\Mycategory;
				$sim = new \MyWallets\Sim;
				$user = new \MyWallets\User;
				$vars = get_value();

	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];
	
	$errors = $vars['errors'] ?? [];

				$user::$query_id = 'get-users';
				$users = $user->where(['deleted'=>0]);


	if(URL(1) == $vars['plugin_route']){

		$id = URL(3) ?? null;

	    $session = get_session();
	    $user_id = $session->id;
	    //dd($session);

		if($id){
			$mywallet::$query_id = 'get-users';
			
			$row = $mywallet->first(['id'=>$id]);
		}

		$mywallets = $mywallet->where(
			['deleted'=>0, 'transaction_status'=>'Approved'],
			['id'=>$id]
		);
		$mycategories = $mycategory->where(['disabled'=>0]);

		$showAddCashdrawerAsDefault = false;
		if ((string)(URL(2) ?? '') === '' && !empty($user_id) && function_exists('operations_account_has_opened_or_owned_cashdrawer_today')) {
			$rolesRaw = $session->roles ?? [];
			if (!is_array($rolesRaw)) {
				$rolesRaw = [$rolesRaw];
			}
			$rolesLower = array_map('strtolower', $rolesRaw);
			$isDrawerManager = false;
			foreach ($rolesLower as $roleName) {
				if (strpos((string)$roleName, 'admin') !== false || strpos((string)$roleName, 'manager') !== false) {
					$isDrawerManager = true;
					break;
				}
			}
			$showAddCashdrawerAsDefault = !$isDrawerManager && !operations_account_has_opened_or_owned_cashdrawer_today((int)$user_id);
		}

		$cashdrawerVaultLimits = [
			'branch_assigned' => false,
			'branch_id' => 0,
			'vault_count' => 0,
			'available' => ['GHS' => [], 'CFA' => []],
			'lookup_error' => '',
		];
		if ($showAddCashdrawerAsDefault || (string)(URL(2) ?? '') === 'add_cashdrawer') {
			try {
				if (function_exists('operations_account_cashdrawer_vault_limits')) {
					$cashdrawerVaultLimits = operations_account_cashdrawer_vault_limits((int)$user_id);
				} else {
					$cashdrawerVaultLimits['lookup_error'] = 'Vault availability could not be checked. Please contact an administrator.';
				}
			} catch (\Throwable $e) {
				error_log('[CashdrawerVaultLimits] ' . $e->getMessage());
				$cashdrawerVaultLimits['lookup_error'] = 'Vault availability could not be checked. Please contact an administrator.';
			}
		}
		$cashdrawerCarryForward = null;
		$cashdrawerCarryForwardError = '';
		if ($showAddCashdrawerAsDefault || (string)(URL(2) ?? '') === 'add_cashdrawer') {
			try {
				$cashdrawerCarryForward = operations_account_previous_cashdrawer_snapshot((int)$user_id);
			} catch (\Throwable $e) {
				error_log('[CashdrawerCarryForward] ' . $e->getMessage());
				$cashdrawerCarryForwardError = 'Previous cashdrawer balances could not be verified. Please contact an administrator before opening today’s cashdrawer.';
			}
		}

		if($showAddCashdrawerAsDefault){
			$settings = null;
			try{
				$settingsModel = new \Settings\Setting;
				$settings = $settingsModel->first(['id' => 1]);
			}catch(\Throwable $e){
				$settings = null;
			}
			require plugin_path('views/admin/cashdrawers/add.php');
		}else
		if(URL(2) == 'add_account'){			
			$mywallet_role = new \MyWallets\User_role;
			require plugin_path('views/admin/accounts/add.php');
		}else
		if(URL(2) == 'edit_account'){			
			$mywallet_role = new \MyWallets\User_role;
			require plugin_path('views/admin/accounts/edit.php');
		}else
		if(URL(2) == 'delete_account'){
			require plugin_path('views/admin/accounts/delete.php');
		}else
		if(URL(2) == 'add_wallet'){			
			$mywallet_role = new \MyWallets\User_role;
			require plugin_path('views/admin/wallets/add.php');
		}else
		if(URL(2) == 'edit_wallet'){			
			$mywallet_role = new \MyWallets\User_role;
			require plugin_path('views/admin/wallets/edit.php');
		}else
		if(URL(2) == 'delete_wallet'){
			require plugin_path('views/admin/wallets/delete.php');
		}else
		if(URL(2) == 'add_cashdrawer'){		
			// Load global settings so add cashdrawer page can access CFA buying/selling rates
			$settings = null;
			try{
				$settingsModel = new \Settings\Setting;
				$settings = $settingsModel->first(['id' => 1]);
			}catch(\Throwable $e){
				$settings = null;
			}
			require plugin_path('views/admin/cashdrawers/add.php');
		}else
		if(URL(2) == 'edit_cashdrawer'){			
			// Load global settings so edit cashdrawer page can access CFA buying/selling rates
			$settings = null;
			try{
				$settingsModel = new \Settings\Setting;
				$settings = $settingsModel->first(['id' => 1]);
			}catch(\Throwable $e){
				$settings = null;
			}
			$cashdrawer = new \MyWallets\Cashdrawer;
			$row = $cashdrawer->first(['id' => $id]);
			require plugin_path('views/admin/cashdrawers/edit.php');
		}else
		if(URL(2) == 'delete_cashdrawer'){
			$cashdrawer = new \MyWallets\Cashdrawer;
			$row = operations_account_get_cashdrawer_row((int)$id);
			$cashdrawer_issue_report = !empty($row) ? operations_account_cashdrawer_issue_report($row) : ['issue_count' => 0, 'issues' => []];
			$cashdrawer_dependencies = !empty($row) ? operations_account_collect_cashdrawer_dependencies($row) : ['direct_records' => [], 'ledger_refs' => []];
			require plugin_path('views/admin/cashdrawers/delete.php');
		}else
		if(URL(2) == 'transfer'){
			require plugin_path('views/admin/transfer.php');
		}else
		if(URL(2) == 'add_sim'){
			$wallets = $wallet->where(['deleted'=>0, 'transaction_status'=>'Approved']);
			require plugin_path('views/admin/sims/add.php');
		}else
		if(URL(2) == 'edit_sim'){
			$row = $sim->first(['id'=>$id]); 
			$wallets = $wallet->where(['deleted'=>0, 'transaction_status'=>'Approved']);
			require plugin_path('views/admin/sims/edit.php');
		}else
		if(URL(2) == 'view_sim'){
			$query = "
			    SELECT 
			        m.*, 
			        u.first_name AS user_first_name, 
			        u.last_name AS user_last_name, 
			        u.image AS user_image,
			        u_updated.first_name AS updated_by_first_name, 
			        u_updated.last_name AS updated_by_last_name, 
			        u_updated.image AS updated_by_image,
			        u_assigned.first_name AS assigned_first_name, 
			        u_assigned.last_name AS assigned_last_name, 
			        u_assigned.image AS assigned_image, 
			        out_wallet.name AS out_wallet_name ,
			        out_wallet.network AS out_wallet_network, 
			        out_wallet.wallet_number AS out_wallet_number,
			       in_wallet.name AS in_wallet_name ,
			       in_wallet.network AS in_wallet_network, 
			       in_wallet.wallet_number AS in_wallet_number 
			    FROM sims AS m 
			    LEFT JOIN users AS u ON m.created_by = u.id 
			    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			    LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id 
			    LEFT JOIN wallets AS out_wallet ON m.wallet_out_id = out_wallet.id 
			    LEFT JOIN wallets AS in_wallet ON m.wallet_in_id = in_wallet.id 
			    WHERE m.id = :id 
			    LIMIT 1";

				$row = $sim->query($query, ['id'=>$id])[0];
			require plugin_path('views/admin/sims/view.php');
		}else
		if(URL(2) == 'view_account'){

				$row = $mycategory->first(['id'=>$id]);

				$limit = 10;
				$pager = new \Core\Pager($limit);
				$offset = $pager->offset;
				$mywallet::$query_id = 'get-users';

				$pager = new \Core\Pager($limit);
				$mywallet->limit = $limit;
				$mywallet->offset = $offset;

				  $query = "SELECT t.*, 
				    u.last_name,
				    u.first_name,
				    u.image,
				    c.category AS parent_name
				    FROM mywallets AS t 
				    LEFT JOIN users AS u ON t.created_by = u.id
				    LEFT JOIN mycategories AS c ON t.parent_account = c.id
				    WHERE t.parent_account = :id
				    ORDER BY t.id DESC
				    LIMIT $limit OFFSET $offset";

				// Execute the query with parameters
				$rows = $mywallet->query($query, ['id' => $id]);

				//dd($rows);

				$mycategories = $mycategory->where(
					['disabled'=>0],
					['id'=>$id]
				);

				$query2 = "SELECT sum(balance) AS total_balance, count(id) as total_num from mywallets WHERE parent_account = $id";
				$total_query = $mywallet->query($query2)[0];

				$total_balance = $total_query->total_balance ?? 0;
				$total_num = $total_query->total_num ?? 0;
				//dd($rows);

			require plugin_path('views/admin/accounts/view.php');
		}else
		if(URL(2) == 'ajax_deno_history'){
			// Disable all output buffering and compression for this AJAX endpoint
			while (ob_get_level()) {
				ob_end_clean();
			}
			
			header('Content-Type: application/json; charset=utf-8');
			header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
			header('Pragma: no-cache');
			header('Expires: 0');
			header('Content-Encoding: identity');
			
			$drawerId = (int)(URL(3) ?? 0);
			if($drawerId <= 0){
				http_response_code(400);
				echo json_encode(['success' => false, 'message' => 'Invalid drawer ID']);
				flush();
				exit;
			}
			
			$drawer = null;
			try {
				$drawer = operations_account_get_cashdrawer_row($drawerId);
			} catch (\Throwable $e) {
				error_log("AJAX Deno History: Error fetching drawer $drawerId: " . $e->getMessage());
				http_response_code(500);
				echo json_encode(['success' => false, 'message' => 'Failed to load drawer']);
				flush();
				exit;
			}
			
			if (empty($drawer)) {
				error_log("AJAX Deno History: Drawer $drawerId not found");
				http_response_code(404);
				echo json_encode(['success' => false, 'message' => 'Drawer not found']);
				flush();
				exit;
			}
			
			// Permission check: allow if user is logged in and either:
			// 1. Has global wallet viewing permissions
			// 2. Owns this drawer
			try {
				$session = get_session();
				$currentUserId = (int)($session->id ?? 0);
				$drawerUserId = (int)($drawer->user_id ?? 0);
				
				$canView = user_can('view_wallet_details') || user_can('view_wallet');
				if (!$canView && $currentUserId > 0) {
					$canView = ($drawerUserId === $currentUserId);
				}
				
				if (!$canView) {
					error_log("AJAX Deno History: User $currentUserId denied access to drawer $drawerId (owner: $drawerUserId)");
					http_response_code(403);
					echo json_encode(['success' => false, 'message' => 'Access denied']);
					flush();
					exit;
				}
			} catch (\Throwable $e) {
				error_log("AJAX Deno History: Permission check error: " . $e->getMessage());
				http_response_code(500);
				echo json_encode(['success' => false, 'message' => 'Permission check failed']);
				flush();
				exit;
			}

			$page = max(1, (int)($_GET['page'] ?? 1));
			$limit = max(1, min(50, (int)($_GET['limit'] ?? 20)));
			$offset = ($page - 1) * $limit;

			try{
				$payload = operations_account_collect_deno_history($drawerId, (int)($drawer->user_id ?? 0), $offset, $limit);
				if(empty($payload)){
					echo json_encode([
						'success' => true,
						'page' => $page,
						'limit' => $limit,
						'has_more' => false,
						'items' => [],
					]);
				}else{
					$items = array_values($payload['movements'] ?? []);
					echo json_encode([
						'success' => true,
						'page' => $page,
						'limit' => $limit,
						'has_more' => !empty($payload['has_more']) && count($items) >= $limit,
						'items' => $items,
					]);
				}
				flush();
			}catch(\Throwable $e){
				error_log("AJAX Deno History: Error collecting history for drawer $drawerId: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
				http_response_code(500);
				echo json_encode(['success' => false, 'message' => 'Failed to load history']);
				flush();
			}
			exit;
		}else
		if(URL(2) == 'view_cashdrawer'){


				$query = "
				    SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image
				    FROM cashdrawers AS m 
				    LEFT JOIN users AS u ON m.user_id = u.id 
				    WHERE m.id = :id LIMIT 1
				";

				$queryResult = $wallet->query($query,['id'=>$id]);
				$row = !empty($queryResult) ? $queryResult[0] : null;
				$cashdrawer_issue_report = !empty($row) ? operations_account_cashdrawer_issue_report($row) : ['issue_count' => 0, 'issues' => []];


				$query = "
				    SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image
				    FROM cashdrawers AS m 
				    LEFT JOIN users AS u ON m.user_id = u.id 
				    WHERE m.id <> :id LIMIT 1
				";

				$cashdrawers = $wallet->query($query,['id'=>$id]);

				// Initialize variables
		$denominations = [];
		$total_volume = 0;
		$total_count = 0;
		$cash_channels = [];
		$ecash_channels = [];

		if (!empty($row)) {
			// Prefer per-drawer denominations via drawer_id, with legacy fallback.
			$drawerId   = (int)$row->id;
			$drawerDate = substr($row->date_created ?? date('Y-m-d'), 0, 10);

			// Primary: per-drawer aggregation
			$query = "SELECT denomination,
			                SUM(count)  AS count,
			                SUM(volume) AS volume
			         FROM drawer_denos
			         WHERE drawer_id = :drawer_id
			           AND user_id   = :user_id
			         GROUP BY denomination
			         ORDER BY denomination DESC";

				$denominations = $wallet->query($query, [
					'drawer_id' => $drawerId,
					'user_id'   => $row->user_id,
				]);
				if (empty($denominations)) {
					$denominations = $wallet->query(
						"SELECT denomination,
						        SUM(count)  AS count,
						        SUM(volume) AS volume
						 FROM drawer_denos
						 WHERE drawer_id = :drawer_id
						 GROUP BY denomination
						 ORDER BY denomination DESC",
						['drawer_id' => $drawerId]
					);
				}

				// Legacy fallback: same user + drawer date only
				if (empty($denominations)) {
					$query = "SELECT denomination,
				                SUM(count)  AS count,
				                SUM(volume) AS volume
				         FROM drawer_denos
				         WHERE user_id = :user_id
				           AND DATE(date_created) = :drawer_date
				         GROUP BY denomination
				         ORDER BY denomination DESC";

				$denominations = $wallet->query($query, [
					'user_id'     => $row->user_id,
					'drawer_date' => $drawerDate,
				]);
			}

				// Totals for physical cash denominations (snapshot)
				$query = "SELECT SUM(volume) AS total_volume, SUM(count) AS total_count
				          FROM drawer_denos
				          WHERE drawer_id = :drawer_id AND user_id = :user_id";
				$total_cashdrawer = $wallet->query($query, [
					'drawer_id' => $drawerId,
					'user_id'   => $row->user_id,
				])[0] ?? null;
				if (empty($total_cashdrawer)) {
					$total_cashdrawer = ($wallet->query(
						"SELECT SUM(volume) AS total_volume, SUM(count) AS total_count
						 FROM drawer_denos
						 WHERE drawer_id = :drawer_id",
						['drawer_id' => $drawerId]
					) ?: [])[0] ?? null;
				}

				// Fallback totals for legacy rows
				if (empty($total_cashdrawer)) {
					$query = "SELECT SUM(volume) AS total_volume, SUM(count) AS total_count
					          FROM drawer_denos
					          WHERE user_id = :user_id
				            AND DATE(date_created) = :drawer_date";
					$total_cashdrawer = $wallet->query($query, [
						'user_id'     => $row->user_id,
						'drawer_date' => $drawerDate,
					])[0] ?? null;
				}

				$total_volume = 0;
				$total_count  = 0;
				if ($total_cashdrawer) {
					$total_volume = (float)($total_cashdrawer->total_volume ?? 0);
					$total_count  = (float)($total_cashdrawer->total_count ?? 0);
				}

				// Normalize the displayed denomination snapshot so the view is stable even
				// when historical movement rows contain signed or incomplete values.
				$denominations = operations_account_normalize_denomination_rows($denominations, 'GHS');
				$denominations = array_values(array_filter($denominations, function($denoRow){
					return (int)($denoRow->count ?? 0) > 0 && abs((float)($denoRow->volume ?? 0)) > 0.009;
				}));
				$denomination_total_volume = 0.0;
				$denomination_total_count  = 0.0;
				foreach ($denominations as $denoRow) {
					$denomination_total_volume += (float)($denoRow->volume ?? 0);
					$denomination_total_count  += (int)($denoRow->count ?? 0);
				}
				if ($denomination_total_count > 0 || abs($denomination_total_volume) > 0.009) {
					$total_count = $denomination_total_count;
					$total_volume = $denomination_total_volume;
				}

				// Derive CASH DRAWER channel from opening amount and cumulative denominations movements
			$openingCash = (float)($row->amount ?? 0);

			// Cumulative IN / OUT from signed drawer_denos rows, excluding opening snapshot
			$inOutQuery = "SELECT 
				SUM(CASE WHEN volume > 0 THEN volume ELSE 0 END)  AS total_positive,
				SUM(CASE WHEN volume < 0 THEN -volume ELSE 0 END) AS total_negative
				FROM drawer_denos
				WHERE drawer_id = :drawer_id AND user_id = :user_id";
			$inOutRow = $wallet->query($inOutQuery, [
				'drawer_id' => $drawerId,
				'user_id'   => $row->user_id,
			])[0] ?? null;
			$totalPositive = (float)($inOutRow->total_positive  ?? 0);
			$totalNegative = (float)($inOutRow->total_negative ?? 0);
			$cashIn  = max($totalPositive - $openingCash, 0);
			$cashOut = $totalNegative;

			// Include approved/sent cash ledger movements (e.g., SIM sales) from drawer_balances
			try{
				$cashLedgerDb = new \Core\Database;
				if($cashLedgerDb->table_exists(['drawer_balances'])){
					$ledgerTotals = $cashLedgerDb->query(
						"SELECT SUM(credit) AS total_credit, SUM(debit) AS total_debit
						FROM drawer_balances
						WHERE drawer_id = :drawer_id
						  AND user_id   = :user_id
						  AND channel_type = 'cash'
						  AND (trans_status IS NULL OR trans_status IN ('Approved','Sent'))",
						[
							'drawer_id' => $row->id,
							'user_id'   => $row->user_id,
						]
					);
					if(!empty($ledgerTotals)){
						$ledgerCashIn  = (float)($ledgerTotals[0]->total_credit ?? 0);
						$ledgerCashOut = (float)($ledgerTotals[0]->total_debit ?? 0);
					}
				}
			}catch(\Throwable $e){
				// fail silently; legacy drawers still show denomination snapshot
			}

			// Closing is opening plus cumulative in minus cumulative out
			$closingCash   = $openingCash + $cashIn - $cashOut;
			$headerBalance = $closingCash;

			// Optional CFA FX snapshot for this drawer so main view can show total available cash
			// Denomination movement history (GHS + CFA) grouped logically so History sidebar always has data
			$deno_history = [];
			$opening_snapshot_history = [];
			$cashdrawer_edit_history = operations_account_cashdrawer_edit_history((int)$drawerId);
			$denoHistoryPayload = operations_account_collect_deno_history((int)$drawerId, (int)$row->user_id, 0, 20);
			$deno_history = $denoHistoryPayload['movements'] ?? [];
			$opening_snapshot_history = $denoHistoryPayload['opening'] ?? [];
			$deno_history_has_more = !empty($denoHistoryPayload['has_more']);

			// Channel exchange history (from Basic Admin exchange-ecash)
			$channel_exchange_history = [];
			try{
				$dbLog = new \Core\Database;
				if($dbLog->table_exists(['logs'])){
					$cols = [];
					try{
						$schema = $dbLog->query('SHOW COLUMNS FROM logs') ?: [];
						foreach($schema as $c){
							$cols[strtolower((string)($c->Field ?? ''))] = true;
						}
					}catch(\Throwable $e){
						$cols = [];
					}
					$select = "description, date_created";
					if(!empty($cols['user_name'])){
						$select .= ", user_name";
					}
					$needle = "%channel_exchange drawer_id={$drawerId}%";
					$channel_exchange_history = $dbLog->query(
						"SELECT {$select} FROM logs WHERE table_name = 'cashdrawer_channel_exchange' AND description LIKE :needle ORDER BY date_created DESC LIMIT 50",
						['needle' => $needle]
					) ?: [];
				}
			}catch(\Throwable $e){
				$channel_exchange_history = [];
			}
			$cfaInGhsBuy = 0.0;
			$cfaInGhsSell = 0.0;
			try{
				if(class_exists('\\Settings\\Setting') && $cfaVolume != 0.0){
					$settingsModel = new \Settings\Setting;
					$settingsRow   = $settingsModel->first(['id' => 1]);
					$buyRate  = (float)($settingsRow->buying_rate  ?? 0);
					$sellRate = (float)($settingsRow->selling_rate ?? 0);
					if($buyRate > 0 || $sellRate > 0){
						$cfaInThousands = $cfaVolume / 1000.0;
						if($buyRate > 0){
							$cfaInGhsBuy = $cfaInThousands * $buyRate;
							$combinedBuy = $closingCash + $cfaInGhsBuy;
						}
						if($sellRate > 0){
							$cfaInGhsSell = $cfaInThousands * $sellRate;
							$combinedSell = $closingCash + $cfaInGhsSell;
						}
					}
				}
			}catch(\Throwable $e){
				// ignore settings failures; cash-only view still renders
			}

			// CFA denomination breakdown from drawer_fx_denos for this drawer (if available)
			$cfa_denominations = [];
			$cfa_total_volume = 0.0;
			$cfa_total_count = 0.0;
			$cfa_denomination_total_volume = 0.0;
			$cfa_denomination_total_count = 0.0;
			$cfa_summary_opening = 0.0;
			$cfa_summary_credit = 0.0;
			$cfa_summary_debit = 0.0;
			try{
				$dbFxDenos = new \Core\Database;
				if($dbFxDenos->table_exists(['drawer_fx_denos'])){
					$cfaSummaryRows = $dbFxDenos->query(
						"SELECT
							COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(trans_status,''))) IN ('opening snapshot','opening cfa snapshot') OR trans_id = :opening_trans_id_opening THEN volume ELSE 0 END), 0) AS opening_volume,
							COALESCE(SUM(CASE WHEN volume > 0 AND NOT (LOWER(TRIM(COALESCE(trans_status,''))) IN ('opening snapshot','opening cfa snapshot') OR trans_id = :opening_trans_id_credit) THEN volume ELSE 0 END), 0) AS credit_volume,
							COALESCE(SUM(CASE WHEN volume < 0 AND NOT (LOWER(TRIM(COALESCE(trans_status,''))) IN ('opening snapshot','opening cfa snapshot') OR trans_id = :opening_trans_id_debit) THEN ABS(volume) ELSE 0 END), 0) AS debit_volume
						 FROM drawer_fx_denos
						 WHERE drawer_id = :drawer_id AND user_id = :user_id AND currency = 'CFA'",
						[
							'drawer_id' => $drawerId,
							'user_id' => $row->user_id,
							'opening_trans_id_opening' => $drawerId,
							'opening_trans_id_credit' => $drawerId,
							'opening_trans_id_debit' => $drawerId,
						]
					) ?: [];
					if(!empty($cfaSummaryRows)){
						$cfa_summary_opening = (float)($cfaSummaryRows[0]->opening_volume ?? 0);
						$cfa_summary_credit = (float)($cfaSummaryRows[0]->credit_volume ?? 0);
						$cfa_summary_debit = (float)($cfaSummaryRows[0]->debit_volume ?? 0);
					}
					$fxDenoRows = $dbFxDenos->query(
						"SELECT denomination,
						        SUM(count)  AS count,
						        SUM(volume) AS volume
						 FROM drawer_fx_denos
						 WHERE drawer_id = :drawer_id
						   AND user_id   = :user_id
						   AND currency  = 'CFA'
						 GROUP BY denomination
						 ORDER BY denomination DESC",
						[
							'drawer_id' => $drawerId,
							'user_id'   => $row->user_id,
						]
					);
					if(empty($fxDenoRows)){
						$fxDenoRows = $dbFxDenos->query(
							"SELECT denomination,
							        SUM(count)  AS count,
							        SUM(volume) AS volume
							 FROM drawer_fx_denos
							 WHERE drawer_id = :drawer_id
							 GROUP BY denomination
							 ORDER BY denomination DESC",
							[
								'drawer_id' => $drawerId,
							]
						);
					}
					if(!empty($fxDenoRows)){
						$cfa_denominations = operations_account_normalize_denomination_rows($fxDenoRows, 'CFA');
						$cfa_denominations = array_values(array_filter($cfa_denominations, function($denoRow){
							return (int)($denoRow->count ?? 0) > 0 && abs((float)($denoRow->volume ?? 0)) > 0.009;
						}));
						$cfa_total_volume = 0.0;
						$cfa_total_count  = 0.0;
						foreach($fxDenoRows as $fxRow){
							$cfa_total_volume += (float)($fxRow->volume ?? 0);
							$cfa_total_count  += (float)($fxRow->count  ?? 0);
						}
						$cfa_denomination_total_volume = 0.0;
						$cfa_denomination_total_count  = 0.0;
						foreach ($cfa_denominations as $cfaRow) {
							$cfa_denomination_total_volume += (float)($cfaRow->volume ?? 0);
							$cfa_denomination_total_count  += (int)($cfaRow->count ?? 0);
						}
					}
				}
			}catch(\Throwable $e){
				// ignore FX denoms failures; GHS view still renders
			}

			$cash_channels[] = (object) [
				'channel_type'    => 'cash',
				'channel_key'     => 'CASH_DRAWER',
				'opening_balance' => $openingCash,
				'credit'          => $cashIn,
				'debit'           => $cashOut,
				'closing_balance' => $closingCash,
				'trans_status'    => 'Sent',
			];

			// CASH_CFA channel from drawer_balances (FX cash in CFA) - any status
			$hasCfaChannel = false;
			try{
				$db_fx_cash = new \Core\Database;
				if($db_fx_cash->table_exists(['drawer_balances'])){
					$fx_cash_rows = $wallet->query(
						"SELECT channel_type, channel_key, opening_balance, credit, debit, closing_balance, trans_status, trans_id
						 FROM drawer_balances
						 WHERE drawer_id = :drawer_id
						   AND user_id   = :user_id
						   AND channel_type = 'cash'
							   AND channel_key  = 'CASH_CFA'
							 ORDER BY id DESC
							 LIMIT 1",
						[
							'drawer_id' => $row->id,
							'user_id'   => $row->user_id,
						]
					);
					if(!empty($fx_cash_rows)){
						$hasCfaChannel = true;
						foreach($fx_cash_rows as $fx_cash){
							$opening = $cfa_summary_opening;
							$credit  = $cfa_summary_credit;
							$debit   = $cfa_summary_debit;
							$closing = $opening + $credit - $debit;
							$fx_cash->opening_balance = $opening;
							$fx_cash->credit          = $credit;
							$fx_cash->debit           = $debit;
							$fx_cash->closing_balance = $closing;
							if(empty($fx_cash->trans_status)){
								$fx_cash->trans_status = 'Sent';
							}
							$cash_channels[] = $fx_cash;
						}
					}
				}
			}catch(\Throwable $e){
				// ignore FX cash channel failures
			}
			// If no CASH_CFA row but CFA totals exist, synthesize a virtual CASH_CFA channel
			if(!$hasCfaChannel && $cfa_total_volume > 0){
				$cash_channels[] = (object) [
					'channel_type'    => 'cash',
					'channel_key'     => 'CASH_CFA',
					'opening_balance' => $cfa_summary_opening,
					'credit'          => $cfa_summary_credit,
					'debit'           => $cfa_summary_debit,
					'closing_balance' => $cfa_summary_opening + $cfa_summary_credit - $cfa_summary_debit,
					'trans_status'    => 'Sent',
				];
			}

			// Fetch draft/pending cash channels from drawer_balances
			try{
				$db_cash = new \Core\Database;
				if($db_cash->table_exists(['drawer_balances'])){
					$cash_draft_query = "SELECT channel_type, channel_key, opening_balance, credit, debit, closing_balance, trans_status, trans_id
						FROM drawer_balances
						WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'cash' AND channel_key <> 'CASH_CFA' AND trans_status IN ('Draft', 'Pending')";
					$cash_draft_rows = $wallet->query($cash_draft_query,[
						'drawer_id' => $row->id,
						'user_id'   => $row->user_id,
					]);
					if(!empty($cash_draft_rows)){
						foreach($cash_draft_rows as $cash_draft){
							$opening = (float)($cash_draft->opening_balance ?? 0);
							$credit  = (float)($cash_draft->credit ?? 0);
							$debit   = (float)($cash_draft->debit ?? 0);
							$closing = $opening + $credit - $debit;
							$cash_draft->closing_balance = $closing;
							$cash_draft = operations_account_enrich_drawer_entry($cash_draft);
							$cash_channels[] = $cash_draft;
						}
					}
				}
			}catch(\Throwable $e){
				// fail silently
			}
				// Show local e-cash plus shared bank and MTN MoMoPay channels.
				try{
					$db_channels = new \Core\Database;
					if($db_channels->table_exists(['drawer_balances'])){
						$bal_query = "SELECT b.channel_type, b.channel_key, b.opening_balance, b.credit, b.debit, b.closing_balance, b.trans_status, b.trans_id, b.proof_image
							FROM drawer_balances b
							INNER JOIN (
								SELECT channel_key, MAX(id) AS max_id
								FROM drawer_balances
								WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash'
								GROUP BY channel_key
							) last_row ON last_row.max_id = b.id";
						$bal_rows = $wallet->query($bal_query, [
							'drawer_id' => $row->id,
							'user_id' => $row->user_id,
						]) ?: [];
						$normalizeChannelKey = static function ($key): string {
							$key = strtoupper(trim((string)$key));
							$key = preg_replace('/^ECASH[_-]*/', '', $key);
							$key = str_replace(['_', ' '], '-', (string)$key);
							return trim((string)preg_replace('/-+/', '-', (string)$key), '-');
						};
						$finalizeChannel = static function ($channel, bool $isShared = false) {
							$channel->opening_balance = (float)($channel->opening_balance ?? 0);
							$channel->credit = (float)($channel->credit ?? 0);
							$channel->debit = (float)($channel->debit ?? 0);
							$channel->closing_balance = $channel->opening_balance + $channel->credit - $channel->debit;
							$channel->trans_status = $channel->trans_status ?: 'Sent';
							if (operations_account_is_momo_pay_channel_key((string)($channel->channel_key ?? ''))) {
								$channel->channel_key = 'MTN_MOMOPAY';
							}
							$channel->is_shared_account = $isShared;
							return operations_account_enrich_drawer_entry($channel);
						};
						$canSeeSharedAccounts = user_can('is_admin');

						$localSharedChannels = [];
						$localNonSharedChannels = [];
						foreach ($bal_rows as $bal) {
							$key = $normalizeChannelKey($bal->channel_key ?? '');
							if (in_array($key, ['EZWICH', 'E-ZWICH', 'CASH-DRAWER', 'CASH-CFA'], true)) {
								continue;
							}
							$isSharedChannel = operations_account_is_shared_channel_key($key);
							$bal = $finalizeChannel($bal, $isSharedChannel);
							if ($isSharedChannel) {
								if ($canSeeSharedAccounts) {
									$localSharedChannels[] = $bal;
								}
								continue;
							}
							$localNonSharedChannels[] = $bal;
						}
						$ecash_channels = $localNonSharedChannels;

						if ($canSeeSharedAccounts) {
							$sharedChannels = [];
							$sharedRoleNames = [
								'admin', 'is_admin', 'manager', 'is_manager',
								'general manager', 'general_manager', 'generalmanager',
								'gm', 'is_general_manager', 'operations manager',
								'operations_manager', 'operationsmanager', 'op manager', 'op_manager',
							];
							$roleParams = [];
							$rolePlaceholders = [];
							foreach ($sharedRoleNames as $index => $roleName) {
								$param = 'shared_role_' . $index;
								$roleParams[$param] = strtolower($roleName);
								$rolePlaceholders[] = ':' . $param;
							}
							$sharedSql = "SELECT b.channel_type, b.channel_key, b.opening_balance, b.credit, b.debit, b.closing_balance, b.trans_status, b.trans_id, b.proof_image
								FROM drawer_balances b
								INNER JOIN (
									SELECT db.channel_key,
										COALESCE(
											MAX(CASE WHEN LOWER(COALESCE(db.trans_status, '')) <> 'opening snapshot' THEN db.id END),
											MAX(db.id)
										) AS max_id
									FROM drawer_balances db
									INNER JOIN cashdrawers cd ON cd.id = db.drawer_id
									LEFT JOIN user_roles_map urm ON urm.user_id = cd.user_id AND (urm.disabled = 0 OR urm.disabled IS NULL)
									LEFT JOIN user_roles r ON r.id = urm.role_id AND (r.disabled = 0 OR r.disabled IS NULL)
									WHERE db.channel_type = 'ecash'
										AND DATE(cd.date_created) = :shared_today
										AND (
											LOWER(r.role) IN (" . implode(',', $rolePlaceholders) . ")
											OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(db.channel_key)), 'ECASH_', ''), 'ECASH-', ''), '_', ''), '-', ''), ' ', '') IN ('MTNMOMOPAY', 'MTNMOMOPAYOUT')
										)
									GROUP BY db.channel_key
								) last_row ON last_row.max_id = b.id";
							$sharedRows = $wallet->query($sharedSql, array_merge(
								['shared_today' => date('Y-m-d')],
								$roleParams
							)) ?: [];
							if (!empty($wallet->has_error)) {
								throw new \RuntimeException('Unable to load shared cashdrawer accounts: ' . (string)$wallet->error);
							}
							foreach ($sharedRows as $bal) {
								$key = $normalizeChannelKey($bal->channel_key ?? '');
								if (in_array($key, ['EZWICH', 'E-ZWICH', 'CASH-DRAWER', 'CASH-CFA'], true)
									|| !operations_account_is_shared_channel_key($key)) {
									continue;
								}
								$sharedChannels[] = $finalizeChannel($bal, true);
							}
							$ecash_channels = array_merge(
								$ecash_channels,
								!empty($sharedChannels) ? $sharedChannels : $localSharedChannels
							);
						}

						$uniqueChannels = [];
						foreach ($ecash_channels as $channel) {
							$key = $normalizeChannelKey($channel->channel_key ?? '');
							if ($key !== '' && !isset($uniqueChannels[$key])) {
								$uniqueChannels[$key] = $channel;
							}
						}
						$ecash_channels = array_values($uniqueChannels);
					}
				}catch(\Throwable $e){
					error_log('[operations_account] Shared cashdrawer channels could not be loaded: ' . $e->getMessage());
				}

			}

			$limit = 10;
			$pager = new \Core\Pager($limit);
			$offset = $pager->offset;
			$mywallet::$query_id = 'get-users';

			$pager = new \Core\Pager($limit);
			$mywallet->limit = $limit;
			$mywallet->offset = $offset;
			$transaction->limit = $limit;
			$transaction->offset = $offset;
			$transaction::$query_id = 'get-users-transactions';

		  $query = "SELECT t.*, 
				    u.last_name,
				    u.first_name,
				    u.image
				    FROM transactions AS t 
				    LEFT JOIN users AS u ON t.created_by = u.id
				    WHERE t.wallet_id = :id
				    ORDER BY t.id DESC
				    LIMIT $limit OFFSET $offset";

				// Execute the query with parameters
				$rows = $transaction->query($query, ['id' => $id]);

				//dd($rows);

				$mywallets = $mywallet->where(
					['deleted'=>0, 'transaction_status'=>'Approved'],
					['id'=>$id]
				);

				$query2 = "SELECT sum(balance) AS total_balance, count(id) as total_num from mywallets";
				$total_query = $mywallet->query($query2)[0];

				$total_balance = $total_query->total_balance ?? 0;
				$total_num = $total_query->total_num ?? 0;
				//dd($rows);

			require plugin_path('views/admin/cashdrawers/view.php');
		}else
		if(URL(2) == 'export_cashdrawer_pdf'){
			// Load standalone PDF export controller
			require plugin_path('controllers/export_pdf-controller.php');
		}else
		if(URL(2) == 'ajax_export_deno_history_pdf'){
			// AJAX PDF export of denomination history
			header('Content-Type: application/json');
			$drawerId = (int)(URL(3) ?? 0);
			if($drawerId <= 0){
				http_response_code(400);
				echo json_encode(['success' => false, 'message' => 'Invalid drawer ID']);
				exit;
			}
			
			try{
				$drawer = operations_account_get_cashdrawer_row($drawerId);
				if(empty($drawer)){
					http_response_code(404);
					echo json_encode(['success' => false, 'message' => 'Drawer not found']);
					exit;
				}
				
				$session = get_session();
				$currentUserId = (int)($session->id ?? 0);
				$drawerUserId = (int)($drawer->user_id ?? 0);
				$canView = user_can('view_wallet_details') || user_can('view_wallet');
				if(!$canView && $currentUserId > 0){
					$canView = ($drawerUserId === $currentUserId);
				}
				if(!$canView){
					http_response_code(403);
					echo json_encode(['success' => false, 'message' => 'Access denied']);
					exit;
				}
				
				// Get all history (no pagination)
				$historyData = operations_account_collect_deno_history($drawerId, $drawerUserId, 0, 999);
				
				// Prepare PDF data
				$pdfData = [
					'drawer' => $drawer,
					'history' => $historyData['movements'],
					'total_movements' => $historyData['total_movements'],
				];
				
				// Load PDF generator controller
				require plugin_path('controllers/export_deno_history_pdf-controller.php');
			}catch(\Throwable $e){
				http_response_code(500);
				echo json_encode(['success' => false, 'message' => $e->getMessage()]);
				exit;
			}
		}else
		if(URL(2) == 'assigned'){
			$mywallet = new \MyWallets\Mywallet;
					$sim = new \MyWallets\Sim;
			$user = new \MyWallets\User;
			$mywallet_roles_map = new \MyWallets\User_roles_map;
			$limit = 10;
			$pager = new \Core\Pager($limit);
			$offset = $pager->offset;

			$mywallet->limit = $limit;
			$mywallet->offset = $offset;
			$mywallet::$query_id = 'get-users';
			
			if (!empty($_GET['find'])) {
			    $find = '%' . trim($_GET['find']) . '%';
			    $query = "
			        SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name, 
				        u_updated.last_name AS updated_by_last_name, 
				        u_updated.image AS updated_by_image,
			            u_assigned.first_name AS assigned_first_name, 
			            u_assigned.last_name AS assigned_last_name, 
			            u_assigned.image AS assigned_image, 
			            c.category AS category_name
				    FROM mywallets AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			        LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id
			         WHERE (
			        	(m.assigned = $user_id  AND m.transaction_status = 'Approved')
			        	AND network LIKE :find 
			            OR id LIKE :find 
			            OR transaction_type LIKE :find 
			            OR u.first_name LIKE :find 
			            OR u.last_name LIKE :find 
			            OR phone LIKE :find
			            OR transaction_status LIKE :find
			            OR date_created LIKE :find)
			        LIMIT $limit OFFSET $offset";

			    $rows = $mywallet->query($query, ['find' => $find]);

				// Define the search term
					$find = '%' . trim($_GET['find'] ?? '') . '%';

					$query2 = "SELECT SUM(balance) AS total_balance FROM mywallets WHERE assigned = :assigned";

					// Run the query
					$total_query = $mywallet->query($query2, ['assigned'=>$user_id]);

					// Check if the query was successful and not empty
					if ($total_query && isset($total_query[0])) {
					    $total_balance = $total_query[0]->total_balance ?? 0;
					} else {
					    $total_balance = 0; // Default to 0 if query failed or returned no rows
					}
			  }
			else{
				$query = "
				    SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name, 
				        u_updated.last_name AS updated_by_last_name, 
				        u_updated.image AS updated_by_image,
			            u_assigned.first_name AS assigned_first_name, 
			            u_assigned.last_name AS assigned_last_name, 
			            u_assigned.image AS assigned_image,
			            c.category AS category_name 
				    FROM mywallets AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			        LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id
			         WHERE m.assigned = $user_id  AND m.transaction_status = 'Approved'
				    ORDER BY m.id DESC
				";

				$rows = $mywallet->query($query);
				//dd($rows);
				$query2 = "SELECT sum(balance) AS total_balance from mywallets WHERE assigned = $user_id";
				$total_query = $mywallet->query($query2);

				$total_balance = $total_query[0]->total_balance ?? 0;
			}
			
			require plugin_path('views/admin/assigned.php');
		}else
		{
			$mywallet = new \MyWallets\Mywallet;
			$myaccount = new \MyWallets\Myaccount;
			$category = new \MyWallets\Category;
			$sim = new \MyWallets\Sim;
			$user = new \MyWallets\User;
			$mywallet_roles_map = new \MyWallets\User_roles_map;
			$limit = 10;
			$pager = new \Core\Pager($limit);
			$offset = $pager->offset;

			$mywallet->limit = $limit;
			$mywallet->offset = $offset;
			$user::$query_id = 'get-users';

			if (!empty($_GET['find'])) {
			    $find = '%' . trim($_GET['find']) . '%';
			    if ($_GET['from'] == 'mywallet') {
			    	$query = "
			        SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name, 
				        u_updated.last_name AS updated_by_last_name, 
				        u_updated.image AS updated_by_image,
			            u_assigned.first_name AS assigned_first_name, 
			            u_assigned.last_name AS assigned_last_name, 
			            u_assigned.image AS assigned_image,
			            c.category AS parent_name 
				    FROM mywallets AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			        LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id
			        LEFT JOIN mycategories AS c ON m.parent_account = c.id
			         WHERE (
			        	(m.assigned = $user_id OR m.created_by = $user_id  AND m.transaction_status = 'Approved')
			        	AND network LIKE :find 
			            OR id LIKE :find 
			            OR transaction_type LIKE :find 
			            OR u.first_name LIKE :find 
			            OR u.last_name LIKE :find 
			            OR phone LIKE :find
			            OR transaction_status LIKE :find
			            OR date_created LIKE :find)
			        LIMIT $limit OFFSET $offset";

				    $walletsRows = $mywallet->query($query, ['find' => $find]);
				    //dd($_GET);
					// Define the search term
					$find = '%' . trim($_GET['find'] ?? '') . '%';

					
					$query2 = "SELECT sum(balance) AS total_balance from mywallets WHERE (
			        	(m.assigned = $user_id  AND m.transaction_status = 'Approved')
			        	AND network LIKE :find 
			            OR id LIKE :find 
			            OR transaction_type LIKE :find 
			            OR u.first_name LIKE :find 
			            OR u.last_name LIKE :find 
			            OR phone LIKE :find
			            OR transaction_status LIKE :find
			            OR date_created LIKE :find)";
					$total_query = $mywallet->query($query2);
					if (!empty($total_query)) {
						$wallets_balance = $total_query->total_balance ?? 0;
					}else{
						$wallets_balance =  0;
					}
			    }else
			    if ($_GET['from'] == 'accounts') {
			    	$find = '%' . trim($_GET['find']) . '%';
					$query = "select * from mycategories where (category like :find) limit $limit offset $offset";
					$accountRows = $mycategory->query($query,['find'=>$find]);

					$query = "SELECT SUM(balance) AS total_balance from mycategories ";
					$total = $mycategory->query($query);
					if (!empty($total)) {
						$total_balance = $total[0]->total_balance ?? 0;
					}
			    }else
			    if ($_GET['from'] == 'myaccounts') {
			    	$query = "
			        SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name, 
				        u_updated.last_name AS updated_by_last_name, 
				        u_updated.image AS updated_by_image,
			            u_assigned.first_name AS assigned_first_name, 
			            u_assigned.last_name AS assigned_last_name, 
			            u_assigned.image AS assigned_image, 
			            c.category AS category_name
				    FROM myaccounts AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			        LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id
			         WHERE (
			        	(m.assigned = $user_id  AND m.transaction_status = 'Approved')
			        	AND network LIKE :find 
			            OR id LIKE :find 
			            OR transaction_type LIKE :find 
			            OR u.first_name LIKE :find 
			            OR u.last_name LIKE :find 
			            OR phone LIKE :find
			            OR transaction_status LIKE :find
			            OR date_created LIKE :find)
			        LIMIT $limit OFFSET $offset";

				    $accountsRows = $mywallet->query($query, ['find' => $find]);
				    //dd($_GET);
					// Define the search term
					$find = '%' . trim($_GET['find'] ?? '') . '%';

					$query2 = "SELECT sum(balance) AS total_balance from myaccounts WHERE (
			        	(m.assigned = $user_id  AND m.transaction_status = 'Approved')
			        	AND network LIKE :find 
			            OR id LIKE :find 
			            OR transaction_type LIKE :find 
			            OR u.first_name LIKE :find 
			            OR u.last_name LIKE :find 
			            OR phone LIKE :find
			            OR transaction_status LIKE :find
			            OR date_created LIKE :find)";
					$total_query = $myaccount->query($query2);
					if (!empty($total_query)) {
						$accounts_balance = $total_query->total_balance ?? 0;
					}else{
						$accounts_balance =  0;
					}
			    }
			  }
			else{

				$query = "
				    SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name, 
				        u_updated.last_name AS updated_by_last_name, 
				        u_updated.image AS updated_by_image,
			            u_assigned.first_name AS assigned_first_name, 
			            u_assigned.last_name AS assigned_last_name, 
			            u_assigned.image AS assigned_image,
			            c.category AS parent_name 
				    FROM mywallets AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			        LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id
			        LEFT JOIN mycategories AS c ON m.parent_account = c.id
			         WHERE m.created_by = $user_id
			        	OR m.assigned = $user_id
				    ORDER BY m.id DESC
				";

				$walletsRows = $mywallet->query($query);
				//dd($rows);
				$query2 = "SELECT sum(balance) AS total_balance from mywallets WHERE deleted = 0 AND created_by = $user_id OR assigned = $user_id";
				$total_query = $mywallet->query($query2)[0];

				$wallets_balance = $total_query->total_balance ?? 0;

				$query = "
				    SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name,
				        u_updated.last_name AS updated_by_last_name,
				        u_updated.image AS updated_by_image,
				        u_assigned.first_name AS assigned_first_name, 
				        u_assigned.last_name AS assigned_last_name, 
				        u_assigned.image AS assigned_image 
				    FROM cashdrawers AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id
				    LEFT JOIN users AS u_assigned ON m.user_id = u_assigned.id 
				    ORDER BY m.id DESC LIMIT $limit OFFSET $offset
				";

				$cashdrawers = $sim->query($query);

				// Query for total SIMs by network
				$query = "SELECT sum(amount) AS total_cashdrawer 
				          FROM cashdrawers 
				         ";

				$total_cashdrawer = $sim->query($query)[0];
				$cashdrawers_total =  0;
				if (!empty($total_cashdrawer)) {
					$cashdrawers_total = $total_cashdrawer->total_cashdrawer ?? 0;
				}
				$total_cashdrawers_row = $sim->query("SELECT COUNT(id) AS total_rows FROM cashdrawers")[0] ?? null;
				$total_cashdrawers = (int)($total_cashdrawers_row->total_rows ?? 0);
				if (isset($pager) && $limit > 0) {
					$pager->total_pages = max(1, (int)ceil($total_cashdrawers / $limit));
				}

				$query = "
				    SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name, 
				        u_updated.last_name AS updated_by_last_name, 
				        u_updated.image AS updated_by_image,
				        u_assigned.first_name AS assigned_first_name, 
				        u_assigned.last_name AS assigned_last_name, 
				        u_assigned.image AS assigned_image 
				    FROM sims AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
				    LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id 
				    ORDER BY m.id DESC LIMIT $limit OFFSET $offset
				";

				$sims = $sim->query($query);

				// Query for total SIMs by network
				$query = "SELECT network, COUNT(id) AS total_count 
				          FROM sims 
				          WHERE (assigned = :user_id OR created_by = :user_id) 
				          AND deleted = 0 
				          GROUP BY network";

				$total_countSim = $sim->query($query, [
				    'user_id' => $user_id
				]);

				// Query for sold SIMs by network
				$query = "SELECT network, COUNT(id) AS total_count 
				          FROM sims 
				          WHERE (assigned = :user_id OR created_by = :user_id) 
				          AND deleted = 0 
				          AND (transaction_status = :transaction_status1 OR transaction_status = :transaction_status2) 
				          GROUP BY network";

				$total_countSold = $sim->query($query, [
				    'user_id' => $user_id,
				    'transaction_status1' => 'Sold - retail',
				    'transaction_status2' => 'Sold - wholesale'
				]);

				// Merge results for display
				$networks = [];
				if (!empty($total_countSim)) {
				    foreach ($total_countSim as $row) {
				        $networks[$row->network]['total'] = $row->total_count;
				    }
				}

				if (!empty($total_countSold)) {
				    foreach ($total_countSold as $row) {
				        $networks[$row->network]['sold'] = $row->total_count;
				    }
				}

				// Ensure all networks have "sold" initialized to 0 if not present
				foreach ($networks as $network => $data) {
				    $networks[$network]['sold'] = $data['sold'] ?? 0;
				}

				//for superior ranks
				$query = "SELECT COUNT(id) AS total_count, SUM(amount) AS total_amount, SUM(commission) AS total_commission FROM sims WHERE deleted = 0 AND transaction_status = :transaction_status";
				$total_unassigned = $sim->query($query, ['transaction_status'=>'Unassigned']);
				if (!empty($total_unassigned)) {			
					$totalUnassigned_amount = $total_unassigned[0]->total_amount ?? 0;
					$totalUnassigned_commission = $total_unassigned[0]->total_commission ?? 0;
					$totalUnassigned_count = $total_unassigned[0]->total_count ?? 0;
				}

				$total_assigned = $sim->query($query, ['transaction_status'=>'Assigned']);
				if (!empty($total_assigned)) {
					$totalAssigned_amount = $total_assigned[0]->total_amount ?? 0;
					$totalAssigned_commission = $total_assigned[0]->total_commission ?? 0;
					$totalAssigned_count = $total_assigned[0]->total_count ?? 0;
				}

				$total_soldWholesale = $sim->query($query, ['transaction_status'=>'Sold - wholesale']);
				if (!empty($total_soldWholesale)) {				
					$totalSoldWholesale_amount = $total_soldWholesale[0]->total_amount ?? 0;
					$totalSoldWholesale_commission = $total_soldWholesale[0]->total_commission ?? 0;
					$totalSoldWholesale_count = $total_soldWholesale[0]->total_count ?? 0;
				}

				$total_soldRetail = $sim->query($query, ['transaction_status'=>'Sold - retail']);
				if (!empty($total_soldRetail)) {
					$totalSoldRetail_amount = $total_soldRetail[0]->total_amount ?? 0;
					$totalSoldRetail_commission = $total_soldRetail[0]->total_commission ?? 0;
					$totalSoldRetail_count = $total_soldRetail[0]->total_count ?? 0;
				}

				$total_expired = $sim->query($query, ['transaction_status'=>'Expired']);
				if (!empty($total_expired)) {
					$totalExpired_amount = $total_expired[0]->total_amount ?? 0;
					$totalExpired_commission = $total_expired[0]->total_commission ?? 0;
					$totalExpired_count = $total_expired[0]->total_count ?? 0;
				}
				
				$query = "SELECT COUNT(id) AS total_count, SUM(amount) AS total_amount, SUM(commission) AS total_commission FROM sims WHERE deleted = 0";
				$total = $sim->query($query);
				if (!empty($total)) {
					$total_amount = $total[0]->total_amount ?? 0;
					$total_commission = $total[0]->total_commission ?? 0;
					$total_count = $total[0]->total_count ?? 0;
				}

				$query = "
				    SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name, 
				        u_updated.last_name AS updated_by_last_name, 
				        u_updated.image AS updated_by_image,
			            u_assigned.first_name AS assigned_first_name, 
			            u_assigned.last_name AS assigned_last_name, 
			            u_assigned.image AS assigned_image,
			            c.category AS category_name 
				    FROM myaccounts AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			        LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id
			         WHERE m.created_by = $user_id
			        	OR m.assigned = $user_id
				    ORDER BY m.id DESC
				";

				$myaccountsRows = $myaccount->query($query);
				//dd($_GET);
				$query2 = "SELECT sum(balance) AS total_balance from myaccounts WHERE deleted = 0 AND created_by = $user_id OR assigned = $user_id";
				$total_query = $myaccount->query($query2)[0];

				$myaccounts_balance = $total_query->total_balance ?? 0;


				$limit = 10;
				$pager = new \Core\Pager($limit);
				$offset = $pager->offset;

				$mycategory->limit = $limit;
				$mycategory->offset = $offset;
				
				$accountRows = $mycategory->getAll();

				// Query for assigned funds in the "categories" table
				$query = "
				    SELECT SUM(balance) AS total_balance 
				    FROM categories 
				    WHERE assigned = :assigned
				";
				$assignedFundsData = $category->query($query, ['assigned' => $user_id]);

				// Default to 0 if no rows are found
				$assignedFunds = $assignedFundsData[0]->total_balance ?? 0;

				// Query for total balance in the "mycategories" table
				$query = "SELECT SUM(balance) AS total_balance FROM mycategories";
				$totalBalanceData = $mycategory->query($query);

				// Default to 0 if no rows are found
				$total_balance = $totalBalanceData[0]->total_balance ?? 0;

				// Calculate the remaining assigned funds
				$assignedRemaining = $assignedFunds - $total_balance;

				// Debugging (remove in production)
				error_log("Assigned Funds: $assignedFunds, Total Balance: $total_balance, Assigned Remaining: $assignedRemaining");


			}

			require plugin_path('views/admin/list.php');
		}

	}
});

// Central hook for updating cashdrawer channel balances
add_action('cashdrawer_update', function($args) {
	try{
		// Basic argument validation
		$drawer_id    = isset($args['drawer_id']) ? (int)$args['drawer_id'] : 0;
		$amount       = isset($args['amount']) ? (float)$args['amount'] : 0;
		$direction    = isset($args['direction']) ? strtolower(trim($args['direction'])) : '';
		$channel_type = isset($args['channel_type']) ? trim($args['channel_type']) : '';
		$channel_key  = isset($args['channel_key']) ? trim($args['channel_key']) : '';

		if($drawer_id <= 0 || $amount <= 0 || !$direction || !$channel_type || !$channel_key){
			return; // invalid payload; do nothing
		}

		// Only support in/out directions
		if($direction !== 'in' && $direction !== 'out'){
			return;
		}

		$db = new \Core\Database;
		if(!$db->table_exists(['drawer_balances'])){
			return; // table not available
		}

		// Resolve user_id from the cashdrawer header to keep it consistent everywhere
		$user_id = 0;
		$drawerRow = $db->query("SELECT user_id FROM cashdrawers WHERE id = :id LIMIT 1", ['id' => $drawer_id]);
		if(!empty($drawerRow)){
			$user_id = (int)($drawerRow[0]->user_id ?? 0);
		}
		if($user_id <= 0){
			return; // cannot resolve drawer owner
		}

		// Look for existing balance row for this drawer + channel
		$sql = "SELECT * FROM drawer_balances
		        WHERE drawer_id = :drawer_id
		          AND user_id = :user_id
		          AND channel_type = :channel_type
		          AND channel_key = :channel_key
		        LIMIT 1";
		$rows = $db->query($sql, [
			'drawer_id'    => $drawer_id,
			'user_id'      => $user_id,
			'channel_type' => $channel_type,
			'channel_key'  => $channel_key,
		]);

		$now = date('Y-m-d H:i:s');

		if(empty($rows)){
			// Insert new row using raw SQL (same pattern as opening balance seeding)
			$opening = 0;
			$credit  = 0;
			$debit   = 0;
			if($direction === 'in'){
				$credit = $amount;
			}else{
				$debit = $amount;
			}
			$closing = $opening + $credit - $debit;

			$insertSql = "INSERT INTO drawer_balances
				(drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created)
				VALUES
				(:drawer_id, :user_id, :channel_type, :channel_key, :opening_balance, :credit, :debit, :closing_balance, :date_created)";
			$params = [
				'drawer_id'       => $drawer_id,
				'user_id'         => $user_id,
				'channel_type'    => $channel_type,
				'channel_key'     => $channel_key,
				'opening_balance' => $opening,
				'credit'          => $credit,
				'debit'           => $debit,
				'closing_balance' => $closing,
				'date_created'    => $now,
			];

			$db->query($insertSql, $params);
		}else{
			// Update existing row using raw SQL
			$row = $rows[0];
			$opening = (float)($row->opening_balance ?? 0);
			$credit  = (float)($row->credit ?? 0);
			$debit   = (float)($row->debit ?? 0);

			if($direction === 'in'){
				$credit += $amount;
			}else{
				$debit += $amount;
			}

			$closing = $opening + $credit - $debit;

			$updateSql = "UPDATE drawer_balances
				SET credit = :credit,
				    debit = :debit,
				    closing_balance = :closing_balance
				WHERE id = :id";
			$params = [
				'credit'          => $credit,
				'debit'           => $debit,
				'closing_balance' => $closing,
				'id'              => $row->id,
			];

			$db->query($updateSql, $params);
		}
	}catch(\Throwable $e){
		// Fail silently; we never break the main transaction flow
		return;
	}
});

/** for manipulating data after a query operation **/
add_filter('after_query',function($data){

	
	if(empty($data['result']))
		return $data;

	if($data['query_id'] == 'get-users')
	{
		$role_map = new \MyWallets\User_roles_map;
		foreach ($data['result'] as $key => $row) {
			
			$query = "select * from user_roles where disabled = 0 && id in (select role_id from user_roles_map where disabled = 0 && user_id = :user_id)";
			$roles = $role_map->query($query,['user_id'=>$row->id]);
			if($roles)
				$data['result'][$key]->roles = array_column($roles, 'role');
			
			/** get mywallet's roles **/
			$mywallet_roles_map = new \MyWallets\User_roles_map;
				
			$role_ids = $mywallet_roles_map->where(['user_id'=>$row->id,'disabled'=>0]);
			if($role_ids)
				$data['result'][$key]->role_ids = array_column($role_ids, 'role_id');

		}
	}

	return $data;
});

<?php

namespace CashpickupCRM;

function normalizePhone($value): ?string
{
	$digits = preg_replace('/\D+/', '', trim((string)$value));
	if ($digits === '') return null;
	if (strpos($digits, '00') === 0) $digits = substr($digits, 2);
	if (strpos($digits, '233') === 0 && strlen($digits) >= 12) return '+233' . substr($digits, 3);
	if (strlen($digits) === 10 && $digits[0] === '0') return '+233' . substr($digits, 1);
	if (strlen($digits) === 9) return '+233' . $digits;
	return '+' . $digits;
}

function phoneVariants($value): array
{
	$normalized = normalizePhone($value);
	if ($normalized === null) return [];
	$digits = preg_replace('/\D+/', '', $normalized);
	$variants = [$normalized, $digits];
	if (strpos($digits, '233') === 0) {
		$variants[] = '0' . substr($digits, 3);
		$variants[] = substr($digits, 3);
		$variants[] = '233' . substr($digits, 3);
	}
	return array_values(array_unique(array_filter($variants, static fn($v) => trim((string)$v) !== '')));
}

function crmServiceDefinitions(): array
{
	return [
		'agencybankings' => ['label' => 'Agency Banking', 'phone_columns' => ['phone', 'sender_phone', 'receiver'], 'text_columns' => ['full_name', 'account_number', 'ussd', 'network', 'transaction_type'], 'amount_columns' => ['amount']],
		'airtimebundles' => ['label' => 'Airtime Bundle', 'phone_columns' => ['phone', 'receiver', 'reseller_number'], 'text_columns' => ['network', 'transaction_type', 'category', 'data_size'], 'amount_columns' => ['amount']],
		'momoremits' => ['label' => 'Momo Remit', 'phone_columns' => ['phone', 'receiver'], 'text_columns' => ['network', 'transaction_type', 'category', 'token', 'id_number'], 'amount_columns' => ['amount']],
		'payments' => ['label' => 'Payment', 'phone_columns' => ['phone_number', 'phone', 'receiver'], 'text_columns' => ['description', 'transaction_status'], 'amount_columns' => ['amount']],
		'payment_services' => ['label' => 'Payment Service', 'phone_columns' => ['phone_number', 'phone', 'receiver'], 'text_columns' => ['description', 'service', 'network', 'transaction_status'], 'amount_columns' => ['amount']],
		'reversals' => ['label' => 'Reversal', 'phone_columns' => ['phone_number', 'phone', 'sender_phone', 'receiver_phone'], 'text_columns' => ['sender_details', 'receiver_details', 'reason', 'network', 'service_type'], 'amount_columns' => ['amount']],
		'sims' => ['label' => 'SIM', 'phone_columns' => ['buyer_phone', 'number_assigned', 'phone_number', 'phone'], 'text_columns' => ['network', 'puk', 'serial_number', 'transaction_type'], 'amount_columns' => ['amount', 'retail_price']],
		'cashpickups' => ['label' => 'Cash Pickup', 'phone_columns' => ['phone_number', 'pickup_person_phone'], 'text_columns' => ['sender_details', 'tracking_id', 'source', 'account_paid_to'], 'amount_columns' => ['amount_involved']],
		'ezwichs' => ['label' => 'eZwich', 'phone_columns' => ['phone', 'receiving', 'receiving_sim'], 'text_columns' => ['usn', 'network', 'transaction_type', 'destination_bank'], 'amount_columns' => ['amount']],
		'cfas' => ['label' => 'CFA', 'phone_columns' => ['phone_number', 'phone'], 'text_columns' => ['description', 'transaction_type'], 'amount_columns' => ['amount_ghs', 'amount_cfa']],
	];
}

function tableColumns(\Core\Database $db, string $table): array
{
	static $cache = [];
	$table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
	if ($table === '') return [];
	if (array_key_exists($table, $cache)) return $cache[$table];
	$rows = $db->query(
		"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table",
		['db' => DB_NAME, 'table' => $table]
	) ?: [];
	$columns = [];
	foreach ($rows as $row) {
		$name = (string)($row->COLUMN_NAME ?? '');
		if ($name !== '') $columns[$name] = true;
	}
	return $cache[$table] = $columns;
}

function serviceSpec(\Core\Database $db, string $service): ?array
{
	$defs = crmServiceDefinitions();
	if (!isset($defs[$service])) return null;
	$columns = tableColumns($db, $service);
	if (!$columns) return null;
	$phoneColumns = array_values(array_filter($defs[$service]['phone_columns'], static fn($c) => isset($columns[$c])));
	$textColumns = array_values(array_filter($defs[$service]['text_columns'], static fn($c) => isset($columns[$c])));
	$amountColumn = null;
	foreach ($defs[$service]['amount_columns'] as $candidate) {
		if (isset($columns[$candidate])) {
			$amountColumn = $candidate;
			break;
		}
	}
	$agentColumns = array_values(array_filter(['assigned_user_id', 'assigned', 'created_by', 'agent_id', 'user_id'], static fn($c) => isset($columns[$c])));
	return [
		'name' => $service,
		'label' => $defs[$service]['label'],
		'columns' => $columns,
		'phone_columns' => $phoneColumns,
		'text_columns' => $textColumns,
		'amount_column' => $amountColumn,
		'date_column' => isset($columns['date_created']) ? 'date_created' : (isset($columns['transaction_date']) ? 'transaction_date' : null),
		'agent_columns' => $agentColumns,
	];
}

function allServiceSpecs(\Core\Database $db, array $filters = []): array
{
	$selected = array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/', (string)($filters['service'] ?? ''))), static fn($v) => $v !== ''));
	$out = [];
	foreach (array_keys(crmServiceDefinitions()) as $service) {
		if ($selected !== [] && !in_array($service, $selected, true)) continue;
		$spec = serviceSpec($db, $service);
		if ($spec) $out[$service] = $spec;
	}
	return $out;
}

function amountExpr(?string $column): string
{
	return $column ? "CAST(COALESCE(NULLIF({$column}, ''), 0) AS DECIMAL(15,2))" : '0';
}

function deletedWhere(array $spec): string
{
	return isset($spec['columns']['deleted']) ? 'deleted = 0' : '1 = 1';
}

function approvedWhere(array $spec): string
{
	return isset($spec['columns']['transaction_status'])
		? "(transaction_status IS NULL OR LOWER(transaction_status) NOT IN ('declined','rejected','denied','cancelled','canceled'))"
		: '1 = 1';
}

function agentWhere(array $spec, int $sessionUserId, bool $isAgentView, array &$params, array $filters = []): string
{
	if ($isAgentView) {
		$targetAgent = $sessionUserId;
		if ($targetAgent <= 0 || empty($spec['agent_columns'])) return '1 = 1';
		$key = 'agent_' . count($params);
		$params[$key] = $targetAgent;
		$parts = [];
		foreach ($spec['agent_columns'] as $column) $parts[] = "{$column} = :{$key}";
		return '(' . implode(' OR ', $parts) . ')';
	}

	$filterAgents = array_values(array_filter(array_map('intval', preg_split('/\s*,\s*/', (string)($filters['agent_id'] ?? ''))), static fn($v) => $v > 0));
	if (empty($filterAgents) || empty($spec['agent_columns'])) return '1 = 1';

	$placeholders = [];
	foreach ($filterAgents as $agentId) {
		$key = 'agent_' . count($params);
		$params[$key] = $agentId;
		$placeholders[] = ':' . $key;
	}

	$parts = [];
	foreach ($spec['agent_columns'] as $column) {
		$parts[] = "{$column} IN (" . implode(', ', $placeholders) . ")";
	}

	return '(' . implode(' OR ', $parts) . ')';
}

function dateWhere(array $spec, array &$params, array $filters = []): string
{
	if (empty($spec['date_column'])) return '1 = 1';
	$parts = [];
	$dateFrom = trim((string)($filters['date_from'] ?? ''));
	$dateTo = trim((string)($filters['date_to'] ?? ''));
	if ($dateFrom !== '') {
		$params['date_from_' . count($params)] = $dateFrom;
		$key = array_key_last($params);
		$parts[] = "DATE({$spec['date_column']}) >= :{$key}";
	}
	if ($dateTo !== '') {
		$params['date_to_' . count($params)] = $dateTo;
		$key = array_key_last($params);
		$parts[] = "DATE({$spec['date_column']}) <= :{$key}";
	}
	return $parts ? '(' . implode(' AND ', $parts) . ')' : '1 = 1';
}

function findWhere(array $spec, string $find, array &$params): string
{
	$find = trim($find);
	if ($find === '') return '1 = 1';
	$parts = [];
	$variants = phoneVariants($find);
	if (!empty($variants) && !empty($spec['phone_columns'])) {
		$placeholders = [];
		foreach ($variants as $variant) {
			$key = 'phone_' . count($params);
			$params[$key] = $variant;
			$placeholders[] = ':' . $key;
		}
		foreach ($spec['phone_columns'] as $column) $parts[] = "{$column} IN (" . implode(',', $placeholders) . ")";
	}
	$key = 'find_' . count($params);
	$params[$key] = '%' . $find . '%';
	foreach (array_merge($spec['phone_columns'], $spec['text_columns']) as $column) $parts[] = "{$column} LIKE :{$key}";
	return $parts ? '(' . implode(' OR ', $parts) . ')' : '1 = 0';
}

function baseWhere(array $spec, int $sessionUserId, bool $isAgentView, array &$params, array $filters = []): array
{
	return [
		deletedWhere($spec),
		approvedWhere($spec),
		dateWhere($spec, $params, $filters),
		agentWhere($spec, $sessionUserId, $isAgentView, $params, $filters),
		findWhere($spec, trim((string)($filters['find'] ?? '')), $params),
	];
}

function displayPhoneFromRow(object $row, array $columns): string
{
	foreach ($columns as $column) {
		$value = trim((string)($row->{$column} ?? ''));
		if ($value !== '') return $value;
	}
	return '';
}

function formatUserDisplayName(object $row, array $availableColumns): string
{
	$name = trim((string)($row->first_name ?? '') . ' ' . (string)($row->last_name ?? ''));
	if ($name !== '') {
		return $name;
	}
	if (isset($availableColumns['username']) && trim((string)($row->username ?? '')) !== '') {
		return trim((string)($row->username ?? ''));
	}
	if (isset($availableColumns['email']) && trim((string)($row->email ?? '')) !== '') {
		return trim((string)($row->email ?? ''));
	}
	return 'Agent #' . ((int)($row->id ?? 0));
}

function agentNameMap(\Core\Database $db, array $ids): array
{
	$ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($v) => $v > 0)));
	if (!$ids) return [];
	$columns = tableColumns($db, 'users');
	$select = 'id, first_name, last_name';
	if (isset($columns['username'])) {
		$select .= ', username';
	}
	if (isset($columns['email'])) {
		$select .= ', email';
	}
	$params = [];
	$ph = [];
	foreach ($ids as $i => $id) {
		$key = 'uid' . $i;
		$params[$key] = $id;
		$ph[] = ':' . $key;
	}
	$sql = "SELECT {$select} FROM users WHERE id IN (" . implode(',', $ph) . ")";
	$rows = $db->query($sql, $params) ?: [];
	$map = [];
	foreach ($rows as $row) {
		$map[(int)($row->id ?? 0)] = formatUserDisplayName($row, $columns);
	}
	return $map;
}

function getAgentOptions(\Core\Database $db): array
{
	$columns = tableColumns($db, 'users');
	$select = 'id, first_name, last_name';
	if (isset($columns['username'])) {
		$select .= ', username';
	}
	if (isset($columns['email'])) {
		$select .= ', email';
	}
	$orderBy = 'ORDER BY first_name ASC, last_name ASC';
	if (isset($columns['username'])) {
		$orderBy .= ', username ASC';
	} elseif (isset($columns['email'])) {
		$orderBy .= ', email ASC';
	}
	$sql = "SELECT {$select} FROM users";
	if (isset($columns['deleted'])) {
		$sql .= ' WHERE deleted = 0';
	}
	$sql .= ' ' . $orderBy;
	$rows = $db->query($sql) ?: [];
	$options = [];
	foreach ($rows as $row) {
		$id = (int)($row->id ?? 0);
		if ($id <= 0) continue;
		$options[] = ['id' => $id, 'name' => formatUserDisplayName($row, $columns)];
	}
	return $options;
}

function fetchTransactions(\Core\Database $db, array $filters = [], int $limitPerService = 500, int $sessionUserId = 0, bool $isAgentView = false): array
{
	$transactions = [];
	$agentIds = [];
	foreach (allServiceSpecs($db, $filters) as $service => $spec) {
		if (empty($spec['date_column'])) continue;
		$params = [];
		$whereParts = baseWhere($spec, $sessionUserId, $isAgentView, $params, $filters);
		$amountColumn = $spec['amount_column'];
		$selectAmount = $amountColumn ? ", {$amountColumn} AS crm_amount" : ', 0 AS crm_amount';
		$selectStatus = isset($spec['columns']['transaction_status']) ? ', transaction_status AS crm_status' : ", 'Approved' AS crm_status";
		$selectAgent = !empty($spec['agent_columns']) ? ', COALESCE(' . implode(', ', $spec['agent_columns']) . ') AS crm_agent_id' : ', NULL AS crm_agent_id';
		$selectPhones = !empty($spec['phone_columns']) ? implode(',', $spec['phone_columns']) . ',' : '';
		$rows = $db->query(
			"SELECT {$selectPhones} {$spec['date_column']} AS crm_date {$selectAmount} {$selectStatus} {$selectAgent}
			 FROM {$service}
			 WHERE " . implode(' AND ', $whereParts) . "
			 ORDER BY {$spec['date_column']} DESC
			 LIMIT " . max(1, (int)$limitPerService),
			$params
		) ?: [];
		foreach ($rows as $row) {
			$agentId = (int)($row->crm_agent_id ?? 0);
			if ($agentId > 0) $agentIds[$agentId] = $agentId;
			$phone = displayPhoneFromRow($row, $spec['phone_columns']);
			$transactions[] = [
				'service' => $service,
				'service_label' => $spec['label'],
				'phone' => $phone,
				'normalized_phone' => normalizePhone($phone) ?: $phone,
				'amount' => (float)($row->crm_amount ?? 0),
				'date' => (string)($row->crm_date ?? ''),
				'status' => (string)($row->crm_status ?? 'Approved'),
				'agent_id' => $agentId,
				'agent_name' => '',
			];
		}
	}
	$names = agentNameMap($db, array_values($agentIds));
	foreach ($transactions as &$tx) {
		$id = (int)($tx['agent_id'] ?? 0);
		$tx['agent_name'] = $names[$id] ?? ($id > 0 ? ('Agent #' . $id) : 'Unassigned');
	}
	unset($tx);
	usort($transactions, static fn($a, $b) => strtotime((string)$b['date']) <=> strtotime((string)$a['date']));
	return $transactions;
}

function searchCustomerByPhone(\Core\Database $db, $find, $sessionUserId = 0, $isAgentView = false, array $filters = []): array
{
	$filters['find'] = trim((string)$find);
	$transactions = fetchTransactions($db, $filters, 500, (int)$sessionUserId, (bool)$isAgentView);
	$phones = [];
	$services = [];
	$totalAmount = 0.0;
	foreach ($transactions as $tx) {
		if (trim((string)$tx['phone']) !== '') $phones[$tx['phone']] = $tx['phone'];
		$key = $tx['service'];
		if (!isset($services[$key])) $services[$key] = ['service' => $key, 'label' => $tx['service_label'], 'count' => 0, 'amount' => 0.0, 'average' => 0.0, 'returning_rate' => 0.0, 'dates' => []];
		$services[$key]['count']++;
		$services[$key]['amount'] += (float)$tx['amount'];
		$date = substr((string)$tx['date'], 0, 10);
		if ($date !== '') $services[$key]['dates'][$date] = true;
		$totalAmount += (float)$tx['amount'];
	}
	foreach ($services as &$svc) {
		$svc['average'] = $svc['count'] > 0 ? $svc['amount'] / $svc['count'] : 0;
		$visits = count($svc['dates'] ?? []);
		$svc['returning_rate'] = $visits > 1 ? round((($visits - 1) / $visits) * 100, 2) : 0;
		unset($svc['dates']);
	}
	unset($svc);
	return [
		'found' => count($transactions) > 0,
		'phones' => array_values($phones),
		'services' => $services,
		'total_transactions' => count($transactions),
		'total_amount' => $totalAmount,
		'normalized_phone' => normalizePhone($find),
	];
}

function getReturningRate(\Core\Database $db, $normalizedPhone, $sessionUserId = 0, $isAgentView = false, array $filters = []): array
{
	$filters['find'] = (string)$normalizedPhone;
	$transactions = fetchTransactions($db, $filters, 500, (int)$sessionUserId, (bool)$isAgentView);
	$dates = [];
	foreach ($transactions as $tx) {
		$date = substr((string)$tx['date'], 0, 10);
		if ($date !== '') $dates[$date] = true;
	}
	$total = count($dates);
	return ['total_visits' => $total, 'is_returning' => $total > 1, 'returning_rate' => $total > 1 ? round((($total - 1) / $total) * 100, 2) : 0];
}

function getTopCustomersByService(\Core\Database $db, $service, $limit = 10, $sessionUserId = 0, $isAgentView = false, array $filters = []): array
{
	$filters['service'] = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$service);
	$transactions = fetchTransactions($db, $filters, 1000, (int)$sessionUserId, (bool)$isAgentView);
	$groups = [];
	foreach ($transactions as $tx) {
		$phone = trim((string)$tx['phone']);
		if ($phone === '') continue;
		$key = normalizePhone($phone) ?: $phone;
		if (!isset($groups[$key])) $groups[$key] = ['phone' => $phone, 'service' => $filters['service'], 'transaction_count' => 0, 'total_amount' => 0.0, 'dates' => []];
		$groups[$key]['transaction_count']++;
		$groups[$key]['total_amount'] += (float)$tx['amount'];
		$date = substr((string)$tx['date'], 0, 10);
		if ($date !== '') $groups[$key]['dates'][$date] = true;
	}
	foreach ($groups as &$g) {
		$visits = count($g['dates']);
		$g['is_returning'] = $visits > 1;
		$g['returning_rate'] = $visits > 1 ? round((($visits - 1) / $visits) * 100, 2) : 0;
		unset($g['dates']);
	}
	unset($g);
	usort($groups, static fn($a, $b) => ($b['transaction_count'] <=> $a['transaction_count']) ?: ($b['total_amount'] <=> $a['total_amount']));
	return array_slice($groups, 0, max(1, (int)$limit));
}

function getTopAgentsByService(\Core\Database $db, $service, $limit = 10, $sessionUserId = 0, $isAgentView = false, array $filters = []): array
{
	$filters['service'] = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$service);
	$transactions = fetchTransactions($db, $filters, 1000, (int)$sessionUserId, (bool)$isAgentView);
	$groups = [];
	foreach ($transactions as $tx) {
		$id = (int)($tx['agent_id'] ?? 0);
		if ($id <= 0) continue;
		if (!isset($groups[$id])) $groups[$id] = ['agent_id' => (string)$id, 'agent_name' => $tx['agent_name'], 'service' => $filters['service'], 'unique_customers' => 0, 'transaction_count' => 0, 'phones' => []];
		$groups[$id]['transaction_count']++;
		$phone = normalizePhone($tx['phone']) ?: trim((string)$tx['phone']);
		if ($phone !== '') $groups[$id]['phones'][$phone] = true;
	}
	foreach ($groups as &$g) {
		$g['unique_customers'] = count($g['phones']);
		unset($g['phones']);
	}
	unset($g);
	usort($groups, static fn($a, $b) => ($b['unique_customers'] <=> $a['unique_customers']) ?: ($b['transaction_count'] <=> $a['transaction_count']));
	return array_slice(array_values($groups), 0, max(1, (int)$limit));
}

function getTransactionHistory(\Core\Database $db, $find, $limit = 50, $offset = 0, $sessionUserId = 0, $isAgentView = false, array $filters = []): array
{
	if (trim((string)$find) !== '') $filters['find'] = (string)$find;
	$transactions = fetchTransactions($db, $filters, 500, (int)$sessionUserId, (bool)$isAgentView);
	$total = count($transactions);
	return ['transactions' => array_slice($transactions, max(0, (int)$offset), max(1, (int)$limit)), 'total' => $total, 'limit' => (int)$limit, 'offset' => (int)$offset];
}

function getReturningAnalytics(\Core\Database $db, array $filters = [], int $sessionUserId = 0, bool $isAgentView = false): array
{
	$transactions = fetchTransactions($db, $filters, 1500, $sessionUserId, $isAgentView);
	$services = [];
	$customers = [];
	$agents = [];
	foreach ($transactions as $tx) {
		$phone = normalizePhone($tx['phone']) ?: trim((string)$tx['phone']);
		if ($phone === '') continue;
		$svc = $tx['service'];
		$date = substr((string)$tx['date'], 0, 10);
		$agentId = (int)($tx['agent_id'] ?? 0);

		if (!isset($services[$svc])) $services[$svc] = ['service' => $tx['service_label'], 'total_customers' => [], 'returning_customers' => 0, 'customer_dates' => []];
		$services[$svc]['total_customers'][$phone] = true;
		$services[$svc]['customer_dates'][$phone][$date] = true;

		if (!isset($customers[$phone])) $customers[$phone] = ['phone' => $tx['phone'], 'total_serves' => 0, 'returning_serves' => 0, 'service_dates' => []];
		$customers[$phone]['total_serves']++;
		$customers[$phone]['service_dates'][$svc]['label'] = $tx['service_label'];
		$customers[$phone]['service_dates'][$svc]['dates'][$date] = true;

		if ($agentId > 0) {
			if (!isset($agents[$agentId])) $agents[$agentId] = ['agent_id' => $agentId, 'agent_name' => $tx['agent_name'], 'total_customers' => [], 'returning_customers' => 0, 'service_customers' => []];
			$agents[$agentId]['total_customers'][$phone] = true;
			$agents[$agentId]['service_customers'][$svc]['label'] = $tx['service_label'];
			$agents[$agentId]['service_customers'][$svc]['customers'][$phone][$date] = true;
		}
	}

	$serviceRows = [];
	foreach ($services as $svc => $data) {
		$details = [];
		$returning = 0;
		$totalVisits = 0;
		foreach ($data['customer_dates'] as $phone => $dates) {
			$visits = count($dates);
			$totalVisits += $visits;
			if ($visits > 1) $returning++;
		}
		foreach ($data['customer_dates'] as $phone => $dates) {
			$visits = count($dates);
			$details[] = [
				'label' => $phone,
				'meta' => number_format($visits) . ' visit' . ($visits === 1 ? '' : 's'),
				'value' => $visits > 1 ? 'Returning' : 'First time',
				'rate' => $totalVisits > 0 ? round(($visits / $totalVisits) * 100, 2) : 0,
			];
		}
		usort($details, static fn($a, $b) => ($b['rate'] <=> $a['rate']));
		$totalCustomers = count($data['total_customers']);
		$serviceRows[] = [
			'title' => $data['service'],
			'meta' => number_format($totalCustomers) . ' customers | ' . number_format($returning) . ' returning',
			'rate' => $totalCustomers > 0 ? round(($returning / $totalCustomers) * 100, 2) : 0,
			'details' => array_slice($details, 0, 8),
		];
	}

	$customerRows = [];
	$returningCustomers = 0;
	// compute total customer visits for percentage calculations
	$totalCustomerVisits = 0;
	foreach ($customers as $p => $d) {
		$totalCustomerVisits += (int)($d['total_serves'] ?? 0);
	}
	foreach ($customers as $phone => $data) {
		$details = [];
		$returningServes = 0;
		foreach ($data['service_dates'] as $svc => $svcData) {
			$visits = count($svcData['dates']);
			$returning = max(0, $visits - 1);
			$returningServes += $returning;
			$details[] = ['label' => $svcData['label'], 'meta' => number_format($visits) . ' visit' . ($visits === 1 ? '' : 's'), 'value' => number_format($returning) . ' returns', 'rate' => $visits > 0 ? round(($returning / $visits) * 100, 2) : 0];
		}
		if ($data['total_serves'] > 1) {
			$returningCustomers++;
		}
		$visitCount = (int)($data['total_serves'] ?? 0);
		$visitPct = $totalCustomerVisits > 0 ? round((($visitCount) / $totalCustomerVisits) * 100, 2) : 0;
		$customerRows[] = [
			'title' => $data['phone'],
			'meta' => number_format($visitCount) . ' transactions | ' . number_format($returningServes) . ' returns',
			'rate' => $visitCount > 0 ? round(($returningServes / $visitCount) * 100, 2) : 0,
			'return_count' => (int)$returningServes,
			'visit_count' => $visitCount,
			'visit_percentage' => $visitPct,
			'details' => $details
		];
	}

	$agentRows = [];
	foreach ($agents as $agent) {
		$details = [];
		$returningCustomers = 0;
		foreach ($agent['service_customers'] as $svc => $svcData) {
			$svcReturning = 0;
			foreach ($svcData['customers'] as $phone => $dates) {
				if (count($dates) > 1) $svcReturning++;
			}
			$total = count($svcData['customers']);
			$returningCustomers += $svcReturning;
			$details[] = ['label' => $svcData['label'], 'meta' => number_format($total) . ' customers', 'value' => number_format($svcReturning) . ' returning', 'rate' => $total > 0 ? round(($svcReturning / $total) * 100, 2) : 0];
		}
		$totalCustomers = count($agent['total_customers']);
		$agentRows[] = ['title' => $agent['agent_name'], 'meta' => number_format($totalCustomers) . ' customers | ' . number_format($returningCustomers) . ' returning', 'rate' => $totalCustomers > 0 ? round(($returningCustomers / $totalCustomers) * 100, 2) : 0, 'details' => $details];
	}

	$sort = static fn($a, $b) => ($b['rate'] <=> $a['rate']);
	usort($serviceRows, $sort);
	usort($customerRows, $sort);
	usort($agentRows, $sort);
	$totalServices = count($services);
	$totalReturningCustomers = count(array_filter($customers, static fn($data) => ($data['total_serves'] ?? 0) > 1));
	$totalHighPerformingAgents = 0;
	foreach ($agents as $agentData) {
		$returningAgentCustomers = 0;
		foreach ($agentData['service_customers'] as $svcData) {
			foreach (($svcData['customers'] ?? []) as $dates) {
				if (count($dates) > 1) {
					$returningAgentCustomers++;
				}
			}
		}
		if ($returningAgentCustomers > 0) {
			$totalHighPerformingAgents++;
		}
	}
	return [
		'services' => array_slice($serviceRows, 0, 10),
		'customers' => array_slice($customerRows, 0, 10),
		'agents' => array_slice($agentRows, 0, 10),
		'total_services' => $totalServices,
		'total_returning_customers' => $totalReturningCustomers,
		'total_high_performing_agents' => $totalHighPerformingAgents,
		'transactions' => $transactions,
	];
}

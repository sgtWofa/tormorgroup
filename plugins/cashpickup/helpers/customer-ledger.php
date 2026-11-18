<?php

namespace CashpickupLedger;

use \Core\Database;

defined('ROOT') or die("Direct script access denied");

function runtime_marker_path(string $name): string
{
	return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . $name;
}

function write_runtime_marker(string $path): void
{
	$dir = dirname($path);
	if (!is_dir($dir)) {
		@mkdir($dir, 0777, true);
	}
	@file_put_contents($path, 'done ' . date('c'));
}

function ensure_cashpickup_performance_indexes(Database $db): void
{
	static $checked = false;
	if ($checked) {
		return;
	}
	$checked = true;

	$marker = runtime_marker_path('cashpickup_performance_indexes_20260425.done');
	if (file_exists($marker)) {
		return;
	}

	try {
		if ($db->table_exists(['cashpickups'])) {
			foreach (['customer_id', 'receive_id', 'transaction_side', 'tracking_id', 'deleted', 'date_created'] as $col) {
				$hasCol = $db->query(
					"SELECT COLUMN_NAME
					 FROM INFORMATION_SCHEMA.COLUMNS
					 WHERE TABLE_SCHEMA = :db
					 AND TABLE_NAME = 'cashpickups'
					 AND COLUMN_NAME = :col
					 LIMIT 1",
					['db' => DB_NAME, 'col' => $col]
				);
				if (empty($hasCol)) {
					return;
				}
			}
			foreach ([
				"CREATE INDEX idx_cashpickups_customer_deleted_date ON cashpickups (customer_id, deleted, date_created)",
				"CREATE INDEX idx_cashpickups_side_tracking ON cashpickups (transaction_side, tracking_id)",
				"CREATE INDEX idx_cashpickups_side_receive_deleted ON cashpickups (transaction_side, receive_id, deleted)",
				"CREATE INDEX idx_cashpickups_tracking_deleted ON cashpickups (tracking_id, deleted)",
				"CREATE INDEX idx_cashpickups_credit_customer_date ON cashpickups (credit_mode, customer_id, date_created)",
				"CREATE INDEX idx_cashpickups_credit_receive_date ON cashpickups (credit_mode, receive_id, date_created)",
			] as $indexSql) {
				try {
					$db->query($indexSql);
				} catch (\Throwable $e) {}
			}
		}
		if ($db->table_exists(['cashpickup_transfers'])) {
			foreach ([
				"CREATE INDEX idx_cashpickup_transfers_receive_user_status ON cashpickup_transfers (receive_id, to_user_id, approval_status)",
				"CREATE INDEX idx_cashpickup_transfers_receive_status ON cashpickup_transfers (receive_id, approval_status)",
			] as $indexSql) {
				try {
					$db->query($indexSql);
				} catch (\Throwable $e) {}
			}
		}
		if ($db->table_exists(['cashpickup_credit_repayments'])) {
			foreach ([
				"CREATE INDEX idx_cashpickup_credit_repayments_credit ON cashpickup_credit_repayments (credit_id, date_created)",
				"CREATE INDEX idx_cashpickup_credit_repayments_receive ON cashpickup_credit_repayments (receive_id, date_created)",
				"CREATE INDEX idx_cashpickup_credit_repayments_customer ON cashpickup_credit_repayments (customer_id, date_created)",
			] as $indexSql) {
				try {
					$db->query($indexSql);
				} catch (\Throwable $e) {}
			}
		}
		write_runtime_marker($marker);
	} catch (\Throwable $e) {
		// Keep page loads resilient; missing indexes only affect performance.
	}
}

function ensure_cashpickup_customer_schema(Database $db): void
{
	static $checked = false;
	if ($checked) {
		return;
	}
	$checked = true;

	$marker = runtime_marker_path('cashpickup_customer_schema_20260423.done');
	if (file_exists($marker)) {
		return;
	}

	if ($db->table_exists(['cashpickup_customers'])) {
		write_runtime_marker($marker);
		return;
	}

	$sql = "CREATE TABLE IF NOT EXISTS cashpickup_customers (
		id BIGINT unsigned NOT NULL,
		full_name varchar(150) null,
		phone_number varchar(50) null,
		id_type varchar(40) null,
		id_number varchar(80) null,
		location varchar(150) null,
		created_by varchar(100) null,
		updated_by varchar(100) null,
		disabled tinyint(1) unsigned default 0,
		deleted tinyint(1) unsigned default 0,
		date_created datetime default null,
		date_updated datetime default null,
		PRIMARY KEY (id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
	$db->query($sql);

	try {
		$db->query("CREATE INDEX idx_cashpickup_customers_phone ON cashpickup_customers (phone_number)");
	} catch (\Throwable $e) {}
	try {
		$db->query("CREATE INDEX idx_cashpickup_customers_id_number ON cashpickup_customers (id_number)");
	} catch (\Throwable $e) {}
	try {
		$db->query("CREATE INDEX idx_cashpickup_customers_created ON cashpickup_customers (date_created)");
	} catch (\Throwable $e) {}
	ensure_cashpickup_customer_approval_columns($db);
	write_runtime_marker($marker);
}

function ensure_cashpickup_customer_approval_columns(Database $db): void
{
	static $checked = false;
	if ($checked) {
		return;
	}
	$checked = true;

	$marker = runtime_marker_path('cashpickup_customer_approval_schema_20260426.done');
	if (file_exists($marker)) {
		return;
	}

	$cols = [
		'approval_status' => "VARCHAR(20) NULL DEFAULT 'approved'",
		'approved_by' => 'INT unsigned NULL',
		'approved_at' => 'datetime default null',
		'denial_reason' => 'TEXT NULL',
	];
	$ok = true;
	foreach ($cols as $col => $type) {
		try {
			$hasCol = $db->query(
				"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
				 WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'cashpickup_customers' AND COLUMN_NAME = :col LIMIT 1",
				['db' => DB_NAME, 'col' => $col]
			);
			if (empty($hasCol)) {
				$db->query("ALTER TABLE cashpickup_customers ADD COLUMN {$col} {$type}");
			}
		} catch (\Throwable $e) {
			$ok = false;
		}
	}
	try {
		$db->query(
			"UPDATE cashpickup_customers
			 SET approval_status = 'approved'
			 WHERE approval_status IS NULL OR approval_status = ''"
		);
	} catch (\Throwable $e) {
		$ok = false;
	}
	try {
		$db->query("CREATE INDEX idx_cashpickup_customers_approval_status ON cashpickup_customers (approval_status)");
	} catch (\Throwable $e) {}
	if ($ok) {
		write_runtime_marker($marker);
	}
}

function ensure_cashpickup_columns(Database $db): void
{
	static $checked = false;
	if ($checked) {
		return;
	}
	$checked = true;

	$marker = runtime_marker_path('cashpickup_customer_columns_20260425.done');
	if (file_exists($marker)) {
		return;
	}

	ensure_cashpickup_performance_indexes($db);

	$ok = true;
	$cols = [
		'idcard_photo' => 'VARCHAR(255) NULL',
		'customer_photo' => 'VARCHAR(255) NULL',
		'receive_proof_photo' => 'VARCHAR(255) NULL',
		'pickup_person_idcard' => 'VARCHAR(255) NULL',
		'pickup_person_signature' => 'VARCHAR(255) NULL',
		'pickup_person_phone' => 'VARCHAR(50) NULL',
		'payment_method' => 'VARCHAR(50) NULL',
		'pickup_charge' => 'FLOAT(10,2) DEFAULT 0.00',
		'amount_paid_out' => 'FLOAT(10,2) DEFAULT 0.00',
		'amount_transferred' => 'FLOAT(10,2) DEFAULT 0.00',
		'denos_json' => 'LONGTEXT NULL',
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
		'payout_channel' => 'varchar(20) null',
		'transfer_mode' => 'varchar(20) null',
		'transfer_channel' => 'varchar(100) null',
		'transfer_bank' => 'varchar(150) null',
		'confirmed_by' => 'varchar(150) null',
		'notes' => 'TEXT NULL',
		'approval_status' => 'varchar(20) null',
		'denial_reason' => 'TEXT NULL',
		'credit_mode' => 'varchar(20) null',
		// Customer-to-customer transfer / cumulative withdrawal bookkeeping
		'ledger_transfer_from_customer_id' => 'BIGINT unsigned NULL',
		'ledger_transfer_to_customer_id' => 'BIGINT unsigned NULL',
		'ledger_transfer_pair_id' => 'BIGINT unsigned NULL',
		'ledger_transfer_direction' => 'varchar(10) null',
	];
	foreach ($cols as $col => $type) {
		try {
			$hasCol = $db->query(
				"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
				 WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'cashpickups' AND COLUMN_NAME = :col LIMIT 1",
				['db' => DB_NAME, 'col' => $col]
			);
			if (empty($hasCol)) {
				$db->query("ALTER TABLE cashpickups ADD COLUMN {$col} {$type}");
			}
		} catch (\Throwable $e) {
			$ok = false;
			// ignore, handled by controller if needed
		}
	}
	try {
		$db->query("CREATE INDEX idx_cashpickups_customer_id ON cashpickups (customer_id)");
	} catch (\Throwable $e) {}
	foreach ([
		"CREATE INDEX idx_cashpickups_receive_id ON cashpickups (receive_id)",
		"CREATE INDEX idx_cashpickups_approval_status ON cashpickups (approval_status)",
		"CREATE INDEX idx_cashpickups_assigned_user_id ON cashpickups (assigned_user_id)",
		"CREATE INDEX idx_cashpickups_side_receive_status ON cashpickups (transaction_side, receive_id, transaction_status, approval_status)",
		"CREATE INDEX idx_cashpickups_credit_mode ON cashpickups (credit_mode)",
	] as $indexSql) {
		try {
			$db->query($indexSql);
		} catch (\Throwable $e) {
			// index may already exist or column may be absent on older installs
		}
	}
	if ($ok) {
		ensure_cashpickup_performance_indexes($db);
		write_runtime_marker($marker);
	}
}

function ensure_cashpickup_credit_schema(Database $db): void
{
	static $checked = false;
	if ($checked) {
		return;
	}
	$checked = true;

	$marker = runtime_marker_path('cashpickup_credit_schema_20260425.done');
	if (file_exists($marker)) {
		return;
	}

	ensure_cashpickup_columns($db);
	ensure_cashpickup_performance_indexes($db);

	try {
		if (!$db->table_exists(['cashpickup_credit_repayments'])) {
			$db->query(
				"CREATE TABLE IF NOT EXISTS cashpickup_credit_repayments (
					id BIGINT unsigned NOT NULL AUTO_INCREMENT,
					credit_id BIGINT unsigned NOT NULL,
					receive_id BIGINT unsigned NOT NULL,
					customer_id BIGINT unsigned NULL,
					amount FLOAT(10,2) DEFAULT 0.00,
					created_by INT unsigned NULL,
					date_created datetime default null,
					PRIMARY KEY (id),
					UNIQUE KEY uniq_cashpickup_credit_receive (credit_id, receive_id),
					KEY credit_id (credit_id),
					KEY receive_id (receive_id),
					KEY customer_id (customer_id),
					KEY date_created (date_created)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
			);
		}
		ensure_cashpickup_performance_indexes($db);
		write_runtime_marker($marker);
	} catch (\Throwable $e) {
		// Ignore schema failures; callers can continue without credit tracking.
	}
}

function normalize_customer_value($value): string
{
	$value = (string)$value;
	$value = trim($value);
	return $value;
}

function customer_is_approved(?object $row): bool
{
	if (empty($row)) {
		return false;
	}
	$status = strtolower(trim((string)($row->approval_status ?? 'approved')));
	return $status === '' || $status === 'approved';
}

function find_customer_any_status(Database $db, string $idNumber, string $phone): ?object
{
	$idNumber = normalize_customer_value($idNumber);
	$phone = normalize_customer_value($phone);

	ensure_cashpickup_customer_approval_columns($db);

	if ($phone !== '') {
		$row = $db->query(
			"SELECT * FROM cashpickup_customers WHERE deleted = 0 AND phone_number = :phone ORDER BY date_created DESC, id DESC LIMIT 1",
			['phone' => $phone]
		);
		if (!empty($row)) {
			return $row[0];
		}
	}
	if ($idNumber !== '') {
		$sql = "SELECT * FROM cashpickup_customers
			WHERE deleted = 0
			AND id_number = :id_number";
		$params = ['id_number' => $idNumber];
		if ($phone !== '') {
			$sql .= " AND (phone_number IS NULL OR phone_number = '' OR phone_number = :phone)";
			$params['phone'] = $phone;
		}
		$sql .= " ORDER BY date_created DESC, id DESC LIMIT 1";
		$row = $db->query($sql, $params);
		if (!empty($row)) {
			return $row[0];
		}
	}
	return null;
}

function find_customer(Database $db, string $idNumber, string $phone): ?object
{
	$idNumber = normalize_customer_value($idNumber);
	$phone = normalize_customer_value($phone);

	ensure_cashpickup_customer_approval_columns($db);

	if ($phone !== '') {
		$row = $db->query(
			"SELECT * FROM cashpickup_customers
			 WHERE deleted = 0
			 AND phone_number = :phone
			 AND (approval_status IS NULL OR approval_status = '' OR LOWER(approval_status) = 'approved')
			 ORDER BY date_created DESC, id DESC
			 LIMIT 1",
			['phone' => $phone]
		);
		if (!empty($row)) {
			return $row[0];
		}
	}
	if ($idNumber !== '') {
		$sql = "SELECT * FROM cashpickup_customers
			 WHERE deleted = 0
			 AND id_number = :id_number
			 AND (approval_status IS NULL OR approval_status = '' OR LOWER(approval_status) = 'approved')";
		$params = ['id_number' => $idNumber];
		if ($phone !== '') {
			$sql .= " AND (phone_number IS NULL OR phone_number = '' OR phone_number = :phone)";
			$params['phone'] = $phone;
		}
		$sql .= " ORDER BY date_created DESC, id DESC LIMIT 1";
		$row = $db->query($sql, $params);
		if (!empty($row)) {
			return $row[0];
		}
	}
	return null;
}

function search_customers(Database $db, string $term, int $limit = 10, bool $approvedOnly = true): array
{
	ensure_cashpickup_customer_approval_columns($db);

	$term = trim($term);
	if ($term === '') {
		return [];
	}

	$limit = $limit > 0 ? min(50, $limit) : 10;
	$fetchLimit = min(250, $limit * 5);
	$params = ['term' => '%' . $term . '%'];
	$where = ["c.deleted = 0"];
	if ($approvedOnly) {
		$where[] = "(c.approval_status IS NULL OR c.approval_status = '' OR LOWER(c.approval_status) = 'approved')";
	}
	$where[] = "(COALESCE(c.full_name,'') LIKE :term OR COALESCE(c.phone_number,'') LIKE :term OR COALESCE(c.id_number,'') LIKE :term)";
	$duplicateApprovalFilter = $approvedOnly
		? "AND (duplicate_customer.approval_status IS NULL OR duplicate_customer.approval_status = '' OR LOWER(duplicate_customer.approval_status) = 'approved')"
		: '';
	$where[] = "NOT EXISTS (
		SELECT 1
		FROM cashpickup_customers duplicate_customer
		WHERE duplicate_customer.deleted = 0
		{$duplicateApprovalFilter}
		AND duplicate_customer.id <> c.id
		AND (
			(
				TRIM(COALESCE(duplicate_customer.id_number,'')) <> ''
				AND LOWER(TRIM(duplicate_customer.id_number)) = LOWER(TRIM(c.id_number))
			)
			OR (
				TRIM(COALESCE(duplicate_customer.phone_number,'')) <> ''
				AND TRIM(duplicate_customer.phone_number) = TRIM(c.phone_number)
				AND TRIM(COALESCE(duplicate_customer.full_name,'')) <> ''
				AND LOWER(TRIM(duplicate_customer.full_name)) = LOWER(TRIM(c.full_name))
			)
		)
		AND (
			CASE
				WHEN LOWER(COALESCE(duplicate_customer.approval_status,'approved')) IN ('pending','draft') THEN 0
				ELSE 1
			END
			<
			CASE
				WHEN LOWER(COALESCE(c.approval_status,'approved')) IN ('pending','draft') THEN 0
				ELSE 1
			END
			OR (
				(
					CASE
						WHEN LOWER(COALESCE(duplicate_customer.approval_status,'approved')) IN ('pending','draft') THEN 0
						ELSE 1
					END
				) = (
					CASE
						WHEN LOWER(COALESCE(c.approval_status,'approved')) IN ('pending','draft') THEN 0
						ELSE 1
					END
				)
				AND duplicate_customer.id < c.id
			)
		)
	)";

	$rows = $db->query(
		"SELECT c.id, c.full_name, c.phone_number, c.id_type, c.id_number, c.location, c.approval_status
		 FROM cashpickup_customers c
		 WHERE " . implode(' AND ', $where) . "
		 ORDER BY
		 	CASE WHEN COALESCE(c.full_name,'') LIKE :term THEN 0 ELSE 1 END,
		 	c.full_name ASC,
		 	c.id ASC
		 LIMIT {$fetchLimit}",
		$params
	) ?: [];

	$uniqueRows = [];
	$seenIdentities = [];
	foreach ($rows as $row) {
		$idNumber = strtolower(trim((string)($row->id_number ?? '')));
		$phone = trim((string)($row->phone_number ?? ''));
		$name = strtolower(trim((string)($row->full_name ?? '')));
		$identityKeys = [];
		if ($idNumber !== '') {
			$identityKeys[] = 'id-number:' . $idNumber;
		}
		if ($phone !== '' && $name !== '') {
			$identityKeys[] = 'phone-name:' . $phone . '|' . $name;
		}
		$identityKeys[] = 'customer-id:' . (int)($row->id ?? 0);

		$isDuplicate = false;
		foreach ($identityKeys as $identityKey) {
			if (isset($seenIdentities[$identityKey])) {
				$isDuplicate = true;
				break;
			}
		}
		if ($isDuplicate) {
			continue;
		}
		foreach ($identityKeys as $identityKey) {
			$seenIdentities[$identityKey] = true;
		}

		$uniqueRows[] = $row;
		if (count($uniqueRows) >= $limit) {
			break;
		}
	}

	return $uniqueRows;
}

function upsert_customer(array $payload, int $actorId, ?Database $db = null, array $options = []): ?array
{
	$db = $db ?? new Database;
	ensure_cashpickup_customer_schema($db);
	ensure_cashpickup_customer_approval_columns($db);

	$fullName = normalize_customer_value($payload['full_name'] ?? '');
	$phone = normalize_customer_value($payload['phone_number'] ?? '');
	$idType = normalize_customer_value($payload['id_type'] ?? '');
	$idNumber = normalize_customer_value($payload['id_number'] ?? '');
	$location = normalize_customer_value($payload['location'] ?? '');
	$approvalStatus = strtolower(trim((string)($options['approval_status'] ?? 'approved')));
	if (!in_array($approvalStatus, ['approved', 'pending', 'denied'], true)) {
		$approvalStatus = 'approved';
	}
	$approvedBy = (int)($options['approved_by'] ?? 0);

	if ($phone === '' && $idNumber === '') {
		return null;
	}

	$existing = find_customer_any_status($db, $idNumber, $phone);
	$now = date('Y-m-d H:i:s');

	if ($existing) {
		$updates = [];
		$params = ['id' => (int)($existing->id ?? 0)];
		if ($fullName !== '' && trim((string)($existing->full_name ?? '')) === '') {
			$updates[] = 'full_name = :full_name';
			$params['full_name'] = $fullName;
		}
		if ($phone !== '' && trim((string)($existing->phone_number ?? '')) === '') {
			$updates[] = 'phone_number = :phone_number';
			$params['phone_number'] = $phone;
		}
		if ($idType !== '' && trim((string)($existing->id_type ?? '')) === '') {
			$updates[] = 'id_type = :id_type';
			$params['id_type'] = $idType;
		}
		if ($idNumber !== '' && trim((string)($existing->id_number ?? '')) === '') {
			$updates[] = 'id_number = :id_number';
			$params['id_number'] = $idNumber;
		}
		if ($location !== '' && trim((string)($existing->location ?? '')) === '') {
			$updates[] = 'location = :location';
			$params['location'] = $location;
		}
		$existingStatus = strtolower(trim((string)($existing->approval_status ?? 'approved')));
		if ($approvalStatus === 'approved' && $existingStatus !== 'approved') {
			$updates[] = 'approval_status = :approval_status';
			$updates[] = 'approved_by = :approved_by';
			$updates[] = 'approved_at = :approved_at';
			$updates[] = 'denial_reason = NULL';
			$params['approval_status'] = 'approved';
			$params['approved_by'] = $approvedBy > 0 ? $approvedBy : null;
			$params['approved_at'] = $now;
		} elseif ($approvalStatus === 'pending' && $existingStatus === 'denied') {
			$updates[] = 'approval_status = :approval_status';
			$updates[] = 'approved_by = NULL';
			$updates[] = 'approved_at = NULL';
			$updates[] = 'denial_reason = NULL';
			$params['approval_status'] = 'pending';
		}
		if (!empty($updates)) {
			$updates[] = 'updated_by = :updated_by';
			$updates[] = 'date_updated = :date_updated';
			$params['updated_by'] = (string)$actorId;
			$params['date_updated'] = $now;
			$db->query(
				"UPDATE cashpickup_customers SET " . implode(', ', $updates) . " WHERE id = :id",
				$params
			);
		}

		return [
			'id' => (int)($existing->id ?? 0),
			'full_name' => $fullName !== '' ? $fullName : (string)($existing->full_name ?? ''),
			'phone_number' => $phone !== '' ? $phone : (string)($existing->phone_number ?? ''),
			'id_type' => $idType !== '' ? $idType : (string)($existing->id_type ?? ''),
			'id_number' => $idNumber !== '' ? $idNumber : (string)($existing->id_number ?? ''),
			'location' => $location !== '' ? $location : (string)($existing->location ?? ''),
			'approval_status' => $approvalStatus === 'approved'
				? 'approved'
				: (string)($existing->approval_status ?? 'approved'),
		];
	}

	$newId = 0;
	for ($attempt = 0; $attempt < 5; $attempt++) {
		$candidateId = random_int(100000000000000000, 999999999999999999);
		$existingId = $db->query(
			"SELECT id FROM cashpickup_customers WHERE id = :id LIMIT 1",
			['id' => $candidateId]
		);
		if ($db->has_error) {
			throw new \RuntimeException('Unable to verify a unique customer ID.');
		}
		if (empty($existingId)) {
			$newId = $candidateId;
			break;
		}
	}
	if ($newId <= 0) {
		throw new \RuntimeException('Unable to generate a unique customer ID.');
	}
	$db->query(
		"INSERT INTO cashpickup_customers (id, full_name, phone_number, id_type, id_number, location, created_by, date_created, approval_status, approved_by, approved_at)
		 VALUES (:id, :full_name, :phone_number, :id_type, :id_number, :location, :created_by, :date_created, :approval_status, :approved_by, :approved_at)",
		[
			'id' => $newId,
			'full_name' => $fullName !== '' ? $fullName : null,
			'phone_number' => $phone !== '' ? $phone : null,
			'id_type' => $idType !== '' ? $idType : null,
			'id_number' => $idNumber !== '' ? $idNumber : null,
			'location' => $location !== '' ? $location : null,
			'created_by' => (string)$actorId,
			'date_created' => $now,
			'approval_status' => $approvalStatus,
			'approved_by' => $approvalStatus === 'approved' && $approvedBy > 0 ? $approvedBy : null,
			'approved_at' => $approvalStatus === 'approved' ? $now : null,
		]
	);
	if ($db->has_error) {
		throw new \RuntimeException('Unable to create customer profile.');
	}

	return [
		'id' => $newId,
		'full_name' => $fullName,
		'phone_number' => $phone,
		'id_type' => $idType,
		'id_number' => $idNumber,
		'location' => $location,
		'approval_status' => $approvalStatus,
	];
}

function link_customer_transactions(Database $db, int $customerId, array $payload): void
{
	if ($customerId <= 0) {
		return;
	}

	$fullName = normalize_customer_value($payload['full_name'] ?? '');
	$phone = normalize_customer_value($payload['phone_number'] ?? '');
	$idType = normalize_customer_value($payload['id_type'] ?? '');
	$idNumber = normalize_customer_value($payload['id_number'] ?? '');
	$location = normalize_customer_value($payload['location'] ?? '');

	$whereParts = [];
	$params = ['customer_id' => $customerId];
	if ($phone !== '') {
		$whereParts[] = 'phone_number = :phone_number';
		$params['phone_number'] = $phone;
		if ($idNumber !== '') {
			$whereParts[] = "((phone_number IS NULL OR phone_number = '') AND customer_id_number = :customer_id_number)";
			$params['customer_id_number'] = $idNumber;
		}
	} elseif ($idNumber !== '') {
		if ($fullName !== '') {
			$whereParts[] = "(customer_id_number = :customer_id_number AND (sender_details = :sender_details OR COALESCE(sender_details,'') = ''))";
			$params['customer_id_number'] = $idNumber;
			$params['sender_details'] = $fullName;
		} else {
			$whereParts[] = 'customer_id_number = :customer_id_number';
			$params['customer_id_number'] = $idNumber;
		}
	}

	if (empty($whereParts)) {
		return;
	}

	$setParts = ['customer_id = :customer_id'];
	if ($idType !== '') {
		$setParts[] = "customer_id_type = CASE WHEN customer_id_type IS NULL OR customer_id_type = '' THEN :customer_id_type ELSE customer_id_type END";
		$params['customer_id_type'] = $idType;
	}
	if ($idNumber !== '') {
		$setParts[] = "customer_id_number = CASE WHEN customer_id_number IS NULL OR customer_id_number = '' THEN :customer_id_number ELSE customer_id_number END";
	}
	if ($location !== '') {
		$setParts[] = "customer_location = CASE WHEN customer_location IS NULL OR customer_location = '' THEN :customer_location ELSE customer_location END";
		$params['customer_location'] = $location;
	}

	$sql = "UPDATE cashpickups SET " . implode(', ', $setParts) .
		" WHERE deleted = 0 AND (" . implode(' OR ', $whereParts) . ")";
	$db->query($sql, $params);
}

function repair_customer_transaction_links(Database $db, int $customerId = 0): int
{
	ensure_cashpickup_customer_schema($db);
	ensure_cashpickup_columns($db);

	$customerId = (int)$customerId;
	$marker = runtime_marker_path('cashpickup_customer_link_repair_20260428.done');
	if ($customerId <= 0 && file_exists($marker)) {
		return 0;
	}

	$params = [];
	$sql = "SELECT id, full_name, phone_number, id_type, id_number, location
		FROM cashpickup_customers
		WHERE deleted = 0";
	if ($customerId > 0) {
		$sql .= " AND id = :id";
		$params['id'] = $customerId;
	}
	$sql .= " ORDER BY (COALESCE(phone_number,'') = '') ASC, id DESC";

	$rows = $db->query($sql, $params) ?: [];
	$count = 0;
	foreach ($rows as $row) {
		link_customer_transactions($db, (int)($row->id ?? 0), [
			'full_name' => (string)($row->full_name ?? ''),
			'phone_number' => (string)($row->phone_number ?? ''),
			'id_type' => (string)($row->id_type ?? ''),
			'id_number' => (string)($row->id_number ?? ''),
			'location' => (string)($row->location ?? ''),
		]);
		$count++;
	}

	if ($customerId <= 0) {
		write_runtime_marker($marker);
	}

	return $count;
}

function sync_customers_from_cashpickups(Database $db, int $limit = 2000): int
{
	ensure_cashpickup_customer_schema($db);
	ensure_cashpickup_columns($db);

	$rows = $db->query(
		"SELECT sender_details, phone_number, customer_id_type, customer_id_number, customer_location, created_by, date_created
		 FROM cashpickups
		 WHERE deleted = 0
		   AND (COALESCE(phone_number,'') <> '' OR COALESCE(customer_id_number,'') <> '')
		 ORDER BY date_created DESC, id DESC
		 LIMIT " . (int)$limit
	) ?: [];

	$count = 0;
	foreach ($rows as $row) {
		$payload = [
			'full_name' => (string)($row->sender_details ?? ''),
			'phone_number' => (string)($row->phone_number ?? ''),
			'id_type' => (string)($row->customer_id_type ?? ''),
			'id_number' => (string)($row->customer_id_number ?? ''),
			'location' => (string)($row->customer_location ?? ''),
		];
		$actorId = (int)($row->created_by ?? 0);
		$customer = upsert_customer($payload, $actorId, $db);
		if ($customer && !empty($customer['id'])) {
			link_customer_transactions($db, (int)$customer['id'], $payload);
			$count++;
		}
	}
	return $count;
}

function build_customer_match_sql(int $customerId, string $phone, string $idNumber, string $alias = 'c', string $paramPrefix = 'm'): array
{
	$alias = trim($alias) !== '' ? trim($alias) : 'c';
	$paramPrefix = trim($paramPrefix) !== '' ? trim($paramPrefix) : 'm';

	$params = [
		$paramPrefix . '_cid' => $customerId,
		$paramPrefix . '_transfer_from' => $customerId,
		$paramPrefix . '_transfer_to' => $customerId,
	];
	$parts = [];
	$fallbackParts = [];

	if ($phone !== '') {
		$params[$paramPrefix . '_phone'] = $phone;
		$fallbackParts[] = "{$alias}.phone_number = :" . $paramPrefix . "_phone";
		if ($idNumber !== '') {
			$params[$paramPrefix . '_id_number'] = $idNumber;
			$fallbackParts[] = "((" . $alias . ".phone_number IS NULL OR " . $alias . ".phone_number = '') AND {$alias}.customer_id_number = :" . $paramPrefix . "_id_number)";
		}
	} elseif ($idNumber !== '') {
		$params[$paramPrefix . '_id_number'] = $idNumber;
		$fallbackParts[] = "{$alias}.customer_id_number = :" . $paramPrefix . "_id_number";
	}

	$parts[] = "{$alias}.customer_id = :" . $paramPrefix . "_cid";
	$parts[] = "(
		({$alias}.customer_id IS NULL OR {$alias}.customer_id = 0)
		AND (
			(
				LOWER(TRIM(COALESCE({$alias}.transaction_side,''))) = 'pay'
				AND {$alias}.ledger_transfer_from_customer_id = :" . $paramPrefix . "_transfer_from
			)
			OR (
				LOWER(TRIM(COALESCE({$alias}.transaction_side,''))) = 'receive'
				AND {$alias}.ledger_transfer_to_customer_id = :" . $paramPrefix . "_transfer_to
			)
		)
	)";
	if (!empty($fallbackParts)) {
		$parts[] = "(({$alias}.customer_id IS NULL OR {$alias}.customer_id = 0) AND (" . implode(' OR ', $fallbackParts) . "))";
	}

	return ['(' . implode(' OR ', $parts) . ')', $params];
}

/**
 * Aggregate balance metrics for a single customer.
 *
 * Returns: total_received (deposits in), total_paid_out (regular payouts out),
 * total_withdrawals (cumulative withdrawals out), credit issued, balance, and
 * tx_count.
 *
 * This is the canonical single-call balance source and also fixes the broken
 * call site in customer-transfer-controller.php.
 */
function fetch_customer_balance_metrics(Database $db, int $customerId, int $excludePayoutId = 0): array
{
	$zero = [
		'total_received' => 0.0,
		'total_paid_out' => 0.0,
		'total_withdrawals' => 0.0,
		'total_credit_given' => 0.0,
		'total_credit_paid' => 0.0,
		'total_amount_in_credit' => 0.0,
		'balance' => 0.0,
		'tx_count' => 0,
	];
	if ($customerId <= 0) {
		return $zero;
	}

	ensure_cashpickup_customer_schema($db);
	ensure_cashpickup_columns($db);

	$customerRows = $db->query(
		"SELECT phone_number, id_number FROM cashpickup_customers WHERE deleted = 0 AND id = :id LIMIT 1",
		['id' => $customerId]
	) ?: [];
	if (empty($customerRows)) {
		return $zero;
	}
	$customer = $customerRows[0];
	$phone = trim((string)($customer->phone_number ?? ''));
	$idNumber = trim((string)($customer->id_number ?? ''));

	list($matchSql, $params) = build_customer_match_sql($customerId, $phone, $idNumber, 'm', 'cbm');
	$excludePayoutSql = '';
	if ($excludePayoutId > 0) {
		$excludePayoutSql = 'AND m.id <> :cbm_exclude_payout';
		$params['cbm_exclude_payout'] = $excludePayoutId;
	}

	// Money IN: real receives (exclude credit-mode advances).
	$receivedExpr = statement_received_amount_sql('m');
	// Money OUT: active per-receive payouts (the legacy payout flow), EXCLUDING
	// cumulative withdrawals so they aren't double-counted in the balance.
	$paidOutExpr = "(CASE
		WHEN LOWER(m.transaction_side) = 'pay'
			AND LOWER(COALESCE(m.transaction_status,'')) = 'approved'
			AND (m.confirmed_by IS NOT NULL AND m.confirmed_by != '')
			AND COALESCE(m.amount_paid_out,0) > 0
			AND " . normalized_cashpickup_credit_mode_sql('m') . " <> 'credit'
			AND (
				m.approval_status IS NULL
				OR m.approval_status = ''
				OR LOWER(m.approval_status) IN ('approved','pending')
			)
			AND LOWER(COALESCE(m.source,'')) <> 'customer_withdrawal'
		THEN (COALESCE(m.amount_paid_out,0) + COALESCE(m.pickup_charge,0))
		ELSE 0
	END)";
	// Money OUT: cumulative customer withdrawals, tagged with a dedicated source
	// so they are counted separately from the legacy per-receive payouts.
	$withdrawalExpr = "(CASE
		WHEN LOWER(m.transaction_side) = 'pay'
			AND LOWER(COALESCE(m.source,'')) = 'customer_withdrawal'
			AND LOWER(COALESCE(m.transaction_status,'')) = 'approved'
			AND (m.confirmed_by IS NOT NULL AND m.confirmed_by != '')
			AND COALESCE(m.amount_paid_out,0) > 0
			AND (
				m.approval_status IS NULL
				OR m.approval_status = ''
				OR LOWER(m.approval_status) IN ('approved','pending')
			)
		THEN (COALESCE(m.amount_paid_out,0) + COALESCE(m.pickup_charge,0))
		ELSE 0
	END)";

	try {
		$rows = $db->query(
			"SELECT
				COUNT(*) AS tx_count,
				COALESCE(SUM({$receivedExpr}),0) AS total_received,
				COALESCE(SUM({$paidOutExpr}),0) AS total_paid_out,
				COALESCE(SUM({$withdrawalExpr}),0) AS total_withdrawals
			 FROM (
				SELECT DISTINCT
					m.id,
					m.customer_id,
					m.ledger_transfer_from_customer_id,
					m.ledger_transfer_to_customer_id,
					m.phone_number,
					m.customer_id_number,
					m.transaction_side,
					m.credit_mode,
					m.amount_involved,
					m.transaction_status,
					m.confirmed_by,
					m.amount_paid_out,
					m.approval_status,
					m.source,
					m.pickup_charge
				FROM cashpickups m
				WHERE (m.deleted IS NULL OR m.deleted = 0)
				AND {$matchSql}
				{$excludePayoutSql}
			 ) AS m",
			$params
		) ?: [];
		$totalReceived = (float)($rows[0]->total_received ?? 0);
		$totalPaidOut = (float)($rows[0]->total_paid_out ?? 0);
		$totalWithdrawals = (float)($rows[0]->total_withdrawals ?? 0);
		$creditMetrics = fetch_customer_credit_metrics($db, $customerId);
		$totalCreditGiven = (float)($creditMetrics['total_credit_given'] ?? 0);
		$totalCreditPaid = (float)($creditMetrics['total_credit_paid'] ?? 0);
		// Repayment receipts are included above, so subtract issued credit to leave only the unpaid amount.
		return [
			'total_received' => $totalReceived,
			'total_paid_out' => $totalPaidOut,
			'total_withdrawals' => $totalWithdrawals,
			'total_credit_given' => $totalCreditGiven,
			'total_credit_paid' => $totalCreditPaid,
			'total_amount_in_credit' => (float)($creditMetrics['total_amount_in_credit'] ?? 0),
			'balance' => (float)($totalReceived - $totalPaidOut - $totalWithdrawals - $totalCreditGiven),
			'tx_count' => (int)($rows[0]->tx_count ?? 0),
		];
	} catch (\Throwable $e) {
		return $zero;
	}
}

/**
 * Compute a FIFO allocation of a cumulative withdrawal amount across the
 * customer's oldest unpaid (or partially-paid) receive records.
 *
 * Returns:
 *   [
 *     'slices' => [['receive_id' => int, 'amount' => float, 'tracking_id' => string], ...],
 *     'allocated' => float,   // sum of slices (<= amount)
 *     'shortfall' => float,   // amount - allocated (should be 0 when amount <= balance)
 *   ]
 *
 * A receive is "available" when it is non-credit, not deleted, has been
 * approved (or is empty), and has remaining = amount_involved - active payouts.
 * Allocation consumes the oldest receives first (FIFO by date_created).
 */
function compute_cumulative_withdrawal_allocation(Database $db, int $customerId, float $amount, int $excludePayoutId = 0): array
{
	$result = ['slices' => [], 'allocated' => 0.0, 'shortfall' => (float)$amount];
	if ($customerId <= 0 || $amount <= 0) {
		$result['shortfall'] = 0.0;
		return $result;
	}

	ensure_cashpickup_customer_schema($db);
	ensure_cashpickup_columns($db);

	$customerRows = $db->query(
		"SELECT phone_number, id_number FROM cashpickup_customers WHERE deleted = 0 AND id = :id LIMIT 1",
		['id' => $customerId]
	) ?: [];
	if (empty($customerRows)) {
		return $result;
	}
	$customer = $customerRows[0];
	$phone = trim((string)($customer->phone_number ?? ''));
	$idNumber = trim((string)($customer->id_number ?? ''));

	list($matchSql, $params) = build_customer_match_sql($customerId, $phone, $idNumber, 'r', 'fifo');

	// Active payouts already counted against each receive (legacy payouts only,
	// exclude our cumulative-withdrawal rows so we don't double-count).
	$payoutExpr = "(CASE
		WHEN LOWER(p.transaction_side) = 'pay'
			AND LOWER(COALESCE(p.source,'')) <> 'customer_withdrawal'
			AND LOWER(COALESCE(p.transaction_status,'')) = 'approved'
			AND (p.confirmed_by IS NOT NULL AND p.confirmed_by != '')
			AND COALESCE(p.amount_paid_out,0) > 0
			AND (
				p.approval_status IS NULL
				OR p.approval_status = ''
				OR LOWER(p.approval_status) IN ('approved','pending')
			)
		THEN (COALESCE(p.amount_paid_out,0) + COALESCE(p.pickup_charge,0))
		ELSE 0
	END)";
	$withdrawalExpr = "(CASE
		WHEN LOWER(p.transaction_side) = 'pay'
			AND LOWER(COALESCE(p.source,'')) = 'customer_withdrawal'
			AND LOWER(COALESCE(p.transaction_status,'')) = 'approved'
			AND (p.confirmed_by IS NOT NULL AND p.confirmed_by != '')
			AND COALESCE(p.amount_paid_out,0) > 0
			AND (
				p.approval_status IS NULL
				OR p.approval_status = ''
				OR LOWER(p.approval_status) IN ('approved','pending')
			)
		THEN (COALESCE(p.amount_paid_out,0) + COALESCE(p.pickup_charge,0))
		ELSE 0
	END)";
	$excludeAlreadyPaid = '';
	$excludeAlreadyWithdrawn = '';
	if ($excludePayoutId > 0) {
		$excludeAlreadyPaid = 'AND p.id <> :fifo_exclude_paid';
		$excludeAlreadyWithdrawn = 'AND p.id <> :fifo_exclude_withdrawal';
		$params['fifo_exclude_paid'] = $excludePayoutId;
		$params['fifo_exclude_withdrawal'] = $excludePayoutId;
	}

	try {
		$rows = $db->query(
			"SELECT DISTINCT r.id,
			        r.tracking_id,
			        COALESCE(r.amount_involved,0) AS amount_involved,
			        COALESCE((
			            SELECT SUM({$payoutExpr})
			            FROM cashpickups p
			            WHERE (p.deleted IS NULL OR p.deleted = 0)
			            AND LOWER(p.transaction_side) = 'pay'
			            AND p.receive_id = r.id
			            {$excludeAlreadyPaid}
			        ),0) AS already_paid,
			        COALESCE((
			            SELECT SUM({$withdrawalExpr})
			            FROM cashpickups p
			            WHERE (p.deleted IS NULL OR p.deleted = 0)
			            AND LOWER(p.transaction_side) = 'pay'
			            AND LOWER(COALESCE(p.source,'')) = 'customer_withdrawal'
			            AND p.receive_id = r.id
			            {$excludeAlreadyWithdrawn}
			        ),0) AS already_withdrawn
			    FROM cashpickups r
			    WHERE (r.deleted IS NULL OR r.deleted = 0)
				AND LOWER(r.transaction_side) = 'receive'
				AND " . normalized_cashpickup_credit_mode_sql('r') . " <> 'credit'
			    AND (
			        r.approval_status IS NULL
			        OR r.approval_status = ''
			        OR LOWER(r.approval_status) = 'approved'
			    )
			    AND {$matchSql}
			    ORDER BY r.date_created ASC, r.id ASC",
			$params
		) ?: [];
	} catch (\Throwable $e) {
		return $result;
	}

	$remaining = (float)$amount;
	foreach ($rows as $row) {
		if ($remaining <= 0.00001) {
			break;
		}
		$receiveTotal = (float)($row->amount_involved ?? 0);
		$alreadyOut = (float)($row->already_paid ?? 0) + (float)($row->already_withdrawn ?? 0);
		$available = $receiveTotal - $alreadyOut;
		if ($available <= 0.00001) {
			continue;
		}
		$take = min($available, $remaining);
		if ($take <= 0.00001) {
			continue;
		}
		$result['slices'][] = [
			'receive_id' => (int)$row->id,
			'amount' => round($take, 2),
			'tracking_id' => (string)($row->tracking_id ?? ''),
			'receive_total' => $receiveTotal,
			'already_paid' => (float)($row->already_paid ?? 0),
			'already_withdrawn' => (float)($row->already_withdrawn ?? 0),
		];
		$remaining -= $take;
		$result['allocated'] += $take;
	}
	$result['allocated'] = round($result['allocated'], 2);
	$result['shortfall'] = round(max(0.0, $amount - $result['allocated']), 2);
	return $result;
}

/**
 * Ensure the customer-transfer bookkeeping columns exist on cashpickups.
 *
 * Kept separate from ensure_cashpickup_columns() because that function is
 * marker-gated and will NOT re-run on existing installs — so any columns added
 * later there would never be created. This runs on every call but only ALTERs
 * when a column is actually missing.
 */
function ensure_cashpickup_transfer_columns(Database $db): void
{
	$cols = [
		'ledger_transfer_from_customer_id' => 'BIGINT unsigned NULL',
		'ledger_transfer_to_customer_id' => 'BIGINT unsigned NULL',
		'ledger_transfer_pair_id' => 'BIGINT unsigned NULL',
		'ledger_transfer_direction' => 'varchar(10) null',
		// Cash CFA payout bookkeeping (cfa_amount paid out, cfa_denos_json note breakdown)
		'cfa_amount' => 'FLOAT(10,2) DEFAULT 0.00',
		'cfa_denos_json' => 'LONGTEXT NULL',
	];
	foreach ($cols as $col => $type) {
		try {
			$hasCol = $db->query(
				"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
				 WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'cashpickups' AND COLUMN_NAME = :col LIMIT 1",
				['db' => DB_NAME, 'col' => $col]
			);
			if (empty($hasCol)) {
				$db->query("ALTER TABLE cashpickups ADD COLUMN {$col} {$type}");
			}
		} catch (\Throwable $e) {
			// ignore; callers fall back gracefully
		}
	}
}

/**
 * Read the current CFA buying and selling rates from the settings table.
 *
 * Rates are expressed as "CFA 1,000 = GH&cent; <rate>". Returns an array with
 * floats; defaults to 0.0 when the settings table or row is missing so callers
 * can guard a zero rate (e.g. block a CFA payout until a rate is set).
 */
function cashpickup_settings_cfa_rates(Database $db): array
{
	$defaults = ['buying_rate' => 0.0, 'selling_rate' => 0.0];
	try {
		if (!$db->table_exists(['settings'])) {
			return $defaults;
		}
		$rows = $db->query("SELECT buying_rate, selling_rate FROM settings ORDER BY id ASC LIMIT 1") ?: [];
		if (empty($rows)) {
			return $defaults;
		}
		return [
			'buying_rate' => (float)($rows[0]->buying_rate ?? 0),
			'selling_rate' => (float)($rows[0]->selling_rate ?? 0),
		];
	} catch (\Throwable $e) {
		return $defaults;
	}
}

/**
 * Build an EXISTS subquery fragment for customers visible to an agent through
 * their cashpickup rows or active customer-level payout assignments.
 * Returns [sqlFragment, params].
 *
 * Fail-closed: an actorId <= 0 yields a never-true fragment.
 */
function customer_visible_to_agent_sql(int $actorId, string $alias = 'c', string $paramPrefix = 'vq'): array
{
	$alias = trim($alias) !== '' ? trim($alias) : 'c';
	$paramPrefix = trim($paramPrefix) !== '' ? trim($paramPrefix) : 'vq';
	if ($actorId <= 0) {
		return ['1 = 0', []];
	}
	$params = [
		$paramPrefix . '_created' => $actorId,
		$paramPrefix . '_assigned' => $actorId,
		$paramPrefix . '_payout_assigned' => $actorId,
	];
	$sql = "(
		EXISTS (
		SELECT 1 FROM cashpickups cpv
		WHERE (cpv.deleted IS NULL OR cpv.deleted = 0)
		AND ({$alias}.id IS NOT NULL)
		AND cpv.customer_id = {$alias}.id
		AND (
			cpv.created_by = :" . $paramPrefix . "_created
			OR cpv.assigned_user_id = :" . $paramPrefix . "_assigned
		)
		)
		OR EXISTS (
			SELECT 1 FROM cashpickup_customer_assignments ca
			WHERE ca.customer_id = {$alias}.id
			AND ca.assigned_user_id = :" . $paramPrefix . "_payout_assigned
			AND LOWER(COALESCE(ca.status,'active')) = 'active'
		)
	)";
	return [$sql, $params];
}

function find_customer_id_for_cashpickup(Database $db, object $cashpickup): int
{
	$customerId = (int)($cashpickup->customer_id ?? 0);
	if ($customerId > 0) {
		return $customerId;
	}
	if (!$db->table_exists(['cashpickup_customers'])) {
		return 0;
	}

	$conditions = [];
	$params = [];
	$phone = trim((string)($cashpickup->phone_number ?? ''));
	$idNumber = trim((string)($cashpickup->customer_id_number ?? ''));
	if ($phone !== '') {
		$conditions[] = 'phone_number = :phone';
		$params['phone'] = $phone;
	}
	if ($idNumber !== '') {
		$conditions[] = 'id_number = :id_number';
		$params['id_number'] = $idNumber;
	}
	if (empty($conditions)) {
		return 0;
	}

	$rows = $db->query(
		"SELECT id FROM cashpickup_customers
		 WHERE deleted = 0 AND (" . implode(' OR ', $conditions) . ")
		 ORDER BY id DESC LIMIT 1",
		$params
	) ?: [];
	return (int)($rows[0]->id ?? 0);
}

function active_payout_amount_sql(string $alias = ''): string
{
	$prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';
	return "(CASE
		WHEN LOWER(TRIM(COALESCE({$prefix}transaction_side,''))) = 'pay'
			AND " . normalized_cashpickup_credit_mode_sql($alias) . " <> 'credit'
			AND LOWER(TRIM(COALESCE({$prefix}transaction_status,''))) = 'approved'
			AND ({$prefix}confirmed_by IS NOT NULL AND {$prefix}confirmed_by != '')
			AND COALESCE({$prefix}amount_paid_out,0) > 0
			AND (
				{$prefix}approval_status IS NULL
				OR {$prefix}approval_status = ''
				OR LOWER(TRIM(COALESCE({$prefix}approval_status,''))) IN ('approved','pending')
			)
		THEN (COALESCE({$prefix}amount_paid_out,0) + COALESCE({$prefix}pickup_charge,0))
		ELSE 0
	END)";
}

function normalized_cashpickup_field_sql(string $alias = '', string $field = ''): string
{
	$prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';
	$field = trim((string)$field);
	if ($field === '') {
		return "''";
	}
	return "LOWER(TRIM(COALESCE({$prefix}{$field},'')))";
}

function normalized_cashpickup_credit_mode_sql(string $alias = ''): string
{
	return normalized_cashpickup_field_sql($alias, 'credit_mode');
}

function statement_received_amount_sql(string $alias = ''): string
{
	$prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';
	return "(CASE
		WHEN LOWER(TRIM(COALESCE({$prefix}transaction_side,''))) = 'receive'
			AND " . normalized_cashpickup_credit_mode_sql($alias) . " <> 'credit'
		THEN COALESCE({$prefix}amount_involved,0)
		ELSE 0
	END)";
}

function statement_credit_issued_amount_sql(string $alias = ''): string
{
	$prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';
	return "(CASE
		WHEN LOWER(TRIM(COALESCE({$prefix}transaction_side,''))) = 'receive'
			AND " . normalized_cashpickup_credit_mode_sql($alias) . " = 'credit'
			AND LOWER(TRIM(COALESCE({$prefix}transaction_status,''))) IN ('approved','pending payout')
			AND (
				{$prefix}approval_status IS NULL
				OR {$prefix}approval_status = ''
				OR LOWER(TRIM(COALESCE({$prefix}approval_status,''))) IN ('approved','pending')
			)
		THEN COALESCE({$prefix}amount_involved,0)
		ELSE 0
	END)";
}

function statement_credit_repayment_amount_sql(string $alias = ''): string
{
	$prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';
	return "(SELECT COALESCE(SUM(crp.amount),0)
		FROM cashpickup_credit_repayments crp
		WHERE crp.receive_id = {$prefix}id)";
}

function statement_delta_sql(string $alias = ''): string
{
	return '(' . statement_received_amount_sql($alias)
		. ' - ' . active_payout_amount_sql($alias)
		. ' - ' . statement_credit_issued_amount_sql($alias)
		. ')';
}

function statement_financial_where_sql(string $alias = ''): string
{
	$prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';
	return "(
		(
			LOWER(TRIM(COALESCE({$prefix}transaction_side,''))) = 'receive'
			AND " . normalized_cashpickup_credit_mode_sql($alias) . " <> 'credit'
		)
		OR " . statement_credit_issued_amount_sql($alias) . " > 0
		OR (
			LOWER(TRIM(COALESCE({$prefix}transaction_side,''))) = 'pay'
			AND " . normalized_cashpickup_credit_mode_sql($alias) . " <> 'credit'
			AND LOWER(TRIM(COALESCE({$prefix}transaction_status,''))) = 'approved'
			AND ({$prefix}confirmed_by IS NOT NULL AND {$prefix}confirmed_by != '')
			AND COALESCE({$prefix}amount_paid_out,0) > 0
			AND (
				{$prefix}approval_status IS NULL
				OR {$prefix}approval_status = ''
				OR LOWER(TRIM(COALESCE({$prefix}approval_status,''))) IN ('approved','pending')
			)
		)
	)";
}

function fetch_customer_statement(Database $db, int $customerId): array
{
	ensure_cashpickup_customer_schema($db);
	ensure_cashpickup_columns($db);
	ensure_cashpickup_credit_schema($db);

	$customerRows = $db->query(
		"SELECT * FROM cashpickup_customers WHERE deleted = 0 AND id = :id LIMIT 1",
		['id' => $customerId]
	) ?: [];
	if (empty($customerRows)) {
		return [null, []];
	}

	$customer = $customerRows[0];
	$phone = trim((string)($customer->phone_number ?? ''));
	$idNumber = trim((string)($customer->id_number ?? ''));
	list($currentMatchSql, $params) = build_customer_match_sql($customerId, $phone, $idNumber, 'c', 'cust');
	$receivedSql = statement_received_amount_sql('c');
	$payoutSql = active_payout_amount_sql('c');
	$creditIssuedSql = statement_credit_issued_amount_sql('c');
	$creditRepaymentSql = statement_credit_repayment_amount_sql('c');
	$currentWhereSql = statement_financial_where_sql('c');

	$rows = $db->query(
		"SELECT DISTINCT
			c.id,
			c.date_created,
			c.tracking_id,
			c.transaction_side,
			c.source,
			c.payment_method,
			c.sender_details,
			c.phone_number,
			{$receivedSql} AS received_amount,
			{$payoutSql} AS payout_amount,
			{$creditIssuedSql} AS credit_amount,
			{$creditRepaymentSql} AS credit_repayment_amount
		 FROM cashpickups c
		 WHERE (c.deleted IS NULL OR c.deleted = 0)
		 AND {$currentMatchSql}
		 AND {$currentWhereSql}
		 ORDER BY c.date_created ASC, c.id ASC",
		$params
	) ?: [];

	$runningBalance = 0.0;
	foreach ($rows as $row) {
		$runningBalance += (float)($row->received_amount ?? 0)
			- (float)($row->payout_amount ?? 0)
			- (float)($row->credit_amount ?? 0);
		$row->balance_amount = round($runningBalance, 2);
	}

	return [$customer, $rows];
}

function build_customer_statement_where(array $filters, int $customerId, string $phone, string $idNumber, string $alias = 'm', string $paramPrefix = 'cust_stmt'): array
{
	$alias = trim($alias) !== '' ? trim($alias) : 'm';
	$find = trim((string)($filters['find'] ?? ''));
	$dateFrom = trim((string)($filters['date_from'] ?? ''));
	$dateTo = trim((string)($filters['date_to'] ?? ''));

	list($matchSql, $params) = build_customer_match_sql($customerId, $phone, $idNumber, $alias, $paramPrefix);
	$conditions = [
		"({$alias}.deleted IS NULL OR {$alias}.deleted = 0)",
		$matchSql,
		statement_financial_where_sql($alias),
	];

	if ($find !== '') {
		$conditions[] = "(CAST({$alias}.id AS CHAR) LIKE :find
			OR COALESCE({$alias}.tracking_id,'') LIKE :find
			OR COALESCE({$alias}.sender_details,'') LIKE :find
			OR COALESCE({$alias}.phone_number,'') LIKE :find
			OR COALESCE({$alias}.source,'') LIKE :find
			OR COALESCE({$alias}.payment_method,'') LIKE :find)";
		$params['find'] = '%' . $find . '%';
	}
	if ($dateFrom !== '') {
		$conditions[] = "DATE({$alias}.date_created) >= :date_from";
		$params['date_from'] = $dateFrom;
	}
	if ($dateTo !== '') {
		$conditions[] = "DATE({$alias}.date_created) <= :date_to";
		$params['date_to'] = $dateTo;
	}

	return ['WHERE ' . implode(' AND ', $conditions), $params];
}

function fetch_customer_statement_page(Database $db, int $customerId, array $filters = [], int $limit = 10, int $offset = 0): array
{
	ensure_cashpickup_customer_schema($db);
	ensure_cashpickup_columns($db);
	ensure_cashpickup_credit_schema($db);

	$customerRows = $db->query(
		"SELECT * FROM cashpickup_customers WHERE deleted = 0 AND id = :id LIMIT 1",
		['id' => $customerId]
	) ?: [];
	if (empty($customerRows)) {
		return [
			'customer' => null,
			'rows' => [],
			'total_rows' => 0,
		];
	}

	$customer = $customerRows[0];
	$phone = trim((string)($customer->phone_number ?? ''));
	$idNumber = trim((string)($customer->id_number ?? ''));
	$order = strtolower(trim((string)($filters['order'] ?? 'desc'))) === 'asc' ? 'ASC' : 'DESC';
	list($whereSql, $params) = build_customer_statement_where($filters, $customerId, $phone, $idNumber, 'm', 'cust_stmt');
	list($runningWhereSql, $runningParams) = build_customer_statement_where($filters, $customerId, $phone, $idNumber, 'gl', 'cust_stmt_running');

	$countRows = $db->query(
		"SELECT COUNT(*) AS c
		 FROM cashpickups m
		 {$whereSql}",
		$params
	) ?: [];
	$totalRows = (int)($countRows[0]->c ?? 0);

	$receivedSql = statement_received_amount_sql('m');
	$payoutSql = active_payout_amount_sql('m');
	$creditIssuedSql = statement_credit_issued_amount_sql('m');
	$creditRepaymentSql = statement_credit_repayment_amount_sql('m');
	$runningDeltaSql = statement_delta_sql('gl');
	$limitSql = '';
	if ($limit > 0) {
		$limitSql = ' LIMIT ' . (int)$limit . ' OFFSET ' . max(0, (int)$offset);
	}

	$rows = $db->query(
		"SELECT
			m.id,
			m.date_created,
			m.tracking_id,
			m.transaction_side,
			m.source,
			m.payment_method,
			m.sender_details,
			m.phone_number,
			{$receivedSql} AS received_amount,
			{$payoutSql} AS payout_amount,
			{$creditIssuedSql} AS credit_amount,
			{$creditRepaymentSql} AS credit_repayment_amount
		 FROM cashpickups m
		 {$whereSql}
		 ORDER BY m.id {$order}
		 {$limitSql}",
		$params
	) ?: [];

	if (!empty($rows)) {
		if ($order === 'ASC') {
			$runningBalance = 0.0;
			foreach ($rows as $row) {
				$runningBalance += (float)($row->received_amount ?? 0)
					- (float)($row->payout_amount ?? 0)
					- (float)($row->credit_amount ?? 0);
				$row->balance_amount = round($runningBalance, 2);
			}
		} else {
			$oldestRow = $rows[count($rows) - 1];
			$prefixParams = $runningParams;
			$prefixParams['balance_id'] = (int)($oldestRow->id ?? 0);
			$prefixRows = $db->query(
				"SELECT COALESCE(SUM({$runningDeltaSql}),0) AS balance_amount
				 FROM cashpickups gl
				 {$runningWhereSql}
				 AND gl.id < :balance_id",
				$prefixParams
			) ?: [];
			$runningBalance = (float)($prefixRows[0]->balance_amount ?? 0);
			for ($idx = count($rows) - 1; $idx >= 0; $idx--) {
				$runningBalance += (float)($rows[$idx]->received_amount ?? 0)
					- (float)($rows[$idx]->payout_amount ?? 0)
					- (float)($rows[$idx]->credit_amount ?? 0);
				$rows[$idx]->balance_amount = round($runningBalance, 2);
			}
		}
	}

	return [
		'customer' => $customer,
		'rows' => $rows,
		'total_rows' => $totalRows,
	];
}

function fetch_customer_workstation_activity(Database $db, int $customerId, array $filters = [], int $limit = 0, int $offset = 0): array
{
	ensure_cashpickup_customer_schema($db);
	ensure_cashpickup_columns($db);
	ensure_cashpickup_credit_schema($db);

	$customerRows = $db->query(
		"SELECT * FROM cashpickup_customers WHERE deleted = 0 AND id = :id LIMIT 1",
		['id' => $customerId]
	) ?: [];
	if (empty($customerRows)) {
		return ['customer' => null, 'rows' => [], 'total_rows' => 0];
	}
	$customer = $customerRows[0];
	list($matchSql, $matchParams) = build_customer_match_sql(
		$customerId,
		trim((string)($customer->phone_number ?? '')),
		trim((string)($customer->id_number ?? '')),
		'm',
		'workstation'
	);
	$transactions = $db->query(
		"SELECT DISTINCT m.*,
		        CASE WHEN COALESCE(m.ledger_transfer_from_customer_id, 0) > 0
		             THEN COALESCE(NULLIF(TRIM(transfer_from.full_name), ''), CONCAT('Customer #', m.ledger_transfer_from_customer_id))
		             ELSE '' END AS transfer_from_customer_name,
		        CASE WHEN COALESCE(m.ledger_transfer_to_customer_id, 0) > 0
		             THEN COALESCE(NULLIF(TRIM(transfer_to.full_name), ''), CONCAT('Customer #', m.ledger_transfer_to_customer_id))
		             ELSE '' END AS transfer_to_customer_name
		 FROM cashpickups m
		 LEFT JOIN cashpickup_customers transfer_from
		 	ON transfer_from.id = m.ledger_transfer_from_customer_id
		 LEFT JOIN cashpickup_customers transfer_to
		 	ON transfer_to.id = m.ledger_transfer_to_customer_id
		 WHERE (m.deleted IS NULL OR m.deleted = 0)
		 AND {$matchSql}
		 ORDER BY m.date_created ASC, m.id ASC",
		$matchParams
	) ?: [];

	$activity = [];
	$runningBalance = 0.0;
	foreach ($transactions as $transaction) {
		$side = strtolower(trim((string)($transaction->transaction_side ?? '')));
		$creditMode = strtolower(trim((string)($transaction->credit_mode ?? ''))) === 'credit';
		$transactionStatus = strtolower(trim((string)($transaction->transaction_status ?? '')));
		$approvalStatus = strtolower(trim((string)($transaction->approval_status ?? '')));
		$isActiveCredit = in_array($transactionStatus, ['approved', 'pending payout'], true)
			&& ($approvalStatus === '' || in_array($approvalStatus, ['approved', 'pending'], true));
		$receivedAmount = (!$creditMode && $side === 'receive')
			? (float)($transaction->amount_involved ?? 0)
			: 0.0;
		$payoutAmount = 0.0;
		if (!$creditMode && $side === 'pay') {
			$status = strtolower(trim((string)($transaction->transaction_status ?? '')));
			$approval = strtolower(trim((string)($transaction->approval_status ?? '')));
			$isActivePayout = $status === 'approved'
				&& trim((string)($transaction->confirmed_by ?? '')) !== ''
				&& (float)($transaction->amount_paid_out ?? 0) > 0
				&& ($approval === '' || in_array($approval, ['approved', 'pending'], true));
			if ($isActivePayout) {
				$payoutAmount = (float)($transaction->amount_paid_out ?? 0) + (float)($transaction->pickup_charge ?? 0);
			}
		}
		$runningBalance += $receivedAmount - $payoutAmount;
		$entryType = $creditMode
			? ($side === 'receive' ? 'credit_issued' : 'credit_payout')
			: ($side === 'receive' ? 'deposit' : 'payout');
		$activity[] = (object)[
			'id' => (int)($transaction->id ?? 0),
			'related_receive_id' => (int)($transaction->id ?? 0),
			'related_credit_id' => $creditMode && $side === 'receive' ? (int)($transaction->id ?? 0) : 0,
			'date_created' => (string)($transaction->date_created ?? ''),
			'entry_type' => $entryType,
			'transaction_side' => $side,
			'source' => (string)($transaction->source ?? ''),
			'payment_method' => (string)($transaction->payment_method ?? ''),
			'sender_details' => (string)($transaction->sender_details ?? ''),
			'transfer_from_customer_name' => (string)($transaction->transfer_from_customer_name ?? ''),
			'transfer_to_customer_name' => (string)($transaction->transfer_to_customer_name ?? ''),
			'phone_number' => (string)($transaction->phone_number ?? ''),
			'tracking_id' => (string)($transaction->tracking_id ?? ''),
			'approval_status' => (string)($transaction->approval_status ?? ''),
			'transaction_status' => (string)($transaction->transaction_status ?? ''),
			'received_amount' => round($receivedAmount, 2),
			'payout_amount' => round($payoutAmount, 2),
			'credit_amount' => $creditMode && $side === 'receive' && $isActiveCredit
				? round(max(0.0, (float)($transaction->amount_involved ?? 0)), 2)
				: ($creditMode && $side === 'pay'
					? round(max(0.0, (float)($transaction->amount_paid_out ?? $transaction->amount_involved ?? 0)), 2)
					: 0.0),
			'credit_repayment_amount' => 0.0,
			'balance_amount' => round($runningBalance, 2),
		];
	}

	$repaymentMatchParams = [
		'repayment_customer_id' => $customerId,
		'credit_customer_id' => $customerId,
		'receive_customer_id' => $customerId,
	];
	$repaymentMatchConditions = [
		'r.customer_id = :repayment_customer_id',
		'credit.customer_id = :credit_customer_id',
		'receive.customer_id = :receive_customer_id',
	];
	$customerPhone = trim((string)($customer->phone_number ?? ''));
	$customerIdNumber = trim((string)($customer->id_number ?? ''));
	if ($customerPhone !== '') {
		$repaymentMatchConditions[] = 'credit.phone_number = :credit_phone';
		$repaymentMatchConditions[] = 'receive.phone_number = :receive_phone';
		$repaymentMatchParams['credit_phone'] = $customerPhone;
		$repaymentMatchParams['receive_phone'] = $customerPhone;
	}
	if ($customerIdNumber !== '') {
		$repaymentMatchConditions[] = 'credit.customer_id_number = :credit_id_number';
		$repaymentMatchConditions[] = 'receive.customer_id_number = :receive_id_number';
		$repaymentMatchParams['credit_id_number'] = $customerIdNumber;
		$repaymentMatchParams['receive_id_number'] = $customerIdNumber;
	}
	$repayments = $db->query(
		"SELECT r.id, r.credit_id, r.receive_id, r.amount, r.created_by, r.date_created,
		        credit.tracking_id AS credit_tracking_id,
		        credit.sender_details AS credit_sender_details
		 FROM cashpickup_credit_repayments r
		 LEFT JOIN cashpickups credit ON credit.id = r.credit_id
		 LEFT JOIN cashpickups receive ON receive.id = r.receive_id
		 WHERE (" . implode(' OR ', $repaymentMatchConditions) . ")
		 ORDER BY r.date_created ASC, r.id ASC",
		$repaymentMatchParams
	) ?: [];
	foreach ($repayments as $repayment) {
		$activity[] = (object)[
			'id' => (int)($repayment->id ?? 0),
			'related_receive_id' => (int)(($repayment->receive_id ?? 0) ?: ($repayment->credit_id ?? 0)),
			'related_credit_id' => (int)($repayment->credit_id ?? 0),
			'date_created' => (string)($repayment->date_created ?? ''),
			'entry_type' => 'credit_repayment',
			'transaction_side' => 'repayment',
			'source' => 'credit_repayment',
			'payment_method' => '',
			'sender_details' => 'Credit repayment received',
			'phone_number' => '',
			'tracking_id' => (string)($repayment->credit_tracking_id ?? ''),
			'approval_status' => 'approved',
			'transaction_status' => 'Approved',
			'received_amount' => 0.0,
			'payout_amount' => 0.0,
			'credit_amount' => 0.0,
			'credit_repayment_amount' => round(max(0.0, (float)($repayment->amount ?? 0)), 2),
			'balance_amount' => 0.0,
		];
	}

	usort($activity, static function(object $left, object $right): int {
		$dateCompare = strcmp((string)$left->date_created, (string)$right->date_created);
		if ($dateCompare !== 0) {
			return $dateCompare;
		}
		$leftId = (int)$left->id;
		$rightId = (int)$right->id;
		if ($leftId !== $rightId) {
			return $leftId <=> $rightId;
		}
		return strcmp((string)$left->entry_type, (string)$right->entry_type);
	});

	$balance = 0.0;
	foreach ($activity as $row) {
		$balance += (float)$row->received_amount
			- (float)$row->payout_amount
			- ($row->entry_type === 'credit_issued' ? (float)$row->credit_amount : 0.0);
		$row->balance_amount = round($balance, 2);
	}

	$find = strtolower(trim((string)($filters['find'] ?? '')));
	$dateFrom = trim((string)($filters['date_from'] ?? ''));
	$dateTo = trim((string)($filters['date_to'] ?? ''));
	$side = strtolower(trim((string)($filters['side'] ?? '')));
	$activity = array_values(array_filter($activity, static function(object $row) use ($find, $dateFrom, $dateTo, $side): bool {
		$date = substr((string)$row->date_created, 0, 10);
		if ($dateFrom !== '' && $date < $dateFrom) {
			return false;
		}
		if ($dateTo !== '' && $date > $dateTo) {
			return false;
		}
		if ($side !== '' && $side !== 'all') {
			if ($side === 'receive' && !in_array($row->entry_type, ['deposit', 'credit_issued'], true)) return false;
			if ($side === 'pay' && !in_array($row->entry_type, ['payout', 'credit_payout'], true)) return false;
			if ($side === 'credit_repayment' && $row->entry_type !== 'credit_repayment') return false;
		}
		if ($find !== '') {
			$haystack = strtolower(implode(' ', [
				(string)$row->id,
				(string)$row->entry_type,
				(string)$row->source,
				(string)$row->payment_method,
				(string)$row->sender_details,
				(string)$row->tracking_id,
			]));
			if (strpos($haystack, $find) === false) return false;
		}
		return true;
	}));
	usort($activity, static function(object $left, object $right): int {
		return strcmp((string)$right->date_created, (string)$left->date_created)
			?: ((int)$right->id <=> (int)$left->id);
	});

	$totalRows = count($activity);
	if ($limit > 0) {
		$activity = array_slice($activity, max(0, $offset), $limit);
	}
	return ['customer' => $customer, 'rows' => $activity, 'total_rows' => $totalRows];
}

function build_general_ledger_where(array $filters = [], string $alias = 'm'): array
{
	$alias = trim($alias) !== '' ? trim($alias) : 'm';
	$find = trim((string)($filters['find'] ?? ''));
	$dateFrom = trim((string)($filters['date_from'] ?? ''));
	$dateTo = trim((string)($filters['date_to'] ?? ''));

	$params = [];
	$conditions = [
		"({$alias}.deleted IS NULL OR {$alias}.deleted = 0)",
		statement_financial_where_sql($alias),
	];
	if ($find !== '') {
		$conditions[] = "(CAST({$alias}.id AS CHAR) LIKE :find
			OR COALESCE({$alias}.tracking_id,'') LIKE :find
			OR COALESCE({$alias}.sender_details,'') LIKE :find
			OR COALESCE({$alias}.phone_number,'') LIKE :find
			OR COALESCE({$alias}.source,'') LIKE :find)";
		$params['find'] = '%' . $find . '%';
	}
	if ($dateFrom !== '') {
		$conditions[] = "DATE({$alias}.date_created) >= :date_from";
		$params['date_from'] = $dateFrom;
	}
	if ($dateTo !== '') {
		$conditions[] = "DATE({$alias}.date_created) <= :date_to";
		$params['date_to'] = $dateTo;
	}

	return ['WHERE ' . implode(' AND ', $conditions), $params];
}

function fetch_general_ledger_metrics(Database $db, array $filters = []): array
{
	ensure_cashpickup_customer_schema($db);
	ensure_cashpickup_columns($db);
	ensure_cashpickup_credit_schema($db);

	list($whereSql, $params) = build_general_ledger_where($filters, 'm');

	$rows = $db->query(
		"SELECT
			COALESCE(SUM(CASE
				WHEN LOWER(TRIM(COALESCE(m.transaction_side,''))) = 'receive'
					AND LOWER(TRIM(COALESCE(m.credit_mode,''))) <> 'credit'
				THEN COALESCE(m.amount_involved,0)
				ELSE 0
			END),0) AS total_received,
			COALESCE(SUM(CASE
				WHEN LOWER(TRIM(COALESCE(m.transaction_side,''))) = 'pay'
					AND LOWER(TRIM(COALESCE(m.transaction_status,''))) = 'approved'
					AND (m.confirmed_by IS NOT NULL AND m.confirmed_by != '')
					AND COALESCE(m.amount_paid_out,0) > 0
					AND " . normalized_cashpickup_credit_mode_sql('m') . " <> 'credit'
					AND (
						m.approval_status IS NULL
						OR m.approval_status = ''
						OR LOWER(TRIM(COALESCE(m.approval_status,''))) IN ('approved','pending')
					)
				THEN (COALESCE(m.amount_paid_out,0) + COALESCE(m.pickup_charge,0))
				ELSE 0
			END),0) AS total_paid_out
		 FROM cashpickups m
		 {$whereSql}",
		$params
	) ?: [];

	$totalReceived = (float)($rows[0]->total_received ?? 0);
	$totalPaidOut = (float)($rows[0]->total_paid_out ?? 0);
	$creditMetrics = fetch_credit_overview_metrics($db, $filters);
	$totalCreditGiven = (float)($creditMetrics['total_credit_given'] ?? 0);
	return [
		'total_received' => $totalReceived,
		'total_paid_out' => $totalPaidOut,
		'total_credit_given' => $totalCreditGiven,
		'balance' => (float)($totalReceived - $totalPaidOut - $totalCreditGiven),
	];
}

function fetch_customer_grouped_ledger(Database $db, int $customerId): array
{
	ensure_cashpickup_customer_schema($db);
	ensure_cashpickup_columns($db);

	$customerRows = $db->query(
		"SELECT * FROM cashpickup_customers WHERE deleted = 0 AND id = :id LIMIT 1",
		['id' => $customerId]
	) ?: [];
	if (empty($customerRows)) {
		return [null, []];
	}

	$customer = $customerRows[0];
	$phone = trim((string)($customer->phone_number ?? ''));
	$idNumber = trim((string)($customer->id_number ?? ''));
	list($matchSql, $params) = build_customer_match_sql($customerId, $phone, $idNumber, 'm', 'customer_grouped');
	$where = "WHERE m.deleted = 0
		AND LOWER(m.transaction_side) = 'receive'
		AND {$matchSql}";
	$trackingGroupExpr = "CASE WHEN COALESCE(m.tracking_id,'') <> '' THEN m.tracking_id ELSE CONCAT('cashpickup-', m.id) END";

	$rows = $db->query(
		"SELECT m.*, u.first_name, u.last_name,
		        au.first_name AS assigned_first_name, au.last_name AS assigned_last_name,
		        grp.group_received,
		        grp.receive_parts,
		        grp.tracking_group,
		        0 AS paid_requested,
		        0 AS paid_out,
		        0 AS payout_parts,
		        0 AS pending_reserved,
		        0 AS pending_parts
		 FROM (
			SELECT MAX(m.id) AS row_id,
			       {$trackingGroupExpr} AS tracking_group,
			       COALESCE(SUM(
			       	CASE
						WHEN " . normalized_cashpickup_credit_mode_sql('m') . " = 'credit' THEN 0
			       		ELSE COALESCE(m.amount_involved,0)
			       	END
			       ),0) AS group_received,
			       COUNT(*) AS receive_parts
			FROM cashpickups AS m
			{$where}
			GROUP BY tracking_group
		 ) AS grp
		 JOIN cashpickups AS m ON m.id = grp.row_id
		 LEFT JOIN users AS u ON m.created_by = u.id
		 LEFT JOIN users AS au ON m.assigned_user_id = au.id
		 ORDER BY m.id DESC",
		$params
	) ?: [];

	if (empty($rows)) {
		return [$customer, []];
	}

	$pageReceiveIds = [];
	$pageTrackingGroups = [];
	$receiveGroupById = [];
	foreach ($rows as $row) {
		$receiveId = (int)($row->id ?? 0);
		$group = trim((string)($row->tracking_group ?? ''));
		if ($group === '' && $receiveId > 0) {
			$group = 'cashpickup-' . $receiveId;
			$row->tracking_group = $group;
		}
		if ($receiveId > 0) {
			$pageReceiveIds[$receiveId] = $receiveId;
			$receiveGroupById[$receiveId] = $group;
		}
		if ($group !== '') {
			$pageTrackingGroups[$group] = $group;
		}
	}

	$makePlaceholders = static function(array $values, string $prefix, array &$params): array {
		$placeholders = [];
		$index = 0;
		foreach ($values as $value) {
			$key = $prefix . $index++;
			$placeholders[] = ':' . $key;
			$params[$key] = $value;
		}
		return $placeholders;
	};

	$payConditions = [];
	$payParams = [];
	if (!empty($pageTrackingGroups)) {
		$payConditions[] = 'tracking_id IN (' . implode(',', $makePlaceholders(array_values($pageTrackingGroups), 'ptg', $payParams)) . ')';
	}
	if (!empty($pageReceiveIds)) {
		$payConditions[] = 'receive_id IN (' . implode(',', $makePlaceholders(array_values($pageReceiveIds), 'prid', $payParams)) . ')';
	}
	if (!empty($payConditions)) {
		$payRows = $db->query(
			"SELECT receive_id, tracking_id, approval_status, transaction_status, confirmed_by, amount_paid_out, pickup_charge
			 FROM cashpickups
			 WHERE (deleted IS NULL OR deleted = 0)
			 AND LOWER(transaction_side) = 'pay'
			 AND (" . implode(' OR ', $payConditions) . ')',
			$payParams
		) ?: [];
		$paidByGroup = [];
		foreach ($payRows as $payRow) {
			$receiveId = (int)($payRow->receive_id ?? 0);
			$tracking = trim((string)($payRow->tracking_id ?? ''));
			$group = ($receiveId > 0 && isset($receiveGroupById[$receiveId])) ? $receiveGroupById[$receiveId] : $tracking;
			if ($group === '' && $receiveId > 0) {
				$group = 'cashpickup-' . $receiveId;
			}
			if ($group === '' || !isset($pageTrackingGroups[$group])) {
				continue;
			}
			if (!isset($paidByGroup[$group])) {
				$paidByGroup[$group] = [
					'paid_requested' => 0.0,
					'paid_out' => 0.0,
					'payout_parts' => 0,
					'pending_reserved' => 0.0,
					'pending_parts' => 0,
				];
			}
			$approval = strtolower(trim((string)($payRow->approval_status ?? '')));
			$txStatus = strtolower(trim((string)($payRow->transaction_status ?? '')));
			$confirmedBy = trim((string)($payRow->confirmed_by ?? ''));
			$paidOut = (float)($payRow->amount_paid_out ?? 0);
			$gross = $paidOut + (float)($payRow->pickup_charge ?? 0);
			$isActivePay = ($approval === '' || in_array($approval, ['approved', 'pending'], true))
				&& $txStatus === 'approved'
				&& $confirmedBy !== ''
				&& $paidOut > 0;
			if (!$isActivePay) {
				continue;
			}
			$paidByGroup[$group]['paid_requested'] += $gross;
			$paidByGroup[$group]['paid_out'] += $paidOut;
			$paidByGroup[$group]['payout_parts']++;
			if ($approval === 'pending') {
				$paidByGroup[$group]['pending_reserved'] += $gross;
				$paidByGroup[$group]['pending_parts']++;
			}
		}

		foreach ($rows as $row) {
			$group = trim((string)($row->tracking_group ?? ''));
			$stats = $paidByGroup[$group] ?? null;
			if ($stats === null) {
				continue;
			}
			$row->paid_requested = (float)$stats['paid_requested'];
			$row->paid_out = (float)$stats['paid_out'];
			$row->payout_parts = (int)$stats['payout_parts'];
			$row->pending_reserved = (float)$stats['pending_reserved'];
			$row->pending_parts = (int)$stats['pending_parts'];
		}
	}

	foreach ($rows as $row) {
		$isCreditPayout = strtolower(trim((string)($row->credit_mode ?? ''))) === 'credit';
		$receivedAmount = (float)($row->group_received ?? $row->amount_involved ?? 0);
		$paidRequested = (float)($row->paid_requested ?? 0);
		$comparisonAmount = $isCreditPayout
			? (float)($row->amount_involved ?? 0)
			: $receivedAmount;
		$remaining = $isCreditPayout
			? (0 - $paidRequested)
			: ($receivedAmount - $paidRequested);
		$row->remaining_amount = round($remaining, 2);
		$row->payout_status = $comparisonAmount <= 0.00001
			? ($paidRequested > 0 ? 'Paid' : 'Not paid')
			: ($paidRequested >= ($comparisonAmount - 0.00001) ? 'Paid' : ($paidRequested > 0 ? 'Partial' : 'Not paid'));
		$row->approval_status_label = ucfirst(strtolower(trim((string)($row->approval_status ?? 'pending')) ?: 'pending'));
	}

	return [$customer, $rows];
}

function credit_paid_out_amount_sql(string $alias = ''): string
{
	$prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';
	return "(CASE
		WHEN LOWER(TRIM(COALESCE({$prefix}transaction_side,''))) = 'pay'
			AND " . normalized_cashpickup_credit_mode_sql($alias) . " = 'credit'
			AND LOWER(TRIM(COALESCE({$prefix}transaction_status,''))) = 'approved'
			AND ({$prefix}confirmed_by IS NOT NULL AND {$prefix}confirmed_by != '')
			AND COALESCE({$prefix}amount_paid_out,0) > 0
			AND (
				{$prefix}approval_status IS NULL
				OR {$prefix}approval_status = ''
				OR LOWER(TRIM(COALESCE({$prefix}approval_status,''))) IN ('approved','pending')
			)
		THEN (COALESCE({$prefix}amount_paid_out,0) + COALESCE({$prefix}pickup_charge,0))
		ELSE 0
	END)";
}

function fetch_customer_credit_metrics(Database $db, int $customerId): array
{
	ensure_cashpickup_customer_schema($db);
	ensure_cashpickup_columns($db);
	ensure_cashpickup_credit_schema($db);

	$customerRows = $db->query(
		"SELECT * FROM cashpickup_customers WHERE deleted = 0 AND id = :id LIMIT 1",
		['id' => $customerId]
	) ?: [];
	if (empty($customerRows)) {
		return [
			'total_credit_given' => 0.0,
			'total_credit_paid' => 0.0,
			'total_amount_in_credit' => 0.0,
		];
	}

	$customer = $customerRows[0];
	$phone = trim((string)($customer->phone_number ?? ''));
	$idNumber = trim((string)($customer->id_number ?? ''));
	list($matchSql, $params) = build_customer_match_sql($customerId, $phone, $idNumber, 'cr', 'credit');

	$rows = $db->query(
		"SELECT
			COALESCE(SUM(
				CASE
					WHEN LOWER(TRIM(COALESCE(cr.transaction_status,''))) IN ('approved','pending payout')
					AND (
						cr.approval_status IS NULL
						OR cr.approval_status = ''
						OR LOWER(TRIM(COALESCE(cr.approval_status,''))) IN ('approved','pending')
					)
					THEN COALESCE(cr.amount_involved,0)
					ELSE 0
				END
			),0) AS total_credit_given,
			COALESCE(SUM(COALESCE(repaid.total_credit_paid,0)),0) AS total_credit_paid
		 FROM cashpickups cr
		 LEFT JOIN (
			SELECT credit_id, COALESCE(SUM(amount),0) AS total_credit_paid
			FROM cashpickup_credit_repayments
			GROUP BY credit_id
		 ) AS repaid ON repaid.credit_id = cr.id
		 WHERE (cr.deleted IS NULL OR cr.deleted = 0)
		 AND LOWER(TRIM(COALESCE(cr.transaction_side,''))) = 'receive'
		 AND " . normalized_cashpickup_credit_mode_sql('cr') . " = 'credit'
		 AND {$matchSql}",
	$params
	) ?: [];
	$totalCreditGiven = (float)($rows[0]->total_credit_given ?? 0);
	$totalCreditPaid = (float)($rows[0]->total_credit_paid ?? 0);
	return [
		'total_credit_given' => $totalCreditGiven,
		'total_credit_paid' => $totalCreditPaid,
		'total_amount_in_credit' => round(max(0, $totalCreditGiven - $totalCreditPaid), 2),
	];
}

function fetch_credit_overview_metrics(Database $db, array $filters = []): array
{
	ensure_cashpickup_customer_schema($db);
	ensure_cashpickup_columns($db);
	ensure_cashpickup_credit_schema($db);

	$find = trim((string)($filters['find'] ?? ''));
	$dateFrom = trim((string)($filters['date_from'] ?? ''));
	$dateTo = trim((string)($filters['date_to'] ?? ''));
	$params = [];
	$whereParts = [
		'(cr.deleted IS NULL OR cr.deleted = 0)',
		"LOWER(TRIM(COALESCE(cr.transaction_side,''))) = 'receive'",
		"" . normalized_cashpickup_credit_mode_sql('cr') . " = 'credit'",
	];
	if ($find !== '') {
		$whereParts[] = "(
			LOWER(COALESCE(c.full_name,'')) LIKE :find
			OR LOWER(COALESCE(c.phone_number,'')) LIKE :find
			OR LOWER(COALESCE(c.id_number,'')) LIKE :find
		)";
		$params['find'] = '%' . strtolower($find) . '%';
	}
	if ($dateFrom !== '') {
		$whereParts[] = "DATE(cr.date_created) >= :date_from";
		$params['date_from'] = $dateFrom;
	}
	if ($dateTo !== '') {
		$whereParts[] = "DATE(cr.date_created) <= :date_to";
		$params['date_to'] = $dateTo;
	}

	$joinCustomer = $find !== '' ? 'LEFT JOIN cashpickup_customers c ON c.id = cr.customer_id' : '';
	$rows = $db->query(
		"SELECT
			COALESCE(SUM(
				CASE
					WHEN LOWER(TRIM(COALESCE(cr.transaction_status,''))) IN ('approved','pending payout')
					AND (
						cr.approval_status IS NULL
						OR cr.approval_status = ''
						OR LOWER(TRIM(COALESCE(cr.approval_status,''))) IN ('approved','pending')
					)
					THEN COALESCE(cr.amount_involved,0)
					ELSE 0
				END
			),0) AS total_credit_given,
			COALESCE(SUM(COALESCE(repaid.total_credit_paid,0)),0) AS total_credit_paid
		 FROM cashpickups cr
		 " . $joinCustomer . "
		 LEFT JOIN (
			SELECT credit_id, COALESCE(SUM(amount),0) AS total_credit_paid
			FROM cashpickup_credit_repayments
			GROUP BY credit_id
		 ) AS repaid ON repaid.credit_id = cr.id
		 WHERE " . implode(' AND ', $whereParts),
		$params
	) ?: [];

	$totalCreditGiven = (float)($rows[0]->total_credit_given ?? 0);
	$totalCreditPaid = (float)($rows[0]->total_credit_paid ?? 0);
	return [
		'total_credit_given' => $totalCreditGiven,
		'total_credit_paid' => $totalCreditPaid,
		'total_amount_in_credit' => round(max(0, $totalCreditGiven - $totalCreditPaid), 2),
	];
}

function fetch_customer_credit_rows(Database $db, int $customerId, int $limit = 50): array
{
	ensure_cashpickup_customer_schema($db);
	ensure_cashpickup_columns($db);
	ensure_cashpickup_credit_schema($db);

	$customerRows = $db->query(
		"SELECT * FROM cashpickup_customers WHERE deleted = 0 AND id = :id LIMIT 1",
		['id' => $customerId]
	) ?: [];
	if (empty($customerRows)) {
		return [];
	}

	$customer = $customerRows[0];
	$phone = trim((string)($customer->phone_number ?? ''));
	$idNumber = trim((string)($customer->id_number ?? ''));
	list($matchSql, $params) = build_customer_match_sql($customerId, $phone, $idNumber, 'cr', 'credit_rows');
	$limit = $limit > 0 ? min(200, $limit) : 50;

	return $db->query(
		"SELECT
			cr.id,
			cr.date_created,
			cr.tracking_id,
			cr.amount_involved AS authorized_amount,
			cr.pickup_charge AS default_charge,
			cr.approval_status,
			cr.notes,
			cr.assigned_user_id,
			u.first_name AS assigned_first_name,
			u.last_name AS assigned_last_name,
			CASE
				WHEN LOWER(COALESCE(cr.transaction_status,'')) IN ('approved','pending payout')
				AND (
					cr.approval_status IS NULL
					OR cr.approval_status = ''
					OR LOWER(cr.approval_status) IN ('approved','pending')
				)
				THEN COALESCE(cr.amount_involved,0)
				ELSE 0
			END AS total_credit_given,
			CASE
				WHEN LOWER(COALESCE(cr.transaction_status,'')) IN ('approved','pending payout')
				AND (
					cr.approval_status IS NULL
					OR cr.approval_status = ''
					OR LOWER(cr.approval_status) IN ('approved','pending')
				)
				THEN 1
				ELSE 0
			END AS payout_parts,
			COALESCE(repaid.total_credit_paid,0) AS total_credit_paid,
			GREATEST(0,
				(CASE
					WHEN LOWER(COALESCE(cr.transaction_status,'')) IN ('approved','pending payout')
					AND (
						cr.approval_status IS NULL
						OR cr.approval_status = ''
						OR LOWER(cr.approval_status) IN ('approved','pending')
					)
					THEN COALESCE(cr.amount_involved,0)
					ELSE 0
				END) - COALESCE(repaid.total_credit_paid,0)
			) AS total_credit_outstanding
		 FROM cashpickups cr
		 LEFT JOIN users u ON u.id = cr.assigned_user_id
		 LEFT JOIN (
			SELECT credit_id, COALESCE(SUM(amount),0) AS total_credit_paid
			FROM cashpickup_credit_repayments
			GROUP BY credit_id
		 ) AS repaid ON repaid.credit_id = cr.id
		 WHERE (cr.deleted IS NULL OR cr.deleted = 0)
		 AND LOWER(cr.transaction_side) = 'receive'
			AND " . normalized_cashpickup_credit_mode_sql('cr') . " = 'credit'
		 AND {$matchSql}
		 ORDER BY cr.date_created DESC, cr.id DESC
		 LIMIT {$limit}",
		$params
	) ?: [];
}

function apply_receive_credit_repayments(Database $db, int $receiveId, int $actorId = 0): void
{
	if ($receiveId <= 0) {
		return;
	}

	ensure_cashpickup_columns($db);
	ensure_cashpickup_credit_schema($db);

	try {
		$receiveRows = $db->query(
			"SELECT id, customer_id, phone_number, customer_id_number, amount_involved, approval_status, credit_mode
			 FROM cashpickups
			 WHERE (deleted IS NULL OR deleted = 0)
			 AND LOWER(transaction_side) = 'receive'
			 AND id = :id
			 LIMIT 1",
			['id' => $receiveId]
		) ?: [];
		if (empty($receiveRows)) {
			return;
		}

		$receiveRow = $receiveRows[0];
		if (strtolower(trim((string)($receiveRow->credit_mode ?? ''))) === 'credit') {
			return;
		}

		$approvalStatus = strtolower(trim((string)($receiveRow->approval_status ?? 'approved')));
		if ($approvalStatus !== '' && $approvalStatus !== 'approved') {
			return;
		}

		$receiveAmount = round(max(0.0, (float)($receiveRow->amount_involved ?? 0)), 2);
		if ($receiveAmount <= 0.00001) {
			return;
		}

		$alreadyRows = $db->query(
			"SELECT COALESCE(SUM(amount),0) AS settled
			 FROM cashpickup_credit_repayments
			 WHERE receive_id = :receive_id",
			['receive_id' => $receiveId]
		) ?: [];
		$settledForReceive = round(max(0.0, (float)($alreadyRows[0]->settled ?? 0)), 2);
		$remainingToApply = round(max(0.0, $receiveAmount - $settledForReceive), 2);
		if ($remainingToApply <= 0.00001) {
			return;
		}

		$customerId = (int)($receiveRow->customer_id ?? 0);
		$phone = trim((string)($receiveRow->phone_number ?? ''));
		$idNumber = trim((string)($receiveRow->customer_id_number ?? ''));
		list($matchSql, $params) = build_customer_match_sql($customerId, $phone, $idNumber, 'cr', 'repay');

		$creditRows = $db->query(
			"SELECT
				cr.id,
				CASE
					WHEN LOWER(COALESCE(cr.transaction_status,'')) = 'approved'
					AND (
						cr.approval_status IS NULL
						OR cr.approval_status = ''
						OR LOWER(cr.approval_status) IN ('approved','pending')
					)
					THEN COALESCE(cr.amount_involved,0)
					ELSE 0
				END AS total_credit_given,
				COALESCE(repaid.total_credit_paid,0) AS total_credit_paid
			 FROM cashpickups cr
			 LEFT JOIN (
				SELECT credit_id, COALESCE(SUM(amount),0) AS total_credit_paid
				FROM cashpickup_credit_repayments
				GROUP BY credit_id
			 ) AS repaid ON repaid.credit_id = cr.id
			 WHERE (cr.deleted IS NULL OR cr.deleted = 0)
			 AND LOWER(cr.transaction_side) = 'receive'
			 AND " . normalized_cashpickup_credit_mode_sql('cr') . " = 'credit'
			 AND {$matchSql}
			 ORDER BY cr.date_created ASC, cr.id ASC",
			$params
		) ?: [];

		if (empty($creditRows)) {
			return;
		}

		$now = date('Y-m-d H:i:s');
		foreach ($creditRows as $creditRow) {
			if ($remainingToApply <= 0.00001) {
				break;
			}

			$creditId = (int)($creditRow->id ?? 0);
			$totalCreditGiven = round(max(0.0, (float)($creditRow->total_credit_given ?? 0)), 2);
			$totalCreditPaid = round(max(0.0, (float)($creditRow->total_credit_paid ?? 0)), 2);
			$outstanding = round(max(0.0, $totalCreditGiven - $totalCreditPaid), 2);
			if ($creditId <= 0 || $outstanding <= 0.00001) {
				continue;
			}

			$applyAmount = round(min($remainingToApply, $outstanding), 2);
			if ($applyAmount <= 0.00001) {
				continue;
			}

			$db->query(
				"INSERT INTO cashpickup_credit_repayments (credit_id, receive_id, customer_id, amount, created_by, date_created)
				 VALUES (:credit_id, :receive_id, :customer_id, :amount, :created_by, :date_created)
				 ON DUPLICATE KEY UPDATE amount = :amount_update",
				[
					'credit_id' => $creditId,
					'receive_id' => $receiveId,
					'customer_id' => $customerId > 0 ? $customerId : null,
					'amount' => $applyAmount,
					'created_by' => $actorId > 0 ? $actorId : null,
					'date_created' => $now,
					'amount_update' => $applyAmount,
				]
			);

			$remainingToApply = round(max(0.0, $remainingToApply - $applyAmount), 2);
		}
	} catch (\Throwable $e) {
		// Credit recovery is secondary bookkeeping; never block the main receive flow.
	}
}

function fetch_general_ledger_page(Database $db, array $filters = [], int $limit = 10, int $offset = 0): array
{
	ensure_cashpickup_customer_schema($db);
	ensure_cashpickup_columns($db);
	ensure_cashpickup_credit_schema($db);

	$order = strtolower(trim((string)($filters['order'] ?? 'desc'))) === 'asc' ? 'ASC' : 'DESC';
	list($whereSql, $params) = build_general_ledger_where($filters, 'm');
	list($runningWhereSql, $runningParams) = build_general_ledger_where($filters, 'gl');
	$countRows = $db->query(
		"SELECT COUNT(*) AS c
		 FROM cashpickups m
		 {$whereSql}",
		$params
	) ?: [];
	$totalRows = (int)($countRows[0]->c ?? 0);

	$receivedSql = statement_received_amount_sql('m');
	$payoutSql = active_payout_amount_sql('m');
	$creditIssuedSql = statement_credit_issued_amount_sql('m');
	$creditRepaymentSql = statement_credit_repayment_amount_sql('m');
	$runningDeltaSql = statement_delta_sql('gl');
	$limitSql = '';
	if ($limit > 0) {
		$limitSql = ' LIMIT ' . (int)$limit . ' OFFSET ' . max(0, (int)$offset);
	}

	$rows = $db->query(
		"SELECT
			m.id,
			m.date_created,
			m.tracking_id,
			m.sender_details,
			m.phone_number,
			m.source,
			m.payment_method,
			m.transaction_side,
			{$receivedSql} AS received_amount,
			{$payoutSql} AS payout_amount,
			{$creditIssuedSql} AS credit_amount,
			{$creditRepaymentSql} AS credit_repayment_amount
		 FROM cashpickups m
		 {$whereSql}
		 ORDER BY m.date_created {$order}, m.id {$order}
		 {$limitSql}",
		$params
	) ?: [];

	if (!empty($rows)) {
		if ($order === 'ASC') {
			$runningBalance = 0.0;
			foreach ($rows as $row) {
				$runningBalance += (float)($row->received_amount ?? 0)
					- (float)($row->payout_amount ?? 0)
					- (float)($row->credit_amount ?? 0);
				$row->balance_amount = round($runningBalance, 2);
			}
		} else {
			$oldestRow = $rows[count($rows) - 1];
			$prefixParams = $runningParams;
			$prefixParams['balance_date'] = (string)($oldestRow->date_created ?? '');
			$prefixParams['balance_id'] = (int)($oldestRow->id ?? 0);
			$prefixRows = $db->query(
				"SELECT COALESCE(SUM({$runningDeltaSql}),0) AS balance_amount
				 FROM cashpickups gl
				 {$runningWhereSql}
				 AND (
					gl.date_created < :balance_date
					OR (gl.date_created = :balance_date AND gl.id < :balance_id)
				 )",
				$prefixParams
			) ?: [];
			$runningBalance = (float)($prefixRows[0]->balance_amount ?? 0);
			for ($idx = count($rows) - 1; $idx >= 0; $idx--) {
				$runningBalance += (float)($rows[$idx]->received_amount ?? 0)
					- (float)($rows[$idx]->payout_amount ?? 0)
					- (float)($rows[$idx]->credit_amount ?? 0);
				$rows[$idx]->balance_amount = round($runningBalance, 2);
			}
		}
	}

	return [
		'rows' => $rows,
		'total_rows' => $totalRows,
	];
}

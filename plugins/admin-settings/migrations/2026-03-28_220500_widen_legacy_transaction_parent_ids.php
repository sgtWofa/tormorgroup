<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

class Widen_legacy_transaction_parent_ids extends Migration
{
	private array $tables = [
		'agencybankings',
		'airtimebundles',
		'bank_cits',
		'bank_deposits',
		'bank_sendings',
		'bank_transfers',
		'bank_withdrawals',
		'cashdrawers',
		'cfas',
		'cits',
		'commissions',
		'deposits',
		'ezwichs',
		'interbanks',
		'momoremits',
		'payments',
		'payments_backoffice',
		'payment_services',
		'reconciliations',
		'reconciliations_summary',
		'requests',
		'requests_backoffice',
		'reversals',
		'sendings',
		'sims',
		'transactions',
		'withdrawals',
	];

	private function getColumnMeta(string $table): ?array
	{
		if (!$this->table_exists([$table])) {
			return null;
		}

		$rows = $this->query("SHOW COLUMNS FROM {$table} LIKE 'id'");
		if (!$rows || empty($rows[0]->Type)) {
			return null;
		}

		return [
			'type' => strtolower((string)$rows[0]->Type),
			'null' => strtoupper((string)($rows[0]->Null ?? 'YES')),
			'extra' => strtolower((string)($rows[0]->Extra ?? '')),
		];
	}

	private function buildDefinition(array $meta, bool $bigint): string
	{
		$isUnsigned = strpos($meta['type'], 'unsigned') !== false;
		$baseType = $bigint ? 'BIGINT(20)' : ($isUnsigned ? 'INT(10)' : 'INT(11)');
		$definition = $baseType . ($isUnsigned ? ' UNSIGNED' : '');
		$definition .= ($meta['null'] === 'NO') ? ' NOT NULL' : ' NULL';
		if (strpos($meta['extra'], 'auto_increment') !== false) {
			$definition .= ' AUTO_INCREMENT';
		}
		return $definition;
	}

	public function up()
	{
		foreach ($this->tables as $table) {
			$meta = $this->getColumnMeta($table);
			if ($meta === null || strpos($meta['type'], 'bigint') !== false) {
				continue;
			}

			$this->query("ALTER TABLE {$table} MODIFY COLUMN id " . $this->buildDefinition($meta, true));
		}
	}

	public function down()
	{
		foreach ($this->tables as $table) {
			$meta = $this->getColumnMeta($table);
			if ($meta === null || strpos($meta['type'], 'bigint') === false) {
				continue;
			}

			$this->query("ALTER TABLE {$table} MODIFY COLUMN id " . $this->buildDefinition($meta, false));
		}
	}
}

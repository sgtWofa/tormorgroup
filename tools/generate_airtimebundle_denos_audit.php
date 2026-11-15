<?php

declare(strict_types=1);

define('FCPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

require_once FCPATH . 'config.php';
require_once FCPATH . 'plugins/airtimebundle/helpers/denos_audit.php';

$report = airtimebundle_generate_denos_audit_report(true);
$files = $report['files'] ?? [];

echo "Generated audit report in: " . ($files['output_dir'] ?? '') . PHP_EOL;
echo "Summary: " . ($files['summary'] ?? '') . PHP_EOL;
echo "Batches: " . ($files['batches'] ?? '') . PHP_EOL;
echo "Transactions: " . ($files['transactions'] ?? '') . PHP_EOL;

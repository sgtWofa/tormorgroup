<?php

require_once plugin_path('helpers/cashdrawer-history.php');

date_default_timezone_set('Africa/Accra');

if (empty($row))
{
	message_fail("Record not found");
	return;
}

$cashdrawer = new \MyWallets\Cashdrawer;

// Load CFA cashdrawer sync helper (if available) to mirror CFA denominations
$cfaHelperPath = realpath(__DIR__ . '/../../cfa/helpers/cashdrawer-sync.php');
if ($cfaHelperPath && file_exists($cfaHelperPath)) {
	require_once $cfaHelperPath;
}

$postdata = $req->post();
$currencyDetails = $postdata['denos'] ?? null;

// Collect dynamic e-cash channels coming from the form.
$ecashChannels = [];
$ecashProofUploads = [];
foreach ($postdata as $field => $value) {
	if (strpos($field, 'ecash_') !== 0) {
		continue;
	}

	$amount = (float) $value;
	if ($amount < 0) {
		continue;
	}
	if (!is_finite($amount)) {
		$amount = 0;
	}

	$rawKey = strtoupper(substr($field, 6));
	$sanitizedKey = preg_replace('/[^A-Z0-9]+/', '_', $rawKey);
	$sanitizedKey = trim($sanitizedKey, '_');
	if (empty($sanitizedKey)) {
		$sanitizedKey = 'CUSTOM';
	}
	if (in_array($sanitizedKey, ['EZWICH', 'E_ZWICH'], true)) {
		continue;
	}

	$channelKey = 'ECASH_' . $sanitizedKey;
	if (!user_can('is_admin') && operations_account_is_shared_channel_key($channelKey)) {
		continue;
	}
	$ecashChannels[$channelKey] = $amount;
	$ecashProofUploads[$channelKey] = 'ecash_proof_' . strtolower(substr($field, 6));
}

$currencyArray = is_string($currencyDetails) ? json_decode($currencyDetails, true) : null;

$postdata['id'] = $row->id;
$csrf = csrf_verify($postdata);
if (!$csrf) {
	$cashdrawer->errors['user_id'] = "Form expired!";
	set_value('errors', array_merge($cashdrawer->errors, $req->upload_errors));
	return;
}

if (!user_can('edit_wallet'))
{
	message_fail('Access denied');
	return;
}

if (!array_key_exists('denos', $postdata) || !is_array($currencyArray)) {
	$cashdrawer->errors['denos'] = 'Opening denomination data is missing or invalid. No drawer data was changed.';
	set_value('errors', array_merge($cashdrawer->errors, $req->upload_errors));
	return;
}
foreach ($currencyArray as $denominationRow) {
	if (!is_array($denominationRow)) {
		$cashdrawer->errors['denos'] = 'Opening denomination data contains an invalid entry. No drawer data was changed.';
		set_value('errors', array_merge($cashdrawer->errors, $req->upload_errors));
		return;
	}
	$currencyCode = strtoupper(trim((string)($denominationRow['currency'] ?? 'GHS')));
	$denomination = $denominationRow['denomination'] ?? null;
	$count = $denominationRow['count'] ?? null;
	if (
		!in_array($currencyCode, ['GHS', 'CFA'], true)
		|| !is_numeric($denomination)
		|| !is_numeric($count)
		|| !is_finite((float)$denomination)
		|| (float)$denomination <= 0
		|| !is_finite((float)$count)
		|| (float)$count < 0
		|| floor((float)$count) !== (float)$count
	) {
		$cashdrawer->errors['denos'] = 'Opening denomination data contains an invalid currency, denomination, or count. No drawer data was changed.';
		set_value('errors', array_merge($cashdrawer->errors, $req->upload_errors));
		return;
	}
}

try {
	operations_account_ensure_denomination_history_schema(new \Core\Database);
} catch (\Throwable $e) {
	error_log('[EditCashdrawer] Unable to prepare denomination history: ' . $e->getMessage());
	$cashdrawer->errors['denos'] = 'Cashdrawer history could not be prepared safely. No changes were made.';
	set_value('errors', array_merge($cashdrawer->errors, $req->upload_errors));
	return;
}

// Upload proofs (only required when amount > 0 and no existing proof exists)
$existingProofs = [];
try {
	$db = new \Core\Database;
	if ($db->table_exists(['drawer_balances'])) {
		$rows = $db->query(
			"SELECT channel_key, proof_image FROM drawer_balances WHERE drawer_id = :drawer_id AND channel_type = 'ecash'",
			['drawer_id' => $row->id]
		) ?: [];
		foreach ($rows as $r) {
			$key = strtoupper(trim((string)($r->channel_key ?? '')));
			if ($key === '') continue;
			if (strpos($key, 'ECASH_') !== 0) {
				$key = 'ECASH_' . $key;
			}
			$existingProofs[$key] = (string)($r->proof_image ?? '');
		}
	}
} catch (\Throwable $e) {
	$existingProofs = [];
}

if (!empty($ecashChannels)) {
	$req->upload_folder = 'uploads/cashdrawers';
	try {
		$dbSchema = new \Core\Database;
		if ($dbSchema->table_exists(['drawer_balances'])) {
			$schema = $dbSchema->query("SHOW COLUMNS FROM drawer_balances") ?: [];
			$existing = [];
			foreach ($schema as $column) {
				$existing[strtolower($column->Field ?? '')] = true;
			}
			if (empty($existing['proof_image'])) {
				$dbSchema->query("ALTER TABLE drawer_balances ADD COLUMN proof_image VARCHAR(255) NULL AFTER trans_status");
			}
		}
	} catch (\Throwable $e) {
		// ignore
	}

	foreach ($ecashProofUploads as $channelKey => $fileKey) {
		$openingBalance = isset($ecashChannels[$channelKey]) ? (float)$ecashChannels[$channelKey] : 0.0;
		if ($openingBalance <= 0) {
			$ecashProofUploads[$channelKey] = null;
			continue;
		}

		$hasExisting = !empty($existingProofs[$channelKey]);
		$uploadedProof = $req->upload_files($fileKey);
		$hasNewUpload = !empty($uploadedProof) && empty($req->upload_errors[$fileKey]);
		if (!$hasNewUpload && !$hasExisting) {
			$displayChannel = preg_replace('/^ECASH_/', '', (string)$channelKey);
			$displayChannel = ucwords(strtolower(str_replace('_', ' ', $displayChannel)));
			$cashdrawer->errors[$fileKey] = $req->upload_errors[$fileKey] ?? ("Upload photo proof for {$displayChannel} because opening balance is greater than 0.");
		} else {
			$ecashProofUploads[$channelKey] = $hasNewUpload ? $uploadedProof : ($existingProofs[$channelKey] ?? null);
		}
	}
}

if (!empty($cashdrawer->errors)) {
	set_value('errors', array_merge($cashdrawer->errors, $req->upload_errors));
	return;
}

// Persist CFA rates to settings if operations manager posted them
try {
	$session = get_session();
	$rolesRaw = $session->roles ?? [];
	if (!is_array($rolesRaw)) {
		$rolesRaw = [$rolesRaw];
	}
	$rolesLower = array_map('strtolower', $rolesRaw);
	$isOpsManager = in_array('operations manager', $rolesLower, true);
	if ($isOpsManager) {
		$buyingRatePost  = isset($postdata['buying_rate'])  ? (float)$postdata['buying_rate']  : null;
		$sellingRatePost = isset($postdata['selling_rate']) ? (float)$postdata['selling_rate'] : null;
		if ($buyingRatePost !== null || $sellingRatePost !== null) {
			$settingsModel = new \Settings\Setting;
			$settingsRow = $settingsModel->first(['id' => 1]);
			$updateData = [];
			if ($buyingRatePost !== null && $buyingRatePost > 0) {
				$updateData['buying_rate'] = $buyingRatePost;
			}
			if ($sellingRatePost !== null && $sellingRatePost > 0) {
				$updateData['selling_rate'] = $sellingRatePost;
			}
			if (!empty($updateData)) {
				if ($settingsRow) {
					$settingsModel->update($settingsRow->id, $updateData);
				} else {
					$updateData['id'] = 1;
					$settingsModel->insert($updateData);
				}
			}
		}
	}
} catch (\Throwable $e) {
	// ignore
}

// Update cashdrawer header
$session = get_session();
$user_id = (int)($session->id ?? 0);
$drawerOwnerUserId = (int)($row->user_id ?? 0);
if ($drawerOwnerUserId <= 0) {
	$drawerOwnerUserId = (int)($postdata['user_id'] ?? 0);
}
if ($drawerOwnerUserId <= 0) {
	$cashdrawer->errors['user_id'] = 'Cashdrawer owner could not be resolved.';
	set_value('errors', array_merge($cashdrawer->errors, $req->upload_errors));
	return;
}
$update = [
	'currency' => (string)($postdata['currency'] ?? $row->currency ?? 'GHS'),
	'amount' => (float)($postdata['amount'] ?? $row->amount ?? 0),
	'updated_by' => $user_id,
	'date_updated' => date('Y-m-d H:i:s'),
];

$oldGhsDenoCounts = [];
$oldCfaDenoCounts = [];
$oldGhsDenoTotal = 0.0;
$oldCfaDenoTotal = 0.0;
$oldEcashTotal = 0.0;
$oldEcashCount = 0;
$loadExistingDenoCounts = static function(\Core\Database $database, string $table, int $drawerId, int $ownerId, string $currency, string $openingStatus): array {
	$out = [];
	if (!$database->table_exists([$table])) {
		return $out;
	}
	$where = "drawer_id = :drawer_id";
	$params = ['drawer_id' => $drawerId];
	if ($ownerId > 0) {
		$where .= " AND user_id = :user_id";
		$params['user_id'] = $ownerId;
	}
	$where .= " AND LOWER(TRIM(COALESCE(trans_status, ''))) = :opening_status";
	$params['opening_status'] = $openingStatus;
	if ($currency !== '') {
		try {
			$cols = $database->query("SHOW COLUMNS FROM {$table}") ?: [];
			$hasCurrency = false;
			foreach ($cols as $col) {
				if (strtolower((string)($col->Field ?? '')) === 'currency') {
					$hasCurrency = true;
					break;
				}
			}
			if ($hasCurrency) {
				$where .= " AND currency = :currency";
				$params['currency'] = $currency;
			}
		} catch (\Throwable $e) {
		}
	}
	$rows = $database->query(
		"SELECT denomination, SUM(`count`) AS total_count FROM {$table} WHERE {$where} GROUP BY denomination",
		$params
	) ?: [];
	foreach ($rows as $denoRow) {
		$key = number_format((float)($denoRow->denomination ?? 0), 2, '.', '');
		if ((float)$key <= 0) {
			continue;
		}
		$out[$key] = (int)round((float)($denoRow->total_count ?? 0));
	}
	return $out;
};
$sumDenoMap = static function(array $map): float {
	$total = 0.0;
	foreach ($map as $denomination => $count) {
		$total += (float)$denomination * (int)$count;
	}
	return $total;
};
$newGhsDenoCounts = [];
$newCfaDenoCounts = [];
if (is_array($currencyArray)) {
	foreach ($currencyArray as $currency) {
		$currencyCode = strtoupper((string)($currency['currency'] ?? 'GHS'));
		$denomination = (float)($currency['denomination'] ?? 0);
		$count = (int)($currency['count'] ?? 0);
		if ($denomination <= 0 || $count <= 0) {
			continue;
		}
		$key = number_format($denomination, 2, '.', '');
		if ($currencyCode === 'CFA') {
			$newCfaDenoCounts[$key] = ($newCfaDenoCounts[$key] ?? 0) + $count;
		} elseif ($currencyCode === 'GHS') {
			$newGhsDenoCounts[$key] = ($newGhsDenoCounts[$key] ?? 0) + $count;
		}
	}
}
try {
	$auditDb = new \Core\Database;
	$oldGhsDenoCounts = $loadExistingDenoCounts($auditDb, 'drawer_denos', (int)$row->id, $drawerOwnerUserId, '', 'opening snapshot');
	$oldCfaDenoCounts = $loadExistingDenoCounts($auditDb, 'drawer_fx_denos', (int)$row->id, $drawerOwnerUserId, 'CFA', 'opening cfa snapshot');
	$oldGhsDenoTotal = $sumDenoMap($oldGhsDenoCounts);
	$oldCfaDenoTotal = $sumDenoMap($oldCfaDenoCounts);
	if ($auditDb->table_exists(['drawer_balances'])) {
		$oldEcashRow = ($auditDb->query(
			"SELECT COUNT(*) AS total_count, SUM(opening_balance) AS total_balance FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash'",
			['drawer_id' => (int)$row->id, 'user_id' => $drawerOwnerUserId]
		) ?: [])[0] ?? null;
		if ($oldEcashRow) {
			$oldEcashCount = (int)($oldEcashRow->total_count ?? 0);
			$oldEcashTotal = (float)($oldEcashRow->total_balance ?? 0);
		}
	}
} catch (\Throwable $e) {
	$oldGhsDenoCounts = [];
	$oldCfaDenoCounts = [];
}
$db = new \Core\Database;
if (!$db->beginTransaction()) {
	$cashdrawer->errors['denos'] = 'Cashdrawer denomination changes could not be started. No drawer data was changed.';
	set_value('errors', array_merge($cashdrawer->errors, $req->upload_errors));
	return;
}
try {
	operations_account_sync_denomination_snapshot(
		$db,
		'drawer_denos',
		(int)$row->id,
		$drawerOwnerUserId,
		$user_id,
		'opening snapshot',
		$newGhsDenoCounts
	);
	operations_account_sync_denomination_snapshot(
		$db,
		'drawer_fx_denos',
		(int)$row->id,
		$drawerOwnerUserId,
		$user_id,
		'opening cfa snapshot',
		$newCfaDenoCounts,
		'CFA'
	);
	if (!$db->commit()) {
		throw new \RuntimeException('Unable to commit cashdrawer denomination changes.');
	}
} catch (\Throwable $e) {
	$db->rollBack();
	error_log('[EditCashdrawer] Unable to sync denomination changes: ' . $e->getMessage());
	$cashdrawer->errors['denos'] = 'Cashdrawer opening denominations could not be updated. No drawer data was changed.';
	set_value('errors', array_merge($cashdrawer->errors, $req->upload_errors));
	return;
}
$openingCfaTotal = $sumDenoMap($newCfaDenoCounts);
$cashdrawer->update($row->id, $update);

// Update the opening component while retaining each channel's accumulated movements.
try {
	$db = new \Core\Database;
	if ($db->table_exists(['drawer_balances'])) {
		$now = date('Y-m-d H:i:s');
		$upsertOpeningBalance = static function (string $channelType, string $channelKey, float $openingBalance, ?string $proofImage = null) use ($db, $row, $drawerOwnerUserId, $now): void {
			$alternateKey = ($channelType === 'ecash' && strpos($channelKey, 'ECASH_') === 0)
				? substr($channelKey, 6)
				: $channelKey;
			$existing = $db->query(
				"SELECT id FROM drawer_balances
				 WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = :channel_type
				   AND (channel_key = :channel_key OR channel_key = :alternate_key)
				 ORDER BY id DESC LIMIT 1",
				[
					'drawer_id' => (int)$row->id,
					'user_id' => $drawerOwnerUserId,
					'channel_type' => $channelType,
					'channel_key' => $channelKey,
					'alternate_key' => $alternateKey,
				]
			) ?: [];

			if (!empty($existing)) {
				$setProof = ($channelType === 'ecash' && $proofImage !== null && $proofImage !== '');
				$sql = "UPDATE drawer_balances
				        SET opening_balance = :opening_balance,
				            closing_balance = :closing_opening_balance + COALESCE(credit, 0) - COALESCE(debit, 0)"
					. ($setProof ? ", proof_image = :proof_image" : '')
					. " WHERE id = :id";
				$params = [
					'opening_balance' => $openingBalance,
					'closing_opening_balance' => $openingBalance,
					'id' => (int)($existing[0]->id ?? 0),
				];
				if ($setProof) {
					$params['proof_image'] = $proofImage;
				}
				$db->query($sql, $params);
				return;
			}

			if ($openingBalance <= 0) {
				return;
			}
			$db->query(
				"INSERT INTO drawer_balances (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, proof_image, date_created)
				 VALUES (:drawer_id, :user_id, :channel_type, :channel_key, :opening_balance, 0, 0, :closing_balance, :proof_image, :date_created)",
				[
					'drawer_id' => (int)$row->id,
					'user_id' => $drawerOwnerUserId,
					'channel_type' => $channelType,
					'channel_key' => $channelKey,
					'opening_balance' => $openingBalance,
					'closing_balance' => $openingBalance,
					'proof_image' => $proofImage,
					'date_created' => $now,
				]
			);
		};

		$upsertOpeningBalance('cash', 'CASH_DRAWER', (float)($update['amount'] ?? 0));
		$upsertOpeningBalance('cash', 'CASH_CFA', $openingCfaTotal);
		foreach ($ecashChannels as $channelKey => $openingBalance) {
			$openingBalance = (float)$openingBalance;
			if ($openingBalance < 0) {
				continue;
			}
			$upsertOpeningBalance('ecash', $channelKey, $openingBalance, $ecashProofUploads[$channelKey] ?? null);
		}
	}
} catch (\Throwable $e) {
	// ignore
}

try {
	$username = 'System';
	if (!empty($session->id)) {
		$username = trim((string)($session->first_name ?? '') . ' ' . (string)($session->last_name ?? ''));
		if ($username === '') {
			$username = 'User #' . (int)$session->id;
		}
	}
	$describeDenoChanges = static function(array $oldMap, array $newMap, string $currency): string {
		$keys = array_unique(array_merge(array_keys($oldMap), array_keys($newMap)));
		usort($keys, static function($a, $b) {
			return (float)$b <=> (float)$a;
		});
		$parts = [];
		foreach ($keys as $key) {
			$oldCount = (int)($oldMap[$key] ?? 0);
			$newCount = (int)($newMap[$key] ?? 0);
			if ($oldCount === $newCount) {
				continue;
			}
			$label = $currency . ' ' . (((float)$key >= 1) ? number_format((float)$key, 0) : number_format((float)$key, 2));
			$oldVolume = (float)$key * $oldCount;
			$newVolume = (float)$key * $newCount;
			$parts[] = $label . ': '
				. number_format($oldCount) . ' (' . $currency . ' ' . number_format($oldVolume, 2) . ')'
				. ' -> '
				. number_format($newCount) . ' (' . $currency . ' ' . number_format($newVolume, 2) . ')';
			if (count($parts) >= 8) {
				$parts[] = 'more changes';
				break;
			}
		}
		return empty($parts) ? ($currency . ' denominations unchanged') : implode('; ', $parts);
	};
	$newGhsDenoTotal = $sumDenoMap($newGhsDenoCounts);
	$newCfaDenoTotal = $sumDenoMap($newCfaDenoCounts);
	$newEcashTotal = 0.0;
	$newEcashCount = 0;
	foreach ($ecashChannels as $openingBalance) {
		$newEcashCount++;
		$newEcashTotal += (float)$openingBalance;
	}
	$descriptionParts = [];
	$descriptionParts[] = $username . ' edited cashdrawer #' . (int)$row->id;
	$descriptionParts[] = 'Opening GHS: ' . number_format((float)($row->amount ?? 0), 2) . ' -> ' . number_format((float)($update['amount'] ?? 0), 2);
	$descriptionParts[] = 'GHS total: ' . number_format($oldGhsDenoTotal, 2) . ' -> ' . number_format($newGhsDenoTotal, 2);
	$descriptionParts[] = 'CFA total: ' . number_format($oldCfaDenoTotal, 2) . ' -> ' . number_format($newCfaDenoTotal, 2);
	$descriptionParts[] = 'E-cash total: ' . number_format($oldEcashTotal, 2) . ' (' . number_format($oldEcashCount) . ' channels) -> ' . number_format($newEcashTotal, 2) . ' (' . number_format($newEcashCount) . ' channels)';
	$descriptionParts[] = $describeDenoChanges($oldGhsDenoCounts, $newGhsDenoCounts, 'GHS');
	$descriptionParts[] = $describeDenoChanges($oldCfaDenoCounts, $newCfaDenoCounts, 'CFA');
	$marker = '[cashdrawer_id:' . (int)$row->id . ']';
	$description = implode('. ', array_filter($descriptionParts, static function($part) {
		return trim((string)$part) !== '';
	})) . '. ' . $marker;
	if (strlen($description) > 1000) {
		$maxBodyLength = max(0, 996 - strlen($marker));
		$body = implode('. ', array_filter($descriptionParts, static function($part) {
			return trim((string)$part) !== '';
		})) . '.';
		$body = substr($body, 0, $maxBodyLength);
		$body = rtrim($body, " .");
		$description = $body . '... ' . $marker;
	}
	$log = new \MyWallets\Log;
	$log->insert([
		'table_name' => 'cashdrawers',
		'action' => 'edited',
		'user_id' => $user_id,
		'user_name' => $username,
		'description' => $description,
		'date_created' => date('Y-m-d H:i:s'),
	]);
} catch (\Throwable $e) {
	// Logging should never block a cashdrawer correction.
}

message_success("Cashdrawer updated successfully!");
redirect($admin_route.'/'.$plugin_route);

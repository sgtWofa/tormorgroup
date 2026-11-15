<?php
	
	require_once plugin_path('helpers/cashdrawer-history.php');

	// Set timezone to Accra (Africa/Accra)
	date_default_timezone_set('Africa/Accra');

	$cashdrawer = new \MyWallets\Cashdrawer;
	$drawerdenos = new \MyWallets\DrawerDenos;

	$session = get_session();
	$isPostRequest = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';

	// Load CFA cashdrawer sync helper (if available) to mirror CFA denominations
	$cfaHelperPath = realpath(__DIR__ . '/../../cfa/helpers/cashdrawer-sync.php');
	if ($cfaHelperPath && file_exists($cfaHelperPath)) {
		require_once $cfaHelperPath;
	}
	$vaultHelperPath = realpath(__DIR__ . '/../../vaults/helpers/vaults.php');
	if ($vaultHelperPath && file_exists($vaultHelperPath)) {
		require_once $vaultHelperPath;
	}

	$postdata = $req->post();
	$currencyDetails = $postdata['denos'] ?? '[]';
	$uploadedProofPaths = [];

	/**
	 * Collect dynamic e-cash channels coming from the form. Hidden inputs are
	 * named using the pattern ecash_{slug}. We sanitize the slug and keep the
	 * map ready for DB inserts.
	 */
	$ecashChannels = [];
	$ecashProofUploads = [];
	$ecashExistingProofs = [];
	foreach ($postdata as $field => $value) {
		if (strpos($field, 'ecash_proof_existing_') === 0) {
			$rawKey = strtoupper(substr($field, strlen('ecash_proof_existing_')));
			$sanitizedKey = preg_replace('/[^A-Z0-9]+/', '_', $rawKey);
			$sanitizedKey = trim($sanitizedKey, '_');
			if (empty($sanitizedKey)) {
				continue;
			}
			if (in_array($sanitizedKey, ['EZWICH', 'E_ZWICH'], true)) {
				continue;
			}
			$channelKey = 'ECASH_' . $sanitizedKey;
			$proofVal = trim((string)$value);
			if ($proofVal !== '') {
				$ecashExistingProofs[$channelKey] = $proofVal;
			}
			continue;
		}
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
		$ecashChannels[$channelKey] = $amount;
		$ecashProofUploads[$channelKey] = 'ecash_proof_' . strtolower(substr($field, 6));
	}
	// Decode the JSON currency details
	$currencyArray = json_decode($currencyDetails, true);

	//dd($postdata); die;

	$csrf = csrf_verify($postdata);
	if($csrf && $cashdrawer->validate_insert($postdata))
	{
		try {
			operations_account_ensure_denomination_history_schema(new \Core\Database);
		} catch (\Throwable $e) {
			error_log('[AddCashdrawer] Unable to prepare denomination history: ' . $e->getMessage());
			$cashdrawer->errors['denos'] = 'Cashdrawer history could not be prepared safely. No changes were made.';
			goto end_controller;
		}

		$currentUserId = (int)($session->id ?? 0);
		$requestedUserId = (int)($postdata['user_id'] ?? 0);
		$guardUserId = $currentUserId > 0 ? $currentUserId : $requestedUserId;
		if ($currentUserId > 0) {
			$postdata['user_id'] = $currentUserId;
		}
		$previousDrawerSnapshot = null;
		$carryForwardVaultMovements = ['out' => [], 'in' => []];
		try {
			if (!function_exists('operations_account_previous_cashdrawer_snapshot')) {
				throw new \RuntimeException('Previous cashdrawer balances cannot be verified.');
			}
			$previousDrawerSnapshot = operations_account_previous_cashdrawer_snapshot($guardUserId);
			$vaultLimits = ['branch_assigned' => false, 'branch_id' => 0, 'available' => ['GHS' => [], 'CFA' => []]];
			if ($previousDrawerSnapshot === null) {
				if (!function_exists('operations_account_cashdrawer_vault_limits')) {
					throw new \RuntimeException('Vault availability validation is unavailable.');
				}
				$vaultLimits = operations_account_cashdrawer_vault_limits($guardUserId);
			}
			if ($previousDrawerSnapshot === null && !empty($vaultLimits['branch_assigned'])
				&& function_exists('\\Vaults\\ensureSchema')) {
					\Vaults\ensureSchema();
			}
			if (!is_array($currencyArray)) {
				$cashdrawer->errors['denos'] = 'The opening denomination data is invalid. Please re-enter the counts.';
			} else {
				$requestedCounts = ['GHS' => [], 'CFA' => []];
				foreach ($currencyArray as $line) {
					if (!is_array($line)) {
						$cashdrawer->errors['denos'] = 'The opening denomination data is invalid. Please re-enter the counts.';
						break;
					}
					$currencyCode = strtoupper(trim((string)($line['currency'] ?? 'GHS')));
					$denomination = $line['denomination'] ?? null;
					$countValue = $line['count'] ?? null;
					if (!isset($requestedCounts[$currencyCode]) || !is_numeric($denomination) || !is_numeric($countValue)) {
						$cashdrawer->errors['denos'] = 'The opening denomination data contains an invalid currency, denomination, or count.';
						break;
					}
					$denominationValue = (float)$denomination;
					$count = (float)$countValue;
					if (!is_finite($denominationValue) || $denominationValue <= 0 || !is_finite($count) || $count < 0 || floor($count) !== $count) {
						$cashdrawer->errors['denos'] = 'Denomination counts must be whole numbers and cannot be negative.';
						break;
					}
					$denominationKey = number_format($denominationValue, 2, '.', '');
					$requestedCounts[$currencyCode][$denominationKey] = ($requestedCounts[$currencyCode][$denominationKey] ?? 0) + (int)$count;
				}

				if ($previousDrawerSnapshot !== null && empty($cashdrawer->errors['denos'])) {
					$previousCounts = ['GHS' => [], 'CFA' => []];
					foreach ($previousDrawerSnapshot['denominations'] as $line) {
						$currencyCode = strtoupper((string)$line['currency']);
						$key = number_format((float)$line['denomination'], 2, '.', '');
						$previousCounts[$currencyCode][$key] = ($previousCounts[$currencyCode][$key] ?? 0) + (int)$line['count'];
					}
					foreach (['GHS', 'CFA'] as $currencyCode) {
						$denominationKeys = array_unique(array_merge(
							array_keys($previousCounts[$currencyCode]),
							array_keys($requestedCounts[$currencyCode])
						));
						foreach ($denominationKeys as $denominationKey) {
							$previousCount = (int)($previousCounts[$currencyCode][$denominationKey] ?? 0);
							$requestedCount = (int)($requestedCounts[$currencyCode][$denominationKey] ?? 0);
							$delta = $requestedCount - $previousCount;
							if ($delta === 0) {
								continue;
							}
							$cashdrawer->errors['denos'] = 'Previous cashdrawer denomination balances are locked and cannot be changed when opening today’s drawer.';
							break 2;
						}
					}
				}

				if (
					$previousDrawerSnapshot === null
					&& !empty($vaultLimits['branch_assigned'])
					&& empty($cashdrawer->errors['denos'])
				) {
					foreach ($requestedCounts as $currencyCode => $denominations) {
						foreach ($denominations as $denominationKey => $requestedCount) {
							$availableCount = (int)($vaultLimits['available'][$currencyCode][$denominationKey] ?? 0);
							if ($requestedCount > $availableCount) {
								$currencyLabel = $currencyCode === 'GHS' ? 'GHS' : 'CFA';
								$cashdrawer->errors['denos'] = sprintf(
									'%s %s opening count exceeds branch vault stock: requested %d, available %d.',
									$currencyLabel,
									rtrim(rtrim($denominationKey, '0'), '.'),
									$requestedCount,
									$availableCount
								);
								break 2;
							}
						}
					}
					if (
						$previousDrawerSnapshot !== null
						&& !empty($carryForwardVaultMovements['out'])
						&& empty($cashdrawer->errors['denos'])
					) {
						foreach ($carryForwardVaultMovements['out'] as $currencyCode => $denominations) {
							foreach ($denominations as $denominationKey => $line) {
								$availableCount = (int)($vaultLimits['available'][$currencyCode][$denominationKey] ?? 0);
								if ((int)$line['count'] > $availableCount) {
									$cashdrawer->errors['denos'] = sprintf(
										'%s %s additional opening count exceeds branch vault stock: requested %d, available %d.',
										$currencyCode,
										rtrim(rtrim($denominationKey, '0'), '.'),
										(int)$line['count'],
										$availableCount
									);
									break 2;
								}
							}
						}
					}
				}
			}
		} catch (\Throwable $vaultLimitError) {
			error_log('[AddCashdrawer] Opening balance verification failed: ' . $vaultLimitError->getMessage());
			$cashdrawer->errors['denos'] = 'Previous drawer balances or required branch vault stock could not be verified. Cashdrawer creation is blocked; please contact an administrator.';
		}
		if (!empty($cashdrawer->errors)) {
			set_value('errors', $cashdrawer->errors);
			goto end_controller;
		}
		if ($previousDrawerSnapshot !== null) {
			if (!is_numeric($postdata['amount'] ?? null)
				|| abs((float)$postdata['amount'] - (float)$previousDrawerSnapshot['ghs_total']) >= 0.01) {
				$cashdrawer->errors['amount'] = 'The previous cashdrawer GHS balance is locked and cannot be changed when opening today’s drawer.';
				set_value('errors', $cashdrawer->errors);
				goto end_controller;
			}
			$ecashChannels = [];
			foreach ($previousDrawerSnapshot['ecash'] as $channelKey => $channel) {
				$openingBalance = (float)($channel['balance'] ?? 0);
				if ($openingBalance <= 0) {
					continue;
				}
				$channelKey = strtoupper((string)$channelKey);
				$ecashChannels[$channelKey] = $openingBalance;
				$ecashProofUploads[$channelKey] = 'ecash_proof_' . strtolower(substr($channelKey, 6));
			}
		}
		if ($isPostRequest && $guardUserId > 0 && function_exists('operations_account_has_cashdrawer_today') && operations_account_has_cashdrawer_today($guardUserId)) {
			message_success('Cashdrawer for today already exists.');
			redirect($admin_route.'/'.$plugin_route);
			exit;
		}

		if ($previousDrawerSnapshot !== null) {
			foreach ($ecashChannels as $channelKey => $openingBalance) {
				$previousChannel = $previousDrawerSnapshot['ecash'][$channelKey] ?? null;
				if (
					is_array($previousChannel)
					&& (float)$openingBalance > 0
					&& abs((float)$openingBalance - (float)$previousChannel['balance']) < 0.01
					&& trim((string)($previousChannel['proof_image'] ?? '')) !== ''
					&& empty($_FILES[$ecashProofUploads[$channelKey]]['tmp_name'])
				) {
					$ecashExistingProofs[$channelKey] = (string)$previousChannel['proof_image'];
				}
			}
		}

		if (!empty($ecashChannels)) {
			$req->upload_folder = 'uploads/cashdrawers';
			// Max file size 1MB
			$maxFileSize = 1 * 1024 * 1024;

			foreach ($ecashProofUploads as $channelKey => $fileKey) {
				$openingBalance = isset($ecashChannels[$channelKey]) ? (float)$ecashChannels[$channelKey] : 0.0;
				if ($openingBalance <= 0) {
					$ecashProofUploads[$channelKey] = null;
					continue;
				}

				$existingProof = $ecashExistingProofs[$channelKey] ?? '';
				$hasIncomingProof = !empty($_FILES[$fileKey]['tmp_name']);

				// Validate file size before upload
				if ($hasIncomingProof) {
					$fileSize = (int)($_FILES[$fileKey]['size'] ?? 0);
					if ($fileSize > $maxFileSize) {
						$displayChannel = preg_replace('/^ECASH_/', '', (string)$channelKey);
						$displayChannel = ucwords(strtolower(str_replace('_', ' ', $displayChannel)));
						$maxFileSizeMB = round($maxFileSize / (1024 * 1024), 0);
						$cashdrawer->errors[$fileKey] = "Image size exceeds {$maxFileSizeMB}MB limit for {$displayChannel}. Please upload a smaller image.";
						continue;
					}
				}

				$uploadedProof = $hasIncomingProof ? $req->upload_files($fileKey) : '';
				if (!empty($uploadedProof) && empty($req->upload_errors[$fileKey])) {
					$ecashProofUploads[$channelKey] = $uploadedProof;
					$uploadedProofPaths[] = $uploadedProof;
				} elseif ($existingProof !== '') {
					$ecashProofUploads[$channelKey] = $existingProof;
				} else {
					// No uploaded proof and no existing proof in POST. Try to find a shared
					// admin/manager proof image created earlier today and reuse it.
					try {
						$dbShared = new \Core\Database;
						$isSharedBankChannel = function_exists('operations_account_is_bank_channel_key')
							&& operations_account_is_bank_channel_key((string)$channelKey);
						if ($isSharedBankChannel && $dbShared->table_exists(['drawer_balances','cashdrawers','user_roles_map','user_roles'])) {
							$today = date('Y-m-d');
							$params = ['today' => $today, 'channel_key' => $channelKey];
							$roleNames = [
								'admin','manager','general manager','general_manager','generalmanager','gm',
								'operations manager','operations_manager','operationsmanager','op manager','op_manager'
							];
							$rolePlaceholders = [];
							foreach ($roleNames as $i => $roleName) {
								$key = 'role' . (string)$i;
								$rolePlaceholders[] = ':' . $key;
								$params[$key] = strtolower((string)$roleName);
							}
							$sql = "SELECT db.proof_image FROM drawer_balances AS db INNER JOIN cashdrawers AS cd ON cd.id = db.drawer_id INNER JOIN user_roles_map AS urm ON urm.user_id = cd.user_id AND (urm.disabled = 0 OR urm.disabled IS NULL) INNER JOIN user_roles AS r ON r.id = urm.role_id AND (r.disabled = 0 OR r.disabled IS NULL) WHERE db.channel_type = 'ecash' AND DATE(cd.date_created) = :today AND LOWER(r.role) IN (" . implode(',', $rolePlaceholders) . ") AND db.channel_key = :channel_key ORDER BY db.id DESC LIMIT 1";
							$rowsShared = $dbShared->query($sql, $params) ?: [];
							if (!empty($rowsShared) && !empty($rowsShared[0]->proof_image)) {
								$ecashProofUploads[$channelKey] = (string)$rowsShared[0]->proof_image;
							}
						}
							} catch (\Throwable $e) {
								// ignore lookup failures and fall through to require upload
							}

							$previousChannel = $previousDrawerSnapshot['ecash'][$channelKey] ?? null;
							$carryForwardBalanceVerified = $previousDrawerSnapshot !== null
								&& is_array($previousChannel)
								&& abs($openingBalance - (float)$previousChannel['balance']) < 0.01;
							// If shared lookup did not populate a proof, require upload
							if (empty($ecashProofUploads[$channelKey])) {
								if ($carryForwardBalanceVerified) {
									$ecashProofUploads[$channelKey] = trim((string)($previousChannel['proof_image'] ?? '')) ?: null;
								} else {
									$displayChannel = preg_replace('/^ECASH_/', '', (string)$channelKey);
									$displayChannel = ucwords(strtolower(str_replace('_', ' ', $displayChannel)));
									$cashdrawer->errors[$fileKey] = $req->upload_errors[$fileKey] ?? ("Upload photo proof for {$displayChannel} because opening balance is greater than 0.");
								}
							}
						}
			}
		}

		if (!empty($cashdrawer->errors)) {
			set_value('errors', $cashdrawer->errors);
			goto end_controller;
		}

		$postdata['date_created'] = date("Y-m-d H:i:s");
		unset($postdata['id']);
		if (!empty($postdata['amount'])) {
			$postdata['credit'] = $postdata['amount'];
			$postdata['balance'] = $postdata['amount'];
		}

		$session = get_session();
		$username = '';
		$user_id = 0;
		if (!empty($session->id)) {
			$username = trim(($session->first_name ?? '') . ' ' . ($session->last_name ?? ''));
			$user_id = (int)$session->id;
		} else {
			$user_id = (int)($postdata['user_id'] ?? 0);
			$username = 'System';
		}

		// If operations manager provided fresh CFA rates on the drawer header, persist them to settings
		try {
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
							// create a new settings row with id 1 if none exists yet
							$updateData['id'] = 1;
							$settingsModel->insert($updateData);
						}
					}
				}
			}
		} catch (\Throwable $settingsEx) {
			// Settings persistence failure should not block drawer creation
		}

		$postdata['created_by'] = $user_id;

		$transactionStarted = false;
		$rollbackDbTransaction = function(\Core\Database $database) use (&$transactionStarted): void {
			if (!$transactionStarted) {
				return;
			}
			if (method_exists($database, 'rollBack')) {
				$database->rollBack();
			} else {
				$database->query('ROLLBACK');
			}
			$transactionStarted = false;
		};

		try {
			$db = new \Core\Database;
			$beginDbTransaction = function(\Core\Database $database) use (&$transactionStarted): bool {
				if (method_exists($database, 'beginTransaction')) {
					$transactionStarted = (bool)$database->beginTransaction();
					return $transactionStarted;
				}
				$database->query('START TRANSACTION');
				$transactionStarted = empty($database->has_error);
				return $transactionStarted;
			};
			$commitDbTransaction = function(\Core\Database $database) use (&$transactionStarted): bool {
				if (!$transactionStarted) {
					return true;
				}
				if (method_exists($database, 'commit')) {
					$result = (bool)$database->commit();
					if ($result) {
						$transactionStarted = false;
					}
					return $result;
				}
				$database->query('COMMIT');
				$result = empty($database->has_error);
				if ($result) {
					$transactionStarted = false;
				}
				return $result;
			};
			$startedTransaction = $beginDbTransaction($db);
			if (!$startedTransaction) {
				throw new \RuntimeException('Unable to start cashdrawer transaction.');
			}

			$assertDbOk = function($database, string $message): void {
				if (!empty($database->has_error)) {
					$detail = trim((string)($database->error ?? ''));
					throw new \RuntimeException($detail !== '' ? $detail : $message);
				}
			};

			$vaultOpeningAllocations = [];
			$vaultCarryForwardReturnAllocations = [];
			$openingBranchId = (int)($vaultLimits['branch_id'] ?? 0);
			$hasCarryForwardVaultMovements = !empty($carryForwardVaultMovements['out'])
				|| !empty($carryForwardVaultMovements['in']);
			$shouldPostOpeningVaultMovements = $previousDrawerSnapshot === null || $hasCarryForwardVaultMovements;
			if ($shouldPostOpeningVaultMovements && !empty($vaultLimits['branch_assigned']) && is_array($currencyArray)) {
				if (($previousDrawerSnapshot === null || $hasCarryForwardVaultMovements)
					&& !function_exists('\\Vaults\\applyVaultTransaction')) {
					throw new \RuntimeException('Vault posting is unavailable. Cashdrawer creation was cancelled.');
				}
				$requestedVaultCounts = ['GHS' => [], 'CFA' => []];
				if ($previousDrawerSnapshot !== null) {
					$requestedVaultCounts = $carryForwardVaultMovements['out'];
				} else {
					foreach ($currencyArray as $line) {
						if (!is_array($line)) {
							continue;
						}
						$currencyCode = strtoupper((string)($line['currency'] ?? 'GHS'));
						$denomination = (float)($line['denomination'] ?? 0);
						$count = (int)($line['count'] ?? 0);
						if ($count <= 0) {
							continue;
						}
						if (!isset($requestedVaultCounts[$currencyCode]) || $denomination <= 0) {
							throw new \RuntimeException('Invalid currency or denomination in cashdrawer opening counts.');
						}
						$key = number_format($denomination, 2, '.', '');
						if (!isset($requestedVaultCounts[$currencyCode][$key])) {
							$requestedVaultCounts[$currencyCode][$key] = [
								'denomination' => $denomination,
								'count' => 0,
							];
						}
						$requestedVaultCounts[$currencyCode][$key]['count'] += $count;
					}
				}

				$hasPhysicalCash = !empty($requestedVaultCounts['GHS']) || !empty($requestedVaultCounts['CFA']);
				if ($hasPhysicalCash) {
					$stockRows = $db->query(
						'SELECT v.id AS vault_id, s.id AS stock_id, s.currency, s.denomination, s.purchase_rate, s.note_count
						 FROM vaults v
						 INNER JOIN vault_stock s ON s.vault_id = v.id
						 WHERE v.branch_id = :branch_id AND v.active = 1 AND s.note_count > 0
						 ORDER BY v.id ASC, s.currency ASC, s.denomination ASC, s.purchase_rate ASC
						 FOR UPDATE',
						['branch_id' => $openingBranchId]
					) ?: [];
					$assertDbOk($db, 'Branch vault stock could not be locked for cashdrawer opening.');

					$remaining = $requestedVaultCounts;
					foreach ($stockRows as $stockRow) {
						$currencyCode = strtoupper((string)$stockRow->currency);
						$key = number_format((float)$stockRow->denomination, 2, '.', '');
						$need = (int)($remaining[$currencyCode][$key]['count'] ?? 0);
						if ($need <= 0) {
							continue;
						}
						$take = min($need, (int)$stockRow->note_count);
						if ($take <= 0) {
							continue;
						}
						$vaultId = (int)$stockRow->vault_id;
						$vaultOpeningAllocations[$vaultId][$currencyCode][$key] = [
							'denomination' => (float)$stockRow->denomination,
							'count' => ($vaultOpeningAllocations[$vaultId][$currencyCode][$key]['count'] ?? 0) + $take,
						];
						$remaining[$currencyCode][$key]['count'] -= $take;
					}

					foreach ($remaining as $currencyCode => $denominations) {
						foreach ($denominations as $line) {
							$short = (int)$line['count'];
							if ($short > 0) {
								throw new \RuntimeException(sprintf(
									'Branch vault stock changed before cashdrawer creation: %s %s is short by %d.',
									$currencyCode,
									rtrim(rtrim(number_format((float)$line['denomination'], 2, '.', ''), '0'), '.'),
									$short
								));
							}
						}
					}
					if ($previousDrawerSnapshot !== null && !empty($carryForwardVaultMovements['in'])) {
						$returnVaultRows = $db->query(
							'SELECT id FROM vaults WHERE branch_id = :branch_id AND active = 1 ORDER BY id ASC LIMIT 1 FOR UPDATE',
							['branch_id' => $openingBranchId]
						) ?: [];
						$assertDbOk($db, 'An active branch vault could not be locked for returned cash.');
						if (!$returnVaultRows) {
							throw new \RuntimeException('No active vault is available to receive the cash being returned.');
						}
						$returnVaultId = (int)$returnVaultRows[0]->id;
						foreach ($carryForwardVaultMovements['in'] as $currencyCode => $denominations) {
							foreach ($denominations as $key => $line) {
								$vaultCarryForwardReturnAllocations[$returnVaultId][$currencyCode][$key] = $line;
							}
						}
					}
				}
			}

			$cashdrawer->insert($postdata);
			$assertDbOk($cashdrawer, 'Cashdrawer header could not be created.');
			$postdata['id'] = (int)$cashdrawer->insert_id;
			if ($postdata['id'] <= 0) {
				throw new \RuntimeException('Cashdrawer header was created without a valid database ID.');
			}

			// Persist opening GHS denominations after the header exists.
			if (is_array($currencyArray) && !empty($currencyArray)) {
				foreach ($currencyArray as $currency) {
					$currencyCode = strtoupper($currency['currency'] ?? 'GHS');
					if ($currencyCode !== 'GHS') {
						continue;
					}

					$denosPost = [
					   'user_id'      => $postdata['user_id'],
					   'drawer_id'    => $postdata['id'],
					   'denomination' => (float)$currency['denomination'],
					   'count'        => (int)$currency['count'],
					   'volume'       => (float)$currency['volume'],
					   'created_by'   => $user_id,
					   'date_created' => $postdata['date_created'],
					   'trans_id'     => (int)$postdata['id'],
					   'trans_status' => 'Opening Snapshot',
					];

					if (empty($denosPost['user_id'])) {
						throw new \RuntimeException('Cashdrawer user_id is required.');
					}

					$drawerdenos->insert($denosPost);
					$assertDbOk($drawerdenos, 'Opening GHS denominations could not be saved.');
				}
			}

			// Mirror CFA denominations into FX drawer tables. If this fails, roll back the entire drawer.
			$openingCfaTotal = 0.0;
			if (is_array($currencyArray) && !empty($currencyArray)) {
				foreach ($currencyArray as $currency) {
					if (strtoupper((string)($currency['currency'] ?? '')) !== 'CFA') {
						continue;
					}
					$openingCfaTotal += (float)($currency['volume'] ?? 0);
				}
			}
			if (function_exists('Cfas\\cfa_manual_fx_adjustment') && is_array($currencyArray) && !empty($currencyArray)) {
				$hasCfaRows = false;
				foreach ($currencyArray as $currency) {
					if (strtoupper((string)($currency['currency'] ?? '')) === 'CFA' && (float)($currency['volume'] ?? 0) > 0) {
						$hasCfaRows = true;
						break;
					}
				}
				if ($hasCfaRows) {
					$fxOk = \Cfas\cfa_manual_fx_adjustment(
						(int)$postdata['user_id'],
						$currencyArray,
						0.0,
						'add',
						'Opening CFA Snapshot'
					);
					if (!$fxOk) {
						throw new \RuntimeException('Opening CFA denominations could not be saved.');
					}
				}
			}

			// Seed opening balances into drawer_balances. These are required for the drawer to be considered valid.
			if($db->table_exists(['drawer_balances'])){
				$now = date('Y-m-d H:i:s');
				$drawerBalanceColumns = function_exists('operations_account_db_table_columns')
					? operations_account_db_table_columns($db, 'drawer_balances')
					: [];
				$insertDrawerBalanceSnapshot = function(string $channelType, string $channelKey, float $openingBalance, ?string $proofImage = null) use ($db, $postdata, $now, $drawerBalanceColumns): void {
					$columns = [];
					$values = [];
					$params = [];
					$add = static function(string $column, string $placeholder, $value) use (&$columns, &$values, &$params): void {
						$columns[] = $column;
						$values[] = $placeholder;
						$params[ltrim($placeholder, ':')] = $value;
					};

					if (empty($drawerBalanceColumns)) {
						throw new \RuntimeException('drawer_balances schema could not be read.');
					}

					if (in_array('drawer_id', $drawerBalanceColumns, true)) {
						$add('drawer_id', ':drawer_id', $postdata['id']);
					}
					if (in_array('user_id', $drawerBalanceColumns, true)) {
						$add('user_id', ':user_id', $postdata['user_id']);
					}
					if (in_array('channel_type', $drawerBalanceColumns, true)) {
						$add('channel_type', ':channel_type', $channelType);
					}
					if (in_array('channel_key', $drawerBalanceColumns, true)) {
						$add('channel_key', ':channel_key', $channelKey);
					}
					if (in_array('currency', $drawerBalanceColumns, true)) {
						$add('currency', ':currency', $channelType === 'cash' && $channelKey === 'CASH_CFA' ? 'CFA' : 'GHS');
					}
					if (in_array('opening_balance', $drawerBalanceColumns, true)) {
						$add('opening_balance', ':opening_balance', $openingBalance);
					} elseif (in_array('amount', $drawerBalanceColumns, true)) {
						$add('amount', ':amount', $openingBalance);
					}
					if (in_array('credit', $drawerBalanceColumns, true)) {
						$add('credit', ':credit', 0);
					}
					if (in_array('debit', $drawerBalanceColumns, true)) {
						$add('debit', ':debit', 0);
					}
					if (in_array('closing_balance', $drawerBalanceColumns, true)) {
						$add('closing_balance', ':closing_balance', $openingBalance);
					} elseif (in_array('balance', $drawerBalanceColumns, true)) {
						$add('balance', ':balance', $openingBalance);
					}
					if ($proofImage !== null && $proofImage !== '' && in_array('proof_image', $drawerBalanceColumns, true)) {
						$add('proof_image', ':proof_image', $proofImage);
					}
					if (in_array('date_created', $drawerBalanceColumns, true)) {
						$add('date_created', ':date_created', $now);
					}
					if (in_array('created_by', $drawerBalanceColumns, true)) {
						$add('created_by', ':created_by', (int)($postdata['created_by'] ?? 0));
					}
					if (in_array('trans_id', $drawerBalanceColumns, true)) {
						$add('trans_id', ':trans_id', (int)$postdata['id']);
					}
					if (in_array('trans_status', $drawerBalanceColumns, true)) {
						$add('trans_status', ':trans_status', 'Opening Snapshot');
					}

					if (empty($columns)) {
						throw new \RuntimeException('drawer_balances has no supported opening snapshot columns.');
					}

					$sql = "INSERT INTO drawer_balances (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";
					$db->query($sql, $params);
				};

				$openingCash = isset($postdata['amount']) ? (float)$postdata['amount'] : 0;
				if($openingCash > 0){
					$insertDrawerBalanceSnapshot('cash', 'CASH_DRAWER', $openingCash);
					$assertDbOk($db, 'Cash channel opening balance could not be created.');
				}
				if($openingCfaTotal > 0){
					$insertDrawerBalanceSnapshot('cash', 'CASH_CFA', $openingCfaTotal);
					$assertDbOk($db, 'CFA cash channel opening balance could not be created.');
				}

				if (!empty($ecashChannels)) {
					foreach($ecashChannels as $channelKey => $openingBalance){
						if($openingBalance < 0){
							continue;
						}
						$insertDrawerBalanceSnapshot('ecash', $channelKey, (float)$openingBalance, $ecashProofUploads[$channelKey] ?? null);
						$assertDbOk($db, 'Opening e-cash channel balances could not be created.');
					}
				}
			}

			foreach ($vaultOpeningAllocations as $vaultId => $currencies) {
				foreach ($currencies as $currencyCode => $denominationsByKey) {
					$denominations = array_values($denominationsByKey);
					$amount = \Vaults\denominationTotal($denominations);
					$cfaRate = $currencyCode === 'CFA' ? \Vaults\currentCfaRate() : null;
					$notes = sprintf(
						'Automatically posted from daily cashdrawer #%d for staff user #%d.',
						(int)$postdata['id'],
						(int)$postdata['user_id']
					);
					$db->query(
						"INSERT INTO vault_transactions
						 (transaction_type, status, source_vault_id, source_branch_id, destination_branch_id,
						  cashdrawer_id, drawer_user_id, agent_user_id, currency, amount, cfa_rate, denos_json, requested_by, notes, date_created, date_updated)
						 VALUES ('cashdrawer_opening', 'Approved', :source_vault_id, :source_branch_id, :destination_branch_id,
						  :cashdrawer_id, :drawer_user_id, :agent_user_id, :currency, :amount, :cfa_rate, :denos_json, :requested_by, :notes, :date_created, :date_updated)",
						[
							'source_vault_id' => (int)$vaultId,
							'source_branch_id' => $openingBranchId,
							'destination_branch_id' => $openingBranchId,
							'cashdrawer_id' => (int)$postdata['id'],
							'drawer_user_id' => (int)$postdata['user_id'],
							'agent_user_id' => (int)$postdata['user_id'],
							'currency' => $currencyCode,
							'amount' => $amount,
							'cfa_rate' => $cfaRate,
							'denos_json' => json_encode($denominations, JSON_UNESCAPED_SLASHES),
							'requested_by' => $user_id > 0 ? $user_id : (int)$postdata['user_id'],
							'notes' => $notes,
							'date_created' => $postdata['date_created'],
							'date_updated' => $postdata['date_created'],
						]
					);
					$assertDbOk($db, 'Vault cashdrawer opening workflow record could not be created.');

					$vaultTransaction = (object)[
						'id' => (int)$db->insert_id,
						'transaction_type' => 'cashdrawer_opening',
						'currency' => $currencyCode,
						'amount' => $amount,
						'cfa_rate' => $cfaRate,
						'denos_json' => json_encode($denominations, JSON_UNESCAPED_SLASHES),
						'source_vault_id' => (int)$vaultId,
						'destination_vault_id' => null,
					];
					\Vaults\applyVaultTransaction(
						$vaultTransaction,
						$user_id > 0 ? $user_id : (int)$postdata['user_id']
					);
				}
			}
			foreach ($vaultCarryForwardReturnAllocations as $vaultId => $currencies) {
				foreach ($currencies as $currencyCode => $denominationsByKey) {
					$denominations = array_values($denominationsByKey);
					$amount = \Vaults\denominationTotal($denominations);
					$cfaRate = $currencyCode === 'CFA' ? \Vaults\currentCfaRate() : null;
					$notes = sprintf(
						'Returned to the branch vault from daily cashdrawer #%d during verification of previous cashdrawer #%d.',
						(int)$postdata['id'],
						(int)$previousDrawerSnapshot['id']
					);
					$denosJson = json_encode($denominations, JSON_UNESCAPED_SLASHES);
					$db->query(
						"INSERT INTO vault_transactions
						 (transaction_type, status, destination_vault_id, source_branch_id, destination_branch_id,
						  cashdrawer_id, drawer_user_id, agent_user_id, currency, amount, cfa_rate, denos_json,
						  requested_by, reviewed_by, reviewed_at, notes, date_created, date_updated)
						 VALUES ('cashdrawer_return', 'Approved', :vault_id, :branch_id, :branch_id,
						  :cashdrawer_id, :drawer_user_id, :agent_user_id, :currency, :amount, :cfa_rate, :denos_json,
						  :requested_by, :reviewed_by, :reviewed_at, :notes, :date_created, :date_updated)",
						[
							'vault_id' => (int)$vaultId,
							'branch_id' => $openingBranchId,
							'cashdrawer_id' => (int)$postdata['id'],
							'drawer_user_id' => (int)$postdata['user_id'],
							'agent_user_id' => (int)$postdata['user_id'],
							'currency' => $currencyCode,
							'amount' => $amount,
							'cfa_rate' => $cfaRate,
							'denos_json' => $denosJson,
							'requested_by' => $user_id > 0 ? $user_id : (int)$postdata['user_id'],
							'reviewed_by' => $user_id > 0 ? $user_id : (int)$postdata['user_id'],
							'reviewed_at' => $postdata['date_created'],
							'notes' => $notes,
							'date_created' => $postdata['date_created'],
							'date_updated' => $postdata['date_created'],
						]
					);
					$assertDbOk($db, 'Vault cashdrawer return workflow record could not be created.');
					\Vaults\applyVaultTransaction(
						(object)[
							'id' => (int)$db->insert_id,
							'transaction_type' => 'cashdrawer_return',
							'currency' => $currencyCode,
							'amount' => $amount,
							'cfa_rate' => $cfaRate,
							'denos_json' => $denosJson,
							'source_vault_id' => null,
							'destination_vault_id' => (int)$vaultId,
						],
						$user_id > 0 ? $user_id : (int)$postdata['user_id']
					);
				}
			}

			if (!$commitDbTransaction($db)) {
				throw new \RuntimeException('Cashdrawer transaction could not be committed.');
			}
		} catch (\Throwable $createEx) {
			$rollbackErrorMessage = '';
			try {
				$dbRollback = $db ?? new \Core\Database;
				$rollbackDbTransaction($dbRollback);
			} catch (\Throwable $rollbackEx) {
				$rollbackErrorMessage = trim((string)$rollbackEx->getMessage());
				error_log('[AddCashdrawer] Rollback failed: ' . $rollbackErrorMessage);
			}

			foreach ($uploadedProofPaths as $uploadedProofPath) {
				$uploadedProofPath = trim((string)$uploadedProofPath);
				if ($uploadedProofPath !== '' && file_exists($uploadedProofPath)) {
					@unlink($uploadedProofPath);
				}
			}

			$createErrorMessage = trim((string)$createEx->getMessage());
			$cashdrawer->errors['amount'] = 'Cashdrawer creation failed and was rolled back. Please create the cashdrawer again.'
				. ($createErrorMessage !== '' ? ' ' . $createErrorMessage : '')
				. ($rollbackErrorMessage !== '' ? ' Rollback warning: ' . $rollbackErrorMessage : '');
			set_value('errors', $cashdrawer->errors);
			goto end_controller;
		}

		//log changes in the log table
		$action 		= 'added';
		$transaction_id 		= $postdata['id'];
		$table 			= 'cashdrawers';
		$description 	= "$username $action a new cashdrawer with id $transaction_id to $table";
		if ($previousDrawerSnapshot !== null) {
			$description .= '; opening balances carried forward from cashdrawer #' . (int)$previousDrawerSnapshot['id']
				. ' and verified by the drawer owner';
			if ($hasCarryForwardVaultMovements) {
				$description .= '; physical cash differences were recorded as branch vault movements';
			}
		}
		$date_created 	= date("Y-m-d H:i:s");
		try {
			$dbAttendance = new \Core\Database;
			if ($dbAttendance->table_exists(['attendance'])) {
				$attendanceDateTime = (string)($postdata['date_created'] ?? date('Y-m-d H:i:s'));
				$attendanceTs = strtotime($attendanceDateTime);
				if ($attendanceTs === false) {
					$attendanceTs = time();
					$attendanceDateTime = date('Y-m-d H:i:s', $attendanceTs);
				}
				$todayDate = date('Y-m-d', $attendanceTs);
				$attendanceTime = date('H:i:s', $attendanceTs);
				$remarksToSet = 'PRESENT';
				try {
					if ($dbAttendance->table_exists(['settings'])) {
						$settingsRow = ($dbAttendance->query("SELECT work_open, late_attendance_time FROM settings ORDER BY id DESC LIMIT 1") ?: [])[0] ?? null;
						$workOpen = $settingsRow ? trim((string)($settingsRow->work_open ?? '')) : '';
						$lateAttendanceTime = $settingsRow ? trim((string)($settingsRow->late_attendance_time ?? '')) : '';
						if ($lateAttendanceTime !== '' || $workOpen !== '') {
							if ($lateAttendanceTime !== '') {
								if (strlen($lateAttendanceTime) === 5) $lateAttendanceTime .= ':00';
								$thr = $lateAttendanceTime;
							} else {
								if (strlen($workOpen) === 5) $workOpen .= ':00';
								$woTs = strtotime('1970-01-01 ' . $workOpen);
								$thr = ($woTs !== false) ? date('H:i:s', $woTs + (30 * 60)) : '';
							}
							$lt = strtotime('1970-01-01 ' . $attendanceTime);
							$th = $thr !== '' ? strtotime('1970-01-01 ' . $thr) : false;
							if ($lt !== false && $th !== false && $lt >= $th) {
								$remarksToSet = 'LATE';
							}
						}
					}
				} catch (\Throwable $e) {
					$remarksToSet = 'PRESENT';
				}
				$attUid = (string)($postdata['user_id'] ?? $user_id);
				$existing = $dbAttendance->query(
					"SELECT id, remarks, time FROM attendance WHERE deleted = 0 AND user_id = :uid AND date = :d LIMIT 1",
					['uid' => $attUid, 'd' => $todayDate]
				);
				if ($existing) {
					$existingRow = $existing[0] ?? null;
					$existingRemarks = $existingRow ? strtoupper((string)($existingRow->remarks ?? '')) : '';
					if ($existingRemarks !== 'PRESENT' && $existingRemarks !== 'LATE') {
						$dbAttendance->query(
							"UPDATE attendance SET remarks = :r, time = :t, updated_by = 'system', date_updated = :du WHERE deleted = 0 AND user_id = :uid AND date = :d",
							[
								'r' => $remarksToSet,
								't' => $attendanceTime,
								'du' => $attendanceDateTime,
								'uid' => $attUid,
								'd' => $todayDate,
							]
						);
					}
				} else {
					$dbAttendance->query(
						"INSERT INTO attendance (date, user_id, time, remarks, created_by, deleted, date_created) VALUES (:d, :uid, :t, :r, 'system', 0, :dc)",
						['d' => $todayDate, 'uid' => $attUid, 't' => $attendanceTime, 'r' => $remarksToSet, 'dc' => $attendanceDateTime]
					);
				}
			}
		} catch (\Throwable $e) {
			// fail silently; attendance should not block drawer creation
		}

	    $log = new \MyWallets\Log;
	    $log_activity = $log->insert([
		 	'table_name'=>$table,
		 	'action'=>$action,
		 	'user_id'=>$user_id,
		 	'user_name'=>$username,
		 	'description'=>$description,
		 	'date_created'=>$date_created,
		 ]);
			$agentId = $postdata['user_id'];
		message_success("New cashdrawer for user with id: $agentId created successfully!");
		redirect($admin_route);
		
	}

	if(!$csrf)
		$cashdrawer->errors['amount'] = "Form expired!";
	
	set_value('errors',$cashdrawer->errors);
	end_controller:

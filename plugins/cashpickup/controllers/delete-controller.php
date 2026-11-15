<?php

$cashpickup = new \Cashpickups\Cashpickup;

$postdata = $req->post();
$csrf = csrf_verify($postdata);

if ($csrf)
{
	$session = get_session();
	$userId = (int)($session->id ?? 0);
	$isOwner = ((int)($row->created_by ?? 0) > 0 && (int)($row->created_by ?? 0) === $userId);
	if (user_can('delete_cashpickup') || $isOwner)
	{
		$originalSide = strtolower(trim((string)($row->transaction_side ?? '')));
		$originalSource = strtolower(trim((string)($row->source ?? '')));
		$originalBank = trim((string)($row->account_paid_to ?? ''));
		$originalAmount = (float)($row->amount_involved ?? 0);

		$cashpickup->update($row->id, [
			'deleted' => 1,
			'date_deleted' => date('Y-m-d H:i:s'),
		]);

		// Reverse/remove related cashpickup charge commission (if any)
		try {
			$dbComm = new \Core\Database;
			if ($dbComm->table_exists(['commissions'])) {
				$needle = '%| ID ' . (int)($row->id ?? 0);
				$dbComm->query(
					"UPDATE commissions
					 SET deleted = 1, date_deleted = :now
					 WHERE commission_type = :ctype AND description LIKE :needle",
					[
						'now' => date('Y-m-d H:i:s'),
						'ctype' => 'CASHPICKUP CHARGE',
						'needle' => $needle,
					]
				);
			}
		} catch (\Throwable $e) {
			// fail silently
		}

		// Reverse shared bank channels for receive-side bank transactions
		if ($originalSide === 'receive' && $originalSource === 'bank' && $originalAmount > 0 && $originalBank !== '') {
			try {
				$applySharedBank = function(string $bankName, float $amount, string $direction, int $transId, string $transStatus, int $actorUserId): void {
					$bankName = trim((string)$bankName);
					if ($bankName === '' || $amount <= 0) {
						return;
					}
					$agencyHelper = realpath(__DIR__ . '/../../agencybanking/helpers/cashdrawer-sync.php');
					if ($agencyHelper && file_exists($agencyHelper)) {
						require_once $agencyHelper;
					}
					if (!function_exists('Agencybankings\\agency_refresh_ecash_channel')) {
						return;
					}
					if (function_exists('Agencybankings\\agency_canonicalize_drawer_bank_name')) {
						$bankName = \Agencybankings\agency_canonicalize_drawer_bank_name($bankName);
					}
					$network = strtoupper(preg_replace('/[^A-Z0-9]+/i', '_', $bankName));
					$network = trim((string)$network, '_');
					if ($network === '') {
						return;
					}
					$baseChannelKey = 'ECASH_' . $network;
					$sharedOpening = 0.0;
					if (function_exists('Agencybankings\\agency_get_shared_ecash_balance')) {
						$sharedOpening = (float)\Agencybankings\agency_get_shared_ecash_balance($baseChannelKey, $bankName);
					}

					$appliedTargets = [];
					$markApplied = function(int $dId, int $uId) use (&$appliedTargets): void {
						$appliedTargets[$dId . ':' . $uId] = true;
					};
					$hasApplied = function(int $dId, int $uId) use (&$appliedTargets): bool {
						return !empty($appliedTargets[$dId . ':' . $uId]);
					};
					$applyToDrawer = function(int $drawerId, int $drawerUserId) use ($baseChannelKey, $bankName, $amount, $direction, $transId, $transStatus, $sharedOpening): void {
						if ($drawerId <= 0 || $drawerUserId <= 0) {
							return;
						}
						$writeKey = $baseChannelKey;
						if (function_exists('Agencybankings\\agency_find_existing_ecash_channel_key')) {
							$existingKey = \Agencybankings\agency_find_existing_ecash_channel_key($drawerId, $drawerUserId, $writeKey);
							if (!empty($existingKey)) {
								$writeKey = (string)$existingKey;
							}
						}
						if ($writeKey === $baseChannelKey && function_exists('Agencybankings\\agency_resolve_ecash_channel_key')) {
							$resolved = \Agencybankings\agency_resolve_ecash_channel_key($drawerId, $drawerUserId, $bankName);
							if (!empty($resolved)) {
								$writeKey = (string)$resolved;
							}
						}
						$seedOpening = null;
						if (function_exists('Agencybankings\\agency_drawer_has_ecash_channel')) {
							$hasChannel = \Agencybankings\agency_drawer_has_ecash_channel($drawerId, $drawerUserId, $writeKey);
							if (!$hasChannel && $sharedOpening > 0) {
								$seedOpening = $sharedOpening;
							}
						}
						\Agencybankings\agency_refresh_ecash_channel($drawerId, $drawerUserId, $writeKey, $direction, $amount, $transId, $transStatus, $seedOpening);
					};

					$drawerId = 0;
					$drawerUserId = 0;
					if (function_exists('Agencybankings\\agency_find_active_drawer')) {
						$ctx = \Agencybankings\agency_find_active_drawer((int)$actorUserId);
						if (!empty($ctx)) {
							$drawerId = (int)($ctx['drawer_id'] ?? 0);
							$drawerUserId = (int)($ctx['drawer_user_id'] ?? 0);
						}
					}
					if ($drawerId <= 0 || $drawerUserId <= 0) {
						try {
							$dbOwner = new \Core\Database;
							$today = date('Y-m-d');
							$row = $dbOwner->query("SELECT id, user_id FROM cashdrawers WHERE user_id = :user_id AND DATE(date_created) = :today ORDER BY id DESC LIMIT 1", ['user_id' => $actorUserId, 'today' => $today]);
							if (empty($row)) {
								$row = $dbOwner->query("SELECT id, user_id FROM cashdrawers WHERE user_id = :user_id ORDER BY date_created DESC, id DESC LIMIT 1", ['user_id' => $actorUserId]);
							}
							if (!empty($row)) {
								$drawerId = (int)($row[0]->id ?? 0);
								$drawerUserId = (int)($row[0]->user_id ?? 0);
							}
						} catch (\Throwable $e) {}
					}
					if ($drawerId > 0 && $drawerUserId > 0) {
						$applyToDrawer($drawerId, $drawerUserId);
						$markApplied($drawerId, $drawerUserId);
					}

					if (function_exists('Agencybankings\\agency_fetch_admin_manager_drawers_today')) {
						$targets = \Agencybankings\agency_fetch_admin_manager_drawers_today();
						if (!empty($targets)) {
							foreach ($targets as $ctx) {
								$targetDrawerId = (int)($ctx['drawer_id'] ?? 0);
								$targetDrawerUserId = (int)($ctx['drawer_user_id'] ?? 0);
								if ($targetDrawerId <= 0 || $targetDrawerUserId <= 0) {
									continue;
								}
								if ($hasApplied($targetDrawerId, $targetDrawerUserId)) {
									continue;
								}
								$applyToDrawer($targetDrawerId, $targetDrawerUserId);
								$markApplied($targetDrawerId, $targetDrawerUserId);
							}
						}
					}
				};

				$applySharedBank($originalBank, $originalAmount, 'out', (int)($row->id ?? 0), 'Reversed', (int)$userId);
			} catch (\Throwable $e) {
				// fail silently
			}
		}

		message_success('Cash pickup deleted successfully!');
		redirect($admin_route.'/'.$plugin_route);
	}
}

if(!$csrf)
	$cashpickup->errors['date'] = "Form expired!";

set_value('errors',$cashpickup->errors);

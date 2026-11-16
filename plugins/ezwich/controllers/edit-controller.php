<?php

require_once __DIR__ . '/../../agencybanking/helpers/cashdrawer-sync.php';

if (!empty($row)) {
	date_default_timezone_set('Africa/Accra');

	$postdata = $req->post();
	$postdata['id'] = $row->id;
	$postdata['momo_image'] = $postdata['momo_image'] ?? ($row->momo_image ?? '');
	$postdata['merchant_receipt'] = $postdata['merchant_receipt'] ?? ($row->merchant_receipt ?? '');
	$postdata['customer_receipt'] = $postdata['customer_receipt'] ?? ($row->customer_receipt ?? '');

	$existingStatus = ezwich_normalize_status($row->transaction_status ?? '', 'Draft');
	if (in_array(strtolower(trim((string)$existingStatus)), ['cancelled', 'canceled'], true)) {
		$ezwich->errors['transaction_status'] = 'Cancelled Ezwich transactions cannot be edited.';
		set_value('errors', array_merge($ezwich->errors, $req->upload_errors));
		message_fail($ezwich->errors['transaction_status']);
		goto end_edit;
	}
	if (ezwich_status_is_declined($existingStatus)) {
		$ezwich->errors['transaction_status'] = 'Declined ezwich transactions can no longer be edited.';
		set_value('errors', array_merge($ezwich->errors, $req->upload_errors));
		message_fail($ezwich->errors['transaction_status']);
		goto end_edit;
	}

	try {
		$dbSchema = new \Core\Database;
		$neededCols = ['momo_image', 'merchant_receipt', 'customer_receipt', 'rejection_reason'];
		$typeMap = [
			'momo_image' => 'VARCHAR(255) NULL',
			'merchant_receipt' => 'VARCHAR(255) NULL',
			'customer_receipt' => 'VARCHAR(255) NULL',
			'rejection_reason' => 'LONGTEXT NULL',
		];
		$missingCols = [];
		foreach ($neededCols as $col) {
			$hasCol = $dbSchema->query(
				"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'ezwichs' AND COLUMN_NAME = :col LIMIT 1",
				['db' => DB_NAME, 'col' => $col]
			);
			if (empty($hasCol)) {
				$missingCols[] = $col;
			}
		}
		if (!empty($missingCols)) {
			foreach ($missingCols as $col) {
				try {
					$colType = $typeMap[$col] ?? 'VARCHAR(255) NULL';
					$dbSchema->query("ALTER TABLE ezwichs ADD COLUMN {$col} {$colType}");
				} catch (\Throwable $e) {
					$ezwich->errors[$col] = "Database column missing: ezwichs.{$col}";
					set_value('errors', array_merge($ezwich->errors, $req->upload_errors));
					return;
				}
			}
		}
	} catch (\Throwable $e) {
		$ezwich->errors['momo_image'] = 'Unable to validate ezwich photo storage.';
		set_value('errors', array_merge($ezwich->errors, $req->upload_errors));
		return;
	}

	$req->upload_folder = 'uploads/ezwich';
	$uploadedMomoImage = $req->upload_files('momo_image');
	if (!empty($uploadedMomoImage)) {
		$postdata['momo_image'] = $uploadedMomoImage;
	}
	$uploadedMerchantReceipt = $req->upload_files('merchant_receipt');
	if (!empty($uploadedMerchantReceipt)) {
		$postdata['merchant_receipt'] = $uploadedMerchantReceipt;
	}
	$uploadedCustomerReceipt = $req->upload_files('customer_receipt');
	if (!empty($uploadedCustomerReceipt)) {
		$postdata['customer_receipt'] = $uploadedCustomerReceipt;
	}

	$isApprovedLockedRecord = ezwich_status_is_approved($existingStatus);
	if ($isApprovedLockedRecord) {
		$requestedStatus = ezwich_normalize_status($postdata['transaction_status'] ?? $existingStatus, $existingStatus);
		$lockedFields = [
			'usn',
			'transaction_id',
			'network',
			'transaction_type',
			'wallet_in_id',
			'wallet_out_id',
			'phone',
			'momo_image',
			'merchant_receipt',
			'customer_receipt',
			'amount_payable',
			'balance',
			'amount',
			'destination_bank',
			'charge',
			'receiving',
			'receiving_sim',
			'assigned',
			'disabled',
			'deleted',
			'created_by',
			'date_created',
			'date_deleted',
		];

		foreach ($lockedFields as $field) {
			$postdata[$field] = $row->{$field} ?? ($postdata[$field] ?? null);
		}

		$postdata['transaction_status'] = $requestedStatus;
	}

	$requestedStatus = ezwich_normalize_status($postdata['transaction_status'] ?? $existingStatus, $existingStatus);
	$requiresRejectionReason = in_array(strtoupper($requestedStatus), ['REJECTED', 'DECLINED'], true);
	$rejectionReason = trim((string)($postdata['rejection_reason'] ?? ($row->rejection_reason ?? '')));
	$postdata['transaction_status'] = $requestedStatus;
	$postdata['rejection_reason'] = $requiresRejectionReason ? $rejectionReason : null;

	$csrf = csrf_verify($postdata);
	$isValidEdit = false;
	if ($csrf) {
		if ($isApprovedLockedRecord) {
			$allowedStatuses = ['Approved', 'Declined'];
			$requestedStatus = ezwich_normalize_status($postdata['transaction_status'] ?? $existingStatus, $existingStatus);
			if (!in_array($requestedStatus, $allowedStatuses, true)) {
				$ezwich->errors['transaction_status'] = 'Approved ezwich transactions can only stay Approved or be changed to Declined.';
			}
			$isValidEdit = empty($ezwich->errors);
		} else {
			$isValidEdit = $ezwich->validate_update($postdata);
		}
		if ($requiresRejectionReason && $rejectionReason === '') {
			$ezwich->errors['rejection_reason'] = 'Rejection reason is required when declining or rejecting an ezwich transaction.';
		}
		$isValidEdit = $isValidEdit && empty($ezwich->errors);
	}

	if ($csrf && $isValidEdit) {
		if (user_can('edit_ezwich')) {
			$postdata['date_updated'] = date("Y-m-d H:i:s");

			$session = get_session();
			if (!empty($session->id)) {
				$username = $session->first_name . ' ' . $session->last_name;
				$user_id = $session->id;
			} else {
				$username = $row->first_name . ' ' . $row->last_name;
				$user_id = $row->id;
			}

			$postdata['updated_by'] = $user_id;
			$newStatus = ezwich_normalize_status($postdata['transaction_status'] ?? $existingStatus, $existingStatus);
			$postdata['transaction_status'] = $newStatus;
			$requiresRejectionReason = in_array(strtoupper($newStatus), ['REJECTED', 'DECLINED'], true);
			$postdata['rejection_reason'] = $requiresRejectionReason
				? trim((string)($postdata['rejection_reason'] ?? ''))
				: null;
			if ($requiresRejectionReason && $postdata['rejection_reason'] === '') {
				$ezwich->errors['rejection_reason'] = 'Rejection reason is required when declining or rejecting an ezwich transaction.';
				set_value('errors', array_merge($ezwich->errors, $req->upload_errors));
				message_fail($ezwich->errors['rejection_reason']);
				goto end_edit;
			}

			$isApprovedToDeclined = ezwich_status_is_approved($existingStatus) && ezwich_status_is_declined($newStatus);
			if ($isApprovedLockedRecord && !$isApprovedToDeclined) {
				$ezwich->errors['transaction_status'] = 'Approved ezwich transactions can only be changed to Declined.';
				set_value('errors', array_merge($ezwich->errors, $req->upload_errors));
				message_fail($ezwich->errors['transaction_status']);
				goto end_edit;
			}

			$currencyDetails = $postdata['denos'] ?? '[]';
			$currencyArray = json_decode((string)$currencyDetails, true);
			if (!is_array($currencyArray)) {
				$currencyArray = [];
			}

			if ($isApprovedToDeclined) {
				$declineReversal = ezwich_reverse_approved_effects((object)array_merge((array)$row, $postdata), $newStatus);
				if (empty($declineReversal['ok'])) {
					$ezwich->errors['transaction_status'] = $declineReversal['message'] ?: 'Unable to reverse this approved transaction.';
					set_value('errors', array_merge($ezwich->errors, $req->upload_errors));
					message_fail($ezwich->errors['transaction_status']);
					goto end_edit;
				}
			} else {
				$syncPayload = array_merge((array)$row, $postdata);
				$syncPayload['network'] = 'MTN MOMOPAY';
				$syncPayload['date_created'] = $row->date_created ?? $postdata['date_updated'];
				$syncPayload['date_updated'] = $postdata['date_updated'];
				try {
					\Agencybankings\agency_sync_cashdrawer(
						$syncPayload,
						$currencyArray,
						(int)($row->created_by ?? $user_id ?? 0),
						(int)($session->id ?? $user_id ?? 0)
					);
				} catch (\Throwable $e) {
					error_log('EZWICH CASHDRAWER SYNC FAILED (EDIT): ' . $e->getMessage());
				}
			}

			if ($isApprovedLockedRecord) {
				$updateData = [
					'transaction_status' => $newStatus,
					'rejection_reason' => $postdata['rejection_reason'],
					'updated_by' => $user_id,
					'date_updated' => $postdata['date_updated'],
				];
			} else {
				$updateData = $postdata;
			}

			unset($updateData['id'], $updateData['denos']);
			$ezwich->update($row->id, $updateData);
			if ($ezwich->has_error) {
				$ezwich->errors['transaction_status'] = 'Ezwich status update failed: ' . ($ezwich->error ?: 'unknown database error');
				set_value('errors', array_merge($ezwich->errors, $req->upload_errors));
				message_fail($ezwich->errors['transaction_status']);
				goto end_edit;
			}

			try {
				$commissionDb = new \Core\Database;
				$commissionDb->query(
					"DELETE FROM commissions WHERE commission_type = :commission_type AND description LIKE :needle",
					[
						'commission_type' => 'EZWICH CHARGE',
						'needle' => '%| ID ' . ($row->id ?? ''),
					]
				);
			} catch (\Throwable $e) {
				error_log('EZWICH CHARGE COMMISSION CLEANUP FAILED (EDIT): ' . $e->getMessage());
			}

			$action = 'edited';
			$oldEzwich = $row->usn . ' ' . $row->transaction_type;
			$transaction_id = $row->id;
			$ezwichLabel = $postdata['usn'] . ' ' . $postdata['transaction_type'];
			$table = 'ezwichs';
			$description = "$username $action ezwich transaction $oldEzwich with id $transaction_id";
			$date_created = date("Y-m-d H:i:s");

			$log = new \Ezwichs\Log;
			$log->insert([
				'table_name' => $table,
				'action' => $action,
				'user_id' => $user_id,
				'user_name' => $username,
				'description' => $description,
				'date_created' => $date_created,
			]);

			message_success("Transaction with id ($transaction_id) edited successfully!");
			redirect($admin_route . '/' . $plugin_route);
		}
	}

	if (!$csrf) {
		$ezwich->errors['usn'] = "Form expired!";
	}

	set_value('errors', array_merge($ezwich->errors, $req->upload_errors));
	end_edit:
} else {
	message_fail("Record not found");
}

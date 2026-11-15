<?php

/**
 * Momoremit Cashout Commission AJAX Controller
 * Handles API requests for commission management
 */

date_default_timezone_set('Africa/Accra');

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');

while (ob_get_level() > 0) {
	ob_end_clean();
}

if (!function_exists('momoremit_commission_api_json')) {
	function momoremit_commission_api_json(array $payload, int $statusCode = 200): void
	{
		while (ob_get_level() > 0) {
			ob_end_clean();
		}
		header('Content-Type: application/json; charset=utf-8');
		http_response_code($statusCode);
		echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		exit;
	}
}

$session = get_session();
$userId = (int)($session->id ?? 0);

// Ensure user is authenticated
if ($userId <= 0) {
	momoremit_commission_api_json(['success' => false, 'message' => 'Unauthorized'], 401);
}

header('Content-Type: application/json; charset=utf-8');

try {
	$action = trim((string)($_REQUEST['action'] ?? 'list'));
	
	switch ($action) {
		case 'list':
		case 'pending':
			// Admins and operations managers can view all pending commissions.
			if (!user_can('view_momoremit_details') && !user_can('view_momoremit') && !user_can('is_operations_manager')) {
				http_response_code(403);
				echo json_encode(['success' => false, 'message' => 'Access denied']);
				exit;
			}
			
			$canViewAllCommissions = user_can('is_admin') || user_can('is_operations_manager');
			$userData = momoremit_get_user_pending_commissions($userId, $canViewAllCommissions);
			$commissions = $userData['commissions'] ?? [];
			$summary = $userData['summary'] ?? null;
			
			// Format commissions for display
			$formattedCommissions = array_map(function($comm) {
				return momoremit_commission_payload($comm);
			}, $commissions);
			
			echo json_encode([
				'success' => true,
				'commissions' => $formattedCommissions,
				'summary' => [
					'total_count' => (int)($summary->total_count ?? 0),
					'pending_count' => (int)($summary->pending_count ?? 0),
					'transferred_count' => (int)($summary->transferred_count ?? 0),
					'pending_amount' => number_format((float)($summary->pending_amount ?? 0), 2),
					'transferred_amount' => number_format((float)($summary->transferred_amount ?? 0), 2),
					'by_commission_type' => is_array($summary->by_commission_type ?? null) ? $summary->by_commission_type : [],
				],
			]);
			break;
			
		case 'transfer':
			// Users can transfer their own commissions; admins can only transfer records they created themselves.
			if (!user_can('edit_momoremit') && !user_can('view_momoremit') && !user_can('view_momoremit_details')) {
				http_response_code(403);
				echo json_encode(['success' => false, 'message' => 'Access denied']);
				exit;
			}
			
			if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
				http_response_code(405);
				echo json_encode(['success' => false, 'message' => 'Method not allowed']);
				exit;
			}
			
			// Verify CSRF token
			$postData = $_POST;
			if (!csrf_verify($postData)) {
				http_response_code(419);
				echo json_encode(['success' => false, 'message' => 'Invalid security token']);
				exit;
			}
			
			$commissionIds = (array)($_POST['commission_ids'] ?? []);
			$commissionIds = array_filter(array_map('intval', $commissionIds));
			$transferAmount = isset($_POST['amount']) ? (float)$_POST['amount'] : null;
			$transferAmounts = [];
			$whatsappAdminIds = array_values(array_filter(array_map('intval', (array)($_POST['whatsapp_admin_ids'] ?? []))));
			$proofImages = [];
			$uploadRequest = new \Core\Request;
			$uploadRequest->upload_folder = 'uploads/momoremit/commission-proofs';
			foreach ($commissionIds as $commissionId) {
				$fileKey = 'proof_image_' . (int)$commissionId;
				if (!empty($_FILES[$fileKey]['name'])) {
					$uploaded = $uploadRequest->upload_files($fileKey);
					if (is_string($uploaded) && $uploaded !== '') {
						$proofImages[(int)$commissionId] = $uploaded;
					} elseif (!empty($uploadRequest->upload_errors[$fileKey])) {
						momoremit_commission_api_json(['success' => false, 'message' => $uploadRequest->upload_errors[$fileKey]], 422);
					}
				}
			}
			if (isset($_POST['transfer_amounts']) && is_array($_POST['transfer_amounts'])) {
				foreach ($_POST['transfer_amounts'] as $id => $value) {
					$idKey = (int)$id;
					if ($idKey > 0 && is_numeric($value)) {
						$transferAmounts[$idKey] = (float)$value;
					}
				}
			}
			
			if (empty($commissionIds)) {
				echo json_encode(['success' => false, 'message' => 'No commissions selected']);
				exit;
			}
			
			// Admins and operations managers can only transfer records they created.
			$isAdmin = user_can('is_admin');
			$cashoutComm = new \Momoremits\CashoutCommission;
			foreach ($commissionIds as $commId) {
				$commission = $cashoutComm->first(['id' => $commId]);
				if (!$commission) {
					http_response_code(403);
					echo json_encode(['success' => false, 'message' => 'Commission not found']);
					exit;
				}
				$commissionOwnerId = (int)($commission->user_id ?? 0);
				$commissionCreatorId = (int)($commission->created_by ?? 0);
				$canTransfer = $commissionOwnerId === $userId || $commissionCreatorId === $userId;
				if ($isAdmin) {
					$canTransfer = $commissionCreatorId === $userId;
				}
				if (!$canTransfer) {
					http_response_code(403);
					echo json_encode(['success' => false, 'message' => 'You do not have permission to transfer these commissions']);
					exit;
				}
			}
			
			// Perform transfer
			$result = momoremit_transfer_commissions_to_main($commissionIds, $transferAmount, $userId, $transferAmounts, $proofImages, $whatsappAdminIds);
			
			if ($result['success']) {
				echo json_encode([
					'success' => true,
					'message' => $result['message'],
					'commission_ids' => $result['commission_ids'] ?? [],
					'whatsapp' => $result['whatsapp'] ?? [],
				]);
			} else {
				echo json_encode([
					'success' => false,
					'message' => $result['message'],
				]);
			}
			break;

		case 'approve':
			if (!user_can('is_admin') && !user_can('is_operations_manager')) {
				momoremit_commission_api_json(['success' => false, 'message' => 'Only an administrator or operations manager can approve commission requests.'], 403);
			}
			if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST)) {
				momoremit_commission_api_json(['success' => false, 'message' => 'Invalid approval request.'], 419);
			}
			$cashoutId = (int)($_POST['cashout_commission_id'] ?? 0);
			$whatsappAdminIds = array_values(array_filter(array_map('intval', (array)($_POST['whatsapp_admin_ids'] ?? []))));
			$result = momoremit_approve_transfer_request($cashoutId, $userId, $whatsappAdminIds);
			momoremit_commission_api_json($result, $result['success'] ? 200 : 422);

		case 'review':
			if (!user_can('is_admin') && !user_can('is_operations_manager')) {
				momoremit_commission_api_json(['success' => false, 'message' => 'Only an administrator or operations manager can review commission requests.'], 403);
			}
			if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST)) {
				momoremit_commission_api_json(['success' => false, 'message' => 'Invalid review request.'], 419);
			}
			$decision = strtolower(trim((string)($_POST['decision'] ?? '')));
			$reason = trim((string)($_POST['reason'] ?? ''));
			$ids = array_values(array_unique(array_filter(
				array_map('intval', (array)($_POST['cashout_commission_ids'] ?? [])),
				static fn(int $id): bool => $id > 0
			)));
			$whatsappAdminIds = array_values(array_filter(array_map('intval', (array)($_POST['whatsapp_admin_ids'] ?? []))));
			if (!in_array($decision, ['approve', 'reject'], true) || empty($ids)) {
				momoremit_commission_api_json(['success' => false, 'message' => 'Select at least one pending commission and a valid decision.'], 422);
			}
			if ($decision === 'reject' && $reason === '') {
				momoremit_commission_api_json(['success' => false, 'message' => 'A rejection reason is required.'], 422);
			}
			$results = [];
			foreach ($ids as $id) {
				$result = $decision === 'approve'
					? momoremit_approve_transfer_request($id, $userId, $whatsappAdminIds)
					: momoremit_reject_transfer_request($id, $userId, $reason, $whatsappAdminIds);
				$result['cashout_commission_id'] = $id;
				$results[] = $result;
			}
			$failed = array_values(array_filter($results, static fn($item) => empty($item['success'])));
			$succeeded = count($results) - count($failed);
			if ($succeeded === 0) {
				momoremit_commission_api_json(['success' => false, 'message' => $failed[0]['message'] ?? 'No commission requests could be reviewed.', 'results' => $results], 422);
			}
			$whatsapp = ['ready' => 0, 'links' => [], 'reasons' => []];
			foreach ($results as $reviewResult) {
				if (empty($reviewResult['success'])) {
					continue;
				}
				$reviewWhatsapp = $reviewResult['whatsapp'] ?? [];
				$whatsapp['ready'] += (int)($reviewWhatsapp['ready'] ?? 0);
				$whatsapp['links'] = array_merge($whatsapp['links'], $reviewWhatsapp['links'] ?? []);
				$whatsapp['reasons'] = array_merge($whatsapp['reasons'], $reviewWhatsapp['reasons'] ?? []);
			}
			$actionLabel = $decision === 'approve' ? 'approved' : 'rejected';
			$message = $succeeded . ' commission request(s) ' . $actionLabel . '.';
			if (!empty($failed)) {
				$message .= ' ' . count($failed) . ' failed: ' . ($failed[0]['message'] ?? 'Please review the pending requests again.');
			}
			momoremit_commission_api_json([
				'success' => true,
				'partial' => !empty($failed),
				'message' => $message,
				'results' => $results,
				'whatsapp' => $whatsapp,
			]);
			
		case 'details':
			// Admins and operations managers can view all commission records.
			if (!user_can('view_momoremit_details') && !user_can('view_momoremit') && !user_can('is_operations_manager')) {
				http_response_code(403);
				echo json_encode(['success' => false, 'message' => 'Access denied']);
				exit;
			}
			
			$commissionId = (int)($_GET['id'] ?? 0);
			if ($commissionId <= 0) {
				echo json_encode(['success' => false, 'message' => 'Invalid commission ID']);
				exit;
			}
			
			$cashoutComm = new \Momoremits\CashoutCommission;
			$commission = $cashoutComm->first(['id' => $commissionId]);
			$isAdmin = user_can('is_admin') || user_can('is_operations_manager');
			if (!$commission) {
				http_response_code(404);
				echo json_encode(['success' => false, 'message' => 'Commission not found']);
				exit;
			}
			$commissionOwnerId = (int)($commission->user_id ?? 0);
			$commissionCreatorId = (int)($commission->created_by ?? 0);
			$hasAccess = $isAdmin || $commissionOwnerId === $userId || $commissionCreatorId === $userId;
			if (!$hasAccess) {
				http_response_code(403);
				echo json_encode(['success' => false, 'message' => 'Commission not found or access denied']);
				exit;
			}
			
			echo json_encode([
				'success' => true,
				'commission' => momoremit_commission_payload($commission),
			]);
			break;
			
		case 'transaction-commission':
			// Get commission for a specific transaction
			if (!user_can('view_momoremit_details')) {
				http_response_code(403);
				echo json_encode(['success' => false, 'message' => 'Access denied']);
				exit;
			}
			
			$transactionId = (int)($_GET['transaction_id'] ?? 0);
			if ($transactionId <= 0) {
				echo json_encode(['success' => false, 'message' => 'Invalid transaction ID']);
				exit;
			}
			
			$commission = momoremit_get_commission_by_transaction($transactionId);
			
			if (!$commission) {
				echo json_encode([
					'success' => false,
					'message' => 'No commission found for this transaction',
					'commission' => null,
				]);
				exit;
			}
			
			echo json_encode([
				'success' => true,
				'commission' => momoremit_commission_payload($commission),
			]);
			break;
			
		default:
			http_response_code(400);
			echo json_encode(['success' => false, 'message' => 'Unknown action']);
			break;
	}
	
} catch (\Throwable $e) {
	http_response_code(500);
	error_log('Momoremit Commission API Error: ' . $e->getMessage());
	momoremit_commission_api_json([
		'success' => false,
		'message' => 'Server error: ' . $e->getMessage(),
	], 500);
}

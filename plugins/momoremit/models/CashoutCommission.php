<?php

namespace Momoremits;
use \Model\Model;

defined('ROOT') or die("Direct script access denied");

/**
 * CashoutCommission class
 * Manages pending commissions from cashout/withdrawal transactions
 * These commissions can be transferred to the main commissions table
 */
class CashoutCommission extends Model
{
	protected $table = 'cashout_commissions';
	public $primary_key = 'id';

	protected $allowedColumns = [
		'id',
		'transaction_id',
		'transaction_type',
		'network',
		'commission_type',
		'commission_rate',
		'transaction_amount',
		'is_capped',
		'cap_value',
		'commission_amount',
		'expected_value_percentage',
		'expected_value_amount',
		'status',
		'transferred_to_commission_id',
		'transferred_amount',
		'user_id',
		'created_by',
		'transferred_by',
		'transferred_at',
		'notes',
		'disabled',
		'deleted',
		'date_created',
		'date_updated',
		'date_deleted',
	];
	
	protected $allowedUpdateColumns = [
		'transaction_id',
		'transaction_type',
		'network',
		'commission_type',
		'commission_rate',
		'transaction_amount',
		'is_capped',
		'cap_value',
		'commission_amount',
		'expected_value_percentage',
		'expected_value_amount',
		'status',
		'transferred_to_commission_id',
		'transferred_amount',
		'user_id',
		'transferred_by',
		'transferred_at',
		'notes',
		'disabled',
		'deleted',
		'date_updated',
		'date_deleted',
	];

	/**
	 * Calculate commission for a cashout/withdrawal transaction
	 * 
	 * @param string $network The network (MTN, Vodafone, Airtel, etc.)
	 * @param string $transactionType The transaction type (Withdrawal, Sending, etc.)
	 * @param float $amount The transaction amount
	 * @return array|null Commission data or null if no rate configured
	 */
	public static function calculateCommission($network, $transactionType, $amount)
	{
		// Normalize commission type name
		$network = strtoupper(trim((string)$network));
		$transactionType = strtoupper(trim((string)$transactionType));
		
		// Build commission type: NETWORK TRANSACTIONTYPE (e.g., "MTN CASHOUT")
		$commissionTypeName = $network . ' ' . $transactionType;
		
		// Get commission rate from admin settings
		try {
			$db = new \Core\Database;
			$rateQuery = "SELECT * FROM commission_rates 
						 WHERE UPPER(TRIM(commission_type)) = UPPER(TRIM(:commission_type))
						 AND status = 1
						 LIMIT 1";
			
			$result = $db->query($rateQuery, ['commission_type' => $commissionTypeName]);
			
			if (empty($result)) {
				return null; // No rate configured
			}
			
			$rate = $result[0];
			$commissionRate = (float)($rate->commission_rate ?? 0);
			$isCapped = (int)($rate->is_capped ?? 0);
			$capValue = (float)($rate->cap_value ?? 0);
			$expectedValuePercentage = (float)($rate->expected_value ?? 0);
			
			// Calculate commission amount
			$commissionAmount = ($amount * $commissionRate) / 100;
			
			// Apply cap if enabled. The cap is a maximum for the commission value itself.
			if ($isCapped && $capValue > 0) {
				$commissionAmount = min($commissionAmount, $capValue);
			}
			
			// Calculate expected value amount from the final capped commission value.
			$expectedValueAmount = ($commissionAmount * $expectedValuePercentage) / 100;
			
			return [
				'commission_type' => $commissionTypeName,
				'commission_rate' => $commissionRate,
				'is_capped' => $isCapped,
				'cap_value' => $capValue,
				'commission_amount' => round($commissionAmount, 2),
				'expected_value_percentage' => $expectedValuePercentage,
				'expected_value_amount' => round($expectedValueAmount, 2),
			];
		} catch (\Throwable $e) {
			error_log('Error calculating commission: ' . $e->getMessage());
			return null;
		}
	}

	/**
	 * Get all pending commissions for a user
	 * 
	 * @param int $userId The user ID
	 * @return array Array of pending commission records
	 */
	public function getPendingForUser($userId)
	{
		return $this->where(['user_id' => $userId, 'status' => 'PENDING', 'deleted' => 0])
			->orderBy('date_created', 'DESC')
			->get();
	}

	/**
	 * Get commission summary for a user
	 * 
	 * @param int $userId The user ID
	 * @return object Summary with totals
	 */
	public function getSummaryForUser($userId)
	{
		$db = new \Core\Database;
		
		$query = "SELECT 
			COUNT(*) as total_count,
			SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending_count,
			SUM(CASE WHEN status = 'TRANSFERRED' THEN 1 ELSE 0 END) as transferred_count,
			SUM(CASE WHEN status = 'PENDING' THEN commission_amount ELSE 0 END) as pending_amount,
			SUM(CASE WHEN status = 'TRANSFERRED' THEN transferred_amount ELSE 0 END) as transferred_amount
		FROM cashout_commissions
		WHERE user_id = :user_id AND deleted = 0";
		
		$result = $db->query($query, ['user_id' => $userId]);
		
		return !empty($result) ? $result[0] : null;
	}

	/**
	 * Transfer commission(s) to main commissions table
	 * Supports partial and full transfers
	 * 
	 * @param array $commissionIds Array of cashout commission IDs to transfer
	 * @param float $amount Amount to transfer (null = all)
	 * @param int $userId User performing the transfer
	 * @return array Result with status and commission ID
	 */
	public function transferToCommissions($commissionIds, $amount = null, $userId = null)
	{
		if (!is_array($commissionIds)) {
			$commissionIds = [$commissionIds];
		}
		
		$result = ['success' => false, 'commission_id' => null, 'message' => ''];
		
		try {
			$db = new \Core\Database;
			
			// Fetch the cashout commissions
			$placeholders = implode(',', array_fill(0, count($commissionIds), '?'));
			$query = "SELECT * FROM cashout_commissions 
					 WHERE id IN ($placeholders) 
					 AND status = 'PENDING' 
					 AND deleted = 0";
			
			$cashoutCommissions = $db->query($query, $commissionIds);
			
			if (empty($cashoutCommissions)) {
				$result['message'] = 'No pending commissions found for transfer.';
				return $result;
			}
			
			// Calculate total amount to transfer
			$totalAmount = 0;
			foreach ($cashoutCommissions as $cashoutComm) {
				$totalAmount += (float)($cashoutComm->commission_amount ?? 0);
			}
			
			if ($amount !== null) {
				$amount = (float)$amount;
				if ($amount > $totalAmount) {
					$result['message'] = 'Transfer amount exceeds total pending commission amount.';
					return $result;
				}
				$transferAmount = $amount;
			} else {
				$transferAmount = $totalAmount;
			}
			
			// Create commission record in main commissions table
			$cashoutComm = $cashoutCommissions[0]; // Use first for reference
			$description = 'MOMOREMIT CASHOUT COMMISSION | ' . $cashoutComm->network . ' | Amount: ' . 
						   number_format($transferAmount, 2) . ' | Transferred from cashout pending';
			
			$commissionData = [
				'date_received' => date('Y-m-d H:i:s'),
				'colletion_period' => 'INSTANT',
				'network' => $cashoutComm->network,
				'commission_type' => $cashoutComm->commission_type,
				'description' => $description,
				'pay_to' => $cashoutComm->user_id,
				'receiving_wallet' => null,
				'amount' => $transferAmount,
				'transaction_status' => 'Approved',
				'created_by' => $userId,
				'updated_by' => null,
				'disabled' => 0,
				'deleted' => 0,
				'date_created' => date('Y-m-d H:i:s'),
				'date_updated' => null,
				'date_deleted' => null,
			];
			
			// Insert into commissions table
			$commissionSql = "INSERT INTO commissions (" . 
							implode(',', array_keys($commissionData)) . 
							") VALUES (:" . 
							implode(',:', array_keys($commissionData)) . ")";
			
			$db->query($commissionSql, $commissionData);
			
			// Get the inserted commission ID
			$commissionId = null;
			$lastIdQuery = "SELECT LAST_INSERT_ID() as id";
			$lastIdResult = $db->query($lastIdQuery);
			if (!empty($lastIdResult)) {
				$commissionId = (int)($lastIdResult[0]->id ?? 0);
			}
			
			if ($commissionId <= 0) {
				$result['message'] = 'Failed to create commission record.';
				return $result;
			}
			
			// Update cashout commissions - mark as transferred
			$updateQuery = "UPDATE cashout_commissions 
						   SET status = 'TRANSFERRED',
							   transferred_to_commission_id = :commission_id,
							   transferred_amount = :amount,
							   transferred_by = :user_id,
							   transferred_at = :transferred_at,
							   date_updated = :date_updated
						   WHERE id IN (" . $placeholders . ")";
			
			$updateParams = array_merge(
				[
					'commission_id' => $commissionId,
					'amount' => $transferAmount,
					'user_id' => $userId,
					'transferred_at' => date('Y-m-d H:i:s'),
					'date_updated' => date('Y-m-d H:i:s'),
				],
				$commissionIds
			);
			
			$db->query($updateQuery, $updateParams);
			
			$result['success'] = true;
			$result['commission_id'] = $commissionId;
			$result['message'] = 'Commission transferred successfully. Amount: GHS ' . number_format($transferAmount, 2);
			
		} catch (\Throwable $e) {
			error_log('Error transferring commission: ' . $e->getMessage());
			$result['message'] = 'Error during transfer: ' . $e->getMessage();
		}
		
		return $result;
	}

	public function validate_insert(array $data): bool
	{
		$this->errors = [];
		
		if (empty($data['transaction_id'])) {
			$this->errors['transaction_id'] = 'Transaction ID is required';
		}
		
		if (empty($data['network'])) {
			$this->errors['network'] = 'Network is required';
		}
		
		if (empty($data['commission_type'])) {
			$this->errors['commission_type'] = 'Commission type is required';
		}
		
		if (empty($data['user_id'])) {
			$this->errors['user_id'] = 'User ID is required';
		}
		
		return empty($this->errors);
	}

	public function validate_update(array $data): bool
	{
		$this->errors = [];
		
		// Only allow updating status and transfer-related fields
		$allowedFields = ['status', 'transferred_to_commission_id', 'transferred_amount', 
						 'transferred_by', 'transferred_at', 'notes'];
		
		foreach ($data as $field => $value) {
			if (!in_array($field, $allowedFields, true)) {
				$this->errors[$field] = 'Field ' . $field . ' cannot be updated';
			}
		}
		
		return empty($this->errors);
	}
}

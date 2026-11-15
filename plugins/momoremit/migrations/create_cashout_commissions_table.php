<?php

/**
 * Migration: Create cashout_commissions table
 * This table tracks pending commissions from momoremit cashout/withdrawal transactions
 * Commissions remain here until the user transfers them to the main commissions table
 */

defined('ROOT') or die("Direct script access denied");

function momoremit_ensure_cashout_commissions_table()
{
    $db = new \Core\Database;
    $table = 'cashout_commissions';
    
    // Check if table already exists
    $checkQuery = "SHOW TABLES LIKE '$table'";
    $exists = $db->query($checkQuery);
    
    if (!empty($exists)) {
        return true; // Table already exists
    }
    
    // Create the table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS `cashout_commissions` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `transaction_id` BIGINT(20) UNSIGNED DEFAULT NULL COMMENT 'Reference to momoremit transaction (withdrawal/sending)',
        `transaction_type` VARCHAR(50) DEFAULT NULL COMMENT 'Withdrawal or Sending',
        `network` VARCHAR(50) DEFAULT NULL COMMENT 'Network (MTN, Vodafone, Airtel, etc.)',
        `commission_type` VARCHAR(255) DEFAULT NULL COMMENT 'Normalized commission type (e.g., MTN CASHOUT)',
        `commission_rate` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Commission rate percentage',
        `transaction_amount` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Original transaction amount',
        `is_capped` TINYINT(1) DEFAULT 0 COMMENT '1 if commission is capped',
        `cap_value` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Maximum commission value if capped',
        `commission_amount` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Calculated commission amount',
        `expected_value_percentage` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Expected value as percentage',
        `expected_value_amount` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Expected value in GHS',
        `status` VARCHAR(50) DEFAULT 'PENDING' COMMENT 'PENDING, TRANSFERRED, CANCELLED',
        `transferred_to_commission_id` INT(11) DEFAULT NULL COMMENT 'Commission ID if transferred',
        `transferred_amount` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Amount transferred (supports partial transfers)',
        `user_id` INT(11) DEFAULT NULL COMMENT 'User who owns this cashout commission',
        `created_by` INT(11) DEFAULT NULL COMMENT 'User who created the transaction',
        `transferred_by` INT(11) DEFAULT NULL COMMENT 'User who transferred the commission',
        `transferred_at` DATETIME DEFAULT NULL COMMENT 'Timestamp of transfer',
        `notes` TEXT DEFAULT NULL COMMENT 'Additional notes about transfer',
        `disabled` TINYINT(1) DEFAULT 0,
        `deleted` TINYINT(1) DEFAULT 0,
        `date_created` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `date_updated` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `date_deleted` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `transaction_id` (`transaction_id`),
        KEY `user_id` (`user_id`),
        KEY `network` (`network`),
        KEY `commission_type` (`commission_type`),
        KEY `status` (`status`),
        KEY `date_created` (`date_created`),
        KEY `transferred_to_commission_id` (`transferred_to_commission_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
    
    try {
        $db->query($sql);
        return true;
    } catch (\Throwable $e) {
        error_log('Failed to create cashout_commissions table: ' . $e->getMessage());
        return false;
    }
}

// Run migration on plugin load
momoremit_ensure_cashout_commissions_table();
?>

<?php

namespace Migration;

defined('FCPATH') or die("Direct script access denied");

class CommissionAssignmentWorkflow extends Migration
{
	public function up()
	{
		$this->query("ALTER TABLE commissions ADD COLUMN assigned BIGINT UNSIGNED NULL AFTER pay_to");
		$this->query("ALTER TABLE commissions ADD COLUMN sim_assignment_id BIGINT UNSIGNED NULL AFTER assigned");
		$this->query("ALTER TABLE commissions ADD COLUMN proof_image VARCHAR(255) NULL AFTER sim_assignment_id");
		$this->query("ALTER TABLE commissions ADD COLUMN reject_reason TEXT NULL AFTER proof_image");
		$this->query("ALTER TABLE commissions ADD COLUMN assignment_snapshot_json LONGTEXT NULL AFTER reject_reason");
		$this->query("ALTER TABLE commissions ADD KEY idx_commissions_assigned (assigned)");
		$this->query("ALTER TABLE commissions ADD KEY idx_commissions_sim_assignment (sim_assignment_id)");
	}

	public function down()
	{
		$this->query("ALTER TABLE commissions DROP KEY idx_commissions_assigned");
		$this->query("ALTER TABLE commissions DROP KEY idx_commissions_sim_assignment");
		$this->query("ALTER TABLE commissions DROP COLUMN assignment_snapshot_json");
		$this->query("ALTER TABLE commissions DROP COLUMN reject_reason");
		$this->query("ALTER TABLE commissions DROP COLUMN proof_image");
		$this->query("ALTER TABLE commissions DROP COLUMN sim_assignment_id");
		$this->query("ALTER TABLE commissions DROP COLUMN assigned");
	}
}

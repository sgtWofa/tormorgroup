<?php if(user_can('view_momoremit')): ?>
<?php
$adminRoute = $admin_route ?? 'admin';
$pluginRoute = $plugin_route ?? 'airtimebundle';
$audit = is_array($denosAudit ?? null) ? $denosAudit : [];
$counts = $audit['counts'] ?? [];
$batches = $audit['batch_rows'] ?? [];
$transactions = $audit['transaction_rows'] ?? [];
$generatedAt = $audit['generated_at'] ?? null;
$files = $audit['files'] ?? [];
?>

<div class="container-fluid px-0">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <p class="text-uppercase text-muted mb-1 small fw-semibold">Airtimebundle maintenance</p>
                    <h2 class="mb-2">Denomination recovery audit</h2>
                    <p class="text-secondary mb-0">This report helps staff review historical airtimebundle transactions whose denomination rows were never linked safely.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-secondary" href="<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>">
                        <i class="fa fa-arrow-left me-2"></i>Back to list
                    </a>
                    <a class="btn btn-outline-dark" href="<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>/denos_audit_export/summary">
                        <i class="fa fa-file-lines me-2"></i>Summary
                    </a>
                    <a class="btn btn-outline-primary" href="<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>/denos_audit_export/batches">
                        <i class="fa fa-file-csv me-2"></i>Batches CSV
                    </a>
                    <a class="btn btn-outline-success" href="<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>/denos_audit_export/transactions">
                        <i class="fa fa-table-list me-2"></i>Transactions CSV
                    </a>
                </div>
            </div>
            <div class="alert alert-warning mt-3 mb-0">
                <strong>Conservative rule:</strong> batches marked <code>AMBIGUOUS_SHARED_BATCH</code> or <code>NO_SAFE_EVIDENCE</code> should not be auto-backfilled. They need human review.
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase text-muted small fw-semibold mb-2">Missing transactions</div>
                    <div class="display-6 fw-bold text-warning mb-1"><?=number_format((int)($counts['total_missing_transactions'] ?? 0))?></div>
                    <div class="text-secondary small">Transactions without linked denomination rows</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase text-muted small fw-semibold mb-2">Batches</div>
                    <div class="display-6 fw-bold text-primary mb-1"><?=number_format((int)($counts['total_batches'] ?? 0))?></div>
                    <div class="text-secondary small">Grouped by creator and timestamp</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase text-muted small fw-semibold mb-2">Ambiguous batches</div>
                    <div class="display-6 fw-bold text-danger mb-1"><?=number_format((int)($counts['ambiguous_shared_batch'] ?? 0))?></div>
                    <div class="text-secondary small">Shared-bucket history that cannot be auto-assigned safely</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-uppercase text-muted small fw-semibold mb-2">Generated</div>
                    <div class="fw-bold mb-1"><?=esc((string)$generatedAt)?></div>
                    <div class="text-secondary small">Latest backend snapshot</div>
                </div>
            </div>
        </div>
    </div>

    <?php if(!empty($files['output_dir'])): ?>
    <div class="alert alert-info small">
        Fresh copies were also written to <code><?=esc((string)$files['output_dir'])?></code> for offline review.
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-0 pt-4 px-4">
            <h4 class="mb-1">Batch review queue</h4>
            <p class="text-secondary mb-0">One row per timestamp batch. Start here when auditing old records.</p>
        </div>
        <div class="card-body px-4 pb-4">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Missing IDs</th>
                            <th>Counts</th>
                            <th>Historical buckets</th>
                            <th>Recommendation</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if(!empty($batches)): ?>
                        <?php foreach($batches as $batch): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?=esc((string)($batch['date_created'] ?? ''))?></div>
                                    <div class="text-secondary small">Created by #<?=esc((string)($batch['created_by'] ?? ''))?></div>
                                </td>
                                <td>
                                    <div class="small"><?=esc((string)($batch['missing_tx_ids'] ?? ''))?></div>
                                </td>
                                <td>
                                    <div class="small">Missing: <strong><?=esc((string)($batch['missing_tx_count'] ?? '0'))?></strong></div>
                                    <div class="small">All same second: <strong><?=esc((string)($batch['all_tx_count_same_timestamp'] ?? '0'))?></strong></div>
                                    <div class="small text-secondary">Amount paid total: GHS <?=esc((string)($batch['missing_amount_paid_total'] ?? '0.00'))?></div>
                                </td>
                                <td>
                                    <div class="small">Denos: <?=esc((string)($batch['denos_bucket_trans_ids'] ?? 'none'))?></div>
                                    <div class="small">Drawer: <?=esc((string)($batch['drawer_bucket_trans_ids'] ?? 'none'))?></div>
                                    <div class="small text-secondary"><?=esc((string)($batch['denos_breakdowns'] ?? $batch['drawer_breakdowns'] ?? ''))?></div>
                                </td>
                                <td>
                                    <?php $code = (string)($batch['recommendation_code'] ?? ''); ?>
                                    <span class="badge <?= $code === 'AMBIGUOUS_SHARED_BATCH' ? 'bg-danger' : ($code === 'NO_SAFE_EVIDENCE' ? 'bg-secondary' : 'bg-success') ?> mb-2"><?=esc($code)?></span>
                                    <div class="small"><?=esc((string)($batch['recommendation'] ?? ''))?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center text-secondary py-4">No missing batches found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-1">Transaction review list</h4>
                <p class="text-secondary mb-0">Per-transaction detail for staff case-by-case follow-up.</p>
            </div>
            <span class="badge bg-dark"><?=number_format(count($transactions))?> rows</span>
        </div>
        <div class="card-body px-4 pb-4">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Transaction</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Same timestamp batch</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if(!empty($transactions)): ?>
                        <?php foreach($transactions as $item): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold">#<?=esc((string)($item['transaction_id'] ?? ''))?></div>
                                    <div class="small text-secondary"><?=esc((string)($item['transaction_type'] ?? ''))?> / <?=esc((string)($item['category'] ?? ''))?> / <?=esc((string)($item['network'] ?? ''))?></div>
                                    <div class="small text-secondary"><?=esc((string)($item['date_created'] ?? ''))?></div>
                                </td>
                                <td>
                                    <div><?=esc((string)($item['phone'] ?? ''))?></div>
                                    <div class="small text-secondary">Receiver: <?=esc((string)($item['receiver'] ?? ''))?></div>
                                </td>
                                <td>
                                    <div>Amount: <strong>GHS <?=esc((string)($item['amount'] ?? '0.00'))?></strong></div>
                                    <div class="small text-secondary">Paid: GHS <?=esc((string)($item['amount_paid'] ?? '0.00'))?></div>
                                </td>
                                <td>
                                    <div class="small">All IDs: <?=esc((string)($item['all_tx_ids_same_timestamp'] ?? ''))?></div>
                                    <div class="small text-secondary">Linked IDs: <?=esc((string)($item['linked_tx_ids_same_timestamp'] ?? 'none'))?></div>
                                </td>
                                <td>
                                    <?php $code = (string)($item['recommendation_code'] ?? ''); ?>
                                    <span class="badge <?= $code === 'AMBIGUOUS_SHARED_BATCH' ? 'bg-danger' : ($code === 'NO_SAFE_EVIDENCE' ? 'bg-secondary' : 'bg-success') ?> mb-2"><?=esc($code)?></span>
                                    <div class="small"><?=esc((string)($item['recommendation'] ?? ''))?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center text-secondary py-4">No missing transactions found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php

/** @var object $training */
/** @var array $attendees */

// Split attendees into present / absent for clearer reporting
$present = [];
$absent  = [];
if (!empty($attendees) && is_array($attendees)) {
  foreach ($attendees as $row) {
    if (($row->status ?? '') === 'present') {
      $present[] = $row;
    } elseif (($row->status ?? '') === 'absent') {
      $absent[] = $row;
    }
  }
}

?>

<style>
  :root {
    --tr-surface: #ffffff;
    --tr-surface-2: #f9fafb;
    --tr-text: #111827;
    --tr-muted: #6b7280;
    --tr-border: rgba(15,23,42,0.08);
    --tr-shadow: 0 14px 34px rgba(15,23,42,0.12), 0 0 0 1px rgba(15,23,42,0.03);
  }

  html[data-bs-theme="dark"],
  body[data-bs-theme="dark"],
  .dark-theme {
    --tr-surface: #111827;
    --tr-surface-2: #0b1220;
    --tr-text: rgba(255,255,255,0.92);
    --tr-muted: rgba(255,255,255,0.68);
    --tr-border: rgba(148,163,184,0.20);
    --tr-shadow: 0 18px 52px rgba(0,0,0,0.55), 0 0 0 1px rgba(148,163,184,0.08);
  }

  .ops-dashboard-shell {
    margin-top: 1.5rem;
    margin-bottom: 2.5rem;
    padding: 1.75rem 1.5rem 2rem;
    border-radius: 1.25rem;
    background: var(--tr-surface);
    border: 1px solid var(--tr-border);
    box-shadow: var(--tr-shadow);
    position: relative;
    overflow: hidden;
  }

  .ops-dashboard-shell::before {
    content: "";
    position: absolute;
    inset: -40%;
    background:
      radial-gradient(circle at 0% 0%, rgba(255,193,7,0.12) 0, transparent 55%),
      radial-gradient(circle at 100% 100%, rgba(59,130,246,0.10) 0, transparent 55%);
    opacity: 1;
    filter: blur(4px);
    pointer-events: none;
    z-index: 0;
  }

  .ops-dashboard-shell > * {
    position: relative;
    z-index: 1;
  }

  .ops-section-title {
    font-size: .95rem;
    text-transform: uppercase;
    letter-spacing: .16em;
    color: var(--tr-muted);
  }

  .ops-section-title span {
    color: var(--tr-text);
  }
</style>

<?php
  $admin_route = get_value()['admin_route'] ?? 'admin';
  $baseUrl = ROOT . '/' . $admin_route . '/trainings';
?>

<div class="container-fluid">
  <div class="col-lg-11 col-md-12 mx-auto ops-dashboard-shell">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <div class="ops-section-title">TRAININGS <span>REPORT</span></div>
        <small class="text-muted">Performance and attendance summary for this training.</small>
        <div class="mt-1 small text-muted">Session: <strong><?php echo esc($training->title); ?></strong></div>
      </div>
      <a href="<?php echo $baseUrl; ?>" class="btn btn-outline-secondary">
        <i class="fa fa-arrow-left me-1"></i> Back to list
      </a>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-md-3">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted text-uppercase small">Total attendees</div>
            <div class="fs-4 fw-bold"><?php echo count($attendees); ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted text-uppercase small">Present</div>
            <div class="fs-4 fw-bold text-success"><?php echo count($present); ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted text-uppercase small">Absent</div>
            <div class="fs-4 fw-bold text-danger"><?php echo count($absent); ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted text-uppercase small">Capacity</div>
            <div class="fs-4 fw-bold"><?php echo (int)($training->max_capacity ?? 0); ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted text-uppercase small">Rating</div>
            <div class="fs-4 fw-bold"><?php echo $training->score_rating ? number_format($training->score_rating, 1) : '—'; ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted text-uppercase small">Cost</div>
            <div class="fs-4 fw-bold"><?php echo $training->cost ? number_format($training->cost, 2) : '—'; ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <h6 class="text-muted text-uppercase small mb-3">Attendee breakdown</h6>

        <div class="row g-3">
          <div class="col-md-6">
            <h6 class="small text-muted text-uppercase mb-2">Present</h6>
            <?php if (!empty($present)): ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>User</th>
                      <th>Notes</th>
                      <th>Date</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php foreach ($present as $row): ?>
                    <tr>
                      <td><?php echo (int)$row->id; ?></td>
                      <td>
                        <?php
                          $fullName = trim((string)($row->first_name ?? '') . ' ' . (string)($row->last_name ?? ''));
                          if ($fullName !== '') {
                            echo esc($fullName);
                          } elseif (!empty($row->email)) {
                            echo esc($row->email);
                          } else {
                            echo (int)($row->user_id ?? 0);
                          }
                        ?>
                      </td>
                      <td><?php echo !empty($row->notes) ? esc($row->notes) : '—'; ?></td>
                      <td><?php echo !empty($row->date_created) ? get_date_time($row->date_created) : '—'; ?></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="alert alert-light mb-0">No attendees marked present.</div>
            <?php endif; ?>
          </div>

          <div class="col-md-6">
            <h6 class="small text-muted text-uppercase mb-2">Absent</h6>
            <?php if (!empty($absent)): ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>User</th>
                      <th>Reason</th>
                      <th>Date</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php foreach ($absent as $row): ?>
                    <tr>
                      <td><?php echo (int)$row->id; ?></td>
                      <td>
                        <?php
                          $fullName = trim((string)($row->first_name ?? '') . ' ' . (string)($row->last_name ?? ''));
                          if ($fullName !== '') {
                            echo esc($fullName);
                          } elseif (!empty($row->email)) {
                            echo esc($row->email);
                          } else {
                            echo (int)($row->user_id ?? 0);
                          }
                        ?>
                      </td>
                      <td><?php echo !empty($row->notes) ? esc($row->notes) : '—'; ?></td>
                      <td><?php echo !empty($row->date_created) ? get_date_time($row->date_created) : '—'; ?></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="alert alert-light mb-0">No attendees marked absent.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <h6 class="text-muted text-uppercase small mb-2">Meeting report / key points</h6>
        <?php if (!empty($training->notes)): ?>
          <div class="border rounded p-3 bg-body-tertiary small" style="white-space: pre-wrap;">
            <?php echo nl2br(esc($training->notes)); ?>
          </div>
        <?php else: ?>
          <div class="alert alert-light mb-0 small">No key points recorded yet. Add them from the training edit screen.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

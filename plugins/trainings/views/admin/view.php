<?php

/** @var object $training */

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

  .ops-section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    margin-bottom: 1.25rem;
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
  <div class="col-lg-10 col-md-12 mx-auto ops-dashboard-shell">
    <div class="ops-section-heading">
      <div>
        <div class="ops-section-title">TRAININGS <span>DETAIL</span></div>
        <small class="text-muted">Full view of this training session.</small>
      </div>
      <div class="d-flex gap-2">
        <a href="<?php echo $baseUrl; ?>" class="btn btn-outline-secondary">
          <i class="fa fa-arrow-left me-1"></i> Back to list
        </a>
        <a href="<?php echo $baseUrl . '/edit/' . (int)$training->id; ?>" class="btn btn-primary">
          <i class="fa fa-edit me-1"></i> Edit
        </a>
      </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <h4 class="mb-1"><?php echo esc($training->title); ?></h4>
        <div class="mb-2">
          <span class="badge bg-secondary me-1"><?php echo esc($training->type ?: 'General'); ?></span>
          <?php if (!empty($training->target_team)): ?>
            <span class="badge bg-dark"><?php echo esc($training->target_team); ?></span>
          <?php endif; ?>
        </div>

        <div class="row small text-muted">
          <div class="col-md-6">
            <div><strong>Status:</strong> <?php echo esc($training->status); ?></div>
            <div><strong>Mode:</strong> <?php echo $training->mode === 'online' ? 'Online' : 'In-person'; ?></div>
            <?php if (!empty($training->trainer_name)): ?>
              <div><strong>Trainer:</strong> <?php echo esc($training->trainer_name); ?></div>
            <?php endif; ?>
            <?php if (!empty($training->location)): ?>
              <div><strong>Location / Link:</strong> <?php echo esc($training->location); ?></div>
            <?php endif; ?>
          </div>
          <div class="col-md-6">
            <div><strong>Start:</strong> <?php echo $training->date_start ? get_date_time($training->date_start) : '—'; ?></div>
            <div><strong>End:</strong> <?php echo $training->date_end ? get_date_time($training->date_end) : '—'; ?></div>
            <div><strong>Duration:</strong> <?php echo $training->duration_minutes ? (int)$training->duration_minutes . ' min' : '—'; ?></div>
            <div><strong>Capacity:</strong> <?php echo (int)($training->attendees_count ?? 0); ?><?php if (!empty($training->max_capacity)) echo ' / ' . (int)$training->max_capacity; ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <h6 class="text-muted text-uppercase small mb-2">Performance</h6>
            <p class="mb-1"><strong>Score / Rating:</strong> <?php echo $training->score_rating ? number_format($training->score_rating, 1) . ' / 10' : '—'; ?></p>
            <p class="mb-1"><strong>Cost:</strong> <?php echo $training->cost ? number_format($training->cost, 2) : '—'; ?></p>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <h6 class="text-muted text-uppercase small mb-2">Materials</h6>
            <p class="mb-1"><strong>Attachments:</strong> <?php echo $training->attachments ? esc($training->attachments) : '—'; ?></p>
          </div>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm mt-3">
      <div class="card-body">
        <h6 class="text-muted text-uppercase small mb-2">Notes</h6>
        <p class="mb-0"><?php echo $training->notes ? nl2br(esc($training->notes)) : 'No notes captured.'; ?></p>
      </div>
    </div>
  </div>
</div>

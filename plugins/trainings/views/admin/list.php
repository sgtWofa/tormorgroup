<?php

/** @var array $trainings */
/** @var object $kpi */

$period   = $_GET['period'] ?? 'this_month';
$status   = $_GET['status'] ?? '';
$type     = $_GET['type'] ?? '';
$mode     = $_GET['mode'] ?? '';
$q        = $_GET['q'] ?? '';

?>

<style>
  :root {
    --tr-surface: #ffffff;
    --tr-surface-2: #f9fafb;
    --tr-text: #111827;
    --tr-muted: #6b7280;
    --tr-border: rgba(15,23,42,0.08);
    --tr-shadow: 0 14px 34px rgba(15,23,42,0.12), 0 0 0 1px rgba(15,23,42,0.03);
    --tr-card-shadow: 0 10px 22px rgba(15,23,42,0.10);
    --tr-overlay-backdrop: rgba(17,24,39,0.28);
    --tr-overlay-backdrop-blur: 6px;
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
    --tr-card-shadow: 0 16px 44px rgba(0,0,0,0.45);
    --tr-overlay-backdrop: rgba(0,0,0,0.55);
    --tr-overlay-backdrop-blur: 6px;
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
      radial-gradient(circle at 0% 0%, rgba(255,193,7,0.14) 0, transparent 55%),
      radial-gradient(circle at 100% 100%, rgba(59,130,246,0.10) 0, transparent 55%);
    opacity: 1;
    filter: blur(4px);
    pointer-events: none;
    z-index: 0;
  }

  html[data-bs-theme="dark"] .ops-dashboard-shell::before,
  body[data-bs-theme="dark"] .ops-dashboard-shell::before,
  .dark-theme .ops-dashboard-shell::before {
    background:
      radial-gradient(circle at 0% 0%, rgba(255,193,7,0.12) 0, transparent 55%),
      radial-gradient(circle at 100% 100%, rgba(59,130,246,0.08) 0, transparent 55%);
    opacity: 0.55;
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

  /* Custom full-screen overlay modal for trainings */
  .trainings-overlay {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
  }

  .trainings-overlay.is-open {
    display: flex;
  }

  .trainings-overlay-backdrop {
    position: absolute;
    inset: 0;
    background: var(--tr-overlay-backdrop);
    backdrop-filter: blur(var(--tr-overlay-backdrop-blur));
  }

  .trainings-overlay-dialog {
    position: relative;
    z-index: 1;
    max-width: 960px;
    width: 100%;
    max-height: 90vh;
    padding: 0 1rem;
  }

  .trainings-overlay-card {
    background: var(--tr-surface);
    border-radius: 1.25rem;
    box-shadow: var(--tr-shadow);
    border: 1px solid var(--tr-border);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    max-height: 90vh;
    z-index: 1;
  }

  .trainings-overlay-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .9rem 1.3rem;
    border-bottom: 1px solid var(--tr-border);
    background: linear-gradient(90deg, var(--tr-surface), var(--tr-surface-2));
  }

  .trainings-overlay-title {
    font-size: .9rem;
    text-transform: uppercase;
    letter-spacing: .18em;
    color: var(--tr-text);
  }

  .trainings-overlay-close {
    border: 0;
    background: transparent;
    color: var(--tr-muted);
    font-size: 1.1rem;
    line-height: 1;
  }

  .trainings-overlay-close:hover {
    color: var(--tr-text);
  }

  .trainings-overlay-body {
    padding: 1rem 1.3rem 1.25rem;
    overflow: auto;
    background: linear-gradient(180deg, var(--tr-surface-2) 0%, var(--tr-surface) 55%);
  }

  .ops-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4,minmax(0,1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
  }

  .ops-kpi-card {
    border-radius: 1rem;
    padding: 1rem 1.1rem;
    background: var(--tr-surface);
    box-shadow: var(--tr-card-shadow);
    border: 1px solid var(--tr-border);
    color: var(--tr-text);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    position: relative;
    overflow: hidden;
  }

  .ops-kpi-label {
    font-size: .78rem;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: var(--tr-muted);
    margin-bottom: .1rem;
  }

  .ops-kpi-value {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--tr-text);
  }

  .ops-kpi-sub {
    font-size: .78rem;
    color: var(--tr-muted);
  }

  @media (max-width: 992px) {
    .ops-dashboard-shell {
      padding: 1.25rem 1rem 1.75rem;
    }
    .ops-kpi-grid {
      grid-template-columns: repeat(2,minmax(0,1fr));
    }
  }

  @media (max-width: 576px) {
    .ops-kpi-grid {
      grid-template-columns: minmax(0,1fr);
    }
  }

  /* Trainings cards: hover + lazy-reveal */
  .training-card {
    transition: transform 0.18s ease-out, box-shadow 0.2s ease-out, border-color 0.2s ease-out, opacity 0.3s ease-out;
    transform: translateY(6px);
    opacity: 0;
    border-radius: 1rem;
  }

  .training-card.is-visible {
    transform: translateY(0);
    opacity: 1;
  }

  .training-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 40px rgba(15,23,42,0.45);
    border-color: rgba(255,193,7,0.65);
  }
</style>

<div class="container-fluid">
  <div class="col-lg-11 col-md-12 mx-auto ops-dashboard-shell">
    <div class="ops-section-heading">
      <div>
        <div class="ops-section-title">TRAININGS <span>OVERVIEW</span></div>

<script>
  (function(){
    var $ = window.jQuery || null;

    // Build a top-level overlay and append directly to <body>
    var overlay = document.createElement('div');
    overlay.id = 'trainingsOverlay';
    overlay.className = 'trainings-overlay';
    overlay.setAttribute('aria-hidden', 'true');
    overlay.innerHTML = '' +
      '<div class="trainings-overlay-backdrop"></div>' +
      '<div class="trainings-overlay-dialog">' +
      '  <div class="trainings-overlay-card">' +
      '    <div class="trainings-overlay-header">' +
      '      <div class="trainings-overlay-title" id="trainingsOverlayTitle">TRAINING</div>' +
      '      <button type="button" class="trainings-overlay-close" id="trainingsOverlayClose" aria-label="Close">&times;</button>' +
      '    </div>' +
      '    <div class="trainings-overlay-body" id="trainingsOverlayBody">' +
      '      <div class="text-center text-muted small">Loading...</div>' +
      '    </div>' +
      '  </div>' +
      '</div>';

    document.body.appendChild(overlay);

    var bodyEl  = document.getElementById('trainingsOverlayBody');
    var titleEl = document.getElementById('trainingsOverlayTitle');
    var closeBtn = document.getElementById('trainingsOverlayClose');

    function extractShellFromHtml(html) {
      try {
        var wrapper = document.createElement('div');
        wrapper.innerHTML = html;

        var shell = wrapper.querySelector('.ops-dashboard-shell');
        if (shell) {
          return shell.innerHTML;
        }

        var mainContainer = wrapper.querySelector('.container-fluid');
        if (mainContainer) {
          return mainContainer.innerHTML;
        }

        return html;
      } catch (e) {
        return html;
      }
    }

    function openOverlay(url, title) {
      if (titleEl) {
        titleEl.textContent = (title || 'Training').toUpperCase();
      }
      bodyEl.innerHTML = '<div class="text-center text-muted small">Loading...</div>';

      if ($) {
        $.get(url, function(html){
          var inner = extractShellFromHtml(html);
          bodyEl.innerHTML = inner;
        });
      } else {
        window.location.href = url;
        return;
      }

      overlay.classList.add('is-open');
      document.body.classList.add('modal-open');
    }

    function closeOverlay() {
      overlay.classList.remove('is-open');
      document.body.classList.remove('modal-open');
    }

    document.addEventListener('click', function(e){
      var target = e.target.closest('.training-modal-link');
      if (target) {
        e.preventDefault();
        var url = target.getAttribute('href');
        var title = target.textContent.trim() || 'Training';
        if (!url) return;
        openOverlay(url, title);
        return;
      }

      if (e.target === overlay || e.target.classList.contains('trainings-overlay-backdrop')) {
        closeOverlay();
      }
    });

    if (closeBtn) {
      closeBtn.addEventListener('click', function(){
        closeOverlay();
      });
    }

    document.addEventListener('keyup', function(e){
      if (e.key === 'Escape' && overlay.classList.contains('is-open')) {
        closeOverlay();
      }
    });
  })();
</script>
        <small class="text-muted">Plan, track, and review all training sessions for your team.</small>
      </div>
      <a href="<?php echo isset($trainings_add_url) ? $trainings_add_url : ROOT . '/' . (get_value()['admin_route'] ?? 'admin') . '/trainings/add'; ?>" class="btn btn-outline-warning">
        <i class="fa fa-plus me-1"></i> New Training
      </a>
    </div>

    <!-- KPI Strip -->
    <div class="ops-kpi-grid mb-4">
      <div class="ops-kpi-card">
        <div class="ops-kpi-label">Total Trainings</div>
        <div class="ops-kpi-value"><?php echo number_format($kpi->total_trainings); ?></div>
        <div class="ops-kpi-sub">Sessions captured in the period.</div>
      </div>
      <div class="ops-kpi-card">
        <div class="ops-kpi-label">Upcoming</div>
        <div class="ops-kpi-value"><?php echo number_format($kpi->upcoming); ?></div>
        <div class="ops-kpi-sub">Scheduled from today onwards.</div>
      </div>
      <div class="ops-kpi-card">
        <div class="ops-kpi-label">Total Attendees</div>
        <div class="ops-kpi-value"><?php echo number_format($kpi->total_attendees); ?></div>
        <div class="ops-kpi-sub">Aggregate headcount across sessions.</div>
      </div>
      <div class="ops-kpi-card">
        <div class="ops-kpi-label">Avg Score / Rating</div>
        <div class="ops-kpi-value"><?php echo $kpi->avg_score ? number_format($kpi->avg_score, 1) : '—'; ?></div>
        <div class="ops-kpi-sub">Self-reported effectiveness (0–10).</div>
      </div>
    </div>

  <!-- Filters + Search -->
  <form method="get" class="card shadow-sm border-0 mb-4 p-3">
    <div class="row g-3 align-items-end">
      <div class="col-md-3 col-sm-6">
        <label class="form-label">Period</label>
        <select name="period" class="form-select" onchange="this.form.submit()">
          <option value="this_month" <?php echo $period === 'this_month' ? 'selected' : ''; ?>>This month</option>
          <option value="last_3_months" <?php echo $period === 'last_3_months' ? 'selected' : ''; ?>>Last 3 months</option>
          <option value="this_year" <?php echo $period === 'this_year' ? 'selected' : ''; ?>>This year</option>
          <option value="all" <?php echo $period === 'all' ? 'selected' : ''; ?>>All time</option>
        </select>
      </div>
      <div class="col-md-2 col-sm-6">
        <label class="form-label">Status</label>
        <select name="status" class="form-select" onchange="this.form.submit()">
          <option value="">All</option>
          <option value="planned" <?php echo $status === 'planned' ? 'selected' : ''; ?>>Planned</option>
          <option value="ongoing" <?php echo $status === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
          <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
          <option value="archived" <?php echo $status === 'archived' ? 'selected' : ''; ?>>Archived</option>
        </select>
      </div>
      <div class="col-md-2 col-sm-6">
        <label class="form-label">Type</label>
        <select name="type" class="form-select" onchange="this.form.submit()">
          <option value="">All</option>
          <option value="Product" <?php echo $type === 'Product' ? 'selected' : ''; ?>>Product</option>
          <option value="Compliance" <?php echo $type === 'Compliance' ? 'selected' : ''; ?>>Compliance</option>
          <option value="Soft skills" <?php echo $type === 'Soft skills' ? 'selected' : ''; ?>>Soft skills</option>
        </select>
      </div>
      <div class="col-md-2 col-sm-6">
        <label class="form-label">Mode</label>
        <select name="mode" class="form-select" onchange="this.form.submit()">
          <option value="">All</option>
          <option value="in_person" <?php echo $mode === 'in_person' ? 'selected' : ''; ?>>In-person</option>
          <option value="online" <?php echo $mode === 'online' ? 'selected' : ''; ?>>Online</option>
        </select>
      </div>
      <div class="col-md-3 col-sm-12">
        <label class="form-label">Search</label>
        <div class="input-group">
          <span class="input-group-text"><i class="fa fa-search"></i></span>
          <input type="text" name="q" class="form-control" placeholder="Title, trainer, team" value="<?php echo htmlspecialchars($q); ?>">
        </div>
      </div>
    </div>
  </form>

    <!-- Trainings List -->
    <?php if (!empty($trainings)): ?>
    <div class="row g-3">
      <?php foreach ($trainings as $t): ?>
        <?php
          $statusClass = 'bg-secondary';
          if ($t->status === 'planned')   $statusClass = 'bg-warning text-dark';
          if ($t->status === 'ongoing')   $statusClass = 'bg-info';
          if ($t->status === 'completed') $statusClass = 'bg-success';
          if ($t->status === 'archived')  $statusClass = 'bg-dark';

          $dateLabel = $t->date_start ? date('M j, Y H:i', strtotime($t->date_start)) : 'No date set';
          $duration  = $t->duration_minutes ? $t->duration_minutes . ' min' : '';
        ?>
        <div class="col-12">
          <div class="card shadow-sm border-0 h-100 training-card">
            <div class="card-body d-flex flex-column">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                  <h5 class="card-title mb-1"><?php echo htmlspecialchars($t->title); ?></h5>
                  <span class="badge bg-secondary text-white me-1"><?php echo htmlspecialchars($t->type ?: 'General'); ?></span>
                  <?php if (!empty($t->target_team)): ?>
                    <span class="badge bg-secondary text-white"><?php echo htmlspecialchars($t->target_team); ?></span>
                  <?php endif; ?>
                </div>
                <span class="badge <?php echo $statusClass; ?> text-white text-uppercase small"><?php echo htmlspecialchars($t->status); ?></span>
              </div>

              <div class="mb-2 small text-muted">
                <div><i class="fa fa-calendar me-1"></i><?php echo $dateLabel; ?></div>
                <?php if ($duration): ?>
                  <div><i class="fa fa-clock me-1"></i><?php echo $duration; ?></div>
                <?php endif; ?>
                <?php if (!empty($t->trainer_name)): ?>
                  <div><i class="fa fa-user-tie me-1"></i><?php echo htmlspecialchars($t->trainer_name); ?></div>
                <?php endif; ?>
                <?php if (!empty($t->mode)): ?>
                  <div><i class="fa fa-globe me-1"></i><?php echo $t->mode === 'online' ? 'Online' : 'In-person'; ?></div>
                <?php endif; ?>
                <?php if (!empty($t->location)): ?>
                  <div><i class="fa fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($t->location); ?></div>
                <?php endif; ?>
              </div>

              <div class="mt-auto d-flex justify-content-between align-items-center pt-2 border-top">
                <div class="small text-muted">
                  <i class="fa fa-users me-1"></i><?php echo (int)$t->attendees_count; ?> attendees
                  <?php if (!empty($t->max_capacity)): ?>
                    <span class="text-muted"> / <?php echo (int)$t->max_capacity; ?></span>
                  <?php endif; ?>
                  <?php if (!empty($t->score_rating)): ?>
                    <br><i class="fa fa-star text-warning me-1"></i><?php echo number_format($t->score_rating, 1); ?> rating
                  <?php endif; ?>
                </div>
                <div class="btn-group btn-group-sm" role="group">
                  <?php $base = ROOT . '/' . (get_value()['admin_route'] ?? 'admin') . '/trainings'; ?>
                  <a href="<?php echo $base . '/view/' . (int)$t->id; ?>" class="btn btn-outline-secondary training-modal-link" data-modal-size="lg">View</a>
                  <a href="<?php echo $base . '/edit/' . (int)$t->id; ?>" class="btn btn-outline-secondary training-modal-link" data-modal-size="lg">Edit</a>
                  <a href="<?php echo $base . '/attendance/' . (int)$t->id; ?>" class="btn btn-outline-secondary">Attendance</a>
                  <a href="<?php echo $base . '/report/' . (int)$t->id; ?>" class="btn btn-outline-secondary training-modal-link" data-modal-size="lg">Report</a>
                  <a href="<?php echo $base . '/delete/' . (int)$t->id; ?>" class="btn btn-outline-danger">Delete</a>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
      <div class="alert alert-info">No trainings found for the selected filters.</div>
    <?php endif; ?>
  </div>
</div>

<script>
  // Lazy-reveal trainings cards using IntersectionObserver
  document.addEventListener('DOMContentLoaded', function () {
    var cards = Array.prototype.slice.call(document.querySelectorAll('.training-card'));
    if (!cards.length) return;

    if (!('IntersectionObserver' in window)) {
      cards.forEach(function (card) { card.classList.add('is-visible'); });
      return;
    }

    var observer = new IntersectionObserver(function (entries, obs) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          obs.unobserve(entry.target);
        }
      });
    }, {
      threshold: 0.18
    });

    cards.forEach(function (card) { observer.observe(card); });
  });
</script>

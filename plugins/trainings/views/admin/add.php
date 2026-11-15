<?php

/** @var object $kpi (optional) */

$period   = $_GET['period'] ?? 'this_month';
$status   = $_GET['status'] ?? '';
$type     = $_GET['type'] ?? '';
$mode     = $_GET['mode'] ?? '';
$q        = $_GET['q'] ?? '';

?>

<style>
  .ops-dashboard-shell {
    margin-top: 1.5rem;
    margin-bottom: 2.5rem;
    padding: 1.75rem 1.5rem 2rem;
    border-radius: 1.25rem;
    box-shadow:
      0 16px 45px rgba(15,23,42,0.75),
      0 0 0 1px rgba(108,117,125,0.5);
    position: relative;
    overflow: hidden;
  }

  .ops-dashboard-shell::before {
    content: "";
    position: absolute;
    inset: -40%;
    background:
      radial-gradient(circle at 0% 0%, rgba(129,140,248,0.28) 0, transparent 55%),
      radial-gradient(circle at 100% 100%, rgba(248,250,252,0.06) 0, transparent 55%);
    opacity: 0.45;
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
    color: #adb5bd;
  }

  .ops-section-title span {
    color: #f8f9fa;
  }
</style>

<div class="container-fluid">
  <div class="col-lg-10 col-md-12 mx-auto ops-dashboard-shell">
    <div class="ops-section-heading">
      <div>
        <div class="ops-section-title">TRAININGS <span>NEW SESSION</span></div>
        <small class="text-muted">Capture a new training session for your operations team.</small>
      </div>
      <a href="<?php echo ROOT . '/' . (get_value()['admin_route'] ?? 'admin') . '/trainings'; ?>" class="btn btn-outline-secondary">
        <i class="fa fa-arrow-left me-1"></i> Back to list
      </a>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <form method="post">
          <?php echo csrf(); ?>
          <input type="hidden" name="id" value="">

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Title</label>
              <input type="text" name="title" class="form-control" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Type</label>
              <select name="type" class="form-select">
                <option value="Product">Product</option>
                <option value="Compliance">Compliance</option>
                <option value="Soft skills">Soft skills</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                <option value="planned">Planned</option>
                <option value="ongoing">Ongoing</option>
                <option value="completed">Completed</option>
                <option value="archived">Archived</option>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Start date & time</label>
              <input type="datetime-local" name="date_start" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label">End date & time</label>
              <input type="datetime-local" name="date_end" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label">Duration (minutes)</label>
              <input type="number" name="duration_minutes" class="form-control" min="0" step="5">
            </div>

            <div class="col-md-4">
              <label class="form-label">Trainer</label>
              <input type="text" name="trainer_name" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label">Mode</label>
              <select name="mode" class="form-select">
                <option value="in_person">In-person</option>
                <option value="online">Online</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Location / Link</label>
              <input type="text" name="location" class="form-control">
            </div>

            <div class="col-md-4">
              <label class="form-label">Target team</label>
              <input type="text" name="target_team" class="form-control" placeholder="SIMs, AgencyBanking, ...">
            </div>
            <div class="col-md-4">
              <label class="form-label">Max capacity</label>
              <input type="number" name="max_capacity" class="form-control" min="0">
            </div>
            <div class="col-md-4">
              <label class="form-label">Attendees (current)</label>
              <input type="number" name="attendees_count" class="form-control" min="0">
            </div>

            <div class="col-md-4">
              <label class="form-label">Cost</label>
              <input type="number" name="cost" class="form-control" min="0" step="0.01">
            </div>
            <div class="col-md-4">
              <label class="form-label">Score / Rating</label>
              <input type="number" name="score_rating" class="form-control" min="0" max="10" step="0.1">
            </div>
            <div class="col-md-4">
              <label class="form-label">Attachments (links)</label>
              <input type="text" name="attachments" class="form-control" placeholder="URLs, comma-separated">
            </div>

            <div class="col-12">
              <label class="form-label">Notes</label>
              <textarea name="notes" class="form-control" rows="3"></textarea>
            </div>
          </div>

          <div class="mt-4 d-flex justify-content-end gap-2">
            <a href="<?php echo ROOT . '/' . (get_value()['admin_route'] ?? 'admin') . '/trainings'; ?>" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Training</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

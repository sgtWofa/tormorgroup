<?php

/** @var object $training */
/** @var array $attendees */
/** @var array $users */

// Build a map of user_id => attendee row
$attendeeMap = [];
if (!empty($attendees) && is_array($attendees)) {
    foreach ($attendees as $row) {
        if (!empty($row->user_id)) {
            $attendeeMap[$row->user_id] = $row;
        }
    }
}

// Split users into unmarked / present / absent
$unmarked = [];
$present  = [];
$absent   = [];

foreach ($users as $u) {
    $row = $attendeeMap[$u->id] ?? null;
    if (!$row || $row->status === 'invited' || $row->status === '' || $row->status === null) {
        $unmarked[] = $u;
    } elseif ($row->status === 'present') {
        $present[] = $u;
    } elseif ($row->status === 'absent') {
        $absent[] = $u;
    } else {
        // Any other status treat as unmarked
        $unmarked[] = $u;
    }
}

$admin_route = get_value()['admin_route'] ?? 'admin';
$baseUrl = ROOT . '/' . $admin_route . '/trainings';
$isPartial = isset($_GET['partial']) && $_GET['partial'] == '1';

// Generate a single CSRF field to be reused in all attendance forms (dedicated key)
$csrfField = csrf('attendance_csrf');

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

  .ops-section-title {
    font-size: .95rem;
    text-transform: uppercase;
    letter-spacing: .16em;
    color: #adb5bd;
  }

  .ops-section-title span {
    color: #f8f9fa;
  }
  .attendance-search-input {
    max-width: 260px;
  }
</style>

<?php if (!$isPartial): ?>
<div class="container-fluid">
  <div class="col-lg-11 col-md-12 mx-auto ops-dashboard-shell">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <div class="ops-section-title">TRAININGS <span>ATTENDANCE</span></div>
        <small class="text-muted">Mark present or absent for each user in this training.</small>
        <div class="mt-1 small text-muted">Session: <strong><?php echo esc($training->title); ?></strong></div>
      </div>
      <a href="<?php echo $baseUrl; ?>" class="btn btn-outline-secondary">
        <i class="fa fa-arrow-left me-1"></i> Back to list
      </a>
    </div>

    <div class="d-flex justify-content-end mb-2">
      <div class="input-group input-group-sm attendance-search-input">
        <span class="input-group-text"><i class="fa fa-search"></i></span>
        <input type="text" id="attendanceSearch" class="form-control" placeholder="Search user...">
      </div>
    </div>
<?php endif; ?>

    <div id="attendance-inner">

    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted text-uppercase small">Unmarked</div>
            <div class="fs-5 fw-bold"><?php echo count($unmarked); ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted text-uppercase small">Present</div>
            <div class="fs-5 fw-bold text-success"><?php echo count($present); ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted text-uppercase small">Absent</div>
            <div class="fs-5 fw-bold text-danger"><?php echo count($absent); ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <h6 class="text-muted text-uppercase small mb-2">Unmarked users</h6>
            <?php if (!empty($unmarked)): ?>
              <div class="table-responsive" style="max-height:420px;overflow:auto;">
                <table class="table table-sm align-middle mb-0">
                  <tbody>
                  <?php foreach ($unmarked as $u): ?>
                    <tr data-attendee-row="1">
                      <td>
                        <div class="fw-semibold"><?php echo esc(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')); ?></div>
                        <div class="small text-muted"><?php echo esc($u->email ?? ''); ?></div>
                      </td>
                      <td class="text-end">
                        <form method="post" class="d-inline attendance-action-form">
                          <?php echo $csrfField; ?>
                          <input type="hidden" name="_action" value="mark">
                          <input type="hidden" name="user_id" value="<?php echo (int)$u->id; ?>">
                          <input type="hidden" name="status" value="present">
                          <button type="submit" class="btn btn-sm btn-success">Present</button>
                        </form>
                        <form method="post" class="d-inline ms-1 attendance-action-form">
                          <?php echo $csrfField; ?>
                          <input type="hidden" name="_action" value="mark">
                          <input type="hidden" name="user_id" value="<?php echo (int)$u->id; ?>">
                          <input type="hidden" name="status" value="absent">
                          <input type="hidden" name="notes" value="">
                          <button type="submit" class="btn btn-sm btn-outline-danger">Absent</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="alert alert-light mb-0">Everyone is marked.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <h6 class="text-muted text-uppercase small mb-2">Present</h6>
            <?php if (!empty($present)): ?>
              <div class="table-responsive" style="max-height:420px;overflow:auto;">
                <table class="table table-sm align-middle mb-0">
                  <tbody>
                  <?php foreach ($present as $u):
                    $row = $attendeeMap[$u->id] ?? null;
                  ?>
                    <tr data-attendee-row="1">
                      <td>
                        <div class="fw-semibold"><?php echo esc(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')); ?></div>
                        <div class="small text-muted"><?php echo esc($u->email ?? ''); ?></div>
                      </td>
                      <td class="text-end">
                        <form method="post" class="d-inline attendance-action-form">
                          <?php echo $csrfField; ?>
                          <input type="hidden" name="_action" value="mark">
                          <input type="hidden" name="user_id" value="<?php echo (int)$u->id; ?>">
                          <input type="hidden" name="status" value="absent">
                          <input type="text" name="notes" class="form-control form-control-sm mb-1" placeholder="Reason" style="width:160px;display:inline-block;">
                          <button type="submit" class="btn btn-sm btn-outline-danger">Mark absent</button>
                        </form>
                        <form method="post" class="d-inline ms-1 attendance-action-form">
                          <?php echo $csrfField; ?>
                          <input type="hidden" name="_action" value="clear">
                          <input type="hidden" name="user_id" value="<?php echo (int)$u->id; ?>">
                          <button type="submit" class="btn btn-sm btn-outline-secondary">Clear</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="alert alert-light mb-0">No one marked present yet.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <h6 class="text-muted text-uppercase small mb-2">Absent</h6>
            <?php if (!empty($absent)): ?>
              <div class="table-responsive" style="max-height:420px;overflow:auto;">
                <table class="table table-sm align-middle mb-0">
                  <tbody>
                  <?php foreach ($absent as $u):
                    $row = $attendeeMap[$u->id] ?? null;
                  ?>
                    <tr data-attendee-row="1">
                      <td>
                        <div class="fw-semibold"><?php echo esc(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')); ?></div>
                        <div class="small text-muted"><?php echo esc($u->email ?? ''); ?></div>
                        <?php if (!empty($row->notes)): ?>
                          <div class="small text-danger">Reason: <?php echo esc($row->notes); ?></div>
                        <?php endif; ?>
                      </td>
                      <td class="text-end">
                        <!-- Update reason while keeping status absent -->
                        <form method="post" class="attendance-action-form mb-1">
                          <?php echo $csrfField; ?>
                          <input type="hidden" name="_action" value="mark">
                          <input type="hidden" name="user_id" value="<?php echo (int)$u->id; ?>">
                          <input type="hidden" name="status" value="absent">
                          <div class="input-group input-group-sm" style="max-width:210px;">
                            <input type="text" name="notes" class="form-control" placeholder="Reason" value="<?php echo !empty($row->notes) ? esc($row->notes) : ''; ?>">
                            <button type="submit" class="btn btn-outline-danger">Save</button>
                          </div>
                        </form>

                        <!-- Change status for absent users -->
                        <form method="post" class="d-inline attendance-action-form">
                          <?php echo $csrfField; ?>
                          <input type="hidden" name="_action" value="mark">
                          <input type="hidden" name="user_id" value="<?php echo (int)$u->id; ?>">
                          <input type="hidden" name="status" value="present">
                          <button type="submit" class="btn btn-sm btn-success">Mark present</button>
                        </form>
                        <form method="post" class="d-inline ms-1 attendance-action-form">
                          <?php echo $csrfField; ?>
                          <input type="hidden" name="_action" value="clear">
                          <input type="hidden" name="user_id" value="<?php echo (int)$u->id; ?>">
                          <button type="submit" class="btn btn-sm btn-outline-secondary">Clear</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="alert alert-light mb-0">No one marked absent yet.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    </div> <!-- #attendance-inner -->

<?php if (!$isPartial): ?>

<script>
  (function(){
    var $ = window.jQuery || null;
    if (!$) return;
    // Submit attendance forms via AJAX and then reload the page (no HTML injection)
    $(document).on('submit', '.attendance-action-form', function(e){
      e.preventDefault();
      var $form = $(this);
      var data = $form.serialize();

      $.ajax({
        url: window.location.href,
        method: 'POST',
        data: data,
        success: function(){
          window.location.reload();
        },
        error: function(){
          alert('Unable to update attendance. Please refresh and try again.');
        }
      });
    });

    // Simple client-side search across all tables
    $('#attendanceSearch').on('input', function(){
      var q = $(this).val().toLowerCase();
      if (!q) {
        $('[data-attendee-row]').show();
        return;
      }
      $('[data-attendee-row]').each(function(){
        var text = $(this).text().toLowerCase();
        $(this).toggle(text.indexOf(q) !== -1);
      });
    });
  })();
</script>
<?php endif; ?>

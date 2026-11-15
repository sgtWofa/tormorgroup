<?php

/** @var object $training */

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
</style>

<?php
  $admin_route = get_value()['admin_route'] ?? 'admin';
  $baseUrl = ROOT . '/' . $admin_route . '/trainings';
?>

<div class="container-fluid">
  <div class="col-lg-8 col-md-11 mx-auto ops-dashboard-shell">
    <div class="mb-3">
      <div class="ops-section-title">TRAININGS <span>DELETE</span></div>
      <small class="text-muted">This will mark the training as deleted and hide it from the list.</small>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <h5 class="mb-2">Delete training?</h5>
        <p class="mb-3">You are about to delete the training session <strong><?php echo esc($training->title); ?></strong>.</p>

        <form method="post">
          <?php echo csrf(); ?>
          <input type="hidden" name="id" value="<?php echo (int)$training->id; ?>">
          <input type="hidden" name="_action" value="delete">

          <div class="d-flex justify-content-end gap-2 mt-3">
            <a href="<?php echo $baseUrl; ?>" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn btn-danger">Yes, delete</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

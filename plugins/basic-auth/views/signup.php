<link rel="stylesheet" type="text/css" href="<?=plugin_http_path('assets/css/style.css')?>">

<!-- ==============================
     1) JavaScript: Fade-In & Toggle Password
     ============================== -->
<style>
  a {
    text-decoration: none;
  }
</style>
<script>
  function togglePassword() {
    const pw   = document.getElementById('password');
    const icon = document.getElementById('togglePwIcon');
    if (pw.type === 'password') {
      pw.type = 'text';
      icon.setAttribute('data-feather', 'eye-off');
    } else {
      pw.type = 'password';
      icon.setAttribute('data-feather', 'eye');
    }
    if (typeof feather !== 'undefined') {
      feather.replace();
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    // 1. Fade-in animation
    const container = document.querySelector('.form-container');
    container.classList.add('invisible');
    setTimeout(() => {
      container.classList.replace('invisible', 'visible');
      container.classList.add('fade-in');
    }, 50);

    // 2. Replace Feather icons
    if (typeof feather !== 'undefined') {
      feather.replace();
    }

    // ----------------------------------------------------------------
    // 3. Multi-Step Logic: show inputs in batches of three
    // ----------------------------------------------------------------

    const form = document.querySelector('.form-container form');
    const inputGroups = Array.from(form.querySelectorAll('.input-group'));
    const totalInputs = inputGroups.length;
    const inputs = Array.from(form.querySelectorAll('.input-field'));
    const totalSteps = Math.ceil(totalInputs / 3);
    let currentStep = 0;

    // Create and insert progress bar container
    const progressContainer = document.createElement('div');
    progressContainer.classList.add('progress-container');
    progressContainer.style.width = '100%';
    progressContainer.style.backgroundColor = 'var(--color-input-bg)';
    progressContainer.style.borderRadius = '0.5rem';
    progressContainer.style.marginBottom = '1rem';
    progressContainer.style.overflow = 'hidden';
    const progressBar = document.createElement('div');
    progressBar.classList.add('progress-bar');
    progressBar.style.height = '0.75rem';
    progressBar.style.backgroundColor = 'var(--color-primary-yellow)';
    progressBar.style.width = '0%';
    progressBar.style.transition = 'width 0.3s ease';
    progressContainer.appendChild(progressBar);
    form.prepend(progressContainer);

    // Create navigation buttons container
    const navButtons = document.createElement('div');
    navButtons.classList.add('nav-buttons');
    navButtons.style.display = 'flex';
    navButtons.style.justifyContent = 'space-between';
    navButtons.style.marginTop = '1rem';

    // Back button
    const backBtn = document.createElement('button');
    backBtn.type = 'button';
    backBtn.id = 'backBtn';
    backBtn.textContent = 'Back';
    backBtn.disabled = true;
    backBtn.style.padding = '0.5rem 1rem';
    backBtn.style.border = 'none';
    backBtn.style.borderRadius = '0.5rem';
    backBtn.style.backgroundColor = 'var(--color-secondary-gray)';
    backBtn.style.color = '#fff';
    backBtn.style.cursor = 'not-allowed';
    backBtn.style.fontSize = '1rem';

    // Next button
    const nextBtn = document.createElement('button');
    nextBtn.type = 'button';
    nextBtn.id = 'nextBtn';
    nextBtn.textContent = 'Next';
    nextBtn.disabled = true;
    nextBtn.style.padding = '0.5rem 1rem';
    nextBtn.style.border = 'none';
    nextBtn.style.borderRadius = '0.5rem';
    nextBtn.style.backgroundColor = 'var(--color-secondary-gray)';
    nextBtn.style.color = '#fff';
    nextBtn.style.cursor = 'not-allowed';
    nextBtn.style.fontSize = '1rem';

    // When enabled, switch to primary-blue
    const setButtonEnabled = (btn, enabled) => {
      if (enabled) {
        btn.style.backgroundColor = 'var(--color-primary-blue)';
        btn.style.cursor = 'pointer';
        btn.disabled = false;
      } else {
        btn.style.backgroundColor = 'var(--color-secondary-gray)';
        btn.style.cursor = 'not-allowed';
        btn.disabled = true;
      }
    };

    navButtons.appendChild(backBtn);
    navButtons.appendChild(nextBtn);
    form.appendChild(navButtons);

    // Show only the input groups belonging to a given step
    function showStep(step) {
      inputGroups.forEach((group, idx) => {
        if (idx >= step * 3 && idx < (step + 1) * 3) {
          group.style.display = 'flex';
        } else {
          group.style.display = 'none';
        }
      });
      setButtonEnabled(backBtn, step > 0);

      const submitBtn = form.querySelector('button[type="submit"]');
      if (step === totalSteps - 1) {
        nextBtn.style.display = 'none';
        submitBtn.style.display = 'block';
        updateNavigation();
      } else {
        nextBtn.style.display = 'inline-block';
        submitBtn.style.display = 'none';
        updateNavigation();
      }
      updateProgress();
    }

    // Helper: “Am I married right now?”
    function userIsMarried() {
      const mStatus = form.querySelector('#marital_status_id');
      return mStatus && mStatus.value === '2';
    }

    // ===========================================
    // 1. isCurrentBatchFilled()
    // ===========================================
    function isCurrentBatchFilled() {
      const startIdx = currentStep * 3;
      const endIdx = Math.min(startIdx + 3, totalInputs);

      for (let i = startIdx; i < endIdx; i++) {
        // Find any element with [required] inside this group
        const requiredElem = inputGroups[i].querySelector('[required]');
        if (!requiredElem) {
          // No required element in this group → skip
          continue;
        }

        // If this is spouse_name or spouse_phone, but user is not married, skip
        const rid = requiredElem.id;
        if ((rid === 'spouse_name' || rid === 'spouse_phone') && !userIsMarried()) {
          continue;
        }

        // Now actually check if that required field is non-empty/checked:
        if (requiredElem.type === 'checkbox' || requiredElem.type === 'radio') {
          if (!requiredElem.checked) {
            return false;
          }
        } else {
          if (!requiredElem.value.trim()) {
            return false;
          }
        }
      }

      return true;
    }

    function updateNavigation() {
      if (currentStep < totalSteps - 1) {
        setButtonEnabled(nextBtn, isCurrentBatchFilled());
      } else {
        const submitBtn = form.querySelector('button[type="submit"]');
        const allFilled = inputs.every(inp => {
          return !inp.hasAttribute('required') || inp.value.trim();
        });
        setButtonEnabled(submitBtn, allFilled);
      }
    }

    // ===========================================
    // updateProgress() — treats all “role_*” checkboxes as a single group,
    // and still skips spouse fields if “Married” isn’t selected.
    // ===========================================
    function updateProgress() {
      let filledCount     = 0;
      let consideredCount = 0;
      let roleConsidered  = false;

      inputs.forEach(inp => {
        // 1) Skip spouse fields if user is NOT married:
        if ((inp.id === 'spouse_name' || inp.id === 'spouse_phone') && !userIsMarried()) {
          return;
        }

        // 2) Skip any input whose entire group is currently hidden:
        if (inp.closest('.input-group').style.display === 'none') {
          return;
        }

        // 3) Handle the “role_*” checkboxes as ONE single slot:
        if (inp.name && inp.name.startsWith('role_')) {
          if (!roleConsidered) {
            roleConsidered = true;
            const anyRoleChecked = inputs.some(i => i.name && i.name.startsWith('role_') && i.checked);
            if (anyRoleChecked) {
              filledCount++;
            }
            consideredCount++;
          }
          return;
        }

        // 4) For all other inputs, count them individually:
        consideredCount++;
        if (inp.type === 'checkbox' || inp.type === 'radio') {
          if (inp.checked) {
            filledCount++;
          }
        } else {
          if (inp.value.trim()) {
            filledCount++;
          }
        }
      });

      const percent = consideredCount > 0
        ? Math.floor((filledCount / consideredCount) * 100)
        : 0;
      progressBar.style.width = percent + '%';
    }

    inputs.forEach(inp => {
      inp.addEventListener('input', () => {
        updateNavigation();
        updateProgress();
      });
    });

    nextBtn.addEventListener('click', () => {
      if (isCurrentBatchFilled()) {
        currentStep = Math.min(currentStep + 1, totalSteps - 1);
        showStep(currentStep);
      }
    });

    backBtn.addEventListener('click', () => {
      currentStep = Math.max(currentStep - 1, 0);
      showStep(currentStep);
    });

    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.style.display = 'none';
    showStep(currentStep);

    // ---------------------------------------------------
    // 4. Initialize Select2 on “Referred By”
    // ---------------------------------------------------
    if (typeof jQuery !== 'undefined' && jQuery().select2) {
      $('#referred_by').select2({
        placeholder: 'Select Name',
        width: '100%',
        dropdownParent: $('#referred_by').closest('.input-group')
      });
    }
  });
</script>

<?php 
  $db = new \Core\Database;
  $query = "select * from user_roles where disabled = 0";
  $roles = $db->query($query); 

  $query = "select * from users";
  $users = $db->query($query); 
?>

<!-- ==============================
     2) SIGN-UP FORM (HTML)
     ============================== -->
<main id="theme-main" class="p-4" style="height: 95vh; overflow-y: scroll;">
  <div class="col-lg-5 col-md-8 col-sm-12 mx-auto px-3 py-5 mt-5">
    <div class="form-container login-card rounded-3 shadow-lg invisible">
      <!-- Heading -->
      <div class="text-center">
        <svg fill="#3d4785" style="border-radius: 50%;" width="80" height="80" viewBox="0 0 24 24">
          <path d="M19 7.001c0 3.865-3.134 7-7 7s-7-3.135-7-7c0-3.867 3.134-7.001 7-7.001s7 3.134 7 7.001zm-1.598 7.18c-1.506 1.137-3.374 1.82-5.402 1.82-2.03 0-3.899-.685-5.407-1.822-4.072 1.793-6.593 7.376-6.593 9.821h24c0-2.423-2.6-8.006-6.598-9.819z"/>
        </svg>
      </div>
      <h2 class="login-title">Sign up</h2>
      <p class="text-center text-muted mb-4">
        Enter personal details to create account
      </p>

      <form method="post" enctype="multipart/form-data" novalidate>
        <?= csrf() ?>

        <!-- ===============================
             REPLACED INPUT FIELDS START
             =============================== -->

        <!-- First Name -->
        <div class="input-group">
          <label for="first_name" class="input-label">First Name</label>
          <input
            name="first_name"
            value="<?=old_value('first_name')?>"
            type="text"
            class="input-field <?= !empty($errors['first_name']) ? 'is-invalid' : '' ?>"
            id="first_name"
            placeholder="First Name"
            required
          />
          <?php if(!empty($errors['first_name'])):?>
            <small class="text-danger px-2"><?=$errors['first_name']?></small>
          <?php endif?>
        </div>

        <!-- Last Name -->
        <div class="input-group">
          <label for="last_name" class="input-label">Last Name</label>
          <input
            name="last_name"
            value="<?=old_value('last_name')?>"
            type="text"
            class="input-field <?= !empty($errors['last_name']) ? 'is-invalid' : '' ?>"
            id="last_name"
            placeholder="Last Name"
            required
          />
          <?php if(!empty($errors['last_name'])):?>
            <small class="text-danger px-2"><?=$errors['last_name']?></small>
          <?php endif?>
        </div>

        <!-- Email -->
        <div class="input-group">
          <label for="email" class="input-label">Email</label>
          <input
            name="email"
            value="<?=old_value('email')?>"
            type="email"
            class="input-field <?= !empty($errors['email']) ? 'is-invalid' : '' ?>"
            id="email"
            placeholder="Email"
            required
          />
          <?php if(!empty($errors['email'])):?>
            <small class="text-danger px-2"><?=$errors['email']?></small>
          <?php endif?>
        </div>

        <!-- Phone -->
        <div class="input-group">
          <label for="phone" class="input-label">Phone</label>
          <input
            name="phone"
            value="<?=old_value('phone')?>"
            type="text"
            class="input-field <?= !empty($errors['phone']) ? 'is-invalid' : '' ?>"
            id="phone"
            placeholder="Phone number"
            required
          />
          <?php if(!empty($errors['phone'])):?>
            <small class="text-danger px-2"><?=$errors['phone']?></small>
          <?php endif?>
        </div>

        <!-- Gender -->
        <div class="input-group">
          <label for="gender" class="input-label">Gender</label>
          <select
            class="input-field <?= !empty($errors['gender']) ? 'is-invalid' : '' ?>"
            name="gender"
            id="gender"
            required
          >
            <option value="">--Select Gender--</option>
            <option <?= old_select('gender','male') ?> value="male">Male</option>
            <option <?= old_select('gender','female') ?> value="female">Female</option>
          </select>
          <?php if(!empty($errors['gender'])):?>
            <small class="text-danger px-2"><?=$errors['gender']?></small>
          <?php endif?>
        </div>

        <!-- Brief Overview -->
        <div class="input-group">
          <label for="overview" class="input-label">Brief Overview</label>
          <textarea
            rows="3"
            id="overview"
            class="input-field <?= !empty($errors['overview']) ? 'is-invalid' : '' ?>"
            name="overview"
            placeholder="Write a brief overview for this person"
            required
          ><?=old_value('overview')?></textarea>
          <?php if(!empty($errors['overview'])):?>
            <small class="text-danger px-2"><?=$errors['overview']?></small>
          <?php endif?>
        </div>

        <!-- Date Joined -->
        <div class="input-group">
          <label for="date_joined" class="input-label">Date Joined</label>
          <input
            name="date_joined"
            type="date"
            class="input-field <?= !empty($errors['date_joined']) ? 'is-invalid' : '' ?>"
            id="date_joined"
            required
          />
          <?php if(!empty($errors['date_joined'])):?>
            <small class="text-danger px-2"><?=$errors['date_joined']?></small>
          <?php endif?>
        </div>

        <!-- Ghana Card -->
        <div class="input-group">
          <label for="ghcard" class="input-label">Ghana Card</label>
          <input
            name="ghcard"
            value="<?=old_value('ghcard')?>"
            type="text"
            class="input-field <?= !empty($errors['ghcard']) ? 'is-invalid' : '' ?>"
            id="ghcard"
            placeholder="Ghana card number"
            required
          />
          <?php if(!empty($errors['ghcard'])):?>
            <small class="text-danger px-2"><?=$errors['ghcard']?></small>
          <?php endif?>
        </div>

        <!-- House Number -->
        <div class="input-group">
          <label for="house_number" class="input-label">House Number</label>
          <input
            name="house_number"
            value="<?=old_value('house_number')?>"
            type="text"
            class="input-field <?= !empty($errors['house_number']) ? 'is-invalid' : '' ?>"
            id="house_number"
            placeholder="House number"
            required
          />
          <?php if(!empty($errors['house_number'])):?>
            <small class="text-danger px-2"><?=$errors['house_number']?></small>
          <?php endif?>
        </div>

        <!-- GPS Address -->
        <div class="input-group">
          <label for="gps_address" class="input-label">GPS Address</label>
          <input
            name="gps_address"
            value="<?=old_value('gps_address')?>"
            type="text"
            class="input-field <?= !empty($errors['gps_address']) ? 'is-invalid' : '' ?>"
            id="gps_address"
            placeholder="GPS address"
            required
          />
          <?php if(!empty($errors['gps_address'])):?>
            <small class="text-danger px-2"><?=$errors['gps_address']?></small>
          <?php endif?>
        </div>

        <!-- Nearest Landmark -->
        <div class="input-group">
          <label for="nearest_landmark" class="input-label">Nearest Landmark</label>
          <input
            name="nearest_landmark"
            value="<?=old_value('nearest_landmark')?>"
            type="text"
            class="input-field <?= !empty($errors['nearest_landmark']) ? 'is-invalid' : '' ?>"
            id="nearest_landmark"
            placeholder="Nearest landmark"
            required
          />
          <?php if(!empty($errors['nearest_landmark'])):?>
            <small class="text-danger px-2"><?=$errors['nearest_landmark']?></small>
          <?php endif?>
        </div>

        <!-- Languages Spoken -->
        <div class="input-group">
          <label for="languages_spoken" class="input-label">Languages Spoken</label>
          <input
            name="languages_spoken"
            value="<?=old_value('languages_spoken')?>"
            type="text"
            class="input-field <?= !empty($errors['languages_spoken']) ? 'is-invalid' : '' ?>"
            id="languages_spoken"
            placeholder="Languages spoken"
            required
          />
          <?php if(!empty($errors['languages_spoken'])):?>
            <small class="text-danger px-2"><?=$errors['languages_spoken']?></small>
          <?php endif?>
        </div>

        <!-- Education Level -->
        <div class="input-group">
          <label for="education_level" class="input-label">Level of Education</label>
          <input
            name="education_level"
            value="<?=old_value('education_level')?>"
            type="text"
            class="input-field <?= !empty($errors['education_level']) ? 'is-invalid' : '' ?>"
            id="education_level"
            placeholder="Level of education"
            required
          />
          <?php if(!empty($errors['education_level'])):?>
            <small class="text-danger px-2"><?=$errors['education_level']?></small>
          <?php endif?>
        </div>

        <!-- Past Reference -->
        <div class="input-group">
          <label for="past_reference" class="input-label">Past Reference</label>
          <input
            name="past_reference"
            value="<?=old_value('past_reference')?>"
            type="text"
            class="input-field <?= !empty($errors['past_reference']) ? 'is-invalid' : '' ?>"
            id="past_reference"
            placeholder="Past reference"
            required
          />
          <?php if(!empty($errors['past_reference'])):?>
            <small class="text-danger px-2"><?=$errors['past_reference']?></small>
          <?php endif?>
        </div>

        <!-- Guarantor’s Name -->
        <div class="input-group">
          <label for="guarantor_name" class="input-label">Guarantor’s Name</label>
          <input
            name="guarantor_name"
            value="<?=old_value('guarantor_name')?>"
            type="text"
            class="input-field <?= !empty($errors['guarantor_name']) ? 'is-invalid' : '' ?>"
            id="guarantor_name"
            placeholder="Guarantor’s name"
            required
          />
          <?php if(!empty($errors['guarantor_name'])):?>
            <small class="text-danger px-2"><?=$errors['guarantor_name']?></small>
          <?php endif?>
        </div>

        <!-- Guarantor’s Phone -->
        <div class="input-group">
          <label for="guarantor_phone" class="input-label">Guarantor’s Phone Number</label>
          <input
            name="guarantor_phone"
            value="<?=old_value('guarantor_phone')?>"
            type="text"
            class="input-field <?= !empty($errors['guarantor_phone']) ? 'is-invalid' : '' ?>"
            id="guarantor_phone"
            placeholder="Guarantor’s phone number"
            required
          />
          <?php if(!empty($errors['guarantor_phone'])):?>
            <small class="text-danger px-2"><?=$errors['guarantor_phone']?></small>
          <?php endif?>
        </div>

        <!-- Upload Signature -->
        <div class="input-group">
          <label class="input-label">Upload Signature</label>
          <div class="d-flex align-items-center">
            <label style="cursor:pointer; display:block; width:100%;">
              <img src="<?=get_image('')?>" class="img-thumbnail" style="width:100%;max-width:200px;max-height:100px;object-fit: cover;">
              <input onchange="display_image(event)" type="file" name="signature" class="d-none input-field">
            </label>
            <?php if(!empty($errors['signature'])):?>
              <small class="text-danger px-2"><?=$errors['signature']?></small>
            <?php endif?>
          </div>
        </div>

        <!-- Role Checkboxes -->
        <div class="input-group">
          <label>Roles:</label>
          <div class="row g-2">
            <?php if (!empty($roles)): $num = 0; ?>
              <?php foreach ($roles as $role): $num++; ?>
                <div class="form-check col-md-6">
                  <input
                    <?= in_array($role->id, $row->role_ids ?? []) ? ' checked ' : '' ?>
                    name="role_<?= $num ?>"
                    class="input-field form-check-input"
                    type="checkbox"
                    value="<?= $role->id ?>"
                    id="check<?= $num ?>"
                  >
                  <label class="form-check-label" for="check<?= $num ?>" style="cursor:pointer;">
                    <?= esc(str_replace("_", " ", $role->role)) ?>
                  </label>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>


        <!-- Password -->
        <div class="input-group position-relative">
          <label for="password" class="input-label">Password</label>
          <input
            name="password"
            value="<?=old_value('password','') ?>"
            type="password"
            class="input-field <?= !empty($errors['password']) ? 'is-invalid' : '' ?>"
            id="password"
            placeholder="Password"
          />
          <button
            type="button"
            class="btn btn-link position-absolute end-0 top-50 translate-middle-y me-3 p-0"
            onclick="togglePassword()"
          >
            <i id="togglePwIcon" data-feather="eye"></i>
          </button>
          <?php if (!empty($errors['password'])): ?>
            <small class="text-danger px-2"><?= $errors['password'] ?></small>
          <?php endif; ?>
        </div>

        <!-- Confirm Password -->
        <div class="input-group">
          <label for="retype_password" class="input-label">Retype Password</label>
          <input
            name="retype_password"
            type="password"
            class="input-field <?= !empty($errors['retype_password']) ? 'is-invalid' : '' ?>"
            id="retype_password"
            placeholder="Retype password"
          />
        </div>

        <!-- Profile Image Preview & Upload -->
        <div class="input-group">
          <label class="text-center w-100">
            <center>click to load your profile image</center>
            <img src="<?=get_image('')?>" class="img-thumbnail" style="cursor:pointer;width:100%;max-width:100px;max-height:100px;object-fit: cover;">
            <input onchange="display_image(event)" type="file" name="image" class="d-none input-field">
          </label>
          <?php if(!empty($errors['image'])):?>
            <small class="text-danger px-2"><?=$errors['image']?></small>
          <?php endif?>
        </div>

        <!-- ===============================
             REPLACED INPUT FIELDS END
             =============================== -->

        <!-- Submit Button -->
        <div class="input-group">
          <button type="submit" class="btn-login w-100">
            Submit
          </button>
        </div>
      </form>

      <!-- Already have an account? -->
      <p class="text-center mt-4 small">
        Already have an account?
        <a href="<?= ROOT ?>/login" class="link-primary">Login</a>
      </p>

      <!-- Download Form (always visible but hidden here) -->
      <a class="d-block text-end mt-5 mb-3 d-none" href="<?= ROOT ?>/admin/make_pdf/download_member_form">
        <button class="btn btn-warning fw-bolder w-100">
          <i class="fas fa-download"></i> Download form
        </button>
      </a>
    </div>
  </div>
</main>

<script>
  function load_image(file) {
    document.querySelector(".js-image-preview").src = URL.createObjectURL(file);
  }

  function disableSpouse(status) {
    if (status.value == '2') {
      document.getElementById('spouse_name').hidden = false;
      document.getElementById('spouse_phone').hidden = false;
    } else {
      document.getElementById('spouse_name').hidden = true;
      document.getElementById('spouse_phone').hidden = true;
    }
  }

  function disablePosition(position) {
    document.getElementById('position').disabled = position.value === '';
  }

  function display_image(e) {
    let allowed = ['image/jpeg','image/png','image/webp'];
    let file = e.currentTarget.files[0];
    if (!allowed.includes(file.type)) {
      alert("Only files of this type allowed: " + allowed.toString().replaceAll('image/',''));
      return;
    }
    e.currentTarget.parentNode.querySelector('img').src = URL.createObjectURL(file);
  }
</script>

<script src="<?=plugin_http_path('assets/js/plugin.js')?>"></script>

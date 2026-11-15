<style>

a {
	text-decoration: none;
}
form, input, .small{
	color: var(--text-color);
}

h3.loginTitle{
	color: var(--text-color) !important;
}

div.continueText{
	color: var(--text-color) !important;
}

.work-closed-toast{
	position: fixed;
	top: 18px;
	right: 18px;
	max-width: 420px;
	width: calc(100vw - 36px);
	background: rgba(20, 20, 24, 0.92);
	border: 1px solid rgba(255, 255, 255, 0.08);
	backdrop-filter: blur(10px);
	border-radius: 14px;
	padding: 14px 14px 12px;
	box-shadow: 0 18px 60px rgba(0,0,0,0.45);
	color: #fff;
	z-index: 9999;
}
.work-closed-toast h6{
	margin: 0 0 6px 0;
	font-weight: 700;
	letter-spacing: 0.2px;
}
.work-closed-toast p{
	margin: 0;
	opacity: 0.92;
}
.work-closed-toast .toast-actions{
	display: flex;
	gap: 10px;
	margin-top: 10px;
}
.work-closed-toast button{
	border: 0;
	border-radius: 10px;
	padding: 8px 10px;
	background: rgba(255,255,255,0.10);
	color: #fff;
	font-weight: 600;
}
.work-closed-toast button:hover{ background: rgba(255,255,255,0.16); }
</style>

<link rel="stylesheet" type="text/css" href="<?=plugin_http_path('assets/css/style.css')?>?v=<?= (int)filemtime(__DIR__ . '/../assets/css/style.css') ?>">

<main id="theme-main" class="login-page-main">
	<?php $workClosedNotice = $vars['work_closed_notice'] ?? null; ?>
	<?php if (!empty($workClosedNotice) && is_array($workClosedNotice)): ?>
		<div class="work-closed-toast" id="workClosedToast" role="status" aria-live="polite">
			<h6><?= esc((string)($workClosedNotice['title'] ?? 'Work Closed')) ?></h6>
			<p><?= esc((string)($workClosedNotice['message'] ?? 'Work has closed for today. Please login tomorrow.')) ?></p>
			<div class="toast-actions">
				<button type="button" id="workClosedToastDismiss">OK</button>
			</div>
		</div>
	<?php endif; ?>
	<div class="login-page-content">
	  <section class="login-welcome" aria-label="Welcome">
	    <div class="login-welcome-mark" aria-hidden="true">
	      <span></span><span></span>
	    </div>
	    <div class="login-welcome-copy">
	      <div class="login-eyebrow">TORMOR GROUP <span>·</span> STAFF PORTAL</div>
	      <h1>Welcome back<span>.</span></h1>
	      <p>Sign in to securely access your workspace and continue your operations.</p>
	    </div>
	    <div class="login-welcome-note">
	      <span class="login-status-dot" aria-hidden="true"></span>
	      A secure workspace for your day-to-day work
	    </div>
	  </section>
	  <div class="login-page-container">
	  <form method="POST" class="login-card">
	  	<?= csrf()?>
	    <div class="login-card-heading">
	      <span class="login-card-icon" aria-hidden="true"><i class="fa-solid fa-arrow-right-to-bracket"></i></span>
	      <div class="login-card-eyebrow">YOUR ACCOUNT</div>
	      <h2 class="login-title">Sign in</h2>
	      <p class="login-card-subtitle">Enter your credentials to continue.</p>
	    </div>
	    <?php if(message_success()):?>
	        <div class="alert alert-success text-center">
	          <?=esc(message_success('',true))?>
	        </div>
	      <?php endif?>
	      <?php if(message_fail()):?>
	        <div class="alert alert-danger text-center">
	          <?=esc(message_fail('',true))?>
	        </div>
	      <?php endif?>

	    <div class="input-group">
	      <label for="email" class="input-label text-light">Email</label>
	      <input
	        type="email"
	        id="email"
	        name="email"
	        required
	        class="input-field"
	        placeholder="you@example.com"
	        autocomplete="username"/>
	    </div>

	    <div class="input-group">
	      <label for="password" class="input-label text-light">Password</label>
	      <input
	        type="password"
	        id="password"
	        name="password"
	        required
	        class="input-field"
	        placeholder="••••••••"
	        autocomplete="current-password"
	      />
	    </div>

	    <button type="submit" class="btn-login">
	      Login
	    </button>
	  </form>
	  <!-- Don't have an account? -->
	    <p class="text-center mt-4 small d-none">
	      Don't have an account?
	      <a href="<?= ROOT ?>/signup" class="link-primary">Signup here</a>
	    </p>
	    </div>
	</div>

<script src="<?=plugin_http_path('assets/js/plugin.js')?>"></script>
	<script>
		(function(){
			var btn = document.getElementById('workClosedToastDismiss');
			var card = document.getElementById('workClosedToast');
			if(btn && card){
				btn.addEventListener('click', function(){
					card.style.display = 'none';
				});
				setTimeout(function(){
					try{ card.style.opacity = '0.98'; }catch(e){}
				}, 30);
			}
		})();
	</script>
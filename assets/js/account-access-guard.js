(function(){
	if(window.tormorAccountAccessGuardReady) return;
	window.tormorAccountAccessGuardReady = true;

	const config = window.tormorAccountAccessGuard || {};
	const statusUrl = config.statusUrl || '/account-status';
	const loginUrl = config.loginUrl || '/login';
	const intervalMs = Math.max(10000, Number(config.intervalMs || 15000));
	let stopped = false;
	const isLoginPage = function(){
		try {
			const pathname = window.location.pathname || '';
			return /(^|\/)(login|sign-in)(\/|$)/i.test(pathname);
		}catch(err){
			return false;
		}
	};

	function shouldBlockAccount(res, data){
		if(data && data.deleted === true) return true;
		if(data && data.blocked === true) return true;
		if(res && res.status === 403 && data && data.success === false && data.deleted === true) return true;
		if(res && (res.status === 401 || res.status === 403) && data && data.logged_in === false && data.deleted === true) return true;
		return false;
	}

	function showBlocked(message){
		if(stopped) return;
		stopped = true;
		const text = message || 'Your account has been removed by an administrator. Access to the app has been blocked.';

		if(window.Swal && typeof window.Swal.fire === 'function'){
			window.Swal.fire({
				icon: 'error',
				title: 'Account access blocked',
				text: text,
				allowOutsideClick: false,
				allowEscapeKey: false,
				confirmButtonText: 'Return to login'
			}).then(function(){
				window.location.href = loginUrl;
			});
		}

		document.body.innerHTML = [
			'<main style="min-height:100vh;display:grid;place-items:center;background:#f6f7fb;color:#18202f;font-family:Arial,sans-serif;padding:20px;">',
			'<section role="alert" style="width:min(92vw,520px);background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 18px 45px rgba(15,23,42,.12);padding:28px;text-align:center;">',
			'<div style="width:56px;height:56px;border-radius:50%;display:inline-grid;place-items:center;background:#fee2e2;color:#b91c1c;font-size:28px;margin-bottom:14px;">!</div>',
			'<h1 style="font-size:24px;margin:0 0 10px;">Account access blocked</h1>',
			'<p style="line-height:1.55;color:#4b5563;margin:0 0 22px;">' + text.replace(/[&<>"']/g, function(ch){return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[ch];}) + '</p>',
			'<a href="' + loginUrl + '" style="display:inline-block;background:#111827;color:#fff;text-decoration:none;border-radius:8px;padding:11px 18px;font-weight:700;">Return to login</a>',
			'</section>',
			'</main>'
		].join('');

		setTimeout(function(){
			window.location.href = loginUrl;
		}, 8000);
	}

	async function checkAccount(){
		if(stopped || document.hidden || isLoginPage()) return;
		try{
			const res = await fetch(statusUrl, {
				headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
				credentials: 'same-origin',
				cache: 'no-store'
			});
			let data = null;
			const contentType = (res.headers.get('content-type') || '').toLowerCase();
			if(contentType.includes('application/json')){
				data = await res.json().catch(function(){ return null; });
			}else{
				const text = await res.text().catch(function(){ return ''; });
				if(text.trim().startsWith('{')){
					try { data = JSON.parse(text); } catch(err) {}
				}
			}
			if(shouldBlockAccount(res, data)){
				showBlocked(data && data.message ? data.message : '');
			}
		}catch(err){}
	}

	setTimeout(checkAccount, 2000);
	setInterval(checkAccount, intervalMs);
	document.addEventListener('visibilitychange', function(){
		if(!document.hidden) checkAccount();
	});
})();

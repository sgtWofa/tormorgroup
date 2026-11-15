<div class="container-fluid py-2" >
	<center>
		<div>SGT GLOBALGH Copyright <?=date("Y")?></div>
	</center>
</div>
</main>
	<script src="<?=ROOT?>/assets/js/bootstrap.min.js"></script>
	<script>
		window.tormorAccountAccessGuard = {
			statusUrl: <?=json_encode(ROOT . '/index.php?url=account-status')?>,
			loginUrl: <?=json_encode(ROOT . '/login')?>,
			intervalMs: 15000
		};
	</script>
	<script src="<?=ROOT?>/assets/js/account-access-guard.js?v=20260516-1"></script>
	<script>
		window.basicAdminReleaseSubmitLock = function(form){
			if(!(form instanceof HTMLFormElement)) return;
			if(form.dataset){
				delete form.dataset.boSubmitting;
			}
			const submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
			submitButtons.forEach(btn => {
				try{
					btn.disabled = false;
					btn.removeAttribute('aria-disabled');
				} catch(err){}
			});
		};
		document.addEventListener('submit', function(e){
			const form = e.target;
			if(!(form instanceof HTMLFormElement)) return;
			if(form.getAttribute('data-bo-allow-multi-submit') === '1') return;
			if(form.dataset && form.dataset.boSubmitting === '1'){
				e.preventDefault();
				e.stopPropagation();
				return;
			}
			if(form.dataset){
				form.dataset.boSubmitting = '1';
			}
			const submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
			submitButtons.forEach(btn => {
				try{
					btn.disabled = true;
					btn.setAttribute('aria-disabled', 'true');
				} catch(err){}
			});
			setTimeout(function(){
				if(e.defaultPrevented && form.dataset && form.dataset.boSubmitting === '1'){
					window.basicAdminReleaseSubmitLock(form);
				}
			}, 0);
		}, true);
	</script>
	<script>
	(function(){
		<?php
			$notificationSoundConfig = function_exists('admin_settings_notification_sound_config') ? admin_settings_notification_sound_config() : [];
			$notificationSoundUrls = $notificationSoundConfig['sounds'] ?? [
				ROOT . '/assets/sounds/dragon-studio-new-notification-3-398649.mp3',
				ROOT . '/assets/sounds/dragon-studio-notification-sound-effect-372475.mp3',
				ROOT . '/assets/sounds/universfield-new-notification-040-493469.mp3',
				ROOT . '/assets/sounds/universfield-new-notification-09-352705.mp3',
			];
			$notificationSoundEnabled = $notificationSoundConfig['enabled'] ?? true;
			$notificationSoundVolume = (int)($notificationSoundConfig['volume'] ?? 90);
			$notificationSoundVolume = max(0, min(100, $notificationSoundVolume)) / 100;
		?>
		let audioContext = null;
		let pendingAttention = false;
		let audioUnlocked = false;
		let soundIndex = 0;
		const notificationSounds = <?=json_encode(array_values($notificationSoundUrls))?>;
		const notificationSoundEnabled = <?=json_encode((bool)$notificationSoundEnabled)?>;
		const notificationSoundVolume = <?=json_encode($notificationSoundVolume)?>;
		const preloadedSounds = notificationSounds.map(function(src){
			try{
				const audio = new Audio(src);
				audio.preload = 'auto';
				audio.volume = notificationSoundVolume;
				audio.load();
				return audio;
			}catch(err){
				return null;
			}
		}).filter(Boolean);

		function attentionKeyFromElement(el){
			return String(el.getAttribute('data-attention-key') || el.textContent || 'app-attention').trim().slice(0, 180);
		}

		function markAttentionPlayed(signature){
			try{
				sessionStorage.setItem(signature, '1');
			}catch(err){}
		}

		function playGeneratedTone(){
			try{
				if(!notificationSoundEnabled) return false;
				const AudioCtx = window.AudioContext || window.webkitAudioContext;
				if(!AudioCtx) return false;
				audioContext = audioContext || new AudioCtx();
				if(audioContext.state === 'suspended'){
					audioContext.resume();
				}
				if(audioContext.state === 'suspended'){
					pendingAttention = true;
					return false;
				}

				const now = audioContext.currentTime;
				[880, 1175].forEach(function(freq, index){
					const osc = audioContext.createOscillator();
					const gain = audioContext.createGain();
					osc.type = 'sine';
					osc.frequency.setValueAtTime(freq, now + (index * 0.13));
					gain.gain.setValueAtTime(0.0001, now + (index * 0.13));
					gain.gain.exponentialRampToValueAtTime(0.16, now + 0.03 + (index * 0.13));
					gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.16 + (index * 0.13));
					osc.connect(gain);
					gain.connect(audioContext.destination);
					osc.start(now + (index * 0.13));
					osc.stop(now + 0.18 + (index * 0.13));
				});
				pendingAttention = false;
				return true;
			}catch(err){
				return false;
			}
		}

		function unlockAudio(){
			if(audioUnlocked) return;
			audioUnlocked = true;
			try{
				if(preloadedSounds.length){
					const audio = preloadedSounds[0];
					audio.muted = true;
					audio.currentTime = 0;
					const p = audio.play();
					if(p && typeof p.then === 'function'){
						p.then(function(){
							audio.pause();
							audio.currentTime = 0;
							audio.muted = false;
						}).catch(function(){
							audio.muted = false;
							audioUnlocked = false;
						});
					}else{
						audio.pause();
						audio.currentTime = 0;
						audio.muted = false;
					}
				}
			}catch(err){
				audioUnlocked = false;
			}
			try{
				const AudioCtx = window.AudioContext || window.webkitAudioContext;
				if(AudioCtx){
					audioContext = audioContext || new AudioCtx();
					if(audioContext.state === 'suspended') audioContext.resume();
				}
			}catch(err){}
		}

		function playAttentionTone(signature){
			if(!notificationSoundEnabled){
				markAttentionPlayed(signature);
				return false;
			}
			if(notificationSounds.length){
				try{
					const srcIndex = soundIndex % notificationSounds.length;
					const src = notificationSounds[srcIndex];
					soundIndex++;
					const audio = preloadedSounds[srcIndex] ? preloadedSounds[srcIndex].cloneNode(true) : new Audio(src);
					audio.preload = 'auto';
					audio.volume = notificationSoundVolume;
					const playPromise = audio.play();
					if(playPromise && typeof playPromise.then === 'function'){
						playPromise.then(function(){
							pendingAttention = false;
							markAttentionPlayed(signature);
						}).catch(function(){
							pendingAttention = signature;
							if(playGeneratedTone()){
								pendingAttention = false;
								markAttentionPlayed(signature);
							}
						});
					}else{
						pendingAttention = false;
						markAttentionPlayed(signature);
					}
					return true;
				}catch(err){}
			}
			if(playGeneratedTone()){
				pendingAttention = false;
				markAttentionPlayed(signature);
				return true;
			}
			pendingAttention = signature;
			return false;
		}

		window.tormorPlayAttentionSound = function(key){
			const signature = 'attention-sound:' + String(key || 'app-attention');
			if(sessionStorage.getItem(signature) === '1') return;
			playAttentionTone(signature);
		};
		window.tormorPlayNotificationSound = window.tormorPlayAttentionSound;

		function scanAttentionElements(){
			document.querySelectorAll('[data-attention-sound="1"]').forEach(function(el){
				window.tormorPlayAttentionSound(attentionKeyFromElement(el));
			});
		}

		['click', 'keydown', 'touchstart'].forEach(function(eventName){
			document.addEventListener(eventName, function(){
				unlockAudio();
				if(pendingAttention){
					const signature = typeof pendingAttention === 'string' ? pendingAttention : 'attention-sound:app-attention';
					playAttentionTone(signature);
				}
			}, {passive: true});
		});

		if(document.readyState === 'loading'){
			document.addEventListener('DOMContentLoaded', scanAttentionElements);
		}else{
			scanAttentionElements();
		}
	})();
	</script>
	<?php
		$__liveSession = function_exists('get_session') ? get_session() : null;
		$__liveUserId = (int)($__liveSession->id ?? 0);
	?>
	<?php if($__liveUserId > 0):?>
	<script>
	(function(){
		if(window.tormorLiveNotificationsActive){
			return;
		}
		const liveUrl = <?=json_encode(ROOT . '/admin/live-notifications')?>;
		const userId = <?=json_encode($__liveUserId)?>;
		const storageKey = 'tormor.live.notifications.' + userId;
		const intervalMs = 5000;
		let initialized = false;
		let lastState = {badges: {}, events: {}};
		let inFlight = false;

		window.tormorLiveNotificationsActive = true;

		try{
			const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
			if(saved && typeof saved === 'object'){
				lastState = {
					badges: saved.badges || {},
					events: saved.events || {}
				};
			}
		}catch(e){}

		function asNumber(value){
			const n = parseInt(value || 0, 10);
			return isNaN(n) ? 0 : n;
		}

		function updateBadge(id, count){
			const badges = document.querySelectorAll('[data-notification-badge="' + String(id).replace(/"/g, '\\"') + '"]');
			badges.forEach(function(badge){
				const n = asNumber(count);
				if(n > 0){
					badge.textContent = String(n);
					badge.classList.remove('d-none');
				}else{
					badge.textContent = '';
					badge.classList.add('d-none');
				}
			});
		}

		function shouldPlaySound(data){
			if(!initialized) return false;
			const badges = data.badges || {};
			const prevBadges = lastState.badges || {};
			for(const key in badges){
				if(asNumber(badges[key]) > asNumber(prevBadges[key])){
					return true;
				}
			}

			const events = data.events || {};
			const prevEvents = lastState.events || {};
			if(asNumber(events.tickets && events.tickets.latest_ticket_id) > asNumber(prevEvents.tickets && prevEvents.tickets.latest_ticket_id)){
				return true;
			}
			if(asNumber(events.tickets && events.tickets.latest_response_id) > asNumber(prevEvents.tickets && prevEvents.tickets.latest_response_id)){
				return true;
			}
			if(asNumber(events.backoffice && events.backoffice.latest_id) > asNumber(prevEvents.backoffice && prevEvents.backoffice.latest_id)){
				return true;
			}
			return false;
		}

		function playLiveSound(){
			if(window.tormorPlayNotificationSound){
				window.tormorPlayNotificationSound('global-live-' + Date.now());
			}else if(window.tormorPlayAttentionSound){
				window.tormorPlayAttentionSound('global-live-' + Date.now());
			}
		}

		async function pollLiveNotifications(){
			if(inFlight) return;
			inFlight = true;
			try{
				const url = liveUrl + '?_=' + Date.now();
				const res = await fetch(url, {
					headers: {'X-Requested-With': 'XMLHttpRequest'},
					credentials: 'same-origin'
				});
				const obj = await res.json();
				if(!obj || !obj.success || !obj.data) return;

				const data = obj.data;
				const badges = data.badges || {};
				Object.keys(badges).forEach(function(id){
					updateBadge(id, badges[id]);
				});

				if(shouldPlaySound(data)){
					playLiveSound();
				}

				lastState = {
					badges: badges,
					events: data.events || {}
				};
				try{ localStorage.setItem(storageKey, JSON.stringify(lastState)); }catch(e){}
				initialized = true;

				try{
					window.dispatchEvent(new CustomEvent('tormor:live-notifications', {detail: data}));
				}catch(e){}
			}catch(e){
			}finally{
				inFlight = false;
			}
		}

		if(document.readyState === 'loading'){
			document.addEventListener('DOMContentLoaded', pollLiveNotifications);
		}else{
			pollLiveNotifications();
		}
		setInterval(pollLiveNotifications, intervalMs);
	})();
	</script>
	<?php endif?>
</body>
</html>

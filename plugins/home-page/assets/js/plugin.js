/** Home page interactions: scroll animations + smooth scroll **/

(function () {
	var animateNodes = Array.prototype.slice.call(document.querySelectorAll('[data-animate]'));
	if (!animateNodes.length) return;

	function reveal() {
		var vh = window.innerHeight || document.documentElement.clientHeight;
		animateNodes.forEach(function (el) {
			if (el.classList.contains('is-visible')) return;
			var rect = el.getBoundingClientRect();
			if (rect.top < vh * 0.9) {
				el.classList.add('is-visible');
			}
		});
	}

	window.addEventListener('scroll', reveal, { passive: true });
	window.addEventListener('resize', reveal);
	setTimeout(reveal, 50);

	// Smooth scroll for Explore button(s)
	var scrollButtons = document.querySelectorAll('[data-scroll-target]');
	scrollButtons.forEach(function (btn) {
		btn.addEventListener('click', function () {
			var target = btn.getAttribute('data-scroll-target');
			if (!target) return;
			var el = document.querySelector(target);
			if (!el) return;
			var top = el.getBoundingClientRect().top + window.pageYOffset - 72;
			window.scrollTo({ top: top, behavior: 'smooth' });
		});
	});

})();
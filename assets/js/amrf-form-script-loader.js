/**
 * Loads the scripts OnDemandScripts.php left as placeholders once a form nears the viewport or a visitor reaches for one.
 */
(function () {
	var TRIGGERS = 'a[href="#contact"], [data-contact-trigger], form.frm-fluent-form';
	var INTERACTIONS = ['pointerover', 'pointerdown', 'focusin'];
	var state = 'idle';
	var observer = null;

	function onInteraction(event) {
		if (event.target.closest && event.target.closest(TRIGGERS)) {
			load();
		}
	}

	function load() {
		if (state !== 'idle') {
			return;
		}
		state = 'loading';

		if (observer) {
			observer.disconnect();
		}
		INTERACTIONS.forEach(function (type) {
			document.removeEventListener(type, onInteraction, true);
		});

		var last = null;
		document.querySelectorAll('script[data-amrf-load]').forEach(function (placeholder) {
			var script = document.createElement('script');
			// async = false keeps execution in document order while fetching in parallel.
			script.async = false;
			script.id = placeholder.id;
			script.src = placeholder.getAttribute('data-amrf-load');
			placeholder.replaceWith(script);
			last = script;
		});

		if (!last) {
			state = 'ready';
			return;
		}
		last.addEventListener('load', function () {
			state = 'ready';
		});
	}

	// A submit before the scripts run would be a native POST that reloads the page and loses the input.
	document.addEventListener(
		'submit',
		function (event) {
			if (state !== 'ready' && event.target.matches('form.frm-fluent-form')) {
				event.preventDefault();
				load();
			}
		},
		true
	);

	INTERACTIONS.forEach(function (type) {
		document.addEventListener(type, onInteraction, { capture: true, passive: true });
	});

	if ('IntersectionObserver' in window) {
		observer = new IntersectionObserver(
			function (entries) {
				if (entries.some(function (entry) { return entry.isIntersecting; })) {
					load();
				}
			},
			{ rootMargin: '0px 0px 100% 0px' }
		);
		document.querySelectorAll('form.frm-fluent-form').forEach(function (form) {
			observer.observe(form);
		});
	} else {
		load();
	}
})();

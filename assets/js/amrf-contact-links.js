/**
 * ROT13-encoded email links and desktop copy buttons, see SiteSettings\ContactLinks.
 * Exposes isMobileDevice() and setupCopyButton() as window.amrfContactLinks for amrf-swish.js.
 */
(() => {
	const copiedLabel = window.amrfContactLinksConfig?.copiedLabel || '';

	const rot13 = (value) =>
		value.replace(/[a-z]/gi, (char) => {
			const base = char <= 'Z' ? 65 : 97;
			return String.fromCharCode(((char.charCodeAt(0) - base + 13) % 26) + base);
		});

	// tel: and Swish deep links only work on a device that can dial or run the app.
	const isMobileDevice = () => {
		const platform = window.navigator.userAgentData?.platform;
		if (platform) return platform === 'Android' || platform === 'iOS';

		const ua = navigator.userAgent;
		if (/android|iphone|ipod/i.test(ua)) return true;

		if (/Macintosh/.test(ua) && navigator.maxTouchPoints > 0) return true;

		return false;
	};

	const setupCopyButton = (button) => {
		const textEl = button.querySelector('.amrf-copy-text');
		if (!textEl) return;

		const originalText = textEl.textContent;
		const copyValue = button.dataset.copyValue || originalText;
		let resetTimer;

		button.setAttribute('aria-live', 'polite');
		button.addEventListener('click', () => {
			if (!navigator.clipboard?.writeText) return;

			navigator.clipboard.writeText(copyValue).then(() => {
				clearTimeout(resetTimer);
				textEl.textContent = copiedLabel || originalText;
				button.classList.add('is-copied');
				resetTimer = setTimeout(() => {
					textEl.textContent = originalText;
					button.classList.remove('is-copied');
				}, 1500);
			});
		});
	};

	document.querySelectorAll('.amrf-email-link[data-user][data-domain]').forEach((link) => {
		const user = rot13(link.dataset.user);
		const domain = rot13(link.dataset.domain);
		if (!user || !domain) return;

		const address = `${user}@${domain}`;
		link.href = `mailto:${address}`;

		const text = link.querySelector('.amrf-email-link-text');
		if (text) text.textContent = address;
		link.hidden = false;
	});

	if (!isMobileDevice()) {
		document.querySelectorAll('a.amrf-copy-link').forEach((link) => {
			const button = document.createElement('button');
			button.type = 'button';
			button.className = link.className;
			button.dataset.copyValue = link.dataset.copyValue;
			button.innerHTML = link.innerHTML;
			link.replaceWith(button);
			setupCopyButton(button);
		});
	}

	window.amrfContactLinks = { isMobileDevice, setupCopyButton };
})();

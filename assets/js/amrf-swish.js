/**
 * Sitewide "#swish" link handling — scans the page for `<a href="#swish">`
 * and swaps each for either a deep link (mobile) or a QR code (desktop).
 * Config comes from one global object, amrfSwish (Swish\FrontendProvider).
 */

function canRunSwish() {
	const platform = window.navigator.userAgentData?.platform;
	if (platform) return platform === 'Android' || platform === 'iOS';

	const ua = navigator.userAgent;
	if (/android|iphone|ipod/i.test(ua)) return true;

	if (/Macintosh/.test(ua) && navigator.maxTouchPoints > 0) return true;

	return false;
}

function setupSwishLink(link) {
	if (link.dataset.swishReady) return;
	link.dataset.swishReady = 'true';

	if (canRunSwish() && window.amrfSwish?.swishUrl) {
		link.href = window.amrfSwish.swishUrl;
		return;
	}

	if (!window.amrfSwish?.qrSrc) return;

	const img = document.createElement('img');
	img.src = window.amrfSwish.qrSrc;
	img.alt = window.amrfSwish.qrAlt || '';
	img.loading = 'lazy';
	img.className = 'amrf-swish-qr';
	link.replaceWith(img);
}

document.querySelectorAll('a[href="#swish"]').forEach(setupSwishLink);

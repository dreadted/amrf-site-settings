/**
 * Sitewide "#swish" link handling — ported from ptsussis-theme's
 * blocks/cta/index.js (canRunSwish() device check, unchanged), generalized
 * to scan the whole rendered page for plain <a href="#swish"> instead of a
 * specific block's own data-swish-trigger button, since amrf-theme has no
 * block/CTA system for a per-block feature to hang off of. Config comes
 * from one global object (amrfSwish, via wp_localize_script — see
 * Swish\FrontendProvider) rather than per-element data attributes: there's
 * only ever one Swish account per site.
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

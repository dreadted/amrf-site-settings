/**
 * Swaps each <a href="#swish"> for a deep link on mobile or a QR code on desktop.
 * Config: the link's data-swish-url/-qr (Swish\LinkQr), else amrfSwish (Swish\FrontendProvider);
 * device detection and copy button: amrf-contact-links.js.
 */

// A link with its own details never falls back to the site's code, which has another amount.
function swishConfig(link) {
	if ('swishQr' in link.dataset) {
		return { swishUrl: link.dataset.swishUrl, qrSrc: link.dataset.swishQr };
	}

	return { swishUrl: window.amrfSwish?.swishUrl, qrSrc: window.amrfSwish?.qrSrc };
}

function setupSwishLink(link) {
	if (link.dataset.swishReady) return;
	link.dataset.swishReady = 'true';

	const { isMobileDevice, setupCopyButton } = window.amrfContactLinks;
	const { swishUrl, qrSrc } = swishConfig(link);

	if (isMobileDevice() && swishUrl) {
		link.href = swishUrl;
		return;
	}

	if (!qrSrc) return;

	const number = window.amrfSwish.qrAlt || '';

	const wrap = document.createElement('div');
	wrap.className = 'amrf-swish-qr-wrap';

	const img = document.createElement('img');
	img.src = qrSrc;
	img.alt = number;
	img.loading = 'lazy';
	img.className = 'amrf-swish-qr';
	img.addEventListener('error', () => img.remove(), { once: true });
	wrap.appendChild(img);

	if (number) {
		const numberButton = document.createElement('button');
		numberButton.type = 'button';
		numberButton.className = 'amrf-swish-qr-number';
		numberButton.dataset.copyValue = number;

		const numberText = document.createElement('span');
		numberText.className = 'amrf-copy-text';
		numberText.textContent = number;
		numberButton.appendChild(numberText);

		setupCopyButton(numberButton);
		wrap.appendChild(numberButton);
	}

	link.replaceWith(wrap);
}

document.querySelectorAll('a[href="#swish"]').forEach(setupSwishLink);

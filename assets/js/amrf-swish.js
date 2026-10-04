/**
 * Swaps each <a href="#swish"> for a deep link on mobile or a QR code on desktop.
 * Config: amrfSwish (Swish\FrontendProvider); device detection and copy button: amrf-contact-links.js.
 */

function setupSwishLink(link) {
	if (link.dataset.swishReady) return;
	link.dataset.swishReady = 'true';

	const { isMobileDevice, setupCopyButton } = window.amrfContactLinks;

	if (isMobileDevice() && window.amrfSwish?.swishUrl) {
		link.href = window.amrfSwish.swishUrl;
		return;
	}

	if (!window.amrfSwish?.qrSrc) return;

	const number = window.amrfSwish.qrAlt || '';

	const wrap = document.createElement('div');
	wrap.className = 'amrf-swish-qr-wrap';

	const img = document.createElement('img');
	img.src = window.amrfSwish.qrSrc;
	img.alt = number;
	img.loading = 'lazy';
	img.className = 'amrf-swish-qr';
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

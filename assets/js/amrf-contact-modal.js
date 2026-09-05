/**
 * Lightbox mechanics for the sitewide "#kontakt" contact link — open/close,
 * backdrop/Escape/close-button dismissal, focus handling. Ported from
 * ptsussis-theme's assets/js/contact-modal.js, generalized: that version
 * only opened for a specific block's own [data-contact-trigger] button;
 * this instead opens for ANY `<a href="#kontakt">` on the page (a
 * [data-contact-trigger] attribute still works too, for a non-anchor
 * element — e.g. a <button> — that wants the same behavior without using
 * that literal href).
 *
 * The form inside is FluentForm's real markup (see Modal.php), which
 * wires up its own validation/AJAX submission the moment its script runs;
 * this file only ever touches the modal shell around it — auto-closing
 * five seconds after FluentForm's own "fluentform_submission_success"
 * event (a real document-level CustomEvent it dispatches itself), and
 * keeping keyboard/AT focus confined to the dialog while it's open (see
 * setBackgroundInert() and the Tab handling below).
 */
(function () {
	var modal = document.querySelector('[data-contact-modal]');
	if (!modal) {
		return;
	}

	var dialog = modal.querySelector('.amrf-contact-modal-dialog');
	var backdrop = modal.querySelector('[data-contact-backdrop]');
	var closeButton = modal.querySelector('[data-contact-close]');
	var form = modal.querySelector('form');
	// The first real text field, whatever FluentForm happens to name/ID it
	// as — .ff-el-form-control is the class FluentForm puts on every
	// visible input/textarea (not checkboxes/radios, not the hidden
	// nonce/honeypot fields), so the first match in document order is
	// reliably the form's first text box regardless of form structure.
	var firstTextField = form ? form.querySelector('input.ff-el-form-control, textarea.ff-el-form-control') : null;
	var lastFocused = null;
	var autoCloseTimer;

	// aria-modal="true" alone doesn't stop Tab from reaching page content
	// behind the overlay, and a sighted keyboard user tabbing past the
	// dialog's own last field would otherwise land on nav/content elements
	// still sitting there under the backdrop. inert removes every other
	// top-level element from both the tab order and the accessibility
	// tree in one go — modal itself is skipped since it's the one thing
	// that should stay reachable.
	function setBackgroundInert(isInert) {
		Array.prototype.forEach.call(document.body.children, function (el) {
			if (el === modal) {
				return;
			}
			if (isInert) {
				el.setAttribute('inert', '');
			} else {
				el.removeAttribute('inert');
			}
		});
	}

	// Belt-and-suspenders alongside inert above: inert stops Tab from ever
	// reaching the background, but doesn't make Tab wrap back to the start
	// of the dialog once it runs past the last field — without this, Tab
	// from the last focusable element just goes nowhere useful (browser
	// chrome, or nothing). Keeps focus cycling within the dialog either way.
	function trapTabKey(event) {
		if (event.key !== 'Tab' || !dialog) {
			return;
		}

		var focusable = Array.prototype.slice
			.call(dialog.querySelectorAll('a[href], button:not([disabled]), input:not([type="hidden"]):not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'))
			.filter(function (el) {
				return el.offsetParent !== null;
			});

		if (!focusable.length) {
			return;
		}

		var first = focusable[0];
		var last = focusable[focusable.length - 1];

		if (event.shiftKey && document.activeElement === first) {
			event.preventDefault();
			last.focus();
		} else if (!event.shiftKey && document.activeElement === last) {
			event.preventDefault();
			first.focus();
		}
	}

	function openModal() {
		clearTimeout(autoCloseTimer);

		if (form) {
			form.reset();
		}

		lastFocused = document.activeElement;
		modal.hidden = false;
		setBackgroundInert(true);
		document.body.style.overflow = 'hidden';
		requestAnimationFrame(function () {
			modal.setAttribute('data-visible', '');
		});
		if (firstTextField) {
			firstTextField.focus();
		}
	}

	// Undoes what FluentForm's own form-submission.js does on a successful
	// submit — it jQuery-.hide()s the <form> and adds a "ff_force_hide"
	// class (belt-and-suspenders, both drive display:none), then inserts a
	// new <div class="ff-message-success"> right after it. Neither is ever
	// undone on its own, so without this, reopening the modal after one
	// successful submission would show the old success message forever —
	// field VALUES already get cleared by FluentForm's own native
	// form.reset() call in that same success handler, this is only about
	// the form's visibility state.
	function resetFormDisplay() {
		if (!form) {
			return;
		}
		form.classList.remove('ff_force_hide');
		form.style.removeProperty('display');
		modal.querySelectorAll('.ff-message-success').forEach(function (el) {
			el.remove();
		});
	}

	function closeModal() {
		clearTimeout(autoCloseTimer);
		modal.removeAttribute('data-visible');
		setBackgroundInert(false);
		resetFormDisplay();
		document.body.style.removeProperty('overflow');
		if (lastFocused) {
			lastFocused.focus();
		}
		setTimeout(function () {
			if (!modal.hasAttribute('data-visible')) {
				modal.hidden = true;
			}
		}, 250);
	}

	document.addEventListener('click', function (event) {
		var trigger = event.target.closest('a[href="#kontakt"], [data-contact-trigger]');
		if (!trigger) {
			return;
		}
		event.preventDefault();
		openModal();
	});

	if (closeButton) {
		closeButton.addEventListener('click', closeModal);
	}
	if (backdrop) {
		backdrop.addEventListener('click', closeModal);
	}

	document.addEventListener('keydown', function (event) {
		if (!modal.hasAttribute('data-visible')) {
			return;
		}
		if (event.key === 'Escape') {
			closeModal();
			return;
		}
		trapTabKey(event);
	});

	document.addEventListener('fluentform_submission_success', function (event) {
		if (form && event.detail && event.detail.form === form) {
			clearTimeout(autoCloseTimer);
			autoCloseTimer = setTimeout(closeModal, 5000);
		}
	});
})();

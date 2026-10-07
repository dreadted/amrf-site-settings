/**
 * Modal for <a href="#contact"> and [data-contact-trigger]: focus trap, close after submit, Subject from data-topic.
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
	// FluentForm's hidden honeypot also has .ff-el-form-control and would swallow the focus.
	var firstTextField = form
		? Array.prototype.find.call(
				form.querySelectorAll('input.ff-el-form-control, textarea.ff-el-form-control'),
				function (el) {
					return !el.closest('.ff-hpsf-container');
				}
		  )
		: null;
	var lastFocused = null;
	var autoCloseTimer;

	// aria-modal alone doesn't stop Tab from reaching page content behind the
	// overlay — inert removes every other top-level element from the tab
	// order and accessibility tree in one go.
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

	// inert (above) stops Tab reaching the background, but doesn't wrap Tab
	// back to the dialog's start — this keeps focus cycling within it.
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

	// Returns true/false so the click handler below only suppresses the
	// href="#contact" fallback once the modal has actually opened.
	function openModal(trigger) {
		try {
			clearTimeout(autoCloseTimer);

			if (form) {
				form.reset();
				var subjectField = form.querySelector('[name="subject"]');
				if (subjectField) {
					subjectField.value = (trigger && trigger.dataset.topic) || '';
				}
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
			return true;
		} catch (error) {
			console.error('amrf-contact-modal: failed to open modal', error);
			return false;
		}
	}

	// Undoes what FluentForm hides/inserts on successful submit (the form
	// itself and a ".ff-message-success" div) — neither reverts on its own,
	// so reopening the modal would otherwise show the old success message.
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
		var trigger = event.target.closest('a[href="#contact"], [data-contact-trigger]');
		if (!trigger) {
			return;
		}
		if (openModal(trigger)) {
			event.preventDefault();
		}
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

	// Where the contact shortcut lands (ContactForm\Shortcut); the fragment is dropped so a reload doesn't reopen it.
	if (window.location.hash === '#contact-modal') {
		history.replaceState(null, '', window.location.pathname + window.location.search);
		openModal(null);
	}
})();

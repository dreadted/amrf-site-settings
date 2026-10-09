/**
 * Resets a form's ALTCHA widget after a failed spam check, so the next submit solves a fresh challenge.
 */
document.addEventListener("fluentform_submission_failed", (event) => {
	const { form, response } = event.detail || {};

	if (!form || !response?.responseJSON?.errors?.amrf_spam_check) {
		return;
	}

	form.querySelector("altcha-widget")?.reset();
});

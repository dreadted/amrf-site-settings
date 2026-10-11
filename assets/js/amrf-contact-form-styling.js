/**
 * Grows FluentForm textareas with their content: a one-row field expands once the visitor types a longer
 * message, a multi-row field starts at its rows and never shrinks below them.
 */
(function () {
	// Browsers without field-sizing (e.g. iOS < 26.2) need the height set from scrollHeight.
	var needsHeightFallback = !CSS.supports("field-sizing", "content");

	function fitToContent(textarea) {
		textarea.style.height = "auto";
		// scrollHeight excludes borders, which border-box height includes.
		var border = textarea.offsetHeight - textarea.clientHeight;
		textarea.style.height = textarea.scrollHeight + border + "px";
	}

	function growFromOneRow(textarea) {
		textarea.addEventListener("input", function () {
			textarea.classList.toggle("is-expanded", textarea.value.length >= 10);

			if (needsHeightFallback) {
				fitToContent(textarea);
			}
		});
	}

	function growFromRows(textarea) {
		textarea.addEventListener("input", function () {
			fitToContent(textarea);
		});
	}

	// FluentForm resets the form natively after a submit, which fires no input event.
	function shrinkOnReset(textarea) {
		textarea.form.addEventListener("reset", function () {
			textarea.classList.remove("is-expanded");
			textarea.style.height = "";
		});
	}

	document.addEventListener("DOMContentLoaded", function () {
		document
			.querySelectorAll("form.frm-fluent-form textarea.ff-el-form-control")
			.forEach(function (textarea) {
				if (textarea.rows === 1) {
					growFromOneRow(textarea);
				} else {
					growFromRows(textarea);
				}

				shrinkOnReset(textarea);
			});
	});
})();

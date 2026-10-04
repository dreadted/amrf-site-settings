/**
 * Grows a one-row FluentForm textarea once the visitor types a longer message, and shrinks it when cleared.
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

	function resizeTextarea(textarea) {
		textarea.addEventListener("input", function () {
			textarea.classList.toggle("is-expanded", textarea.value.length >= 10);

			if (needsHeightFallback) {
				fitToContent(textarea);
			}
		});
	}

	document.addEventListener("DOMContentLoaded", function () {
		document
			.querySelectorAll(
				'form.frm-fluent-form textarea.ff-el-form-control[rows="1"]'
			)
			.forEach(resizeTextarea);
	});
})();

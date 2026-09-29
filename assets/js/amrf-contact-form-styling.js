/**
 * Expands a FluentForm textarea that starts collapsed to one row
 * (rows="1") once the visitor has typed enough to suggest a longer
 * message, and collapses it back if they clear it. Pairs with the
 * .fluentform textarea.ff-el-form-control transition in
 * amrf-contact-form-styling.css.
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

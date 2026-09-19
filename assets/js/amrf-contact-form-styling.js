/**
 * Expands a FluentForm textarea that starts collapsed to one row
 * (rows="1") once the visitor has typed enough to suggest a longer
 * message, and collapses it back if they clear it. Pairs with the
 * .fluentform textarea.ff-el-form-control transition in
 * amrf-contact-form-styling.css.
 */
(function () {
  function resizeTextarea(textarea) {
    textarea.addEventListener("input", function () {
      textarea.classList.toggle("is-expanded", textarea.value.length >= 10);
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

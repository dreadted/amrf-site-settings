// Client-side validation for Swedish Personal Identity Number fields. Fluent Forms
// puts the "ff-personnummer" Container Class (FluentFormValidation\Provider's
// CONTAINER_CLASS) on the field's .ff-el-group, not on the input.

const pinValidationStrings = {
  invalidPin: wp.i18n.__(
    "Please enter a valid Swedish Personal Identity Number (YYMMDD-XXXX).",
    "amrf-admin"
  ),
};

// Mirrors the error markup Fluent Forms itself renders, so its own
// on-change clearing removes our message too.
const showFluentFormsError = (input, message) => {
  const group = input.closest(".ff-el-group");
  const content = group.querySelector(".ff-el-input--content") || group;
  let errorContainer = content.querySelector(".error");

  if (!errorContainer) {
    errorContainer = document.createElement("div");
    errorContainer.classList.add("error", "text-danger");
    errorContainer.setAttribute("role", "alert");
    content.appendChild(errorContainer);
  }

  errorContainer.textContent = message;
  input.setAttribute("aria-invalid", "true");
  group.classList.add("ff-el-is-error");
};

const clearFluentFormsError = (input) => {
  const group = input.closest(".ff-el-group");
  group.querySelector(".error")?.remove();
  input.setAttribute("aria-invalid", "false");
  group.classList.remove("ff-el-is-error");
};

const validatePIN = (PIN) => {
  PIN = PIN.replace(/[-\s]/g, "");

  if (PIN.length !== 10 && PIN.length !== 12) {
    return false;
  }

  if (PIN.length === 12) {
    PIN = PIN.substring(2);
  }

  if (!/^\d+$/.test(PIN)) {
    return false;
  }

  let sum = 0;
  for (let i = 0; i < 9; i++) {
    let digit = parseInt(PIN.charAt(i), 10);
    if (i % 2 === 0) {
      digit *= 2;
      if (digit > 9) {
        digit = digit - 9;
      }
    }
    sum += digit;
  }

  const checksum = (10 - (sum % 10)) % 10;
  const lastDigit = parseInt(PIN.charAt(9), 10);

  return checksum === lastDigit;
};

const formatPIN = (PIN) => {
  PIN = PIN.replace(/[^\d]/g, "");

  if (PIN.length === 12) {
    PIN = PIN.substring(2);
  }

  if (PIN.length === 10) {
    return `${PIN.substring(0, 6)}-${PIN.substring(6)}`;
  }

  return PIN;
};

const isInvalidPinInput = (input) =>
  input.value.trim() !== "" && !validatePIN(input.value);

const initPinValidation = () => {
  const pinInputs = document.querySelectorAll(
    ".frm-fluent-form .ff-personnummer input.ff-el-form-control"
  );

  pinInputs.forEach((input) => {
    input.setAttribute("autocomplete", "off");

    input.addEventListener("blur", () => {
      if (input.value.trim() === "") {
        clearFluentFormsError(input);
      } else if (!validatePIN(input.value)) {
        showFluentFormsError(input, pinValidationStrings.invalidPin);
      } else {
        clearFluentFormsError(input);
        input.value = formatPIN(input.value);
      }
    });
  });

  const forms = new Set(
    [...pinInputs].map((input) => input.closest("form.frm-fluent-form"))
  );

  forms.forEach((form) => {
    form.addEventListener("submit", (e) => {
      const invalid = [
        ...form.querySelectorAll(".ff-personnummer input.ff-el-form-control"),
      ].filter(isInvalidPinInput);

      if (invalid.length === 0) return;

      // Fluent Forms submits via a jQuery handler delegated to document;
      // stopping propagation here keeps that handler from firing.
      e.preventDefault();
      e.stopPropagation();
      invalid.forEach((input) =>
        showFluentFormsError(input, pinValidationStrings.invalidPin)
      );
      invalid[0].focus();
    });
  });
};

document.addEventListener("DOMContentLoaded", () => {
  initPinValidation();
});

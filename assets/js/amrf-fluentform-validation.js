// Client-side validation for the Swedish Personal Identity Number field,
// identified by the "ff-personnummer" CSS class (FluentFormValidation\Provider's
// CONTAINER_CLASS), not by field name.

const pinValidationStrings = {
  invalidPin: wp.i18n.__(
    "Please enter a valid Swedish Personal Identity Number (YYMMDD-XXXX)",
    "amrf-admin"
  ),
  requiredPin: wp.i18n.__("Personal Identity Number is required", "amrf-admin"),
};

const showFluentFormsError = (element, message) => {
  let errorContainer = element.closest(".ff-el-group").querySelector(".error");

  if (!errorContainer) {
    errorContainer = document.createElement("div");
    errorContainer.classList.add("error", "text-danger");

    element.closest(".ff-el-group").appendChild(errorContainer);
  }

  errorContainer.textContent = message;
  element.classList.add("is-invalid");
  element.closest(".ff-el-group").classList.add("ff-el-is-error");
};

const clearFluentFormsError = (element) => {
  const errorContainer = element
    .closest(".ff-el-group")
    .querySelector(".ff-el-is-error");
  if (errorContainer) {
    errorContainer.remove();
  }
  element.classList.remove("is-invalid");
  element.closest(".ff-el-group").classList.remove("ff-el-is-error");
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

const initPinValidation = () => {
  const pinField = document.querySelector(".frm-fluent-form .ff-personnummer");

  if (!pinField) return;

  pinField.setAttribute("autocomplete", "off");

  pinField.addEventListener("blur", () => {
    if (pinField.value.trim() === "") {
      clearFluentFormsError(pinField);
      return;
    }

    if (!validatePIN(pinField.value)) {
      showFluentFormsError(pinField, pinValidationStrings.invalidPin);
    } else {
      clearFluentFormsError(pinField);
      pinField.value = formatPIN(pinField.value);
    }
  });

  const form = pinField.closest("form.frm-fluent-form");
  if (form) {
    form.addEventListener("submit", (e) => {
      if (pinField.value.trim() !== "" && !validatePIN(pinField.value)) {
        e.preventDefault();
        showFluentFormsError(pinField, pinValidationStrings.invalidPin);
        pinField.focus();
      }
    });
  }
};

document.addEventListener("DOMContentLoaded", () => {
  initPinValidation();
});

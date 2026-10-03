<?php

namespace Antropomorf\FluentFormValidation;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class Provider
 *
 * Swedish Personal Identity Number (personnummer) validation/formatting for
 * FluentForm text fields. Identifies the field by a CSS class
 * (self::CONTAINER_CLASS) on its container, not by field name/label —
 * matching on a name like "personnummer" would be language-fragile and
 * risks false positives on unrelated text fields.
 *
 * @package Antropomorf\FluentFormValidation
 */
class Provider
{
  /**
   * CSS class a form's PIN field must carry (FluentForm's own "Container
   * Class" field setting) for this module to touch it. Undocumented
   * outside this code and the plan — whoever builds a form with a
   * personnummer field needs to know to set it.
   */
  private const CONTAINER_CLASS = 'ff-personnummer';

  private const SCRIPT_HANDLE = 'amrf-fluentform-validation';

  public function __construct()
  {
    add_filter('fluentform/validate_input_item_text', [$this, 'validatePinField'], 10, 4);
    add_filter('fluentform/response_render_item_text', [$this, 'formatPinField'], 10, 2);
    add_action('fluentform/before_form_render', [$this, 'enqueueValidationScript']);
  }

  /**
   * @param string|null $error
   * @param array       $field
   * @param array       $formData
   * @param array       $fields
   * @return string|null
   */
  public function validatePinField($error, array $field, array $formData, array $fields)
  {
    if (!$this->isPinField($field)) {
      return $error;
    }

    $pin = $field['value'];

    // Skip validation if field is empty (use required validation for that).
    if (empty($pin)) {
      return $error;
    }

    $sanitized_pin = preg_replace('/[^0-9]/', '', $pin);

    if (!$this->isValidSwedishPin($sanitized_pin)) {
      return __('Please enter a valid Swedish Personal Identity Number (YYMMDD-XXXX).', 'amrf-admin');
    }

    return $error;
  }

  /**
   * @param string $value
   * @param array  $field
   * @return string
   */
  public function formatPinField($value, array $field)
  {
    if (!$this->isPinField($field)) {
      return $value;
    }

    if (empty($value)) {
      return $value;
    }

    $sanitized_pin = preg_replace('/[^0-9]/', '', $value);

    if ($this->isValidSwedishPin($sanitized_pin)) {
      return $this->formatSwedishPin($sanitized_pin);
    }

    return $value;
  }

  /**
   * Only Simple Text fields count, since the validation filters and the script target text inputs.
   *
   * @param array $field
   * @return bool Whether this is a text field carrying the CONTAINER_CLASS marker.
   */
  private function isPinField(array $field): bool
  {
    if (($field['element'] ?? '') !== 'input_text') {
      return false;
    }

    $container_class = $field['settings']['container_class'] ?? '';
    return $container_class !== '' && strpos($container_class, self::CONTAINER_CLASS) !== false;
  }

  /**
   * Luhn-style checksum validation for a Swedish personal identity number.
   *
   * @param string $pin 10 or 12 digits, century prefix already stripped by caller if 12.
   * @return bool
   */
  private function isValidSwedishPin(string $pin): bool
  {
    if (strlen($pin) !== 10 && strlen($pin) !== 12) {
      return false;
    }

    if (strlen($pin) === 12) {
      $pin = substr($pin, 2);
    }

    if (!ctype_digit($pin)) {
      return false;
    }

    $sum = 0;
    for ($i = 0; $i < 9; $i++) {
      $digit = intval($pin[$i]);
      if ($i % 2 === 0) {
        $digit *= 2;
        if ($digit > 9) {
          $digit -= 9;
        }
      }
      $sum += $digit;
    }

    $checksum = (10 - ($sum % 10)) % 10;
    $last_digit = intval($pin[9]);

    return $checksum === $last_digit;
  }

  /**
   * @param string $pin Already validated by isValidSwedishPin().
   * @return string "YYMMDD-XXXX"
   */
  private function formatSwedishPin(string $pin): string
  {
    if (strlen($pin) === 12) {
      $pin = substr($pin, 2);
    }

    return substr($pin, 0, 6) . '-' . substr($pin, 6);
  }

  /**
   * Whether any field in the form, including fields nested in containers, is a PIN field.
   *
   * @param array $fields
   * @return bool
   */
  private function hasPinField(array $fields): bool
  {
    foreach ($fields as $field) {
      if (!is_array($field)) {
        continue;
      }

      if ($this->isPinField($field)) {
        return true;
      }

      foreach ($field['columns'] ?? [] as $column) {
        if ($this->hasPinField($column['fields'] ?? [])) {
          return true;
        }
      }
    }

    return false;
  }

  /**
   * Client-side mirror of the same validation, enqueued only for forms that have a PIN field.
   *
   * @param object $form FluentForm form, with decoded $form->fields.
   * @return void
   */
  public function enqueueValidationScript($form): void
  {
    if (!$this->hasPinField($form->fields['fields'] ?? [])) {
      return;
    }

    wp_enqueue_script(
      self::SCRIPT_HANDLE,
      AMRF_ADMIN_PLUGIN_URL . 'assets/js/amrf-fluentform-validation.js',
      ['wp-i18n'],
      filemtime(AMRF_ADMIN_PLUGIN_DIR . '/assets/js/amrf-fluentform-validation.js'),
      ['strategy' => 'defer', 'in_footer' => true]
    );

    wp_set_script_translations(self::SCRIPT_HANDLE, 'amrf-admin', AMRF_ADMIN_PLUGIN_DIR . '/languages');
  }
}

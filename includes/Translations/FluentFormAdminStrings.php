<?php

namespace Antropomorf\Translations;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class FluentFormAdminStrings
 *
 * Fluent Forms' admin scripts translate through a server-built key map
 * (admin_i18n) and fall back to English for keys missing from it. Adds the
 * keys its entries page uses, translated from Fluent Forms' own catalogue.
 *
 * @package Antropomorf\Translations
 */
class FluentFormAdminStrings
{
  private const ENTRIES_PAGE_KEYS = [
    'Action',
    'CSV Delimiter',
    'Choose File type you would like to import',
    'Delete Existing Submissions',
    'Enter to Select',
    'Esc to close',
    'File Type',
    'Form Fields',
    'Import',
    'Imported',
    'It will take some times. Please wail...',
    'Map responsible fields to import',
    'Mapping Fields',
    "Maximum execution time error, due to lot's of entries. Maybe fail importing some of entries. Please increase server maximum executing time and try again.",
    'Navigate',
    'Next [Map Columns]',
    'Please make sure your csv file delimiter is correct and has unique headers. Otherwise, it may fail to import',
    'Search not match. Try a different query.',
    'Select File',
    'Select Form',
    'Select Forms',
    'Select the FluentForms exported entries (.json) file. Otherwise, it may fail to import',
    'Select the form you would like to map entries.',
    'Select your CSV file delimiter',
    'Show Submission Info Mapping',
    'Submission Info Fields',
    'Tab to focus search',
    'Unknown Error',
    'Update Needed.',
    'Update fluentformpro to get access to import entries.',
    'View',
    'View Entries',
  ];

  public function __construct()
  {
    add_filter('fluentform/admin_i18n', [$this, 'addEntriesPageKeys']);
  }

  /**
   * @param array $i18n Key => translated string map Fluent Forms passes to its scripts.
   * @return array
   */
  public function addEntriesPageKeys($i18n)
  {
    if (!is_array($i18n)) {
      return $i18n;
    }

    foreach (self::ENTRIES_PAGE_KEYS as $key) {
      if (!isset($i18n[$key])) {
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- msgids belong to Fluent Forms' catalogue.
        $i18n[$key] = __($key, 'fluentform');
      }
    }

    return $i18n;
  }
}

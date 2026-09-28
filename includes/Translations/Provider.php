<?php

namespace Antropomorf\Translations;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class Provider
 *
 * Serves this plugin's own translations for third-party plugins from its
 * languages/ folder, taking precedence over wordpress.org language packs.
 * Covers PHP (.mo) and script (.json) translations.
 *
 * @package Antropomorf\Translations
 */
class Provider
{
  private const DOMAINS = ['fluentform', 'fluent-crm'];

  /** Script handles that call wp.i18n but that their plugin never registers translations for. */
  private const SCRIPT_DOMAINS = ['fcrm_editor_custom' => 'fluent-crm'];

  public function __construct()
  {
    // Just-in-time loading never reaches load_translation_file unless a file is found here first.
    add_filter('lang_dir_for_domain', [$this, 'filterLangDir'], 10, 3);
    add_filter('load_translation_file', [$this, 'filterTranslationFile'], 10, 3);
    add_action('wp_enqueue_scripts', [$this, 'setScriptTranslations'], PHP_INT_MAX);
  }

  public function setScriptTranslations(): void
  {
    foreach (self::SCRIPT_DOMAINS as $handle => $domain) {
      if (wp_script_is($handle, 'registered')) {
        wp_set_script_translations($handle, $domain, AMRF_ADMIN_PLUGIN_DIR . '/languages');
      }
    }
  }

  /**
   * @param string|false $path
   * @return string|false
   */
  public function filterLangDir($path, string $domain, string $locale)
  {
    if (!$this->bundledFile($domain, $locale, '.mo')) {
      return $path;
    }

    return AMRF_ADMIN_PLUGIN_DIR . '/languages/';
  }

  public function filterTranslationFile(string $file, string $domain, string $locale): string
  {
    $suffix = str_ends_with($file, '.l10n.php') ? '.l10n.php' : '.mo';

    return $this->bundledFile($domain, $locale, $suffix)
      ?? $this->bundledFile($domain, $locale, '.mo')
      ?? $file;
  }

  private function bundledFile(string $domain, string $locale, string $suffix): ?string
  {
    if (!in_array($domain, self::DOMAINS, true)) {
      return null;
    }

    $file = AMRF_ADMIN_PLUGIN_DIR . "/languages/{$domain}-{$locale}{$suffix}";

    return is_readable($file) ? $file : null;
  }
}

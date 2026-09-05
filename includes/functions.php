<?php

/**
 * Public, non-namespaced function API for themes to consume — a small,
 * stable surface separate from the plugin's internal Antropomorf\* classes,
 * required directly from amrf-site-settings.php (the autoloader only maps class
 * names, not plain functions).
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * This site's business/contact settings — the plugin's equivalent of
 * ptsussis-theme's own ptsussis_get_site_settings(), same return shape
 * (every field key always present, defaulted to '').
 *
 * @return array<string, string>
 */
function amrf_get_site_settings(): array
{
    return \Antropomorf\SiteSettings\Repository::getSettings();
}

/**
 * This site's Swish tab settings (number/amount/message + their own
 * "editable after scanning" toggles) — see includes/Swish/Repository.php.
 * Every field key always present, defaulted to ''.
 *
 * @return array<string, string>
 */
function amrf_get_swish_settings(): array
{
    return \Antropomorf\Swish\Repository::getSettings();
}

/**
 * Swish's own deep-link URL, or '' if no number is set. Typically called
 * with amrf_get_swish_settings()'s own fields, same shape as
 * Swish\FrontendProvider's own localized amrfSwish.swishUrl.
 *
 * @param string $swish_number
 * @param string $amount
 * @param bool   $amount_editable
 * @param string $message
 * @param bool   $message_editable
 */
function amrf_build_swish_url(
    string $swish_number,
    string $amount = '',
    bool $amount_editable = true,
    string $message = '',
    bool $message_editable = true
): string {
    return \Antropomorf\SiteSettings\Swish::buildUrl($swish_number, $amount, $amount_editable, $message, $message_editable);
}

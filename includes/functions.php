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
 * Swish's own deep-link URL for a given Swish number, or '' if blank.
 *
 * @param string $swish_number Typically amrf_get_site_settings()['swish_number'].
 */
function amrf_build_swish_url(string $swish_number): string
{
    return \Antropomorf\SiteSettings\Swish::buildUrl($swish_number);
}

<?php

/**
 * Global helper functions for amrf-admin-panel-settings, required (not
 * autoloaded) directly from amrf-admin.php.
 *
 * !!! RECONSTRUCTED, INCOMPLETE — NOT THE ORIGINAL FILE !!!
 *
 * An accidental `rm -rf` deleted the working copy this file lived in
 * (2026-09-04), and it was never read in full before that — this repo's
 * git history didn't reach back far enough to recover it either (it
 * predates the earliest commit any surviving local clone had). Only
 * amrf_get_site_settings() is reconstructed here, since the plan document
 * this project follows explicitly specifies it 1:1 against
 * ptsussis_get_site_settings(). Likely also missing from the original:
 * an amrf_build_swish_url() global wrapper (see includes/SiteSettings/
 * Swish.php, also missing — no Swish deep-link format was ever read, so
 * nothing was fabricated there). Please review/rebuild this file.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('amrf_get_site_settings')) {
    /**
     * @return array<string, string>
     */
    function amrf_get_site_settings(): array
    {
        return \Antropomorf\SiteSettings\Repository::getSettings();
    }
}

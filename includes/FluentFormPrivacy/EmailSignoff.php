<?php

namespace Antropomorf\FluentFormPrivacy;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EmailSignoff
 *
 * !!! STUB — NOT THE ORIGINAL IMPLEMENTATION !!!
 *
 * An accidental `rm -rf` deleted the working copy this class lived in
 * (2026-09-04), and it was never read in full during the session that was
 * lost — only referenced (FluentFormPrivacy\Bootstrap::register() calling
 * `new EmailSignoff()`). Per the plan document this project follows, the
 * real class personalized WordPress's own privacy-request emails with a
 * person_name filter, reading that value via the SiteSettings module's
 * accessor (Repository::getSettings()['person_name'] in
 * Antropomorf\SiteSettings).
 *
 * This stub is intentionally inert so the plugin doesn't fatal-error on
 * the missing class — needs a real rewrite.
 *
 * @package Antropomorf\FluentFormPrivacy
 */
class EmailSignoff
{
    public function __construct()
    {
        // Intentionally inert — see class docblock.
    }
}

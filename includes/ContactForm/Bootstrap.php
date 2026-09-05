<?php

namespace Antropomorf\ContactForm;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Bootstrap
 *
 * Entry point for the Contact Form module, called once from amrf-admin.php
 * alongside the other modules' Bootstrap::register() calls. Covers the
 * "Contact Forms" Site Settings page (default form + consistency-CSS
 * toggle, and — under its "GDPR" heading — retention scope/duration), the
 * sitewide "#kontakt" lightbox (Modal), and WordPress's own personal-data
 * export/erase tools and privacy-request emails (unchanged from this
 * module's original FluentFormPrivacy name).
 *
 * @package Antropomorf\ContactForm
 */
class Bootstrap
{
    public static function register(): void
    {
        new Provider();
        new Modal();
        new RetentionCron();
        new PrivacyRequests();
        new EmailSignoff();
    }
}

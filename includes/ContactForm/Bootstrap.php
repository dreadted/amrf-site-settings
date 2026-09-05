<?php

namespace Antropomorf\ContactForm;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Bootstrap
 *
 * Entry point for the Contact Form module, called once from amrf-site-settings.php
 * alongside the other modules' Bootstrap::register() calls. Covers the
 * "Contact Forms" and "GDPR" tabs on the shared "Forms" page (Forms\Menu) —
 * default form + consistency-CSS toggle on one, retention scope/duration on
 * the other — the sitewide "#kontakt" lightbox (Modal), and WordPress's own
 * personal-data export/erase tools and privacy-request emails (unchanged
 * from this module's original FluentFormPrivacy name).
 *
 * @package Antropomorf\ContactForm
 */
class Bootstrap
{
    public static function register(): void
    {
        new Provider();
        new Modal();
        new Altcha();
        new RetentionCron();
        new PrivacyRequests();
        new EmailSignoff();
    }
}

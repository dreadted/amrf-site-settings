<?php

namespace Antropomorf\ContactForm;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Bootstrap
 *
 * Entry point for the Contact Form module: the "Contact Forms" and "GDPR"
 * tabs on the shared "Forms" page (Forms\Menu), the sitewide "#kontakt"
 * lightbox (Modal), and WordPress's personal-data export/erase tools and
 * privacy-request emails.
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

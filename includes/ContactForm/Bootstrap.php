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
 * lightbox (Modal), WordPress's personal-data export/erase tools and
 * privacy-request emails, and the [amrf_contact_retention] shortcode for
 * quoting the GDPR tab's own retention setting in a Privacy Policy page.
 *
 * @package Antropomorf\ContactForm
 */
class Bootstrap
{
    public static function register(): void
    {
        new Provider();
        // Modal disconnected — every page now has its own inline contact form. Kept, not deleted.
        // new Modal();
        new Altcha();
        new RetentionCron();
        new PrivacyRequests();
        new EmailSignoff();
        new RetentionShortcode();
    }
}

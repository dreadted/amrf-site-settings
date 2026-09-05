<?php

namespace Antropomorf\ContactForm;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EmailSignoff
 *
 * Personalizes WordPress core's own privacy-request emails (the "please
 * confirm this request" email and, for exports, the "your file is ready"
 * follow-up) with Site Settings' person_name instead of core's default
 * "All at ###SITENAME###" — reads through the SiteSettings module's own
 * accessor now, so this stays generic automatically rather than needing
 * its own copy of that field.
 *
 * @package Antropomorf\ContactForm
 */
class EmailSignoff
{
    public function __construct()
    {
        add_filter('user_request_action_email_content', [$this, 'personalize']);
        add_filter('wp_privacy_personal_data_email_content', [$this, 'personalize']);
    }

    /**
     * Runs before WP core substitutes ###SITENAME###/###SITEURL### into the
     * content, so both the English source and the real Swedish core
     * translation are matched literally rather than trying to match against
     * a sitename that hasn't been substituted in yet.
     */
    public function personalize(string $content): string
    {
        $person_name = amrf_get_site_settings()['person_name'] ?? '';
        $signoff = $person_name !== '' ? $person_name : get_bloginfo('name');

        return str_replace(
            ['All at ###SITENAME###', 'Vi på ###SITENAME###'],
            $signoff,
            $content
        );
    }
}

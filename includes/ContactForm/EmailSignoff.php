<?php

namespace Antropomorf\ContactForm;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EmailSignoff
 *
 * Personalizes WP core's privacy-request emails with Site Settings'
 * person_name instead of core's default "All at ###SITENAME###".
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

<?php

namespace Antropomorf\FluentFormPrivacy;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PrivacyRequests
 *
 * !!! STUB — NOT THE ORIGINAL IMPLEMENTATION !!!
 *
 * An accidental `rm -rf` deleted the working copy this class lived in
 * (2026-09-04), and it was never read in full during the session that was
 * lost — only referenced (FluentFormPrivacy\Bootstrap::register() calling
 * `new PrivacyRequests()`). The real class (per the plan document this
 * project follows) hooked WordPress's own personal-data export/erase
 * request machinery (wp_privacy_personal_data_exporters /
 * wp_privacy_personal_data_erasers filters) to pull matching FluentForm
 * submissions for the forms in Repository::getFormIds() — exporting as a
 * raw field-name => value dump (deliberately not hardcoded to any one
 * form's field names), and erasing by regex-matching an email-like value
 * across all fields rather than a hardcoded 'email' key.
 *
 * Rather than fabricate that logic from guesswork — this handles real
 * personal-data export/erase requests, exactly the kind of code where a
 * plausible-looking but wrong reconstruction is worse than an obvious
 * stub — this class is intentionally inert. It does NOT currently
 * register any exporter/eraser. This needs a real rewrite before GDPR
 * data subject requests can be fulfilled through it again.
 *
 * @package Antropomorf\FluentFormPrivacy
 */
class PrivacyRequests
{
    public function __construct()
    {
        // Intentionally inert — see class docblock.
    }
}

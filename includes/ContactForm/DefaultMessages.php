<?php

namespace Antropomorf\ContactForm;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DefaultMessages
 *
 * Falls back to FluentForm's own translated default for any global
 * validation message saved blank in Global Settings → Miscellaneous.
 *
 * @package Antropomorf\ContactForm
 */
class DefaultMessages
{
    public function __construct()
    {
        add_filter('fluentform/global_default_messages', [$this, 'fillBlankMessages']);
    }

    /**
     * FluentForm array_merge()s saved messages over its defaults, so a blank
     * saved value otherwise renders an empty error and blocks submission.
     *
     * @param array<string, string> $messages
     * @return array<string, string>
     */
    public function fillBlankMessages($messages)
    {
        $helper = '\FluentForm\App\Helpers\Helper';
        if (!is_array($messages) || !method_exists($helper, 'globalDefaultMessageSettingFields')) {
            return $messages;
        }

        foreach ($helper::globalDefaultMessageSettingFields() as $key => $field) {
            if (trim((string) ($messages[$key] ?? '')) === '') {
                $messages[$key] = $field['value'];
            }
        }

        return $messages;
    }
}

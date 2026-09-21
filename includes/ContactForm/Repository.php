<?php

namespace Antropomorf\ContactForm;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Repository
 *
 * Storage/defaults/sanitization for the Contact Forms + GDPR settings:
 * default contact form, consistency CSS, ALTCHA spam protection
 * (ContactForm\Altcha), retention scope/duration, and the ALTCHA HMAC key
 * (getAltchaHmacKey()).
 *
 * OPTION_NAME stays 'amrf_fluentform_privacy' to avoid dropping existing
 * sites' saved settings on upgrade.
 *
 * @package Antropomorf\ContactForm
 */
class Repository
{
    public const OPTION_NAME = 'amrf_fluentform_privacy';

    /** Site baseline for FluentForm's own _fluentform_global_form_settings['misc'], applied on demand (see applyFluentFormBaseline()). */
    private const FLUENTFORM_BASELINE = [
        'isIpLogingDisabled' => true,
        'isAnalyticsDisabled' => false,
        'honeypotStatus' => 'yes',
        'tokenBasedProtectionStatus' => 'yes',
        'classicEditorButton' => 'no',
        'noConflictStatus' => 'yes',
        'tabIndex' => 'no',
    ];

    /** The form this site baseline is written onto (see applyContactFormBaseline()). */
    private const CONTACT_FORM_ID = 1;

    private const CONTACT_FORM_TITLE = 'Kontaktformulär';

    /** Site baseline for form CONTACT_FORM_ID's fields — excludes this site's own site-specific "interest" dropdown. */
    private const FLUENTFORM_CONTACT_FORM_FIELDS = [
        'fields' => [
            [
                'index' => 0,
                'element' => 'input_name',
                'attributes' => [
                    'name' => 'names',
                    'data-type' => 'name-element',
                ],
                'settings' => [
                    'container_class' => '',
                    'admin_field_label' => 'Namn',
                    'conditional_logics' => [],
                    'label_placement' => '',
                ],
                'fields' => [
                    'first_name' => [
                        'element' => 'input_text',
                        'attributes' => [
                            'type' => 'text',
                            'name' => 'first_name',
                            'value' => '',
                            'id' => '',
                            'class' => '',
                            'placeholder' => '',
                        ],
                        'settings' => [
                            'container_class' => '',
                            'label' => 'Förnamn',
                            'help_message' => '',
                            'visible' => true,
                            'validation_rules' => [
                                'required' => [
                                    'value' => true,
                                    'message' => 'This field is required',
                                    'global' => true,
                                    'global_message' => 'This field is required',
                                ],
                            ],
                            'conditional_logics' => [],
                        ],
                        'editor_options' => [
                            'template' => 'inputText',
                        ],
                    ],
                    'middle_name' => [
                        'element' => 'input_text',
                        'attributes' => [
                            'type' => 'text',
                            'name' => 'middle_name',
                            'value' => '',
                            'id' => '',
                            'class' => '',
                            'placeholder' => '',
                            'required' => false,
                        ],
                        'settings' => [
                            'container_class' => '',
                            'label' => 'Middle Name',
                            'help_message' => '',
                            'error_message' => '',
                            'visible' => false,
                            'validation_rules' => [
                                'required' => [
                                    'value' => false,
                                    'message' => 'This field is required',
                                    'global' => false,
                                    'global_message' => 'This field is required',
                                ],
                            ],
                            'conditional_logics' => [],
                        ],
                        'editor_options' => [
                            'template' => 'inputText',
                        ],
                    ],
                    'last_name' => [
                        'element' => 'input_text',
                        'attributes' => [
                            'type' => 'text',
                            'name' => 'last_name',
                            'value' => '',
                            'id' => '',
                            'class' => '',
                            'placeholder' => '',
                            'required' => false,
                        ],
                        'settings' => [
                            'container_class' => '',
                            'label' => 'Efternamn',
                            'help_message' => '',
                            'error_message' => '',
                            'visible' => true,
                            'validation_rules' => [
                                'required' => [
                                    'value' => false,
                                    'message' => 'This field is required',
                                    'global' => false,
                                    'global_message' => 'This field is required',
                                ],
                            ],
                            'conditional_logics' => [],
                        ],
                        'editor_options' => [
                            'template' => 'inputText',
                        ],
                    ],
                ],
                'editor_options' => [
                    'title' => 'Name Fields',
                    'element' => 'name-fields',
                    'icon_class' => 'ff-edit-name',
                    'template' => 'nameFields',
                ],
                'uniqElKey' => 'el_1570866006692',
            ],
            [
                'index' => 1,
                'element' => 'input_email',
                'attributes' => [
                    'type' => 'email',
                    'name' => 'email',
                    'value' => '',
                    'id' => '',
                    'class' => '',
                    'placeholder' => '',
                ],
                'settings' => [
                    'container_class' => '',
                    'label' => 'E-postadress',
                    'label_placement' => '',
                    'help_message' => '',
                    'admin_field_label' => 'E-postadress',
                    'validation_rules' => [
                        'required' => [
                            'value' => true,
                            'message' => 'This field is required',
                            'global' => true,
                            'global_message' => 'This field is required',
                        ],
                        'email' => [
                            'value' => true,
                            'message' => 'This field must contain a valid email',
                            'global' => false,
                            'global_message' => 'This field must contain a valid email',
                        ],
                    ],
                    'conditional_logics' => [],
                    'is_unique' => 'no',
                    'unique_validation_message' => 'Email address need to be unique.',
                    'prefix_label' => '',
                    'suffix_label' => '',
                ],
                'editor_options' => [
                    'title' => 'Email Address',
                    'icon_class' => 'ff-edit-email',
                    'template' => 'inputText',
                ],
                'uniqElKey' => 'el_1570866012914',
            ],
            [
                'index' => 3,
                'element' => 'textarea',
                'attributes' => [
                    'name' => 'message',
                    'value' => '',
                    'id' => '',
                    'class' => '',
                    'placeholder' => '',
                    'rows' => '1',
                    'cols' => 2,
                    'maxlength' => '',
                ],
                'settings' => [
                    'container_class' => '',
                    'label' => 'Meddelande',
                    'admin_field_label' => 'Meddelande',
                    'label_placement' => '',
                    'help_message' => '',
                    'validation_rules' => [
                        'required' => [
                            'value' => true,
                            'message' => 'This field is required',
                            'global' => true,
                            'global_message' => 'This field is required',
                        ],
                    ],
                    'conditional_logics' => [
                        'type' => 'any',
                        'status' => false,
                        'conditions' => [
                            ['field' => '', 'value' => '', 'operator' => ''],
                        ],
                    ],
                    'prefix_label' => '',
                    'suffix_label' => '',
                ],
                'editor_options' => [
                    'title' => 'Text Area',
                    'icon_class' => 'ff-edit-textarea',
                    'template' => 'inputTextarea',
                ],
                'uniqElKey' => 'el_1570879001207',
            ],
        ],
        'submitButton' => [
            'uniqElKey' => 'el_1524065200616',
            'element' => 'button',
            'attributes' => [
                'type' => 'submit',
                'class' => '',
            ],
            'settings' => [
                'align' => 'left',
                'button_style' => 'default',
                'container_class' => '',
                'help_message' => '',
                'background_color' => '#1a7efb',
                'button_size' => 'md',
                'color' => '#ffffff',
                'button_ui' => [
                    'type' => 'default',
                    'text' => 'Skicka',
                    'img_url' => '',
                ],
                'normal_styles' => [
                    'backgroundColor' => '#1a7efb',
                    'borderColor' => '#1a7efb',
                    'color' => '#ffffff',
                    'borderRadius' => '',
                    'minWidth' => '',
                ],
                'hover_styles' => [
                    'backgroundColor' => '#ffffff',
                    'borderColor' => '#1a7efb',
                    'color' => '#1a7efb',
                    'borderRadius' => '',
                    'minWidth' => '',
                ],
                'current_state' => 'normal_styles',
            ],
            'editor_options' => [
                'title' => 'Submit Button',
            ],
        ],
    ];

    /** Site baseline for form CONTACT_FORM_ID's formSettings['confirmation']. */
    private const FLUENTFORM_CONFIRMATION_BASELINE = [
        'redirectTo' => 'samePage',
        'messageToShow' => '<h3>Tack för ditt meddelande,<br />jag hör av mig snarast möjligt!</h3>',
        'customPage' => null,
        'samePageFormBehavior' => 'hide_form',
        'customUrl' => null,
    ];

    /** Local part of the baseline FluentSMTP sender address — combined with this site's own domain at apply time. */
    private const FLUENTSMTP_SENDER_LOCAL_PART = 'kontakt';

    /** Site baseline for a FluentSMTP connection's provider_settings — minus sender_email, which is domain-dependent (see applyFluentSmtpBaseline()). */
    private const FLUENTSMTP_CONNECTION_BASELINE = [
        'provider' => 'smtp',
        'sender_name' => 'Kontaktformulär',
        'force_from_name' => 'yes',
        'force_from_email' => 'yes',
        'return_path' => 'yes',
        'host' => 'mailhog',
        'port' => '1025',
        'auth' => 'no',
        'username' => null,
        'password' => null,
        'auto_tls' => 'yes',
        'encryption' => 'none',
        'key_store' => 'db',
    ];

    /** Site baseline for form CONTACT_FORM_ID's single email notification feed. */
    private const FLUENTFORM_NOTIFICATION_BASELINE = [
        'name' => 'Kontaktformulär',
        'sendTo' => [
            'type' => 'email',
            'email' => '{wp.admin_email}',
            'field' => null,
            'routing' => [
                ['input_value' => '', 'field' => null, 'operator' => '=', 'value' => null],
            ],
        ],
        'fromName' => '',
        'fromEmail' => '',
        'replyTo' => '',
        'bcc' => '',
        'subject' => 'Nytt meddelande från {inputs.email}',
        'message' => '<p>{all_data}</p>',
        'conditionals' => [
            'status' => false,
            'type' => 'all',
            'conditions' => [
                ['field' => null, 'operator' => '=', 'value' => null],
            ],
        ],
        'enabled' => true,
        'pdf_attachments' => [],
        'attachments' => [],
        'media_attachments' => [],
        'feed_trigger_event' => 'payment_success',
    ];

    /**
     * @return array{default_contact_form_id: string, enable_consistent_styling: bool, altcha_enabled: bool, contact_form_ids: int[], retention_days: string}
     */
    public static function getDefaults(): array
    {
        return [
            // '0' = "None" — Modal no-ops sitewide (see getDefaultContactFormId()).
            'default_contact_form_id' => '0',
            'enable_consistent_styling' => false,
            // On by default; a site can opt out for its own spam protection.
            'altcha_enabled' => true,
            'contact_form_ids' => [],
            'retention_days' => '',
        ];
    }

    /**
     * @return array{default_contact_form_id: string, enable_consistent_styling: bool, altcha_enabled: bool, contact_form_ids: int[], retention_days: string}
     */
    public static function getSettings(): array
    {
        $stored = get_option(self::OPTION_NAME, []);
        $stored = is_array($stored) ? $stored : [];

        // One-time migration: legacy comma-separated 'form_ids' -> int[] 'contact_form_ids'.
        if (!array_key_exists('contact_form_ids', $stored) && array_key_exists('form_ids', $stored)) {
            $ids = array_map('absint', explode(',', (string) $stored['form_ids']));
            $stored['contact_form_ids'] = array_values(array_unique(array_filter($ids)));
            unset($stored['form_ids']);
            update_option(self::OPTION_NAME, $stored);
        }

        return wp_parse_args($stored, self::getDefaults());
    }

    /**
     * @param mixed $input Raw POSTed value for this option.
     * @return array{default_contact_form_id: string, enable_consistent_styling: bool, altcha_enabled: bool, contact_form_ids: int[], retention_days: string}
     */
    public static function sanitize($input): array
    {
        $input = is_array($input) ? $input : [];
        $current = self::getSettings();

        // One-shot action, not a stored setting — deliberately absent from $output below.
        if (array_key_exists('apply_fluentform_baseline_submitted', $input) && !empty($input['apply_fluentform_baseline'])) {
            self::applyFluentFormBaseline();
        }

        $output = [
            'default_contact_form_id' => (string) absint($input['default_contact_form_id'] ?? $current['default_contact_form_id']),
            'retention_days' => (string) absint($input['retention_days'] ?? ''),
        ];

        // "_submitted" marker disambiguates "not submitted" from "submitted, all unchecked".
        $output['enable_consistent_styling'] = array_key_exists('enable_consistent_styling_submitted', $input)
            ? !empty($input['enable_consistent_styling'])
            : $current['enable_consistent_styling'];

        $output['altcha_enabled'] = array_key_exists('altcha_enabled_submitted', $input)
            ? !empty($input['altcha_enabled'])
            : $current['altcha_enabled'];

        if (array_key_exists('contact_form_ids_submitted', $input)) {
            $ids = isset($input['contact_form_ids']) && is_array($input['contact_form_ids'])
                ? array_map('absint', $input['contact_form_ids'])
                : [];
            $output['contact_form_ids'] = array_values(array_unique(array_filter($ids)));
        } else {
            $output['contact_form_ids'] = $current['contact_form_ids'];
        }

        return $output;
    }

    /**
     * @return int Positive form ID, or 0 if unset (no form to show in the
     *             "#contact" lightbox).
     */
    public static function getDefaultContactFormId(): int
    {
        return absint(self::getSettings()['default_contact_form_id']);
    }

    public static function isConsistentStylingEnabled(): bool
    {
        return self::getSettings()['enable_consistent_styling'];
    }

    public static function isAltchaEnabled(): bool
    {
        return self::getSettings()['altcha_enabled'];
    }

    /**
     * Auto-generated once per site, stored invisibly — bypasses
     * sanitize()/the Settings API since it's never part of a form submission.
     *
     * @return string
     */
    public static function getAltchaHmacKey(): string
    {
        $stored = get_option(self::OPTION_NAME, []);
        $stored = is_array($stored) ? $stored : [];

        if (empty($stored['altcha_hmac_key'])) {
            $stored['altcha_hmac_key'] = bin2hex(random_bytes(32));
            update_option(self::OPTION_NAME, $stored);
        }

        return $stored['altcha_hmac_key'];
    }

    /**
     * @return int[] Form IDs the retention cron and personal-data export/
     *               erase requests apply to.
     */
    public static function getContactFormIds(): array
    {
        return self::getSettings()['contact_form_ids'];
    }

    public static function getRetentionDays(): int
    {
        return absint(self::getSettings()['retention_days']);
    }

    /**
     * Overwrites FluentForm's own misc settings with this site's fixed
     * baseline, regardless of their current values — a different option
     * than self::OPTION_NAME, so this never re-enters this class's own
     * sanitize() filter.
     *
     * @return void
     */
    public static function applyFluentFormBaseline(): void
    {
        $settings = get_option('_fluentform_global_form_settings', []);
        $settings = is_array($settings) ? $settings : [];
        $settings['misc'] = array_merge($settings['misc'] ?? [], self::FLUENTFORM_BASELINE);
        update_option('_fluentform_global_form_settings', $settings);

        self::applyContactFormBaseline();

        if (defined('FLUENTMAIL')) {
            self::applyFluentSmtpBaseline();
        }
    }

    /** Same one-shot, overwrite-regardless-of-current-values contract as applyFluentFormBaseline(); merges into existing connections rather than replacing them. */
    private static function applyFluentSmtpBaseline(): void
    {
        $domain = wp_parse_url(home_url(), PHP_URL_HOST);
        if (!$domain) {
            return;
        }

        $senderEmail = self::FLUENTSMTP_SENDER_LOCAL_PART . '@' . $domain;
        // Same key FluentSMTP itself derives for a connection — see Settings::generateUniqueKey().
        $key = md5($senderEmail);

        $settings = get_option('fluentmail-settings', []);
        $settings = is_array($settings) ? $settings : [];

        $settings['connections'][$key] = [
            'title' => 'SMTP Server',
            'provider_settings' => array_merge(self::FLUENTSMTP_CONNECTION_BASELINE, ['sender_email' => $senderEmail]),
        ];
        $settings['mappings'][$senderEmail] = $key;
        $settings['misc'] = array_merge($settings['misc'] ?? [], ['default_connection' => $key]);

        update_option('fluentmail-settings', $settings);
    }

    /** Same one-shot, overwrite-regardless-of-current-values contract as applyFluentFormBaseline(); no-ops if the form doesn't exist. */
    private static function applyContactFormBaseline(): void
    {
        global $wpdb;
        $formsTable = $wpdb->prefix . 'fluentform_forms';
        $metaTable = $wpdb->prefix . 'fluentform_form_meta';
        $formId = self::CONTACT_FORM_ID;

        if (!$wpdb->get_var($wpdb->prepare("SELECT id FROM {$formsTable} WHERE id = %d", $formId))) {
            return;
        }

        $wpdb->update(
            $formsTable,
            [
                'title' => self::CONTACT_FORM_TITLE,
                'form_fields' => wp_json_encode(self::FLUENTFORM_CONTACT_FORM_FIELDS),
            ],
            ['id' => $formId]
        );

        $settingsRow = $wpdb->get_row($wpdb->prepare(
            "SELECT id, value FROM {$metaTable} WHERE form_id = %d AND meta_key = 'formSettings' LIMIT 1",
            $formId
        ));
        $formSettings = $settingsRow ? json_decode((string) $settingsRow->value, true) : null;
        $formSettings = is_array($formSettings) ? $formSettings : [];
        $formSettings['confirmation'] = self::FLUENTFORM_CONFIRMATION_BASELINE;
        $encodedSettings = wp_json_encode($formSettings);

        if ($settingsRow) {
            $wpdb->update($metaTable, ['value' => $encodedSettings], ['id' => $settingsRow->id]);
        } else {
            $wpdb->insert($metaTable, ['form_id' => $formId, 'meta_key' => 'formSettings', 'value' => $encodedSettings]);
        }

        $notificationId = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$metaTable} WHERE form_id = %d AND meta_key = 'notifications' LIMIT 1",
            $formId
        ));
        $encodedNotification = wp_json_encode(self::FLUENTFORM_NOTIFICATION_BASELINE);

        if ($notificationId) {
            $wpdb->update($metaTable, ['value' => $encodedNotification], ['id' => $notificationId]);
        } else {
            $wpdb->insert($metaTable, ['form_id' => $formId, 'meta_key' => 'notifications', 'value' => $encodedNotification]);
        }
    }
}

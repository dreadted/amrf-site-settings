<?php

namespace Antropomorf\ContactForm;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * The site's one-shot FluentForm, FluentSMTP and FluentCRM baseline, applied
 * from the "Apply Recommended FluentForm Settings" toggle on the Contact Forms tab.
 *
 * @package Antropomorf\ContactForm
 */
class FluentFormBaseline
{
	/** Site baseline for FluentForm's own _fluentform_global_form_settings['misc'], applied on demand (see apply()). */
	private const FLUENTFORM_BASELINE = [
		'isIpLogingDisabled' => false,
		'isAnalyticsDisabled' => true,
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
							'global' => true,
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
				'align' => 'right',
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

	/** Newsletter opt-in checkbox appended to CONTACT_FORM_ID's fields when FluentCRM is active, minus its option label (see getNewsletterOptinField()). */
	private const FLUENTFORM_NEWSLETTER_OPTIN_FIELD = [
		'index' => 9,
		'element' => 'input_checkbox',
		'attributes' => [
			'type' => 'checkbox',
			'name' => Repository::NEWSLETTER_OPTIN_FIELD_NAME,
			'value' => [],
		],
		'settings' => [
			'dynamic_default_value' => '',
			'container_class' => '',
			'label' => 'Nyhetsbrev',
			'admin_field_label' => 'Nyhetsbrev',
			'label_placement' => 'hide_label',
			'display_type' => '',
			'help_message' => '',
			'advanced_options' => [],
			'calc_value_status' => false,
			'enable_image_input' => false,
			'values_visible' => true,
			'randomize_options' => 'no',
			'enable_other_option' => 'no',
			'other_option_label' => 'Other',
			'other_option_placeholder' => 'Please specify...',
			'other_option_required_message' => 'Please specify a value for the selected "Other" option',
			'validation_rules' => [
				'required' => [
					'value' => false,
					'message' => 'Detta fält är obligatoriskt',
					'global_message' => 'Detta fält är obligatoriskt',
					'global' => true,
				],
				'max_selection' => [
					'value' => '',
					'message' => 'Du har valt fler alternativ än tillåtet',
					'global_message' => 'Du har valt fler alternativ än tillåtet',
					'global' => true,
				],
				'min_selection' => [
					'value' => '',
					'message' => 'Välj minst det lägsta tillåtna antalet alternativ',
					'global_message' => 'Välj minst det lägsta tillåtna antalet alternativ',
					'global' => true,
				],
			],
			'conditional_logics' => [
				'type' => 'any',
				'status' => false,
				'conditions' => [
					['field' => '', 'value' => '', 'operator' => ''],
				],
			],
			'layout_class' => '',
		],
		'editor_options' => [
			'title' => 'Checkbox',
			'icon_class' => 'ff-edit-checkbox-1',
			'template' => 'inputCheckable',
		],
		'uniqElKey' => 'el_1790519812526',
	];

	private const NEWSLETTER_LIST_SLUG = 'nyhetsbrev';

	private const NEWSLETTER_LIST_TITLE = 'Nyhetsbrev';

	/** Site baseline for CONTACT_FORM_ID's FluentCRM feed, minus list_id (resolved per site). */
	private const FLUENTCRM_FEED_BASELINE = [
		'name' => 'FluentCRM Integration Feed',
		'first_name' => '{inputs.names.first_name}',
		'last_name' => '{inputs.names.last_name}',
		'full_name' => '',
		'email' => 'email',
		'other_fields' => [
			['item_value' => '', 'label' => ''],
		],
		'tag_ids' => [],
		'tag_ids_selection_type' => 'simple',
		'tag_routers' => [],
		'skip_if_exists' => false,
		'double_opt_in' => false,
		'force_subscribe' => false,
		'skip_primary_data' => false,
		'conditionals' => [
			'conditions' => [
				['field' => Repository::NEWSLETTER_OPTIN_FIELD_NAME, 'operator' => '=', 'value' => Repository::NEWSLETTER_OPTIN_VALUE],
			],
			'status' => true,
			'type' => 'all',
		],
		'run_events_only' => [],
		'remove_tags' => [],
		'enabled' => true,
		'CustomFields' => [],
		'default_fields' => [],
	];

	/** Site baseline for form CONTACT_FORM_ID's formSettings['confirmation'], minus messageToShow (see getConfirmationMessage()). */
	private const FLUENTFORM_CONFIRMATION_BASELINE = [
		'redirectTo' => 'samePage',
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
	 * Overwrites FluentForm's misc settings with the fixed baseline; a separate option, so sanitize() isn't re-entered.
	 *
	 * @return void
	 */
	public static function apply(): void
	{
		$settings = get_option('_fluentform_global_form_settings', []);
		$settings = is_array($settings) ? $settings : [];
		$settings['misc'] = array_merge($settings['misc'] ?? [], self::FLUENTFORM_BASELINE);
		$messages = self::getSiteLocaleDefaultMessages();
		if ($messages) {
			$settings['default_messages'] = $messages;
		}
		update_option('_fluentform_global_form_settings', $settings);

		self::applyContactFormBaseline();

		if (defined('FLUENTMAIL')) {
			self::applyFluentSmtpBaseline();
		}
	}

	/**
	 * FluentForm's default validation messages translated into the site locale,
	 * since its saved copies otherwise freeze the saving admin's own locale.
	 *
	 * @return array<string, string> Keyed like FluentForm's default_messages; [] if FluentForm is missing.
	 */
	private static function getSiteLocaleDefaultMessages(): array
	{
		$helper = '\FluentForm\App\Helpers\Helper';
		if (!method_exists($helper, 'globalDefaultMessageSettingFields')) {
			return [];
		}

		$switched = switch_to_locale(get_locale());
		$messages = wp_list_pluck($helper::globalDefaultMessageSettingFields(), 'value');
		if ($switched) {
			restore_previous_locale();
		}

		return $messages;
	}

	/** Seeds a Mailhog connection on a local site only, and only while none exists, so a real mail setup is never overwritten. */
	private static function applyFluentSmtpBaseline(): void
	{
		if (wp_get_environment_type() !== 'local') {
			return;
		}

		$settings = get_option('fluentmail-settings', []);
		$settings = is_array($settings) ? $settings : [];
		if (!empty($settings['connections'])) {
			return;
		}

		$domain = wp_parse_url(home_url(), PHP_URL_HOST);
		if (!$domain) {
			return;
		}

		$senderEmail = self::FLUENTSMTP_SENDER_LOCAL_PART . '@' . $domain;
		// Same key FluentSMTP itself derives for a connection — see Settings::generateUniqueKey().
		$key = md5($senderEmail);

		$settings['connections'][$key] = [
			'title' => 'SMTP Server',
			'provider_settings' => array_merge(self::FLUENTSMTP_CONNECTION_BASELINE, ['sender_email' => $senderEmail]),
		];
		$settings['mappings'][$senderEmail] = $key;
		$settings['misc'] = array_merge($settings['misc'] ?? [], ['default_connection' => $key]);

		update_option('fluentmail-settings', $settings);
	}

	/** Links the privacy notice to the site's own privacy policy page, and drops it if none is set. */
	private static function getConfirmationMessage(): string
	{
		$message = '<h3>Tack för ditt meddelande,<br />jag hör av mig snarast möjligt!</h3>';
		$privacyUrl = get_privacy_policy_url();
		if (!$privacyUrl) {
			return $message;
		}

		return $message . "\n<p>&nbsp;</p>\n" . sprintf(
			'<p><small>Vi behandlar dina personuppgifter för att kunna svara på ditt meddelande. Läs mer i vår <a href="%s">integritetspolicy</a>.</small></p>',
			esc_url($privacyUrl)
		);
	}

	/** Opt-in option label naming this site's business. */
	private static function getNewsletterOptinField(): array
	{
		$name = amrf_get_site_settings()['business_name'] ?: get_bloginfo('name');
		$field = self::FLUENTFORM_NEWSLETTER_OPTIN_FIELD;
		$field['settings']['advanced_options'] = [
			[
				'label' => sprintf('Ja tack, jag vill få nyheter och erbjudanden från %s via e-post.', $name),
				'value' => Repository::NEWSLETTER_OPTIN_VALUE,
				'calc_value' => '',
				'image' => '',
				'id' => 1790519812526,
			],
		];

		return $field;
	}

	/**
	 * @return int The newsletter list's ID, created if missing; 0 if FluentCRM isn't active.
	 */
	private static function getNewsletterListId(): int
	{
		global $wpdb;
		if (!defined('FLUENTCRM')) {
			return 0;
		}

		$listsTable = $wpdb->prefix . 'fc_lists';
		$listId = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$listsTable} WHERE slug = %s", self::NEWSLETTER_LIST_SLUG));
		if ($listId) {
			return $listId;
		}

		$now = current_time('mysql');
		$inserted = $wpdb->insert($listsTable, [
			'title' => self::NEWSLETTER_LIST_TITLE,
			'slug' => self::NEWSLETTER_LIST_SLUG,
			'is_public' => 0,
			'created_at' => $now,
			'updated_at' => $now,
		]);

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	private static function upsertFormMeta(int $formId, string $key, string $value): void
	{
		global $wpdb;
		$metaTable = $wpdb->prefix . 'fluentform_form_meta';

		$id = $wpdb->get_var($wpdb->prepare(
			"SELECT id FROM {$metaTable} WHERE form_id = %d AND meta_key = %s LIMIT 1",
			$formId,
			$key
		));

		if ($id) {
			$wpdb->update($metaTable, ['value' => $value], ['id' => $id]);
		} else {
			$wpdb->insert($metaTable, ['form_id' => $formId, 'meta_key' => $key, 'value' => $value]);
		}
	}

	/** Same one-shot, overwrite-regardless-of-current-values contract as apply(); no-ops if the form doesn't exist. */
	private static function applyContactFormBaseline(): void
	{
		global $wpdb;
		$formsTable = $wpdb->prefix . 'fluentform_forms';
		$metaTable = $wpdb->prefix . 'fluentform_form_meta';
		$formId = self::CONTACT_FORM_ID;

		if (!$wpdb->get_var($wpdb->prepare("SELECT id FROM {$formsTable} WHERE id = %d", $formId))) {
			return;
		}

		$fields = self::FLUENTFORM_CONTACT_FORM_FIELDS;
		$newsletterListId = self::getNewsletterListId();
		if ($newsletterListId) {
			$fields['fields'][] = self::getNewsletterOptinField();
		}

		$wpdb->update(
			$formsTable,
			[
				'title' => self::CONTACT_FORM_TITLE,
				'form_fields' => wp_json_encode($fields),
			],
			['id' => $formId]
		);

		$settingsValue = $wpdb->get_var($wpdb->prepare(
			"SELECT value FROM {$metaTable} WHERE form_id = %d AND meta_key = 'formSettings' LIMIT 1",
			$formId
		));
		$formSettings = $settingsValue ? json_decode((string) $settingsValue, true) : null;
		$formSettings = is_array($formSettings) ? $formSettings : [];
		$formSettings['confirmation'] = array_merge(self::FLUENTFORM_CONFIRMATION_BASELINE, [
			'messageToShow' => self::getConfirmationMessage(),
		]);
		self::upsertFormMeta($formId, 'formSettings', wp_json_encode($formSettings));

		self::upsertFormMeta($formId, 'notifications', wp_json_encode(self::FLUENTFORM_NOTIFICATION_BASELINE));

		if ($newsletterListId) {
			$feed = array_merge(self::FLUENTCRM_FEED_BASELINE, ['list_id' => (string) $newsletterListId]);
			self::upsertFormMeta($formId, 'fluentcrm_feeds', wp_json_encode($feed));
		}
	}
}

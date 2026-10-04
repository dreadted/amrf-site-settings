<?php

namespace Antropomorf\ContactForm;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Contact Form module: Forms tabs, #contact modal, privacy export/erase and the retention shortcode.
 *
 * @package Antropomorf\ContactForm
 */
class Bootstrap
{
	public static function register(): void
	{
		new Provider();
		// No-ops itself wherever no form resolves — Default Contact Form is
		// "None" (Repository::getDefaultContactFormId() === 0) and nothing
		// filters 'amrf_contact_modal_form_id' to a real form ID either.
		new Modal();
		new Altcha();
		new RetentionCron();
		new PrivacyRequests();
		new EmailSignoff();
		new RetentionShortcode();
		new DefaultMessages();
	}
}

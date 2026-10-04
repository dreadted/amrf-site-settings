<?php

namespace Antropomorf\FluentCrm;

use Antropomorf\ContactForm\Repository as ContactFormRepository;
use FluentCrm\App\Models\SubscriberNote;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Logs newsletter opt-ins and unsubscribes as notes on the FluentCRM contact, so proof of consent outlives the submission.
 * An opt-in from an unsubscribed contact sends a confirmation email instead, unless tagged "Do not contact".
 *
 * @package Antropomorf\FluentCrm
 */
class ConsentLog
{
	public function __construct()
	{
		add_action('plugins_loaded', [$this, 'register']);
	}

	public function register(): void
	{
		if (!defined('FLUENTCRM')) {
			return;
		}

		$logOptin = fn($subscriber, $entry, $form) => SiteLocale::run(fn() => $this->logOptin($subscriber, $entry, $form));
		add_action('fluent_crm/contact_added_by_fluentform', $logOptin, 10, 3);
		add_action('fluent_crm/contact_updated_by_fluentform', $logOptin, 10, 3);
		add_action('fluent_crm/subscriber_status_changed', fn($subscriber, $old, $new) => SiteLocale::run(fn() => $this->logUnsubscribe($subscriber, $old, $new)), 10, 3);
	}

	/**
	 * @param \FluentCrm\App\Models\Subscriber $subscriber
	 * @param object $entry FluentForm submission.
	 * @param object $form  FluentForm form.
	 */
	public function logOptin($subscriber, $entry, $form): void
	{
		if (!$this->hasOptin((string) $entry->response)) {
			return;
		}

		if (DoNotContact::isTagged($subscriber)) {
			// The feed has just written the submitted name and list back onto the contact.
			DoNotContact::enforce($subscriber);
			$this->addNote($subscriber, __('Newsletter opt-in ignored', 'amrf-admin'), __('The contact is tagged "Do not contact", so no confirmation email was sent.', 'amrf-admin'), $entry->created_at);
			return;
		}

		if ($subscriber->status === 'subscribed') {
			$this->addNote($subscriber, __('Newsletter consent', 'amrf-admin'), $this->describeOptin($entry, $form), $entry->created_at);
			return;
		}

		// The feed leaves unsubscribed contacts unsubscribed (force_subscribe off); only the owner's confirmation click re-subscribes.
		if ($subscriber->status !== 'unsubscribed') {
			return;
		}

		$outcome = $subscriber->sendDoubleOptinEmail()
			? __('A confirmation email was sent; the contact is subscribed again only after clicking its link.', 'amrf-admin')
			: __('No confirmation email was sent (one was sent less than 150 seconds ago, or the double opt-in email isn\'t configured).', 'amrf-admin');

		$this->addNote($subscriber, __('Newsletter re-subscription requested', 'amrf-admin'), $this->describeOptin($entry, $form) . '<br>' . $outcome, $entry->created_at);
	}

	/**
	 * @param \FluentCrm\App\Models\Subscriber $subscriber
	 */
	public function logUnsubscribe($subscriber, string $oldStatus, string $newStatus): void
	{
		if ($newStatus !== 'unsubscribed') {
			return;
		}

		// FluentCRM's own unsubscribe page logs a note with IP and reason already.
		if (wp_doing_ajax() && ($_REQUEST['action'] ?? '') === 'fluentcrm_unsubscribe_ajax') {
			return;
		}

		$user = wp_get_current_user();
		$description = $user->exists()
			/* translators: %s: name of the logged-in user who changed the status */
			? sprintf(__('Unsubscribed manually by %s.', 'amrf-admin'), esc_html($user->display_name))
			: __('Unsubscribed without the unsubscribe page, e.g. by one-click unsubscribe in the email client.', 'amrf-admin');

		$this->addNote($subscriber, __('Unsubscribed', 'amrf-admin'), $description);
	}

	/** System-log notes are the ones PrivacyEraser keeps. */
	private function addNote($subscriber, string $title, string $description, ?string $createdAt = null): void
	{
		SubscriberNote::create(array_filter([
			'subscriber_id' => $subscriber->id,
			'type' => 'system_log',
			'title' => $title,
			'description' => $description,
			'created_at' => $createdAt,
		]));
	}

	private function describeOptin($entry, $form): string
	{
		return sprintf(
			/* translators: 1: form title, 2: page URL, 3: IP address, 4: opt-in checkbox label */
			__('Opted in via the form "%1$s" on %2$s from IP address %3$s.<br>Consent text: "%4$s"', 'amrf-admin'),
			esc_html($form->title),
			esc_html($entry->source_url ?: '-'),
			esc_html($entry->ip ?: '-'),
			esc_html($this->getOptinLabel((string) $form->form_fields))
		);
	}

	private function hasOptin(string $response): bool
	{
		$data = json_decode($response, true);
		$values = (array) ($data[ContactFormRepository::NEWSLETTER_OPTIN_FIELD_NAME] ?? []);

		return in_array(ContactFormRepository::NEWSLETTER_OPTIN_VALUE, $values, true);
	}

	/** The label as the form showed it at submission time, since the baseline can change it later. */
	private function getOptinLabel(string $formFields): string
	{
		$field = $this->findField(json_decode($formFields, true) ?: [], ContactFormRepository::NEWSLETTER_OPTIN_FIELD_NAME);

		foreach ($field['settings']['advanced_options'] ?? [] as $option) {
			if (($option['value'] ?? null) === ContactFormRepository::NEWSLETTER_OPTIN_VALUE) {
				return wp_strip_all_tags((string) ($option['label'] ?? ''));
			}
		}

		return '-';
	}

	/** Recurses into containers, whose columns nest their own field lists. */
	private function findField(array $node, string $name): ?array
	{
		if (($node['attributes']['name'] ?? null) === $name) {
			return $node;
		}

		foreach ($node as $child) {
			if (is_array($child) && ($found = $this->findField($child, $name))) {
				return $found;
			}
		}

		return null;
	}
}

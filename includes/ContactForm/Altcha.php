<?php

namespace Antropomorf\ContactForm;

use AltchaOrg\Altcha\Algorithm\Sha;
use AltchaOrg\Altcha\Algorithm\ShaAlgorithm;
use AltchaOrg\Altcha\Altcha as AltchaLib;
use AltchaOrg\Altcha\CreateChallengeOptions;
use AltchaOrg\Altcha\VerifySolutionOptions;
use FluentForm\Framework\Validator\ValidationException;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Altcha
 *
 * Sitewide, invisible proof-of-work spam protection for every FluentForm,
 * instead of FluentForm's built-in Cloudflare Turnstile field — Turnstile's
 * site key is domain-locked, so a cloned dev/staging DB breaks it. ALTCHA
 * signs/verifies challenges with a local auto-generated HMAC secret
 * (Repository::getAltchaHmacKey()), no external service or config needed.
 *
 * The widget is injected into every form via FluentForm's own render hooks,
 * invisible (display="invisible" auto="onsubmit"), toggled sitewide via
 * Repository::isAltchaEnabled() (on by default).
 *
 * @package Antropomorf\ContactForm
 */
class Altcha
{
    private const REST_NAMESPACE = 'amrf-admin/v1';
    private const REST_ROUTE = '/altcha-challenge';
    private const SCRIPT_HANDLE = 'amrf-altcha-widget';

    /**
     * Matches the hidden input name the altcha-widget custom element
     * manages on the client, so $formData[self::FIELD_NAME] below is
     * exactly what FluentForm actually receives in $_POST.
     */
    private const FIELD_NAME = 'altcha';

    public function __construct()
    {
        if (!Repository::isAltchaEnabled()) {
            return;
        }

        add_action('rest_api_init', [$this, 'registerChallengeRoute']);
        add_action('fluentform/before_form_render', [$this, 'enqueueWidget']);
        add_action('fluentform/render_item_submit_button', [$this, 'renderWidget']);
        add_action('fluentform/before_form_validation', [$this, 'validateSubmission'], 10, 2);
        add_filter('fluentform/white_listed_fields', [$this, 'whitelistField']);
    }

    /**
     * FluentForm strips POST fields not in its registered fields or this
     * whitelist — without it, 'altcha' never reaches validateSubmission().
     *
     * @param string[] $fields
     * @return string[]
     */
    public function whitelistField(array $fields): array
    {
        $fields[] = self::FIELD_NAME;
        return $fields;
    }

    /**
     * SHA-256 keeps the client-side proof-of-work sub-second; ALTCHA's
     * heavier algorithms (Argon2id/Scrypt) aren't needed for a contact form.
     *
     * @return Sha
     */
    private function algorithm(): Sha
    {
        return new Sha(ShaAlgorithm::SHA256);
    }

    private function altcha(): AltchaLib
    {
        return new AltchaLib(Repository::getAltchaHmacKey());
    }

    /**
     * Public/unauthenticated by design — the HMAC secret makes a solved
     * challenge unforgeable, not who's allowed to request one.
     *
     * @return void
     */
    public function registerChallengeRoute(): void
    {
        register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE, [
            'methods' => 'GET',
            'callback' => [$this, 'serveChallenge'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * @return \WP_REST_Response
     */
    public function serveChallenge(): \WP_REST_Response
    {
        $challenge = $this->altcha()->createChallenge(new CreateChallengeOptions(
            algorithm: $this->algorithm(),
            // One hash per attempt — difficulty comes entirely from
            // keyPrefixLength below, not from repeating work per guess.
            cost: 1,
            // Must be '' (not the library's own '00' default) or it never
            // randomizes the prefix.
            keyPrefix: '',
            // 2 random bytes: sub-second client-side, still real per-submission work.
            keyPrefixLength: 2,
            expiresAt: time() + 600,
        ));

        $response = new \WP_REST_Response($challenge->toArray());
        $response->header('Cache-Control', 'no-store');

        return $response;
    }

    /**
     * Fires once per form, only when it's actually about to render.
     *
     * @return void
     */
    public function enqueueWidget(): void
    {
        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            AMRF_ADMIN_PLUGIN_URL . 'assets/js/altcha-widget.min.js',
            [],
            filemtime(AMRF_ADMIN_PLUGIN_DIR . '/assets/js/altcha-widget.min.js'),
            true
        );
    }

    /**
     * Hooks the same action FluentForm's own submit-button renderer uses,
     * so it prints adjacent to it regardless of form type/step.
     *
     * @return void
     */
    public function renderWidget(): void
    {
        printf(
            '<altcha-widget challenge="%s" display="invisible" auto="onsubmit"></altcha-widget>',
            esc_url(rest_url(self::REST_NAMESPACE . self::REST_ROUTE))
        );
    }

    /**
     * Hooked the same way FluentForm's own captcha providers run — throwing
     * ValidationException here surfaces as a normal field error, not a
     * broken page.
     *
     * @param array $fields    Unused — required by the hook signature.
     * @param array $formData  The submitted form data, by reference.
     * @return void
     */
    public function validateSubmission($fields, $formData): void
    {
        $payload = $formData[self::FIELD_NAME] ?? '';

        if ($payload === '' || !$this->isSolutionValid($payload)) {
            throw new ValidationException('', 422, null, [
                'errors' => [
                    self::FIELD_NAME => [
                        __('Spam verification failed, please reload the page and try again.', 'amrf-admin'),
                    ],
                ],
            ]);
        }
    }

    /**
     * @param string $payload Base64 payload posted by the widget.
     * @return bool
     */
    private function isSolutionValid(string $payload): bool
    {
        try {
            $result = $this->altcha()->verifySolution(new VerifySolutionOptions(
                payload: $payload,
                algorithm: $this->algorithm(),
            ));
        } catch (\InvalidArgumentException $e) {
            // Malformed payload — not a real solution attempt at all.
            return false;
        }

        return $result->verified;
    }
}

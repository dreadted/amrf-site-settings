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
 * Sitewide, invisible proof-of-work spam protection for every FluentForm on
 * the site, replacing FluentForm's own built-in Cloudflare Turnstile field.
 * Turnstile ties a site key to specific domains registered on Cloudflare's
 * own dashboard; that key travels with a database export, so a site cloned
 * for dev/staging carries a key that's only valid for the original domain —
 * every other environment gets a real, user-visible "invalid domain" error
 * from Cloudflare's own script. ALTCHA's HMAC secret
 * (Repository::getAltchaHmacKey(), auto-generated and stored on first use —
 * no external account, nothing for a site owner to configure) signs and
 * verifies challenges without ever calling out to a third-party service,
 * and therefore works identically on every domain the same secret happens
 * to be deployed to, including a cloned database.
 *
 * Unlike Turnstile, no FluentForm form-builder element is used — the widget
 * is injected directly into every rendered form via FluentForm's own render
 * hooks (fluentform/render_item_submit_button), so protection is automatic
 * for every existing and future form without configuring each one
 * individually. display="invisible" + auto="onsubmit" means no checkbox or
 * other visual indicator ever appears in the form itself — the only
 * settings-page control is a plain on/off toggle
 * (Repository::isAltchaEnabled(), on by default) for a site that wants to
 * run its own spam protection instead; there's still nothing to configure
 * beyond that, no secret or key ever shown.
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
     * FluentForm builds $formData by intersecting the raw POST against the
     * form's own registered fields plus this whitelist
     * (Helper::getWhiteListedFields(), which is how its own built-in
     * reCaptcha/hCaptcha/Turnstile response fields survive without being
     * real form elements either) — without this, 'altcha' is silently
     * stripped before validateSubmission() ever sees it, and every
     * submission fails as if the solution were invalid.
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
     * SHA-256 keeps the client-side proof-of-work cheap (a few thousand
     * plain hash computations, sub-second even on a slow phone) — ALTCHA's
     * heavier algorithms (Argon2id/Scrypt) trade that for stronger
     * resistance against large-scale abuse, which a small contact form
     * doesn't need.
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
     * Public, unauthenticated by design — this is the same trust boundary
     * as the widget it serves: anyone can request a challenge (that's how
     * every visitor's browser gets one), the HMAC secret is what makes a
     * solved challenge unforgeable, not who's allowed to ask for one.
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
            // keyPrefix must be passed as '' (not left at its own default
            // of '00') to actually make the library randomize a prefix of
            // keyPrefixLength bytes — otherwise every challenge silently
            // reuses the fixed 1-byte '00' target instead.
            keyPrefix: '',
            // 2 random bytes = ~65k possible prefixes, ~32k average
            // brute-force attempts client-side: fast enough to be
            // invisible (well under a second), still real, asymmetric
            // work a spam script has to redo for every submission.
            keyPrefixLength: 2,
            expiresAt: time() + 600,
        ));

        $response = new \WP_REST_Response($challenge->toArray());
        $response->header('Cache-Control', 'no-store');

        return $response;
    }

    /**
     * Fires once per form, only when a form is actually about to render —
     * unlike loading the widget script sitewide on every page regardless
     * of whether it contains a form at all.
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
     * so this prints adjacent to it in every form regardless of form type
     * or step configuration — see FormBuilder::compile()'s
     * 'fluentform/render_item_submit_button' call.
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
     * Hooked the same way FluentForm's own validateReCaptcha/HCaptcha/
     * Turnstile run — see FormValidationService::validateSubmission(),
     * which calls do_action('fluentform/before_form_validation', ...)
     * before any of its own hardcoded provider checks. Throwing
     * ValidationException here is caught by the exact same code path
     * Turnstile's own failure uses, so the submitter sees a normal
     * "please fix this field" error, not a broken page.
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

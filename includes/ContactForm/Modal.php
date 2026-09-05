<?php

namespace Antropomorf\ContactForm;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class Modal
 *
 * Wires any "#kontakt" link/button on the site to a lightbox containing
 * the real FluentForm form configured as Repository::getDefaultContactFormId()
 * — ported from ptsussis-theme's includes/contact-modal.php + assets/js/
 * contact-modal.js, generalized: that version was hardcoded to a single
 * ptsussis/cta block (its own data-contact-trigger attribute) and form id
 * 1, and only ever rendered on the front page (the only page that block
 * could appear on for that specific site). A shared plugin can't assume
 * either — this instead matches ANY `<a href="#kontakt">` sitewide (plus
 * an optional data-contact-trigger attribute for a non-anchor element that
 * wants the same behavior — see assets/js/amrf-contact-modal.js), and
 * renders the form the "Contact Forms" Site Settings page has configured,
 * on every page.
 *
 * FluentForm dependency: no-ops entirely if the shortcode itself is
 * missing (plugin deactivated) or renders empty (configured form id
 * doesn't exist) — same defensive-guard convention this codebase already
 * uses for optional plugin integrations elsewhere.
 *
 * @package Antropomorf\ContactForm
 */
class Modal
{
  private const STYLE_HANDLE = 'amrf-contact-modal';
  private const STYLING_HANDLE = 'amrf-contact-form-styling';
  private const SCRIPT_HANDLE = 'amrf-contact-modal';

  private string $formHtml = '';

  public function __construct()
  {
    add_action('wp_enqueue_scripts', [$this, 'prerenderContactForm']);
    add_action('wp_footer', [$this, 'renderContactModal']);
  }

  /**
   * Renders the configured form's shortcode early — during
   * wp_enqueue_scripts, well before wp_head prints enqueued styles — and
   * caches the result on $this->formHtml for renderContactModal() (wp_footer)
   * to just echo. FluentForm's own shortcode handler enqueues its CSS/JS
   * as a side effect of actually rendering; doing that for the first time
   * inside a wp_footer callback — where a modal naturally belongs in the
   * DOM — would enqueue those styles too late for wp_head to have already
   * printed them. Rendering early and only echoing the cached markup
   * later avoids that entirely.
   *
   * @return void
   */
  public function prerenderContactForm(): void
  {
    if (!shortcode_exists('fluentform')) {
      return;
    }

    $form_id = Repository::getDefaultContactFormId();
    if ($form_id < 1) {
      return;
    }

    $this->formHtml = do_shortcode('[fluentform id="' . $form_id . '"]');

    if ($this->formHtml === '') {
      return;
    }

    wp_enqueue_style(
      self::STYLE_HANDLE,
      AMRF_ADMIN_PLUGIN_URL . 'assets/css/amrf-contact-modal.css',
      [],
      filemtime(AMRF_ADMIN_PLUGIN_DIR . '/assets/css/amrf-contact-modal.css')
    );

    if (Repository::isConsistentStylingEnabled()) {
      wp_enqueue_style(
        self::STYLING_HANDLE,
        AMRF_ADMIN_PLUGIN_URL . 'assets/css/amrf-contact-form-styling.css',
        [],
        filemtime(AMRF_ADMIN_PLUGIN_DIR . '/assets/css/amrf-contact-form-styling.css')
      );
    }

    wp_enqueue_script(
      self::SCRIPT_HANDLE,
      AMRF_ADMIN_PLUGIN_URL . 'assets/js/amrf-contact-modal.js',
      [],
      filemtime(AMRF_ADMIN_PLUGIN_DIR . '/assets/js/amrf-contact-modal.js'),
      true
    );
  }

  /**
   * Prints the modal shell around the form HTML cached above. Nothing to
   * print — FluentForm missing, or the configured form id doesn't exist —
   * means no modal markup at all, and no dangling "#kontakt" link that
   * silently does nothing worse than a normal anchor jump would.
   *
   * @return void
   */
  public function renderContactModal(): void
  {
    if ($this->formHtml === '') {
      return;
    }
?>
    <div class="amrf-contact-modal" data-contact-modal hidden>
      <div class="amrf-contact-modal-backdrop" data-contact-backdrop></div>
      <div class="amrf-contact-modal-dialog" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Contact', 'amrf-admin'); ?>">
        <button type="button" class="amrf-contact-modal-close" data-contact-close aria-label="<?php esc_attr_e('Close', 'amrf-admin'); ?>">
          <span aria-hidden="true">&times;</span>
        </button>
        <?php echo $this->formHtml; ?>
        <p class="amrf-contact-modal-privacy-note">
          <?php
          // get_privacy_policy_url() only ever returns a URL for a
          // PUBLISHED privacy policy page — renders without a link until
          // one exists, no code change needed once it's published.
          $policy_url = get_privacy_policy_url();
          $link_open = $policy_url ? '<a href="' . esc_url($policy_url) . '">' : '';
          $link_close = $policy_url ? '</a>' : '';
          printf(
            /* translators: 1: opening <a> tag to the privacy policy (omitted if it isn't published yet), 2: closing </a> tag */
            esc_html__('We process your personal data to answer your message. Read more in our %1$sprivacy policy%2$s.', 'amrf-admin'),
            $link_open,
            $link_close
          );
          ?>
        </p>
      </div>
    </div>
<?php
  }
}

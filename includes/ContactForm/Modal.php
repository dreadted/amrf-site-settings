<?php

namespace Antropomorf\ContactForm;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Class Modal
 *
 * Wires any `<a href="#kontakt">` (or element with data-contact-trigger,
 * see assets/js/amrf-contact-modal.js) sitewide to a lightbox containing
 * the FluentForm configured via Repository::getDefaultContactFormId().
 * No-ops entirely if FluentForm is inactive or the configured form doesn't
 * exist.
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
   * Renders the form shortcode early (wp_enqueue_scripts) and caches it for
   * renderContactModal() to echo — FluentForm enqueues its CSS/JS as a side
   * effect of rendering, which would be too late for wp_head if done from
   * wp_footer directly.
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

      $this->inlineThemeButtonStyle();
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
   * Exposes the active theme's own button design (theme.json's
   * styles.elements.button, see SiteSettings\Repository::
   * getThemeButtonStyle()) as CSS custom properties, so
   * amrf-contact-form-styling.css can style the submit button to match it
   * instead of a generic default — no-ops if the theme doesn't declare
   * that element at all.
   *
   * @return void
   */
  private function inlineThemeButtonStyle(): void
  {
    $vars = \Antropomorf\SiteSettings\Repository::getThemeButtonStyle();
    if (!$vars) {
      return;
    }

    $declarations = '';
    foreach ($vars as $property => $value) {
      $declarations .= $property . ':' . $value . ';';
    }

    wp_add_inline_style(self::STYLING_HANDLE, ':root{' . $declarations . '}');
  }

  /**
   * Prints the modal shell around the cached form HTML; prints nothing if
   * there's no form to show.
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
          // Only returns a URL once the privacy policy page is published.
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

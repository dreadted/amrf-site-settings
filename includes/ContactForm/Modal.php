<?php

namespace Antropomorf\ContactForm;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Sitewide lightbox with a FluentForm for <a href="#contact"> and [data-contact-trigger].
 * The form comes from the amrf_contact_modal_form_id filter, defaulting to the Default Contact Form.
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
		add_filter('block_editor_settings_all', [$this, 'addEditorStyles']);
	}

	/**
	 * The editor iframe only copies stylesheets that mention .wp-block or .editor-styles-wrapper;
	 * $settings['styles'] isn't selector-gated.
	 *
	 * @param array $settings
	 * @return array
	 */
	public function addEditorStyles(array $settings): array
	{
		if (!Repository::isConsistentStylingEnabled()) {
			return $settings;
		}

		$css = file_get_contents(AMRF_ADMIN_PLUGIN_DIR . '/assets/css/amrf-contact-form-styling.css');
		$settings['styles'][] = ['css' => $css];

		return $settings;
	}

	/**
	 * Renders early and caches: FluentForm enqueues its assets while rendering, too late for wp_head from wp_footer.
	 *
	 * @return void
	 */
	public function prerenderContactForm(): void
	{
		if (!shortcode_exists('fluentform')) {
			return;
		}

		// Rendered before enqueueConsistentStyling() so our CSS always queues after FluentForm's.
		$form_id = apply_filters('amrf_contact_modal_form_id', Repository::getDefaultContactFormId());
		$this->formHtml = $form_id > 0 ? do_shortcode('[fluentform id="' . $form_id . '"]') : '';

		// Applies to every FluentForm on the site, not just the modal's — enqueue
		// regardless of whether a modal form is even configured.
		$this->enqueueConsistentStyling();

		if ($this->formHtml === '') {
			return;
		}

		wp_enqueue_style(
			self::STYLE_HANDLE,
			AMRF_ADMIN_PLUGIN_URL . 'assets/css/amrf-contact-modal.css',
			[],
			filemtime(AMRF_ADMIN_PLUGIN_DIR . '/assets/css/amrf-contact-modal.css')
		);

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			AMRF_ADMIN_PLUGIN_URL . 'assets/js/amrf-contact-modal.js',
			[],
			filemtime(AMRF_ADMIN_PLUGIN_DIR . '/assets/js/amrf-contact-modal.js'),
			true
		);
	}

	/** Shared by the frontend prerender and the editor-iframe hook above. */
	private function enqueueConsistentStyling(): void
	{
		if (!Repository::isConsistentStylingEnabled()) {
			return;
		}

		wp_enqueue_style(
			self::STYLING_HANDLE,
			AMRF_ADMIN_PLUGIN_URL . 'assets/css/amrf-contact-form-styling.css',
			[],
			filemtime(AMRF_ADMIN_PLUGIN_DIR . '/assets/css/amrf-contact-form-styling.css')
		);

		wp_enqueue_script(
			self::STYLING_HANDLE,
			AMRF_ADMIN_PLUGIN_URL . 'assets/js/amrf-contact-form-styling.js',
			[],
			filemtime(AMRF_ADMIN_PLUGIN_DIR . '/assets/js/amrf-contact-form-styling.js'),
			true
		);
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
			</div>
		</div>
<?php
	}
}

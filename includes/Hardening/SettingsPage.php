<?php

namespace Antropomorf\Hardening;

use Antropomorf\Utilities\SettingsRenderer;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class SettingsPage
 *
 * The "Hardening" admin page under Site Settings, with its Images and
 * Frontend tabs.
 *
 * @package Antropomorf\Hardening
 */
class SettingsPage
{
	private const PAGE_SLUG = 'amrf-site-settings-hardening';
	private const TABS_FILTER = 'amrf_hardening_tabs';
	private const TAB_PAGE_SLUG_IMAGES = 'amrf-site-settings-hardening-images';
	private const TAB_PAGE_SLUG_FRONTEND = 'amrf-site-settings-hardening-frontend';

	private SettingsRenderer $renderer;

	public function __construct()
	{
		$this->renderer = new SettingsRenderer(self::TABS_FILTER, self::PAGE_SLUG, fn() => __('Hardening', 'amrf-admin'));

		add_filter('amrf_site_settings_pages', [$this, 'registerPages']);
		add_filter(self::TABS_FILTER, [$this, 'registerTabs']);
	}

	/**
	 * @param array $pages Pages registered so far by other callbacks on this filter.
	 * @return array Pages with 'hardening' appended.
	 */
	public function registerPages(array $pages): array
	{
		$pages['hardening'] = [
			'page_title' => __('Hardening', 'amrf-admin'),
			'menu_title' => __('Hardening', 'amrf-admin'),
			'capability' => 'manage_options',
			'menu_slug' => self::PAGE_SLUG,
			'register' => fn() => SettingsRenderer::registerSettings(self::TABS_FILTER),
			'render' => [$this->renderer, 'render'],
		];

		return $pages;
	}

	public function registerTabs(array $tabs): array
	{
		$tabs['images'] = [
			'label' => __('Images', 'amrf-admin'),
			'option_group' => Repository::OPTION_GROUP_IMAGES,
			'page_slug' => self::TAB_PAGE_SLUG_IMAGES,
			'show_reset' => false,
			'register' => [$this, 'registerImagesTab'],
		];

		$tabs['frontend'] = [
			'label' => __('Frontend', 'amrf-admin'),
			'option_group' => Repository::OPTION_GROUP_FRONTEND,
			'page_slug' => self::TAB_PAGE_SLUG_FRONTEND,
			'show_reset' => false,
			'register' => [$this, 'registerFrontendTab'],
		];

		return $tabs;
	}

	/**
	 * @return void
	 */
	public function registerImagesTab(): void
	{
		register_setting(
			Repository::OPTION_GROUP_IMAGES,
			Repository::OPTION_NAME,
			[Repository::class, 'sanitize']
		);

		add_settings_section('hardening_images_section', '', '__return_false', self::TAB_PAGE_SLUG_IMAGES);

		// Registered first so it renders at the top of the tab, ahead of the
		// $fields loop below.
		add_settings_field(
			'convert_uploads_to_webp',
			__('Convert uploads to WebP', 'amrf-admin'),
			function () {
				$this->renderCheckbox(
					'convert_uploads_to_webp',
					__('Automatically converts every non-WebP image upload to WebP at the quality below, and blocks exact duplicates of an already-uploaded image.', 'amrf-admin')
				);
			},
			self::TAB_PAGE_SLUG_IMAGES,
			'hardening_images_section'
		);

		add_settings_field(
			'webp_quality',
			__('WebP quality', 'amrf-admin'),
			function () {
				$this->renderWebpQualityField();
			},
			self::TAB_PAGE_SLUG_IMAGES,
			'hardening_images_section'
		);

		$fields = [
			'restrict_media_deletion' => [
				__('Restrict media deletion', 'amrf-admin'),
				__('Non-administrators can only delete media library items they uploaded themselves. Without this, WordPress lets anyone who can upload files delete any attachment, regardless of who uploaded it.', 'amrf-admin'),
			],
			'allow_svg_uploads' => [
				__('Allow SVG uploads', 'amrf-admin'),
				__('Lets administrators upload SVG files through the Media Library — every file is sanitized (scripts, event handlers, and embedded HTML stripped) before it\'s stored.', 'amrf-admin'),
			],
			'disable_generated_image_sizes' => [
				__('Disable generated image sizes', 'amrf-admin'),
				__('Stops WordPress from generating its own default image sizes (thumbnail, medium, medium_large, large, 1536x1536, 2048x2048). Sizes a theme registers itself are unaffected.', 'amrf-admin'),
			],
		];

		$this->registerCheckboxFields($fields, self::TAB_PAGE_SLUG_IMAGES, 'hardening_images_section');
	}

	/**
	 * @return void
	 */
	public function registerFrontendTab(): void
	{
		register_setting(
			Repository::OPTION_GROUP_FRONTEND,
			Repository::OPTION_NAME,
			[Repository::class, 'sanitize']
		);

		add_settings_section('hardening_frontend_section', '', '__return_false', self::TAB_PAGE_SLUG_FRONTEND);

		// Registered first, ahead of the $fields loop below, and styled with the
		// alert-colored switch — this one takes the whole site offline for
		// logged-out visitors, a much bigger consequence than the rest of this tab.
		add_settings_field(
			'restrict_site_to_logged_in',
			__('Restrict site to logged-in users', 'amrf-admin'),
			function () {
				$this->renderCheckbox(
					'restrict_site_to_logged_in',
					__('Blocks every front-end page for logged-out visitors with a blank placeholder page — the WordPress admin and login screen stay reachable. Use this to preview a site privately before launch.', 'amrf-admin'),
					true
				);
			},
			self::TAB_PAGE_SLUG_FRONTEND,
			'hardening_frontend_section'
		);

		$fields = [
			'disable_author_archives' => [
				__('Disable author archives', 'amrf-admin'),
				__('Redirects author archive pages to the homepage — mainly useful on single-author sites, or to avoid leaking usernames via author URLs.', 'amrf-admin'),
			],
			'disable_posts' => [
				__('Disable blog posts', 'amrf-admin'),
				__('For sites built from pages only. Posts, category, tag and date archives, and the RSS feeds return a 404 (then redirect to the homepage if "Redirect 404 pages to the homepage" is on), and posts are left out of the WordPress sitemap. Existing posts and the admin are unaffected.', 'amrf-admin'),
			],
			'disable_comments' => [
				__('Disable comments', 'amrf-admin'),
				__('Closes comments, pingbacks and trackbacks on all content regardless of each page\'s discussion settings, hides already approved comments, and turns the comment feeds into a 404.', 'amrf-admin'),
			],
			'redirect_404_to_home' => [
				__('Redirect 404 pages to the homepage', 'amrf-admin'),
				__('Applies to logged-out visitors only. Turn off if this site should show a real 404 page instead.', 'amrf-admin'),
			],
			'disable_site_search' => [
				__('Disable site search', 'amrf-admin'),
				__('Turns the built-in WordPress search into a 404 for every visitor — useful while a site is still under construction and shouldn\'t expose a working search box yet.', 'amrf-admin'),
			],
			'remove_jquery_migrate' => [
				__('Remove jQuery Migrate', 'amrf-admin'),
				__('Turn off if an older plugin or theme on this site depends on jQuery Migrate\'s compatibility shims.', 'amrf-admin'),
			],
		];

		$this->registerCheckboxFields($fields, self::TAB_PAGE_SLUG_FRONTEND, 'hardening_frontend_section');
	}

	private function registerCheckboxFields(array $fields, string $page_slug, string $section): void
	{
		foreach ($fields as $key => [$label, $description]) {
			add_settings_field(
				$key,
				$label,
				function () use ($key, $description) {
					$this->renderCheckbox($key, $description);
				},
				$page_slug,
				$section
			);
		}
	}

	/**
	 * Same .switch/.slider markup as Settings\Manager::renderCheckbox().
	 *
	 * @param string $key
	 * @param string $description
	 * @param bool   $alert Renders the slider in the alert color once checked —
	 *                       for toggles with consequences big enough to want a
	 *                       visual warning when left on.
	 * @return void
	 */
	private function renderCheckbox(string $key, string $description, bool $alert = false): void
	{
		$settings = Repository::getSettings();
		$name = Repository::OPTION_NAME . '[' . $key . ']';

		printf(
			'<label class="switch%1$s"><input type="checkbox" name="%2$s" value="1" %3$s /><span class="slider round"></span></label><p class="description">%4$s</p>',
			$alert ? ' switch-alert' : '',
			esc_attr($name),
			checked(!empty($settings[$key]), true, false),
			esc_html($description)
		);
	}

	/**
	 * @return void
	 */
	private function renderWebpQualityField(): void
	{
		$settings = Repository::getSettings();

		printf(
			'<input type="number" min="1" max="100" name="%1$s[webp_quality]" value="%2$d" />'
				. '<p class="description">%3$s</p>',
			esc_attr(Repository::OPTION_NAME),
			(int) $settings['webp_quality'],
			esc_html__('Applies to both the WebP format conversion above and every automatically generated responsive image size (1–100).', 'amrf-admin')
		);
	}
}

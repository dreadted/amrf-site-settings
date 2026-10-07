<?php

namespace Antropomorf\ContactForm;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Holds back the form scripts (and jQuery when only they need it) until a form nears the viewport or a visitor reaches for one.
 * The form HTML stays server-rendered, so FluentForm binds it normally when its script runs late.
 *
 * @package Antropomorf\ContactForm
 */
class OnDemandScripts
{
	private const LOADER_HANDLE = 'amrf-form-script-loader';
	private const ROOT_HANDLES = ['fluent-form-submission', 'amrf-altcha-widget'];

	/** @var array<string, bool> */
	private array $lazy = [];

	public function __construct()
	{
		if (is_admin()) {
			return;
		}

		add_filter('script_loader_tag', [$this, 'holdBack'], 10, 2);
		// Before _wp_footer_scripts (10), after the content has enqueued its forms.
		add_action('wp_print_footer_scripts', [$this, 'enqueueLoader'], 9);
	}

	/**
	 * @return string[]
	 */
	private function rootHandles(): array
	{
		return (array) apply_filters('amrf_on_demand_script_handles', self::ROOT_HANDLES);
	}

	public function enqueueLoader(): void
	{
		$needed = array_filter(
			$this->rootHandles(),
			fn(string $handle): bool => wp_script_is($handle, 'enqueued') || wp_script_is($handle, 'done')
		);

		if (!$needed) {
			return;
		}

		wp_enqueue_script(
			self::LOADER_HANDLE,
			AMRF_ADMIN_PLUGIN_URL . 'assets/js/amrf-form-script-loader.js',
			[],
			filemtime(AMRF_ADMIN_PLUGIN_DIR . '/assets/js/amrf-form-script-loader.js'),
			['in_footer' => true, 'strategy' => 'defer']
		);
	}

	/**
	 * Turns the script tag into an inert placeholder the loader replaces later.
	 *
	 * @param string $tag    Full tag markup, including inline before/after scripts.
	 * @param string $handle
	 * @return string
	 */
	public function holdBack(string $tag, string $handle): string
	{
		if (!$this->isLazy($handle)) {
			return $tag;
		}

		$processor = new \WP_HTML_Tag_Processor($tag);
		while ($processor->next_tag('script')) {
			$src = $processor->get_attribute('src');
			if ($processor->get_attribute('id') !== $handle . '-js' || !is_string($src)) {
				continue;
			}

			$processor->remove_attribute('src');
			$processor->remove_attribute('defer');
			$processor->remove_attribute('async');
			$processor->set_attribute('type', 'text/plain');
			$processor->set_attribute('data-amrf-load', $src);
			break;
		}

		return $processor->get_updated_html();
	}

	/**
	 * Lazy: a root handle, anything depending on a lazy handle, or a dependency whose every needed dependent is lazy.
	 * Handles with an 'after' inline script stay eager, as in core's own delayed strategies.
	 */
	private function isLazy(string $handle): bool
	{
		if (isset($this->lazy[$handle])) {
			return $this->lazy[$handle];
		}

		$scripts = wp_scripts();
		$script = $scripts->registered[$handle] ?? null;

		if (!$script || $scripts->get_data($handle, 'after')) {
			return $this->lazy[$handle] = false;
		}

		$this->lazy[$handle] = in_array($handle, $this->rootHandles(), true)
			|| array_filter($script->deps, fn(string $dep): bool => !empty($this->lazy[$dep]))
			|| $this->onlyServesLazy($handle);

		return $this->lazy[$handle];
	}

	private function onlyServesLazy(string $handle): bool
	{
		$scripts = wp_scripts();
		$dependents = array_filter(
			array_keys($scripts->registered),
			fn(string $other): bool => in_array($handle, $scripts->registered[$other]->deps, true)
				&& ($scripts->query($other, 'enqueued') || $scripts->query($other, 'done'))
		);

		foreach ($dependents as $dependent) {
			if (!$this->isLazy($dependent)) {
				return false;
			}
		}

		return (bool) $dependents;
	}
}

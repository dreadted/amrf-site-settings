<?php

namespace Antropomorf\SiteSettings;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Site logo instead of the WordPress logo on wp-login.php, without the wordpress.org link.
 * Pins the admin color variables to the default scheme, otherwise set from the logged-in user's preference.
 *
 * @package Antropomorf\SiteSettings
 */
class LoginBranding
{
	public function __construct()
	{
		add_action('login_enqueue_scripts', [$this, 'renderStyles']);
		add_filter('login_headertext', [$this, 'headerText']);
		add_action('login_enqueue_scripts', [$this, 'stripHeaderLink']);
	}

	/**
	 * @return string
	 */
	public function headerText(): string
	{
		return get_bloginfo('name');
	}

	/**
	 * Inline after login.css so the logo wins without a flash. Variables sit on body.login
	 * to override wp-login.php's hardcoded admin-color-modern class.
	 * '.login h1' is targeted too, since stripHeaderLink() removes the link.
	 *
	 * @return void
	 */
	public function renderStyles(): void
	{
		$css = "
body.login {
    --wp-admin-theme-color: #2271b1;
    --wp-admin-theme-color-darker-10: #2271b1;
}
.login .message, .login .notice, .login .success {
    border-left: 4px solid var(--wp-admin-theme-color);
}
";

		$logo = BrandImages::url('logo') ?: get_site_icon_url(512);
		if ($logo) {
			$logo = esc_url($logo);
			$css .= "
.login h1 a, .login h1 {
    background-image: url(\"{$logo}\");
    background-position: center center;
    background-repeat: no-repeat;
    background-size: contain;
    width: 84px;
    height: 84px;
    margin: 0 auto 25px;
    padding: 0;
    text-indent: -9999px;
    overflow: hidden;
    display: block;
}
";
		}

		wp_add_inline_style('login', $css);
	}

	/**
	 * WP core has no filter to drop the <a> wrapper around the login
	 * header logo, so it's unwrapped client-side after the page renders.
	 *
	 * @return void
	 */
	public function stripHeaderLink(): void
	{
		wp_register_script('amrf-login-branding', false, [], false, true);
		wp_enqueue_script('amrf-login-branding');
		wp_add_inline_script('amrf-login-branding', "
document.addEventListener('DOMContentLoaded', function() {
	var link = document.querySelector('.wp-login-logo a');
	if (link) {
		link.replaceWith(...link.childNodes);
	}
});
");
	}
}

<?php

namespace Antropomorf\SiteSettings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Replaces the WordPress logo on wp-login.php with the site's own
 * favicon.svg from the uploads directory, and removes the link back
 * to wordpress.org. Also pins the admin theme-color CSS variables to
 * the default blue on the login page, since they're only otherwise
 * set from the logged-in user's own color-scheme preference.
 *
 * @package Antropomorf\SiteSettings
 */
class LoginBranding
{
    public function __construct()
    {
        add_action('login_enqueue_scripts', [$this, 'renderStyles']);
        add_filter('login_headertext', [$this, 'headerText']);
        add_action('login_footer', [$this, 'stripHeaderLink']);
    }

    /**
     * @return string
     */
    public function headerText(): string
    {
        return get_bloginfo('name');
    }

    /**
     * Attached via wp_add_inline_style() rather than a raw <style> echo,
     * so it prints right after WP core's own login.css and reliably wins
     * the cascade instead of flashing the WordPress logo first.
     *
     * The theme-color variables are set on 'body.login' rather than
     * ':root' — wp-login.php always hardcodes an 'admin-color-modern'
     * body class (core's default scheme, regardless of login state),
     * and 'body.admin-color-modern' in admin-schemes.css has higher
     * specificity than ':root' so it would otherwise always win.
     * 'body.login' matches that specificity and comes later in the
     * cascade, since wp-base-styles (admin-schemes.css) is a dependency
     * of the 'login' handle this is attached to.
     *
     * Both '.login h1' and '.login h1 a' are targeted identically since
     * stripHeaderLink() removes the <a> — the rule needs to still apply
     * once that wrapper is gone.
     *
     * @return void
     */
    public function renderStyles(): void
    {
        $uploadDir = wp_upload_dir();
        $logo = esc_url(trailingslashit($uploadDir['baseurl']) . 'favicon.svg');

        wp_add_inline_style('login', "
body.login {
    --wp-admin-theme-color: #2271b1;
    --wp-admin-theme-color-darker-10: #2271b1;
}
.login h1 a, .login h1 {
    background-image: url({$logo});
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
");
    }

    /**
     * WP core has no filter to drop the <a> wrapper around the login
     * header logo, so it's unwrapped client-side after the page renders.
     *
     * @return void
     */
    public function stripHeaderLink(): void
    {
        ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var link = document.querySelector('.wp-login-logo a');
    if (link) {
        link.replaceWith(...link.childNodes);
    }
});
</script>
        <?php
    }
}

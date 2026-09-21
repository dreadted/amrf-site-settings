<?php

namespace Antropomorf\SiteSettings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Replaces the WordPress logo on wp-login.php with the site's own
 * favicon.svg from the uploads directory, and removes the link back
 * to wordpress.org.
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
     * @return void
     */
    public function renderStyles(): void
    {
        $uploadDir = wp_upload_dir();
        $logo = trailingslashit($uploadDir['baseurl']) . 'favicon.svg';
        ?>
<style>
    .login h1 a {
        background-image: url(<?php echo esc_url($logo); ?>);
        background-size: contain;
        background-position: center;
        width: 100%;
        height: 84px;
    }
</style>
        <?php
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

<?php

/**
 * Plugin Name:       Admin Panel Settings
 * Description:       Customize admin panel settings for different user roles.
 * Version:           0.2.3
 * Requires at least: 5.6
 * Requires PHP:      8.1
 * Author:            Christofer Laurin
 * Author URI:        https://github.com/dreadted/
 * Text Domain:       amrf-admin
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AMRF_ADMIN_PLUGIN_FILE', __FILE__);
define('AMRF_ADMIN_PLUGIN_DIR', __DIR__);
define('AMRF_ADMIN_PLUGIN_URL', plugin_dir_url(__FILE__));

// Composer dependencies (currently just altcha-org/altcha, for
// ContactForm\Altcha's sitewide spam protection) — vendor/ is committed to
// the repo since this plugin has no build step, so no composer install is
// required after checkout.
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * Autoloader for plugin classes.
 *
 * @param string $class The fully-qualified class name.
 */
spl_autoload_register(function ($class) {
    $prefix = 'Antropomorf\\';
    $base_dir = __DIR__ . '/includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

load_plugin_textdomain(
    'amrf-admin',
    false,
    dirname(plugin_basename(__FILE__)) . '/languages'
);

require_once __DIR__ . '/includes/functions.php';

use Antropomorf\Hooks\AdminHooks;
use Antropomorf\Hooks\FrontendHooks;
use Antropomorf\SiteSettings\Bootstrap as SiteSettingsBootstrap;
use Antropomorf\ContactForm\Bootstrap as ContactFormBootstrap;
use Antropomorf\Forms\Bootstrap as FormsBootstrap;
use Antropomorf\Swish\Bootstrap as SwishBootstrap;
use Antropomorf\SupportGenix\Bootstrap as SupportGenixBootstrap;
use Antropomorf\Umami\Bootstrap as UmamiBootstrap;
use Antropomorf\Branding\Bootstrap as BrandingBootstrap;
use Antropomorf\FluentFormValidation\Bootstrap as FluentFormValidationBootstrap;
use Antropomorf\Hardening\Bootstrap as HardeningBootstrap;

AdminHooks::register();
FrontendHooks::register();
SiteSettingsBootstrap::register();
ContactFormBootstrap::register();
FormsBootstrap::register();
SwishBootstrap::register();
SupportGenixBootstrap::register();
UmamiBootstrap::register();
BrandingBootstrap::register();
FluentFormValidationBootstrap::register();
HardeningBootstrap::register();

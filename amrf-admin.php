<?php
/**
 * Plugin Name:     Admin Panel Settings
 * Description:     Customize admin panel settings for different user roles.
 * Version:         0.1.0
 * Author:          Christofer Laurin
 * Author URI:      https://github.com/dreadted/
 * Text Domain:     amrf-admin
 * Domain Path:     /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AMRF_ADMIN_PLUGIN_FILE', __FILE__);
define('AMRF_ADMIN_PLUGIN_DIR', __DIR__);
define('AMRF_ADMIN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AMRF_ADMIN_TEXT_DOMAIN', 'amrf-admin');

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
    AMRF_ADMIN_TEXT_DOMAIN,
    false,
    dirname(plugin_basename(__FILE__)) . '/languages'
);

use Antropomorf\Hooks\AdminHooks;
use Antropomorf\Hooks\FrontendHooks;

AdminHooks::register();
FrontendHooks::register();

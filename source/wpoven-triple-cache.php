<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.wpoven.com
 * @since             1.0.0
 * @package           Wpoven_Triple_Cache
 *
 * @wordpress-plugin
 * Plugin Name:       WPOven Triple Cache
 * Plugin URI:        https://www.wpoven.com/plugins/wpoven-triple-cache
 * Description:       Cloudflare Caching
 * Version:           1.0.0
 * Author:            WPOven
 * Author URI:        https://www.wpoven.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wpoven-triple-cache
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('WPOVEN_TRIPLE_CACHE_VERSION', '1.0.0');
if (!defined('WPOVEN_TRIPLE_CACHE_SLUG'))
	define('WPOVEN_TRIPLE_CACHE_SLUG', 'wpoven-triple-cache');

define('WPOVEN_TRIPLE_CACHE', 'WPOven Triple Cache Options');
define('WPOVEN_TRIPLE_CACHE_ROOT_PL', __FILE__);
define('WPOVEN_TRIPLE_CACHE_ROOT_URL', plugins_url('', WPOVEN_TRIPLE_CACHE_ROOT_PL));
define('WPOVEN_TRIPLE_CACHE_ROOT_DIR', dirname(WPOVEN_TRIPLE_CACHE_ROOT_PL));
define('WPOVEN_TRIPLE_CACHE_PLUGIN_DIR', plugin_dir_path(__DIR__));
define('WPOVEN_TRIPLE_CACHE_PLUGIN_BASE', plugin_basename(WPOVEN_TRIPLE_CACHE_ROOT_PL));
define('WPOVEN_CACHE_PATH', realpath(plugin_dir_path(WPOVEN_TRIPLE_CACHE_ROOT_PL)) . '/');


define('WPOCF_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WPOCF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPOCF_BASEFILE', __FILE__);
define('WPOCF_PLUGIN_REVIEWS_URL', 'https://wordpress.org/support/plugin/WPOven Triple Cache/reviews/');
define('WPOCF_PLUGIN_FORUM_URL', 'https://wordpress.org/support/plugin/WPOven Triple Cache/');
define('WPOCF_AUTH_MODE_API_KEY',   0);
define('WPOCF_AUTH_MODE_API_TOKEN', 1);
define('WPOCF_LOGS_STANDARD_VERBOSITY', 1);
define('WPOCF_LOGS_HIGH_VERBOSITY', 2);

// if (!defined('WPOCF_PRELOADER_MAX_POST_NUMBER'))
// 	define('WPOCF_PRELOADER_MAX_POST_NUMBER', 50);

if (!defined('WPOCF_CACHE_BUSTER'))
	define('WPOCF_CACHE_BUSTER', 'wpocf');

if (!defined('WPOCF_CURL_TIMEOUT'))
	define('WPOCF_CURL_TIMEOUT', 10);

if (!defined('WPOCF_PURGE_CACHE_LOCK_SECONDS'))
	define('WPOCF_PURGE_CACHE_LOCK_SECONDS', 10);

if (!defined('WPOCF_HOME_PAGE_SHOWS_POSTS'))
	define('WPOCF_HOME_PAGE_SHOWS_POSTS', true);


require_once plugin_dir_path(__FILE__) . 'includes/libraries/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/baseapp/wpoven_triplecache.git',
	__FILE__,
	'wpoven-triple-cache'
);
$myUpdateChecker->getVcsApi()->enableReleaseAssets();

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-wpoven-triple-cache-activator.php
 */
function activate_wpoven_triple_cache()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-wpoven-triple-cache-activator.php';
	Wpoven_Triple_Cache_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-wpoven-triple-cache-deactivator.php
 */
function deactivate_wpoven_triple_cache()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-wpoven-triple-cache-deactivator.php';
	Wpoven_Triple_Cache_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_wpoven_triple_cache');
register_deactivation_hook(__FILE__, 'deactivate_wpoven_triple_cache');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-wpoven-triple-cache.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_wpoven_triple_cache()
{

	$plugin = new Wpoven_Triple_Cache();
	$plugin->run();
}
run_wpoven_triple_cache();

function wpoven_triple_cache_plugin_settings_link($links)
{
	$settings_link = '<a href="' . admin_url('admin.php?page=' . WPOVEN_TRIPLE_CACHE_SLUG) . '">Settings</a>';

	array_push($links, $settings_link);
	return $links;
}
add_filter('plugin_action_links_' . WPOVEN_TRIPLE_CACHE_PLUGIN_BASE, 'wpoven_triple_cache_plugin_settings_link');

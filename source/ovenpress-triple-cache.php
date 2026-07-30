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
 * @package           Ovenpress_Triple_Cache
 *
 * @wordpress-plugin
 * Plugin Name:       OvenPress Triple Cache
 * Plugin URI:        https://www.wpoven.com/plugins/ovenpress-triple-cache
 * Description:       Cloudflare Caching
 * Version:           1.0.2
 * Author:            WPOven
 * Author URI:        https://www.wpoven.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ovenpress-triple-cache
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


define('OVENPRESS_TRIPLE_CACHE_VERSION', '1.0.2');
if (!defined('OVENPRESS_TRIPLE_CACHE_SLUG'))
	define('OVENPRESS_TRIPLE_CACHE_SLUG', 'ovenpress-triple-cache');

define('OVENPRESS_TRIPLE_CACHE', 'OvenPress Triple Cache Options');
define('OVENPRESS_TRIPLE_CACHE_ROOT_PL', __FILE__);
define('OVENPRESS_TRIPLE_CACHE_ROOT_URL', plugins_url('', OVENPRESS_TRIPLE_CACHE_ROOT_PL));
define('OVENPRESS_TRIPLE_CACHE_ROOT_DIR', dirname(OVENPRESS_TRIPLE_CACHE_ROOT_PL));
define('OVENPRESS_TRIPLE_CACHE_PLUGIN_DIR', plugin_dir_path(__DIR__));
define('OVENPRESS_TRIPLE_CACHE_PLUGIN_BASE', plugin_basename(OVENPRESS_TRIPLE_CACHE_ROOT_PL));
define('OVENPRESS_CACHE_PATH', realpath(plugin_dir_path(OVENPRESS_TRIPLE_CACHE_ROOT_PL)) . '/');


define('WPOCF_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WPOCF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPOCF_BASEFILE', __FILE__);
define('WPOCF_PLUGIN_REVIEWS_URL', 'https://wordpress.org/support/plugin/OvenPress Triple Cache/reviews/');
define('WPOCF_PLUGIN_FORUM_URL', 'https://wordpress.org/support/plugin/OvenPress Triple Cache/');
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





/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-ovenpress-triple-cache-activator.php
 */
function ovenpress_triple_cache_activate()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-ovenpress-triple-cache-activator.php';
	Ovenpress_Triple_Cache_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-ovenpress-triple-cache-deactivator.php
 */
function ovenpress_triple_cache_deactivate()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-ovenpress-triple-cache-deactivator.php';
	Ovenpress_Triple_Cache_Deactivator::deactivate();

	$dropin = WP_CONTENT_DIR . '/object-cache.php';

	if (!file_exists($dropin)) {
		return;
	}

	$contents = file_get_contents($dropin);

	// Strict signature match
	if (
		strpos($contents, 'ovenpress-triple-cache') !== false ||
		strpos($contents, 'WP_PhpFastCache_Object_Cache') !== false
	) {

		// Initialize WP filesystem
		global $wp_filesystem;
		if (empty($wp_filesystem)) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// Delete drop-in safely
		if (!$wp_filesystem->delete($dropin)) {
			error_log('OvenPress Triple Cache: Failed to delete object-cache.php on deactivation');
		}
	}
}

register_activation_hook(__FILE__, 'ovenpress_triple_cache_activate');
register_deactivation_hook(__FILE__, 'ovenpress_triple_cache_deactivate');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-ovenpress-triple-cache.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */

add_action('plugins_loaded', function () {
	$plugin = new Ovenpress_Triple_Cache();
	$plugin->run();
});

function ovenpress_triple_cache_plugin_settings_link($links)
{
	$settings_link = '<a href="' . admin_url('admin.php?page=' . OVENPRESS_TRIPLE_CACHE_SLUG) . '">Settings</a>';

	array_push($links, $settings_link);
	return $links;
}
add_filter('plugin_action_links_' . OVENPRESS_TRIPLE_CACHE_PLUGIN_BASE, 'ovenpress_triple_cache_plugin_settings_link');

<?php

if (! defined('ABSPATH')) exit;

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://www.wpoven.com
 * @since      1.0.0
 *
 * @package    Wpoven_Triple_Cache
 * @subpackage Wpoven_Triple_Cache/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Wpoven_Triple_Cache
 * @subpackage Wpoven_Triple_Cache/includes
 * @author     WPOven <contact@wpoven.com>
 */
class Wpoven_Triple_Cache
{

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Wpoven_Triple_Cache_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;
	protected $options;
	private $_cleared_varnish_cache_full = false;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct()
	{
		if (defined('WPOVEN_TRIPLE_CACHE_VERSION')) {
			$this->version = WPOVEN_TRIPLE_CACHE_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'wpoven-triple-cache';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Wpoven_Triple_Cache_Loader. Orchestrates the hooks of the plugin.
	 * - Wpoven_Triple_Cache_i18n. Defines internationalization functionality.
	 * - Wpoven_Triple_Cache_Admin. Defines all hooks for the admin area.
	 * - Wpoven_Triple_Cache_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies()
	{

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wpoven-triple-cache-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wpoven-triple-cache-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-wpoven-triple-cache-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-wpoven-triple-cache-public.php';

		$this->loader = new Wpoven_Triple_Cache_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Wpoven_Triple_Cache_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale()
	{

		$plugin_i18n = new Wpoven_Triple_Cache_i18n();

		$this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks()
	{

		$plugin_admin = new Wpoven_Triple_Cache_Admin($this->get_plugin_name(), $this->get_version());

		$this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
		$this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
		$plugin_admin->admin_main($this);
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks()
	{

		$plugin_public = new Wpoven_Triple_Cache_Public($this->get_plugin_name(), $this->get_version());

		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run()
	{

		// Load the settings
		$this->options = get_option(WPOVEN_TRIPLE_CACHE_SLUG);
		// Setup Caching setup 
		$this->_detect_content_change();

		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name()
	{
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Wpoven_Triple_Cache_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader()
	{
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version()
	{
		return $this->version;
	}


	// Cleared on all updates 

	public function _clear_varnish_cache()
	{
		$urlParts = wp_parse_url(get_bloginfo('wpurl'));

		$response = wp_remote_request("http://127.0.0.1", array('method' => 'PURGE', 'headers' => array('Host' => $urlParts['host'])));
	}



	public function _process_flush_cache()
	{
		// clear varnish cache is set ? 
		if (isset($this->options['varnish_cache_enable']) && $this->options['varnish_cache_enable'] && !$this->_cleared_varnish_cache_full) {
			// Request the local vanish setup to urge all cache ( All Cache ) 
			// Do only once
			$this->_clear_varnish_cache();
			$this->_cleared_varnish_cache_full = true;
		}
	}

	private function _detect_content_change()
	{
		add_action('switch_theme', array($this, '_process_flush_cache'));
		add_action('publish_phone', array($this, '_process_flush_cache'));
		add_action('publish_post', array($this, '_process_flush_cache'));
		add_action('edit_post', array($this, '_process_flush_cache'));
		add_action('save_post', array($this, '_process_flush_cache'));
		add_action('wp_trash_post', array($this, '_process_flush_cache'));
		add_action('delete_post', array($this, '_process_flush_cache'));
		add_action('trackback_post', array($this, '_process_flush_cache'));
		add_action('pingback_post', array($this, '_process_flush_cache'));
		add_action('comment_post', array($this, '_process_flush_cache'));
		add_action('edit_comment', array($this, '_process_flush_cache'));
		add_action('wp_set_comment_status', array($this, '_process_flush_cache'));
		add_action('delete_comment', array($this, '_process_flush_cache'));
		add_action('comment_cookie_lifetime', array($this, '_process_flush_cache'));
		add_action('wp_update_nav_menu', array($this, '_process_flush_cache'));
		add_action('edit_user_profile_update', array($this, '_process_flush_cache'));
	}
}

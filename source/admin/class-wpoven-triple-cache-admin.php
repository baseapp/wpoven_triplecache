<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.wpoven.com
 * @since      1.0.0
 *
 * @package    Wpoven_Triple_Cache
 * @subpackage Wpoven_Triple_Cache/admin
 */

use PSpell\Config;

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Wpoven_Triple_Cache
 * @subpackage Wpoven_Triple_Cache/admin
 * @author     WPOven <contact@wpoven.com>
 */
class Wpoven_Triple_Cache_Admin
{

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;
	private $_wpoven_triple_cache;
	private $config   = false;
	private $objects  = array();
	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct($plugin_name, $version)
	{

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->plugin_name = $plugin_name;
		$this->version = $version;

		if (!class_exists('ReduxFramework') && file_exists(require_once plugin_dir_path(dirname(__FILE__)) . 'includes/libraries/redux-framework/redux-core/framework.php')) {
			require_once plugin_dir_path(dirname(__FILE__)) . 'includes/libraries/redux-framework/redux-core/framework.php';
		}

		if (!function_exists('is_plugin_active')) {
			include_once(ABSPATH . 'wp-admin/includes/plugin.php');
		}

		if (!$this->init_config()) {
			$this->config = $this->get_default_config();
			$this->update_config();
		}
		$this->include_libs();
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles()
	{

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Wpoven_Triple_Cache_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Wpoven_Triple_Cache_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/wpoven-triple-cache-admin.css', array(), $this->version, 'all');
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts()
	{

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Wpoven_Triple_Cache_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Wpoven_Triple_Cache_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/wpoven-triple-cache-admin.js', array('jquery'), $this->version, false);
	}

	function include_libs()
	{

		if (count($this->objects) > 0)
			return;

		$this->objects = array();

		include_once(ABSPATH . 'wp-includes/pluggable.php');

		// Composer autoload.
		if (file_exists(WPOCF_PLUGIN_PATH . 'vendor/autoload.php')) {
			require WPOCF_PLUGIN_PATH . 'vendor/autoload.php';
		}

		require_once WPOCF_PLUGIN_PATH . 'libs/cloudflare.class.php';
		require_once WPOCF_PLUGIN_PATH . 'libs/cache_controller.class.php';
		require_once WPOCF_PLUGIN_PATH . 'libs/backend.class.php';

		$this->objects = apply_filters('wpocf_include_libs_early', $this->objects);

		$this->objects['cloudflare'] = new WPOCF_Cloudflare(
			$this->get_single_config('cf_auth_mode'),
			$this->get_cloudflare_api_key(),
			$this->get_cloudflare_api_email(),
			$this->get_cloudflare_api_token(),
			$this->get_cloudflare_api_zone_id(),
			$this
		);

		$this->objects['cache_controller'] = new WPOCF_Cache_Controller(WPOCF_CACHE_BUSTER, $this);
		$this->objects['backend'] = new WPOCF_Backend($this);
		$this->objects = apply_filters('wpocf_include_libs_lately', $this->objects);
		$this->enable_wp_cli_support();
	}

	function get_default_config()
	{

		$config = array();

		// Cloudflare config
		$config['cf_zoneid']                          = '';
		$config['cf_zoneid_list']                     = array();
		$config['cf_email']                           = '';
		$config['cf_apitoken']                        = '';
		$config['cf_apikey']                          = '';
		$config['cf_token']                           = '';
		$config['cf_apitoken_domain']                 = '';
		$config['cf_old_bc_ttl']                      = '';
		$config['cf_page_rule_id']                    = '';
		$config['cf_bypass_backend_page_rule_id']     = '';
		$config['cf_bypass_backend_page_rule']        = 0;
		$config['cf_auto_purge']                      = 1;
		$config['cf_auto_purge_all']                  = 0;
		$config['cf_auto_purge_on_comments']          = 0;
		$config['cf_cache_enabled']                   = 0;
		$config['cf_maxage']                          = 31536000; // 1 year
		$config['cf_browser_maxage']                  = 60; // 1 minute
		$config['cf_post_per_page']                   = get_option('posts_per_page', 0);
		$config['cf_strip_cookies']                   = 0;
		$config['cf_worker_enabled']                   = 0;
		$config['cf_purge_only_html']                 = 0;
		$config['cf_disable_cache_purging_queue']     = 0;
		$config['cf_auto_purge_on_upgrader_process_complete'] = 0;
		// Pages
		$config['cf_excluded_urls']                 = array('/*ao_noptirocket*', '/*jetpack=comms*', '/*kinsta-monitor*', '*ao_speedup_cachebuster*', '/*removed_item*', '/my-account*', '/wc-api/*', '/edd-api/*', '/wp-json*');
		$config['cf_bypass_front_page']             = 0;
		$config['cf_bypass_pages']                  = 0;
		$config['cf_bypass_home']                   = 0;
		$config['cf_bypass_archives']               = 0;
		$config['cf_bypass_tags']                   = 0;
		$config['cf_bypass_category']               = 0;
		$config['cf_bypass_author_pages']           = 0;
		$config['cf_bypass_single_post']            = 0;
		$config['cf_bypass_feeds']                  = 1;
		$config['cf_bypass_search_pages']           = 1;
		$config['cf_bypass_404']                    = 1;
		$config['cf_bypass_logged_in']              = 1;
		$config['cf_bypass_amp']                    = 0;
		$config['cf_bypass_file_robots']            = 1;
		$config['cf_bypass_sitemap']                = 1;
		$config['cf_bypass_ajax']                   = 1;
		$config['cf_cache_control_htaccess']        = 0;
		$config['cf_browser_caching_htaccess']      = 0;
		$config['cf_auth_mode']                     = WPOCF_AUTH_MODE_API_KEY;
		//$config['cf_bypass_post']                   = 0;
		$config['cf_bypass_query_var']              = 0;
		$config['cf_bypass_wp_json_rest']           = 0;
		// Other
		$config['cf_remove_purge_option_toolbar'] = 0;
		$config['cf_disable_single_metabox'] = 1;
		$config['cf_seo_redirect'] = 0;
		$config['cf_opcache_purge_on_flush'] = 0;
		$config['cf_object_cache_purge_on_flush'] = 0;
		$config['cf_purge_roles'] = array();
		$config['cf_prefetch_urls_viewport'] = 0;
		$config['cf_prefetch_urls_viewport_timestamp'] = time();
		$config['cf_prefetch_urls_on_hover'] = 0;
		$config['cf_remove_cache_buster'] = 0;
		$config['keep_settings_on_deactivation'] = 1;

		return $config;
	}

	function get_single_config($name, $default = false)
	{
		if (isset($this->config)) {
			if (!is_array($this->config) || !isset($this->config[$name]))
				return $default;

			if (is_array($this->config[$name]))
				return $this->config[$name];

			return trim($this->config[$name]);
		}
	}


	function set_single_config($name, $value)
	{

		if (!is_array($this->config))
			$this->config = array();

		if (is_array($value))
			$this->config[trim($name)] = $value;
		else
			$this->config[trim($name)] = trim($value);
	}


	function update_config()
	{
		update_option('wpocf_general_details', $this->config);
	}


	function init_config()
	{

		$this->config = get_option('wpocf_general_details');

		if (!$this->config)
			return false;

		// If the option exists, return true
		return true;
	}


	function set_config($config)
	{
		$this->config = $config;
	}


	function get_config()
	{
		return $this->config;
	}

	function get_objects()
	{
		return $this->objects;
	}

	function get_cloudflare_api_zone_id()
	{

		if (defined('WPOCF_CF_API_ZONE_ID'))
			return WPOCF_CF_API_ZONE_ID;

		return $this->get_single_config('cf_zoneid', '');
	}


	function get_cloudflare_api_key()
	{

		if (defined('WPOCF_CF_API_KEY'))
			return WPOCF_CF_API_KEY;

		return $this->get_single_config('cf_apikey', '');
	}


	function get_cloudflare_api_email()
	{

		if (defined('WPOCF_CF_API_EMAIL'))
			return WPOCF_CF_API_EMAIL;

		return $this->get_single_config('cf_email', '');
	}


	function get_cloudflare_api_token()
	{

		if (defined('WPOCF_CF_API_TOKEN'))
			return WPOCF_CF_API_TOKEN;

		return $this->get_single_config('cf_apitoken', '');
	}

	function get_plugin_wp_content_directory()
	{

		$parts = wp_parse_url(home_url());

		return WP_CONTENT_DIR . "/wpoven-cloudflare-cache/{$parts['host']}";
	}


	function is_login_page()
	{

		return in_array($GLOBALS['pagenow'], array('wp-login.php', 'wp-register.php'));
	}


	function get_second_level_domain()
	{

		$site_hostname = wp_parse_url(home_url(), PHP_URL_HOST);

		if (is_null($site_hostname)) {
			return '';
		}

		// get the domain name from the hostname
		$site_domain = preg_replace('/^www\./', '', $site_hostname);

		return $site_domain;
	}


	function enable_wp_cli_support()
	{

		if (defined('WP_CLI') && WP_CLI && !class_exists('WPOCF_WP_CLI') && class_exists('WP_CLI_Command')) {

			require_once WPOCF_PLUGIN_PATH . 'libs/wpcli.class.php';

			$wpcli = new WPOCF_WP_CLI($this);

			WP_CLI::add_command('cfcache', $wpcli);
		}
	}


	function can_current_user_purge_cache()
	{

		if (!is_user_logged_in())
			return false;

		if (current_user_can('manage_options'))
			return true;

		$allowed_roles = $this->get_single_config('cf_purge_roles', array());

		if (count($allowed_roles) > 0) {

			$user = wp_get_current_user();

			foreach ($allowed_roles as $role_name) {

				if (in_array($role_name, (array)$user->roles))
					return true;
			}
		}

		return false;
	}


	function get_wordpress_roles()
	{

		global $wp_roles;
		$wordpress_roles = array();

		foreach ($wp_roles->roles as $role => $role_data)
			$wordpress_roles[] = $role;

		return $wordpress_roles;
	}


	function does_current_url_have_trailing_slash()
	{

		if (!preg_match('/\/$/', $_SERVER['REQUEST_URI']))
			return false;

		return true;
	}


	function is_api_request()
	{

		// Wordpress standard API
		if ((defined('REST_REQUEST') && REST_REQUEST) || strcasecmp(substr($_SERVER['REQUEST_URI'], 0, 8), '/wp-json') == 0)
			return true;

		// WooCommerce standard API
		if (strcasecmp(substr($_SERVER['REQUEST_URI'], 0, 8), '/wc-api/') == 0)
			return true;

		// WooCommerce standard API
		if (strcasecmp(substr($_SERVER['REQUEST_URI'], 0, 9), '/edd-api/') == 0)
			return true;

		return false;
	}


	function wildcard_match($pattern, $subject)
	{

		$pattern = '#^' . preg_quote($pattern) . '$#i'; // Case insensitive
		$pattern = str_replace('\*', '.*', $pattern);
		//$pattern = str_replace('\.', '.', $pattern);

		if (!preg_match($pattern, $subject, $regs))
			return false;
		return true;
	}

	// Pass parse_url() array and get the URL back as string
	function get_unparsed_url($parsed_url)
	{
		// PHP_URL_SCHEME
		$scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
		$host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
		$port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
		$user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
		$pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
		$pass     = ($user || $pass) ? "$pass@" : '';
		$path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
		$query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
		$fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
		return "{$scheme}{$user}{$pass}{$host}{$port}{$path}{$query}{$fragment}";
	}

	function get_current_lang_code()
	{

		$current_language_code = false;

		if (has_filter('wpml_current_language'))
			$current_language_code = apply_filters('wpml_current_language', null);

		return $current_language_code;
	}


	function get_permalink($post_id)
	{

		$url = get_the_permalink($post_id);

		if (has_filter('wpml_permalink'))
			$url = apply_filters('wpml_permalink', $url, $this->get_current_lang_code());

		return $url;
	}


	function get_home_url($blog_id = null, $path = '', $scheme = null)
	{

		global $pagenow;

		if (empty($blog_id) || !is_multisite()) {
			$url = get_option('home');
		} else {
			switch_to_blog($blog_id);
			$url = get_option('home');
			restore_current_blog();
		}

		if (!in_array($scheme, array('http', 'https', 'relative'), true)) {

			if (is_ssl() && !is_admin() && 'wp-login.php' !== $pagenow)
				$scheme = 'https';
			else
				$scheme = wp_parse_url($url, PHP_URL_SCHEME);
		}

		$url = set_url_scheme($url, $scheme);

		if ($path && is_string($path))
			$url .= '/' . ltrim($path, '/');

		return $url;
	}


	function home_url($path = '', $scheme = null)
	{
		return $this->get_home_url(null, $path, $scheme);
	}

	/**
	 * Add a admin menu.
	 */
	function wpoven_triple_cache_menu()
	{
		add_menu_page('WPOven Plugins', 'WPOven Plugins', '', 'wpoven', 'manage_options', plugin_dir_url(__FILE__) . '/img/logo.png');
		add_submenu_page('wpoven', 'Triple Cache', 'Triple Cache', 'manage_options', WPOVEN_TRIPLE_CACHE_SLUG);
	}

	function cf_general_settings()
	{
		$error_msg      = '';
		$domain_found   = false;
		$domain_zone_id = '';

		if ((isset($_POST['wpocf_cf_email']) && isset($_POST['wpocf_cf_apikey'])) or (isset($_POST['wpocf_cf_email']) && isset($_POST['wpocf_cf_apitoken']))) {
			$this->set_single_config('cf_auth_mode', (int) $_POST['wpocf_cf_auth_mode-select']);
			$this->set_single_config('cf_email', sanitize_email($_POST['wpocf_cf_email']));
			$this->set_single_config('cf_apikey', sanitize_text_field($_POST['wpocf_cf_apikey']));
			$this->set_single_config('cf_apitoken', sanitize_text_field($_POST['wpocf_cf_apitoken']));
			$this->set_single_config('cf_apitoken_domain', sanitize_text_field($_POST['wpocf_cf_apitoken_domain']));

			// Force refresh on Cloudflare api class
			$this->objects['cloudflare']->set_auth_mode((int) $_POST['wpocf_cf_auth_mode-select'] ?? 0);
			$this->objects['cloudflare']->set_api_key(sanitize_text_field($_POST['wpocf_cf_apikey']));
			$this->objects['cloudflare']->set_api_email(sanitize_text_field($_POST['wpocf_cf_email']));
			$this->objects['cloudflare']->set_api_token(sanitize_text_field($_POST['wpocf_cf_apitoken']));

			if (isset($_POST['wpocf_cf_apitoken_domain']) && strlen(trim($_POST['wpocf_cf_apitoken_domain'])) > 0)
				$this->objects['cloudflare']->set_api_token_domain(sanitize_text_field($_POST['wpocf_cf_apitoken_domain']));

			// Purge whole cache before passing to html only cache purging, to avoid to unable to purge already cached pages not in list
			if ($this->objects['cache_controller']->is_cache_enabled() && (int) $_POST['wpocf_cf_purge_only_html'] > 0 && $this->get_single_config('cf_purge_only_html', 0) == 0) {
				$this->objects['cache_controller']->purge_all(false, false, true);
			}

			$this->update_config();

			if (isset($_POST['wpocf_post_per_page']) && (int) $_POST['wpocf_post_per_page'] >= 0) {
				$this->set_single_config('cf_post_per_page', (int) $_POST['wpocf_post_per_page']);
			}

			if (isset($_POST['wpocf_maxage']) && (int) $_POST['wpocf_maxage'] >= 0) {
				$this->set_single_config('cf_maxage', (int) $_POST['wpocf_maxage']);
			}

			if (isset($_POST['wpocf_browser_maxage']) && (int) $_POST['wpocf_browser_maxage'] >= 0) {
				$this->set_single_config('cf_browser_maxage', (int) $_POST['wpocf_browser_maxage']);
			}

			if (isset($_POST['wpocf_cf_zoneid-select'])) {
				$this->set_single_config('cf_zoneid', trim(sanitize_text_field($_POST['wpocf_cf_zoneid-select'])));
			}

			if (isset($_POST['wpocf_cf_auto_purge'])) {
				$this->set_single_config('cf_auto_purge', (int) $_POST['wpocf_cf_auto_purge']);
			} else {
				$this->set_single_config('cf_auto_purge', 0);
			}

			if (isset($_POST['wpocf_cf_auto_purge_all'])) {
				$this->set_single_config('cf_auto_purge_all', (int) $_POST['wpocf_cf_auto_purge_all']);
			} else {
				$this->set_single_config('cf_auto_purge_all', 0);
			}

			if (isset($_POST['wpocf_cf_bypass_404'])) {
				$this->set_single_config('cf_bypass_404', (int) $_POST['wpocf_cf_bypass_404']);
			} else {
				$this->set_single_config('cf_bypass_404', 0);
			}

			if (isset($_POST['wpocf_cf_bypass_single_post'])) {
				$this->set_single_config('cf_bypass_single_post', (int) $_POST['wpocf_cf_bypass_single_post']);
			} else {
				$this->set_single_config('cf_bypass_single_post', 0);
			}

			if (isset($_POST['wpocf_cf_bypass_author_pages'])) {
				$this->set_single_config('cf_bypass_author_pages', (int) $_POST['wpocf_cf_bypass_author_pages']);
			} else {
				$this->set_single_config('cf_bypass_author_pages', 0);
			}

			if (isset($_POST['wpocf_cf_bypass_search_pages'])) {
				$this->set_single_config('cf_bypass_search_pages', (int) $_POST['wpocf_cf_bypass_search_pages']);
			} else {
				$this->set_single_config('cf_bypass_search_pages', 0);
			}

			if (isset($_POST['wpocf_cf_bypass_feeds'])) {
				$this->set_single_config('cf_bypass_feeds', (int) $_POST['wpocf_cf_bypass_feeds']);
			} else {
				$this->set_single_config('cf_bypass_feeds', 0);
			}

			if (isset($_POST['wpocf_cf_bypass_category'])) {
				$this->set_single_config('cf_bypass_category', (int) $_POST['wpocf_cf_bypass_category']);
			} else {
				$this->set_single_config('cf_bypass_category', 0);
			}

			if (isset($_POST['wpocf_cf_bypass_tags'])) {
				$this->set_single_config('cf_bypass_tags', (int) $_POST['wpocf_cf_bypass_tags']);
			} else {
				$this->set_single_config('cf_bypass_tags', 0);
			}

			if (isset($_POST['wpocf_cf_bypass_archives'])) {
				$this->set_single_config('cf_bypass_archives', (int) $_POST['wpocf_cf_bypass_archives']);
			} else {
				$this->set_single_config('cf_bypass_archives', 0);
			}

			if (isset($_POST['wpocf_cf_bypass_home'])) {
				$this->set_single_config('cf_bypass_home', (int) $_POST['wpocf_cf_bypass_home']);
			} else {
				$this->set_single_config('cf_bypass_home', 0);
			}

			if (isset($_POST['wpocf_cf_bypass_front_page'])) {
				$this->set_single_config('cf_bypass_front_page', (int) $_POST['wpocf_cf_bypass_front_page']);
			} else {
				$this->set_single_config('cf_bypass_front_page', 0);
			}

			if (isset($_POST['wpocf_cf_bypass_pages'])) {
				$this->set_single_config('cf_bypass_pages', (int) $_POST['wpocf_cf_bypass_pages']);
			} else {
				$this->set_single_config('cf_bypass_pages', 0);
			}

			if (isset($_POST['wpocf_cf_bypass_amp'])) {
				$this->set_single_config('cf_bypass_amp', (int) $_POST['wpocf_cf_bypass_amp']);
			} else {
				$this->set_single_config('cf_bypass_amp', 0);
			}

			if (isset($_POST['wpocf_cf_bypass_ajax'])) {
				$this->set_single_config('cf_bypass_ajax', (int) $_POST['wpocf_cf_bypass_ajax']);
			} else {
				$this->set_single_config('cf_bypass_ajax', 0);
			}

			if (isset($_POST['wpocf_cf_bypass_query_var'])) {
				$this->set_single_config('cf_bypass_query_var', (int) $_POST['wpocf_cf_bypass_query_var']);
			} else {
				$this->set_single_config('cf_bypass_query_var', 0);
			}

			if (isset($_POST['wpocf_cf_bypass_wp_json_rest'])) {
				$this->set_single_config('cf_bypass_wp_json_rest', (int) $_POST['wpocf_cf_bypass_wp_json_rest']);
			} else {
				$this->set_single_config('cf_bypass_wp_json_rest', 0);
			}

			if (isset($_POST['wpocf_cf_bypass_sitemap'])) {
				$this->set_single_config('cf_bypass_sitemap', (int) $_POST['wpocf_cf_bypass_sitemap']);
			} else {
				$this->set_single_config('cf_bypass_sitemap', 0);
			}

			if (isset($_POST['wpocf_cf_bypass_file_robots'])) {
				$this->set_single_config('cf_bypass_file_robots', (int) $_POST['wpocf_cf_bypass_file_robots']);
			} else {
				$this->set_single_config('cf_bypass_file_robots', 0);
			}

			// Strip cookies
			if (isset($_POST['wpocf_cf_strip_cookies'])) {
				$this->set_single_config('cf_strip_cookies', (int) $_POST['wpocf_cf_strip_cookies']);
			}

			// Htaccess
			if (isset($_POST['wpocf_cf_cache_control_htaccess'])) {
				$this->set_single_config('cf_cache_control_htaccess', (int) $_POST['wpocf_cf_cache_control_htaccess']);
			}

			// Purge HTML pages only
			if (isset($_POST['wpocf_cf_purge_only_html'])) {
				$this->set_single_config('cf_purge_only_html', (int) $_POST['wpocf_cf_purge_only_html']);
			}

			// Disable cache purging using queue
			if (isset($_POST['wpocf_cf_disable_cache_purging_queue'])) {
				$this->set_single_config('cf_disable_cache_purging_queue', (int) $_POST['wpocf_cf_disable_cache_purging_queue']);
			}

			// Comments
			if (isset($_POST['wpocf_cf_auto_purge_on_comments'])) {
				$this->set_single_config('cf_auto_purge_on_comments', (int) $_POST['wpocf_cf_auto_purge_on_comments']);
			}

			// Purge on upgrader process complete
			if (isset($_POST['wpocf_cf_auto_purge_on_upgrader_process_complete'])) {
				$this->set_single_config('cf_auto_purge_on_upgrader_process_complete', (int) $_POST['wpocf_cf_auto_purge_on_upgrader_process_complete']);
			}

			// URLs to exclude from cache
			if (isset($_POST['wpocf_cf_excluded_urls'])) {

				$excluded_urls = array();

				if (strlen(trim($_POST['wpocf_cf_excluded_urls'])) > 0) {
					$_POST['wpocf_cf_excluded_urls'] .= "\n";
				}
				$parsed_excluded_urls = explode("\n", $_POST['wpocf_cf_excluded_urls']);

				if (isset($_POST['wpocf_cf_bypass_woo_checkout_page']) && ((int) $_POST['wpocf_cf_bypass_woo_checkout_page']) > 0 && function_exists('wc_get_checkout_url')) {
					$parsed_excluded_urls[] = wc_get_checkout_url() . '*';
				}

				if (isset($_POST['wpocf_cf_bypass_woo_cart_page']) && ((int) $_POST['wpocf_cf_bypass_woo_cart_page']) > 0 && function_exists('wc_get_cart_url')) {
					$parsed_excluded_urls[] = wc_get_cart_url() . '*';
				}

				if (isset($_POST['wpocf_cf_bypass_edd_checkout_page']) && ((int) $_POST['wpocf_cf_bypass_edd_checkout_page']) > 0 && function_exists('edd_get_checkout_uri')) {
					$parsed_excluded_urls[] = edd_get_checkout_uri() . '*';
				}

				foreach ($parsed_excluded_urls as $single_url) {

					if (trim($single_url) == '') {
						continue;
					}

					$parsed_url = wp_parse_url(str_replace(array("\r", "\n"), '', $single_url));

					if ($parsed_url && isset($parsed_url['path'])) {

						$uri = $parsed_url['path'];

						// Force trailing slash
						if (strlen($uri) > 1 && $uri[strlen($uri) - 1] != '/' && $uri[strlen($uri) - 1] != '*') {
							$uri .= '/';
						}
						if (isset($parsed_url['query'])) {
							$uri .= "?{$parsed_url['query']}";
						}

						if (!in_array($uri, $excluded_urls)) {
							$excluded_urls[] = $uri;
						}
					}
				}

				if (count($excluded_urls) > 0)
					$this->set_single_config('cf_excluded_urls', $excluded_urls);
				else
					$this->set_single_config('cf_excluded_urls', array());
			}

			if (count($this->get_single_config('cf_zoneid_list', array())) == 0 && ($zone_id_list = $this->objects['cloudflare']->get_zone_id_list($error_msg))) {

				$this->set_single_config('cf_zoneid_list', $zone_id_list);
				if ($this->get_single_config('cf_auth_mode', WPOCF_AUTH_MODE_API_KEY) == WPOCF_AUTH_MODE_API_TOKEN && isset($_POST['wpocf_cf_apitoken_domain']) && strlen(trim($_POST['wpocf_cf_apitoken_domain'])) > 0) {
					$this->set_single_config('cf_zoneid', $zone_id_list[$this->get_single_config('cf_apitoken_domain', '')]);
				}
			}

			$this->objects['cache_controller']->write_htaccess($error_msg);

			$this->update_config();
			$success_msg = __('Settings updated successfully', 'WPOven Triple Cache');
		}

		$zone_id_list = $this->get_single_config('cf_zoneid_list', array());

		if (is_array($zone_id_list) && count($zone_id_list) > 0) {

			// If the domain name is found in the zone list, I will show it only instead of full domains list
			$current_domain = str_replace(array('/', 'http:', 'https:', 'www.'), '', site_url());

			foreach ($zone_id_list as $zone_id_name => $zone_id) {

				if ($zone_id_name == $current_domain) {
					$domain_found = true;
					$domain_zone_id = $zone_id;
					break;
				}
			}
		} else {
			$zone_id_list = array();
		}

		$list = array();
		if ($domain_found) {
			$list = array(
				$domain_zone_id => $current_domain
			);
		} else {
			foreach ($zone_id_list as $zone_id_name => $zone_id) {
				$list[$zone_id] = $zone_id_name;
			}
		}

		$options = get_option(WPOVEN_TRIPLE_CACHE_SLUG);
		$cf_auth_mode = $options['wpocf_cf_auth_mode'] ?? null;
		$domain_key_domain = $options['wpocf_cf_zoneid'] ?? null;
		$api_token_domain = $options['wpocf_cf_apitoken_domain'] ?? null;
		$result = array();

		$cache_controller = $this->cf_cache_controller();

		$cf_authentication_mode = array(
			'id'          => 'wpocf_cf_auth_mode',
			'type'        => 'select',
			'title'       => 'Authentication mode',
			'options'     => array(
				WPOCF_AUTH_MODE_API_KEY  => 'API Key ',
				WPOCF_AUTH_MODE_API_TOKEN  => 'API Token',
			),
			'default'   => WPOCF_AUTH_MODE_API_KEY,
			'desc'     => 'Authentication mode to use to connect to your Cloudflare account.'
		);
		$cf_email = array(
			'id'      => 'wpocf_cf_email',
			'type'    => 'text',
			'validate' => 'csf_validate_email',
			'title'   => 'Cloudflare e-mail<strong style="color:red;">*</strong>',
			'desc'     => 'The email address you use to log in to Cloudflare.'
		);
		$cf_apikey = array(
			'id'      => 'wpocf_cf_apikey',
			'type'    => 'password',
			'title'   => 'Cloudflare API Key<strong style="color:red;">*</strong>',
			'required' => array('wpocf_cf_auth_mode', 'equals', WPOCF_AUTH_MODE_API_KEY),
			'desc'     => 'The Global API Key is extracted from your Cloudflare account.'
		);
		$cf_apitoken = array(
			'id'      => 'wpocf_cf_apitoken',
			'type'    => 'password',
			'title'   => 'Cloudflare API Token<strong style="color:red;">*</strong>',
			'required' => array('wpocf_cf_auth_mode', 'equals', WPOCF_AUTH_MODE_API_TOKEN),
			'desc'     => 'The API Token is extracted from your Cloudflare account.'
		);
		$cf_apitoken_domain = array(
			'id'          => 'wpocf_cf_apitoken_domain',
			'type'        => 'text',
			'title'       => 'Cloudflare Domain Name<strong style="color:red;">*</strong>',
			// 'default'     => $this->get_single_config('cf_apitoken_domain', $this->get_second_level_domain()),
			'required' => array('wpocf_cf_auth_mode', 'equals', WPOCF_AUTH_MODE_API_TOKEN),
			'desc'     => 'Add the domain name for which you want to enable the cache exactly as reported on Cloudflare, then click on Save Changes.'
		);

		$cf_apiKey_domain = array(
			'id'          => 'wpocf_cf_zoneid',
			'type'        => 'select',
			'title'       => 'Cloudflare Domain Name<strong style="color:red;">*</strong>',
			'placeholder' => 'Select an option',
			'options'     => $list,
			'required' => array('wpocf_cf_auth_mode', 'equals', WPOCF_AUTH_MODE_API_KEY),
			'desc'     => 'Select the domain for which you want to enable the cache and click on Save Changes.'
		);

		$cache_controller_settings_info = array(
			'id'      => 'cache-controller',
			'type'    => 'info',
			'desc'    => '<div>
							<h2>Enter your Cloudflare API key and e-mail</h2>
							<p>You do not know how to do it? Follow these simple four steps:</p>
							<ol>
								<li><a href="https://dash.cloudflare.com/login" target="_blank">Sign in to your Cloudflare account </a> and select My Profile.</li>
								<li>Navigate to API tokens, scroll to API Keys, and click View next to Global API Key.</li>
								<li>To see the API key, click the View button and enter your Cloudflare password.</li>
								<li>Input both the API key and email address into the form below and click Save Changes.</li>
								<li>Select the domain for which you want to enable the cache and click on Save Changes.</li>
							</ol>
                        </div><br>'
		);

		if ($domain_key_domain && $cf_auth_mode == 0) {
			$result[] = $cache_controller;
		} else if ($api_token_domain && $cf_auth_mode == 1) {
			$result[] = $cache_controller;
		} else {
			$result[] = $cache_controller_settings_info;
		}

		$result[] = $cf_authentication_mode;
		$result[] = $cf_email;
		$result[] = $cf_apikey;
		$result[] = $cf_apitoken;
		$result[] = $cf_apitoken_domain;
		if ($list) {
			$result[] = $cf_apiKey_domain;
		}


		return $result;
	}

	function cf_cache_controller()
	{
		//$nonce = wp_create_nonce('wpocf_index_nonce');

		if (!$this->objects['cache_controller']->is_cache_enabled()) {
			$cf_cache_contoller = array(
				'id'      => 'cache-controller',
				'type'    => 'info',
				'desc' => '<div style="text-align: center;">
				<h2 style="text-align: center;">Enable Page Caching</h2>
				<p style="text-align: center;">Now you can set up and activate the page cache to enhance this website speed.</p>
				<br><button type="submit" class="cache-controller button-primary wpocf_hide" style="width: 120px; margin-right: 10px; text-align:center;" id="wpocf_submit_enable_page_cache" value="enable">ENABLE CACHE</button>
				</div><br><br>',
			);
		} else {
			$cf_cache_contoller = array(
				'id'      => 'cache-controller',
				'type'    => 'info',
				'desc' => '<div style="text-align: center;"><h2>Cache Actions</h2><br>
					<button type="submit" class="cache-controller button-primary" style="width: 120px; margin-right: 10px; text-align:center;" id="wpocf_submit_disable_page_cache" value="disable">DISABLE CACHE</button>
					<button type="submit" class="cache-controller button-primary" style="width: 120px; margin-right: 10px; text-align:center;" id="wpocf_submit_purge_cache" value="purge">PURGE CACHE</button>
					<button type="submit" class="cache-controller button-primary" style="width: 120px; margin-right: 10px; text-align:center;" id="wpocf_submit_test_cache" value="test">TEST CACHE</button>
					<button type="submit" class="cache-controller button-primary csf-warning-prinmary" style="width: 120px; margin-right: 10px; text-align:center;" id="wpocf_submit_reset_all" value="reset">RESET ALL</button>
				</div><br><br>',
			);
		}

		return $cf_cache_contoller;
	}

	function cf_cache_settings()
	{
		$result = array();

		$cf_maxage = array(
			'id'      => 'wpocf_maxage',
			'type'    => 'text',
			'title'   => 'Cloudflare Cache-Control max-age',
			'default' => '31536000',
			'desc'    => "Must be greater than zero. Recommended 31536000 (1 year)"
		);

		$cf_browser_age = array(
			'id'      => 'wpocf_browser_maxage',
			'type'    => 'text',
			'title'   => 'Browser Cache-Control max-age',
			'default' => '60',
			'desc'    => "Must be greater than zero. Recommended a value between 60 and 600"
		);

		$cf_excluded_urls = array(
			'id'      => 'wpocf_cf_excluded_urls',
			'type'    => 'textarea',
			'title'   => 'Prevent the following URIs to be cached',
			'desc'    => 'One URI per line. You can use the * for wildcard URLs.
			Example: /my-page
			/my-main-page/my-sub-page
			/my-main-page*',
			'default' => '/*ao_noptirocket*
/*jetpack=comms*
/*kinsta-monitor*
*ao_speedup_cachebuster*
/*removed_item*
/my-account*
/wc-api/*
/edd-api/*
/wp-json*
/checkout/*
/cart/*',

		);
		$cf_strip_cookies = array(
			'id'    => 'wpocf_cf_strip_cookies',
			'type'  => 'switch',
			'title' => 'Strip response cookies on pages that should be cached',
			'text_on'  => 'Yes',
			'text_off' => 'No',
			'desc'    => 'Cloudflare will not cache when there are cookies in responses unless you strip out them to overwrite the behavior.
			If the cache does not work due to response cookies and you are sure that these cookies are not essential for the website to works, enable this option.'
		);
		$cf_auto_purge_on_comments = array(
			'id'    => 'wpocf_cf_auto_purge_on_comments',
			'type'  => 'switch',
			'title' => 'Purge single posts cache on comment changes',
			'text_on'  => 'Yes',
			'text_off' => 'No',
			'desc'    => 'Automatically purge single post cache when a new comment is inserted into the database or when a comment is approved or deleted.'
		);
		$cf_auto_purge_on_upgrader_process_complete = array(
			'id'    => 'wpocf_cf_auto_purge_on_upgrader_process_complete',
			'type'  => 'switch',
			'title' => 'Automatically purge the cache when the upgrader process is complete',
			'text_on'  => 'Yes',
			'text_off' => 'No',
			'desc'    => ''
		);
		$cf_post_per_page = array(
			'id'      => 'wpocf_post_per_page',
			'type'    => 'text',
			'title'   => 'Posts per page',
			'default' => '10',
			'desc'    => 'Enter how many posts per page (or category) the theme shows to your users. It will be use to clean up the pagination on cache purge.'
		);
		$cf_cache_control_htaccess = array(
			'id'    => 'wpocf_cf_cache_control_htaccess',
			'type'  => 'switch',
			'title' => 'Overwrite the cache-control header for Wordpress pages using web server rules',
			'text_on'  => 'Yes',
			'text_off' => 'No',
			'desc'    => ''
		);
		$cf_bypass_backend_page_rule = array(
			'id'    => 'wpocf_cf_bypass_backend_page_rule',
			'type'  => 'switch',
			'title' => 'Force cache bypassing for backend with an additional Cloudflare page rule',
			'text_on'  => 'Yes',
			'text_off' => 'No',
			'desc'    => 'Read here: by default, all back-end URLs are not cached thanks to some response headers, but if for some circumstances your backend pages are still cached, you can enable this option which will add an additional page rule on Cloudflare to force cache bypassing for the whole Wordpress backend directly from Cloudflare. This option will be ignored if worker mode is enabled.'
		);
		$cf_purge_only_html = array(
			'id'    => 'wpocf_cf_purge_only_html',
			'type'  => 'switch',
			'title' => 'Purge HTML pages only',
			'text_on'  => 'Yes',
			'text_off' => 'No',
			'desc'    => 'Purge only the cached HTML pages instead of the entire Cloudflare cache, which includes both assets and pages.'
		);
		$cf_disable_cache_purging_queue = array(
			'id'    => 'wpocf_cf_disable_cache_purging_queue',
			'type'  => 'switch',
			'title' => 'Disable cache purging using queue',
			'text_on'  => 'Yes',
			'text_off' => 'No',
			'desc'    => 'This plugin waits 10 seconds before purging the cache to avoid too many requests from other plugins. It uses a WordPress scheduled event. If you see interval errors, enable the provided option to turn off this delay.'
		);

		$select = array(
			'id'          => 'do-not-cache',
			'type'        => 'select',
			'title'       => 'Cache Behavior Settings',
			'placeholder' => 'Select an option',
			'chosen'      => true,
			'multi'    => true,
			'options'     => array(
				'wpocf_cf_auto_purge'  => 'Purge cache for related pages only - (recommended)',
				'wpocf_cf_auto_purge_all'  => 'Purge whole cache',
				'wpocf_cf_bypass_404'  => 'Page 404 (is_404) - (recommended)',
				'wpocf_cf_bypass_single_post'  => 'Single posts (is_single)',
				'wpocf_cf_bypass_pages'  => 'Pages (is_page)',
				'wpocf_cf_bypass_front_page'  => 'Front Page (is_front_page)',
				'wpocf_cf_bypass_home'  => 'Home (is_home)',
				'wpocf_cf_bypass_archives'  => 'Archives (is_archive)',
				'wpocf_cf_bypass_tags'  => 'Tags (is_tag)',
				'wpocf_cf_bypass_category'  => 'Categories (is_category)',
				'wpocf_cf_bypass_feeds'  => 'Feeds (is_feed) - (recommended)',
				'wpocf_cf_bypass_search_pages'  => 'Search Pages (is_search) - (recommended)',
				'wpocf_cf_bypass_author_pages'  => 'Author Pages (is_author)',
				'wpocf_cf_bypass_amp'  => 'AMP pages',
				'wpocf_cf_bypass_ajax'  => 'Ajax requests - (recommended)',
				'wpocf_cf_bypass_query_var'  => 'Pages with query args',
				'wpocf_cf_bypass_wp_json_rest'  => 'WP JSON endpoints',
				'wpocf_cf_bypass_sitemap'  => 'XML sitemaps - (recommended)',
				'wpocf_cf_bypass_file_robots'  => 'Robots.txt - (recommended)',
			),
			'desc'    => 'Do not cache the following static & dynamic contents.'
		);

		$result[] = $cf_maxage;
		$result[] = $cf_browser_age;
		$result[] = $select;
		$result[] = $cf_excluded_urls;
		$result[] = $cf_post_per_page;
		$result[] = $cf_strip_cookies;
		$result[] = $cf_auto_purge_on_comments;
		$result[] = $cf_auto_purge_on_upgrader_process_complete;
		$result[] = $cf_cache_control_htaccess;
		//$result[] = $cf_bypass_backend_page_rule;
		$result[] = $cf_purge_only_html;
		$result[] = $cf_disable_cache_purging_queue;


		return $result;
	}

	/**
	 * Set WPOven Triple Cache admin page.
	 */
	function setup_gui()
	{

		if (!class_exists('Redux')) {
			return;
		}
		$options = get_option(WPOVEN_TRIPLE_CACHE_SLUG);
		$opt_name = WPOVEN_TRIPLE_CACHE_SLUG;

		Redux::disable_demo();

		$args = array(
			'opt_name'                  => $opt_name,
			'display_name'              => 'WPOven Triple Cache',
			'display_version'           => ' ',
			//'menu_type'                 => 'menu',
			'allow_sub_menu'            => true,
			//	'menu_title'                => esc_html__('Triple Cache', 'WPOven Triple Cache'),
			'page_title'                => esc_html__('WPOven Triple Cache', 'WPOven Triple Cache'),
			'disable_google_fonts_link' => false,
			'admin_bar'                 => false,
			'admin_bar_icon'            => 'dashicons-portfolio',
			'admin_bar_priority'        => 90,
			'global_variable'           => $opt_name,
			'dev_mode'                  => false,
			'customizer'                => false,
			'open_expanded'             => false,
			'disable_save_warn'         => false,
			'page_priority'             => 90,
			'page_parent'               => 'themes.php',
			'page_permissions'          => 'manage_options',
			'menu_icon'                 => plugin_dir_url(__FILE__) . '/img/logo.png',
			'last_tab'                  => '',
			'page_icon'                 => 'icon-themes',
			'page_slug'                 => $opt_name,
			'save_defaults'             => false,
			'default_show'              => false,
			'default_mark'              => '',
			'show_import_export'        => false,
			'transient_time'            => 60 * MINUTE_IN_SECONDS,
			'output'                    => false,
			'output_tag'                => false,
			//'footer_credit'             => 'Please rate WPOven Triple Cache ★★★★★ on WordPress.org to support us. Thank you!',
			'footer_credit'             => ' ',
			'use_cdn'                   => false,
			'admin_theme'               => 'wp',
			'flyout_submenus'           => true,
			'font_display'              => 'swap',
			'hide_reset'                => true,
			'database'                  => '',
			'network_admin'           => '',
			'search'                    => false,
			'hide_expand'            => true,
		);

		Redux::set_args($opt_name, $args);

		Redux::set_section(
			$opt_name,
			array(
				'title'      => esc_html__('Cloudflare Settings', 'WPOven Triple Cache'),
				'id'         => 'general',
				'subsection' => false,
				'icon'       => 'el el-cloud',
				'heading'    => 'CLOUDFLARE GENERAL SETTINGS',
				'fields'     => $this->cf_general_settings(),
			)
		);
		Redux::set_section(
			$opt_name,
			array(
				'title'      => esc_html__('Cache Settings', 'WPOven Triple Cache'),
				'id'         => 'cache',
				'subsection' => false,
				'heading'    => 'CACHE LIFETIME SETTINGS',
				'fields'     => $this->cf_cache_settings(),
				'icon'       => 'fa-solid fa-database'
			)
		);
	}

	/**
	 * Hook to add the admin menu.
	 */
	public function admin_main(Wpoven_Triple_Cache $wpoven_triple_cache)
	{
		$this->_wpoven_triple_cache = $wpoven_triple_cache;
		add_action('admin_menu', array($this, 'wpoven_triple_cache_menu'));
		$this->setup_gui();
	}
}

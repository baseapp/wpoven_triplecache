<?php

defined('ABSPATH') || die('Cheating&#8217; uh?');

class WPOCF_Cache_Controller
{

    private $main_instance = null;

    private $objects = false;

    private $skip_cache = false;
    private $purge_all_already_done = false;
    private $cache_buster = 'wpocf';
    private $htaccess_path = '';

    function __construct($cache_buster, $main_instance)
    {

        $this->cache_buster  = $cache_buster;
        $this->main_instance = $main_instance;

        if (!function_exists('get_home_path'))
            require_once ABSPATH . 'wp-admin/includes/file.php';

        $this->htaccess_path = get_home_path() . '.htaccess';

        $this->actions();
    }


    function actions()
    {

        // Purge cache cronjob
        add_action('wpocf_cache_purge_cron', array($this, 'purge_cache_queue_job'));
        add_filter('cron_schedules', array($this, 'purge_cache_queue_custom_interval'));
        add_action('shutdown', array($this, 'purge_cache_queue_start_cronjob'), PHP_INT_MAX);

        // SEO redirect for all URLs that for any reason have been indexed together with the cache buster
        if ($this->main_instance->get_single_config('cf_seo_redirect', 1) > 0) {
            add_action('init', array($this, 'redirect_301_real_url'), 0);
        }

        add_action('wp_footer',    array($this, 'inject_cache_buster_js_code'), PHP_INT_MAX);
        add_action('admin_footer', array($this, 'inject_cache_buster_js_code'), PHP_INT_MAX);

        // Auto prefetch URLs
        add_action('wp_footer',    array($this, 'prefetch_urls'), PHP_INT_MAX);

        // Ajax clear whole cache
        add_action('wp_ajax_wpocf_purge_whole_cache', array($this, 'ajax_purge_whole_cache'));

        // Force purge everything
        add_action('wp_ajax_wpocf_purge_everything', array($this, 'ajax_purge_everything'));

        // Ajax clear single post cache
        add_action('wp_ajax_wpocf_purge_single_post_cache', array($this, 'ajax_purge_single_post_cache'));

        // Ajax reset all
        add_action('wp_ajax_wpocf_reset_all', array($this, 'ajax_reset_all'));

        // This sets response headers for backend
        add_action('init', array($this, 'setup_response_headers_backend'), 0);

        // These set response headers for frontend
        add_action('send_headers', array($this, 'bypass_cache_on_init'), PHP_INT_MAX);
        add_action('template_redirect', array($this, 'apply_cache'), PHP_INT_MAX);


        // Autoptimize actions
        if ($this->main_instance->get_single_config('cf_autoptimize_purge_on_cache_flush', 0) > 0)
            add_action('autoptimize_action_cachepurged', array($this, 'autoptimize_hooks'), PHP_INT_MAX);

        // Purge when upgrader process is complete
        if ($this->main_instance->get_single_config('cf_auto_purge_on_upgrader_process_complete', 0) > 0)
            add_action('upgrader_process_complete', array($this, 'purge_on_plugin_update'), PHP_INT_MAX);

        // Bypass WP JSON REST
        if ($this->main_instance->get_single_config('cf_bypass_wp_json_rest', 0) > 0)
            add_filter('rest_send_nocache_headers', '__return_true');

        // Purge cache on comments
        add_action('transition_comment_status', array($this, 'purge_cache_when_comment_is_approved'), PHP_INT_MAX, 3);
        add_action('comment_post',              array($this, 'purge_cache_when_new_comment_is_added'), PHP_INT_MAX, 3);
        add_action('delete_comment',            array($this, 'purge_cache_when_comment_is_deleted'), PHP_INT_MAX);

        // Programmatically purge the cache via action
        add_action('wpocf_purge_cache', array($this, 'purge_cache_programmatically'), PHP_INT_MAX, 1);

        $purge_actions = array(
            'wp_update_nav_menu',                                     // When a custom menu is updated
            'update_option_theme_mods_' . get_option('stylesheet'), // When any theme modifications are updated
            'avada_clear_dynamic_css_cache',                          // When Avada theme purge its own cache
            'switch_theme',                                           // When user changes the theme
            'customize_save_after',                                   // Edit theme
            'permalink_structure_changed',                            // When permalink structure is update
        );

        foreach ($purge_actions as $action) {
            add_action($action, array($this, 'purge_cache_on_theme_edit'), PHP_INT_MAX);
        }

        $purge_actions = array(
            'deleted_post',                     // Delete a post
            'wp_trash_post',                    // Before a post is sent to the Trash
            'clean_post_cache',                 // After a post’s cache is cleaned
            'edit_post',                        // Edit a post - includes leaving comments
            'delete_attachment',                // Delete an attachment - includes re-uploading
            'elementor/editor/after_save',      // Elementor edit
            'elementor/core/files/clear_cache', // Elementor clear cache
        );

        foreach ($purge_actions as $action) {
            add_action($action, array($this, 'purge_cache_on_post_edit'), PHP_INT_MAX, 2);
        }

        add_action('transition_post_status', array($this, 'purge_cache_when_post_is_published'), PHP_INT_MAX, 3);

        // Metabox
        if ($this->main_instance->get_single_config('cf_disable_single_metabox', 0) == 0) {
            add_action('add_meta_boxes', array($this, 'add_metaboxes'), PHP_INT_MAX);
            add_action('save_post', array($this, 'wpocf_cache_mbox_save_values'), PHP_INT_MAX);
        }

        // Ajax enable page cache
        add_action('wp_ajax_wpocf_enable_page_cache', array($this, 'ajax_enable_page_cache'));

        // Ajax disable page cache
        add_action('wp_ajax_wpocf_disable_page_cache', array($this, 'ajax_disable_page_cache'));

        // Add wp_redirect filter to adding cache buster for logged in users
        add_filter('wp_redirect', array($this, 'wp_redirect_filter'), PHP_INT_MAX, 2);
    }


    function get_cache_buster()
    {

        return $this->cache_buster;
    }


    function add_metaboxes()
    {

        $allowed_post_types = apply_filters('wpocf_bypass_cache_metabox_post_types', ['post', 'page']);

        add_meta_box(
            'wpocf_cache_mbox',
            __('Cloudflare Page Cache Settings', 'WPOven Triple Cache'),
            array($this, 'wpocf_cache_mbox_callback'),
            $allowed_post_types,
            'side'
        );
    }


    function wpocf_cache_mbox_callback($post)
    {

        $bypass_cache = (int) get_post_meta($post->ID, 'wpocf_bypass_cache', true);

?>

        <label for="wpocf_bypass_cache"><?php _e('Bypass the cache for this page', 'WPOven Triple Cache'); ?></label>
        <select name="wpocf_bypass_cache">
            <option value="0" <?php if ($bypass_cache == 0) echo 'selected'; ?>><?php _e('No', 'WPOven Triple Cache'); ?></option>
            <option value="1" <?php if ($bypass_cache == 1) echo 'selected'; ?>><?php _e('Yes', 'WPOven Triple Cache'); ?></option>
        </select>

    <?php

    }


    function wpocf_cache_mbox_save_values($post_id)
    {

        if (array_key_exists('wpocf_bypass_cache', $_POST)) {
            update_post_meta($post_id, 'wpocf_bypass_cache', $_POST['wpocf_bypass_cache']);
        }
    }





    function redirect_301_real_url()
    {

        // For non logged-in users, only redirect when the request URL is not from a CRON job
        if (!is_user_logged_in() && (isset($_GET['wpocf-preloader']) || isset($_GET['wpocf-purge-all']))) return;

        // For non CRON job URLs, we will redirect
        if (!is_user_logged_in() && !empty($_SERVER['QUERY_STRING'])) {
            if (strlen($_SERVER['QUERY_STRING']) > 0 && strpos($_SERVER['QUERY_STRING'], $this->get_cache_buster()) !== false) {

                // Build the full URL
                $parts = wp_parse_url(home_url());
                $current_uri = "{$parts['scheme']}://{$parts['host']}" . add_query_arg(NULL, NULL);

                // Strip out the cache buster
                $parsed = wp_parse_url($current_uri);
                $query_string = $parsed['query'];

                parse_str($query_string, $params);

                unset($params[$this->get_cache_buster()]);
                $query_string = http_build_query($params);

                // Rebuild the full URL without the cache buster
                $current_uri = "{$parts['scheme']}://{$parts['host']}";

                if (isset($parsed['path']))
                    $current_uri .= $parsed['path'];

                if (strlen($query_string) > 0)
                    $current_uri .= "?{$query_string}";

                // SEO redirect
                wp_redirect($current_uri, 301);
                die();
            }
        }
    }


    function setup_response_headers_filter($headers)
    {

        if (!isset($headers['Wpoven-Cache'])) {

            $this->objects = $this->main_instance->get_objects();

            if (!$this->is_cache_enabled()) {
                $headers['Wpoven-Cache'] = 'disabled';
            } else if ($this->is_url_to_bypass() || $this->can_i_bypass_cache()) {
                $headers['Cache-Control'] = 'no-store, no-cache, must-revalidate, max-age=0';
                $headers['Wpoven-Cache-Cache-Control'] = 'no-store, no-cache, must-revalidate, max-age=0';
                $headers['Wpoven-Cache'] = 'no-cache';
                $headers['Pragma'] = 'no-cache';
                $headers['Expires'] = gmdate('D, d M Y H:i:s \G\M\T', time());
            } else {


                $headers['Cache-Control'] = $this->get_cache_control_value(); // Used by Cloudflare
                $headers['Wpoven-Cache-Cache-Control'] = $this->get_cache_control_value(); // Used by all
                $headers['Wpoven-Cache-Active'] = '1'; // Used by CF worker
                $headers['Wpoven-Cache'] = 'cache';
            }
        }

        return $headers;
    }


    function setup_response_headers_backend()
    {

        $this->objects = $this->main_instance->get_objects();

        if (is_admin()) {
            if (!$this->is_cache_enabled()) {

                add_filter('nocache_headers', function () {
                    return array(
                        'Wpoven-Cache' => 'disabled'
                    );
                }, PHP_INT_MAX);
            } else {
                add_filter('nocache_headers', function () {
                    return array(
                        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                        'Wpoven-Cache-Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                        'Wpoven-Cache' => 'no-cache',
                        'Pragma' => 'no-cache',
                        'Expires' => gmdate('D, d M Y H:i:s \G\M\T', time())
                    );
                }, PHP_INT_MAX);
            }
            return;
        }

        if (!$this->is_cache_enabled()) {
            add_filter('nocache_headers', function () {

                return array(
                    'Wpoven-Cache' => 'disabled'
                );
            }, PHP_INT_MAX);
        } else if ($this->is_url_to_bypass() || $this->can_i_bypass_cache()) {
            add_filter('nocache_headers', function () {

                return array(
                    'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                    'Wpoven-Cache-Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                    'Wpoven-Cache' => 'no-cache',
                    'Pragma' => 'no-cache',
                    'Expires' => gmdate('D, d M Y H:i:s \G\M\T', time())
                );
            }, PHP_INT_MAX);
        } else {
            add_filter('nocache_headers', function () {

                return array(
                    'Cache-Control' => $this->get_cache_control_value(), // Used by Cloudflare
                    'Wpoven-Cache-Cache-Control' => $this->get_cache_control_value(), // Used by all
                    'Wpoven-Cache-Active' => '1', // Used by CF Worker
                    'Wpoven-Cache' => 'cache'
                );
            }, PHP_INT_MAX);
        }
    }

    function bypass_cache_on_init()
    {

        if (is_admin())
            return;

        $this->objects = $this->main_instance->get_objects();

        if (!$this->is_cache_enabled()) {
            header('Wpoven-Cache: disabled');
            return;
        }

        if ($this->skip_cache)
            return;

        header_remove('Pragma');
        header_remove('Expires');
        header_remove('Cache-Control');

        if ($this->is_url_to_bypass()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time()));
            header('Wpoven-Cache: no-cache');
            header('Wpoven-Cache-Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            $this->skip_cache = true;
            return;
        }
    }


    function apply_cache()
    {

        if (is_admin())
            return;

        $this->objects = $this->main_instance->get_objects();

        if (!$this->is_cache_enabled()) {
            header('Wpoven-Cache: disabled');
            header('Wpoven-Cache-Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            return;
        }

        if ($this->skip_cache) {
            return;
        }

        if ($this->can_i_bypass_cache()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time()));
            header('Wpoven-Cache: no-cache');
            header('Wpoven-Cache-Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            return;
        }

        if ($this->main_instance->get_single_config('cf_strip_cookies', 0) > 0) {
            header_remove('Set-Cookie');
        }

        header_remove('Pragma');
        header_remove('Expires');
        header_remove('Cache-Control');
        header('Cache-Control: ' . $this->get_cache_control_value());
        header('Wpoven-Cache: cache');
        header('Wpoven-Cache-Active: 1');
        header('Wpoven-Cache-Cache-Control: ' . $this->get_cache_control_value());
    }


    function purge_all($disable_preloader = false, $queue_mode = true, $force_purge_everything = false)
    {

        $this->objects = $this->main_instance->get_objects();
        $error = '';

        if ($queue_mode && $this->main_instance->get_single_config('cf_disable_cache_purging_queue', 0) == 0) {
            $this->purge_cache_queue_write(array(), true);
        } else {
            // Avoid to send multiple purge requests for the same session
            if ($this->purge_all_already_done)
                return true;

            if ($force_purge_everything == false && $this->main_instance->get_single_config('cf_purge_only_html', 0) > 0) {
                $timestamp         = time();
            } else {
                if (!$this->objects['cloudflare']->purge_cache($error)) {
                    return false;
                }
            }

            do_action('wpocf_purge_all');

            // Reset timestamp for Auto prefetch URLs in viewport option
            if ($this->main_instance->get_single_config('cf_prefetch_urls_viewport', 0) > 0)
                $this->generate_new_prefetch_urls_timestamp();

            $this->purge_all_already_done = true;
        }

        return true;
    }


    function purge_urls($urls, $queue_mode = true)
    {

        if (!is_array($urls))
            return false;

        $this->objects = $this->main_instance->get_objects();
        $error = '';

        // Strip out external links or invalid URLs
        foreach ($urls as $array_index => $single_url) {

            if ($this->is_external_link($single_url) || substr(strtolower($single_url), 0, 4) != 'http')
                unset($urls[$array_index]);
        }

        if ($queue_mode && $this->main_instance->get_single_config('cf_disable_cache_purging_queue', 0) == 0) {

            $this->purge_cache_queue_write($urls);
        } else {

            $count_urls = count($urls);

            if (!$this->objects['cloudflare']->purge_cache_urls($urls, $error)) {
                // $this->objects['logs']->add_log('cache_controller::purge_urls', "Unable to purge some URLs from Cloudflare due to error: {$error}");
                return false;
            }

            do_action('wpocf_purge_urls', $urls);

            // Reset timestamp for Auto prefetch URLs in viewport option
            if ($this->main_instance->get_single_config('cf_prefetch_urls_viewport', 0) > 0)
                $this->generate_new_prefetch_urls_timestamp();
        }

        return true;
    }

    function purge_cache_when_comment_is_approved($new_status, $old_status, $comment)
    {
        if ($this->main_instance->get_single_config('cf_auto_purge_on_comments', 0) > 0 && $this->is_cache_enabled()) {
            if ($old_status != $new_status && $new_status == 'approved') {
                $current_action = function_exists('current_action') ? current_action() : "";
                $this->objects = $this->main_instance->get_objects();
                $urls = array();
                $urls[] = get_permalink($comment->comment_post_ID);
                $this->purge_urls($urls);
            }
        }
    }

    function purge_cache_when_new_comment_is_added($comment_ID, $comment_approved, $commentdata)
    {
        if ($this->main_instance->get_single_config('cf_auto_purge_on_comments', 0) > 0 && $this->is_cache_enabled()) {
            if (isset($commentdata['comment_post_ID'])) {
                $current_action = function_exists('current_action') ? current_action() : "";
                $this->objects = $this->main_instance->get_objects();
                $error = '';
                $urls = array();
                $urls[] = get_permalink($commentdata['comment_post_ID']);
                $this->purge_urls($urls);
            }
        }
    }

    function purge_cache_when_comment_is_deleted($comment_ID)
    {
        if ($this->main_instance->get_single_config('cf_auto_purge_on_comments', 0) > 0 && $this->is_cache_enabled()) {
            $current_action = function_exists('current_action') ? current_action() : "";
            $this->objects = $this->main_instance->get_objects();
            $urls    = array();
            $comment = get_comment($comment_ID);
            $urls[]  = get_permalink($comment->comment_post_ID);
            $this->purge_urls($urls);
        }
    }


    function purge_cache_when_post_is_published($new_status, $old_status, $post)
    {

        if (($this->main_instance->get_single_config('cf_auto_purge', 0) > 0 || $this->main_instance->get_single_config('cf_auto_purge_all', 0) > 0) && $this->is_cache_enabled()) {

            if (in_array($old_status, ['future', 'draft', 'pending']) && in_array($new_status, ['publish', 'private'])) {

                $current_action = function_exists('current_action') ? current_action() : "";

                $this->objects = $this->main_instance->get_objects();

                if ($this->main_instance->get_single_config('cf_auto_purge_all', 0) > 0) {

                    $this->purge_all();
                } else {

                    $urls = $this->get_post_related_links($post->ID);

                    $this->purge_urls($urls);
                }
            }
        }
    }


    function purge_cache_on_post_edit($postId)
    {

        static $done = [];

        if (isset($done[$postId])) {
            return;
        }

        // Do not run this on the WordPress Nav Menu Pages
        global $pagenow;
        if ($pagenow === 'nav-menus.php') return;

        if (($this->main_instance->get_single_config('cf_auto_purge', 0) > 0 || $this->main_instance->get_single_config('cf_auto_purge_all', 0) > 0) && $this->is_cache_enabled()) {

            $current_action = function_exists('current_action') ? current_action() : "";

            $this->objects = $this->main_instance->get_objects();

            $error = '';

            $validPostStatus = ['publish', 'trash', 'private'];
            $thisPostStatus = get_post_status($postId);

            if (get_permalink($postId) != true || !in_array($thisPostStatus, $validPostStatus)) {
                return;
            }

            if (is_int(wp_is_post_autosave($postId)) || is_int(wp_is_post_revision($postId))) {
                return;
            }

            if ($this->main_instance->get_single_config('cf_auto_purge_all', 0) > 0) {
                $this->purge_all();
                return;
            }

            $savedPost = get_post($postId);

            if (is_a($savedPost, 'WP_Post') == false) {
                return;
            }

            $urls = $this->get_post_related_links($postId);

            $this->purge_urls($urls);
            $done[$postId] = true;
        }
    }


    function purge_cache_on_theme_edit()
    {

        if (($this->main_instance->get_single_config('cf_auto_purge', 0) > 0 || $this->main_instance->get_single_config('cf_auto_purge_all', 0) > 0) && $this->is_cache_enabled()) {

            $current_action = function_exists('current_action') ? current_action() : "";

            $this->objects = $this->main_instance->get_objects();

            $this->purge_all();
        }
    }


    function get_post_related_links($postId)
    {

        $this->objects = $this->main_instance->get_objects();

        $listofurls = apply_filters('wpocf_post_related_url_init', __return_empty_array(), $postId);
        $postType = get_post_type($postId);

        // Post URL
        array_push($listofurls, get_permalink($postId));

        //Purge taxonomies terms URLs
        $postTypeTaxonomies = get_object_taxonomies($postType);

        foreach ($postTypeTaxonomies as $taxonomy) {

            if (is_object($taxonomy) && ($taxonomy->public == false || $taxonomy->rewrite == false)) {
                continue;
            }

            $terms = get_the_terms($postId, $taxonomy);

            if (empty($terms) || is_wp_error($terms)) {
                continue;
            }

            foreach ($terms as $term) {

                $termLink = get_term_link($term);

                if (!is_wp_error($termLink)) {

                    array_push($listofurls, $termLink);

                    if ($this->main_instance->get_single_config('cf_post_per_page', 0) > 0) {

                        // Thanks to Davide Prevosto for the suggest
                        $term_count   = $term->count;
                        $pages_number = ceil($term_count / $this->main_instance->get_single_config('cf_post_per_page', 0));
                        $max_pages    = $pages_number > 10 ? 10 : $pages_number; // Purge max 10 pages

                        for ($i = 2; $i <= $max_pages; $i++) {
                            $paginated_url = "{$termLink}page/" . user_trailingslashit($i);
                            array_push($listofurls, $paginated_url);
                        }
                    }
                }
            }
        }

        // Author URL
        array_push(
            $listofurls,
            get_author_posts_url(get_post_field('post_author', $postId)),
            get_author_feed_link(get_post_field('post_author', $postId))
        );

        // Archives and their feeds
        if (get_post_type_archive_link($postType) == true) {
            array_push(
                $listofurls,
                get_post_type_archive_link($postType),
                get_post_type_archive_feed_link($postType)
            );
        }

        // Also clean URL for trashed post.
        if (get_post_status($postId) == 'trash') {
            $trashPost = get_permalink($postId);
            $trashPost = str_replace('__trashed', '', $trashPost);
            array_push($listofurls, $trashPost, "{$trashPost}feed/");
        }

        // Purge the home page as well if WPOCF_HOME_PAGE_SHOWS_POSTS set to true
        if (defined('WPOCF_HOME_PAGE_SHOWS_POSTS') && WPOCF_HOME_PAGE_SHOWS_POSTS === true) {
            array_push($listofurls, home_url('/'));
        }

        $pageLink = get_permalink(get_option('page_for_posts'));
        if (is_string($pageLink) && !empty($pageLink) && get_option('show_on_front') == 'page') {
            array_push($listofurls, $pageLink);
        }

        return $listofurls;
    }


    function reset_all($keep_settings = false)
    {

        $this->objects = $this->main_instance->get_objects();
        $error = '';

        // Purge all caches and prevent preloader to start
        $this->purge_all(true, false, true);

        // Reset old browser cache TTL
        $this->objects['cloudflare']->change_browser_cache_ttl($this->main_instance->get_single_config('cf_old_bc_ttl', 0), $error);

        // Delete worker and route
        // if( $this->main_instance->get_single_config('cf_worker_enabled', 0) > 0 ) {

        //     $this->objects['cloudflare']->worker_delete($error);

        //     if( $this->main_instance->get_single_config('cf_worker_route_id', '') != '' ) {
        //         $this->objects['cloudflare']->worker_route_delete($error);
        //     }

        // }

        // Delete the page rule
        if ($this->main_instance->get_single_config('cf_page_rule_id', '') != '') {
            $this->objects['cloudflare']->delete_page_rule($this->main_instance->get_single_config('cf_page_rule_id', ''), $error);
        }

        // Restore default plugin config
        if ($keep_settings == false) {
            $this->main_instance->set_config($this->main_instance->get_default_config());
            $this->main_instance->update_config();
            update_option('wpoven-triple-cache', array());
        } else {
            $this->main_instance->set_single_config('cf_cache_enabled', 0);
            $this->main_instance->update_config();
            update_option('wpoven-triple-cache', array());
        }

        // Delete all htaccess rules
        $this->reset_htaccess();

        // Unschedule purge cache cron
        $timestamp = wp_next_scheduled('wpocf_cache_purge_cron');

        if ($timestamp !== false) {
            wp_unschedule_event($timestamp, 'wpocf_cache_purge_cron');
            wp_clear_scheduled_hook('wpocf_cache_purge_cron');
        }
    }


    function wp_redirect_filter($location, $status)
    {

        if (apply_filters('wpocf_bypass_redirect_cache_buster', false, $location) === true)
            return $location;

        if (!$this->is_cache_enabled())
            return $location;

        if (!is_user_logged_in())
            return $location;

        $this->objects = $this->main_instance->get_objects();

        // if( $this->main_instance->get_single_config('cf_worker_enabled', 0) > 0 )
        //     return $location;

        if (version_compare(phpversion(), '8', '>='))
            $cache_buster_exists = str_contains($location, $this->cache_buster);
        else
            $cache_buster_exists = strpos($location, $this->cache_buster);

        if ($cache_buster_exists == false)
            $location = add_query_arg(array($this->cache_buster => '1'), $location);

        return $location;
    }


    function inject_cache_buster_js_code()
    {

        if (!$this->is_cache_enabled())
            return;

        if ($this->remove_cache_buster())
            return;

        if (!is_user_logged_in())
            return;

        // Make sure we don't add the following script for AMP endpoints as they are gonna be striped out by the AMP system anyway
        if (!is_admin() && (function_exists('amp_is_request') && amp_is_request()) || (function_exists('ampforwp_is_amp_endpoint') && ampforwp_is_amp_endpoint()))
            return;

        $this->objects = $this->main_instance->get_objects();

        // // Cache buster is disabled in worker mode
        // if( $this->main_instance->get_single_config('cf_worker_enabled', 0) > 0 )
        //     return;

        $selectors = 'a';

        if (is_admin())
            $selectors = '#wp-admin-bar-my-sites-list a, #wp-admin-bar-site-name a, #wp-admin-bar-view-site a, #wp-admin-bar-view a, .row-actions a, .preview, #sample-permalink a, #message a, #editor .is-link, #editor .editor-post-preview, #editor .editor-post-permalink__link, .edit-post-post-link__preview-link-container .edit-post-post-link__link';

    ?>

        <script id="wpocf">
            var wpocf_adjust_internal_links = function(selectors_txt) {

                const comp = new RegExp(location.host);
                const current_url = window.location.href.split("#")[0];

                [].forEach.call(document.querySelectorAll(selectors_txt), function(el) {

                    if (comp.test(el.href) && !el.href.includes("<?php echo $this->cache_buster; ?>=1") && !el.href.startsWith("#") && !el.href.startsWith(current_url + "#")) {

                        if (el.href.indexOf('#') != -1) {

                            const link_split = el.href.split("#");
                            el.href = link_split[0];
                            el.href += (el.href.indexOf('?') != -1 ? "&<?php echo $this->cache_buster; ?>=1" : "?<?php echo $this->cache_buster; ?>=1");
                            el.href += "#" + link_split[1];

                        } else {
                            el.href += (el.href.indexOf('?') != -1 ? "&<?php echo $this->cache_buster; ?>=1" : "?<?php echo $this->cache_buster; ?>=1");
                        }

                    }

                });

            }

            document.addEventListener("DOMContentLoaded", function() {

                wpocf_adjust_internal_links("<?php echo $selectors; ?>");

            });

            window.addEventListener("load", function() {

                wpocf_adjust_internal_links("<?php echo $selectors; ?>");

            });

            setInterval(function() {
                wpocf_adjust_internal_links("<?php echo $selectors; ?>");
            }, 3000);


            // Looking for dynamic link added after clicking on Pusblish/Update button
            var wpocf_wordpress_btn_publish = document.querySelector(".editor-post-publish-button__button");

            if (wpocf_wordpress_btn_publish !== undefined && wpocf_wordpress_btn_publish !== null) {

                wpocf_wordpress_btn_publish.addEventListener('click', function() {

                    var wpocf_wordpress_edited_post_interval = setInterval(function() {

                        var wpocf_wordpress_edited_post_link = document.querySelector(".components-snackbar__action");

                        if (wpocf_wordpress_edited_post_link !== undefined) {
                            wpocf_adjust_internal_links(".components-snackbar__action");
                            clearInterval(wpocf_wordpress_edited_post_link);
                        }

                    }, 100);

                }, false);

            }
        </script>

        <?php

    }


    function generate_new_prefetch_urls_timestamp()
    {

        $current_timestamp = $this->main_instance->get_single_config('cf_prefetch_urls_viewport_timestamp', time());

        if ($current_timestamp < time()) {

            $current_timestamp = time() + 120; // Cache the timestamp for 2 minutes
            $this->main_instance->set_single_config('cf_prefetch_urls_viewport_timestamp', $current_timestamp);
            $this->main_instance->update_config();

            $this->objects = $this->main_instance->get_objects();
        }

        return $current_timestamp;
    }


    function prefetch_urls()
    {

        if (!$this->is_cache_enabled() || is_user_logged_in())
            return;

        if ($this->main_instance->get_single_config('cf_prefetch_urls_viewport', 0) > 0) : ?>

            <script id="wpocf">
                const wpocf_prefetch_urls_timestamp_server = '<?php echo $this->main_instance->get_single_config('cf_prefetch_urls_viewport_timestamp', time()); ?>';

                let wpocf_prefetched_urls = localStorage.getItem("wpocf_prefetched_urls");
                wpocf_prefetched_urls = (wpocf_prefetched_urls) ? JSON.parse(wpocf_prefetched_urls) : [];

                let wpocf_prefetch_urls_timestamp_client = localStorage.getItem("wpocf_prefetch_urls_timestamp_client");

                if (wpocf_prefetch_urls_timestamp_client == undefined || wpocf_prefetch_urls_timestamp_client != wpocf_prefetch_urls_timestamp_server) {
                    wpocf_prefetch_urls_timestamp_client = wpocf_prefetch_urls_timestamp_server;
                    wpocf_prefetched_urls = new Array();
                    localStorage.setItem("wpocf_prefetched_urls", JSON.stringify(wpocf_prefetched_urls));
                    localStorage.setItem("wpocf_prefetch_urls_timestamp_client", wpocf_prefetch_urls_timestamp_client);
                }

                function wpocf_element_is_in_viewport(element) {

                    let bounding = element.getBoundingClientRect();

                    if (bounding.top >= 0 && bounding.left >= 0 && bounding.right <= (window.innerWidth || document.documentElement.clientWidth) && bounding.bottom <= (window.innerHeight || document.documentElement.clientHeight))
                        return true;

                    return false;

                }

                function wpocf_prefetch_urls() {

                    let comp = new RegExp(location.host);

                    document.querySelectorAll("a").forEach((item) => {

                        if (item.href) {

                            let href = item.href.split("#")[0];

                            if (wpocf_can_url_be_prefetched(href) && wpocf_prefetched_urls.includes(href) == false && comp.test(item.href) && wpocf_element_is_in_viewport(item)) {
                                wpocf_prefetched_urls.push(href);
                                //console.log( href );
                                let prefetch_element = document.createElement('link');
                                prefetch_element.rel = "prefetch";
                                prefetch_element.href = href;
                                document.getElementsByTagName('body')[0].appendChild(prefetch_element);
                            }
                        }
                    })

                    localStorage.setItem("wpocf_prefetched_urls", JSON.stringify(wpocf_prefetched_urls));

                }

                window.addEventListener("load", function(event) {
                    wpocf_prefetch_urls();
                });

                window.addEventListener("scroll", function(event) {
                    wpocf_prefetch_urls();
                });
            </script>

        <?php endif; ?>

<?php

    }


    function is_url_to_bypass()
    {

        $this->objects = $this->main_instance->get_objects();

        // Bypass API requests
        if ($this->main_instance->is_api_request())
            return true;

        // Bypass AMP
        if ($this->main_instance->get_single_config('cf_bypass_amp', 0) > 0 && preg_match('/(\/)((\?amp)|(amp\/))/', $_SERVER['REQUEST_URI'])) {
            return true;
        }

        // Bypass sitemap
        if ($this->main_instance->get_single_config('cf_bypass_sitemap', 0) > 0 && strcasecmp($_SERVER['REQUEST_URI'], '/sitemap_index.xml') == 0 || preg_match('/[a-zA-Z0-9]-sitemap.xml$/', $_SERVER['REQUEST_URI'])) {
            return true;
        }

        // Bypass robots.txt
        if ($this->main_instance->get_single_config('cf_bypass_file_robots', 0) > 0 && preg_match('/^\/robots.txt/', $_SERVER['REQUEST_URI'])) {
            return true;
        }

        // Bypass the cache on excluded URLs
        $excluded_urls = $this->main_instance->get_single_config('cf_excluded_urls', array());

        if (is_array($excluded_urls) && count($excluded_urls) > 0) {

            $current_url = $_SERVER['REQUEST_URI'];

            if (isset($_SERVER['QUERY_STRING']) && strlen($_SERVER['QUERY_STRING']) > 0)
                $current_url .= "?{$_SERVER['QUERY_STRING']}";

            foreach ($excluded_urls as $url_to_exclude) {

                if ($this->main_instance->wildcard_match($url_to_exclude, $current_url))
                    return true;
            }
        }

        if (isset($_GET[$this->cache_buster]) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') || (defined('DOING_AJAX') && DOING_AJAX) || (defined('DOING_CRON') && DOING_CRON)) {
            return true;
        }

        if (in_array($GLOBALS['pagenow'], array('wp-login.php', 'wp-register.php')))
            return true;
        return false;
    }

    function can_i_bypass_cache()
    {

        global $post;

        $this->objects = $this->main_instance->get_objects();

        // Bypass the cache using filter
        if (has_filter('wpocf_cache_bypass')) {
            $cache_bypass = apply_filters('wpocf_cache_bypass', false);
            if ($cache_bypass === true)
                return true;
        }

        // Bypass post protected by password
        if (is_object($post) && post_password_required($post->ID) !== false) {
            return true;
        }

        // Bypass single post by metabox
        if ($this->main_instance->get_single_config('cf_disable_single_metabox', 0) == 0 && is_object($post) && (int) get_post_meta($post->ID, 'wpocf_bypass_cache', true) > 0) {
            return true;
        }

        // Bypass requests with query var
        if ($this->main_instance->get_single_config('cf_bypass_query_var', 0) > 0 && isset($_SERVER['QUERY_STRING']) && strlen($_SERVER['QUERY_STRING']) > 0) {
            return true;
        }

        // Bypass POST requests
        if ($this->main_instance->get_single_config('cf_bypass_post', 0) > 0 && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST') {
            return true;
        }

        // Bypass AJAX requests
        if ($this->main_instance->get_single_config('cf_bypass_ajax', 0) > 0) {
            if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
                return true;
            }

            if (function_exists('is_ajax') && is_ajax()) {
                return true;
            }

            if ((isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') || (defined('DOING_AJAX') && DOING_AJAX)) {
                return true;
            }
        }

        // Bypass EDD pages
        if (is_object($post) && $this->main_instance->get_single_config('cf_bypass_edd_checkout_page', 0) > 0 && function_exists('edd_get_option') && edd_get_option('purchase_page', 0) == $post->ID) {
            return true;
        }

        if (is_object($post) && $this->main_instance->get_single_config('cf_bypass_edd_success_page', 0) > 0 && function_exists('edd_get_option') && edd_get_option('success_page', 0) == $post->ID) {
            return true;
        }

        if (is_object($post) && $this->main_instance->get_single_config('cf_bypass_edd_failure_page', 0) > 0 && function_exists('edd_get_option') && edd_get_option('failure_page', 0) == $post->ID) {
            return true;
        }

        if (is_object($post) && $this->main_instance->get_single_config('cf_bypass_edd_purchase_history_page', 0) > 0 && function_exists('edd_get_option') && edd_get_option('purchase_history_page', 0) == $post->ID) {
            return true;
        }

        if (is_object($post) && $this->main_instance->get_single_config('cf_bypass_edd_login_redirect_page', 0) > 0 && function_exists('edd_get_option') && edd_get_option('login_redirect_page', 0) == $post->ID) {
            return true;
        }

        // Bypass WooCommerce pages
        if ($this->main_instance->get_single_config('cf_bypass_woo_cart_page', 0) > 0 && function_exists('is_cart') && is_cart()) {
            return true;
        }

        if ($this->main_instance->get_single_config('cf_bypass_woo_account_page', 0) > 0 && function_exists('is_account') && is_account()) {
            return true;
        }

        if ($this->main_instance->get_single_config('cf_bypass_woo_checkout_page', 0) > 0 && function_exists('is_checkout') && is_checkout()) {
            return true;
        }

        if ($this->main_instance->get_single_config('cf_bypass_woo_checkout_pay_page', 0) > 0 && function_exists('is_checkout_pay_page') && is_checkout_pay_page()) {
            return true;
        }

        if ($this->main_instance->get_single_config('cf_bypass_woo_shop_page', 0) > 0 && function_exists('is_shop') && is_shop()) {
            return true;
        }

        if ($this->main_instance->get_single_config('cf_bypass_woo_product_page', 0) > 0 && function_exists('is_product') && is_product()) {
            return true;
        }

        if ($this->main_instance->get_single_config('cf_bypass_woo_product_cat_page', 0) > 0 && function_exists('is_product_category') && is_product_category()) {
            return true;
        }

        if ($this->main_instance->get_single_config('cf_bypass_woo_product_tag_page', 0) > 0 && function_exists('is_product_tag') && is_product_tag()) {
            return true;
        }

        if ($this->main_instance->get_single_config('cf_bypass_woo_product_tax_page', 0) > 0 && function_exists('is_product_taxonomy') && is_product_taxonomy()) {
            return true;
        }

        if ($this->main_instance->get_single_config('cf_bypass_woo_pages', 0) > 0 && function_exists('is_woocommerce') && is_woocommerce()) {
            return true;
        }

        // Bypass Wordpress pages
        if ($this->main_instance->get_single_config('cf_bypass_front_page', 0) > 0 && is_front_page()) {
            return true;
        }

        if ($this->main_instance->get_single_config('cf_bypass_pages', 0) > 0 && is_page()) {
            return true;
        }

        if ($this->main_instance->get_single_config('cf_bypass_home', 0) > 0 && is_home()) {
            return true;
        }

        if ($this->main_instance->get_single_config('cf_bypass_archives', 0) > 0 && is_archive()) {
            return true;
        }

        if ($this->main_instance->get_single_config('cf_bypass_tags', 0) > 0 && is_tag()) {
            return true;
        }

        if ($this->main_instance->get_single_config('cf_bypass_category', 0) > 0 && is_category()) {
            return true;
        }

        if ($this->main_instance->get_single_config('cf_bypass_feeds', 0) > 0 && is_feed()) {
            return true;
        }

        if ($this->main_instance->get_single_config('cf_bypass_search_pages', 0) > 0 && is_search()) {
            return true;
        }

        if ($this->main_instance->get_single_config('cf_bypass_author_pages', 0) > 0 && is_author()) {
            return true;
        }

        if ($this->main_instance->get_single_config('cf_bypass_single_post', 0) > 0 && is_single()) {
            return true;
        }

        if ($this->main_instance->get_single_config('cf_bypass_404', 0) > 0 && is_404()) {
            return true;
        }

        /*
        if( $this->main_instance->get_single_config('cf_bypass_logged_in', 0) > 0 && is_user_logged_in() ) {
            return true;
        }
        */

        if (is_user_logged_in()) {
            return true;
        }


        // Bypass cache if the parameter wpocf is setted or we are on backend
        if (isset($_GET[$this->cache_buster]) || is_admin()) {
            return true;
        }

        return false;
    }

    function get_cache_control_value()
    {
        $this->objects = $this->main_instance->get_objects();
        $value = 's-maxage=' . $this->main_instance->get_single_config('cf_maxage', 604800) . ', max-age=' . $this->main_instance->get_single_config('cf_browser_maxage', 60);
        return $value;
    }

    function is_cache_enabled()
    {
        $this->objects = $this->main_instance->get_objects();
        if ($this->main_instance->get_single_config('cf_cache_enabled', 0) > 0)
            return true;
        return false;
    }

    function remove_cache_buster()
    {
        $this->objects = $this->main_instance->get_objects();
        if ($this->main_instance->get_single_config('cf_remove_cache_buster', 0) > 0) {
            return true;
        } else {
            return false;
        }
    }


    function spl_purge_all()
    {
        $current_action = function_exists('current_action') ? current_action() : "";
        $this->objects = $this->main_instance->get_objects();
        if ($current_action == 'swift_performance_after_clear_all_cache')
            $this->purge_all(false, true, true);
        else
            $this->purge_all();
        $this->purge_all();
    }


    function spl_purge_single_post($post_id)
    {

        static $done = [];

        if (isset($done[$post_id])) {
            return;
        }

        if ($this->main_instance->get_single_config('cf_spl_purge_on_flush_single_post', 0) > 0) {
            $current_action = function_exists('current_action') ? current_action() : "";
            $this->objects = $this->main_instance->get_objects();

            $urls = array();
            $urls[] = get_permalink($post_id);

            $this->purge_urls($urls);
            $done[$post_id] = true;
        }
    }

    function purge_on_plugin_update()
    {
        $current_action = function_exists('current_action') ? current_action() : "";
        $this->objects = $this->main_instance->get_objects();
        $this->purge_all(false, true, true);
    }


    function edd_purge_cache_on_payment_add()
    {
        $current_action = function_exists('current_action') ? current_action() : "";
        $this->objects = $this->main_instance->get_objects();
        $this->purge_all();
    }

    function reset_htaccess()
    {

        if (function_exists('insert_with_markers'))
            insert_with_markers($this->htaccess_path, 'WPOven Triple Cache', array());
    }

    function write_htaccess(&$error_msg)
    {

        $this->objects = $this->main_instance->get_objects();
        $htaccess_lines = array();

        if ($this->main_instance->get_single_config('cf_cache_control_htaccess', 0) > 0 && $this->is_cache_enabled() && $this->main_instance->get_single_config('cf_worker_enabled', 0) == 0) {

            $htaccess_lines[] = '<IfModule mod_headers.c>';
            $htaccess_lines[] = 'Header unset Pragma "expr=resp(\'WPOven-cache-active\') == \'1\'"';
            $htaccess_lines[] = 'Header always unset Pragma "expr=resp(\'WPOven-cache-active\') == \'1\'"';
            $htaccess_lines[] = 'Header unset Expires "expr=resp(\'WPOven-cache-active\') == \'1\'"';
            $htaccess_lines[] = 'Header always unset Expires "expr=resp(\'WPOven-cache-active\') == \'1\'"';
            $htaccess_lines[] = 'Header unset Cache-Control "expr=resp(\'WPOven-cache-active\') == \'1\'"';
            $htaccess_lines[] = 'Header always unset Cache-Control "expr=resp(\'WPOven-cache-active\') == \'1\'"';
            $htaccess_lines[] = 'Header always set Cache-Control "' . $this->get_cache_control_value() . '" "expr=resp(\'WPOven-cache-active\') == \'1\'"';

            $htaccess_lines[] = 'Header unset Pragma "expr=resp(\'WPOven-cache-cache-control\') != \'\'"';
            $htaccess_lines[] = 'Header always unset Pragma "expr=resp(\'WPOven-cache-cache-control\') != \'\'"';
            $htaccess_lines[] = 'Header unset Expires "expr=resp(\'WPOven-cache-cache-control\') != \'\'"';
            $htaccess_lines[] = 'Header always unset Expires "expr=resp(\'WPOven-cache-cache-control\') != \'\'"';
            $htaccess_lines[] = 'Header unset Cache-Coget_plugin_wp_content_directory_uriache-Control "expr=resp(\'WPOven-cache-cache-control\') != \'\'"';

            // Add a cache-control header with the value of WPOven-cache-cache-control response header
            $htaccess_lines[] = 'Header always set Cache-Control "expr=%{resp:WPOven-cache-cache-control}" "expr=resp(\'WPOven-cache-cache-control\') != \'\'"';

            $htaccess_lines[] = '</IfModule>';
        }

        if ($this->main_instance->get_single_config('cf_strip_cookies', 0) > 0 && $this->is_cache_enabled()) {

            $htaccess_lines[] = '<IfModule mod_headers.c>';
            $htaccess_lines[] = 'Header unset Set-Cookie "expr=resp(\'WPOven-cache-active\') == \'1\'"';
            $htaccess_lines[] = 'Header always unset Set-Cookie "expr=resp(\'WPOven-cache-active\') == \'1\'"';
            $htaccess_lines[] = '</IfModule>';
        }

        if ($this->main_instance->get_single_config('cf_bypass_sitemap', 0) > 0 && $this->is_cache_enabled()) {

            $htaccess_lines[] = '<IfModule mod_expires.c>';
            $htaccess_lines[] = 'ExpiresActive on';
            $htaccess_lines[] = 'ExpiresByType application/xml "access plus 0 seconds"';
            $htaccess_lines[] = 'ExpiresByType text/xsl "access plus 0 seconds"';
            $htaccess_lines[] = '</IfModule>';

            $htaccess_lines[] = '<FilesMatch "\.(xml|xsl)$">';
            $htaccess_lines[] = '<IfModule mod_headers.c>';
            $htaccess_lines[] = 'Header set Cache-Control "no-cache, no-store, must-revalidate, max-age=0"';
            $htaccess_lines[] = '</IfModule>';
            $htaccess_lines[] = '</FilesMatch>';
        }

        if ($this->main_instance->get_single_config('cf_bypass_file_robots', 0) > 0 && $this->is_cache_enabled()) {

            $htaccess_lines[] = '<FilesMatch "robots\.txt">';
            $htaccess_lines[] = '<IfModule mod_headers.c>';
            $htaccess_lines[] = 'Header set Cache-Control "no-cache, no-store, must-revalidate, max-age=0"';
            $htaccess_lines[] = '</IfModule>';
            $htaccess_lines[] = '</FilesMatch>';
        }

        if ($this->main_instance->get_single_config('cf_browser_caching_htaccess', 0) > 0 && $this->is_cache_enabled()) {

            // Cache CSS/JS/PDF for 1 month
            $htaccess_lines[] = '<FilesMatch "\.(css|js|pdf)$">';
            $htaccess_lines[] = '<IfModule mod_headers.c>';
            $htaccess_lines[] = 'Header set Cache-Control "public, must-revalidate, proxy-revalidate, immutable, max-age=2592000, stale-while-revalidate=86400, stale-if-error=604800"';
            $htaccess_lines[] = '</IfModule>';
            $htaccess_lines[] = '</FilesMatch>';

            // Cache other static files for 1 year
            $htaccess_lines[] = '<FilesMatch "\.(jpg|jpeg|png|gif|ico|eot|swf|svg|webp|avif|ttf|otf|woff|woff2|ogg|mp4|mpeg|avi|mkv|webm|mp3)$">';
            $htaccess_lines[] = '<IfModule mod_headers.c>';
            $htaccess_lines[] = 'Header set Cache-Control "public, must-revalidate, proxy-revalidate, immutable, max-age=31536000, stale-while-revalidate=86400, stale-if-error=604800"';
            $htaccess_lines[] = '</IfModule>';
            $htaccess_lines[] = '</FilesMatch>';
        }

        // Disable direct access to log file
        //$log_file_uri = $this->main_instance->get_plugin_wp_content_directory_uri() . '/debug.log';

        // $htaccess_lines[] = '<IfModule mod_rewrite.c>';
        // $htaccess_lines[] = "RewriteCond %{REQUEST_URI} ^(.*)?{$log_file_uri}(.*)$";
        // $htaccess_lines[] = 'RewriteRule ^(.*)$ - [F]';
        // $htaccess_lines[] = '</IfModule>';

        // // Force cache bypass for wp-cron.php
        // $htaccess_lines[] = '<FilesMatch "wp-cron.php">';
        // $htaccess_lines[] = '<IfModule mod_headers.c>';
        // $htaccess_lines[] = 'Header set Cache-Control "no-cache, no-store, must-revalidate, max-age=0"';
        // $htaccess_lines[] = '</IfModule>';
        // $htaccess_lines[] = '</FilesMatch>';

        // $nginx_rules = $this->get_nginx_rules();

        // if( is_array($nginx_rules) ) {
        //     file_put_contents($this->main_instance->get_plugin_wp_content_directory() . '/nginx.conf', implode("\n", $nginx_rules));
        // }
        // else {
        //     file_put_contents($this->main_instance->get_plugin_wp_content_directory() . '/nginx.conf', '');
        // }

        // if( function_exists('insert_with_markers') && !insert_with_markers( $this->htaccess_path, 'WPOven Triple Cache', $htaccess_lines ) ) {
        //     $error_msg = sprintf( __( 'The .htaccess file (%s) could not be edited. Check if the file has write permissions.', 'WPOven Triple Cache' ), $this->htaccess_path );
        //     return false;
        // }

        return true;
    }

    function ajax_purge_everything()
    {

        check_ajax_referer('ajax-nonce-string', 'security');

        $return_array = array('status' => 'ok');

        $this->objects = $this->main_instance->get_objects();

        if (!$this->main_instance->can_current_user_purge_cache()) {
            $return_array['status'] = 'error';
            $return_array['error'] = __('Permission denied', 'WPOven Triple Cache');
            die(wp_json_encode($return_array));
        }

        $this->purge_all(false, false, true);

        $return_array['success_msg'] = __('Cache purged successfully! It may take up to 30 seconds for the cache to be permanently cleaned by Cloudflare.', 'WPOven Triple Cache');

        die(wp_json_encode($return_array));
    }

    function ajax_purge_whole_cache()
    {

        check_ajax_referer('ajax-nonce-string', 'security');

        $return_array = array('status' => 'ok');

        $this->objects = $this->main_instance->get_objects();

        if (!$this->main_instance->can_current_user_purge_cache()) {
            $return_array['status'] = 'error';
            $return_array['error'] = __('Permission denied', 'WPOven Triple Cache');
            die(wp_json_encode($return_array));
        }

        $this->purge_all(false, false);

        $return_array['success_msg'] = __('Cache purged successfully! It may take up to 30 seconds for the cache to be permanently cleaned by Cloudflare.', 'WPOven Triple Cache');

        die(wp_json_encode($return_array));
    }

    function ajax_purge_single_post_cache()
    {

        check_ajax_referer('ajax-nonce-string', 'security');

        $return_array = array('status' => 'ok');

        $data = stripslashes($_POST['data']);
        $data = json_decode($data, true);

        $this->objects = $this->main_instance->get_objects();

        if (!$this->main_instance->can_current_user_purge_cache()) {
            $return_array['status'] = 'error';
            $return_array['error'] = __('Permission denied', 'WPOven Triple Cache');
            die(wp_json_encode($return_array));
        }

        $post_id = (int) $data['post_id'];

        $urls = $this->get_post_related_links($post_id);

        if (!$this->purge_urls($urls, false)) {
            $return_array['status'] = 'error';
            $return_array['error'] = __('An error occurred while cleaning the cache. Please check log file for further details.', 'WPOven Triple Cache');
            die(wp_json_encode($return_array));
        }
        $return_array['success_msg'] = __('Cache purged successfully! It may take up to 30 seconds for the cache to be permanently cleaned by Cloudflare.', 'WPOven Triple Cache');

        die(wp_json_encode($return_array));
    }

    function ajax_reset_all()
    {

        check_ajax_referer('ajax-nonce-string', 'security');

        $return_array = array('status' => 'ok');

        if (!current_user_can('manage_options')) {
            $return_array['status'] = 'error';
            $return_array['error'] = __('Permission denied', 'WPOven Triple Cache');
            die(wp_json_encode($return_array));
        }

        $this->reset_all();

        $return_array['success_msg'] = __('Cloudflare and all configurations have been reset to the initial settings.', 'WPOven Triple Cache');

        die(wp_json_encode($return_array));
    }

    function is_purge_cache_queue_writable()
    {
        $purge_cache_lock = get_option('wpocf_purge_cache_lock', 0);
        if ($purge_cache_lock == 0 || (time() - $purge_cache_lock) > 60)
            return true;
        return false;
    }

    function lock_cache_purge_queue()
    {
        update_option('wpocf_purge_cache_lock', time());
    }

    function unlock_cache_purge_queue()
    {
        update_option('wpocf_purge_cache_lock', 0);
    }

    function purge_cache_queue_init_directory()
    {
        // Initialize the WP Filesystem API
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $cache_path = $this->main_instance->get_plugin_wp_content_directory() . '/purge_cache_queue/';

        // Create directory if it doesn’t exist
        if (!file_exists($cache_path) && wp_mkdir_p($cache_path)) {
            // Use WP_Filesystem to create the index.php file
            $wp_filesystem->put_contents($cache_path . 'index.php', '<?php // Silence is golden');
        }

        return $cache_path;
    }


    function purge_cache_queue_write($urls = array(), $purge_all = false)
    {

        $this->objects = $this->main_instance->get_objects();

        while (!$this->is_purge_cache_queue_writable()) {
            sleep(1);
        }

        $this->lock_cache_purge_queue();

        $cache_queue_path = $this->purge_cache_queue_init_directory() . 'cache_queue.json';
        $wpocf_cache_queue = [];

        if (file_exists($cache_queue_path)) {

            $wpocf_cache_queue = json_decode(file_get_contents($cache_queue_path), true);

            if (!is_array($wpocf_cache_queue) || (is_array($wpocf_cache_queue) && (!isset($wpocf_cache_queue['purge_all']) || !isset($wpocf_cache_queue['urls'])))) {
                $this->unlock_cache_purge_queue();
                return true;
            }

            if ($wpocf_cache_queue['purge_all']) {
                $this->unlock_cache_purge_queue();
                return true;
            }

            if ($wpocf_cache_queue['purge_all'] === false && $purge_all === true) {
                $wpocf_cache_queue['purge_all'] = true;
            } else {
                $wpocf_cache_queue['urls'] = array_unique(array_merge($wpocf_cache_queue['urls'], $urls));
            }
        } else {
            if (!is_array($urls))
                $urls = array();
            $wpocf_cache_queue = array('purge_all' => $purge_all, 'urls' => $urls);
        }
        file_put_contents($cache_queue_path, wp_json_encode($wpocf_cache_queue));

        $this->unlock_cache_purge_queue();
    }

    function purge_cache_queue_custom_interval($schedules)
    {

        $schedules['wpocf_purge_cache_cron_interval'] = array(
            'interval' => (defined('wpocf_PURGE_CACHE_CRON_INTERVAL') && WPOCF_PURGE_CACHE_CRON_INTERVAL > 0) ? WPOCF_PURGE_CACHE_CRON_INTERVAL : 10,
            'display'  => esc_html__('WPOven Triple Cache for Cloudflare - Purge Cache Cron Interval', 'WPOven Triple Cache')
        );

        return $schedules;
    }

    function purge_cache_queue_start_cronjob()
    {

        if ($this->main_instance->get_single_config('cf_disable_cache_purging_queue', 0) > 0)
            return false;

        $cache_queue_path = $this->purge_cache_queue_init_directory() . 'cache_queue.json';

        $this->objects = $this->main_instance->get_objects();

        // Purge queue file does not exist, so don't start purge events and unschedule running purge events
        if (!file_exists($cache_queue_path)) {

            $timestamp = wp_next_scheduled('wpocf_cache_purge_cron');

            if ($timestamp !== false) {
                if (wp_unschedule_event($timestamp, 'wpocf_cache_purge_cron')) {
                    wp_clear_scheduled_hook('wpocf_cache_purge_cron');
                }
            }

            return false;
        }

        // If the purge queue file exists and there are not aready running scheduled events, start a new one
        if (!wp_next_scheduled('wpocf_purge_cache_cron') && !wp_get_schedule('wpocf_cache_purge_cron')) {

            $timestamp = time();

            if (wp_schedule_event($timestamp, 'wpocf_purge_cache_cron_interval', 'wpocf_cache_purge_cron')) {
                return true;
            }
        }
        return false;
    }

    function purge_cache_queue_job()
    {

        $this->objects = $this->main_instance->get_objects();

        $cache_queue_path = $this->purge_cache_queue_init_directory() . 'cache_queue.json';

        if (!file_exists($cache_queue_path)) {
            return false;
        }

        while (!$this->is_purge_cache_queue_writable()) {
            sleep(1);
        }

        $this->lock_cache_purge_queue();

        $wpocf_cache_queue = json_decode(file_get_contents($cache_queue_path), true);

        if (isset($wpocf_cache_queue['purge_all']) && $wpocf_cache_queue['purge_all']) {
            $this->purge_all(false, false);
        } else if (isset($wpocf_cache_queue['urls']) && is_array($wpocf_cache_queue['urls']) && count($wpocf_cache_queue['urls']) > 0) {
            $this->purge_urls($wpocf_cache_queue['urls'], false);
        }

        @unlink($cache_queue_path);

        $this->unlock_cache_purge_queue();

        return true;
    }

    function is_external_link($url)
    {

        $source = wp_parse_url(home_url());
        $target = wp_parse_url($url);

        if (!$source || empty($source['host']) || !$target || empty($target['host']))
            return false;

        if (strcasecmp($target['host'], $source['host']) === 0)
            return false;

        return true;
    }

    function purge_object_cache()
    {

        if (!function_exists('wp_cache_flush'))
            return false;

        wp_cache_flush();

        $this->objects = $this->main_instance->get_objects();

        return true;
    }

    function purge_cache_programmatically($urls)
    {

        if (!is_array($urls) || count($urls) == 0)
            $this->purge_all(true, false);
        else
            $this->purge_urls($urls, false);
    }

    function ajax_enable_page_cache()
    {

        check_ajax_referer('ajax-nonce-string', 'security');

        $this->objects = $this->main_instance->get_objects();

        $return_array = array('status' => 'ok');
        $error = '';

        if (!current_user_can('manage_options')) {
            $return_array['status'] = 'error';
            $return_array['error'] = __('Permission denied', 'WPOven Triple Cache');
            die(wp_json_encode($return_array));
        }

        if (!$this->objects['cloudflare']->enable_page_cache($error)) {
            $return_array['status'] = 'error';
            $return_array['error'] = $error;
            die(wp_json_encode($return_array));
        }

        $return_array['success_msg'] = __('Page cache enabled successfully', 'WPOven Triple Cache');

        die(wp_json_encode($return_array));
    }

    function ajax_disable_page_cache()
    {

        check_ajax_referer('ajax-nonce-string', 'security');

        $this->objects = $this->main_instance->get_objects();

        $return_array = array('status' => 'ok');
        $error = '';

        if (!current_user_can('manage_options')) {
            $return_array['status'] = 'error';
            $return_array['error'] = __('Permission denied', 'WPOven Triple Cache');
            die(wp_json_encode($return_array));
        }

        if (!$this->objects['cloudflare']->disable_page_cache($error)) {
            $return_array['status'] = 'error';
            $return_array['error'] = $error;
            die(wp_json_encode($return_array));
        }

        $return_array['success_msg'] = __('Page cache disabled successfully', 'WPOven Triple Cache');

        die(wp_json_encode($return_array));
    }
}

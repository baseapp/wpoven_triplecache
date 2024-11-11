<?php

defined('ABSPATH') || die('Cheating&#8217; uh?');

class WPOCF_Backend
{

    private $main_instance = null;
    private $objects       = false;

    function __construct($main_instance)
    {
        $this->main_instance = $main_instance;

        $this->actions();
    }


    function actions()
    {

        add_action('admin_enqueue_scripts', array($this, 'load_custom_wp_admin_styles_and_script'));

        // Modify Script Attributes based of the script handle
        add_filter('script_loader_tag', array($this, 'modify_script_attributes'), 10, 2);

        if (is_admin() && is_user_logged_in() && current_user_can('manage_options')) {
            // Action rows
            add_filter('post_row_actions', array($this, 'add_post_row_actions'), PHP_INT_MAX, 2);
            add_filter('page_row_actions', array($this, 'add_post_row_actions'), PHP_INT_MAX, 2);
        }

        if ($this->main_instance->get_single_config('cf_remove_purge_option_toolbar', 0) == 0 && $this->main_instance->can_current_user_purge_cache()) {

            // Load assets on frontend too
            add_action('wp_enqueue_scripts', array($this, 'load_custom_wp_admin_styles_and_script'));

            // Admin toolbar options
            add_action('admin_bar_menu', array($this, 'add_toolbar_items'), PHP_INT_MAX);

            // Ajax nonce
            add_action('wp_footer', array($this, 'add_ajax_nonce_everywhere'));
        }

        // Ajax nonce
        add_action('admin_footer', array($this, 'add_ajax_nonce_everywhere'));
    }


    function load_custom_wp_admin_styles_and_script()
    {
        $wp_scripts = wp_scripts();
        wp_register_script('wpocf_admin_js', WPOCF_PLUGIN_URL . 'assets/js/backend.js', true);
        $inline_js = 'const wpocf_ajax_url = "' . admin_url('admin-ajax.php') . '"; ';
        $inline_js .= 'let wpocf_cache_enabled = ' . $this->main_instance->get_single_config('cf_cache_enabled', 0) . ';';
        wp_add_inline_script('wpocf_admin_js', $inline_js, 'before');
        wp_enqueue_script('wpocf_admin_js');
    }


    function autoprefetch_config_wp_enqueue_scripts()
    {

        /**
         * Register a blank script to be added in the <head>
         * As this is a blank script, WP won't actually addd it but we can add our inline script before it
         * without depening on jQuery. This is to ensure the prefetch scripts get loaded whether a site uses
         * jQuery or not.
         *
         * https://wordpress.stackexchange.com/questions/298762/wp-add-inline-script-without-dependency/311279#311279
         */
        wp_register_script('wpocf_auto_prefetch_url', '', [], '1.0.0', true);
        wp_enqueue_script('wpocf_auto_prefetch_url');

        // Making sure we are not adding the following inline script for AMP endpoints as they are not gonna work anyway and will be striped out by the AMP system
        if (!((function_exists('amp_is_request') && amp_is_request()) || (function_exists('ampforwp_is_amp_endpoint') && ampforwp_is_amp_endpoint()) || is_customize_preview())) :

            ob_start();
            $inline_js = ob_get_contents();
            ob_end_clean();

            wp_add_inline_script('wpocf_auto_prefetch_url', $inline_js, 'before');

        endif;
    }

    function modify_script_attributes($tag, $handle)
    {
        // List of scripts added by this plugin
        $plugin_scripts = [
            'wpocf_admin_js',
        ];

        // Check if handle is any of the above scripts made sure we load them as defer
        if (!empty($tag) && in_array($handle, $plugin_scripts)) {
            return str_replace(' id', ' defer id', $tag);
        }
        return $tag;
    }


    function add_ajax_nonce_everywhere()
    {
?>
        <div id="wpocf-ajax-nonce" style="display:none;"><?php echo wp_create_nonce('ajax-nonce-string'); ?></div>
<?php

    }

    function add_toolbar_items($admin_bar)
    {
        $screen = is_admin() ? get_current_screen() : false;

        // Make sure we don't add the following admin bar menu as it is not gonna work for AMP endpoints anyway
        if (
            (function_exists('amp_is_request') && (!is_admin() && amp_is_request())) ||
            (function_exists('ampforwp_is_amp_endpoint') && (!is_admin() && ampforwp_is_amp_endpoint())) ||
            (is_object($screen) && $screen->base === 'woofunnels_page_wfob') ||
            is_customize_preview()
        ) return;

        $this->objects = $this->main_instance->get_objects();

        if ($this->main_instance->get_single_config('cf_remove_purge_option_toolbar', 0) == 0) {

            $wpocf_toolbar_container_url_query_arg_admin = [
                'page' => 'wpocf-cache-index',
                $this->objects['cache_controller']->get_cache_buster() => 1
            ];

            if ($this->main_instance->get_single_config('cf_remove_cache_buster', 1) > 0) {
                $wpocf_toolbar_container_url_query_arg_admin = [
                    'page' => 'wpocf-cache-index'
                ];
            }

            $admin_bar->add_menu(array(
                'id' => 'wpocf-cache-toolbar-container',
                'title' => '<span class="ab-icon"></span><span class="ab-label">' . __('WPOven CF Cache', 'WPOven Triple Cache') . '</span>',
                'href' => current_user_can('manage_options') ? admin_url('admin.php?page=' . WPOVEN_TRIPLE_CACHE_SLUG) : '#',
            ));

            if ($this->main_instance->get_single_config('cf_cache_enabled', 0) > 0) {

                global $post;

                $admin_bar->add_menu(array(
                    'id' => 'wpocf-cache-toolbar-purge-all',
                    'parent' => 'wpocf-cache-toolbar-container',
                    'title' => __('Purge whole cache', 'WPOven Triple Cache'),
                    //'href' => add_query_arg( array( 'page' => 'wpocf-cache-index', $this->objects['cache_controller']->get_cache_buster() => 1, 'swcfpc-purge-cache' => 1), admin_url('options-general.php' ) ),
                    'href' => '#'
                ));

                if (is_object($post)) {

                    $admin_bar->add_menu(array(
                        'id' => 'wpocf-cache-toolbar-purge-single',
                        'parent' => 'wpocf-cache-toolbar-container',
                        'title' => __('Purge cache for this page only', 'WPOven Triple Cache'),
                        'href' => "#{$post->ID}"
                    ));
                }
            }
        }
    }

    function add_post_row_actions($actions, $post)
    {
        if (!in_array($post->post_type, ['shop_order', 'shop_subscription'])) {
            $actions['wpocf_single_purge'] = '<a class="wpocf_action_row_single_post_cache_purge" data-post_id="' . $post->ID . '" href="#">' . __('Purge CF Cache', 'WPOven Triple Cache') . '</a>';
        }
        return $actions;
    }
}

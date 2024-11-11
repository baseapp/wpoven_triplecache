<?php

defined('ABSPATH') || die('Cheating&#8217; uh?');

class WPOCF_Cloudflare
{

    private $main_instance = null;

    private $objects   = false;
    private $api_key   = '';
    private $email     = '';
    private $api_token = '';
    private $auth_mode = 0;
    private $zone_id   = '';
    private $api_token_domain = '';
    private $worker_mode = false;
    private $worker_content = '';
    private $worker_id = '';
    private $worker_route_id = '';
    private $account_id_list = array();

    function __construct($auth_mode, $api_key, $email, $api_token, $zone_id, $main_instance)
    {

        $this->auth_mode       = $auth_mode;
        $this->api_key         = $api_key;
        $this->email           = $email;
        $this->api_token       = $api_token;
        $this->zone_id         = $zone_id;
        $this->main_instance   = $main_instance;
        $this->actions();
    }


    function actions()
    {
        // Ajax clear whole cache
        add_action('wp_ajax_wpocf_test_page_cache', array($this, 'ajax_test_page_cache'));
    }


    function set_auth_mode($auth_mode)
    {
        $this->auth_mode = $auth_mode;
    }


    function set_api_key($api_key)
    {
        $this->api_key = $api_key;
    }


    function set_api_email($email)
    {
        $this->email = $email;
    }


    function set_api_token($api_token)
    {
        $this->api_token = $api_token;
    }


    function set_api_token_domain($api_token_domain)
    {
        $this->api_token_domain = $api_token_domain;
    }


    // function set_worker_id($worker_id)
    // {
    //     $this->worker_id = $worker_id;
    // }


    // function set_worker_route_id($worker_route_id)
    // {
    //     $this->worker_route_id = $worker_route_id;
    // }


    // function enable_worker_mode($worker_content)
    // {
    //     $this->worker_mode = true;
    //     $this->worker_content = $worker_content;
    // }


    function get_api_headers($standard_curl = false)
    {

        $cf_headers = array();
        if ($this->auth_mode == WPOCF_AUTH_MODE_API_TOKEN) {

            if ($standard_curl) {

                $cf_headers = array(
                    'headers' => array(
                        "Authorization: Bearer {$this->api_token}",
                        'Content-Type: application/json'
                    )
                );
            } else {

                $cf_headers = array(
                    'headers' => array(
                        'Authorization' => "Bearer {$this->api_token}",
                        'Content-Type' => 'application/json'
                    )
                );
            }
        } else {

            if ($standard_curl) {

                $cf_headers = array(
                    'headers' => array(
                        "X-Auth-Email: {$this->email}",
                        "X-Auth-Key: {$this->api_key}",
                        'Content-Type: application/json'
                    )
                );
            } else {

                $cf_headers = array(
                    'headers' => array(
                        'X-Auth-Email' => $this->email,
                        'X-Auth-Key' => $this->api_key,
                        'Content-Type' => 'application/json'
                    )
                );
            }
        }

        $cf_headers['timeout'] = defined('WPOCF_CURL_TIMEOUT') ? WPOCF_CURL_TIMEOUT : 10;

        return $cf_headers;
    }


    function get_zone_id_list(&$error)
    {
        $this->objects = $this->main_instance->get_objects();

        $zone_id_list = array();
        $per_page     = 50;
        $current_page = 1;
        $pagination   = false;
        $cf_headers   = $this->get_api_headers();
        do {

            if ($this->auth_mode == WPOCF_AUTH_MODE_API_TOKEN && $this->api_token_domain != '') {
                $response = wp_remote_get(
                    esc_url_raw("https://api.cloudflare.com/client/v4/zones?name={$this->api_token_domain}"),
                    $cf_headers
                );
            } else {
                $response = wp_remote_get(
                    esc_url_raw("https://api.cloudflare.com/client/v4/zones?page={$current_page}&per_page={$per_page}"),
                    $cf_headers
                );
            }

            if (is_wp_error($response)) {
                return false;
            }

            $response_body = wp_remote_retrieve_body($response);
            $json = json_decode($response_body, true);
           
            if ($json['success'] == false) {

                $error = array();

                foreach ($json['errors'] as $single_error) {
                    $error[] = "{$single_error['message']} (err code: {$single_error['code']})";
                }

                $error = implode(' - ', $error);

                return false;
            }

            if (isset($json['result_info']) && is_array($json['result_info'])) {

                if (isset($json['result_info']['total_pages']) && (int) $json['result_info']['total_pages'] > $current_page) {
                    $pagination = true;
                    $current_page++;
                } else {
                    $pagination = false;
                }
            } else {

                if ($pagination)
                    $pagination = false;
            }

            if (isset($json['result']) && is_array($json['result'])) {

                foreach ($json['result'] as $domain_data) {

                    if (!isset($domain_data['name']) || !isset($domain_data['id'])) {
                        $error = __('Unable to retrive zone id due to invalid response data', 'WPOven Triple Cache');
                        return false;
                    }

                    $zone_id_list[$domain_data['name']] = $domain_data['id'];
                }
            }
        } while ($pagination);


        if (!count($zone_id_list)) {
            $error = __('Unable to find domains configured on Cloudflare', 'WPOven Triple Cache');
            return false;
        }
       
        return $zone_id_list;
    }


    function get_current_browser_cache_ttl(&$error)
    {

        $this->objects = $this->main_instance->get_objects();
        $cf_headers = $this->get_api_headers();

        $response = wp_remote_get(
            esc_url_raw("https://api.cloudflare.com/client/v4/zones/{$this->zone_id}/settings/browser_cache_ttl"),
            $cf_headers
        );

        $response_body = wp_remote_retrieve_body($response);
        $json = json_decode($response_body, true);

        if ($json['success'] == false) {

            $error = array();

            foreach ($json['errors'] as $single_error) {
                $error[] = "{$single_error['message']} (err code: {$single_error['code']})";
            }

            $error = implode(' - ', $error);

            return false;
        }

        if (isset($json['result']) && is_array($json['result']) && isset($json['result']['value'])) {
            return $json['result']['value'];
        }

        $error = __('Unable to find Browser Cache TTL settings ', 'WPOven Triple Cache');
        return false;
    }


    function change_browser_cache_ttl($ttl, &$error)
    {

        $this->objects = $this->main_instance->get_objects();

        $cf_headers           = $this->get_api_headers();
        $cf_headers['method'] = 'PATCH';
        $cf_headers['body']   = json_encode(array('value' => $ttl));

        $response = wp_remote_post(
            esc_url_raw("https://api.cloudflare.com/client/v4/zones/{$this->zone_id}/settings/browser_cache_ttl"),
            $cf_headers
        );

        if (is_wp_error($response)) {
            return false;
        }

        $response_body = wp_remote_retrieve_body($response);

        $json = json_decode($response_body, true);

        if ($json['success'] == false) {

            $error = array();

            foreach ($json['errors'] as $single_error) {
                $error[] = "{$single_error['message']} (err code: {$single_error['code']})";
            }

            $error = implode(' - ', $error);

            return false;
        }

        return true;
    }


    function delete_page_rule($page_rule_id, &$error)
    {

        $this->objects = $this->main_instance->get_objects();

        $cf_headers = $this->get_api_headers();
        $cf_headers['method'] = 'DELETE';

        if ($page_rule_id == '') {
            return false;
        }

        if ($this->zone_id == '') {
            return false;
        }

        $response = wp_remote_post(
            esc_url_raw("https://api.cloudflare.com/client/v4/zones/{$this->zone_id}/pagerules/{$page_rule_id}"),
            $cf_headers
        );

        if (is_wp_error($response)) {
            return false;
        }

        $response_body = wp_remote_retrieve_body($response);

        $json = json_decode($response_body, true);

        if ($json['success'] == false) {

            $error = array();

            foreach ($json['errors'] as $single_error) {
                $error[] = "{$single_error['message']} (err code: {$single_error['code']})";
            }

            $error = implode(' - ', $error);

            return false;
        }

        return true;
    }


    function add_cache_everything_page_rule(&$error)
    {

        $this->objects = $this->main_instance->get_objects();

        $cf_headers = $this->get_api_headers();
        $url = $this->main_instance->home_url('/*');

        $cf_headers['method'] = 'POST';
        $cf_headers['body'] = json_encode(
            array(
                'targets' => array(
                    array(
                        'target' => 'url',
                        'constraint' => array(
                            'operator' => 'matches',
                            'value' =>  'test.devscript.cloud' //$url
                        ),
                    )
                ),
                'actions' => array(
                    array(
                        'id' => 'cache_level',
                        'value' => 'cache_everything'
                    )
                ),
                'priority' => 1,
                'status' => 'active'
            )
        );

        $response = wp_remote_post(
            esc_url_raw("https://api.cloudflare.com/client/v4/zones/{$this->zone_id}/pagerules"),
            $cf_headers
        );
        if (is_wp_error($response)) {
            return false;
        }
        $response_body = wp_remote_retrieve_body($response);

        $json = json_decode($response_body, true);

        if ($json['success'] == false) {

            $error = array();

            foreach ($json['errors'] as $single_error) {
                $error[] = "{$single_error['message']} (err code: {$single_error['code']})";
            }

            $error = implode(' - ', $error);

            return false;
        }

        if (isset($json['result']) && is_array($json['result']) && isset($json['result']['id'])) {
            return $json['result']['id'];
        }

        return false;
    }


    function add_bypass_cache_backend_page_rule(&$error)
    {

        $this->objects = $this->main_instance->get_objects();

        $cf_headers = $this->get_api_headers();
        $url = admin_url('/*');

        $cf_headers['method'] = 'POST';
        $cf_headers['body'] = json_encode(array('targets' => array(array('target' => 'url', 'constraint' => array('operator' => 'matches', 'value' => $url))), 'actions' => array(array('id' => 'cache_level', 'value' => 'bypass')), 'priority' => 1, 'status' => 'active'));

        $response = wp_remote_post(
            esc_url_raw("https://api.cloudflare.com/client/v4/zones/{$this->zone_id}/pagerules"),
            $cf_headers
        );

        if (is_wp_error($response)) {
            return false;
        }

        $response_body = wp_remote_retrieve_body($response);

        $json = json_decode($response_body, true);

        if ($json['success'] == false) {

            $error = array();

            foreach ($json['errors'] as $single_error) {
                $error[] = "{$single_error['message']} (err code: {$single_error['code']})";
            }

            $error = implode(' - ', $error);

            return false;
        }

        if (isset($json['result']) && is_array($json['result']) && isset($json['result']['id'])) {
            return $json['result']['id'];
        }

        return false;
    }


    function purge_cache(&$error)
    {

        $this->objects = $this->main_instance->get_objects();

        do_action('WPOCF_cf_purge_whole_cache_before');

        $cf_headers           = $this->get_api_headers();
        $cf_headers['method'] = 'POST';
        $cf_headers['body']   = json_encode(array('purge_everything' => true));

        $response = wp_remote_post(
            esc_url_raw("https://api.cloudflare.com/client/v4/zones/{$this->zone_id}/purge_cache"),
            $cf_headers
        );

        if (is_wp_error($response)) {
            return false;
        }

        $response_body = wp_remote_retrieve_body($response);

        $json = json_decode($response_body, true);

        if ($json['success'] == false) {

            $error = array();

            foreach ($json['errors'] as $single_error) {
                $error[] = "{$single_error['message']} (err code: {$single_error['code']})";
            }

            $error = implode(' - ', $error);

            return false;
        }

        do_action('WPOCF_cf_purge_whole_cache_after');

        return true;
    }


    private function purge_cache_urls_async($urls)
    {

        $this->objects = $this->main_instance->get_objects();

        $cf_headers = $this->get_api_headers(true);

        $chunks = array_chunk($urls, 30);

        $multi_curl = curl_multi_init();
        $curl_array = array();
        $curl_index = 0;

        foreach ($chunks as $single_chunk) {

            $curl_array[$curl_index] = curl_init();

            curl_setopt_array($curl_array[$curl_index], array(
                CURLOPT_URL => "https://api.cloudflare.com/client/v4/zones/{$this->zone_id}/purge_cache",
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => $cf_headers['timeout'],
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POST => 1,
                CURLOPT_HTTPHEADER => $cf_headers['headers'],
                CURLOPT_POSTFIELDS => json_encode(array('files' => array_values($single_chunk))),
            ));

            curl_multi_add_handle($multi_curl, $curl_array[$curl_index]);

            $curl_index++;
        }

        // execute the multi handle
        $active = null;

        do {

            $status = curl_multi_exec($multi_curl, $active);

            if ($active) {
                // Wait a short time for more activity
                curl_multi_select($multi_curl);
            }
        } while ($active && $status == CURLM_OK);

        // close the handles
        for ($i = 0; $i < $curl_index; $i++) {
            curl_multi_remove_handle($multi_curl, $curl_array[$i]);
        }

        curl_multi_close($multi_curl);

        // free up additional memory resources
        for ($i = 0; $i < $curl_index; $i++) {
            curl_close($curl_array[$i]);
        }

        return true;
    }


    function purge_cache_urls($urls, &$error, $async = true)
    {

        $this->objects = $this->main_instance->get_objects();

        do_action('WPOCF_cf_purge_cache_by_urls_before', $urls);

        $cf_headers           = $this->get_api_headers();
        $cf_headers['method'] = 'POST';

        if (count($urls) > 30) {
            $this->purge_cache_urls_async($urls);
        } else {

            $cf_headers['body'] = json_encode(array('files' => array_values($urls)));

            $response = wp_remote_post(
                esc_url_raw("https://api.cloudflare.com/client/v4/zones/{$this->zone_id}/purge_cache"),
                $cf_headers
            );

            if (is_wp_error($response)) {
                return false;
            }

            $response_body = wp_remote_retrieve_body($response);

            $json = json_decode($response_body, true);

            if ($json['success'] == false) {

                $error = array();

                foreach ($json['errors'] as $single_error) {
                    $error[] = "{$single_error['message']} (err code: {$single_error['code']})";
                }

                $error = implode(' - ', $error);

                return false;
            }
        }

        do_action('WPOCF_cf_purge_cache_by_urls_after', $urls);

        return true;
    }


    function get_account_ids(&$error)
    {

        $this->objects = $this->main_instance->get_objects();

        $this->account_id_list = array();
        $cf_headers      = $this->get_api_headers();

        $response = wp_remote_get(
            esc_url_raw('https://api.cloudflare.com/client/v4/accounts?page=1&per_page=20&direction=desc'),
            $cf_headers
        );

        if (is_wp_error($response)) {
            return false;
        }

        $response_body = wp_remote_retrieve_body($response);

        $json = json_decode($response_body, true);

        if ($json['success'] == false) {

            $error = array();

            foreach ($json['errors'] as $single_error) {
                $error[] = "{$single_error['message']} (err code: {$single_error['code']})";
            }

            $error = implode(' - ', $error);

            return false;
        }

        if (isset($json['result']) && is_array($json['result'])) {

            foreach ($json['result'] as $account_data) {

                if (!isset($account_data['id'])) {
                    $error = __('Unable to retrive account ID', 'WPOven Triple Cache');
                    return false;
                }

                $this->account_id_list[] = array('id' => $account_data['id'], 'name' => $account_data['name']);
            }
        }

        return $this->account_id_list;
    }


    function get_current_account_id(&$error)
    {

        $account_id = '';

        if (count($this->account_id_list) == 0)
            $this->get_account_ids($error);

        if (count($this->account_id_list) > 1) {

            foreach ($this->account_id_list as $account_data) {

                if (strstr(strtolower($account_data['name']), strtolower($this->email)) !== false) {
                    $account_id = $account_data['id'];
                    break;
                }
            }
        } else {
            $account_id = $this->account_id_list[0]['id'];
        }

        if ($account_id == '') {
            $error = __('Unable to find a valid account ID.', 'WPOven Triple Cache');
            return false;
        }

        return $account_id;
    }

    function page_cache_test($url, &$error, $test_static = false)
    {

        $this->objects = $this->main_instance->get_objects();

        $args = array(
            'timeout'    => defined('WPOCF_CURL_TIMEOUT') ? WPOCF_CURL_TIMEOUT : 10,
            'sslverify'  => false,
            'user-agent' => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:59.0) Gecko/20100101 Firefox/59.0',
            'headers' => array(
                'Accept' => 'text/html'
            )
        );

        // First test - Home URL
        $response = wp_remote_get(esc_url_raw($url), $args);

        if (is_wp_error($response)) {
            return false;
        }

        $headers = wp_remote_retrieve_headers($response);

        if (!$test_static && !isset($headers['Wpoven-Cache'])) {
            return false;
        }

        if (!$test_static && $headers['Wpoven-Cache'] == 'no-cache') {
            return false;
        }

        if (!isset($headers['CF-Cache-Status'])) {
            return false;
        }

        if (!isset($headers['Cache-Control'])) {
            return false;
        }

        if (!$test_static && !isset($headers['Wpoven-Cache-Cache-Control'])) {
            return false;
        }

        if (strcasecmp($headers['Cache-Control'], '{resp:Wpoven-cache-cache-control}') == 0) {
            return false;
        }

        if ($this->worker_mode == true && !isset($headers['Wpoven-cache-worker-status'])) {
            return false;
        }

        if ($this->worker_mode == true && (strcasecmp($headers['Wpoven-cache-worker-status'], 'hit') == 0 || strcasecmp($headers['Wpoven-cache-worker-status'], 'miss') == 0)) {
            return true;
        }

        if (strcasecmp($headers['CF-Cache-Status'], 'HIT') == 0 || strcasecmp($headers['CF-Cache-Status'], 'MISS') == 0 || strcasecmp($headers['CF-Cache-Status'], 'EXPIRED') == 0) {
            return true;
        }

        if (strcasecmp($headers['CF-Cache-Status'], 'REVALIDATED') == 0) {
            return false;
        }

        if (strcasecmp($headers['CF-Cache-Status'], 'UPDATING') == 0) {
            return false;
        }

        if (strcasecmp($headers['CF-Cache-Status'], 'BYPASS') == 0) {
            return false;
        }

        if (strcasecmp($headers['CF-Cache-Status'], 'DYNAMIC') == 0) {
            $cookies = wp_remote_retrieve_cookies($response);
            return false;
        }
        return false;
    }


    function disable_page_cache(&$error)
    {

        $error = '';

        $this->objects = $this->main_instance->get_objects();

        // Reset old browser cache TTL
        if ($this->main_instance->get_single_config('cf_old_bc_ttl', 0) != 0)
            $this->change_browser_cache_ttl($this->main_instance->get_single_config('cf_old_bc_ttl', 0), $error);

        // Delete page rules
        if ($this->worker_mode == false && $this->main_instance->get_single_config('cf_page_rule_id', '') != '' && !$this->delete_page_rule($this->main_instance->get_single_config('cf_page_rule_id', ''), $error)) {
            return false;
        } else {
            $this->main_instance->set_single_config('cf_page_rule_id', '');
        }

        // Purge cache
        $this->purge_cache($error);

        // Reset htaccess
        $this->objects['cache_controller']->reset_htaccess();

        $this->main_instance->set_single_config('cf_worker_route_id', '');
        $this->main_instance->set_single_config('cf_cache_enabled', 0);
        $this->main_instance->update_config();

        return true;
    }


    function enable_page_cache(&$error)
    {
        $this->objects = $this->main_instance->get_objects();

        $current_cf_browser_ttl = $this->get_current_browser_cache_ttl($error);

        if ($current_cf_browser_ttl !== false) {

            $this->main_instance->set_single_config('cf_old_bc_ttl', $current_cf_browser_ttl);
        }

        if (!$this->change_browser_cache_ttl(0, $error)) {
            $this->main_instance->set_single_config('cf_cache_enabled', 0);
            $this->main_instance->update_config();
            return false;
        }

        if ($this->main_instance->get_single_config('cf_page_rule_id', '') != '' && $this->delete_page_rule($this->main_instance->get_single_config('cf_page_rule_id', ''), $error_msg)) {
            $this->main_instance->set_single_config('cf_page_rule_id', '');
        }

        if ($this->worker_mode == true) {
        } else {
            $cache_everything_page_rule_id = $this->add_cache_everything_page_rule($error);
            $this->main_instance->set_single_config('cf_page_rule_id', $cache_everything_page_rule_id);
        }

        // Update config data
        $this->main_instance->update_config();

        $this->purge_cache($error);

        $this->main_instance->set_single_config('cf_cache_enabled', 1);
        $this->main_instance->update_config();

        $this->objects['cache_controller']->write_htaccess($error);

        return true;
    }

    function ajax_test_page_cache()
    {

        check_ajax_referer('ajax-nonce-string', 'security');

        $return_array = array('status' => 'ok');
        $error_dynamic = '';
        $error_static = '';

        $url_static_resource = WPOCF_PLUGIN_URL . 'assets/testcache.html';
        $url_dynamic_resource = home_url();

        $return_array['static_resource_url'] = $url_static_resource;
        $return_array['dynamic_resource_url'] = $url_dynamic_resource;

        $headers_dyamic_resource = $this->page_cache_test($url_dynamic_resource, $error_dynamic);
    
        if (!$headers_dyamic_resource) {

            $headers_static_resource = $this->page_cache_test($url_static_resource, $error_static, true);
            $error = '';

            // Error on both dynamic and static test
            if (!$headers_static_resource) {
                $error .= __('Page caching seems not working for both dynamic and static pages.', 'WPOven Triple Cache');
            }
            // Error on dynamic test only
            else {
                $error .= sprintf(__('Page caching is working for static page but seems not working for dynamic pages.', 'WPOven Triple Cache'), $url_static_resource);
            }

            $return_array['status'] = 'error';
            $return_array['error'] = $error;

            die(json_encode($return_array));
        }

        $return_array['success_msg'] = __('Page caching is working properly', 'WPOven Triple Cache');

        die(json_encode($return_array));
    }
}

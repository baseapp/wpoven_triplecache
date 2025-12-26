<?php

/**
 * object-cache.php
 * WordPress Redis Object Cache drop-in using phpFastCache v9.2
 *
 * Place at: wp-content/object-cache.php
 *
 * Composer autoload path used:
 * wp-content/plugins/wpoven-triple-cache/includes/libraries/vendor/autoload.php
 */

if (!defined('ABSPATH')) {
    exit;
}

/* -------------------------
 * Composer Autoloader
 * ------------------------- */
$composer = WP_CONTENT_DIR . '/plugins/wpoven-triple-cache/includes/libraries/vendor/autoload.php';

if (!file_exists($composer)) {
    error_log('object-cache: Composer autoload not found at: ' . $composer);
    return;
}

require_once $composer;

/* -------------------------
 * Imports (phpFastCache v9)
 * ------------------------- */

use Phpfastcache\CacheManager;
use Phpfastcache\Config\ConfigurationOption;
use Phpfastcache\Drivers\Redis\Config as RedisConfig;

/* -------------------------
 * phpFastCache Default Config (file fallback settings)
 * ------------------------- */

CacheManager::setDefaultConfig(
    new ConfigurationOption([
        'path'       => WP_CONTENT_DIR . '/cache/triplecache', // fallback files
        'defaultTtl' => 3600,
        'preventCacheSlams' => true,
    ])
);

/* -------------------------
 * Redis Driver Config
 * ------------------------- */
/*
 * Preference order:
 * - Use defined WP_REDIS_* constants if present (safe)
 * - Otherwise fall back to local defaults (common)
 *
 * NOTE: Replace fallback password below if different.
 */

global $wpdb;

function wpoven_get_cache_options()
{
    global $wpdb;

    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    // Try to load the option safely (no get_option!)
    $row = $wpdb->get_var(
        "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'wpoven-triple-cache' LIMIT 1"
    );

    $cache = $row ? maybe_unserialize($row) : [];

    return $cache;
}

$options = wpoven_get_cache_options();

$redis_enable = isset($options['redis_enable']) ? $options['redis_enable'] : false;
$file_enable = isset($options['file_enable']) ? $options['file_enable'] : false;

$redisHost = !empty($options['redis_host']) ? $options['redis_host'] : '127.0.0.1';
$redisPort = !empty($options['redis_port']) ? (int) $options['redis_port'] : 6379;
$redisPass = isset($options['redis_password']) ? $options['redis_password'] : '';
$redisDb   = isset($options['redis_database']) ? (int) $options['redis_database'] : 0;

$redisTimeout = 1;

$redisConfig = new RedisConfig([
    'host'     => $redisHost,
    'port'     => $redisPort,
    'password' => $redisPass,
    'database' => $redisDb,
    'timeout'  => $redisTimeout,
]);


/* -------------------------
 * Create Pool (ItemPool driver)
 * ------------------------- */
$pool = null;
try {
    // use lowercase 'redis' driver name works in v9
    if ($redis_enable) {
        $pool = CacheManager::getInstance('redis', $redisConfig);
    } else {
        throw new Exception('Redis not enabled');
    }
} catch (Throwable $e) {

    error_log('object-cache: phpFastCache Redis connection failed: ' . $e->getMessage());
    // fallback to files pool
    try {

        if ($file_enable) {
            $pool = CacheManager::getInstance('files');
            error_log('object-cache: using file fallback pool');
        }
    } catch (Throwable $e2) {
        error_log('object-cache: phpFastCache fallback failed: ' . $e2->getMessage());
        return;
    }
}

if (!$pool) {
    error_log('object-cache: No cache pool available. Object cache disabled.');
    return; // completely disable object cache to avoid blank page
}

/* -------------------------
 * WP Object Cache Implementation
 * ------------------------- */
class WP_PhpFastCache_Object_Cache
{
    private $pool;
    private $local_cache = [];
    private $non_persistent_groups = [];
    private $global_groups = [];
    private $prefix;
    private $blog_prefix = '';
    private $cache_hits = 0;
    private $cache_misses = 0;

    public function __construct($pool)
    {
        $this->pool = $pool;
        global $table_prefix, $blog_id;
        $this->prefix = defined('WP_CACHE_KEY_SALT') ? WP_CACHE_KEY_SALT : (isset($table_prefix) ? $table_prefix : 'wp_');
        $this->blog_prefix = is_multisite() ? (isset($blog_id) ? $blog_id : '') : '';
    }

    private function is_admin_or_no_cache()
    {
        // Do NOT use any is_cart(), is_checkout(), is_account_page(), etc.
        // They cause infinite recursion because they rely on WP cache.

        // 1. Never cache for admin, cron, ajax
        if (is_admin() || defined('DOING_CRON') || defined('DOING_AJAX')) {
            return true;
        }

        // 2. Detect WooCommerce frontend endpoints by URL (safe)
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        $blocked_paths = [
            '/cart',
            '/checkout',
            '/my-account',
            '/order-pay',
            '/order-received',
            '/wc-api',
        ];

        foreach ($blocked_paths as $path) {
            if (strpos($uri, $path) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build a sanitized key safe for phpFastCache.
     * WordPress keys often contain ":" which phpFastCache rejects.
     * We produce: prefix + (blog?) + group + key  -> then sanitize.
     */
    private function build_key($key, $group)
    {
        if (empty($group)) {
            $group = 'default';
        }

        // If multisite and group not global, include blog prefix
        $use_blog = (is_multisite() && !in_array($group, $this->global_groups, true));
        $blog_part = $use_blog && $this->blog_prefix !== '' ? $this->blog_prefix . ':' : '';

        $raw = $this->prefix . '_' . $blog_part . $group . '_' . $key;

        // sanitize: allow a-zA-Z0-9 _ - . ; replace others with underscore
        $san = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $raw);

        // Keep length reasonable: if too long, hash it
        if (strlen($san) > 240) {
            $san = 'wpfc_' . md5($san);
        }

        return $san;
    }

    /**
     * Local (request) cache helpers
     */
    private function local_get($full_key)
    {
        if (array_key_exists($full_key, $this->local_cache)) {
            $this->cache_hits++;
            return $this->local_cache[$full_key];
        }
        $this->cache_misses++;
        return false;
    }

    private function local_set($full_key, $value)
    {
        $this->local_cache[$full_key] = $value;
    }

    private function local_delete($full_key)
    {
        if (isset($this->local_cache[$full_key])) {
            unset($this->local_cache[$full_key]);
        }
    }

    /* -------------------------
     * Core methods
     * ------------------------- */

    public function add($key, $data, $group = 'default', $expire = 0)
    {

        if ($this->is_admin_or_no_cache()) {
            return true; // skip caching
        }

        $full_key = $this->build_key($key, $group);

        // Non-persistent groups: keep only in-request
        if (in_array($group, $this->non_persistent_groups, true)) {
            if (array_key_exists($full_key, $this->local_cache)) {
                return false;
            }
            $this->local_cache[$full_key] = $data;
            return true;
        }

        try {
            $item = $this->pool->getItem($full_key);
            if ($item->isHit()) {
                return false;
            }
            $item->set($data);
            if ((int)$expire > 0) {
                $item->expiresAfter((int)$expire);
            }
            $saved = $this->pool->save($item);
            if ($saved) {
                $this->local_set($full_key, $data);
            }
            return $saved;
        } catch (Throwable $e) {
            error_log('object-cache:add error: ' . $e->getMessage());
            return false;
        }
    }

    public function set($key, $data, $group = 'default', $expire = 0)
    {

        if ($this->is_admin_or_no_cache()) {
            return true; // skip caching
        }

        $full_key = $this->build_key($key, $group);

        if (in_array($group, $this->non_persistent_groups, true)) {
            $this->local_cache[$full_key] = $data;
            return true;
        }

        try {
            $item = $this->pool->getItem($full_key);
            $item->set($data);
            if ((int)$expire > 0) {
                $item->expiresAfter((int)$expire);
            }
            $saved = $this->pool->save($item);
            if ($saved) {
                $this->local_set($full_key, $data);
            }
            return $saved;
        } catch (Throwable $e) {
            error_log('object-cache:set error: ' . $e->getMessage());
            return false;
        }
    }

    public function get($key, $group = 'default', $force = false, &$found = null)
    {
        if ($this->is_admin_or_no_cache()) {
            $found = false;
            return false; // never serve cached values in wp-admin
        }

        $full_key = $this->build_key($key, $group);

        // Local cache first (unless forced)
        if (!$force) {
            $local = $this->local_get($full_key);
            if ($local !== false) {
                $found = true;

                if (!headers_sent()) {
                    header('WPOven-Redis-File-Object-Cache: HIT');
                }

                return $local;
            }
        }

        if (in_array($group, $this->non_persistent_groups, true)) {
            $found = false;

            if (!headers_sent()) {
                header('WPOven-Redis-File-Object-Cache: MISS');
            }

            return false;
        }

        try {
            $item = $this->pool->getItem($full_key);

            if ($item->isHit()) {
                if (!headers_sent()) {
                    header('WPOven-Redis-File-Object-Cache: HIT');
                }

                $val = $item->get();
                $this->local_set($full_key, $val);
                $found = true;
                return $val;
            } else {
                if (!headers_sent()) {
                    header('WPOven-Redis-File-Object-Cache: MISS');
                }
            }
        } catch (Throwable $e) {
            error_log('object-cache:get error: ' . $e->getMessage());
        }

        $found = false;
        return false;
    }


    public function delete($key, $group = 'default')
    {
        $full_key = $this->build_key($key, $group);
        $this->local_delete($full_key);

        if (in_array($group, $this->non_persistent_groups, true)) {
            return true;
        }

        try {
            return $this->pool->deleteItem($full_key);
        } catch (Throwable $e) {
            error_log('object-cache:delete error: ' . $e->getMessage());
            return false;
        }
    }

    public function flush()
    {
        $this->local_cache = [];
        try {
            return $this->pool->clear();
        } catch (Throwable $e) {
            error_log('object-cache:flush error: ' . $e->getMessage());
            return false;
        }
    }

    public function replace($key, $data, $group = 'default', $expire = 0)
    {
        if ($this->is_admin_or_no_cache()) {
            return true; // skip caching
        }

        $found = null;
        if (!$this->get($key, $group, false, $found)) {
            return false;
        }
        return $this->set($key, $data, $group, $expire);
    }

    public function incr($key, $offset = 1, $group = 'default')
    {
        if ($this->is_admin_or_no_cache()) {
            return true; // skip caching
        }

        $curr = $this->get($key, $group);
        $curr = ($curr === false) ? 0 : (int)$curr;
        $curr += (int)$offset;
        $this->set($key, $curr, $group);
        return $curr;
    }

    public function decr($key, $offset = 1, $group = 'default')
    {
        if ($this->is_admin_or_no_cache()) {
            return true; // skip caching
        }

        return $this->incr($key, -$offset, $group);
    }

    public function add_non_persistent_groups($groups)
    {
        $groups = (array)$groups;
        $this->non_persistent_groups = array_unique(array_merge($this->non_persistent_groups, $groups));
    }

    public function add_global_groups($groups)
    {
        $groups = (array)$groups;
        $this->global_groups = array_unique(array_merge($this->global_groups, $groups));
    }

    public function switch_to_blog($blog_id)
    {
        $this->blog_prefix = $blog_id;
        $this->local_cache = [];
    }

    public function stats()
    {
        return [
            'hits' => $this->cache_hits,
            'misses' => $this->cache_misses,
        ];
    }
}

/* -------------------------
 * Instantiate global cache
 * ------------------------- */
global $wp_object_cache;
$wp_object_cache = new WP_PhpFastCache_Object_Cache($pool);

/* -------------------------
 * WordPress wrapper functions (full API)
 * ------------------------- */

function wp_cache_init()
{
    // Drop-in is self-initializing when included
}

/* Primary functions */
function wp_cache_get($key, $group = '', $force = false, &$found = null)
{
    global $wp_object_cache;
    $group = ($group === '') ? 'default' : $group;
    return $wp_object_cache->get($key, $group, $force, $found);
}

function wp_cache_set($key, $data, $group = '', $expire = 0)
{
    global $wp_object_cache;
    $group = ($group === '') ? 'default' : $group;
    return $wp_object_cache->set($key, $data, $group, $expire);
}

function wp_cache_add($key, $data, $group = '', $expire = 0)
{
    global $wp_object_cache;
    $group = ($group === '') ? 'default' : $group;
    return $wp_object_cache->add($key, $data, $group, $expire);
}

function wp_cache_replace($key, $data, $group = '', $expire = 0)
{
    global $wp_object_cache;
    $group = ($group === '') ? 'default' : $group;
    return $wp_object_cache->replace($key, $data, $group, $expire);
}

function wp_cache_delete($key, $group = '')
{
    global $wp_object_cache;
    $group = ($group === '') ? 'default' : $group;
    return $wp_object_cache->delete($key, $group);
}

function wp_cache_flush()
{
    global $wp_object_cache;
    return $wp_object_cache->flush();
}

function wp_cache_incr($key, $offset = 1, $group = '')
{
    global $wp_object_cache;
    $group = ($group === '') ? 'default' : $group;
    return $wp_object_cache->incr($key, $offset, $group);
}

function wp_cache_decr($key, $offset = 1, $group = '')
{
    global $wp_object_cache;
    $group = ($group === '') ? 'default' : $group;
    return $wp_object_cache->decr($key, $offset, $group);
}

/* Bulk operations */
function wp_cache_add_multiple(array $data, $group = '', $expire = 0)
{

    $group = ($group === '') ? 'default' : $group;
    $res = [];
    foreach ($data as $k => $v) {
        $res[$k] = wp_cache_add($k, $v, $group, $expire);
    }
    return $res;
}

function wp_cache_get_multiple($keys, $group = '', $force = false)
{
    $group = ($group === '') ? 'default' : $group;
    $out = [];
    foreach ($keys as $k) {
        $out[$k] = wp_cache_get($k, $group, $force);
    }
    return $out;
}

function wp_cache_set_multiple(array $data, $group = '', $expire = 0)
{
    $group = ($group === '') ? 'default' : $group;
    foreach ($data as $k => $v) {
        wp_cache_set($k, $v, $group, $expire);
    }
    return true;
}

function wp_cache_delete_multiple(array $keys, $group = '')
{
    $group = ($group === '') ? 'default' : $group;
    foreach ($keys as $k) {
        wp_cache_delete($k, $group);
    }
    return true;
}

/* Groups and multisite */
function wp_cache_add_non_persistent_groups($groups)
{
    global $wp_object_cache;
    $wp_object_cache->add_non_persistent_groups($groups);
}

function wp_cache_add_global_groups($groups)
{
    global $wp_object_cache;
    $wp_object_cache->add_global_groups($groups);
}

function wp_cache_switch_to_blog($blog_id)
{
    global $wp_object_cache;
    $wp_object_cache->switch_to_blog($blog_id);
}

function wp_cache_close()
{
    return true;
}



/* -------------------------
 * End of drop-in
 * ------------------------- */

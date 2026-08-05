<?php

if (! defined('ABSPATH')) exit;

require_once __DIR__ . '/libraries/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$ovenpress_triple_cache_update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/baseapp/ovenpress_triplecache',
    OVENPRESS_TRIPLE_CACHE_ROOT_PL,
    'ovenpress-triple-cache'
);
$ovenpress_triple_cache_update_checker->getVcsApi()->enableReleaseAssets();

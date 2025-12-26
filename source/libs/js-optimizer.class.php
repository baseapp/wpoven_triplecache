<?php

if (!defined('ABSPATH')) exit;

require_once WPOCF_PLUGIN_PATH . '/includes/libraries/vendor/autoload.php';

use AppSeeds\Defer;

class WPOCF_JS_Optimizer
{
    protected $options;

    public function __construct($options)
    {
        $this->options = $options;
    }

    public function optimize_output($html)
    {
        print_r('optimize_output called');
        // Skip optimization for admin area
        if (is_admin()) {
            return $html;
        }

        // Skip empty HTML
        if (empty($html) || !is_string($html)) {
            return $html;
        }

        // CRITICAL: defer.php only works with FULL HTML documents
        // Check if this is a complete HTML document
        if (!$this->isFullPageHtml($html)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Wpoven JS Optimizer: Not a full HTML document, skipping optimization');
            }
            return $html;
        }

        try {
            // Build defer options array
            $defer_options = [
                'optimize_scripts' => false,
                'fix_render_blocking' => true,
                'defer_third_party' => false,
                'default_defer_time' => 0,
                'inline_deferjs' => false,
                'minify_output_html' => false,
                'ignore_lazyload_texts' => [],
                'optimize_images'     => false,
                'optimize_iframes'    => false,
                'enable_lazyloading'  => false,
                'optimize_background' => false,
                'enable_lazyloading' => false,
                'ignore_lazyload_css_class' => [],
                'ignore_lazyload_css_selectors' => [],
                'img_placeholder'     => '',
            ];

            // Defer JS - this is the main option
            if (isset($this->options['jsopt_defer']) && $this->options['jsopt_defer']) {
                $defer_options['optimize_scripts'] = true;
                $defer_options['defer_third_party'] = true;
            }

            // Delay JS execution
            if (isset($this->options['jsopt_delay']) && $this->options['jsopt_delay']) {
                $defer_options['fix_render_blocking'] = true;
                $defer_options['default_defer_time'] = 0; // 3 seconds
            }

            // Safe mode - optimize but less aggressive
            if (isset($this->options['jsopt_safe_mode']) && $this->options['jsopt_safe_mode']) {
                $defer_options['optimize_scripts'] = false;
                $defer_options['defer_third_party'] = true;
            }

            // lazyload mode
            if (isset($this->options['lazyload_images']) && $this->options['lazyload_images']) {
                $defer_options['optimize_images'] = true;
                $defer_options['optimize_iframes'] = true;
                $defer_options['enable_lazyloading'] = true;
                $defer_options['optimize_background'] = true;
                $defer_options['img_placeholder'] = '';
            }

            if (isset($this->options['lazyload_threshold']) && $this->options['lazyload_threshold']) {
                $defer_options['optimize_images'] = true;
                $defer_options['enable_lazyloading'] = true;
            }

            // Add exclusion patterns
            $ignore_patterns = [
                'jquery',
                'jquery.js',
                'jquery.min.js',
                '/wp-includes/js/jquery/',
                '/wp-admin/',
                '/wp-login.php',
            ];

            // Add user exclusions
            if (isset($this->options['jsopt_exclude_patterns']) && $this->options['jsopt_exclude_patterns']) {
                $patterns = explode("\n", $this->options['jsopt_exclude_patterns']);
                foreach ($patterns as $pattern) {
                    $pattern = trim($pattern);
                    if (!empty($pattern)) {
                        $ignore_patterns[] = $pattern;
                    }
                }
            }
            $defer_options['ignore_lazyload_texts'] = $ignore_patterns;

            // Add exclusion for lazyload selectors
            $ignore_selector_patterns = [];
            if (isset($this->options['lazyload_exclude_selectors']) && $this->options['lazyload_exclude_selectors']) {
                $patterns = explode("\n", $this->options['lazyload_exclude_selectors']);
                foreach ($patterns as $pattern) {
                    $pattern = trim($pattern);
                    if (!empty($pattern)) {
                        $ignore_selector_patterns[] = $pattern;
                    }
                }
            }
            $defer_options['ignore_lazyload_css_selectors'] = $ignore_selector_patterns;

            // Add exclusion for lazyload classes
            $ignore_class_patterns = [];
            if (isset($this->options['lazyload_exclude_classes']) && $this->options['lazyload_exclude_classes']) {
                $patterns = explode("\n", $this->options['lazyload_exclude_classes']);
                foreach ($patterns as $pattern) {
                    $pattern = trim($pattern);
                    if (!empty($pattern)) {
                        $ignore_class_patterns[] = $pattern;
                    }
                }
            }

            $defer_options['ignore_lazyload_css_class'] = $ignore_class_patterns;

            // Create Defer instance with options as first parameter
            $defer = new Defer($defer_options);

            // Load HTML
            $defer->fromHtml($html);

            // Get optimized HTML
            $optimized = $defer->toHtml();

            // Log success
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Wpoven JS Optimizer: Successfully optimized HTML');
                error_log('Original length: ' . strlen($html) . ' | Optimized length: ' . strlen($optimized));
            }

            // Return optimized HTML if valid
            return !empty($optimized) ? $optimized : $html;
        } catch (Throwable $e) {
            // Log error
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Wpoven JS Optimizer Error: ' . $e->getMessage());
                error_log('File: ' . $e->getFile() . ':' . $e->getLine());
            }

            // Return original HTML on error (fail-safe)
            return $html;
        }
    }

    /**
     * Check if HTML is a full page document
     * defer.php requires full HTML documents with DOCTYPE
     */
    private function isFullPageHtml($html)
    {
        // Check for DOCTYPE and html tag in first 1000 characters
        return preg_match('/<\!DOCTYPE.+html.+<html/is', substr($html, 0, 1000)) !== false;
    }
}

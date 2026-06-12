<?php
/**
 * Radle - SEO Plugin Integration
 *
 * Adds support for SEO plugin meta tokens in Reddit post templates.
 * Supports both Yoast SEO and Rank Math SEO plugins.
 *
 * These tokens can be used in templates:
 *
 * Yoast SEO tokens:
 * - {yoast_meta_title} - Yoast SEO custom title
 * - {yoast_meta_description} - Yoast SEO custom description
 *
 * Rank Math tokens:
 * - {rankmath_meta_title} - Rank Math custom title
 * - {rankmath_meta_description} - Rank Math custom description
 *
 * @package Radle
 */

namespace Radle\Modules\Publish;

if (!defined('ABSPATH')) {
    exit;
}

class SEO_Token_Support {

    /**
     * Initialize SEO plugin token support.
     *
     * Registers the filters that add SEO meta tokens to the template token
     * values and to the available token UI list. Supports Yoast SEO and
     * Rank Math SEO.
     */
    public function __construct() {
        // Add filter to extend template tokens (for actual token replacement)
        add_filter('radle_template_tokens', [$this, 'add_seo_tokens'], 10, 3);

        // Add filter to extend available tokens list (for UI display)
        add_filter('radle_available_tokens', [$this, 'add_available_seo_tokens'], 10, 2);
    }

    /**
     * Add SEO plugin tokens to template tokens (for actual token replacement).
     *
     * Supports Yoast SEO and Rank Math SEO. Adds plugin-specific tokens
     * only when the corresponding SEO plugin is active.
     *
     * @param array $tokens Existing tokens
     * @param \WP_Post $post The post object
     * @param string $post_excerpt The generated post excerpt
     * @return array Modified tokens array
     */
    public function add_seo_tokens($tokens, $post, $post_excerpt) {
        $plugin_name = 'none';

        // Check for Yoast SEO
        if (defined('WPSEO_VERSION')) {
            $plugin_name = 'Yoast SEO';
            $yoast_title = get_post_meta($post->ID, '_yoast_wpseo_title', true);
            $yoast_description = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);

            // Process Yoast variables (replaces %%variables%% with actual values)
            if (function_exists('wpseo_replace_vars')) {
                $yoast_title = wpseo_replace_vars($yoast_title, $post);
                $yoast_description = wpseo_replace_vars($yoast_description, $post);
            }

            // Add Yoast-specific tokens
            $tokens['{yoast_meta_title}'] = !empty($yoast_title) ? $yoast_title : $post->post_title;
            $tokens['{yoast_meta_description}'] = !empty($yoast_description) ? $yoast_description : $post_excerpt;

            $this->log($plugin_name . ' tokens added: title=' . (!empty($yoast_title) ? 'custom' : 'fallback') .
                       ', description=' . (!empty($yoast_description) ? 'custom' : 'fallback'));
        }

        // Check for Rank Math (can coexist with Yoast)
        if (defined('RANK_MATH_VERSION')) {
            $plugin_name = 'Rank Math';

            // Try to get processed values from Rank Math Helper first
            $rank_math_title = '';
            $rank_math_description = '';

            if (class_exists('RankMath\Helper')) {
                // Use Rank Math Helper to get processed title and description
                $rank_math_title = \RankMath\Helper::get_post_meta('title', $post->ID);
                $rank_math_description = \RankMath\Helper::get_post_meta('description', $post->ID);

                // Process variables using Rank Math's replace_vars function
                if (function_exists('rank_math_replace_vars')) {
                    $rank_math_title = rank_math_replace_vars($rank_math_title, $post);
                    $rank_math_description = rank_math_replace_vars($rank_math_description, $post);
                } elseif (class_exists('RankMath\Replace_Vars\Replace_Vars')) {
                    $rank_math_title = \RankMath\Replace_Vars\Replace_Vars::replace_vars($rank_math_title, $post);
                    $rank_math_description = \RankMath\Replace_Vars\Replace_Vars::replace_vars($rank_math_description, $post);
                }
            } else {
                // Fallback to direct meta access
                $rank_math_title = get_post_meta($post->ID, 'rank_math_title', true);
                $rank_math_description = get_post_meta($post->ID, 'rank_math_description', true);
            }

            // Add Rank Math-specific tokens
            $tokens['{rankmath_meta_title}'] = !empty($rank_math_title) ? $rank_math_title : $post->post_title;
            $tokens['{rankmath_meta_description}'] = !empty($rank_math_description) ? $rank_math_description : $post_excerpt;

            $this->log($plugin_name . ' tokens added: title=' . (!empty($rank_math_title) ? 'custom' : 'fallback') .
                       ', description=' . (!empty($rank_math_description) ? 'custom' : 'fallback'));
        }

        return $tokens;
    }

    /**
     * Check if any SEO plugin is active.
     *
     * @return bool
     */
    public function is_seo_plugin_active() {
        return defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION');
    }

    /**
     * Get active SEO plugin name and version.
     *
     * @return array|false Array with 'name' and 'version' or false if none active
     */
    public function get_seo_plugin_info() {
        if (defined('WPSEO_VERSION')) {
            return [
                'name' => 'Yoast SEO',
                'version' => WPSEO_VERSION
            ];
        }

        if (defined('RANK_MATH_VERSION')) {
            return [
                'name' => 'Rank Math',
                'version' => RANK_MATH_VERSION
            ];
        }

        return false;
    }

    /**
     * Check if Yoast SEO is active (backwards compatibility).
     *
     * @return bool
     */
    public function is_yoast_active() {
        return defined('WPSEO_VERSION');
    }

    /**
     * Get Yoast SEO version (backwards compatibility).
     *
     * @return string|false Version string or false if not active
     */
    public function get_yoast_version() {
        return defined('WPSEO_VERSION') ? WPSEO_VERSION : false;
    }

    /**
     * Add SEO tokens to available tokens list (for UI display).
     *
     * This adds SEO tokens to the "Available Tokens" list shown in the publish metabox.
     * Only shows tokens when an SEO plugin is actually active.
     *
     * @param array $tokens Existing tokens from Lite
     * @param bool $yoast_active Whether Yoast is active (legacy parameter)
     * @return array Modified tokens array
     */
    public function add_available_seo_tokens($tokens, $yoast_active = false) {
        $seo_tokens = [];

        // Check for Yoast SEO
        if (defined('WPSEO_VERSION')) {
            // Add Yoast-specific tokens
            $seo_tokens['{yoast_meta_description}'] = __('Yoast SEO Meta Description', 'radle-lite');
            $seo_tokens['{yoast_meta_title}'] = __('Yoast SEO Meta Title', 'radle-lite');
        }

        // Check for Rank Math (can coexist with Yoast)
        if (defined('RANK_MATH_VERSION')) {
            // Add Rank Math-specific tokens
            $seo_tokens['{rankmath_meta_description}'] = __('Rank Math Meta Description', 'radle-lite');
            $seo_tokens['{rankmath_meta_title}'] = __('Rank Math Meta Title', 'radle-lite');
        }

        // Merge SEO tokens at the beginning if any were found
        if (!empty($seo_tokens)) {
            $tokens = array_merge($seo_tokens, $tokens);
        }

        return $tokens;
    }

    /**
     * Helper function for logging.
     *
     * @param string $message Message to log
     */
    private function log($message) {
        global $radleLogs;
        if ($radleLogs) {
            $radleLogs->log($message, 'radle-seo-tokens');
        }
    }
}

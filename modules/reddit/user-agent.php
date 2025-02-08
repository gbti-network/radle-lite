<?php
namespace Radle\Modules\Reddit;

/**
 * Manages User-Agent string generation for Reddit API requests.
 * 
 * This class generates a Reddit-compliant User-Agent string following
 * Reddit's API guidelines. The format is:
 * [platform]:[app ID]:[version] (by [domain])
 * 
 * Example:
 * PHP:wordpress.plugin.radle:v1.0.0 (by example.com)
 * 
 * @since 1.0.0
 */
class User_Agent {
    /** @var string Platform identifier */
    private static $platform = 'PHP';

    /** @var string Application identifier */
    private static $appId = 'wordpress.plugin.radle';

    /**
     * Get the formatted User-Agent string.
     * 
     * Generates a User-Agent string following Reddit's format guidelines:
     * [platform]:[app ID]:[version] (by [domain])
     * 
     * @return string Formatted User-Agent string
     */
    public static function get() {
        return sprintf(
            '%s:%s:v%s (by %s)',
            self::$platform,
            self::$appId,
            RADLE_VERSION,
            self::getDomain()
        );
    }

    /**
     * Get the site's domain name.
     * 
     * Extracts the host component from the site URL.
     * 
     * @return string Site domain name
     */
    private static function getDomain() {
        return wp_parse_url(get_site_url(), PHP_URL_HOST);
    }
}
<?php
namespace Radle\Modules\Reddit;

class User_Agent {
    private static $platform = 'PHP';
    private static $appId = 'wordpress.plugin.radle';

    public static function get() {
        return sprintf(
            '%s:%s:v%s (by %s)',
            self::$platform,
            self::$appId,
            RADLE_VERSION,
            self::getDomain()
        );
    }

    private static function getDomain() {
        return parse_url(get_site_url(), PHP_URL_HOST);
    }
}
<?php

namespace Auxilium\SessionHandling;

use Auxilium\Enumerators\CookieKey;
use Auxilium\ServiceInteractions\APIInteractions;
use Auxilium\Utilities\ConfigurationUtilities;
use Auxilium\Utilities\SystemSettingsUtilities;
use Composer\Config;
use Exception;

class CookieHandling
{
    /**
     * Used for getting a cookie from the client with an optional fallback value.
     *
     * @param CookieKey $targetCookie Which cookie to get.
     * @param string $default A fallback value, this will be returned if the cookie does not exist.
     * @return string Either the cookie's value, or the fallback value.
     */
    public static function GetCookieValue(CookieKey $targetCookie, string $default = ''): string
    {
        return $_COOKIE[$targetCookie->value] ?? $default;
    }

    /**
     * Used for deleting a cookie from the client.
     *
     * @param CookieKey $targetCookie Which cookie to delete.
     * @return bool Whether it was successful.
     * @throws Exception Will be thrown if there was an issue with the config file.
     */
    public static function DeleteCookie(CookieKey $targetCookie): bool
    {
        unset($_COOKIE[$targetCookie->value]);

        return setcookie(
            name: $targetCookie->value,
            value: '',
            expires_or_options: time() - 86400 * 2,
            path: '/',
            domain: SystemSettingsUtilities::GetFqdn(),
            secure: true,
            httponly: false,
        );
    }

    /**
     * Used for creating or updating the value of a cookie.
     *
     * @param CookieKey $targetCookie What cookie to create/update.
     * @param string $value The data to store within the cookie.
     * @return bool Whether it was successful.
     * @throws Exception Will be thrown if there was an issue with the config file.
     */
    public static function SetCookie(CookieKey $targetCookie, string $value): bool
    {
        $_COOKIE[$targetCookie->value] = $value;

        return setcookie(
            name: $targetCookie->value,
            value: $value,
            expires_or_options: time() + self::GetCookieTTL($targetCookie),
            path: '/',
            domain: SystemSettingsUtilities::GetFqdn(),
            secure: true,
            httponly: false,
        );
    }

    /**
     * An abstraction function used for grabbing the TTL of a cookie.
     *
     * @param CookieKey $targetCookie Which cookie we're querying about.
     * @return int What that specific cookie's TTL should be.
     */
    private static function GetCookieTTL(CookieKey $targetCookie): int
    {
        return match ($targetCookie)
        {
            // 30 minutes
            CookieKey::ACCESS_TOKEN     => (3600 / 2),

            // 48 hours
            CookieKey::SESSION_KEY      => (3600 * 48),

            // 30 days
            CookieKey::STYLE,
            CookieKey::LANGUAGE,
            CookieKey::REFRESH_TOKEN    => (3600 * 24 * 30),

            default                     => 0,
        };
    }
}

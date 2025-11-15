<?php

namespace Auxilium\SessionHandling;

use Auxilium\Enumerators\CookieKey;
use Auxilium\Enumerators\Language;
use Auxilium\Utilities\ConfigurationUtilities;

class CookieHandling
{
    public static function GetBooleanCookie(CookieKey $targetCookie, bool $default = false): bool
    {
        $cookieValue = self::GetCookieValue($targetCookie);

        if ($cookieValue === '') {
            return $default;
        }

        return $cookieValue === 'true';
    }

    public static function GetCookieValue(CookieKey $targetCookie, string $default = ''): string
    {
        return $_COOKIE[$targetCookie->value] ?? $default;
    }

    public static function DeleteCookie(CookieKey $targetCookie): bool
    {
        unset($_COOKIE[$targetCookie->value]);

        return setcookie(
            name: $targetCookie->value,
            value: '',
            expires_or_options: time() - 86400 * 2,
            path: '/',
            domain: ConfigurationUtilities::GetUserConfiguration()['Instance']['QualifiedDNS'],
            secure: true,
            httponly: false,
        );
    }

    public static function SetCookie(CookieKey $targetCookie, string $value): bool
    {
        $_COOKIE[$targetCookie->value] = $value;

        return setcookie(
            name: $targetCookie->value,
            value: $value,
            expires_or_options: time() + self::GetCookieTTL($targetCookie),
            path: '/',
            domain: ConfigurationUtilities::GetUserConfiguration()['Instance']['QualifiedDNS'],
            secure: true,
            httponly: false,
        );
    }

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

    public static function SetLanguage(Language $language): void
    {
        self::SetCookie(CookieKey::LANGUAGE, $language->value);
    }

    public static function SetStyle(string $style): void
    {
        self::SetCookie(CookieKey::STYLE, $style);
    }
}

<?php

namespace Auxilium\Utilities;

use Auxilium\Enumerators\SessionKey;
use Auxilium\ServiceInteractions\APIInteractions;

final class SystemSettingsUtilities
{
    private const CACHE_TTL_SECONDS = 300;

    public static function GetAll(bool $forceRefresh = false): array
    {
        if (!$forceRefresh)
        {
            $cached = self::getCached();

            if ($cached !== false)
            {
                return $cached['Settings'];
            }
        }

        return self::fetchAndCache();
    }

    public static function Get(string $jsonKey, mixed $default = null): mixed
    {
        $all = self::GetAll();
        return $all[$jsonKey]['value'] ?? $default;
    }

    public static function GetInstanceName(): ?string
    {
        return self::Get('instance.branding.name');
    }

    public static function GetLogoPath(): ?string
    {
        return self::Get('instance.branding.logoRelativePath');
    }

    public static function GetLogoContrastPath(): ?string
    {
        return self::Get('instance.branding.logoContrastRelativePath');
    }

    public static function GetFqdn(): ?string
    {
        return self::Get('instance.fqdn');
    }

    public static function GetDefaultLanguage(): ?string
    {
        return self::Get('instance.defaults.language');
    }

    public static function GetDefaultTimeZone(): ?string
    {
        return self::Get('instance.defaults.timeZone');
    }

    public static function InvalidateCache(): void
    {
        SessionUtilities::Delete(SessionKey::SYSTEM_SETTINGS);
    }

    private static function getCached(): array|false
    {
        $cached = SessionUtilities::Get(SessionKey::SYSTEM_SETTINGS, false);

        if ($cached === false)
        {
            return false;
        }

        $lastUpdated = $cached['LastUpdatedAt'] ?? 0;
        $expiry = time() - self::CACHE_TTL_SECONDS;

        if ($lastUpdated < $expiry)
        {
            return false;
        }

        return $cached;
    }

    private static function fetchAndCache(): array
    {
        $isLoggedIn = JWTUtilities::IsLoggedIn();

        $response = $isLoggedIn
            ? APIInteractions::Get(endpoint: '/api/v3/system-settings/visible')
            : APIInteractions::Get(endpoint: '/api/v3/system-settings/visible', requireAuth: false);

        $settings = [];

        if ($response->StatusCode === 200 && is_array($response->Payload))
        {
            foreach ($response->Payload as $setting)
            {
                $settings[$setting['key']] = $setting;
            }
        }

        $cacheEntry = [
            'LastUpdatedAt' => time(),
            'Settings'      => $settings,
        ];

        SessionUtilities::Set(SessionKey::SYSTEM_SETTINGS, $cacheEntry);

        return $settings;
    }
}

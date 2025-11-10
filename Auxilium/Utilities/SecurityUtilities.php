<?php

namespace Auxilium\Utilities;

use Auxilium\Enumerators\SessionKey;
use Auxilium\ServiceInteractions\APIInteractions;
use RuntimeException;

class SecurityUtilities
{
    private const USER_DETAILS_CACHE_TTL = 60; // seconds

    public static function RequireLogin(): void
    {
        JWTUtilities::GetJwtInfo();
    }

    public static function IsAdmin(): bool
    {
        $userDetails = self::getCachedUserDetails();

        if (self::shouldRefreshUserDetails($userDetails)) {
            $userDetails = self::fetchAndCacheUserDetails();
        }

        return $userDetails['IsAdmin'] ?? false;
    }

    private static function getCachedUserDetails(): array|false
    {
        return SessionUtilities::Get(SessionKey::USER_DETAILS, false);
    }

    private static function shouldRefreshUserDetails(array|false $userDetails): bool
    {
        if ($userDetails === false) {
            return true;
        }

        $lastUpdated = $userDetails['LastUpdatedAt'] ?? 0;
        $cacheExpiry = time() - self::USER_DETAILS_CACHE_TTL;

        return $lastUpdated < $cacheExpiry;
    }

    private static function fetchAndCacheUserDetails(): array
    {
        $response = APIInteractions::Get(endpoint: '/users/me');

        $userDetails = [
            'LastUpdatedAt' => time(),
            'UserID'        => $response->Payload['id'],
            'EmailAddress'  => $response->Payload['email_address'],
            'FullName'      => $response->Payload['full_name'],
            'IsAdmin'       => $response->Payload['is_admin'],
        ];

        SessionUtilities::Set(SessionKey::USER_DETAILS, $userDetails);

        return $userDetails;
    }

    public static function GeneratePseudoRandomBytes(int $length): string
    {
        $bytes = openssl_random_pseudo_bytes($length, $isStrong);

        if ($bytes === false || !$isStrong) {
            throw new RuntimeException('Failed to generate secure random bytes.');
        }

        return $bytes;
    }
}
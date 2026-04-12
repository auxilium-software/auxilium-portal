<?php

namespace Auxilium\Utilities;

use Auxilium\Enumerators\SessionKey;
use Auxilium\ServiceInteractions\APIInteractions;
use Exception;
use RuntimeException;

/**
 * Utilities to help with Security.
 */
final class SecurityUtilities
{
    /**
     * Makes sure that the current user is logged in.
     *
     * @return void Won't return anything.
     */
    public static function RequireLogin(): void
    {
        JWTUtilities::GetJwtInfo();
    }

    /**
     * Finds out whether the current user is an admin or not.
     *
     * @return bool Admin status (true==is Admin).*
     *
     * @throws Exception Thrown if there was a problem interacting with the config file.
     */
    public static function IsAdmin(): bool
    {
        $userDetails = self::getCachedUserDetails();

        if (self::shouldRefreshUserDetails($userDetails))
        {
            $userDetails = self::fetchAndCacheUserDetails();
        }

        return $userDetails['IsAdmin'] ?? false;
    }

    /**
     * Retrieves user details from the Session.
     *
     * @return array|false Either user details as an associative array, or false denoting that the user details weren't successfully gotten.
     */
    private static function getCachedUserDetails(): array|false
    {
        return SessionUtilities::Get(SessionKey::USER_DETAILS, false);
    }

    /**
     * Checks to see whether it's time to refresh the user details.
     *
     * @param array|false $userDetails The current user details.
     * @return bool Whether we should update them.
     *
     * @throws Exception Thrown if there was a problem interacting with the config file.
     */
    private static function shouldRefreshUserDetails(array|false $userDetails): bool
    {
        if ($userDetails === false) {
            return true;
        }

        $lastUpdated = $userDetails['LastUpdatedAt'] ?? 0;
        $cacheExpiry = time() - ConfigurationUtilities::GetSystemConfiguration()["Security"]["UserDetailsCacheTTL"];

        return $lastUpdated < $cacheExpiry;
    }

    /**
     * Gets new user details from the API server.
     *
     * @return array The new user details.
     */
    private static function fetchAndCacheUserDetails(): array
    {
        $response = APIInteractions::Get(endpoint: '/me');

        $userDetails = [
            'LastUpdatedAt' => time(),
            'UserID'        => $response->Payload['id'],
            'EmailAddress'  => $response->Payload['emailAddress'],
            'FullName'      => $response->Payload['fullName'],
            'IsAdmin'       => $response->Payload['isAdmin'],
        ];

        SessionUtilities::Set(SessionKey::USER_DETAILS, $userDetails);

        return $userDetails;
    }

    /**
     * Generates a pseudo random string.
     *
     * @param int $length How long the string should be.
     * @return string The generated pseudo random string.
     */
    public static function GeneratePseudoRandomBytes(int $length): string
    {
        $bytes = openssl_random_pseudo_bytes($length, $isStrong);

        if ($bytes === false || !$isStrong) {
            throw new RuntimeException('Failed to generate secure random bytes.');
        }

        return $bytes;
    }
}

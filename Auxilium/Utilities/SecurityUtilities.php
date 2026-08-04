<?php

namespace Auxilium\Utilities;

use Auxilium\Enumerators\SessionKey;
use Auxilium\ServiceInteractions\APIInteractions;
use Auxilium\TwigHandling\PageBuilder;
use Exception;
use RuntimeException;

/**
 * Utilities to help with Security.
 */
final class SecurityUtilities
{
    public static function IsLoggedIn(): bool
    {
        // this is NON-redirecting
        return JWTUtilities::IsLoggedIn();
    }

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
        return self::fetchUserDetails()['IsAdmin'] ?? false;
    }

    public static function RequireAdmin(): void
    {
        if(!self::IsAdmin())
        {
            PageBuilder::Render(
                template: '/ErrorPages/InsufficientPermissionsErrorPage.html.twig',
                variables: [
                    "RequiredPermissions" => [
                        "Administrator",
                    ],
                ],
                useAuth: false,
            );
        }
    }



    public static function GetUserId(): ?string
    {
        return self::fetchUserDetails()['Id'] ?? null;
    }
    public static function GetUserName(): ?string
    {
        return self::fetchUserDetails()['FullName'] ?? null;
    }
    public static function GetLanguagePreference(): ?string
    {
        return self::fetchUserDetails()['LanguagePreference'] ?? null;
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
    private static function fetchUserDetails(): array
    {
        $response = APIInteractions::Get(endpoint: '/api/v3/me');

        return [
            'LastUpdatedAt'         => time(),
            'UserID'                => $response->Payload['id'],
            'EmailAddress'          => $response->Payload['emailAddress'],
            'FullName'              => $response->Payload['fullName'],
            'IsAdmin'               => $response->Payload['isAdmin'],
            'LanguagePreference'    => $response->Payload['languagePreference'],
        ];
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

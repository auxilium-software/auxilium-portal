<?php

namespace Auxilium\Utilities;

use Auxilium\DataClasses\JWTPayload;
use Auxilium\Enumerators\CookieKey;
use Auxilium\ServiceInteractions\APIInteractions;
use Auxilium\SessionHandling\CookieHandling;
use Exception;
use Firebase\JWT\JWT;

class JWTUtilities
{
    /**
     * Used for getting information from the JWT in the `access_token` cookie.
     * If that cookie doesn't exist, along with no refresh token, the user will be redirected to the login page.
     * If that cookie doesn't exist but the refresh token does, the refresh token will be used to request a new access token from the API.
     *
     * @return JWTPayload Decoded JWT data.
     */
    public static function GetJwtInfo(): JWTPayload
    {
        if (!isset($_COOKIE['access_token']) && !isset($_COOKIE['refresh_token']))
        {
            NavigationUtilities::Redirect(target: '/login');
        }

        if (!isset($_COOKIE['access_token']))
        {
            return self::refreshAccessToken();
        }

        return self::decodeAccessToken($_COOKIE['access_token']);
    }

    /**
     * Used for getting new access and refresh tokens from the API and storing them in cookies.
     *
     * @return JWTPayload Decoded JWT data.
     */
    private static function refreshAccessToken(): JWTPayload
    {
        $response = APIInteractions::Post(
            endpoint: '/authentication/refresh',
            payload: ['refresh_token' => $_COOKIE['refresh_token']],
            requireAuth: false
        );

        if ($response->StatusCode !== 200) {
            NavigationUtilities::Redirect(target: '/login');
        }

        CookieHandling::SetCookie(CookieKey::ACCESS_TOKEN, $response->Payload['access_token']);
        CookieHandling::SetCookie(CookieKey::REFRESH_TOKEN, $response->Payload['refresh_token']);

        return self::decodeAccessToken($response->Payload['access_token']);
    }

    /**
     * Takes in a JWT string, grabs the data itself out of it, and turns it into a dataclass.
     *
     * @param string $token The JWT string to operate on.
     * @return JWTPayload The data stored within the JWT.
     */
    private static function decodeAccessToken(string $token): JWTPayload
    {
        try
        {
            $config = ConfigurationUtilities::GetUserConfiguration()['JWT'];

            $decodedObject = JWT::decode(
                jwt: $token,
                keyOrKeyArray: $config['SecretKey'],
                allowed_algs: [
                    $config['Algorithm'],
                ]
            );

            $decodedArray = json_decode(
                json: json_encode($decodedObject, JSON_THROW_ON_ERROR),
                associative: true,
                depth: 512,
                flags: JSON_THROW_ON_ERROR
            );

            return new JWTPayload(
                rawJWT: $token,
                assocArray: $decodedArray
            );
        }
        catch (Exception $ex)
        {
            NavigationUtilities::Redirect(target: '/logout');
        }
    }
}

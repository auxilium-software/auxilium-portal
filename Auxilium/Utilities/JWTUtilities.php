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
        if (!isset($_COOKIE[CookieKey::ACCESS_TOKEN->value]) && !isset($_COOKIE[CookieKey::REFRESH_TOKEN->value]))
        {
            NavigationUtilities::Redirect(target: '/login');
        }

        if (!isset($_COOKIE[CookieKey::ACCESS_TOKEN->value]))
        {
            return self::refreshAccessToken();
        }

        return self::decodeAccessToken($_COOKIE[CookieKey::ACCESS_TOKEN->value]);
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
            payload: ['refreshToken' => $_COOKIE[CookieKey::REFRESH_TOKEN->value]],
            requireAuth: false
        );

        if ($response->StatusCode !== 200) {
            NavigationUtilities::Redirect(target: '/login');
        }

        CookieHandling::SetCookie(CookieKey::ACCESS_TOKEN, $response->Payload['accessToken']);
        CookieHandling::SetCookie(CookieKey::REFRESH_TOKEN, $response->Payload['refreshToken']);

        return self::decodeAccessToken($response->Payload['accessToken']);
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

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
    public static function GetJwtInfo(): JWTPayload
    {
        self::ensureTokensExist();

        if (!isset($_COOKIE['access_token'])) {
            return self::refreshAccessToken();
        }

        return self::decodeAccessToken($_COOKIE['access_token']);
    }

    private static function ensureTokensExist(): void
    {
        if (!isset($_COOKIE['access_token']) && !isset($_COOKIE['refresh_token'])) {
            self::redirectToLogin();
        }
    }

    private static function refreshAccessToken(): JWTPayload
    {
        $response = APIInteractions::Post(
            endpoint: '/authentication/refresh',
            payload: ['refresh_token' => $_COOKIE['refresh_token']],
            requireAuth: false
        );

        if ($response->StatusCode !== 200) {
            self::redirectToLogin();
        }

        CookieHandling::SetCookie(CookieKey::ACCESS_TOKEN, $response->Payload['access_token']);
        CookieHandling::SetCookie(CookieKey::REFRESH_TOKEN, $response->Payload['refresh_token']);

        return self::decodeAccessToken($response->Payload['access_token']);
    }

    private static function decodeAccessToken(string $token): JWTPayload
    {
        try {
            $config = ConfigurationUtilities::GetUserConfiguration()['JWT'];

            $decodedObject = JWT::decode(
                jwt: $token,
                keyOrKeyArray: $config['SecretKey'],
                allowed_algs: [$config['Algorithm']]
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
        } catch (Exception $ex) {
            self::redirectToLogout();
        }
    }

    private static function redirectToLogin(): never
    {
        header('Location: /login');
        die();
    }

    private static function redirectToLogout(): never
    {
        header('Location: /logout');
        die();
    }
}

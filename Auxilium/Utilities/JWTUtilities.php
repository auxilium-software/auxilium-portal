<?php

namespace Auxilium\Utilities;

use Auxilium\DataClasses\JWTPayload;
use Auxilium\Enumerators\CookieKey;
use Auxilium\ServiceInteractions\APIInteractions;
use Auxilium\SessionHandling\CookieHandling;
use Exception;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JWTUtilities
{
    private static ?JWTPayload $cachedPayload = null;

    public static function IsLoggedIn(): bool
    {
        if (self::$cachedPayload !== null)
        {
            return true;
        }

        if (!isset($_COOKIE[CookieKey::ACCESS_TOKEN->value], $_COOKIE[CookieKey::REFRESH_TOKEN->value]))
        {
            return false;
        }

        try
        {
            $config = ConfigurationUtilities::GetUserConfiguration()['JWT'];

            JWT::decode(
                jwt: $_COOKIE[CookieKey::ACCESS_TOKEN->value],
                keyOrKeyArray: new Key($config['SecretKey'], $config['Algorithm'])
            );

            return true;
        }
        catch (Exception $e)
        {
            return false;
        }
    }


    public static function GetJwtInfo(): JWTPayload
    {
        if (self::$cachedPayload !== null)
        {
            return self::$cachedPayload;
        }

        if (!isset($_COOKIE[CookieKey::ACCESS_TOKEN->value]) && !isset($_COOKIE[CookieKey::REFRESH_TOKEN->value]))
        {
            NavigationUtilities::Redirect(target: '/login');
        }

        if (!isset($_COOKIE[CookieKey::ACCESS_TOKEN->value]))
        {
            return self::refreshAccessToken();
        }

        return self::decodeAccessToken($_COOKIE[CookieKey::ACCESS_TOKEN->value], allowRefreshOnFailure: true);
    }

    private static function refreshAccessToken(): JWTPayload
    {
        if (!isset($_COOKIE[CookieKey::REFRESH_TOKEN->value]))
        {
            NavigationUtilities::Redirect(target: '/login');
        }

        $response = APIInteractions::Post(
            endpoint: '/api/v3/authentication/refresh',
            payload: ['refreshToken' => $_COOKIE[CookieKey::REFRESH_TOKEN->value]],
            requireAuth: false
        );

        if ($response->StatusCode !== 200)
        {
            NavigationUtilities::Redirect(target: '/login');
        }

        CookieHandling::SetCookie(CookieKey::ACCESS_TOKEN, $response->Payload['accessToken']);
        CookieHandling::SetCookie(CookieKey::REFRESH_TOKEN, $response->Payload['refreshToken']);

        return self::decodeAccessToken($response->Payload['accessToken'], allowRefreshOnFailure: false);
    }

    private static function decodeAccessToken(string $token, bool $allowRefreshOnFailure = true): JWTPayload
    {
        try
        {
            $config = ConfigurationUtilities::GetUserConfiguration()['JWT'];

            $decodedObject = JWT::decode(
                jwt: $token,
                keyOrKeyArray: new Key($config['SecretKey'], $config['Algorithm'])
            );

            $decodedArray = json_decode(
                json: json_encode($decodedObject, JSON_THROW_ON_ERROR),
                associative: true,
                depth: 512,
                flags: JSON_THROW_ON_ERROR
            );

            $payload = new JWTPayload(
                rawJWT: $token,
                assocArray: $decodedArray
            );

            self::$cachedPayload = $payload;

            return $payload;
        }
        catch (ExpiredException $ex)
        {
            if ($allowRefreshOnFailure && isset($_COOKIE[CookieKey::REFRESH_TOKEN->value]))
            {
                return self::refreshAccessToken();
            }

            NavigationUtilities::Redirect(target: '/logout');
        }
        catch (Exception $ex)
        {
            NavigationUtilities::Redirect(target: '/logout');
        }
    }
}

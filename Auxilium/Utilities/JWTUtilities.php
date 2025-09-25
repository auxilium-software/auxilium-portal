<?php

namespace Auxilium\Utilities;

use Auxilium\DataClasses\JWTPayload;
use Auxilium\Enumerators\CookieKey;
use Auxilium\ServiceInteractions\APIInteractions;
use Auxilium\SessionHandling\CookieHandling;
use Exception;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;


class JWTUtilities
{
    public static function GetJwtInfo() : JWTPayload
    {
        if(!isset($_COOKIE["access_token"]) && !isset($_COOKIE["refresh_token"]))
        {
            header("Location: /login");
            die();
        }


        if(!isset($_COOKIE["access_token"]))
        {
            $temp = APIInteractions::Post(
                endpoint: "/authentication/refresh",
                payload: [
                    "refresh_token" => $_COOKIE["refresh_token"],
                ],
                requireAuth: false,
            );
            if($temp->StatusCode === 200)
            {
                CookieHandling::SetCookie(CookieKey::ACCESS_TOKEN, $temp->Payload['access_token']);
                CookieHandling::SetCookie(CookieKey::REFRESH_TOKEN, $temp->Payload['refresh_token']);

                $decodedJWTObject = JWT::decode(
                    jwt: $temp->Payload['access_token'],
                    keyOrKeyArray: ConfigurationUtilities::GetConfiguration()['JWT']['SecretKey'],
                    allowed_algs: [ConfigurationUtilities::GetConfiguration()['JWT']['Algorithm']],
                );
                $decodedJWTAssocArray = json_decode(json_encode($decodedJWTObject), true);
                return new JWTPayload(
                    rawJWT: $temp->Payload['access_token'],
                    assocArray: $decodedJWTAssocArray
                );
            }
        }


        try
        {
            $decodedJWTObject = JWT::decode(
                jwt: $_COOKIE["access_token"],
                keyOrKeyArray: ConfigurationUtilities::GetConfiguration()['JWT']['SecretKey'],
                allowed_algs: [ConfigurationUtilities::GetConfiguration()['JWT']['Algorithm']],
            );
        }
        /*
        catch(ExpiredException $ex)
        {
            APIInteractions::Post(
                endpoint: "/authentication/refresh",
                payload: [],
                requireAuth: false,
            );
        }
        */
        catch(Exception $ex)
        {
            header("Location: /logout");
            die();
        }

        $decodedJWTAssocArray = json_decode(json_encode($decodedJWTObject), true);
        return new JWTPayload(
            rawJWT: $_COOKIE["access_token"],
            assocArray: $decodedJWTAssocArray
        );
    }

}
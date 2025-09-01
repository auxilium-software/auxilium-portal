<?php

namespace Auxilium\Utilities;

use Auxilium\DataClasses\JWTPayload;
use Auxilium\ServiceInteractions\APIInteractions;
use Exception;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;


class JWTUtilities
{
    public static function GetJwtInfo() : JWTPayload
    {
        if(!isset($_COOKIE["access_token"]))
        {
            header("Location: /login");
            die();
        }

        try
        {
            $decodedJWTObject = JWT::decode(
                jwt: $_COOKIE["access_token"],
                keyOrKeyArray: ConfigurationUtilities::GetConfiguration()['JWT']['SecretKey'],
                allowed_algs: [ConfigurationUtilities::GetConfiguration()['JWT']['Algorithm']],
            );
        }
        catch(ExpiredException $ex)
        {
            var_dump($_COOKIE);
            die();
            APIInteractions::Post(
                endpoint: "/authentication/refresh",
                payload: [],
                requireAuth: false,
            );
        }
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
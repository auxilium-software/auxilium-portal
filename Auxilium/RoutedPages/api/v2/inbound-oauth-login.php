<?php

use Auxilium\DatabaseInteractions\RelationalDatabaseConnection;
use Auxilium\SessionHandling\CookieHandling;
use Auxilium\Utilities\EncodingTools;
use Auxilium\Utilities\NavigationUtilities;
use Auxilium\Utilities\Security;
use Jose\Component\Core\Util\RSAKey;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Signer\Eddsa;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../Configuration/Configuration/Environment.php';

if(isset($_POST["id_token"]) || isset($_GET["id_token"]))
{
    $state_claims = null;

    if(!(isset($_POST["state"]) || isset($_GET["state"])))
    {
        throw new Exception("State JWT is missing.");
    }

    $id_token = null;
    $state = null;

    if(isset($_GET["id_token"]))
    {
        $id_token = $_GET["id_token"];
    }
    if(isset($_POST["id_token"]))
    {
        $id_token = $_POST["id_token"];
    }
    if(isset($_GET["state"]))
    {
        $state = $_GET["state"];
    }
    if(isset($_POST["state"]))
    {
        $state = $_POST["state"];
    }


    try
    {
        $state_jwt = (new Lcobucci\JWT\JwtFacade())->parse(
            $state,
            new SignedWith(new Eddsa(), InMemory::base64Encoded(INSTANCE_CREDENTIAL_AUTH_JWT_EDDSA_PUBLIC_KEY)),
            new StrictValidAt(SystemClock::fromUTC()),
            new IssuedBy(INSTANCE_DOMAIN_NAME)
        );
        $state_claims = $state_jwt->claims()->all();
    }
    catch(RequiredConstraintsViolated $e)
    {
        throw new Exception("State JWT has been tampered with.");
    }

    $trusted_jwks = [];

    $jwt_header = json_decode(EncodingTools::Base64DecodeURLSafe(explode(".", $id_token)[0]), true);
    $jwt_alg = $jwt_header["alg"];

    $token_valid = false;
    $openid_token = null;
    $token_validator = null;

    foreach(INSTANCE_CREDENTIAL_OPENID_SOURCES as &$openid_config)
    {
        // cache jwks for INSTANCE_CREDENTIAL_OPENID_CACHE_TIME
        $jwk_cache_path = LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/jwk-cache-" . $openid_config["unique_name"] . ".json";
        $jwk_cache = null;
        $refresh_jwk = false;
        if(file_exists($jwk_cache_path))
        {
            $jwk_cache = json_decode(file_get_contents($jwk_cache_path), true);
            if($jwk_cache["created"] < (time() - INSTANCE_CREDENTIAL_OPENID_CACHE_TIME))
            {
                $refresh_jwk = true;
            }
        }
        else
        {
            $refresh_jwk = true;
        }
        if($refresh_jwk)
        {
            $inner_content = json_decode(file_get_contents($openid_config["jwk_discovery"]), true);
            $jwk_cache = [
                "keys" => $inner_content["keys"]
            ];
            $jwk_cache["created"] = time();
            file_put_contents($jwk_cache_path, json_encode($jwk_cache));
        }
        foreach($jwk_cache["keys"] as &$trusted_jwk)
        {
            $jwk = new Jose\Component\Core\JWK($trusted_jwk);
            $signer = null;
            switch($jwk->get("kty"))
            {
                case "RSA":
                    switch($jwt_alg)
                    {
                        case "RS256":
                            $signer = new Sha256();
                            break;
                        default:
                            break;
                    }
                    break;
                default:
                    break;
            }
            if($signer == null)
            {
                continue; // We don't support this algorithm, exit.
            }
            //echo json_encode($jwk->all(), JSON_PRETTY_PRINT);
            $pem_key_content = RSAKey::createFromJWK($jwk)->toPEM();
            //echo $pem_key_content;
            //die();
            $key = InMemory::plainText($pem_key_content);

            //echo $pem_key_content;

            try
            {
                $openid_token = (new Lcobucci\JWT\JwtFacade())->parse(
                    $id_token,
                    new SignedWith($signer, $key),
                    new StrictValidAt(SystemClock::fromUTC())
                );
                $token_valid = true;
                break;
            }
            catch(RequiredConstraintsViolated $e)
            {
                continue;
            }
        }

        if($token_valid)
        {
            $token_validator = $openid_config["unique_name"];
            break;
        }
    }

    $openid_claims = null;

    if($token_valid)
    {
        $openid_claims = $openid_token->claims()->all();
        if($openid_claims["nonce"] !== $state_claims["nonce"])
        {
            $token_valid = false;
            throw new Exception("State JWT nonce does not match OpenID nonce.");
        }
    }

    if($token_valid)
    {
        $combined_id = $token_validator . "/" . $openid_claims["sub"];
        echo "<pre>";
        echo "openid_unique_name => ";
        echo $combined_id;
        echo "\n\n";
        echo "openid_jwt_claims => \n";
        echo json_encode($openid_claims, JSON_PRETTY_PRINT);
        echo "\n\n";
        echo "state_jwt_claims => \n";
        echo json_encode($state_claims, JSON_PRETTY_PRINT);
        echo "</pre>";

        switch($state_claims["intent"])
        {
            case "REGISTER_OAUTH":
                //echo $combined_id." => ".$state_claims["sub"];
                $bind_variables = [
                    "unique_sub" => $combined_id
                ];
                $sql = "SELECT unique_sub, user_uuid FROM oauth_logins WHERE unique_sub=:unique_sub";
                $statement = RelationalDatabaseConnection::get_pdo()->prepare($sql);
                $statement->execute($bind_variables);
                $returned_data = $statement->fetch();
                if($returned_data == null)
                {
                    $bind_variables = [
                        "user_uuid" => $state_claims["sub"],
                        "unique_sub" => $combined_id
                    ];
                    $sql = "INSERT INTO oauth_logins (unique_sub, user_uuid) VALUES (:unique_sub, :user_uuid)";
                    $statement = RelationalDatabaseConnection::get_pdo()->prepare($sql);
                    $statement->execute($bind_variables);
                    NavigationUtilities::Redirect(target: "/graph/~" . $state_claims["sub"]);
                    exit();
                }
                else
                {
                    NavigationUtilities::Redirect(target: "/users/" . $state_claims["sub"] . "/add-login-method?error=ALREADY_ASSIGNED");
                    exit();
                    //throw new \Exception("This OAuth unique ID has already been used.");
                }
                break;
            case "LOGIN":
                $bind_variables = [
                    "unique_sub" => $combined_id
                ];
                $sql = "SELECT unique_sub, user_uuid FROM oauth_logins WHERE unique_sub=:unique_sub";
                $statement = RelationalDatabaseConnection::get_pdo()->prepare($sql);
                $statement->execute($bind_variables);
                $returned_data = $statement->fetch();
                if($returned_data == null)
                {
                    throw new Exception("No user with this OAuth unique ID, direct OAuth signup not supported on this configuration.");
                }
                else
                {
                    $session_key = rtrim(strtr(base64_encode(Security::GeneratePseudoRandomBytes(length: 64)), '+/', '-_'), '='); // Taken from /login

                    $session_info = [
                        "session_uuid" => EncodingTools::GenerateNewUUID("sessions"),
                        "session_key" => $session_key,
                        "user_uuid" => $returned_data["user_uuid"],
                        "unique_sub" => $combined_id,
                        "ip_address" => $_SERVER["REMOTE_ADDR"],
                        "active" => 1,
                    ];
                    $sql = "INSERT INTO portal_sessions (session_uuid, session_key, user_uuid, ip_address, active, unique_sub) VALUES (:session_uuid, :session_key, :user_uuid, :ip_address, :active, :unique_sub)";
                    $statement = RelationalDatabaseConnection::get_pdo()->prepare($sql);
                    $statement->execute($session_info);
                    CookieHandling::SetSessionKey(sessionKey: $session_key);
                    if($form_data == null)
                    {
                        NavigationUtilities::Redirect(target: "/");
                    }
                    else
                    {
                        if(count($form_data["form_stack"]) > 0)
                        {
                            Auxilium\PersistentFormData::set($form_data);
                            NavigationUtilities::Redirect(target: array_pop($form_data["form_stack"]));
                        }
                        else
                        {
                            NavigationUtilities::Redirect(target: "/");
                        }
                    }
                    exit();
                }
                break;
            default:
                throw new Exception("Invalid authentication intent.");
                break;
        }
    }
    else
    {
        throw new Exception("OpenID token has been tampered with.");
    }
}

//$redirect_uri = $openid_config["openid_login_uri"]."&redirect_uri=https%3A%2F%2F".INSTANCE_DOMAIN_NAME."%2Flogin&state=$jwt&nonce=$nonce";
//\Auxilium\Utilities\NavigationUtilities::Redirect(target: $redirect_uri);
exit();

<?php

use Auxilium\DatabaseInteractions\Deegraph\DeegraphNode;
use Auxilium\DatabaseInteractions\RelationalDatabaseConnection;
use Auxilium\EmailHandling\EmailBuilder;
use Auxilium\SessionHandling\Session;
use Auxilium\TwigHandling\PageBuilder;
use Auxilium\Utilities\EncodingTools;
use Auxilium\Utilities\NavigationUtilities;
use Auxilium\Utilities\Security;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Configuration/Configuration/Environment.php';

Security::RequireLogin();

$pb = PageBuilder::get_instance();

$uri_components = explode("/", $_SERVER["REQUEST_URI"]);
$last_uri_component = explode("?", end($uri_components));
$get_params = "";
if(count($last_uri_component) > 1)
{
    $get_params = $last_uri_component[1];
}
$uri_components[count($uri_components) - 1] = $last_uri_component[0];

array_shift($uri_components);
$method = array_shift($uri_components);
if(count($uri_components) > 0)
{
    if(mb_strlen(end($uri_components)) == 0)
    {
        array_pop($uri_components);
    }
}

$jwt_validation_passed = false; // This is used to make sure that a user has clicked a link that Auxilium has generated. 
//This is not the current state of the url_metadata, rather the state it was in when we received the request

$target_node_id_uri = $uri_components[0];

$url_metadata = Auxilium\URLMetadata::from_jwt($get_params);
if($url_metadata == null)
{
    $url_metadata = new Auxilium\URLMetadata();
    $url_metadata->setPath("{" . $target_node_id_uri . "}");
}
else
{
    $jwt_validation_passed = $url_metadata->isValid();
    if(!$jwt_validation_passed)
    {
        $url_metadata = new Auxilium\URLMetadata();
    }
}
$pb->setVariable("url_metadata", $url_metadata);
$pb->setVariable("root_url_metadata", new Auxilium\URLMetadata());
$pb->setVariable("jwt_validation_passed", $jwt_validation_passed);

if(!preg_match("/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/", $target_node_id_uri))
{
    $pb->setDefaultVariables();
    $pb->setTemplate("Pages/request-error");
    $technical_details = "Exception Type:\n    " . "Invalid User UUID";
    $technical_details .= "\nURI:\n    " . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
    $pb->setVariable("technical_details", $technical_details);
    http_response_code(400);
    $pb->render();
}

$target_node = null;
if($jwt_validation_passed)
{ // Only accept the JWT tn if it's valid
    $target_node = $url_metadata->getProperty("tn");
}
if($target_node == null)
{
    $target_node = $target_node_id_uri;
}
else
{
    $target_node = Auxilium\URLMetadata::expand_crushed_uuid($target_node);
    if($target_node != $target_node_id_uri)
    {
        $pb->setDefaultVariables();
        $pb->setTemplate("Pages/request-error");
        $technical_details = "Exception Type:\n    " . "XSS Detected";
        $technical_details .= "\nURI:\n    " . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
        $pb->setVariable("technical_details", $technical_details);
        http_response_code(400);
        $pb->render();
        exit();
    }
}
$target_node = DeegraphNode::from_id($target_node);

$pb->setVariable("user_uuid", $target_node->getId());

switch($uri_components[1])
{
    case "add-basic-login":
        if(in_array("ACT", $target_node->getPermissions()))
        {
            if((count($uri_components) > 1) && $jwt_validation_passed)
            {
                $form_validation = [
                    "email_address" => true,
                    "password" => true
                ];
                if(isset($_POST["password"]) && isset($_POST["email_address"]))
                {
                    $bind_variables = [
                        "email_address" => strtolower($_POST["email_address"]),
                    ];
                    $sql = "SELECT COUNT(*) FROM standard_logins WHERE email_address=:email_address";
                    $statement = \Auxilium\DatabaseInteractions\RelationalDatabaseConnection::get_pdo()->prepare($sql);
                    $statement->execute($bind_variables);

                    if($statement->fetchColumn() > 0)
                    {
                        $form_validation["email_address"] = false;
                    }
                    else
                    {
                        $pre_hashed_password = base64_encode(hash("sha256", $_POST["password"], true));
                        $hash_options = [
                            "cost" => 12,
                        ];
                        $hashed_password = password_hash($pre_hashed_password, PASSWORD_BCRYPT, $hash_options);

                        $word_list = json_decode(file_get_contents(__DIR__ . "/../byte-word-list.json"), true);

                        $garbage_data = Security::GeneratePseudoRandomBytes(length: 4);

                        $verification_code = $word_list[ord($garbage_data[0])] . " " . $word_list[ord($garbage_data[1])] . " " . $word_list[ord($garbage_data[2])] . " " . $word_list[ord($garbage_data[3])];
                        $temporary_data = [
                            "user_uuid" => $target_node->getId(),
                            "email_address" => strtolower($_POST["email_address"]),
                            "verification_code" => str_replace(" ", "", $verification_code),
                        ];
                        $sql = "INSERT INTO email_verification_codes (user_uuid, verification_code, email_address) VALUES (:user_uuid, :verification_code, :email_address)";
                        $statement = \Auxilium\DatabaseInteractions\RelationalDatabaseConnection::get_pdo()->prepare($sql);
                        $statement->execute($temporary_data);

                        $pb->setVariable("encoded_password_hash", base64_encode($hashed_password));

                        $form_validation = [
                            "verification_code" => true
                        ];

                        $user_name = "";

                        if($target_node->getProperty("display_name") != null)
                        {
                            $user_name = $target_node->getProperty("display_name");
                        }
                        if($target_node->getProperty("name") != null)
                        {
                            $user_name = $target_node->getProperty("name");
                        }

                        $email = (new EmailBuilder())
                            ->setTemplate("new-login-verification-code")
                            ->setTemplateProperty("verification_code", $verification_code)
                            ->setTemplateProperty("recipient_name", $user_name)
                            ->setSubject("Login security code")
                            ->addRecipient(strtolower($_POST["email_address"]), $user_name)
                            ->build();
                        \Auxilium\EmailHandling\InternetMessageTransport::send($email, "MIME");

                        $pb->setVariable("form_validation", $form_validation);
                        $pb->setTemplate("Pages/users/add-basic-login-verify");
                        $pb->render();
                        exit();
                    }
                }
                elseif(isset($_POST["email_address_verification_code"]) && isset($_POST["encoded_password_hash"]))
                {
                    $bind_variables = [
                        "verification_code" => strtolower(str_replace(" ", "", $_POST["email_address_verification_code"])),
                        "user_uuid" => $target_node->getId(),
                    ];
                    $sql = "SELECT user_uuid, email_address FROM email_verification_codes WHERE verification_code=:verification_code AND user_uuid=:user_uuid";
                    $statement = \Auxilium\DatabaseInteractions\RelationalDatabaseConnection::get_pdo()->prepare($sql);
                    $statement->execute($bind_variables);
                    $returned_data = $statement->fetch();
                    if($returned_data == null)
                    {
                        $form_validation["verification_code"] = false;
                        $pb->setVariable("encoded_password_hash", $_POST["encoded_password_hash"]);

                        $pb->setVariable("form_validation", $form_validation);
                        $pb->setTemplate("Pages/users/add-basic-login-verify");
                        $pb->render();
                        exit();
                    }
                    else
                    {
                        $bind_variables = [
                            "user_uuid" => $target_node->getId(),
                            "email_address" => $returned_data["email_address"],
                            "password" => base64_decode($_POST["encoded_password_hash"])
                        ];
                        $sql = "INSERT INTO standard_logins (email_address, user_uuid, password) VALUES (:email_address, :user_uuid, :password)";
                        $statement = \Auxilium\DatabaseInteractions\RelationalDatabaseConnection::get_pdo()->prepare($sql);
                        $statement->execute($bind_variables);

                        NavigationUtilities::Redirect(target: "/users/" . $target_node->getId() . "/login-methods");
                    }
                }
                $pb->setVariable("form_validation", $form_validation);
                $pb->setTemplate("Pages/users/add-basic-login");
                $pb->render();
                exit();
            }
            else
            {
                $pb->setDefaultVariables();
                $pb->setTemplate("Pages/request-error");
                $technical_details = "Exception Type:\n    " . "Malformed request";
                $technical_details .= "\nURI:\n    " . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
                $pb->setVariable("technical_details", $technical_details);
                http_response_code(400);
                $pb->render();
            }
        }
        else
        {
            $pb->setDefaultVariables();
            $pb->setTemplate("Pages/request-error");
            $technical_details = "Exception Type:\n    " . "Missing ACT permission for user";
            $technical_details .= "\nURI:\n    " . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
            $pb->setVariable("technical_details", $technical_details);
            http_response_code(403);
            $pb->render();
        }
        break;
    case "remove-login-method":
        if(in_array("ACT", $target_node->getPermissions()))
        {
            if((count($uri_components) > 2) && $jwt_validation_passed)
            {
                switch($uri_components[2])
                {
                    case "oauth":
                        $sub = EncodingTools::Base64DecodeURLSafe($uri_components[3]);
                        $bind_variables = [
                            "user_uuid" => $target_node->getId(),
                            "unique_sub" => $sub
                        ];
                        $sql = "DELETE FROM oauth_logins WHERE user_uuid=:user_uuid AND unique_sub=:unique_sub";
                        $statement = \Auxilium\DatabaseInteractions\RelationalDatabaseConnection::get_pdo()->prepare($sql);
                        $statement->execute($bind_variables);
                        NavigationUtilities::Redirect(target: "/users/" . $target_node->getId() . "/login-methods");
                    case "standard":
                        $sub = EncodingTools::Base64DecodeURLSafe($uri_components[3]);
                        $email = explode("/", $sub);
                        array_shift($email);
                        $email = implode("/", $email);
                        $bind_variables = [
                            "user_uuid" => $target_node->getId(),
                            "email_address" => $email
                        ];
                        $sql = "DELETE FROM standard_logins WHERE user_uuid=:user_uuid AND email_address=:email_address";
                        $statement = \Auxilium\DatabaseInteractions\RelationalDatabaseConnection::get_pdo()->prepare($sql);
                        $statement->execute($bind_variables);
                        NavigationUtilities::Redirect(target: "/users/" . $target_node->getId() . "/login-methods");
                    default:
                        $pb->setDefaultVariables();
                        $pb->setTemplate("Pages/request-error");
                        $technical_details = "Exception Type:\n    " . "Invalid login type";
                        $technical_details .= "\nURI:\n    " . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
                        $pb->setVariable("technical_details", $technical_details);
                        http_response_code(400);
                        $pb->render();
                        exit();
                }
            }
            else
            {
                $pb->setDefaultVariables();
                $pb->setTemplate("Pages/request-error");
                $technical_details = "Exception Type:\n    " . "Malformed request";
                $technical_details .= "\nURI:\n    " . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
                $pb->setVariable("technical_details", $technical_details);
                http_response_code(400);
                $pb->render();
                exit();
            }
        }
        else
        {
            $pb->setDefaultVariables();
            $pb->setTemplate("Pages/request-error");
            $technical_details = "Exception Type:\n    " . "Missing ACT permission for user";
            $technical_details .= "\nURI:\n    " . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
            $pb->setVariable("technical_details", $technical_details);
            http_response_code(403);
            $pb->render();
            exit();
        }
        break;
    case "login-methods":
        if(in_array("ACT", $target_node->getPermissions()))
        {
            if($target_node->getId() == Session::get_current()->getUser()->getId())
            {
                $pb->setVariable("is_own_account", true);
            }

            $current_sub = null;
            $sub_map = [];

            $bind_variables = [
                "user_uuid" => $target_node->getId()
            ];
            $sql = "SELECT session_uuid, ip_address, unique_sub, session_key, active, start_timestamp FROM portal_sessions WHERE user_uuid=:user_uuid";
            $statement = \Auxilium\DatabaseInteractions\RelationalDatabaseConnection::get_pdo()->prepare($sql);
            $statement->execute($bind_variables);
            $session_rows = $statement->fetchAll();

            foreach($session_rows as &$session_row)
            {
                $session_definition = [
                    "ip_address" => $session_row["ip_address"],
                    "session_uuid" => $session_row["session_uuid"],
                    "is_current" => ($session_row["session_key"] == $_COOKIE["session_key"]),
                    "active" => $session_row["active"],
                    "start_timestamp" => $session_row["start_timestamp"]
                ];
                if($session_row["session_key"] == $_COOKIE["session_key"])
                {
                    $current_sub = $session_row["unique_sub"];
                }
                if(!array_key_exists($session_row["unique_sub"], $sub_map))
                {
                    $sub_map[$session_row["unique_sub"]] = [];
                }
                $sub_map[$session_row["unique_sub"]][] = $session_definition;
            }

            $login_methods = [];
            $bind_variables = [
                "user_uuid" => $target_node->getId(),
            ];
            $sql = "SELECT email_address, user_uuid FROM standard_logins WHERE user_uuid=:user_uuid";
            $statement = RelationalDatabaseConnection::get_pdo()->prepare($sql);
            $statement->execute($bind_variables);
            $user_data = $statement->fetch();
            if($user_data != null)
            {
                $unique_sub = "auxilium/" . $user_data["email_address"];
                $sessions = [];
                if(array_key_exists($unique_sub, $sub_map))
                {
                    $sessions = $sub_map[$unique_sub];
                }
                $login_methods[] = [
                    "type" => "classic",
                    "is_current" => ($current_sub == $unique_sub),
                    "sub" => EncodingTools::Base64EncodeURLSafe($unique_sub),
                    "sessions" => $sessions
                ];
            }

            $bind_variables = [
                "user_uuid" => $target_node->getId()
            ];
            $sql = "SELECT unique_sub, user_uuid FROM oauth_logins WHERE user_uuid=:user_uuid";
            $statement = RelationalDatabaseConnection::get_pdo()->prepare($sql);
            $statement->execute($bind_variables);
            $returned_data = $statement->fetch();
            while($returned_data != null)
            {
                $sessions = [];
                if(array_key_exists($returned_data["unique_sub"], $sub_map))
                {
                    $sessions = $sub_map[$returned_data["unique_sub"]];
                }
                $login_methods[] = [
                    "type" => "oauth",
                    "vendor" => explode("/", $returned_data["unique_sub"])[0],
                    "sub" => EncodingTools::Base64EncodeURLSafe($returned_data["unique_sub"]),
                    "is_current" => ($current_sub == $returned_data["unique_sub"]),
                    "sessions" => $sessions
                ];
                $returned_data = $statement->fetch();
            }

            $pb->setVariable("login_methods", $login_methods);

            $openid_configs_printable = [];


            foreach(INSTANCE_CREDENTIAL_OPENID_SOURCES as &$openid_config)
            {
                $openid_configs_printable[] = [
                    "unique_name" => $openid_config["unique_name"],
                    "display_name" => $openid_config["brand_name"],
                ];
            }

            $pb->setVariable("openid_configs", $openid_configs_printable);
            $pb->setTemplate("Pages/users/login-methods");
        }
        else
        {
            $pb->setDefaultVariables();
            $pb->setTemplate("Pages/request-error");
            $technical_details = "Exception Type:\n    " . "Missing ACT permission for user";
            $technical_details .= "\nURI:\n    " . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
            $pb->setVariable("technical_details", $technical_details);
            http_response_code(403);
        }
        $pb->render();
        exit();
    default:
        var_dump($uri_components);
        exit();
}

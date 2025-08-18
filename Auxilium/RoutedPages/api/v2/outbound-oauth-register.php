<?php

use Auxilium\DatabaseInteractions\Deegraph\DeegraphNode;
use Auxilium\SessionHandling\Session;
use Auxilium\Utilities\EncodingTools;
use Auxilium\Utilities\NavigationUtilities;
use Auxilium\Utilities\Security;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Eddsa;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Builder;

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../Configuration/Configuration/Environment.php';

$at = Auxilium\APITools::get_instance();
$at->requireLogin();

$nonce = EncodingTools::Base64EncodeURLSafe(Security::GeneratePseudoRandomBytes(length: 16));

$uri = new URIUtilities();

$openid_config = null;

foreach(INSTANCE_CREDENTIAL_OPENID_SOURCES as &$openid_candidate_config)
{
    if($openid_candidate_config["unique_name"] == $uri->getLastURIComponent())
    {
        $openid_config = $openid_candidate_config;
    }
}

if($openid_config == null)
{
    $at->setErrorText("Invalid OpenID provider.");
    $at->output();
    exit();
}


$token_builder = (new Builder(new JoseEncoder(), ChainedFormatter::default()));
$algorithm = new Eddsa();
$signing_key = InMemory::base64Encoded(INSTANCE_CREDENTIAL_AUTH_JWT_EDDSA_PRIVATE_KEY);

$target_user_id = Session::get_current()->getUser()->getId();

if(isset($_GET["for"]))
{
    if(preg_match("/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/", $_GET["for"]))
    {
        $target_user_id = $_GET["for"];
    }
    else
    {
        $at->setErrorText("Invalid target UUID.");
        $at->output();
        exit();
    }
}

$target_node = new DeegraphNode($target_user_id);
if(in_array("ACT", $target_node->getPermissions()))
{
    $target_user_id = $target_node->getId();
}
else
{
    $at->setErrorText("Missing ACT permission for target user.");
    $at->output();
    exit();
}

$now = new DateTimeImmutable();
$token_builder
    ->issuedBy(INSTANCE_DOMAIN_NAME)
    ->permittedFor(INSTANCE_DOMAIN_NAME)
    ->issuedAt($now)
    ->canOnlyBeUsedAfter($now->modify("-5 minute"))
    ->withClaim("nonce", $nonce)
    ->relatedTo($target_user_id)
    ->withClaim("intent", "REGISTER_OAUTH")
    ->expiresAt($now->modify("+1 hour"));

$token = $token_builder->getToken($algorithm, $signing_key);

//->withClaim("sub", $uri_components[0])

$jwt = $token->toString();

$redirect_uri = $openid_config["openid_login_uri"] . "&redirect_uri=https%3A%2F%2F" . INSTANCE_DOMAIN_NAME . "%2Fapi%2Fv2%2Finbound-oauth-login&state=$jwt&nonce=$nonce";
NavigationUtilities::Redirect(target: $redirect_uri);


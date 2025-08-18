<?php

use Auxilium\Utilities\EncodingTools;
use Auxilium\Utilities\NavigationUtilities;
use Auxilium\Utilities\Security;
use Auxilium\Utilities\URIUtilities;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Eddsa;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Builder;

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../Configuration/Configuration/Environment.php';

//$at = \auxilium\APITools::get_instance();
//$at->setVariable("req", );
//$at->output();

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
    $at = Auxilium\APITools::get_instance();
    $at->setErrorText("Invalid OpenID provider.");
    $at->output();
    exit();
}


$token_builder = (new Builder(new JoseEncoder(), ChainedFormatter::default()));
$algorithm = new Eddsa();
$signing_key = InMemory::base64Encoded(INSTANCE_CREDENTIAL_AUTH_JWT_EDDSA_PRIVATE_KEY);

$now = new DateTimeImmutable();
$token_builder
    ->issuedBy(INSTANCE_DOMAIN_NAME)
    ->permittedFor(INSTANCE_DOMAIN_NAME)
    ->issuedAt($now)
    ->canOnlyBeUsedAfter($now->modify("-5 minute"))
    ->withClaim("nonce", $nonce)
    ->withClaim("intent", "LOGIN")
    ->expiresAt($now->modify("+1 hour"));

$token = $token_builder->getToken($algorithm, $signing_key);

//->withClaim("sub", $uri_components[0])

$jwt = $token->toString();

$redirect_uri = $openid_config["openid_login_uri"] . "&redirect_uri=https%3A%2F%2F" . INSTANCE_DOMAIN_NAME . "%2Fapi%2Fv2%2Finbound-oauth-login&state=$jwt&nonce=$nonce";
exit();
NavigationUtilities::Redirect(target: $redirect_uri);

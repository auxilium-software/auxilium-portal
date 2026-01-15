<?php

use Auxilium\Enumerators\CookieKey;
use Auxilium\ServiceInteractions\APIInteractions;
use Auxilium\SessionHandling\CookieHandling;
use Auxilium\TwigHandling\PageBuilder;
use Auxilium\Utilities\SecurityUtilities;
use Auxilium\Utilities\URIParsingUtilities;

require_once __DIR__ . '/../vendor/autoload.php';

try
{
    SecurityUtilities::RequireLogin();

    $userData = APIInteractions::Get(
        endpoint: '/users/' . URIParsingUtilities::GetUUIDFromURI(index: 0),
    )->Payload;

    PageBuilder::Render(
        template: '/VirtualPages/UserOverviewPage.html.twig',
        variables: [
            "UserDetails" => $userData,
        ]
    );
}
catch(Exception $e)
{
    PageBuilder::RenderInternalSystemError($e);
}

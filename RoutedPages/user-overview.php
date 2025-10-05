<?php

use Auxilium\Enumerators\CookieKey;
use Auxilium\ServiceInteractions\APIInteractions;
use Auxilium\SessionHandling\CookieHandling;
use Auxilium\TwigHandling\PageBuilder;
use Auxilium\Utilities\SecurityUtilities;
use Auxilium\Utilities\URLParsingUtilities;

require_once __DIR__ . '/../vendor/autoload.php';

try
{
    SecurityUtilities::RequireLogin();

    $userData = [];
    if(!CookieHandling::GetBooleanCookie(CookieKey::PROGRESSIVE_LOAD, false))
    {
        $userData = APIInteractions::Get(
            endpoint: '/users/' . URLParsingUtilities::GetUUIDFromURL(index: 0),
        )->Payload;
    }

    PageBuilder::Render(
        template: '/VirtualPages/UserOverviewPage.html.twig',
        variables: [
            "progressive_load" => CookieHandling::GetBooleanCookie(CookieKey::PROGRESSIVE_LOAD, false),
            "is_admin" => SecurityUtilities::IsAdmin(),
            "UserDetails" => $userData,
        ]
    );
}
catch(Exception $e)
{
    PageBuilder::RenderInternalSystemError($e);
}

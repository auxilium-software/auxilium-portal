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

    $fileData = [];
    if(!CookieHandling::GetBooleanCookie(CookieKey::PROGRESSIVE_LOAD, false))
    {
        $fileData = APIInteractions::Get(
            endpoint: '/files/' . URIParsingUtilities::GetUUIDFromURI(index: 0),
        )->Payload;
    }

    PageBuilder::Render(
        template: '/VirtualPages/FileOverviewPage.html.twig',
        variables: [
            "is_admin" => SecurityUtilities::IsAdmin(),
            "FileDetails" => $fileData,
        ]
    );
}
catch(Exception $e)
{
    PageBuilder::RenderInternalSystemError($e);
}

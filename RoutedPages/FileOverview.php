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

    $parentType = explode("/", $_SERVER['REQUEST_URI'])[1];
    $parentId = URIParsingUtilities::GetUUIDFromURI(index: 0);
    $fileId = URIParsingUtilities::GetUUIDFromURI(index: 2);

    $fileData = APIInteractions::Get(
        endpoint: "/api/v3/$parentType/" . URIParsingUtilities::GetUUIDFromURI(index: 0) . '/files/' . URIParsingUtilities::GetUUIDFromURI(index: 1),
    )->Payload;

    PageBuilder::Render(
        template: '/VirtualPages/FileOverviewPage.html.twig',
        variables: [
            "is_admin" => SecurityUtilities::IsAdmin(),
            "ParentType" => $parentType,
            "ParentId" => $parentId,
            "FileDetails" => $fileData,
        ]
    );
}
catch(Exception $e)
{
    PageBuilder::RenderInternalSystemError($e);
}

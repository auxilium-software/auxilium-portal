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

    $toDoData = APIInteractions::Get(
        endpoint: '/cases/' . URIParsingUtilities::GetUUIDFromURI(index: 0) . '/todos/' . URIParsingUtilities::GetUUIDFromURI(index: 0),
    )->Payload;

    PageBuilder::Render(
        template: '/VirtualPages/ToDoOverviewPage.html.twig',
        variables: [
            "is_admin" => SecurityUtilities::IsAdmin(),
            "CaseID" => URIParsingUtilities::GetUUIDFromURI(index: 0),
            "ToDoID" => URIParsingUtilities::GetUUIDFromURI(index: 1),
            "ToDoDetails" => $toDoData,
        ]
    );
}
catch(Exception $e)
{
    PageBuilder::RenderInternalSystemError($e);
}

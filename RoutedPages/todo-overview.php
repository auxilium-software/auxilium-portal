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

    $toDoData = [];
    if(!CookieHandling::GetBooleanCookie(CookieKey::PROGRESSIVE_LOAD, false))
    {
        $toDoData = APIInteractions::Get(
            endpoint: '/cases/' . URLParsingUtilities::GetUUIDFromURL(index: 0) . '/todos/' . URLParsingUtilities::GetUUIDFromURL(index: 0),
        )->Payload;
    }

    PageBuilder::Render(
        template: '/VirtualPages/ToDoOverviewPage.html.twig',
        variables: [
            "progressive_load" => CookieHandling::GetBooleanCookie(CookieKey::PROGRESSIVE_LOAD, false),
            "is_admin" => SecurityUtilities::IsAdmin(),
            "CaseID" => URLParsingUtilities::GetUUIDFromURL(index: 0),
            "ToDoID" => URLParsingUtilities::GetUUIDFromURL(index: 1),
            "ToDoDetails" => $toDoData,
        ]
    );
}
catch(Exception $e)
{
    PageBuilder::RenderInternalSystemError($e);
}

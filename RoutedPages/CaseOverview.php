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

    $caseData = APIInteractions::Get(
        endpoint: '/cases/' . URIParsingUtilities::GetUUIDFromURI(index: 0),
    )->Payload;

    PageBuilder::Render(
        template: '/VirtualPages/CaseOverviewPage.html.twig',
        variables: [
            "is_admin" => SecurityUtilities::IsAdmin(),
            "CaseID" => URIParsingUtilities::GetUUIDFromURI(index: 0),
            "CaseDetails" => $caseData,
        ]
    );
}
catch(Exception $e)
{
    PageBuilder::RenderInternalSystemError($e);
}

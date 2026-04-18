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
        endpoint: '/api/v3/cases/' . URIParsingUtilities::GetUUIDFromURI(index: 0),
    )->Payload;

    if(
        SecurityUtilities::IsAdmin()
        || in_array(needle: SecurityUtilities::GetUserId(), haystack: $caseData['workers'], strict: true)
    )
    {
        PageBuilder::Render(
            template: '/VirtualPages/CaseOverviewPage-CaseWorker.html.twig',
            variables: [
                "is_admin" => SecurityUtilities::IsAdmin(),
                "CaseID" => URIParsingUtilities::GetUUIDFromURI(index: 0),
                "CaseDetails" => $caseData,
            ]
        );
    }

    PageBuilder::Render(
        template: '/VirtualPages/CaseOverviewPage-Client.html.twig',
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

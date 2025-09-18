<?php

use Auxilium\Enumerators\CookieKey;
use Auxilium\ServiceInteractions\APIInteractions;
use Auxilium\SessionHandling\CookieHandling;
use Auxilium\TwigHandling\PageBuilder;
use Auxilium\Utilities\SecurityUtilities;

require_once __DIR__ . '/../vendor/autoload.php';

try
{
    SecurityUtilities::RequireLogin();

    $caseData = [];
    if(!CookieHandling::GetBooleanCookie(CookieKey::PROGRESSIVE_LOAD, false))
    {
        $caseData = APIInteractions::Get(
            endpoint: '/cases/' . str_replace(search: '/cases/', replace: '', subject: $_SERVER['REQUEST_URI']),
        )->Payload;

        foreach($caseData['clients'] as &$clientID)
        {
            $workerDetails = APIInteractions::Get(
                endpoint: '/users/' . $clientID,
            );
            $clientID = $workerDetails->Payload;
        }
        unset($clientID);
        foreach($caseData['workers'] as &$workerID)
        {
            $workerDetails = APIInteractions::Get(
                endpoint: '/users/' . $workerID,
            );
            $workerID = $workerDetails->Payload;
        }
        unset($workerID);
    }

    PageBuilder::Render(
        template: '/VirtualPages/CaseOverviewPage.html.twig',
        variables: [
            "progressive_load" => CookieHandling::GetBooleanCookie(CookieKey::PROGRESSIVE_LOAD, false),
            "is_admin" => SecurityUtilities::IsAdmin(),
            "CaseDetails" => $caseData,
        ]
    );
}
catch(Exception $e)
{
    PageBuilder::RenderInternalSystemError($e);
}

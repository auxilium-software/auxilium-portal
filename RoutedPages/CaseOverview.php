<?php

use Auxilium\Enumerators\AdditionalPropertySubject;
use Auxilium\ServiceInteractions\APIInteractions;
use Auxilium\TwigHandling\PageBuilder;
use Auxilium\Utilities\AdditionalPropertyGroupingUtilities;
use Auxilium\Utilities\SecurityUtilities;
use Auxilium\Utilities\URIParsingUtilities;

require_once __DIR__ . '/../vendor/autoload.php';

try
{

    SecurityUtilities::RequireLogin();

    $caseId = URIParsingUtilities::GetUUIDFromURI(index: 0);

    $caseData = APIInteractions::Get(
        endpoint: '/api/v3/cases/' . $caseId,
    )->Payload;

    $propertyGroups = AdditionalPropertyGroupingUtilities::GetGroups(AdditionalPropertySubject::CASES);
    $properties = is_array($caseData['additionalProperties'] ?? null) ? $caseData['additionalProperties'] : [];

    if(
        SecurityUtilities::IsAdmin()
        || in_array(needle: SecurityUtilities::GetUserId(), haystack: $caseData['workers'], strict: true)
    )
    {
        PageBuilder::Render(
            template: '/VirtualPages/CaseOverviewPage-CaseWorker.html.twig',
            variables: [
                "is_admin" => SecurityUtilities::IsAdmin(),
                "CaseID" => $caseId,
                "CaseDetails" => $caseData,
                "PropertySections" => AdditionalPropertyGroupingUtilities::BuildSections(
                    groups: $propertyGroups,
                    properties: $properties,
                    includeEmptyGroups: true
                ),
            ]
        );
    }

    PageBuilder::Render(
        template: '/VirtualPages/CaseOverviewPage-Client.html.twig',
        variables: [
            "is_admin" => SecurityUtilities::IsAdmin(),
            "CaseID" => $caseId,
            "CaseDetails" => $caseData,
            "PropertySections" => AdditionalPropertyGroupingUtilities::BuildSections(
                groups: $propertyGroups,
                properties: $properties,
                includeEmptyGroups: false
            ),
        ]
    );
}
catch(Exception $e)
{
    PageBuilder::RenderInternalSystemError($e);
}

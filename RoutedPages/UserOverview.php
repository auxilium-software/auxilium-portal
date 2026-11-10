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

    $userData = APIInteractions::Get(
        endpoint: '/api/v3/users/' . URIParsingUtilities::GetUUIDFromURI(index: 0),
    )->Payload;

    $properties = is_array($userData['additionalProperties'] ?? null) ? $userData['additionalProperties'] : [];

    PageBuilder::Render(
        template: '/VirtualPages/UserOverviewPage.html.twig',
        variables: [
            "UserDetails" => $userData,
            "PropertySections" => AdditionalPropertyGroupingUtilities::BuildSections(
                groups: AdditionalPropertyGroupingUtilities::GetGroups(AdditionalPropertySubject::USERS),
                properties: $properties,
                includeEmptyGroups: true
            ),
        ]
    );
}
catch(Exception $e)
{
    PageBuilder::RenderInternalSystemError($e);
}


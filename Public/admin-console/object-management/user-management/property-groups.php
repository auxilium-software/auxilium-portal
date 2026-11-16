<?php


use Auxilium\Enumerators\AdditionalPropertySubject;
use Auxilium\TwigHandling\PageBuilder;
use Auxilium\Utilities\AdminConsoleUtilities;
use Auxilium\Utilities\LocalisationUtilities;
use Auxilium\Utilities\SecurityUtilities;

require_once __DIR__ . '/../../../../vendor/autoload.php';

try
{
    SecurityUtilities::RequireAdmin();
    PageBuilder::Render(
        template: '/VirtualPages/AdminConsoleAdditionalPropertyGroupManagement.html.twig',
        variables: [
            ...AdminConsoleUtilities::GrabVariablesToPassIntoTwig(),
            'PropertySubject' => AdditionalPropertySubject::USERS->value,
            'SupportedLocales' => LocalisationUtilities::SUPPORTED_LOCALES,
        ]
    );
}
catch(Exception $e)
{
    PageBuilder::RenderInternalSystemError($e);
}

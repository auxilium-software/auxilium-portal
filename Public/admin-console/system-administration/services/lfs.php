<?php


use Auxilium\ServiceInteractions\APIInteractions;
use Auxilium\TwigHandling\PageBuilder;
use Auxilium\Utilities\AdminConsoleUtilities;
use Auxilium\Utilities\ConfigurationUtilities;
use Auxilium\Utilities\SecurityUtilities;

require_once __DIR__ . '/../../../../vendor/autoload.php';

try
{
    SecurityUtilities::RequireAdmin();
    PageBuilder::AutoRender(
        variables: AdminConsoleUtilities::GrabVariablesToPassIntoTwig()
    );
}
catch(Exception $e)
{
    PageBuilder::RenderInternalSystemError($e);
}

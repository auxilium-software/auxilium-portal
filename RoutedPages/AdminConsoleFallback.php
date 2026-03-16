<?php

use Auxilium\TwigHandling\PageBuilder;
use Auxilium\Utilities\AdminConsoleUtilities;
use Auxilium\Utilities\ConfigurationUtilities;

require_once __DIR__ . '/../vendor/autoload.php';


try
{
    PageBuilder::Render(
        template: '/VirtualPages/AdminConsolePlaceholderPage.html.twig',
        variables: AdminConsoleUtilities::GrabVariablesToPassIntoTwig()
    );
}
catch(Exception $e)
{
    PageBuilder::RenderInternalSystemError($e);
}

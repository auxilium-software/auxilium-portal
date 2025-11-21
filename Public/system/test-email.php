<?php

use Auxilium\TwigHandling\PageBuilder;
use Auxilium\Utilities\SecurityUtilities;

require_once __DIR__ . '/../../vendor/autoload.php';

try
{
    SecurityUtilities::RequireLogin();

    PageBuilder::AutoRender(variables: [
    ]);
}
catch(Exception $e)
{
    PageBuilder::RenderInternalSystemError($e);
}

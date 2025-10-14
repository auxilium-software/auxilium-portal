<?php

use Auxilium\Enumerators\CookieKey;
use Auxilium\Exceptions\DatabaseConnectionException;
use Auxilium\SessionHandling\CookieHandling;
use Auxilium\TwigHandling\PageBuilder;
use Auxilium\Utilities\SecurityUtilities;
use Auxilium\Wrappers\ICMPWrapper;

require_once __DIR__ . '/../vendor/autoload.php';

try
{
    SecurityUtilities::RequireLogin();

    PageBuilder::AutoRender(variables: [
        "is_admin" => SecurityUtilities::IsAdmin(),
    ]
    );
}
catch(Exception $e)
{
    PageBuilder::RenderInternalSystemError($e);
}

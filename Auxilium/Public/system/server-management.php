<?php

use Auxilium\Enumerators\CookieKey;
use Auxilium\Exceptions\DatabaseConnectionException;
use Auxilium\SessionHandling\CookieHandling;
use Auxilium\TwigHandling\PageBuilder2;
use Auxilium\Utilities\Security;
use Auxilium\Wrappers\ICMPWrapper;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../Configuration/Configuration/Environment.php';

try
{
    Security::RequireLogin();
    if(Security::IsAdmin())
    {
        PageBuilder2::AutoRender();
    }
    else
    {
        PageBuilder2::RenderInternalSystemError(new Exception("not admin"));
    }
}
catch(Exception $e)
{
    PageBuilder2::RenderInternalSystemError($e);
}

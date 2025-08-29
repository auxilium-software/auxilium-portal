<?php

use Auxilium\Enumerators\CookieKey;
use Auxilium\Exceptions\DatabaseConnectionException;
use Auxilium\SessionHandling\CookieHandling;
use Auxilium\TwigHandling\PageBuilder;
use Auxilium\Utilities\SecurityUtilities;
use Auxilium\Wrappers\ICMPWrapper;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Configuration/Configuration/Environment.php';

try
{
    SecurityUtilities::RequireLogin();

    PageBuilder::AutoRender(variables: [
        "progressive_load" => CookieHandling::GetBooleanCookie(CookieKey::PROGRESSIVE_LOAD, false),
        "is_admin" => SecurityUtilities::IsAdmin(),
    ]
    );
}
catch(Exception $e)
{
    PageBuilder::RenderInternalSystemError($e);
}

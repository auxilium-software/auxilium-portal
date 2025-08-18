<?php

use Auxilium\Enumerators\CookieKey;
use Auxilium\Exceptions\DatabaseConnectionException;
use Auxilium\SessionHandling\CookieHandling;
use Auxilium\TwigHandling\PageBuilder2;
use Auxilium\Utilities\Security;
use Auxilium\Wrappers\ICMPWrapper;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Configuration/Configuration/Environment.php';

try
{
    try
    {
        Security::RequireLogin();
        ICMPWrapper::RequireSchemaRepo();


        PageBuilder2::AutoRender(variables: [
            "progressive_load" => CookieHandling::GetBooleanCookie(CookieKey::PROGRESSIVE_LOAD, false),
            "is_admin" => Security::IsAdmin(),
        ]);
    }
    catch(DatabaseConnectionException $e)
    {
        PageBuilder2::RenderInternalSystemError($e);
    }
}
catch(Exception $e)
{
    PageBuilder2::RenderInternalSystemError($e);
}

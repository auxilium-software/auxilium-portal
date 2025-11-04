<?php

use Auxilium\Enumerators\CookieKey;
use Auxilium\Exceptions\DatabaseConnectionException;
use Auxilium\ServiceInteractions\APIInteractions;
use Auxilium\SessionHandling\CookieHandling;
use Auxilium\TwigHandling\PageBuilder;
use Auxilium\Utilities\SecurityUtilities;
use Auxilium\Wrappers\ICMPWrapper;

require_once __DIR__ . '/../vendor/autoload.php';

try
{
    SecurityUtilities::RequireLogin();

    $cases = APIInteractions::Get(
        endpoint: '/cases/mine',
    )->Payload;
    $messages = APIInteractions::Get(
        endpoint: '/messages',
    )->Payload;

    PageBuilder::AutoRender(variables: [
        "is_admin" => SecurityUtilities::IsAdmin(),
        "MyCases" => $cases,
        "MyMessages" => $messages,
    ]);
}
catch(Exception $e)
{
    PageBuilder::RenderInternalSystemError($e);
}

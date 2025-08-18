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

        PageBuilder2::Render(
            template: 'Pages/message-centre.html.twig',
            variables: [
                "target_sender"=>"82d916e8-8114-4b07-95bf-594ff8883a6f",
                "progressive_load" => CookieHandling::GetBooleanCookie(CookieKey::PROGRESSIVE_LOAD, false),
                "is_admin" => (
                    in_array(
                        needle  : "ACT",
                        haystack: \Auxilium\DatabaseInteractions\GraphDatabaseConnection::get_instance_node()->getPermissions()
                    )
                ),
            ]
        );
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

<?php

use Auxilium\Exceptions\DatabaseConnectionException;
use Auxilium\TwigHandling\PageBuilder2;
use Auxilium\Utilities\Security;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Configuration/Configuration/Environment.php';


try
{
    Security::RequireLogin();
    PageBuilder2::Render(
        template : 'Pages/all-cases.html.twig',
        variables: [
            "is_admin" => Security::IsAdmin(),
        ]
    );
}
catch(DatabaseConnectionException $e)
{
    PageBuilder2::RenderInternalSystemError($e);
}

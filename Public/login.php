<?php


use Auxilium\ServiceInteractions\APIInteractions;
use Auxilium\TwigHandling\PageBuilder;

require_once __DIR__ . '/../vendor/autoload.php';

try
{
    if(array_keys(array: $_POST) === ["email_address", "raw_password"])
    {
        APIInteractions::Post(
            endpoint: '/authentication/login',
            payload : [

            ],
        );

    }
    else
    {
        PageBuilder::AutoRender();
    }
}
catch(Exception $e)
{
    PageBuilder::RenderInternalSystemError($e);
}

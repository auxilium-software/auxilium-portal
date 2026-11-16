<?php


use Auxilium\ServiceInteractions\APIInteractions;
use Auxilium\TwigHandling\PageBuilder;

require_once __DIR__ . '/../vendor/autoload.php';

try
{
    PageBuilder::AutoRender();
}
catch(Exception $e)
{
    PageBuilder::RenderInternalSystemError($e);
}

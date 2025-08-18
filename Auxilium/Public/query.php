<?php

use Auxilium\DatabaseInteractions\GraphDatabaseConnection;
use Auxilium\SessionHandling\Session;
use Auxilium\TwigHandling\PageBuilder;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Configuration/Configuration/Environment.php';

$pb = PageBuilder::get_instance();
$pb->requireLogin();
$pb->setTemplate("Pages/query");
if(isset($_POST["query"]))
{
    $query = trim($_POST["query"]);
    $result = GraphDatabaseConnection::query(Session::get_current()->getUser(), $query);
    $pb->setVariable("result", json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    $pb->setVariable("query", $query, JSON_PRETTY_PRINT);
}
$pb->render();

<?php

use Auxilium\TwigHandling\Extensions\CommonFilters;
use Auxilium\TwigHandling\Extensions\CommonFunctions;

require_once __DIR__ . '/../vendor/autoload.php';


function requireComponents(): void
{
    if(
        file_exists(__DIR__ . '/../vendor/autoload.php')
    )
    {
        require_once __DIR__ . '/../vendor/autoload.php';
        return;
    }

    if(!file_exists(__DIR__ . '/../vendor/autoload.php'))
    {
        echo "pls install composer";
        die();
    }

    require_once __DIR__ . '/../vendor/autoload.php';
    $loader = new FilesystemLoader(__DIR__ . "/../Templates/");
    $twig = new Environment($loader, [
            "debug" => true,
            "cache" => false,
        ]
    );
    $twig->addExtension(new CommonFilters());
    $twig->addExtension(new CommonFunctions());
}


// Get the requested URI
$requestUri = $_SERVER['REQUEST_URI'];

// Remove query string if present
$path = parse_url($requestUri, PHP_URL_PATH);

// Base directory for your public files
$publicDir = __DIR__ . "/../Public";
$routedDir = __DIR__ . "/../RoutedPages";

// Map routes to corresponding files
$routes = [
    "/form"             => "$routedDir/form.php",
    "/case"             => "$routedDir/case-overview.php",
    "/user"             => "$routedDir/user-overview.php",

    "/new"              => "$routedDir/new.php",
    "/graph"            => "$routedDir/graph.php",
    "/chats/drafts"     => "$routedDir/chats/draft.php",
    "/message-centre"   => "$routedDir/message-centre.php",

    "/assets/language-packs"    => "$routedDir/assets/get-language-pack.php",

    "/email-link"   => "$routedDir/email-link.php",
];

// check for an api endpoint
if(str_starts_with($path, "/api/v1"))
{
    http_response_code(299);
    echo "404";
    die();
}
if(str_starts_with($path, "/api/v2"))
{
    $originalLimit = ini_get('memory_limit');
    $originalTimeLimit = ini_get('max_execution_time');
    ini_set('memory_limit', -1);
    ini_set('max_execution_time', 0);

    requireComponents();
    $apiResponse = APIMaster::Go();
    echo json_encode($apiResponse, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

    ini_set('memory_limit', $originalLimit);
    ini_set('max_execution_time', $originalTimeLimit);

    die();
}

// handle index page
if($path === "/")
{
    requireComponents();
    require_once "$routedDir/index.php";
    return true;
}

// Check if the request matches a predefined route
foreach($routes as $route => $file)
{
    if(str_starts_with($path, $route))
    {
        requireComponents();
        require_once $file;
        return true;
    }
}

// Check if the requested file exists with a `.php` extension
$file = $publicDir . $path . '.php';
if(file_exists($file))
{
    if($path !== "/system/init")
    {
        requireComponents();
    }
    require_once $file;
    return true;
}

// Check if the requested file exists without an extension
$file = $publicDir . $path;
if(file_exists($file))
{
    return false;
}

// Return a 404 response for unmatched routes
http_response_code(404);
echo "404 - could not route";
die();

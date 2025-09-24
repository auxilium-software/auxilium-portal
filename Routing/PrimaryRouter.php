<?php

use Auxilium\Utilities\UUIDUtilities;

require_once __DIR__ . '/../vendor/autoload.php';

// Get path without query string
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$publicDir = __DIR__ . '/../Public';

// 1. Serve root index
if ($path === '/')
{
    require_once "$publicDir/index.php";
    return true;
}

// 2. Serve public PHP files like /about → /Public/about.php
$phpFile = "$publicDir$path.php";
if (is_file($phpFile))
{
    require_once $phpFile;
    return true;
}

// 3. Serve static files directly (e.g., CSS, JS, images)
$rawFile = "$publicDir$path";
if (is_file($rawFile))
{
    return false; // Let the web server serve this
}

// 4. Custom regex routes
$routes = [
    "#^/form$#"                                                                     => __DIR__ . '/../RoutedPages/form.php',
    "#^/cases/" . UUIDUtilities::$Regex . '/todos/' . UUIDUtilities::$Regex . '$#'  => __DIR__ . '/../RoutedPages/todo-overview.php',
    "#^/cases/" . UUIDUtilities::$Regex . '$#'                                      => __DIR__ . '/../RoutedPages/case-overview.php',
    "#^/user$#"                                                                     => __DIR__ . '/../RoutedPages/user-overview.php',

    /*
    "/new"              => "$routedDir/new.php",
    "/graph"            => "$routedDir/graph.php",
    "/chats/drafts"     => "$routedDir/chats/draft.php",
    "/message-centre"   => "$routedDir/message-centre.php",

    "/assets/language-packs"    => "$routedDir/assets/get-language-pack.php",

    "/email-link"   => "$routedDir/email-link.php",
    */
];

foreach ($routes as $pattern => $file)
{
    if (preg_match($pattern, $path))
    {
        require_once $file;
        return true;
    }
}


echo 404;
die();

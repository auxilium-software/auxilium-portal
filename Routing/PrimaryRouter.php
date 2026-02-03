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
    "#^/form/"  . UUIDUtilities::$Regex . '$#'                                              => __DIR__ . '/../RoutedPages/Form.php',

    "#^/cases/" . UUIDUtilities::$Regex . '$#'                                              => __DIR__ . '/../RoutedPages/CaseOverview.php',
    "#^/cases/" . UUIDUtilities::$Regex . '/todos/' . UUIDUtilities::$Regex . '$#'          => __DIR__ . '/../RoutedPages/TodoOverview.php',
    "#^/cases/" . UUIDUtilities::$Regex . '/files/' . UUIDUtilities::$Regex . '$#'          => __DIR__ . '/../RoutedPages/FileOverview.php',
    "#^/cases/" . UUIDUtilities::$Regex . '/files/' . UUIDUtilities::$Regex . '/render$#'   => __DIR__ . '/../RoutedPages/FileRender.php',

    "#^/users/" . UUIDUtilities::$Regex . '$#'                                              => __DIR__ . '/../RoutedPages/UserOverview.php',
    "#^/users/" . UUIDUtilities::$Regex . '/files/' . UUIDUtilities::$Regex . '$#'          => __DIR__ . '/../RoutedPages/FileOverview.php',
    "#^/users/" . UUIDUtilities::$Regex . '/files/' . UUIDUtilities::$Regex . '/render$#'   => __DIR__ . '/../RoutedPages/FileRender.php',

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

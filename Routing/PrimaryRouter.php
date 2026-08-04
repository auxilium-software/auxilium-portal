<?php

use Auxilium\TwigHandling\PageBuilder;
use Auxilium\Utilities\UUIDUtilities;

require_once __DIR__ . '/../vendor/autoload.php';





register_shutdown_function(function () {
    $error = error_get_last();

    if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true))
    {
        return;
    }

    if (str_contains($error['message'], 'Maximum execution time'))
    {
        PageBuilder::OfflineRender(
            template: '/ErrorPages/FatalApiFailureErrorPage.html.twig',
            variables: ['ErrorMessage' => 'The request to the API server timed out. The API server may be unavailable.'],
        );
    }

    PageBuilder::OfflineRender(
        template: '/ErrorPages/InternalSystemErrorErrorPage.html.twig',
        variables: [],
    );
});






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
    "#^/API/BFFAPIFileProxy/.+#"                                                            => __DIR__ . '/../RoutedPages/BFFAPIFileProxy.php',
    "#^/API/BFFAPIProxy/.+#"                                                                => __DIR__ . '/../RoutedPages/BFFAPIProxy.php',

    "#^/form/"  . UUIDUtilities::$Regex . '$#'                                              => __DIR__ . '/../RoutedPages/Form.php',

    "#^/cases/" . UUIDUtilities::$Regex . '$#'                                              => __DIR__ . '/../RoutedPages/CaseOverview.php',
    "#^/cases/" . UUIDUtilities::$Regex . '/todos/' . UUIDUtilities::$Regex . '$#'          => __DIR__ . '/../RoutedPages/TodoOverview.php',
    "#^/cases/" . UUIDUtilities::$Regex . '/files/' . UUIDUtilities::$Regex . '$#'          => __DIR__ . '/../RoutedPages/FileOverview.php',
    "#^/cases/" . UUIDUtilities::$Regex . '/files/' . UUIDUtilities::$Regex . '/render$#'   => __DIR__ . '/../RoutedPages/FileRender.php',

    "#^/users/" . UUIDUtilities::$Regex . '$#'                                              => __DIR__ . '/../RoutedPages/UserOverview.php',
    "#^/users/" . UUIDUtilities::$Regex . '/files/' . UUIDUtilities::$Regex . '$#'          => __DIR__ . '/../RoutedPages/FileOverview.php',
    "#^/users/" . UUIDUtilities::$Regex . '/files/' . UUIDUtilities::$Regex . '/render$#'   => __DIR__ . '/../RoutedPages/FileRender.php',

    "#^/admin-console/object-management/user-management/" . UUIDUtilities::$Regex . "$#"    => __DIR__ . '/../RoutedPages/AdminConsoleUserOverviewPage.php',
    "#^/admin-console#"                                                                     => __DIR__ . '/../RoutedPages/AdminConsoleFallback.php',

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


PageBuilder::Render404();

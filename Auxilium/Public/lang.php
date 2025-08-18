<?php

use Auxilium\Enumerators\Language;
use Auxilium\SessionHandling\CookieHandling;
use Auxilium\Utilities\NavigationUtilities;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Configuration/Configuration/Environment.php';

/*
Cookie consent *not* required for "essential functionality"
https://ico.org.uk/for-organisations/guide-to-pecr/cookies-and-similar-technologies/
if (!isset($_COOKIE["cookie-consent"])) {
    echo $twig->render($twig_variables["selected_lang"]."/cookie-consent-required.html", $twig_variables);
    exit();
}
if ($_COOKIE["cookie-consent"] != "true") {
    echo $twig->render($twig_variables["selected_lang"]."/cookie-consent-required.html", $twig_variables);
    exit();
}
*/

if(isset($_GET["switch"]))
{
    switch($_GET["switch"])
    {
        case "cy":
            CookieHandling::SetLanguage(language: Language::WELSH);
            break;
        case "en":
        default:
            CookieHandling::SetLanguage(language: Language::ENGLISH);
            break;
    }
}

if(isset($_SERVER["HTTP_REFERER"]))
{
    if(!str_contains($_SERVER["HTTP_REFERER"], "/lang"))
    {
        NavigationUtilities::Redirect(target: $_SERVER["HTTP_REFERER"]);
    }
}
NavigationUtilities::Redirect(target: "/");

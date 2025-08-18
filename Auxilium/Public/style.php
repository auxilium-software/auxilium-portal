<?php

use Auxilium\TwigHandling\PageBuilder;
use Auxilium\Utilities\NavigationUtilities;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Configuration/Configuration/Environment.php';

$style_options = [];

function toggle_style($style_name): void
{
    global $style_options;
    if(isset($_COOKIE["style"]))
    {
        $style_options = explode(" ", $_COOKIE["style"]);
    }
    $index = array_search($style_name, $style_options, true);
    if($index !== false)
    {
        unset($style_options[$index]);
    }
    else
    {
        $style_options[] = $style_name;
    }
}

if(isset($_GET["switch"]))
{
    switch($_GET["switch"])
    {
        case "dark-mode":
            toggle_style("dark-mode");
            break;
        case "large-fonts":
            toggle_style("large-fonts");
            break;
        case "skeuomorphism":
            toggle_style("skeuomorphism");
            break;
        default:
            break;
    }
    setcookie("style", implode(" ", $style_options), time() + (3600 * 24 * 30));
}


if(isset($_SERVER["HTTP_REFERER"]))
{
    if(!str_contains($_SERVER["HTTP_REFERER"], "/style"))
    {
        NavigationUtilities::Redirect(target: $_SERVER["HTTP_REFERER"]);
    }
}
NavigationUtilities::Redirect(target: "/");

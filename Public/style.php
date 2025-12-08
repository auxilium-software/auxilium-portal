<?php

use Auxilium\Enumerators\CookieKey;
use Auxilium\SessionHandling\CookieHandling;
use Auxilium\Utilities\NavigationUtilities;

require_once __DIR__ . '/../vendor/autoload.php';

$themeToSetTo = $_GET['switch'];

CookieHandling::SetCookie(
    targetCookie: CookieKey::STYLE,
    value: $themeToSetTo
);

NavigationUtilities::Redirect(
    target: $_SERVER['HTTP_REFERER']
);

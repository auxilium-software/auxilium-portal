<?php

use Auxilium\Enumerators\CookieKey;
use Auxilium\SessionHandling\CookieHandling;
use Auxilium\Utilities\NavigationUtilities;

require_once __DIR__ . '/../vendor/autoload.php';

$languageToSetTo = $_GET['switch'];

CookieHandling::SetCookie(
    targetCookie: CookieKey::LANGUAGE,
    value: $languageToSetTo
);

NavigationUtilities::Redirect(
    target: $_SERVER['HTTP_REFERER']
);

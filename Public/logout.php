<?php

use Auxilium\Enumerators\CookieKey;
use Auxilium\SessionHandling\CookieHandling;
use Auxilium\Utilities\NavigationUtilities;

require_once __DIR__ . '/../vendor/autoload.php';

CookieHandling::DeleteCookie(targetCookie: CookieKey::ACCESS_TOKEN);
CookieHandling::DeleteCookie(targetCookie: CookieKey::REFRESH_TOKEN);

NavigationUtilities::Redirect(target: '/login');

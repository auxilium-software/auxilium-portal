<?php

use Auxilium\Utilities\LocalisationUtilities;
use Auxilium\Utilities\NavigationUtilities;

require_once __DIR__ . '/../vendor/autoload.php';

try
{
    LocalisationUtilities::SetLocale($_GET['switch'] ?? '');
}
catch (InvalidArgumentException)
{
    // ignore an unsupported/garbage ?switch value - fall through and just redirect
}

NavigationUtilities::Redirect(target: $_SERVER['HTTP_REFERER']);

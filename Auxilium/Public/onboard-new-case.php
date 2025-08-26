<?php

use Auxilium\Utilities\CacheUtilities;
use Auxilium\Utilities\NavigationUtilities;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Configuration/Configuration/Environment.php';

$formInstanceID = CacheUtilities::CreateNewForm('OnboardNewCase');
NavigationUtilities::Redirect(
    target: "/form/$formInstanceID",
);


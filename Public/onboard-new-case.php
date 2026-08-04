<?php

use Auxilium\Utilities\CacheUtilities;
use Auxilium\Utilities\JWTUtilities;
use Auxilium\Utilities\NavigationUtilities;
use Auxilium\Utilities\SecurityUtilities;
use Auxilium\Utilities\SessionUtilities;

require_once __DIR__ . '/../vendor/autoload.php';

$formInstanceID = CacheUtilities::CreateNewForm(
    formSpecName: 'OnboardNewCase',
    requireAuth: JWTUtilities::IsLoggedIn(),
);
NavigationUtilities::Redirect(
    target: "/form/$formInstanceID",
);

<?php

use Auxilium\Utilities\CacheUtilities;
use Auxilium\Utilities\NavigationUtilities;

require_once __DIR__ . '/../../vendor/autoload.php';

$formInstanceID = CacheUtilities::CreateNewForm('Survey-ScreeningUnmetLegalNeedsInVeterans-ProfessionalsAndCaseworkers');
NavigationUtilities::Redirect(
    target: "/form/$formInstanceID",
);


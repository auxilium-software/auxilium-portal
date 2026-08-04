<?php

use Auxilium\Enumerators\CookieKey;
use Auxilium\ServiceInteractions\APIInteractions;
use Auxilium\SessionHandling\CookieHandling;
use Auxilium\TwigHandling\PageBuilder;
use Auxilium\Utilities\SecurityUtilities;

require_once __DIR__ . '/../vendor/autoload.php';

try
{
    SecurityUtilities::RequireLogin();

    $hour = (int)date('H');
    if ($hour >= 0 && $hour < 12)
    {
        $timePeriod = "morning";
    }
    elseif ($hour >= 12 && $hour < 17)
    {
        $timePeriod = "afternoon";
    }
    else
    {
        $timePeriod = "evening";
    }

    PageBuilder::AutoRender(variables: [
        "TimePeriod" => $timePeriod,
    ]);
}
catch(Exception $e)
{
    PageBuilder::RenderInternalSystemError($e);
}

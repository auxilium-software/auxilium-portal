<?php

namespace Auxilium\Utilities;

use JetBrains\PhpStorm\NoReturn;

/**
 * Utilities to help with Navigation.
 */
final class NavigationUtilities
{
    /**
     * Simple abstraction function to handle internal redirects.
     */
    #[NoReturn] public static function Redirect(string $target): void
    {
        header("Location: " . $target);
        die();
    }
}

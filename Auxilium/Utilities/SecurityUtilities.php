<?php

namespace Auxilium\Utilities;

use Auxilium\DatabaseInteractions\GraphDatabaseConnection;
use Auxilium\SessionHandling\Session;
use RuntimeException;

class SecurityUtilities
{
    /**
     * If called, this function will check to see if the user is logged in.
     * If they are not, they are redirected to the login page.
     */
    public static function RequireLogin(): void
    {
        if(Session::get_current()?->sessionAuthenticated())
        {
            return;
        }

        NavigationUtilities::Redirect(target: "/login");
    }

    public static function IsAdmin(): bool
    {
        if(in_array("ACT", GraphDatabaseConnection::get_instance_node()->getPermissions(), true))
        {
            return true;
        }
        return false;
    }

    public static function GeneratePseudoRandomBytes(int $length): string
    {
        // Use openssl rand as mt_rand is known to produce duplicates.
        $temp = openssl_random_pseudo_bytes($length, $isStrong);
        if($temp == false || !$isStrong)
        {
            throw new RuntimeException("Failed to generate secure random bytes.");
        }
        return $temp;
    }

}

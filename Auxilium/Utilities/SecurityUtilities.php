<?php

namespace Auxilium\Utilities;

use Auxilium\SessionHandling\Session;
use RuntimeException;

class SecurityUtilities
{
    public static function RequireLogin(): void
    {
        $jwt = JWTUtilities::GetJwtInfo();
    }

    public static function IsAdmin(): bool
    {
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

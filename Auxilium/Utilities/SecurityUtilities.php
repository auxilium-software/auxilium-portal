<?php

namespace Auxilium\Utilities;

use Auxilium\Enumerators\SessionKey;
use Auxilium\ServiceInteractions\APIInteractions;
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
        $userDetails = SessionUtilities::Get(SessionKey::USER_DETAILS, false);
        if($userDetails === false || $userDetails['LastUpdatedAt'] > time() - 60)
        {
            $temp = APIInteractions::Get(
                endpoint: '/users/me'
            );

            $temp = [
                "LastUpdatedAt" => time(),
                "UserID"        => $temp->Payload['id'],
                "EmailAddress"  => $temp->Payload['email_address'],
                "FullName"      => $temp->Payload['full_name'],
                "IsAdmin"       => $temp->Payload['is_admin'],
            ];
            SessionUtilities::Set(SessionKey::USER_DETAILS, $temp);
            return $temp['IsAdmin'];
        }

        return $userDetails['IsAdmin'];
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

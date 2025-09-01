<?php

namespace Auxilium\Utilities;

use Auxilium\Enumerators\SessionKey;

class SessionUtilities
{
    public static function Get(SessionKey $key, $default = null)
    {
        if(isset($_SESSION[$key->value]))
        {
            return $_SESSION[$key->value];
        }
        return $default;
    }
    public static function Set(SessionKey $key, $value): void
    {
        $_SESSION[$key->value] = $value;
    }
}

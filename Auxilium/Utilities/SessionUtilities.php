<?php

namespace Auxilium\Utilities;

use Auxilium\Enumerators\SessionKey;

/**
 * Utilities to help with interacting with $_SESSION.
 */
class SessionUtilities
{
    /**
     * Gets an object from $_SESSION, if that object doesn't exist, it'll return the value of \$default.
     *
     * @param SessionKey $key Which object to retrieve.
     * @param mixed $default What data to return as a fallback
     *
     * @return mixed Will either return back the object at the given id in \$_SESSION or return back the value of \$default.
     */
    public static function Get(SessionKey $key, mixed $default = null): mixed
    {
        return $_SESSION[$key->value] ?? $default;
    }

    /**
     * Used for setting an element in \$_SESSION.
     *
     * @param SessionKey $key Which element to set.
     * @param mixed $value The data to store.
     *
     * @return void Won't return anything.
     */
    public static function Set(SessionKey $key, mixed $value): void
    {
        $_SESSION[$key->value] = $value;
    }

    /**
     * Used for deleting or "unsetting" an element on \$_SESSION
     *
     * @param SessionKey $key Which element to delete
     *
     * @return void Won't return anything.
     */
    public static function Delete(SessionKey $key): void
    {
        unset($_SESSION[$key->value]);
    }
}

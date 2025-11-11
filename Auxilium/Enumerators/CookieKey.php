<?php

namespace Auxilium\Enumerators;

/**
 * String backed enumerator for cookie keys.
 */
enum CookieKey: string
{
    case LANGUAGE = "lang";
    case SESSION_KEY = "session_key";
    case STYLE = "style";


    case ACCESS_TOKEN = "access_token";
    case REFRESH_TOKEN = "refresh_token";
}

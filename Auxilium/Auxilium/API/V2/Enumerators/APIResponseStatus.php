<?php

namespace Auxilium\API\V2\Enumerators;

enum APIResponseStatus: string
{
    case OK = "OK";
    case ERROR = "ERROR";
    case UNAUTHORISED = "UNAUTHORIZED";
}

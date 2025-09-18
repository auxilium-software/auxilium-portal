<?php

namespace Auxilium\DataClasses;

use DateTime;

class JWTPayload
{
    public string $ID;
    public string $ExpiresAt;
    public string $Sub;


    public string $RawJWTString;


    public function __construct(string $rawJWT, array|false $assocArray)
    {
        $this->ID           = $assocArray["id"];
        $this->ExpiresAt    = $assocArray["exp"];
        $this->Sub          = $assocArray["sub"];

        $this->RawJWTString = $rawJWT;
    }
}
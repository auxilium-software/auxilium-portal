<?php

namespace Auxilium\DataClasses;

use DateTime;

class JWTPayload
{
    public string $ID;
    public string $EmailAddress;
    public string $FullName;


    public string $RawJWTString;


    public function __construct(string $rawJWT, array|false $assocArray)
    {
        $this->ID           = $assocArray["id"];
        $this->EmailAddress = $assocArray["email_address"];
        $this->FullName     = $assocArray["full_name"];

        $this->RawJWTString = $rawJWT;
    }
}
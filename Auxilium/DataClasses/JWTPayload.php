<?php

namespace Auxilium\DataClasses;

class JWTPayload
{
    public string $ID;
    public string $ExpiresAt;
    public string $Sub;
    public string $Issuer;
    public string $Audience;


    public string $RawJWTString;


    public function __construct(string $rawJWT, array|false $assocArray)
    {
        $this->ID           = $assocArray["jti"];
        $this->ExpiresAt    = $assocArray["exp"];
        $this->Sub          = $assocArray["sub"];
        $this->Issuer       = $assocArray["iss"];
        $this->Audience     = $assocArray["aud"];

        $this->RawJWTString = $rawJWT;
    }
}
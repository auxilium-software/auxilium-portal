<?php

namespace Auxilium\DataClasses;

/**
 * Wrapper class for API responses so both and only the Status Code and the Payload are available easily.
 */
class APIResponsePayload
{
    /**
     * @var int The response Status Code.
     */
    public int $StatusCode;

    /**
     * @var array The response payload as an array.
     */
    public array $Payload;

    public function __construct(int $StatusCode, array $Payload)
    {
        $this->StatusCode = $StatusCode;
        $this->Payload = $Payload;
    }
}

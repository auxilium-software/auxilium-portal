<?php

namespace Auxilium\DataClasses;

/**
 * Wrapper class for API responses so both and only the Status Code and the Payload are available easily.
 */
final class APIResponsePayload
{
    /**
     * @var int The response Status Code.
     */
    public int $StatusCode;

    /**
     * @var array Any headers returned from the request.
     */
    public array $Headers;

    /**
     * @var array|string The response payload as an array.
     */
    public array|string $Payload;

    public function __construct(int $statusCode, array $headers, array|string $payload)
    {
        $this->StatusCode = $statusCode;
        $this->Headers = $headers;
        $this->Payload = $payload;
    }
}

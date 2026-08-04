<?php

use Auxilium\ServiceInteractions\APIInteractions;
use Auxilium\TwigHandling\PageBuilder;
use Auxilium\Utilities\SecurityUtilities;
use Auxilium\Utilities\URIParsingUtilities;

require_once __DIR__ . '/../vendor/autoload.php';

try
{
    SecurityUtilities::RequireLogin();

    $parentType = explode("/", $_SERVER['REQUEST_URI'])[1];
    $parentId = URIParsingUtilities::GetUUIDFromURI(index: 0);
    $fileId = URIParsingUtilities::GetUUIDFromURI(index: 1);

    $response = APIInteractions::GetRaw(
        endpoint: "/api/v3/{$parentType}/{$parentId}/files/{$fileId}/render",
    );

    http_response_code($response->StatusCode);

    $headersToForward = [
        'content-type',
        'content-length',
        'content-disposition',
        'cache-control',
    ];

    foreach ($headersToForward as $headerName)
    {
        if (isset($response->Headers[$headerName]))
        {
            $value = is_array($response->Headers[$headerName])
                ? $response->Headers[$headerName][0]
                : $response->Headers[$headerName];

            header("{$headerName}: {$value}");
        }
    }

    echo $response->Payload;
    die();
}
catch(Exception $e)
{
    PageBuilder::RenderInternalSystemError($e);
}

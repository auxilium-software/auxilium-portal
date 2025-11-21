<?php

use Auxilium\Enumerators\CookieKey;
use Auxilium\ServiceInteractions\APIInteractions;
use Auxilium\SessionHandling\CookieHandling;
use Auxilium\TwigHandling\PageBuilder;
use Auxilium\Utilities\SecurityUtilities;
use Auxilium\Utilities\URIParsingUtilities;

require_once __DIR__ . '/../vendor/autoload.php';

try
{
    SecurityUtilities::RequireLogin();

    $fileData = APIInteractions::Get(
        endpoint: '/files/' . URIParsingUtilities::GetUUIDFromURI(index: 0),
    )->Payload;

    $mimeType = $fileData['content_type'];
    $blobData = $fileData['contents'];

    header("Content-Type: $mimeType; charset=utf-8");
    echo $blobData;
    die();
}
catch(Exception $e)
{
    PageBuilder::RenderInternalSystemError($e);
}

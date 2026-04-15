<?php

use Auxilium\Enumerators\CookieKey;
use Auxilium\SessionHandling\CookieHandling;
use Auxilium\Utilities\ConfigurationUtilities;

require_once __DIR__ . '/../vendor/autoload.php';

$prefix = '/API/BFFFileProxy';
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path   = substr($uri, strlen($prefix));

$apiBase = ConfigurationUtilities::GetUserConfiguration()['API']['AvailableAt'];
$url     = $apiBase . $path;

$method = $_SERVER['REQUEST_METHOD'];

$accessToken = CookieHandling::GetCookieValue(CookieKey::ACCESS_TOKEN);
if (!$accessToken)
{
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized'], JSON_THROW_ON_ERROR);
    return;
}

$ch = curl_init($url);

$headers = [
    'Authorization: Bearer ' . $accessToken,
    'X-Forwarded-For: ' . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR']),
];

// for uploads, forward content type and raw body
if ($method === 'POST' || $method === 'PUT')
{
    $rawBody = file_get_contents('php://input');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);

    if (!empty($_SERVER['CONTENT_TYPE'])) {
        $headers[] = 'Content-Type: ' . $_SERVER['CONTENT_TYPE'];
    }
}

curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST  => $method,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => true,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_FOLLOWLOCATION => false,
]);

$response   = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

$responseHeaders = substr($response, 0, $headerSize);
$responseBody    = substr($response, $headerSize);

http_response_code($statusCode);

$safeHeaders = ['Content-Type', 'Content-Disposition', 'Content-Length', 'Cache-Control'];
foreach (explode("\r\n", $responseHeaders) as $line)
{
    foreach ($safeHeaders as $safe)
    {
        if (stripos($line, $safe . ':') === 0)
        {
            header($line, true);
        }
    }
}

echo $responseBody;

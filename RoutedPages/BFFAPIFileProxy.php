<?php

use Auxilium\Enumerators\CookieKey;
use Auxilium\SessionHandling\CookieHandling;
use Auxilium\Utilities\ConfigurationUtilities;

require_once __DIR__ . '/../vendor/autoload.php';

$prefix = '/API/BFFAPIFileProxy';
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
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (str_contains($contentType, 'multipart/form-data'))
    {
        // rebuild multipart from php's parsed data
        $postFields = $_POST;

        foreach ($_FILES as $fieldName => $file) {
            $postFields[$fieldName] = new CURLFile(
                $file['tmp_name'],
                $file['type'],
                $file['name']
            );
        }

        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        // let curl set its own Content-Type with boundary
    }
    else
    {
        $rawBody = file_get_contents('php://input');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
        if (!empty($contentType)) {
            $headers[] = 'Content-Type: ' . $contentType;
        }
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

if (ConfigurationUtilities::GetUserConfiguration()['Development']['PHPAcceptSelfSignedCertificatesForAPI'] === true)
{
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
}

$response = curl_exec($ch);

if ($response === false)
{
    $error = curl_error($ch);
    curl_close($ch);
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Upstream request failed', 'detail' => $error]);
    return;
}


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

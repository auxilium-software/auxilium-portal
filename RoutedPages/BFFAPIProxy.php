<?php

use Auxilium\Enumerators\CookieKey;
use Auxilium\ServiceInteractions\APIInteractions;
use Auxilium\SessionHandling\CookieHandling;

require_once __DIR__ . '/../vendor/autoload.php';

const PROXY_ROUTES = [
    '/api/v3/authentication/logout'     => 'REQUIRED',
    '/api/v3/authentication'            => 'NONE',
    '/api/v3/system-bulletin'           => 'OPTIONAL',
    '/api/v3'                           => 'REQUIRED',
];

$prefix = '/API/BFFAPIProxy';
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path   = substr($uri, strlen($prefix));


// ==================================================
// ROUTE MATCHING

$authMode = null;

foreach (PROXY_ROUTES as $routePrefix => $mode)
{
    if (str_starts_with($path, $routePrefix))
    {
        $authMode = $mode;
        break;
    }
}

if ($authMode === null)
{
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Forbidden proxy path'], JSON_THROW_ON_ERROR);
    return;
}

$requireAuth = match ($authMode) {
    'NONE'    => false,
    'OPTIONAL' => CookieHandling::GetCookieValue(CookieKey::ACCESS_TOKEN) !== null,
    default   => true,
};

// ==================================================
// REQUEST BODY

$method      = $_SERVER['REQUEST_METHOD'];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$isJson      = str_contains($contentType, 'application/json');

$body = [];
if (!in_array($method, ['GET', 'HEAD', 'DELETE'], true) && $isJson)
{
    $raw = file_get_contents('php://input');
    if (!empty($raw))
    {
        $body = json_decode($raw, true, 512, JSON_THROW_ON_ERROR) ?? [];
    }
}

// ==================================================
// DISPATCH

function dispatchRequest(string $method, string $path, array $body, bool $requireAuth): mixed
{
    return match ($method) {
        'GET'    => APIInteractions::Get($path, $_GET, requireAuth: $requireAuth),
        'POST'   => APIInteractions::Post($path, $body, requireAuth: $requireAuth),
        'PUT'    => APIInteractions::Put($path, $body, requireAuth: $requireAuth),
        'PATCH'  => APIInteractions::Patch($path, $body, requireAuth: $requireAuth),
        'DELETE' => APIInteractions::Delete($path, requireAuth: $requireAuth),
        default  => null,
    };
}

$response = dispatchRequest($method, $path, $body, $requireAuth);

if ($response === null)
{
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed'], JSON_THROW_ON_ERROR);
    return;
}

// ==================================================
// TOKEN REFRESH ON 401
// Never attempt refresh on authentication endpoints to avoid loops

$isAuthPath = str_starts_with($path, '/api/v3/authentication');

if ($response->StatusCode === 401 && $requireAuth && !$isAuthPath)
{
    error_log("[BFF] 401 on {$method} {$path} - attempting refresh");

    $refreshToken = CookieHandling::GetCookieValue(CookieKey::REFRESH_TOKEN);

    if ($refreshToken === null)
    {
        error_log("[BFF] No refresh token in cookie - aborting");
    }
    if ($refreshToken !== null)
    {
        error_log("[BFF] Got refresh token, calling refresh endpoint");

        $refreshResponse = APIInteractions::Post(
                         '/api/v3/authentication/refresh',
                         ['refreshToken' => $refreshToken],
            requireAuth: false
        );

        error_log("[BFF] Refresh response status: {$refreshResponse->StatusCode}");
        error_log("[BFF] Refresh payload keys: " . implode(', ', array_keys((array)$refreshResponse->Payload)));

        if ($refreshResponse->StatusCode === 200 && isset($refreshResponse->Payload['accessToken']))
        {
            error_log("[BFF] Refresh succeeded, retrying original request");

            $newAccessToken = $refreshResponse->Payload['accessToken'];

            $_COOKIE['access_token'] = $newAccessToken;

            CookieHandling::SetCookie(
                targetCookie: CookieKey::ACCESS_TOKEN,
                value: $newAccessToken,
                httpOnly: true,
            );

            if (isset($refreshResponse->Payload['refreshToken']))
            {
                CookieHandling::SetCookie(
                    targetCookie: CookieKey::REFRESH_TOKEN,
                    value: $refreshResponse->Payload['refreshToken'],
                    httpOnly: true,
                );
            }

            // Retry the original request - APIInteractions will pick up the new cookie
            $response = dispatchRequest($method, $path, $body, $requireAuth);
            error_log("[BFF] Retry response status: {$response->StatusCode}");
        }
        else
        {
            error_log("[BFF] Refresh failed or missing accessToken in payload");
        }
        // If refresh failed, fall through and return the 401 to the client
    }
}

// ==================================================
// Strip tokens from authentication responses and store them as `httpOnly`

if ($isAuthPath && $response->StatusCode === 200)
{
    $payload = $response->Payload;

    if (isset($payload['accessToken']))
    {
        CookieHandling::SetCookie(
            targetCookie: CookieKey::ACCESS_TOKEN,
            value: $payload['accessToken'],
            httpOnly: true,
        );
        unset($payload['accessToken']);
    }

    if (isset($payload['refreshToken']))
    {
        CookieHandling::SetCookie(
            targetCookie: CookieKey::REFRESH_TOKEN,
            value: $payload['refreshToken'],
            httpOnly: true,
        );
        unset($payload['refreshToken']);
    }

    http_response_code($response->StatusCode);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_THROW_ON_ERROR);
    return;
}

// ==================================================
// DEFAULT RESPONSE PASSTHROUGH

http_response_code($response->StatusCode);
header('Content-Type: application/json');

if (isset($response->Headers['Set-Cookie']))
{
    $cookies = is_array($response->Headers['Set-Cookie'])
        ? $response->Headers['Set-Cookie']
        : [$response->Headers['Set-Cookie']];

    foreach ($cookies as $cookie)
    {
        header('Set-Cookie: ' . $cookie, false);
    }
}

echo is_string($response->Payload)
    ? $response->Payload
    : json_encode($response->Payload, JSON_THROW_ON_ERROR);

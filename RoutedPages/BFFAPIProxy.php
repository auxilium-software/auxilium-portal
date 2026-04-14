<?php

use Auxilium\Enumerators\CookieKey;
use Auxilium\ServiceInteractions\APIInteractions;
use Auxilium\SessionHandling\CookieHandling;

// ── Auth modes ──────────────────────────────────────────────────────
const AUTH_REQUIRED = 'required';
const AUTH_OPTIONAL = 'optional';
const AUTH_NONE     = 'none';

// ── Route whitelist ─────────────────────────────────────────────────
// First match wins — put more specific prefixes above broader ones.
const PROXY_ROUTES = [
    '/api/v3/authentication/logout'     => AUTH_REQUIRED,
    '/api/v3/authentication'            => AUTH_NONE,
    '/api/v3/cases'                     => AUTH_REQUIRED,
    '/api/v3/me'                        => AUTH_REQUIRED,
    '/api/v3/users'                     => AUTH_REQUIRED,
    '/api/v3/system-bulletin'           => AUTH_OPTIONAL,
    '/api/v3/organisations'             => AUTH_REQUIRED,
    '/api/v3/todos'                     => AUTH_REQUIRED,
];

// ── Extract proxy path ──────────────────────────────────────────────
$prefix = '/API/BFFAPIProxy';
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path   = substr($uri, strlen($prefix));

// ── Whitelist + auth mode lookup ────────────────────────────────────
$authMode = null;

foreach (PROXY_ROUTES as $routePrefix => $mode) {
    if (str_starts_with($path, $routePrefix)) {
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
    AUTH_REQUIRED => true,
    AUTH_NONE     => false,
    AUTH_OPTIONAL => CookieHandling::GetCookieValue(CookieKey::ACCESS_TOKEN) !== null,
};

// ── Parse request body ──────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];

$body = [];
if (!in_array($method, ['GET', 'HEAD', 'DELETE'], true)) {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $body = json_decode($raw, true, 512, JSON_THROW_ON_ERROR) ?? [];
    }
}

// ── Dispatch to API ─────────────────────────────────────────────────
$response = match ($method) {
    'GET'    => APIInteractions::Get($path, $_GET, requireAuth: $requireAuth),
    'POST'   => APIInteractions::Post($path, $body, requireAuth: $requireAuth),
    'PUT'    => APIInteractions::Put($path, $body, requireAuth: $requireAuth),
    'PATCH'  => APIInteractions::Patch($path, $body, requireAuth: $requireAuth),
    'DELETE' => APIInteractions::Delete($path, requireAuth: $requireAuth),
    default  => null,
};

if ($response === null) {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed'], JSON_THROW_ON_ERROR);
    return;
}

// ── Relay response ──────────────────────────────────────────────────
http_response_code($response->StatusCode);
header('Content-Type: application/json');

if (isset($response->Headers['Set-Cookie'])) {
    $cookies = is_array($response->Headers['Set-Cookie'])
        ? $response->Headers['Set-Cookie']
        : [$response->Headers['Set-Cookie']];

    foreach ($cookies as $cookie) {
        header('Set-Cookie: ' . $cookie, false);
    }
}

echo is_string($response->Payload)
    ? $response->Payload
    : json_encode($response->Payload, JSON_THROW_ON_ERROR);

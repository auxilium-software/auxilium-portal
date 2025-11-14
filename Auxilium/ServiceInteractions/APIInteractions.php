<?php

namespace Auxilium\ServiceInteractions;

use Auxilium\DataClasses\APIResponsePayload;
use Auxilium\Enumerators\CookieKey;
use Auxilium\SessionHandling\CookieHandling;
use Auxilium\TwigHandling\PageBuilder;
use Auxilium\Utilities\ConfigurationUtilities;
use Auxilium\Utilities\NavigationUtilities;
use CurlHandle;
use Exception;
use JetBrains\PhpStorm\NoReturn;

class APIInteractions
{
    private static bool $hasAttemptedRefresh = false;

    private CurlHandle $CurlHandler;
    private string $lastEndpoint = '';
    private array $lastPayload = [];
    private string $lastMethod = 'GET';
    private bool $requiresAuth;

    public static function GetBaseURL(): string
    {
        return ConfigurationUtilities::GetUserConfiguration()['API']['URL'] . '/api/v3';
    }

    private function __construct(bool $requiresAuth = true)
    {
        $this->requiresAuth = $requiresAuth;
        $this->CurlHandler = curl_init();

        if ($this->CurlHandler === false) {
            throw new Exception('Failed to initialize cURL');
        }

        curl_setopt($this->CurlHandler, CURLOPT_HEADER, 0);
        curl_setopt($this->CurlHandler, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->CurlHandler, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($this->CurlHandler, CURLOPT_TIMEOUT, 30);

        $this->setHeaders();
    }

    private function setHeaders(): void
    {
        $headers = ['Content-Type: application/json'];

        if ($this->requiresAuth) {
            $accessToken = CookieHandling::GetCookieValue(CookieKey::ACCESS_TOKEN);

            if (!$accessToken) {
                $this->redirectToLogin();
            }

            $headers[] = 'Authorization: Bearer ' . $accessToken;
        }

        $cookieHeader = $this->buildCookieHeader();
        if ($cookieHeader) {
            $headers[] = $cookieHeader;
        }

        curl_setopt($this->CurlHandler, CURLOPT_HTTPHEADER, $headers);
    }

    private function buildCookieHeader(): string
    {
        $cookies = [];

        if (isset($_COOKIE[CookieKey::ACCESS_TOKEN->value])) {
            $cookies[] = CookieKey::ACCESS_TOKEN->value . '=' . $_COOKIE[CookieKey::ACCESS_TOKEN->value];
        }

        if (isset($_COOKIE[CookieKey::REFRESH_TOKEN->value])) {
            $cookies[] = CookieKey::REFRESH_TOKEN->value . '=' . $_COOKIE[CookieKey::REFRESH_TOKEN->value];
        }

        return !empty($cookies) ? 'Cookie: ' . implode('; ', $cookies) : '';
    }

    #[NoReturn]
    private function redirectToLogin(): void
    {
        CookieHandling::DeleteCookie(CookieKey::ACCESS_TOKEN);
        CookieHandling::DeleteCookie(CookieKey::REFRESH_TOKEN);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        NavigationUtilities::Redirect(target: '/login');
    }

    private function setTarget(string $endpoint): void
    {
        $this->lastEndpoint = $endpoint;
        curl_setopt($this->CurlHandler, CURLOPT_URL, self::GetBaseURL() . $endpoint);
    }

    private function setMethod(string $method): void
    {
        $this->lastMethod = $method;

        switch ($method) {
            case 'POST':
                curl_setopt($this->CurlHandler, CURLOPT_POST, 1);
                break;
            case 'GET':
                // GET is default, no action needed
                break;
            default:
                curl_setopt($this->CurlHandler, CURLOPT_CUSTOMREQUEST, $method);
                break;
        }
    }

    private function setPayload(array $payload): void
    {
        $this->lastPayload = $payload;

        if (!empty($payload)) {
            curl_setopt($this->CurlHandler, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
        }
    }

    private function executeRequest(): APIResponsePayload
    {
        $response = curl_exec($this->CurlHandler);

        if ($response === false) {
            $error = curl_error($this->CurlHandler);
            curl_close($this->CurlHandler);
            throw new Exception('cURL request failed: ' . $error);
        }

        $statusCode = curl_getinfo($this->CurlHandler, CURLINFO_HTTP_CODE);
        curl_close($this->CurlHandler);

        // Handle 401 Unauthorized - attempt token refresh
        if ($statusCode === 401 && $this->requiresAuth && !self::$hasAttemptedRefresh) {
            if (self::attemptTokenRefresh()) {
                // Reset the flag and retry with new token
                self::$hasAttemptedRefresh = false;
                return $this->retryRequest();
            }

            $this->redirectToLogin();
        }

        if ($statusCode === 401) {
            $this->redirectToLogin();
        }

        if ($statusCode >= 400) {
            error_log("API Error: Status $statusCode, Response: $response");
        }

        $responsePayload = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            PageBuilder::Render(
                template: '/ErrorPages/APIError.html.twig',
                variables: [
                    'ErrorMessage' => json_last_error_msg(),
                ],
                useAuth: false,
            );
            exit;
        }

        return new APIResponsePayload(
            StatusCode: $statusCode,
            Payload: $responsePayload ?? []
        );
    }

    private function retryRequest(): APIResponsePayload
    {
        $retry = new self($this->requiresAuth);
        $retry->setTarget($this->lastEndpoint);
        $retry->setMethod($this->lastMethod);
        $retry->setPayload($this->lastPayload);

        return $retry->executeRequest();
    }

    private static function attemptTokenRefresh(): bool
    {
        self::$hasAttemptedRefresh = true;

        try {
            $refreshToken = CookieHandling::GetCookieValue(CookieKey::REFRESH_TOKEN);

            if (!$refreshToken) {
                return false;
            }

            $response = self::Post(
                endpoint: '/authentication/refresh',
                payload: ['refresh_token' => $refreshToken],
                requireAuth: false
            );

            if ($response->StatusCode === 200 && isset($response->Payload['access_token'])) {
                CookieHandling::SetCookie(
                    CookieKey::ACCESS_TOKEN,
                    $response->Payload['access_token']
                );

                if (isset($response->Payload['refresh_token'])) {
                    CookieHandling::SetCookie(
                        CookieKey::REFRESH_TOKEN,
                        $response->Payload['refresh_token']
                    );
                }

                return true;
            }

            return false;
        } catch (Exception $e) {
            error_log('Token refresh failed: ' . $e->getMessage());
            return false;
        }
    }

    // Static factory methods

    public static function Post(string $endpoint, array $payload, bool $requireAuth = true): APIResponsePayload
    {
        $apiWrapper = new self($requireAuth);
        $apiWrapper->setTarget($endpoint);
        $apiWrapper->setMethod('POST');
        $apiWrapper->setPayload($payload);
        return $apiWrapper->executeRequest();
    }

    public static function Patch(string $endpoint, array $payload, bool $requireAuth = true): APIResponsePayload
    {
        $apiWrapper = new self($requireAuth);
        $apiWrapper->setTarget($endpoint);
        $apiWrapper->setMethod('PATCH');
        $apiWrapper->setPayload($payload);
        return $apiWrapper->executeRequest();
    }

    public static function Put(string $endpoint, array $payload, bool $requireAuth = true): APIResponsePayload
    {
        $apiWrapper = new self($requireAuth);
        $apiWrapper->setTarget($endpoint);
        $apiWrapper->setMethod('PUT');
        $apiWrapper->setPayload($payload);
        return $apiWrapper->executeRequest();
    }

    public static function Delete(string $endpoint, bool $requireAuth = true): APIResponsePayload
    {
        $apiWrapper = new self($requireAuth);
        $apiWrapper->setTarget($endpoint);
        $apiWrapper->setMethod('DELETE');
        return $apiWrapper->executeRequest();
    }

    public static function Get(string $endpoint, array $parameters = [], bool $requireAuth = true): APIResponsePayload
    {
        $queryString = '';

        if (!empty($parameters)) {
            $queryString = '?' . http_build_query($parameters);
        }

        $apiWrapper = new self($requireAuth);
        $apiWrapper->setTarget($endpoint . $queryString);
        $apiWrapper->setMethod('GET');
        return $apiWrapper->executeRequest();
    }
}

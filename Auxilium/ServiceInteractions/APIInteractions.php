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
        return ConfigurationUtilities::GetUserConfiguration()['API']['AvailableAt'] . '/api/v3';
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

        if(ConfigurationUtilities::GetUserConfiguration()["Development"]["PHPAcceptSelfSignedCertificatesForAPI"] === true)
        {
            curl_setopt($this->CurlHandler, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($this->CurlHandler, CURLOPT_SSL_VERIFYHOST, false);
        }

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

        // Add cookie header to send cookies with the request
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

    private function executeRequest(bool $decodeAsJSON = true): APIResponsePayload
    {
        // Enable header capture
        curl_setopt($this->CurlHandler, CURLOPT_HEADER, true);

        $response = null;

        try
        {
            $response = curl_exec($this->CurlHandler);
        }
        catch (Exception $ex)
        {
            PageBuilder::RenderInternalSystemError($ex);
        }

        if ($response === false) {
            $error = curl_error($this->CurlHandler);
            curl_close($this->CurlHandler);

            PageBuilder::RenderInternalSystemError(new Exception($error));

        }

        $statusCode = curl_getinfo($this->CurlHandler, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($this->CurlHandler, CURLINFO_HEADER_SIZE);

        // Split headers and body
        $headerString = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        // Parse headers into array
        $headers = [];
        foreach (explode("\r\n", $headerString) as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Store multiple values for same header (rare but possible)
                if (isset($headers[$key])) {
                    if (!is_array($headers[$key])) {
                        $headers[$key] = [$headers[$key]];
                    }
                    $headers[$key][] = $value;
                } else {
                    $headers[$key] = $value;
                }
            }
        }

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

        // If we already tried refreshing, or it's a different error
        if ($statusCode === 401) {
            $this->redirectToLogin();
        }

        // Handle other HTTP errors
        if ($statusCode >= 400) {
            error_log("API Error: Status $statusCode, Response: $body");
        }

        if($decodeAsJSON)
        {
            $responsePayload = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                PageBuilder::Render(
                    template: '/VirtualPages/ApiErrorPage.html.twig',
                    variables: [
                        'ErrorMessage' => json_last_error_msg(),
                    ],
                    useAuth: false,
                );
            }
        }
        else
        {
            $responsePayload = $body;
        }

        return new APIResponsePayload(
            statusCode: $statusCode,
            headers: $headers,
            payload: $responsePayload ?? ""
        );
    }

    private function retryRequest(): APIResponsePayload
    {
        // Create a new instance with fresh auth headers
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
                payload: ['refreshToken' => $refreshToken],
                requireAuth: false
            );

            if ($response->StatusCode === 200 && isset($response->Payload['accessToken'])) {
                CookieHandling::SetCookie(
                    CookieKey::ACCESS_TOKEN,
                    $response->Payload['accessToken']
                );

                if (isset($response->Payload['refreshToken'])) {
                    CookieHandling::SetCookie(
                        CookieKey::REFRESH_TOKEN,
                        $response->Payload['refreshToken']
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

    public static function GetRaw(string $endpoint, array $parameters = [], bool $requireAuth = true): APIResponsePayload
    {
        $queryString = '';

        if (!empty($parameters)) {
            $queryString = '?' . http_build_query($parameters);
        }

        $apiWrapper = new self($requireAuth);
        $apiWrapper->setTarget($endpoint . $queryString);
        $apiWrapper->setMethod('GET');
        return $apiWrapper->executeRequest(
            decodeAsJSON: false,
        );
    }
}

<?php

namespace Auxilium\ServiceInteractions;

use App\Wrappers\APIWrapper;
use App\Wrappers\CookieWrapper;
use Auxilium\DataClasses\APIResponsePayload;
use Auxilium\Enumerators\CookieKey;
use Auxilium\SessionHandling\CookieHandling;
use Auxilium\Utilities\NavigationUtilities;
use CurlHandle;
use Exception;
use JetBrains\PhpStorm\NoReturn;

class APIInteractions
{
    private static bool $hasAttemptedRefresh = false;
    public CurlHandle $CurlHandler;
    private string $lastEndpoint = '';
    private array $lastPayload = [];
    private string $lastMethod = 'GET';

    function __construct(bool $requiresAuth = true)
    {
        $this->CurlHandler = curl_init();

        curl_setopt($this->CurlHandler, CURLOPT_HEADER, 0);
        curl_setopt($this->CurlHandler, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->CurlHandler, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($this->CurlHandler, CURLOPT_TIMEOUT, 30);

        /*
        if (Configuration::GetConfig("Development", "DevelopmentMode"))
        {
            curl_setopt($this->CurlHandler, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($this->CurlHandler, CURLOPT_SSL_VERIFYHOST, 0);
        }
        */

        $headers = ["Content-Type: application/json"];

        if($requiresAuth)
        {
            $accessToken = CookieHandling::GetCookieValue(CookieKey::ACCESS_TOKEN);
            if($accessToken)
            {
                $headers[] = "Authorization: Bearer " . $accessToken;
            }
            if(!$accessToken)
            {
                $this->redirectToLogin();
            }
        }

        curl_setopt($this->CurlHandler, CURLOPT_HTTPHEADER, $headers);

        if($this->CurlHandler === false)
        {
            throw new Exception("Failed to initialize cURL");
        }
    }

    #[NoReturn] private function redirectToLogin(): void
    {
        CookieHandling::DeleteCookie(CookieKey::ACCESS_TOKEN);
        CookieHandling::DeleteCookie(CookieKey::REFRESH_TOKEN);

        if(session_status() === PHP_SESSION_ACTIVE)
        {
            unset($_SESSION);
        }

        NavigationUtilities::Redirect(target: '/login');
    }
    private function setTarget(string $endpoint): void
    {
        $this->lastEndpoint = $endpoint;
        curl_setopt($this->CurlHandler, CURLOPT_URL, self::GetBaseURL() . $endpoint);
    }

    public static function GetBaseURL(): string
    {
        return "http://localhost:1983/api/v3";
    }

    private function SetMethod(string $method): void
    {
        $this->lastMethod = $method;
        if($method !== 'GET' && $method !== 'POST')
        {
            curl_setopt($this->CurlHandler, CURLOPT_CUSTOMREQUEST, $method);
        }
    }

    private function SetPayload(array $payload): void
    {
        $this->lastPayload = $payload;
        if(!empty($payload))
        {
            curl_setopt($this->CurlHandler, CURLOPT_POST, 1);
            curl_setopt($this->CurlHandler, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
        }
    }

    public function executeRequest(): APIResponsePayload
    {
        $response = curl_exec($this->CurlHandler);

        if($response === false)
        {
            $error = curl_error($this->CurlHandler);
            curl_close($this->CurlHandler);
            throw new Exception("cURL request failed: " . $error);
        }

        error_log(json_encode($response));

        $statusCode = curl_getinfo($this->CurlHandler, CURLINFO_HTTP_CODE);
        curl_close($this->CurlHandler);

        if($statusCode >= 400)
        {
            if($statusCode === 401 && !self::$hasAttemptedRefresh)
            {
                // Token expired or invalid - try to refresh
                if(self::attemptTokenRefresh())
                {
                    $this->executeRequest();
                }
                $this->redirectToLogin();
            }
            elseif($statusCode === 401)
            {
                $this->redirectToLogin();
            }
            else
            {
                // Other errors - show error page
                $this->showErrorPage($statusCode, $response);
            }
        }

        $responsePayload = json_decode($response, true);

        if(json_last_error() !== JSON_ERROR_NONE)
        {
            throw new Exception("Invalid JSON response from API");
        }

        $temp = new APIResponsePayload();
        $temp->StatusCode = $statusCode;
        $temp->Payload = $responsePayload;
        return $temp;
    }

    private static function attemptTokenRefresh(): bool
    {
        self::$hasAttemptedRefresh = true; // Prevent infinite loops

        try
        {
            $refreshToken = CookieHandling::GetCookieValue(CookieKey::REFRESH_TOKEN);
            if(!$refreshToken)
            {
                return false;
            }

            $refreshWrapper = new APIInteractions(false); // No auth required for refresh
            $refreshWrapper->SetTarget('/authentication/refresh');
            $refreshWrapper->SetPayload(['refresh_token' => $refreshToken]);
            $response = $refreshWrapper->executeRequest();

            if(isset($response['access_token']))
            {
                CookieHandling::SetCookie(CookieKey::ACCESS_TOKEN, $response['access_token']);

                if(isset($response['refresh_token']))
                {
                    CookieHandling::SetCookie(CookieKey::REFRESH_TOKEN, $response['refresh_token']);
                }

                return true;
            }

            return false;
        }
        catch(Exception $e)
        {
            error_log("Token refresh failed: " . $e->getMessage());
            return false;
        }
    }








    public static function Post(string $endpoint, array $payload, bool $requireAuth = true): APIResponsePayload
    {
        $apiWrapper = new APIInteractions($requireAuth);
        $apiWrapper->SetTarget($endpoint);
        $apiWrapper->SetMethod('POST');
        $apiWrapper->SetPayload($payload);
        return $apiWrapper->executeRequest();
    }

    public static function Patch(string $endpoint, array $payload): APIResponsePayload
    {
        $apiWrapper = new APIInteractions(true);
        $apiWrapper->SetTarget($endpoint);
        $apiWrapper->SetMethod('PATCH');
        $apiWrapper->SetPayload($payload);
        return $apiWrapper->executeRequest();
    }

    public static function Put(string $endpoint, array $payload): APIResponsePayload
    {
        $apiWrapper = new APIInteractions(true);
        $apiWrapper->SetTarget($endpoint);
        $apiWrapper->SetMethod('PUT');
        $apiWrapper->SetPayload($payload);
        return $apiWrapper->executeRequest();
    }

    public static function Delete(string $endpoint): APIResponsePayload
    {
        $apiWrapper = new APIInteractions(true);
        $apiWrapper->SetTarget($endpoint);
        $apiWrapper->SetMethod('DELETE');
        return $apiWrapper->executeRequest();
    }

    public static function Get(string $endpoint, array $parameters = []): APIResponsePayload
    {
        $queryString = "";
        if(!empty($parameters))
        {
            $queryString = "?" . http_build_query($parameters);
        }

        $apiWrapper = new APIInteractions(true);
        $apiWrapper->SetTarget($endpoint . $queryString);
        $apiWrapper->SetMethod('GET');
        return $apiWrapper->executeRequest();
    }
}

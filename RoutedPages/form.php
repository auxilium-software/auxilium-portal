<?php

use Auxilium\Auxilium\AuxiliumScript;
use Auxilium\ServiceInteractions\APIInteractions;
use Auxilium\TwigHandling\PageBuilder;
use Auxilium\Utilities\CacheUtilities;
use Auxilium\Utilities\ConfigurationUtilities;
use Auxilium\Utilities\JWTUtilities;
use Auxilium\Utilities\NavigationUtilities;
use Auxilium\Utilities\SecurityUtilities;

require_once __DIR__ . '/../vendor/autoload.php';

$formInstanceID = explode(separator: '/', string: $_SERVER['REQUEST_URI'])[2];

if(!CacheUtilities::DoesFormExistYet(formInstanceID: $formInstanceID))
{
    NavigationUtilities::Redirect(
        target: '/'
    );
}

$formData = CacheUtilities::GetFormData($formInstanceID);
$formSpec = ConfigurationUtilities::GetFormDefinition(target: $formData['FormSpecID']);

// make sure only authorised people access the form:


function processPayload($payload, array $vars): mixed
{
    if (is_string($payload)) {
        if (str_contains($payload, '$')) {
            return AuxiliumScript::evaluate_expression($payload, $vars);
        }
        return $payload;
    }

    if (is_array($payload)) {
        $result = [];
        foreach ($payload as $key => $value) {
            $processedKey = is_string($key) && str_contains($key, '$')
                ? AuxiliumScript::evaluate_expression($key, $vars)
                : $key;

            $result[$processedKey] = processPayload($value, $vars);
        }
        return $result;
    }

    return $payload;
}

function parsePayloadFromXML($payloadNode): mixed
{
    if (!$payloadNode) {
        return [];
    }

    if (is_string($payloadNode)) {
        $decoded = json_decode($payloadNode, true, 512, JSON_THROW_ON_ERROR);
        return $decoded ?? $payloadNode;
    }

    if (is_object($payloadNode)) {
        $result = [];
        foreach ($payloadNode as $key => $value) {
            if ($value instanceof SimpleXMLElement) {
                if (count($value->children()) > 0) {
                    $result[$key] = parsePayloadFromXML($value);
                } else {
                    $result[$key] = (string)$value;
                }
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    return $payloadNode;
}

function executeSubmissionActions($formSpec, $formData, $formInstanceID): array
{
    if (!isset($formSpec['submissionActions']))
    {
        return ['success' => true, 'message' => 'No submission actions defined'];
    }

    $results = [];
    $vars = [
        'formData' => $formData['Data'] ?? [],
    ];


    $apiRequests = $formSpec['submissionActions']['apiRequest'];

    if (!isset($apiRequests[0]))
    {
        $apiRequests = [$apiRequests];
    }

    foreach ($apiRequests as $apiRequest)
    {
        $executeIf = $apiRequest['executeIf'] ?? 'true';

        if(!AuxiliumScript::evaluate_expression($executeIf, $vars))
        {
            continue;
        }

        $payloadDefinition = $apiRequest['payload'] ?? [];
        $payloadToSend = processPayload(parsePayloadFromXML($payloadDefinition), $vars);

        if($formSpec['useReCAPTCHA'])
        {
            $payloadToSend['recaptcha_token'] = $_POST['ReCAPTCHAToken'];
        }

        $result = APIInteractions::Post(
            endpoint: $apiRequest['endpoint'],
            payload: $payloadToSend,
            requireAuth: $formSpec['requireAuthentication'],
        );
        $results[] = $result;

        if ($result->StatusCode >= 400)
        {
            return [
                'success' => false,
                'message' => $result->Payload['message'] ?? 'An error occurred while executing the submission action',
                'results' => $results,
            ];
        }
    }

    return [
        'success' => true,
        'message' => 'All submission actions completed successfully',
        'results' => $results
    ];
}

function findNextVisiblePage($formSpec, $formData, $currentPage, $direction = 1)
{
    $pages = $formSpec['pages']['page'];
    $totalPages = count($pages);
    $nextPage = $currentPage + $direction;

    while($nextPage >= 0 && $nextPage < $totalPages)
    {
        $page = $pages[$nextPage];
        $renderIf = $page['renderIf'] ?? 'true';

        $vars = [
            'formData' => $formData['Data'] ?? [],
        ];

        if(AuxiliumScript::evaluate_expression($renderIf, $vars))
        {
            return $nextPage;
        }
        $nextPage += $direction;
    }

    return -1;
}

function getVisibleReviewComponents($formSpec, $formData): array
{
    if(!isset($formSpec['reviewPage']['components']['component']))
    {
        return [];
    }

    $components = $formSpec['reviewPage']['components']['component'];
    $vars = [
        'formData' => $formData['Data'] ?? [],
    ];
    $visibleComponents = [];

    foreach($components as $component)
    {
        $condition = $component['if'] ?? 'true';
        if(AuxiliumScript::evaluate_expression($condition, $vars))
        {
            $processedComponent = $component;

            if(isset($component['value']) && str_contains($component['value'], '$'))
            {
                $processedComponent['value'] = AuxiliumScript::evaluate_expression($component['value'], $vars);
            }

            if($component['type'] === 'DESCRIPTION_LIST' && isset($component['dictionary']['item']))
            {
                $items = $component['dictionary']['item'];
                $processedComponent['dictionary']['item'] = [];

                if(is_array($items))
                {
                    if(isset($items['key'], $items['value']))
                    {
                        $key = $items['key'];
                        $value = $items['value'];

                        if(str_contains($value, '$'))
                        {
                            $value = str_replace(['$formData["', '"]'], ['$formData[\'', '\']'], $value);
                            $value = AuxiliumScript::evaluate_expression($value, $vars);
                        }

                        $processedComponent['dictionary']['item'][] = [
                            'key' => $key,
                            'value' => $value
                        ];
                    }
                    else if(isset($items[0]))
                    {
                        if(is_array($items[0]))
                        {
                            foreach($items as $item)
                            {
                                if(isset($item['key'], $item['value']))
                                {
                                    $key = $item['key'];
                                    $value = $item['value'];

                                    if(str_contains($value, '$'))
                                    {
                                        $value = str_replace(['$formData["', '"]'], ['$formData[\'', '\']'], $value);
                                        $value = AuxiliumScript::evaluate_expression($value, $vars);
                                    }

                                    $processedComponent['dictionary']['item'][] = [
                                        'key' => $key,
                                        'value' => $value
                                    ];
                                }
                            }
                        }
                        else if(is_string($items[0]))
                        {
                            for($i = 0, $iMax = count($items); $i < $iMax; $i += 2)
                            {
                                if(isset($items[$i + 1]))
                                {
                                    $key = $items[$i];
                                    $value = $items[$i + 1];

                                    if(str_contains($value, '$'))
                                    {
                                        $value = str_replace(['$formData["', '"]'], ['$formData[\'', '\']'], $value);
                                        $value = AuxiliumScript::evaluate_expression($value, $vars);
                                    }

                                    $processedComponent['dictionary']['item'][] = [
                                        'key' => $key,
                                        'value' => $value
                                    ];
                                }
                            }
                        }
                    }
                }
            }

            $visibleComponents[] = $processedComponent;
        }
    }

    return $visibleComponents;
}

$storedPageIndex = $formData['CurrentPageIndex'] ?? 0;
$isReviewPage = ($storedPageIndex === 'review');

$visiblePages = [];
$pageIndexMap = [];
for($i = 0, $iMax = count($formSpec['pages']['page']); $i < $iMax; $i++)
{
    $page = $formSpec['pages']['page'][$i];
    $renderIf = $page['renderIf'] ?? 'true';

    $vars = [
        'formData' => $formData['Data'] ?? [],
    ];

    if(AuxiliumScript::evaluate_expression($renderIf, $vars))
    {
        $visiblePages[] = $page;
        $pageIndexMap[] = $i;
    }
}

$currentVisiblePageIndex = 0;
if(!$isReviewPage)
{
    $currentVisiblePageIndex = array_search($storedPageIndex, $pageIndexMap);
    if($currentVisiblePageIndex === false)
    {
        $currentVisiblePageIndex = 0;
        if(!empty($pageIndexMap))
        {
            $storedPageIndex = $pageIndexMap[0];
        }
    }
}

$totalVisiblePages = count($visiblePages);

if($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $action = $_POST['action'] ?? 'continue';

    if(isset($_POST['jumpToPage']))
    {
        $targetPageId = $_POST['jumpToPage'];

        for($i = 0, $iMax = count($formSpec['pages']['page']); $i < $iMax; $i++)
        {
            if($formSpec['pages']['page'][$i]['id'] === $targetPageId)
            {
                CacheUtilities::SetCurrentPageIndex($formInstanceID, $i);
                NavigationUtilities::Redirect(target: "/form/$formInstanceID");
            }
        }
    }

    if($action !== 'back')
    {
        CacheUtilities::UpdateFormData($formInstanceID, $_POST);
        $formData = CacheUtilities::GetFormData($formInstanceID);
    }

    switch($action)
    {
        case 'back':
            if($isReviewPage)
            {
                if(!empty($pageIndexMap))
                {
                    $lastPageIndex = end($pageIndexMap);
                    CacheUtilities::SetCurrentPageIndex($formInstanceID, $lastPageIndex);
                }
            }
            else
            {
                $prevPageIndex = findNextVisiblePage($formSpec, $formData, $storedPageIndex, -1);
                if($prevPageIndex >= 0)
                {
                    CacheUtilities::SetCurrentPageIndex($formInstanceID, $prevPageIndex);
                }
            }
            break;

        case 'continue':
        case 'next':
            if ($isReviewPage)
            {
                $submissionResult = executeSubmissionActions($formSpec, $formData, $formInstanceID);

                if ($submissionResult['success'])
                {
                    CacheUtilities::MarkFormAsComplete($formInstanceID);
                    NavigationUtilities::Redirect(target: $formSpec['afterSubmissionRedirect']['target']);
                }
                else
                {
                    $_SESSION['submission_error'] = $submissionResult['message'];
                    NavigationUtilities::Redirect(target: "/form/$formInstanceID");
                }
            }
            else
            {
                $nextPageIndex = findNextVisiblePage($formSpec, $formData, $storedPageIndex, 1);
                if($nextPageIndex >= 0)
                {
                    CacheUtilities::SetCurrentPageIndex($formInstanceID, $nextPageIndex);
                }
                else
                {
                    CacheUtilities::SetCurrentPageIndex($formInstanceID, 'review');
                }
            }
            break;

        case 'review':
            CacheUtilities::SetCurrentPageIndex($formInstanceID, 'review');
            break;

        case 'submit':
            $submissionResult = executeSubmissionActions($formSpec, $formData, $formInstanceID);

            if ($submissionResult['success'])
            {
                CacheUtilities::MarkFormAsComplete($formInstanceID);
                NavigationUtilities::Redirect(target: $formSpec['afterSubmissionRedirect']['target']);
            }
            else
            {
                $_SESSION['submission_error'] = $submissionResult['message'];
                NavigationUtilities::Redirect(target: "/form/$formInstanceID");
            }
            break;
    }

    NavigationUtilities::Redirect(target: "/form/$formInstanceID");
}

if($totalVisiblePages === 0 && !$isReviewPage)
{
    NavigationUtilities::Redirect(target: '/');
}

if ($isReviewPage)
{
    $shouldUserBeLoggedIn = isset($formSpec['requireAuthentication']) && $formSpec['requireAuthentication'] === 'true';

    $reviewComponents = getVisibleReviewComponents($formSpec, $formData);
    $variables = [
        "FormInstanceID" => $formInstanceID,
        "FormSpec" => $formSpec,
        "FormData" => $formData,
        "IsReviewPage" => true,
        "ReviewComponents" => $reviewComponents,
        "ShowBackButton" => true,
        "ShowSubmitButton" => true,
        "SubmissionError" => $_SESSION['submission_error'] ?? null,
    ];

    unset($_SESSION['submission_error']);

    PageBuilder::Render(
        template: '/VirtualPages/FormReviewPage.html.twig',
        variables: $variables,
        useAuth: $shouldUserBeLoggedIn,
    );
}
else
{
    $currentPage = $visiblePages[$currentVisiblePageIndex];
    $actualPageIndex = $pageIndexMap[$currentVisiblePageIndex];

    $shouldUserBeLoggedIn = isset($formSpec['requireAuthentication']) && $formSpec['requireAuthentication'] === 'true';

    $isAbandonPage = isset($currentPage['abandonForm']) && $currentPage['abandonForm'] === 'true';
    $hasPreviousPage = findNextVisiblePage($formSpec, $formData, $actualPageIndex, -1) >= 0;
    $hasNextPage = findNextVisiblePage($formSpec, $formData, $actualPageIndex, 1) >= 0;
    $isLastPage = $currentVisiblePageIndex === $totalVisiblePages - 1;

    $variables = [
        "FormInstanceID" => $formInstanceID,
        "FormSpec" => $formSpec,
        "FormData" => $formData,
        "CurrentPage" => $currentPage,
        "CurrentPageIndex" => $currentVisiblePageIndex,
        "ActualPageIndex" => $actualPageIndex,
        "TotalPages" => $totalVisiblePages,
        "IsFirstPage" => ($currentVisiblePageIndex === 0),
        "IsLastPage" => $isLastPage,
        "IsReviewPage" => false,
        "IsAbandonPage" => $isAbandonPage,
        "ShowBackButton" => $hasPreviousPage && !$isAbandonPage,
        "ShowNextButton" => !$isAbandonPage,
        "ShowReviewButton" => false,
    ];

    PageBuilder::Render(
        template : '/VirtualPages/FormPage.html.twig',
        variables: $variables,
        useAuth: $shouldUserBeLoggedIn,
    );
}

<?php

use Auxilium\Auxilium\AuxiliumScript;
use Auxilium\TwigHandling\PageBuilder;
use Auxilium\Utilities\CacheUtilities;
use Auxilium\Utilities\ConfigurationUtilities;
use Auxilium\Utilities\NavigationUtilities;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Configuration/Configuration/Environment.php';

$formInstanceID = explode(separator: '/', string: $_SERVER['REQUEST_URI'])[2];

if(!CacheUtilities::DoesFormExistYet(formInstanceID: $formInstanceID))
{
    NavigationUtilities::Redirect(
        target: '/'
    );
}

$formData = CacheUtilities::GetFormData($formInstanceID);
$formSpec = ConfigurationUtilities::GetFormDefinition(target: $formData['FormSpecID']);

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


function getVisibleReviewComponents($formSpec, $formData)
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
                break;
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
            if($isReviewPage)
            {
                CacheUtilities::MarkFormAsComplete($formInstanceID);
                NavigationUtilities::Redirect(target: '/form-complete/' . $formInstanceID);
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
            CacheUtilities::MarkFormAsComplete($formInstanceID);
            NavigationUtilities::Redirect(target: '/form-complete/' . $formInstanceID);
            break;
    }

    NavigationUtilities::Redirect(target: "/form/$formInstanceID");
}

if($totalVisiblePages === 0 && !$isReviewPage)
{
    NavigationUtilities::Redirect(target: '/');
}

if($isReviewPage)
{
    $reviewComponents = getVisibleReviewComponents($formSpec, $formData);
    $variables = [
        "FormInstanceID" => $formInstanceID,
        "FormSpec" => $formSpec,
        "FormData" => $formData,
        "IsReviewPage" => true,
        "ReviewComponents" => $reviewComponents,
        "ShowBackButton" => true,
        "ShowSubmitButton" => true,
    ];

    PageBuilder::Render(
        template : '/VirtualPages/FormReviewPage.html.twig',
        variables: $variables,
    );
}
else
{

    $currentPage = $visiblePages[$currentVisiblePageIndex];
    $actualPageIndex = $pageIndexMap[$currentVisiblePageIndex];

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
        "ShowBackButton" => $hasPreviousPage,
        "ShowNextButton" => $hasNextPage,
        "ShowReviewButton" => $isLastPage,
    ];

    PageBuilder::Render(
        template : '/VirtualPages/FormPage.html.twig',
        variables: $variables,
    );
}

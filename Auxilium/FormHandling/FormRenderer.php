<?php

namespace Auxilium\FormHandling;

use Auxilium\Enumerators\SessionKey;
use Auxilium\TwigHandling\PageBuilder;
use Auxilium\Utilities\SessionUtilities;
use JetBrains\PhpStorm\NoReturn;

/**
 * Handles rendering of form pages and review pages
 */
class FormRenderer
{
    private ReviewPageBuilder $reviewPageBuilder;
    private PageNavigator $navigator;

    /**
     * @param ReviewPageBuilder $reviewPageBuilder Builder for review page components
     * @param PageNavigator $navigator Navigator for page logic
     */
    public function __construct(
        ReviewPageBuilder $reviewPageBuilder,
        PageNavigator     $navigator
    )
    {
        $this->reviewPageBuilder = $reviewPageBuilder;
        $this->navigator = $navigator;
    }

    /**
     * Renders the review page showing submitted data
     *
     * @param string $formInstanceID The form instance ID
     * @param array $formSpec The form specification
     * @param array $formData The current form data
     * @param array $vars Variables for expression evaluation
     */
    #[NoReturn]
    public function renderReviewPage(
        string $formInstanceID,
        array  $formSpec,
        array  $formData,
        array  $vars
    ): void
    {
        $shouldUserBeLoggedIn = $formSpec['requireAuthentication'] === 'true';
        $reviewComponents = $this->reviewPageBuilder->getVisibleComponents($formSpec, $vars);

        $variables = [
            "FormInstanceID" => $formInstanceID,
            "FormSpec" => $formSpec,
            "FormData" => $formData,
            "IsReviewPage" => true,
            "ReviewComponents" => $reviewComponents,
            "ShowBackButton" => true,
            "ShowSubmitButton" => true,
            "SubmissionError" => SessionUtilities::Get(key: SessionKey::FORM_SUBMISSION_ERROR, default: null),
        ];

        SessionUtilities::Delete(key: SessionKey::FORM_SUBMISSION_ERROR);

        PageBuilder::Render(
            template : '/VirtualPages/FormReviewPage.html.twig',
            variables: $variables,
            useAuth  : $shouldUserBeLoggedIn,
        );
    }

    /**
     * Renders a regular form page
     *
     * @param string $formInstanceID The form instance ID
     * @param array $formSpec The form specification
     * @param array $formData The current form data
     * @param array $visiblePages Array of visible pages
     * @param array $pageIndexMap Mapping of visible to actual page indices
     * @param int $storedPageIndex The currently stored page index
     */
    #[NoReturn]
    public function renderFormPage(
        string $formInstanceID,
        array  $formSpec,
        array  $formData,
        array  $visiblePages,
        array  $pageIndexMap,
        int    $storedPageIndex
    ): void
    {
        $currentVisiblePageIndex = $this->navigator->getCurrentVisiblePageIndex(
            $storedPageIndex,
            $pageIndexMap
        );

        $currentPage = $visiblePages[$currentVisiblePageIndex];
        $actualPageIndex = $pageIndexMap[$currentVisiblePageIndex];

        $shouldUserBeLoggedIn = $formSpec['requireAuthentication'] === 'true';
        $isAbandonPage = ($currentPage['abandonForm'] ?? 'false') === 'true';

        $vars = ['formData' => $formData['Data'] ?? []];
        $hasPreviousPage = $this->navigator->findNextVisiblePage(
                $formSpec['pages']['page'],
                $actualPageIndex,
                -1,
                $vars
            ) >= 0;

        $isLastPage = $currentVisiblePageIndex === count($visiblePages) - 1;

        $variables = [
            "FormInstanceID" => $formInstanceID,
            "FormSpec" => $formSpec,
            "FormData" => $formData,
            "CurrentPage" => $currentPage,
            "CurrentPageIndex" => $currentVisiblePageIndex,
            "ActualPageIndex" => $actualPageIndex,
            "TotalPages" => count($visiblePages),
            "IsFirstPage" => $currentVisiblePageIndex === 0,
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
            useAuth  : $shouldUserBeLoggedIn,
        );
    }
}

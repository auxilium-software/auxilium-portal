<?php

namespace Auxilium\FormHandling;

use Auxilium\Auxilium\AuxiliumScript;
use Auxilium\Enumerators\SessionKey;
use Auxilium\Utilities\CacheUtilities;
use Auxilium\Utilities\ConfigurationUtilities;
use Auxilium\Utilities\JWTUtilities;
use Auxilium\Utilities\NavigationUtilities;
use Auxilium\Utilities\SecurityUtilities;
use Auxilium\Utilities\SessionUtilities;
use JetBrains\PhpStorm\NoReturn;
use JsonException;

/**
 * Main class for form handling
 */
class FormHandler
{
    private string $formInstanceID;
    private array $formData;
    private array $formSpec;
    private int|string $storedPageIndex;
    private bool $isReviewPage;
    private array $visiblePages;
    private array $pageIndexMap;


    private FormRenderer $renderer;
    private FormSubmissionHandler $submissionHandler;
    private PageNavigator $navigator;
    private PayloadProcessor $payloadProcessor;
    private ReviewPageBuilder $reviewPageBuilder;


    /**
     * Initialize the form handler with a form instance ID
     *
     * @param string $formInstanceID The UUID for this form instance
     * @throws JsonException Thrown if there's issues getting the form data.
     */
    public function __construct(string $formInstanceID)
    {
        $this->formInstanceID = $formInstanceID;

        // verify the form actually exists, if it doesn't, send the user to `/`
        if(!CacheUtilities::DoesFormExistYet(formInstanceID: $formInstanceID))
        {
            NavigationUtilities::Redirect(target: '/');
        }

        // load the data already submitted to the form
        $this->formData = CacheUtilities::GetFormData($formInstanceID);

        // load the form spec using form data
        $this->formSpec = ConfigurationUtilities::GetFormDefinition(target: $this->formData['FormSpecID']);

        // make sure the user requesting the form has permissions to actually interact with the form
        $this->validateAuthentication();

        // initialize dependencies
        $this->initializeDependencies();

        // initialize the page state
        $this->storedPageIndex = $this->formData['CurrentPageIndex'] ?? 0;
        $this->isReviewPage = ($this->storedPageIndex === 'review');

        // build the list of visible pages
        $this->buildVisiblePagesList();
    }

    /**
     * Validates that the current user has permission to access this form
     */
    private function validateAuthentication(): void
    {
        if(!$this->formSpec['requireAuthentication'])
        {
            return;
        }

        if($this->formSpec['requireAuthentication'] === "false")
        {
            return;
        }

        SecurityUtilities::RequireLogin();
        $targetUserID = $this->formData['UserID'];

        // if form is assigned to a specific user, verify that the current user is that user
        if($targetUserID !== "*")
        {
            $currentUserID = JWTUtilities::GetJwtInfo()->ID;
            if($targetUserID !== $currentUserID)
            {
                NavigationUtilities::Redirect(target: '/');
            }
        }
    }

    /**
     * Initializes all dependency objects
     */
    private function initializeDependencies(): void
    {
        $this->navigator = new PageNavigator();
        $this->payloadProcessor = new PayloadProcessor();
        $this->reviewPageBuilder = new ReviewPageBuilder();

        $this->submissionHandler = new FormSubmissionHandler(
            $this->payloadProcessor,
            $this->formInstanceID,
            $this->formSpec
        );

        $this->renderer = new FormRenderer(
            $this->reviewPageBuilder,
            $this->navigator
        );
    }

    /**
     * Builds a list of visible pages based on renderIf conditions
     */
    private function buildVisiblePagesList(): void
    {
        $vars = $this->getExpressionVariables();
        $result = $this->navigator->buildVisiblePagesList(
            $this->formSpec['pages']['page'],
            $vars
        );

        $this->visiblePages = $result['visiblePages'];
        $this->pageIndexMap = $result['pageIndexMap'];
    }

    /**
     * Gets the standard variables array used for AuxiliumScript expression evaluation
     *
     * @return array Variables containing form data
     */
    private function getExpressionVariables(): array
    {
        return ['formData' => $this->formData['Data'] ?? []];
    }

    /**
     * Processes a POST request (form submission or navigation)
     *
     * @throws JsonException thrown if there's an issue encoding the form data as JSON.
     */
    #[NoReturn]
    public function handlePostRequest(): void
    {
        $action = $_POST['action'] ?? 'continue';

        // Handle direct page jumps
        if(isset($_POST['jumpToPage']))
        {
            $this->handlePageJump($_POST['jumpToPage']);
        }

        // update form data for all actions except "back"
        if($action !== 'back')
        {
            $this->hashPasswordFields();
            CacheUtilities::UpdateFormData($this->formInstanceID, $_POST);
            $this->formData = CacheUtilities::GetFormData($this->formInstanceID);
        }

        // handle the main navigation
        match ($action)
        {
            'back' => $this->handleBackAction(),
            'continue', 'next' => $this->handleNextAction(),
            'review' => $this->handleReviewAction(),
            'submit' => $this->handleSubmitAction(),
            default => null, // unknown action, do nothing
        };

        // reload the page
        NavigationUtilities::Redirect(target: "/form/$this->formInstanceID");
    }

    /**
     * Handles jumping directly to a specific page by ID
     */
    #[NoReturn]
    private function handlePageJump(string $targetPageId): void
    {
        $pageIndex = $this->navigator->findPageIndexById(
            $targetPageId,
            $this->formSpec['pages']['page']
        );

        if($pageIndex !== null)
        {
            CacheUtilities::SetCurrentPageIndex($this->formInstanceID, $pageIndex);
        }

        NavigationUtilities::Redirect(target: "/form/$this->formInstanceID");
    }

    /**
     * Handles the "back" navigation action
     */
    private function handleBackAction(): void
    {
        if($this->isReviewPage)
        {
            // from review page, go to last visible form page
            if(!empty($this->pageIndexMap))
            {
                $lastPageIndex = end($this->pageIndexMap);
                CacheUtilities::SetCurrentPageIndex($this->formInstanceID, $lastPageIndex);
            }
            return;
        }

        // find previous visible page
        $vars = $this->getExpressionVariables();
        $prevPageIndex = $this->navigator->findNextVisiblePage(
            $this->formSpec['pages']['page'],
            $this->storedPageIndex,
            -1,
            $vars
        );

        if($prevPageIndex >= 0)
        {
            CacheUtilities::SetCurrentPageIndex($this->formInstanceID, $prevPageIndex);
        }
    }

    /**
     * Handles the "next" or "continue" action
     */
    private function handleNextAction(): void
    {
        // as the current page is the review page, the next action MUST be a submission
        if($this->isReviewPage)
        {
            $this->submitForm();
        }

        // for any other page, navigate to the next visible page/review page
        $vars = $this->getExpressionVariables();
        $nextPageIndex = $this->navigator->findNextVisiblePage(
            $this->formSpec['pages']['page'],
            $this->storedPageIndex,
            1,
            $vars
        );

        if($nextPageIndex >= 0)
        {
            CacheUtilities::SetCurrentPageIndex($this->formInstanceID, $nextPageIndex);
        }
        else
        {
            // There are no more pages, therefore, go to review
            CacheUtilities::SetCurrentPageIndex($this->formInstanceID, 'review');
        }
    }

    /**
     * Executes submission actions and redirects appropriately
     *
     * @throws JsonException
     */
    #[NoReturn]
    private function submitForm(): void
    {
        $submissionResult = $this->submissionHandler->execute($this->formData);

        if($submissionResult->Success)
        {
            CacheUtilities::MarkFormAsComplete($this->formInstanceID);


            //TODO fix this bit
            $redirectionTarget = AuxiliumScript::evaluate_variable_path(
                string: $this->formSpec['afterSubmissionRedirect']['target'],
                vars: [
                    'formData'=>[
                        "case_id"=>"test"
                    ],
                ]
            );

            NavigationUtilities::Redirect(target: $redirectionTarget);
        }

        SessionUtilities::Set(key: SessionKey::FORM_SUBMISSION_ERROR, value: $submissionResult->Message);
        NavigationUtilities::Redirect(target: "/form/$this->formInstanceID");
    }

    /**
     * Handles jumping directly to the review page
     */
    private function handleReviewAction(): void
    {
        CacheUtilities::SetCurrentPageIndex($this->formInstanceID, 'review');
    }

    /**
     * Handles form submission
     *
     * @throws JsonException
     */
    #[NoReturn]
    private function handleSubmitAction(): void
    {
        $this->submitForm();
    }

    /**
     * Renders the current page (either a form page or review page)
     */
    #[NoReturn]
    public function render(): void
    {
        // if there are no visible pages, and we're not on the review page, redirect to `/`
        if(empty($this->visiblePages) && !$this->isReviewPage)
        {
            NavigationUtilities::Redirect(target: '/');
        }

        if($this->isReviewPage)
        {
            $this->renderReviewPage();
        }
        else
        {
            $this->renderFormPage();
        }
    }

    /**
     * Renders the review page showing submitted data
     */
    #[NoReturn]
    private function renderReviewPage(): void
    {
        $vars = $this->getExpressionVariables();

        $this->renderer->renderReviewPage(
            $this->formInstanceID,
            $this->formSpec,
            $this->formData,
            $vars
        );
    }

    /**
     * Renders a regular form page
     */
    #[NoReturn]
    private function renderFormPage(): void
    {
        $this->renderer->renderFormPage(
            $this->formInstanceID,
            $this->formSpec,
            $this->formData,
            $this->visiblePages,
            $this->pageIndexMap,
            $this->storedPageIndex
        );
    }








    private function hashPasswordFields(): void
    {
        $pages = $this->formSpec['pages']['page'];
        $currentPage = $pages[$this->storedPageIndex] ?? null;

        if(!$currentPage || !isset($currentPage['components']['component']))
        {
            return;
        }

        foreach($currentPage['components']['component'] as $component)
        {
            if(($component['type'] ?? '') === 'PASSWORD_FIELD')
            {
                $fieldId = $component['id'];
                if(isset($_POST[$fieldId]) && $_POST[$fieldId] !== '')
                {
                    $_POST[$fieldId] = hash('sha512', $_POST[$fieldId]);
                }
            }
        }
    }
}

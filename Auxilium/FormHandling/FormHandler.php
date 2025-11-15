<?php

namespace Auxilium\FormHandling;

use Auxilium\Auxilium\AuxiliumScript;
use Auxilium\Enumerators\SessionKey;
use Auxilium\ServiceInteractions\APIInteractions;
use Auxilium\TwigHandling\PageBuilder;
use Auxilium\Utilities\CacheUtilities;
use Auxilium\Utilities\ConfigurationUtilities;
use Auxilium\Utilities\JWTUtilities;
use Auxilium\Utilities\NavigationUtilities;
use Auxilium\Utilities\SecurityUtilities;
use Auxilium\Utilities\SessionUtilities;
use JetBrains\PhpStorm\NoReturn;
use JsonException;
use SimpleXMLElement;

/**
 * Handles form rendering, validation, navigation, and submission
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

    /**
     * Initialize the form handler with a form instance ID
     *
     * @param string $formInstanceID The unique identifier for this form instance
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

        // initialise the page state
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
     * Builds a list of visible pages based on renderIf conditions
     */
    private function buildVisiblePagesList(): void
    {
        $this->visiblePages = [];
        $this->pageIndexMap = [];

        $vars = $this->getExpressionVariables();

        foreach($this->formSpec['pages']['page'] as $i => $page)
        {
            $renderIf = $page['renderIf'] ?? 'true'; // if there's no renderIf, assume that the page SHOULD be seen

            if(AuxiliumScript::evaluate_expression($renderIf, $vars))
            {
                $this->visiblePages[] = $page;
                $this->pageIndexMap[] = $i;
            }
        }
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

        // handle direct page jumps
        if(isset($_POST['jumpToPage']))
        {
            $this->handlePageJump($_POST['jumpToPage']);
        }

        // update form data for all actions except "back"
        if($action !== 'back')
        {
            CacheUtilities::UpdateFormData($this->formInstanceID, $_POST);
            $this->formData = CacheUtilities::GetFormData($this->formInstanceID);
        }

        // this bit handles the main navigation essentially
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
        foreach($this->formSpec['pages']['page'] as $i => $page)
        {
            if($page['id'] === $targetPageId)
            {
                CacheUtilities::SetCurrentPageIndex($this->formInstanceID, $i);
                NavigationUtilities::Redirect(target: "/form/$this->formInstanceID");
            }
        }

        // If we reach here, page ID wasn't found - just reload current page
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
        $prevPageIndex = $this->findNextVisiblePage($this->storedPageIndex, -1);
        if($prevPageIndex >= 0)
        {
            CacheUtilities::SetCurrentPageIndex($this->formInstanceID, $prevPageIndex);
        }
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
     * Finds the next visible page based on renderIf conditions
     *
     * @param int $currentPage The current page index
     * @param int $direction 1 for forward, -1 for backward
     * @return int The index of the next visible page, or -1 if none found
     */
    private function findNextVisiblePage(int $currentPage, int $direction): int
    {
        $pages = $this->formSpec['pages']['page'];
        $totalPages = count($pages);
        $nextPage = $currentPage + $direction;

        $vars = $this->getExpressionVariables();

        while($nextPage >= 0 && $nextPage < $totalPages)
        {
            $page = $pages[$nextPage];
            $renderIf = $page['renderIf'] ?? 'true';

            if(AuxiliumScript::evaluate_expression($renderIf, $vars))
            {
                return $nextPage;
            }
            $nextPage += $direction;
        }

        return -1;
    }

    /**
     * Handles the "next" or "continue" action
     */
    private function handleNextAction(): void
    {
        // as the current page is the review page, the next action must be a submission
        if($this->isReviewPage)
        {
            $this->submitForm();
            return;
        }

        // for any other page, navigate to the next visible page/review page
        $nextPageIndex = $this->findNextVisiblePage($this->storedPageIndex, 1);
        if($nextPageIndex >= 0)
        {
            CacheUtilities::SetCurrentPageIndex($this->formInstanceID, $nextPageIndex);
        }
        else
        {
            // there are no more pages, therefore, go to review
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
        $submissionResult = $this->executeSubmissionActions();

        if($submissionResult->Success)
        {
            CacheUtilities::MarkFormAsComplete($this->formInstanceID);
            NavigationUtilities::Redirect(target: $this->formSpec['afterSubmissionRedirect']['target']);
        }

        SessionUtilities::Set(key: SessionKey::FORM_SUBMISSION_ERROR, value: $submissionResult->Message);
        NavigationUtilities::Redirect(target: "/form/$this->formInstanceID");
    }

    /**
     * Executes all submission actions defined in the form specification
     *
     * @return array Result array with 'success' status, 'message', and 'results'
     * @throws JsonException
     */
    private function executeSubmissionActions(): FormSubmissionActionResultWrapper
    {
        if(!isset($this->formSpec['submissionActions']))
        {
            return new FormSubmissionActionResultWrapper(
                success: true,
                message: "No submission actions are defined.",
            );
        }

        $results = [];
        $vars = $this->getExpressionVariables();

        // because of the way the xml parser works, if there's only one element in the list, it won't be an array,
        // so here we're just converting it to always be an array
        $apiRequests = $this->normalizeToArray($this->formSpec['submissionActions']['apiRequest']);

        // execute each api request
        foreach($apiRequests as $apiRequest)
        {
            // if there's no executeIf, assume that it should be executed
            $executeIf = $apiRequest['executeIf'] ?? 'true';

            if(!AuxiliumScript::evaluate_expression($executeIf, $vars))
            {
                continue;
            }

            $payloadDefinition = $apiRequest['payload'] ?? [];
            $payloadToSend = $this->processPayload(
                $this->parsePayloadFromXML($payloadDefinition),
                $vars
            );

            // add reCAPTCHA token if the form spec says it should be used
            if($this->formSpec['useReCAPTCHA'])
            {
                $payloadToSend['recaptcha_token'] = $_POST['ReCAPTCHAToken'];
            }

            $result = APIInteractions::Post(
                endpoint   : $apiRequest['endpoint'],
                payload    : $payloadToSend,
                requireAuth: $this->formSpec['requireAuthentication'],
            );
            $results[] = $result;

            // cancel on the first error (don't execute any more actions)
            if($result->StatusCode >= 400)
            {
                return new FormSubmissionActionResultWrapper(
                    success: false,
                    message: $result->Payload['message'] ?? 'An error occurred while executing the submission action',
                    results: $results
                );
            }
        }

        return new FormSubmissionActionResultWrapper(
            success: true,
            message: 'All submission actions completed successfully',
            results: $results
        );
    }

    /**
     * Normalizes XML parser output to always return an array
     * (XML parser returns single items as non-array values)
     *
     * @param mixed $value The value to normalize
     * @return array The normalized array
     */
    private function normalizeToArray(mixed $value): array
    {
        // If it's already an indexed array, return as-is
        if(is_array($value) && isset($value[0]))
        {
            return $value;
        }

        // Otherwise wrap in an array
        return [$value];
    }

    /**
     * Recursively processes a payload, evaluating AuxiliumScript expressions
     *
     * @param mixed $payload The payload to process
     * @param array $vars Variables available for expression evaluation
     * @return mixed The processed payload
     */
    private function processPayload(mixed $payload, array $vars): mixed
    {
        if(is_string($payload))
        {
            if(str_contains($payload, '$'))
            {
                return AuxiliumScript::evaluate_expression($payload, $vars);
            }
            return $payload;
        }

        if(is_array($payload))
        {
            $result = [];
            foreach($payload as $key => $value)
            {
                $processedKey = is_string($key) && str_contains($key, '$')
                    ? AuxiliumScript::evaluate_expression($key, $vars)
                    : $key;

                $result[$processedKey] = $this->processPayload($value, $vars);
            }
            return $result;
        }

        return $payload;
    }

    /**
     * Parses payload data from XML format into a PHP array
     *
     * @param mixed $payloadNode The XML node to parse
     * @return mixed The parsed payload
     * @throws JsonException thrown if there's a problem loading the payload node's JSON string into an assoc array
     */
    private function parsePayloadFromXML(mixed $payloadNode): mixed
    {
        if(!$payloadNode)
        {
            return [];
        }

        if(is_string($payloadNode))
        {
            $decoded = json_decode($payloadNode, true, 512, JSON_THROW_ON_ERROR);
            return $decoded ?? $payloadNode;
        }

        if(is_object($payloadNode))
        {
            $result = [];
            foreach($payloadNode as $key => $value)
            {
                if($value instanceof SimpleXMLElement)
                {
                    $result[$key] = count($value->children()) > 0
                        ? $this->parsePayloadFromXML($value)
                        : (string)$value;
                }
                else
                {
                    $result[$key] = $value;
                }
            }
            return $result;
        }

        return $payloadNode;
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
        // if there's no visible pages, and we're not on the review page, redirect to `/`
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
        $shouldUserBeLoggedIn = $this->formSpec['requireAuthentication'] === 'true';
        $reviewComponents = $this->getVisibleReviewComponents();

        $variables = [
            "FormInstanceID" => $this->formInstanceID,
            "FormSpec" => $this->formSpec,
            "FormData" => $this->formData,
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
     * Retrieves and processes components for the review page
     *
     * @return array Array of visible components with processed values
     */
    private function getVisibleReviewComponents(): array
    {
        if(!isset($this->formSpec['reviewPage']['components']['component']))
        {
            return [];
        }

        $components = $this->formSpec['reviewPage']['components']['component'];
        $vars = $this->getExpressionVariables();
        $visibleComponents = [];

        foreach($components as $component)
        {
            $condition = $component['if'] ?? 'true';

            if(!AuxiliumScript::evaluate_expression($condition, $vars))
            {
                continue;
            }

            $processedComponent = $component;

            // evaluate dynamic values
            if(isset($component['value']) && str_contains($component['value'], '$'))
            {
                $processedComponent['value'] = AuxiliumScript::evaluate_expression($component['value'], $vars);
            }

            // handle description lists
            if($component['type'] === 'DESCRIPTION_LIST' && isset($component['dictionary']['item']))
            {
                $processedComponent['dictionary']['item'] = $this->processDescriptionListItems(
                    $component['dictionary']['item'],
                    $vars
                );
            }

            $visibleComponents[] = $processedComponent;
        }

        return $visibleComponents;
    }

    /**
     * Processes items for a description list component
     *
     * @param mixed $items The items to process
     * @param array $vars Variables for expression evaluation
     * @return array Processed items
     */
    private function processDescriptionListItems(mixed $items, array $vars): array
    {
        if(!is_array($items))
        {
            return [];
        }

        // single key-value pair
        if(isset($items['key'], $items['value']))
        {
            return [$this->processDescriptionListItem($items, $vars)];
        }

        // array of items
        if(!isset($items[0]))
        {
            return [];
        }

        // array of objects
        if(is_array($items[0]))
        {
            return array_filter(
                array_map(
                    fn($item) => isset($item['key'], $item['value'])
                        ? $this->processDescriptionListItem($item, $vars)
                        : null,
                    $items
                )
            );
        }

        // flat array (alternating keys and values)
        if(is_string($items[0]))
        {
            return $this->processFlatArrayItems($items, $vars);
        }

        return [];
    }

    /**
     * Processes a single description list item
     *
     * @param array $item The item with 'key' and 'value'
     * @param array $vars Variables for expression evaluation
     * @return array Processed item
     */
    private function processDescriptionListItem(array $item, array $vars): array
    {
        $key = $item['key'];
        $value = $item['value'];

        if(str_contains($value, '$'))
        {
            $value = str_replace(['$formData["', '"]'], ['$formData[\'', '\']'], $value);
            $value = AuxiliumScript::evaluate_expression($value, $vars);
        }

        return ['key' => $key, 'value' => $value];
    }

    /**
     * Processes flat array items (alternating keys and values)
     *
     * @param array $items Flat array of alternating keys and values
     * @param array $vars Variables for expression evaluation
     * @return array Processed items
     */
    private function processFlatArrayItems(array $items, array $vars): array
    {
        $processedItems = [];
        for($i = 0, $iMax = count($items); $i < $iMax; $i += 2)
        {
            if(isset($items[$i + 1]))
            {
                $processedItems[] = $this->processDescriptionListItem([
                    'key' => $items[$i],
                    'value' => $items[$i + 1]
                ], $vars
                );
            }
        }
        return $processedItems;
    }

    /**
     * Renders a regular form page
     */
    #[NoReturn]
    private function renderFormPage(): void
    {
        $currentVisiblePageIndex = $this->getCurrentVisiblePageIndex();
        $currentPage = $this->visiblePages[$currentVisiblePageIndex];
        $actualPageIndex = $this->pageIndexMap[$currentVisiblePageIndex];

        $shouldUserBeLoggedIn = $this->formSpec['requireAuthentication'] === 'true';
        $isAbandonPage = ($currentPage['abandonForm'] ?? 'false') === 'true';
        $hasPreviousPage = $this->findNextVisiblePage($actualPageIndex, -1) >= 0;
        $isLastPage = $currentVisiblePageIndex === count($this->visiblePages) - 1;

        $variables = [
            "FormInstanceID" => $this->formInstanceID,
            "FormSpec" => $this->formSpec,
            "FormData" => $this->formData,
            "CurrentPage" => $currentPage,
            "CurrentPageIndex" => $currentVisiblePageIndex,
            "ActualPageIndex" => $actualPageIndex,
            "TotalPages" => count($this->visiblePages),
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

    /**
     * Gets the current visible page index
     *
     * @return int The current visible page index
     */
    private function getCurrentVisiblePageIndex(): int
    {
        $currentVisiblePageIndex = array_search(
            needle  : $this->storedPageIndex,
            haystack: $this->pageIndexMap,
            strict  : true
        );

        if($currentVisiblePageIndex === false)
        {
            $currentVisiblePageIndex = 0;
            if(!empty($this->pageIndexMap))
            {
                $this->storedPageIndex = $this->pageIndexMap[0];
            }
        }

        return $currentVisiblePageIndex;
    }
}
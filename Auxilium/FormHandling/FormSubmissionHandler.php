<?php

namespace Auxilium\FormHandling;

use Auxilium\Auxilium\AuxiliumScript;
use Auxilium\ServiceInteractions\APIInteractions;
use JsonException;

/**
 * Handles form submission actions and API request execution
 */
class FormSubmissionHandler
{
    private PayloadProcessor $payloadProcessor;
    private string $formInstanceID;
    private array $formSpec;

    /**
     * @param PayloadProcessor $payloadProcessor Processor for handling payloads
     * @param string $formInstanceID The unique form instance identifier
     * @param array $formSpec The form specification
     */
    public function __construct(
        PayloadProcessor $payloadProcessor,
        string           $formInstanceID,
        array            $formSpec
    )
    {
        $this->payloadProcessor = $payloadProcessor;
        $this->formInstanceID = $formInstanceID;
        $this->formSpec = $formSpec;
    }

    /**
     * Executes all submission actions defined in the form specification
     *
     * @param array $formData The current form data
     * @return FormSubmissionActionResultWrapper Result wrapper with success status and message
     * @throws JsonException
     */
    public function execute(array $formData): FormSubmissionActionResultWrapper
    {
        if(!isset($this->formSpec['submissionActions']))
        {
            return new FormSubmissionActionResultWrapper(
                success: true,
                message: "No submission actions are defined.",
            );
        }

        $results = [];
        $vars = ['formData' => $formData['Data'] ?? []];

        // normalize API requests to always be an array
        $apiRequests = $this->payloadProcessor->normalizeToArray(
            $this->formSpec['submissionActions']['apiRequest']
        );

        // execute each API request
        foreach($apiRequests as $apiRequest)
        {
            $executeIf = $apiRequest['executeIf'] ?? 'true';

            if(!AuxiliumScript::evaluate_expression($executeIf, $vars))
            {
                continue;
            }

            $result = $this->executeApiRequest($apiRequest, $vars);
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
     * Executes a single API request
     *
     * @param array $apiRequest The API request configuration
     * @param array $vars Variables for expression evaluation
     * @return object API response object
     * @throws JsonException
     */
    private function executeApiRequest(array $apiRequest, array $vars): object
    {
        $payloadDefinition = $apiRequest['payload'] ?? [];
        $payloadToSend = $this->payloadProcessor->process(
            $this->payloadProcessor->parseFromXML($payloadDefinition),
            $vars
        );

        // add reCAPTCHA token if the form spec says so
        if($this->formSpec['useReCAPTCHA'])
        {
            $payloadToSend['recaptchaToken'] = $_POST['ReCAPTCHAToken'];
        }

        return APIInteractions::Post(
            endpoint   : $apiRequest['endpoint'],
            payload    : $payloadToSend,
            requireAuth: $this->formSpec['requireAuthentication'] === "true",
        );
    }
}

<?php

namespace Auxilium\FormHandling;

use Auxilium\Auxilium\AuxiliumScript;
use JsonException;
use SimpleXMLElement;

/**
 * Handles parsing and processing of form submission payloads
 */
final class PayloadProcessor
{
    /**
     * Parses payload data from XML format into a PHP array
     *
     * @param mixed $payloadNode The XML node to parse
     * @return mixed The parsed payload
     * @throws JsonException thrown if there's a problem loading the payload node's JSON string into an assoc array
     */
    public function parseFromXML(mixed $payloadNode): mixed
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
                        ? $this->parseFromXML($value)
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
     * Recursively processes a payload, evaluating AuxiliumScript expressions
     *
     * @param mixed $payload The payload to process
     * @param array $vars Variables available for expression evaluation
     * @return mixed The processed payload
     */
    public function process(mixed $payload, array $vars): mixed
    {
        if(is_string($payload))
        {
            if(str_contains($payload, '$'))
            {
                // special handling for collectCheckboxValues function calls
                if(str_contains($payload, 'collectCheckboxValues'))
                {
                    return $this->handleCheckboxFunction($payload, $vars);
                }

                $result = AuxiliumScript::evaluate_expression($payload, $vars);

                // if the result is null/empty, and it looks like a checkbox field, let's try to collect it
                if(($result === null || $result === '') && $this->looksLikeCheckboxField($payload, $vars['formData']))
                {
                    // extract the field name from the expression like $formData["field_name"]
                    if(preg_match('/\$formData\[(["\'])([^"\']+)\1\]/', $payload, $matches))
                    {
                        return FormDataHelpers::collectCheckboxValues($matches[2], $vars['formData']);
                    }
                }

                return $result;
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

                $result[$processedKey] = $this->process($value, $vars);
            }
            return $result;
        }

        return $payload;
    }

    /**
     * Handles function calls like collectCheckboxValues("field_name")
     *
     * @param string $expression The expression containing the function call
     * @param array $vars Variables for expression evaluation
     * @return array The collected checkbox values
     */
    private function handleCheckboxFunction(string $expression, array $vars): array
    {
        // match -> `collectCheckboxValues("field_name")` // `collectCheckboxValues('field_name')`
        if(preg_match('/collectCheckboxValues\(["\']([^"\']+)["\']\)/', $expression, $matches))
        {
            $fieldName = $matches[1];
            return FormDataHelpers::collectCheckboxValues($fieldName, $vars['formData']);
        }

        // match -> `collectCheckboxValues($formData["field_name"])`
        if(preg_match('/collectCheckboxValues\(\$formData\[(["\'])([^"\']+)\1\]\)/', $expression, $matches))
        {
            $fieldName = $matches[2];
            return FormDataHelpers::collectCheckboxValues($fieldName, $vars['formData']);
        }

        return [];
    }

    /**
     * Normalizes XML parser output to always return an array
     * (XML parser returns single items as non-array values)
     *
     * @param mixed $value The value to normalize
     * @return array The normalized array
     */
    public function normalizeToArray(mixed $value): array
    {
        // if it's already an indexed array, return without modification
        if(is_array($value) && isset($value[0]))
        {
            return $value;
        }

        // otherwise, turn it into an array
        return [$value];
    }

    /**
     * Heuristic to detect if a field name refers to a checkbox field
     *
     * @param string $expression The expression to check
     * @param array $formData The form data
     * @return bool True if this looks like a checkbox field
     */
    private function looksLikeCheckboxField(string $expression, array $formData): bool
    {
        // extract the field name from the `$formData["field_name"]` pattern
        if(preg_match('/\$formData\[(["\'])([^"\']+)\1\]/', $expression, $matches))
        {
            $fieldName = $matches[2];
            $prefix = $fieldName . '-';
            $matchCount = 0;

            foreach(array_keys($formData) as $key)
            {
                if(str_starts_with($key, $prefix))
                {
                    $matchCount++;
                    if($matchCount >= 2) return true;
                }
            }
        }

        return false;
    }
}

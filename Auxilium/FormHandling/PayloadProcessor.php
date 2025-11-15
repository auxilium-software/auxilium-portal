<?php

namespace Auxilium\FormHandling;

use Auxilium\Auxilium\AuxiliumScript;
use JsonException;
use SimpleXMLElement;

/**
 * Handles parsing and processing of form submission payloads
 */
class PayloadProcessor
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

                $result[$processedKey] = $this->process($value, $vars);
            }
            return $result;
        }

        return $payload;
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
        // If it's already an indexed array, return as-is
        if(is_array($value) && isset($value[0]))
        {
            return $value;
        }

        // Otherwise wrap in an array
        return [$value];
    }
}

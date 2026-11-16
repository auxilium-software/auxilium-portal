<?php

namespace Auxilium\FormHandling;

use RuntimeException;

/**
 * Helper functions for form data processing
 * These can be called from AuxiliumScript expressions
 */
final class FormDataHelpers
{
    /**
     * Collects all selected checkbox values for a given field
     *
     * @param string $fieldName The base field name
     * @param array $formData The form data
     * @return array Array of selected checkbox values
     */
    public static function collectCheckboxValues(string $fieldName, array $formData): array
    {
        $selected = [];
        $prefix = $fieldName . '-';

        foreach($formData as $key => $value)
        {
            if(empty($value) || !str_starts_with($key, $prefix))
            {
                continue;
            }

            // handle the optional "other" option with text
            if($key === $prefix . 'other')
            {
                $otherTextKey = $prefix . 'other-text';
                if(!empty($formData[$otherTextKey]))
                {
                    $selected[] = $formData[$otherTextKey];
                }
                else
                {
                    $selected[] = 'Other';
                }
            }
            // skip the optional "other-text" field and boolean TRUE values
            elseif($key !== $prefix . 'other-text' && $value !== 'TRUE' && $value !== true)
            {
                $selected[] = $value;
            }
        }

        return $selected;
    }

    /**
     * Checks if a checkbox field has any selected values
     *
     * @param string $fieldName The base field name
     * @param array $formData The form data
     * @return bool True if any checkboxes are selected
     */
    public static function hasCheckboxValues(string $fieldName, array $formData): bool
    {
        return !empty(self::collectCheckboxValues($fieldName, $formData));
    }

    /**
     * Counts selected checkbox values
     *
     * @param string $fieldName The base field name
     * @param array $formData The form data
     * @return int Number of selected checkboxes
     */
    public static function countCheckboxValues(string $fieldName, array $formData): int
    {
        return count(self::collectCheckboxValues($fieldName, $formData));
    }

    public static function collectGridValues(
        string $fieldName,
        array  $formData,
        ?array $validValues = null,
        ?array &$invalidStatements = null
    ): array
    {
        $values = [];
        $invalidStatements = [];
        $prefix = $fieldName . '-';

        foreach($formData as $key => $value)
        {
            if($value === '' || $value === null || !str_starts_with($key, $prefix))
            {
                continue;
            }

            $statementId = substr($key, strlen($prefix));

            if(is_array($value))
            {
                $invalidStatements[$statementId] = $value;
                continue;
            }

            $normalisedValue = is_numeric($value) ? (int)$value : $value;

            if($validValues !== null && !in_array($normalisedValue, $validValues, true))
            {
                $invalidStatements[$statementId] = $value;
                continue;
            }

            $values[$statementId] = $normalisedValue;
        }

        return $values;
    }

    public static function collectGridValuesOrFail(string $fieldName, array $formData, array $validValues): array
    {
        $invalidStatements = null;
        $values = self::collectGridValues($fieldName, $formData, $validValues, $invalidStatements);

        if(!empty($invalidStatements))
        {
            $details = [];
            foreach($invalidStatements as $statementId => $rawValue)
            {
                $details[] = $statementId . '=' . (is_scalar($rawValue) ? (string)$rawValue : gettype($rawValue));
            }

            throw new RuntimeException(
                "Invalid grid values for field '$fieldName': " . implode(', ', $details)
            );
        }

        return $values;
    }
}

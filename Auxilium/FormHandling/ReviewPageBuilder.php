<?php

namespace Auxilium\FormHandling;

use Auxilium\Auxilium\AuxiliumScript;

/**
 * Builds and processes review page components
 */
final class ReviewPageBuilder
{
    /**
     * Retrieves and processes components for the review page
     *
     * @param array $formSpec The form specification
     * @param array $vars Variables for expression evaluation
     * @return array Array of visible components with processed values
     */
    public function getVisibleComponents(array $formSpec, array $vars): array
    {
        if(!isset($formSpec['reviewPage']['components']['component']))
        {
            return [];
        }

        $components = $formSpec['reviewPage']['components']['component'];
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

            // handle DESCRIPTION_LISTs
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

        // figure out if this might be a checkbox field by checking if the value is a string that matches multiple keys in `formData`
        if(is_string($value) && $this->looksLikeCheckboxField($value, $vars['formData']))
        {
            $value = $this->collectCheckboxValues($value, $vars['formData']);
        }

        // format the arrays nicely for display
        if(is_array($value))
        {
            $value = $this->formatArrayValue($value);
        }

        return ['key' => $key, 'value' => $value];
    }

    /**
     * Heuristic to detect if a field name refers to a checkbox field
     *
     * @param string $fieldName The field name to check
     * @param array $formData The form data
     * @return bool True if this looks like a checkbox field
     */
    private function looksLikeCheckboxField(string $fieldName, array $formData): bool
    {
        $prefix = $fieldName . '-';
        $matchCount = 0;

        foreach(array_keys($formData) as $key)
        {
            if(str_starts_with($key, $prefix))
            {
                $matchCount++;
                if($matchCount >= 2) // multiple matches == checkbox field
                {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Collects all selected checkbox values for a given field
     *
     * @param string $fieldName The base field name (e.g., "do_any_of_the_below_apply_to_you")
     * @param array $formData The form data to search
     * @return array Array of selected values
     */
    private function collectCheckboxValues(string $fieldName, array $formData): array
    {
        $selected = [];
        $prefix = $fieldName . '-';

        foreach($formData as $key => $value)
        {
            // check if this key belongs to our checkbox field
            if(!empty($value) && str_starts_with($key, $prefix))
            {
                // handle the optional "other" option specially
                if($key === $prefix . 'other')
                {
                    // check if the user's entered in text for the option
                    $otherTextKey = $prefix . 'other-text';
                    if(!empty($formData[$otherTextKey]))
                    {
                        $selected[] = 'Other: ' . $formData[$otherTextKey];
                    }
                    else
                    {
                        $selected[] = 'Other';
                    }
                }
                // skip the `other-text` field as it's handled above
                elseif($key !== $prefix . 'other-text')
                {
                    // only add if it's not a boolean `TRUE` string
                    if($value !== 'TRUE' && $value !== true)
                    {
                        $selected[] = $value;
                    }
                }
            }
        }

        return $selected;
    }

    /**
     * Formats an array value for display
     *
     * @param array $value The array to format
     * @return string Formatted string
     */
    private function formatArrayValue(array $value): string
    {
        // filter out empty values and boolean TRUE strings
        $filtered = array_filter($value, fn($v) => !empty($v) && $v !== 'TRUE' && $v !== true);

        if(empty($filtered))
        {
            return 'None selected';
        }

        // format the list with bullet points (won't be formatted cleanly, but hey ho)
        return '• ' . implode("\n• ", $filtered);

        // TODO: make the below work
        // return '<ul><li>' . implode("</li><li>", $filtered) . '</li></ul>';
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
                ], $vars);
            }
        }
        return $processedItems;
    }
}

<?php

namespace Auxilium\FormHandling;

use Auxilium\Auxilium\AuxiliumScript;

/**
 * Builds and processes review page components
 */
class ReviewPageBuilder
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

            // Evaluate dynamic values
            if(isset($component['value']) && str_contains($component['value'], '$'))
            {
                $processedComponent['value'] = AuxiliumScript::evaluate_expression($component['value'], $vars);
            }

            // Handle description lists
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
}

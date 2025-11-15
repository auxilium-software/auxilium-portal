<?php

namespace Auxilium\FormHandling;

use Auxilium\Auxilium\AuxiliumScript;

/**
 * Handles page navigation logic including visibility determination and page finding
 */
class PageNavigator
{
    /**
     * Builds a list of visible pages and their index mapping based on renderIf conditions
     *
     * @param array $pages All pages from the form specification
     * @param array $vars Variables available for expression evaluation
     * @return array Array with 'visiblePages' and 'pageIndexMap' keys
     */
    public function buildVisiblePagesList(array $pages, array $vars): array
    {
        $visiblePages = [];
        $pageIndexMap = [];

        foreach($pages as $i => $page)
        {
            $renderIf = $page['renderIf'] ?? 'true';

            if(AuxiliumScript::evaluate_expression($renderIf, $vars))
            {
                $visiblePages[] = $page;
                $pageIndexMap[] = $i;
            }
        }

        return [
            'visiblePages' => $visiblePages,
            'pageIndexMap' => $pageIndexMap,
        ];
    }

    /**
     * Finds the next visible page based on renderIf conditions
     *
     * @param array $pages All pages from the form specification
     * @param int $currentPage The current page index
     * @param int $direction 1 for forward, -1 for backward
     * @param array $vars Variables available for expression evaluation
     * @return int The index of the next visible page, or -1 if none found
     */
    public function findNextVisiblePage(array $pages, int $currentPage, int $direction, array $vars): int
    {
        $totalPages = count($pages);
        $nextPage = $currentPage + $direction;

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
     * Gets the current visible page index from the stored page index
     *
     * @param int $storedPageIndex The stored page index
     * @param array $pageIndexMap Mapping of visible to actual page indices
     * @return int The current visible page index
     */
    public function getCurrentVisiblePageIndex(int $storedPageIndex, array $pageIndexMap): int
    {
        $currentVisiblePageIndex = array_search(
            needle  : $storedPageIndex,
            haystack: $pageIndexMap,
            strict  : true
        );

        if($currentVisiblePageIndex === false)
        {
            return 0;
        }

        return $currentVisiblePageIndex;
    }

    /**
     * Finds the page index for a given page ID
     *
     * @param string $targetPageId The ID of the page to find
     * @param array $pages All pages from the form specification
     * @return int|null The page index if found, null otherwise
     */
    public function findPageIndexById(string $targetPageId, array $pages): ?int
    {
        foreach($pages as $i => $page)
        {
            if($page['id'] === $targetPageId)
            {
                return $i;
            }
        }

        return null;
    }
}

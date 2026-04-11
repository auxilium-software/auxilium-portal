<?php

namespace Auxilium\Utilities;

final class AdminConsoleUtilities
{
    public static function GrabVariablesToPassIntoTwig(): array
    {
        $navigationTree = ConfigurationUtilities::GetAdminConsoleNavigationTree();
        $currentUri = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

        // annotate each node with 'Active' (exact match or has an active descendant)
        self::annotateActiveState($navigationTree, $currentUri);

        // build breadcrumbs from all items whose href is a path-prefix of the current URI
        $breadcrumbs = self::buildBreadcrumbs($navigationTree, $currentUri);

        return [
            ...self::GetNotFoundContext(),
            'NavigationTree' => $navigationTree,
            'Breadcrumbs' => $breadcrumbs,
        ];
    }

    private static function annotateActiveState(array &$items, string $currentUri): bool
    {
        $anyActive = false;

        foreach ($items as &$item) {
            $item['Active'] = false;

            $childActive = false;
            if (!empty($item['Children'])) {
                $childActive = self::annotateActiveState($item['Children'], $currentUri);
            }

            $itemHref = isset($item['HREF']) ? rtrim($item['HREF'], '/') : null;

            if ($childActive || ($itemHref !== null && $itemHref === $currentUri)) {
                $item['Active'] = true;
                $anyActive = true;
            }
        }

        return $anyActive;
    }

    private static function buildBreadcrumbs(array $navigationTree, string $currentUri): array
    {
        $hrefMap = [];
        self::collectHrefs($navigationTree, $hrefMap);

        $breadcrumbs = [];
        foreach ($hrefMap as $normalizedHref => $item)
        {
            if (
                $normalizedHref === $currentUri ||
                str_starts_with($currentUri, $normalizedHref . '/')
            ) {
                $breadcrumbs[] = [
                    'Label' => $item['Label'],
                    'HREF'  => $item['HREF'],
                ];
            }
        }

        usort($breadcrumbs, static fn($a, $b) => strlen($a['HREF']) <=> strlen($b['HREF']));

        return $breadcrumbs;
    }

    /**
     * flattens the entire navigation tree into a map of normalised-href -> item for quick prefix lookups.
     */
    private static function collectHrefs(array $items, array &$hrefMap): void
    {
        foreach ($items as $item) {
            if (!empty($item['HREF'])) {
                $hrefMap[rtrim($item['HREF'], '/')] = $item;
            }
            if (!empty($item['Children'])) {
                self::collectHrefs($item['Children'], $hrefMap);
            }
        }
    }








    private static function collectNavigableItems(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            if (!empty($item['HREF']) && !empty($item['Label'])) {
                $result[] = [
                    'Label' => $item['Label'],
                    'HREF' => $item['HREF'],
                    'Icon' => $item['Icon'] ?? null,
                    'Type' => $item['Type'] ?? null,
                ];
            }
        }
        return $result;
    }

    public static function GetNotFoundContext(): array
    {
        $navigationTree = ConfigurationUtilities::GetAdminConsoleNavigationTree();
        $currentUri = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

        // flatten every item with an HREF
        $hrefMap = [];
        self::collectHrefs($navigationTree, $hrefMap);

        // find the deepest ancestor whose HREF is a prefix of the current URI
        $bestMatch = null;
        $bestLength = -1;

        foreach ($hrefMap as $normalizedHref => $item) {
            $len = strlen($normalizedHref);
            if (
                $len > $bestLength &&
                ($normalizedHref === $currentUri || str_starts_with($currentUri, $normalizedHref . '/'))
            ) {
                $bestMatch = $item;
                $bestLength = $len;
            }
        }

        // gather navigable suggestions from that node's children, or fall back to the top-level items
        $suggestions = [];
        $parentLabel = null;
        $parentHref = null;

        if ($bestMatch !== null) {
            $parentLabel = $bestMatch['Label'] ?? null;
            $parentHref = $bestMatch['HREF'] ?? null;

            if (!empty($bestMatch['Children'])) {
                $suggestions = self::collectNavigableItems($bestMatch['Children']);
            }
        }

        // if the matched node has no children (it's a leaf, or the root with no children),
        // walk up one URI segment and try its siblings
        if (empty($suggestions) && $bestMatch !== null) {
            $parentPath = dirname($bestMatch['HREF'] ?? '');
            if (isset($hrefMap[rtrim($parentPath, '/')])) {
                $grandparent = $hrefMap[rtrim($parentPath, '/')];
                $parentLabel = $grandparent['Label'] ?? null;
                $parentHref = $grandparent['HREF'] ?? null;
                if (!empty($grandparent['Children'])) {
                    $suggestions = self::collectNavigableItems($grandparent['Children']);
                }
            }
        }

        // ultimate fallback: top-level navigable items
        if (empty($suggestions)) {
            $parentLabel = null;
            $parentHref = null;
            $suggestions = self::collectNavigableItems($navigationTree);
        }

        return [
            'RequestedUri' => $_SERVER['REQUEST_URI'],
            'ParentLabel' => $parentLabel,
            'ParentHref' => $parentHref,
            'Suggestions' => $suggestions,
        ];
    }
}

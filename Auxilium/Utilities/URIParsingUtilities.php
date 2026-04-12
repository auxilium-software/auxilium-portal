<?php

namespace Auxilium\Utilities;

/**
 * Utilities to help with the parsing of URIs.
 */
final class URIParsingUtilities
{
    /**
     * Extracts UUIDs from the URI and returns back the UUID at the requested index.
     *
     * @param int $index Which UUID to return.
     *
     * @return string|null Will either return a UUID as a string, or null if the UUID at the given index does not exist.
     */
    public static function GetUUIDFromURI(int $index = 0): ?string
    {
        $url = $_SERVER['REQUEST_URI'];
        $urlComponents = explode("/", $url);

        $uuids = [];

        foreach($urlComponents as $component)
        {
            if(UUIDUtilities::IsValid(uuid: $component))
            {
                $uuids[] = $component;
            }
        }

        return $uuids[$index] ?? null;
    }
}

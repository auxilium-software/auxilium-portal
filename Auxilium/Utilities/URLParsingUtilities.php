<?php

namespace Auxilium\Utilities;

class URLParsingUtilities
{
    public static function GetUUIDFromURL(int $index = 0): ?string
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
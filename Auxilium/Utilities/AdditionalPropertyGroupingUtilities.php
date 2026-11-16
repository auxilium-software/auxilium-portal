<?php

namespace Auxilium\Utilities;

use Auxilium\Enumerators\AdditionalPropertySubject;
use Auxilium\ServiceInteractions\APIInteractions;

final class AdditionalPropertyGroupingUtilities
{
    public static function GetGroups(AdditionalPropertySubject $subject): array
    {
        $response = APIInteractions::GetRaw(endpoint: $subject->GroupsEndpoint());

        if ($response->StatusCode !== 200 || !is_string($response->Payload) || $response->Payload === '')
        {
            return [];
        }

        $groups = json_decode($response->Payload, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($groups) || !array_is_list($groups))
        {
            return [];
        }

        $groups = array_values(array_filter(
            $groups,
            static fn($group) => is_array($group) && isset($group['id'])
        ));

        usort(
            $groups,
            static fn(array $a, array $b) =>
                [($a['sortOrder'] ?? 0), strtolower($a['canonicalName'] ?? '')]
                <=>
                [($b['sortOrder'] ?? 0), strtolower($b['canonicalName'] ?? '')]
        );

        return $groups;
    }

    public static function BuildSections(array $groups, array $properties, bool $includeEmptyGroups): array
    {
        $propertiesByGroupId = [];
        foreach ($groups as $group)
        {
            $propertiesByGroupId[strtolower($group['id'])] = [];
        }

        $ungrouped = [];
        foreach ($properties as $property)
        {
            if (!is_array($property))
            {
                continue;
            }

            $groupId = isset($property['groupId']) && is_string($property['groupId'])
                ? strtolower($property['groupId'])
                : null;

            if ($groupId !== null && array_key_exists($groupId, $propertiesByGroupId))
            {
                $propertiesByGroupId[$groupId][] = $property;
            }
            else
            {
                $ungrouped[] = $property;
            }
        }

        $sections = [];
        foreach ($groups as $group)
        {
            $groupProperties = self::sortByCreation($propertiesByGroupId[strtolower($group['id'])]);

            if (!$includeEmptyGroups && count($groupProperties) === 0)
            {
                continue;
            }

            $sections[] = [
                'Group' => $group,
                'Properties' => $groupProperties,
            ];
        }

        if (count($ungrouped) > 0 || count($sections) === 0)
        {
            $sections[] = [
                'Group' => null,
                'Properties' => self::sortByCreation($ungrouped),
            ];
        }

        return $sections;
    }

    private static function sortByCreation(array $properties): array
    {
        // ISO 8601 timestamps from the API sort correctly as strings
        usort(
            $properties,
            static fn(array $a, array $b) => strcmp((string)($a['createdAt'] ?? ''), (string)($b['createdAt'] ?? ''))
        );

        return $properties;
    }
}


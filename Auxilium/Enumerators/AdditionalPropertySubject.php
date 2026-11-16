<?php

namespace Auxilium\Enumerators;

enum AdditionalPropertySubject: string
{
    case CASES = "cases";
    case USERS = "users";



    public function GroupsEndpoint(): string
    {
        return '/api/v3/' . $this->value . '/additional_property_groups';
    }

    public function PropertiesEndpoint(string $subjectId): string
    {
        return '/api/v3/' . $this->value . '/' . $subjectId . '/additional_properties';
    }

    public function PropertyManagerClass(): string
    {
        return match ($this) {
            self::CASES => 'CasePropertyManager',
            self::USERS => 'UserPropertyManager',
        };
    }
}

<?php

namespace Auxilium\Utilities;

use Auxilium\Enumerators\EnvironmentVariable;
use Exception;
use SimpleXMLElement;
use Symfony\Component\Yaml\Yaml;

final class ConfigurationUtilities
{
    private const XML_NS = 'http://www.w3.org/XML/1998/namespace';
    private const REPEATABLE_ELEMENT_NAMES = ['page', 'component', 'option', 'statement', 'item', 'translation'];

    public static array $SystemConfiguration;
    public static array $UserConfiguration;


    public static function GetSystemConfiguration(): array
    {
        if(isset(self::$SystemConfiguration))
        {
            return self::$SystemConfiguration;
        }

        $filePath = __DIR__ . "/../../Configuration/System/System.yaml";

        if($filePath !== false)
        {
            $filePath = str_replace(search: '"', replace: '', subject: $filePath);
            if(file_exists($filePath))
            {
                $temp = file_get_contents($filePath);
                self::$SystemConfiguration = Yaml::parse($temp);
                return self::$SystemConfiguration;
            }
            throw new Exception("Configuration file not found at " . $filePath);
        }
        throw new Exception("Configuration file not specified");
    }
    public static function GetUserConfiguration(): array
    {
        if(isset(self::$UserConfiguration))
        {
            return self::$UserConfiguration;
        }

        $filePath = getenv(EnvironmentVariable::CONFIG_FILE_LOCATION->value);

        if($filePath !== false)
        {
            $filePath = str_replace(search: '"', replace: '', subject: $filePath);
            if(file_exists($filePath))
            {
                $temp = file_get_contents($filePath);
                self::$UserConfiguration = Yaml::parse($temp);
                return self::$UserConfiguration;
            }
            throw new Exception("Configuration file not found at " . $filePath);
        }
        throw new Exception("Configuration file not specified");
    }


    public static function GetFormDefinition(string $target): array
    {
        $xmlString = file_get_contents(__DIR__ . "/../../Configuration/FormDefinitions/" . $target . ".aux3form");
        $xml = new SimpleXMLElement($xmlString);
        $temp = self::xmlToArray($xml);

        $temp["id"] = "AuxiliumFormDefinition" . $temp["id"];

        $temp = self::normalizeRepeatableElements($temp);

        return $temp;
    }

    private static function normalizeRepeatableElements(mixed $node): mixed
    {
        if(!is_array($node))
        {
            return $node;
        }

        $result = [];

        foreach($node as $key => $value)
        {
            if(is_array($value))
            {
                $value = self::normalizeRepeatableElements($value);

                if(in_array($key, self::REPEATABLE_ELEMENT_NAMES, true) && !array_is_list($value))
                {
                    $value = [$value];
                }
            }

            $result[$key] = $value;
        }

        return $result;
    }

    private static function xmlToArray(SimpleXMLElement $node): array|string|int|float|bool
    {
        $attributes = [];
        foreach($node->attributes() as $key => $value)
        {
            $attributes[$key] = (string) $value;
        }
        foreach($node->attributes(self::XML_NS) as $key => $value)
        {
            $attributes[$key] = (string) $value;
        }

        $children = $node->children();

        if(count($children) === 0)
        {
            $text = trim((string) $node);

            if(empty($attributes))
            {
                return $text;
            }

            if(count($attributes) === 1 && isset($attributes['type']))
            {
                return self::castTypedValue($text, $attributes['type']);
            }

            return ['@attributes' => $attributes, '#text' => $text];
        }

        $result = [];
        if(!empty($attributes))
        {
            $result['@attributes'] = $attributes;
        }

        foreach($children as $childName => $child)
        {
            $childValue = self::xmlToArray($child);

            if(isset($result[$childName]))
            {
                if(!is_array($result[$childName]) || !array_is_list($result[$childName]))
                {
                    $result[$childName] = [$result[$childName]];
                }
                $result[$childName][] = $childValue;
            }
            else
            {
                $result[$childName] = $childValue;
            }
        }

        return $result;
    }

    private static function castTypedValue(string $text, string $type): int|float|bool|string
    {
        return match(strtolower($type))
        {
            'integer', 'int' => (int) $text,
            'float', 'double', 'decimal' => (float) $text,
            'boolean', 'bool' => filter_var($text, FILTER_VALIDATE_BOOLEAN),
            default => $text,
        };
    }

    public static function GetAdminConsoleNavigationTree(): array
    {
        $filePath = __DIR__ . '/../../Configuration/System/AdminConsoleNavigation.yaml';
        $fileContents = file_get_contents($filePath);
        return Yaml::parse($fileContents);
    }
    public static function GetAdminConsoleQuickActions(): array
    {
        $filePath = __DIR__ . '/../../Configuration/System/AdminConsoleQuickActions.yaml';
        $fileContents = file_get_contents($filePath);
        return Yaml::parse($fileContents);
    }
}

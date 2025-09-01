<?php

namespace Auxilium\Utilities;

use Auxilium\Enumerators\EnvironmentVariable;
use Exception;
use SimpleXMLElement;
use Symfony\Component\Yaml\Yaml;

class ConfigurationUtilities
{
    public static array $Configuration;


    public static function GetLanguagePack(string $language): array
    {
        $temp = file_get_contents(__DIR__ . "/../../Configuration/LanguagePacks/$language.json");
        $temp = json_decode($temp, true);
        return $temp;
    }

    public static function GetConfiguration(): array
    {
        if(isset(self::$Configuration))
        {
            return self::$Configuration;
        }

        $filePath = getenv(EnvironmentVariable::CONFIG_FILE_LOCATION->value);

        if($filePath !== false)
        {
            $filePath = str_replace(search: '"', replace: '', subject: $filePath);
            if(file_exists($filePath))
            {
                $temp = file_get_contents($filePath);
                self::$Configuration = Yaml::parse($temp);
                return self::$Configuration;
            }
            throw new Exception("Configuration file not found at " . $filePath);
        }
        throw new Exception("Configuration file not specified");
    }


    public static function GetFormDefinition(string $target): array
    {
        $temp = file_get_contents(__DIR__ . "/../../Configuration/FormDefinitions/" . $target . ".auxform.xml");
        $temp = new SimpleXMLElement($temp);
        $temp = json_decode(json_encode($temp), true);

        $temp["id"] = "AuxiliumFormDefinition" . $temp["id"];

        // if there's only one wizard page
        if(!array_is_list($temp["pages"]["page"]))
        {
            $temp["pages"]["page"] = [$temp["pages"]["page"]];
        }

        foreach($temp["pages"]["page"] as &$page)
        {
            // Ensure components.component is a list
            if(
                isset($page["components"]["component"]) &&
                !array_is_list($page["components"]["component"])
            )
            {
                $page["components"]["component"] = [$page["components"]["component"]];
            }
        }
        unset($page);


        // sorts out the options
        foreach($temp["pages"]["page"] as $iValue)
        {
            if(
                array_key_exists("components", $iValue)
                && array_key_exists("component", $iValue['components'])
                && is_array($iValue["components"]["component"])
            )
            {
                for($ii = 0, $iiMax = count($iValue["components"]["component"]); $ii < $iiMax; $ii++)
                {
                }
            }
        }

        return $temp;
    }
}
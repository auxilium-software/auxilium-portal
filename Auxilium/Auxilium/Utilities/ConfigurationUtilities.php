<?php

namespace Auxilium\Utilities;

use SimpleXMLElement;

class ConfigurationUtilities
{
    public static string $ConfigurationDirectory = __DIR__ . "/../../Configuration";





    public static function GetFormDefinition(string $target): array
    {
        $temp = file_get_contents(self::$ConfigurationDirectory . "/FormDefinitions/" . $target . ".auxform.xml");
        $temp = new SimpleXMLElement($temp);
        $temp = json_decode(json_encode($temp), true);

        $temp["id"] = "AuxiliumFormDefinition" . $temp["id"];

        // if there's only one wizard page
        if (!array_is_list($temp["pages"]["page"])) {
            $temp["pages"]["page"] = [ $temp["pages"]["page"] ];
        }

        foreach ($temp["pages"]["page"] as &$page)
        {
            // Ensure components.component is a list
            if (
                isset($page["components"]["component"]) &&
                !array_is_list($page["components"]["component"])
            ) {
                $page["components"]["component"] = [ $page["components"]["component"] ];
            }
        }
        unset($page);


        // sorts out the options
        foreach($temp["pages"]["page"] as $iValue)
        {
            if (array_key_exists("components", $iValue) && is_array($iValue["components"]["component"]))
            {
                for ($ii = 0, $iiMax = count($iValue["components"]["component"]); $ii < $iiMax; $ii++)
                {
                }
            }
        }

        return $temp;
    }
}
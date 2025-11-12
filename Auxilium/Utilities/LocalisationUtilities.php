<?php

namespace Auxilium\Utilities;

use Auxilium\Enumerators\CookieKey;
use Auxilium\Enumerators\SessionKey;
use Auxilium\SessionHandling\CookieHandling;

/**
 * Utilities to help with Localisation.
 */
class LocalisationUtilities
{
    public static ?array $LanguagePackCache = null;


    /**
     * TODO: finish this function lol
     *
     * @param string $text The text to translate.
     * @return string The translated text.
     */
    public static function Translate(string $text, array $subs = []): string
    {
        $language = CookieHandling::GetCookieValue(targetCookie: CookieKey::LANGUAGE, default: "en-GB");

        $sanitisedLanguage = "en-GB";

        switch ($language)
        {
            case "en-GB":
                $sanitisedLanguage = "en-GB";
                break;
            case "cy-GB":
                $sanitisedLanguage = "cy-GB";
                break;
            case "zh":
                $sanitisedLanguage = "zh";
                break;
        }

        if($sanitisedLanguage === "en-GB")
        {
            return $text;
        }

        if(self::$LanguagePackCache === null)
        {
            self::$LanguagePackCache = json_decode(
                file_get_contents(filename: __DIR__ . "/../../Configuration/Localisation/LanguagePacks/$sanitisedLanguage.json"),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        }

        if(array_key_exists(key: $text, array: self::$LanguagePackCache))
        {
            return self::$LanguagePackCache[$text];
        }


        return $text;
    }
}

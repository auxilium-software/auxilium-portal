<?php

namespace Auxilium\Utilities;

use Auxilium\Enumerators\CookieKey;
use Auxilium\Enumerators\SessionKey;
use Auxilium\SessionHandling\CookieHandling;
use Exception;
use JsonException;

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
     *
     * @throws JsonException
     * @throws Exception
     */
    public static function Translate(string $text, array $subs = []): string
    {
        $language = CookieHandling::GetCookieValue(targetCookie: CookieKey::LANGUAGE, default: "en-GB");

        $sanitisedLanguage = match ($language)
        {
            "en-GB" => "en-GB",
            "cy-GB" => "cy-GB",
            "zh" => "zh",
            default => throw new \Exception("invalid language code"),
        };

        // Get the translated text (or fallback to original)
        $translatedText = $text;

        if($sanitisedLanguage !== "en-GB")
        {
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
                $translatedText = self::$LanguagePackCache[$text];
            }
            else
            {
                self::logMissingTranslation($text, $sanitisedLanguage);
            }
        }

        // Apply substitutions
        if (!empty($subs)) {
            $translatedText = str_replace(
                array_keys($subs),
                array_values($subs),
                $translatedText
            );
        }

        return $translatedText;
    }

    /**
     * @throws JsonException
     */
    private static function logMissingTranslation(string $text, string $language): void
    {
        $filePath = __DIR__ . "/../../LocalStorage/Cache/Development/MissingTranslations.json";

        $translations = file_exists($filePath)
            ? json_decode(file_get_contents($filePath), true, flags: JSON_THROW_ON_ERROR)
            : [];

        if (!in_array($text, $translations[$language] ?? [], true)) {
            $translations[$language][] = $text;
            file_put_contents(
                $filePath,
                json_encode($translations, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
        }
    }
}
